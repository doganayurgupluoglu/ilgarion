<?php
// public/admin/index.php - Admin Dashboard

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Oturum kontrolü
if (is_user_logged_in()) {
    check_user_session_validity();
}

// Admin yetkisi kontrolü
require_permission($pdo, 'admin.panel.access');

$page_title = "Admin Dashboard";
$current_user_id = $_SESSION['user_id'];
$is_super_admin_user = is_super_admin($pdo);

// Kullanıcının yetki durumunu al
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

// Dashboard istatistikleri
$stats = [
    'users' => [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'suspended' => 0,
        'rejected' => 0
    ],
    'events' => [
        'active' => 0,
        'past' => 0,
        'total' => 0
    ],
    'roles' => [
        'total' => 0,
        'manageable' => 0
    ],
    'security' => [
        'audit_entries_today' => 0,
        'failed_logins_today' => 0,
        'super_admin_count' => 0
    ]
];

try {
    // Kullanıcı istatistikleri
    if (has_permission($pdo, 'admin.users.view')) {
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM users 
            GROUP BY status
        ");
        $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($user_stats as $stat) {
            $stats['users'][$stat['status']] = $stat['count'];
            $stats['users']['total'] += $stat['count'];
        }
    }
    
    // Etkinlik istatistikleri
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM events 
        GROUP BY status
    ");
    $event_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($event_stats as $stat) {
        $stats['events'][$stat['status']] = $stat['count'];
        $stats['events']['total'] += $stat['count'];
    }
    
    // Rol istatistikleri
    if (has_permission($pdo, 'admin.roles.view')) {
        $stats['roles']['total'] = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        
        // Yönetilebilir rol sayısı
        $user_roles = get_user_roles($pdo, $current_user_id);
        $user_highest_priority = 999;
        foreach ($user_roles as $role) {
            if ($role['priority'] < $user_highest_priority) {
                $user_highest_priority = $role['priority'];
            }
        }
        
        if ($is_super_admin_user) {
            $stats['roles']['manageable'] = $stats['roles']['total'];
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE priority > ?");
            $stmt->execute([$user_highest_priority]);
            $stats['roles']['manageable'] = $stmt->fetchColumn();
        }
    }
    
    // Güvenlik istatistikleri
    if (has_permission($pdo, 'admin.audit_log.view')) {
        $stats['security']['audit_entries_today'] = $pdo->query("
            SELECT COUNT(*) FROM audit_log 
            WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();
        
        $stats['security']['failed_logins_today'] = $pdo->query("
            SELECT COUNT(*) FROM audit_log 
            WHERE action LIKE '%login%' AND action LIKE '%failed%' AND DATE(created_at) = CURDATE()
        ")->fetchColumn();
    }
    
    // Süper admin sayısı
    $super_admin_data = get_system_setting($pdo, 'super_admin_users', []);
    $stats['security']['super_admin_count'] = count($super_admin_data);
    
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Son aktiviteler
$recent_activities = [];
try {
    if (has_permission($pdo, 'admin.audit_log.view')) {
        $stmt = $pdo->query("
            SELECT al.*, u.username 
            FROM audit_log al 
            LEFT JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT 10
        ");
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Recent activities error: " . $e->getMessage());
}

// Bekleyen görevler
$pending_tasks = [];
if (has_permission($pdo, 'admin.users.view')) {
    $pending_approval_count = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    if ($pending_approval_count > 0) {
        $pending_tasks[] = [
            'type' => 'user_approval',
            'count' => $pending_approval_count,
            'message' => "$pending_approval_count kullanıcı onay bekliyor",
            'action_url' => '/public/admin/users.php?filter=pending',
            'priority' => 'high'
        ];
    }
}

// Sistem durumu kontrolü
$system_health = [
    'status' => 'good',
    'issues' => []
];

if ($stats['security']['super_admin_count'] == 0) {
    $system_health['issues'][] = "Hiç süper admin tanımlanmamış";
    $system_health['status'] = 'warning';
}

if ($stats['security']['failed_logins_today'] > 10) {
    $system_health['issues'][] = "Bugün çok fazla başarısız giriş girişimi ({$stats['security']['failed_logins_today']})";
    $system_health['status'] = 'warning';
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.admin-dashboard {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.dashboard-header h1 {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-info-badge {
    background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1));
    padding: 0.5rem 1rem;
    border-radius: 25px;
    border: 1px solid var(--gold);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.super-admin-crown {
    color: #ffd700;
    animation: crown-glow 2s ease-in-out infinite alternate;
}

@keyframes crown-glow {
    from { text-shadow: 0 0 5px #ffd700; }
    to { text-shadow: 0 0 15px #ffd700, 0 0 25px #ffd700; }
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.stats-overview {
    grid-column: 1 / -1;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1.5rem;
    transition: border-color 0.2s ease, transform 0.2s ease;
}

.stat-card:hover {
    border-color: var(--gold);
    transform: translateY(-2px);
}

.stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-card-title {
    color: var(--gold);
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card-icon {
    font-size: 1.5rem;
    color: var(--light-gold);
}

.stat-card-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--lighter-grey);
    margin-bottom: 0.5rem;
}

.stat-card-details {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.8rem;
}

.stat-detail {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--light-grey);
}

.stat-detail.warning {
    color: #ff9800;
}

.stat-detail.success {
    color: var(--turquase);
}

.stat-detail.danger {
    color: #f44336;
}

.dashboard-card {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    overflow: hidden;
}

.dashboard-card-header {
    background-color: rgba(34, 34, 34, 0.5);
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dashboard-card-title {
    color: var(--gold);
    font-size: 1.1rem;
    font-weight: 500;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-card-body {
    padding: 1.25rem;
}

.system-health {
    margin-bottom: 2rem;
}

.health-status {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.health-status.good {
    background-color: rgba(76, 175, 80, 0.1);
    border: 1px solid #4caf50;
    color: #4caf50;
}

.health-status.warning {
    background-color: rgba(255, 152, 0, 0.1);
    border: 1px solid #ff9800;
    color: #ff9800;
}

.health-status.critical {
    background-color: rgba(244, 67, 54, 0.1);
    border: 1px solid #f44336;
    color: #f44336;
}

.health-issues {
    list-style: none;
    padding: 0;
    margin: 0;
}

.health-issues li {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--darker-gold-2);
    color: var(--light-grey);
    font-size: 0.9rem;
}

.health-issues li:last-child {
    border-bottom: none;
}

.pending-tasks {
    margin-bottom: 2rem;
}

.task-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.task-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background-color: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
    border-radius: 6px;
    color: var(--lighter-grey);
}

.task-item.high {
    background-color: rgba(244, 67, 54, 0.1);
    border-color: rgba(244, 67, 54, 0.3);
}

.task-message {
    flex-grow: 1;
    font-size: 0.9rem;
}

.task-action {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
    padding: 0.25rem 0.75rem;
    border: 1px solid var(--turquase);
    border-radius: 4px;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.task-action:hover {
    background-color: var(--turquase);
    color: var(--charcoal);
}

.recent-activities {
    height: 400px;
    overflow-y: auto;
}

.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--darker-gold-2);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--darker-gold-1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 0.8rem;
    flex-shrink: 0;
}

.activity-content {
    flex-grow: 1;
    min-width: 0;
}

.activity-action {
    font-size: 0.9rem;
    color: var(--lighter-grey);
    margin-bottom: 0.25rem;
    line-height: 1.3;
}

.activity-meta {
    font-size: 0.8rem;
    color: var(--light-grey);
    display: flex;
    gap: 1rem;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background-color: var(--grey);
    color: var(--lighter-grey);
    text-decoration: none;
    border-radius: 6px;
    border: 1px solid var(--darker-gold-1);
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.quick-action-btn:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
}

.quick-action-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.permission-indicator {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 10px;
    margin-left: auto;
}

.permission-indicator.granted {
    background-color: var(--turquase);
    color: var(--black);
}

.permission-indicator.denied {
    background-color: var(--red);
    color: var(--white);
}

/* Custom Thin Scrollbar - Theme Compatible */
.dashboard-card-body {
    scrollbar-width: thin;
    scrollbar-color: var(--darker-gold-1) var(--charcoal);
}

.dashboard-card-body::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.dashboard-card-body::-webkit-scrollbar-track {
    background: var(--charcoal);
    border-radius: 3px;
}

.dashboard-card-body::-webkit-scrollbar-thumb {
    background: var(--darker-gold-1);
    border-radius: 3px;
    transition: background-color 0.2s ease;
}

.dashboard-card-body::-webkit-scrollbar-thumb:hover {
    background: var(--gold);
}

.dashboard-card-body::-webkit-scrollbar-corner {
    background: var(--charcoal);
}

/* Enhanced scrollbar for recent activities */
.recent-activities {
    scrollbar-width: thin;
    scrollbar-color: var(--gold) var(--darker-gold-2);
}

.recent-activities::-webkit-scrollbar {
    width: 8px;
}

.recent-activities::-webkit-scrollbar-track {
    background: var(--darker-gold-2);
    border-radius: 4px;
    border: 1px solid var(--charcoal);
}

.recent-activities::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, var(--gold), var(--darker-gold-1));
    border-radius: 4px;
    border: 1px solid var(--darker-gold-2);
    transition: all 0.2s ease;
}

.recent-activities::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, var(--light-gold), var(--gold));
    box-shadow: 0 0 5px rgba(189, 145, 42, 0.3);
}

.recent-activities::-webkit-scrollbar-thumb:active {
    background: var(--light-gold);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .admin-dashboard {
        padding: 1rem 0.75rem;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem;
        justify-content: center;
    }
    
    .user-info-badge {
        text-align: center;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <div class="admin-dashboard">
        <div class="dashboard-header">
            <h1>
                <i class="fas fa-tachometer-alt"></i>
                Admin Dashboard
            </h1>
            <div class="user-info-badge">
                <?php if ($is_super_admin_user): ?>
                    <i class="fas fa-crown super-admin-crown"></i>
                    <span>Süper Admin</span>
                <?php else: ?>
                    <i class="fas fa-user-shield"></i>
                    <span>Administrator</span>
                <?php endif; ?>
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

        <!-- Sistem Durumu -->
        <div class="system-health">
            <h2 class="section-title">Sistem Durumu</h2>
            <div class="health-status <?php echo $system_health['status']; ?>">
                <i class="fas fa-<?php echo $system_health['status'] === 'good' ? 'check-circle' : ($system_health['status'] === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                <span>
                    <?php if ($system_health['status'] === 'good'): ?>
                        Sistem normal çalışıyor
                    <?php elseif ($system_health['status'] === 'warning'): ?>
                        Sistem uyarıları mevcut
                    <?php else: ?>
                        Kritik sistem sorunları var
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if (!empty($system_health['issues'])): ?>
                <ul class="health-issues">
                    <?php foreach ($system_health['issues'] as $issue): ?>
                        <li><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Bekleyen Görevler -->
        <?php if (!empty($pending_tasks)): ?>
        <div class="pending-tasks">
            <h2 class="section-title">Bekleyen Görevler</h2>
            <ul class="task-list">
                <?php foreach ($pending_tasks as $task): ?>
                    <li class="task-item <?php echo $task['priority']; ?>">
                        <span class="task-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($task['message']); ?>
                        </span>
                        <a href="<?php echo htmlspecialchars($task['action_url']); ?>" class="task-action">
                            İşlem Yap
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="stats-overview">
            <h2 class="section-title">Genel İstatistikler</h2>
            <div class="stats-grid">
                <?php if (has_permission($pdo, 'admin.users.view')): ?>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Kullanıcılar</div>
                        <i class="fas fa-users stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['users']['total']); ?></div>
                    <div class="stat-card-details">
                        <div class="stat-detail success">
                            <i class="fas fa-check"></i>
                            <?php echo $stats['users']['approved']; ?> Onaylı
                        </div>
                        <div class="stat-detail warning">
                            <i class="fas fa-clock"></i>
                            <?php echo $stats['users']['pending']; ?> Bekliyor
                        </div>
                        <?php if ($stats['users']['suspended'] > 0): ?>
                        <div class="stat-detail danger">
                            <i class="fas fa-ban"></i>
                            <?php echo $stats['users']['suspended']; ?> Askıda
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Etkinlikler</div>
                        <i class="fas fa-calendar-alt stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['events']['total']); ?></div>
                    <div class="stat-card-details">
                        <div class="stat-detail success">
                            <i class="fas fa-play"></i>
                            <?php echo $stats['events']['active']; ?> Aktif
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-history"></i>
                            <?php echo $stats['events']['past']; ?> Geçmiş
                        </div>
                    </div>
                </div>

                <?php if (has_permission($pdo, 'admin.roles.view')): ?>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Roller</div>
                        <i class="fas fa-user-tag stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['roles']['total']); ?></div>
                    <div class="stat-card-details">
                        <div class="stat-detail success">
                            <i class="fas fa-edit"></i>
                            <?php echo $stats['roles']['manageable']; ?> Yönetilebilir
                        </div>
                        <?php if ($is_super_admin_user): ?>
                        <div class="stat-detail">
                            <i class="fas fa-crown"></i>
                            Tam Yetki
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (has_permission($pdo, 'admin.audit_log.view')): ?>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Güvenlik</div>
                        <i class="fas fa-shield-alt stat-card-icon"></i>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['security']['audit_entries_today']); ?></div>
                    <div class="stat-card-details">
                        <div class="stat-detail">
                            <i class="fas fa-clipboard-list"></i>
                            Bugünkü Log
                        </div>
                        <?php if ($stats['security']['failed_logins_today'] > 0): ?>
                        <div class="stat-detail warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $stats['security']['failed_logins_today']; ?> Başarısız Giriş
                        </div>
                        <?php endif; ?>
                        <div class="stat-detail">
                            <i class="fas fa-crown"></i>
                            <?php echo $stats['security']['super_admin_count']; ?> Süper Admin
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Hızlı Eylemler -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">
                        <i class="fas fa-bolt"></i>
                        Hızlı Eylemler
                    </h3>
                </div>
                <div class="dashboard-card-body">
                    <div class="quick-actions">
                        <a href="/public/admin/users.php" class="quick-action-btn <?php echo has_permission($pdo, 'admin.users.view') ? '' : 'disabled'; ?>">
                            <i class="fas fa-users"></i>
                            Kullanıcı Yönetimi
                            <span class="permission-indicator <?php echo has_permission($pdo, 'admin.users.view') ? 'granted' : 'denied'; ?>">
                                <?php echo has_permission($pdo, 'admin.users.view') ? 'OK' : 'NO'; ?>
                            </span>
                        </a>
                        
                        <a href="/public/admin/manage_roles.php" class="quick-action-btn <?php echo has_permission($pdo, 'admin.roles.view') ? '' : 'disabled'; ?>">
                            <i class="fas fa-user-tag"></i>
                            Rol Yönetimi
                            <span class="permission-indicator <?php echo has_permission($pdo, 'admin.roles.view') ? 'granted' : 'denied'; ?>">
                                <?php echo has_permission($pdo, 'admin.roles.view') ? 'OK' : 'NO'; ?>
                            </span>
                        </a>
                        
                        <a href="/public/admin/events.php" class="quick-action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            Etkinlik Yönetimi
                        </a>
                        
                        <a href="/public/admin/audit_log.php" class="quick-action-btn <?php echo has_permission($pdo, 'admin.audit_log.view') ? '' : 'disabled'; ?>">
                            <i class="fas fa-clipboard-list"></i>
                            Audit Log
                            <span class="permission-indicator <?php echo has_permission($pdo, 'admin.audit_log.view') ? 'granted' : 'denied'; ?>">
                                <?php echo has_permission($pdo, 'admin.audit_log.view') ? 'OK' : 'NO'; ?>
                            </span>
                        </a>
                        
                        <?php if ($is_super_admin_user): ?>
                        <a href="/public/admin/manage_super_admins.php" class="quick-action-btn">
                            <i class="fas fa-crown"></i>
                            Süper Admin Yönetimi
                            <span class="permission-indicator granted">SÜPER</span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="/public/admin/gallery.php" class="quick-action-btn">
                            <i class="fas fa-photo-video"></i>
                            Galeri Yönetimi
                        </a>
                    </div>
                </div>
            </div>

            <!-- Son Aktiviteler -->
            <?php if (has_permission($pdo, 'admin.audit_log.view')): ?>
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">
                        <i class="fas fa-history"></i>
                        Son Aktiviteler
                    </h3>
                    <a href="/public/admin/audit_log.php" style="color: var(--turquase); text-decoration: none; font-size: 0.9rem;">
                        Tümünü Gör
                    </a>
                </div>
                <div class="dashboard-card-body">
                    <div class="recent-activities">
                        <?php if (empty($recent_activities)): ?>
                            <p style="text-align: center; color: var(--light-grey); padding: 2rem 0;">
                                <i class="fas fa-info-circle"></i><br>
                                Henüz aktivite kaydı bulunmuyor.
                            </p>
                        <?php else: ?>
                            <ul class="activity-list">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <li class="activity-item">
                                        <div class="activity-icon">
                                            <?php
                                            $icon = 'fas fa-info';
                                            if (strpos($activity['action'], 'login') !== false) {
                                                $icon = 'fas fa-sign-in-alt';
                                            } elseif (strpos($activity['action'], 'role') !== false) {
                                                $icon = 'fas fa-user-tag';
                                            } elseif (strpos($activity['action'], 'user') !== false) {
                                                $icon = 'fas fa-user';
                                            } elseif (strpos($activity['action'], 'security') !== false) {
                                                $icon = 'fas fa-shield-alt';
                                            }
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-action">
                                                <?php
                                                $action_text = [
                                                    'user_created' => 'Yeni kullanıcı kaydı',
                                                    'user_approved' => 'Kullanıcı onaylandı',
                                                    'user_rejected' => 'Kullanıcı reddedildi',
                                                    'role_created' => 'Yeni rol oluşturuldu',
                                                    'role_updated' => 'Rol güncellendi',
                                                    'role_deleted' => 'Rol silindi',
                                                    'role_assigned' => 'Rol atandı',
                                                    'role_removed' => 'Rol kaldırıldı',
                                                    'login_successful' => 'Başarılı giriş',
                                                    'login_failed' => 'Başarısız giriş',
                                                    'unauthorized_access_attempt' => 'Yetkisiz erişim girişimi',
                                                    'security_violation' => 'Güvenlik ihlali'
                                                ];
                                                
                                                $action = $activity['action'];
                                                $display_action = $action_text[$action] ?? ucfirst(str_replace('_', ' ', $action));
                                                
                                                echo htmlspecialchars($display_action);
                                                
                                                if ($activity['username']) {
                                                    echo ' - ' . htmlspecialchars($activity['username']);
                                                }
                                                ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span>
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?>
                                                </span>
                                                <?php if ($activity['ip_address']): ?>
                                                    <span>
                                                        <i class="fas fa-globe"></i>
                                                        <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Yetki Özeti -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">
                        <i class="fas fa-key"></i>
                        Yetki Durumunuz
                    </h3>
                </div>
                <div class="dashboard-card-body">
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                            <?php if ($is_super_admin_user): ?>
                                <i class="fas fa-crown" style="color: #ffd700;"></i>
                                <strong style="color: #ffd700;">SÜPER ADMİN</strong>
                                <span style="color: var(--light-grey); font-size: 0.9rem;">- Sınırsız yetki</span>
                            <?php else: ?>
                                <i class="fas fa-user-shield" style="color: var(--turquase);"></i>
                                <strong style="color: var(--turquase);">ADMİNİSTRATÖR</strong>
                            <?php endif; ?>
                        </div>
                        
                        <div style="background-color: rgba(42, 189, 168, 0.1); padding: 0.75rem; border-radius: 6px; border: 1px solid rgba(42, 189, 168, 0.3);">
                            <div style="font-size: 0.9rem; color: var(--turquase); margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Toplam Yetki Sayınız: <?php echo count($user_permissions); ?></strong>
                            </div>
                            
                            <?php if (!$is_super_admin_user): ?>
                                <div style="font-size: 0.8rem; color: var(--light-grey);">
                                    Yetki seviyeniz, hangi kullanıcıları ve rolleri yönetebileceğinizi belirler.
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--light-grey);">
                                    Süper Admin olarak tüm sistem kaynaklarına tam erişiminiz var.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.85rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-users" style="color: <?php echo has_permission($pdo, 'admin.users.view') ? 'var(--turquase)' : 'var(--red)'; ?>;"></i>
                            <span>Kullanıcı Yönetimi</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-user-tag" style="color: <?php echo has_permission($pdo, 'admin.roles.view') ? 'var(--turquase)' : 'var(--red)'; ?>;"></i>
                            <span>Rol Yönetimi</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-clipboard-list" style="color: <?php echo has_permission($pdo, 'admin.audit_log.view') ? 'var(--turquase)' : 'var(--red)'; ?>;"></i>
                            <span>Audit Log</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-shield-alt" style="color: <?php echo has_permission($pdo, 'admin.system.security') ? 'var(--turquase)' : 'var(--red)'; ?>;"></i>
                            <span>Sistem Güvenliği</span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--darker-gold-2);">
                        <a href="/src/api/get_user_permissions.php" target="_blank" style="color: var(--turquase); text-decoration: none; font-size: 0.9rem;">
                            <i class="fas fa-external-link-alt"></i>
                            Detaylı Yetki Listesi
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Quick Navigation Include -->
        <?php include_once BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============= CUSTOM SCROLLBAR ENHANCEMENTS =============
    
    // Smooth scrollbar behavior for all dashboard card bodies
    function enhanceScrollbars() {
        const scrollableElements = document.querySelectorAll('.dashboard-card-body, .recent-activities');
        
        scrollableElements.forEach(element => {
            let scrollTimeout;
            
            // Add scrolling class for enhanced visual feedback
            element.addEventListener('scroll', function() {
                this.classList.add('scrolling');
                
                // Remove scrolling class after scroll ends
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    this.classList.remove('scrolling');
                }, 150);
                
                // Add scroll position indicators
                const scrollPercentage = (this.scrollTop / (this.scrollHeight - this.clientHeight)) * 100;
                
                // Add data attribute for CSS targeting
                if (scrollPercentage === 0) {
                    this.setAttribute('data-scroll-position', 'top');
                } else if (scrollPercentage >= 95) {
                    this.setAttribute('data-scroll-position', 'bottom');
                } else {
                    this.setAttribute('data-scroll-position', 'middle');
                }
            });
            
            // Mouse wheel smooth scrolling
            element.addEventListener('wheel', function(e) {
                if (this.scrollHeight > this.clientHeight) {
                    e.preventDefault();
                    
                    const delta = e.deltaY || e.detail || e.wheelDelta;
                    const scrollAmount = delta * 0.5; // Smoother scrolling
                    
                    this.scrollTo({
                        top: this.scrollTop + scrollAmount,
                        behavior: 'smooth'
                    });
                }
            }, { passive: false });
            
            // Touch scrolling enhancements for mobile
            let touchStartY = 0;
            let touchStartScrollTop = 0;
            
            element.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY;
                touchStartScrollTop = this.scrollTop;
                this.classList.add('touch-scrolling');
            }, { passive: true });
            
            element.addEventListener('touchmove', function(e) {
                if (this.scrollHeight > this.clientHeight) {
                    const touchY = e.touches[0].clientY;
                    const deltaY = touchStartY - touchY;
                    const newScrollTop = touchStartScrollTop + deltaY;
                    
                    this.scrollTop = Math.max(0, Math.min(newScrollTop, this.scrollHeight - this.clientHeight));
                }
            }, { passive: true });
            
            element.addEventListener('touchend', function() {
                this.classList.remove('touch-scrolling');
            }, { passive: true });
        });
    }
    
    // Initialize scrollbar enhancements
    enhanceScrollbars();
    
    // ============= ORIGINAL DASHBOARD FUNCTIONALITY =============
    
    // Stat card hover effects
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 8px 25px rgba(189, 145, 42, 0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.boxShadow = 'none';
        });
    });

    // Auto-refresh stats every 30 seconds
    let refreshInterval;
    
    function startAutoRefresh() {
        refreshInterval = setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    }
    
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    }
    
    // Handle page visibility change
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Activity scroll enhancement
    const activityContainer = document.querySelector('.recent-activities');
    if (activityContainer) {
        let isScrolling = false;
        
        activityContainer.addEventListener('scroll', function() {
            isScrolling = true;
            
            setTimeout(function() {
                if (isScrolling) {
                    isScrolling = false;
                    
                    // Add visual feedback for scroll position
                    const scrollPercentage = (this.scrollTop / (this.scrollHeight - this.clientHeight)) * 100;
                    if (scrollPercentage > 90) {
                        // Near bottom - could load more activities here
                        console.log('Near bottom of activities');
                    }
                }
            }, 100);
        });
    }
    
    // Enhanced quick action button interactions
    const quickActionBtns = document.querySelectorAll('.quick-action-btn:not(.disabled)');
    quickActionBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = e.offsetX + 'px';
            ripple.style.top = e.offsetY + 'px';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Health status animations
    const healthStatus = document.querySelector('.health-status');
    if (healthStatus) {
        const statusClass = healthStatus.classList.contains('warning') ? 'warning' : 
                           healthStatus.classList.contains('critical') ? 'critical' : 'good';
        
        if (statusClass === 'warning' || statusClass === 'critical') {
            // Add subtle pulse animation for warnings/critical
            healthStatus.style.animation = 'pulse 2s infinite';
        }
    }
    
    // Notification for pending tasks
    const pendingTasks = document.querySelectorAll('.task-item');
    if (pendingTasks.length > 0) {
        // Could add browser notification here if permission granted
        console.log(`${pendingTasks.length} pending tasks require attention`);
    }
    
    // Real-time updates simulation (in production, use WebSockets or SSE)
    function simulateRealTimeUpdates() {
        const statsValues = document.querySelectorAll('.stat-card-value');
        statsValues.forEach(value => {
            value.addEventListener('animationend', function() {
                this.style.animation = 'none';
            });
        });
    }
    
    simulateRealTimeUpdates();
});

