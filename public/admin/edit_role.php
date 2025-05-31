<?php
// public/admin/edit_role.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

$page_title = "Yeni Rol Oluştur";
$form_action = "create_role";
$submit_button_text = "Rolü Oluştur";
$role_id_to_edit = null;

// Düzenleme modu için role_id'yi güvenli şekilde kontrol et
if (isset($_GET['role_id']) && is_numeric($_GET['role_id'])) {
    $role_id_to_edit = (int)$_GET['role_id'];
    require_permission($pdo, 'admin.roles.edit');
    $page_title = "Rolü Düzenle";
    $form_action = "update_role";
    $submit_button_text = "Değişiklikleri Kaydet";
} else {
    require_permission($pdo, 'admin.roles.create');
}

// Session'dan form verilerini al (hata durumunda)
$role_data = [
    'name' => $_SESSION['form_input_role_edit']['role_name'] ?? '',
    'description' => $_SESSION['form_input_role_edit']['role_description'] ?? '',
    'color' => $_SESSION['form_input_role_edit']['role_color'] ?? '#4a5568',
    'priority' => $_SESSION['form_input_role_edit']['role_priority'] ?? 999,
    'permissions' => $_SESSION['form_input_role_edit']['permissions'] ?? []
];
unset($_SESSION['form_input_role_edit']);

