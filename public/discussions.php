<?php
// public/discussions.php

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

$page_title = "Tartışmalar";
$topics_with_details = [];

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

$can_create_topic = $current_user_is_approved && has_permission($pdo, 'discussion.topic.create', $current_user_id);
$can_view_discussions_page = false;
$access_message_discussions = "";

// Sayfa görüntüleme yetkisi kontrolü
if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'discussion.view_all', $current_user_id))) {
    $can_view_discussions_page = true;
} elseif ($current_user_is_approved) {
    if (has_permission($pdo, 'discussion.view_approved', $current_user_id) || // Onaylı üyelere açık konuları görme yetkisi
        has_permission($pdo, 'discussion.view_public', $current_user_id) ||
        has_permission($pdo, 'discussion.view_faction_only', $current_user_id) // Rol bazlı konuları görme yetkisi (genel)
       ) {
        $can_view_discussions_page = true;
    } else {
        $access_message_discussions = "Tartışmaları görüntüleme yetkiniz bulunmamaktadır.";
    }
} elseif (has_permission($pdo, 'discussion.view_public', null)) { // Misafir kullanıcılar için public konuları görme
    $can_view_discussions_page = true;
} else {
     $access_message_discussions = "Tartışmaları görmek için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
}

if (!$can_view_discussions_page) {
    $_SESSION['error_message'] = $access_message_discussions ?: "Tartışmaları görüntüleme yetkiniz bulunmamaktadır.";
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
}


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
                (SELECT GROUP_CONCAT(r_starter.name ORDER BY FIELD(r_starter.name, '" . implode("','", $GLOBALS['role_priority'] ?? []) . "') SEPARATOR ',')
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
                (SELECT GROUP_CONCAT(r_replier.name ORDER BY FIELD(r_replier.name, '" . implode("','", $GLOBALS['role_priority'] ?? []) . "') SEPARATOR ',')
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


    if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'discussion.view_all', $current_user_id))) {
        // Admin veya 'view_all' yetkisine sahip olanlar için ek bir WHERE koşulu gerekmez (tüm görünürlükleri görürler)
    } else {
        $visibility_or_conditions = [];

        // 1. Herkese açık konular (discussion.view_public yetkisiyle)
        if (has_permission($pdo, 'discussion.view_public', $current_user_id)) {
            $visibility_or_conditions[] = "dt.is_public_no_auth = 1";
        }

        // 2. Onaylı üyelere açık konular (discussion.view_approved yetkisiyle)
        if ($current_user_is_approved && has_permission($pdo, 'discussion.view_approved', $current_user_id)) {
            $visibility_or_conditions[] = "dt.is_members_only = 1";
        }

        // 3. Rol bazlı konular (faction_only gibi)
        // Kullanıcı onaylı olmalı VE konunun atandığı rollerden birine sahip olmalı
        if ($current_user_is_approved) {
            $user_actual_role_ids_list = [];
            if ($current_user_id) {
                $stmt_user_role_ids_list = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :current_user_id_for_role_check");
                $stmt_user_role_ids_list->execute([':current_user_id_for_role_check' => $current_user_id]);
                $user_actual_role_ids_list = $stmt_user_role_ids_list->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($user_actual_role_ids_list)) {
                $role_placeholders_sql = [];
                foreach ($user_actual_role_ids_list as $idx_role_list => $role_id_val_list) {
                    $placeholder_role_list = ':user_role_id_for_topic_visibility_' . $idx_role_list;
                    $role_placeholders_sql[] = $placeholder_role_list;
                    $params[$placeholder_role_list] = $role_id_val_list;
                }
                $in_clause_roles_sql_list = implode(',', $role_placeholders_sql);
                if (!empty($in_clause_roles_sql_list)) {
                     $visibility_or_conditions[] = "(dt.is_public_no_auth = 0 AND dt.is_members_only = 0 AND EXISTS (
                                                    SELECT 1 FROM discussion_topic_visibility_roles dtvr_check_list
                                                    WHERE dtvr_check_list.topic_id = dt.id AND dtvr_check_list.role_id IN (" . $in_clause_roles_sql_list . ")
                                                 ))";
                }
            }
        }
        
        // 4. Kullanıcının kendi başlattığı konular her zaman görünür (eğer admin değilse ve view_all yetkisi yoksa)
        if ($current_user_id && !$current_user_is_admin && !has_permission($pdo, 'discussion.view_all', $current_user_id)) {
            $visibility_or_conditions[] = "dt.user_id = :current_user_id_owner_check_list_page";
            $params[':current_user_id_owner_check_list_page'] = $current_user_id;
        }

        if (!empty($visibility_or_conditions)) {
            $where_clauses[] = "(" . implode(" OR ", $visibility_or_conditions) . ")";
        } else {
            // Eğer hiçbir OR koşulu oluşmadıysa (örneğin, misafir ve public görme yetkisi yoksa), hiçbir şey göremez.
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

    // Okunmamış durumunu belirle (önceki gibi)
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
    error_log("Tartışma konularını çekme hatası (discussions.php): " . $e->getMessage() . " --- SQL: " . ($final_sql ?? 'SQL not generated') . " --- Params: " . print_r($params ?? [], true));
    $_SESSION['error_message'] = "Tartışmalar yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* discussions.php için GÜNCELLENMİŞ Stiller */
.discussions-page-container-v3 {
    width: 100%;
    max-width: 1600px;
    margin: 20px auto;
    padding: 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 140px);
}