// ============= ENHANCED SCROLLBAR STYLES =============
const style = document.createElement('style');
style.textContent = `
    /* Enhanced scrollbar states */
    .dashboard-card-body.scrolling::-webkit-scrollbar-thumb,
    .recent-activities.scrolling::-webkit-scrollbar-thumb {
        background: var(--light-gold);
        box-shadow: 0 0 8px rgba(189, 145, 42, 0.4);
    }
    
    .dashboard-card-body.touch-scrolling::-webkit-scrollbar-thumb,
    .recent-activities.touch-scrolling::-webkit-scrollbar-thumb {
        background: var(--turquase);
        transition: background-color 0.1s ease;
    }
    
    /* Scroll position indicators */
    .dashboard-card-body[data-scroll-position="top"]::-webkit-scrollbar-track,
    .recent-activities[data-scroll-position="top"]::-webkit-scrollbar-track {
        background: linear-gradient(to bottom, var(--gold), var(--charcoal));
    }
    
    .dashboard-card-body[data-scroll-position="bottom"]::-webkit-scrollbar-track,
    .recent-activities[data-scroll-position="bottom"]::-webkit-scrollbar-track {
        background: linear-gradient(to top, var(--gold), var(--charcoal));
    }
    
    .dashboard-card-body[data-scroll-position="middle"]::-webkit-scrollbar-track,
    .recent-activities[data-scroll-position="middle"]::-webkit-scrollbar-track {
        background: linear-gradient(to bottom, var(--charcoal), var(--darker-gold-2), var(--charcoal));
    }
    
    /* Firefox scrollbar enhancements */
    @supports (scrollbar-width: thin) {
        .dashboard-card-body.scrolling,
        .recent-activities.scrolling {
            scrollbar-color: var(--light-gold) var(--darker-gold-2);
        }
        
        .dashboard-card-body.touch-scrolling,
        .recent-activities.touch-scrolling {
            scrollbar-color: var(--turquase) var(--darker-gold-2);
        }
    }
    
    /* Smooth scrolling behavior */
    .dashboard-card-body,
    .recent-activities {
        scroll-behavior: smooth;
    }
    
    /* Mobile scrollbar adjustments */
    @media (max-width: 768px) {
        .dashboard-card-body::-webkit-scrollbar,
        .recent-activities::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        
        .dashboard-card-body::-webkit-scrollbar-thumb,
        .recent-activities::-webkit-scrollbar-thumb {
            border-radius: 2px;
        }
    }
    
    /* Original styles */
    .quick-action-btn {
        position: relative;
        overflow: hidden;
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background-color: rgba(189, 145, 42, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .stat-card-value {
        transition: all 0.3s ease;
    }
    
    .activity-item:hover {
        background-color: rgba(189, 145, 42, 0.05);
        border-radius: 4px;
        margin: 0 -0.5rem;
        padding: 0.75rem 0.5rem;
    }
    
    .health-status.warning,
    .health-status.critical {
        position: relative;
    }
    
    .health-status.warning::before,
    .health-status.critical::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: inherit;
        opacity: 0.5;
        border-radius: inherit;
        animation: pulse 2s infinite;
    }
`;
document.head.appendChild(style);
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>