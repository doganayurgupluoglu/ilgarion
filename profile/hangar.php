<?php
// profile/hangar.php - Hangar Yönetimi Sayfası

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
            case 'add_ship':
                $result = addShipToHangar($pdo, $current_user_id, $_POST);
                break;
            case 'update_ship':
                $result = updateHangarShip($pdo, $current_user_id, $_POST);
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

$page_title = "Hangar Yönetimi - " . ($_SESSION['username'] ?? 'Profil');

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Kullanıcının hangar gemilerini çeker
 */
function getUserHangarShips(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT id, ship_api_id, ship_name, ship_manufacturer, ship_focus, 
                   ship_size, ship_image_url, quantity, user_notes, added_at
            FROM user_hangar 
            WHERE user_id = :user_id 
            ORDER BY ship_manufacturer ASC, ship_name ASC
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll() ?: [];
        
    } catch (Exception $e) {
        error_log("Hangar ships error: " . $e->getMessage());
        return [];
    }
}

/**
 * Hangar istatistiklerini çeker
 */
function getHangarStatistics(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT 
                COUNT(*) as unique_ships,
                SUM(quantity) as total_ships,
                COUNT(DISTINCT ship_manufacturer) as manufacturers,
                GROUP_CONCAT(DISTINCT ship_size ORDER BY ship_size) as sizes
            FROM user_hangar 
            WHERE user_id = :user_id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        $result = $stmt->fetch();
        
        return [
            'unique_ships' => (int)($result['unique_ships'] ?? 0),
            'total_ships' => (int)($result['total_ships'] ?? 0),
            'manufacturers' => (int)($result['manufacturers'] ?? 0),
            'sizes' => $result['sizes'] ? explode(',', $result['sizes']) : []
        ];
        
    } catch (Exception $e) {
        error_log("Hangar stats error: " . $e->getMessage());
        return ['unique_ships' => 0, 'total_ships' => 0, 'manufacturers' => 0, 'sizes' => []];
    }
}

/**
 * Hangara gemi ekleme
 */
function addShipToHangar(PDO $pdo, int $user_id, array $post_data): array {
    try {
        $ship_name = trim($post_data['ship_name'] ?? '');
        $ship_manufacturer = trim($post_data['ship_manufacturer'] ?? '');
        $ship_focus = trim($post_data['ship_focus'] ?? '');
        $ship_size = trim($post_data['ship_size'] ?? '');
        $quantity = max(1, (int)($post_data['quantity'] ?? 1));
        $user_notes = trim($post_data['user_notes'] ?? '');
        
        if (empty($ship_name)) {
            return ['success' => false, 'message' => 'Gemi adı gereklidir.'];
        }
        
        // API ID oluştur (basit slug)
        $ship_api_id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $ship_name));
        
        $query = "
            INSERT INTO user_hangar 
            (user_id, ship_api_id, ship_name, ship_manufacturer, ship_focus, ship_size, quantity, user_notes)
            VALUES (:user_id, :ship_api_id, :ship_name, :ship_manufacturer, :ship_focus, :ship_size, :quantity, :user_notes)
            ON DUPLICATE KEY UPDATE
            quantity = quantity + VALUES(quantity),
            user_notes = VALUES(user_notes),
            added_at = CURRENT_TIMESTAMP
        ";
        
        $params = [
            ':user_id' => $user_id,
            ':ship_api_id' => $ship_api_id,
            ':ship_name' => $ship_name,
            ':ship_manufacturer' => $ship_manufacturer ?: null,
            ':ship_focus' => $ship_focus ?: null,
            ':ship_size' => $ship_size ?: null,
            ':quantity' => $quantity,
            ':user_notes' => $user_notes ?: null
        ];
        
        execute_safe_query($pdo, $query, $params);
        
        return ['success' => true, 'message' => 'Gemi başarıyla hangarınıza eklendi.'];
        
    } catch (Exception $e) {
        error_log("Add ship error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gemi eklenirken bir hata oluştu.'];
    }
}

/**
 * Hangar gemisini güncelleme
 */
