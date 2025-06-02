<?php
// public/admin/users.php - Kullanıcı Yönetimi Sayfası

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

// Admin yetkisi kontrolü
require_permission($pdo, 'admin.users.view');

$page_title = "Kullanıcı Yönetimi";
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

// Filtreleme parametreleri
$filter_status = $_GET['filter'] ?? 'all';
$search_term = trim($_GET['search'] ?? '');
$valid_filters = ['all', 'pending', 'approved', 'rejected', 'suspended'];

if (!in_array($filter_status, $valid_filters)) {
    $filter_status = 'all';
}

// Kullanıcıları çek
$users = [];
$where_conditions = [];
$params = [];

// Status filtreleme
if ($filter_status !== 'all') {
    $where_conditions[] = "u.status = :status";
    $params[':status'] = $filter_status;
}

// Arama filtreleme
if (!empty($search_term)) {
    // Basit güvenlik kontrolü - çok kısıtlayıcı olmasın
    if (strlen($search_term) > 100) {
        $_SESSION['error_message'] = "Arama terimi çok uzun (maksimum 100 karakter).";
        $search_term = '';
    } elseif (preg_match('/[<>"\']/', $search_term)) {
        $_SESSION['error_message'] = "Arama terimi geçersiz karakterler içeriyor.";
        $search_term = '';
    } else {
        try {
            $search_pattern = create_safe_like_pattern($search_term, 'contains');
            // Her LIKE için ayrı parametre kullan
            $where_conditions[] = "(u.username LIKE :search_username OR u.email LIKE :search_email OR u.ingame_name LIKE :search_ingame)";
            $params[':search_username'] = $search_pattern;
            $params[':search_email'] = $search_pattern;
            $params[':search_ingame'] = $search_pattern;
            
            // Debug: Arama pattern'ini logla
            error_log("Search debug - Original term: '$search_term', Pattern: '$search_pattern'");
        } catch (Exception $e) {
            error_log("Search pattern creation error: " . $e->getMessage());
            $_SESSION['error_message'] = "Arama terimi işlenirken hata oluştu.";
            $search_term = '';
        }
    }
}

try {
    // Debug: Sorgu ve parametreleri logla
    error_log("Users query debug - WHERE conditions: " . implode(" AND ", $where_conditions));
    error_log("Users query debug - Parameters: " . json_encode($params));
    
    $query = "
        SELECT u.id, u.username, u.email, u.status, u.ingame_name, u.discord_username, 
               u.avatar_path, u.created_at, u.updated_at,
               GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ',') as roles_list,
               MIN(r.priority) as highest_priority,
               (SELECT r2.color 
                FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id 
                ORDER BY r2.priority ASC 
                LIMIT 1) as primary_role_color
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        " . (!empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "") . "
        GROUP BY u.id, u.username, u.email, u.status, u.ingame_name, u.discord_username, 
                 u.avatar_path, u.created_at, u.updated_at
        ORDER BY u.created_at DESC
    ";
    
    error_log("Users query debug - Final query: " . $query);
    
    $stmt = execute_safe_query($pdo, $query, $params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Users query debug - Result count: " . count($users));
    
    // Her kullanıcı için yönetim yetkilerini kontrol et
    foreach ($users as &$user) {
        $target_priority = $user['highest_priority'] ?? 999;
        
        // Hiyerarşi kontrolü
        if ($is_super_admin_user) {
            $user['can_manage'] = true;
        } else {
            // Kullanıcı kendini yönetemez ve üst seviye kullanıcıları yönetemez
            $user['can_manage'] = ($user['id'] != $current_user_id) && ($target_priority > $user_highest_priority);
        }
        
        // Roller dizisi oluştur
        $user['roles_array'] = !empty($user['roles_list']) ? explode(',', $user['roles_list']) : [];
    }
    
} catch (Exception $e) {
    error_log("Kullanıcı listesi çekme hatası: " . $e->getMessage());
    error_log("Query: " . ($query ?? 'Query not set'));
    error_log("Params: " . json_encode($params));
    error_log("Where conditions: " . json_encode($where_conditions));
    $_SESSION['error_message'] = "Kullanıcılar yüklenirken bir hata oluştu. Detay: " . $e->getMessage();
    $users = [];
}

