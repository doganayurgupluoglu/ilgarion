<?php
// public/event_detail.php - Entegre Edilmiş Yeni Yetki Sistemi

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php'; // Gelişmiş yetki kontrolleri
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover
require_once BASE_PATH . '/src/functions/notification_functions.php'; // Bildirim fonksiyonları

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$event_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $event_id = (int)$_GET['id'];
} else {
    $_SESSION['error_message'] = "Geçersiz etkinlik ID'si.";
    header('Location: ' . get_auth_base_url() . '/events.php');
    exit;
}

// Bildirim okundu işaretleme
if (isset($_GET['notif_id']) && is_numeric($_GET['notif_id']) && is_user_logged_in()) {
    if (isset($pdo)) {
        mark_notification_as_read($pdo, (int)$_GET['notif_id'], $_SESSION['user_id']);
    }
}

$event = null;
$page_title = "Etkinlik Detayı";
$attending_participants = [];
$maybe_participants = [];
$declined_participants = [];
$user_current_participation_status = null;
$event_photos_for_js_modal = [];
$suggested_loadout = null;

// Kullanıcı durum değişkenleri
$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

// Sayfa herkese açık - sadece içerik yetki kontrolü var
$can_view_event_detail = true;
$access_message_detail = "";

try {
    $sql_event = "SELECT
                    e.*,
                    u.id AS creator_id,
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
                     LIMIT 1) AS creator_primary_role_color
                FROM events e JOIN users u ON e.created_by_user_id = u.id
                WHERE e.id = :event_id";
    $stmt_event = $pdo->prepare($sql_event);
    $stmt_event->bindParam(':event_id', $event_id, PDO::PARAM_INT);
    $stmt_event->execute();
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        $page_title = htmlspecialchars($event['title']);

        // YETKİLENDİRME MANTIĞI - Gelişmiş yetki sistemi ile
        // Etkinlik verilerini can_user_access_content fonksiyonu için hazırla
        $content_data = [
            'visibility' => $event['visibility'],
            'user_id' => $event['created_by_user_id']
        ];
        
        // Etkinliğin görünürlük rollerini al
        $content_role_ids = get_content_visibility_roles($pdo, 'event', $event_id);
        
        // Kullanıcının bu etkinliğe erişimi var mı kontrol et
        $can_view_event_detail = can_user_access_content($pdo, $content_data, $content_role_ids, $current_user_id);

        if (!$can_view_event_detail) {
            if (!$current_user_is_logged_in) {
                 $access_message_detail = "Bu etkinliği görmek için lütfen <a href='" . get_auth_base_url() . "/login.php'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php'>kayıt olun</a>.";
            } elseif (!$current_user_is_approved) {
                $access_message_detail = "Bu etkinliği görüntüleyebilmek için hesabınızın onaylanmış olması gerekmektedir.";
            } else {
                $access_message_detail = "Bu etkinliği görüntüleme yetkiniz bulunmamaktadır.";
            }
        }
        // YETKİLENDİRME MANTIĞI SONU

        if ($can_view_event_detail) {
            // Creator için birincil rol belirleme
            if (!empty($event['creator_roles_list'])) {
                $creator_roles = explode(',', $event['creator_roles_list']);
                $event['creator_primary_role'] = trim($creator_roles[0]); // İlk rol (en yüksek öncelikli)
            } else {
                $event['creator_primary_role'] = 'member'; // Varsayılan rol
            }

            if (!empty($event['suggested_loadout_id'])) {
                $stmt_loadout = $pdo->prepare("SELECT id, set_name FROM loadout_sets WHERE id = :loadout_id");
                $stmt_loadout->bindParam(':loadout_id', $event['suggested_loadout_id'], PDO::PARAM_INT);
                $stmt_loadout->execute();
                $suggested_loadout = $stmt_loadout->fetch(PDO::FETCH_ASSOC);
            }

            $sql_all_participants = "SELECT
                                        ep.user_id, ep.participation_status, u.username,
                                        u.ingame_name, u.avatar_path,
                                        u.discord_username AS participant_discord_username,
                                        (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS participant_event_count,
                                        (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS participant_gallery_count,
                                        (SELECT GROUP_CONCAT(r.name ORDER BY r.priority ASC SEPARATOR ',') 
                                         FROM user_roles ur_p 
                                         JOIN roles r ON ur_p.role_id = r.id 
                                         WHERE ur_p.user_id = u.id) AS participant_roles_list,
                                        (SELECT r.color 
                                         FROM user_roles ur_primary 
                                         JOIN roles r ON ur_primary.role_id = r.id 
                                         WHERE ur_primary.user_id = u.id 
                                         ORDER BY r.priority ASC 
                                         LIMIT 1) AS participant_primary_role_color
                                     FROM event_participants ep
                                     JOIN users u ON ep.user_id = u.id
                                     WHERE ep.event_id = :event_id
                                     ORDER BY CASE ep.participation_status WHEN 'attending' THEN 1 WHEN 'maybe' THEN 2 WHEN 'declined' THEN 3 ELSE 4 END, u.username ASC";
            $stmt_all_participants = $pdo->prepare($sql_all_participants);
            $stmt_all_participants->bindParam(':event_id', $event_id, PDO::PARAM_INT);
            $stmt_all_participants->execute();
            $all_participants_with_details_and_roles = $stmt_all_participants->fetchAll(PDO::FETCH_ASSOC);

            foreach($all_participants_with_details_and_roles as &$participant) {
                // Participant için birincil rol belirleme
                if (!empty($participant['participant_roles_list'])) {
                    $participant_roles = explode(',', $participant['participant_roles_list']);
                    $participant['participant_primary_role'] = trim($participant_roles[0]); // İlk rol (en yüksek öncelikli)
                } else {
                    $participant['participant_primary_role'] = 'member'; // Varsayılan rol
                }

                if ($participant['participation_status'] === 'attending') $attending_participants[] = $participant;
                elseif ($participant['participation_status'] === 'maybe') $maybe_participants[] = $participant;
                elseif ($participant['participation_status'] === 'declined') $declined_participants[] = $participant;
                if ($current_user_id && $participant['user_id'] == $current_user_id) {
                    $user_current_participation_status = $participant['participation_status'];
                }
            }

            $temp_event_images = [];
            if (!empty($event['image_path_1'])) $temp_event_images[] = $event['image_path_1'];
            if (!empty($event['image_path_2'])) $temp_event_images[] = $event['image_path_2'];
            if (!empty($event['image_path_3'])) $temp_event_images[] = $event['image_path_3'];

            foreach($temp_event_images as $img_idx => $img_path){
                $event_photos_for_js_modal[] = [
                    'photo_id' => 'event_img_' . $event_id . '_' . ($img_idx + 1),
                    'image_path_full' => '/public/' . htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'),
                    'photo_description' => '',
                    'uploader_user_id' => (int)$event['creator_id'],
                    'uploader_username' => '', 'uploader_avatar_path_full' => '', 'uploader_ingame_name' => '',
                    'uploader_discord_username' => '', 'uploader_event_count' => 0, 'uploader_gallery_count' => 0,
                    'uploader_roles_list' => '', 'uploaded_at_formatted' => '', 'like_count' => 0,
                    'user_has_liked' => false, 'isEventImage' => true
                ];
            }
        }

    } else {
        $_SESSION['error_message'] = "Etkinlik bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/events.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Etkinlik detayı (event_detail.php) çekme hatası (ID: $event_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlik detayı yüklenirken bir sorun oluştu.";
    header('Location: ' . get_auth_base_url() . '/events.php');
    exit;
}

// Buton görünürlükleri için yetki kontrolleri - Gelişmiş yetki sistemi ile
$can_edit_this_event = $current_user_id && $event && can_user_edit_content($pdo, 'event', $event['created_by_user_id'], $current_user_id);
$can_delete_this_event_admin = $current_user_is_admin && $event && can_user_delete_content($pdo, 'event', $event['created_by_user_id'], $current_user_id);
$can_cancel_this_event = $current_user_id && $event && (
    has_permission($pdo, 'event.manage_participants', $current_user_id) || 
    $current_user_is_admin || 
    ($event['created_by_user_id'] == $current_user_id && has_permission($pdo, 'event.edit_own', $current_user_id))
);
$can_participate = $current_user_is_approved && $event && $event['status'] === 'active' && 
                  (new DateTime($event['event_datetime']) >= new DateTime()) && 
                  has_permission($pdo, 'event.participate', $current_user_id);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Event Detail Page Styles - Consistent with Gallery & Events */
.event-detail-page-container { 
    width: 100%;
    max-width: 1200px; 
    margin: 0 auto; 
    padding: 2rem 1rem; 
    font-family: var(--font);
    color: var(--lighter-grey);
}

.page-top-nav-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem; 
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2); 
}

