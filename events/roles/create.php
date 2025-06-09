<?php
// /events/roles/create.php - Etkinlik rolü oluşturma sayfası - MEVCUT DB YAPISINA GÖRE

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Events layout system include
require_once '../includes/events_layout.php';

// Session kontrolü
check_user_session_validity();
require_approved_user();

// Rol oluşturma yetkisi kontrolü
if (!has_permission($pdo, 'event_role.create')) {
    $_SESSION['error_message'] = "Etkinlik rolü oluşturmak için yetkiniz bulunmuyor.";
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$edit_mode = false;
$role_data = null;
$role_id = 0;

// Düzenleme modu kontrolü - hem ?id=X hem de ?edit=X destekle
$role_id_param = $_GET['id'] ?? $_GET['edit'] ?? null;
if ($role_id_param && is_numeric($role_id_param)) {
    $role_id = (int)$role_id_param;
    
    try {
        // MEVCUT TABLO YAPISINA GÖRE düzenleme sorgusu
        $stmt = $pdo->prepare("
            SELECT er.*, ls.set_name as loadout_name 
            FROM event_roles er 
            LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id 
            WHERE er.id = :id
        ");
        $stmt->execute([':id' => $role_id]);
        $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role_data) {
            // Düzenleme yetkisi kontrolü - admin veya rol oluşturan kişi
            $can_edit = has_permission($pdo, 'event_role.edit_all');
            
            if (!$can_edit) {
                $_SESSION['error_message'] = "Bu rolü düzenleme yetkiniz yok.";
                header('Location: index.php');
                exit;
            }
            $edit_mode = true;
        } else {
            $_SESSION['error_message'] = "Rol bulunamadı.";
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Role edit error: " . $e->getMessage());
        $_SESSION['error_message'] = "Rol yüklenirken bir hata oluştu.";
        header('Location: index.php');
        exit;
    }
}

// Form gönderimi işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası - CSRF token geçersiz";
        header('Location: ' . $_SERVER['PHP_SELF'] . ($edit_mode ? "?id=$role_id" : ''));
        exit;
    }
    
    // Form verilerini al ve temizle
    $role_name = trim($_POST['role_name'] ?? '');
    $role_description = trim($_POST['role_description'] ?? '');
    $role_icon = trim($_POST['role_icon'] ?? 'fas fa-user'); // MEVCUT ALAN ADI
    $suggested_loadout_id = !empty($_POST['suggested_loadout_id']) ? (int)$_POST['suggested_loadout_id'] : null;
    
    // MEVCUT TABLO YAPISINA GÖRE gereksinimler - N:N ilişki
    $skill_tag_ids = $_POST['skill_tag_ids'] ?? [];
    
    // Validasyon
    $errors = [];
    
    if (empty($role_name)) {
        $errors[] = "Rol adı gereklidir.";
    } elseif (strlen($role_name) < 3 || strlen($role_name) > 100) {
        $errors[] = "Rol adı 3-100 karakter arasında olmalıdır.";
    }
    
    if (empty($role_description)) {
        $errors[] = "Rol açıklaması gereklidir.";
    } elseif (strlen($role_description) < 10) {
        $errors[] = "Rol açıklaması en az 10 karakter olmalıdır.";
    }
    
    // Rol adı benzersizlik kontrolü
    try {
        if ($edit_mode) {
            $check_stmt = $pdo->prepare("SELECT id FROM event_roles WHERE role_name = :name AND id != :id");
            $check_stmt->execute([':name' => $role_name, ':id' => $role_id]);
        } else {
            $check_stmt = $pdo->prepare("SELECT id FROM event_roles WHERE role_name = :name");
            $check_stmt->execute([':name' => $role_name]);
        }
        
        if ($check_stmt->fetch()) {
            $errors[] = "Bu rol adı zaten kullanılıyor.";
        }
    } catch (PDOException $e) {
        error_log("Role name check error: " . $e->getMessage());
        $errors[] = "Rol adı kontrolü yapılamadı.";
    }
    
    // Loadout kontrolü
    if ($suggested_loadout_id) {
        try {
            $loadout_stmt = $pdo->prepare("
                SELECT id FROM loadout_sets 
                WHERE id = :id AND status = 'published' 
                AND (visibility = 'public' OR (visibility = 'members_only' AND :is_member = 1))
            ");
            $loadout_stmt->execute([
                ':id' => $suggested_loadout_id,
                ':is_member' => is_user_approved() ? 1 : 0
            ]);
            
            if (!$loadout_stmt->fetch()) {
                $errors[] = "Seçilen teçhizat seti bulunamadı veya erişim yetkiniz yok.";
                $suggested_loadout_id = null;
            }
        } catch (PDOException $e) {
            error_log("Loadout check error: " . $e->getMessage());
            $suggested_loadout_id = null;
        }
    }
    
    // Skill tag gereksinimlerini validate et
    if (!empty($skill_tag_ids)) {
        try {
            $tag_check_stmt = $pdo->prepare("SELECT id FROM skill_tags WHERE id = ?");
            $valid_skill_tag_ids = [];
            
            foreach ($skill_tag_ids as $tag_id) {
                $tag_id = (int)$tag_id;
                $tag_check_stmt->execute([$tag_id]);
                if ($tag_check_stmt->fetch()) {
                    $valid_skill_tag_ids[] = $tag_id;
                }
            }
            $skill_tag_ids = $valid_skill_tag_ids;
        } catch (PDOException $e) {
            error_log("Skill tag validation error: " . $e->getMessage());
            $errors[] = "Skill tag kontrolü yapılamadı.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($edit_mode) {
                // MEVCUT TABLO YAPISINA GÖRE güncelleme
                $stmt = $pdo->prepare("
                    UPDATE event_roles 
                    SET role_name = :name, role_description = :description, 
                        role_icon = :icon, suggested_loadout_id = :loadout_id
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $role_name,
                    ':description' => $role_description,
                    ':icon' => $role_icon,
                    ':loadout_id' => $suggested_loadout_id,
                    ':id' => $role_id
                ]);
                
                // Mevcut gereksinimleri sil
                $del_stmt = $pdo->prepare("DELETE FROM event_role_requirements WHERE role_id = :role_id");
                $del_stmt->execute([':role_id' => $role_id]);
                
                $message = "Etkinlik rolü başarıyla güncellendi.";
            } else {
                // MEVCUT TABLO YAPISINA GÖRE yeni oluşturma
                $stmt = $pdo->prepare("
                    INSERT INTO event_roles 
                    (role_name, role_description, role_icon, suggested_loadout_id)
                    VALUES (:name, :description, :icon, :loadout_id)
                ");
                $stmt->execute([
                    ':name' => $role_name,
                    ':description' => $role_description,
                    ':icon' => $role_icon,
                    ':loadout_id' => $suggested_loadout_id
                ]);
                
                $role_id = $pdo->lastInsertId();
                $message = "Etkinlik rolü başarıyla oluşturuldu.";
            }
            
            // MEVCUT TABLO YAPISINA GÖRE skill tag gereksinimlerini kaydet - N:N ilişki
            if (!empty($skill_tag_ids)) {
                $req_stmt = $pdo->prepare("
                    INSERT INTO event_role_requirements (role_id, skill_tag_id)
                    VALUES (:role_id, :skill_tag_id)
                ");
                
                foreach ($skill_tag_ids as $skill_tag_id) {
                    $req_stmt->execute([
                        ':role_id' => $role_id,
                        ':skill_tag_id' => $skill_tag_id
                    ]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = $message;
            header('Location: view.php?id=' . $role_id);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Role save error: " . $e->getMessage());
            $errors[] = "Rol kaydedilirken bir hata oluştu.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Mevcut teçhizat setlerini çek
try {
    $loadouts_stmt = $pdo->prepare("
        SELECT ls.id, ls.set_name, u.username
        FROM loadout_sets ls
        LEFT JOIN users u ON ls.user_id = u.id
        WHERE ls.status = 'published' 
        AND (ls.visibility = 'public' OR (ls.visibility = 'members_only' AND :is_member = 1))
        ORDER BY ls.set_name ASC
    ");
    $loadouts_stmt->execute([':is_member' => is_user_approved() ? 1 : 0]);
    $available_loadouts = $loadouts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Loadouts fetch error: " . $e->getMessage());
    $available_loadouts = [];
}

// Skill tags'leri çek
try {
    $skill_tags_stmt = $pdo->prepare("
        SELECT id, tag_name 
        FROM skill_tags 
        ORDER BY tag_name ASC
    ");
    $skill_tags_stmt->execute();
    $available_skill_tags = $skill_tags_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Skill tags fetch error: " . $e->getMessage());
    $available_skill_tags = [];
}

// MEVCUT TABLO YAPISINA GÖRE mevcut rol gereksinimlerini çek - N:N ilişki
$existing_skill_tag_ids = [];
if ($edit_mode) {
    try {
        $req_stmt = $pdo->prepare("
            SELECT skill_tag_id
            FROM event_role_requirements
            WHERE role_id = :role_id
        ");
        $req_stmt->execute([':role_id' => $role_id]);
        $existing_skill_tag_ids = $req_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Requirements fetch error: " . $e->getMessage());
    }
}

$page_title = $edit_mode ? "Etkinlik Rolü Düzenle" : "Yeni Etkinlik Rolü Oluştur";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Roller', 'url' => '/events/roles/', 'icon' => 'fas fa-user-tag'],
    ['text' => $edit_mode ? 'Rolü Düzenle' : 'Yeni Rol Oluştur', 'url' => '', 'icon' => $edit_mode ? 'fas fa-edit' : 'fas fa-plus']
];

// Star Citizen operasyon rolleri için ikonlar
$role_icons = [
    'fas fa-star' => 'Squad Leader (Yıldız)',
    'fas fa-space-shuttle' => 'Pilot (Uzay Gemisi)',
    'fas fa-crosshairs' => 'Gunner (Nişangah)',
    'fas fa-medkit' => 'Medic (Tıp Çantası)',
    'fas fa-wrench' => 'Engineer (İngiliz Anahtarı)',
    'fas fa-search' => 'Scout (Arama)',
    'fas fa-shield-alt' => 'Marine (Kalkan)',
    'fas fa-boxes' => 'Cargo Handler (Kargolar)',
    'fas fa-satellite' => 'Communications (Uydu)',
    'fa-solid fa-satellite-dish' => 'Sensor Operator (Radar)',
    'fas fa-rocket' => 'Missile Operator (Füze)',
    'fas fa-cog' => 'Systems Operator (Dişli)',
    'fas fa-eye' => 'Observer (Göz)',
    'fas fa-map' => 'Navigator (Harita)',
    'fas fa-users' => 'Team Leader (Takım)',
    'fas fa-headset' => 'Command (Kulaklık)',
    'fas fa-fighter-jet' => 'Fighter Pilot (Savaş Uçağı)',
    'fas fa-truck' => 'Transport (Kamyon)',
    'fas fa-bomb' => 'Demolition (Bomba)',
    'fas fa-binoculars' => 'Spotter (Dürbün)'
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Layout başlat
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="../css/events_sidebar.css">
<link rel="stylesheet" href="css/create_role.css">

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="header-info">
            <h1>
                <i class="fas fa-user-tag"></i>
                <?= $edit_mode ? 'Etkinlik Rolü Düzenle' : 'Yeni Etkinlik Rolü Oluştur' ?>
            </h1>
            <p><?= $edit_mode ? 'Star Citizen operasyonları için rol tanımlarını düzenleyin' : 'Star Citizen operasyonları için rol tanımları oluşturun' ?></p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Rollere Dön
            </a>
            <?php if ($edit_mode): ?>
                <a href="view.php?id=<?= $role_id ?>" class="btn-secondary">
                    <i class="fas fa-eye"></i> Rol Detayını Gör
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="create-role-container">
    <form id="roleForm" method="POST" class="role-form">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Temel Bilgiler -->
        <div class="form-section">
            <h3>
                <i class="fas fa-info-circle"></i> 
                Temel Bilgiler
                <?php if ($edit_mode): ?>
                    <span style="font-size: 0.8rem; color: var(--gold); margin-left: 1rem; padding: 0.25rem 0.75rem; background: rgba(189, 145, 42, 0.1); border-radius: 15px; border: 1px solid var(--gold);">
                        <i class="fas fa-edit"></i> Düzenleme Modu
                    </span>
                <?php endif; ?>
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role_name">Rol Adı *</label>
                    <input type="text" id="role_name" name="role_name" required 
                           value="<?= $edit_mode ? htmlspecialchars($role_data['role_name']) : '' ?>"
                           placeholder="örn: Squad Leader, Pilot, Medic">
                </div>
            </div>
            
            <div class="form-group">
                <label for="role_description">Rol Açıklaması *</label>
                <textarea id="role_description" name="role_description" required rows="4"
                          placeholder="Bu rolün sorumluluklarını, gereksinimlerini ve görevlerini detaylı şekilde açıklayın..."><?= $edit_mode ? htmlspecialchars($role_data['role_description']) : '' ?></textarea>
            </div>
        </div>

        <!-- Icon Seçimi -->
        <div class="form-section">
            <h3><i class="fas fa-icons"></i> Rol İkonu</h3>
            <div class="icon-selection">
                <?php foreach ($role_icons as $icon_class => $icon_description): ?>
                    <label class="icon-option">
                        <input type="radio" name="role_icon" value="<?= $icon_class ?>" 
                               <?= ($edit_mode && $role_data['role_icon'] === $icon_class) || (!$edit_mode && $icon_class === 'fas fa-user') ? 'checked' : '' ?>>
                        <div class="icon-preview">
                            <i class="<?= $icon_class ?>"></i>
                            <span><?= $icon_description ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Önerilen Teçhizat -->
        <div class="form-section">
            <h3><i class="fas fa-user-astronaut"></i> Önerilen Teçhizat (Opsiyonel)</h3>
            
            <div class="form-group">
                <label for="suggested_loadout_id">Teçhizat Seti</label>
                <select id="suggested_loadout_id" name="suggested_loadout_id">
                    <option value="">Teçhizat seti seçilmedi</option>
                    <?php foreach ($available_loadouts as $loadout): ?>
                        <option value="<?= $loadout['id'] ?>" 
                                <?= ($edit_mode && $role_data['suggested_loadout_id'] == $loadout['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loadout['set_name']) ?> 
                            (<?= htmlspecialchars($loadout['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Bu role katılan oyuncular için önerilen teçhizat seti</small>
            </div>
        </div>

        <!-- MEVCUT TABLO YAPISINA GÖRE Skill Tag Gereksinimleri -->
        <div class="form-section">
            <h3><i class="fas fa-clipboard-check"></i> Skill Tag Gereksinimleri (Opsiyonel)</h3>
            <p class="section-description">Bu role katılabilmek için gerekli yetenek tag'leri (çoklu seçim)</p>
            
            <div class="form-group">
                <label for="skill_tag_ids">Gerekli Skill Tag'ler:</label>
                <select id="skill_tag_ids" name="skill_tag_ids[]" multiple style="min-height: 150px; width: 100%; padding: 0.75rem; background: var(--card-bg-3); border: 1px solid var(--border-1); border-radius: 6px; color: var(--lighter-grey);">
                    <?php foreach ($available_skill_tags as $tag): ?>
                        <option value="<?= $tag['id'] ?>" 
                                <?= in_array($tag['id'], $existing_skill_tag_ids) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tag['tag_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Birden fazla tag seçmek için Ctrl+Click kullanın. Seçilen tüm tag'ler zorunlu olarak kabul edilir.</small>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i>
                <?= $edit_mode ? 'Değişiklikleri Kaydet' : 'Rolü Oluştur' ?>
            </button>
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-times"></i>
                İptal Et
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validasyonu
    document.getElementById('roleForm').addEventListener('submit', function(e) {
        const roleName = document.getElementById('role_name').value.trim();
        const roleDescription = document.getElementById('role_description').value.trim();
        
        if (roleName.length < 3) {
            e.preventDefault();
            alert('Rol adı en az 3 karakter olmalıdır.');
            return;
        }
        
        if (roleDescription.length < 10) {
            e.preventDefault();
            alert('Rol açıklaması en az 10 karakter olmalıdır.');
            return;
        }
        
        // Düzenleme modunda onay sor
        <?php if ($edit_mode): ?>
            if (!confirm('<?= htmlspecialchars($role_data['role_name']) ?> rolünü güncellemek istediğinizden emin misiniz?')) {
                e.preventDefault();
                return;
            }
        <?php endif; ?>
    });
    
    // Icon seçimi animasyonu
    document.querySelectorAll('.icon-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Önceki seçimi temizle
            document.querySelectorAll('.icon-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Yeni seçimi işaretle
            if (this.checked) {
                this.closest('.icon-option').classList.add('selected');
            }
        });
        
        // Sayfa yüklendiğinde seçili olanı işaretle
        if (radio.checked) {
            radio.closest('.icon-option').classList.add('selected');
        }
    });
    
    // Multi-select için yardımcı text
    const skillTagSelect = document.getElementById('skill_tag_ids');
    if (skillTagSelect) {
        skillTagSelect.addEventListener('focus', function() {
            console.log('Çoklu seçim için Ctrl+Click kullanın');
        });
    }
});

// Önizleme fonksiyonu
function previewRole() {
    const roleName = document.getElementById('role_name').value;
    const roleDescription = document.getElementById('role_description').value;
    const selectedIcon = document.querySelector('input[name="role_icon"]:checked');
    
    if (!roleName || !roleDescription || !selectedIcon) {
        alert('Önizleme için tüm zorunlu alanları doldurun.');
        return;
    }
    
    const preview = `
        <div class="role-preview-modal">
            <div class="role-preview-content">
                <div class="role-card">
                    <div class="role-icon">
                        <i class="${selectedIcon.value}"></i>
                    </div>
                    <div class="role-info">
                        <h3>${roleName}</h3>
                        <p>${roleDescription}</p>
                    </div>
                </div>
                <button onclick="closePreview()" class="btn-secondary">Kapat</button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', preview);
}

function closePreview() {
    const modal = document.querySelector('.role-preview-modal');
    if (modal) {
        modal.remove();
    }
}
</script>

<?php
// Layout sonlandır
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>