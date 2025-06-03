<?php
// public/forum/topic.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(__DIR__)) . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = is_user_logged_in();
$is_approved = is_user_approved();

// Konu ID'sini al
$topic_id = (int)($_GET['id'] ?? 0);
if (!$topic_id) {
    header('Location: /public/forum/');
    exit;
}

// Sayfalama parametreleri
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // Konu detaylarını çek
    $topic_query = "
        SELECT ft.*, fc.name as category_name, fc.slug as category_slug, fc.color as category_color,
               u.username as author_username, u.id as author_user_id,
               ar.color as author_role_color
        FROM forum_topics ft
        JOIN forum_categories fc ON ft.category_id = fc.id
        JOIN users u ON ft.user_id = u.id
        LEFT JOIN (
            SELECT ur.user_id, r.color
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE r.priority = (
                SELECT MIN(r2.priority) 
                FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = ur.user_id
            )
        ) ar ON u.id = ar.user_id
        WHERE ft.id = :topic_id
    ";
    
    $stmt = execute_safe_query($pdo, $topic_query, [':topic_id' => $topic_id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        header('Location: /public/forum/');
        exit;
    }
    
    // Kategori erişim kontrolü
    $category = [
        'id' => $topic['category_id'],
        'visibility' => $topic['visibility'] ?? 'public'
    ];
    
    if (!can_user_access_forum_category($pdo, $category, $current_user_id)) {
        header('Location: /public/forum/');
        exit;
    }
    
    // Konu görüntüleme sayısını artır (sadece giriş yapmış kullanıcılar için)
    if ($current_user_id) {
        $update_views_query = "UPDATE forum_topics SET view_count = view_count + 1 WHERE id = :topic_id";
        execute_safe_query($pdo, $update_views_query, [':topic_id' => $topic_id]);
    }
    
    // Gönderileri çek
    $posts_query = "
        SELECT fp.*, u.username, u.id as user_id, u.avatar_path,
               ur_role.color as user_role_color,
               ur_role.name as user_role_name,
               (SELECT COUNT(*) FROM forum_post_likes fpl WHERE fpl.post_id = fp.id) as like_count,
               " . ($current_user_id ? "(SELECT COUNT(*) FROM forum_post_likes fpl WHERE fpl.post_id = fp.id AND fpl.user_id = $current_user_id) as user_liked" : "0 as user_liked") . "
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        LEFT JOIN (
            SELECT ur.user_id, r.color, r.name
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE r.priority = (
                SELECT MIN(r2.priority) 
                FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = ur.user_id
            )
        ) ur_role ON u.id = ur_role.user_id
        WHERE fp.topic_id = :topic_id
        ORDER BY fp.created_at ASC
        LIMIT :limit OFFSET :offset
    ";
    
    $posts_params = [
        ':topic_id' => $topic_id,
        ':limit' => $per_page,
        ':offset' => $offset
    ];
    
    $stmt = execute_safe_query($pdo, $posts_query, $posts_params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam gönderi sayısı
    $count_query = "SELECT COUNT(*) FROM forum_posts WHERE topic_id = :topic_id";
    $stmt = execute_safe_query($pdo, $count_query, [':topic_id' => $topic_id]);
    $total_posts = $stmt->fetchColumn();
    
    // Sayfa sayısını hesapla
    $total_pages = ceil($total_posts / $per_page);
    
    // Her gönderi için beğenen ilk 10 kişiyi al
    $post_likes = [];
    if (!empty($posts)) {
        $post_ids = array_column($posts, 'id');
        $in_clause = create_safe_in_clause($pdo, $post_ids, 'int');
        
        $likes_query = "
            SELECT fpl.post_id, u.username, u.id as user_id, ur_role.color as role_color
            FROM forum_post_likes fpl
            JOIN users u ON fpl.user_id = u.id
            LEFT JOIN (
                SELECT ur.user_id, r.color
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE r.priority = (
                    SELECT MIN(r2.priority) 
                    FROM user_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = ur.user_id
                )
            ) ur_role ON u.id = ur_role.user_id
            WHERE fpl.post_id " . $in_clause['placeholders'] . "
            ORDER BY fpl.liked_at ASC
        ";
        
        $stmt = execute_safe_query($pdo, $likes_query, $in_clause['params']);
        $all_likes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gönderi ID'sine göre gruplandir
        foreach ($all_likes as $like) {
            if (!isset($post_likes[$like['post_id']])) {
                $post_likes[$like['post_id']] = [];
            }
            $post_likes[$like['post_id']][] = $like;
        }
    }
    
    // Kullanıcı yetkilerini kontrol et
    $can_reply = $current_user_id && $is_approved && !$topic['is_locked'] && has_permission($pdo, 'forum.topic.reply', $current_user_id);
    $can_edit_topic = can_user_edit_content($pdo, 'forum_topic', $topic['user_id'], $current_user_id);
    $can_delete_topic = can_user_delete_content($pdo, 'forum_topic', $topic['user_id'], $current_user_id);
    $can_lock_topic = $current_user_id && has_permission($pdo, 'forum.topic.lock', $current_user_id);
    $can_pin_topic = $current_user_id && has_permission($pdo, 'forum.topic.pin', $current_user_id);
    
} catch (Exception $e) {
    error_log("Forum topic page error: " . $e->getMessage());
    header('Location: /public/forum/');
    exit;
}

// Sayfa başlığı
$page_title = htmlspecialchars($topic['title']) . " - Forum - Ilgarion Turanis";

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/public/forum/css/forum.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <nav class="forum-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/public/index.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="/public/forum/">
                    <i class="fas fa-comments"></i> Forum
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="/public/forum/category.php?slug=<?= urlencode($topic['category_slug']) ?>" 
                   style="color: <?= htmlspecialchars($topic['category_color']) ?>">
                    <?= htmlspecialchars($topic['category_name']) ?>
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-comment-dots"></i>
                <?= htmlspecialchars($topic['title']) ?>
            </li>
        </ol>
    </nav>

    <!-- Konu Header -->
    <div class="topic-header">
        <div class="topic-info">
            <div class="topic-status-icons">
                <?php if ($topic['is_pinned']): ?>
                    <i class="fas fa-thumbtack pinned-icon" title="Sabitlenmiş Konu"></i>
                <?php endif; ?>
                
                <?php if ($topic['is_locked']): ?>
                    <i class="fas fa-lock locked-icon" title="Kilitli Konu"></i>
                <?php endif; ?>
            </div>
            
            <div class="topic-details">
                <h1><?= htmlspecialchars($topic['title']) ?></h1>
                <div class="topic-meta">
                    <span class="topic-author">
                        <span class="user-link" data-user-id="<?= $topic['author_user_id'] ?>"
                              style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                            <?= htmlspecialchars($topic['author_username']) ?>
                        </span>
                    </span>
                    <span class="topic-date">
                        <?= format_time_ago($topic['created_at']) ?> tarihinde başlatıldı
                    </span>
                    <span class="topic-stats">
                        <i class="fas fa-eye"></i> <?= number_format($topic['view_count']) ?> görüntüleme
                        <i class="fas fa-comments"></i> <?= number_format($total_posts) ?> yanıt
                    </span>
                </div>
                
                <?php if ($topic['tags']): ?>
                    <div class="topic-tags">
                        <?php 
                        $tags = explode(',', $topic['tags']);
                        foreach ($tags as $tag): 
                            $tag = trim($tag);
                        ?>
                            <span class="topic-tag">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($tag) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="topic-actions">
            <?php if ($can_reply): ?>
                <a href="#reply-form" class="btn-reply">
                    <i class="fas fa-reply"></i> Yanıtla
                </a>
            <?php endif; ?>
            
            <?php if ($can_edit_topic || $can_delete_topic || $can_lock_topic || $can_pin_topic): ?>
                <div class="dropdown topic-admin-actions">
                    <button class="btn-admin-actions" data-dropdown="topic-admin">
                        <i class="fas fa-cog"></i> Yönet
                    </button>
                    <div class="dropdown-menu" id="topic-admin">
                        <?php if ($can_edit_topic): ?>
                            <a href="/public/forum/edit_topic.php?id=<?= $topic['id'] ?>" class="dropdown-item">
                                <i class="fas fa-edit"></i> Konuyu Düzenle
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($can_pin_topic): ?>
                            <button class="dropdown-item" onclick="toggleTopicPin(<?= $topic['id'] ?>, <?= $topic['is_pinned'] ? 'false' : 'true' ?>)">
                                <i class="fas fa-thumbtack"></i> 
                                <?= $topic['is_pinned'] ? 'Sabitlemeyi Kaldır' : 'Sabitle' ?>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($can_lock_topic): ?>
                            <button class="dropdown-item" onclick="toggleTopicLock(<?= $topic['id'] ?>, <?= $topic['is_locked'] ? 'false' : 'true' ?>)">
                                <i class="fas fa-lock"></i> 
                                <?= $topic['is_locked'] ? 'Kilidi Aç' : 'Kilitle' ?>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($can_delete_topic): ?>
                            <button class="dropdown-item delete-action" onclick="deleteTopicConfirm(<?= $topic['id'] ?>)">
                                <i class="fas fa-trash"></i> Konuyu Sil
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ana Gönderi (İlk Gönderi) -->
    <div class="topic-main-post">
        <div class="post-header">
            <div class="post-author-info">
                <div class="author-avatar">
                    <img src="<?= htmlspecialchars($topic['avatar_path'] ?? '/assets/logo.png') ?>" 
                         alt="<?= htmlspecialchars($topic['author_username']) ?> Avatarı"
                         class="avatar-img">
                </div>
                <div class="author-details">
                    <div class="author-name">
                        <span class="user-link" data-user-id="<?= $topic['author_user_id'] ?>"
                              style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                            <?= htmlspecialchars($topic['author_username']) ?>
                        </span>
                    </div>
                    <div class="author-role" style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                        <?= htmlspecialchars($topic['author_role_name'] ?? 'Üye') ?>
                    </div>
                </div>
            </div>
            <div class="post-date">
                <time datetime="<?= $topic['created_at'] ?>">
                    <?= format_time_ago($topic['created_at']) ?>
                </time>
            </div>
        </div>
        
        <div class="post-content">
            <div class="post-text">
                <?= nl2br(htmlspecialchars($topic['content'])) ?>
            </div>
        </div>
        
        <?php if ($topic['is_edited']): ?>
            <div class="post-edited-info">
                <i class="fas fa-edit"></i>
                Son düzenleme: <?= format_time_ago($topic['edited_at']) ?>
                <?php if ($topic['edited_by_user_id']): ?>
                    <span class="user-link" data-user-id="<?= $topic['edited_by_user_id'] ?>">
                        tarafından
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Yanıtlar -->
    <?php if (!empty($posts)): ?>
        <div class="topic-replies">
            <div class="replies-header">
                <h3>
                    <i class="fas fa-comments"></i>
                    Yanıtlar (<?= number_format($total_posts) ?>)
                </h3>
                <?php if ($total_pages > 1): ?>
                    <div class="replies-pagination-top">
                        <?php include 'includes/pagination.php'; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="posts-list">
                <?php foreach ($posts as $index => $post): ?>
                    <div class="post-item" id="post-<?= $post['id'] ?>">
                        <div class="post-number">
                            #<?= $offset + $index + 1 ?>
                        </div>
                        
                        <div class="post-header">
                            <div class="post-author-info">
                                <div class="author-avatar">
                                    <img src="<?= htmlspecialchars($post['avatar_path'] ?? '/assets/logo.png') ?>" 
                                         alt="<?= htmlspecialchars($post['username']) ?> Avatarı"
                                         class="avatar-img">
                                </div>
                                <div class="author-details">
                                    <div class="author-name">
                                        <span class="user-link" data-user-id="<?= $post['user_id'] ?>"
                                              style="color: <?= $post['user_role_color'] ?? '#bd912a' ?>">
                                            <?= htmlspecialchars($post['username']) ?>
                                        </span>
                                    </div>
                                    <div class="author-role" style="color: <?= $post['user_role_color'] ?? '#bd912a' ?>">
                                        <?= htmlspecialchars($post['user_role_name'] ?? 'Üye') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="post-date">
                                <time datetime="<?= $post['created_at'] ?>">
                                    <?= format_time_ago($post['created_at']) ?>
                                </time>
                                <a href="#post-<?= $post['id'] ?>" class="post-link" title="Bu gönderiye link">
                                    <i class="fas fa-link"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <div class="post-text">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                        </div>
                        
                        <?php if ($post['is_edited']): ?>
                            <div class="post-edited-info">
                                <i class="fas fa-edit"></i>
                                Son düzenleme: <?= format_time_ago($post['edited_at']) ?>
                                <?php if ($post['edited_by_user_id']): ?>
                                    <span class="user-link" data-user-id="<?= $post['edited_by_user_id'] ?>">
                                        tarafından
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-footer">
                            <div class="post-likes">
                                <?php if ($current_user_id && has_permission($pdo, 'forum.post.like', $current_user_id)): ?>
                                    <button class="like-btn <?= $post['user_liked'] ? 'liked' : '' ?>" 
                                            onclick="togglePostLike(<?= $post['id'] ?>)">
                                        <i class="fas fa-heart"></i>
                                        <span class="like-count"><?= $post['like_count'] ?></span>
                                    </button>
                                <?php else: ?>
                                    <span class="like-display">
                                        <i class="fas fa-heart"></i>
                                        <span class="like-count"><?= $post['like_count'] ?></span>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($post['like_count'] > 0 && isset($post_likes[$post['id']])): ?>
                                    <div class="like-details">
                                        <?php 
                                        $likes = $post_likes[$post['id']];
                                        $first_likes = array_slice($likes, 0, 10);
                                        $remaining_count = count($likes) - 10;
                                        ?>
                                        
                                        <span class="liked-by">
                                            <?php foreach ($first_likes as $i => $like): ?>
                                                <?php if ($i > 0) echo ', '; ?>
                                                <span class="user-link" data-user-id="<?= $like['user_id'] ?>"
                                                      style="color: <?= $like['role_color'] ?? '#bd912a' ?>">
                                                    <?= htmlspecialchars($like['username']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($remaining_count > 0): ?>
                                                ve <?= $remaining_count ?> kişi daha beğendi
                                            <?php elseif (count($first_likes) == 1): ?>
                                                beğendi
                                            <?php else: ?>
                                                beğendi
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <?php if ($can_reply): ?>
                                    <button class="post-action-btn quote-btn" onclick="quotePost(<?= $post['id'] ?>, '<?= htmlspecialchars($post['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-quote-right"></i> Alıntıla
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (can_user_edit_content($pdo, 'forum_post', $post['user_id'], $current_user_id)): ?>
                                    <a href="/public/forum/edit_post.php?id=<?= $post['id'] ?>" class="post-action-btn">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (can_user_delete_content($pdo, 'forum_post', $post['user_id'], $current_user_id)): ?>
                                    <button class="post-action-btn delete-action" onclick="deletePostConfirm(<?= $post['id'] ?>)">
                                        <i class="fas fa-trash"></i> Sil
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
        <div class="forum-pagination">
            <div class="pagination-info">
                Sayfa <?= $page ?> / <?= $total_pages ?> 
                (Toplam <?= number_format($total_posts) ?> yanıt)
            </div>
            
            <nav class="pagination-nav">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $topic['id'] ?>&page=1" class="page-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?id=<?= $topic['id'] ?>&page=<?= $page - 1 ?>" class="page-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?id=<?= $topic['id'] ?>&page=<?= $i ?>" 
                       class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?= $topic['id'] ?>&page=<?= $page + 1 ?>" class="page-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?id=<?= $topic['id'] ?>&page=<?= $total_pages ?>" class="page-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>

    <!-- Yanıt Formu -->
    <?php if ($can_reply): ?>
        <div class="reply-form-container" id="reply-form">
            <h3><i class="fas fa-reply"></i> Yanıt Yaz</h3>
            
            <form action="/public/forum/actions/submit_reply.php" method="POST" class="reply-form">
                <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-group">
                    <label for="reply_content">Yanıtınız:</label>
                    <textarea id="reply_content" name="content" required 
                              placeholder="Yanıtınızı buraya yazın..." 
                              rows="8" class="form-control"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Yanıtı Gönder
                    </button>
                    <button type="button" class="btn-preview" onclick="previewReply()">
                        <i class="fas fa-eye"></i> Önizleme
                    </button>
                </div>
            </form>
            
            <div id="reply-preview" class="reply-preview" style="display: none;">
                <h4>Önizleme:</h4>
                <div class="preview-content"></div>
            </div>
        </div>
    <?php elseif (!$is_logged_in): ?>
        <div class="login-prompt">
            <div class="prompt-content">
                <i class="fas fa-lock"></i>
                <h3>Yanıt yazmak için giriş yapın</h3>
                <p>Bu konuya yanıt vermek için hesabınıza giriş yapmanız gerekmektedir.</p>
                <div class="prompt-actions">
                    <a href="/public/register.php?mode=login" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </a>
                    <a href="/public/register.php" class="btn-secondary">
                        <i class="fas fa-user-plus"></i> Kayıt Ol
                    </a>
                </div>
            </div>
        </div>
    <?php elseif (!$is_approved): ?>
        <div class="approval-prompt">
            <div class="prompt-content">
                <i class="fas fa-clock"></i>
                <h3>Hesap onayı bekleniyor</h3>
                <p>Forumdaki konulara yanıt verebilmek için hesabınızın admin tarafından onaylanması gerekmektedir.</p>
            </div>
        </div>
    <?php elseif ($topic['is_locked']): ?>
        <div class="locked-prompt">
            <div class="prompt-content">
                <i class="fas fa-lock"></i>
                <h3>Konu kilitli</h3>
                <p>Bu konu yeni yanıtlara kapatılmıştır.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/public/forum/includes/user_popover.php'; ?>

<script src="/public/forum/js/forum.js"></script>
<script>
// Topic-specific JavaScript functions

function togglePostLike(postId) {
    fetch('/public/forum/actions/toggle_post_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `post_id=${postId}&csrf_token=<?= generate_csrf_token() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const likeBtn = document.querySelector(`button[onclick="togglePostLike(${postId})"]`);
            const likeCount = likeBtn.querySelector('.like-count');
            
            if (data.liked) {
                likeBtn.classList.add('liked');
            } else {
                likeBtn.classList.remove('liked');
            }
            
            likeCount.textContent = data.like_count;
            
            // Update like details if needed
            if (data.like_count > 0) {
                // Refresh page to show updated like details
                setTimeout(() => {
                    location.reload();
                }, 500);
            }
        } else {
            alert(data.message || 'Bir hata oluştu.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Bağlantı hatası oluştu.');
    });
}

function quotePost(postId, username) {
    const replyTextarea = document.getElementById('reply_content');
    if (replyTextarea) {
        const quoteText = `[quote="${username}"]...[/quote]\n\n`;
        replyTextarea.value += quoteText;
        replyTextarea.focus();
        
        // Scroll to reply form
        document.getElementById('reply-form').scrollIntoView({ 
            behavior: 'smooth' 
        });
    }
}

function previewReply() {
    const content = document.getElementById('reply_content').value;
    const previewDiv = document.getElementById('reply-preview');
    const previewContent = previewDiv.querySelector('.preview-content');
    
    if (content.trim()) {
        // Simple preview - convert newlines to <br>
        previewContent.innerHTML = content.replace(/\n/g, '<br>');
        previewDiv.style.display = 'block';
        
        // Scroll to preview
        previewDiv.scrollIntoView({ behavior: 'smooth' });
    } else {
        previewDiv.style.display = 'none';
    }
}

function toggleTopicPin(topicId, pinned) {
    if (confirm(pinned ? 'Bu konuyu sabitle?' : 'Bu konunun sabitlemesini kaldır?')) {
        fetch('/public/forum/actions/toggle_topic_pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `topic_id=${topicId}&pinned=${pinned}&csrf_token=<?= generate_csrf_token() ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Bir hata oluştu.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Bağlantı hatası oluştu.');
        });
    }
}

