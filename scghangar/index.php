<?php
// scghangar/index.php - SCG Hangar Görüntüleme Sistemi

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Giriş yapma zorunluluğu
require_login();

// Permission kontrolü - scg.hangar.view yetkisi gerekli
require_permission($pdo, 'scg.hangar.view');

$current_user_id = $_SESSION['user_id'];

/**
 * SCG rolüne sahip kullanıcıların hangar gemilerini çeker
 */
function getSCGMembersHangarShips(PDO $pdo): array {
    try {
        $query = "
            SELECT uh.id, uh.ship_api_id, uh.ship_name, uh.ship_manufacturer, 
                   uh.ship_focus, uh.ship_size, uh.ship_image_url, uh.quantity, 
                   uh.has_lti, uh.user_notes, uh.added_at,
                   u.username, u.avatar_path,
                   CASE 
                       WHEN uh.ship_api_id LIKE 'api_%' THEN 'api'
                       WHEN uh.ship_api_id LIKE 'manual_%' THEN 'manual'
                       ELSE 'legacy'
                   END as source_type
            FROM user_hangar uh, users u, user_roles ur
            WHERE uh.user_id = u.id 
            AND u.id = ur.user_id 
            AND ur.role_id = 68
            ORDER BY u.username ASC, uh.ship_manufacturer ASC, uh.ship_name ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll() ?: [];
        
        error_log("SCG ships final result count: " . count($result));
        
        return $result;
        
    } catch (Exception $e) {
        error_log("SCG Hangar ships error: " . $e->getMessage());
        return [];
    }
}

/**
 * SCG hangar istatistikleri
 */