.back-to-events-link {
    font-size: 0.9rem; 
    color: var(--turquase); 
    text-decoration: none;
    display: inline-flex; 
    align-items: center; 
    gap: 0.5rem;
    padding: 0.75rem 1rem; 
    border-radius: 6px; 
    border: 1px solid var(--turquase); 
    background-color: transparent; 
    transition: all 0.2s ease;
}

.back-to-events-link:hover { 
    color: var(--charcoal); 
    background-color: var(--turquase); 
}

.back-to-events-link i.fas { 
    font-size: 0.85em; 
}

.btn-edit-event {
    background-color: var(--gold); 
    color: var(--charcoal); 
    padding: 0.75rem 1rem;
    border-radius: 6px; 
    text-decoration: none; 
    font-weight: 500; 
    font-size: 0.9rem; 
    transition: all 0.2s ease; 
    display: inline-flex; 
    align-items: center; 
    gap: 0.5rem;
    border: 1px solid var(--gold); 
}

.btn-edit-event:hover { 
    background-color: var(--light-gold); 
    color: var(--charcoal);
}

.btn-edit-event i.fas { 
    font-size: 0.85em;
}

.event-main-header {
    padding: 2rem 1.5rem; 
    border-radius: 8px; 
    margin-bottom: 2rem; 
    border: 1px solid var(--darker-gold-2); 
    background-color: transparent; 
    text-align: center; 
}

