<?php
// public/discussion_detail.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // parse_discussion_quotes_from_safe_html ve render_user_info_with_popover
require_once BASE_PATH . '/src/functions/notification_functions.php'; // Bildirim fonksiyonları

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$topic_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $topic_id = (int)$_GET['id'];
} else {
    $_SESSION['error_message'] = "Geçersiz tartışma konusu ID'si.";
    header('Location: ' . get_auth_base_url() . '/discussions.php');
    exit;
}

// Bildirim okundu işaretleme (navbar'dan gelinirse)
if (isset($_GET['notif_id']) && is_numeric($_GET['notif_id']) && is_user_logged_in()) {
    if (isset($pdo) && function_exists('mark_notification_as_read')) {
        mark_notification_as_read($pdo, (int)$_GET['notif_id'], $_SESSION['user_id']);
    }
}

$topic = null;
$first_post = null;
$comments = [];
$page_title = "Tartışma Detayı";

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

$can_view_this_topic = false;
$access_message_topic_detail = "";

// Rol öncelik sırası (render_user_info_with_popover içinde global $role_priority kullanılıyor)
$role_priority_local_detail = $GLOBALS['role_priority'] ?? ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];


try {
    // Konu bilgilerini ve başlatan kullanıcının detaylarını çek
    $sql_topic = "SELECT
                    dt.*,
                    u.username AS topic_starter_username,
                    u.avatar_path AS topic_starter_avatar,
                    u.ingame_name AS topic_starter_ingame,
                    u.discord_username AS topic_starter_discord,
                    (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS topic_starter_event_count,
                    (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS topic_starter_gallery_count,
                    (SELECT GROUP_CONCAT(r.name ORDER BY FIELD(r.name, '" . implode("','", $GLOBALS['role_priority'] ?? []) . "') SEPARATOR ',')
                     FROM user_roles ur_starter
                     JOIN roles r ON ur_starter.role_id = r.id
                     WHERE ur_starter.user_id = u.id) AS topic_starter_roles_list
                  FROM discussion_topics dt
                  JOIN users u ON dt.user_id = u.id
                  WHERE dt.id = :topic_id";
    $stmt_topic = $pdo->prepare($sql_topic);
    $stmt_topic->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
    $stmt_topic->execute();
    $topic = $stmt_topic->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        $page_title = htmlspecialchars($topic['title']);

        // YETKİLENDİRME MANTIĞI (GÜNCELLENDİ)
        if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'discussion.view_all', $current_user_id))) {
            $can_view_this_topic = true;
        } elseif ($topic['is_public_no_auth'] == 1 && has_permission($pdo, 'discussion.view_public', $current_user_id)) {
            $can_view_this_topic = true;
        } elseif ($current_user_is_approved) { // Sadece onaylı kullanıcılar için diğer kontroller
            if ($topic['is_members_only'] == 1 && has_permission($pdo, 'discussion.view_approved', $current_user_id)) {
                // 'discussion.view_approved' yetkisi, 'is_members_only' konuları görmeyi sağlar.
                $can_view_this_topic = true;
            } elseif ($topic['is_public_no_auth'] == 0 && $topic['is_members_only'] == 0) { // Sadece belirli rollere özel
                $user_actual_role_ids_detail_page = [];
                if ($current_user_id) {
                    $stmt_user_role_ids_detail_page = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :current_user_id_for_roles_detail");
                    $stmt_user_role_ids_detail_page->execute([':current_user_id_for_roles_detail' => $current_user_id]);
                    $user_actual_role_ids_detail_page = $stmt_user_role_ids_detail_page->fetchAll(PDO::FETCH_COLUMN);
                }

                if (!empty($user_actual_role_ids_detail_page)) {
                    $stmt_topic_roles = $pdo->prepare("SELECT role_id FROM discussion_topic_visibility_roles WHERE topic_id = ?");
                    $stmt_topic_roles->execute([$topic_id]);
                    $topic_visible_to_role_ids = $stmt_topic_roles->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty(array_intersect($user_actual_role_ids_detail_page, $topic_visible_to_role_ids))) {
                        $can_view_this_topic = true;
                    }
                }
            }
        }

        // Konu sahibi her zaman kendi konusunu görebilir (eğer $can_view_this_topic hala false ise)
        if (!$can_view_this_topic && $current_user_id && $current_user_id == $topic['user_id']) {
            $can_view_this_topic = true;
        }
        // YETKİLENDİRME SONU

        if (!$can_view_this_topic) {
            if (!$current_user_is_logged_in) {
                 $access_message_topic_detail = "Bu konuyu görmek için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
            } elseif (!$current_user_is_approved) {
                $access_message_topic_detail = "Bu konuyu görüntüleyebilmek için hesabınızın onaylanmış olması gerekmektedir.";
            } else {
                $access_message_topic_detail = "Bu konuyu görüntüleme yetkiniz bulunmamaktadır.";
            }
        } else {
            // Konu okundu olarak işaretle
            if ($current_user_id) {
                $stmt_upsert_view = $pdo->prepare(
                    "INSERT INTO user_topic_views (user_id, topic_id, last_viewed_at)
                     VALUES (:user_id, :topic_id, NOW())
                     ON DUPLICATE KEY UPDATE last_viewed_at = NOW()"
                );
                $stmt_upsert_view->execute([':user_id' => $current_user_id, ':topic_id' => $topic_id]);
            }

            // Yorumları çek (önceki gibi)
            $sql_posts = "SELECT
                            dp.id, dp.user_id, dp.parent_post_id, dp.content, dp.created_at,
                            u.username AS post_author_username,
                            u.avatar_path AS post_author_avatar,
                            u.ingame_name AS post_author_ingame,
                            u.discord_username AS post_author_discord,
                            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS post_author_event_count,
                            (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS post_author_gallery_count,
                            (SELECT GROUP_CONCAT(r_author.name ORDER BY FIELD(r_author.name, '" . implode("','", $GLOBALS['role_priority'] ?? []) . "') SEPARATOR ',')
                             FROM user_roles ur_author
                             JOIN roles r_author ON ur_author.role_id = r_author.id
                             WHERE ur_author.user_id = u.id) AS post_author_roles_list
                          FROM discussion_posts dp
                          JOIN users u ON dp.user_id = u.id
                          WHERE dp.topic_id = :topic_id
                          ORDER BY dp.created_at ASC";
            $stmt_posts = $pdo->prepare($sql_posts);
            $stmt_posts->bindParam(':topic_id', $topic_id, PDO::PARAM_INT);
            $stmt_posts->execute();
            $all_posts_in_topic = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($all_posts_in_topic)) {
                // İlk mesajı ayır (önceki gibi)
                foreach ($all_posts_in_topic as $key => $p) {
                    if (empty($p['parent_post_id']) && $first_post === null && $p['user_id'] == $topic['user_id']) {
                        $first_post = $p;
                        unset($all_posts_in_topic[$key]);
                        break;
                    }
                }
                if ($first_post === null && !empty($all_posts_in_topic)) {
                    foreach ($all_posts_in_topic as $key => $p) {
                        if (empty($p['parent_post_id'])) {
                            $first_post = $p;
                            unset($all_posts_in_topic[$key]);
                            break;
                        }
                    }
                }
                if ($first_post === null && !empty($all_posts_in_topic)) {
                     $first_post = array_shift($all_posts_in_topic);
                }
                $comments = array_values($all_posts_in_topic);
            }
        }

    } else {
        $_SESSION['error_message'] = "Tartışma konusu bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/discussions.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Tartışma detayı çekme hatası (Konu ID: $topic_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Tartışma detayı yüklenirken bir sorun oluştu.";
}