.page-top-nav-controls {    
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px; 
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.page-top-nav-controls .page-title-discussions {
    color: var(--gold);
    font-size: 2.2rem;
    font-family: var(--font);
    margin: 0;
    flex-grow: 1;
}
.btn-start-new-topic-main-v2 { 
    background-color: var(--turquase); color: var(--black); padding: 10px 22px;
    border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 0.95rem;
    transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;
    border: none; box-shadow: 0 2px 8px rgba(var(--turquase-rgb,0,0,0),0.2);
    white-space: nowrap;
}
.btn-start-new-topic-main-v2:hover {
    background-color: var(--light-turquase); color: var(--darker-gold-2);
    transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--turquase-rgb,0,0,0),0.3);
}
.btn-start-new-topic-main-v2 i.fas { font-size: 0.9em; }

.discussion-list { list-style-type: none; padding: 0; margin: 0; }
.discussion-list-item {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-left-width: 5px;
    border-radius: 10px;
    margin-bottom: 18px;
    padding: 18px 22px;
    display: grid;
    grid-template-columns: auto 1fr auto; 
    grid-template-areas: "avatar info stats";
    gap: 18px; 
    align-items: flex-start; 
    transition: all 0.25s ease;
}
.discussion-list-item:hover {
    background-color: var(--darker-gold-2);
    border-color: var(--gold); 
    transform: translateY(-3px) scale(1.01); 
    box-shadow: 0 6px 20px rgba(var(--gold-rgb, 189,145,42), 0.1); 
}
.discussion-list-item.topic-unread { border-left-color: var(--gold); }
.discussion-list-item.topic-unread:hover { border-left-color: var(--light-gold); }
.discussion-list-item.topic-read { border-left-color: var(--grey); }
.discussion-list-item.topic-read:hover { border-left-color: var(--light-grey); }
.discussion-list-item.pinned-topic {
    border-left-color: var(--turquase) !important; 
    background-color: rgba(var(--turquase-rgb, 0,0,0), 0.05); 
}
.discussion-list-item.pinned-topic:hover {
    border-left-color: var(--light-turquase) !important;
    box-shadow: 0 6px 20px rgba(var(--turquase-rgb,0,0,0), 0.15);
}

.discussion-avatar-area { grid-area: avatar; flex-shrink: 0; padding-top: 2px; } 
.starter-avatar-disc-list { 
    width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--darker-gold-1);
}
.avatar-placeholder-disc-list { 
    width: 50px; height: 50px; border-radius: 50%; background-color: var(--grey); color: var(--gold);
    display: flex; align-items: center; justify-content: center; font-size: 1.7rem; font-weight: bold;
    border: 2px solid var(--darker-gold-1); line-height: 1;
}