.event-main-title {
    color: var(--gold); 
    font-size: 2.5rem; 
    font-family: var(--font); 
    margin: 0 0 1.5rem 0; 
    line-height: 1.2; 
    font-weight: 300; 
    letter-spacing: -0.5px;
}

.event-status-alert {
    padding: 1rem 1.5rem; 
    margin: 1.5rem auto; 
    border-radius: 6px; 
    font-size: 0.9rem;
    border: 1px solid; 
    text-align: center; 
    font-weight: 500;
    max-width: 600px; 
}

.event-status-alert.cancelled { 
    background-color: rgba(255, 82, 82, 0.1); 
    color: var(--red); 
    border-color: var(--red); 
}

.event-status-alert.past { 
    background-color: rgba(42, 189, 168, 0.1); 
    color: var(--turquase); 
    border-color: var(--turquase); 
}

.event-status-alert.info {
    background-color: rgba(34, 34, 34, 0.3);
    color: var(--light-grey);
    border-color: var(--grey);
}

.event-meta-details {
    margin-top: 1rem; 
    padding-top: 1rem;
    border-top: 1px solid var(--darker-gold-2); 
    font-size: 0.9rem; 
    color: var(--light-grey);
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-wrap: wrap; 
    gap: 1rem; 
}

.event-meta-details p { 
    margin-bottom: 0; 
    display: flex; 
    align-items: center; 
    gap: 0.5rem;
} 

.event-meta-details p i.fas { 
    color: var(--gold); 
    font-size: 0.9em; 
}

.event-meta-details strong { 
    color: var(--lighter-grey); 
    font-weight: 500; 
}

.event-creator-info { 
    display: inline-flex; 
    align-items: center; 
    gap: 0.5rem; 
    cursor: default; 
} 

.creator-avatar { 
    width: 24px; 
    height: 24px; 
    border-radius: 50%; 
    object-fit: cover; 
    border: 1px solid var(--gold); 
}

.avatar-placeholder { 
    width: 24px; 
    height: 24px; 
    font-size: 0.8rem; 
    background-color: var(--grey); 
    color: var(--gold); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 500; 
    border: 1px solid var(--darker-gold-1); 
    border-radius: 50%;
}

.creator-name-link { 
    color: inherit !important; 
    font-weight: 500; 
    font-size: 0.9em; 
    text-decoration: none; 
}

.creator-name-link:hover { 
    color: var(--gold) !important; 
}

.event-key-details-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem; 
    margin-bottom: 2rem;
    padding: 1.5rem;
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    background-color: transparent; 
}

.key-detail-item {
    background-color: rgba(34, 34, 34, 0.3); 
    padding: 1rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-left: 3px solid var(--gold);
    transition: background-color 0.2s ease;
}

.key-detail-item:hover {
    background-color: rgba(82, 56, 10, 0.2);
}

.key-detail-item i.fas {
    color: var(--gold);
    font-size: 1.25em; 
    width: 24px; 
    text-align: center;
}

.key-detail-item div { 
    display: flex; 
    flex-direction: column; 
}

.key-detail-item p.detail-label { 
    margin: 0 0 0.25rem 0; 
    font-size: 0.8rem; 
    color: var(--light-grey); 
    text-transform: uppercase; 
    letter-spacing: 0.5px;
}

.key-detail-item strong.detail-value { 
    font-size: 0.95em; 
    color: var(--lighter-grey); 
    font-weight: 500; 
}

.key-detail-item strong.detail-value a { 
    color: var(--turquase); 
    text-decoration: none; 
}

.key-detail-item strong.detail-value a:hover { 
    text-decoration: underline; 
}

.event-content-section {
    border-radius: 8px; 
    margin-bottom: 2rem; 
    background-color: transparent; 
    border: 1px solid var(--darker-gold-2);
    padding: 1.5rem; 
}

.section-title {
    color: var(--gold); 
    font-size: 1.5rem; 
    margin: 0 0 1rem 0;
    padding-bottom: 0.75rem; 
    border-bottom: 1px solid var(--darker-gold-2); 
    font-family: var(--font); 
    font-weight: 400; 
    display: flex; 
    align-items: center; 
    gap: 0.5rem;
}

.event-description-text p { 
    line-height: 1.6; 
    font-size: 1rem; 
    color: var(--lighter-grey); 
    word-wrap: break-word; 
}

.event-description-text p:last-child { 
    margin-bottom: 0; 
}

.event-images-grid {
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); 
    gap: 1rem; 
}

