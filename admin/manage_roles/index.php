<?php
// /admin/manage_roles/index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Yetki kontrolü
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu sayfaya erişim için giriş yapmalısınız.";
    header('Location: ' . get_auth_base_url() . '/login.php');
    exit;
}

if (!has_permission($pdo, 'admin.roles.view')) {
    $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
    header('Location: /index.php');
    exit;
}

$page_title = 'Rol Yönetimi';
$additional_css = ['../../css/style.css'];
$additional_css = ['../admin/manage_roles/css/manage_roles.css'];
$additional_js = ['../admin/manage_roles/js/manage_roles.js'];

// Rol hiyerarşi verilerini al
$hierarchy_data = get_role_hierarchy_data($pdo, $_SESSION['user_id']);
$roles = $hierarchy_data['roles'];
$user_info = $hierarchy_data['user_info'];

// Tüm yetkileri gruplar halinde al
$permissions_grouped = get_all_permissions_grouped($pdo);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a>
        <span class="active"><i class="fas fa-user-tag"></i> Rol Yönetimi</span>
    </nav>

    <!-- Ana Başlık -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1 class="page-title">
                    <i class="fas fa-user-tag"></i>
                    Rol Yönetimi
                </h1>
                <p class="page-subtitle">Rolleri, yetkileri ve hiyerarşiyi yönetin</p>
            </div>
            
            <div class="page-actions">
                <?php if (has_permission($pdo, 'admin.roles.create')): ?>
                    <button class="btn btn-primary" onclick="openCreateRoleModal()">
                        <i class="fas fa-plus"></i>
                        Yeni Rol Oluştur
                    </button>
                <?php endif; ?>
                
                <button class="btn btn-secondary" onclick="refreshRoleData()">
                    <i class="fas fa-sync-alt"></i>
                    Yenile
                </button>
            </div>
        </div>
    </div>

    <!-- İstatistik Kartları -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users-cog"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= count($roles) ?></div>
                <div class="stat-label">Toplam Rol</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $user_info['manageable_count'] ?? 0 ?></div>
                <div class="stat-label">Yönetilebilir Rol</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-key"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= array_sum(array_map('count', $permissions_grouped)) ?></div>
                <div class="stat-label">Toplam Yetki</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-content">
            <div class="stat-number"><?= $user_info['priority'] ?? 999 ?></div>
                <div class="stat-label">Öncelik Seviyeniz</div>
            </div>
        </div>
    </div>

    <!-- Rol Listesi -->
    <div class="roles-container">
        <div class="roles-header">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Rol Listesi
            </h2>
            
            <div class="roles-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="roleSearch" placeholder="Rol ara...">
                </div>
                
                <select id="roleFilter" class="filter-select">
                    <option value="all">Tüm Roller</option>
                    <option value="manageable">Yönetilebilir</option>
                    <option value="protected">Korumalı</option>
                </select>
            </div>
        </div>

        <div class="roles-grid" id="rolesGrid">
            <?php foreach ($roles as $role): ?>
                <div class="role-card" data-role-id="<?= $role['id'] ?>" data-role-name="<?= strtolower($role['name']) ?>">
                    <div class="role-card-header" style="border-left-color: <?= htmlspecialchars($role['color']) ?>">
                        <div class="role-info">
                            <h3 class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>">
                                <?= htmlspecialchars($role['name']) ?>
                                <?php if ($role['is_protected']): ?>
                                    <i class="fas fa-shield-alt" title="Korumalı Rol"></i>
                                <?php endif; ?>
                                <?php if ($role['is_super_admin_only']): ?>
                                    <i class="fas fa-crown" title="Süper Admin Özel"></i>
                                <?php endif; ?>
                            </h3>
                            <p class="role-description"><?= htmlspecialchars($role['description']) ?></p>
                        </div>
                        
                        <div class="role-priority">
                            <span class="priority-badge">Öncelik: <?= $role['priority'] ?></span>
                        </div>
                    </div>

                    <div class="role-card-content">
                        <div class="role-stats">
                            <div class="role-stat">
                                <i class="fas fa-users"></i>
                                <span><?= $role['user_count'] ?> kullanıcı</span>
                            </div>
                            <div class="role-stat">
                                <i class="fas fa-key"></i>
                                <span><?= $role['permission_count'] ?> yetki</span>
                            </div>
                        </div>
                    </div>

                    <div class="role-card-actions">
                        <?php if ($role['can_manage']): ?>
                            <button class="btn btn-sm btn-outline" onclick="editRole(<?= $role['id'] ?>)">
                                <i class="fas fa-edit"></i>
                                Düzenle
                            </button>
                            
                            <button class="btn btn-sm btn-outline" onclick="managePermissions(<?= $role['id'] ?>)">
                                <i class="fas fa-key"></i>
                                Yetkiler
                            </button>
                            
                            <?php if (!$role['is_protected'] && ($role['can_manage'] ?? false)): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteRole(<?= $role['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline" onclick="viewRoleDetails(<?= $role['id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Görüntüle
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Rol Düzenleme Modal -->
<div id="editRoleModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-edit"></i>
                <span id="editModalTitle">Rol Düzenle</span>
            </h3>
            <button class="modal-close" onclick="closeEditRoleModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <form id="editRoleForm">
                <input type="hidden" id="editRoleId" name="role_id">
                
                <div class="form-group">
                    <label for="editRoleName">Rol Adı</label>
                    <input type="text" id="editRoleName" name="role_name" required>
                    <small class="form-text">Sadece küçük harf, rakam ve alt çizgi kullanın</small>
                </div>
                
                <div class="form-group">
                    <label for="editRoleDescription">Açıklama</label>
                    <textarea id="editRoleDescription" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editRoleColor">Renk</label>
                    <input type="color" id="editRoleColor" name="color" value="#bd912a">
                </div>
                
                <div class="form-group">
                    <label for="editRolePriority">Öncelik</label>
                    <input type="number" id="editRolePriority" name="priority" min="1" max="999" required>
                    <small class="form-text">Düşük sayı = Yüksek öncelik</small>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditRoleModal()">İptal</button>
            <button type="button" class="btn btn-primary" onclick="saveRole()">Kaydet</button>
        </div>
    </div>
</div>

<!-- Yetki Yönetimi Modal -->
<div id="permissionsModal" class="modal extra-large-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-key"></i>
                <span id="permissionsModalTitle">Yetki Yönetimi</span>
            </h3>
            <button class="modal-close" onclick="closePermissionsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <input type="hidden" id="permissionsRoleId">
            
            <div class="permissions-header">
                <div class="permission-stats">
                    <div class="stat-chip">
                        <i class="fas fa-check-circle"></i>
                        <span id="selectedCount">0</span> seçili
                    </div>
                    <div class="stat-chip">
                        <i class="fas fa-key"></i>
                        <span id="totalCount">0</span> toplam
                    </div>
                </div>
                
                <div class="permission-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="selectAllPermissions()">
                        <i class="fas fa-check-double"></i>
                        Tümünü Seç
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="clearAllPermissions()">
                        <i class="fas fa-times-circle"></i>
                        Tümünü Temizle
                    </button>
                </div>
                
                <div class="permission-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="permissionSearch" placeholder="Yetki ara...">
                </div>
            </div>
            
            <div class="permissions-container" id="permissionsContainer">
                <?php foreach ($permissions_grouped as $group_name => $permissions): ?>
                    <div class="permission-group" data-group="<?= htmlspecialchars($group_name) ?>">
                        <div class="permission-group-header">
                            <div class="group-info">
                                <h4 class="group-title">
                                    <i class="fas fa-folder-open"></i>
                                    <?= htmlspecialchars($group_name) ?>
                                </h4>
                                <div class="group-stats">
                                    <span class="group-count"><?= count($permissions) ?> yetki</span>
                                    <span class="group-selected">0 seçili</span>
                                </div>
                            </div>
                            
                            <div class="group-actions">
                                <button type="button" class="btn btn-xs btn-outline group-toggle" onclick="toggleGroupPermissions('<?= htmlspecialchars($group_name) ?>')">
                                    <i class="fas fa-check-square"></i>
                                    Grup Seç/Bırak
                                </button>
                                <button type="button" class="btn btn-xs btn-ghost group-collapse" onclick="toggleGroupCollapse('<?= htmlspecialchars($group_name) ?>')">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="permission-grid" data-group="<?= htmlspecialchars($group_name) ?>">
                            <?php foreach ($permissions as $permission_key => $permission_name): ?>
                                <div class="permission-card">
                                    <div class="permission-switch-container">
                                        <input type="checkbox" 
                                               id="perm_<?= htmlspecialchars($permission_key) ?>"
                                               name="permissions[]" 
                                               value="<?= htmlspecialchars($permission_key) ?>" 
                                               data-permission="<?= htmlspecialchars($permission_key) ?>"
                                               data-group="<?= htmlspecialchars($group_name) ?>"
                                               class="permission-switch">
                                        <label for="perm_<?= htmlspecialchars($permission_key) ?>" class="switch-label">
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="permission-info">
                                        <div class="permission-name"><?= htmlspecialchars($permission_name) ?></div>
                                        <div class="permission-key"><?= htmlspecialchars($permission_key) ?></div>
                                    </div>
                                    
                                    <div class="permission-status">
                                        <i class="fas fa-circle permission-indicator"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="modal-footer">
            <div class="footer-stats">
                <span class="change-indicator" id="changeIndicator">
                    <i class="fas fa-info-circle"></i>
                    Değişiklik yapmadınız
                </span>
            </div>
            
            <div class="footer-actions">
                <button type="button" class="btn btn-secondary" onclick="closePermissionsModal()">İptal</button>
                <button type="button" class="btn btn-primary" onclick="savePermissions()">
                    <i class="fas fa-save"></i>
                    Yetkileri Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <span>İşleminiz gerçekleştiriliyor...</span>
    </div>
</div>

<?php 
require_once BASE_PATH . '/src/includes/admin_quick_menu.php';
require_once BASE_PATH . '/src/includes/footer.php'; 
?>

<!-- JavaScript dosyalarını yükle -->
<script src="/admin/manage_roles/js/manage_roles.js"></script>
<script>
// Sayfa yüklendikten sonra JavaScript'i başlat
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ManageRoles !== 'undefined') {
        ManageRoles.init();
    }
});
</script>