<?php
// public/discussions.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php'; // İçerik erişim kontrolleri
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$page_title = "Tartışmalar";
$topics_with_details = [];

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_admin($pdo) : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

// Yeni yetki sistemi ile kontroller
$can_create_topic = $current_user_is_approved && has_permission($pdo, 'discussion.topic.create');
$can_view_public = has_permission($pdo, 'discussion.view_public') || !$current_user_is_logged_in; // Misafir kullanıcılar da herkese açık içeriği görebilir
$can_view_members_only = $current_user_is_approved && has_permission($pdo, 'discussion.view_approved');
$can_view_all = $current_user_is_admin || has_permission($pdo, 'discussion.topic.edit_all');

// Base URL fonksiyonu (navbar ile uyumlu)
$baseUrl = get_auth_base_url();

try {
    $sql_base = "SELECT
                dt.id, dt.title, dt.created_at AS topic_created_at, dt.last_reply_at,
                dt.reply_count, dt.is_locked, dt.is_pinned,
                dt.is_public_no_auth, dt.is_members_only,
                dt.user_id AS topic_starter_id,
                u_starter.username AS topic_starter_username,
                u_starter.avatar_path AS topic_starter_avatar,
                u_starter.ingame_name AS topic_starter_ingame,
                u_starter.discord_username AS topic_starter_discord,
                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u_starter.id) AS topic_starter_event_count,
                (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u_starter.id) AS topic_starter_gallery_count,
                (SELECT GROUP_CONCAT(r_starter.name ORDER BY r_starter.priority ASC SEPARATOR ',')
                 FROM user_roles ur_starter
                 JOIN roles r_starter ON ur_starter.role_id = r_starter.id
                 WHERE ur_starter.user_id = u_starter.id) AS topic_starter_roles_list,

                dp_last.user_id AS last_replier_user_id,
                u_replier.username AS last_replier_username,
                u_replier.avatar_path AS last_replier_avatar,
                u_replier.ingame_name AS last_replier_ingame,
                u_replier.discord_username AS last_replier_discord,
                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u_replier.id) AS last_replier_event_count,
                (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u_replier.id) AS last_replier_gallery_count,
                (SELECT GROUP_CONCAT(r_replier.name ORDER BY r_replier.priority ASC SEPARATOR ',')
                 FROM user_roles ur_replier
                 JOIN roles r_replier ON ur_replier.role_id = r_replier.id
                 WHERE ur_replier.user_id = u_replier.id) AS last_replier_roles_list,
                utv.last_viewed_at";

    $sql_from_join = " FROM discussion_topics dt
                       JOIN users u_starter ON dt.user_id = u_starter.id
                       LEFT JOIN discussion_posts dp_last ON dp_last.id = (
                           SELECT id FROM discussion_posts
                           WHERE topic_id = dt.id
                           ORDER BY created_at DESC LIMIT 1
                       )
                       LEFT JOIN users u_replier ON dp_last.user_id = u_replier.id
                       LEFT JOIN user_topic_views utv ON dt.id = utv.topic_id AND utv.user_id = :current_user_id_for_view";

    $where_clauses = [];
    $params = [':current_user_id_for_view' => $current_user_id ?? null];

    // Gelişmiş görünürlük kontrolleri - can_user_access_content benzeri mantık
    if ($can_view_all) {
        // Admin veya 'view_all' yetkisine sahip olanlar için ek WHERE koşulu gerekmez
    } else {
        $visibility_or_conditions = [];

        // 1. Herkese açık konular
        if ($can_view_public) {
            $visibility_or_conditions[] = "dt.is_public_no_auth = 1";
        }

        // 2. Onaylı üyelere açık konular
        if ($can_view_members_only) {
            $visibility_or_conditions[] = "dt.is_members_only = 1";
        }

        // 3. Rol bazlı konular (faction_only)
        if ($current_user_is_approved && $current_user_id) {
            $user_roles = get_user_roles($pdo, $current_user_id);
            $user_role_ids = array_column($user_roles, 'id');
            
            if (!empty($user_role_ids)) {
                $role_placeholders = [];
                foreach ($user_role_ids as $idx => $role_id) {
                    $placeholder = ':user_role_id_' . $idx;
                    $role_placeholders[] = $placeholder;
                    $params[$placeholder] = $role_id;
                }
                $in_clause_roles = implode(',', $role_placeholders);
                
                $visibility_or_conditions[] = "(dt.is_public_no_auth = 0 AND dt.is_members_only = 0 AND EXISTS (
                                                SELECT 1 FROM discussion_topic_visibility_roles dtvr
                                                WHERE dtvr.topic_id = dt.id AND dtvr.role_id IN (" . $in_clause_roles . ")
                                             ))";
            }
        }
        
        // 4. Kullanıcının kendi başlattığı konular her zaman görünür
        if ($current_user_id) {
            $visibility_or_conditions[] = "dt.user_id = :current_user_id_owner";
            $params[':current_user_id_owner'] = $current_user_id;
        }

        if (!empty($visibility_or_conditions)) {
            $where_clauses[] = "(" . implode(" OR ", $visibility_or_conditions) . ")";
        } else {
            $where_clauses[] = "1=0"; // Hiçbir sonuç döndürmez
        }
    }

    $sql_where_string = "";
    if (!empty($where_clauses)) {
        $sql_where_string = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_order_by = " ORDER BY dt.is_pinned DESC, dt.last_reply_at DESC, dt.created_at DESC";
    $final_sql = $sql_base . $sql_from_join . $sql_where_string . $sql_order_by;
    
    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params);
    $raw_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Okunmamış durumunu belirle
    foreach ($raw_topics as $topic) {
        $is_unread = false;
        if ($current_user_id && $topic['last_reply_at'] !== null) {
            $last_activity_time = strtotime($topic['last_reply_at']);
            $last_viewed_time = ($topic['last_viewed_at'] === null) ? 0 : strtotime($topic['last_viewed_at']);

            if ($last_activity_time > $last_viewed_time) {
                if ( ($topic['reply_count'] > 0) || 
                     ($topic['reply_count'] == 0 && $topic['topic_starter_id'] != $current_user_id && $topic['last_viewed_at'] === null) 
                   ) {
                    $is_unread = true;
                }
            }
        }
        $topic['is_unread'] = $is_unread;
        $topics_with_details[] = $topic;
    }

} catch (PDOException $e) {
    error_log("Tartışma konularını çekme hatası (discussions.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Tartışmalar yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Discussions Page Styles - Consistent with Gallery & Events */
.discussions-page-container {
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.discussions-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 3rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.discussions-header h1 {
    color: var(--gold);
    font-size: 2.5rem;
    font-family: var(--font);
    margin: 0;
    font-weight: 300;
    letter-spacing: -0.5px;
}

.discussions-count {
    color: var(--light-grey);
    font-size: 1rem;
    margin-top: 0.25rem;
}

.btn-new-topic {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    border: 1px solid var(--turquase);
    background-color: var(--turquase);
    color: var(--charcoal);
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-new-topic:hover {
    background-color: var(--light-turquase);
    transform: translateY(-1px);
}

.btn-new-topic i {
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

.discussions-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.discussion-item {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    margin-bottom: 1rem;
    padding: 1.5rem;
    transition: all 0.2s ease;
    position: relative;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: hidden;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.discussion-item:hover {
    border-color: var(--gold);
}

.discussion-item.unread {
    border-left: 4px solid var(--gold);
}

.discussion-item.pinned {
    border-left: 4px solid var(--turquase);
    background-color: rgba(42, 189, 168, 0.05);
}

.discussion-item.locked {
    opacity: 0.8;
}

.discussion-content {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 1rem;
    align-items: flex-start;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.discussion-avatar {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
}

.starter-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-1);
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: var(--grey);
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 500;
    border: 1px solid var(--darker-gold-1);
}

.discussion-main {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.discussion-title {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    line-height: 1.3;
    word-break: break-word;
    overflow-wrap: break-word;
}

.discussion-title a {
    color: var(--lighter-grey);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
    word-break: break-word;
    overflow-wrap: break-word;
}

.discussion-title a:hover {
    color: var(--gold);
}

.discussion-item.unread .discussion-title a {
    color: var(--white);
    font-weight: 600;
}

.topic-badges {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: 0.5rem;
}

.badge-pinned {
    color: var(--turquase);
    font-size: 0.9em;
}

.badge-locked {
    background-color: var(--red);
    color: var(--white);
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.discussion-meta {
    font-size: 0.85rem;
    color: var(--light-grey);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.discussion-meta .starter-name {
    color: var(--lighter-grey);
    font-weight: 500;
    text-decoration: none;
}

.discussion-meta .starter-name:hover {
    color: var(--gold);
}

.discussion-stats {
    text-align: right;
    font-size: 0.85rem;
    color: var(--light-grey);
    min-width: 140px;
}

.stat-replies {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    justify-content: flex-end;
    margin-bottom: 0.5rem;
}

.stat-replies i {
    color: var(--gold);
    font-size: 0.9em;
}

.last-reply {
    font-size: 0.8rem;
}

.last-reply-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 0.25rem;
}

.last-reply-avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-2);
}

.last-reply-avatar-placeholder {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: var(--grey);
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 500;
    border: 1px solid var(--darker-gold-2);
}

.last-reply-name {
    color: var(--lighter-grey);
    font-weight: 500;
    text-decoration: none;
    font-size: 0.85rem;
}

.last-reply-name:hover {
    color: var(--gold);
}

.empty-state {
    text-align: center;
    font-size: 1rem;
    color: var(--light-grey);
    padding: 3rem 2rem;
    border: 1px dashed var(--grey);
    border-radius: 6px;
    margin-top: 2rem;
}

.empty-state a {
    color: var(--turquase);
    font-weight: 500;
    text-decoration: none;
}

.empty-state a:hover {
    text-decoration: underline;
}

/* User Info Trigger Base Styles */
.user-info-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: default;
    position: relative;
    max-width: 100%;
    overflow: hidden;
}

.user-info-trigger img {
    max-width: 100% !important;
    height: auto !important;
    flex-shrink: 0;
    max-height: 48px !important;
    width: auto !important;
    object-fit: cover !important;
    border-radius: 50% !important;
}

/* Specifically target avatar images in user info triggers */
.user-info-trigger .starter-avatar,
.user-info-trigger .last-reply-avatar,
.user-info-trigger .avatar-placeholder {
    width: 48px !important;
    height: 48px !important;
    max-width: 48px !important;
    max-height: 48px !important;
    min-width: 48px !important;
    min-height: 48px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    flex-shrink: 0 !important;
}

.user-info-trigger a {
    transition: color 0.2s ease;
    text-decoration: none;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-info-trigger a:hover {
    text-decoration: underline;
}

/* Force any image inside discussion content to be controlled */
.discussion-content img,
.discussion-main img,
.discussion-avatar img {
    max-width: 48px !important;
    max-height: 48px !important;
    width: 48px !important;
    height: 48px !important;
    object-fit: cover !important;
    border-radius: 50% !important;
    flex-shrink: 0 !important;
}

/* Rol Bazlı Renk Sistemleri - Navbar ile uyumlu */
.user-info-trigger.role-admin .starter-name,
.user-info-trigger.role-admin .last-reply-name,
.user-info-trigger.role-admin a {
    color: #0091ff !important; /* Admin rol rengi */
}

.user-info-trigger.role-ilgarion_turanis .starter-name,
.user-info-trigger.role-ilgarion_turanis .last-reply-name,
.user-info-trigger.role-ilgarion_turanis a {
    color: #3da6a2 !important; /* Ilgarion Turanis rol rengi */
}

.user-info-trigger.role-scg_uye .starter-name,
.user-info-trigger.role-scg_uye .last-reply-name,
.user-info-trigger.role-scg_uye a {
    color: #a52a2a !important; /* SCG üye rol rengi */
}

.user-info-trigger.role-member .starter-name,
.user-info-trigger.role-member .last-reply-name,
.user-info-trigger.role-member a {
    color: #0000ff !important; /* Member rol rengi */
}

.user-info-trigger.role-dis_uye .starter-name,
.user-info-trigger.role-dis_uye .last-reply-name,
.user-info-trigger.role-dis_uye a {
    color: #808080 !important; /* Dış üye rol rengi */
}

/* Responsive Design */
@media (max-width: 768px) {
    .discussions-page-container {
        padding: 1.5rem 1rem;
    }
    
    .discussions-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        text-align: center;
    }
    
    .discussions-header h1 {
        font-size: 2rem;
    }
    
    .btn-new-topic {
        width: 100%;
        justify-content: center;
    }
    
    .discussion-content {
        grid-template-columns: auto 1fr;
        grid-template-areas:
            "avatar main"
            "stats stats";
        gap: 1rem;
    }
    
    .discussion-avatar {
        grid-area: avatar;
    }
    
    .discussion-main {
        grid-area: main;
        min-width: 0;
        overflow: hidden;
    }
    
    .discussion-main img {
        max-height: 150px;
    }
    
    .discussion-stats {
        grid-area: stats;
        text-align: left;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--darker-gold-2);
        min-width: 0;
        max-width: 100%;
    }
    
    .last-reply-user {
        justify-content: flex-start;
    }
}

@media (max-width: 480px) {
    .discussions-page-container {
        padding: 1rem 0.75rem;
    }
    
    .discussions-header h1 {
        font-size: 1.75rem;
    }
    
    .discussion-item {
        padding: 1rem;
    }
    
    .discussion-title {
        font-size: 1.125rem;
    }
    
    .discussion-main img {
        max-height: 120px;
    }
    
    .topic-badges {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        margin-left: 0;
        margin-top: 0.25rem;
    }
    
    .discussion-content {
        gap: 0.75rem;
    }
}
</style>

<main class="main-content">
    <div class="container discussions-page-container">
        <div class="discussions-header">
            <div>
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <div class="discussions-count"><?php echo count($topics_with_details); ?> Tartışma Konusu</div>
            </div>
            <?php if ($can_create_topic): ?>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/new_discussion_topic.php" class="btn-new-topic">
                    <i class="fas fa-plus-circle"></i>
                    Yeni Tartışma Başlat
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$current_user_is_logged_in && !empty($topics_with_details)): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> 
                Şu anda sadece herkese açık tartışmaları görüntülüyorsunuz. Daha fazla tartışmaya erişmek için 
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/login.php">giriş yapın</a> ya da 
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/register.php">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (empty($topics_with_details)): ?>
            <div class="empty-state">
                <i class="fas fa-comments" style="font-size: 3rem; color: var(--gold); margin-bottom: 1rem;"></i><br>
                Henüz hiç tartışma başlığı açılmamış veya görüntüleme yetkiniz olan bir konu bulunmuyor.
                <?php if ($can_create_topic): ?>
                    <br>İlk tartışmayı <a href="<?php echo htmlspecialchars($baseUrl); ?>/new_discussion_topic.php">sen başlat</a>!
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="discussions-list">
                <?php foreach ($topics_with_details as $topic): ?>
                    <?php
                        // Rol bazında renk belirleme - en yüksek öncelikli rolü al
                        $starter_primary_role = '';
                        $replier_primary_role = '';
                        
                        if (!empty($topic['topic_starter_roles_list'])) {
                            $starter_roles = explode(',', $topic['topic_starter_roles_list']);
                            $starter_primary_role = trim($starter_roles[0]); // İlk rol en yüksek öncelikli
                        }
                        
                        if (!empty($topic['last_replier_roles_list'])) {
                            $replier_roles = explode(',', $topic['last_replier_roles_list']);
                            $replier_primary_role = trim($replier_roles[0]); // İlk rol en yüksek öncelikli
                        }

                        $starter_popover_data = [
                            'id' => $topic['topic_starter_id'],
                            'username' => $topic['topic_starter_username'],
                            'avatar_path' => $topic['topic_starter_avatar'],
                            'ingame_name' => $topic['topic_starter_ingame'],
                            'discord_username' => $topic['topic_starter_discord'],
                            'user_event_count' => $topic['topic_starter_event_count'],
                            'user_gallery_count' => $topic['topic_starter_gallery_count'],
                            'user_roles_list' => $topic['topic_starter_roles_list']
                        ];
                        
                        $replier_popover_data = null;
                        if ($topic['last_replier_user_id']) {
                            $replier_popover_data = [
                                'id' => $topic['last_replier_user_id'],
                                'username' => $topic['last_replier_username'],
                                'avatar_path' => $topic['last_replier_avatar'],
                                'ingame_name' => $topic['last_replier_ingame'],
                                'discord_username' => $topic['last_replier_discord'],
                                'user_event_count' => $topic['last_replier_event_count'],
                                'user_gallery_count' => $topic['last_replier_gallery_count'],
                                'user_roles_list' => $topic['last_replier_roles_list']
                            ];
                        }
                        
                        // CSS classes for topic state
                        $itemClasses = ['discussion-item'];
                        if ($topic['is_unread'] && $current_user_is_logged_in) $itemClasses[] = 'unread';
                        if ($topic['is_pinned']) $itemClasses[] = 'pinned';
                        if ($topic['is_locked']) $itemClasses[] = 'locked';
                    ?>
                    <li class="<?php echo implode(' ', $itemClasses); ?>">
                        <div class="discussion-content">
                            <div class="discussion-main">
                                <h3 class="discussion-title">
                                    <a href="discussion_detail.php?id=<?php echo $topic['id']; ?><?php 
                                        if($topic['is_unread'] && $topic['reply_count'] > 0) echo '#latest-comment'; 
                                        elseif($topic['is_unread']) echo '#newCommentForm';
                                    ?>">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                    <span class="topic-badges">
                                        <?php if ($topic['is_pinned']): ?>
                                            <i class="fas fa-thumbtack badge-pinned" title="Sabitlenmiş Konu"></i>
                                        <?php endif; ?>
                                        <?php if ($topic['is_locked']): ?>
                                            <span class="badge-locked" title="Bu konu yorumlara kapatılmıştır.">
                                                <i class="fas fa-lock"></i> Kilitli
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </h3>
                                
                                <div class="discussion-meta">
                                    <?php
                                    echo render_user_info_with_popover(
                                        $pdo, 
                                        $starter_popover_data, 
                                        'starter-name', 
                                        '', 
                                        'user-info-trigger' . ($starter_primary_role ? ' role-' . $starter_primary_role : '')
                                    );
                                    ?>
                                    <span>tarafından <?php echo date('d M Y, H:i', strtotime($topic['topic_created_at'])); ?> tarihinde başlatıldı.</span>
                                </div>
                            </div>

                            <div class="discussion-stats">
                                <div class="stat-replies">
                                    <i class="fas fa-comments"></i>
                                    <span><?php echo $topic['reply_count']; ?> Yorum</span>
                                </div>
                                
                                <?php if ($topic['reply_count'] > 0 && $replier_popover_data): ?>
                                    <div class="last-reply">
                                        <div>Son: <?php echo date('d M, H:i', strtotime($topic['last_reply_at'])); ?></div>
                                        <div class="last-reply-user">
                                            <?php echo render_user_info_with_popover(
                                                $pdo, 
                                                $replier_popover_data, 
                                                'last-reply-name', 
                                                'last-reply-avatar', 
                                                'user-info-trigger' . ($replier_primary_role ? ' role-' . $replier_primary_role : '')
                                            ); ?>
                                        </div>
                                    </div>
                                <?php elseif($topic['reply_count'] == 0): ?>
                                    <div class="last-reply">Henüz yorum yok</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced card interactions
    const discussionItems = document.querySelectorAll('.discussion-item');
    discussionItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        item.addEventListener('mouseleave', function() {
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
        
        discussionItems.forEach(item => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(15px)';
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            cardObserver.observe(item);
        });
    }

    // Mark topics as read when clicked
    const topicLinks = document.querySelectorAll('.discussion-title a');
    topicLinks.forEach(link => {
        link.addEventListener('click', function() {
            const discussionItem = this.closest('.discussion-item');
            if (discussionItem && discussionItem.classList.contains('unread')) {
                // Add visual feedback for read state
                setTimeout(() => {
                    discussionItem.classList.remove('unread');
                    discussionItem.style.borderLeftColor = 'var(--grey)';
                }, 100);
            }
        });
    });

    // Enhanced accessibility
    document.querySelectorAll('.discussion-item').forEach(item => {
        item.addEventListener('focus', function() {
            this.style.outline = '2px solid var(--gold)';
            this.style.outlineOffset = '2px';
        });
        
        item.addEventListener('blur', function() {
            this.style.outline = 'none';
        });
    });

    // Keyboard navigation for discussion items
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            const focusedItem = document.activeElement;
            if (focusedItem && focusedItem.classList.contains('discussion-item')) {
                e.preventDefault();
                const link = focusedItem.querySelector('.discussion-title a');
                if (link) {
                    link.click();
                }
            }
        }
    });

    // Auto-refresh discussions every 2 minutes (optional feature)
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(() => {
            // Only refresh if user is active (not idle)
            if (document.hasFocus()) {
                // Check for new discussions via AJAX (implementation depends on your backend)
                checkForNewDiscussions();
            }
        }, 120000); // 2 minutes
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }
    
    function checkForNewDiscussions() {
        // This would typically make an AJAX request to check for new discussions
        // For now, just show a subtle notification if there might be new content
        const currentCount = document.querySelectorAll('.discussion-item').length;
        
        // Example implementation (you'd replace this with actual AJAX call)
        fetch(window.location.href, {
            method: 'HEAD',
            cache: 'no-cache'
        }).then(response => {
            // Check if content has changed (you'd implement this based on your needs)
            // For now, just a placeholder
        }).catch(error => {
            console.log('Auto-refresh check failed:', error);
        });
    }
    
    // Start auto-refresh if user is logged in
    <?php if ($current_user_is_logged_in): ?>
    startAutoRefresh();
    
    // Stop refresh when page becomes hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
    <?php endif; ?>

    // Show loading state for new topic button
    const newTopicBtn = document.querySelector('.btn-new-topic');
    if (newTopicBtn) {
        newTopicBtn.addEventListener('click', function() {
            this.style.opacity = '0.7';
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yönlendiriliyor...';
        });
    }

    // Enhanced topic title truncation for very long titles
    const topicTitles = document.querySelectorAll('.discussion-title a');
    topicTitles.forEach(title => {
        const maxLength = window.innerWidth < 768 ? 50 : 80;
        if (title.textContent.length > maxLength) {
            title.title = title.textContent; // Show full title on hover
            title.style.overflow = 'hidden';
            title.style.textOverflow = 'ellipsis';
            title.style.whiteSpace = 'nowrap';
            title.style.maxWidth = '100%';
            title.style.display = 'inline-block';
        }
    });

    // Smooth scroll to discussion when coming from notification
    if (window.location.hash && window.location.hash.startsWith('#discussion-')) {
        const targetId = window.location.hash.substring(1);
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
            setTimeout(() => {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                targetElement.style.background = 'rgba(189, 145, 42, 0.1)';
                setTimeout(() => {
                    targetElement.style.background = '';
                }, 2000);
            }, 500);
        }
    }

    // Performance optimization: Lazy load user avatars
    if ('IntersectionObserver' in window) {
        const avatarObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const avatar = entry.target;
                    if (avatar.dataset.src) {
                        avatar.src = avatar.dataset.src;
                        avatar.removeAttribute('data-src');
                        avatarObserver.unobserve(avatar);
                    }
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            avatarObserver.observe(img);
        });
    }

    // Role-based styling enhancements
    document.querySelectorAll('.user-info-trigger').forEach(trigger => {
        // Add subtle animation for role-colored usernames
        if (trigger.classList.contains('role-admin') || 
            trigger.classList.contains('role-ilgarion_turanis') ||
            trigger.classList.contains('role-scg_uye')) {
            trigger.addEventListener('mouseenter', function() {
                this.style.textShadow = '0 0 5px currentColor';
            });
            
            trigger.addEventListener('mouseleave', function() {
                this.style.textShadow = 'none';
            });
        }
    });

    // Enhanced permission-based UI adjustments
    <?php if (!$current_user_is_logged_in): ?>
    // Add subtle visual cues for guest users
    document.body.classList.add('guest-user');
    <?php elseif (!$current_user_is_approved): ?>
    // Add visual cues for unapproved users
    document.body.classList.add('unapproved-user');
    <?php endif; ?>

    // Performance monitoring (optional)
    if ('PerformanceObserver' in window) {
        const perfObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (entry.entryType === 'navigation') {
                    console.log('Page load time:', entry.loadEventEnd - entry.loadEventStart, 'ms');
                }
            }
        });
        perfObserver.observe({ entryTypes: ['navigation'] });
    }

    // Enhanced error handling for AJAX operations
    window.addEventListener('unhandledrejection', function(event) {
        console.warn('Unhandled promise rejection:', event.reason);
        // Optionally show user-friendly error message
        if (event.reason && event.reason.message && event.reason.message.includes('fetch')) {
            // Network error handling
            console.log('Network issue detected, discussion features may be limited');
        }
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>