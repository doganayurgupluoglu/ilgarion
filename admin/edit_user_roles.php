<?php
// public/admin/edit_user_roles.php - Hiyerarşik Rol Sistemi ile Revize Edilmiş

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Yetki kontrolü - Kullanıcılara rol atama/kaldırma yetkisi gerekli
require_permission($pdo, 'admin.users.assign_roles');

$user_id_to_edit = null;
$user_to_edit = null;
$all_system_roles = [];
$user_current_role_ids = [];
$manageable_roles = [];
$user_current_roles_details = [];

// URL'den kullanıcı ID'sini al ve validate et
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id_to_edit = (int)$_GET['user_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si.";
    header('Location: ' . get_auth_base_url() . '/admin/users.php');
    exit;
}

// Kullanıcının hiyerarşik konumunu belirle
$current_user_id = $_SESSION['user_id'];
$is_super_admin_user = is_super_admin($pdo);
$user_roles = get_user_roles($pdo, $current_user_id);
$user_highest_priority = 999;
$user_role_names = [];

foreach ($user_roles as $role) {
    $user_role_names[] = $role['name'];
    if ($role['priority'] < $user_highest_priority) {
        $user_highest_priority = $role['priority'];
    }
}

try {
    // Düzenlenecek kullanıcının bilgilerini çek
    $user_query = "
        SELECT u.id, u.username, u.email, u.ingame_name, u.status,
               MIN(r.priority) as user_highest_priority
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = ?
        GROUP BY u.id, u.username, u.email, u.ingame_name, u.status
    ";
    $stmt_user = execute_safe_query($pdo, $user_query, [$user_id_to_edit]);
    $user_to_edit = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_to_edit) {
        $_SESSION['error_message'] = "Rolleri düzenlenecek kullanıcı bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/admin/users.php');
        exit;
    }

    // Hiyerarşi kontrolü - Bu kullanıcıyı yönetebiliyor mu?
    $target_user_priority = $user_to_edit['user_highest_priority'] ?? 999;
    
    if (!$is_super_admin_user) {
        // Kendini düzenleyemez
        if ($user_id_to_edit == $current_user_id) {
            $_SESSION['error_message'] = "Kendi rollerinizi bu arayüzden düzenleyemezsiniz.";
            header('Location: ' . get_auth_base_url() . '/admin/users.php');
            exit;
        }
        
        // Üst seviye kullanıcıyı düzenleyemez
        if ($target_user_priority <= $user_highest_priority) {
            $_SESSION['error_message'] = "Bu kullanıcıyı yönetme yetkiniz bulunmamaktadır (hiyerarşi kısıtlaması).";
            header('Location: ' . get_auth_base_url() . '/admin/users.php');
            exit;
        }
    }

    // Sistemdeki tüm rolleri çek
    $all_roles_query = "SELECT id, name, description, color, priority FROM roles ORDER BY priority ASC, name ASC";
    $stmt_all_roles = execute_safe_query($pdo, $all_roles_query);
    $all_system_roles = $stmt_all_roles->fetchAll(PDO::FETCH_ASSOC);

    // Kullanıcının yönetebileceği rolleri belirle (kendi seviyesinden düşük olanlar)
    if ($is_super_admin_user) {
        $manageable_roles = $all_system_roles;
    } else {
        $manageable_roles = array_filter($all_system_roles, function($role) use ($user_highest_priority) {
            return $role['priority'] > $user_highest_priority;
        });
    }

    // Kullanıcının mevcut rollerini çek
    $current_roles_query = "
        SELECT ur.role_id, r.name, r.description, r.color, r.priority
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
        ORDER BY r.priority ASC
    ";
    $stmt_user_roles = execute_safe_query($pdo, $current_roles_query, [$user_id_to_edit]);
    $user_current_roles_details = $stmt_user_roles->fetchAll(PDO::FETCH_ASSOC);
    $user_current_role_ids = array_column($user_current_roles_details, 'role_id');

    // Güvenlik kontrolü: Kullanıcının sahip olduğu rollerden yönetemeyeceği olanları belirle
    $protected_roles = [];
    foreach ($user_current_roles_details as $current_role) {
        if (!$is_super_admin_user && $current_role['priority'] <= $user_highest_priority) {
            $protected_roles[] = $current_role['role_id'];
        }
    }

} catch (Exception $e) {
    error_log("Kullanıcı rolleri düzenleme sayfası yükleme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Veriler yüklenirken bir hata oluştu.";
}

$page_title = "Kullanıcı Rollerini Düzenle: " . htmlspecialchars($user_to_edit['username'] ?? 'Bilinmiyor');

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* edit_user_roles.php için geliştirilmiş stiller */
.edit-roles-container {
    width: 100%;
    max-width: 1400px;
    margin: 30px auto;
    padding: 25px 30px;
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    color: var(--lighter-grey);
}

.user-info-header {
    background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1));
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 4px solid var(--gold);
}

