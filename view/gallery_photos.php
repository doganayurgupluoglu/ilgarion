<?php
// view/gallery_photos.php - Kullanıcı Galeri Fotoğrafları
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
$category = isset($_GET['category']) ? $_GET['category'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Galeri fotoğraflarını çek
$photos_data = getGalleryPhotos($pdo, $user_id, $sort_by, $category, $per_page, $offset);

$page_title = "Galeri Fotoğrafları - " . htmlspecialchars($viewed_user_data['username']);

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
        error_log("getUserStats error: " . $e->getMessage());
        return [
            'forum' => ['topics' => 0, 'posts' => 0, 'likes_received' => 0],
            'gallery' => ['photos' => 0, 'likes_received' => 0],
            'hangar' => ['unique_ships' => 0, 'total_ships' => 0]
        ];
    }
}

function getGalleryPhotos(PDO $pdo, int $user_id, string $sort_by, string $category, int $limit, int $offset): array {
    try {
        $order_by = match($sort_by) {
            'oldest' => 'gp.uploaded_at ASC',
            'most_liked' => 'like_count DESC, gp.uploaded_at DESC',
            'alphabetical' => 'gp.id ASC', // No title field, use ID
            default => 'gp.uploaded_at DESC' // newest
        };
        
        $where_conditions = ['gp.user_id = :user_id'];
        $params = [':user_id' => $user_id];
        
        // Category filtering disabled since there's no category field
        // if (!empty($category)) {
        //     $where_conditions[] = 'gp.category = :category';
        //     $params[':category'] = $category;
        // }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "
            SELECT gp.id, 
                   COALESCE(gp.image_path, '') as file_path, 
                   COALESCE(gp.description, '') as description, 
                   gp.uploaded_at as created_at, 
                   gp.uploaded_at as updated_at,
                   (SELECT COUNT(*) FROM gallery_photo_likes gpl WHERE gpl.photo_id = gp.id) as like_count,
                   (SELECT COUNT(*) FROM gallery_photo_comments gpc WHERE gpc.photo_id = gp.id) as comment_count
            FROM gallery_photos gp
            $where_clause
            ORDER BY $order_by
            LIMIT :limit OFFSET :offset
        ";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = execute_safe_query($pdo, $query, $params);
        $photos = $stmt->fetchAll();
        
        // Add missing fields for template compatibility and fix paths
        foreach ($photos as &$photo) {
            // Debug mode - set to false for production
            $debug_mode = false; // Change to true for debugging
            
            if ($debug_mode) {
                error_log("Photo data before processing: " . json_encode($photo));
            }
            
            $photo['title'] = 'Fotoğraf #' . $photo['id']; // Generate title
            
            // Fix file path - ensure it's not empty and has proper format
            $original_file_path = $photo['file_path'] ?? null;
            
            if (is_null($original_file_path) || $original_file_path === '' || $original_file_path === 'null' || $original_file_path === false) {
                $photo['file_path'] = '/assets/logo.png'; // Default image
                if ($debug_mode) {
                    error_log("Using default image for photo ID: " . $photo['id'] . " - Original was: " . var_export($original_file_path, true));
                }
            } else {
                $photo['file_path'] = (strpos($original_file_path, '/') !== 0) ? '/' . $original_file_path : $original_file_path;
            }
            
            // Ensure description is not null
            $photo['description'] = $photo['description'] ?? '';
            
            // Ensure all fields are strings, not null - CRITICAL
            $photo['title'] = trim((string)$photo['title']);
            $photo['file_path'] = trim((string)$photo['file_path']);
            $photo['description'] = trim((string)$photo['description']);
            
            // Double check - if still empty, use defaults
            if (empty($photo['title'])) $photo['title'] = 'Fotoğraf';
            if (empty($photo['file_path'])) $photo['file_path'] = '/assets/logo.png';
            if (empty($photo['description'])) $photo['description'] = '';
            
            if ($debug_mode) {
                error_log("Photo data after processing: " . json_encode([
                    'id' => $photo['id'],
                    'title' => $photo['title'],
                    'file_path' => $photo['file_path'],
                    'description' => $photo['description']
                ]));
            }
        }
        
        // Toplam sayı
        $count_query = "SELECT COUNT(*) FROM gallery_photos gp $where_clause";
        $count_params = array_filter($params, function($key) {
            return !in_array($key, [':limit', ':offset']);
        }, ARRAY_FILTER_USE_KEY);
        
        $stmt = execute_safe_query($pdo, $count_query, $count_params);
        $total = $stmt->fetchColumn();
        
        return [
            'photos' => $photos ?: [],
            'total' => (int)$total,
            'current_page' => (int)ceil(($offset + 1) / $limit),
            'total_pages' => (int)ceil($total / $limit)
        ];
    } catch (Exception $e) {
        error_log("Gallery photos error: " . $e->getMessage());
        return ['photos' => [], 'total' => 0, 'current_page' => 1, 'total_pages' => 0];
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
<link rel="stylesheet" href="/view/css/gallery_photos.css">

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
                <i class="fas fa-images"></i> Galeri Fotoğrafları
            </li>
        </ol>
    </nav>

    <div class="view-container">
        <?php include '../src/includes/view_profile_sidebar.php'; ?>

        <div class="view-main-content">
            <!-- Header -->
            <div class="view-header">
                <div class="view-header-info">
                    <h1><i class="fas fa-images"></i> Galeri Fotoğrafları</h1>
                    <p>
                        <a href="/view_profile.php?user_id=<?= $user_id ?>" class="user-link">
                            <?= htmlspecialchars($viewed_user_data['username']) ?>
                        </a>
                        tarafından paylaşılan fotoğraflar
                    </p>
                </div>
                <div class="view-header-stats">
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($photos_data['total']) ?></span>
                        <span class="view-stat-label">Fotoğraf</span>
                    </div>
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($viewed_user_data['gallery_stats']['likes_received']) ?></span>
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

            <!-- Fotoğraflar -->
            <?php if (!empty($photos_data['photos'])): ?>
                <div class="gallery-grid">
                    <?php foreach ($photos_data['photos'] as $photo): ?>
                        <div class="gallery-item">
                            <?php 
                            // Store data in attributes for JavaScript
                            $safe_file_path = htmlspecialchars($photo['file_path']);
                            $safe_title = htmlspecialchars($photo['title']); 
                            $safe_description = htmlspecialchars($photo['description']);
                            ?>
                            <div class="gallery-link" 
                                 data-image-path="<?= $safe_file_path ?>"
                                 data-image-title="<?= $safe_title ?>"
                                 data-image-description="<?= $safe_description ?>">
                                <div class="gallery-image-container">
                                    <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                         alt="<?= htmlspecialchars($photo['title']) ?>" 
                                         class="gallery-image">
                                    
                                    <div class="gallery-overlay">
                                        <div class="gallery-overlay-content">
                                            <h4 class="gallery-title"><?= htmlspecialchars($photo['title']) ?></h4>
                                            <?php if (!empty($photo['description'])): ?>
                                                <p class="gallery-description">
                                                    <?= htmlspecialchars(mb_substr($photo['description'], 0, 80)) ?>...
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="gallery-stats">
                                                <span>
                                                    <i class="fas fa-heart"></i>
                                                    <?= number_format($photo['like_count']) ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-comments"></i>
                                                    <?= number_format($photo['comment_count']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="gallery-item-info">
                                    <div class="gallery-item-meta">
                                        <span class="gallery-date">
                                            <i class="fas fa-clock"></i>
                                            <?= formatTimeAgo($photo['created_at']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($photos_data['total_pages'] > 1): ?>
                    <div class="view-pagination">
                        <?php if ($photos_data['current_page'] > 1): ?>
                            <a href="?user_id=<?= $user_id ?>&sort=<?= $sort_by ?>&page=<?= $photos_data['current_page'] - 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $photos_data['current_page'] - 2); $i <= min($photos_data['total_pages'], $photos_data['current_page'] + 2); $i++): ?>
                            <a href="?user_id=<?= $user_id ?>&sort=<?= $sort_by ?>&page=<?= $i ?>" 
                               class="pagination-btn <?= $i === $photos_data['current_page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($photos_data['current_page'] < $photos_data['total_pages']): ?>
                            <a href="?user_id=<?= $user_id ?>&sort=<?= $sort_by ?>&page=<?= $photos_data['current_page'] + 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="view-empty">
                    <div class="view-empty-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <h3 class="view-empty-title">Henüz Fotoğraf Yok</h3>
                    <p class="view-empty-text">
                        <?= htmlspecialchars($viewed_user_data['username']) ?> henüz hiç fotoğraf paylaşmamış.
                    </p>
                    <?php if ($is_own_profile): ?>
                        <div class="view-empty-action">
                            <a href="/gallery/upload.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> İlk Fotoğrafını Yükle
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/view/js/view_common.js"></script>
<script src="/view/js/gallery_photos.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?> 