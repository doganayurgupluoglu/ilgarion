<?php
// public/forum/category.php - Forum Kategori Sayfası

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

// Kategori ID'sini al ve validate et
$category_id = $_GET['id'] ?? null;

if (!$category_id || !is_numeric($category_id)) {
    $_SESSION['error_message'] = "Geçersiz kategori ID'si.";
    header('Location: ' . get_auth_base_url() . '/forum/index.php');
    exit;
}

$category_id = (int)$category_id;

// Kategori detaylarını al
$category = get_forum_category_details($pdo, $category_id, $current_user_id);

if (!$category) {
    $_SESSION['error_message'] = "Kategori bulunamadı veya erişim yetkiniz bulunmuyor.";
    header('Location: ' . get_auth_base_url() . '/forum/index.php');
    exit;
}

// Sayfalama parametreleri
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Konuları getir
$topics_data = get_forum_topics_in_category($pdo, $category_id, $current_user_id, $per_page, $offset);
$topics = $topics_data['topics'];
$total_topics = $topics_data['total'];
$total_pages = ceil($total_topics / $per_page);

$page_title = htmlspecialchars($category['name']) . " - Forum - Ilgarion Turanis";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.category-page {
    width: 100%;
    max-width: 1400px;
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
}

.category-header {
    background: linear-gradient(135deg, rgba(34, 34, 34, 0.9), rgba(42, 42, 42, 0.9));
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 4px solid;
    position: relative;
    overflow: hidden;
}

.category-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(189, 145, 42, 0.05), transparent);
    pointer-events: none;
}

.category-info {
    position: relative;
    z-index: 2;
}

.category-title-section {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.category-icon-large {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: var(--charcoal);
    font-weight: bold;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.category-title {
    color: var(--gold);
    font-size: 2rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    line-height: 1.2;
}

.category-description {
    color: var(--light-grey);
    font-size: 1.1rem;
    line-height: 1.5;
    margin: 0;
}

.category-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
}

.category-stats {
    display: flex;
    gap: 2rem;
    font-size: 0.9rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.stat-number {
    font-size: 1.4rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--light-grey);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
}

.create-topic-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, var(--gold) 0%, var(--light-gold) 100%);
    color: var(--charcoal);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    cursor: pointer;
}

.create-topic-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(189, 145, 42, 0.4);
}

.create-topic-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

.topics-container {
    background: rgba(34, 34, 34, 0.8);
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    overflow: hidden;
}

.topics-header {
    background: rgba(26, 26, 26, 0.8);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.topics-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.topic-item {
    border-bottom: 1px solid rgba(189, 145, 42, 0.1);
    transition: background-color 0.2s ease;
}

.topic-item:last-child {
    border-bottom: none;
}

.topic-item:hover {
    background: rgba(189, 145, 42, 0.05);
}

.topic-content {
    display: grid;
    grid-template-columns: auto 1fr auto auto auto;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    align-items: center;
}

.topic-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    width: 40px;
}

.topic-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    color: var(--charcoal);
    font-weight: bold;
}

.topic-normal {
    background: var(--turquase);
}

.topic-pinned {
    background: var(--gold);
    animation: pulse-gold 2s infinite;
}

.topic-locked {
    background: var(--red);
}

.topic-hot {
    background: #e67e22;
}

@keyframes pulse-gold {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.topic-indicators {
    display: flex;
    gap: 0.25rem;
    font-size: 0.7rem;
}

.topic-indicator {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.indicator-pinned {
    background: var(--gold);
}

.indicator-locked {
    background: var(--red);
}

.topic-main {
    min-width: 0;
}

.topic-title {
    color: var(--lighter-grey);
    text-decoration: none;
    font-size: 1.1rem;
    font-weight: 600;
    line-height: 1.3;
    margin-bottom: 0.5rem;
    display: block;
    transition: color 0.2s ease;
}

.topic-title:hover {
    color: var(--gold);
}

.topic-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--light-grey);
    font-size: 0.85rem;
}

.topic-author {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
}

.topic-author:hover {
    color: var(--light-gold);
}

.topic-stats {
    text-align: center;
    color: var(--light-grey);
    font-size: 0.9rem;
}

