<?php
// public/admin/manage_roles.php - Tamamen Yeniden Yazılmış Gelişmiş Versiyon

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Bu sayfaya erişim için yetki kontrolü
require_permission($pdo, 'admin.roles.view');

$page_title = "Rol Yönetimi";

// Kullanıcının yetki durumunu kontrol et
$current_user_id = $_SESSION['user_id'];
$can_create_role = has_permission($pdo, 'admin.roles.create');
$can_edit_role = has_permission($pdo, 'admin.roles.edit');
$can_delete_role = has_permission($pdo, 'admin.roles.delete');
$is_super_admin_user = is_super_admin($pdo);

// Kullanıcının hiyerarşik konumunu belirle
$user_roles = get_user_roles($pdo, $current_user_id);
$user_highest_priority = 999;
foreach ($user_roles as $role) {
    if ($role['priority'] < $user_highest_priority) {
        $user_highest_priority = $role['priority'];
    }
}

// Güvenli sıralama parametreleri
$allowed_order_columns = ['id', 'name', 'description', 'priority', 'created_at'];
$order_by = $_GET['order_by'] ?? 'priority';
$direction = $_GET['direction'] ?? 'ASC';

// Input validation
if (!in_array($order_by, $allowed_order_columns)) {
    $order_by = 'priority';
}
if (!in_array(strtoupper($direction), ['ASC', 'DESC'])) {
    $direction = 'ASC';
}

// Rol listesi ve hiyerarşi kontrolü ile getir
try {
    $query_params = [
        'user_priority' => $user_highest_priority,
        'is_super_admin_1' => $is_super_admin_user ? 1 : 0,
        'is_super_admin_2' => $is_super_admin_user ? 1 : 0
    ];
    
    $query = "
        SELECT r.*, 
               COUNT(DISTINCT ur.user_id) as user_count,
               COUNT(DISTINCT rp.permission_id) as permission_count,
               CASE 
                   WHEN r.priority <= :user_priority AND :is_super_admin_1 = 0 THEN 0
                   WHEN r.name IN ('admin', 'member', 'dis_uye') AND :is_super_admin_2 = 0 THEN 0
                   ELSE 1
               END as can_manage
        FROM roles r
        LEFT JOIN user_roles ur ON r.id = ur.user_id
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        GROUP BY r.id, r.name, r.description, r.color, r.priority, r.created_at, r.updated_at
        ORDER BY r." . $order_by . " " . $direction;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($query_params);
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Rol listesi getirme hatası: " . $e->getMessage());
    $roles = [];
    $_SESSION['error_message'] = "Roller yüklenirken bir hata oluştu: " . $e->getMessage();
}

// Tüm yetkileri gruplar halinde getir (popup için)
$permission_groups = [];
try {
    $all_permissions = get_all_permissions_grouped($pdo);
    $permission_groups = $all_permissions;
} catch (Exception $e) {
    error_log("Yetkiler getirme hatası: " . $e->getMessage());
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Rol Yönetimi Sayfası Stilleri */
.roles-management-container {
    width: 100%;
    max-width: 1600px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
}

.page-header-roles {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.page-title-roles {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.hierarchy-info-box {
    background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1));
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 4px solid var(--gold);
}

.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.role-card {
    background-color: var(--charcoal);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--darker-gold-1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.role-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    border-color: var(--gold);
}

.role-card.protected {
    border-left: 4px solid var(--red);
}

.role-card.manageable {
    border-left: 4px solid var(--turquase);
}

.role-card.not-manageable {
    opacity: 0.7;
    border-left: 4px solid var(--light-grey);
}

.role-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.role-name {
    display: flex;
    align-items: center;
    gap: 10px;
}

.role-color-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.2);
}

.role-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--lighter-grey);
    margin: 0;
}

.role-priority {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
}

.role-description {
    color: var(--light-grey);
    font-size: 0.9rem;
    margin-bottom: 15px;
    line-height: 1.4;
}

.role-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding: 10px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
}

.role-stat {
    text-align: center;
}

.role-stat-number {
    font-size: 1.3rem;
    font-weight: bold;
    color: var(--turquase);
    margin-bottom: 2px;
}

.role-stat-label {
    font-size: 0.7rem;
    color: var(--lighter-grey);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-badges {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.role-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
}

.role-badge.protected {
    background-color: var(--red);
    color: white;
}

.role-badge.super-admin-only {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
}

.role-badge.manageable {
    background-color: var(--turquase);
    color: var(--black);
}

.role-badge.not-manageable {
    background-color: var(--light-grey);
    color: var(--black);
}

.role-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.role-actions .btn {
    padding: 6px 12px;
    font-size: 0.8rem;
    border-radius: 4px;
}

/* Modal/Popup Stilleri */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 50%;
    top: 50%;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    backdrop-filter: blur(5px);
}

