<?php
// profile/hangar.php - API Entegrasyonlu Hangar Yönetimi

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Giriş yapma zorunluluğu
require_login();

$current_user_id = $_SESSION['user_id'];
$is_approved = is_user_approved();

// Kullanıcı hangar verilerini çek
$hangar_ships = getUserHangarShips($pdo, $current_user_id);
$hangar_stats = getHangarStatistics($pdo, $current_user_id);

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_ships_from_cart':
                $result = addShipsFromCart($pdo, $current_user_id, $_POST);
                break;
            case 'delete_ship':
                $result = deleteHangarShip($pdo, $current_user_id, $_POST);
                break;
            default:
                $result = ['success' => false, 'message' => 'Geçersiz işlem.'];
        }
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
        } else {
            $error_message = $result['message'];
        }
        
        // Verileri yeniden yükle
        $hangar_ships = getUserHangarShips($pdo, $current_user_id);
        $hangar_stats = getHangarStatistics($pdo, $current_user_id);
    }
}

/**
 * Sepetteki gemileri hangara ekleme
 */
function addShipsFromCart(PDO $pdo, int $user_id, array $post_data): array {
    try {
        $cart_ships = json_decode($post_data['cart_ships'] ?? '[]', true);
        
        if (empty($cart_ships)) {
            return ['success' => false, 'message' => 'Sepet boş.'];
        }
        
        $pdo->beginTransaction();
        
        $added_count = 0;
        $errors = [];
        
        foreach ($cart_ships as $ship_data) {
            try {
                $ship_api_id = 'api_' . $ship_data['id'];
                $quantity = max(1, (int)($ship_data['quantity'] ?? 1));
                
                // Gemi bilgilerini hazırla
                $ship_name = $ship_data['name'] ?? 'Bilinmeyen Gemi';
                $ship_manufacturer = $ship_data['manufacturer']['name'] ?? '';
                $ship_focus = $ship_data['focus'] ?? '';
                $ship_size = ucfirst($ship_data['size'] ?? '');
                
                // Resim URL'sini bul
                $ship_image_url = null;
                if (!empty($ship_data['media'])) {
                    foreach ($ship_data['media'] as $media) {
                        if (isset($media['source_url']) && !empty($media['source_url'])) {
                            $ship_image_url = $media['source_url'];
                            break;
                        }
                    }
                }
                
                $query = "
                    INSERT INTO user_hangar 
                    (user_id, ship_api_id, ship_name, ship_manufacturer, ship_focus, ship_size, ship_image_url, quantity)
                    VALUES (:user_id, :ship_api_id, :ship_name, :ship_manufacturer, :ship_focus, :ship_size, :ship_image_url, :quantity)
                    ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity)
                ";
                
                $params = [
                    ':user_id' => $user_id,
                    ':ship_api_id' => $ship_api_id,
                    ':ship_name' => $ship_name,
                    ':ship_manufacturer' => $ship_manufacturer ?: null,
                    ':ship_focus' => $ship_focus ?: null,
                    ':ship_size' => $ship_size ?: null,
                    ':ship_image_url' => $ship_image_url,
                    ':quantity' => $quantity
                ];
                
                execute_safe_query($pdo, $query, $params);
                $added_count++;
                
            } catch (Exception $e) {
                $errors[] = $ship_data['name'] . ': ' . $e->getMessage();
                error_log("Error adding ship {$ship_data['name']}: " . $e->getMessage());
            }
        }
        
        $pdo->commit();
        
        if ($added_count > 0) {
            $message = "$added_count gemi başarıyla hangarınıza eklendi.";
            if (!empty($errors)) {
                $message .= " Bazı gemiler eklenirken hata oluştu.";
            }
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'Hiçbir gemi eklenemedi: ' . implode(', ', $errors)];
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add ships from cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gemiler eklenirken bir hata oluştu.'];
    }
}

/**
 * Kullanıcının hangar gemilerini çeker
 */
function getUserHangarShips(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT id, ship_api_id, ship_name, ship_manufacturer, ship_focus, 
                   ship_size, ship_image_url, quantity, user_notes, added_at,
                   CASE 
                       WHEN ship_api_id LIKE 'api_%' THEN 'api'
                       WHEN ship_api_id LIKE 'manual_%' THEN 'manual'
                       ELSE 'legacy'
                   END as source_type
            FROM user_hangar 
            WHERE user_id = :user_id 
            ORDER BY added_at DESC, ship_manufacturer ASC, ship_name ASC
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll() ?: [];
        
    } catch (Exception $e) {
        error_log("Hangar ships error: " . $e->getMessage());
        return [];
    }
}

