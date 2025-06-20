<?php
// /forum/topic.php - Tüm sorunları çözülmüş versiyon

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/htmlpurifier-4.15.0/library/HTMLPurifier.auto.php';

// Setup HTML Purifier for output sanitization
$output_config = HTMLPurifier_Config::createDefault();
$output_config->set('HTML.SafeIframe', true);
$output_config->set('URI.SafeIframeRegexp', '%^(https?://)?(www\.youtube(?:-nocookie)?\.com/embed/)%');
$purifier = new HTMLPurifier($output_config);

// Session kontrolü
check_user_session_validity();
check_forum_topic_likes_table($pdo);
// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = is_user_logged_in();
$is_approved = is_user_approved();

// Konu ID'sini al
$topic_id = (int) ($_GET['id'] ?? 0);
if (!$topic_id) {
    header('Location: forum/');
    exit;
}

// Konu detaylarını çek
$topic = get_forum_topic_by_id($pdo, $topic_id, $current_user_id);
if (!$topic) {
    $_SESSION['error_message'] = "Konu bulunamadı veya erişim yetkiniz bulunmuyor.";
    header('Location: forum/');
    exit;
}

// YENİ EKLEME - Konu beğeni verilerini al
try {
    $topic_like_data = get_topic_like_data($pdo, $topic_id, $current_user_id);
    
    $topic['like_count'] = $topic_like_data['like_count'] ?? 0;
    $topic['user_liked'] = $topic_like_data['user_liked'] ?? false;
    $topic['liked_users'] = $topic_like_data['liked_users'] ?? [];
    $topic['can_like'] = ($is_approved && 
                         $current_user_id && 
                         $topic['user_id'] != $current_user_id && 
                         has_permission($pdo, 'forum.post.like', $current_user_id));
} catch (Exception $e) {
    error_log("Topic like data error: " . $e->getMessage());
    $topic['like_count'] = 0;
    $topic['user_liked'] = false;
    $topic['liked_users'] = [];
    $topic['can_like'] = false;
}


// Sayfalama parametreleri
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Görüntüleme sayısını artır
increment_topic_view_count($pdo, $topic_id, $current_user_id);

// Gönderileri çek
$posts_data = get_forum_topic_posts($pdo, $topic_id, $current_user_id, $per_page, $offset);
$posts = $posts_data['posts'];
$total_posts = $posts_data['total'];

// Sayfa sayısını hesapla
$total_pages = ceil($total_posts / $per_page);

// Son sayfaya git özelliği
if (isset($_GET['page']) && $_GET['page'] === 'last') {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
    $posts_data = get_forum_topic_posts($pdo, $topic_id, $current_user_id, $per_page, $offset);
    $posts = $posts_data['posts'];
}

// Sayfa başlığı
$page_title = htmlspecialchars($topic['title']) . " - " . htmlspecialchars($topic['category_name']) . " - Forum - Ilgarion Turanis";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Forum', 'url' => '/forum/', 'icon' => 'fas fa-comments'],
    ['text' => $topic['category_name'], 'url' => 'category.php?slug=' . urlencode($topic['category_slug']), 'icon' => $topic['category_icon'] ?? 'fas fa-folder'],
    ['text' => $topic['title'], 'url' => '', 'icon' => 'fas fa-comment-dots']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="css/forum.css">
<link rel="stylesheet" href="css/topic.css">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <?= generate_forum_breadcrumb($breadcrumb_items) ?>

    <!-- Topic Header -->
    <div class="topic-header">
        <div class="topic-info">
            <div class="topic-title-section">
                <h1 class="topic-title">
                    <?php if ($topic['is_pinned']): ?>
                        <i class="fas fa-thumbtack pinned-icon" title="Sabitlenmiş Konu"></i>
                    <?php endif; ?>

                    <?php if ($topic['is_locked']): ?>
                        <i class="fas fa-lock locked-icon" title="Kilitli Konu"></i>
                    <?php endif; ?>

                    <?= htmlspecialchars($topic['title']) ?>
                </h1>
            </div>
            <div class="topic-stats">
                <div class="stat-item">
                    <div class="stat-number"><?= number_format($topic['reply_count']) ?></div>
                    <div class="stat-label">Yanıt</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= number_format($topic['view_count']) ?></div>
                    <div class="stat-label">Görüntüleme</div>
                </div>
            </div>
        </div>

        <div class="topic-actions">
            <?php if ($topic['can_reply']): ?>
                <a href="#reply-form" class="btn-reply">
                    <i class="fas fa-reply"></i> Yanıtla
                </a>
            <?php endif; ?>

           <?php if ($topic['can_edit']): ?>
   <a href="create_topic.php?edit=<?= $topic_id ?>" class="btn-edit">
       <i class="fas fa-edit"></i> Düzenle
   </a>