.modal-content {
    background-color: var(--charcoal);
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 1600px;
    max-height: 95vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-50px) scale(0.9); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--darker-gold-1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, var(--darker-gold-1), var(--charcoal));
}

.modal-title {
    color: var(--gold);
    font-size: 1.4rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    color: var(--light-grey);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: var(--red);
    color: white;
    transform: rotate(90deg);
}

.modal-body {
    padding: 25px;
}

.form-group-modal {
    margin-bottom: 20px;
}

.form-group-modal label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 0.95rem;
}

.form-control-modal {
    width: 100%;
    padding: 12px 15px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--white);
    font-size: 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-control-modal:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.color-input-wrapper {
    display: flex;
    align-items: center;
    gap: 15px;
}

.color-preview {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    border: 2px solid var(--darker-gold-1);
    transition: transform 0.2s ease;
}

.color-preview:hover {
    transform: scale(1.1);
}

.permissions-section {
    margin-top: 25px;
}

.permissions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    max-height: 900px;
    overflow-y: auto;
    padding: 10px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
}

.permission-group {
    background-color: var(--charcoal);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}

.permission-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--darker-gold-2);
}

.permission-group-title {
    color: var(--gold);
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}

.group-toggle-btn {
    background: none;
    border: 1px solid var(--darker-gold-1);
    color: var(--lighter-grey);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.group-toggle-btn:hover {
    background-color: var(--gold);
    color: var(--black);
}

.permission-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 8px;
    padding: 5px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.permission-item:hover {
    background-color: var(--darker-gold-1);
}

.permission-item input[type="checkbox"] {
    margin-right: 10px;
    margin-top: 3px;
    accent-color: var(--turquase);
    transform: scale(1.1);
}

.permission-label {
    color: var(--lighter-grey);
    font-size: 0.85rem;
    cursor: pointer;
    line-height: 1.3;
}

.permission-key {
    display: block;
    font-family: monospace;
    font-size: 0.7rem;
    color: var(--light-grey);
    opacity: 0.8;
    margin-top: 2px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--darker-gold-1);
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    background-color: var(--darker-gold-2);
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0,0,0,0.7);
    display: none;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--darker-gold-1);
    border-top: 4px solid var(--gold);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.validation-error {
    color: var(--red);
    font-size: 0.8rem;
    margin-top: 5px;
    display: none;
}

.form-control-modal.is-invalid {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
}