// İstatistikler
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'suspended' => 0
];

try {
    $stats_query = "
        SELECT status, COUNT(*) as count 
        FROM users 
        GROUP BY status
    ";
    $stmt_stats = execute_safe_query($pdo, $stats_query);
    $stats_data = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats_data as $stat) {
        $stats[$stat['status']] = $stat['count'];
        $stats['total'] += $stat['count'];
    }
} catch (Exception $e) {
    error_log("Kullanıcı istatistikleri hatası: " . $e->getMessage());
}

// Yönetilebilir roller (rol atama için)
$manageable_roles = [];
if (has_permission($pdo, 'admin.users.assign_roles')) {
    try {
        if ($is_super_admin_user) {
            $roles_query = "SELECT id, name, color, priority FROM roles ORDER BY priority ASC";
            $stmt_roles = execute_safe_query($pdo, $roles_query);
            $manageable_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $roles_query = "SELECT id, name, color, priority FROM roles WHERE priority > :user_priority ORDER BY priority ASC";
            $stmt_roles = execute_safe_query($pdo, $roles_query, [':user_priority' => $user_highest_priority]);
            $manageable_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Roller çekme hatası: " . $e->getMessage());
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Kullanıcı Yönetimi Sayfa Stilleri */
.users-management {
    width: 100%;
    max-width: 1600px;
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

.header-stats {
    display: flex;
    gap: 1rem;
    align-items: center;
    font-size: 0.9rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    background: rgba(189, 145, 42, 0.1);
    border-radius: 20px;
    border: 1px solid var(--darker-gold-2);
}

.stat-number {
    font-weight: bold;
    color: var(--gold);
}

.filters-section {
    background: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.filters-row {
    display: grid;
    grid-template-columns: 200px 1fr 150px;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    color: var(--gold);
    font-size: 0.9rem;
    font-weight: 500;
}

.filter-select,
.filter-input {
    padding: 0.75rem;
    background: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 2px rgba(189, 145, 42, 0.2);
}

.filter-btn {
    padding: 0.75rem 1.5rem;
    background: var(--gold);
    color: var(--charcoal);
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn:hover {
    background: var(--light-gold);
    transform: translateY(-1px);
}

.users-table-container {
    background: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    overflow: hidden;
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
    background: rgba(34, 34, 34, 0.5);
    color: var(--gold);
    font-weight: 500;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.users-table tbody tr:hover {
    background: rgba(189, 145, 42, 0.05);
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
    gap: 1rem;
}

.user-details h4 {
    margin: 0 0 0.25rem 0;
    color: var(--lighter-grey);
    font-size: 1rem;
    font-weight: 500;
}

.user-details .user-meta {
    font-size: 0.8rem;
    color: var(--light-grey);
    display: flex;
    gap: 1rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-pending {
    background: rgba(255, 152, 0, 0.2);
    color: #ff9800;
    border: 1px solid rgba(255, 152, 0, 0.3);
}

.status-approved {
    background: rgba(76, 175, 80, 0.2);
    color: #4caf50;
    border: 1px solid rgba(76, 175, 80, 0.3);
}

.status-rejected {
    background: rgba(244, 67, 54, 0.2);
    color: #f44336;
    border: 1px solid rgba(244, 67, 54, 0.3);
}

.status-suspended {
    background: rgba(156, 39, 176, 0.2);
    color: #9c27b0;
    border: 1px solid rgba(156, 39, 176, 0.3);
}

.user-roles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 500;
    background: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
}

.user-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.action-btn.approve {
    background: #4caf50;
    color: white;
}

.action-btn.reject {
    background: #f44336;
    color: white;
}

.action-btn.suspend {
    background: #9c27b0;
    color: white;
}

.action-btn.reinstate {
    background: var(--turquase);
    color: var(--charcoal);
}

.action-btn.roles {
    background: var(--gold);
    color: var(--charcoal);
}

.action-btn.disabled {
    background: var(--grey);
    color: var(--light-grey);
    cursor: not-allowed;
    opacity: 0.5;
}

.action-btn:not(.disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.quick-actions {
    margin-top: 1.5rem;
    padding: 1rem;
    background: rgba(189, 145, 42, 0.1);
    border-radius: 6px;
    border: 1px solid var(--darker-gold-2);
}

.quick-actions h3 {
    color: var(--gold);
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.quick-action-btn {
    padding: 0.75rem;
    background: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--lighter-grey);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    background: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
}

.no-results {
    text-align: center;
    padding: 3rem;
    color: var(--light-grey);
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--darker-gold-1);
}

/* Modal Stilleri */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.modal-title {
    color: var(--gold);
    margin: 0;
    font-size: 1.2rem;
}

.modal-body {
    margin-bottom: 1.5rem;
}

.modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--darker-gold-2);
}

.btn-primary {
    background: var(--gold);
    color: var(--charcoal);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
}
.btn-secondary {
    background: var(--red);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
}
.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    color: var(--light-grey);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: var(--grey);
    color: var(--gold);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: var(--gold);
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.current-roles-display {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--grey);
    border-radius: 6px;
    border: 1px solid var(--darker-gold-2);
    min-height: 40px;
    align-items: center;
}

.current-role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    max-height: 300px;
    overflow-y: auto;
    padding: 0.5rem;
    border: 1px solid var(--darker-gold-2);
    border-radius: 6px;
    background: var(--grey);
}

.role-checkbox-item {
    display: block;
    cursor: pointer;
}

.role-checkbox-item input[type="checkbox"] {
    display: none;
}

.role-checkbox-visual {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 2px solid var(--darker-gold-1);
    border-radius: 6px;
    background: var(--charcoal);
    transition: all 0.2s ease;
    position: relative;
}

.role-checkbox-visual:hover {
    background: rgba(189, 145, 42, 0.1);
    transform: translateY(-1px);
}

.role-checkbox-item input:checked + .role-checkbox-visual {
    border-color: var(--gold);
    background: rgba(189, 145, 42, 0.2);
}

.role-color-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.role-name {
    flex-grow: 1;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    font-weight: 500;
}

.role-check-icon {
    color: var(--gold);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.role-checkbox-item input:checked + .role-checkbox-visual .role-check-icon {
    opacity: 1;
}

.warning-note {
    padding: 1rem;
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
    border-radius: 6px;
    color: #ff9800;
    font-size: 0.85rem;
    margin-top: 1rem;
}

.warning-note i {
    margin-right: 0.5rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filters-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .users-table {
        font-size: 0.9rem;
    }
    
    .users-table th,
    .users-table td {
        padding: 0.75rem;
    }
}

@media (max-width: 768px) {
    .users-management {
        padding: 1rem 0.5rem;
    }
    
    .users-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .header-stats {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .users-table-container {
        overflow-x: auto;
    }
    
    .users-table {
        min-width: 800px;
    }
    
    .user-actions {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <div class="users-management">
        <div class="users-header">
            <h1>
                <i class="fas fa-users"></i>
                Kullanıcı Yönetimi
            </h1>
            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total']; ?></span>
                    <span>Toplam</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['pending']; ?></span>
                    <span>Bekleyen</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['approved']; ?></span>
                    <span>Onaylı</span>
                </div>
            </div>
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

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($_SESSION['warning_message']); unset($_SESSION['warning_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Filtreler -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Durum Filtresi</label>
                        <select name="filter" class="filter-select">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tümü</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Onay Bekleyen (<?php echo $stats['pending']; ?>)</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Onaylı (<?php echo $stats['approved']; ?>)</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Reddedilen (<?php echo $stats['rejected']; ?>)</option>
                            <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Askıda (<?php echo $stats['suspended']; ?>)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Arama</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Kullanıcı adı, email veya oyun adı..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search"></i>
                            Filtrele
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Kullanıcı Tablosu -->
        <div class="users-table-container">
            <?php if (empty($users)): ?>
                <div class="no-results">
                    <i class="fas fa-users-slash"></i>
                    <h3>Kullanıcı Bulunamadı</h3>
                    <p>Seçilen kriterlere uygun kullanıcı bulunmamaktadır.</p>
                </div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Durum</th>
                            <th>Roller</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo !empty($user['avatar_path']) ? '/public/' . htmlspecialchars($user['avatar_path']) : '/public/assets/images/default-avatar.png'; ?>" 
                                             alt="Avatar" class="user-avatar">
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                                            <div class="user-meta">
                                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                                                <?php if (!empty($user['ingame_name'])): ?>
                                                    <span><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($user['ingame_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($user['discord_username'])): ?>
                                                    <span><i class="fab fa-discord"></i> <?php echo htmlspecialchars($user['discord_username']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-roles">
                                        <?php if (!empty($user['roles_array'])): ?>
                                            <?php foreach ($user['roles_array'] as $role): ?>
                                                <span class="role-badge" 
                                                      style="<?php echo !empty($user['primary_role_color']) ? 'border-color: ' . htmlspecialchars($user['primary_role_color']) : ''; ?>">
                                                    <?php echo htmlspecialchars($role); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="role-badge">Rol atanmamış</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <?php if ($user['can_manage']): ?>
                                            <!-- Durum Değiştirme İşlemleri -->
                                            <?php if ($user['status'] === 'pending' && has_permission($pdo, 'admin.users.edit_status')): ?>
                                                <form method="POST" action="/src/actions/handle_user_approval.php" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="action-btn approve" 
                                                            onclick="return confirm('Bu kullanıcıyı onaylamak istediğinizden emin misiniz?')">
                                                        <i class="fas fa-check"></i> Onayla
                                                    </button>
                                                </form>
                                                <form method="POST" action="/src/actions/handle_user_approval.php" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="action-btn reject" 
                                                            onclick="return confirm('Bu kullanıcıyı reddetmek istediğinizden emin misiniz?')">
                                                        <i class="fas fa-times"></i> Reddet
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'approved' && has_permission($pdo, 'admin.users.edit_status')): ?>
                                                <form method="POST" action="/src/actions/handle_user_approval.php" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button type="submit" class="action-btn suspend" 
                                                            onclick="return confirm('Bu kullanıcıyı askıya almak istediğinizden emin misiniz?')">
                                                        <i class="fas fa-pause"></i> Askıya Al
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($user['status'], ['suspended', 'rejected']) && has_permission($pdo, 'admin.users.edit_status')): ?>
                                                <form method="POST" action="/src/actions/handle_user_approval.php" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="reinstate_approved">
                                                    <button type="submit" class="action-btn reinstate" 
                                                            onclick="return confirm('Bu kullanıcıyı tekrar onaylı duruma getirmek istediğinizden emin misiniz?')">
                                                        <i class="fas fa-undo"></i> Yeniden Onayla
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Rol Yönetimi -->
                                            <?php if (has_permission($pdo, 'admin.users.assign_roles') && !empty($manageable_roles)): ?>
                                                <button type="button" class="action-btn roles" 
                                                        onclick="openRoleModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo htmlspecialchars(json_encode($user['roles_array'])); ?>)">
                                                    <i class="fas fa-user-tag"></i> Rolleri Düzenle
                                                </button>
                                            <?php endif; ?>
                                            
                                        <?php else: ?>
                                            <span class="action-btn disabled">
                                                <i class="fas fa-lock"></i> Yetki Yok
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Hızlı İşlemler -->
        <?php if (has_permission($pdo, 'admin.users.view')): ?>
        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Hızlı İşlemler</h3>
            <div class="quick-actions-grid">
                <a href="?filter=pending" class="quick-action-btn">
                    <i class="fas fa-clock"></i>
                    Onay Bekleyen Kullanıcılar (<?php echo $stats['pending']; ?>)
                </a>
                <a href="/public/admin/manage_roles.php" class="quick-action-btn">
                    <i class="fas fa-user-tag"></i>
                    Rol Yönetimi
                </a>
                <?php if (has_permission($pdo, 'admin.audit_log.view')): ?>
                <a href="/public/admin/audit_log.php?filter=user_management" class="quick-action-btn">
                    <i class="fas fa-clipboard-list"></i>
                    Kullanıcı İşlem Logları
                </a>
                <?php endif; ?>
                <a href="?search=&filter=all" class="quick-action-btn">
                    <i class="fas fa-list"></i>
                    Tüm Kullanıcıları Listele
                </a>
                <?php if ($is_super_admin_user): ?>
                <a href="/public/admin/manage_super_admins.php" class="quick-action-btn">
                    <i class="fas fa-crown"></i>
                    Süper Admin Yönetimi
                </a>
                <?php endif; ?>
                <a href="/public/admin/index.php" class="quick-action-btn">
                    <i class="fas fa-tachometer-alt"></i>
                    Admin Dashboard'a Dön
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rol Düzenleme Modal -->
        <div id="roleModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fas fa-user-tag"></i>
                        <span id="roleModalTitle">Kullanıcı Rollerini Düzenle</span>
                    </h3>
                    <button type="button" class="modal-close" onclick="closeRoleModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="roleForm" method="POST" action="/src/actions/handle_user_roles_update.php">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="user_id_to_update" id="roleUserId">
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-info-circle"></i>
                                Kullanıcı: <strong id="roleUserName"></strong>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Mevcut Roller</label>
                            <div id="currentRoles" class="current-roles-display"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Yönetilebilir Roller</label>
                            <div class="roles-grid" id="rolesGrid">
                                <?php foreach ($manageable_roles as $role): ?>
                                    <label class="role-checkbox-item">
                                        <input type="checkbox" 
                                               name="assigned_roles[]" 
                                               value="<?php echo $role['id']; ?>" 
                                               data-role-name="<?php echo htmlspecialchars($role['name']); ?>"
                                               data-role-color="<?php echo htmlspecialchars($role['color']); ?>">
                                        <div class="role-checkbox-visual" 
                                             style="border-color: <?php echo htmlspecialchars($role['color']); ?>">
                                            <div class="role-color-indicator" 
                                                 style="background-color: <?php echo htmlspecialchars($role['color']); ?>"></div>
                                            <span class="role-name"><?php echo htmlspecialchars($role['name']); ?></span>
                                            <i class="fas fa-check role-check-icon"></i>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php if (!$is_super_admin_user): ?>
                        <div class="warning-note">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Not:</strong> Korumalı roller (admin gibi) ve üst seviye roller bu arayüzden yönetilemez.
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeRoleModal()">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="submit" form="roleForm" class="btn-primary">
                        <i class="fas fa-save"></i> Rolleri Kaydet
                    </button>
                </div>
            </div>
        </div>

        <!-- Toplu İşlemler Modal -->
        <div id="bulkActionModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Toplu İşlem</h3>
                </div>
                <div class="modal-body">
                    <p>Seçilen kullanıcılar üzerinde toplu işlem yapmak istediğinizden emin misiniz?</p>
                    <div id="selectedUsers"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeBulkModal()">İptal</button>
                    <button type="button" class="btn-primary" onclick="executeBulkAction()">Onayla</button>
                </div>
            </div>
        </div>

        <!-- Admin Quick Navigation Include -->
        <?php include_once BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>
    </div>
</main>

<script>
// Rol düzenleme modal fonksiyonları - sayfa başında tanımla
function openRoleModal(userId, username, currentRoles) {
    // Debug log
    console.log('Opening role modal for user:', userId, username, currentRoles);
    
    // User ID'yi set et
    const userIdInput = document.getElementById('roleUserId');
    if (userIdInput) {
        userIdInput.value = userId;
        console.log('Set user ID to:', userId);
    } else {
        console.error('roleUserId input not found!');
        return;
    }
    
    document.getElementById('roleUserName').textContent = username;
    document.getElementById('roleModalTitle').textContent = `${username} - Rolleri Düzenle`;
    
    // Mevcut rolleri göster
    const currentRolesContainer = document.getElementById('currentRoles');
    currentRolesContainer.innerHTML = '';
    
    if (currentRoles && currentRoles.length > 0) {
        currentRoles.forEach(roleName => {
            const badge = document.createElement('span');
            badge.className = 'current-role-badge';
            badge.style.background = 'var(--gold)';
            badge.style.color = 'var(--charcoal)';
            badge.innerHTML = `<i class="fas fa-tag"></i> ${roleName}`;
            currentRolesContainer.appendChild(badge);
        });
    } else {
        const noRoles = document.createElement('span');
        noRoles.textContent = 'Henüz rol atanmamış';
        noRoles.style.color = 'var(--light-grey)';
        noRoles.style.fontStyle = 'italic';
        currentRolesContainer.appendChild(noRoles);
    }
    
    // Checkbox'ları güncelle
    const checkboxes = document.querySelectorAll('#rolesGrid input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        const roleName = checkbox.dataset.roleName;
        checkbox.checked = currentRoles && currentRoles.includes(roleName);
        
        // Visual update
        const visual = checkbox.nextElementSibling;
        if (checkbox.checked) {
            visual.style.borderColor = checkbox.dataset.roleColor;
            visual.style.background = `${checkbox.dataset.roleColor}20`;
        } else {
            visual.style.borderColor = 'var(--darker-gold-1)';
            visual.style.background = 'var(--charcoal)';
        }
    });
    
    document.getElementById('roleModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Modal fonksiyonları
function showBulkModal(action) {
    document.getElementById('bulkActionModal').style.display = 'flex';
}

function closeBulkModal() {
    document.getElementById('bulkActionModal').style.display = 'none';
}

function executeBulkAction() {
    closeBulkModal();
}

// Utility functions
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Panoya kopyalandı');
        });
    }
}

function showToast(message, type = 'info') {
    console.log(`Toast (${type}): ${message}`);
}

// Export functions
function exportUsers(format = 'csv') {
    const users = <?php echo json_encode($users); ?>;
    
    if (format === 'csv') {
        let csv = 'Kullanıcı Adı,Email,Durum,Roller,Kayıt Tarihi\n';
        users.forEach(user => {
            csv += `"${user.username}","${user.email}","${user.status}","${user.roles_list || 'Yok'}","${user.created_at}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kullanicilar_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Tablo satırlarında hover efektleri
    const tableRows = document.querySelectorAll('.users-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.transition = 'transform 0.2s ease';
        });

// Rol düzenleme modal fonksiyonları
function openRoleModal(userId, username, currentRoles) {
    document.getElementById('roleUserId').value = userId;
    document.getElementById('roleUserName').textContent = username;
    document.getElementById('roleModalTitle').textContent = `${username} - Rolleri Düzenle`;
    
    // Mevcut rolleri göster
    const currentRolesContainer = document.getElementById('currentRoles');
    currentRolesContainer.innerHTML = '';
    
    if (currentRoles && currentRoles.length > 0) {
        currentRoles.forEach(roleName => {
            const badge = document.createElement('span');
            badge.className = 'current-role-badge';
            badge.style.background = 'var(--gold)';
            badge.style.color = 'var(--charcoal)';
            badge.innerHTML = `<i class="fas fa-tag"></i> ${roleName}`;
            currentRolesContainer.appendChild(badge);
        });
    } else {
        const noRoles = document.createElement('span');
        noRoles.textContent = 'Henüz rol atanmamış';
        noRoles.style.color = 'var(--light-grey)';
        noRoles.style.fontStyle = 'italic';
        currentRolesContainer.appendChild(noRoles);
    }
    
    // Checkbox'ları güncelle
    const checkboxes = document.querySelectorAll('#rolesGrid input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        const roleName = checkbox.dataset.roleName;
        checkbox.checked = currentRoles && currentRoles.includes(roleName);
    });
    
    document.getElementById('roleModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Form submit handling
document.addEventListener('DOMContentLoaded', function() {
    const roleForm = document.getElementById('roleForm');
    if (roleForm) {
        roleForm.addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('#roleModal .btn-primary');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            submitBtn.disabled = true;
            
            // 10 saniye sonra geri yükle (eğer sayfa yenilenmezse)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });
    }
});
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // Action butonları için loading state
    const actionForms = document.querySelectorAll('form[action*="handle_user_approval"]');
    actionForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
            button.disabled = true;
            
            // 10 saniye sonra geri yükle (eğer sayfa yenilenmezse)
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 10000);
        });
    });

    // Arama kutusunda Enter tuşu
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    }

    // Debug: Arama parametrelerini console'a yazdır
    console.log('Search functionality - Debug info:');
    console.log('Current URL params:', window.location.search);
    console.log('Search input value:', searchInput ? searchInput.value : 'Search input not found');
    console.log('Filter select value:', filterSelect ? filterSelect.value : 'Filter select not found');

    // Filtre değiştiğinde otomatik submit
    const filterSelect = document.querySelector('select[name="filter"]');
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            // Arama kutusunu temizle
            if (searchInput) {
                searchInput.value = '';
            }
            this.closest('form').submit();
        });
    }

    // Rol badge'lerine tıklama ile rol filtreleme
    const roleBadges = document.querySelectorAll('.role-badge');
    roleBadges.forEach(badge => {
        badge.addEventListener('click', function() {
            const roleName = this.textContent.trim();
            if (searchInput && roleName !== 'Rol atanmamış') {
                searchInput.value = roleName;
                searchInput.closest('form').submit();
            }
        });
        
        badge.style.cursor = 'pointer';
        badge.title = 'Bu role sahip kullanıcıları ara';
    });

    // Status badge'lerine tıklama ile durum filtreleme
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.addEventListener('click', function() {
            const status = this.classList[1].replace('status-', '');
            if (filterSelect) {
                filterSelect.value = status;
                if (searchInput) {
                    searchInput.value = '';
                }
                filterSelect.closest('form').submit();
            }
        });
        
        badge.style.cursor = 'pointer';
        badge.title = 'Bu durumda olan kullanıcıları filtrele';
    });

    // Avatarlara tıklama ile kullanıcı detay sayfası (eğer varsa)
    const avatars = document.querySelectorAll('.user-avatar');
    avatars.forEach(avatar => {
        avatar.addEventListener('click', function() {
            const userRow = this.closest('tr');
            const username = userRow.querySelector('.user-details h4').textContent;
            
            // Kullanıcı detay sayfası varsa yönlendir
            // window.location.href = `/public/admin/user_detail.php?username=${encodeURIComponent(username)}`;
            
            // Şimdilik kullanıcı bilgilerini konsola yazdır
            console.log('Kullanıcı detayları:', username);
        });
        
        avatar.style.cursor = 'pointer';
        avatar.title = 'Kullanıcı detaylarını görüntüle';
    });

    // Toplu seçim sistemi (gelişmiş özellik)
    let selectedUsers = [];
    
    function addCheckboxes() {
        const headerRow = document.querySelector('.users-table thead tr');
        const selectAllCheckbox = document.createElement('th');
        selectAllCheckbox.innerHTML = '<input type="checkbox" id="selectAll" title="Tümünü seç">';
        headerRow.insertBefore(selectAllCheckbox, headerRow.firstChild);
        
        const bodyRows = document.querySelectorAll('.users-table tbody tr');
        bodyRows.forEach((row, index) => {
            const checkbox = document.createElement('td');
            const userId = row.querySelector('input[name="user_id"]')?.value || index;
            checkbox.innerHTML = `<input type="checkbox" class="user-checkbox" data-user-id="${userId}">`;
            row.insertBefore(checkbox, row.firstChild);
        });
        
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                updateSelectedUsers();
            });
        });
        
        // Individual checkbox changes
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedUsers);
        });
    }
    
    function updateSelectedUsers() {
        selectedUsers = [];
        document.querySelectorAll('.user-checkbox:checked').forEach(cb => {
            selectedUsers.push(cb.dataset.userId);
        });
        
        // Update select all checkbox state
        const selectAll = document.getElementById('selectAll');
        const totalCheckboxes = document.querySelectorAll('.user-checkbox').length;
        const checkedCheckboxes = selectedUsers.length;
        
        if (checkedCheckboxes === 0) {
            selectAll.indeterminate = false;
            selectAll.checked = false;
        } else if (checkedCheckboxes === totalCheckboxes) {
            selectAll.indeterminate = false;
            selectAll.checked = true;
        } else {
            selectAll.indeterminate = true;
        }
    }

    // Form submit handling
    const roleForm = document.getElementById('roleForm');
    if (roleForm) {
        roleForm.addEventListener('submit', function(e) {
            // User ID kontrolü
            const userId = this.querySelector('input[name="user_id_to_update"]')?.value;
            console.log('Form submit - User ID:', userId);
            
            if (!userId || userId === '') {
                e.preventDefault();
                alert('Kullanıcı ID bulunamadı. Modal kapatılıp tekrar açılacak.');
                closeRoleModal();
                return false;
            }
            
            // En az bir rol seçilmiş mi kontrol et
            const selectedRoles = this.querySelectorAll('input[name="assigned_roles[]"]:checked');
            if (selectedRoles.length === 0) {
                if (!confirm('Hiçbir rol seçilmedi. Bu kullanıcının tüm rollerini kaldırmak istediğinizden emin misiniz?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            const submitBtn = this.querySelector('.btn-primary');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            submitBtn.disabled = true;
            
            // 15 saniye sonra geri yükle (eğer sayfa yenilenmezse)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 15000);
        });
    }

    // Modal kapatma - ESC tuşu ile
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRoleModal();
            closeBulkModal();
        }
        
        // Ctrl+F: Arama kutusuna odaklan
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape: Arama kutusunu temizle
        if (e.key === 'Escape' && searchInput === document.activeElement) {
            searchInput.value = '';
            searchInput.blur();
        }
    });

    // Modal overlay tıklama ile kapatma
    const roleModal = document.getElementById('roleModal');
    if (roleModal) {
        roleModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeRoleModal();
            }
        });
    }

    // Rol checkbox'larında değişiklik tracking
    const roleCheckboxes = document.querySelectorAll('#rolesGrid input[type="checkbox"]');
    roleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Visual feedback
            const visual = this.nextElementSibling;
            if (this.checked) {
                visual.style.borderColor = this.dataset.roleColor;
                visual.style.background = `${this.dataset.roleColor}20`;
            } else {
                visual.style.borderColor = 'var(--darker-gold-1)';
                visual.style.background = 'var(--charcoal)';
            }
        });
    });

    // İstatistikleri güncelleme (sayfa yüklendiğinde animasyon)
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        let currentValue = 0;
        const increment = Math.ceil(finalValue / 20);
        
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(timer);
            }
            stat.textContent = currentValue;
        }, 50);
    });

    // Responsive table handling
    function handleResponsiveTable() {
        const table = document.querySelector('.users-table');
        const container = document.querySelector('.users-table-container');
        
        if (table && container) {
            if (table.scrollWidth > container.clientWidth) {
                container.style.overflowX = 'auto';
                table.style.minWidth = '800px';
            }
        }
    }
    
    window.addEventListener('resize', handleResponsiveTable);
    handleResponsiveTable();

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = this.querySelector('input[name="action"]')?.value;
            let userId = this.querySelector('input[name="user_id"]')?.value;
            
            // Arama formu için özel kontrol - user_id gerekli değil
            const isSearchForm = this.querySelector('input[name="search"]') || this.querySelector('select[name="filter"]');
            if (isSearchForm) {
                return true; // Arama formları için validation yapmayalım
            }
            
            // Rol formu için özel kontrol
            if (this.id === 'roleForm') {
                userId = this.querySelector('input[name="user_id_to_update"]')?.value;
                if (!userId) {
                    e.preventDefault();
                    alert('Kullanıcı ID eksik. Modal kapatılıp tekrar açılacak.');
                    closeRoleModal();
                    return false;
                }
            } else {
                // Diğer formlar için user_id kontrolü
                if (!userId) {
                    e.preventDefault();
                    alert('Kullanıcı ID eksik. Lütfen sayfayı yenileyin.');
                    return false;
                }
            }
            
            // Kritik işlemler için ek onay
            const criticalActions = ['reject', 'suspend', 'delete'];
            if (criticalActions.includes(action)) {
                if (!confirm(`Bu işlem geri alınamaz. ${action} işlemini yapmak istediğinizden emin misiniz?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
});

// Modal fonksiyonları
function showBulkModal(action) {
    // Toplu işlem modal'ını göster (gelecek özellik için)
    document.getElementById('bulkActionModal').style.display = 'flex';
}

function closeBulkModal() {
    document.getElementById('bulkActionModal').style.display = 'none';
}

function executeBulkAction() {
    // Toplu işlem execute (gelecek özellik için)
    closeBulkModal();
}

// Utility functions
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Panoya kopyalandı');
        });
    }
}

function showToast(message, type = 'info') {
    // Toast notification (gelecek özellik için)
    console.log(`Toast (${type}): ${message}`);
}

// Export functions (gelecek özellik için)
function exportUsers(format = 'csv') {
    const users = <?php echo json_encode($users); ?>;
    
    if (format === 'csv') {
        let csv = 'Kullanıcı Adı,Email,Durum,Roller,Kayıt Tarihi\n';
        users.forEach(user => {
            csv += `"${user.username}","${user.email}","${user.status}","${user.roles_list || 'Yok'}","${user.created_at}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kullanicilar_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }
}
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>