function updateHangarShip(PDO $pdo, int $user_id, array $post_data): array {
    try {
        $ship_id = (int)($post_data['ship_id'] ?? 0);
        $quantity = max(1, (int)($post_data['quantity'] ?? 1));
        $user_notes = trim($post_data['user_notes'] ?? '');
        
        if (!$ship_id) {
            return ['success' => false, 'message' => 'Geçersiz gemi ID.'];
        }
        
        $query = "
            UPDATE user_hangar 
            SET quantity = :quantity, user_notes = :user_notes
            WHERE id = :ship_id AND user_id = :user_id
        ";
        
        $params = [
            ':quantity' => $quantity,
            ':user_notes' => $user_notes ?: null,
            ':ship_id' => $ship_id,
            ':user_id' => $user_id
        ];
        
        $stmt = execute_safe_query($pdo, $query, $params);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Gemi bulunamadı veya size ait değil.'];
        }
        
        return ['success' => true, 'message' => 'Gemi bilgileri güncellendi.'];
        
    } catch (Exception $e) {
        error_log("Update ship error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gemi güncellenirken bir hata oluştu.'];
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
?>

<link rel="stylesheet" href="/css/profile.css">
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
                        Star Citizen gemilerinizi yönetin ve koleksiyonunuzu sergileyin.
                    </p>
                </div>
                
                <div class="hangar-actions">
                    <button class="btn btn-primary" onclick="openAddShipModal()">
                        <i class="fas fa-plus"></i> Gemi Ekle
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
                        <div class="stat-number"><?= count($hangar_stats['sizes']) ?></div>
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
                        <p>Henüz hangarınıza gemi eklememişsiniz. İlk geminizi eklemek için yukarıdaki "Gemi Ekle" butonunu kullanın.</p>
                        <button class="btn btn-primary" onclick="openAddShipModal()">
                            <i class="fas fa-plus"></i> İlk Gemi Ekle
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
                                        <button class="action-btn edit-btn" onclick="editShip(<?= $ship['id'] ?>)" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
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
                                        <?= date('d.m.Y', strtotime($ship['added_at'])) ?>
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

<!-- Gemi Ekleme/Düzenleme Modal -->
<div id="shipModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeShipModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Gemi Ekle</h3>
            <button class="modal-close" onclick="closeShipModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="shipForm" method="POST">
            <input type="hidden" name="action" id="formAction" value="add_ship">
            <input type="hidden" name="ship_id" id="shipId" value="">
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="ship_name" class="form-label">
                            <i class="fas fa-rocket"></i> Gemi Adı *
                        </label>
                        <input type="text" id="ship_name" name="ship_name" class="form-input" required maxlength="255">
                    </div>
                    
                    <div class="form-group">
                        <label for="ship_manufacturer" class="form-label">
                            <i class="fas fa-industry"></i> Üretici
                        </label>
                        <select id="ship_manufacturer" name="ship_manufacturer" class="form-input">
                            <option value="">Seçiniz...</option>
                            <option value="Aegis Dynamics">Aegis Dynamics</option>
                            <option value="Anvil Aerospace">Anvil Aerospace</option>
                            <option value="Argo Astronautics">Argo Astronautics</option>
                            <option value="Banu">Banu</option>
                            <option value="Crusader Industries">Crusader Industries</option>
                            <option value="Drake Interplanetary">Drake Interplanetary</option>
                            <option value="Esperia">Esperia</option>
                            <option value="MISC">Musashi Industrial & Starflight Concern</option>
                            <option value="Origin Jumpworks">Origin Jumpworks</option>
                            <option value="RSI">Roberts Space Industries</option>
                            <option value="Vanduul">Vanduul</option>
                            <option value="Xi'an">Xi'an</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ship_size" class="form-label">
                            <i class="fas fa-expand-arrows-alt"></i> Boyut
                        </label>
                        <select id="ship_size" name="ship_size" class="form-input">
                            <option value="">Seçiniz...</option>
                            <option value="Snub">Snub</option>
                            <option value="Small">Small</option>
                            <option value="Medium">Medium</option>
                            <option value="Large">Large</option>
                            <option value="Capital">Capital</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ship_focus" class="form-label">
                            <i class="fas fa-crosshairs"></i> Odak Alanı
                        </label>
                        <select id="ship_focus" name="ship_focus" class="form-input">
                            <option value="">Seçiniz...</option>
                            <option value="Combat">Combat</option>
                            <option value="Exploration">Exploration</option>
                            <option value="Transport">Transport</option>
                            <option value="Industrial">Industrial</option>
                            <option value="Support">Support</option>
                            <option value="Racing">Racing</option>
                            <option value="Multi-Role">Multi-Role</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity" class="form-label">
                            <i class="fas fa-hashtag"></i> Adet
                        </label>
                        <input type="number" id="quantity" name="quantity" class="form-input" min="1" value="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="user_notes" class="form-label">
                        <i class="fas fa-sticky-note"></i> Notlar
                    </label>
                    <textarea id="user_notes" name="user_notes" class="form-textarea" rows="3" maxlength="500" placeholder="Bu gemi hakkında notlarınız..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeShipModal()">
                    <i class="fas fa-times"></i> İptal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddShipModal() {
    document.getElementById('modalTitle').textContent = 'Gemi Ekle';
    document.getElementById('formAction').value = 'add_ship';
    document.getElementById('shipId').value = '';
    document.getElementById('shipForm').reset();
    document.getElementById('shipModal').style.display = 'flex';
}

function editShip(shipId) {
    const shipCard = document.querySelector(`[data-ship-id="${shipId}"]`);
    if (!shipCard) return;
    
    // Mevcut gemi verilerini modal'a doldur
    document.getElementById('modalTitle').textContent = 'Gemi Düzenle';
    document.getElementById('formAction').value = 'update_ship';
    document.getElementById('shipId').value = shipId;
    
    // Form verilerini doldur (bu kısım gerçek implementasyonda AJAX ile yapılmalı)
    const shipName = shipCard.querySelector('.ship-name').textContent;
    document.getElementById('ship_name').value = shipName;
    
    document.getElementById('shipModal').style.display = 'flex';
}

function deleteShip(shipId) {
    if (!confirm('Bu gemiyi hangarınızdan kaldırmak istediğinizden emin misiniz?')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_ship">
        <input type="hidden" name="ship_id" value="${shipId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function closeShipModal() {
    document.getElementById('shipModal').style.display = 'none';
}

// Modal dışı tıklama ile kapatma
document.getElementById('shipModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeShipModal();
    }
});

// ESC tuşu ile modal kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeShipModal();
    }
});
</script>