.topic-stat-number {
    display: block;
    font-weight: bold;
    color: var(--gold);
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.topic-views {
    text-align: center;
    color: var(--light-grey);
    font-size: 0.9rem;
}

.topic-views-number {
    display: block;
    font-weight: bold;
    color: var(--turquase);
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.topic-last-post {
    text-align: right;
    min-width: 150px;
}

.last-post-user {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
}

.last-post-user:hover {
    color: var(--light-gold);
}

.last-post-time {
    color: var(--light-grey);
    font-size: 0.8rem;
    margin-top: 0.25rem;
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

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--light-grey);
}

.empty-state-icon {
    font-size: 4rem;
    color: var(--darker-gold-1);
    margin-bottom: 1.5rem;
}

.empty-state-title {
    font-size: 1.3rem;
    color: var(--lighter-grey);
    margin-bottom: 0.75rem;
}

.empty-state-text {
    font-size: 1rem;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.visibility-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 0.5rem;
}

.visibility-public {
    background: rgba(46, 204, 113, 0.2);
    color: #2ecc71;
    border: 1px solid rgba(46, 204, 113, 0.3);
}

.visibility-members {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.visibility-faction {
    background: rgba(155, 89, 182, 0.2);
    color: #9b59b6;
    border: 1px solid rgba(155, 89, 182, 0.3);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .topics-header,
    .topic-content {
        grid-template-columns: 1fr auto;
        gap: 0.75rem;
    }
    
    .topic-stats,
    .topic-views {
        display: none;
    }
    
    .category-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 1.5rem;
    }
    
    .category-stats {
        justify-content: space-around;
    }
}

@media (max-width: 768px) {
    .category-page {
        padding: 1rem 0.75rem;
    }
    
    .category-title-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .category-icon-large {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .category-title {
        font-size: 1.5rem;
    }
    
    .topics-container {
        border-radius: 8px;
    }
    
    .topic-content {
        grid-template-columns: auto 1fr;
        padding: 1rem;
    }
    
    .topic-last-post {
        grid-column: 1 / -1;
        text-align: left;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(189, 145, 42, 0.2);
        min-width: auto;
    }
    
    .category-stats {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .stat-item {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .breadcrumb {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .category-header {
        padding: 1.5rem;
    }
    
    .topics-header {
        display: none;
    }
    
    .topic-item {
        border-radius: 8px;
        margin-bottom: 0.5rem;
        border: 1px solid var(--darker-gold-2);
    }
    
    .pagination {
        gap: 0.25rem;
    }
    
    .pagination-btn {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
        min-width: 35px;
    }
}
</style>

<main class="main-content">
    <div class="category-page">
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
                    <span class="breadcrumb-current"><?php echo htmlspecialchars($category['name']); ?></span>
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

        <!-- Category Header -->
        <header class="category-header" style="border-left-color: <?php echo htmlspecialchars($category['color']); ?>">
            <div class="category-info">
                <div class="category-title-section">
                    <div class="category-icon-large" style="background-color: <?php echo htmlspecialchars($category['color']); ?>">
                        <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                    </div>
                    <div>
                        <h1 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h1>
                        <div class="visibility-badge visibility-<?php echo $category['visibility'] === 'members_only' ? 'members' : ($category['visibility'] === 'faction_only' ? 'faction' : 'public'); ?>">
                            <?php
                            $visibility_icons = [
                                'public' => 'fas fa-globe',
                                'members_only' => 'fas fa-users',
                                'faction_only' => 'fas fa-shield-alt'
                            ];
                            $visibility_texts = [
                                'public' => 'Herkese Açık',
                                'members_only' => 'Sadece Üyeler',
                                'faction_only' => 'Sadece Fraksiyon'
                            ];
                            ?>
                            <i class="<?php echo $visibility_icons[$category['visibility']]; ?>"></i>
                            <?php echo $visibility_texts[$category['visibility']]; ?>
                        </div>
                    </div>
                </div>
                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
            </div>
        </header>

        <!-- Category Actions -->
        <div class="category-actions">
            <div class="category-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($category['topic_count']); ?></span>
                    <span class="stat-label">Konular</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($category['post_count']); ?></span>
                    <span class="stat-label">Mesajlar</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_topics); ?></span>
                    <span class="stat-label">Toplam Konu</span>
                </div>
            </div>
            
            <?php if ($category['can_create_topic']): ?>
                <a href="<?php echo get_auth_base_url(); ?>/forum/create_topic.php?category=<?php echo $category['id']; ?>" 
                   class="create-topic-btn">
                    <i class="fas fa-plus"></i>
                    Yeni Konu Aç
                </a>
            <?php elseif (!$current_user_id): ?>
                <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=login" 
                   class="create-topic-btn" style="background: rgba(52, 152, 219, 0.8);">
                    <i class="fas fa-sign-in-alt"></i>
                    Konu Açmak İçin Giriş Yap
                </a>
            <?php endif; ?>
        </div>

        <!-- Topics List -->
        <div class="topics-container">
            <?php if (!empty($topics)): ?>
                <div class="topics-header">
                    <div>Konu</div>
                    <div>Yanıtlar</div>
                    <div>Görüntülenme</div>
                    <div>Son Gönderi</div>
                </div>
                
                <ul class="topics-list">
                    <?php foreach ($topics as $topic): ?>
                        <li class="topic-item">
                            <div class="topic-content">
                                <div class="topic-status">
                                    <?php
                                    $topic_class = 'topic-normal';
                                    $topic_icon = 'fas fa-comment';
                                    
                                    if ($topic['is_pinned']) {
                                        $topic_class = 'topic-pinned';
                                        $topic_icon = 'fas fa-thumbtack';
                                    } elseif ($topic['is_locked']) {
                                        $topic_class = 'topic-locked';
                                        $topic_icon = 'fas fa-lock';
                                    } elseif ($topic['reply_count'] > 20) {
                                        $topic_class = 'topic-hot';
                                        $topic_icon = 'fas fa-fire';
                                    }
                                    ?>
                                    <div class="topic-icon <?php echo $topic_class; ?>">
                                        <i class="<?php echo $topic_icon; ?>"></i>
                                    </div>
                                    <div class="topic-indicators">
                                        <?php if ($topic['is_pinned']): ?>
                                            <div class="topic-indicator indicator-pinned" title="Sabitlenmiş"></div>
                                        <?php endif; ?>
                                        <?php if ($topic['is_locked']): ?>
                                            <div class="topic-indicator indicator-locked" title="Kilitli"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="topic-main">
                                    <a href="<?php echo get_auth_base_url(); ?>/forum/topic.php?id=<?php echo $topic['id']; ?>" 
                                       class="topic-title">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                    <div class="topic-meta">
                                        <span>
                                            <i class="fas fa-user"></i>
                                            <a href="<?php echo get_auth_base_url(); ?>/profile.php?user=<?php echo $topic['user_id']; ?>" 
                                               class="topic-author">
                                                <?php echo htmlspecialchars($topic['author_username']); ?>
                                            </a>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($topic['created_at'])); ?>
                                        </span>
                                        <?php if ($topic['visibility'] !== 'public'): ?>
                                            <span class="visibility-badge visibility-<?php echo $topic['visibility'] === 'members_only' ? 'members' : 'faction'; ?>">
                                                <i class="<?php echo $topic['visibility'] === 'members_only' ? 'fas fa-users' : 'fas fa-shield-alt'; ?>"></i>
                                                <?php echo $topic['visibility'] === 'members_only' ? 'Üyeler' : 'Fraksiyon'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="topic-stats">
                                    <span class="topic-stat-number"><?php echo number_format($topic['reply_count']); ?></span>
                                    <span>Yanıt</span>
                                </div>
                                
                                <div class="topic-views">
                                    <span class="topic-views-number"><?php echo number_format($topic['view_count']); ?></span>
                                    <span>Görüntülenme</span>
                                </div>
                                
                                <div class="topic-last-post">
                                    <?php if ($topic['last_post_username']): ?>
                                        <a href="<?php echo get_auth_base_url(); ?>/profile.php?user=<?php echo $topic['last_post_user_id']; ?>" 
                                           class="last-post-user">
                                            <?php echo htmlspecialchars($topic['last_post_username']); ?>
                                        </a>
                                        <div class="last-post-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($topic['last_post_at'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="last-post-user">-</span>
                                        <div class="last-post-time">-</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="empty-state-title">Bu kategoride henüz konu yok</h3>
                    <p class="empty-state-text">
                        Bu kategoride henüz hiç konu açılmamış. 
                        <?php if ($category['can_create_topic']): ?>
                            İlk konuyu sen aç ve tartışmayı başlat!
                        <?php elseif (!$current_user_id): ?>
                            Konu açmak için giriş yapman gerekiyor.
                        <?php else: ?>
                            Bu kategoride konu açma yetkin bulunmuyor.
                        <?php endif; ?>
                    </p>
                    <?php if ($category['can_create_topic']): ?>
                        <a href="<?php echo get_auth_base_url(); ?>/forum/create_topic.php?category=<?php echo $category['id']; ?>" 
                           class="create-topic-btn">
                            <i class="fas fa-plus"></i>
                            İlk Konuyu Aç
                        </a>
                    <?php elseif (!$current_user_id): ?>
                        <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=login" 
                           class="create-topic-btn" style="background: rgba(52, 152, 219, 0.8);">
                            <i class="fas fa-sign-in-alt"></i>
                            Giriş Yap
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?id=<?php echo $category_id; ?>&page=1" class="pagination-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?id=<?php echo $category_id; ?>&page=1" class="pagination-btn">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="pagination-btn" style="border: none; background: transparent;">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>" 
                       class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="pagination-btn" style="border: none; background: transparent;">...</span>
                    <?php endif; ?>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $total_pages; ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $total_pages; ?>" class="pagination-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; color: var(--light-grey); font-size: 0.9rem; margin-top: 1rem;">
                Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?> 
                (Toplam <?php echo number_format($total_topics); ?> konu)
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Konu satırlarına tıklama olayı ekleme
    const topicItems = document.querySelectorAll('.topic-item');
    
    topicItems.forEach(item => {
        const topicLink = item.querySelector('.topic-title');
        const topicContent = item.querySelector('.topic-content');
        
        if (topicLink) {
            topicContent.addEventListener('click', function(e) {
                // Eğer tıklanan element bir link değilse, ana konu linkine yönlendir
                if (!e.target.closest('a')) {
                    topicLink.click();
                }
            });
            
            // Hover efektleri
            item.addEventListener('mouseenter', function() {
                this.style.cursor = 'pointer';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.cursor = 'default';
            });
        }
    });
    
    // Konu başlıklarını kısaltma (mobil için)
    function truncateTopicTitles() {
        const topicTitles = document.querySelectorAll('.topic-title');
        
        topicTitles.forEach(title => {
            const originalText = title.textContent;
            title.setAttribute('data-original-title', originalText);
            
            if (window.innerWidth <= 768 && originalText.length > 50) {
                title.textContent = originalText.substring(0, 50) + '...';
                title.setAttribute('title', originalText);
            } else {
                title.textContent = originalText;
                title.removeAttribute('title');
            }
        });
    }
    
    // Sayfa yüklendiğinde ve pencere boyutu değiştiğinde çalıştır
    truncateTopicTitles();
    window.addEventListener('resize', truncateTopicTitles);
    
    // Infinite scroll (opsiyonel - gelecek özellik için)
    let isLoading = false;
    
    function handleInfiniteScroll() {
        if (isLoading) return;
        
        const scrollPosition = window.scrollY + window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        
        if (scrollPosition >= documentHeight - 1000) {
            // Burada AJAX ile yeni konular yüklenebilir
            console.log('Near bottom - could load more topics');
        }
    }
    
    // Throttled scroll event
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(handleInfiniteScroll, 100);
    });
    
    // Klavye navigasyonu
    document.addEventListener('keydown', function(e) {
        // N tuşu ile yeni konu
        if (e.key === 'n' || e.key === 'N') {
            if (!e.ctrlKey && !e.altKey) {
                const createBtn = document.querySelector('.create-topic-btn');
                if (createBtn && createBtn.href && !createBtn.href.includes('login')) {
                    e.preventDefault();
                    createBtn.click();
                }
            }
        }
        
        // R tuşu ile sayfayı yenile
        if (e.key === 'r' || e.key === 'R') {
            if (!e.ctrlKey && !e.altKey) {
                e.preventDefault();
                location.reload();
            }
        }
    });
    
    // Dinamik zaman güncellemesi
    function updateTimestamps() {
        const timeElements = document.querySelectorAll('[data-timestamp]');
        
        timeElements.forEach(element => {
            const timestamp = parseInt(element.dataset.timestamp);
            const now = Math.floor(Date.now() / 1000);
            const diff = now - timestamp;
            
            let timeText = '';
            
            if (diff < 60) {
                timeText = 'Az önce';
            } else if (diff < 3600) {
                timeText = Math.floor(diff / 60) + ' dakika önce';
            } else if (diff < 86400) {
                timeText = Math.floor(diff / 3600) + ' saat önce';
            } else {
                return; // Eski tarihler için güncelleme yapma
            }
            
            const timeIcon = element.querySelector('i');
            const iconHTML = timeIcon ? timeIcon.outerHTML + ' ' : '';
            element.innerHTML = iconHTML + timeText;
        });
    }
    
    // Her dakika zaman damgalarını güncelle
    setInterval(updateTimestamps, 60000);
    
    // Sayfa yüklenme animasyonları
    const topicItems2 = document.querySelectorAll('.topic-item');
    topicItems2.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 50);
    });
    
    // Sayfalama butonları için loading state
    const paginationBtns = document.querySelectorAll('.pagination-btn');
    paginationBtns.forEach(btn => {
        if (btn.href) {
            btn.addEventListener('click', function() {
                this.style.opacity = '0.6';
                this.style.pointerEvents = 'none';
                
                // Loading spinner ekle
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Eğer sayfa yüklenmezse geri yükle (failsafe)
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.style.opacity = '1';
                    this.style.pointerEvents = 'auto';
                }, 5000);
            });
        }
    });
});