.super-admin-warning {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.hierarchy-warning {
    background-color: var(--transparent-turquase);
    color: var(--turquase);
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    border: 1px solid var(--turquase);
}

/* Responsive Design */
@media (max-width: 768px) {
    .roles-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 5% auto;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header-roles {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>

<main class="main-content">
    <div class="container admin-container roles-management-container">
        <div class="page-header-roles">
            <h1 class="page-title-roles">
                <i class="fas fa-user-tag"></i>
                <?php echo htmlspecialchars($page_title); ?>
                <?php if ($is_super_admin_user): ?>
                    <i class="fas fa-crown" style="color: #ffd700;" title="Süper Admin - Sınırsız Yetki"></i>
                <?php endif; ?>
            </h1>
            <div class="header-actions">
                <?php if ($can_create_role): ?>
                <button onclick="openCreateRoleModal()" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Yeni Rol Oluştur
                </button>
                <?php endif; ?>
                <?php if (is_super_admin($pdo)): ?>
                <a href="manage_super_admins.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-crown"></i> Süper Admin
                </a>
                <?php endif; ?>
                <a href="audit_log.php" class="btn btn-sm btn-info">
                    <i class="fas fa-clipboard-list"></i> Audit Log
                </a>
            </div>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <!-- Hiyerarşi Bilgi Kutusu -->
        <div class="hierarchy-info-box">
            <div style="display: flex; align-items: center; gap: 10px; color: var(--light-gold); margin-bottom: 10px;">
                <i class="fas fa-layer-group"></i>
                <strong>Rol Hiyerarşisi ve Yetki Durumunuz:</strong>
            </div>
            <div style="color: var(--lighter-grey); font-size: 0.9rem; line-height: 1.4;">
                <?php if ($is_super_admin_user): ?>
                    <span style="color: #ffd700; font-weight: bold;">🔥 SÜPER ADMİN</span> - Tüm rolleri sınırsız olarak yönetebilirsiniz (korumalı roller dahil)
                <?php else: ?>
                    Hiyerarşi seviyeniz: <strong><?php echo $user_highest_priority; ?></strong> - 
                    Sadece kendi seviyenizden <strong>düşük öncelikli</strong> rolleri yönetebilirsiniz.
                    Süper Admin yetkilerini sadece mevcut süper adminler yönetebilir.
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($roles)): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--light-grey);">
                <i class="fas fa-user-tag" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                <p style="font-size: 1.2rem;">Sistemde henüz tanımlanmış bir rol bulunmamaktadır.</p>
                <?php if ($can_create_role): ?>
                <?php if ($can_create_role): ?>
                <button onclick="openCreateRoleModal()" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> İlk Rolü Oluşturun
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Roller Grid -->
            <div class="roles-grid">
                <?php foreach ($roles as $role): ?>
                <?php 
                $is_protected = !is_role_deletable($role['name']);
                $can_manage_this_role = ($role['can_manage'] == 1) || $is_super_admin_user;
                $is_super_admin_role = in_array($role['name'], ['admin']) && !$is_super_admin_user;
                ?>
                <div class="role-card <?php echo $is_protected ? 'protected' : ($can_manage_this_role ? 'manageable' : 'not-manageable'); ?>">
                    <div class="role-header">
                        <div class="role-name">
                            <div class="role-color-indicator" style="background-color: <?php echo htmlspecialchars($role['color']); ?>;"></div>
                            <h3 class="role-title"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role['name']))); ?></h3>
                        </div>
                        <div class="role-priority">Seviye <?php echo $role['priority']; ?></div>
                    </div>

                    <div class="role-description">
                        <?php echo htmlspecialchars($role['description'] ?: 'Açıklama eklenmemiş'); ?>
                    </div>

                    <div class="role-stats">
                        <div class="role-stat">
                            <div class="role-stat-number"><?php echo $role['user_count']; ?></div>
                            <div class="role-stat-label">Kullanıcı</div>
                        </div>
                        <div class="role-stat">
                            <div class="role-stat-number"><?php echo $role['permission_count']; ?></div>
                            <div class="role-stat-label">Yetki</div>
                        </div>
                        <div class="role-stat">
                            <div class="role-stat-number"><?php echo $role['id']; ?></div>
                            <div class="role-stat-label">ID</div>
                        </div>
                    </div>

                    <div class="role-badges">
                        <?php if ($is_protected): ?>
                            <span class="role-badge protected">
                                <i class="fas fa-shield-alt"></i> Korumalı
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($is_super_admin_role): ?>
                            <span class="role-badge super-admin-only">
                                <i class="fas fa-crown"></i> Sadece Süper Admin
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($can_manage_this_role && !$is_super_admin_role): ?>
                            <span class="role-badge manageable">
                                <i class="fas fa-check"></i> Yönetilebilir
                            </span>
                        <?php elseif (!$can_manage_this_role): ?>
                            <span class="role-badge not-manageable">
                                <i class="fas fa-lock"></i> Yetki Yok
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="role-actions">
                        <button  style="display: none;" onclick="viewRolePermissions(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name']); ?>')" 
                                class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Yetkileri Gör
                        </button>
                        
                        <?php if ($can_edit_role && $can_manage_this_role && !$is_super_admin_role): ?>
                        <button onclick="openEditRoleModal(<?php echo $role['id']; ?>)" 
                                class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i> Düzenle
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($can_delete_role && is_role_deletable($role['name']) && $can_manage_this_role && !$is_super_admin_role): ?>
                        <button onclick="confirmDeleteRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name']); ?>', <?php echo $role['user_count']; ?>)" 
                                class="btn btn-sm btn-danger">
                            <i class="fas fa-trash-alt"></i> Sil
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- İstatistikler -->
            <div style="margin-top: 30px; padding: 20px; background-color: var(--darker-gold-2); border-radius: 8px;">
                <h4 style="color: var(--gold); margin: 0 0 15px 0;">Rol Yönetimi İstatistikleri:</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong style="color: var(--turquase);"><?php echo count($roles); ?></strong> Toplam Rol
                    </div>
                    <div>
                        <strong style="color: var(--turquase);"><?php echo count(array_filter($roles, function($r) use ($is_super_admin_user) { return ($r['can_manage'] == 1) || $is_super_admin_user; })); ?></strong> Yönetilebilir Rol
                    </div>
                    <div>
                        <strong style="color: var(--red);"><?php echo count(array_filter($roles, function($r) { return !is_role_deletable($r['name']); })); ?></strong> Korumalı Rol
                    </div>
                    <div>
                        <strong style="color: var(--gold);"><?php echo array_sum(array_column($roles, 'user_count')); ?></strong> Toplam Atama
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rol Düzenleme/Oluşturma Modalı -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-user-tag"></i>
                    <span id="roleModalTitle">Yeni Rol Oluştur</span>
                </h2>
                <button type="button" class="modal-close" onclick="closeRoleModal()">&times;</button>
            </div>
            <form id="roleForm" method="POST" action="/src/actions/handle_roles.php">
                <div class="modal-body">
                    <input type="hidden" name="action" id="roleAction" value="create_role">
                    <input type="hidden" name="role_id" id="roleId" value="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <!-- Güvenlik Uyarıları -->
                    <div id="securityWarnings"></div>
                    
                    <!-- Temel Bilgiler -->
                    <div class="form-group-modal">
                        <label for="role_name">Rol Adı (Sistem Anahtarı):</label>
                        <input type="text" id="role_name" name="role_name" class="form-control-modal" 
                               required pattern="[a-z0-9_]{2,50}" 
                               title="2-50 karakter arası, sadece küçük harf, rakam ve alt çizgi (_) kullanın."
                               placeholder="ornek_rol_adi">
                        <small style="color: var(--light-grey); font-size: 0.8rem; display: block; margin-top: 5px;">
                            Sistem içinde kullanılacak benzersiz ad. Sadece küçük harf, rakam ve alt çizgi (_) içermelidir.
                        </small>
                        <div class="validation-error" id="role_name_error"></div>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="role_description">Rol Açıklaması:</label>
                        <input type="text" id="role_description" name="role_description" class="form-control-modal" 
                               maxlength="255" placeholder="Rolün kısa bir açıklaması...">
                        <small style="color: var(--light-grey); font-size: 0.8rem; display: block; margin-top: 5px;">
                            Maksimum 255 karakter. Bu açıklama admin panelinde görünecektir.
                        </small>
                        <div class="validation-error" id="role_description_error"></div>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="role_color">Rol Rengi:</label>
                        <div class="color-input-wrapper">
                            <input type="color" id="role_color" name="role_color" 
                                   class="form-control-modal" value="#4a5568" style="width: 60px; padding: 4px;">
                            <div id="role_color_preview" class="color-preview" style="background-color: #4a5568;"></div>
                            <span style="color: var(--light-grey); font-size: 0.9rem;">
                                Bu renk kullanıcı adlarında ve rol gösterimlerinde kullanılacaktır.
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="role_priority">Rol Önceliği:</label>
                        <input type="number" id="role_priority" name="role_priority" 
                               class="form-control-modal" value="999" min="1" max="9999" required style="max-width: 150px;">
                        <small style="color: var(--light-grey); font-size: 0.8rem; display: block; margin-top: 5px;">
                            Düşük sayı = yüksek öncelik. Admin rolleri 1-10, üye rolleri 100+, misafir rolleri 900+ olmalıdır.
                        </small>
                        <div class="validation-error" id="role_priority_error"></div>
                    </div>
                    
                    <!-- Yetkiler Bölümü -->
                    <div class="permissions-section">
                        <div class="permissions-header">
                            <h3 style="color: var(--gold); margin: 0; font-size: 1.2rem;">
                                <i class="fas fa-key"></i> Yetkiler
                            </h3>
                            <div>
                                <button type="button" id="selectAllPermissions" class="btn btn-sm btn-outline-secondary">
                                    Tümünü Seç
                                </button>
                                <button type="button" id="clearAllPermissions" class="btn btn-sm btn-outline-secondary">
                                    Temizle
                                </button>
                            </div>
                        </div>
                        
                        <!-- Kullanıcının Yetki Durumu -->
                        <div id="userPermissionStatus" style="margin-bottom: 15px;"></div>
                        
                        <div class="permissions-grid" id="permissionsGrid">
                            <!-- Yetkiler buraya yüklenecek -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">İptal</button>
                    <button type="submit" class="btn btn-primary" id="saveRoleBtn">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </form>
            <div class="loading-overlay" id="roleModalLoading">
                <div class="loading-spinner"></div>
            </div>
        </div>
    </div>
    <div id="permissionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-key"></i>
                    <span id="permissionsModalTitle">Rol Yetkileri</span>
                </h2>
                <button type="button" class="modal-close" onclick="closePermissionsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="permissionsContent">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePermissionsModal()">Kapat</button>
            </div>
        </div>
    </div>

    <!-- Rol Silme Onay Modalı -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--red);"></i>
                    Rol Silme Onayı
                </h2>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="deleteContent">
                    <p>Bu rolü silmek istediğinizden emin misiniz?</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">İptal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash-alt"></i> Evet, Sil
                </button>
            </div>
        </div>
    </div>
