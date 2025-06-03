<?php
// public/forum/topic.php - Forum Konu Detay Sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';

// Oturum kontrolü (opsiyonel - misafirler de görebilir)
if (is_user_logged_in()) {
    check_user_session_validity();
}

$current_user_id = $_SESSION['user_id'] ?? null;

// Konu ID'sini al ve validate et
$topic_id = $_GET['id'] ?? null;

if (!$topic_id || !is_numeric($topic_id)) {
    $_SESSION['error_message'] = "Geçersiz konu ID'si.";
    header('Location: ' . get_auth_base_url() . '/forum/index.php');
    exit;
}

$topic_id = (int)$topic_id;

// Konu detaylarını al
$topic = get_forum_topic_details($pdo, $topic_id, $current_user_id);

if (!$topic) {
    $_SESSION['error_message'] = "Konu bulunamadı veya erişim yetkiniz bulunmuyor.";
    header('Location: ' . get_auth_base_url() . '/forum/index.php');
    exit;
}

// Görüntülenme sayısını artır (sadece farklı kullanıcılar için)


// Sayfalama parametreleri
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Gönderileri getir
$posts_data = get_forum_posts_in_topic($pdo, $topic_id, $current_user_id, $per_page, $offset);
$posts = $posts_data['posts'];
$total_posts = $posts_data['total'];
$total_pages = ceil($total_posts / $per_page);

// Kullanıcının yetkilerini kontrol et
$can_reply = can_user_reply_to_topic($pdo, $topic_id, $current_user_id);
$can_moderate = $current_user_id && has_permission($pdo, 'forum.moderate', $current_user_id);

$page_title = htmlspecialchars($topic['title']) . " - " . htmlspecialchars($topic['category_name']) . " - Forum - Ilgarion Turanis";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.topic-page {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.breadcrumb {
    background: rgba(34, 34, 34, 0.6);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 2rem;
    font-size: 0.9rem;
}

.breadcrumb-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
}

.breadcrumb-item:not(:last-child)::after {
    content: '›';
    margin-left: 0.75rem;
    color: var(--gold);
    font-weight: bold;
}

.breadcrumb-link {
    color: var(--turquase);
    text-decoration: none;
    transition: color 0.2s ease;
}

.breadcrumb-link:hover {
    color: var(--light-gold);
}

.breadcrumb-current {
    color: var(--lighter-grey);
    font-weight: 500;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.topic-header {
    background: linear-gradient(135deg, rgba(34, 34, 34, 0.9), rgba(42, 42, 42, 0.9));
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.topic-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(189, 145, 42, 0.05), transparent);
    pointer-events: none;
}

.topic-header-content {
    position: relative;
    z-index: 2;
}