// Buton görünürlükleri için yetkiler (önceki gibi)
$can_lock_topic = $current_user_id && $topic && has_permission($pdo, 'discussion.topic.lock', $current_user_id);
$can_pin_topic = $current_user_id && $topic && has_permission($pdo, 'discussion.topic.pin', $current_user_id);
$can_delete_topic = $current_user_id && $topic && (has_permission($pdo, 'discussion.topic.delete_all', $current_user_id) || (has_permission($pdo, 'discussion.topic.delete_own', $current_user_id) && $topic['user_id'] == $current_user_id));
$can_reply_topic = $current_user_is_approved && $topic && ($topic['is_locked'] ?? 1) == 0 && has_permission($pdo, 'discussion.post.create', $current_user_id);


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* discussion_detail.php için GÜNCELLENMİŞ Stiller */
.discussion-detail-page-container-v2 {
    width: 100%;
    max-width: 1100px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 102px);
}

.page-top-nav-controls-dd { 
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}
.back-to-discussions-link-dd {
    font-size: 0.95rem; color: var(--turquase); text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 12px; border-radius: 20px; border: 1px solid transparent;
    transition: all 0.2s ease;
}
.back-to-discussions-link-dd:hover {
    color: var(--light-turquase); text-decoration: none;
}
.back-to-discussions-link-dd i.fas { font-size: 0.9em; }

