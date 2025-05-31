<?php
// public/admin/edit_role.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

$page_title = "Yeni Rol Oluştur"; // Varsayılan başlık
$form_action = "create_role"; // Varsayılan action
$submit_button_text = "Rolü Oluştur";
$role_id_to_edit = null;

// Düzenleme modu için role_id'yi kontrol et
if (isset($_GET['role_id']) && is_numeric($_GET['role_id'])) {
    $role_id_to_edit = (int)$_GET['role_id'];
    require_permission($pdo, 'admin.roles.edit'); // Düzenleme yetkisi kontrolü
    $page_title = "Rolü Düzenle";
    $form_action = "update_role";
    $submit_button_text = "Değişiklikleri Kaydet";
} else {
    require_permission($pdo, 'admin.roles.create'); // Oluşturma yetkisi kontrolü
}

$role_data = [
    'name' => $_SESSION['form_input_role_edit']['role_name'] ?? '', // Session anahtarı güncellendi
    'description' => $_SESSION['form_input_role_edit']['role_description'] ?? '',
    'color' => $_SESSION['form_input_role_edit']['role_color'] ?? '#4a5568', 
    'permissions' => $_SESSION['form_input_role_edit']['permissions'] ?? []
];
unset($_SESSION['form_input_role_edit']); // Session anahtarı güncellendi

$all_available_permissions = [];
if (file_exists(BASE_PATH . '/src/config/permissions.php')) {
    $all_available_permissions = require BASE_PATH . '/src/config/permissions.php';
} else {
    $_SESSION['error_message'] = "Yetki yapılandırma dosyası (permissions.php) bulunamadı.";
}

$permission_groups = [];
foreach ($all_available_permissions as $perm_key => $perm_desc) {
    $group_name_parts = explode('.', $perm_key);
    $group_name = ucfirst($group_name_parts[0]);
    
    // Grupları daha anlamlı hale getirelim
    if (count($group_name_parts) > 1) {
        $sub_group = ucfirst($group_name_parts[1]);
        if ($group_name === 'Admin' && $sub_group === 'Users') $group_name = "Yönetim: Kullanıcılar";
        elseif ($group_name === 'Admin' && $sub_group === 'Roles') $group_name = "Yönetim: Roller";
        elseif ($group_name === 'Admin' && $sub_group === 'Settings') $group_name = "Yönetim: Ayarlar";
        elseif ($group_name === 'Admin') $group_name = "Yönetim: Genel"; // admin.panel.access gibi
        elseif ($group_name === 'Event') $group_name = "Etkinlik Yetkileri";
        elseif ($group_name === 'Gallery') $group_name = "Galeri Yetkileri";
        elseif ($group_name === 'Discussion') $group_name = "Tartışma Yetkileri";
        elseif ($group_name === 'Guide') $group_name = "Rehber Yetkileri";
        elseif ($group_name === 'Loadout') $group_name = "Teçhizat Yetkileri";
        // Diğer özel gruplamalar buraya eklenebilir
    } else {
        $group_name = "Genel Yetkiler";
    }
    
    $permission_groups[$group_name][$perm_key] = $perm_desc;
}
ksort($permission_groups);


if ($role_id_to_edit) { // Sadece düzenleme modunda mevcut rol bilgilerini çek
    try {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$role_id_to_edit]);
        $existing_role = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_role) {
            $role_data['name'] = $existing_role['name'];
            $role_data['description'] = $existing_role['description'];
            $role_data['color'] = $existing_role['color'];
            $role_data['permissions'] = !empty($existing_role['permissions']) ? explode(',', $existing_role['permissions']) : [];
        } else {
            $_SESSION['error_message'] = "Düzenlenecek rol bulunamadı.";
            header('Location: ' . get_auth_base_url() . '/admin/manage_roles.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Rol bilgileri yüklenirken bir hata oluştu: " . $e->getMessage();
        header('Location: ' . get_auth_base_url() . '/admin/manage_roles.php');
        exit;
    }
}

$is_core_role_editing = ($role_id_to_edit && in_array($role_data['name'], ['admin', 'member', 'dis_uye']));

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* edit_role.php için Gelişmiş UX Stilleri */
.edit-role-container-ux {
    width: 100%;
    max-width: 1100px; /* Daha geniş bir alan */
    margin: 30px auto;
    padding: 30px 35px;
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.edit-role-container-ux .page-header-form h1 {
    color: var(--gold);
    font-size: 2.1rem; /* Biraz küçültüldü */
    margin: 0;
    font-weight: 600;
}
.edit-role-container-ux .page-header-form {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-2);
}

.form-section-role-ux {
    margin-bottom: 30px;
    padding: 25px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}