.topic-status-badges {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-pinned {
    background: rgba(189, 145, 42, 0.2);
    color: var(--gold);
    border: 1px solid rgba(189, 145, 42, 0.4);
}

.badge-locked {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
    border: 1px solid rgba(231, 76, 60, 0.4);
}

.badge-visibility {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    border: 1px solid rgba(52, 152, 219, 0.4);
}

.topic-title {
    color: var(--gold);
    font-size: 2rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    line-height: 1.3;
    word-wrap: break-word;
}

.topic-meta {
    display: flex;
    align-items: center;
    gap: 2rem;
    color: var(--light-grey);
    font-size: 0.9rem;
    flex-wrap: wrap;
}

.topic-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.topic-author-link {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 600;
}

.topic-author-link:hover {
    color: var(--light-gold);
}

.topic-stats {
    display: flex;
    gap: 1.5rem;
}

.topic-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.btn-reply {
    background: linear-gradient(135deg, var(--turquase) 0%, #16a085 100%);
    color: var(--white);
}

.btn-reply:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(42, 189, 168, 0.3);
}

.btn-edit {
    background: rgba(52, 152, 219, 0.8);
    color: var(--white);
}

.btn-edit:hover {
    background: #3498db;
    transform: translateY(-1px);
}

.btn-delete {
    background: rgba(231, 76, 60, 0.8);
    color: var(--white);
}

.btn-delete:hover {
    background: #e74c3c;
    transform: translateY(-1px);
}

.btn-moderate {
    background: rgba(155, 89, 182, 0.8);
    color: var(--white);
}

.btn-moderate:hover {
    background: #9b59b6;
    transform: translateY(-1px);
}

.topic-navigation {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.9rem;
}

.nav-info {
    color: var(--light-grey);
}

.posts-container {
    margin-bottom: 2rem;
}

.post-item {
    background: rgba(34, 34, 34, 0.8);
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    margin-bottom: 1.5rem;
    overflow: hidden;
    position: relative;
}

.post-item:target {
    border-color: var(--gold);
    box-shadow: 0 0 20px rgba(189, 145, 42, 0.3);
}

.post-header {
    background: rgba(26, 26, 26, 0.6);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.post-author-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.post-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid var(--gold);
    object-fit: cover;
    flex-shrink: 0;
}

.post-author-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.post-author-name {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
}

.post-author-name:hover {
    color: var(--light-gold);
}

.post-author-role {
    color: var(--light-grey);
    font-size: 0.75rem;
    font-style: italic;
}

.post-meta {
    text-align: right;
    font-size: 0.85rem;
    color: var(--light-grey);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-end;
}

.post-number {
    color: var(--gold);
    font-weight: 600;
}

.post-content {
    padding: 1.5rem;
    line-height: 1.7;
    word-wrap: break-word;
}

.post-content p {
    margin: 0 0 1rem 0;
}

.post-content p:last-child {
    margin-bottom: 0;
}

.post-content blockquote {
    border-left: 4px solid var(--gold);
    background: rgba(189, 145, 42, 0.1);
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0 8px 8px 0;
    color: var(--light-grey);
    font-style: italic;
}

.post-content code {
    background: rgba(66, 66, 66, 0.8);
    border: 1px solid var(--darker-gold-2);
    border-radius: 4px;
    padding: 0.2rem 0.4rem;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    color: var(--turquase);
}

.post-content pre {
    background: rgba(26, 26, 26, 0.8);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1rem;
    overflow-x: auto;
    margin: 1rem 0;
}

.post-content pre code {
    background: none;
    border: none;
    padding: 0;
    color: var(--lighter-grey);
}

.post-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--darker-gold-2);
    background: rgba(26, 26, 26, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.post-edited-info {
    color: var(--light-grey);
    font-size: 0.8rem;
    font-style: italic;
}

.post-actions {
    display: flex;
    gap: 0.5rem;
}

.post-action-btn {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.post-action-btn:hover {
    transform: translateY(-1px);
}

.btn-quote {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    border-color: rgba(52, 152, 219, 0.3);
}

.btn-quote:hover {
    background: rgba(52, 152, 219, 0.3);
}

.btn-edit-post {
    background: rgba(241, 196, 15, 0.2);
    color: #f1c40f;
    border-color: rgba(241, 196, 15, 0.3);
}

.btn-edit-post:hover {
    background: rgba(241, 196, 15, 0.3);
}

.btn-delete-post {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
    border-color: rgba(231, 76, 60, 0.3);
}

.btn-delete-post:hover {
    background: rgba(231, 76, 60, 0.3);
}

.reply-form {
    background: rgba(34, 34, 34, 0.8);
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.reply-form h3 {
    color: var(--gold);
    font-size: 1.3rem;
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: var(--lighter-grey);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-textarea {
    width: 100%;
    min-height: 120px;
    padding: 1rem;
    background: rgba(66, 66, 66, 0.8);
    border: 2px solid var(--darker-gold-2);
    border-radius: 8px;
    color: var(--lighter-grey);
    font-family: var(--font);
    font-size: 0.95rem;
    line-height: 1.6;
    resize: vertical;
    transition: all 0.3s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--gold);
    background: rgba(34, 34, 34, 0.9);
    box-shadow: 0 0 0 3px rgba(189, 145, 42, 0.1);
}

.form-textarea::placeholder {
    color: var(--light-grey);
    opacity: 0.7;
}

.form-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.btn-submit {
    padding: 0.75rem 2rem;
    background: linear-gradient(135deg, var(--gold) 0%, var(--light-gold) 100%);
    color: var(--charcoal);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(189, 145, 42, 0.4);
}

.btn-cancel {
    padding: 0.75rem 2rem;
    background: rgba(66, 66, 66, 0.8);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-cancel:hover {
    background: rgba(66, 66, 66, 1);
    border-color: var(--gold);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin: 2rem 0;
    flex-wrap: wrap;
}

.pagination-btn {
    padding: 0.5rem 0.75rem;
    background: rgba(66, 66, 66, 0.8);
    color: var(--lighter-grey);
    text-decoration: none;
    border-radius: 6px;
    border: 1px solid var(--darker-gold-2);
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
    font-size: 0.9rem;
}

.pagination-btn:hover {
    background: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
}

.pagination-btn.active {
    background: var(--gold);
    color: var(--charcoal);
    border-color: var(--gold);
    font-weight: bold;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--light-grey);
}

.empty-state-icon {
    font-size: 3rem;
    color: var(--darker-gold-1);
    margin-bottom: 1rem;
}

.login-prompt {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    color: #3498db;
    margin-bottom: 2rem;
}

.login-prompt a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 600;
}

.login-prompt a:hover {
    color: var(--light-gold);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .topic-meta {
        gap: 1rem;
    }
    
    .topic-stats {
        gap: 1rem;
    }
    
    .topic-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .topic-page {
        padding: 1rem 0.75rem;
    }
    
    .topic-header {
        padding: 1.5rem;
    }
    
    .topic-title {
        font-size: 1.5rem;
    }
    
    .post-header {
        padding: 1rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .post-author-info {
        justify-content: flex-start;
    }
    
    .post-meta {
        text-align: left;
        align-items: flex-start;
    }
    
    .post-content {
        padding: 1rem;
    }
    
    .post-footer {
        padding: 1rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .post-actions {
        justify-content: center;
    }
    
    .reply-form {
        padding: 1.5rem;
    }
    
    .form-buttons {
        flex-direction: column;
    }
    
    .btn-submit,
    .btn-cancel {
        text-align: center;
    }
}

@media (max-width: 480px) {
    .breadcrumb {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .breadcrumb-current {
        max-width: 200px;
    }
    
    .topic-status-badges {
        flex-wrap: wrap;
    }
    
    .status-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
    }
    
    .topic-meta {
        font-size: 0.8rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        justify-content: center;
    }
}
</style>



<main class="main-content">
    <div class="topic-page">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item">
                    <a href="<?php echo get_auth_base_url(); ?>/index.php" class="breadcrumb-link">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo get_auth_base_url(); ?>/forum/index.php" class="breadcrumb-link">Forum</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo get_auth_base_url(); ?>/forum/category.php?id=<?php echo $topic['category_id']; ?>" 
                       class="breadcrumb-link">
                        <?php echo htmlspecialchars($topic['category_name']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <span class="breadcrumb-current" title="<?php echo htmlspecialchars($topic['title']); ?>">
                        <?php echo htmlspecialchars($topic['title']); ?>
                    </span>
                </li>
            </ol>
        </nav>

        <!-- Mesajlar -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Topic Header -->
        <header class="topic-header">
            <div class="topic-header-content">
                <div class="topic-status-badges">
                    <?php if ($topic['is_pinned']): ?>
                        <span class="status-badge badge-pinned">
                            <i class="fas fa-thumbtack"></i>
                            Sabitlenmiş
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($topic['is_locked']): ?>
                        <span class="status-badge badge-locked">
                            <i class="fas fa-lock"></i>
                            Kilitli
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($topic['visibility'] !== 'public'): ?>
                        <span class="status-badge badge-visibility">
                            <i class="<?php echo $topic['visibility'] === 'members_only' ? 'fas fa-users' : 'fas fa-shield-alt'; ?>"></i>
                            <?php echo $topic['visibility'] === 'members_only' ? 'Sadece Üyeler' : 'Sadece Fraksiyon'; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <h1 class="topic-title"><?php echo htmlspecialchars($topic['title']); ?></h1>
                
                <div class="topic-meta">
                    <div class="topic-author">
                        <i class="fas fa-user"></i>
                        <span>Başlatan:</span>
                        <a href="<?php echo get_auth_base_url(); ?>/profile.php?user=<?php echo $topic['user_id']; ?>" 
                           class="topic-author-link">
                            <?php echo htmlspecialchars($topic['author_username']); ?>
                        </a>
                    </div>
                    
                    <div>
                        <i class="fas fa-clock"></i>
                        <?php echo date('d.m.Y H:i', strtotime($topic['created_at'])); ?>
                    </div>
                    
                    <div class="topic-stats">
                        <span>
                            <i class="fas fa-comments"></i>
                            <?php echo number_format($topic['reply_count']); ?> Yanıt
                        </span>
                        <span>
                            <i class="fas fa-eye"></i>
                            <?php echo number_format($topic['view_count']); ?> Görüntülenme
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Topic Actions -->
        <div class="topic-actions">
            <div class="action-buttons">
                <?php if ($can_reply && !$topic['is_locked']): ?>
                    <a href="#reply-form" class="action-btn btn-reply">
                        <i class="fas fa-reply"></i>
                        Yanıtla
                    </a>
                <?php elseif (!$current_user_id): ?>
                    <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=login" class="action-btn btn-reply">
                        <i class="fas fa-sign-in-alt"></i>
                        Yanıtlamak İçin Giriş Yap
                    </a>
                <?php endif; ?>
                
                <?php if ($can_edit_topic): ?>
                    <a href="<?php echo get_auth_base_url(); ?>/forum/edit_topic.php?id=<?php echo $topic['id']; ?>" 
                       class="action-btn btn-edit">
                        <i class="fas fa-edit"></i>
                        Düzenle
                    </a>
                <?php endif; ?>
                
                <?php if ($can_delete_topic): ?>
                    <a href="<?php echo get_auth_base_url(); ?>/forum/delete_topic.php?id=<?php echo $topic['id']; ?>" 
                       class="action-btn btn-delete" 
                       onclick="return confirm('Bu konuyu silmek istediğinizden emin misiniz?')">
                        <i class="fas fa-trash"></i>
                        Sil
                    </a>
                <?php endif; ?>
                
                <?php if ($can_moderate): ?>
                    <a href="<?php echo get_auth_base_url(); ?>/forum/moderate_topic.php?id=<?php echo $topic['id']; ?>" 
                       class="action-btn btn-moderate">
                        <i class="fas fa-gavel"></i>
                        Moderasyon
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="topic-navigation">
                <span class="nav-info">
                    Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?>
                    (<?php echo number_format($total_posts); ?> gönderi)
                </span>
            </div>
        </div>

        <!-- Posts -->
        <div class="posts-container">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $index => $post): ?>
                    <article class="post-item" id="post-<?php echo $post['id']; ?>">
                        <header class="post-header">
                            <div class="post-author-info">
                                <img src="<?php echo get_auth_base_url() . '/' . ($post['avatar_path'] ?: 'assets/default-avatar.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($post['author_username']); ?>" 
                                     class="post-avatar">
                                <div class="post-author-details">
                                    <a href="<?php echo get_auth_base_url(); ?>/profile.php?user=<?php echo $post['user_id']; ?>" 
                                       class="post-author-name">
                                        <?php echo htmlspecialchars($post['author_username']); ?>
                                    </a>
                                    <div class="post-author-role">
                                        <?php echo htmlspecialchars($post['user_roles'] ?? 'Üye'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="post-meta">
                                <div class="post-number">#<?php echo ($offset + $index + 1); ?></div>
                                <div><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></div>
                                <?php if ($post['is_edited']): ?>
                                    <div style="font-size: 0.75rem; color: var(--light-grey);">
                                        <i class="fas fa-edit"></i> Düzenlenmiş
                                    </div>
                                <?php endif; ?>
                            </div>
                        </header>
                        
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        
                        <footer class="post-footer">
                            <div class="post-edited-info">
                                <?php if ($post['is_edited'] && $post['edited_at']): ?>
                                    Son düzenlenme: <?php echo date('d.m.Y H:i', strtotime($post['edited_at'])); ?>
                                    <?php if ($post['edited_by_username']): ?>
                                        - <?php echo htmlspecialchars($post['edited_by_username']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <?php if ($can_reply && !$topic['is_locked']): ?>
                                    <a href="#reply-form" class="post-action-btn btn-quote" 
                                       onclick="quotePost('<?php echo htmlspecialchars($post['author_username']); ?>', '<?php echo htmlspecialchars(addslashes($post['content'])); ?>')">
                                        <i class="fas fa-quote-left"></i>
                                        Alıntı
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (can_user_edit_forum_post($pdo, $post['id'], $current_user_id)): ?>
                                    <a href="<?php echo get_auth_base_url(); ?>/forum/edit_post.php?id=<?php echo $post['id']; ?>" 
                                       class="post-action-btn btn-edit-post">
                                        <i class="fas fa-edit"></i>
                                        Düzenle
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (can_user_delete_forum_post($pdo, $post['id'], $current_user_id)): ?>
                                    <a href="<?php echo get_auth_base_url(); ?>/forum/delete_post.php?id=<?php echo $post['id']; ?>" 
                                       class="post-action-btn btn-delete-post" 
                                       onclick="return confirm('Bu gönderiyi silmek istediğinizden emin misiniz?')">
                                        <i class="fas fa-trash"></i>
                                        Sil
                                    </a>
                                <?php endif; ?>
                            </div>
                        </footer>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <p>Bu konuda henüz gönderi yok.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?id=<?php echo $topic_id; ?>&page=1" class="pagination-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?id=<?php echo $topic_id; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?id=<?php echo $topic_id; ?>&page=1" class="pagination-btn">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="pagination-btn" style="border: none; background: transparent;">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?id=<?php echo $topic_id; ?>&page=<?php echo $i; ?>" 
                       class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="pagination-btn" style="border: none; background: transparent;">...</span>
                    <?php endif; ?>
                    <a href="?id=<?php echo $topic_id; ?>&page=<?php echo $total_pages; ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?php echo $topic_id; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?id=<?php echo $topic_id; ?>&page=<?php echo $total_pages; ?>" class="pagination-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Reply Form -->
        <?php if ($can_reply && !$topic['is_locked']): ?>
            <form action="<?php echo get_auth_base_url(); ?>/forum/add_reply.php" method="POST" class="reply-form" id="reply-form">
                <h3>
                    <i class="fas fa-reply"></i>
                    Yanıt Yaz
                </h3>
                
                <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="reply_content" class="form-label">Mesajınız *</label>
                    <textarea name="content" id="reply_content" class="form-textarea" 
                              placeholder="Yanıtınızı buraya yazın..." required></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Yanıtı Gönder
                    </button>
                    <a href="<?php echo get_auth_base_url(); ?>/forum/category.php?id=<?php echo $topic['category_id']; ?>" 
                       class="btn-cancel">
                        <i class="fas fa-times"></i>
                        İptal
                    </a>
                </div>
            </form>
        <?php elseif (!$current_user_id): ?>
            <div class="login-prompt">
                <i class="fas fa-info-circle"></i>
                <strong>Bu konuya yanıt vermek için giriş yapmanız gerekiyor.</strong><br>
                <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=login">Giriş Yap</a> 
                veya <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=register">Kayıt Ol</a>
            </div>
        <?php elseif ($topic['is_locked']): ?>
            <div class="login-prompt" style="background: rgba(231, 76, 60, 0.1); border-color: rgba(231, 76, 60, 0.3); color: #e74c3c;">
                <i class="fas fa-lock"></i>
                <strong>Bu konu kilitli olduğu için yanıt veremezsiniz.</strong>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alıntı yapma işlevi
    window.quotePost = function(authorName, content) {
        const replyTextarea = document.getElementById('reply_content');
        if (replyTextarea) {
            const quotedText = `[quote="${authorName}"]${content}[/quote]\n\n`;
            replyTextarea.value = quotedText + replyTextarea.value;
            replyTextarea.focus();
            replyTextarea.setSelectionRange(replyTextarea.value.length, replyTextarea.value.length);
            
            // Reply form'a yumuşak kaydırma
            document.getElementById('reply-form').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
    };
    
    // Hash linklerine yumuşak kaydırma
    const hashLinks = document.querySelectorAll('a[href^="#"]');
    hashLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
                // URL'yi güncelle
                window.history.pushState(null, null, this.getAttribute('href'));
            }
        });
    });
    
    // Sayfa yüklendiğinde hash varsa o elemana git
    if (window.location.hash) {
        const targetElement = document.querySelector(window.location.hash);
        if (targetElement) {
            setTimeout(() => {
                targetElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 100);
        }
    }
    
    // Form gönderme için loading state
    const replyForm = document.getElementById('reply-form');
    if (replyForm) {
        replyForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.style.opacity = '0.6';
                submitBtn.style.pointerEvents = 'none';
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            }
        });
    }
    
    // Textarea otomatik boyutlandırma
    const textarea = document.getElementById('reply_content');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    
    // Klavye kısayolları
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter ile form gönder
        if (e.ctrlKey && e.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.id === 'reply_content') {
                e.preventDefault();
                replyForm.submit();
            }
        }
        
        // R tuşu ile yanıtla
        if (e.key === 'r' || e.key === 'R') {
            if (!e.ctrlKey && !e.altKey && document.activeElement.tagName !== 'TEXTAREA') {
                const replyBtn = document.querySelector('.btn-reply');
                if (replyBtn && replyBtn.href && replyBtn.href.includes('#reply-form')) {
                    e.preventDefault();
                    document.getElementById('reply-form').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                    setTimeout(() => {
                        document.getElementById('reply_content').focus();
                    }, 500);
                }
            }
        }
    });
    
    // Post linklerini yeni sekmede açma (harici linkler)
    const postContents = document.querySelectorAll('.post-content');
    postContents.forEach(content => {
        const links = content.querySelectorAll('a');
        links.forEach(link => {
            if (link.hostname !== window.location.hostname) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
        });
    });
    
    // Dinamik zaman güncellemesi
    function updatePostTimestamps() {
        const timeElements = document.querySelectorAll('.post-meta > div:nth-child(2)');
        
        timeElements.forEach(element => {
            const timeText = element.textContent;
            const match = timeText.match(/(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2})/);
            
            if (match) {
                const [, day, month, year, hour, minute] = match;
                const postDate = new Date(year, month - 1, day, hour, minute);
                const now = new Date();
                const diff = Math.floor((now - postDate) / 1000);
                
                let relativeTime = '';
                if (diff < 60) {
                    relativeTime = 'Az önce';
                } else if (diff < 3600) {
                    relativeTime = Math.floor(diff / 60) + ' dakika önce';
                } else if (diff < 86400) {
                    relativeTime = Math.floor(diff / 3600) + ' saat önce';
                } else {
                    return; // Eski tarihler için güncelleme yapma
                }
                
                element.setAttribute('title', timeText);
                element.textContent = relativeTime;
            }
        });
    }
    
    // Her dakika zaman damgalarını güncelle
    updatePostTimestamps();
    setInterval(updatePostTimestamps, 60000);
    
    // Sayfa yüklenme animasyonları
    const posts = document.querySelectorAll('.post-item');
    posts.forEach((post, index) => {
        post.style.opacity = '0';
        post.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            post.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            post.style.opacity = '1';
            post.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Touch gestures for mobile navigation
if ('ontouchstart' in window) {
    let startX = 0;
    let startY = 0;
    
    document.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });
    
    document.addEventListener('touchend', function(e) {
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        const diffX = startX - endX;
        const diffY = startY - endY;
        
        // Horizontal swipe detection
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 100) {
            if (diffX > 0) {
                // Swipe left - next page
                const nextBtn = document.querySelector('.pagination-btn[href*="page=' + (<?php echo $page; ?> + 1) + '"]');
                if (nextBtn) nextBtn.click();
            } else {
                // Swipe right - previous page
                const prevBtn = document.querySelector('.pagination-btn[href*="page=' + (<?php echo $page; ?> - 1) + '"]');
                if (prevBtn) prevBtn.click();
            }
        }
    });
}
</script>