.discussion-info-area { 
    grid-area: info;
    display: flex;
    flex-direction: column;
    gap: 5px; 
    min-width:0; 
}
.discussion-title { margin: 0; font-size: 1.35rem; line-height: 1.3; }
.discussion-title a { color: var(--lighter-grey); text-decoration: none; font-weight: 600; word-break: break-word; }
.discussion-title a:hover { color: var(--gold); text-decoration: underline; }
.discussion-list-item.topic-unread .discussion-title a { color: var(--white); }
.discussion-list-item.topic-unread .discussion-title a strong { color: var(--gold); }

.topic-icons-bar { display: inline-flex; align-items: center; gap: 10px; margin-left: 8px; }
.topic-status-badge.locked-topic {
    font-size: 0.78rem; padding: 3px 7px; border-radius: 4px;
    font-weight: 500; vertical-align: middle;
    background-color: var(--dark-red); color: var(--white); border: 1px solid var(--red);
}
.topic-pin-icon { color: var(--turquase); font-size: 1.1em; vertical-align: middle; }

.discussion-meta { font-size: 0.85rem; color: var(--light-grey); }
.discussion-meta .starter-name-wrapper { 
    display: inline-flex; align-items:center; gap:4px; cursor:default;
}
.discussion-meta .starter-name-link { 
    color: inherit !important; 
    font-weight: 500; text-decoration: none;
}
.discussion-meta .starter-name-link:hover { text-decoration: underline; }


.discussion-stats-area { 
    grid-area: stats;
    flex-shrink: 0;
    text-align: right;
    font-size: 0.9rem;
    color: var(--light-grey);
    min-width: 180px; 
    display: flex;
    flex-direction: column;
    gap: 8px; 
    align-items: flex-end; 
    padding-top:2px; 
}
.discussion-stats-area .stat-item { display: block; }
.discussion-stats-area .stat-item i.fas { margin-right: 6px; color:var(--light-grey); font-size:0.9em;}
.discussion-stats-area .last-reply-info { font-size: 0.85rem; color: var(--light-grey); text-align: right; }
.last-reply-info .last-reply-text { display:block; margin-bottom:3px;}
.last-reply-info .replier-avatar-area { 
    display: inline-flex; align-items: center; gap: 6px; cursor: default;
}
.replier-avatar-micro { 
    width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1px solid var(--darker-gold-2);
}
.avatar-placeholder-micro { 
    width: 22px; height: 22px; border-radius: 50%; background-color: var(--grey); color: var(--gold);
    display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;
    border: 1px solid var(--darker-gold-1); line-height: 1;
}
.replier-name-link { 
    color: inherit !important; 
    font-weight: 500; text-decoration: none; font-size:0.95em;
}
.replier-name-link:hover { text-decoration: underline; }

/* Kullanıcı adı renkleri (style.css'den gelmeli) */
.discussion-avatar-area.username-role-admin .starter-avatar-disc-list,
.discussion-avatar-area.username-role-admin .avatar-placeholder-disc-list,
.last-reply-info .replier-avatar-area.username-role-admin .replier-avatar-micro,
.last-reply-info .replier-avatar-area.username-role-admin .avatar-placeholder-micro { border-color: var(--gold) !important; }

.discussion-meta .starter-name-wrapper.username-role-admin .starter-name-link,
.last-reply-info .replier-avatar-area.username-role-admin .replier-name-link { color: var(--gold) !important; }

.discussion-meta .starter-name-wrapper.username-role-scg_uye .starter-name-link,
.last-reply-info .replier-avatar-area.username-role-scg_uye .replier-name-link { color: #A52A2A !important; }
/* Diğer roller için benzer stiller eklenebilir veya render_user_info_with_popover'daki inline stil yeterli olabilir */


.empty-message { text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px; background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1); margin-top:20px;}
.empty-message a { color:var(--turquase); font-weight:bold;}
.empty-message a:hover { color:var(--light-turquase);}