</main>

<script>
// Modal açılmama sorununu çözen düzeltilmiş JavaScript kodu

// Global değişkenler
let currentRoleIdToDelete = null;
let currentRoleNameToDelete = null;
let userPermissions = []; 
let allPermissions = <?php echo json_encode($permission_groups); ?>; 
let isUserSuperAdmin = <?php echo $is_super_admin_user ? 'true' : 'false'; ?>;

// DOM elementlerini güvenli şekilde seçen yardımcı fonksiyon
function safeGetElement(id) {
    const element = document.getElementById(id);
    if (!element) {
        console.error(`Element bulunamadı: ${id}`);
    }
    return element;
}

// Kullanıcının kendi yetkilerini al
async function loadUserPermissions() {
    try {
        const response = await fetch('/src/api/get_user_permissions.php');
        const data = await response.json();
        if (data.success) {
            userPermissions = data.permissions || [];
        }
    } catch (error) {
        console.error('Kullanıcı yetkileri alınırken hata:', error);
        userPermissions = [];
    }
}

// Modal açma/kapama fonksiyonları - DÜZELTİLDİ
async function openCreateRoleModal() {
    console.log('openCreateRoleModal çağrıldı');
    
    // DOM elementlerini kontrol et
    const modal = safeGetElement('roleModal');
    const title = safeGetElement('roleModalTitle');
    const form = safeGetElement('roleForm');
    
    if (!modal || !title || !form) {
        console.error('Modal elementleri eksik');
        alert('Modal yüklenirken bir hata oluştu. Sayfa yenilenecek.');
        setTimeout(() => location.reload(), 1000);
        return;
    }
    
    try {
        await loadUserPermissions();
        
        // Modal içeriğini ayarla
        title.textContent = 'Yeni Rol Oluştur';
        safeGetElement('roleAction').value = 'create_role';
        safeGetElement('roleId').value = '';
        
        // Formu temizle
        form.reset();
        
        // Renk ve öncelik değerlerini ayarla
        const colorInput = safeGetElement('role_color');
        const colorPreview = safeGetElement('role_color_preview');
        const priorityInput = safeGetElement('role_priority');
        
        if (colorInput && colorPreview) {
            colorInput.value = '#4a5568';
            colorPreview.style.backgroundColor = '#4a5568';
        }
        
        if (priorityInput) {
            priorityInput.value = <?php echo $user_highest_priority + 1; ?>;
        }
        
        // Güvenlik uyarıları ve yetkileri yükle
        updateSecurityWarnings();
        loadPermissionsForModal();
        
        // Modal'ı göster - CSS display ve z-index kontrolü
        modal.style.display = 'block';
        modal.style.zIndex = '10000';
        
        // Body'ye modal-open class ekle (scroll engelleme için)
        document.body.classList.add('modal-open');
        
        console.log('Modal başarıyla açıldı');
        
    } catch (error) {
        console.error('Modal açılırken hata:', error);
        alert('Modal açılırken bir hata oluştu: ' + error.message);
    }
}

