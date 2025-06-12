<?php
// view/forum_posts.php - Kullanıcı Forum Gönderileri
session_start();

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

require_login();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
if (!$user_id) {
    header('Location: /index.php');
    exit;
}

// Kullanıcı bilgilerini çek
$viewed_user_data = getUserData($pdo, $user_id);
if (!$viewed_user_data) {
    header('Location: /index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$user_data = getUserData($pdo, $current_user_id);
$is_own_profile = $current_user_id == $user_id;

// Filtreleme parametreleri
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Forum gönderilerini çek
$posts_data = getForumPosts($pdo, $user_id, $sort_by, $per_page, $offset);

$page_title = "Forum Gönderileri - " . htmlspecialchars($viewed_user_data['username']);

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
        
        // İstatistikleri çek
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

function getUserStats(PDO $pdo, int $user_id): array {
    try {
        // Forum istatistikleri - ayrı sorgular
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
        
        // Galeri istatistikleri - ayrı sorgular
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
        
        // Hangar istatistikleri - tek sorgu
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
        error_log("getUserStats error: " . $e->getMessage());
        return [
            'forum' => ['topics' => 0, 'posts' => 0, 'likes_received' => 0],
            'gallery' => ['photos' => 0, 'likes_received' => 0],
            'hangar' => ['unique_ships' => 0, 'total_ships' => 0]
        ];
    }
}

function getForumPosts(PDO $pdo, int $user_id, string $sort_by, int $limit, int $offset): array {
    try {
        $order_by = match($sort_by) {
            'oldest' => 'fp.created_at ASC',
            'most_liked' => 'like_count DESC, fp.created_at DESC',
            default => 'fp.created_at DESC' // newest
        };
        
        $query = "
            SELECT fp.id, fp.content, fp.created_at, fp.updated_at,
                   ft.id as topic_id, ft.title as topic_title,
                   fc.name as category_name, fc.color as category_color,
                   (SELECT COUNT(*) FROM forum_post_likes fpl WHERE fpl.post_id = fp.id) as like_count
            FROM forum_posts fp
            JOIN forum_topics ft ON fp.topic_id = ft.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE fp.user_id = :user_id
            ORDER BY $order_by
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = execute_safe_query($pdo, $query, [
            ':user_id' => $user_id,
            ':limit' => $limit,
            ':offset' => $offset
        ]);
        $posts = $stmt->fetchAll();
        
        // Toplam sayı
        $count_query = "SELECT COUNT(*) FROM forum_posts WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $count_query, [':user_id' => $user_id]);
        $total = $stmt->fetchColumn();
        
        return [
            'posts' => $posts ?: [],
            'total' => (int)$total,
            'current_page' => (int)ceil(($offset + 1) / $limit),
            'total_pages' => (int)ceil($total / $limit)
        ];
    } catch (Exception $e) {
        error_log("Forum posts error: " . $e->getMessage());
        return ['posts' => [], 'total' => 0, 'current_page' => 1, 'total_pages' => 0];
    }
}

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

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="/view/css/view_common.css">
<link rel="stylesheet" href="/view/css/forum_posts.css">

<div class="site-container">
    <nav class="profile-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="/view_profile.php?user_id=<?= $user_id ?>">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($viewed_user_data['username']) ?>
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-comment"></i> Forum Gönderileri
            </li>
        </ol>
    </nav>

    <div class="view-container">
        <?php include '../src/includes/view_profile_sidebar.php'; ?>

        <div class="view-main-content">
            <!-- Header -->
            <div class="view-header">
                <div class="view-header-info">
                    <h1><i class="fas fa-comment"></i> Forum Gönderileri</h1>
                    <p>
                        <a href="/view_profile.php?user_id=<?= $user_id ?>" class="user-link">
                            <?= htmlspecialchars($viewed_user_data['username']) ?>
                        </a>
                        tarafından yapılan forum gönderileri
                    </p>
                </div>
                <div class="view-header-stats">
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($posts_data['total']) ?></span>
                        <span class="view-stat-label">Gönderi</span>
                    </div>
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($viewed_user_data['forum_stats']['topics']) ?></span>
                        <span class="view-stat-label">Konu</span>
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
                        <label class="filter-label">Sıralama</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>En Yeni</option>
                            <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>En Eski</option>
                            <option value="most_liked" <?= $sort_by === 'most_liked' ? 'selected' : '' ?>>En Beğenilen</option>
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

            <!-- Gönderiler -->
            <?php if (!empty($posts_data['posts'])): ?>
                <div class="view-grid">
                    <?php foreach ($posts_data['posts'] as $post): ?>
                        <div class="view-card fade-in">
                            <div class="view-card-header">
                                <h3 class="view-card-title">
                                    <a href="/forum/topic.php?id=<?= $post['topic_id'] ?>#post-<?= $post['id'] ?>">
                                        <?= htmlspecialchars($post['topic_title']) ?>
                                    </a>
                                </h3>
                                <div class="view-card-meta">
                                    <span style="color: <?= htmlspecialchars($post['category_color']) ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($post['category_name']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= formatTimeAgo($post['created_at']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="view-card-content">
                                <div class="post-content">
                                    <?= mb_substr(strip_tags($post['content']), 0, 250) ?>...
                                </div>
                            </div>
                            
                            <div class="view-card-footer">
                                <div class="post-stats">
                                    <span>
                                        <i class="fas fa-heart"></i>
                                        <?= number_format($post['like_count']) ?> beğeni
                                    </span>
                                    <?php if ($post['updated_at'] != $post['created_at']): ?>
                                        <span>
                                            <i class="fas fa-edit"></i>
                                            Düzenlendi: <?= formatTimeAgo($post['updated_at']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="post-actions">
                                    <a href="/forum/topic.php?id=<?= $post['topic_id'] ?>#post-<?= $post['id'] ?>" 
                                       class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> Görüntüle
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($posts_data['total_pages'] > 1): ?>
                    <div class="view-pagination">
                        <?php if ($posts_data['current_page'] > 1): ?>
                            <a href="?user_id=<?= $user_id ?>&sort=<?= $sort_by ?>&page=<?= $posts_data['current_page'] - 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $posts_data['current_page'] - 2); $i <= min($posts_data['total_pages'], $posts_data['current_page'] + 2); $i++): ?>
                            <a href="?user_id=<?= $user_id ?>&sort=<?= $sort_by ?>&page=<?= $i ?>" 
                               class="pagination-btn <?= $i === $posts_data['current_page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($posts_data['current_page'] < $posts_data['total_pages']): ?>
                            <a href="?user_id=<?= $user_id ?>&sort=<?= $sort_by ?>&page=<?= $posts_data['current_page'] + 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="view-empty">
                    <div class="view-empty-icon">
                        <i class="fas fa-comment"></i>
                    </div>
                    <h3 class="view-empty-title">Henüz Gönderi Yok</h3>
                    <p class="view-empty-text">
                        <?= htmlspecialchars($viewed_user_data['username']) ?> henüz hiç forum gönderisi yapmamış.
                    </p>
                    <?php if ($is_own_profile): ?>
                        <div class="view-empty-action">
                            <a href="/forum/" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Forum'u Keşfet
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/view/js/view_common.js"></script>
<script src="/view/js/forum_posts.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?> 