.event-image-item img {
    width: 100%; 
    height: 160px; 
    object-fit: cover; 
    border-radius: 6px; 
    border: 1px solid var(--darker-gold-2); 
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.event-image-item img:hover { 
    opacity: 0.9; 
}

.event-participation-section {
    padding: 1.5rem; 
    border-radius: 8px; 
    margin-bottom: 2rem; 
    border: 1px solid var(--darker-gold-2);
    background-color: transparent;
}

.participation-buttons { 
    margin-bottom: 2rem; 
    text-align: center; 
    padding: 1rem;  
}

.participation-buttons form { 
    display: inline-block; 
    margin: 0.25rem 0.5rem; 
}

.participation-buttons .btn { 
    font-weight: 500; 
    font-size: 0.9rem; 
    padding: 0.75rem 1.25rem; 
    border-radius: 6px;
}

.participation-buttons .btn.active-status { 
    opacity: 0.7; 
    cursor: default; 
    border: 2px solid var(--gold) !important; 
}

.participation-buttons .btn i.fas { 
    margin-right: 0.5rem; 
}

.participant-columns-container {
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
    gap: 1.5rem; 
    margin-top: 1rem;
}

.participant-list-column h4.participant-list-title {
    color: var(--gold); 
    font-size: 1.125rem; 
    margin: 0 0 1rem 0;
    padding-bottom: 0.75rem; 
    border-bottom: 1px solid var(--darker-gold-1); 
    font-weight: 400;
    display: flex; 
    align-items: center; 
    gap: 0.5rem;
}

ul.participant-list { 
    list-style-type: none; 
    padding-left: 0; 
    margin-top: 0.5rem; 
}

ul.participant-list li {
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    padding: 0.5rem 0; 
    border-bottom: 1px solid var(--darker-gold-2); 
    font-size: 0.9rem; 
}

ul.participant-list li:last-child { 
    border-bottom: none; 
}

.participant-avatar { 
    width: 24px; 
    height: 24px; 
    border-radius: 50%; 
    object-fit: cover; 
    border: 1px solid var(--darker-gold-1); 
}

.avatar-placeholder-participant { 
    width: 24px; 
    height: 24px; 
    font-size: 0.8em; 
    background-color: var(--grey); 
    color: var(--gold); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 500; 
    border: 1px solid var(--darker-gold-1); 
    border-radius: 50%;
}

.participant-name-link { 
    color: inherit !important; 
    font-weight: 500; 
    text-decoration: none; 
    font-size: 0.9rem;
}

.participant-name-link:hover {
    color: var(--gold) !important;
}

.participant-ingame { 
    font-size: 0.8rem; 
    color: var(--light-grey); 
    margin-left: 0.25rem; 
    font-style: italic; 
}

.no-participants-message {
    font-size: 0.9em; 
    text-align: left; 
    padding-left: 0.25rem;
    color: var(--light-grey);
}

.event-admin-actions {
    margin-top: 2rem; 
    padding: 1.5rem;
    border-radius: 8px; 
    text-align: center; 
    border: 1px solid var(--darker-gold-2); 
    background-color: transparent; 
}

.event-admin-actions form { 
    display: inline-block; 
    margin: 0.25rem 0.5rem; 
}

.event-admin-actions .btn { 
    font-weight: 500; 
    font-size: 0.9rem; 
    padding: 0.75rem 1.25rem; 
    border-radius: 6px;
}

.event-admin-actions .btn i.fas { 
    margin-right: 0.5rem;
}

.access-denied-message-detail {
    text-align: center;
    font-size: 1rem;
    color: var(--light-grey);
    padding: 3rem 2rem;
    border: 1px dashed var(--grey);
    border-radius: 6px;
    margin: 2rem auto;
    max-width: 600px;
}

.access-denied-message-detail a {
    color: var(--turquase);
    font-weight: 500;
    text-decoration: none;
}

.access-denied-message-detail a:hover {
    text-decoration: underline;
}

/* Username Role Colors - Rol tabanlı renkler */
.user-info-trigger.username-role-admin .creator-name-link,
.user-info-trigger.username-role-admin .participant-name-link { 
    color: #0091ff !important; /* Admin rengi */
}

.user-info-trigger.username-role-scg_uye .creator-name-link,
.user-info-trigger.username-role-scg_uye .participant-name-link { 
    color: #a52a2a !important; /* SCG üye rengi */
}

.user-info-trigger.username-role-ilgarion_turanis .creator-name-link,
.user-info-trigger.username-role-ilgarion_turanis .participant-name-link { 
    color: #3da6a2 !important; /* Ilgarion Turanis rengi */
}

.user-info-trigger.username-role-member .creator-name-link,
.user-info-trigger.username-role-member .participant-name-link { 
    color: #0000ff !important; /* Member rengi */
}

.user-info-trigger.username-role-dis_uye .creator-name-link,
.user-info-trigger.username-role-dis_uye .participant-name-link { 
    color: #808080 !important; /* Dış üye rengi */
}

/* Dinamik rol renkleri için */
.user-info-trigger[data-role-color] .creator-name-link,
.user-info-trigger[data-role-color] .participant-name-link {
    color: var(--dynamic-role-color) !important;
}

/* Modal overrides */
#galleryModal .caption-v2-horizontal { 
    display: none !important; 
} 

