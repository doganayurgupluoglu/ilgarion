<?php
// public/event_detail.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
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

$can_view_event_detail = false;
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
                    (SELECT GROUP_CONCAT(r.name SEPARATOR ',') FROM user_roles ur_creator JOIN roles r ON ur_creator.role_id = r.id WHERE ur_creator.user_id = u.id) AS creator_roles_list
                FROM events e JOIN users u ON e.created_by_user_id = u.id
                WHERE e.id = :event_id";
    $stmt_event = $pdo->prepare($sql_event);
    $stmt_event->bindParam(':event_id', $event_id, PDO::PARAM_INT);
    $stmt_event->execute();
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        $page_title = htmlspecialchars($event['title']);

        // YETKİLENDİRME MANTIĞI
        if ($event['visibility'] === 'public' && has_permission($pdo, 'event.view_public', $current_user_id)) {
            $can_view_event_detail = true;
        } elseif ($current_user_is_approved) { // Giriş yapmış ve onaylı kullanıcılar için
            if ($event['visibility'] === 'members_only' && has_permission($pdo, 'event.view_members_only', $current_user_id)) {
                $can_view_event_detail = true;
            } elseif ($event['visibility'] === 'faction_only' && has_permission($pdo, 'event.view_faction_only', $current_user_id)) {
                // Kullanıcının rollerini al (ID olarak)
                $stmt_user_role_ids_detail = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :user_id");
                $stmt_user_role_ids_detail->execute([':user_id' => $current_user_id]);
                $current_user_role_ids_detail = $stmt_user_role_ids_detail->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($current_user_role_ids_detail)) {
                    $stmt_event_roles_detail = $pdo->prepare("SELECT evr.role_id FROM event_visibility_roles evr WHERE evr.event_id = :event_id");
                    $stmt_event_roles_detail->execute([':event_id' => $event_id]);
                    $allowed_role_ids_for_event_detail = $stmt_event_roles_detail->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty(array_intersect($current_user_role_ids_detail, $allowed_role_ids_for_event_detail))) {
                        $can_view_event_detail = true;
                    }
                }
            }
        }

        // Etkinliği oluşturan veya 'event.view_all' yetkisine sahip olanlar her zaman görebilir
        if ($current_user_is_logged_in && ($event['created_by_user_id'] == $current_user_id || has_permission($pdo, 'event.view_all', $current_user_id))) {
            $can_view_event_detail = true;
        }

        if (!$can_view_event_detail) {
            if (!$current_user_is_logged_in) {
                 $access_message_detail = "Bu etkinliği görmek için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
            } elseif (!$current_user_is_approved) {
                $access_message_detail = "Bu etkinliği görüntüleyebilmek için hesabınızın onaylanmış olması gerekmektedir.";
            } else {
                $access_message_detail = "Bu etkinliği görüntüleme yetkiniz bulunmamaktadır.";
            }
        }
        // YETKİLENDİRME MANTIĞI SONU

        if ($can_view_event_detail) {
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
                                        (SELECT GROUP_CONCAT(r.name SEPARATOR ',') FROM user_roles ur_p JOIN roles r ON ur_p.role_id = r.id WHERE ur_p.user_id = u.id) AS participant_roles_list
                                     FROM event_participants ep
                                     JOIN users u ON ep.user_id = u.id
                                     WHERE ep.event_id = :event_id
                                     ORDER BY CASE ep.participation_status WHEN 'attending' THEN 1 WHEN 'maybe' THEN 2 WHEN 'declined' THEN 3 ELSE 4 END, u.username ASC";
            $stmt_all_participants = $pdo->prepare($sql_all_participants);
            $stmt_all_participants->bindParam(':event_id', $event_id, PDO::PARAM_INT);
            $stmt_all_participants->execute();
            $all_participants_with_details_and_roles = $stmt_all_participants->fetchAll(PDO::FETCH_ASSOC);

            foreach($all_participants_with_details_and_roles as $participant) {
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

// Buton görünürlükleri için yetki kontrolleri
$can_edit_this_event = $current_user_id && $event && (has_permission($pdo, 'event.edit_all', $current_user_id) || (has_permission($pdo, 'event.edit_own', $current_user_id) && $event['created_by_user_id'] == $current_user_id));
$can_delete_this_event_admin = $current_user_is_admin && has_permission($pdo, 'event.delete_all', $current_user_id); // Sadece admin için ayrı bir silme
$can_cancel_this_event = $current_user_id && $event && (has_permission($pdo, 'event.manage_participants', $current_user_id) || $current_user_is_admin || ($event['created_by_user_id'] == $current_user_id && has_permission($pdo, 'event.edit_own', $current_user_id))); // Genişletilmiş iptal yetkisi
$can_participate = $current_user_is_approved && $event && $event['status'] === 'active' && (new DateTime($event['event_datetime']) >= new DateTime()) && has_permission($pdo, 'event.participate', $current_user_id);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* ... (mevcut event_detail.php stilleriniz aynı kalacak) ... */
.event-detail-page-container-v4 { 
    width: 100%;
    max-width: 1100px; 
    margin: 40px auto; 
    padding: 0 20px; 
    font-family: var(--font);
    color: var(--lighter-grey);
}
.page-top-nav-controls-evd {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px; 
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-2); 
}
.back-to-events-link-evd {
    font-size: 0.9rem; color: var(--turquase); text-decoration: none;
    display: inline-flex; align-items: center; gap: 7px;
    padding: 7px 15px; 
    border-radius: 18px; 
    border: 1px solid var(--turquase); 
    background-color: transparent; 
    transition: all 0.2s ease;
}
.back-to-events-link-evd:hover { color: var(--white); background-color: var(--turquase); border-color:var(--turquase); }
.back-to-events-link-evd i.fas { font-size: 0.85em; }
.btn-edit-event-evd {
    background-color: var(--gold); color: var(--darker-gold-2); padding: 7px 15px;
    border-radius: 18px; text-decoration: none; font-weight: 600; font-size: 0.85rem; 
    transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px;
    border: 1px solid var(--gold); 
}
.btn-edit-event-evd:hover { background-color: var(--light-gold); color: var(--black); transform: translateY(-1px); border-color:var(--light-gold);}
.btn-edit-event-evd i.fas { font-size:0.85em;}
.event-main-header-evd {
    padding: 20px 0; 
    border-radius: 8px; 
    margin-bottom: 35px; 
    border: 1px solid var(--darker-gold-2); 
    background-color: transparent; 
    text-align: center; 
}
.event-main-title-evd {
    color: var(--gold); font-size: 2.8rem; 
    font-family: var(--font); margin: 0 0 20px 0; line-height: 1.2; 
    font-weight: 700; 
    letter-spacing: -0.5px;
}
.event-status-alert-evd {
    padding: 10px 15px; margin: 25px auto; border-radius: 6px; font-size: 0.95rem;
    border: 1px solid; text-align:center; font-weight:500;
    max-width: 600px; 
}
.event-status-alert-evd.cancelled { background-color: rgba(var(--red-rgb), 0.1); color: var(--red); border-color: var(--red); }
.event-status-alert-evd.past { background-color: rgba(var(--turquase-rgb), 0.1); color: var(--turquase); border-color: var(--turquase); }
.event-meta-details-evd {
    margin-top: 10px; padding-top: 15px;
    border-top: 1px solid var(--darker-gold-2); 
    font-size: 0.9rem; 
    color: var(--light-grey);
    display: flex; 
    flex-direction: row; 
    align-items: center; 
    justify-content: center; 
    flex-wrap: wrap; 
    gap: 15px 25px; 
}
.event-meta-details-evd p { margin-bottom: 0; display: flex; align-items: center; gap: 6px;} 
.event-meta-details-evd p i.fas { color: var(--light-gold); font-size: 0.9em; }
.event-meta-details-evd strong { color: var(--lighter-grey); font-weight: 400; }
.event-creator-info-evd { display: inline-flex; align-items: center; gap: 8px; cursor: default; } 
.creator-avatar-evd { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--gold); }
.avatar-placeholder-evd { width: 32px; height: 32px; font-size:1rem; background-color:var(--grey); color:var(--gold); display:flex; align-items:center; justify-content:center; font-weight:bold; border:1px solid var(--darker-gold-1); border-radius:50%;}
.creator-name-link-evd { color: inherit !important; font-weight: 500; font-size:0.95em; text-decoration:none; }
.creator-name-link-evd:hover { text-decoration:underline; }
.event-key-details-section {
    display: flex;
    flex-wrap: wrap;
    gap: 20px; 
    margin-bottom: 35px;
    padding: 25px;
    border: 1px solid var(--darker-gold-2);
    border-radius: 10px;
    background-color: transparent; 
}
.key-detail-item {
    background-color: var(--darker-gold-2); 
    padding: 18px 22px;
    border-radius: 8px;
    flex: 1 1 280px; 
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid var(--gold);
    transition: background-color 0.2s ease;
}
.key-detail-item:hover {
    background-color: var(--darker-gold-1);
}
.key-detail-item i.fas {
    color: var(--gold);
    font-size: 1.6em; 
    width: 30px; 
    text-align: center;
}
.key-detail-item div { display: flex; flex-direction: column; }
.key-detail-item p.detail-label { margin: 0 0 3px 0; font-size: 0.85rem; color: var(--light-grey); text-transform: uppercase; letter-spacing: 0.5px;}
.key-detail-item strong.detail-value { font-size: 1.1em; color: var(--lighter-grey); font-weight: 600; }
.key-detail-item strong.detail-value a { color: var(--turquase); text-decoration:none; }
.key-detail-item strong.detail-value a:hover { text-decoration:underline; }
.event-content-section-evd {
    border-radius: 8px; margin-bottom: 35px; 
    background-color: transparent; 
    border: 1px solid var(--darker-gold-2);
    padding: 30px; /* İçerik bölümü için padding artırıldı */
}
.section-title-evd {
 color: var(--gold); font-size: 1.8rem; margin: 0 0 20px 0;
    padding-bottom: 12px; border-bottom: 1px solid var(--darker-gold-2); 
    font-family: var(--font); font-weight: 600; display:flex; align-items:center; gap:10px;
}
.event-description-text-evd p { line-height: 1.75; font-size: 1rem; color: var(--lighter-grey); word-wrap: break-word; }
.event-description-text-evd p:last-child { margin-bottom:0; }
.event-images-grid-evd {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
    gap: 15px; 
}
.event-image-item-evd img {
    width: 100%; height: 180px; object-fit: cover; border-radius: 6px; 
    border: 1px solid var(--darker-gold-2); cursor: pointer;
    transition: transform 0.25s ease, box-shadow 0.25s ease, opacity 0.25s ease;
}
.event-image-item-evd img:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(var(--gold-rgb,189,145,42),0.2); opacity:0.9; }
.event-participation-section-evd {
    padding: 30px; border-radius: 8px; margin-bottom: 35px; border: 1px solid var(--darker-gold-2);
    background-color: transparent;
}
.participation-buttons-evd { margin-bottom: 30px; text-align:center; padding: 20px;  }
.participation-buttons-evd form { display: inline-block; margin: 5px 8px; }
.participation-buttons-evd .btn { font-weight:500; font-size:0.95rem; padding: 9px 20px; border-radius:25px;}
.participation-buttons-evd .btn.active-status { opacity:0.7; cursor:default; border:2px solid var(--gold) !important; filter: brightness(1.1);}
.participation-buttons-evd .btn i.fas { margin-right:8px; }
.participant-columns-container-evd {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); 
    gap: 25px; margin-top: 20px;
}
.participant-list-column-evd h4.participant-list-title {
    color: var(--gold); font-size: 1.3rem; margin: 0 0 18px 0;
    padding-bottom: 12px; border-bottom: 1px solid var(--darker-gold-1); font-weight:600;
    display:flex; align-items:center; gap:10px;
}
ul.participant-list-evd { list-style-type: none; padding-left: 0; margin-top: 10px; }
ul.participant-list-evd li {
    display: flex; align-items: center; gap:10px; 
    padding: 8px 0px; 
    border-bottom: 1px solid var(--darker-gold-2); font-size: 0.9rem; 
}
ul.participant-list-evd li:last-child { border-bottom: none; }
.participant-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border:1px solid var(--darker-gold-1); }
.avatar-placeholder-participant-evd { width: 32px; height: 32px; font-size:1em; background-color:var(--grey); color:var(--gold); display:flex; align-items:center; justify-content:center; font-weight:bold; border:1px solid var(--darker-gold-1); border-radius:50%;}
.participant-name-link-evd { color: inherit !important; font-weight: 500; text-decoration: none; font-size:0.95rem;}
.participant-ingame-evd { font-size: 0.85rem; color: var(--light-grey); margin-left: 5px; font-style:italic; }
.event-admin-actions-evd {
    margin-top: 35px; padding: 20px;
    border-radius: 8px; text-align: center; border: 1px solid var(--darker-gold-2); 
    background-color: transparent; 
}
.event-admin-actions-evd form { display: inline-block; margin: 5px 8px; }
.event-admin-actions-evd .btn { font-weight:500; font-size:0.9rem; padding:8px 18px; border-radius:20px;}
.event-admin-actions-evd .btn i.fas { margin-right:8px;}
.user-info-trigger.username-role-admin .creator-name-link-evd,
.user-info-trigger.username-role-admin .participant-name-link-evd { color: var(--gold) !important; }
.user-info-trigger.username-role-scg_uye .creator-name-link-evd,
.user-info-trigger.username-role-scg_uye .participant-name-link-evd { color: #A52A2A !important; }
.user-info-trigger.username-role-ilgarion_turanis .creator-name-link-evd,
.user-info-trigger.username-role-ilgarion_turanis .participant-name-link-evd { color: var(--turquase) !important; }
.user-info-trigger.username-role-member .creator-name-link-evd,
.user-info-trigger.username-role-member .participant-name-link-evd { color: var(--white) !important; }
.user-info-trigger.username-role-dis_uye .creator-name-link-evd,
.user-info-trigger.username-role-dis_uye .participant-name-link-evd { color: var(--light-grey) !important; }
#galleryModal .caption-v2-horizontal { display: none !important; } 
.access-denied-message-detail { /* Yeni class */
    text-align: center;
    font-size: 1.1rem;
    color: var(--light-grey);
    padding: 40px 20px;
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px dashed var(--darker-gold-1);
    margin: 40px auto; /* Sayfada ortalamak için */
    max-width: 700px;
}
.access-denied-message-detail a {
    color: var(--turquase);
    font-weight: bold;
}
.access-denied-message-detail a:hover {
    color: var(--light-turquase);
}

@media (max-width: 768px) {
    .event-detail-page-container-v4 { padding: 15px; }
    .page-top-nav-controls-evd { flex-direction:column; align-items:stretch; gap:15px;} 
    .back-to-events-link-evd, .btn-edit-event-evd { width:100%; justify-content:center; }
    .event-main-header-evd h1 { font-size:2.2rem; }
    .event-key-details-section { grid-template-columns: 1fr; gap: 15px; padding:20px; } 
    .highlight-card { padding:15px 20px; }
    .event-content-section-evd { padding: 20px; }
    .participation-buttons-evd { flex-direction: column; gap: 10px; padding:15px; }
    .participation-buttons-evd .btn { width: 100%; }
    .participant-columns-container-evd { grid-template-columns: 1fr; gap:20px; }
    .event-admin-actions-evd { justify-content: center; padding:20px; }
    .event-admin-actions-evd .btn { width: 100%; max-width: 280px; }
}
</style>

<main class="main-content">
    <div class="container event-detail-page-container-v4">

        <?php if (!$can_view_event_detail && !empty($access_message_detail)): ?>
            <div class="access-denied-message-detail">
                <p><i class="fas fa-lock" style="color: var(--gold); margin-right: 10px; font-size: 1.5em;"></i></p>
                <p><?php echo $access_message_detail; ?></p>
                <p style="margin-top: 20px;">
                    <a href="<?php echo get_auth_base_url(); ?>/events.php" class="btn btn-secondary btn-sm">Tüm Etkinliklere Dön</a>
                </p>
            </div>
        <?php elseif ($event && $can_view_event_detail): ?>
            <div class="page-top-nav-controls-evd">
                <a href="events.php" class="back-to-events-link-evd"><i class="fas fa-arrow-left"></i> Tüm Etkinliklere Dön</a>
                <?php if ($can_edit_this_event): ?>
                    <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn-edit-event-evd"><i class="fas fa-edit"></i> Etkinliği Düzenle</a>
                <?php endif; ?>
            </div>

            <div class="event-main-header-evd">
                <h1 class="event-main-title-evd"><?php echo htmlspecialchars($event['title']); ?></h1>

                <?php if ($event['status'] === 'cancelled'): ?>
                    <p class="event-status-alert-evd cancelled"><strong><i class="fas fa-exclamation-triangle"></i> Bu etkinlik iptal edilmiştir.</strong></p>
                <?php elseif ($event['status'] === 'past' || new DateTime($event['event_datetime']) < new DateTime()): ?>
                    <p class="event-status-alert-evd past"><strong><i class="fas fa-history"></i> Bu etkinlik geçmiş bir tarihtedir.</strong></p>
                <?php endif; ?>

                <div class="event-meta-details-evd">
                    <?php
                    $creator_data_for_popover_detail = [
                        'id' => $event['creator_id'],
                        'username' => $event['creator_username'],
                        'avatar_path' => $event['creator_avatar_path'],
                        'ingame_name' => $event['creator_ingame_name'],
                        'discord_username' => $event['creator_discord_username'],
                        'user_event_count' => $event['creator_event_count'],
                        'user_gallery_count' => $event['creator_gallery_count'],
                        'user_roles_list' => $event['creator_roles_list']
                    ];
                    echo render_user_info_with_popover(
                        $pdo, // $pdo eklendi
                        $creator_data_for_popover_detail,
                        'creator-name-link-evd',
                        'creator-avatar-evd',
                        'event-creator-info-evd'
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
                    <div><p class="detail-label">Konum</p><strong class="detail-value"><?php echo htmlspecialchars($event['location'] ?: 'Belirtilmemiş'); ?></strong></div>
                </div>
                <div class="key-detail-item">
                    <i class="fas fa-tag"></i>
                    <div><p class="detail-label">Etkinlik Tipi</p><strong class="detail-value"><?php echo htmlspecialchars($event['event_type']); ?></strong></div>
                </div>
                 <div class="key-detail-item">
                    <i class="fas fa-eye"></i>
                    <div><p class="detail-label">Görünürlük</p><strong class="detail-value">
                        <?php
                        switch ($event['visibility']) {
                            case 'public': echo 'Herkese Açık'; break;
                            case 'members_only': echo 'Sadece Üyelere'; break;
                            case 'faction_only': echo 'Sadece Fraksiyon Üyelerine'; break;
                            default: echo ucfirst($event['visibility']);
                        }
                        ?>
                    </strong></div>
                </div>
                <div class="key-detail-item">
                    <i class="fas fa-users"></i>
                    <div><p class="detail-label">Katılımcı Limiti</p><strong class="detail-value"><?php echo $event['max_participants'] !== null ? htmlspecialchars($event['max_participants']) : 'Sınırsız'; ?></strong></div>
                </div>
            </div>


            <?php if (!empty($event['description'])): ?>
            <div class="event-content-section-evd">
                <h3 class="section-title-evd"><i class="fas fa-info-circle"></i>Etkinlik Açıklaması</h3>
                <div class="event-description-text-evd">
                    <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($event_photos_for_js_modal)): ?>
            <div class="event-content-section-evd">
                <h3 class="section-title-evd"><i class="fas fa-images"></i>Etkinlik Fotoğrafları</h3>
                <div class="event-images-grid-evd">
                    <?php foreach ($event_photos_for_js_modal as $index => $img_data): ?>
                        <div class="event-image-item-evd">
                            <img src="<?php echo htmlspecialchars($img_data['image_path_full']); ?>"
                                 alt="<?php echo htmlspecialchars($img_data['photo_description'] ?? ($event['title'] . ' fotoğrafı')); ?>"
                                 onclick="openEventPhotoModal(<?php echo $index; ?>)">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="event-participation-section-evd">
                <h3 class="section-title-evd"><i class="fas fa-calendar-check"></i>Katılım Durumu</h3>

                <?php if ($can_participate): ?>
                    <div class="participation-buttons-evd">
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
                    <p class="event-status-alert-evd info" style="text-align:center; background-color:var(--charcoal); color:var(--light-grey); border-color:var(--grey);">Bu etkinliğe katılım durumu bildirilemez (aktif değil veya geçmişte).</p>
                <?php elseif (!$current_user_is_approved): ?>
                     <p class="event-status-alert-evd info" style="text-align:center; background-color:var(--charcoal); color:var(--light-grey); border-color:var(--grey);">Katılım durumu belirtmek için hesabınızın onaylanmış olması gerekmektedir.</p>
                <?php elseif (!$current_user_is_logged_in): ?>
                     <p class="event-status-alert-evd info" style="text-align:center; background-color:var(--charcoal); color:var(--light-grey); border-color:var(--grey);">Katılım durumu belirtmek için <a href="<?php echo get_auth_base_url(); ?>/login.php">giriş yapmalısınız</a>.</p>
                <?php else: // Katılım yetkisi yoksa (event.participate) ?>
                     <p class="event-status-alert-evd info" style="text-align:center; background-color:var(--charcoal); color:var(--light-grey); border-color:var(--grey);">Bu etkinliğe katılım durumu bildirme yetkiniz bulunmamaktadır.</p>
                <?php endif; ?>


                <div class="participant-columns-container-evd">
                    <div class="participant-list-column-evd">
                        <h4 class="participant-list-title"><i class="fas fa-user-check"></i>Katılanlar (<?php echo count($attending_participants); ?>)</h4>
                        <?php if (!empty($attending_participants)): ?>
                            <ul class="participant-list-evd">
                                <?php foreach ($attending_participants as $participant): ?>
                                    <li>
                                        <?php
                                        echo render_user_info_with_popover(
                                            $pdo, // $pdo eklendi
                                            $participant,
                                            'participant-name-link-evd',
                                            'participant-avatar',
                                            ''
                                        );
                                        ?>
                                        <span class="participant-ingame-evd">(<?php echo htmlspecialchars($participant['ingame_name'] ?: '-'); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?><p class="no-participants-message" style="font-size:0.9em; text-align:left; padding-left:5px;">Henüz kimse katılmıyor.</p><?php endif; ?>
                    </div>
                    <div class="participant-list-column-evd">
                        <h4 class="participant-list-title"><i class="fas fa-user-clock"></i>Belki Katılırım (<?php echo count($maybe_participants); ?>)</h4>
                        <?php if (!empty($maybe_participants)): ?>
                            <ul class="participant-list-evd">
                                <?php foreach ($maybe_participants as $participant): ?>
                                    <li>
                                        <?php
                                        echo render_user_info_with_popover(
                                            $pdo, // $pdo eklendi
                                            $participant,
                                            'participant-name-link-evd',
                                            'participant-avatar',
                                            ''
                                        );
                                        ?>
                                        <span class="participant-ingame-evd">(<?php echo htmlspecialchars($participant['ingame_name'] ?: '-'); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?><p class="no-participants-message" style="font-size:0.9em; text-align:left; padding-left:5px;">"Belki katılırım" diyen kimse yok.</p><?php endif; ?>
                    </div>
                    <div class="participant-list-column-evd">
                        <h4 class="participant-list-title"><i class="fas fa-user-times"></i>Katılmıyorum (<?php echo count($declined_participants); ?>)</h4>
                        <?php if (!empty($declined_participants)): ?>
                            <ul class="participant-list-evd">
                                <?php foreach ($declined_participants as $participant): ?>
                                    <li>
                                        <?php
                                        echo render_user_info_with_popover(
                                            $pdo, // $pdo eklendi
                                            $participant,
                                            'participant-name-link-evd',
                                            'participant-avatar',
                                            ''
                                        );
                                        ?>
                                        <span class="participant-ingame-evd">(<?php echo htmlspecialchars($participant['ingame_name'] ?: '-'); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?><p class="no-participants-message" style="font-size:0.9em; text-align:left; padding-left:5px;">Henüz "Katılmıyorum" diyen kimse yok.</p><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($can_cancel_this_event || $can_delete_this_event_admin): ?>
            <div class="event-admin-actions-evd">
                <?php if ($can_cancel_this_event && $event['status'] === 'active'): ?>
                    <form action="/src/actions/handle_event_actions.php" method="POST" onsubmit="return confirm('Bu etkinliği iptal etmek istediğinizden emin misiniz?');">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="action" value="cancel_event">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Etkinliği İptal Et</button>
                    </form>
                <?php elseif ($can_cancel_this_event && ($event['status'] === 'cancelled' || $event['status'] === 'past')): ?>
                     <form action="/src/actions/handle_event_actions.php" method="POST" onsubmit="return confirm('Bu etkinliği tekrar aktif yapmak istediğinizden emin misiniz?');">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="action" value="activate_event">
                        <button type="submit" class="btn btn-success"><i class="fas fa-play-circle"></i> Tekrar Aktif Et</button>
                    </form>
                <?php endif; ?>
                <?php if ($can_delete_this_event_admin): ?>
                    <form action="/src/actions/handle_event_actions.php" method="POST" onsubmit="return confirm('Bu etkinliği KALICI OLARAK silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="action" value="delete_event">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-trash-alt"></i> Etkinliği Sil (Admin)</button>
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
// ... (mevcut event_detail.php JavaScript kodunuz aynı kalacak) ...
window.eventPhotosForModal = <?php echo json_encode($event_photos_for_js_modal); ?>;
window.eventPhotosForModal = <?php echo json_encode($event_photos_for_js_modal); ?>;

document.addEventListener('DOMContentLoaded', function() {
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
    ['darker-gold-2', 'darker-gold-1', 'grey', 'gold', 'charcoal', 'turquase', 'red', 'dark-red', 'light-turquase', 'light-gold', 'transparent-red', 'transparent-turquase-2'].forEach(setRgbVar);
});
</script>
<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