.form-section-role-ux legend, .form-section-role-ux .section-legend-ux {
    font-size: 1.4rem; /* Bölüm başlığı büyütüldü */
    color: var(--light-gold);
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--darker-gold-1);
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-section-role-ux legend i.fas, .form-section-role-ux .section-legend-ux i.fas {
    font-size: 1em; /* İkon boyutu başlıkla orantılı */
    color: var(--gold);
}

.form-group-role-ux label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-size: 0.95rem;
    font-weight: 500;
}
.form-control-role-ux, input[type="color"].form-control-role-ux {
    width: 100%;
    padding: 11px 15px; /* Padding ayarlandı */
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
input[type="color"].form-control-role-ux {
    padding: 4px; /* Renk seçici için padding */
    height: 48px; /* Diğer inputlarla aynı yükseklik */
    cursor: pointer;
    border-radius: 6px;
}
.form-control-role-ux:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3.5px var(--transparent-gold);
}
.role-color-preview-wrapper {
    display: flex;
    align-items: center;
    gap: 15px;
}
.role-color-preview {
    width: 48px; /* input[type=color] ile aynı yükseklik */
    height: 48px;
    border-radius: 6px;
    border: 2px solid var(--darker-gold-1);
    transition: background-color 0.3s ease;
}

.permissions-header-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.permissions-header-controls .section-legend-ux {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
#toggleAllPermissions {
    font-size: 0.85rem;
    padding: 6px 12px;
    border-radius: 15px;
}

.permissions-grid-ux {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px; /* Kartlar arası boşluk */
}
.permission-group-card-ux {
    background-color: var(--charcoal);
    padding: 18px 20px;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    display: flex;
    flex-direction: column;
}
.permission-group-header-ux {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.permission-group-header-ux h5 {
    font-size: 1.05rem;
    color: var(--gold);
    margin: 0;
    font-weight: 600;
}
.permission-group-header-ux .toggle-group-permissions {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 10px;
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
}
.permission-group-header-ux .toggle-group-permissions:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
}

.permission-item-ux { display: block; margin-bottom: 10px; }
.permission-item-ux label {
    font-size: 0.9rem; /* Yetki metni biraz daha okunaklı */
    color: var(--lighter-grey);
    cursor: pointer;
    display: flex; /* Checkbox ve metni daha iyi hizala */
    align-items: flex-start; /* Uzun açıklamalarda checkbox üste gelsin */
    font-weight: normal;
}
.permission-item-ux input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
    accent-color: var(--turquase);
    transform: scale(1.15);
    margin-top: 2px; /* Metinle daha iyi hizalama */
    flex-shrink: 0;
}
.permission-item-ux .permission-text-wrapper {
    display: flex;
    flex-direction: column;
}
.permission-item-ux .permission-desc {
    /* Açıklama metni, zaten label içinde */
}
.permission-item-ux .permission-key-code {
    font-size: 0.75em;
    color: var(--light-grey);
    opacity: 0.7;
    margin-top: 2px;
    font-family: monospace;
}

.form-actions-role-ux {
    margin-top: 35px;
    padding-top: 25px;
    border-top: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}
.form-actions-role-ux .btn {
    padding: 11px 26px;
    font-size: 0.95rem;
    font-weight: 600;
}
/* .btn-primary ve .btn-secondary stilleri style.css'den */

.readonly-notice {
    background-color: var(--transparent-gold);
    color: var(--light-gold);
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 0.85rem;
    margin-top: 5px;
    border: 1px solid var(--gold);
}
</style>

