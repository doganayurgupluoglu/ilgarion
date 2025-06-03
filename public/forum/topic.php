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

// Konu detaylarını çek
$topic = get_forum_topic_by_id($pdo, $topic_id, $current_user_id);
if (!$topic) {
    $_SESSION['error_message'] = "Konu bulunamadı veya erişim yetkiniz bulunmuyor.";
    header('Location: /public/forum/');
    exit;
}

// Sayfalama parametreleri
$page = max(1, (int)($_GET['page'] ?? 1));
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
    ['text' => 'Ana Sayfa', 'url' => '/public/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Forum', 'url' => '/public/forum/', 'icon' => 'fas fa-comments'],
    ['text' => $topic['category_name'], 'url' => '/public/forum/category.php?slug=' . urlencode($topic['category_slug']), 'icon' => $topic['category_icon'] ?? 'fas fa-folder'],
    ['text' => $topic['title'], 'url' => '', 'icon' => 'fas fa-comment-dots']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/public/forum/css/forum.css">
<link rel="stylesheet" href="/public/forum/css/topic.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
                
                <div class="topic-meta">
                    <span class="topic-author">
                        <span class="user-link" data-user-id="<?= $topic['user_id'] ?>"
                              style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                            <?= htmlspecialchars($topic['author_username']) ?>
                        </span>
                    </span>
                    <span class="topic-date"><?= format_time_ago($topic['created_at']) ?></span>
                    
                    <?php if ($topic['visibility'] !== 'public'): ?>
                        <span class="topic-visibility">
                            <?php
                            $visibility_badges = [
                                'members_only' => '<i class="fas fa-users"></i> Sadece Üyeler',
                                'faction_only' => '<i class="fas fa-shield-alt"></i> Fraksiyona Özel'
                            ];
                            echo $visibility_badges[$topic['visibility']] ?? '';
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
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
                <a href="/public/forum/edit_topic.php?id=<?= $topic_id ?>" class="btn-edit">
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
    </div>

    <!-- Topic Content (First Post) -->
    <div class="topic-content-wrapper">
        <div class="topic-first-post post-item" id="post-0">
            <div class="post-author">
                <div class="author-avatar">
                    <img src="<?= htmlspecialchars($topic['author_avatar'] ?? '/assets/logo.png') ?>" 
                         alt="<?= htmlspecialchars($topic['author_username']) ?> Avatarı" 
                         class="avatar-img">
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
                <div class="post-body">
                    <?= parse_bbcode($topic['content']) ?>
                </div>
                
                <div class="post-footer">
                    <div class="post-date">
                        <i class="fas fa-clock"></i>
                        <?= format_time_ago($topic['created_at']) ?>
                    </div>
                    
                    <div class="post-actions">
                        <?php if ($topic['can_edit']): ?>
                            <button class="post-action-btn" onclick="editPost(0, 'topic')">
                                <i class="fas fa-edit"></i> Düzenle
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($is_approved): ?>
                            <button class="post-action-btn" onclick="quotePost('<?= htmlspecialchars($topic['author_username']) ?>', '<?= htmlspecialchars(strip_tags($topic['content'])) ?>')">
                                <i class="fas fa-quote-left"></i> Alıntıla
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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
                            <img src="<?= htmlspecialchars($post['avatar_path'] ?? '/assets/logo.png') ?>" 
                                 alt="<?= htmlspecialchars($post['username']) ?> Avatarı" 
                                 class="avatar-img">
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
                        <div class="post-body">
                            <?= parse_bbcode($post['content']) ?>
                            
                            <?php if ($post['is_edited']): ?>
                                <div class="post-edited-info">
                                    <i class="fas fa-edit"></i>
                                    <em>Son düzenleme: <?= format_time_ago($post['edited_at']) ?></em>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="post-footer">
                            <div class="post-date">
                                <i class="fas fa-clock"></i>
                                <?= format_time_ago($post['created_at']) ?>
                            </div>
                            
                            <div class="post-reactions">
                                <?php if ($post['can_like']): ?>
                                    <button class="post-like-btn <?= $post['user_liked'] ? 'liked' : '' ?>" 
                                            onclick="togglePostLike(<?= $post['id'] ?>)"
                                            data-post-id="<?= $post['id'] ?>">
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
                            
                            <div class="post-actions">
                                <?php if ($post['can_edit']): ?>
                                    <button class="post-action-btn" onclick="editPost(<?= $post['id'] ?>, 'post')">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($is_approved): ?>
                                    <button class="post-action-btn" onclick="quotePost('<?= htmlspecialchars($post['username']) ?>', '<?= htmlspecialchars(strip_tags($post['content'])) ?>')">
                                        <i class="fas fa-quote-left"></i> Alıntıla
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
                    <a href="?id=<?= $topic_id ?>&page=<?= $i ?>" 
                       class="page-btn <?= $i === $page ? 'active' : '' ?>">
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
            
            <form id="replyForm" action="/public/forum/actions/submit_reply.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="topic_id" value="<?= $topic_id ?>">
                
                <div class="form-group">
                    <label for="reply_content">Yanıt İçeriği:</label>
                    <div class="editor-toolbar">
                        <button type="button" class="editor-btn" onclick="insertBBCode('b')" title="Kalın">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('i')" title="İtalik">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('u')" title="Altı Çizili">
                            <i class="fas fa-underline"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('url')" title="Link">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('quote')" title="Alıntı">
                            <i class="fas fa-quote-left"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('code')" title="Kod">
                            <i class="fas fa-code"></i>
                        </button>
                    </div>
                    <textarea name="content" id="reply_content" rows="8" required 
                              placeholder="Yanıtınızı buraya yazın... BBCode kullanabilirsiniz."
                              minlength="5" maxlength="10000"></textarea>
                    <div class="char-counter">
                        <span id="char-count">0</span> / 10000 karakter
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Yanıtı Gönder
                    </button>
                    <button type="button" class="btn-preview" onclick="previewReply()">
                        <i class="fas fa-eye"></i> Önizle
                    </button>
                </div>
            </form>
            
            <div id="reply-preview" class="reply-preview" style="display: none;">
                <h4><i class="fas fa-eye"></i> Önizleme</h4>
                <div class="preview-content"></div>
            </div>
        </div>
    <?php elseif (!$is_logged_in): ?>
        <div class="reply-form-section">
            <div class="login-required">
                <i class="fas fa-sign-in-alt"></i>
                <h3>Yanıt yazmak için giriş yapın</h3>
                <p>Bu konuya yanıt vermek için hesabınızla giriş yapmanız gerekmektedir.</p>
                <div class="login-actions">
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