<?php endif; ?>

            <?php if ($topic['can_pin']): ?>
                <button class="btn-pin <?= $topic['is_pinned'] ? 'active' : '' ?>"
                    onclick="toggleTopicPin(<?= $topic_id ?>, <?= $topic['is_pinned'] ? 'false' : 'true' ?>)"
                    data-topic-id="<?= $topic_id ?>">
                    <i class="fas fa-thumbtack"></i>
                    <?= $topic['is_pinned'] ? 'Sabitlemeyi Kaldır' : 'Sabitle' ?>
                </button>
            <?php endif; ?>

            <?php if ($topic['can_lock']): ?>
                <button class="btn-lock <?= $topic['is_locked'] ? 'active' : '' ?>"
                    onclick="toggleTopicLock(<?= $topic_id ?>, <?= $topic['is_locked'] ? 'false' : 'true' ?>)"
                    data-topic-id="<?= $topic_id ?>">
                    <i class="fas fa-lock"></i>
                    <?= $topic['is_locked'] ? 'Kilidi Aç' : 'Kilitle' ?>
                </button>
            <?php endif; ?>

            <?php if ($topic['can_delete']): ?>
                <button class="btn-delete" onclick="confirmTopicDelete(<?= $topic_id ?>)">
                    <i class="fas fa-trash"></i> Sil
                </button>
            <?php endif; ?>
        </div>
        <br>
    </div>
<?php if (!empty($topic['tags'])): ?>
            <div class="topic-tags-section">
                <div class="topic-tags">
                    <i class="fas fa-tags"></i>
                    <div class="tags-list">
                        <?php 
                        $tags = array_filter(array_map('trim', explode(',', $topic['tags'])));
                        foreach ($tags as $tag): 
                        ?>
                            <a href="search.php?q=<?= urlencode($tag) ?>&type=tags" 
                               class="topic-tag" 
                               title="'<?= htmlspecialchars($tag) ?>' etiketini ara">
                                <?= htmlspecialchars($tag) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <!-- Topic Content (First Post) -->