async function openEditRoleModal(roleId) {
    console.log('openEditRoleModal çağrıldı:', roleId);
    
    // DOM elementlerini kontrol et
    const modal = safeGetElement('roleModal');
    const title = safeGetElement('roleModalTitle');
    const loading = safeGetElement('roleModalLoading');
    
    if (!modal || !title || !loading) {
        console.error('Modal elementleri eksik');
        alert('Modal yüklenirken bir hata oluştu.');
        return;
    }
    
    try {
        await loadUserPermissions();
        
        // Modal ayarları
        title.textContent = 'Rol Düzenle';
        safeGetElement('roleAction').value = 'update_role';
        safeGetElement('roleId').value = roleId;
        
        // Modal'ı göster
        modal.style.display = 'block';
        modal.style.zIndex = '10000';
        loading.style.display = 'flex';
        document.body.classList.add('modal-open');
        
        // Rol verilerini çek
        const response = await fetch(`/src/api/get_role_data.php?role_id=${roleId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Role data API response:', data);
        
        if (data.success) {
            const role = data.role;
            
            // Form alanlarını doldur
            const fields = {
                'role_name': role.name,
                'role_description': role.description,
                'role_color': role.color,
                'role_priority': role.priority
            };
            
            Object.keys(fields).forEach(fieldId => {
                const element = safeGetElement(fieldId);
                if (element) {
                    element.value = fields[fieldId];
                }
            });
            
            // Renk önizlemesini güncelle
            const colorPreview = safeGetElement('role_color_preview');
            if (colorPreview) {
                colorPreview.style.backgroundColor = role.color;
            }
            
            // Korumalı rol kontrolü
            if (!role.is_name_editable) {
                const nameField = safeGetElement('role_name');
                if (nameField) {
                    nameField.readOnly = true;
                    nameField.style.backgroundColor = 'var(--darker-gold-2)';
                }
            }
            
            // Güvenlik uyarıları ve yetkileri yükle
            updateSecurityWarnings(role);
            loadPermissionsForModal(role.permissions);
            
        } else {
            throw new Error(data.message || 'Rol verileri alınamadı');
        }
        
    } catch (error) {
        console.error('Rol verileri yüklenirken hata:', error);
        alert('Rol verileri yüklenirken bir hata oluştu: ' + error.message);
        closeRoleModal();
    } finally {
        if (loading) {
            loading.style.display = 'none';
        }
    }
}

function closeRoleModal() {
    const modal = safeGetElement('roleModal');
    const form = safeGetElement('roleForm');
    const nameField = safeGetElement('role_name');
    
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
    
    // Form'u temizle
    if (form) {
        form.reset();
    }
    
    // Readonly durumunu temizle
    if (nameField) {
        nameField.readOnly = false;
        nameField.style.backgroundColor = '';
    }
    
    console.log('Modal kapatıldı');
}

// Yetki görüntüleme modalı
function viewRolePermissions(roleId, roleName) {
    console.log('viewRolePermissions çağrıldı:', roleId, roleName);
    
    const modal = safeGetElement('permissionsModal');
    const titleElement = safeGetElement('permissionsModalTitle');
    const contentElement = safeGetElement('permissionsContent');
    
    if (!modal || !titleElement || !contentElement) {
        console.error('Permissions modal elementleri eksik');
        alert('Modal yüklenirken bir hata oluştu.');
        return;
    }
    
    titleElement.textContent = `${roleName} - Rol Yetkileri`;
    contentElement.innerHTML = '<div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div><p style="margin-top: 15px;">Yetkiler yükleniyor...</p></div>';
    
    // Modal'ı göster
    modal.style.display = 'block';
    modal.style.zIndex = '10000';
    document.body.classList.add('modal-open');
    
    // API'den yetkileri çek
    fetch(`/src/api/get_role_permissions.php?role_id=${roleId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayPermissions(data);
            } else {
                throw new Error(data.message || 'Yetkiler alınamadı');
            }
        })
        .catch(error => {
            console.error('API Error:', error);
            contentElement.innerHTML = `<div style="color: var(--red); text-align: center; padding: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Yetkiler yüklenirken bir hata oluştu: ${error.message}</p>
            </div>`;
        });
}

