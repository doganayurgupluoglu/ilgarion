<?php
// /events/roles/skill_tags.php - Skill Tag Yönetimi

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Events layout system include
require_once '../includes/events_layout.php';

// Session kontrolü
check_user_session_validity();
require_approved_user();

// Admin yetkisi kontrolü
if (!has_permission($pdo, 'admin.panel.access')) {
    $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmuyor.";
    header('Location: ../../index.php');
    exit;
}

// CSRF Token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası - CSRF token geçersiz";
        header('Location: skill_tags.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            // Yeni skill tag ekleme
            $tag_name = trim($_POST['tag_name'] ?? '');
            
            if (empty($tag_name)) {
                throw new Exception("Tag adı boş olamaz.");
            }
            
            if (strlen($tag_name) < 2 || strlen($tag_name) > 50) {
                throw new Exception("Tag adı 2-50 karakter arasında olmalıdır.");
            }
            
            // Benzersizlik kontrolü
            $check_stmt = $pdo->prepare("SELECT id FROM skill_tags WHERE tag_name = ?");
            $check_stmt->execute([$tag_name]);
            if ($check_stmt->fetch()) {
                throw new Exception("Bu tag adı zaten mevcut.");
            }
            
            // Ekle
            $stmt = $pdo->prepare("INSERT INTO skill_tags (tag_name) VALUES (?)");
            $stmt->execute([$tag_name]);
            
            $_SESSION['success_message'] = "Skill tag başarıyla eklendi: " . htmlspecialchars($tag_name);
            
        } elseif ($action === 'edit') {
            // Skill tag düzenleme
            $tag_id = (int)($_POST['tag_id'] ?? 0);
            $tag_name = trim($_POST['tag_name'] ?? '');
            
            if ($tag_id <= 0) {
                throw new Exception("Geçersiz tag ID.");
            }
            
            if (empty($tag_name)) {
                throw new Exception("Tag adı boş olamaz.");
            }
            
            if (strlen($tag_name) < 2 || strlen($tag_name) > 50) {
                throw new Exception("Tag adı 2-50 karakter arasında olmalıdır.");
            }
            
            // Benzersizlik kontrolü (kendisi hariç)
            $check_stmt = $pdo->prepare("SELECT id FROM skill_tags WHERE tag_name = ? AND id != ?");
            $check_stmt->execute([$tag_name, $tag_id]);
            if ($check_stmt->fetch()) {
                throw new Exception("Bu tag adı zaten mevcut.");
            }
            
            // Güncelle
            $stmt = $pdo->prepare("UPDATE skill_tags SET tag_name = ? WHERE id = ?");
            $stmt->execute([$tag_name, $tag_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Tag bulunamadı veya güncelleme gerekli değil.");
            }
            
            $_SESSION['success_message'] = "Skill tag başarıyla güncellendi: " . htmlspecialchars($tag_name);
            
        } elseif ($action === 'delete') {
            // Skill tag silme
            $tag_id = (int)($_POST['tag_id'] ?? 0);
            
            if ($tag_id <= 0) {
                throw new Exception("Geçersiz tag ID.");
            }
            
            $pdo->beginTransaction();
            
            // Tag'i kullanan kayıtları temizle
            
            // 1. event_role_requirements tablosundaki referansları temizle
            $req_stmt = $pdo->prepare("SELECT id, skill_tag_ids FROM event_role_requirements WHERE FIND_IN_SET(?, skill_tag_ids) > 0");
            $req_stmt->execute([$tag_id]);
            $requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($requirements as $req) {
                $tag_ids = array_filter(explode(',', $req['skill_tag_ids']));
                $tag_ids = array_diff($tag_ids, [$tag_id]); // Silinecek tag'i çıkar
                
                if (empty($tag_ids)) {
                    // Hiç tag kalmadıysa gereksinimi sil
                    $del_req_stmt = $pdo->prepare("DELETE FROM event_role_requirements WHERE id = ?");
                    $del_req_stmt->execute([$req['id']]);
                } else {
                    // Kalan tag'lerle güncelle
                    $new_tag_ids = implode(',', $tag_ids);
                    $upd_req_stmt = $pdo->prepare("UPDATE event_role_requirements SET skill_tag_ids = ? WHERE id = ?");
                    $upd_req_stmt->execute([$new_tag_ids, $req['id']]);
                }
            }
            
            // 2. user_skill_tags tablosundaki referansları temizle
            $user_stmt = $pdo->prepare("SELECT id, tag_ids FROM user_skill_tags WHERE FIND_IN_SET(?, tag_ids) > 0");
            $user_stmt->execute([$tag_id]);
            $user_tags = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($user_tags as $user_tag) {
                $tag_ids = array_filter(explode(',', $user_tag['tag_ids']));
                $tag_ids = array_diff($tag_ids, [$tag_id]); // Silinecek tag'i çıkar
                
                if (empty($tag_ids)) {
                    // Hiç tag kalmadıysa kullanıcı kaydını sil
                    $del_user_stmt = $pdo->prepare("DELETE FROM user_skill_tags WHERE id = ?");
                    $del_user_stmt->execute([$user_tag['id']]);
                } else {
                    // Kalan tag'lerle güncelle
                    $new_tag_ids = implode(',', $tag_ids);
                    $upd_user_stmt = $pdo->prepare("UPDATE user_skill_tags SET tag_ids = ? WHERE id = ?");
                    $upd_user_stmt->execute([$new_tag_ids, $user_tag['id']]);
                }
            }
            
            // 3. Son olarak skill tag'i sil
            $del_stmt = $pdo->prepare("DELETE FROM skill_tags WHERE id = ?");
            $del_stmt->execute([$tag_id]);
            
            if ($del_stmt->rowCount() === 0) {
                throw new Exception("Tag bulunamadı.");
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Skill tag ve tüm ilişkili kayıtlar başarıyla silindi.";
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: skill_tags.php');
    exit;
}

// Skill tag'leri listele
try {
    $stmt = $pdo->prepare("
        SELECT st.*, 
               (SELECT COUNT(*) FROM event_role_requirements err WHERE FIND_IN_SET(st.id, err.skill_tag_ids) > 0) as requirement_usage,
               (SELECT COUNT(*) FROM user_skill_tags ust WHERE FIND_IN_SET(st.id, ust.tag_ids) > 0) as user_usage
        FROM skill_tags st
        ORDER BY st.tag_name ASC
    ");
    $stmt->execute();
    $skill_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Skill tag'ler yüklenirken hata oluştu: " . $e->getMessage();
    $skill_tags = [];
}

$page_title = "Skill Tag Yönetimi";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Roller', 'url' => '/events/roles/', 'icon' => 'fas fa-user-tag'],
    ['text' => 'Skill Tag Yönetimi', 'url' => '', 'icon' => 'fas fa-tags']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="../css/events_sidebar.css">
<link rel="stylesheet" href="css/roles.css">

<div class="loadout-view-container">
    <!-- Sayfa Başlığı -->
    <div class="set-meta">
        <div class="set-header">
            <h1>
                <i class="fas fa-tags"></i>
                Skill Tag Yönetimi
            </h1>
            <p class="page-description">
                Sistem genelinde kullanılan skill tag'leri yönetin. Tag silindiğinde tüm ilişkili kayıtlar otomatik temizlenir.
            </p>
        </div>
        
        <div class="set-actions">
            <button type="button" class="btn-action-primary" onclick="showAddModal()">
                <i class="fas fa-plus"></i>
                Yeni Tag Ekle
            </button>
        </div>
    </div>

    <!-- Skill Tag Listesi -->
    <div class="loadout-items-section">
        <div class="items-header">
            <h2>
                <i class="fas fa-list"></i>
                Mevcut Skill Tag'ler
            </h2>
            <div class="items-count">
                <?= count($skill_tags) ?> tag
            </div>
        </div>

        <?php if (empty($skill_tags)): ?>
            <div class="empty-items">
                <i class="fas fa-tags"></i>
                <h3>Skill Tag Bulunamadı</h3>
                <p>Henüz hiç skill tag oluşturulmamış.</p>
                <button type="button" class="btn-action-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i>
                    İlk Tag'i Oluştur
                </button>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($skill_tags as $tag): ?>
                    <div class="item-card">
                        <div class="item-header">
                            <div class="item-slot">
                                <i class="fas fa-tag"></i>
                                <span><?= htmlspecialchars($tag['tag_name']) ?></span>
                            </div>
                        </div>
                        <div class="item-content">
                            <h3 class="item-name">
                                <?= htmlspecialchars($tag['tag_name']) ?>
                            </h3>
                            
                            <div class="item-manufacturer">
                                <i class="fas fa-clipboard-check"></i>
                                <span><?= $tag['requirement_usage'] ?> gereksinimde kullanılıyor</span>
                            </div>
                            
                            <div class="item-type">
                                <i class="fas fa-users"></i>
                                <span><?= $tag['user_usage'] ?> kullanıcıda mevcut</span>
                            </div>
                            
                            <div class="item-notes">
                                <i class="fas fa-calendar"></i>
                                Oluşturulma: <?= date('d.m.Y', strtotime($tag['created_at'])) ?>
                            </div>
                            
                            <div class="set-actions" style="margin-top: 1rem;">
                                <button type="button" 
                                        class="btn-action-secondary"
                                        onclick="showEditModal(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['tag_name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                    Düzenle
                                </button>
                                
                                <button type="button" 
                                        class="btn-action-secondary delete-tag" 
                                        data-tag-id="<?= $tag['id'] ?>"
                                        data-tag-name="<?= htmlspecialchars($tag['tag_name']) ?>"
                                        data-usage-count="<?= $tag['requirement_usage'] + $tag['user_usage'] ?>"
                                        style="background: transparent; border: 1px solid var(--red); color: var(--red);">
                                    <i class="fas fa-trash"></i>
                                    Sil
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ekleme/Düzenleme Modal -->
<div id="tagModal" class="modal" style="display: none;">
    <div class="modal-dialog" >
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-1); border-radius: 8px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-1); padding: 1.5rem;">
                <h5 class="modal-title" style="color: var(--gold); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-tag"></i>
                    <span id="modalTitle">Skill Tag Ekle</span>
                </h5>
                <button type="button" class="btn-close" onclick="closeModal()" style="background: none; border: none; color: var(--light-grey); font-size: 1.5rem;">&times;</button>
            </div>
            <form id="tagForm" method="POST">
                <div class="modal-body" style="padding: 1.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="tag_id" id="tagId" value="">
                    
                    <div class="form-group">
                        <label for="tagName" style="margin-bottom: 0.5rem; font-weight: 500; color: var(--lighter-grey);">Tag Adı *</label>
                        <input type="text" 
                               id="tagName" 
                               name="tag_name" 
                               required 
                               maxlength="50"
                               style="width: 100%; padding: 0.75rem; background: var(--card-bg-3); border: 1px solid var(--border-1); border-radius: 6px; color: var(--lighter-grey);"
                               placeholder="örn: pilot, combat, leadership">
                        <small style="color: var(--light-grey);">2-50 karakter arası, benzersiz olmalı</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-1); padding: 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn-action-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="submit" class="btn-action-primary">
                        <i class="fas fa-save"></i> <span id="submitText">Ekle</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Silme Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-1); border-radius: 8px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-1); padding: 1.5rem;">
                <h5 class="modal-title" style="color: var(--red); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Skill Tag Sil
                </h5>
                <button type="button" class="btn-close" onclick="closeDeleteModal()" style="background: none; border: none; color: var(--light-grey); font-size: 1.5rem;">&times;</button>
            </div>
            <form id="deleteForm" method="POST">
                <div class="modal-body" style="padding: 1.5rem; color: var(--lighter-grey);">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tag_id" id="deleteTagId" value="">
                    
                    <p><strong id="deleteTagName" style="color: var(--gold);"></strong> tag'ini silmek istediğinizden emin misiniz?</p>
                    
                    <div id="usageWarning" style="background: rgba(235, 0, 0, 0.1); border: 1px solid var(--red); border-radius: 6px; padding: 1rem; margin: 1rem 0;">
                        <i class="fas fa-exclamation-circle" style="color: var(--red);"></i>
                        Bu tag <strong id="usageCount"></strong> yerde kullanılıyor. Silme işlemi:
                        <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                            <li>İlgili tüm gereksinim kayıtlarını temizleyecek</li>
                            <li>İlgili tüm kullanıcı tag kayıtlarını temizleyecek</li>
                            <li>Bu işlem geri alınamaz</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-1); padding: 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn-action-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="submit" class="btn-action-primary" style="background: var(--red); border-color: var(--red);">
                        <i class="fas fa-trash"></i> Sil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal işlemleri
