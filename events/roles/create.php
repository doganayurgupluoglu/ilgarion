<?php
// /events/roles/create.php - Etkinlik rolü oluşturma sayfası

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

// Rol oluşturma yetkisi kontrolü - YENİ YETKİLER
if (!has_permission($pdo, 'event_role.create')) {
    $_SESSION['error_message'] = "Etkinlik rolü oluşturmak için yetkiniz bulunmuyor.";
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$edit_mode = false;
$role_data = null;
$role_id = 0;

// Düzenleme modu kontrolü
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $role_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT er.*, ls.set_name as loadout_name 
            FROM event_roles er 
            LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id 
            WHERE er.id = :id AND (er.created_by_user_id = :user_id OR :can_edit_all = 1)
        ");
        $stmt->execute([
            ':id' => $role_id,
            ':user_id' => $current_user_id,
            ':can_edit_all' => has_permission($pdo, 'event_role.edit_all') ? 1 : 0
        ]);
        $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role_data) {
            $edit_mode = true;
        } else {
            $_SESSION['error_message'] = "Rol bulunamadı veya düzenleme yetkiniz yok.";
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
        header('Location: ' . $_SERVER['PHP_SELF'] . ($edit_mode ? "?edit=$role_id" : ''));
        exit;
    }
    
    // Form verilerini al ve temizle
    $role_name = trim($_POST['role_name'] ?? '');
    $role_description = trim($_POST['role_description'] ?? '');
    $icon_class = trim($_POST['icon_class'] ?? 'fas fa-user');
    $visibility = $_POST['visibility'] ?? 'public';
    $suggested_loadout_id = !empty($_POST['suggested_loadout_id']) ? (int)$_POST['suggested_loadout_id'] : null;
    
    // Gereksinimler - Yeni yapı
    $skill_tag_requirements = $_POST['skill_tag_requirements'] ?? [];
    
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
    
    if (!in_array($visibility, ['public', 'members_only'])) {
        $visibility = 'public';
    }
    
    // Rol adı benzersizlik kontrolü (düzenleme modunda mevcut rol hariç)
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
    if (!empty($skill_tag_requirements)) {
        try {
            $valid_tag_ids = [];
            $tag_check_stmt = $pdo->prepare("SELECT id FROM skill_tags WHERE id = ?");
            
            foreach ($skill_tag_requirements as $requirement) {
                if (!empty($requirement['skill_tag_ids'])) {
                    $tag_ids = array_map('intval', array_filter($requirement['skill_tag_ids']));
                    
                    foreach ($tag_ids as $tag_id) {
                        $tag_check_stmt->execute([$tag_id]);
                        if ($tag_check_stmt->fetch()) {
                            $valid_tag_ids[] = $tag_id;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Skill tag validation error: " . $e->getMessage());
            $errors[] = "Skill tag kontrolü yapılamadı.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($edit_mode) {
                // Güncelleme
                $stmt = $pdo->prepare("
                    UPDATE event_roles 
                    SET role_name = :name, role_description = :description, 
                        icon_class = :icon, visibility = :visibility,
                        suggested_loadout_id = :loadout_id, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $role_name,
                    ':description' => $role_description,
                    ':icon' => $icon_class,
                    ':visibility' => $visibility,
                    ':loadout_id' => $suggested_loadout_id,
                    ':id' => $role_id
                ]);
                
                // Mevcut gereksinimleri sil
                $del_stmt = $pdo->prepare("DELETE FROM event_role_requirements WHERE event_role_id = :role_id");
                $del_stmt->execute([':role_id' => $role_id]);
                
                $message = "Etkinlik rolü başarıyla güncellendi.";
            } else {
                // Yeni oluşturma
                $stmt = $pdo->prepare("
                    INSERT INTO event_roles 
                    (role_name, role_description, icon_class, visibility, suggested_loadout_id, created_by_user_id)
                    VALUES (:name, :description, :icon, :visibility, :loadout_id, :user_id)
                ");
                $stmt->execute([
                    ':name' => $role_name,
                    ':description' => $role_description,
                    ':icon' => $icon_class,
                    ':visibility' => $visibility,
                    ':loadout_id' => $suggested_loadout_id,
                    ':user_id' => $current_user_id
                ]);
                
                $role_id = $pdo->lastInsertId();
                $message = "Etkinlik rolü başarıyla oluşturuldu.";
            }
            
            // Skill tag gereksinimlerini kaydet
            if (!empty($skill_tag_requirements)) {
                $req_stmt = $pdo->prepare("
                    INSERT INTO event_role_requirements 
                    (event_role_id, skill_tag_ids, is_required)
                    VALUES (:role_id, :skill_tag_ids, :required)
                ");
                
                foreach ($skill_tag_requirements as $requirement) {
                    if (!empty($requirement['skill_tag_ids'])) {
                        $tag_ids = array_map('intval', array_filter($requirement['skill_tag_ids']));
                        if (!empty($tag_ids)) {
                            $skill_tag_ids_str = implode(',', $tag_ids);
                            $req_stmt->execute([
                                ':role_id' => $role_id,
                                ':skill_tag_ids' => $skill_tag_ids_str,
                                ':required' => isset($requirement['required']) ? 1 : 0
                            ]);
                        }
                    }
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

// Mevcut rol gereksinimlerini çek (düzenleme modu için)
$existing_requirements = [];
if ($edit_mode) {
    try {
        $req_stmt = $pdo->prepare("
            SELECT err.*, 
                   GROUP_CONCAT(st.tag_name ORDER BY st.tag_name ASC) as tag_names,
                   err.skill_tag_ids
            FROM event_role_requirements err
            LEFT JOIN skill_tags st ON FIND_IN_SET(st.id, err.skill_tag_ids) > 0
            WHERE err.event_role_id = :role_id 
            GROUP BY err.id
            ORDER BY err.id
        ");
        $req_stmt->execute([':role_id' => $role_id]);
        $existing_requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <p>Star Citizen operasyonları için rol tanımları oluşturun</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<div class="create-role-container">
    <form id="roleForm" method="POST" class="role-form">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Temel Bilgiler -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Temel Bilgiler</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role_name">Rol Adı *</label>
                    <input type="text" id="role_name" name="role_name" required 
                           value="<?= $edit_mode ? htmlspecialchars($role_data['role_name']) : '' ?>"
                           placeholder="örn: Squad Leader, Pilot, Medic">
                </div>
                
                <div class="form-group">
                    <label for="visibility">Görünürlük *</label>
                    <select id="visibility" name="visibility" required>
                        <option value="public" <?= ($edit_mode && $role_data['visibility'] === 'public') ? 'selected' : '' ?>>
                            Herkese Açık
                        </option>
                        <option value="members_only" <?= ($edit_mode && $role_data['visibility'] === 'members_only') ? 'selected' : '' ?>>
                            Sadece Üyelere Açık
                        </option>
                    </select>
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
                        <input type="radio" name="icon_class" value="<?= $icon_class ?>" 
                               <?= ($edit_mode && $role_data['icon_class'] === $icon_class) || (!$edit_mode && $icon_class === 'fas fa-user') ? 'checked' : '' ?>>
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

        <!-- Skill Tag Gereksinimleri -->
        <div class="form-section">
            <h3><i class="fas fa-clipboard-check"></i> Skill Tag Gereksinimleri (Opsiyonel)</h3>
            <p class="section-description">Bu role katılabilmek için gerekli yetenek tag'leri</p>
            
            <div id="requirements-container">
                <?php if ($edit_mode && !empty($existing_requirements)): ?>
                    <?php foreach ($existing_requirements as $req): ?>
                        <div class="requirement-item">
                            <div class="form-row">
                                <div class="form-group" style="flex: 2;">
                                    <label>Skill Tag'ler:</label>
                                    <select name="skill_tag_requirements[<?= uniqid() ?>][skill_tag_ids][]" 
                                            class="skill-tag-select" multiple style="min-height: 80px;">
                                        <?php 
                                        $selected_tag_ids = explode(',', $req['skill_tag_ids']);
                                        foreach ($available_skill_tags as $tag): ?>
                                            <option value="<?= $tag['id'] ?>" 
                                                    <?= in_array($tag['id'], $selected_tag_ids) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tag['tag_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Birden fazla seçim için Ctrl+Click kullanın</small>
                                </div>
                                <div class="form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" 
                                               name="skill_tag_requirements[<?= uniqid() ?>][required]" 
                                               <?= $req['is_required'] ? 'checked' : '' ?>>
                                        Zorunlu
                                    </label>
                                </div>
                                <button type="button" class="btn-remove-requirement" onclick="removeRequirement(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" id="add-requirement" class="btn-secondary">
                <i class="fas fa-plus"></i> Skill Tag Gereksinimi Ekle
            </button>
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
    // Available skill tags data from PHP
    const availableSkillTags = <?= json_encode($available_skill_tags) ?>;
    
    // Gereksinim ekleme
    let requirementCounter = 0;
    
    document.getElementById('add-requirement').addEventListener('click', function() {
        const container = document.getElementById('requirements-container');
        const requirementId = 'req_' + (++requirementCounter);
        
        const requirementHtml = `
            <div class="requirement-item">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Skill Tag'ler:</label>
                        <select name="skill_tag_requirements[${requirementId}][skill_tag_ids][]" 
                                class="skill-tag-select" multiple style="min-height: 80px;">
                            ${availableSkillTags.map(tag => 
                                `<option value="${tag.id}">${tag.tag_name}</option>`
                            ).join('')}
                        </select>
                        <small>Birden fazla seçim için Ctrl+Click kullanın</small>
                    </div>
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="skill_tag_requirements[${requirementId}][required]">
                            Zorunlu
                        </label>
                    </div>
                    <button type="button" class="btn-remove-requirement" onclick="removeRequirement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', requirementHtml);
    });
    
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
});

function removeRequirement(button) {
    button.closest('.requirement-item').remove();
}

// Önizleme fonksiyonu
function previewRole() {
    const roleName = document.getElementById('role_name').value;
    const roleDescription = document.getElementById('role_description').value;
    const selectedIcon = document.querySelector('input[name="icon_class"]:checked');
    
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