function toggleTopicLock(topicId, locked) {
    if (confirm(locked ? 'Bu konuyu kilitle?' : 'Bu konunun kilidini aç?')) {
        fetch('/public/forum/actions/toggle_topic_lock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `topic_id=${topicId}&locked=${locked}&csrf_token=<?= generate_csrf_token() ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Bir hata oluştu.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Bağlantı hatası oluştu.');
        });
    }
}

function deleteTopicConfirm(topicId) {
    if (confirm('Bu konuyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        if (confirm('Emin misiniz? Tüm yanıtlar da silinecektir.')) {
            fetch('/public/forum/actions/delete_topic.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `topic_id=${topicId}&csrf_token=<?= generate_csrf_token() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // window.location.href = data.redirect_url || '/public/forum/';
                } else {
                    alert(data.message || 'Bir hata oluştu.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası oluştu.');
            });
        }
    }
}

function deletePostConfirm(postId) {
    if (confirm('Bu göndeريyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        fetch('/public/forum/actions/delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `post_id=${postId}&csrf_token=<?= generate_csrf_token() ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Bir hata oluştu.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Bağlantı hatası oluştu.');
        });
    }
}

// Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdownButtons = document.querySelectorAll('[data-dropdown]');
    
    dropdownButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdownId = this.getAttribute('data-dropdown');
            const dropdown = document.getElementById(dropdownId);
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    });
});

// Auto-scroll to specific post if URL has hash
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const targetPost = document.querySelector(window.location.hash);
        if (targetPost) {
            setTimeout(() => {
                targetPost.scrollIntoView({ behavior: 'smooth' });
                targetPost.classList.add('highlighted');
                
                setTimeout(() => {
                    targetPost.classList.remove('highlighted');
                }, 3000);
            }, 500);
        }
    }
});
</script>

<?php
// Zaman formatlama fonksiyonu
function format_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    if ($time < 2592000) return floor($time/604800) . ' hafta önce';
    
    return date('d.m.Y H:i', strtotime($datetime));
}

include BASE_PATH . '/src/includes/footer.php';
?>