<style>
/* Hangar özel stilleri */
.hangar-header {
    background: linear-gradient(135deg, var(--card-bg), var(--card-bg-2));
    border: 1px solid var(--border-1);
    border-radius: 12px;
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.hangar-title {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 600;
    color: var(--gold);
}

.hangar-description {
    margin: 0;
    color: var(--light-grey);
    font-size: 1.1rem;
}

.alert-success {
    background: var(--transparent-turquase);
    border: 1px solid var(--turquase);
    color: var(--turquase);
}

.hangar-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.hangar-stats .stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease;
}

.hangar-stats .stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--charcoal);
    font-size: 1.5rem;
}

.hangar-ships-section {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    padding: 2rem;
}

.empty-hangar {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--light-grey);
}

.empty-hangar-icon {
    font-size: 4rem;
    color: var(--border-1-hover);
    margin-bottom: 1rem;
}

.empty-hangar h4 {
    margin: 0 0 1rem 0;
    font-size: 1.5rem;
    color: var(--lighter-grey);
}

.empty-hangar p {
    margin: 0 0 2rem 0;
    font-size: 1.1rem;
}

.ships-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.ship-card {
    background: var(--card-bg-2);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.ship-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    border-color: var(--border-1-hover);
}

.ship-card-header {
    position: relative;
    height: 180px;
    background: var(--card-bg-3);
}

.ship-image {
    width: 100%;
    height: 100%;
}

.ship-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ship-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: var(--border-1-hover);
    background: var(--card-bg);
}

.ship-actions {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.ship-card:hover .ship-actions {
    opacity: 1;
}

.action-btn {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.edit-btn {
    background: rgba(184, 132, 92, 0.9);
    color: var(--charcoal);
}

.edit-btn:hover {
    background: var(--gold);
    transform: scale(1.1);
}

.delete-btn {
    background: rgba(235, 0, 0, 0.9);
    color: var(--white);
}

.delete-btn:hover {
    background: var(--red);
    transform: scale(1.1);
}

.ship-card-body {
    padding: 1.5rem;
}

.ship-name {
    margin: 0 0 1rem 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--gold);
}

.ship-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.ship-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.detail-label {
    color: var(--light-grey);
    font-weight: 500;
}

.detail-value {
    color: var(--lighter-grey);
}

.detail-value.quantity {
    background: var(--card-bg-4);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-weight: 600;
    color: var(--gold);
}

.ship-notes {
    background: var(--card-bg-4);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    color: var(--light-grey);
    line-height: 1.4;
}

.ship-notes i {
    color: var(--gold);
    margin-right: 0.5rem;
}

.ship-added-date {
    font-size: 0.8rem;
    color: var(--light-grey);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-1);
}

/* Modal Stilleri */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: slideUp 0.3s ease;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--gold);
    font-size: 1.4rem;
}

.modal-close {
    background: none;
    border: none;
    color: var(--light-grey);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: var(--transparent-red);
    color: var(--red);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-1);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(50px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .hangar-header {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }
    
    .hangar-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .ships-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .modal-footer {
        flex-direction: column;
    }
}
</style>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>