// Tüm yetkileri güvenli şekilde çek
$permission_groups = [];
try {
    $all_available_permissions = get_all_permissions_grouped($pdo);
    
    if (empty($all_available_permissions)) {
        $_SESSION['error_message'] = "Sistem yetkilerinde bir sorun var. Lütfen yönetici ile iletişime geçin.";
    } else {
        $permission_groups = $all_available_permissions;
    }
} catch (Exception $e) {
    error_log("Yetkileri çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Yetkiler yüklenirken bir hata oluştu.";
}

// Düzenleme modunda mevcut rol bilgilerini güvenli şekilde çek
if ($role_id_to_edit) {
    try {
        $get_role_query = "SELECT * FROM roles WHERE id = :role_id";
        $get_role_params = [':role_id' => $role_id_to_edit];
        $stmt = execute_safe_query($pdo, $get_role_query, $get_role_params);
        $existing_role = $stmt->fetch();

        if ($existing_role) {
            $role_data['name'] = $existing_role['name'];
            $role_data['description'] = $existing_role['description'];
            $role_data['color'] = $existing_role['color'];
            $role_data['priority'] = $existing_role['priority'];
            
            // Rol yetkilerini güvenli şekilde çek
            $role_data['permissions'] = get_role_permissions($pdo, $role_id_to_edit);
        } else {
            $_SESSION['error_message'] = "Düzenlenecek rol bulunamadı.";
            header('Location: ' . get_auth_base_url() . '/admin/manage_roles.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Rol bilgileri yüklenirken hata: " . $e->getMessage());
        $_SESSION['error_message'] = "Rol bilgileri yüklenirken bir hata oluştu: " . $e->getMessage();
        header('Location: ' . get_auth_base_url() . '/admin/manage_roles.php');
        exit;
    }
}

$is_core_role_editing = ($role_id_to_edit && !is_role_name_editable($role_data['name']));

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* edit_role.php için Gelişmiş UX Stilleri */
.edit-role-container-ux {
    width: 100%;
    max-width: 1100px;
    margin: 30px auto;
    padding: 30px 35px;
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.edit-role-container-ux .page-header-form h1 {
    color: var(--gold);
    font-size: 2.1rem;
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

.security-notice {
    background-color: var(--transparent-gold);
    color: var(--light-gold);
    padding: 12px 15px;
    border-radius: 5px;
    font-size: 0.9rem;
    margin-bottom: 20px;
    border: 1px solid var(--gold);
    display: flex;
    align-items: center;
    gap: 10px;
}

.security-notice i {
    color: var(--gold);
}

.form-section-role-ux {
    margin-bottom: 30px;
    padding: 25px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}
.form-section-role-ux legend, .form-section-role-ux .section-legend-ux {
    font-size: 1.4rem;
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
    font-size: 1em;
    color: var(--gold);
}

.form-group-role-ux {
    margin-bottom: 20px;
}

.form-group-role-ux label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-size: 0.95rem;
    font-weight: 500;
}
.form-control-role-ux, input[type="color"].form-control-role-ux, input[type="number"].form-control-role-ux {
    width: 100%;
    padding: 11px 15px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
input[type="color"].form-control-role-ux {
    padding: 4px;
    height: 48px;
    cursor: pointer;
    border-radius: 6px;
}
input[type="number"].form-control-role-ux {
    max-width: 150px;
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
    width: 48px;
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
    gap: 25px;
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

.permission-item-ux {
    display: block;
    margin-bottom: 10px;
}
.permission-item-ux label {
    font-size: 0.9rem;
    color: var(--lighter-grey);
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    font-weight: normal;
}
.permission-item-ux input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
    accent-color: var(--turquase);
    transform: scale(1.15);
    margin-top: 2px;
    flex-shrink: 0;
}
.permission-item-ux .permission-text-wrapper {
    display: flex;
    flex-direction: column;
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

.readonly-notice {
    background-color: var(--transparent-gold);
    color: var(--light-gold);
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 0.85rem;
    margin-top: 5px;
    border: 1px solid var(--gold);
}

.input-help-text {
    font-size: 0.8em;
    color: var(--light-grey);
    margin-top: 5px;
    line-height: 1.4;
}

.validation-error {
    color: var(--red);
    font-size: 0.8rem;
    margin-top: 5px;
    display: none;
}

.form-control-role-ux.is-invalid {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
}
</style>

<main class="main-content">
    <div class="container admin-container edit-role-container-ux">
        <div class="page-header-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="manage_roles.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Rol Listesine Dön
            </a>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <!-- Super Admin Yönetimi (Sadece super admin'ler için) -->
        <?php if (is_super_admin($pdo)): ?>
        <div style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-crown" style="font-size: 1.3rem;"></i>
            <div style="flex: 1;">
                <strong>Süper Admin Ayrıcalıkları:</strong> 
                Normal rol yetkilerinin yanında tüm sistem yetkilerine erişiminiz var.
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="manage_super_admins.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                    <i class="fas fa-crown"></i> Süper Admin'leri Yönet
                </a>
                <a href="audit_log.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                    <i class="fas fa-clipboard-list"></i> Audit Log
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <span>Bu form gelişmiş güvenlik koruması, CSRF koruması ve input validation ile korunmaktadır.</span>
        </div>

        <form action="/src/actions/handle_roles.php" method="POST" id="roleForm" style="margin-top:20px;">
            <input type="hidden" name="action" value="<?php echo htmlspecialchars($form_action); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <?php if ($role_id_to_edit): ?>
                <input type="hidden" name="role_id" value="<?php echo $role_id_to_edit; ?>">
            <?php endif; ?>

            <fieldset class="form-section-role-ux">
                <legend><i class="fas fa-info-circle"></i>Rol Temel Bilgileri</legend>
                
                <div class="form-group-role-ux">
                    <label for="role_name">Rol Adı (Benzersiz Sistem Anahtarı):</label>
                    <input type="text" id="role_name" name="role_name" class="form-control-role-ux" 
                           value="<?php echo htmlspecialchars($role_data['name']); ?>" required 
                           pattern="[a-z0-9_]{2,50}" 
                           title="2-50 karakter arası, sadece küçük harf, rakam ve alt çizgi (_) kullanın."
                           <?php echo $is_core_role_editing ? 'readonly title="Korumalı rollerin sistem adı değiştirilemez."' : ''; ?>
                           data-validation="role_name">
                    <div class="input-help-text">
                        Sistem içinde kullanılacak kısa ve benzersiz bir ad. <strong>Sadece küçük harf, rakam ve alt çizgi (_)</strong> içermelidir.
                        <?php if ($is_core_role_editing): ?>
                            <span class="readonly-notice">Bu korumalı rolün sistem adı güvenlik nedeniyle değiştirilemez.</span>
                        <?php endif; ?>
                    </div>
                    <div class="validation-error" id="role_name_error"></div>
                </div>
                
                <div class="form-group-role-ux">
                    <label for="role_description">Rol Açıklaması (Admin panelinde görünür):</label>
                    <input type="text" id="role_description" name="role_description" class="form-control-role-ux" 
                           value="<?php echo htmlspecialchars($role_data['description']); ?>" 
                           placeholder="Rolün kısa bir açıklaması..." maxlength="255"
                           data-validation="role_description">
                    <div class="input-help-text">Maksimum 255 karakter. Bu açıklama admin panelinde görünecektir.</div>
                    <div class="validation-error" id="role_description_error"></div>
                </div>
                
                <div class="form-group-role-ux">
                    <label for="role_color">Rol Rengi:</label>
                    <div class="role-color-preview-wrapper">
                        <input type="color" id="role_color" name="role_color" class="form-control-role-ux" 
                               value="<?php echo htmlspecialchars($role_data['color']); ?>"
                               data-validation="role_color">
                        <div id="role_color_preview" class="role-color-preview" 
                             style="background-color: <?php echo htmlspecialchars($role_data['color']); ?>;"></div>
                    </div>
                    <div class="input-help-text">Bu renk kullanıcı adlarında ve rol gösterimlerinde kullanılacaktır.</div>
                </div>
                
                <div class="form-group-role-ux">
                    <label for="role_priority">Rol Önceliği:</label>
                    <input type="number" id="role_priority" name="role_priority" class="form-control-role-ux" 
                           value="<?php echo htmlspecialchars($role_data['priority']); ?>" 
                           min="1" max="9999" required
                           data-validation="role_priority">
                    <div class="input-help-text">
                        Düşük sayı = yüksek öncelik. Admin rolleri genellikle 1-10, üye rolleri 100+, misafir rolleri 900+ olmalıdır.
                    </div>
                    <div class="validation-error" id="role_priority_error"></div>
                </div>
            </fieldset>

            <fieldset class="form-section-role-ux">
                <div class="permissions-header-controls">
                    <legend class="section-legend-ux"><i class="fas fa-tasks"></i>Yetkiler</legend>
                    <button type="button" id="toggleAllPermissions" class="btn btn-sm btn-outline-secondary">
                        Tümünü Seç / Kaldır
                    </button>
                </div>
                
                <?php if (empty($permission_groups)): ?>
                    <p class="info-message">Tanımlı yetki bulunmamaktadır. Lütfen sistem yöneticisi ile iletişime geçin.</p>
                <?php else: ?>
                    <div class="permissions-grid-ux">
                        <?php foreach ($permission_groups as $group_name => $permissions_in_group): ?>
                            <div class="permission-group-card-ux">
                                <div class="permission-group-header-ux">
                                    <h5><?php echo htmlspecialchars($group_name); ?></h5>
                                    <button type="button" class="toggle-group-permissions" 
                                            data-group-target="<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($group_name))); ?>">
                                        Grubu Seç
                                    </button>
                                </div>
                                <div class="permission-group-items" id="group-<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($group_name))); ?>">
                                    <?php foreach ($permissions_in_group as $perm_key => $perm_desc): ?>
                                        <div class="permission-item-ux">
                                            <label for="perm_<?php echo htmlspecialchars($perm_key); ?>">
                                                <input type="checkbox" name="permissions[]" 
                                                       id="perm_<?php echo htmlspecialchars($perm_key); ?>" 
                                                       value="<?php echo htmlspecialchars($perm_key); ?>"
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
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <?php echo htmlspecialchars($submit_button_text); ?>
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Renk önizleme
    const roleColorInput = document.getElementById('role_color');
    const roleColorPreview = document.getElementById('role_color_preview');

    if (roleColorInput && roleColorPreview) {
        roleColorInput.addEventListener('input', function() {
            roleColorPreview.style.backgroundColor = this.value;
        });
    }

    // Tümünü seç/kaldır
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

    // Grup bazında seç/kaldır
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

    // Form validation
    const form = document.getElementById('roleForm');
    const submitBtn = document.getElementById('submitBtn');

    // Real-time validation
    const validateField = (field) => {
        const value = field.value.trim();
        const fieldName = field.dataset.validation;
        let isValid = true;
        let errorMessage = '';

        switch (fieldName) {
            case 'role_name':
                if (!value) {
                    errorMessage = 'Rol adı gereklidir.';
                    isValid = false;
                } else if (!/^[a-z0-9_]{2,50}$/.test(value)) {
                    errorMessage = 'Sadece 2-50 karakter arası küçük harf, rakam ve alt çizgi (_) kullanın.';
                    isValid = false;
                }
                break;
            case 'role_description':
                if (value.length > 255) {
                    errorMessage = 'Açıklama 255 karakterden uzun olamaz.';
                    isValid = false;
                }
                break;
            case 'role_priority':
                const priority = parseInt(value);
                if (!value || isNaN(priority) || priority < 1 || priority > 9999) {
                    errorMessage = 'Öncelik 1-9999 arasında bir sayı olmalıdır.';
                    isValid = false;
                }
                break;
        }

        // Error display
        const errorElement = document.getElementById(fieldName + '_error');
        if (errorElement) {
            if (isValid) {
                errorElement.style.display = 'none';
                field.classList.remove('is-invalid');
            } else {
                errorElement.textContent = errorMessage;
                errorElement.style.display = 'block';
                field.classList.add('is-invalid');
            }
        }

        return isValid;
    };

    // Validation event listeners
    document.querySelectorAll('[data-validation]').forEach(field => {
        field.addEventListener('blur', () => validateField(field));
        field.addEventListener('input', () => {
            // Clear error on input
            field.classList.remove('is-invalid');
            const errorElement = document.getElementById(field.dataset.validation + '_error');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        });
    });

    // Form submit validation
    form.addEventListener('submit', function(e) {
        let formIsValid = true;
        
        // Validate all fields
        document.querySelectorAll('[data-validation]').forEach(field => {
            if (!validateField(field)) {
                formIsValid = false;
            }
        });

        // Check if at least some permissions are selected (warning, not error)
        const checkedPermissions = document.querySelectorAll('.permissions-grid-ux input[type="checkbox"]:checked');
        if (checkedPermissions.length === 0) {
            const confirmed = confirm('Hiç yetki seçmediniz. Bu rol hiçbir işlem yapamayacak. Devam etmek istediğinizden emin misiniz?');
            if (!confirmed) {
                formIsValid = false;
            }
        }

        if (!formIsValid) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
        
        // Re-enable after 10 seconds (safety net)
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<?php echo htmlspecialchars($submit_button_text); ?>';
        }, 10000);
    });

    // Security: Disable form if page has been open too long (CSRF token expiry)
    setTimeout(() => {
        const warningMessage = document.createElement('div');
        warningMessage.className = 'security-notice';
        warningMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Güvenlik nedeniyle sayfa yenilenecek. Değişikliklerinizi kaydedin.</span>';
        document.querySelector('.edit-role-container-ux').insertBefore(warningMessage, document.querySelector('form'));
        
        setTimeout(() => {
            location.reload();
        }, 60000); // 1 minute warning, then reload
    }, 3300000); // 55 minutes
    
    // Character counter for description
    const descField = document.getElementById('role_description');
    if (descField) {
        const helpText = descField.nextElementSibling;
        const updateCharCount = () => {
            const remaining = 255 - descField.value.length;
            helpText.innerHTML = `Maksimum 255 karakter. Kalan: <strong>${remaining}</strong>`;
            if (remaining < 0) {
                helpText.style.color = 'var(--red)';
            } else if (remaining < 20) {
                helpText.style.color = 'var(--gold)';
            } else {
                helpText.style.color = 'var(--light-grey)';
            }
        };
        
        descField.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial call
    }

    // Auto-save to localStorage (as backup, not for security)
    const autoSaveForm = () => {
        if (typeof(Storage) !== "undefined") {
            const formData = {
                role_name: document.getElementById('role_name').value,
                role_description: document.getElementById('role_description').value,
                role_color: document.getElementById('role_color').value,
                role_priority: document.getElementById('role_priority').value,
                timestamp: Date.now()
            };
            localStorage.setItem('role_form_backup', JSON.stringify(formData));
        }
    };

    // Auto-save every 30 seconds
    setInterval(autoSaveForm, 30000);

    // Restore from backup if available (only if form is empty)
    const restoreFromBackup = () => {
        if (typeof(Storage) !== "undefined") {
            const backup = localStorage.getItem('role_form_backup');
            if (backup) {
                const backupData = JSON.parse(backup);
                const age = Date.now() - backupData.timestamp;
                
                // Only restore if backup is less than 1 hour old and form is mostly empty
                if (age < 3600000 && !document.getElementById('role_name').value) {
                    const confirmed = confirm('Yarım kalmış bir form bulundu. Geri yüklemek istiyor musunuz?');
                    if (confirmed) {
                        document.getElementById('role_name').value = backupData.role_name || '';
                        document.getElementById('role_description').value = backupData.role_description || '';
                        document.getElementById('role_color').value = backupData.role_color || '#4a5568';
                        document.getElementById('role_priority').value = backupData.role_priority || '999';
                        
                        // Update color preview
                        document.getElementById('role_color_preview').style.backgroundColor = backupData.role_color || '#4a5568';
                    }
                }
            }
        }
    };

    // Only restore for new role creation
    <?php if (!$role_id_to_edit): ?>
    restoreFromBackup();
    <?php endif; ?>

    // Clear backup on successful submit
    form.addEventListener('submit', () => {
        if (typeof(Storage) !== "undefined") {
            localStorage.removeItem('role_form_backup');
        }
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>