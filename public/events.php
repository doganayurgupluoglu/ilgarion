<?php
// public/events.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover

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
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false; // TANIMLAMA BURADA
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;
$current_user_roles_names = $_SESSION['user_roles'] ?? [];

$page_title = "Etkinlikler";

// Yetki kontrolleri
$can_create_event = $current_user_id && $current_user_is_approved && has_permission($pdo, 'event.create', $current_user_id);

$active_events = [];
$past_events = [];
$can_view_events_page = false;
$access_message = "";

// Sayfa görüntüleme yetkisi kontrolü
if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'event.view_all', $current_user_id))) {
    $can_view_events_page = true;
} elseif ($current_user_is_approved) {
    if (has_permission($pdo, 'event.view_members_only', $current_user_id) || has_permission($pdo, 'event.view_faction_only', $current_user_id) || has_permission($pdo, 'event.view_public', $current_user_id) ) {
        $can_view_events_page = true;
    } else {
        $user_roles = $_SESSION['user_roles'] ?? [];
        if (in_array('dis_uye', $user_roles) && count($user_roles) === 1) {
             // Sadece 'dis_uye' ise ve özel view yetkileri yoksa, sadece public olanları görebilir.
             // Bu durum sorguda ele alınacak. Şimdilik sayfaya erişimine izin verelim.
            if (has_permission($pdo, 'event.view_public', $current_user_id)){ // Eğer public görme yetkisi varsa
                 $can_view_events_page = true;
            } else {
                $access_message = "Etkinlikleri görüntüleme yetkiniz bulunmamaktadır.";
            }
        } else {
             $access_message = "Etkinlikleri görüntüleme yetkiniz bulunmamaktadır.";
        }
    }
} elseif (has_permission($pdo, 'event.view_public', null)) { // Giriş yapmamış kullanıcılar için public etkinlikleri görme yetkisi
    $can_view_events_page = true;
} else {
    $access_message = "Etkinlikleri görmek için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
}


