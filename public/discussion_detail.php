<?php
// public/discussion_detail.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php'; // İçerik erişim kontrolleri
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
$current_user_is_admin = $current_user_is_logged_in ? is_admin($pdo) : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

$can_view_this_topic = false;
$access_message_topic_detail = "";

// Base URL fonksiyonu (navbar ile uyumlu)
$baseUrl = get_auth_base_url();

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
                    (SELECT GROUP_CONCAT(r.name ORDER BY r.priority ASC SEPARATOR ',')
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

        // Gelişmiş yetkilendirme kontrolü - can_user_access_content benzeri mantık
        $content_data = [
            'is_public_no_auth' => $topic['is_public_no_auth'],
            'is_members_only' => $topic['is_members_only'],
            'user_id' => $topic['user_id']
        ];

        // Rol bazlı görünürlük kontrolü
        $topic_role_ids = [];
        if ($topic['is_public_no_auth'] == 0 && $topic['is_members_only'] == 0) {
            $stmt_topic_roles = $pdo->prepare("SELECT role_id FROM discussion_topic_visibility_roles WHERE topic_id = ?");
            $stmt_topic_roles->execute([$topic_id]);
            $topic_role_ids = $stmt_topic_roles->fetchAll(PDO::FETCH_COLUMN);
        }

        $can_view_this_topic = can_user_access_content($pdo, $content_data, $topic_role_ids, $current_user_id);

        // Ek yetki kontrolleri
        if (!$can_view_this_topic) {
            // Admin her zaman görüntüleyebilir
            if ($current_user_is_admin || has_permission($pdo, 'discussion.topic.edit_all')) {
                $can_view_this_topic = true;
            }
            // Konu sahibi her zaman kendi konusunu görebilir
            elseif ($current_user_id && $current_user_id == $topic['user_id']) {
                $can_view_this_topic = true;
            }
        }

        if (!$can_view_this_topic) {
            if (!$current_user_is_logged_in) {
                $access_message_topic_detail = "Bu konuyu görmek için lütfen <a href='" . htmlspecialchars($baseUrl) . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . htmlspecialchars($baseUrl) . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
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

            // Yorumları çek - rol renklerini destekleyen sürüm
            $sql_posts = "SELECT
                            dp.id, dp.user_id, dp.parent_post_id, dp.content, dp.created_at,
                            u.username AS post_author_username,
                            u.avatar_path AS post_author_avatar,
                            u.ingame_name AS post_author_ingame,
                            u.discord_username AS post_author_discord,
                            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS post_author_event_count,
                            (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS post_author_gallery_count,
                            (SELECT GROUP_CONCAT(r_author.name ORDER BY r_author.priority ASC SEPARATOR ',')
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
                // İlk mesajı ayır
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
        header('Location: ' . htmlspecialchars($baseUrl) . '/discussions.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Tartışma detayı çekme hatası (Konu ID: $topic_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Tartışma detayı yüklenirken bir sorun oluştu.";
}

// Yeni yetki sistemi ile buton görünürlükleri
$can_lock_topic = $current_user_id && $topic && has_permission($pdo, 'discussion.topic.lock');
$can_pin_topic = $current_user_id && $topic && has_permission($pdo, 'discussion.topic.pin');
$can_delete_topic = $current_user_id && $topic && can_user_delete_content($pdo, 'discussion_topic', $topic['user_id']);
$can_reply_topic = $current_user_is_approved && $topic && ($topic['is_locked'] ?? 1) == 0 && has_permission($pdo, 'discussion.post.create');

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Discussion Detail Page Styles - Fully Redesigned */
.discussion-detail-container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 100px);
}

/* Header Section */
.discussion-header {
    margin-bottom: 2rem;
}

.breadcrumb-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    font-size: 0.9rem;
    color: var(--light-grey);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--turquase);
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    font-weight: 500;
}

.back-link:hover {
    background-color: rgba(61, 166, 162, 0.1);
    border-color: var(--turquase);
    transform: translateX(-2px);
}

.topic-title-section {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 2rem;
    gap: 2rem;
}

.topic-title {
    color: var(--gold);
    font-size: 2.5rem;
    font-weight: 600;
    margin: 0;
    line-height: 1.2;
    flex: 1;
    word-break: break-word;
}

.topic-badges {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge.pinned {
    background: linear-gradient(135deg, var(--turquase), var(--light-turquase));
    color: var(--charcoal);
}

.badge.locked {
    background: linear-gradient(135deg, var(--red), #e74c3c);
    color: white;
}

/* Admin Actions */
.admin-actions {
    background: linear-gradient(135deg, var(--darker-gold-2), var(--charcoal));
    padding: 1.5rem 2rem;
    border-radius: 12px;
    border: 1px solid var(--darker-gold-1);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 1rem;
    flex-wrap: wrap;
}

.admin-actions .btn {
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.admin-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Post Containers */
.post-container {
    background-color: var(--charcoal);
    border-radius: 12px;
    border: 1px solid var(--darker-gold-1);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.post-container:hover {
    border-color: var(--gold);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.post-container.starter-post {
    border-left: 4px solid var(--gold);
    background: linear-gradient(135deg, var(--charcoal), rgba(189, 145, 42, 0.03));
}

.post-container.reply-post {
    border-left: 4px solid var(--turquase);
}

.post-container.nested-reply {
    margin-left: 2rem;
    border-left: 4px solid var(--light-grey);
}

/* Post Header */
.post-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--darker-gold-1);
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.user-avatar:hover {
    transform: scale(1.05);
}

.avatar-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--grey), var(--darker-gold-2));
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    border: 2px solid var(--darker-gold-1);
    flex-shrink: 0;
}

.user-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.username-link {
    color: var(--lighter-grey);
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    word-break: break-word;
    transition: color 0.2s ease;
}

.username-link:hover {
    text-decoration: underline;
}

.post-meta {
    font-size: 0.85rem;
    color: var(--light-grey);
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.post-badge {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--charcoal);
    padding: 0.2rem 0.6rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
}

/* Post Content */
.post-content {
    padding: 1.5rem 2rem;
    line-height: 1.7;
    font-size: 1rem;
    color: var(--lighter-grey);
    word-wrap: break-word;
}

.post-content p {
    margin-bottom: 1rem;
}

.post-content p:last-child {
    margin-bottom: 0;
}

.post-content blockquote.quoted-reply {
    border-left: 4px solid var(--gold);
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    background: linear-gradient(135deg, var(--darker-gold-2), rgba(189, 145, 42, 0.05));
    border-radius: 0 8px 8px 0;
    position: relative;
}

.post-content blockquote.quoted-reply::before {
    content: '"';
    position: absolute;
    top: -10px;
    left: 15px;
    font-size: 3rem;
    color: var(--gold);
    opacity: 0.3;
}

.quoted-author {
    font-size: 0.9rem;
    color: var(--light-gold);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.quoted-content {
    font-size: 0.95rem;
    color: var(--lighter-grey);
    font-style: italic;
    opacity: 0.9;
    line-height: 1.5;
}

/* Post Actions */
.post-actions {
    padding: 1rem 2rem 1.5rem;
    border-top: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: transparent;
    border: 1px solid var(--darker-gold-1);
    color: var(--turquase);
    text-decoration: none;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.action-btn:hover {
    background-color: var(--turquase);
    color: var(--charcoal);
    transform: translateY(-1px);
}

.action-btn.delete {
    color: var(--red);
    border-color: var(--red);
}

.action-btn.delete:hover {
    background-color: var(--red);
    color: white;
}

/* Comments Section */
.comments-section {
    margin-top: 3rem;
}

.comments-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-1);
}

.comments-title {
    color: var(--gold);
    font-size: 1.8rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.comments-count {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--charcoal);
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: bold;
}

/* Reply Form */
.reply-form-container {
    background: linear-gradient(135deg, var(--darker-gold-2), var(--charcoal));
    border-radius: 12px;
    border: 1px solid var(--darker-gold-1);
    padding: 2rem;
    margin-top: 2rem;
}

.reply-form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.reply-form-title {
    color: var(--light-gold);
    font-size: 1.4rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.reply-info {
    font-size: 0.9rem;
    color: var(--light-grey);
    font-weight: normal;
}

.reply-info a {
    color: var(--turquase);
    text-decoration: none;
}

.reply-info a:hover {
    text-decoration: underline;
}

.cancel-reply {
    color: var(--red);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    opacity: 0.8;
    transition: opacity 0.2s ease;
}

.cancel-reply:hover {
    opacity: 1;
    text-decoration: underline;
}

.quoted-preview {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-left: 4px solid var(--gold);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 0 8px 8px 0;
    font-size: 0.9rem;
}

.quoted-preview-author {
    font-weight: 600;
    color: var(--light-gold);
    margin-bottom: 0.5rem;
}

.quoted-preview-text {
    color: var(--lighter-grey);
    font-style: italic;
    opacity: 0.9;
    max-height: 100px;
    overflow-y: auto;
    line-height: 1.4;
}

.form-group {
    margin-bottom: 1.5rem;
}

.reply-textarea {
    width: 100%;
    min-height: 140px;
    padding: 1rem 1.5rem;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 8px;
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
    line-height: 1.6;
    resize: vertical;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.reply-textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.reply-textarea::placeholder {
    color: var(--light-grey);
    opacity: 0.7;
}

/* Access Denied Message */
.access-denied {
    text-align: center;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, var(--charcoal), var(--darker-gold-2));
    border-radius: 12px;
    border: 1px dashed var(--darker-gold-1);
    margin: 2rem auto;
    max-width: 700px;
}

.access-denied-icon {
    font-size: 4rem;
    color: var(--gold);
    margin-bottom: 1.5rem;
    opacity: 0.7;
}

.access-denied p {
    font-size: 1.1rem;
    color: var(--light-grey);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.access-denied a {
    color: var(--turquase);
    font-weight: bold;
    text-decoration: none;
}

.access-denied a:hover {
    color: var(--light-turquase);
    text-decoration: underline;
}

/* Info Messages */
.info-message {
    text-align: center;
    padding: 2rem;
    background: linear-gradient(135deg, rgba(42, 189, 168, 0.1), rgba(42, 189, 168, 0.05));
    border: 1px solid rgba(42, 189, 168, 0.3);
    border-radius: 8px;
    color: var(--turquase);
    font-size: 1rem;
    margin: 2rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.empty-message {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--light-grey);
    font-size: 1.1rem;
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px dashed var(--darker-gold-1);
    margin: 2rem 0;
}

/* Role-based Username Colors */
.user-info-trigger.role-admin .username-link {
    color: #0091ff !important;
}

.user-info-trigger.role-ilgarion_turanis .username-link {
    color: #3da6a2 !important;
}

.user-info-trigger.role-scg_uye .username-link {
    color: #a52a2a !important;
}

.user-info-trigger.role-member .username-link {
    color: #0000ff !important;
}

.user-info-trigger.role-dis_uye .username-link {
    color: #808080 !important;
}

/* Animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.post-container {
    animation: slideInUp 0.3s ease-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .discussion-detail-container {
        padding: 1.5rem 1rem;
    }
    
    .topic-title-section {
        flex-direction: column;
        gap: 1rem;
    }
    
    .topic-title {
        font-size: 2rem;
    }
    
    .topic-badges {
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .admin-actions {
        justify-content: center;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .admin-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .post-container.nested-reply {
        margin-left: 1rem;
    }
    
    .post-header {
        padding: 1rem 1.5rem 0.5rem;
    }
    
    .post-content {
        padding: 1rem 1.5rem;
    }
    
    .post-actions {
        padding: 0.5rem 1.5rem 1rem;
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-btn {
        justify-content: center;
    }
    
    .reply-form-container {
        padding: 1.5rem;
    }
    
    .comments-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .topic-title {
        font-size: 1.75rem;
    }
    
    .post-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .user-avatar,
    .avatar-placeholder {
        width: 40px;
        height: 40px;
    }
    
    .reply-form-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .badge {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
    }
}

/* Loading Animation */
.loading-shimmer {
    background: linear-gradient(90deg, var(--charcoal) 25%, var(--darker-gold-2) 50%, var(--charcoal) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

/* Enhanced Interactions */
.post-container:target {
    border-color: var(--gold);
    box-shadow: 0 0 20px rgba(189, 145, 42, 0.3);
    background: linear-gradient(135deg, var(--charcoal), rgba(189, 145, 42, 0.05));
}

/* Scroll Padding for Smooth Navigation */
html {
    scroll-padding-top: calc(var(--navbar-height, 70px) + 2rem);
}

/* Custom Scrollbar */
.post-content::-webkit-scrollbar,
.quoted-preview-text::-webkit-scrollbar,
.reply-textarea::-webkit-scrollbar {
    width: 6px;
}

.post-content::-webkit-scrollbar-track,
.quoted-preview-text::-webkit-scrollbar-track,
.reply-textarea::-webkit-scrollbar-track {
    background: var(--darker-gold-2);
    border-radius: 3px;
}

.post-content::-webkit-scrollbar-thumb,
.quoted-preview-text::-webkit-scrollbar-thumb,
.reply-textarea::-webkit-scrollbar-thumb {
    background: var(--gold);
    border-radius: 3px;
}

.post-content::-webkit-scrollbar-thumb:hover,
.quoted-preview-text::-webkit-scrollbar-thumb:hover,
.reply-textarea::-webkit-scrollbar-thumb:hover {
    background: var(--light-gold);
}
</style>

<main class="main-content">
    <div class="container discussion-detail-container">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-nav">
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/discussions.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Tüm Tartışmalara Dön
            </a>
        </div>

        <?php if (!$can_view_this_topic && !empty($access_message_topic_detail)): ?>
            <!-- Access Denied Message -->
            <div class="access-denied">
                <div class="access-denied-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <p><?php echo $access_message_topic_detail; ?></p>
                <p style="margin-top: 2rem;">
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/discussions.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Tüm Tartışmalara Dön
                    </a>
                </p>
            </div>
        <?php elseif ($topic && $can_view_this_topic): ?>
            <!-- Discussion Header -->
            <div class="discussion-header">
                <div class="topic-title-section">
                    <h1 class="topic-title"><?php echo htmlspecialchars($topic['title']); ?></h1>
                    <div class="topic-badges">
                        <?php if ($topic['is_pinned']): ?>
                            <span class="badge pinned">
                                <i class="fas fa-thumbtack"></i>
                                Sabitlenmiş
                            </span>
                        <?php endif; ?>
                        <?php if ($topic['is_locked']): ?>
                            <span class="badge locked">
                                <i class="fas fa-lock"></i>
                                Kilitli
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Admin Actions -->
            <?php if ($can_lock_topic || $can_pin_topic || $can_delete_topic): ?>
                <div class="admin-actions">
                    <?php if ($can_lock_topic): ?>
                    <form action="<?php echo htmlspecialchars($baseUrl); ?>/src/actions/handle_topic_actions.php" method="POST" style="display: inline;">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="action" value="toggle_lock">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" class="btn <?php echo $topic['is_locked'] ? 'btn-success' : 'btn-warning'; ?>">
                            <i class="fas <?php echo $topic['is_locked'] ? 'fa-unlock' : 'fa-lock'; ?>"></i>
                            <?php echo $topic['is_locked'] ? 'Kilidi Aç' : 'Kilitle'; ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($can_pin_topic): ?>
                    <form action="<?php echo htmlspecialchars($baseUrl); ?>/src/actions/handle_topic_actions.php" method="POST" style="display: inline;">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="action" value="toggle_pin">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" class="btn <?php echo $topic['is_pinned'] ? 'btn-secondary' : 'btn-primary'; ?>">
                            <i class="fas <?php echo $topic['is_pinned'] ? 'fa-unlink' : 'fa-thumbtack'; ?>"></i>
                            <?php echo $topic['is_pinned'] ? 'Sabitliği Kaldır' : 'Sabitle'; ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($can_delete_topic): ?>
                    <form action="<?php echo htmlspecialchars($baseUrl); ?>/src/actions/handle_topic_actions.php" method="POST" style="display: inline;" 
                          onsubmit="return confirm('Bu tartışma konusunu ve tüm yorumlarını KALICI OLARAK silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="action" value="delete_topic">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i>
                            Konuyu Sil
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Original Post -->
            <div class="post-container starter-post" id="post-<?php echo htmlspecialchars($first_post['id'] ?? 'topic_start'); ?>">
                <?php
                // Rol rengini belirle
                $starter_primary_role = '';
                if (!empty($topic['topic_starter_roles_list'])) {
                    $starter_roles = explode(',', $topic['topic_starter_roles_list']);
                    $starter_primary_role = trim($starter_roles[0]); // İlk rol en yüksek öncelikli
                }

                $starter_popover_data = [
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
                
                <div class="post-header">
                    <?php echo render_user_info_with_popover(
                        $pdo, 
                        $starter_popover_data, 
                        '', 
                        'user-avatar', 
                        'user-info-trigger' . ($starter_primary_role ? ' role-' . $starter_primary_role : '')
                    ); ?>
                    <div class="user-info">
                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/view_profile.php?user_id=<?php echo $topic['user_id']; ?>" 
                           class="username-link">
                            <?php echo htmlspecialchars($topic['topic_starter_username']); ?>
                        </a>
                        <div class="post-meta">
                            <span class="post-badge">Konu Başlatıcısı</span>
                            <span><?php echo date('d M Y, H:i', strtotime($topic['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="post-content" data-raw-content="<?php echo htmlspecialchars($first_post['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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

                <?php if ($can_reply_topic && $first_post): ?>
                <div class="post-actions">
                    <a href="#reply-form" class="action-btn reply-btn"
                       data-post-id="<?php echo $first_post['id']; ?>"
                       data-post-author="<?php echo htmlspecialchars($topic['topic_starter_username']); ?>">
                        <i class="fas fa-reply"></i>
                        Yanıtla
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Comments Section -->
            <div class="comments-section">
                <div class="comments-header">
                    <h2 class="comments-title">
                        <i class="fas fa-comments"></i>
                        Yorumlar
                        <span class="comments-count"><?php echo count($comments); ?></span>
                    </h2>
                </div>

                <!-- Lock Status Message -->
                <?php if ($topic['is_locked'] == 1): ?>
                    <div class="info-message">
                        <i class="fas fa-lock"></i>
                        Bu konu yorumlara kapatılmıştır.
                        <?php if (empty($comments)): ?>
                            Henüz hiç yorum yapılmamıştır.
                        <?php else: ?>
                            Mevcut yorumlar aşağıdadır.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div class="comments-list">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <?php
                            // Yorumcu rol rengini belirle
                            $comment_primary_role = '';
                            if (!empty($comment['post_author_roles_list'])) {
                                $comment_roles = explode(',', $comment['post_author_roles_list']);
                                $comment_primary_role = trim($comment_roles[0]);
                            }

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
                            
                            $can_delete_this_post = $current_user_id && can_user_delete_content($pdo, 'discussion_post', $comment['user_id']);
                            ?>
                            
                            <div class="post-container <?php echo !empty($comment['parent_post_id']) ? 'nested-reply' : 'reply-post'; ?>" 
                                 id="post-<?php echo $comment['id']; ?>">
                                
                                <div class="post-header">
                                    <?php echo render_user_info_with_popover(
                                        $pdo, 
                                        $comment_author_popover_data, 
                                        '', 
                                        'user-avatar', 
                                        'user-info-trigger' . ($comment_primary_role ? ' role-' . $comment_primary_role : '')
                                    ); ?>
                                    <div class="user-info">
                                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/view_profile.php?user_id=<?php echo $comment['user_id']; ?>" 
                                           class="username-link">
                                            <?php echo htmlspecialchars($comment['post_author_username']); ?>
                                        </a>
                                        <div class="post-meta">
                                            <span><?php echo date('d M Y, H:i', strtotime($comment['created_at'])); ?></span>
                                            <?php if (!empty($comment['parent_post_id'])): ?>
                                                <span class="post-badge">Yanıt</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="post-content" data-raw-content="<?php echo htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php
                                    if (function_exists('parse_discussion_quotes_from_safe_html')) {
                                        echo parse_discussion_quotes_from_safe_html(nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')));
                                    } else {
                                        echo nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8'));
                                        error_log("parse_discussion_quotes_from_safe_html fonksiyonu bulunamadı (discussion_detail.php - comment)");
                                    }
                                    ?>
                                </div>

                                <div class="post-actions">
                                    <?php if ($can_reply_topic): ?>
                                        <a href="#reply-form" class="action-btn reply-btn"
                                           data-post-id="<?php echo $comment['id']; ?>"
                                           data-post-author="<?php echo htmlspecialchars($comment['post_author_username']); ?>">
                                            <i class="fas fa-reply"></i>
                                            Yanıtla
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete_this_post): ?>
                                        <form action="<?php echo htmlspecialchars($baseUrl); ?>/src/actions/handle_delete_discussion_post.php" 
                                              method="POST" style="display: inline;"
                                              onsubmit="return confirm('Bu yorumu silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="post_id" value="<?php echo $comment['id']; ?>">
                                            <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <button type="submit" class="action-btn delete">
                                                <i class="fas fa-trash-alt"></i>
                                                Sil
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($topic['is_locked'] == 0): ?>
                        <div class="empty-message">
                            <i class="fas fa-comment-dots" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i><br>
                            Henüz hiç yorum yapılmamış. İlk yorumu sen yap!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reply Form -->
            <?php if ($can_reply_topic): ?>
                <div class="reply-form-container" id="reply-form">
                    <div class="reply-form-header">
                        <h3 class="reply-form-title">
                            <i class="fas fa-comment-dots"></i>
                            Yorum Yap
                            <span class="reply-info" id="reply-info" style="display: none;"></span>
                        </h3>
                        <a href="#" class="cancel-reply" id="cancel-reply" style="display: none;">
                            <i class="fas fa-times"></i>
                            Yanıtı İptal Et
                        </a>
                    </div>
                    
                    <div class="quoted-preview" id="quoted-preview" style="display: none;">
                        <div class="quoted-preview-author" id="quoted-author"></div>
                        <div class="quoted-preview-text" id="quoted-text"></div>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($baseUrl); ?>/src/actions/handle_new_discussion_post.php" 
                          method="POST" id="reply-form-element">
                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                        <input type="hidden" name="parent_post_id" id="parent-post-id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <textarea name="comment_content" 
                                     id="comment-textarea" 
                                     class="reply-textarea" 
                                     rows="6" 
                                     required 
                                     placeholder="Yorumunuzu buraya yazın..."></textarea>
                        </div>
                        
                        <div class="form-group" style="text-align: right;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Yorumu Gönder
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
        <?php elseif (!$topic && empty($access_message_topic_detail)): ?>
            <div class="empty-message">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i><br>
                Tartışma konusu yüklenirken bir sorun oluştu veya konu bulunamadı.
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reply functionality
    const replyButtons = document.querySelectorAll('.reply-btn');
    const replyInfo = document.getElementById('reply-info');
    const cancelReply = document.getElementById('cancel-reply');
    const quotedPreview = document.getElementById('quoted-preview');
    const quotedAuthor = document.getElementById('quoted-author');
    const quotedText = document.getElementById('quoted-text');
    const parentPostId = document.getElementById('parent-post-id');
    const commentTextarea = document.getElementById('comment-textarea');

    replyButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const postId = this.dataset.postId;
            const postAuthor = this.dataset.postAuthor;
            const postElement = document.getElementById(`post-${postId}`);
            
            if (postElement) {
                const postContent = postElement.querySelector('.post-content');
                const rawContent = postContent.dataset.rawContent || postContent.textContent;
                
                // Set reply info
                replyInfo.innerHTML = `<a href="#post-${postId}">@${postAuthor}</a> kullanıcısına yanıt veriyorsunuz`;
                replyInfo.style.display = 'inline';
                cancelReply.style.display = 'inline';
                
                // Set quoted content
                quotedAuthor.textContent = postAuthor;
                quotedText.textContent = rawContent.trim().substring(0, 200) + (rawContent.length > 200 ? '...' : '');
                quotedPreview.style.display = 'block';
                
                // Set parent post ID
                parentPostId.value = postId;
                
                // Focus textarea and add quote
                const quoteText = `[ALINTI="${postAuthor}"]\n${rawContent.trim().substring(0, 500)}\n[/ALINTI]\n\n`;
                commentTextarea.value = quoteText;
                commentTextarea.focus();
                commentTextarea.setSelectionRange(commentTextarea.value.length, commentTextarea.value.length);
                
                // Scroll to form
                document.getElementById('reply-form').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        });
    });

    // Cancel reply
    if (cancelReply) {
        cancelReply.addEventListener('click', function(e) {
            e.preventDefault();
            
            replyInfo.style.display = 'none';
            cancelReply.style.display = 'none';
            quotedPreview.style.display = 'none';
            parentPostId.value = '';
            commentTextarea.value = '';
        });
    }

    // Smooth scroll to specific post
    if (window.location.hash && window.location.hash.startsWith('#post-')) {
        const targetPostId = window.location.hash;
        const targetPostElement = document.querySelector(targetPostId);
        if (targetPostElement) {
            setTimeout(() => {
                targetPostElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                
                // Highlight effect
                targetPostElement.style.transition = 'all 0.5s ease';
                targetPostElement.style.transform = 'scale(1.02)';
                targetPostElement.style.boxShadow = '0 0 30px rgba(189, 145, 42, 0.4)';
                
                setTimeout(() => {
                    targetPostElement.style.transform = '';
                    targetPostElement.style.boxShadow = '';
                }, 2000);
            }, 300);
        }
    }

    // Auto-scroll to reply form if coming from discussions page with #newCommentForm
    if (window.location.hash === '#newCommentForm') {
        const replyForm = document.getElementById('reply-form');
        if (replyForm) {
            setTimeout(() => {
                replyForm.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                if (commentTextarea) commentTextarea.focus();
            }, 300);
        }
    }

    // Enhanced post animations
    const posts = document.querySelectorAll('.post-container');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const postObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    posts.forEach(post => {
        post.style.opacity = '0';
        post.style.transform = 'translateY(20px)';
        post.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        postObserver.observe(post);
    });

    // Form submission enhancement
    const replyForm = document.getElementById('reply-form-element');
    if (replyForm) {
        replyForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            }
        });
    }

    // Character counter for textarea
    if (commentTextarea) {
        const maxLength = 5000;
        const counter = document.createElement('div');
        counter.style.cssText = `
            text-align: right;
            font-size: 0.8rem;
            color: var(--light-grey);
            margin-top: 0.5rem;
        `;
        
        function updateCounter() {
            const remaining = maxLength - commentTextarea.value.length;
            counter.textContent = `${commentTextarea.value.length}/${maxLength} karakter`;
            counter.style.color = remaining < 100 ? 'var(--red)' : 'var(--light-grey)';
        }
        
        commentTextarea.parentNode.appendChild(counter);
        commentTextarea.addEventListener('input', updateCounter);
        updateCounter();
    }

    // Enhanced role-based username styling
    document.querySelectorAll('.user-info-trigger').forEach(trigger => {
        if (trigger.classList.contains('role-admin') || 
            trigger.classList.contains('role-ilgarion_turanis') ||
            trigger.classList.contains('role-scg_uye')) {
            
            trigger.addEventListener('mouseenter', function() {
                const username = this.querySelector('.username-link');
                if (username) {
                    username.style.textShadow = '0 0 8px currentColor';
                    username.style.transform = 'scale(1.05)';
                }
            });
            
            trigger.addEventListener('mouseleave', function() {
                const username = this.querySelector('.username-link');
                if (username) {
                    username.style.textShadow = 'none';
                    username.style.transform = 'scale(1)';
                }
            });
        }
    });

    // Progressive loading for images
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit reply
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && document.activeElement === commentTextarea) {
            e.preventDefault();
            replyForm.submit();
        }
        
        // Escape to cancel reply
        if (e.key === 'Escape' && cancelReply.style.display !== 'none') {
            cancelReply.click();
        }
    });

    // Auto-save draft (optional)
    let draftKey = `discussion_draft_${<?php echo $topic_id; ?>}`;
    
    if (commentTextarea) {
        // Load draft
        const saved = localStorage.getItem(draftKey);
        if (saved && !commentTextarea.value) {
            commentTextarea.value = saved;
        }
        
        // Save draft
        let saveTimeout;
        commentTextarea.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                if (this.value.trim()) {
                    localStorage.setItem(draftKey, this.value);
                } else {
                    localStorage.removeItem(draftKey);
                }
            }, 1000);
        });
        
        // Clear draft on submit
        replyForm.addEventListener('submit', function() {
            localStorage.removeItem(draftKey);
        });
    }

    // Performance monitoring
    if ('PerformanceObserver' in window) {
        const perfObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (entry.entryType === 'navigation') {
                    console.log('Discussion detail load time:', entry.loadEventEnd - entry.loadEventStart, 'ms');
                }
            }
        });
        perfObserver.observe({ entryTypes: ['navigation'] });
    }
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>