@media (max-width: 992px) { 
    .discussion-list-item {
        grid-template-columns: auto 1fr; 
        grid-template-areas:
            "avatar info"
            "stats stats"; 
        align-items: center; 
    }
    .discussion-stats-area {
        grid-area: stats; 
        text-align: left; 
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--darker-gold-2);
        width: 100%;
        min-width: 0;
        align-items: flex-start; 
        gap:5px;
    }
}
@media (max-width: 768px) { 
    .discussions-page-container-v3 { padding: 20px; }
    .page-top-nav-controls { flex-direction: column; align-items: stretch; gap:15px; }
    .btn-start-new-topic-main-v2 { width:100%; text-align:center; justify-content:center;}
    .page-top-nav-controls .page-title-discussions { font-size: 2rem; text-align:center; }
    .discussion-list-item {
        padding: 15px;
        gap: 15px; 
    }
    .discussion-title { font-size: 1.15rem; }
}
</style>

<main class="main-content">
    <div class="container discussions-page-container-v3">
        <div class="page-top-nav-controls">
            <h1 class="page-title-discussions"><?php echo htmlspecialchars($page_title); ?> (<?php echo count($topics_with_details); ?> Konu)</h1>
            <?php if ($can_create_topic): ?>
                <a href="new_discussion_topic.php" class="btn-start-new-topic-main-v2"><i class="fas fa-plus-circle"></i> Yeni Tartışma Başlat</a>
            <?php endif; ?>
        </div>

        <?php // Hata ve başarı mesajları header.php'de gösteriliyor ?>

        <?php if (!$current_user_is_logged_in && $can_view_discussions_page && !empty($topics_with_details)): ?>
            <div class="info-message" style="text-align: center; margin-bottom: 20px; background-color: var(--transparent-turquase-2); color: var(--turquase); border: 1px solid var(--turquase); padding: 10px; border-radius: 5px;">
                <i class="fas fa-info-circle"></i> Şu anda sadece herkese açık tartışmaları görüntülüyorsunuz. Daha fazla tartışmaya erişmek için <a href="<?php echo get_auth_base_url(); ?>/login.php" style="color: var(--light-turquase); font-weight:bold;">giriş yapın</a> ya da <a href="<?php echo get_auth_base_url(); ?>/register.php" style="color: var(--light-turquase); font-weight:bold;">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (empty($topics_with_details)): ?>
            <p class="empty-message">Henüz hiç tartışma başlığı açılmamış veya görüntüleme yetkiniz olan bir konu bulunmuyor.
                <?php if ($can_create_topic): ?>
                    İlkini <a href="new_discussion_topic.php">sen başlat</a>!
                <?php endif; ?>
            </p>
        <?php else: ?>
            <ul class="discussion-list">
                <?php foreach ($topics_with_details as $topic): ?>
                    <?php
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
                    ?>
                    <li class="discussion-list-item <?php echo $topic['is_unread'] ? 'topic-unread' : 'topic-read'; ?> <?php echo $topic['is_pinned'] ? 'pinned-topic' : ''; ?>">
                        <div class="discussion-avatar-area">
                            <?php echo render_user_info_with_popover($pdo, $starter_popover_data, '', 'starter-avatar-disc-list', 'user-info-trigger'); ?>
                        </div>

                        <div class="discussion-info-area">
                            <h3 class="discussion-title">
                                <a href="discussion_detail.php?id=<?php echo $topic['id']; ?><?php if($topic['is_unread'] && $topic['reply_count'] > 0) echo '#latest-comment'; elseif($topic['is_unread']) echo '#newCommentForm';?>" 
                                   class="<?php echo $topic['is_unread'] ? 'unread-link-style' : ''; ?>">
                                    <?php if ($topic['is_unread']): ?><strong><?php endif; ?>
                                    <?php echo htmlspecialchars($topic['title']); ?>
                                    <?php if ($topic['is_unread']): ?></strong><?php endif; ?>
                                </a>
                                <span class="topic-icons-bar">
                                    <?php if ($topic['is_pinned']): ?>
                                        <i class="fas fa-thumbtack topic-pin-icon" title="Sabitlenmiş Konu"></i>
                                    <?php endif; ?>
                                    <?php if ($topic['is_locked']): ?>
                                        <span class="topic-status-badge locked-topic" title="Bu konu yorumlara kapatılmıştır.">🔒 Kilitli</span>
                                    <?php endif; ?>
                                </span>
                            </h3>
                            <small class="discussion-meta">
                                <?php // Konu başlatanın popover'ı avatarın üzerine geldiğinde zaten çıkıyor, burada tekrar render_user_info_with_popover çağırmak yerine direkt link verelim.
                                      // Veya daha küçük bir gösterim için ayrı bir fonksiyon/mantık gerekebilir.
                                      // Şimdilik, render_user_info_with_popover'ın sadece link class'ını kullanarak metin kısmını alalım.
                                ?>
                                <span class="starter-name-wrapper user-info-trigger"
                                      data-user-id="<?php echo htmlspecialchars($starter_popover_data['id']); ?>"
                                      data-username="<?php echo htmlspecialchars($starter_popover_data['username']); ?>"
                                      data-avatar="<?php echo htmlspecialchars(!empty($starter_popover_data['avatar_path']) ? ('/public/' . $starter_popover_data['avatar_path']) : ''); ?>"
                                      data-ingame="<?php echo htmlspecialchars($starter_popover_data['ingame_name'] ?? 'N/A'); ?>"
                                      data-discord="<?php echo htmlspecialchars($starter_popover_data['discord_username'] ?? 'N/A'); ?>"
                                      data-event-count="<?php echo htmlspecialchars($starter_popover_data['user_event_count'] ?? '0'); ?>"
                                      data-gallery-count="<?php echo htmlspecialchars($starter_popover_data['user_gallery_count'] ?? '0'); ?>"
                                      data-roles="<?php echo htmlspecialchars($starter_popover_data['user_roles_list'] ?? ''); ?>">
                                    <a href="view_profile.php?user_id=<?php echo $starter_popover_data['id']; ?>" 
                                       class="starter-name-link" 
                                       <?php /* Rol rengi burada render_user_info_with_popover tarafından eklenen inline style ile gelecek */ ?>>
                                        <?php echo htmlspecialchars($starter_popover_data['username']); ?>
                                    </a>
                                </span>
                                tarafından <?php echo date('d M Y, H:i', strtotime($topic['topic_created_at'])); ?> tarihinde başlatıldı.
                            </small>
                        </div>

                        <div class="discussion-stats-area">
                            <span class="stat-item"><i class="fas fa-comments"></i> <?php echo $topic['reply_count']; ?> Yorum</span>
                            <?php if ($topic['reply_count'] > 0 && $replier_popover_data): ?>
                                <div class="last-reply-info">
                                    <span class="last-reply-text">Son Yorum: (<?php echo date('d M, H:i', strtotime($topic['last_reply_at'])); ?>)</span>
                                    <span class="replier-avatar-area">
                                        <?php echo render_user_info_with_popover($pdo, $replier_popover_data, 'replier-name-link', 'replier-avatar-micro', 'user-info-trigger'); ?>
                                    </span>
                                </div>
                            <?php elseif($topic['reply_count'] == 0): ?>
                                <span class="stat-item last-reply-info">Henüz yorum yok</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Popover.js ve discussion.js zaten footer'da yüklü olduğu için çalışacaktır.
    // RGB Renkleri için CSS Değişkenlerini Tanımla
    const rootStyles = getComputedStyle(document.documentElement);
    const setRgbVar = (varName) => {
        const hexColor = rootStyles.getPropertyValue(`--${varName}`).trim();
        if (hexColor && hexColor.startsWith('#')) {
            let r, g, b;
            if (hexColor.length === 4) { 
                r = parseInt(hexColor[1] + hexColor[1], 16);
                g = parseInt(hexColor[2] + hexColor[2], 16);
                b = parseInt(hexColor[3] + hexColor[3], 16);
            } else if (hexColor.length === 7) { 
                r = parseInt(hexColor.slice(1, 3), 16);
                g = parseInt(hexColor.slice(3, 5), 16);
                b = parseInt(hexColor.slice(5, 7), 16);
            } else {
                return; 
            }
            document.documentElement.style.setProperty(`--${varName}-rgb`, `${r}, ${g}, ${b}`);
        }
    };
    ['darker-gold-2', 'darker-gold-1', 'grey', 'gold', 'charcoal', 'turquase', 'red', 'dark-red', 'light-turquase', 'transparent-turquase-2'].forEach(setRgbVar);
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
