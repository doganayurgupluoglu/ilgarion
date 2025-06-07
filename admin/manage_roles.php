<?php
// public/admin/manage_roles.php - Güncellenmiş Rol Yönetimi

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Oturum kontrolü
if (is_user_logged_in()) {
    check_user_session_validity();
}

// Rol yönetimi yetkisi kontrolü
require_permission($pdo, 'admin.roles.view');

$page_title = "Rol Yönetimi";
$current_user_id = $_SESSION['user_id'];
$is_super_admin_user = is_super_admin($pdo);

// Kullanıcının hiyerarşik konumunu belirle
$user_roles = get_user_roles($pdo, $current_user_id);
$user_highest_priority = 999;
foreach ($user_roles as $role) {
    if ($role['priority'] < $user_highest_priority) {
        $user_highest_priority = $role['priority'];
    }
}

// Hiyerarşi verilerini al
$hierarchy_data = get_role_hierarchy_data($pdo, $current_user_id);
$roles = $hierarchy_data['roles'];
$user_info = $hierarchy_data['user_info'];

// Tüm yetkileri gruplar halinde al
$grouped_permissions = get_all_permissions_grouped($pdo);

// Kullanıcının sahip olduğu yetkileri al (rol oluşturma/düzenleme için)
$user_permissions = [];
if ($is_super_admin_user) {
    $stmt = $pdo->query("SELECT permission_key FROM permissions WHERE is_active = 1");
    $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $query = "
        SELECT DISTINCT p.permission_key
        FROM user_roles ur 
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = :user_id AND p.is_active = 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $current_user_id]);
    $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Form input'ları session'dan al (hata durumunda)
$form_input = $_SESSION['form_input_role_edit'] ?? [];
unset($_SESSION['form_input_role_edit']);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.role-management-container {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.page-header h1 {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btn-create-role {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--charcoal);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-create-role:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(189, 145, 42, 0.3);
    color: var(--charcoal);
}

.btn-create-role.disabled {
    background: var(--grey);
    color: var(--light-grey);
    cursor: not-allowed;
    pointer-events: none;
}

.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.role-card {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.role-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--role-color, var(--gold));
}

.role-card:hover {
    border-color: var(--gold);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(189, 145, 42, 0.15);
}

.role-card.protected {
    border-color: var(--turquase);
    background: linear-gradient(135deg, var(--charcoal), rgba(42, 189, 168, 0.05));
}

.role-card.super-admin-only {
    border-color: #ff6b6b;
    background: linear-gradient(135deg, var(--charcoal), rgba(255, 107, 107, 0.05));
}

.role-card.not-manageable {
    opacity: 0.6;
    background: linear-gradient(135deg, var(--charcoal), rgba(128, 128, 128, 0.1));
}

.role-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.role-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--role-color, var(--gold));
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.role-badges {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-end;
}

.role-badge {
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-protected {
    background: var(--turquase);
    color: var(--black);
}

.badge-super-admin {
    background: #ff6b6b;
    color: white;
}

.badge-admin-only {
    background: linear-gradient(135deg, #ff6b6b, #ffd700);
    color: var(--black);
    animation: glow 2s ease-in-out infinite alternate;
}

.badge-not-manageable {
    background: var(--grey);
    color: var(--light-grey);
}

@keyframes glow {
    from { box-shadow: 0 0 5px rgba(255, 215, 0, 0.5); }
    to { box-shadow: 0 0 15px rgba(255, 215, 0, 0.8); }
}

.role-description {
    color: var(--light-grey);
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 1rem;
    min-height: 2.8rem;
}

.role-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(34, 34, 34, 0.5);
    border-radius: 6px;
    border: 1px solid var(--darker-gold-2);
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--role-color, var(--gold));
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--light-grey);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
}

.btn-role-action {
    padding: 0.5rem 1rem;
    border: 1px solid;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    cursor: pointer;
    background: transparent;
}

