<?php
// /events/loadouts/view.php - Teçhizat seti görüntüleme sayfası

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

// Loadout ID kontrolü
$loadout_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($loadout_id <= 0) {
    $_SESSION['error_message'] = "Geçersiz teçhizat seti ID'si.";
    header('Location: index.php');
    exit;
}

try {
    // Ana loadout set bilgilerini çek
    $stmt = $pdo->prepare("
        SELECT 
            ls.*,
            u.username,
            u.avatar_path,
            u.ingame_name,
            u.profile_info,
            r.name as primary_role_name,
            r.color as primary_role_color,
            r.description as primary_role_description
        FROM loadout_sets ls
        LEFT JOIN users u ON ls.user_id = u.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id AND r.priority = (
            SELECT MIN(r2.priority) 
            FROM user_roles ur2 
            JOIN roles r2 ON ur2.role_id = r2.id 
            WHERE ur2.user_id = u.id
        )
        WHERE ls.id = :id
    ");
    $stmt->execute([':id' => $loadout_id]);
    $loadout_set = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loadout_set) {
        $_SESSION['error_message'] = "Teçhizat seti bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    // Görünürlük kontrolü
    $can_view = false;
    
    if ($loadout_set['visibility'] === 'public') {
        $can_view = true;
    } elseif ($loadout_set['visibility'] === 'members_only' && is_user_approved()) {
        $can_view = true;
    } elseif ($loadout_set['visibility'] === 'faction_only' && is_user_approved()) {
        // Faction kontrolü burada yapılabilir
        $can_view = true;
    } elseif ($loadout_set['visibility'] === 'private' && 
              is_user_logged_in() && 
              ($_SESSION['user_id'] == $loadout_set['user_id'] || has_permission($pdo, 'admin.panel.access'))) {
        $can_view = true;
    }
    
    if (!$can_view) {
        $_SESSION['error_message'] = "Bu teçhizat setini görüntüleme yetkiniz bulunmuyor.";
        header('Location: index.php');
        exit;
    }
    
    // Set itemlerini çek
    $items_stmt = $pdo->prepare("
        SELECT 
            lsi.*,
            es.slot_name,
            es.slot_type
        FROM loadout_set_items lsi
        LEFT JOIN equipment_slots es ON lsi.equipment_slot_id = es.id
        WHERE lsi.loadout_set_id = :set_id
        ORDER BY es.display_order ASC, lsi.id ASC
    ");
    $items_stmt->execute([':set_id' => $loadout_id]);
    $set_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Weapon attachments çek (eğer tablo varsa)
    $weapon_attachments = [];
    try {
        $attachments_stmt = $pdo->prepare("
            SELECT 
                lwa.*,
                was.slot_name as attachment_slot_name,
                was.slot_type as attachment_slot_type,
                was.icon_class,
                es.slot_name as parent_slot_name
            FROM loadout_weapon_attachments lwa
            LEFT JOIN weapon_attachment_slots was ON lwa.attachment_slot_id = was.id
            LEFT JOIN equipment_slots es ON lwa.parent_equipment_slot_id = es.id
            WHERE lwa.loadout_set_id = :set_id
            ORDER BY lwa.parent_equipment_slot_id ASC, was.display_order ASC
        ");
        $attachments_stmt->execute([':set_id' => $loadout_id]);
        $weapon_attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Weapon attachments tablosu yoksa sessizce geç
        error_log("Weapon attachments table not found: " . $e->getMessage());
    }
    
    // Attachments'ları parent slot'a göre grupla
    $grouped_attachments = [];
    foreach ($weapon_attachments as $attachment) {
        $parent_slot_id = $attachment['parent_equipment_slot_id'];
        if (!isset($grouped_attachments[$parent_slot_id])) {
            $grouped_attachments[$parent_slot_id] = [];
        }
        $grouped_attachments[$parent_slot_id][] = $attachment;
    }
    
} catch (PDOException $e) {
    error_log("Loadout view error: " . $e->getMessage());
    $_SESSION['error_message'] = "Teçhizat seti yüklenirken bir hata oluştu.";
    header('Location: index.php');
    exit;
}

$page_title = $loadout_set['set_name'] . " - Teçhizat Seti";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Teçhizat Setleri', 'url' => '/events/loadouts/', 'icon' => 'fas fa-user-astronaut'],
    ['text' => $loadout_set['set_name'], 'url' => '', 'icon' => 'fas fa-eye']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Helper functions
function generate_breadcrumb($items) {
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

function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 2592000) return floor($time/86400) . ' gün önce';
    return date('d.m.Y', strtotime($datetime));
}

function get_visibility_text($visibility) {
    switch ($visibility) {
        case 'public': return 'Herkese Açık';
        case 'members_only': return 'Üyelere Özel';
        case 'faction_only': return 'Fraksiyona Özel';
        case 'private': return 'Özel';
        default: return ucfirst($visibility);
    }
}

function get_visibility_icon($visibility) {
    switch ($visibility) {
        case 'public': return 'fas fa-globe';
        case 'members_only': return 'fas fa-users';
        case 'faction_only': return 'fas fa-shield-alt';
        case 'private': return 'fas fa-lock';
        default: return 'fas fa-eye';
    }
}

function get_slot_icon($slot_name) {
    $icons = [
        'Kask' => 'fas fa-hard-hat',
        'Gövde Zırhı' => 'fas fa-tshirt',
        'Kol Zırhları' => 'fas fa-hand-paper',
        'Bacak Zırhları' => 'fas fa-socks',
        'Alt Giyim' => 'fas fa-underwear',
        'Sırt Çantası' => 'fas fa-backpack',
        'Birincil Silah 1' => 'fas fa-gun',
        'Birincil Silah 2 (Sırtta)' => 'fas fa-gun',
        'İkincil Silah (Tabanca veya Medgun)' => 'fas fa-gun',
        'Yardımcı Modül/Gadget 1' => 'fas fa-microchip',
        'Medikal Araç' => 'fas fa-medkit',
        'Multi-Tool Attachment' => 'fas fa-wrench'
    ];
    return $icons[$slot_name] ?? 'fas fa-cube';
}
?>

<link rel="stylesheet" href="css/view_loadout.css">

<div class="loadout-view-container">
    <!-- Breadcrumb Navigation -->
    <?= generate_breadcrumb($breadcrumb_items) ?>

    <div class="loadout-view-content">
        <!-- Sol Taraf: Set Görseli ve Meta Bilgiler -->
        <div class="loadout-image-section">
            <div class="set-image-container">
                <?php if (!empty($loadout_set['set_image_path'])): ?>
                    <img src="/<?= htmlspecialchars($loadout_set['set_image_path']) ?>" 
                         alt="<?= htmlspecialchars($loadout_set['set_name']) ?>" 
                         class="set-main-image"
                         onerror="this.parentElement.innerHTML='<div class=\'image-placeholder\'><i class=\'fas fa-user-astronaut\'></i><span>Görsel Yüklenemedi</span></div>'">
                <?php else: ?>
                    <div class="image-placeholder">
                        <i class="fas fa-user-astronaut"></i>
                        <span>Görsel Yok</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Meta Bilgiler -->
            <div class="set-meta">
                <!-- <div class="set-header">
                    <h1><?= htmlspecialchars($loadout_set['set_name']) ?></h1>
                    <span class="visibility-badge visibility-<?= $loadout_set['visibility'] ?>">
                        <i class="<?= get_visibility_icon($loadout_set['visibility']) ?>"></i>
                        <?= get_visibility_text($loadout_set['visibility']) ?>
                    </span>
                </div> -->

                <!-- Oluşturan Kişi -->
                <div class="creator-section">
                    <div class="creator-info">
                        <img src="<?= !empty($loadout_set['avatar_path']) ? '/' . htmlspecialchars($loadout_set['avatar_path']) : '/assets/logo.png' ?>" 
                             alt="<?= htmlspecialchars($loadout_set['username']) ?>" 
                             class="creator-avatar"
                             onerror="this.src='/assets/logo.png'">
                        <div class="creator-details">
                            <span class="creator-label">Oluşturan:</span>
                            <span class="user-link creator-name" 
                                  data-user-id="<?= $loadout_set['user_id'] ?>"
                                  style="color: <?= $loadout_set['primary_role_color'] ?? '#bd912a' ?>;">
                                <?= htmlspecialchars($loadout_set['username']) ?>
                                <?php if (!empty($loadout_set['primary_role_name'])): ?>
                        <div class="creator-role" style="color: <?= $loadout_set['primary_role_color'] ?? '#bd912a' ?>;">
                            <?= htmlspecialchars($loadout_set['primary_role_name']) ?>
                        </div>
                    <?php endif; ?>
                            </span>
                            <?php if (!empty($loadout_set['ingame_name'])): ?>
                                <small class="ingame-name">
                                    <i class="fas fa-gamepad"></i>
                                    <?= htmlspecialchars($loadout_set['ingame_name']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    
                </div>

                <!-- İstatistikler -->
                <div class="set-stats">
                    <div class="stat-item-page">
                        <i class="fas fa-boxes"></i>
                        <span><?= count($set_items) ?> Item</span>
                    </div>
                    <div class="stat-item-page">
                        <i class="fas fa-puzzle-piece"></i>
                        <span><?= count($weapon_attachments) ?> Eklenti</span>
                    </div>
                    <div class="stat-item-page">
                        <i class="fas fa-calendar"></i>
                        <span title="<?= date('d.m.Y H:i', strtotime($loadout_set['created_at'])) ?>">
                            <?= time_ago($loadout_set['created_at']) ?>
                        </span>
                    </div>
                    <?php if ($loadout_set['updated_at'] && $loadout_set['updated_at'] !== $loadout_set['created_at']): ?>
                        <div class="stat-item-page">
                            <i class="fas fa-edit"></i>
                            <span title="<?= date('d.m.Y H:i', strtotime($loadout_set['updated_at'])) ?>">
                                <?= time_ago($loadout_set['updated_at']) ?> güncellendi
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- İşlemler -->
                <?php if (is_user_logged_in()): ?>
                    <div class="set-actions">
                        <?php if ($loadout_set['user_id'] == $_SESSION['user_id'] || has_permission($pdo, 'loadout.manage_sets')): ?>
                            <a href="create_loadouts.php?edit=<?= $loadout_set['id'] ?>" class="btn-action-primary">
                                <i class="fas fa-edit"></i>
                                Düzenle
                            </a>
                        <?php endif; ?>
                        <button class="btn-action-secondary" onclick="copySetLink()">
                            <i class="fas fa-share"></i>
                            Paylaş
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sağ Taraf: Items Listesi -->
        <div class="loadout-items-section">
            <div class="items-header">
                <h2><i class="fas fa-list"></i> Teçhizat Listesi</h2>
                <span class="items-count"><?= count($set_items) ?> Item</span>
            </div>

            <!-- Items Grid -->
            <div class="items-grid">
                <?php foreach ($set_items as $item): ?>
                    <div class="item-card">
                        <div class="item-header">
                            <div class="item-slot">
                                <i class="<?= get_slot_icon($item['slot_name']) ?>"></i>
                                <span><?= htmlspecialchars($item['slot_name']) ?></span>
                            </div>
                            
                            <!-- Attachment Indicator -->
                            <?php if (isset($grouped_attachments[$item['equipment_slot_id']]) && 
                                      !empty($grouped_attachments[$item['equipment_slot_id']])): ?>
                                <div class="attachment-indicator" 
                                     onclick="toggleAttachments(<?= $item['equipment_slot_id'] ?>)"
                                     title="<?= count($grouped_attachments[$item['equipment_slot_id']]) ?> eklenti var">
                                    <i class="fas fa-puzzle-piece"></i>
                                    <span class="attachment-count"><?= count($grouped_attachments[$item['equipment_slot_id']]) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="item-content">
                            <h3 class="item-name"><?= htmlspecialchars($item['item_name']) ?></h3>
                            
                            <?php if (!empty($item['item_manufacturer_api'])): ?>
                                <div class="item-manufacturer">
                                    <i class="fas fa-industry"></i>
                                    <?= htmlspecialchars($item['item_manufacturer_api']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['item_type_api'])): ?>
                                <div class="item-type">
                                    <i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($item['item_type_api']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['item_notes'])): ?>
                                <div class="item-notes">
                                    <i class="fas fa-sticky-note"></i>
                                    <?= htmlspecialchars($item['item_notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Weapon Attachments (Başlangıçta gizli) -->
                        <?php if (isset($grouped_attachments[$item['equipment_slot_id']]) && 
                                  !empty($grouped_attachments[$item['equipment_slot_id']])): ?>
                            <div class="item-attachments" id="attachments-<?= $item['equipment_slot_id'] ?>" style="display: none;">
                                <h4><i class="fas fa-puzzle-piece"></i> Eklentiler</h4>
                                <div class="attachments-list">
                                    <?php foreach ($grouped_attachments[$item['equipment_slot_id']] as $attachment): ?>
                                        <div class="attachment-item">
                                            <div class="attachment-slot">
                                                <i class="<?= $attachment['icon_class'] ?? 'fas fa-puzzle-piece' ?>"></i>
                                                <span><?= htmlspecialchars($attachment['attachment_slot_name']) ?></span>
                                            </div>
                                            <div class="attachment-details">
                                                <span class="attachment-name"><?= htmlspecialchars($attachment['attachment_item_name']) ?></span>
                                                <?php if (!empty($attachment['attachment_item_manufacturer'])): ?>
                                                    <small class="attachment-manufacturer">
                                                        <?= htmlspecialchars($attachment['attachment_item_manufacturer']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($set_items)): ?>
                    <div class="empty-items">
                        <i class="fas fa-box-open"></i>
                        <p>Bu sette henüz hiç item bulunmuyor.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Set Açıklaması -->
            <div class="set-description-section">
                <h3><i class="fas fa-info-circle"></i> Set Açıklaması</h3>
                <div class="set-description-content">
                    <?= (htmlspecialchars($loadout_set['set_description'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>

<script>
// Attachment toggle fonksiyonu
function toggleAttachments(slotId) {
    const attachmentsDiv = document.getElementById(`attachments-${slotId}`);
    const indicator = event.currentTarget;
    
    if (attachmentsDiv.style.display === 'none') {
        attachmentsDiv.style.display = 'block';
        indicator.classList.add('active');
    } else {
        attachmentsDiv.style.display = 'none';
        indicator.classList.remove('active');
    }
}

// Set link kopyalama
function copySetLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        showMessage('Set linki kopyalandı!', 'success');
    }).catch(() => {
        // Fallback method
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showMessage('Set linki kopyalandı!', 'success');
    });
}

// Message gösterme fonksiyonu
function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        padding: 1rem 1.5rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: var(--font);
        font-weight: 500;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;
    
    if (type === 'success') {
        messageDiv.style.background = 'rgba(40, 167, 69, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.innerHTML = '<i class="fas fa-check-circle"></i><span>' + message + '</span>';
    }
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 300);
    }, 3000);
}

// CSS animasyonu ekle
const style = document.createElement('style');
style.textContent = `
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(100%); }
    to { opacity: 1; transform: translateX(0); }
}
`;
document.head.appendChild(style);
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>