.discussion-main-title-dd {
    color: var(--gold);
    font-size: 2.3rem; 
    font-family: var(--font);
    margin: 0 0 20px 0;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
    line-height: 1.3;
    display: flex; 
    align-items: center;
    gap: 15px; 
}
.discussion-main-title-dd .topic-icons-bar-main { 
    font-size: 1rem; 
}
.topic-icons-bar-main .topic-pin-icon { color: var(--turquase); font-size:1.1em; }
.topic-icons-bar-main .topic-status-badge.locked-topic { font-size:0.8em; padding: 4px 8px; }


.topic-admin-actions-dd {
    background-color: var(--darker-gold-2);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    text-align: right;
    border: 1px solid var(--darker-gold-1);
}
.topic-admin-actions-dd form { display: inline-block; margin-left: 10px; }
.topic-admin-actions-dd .btn { font-weight: 500; font-size:0.85rem; padding: 7px 15px; border-radius:20px;}


.discussion-post-wrapper { 
    margin-bottom: 25px;
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-left-width: 4px; 
    border-radius: 10px;
    padding: 20px 25px;
    transition: box-shadow 0.2s ease;
}
.discussion-post-wrapper:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.discussion-post-wrapper.topic-starter-post-dd { 
    border-left-color: var(--gold);
}
.discussion-post-wrapper.comment-item-dd { 
    border-left-color: var(--grey);
}
.discussion-post-wrapper.comment-item-dd.is-reply { 
    margin-left: 35px; 
    border-left-color: var(--darker-gold-1); 
    border-top-left-radius: 0; border-bottom-left-radius: 0; 
}


.post-header-dd {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.author-avatar-post { 
    width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--darker-gold-1); flex-shrink:0;
}
.avatar-placeholder-post { 
    width: 45px; height: 45px; border-radius: 50%; background-color: var(--grey); color: var(--gold);
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold;
    border: 2px solid var(--darker-gold-1); line-height: 1; flex-shrink:0;
}
.author-details-post { display: flex; flex-direction: column; flex-grow:1; min-width:0;}
.author-name-post-link { 
    color: inherit !important; 
    font-weight: 600; font-size:1.1rem; text-decoration: none;
    word-break: break-word;
}
.author-name-post-link:hover { text-decoration: underline; }


.post-date-post { font-size: 0.8rem; color: var(--light-grey); margin-top: 2px; }