if ($can_view_events_page) {
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
                                (SELECT GROUP_CONCAT(r.name SEPARATOR ',')
                                 FROM user_roles ur_creator
                                 JOIN roles r ON ur_creator.role_id = r.id
                                 WHERE ur_creator.user_id = u.id) AS creator_roles_list";

        $sql_from_join = " FROM events e JOIN users u ON e.created_by_user_id = u.id";
        $sql_params = [];
        $visibility_conditions_array = []; // Koşulları biriktirmek için dizi

        // $current_user_is_admin değişkeni artık burada doğru şekilde tanımlı olmalı.
        if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'event.view_all', $current_user_id))) {
            $visibility_conditions_array[] = "1=1"; // Admin veya özel yetkili her şeyi görür
        } else {
            $specific_visibility_or_conditions = [];
            if (has_permission($pdo, 'event.view_public', $current_user_id) || !$current_user_id) {
                $specific_visibility_or_conditions[] = "e.visibility = 'public'";
            }
            if ($current_user_id && $current_user_is_approved && has_permission($pdo, 'event.view_members_only', $current_user_id)) {
                $specific_visibility_or_conditions[] = "e.visibility = 'members_only'";
            }
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

        $sql_active = $sql_select_fields . $sql_from_join .
                      " WHERE e.status = 'active' AND ($final_visibility_condition)" .
                      " ORDER BY e.event_datetime ASC";
        $stmt_active = $pdo->prepare($sql_active);
        $stmt_active->execute($sql_params);
        $active_events = $stmt_active->fetchAll(PDO::FETCH_ASSOC);

        $sql_past = $sql_select_fields . $sql_from_join .
                    " WHERE e.status = 'past' AND ($final_visibility_condition)" .
                    " ORDER BY e.event_datetime DESC";
        $stmt_past = $pdo->prepare($sql_past);
        $stmt_past->execute($sql_params);
        $past_events = $stmt_past->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Etkinlikleri listeleme hatası (events.php): " . $e->getMessage());
        $_SESSION['error_message'] = "Etkinlikler yüklenirken bir veritabanı sorunu oluştu.";
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
   /* events.php için özel stiller (style.css'deki .events-page-container vb. üzerine yazabilir veya ekleyebilir) */
    .events-page-container {
        width: 100%;
        max-width: 1600px;
        margin: 30px auto;
        padding: 25px 35px; /* Yan boşluklar artırıldı */
    }

    .events-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 35px; /* Boşluk artırıldı */
        padding-bottom: 20px;
        border-bottom: 1px solid var(--darker-gold-1);
    }

    .events-header h2 {
        color: var(--gold);
        font-size: 2.2rem; /* Başlık boyutu */
        font-family: var(--font);
        margin: 0;
    }

    .btn-outline-turquase {
        color: var(--turquase);
        border-color: var(--turquase); 
        background-color: transparent;
        padding: 8px 18px;
        font-size: 0.9rem;
        font-weight: 500;
        border-radius: 20px;
        border: 1px solid var(--turquase); 
        display: inline-flex;
        align-items: center;
        gap: 8px; 
        text-decoration: none; 
        transition: all 0.2s ease; 
    }
    .btn-outline-turquase:hover {
        color: var(--white);
        background-color: var(--turquase);
        border-color: var(--turquase); 
        transform: translateY(-2px); 
    }
    .btn-outline-turquase.btn-sm {
        padding: 6px 14px;
        font-size: 0.85rem;
    }
    .btn-outline-turquase i.fas {
        font-size: 0.9em;
    }
    
    .event-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 28px;
        margin-bottom: 40px;
    }

    .event-card {
        background-color: var(--charcoal);
        border: 1px solid var(--darker-gold-1);
        border-radius: 10px;
        overflow: hidden;
        transition: transform 0.25s ease-out, box-shadow 0.25s ease-out, border-color 0.25s ease-out;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .event-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 28px rgba(var(--gold-rgb, 189,145,42), 0.18);
        border-color: var(--darker-gold);
    }

    .event-card .event-card-image-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 10px 10px 0 0;
    }
    .event-card .event-card-image-wrapper a { 
        display:block;
    }
    .event-card .event-card-image {
        width: 100%;
        height: 220px;
        object-fit: cover;
        display: block;
        transition: transform 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
    }
    .event-card:hover .event-card-image { 
        transform: scale(1.05);
    }

    .event-card .event-card-body {
        padding: 18px 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .event-card .event-card-title a { 
        font-size: 1.35rem;
        color: var(--light-gold);
        margin-top: 0;
        margin-bottom: 10px;
        font-family: var(--font);
        line-height: 1.3;
        font-weight: 600;
        text-decoration: none;
        display: block; 
    }
    .event-card .event-card-title a:hover {
        color: var(--gold);
        text-decoration: underline;
    }

    .event-card .event-card-date {
        font-size: 0.88rem;
        color: var(--light-grey);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .event-card .event-card-date strong {
        color: var(--lighter-grey);
        font-weight: 500;
    }
    .event-card .event-card-date .date-icon {
        color: var(--gold);
        font-size: 0.95em;
    }

    .event-card .event-card-description-short {
        font-size: 0.9rem;
        color: var(--lighter-grey);
        line-height: 1.55;
        margin-bottom: 15px;
        flex-grow: 1;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: calc(1.55em * 3);
    }

    .event-card .event-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 12px;
        border-top: 1px solid var(--darker-gold-2);
        font-size: 0.85rem;
    }
   
    .event-footer-actions { 
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .participant-count-info {
        font-size: 0.85rem;
        color: var(--light-grey);
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .participant-count-info i.fas {
        color: var(--gold);
    }
    .participant-count-info strong {
        color: var(--lighter-grey);
    }

    .btn-view-event-detail { 
        color: var(--turquase);
        font-weight: 600;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 15px;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        display: inline-flex; 
        align-items: center; 
        gap: 5px; 
    }
    .btn-view-event-detail:hover {
        color: var(--light-turquase);
        background-color: var(--transparent-turquase-2);
        border-color: var(--turquase);
    }

    .accordion-section {
        margin-top: 40px;
        background-color: var(--charcoal);
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--darker-gold-1);
    }
    .accordion-button {
        background-color: var(--grey);
        color: var(--gold);
        cursor: pointer;
        padding: 18px 22px;
        width: 100%;
        border: none;
        text-align: left;
        outline: none;
        font-size: 1.4rem;
        transition: background-color 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: var(--font);
        font-weight: 600;
    }
    .accordion-button:hover,
    .accordion-button.active {
        background-color: var(--darker-gold-1);
        color: var(--light-gold);
    }
    .accordion-icon {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
        margin-left: 10px;
    }
    .accordion-button.active .accordion-icon {
        transform: rotate(90deg);
    }
    .accordion-content {
        padding: 0px 20px 20px 20px;
        background-color: transparent;
        display: none;
        border-top: 1px solid var(--darker-gold-2);
    }
    .accordion-content .event-grid {
        margin-top: 25px;
    }
    .accordion-content .no-events-message {
        font-size: 1rem;
        color: var(--light-grey);
        padding: 20px;
        background-color: transparent;
        border: none;
    }
    .past-event-card .event-card-image {
        filter: grayscale(60%);
        opacity: 0.85;
    }
    .past-event-card .event-card-title a { 
        color: var(--light-grey);
    }
    .past-event-card:hover .event-card-title a {
        color: var(--light-gold);
    }

    .no-events-message, .access-denied-message { /* Ortak stil */
        text-align: center;
        font-size: 1.1rem;
        color: var(--light-grey);
        padding: 30px 20px;
        background-color: var(--charcoal);
        border-radius: 6px;
        border: 1px dashed var(--darker-gold-1);
        margin-top: 20px;
    }
    .access-denied-message a { /* Özel link stili */
        color: var(--turquase);
        font-weight: bold;
    }
    .access-denied-message a:hover {
        color: var(--light-turquase);
    }


    @media (max-width: 768px) {
        .events-page-container { padding: 20px; }
        .events-header { flex-direction: column; align-items: stretch; gap:15px; }
        .btn-outline-turquase { width:100%; text-align:center; justify-content:center;} 
        .events-header h2 { font-size: 2rem; text-align:center; }
        .event-grid { grid-template-columns: 1fr; gap:25px; }
        .accordion-button { font-size:1.2rem; padding:15px;}
        .event-card .event-card-footer { flex-direction: column; align-items: flex-start; gap: 10px;}
        .event-footer-actions { width: 100%; justify-content: space-between;}
    }
</style>

<main class="main-content">
    <div class="container events-page-container">
        <div class="events-header">
            <h2><?php echo htmlspecialchars($page_title); ?></h2>
            <?php if ($can_create_event): ?>
                <a href="create_event.php" class="btn-outline-turquase"><i class="fas fa-plus-circle"></i> Yeni Etkinlik Oluştur</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="message success-message"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
        <?php endif; ?>

        <?php if (!$current_user_is_logged_in && $can_view_events_page && (!empty($active_events) || !empty($past_events))): ?>
            <div class="info-message" style="text-align: center; margin-bottom: 20px; background-color: var(--transparent-turquase-2); color: var(--turquase); border: 1px solid var(--turquase); padding: 10px; border-radius: 5px;">
                <i class="fas fa-info-circle"></i> Şu anda sadece herkese açık etkinlikleri görüntülüyorsunuz. Daha fazla etkinliğe erişmek için <a href="<?php echo get_auth_base_url(); ?>/login.php" style="color: var(--light-turquase); font-weight:bold;">giriş yapın</a> ya da <a href="<?php echo get_auth_base_url(); ?>/register.php" style="color: var(--light-turquase); font-weight:bold;">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (!$can_view_events_page): ?>
            <div class="access-denied-message">
                <p><i class="fas fa-lock" style="color: var(--gold); margin-right: 10px; font-size: 1.5em;"></i></p>
                <p><?php echo $access_message; ?></p>
                <p style="margin-top: 20px;">
                    <a href="<?php echo get_auth_base_url(); ?>/index.php" class="btn btn-secondary btn-sm">Ana Sayfaya Dön</a>
                </p>
            </div>
        <?php else: ?>
            <h3 style="color: var(--light-gold); font-size: 1.8rem; margin-top: 30px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--darker-gold-2);">Aktif ve Yaklaşan Etkinlikler</h3>
            <?php if (empty($active_events)): ?>
                <p class="no-events-message">Şu anda aktif veya yaklaşan bir etkinlik bulunmamaktadır.</p>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($active_events as $event): ?>
                        <div class="event-card">
                            <div class="event-card-image-wrapper">
                                 <a href="event_detail.php?id=<?php echo $event['id']; ?>">
                                    <img src="<?php echo !empty($event['image_path_1']) ? '/public/' . htmlspecialchars($event['image_path_1']) : 'https://via.placeholder.com/400x220/1a1101/bd912a?text=' . urlencode(substr($event['title'],0,20)); ?>"
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
                                        'user_roles_list' => $event['creator_roles_list']
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
                                        <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn-view-event-detail">Detaylar <i class="fas fa-arrow-right"></i></a>
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
                        <p class="no-events-message">Henüz geçmiş bir etkinlik bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="event-grid">
                            <?php foreach ($past_events as $event): ?>
                                <div class="event-card past-event-card">
                                     <div class="event-card-image-wrapper">
                                         <a href="event_detail.php?id=<?php echo $event['id']; ?>">
                                            <img src="<?php echo !empty($event['image_path_1']) ? '/public/' . htmlspecialchars($event['image_path_1']) : 'https://via.placeholder.com/400x220/333333/808080?text=Gecmis+Etkinlik'; ?>"
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
                                                'user_roles_list' => $event['creator_roles_list']
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
                                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn-view-event-detail">Detaylar <i class="fas fa-arrow-right"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
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

    const rootStyles = getComputedStyle(document.documentElement);
    const setRgbVar = (varName) => {
        const hexColor = rootStyles.getPropertyValue(`--${varName}`).trim();
        if (hexColor && hexColor.startsWith('#')) {
            const r = parseInt(hexColor.slice(1, 3), 16);
            const g = parseInt(hexColor.slice(3, 5), 16);
            const b = parseInt(hexColor.slice(5, 7), 16);
            document.documentElement.style.setProperty(`--${varName}-rgb`, `${r}, ${g}, ${b}`);
        }
    };
    ['gold', 'turquase', 'darker-gold-2', 'light-gold', 'grey', 'light-grey', 'lighter-grey', 'white', 'black', 'transparent-turquase-2', 'darker-gold-1', 'charcoal'].forEach(setRgbVar);
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