.btn-edit {
    color: var(--turquase);
    border-color: var(--turquase);
}

.btn-edit:hover {
    background: var(--turquase);
    color: var(--charcoal);
}

.btn-delete {
    color: var(--red);
    border-color: var(--red);
}

.btn-delete:hover {
    background: var(--red);
    color: white;
}

.btn-permissions {
    color: var(--gold);
    border-color: var(--gold);
}

.btn-permissions:hover {
    background: var(--gold);
    color: var(--charcoal);
}

.btn-role-action.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.hierarchy-info {
    background: linear-gradient(135deg, rgba(42, 189, 168, 0.1), rgba(189, 145, 42, 0.05));
    border: 1px solid rgba(42, 189, 168, 0.3);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.hierarchy-title {
    color: var(--turquase);
    font-size: 1.1rem;
    font-weight: 500;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hierarchy-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.hierarchy-stat {
    background: rgba(42, 189, 168, 0.05);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid rgba(42, 189, 168, 0.2);
}

.hierarchy-stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--turquase);
    margin-bottom: 0.25rem;
}

.hierarchy-stat-label {
    font-size: 0.9rem;
    color: var(--light-grey);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    padding: 0;
}
.modal-content::-webkit-scrollbar {
    width: 0px;
    height: 0px;
}

/* Firefox için */
.modal-content {
    scrollbar-width: none; /* Firefox */
}

/* scrollbar tamamen devre dışı */
.modal-content {
    -ms-overflow-style: none;  /* Internet Explorer 10+ */
}
.modal-content {
    background-color: var(--charcoal);
    margin: 2% auto;
    padding: 0;
    border: 1px solid var(--darker-gold-1);
    border-radius: 8px;
    width: 90%;
    max-width: 1600px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    scrollbar-width: 0px;
}

.modal-header {
    background: linear-gradient(135deg, var(--darker-gold-1), var(--charcoal));
    padding: 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    color: var(--gold);
    font-size: 1.25rem;
    font-weight: 500;
    margin: 0;
}

.close {
    color: var(--light-grey);
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s ease;
}

.close:hover {
    color: var(--red);
}

.modal-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: var(--gold);
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-2);
    border-radius: 4px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 2px rgba(189, 145, 42, 0.1);
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.permission-group {
    background: rgba(34, 34, 34, 0.5);
    border: 1px solid var(--darker-gold-2);
    border-radius: 6px;
    padding: 1rem;
}

.permission-group-title {
    color: var(--gold);
    font-weight: 500;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    padding: 0.25rem;
    border-radius: 3px;
    transition: background-color 0.2s ease;
}

.permission-item:hover {
    background-color: rgba(189, 145, 42, 0.05);
}

.permission-checkbox {
    margin-top: 0.1rem;
    accent-color: var(--gold);
}

.permission-checkbox.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.permission-label {
    font-size: 0.8rem;
    color: var(--lighter-grey);
    line-height: 1.3;
    cursor: pointer;
    flex-grow: 1;
}

.permission-label.disabled {
    color: var(--light-grey);
    cursor: not-allowed;
}

.permission-restricted {
    background-color: rgba(255, 107, 107, 0.1);
    border: 1px dashed rgba(255, 107, 107, 0.3);
}

.permission-restricted .permission-label::after {
    content: " (Süper Admin Gerekli)";
    color: #ff6b6b;
    font-size: 0.7rem;
    font-weight: 500;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--darker-gold-2);
}

.btn-primary {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--charcoal);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(189, 145, 42, 0.3);
}

.btn-secondary {
    background: transparent;
    color: var(--light-grey);
    border: 1px solid var(--grey);
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: var(--grey);
    border-color: var(--light-grey);
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background-color: rgba(76, 175, 80, 0.1);
    border-color: rgba(76, 175, 80, 0.3);
    color: #4caf50;
}

.alert-danger {
    background-color: rgba(244, 67, 54, 0.1);
    border-color: rgba(244, 67, 54, 0.3);
    color: #f44336;
}