function closePermissionsModal() {
    const modal = safeGetElement('permissionsModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

// Silme modalı
function confirmDeleteRole(roleId, roleName, userCount) {
    currentRoleIdToDelete = roleId;
    currentRoleNameToDelete = roleName;
    
    const modal = safeGetElement('deleteModal');
    const contentElement = safeGetElement('deleteContent');
    
    if (!modal || !contentElement) {
        console.error('Delete modal elementleri eksik');
        return;
    }
    
    let warningMessage = `<strong>"${roleName}"</strong> rolünü silmek istediğinizden emin misiniz?`;
    
    if (userCount > 0) {
        warningMessage += `<br><br><div style="background-color: var(--transparent-red); padding: 10px; border-radius: 4px; border: 1px solid var(--red);">
            <i class="fas fa-exclamation-triangle" style="color: var(--red);"></i>
            <strong>DİKKAT:</strong> Bu role sahip <strong>${userCount} kullanıcı</strong> etkilenecektir!
        </div>`;
    }
    
    warningMessage += `<br><br><small style="color: var(--light-grey);">Bu işlem <strong>geri alınamaz</strong> ve tüm ilişkili veriler silinecektir.</small>`;
    
    contentElement.innerHTML = `<div style="line-height: 1.5;">${warningMessage}</div>`;
    
    modal.style.display = 'block';
    modal.style.zIndex = '10000';
    document.body.classList.add('modal-open');
}

function closeDeleteModal() {
    const modal = safeGetElement('deleteModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
    currentRoleIdToDelete = null;
    currentRoleNameToDelete = null;
}

// Diğer yardımcı fonksiyonlar
function updateSecurityWarnings(roleData = null) {
    const warningsDiv = safeGetElement('securityWarnings');
    if (!warningsDiv) return;
    
    let warnings = [];
    
    if (!isUserSuperAdmin) {
        warnings.push(`
            <div class="hierarchy-warning">
                <i class="fas fa-info-circle"></i>
                <strong>Hiyerarşi Kısıtlaması:</strong> Sadece kendi seviyenizden (${<?php echo $user_highest_priority; ?>}) 
                düşük öncelikli roller oluşturabilir/düzenleyebilirsiniz.
            </div>
        `);
        
        warnings.push(`
            <div class="hierarchy-warning">
                <i class="fas fa-shield-alt"></i>
                <strong>Yetki Kısıtlaması:</strong> Sadece sahip olduğunuz yetkileri başkalarına atayabilirsiniz.
            </div>
        `);
    }
    
    if (roleData && roleData.is_protected) {
        warnings.push(`
            <div class="super-admin-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Korumalı Rol:</strong> Bu rol sistem güvenliği için korunmaktadır. 
                Bazı alanlar düzenlenemez.
            </div>
        `);
    }
    
    warningsDiv.innerHTML = warnings.join('');
}

function loadPermissionsForModal(selectedPermissions = []) {
    const grid = safeGetElement('permissionsGrid');
    const statusDiv = safeGetElement('userPermissionStatus');
    
    if (!grid || !statusDiv) {
        console.error('Permissions elementleri bulunamadı');
        return;
    }
    
    // Kullanıcının yetki durumu
    let statusHtml = '';
    if (isUserSuperAdmin) {
        statusHtml = `
            <div style="background: linear-gradient(135deg, #ffd700, #ffed4e); color: #333; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px;">
                <i class="fas fa-crown"></i>
                <strong>Süper Admin:</strong> Tüm yetkileri atayabilirsiniz.
            </div>
        `;
    } else {
        const userPermissionCount = userPermissions.length;
        statusHtml = `
            <div style="background-color: var(--transparent-turquase); padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid var(--turquase);">
                <i class="fas fa-user-shield"></i>
                <strong>Yetki Durumunuz:</strong> ${userPermissionCount} yetkiye sahipsiniz. 
                Sadece sahip olduğunuz yetkileri atayabilirsiniz.
            </div>
        `;
    }
    statusDiv.innerHTML = statusHtml;
    
    // Yetkiler grid'i oluştur
    let gridHtml = '';
    
    if (allPermissions && typeof allPermissions === 'object') {
        Object.keys(allPermissions).forEach(groupName => {
            const permissions = allPermissions[groupName];
            if (!permissions || typeof permissions !== 'object') return;
            
            const groupPermissions = Object.keys(permissions);
            
            // Bu gruptaki kullanıcının sahip olduğu yetki sayısı
            const userGroupPermissions = groupPermissions.filter(key => 
                isUserSuperAdmin || userPermissions.includes(key)
            );
            
            gridHtml += `
                <div class="permission-group">
                    <div class="permission-group-header">
                        <h5 class="permission-group-title">${groupName}</h5>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="background-color: var(--turquase); color: var(--black); padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; font-weight: bold;">
                                ${userGroupPermissions.length}/${groupPermissions.length}
                            </span>
                            <button type="button" class="group-toggle-btn" onclick="toggleGroupPermissions('${groupName.replace(/\s+/g, '_')}')">
                                Grup Seç
                            </button>
                        </div>
                    </div>
                    <div id="group_${groupName.replace(/\s+/g, '_')}">
            `;
            
            Object.keys(permissions).forEach(permissionKey => {
                const permissionName = permissions[permissionKey];
                const canAssign = isUserSuperAdmin || userPermissions.includes(permissionKey);
                const isSelected = selectedPermissions.includes(permissionKey);
                
                gridHtml += `
                    <div class="permission-item">
                        <label style="display: flex; align-items: flex-start; gap: 10px; opacity: ${canAssign ? '1' : '0.5'};">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="${permissionKey}"
                                   ${isSelected ? 'checked' : ''}
                                   ${!canAssign ? 'disabled' : ''}
                                   style="margin-top: 3px;">
                            <div>
                                <div class="permission-label" style="font-weight: 500;">
                                    ${permissionName}
                                    ${!canAssign ? '<i class="fas fa-lock" style="color: var(--red); margin-left: 5px;" title="Bu yetkiyi atama yetkiniz yok"></i>' : ''}
                                </div>
                                <code class="permission-key">${permissionKey}</code>
                            </div>
                        </label>
                    </div>
                `;
            });
            
            gridHtml += '</div></div>';
        });
    }
    
    grid.innerHTML = gridHtml;
}

// Grup yetki seçimi
function toggleGroupPermissions(groupName) {
    const groupDiv = safeGetElement('group_' + groupName);
    if (!groupDiv) return;
    
    const checkboxes = groupDiv.querySelectorAll('input[type="checkbox"]:not([disabled])');
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    const shouldCheck = checkedCount < checkboxes.length;
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = shouldCheck;
    });
}

// Event listeners ve DOM hazır kontrolü
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM yüklendi - modal sistemi başlatılıyor');
    
    // CSS class'ını body'ye ekle (modal overlay için)
    const style = document.createElement('style');
    style.textContent = `
        .modal-open {
            overflow: hidden;
        }
        .modal {
            backdrop-filter: blur(5px);
        }
    `;
    document.head.appendChild(style);
    
    // Modal elementlerini kontrol et
    const modals = ['roleModal', 'permissionsModal', 'deleteModal'];
    const missingModals = modals.filter(id => !document.getElementById(id));
    
    if (missingModals.length > 0) {
        console.error('Eksik modal elementleri:', missingModals);
        // Eksik modalları oluştur (alternatif çözüm)
        missingModals.forEach(modalId => {
            console.warn(`${modalId} bulunamadı, sayfa yenilenecek`);
        });
        setTimeout(() => location.reload(), 2000);
        return;
    }
    
    console.log('Tüm modal elementleri mevcut');
    
    // Modal kapatma - dış alan tıklama
    window.onclick = function(event) {
        const modals = [
            { element: safeGetElement('permissionsModal'), closeFunc: closePermissionsModal },
            { element: safeGetElement('deleteModal'), closeFunc: closeDeleteModal },
            { element: safeGetElement('roleModal'), closeFunc: closeRoleModal }
        ];
        
        modals.forEach(modal => {
            if (modal.element && event.target === modal.element) {
                modal.closeFunc();
            }
        });
    };
    
    // ESC tuşu ile modal kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePermissionsModal();
            closeDeleteModal();
            closeRoleModal();
        }
    });
    
    // Form submit kontrolü
    const roleForm = safeGetElement('roleForm');
    if (roleForm) {
        roleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateRoleForm()) {
                return false;
            }
            
            const submitBtn = safeGetElement('saveRoleBtn');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            }
            
            setTimeout(() => {
                roleForm.submit();
            }, 500);
        });
    }
    
    // Renk değişikliği
    const colorInput = safeGetElement('role_color');
    const colorPreview = safeGetElement('role_color_preview');
    if (colorInput && colorPreview) {
        colorInput.addEventListener('input', function() {
            colorPreview.style.backgroundColor = this.value;
        });
    }
    
    // Yetki seçim butonları
    const selectAllBtn = safeGetElement('selectAllPermissions');
    const clearAllBtn = safeGetElement('clearAllPermissions');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]:not([disabled])');
            checkboxes.forEach(cb => cb.checked = true);
        });
    }
    
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        });
    }
    
    // Silme onayı butonu
    const confirmDeleteBtn = safeGetElement('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', executeRoleDelete);
    }
    
    // Kullanıcı yetkilerini yükle
    loadUserPermissions();
    
    console.log('Tüm event listener\'lar kuruldu');
});