/**
 * Hangar istatistikleri
 */
function getHangarStatistics(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT 
                COUNT(*) as unique_ships,
                SUM(quantity) as total_ships,
                COUNT(DISTINCT ship_manufacturer) as manufacturers,
                COUNT(DISTINCT ship_size) as sizes
            FROM user_hangar 
            WHERE user_id = :user_id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        $result = $stmt->fetch();
        
        return [
            'unique_ships' => (int)($result['unique_ships'] ?? 0),
            'total_ships' => (int)($result['total_ships'] ?? 0),
            'manufacturers' => (int)($result['manufacturers'] ?? 0),
            'sizes' => (int)($result['sizes'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Hangar stats error: " . $e->getMessage());
        return ['unique_ships' => 0, 'total_ships' => 0, 'manufacturers' => 0, 'sizes' => 0];
    }
}


/**
 * Hangar gemisini silme
 */
function deleteHangarShip(PDO $pdo, int $user_id, array $post_data): array {
    try {
        $ship_id = (int)($post_data['ship_id'] ?? 0);
        
        if (!$ship_id) {
            return ['success' => false, 'message' => 'Geçersiz gemi ID.'];
        }
        
        $query = "DELETE FROM user_hangar WHERE id = :ship_id AND user_id = :user_id";
        $stmt = execute_safe_query($pdo, $query, [':ship_id' => $ship_id, ':user_id' => $user_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Gemi bulunamadı veya size ait değil.'];
        }
        
        return ['success' => true, 'message' => 'Gemi hangarınızdan kaldırıldı.'];
        
    } catch (Exception $e) {
        error_log("Delete ship error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gemi silinirken bir hata oluştu.'];
    }
}

$page_title = "Hangar Yönetimi - " . ($_SESSION['username'] ?? 'Profil');

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="css/hangar.css">
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
                <a href="/profile.php">
                    <i class="fas fa-user"></i> Profil
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-space-shuttle"></i> Hangar
            </li>
        </ol>
    </nav>

    <div class="profile-container">
        <!-- Sidebar -->
        <?php include '../src/includes/profile_sidebar.php'; ?>

        <!-- Ana İçerik -->
        <div class="profile-main-content">
            <!-- Hangar Header -->
            <div class="hangar-header">
                <div class="hangar-title-section">
                    <h1 class="hangar-title">
                        <i class="fas fa-space-shuttle"></i> Hangar Yönetimi
                    </h1>
                    <p class="hangar-description">
                        Star Citizen gemilerini arayın, sepete ekleyin ve hangarınıza kaydedin.
                    </p>
                </div>
                
                <div class="hangar-actions">
                    <button class="btn btn-primary" onclick="openShipSearchModal()">
                        <i class="fas fa-search"></i> Gemi Ara & Ekle
                    </button>
                    <button class="btn btn-cart" id="cartButton" onclick="openCartModal()" style="display: none;">
                        <i class="fas fa-shopping-cart"></i> Sepet (<span id="cartCount">0</span>)
                    </button>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- Hangar İstatistikleri -->
            <div class="hangar-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= number_format($hangar_stats['unique_ships']) ?></div>
                        <div class="stat-label">Farklı Gemi</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= number_format($hangar_stats['total_ships']) ?></div>
                        <div class="stat-label">Toplam Gemi</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-industry"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= number_format($hangar_stats['manufacturers']) ?></div>
                        <div class="stat-label">Üretici</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= number_format($hangar_stats['sizes']) ?></div>
                        <div class="stat-label">Boyut Çeşidi</div>
                    </div>
                </div>
            </div>

            <!-- Hangar Gemileri -->
            <div class="hangar-ships-section">
                <h3 class="section-title">
                    <i class="fas fa-list"></i> Hangar Gemilerim
                </h3>
                
                <?php if (empty($hangar_ships)): ?>
                    <div class="empty-hangar">
                        <div class="empty-hangar-icon">
                            <i class="fas fa-space-shuttle"></i>
                        </div>
                        <h4>Hangarınız Boş</h4>
                        <p>Henüz hangarınıza gemi eklememişsiniz. Star Citizen API'sinden gemi arayıp sepete ekleyin.</p>
                        <button class="btn btn-primary" onclick="openShipSearchModal()">
                            <i class="fas fa-search"></i> Gemi Ara & Ekle
                        </button>
                    </div>
                <?php else: ?>
                    <div class="ships-grid">
                        <?php foreach ($hangar_ships as $ship): ?>
                            <div class="ship-card" data-ship-id="<?= $ship['id'] ?>">
                                <div class="ship-card-header">
                                    <div class="ship-image">
                                        <?php if (!empty($ship['ship_image_url'])): ?>
                                            <img src="<?= htmlspecialchars($ship['ship_image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($ship['ship_name']) ?>"
                                                 onerror="this.src='/assets/ship-placeholder.png'">
                                        <?php else: ?>
                                            <div class="ship-placeholder">
                                                <i class="fas fa-space-shuttle"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ship-actions">
                                        <button class="action-btn delete-btn" onclick="deleteShip(<?= $ship['id'] ?>)" title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="ship-card-body">
                                    <h4 class="ship-name"><?= htmlspecialchars($ship['ship_name']) ?></h4>
                                    
                                    <div class="ship-details">
                                        <?php if (!empty($ship['ship_manufacturer'])): ?>
                                            <div class="ship-detail">
                                                <span class="detail-label">Üretici:</span>
                                                <span class="detail-value"><?= htmlspecialchars($ship['ship_manufacturer']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($ship['ship_size'])): ?>
                                            <div class="ship-detail">
                                                <span class="detail-label">Boyut:</span>
                                                <span class="detail-value"><?= htmlspecialchars($ship['ship_size']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($ship['ship_focus'])): ?>
                                            <div class="ship-detail">
                                                <span class="detail-label">Odak:</span>
                                                <span class="detail-value"><?= htmlspecialchars($ship['ship_focus']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="ship-detail">
                                            <span class="detail-label">Adet:</span>
                                            <span class="detail-value quantity"><?= number_format($ship['quantity']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($ship['user_notes'])): ?>
                                        <div class="ship-notes">
                                            <i class="fas fa-sticky-note"></i>
                                            <?= nl2br(htmlspecialchars($ship['user_notes'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ship-added-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d.m.Y H:i', strtotime($ship['added_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Gemi Arama Modal -->
<div id="shipSearchModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeShipSearchModal()"></div>
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3>Star Citizen Gemi Arama</h3>
            <button class="modal-close" onclick="closeShipSearchModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <!-- Arama Formu -->
            <div class="search-form">
                <div class="search-grid">
                    <div class="form-group">
                        <label for="search_name" class="form-label">
                            <i class="fas fa-rocket"></i> Gemi Adı
                        </label>
                        <input type="text" id="search_name" class="form-input" placeholder="Örn: Mustang, Constellation...">
                    </div>
                    
                    <div class="form-group">
                        <label for="search_classification" class="form-label">
                            <i class="fas fa-filter"></i> Sınıflandırma
                        </label>
                        <select id="search_classification" class="form-input">
                            <option value="">Tümü</option>
                            <option value="combat">Combat</option>
                            <option value="transport">Transport</option>
                            <option value="exploration">Exploration</option>
                            <option value="industrial">Industrial</option>
                            <option value="support">Support</option>
                            <option value="competition">Competition</option>
                            <option value="ground">Ground</option>
                            <option value="multi">Multi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" onclick="searchShips()" class="btn btn-primary btn-search">
                            <i class="fas fa-search"></i> Ara
                        </button>
                        <button type="button" onclick="clearSearch()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Temizle
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Yükleme İndikatörü -->
            <div id="searchLoading" class="search-loading" style="display: none;">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <p>API'den gemiler getiriliyor...</p>
            </div>
            
            <!-- Arama Sonuçları -->
            <div id="searchResults" class="search-results">
                <div class="search-help">
                    <i class="fas fa-info-circle"></i>
                    Gemi aramak için yukarıdaki alanları kullanın. Boş bırakırsanız tüm gemiler listelenir.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sepet Modal -->
<div id="cartModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeCartModal()"></div>
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>
                <i class="fas fa-shopping-cart"></i> Sepetim
            </h3>
            <button class="modal-close" onclick="closeCartModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div id="cartItems" class="cart-items">
                <!-- Sepet öğeleri buraya yüklenecek -->
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" onclick="saveCartToHangar()" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Hangarıma Kaydet
            </button>
            <button type="button" onclick="clearCart()" class="btn btn-danger">
                <i class="fas fa-trash"></i> Sepeti Temizle
            </button>
            <button type="button" onclick="closeCartModal()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Kapat
            </button>
        </div>
    </div>
</div>

<script src="js/hangar.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>