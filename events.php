<?php
// public/events.php - Entegre Edilmiş Yeni Yetki Sistemi

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php'; // Gelişmiş yetki kontrolleri
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover
require_once BASE_PATH . '/src/functions/sql_security_functions.php'; // render_user_info_with_popover için


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et (her sayfanın başında olmalı)
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

// Kullanıcı durum değişkenlerini burada tanımla (check_user_session_validity'den sonra güncel session kullanılır)
$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;
$current_user_roles_names = $_SESSION['user_roles'] ?? [];

$page_title = "Etkinlikler";

// Yetki kontrolleri - Gelişmiş yetki sistemi ile
$can_create_event = $current_user_id && $current_user_is_approved && can_user_create_content($pdo, 'event');

$active_events = [];
$past_events = [];

// Sayfa herkese açık - sadece içerik yetki kontrolü var
$can_view_events_page = true;

try {
    $sql_select_fields = "SELECT
                            e.id, e.title, e.description, e.event_datetime, e.status, e.visibility,
                            e.image_path_1, e.created_by_user_id, e.max_participants,
                            (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.participation_status = 'attending') AS participant_count,
                            u.username AS creator_username,
                            u.avatar_path AS creator_avatar_path,
                            u.ingame_name AS creator_ingame_name,
                            u.discord_username AS creator_discord_username,
                            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS creator_event_count,
                            (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS creator_gallery_count,
                            (SELECT GROUP_CONCAT(r.name ORDER BY r.priority ASC SEPARATOR ',')
                             FROM user_roles ur_creator
                             JOIN roles r ON ur_creator.role_id = r.id
                             WHERE ur_creator.user_id = u.id) AS creator_roles_list,
                            (SELECT r.color 
                             FROM user_roles ur_primary 
                             JOIN roles r ON ur_primary.role_id = r.id 
                             WHERE ur_primary.user_id = u.id 
                             ORDER BY r.priority ASC 
                             LIMIT 1) AS creator_primary_role_color";

    $sql_from_join = " FROM events e JOIN users u ON e.created_by_user_id = u.id";
    $sql_params = [];
    $visibility_conditions_array = [];

    // Görünürlük koşulları - Gelişmiş yetki sistemi ile
    if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'event.view_all', $current_user_id))) {
        $visibility_conditions_array[] = "1=1"; // Admin veya özel yetkili her şeyi görür
    } else {
        $specific_visibility_or_conditions = [];
        
        // Public events - giriş yapmayan kullanıcılar dahil
        if (has_permission($pdo, 'event.view_public', $current_user_id) || !$current_user_id) {
            $specific_visibility_or_conditions[] = "e.visibility = 'public'";
        }
        
        // Members only events - sadece onaylı üyeler
        if ($current_user_id && $current_user_is_approved && has_permission($pdo, 'event.view_members_only', $current_user_id)) {
            $specific_visibility_or_conditions[] = "e.visibility = 'members_only'";
        }
        
        // Faction only events - belirli rollere sahip olanlar
        if ($current_user_id && $current_user_is_approved && has_permission($pdo, 'event.view_faction_only', $current_user_id)) {
            $stmt_user_roles_ids = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :current_user_id_for_faction");
            $stmt_user_roles_ids->execute([':current_user_id_for_faction' => $current_user_id]);
            $user_role_ids_for_faction = $stmt_user_roles_ids->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($user_role_ids_for_faction)) {
                $role_placeholders_faction = [];
                foreach ($user_role_ids_for_faction as $idx => $role_id_faction) {
                    $placeholder_faction = ':faction_role_id_' . $idx;
                    $role_placeholders_faction[] = $placeholder_faction;
                    $sql_params[$placeholder_faction] = $role_id_faction;
                }
                $in_clause_faction = implode(',', $role_placeholders_faction);
                if (!empty($in_clause_faction)) {
                     $specific_visibility_or_conditions[] = "(e.visibility = 'faction_only' AND EXISTS (
                                                    SELECT 1 FROM event_visibility_roles evr_check
                                                    WHERE evr_check.event_id = e.id AND evr_check.role_id IN (" . $in_clause_faction . ")
                                                 ))";
                }
            }
        }
        
        if (!empty($specific_visibility_or_conditions)) {
            $visibility_conditions_array[] = "(" . implode(" OR ", $specific_visibility_or_conditions) . ")";
        } else {
            $visibility_conditions_array[] = "1=0"; // Hiçbir şey göremez
        }
    }
    
    $final_visibility_condition = implode(" AND ", $visibility_conditions_array);

    // Aktif etkinlikleri çek
    $sql_active = $sql_select_fields . $sql_from_join .
                  " WHERE e.status = 'active' AND ($final_visibility_condition)" .
                  " ORDER BY e.event_datetime ASC";
    $stmt_active = $pdo->prepare($sql_active);
    $stmt_active->execute($sql_params);
    $active_events = $stmt_active->fetchAll(PDO::FETCH_ASSOC);

    // Geçmiş etkinlikleri çek
    $sql_past = $sql_select_fields . $sql_from_join .
                " WHERE e.status = 'past' AND ($final_visibility_condition)" .
                " ORDER BY e.event_datetime DESC";
    $stmt_past = $pdo->prepare($sql_past);
    $stmt_past->execute($sql_params);
    $past_events = $stmt_past->fetchAll(PDO::FETCH_ASSOC);

    // Her etkinlik için görünürlük rollerini al ve içerik erişim kontrolü yap
    foreach ([$active_events, $past_events] as &$events_array) {
        foreach ($events_array as $key => &$event) {
            // Etkinlik verilerini can_user_access_content fonksiyonu için hazırla
            $content_data = [
                'visibility' => $event['visibility'],
                'user_id' => $event['created_by_user_id']
            ];
            
            // Etkinliğin görünürlük rollerini al
            $content_role_ids = get_content_visibility_roles($pdo, 'event', $event['id']);
            
            // Kullanıcının bu etkinliğe erişimi var mı kontrol et
            $can_access = can_user_access_content($pdo, $content_data, $content_role_ids, $current_user_id);
            
            if (!$can_access) {
                // Erişimi yoksa etkinliği kaldır
                unset($events_array[$key]);
                continue;
            }
            
            // Creator için birincil rol rengi belirleme
            if (!empty($event['creator_roles_list'])) {
                $creator_roles = explode(',', $event['creator_roles_list']);
                $event['creator_primary_role'] = trim($creator_roles[0]); // İlk rol (en yüksek öncelikli)
            } else {
                $event['creator_primary_role'] = 'member'; // Varsayılan rol
            }
        }
        // Array key'lerini yeniden sırala
        $events_array = array_values($events_array);
    }

} catch (PDOException $e) {
    error_log("Etkinlikleri listeleme hatası (events.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlikler yüklenirken bir veritabanı sorunu oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Events Page Styles - Consistent with Gallery */


.events-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 3rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.events-header h2 {
    color: var(--gold);
    font-size: 2.5rem;
    font-family: var(--font);
    margin: 0;
    font-weight: 300;
    letter-spacing: -0.5px;
}

.btn-outline-turquase {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    border: 1px solid var(--turquase);
    background-color: transparent;
    color: var(--turquase);
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-outline-turquase:hover {
    background-color: var(--turquase);
    color: var(--charcoal);
}

.btn-outline-turquase i.fas {
    font-size: 0.9em;
}

.info-message {
    text-align: center; 
    margin-bottom: 2rem; 
    background-color: rgba(42, 189, 168, 0.1); 
    color: var(--turquase); 
    border: 1px solid rgba(42, 189, 168, 0.3); 
    padding: 1rem; 
    border-radius: 6px;
    font-size: 0.9rem;
}

.info-message a { 
    color: var(--turquase); 
    font-weight: 500; 
    text-decoration: none; 
}

.info-message a:hover { 
    text-decoration: underline; 
}

.section-title {
    color: var(--gold);
    font-size: 1.75rem;
    margin: 2rem 0 1.5rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
    font-weight: 400;
}

.event-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.event-card {
    background: linear-gradient(135deg, var(--charcoal), var(--darker-gold-2));
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    overflow: hidden;
    transition: border-color 0.2s ease;
    display: flex;
    flex-direction: column;
}

.event-card:hover {
    border-color: var(--gold);
}

.event-card-image-wrapper {
    position: relative;
    overflow: hidden;
    line-height: 0;
}

.event-card-image-wrapper a { 
    display: block;
}

.event-card-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
    transition: opacity 0.2s ease;
}

.event-card:hover .event-card-image { 
    opacity: 0.95;
}

.event-card-body {
    padding: 1rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.event-card-title a { 
    font-size: 1.25rem;
    color: var(--gold);
    margin: 0 0 0.75rem 0;
    font-family: var(--font);
    line-height: 1.3;
    font-weight: 500;
    text-decoration: none;
    display: block; 
}

.event-card-title a:hover {
    color: var(--light-gold);
}

.event-card-date {
    font-size: 0.85rem;
    color: var(--light-grey);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.event-card-date strong {
    color: var(--lighter-grey);
    font-weight: 500;
}

.event-card-date .date-icon {
    color: var(--gold);
    font-size: 0.9em;
}

.event-card-description-short {
    font-size: 0.9rem;
    color: var(--light-grey);
    line-height: 1.5;
    margin-bottom: 1rem;
    flex-grow: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.event-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid var(--darker-gold-2);
    font-size: 0.85rem;
}

.event-footer-actions { 
    display: flex;
    align-items: center;
    gap: 1rem;
}

.participant-count-info {
    font-size: 0.8rem;
    color: var(--light-grey);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.participant-count-info i.fas {
    color: var(--gold);
    font-size: 0.9em;
}

.participant-count-info strong {
    color: var(--lighter-grey);
}

.btn-view-event-detail { 
    color: var(--turquase);
    font-weight: 500;
    text-decoration: none;
    padding: 0.4rem 0.75rem;
    border-radius: 4px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    display: inline-flex; 
    align-items: center; 
    gap: 0.25rem; 
    font-size: 0.8rem;
}

.btn-view-event-detail:hover {
    border-color: var(--turquase);
    color: var(--turquase);
}

.accordion-section {
    margin-top: 3rem;
    background-color: var(--charcoal);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--darker-gold-2);
}

.accordion-button {
    background-color: rgba(34, 34, 34, 0.5);
    color: var(--gold);
    cursor: pointer;
    padding: 1rem 1.25rem;
    width: 100%;
    border: none;
    text-align: left;
    outline: none;
    font-size: 1.25rem;
    transition: background-color 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: var(--font);
    font-weight: 500;
}

.accordion-button:hover,
.accordion-button.active {
    background-color: rgba(82, 56, 10, 0.3);
}

.accordion-icon {
    font-size: 1rem;
    transition: transform 0.2s ease;
}

.accordion-button.active .accordion-icon {
    transform: rotate(90deg);
}

.accordion-content {
    padding: 0 1.25rem 1.25rem 1.25rem;
    background-color: transparent;
    display: none;
    border-top: 1px solid var(--darker-gold-2);
}

.accordion-content .event-grid {
    margin-top: 1rem;
}

.past-event-card .event-card-image {
    filter: grayscale(40%);
    opacity: 0.9;
}

.past-event-card .event-card-title a { 
    color: var(--light-grey);
}

.past-event-card:hover .event-card-title a {
    color: var(--gold);
}

.no-events-message {
    text-align: center;
    font-size: 1rem;
    color: var(--light-grey);
    padding: 3rem 2rem;
    border: 1px dashed var(--grey);
    border-radius: 6px;
    margin-top: 2rem;
}

.no-events-message a { 
    color: var(--turquase); 
    font-weight: 500; 
    text-decoration: none; 
}

.no-events-message a:hover { 
    text-decoration: underline; 
}

/* Dinamik rol renkleri için - CSS custom properties kullanarak */
.event-creator-info-wrapper[data-role-color] a {
    color: var(--dynamic-role-color) !important;
}

/* Creator info styles */
.event-creator-info-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.creator-avatar-small {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-1);
    flex-shrink: 0;
}

.creator-name-link a {
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    color: var(--lighter-grey);
    transition: color 0.2s ease;
}

.creator-name-link a:hover {
    color: var(--gold);
}

/* Responsive Design */
@media (max-width: 768px) {
    .events-page-container { 
        padding: 1.5rem 1rem; 
    }
    
    .events-header { 
        flex-direction: column; 
        align-items: stretch; 
        gap: 1rem; 
        text-align: center;
    }
    
    .events-header h2 {
        font-size: 2rem;
    }
    
    .btn-outline-turquase { 
        width: 100%; 
        justify-content: center;
    }
    
    .event-grid { 
        grid-template-columns: 1fr; 
        gap: 1.25rem; 
    }
    
    .accordion-button { 
        font-size: 1.1rem; 
        padding: 0.875rem 1rem;
    }
    
    .event-card-footer { 
        flex-direction: column; 
        align-items: flex-start; 
        gap: 0.75rem;
    }
    
    .event-footer-actions { 
        width: 100%; 
        justify-content: space-between;
    }
}

@media (max-width: 480px) {
    .events-page-container {
        padding: 1rem 0.75rem;
    }
    
    .events-header h2 {
        font-size: 1.75rem;
    }
    
    .event-card-body {
        padding: 0.75rem;
    }
    
    .accordion-button {
        font-size: 1rem;
        padding: 0.75rem;
    }
}
</style>

<main class="main-content">
    <div class="site-container">
        <div class="events-header">
            <h2><?php echo htmlspecialchars($page_title); ?></h2>
            <?php if ($can_create_event): ?>
                <a href="create_event.php" class="btn-outline-turquase">
                    <i class="fas fa-plus-circle"></i> Yeni Etkinlik Oluştur
                </a>
            <?php endif; ?>
        </div>

        <?php // Hata ve başarı mesajları header.php'de gösteriliyor ?>

        <?php if (!$current_user_is_logged_in && (!empty($active_events) || !empty($past_events))): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> 
                Şu anda sadece herkese açık etkinlikleri görüntülüyorsunuz. Daha fazla etkinliğe erişmek için 
                <a href="<?php echo get_auth_base_url(); ?>/login.php">giriş yapın</a> ya da 
                <a href="<?php echo get_auth_base_url(); ?>/register.php">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <h3 class="section-title">Aktif ve Yaklaşan Etkinlikler</h3>
        <?php if (empty($active_events)): ?>
            <p class="no-events-message">
                <i class="fas fa-calendar-times"></i><br>
                Şu anda sizin rolünüze uygun aktif veya yaklaşan bir etkinlik bulunmamaktadır.
                <?php if ($can_create_event): ?>
                    <br>İlk etkinliği <a href="create_event.php">sen oluştur</a>!
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="event-grid">
                <?php foreach ($active_events as $event): ?>
                    <div class="event-card">
                        <div class="event-card-image-wrapper">
                             <a href="event_detail.php?id=<?php echo $event['id']; ?>">
                                <img src="<?php echo !empty($event['image_path_1']) ? '/public/' . htmlspecialchars($event['image_path_1']) : 'https://via.placeholder.com/400x200/1a1101/bd912a?text=' . urlencode(substr($event['title'],0,20)); ?>"
                                     alt="<?php echo htmlspecialchars($event['title']); ?>" class="event-card-image">
                            </a>
                        </div>
                        <div class="event-card-body">
                            <h3 class="event-card-title">
                                <a href="event_detail.php?id=<?php echo $event['id']; ?>">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </a>
                            </h3>
                            <p class="event-card-date">
                                <i class="fas fa-calendar-alt date-icon"></i>
                                <strong><?php echo date('d F Y, H:i', strtotime($event['event_datetime'])); ?></strong>
                            </p>
                            <p class="event-card-description-short">
                                <?php
                                $description_excerpt_active = mb_substr(strip_tags($event['description'] ?? ''), 0, 120, 'UTF-8');
                                if (mb_strlen(strip_tags($event['description'] ?? ''), 'UTF-8') > 120) {
                                    $description_excerpt_active .= '...';
                                }
                                echo htmlspecialchars($description_excerpt_active);
                                ?>
                            </p>
                            <div class="event-card-footer">
                                <?php
                                $creator_data_for_popover = [
                                    'id' => $event['created_by_user_id'],
                                    'username' => $event['creator_username'],
                                    'avatar_path' => $event['creator_avatar_path'],
                                    'ingame_name' => $event['creator_ingame_name'],
                                    'discord_username' => $event['creator_discord_username'],
                                    'user_event_count' => $event['creator_event_count'],
                                    'user_gallery_count' => $event['creator_gallery_count'],
                                    'user_roles_list' => $event['creator_roles_list'],
                                    'primary_role' => $event['creator_primary_role'] ?? 'member',
                                    'primary_role_color' => $event['creator_primary_role_color'] ?? '#0000ff'
                                ];
                                echo render_user_info_with_popover(
                                    $pdo,
                                    $creator_data_for_popover,
                                    'creator-name-link',
                                    'creator-avatar-small',
                                    'event-creator-info-wrapper'
                                );
                                ?>
                                <div class="event-footer-actions">
                                    <span class="participant-count-info" title="Katılımcı Sayısı">
                                        <i class="fas fa-users"></i>
                                        <strong><?php echo htmlspecialchars($event['participant_count'] ?? 0); ?></strong>
                                        <?php if ($event['max_participants'] !== null): ?>
                                            / <?php echo htmlspecialchars($event['max_participants']); ?>
                                        <?php endif; ?>
                                    </span>
                                    <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn-view-event-detail">
                                        Detaylar <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="accordion-section">
            <button type="button" class="accordion-button">
                Geçmiş Etkinlikler (<?php echo count($past_events); ?>)
                <i class="fas fa-chevron-right accordion-icon"></i>
            </button>
            <div class="accordion-content">
                <?php if (empty($past_events)): ?>
                    <p class="no-events-message">
                        <i class="fas fa-history"></i><br>
                        Henüz geçmiş bir etkinlik bulunmamaktadır.
                    </p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($past_events as $event): ?>
                            <div class="event-card past-event-card">
                                 <div class="event-card-image-wrapper">
                                     <a href="event_detail.php?id=<?php echo $event['id']; ?>">
                                        <img src="<?php echo !empty($event['image_path_1']) ? '/public/' . htmlspecialchars($event['image_path_1']) : 'https://via.placeholder.com/400x200/333333/808080?text=Gecmis+Etkinlik'; ?>"
                                             alt="<?php echo htmlspecialchars($event['title']); ?>" class="event-card-image">
                                    </a>
                                </div>
                                <div class="event-card-body">
                                    <h4 class="event-card-title">
                                        <a href="event_detail.php?id=<?php echo $event['id']; ?>">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </a>
                                    </h4>
                                    <p class="event-card-date">
                                        <i class="fas fa-calendar-times date-icon" style="color:var(--light-grey);"></i>
                                        <strong><?php echo date('d F Y, H:i', strtotime($event['event_datetime'])); ?> (Geçmiş)</strong>
                                    </p>
                                    <p class="event-card-description-short">
                                        <?php
                                        $description_excerpt_past = mb_substr(strip_tags($event['description'] ?? ''), 0, 100, 'UTF-8');
                                        if (mb_strlen(strip_tags($event['description'] ?? ''), 'UTF-8') > 100) {
                                            $description_excerpt_past .= '...';
                                        }
                                        echo htmlspecialchars($description_excerpt_past);
                                        ?>
                                    </p>
                                    <div class="event-card-footer">
                                        <?php
                                        $creator_data_past_for_popover = [
                                            'id' => $event['created_by_user_id'],
                                            'username' => $event['creator_username'],
                                            'avatar_path' => $event['creator_avatar_path'],
                                            'ingame_name' => $event['creator_ingame_name'],
                                            'discord_username' => $event['creator_discord_username'],
                                            'user_event_count' => $event['creator_event_count'],
                                            'user_gallery_count' => $event['creator_gallery_count'],
                                            'user_roles_list' => $event['creator_roles_list'],
                                            'primary_role' => $event['creator_primary_role'] ?? 'member',
                                            'primary_role_color' => $event['creator_primary_role_color'] ?? '#0000ff'
                                        ];
                                        echo render_user_info_with_popover(
                                            $pdo,
                                            $creator_data_past_for_popover,
                                            'creator-name-link',
                                            'creator-avatar-small',
                                            'event-creator-info-wrapper'
                                        );
                                        ?>
                                        <div class="event-footer-actions">
                                            <span class="participant-count-info" title="Katılımcı Sayısı">
                                                <i class="fas fa-users"></i>
                                                <strong><?php echo htmlspecialchars($event['participant_count'] ?? 0); ?></strong>
                                                <?php if ($event['max_participants'] !== null): ?>
                                                    / <?php echo htmlspecialchars($event['max_participants']); ?>
                                                <?php endif; ?>
                                            </span>
                                            <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn-view-event-detail">
                                                Detaylar <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Accordion functionality
    const accordionButtons = document.querySelectorAll('.accordion-button');
    accordionButtons.forEach(button => {
        button.addEventListener('click', function () {
            this.classList.toggle('active');
            const content = this.nextElementSibling;
            const icon = this.querySelector('.accordion-icon');

            if (content.style.display === "block") {
                content.style.display = "none";
                if(icon) icon.classList.remove('fa-chevron-down');
                if(icon) icon.classList.add('fa-chevron-right');
            } else {
                content.style.display = "block";
                if(icon) icon.classList.remove('fa-chevron-right');
                if(icon) icon.classList.add('fa-chevron-down');
            }
        });
    });

    // Enhanced card interactions
    const eventCards = document.querySelectorAll('.event-card');
    eventCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Intersection Observer for card animations
    if ('IntersectionObserver' in window) {
        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -30px 0px'
        });
        
        eventCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(15px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            cardObserver.observe(card);
        });
    }

    // Smooth scroll to accordion when opened
    accordionButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.classList.contains('active')) {
                setTimeout(() => {
                    this.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 300);
            }
        });
    });

    // Dinamik rol renklerini uygula
    const creatorInfoWrappers = document.querySelectorAll('.event-creator-info-wrapper');
    creatorInfoWrappers.forEach(wrapper => {
        const roleColor = wrapper.dataset.roleColor;
        if (roleColor) {
            wrapper.style.setProperty('--dynamic-role-color', roleColor);
        }
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>