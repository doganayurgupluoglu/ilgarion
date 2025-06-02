<?php
// public/admin/users.php - Hiyerarşik Yetki Sistemi ile Kullanıcı Yönetimi

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Oturum kontrolü
if (is_user_logged_in()) {
    check_user_session_validity();
}

// Yetki kontrolü
require_permission($pdo, 'admin.users.view');

$page_title = "Kullanıcı Yönetimi";
$current_admin_id = $_SESSION['user_id'];
$is_super_admin_user = is_super_admin($pdo);

// Kullanıcının hiyerarşik konumunu belirle
$admin_roles = get_user_roles($pdo, $current_admin_id);
$admin_highest_priority = 999;
foreach ($admin_roles as $role) {
    if ($role['priority'] < $admin_highest_priority) {
        $admin_highest_priority = $role['priority'];
    }
}

// Filtreleme parametreleri
$filter_status = $_GET['filter'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Kullanıcıları çek (hiyerarşi kontrolü ile)
$users = [];
$total_users = 0;

try {
    $where_conditions = [];
    $params = [];
    
    // Status filtresi
    if ($filter_status !== 'all') {
        $where_conditions[] = "u.status = :status";
        $params[':status'] = $filter_status;
    }
    
    // Arama filtresi
    if (!empty($search_query)) {
        if (!validate_sql_input($search_query)) {
            throw new SecurityException("Invalid search query");
        }
        $search_pattern = create_safe_like_pattern($search_query, 'contains');
        $where_conditions[] = "(u.username LIKE :search OR u.email LIKE :search OR u.ingame_name LIKE :search)";
        $params[':search'] = $search_pattern;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Toplam kullanıcı sayısı
    $count_query = "SELECT COUNT(*) FROM users u $where_clause";
    $stmt = execute_safe_query($pdo, $count_query, $params);
    $total_users = $stmt->fetchColumn();
    
    // Kullanıcıları çek
    $users_query = "
        SELECT u.id, u.username, u.email, u.status, u.ingame_name, u.discord_username, 
               u.avatar_path, u.created_at,
               MIN(r.priority) as user_highest_priority,
               GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ', ') as user_roles,
               GROUP_CONCAT(DISTINCT r.color ORDER BY r.priority ASC SEPARATOR ',') as role_colors,
               COUNT(DISTINCT ur.role_id) as role_count
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        $where_clause
        GROUP BY u.id, u.username, u.email, u.status, u.ingame_name, u.discord_username, 
                 u.avatar_path, u.created_at
        ORDER BY u.created_at DESC
        LIMIT $offset, $per_page
    ";
    
    $stmt = execute_safe_query($pdo, $users_query, $params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Her kullanıcı için yönetim yetkisini kontrol et
    foreach ($users as &$user) {
        $user_priority = $user['user_highest_priority'] ?? 999;
        $user['can_manage'] = $is_super_admin_user || 
                             ($user_priority > $admin_highest_priority && $user['id'] != $current_admin_id);
        
        // Rol renklerini işle
        if (!empty($user['role_colors'])) {
            $colors = explode(',', $user['role_colors']);
            $user['primary_role_color'] = $colors[0];
        } else {
            $user['primary_role_color'] = '#808080';
        }
    }
    
} catch (Exception $e) {
    error_log("Users list error: " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcılar yüklenirken bir hata oluştu.";
}

// İstatistikler
$stats = [];
try {
    $stats_query = "
        SELECT status, COUNT(*) as count 
        FROM users 
        GROUP BY status
    ";
    $stmt = execute_safe_query($pdo, $stats_query);
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status_counts as $stat) {
        $stats[$stat['status']] = $stat['count'];
    }
    
    $stats['total'] = array_sum($stats);
} catch (Exception $e) {
    error_log("User stats error: " . $e->getMessage());
}

// Yönetilebilir roller (hiyerarşi kontrolü ile)
$manageable_roles = [];
try {
    if (has_permission($pdo, 'admin.users.assign_roles')) {
        if ($is_super_admin_user) {
            $roles_query = "SELECT id, name, description, color, priority FROM roles ORDER BY priority ASC";
            $stmt = execute_safe_query($pdo, $roles_query);
        } else {
            $roles_query = "SELECT id, name, description, color, priority FROM roles WHERE priority > ? ORDER BY priority ASC";
            $stmt = execute_safe_query($pdo, $roles_query, [$admin_highest_priority]);
        }
        $manageable_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Manageable roles error: " . $e->getMessage());
}

$total_pages = ceil($total_users / $per_page);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.admin-users {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.users-header h1 {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1.25rem;
    text-align: center;
    transition: border-color 0.2s ease;
}

.stat-card:hover {
    border-color: var(--gold);
}

.stat-number {
    font-size: 1.75rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--light-grey);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters-section {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.9rem;
    color: var(--light-grey);
    font-weight: 500;
}

.filter-select, .search-input {
    padding: 0.75rem;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.filter-select:focus, .search-input:focus {
    outline: none;
    border-color: var(--gold);
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    background-color: var(--gold);
    color: var(--charcoal);
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--light-gold);
}

.users-table-container {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th,
.users-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2);
}

.users-table th {
    background-color: rgba(34, 34, 34, 0.5);
    color: var(--gold);
    font-weight: 500;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.users-table tbody tr:hover {
    background-color: rgba(189, 145, 42, 0.05);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--darker-gold-1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-username {
    font-weight: 500;
    color: var(--lighter-grey);
}

.user-ingame {
    font-size: 0.85rem;
    color: var(--light-grey);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background-color: rgba(255, 152, 0, 0.2);
    color: #ff9800;
    border: 1px solid rgba(255, 152, 0, 0.3);
}

.status-approved {
    background-color: rgba(76, 175, 80, 0.2);
    color: #4caf50;
    border: 1px solid rgba(76, 175, 80, 0.3);
}

.status-rejected {
    background-color: rgba(244, 67, 54, 0.2);
    color: #f44336;
    border: 1px solid rgba(244, 67, 54, 0.3);
}

.status-suspended {
    background-color: rgba(156, 39, 176, 0.2);
    color: #9c27b0;
    border: 1px solid rgba(156, 39, 176, 0.3);
}

.user-roles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.role-badge {
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--charcoal);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.user-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-action {
    padding: 0.4rem 0.75rem;
    border: 1px solid;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    background: transparent;
}

.btn-edit {
    color: var(--turquase);
    border-color: var(--turquase);
}

.btn-edit:hover {
    background-color: var(--turquase);
    color: var(--charcoal);
}

.btn-approve {
    color: #4caf50;
    border-color: #4caf50;
}

.btn-approve:hover {
    background-color: #4caf50;
    color: white;
}

.btn-reject {
    color: #f44336;
    border-color: #f44336;
}

.btn-reject:hover {
    background-color: #f44336;
    color: white;
}

.btn-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-btn {
    padding: 0.5rem 0.75rem;
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.page-btn:hover,
.page-btn.active {
    background-color: var(--gold);
    color: var(--charcoal);
    border-color: var(--gold);
}

.hierarchy-info {
    background-color: rgba(42, 189, 168, 0.1);
    border: 1px solid rgba(42, 189, 168, 0.3);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: var(--turquase);
}

/* Modal styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: none;
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: var(--charcoal);
    border: 1px solid var(--gold);
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    color: var(--gold);
    margin: 0;
    font-size: 1.25rem;
}

.modal-close {
    background: none;
    border: none;
    color: var(--light-grey);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
}

.modal-body {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--light-grey);
    font-weight: 500;
}

.form-select {
    width: 100%;
    padding: 0.75rem;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background-color: transparent;
    color: var(--light-grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: var(--darker-gold-1);
    color: var(--lighter-grey);
}

/* Responsive Design */
@media (max-width: 768px) {
    .admin-users {
        padding: 1rem 0.75rem;
    }
    
    .users-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .users-table-container {
        overflow-x: auto;
    }
    
    .users-table {
        min-width: 800px;
    }
    
    .user-actions {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-action {
        font-size: 0.75rem;
        padding: 0.3rem 0.5rem;
    }
}
</style>

<main class="main-content">
    <div class="admin-users">
        <div class="users-header">
            <h1>
                <i class="fas fa-users"></i>
                Kullanıcı Yönetimi
            </h1>
        </div>

        <?php // Mesajlar ?>
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

        <!-- Hiyerarşi Bilgisi -->
        <div class="hierarchy-info">
            <i class="fas fa-info-circle"></i>
            <strong>Yetki Bilgisi:</strong>
            <?php if ($is_super_admin_user): ?>
                Süper Admin olarak tüm kullanıcıları yönetebilirsiniz.
            <?php else: ?>
                Hiyerarşi seviyeniz: <?php echo $admin_highest_priority; ?> - Sadece daha düşük seviyeli kullanıcıları yönetebilirsiniz.
            <?php endif; ?>
        </div>

        <!-- İstatistikler -->
        <?php if (!empty($stats)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Onay Bekleyen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Onaylı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Reddedilen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['suspended'] ?? 0; ?></div>
                <div class="stat-label">Askıya Alınan</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtreler -->
        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Durum</label>
                    <select name="filter" class="filter-select">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tümü</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Onay Bekleyen</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Onaylı</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Reddedilen</option>
                        <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Askıya Alınan</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Arama</label>
                    <input type="text" name="search" class="search-input" 
                           placeholder="Kullanıcı adı, e-posta veya oyun adı..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Filtrele
                </button>
            </form>
        </div>

        <!-- Kullanıcılar Tablosu -->
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>E-posta</th>
                        <th>Durum</th>
                        <th>Roller</th>
                        <th>Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--light-grey);">
                                <i class="fas fa-users"></i><br>
                                Hiç kullanıcı bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo !empty($user['avatar_path']) ? '/public/' . htmlspecialchars($user['avatar_path']) : 'https://via.placeholder.com/40x40/666/fff?text=' . substr($user['username'], 0, 1); ?>" 
                                             alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                             class="user-avatar">
                                        <div class="user-details">
                                            <div class="user-username"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <?php if (!empty($user['ingame_name'])): ?>
                                                <div class="user-ingame"><?php echo htmlspecialchars($user['ingame_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php
                                        $status_labels = [
                                            'pending' => 'Bekliyor',
                                            'approved' => 'Onaylı',
                                            'rejected' => 'Reddedilen',
                                            'suspended' => 'Askıda'
                                        ];
                                        echo $status_labels[$user['status']] ?? $user['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($user['user_roles'])): ?>
                                        <div class="user-roles">
                                            <?php
                                            $roles = explode(', ', $user['user_roles']);
                                            $colors = explode(',', $user['role_colors'] ?? '');
                                            foreach ($roles as $index => $role):
                                                $color = $colors[$index] ?? '#808080';
                                            ?>
                                                <span class="role-badge" style="background-color: <?php echo htmlspecialchars($color); ?>;">
                                                    <?php echo htmlspecialchars($role); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--light-grey); font-style: italic;">Rol atanmamış</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="user-actions">
                                        <?php if ($user['can_manage']): ?>
                                            <?php if (has_permission($pdo, 'admin.users.assign_roles')): ?>
                                                <a href="/public/admin/edit_user_roles.php?user_id=<?php echo $user['id']; ?>" 
                                                   class="btn-action btn-edit" title="Rolleri Düzenle">
                                                    <i class="fas fa-user-tag"></i> Roller
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (has_permission($pdo, 'admin.users.edit_status')): ?>
                                                <?php if ($user['status'] === 'pending'): ?>
                                                    <button class="btn-action btn-approve" 
                                                            onclick="updateUserStatus(<?php echo $user['id']; ?>, 'approve')" 
                                                            title="Onayla">
                                                        <i class="fas fa-check"></i> Onayla
                                                    </button>
                                                    <button class="btn-action btn-reject" 
                                                            onclick="updateUserStatus(<?php echo $user['id']; ?>, 'reject')" 
                                                            title="Reddet">
                                                        <i class="fas fa-times"></i> Reddet
                                                    </button>
                                                <?php elseif ($user['status'] === 'approved'): ?>
                                                    <button class="btn-action btn-reject" 
                                                            onclick="updateUserStatus(<?php echo $user['id']; ?>, 'suspend')" 
                                                            title="Askıya Al">
                                                        <i class="fas fa-ban"></i> Askıya Al
                                                    </button>
                                                <?php elseif (in_array($user['status'], ['suspended', 'rejected'])): ?>
                                                    <button class="btn-action btn-approve" 
                                                            onclick="updateUserStatus(<?php echo $user['id']; ?>, 'reinstate_approved')" 
                                                            title="Yeniden Onayla">
                                                        <i class="fas fa-undo"></i> Onayla
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="btn-action btn-disabled" title="Bu kullanıcıyı yönetme yetkiniz yok">
                                                <i class="fas fa-lock"></i> Kısıtlı
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>" 
                   class="page-btn">
                    <i class="fas fa-chevron-left"></i> Önceki
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>" 
                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>" 
                   class="page-btn">
                    Sonraki <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Admin Quick Navigation Include -->
        <?php include_once BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>
    </div>
</main>

<!-- Hidden Forms for Status Updates -->
<div style="display: none;">
    <form id="statusUpdateForm" method="POST" action="/src/actions/handle_user_approval.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="user_id" id="statusUserId">
        <input type="hidden" name="action" id="statusAction">
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status update function
    window.updateUserStatus = function(userId, action) {
        const actionLabels = {
            'approve': 'onaylamak',
            'reject': 'reddetmek',
            'suspend': 'askıya almak',
            'reinstate_approved': 'yeniden onaylamak'
        };
        
        const actionLabel = actionLabels[action] || action;
        
        if (confirm(`Bu kullanıcıyı ${actionLabel} istediğinizden emin misiniz?`)) {
            document.getElementById('statusUserId').value = userId;
            document.getElementById('statusAction').value = action;
            document.getElementById('statusUpdateForm').submit();
        }
    };
    
    // Table row hover effects
    const tableRows = document.querySelectorAll('.users-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(2px)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Search input enhancement
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    // Auto-submit after 1 second of no typing
                    this.closest('form').submit();
                }
            }, 1000);
        });
    }
    
    // Status badge animations
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Role badge interactions
    const roleBadges = document.querySelectorAll('.role-badge');
    roleBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Action button confirmations
    const actionButtons = document.querySelectorAll('.btn-action:not(.btn-edit)');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.textContent.trim();
            if (action.includes('Sil') || action.includes('Reddet') || action.includes('Askıya')) {
                if (!confirm(`Bu işlemi yapmak istediğinizden emin misiniz? (${action})`)) {
                    e.preventDefault();
                }
            }
        });
    });
    
    // Enhanced table responsiveness
    function checkTableResponsive() {
        const table = document.querySelector('.users-table');
        const container = document.querySelector('.users-table-container');
        
        if (table && container) {
            if (table.offsetWidth > container.offsetWidth) {
                container.classList.add('scroll-hint');
            } else {
                container.classList.remove('scroll-hint');
            }
        }
    }
    
    checkTableResponsive();
    window.addEventListener('resize', checkTableResponsive);
    
    // Auto-refresh for pending users (every 30 seconds)
    if (window.location.search.includes('filter=pending')) {
        setInterval(() => {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    }
});

// Add scroll hint styles
const style = document.createElement('style');
style.textContent = `
    .users-table-container.scroll-hint::after {
        content: '← Kaydırın →';
        position: absolute;
        bottom: 10px;
        right: 10px;
        background-color: rgba(189, 145, 42, 0.9);
        color: var(--charcoal);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.7rem;
        pointer-events: none;
        animation: pulse 2s infinite;
    }
    
    .users-table-container {
        position: relative;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .status-badge,
    .role-badge {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .users-table tbody tr {
        transition: transform 0.2s ease, background-color 0.2s ease;
    }
`;
document.head.appendChild(style);
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>