<!-- User Popover Include -->
<?php include BASE_PATH . '/public/forum/includes/user_popover.php'; ?>


<script>
// Topic functionality
let csrfToken = '<?= generate_csrf_token() ?>';

// Character counter for reply form
document.addEventListener('DOMContentLoaded', function() {
    const replyContent = document.getElementById('reply_content');
    const charCount = document.getElementById('char-count');
    
    if (replyContent && charCount) {
        replyContent.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            if (this.value.length > 9500) {
                charCount.style.color = 'var(--red)';
            } else if (this.value.length > 8000) {
                charCount.style.color = 'var(--gold)';
            } else {
                charCount.style.color = 'var(--light-grey)';
            }
        });
    }
});

// BBCode editor functions
function insertBBCode(tag) {
    const textarea = document.getElementById('reply_content');
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    let replacement;
    
    switch(tag) {
        case 'url':
            const url = prompt('URL girin:');
            if (url) {
                replacement = selectedText ? `[url=${url}]${selectedText}[/url]` : `[url=${url}][/url]`;
            } else {
                return;
            }
            break;
        default:
            replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Move cursor
    const newPos = start + replacement.length - (selectedText ? 0 : `[/${tag}]`.length);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    // Update character count
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Quote post function
function quotePost(username, content) {
    const textarea = document.getElementById('reply_content');
    if (!textarea) return;
    
    // Clean content for quoting
    const cleanContent = content.substring(0, 200) + (content.length > 200 ? '...' : '');
    const quote = `[quote=${username}]${cleanContent}[/quote]\n\n`;
    
    const currentPos = textarea.value.length;
    textarea.value += quote;
    textarea.setSelectionRange(currentPos + quote.length, currentPos + quote.length);
    textarea.focus();
    
    // Scroll to reply form
    document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
    
    // Update character count
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Preview reply function
function previewReply() {
    const content = document.getElementById('reply_content').value;
    const previewDiv = document.getElementById('reply-preview');
    const previewContent = previewDiv.querySelector('.preview-content');
    
    if (!content.trim()) {
        alert('Önizlemek için içerik girin.');
        return;
    }
    
    // Simple BBCode to HTML conversion for preview
    let html = content
        .replace(/\[b\](.*?)\[\/b\]/g, '<strong>$1</strong>')
        .replace(/\[i\](.*?)\[\/i\]/g, '<em>$1</em>')
        .replace(/\[u\](.*?)\[\/u\]/g, '<u>$1</u>')
        .replace(/\[s\](.*?)\[\/s\]/g, '<del>$1</del>')
        .replace(/\[url=(.*?)\](.*?)\[\/url\]/g, '<a href="$1" target="_blank">$2</a>')
        .replace(/\[url\](.*?)\[\/url\]/g, '<a href="$1" target="_blank">$1</a>')
        .replace(/\[quote=(.*?)\](.*?)\[\/quote\]/g, '<blockquote class="forum-quote"><cite>$1 yazdı:</cite>$2</blockquote>')
        .replace(/\[quote\](.*?)\[\/quote\]/g, '<blockquote class="forum-quote">$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/g, '<pre class="forum-code">$1</pre>')
        .replace(/\n/g, '<br>');
    
    previewContent.innerHTML = html;
    previewDiv.style.display = 'block';
    previewDiv.scrollIntoView({ behavior: 'smooth' });
}

// Toggle topic pin
async function toggleTopicPin(topicId, pinned) {
    try {
        const response = await fetch('/public/forum/actions/toggle_topic_pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&topic_id=${topicId}&pinned=${pinned}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const btn = document.querySelector('.btn-pin');
            if (btn) {
                if (data.pinned) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-thumbtack"></i> Sabitlemeyi Kaldır';
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-thumbtack"></i> Sabitle';
                }
                btn.onclick = () => toggleTopicPin(topicId, !data.pinned);
            }
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Pin toggle error:', error);
        showNotification('Bir hata oluştu.', 'error');
    }
}

// Toggle topic lock
async function toggleTopicLock(topicId, locked) {
    try {
        const response = await fetch('/public/forum/actions/toggle_topic_lock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&topic_id=${topicId}&locked=${locked}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const btn = document.querySelector('.btn-lock');
            if (btn) {
                if (data.locked) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-lock"></i> Kilidi Aç';
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-lock"></i> Kilitle';
                }
                btn.onclick = () => toggleTopicLock(topicId, !data.locked);
            }
            
            showNotification(data.message, 'success');
            
            // Refresh page if topic was locked to update reply form
            if (data.locked) {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Lock toggle error:', error);
        showNotification('Bir hata oluştu.', 'error');
    }
}

// Toggle post like
async function togglePostLike(postId) {
    try {
        const response = await fetch('/public/forum/actions/toggle_post_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&post_id=${postId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const btn = document.querySelector(`[data-post-id="${postId}"]`);
            const likeCount = btn.querySelector('.like-count');
            
            if (data.liked) {
                btn.classList.add('liked');
            } else {
                btn.classList.remove('liked');
            }
            
            likeCount.textContent = data.like_count;
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Like toggle error:', error);
        showNotification('Bir hata oluştu.', 'error');
    }
}

// Confirm topic delete
function confirmTopicDelete(topicId) {
    if (confirm('Bu konuyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yanıtlar da silinecektir.')) {
        // Implement topic deletion
        showNotification('Konu silme özelliği henüz aktif değil.', 'info');
    }
}

// Confirm post delete
function confirmPostDelete(postId) {
    if (confirm('Bu gönderiyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        // Implement post deletion
        showNotification('Gönderi silme özelliği henüz aktif değil.', 'info');
    }
}

// Edit post function
function editPost(postId, type) {
    showNotification('Düzenleme özelliği henüz aktif değil.', 'info');
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const replyForm = document.getElementById('replyForm');
    
    if (replyForm) {
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // Redirect to the new post
                    if (data.redirect_url) {
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 1000);
                    }
                } else {
                    showNotification(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Reply submission error:', error);
                showNotification('Yanıt gönderilirken bir hata oluştu.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});

// Notification function
function showNotification(message, type) {
    // Use the forum manager's notification system if available
    if (window.forumManager) {
        window.forumManager.showNotification(message, type);
    } else {
        // Fallback notification
        alert(message);
    }
}
</script>

<script src="/public/forum/js/forum.js"></script>

<?php
// Time formatting function for this page
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