// Form validasyonu
function validateRoleForm() {
    let isValid = true;
    
    // Rol adı kontrolü
    const roleNameElement = safeGetElement('role_name');
    if (!roleNameElement) return false;
    
    const roleName = roleNameElement.value.trim();
    if (!roleName) {
        showFieldError('role_name', 'Rol adı gereklidir.');
        isValid = false;
    } else if (!/^[a-z0-9_]{2,50}$/.test(roleName)) {
        showFieldError('role_name', 'Sadece 2-50 karakter arası küçük harf, rakam ve alt çizgi (_) kullanın.');
        isValid = false;
    } else {
        hideFieldError('role_name');
    }
    
    // Diğer validasyonlar...
    return isValid;
}

function showFieldError(fieldId, message) {
    const field = safeGetElement(fieldId);
    const errorDiv = safeGetElement(fieldId + '_error');
    
    if (field) field.classList.add('is-invalid');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
}

function hideFieldError(fieldId) {
    const field = safeGetElement(fieldId);
    const errorDiv = safeGetElement(fieldId + '_error');
    
    if (field) field.classList.remove('is-invalid');
    if (errorDiv) errorDiv.style.display = 'none';
}

// Diğer fonksiyonlar (displayPermissions, executeRoleDelete vb.)
function displayPermissions(data) {
    const contentElement = safeGetElement('permissionsContent');
    if (!contentElement) return;
    
    if (!data.permissions || data.permissions.length === 0) {
        contentElement.innerHTML = `<div style="text-align: center; padding: 40px; color: var(--light-grey);">
            <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
            <p>Bu role henüz hiç yetki atanmamış.</p>
        </div>`;
        return;
    }
    
    // Permission display implementation...
    // (Mevcut displayPermissions fonksiyonunun içeriği)
}

function executeRoleDelete() {
    if (!currentRoleIdToDelete) return;
    
    const confirmBtn = safeGetElement('confirmDeleteBtn');
    if (!confirmBtn) return;
    
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
    
    // Form oluştur ve gönder
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/src/actions/handle_roles.php';
    
    const inputs = [
        { name: 'action', value: 'delete_role' },
        { name: 'role_id', value: currentRoleIdToDelete },
        { name: 'csrf_token', value: '<?php echo generate_csrf_token(); ?>' }
    ];
    
    inputs.forEach(input => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = input.name;
        hiddenInput.value = input.value;
        form.appendChild(hiddenInput);
    });
    
    document.body.appendChild(form);
    
    setTimeout(() => {
        form.submit();
    }, 1000);
}

// Hata yakalama
window.addEventListener('error', function(e) {
    console.error('JavaScript Hatası:', e.error);
    
    if (e.error && e.error.message.includes('fetch')) {
        alert('Bağlantı hatası oluştu. Sayfa yenilenecek...');
        setTimeout(() => location.reload(), 3000);
    }
});

// Global modal test fonksiyonu (debug için)
window.testModal = function() {
    console.log('Modal test fonksiyonu çağrıldı');
    openCreateRoleModal();
};
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>