.alert-warning {
    background-color: rgba(255, 152, 0, 0.1);
    border-color: rgba(255, 152, 0, 0.3);
    color: #ff9800;
}

.alert-info {
    background-color: rgba(42, 189, 168, 0.1);
    border-color: rgba(42, 189, 168, 0.3);
    color: var(--turquase);
}

/* Responsive */
@media (max-width: 768px) {
    .role-management-container {
        padding: 1rem 0.75rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .roles-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .role-stats {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .role-actions {
        flex-direction: column;
    }
    
    .hierarchy-content {
        grid-template-columns: 1fr;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .modal-content {
        width: 95%;
        margin: 1% auto;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
}
</style>

<main class="main-content">
    <div class="role-management-container">
        <div class="page-header">
            <h1>
                <i class="fas fa-user-tag"></i>
                Rol Yönetimi
            </h1>
            <?php if (has_permission($pdo, 'admin.roles.create')): ?>
                <button class="btn-create-role" onclick="openCreateRoleModal()">
                    <i class="fas fa-plus"></i>
                    Yeni Rol Oluştur
                </button>
            <?php else: ?>
                <button class="btn-create-role disabled" title="Rol oluşturma yetkiniz yok">
                    <i class="fas fa-lock"></i>
                    Yetki Gerekli
                </button>
            <?php endif; ?>
        </div>

        <!-- Mesajlar -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($_SESSION['warning_message']); unset($_SESSION['warning_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Hiyerarşi Bilgisi -->
        <div class="hierarchy-info">
            <h3 class="hierarchy-title">
                <i class="fas fa-sitemap"></i>
                Yetki Durumunuz
            </h3>
            <div class="hierarchy-content">
                <div class="hierarchy-stat">
                    <div class="hierarchy-stat-number"><?php echo $user_info['highest_priority']; ?></div>
                    <div class="hierarchy-stat-label">Hiyerarşi Seviyeniz</div>
                </div>
                <div class="hierarchy-stat">
                    <div class="hierarchy-stat-number"><?php echo $user_info['manageable_count']; ?></div>
                    <div class="hierarchy-stat-label">Yönetilebilir Rol</div>
                </div>
                <div class="hierarchy-stat">
                    <div class="hierarchy-stat-number"><?php echo count($roles); ?></div>
                    <div class="hierarchy-stat-label">Toplam Rol</div>
                </div>
                <div class="hierarchy-stat">
                    <div class="hierarchy-stat-number"><?php echo $is_super_admin_user ? 'EVET' : 'HAYIR'; ?></div>
                    <div class="hierarchy-stat-label">Süper Admin</div>
                </div>
            </div>
        </div>

        <!-- Özel Koruma Uyarısı -->
        <?php if (!$is_super_admin_user): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Koruma Sistemi:</strong> Sadece <strong>"admin"</strong> rolü korunmaktadır. 
                Diğer tüm roller (member, dis_uye, vb.) artık normal yetki kurallarına göre yönetilebilir.
                <br><small>Hiyerarşik kısıtlamalar: Sadece daha düşük öncelikli rolleri yönetebilirsiniz.</small>
            </div>
        </div>
        <?php endif; ?>

        <!-- Roller Grid -->
        <div class="roles-grid">
            <?php foreach ($roles as $role): ?>
                <?php
                $role_classes = ['role-card'];
                if ($role['is_protected']) $role_classes[] = 'protected';
                if ($role['is_super_admin_only']) $role_classes[] = 'super-admin-only';
                if (!$role['can_manage'] && !$is_super_admin_user) $role_classes[] = 'not-manageable';
                ?>
                <div class="<?php echo implode(' ', $role_classes); ?>" style="--role-color: <?php echo htmlspecialchars($role['color']); ?>">
                    <div class="role-header">
                        <h3 class="role-name">
                            <?php echo htmlspecialchars($role['name']); ?>
                            <?php if ($role['priority'] <= 2): ?>
                                <i class="fas fa-crown" style="color: #ffd700;" title="Üst Düzey Rol"></i>
                            <?php endif; ?>
                        </h3>
                        <div class="role-badges">
                            <?php if ($role['is_protected']): ?>
                                <span class="role-badge badge-protected">Korumalı</span>
                            <?php endif; ?>
                            <?php if ($role['is_super_admin_only']): ?>
                                <span class="role-badge badge-admin-only">Sadece Admin</span>
                            <?php endif; ?>
                            <?php if (!$role['can_manage'] && !$is_super_admin_user): ?>
                                <span class="role-badge badge-not-manageable">Yönetilemez</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="role-description">
                        <?php echo htmlspecialchars($role['description']); ?>
                    </div>

                    <div class="role-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $role['priority']; ?></div>
                            <div class="stat-label">Öncelik</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $role['user_count']; ?></div>
                            <div class="stat-label">Kullanıcı</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $role['permission_count']; ?></div>
                            <div class="stat-label">Yetki</div>
                        </div>
                    </div>

                    <div class="role-actions">
                        <!-- Yetkiler Görüntüle -->
                        <button class="btn-role-action btn-permissions" onclick="viewRolePermissions(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name'], ENT_QUOTES); ?>')">
                            <i class="fas fa-key"></i>
                            Yetkiler
                        </button>

                        <!-- Düzenle -->
                        <?php if (has_permission($pdo, 'admin.roles.edit') && ($role['can_manage'] || $is_super_admin_user)): ?>
                            <button class="btn-role-action btn-edit" onclick="editRole(<?php echo $role['id']; ?>)">
                                <i class="fas fa-edit"></i>
                                Düzenle
                            </button>
                        <?php else: ?>
                            <button class="btn-role-action btn-edit disabled" title="Düzenleme yetkiniz yok">
                                <i class="fas fa-lock"></i>
                                Kilitli
                            </button>
                        <?php endif; ?>

                        <!-- Sil -->
                        <?php if (has_permission($pdo, 'admin.roles.delete') && ($role['can_manage'] || $is_super_admin_user) && $role['name'] !== 'admin'): ?>
                            <button class="btn-role-action btn-delete" onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name'], ENT_QUOTES); ?>', <?php echo $role['user_count']; ?>)">
                                <i class="fas fa-trash"></i>
                                Sil
                            </button>
                        <?php else: ?>
                            <button class="btn-role-action btn-delete disabled" title="<?php echo $role['name'] === 'admin' ? 'Admin rolü silinemez' : 'Silme yetkiniz yok'; ?>">
                                <i class="fas fa-shield-alt"></i>
                                <?php echo $role['name'] === 'admin' ? 'Korumalı' : 'Kilitli'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Admin Quick Navigation Include -->
        <?php include_once BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>
    </div>
</main>

<!-- Create/Edit Role Modal -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Yeni Rol Oluştur</h3>
            <span class="close" onclick="closeRoleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="roleForm" action="/src/actions/handle_roles.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" id="formAction" value="create_role">
                <input type="hidden" name="role_id" id="roleId" value="">

                <div class="form-group">
                    <label class="form-label" for="roleName">Rol Adı *</label>
                    <input type="text" class="form-control" id="roleName" name="role_name" 
                           value="<?php echo htmlspecialchars($form_input['role_name'] ?? ''); ?>" 
                           required pattern="^[a-z][a-z0-9_]{1,49}$"
                           title="Sadece küçük harf, rakam ve alt çizgi kullanın">
                    <small style="color: var(--light-grey); font-size: 0.8rem;">
                        Rol adı küçük harfle başlamalı, sadece harf, rakam ve alt çizgi içerebilir (2-50 karakter)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="roleDescription">Açıklama</label>
                    <textarea class="form-control" id="roleDescription" name="role_description" rows="3" 
                              maxlength="255"><?php echo htmlspecialchars($form_input['role_description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="roleColor">Rol Rengi</label>
                    <input type="color" class="form-control" id="roleColor" name="role_color" 
                           value="<?php echo htmlspecialchars($form_input['role_color'] ?? '#4a5568'); ?>" 
                           style="height: 50px;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="rolePriority">Öncelik *</label>
                    <input type="number" class="form-control" id="rolePriority" name="role_priority" 
                           value="<?php echo htmlspecialchars($form_input['role_priority'] ?? '999'); ?>" 
                           min="<?php echo $is_super_admin_user ? '1' : ($user_highest_priority + 1); ?>" 
                           max="9999" required>
                    <small style="color: var(--light-grey); font-size: 0.8rem;">
                        Düşük sayı = Yüksek öncelik. 
                        <?php if (!$is_super_admin_user): ?>
                            Sizin seviyenizden yüksek (<?php echo $user_highest_priority + 1; ?>+) olmalıdır.
                        <?php else: ?>
                            Süper admin olarak tüm öncelik seviyelerini kullanabilirsiniz.
                        <?php endif; ?>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Yetkiler</label>
                    <?php if (!$is_super_admin_user): ?>
                        <div class="alert alert-info" style="margin-bottom: 1rem;">
                            <i class="fas fa-info-circle"></i>
                            <small>Sadece sahip olduğunuz yetkileri başkalarına atayabilirsiniz. Kırmızı işaretli yetkiler süper admin gerektirir.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="permissions-grid">
                        <?php foreach ($grouped_permissions as $group_name => $permissions): ?>
                            <div class="permission-group">
                                <div class="permission-group-title"><?php echo htmlspecialchars($group_name); ?></div>
                                <?php foreach ($permissions as $permission_key => $permission_name): ?>
                                    <?php 
                                    $user_has_permission = in_array($permission_key, $user_permissions);
                                    $is_super_admin_permission = in_array($permission_key, ['admin.super_admin.view', 'admin.super_admin.manage', 'admin.audit_log.view', 'admin.audit_log.export']);
                                    $is_restricted = !$is_super_admin_user && $is_super_admin_permission;
                                    $is_disabled = !$is_super_admin_user && !$user_has_permission;
                                    ?>
                                    <div class="permission-item <?php echo $is_restricted ? 'permission-restricted' : ''; ?>">
                                        <input type="checkbox" 
                                               class="permission-checkbox <?php echo $is_disabled ? 'disabled' : ''; ?>" 
                                               name="permissions[]" 
                                               value="<?php echo htmlspecialchars($permission_key); ?>"
                                               id="perm_<?php echo htmlspecialchars($permission_key); ?>"
                                               <?php echo $is_disabled ? 'disabled' : ''; ?>>
                                        <label class="permission-label <?php echo $is_disabled ? 'disabled' : ''; ?>" 
                                               for="perm_<?php echo htmlspecialchars($permission_key); ?>">
                                            <?php echo htmlspecialchars($permission_name); ?>
                                            <?php if ($is_restricted): ?>
                                                <i class="fas fa-crown" style="color: #ff6b6b; margin-left: 0.25rem;" title="Süper Admin Gerekli"></i>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeRoleModal()">İptal</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Permissions Modal -->
<div id="permissionsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="permissionsModalTitle">Rol Yetkileri</h3>
            <span class="close" onclick="closePermissionsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="permissionsContent">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--gold);"></i>
                    <p style="margin-top: 1rem; color: var(--light-grey);">Yetkiler yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form input'larını session'dan doldur (hata durumunda)
<?php if (!empty($form_input)): ?>
document.addEventListener('DOMContentLoaded', function() {
    openCreateRoleModal();
});
<?php endif; ?>

function openCreateRoleModal() {
    document.getElementById('modalTitle').textContent = 'Yeni Rol Oluştur';
    document.getElementById('formAction').value = 'create_role';
    document.getElementById('roleId').value = '';
    document.getElementById('submitBtn').textContent = 'Oluştur';
    document.getElementById('roleForm').reset();
    
    // Priority minimum değerini ayarla
    const priorityInput = document.getElementById('rolePriority');
    <?php if (!$is_super_admin_user): ?>
    priorityInput.min = <?php echo $user_highest_priority + 1; ?>;
    priorityInput.value = <?php echo $user_highest_priority + 1; ?>;
    <?php else: ?>
    priorityInput.min = 1;
    priorityInput.value = 999;
    <?php endif; ?>
    
    document.getElementById('roleModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function editRole(roleId) {
    document.getElementById('modalTitle').textContent = 'Rol Düzenle';
    document.getElementById('formAction').value = 'update_role';
    document.getElementById('roleId').value = roleId;
    document.getElementById('submitBtn').textContent = 'Güncelle';
    
    // AJAX ile rol verilerini yükle
    fetch(`/src/api/get_role_data.php?role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const role = data.role;
                
                document.getElementById('roleName').value = role.name;
                document.getElementById('roleDescription').value = role.description || '';
                document.getElementById('roleColor').value = role.color;
                document.getElementById('rolePriority').value = role.priority;
                
                // Name editability kontrolü
                document.getElementById('roleName').readOnly = !role.is_name_editable;
                if (!role.is_name_editable) {
                    document.getElementById('roleName').style.backgroundColor = 'var(--darker-gold-2)';
                    document.getElementById('roleName').title = 'Bu rol adı değiştirilemez';
                }
                
                // Priority minimum kontrolü
                const priorityInput = document.getElementById('rolePriority');
                <?php if (!$is_super_admin_user): ?>
                if (role.priority <= <?php echo $user_highest_priority; ?>) {
                    priorityInput.min = role.priority; // Mevcut değerini koruyabilir
                } else {
                    priorityInput.min = <?php echo $user_highest_priority + 1; ?>;
                }
                <?php else: ?>
                priorityInput.min = 1;
                <?php endif; ?>
                
                // Yetkileri işaretle
                const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = role.permissions.includes(checkbox.value);
                });
                
                document.getElementById('roleModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                alert('Rol verileri yüklenirken hata oluştu: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Rol verileri yüklenirken bir hata oluştu.');
        });
}

function deleteRole(roleId, roleName, userCount) {
    let confirmMessage = `"${roleName}" rolünü silmek istediğinizden emin misiniz?`;
    
    if (userCount > 0) {
        confirmMessage += `\n\nBu rol ${userCount} kullanıcı tarafından kullanılmaktadır.`;
    }
    
    if (roleName === 'admin') {
        alert('Admin rolü güvenlik nedeniyle silinemez.');
        return;
    }
    
    if (confirm(confirmMessage)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/src/actions/handle_roles.php';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = 'csrf_token';
        csrfToken.value = '<?php echo generate_csrf_token(); ?>';
        
        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'delete_role';
        
        const roleIdInput = document.createElement('input');
        roleIdInput.type = 'hidden';
        roleIdInput.name = 'role_id';
        roleIdInput.value = roleId;
        
        form.appendChild(csrfToken);
        form.appendChild(action);
        form.appendChild(roleIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function viewRolePermissions(roleId, roleName) {
    document.getElementById('permissionsModalTitle').textContent = `"${roleName}" Rol Yetkileri`;
    document.getElementById('permissionsModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Yükleniyor durumunu göster
    document.getElementById('permissionsContent').innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--gold);"></i>
            <p style="margin-top: 1rem; color: var(--light-grey);">Yetkiler yükleniyor...</p>
        </div>
    `;
    
    // AJAX ile rol yetkilerini yükle
    fetch(`/src/api/get_role_permissions.php?role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '';
                
                if (data.stats.total_permissions === 0) {
                    html = `
                        <div style="text-align: center; padding: 2rem; color: var(--light-grey);">
                            <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <p>Bu rol henüz hiçbir yetkiye sahip değil.</p>
                        </div>
                    `;
                } else {
                    html = `
                        <div style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(42, 189, 168, 0.1); border-radius: 6px; border: 1px solid rgba(42, 189, 168, 0.3);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--turquase); font-weight: 500;">
                                    <i class="fas fa-key"></i> Toplam Yetki: ${data.stats.total_permissions}
                                </span>
                                <span style="color: var(--light-grey); font-size: 0.9rem;">
                                    ${Object.keys(data.grouped_permissions).length} grup
                                </span>
                            </div>
                        </div>
                        <div class="permissions-grid">
                    `;
                    
                    for (const [groupName, permissions] of Object.entries(data.grouped_permissions)) {
                        html += `
                            <div class="permission-group">
                                <div class="permission-group-title">
                                    ${groupName} (${permissions.length})
                                </div>
                        `;
                        
                        permissions.forEach(permission => {
                            html += `
                                <div class="permission-item">
                                    <i class="fas fa-check" style="color: var(--turquase); margin-top: 0.1rem; width: 16px;"></i>
                                    <span class="permission-label">${permission.name}</span>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                    }
                    
                    html += '</div>';
                }
                
                document.getElementById('permissionsContent').innerHTML = html;
            } else {
                document.getElementById('permissionsContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--red);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>Yetkiler yüklenirken hata oluştu: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('permissionsContent').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--red);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>Yetkiler yüklenirken bir hata oluştu.</p>
                </div>
            `;
        });
}

function closePermissionsModal() {
    document.getElementById('permissionsModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Modal dışına tıklayınca kapat
window.onclick = function(event) {
    const roleModal = document.getElementById('roleModal');
    const permissionsModal = document.getElementById('permissionsModal');
    
    if (event.target === roleModal) {
        closeRoleModal();
    }
    if (event.target === permissionsModal) {
        closePermissionsModal();
    }
}

// Escape tuşu ile modali kapat
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRoleModal();
        closePermissionsModal();
    }
});

// Form validasyonu
document.getElementById('roleForm').addEventListener('submit', function(e) {
    const roleName = document.getElementById('roleName').value;
    const rolePriority = parseInt(document.getElementById('rolePriority').value);
    
    // Rol adı format kontrolü
    if (!/^[a-z][a-z0-9_]{1,49}$/.test(roleName)) {
        e.preventDefault();
        alert('Rol adı geçersiz format. Küçük harfle başlamalı, sadece harf, rakam ve alt çizgi içerebilir.');
        return;
    }
    
    // Priority kontrolü
    <?php if (!$is_super_admin_user): ?>
    if (rolePriority <= <?php echo $user_highest_priority; ?>) {
        e.preventDefault();
        alert('Rol önceliği sizin hiyerarşi seviyenizden yüksek olmalıdır (<?php echo $user_highest_priority + 1; ?>+).');
        return;
    }
    <?php endif; ?>
    
    // En az bir yetki seçilmiş mi kontrol et
    const checkedPermissions = document.querySelectorAll('input[name="permissions[]"]:checked:not(:disabled)');
    if (checkedPermissions.length === 0) {
        if (!confirm('Hiçbir yetki seçilmedi. Rol yetki olmadan oluşturulsun mu?')) {
            e.preventDefault();
            return;
        }
    }
});

// Rol kartları için hover efektleri
document.addEventListener('DOMContentLoaded', function() {
    const roleCards = document.querySelectorAll('.role-card');
    roleCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.classList.contains('not-manageable')) {
                this.style.boxShadow = '0 12px 35px rgba(189, 145, 42, 0.25)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.boxShadow = '';
        });
    });
    
    // Renk preview
    const colorInput = document.getElementById('roleColor');
    if (colorInput) {
        colorInput.addEventListener('change', function() {
            document.documentElement.style.setProperty('--preview-color', this.value);
        });
    }
});

// Responsive navigation için
function toggleMobileMenu() {
    const menu = document.querySelector('.hierarchy-content');
    if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'grid' : 'none';
    }
}
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>