<main class="main-content">
    <div class="container admin-container edit-role-container-ux">
        <div class="page-header-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="manage_roles.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Rol Listesine Dön</a>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <form action="/src/actions/handle_roles.php" method="POST" style="margin-top:20px;">
            <input type="hidden" name="action" value="<?php echo $form_action; ?>">
            <?php if ($role_id_to_edit): ?>
                <input type="hidden" name="role_id" value="<?php echo $role_id_to_edit; ?>">
            <?php endif; ?>

            <fieldset class="form-section-role-ux">
                <legend><i class="fas fa-info-circle"></i>Rol Temel Bilgileri</legend>
                <div class="form-group-role-ux">
                    <label for="role_name">Rol Adı (Benzersiz Sistem Anahtarı):</label>
                    <input type="text" id="role_name" name="role_name" class="form-control-role-ux" value="<?php echo htmlspecialchars($role_data['name']); ?>" required 
                           pattern="[a-z0-9_]+" title="Sadece küçük harf, rakam ve alt çizgi (_) kullanın."
                           <?php echo $is_core_role_editing ? 'readonly class="form-control-role-ux readonly-input" title="Temel rollerin sistem adı değiştirilemez."' : ''; ?>>
                    <small class="form-text" style="font-size:0.8em; color:var(--light-grey);">
                        Sistem içinde kullanılacak kısa ve benzersiz bir ad. <strong>Sadece küçük harf, rakam ve alt çizgi (_)</strong> içermelidir. Boşluk veya özel karakter kullanmayın.
                        <?php if ($is_core_role_editing): ?>
                            <span class="readonly-notice">Bu temel rolün sistem adı değiştirilemez.</span>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="form-group-role-ux">
                    <label for="role_description">Rol Açıklaması (Admin panelinde görünür):</label>
                    <input type="text" id="role_description" name="role_description" class="form-control-role-ux" value="<?php echo htmlspecialchars($role_data['description']); ?>" placeholder="Rolün kısa bir açıklaması...">
                </div>
                <div class="form-group-role-ux">
                    <label for="role_color">Rol Rengi:</label>
                    <div class="role-color-preview-wrapper">
                        <input type="color" id="role_color" name="role_color" class="form-control-role-ux" value="<?php echo htmlspecialchars($role_data['color']); ?>">
                        <div id="role_color_preview" class="role-color-preview" style="background-color: <?php echo htmlspecialchars($role_data['color']); ?>;"></div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section-role-ux">
                <div class="permissions-header-controls">
                    <legend class="section-legend-ux"><i class="fas fa-tasks"></i>Yetkiler</legend>
                    <button type="button" id="toggleAllPermissions" class="btn btn-sm btn-outline-secondary">Tümünü Seç / Kaldır</button>
                </div>
                <?php if (empty($permission_groups)): ?>
                    <p class="info-message">Tanımlı yetki bulunmamaktadır. Lütfen `src/config/permissions.php` dosyasını kontrol edin.</p>
                <?php else: ?>
                    <div class="permissions-grid-ux">
                        <?php foreach ($permission_groups as $group_name => $permissions_in_group): ?>
                            <div class="permission-group-card-ux">
                                <div class="permission-group-header-ux">
                                    <h5><?php echo htmlspecialchars($group_name); ?></h5>
                                    <button type="button" class="toggle-group-permissions" data-group-target="<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($group_name))); ?>">Tümünü Seç</button>
                                </div>
                                <div class="permission-group-items" id="group-<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($group_name))); ?>">
                                    <?php foreach ($permissions_in_group as $perm_key => $perm_desc): ?>
                                        <div class="permission-item-ux">
                                            <label for="perm_<?php echo htmlspecialchars($perm_key); ?>">
                                                <input type="checkbox" name="permissions[]" id="perm_<?php echo htmlspecialchars($perm_key); ?>" value="<?php echo htmlspecialchars($perm_key); ?>"
                                                       <?php echo in_array($perm_key, $role_data['permissions']) ? 'checked' : ''; ?>>
                                                <span class="permission-text-wrapper">
                                                    <span class="permission-desc"><?php echo htmlspecialchars($perm_desc); ?></span>
                                                    <code class="permission-key-code"><?php echo htmlspecialchars($perm_key); ?></code>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>

            <div class="form-actions-role-ux">
                <a href="manage_roles.php" class="btn btn-secondary">İptal</a>
                <button type="submit" class="btn btn-primary"><?php echo $submit_button_text; ?></button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleColorInput = document.getElementById('role_color');
    const roleColorPreview = document.getElementById('role_color_preview');

    if (roleColorInput && roleColorPreview) {
        roleColorInput.addEventListener('input', function() {
            roleColorPreview.style.backgroundColor = this.value;
        });
    }

    const toggleAllButton = document.getElementById('toggleAllPermissions');
    const allPermissionCheckboxes = document.querySelectorAll('.permissions-grid-ux input[type="checkbox"]');

    if (toggleAllButton && allPermissionCheckboxes.length > 0) {
        toggleAllButton.addEventListener('click', function() {
            let allChecked = true;
            allPermissionCheckboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    allChecked = false;
                }
            });

            const newCheckedState = !allChecked;
            allPermissionCheckboxes.forEach(checkbox => {
                checkbox.checked = newCheckedState;
            });
            this.textContent = newCheckedState ? 'Tümünü Kaldır' : 'Tümünü Seç';
        });
    }

    const groupToggleButtons = document.querySelectorAll('.toggle-group-permissions');
    groupToggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const groupTargetId = this.dataset.groupTarget;
            const groupCheckboxes = document.querySelectorAll('#group-' + groupTargetId + ' input[type="checkbox"]');
            
            if (groupCheckboxes.length > 0) {
                let allCheckedInGroup = true;
                groupCheckboxes.forEach(checkbox => {
                    if (!checkbox.checked) {
                        allCheckedInGroup = false;
                    }
                });

                const newCheckedStateInGroup = !allCheckedInGroup;
                groupCheckboxes.forEach(checkbox => {
                    checkbox.checked = newCheckedStateInGroup;
                });
                this.textContent = newCheckedStateInGroup ? 'Grubu Kaldır' : 'Grubu Seç';
            }
        });
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