.post-content-dd { line-height: 1.7; font-size: 1rem; color: var(--lighter-grey); word-wrap: break-word; }
.post-content-dd p { margin-bottom: 1em; }
.post-content-dd p:last-child { margin-bottom: 0; }
.post-content-dd blockquote.quoted-reply { 
    border-left: 4px solid var(--gold); padding: 12px 18px; margin: 18px 0 18px 5px; 
    color: var(--light-grey); font-style: italic; background-color: var(--darker-gold-2);
    border-radius: 0 6px 6px 0;
}
.post-content-dd blockquote.quoted-reply .quoted-author { font-size: 0.88rem; color: var(--light-grey); margin-bottom: 6px; }
.post-content-dd blockquote.quoted-reply .quoted-author strong { color: var(--light-gold); }
.post-content-dd blockquote.quoted-reply .quoted-content { font-size: 0.92rem; color: var(--lighter-grey); font-style: normal; opacity: 0.9; line-height: 1.5; max-height:none; overflow:visible;}


.post-actions-bar-dd {
    margin-top: 18px;
    padding-top: 12px;
    border-top: 1px solid var(--darker-gold-2);
    text-align: right;
    display: flex;
    justify-content: flex-end; 
    align-items: center;
    gap: 15px; 
}
.reply-to-post-link-dd {
    color: var(--turquase); text-decoration: none; font-size: 0.9rem; font-weight: 600;
    display:inline-flex; align-items:center; gap:6px;
    padding: 6px 10px; border-radius:15px; border:1px solid transparent;
}
.reply-to-post-link-dd:hover {
    color: var(--light-turquase); text-decoration:none;
}
.reply-to-post-link-dd i.fas { font-size:0.9em;}

form.post-delete-action-dd button {
    background-color: transparent; border: none; color: var(--dark-red); cursor: pointer;
    padding: 0; font-size: 0.9rem; font-weight: 600; font-family: var(--font);
    display:inline-flex; align-items:center; gap:6px;
}
form.post-delete-action-dd button:hover { color: var(--red); text-decoration: underline; }
form.post-delete-action-dd button i.fas { font-size:0.9em;}


.comments-title-dd {
    font-size: 1.8rem; color: var(--gold); margin-top: 40px; margin-bottom: 25px;
    padding-bottom: 15px; border-bottom: 1px solid var(--darker-gold-1); font-family: var(--font);
}
.locked-message.info-message, .no-comments-message { 
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px;
    background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1);
    margin-top: 20px;
}


.new-comment-form-section-dd {
    margin-top: 30px;
    padding: 25px 30px;
    background-color: var(--darker-gold-2); 
    border-radius: 10px;
    border: 1px solid var(--darker-gold-1);
}
.new-comment-form-section-dd h4 {
    font-size: 1.5rem;
    color: var(--light-gold);
    margin-bottom: 20px;
    font-family: var(--font);
    display: flex;
    align-items: center;
}
#replyingToInfo { 
    font-size: 0.85em;
    color: var(--light-grey);
    margin-left: 10px;
    font-weight: normal;
}
#replyingToInfo a {
    color: var(--turquase);
    text-decoration: none;
}
#replyingToInfo a:hover {
    text-decoration: underline;
}
#cancelReply { 
    font-size: 0.75em; 
    margin-left: 10px; 
    color: var(--red);
    text-decoration: none;
    font-weight: 500; 
    opacity: 0.8; 
}
#cancelReply:hover {
    text-decoration: underline;
    opacity: 1;
}
#cancelReply i.fas {
    font-size: 0.9em; 
}

.quoted-message-preview { 
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-left: 4px solid var(--gold);
    padding: 12px 15px;
    margin-bottom: 15px;
    border-radius: 0 6px 6px 0;
    font-size: 0.9em;
}
.quoted-message-preview #quotedAuthor {
    display: block;
    font-weight: 600;
    color: var(--light-gold);
    margin-bottom: 5px;
    font-size: 0.95em;
}
.quoted-message-preview #quotedText {
    color: var(--lighter-grey);
    font-style: italic;
    opacity: 0.9;
    max-height: 100px; 
    overflow-y: auto; 
    line-height: 1.4;
}