function getSCGHangarStatistics(PDO $pdo): array {
    try {
        $query = "
            SELECT 
                COUNT(DISTINCT uh.id) as unique_ships,
                SUM(uh.quantity) as total_ships,
                COUNT(DISTINCT uh.ship_manufacturer) as manufacturers,
                COUNT(DISTINCT uh.ship_size) as sizes,
                SUM(CASE WHEN uh.has_lti = 1 THEN uh.quantity ELSE 0 END) as lti_ships,
                COUNT(DISTINCT u.id) as scg_members_with_ships
            FROM user_hangar uh, users u, user_roles ur
            WHERE uh.user_id = u.id 
            AND u.id = ur.user_id 
            AND ur.role_id = 68
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'unique_ships' => (int)($result['unique_ships'] ?? 0),
            'total_ships' => (int)($result['total_ships'] ?? 0),
            'manufacturers' => (int)($result['manufacturers'] ?? 0),
            'sizes' => (int)($result['sizes'] ?? 0),
            'lti_ships' => (int)($result['lti_ships'] ?? 0),
            'scg_members_with_ships' => (int)($result['scg_members_with_ships'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("SCG Hangar stats error: " . $e->getMessage());
        return [
            'unique_ships' => 0, 
            'total_ships' => 0, 
            'manufacturers' => 0, 
            'sizes' => 0, 
            'lti_ships' => 0,
            'scg_members_with_ships' => 0
        ];
    }
}

/**
 * SCG üyelerinin listesini çeker
 */
function getSCGMembers(PDO $pdo): array {
    try {
        $query = "
            SELECT u.id, u.username, u.avatar_path,
                   COUNT(uh.id) as ship_count,
                   COALESCE(SUM(uh.quantity), 0) as total_quantity
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN user_hangar uh ON u.id = uh.user_id
            WHERE ur.role_id = 68
            GROUP BY u.id, u.username, u.avatar_path
            ORDER BY u.username ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll() ?: [];
        
        error_log("SCG Members query result: " . count($result) . " members found");
        if (!empty($result)) {
            error_log("First member: " . print_r($result[0], true));
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("SCG Members error: " . $e->getMessage());
        return [];
    }
}

// Verileri çek
$scg_ships = getSCGMembersHangarShips($pdo);
$scg_stats = getSCGHangarStatistics($pdo);
$scg_members = getSCGMembers($pdo);

// Filtreleme parametreleri
$filter_user = $_GET['user'] ?? '';
$filter_manufacturer = $_GET['manufacturer'] ?? '';
$filter_size = $_GET['size'] ?? '';
$show_lti_only = isset($_GET['lti_only']) && $_GET['lti_only'] === '1';

// Filtreleri uygula
if (!empty($filter_user) || !empty($filter_manufacturer) || !empty($filter_size) || $show_lti_only) {
    $scg_ships = array_filter($scg_ships, function($ship) use ($filter_user, $filter_manufacturer, $filter_size, $show_lti_only) {
        if (!empty($filter_user) && stripos($ship['username'], $filter_user) === false) {
            return false;
        }
        if (!empty($filter_manufacturer) && stripos($ship['ship_manufacturer'], $filter_manufacturer) === false) {
            return false;
        }
        if (!empty($filter_size) && $ship['ship_size'] !== $filter_size) {
            return false;
        }
        if ($show_lti_only && !$ship['has_lti']) {
            return false;
        }
        return true;
    });
}

// Sayfalama
$ships_per_page = 12; // 20'den 12'ye düşürdük
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_ships = count($scg_ships);
$total_pages = max(1, ceil($total_ships / $ships_per_page));
$offset = ($current_page - 1) * $ships_per_page;
$paginated_ships = array_slice($scg_ships, $offset, $ships_per_page);

$page_title = "SCG Hangar Görüntüleme";

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="/profile/css/hangar.css">
<link rel="stylesheet" href="css/scg-hangar.css">
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
            <li class="breadcrumb-item active">
                <i class="fas fa-warehouse"></i> SCG Hangar
            </li>
        </ol>
    </nav>

    <div class="scg-hangar-container">
        <!-- SCG Hangar Header -->
        <div class="scg-hangar-header">
            <div class="scg-header-content">
                <h1 class="scg-hangar-title">
                    <i class="fas fa-warehouse"></i> 
                    SCG Hangar Görüntüleme
                </h1>
                <p class="scg-hangar-description">
                    Star Citizen Global üyelerinin hangar koleksiyonunu görüntüleyin.
                </p>
            </div>
            <div class="scg-role-badge">
                <i class="fas fa-users"></i>
                SCG Exclusive
            </div>
        </div>

        <!-- SCG İstatistikleri -->
        <div class="scg-stats-grid">
            <div class="scg-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-space-shuttle"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($scg_stats['total_ships']) ?></span>
                    <span class="stat-label">Toplam Gemi</span>
                </div>
            </div>
            <div class="scg-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($scg_stats['unique_ships']) ?></span>
                    <span class="stat-label">Benzersiz Gemi</span>
                </div>
            </div>
            <div class="scg-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-industry"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= $scg_stats['manufacturers'] ?></span>
                    <span class="stat-label">Üretici</span>
                </div>
            </div>
            <div class="scg-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= number_format($scg_stats['lti_ships']) ?></span>
                    <span class="stat-label">LTI Gemi</span>
                </div>
            </div>
            <div class="scg-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= $scg_stats['scg_members_with_ships'] ?></span>
                    <span class="stat-label">Üye (Gemi Sahibi)</span>
                </div>
            </div>
        </div>

        <!-- Filtreleme Formu -->
        <div class="scg-filter-section">
            <h3><i class="fas fa-filter"></i> Filtreleme</h3>
            <form method="GET" class="scg-filter-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="user">Kullanıcı</label>
                        <input type="text" id="user" name="user" value="<?= htmlspecialchars($filter_user) ?>" 
                               placeholder="Kullanıcı adı...">
                    </div>
                    <div class="filter-group">
                        <label for="manufacturer">Üretici</label>
                        <input type="text" id="manufacturer" name="manufacturer" value="<?= htmlspecialchars($filter_manufacturer) ?>" 
                               placeholder="Üretici adı...">
                    </div>
                    <div class="filter-group">
                        <label for="size">Gemi Boyutu</label>
                        <select id="size" name="size">
                            <option value="">Tüm Boyutlar</option>
                            <option value="vehicle" <?= $filter_size === 'vehicle' ? 'selected' : '' ?>>Vehicle</option>
                            <option value="small" <?= $filter_size === 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= $filter_size === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= $filter_size === 'large' ? 'selected' : '' ?>>Large</option>
                            <option value="capital" <?= $filter_size === 'capital' ? 'selected' : '' ?>>Capital</option>
                        </select>
                    </div>
                    <div class="filter-group filter-checkbox">
                        <label class="checkbox-label">
                            <input type="checkbox" name="lti_only" value="1" <?= $show_lti_only ? 'checked' : '' ?>>
                            <span class="checkmark"></span>
                            Sadece LTI Gemiler
                        </label>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrele
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Temizle
                    </a>
                </div>
            </form>
        </div>
 <!-- Görünüm Geçiş Butonları -->
 <div class="view-toggle-section">
            <div class="view-toggle-buttons">
                <button class="toggle-btn active" id="hangarViewBtn" onclick="switchView('hangar')">
                    <i class="fas fa-th-large"></i>
                    <span>Hangar Kartları</span>
                </button>
                <button class="toggle-btn" id="summaryViewBtn" onclick="switchView('summary')">
                    <i class="fas fa-list-ul"></i>
                    <span>Özet Listesi</span>
                </button>
            </div>
        </div>
        <!-- Gemi Özet Listesi -->
        <div class="scg-ship-summary-section" id="summarySection" style="display: none;">
            <h3><i class="fas fa-list-ul"></i> Gemi Özet Listesi</h3>
            <div class="ship-summary-grid">
                <?php
                // Gemileri isim ve miktara göre grupla
                $ship_summary = [];
                foreach ($scg_ships as $ship) {
                    $ship_name = $ship['ship_name'];
                    if (!isset($ship_summary[$ship_name])) {
                        $ship_summary[$ship_name] = [
                            'total_quantity' => 0,
                            'lti_quantity' => 0,
                            'manufacturer' => $ship['ship_manufacturer'],
                            'size' => $ship['ship_size'],
                            'image_url' => $ship['ship_image_url']
                        ];
                    }
                    $ship_summary[$ship_name]['total_quantity'] += $ship['quantity'];
                    if ($ship['has_lti']) {
                        $ship_summary[$ship_name]['lti_quantity'] += $ship['quantity'];
                    }
                }
                
                // Toplam miktara göre sırala (büyükten küçüğe)
                uasort($ship_summary, function($a, $b) {
                    return $b['total_quantity'] - $a['total_quantity'];
                });
                ?>
                
                <?php if (empty($ship_summary)): ?>
                <div class="no-ships-summary">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Filtrelenen gemiler için özet bulunamadı.</p>
                </div>
                <?php else: ?>
                <?php foreach ($ship_summary as $ship_name => $data): ?>
                <div class="ship-summary-item">
                    <div class="ship-summary-image">
                        <?php if (!empty($data['image_url'])): ?>
                        <img data-src="<?= htmlspecialchars($data['image_url']) ?>" 
                             src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Crect width='100%25' height='100%25' fill='%23333'/%3E%3Ctext x='50%25' y='50%25' fill='%23666' text-anchor='middle' dominant-baseline='middle' font-size='10'%3E...%3C/text%3E%3C/svg%3E"
                             alt="<?= htmlspecialchars($ship_name) ?>" 
                             class="ship-summary-img lazy"
                             loading="lazy">
                        <?php else: ?>
                        <div class="ship-image-placeholder">
                            <i class="fas fa-space-shuttle"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ship-summary-info">
                        <h4 class="ship-summary-name"><?= htmlspecialchars($ship_name) ?></h4>
                        <div class="ship-summary-details">
                            <?php if (!empty($data['manufacturer'])): ?>
                            <span class="ship-summary-manufacturer">
                                <i class="fas fa-industry"></i>
                                <?= htmlspecialchars($data['manufacturer']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($data['size'])): ?>
                            <span class="ship-summary-size">
                                <i class="fas fa-expand-arrows-alt"></i>
                                <?= ucfirst($data['size']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ship-summary-stats">
                        <div class="summary-stat total">
                            <span class="stat-number"><?= $data['total_quantity'] ?></span>
                            <span class="stat-label">Toplam</span>
                        </div>
                        
                        <?php if ($data['lti_quantity'] > 0): ?>
                        <div class="summary-stat lti">
                            <span class="stat-number"><?= $data['lti_quantity'] ?></span>
                            <span class="stat-label">LTI</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-stat non-lti">
                            <span class="stat-number"><?= ($data['total_quantity'] - $data['lti_quantity']) ?></span>
                            <span class="stat-label">Normal</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

       

        <!-- Gemi Listesi -->
        <div class="scg-ships-section" id="hangarSection">
            <div class="scg-ships-header">
                <h3>
                    <i class="fas fa-space-shuttle"></i> 
                    SCG Hangar Gemileri 
                    <span class="ships-count">(<?= number_format(count($scg_ships)) ?>)</span>
                </h3>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination-info">
                    Sayfa <?= $current_page ?> / <?= $total_pages ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($paginated_ships)): ?>
            <div class="no-ships">
                <div class="no-ships-icon">
                    <i class="fas fa-space-shuttle"></i>
                </div>
                <h4>Gemi Bulunamadı</h4>
                <p>Seçilen filtrelere uygun gemi bulunmamaktadır.</p>
            </div>
            <?php else: ?>
            <div class="scg-ships-grid">
                <?php foreach ($paginated_ships as $ship): ?>
                <div class="scg-ship-card">
                    <div class="ship-card-header">
                        <div class="ship-image-container">
                            <?php if (!empty($ship['ship_image_url'])): ?>
                            <img data-src="<?= htmlspecialchars($ship['ship_image_url']) ?>" 
                                 src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='350' height='200'%3E%3Crect width='100%25' height='100%25' fill='%23333'/%3E%3Ctext x='50%25' y='50%25' fill='%23666' text-anchor='middle' dominant-baseline='middle' font-family='Arial'%3EYükleniyor...%3C/text%3E%3C/svg%3E"
                                 alt="<?= htmlspecialchars($ship['ship_name']) ?>" 
                                 class="ship-image lazy"
                                 loading="lazy">
                            <?php else: ?>
                            <div class="ship-image-placeholder">
                                <i class="fas fa-space-shuttle"></i>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($ship['has_lti']): ?>
                            <div class="lti-badge">
                                <i class="fas fa-shield-alt"></i>
                                LTI
                            </div>
                            <?php endif; ?>
                            
                            <div class="quantity-badge">
                                x<?= $ship['quantity'] ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ship-card-body">
                        <h4 class="ship-name"><?= htmlspecialchars($ship['ship_name']) ?></h4>
                        <p class="ship-manufacturer"><?= htmlspecialchars($ship['ship_manufacturer']) ?></p>
                        
                        <div class="ship-specs">
                            <?php if (!empty($ship['ship_focus'])): ?>
                            <span class="ship-spec">
                                <i class="fas fa-crosshairs"></i>
                                <?= htmlspecialchars($ship['ship_focus']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($ship['ship_size'])): ?>
                            <span class="ship-spec">
                                <i class="fas fa-expand-arrows-alt"></i>
                                <?= ucfirst($ship['ship_size']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ship-owner">
                            <div class="owner-avatar">
                                <?php 
                                $avatar_path = $ship['avatar_path'];
                                if (empty($avatar_path)) {
                                    $avatar_path = '/assets/logo.png';
                                } elseif (strpos($avatar_path, '../assets/') === 0) {
                                    $avatar_path = str_replace('../assets/', '/assets/', $avatar_path);
                                } elseif (strpos($avatar_path, 'uploads/') === 0) {
                                    $avatar_path = '/' . $avatar_path;
                                }
                                ?>
                                <img src="<?= htmlspecialchars($avatar_path) ?>" 
                                     alt="<?= htmlspecialchars($ship['username']) ?>" 
                                     loading="lazy">
                            </div>
                            <div class="owner-info">
                                <span class="owner-name">
                                    <?= htmlspecialchars($ship['username']) ?>
                                </span>
                                <span class="owner-username">@<?= htmlspecialchars($ship['username']) ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($ship['user_notes'])): ?>
                        <div class="ship-notes">
                            <i class="fas fa-sticky-note"></i>
                            <?= htmlspecialchars($ship['user_notes']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="ship-meta">
                            <span class="added-date">
                                <i class="fas fa-calendar-plus"></i>
                                <?= date('d.m.Y', strtotime($ship['added_at'])) ?>
                            </span>
                            <span class="source-type <?= $ship['source_type'] ?>">
                                <?php
                                switch($ship['source_type']) {
                                    case 'api': echo '<i class="fas fa-plug"></i> API'; break;
                                    case 'manual': echo '<i class="fas fa-hand-paper"></i> Manuel'; break;
                                    default: echo '<i class="fas fa-question"></i> Legacy'; break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Sayfalama -->
            <?php if ($total_pages > 1): ?>
            <div class="scg-pagination">
                <nav class="pagination-nav">
                    <?php if ($current_page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" class="page-btn">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="page-btn <?= $i === $current_page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" class="page-btn">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-btn">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Debug Bilgileri (sadece admin için)
        <?php if (has_permission($pdo, 'admin.panel.access')): ?>
        <div class="debug-section" style="margin-top: 2rem; padding: 1rem; background: var(--card-bg-3); border: 1px solid var(--border-1); border-radius: 8px;">
            <h4 style="color: var(--red); margin-bottom: 1rem;">
                <i class="fas fa-bug"></i> Debug Bilgileri (Sadece Admin)
            </h4>
            <div style="color: var(--light-grey); font-size: 0.85rem; font-family: monospace;">
                <p><strong>Toplam SCG gemileri:</strong> <?= count($scg_ships) ?></p>
                <p><strong>Sayfalama sonrası:</strong> <?= count($paginated_ships) ?></p>
                <p><strong>Uygulanan filtreler:</strong></p>
                <ul style="margin-left: 1rem;">
                    <li>Kullanıcı: <?= $filter_user ?: 'Yok' ?></li>
                    <li>Üretici: <?= $filter_manufacturer ?: 'Yok' ?></li>
                    <li>Boyut: <?= $filter_size ?: 'Yok' ?></li>
                    <li>Sadece LTI: <?= $show_lti_only ? 'Evet' : 'Hayır' ?></li>
                </ul>
                <p><strong>İlk 3 gemi (varsa):</strong></p>
                <pre style="background: var(--card-bg); padding: 0.5rem; border-radius: 4px; max-height: 200px; overflow-y: auto;">
<?php 
if (!empty($scg_ships)) {
    echo htmlspecialchars(print_r(array_slice($scg_ships, 0, 3), true));
} else {
    echo "Hiç gemi bulunamadı";
}
?>
                </pre>
            </div>
        </div>
        <?php endif; ?> -->

<!-- SCG Üyeleri Bölümü -->
<div class="scg-members-section">
    <h3><i class="fas fa-users"></i> SCG Üyeleri (<?= count($scg_members) ?>)</h3>
    
    <?php if (empty($scg_members)): ?>
    <div class="no-members">
        <div class="no-members-icon">
            <i class="fas fa-users-slash"></i>
        </div>
        <h4>Henüz SCG Üyesi Bulunamadı</h4>
        <p>SCG rolüne sahip aktif üye bulunmamaktadır.</p>
    </div>
    <?php else: ?>
    <div class="scg-members-grid">
        <?php foreach ($scg_members as $member): ?>
        <div class="scg-member-card">
            <div class="member-avatar">
                <?php 
                $avatar_path = $member['avatar_path'];
                if (empty($avatar_path)) {
                    $avatar_path = '/assets/logo.png';
                } elseif (strpos($avatar_path, '../assets/') === 0) {
                    $avatar_path = str_replace('../assets/', '/assets/', $avatar_path);
                } elseif (strpos($avatar_path, 'uploads/') === 0) {
                    $avatar_path = '/' . $avatar_path;
                }
                ?>
                <img src="<?= htmlspecialchars($avatar_path) ?>" 
                     alt="<?= htmlspecialchars($member['username']) ?>" 
                     loading="lazy"
                     onerror="this.src='/assets/logo.png'">
            </div>
            <div class="member-info">
                <h4 class="member-name">
                    <?= htmlspecialchars($member['username']) ?>
                </h4>
                <p class="member-username">@<?= htmlspecialchars($member['username']) ?></p>
                <div class="member-stats">
                    <span class="member-stat">
                        <i class="fas fa-space-shuttle"></i>
                        <?= (int)$member['ship_count'] ?> çeşit
                    </span>
                    <span class="member-stat">
                        <i class="fas fa-calculator"></i>
                        <?= (int)$member['total_quantity'] ?> toplam
                    </span>
                </div>
            </div>
            <div class="member-actions">
                <?php if ((int)$member['ship_count'] > 0): ?>
                <a href="?user=<?= urlencode($member['username']) ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-eye"></i> Gemilerini Gör
                </a>
                <?php else: ?>
                <span class="btn btn-sm btn-disabled">
                    <i class="fas fa-ban"></i> Gemi Yok
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
    </div>
</div>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>

<script>
// Görünüm geçiş fonksiyonu
function switchView(viewType) {
    const hangarSection = document.getElementById('hangarSection');
    const summarySection = document.getElementById('summarySection');
    const hangarBtn = document.getElementById('hangarViewBtn');
    const summaryBtn = document.getElementById('summaryViewBtn');
    
    if (viewType === 'summary') {
        // Özet görünümüne geç
        hangarSection.style.opacity = '0';
        hangarSection.style.visibility = 'hidden';
        hangarSection.style.position = 'absolute';
        hangarSection.style.top = '-9999px';
        
        summarySection.style.opacity = '1';
        summarySection.style.visibility = 'visible';
        summarySection.style.position = 'static';
        summarySection.style.top = 'auto';
        summarySection.style.display = 'block';
        
        hangarBtn.classList.remove('active');
        summaryBtn.classList.add('active');
        
        // URL'yi güncelle (opsiyonel)
        const url = new URL(window.location);
        url.searchParams.set('view', 'summary');
        window.history.replaceState({}, '', url);
        
    } else {
        // Hangar görünümüne geç
        summarySection.style.opacity = '0';
        summarySection.style.visibility = 'hidden';
        summarySection.style.position = 'absolute';
        summarySection.style.top = '-9999px';
        
        hangarSection.style.opacity = '1';
        hangarSection.style.visibility = 'visible';
        hangarSection.style.position = 'static';
        hangarSection.style.top = 'auto';
        hangarSection.style.display = 'block';
        
        summaryBtn.classList.remove('active');
        hangarBtn.classList.add('active');
        
        // URL'yi güncelle (opsiyonel)
        const url = new URL(window.location);
        url.searchParams.delete('view');
        window.history.replaceState({}, '', url);
    }
}

// Sayfa yüklendiğinde URL'den görünümü kontrol et
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view');
    
    // Başlangıçta her iki section'ı da hazırla
    const hangarSection = document.getElementById('hangarSection');
    const summarySection = document.getElementById('summarySection');
    
    // CSS transition ekle
    hangarSection.style.transition = 'opacity 0.3s ease, visibility 0.3s ease';
    summarySection.style.transition = 'opacity 0.3s ease, visibility 0.3s ease';
    
    if (viewParam === 'summary') {
        switchView('summary');
    } else {
        switchView('hangar');
    }
    
    // Filtreleme formu gelişmiş işlevsellik
    const filterForm = document.querySelector('.scg-filter-form');
    if (filterForm) {
        const filterInputs = filterForm.querySelectorAll('input, select');
        
        // Otomatik filtreleme (debounce ile)
        let filterTimeout;
        filterInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    if (input.type !== 'checkbox') {
                        filterForm.submit();
                    }
                }, 500);
            });
            
            if (input.type === 'checkbox') {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            }
        });
    }
    
    // Gelişmiş lazy loading sistem
    const lazyImages = document.querySelectorAll('.lazy');
    if (lazyImages.length > 0) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // Yeni image element oluştur (preload için)
                    const newImg = new Image();
                    newImg.onload = function() {
                        // Resim yüklendikten sonra göster
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                    };
                    newImg.onerror = function() {
                        // Hata durumunda placeholder göster
                        img.classList.remove('lazy');
                        img.classList.add('error');
                        img.style.display = 'none';
                        
                        // Placeholder div oluştur
                        const placeholder = document.createElement('div');
                        placeholder.className = 'ship-image-placeholder';
                        placeholder.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        img.parentNode.insertBefore(placeholder, img);
                    };
                    newImg.src = img.dataset.src;
                    
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px', // 50px önceden yüklemeye başla
            threshold: 0.1
        });
        
        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });
    }
});
</script>