.user-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.user-info-item {
    background: var(--darker-gold-2);
    padding: 12px 15px;
    border-radius: 6px;
    border: 1px solid var(--darker-gold-1);
}

.user-info-label {
    font-size: 0.8rem;
    color: var(--light-gold);
    margin-bottom: 5px;
    font-weight: 500;
}

.user-info-value {
    font-size: 1rem;
    color: var(--lighter-grey);
    font-weight: 600;
}

.hierarchy-warning {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid #ffc107;
    color: #ffc107;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.roles-management-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.current-roles-panel, .available-roles-panel {
    background-color: var(--darker-gold-2);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid var(--darker-gold-1);
}

.panel-header {
    font-size: 1.2rem;
    color: var(--light-gold);
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--darker-gold-1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.role-item-advanced {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    padding: 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.role-item-advanced:hover {
    background-color: var(--darker-gold-1);
    border-color: var(--gold);
}

.role-item-advanced.protected {
    background-color: rgba(255, 107, 107, 0.1);
    border-color: #ff6b6b;
    opacity: 0.7;
}

.role-item-advanced.manageable {
    background-color: rgba(40, 167, 69, 0.1);
    border-color: #28a745;
}

.role-checkbox {
    width: auto;
    margin-right: 12px;
    accent-color: var(--gold);
    transform: scale(1.3);
    margin-top: 3px;
    flex-shrink: 0;
}

.role-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.role-details {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.role-name-line {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}

.role-color-indicator {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    margin-right: 10px;
    border: 1px solid rgba(255,255,255,0.2);
    flex-shrink: 0;
}

.role-name {
    font-size: 1rem;
    color: var(--lighter-grey);
    font-weight: 500;
    margin-right: 10px;
}

.role-priority {
    background: var(--gold);
    color: var(--black);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: bold;
    margin-left: auto;
}

.role-description {
    font-size: 0.85rem;
    color: var(--light-grey);
    line-height: 1.3;
    margin-top: 2px;
}

.role-status-indicator {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 500;
    margin-left: 10px;
}

.role-status-indicator.protected {
    background-color: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
}

.role-status-indicator.manageable {
    background-color: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.form-actions-enhanced {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.action-summary {
    color: var(--light-gold);
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.permissions-info {
    background: var(--transparent-turquase);
    padding: 15px;
    border-radius: 6px;
    margin-top: 20px;
    border: 1px solid var(--turquase);
}

.permissions-info h4 {
    color: var(--turquase);
    margin: 0 0 10px 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.manageable-roles-count {
    background: var(--turquase);
    color: var(--black);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
    margin-left: auto;
}

@media (max-width: 768px) {
    .roles-management-section {
        grid-template-columns: 1fr;
    }
    
    .form-actions-enhanced {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<main class="main-content">
    <div class="container admin-container edit-roles-container">
        <div class="page-header-form">
            <h1 style="color: var(--gold); text-align: center; margin-bottom: 20px;">
                <i class="fas fa-user-cog"></i> Kullanıcı Rollerini Düzenle
            </h1>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <?php if ($user_to_edit): ?>
            <!-- Kullanıcı Bilgileri -->
            <div class="user-info-header">
                <h3 style="color: var(--gold); margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user"></i>
                    Düzenlenen Kullanıcı Bilgileri
                    <?php if ($user_id_to_edit == $current_user_id): ?>
                        <span style="background: var(--turquase); color: var(--black); padding: 4px 12px; border-radius: 12px; font-size: 0.8rem;">SİZ</span>
                    <?php endif; ?>
                </h3>
                
                <div class="user-info-grid">
                    <div class="user-info-item">
                        <div class="user-info-label">Kullanıcı Adı</div>
                        <div class="user-info-value"><?php echo htmlspecialchars($user_to_edit['username']); ?></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">E-posta</div>
                        <div class="user-info-value"><?php echo htmlspecialchars($user_to_edit['email']); ?></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Oyun İçi İsim</div>
                        <div class="user-info-value"><?php echo htmlspecialchars($user_to_edit['ingame_name']); ?></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Durum</div>
                        <div class="user-info-value">
                            <span style="color: var(--turquase);"><?php echo ucfirst($user_to_edit['status']); ?></span>
                        </div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Hiyerarşi Seviyesi</div>
                        <div class="user-info-value">
                            <?php echo $target_user_priority; ?>
                            <span style="font-size: 0.8rem; color: var(--light-gold); margin-left: 10px;">
                                (<?php echo $target_user_priority > $user_highest_priority ? 'Yönetilebilir' : 'Üst Seviye'; ?>)
                            </span>
                        </div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Mevcut Rol Sayısı</div>
                        <div class="user-info-value"><?php echo count($user_current_roles_details); ?></div>
                    </div>
                </div>
            </div>

            <!-- Hiyerarşi Uyarısı (gerekirse) -->
            <?php if (!$is_super_admin_user): ?>
            <div class="hierarchy-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Hiyerarşi Kısıtlaması:</strong> 
                    Sadece kendi seviyenizden (<?php echo $user_highest_priority; ?>) düşük öncelikli rolleri atayabilir/kaldırabilirsiniz.
                    <?php if (!empty($protected_roles)): ?>
                    Bu kullanıcının <?php echo count($protected_roles); ?> rolü korunmalı ve değiştirilemez.
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($manageable_roles)): ?>
                <form action="../../src/actions/handle_user_roles_update.php" method="POST" id="rolesForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="user_id_to_update" value="<?php echo $user_to_edit['id']; ?>">
                    
                    <div class="roles-management-section">
                        <!-- Mevcut Roller -->
                        <div class="current-roles-panel">
                            <div class="panel-header">
                                <i class="fas fa-user-tag"></i>
                                Mevcut Roller
                                <span class="manageable-roles-count"><?php echo count($user_current_roles_details); ?></span>
                            </div>
                            
                            <?php if (!empty($user_current_roles_details)): ?>
                                <?php foreach ($user_current_roles_details as $current_role): 
                                    $is_protected = in_array($current_role['role_id'], $protected_roles);
                                    $is_manageable = !$is_protected;
                                ?>
                                    <div class="role-item-advanced <?php echo $is_protected ? 'protected' : 'manageable'; ?>">
                                        <div class="role-details">
                                            <div class="role-name-line">
                                                <span class="role-color-indicator" style="background-color: <?php echo htmlspecialchars($current_role['color']); ?>;"></span>
                                                <span class="role-name"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $current_role['name']))); ?></span>
                                                <span class="role-priority">Öncelik: <?php echo $current_role['priority']; ?></span>
                                                <span class="role-status-indicator <?php echo $is_protected ? 'protected' : 'manageable'; ?>">
                                                    <?php echo $is_protected ? 'Korumalı' : 'Yönetilebilir'; ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($current_role['description'])): ?>
                                                <div class="role-description"><?php echo htmlspecialchars($current_role['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: var(--light-grey); padding: 20px;">
                                    <i class="fas fa-user-minus" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                    Bu kullanıcıya henüz rol atanmamış.
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Atanabilir Roller -->
                        <div class="available-roles-panel">
                            <div class="panel-header">
                                <i class="fas fa-plus-circle"></i>
                                Atanabilir Roller
                                <span class="manageable-roles-count"><?php echo count($manageable_roles); ?></span>
                            </div>
                            
                            <?php foreach ($manageable_roles as $role): 
                                $is_currently_assigned = in_array($role['id'], $user_current_role_ids);
                                $is_protected_current = in_array($role['id'], $protected_roles);
                            ?>
                                <div class="role-item-advanced manageable">
                                    <input type="checkbox" 
                                           name="assigned_roles[]" 
                                           id="role_<?php echo $role['id']; ?>" 
                                           value="<?php echo $role['id']; ?>"
                                           class="role-checkbox"
                                           <?php echo $is_currently_assigned ? 'checked' : ''; ?>
                                           <?php echo $is_protected_current ? 'disabled' : ''; ?>
                                           data-role-name="<?php echo htmlspecialchars($role['name']); ?>"
                                           data-role-priority="<?php echo $role['priority']; ?>">
                                    
                                    <label for="role_<?php echo $role['id']; ?>" class="role-details" style="cursor: pointer;">
                                        <div class="role-name-line">
                                            <!-- <span class="role-color-indicator" style="background-color: <?php echo htmlspecialchars($role['color']); ?>;"></span> -->
                                            <span class="role-name"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role['name']))); ?></span>
                                            <span class="role-priority">Öncelik: <?php echo $role['priority']; ?></span>
                                            <?php if ($is_protected_current): ?>
                                                <span class="role-status-indicator protected">Korumalı</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($role['description'])): ?>
                                            <div class="role-description"><?php echo htmlspecialchars($role['description']); ?></div>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Korumalı rolleri hidden input olarak ekle -->
                    <?php foreach ($protected_roles as $protected_role_id): ?>
                        <input type="hidden" name="assigned_roles[]" value="<?php echo $protected_role_id; ?>">
                    <?php endforeach; ?>

                    <div class="form-actions-enhanced">
                        <div class="action-summary">
                            <i class="fas fa-info-circle"></i>
                            <span id="changesSummary">Değişiklik yapmak için rolleri seçin/seçimi kaldırın</span>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kullanıcı Listesine Dön
                            </a>
                            <button type="submit" class="btn btn-primary" id="saveButton">
                                <i class="fas fa-save"></i> Rolleri Güncelle
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Yetki Bilgisi -->
                <div class="permissions-info">
                    <h4>
                        <i class="fas fa-shield-alt"></i>
                        Yetki Durumunuz
                    </h4>
                    <div style="color: var(--lighter-grey); line-height: 1.6;">
                        <div style="margin-bottom: 10px;">
                            <strong>Sizin Rolleriniz:</strong> 
                            <?php echo implode(', ', array_map('ucfirst', $user_role_names)); ?>
                            <span style="margin-left: 10px; background: var(--gold); color: var(--black); padding: 2px 8px; border-radius: 8px; font-size: 0.8rem;">
                                Seviye: <?php echo $user_highest_priority; ?>
                            </span>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong>Yönetebileceğiniz Roller:</strong> <?php echo count($manageable_roles); ?> adet
                        </div>
                        <?php if (!empty($protected_roles)): ?>
                        <div style="margin-bottom: 10px; color: #ff6b6b;">
                            <strong>Korumalı Roller:</strong> <?php echo count($protected_roles); ?> adet
                            <span style="font-size: 0.9rem; margin-left: 10px;">(Hiyerarşi nedeniyle değiştirilemez)</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($is_super_admin_user): ?>
                        <div style="background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; padding: 8px 12px; border-radius: 6px; margin-top: 10px;">
                            <i class="fas fa-crown"></i> <strong>SÜPER ADMİN:</strong> Tüm rolleri yönetebilirsiniz
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 40px; background-color: var(--transparent-gold); border-radius: 8px; border: 1px solid var(--gold);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--gold); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--gold); margin-bottom: 15px;">Yönetilebilir Rol Bulunamadı</h3>
                    <p style="color: var(--light-gold); margin-bottom: 20px;">
                        Mevcut hiyerarşi seviyenizde bu kullanıcıya atayabileceğiniz rol bulunmamaktadır.
                    </p>
                    <div style="margin-bottom: 20px; padding: 15px; background: var(--charcoal); border-radius: 6px;">
                        <strong>Sebep:</strong> Sadece kendi seviyenizden (<?php echo $user_highest_priority; ?>) 
                        düşük öncelikli rolleri yönetebilirsiniz.
                    </div>
                    <a href="users.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Kullanıcı Listesine Dön
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-user-times" style="font-size: 3rem; color: var(--red); margin-bottom: 20px;"></i>
                <h3 style="color: var(--red); margin-bottom: 15px;">Kullanıcı Bulunamadı</h3>
                <p style="color: var(--lighter-grey); margin-bottom: 20px;">
                    Düzenlemek istediğiniz kullanıcı bulunamadı veya erişim yetkiniz bulunmamaktadır.
                </p>
                <a href="users.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Kullanıcı Listesine Dön
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('rolesForm');
    const checkboxes = document.querySelectorAll('input[name="assigned_roles[]"]:not([type="hidden"])');
    const changesSummary = document.getElementById('changesSummary');
    const saveButton = document.getElementById('saveButton');
    
    // Initial state tracking
    const initialState = new Set();
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            initialState.add(checkbox.value);
        }
    });
    
    function updateChangesSummary() {
        const currentState = new Set();
        checkboxes.forEach(checkbox => {
            if (checkbox.checked && !checkbox.disabled) {
                currentState.add(checkbox.value);
            }
        });
        
        const added = [...currentState].filter(x => !initialState.has(x));
        const removed = [...initialState].filter(x => !currentState.has(x));
        
        let summary = '';
        let hasChanges = added.length > 0 || removed.length > 0;
        
        if (hasChanges) {
            const parts = [];
            if (added.length > 0) {
                parts.push(`<span style="color: #28a745;"><i class="fas fa-plus-circle"></i> ${added.length} rol eklenecek</span>`);
            }
            if (removed.length > 0) {
                parts.push(`<span style="color: #dc3545;"><i class="fas fa-minus-circle"></i> ${removed.length} rol kaldırılacak</span>`);
            }
            summary = parts.join(' • ');
            saveButton.style.background = 'var(--gold)';
            saveButton.style.borderColor = 'var(--gold)';
        } else {
            summary = '<i class="fas fa-info-circle"></i> Henüz değişiklik yapılmadı';
            saveButton.style.background = '';
            saveButton.style.borderColor = '';
        }
        
        changesSummary.innerHTML = summary;
        saveButton.disabled = !hasChanges;
    }
    
    // Event listeners for checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateChangesSummary);
    });
    
    // Form submission confirmation
    if (form) {
        form.addEventListener('submit', function(e) {
            const currentState = new Set();
            checkboxes.forEach(checkbox => {
                if (checkbox.checked && !checkbox.disabled) {
                    currentState.add(checkbox.value);
                }
            });
            
            const added = [...currentState].filter(x => !initialState.has(x));
            const removed = [...initialState].filter(x => !currentState.has(x));
            
            if (added.length === 0 && removed.length === 0) {
                e.preventDefault();
                alert('Herhangi bir değişiklik yapılmadı.');
                return;
            }
            
            let confirmMessage = '<?php echo htmlspecialchars($user_to_edit['username'] ?? ''); ?> kullanıcısının rollerini güncellemek istediğinizden emin misiniz?\n\n';
            
            if (added.length > 0) {
                const addedNames = added.map(id => {
                    const checkbox = document.querySelector(`input[value="${id}"]`);
                    return checkbox ? checkbox.dataset.roleName : id;
                });
                confirmMessage += `Eklenecek roller: ${addedNames.join(', ')}\n`;
            }
            
            if (removed.length > 0) {
                const removedNames = removed.map(id => {
                    const checkbox = document.querySelector(`input[value="${id}"]`);
                    return checkbox ? checkbox.dataset.roleName : id;
                });
                confirmMessage += `Kaldırılacak roller: ${removedNames.join(', ')}\n`;
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    }
    
    // Initialize summary
    updateChangesSummary();
    
    // Hover effects for role items
    const roleItems = document.querySelectorAll('.role-item-advanced');
    roleItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            const checkbox = this.querySelector('input[type="checkbox"]');
            if (!checkbox.disabled) {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            }
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // Keyboard navigation support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'users.php';
        }
    });
    
    // Auto-save warning
    let hasUnsavedChanges = false;
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            hasUnsavedChanges = true;
        });
    });
    
    if (form) {
        form.addEventListener('submit', function() {
            hasUnsavedChanges = false;
        });
    }
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'Kaydedilmemiş değişiklikleriniz var. Sayfadan ayrılmak istediğinizden emin misiniz?';
            return e.returnValue;
        }
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>