function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Skill Tag Ekle';
    document.getElementById('formAction').value = 'add';
    document.getElementById('tagId').value = '';
    document.getElementById('tagName').value = '';
    document.getElementById('submitText').textContent = 'Ekle';
    document.getElementById('tagModal').style.display = 'block';
    document.getElementById('tagName').focus();
}

function showEditModal(tagId, tagName) {
    document.getElementById('modalTitle').textContent = 'Skill Tag Düzenle';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('tagId').value = tagId;
    document.getElementById('tagName').value = tagName;
    document.getElementById('submitText').textContent = 'Güncelle';
    document.getElementById('tagModal').style.display = 'block';
    document.getElementById('tagName').focus();
}

function closeModal() {
    document.getElementById('tagModal').style.display = 'none';
}

// Silme modal işlemleri
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-tag')) {
        const button = e.target.closest('.delete-tag');
        const tagId = button.dataset.tagId;
        const tagName = button.dataset.tagName;
        const usageCount = button.dataset.usageCount;
        
        document.getElementById('deleteTagId').value = tagId;
        document.getElementById('deleteTagName').textContent = tagName;
        document.getElementById('usageCount').textContent = usageCount;
        
        // Kullanım sayısına göre uyarı göster/gizle
        const warning = document.getElementById('usageWarning');
        if (parseInt(usageCount) > 0) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
        
        document.getElementById('deleteModal').style.display = 'block';
    }
});

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Modal dışına tıklama ile kapatma
window.onclick = function(event) {
    const tagModal = document.getElementById('tagModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target === tagModal) {
        closeModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// ESC tuşu ile kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});

// Form validasyonu
document.getElementById('tagForm').addEventListener('submit', function(e) {
    const tagName = document.getElementById('tagName').value.trim();
    
    if (tagName.length < 2) {
        e.preventDefault();
        alert('Tag adı en az 2 karakter olmalıdır.');
        return;
    }
    
    if (tagName.length > 50) {
        e.preventDefault();
        alert('Tag adı en fazla 50 karakter olabilir.');
        return;
    }
});

// Başarı/hata mesajları
<?php if (isset($_SESSION['success_message'])): ?>
    showNotification('<?= addslashes($_SESSION['success_message']) ?>', 'success');
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    showNotification('<?= addslashes($_SESSION['error_message']) ?>', 'error');
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--card-bg);
        color: var(--lighter-grey);
        border: 1px solid var(--border-1);
        border-radius: 8px;
        padding: 1rem;
        z-index: 10000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;

    if (type === 'success') {
        notification.style.borderLeftColor = 'var(--turquase)';
        notification.style.borderLeftWidth = '4px';
    } else if (type === 'error') {
        notification.style.borderLeftColor = 'var(--red)';
        notification.style.borderLeftWidth = '4px';
    }

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 4000);
}

// CSS animasyonları
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateY(0); opacity: 0; }
        to { transform: translateY(100%); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        backdrop-filter: blur(5px);
    }
    .modal-dialog {
        transform: translateY(150%) !important;
        max-width: 500px;
        width: 90%;
        margin: auto !important;
    }
`;
document.head.appendChild(style);
</script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>