.new-comment-form-section-dd .form-group {
    margin-bottom: 20px;
}
.new-comment-form-section-dd textarea#comment_content_textarea {
    width: 100%;
    min-height: 120px; 
    padding: 12px 15px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
    line-height: 1.6;
    resize: vertical;
    box-sizing: border-box;
}
.new-comment-form-section-dd textarea#comment_content_textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.new-comment-form-section-dd .form-group button.btn-primary { 
    font-weight: bold;
    padding: 10px 25px;
    font-size: 1rem;
    border-radius: 20px;
}

.empty-message { 
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px;
    background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1);
    margin-top: 20px;
}

@media (max-width: 768px) {
    .discussion-detail-page-container-v2 { padding:15px; }
    .page-top-nav-controls-dd { flex-direction:column; align-items:flex-start; gap:15px; }
    .discussion-main-title-dd { font-size:1.9rem; gap:10px;}
    .discussion-main-title-dd .topic-icons-bar-main { font-size:0.9rem;}
    .discussion-post-wrapper { padding:15px; }
    .discussion-post-wrapper.comment-item-dd.is-reply { margin-left:20px; }
    .comments-title-dd { font-size:1.6rem; }
    .new-comment-form-section-dd { padding:20px; }
}


.access-denied-message-topic-detail { 
    text-align: center;
    font-size: 1.1rem;
    color: var(--light-grey);
    padding: 40px 20px;
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px dashed var(--darker-gold-1);
    margin: 40px auto;
    max-width: 700px;
}
.access-denied-message-topic-detail a {
    color: var(--turquase);
    font-weight: bold;
}
.access-denied-message-topic-detail a:hover {
    color: var(--light-turquase);
}
/* Kullanıcı adı renkleri (render_user_info_with_popover tarafından eklenen class'lar) */
.post-header-dd .user-info-trigger.username-role-admin .author-name-post-link { color: var(--gold) !important; }
.post-header-dd .user-info-trigger.username-role-scg_uye .author-name-post-link { color: #A52A2A !important; }
.post-header-dd .user-info-trigger.username-role-ilgarion_turanis .author-name-post-link { color: var(--turquase) !important; }
.post-header-dd .user-info-trigger.username-role-member .author-name-post-link { color: var(--white) !important; }
.post-header-dd .user-info-trigger.username-role-dis_uye .author-name-post-link { color: var(--light-grey) !important; }
</style>


<main class="main-content">
    <div class="container discussion-detail-page-container-v2">
        <div class="page-top-nav-controls-dd">
            <a href="discussions.php" class="back-to-discussions-link-dd"><i class="fas fa-arrow-left"></i> Tüm Tartışmalara Dön</a>
        </div>

        <?php if (!$can_view_this_topic && !empty($access_message_topic_detail)): ?>
            <div class="access-denied-message-topic-detail">
                <p><i class="fas fa-lock" style="color: var(--gold); margin-right: 10px; font-size: 1.5em;"></i></p>
                <p><?php echo $access_message_topic_detail; ?></p>
                <p style="margin-top: 20px;">
                    <a href="<?php echo get_auth_base_url(); ?>/discussions.php" class="btn btn-secondary btn-sm">Tüm Tartışmalara Dön</a>
                </p>
            </div>
        <?php elseif ($topic && $can_view_this_topic): ?>
            <h1 class="discussion-main-title-dd">
                <?php echo htmlspecialchars($topic['title']); ?>
                <span class="topic-icons-bar-main">
                    <?php if ($topic['is_pinned']): ?>
                        <i class="fas fa-thumbtack topic-pin-icon" title="Sabitlenmiş Konu"></i>
                    <?php endif; ?>
                    <?php if ($topic['is_locked']): ?>
                        <span class="topic-status-badge locked-topic" title="Bu konu yorumlara kapatılmıştır.">🔒 Kilitli</span>
                    <?php endif; ?>
                </span>
            </h1>

            <?php if ($can_lock_topic || $can_pin_topic || $can_delete_topic): ?>
                <div class="topic-admin-actions-dd">
                    <?php if ($can_lock_topic): ?>
                    <form action="/src/actions/handle_topic_actions.php" method="POST" class="inline-form">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="action" value="toggle_lock">
                        <button type="submit" class="btn btn-sm <?php echo $topic['is_locked'] ? 'btn-success' : 'btn-warning'; ?>">
                            <i class="fas <?php echo $topic['is_locked'] ? 'fa-unlock' : 'fa-lock'; ?>"></i> <?php echo $topic['is_locked'] ? 'Kilidi Aç' : 'Kilitle'; ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($can_pin_topic): ?>
                    <form action="/src/actions/handle_topic_actions.php" method="POST" class="inline-form">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="action" value="toggle_pin">
                        <button type="submit" class="btn btn-sm <?php echo $topic['is_pinned'] ? 'btn-secondary' : 'btn-primary'; ?>" title="<?php echo $topic['is_pinned'] ? 'Sabitlemeyi Kaldır' : 'Konuyu Sabitle'; ?>">
                            <i class="fas <?php echo $topic['is_pinned'] ? 'fa-unlink' : 'fa-thumbtack'; ?>"></i> <?php echo $topic['is_pinned'] ? 'Sabitliği Kaldır' : 'Sabitle'; ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($can_delete_topic): ?>
                    <form action="/src/actions/handle_topic_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu tartışma konusunu ve tüm yorumlarını KALICI OLARAK silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="action" value="delete_topic">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i> Konuyu Sil</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="discussion-post-wrapper topic-starter-post-dd" id="post-<?php echo htmlspecialchars($first_post['id'] ?? 'topic_start'); ?>">
                <?php
                    $starter_popover_data_detail = [
                        'id' => $topic['user_id'],
                        'username' => $topic['topic_starter_username'],
                        'avatar_path' => $topic['topic_starter_avatar'],
                        'ingame_name' => $topic['topic_starter_ingame'],
                        'discord_username' => $topic['topic_starter_discord'],
                        'user_event_count' => $topic['topic_starter_event_count'],
                        'user_gallery_count' => $topic['topic_starter_gallery_count'],
                        'user_roles_list' => $topic['topic_starter_roles_list']
                    ];
                ?>
                <div class="post-header-dd">
                    <?php echo render_user_info_with_popover($pdo, $starter_popover_data_detail, '', 'author-avatar-post', 'user-info-trigger'); ?>
                    <div class="author-details-post" style="margin-left:15px;">
                         <a href="view_profile.php?user_id=<?php echo $topic['user_id']; ?>" 
                           class="author-name-post-link">
                            <?php echo htmlspecialchars($topic['topic_starter_username']); ?>
                        </a>
                        <small class="post-date-post">Konuyu başlattı: <?php echo date('d M Y, H:i', strtotime($topic['created_at'])); ?></small>
                    </div>
                </div>

                <div class="post-content-dd" data-raw-content="<?php echo htmlspecialchars($first_post['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    if ($first_post && isset($first_post['content'])) {
                        if (function_exists('parse_discussion_quotes_from_safe_html')) {
                            echo parse_discussion_quotes_from_safe_html(nl2br(htmlspecialchars($first_post['content'], ENT_QUOTES, 'UTF-8')));
                        } else {
                            echo nl2br(htmlspecialchars($first_post['content'], ENT_QUOTES, 'UTF-8'));
                            error_log("parse_discussion_quotes_from_safe_html fonksiyonu bulunamadı (discussion_detail.php - first_post)");
                        }
                    } else {
                        echo "<p><em>Bu konunun ana içeriği bulunamadı.</em></p>";
                    }
                    ?>
                </div>
                 <div class="post-actions-bar-dd">
                    <?php if ($can_reply_topic && $first_post): ?>
                        <a href="#newCommentForm" class="reply-to-post-link-dd"
                           data-post-id="<?php echo $first_post['id']; ?>"
                           data-post-author="<?php echo htmlspecialchars($topic['topic_starter_username']); ?>">
                           <i class="fas fa-reply"></i> Bu Mesajı Alıntıla/Yanıtla
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="comments-title-dd">Yorumlar (<?php echo count($comments); ?>)</h3>

            <?php if ($topic['is_locked'] == 1 && empty($comments)): ?>
                <p class="locked-message info-message"><i class="fas fa-lock" style="margin-right:8px;"></i>Bu konu yorumlara kapatılmıştır ve henüz hiç yorum yapılmamıştır.</p>
            <?php elseif ($topic['is_locked'] == 1 && !empty($comments)): ?>
                 <p class="locked-message info-message"><i class="fas fa-lock" style="margin-right:8px;"></i>Bu konu yorumlara kapatılmıştır. Mevcut yorumlar aşağıdadır.</p>
            <?php endif; ?>

            <div class="discussion-posts-list">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php
                            $comment_author_popover_data = [
                                'id' => $comment['user_id'],
                                'username' => $comment['post_author_username'],
                                'avatar_path' => $comment['post_author_avatar'],
                                'ingame_name' => $comment['post_author_ingame'],
                                'discord_username' => $comment['post_author_discord'],
                                'user_event_count' => $comment['post_author_event_count'],
                                'user_gallery_count' => $comment['post_author_gallery_count'],
                                'user_roles_list' => $comment['post_author_roles_list']
                            ];
                            $can_delete_this_post = $current_user_id && (has_permission($pdo, 'discussion.post.delete_all', $current_user_id) || (has_permission($pdo, 'discussion.post.delete_own', $current_user_id) && $comment['user_id'] == $current_user_id));
                        ?>
                        <div class="discussion-post-wrapper comment-item-dd <?php echo !empty($comment['parent_post_id']) ? 'is-reply' : ''; ?>" id="post-<?php echo $comment['id']; ?>">
                            <div class="post-header-dd">
                                <?php echo render_user_info_with_popover($pdo, $comment_author_popover_data, '', 'author-avatar-post', 'user-info-trigger'); ?>
                                <div class="author-details-post" style="margin-left:15px;">
                                    <a href="view_profile.php?user_id=<?php echo $comment['user_id']; ?>" class="author-name-post-link">
                                        <?php echo htmlspecialchars($comment['post_author_username']); ?>
                                    </a>
                                    <small class="post-date-post"><?php echo date('d M Y, H:i', strtotime($comment['created_at'])); ?></small>
                                </div>
                            </div>
                            <div class="post-content-dd" data-raw-content="<?php echo htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php
                                if (function_exists('parse_discussion_quotes_from_safe_html')) {
                                    echo parse_discussion_quotes_from_safe_html(nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')));
                                } else {
                                    echo nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8'));
                                    error_log("parse_discussion_quotes_from_safe_html fonksiyonu bulunamadı (discussion_detail.php - comment)");
                                }
                                ?>
                            </div>
                            <div class="post-actions-bar-dd">
                                <?php if ($can_reply_topic): ?>
                                    <a href="#newCommentForm" class="reply-to-post-link-dd"
                                       data-post-id="<?php echo $comment['id']; ?>"
                                       data-post-author="<?php echo htmlspecialchars($comment['post_author_username']); ?>">
                                       <i class="fas fa-reply"></i> Yanıtla
                                    </a>
                                <?php endif; ?>
                                <?php if ($can_delete_this_post): ?>
                                    <form action="/src/actions/handle_delete_discussion_post.php" method="POST" class="post-delete-action-dd" onsubmit="return confirm('Bu yorumu silmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="post_id" value="<?php echo $comment['id']; ?>">
                                        <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                                        <button type="submit"><i class="fas fa-trash-alt"></i> Yorumu Sil</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($topic['is_locked'] == 0): ?>
                    <p class="no-comments-message">Henüz hiç yorum yapılmamış. İlk yorumu sen yap!</p>
                <?php endif; ?>
            </div>

            <?php if ($can_reply_topic): ?>
                <div class="new-comment-form-section-dd" id="newCommentForm">
                    <h4>
                        <i class="fas fa-comment-dots" style="margin-right:10px; color:var(--light-gold);"></i>Yorum Yap
                        <span id="replyingToInfo" style="display:none;"></span>
                        <a href="#" id="cancelReply" style="display:none;"><i class="fas fa-times-circle" style="margin-right:4px;"></i>Yanıtı İptal Et</a>
                    </h4>
                    <div id="quotedMessagePreview" class="quoted-message-preview" style="display:none;">
                        <p id="quotedAuthor"></p>
                        <blockquote id="quotedText"></blockquote>
                    </div>
                    <form action="/src/actions/handle_new_discussion_post.php" method="POST" id="commentFormActual">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="parent_post_id" id="parent_post_id_field" value="">
                        <div class="form-group">
                            <textarea name="comment_content" id="comment_content_textarea" rows="6" required placeholder="Yorumunuzu buraya yazın..."></textarea>
                        </div>
                        <div class="form-group" style="text-align:right;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane" style="margin-right:8px;"></i>Yorumu Gönder</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php elseif (!$topic && empty($access_message_topic_detail)): ?>
            <p class="access-denied-message-topic-detail">Tartışma konusu yüklenirken bir sorun oluştu veya konu bulunamadı.</p>
        <?php endif; ?>
    </div>
</main>

<script>
// discussion.js zaten footer'da yüklü olduğu için çalışacak.
// Popover.js de footer'da yüklü.
document.addEventListener('DOMContentLoaded', function() {
    // RGB Renkleri için CSS Değişkenlerini Tanımla
    const rootStyles = getComputedStyle(document.documentElement);
    const setRgbVar = (varName) => {
        const hexColor = rootStyles.getPropertyValue(`--${varName}`).trim();
        if (hexColor && hexColor.startsWith('#')) {
            let r, g, b;
            if (hexColor.length === 4) { // #RGB formatı
                r = parseInt(hexColor[1] + hexColor[1], 16);
                g = parseInt(hexColor[2] + hexColor[2], 16);
                b = parseInt(hexColor[3] + hexColor[3], 16);
            } else if (hexColor.length === 7) { // #RRGGBB formatı
                r = parseInt(hexColor.slice(1, 3), 16);
                g = parseInt(hexColor.slice(3, 5), 16);
                b = parseInt(hexColor.slice(5, 7), 16);
            } else { return; }
            document.documentElement.style.setProperty(`--${varName}-rgb`, `${r}, ${g}, ${b}`);
        }
    };
    ['darker-gold-2', 'darker-gold-1', 'grey', 'gold', 'charcoal', 'turquase', 'red', 'dark-red', 'light-turquase'].forEach(setRgbVar);

    // Sayfa yüklendiğinde belirli bir posta scroll etme (eğer URL'de #post-ID varsa)
    if (window.location.hash && window.location.hash.startsWith('#post-')) {
        const targetPostId = window.location.hash;
        const targetPostElement = document.querySelector(targetPostId);
        if (targetPostElement) {
            setTimeout(() => {
                targetPostElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetPostElement.style.transition = 'background-color 0.5s ease-in-out';
                targetPostElement.style.backgroundColor = 'var(--darker-gold-1)'; 
                setTimeout(() => {
                    targetPostElement.style.backgroundColor = ''; 
                }, 2500); 
            }, 200);
        }
    } else if (window.location.hash && window.location.hash === '#newCommentForm') {
        const commentForm = document.getElementById('newCommentForm');
        if (commentForm) {
            setTimeout(() => {
                commentForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const textarea = document.getElementById('comment_content_textarea');
                if (textarea) textarea.focus();
            }, 200);
        }
    }
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