<div class="topic-content-wrapper">
    <div class="topic-first-post post-item" id="post-0" data-topic-id="<?= $topic_id ?>">
        <div class="post-author">
            <div class="author-avatar">
                <img src="<?= fix_avatar_path($topic['author_avatar']) ?>"
                    alt="<?= htmlspecialchars($topic['author_username']) ?> Avatarı" class="avatar-img">
            </div>
            <div class="author-info">
                <div class="author-name">
                    <span class="user-link" data-user-id="<?= $topic['user_id'] ?>"
                        style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                        <?= htmlspecialchars($topic['author_username']) ?>
                    </span>
                </div>
                <div class="author-role" style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                    <?= htmlspecialchars($topic['author_role_name'] ?? 'Üye') ?>
                </div>
                <div class="author-join-date">
                    Üyelik: <?= date('M Y', strtotime($topic['created_at'])) ?>
                </div>
            </div>
        </div>

        <div class="post-content">
            <div class="post-body" id="post-content-topic-<?= $topic['id'] ?>">
                <?php echo $purifier->purify($topic['content']); ?>
            </div>

            <div class="post-footer">
                <div class="post-meta-section">
                    <div class="post-date">
                        <i class="fas fa-clock"></i>
                        <?= format_time_ago($topic['created_at']) ?>
                        <?php if (isset($topic['updated_at']) && $topic['created_at'] !== $topic['updated_at']): ?>
                            <i class="fas fa-edit"></i>
                            <em>Son düzenleme: <?= format_time_ago($topic['updated_at']) ?></em>
                        <?php endif; ?>
                    </div>

                    <div class="post-actions">
                        <?php if ($topic['can_edit']): ?>
                            <button class="post-action-btn edit" onclick="showInlineEditor('topic', <?= $topic['id'] ?>)">
                                <i class="fas fa-edit"></i> Düzenle
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- BEĞENİ SİSTEMİ BÖLÜMÜ - GÜVENLİ VERSİYON -->
                <div class="post-reactions-section">
                    <div class="post-reactions">
                        <?php 
                        $like_count = $topic['like_count'] ?? 0;
                        $user_liked = $topic['user_liked'] ?? false;
                        $can_like = $topic['can_like'] ?? false;
                        ?>
                        
                        <?php if ($can_like): ?>
                            <button class="post-like-btn topic-like-btn <?= $user_liked ? 'liked' : '' ?>"
                                onclick="toggleTopicLike(<?= $topic_id ?>)" 
                                data-topic-id="<?= $topic_id ?>"
                                data-like-count="<?= $like_count ?>">
                                <i class="fas fa-heart"></i>
                                <span class="like-count"><?= $like_count ?></span>
                            </button>
                        <?php elseif ($like_count > 0): ?>
                            <span class="post-like-display topic-like-display">
                                <i class="fas fa-heart"></i>
                                <span class="like-count"><?= $like_count ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- BEĞENEN KULLANICILAR LİSTESİ -->
                    
                </div>
            </div>
        </div>
    </div>
    <?php 
                    $liked_users = $topic['liked_users'] ?? [];
                    if (!empty($liked_users) && $like_count > 0): 
                    ?>
                        <div class="liked-users-section">
                            <div class="liked-users-toggle" onclick="toggleLikedUsers('topic-<?= $topic_id ?>')">
                                <i class="fas fa-users"></i>
                                <span><?= $like_count ?> kişi beğendi</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="liked-users-list" id="liked-users-topic-<?= $topic_id ?>" style="display: none;">
                                <?php foreach ($liked_users as $user): ?>
                                    <div class="liked-user-item">
                                        <span class="user-link" data-user-id="<?= $user['id'] ?>"
                                            style="color: <?= $user['role_color'] ?? '#bd912a' ?>">
                                            <?= htmlspecialchars($user['username']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($like_count > 20): ?>
                                    <div class="liked-users-more">
                                        ve <?= $like_count - 20 ?> kişi daha...
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
</div>

    <!-- Posts List -->
    <?php if (!empty($posts)): ?>
        <div class="posts-list">
            <div class="posts-header">
                <h3><i class="fas fa-comments"></i> Yanıtlar</h3>
                <div class="posts-count">
                    <?= number_format($total_posts) ?> yanıt
                </div>
            </div>

            <?php foreach ($posts as $index => $post): ?>
                <div class="post-item" id="post-<?= $post['id'] ?>">
                    <div class="post-author">
                        <div class="author-avatar">
                            <img src="<?= fix_avatar_path($post['avatar_path']) ?>"
                                alt="<?= htmlspecialchars($post['username']) ?> Avatarı" class="avatar-img">
                        </div>
                        <div class="author-info">
                            <div class="author-name">
                                <span class="user-link" data-user-id="<?= $post['user_id'] ?>"
                                    style="color: <?= $post['user_role_color'] ?? '#bd912a' ?>">
                                    <?= htmlspecialchars($post['username']) ?>
                                </span>
                            </div>
                            <div class="author-role" style="color: <?= $post['user_role_color'] ?? '#bd912a' ?>">
                                <?= htmlspecialchars($post['user_role_name'] ?? 'Üye') ?>
                            </div>
                            <div class="author-join-date">
                                Üyelik: <?= date('M Y', strtotime($post['user_join_date'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="post-content">
                        <div class="post-body" id="post-content-post-<?= $post['id'] ?>">
                            <?php echo $purifier->purify($post['content']); ?>

                            
                        </div>

                        <div class="post-footer">
                            <div class="post-date">
                                <i class="fas fa-clock"></i>
                                <?= format_time_ago($post['created_at']) ?>
                                <?php if ($post['is_edited']): ?>
                                <div class="post-edited-info">
                                    <i class="fas fa-edit"></i>
                                    <em>Son düzenleme: <?= format_time_ago($post['edited_at']) ?></em>
                                </div>
                            <?php endif; ?>
                            </div>



                            <div class="post-actions">
                                <div class="post-reactions">
                                    <?php if ($post['can_like']): ?>
                                        <button class="post-like-btn <?= $post['user_liked'] ? 'liked' : '' ?>"
                                            onclick="togglePostLike(<?= $post['id'] ?>)" data-post-id="<?= $post['id'] ?>">
                                            <i class="fas fa-heart"></i>
                                            <span class="like-count"><?= $post['like_count'] ?></span>
                                        </button>
                                    <?php elseif ($post['like_count'] > 0): ?>
                                        <span class="post-like-display">
                                            <i class="fas fa-heart"></i>
                                            <span class="like-count"><?= $post['like_count'] ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($post['can_edit']): ?>
                                    <button class="post-action-btn edit" onclick="showInlineEditor('post', <?= $post['id'] ?>)">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </button>
                                <?php endif; ?>
                                <?php if ($post['can_delete']): ?>
                                    <button class="post-action-btn delete" onclick="confirmPostDelete(<?= $post['id'] ?>)">
                                        <i class="fas fa-trash"></i> Sil
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="forum-pagination">
            <div class="pagination-info">
                Sayfa <?= $page ?> / <?= $total_pages ?>
                (Toplam <?= number_format($total_posts) ?> yanıt)
            </div>

            <nav class="pagination-nav">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $topic_id ?>&page=1" class="page-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?id=<?= $topic_id ?>&page=<?= $page - 1 ?>" class="page-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?id=<?= $topic_id ?>&page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?= $topic_id ?>&page=<?= $page + 1 ?>" class="page-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?id=<?= $topic_id ?>&page=<?= $total_pages ?>" class="page-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>

    <!-- Reply Form -->
    <?php if ($topic['can_reply']): ?>
        <div class="reply-form-section" id="reply-form">
            <h3><i class="fas fa-reply"></i> Yanıt Yaz</h3>

            <form id="replyForm" action="actions/submit_reply.php" method="POST" data-total-posts="<?= $total_posts ?? 0 ?>">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="topic_id" value="<?= $topic_id ?>">

                <div class="form-group">
                    <label for="reply_content">Yanıt İçeriği:</label>
                    <?php
                    // Set variables for the editor
                    $textarea_name = 'content';
                    $initial_content = ''; // Start with an empty editor for replies
                    
                    // Include the WYSIWYG editor
                    // Note: The editor is in the root 'editor' directory.
                    // topic.php is in '/forum', so we use BASE_PATH for a reliable include.
                    require BASE_PATH . '/editor/wysiwyg_editor.php';
                    ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Yanıtı Gönder
                    </button>
                </div>
            </form>

        </div>
    <?php elseif (!$is_logged_in): ?>
        <div class="reply-form-section">
            <div class="login-required">
                <i class="fas fa-sign-in-alt"></i>
                <h3>Yanıt yazmak için giriş yapın</h3>
                <p>Bu konuya yanıt vermek için hesabınızla giriş yapmanız gerekmektedir.</p>
                <div class="login-actions">
                    <a href="register.php?mode=login" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </a>
                    <a href="register.php" class="btn-secondary">
                        <i class="fas fa-user-plus"></i> Kayıt Ol
                    </a>
                </div>
            </div>
        </div>
    <?php elseif (!$is_approved): ?>
        <div class="reply-form-section">
            <div class="approval-required">
                <i class="fas fa-clock"></i>
                <h3>Hesap onayı bekleniyor</h3>
                <p>Bu konuya yanıt vermek için hesabınızın admin tarafından onaylanması gerekmektedir.</p>
            </div>
        </div>
    <?php elseif ($topic['is_locked']): ?>
        <div class="reply-form-section">
            <div class="topic-locked">
                <i class="fas fa-lock"></i>
                <h3>Konu kilitli</h3>
                <p>Bu konu yeni yanıtlara kapatılmıştır.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden Editor Template for Inline Editing -->
<div id="editor-template" style="display: none;">
    <?php require BASE_PATH . '/editor/wysiwyg_editor.php'; ?>
    <div class="inline-editor-actions" style="display: flex; justify-content: flex-end; gap: 0.75rem; padding: 0.75rem; background-color: var(--card-bg, #1D1A18); border-top: 1px solid var(--border-1, #59524c46); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
        <button type="button" class="btn btn-secondary btn-cancel-edit">İptal</button>
        <button type="button" class="btn btn-primary btn-save-edit">Kaydet</button>
    </div>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>

<script src="js/forum.js"></script>
<script src="js/topic.js"></script>

<?php
// Time formatting function for this page
function format_time_ago($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60)
        return 'Az önce';
    if ($time < 3600)
        return floor($time / 60) . ' dakika önce';
    if ($time < 86400)
        return floor($time / 3600) . ' saat önce';
    if ($time < 604800)
        return floor($time / 86400) . ' gün önce';
    if ($time < 2592000)
        return floor($time / 604800) . ' hafta önce';

    return date('d.m.Y H:i', strtotime($datetime));
}

include BASE_PATH . '/src/includes/footer.php';
include BASE_PATH . '/src/functions/Parsedown.php';
?>