/* Responsive Design */
@media (max-width: 768px) {
    .event-content-section { 
        padding: 1rem; 
    }
    
    .participation-buttons { 
        flex-direction: column; 
        gap: 0.5rem; 
        padding: 1rem; 
    }
    
    .participation-buttons .btn { 
        width: 100%; 
    }
    
    .participant-columns-container { 
        grid-template-columns: 1fr;
        gap: 1rem; 
    }
    
    .event-admin-actions { 
        justify-content: center; 
        padding: 1rem; 
    }
    
    .event-admin-actions .btn { 
        width: 100%; 
        max-width: 280px; 
    }
}

@media (max-width: 480px) {
    .event-detail-page-container {
        padding: 1rem 0.75rem;
    }
    
    .event-main-title {
        font-size: 1.75rem;
    }
    
    .event-key-details-section {
        padding: 0.75rem;
    }
    
    .key-detail-item {
        padding: 0.75rem;
    }
    
    .event-content-section {
        padding: 0.75rem;
    }
    
    .participation-buttons {
        padding: 0.75rem;
    }
}
</style>

<main class="main-content">
    <div class="container event-detail-page-container">

        <?php if (!$can_view_event_detail && !empty($access_message_detail)): ?>
            <div class="access-denied-message-detail">
                <p><i class="fas fa-lock" style="color: var(--gold); margin-right: 10px; font-size: 1.5em;"></i></p>
                <p><?php echo $access_message_detail; ?></p>
                <p style="margin-top: 20px;">
                    <a href="<?php echo get_auth_base_url(); ?>/events.php" class="btn btn-secondary btn-sm">Tüm Etkinliklere Dön</a>
                </p>
            </div>
        <?php elseif ($event && $can_view_event_detail): ?>
            <div class="page-top-nav-controls">
                <a href="events.php" class="back-to-events-link">
                    <i class="fas fa-arrow-left"></i> Tüm Etkinliklere Dön
                </a>
                <?php if ($can_edit_this_event): ?>
                    <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn-edit-event">
                        <i class="fas fa-edit"></i> Etkinliği Düzenle
                    </a>
                <?php endif; ?>
            </div>

            <div class="event-main-header">
                <h1 class="event-main-title"><?php echo htmlspecialchars($event['title']); ?></h1>

                <?php if ($event['status'] === 'cancelled'): ?>
                    <p class="event-status-alert cancelled">
                        <strong><i class="fas fa-exclamation-triangle"></i> Bu etkinlik iptal edilmiştir.</strong>
                    </p>
                <?php elseif ($event['status'] === 'past' || new DateTime($event['event_datetime']) < new DateTime()): ?>
                    <p class="event-status-alert past">
                        <strong><i class="fas fa-history"></i> Bu etkinlik geçmiş bir tarihtedir.</strong>
                    </p>
                <?php endif; ?>

                <div class="event-meta-details">
                    <?php
                    $creator_data_for_popover_detail = [
                        'id' => $event['creator_id'],
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
                        $creator_data_for_popover_detail,
                        'creator-name-link',
                        'creator-avatar',
                        'event-creator-info'
                    );
                    ?>
                </div>
            </div>

            <div class="event-key-details-section">
                <div class="key-detail-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <p class="detail-label">Etkinlik Zamanı</p>
                        <strong class="detail-value"><?php echo date('d F Y, H:i', strtotime($event['event_datetime'])); ?></strong>
                    </div>
                </div>
                <?php if ($suggested_loadout): ?>
                <div class="key-detail-item">
                     <i class="fas fa-user-shield"></i>
                     <div>
                        <p class="detail-label">Önerilen Teçhizat</p>
                        <strong class="detail-value">
                            <a href="loadout_detail.php?set_id=<?php echo $suggested_loadout['id']; ?>">
                                <?php echo htmlspecialchars($suggested_loadout['set_name']); ?>
                            </a>
                        </strong>
                    </div>
                </div>
                <?php endif; ?>
                 <div class="key-detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <p class="detail-label">Konum</p>
                        <strong class="detail-value"><?php echo htmlspecialchars($event['location'] ?: 'Belirtilmemiş'); ?></strong>
                    </div>
                </div>
                <div class="key-detail-item">
                    <i class="fas fa-tag"></i>
                    <div>
                        <p class="detail-label">Etkinlik Tipi</p>
                        <strong class="detail-value"><?php echo htmlspecialchars($event['event_type']); ?></strong>
                    </div>
                </div>
                 <div class="key-detail-item">
                    <i class="fas fa-eye"></i>
                    <div>
                        <p class="detail-label">Görünürlük</p>
                        <strong class="detail-value">
                            <?php
                            switch ($event['visibility']) {
                                case 'public': echo 'Herkese Açık'; break;
                                case 'members_only': echo 'Sadece Üyelere'; break;
                                case 'faction_only': echo 'Sadece Fraksiyon Üyelerine'; break;
                                default: echo ucfirst($event['visibility']);
                            }
                            ?>
                        </strong>
                    </div>
                </div>
                <div class="key-detail-item">
                    <i class="fas fa-users"></i>
                    <div>
                        <p class="detail-label">Katılımcı Limiti</p>
                        <strong class="detail-value"><?php echo $event['max_participants'] !== null ? htmlspecialchars($event['max_participants']) : 'Sınırsız'; ?></strong>
                    </div>
                </div>
            </div>

            <?php if (!empty($event['description'])): ?>
            <div class="event-content-section">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i>Etkinlik Açıklaması
                </h3>
                <div class="event-description-text">
                    <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($event_photos_for_js_modal)): ?>
            <div class="event-content-section">
                <h3 class="section-title">
                    <i class="fas fa-images"></i>Etkinlik Fotoğrafları
                </h3>
                <div class="event-images-grid">
                    <?php foreach ($event_photos_for_js_modal as $index => $img_data): ?>
                        <div class="event-image-item">
                            <img src="<?php echo htmlspecialchars($img_data['image_path_full']); ?>"
                                 alt="<?php echo htmlspecialchars($img_data['photo_description'] ?? ($event['title'] . ' fotoğrafı')); ?>"
                                 onclick="openEventPhotoModal(<?php echo $index; ?>)">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="event-participation-section">
                <h3 class="section-title">
                    <i class="fas fa-calendar-check"></i>Katılım Durumu
                </h3>

                <?php if ($can_participate): ?>
                    <div class="participation-buttons">
                        <form action="/src/actions/handle_event_participation.php" method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="participation_status" value="attending">
                            <button type="submit" class="btn btn-success <?php echo ($user_current_participation_status === 'attending' ? 'active-status' : ''); ?>"
                                    <?php echo ($user_current_participation_status === 'attending' ? 'disabled' : ''); ?>
                                    <?php echo ($event['max_participants'] !== null && count($attending_participants) >= $event['max_participants'] && $user_current_participation_status !== 'attending' ? 'disabled title="Kontenjan dolu"' : ''); ?>>
                                <i class="fas fa-check-circle"></i> Katılıyorum <?php echo ($user_current_participation_status === 'attending' ? '✓' : ''); ?>
                            </button>
                        </form>
                         <form action="/src/actions/handle_event_participation.php" method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="participation_status" value="maybe">
                            <button type="submit" class="btn btn-warning <?php echo ($user_current_participation_status === 'maybe' ? 'active-status' : ''); ?>" <?php echo ($user_current_participation_status === 'maybe' ? 'disabled' : ''); ?>>
                                <i class="fas fa-question-circle"></i> Belki Katılırım <?php echo ($user_current_participation_status === 'maybe' ? '✓' : ''); ?>
                            </button>
                        </form>
                        <form action="/src/actions/handle_event_participation.php" method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="participation_status" value="declined">
                            <button type="submit" class="btn btn-danger <?php echo ($user_current_participation_status === 'declined' ? 'active-status' : ''); ?>" <?php echo ($user_current_participation_status === 'declined' ? 'disabled' : ''); ?>>
                                <i class="fas fa-times-circle"></i> Katılmıyorum <?php echo ($user_current_participation_status === 'declined' ? '✓' : ''); ?>
                            </button>
                        </form>
                    </div>
                <?php elseif ($event['status'] !== 'active' || (new DateTime($event['event_datetime']) < new DateTime())): ?>
                    <p class="event-status-alert info">Bu etkinliğe katılım durumu bildirilemez (aktif değil veya geçmişte).</p>
                <?php elseif (!$current_user_is_approved): ?>
                     <p class="event-status-alert info">Katılım durumu belirtmek için hesabınızın onaylanmış olması gerekmektedir.</p>
                <?php elseif (!$current_user_is_logged_in): ?>
                     <p class="event-status-alert info">Katılım durumu belirtmek için <a href="<?php echo get_auth_base_url(); ?>/login.php">giriş yapmalısınız</a>.</p>
                <?php else: // Katılım yetkisi yoksa (event.participate) ?>
                     <p class="event-status-alert info">Bu etkinliğe katılım durumu bildirme yetkiniz bulunmamaktadır.</p>
                <?php endif; ?>

                <div class="participant-columns-container">
                    <div class="participant-list-column">
                        <h4 class="participant-list-title">
                            <i class="fas fa-user-check"></i>Katılanlar (<?php echo count($attending_participants); ?>)
                        </h4>
                        <?php if (!empty($attending_participants)): ?>
                            <ul class="participant-list">
                                <?php foreach ($attending_participants as $participant): ?>
                                    <li>
                                        <?php
                                        // Participant verilerini popover için hazırla
                                        $participant_data_for_popover = [
                                            'id' => $participant['user_id'],
                                            'username' => $participant['username'],
                                            'avatar_path' => $participant['avatar_path'],
                                            'ingame_name' => $participant['ingame_name'],
                                            'discord_username' => $participant['participant_discord_username'],
                                            'user_event_count' => $participant['participant_event_count'],
                                            'user_gallery_count' => $participant['participant_gallery_count'],
                                            'user_roles_list' => $participant['participant_roles_list'],
                                            'primary_role' => $participant['participant_primary_role'] ?? 'member',
                                            'primary_role_color' => $participant['participant_primary_role_color'] ?? '#0000ff'
                                        ];
                                        
                                        echo render_user_info_with_popover(
                                            $pdo,
                                            $participant_data_for_popover,
                                            'participant-name-link',
                                            'participant-avatar',
                                            ''
                                        );
                                        ?>
                                        <span class="participant-ingame">(<?php echo htmlspecialchars($participant['ingame_name'] ?: '-'); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-participants-message">Henüz kimse katılmıyor.</p>
                        <?php endif; ?>
                    </div>
                    <div class="participant-list-column">
                        <h4 class="participant-list-title">
                            <i class="fas fa-user-clock"></i>Belki Katılırım (<?php echo count($maybe_participants); ?>)
                        </h4>
                        <?php if (!empty($maybe_participants)): ?>
                            <ul class="participant-list">
                                <?php foreach ($maybe_participants as $participant): ?>
                                    <li>
                                        <?php
                                        $participant_data_for_popover = [
                                            'id' => $participant['user_id'],
                                            'username' => $participant['username'],
                                            'avatar_path' => $participant['avatar_path'],
                                            'ingame_name' => $participant['ingame_name'],
                                            'discord_username' => $participant['participant_discord_username'],
                                            'user_event_count' => $participant['participant_event_count'],
                                            'user_gallery_count' => $participant['participant_gallery_count'],
                                            'user_roles_list' => $participant['participant_roles_list'],
                                            'primary_role' => $participant['participant_primary_role'] ?? 'member',
                                            'primary_role_color' => $participant['participant_primary_role_color'] ?? '#0000ff'
                                        ];
                                        
                                        echo render_user_info_with_popover(
                                            $pdo,
                                            $participant_data_for_popover,
                                            'participant-name-link',
                                            'participant-avatar',
                                            ''
                                        );
                                        ?>
                                        <span class="participant-ingame">(<?php echo htmlspecialchars($participant['ingame_name'] ?: '-'); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-participants-message">"Belki katılırım" diyen kimse yok.</p>
                        <?php endif; ?>
                    </div>
                    <div class="participant-list-column">
                        <h4 class="participant-list-title">
                            <i class="fas fa-user-times"></i>Katılmıyorum (<?php echo count($declined_participants); ?>)
                        </h4>
                        <?php if (!empty($declined_participants)): ?>
                            <ul class="participant-list">
                                <?php foreach ($declined_participants as $participant): ?>
                                    <li>
                                        <?php
                                        $participant_data_for_popover = [
                                            'id' => $participant['user_id'],
                                            'username' => $participant['username'],
                                            'avatar_path' => $participant['avatar_path'],
                                            'ingame_name' => $participant['ingame_name'],
                                            'discord_username' => $participant['participant_discord_username'],
                                            'user_event_count' => $participant['participant_event_count'],
                                            'user_gallery_count' => $participant['participant_gallery_count'],
                                            'user_roles_list' => $participant['participant_roles_list'],
                                            'primary_role' => $participant['participant_primary_role'] ?? 'member',
                                            'primary_role_color' => $participant['participant_primary_role_color'] ?? '#0000ff'
                                        ];
                                        
                                        echo render_user_info_with_popover(
                                            $pdo,
                                            $participant_data_for_popover,
                                            'participant-name-link',
                                            'participant-avatar',
                                            ''
                                        );
                                        ?>
                                        <span class="participant-ingame">(<?php echo htmlspecialchars($participant['ingame_name'] ?: '-'); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-participants-message">Henüz "Katılmıyorum" diyen kimse yok.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($can_cancel_this_event || $can_delete_this_event_admin): ?>
            <div class="event-admin-actions">
                <?php if ($can_cancel_this_event && $event['status'] === 'active'): ?>
                    <form action="/src/actions/handle_event_actions.php" method="POST" onsubmit="return confirm('Bu etkinliği iptal etmek istediğinizden emin misiniz?');">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="action" value="cancel_event">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban"></i> Etkinliği İptal Et
                        </button>
                    </form>
                <?php elseif ($can_cancel_this_event && ($event['status'] === 'cancelled' || $event['status'] === 'past')): ?>
                     <form action="/src/actions/handle_event_actions.php" method="POST" onsubmit="return confirm('Bu etkinliği tekrar aktif yapmak istediğinizden emin misiniz?');">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="action" value="activate_event">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-play-circle"></i> Tekrar Aktif Et
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($can_delete_this_event_admin): ?>
                    <form action="/src/actions/handle_event_actions.php" method="POST" onsubmit="return confirm('Bu etkinliği KALICI OLARAK silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="action" value="delete_event">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-trash-alt"></i> Etkinliği Sil (Admin)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php elseif (!$event && empty($access_message_detail)): ?>
             <p class="access-denied-message-detail">Etkinlik bilgileri yüklenemedi veya bulunamadı.</p>
        <?php endif; ?>
    </div>
</main>

<div id="galleryModal" class="modal">
    <span class="close-modal" id="closeGalleryModalSpan">&times;</span>
    <a class="prev-modal" id="prevGalleryModalButton">&#10094;</a>
    <a class="next-modal" id="nextGalleryModalButton">&#10095;</a>
    <div class="modal-content-wrapper">
        <img class="modal-content" id="galleryModalImage" alt="Etkinlik Fotoğrafı">
        <div id="galleryCaptionTextV2" class="caption-v2-horizontal" style="display:none !important;">
             <?php /* Bu kısım etkinlik fotoğrafları için kullanılmayacak, CSS ile tamamen gizlendi */ ?>
        </div>
    </div>
</div>

<script>
window.eventPhotosForModal = <?php echo json_encode($event_photos_for_js_modal); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Event photo modal functionality
    window.openEventPhotoModal = function(index) {
        if (typeof window.eventPhotosForModal !== 'undefined' && Array.isArray(window.eventPhotosForModal) && window.eventPhotosForModal[index]) {
            const photoData = window.eventPhotosForModal[index];
            const modal = document.getElementById('galleryModal');
            const modalImg = document.getElementById('galleryModalImage');
            const modalCaptionContainer = document.getElementById('galleryCaptionTextV2');

            if (modal && modalImg && modalCaptionContainer) {
                modalImg.src = photoData.image_path_full;
                modalImg.alt = photoData.photo_description || 'Etkinlik Fotoğrafı';
                modal.style.display = 'flex';
                
                const prevBtn = document.getElementById('prevGalleryModalButton');
                const nextBtn = document.getElementById('nextGalleryModalButton');
                
                if(prevBtn) {
                    prevBtn.style.display = (index > 0) ? "block" : "none";
                    prevBtn.onclick = function() { 
                        if(window.currentRichModalIndex > 0) { 
                            openEventPhotoModal(window.currentRichModalIndex - 1);
                        }
                    };
                }
                if(nextBtn) {
                    nextBtn.style.display = (index < window.eventPhotosForModal.length - 1) ? "block" : "none";
                     nextBtn.onclick = function() {
                        if(window.currentRichModalItems && window.currentRichModalIndex < window.currentRichModalItems.length - 1) { 
                            openEventPhotoModal(window.currentRichModalIndex + 1);
                        }
                    };
                }
                window.currentRichModalIndex = index; 
                window.currentRichModalItems = window.eventPhotosForModal;
            }
        } else {
            console.error("Etkinlik fotoğrafı verisi veya modal elemanları bulunamadı. Index: " + index);
        }
    };
    
    // Modal controls
    const galleryModal = document.getElementById('galleryModal');
    if(galleryModal){
        const closeBtn = document.getElementById('closeGalleryModalSpan');
        if(closeBtn) closeBtn.onclick = function() { galleryModal.style.display = "none"; };
        
        galleryModal.addEventListener('click', function(event) {
            if (event.target == galleryModal) galleryModal.style.display = "none";
        });
        
        document.addEventListener('keydown', function(event) {
            if (galleryModal.style.display === "flex") {
                const prevBtn = document.getElementById('prevGalleryModalButton'); 
                const nextBtn = document.getElementById('nextGalleryModalButton');
                if (event.key === "Escape") {
                     galleryModal.style.display = "none";
                } else if (event.key === "ArrowLeft" && prevBtn && prevBtn.style.display !== "none") {
                    if(window.currentRichModalIndex > 0) { 
                        openEventPhotoModal(window.currentRichModalIndex - 1);
                    }
                } else if (event.key === "ArrowRight" && nextBtn && nextBtn.style.display !== "none") {
                    if(window.currentRichModalItems && window.currentRichModalIndex < window.currentRichModalItems.length - 1) { 
                        openEventPhotoModal(window.currentRichModalIndex + 1);
                    }
                }
            }
        });
    }

    // Enhanced form interactions
    const participationForms = document.querySelectorAll('.participation-buttons form');
    participationForms.forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('button');
            if (button && !button.disabled) {
                button.style.opacity = '0.6';
                button.disabled = true;
                setTimeout(() => {
                    button.style.opacity = '';
                    button.disabled = false;
                }, 2000);
            }
        });
    });

    // Scroll animations for content sections
    if ('IntersectionObserver' in window) {
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -20px 0px'
        });
        
        document.querySelectorAll('.event-content-section, .event-participation-section').forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(15px)';
            section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            sectionObserver.observe(section);
        });
    }

    // Dinamik rol renklerini uygula
    const userInfoTriggers = document.querySelectorAll('.user-info-trigger');
    userInfoTriggers.forEach(trigger => {
        const roleColor = trigger.dataset.roleColor;
        if (roleColor) {
            trigger.style.setProperty('--dynamic-role-color', roleColor);
        }
    });
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>