// Touch gestures for mobile
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

<?php
// Forum functions için gerekli fonksiyonu ekle
function get_forum_topics_in_category(PDO $pdo, int $category_id, ?int $user_id = null, int $limit = 20, int $offset = 0): array {
    try {
        // Kategoriye erişim kontrolü
        $category = get_forum_category_details($pdo, $category_id, $user_id);
        if (!$category) {
            return ['topics' => [], 'total' => 0];
        }

        // Kullanıcının görebileceği konuları belirle
        $visibility_clause = "";
        $params = [':category_id' => $category_id];

        if ($user_id === null || !is_user_logged_in()) {
            $visibility_clause = " AND ft.visibility = 'public'";
        } else {
            if (is_user_approved()) {
                if (has_permission($pdo, 'forum.view_faction_only', $user_id)) {
                    // Tüm konuları görebilir
                } else if (has_permission($pdo, 'forum.view_members_only', $user_id)) {
                    $visibility_clause = " AND ft.visibility IN ('public', 'members_only')";
                } else {
                    $visibility_clause = " AND ft.visibility = 'public'";
                }
            } else {
                $visibility_clause = " AND ft.visibility = 'public'";
            }
        }

        // Toplam konu sayısı
        $count_query = "
            SELECT COUNT(*) 
            FROM forum_topics ft 
            WHERE ft.category_id = :category_id" . $visibility_clause;
        
        $stmt = execute_safe_query($pdo, $count_query, $params);
        $total = (int)$stmt->fetchColumn();

        // Konuları getir
        $safe_limit = create_safe_limit($limit, $offset, 100);
        
        $topics_query = "
            SELECT ft.*, 
                   u.username as author_username,
                   lpu.username as last_post_username
            FROM forum_topics ft
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN users lpu ON ft.last_post_user_id = lpu.id
            WHERE ft.category_id = :category_id" . $visibility_clause . "
            ORDER BY ft.is_pinned DESC, ft.updated_at DESC
            " . $safe_limit;

        $stmt = execute_safe_query($pdo, $topics_query, $params);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'topics' => $topics,
            'total' => $total
        ];

    } catch (Exception $e) {
        error_log("Forum kategori konuları getirme hatası: " . $e->getMessage());
        return ['topics' => [], 'total' => 0];
    }
}

require_once BASE_PATH . '/src/includes/footer.php';
?>