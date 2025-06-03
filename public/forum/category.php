<?php
// public/forum/category.php

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

// Kategori slug'ını al
$category_slug = $_GET['slug'] ?? '';
if (empty($category_slug)) {
    header('Location: /public/forum/');
    exit;
}

// Kategori detaylarını çek
$category = get_forum_category_details_by_slug($pdo, $category_slug, $current_user_id);
if (!$category) {
    header('Location: /public/forum/');
    exit;
}

// Sayfalama parametreleri
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Basit sıralama (şimdilik)
$sort = 'updated';
$order = 'desc';

// Tag filtresi (şimdilik boş)
$tag_filter = '';

// Konuları çek - mevcut fonksiyonu kullan
$topics_data = get_forum_topics_in_category($pdo, $category['id'], $current_user_id, $per_page, $offset);
$topics = $topics_data['topics'];
$total_topics = $topics_data['total'];

// Sayfa sayısını hesapla
$total_pages = ceil($total_topics / $per_page);

// Kategorinin etiketlerini çek (boş array için şimdilik)
$category_tags = [];

// Sayfa başlığı
$page_title = $category['name'] . " - Forum - Ilgarion Turanis";

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/public/forum/css/forum.css">
<link rel="stylesheet" href="/public/forum/css/category.css">

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
            <li class="breadcrumb-item active">
                <i class="<?= htmlspecialchars($category['icon']) ?>" style="color: <?= htmlspecialchars($category['color']) ?>"></i>
                <?= htmlspecialchars($category['name']) ?>
            </li>
        </ol>
    </nav>

    <!-- Kategori Header -->
    <div class="category-header">
        <div class="category-info">
            <div class="category-icon-large" style="color: <?= htmlspecialchars($category['color']) ?>">
                <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
            </div>
            <div class="category-details">
                <h1 style="color: <?= htmlspecialchars($category['color']) ?>">
                    <?= htmlspecialchars($category['name']) ?>
                </h1>
                <p class="category-description">
                    <?= htmlspecialchars($category['description']) ?>
                </p>
                <div class="category-meta">
                    <span class="meta-item">
                        <i class="fas fa-comments"></i>
                        <?= number_format($category['topic_count']) ?> Konu
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-comment-dots"></i>
                        <?= number_format($category['post_count']) ?> Gönderi
                    </span>
                    <span class="meta-item visibility-indicator">
                        <?php
                        $visibility_icons = [
                            'public' => '<i class="fas fa-globe" title="Herkese Açık"></i> Herkese Açık',
                            'members_only' => '<i class="fas fa-users" title="Sadece Üyeler"></i> Sadece Üyeler',
                            'faction_only' => '<i class="fas fa-shield-alt" title="Fraksiyona Özel"></i> Fraksiyona Özel'
                        ];
                        echo $visibility_icons[$category['visibility']] ?? '';
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="category-actions">
            <?php if ($category['can_create_topic']): ?>
                <a href="/public/forum/create_topic.php?category_id=<?= $category['id'] ?>" class="btn-new-topic">
                    <i class="fas fa-plus"></i> Yeni Konu Aç
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtreler ve Sıralama -->
    <div class="topics-toolbar">
        <div class="topics-filters">
            <!-- Tag Filtreleri -->
            <?php if (!empty($category_tags)): ?>
                <div class="tag-filters">
                    <span class="filter-label">Etiketler:</span>
                    <div class="tag-list">
                        <a href="?slug=<?= urlencode($category_slug) ?>" 
                           class="tag-filter <?= empty($tag_filter) ? 'active' : '' ?>">
                            <i class="fas fa-tags"></i> Tümü
                        </a>
                        <?php foreach ($category_tags as $tag): ?>
                            <a href="?slug=<?= urlencode($category_slug) ?>&tag=<?= urlencode($tag['slug']) ?>" 
                               class="tag-filter <?= $tag_filter === $tag['slug'] ? 'active' : '' ?>"
                               style="--tag-color: <?= htmlspecialchars($tag['color']) ?>">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($tag['name']) ?>
                                <span class="tag-count"><?= $tag['usage_count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="topics-sorting">
            <div class="sort-options">
                <span class="sort-label">Sırala:</span>
                <select id="sortSelect" onchange="changeSorting()">
                    <option value="updated-desc" <?= ($sort === 'updated' && $order === 'desc') ? 'selected' : '' ?>>
                        Son Aktivite (Yeni → Eski)
                    </option>
                    <option value="updated-asc" <?= ($sort === 'updated' && $order === 'asc') ? 'selected' : '' ?>>
                        Son Aktivite (Eski → Yeni)
                    </option>
                    <option value="created-desc" <?= ($sort === 'created' && $order === 'desc') ? 'selected' : '' ?>>
                        Oluşturulma (Yeni → Eski)
                    </option>
                    <option value="created-asc" <?= ($sort === 'created' && $order === 'asc') ? 'selected' : '' ?>>
                        Oluşturulma (Eski → Yeni)
                    </option>
                    <option value="replies-desc" <?= ($sort === 'replies' && $order === 'desc') ? 'selected' : '' ?>>
                        Yanıt Sayısı (Çok → Az)
                    </option>
                    <option value="title-asc" <?= ($sort === 'title' && $order === 'asc') ? 'selected' : '' ?>>
                        Alfabetik (A → Z)
                    </option>
                </select>
            </div>
        </div>
    </div>

    <!-- Konular Listesi -->
    <div class="topics-list">
        <?php if (empty($topics)): ?>
            <div class="no-topics">
                <i class="fas fa-comment-slash"></i>
                <h3>Bu kategoride henüz konu bulunmuyor</h3>
                <p>
                    <?php if ($category['can_create_topic']): ?>
                        İlk konuyu siz açarak tartışmayı başlatabilirsiniz.
                    <?php else: ?>
                        Yakında konular ekleneceğini umuyoruz.
                    <?php endif; ?>
                </p>
                <?php if ($category['can_create_topic']): ?>
                    <a href="/public/forum/create_topic.php?category_id=<?= $category['id'] ?>" class="btn-primary">
                        <i class="fas fa-plus"></i> İlk Konuyu Aç
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="topics-header">
                <div class="topics-count">
                    <?= number_format($total_topics) ?> konu bulundu
                    <?php if (!empty($tag_filter)): ?>
                        <span class="filter-active">
                            "<?= htmlspecialchars($tag_filter) ?>" etiketi için
                            <a href="?slug=<?= urlencode($category_slug) ?>" class="clear-filter">
                                <i class="fas fa-times"></i> Filtreyi Temizle
                            </a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach ($topics as $topic): ?>
                <div class="topic-item <?= $topic['is_pinned'] ? 'pinned' : '' ?> <?= $topic['is_locked'] ? 'locked' : '' ?>">
                    <div class="topic-status">
                        <?php if ($topic['is_pinned']): ?>
                            <i class="fas fa-thumbtack pinned-icon" title="Sabitlenmiş"></i>
                        <?php endif; ?>
                        
                        <?php if ($topic['is_locked']): ?>
                            <i class="fas fa-lock locked-icon" title="Kilitli"></i>
                        <?php else: ?>
                            <i class="fas fa-comment-dots topic-icon"></i>
                        <?php endif; ?>
                    </div>

                    <div class="topic-content">
                        <div class="topic-title-row">
                            <h3 class="topic-title">
                                <a href="/public/forum/topic.php?id=<?= $topic['id'] ?>">
                                    <?= htmlspecialchars($topic['title']) ?>
                                </a>
                            </h3>
                            
                            <?php if (!empty($topic['tags'])): ?>
                                <div class="topic-tags">
                                    <?php 
                                    $topic_tags = explode(',', $topic['tags']);
                                    foreach (array_slice($topic_tags, 0, 3) as $tag_name): 
                                        $tag_name = trim($tag_name);
                                    ?>
                                        <span class="topic-tag">
                                            <i class="fas fa-tag"></i>
                                            <?= htmlspecialchars($tag_name) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($topic_tags) > 3): ?>
                                        <span class="topic-tag-more">+<?= count($topic_tags) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="topic-meta">
                            <span class="topic-author">
                                <span class="user-link" data-user-id="<?= $topic['user_id'] ?>"
                                      style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                                    <?= htmlspecialchars($topic['author_username']) ?>
                                </span>
                            </span>
                            <span class="topic-date">
                                <?= format_time_ago($topic['created_at']) ?>
                            </span>
                            
                            <?php if ($topic['visibility'] !== 'public'): ?>
                                <span class="topic-visibility">
                                    <?php
                                    $visibility_badges = [
                                        'members_only' => '<i class="fas fa-users"></i> Üyeler',
                                        'faction_only' => '<i class="fas fa-shield-alt"></i> Fraksiyon'
                                    ];
                                    echo $visibility_badges[$topic['visibility']] ?? '';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="topic-stats">
                        <div class="stat-replies">
                            <div class="stat-number"><?= number_format($topic['reply_count']) ?></div>
                            <div class="stat-label">Yanıt</div>
                        </div>
                        <div class="stat-views">
                            <div class="stat-number"><?= number_format($topic['view_count']) ?></div>
                            <div class="stat-label">Görüntüleme</div>
                        </div>
                    </div>

                    <div class="topic-latest">
                        <?php if ($topic['last_post_at'] && $topic['last_post_username']): ?>
                            <div class="latest-post">
                                <div class="latest-author">
                                    <span class="user-link" data-user-id="<?= $topic['last_post_user_id'] ?>"
                                          style="color: <?= $topic['last_post_role_color'] ?? '#bd912a' ?>">
                                        <?= htmlspecialchars($topic['last_post_username']) ?>
                                    </span>
                                </div>
                                <div class="latest-time">
                                    <?= format_time_ago($topic['last_post_at']) ?>
                                </div>
                                <a href="/public/forum/topic.php?id=<?= $topic['id'] ?>&page=last#last-post" 
                                   class="latest-link" title="Son gönderiye git">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="no-replies">
                                <i class="fas fa-clock"></i>
                                <span>Henüz yanıt yok</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
        <div class="forum-pagination">
            <div class="pagination-info">
                Sayfa <?= $page ?> / <?= $total_pages ?> 
                (Toplam <?= number_format($total_topics) ?> konu)
            </div>
            
            <nav class="pagination-nav">
                <?php if ($page > 1): ?>
                    <a href="?slug=<?= urlencode($category_slug) ?>&page=1<?= !empty($tag_filter) ? '&tag=' . urlencode($tag_filter) : '' ?>&sort=<?= $sort ?>&order=<?= $order ?>" 
                       class="page-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?slug=<?= urlencode($category_slug) ?>&page=<?= $page - 1 ?><?= !empty($tag_filter) ? '&tag=' . urlencode($tag_filter) : '' ?>&sort=<?= $sort ?>&order=<?= $order ?>" 
                       class="page-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?slug=<?= urlencode($category_slug) ?>&page=<?= $i ?><?= !empty($tag_filter) ? '&tag=' . urlencode($tag_filter) : '' ?>&sort=<?= $sort ?>&order=<?= $order ?>" 
                       class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?slug=<?= urlencode($category_slug) ?>&page=<?= $page + 1 ?><?= !empty($tag_filter) ? '&tag=' . urlencode($tag_filter) : '' ?>&sort=<?= $sort ?>&order=<?= $order ?>" 
                       class="page-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?slug=<?= urlencode($category_slug) ?>&page=<?= $total_pages ?><?= !empty($tag_filter) ? '&tag=' . urlencode($tag_filter) : '' ?>&sort=<?= $sort ?>&order=<?= $order ?>" 
                       class="page-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/public/forum/includes/user_popover.php'; ?>

<script>
function changeSorting() {
    const select = document.getElementById('sortSelect');
    const [sort, order] = select.value.split('-');
    const url = new URL(window.location);
    url.searchParams.set('sort', sort);
    url.searchParams.set('order', order);
    url.searchParams.set('page', '1'); // Reset to first page
    window.location.href = url.toString();
}
</script>

<script src="/public/forum/js/forum.js"></script>

<?php
// Zaman formatlama fonksiyonu
function format_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    if ($time < 2592000) return floor($time/604800) . ' hafta önce';
    
    return date('d.m.Y', strtotime($datetime));
}

include BASE_PATH . '/src/includes/footer.php';
?>