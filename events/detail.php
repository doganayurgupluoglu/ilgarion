<?php
// /events/detail.php - Etkinlik detay sayfası - Markdown destekli

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/htmlpurifier-4.15.0/library/HTMLPurifier.auto.php';

// Events layout system include
require_once 'includes/events_layout.php';

// Session kontrolü - Ziyaretçiler için isteğe bağlı
$current_user_id = null;
$is_logged_in = false;

if (isset($_SESSION['user_id'])) {
    try {
        check_user_session_validity();
        $current_user_id = $_SESSION['user_id'];
        $is_logged_in = true;
        $is_approved = is_user_approved();
    } catch (Exception $e) {
        $is_approved = false;
    }
} else {
    $is_approved = false;
}

// Etkinlik ID kontrolü
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Debug: ID parametresini kontrol et
if (!isset($_GET['id'])) {
    error_log("Detail.php ERROR: ID parameter missing. URL: " . $_SERVER['REQUEST_URI']);
    $_SESSION['error_message'] = "Etkinlik ID parametresi eksik.";
    header('Location: index.php');
    exit;
}

if (!$event_id || $event_id <= 0) {
    error_log("Detail.php ERROR: Invalid event ID: " . ($_GET['id'] ?? 'NULL'));
    $_SESSION['error_message'] = "Geçersiz etkinlik ID: " . htmlspecialchars($_GET['id'] ?? '');
    header('Location: index.php');
    exit;
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Debug: Database bağlantısını test et
    if (!$pdo) {
        error_log("Detail.php ERROR: PDO connection is null");
        $_SESSION['error_message'] = "Veritabanı bağlantı hatası.";
        header('Location: index.php');
        exit;
    }
    
    // Etkinlik detaylarını getir
    $event_stmt = $pdo->prepare("
        SELECT e.*, 
               u.username as creator_username,
               (SELECT r.color 
                FROM roles r 
                JOIN user_roles ur ON r.id = ur.role_id 
                WHERE ur.user_id = e.created_by_user_id 
                ORDER BY r.priority ASC 
                LIMIT 1) as creator_role_color
        FROM events e
        LEFT JOIN users u ON e.created_by_user_id = u.id
        WHERE e.id = :event_id
    ");
    
    $event_stmt->execute([':event_id' => $event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Query sonucunu kontrol et
    if (!$event) {
        error_log("Detail.php ERROR: Event not found with ID: " . $event_id);
        $_SESSION['error_message'] = "Etkinlik bulunamadı (ID: $event_id).";
        header('Location: index.php');
        exit;
    }
    
    // Görüntüleme yetki kontrolü
    $can_view = true;
    if ($event['visibility'] === 'members_only' && !$is_logged_in) {
        $can_view = false;
    } elseif ($event['visibility'] === 'private' && (!$is_logged_in || !has_permission($pdo, 'event.view_private'))) {
        $can_view = false;
    }
    
    if (!$can_view) {
        error_log("Detail.php ACCESS DENIED: User cannot view event " . $event_id);
        $_SESSION['error_message'] = "Bu etkinliği görüntüleme yetkiniz yok.";
        header('Location: index.php');
        exit;
    }
    
    // Yetki kontrolleri
    $can_edit = $is_logged_in && (
        has_permission($pdo, 'event.edit_all') || 
        (has_permission($pdo, 'event.edit_own') && $event['created_by_user_id'] == $current_user_id)
    );
    
    $can_delete = $is_logged_in && (
        has_permission($pdo, 'event.delete_all') || 
        (has_permission($pdo, 'event.delete_own') && $event['created_by_user_id'] == $current_user_id)
    );
    
    $can_participate = $is_logged_in && $is_approved && $event['status'] === 'published' && has_permission($pdo, 'event_role.join');
    
    // Rol slotlarını getir
    $roles_stmt = $pdo->prepare("
        SELECT ers.id as slot_id, ers.slot_count, ers.role_id,
               er.role_name, er.role_description, er.role_icon, er.suggested_loadout_id,
               ls.set_name as loadout_set_name,
               COALESCE(SUM(CASE WHEN ep.participation_status IN ('confirmed', 'maybe') THEN 1 ELSE 0 END), 0) as current_participants,
               COALESCE(SUM(CASE WHEN ep.participation_status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed_participants,
               COALESCE(SUM(CASE WHEN ep.participation_status = 'maybe' THEN 1 ELSE 0 END), 0) as pending_participants
        FROM event_role_slots ers
        JOIN event_roles er ON ers.role_id = er.id
        LEFT JOIN event_participations ep ON ers.id = ep.role_slot_id
        LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id
        WHERE ers.event_id = :event_id
        GROUP BY ers.id, ers.slot_count, ers.role_id, er.role_name, er.role_description, er.role_icon, er.suggested_loadout_id, ls.set_name
        ORDER BY er.role_name ASC
    ");
    
    $roles_stmt->execute([':event_id' => $event_id]);
    $event_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ROLLERİN GEREKSİNİMLERİNİ TOPLU OLARAK ÇEK
    $role_requirements = [];
    if (!empty($event_roles)) {
        $role_ids = array_column($event_roles, 'role_id');
        if (!empty($role_ids)) {
            $placeholders = implode(',', array_fill(0, count($role_ids), '?'));
    
            $req_stmt = $pdo->prepare("
                SELECT err.role_id, err.skill_tag_id, st.tag_name
                FROM event_role_requirements err
                JOIN skill_tags st ON err.skill_tag_id = st.id
                WHERE err.role_id IN ($placeholders)
            ");
            $req_stmt->execute($role_ids);
            $all_requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Gereksinimleri role_id'ye göre grupla
            foreach ($all_requirements as $req) {
                $role_requirements[$req['role_id']][] = $req;
            }
        }
    }

    // Kullanıcının katılım durumunu ve yeteneklerini kontrol et
    $user_participation = null;
    $user_skill_tags = [];
    if ($is_logged_in) {
        // Katılım durumu
        $participation_stmt = $pdo->prepare("
            SELECT ep.*, ers.role_id, er.role_name
            FROM event_participations ep
            LEFT JOIN event_role_slots ers ON ep.role_slot_id = ers.id
            LEFT JOIN event_roles er ON ers.role_id = er.id
            WHERE ep.event_id = :event_id AND ep.user_id = :user_id
        ");
        
        $participation_stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $current_user_id
        ]);
        
        $user_participation = $participation_stmt->fetch(PDO::FETCH_ASSOC);

        // Yetenek etiketleri
        $user_tags_stmt = $pdo->prepare("
            SELECT ust.skill_tag_id
            FROM user_skill_tags ust
            WHERE ust.user_id = :user_id
        ");
        $user_tags_stmt->execute([':user_id' => $current_user_id]);
        $user_skill_tags_raw = $user_tags_stmt->fetchAll(PDO::FETCH_COLUMN);
        $user_skill_tags = array_map('intval', $user_skill_tags_raw);
    }
    
    // Katılımcıları getir (Artık herkes görebilir)
    $participants_stmt = $pdo->prepare("
        SELECT ep.*, u.username, 
               COALESCE(er.role_name, 'Rol Atanmamış') as role_name,
               ers.role_id,
               (SELECT r.color 
                FROM roles r 
                JOIN user_roles ur ON r.id = ur.role_id 
                WHERE ur.user_id = u.id 
                ORDER BY r.priority ASC 
                LIMIT 1) as user_role_color
        FROM event_participations ep
        JOIN users u ON ep.user_id = u.id
        LEFT JOIN event_role_slots ers ON ep.role_slot_id = ers.id
        LEFT JOIN event_roles er ON ers.role_id = er.id
        WHERE ep.event_id = :event_id
        ORDER BY 
            CASE ep.participation_status 
                WHEN 'confirmed' THEN 1 
                WHEN 'maybe' THEN 2 
                WHEN 'declined' THEN 3 
                ELSE 4 
            END,
            COALESCE(er.role_name, 'ZZZ') ASC, 
            u.username ASC
    ");
    
    $participants_stmt->execute([':event_id' => $event_id]);
    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // HTML Purifier'ı başlat
    $config = HTMLPurifier_Config::createDefault();
    $cache_path = BASE_PATH . '/htmlpurifier-4.15.0/cache';
    if (!is_dir($cache_path)) {
        mkdir($cache_path, 0755, true);
    }
    $config->set('Cache.SerializerPath', $cache_path);
    $config->set('HTML.SafeIframe', true);
    $config->set('URI.SafeIframeRegexp', '%^(https?://)?(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/)%');
    $config->set('HTML.Allowed', 'h1,h2,h3,h4,h5,h6,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[style|width|height|alt|src],iframe[src|width|height|frameborder|allow|allowfullscreen|title]');
    $config->set('CSS.AllowedProperties', 'text-align,color,max-width,height,display,border-radius');
    $purifier = new HTMLPurifier($config);
    $clean_event_description = $purifier->purify($event['event_description']);
    
} catch (PDOException $e) {
    error_log("Detail.php PDO ERROR: " . $e->getMessage());
    error_log("Detail.php PDO ERROR Query: Event ID = " . $event_id);
    $_SESSION['error_message'] = "Etkinlik detayları yüklenirken veritabanı hatası oluştu: " . $e->getMessage();
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    error_log("Detail.php GENERAL ERROR: " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlik detayları yüklenirken bir hata oluştu: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Sayfa başlığı ve breadcrumb
$page_title = htmlspecialchars($event['event_title']) . " - Etkinlik Detayı";
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => $event['event_title'], 'url' => '', 'icon' => 'fas fa-calendar-alt']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);
?>

<!-- CSS Dahil Etme -->
<link rel="stylesheet" href="css/events_detail.css">
<link rel="stylesheet" href="css/events_sidebar.css">

<style>
.requirement-missing {
    background-color: var(--orange-dark);
    border: 1px solid var(--orange);
    color: var(--orange);
    cursor: help;
}
.suggested-loadout-container {
    text-align: center;
    margin-top: 0.5rem;
    margin-bottom: 1rem;
}
.suggested-loadout-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color:rgb(70, 166, 61);
    font-weight: 500;
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
}
</style>

<!-- Event Detail Container -->
<div class="event-detail-container">
    
    <!-- Event Header -->
    <div class="event-header">
        <?php if (!empty($event['event_thumbnail_path'])): ?>
            <div class="event-banner">
                <img src="<?= htmlspecialchars($event['event_thumbnail_path']) ?>" 
                     alt="<?= htmlspecialchars($event['event_title']) ?>"
                     class="banner-image">
                <div class="banner-overlay"></div>
            </div>
        <?php endif; ?>
        
        <div class="event-header-content">
            <div class="event-title-section">
                <h1 class="event-title">
                    <?= htmlspecialchars($event['event_title']) ?>
                    <span class="status-badge status-<?= $event['status'] ?>">
                        <?php
                        switch($event['status']) {
                            case 'published': echo 'Aktif'; break;
                            case 'draft': echo 'Taslak'; break;
                            case 'cancelled': echo 'İptal'; break;
                            case 'completed': echo 'Tamamlandı'; break;
                            default: echo ucfirst($event['status']);
                        }
                        ?>
                    </span>
                </h1>
                
                <div class="event-meta-header">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?= date('d.m.Y H:i', strtotime($event['event_date'])) ?></span>
                    </div>
                    
                    <?php if (!empty($event['event_location'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($event['event_location']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span>Oluşturan: </span>
                        <span class="creator-name user-link" 
                              data-user-id="<?= $event['created_by_user_id'] ?>"
                              style="color: <?= htmlspecialchars($event['creator_role_color'] ?? '#bd912a') ?>; cursor: pointer;">
                            <?= htmlspecialchars($event['creator_username'] ?? 'Bilinmeyen') ?>
                        </span>
                    </div>
                    
                    <div class="meta-item">
                        <i class="fas fa-<?= $event['visibility'] === 'public' ? 'globe' : ($event['visibility'] === 'members_only' ? 'users' : 'lock') ?>"></i>
                        <span>
                            <?php
                            switch($event['visibility']) {
                                case 'public': echo 'Herkese Açık'; break;
                                case 'members_only': echo 'Sadece Üyeler'; break;
                                case 'private': echo 'Özel'; break;
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons - YENİ VE GÜNCELLENMİŞ -->
            <div class="event-actions">
                <?php if ($can_participate): ?>
                    <?php if (!$user_participation): ?>
                        <!-- Katılım Butonları - Katılım Yok -->
                        <div class="participation-buttons">
                            <button type="button" class="btn-primary participate-btn" data-event-id="<?= $event_id ?>">
                                <i class="fas fa-hand-paper"></i>
                                Etkinliğe Katıl
                            </button>
                            <button type="button" class="btn-secondary participation-status-btn" 
                                    data-event-id="<?= $event_id ?>" data-status="maybe">
                                <i class="fas fa-question-circle"></i>
                                Belki Katılırım
                            </button>
                            <button type="button" class="btn-outline participation-status-btn" 
                                    data-event-id="<?= $event_id ?>" data-status="declined">
                                <i class="fas fa-times-circle"></i>
                                Katılmıyorum
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Mevcut Katılım Durumu -->
                        <div class="participation-status">
                            <span class="status-<?= $user_participation['participation_status'] ?>">
                                <i class="fas fa-<?= $user_participation['participation_status'] === 'confirmed' ? 'check-circle' : 
                                    ($user_participation['participation_status'] === 'maybe' ? 'question-circle' : 'times-circle') ?>"></i>
                                <?php
                                switch($user_participation['participation_status']) {
                                    case 'confirmed':
                                        echo 'Katılım Onaylandı';
                                        break;
                                    case 'maybe':
                                        echo 'Belki Katılırım';
                                        break;
                                    case 'declined':
                                        echo 'Katılmıyorum';
                                        break;
                                }
                                ?>
                            </span>
                            <?php if ($user_participation['participation_status'] === 'confirmed' && !empty($user_participation['role_name'])): ?>
                                <small>Rol: <?= htmlspecialchars($user_participation['role_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Durum Değiştirme Butonları -->
                        <div class="participation-change-buttons">
                            <?php
                            $status = $user_participation['participation_status'];
                            ?>
                            
                            <?php if ($status === 'confirmed'): ?>
                                <button type="button" class="btn-primary participate-btn" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-exchange-alt"></i>
                                    Rolü Değiştir
                                </button>
                                <button type="button" class="btn-danger leave-event-btn" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Katılımdan Ayrıl
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-primary participate-btn" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-hand-paper"></i>
                                    Role Katıl
                                </button>
                                <button type="button" class="btn-secondary participation-status-btn <?= $status === 'maybe' ? 'active-status' : '' ?>" 
                                        data-event-id="<?= $event_id ?>" data-status="maybe">
                                    <i class="fas fa-question-circle"></i>
                                    Belki Katılırım
                                </button>
                                <button type="button" class="btn-outline participation-status-btn <?= $status === 'declined' ? 'active-status' : '' ?>" 
                                        data-event-id="<?= $event_id ?>" data-status="declined">
                                    <i class="fas fa-times-circle"></i>
                                    Katılmıyorum
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Katılım Yetki Mesajı -->
                    <div class="participation-disabled">
                        <?php if (!$is_logged_in): ?>
                            <p><i class="fas fa-info-circle"></i> Etkinliğe katılmak için giriş yapmalısınız.</p>
                            <a href="/login.php" class="btn-primary">
                                <i class="fas fa-sign-in-alt"></i>
                                Giriş Yap
                            </a>
                        <?php elseif (!$is_approved): ?>
                            <p><i class="fas fa-clock"></i> Etkinliğe katılmak için hesabınızın onaylanması beklenmektedir.</p>
                        <?php elseif ($event['status'] !== 'published'): ?>
                            <p><i class="fas fa-eye-slash"></i> Bu etkinlik henüz yayınlanmamış.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Diğer Aksiyonlar -->
                <div class="other-actions">
                    <?php if ($can_edit): ?>
                        <a href="create.php?id=<?= $event_id ?>" class="btn-secondary">
                            <i class="fas fa-edit"></i>
                            Düzenle
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($can_delete): ?>
                        <button type="button" class="btn-danger delete-event-btn" data-event-id="<?= $event_id ?>">
                            <i class="fas fa-trash"></i>
                            Sil
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn-secondary share-btn" data-event-id="<?= $event_id ?>">
                        <i class="fas fa-share"></i>
                        Paylaş
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Content Grid -->
    <div class="event-content-grid">
        
        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Event Description -->
            <div class="content-section">
                <h2><i class="fas fa-align-left"></i> Etkinlik Açıklaması</h2>
                <div class="event-description">
                    <?= $clean_event_description ?>
                </div>
            </div>
            
            <!-- Event Roles -->
            <?php if (!empty($event_roles)): ?>
                <div class="content-section">
                    <h2>
                        <i class="fas fa-users"></i>
                        Roller ve Katılımcılar
                        <span class="roles-count">(<?= count($event_roles) ?> rol)</span>
                    </h2>
                    
                    <div class="roles-grid">
                        <?php foreach ($event_roles as $role): ?>
                            <div class="role-card">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <?php if (!empty($role['role_icon'])): ?>
                                            <i class="<?= htmlspecialchars($role['role_icon']) ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="role-info">
                                        <h3 class="role-name">
                                            <a href="roles/view.php?id=<?= $role['role_id'] ?>">
                                                <?= htmlspecialchars($role['role_name']) ?>
                                            </a>
                                        </h3>
                                        <div class="role-slots">
                                            <span class="confirmed"><?= $role['confirmed_participants'] ?></span>
                                            <?php if ($role['pending_participants'] > 0): ?>
                                                <span class="pending">+<?= $role['pending_participants'] ?></span>
                                            <?php endif; ?>
                                            <span class="total">/ <?= $role['slot_count'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($role['role_description'])): ?>
                                    <div class="role-description">
                                        <p><?= htmlspecialchars(mb_substr($role['role_description'], 0, 120)) ?><?= mb_strlen($role['role_description']) > 120 ? '...' : '' ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($role['suggested_loadout_id']) && !empty($role['loadout_set_name'])): ?>
                                    <div class="suggested-loadout-container">
                                        <div class="suggested-loadout-label">
                                            <i class="fas fa-star"></i>
                                            <span>Önerilen Teçhizat Seti</span>
                                        </div>
                                        <a href="/events/loadouts/view.php?id=<?= $role['suggested_loadout_id'] ?>" class="btn-suggested-loadout">
                                            <i class="fas fa-user-shield"></i>
                                            <?= htmlspecialchars($role['loadout_set_name']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="role-actions">
                                    <?php
                                    $available_slots = $role['slot_count'] - $role['current_participants'];
                                    $user_in_this_role = $user_participation && isset($user_participation['role_slot_id']) && $user_participation['role_slot_id'] == $role['slot_id'];
                                    
                                    // ROL GEREKSİNİMLERİNİ KONTROL ET
                                    $role_id = $role['role_id'];
                                    $requirements_for_this_role = $role_requirements[$role_id] ?? [];
                                    $user_meets_requirements = true;
                                    $missing_tags_str = '';
                                    
                                    if (!empty($requirements_for_this_role)) {
                                        if (!$is_logged_in) {
                                            $user_meets_requirements = false;
                                        } else {
                                            $required_tag_ids = array_column($requirements_for_this_role, 'skill_tag_id');
                                            $missing_tag_ids = array_diff($required_tag_ids, $user_skill_tags);
                                            
                                            if (!empty($missing_tag_ids)) {
                                                $user_meets_requirements = false;
                                                $missing_tag_names = [];
                                                foreach ($requirements_for_this_role as $req) {
                                                    if (in_array($req['skill_tag_id'], $missing_tag_ids)) {
                                                        $missing_tag_names[] = $req['tag_name'];
                                                    }
                                                }
                                                $missing_tags_str = 'Eksik Yetenekler: ' . implode(', ', $missing_tag_names);
                                            }
                                        }
                                    }
                                    ?>

                                    <?php if ($user_in_this_role): ?>
                                        <span class="current-role-badge">
                                            <i class="fas fa-check"></i>
                                            Mevcut Rolünüz
                                        </span>
                                    <?php elseif ($available_slots <= 0): ?>
                                        <span class="role-full-badge">
                                            <i class="fas fa-users"></i>
                                            Dolu
                                        </span>
                                    <?php elseif ($can_participate && !$user_meets_requirements): ?>
                                        <span class="role-full-badge requirement-missing" title="<?= htmlspecialchars($missing_tags_str) ?>">
                                            <i class="fas fa-times-circle"></i>
                                            Gereksinimler Eksik
                                        </span>
                                    <?php elseif ($can_participate): ?>
                                        <?php
                                        // Kullanıcı zaten başka bir role katılmışsa veya "Belki" durumundaysa, buton metnini ve ikonunu ayarla
                                        $is_switching = $user_participation && $user_participation['participation_status'] === 'confirmed';
                                        $button_text = $is_switching ? 'Bu Role Geç' : 'Bu Role Katıl';
                                        $button_icon = $is_switching ? 'fas fa-exchange-alt' : 'fas fa-plus';
                                        ?>
                                        <button type="button" class="btn-primary btn-role-join"
                                                data-event-id="<?= $event_id ?>"
                                                data-slot-id="<?= $role['slot_id'] ?>"
                                                data-role-name="<?= htmlspecialchars($role['role_name']) ?>">
                                            <i class="<?= $button_icon ?>"></i>
                                            <?= $button_text ?>
                                        </button>
                                    <?php endif; ?>

                                    <a href="roles/view.php?id=<?= $role['role_id'] ?>" class="btn-role-details">
                                        <i class="fas fa-info"></i>
                                        Detaylar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Participants (Artık herkes görebilir, aksiyonlar yetkiye bağlı) -->
            <?php if (!empty($participants)): ?>
                <div class="content-section">
                    <h2>
                        <i class="fas fa-list"></i>
                        Katılımcılar
                        <span class="participants-count">(<?= count($participants) ?> kişi)</span>
                    </h2>
                    
                    <div class="participants-list">
                        <?php 
                        $grouped_participants = [];
                        foreach ($participants as $participant) {
                            $status = $participant['participation_status'];
                            if (!isset($grouped_participants[$status])) {
                                $grouped_participants[$status] = [];
                            }
                            $grouped_participants[$status][] = $participant;
                        }
                        ?>
                        
                        <?php foreach (['confirmed', 'maybe', 'declined'] as $status): ?>
                            <?php if (isset($grouped_participants[$status]) && count($grouped_participants[$status]) > 0): ?>
                                <div class="participants-group">
                                    <h4 class="group-title status-<?= $status ?>">
                                        <i class="fas fa-<?= $status === 'confirmed' ? 'check-circle' : ($status === 'maybe' ? 'question-circle' : 'times-circle') ?>"></i>
                                        <?php
                                        switch($status) {
                                            case 'confirmed':
                                                echo 'Onaylanan Katılımcılar';
                                                break;
                                            case 'maybe':
                                                echo 'Belki Katılacaklar';
                                                break;
                                            case 'declined':
                                                echo 'Katılmayacaklar';
                                                break;
                                        }
                                        ?>
                                        (<?= count($grouped_participants[$status]) ?>)
                                    </h4>
                                    
                                    <div class="participants-grid">
                                        <?php foreach ($grouped_participants[$status] as $participant): ?>
                                            <div class="participant-card">
                                                <div class="participant-info">
                                                    <span class="participant-name user-link"
                                                          data-user-id="<?= $participant['user_id'] ?>" 
                                                          style="color: <?= htmlspecialchars($participant['user_role_color'] ?? '#bd912a') ?>; cursor: pointer;">
                                                        <?= htmlspecialchars($participant['username']) ?>
                                                    </span>
                                                    
                                                    <?php if ($status === 'confirmed' && !empty($participant['role_name'])): ?>
                                                        <span class="participant-role">
                                                            <i class="fas fa-user-tag"></i>
                                                            <?= htmlspecialchars($participant['role_name']) ?>
                                                        </span>
                                                    <?php elseif ($status === 'maybe'): ?>
                                                        <span class="participant-role">
                                                            <i class="fas fa-question-circle"></i>
                                                            Kararsız
                                                        </span>
                                                    <?php elseif ($status === 'declined'): ?>
                                                        <span class="participant-role">
                                                            <i class="fas fa-times-circle"></i>
                                                            Katılmıyor
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="participant-registered">
                                                        <i class="fas fa-clock"></i>
                                                        <?= date('d.m.Y H:i', strtotime($participant['registered_at'])) ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if ($can_edit): ?>
                                                    <div class="participant-actions">
                                                        <?php if ($participant['participation_status'] === 'maybe'): ?>
                                                            <button type="button" 
                                                                    class="btn-approve" 
                                                                    data-participation-id="<?= $participant['id'] ?>"
                                                                    title="Katılımı Onayla">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" 
                                                                class="btn-remove" 
                                                                data-participation-id="<?= $participant['id'] ?>"
                                                                data-event-id="<?= $event_id ?>"
                                                                title="Katılımcıyı Kaldır">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if (empty($participants)): ?>
                            <div class="no-participants">
                                <i class="fas fa-users"></i>
                                <p>Henüz katılımcı bulunmuyor.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Sidebar -->
        <div class="event-sidebar">
            
            <!-- Event Info Card -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Etkinlik Bilgileri</h3>
                
                <div class="info-item">
                    <div class="info-value">
                        <?= date('d F Y, H:i', strtotime($event['event_date'])) ?>
                    </div>
                </div>
                
                <?php if (!empty($event['event_location'])): ?>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Konum
                        </div>
                        <div class="info-value">
                            <?= htmlspecialchars($event['event_location']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-eye"></i>
                        Görünürlük
                    </div>
                    <div class="info-value">
                        <?php
                        switch($event['visibility']) {
                            case 'public': echo 'Herkese Açık'; break;
                            case 'members_only': echo 'Sadece Üyeler'; break;
                            case 'private': echo 'Özel'; break;
                        }
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        Organize Eden
                    </div>
                    <div class="info-value">
                        <span class="user-link" 
                              data-user-id="<?= $event['created_by_user_id'] ?>"
                              style="color: <?= htmlspecialchars($event['creator_role_color'] ?? '#bd912a') ?>; cursor: pointer;">
                            <?= htmlspecialchars($event['creator_username'] ?? 'Bilinmeyen') ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-clock"></i>
                        Oluşturulma
                    </div>
                    <div class="info-value">
                        <?= date('d.m.Y H:i', strtotime($event['created_at'])) ?>
                    </div>
                </div>
            </div>
            
            <!-- Participation Stats -->
            <?php if (!empty($participants)): ?>
                <div class="info-card">
                    <h3><i class="fas fa-chart-pie"></i> Katılım İstatistikleri</h3>
                    
                    <?php
                    $confirmed_count = count($grouped_participants['confirmed'] ?? []);
                    $maybe_count = count($grouped_participants['maybe'] ?? []);
                    $declined_count = count($grouped_participants['declined'] ?? []);
                    $total_count = $confirmed_count + $maybe_count + $declined_count;
                    ?>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number confirmed"><?= $confirmed_count ?></div>
                            <div class="stat-label">Onaylanan</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-number maybe"><?= $maybe_count ?></div>
                            <div class="stat-label">Belki</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-number declined"><?= $declined_count ?></div>
                            <div class="stat-label">Katılmıyor</div>
                        </div>
                        
                        <div class="stat-item total">
                            <div class="stat-number"><?= $total_count ?></div>
                            <div class="stat-label">Toplam</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Role Summary -->
            <?php if (!empty($event_roles)): ?>
                <div class="info-card">
                    <h3><i class="fas fa-users"></i> Rol Özeti</h3>
                    
                    <div class="roles-summary">
                        <?php foreach ($event_roles as $role): ?>
                            <div class="role-summary-item">
                                <div class="role-summary-header">
                                    <span class="role-summary-name">
                                        <?= htmlspecialchars($role['role_name']) ?>
                                    </span>
                                    <span class="role-summary-count">
                                        <?= $role['confirmed_participants'] ?>/<?= $role['slot_count'] ?>
                                    </span>
                                </div>
                                <div class="role-summary-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" 
                                             style="width: <?= $role['slot_count'] > 0 ? ($role['confirmed_participants'] / $role['slot_count']) * 100 : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="info-card">
                <h3><i class="fas fa-bolt"></i> Hızlı İşlemler</h3>
                
                <div class="quick-actions">
                    <button type="button" class="quick-action-btn share-btn" data-event-id="<?= $event_id ?>">
                        <i class="fas fa-share"></i>
                        Paylaş
                    </button>
                    
                    <button type="button" class="quick-action-btn" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        Yazdır
                    </button>
                    
                    <?php if ($can_edit): ?>
                        <a href="create.php?id=<?= $event_id ?>" class="quick-action-btn">
                            <i class="fas fa-edit"></i>
                            Düzenle
                        </a>
                    <?php endif; ?>
                    
                    <a href="index.php" class="quick-action-btn">
                        <i class="fas fa-arrow-left"></i>
                        Geri Dön
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- JavaScript ve EventData -->
<script>
    // Event data'yı JavaScript'e geç
    window.eventData = {
        id: <?= $event_id ?>,
        csrfToken: '<?= $_SESSION['csrf_token'] ?>',
        canParticipate: <?= $can_participate ? 'true' : 'false' ?>,
        eventDate: '<?= $event['event_date'] ?>',
        userParticipation: <?= $user_participation ? json_encode($user_participation) : 'null' ?>
    };
</script>
<script src="js/events_detail.js"></script>

<?php
include_once BASE_PATH . '/src/includes/user_popover.php';
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>