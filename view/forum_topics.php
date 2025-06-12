<?php
// view/forum_topics.php - Kullanıcı Forum Konuları Görüntüleme

session_start();

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Giriş yapma zorunluluğu
require_login();

// URL parametrelerini al
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
if (!$user_id) {
    $_SESSION['error_message'] = "Kullanıcı ID'si belirtilmedi.";
    header('Location: /index.php');
    exit;
}

// Görüntülenen kullanıcı bilgilerini çek
$viewed_user_data = getUserData($pdo, $user_id);
if (!$viewed_user_data) {
    $_SESSION['error_message'] = "Kullanıcı bulunamadı.";
    header('Location: /index.php');
    exit;
}

// Kendi profilini mi görüntülüyor
$is_own_profile = $_SESSION['user_id'] == $user_id;

// Sidebar için mevcut kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'];
$user_data = getUserData($pdo, $current_user_id);

// Filtreleme parametreleri
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Forum konularını çek
$topics_data = getForumTopics($pdo, $user_id, $category_id, $sort_by, $per_page, $offset);
$categories = getForumCategories($pdo);

// Sayfa başlığı
$page_title = "Forum Konuları - " . htmlspecialchars($viewed_user_data['username']);

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Kullanıcı verilerini çek
 */
function getUserData(PDO $pdo, int $user_id): ?array {
    try {
        $query = "
            SELECT u.id, u.username, u.avatar_path,
                   (SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_name,
                   (SELECT r.color FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_color
            FROM users u WHERE u.id = :user_id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        $user = $stmt->fetch();
        
        if (!$user) return null;
        
        $avatar_path = $user['avatar_path'] ?: '/assets/logo.png';
        
        // Gerçek istatistikleri çek
        $stats = getUserStats($pdo, $user_id);
        
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'avatar_path' => $avatar_path,
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a',
            'forum_stats' => $stats['forum'],
            'gallery_stats' => $stats['gallery'],
            'hangar_stats' => $stats['hangar']
        ];
    } catch (Exception $e) {
        return null;
    }
}



/**
 * Kullanıcı istatistiklerini çek
 */
function getUserStats(PDO $pdo, int $user_id): array {
    try {
        // Forum istatistikleri
        $topic_query = "SELECT COUNT(*) FROM forum_topics WHERE user_id = ?";
        $stmt = $pdo->prepare($topic_query);
        $stmt->execute([$user_id]);
        $topic_count = $stmt->fetchColumn();
        
        $post_query = "SELECT COUNT(*) FROM forum_posts WHERE user_id = ?";
        $stmt = $pdo->prepare($post_query);
        $stmt->execute([$user_id]);
        $post_count = $stmt->fetchColumn();
        
        $topic_likes_query = "
            SELECT COUNT(*) FROM forum_topic_likes ftl 
            JOIN forum_topics ft ON ftl.topic_id = ft.id 
            WHERE ft.user_id = ?
        ";
        $stmt = $pdo->prepare($topic_likes_query);
        $stmt->execute([$user_id]);
        $topic_likes = $stmt->fetchColumn();
        
        $post_likes_query = "
            SELECT COUNT(*) FROM forum_post_likes fpl 
            JOIN forum_posts fp ON fpl.post_id = fp.id 
            WHERE fp.user_id = ?
        ";
        $stmt = $pdo->prepare($post_likes_query);
        $stmt->execute([$user_id]);
        $post_likes = $stmt->fetchColumn();
        
        // Galeri istatistikleri
        $photos_query = "SELECT COUNT(*) FROM gallery_photos WHERE user_id = ?";
        $stmt = $pdo->prepare($photos_query);
        $stmt->execute([$user_id]);
        $photos_count = $stmt->fetchColumn();
        
        $gallery_likes_query = "
            SELECT COUNT(*) FROM gallery_photo_likes gpl 
            JOIN gallery_photos gp ON gpl.photo_id = gp.id 
            WHERE gp.user_id = ?
        ";
        $stmt = $pdo->prepare($gallery_likes_query);
        $stmt->execute([$user_id]);
        $gallery_likes = $stmt->fetchColumn();
        
        // Hangar istatistikleri
        $hangar_query = "SELECT COUNT(*), SUM(quantity) FROM user_hangar WHERE user_id = ?";
        $stmt = $pdo->prepare($hangar_query);
        $stmt->execute([$user_id]);
        $hangar_result = $stmt->fetch();
        
        return [
            'forum' => [
                'topics' => (int)$topic_count,
                'posts' => (int)$post_count,
                'likes_received' => (int)($topic_likes + $post_likes)
            ],
            'gallery' => [
                'photos' => (int)$photos_count,
                'likes_received' => (int)$gallery_likes
            ],
            'hangar' => [
                'unique_ships' => (int)($hangar_result[0] ?? 0),
                'total_ships' => (int)($hangar_result[1] ?? 0)
            ]
        ];
        
    } catch (Exception $e) {
        error_log("User stats error: " . $e->getMessage());
        return [
            'forum' => ['topics' => 0, 'posts' => 0, 'likes_received' => 0],
            'gallery' => ['photos' => 0, 'likes_received' => 0],
            'hangar' => ['unique_ships' => 0, 'total_ships' => 0]
        ];
    }
}

/**
 * Forum konularını çek
 */
function getForumTopics(PDO $pdo, int $user_id, ?int $category_id, string $sort_by, int $limit, int $offset): array {
    try {
        // Sıralama seçenekleri
        $order_by = match($sort_by) {
            'oldest' => 'ft.created_at ASC',
            'most_liked' => 'like_count DESC, ft.created_at DESC',
            'most_replies' => 'reply_count DESC, ft.created_at DESC',
            default => 'ft.created_at DESC' // newest
        };
        
        // WHERE koşulları
        $where_conditions = ['ft.user_id = :user_id'];
        $params = [':user_id' => $user_id];
        
        if ($category_id) {
            $where_conditions[] = 'ft.category_id = :category_id';
            $params[':category_id'] = $category_id;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Ana sorgu
        $query = "
            SELECT ft.id, ft.title, ft.content, ft.created_at, ft.updated_at, ft.is_pinned,
                   fc.name as category_name, fc.color as category_color,
                   (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) as reply_count,
                   (SELECT COUNT(*) FROM forum_topic_likes ftl WHERE ftl.topic_id = ft.id) as like_count,
                   (SELECT fp.created_at FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) as last_reply_at
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            $where_clause
            ORDER BY $order_by
            LIMIT :limit OFFSET :offset
        ";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = execute_safe_query($pdo, $query, $params);
        $topics = $stmt->fetchAll();
        
        // Toplam sayısı
        $count_query = "
            SELECT COUNT(*) as total
            FROM forum_topics ft
            $where_clause
        ";
        
        $count_params = array_filter($params, function($key) {
            return !in_array($key, [':limit', ':offset']);
        }, ARRAY_FILTER_USE_KEY);
        
        $stmt = execute_safe_query($pdo, $count_query, $count_params);
        $total = $stmt->fetchColumn();
        
        return [
            'topics' => $topics ?: [],
            'total' => (int)$total,
            'current_page' => (int)ceil(($offset + 1) / $limit),
            'total_pages' => (int)ceil($total / $limit)
        ];
        
    } catch (Exception $e) {
        error_log("Forum topics error: " . $e->getMessage());
        return ['topics' => [], 'total' => 0, 'current_page' => 1, 'total_pages' => 0];
    }
}

/**
 * Forum kategorilerini çek
 */
function getForumCategories(PDO $pdo): array {
    try {
        $query = "SELECT id, name, color FROM forum_categories ORDER BY name ASC";
        $stmt = execute_safe_query($pdo, $query);
        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log("Forum categories error: " . $e->getMessage());
        return [];
    }
}

/**
 * Zaman formatla
 */
function formatTimeAgo($datetime) {
    if (!$datetime) return '';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    if ($time < 2592000) return floor($time/604800) . ' hafta önce';
    
    return date('d.m.Y H:i', strtotime($datetime));
}
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="/view/css/view_common.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="profile-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/index.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="/view_profile.php?user_id=<?= $user_id ?>">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($viewed_user_data['username']) ?>
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-comments"></i> Forum Konuları
            </li>
        </ol>
    </nav>

    <div class="view-container">
        <!-- Sidebar -->
        <?php include '../src/includes/view_profile_sidebar.php'; ?>

        <!-- Ana İçerik -->
        <div class="view-main-content">
            <!-- Header -->
            <div class="view-header">
                <div class="view-header-info">
                    <h1>
                        <i class="fas fa-comments"></i>
                        Forum Konuları
                    </h1>
                    <p>
                        <a href="/view_profile.php?user_id=<?= $user_id ?>" class="user-link">
                            <?= htmlspecialchars($viewed_user_data['username']) ?>
                        </a>
                        tarafından oluşturulan forum konuları
                    </p>
                </div>
                <div class="view-header-stats">
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($topics_data['total']) ?></span>
                        <span class="view-stat-label">Konu</span>
                    </div>
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($viewed_user_data['forum_stats']['posts']) ?></span>
                        <span class="view-stat-label">Gönderi</span>
                    </div>
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($viewed_user_data['forum_stats']['likes_received']) ?></span>
                        <span class="view-stat-label">Beğeni</span>
                    </div>
                </div>
            </div>

            <!-- Filtreler -->
            <div class="view-filters">
                <h3 class="view-filters-title">
                    <i class="fas fa-filter"></i> Filtreler
                </h3>
                <form method="GET" class="view-filters-grid">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    
                    <div class="filter-group">
                        <label class="filter-label">Kategori</label>
                        <select name="category" class="filter-select">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Sıralama</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>En Yeni</option>
                            <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>En Eski</option>
                            <option value="most_liked" <?= $sort_by === 'most_liked' ? 'selected' : '' ?>>En Beğenilen</option>
                            <option value="most_replies" <?= $sort_by === 'most_replies' ? 'selected' : '' ?>>En Çok Yanıtlanan</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrele
                        </button>
                    </div>
                </form>
            </div>

            <!-- Konular -->
            <?php if (!empty($topics_data['topics'])): ?>
                <div class="view-grid">
                    <?php foreach ($topics_data['topics'] as $topic): ?>
                        <div class="view-card fade-in">
                            <div class="view-card-header">
                                <h3 class="view-card-title">
                                    <?php if ($topic['is_pinned']): ?>
                                        <i class="fas fa-thumbtack" style="color: var(--gold);" title="Sabitlenmiş"></i>
                                    <?php endif; ?>
                                    <a href="/forum/topic.php?id=<?= $topic['id'] ?>">
                                        <?= htmlspecialchars($topic['title']) ?>
                                    </a>
                                </h3>
                                <div class="view-card-meta">
                                    <span style="color: <?= htmlspecialchars($topic['category_color']) ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($topic['category_name']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= formatTimeAgo($topic['created_at']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="view-card-content">
                                <p><?= mb_substr(strip_tags($topic['content']), 0, 200) ?>...</p>
                            </div>
                            
                            <div class="view-card-footer">
                                <div class="topic-stats">
                                    <span>
                                        <i class="fas fa-comments"></i>
                                        <?= number_format($topic['reply_count']) ?> yanıt
                                    </span>
                                    <span>
                                        <i class="fas fa-heart"></i>
                                        <?= number_format($topic['like_count']) ?> beğeni
                                    </span>
                                    <?php if ($topic['last_reply_at']): ?>
                                        <span>
                                            <i class="fas fa-reply"></i>
                                            Son: <?= formatTimeAgo($topic['last_reply_at']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="topic-actions">
                                    <a href="/forum/topic.php?id=<?= $topic['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> Görüntüle
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($topics_data['total_pages'] > 1): ?>
                    <div class="view-pagination">
                        <?php if ($topics_data['current_page'] > 1): ?>
                            <a href="?user_id=<?= $user_id ?>&category=<?= $category_id ?>&sort=<?= $sort_by ?>&page=<?= $topics_data['current_page'] - 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $topics_data['current_page'] - 2); $i <= min($topics_data['total_pages'], $topics_data['current_page'] + 2); $i++): ?>
                            <a href="?user_id=<?= $user_id ?>&category=<?= $category_id ?>&sort=<?= $sort_by ?>&page=<?= $i ?>" 
                               class="pagination-btn <?= $i === $topics_data['current_page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($topics_data['current_page'] < $topics_data['total_pages']): ?>
                            <a href="?user_id=<?= $user_id ?>&category=<?= $category_id ?>&sort=<?= $sort_by ?>&page=<?= $topics_data['current_page'] + 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="view-empty">
                    <div class="view-empty-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="view-empty-title">Henüz Konu Yok</h3>
                    <p class="view-empty-text">
                        <?= htmlspecialchars($viewed_user_data['username']) ?> henüz hiç forum konusu oluşturmamış.
                    </p>
                    <?php if ($is_own_profile): ?>
                        <div class="view-empty-action">
                            <a href="/forum/create_topic.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> İlk Konunu Oluştur
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Forum Topics Specific Styles */
.topic-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    color: var(--light-grey);
}

.topic-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.topic-actions {
    display: flex;
    gap: 0.5rem;
}

.view-card-title a {
    color: var(--gold);
    text-decoration: none;
    transition: color 0.3s ease;
}

.view-card-title a:hover {
    color: var(--light-gold);
    text-decoration: none;
}

@media (max-width: 768px) {
    .topic-stats {
        font-size: 0.8rem;
        gap: 0.75rem;
    }
    
    .view-card-footer {
        flex-direction: column;
        gap: 1rem;
    }
    
    .topic-actions {
        justify-content: center;
    }
}
</style>

<script src="/view/js/view_common.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?> 