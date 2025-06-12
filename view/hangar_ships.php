<?php
// view/hangar_ships.php - Kullanıcı Hangar Gemileri
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
$manufacturer = isset($_GET['manufacturer']) ? $_GET['manufacturer'] : '';
$size_category = isset($_GET['size']) ? $_GET['size'] : '';
$role_category = isset($_GET['role']) ? $_GET['role'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Hangar gemilerini çek
$ships_data = getHangarShips($pdo, $user_id, $sort_by, $manufacturer, $size_category, $role_category, $per_page, $offset);
$manufacturers = getManufacturers($pdo);

$page_title = "Hangar Gemileri - " . htmlspecialchars($viewed_user_data['username']);

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
        $hangar_query = "
            SELECT 
                COUNT(*) as unique_ships,
                SUM(quantity) as total_ships,
                SUM(CASE WHEN has_lti = 1 THEN 1 ELSE 0 END) as lti_ships
            FROM user_hangar 
            WHERE user_id = ?
        ";
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
                'unique_ships' => (int)($hangar_result['unique_ships'] ?? 0),
                'total_ships' => (int)($hangar_result['total_ships'] ?? 0)
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

function getHangarShips(PDO $pdo, int $user_id, string $sort_by, string $manufacturer, string $size_category, string $role_category, int $limit, int $offset): array {
    try {
        $order_by = match($sort_by) {
            'oldest' => 'uh.added_at ASC',
            'alphabetical' => 'uh.ship_name ASC',
            'manufacturer' => 'uh.ship_manufacturer ASC, uh.ship_name ASC',
            'quantity' => 'uh.quantity DESC, uh.ship_name ASC',
            default => 'uh.added_at DESC' // newest
        };
        
        $where_conditions = ['uh.user_id = :user_id'];
        $params = [':user_id' => $user_id];
        
        if (!empty($manufacturer)) {
            $where_conditions[] = 'uh.ship_manufacturer = :manufacturer';
            $params[':manufacturer'] = $manufacturer;
        }
        
        if (!empty($size_category)) {
            $where_conditions[] = 'uh.ship_size = :size_category';
            $params[':size_category'] = $size_category;
        }
        
        if (!empty($role_category)) {
            $where_conditions[] = 'uh.ship_focus = :role_category';
            $params[':role_category'] = $role_category;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "
            SELECT uh.id, uh.ship_name, uh.ship_manufacturer as manufacturer, uh.quantity, 
                   uh.added_at as acquired_date, uh.user_notes as notes,
                   uh.ship_image_url as image_path, uh.ship_size as size_category, 
                   uh.ship_focus as role_category, uh.has_lti,
                   NULL as crew_size, NULL as cargo_capacity
            FROM user_hangar uh
            $where_clause
            ORDER BY $order_by
            LIMIT :limit OFFSET :offset
        ";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = execute_safe_query($pdo, $query, $params);
        $ships = $stmt->fetchAll();
        
        // Toplam sayı
        $count_query = "SELECT COUNT(*) FROM user_hangar uh $where_clause";
        $count_params = array_filter($params, function($key) {
            return !in_array($key, [':limit', ':offset']);
        }, ARRAY_FILTER_USE_KEY);
        
        $stmt = execute_safe_query($pdo, $count_query, $count_params);
        $total = $stmt->fetchColumn();
        
        return [
            'ships' => $ships ?: [],
            'total' => (int)$total,
            'current_page' => (int)ceil(($offset + 1) / $limit),
            'total_pages' => (int)ceil($total / $limit)
        ];
    } catch (Exception $e) {
        error_log("Hangar ships error: " . $e->getMessage());
        return ['ships' => [], 'total' => 0, 'current_page' => 1, 'total_pages' => 0];
    }
}

function getManufacturers(PDO $pdo): array {
    try {
        $query = "SELECT DISTINCT ship_manufacturer FROM user_hangar ORDER BY ship_manufacturer ASC";
        $stmt = execute_safe_query($pdo, $query);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        return [];
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
    
    return date('d.m.Y', strtotime($datetime));
}

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="/view/css/view_common.css">
<link rel="stylesheet" href="/view/css/hangar_ships.css">

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
                <i class="fas fa-space-shuttle"></i> Hangar Gemileri
            </li>
        </ol>
    </nav>

    <div class="view-container">
        <?php include '../src/includes/view_profile_sidebar.php'; ?>

        <div class="view-main-content">
            <!-- Header -->
            <div class="view-header">
                <div class="view-header-info">
                    <h1><i class="fas fa-space-shuttle"></i> Hangar Gemileri</h1>
                    <p>
                        <a href="/view_profile.php?user_id=<?= $user_id ?>" class="user-link">
                            <?= htmlspecialchars($viewed_user_data['username']) ?>
                        </a>
                        kullanıcısının Star Citizen hangar koleksiyonu
                    </p>
                </div>
                <div class="view-header-stats">
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($viewed_user_data['hangar_stats']['unique_ships']) ?></span>
                        <span class="view-stat-label">Farklı Gemi</span>
                    </div>
                    <div class="view-stat-item">
                        <span class="view-stat-number"><?= number_format($viewed_user_data['hangar_stats']['total_ships']) ?></span>
                        <span class="view-stat-label">Toplam</span>
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
                        <label class="filter-label">Üretici</label>
                        <select name="manufacturer" class="filter-select">
                            <option value="">Tüm Üreticiler</option>
                            <?php foreach ($manufacturers as $mfr): ?>
                                <option value="<?= htmlspecialchars($mfr) ?>" <?= $manufacturer === $mfr ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mfr) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Boyut</label>
                        <select name="size" class="filter-select">
                            <option value="">Tüm Boyutlar</option>
                            <option value="Small" <?= $size_category === 'Small' ? 'selected' : '' ?>>Küçük</option>
                            <option value="Medium" <?= $size_category === 'Medium' ? 'selected' : '' ?>>Orta</option>
                            <option value="Large" <?= $size_category === 'Large' ? 'selected' : '' ?>>Büyük</option>
                            <option value="Capital" <?= $size_category === 'Capital' ? 'selected' : '' ?>>Capital</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Rol</label>
                        <select name="role" class="filter-select">
                            <option value="">Tüm Roller</option>
                            <option value="Fighter" <?= $role_category === 'Fighter' ? 'selected' : '' ?>>Savaşçı</option>
                            <option value="Transport" <?= $role_category === 'Transport' ? 'selected' : '' ?>>Nakliye</option>
                            <option value="Exploration" <?= $role_category === 'Exploration' ? 'selected' : '' ?>>Keşif</option>
                            <option value="Mining" <?= $role_category === 'Mining' ? 'selected' : '' ?>>Madencilik</option>
                            <option value="Multi-role" <?= $role_category === 'Multi-role' ? 'selected' : '' ?>>Çok Amaçlı</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Sıralama</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>En Yeni</option>
                            <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>En Eski</option>
                            <option value="alphabetical" <?= $sort_by === 'alphabetical' ? 'selected' : '' ?>>Alfabetik</option>
                            <option value="manufacturer" <?= $sort_by === 'manufacturer' ? 'selected' : '' ?>>Üretici</option>
                            <option value="quantity" <?= $sort_by === 'quantity' ? 'selected' : '' ?>>Adet</option>
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

            <!-- Gemiler -->
            <?php if (!empty($ships_data['ships'])): ?>
                <div class="ships-grid">
                    <?php foreach ($ships_data['ships'] as $ship): ?>
                        <div class="ship-card" data-ship-id="<?= $ship['id'] ?>">
                            <div class="ship-card-header">
                                <div class="ship-image">
                                    <?php if (!empty($ship['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($ship['image_path']) ?>" 
                                             alt="<?= htmlspecialchars($ship['ship_name']) ?>"
                                             onerror="this.src='/assets/ship-placeholder.png'">
                                    <?php else: ?>
                                        <div class="ship-placeholder">
                                            <i class="fas fa-space-shuttle"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- LTI Badge (has_lti=1 ise göster) -->
                                <?php if ($ship['has_lti']): ?>
                                    <div class="lti-badge">
                                        <i class="fas fa-shield-alt"></i>
                                        <span>LTI</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ship-card-body">
                                <h4 class="ship-name"><?= htmlspecialchars($ship['ship_name']) ?></h4>
                                
                                <div class="ship-details">
                                    <?php if (!empty($ship['manufacturer'])): ?>
                                        <div class="ship-detail">
                                            <span class="detail-label">Üretici:</span>
                                            <span class="detail-value"><?= htmlspecialchars($ship['manufacturer']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($ship['size_category'])): ?>
                                        <div class="ship-detail">
                                            <span class="detail-label">Boyut:</span>
                                            <span class="detail-value"><?= htmlspecialchars($ship['size_category']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($ship['role_category'])): ?>
                                        <div class="ship-detail">
                                            <span class="detail-label">Odak:</span>
                                            <span class="detail-value"><?= htmlspecialchars($ship['role_category']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ship-detail">
                                        <span class="detail-label">Adet:</span>
                                        <span class="detail-value quantity"><?= number_format($ship['quantity']) ?></span>
                                    </div>
                                    
                                    <div class="ship-detail">
                                        <span class="detail-label">Sigorta:</span>
                                        <span class="detail-value insurance <?= $ship['has_lti'] ? 'lti' : 'standard' ?>">
                                            <?= $ship['has_lti'] ? 'LTI' : 'Standart' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($ship['notes'])): ?>
                                    <div class="ship-notes">
                                        <i class="fas fa-sticky-note"></i>
                                        <?= nl2br(htmlspecialchars($ship['notes'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="ship-added-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= formatTimeAgo($ship['acquired_date']) ?> tarihinde eklendi
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($ships_data['total_pages'] > 1): ?>
                    <div class="view-pagination">
                        <?php if ($ships_data['current_page'] > 1): ?>
                            <a href="?user_id=<?= $user_id ?>&manufacturer=<?= urlencode($manufacturer) ?>&size=<?= urlencode($size_category) ?>&role=<?= urlencode($role_category) ?>&sort=<?= $sort_by ?>&page=<?= $ships_data['current_page'] - 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $ships_data['current_page'] - 2); $i <= min($ships_data['total_pages'], $ships_data['current_page'] + 2); $i++): ?>
                            <a href="?user_id=<?= $user_id ?>&manufacturer=<?= urlencode($manufacturer) ?>&size=<?= urlencode($size_category) ?>&role=<?= urlencode($role_category) ?>&sort=<?= $sort_by ?>&page=<?= $i ?>" 
                               class="pagination-btn <?= $i === $ships_data['current_page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($ships_data['current_page'] < $ships_data['total_pages']): ?>
                            <a href="?user_id=<?= $user_id ?>&manufacturer=<?= urlencode($manufacturer) ?>&size=<?= urlencode($size_category) ?>&role=<?= urlencode($role_category) ?>&sort=<?= $sort_by ?>&page=<?= $ships_data['current_page'] + 1 ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="view-empty">
                    <div class="view-empty-icon">
                        <i class="fas fa-space-shuttle"></i>
                    </div>
                    <h3 class="view-empty-title">Henüz Gemi Yok</h3>
                    <p class="view-empty-text">
                        <?= htmlspecialchars($viewed_user_data['username']) ?> henüz hangarına gemi eklememiş.
                    </p>
                    <?php if ($is_own_profile): ?>
                        <div class="view-empty-action">
                            <a href="/profile/hangar.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> İlk Gemini Ekle
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Ship Card Styling - Profile hangar.php'den uyarlandı */
.ships-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.ship-card {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.ship-card:hover {
    border-color: var(--border-1-hover);
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.ship-card-header {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.ship-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-bg-2);
}

.ship-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.ship-card:hover .ship-image img {
    transform: scale(1.05);
}

.ship-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--light-grey);
    font-size: 2rem;
    width: 100%;
    height: 100%;
}

.ship-placeholder i {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* LTI Badge */
.lti-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: linear-gradient(135deg, #C69653, #b8845c);
    color: white;
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(198, 150, 83, 0.4);
    display: flex;
    align-items: center;
    gap: 4px;
    z-index: 10;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.lti-badge i {
    font-size: 0.7rem;
}

.ship-card:hover .lti-badge {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(198, 150, 83, 0.6);
}

/* Ship Card Body */
.ship-card-body {
    padding: 1.5rem;
}

.ship-name {
    color: var(--gold);
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    text-align: center;
}

.ship-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.ship-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-1);
}

.ship-detail:last-child {
    border-bottom: none;
}

.detail-label {
    color: var(--light-grey);
    font-size: 0.9rem;
    font-weight: 500;
}

.detail-value {
    color: var(--lighter-grey);
    font-weight: 600;
}

.detail-value.quantity {
    color: var(--turquase);
    font-weight: 700;
}

.detail-value.insurance.lti {
    color: var(--gold);
    font-weight: 700;
}

.detail-value.insurance.standard {
    color: var(--light-grey);
}

.ship-notes {
    background: var(--card-bg-2);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    line-height: 1.5;
}

.ship-notes i {
    color: var(--gold);
    margin-right: 0.5rem;
}

.ship-added-date {
    color: var(--light-grey);
    font-size: 0.85rem;
    text-align: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.ship-added-date i {
    color: var(--turquase);
}

/* Responsive */
@media (max-width: 768px) {
    .ships-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .ship-card-body {
        padding: 1rem;
    }
    
    .ship-name {
        font-size: 1.1rem;
    }
}
</style>

<script src="/view/js/view_common.js"></script>
<script src="/view/js/hangar_ships.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?> 