<?php
// /events/loadouts/create_loadouts.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// Kullanıcı giriş ve onay kontrolü
require_approved_user();

// Kullanıcı yetkisi kontrolü
if (!has_permission($pdo, 'loadout.manage_sets')) {
    $_SESSION['error_message'] = "Teçhizat seti oluşturmak için yetkiniz bulunmuyor.";
    header('Location: ' . '/index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Düzenleme modu kontrolü
$edit_mode = false;
$loadout_set = null;
$loadout_id = 0;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $loadout_id = (int)$_GET['edit'];
    
    try {
        // Mevcut seti çek
        $stmt = $pdo->prepare("
            SELECT * FROM loadout_sets 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $loadout_id,
            ':user_id' => $current_user_id
        ]);
        $loadout_set = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($loadout_set) {
            $edit_mode = true;
            
            // Set itemlerini çek
            $items_stmt = $pdo->prepare("
                SELECT lsi.*, es.slot_name, es.slot_type 
                FROM loadout_set_items lsi
                LEFT JOIN equipment_slots es ON lsi.equipment_slot_id = es.id
                WHERE lsi.loadout_set_id = :set_id
                ORDER BY es.display_order ASC, lsi.id ASC
            ");
            $items_stmt->execute([':set_id' => $loadout_id]);
            $existing_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Loadout edit error: " . $e->getMessage());
        $_SESSION['error_message'] = "Set yüklenirken bir hata oluştu.";
        header('Location: index.php');
        exit;
    }
}

// Equipment slotlarını çek
try {
    $slots_stmt = $pdo->prepare("
        SELECT * FROM equipment_slots 
        WHERE is_standard = 1 
        ORDER BY display_order ASC
    ");
    $slots_stmt->execute();
    $equipment_slots = $slots_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Equipment slots error: " . $e->getMessage());
    $equipment_slots = [];
}

$page_title = $edit_mode ? "Teçhizat Seti Düzenle" : "Yeni Teçhizat Seti Oluştur";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Teçhizat Setleri', 'url' => '/events/loadouts/', 'icon' => 'fas fa-user-astronaut'],
    ['text' => $edit_mode ? 'Seti Düzenle' : 'Yeni Set Oluştur', 'url' => '', 'icon' => $edit_mode ? 'fas fa-edit' : 'fas fa-plus']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Breadcrumb helper function
function generate_loadout_breadcrumb($items) {
    $breadcrumb = '<nav class="breadcrumb-nav"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        $breadcrumb .= '<li class="breadcrumb-item' . ($isLast ? ' active' : '') . '">';
        
        if ($isLast || empty($item['url'])) {
            $breadcrumb .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
        } else {
            $breadcrumb .= '<a href="' . htmlspecialchars($item['url']) . '">';
            $breadcrumb .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
            $breadcrumb .= '</a>';
        }
        
        $breadcrumb .= '</li>';
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}
?>

<link rel="stylesheet" href="css/create_loadout.css">
<link rel="stylesheet" href="../../css/style.css">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">

<div class="site-container">
    <!-- Breadcrumb Navigation -->
    <?= generate_loadout_breadcrumb($breadcrumb_items) ?>

    <div class="loadout-page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1>
                        <i class="fas fa-user-astronaut"></i>
                        <?= $edit_mode ? 'Teçhizat Seti Düzenle' : 'Yeni Teçhizat Seti Oluştur' ?>
                    </h1>
                    <p>Star Citizen için özel teçhizat setleri oluşturun ve paylaşın</p>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
                </div>
            </div>
        </div>

        <div class="loadout-creator">
            <!-- 1. SATIR: Set Bilgileri (Full width) -->
            <div class="loadout-info-panel">
                <h3><i class="fas fa-info-circle"></i> Set Bilgileri</h3>
                <form id="loadoutForm" action="actions/save_loadout.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="loadout_id" value="<?= $loadout_id ?>">
                        <input type="hidden" name="action" value="update">
                    <?php else: ?>
                        <input type="hidden" name="action" value="create">
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1.5rem; align-items: end;">
                        <div class="form-group">
                            <label for="set_name">Set İsmi *</label>
                            <input type="text" id="set_name" name="set_name" required 
                                   value="<?= $edit_mode ? htmlspecialchars($loadout_set['set_name']) : '' ?>"
                                   placeholder="örn: Operasyon Alpha Seti">
                        </div>
                        
                        <div class="form-group">
                            <label for="set_description">Açıklama *</label>
                            <textarea id="set_description" name="set_description" required rows="3"
                                      placeholder="Bu setin ne için kullanıldığını açıklayın..."><?= $edit_mode ? htmlspecialchars($loadout_set['set_description']) : '' ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="visibility">Görünürlük</label>
                            <select id="visibility" name="visibility">
                                <option value="private" <?= ($edit_mode && $loadout_set['visibility'] === 'private') ? 'selected' : '' ?>>Özel (Sadece Ben)</option>
                                <option value="members_only" <?= ($edit_mode && $loadout_set['visibility'] === 'members_only') ? 'selected' : '' ?>>Üyelere Açık</option>
                                <option value="public" <?= ($edit_mode && $loadout_set['visibility'] === 'public') ? 'selected' : '' ?>>Herkese Açık</option>
                            </select>
                        </div>
                        
                        <!-- Kaydet Butonu -->
                        <button type="submit" class="btn-primary btn-save">
                            <i class="fas fa-save"></i>
                            <?= $edit_mode ? 'Kaydet' : 'Oluştur' ?>
                        </button>
                    </div>
                    
                    <?php if ($edit_mode && !empty($loadout_set['set_image_path'])): ?>
                        <div class="current-image" style="margin-top: 1rem;">
                            <img src="<?= htmlspecialchars($loadout_set['set_image_path']) ?>" alt="Mevcut görsel" style="max-width: 200px; max-height: 150px; border-radius: 4px;">
                            <p>Mevcut görsel - Yeni yükleyerek değiştirebilirsiniz</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="set_image">Set Görseli <?= !$edit_mode ? '*' : '' ?></label>
                        <input type="file" id="set_image" name="set_image" accept="image/*" 
                               <?= !$edit_mode ? 'required' : '' ?>>
                        <small>PNG, JPG veya JPEG formatında, maksimum 5MB</small>
                    </div>
                </form>
            </div>

            <!-- 2. SATIR: Arama (Sol) + Slotlar (Sağ) -->
            <div class="second-row">
                <!-- Sol: Item Arama -->
                <div class="search-panel">
                    <h3><i class="fas fa-search"></i> Item Arama</h3>
                    
                    <div class="search-form">
                        <div class="search-input-group">
                            <input type="text" id="item_search" placeholder="Item adı yazın (örn: Morozov, Helmet)">
                            <button type="button" id="search_btn" onclick="searchItems()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="search-filters">
                            <select id="type_filter">
                                <option value="">Tüm Tipler</option>
                                <option value="WeaponPersonal">Silahlar</option>
                                <option value="Char_Armor_Helmet">Kasklar</option>
                                <option value="Char_Armor_Torso">Gövde Zırhları</option>
                                <option value="Char_Armor_Arms">Kol Zırhları</option>
                                <option value="Char_Armor_Legs">Bacak Zırhları</option>
                                <option value="Char_Armor_Backpack">Sırt Çantaları</option>
                                <option value="Char_Clothing_Undersuit">Alt Giysiler</option>
                                <option value="fps_consumable">Tüketilebilir</option>
                            </select>
                        </div>
                    </div>

                    <div class="search-results">
                        <div class="search-placeholder">
                            <i class="fas fa-search"></i>
                            <p>Yukarıdaki arama kutusunu kullanarak Star Citizen itemlerini arayın</p>
                            <small>Sonuçlara tıklayarak uygun slotlara yerleştirebilirsiniz</small>
                        </div>
                        
                        <div class="search-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Aranıyor...</p>
                        </div>
                        
                        <div id="search_results_container" class="results-container" style="display: none;">
                            <!-- Arama sonuçları buraya gelecek -->
                        </div>
                    </div>
                </div>

                <!-- Sağ: Equipment Slotları -->
                <div class="equipment-slots-panel">
                    <h3><i class="fas fa-boxes"></i> Teçhizat Slotları</h3>
                    <div class="slots-container">
                        <?php 
                        // Slot ikonları tanımla
                        $slot_icons = [
                            'Kask' => 'fas fa-hard-hat slot-helmet',
                            'Gövde Zırhı' => 'fas fa-tshirt slot-torso',
                            'Kol Zırhları' => 'fas fa-hand-paper slot-arms',
                            'Bacak Zırhları' => 'fas fa-socks slot-legs',
                            'Alt Giyim' => 'fas fa-underwear slot-undersuit',
                            'Sırt Çantası' => 'fas fa-backpack slot-backpack',
                            'Birincil Silah 1' => 'fas fa-gun slot-weapon',
                            'Birincil Silah 2 (Sırtta)' => 'fas fa-gun slot-weapon',
                            'İkincil Silah (Tabanca veya Medgun)' => 'fas fa-gun slot-weapon',
                            'Yardımcı Modül/Gadget 1' => 'fas fa-microchip slot-gadget',
                            'Medikal Araç' => 'fas fa-medkit slot-medical',
                            'Multi-Tool Attachment' => 'fas fa-wrench slot-tool'
                        ];
                        
                        foreach ($equipment_slots as $slot): 
                            $icon_class = $slot_icons[$slot['slot_name']] ?? 'fas fa-cube';
                        ?>
                            <div class="equipment-slot" 
                                 data-slot-id="<?= $slot['id'] ?>" 
                                 data-slot-type="<?= htmlspecialchars($slot['slot_type']) ?>"
                                 data-slot-name="<?= htmlspecialchars($slot['slot_name']) ?>">
                                <div class="slot-header">
                                    <span class="slot-name"><?= htmlspecialchars($slot['slot_name']) ?></span>
                                    <button type="button" class="slot-clear-btn" onclick="clearSlot(<?= $slot['id'] ?>)" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="slot-content">
                                    <div class="slot-empty">
                                        <i class="<?= $icon_class ?>"></i>
                                        <span>Boş Slot</span>
                                        <small><?= htmlspecialchars($slot['slot_type']) ?></small>
                                    </div>
                                    <div class="slot-item" style="display: none;">
                                        <div class="item-info">
                                            <span class="item-name"></span>
                                            <small class="item-manufacturer"></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Onay Gerekli</h3>
            <button class="modal-close" onclick="closeModal('confirmModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="confirmMessage">Bu işlemi yapmak istediğinizden emin misiniz?</p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('confirmModal')">İptal</button>
            <button class="btn-primary" id="confirmBtn">Onayla</button>
        </div>
    </div>
</div>

<script src="js/create_loadout.js"></script>

<?php if ($edit_mode && !empty($existing_items)): ?>
<script>
// Mevcut itemleri yükle
document.addEventListener('DOMContentLoaded', function() {
    const existingItems = <?= json_encode($existing_items) ?>;
    loadExistingItems(existingItems);
});
</script>
<?php endif; ?>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>