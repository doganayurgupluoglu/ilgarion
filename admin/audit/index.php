<?php
// /admin/audit/index.php
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
require_once BASE_PATH . '/src/functions/audit_functions.php';

// Yetki kontrolü - Süper admin gerekli
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu sayfaya erişim için giriş yapmalısınız.";
    header('Location: ' . get_auth_base_url() . '/login.php');
    exit;
}

if (!has_permission($pdo, 'admin.audit_log.view')) {
    $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
    header('Location: /index.php');
    exit;
}

$page_title = 'Audit Log Yönetimi';
$additional_css = ['../../css/style.css', '../admin/audit/css/audit.css'];
$additional_js = ['../admin/audit/js/audit.js'];

// Audit istatistiklerini al
try {
    $stats_query = "
        SELECT 
            COUNT(*) as total_logs,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT action) as unique_actions,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h
        FROM audit_log
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Eğer veri yoksa varsayılan değerler
    if (!$stats) {
        $stats = [
            'total_logs' => 0,
            'unique_users' => 0,
            'unique_actions' => 0,
            'last_24h' => 0
        ];
    }
    
    // Action kategorilerini al
    $actions_query = "
        SELECT action, COUNT(*) as count
        FROM audit_log 
        GROUP BY action 
        ORDER BY count DESC 
        LIMIT 10
    ";
    $actions_stmt = $pdo->prepare($actions_query);
    $actions_stmt->execute();
    $top_actions = $actions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // User activity
    $users_query = "
        SELECT COALESCE(u.username, 'System') as username, COUNT(al.id) as activity_count
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY al.user_id, u.username
        ORDER BY activity_count DESC
        LIMIT 5
    ";
    $users_stmt = $pdo->prepare($users_query);
    $users_stmt->execute();
    $top_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Audit stats error: " . $e->getMessage());
    $stats = [
        'total_logs' => 0,
        'unique_users' => 0,
        'unique_actions' => 0,
        'last_24h' => 0
    ];
    $top_actions = [];
    $top_users = [];
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
require_once BASE_PATH . '/src/includes/admin_quick_menu.php';
?>

<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/admin/">Admin Panel</a></li>
            <li class="breadcrumb-item active" aria-current="page">Audit Log</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1 class="page-title">
                    <i class="fas fa-clipboard-list"></i>
                    Audit Log Yönetimi
                </h1>
                <p class="page-subtitle">Sistem aktivitelerini izleyin ve denetleyin</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary" onclick="refreshAuditData()">
                    <i class="fas fa-sync-alt"></i>
                    Yenile
                </button>
                <?php if (has_permission($pdo, 'admin.audit_log.export')): ?>
                <button class="btn btn-secondary" onclick="exportAuditLog()">
                    <i class="fas fa-download"></i>
                    Dışa Aktar
                </button>
                <?php endif; ?>
                <button class="btn btn-outline-secondary" onclick="toggleAdvancedFilters()">
                    <i class="fas fa-filter"></i>
                    Gelişmiş Filtreler
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-list-ol"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total_logs'] ?? 0); ?></div>
                <div class="stat-label">Toplam Log</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-cogs"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['unique_actions'] ?? 0); ?></div>
                <div class="stat-label">Farklı Aktivite</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['last_24h'] ?? 0); ?></div>
                <div class="stat-label">Son 24 Saat</div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Audit Logs Container -->
        <div class="audit-container">
            <div class="audit-header">
                <div class="search-controls">
                    <div class="search-group">
                        <input type="text" id="auditSearch" class="search-input" placeholder="Log ara...">
                        <i class="fas fa-search"></i>
                    </div>
                    <select id="actionFilter" class="filter-select">
                        <option value="">Tüm Aktiviteler</option>
                        <?php foreach ($top_actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action['action']); ?>">
                            <?php echo htmlspecialchars($action['action']); ?> (<?php echo $action['count']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="userFilter" class="filter-select">
                        <option value="">Tüm Kullanıcılar</option>
                        <?php foreach ($top_users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['username'] ?? 'Bilinmeyen'); ?>">
                            <?php echo htmlspecialchars($user['username'] ?? 'Bilinmeyen'); ?> (<?php echo $user['activity_count']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="timeFilter" class="filter-select">
                        <option value="">Tüm Zamanlar</option>
                        <option value="today">Bugün</option>
                        <option value="week">Son 7 Gün</option>
                        <option value="month">Son 30 Gün</option>
                        <option value="custom">Özel Tarih</option>
                    </select>
                </div>
                
                <!-- Advanced Filters (Hidden by default) -->
                <div id="advancedFilters" class="advanced-filters" style="display: none;">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Başlangıç Tarihi:</label>
                            <input type="datetime-local" id="startDate" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label>Bitiş Tarihi:</label>
                            <input type="datetime-local" id="endDate" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label>Target Type:</label>
                            <select id="targetTypeFilter" class="filter-select">
                                <option value="">Tümü</option>
                                <option value="role">Role</option>
                                <option value="user">User</option>
                                <option value="permission">Permission</option>
                                <option value="page">Page</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>IP Adresi:</label>
                            <input type="text" id="ipFilter" class="filter-input" placeholder="IP adresi...">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-sm btn-primary" onclick="applyAdvancedFilters()">
                            <i class="fas fa-filter"></i> Filtrele
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="clearAdvancedFilters()">
                            <i class="fas fa-times"></i> Temizle
                        </button>
                    </div>
                </div>
            </div>

            <div class="audit-content">
                <div class="audit-table-container">
                    <table class="audit-table" id="auditTable">
                        <thead>
                            <tr>
                                <th>
                                    <div class="th-content">
                                        <span>ID</span>
                                        <i class="fas fa-sort sort-icon" onclick="sortTable('id')"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Kullanıcı</span>
                                        <i class="fas fa-sort sort-icon" onclick="sortTable('user')"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Aktivite</span>
                                        <i class="fas fa-sort sort-icon" onclick="sortTable('action')"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Target</span>
                                        <i class="fas fa-sort sort-icon" onclick="sortTable('target')"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>IP Adresi</span>
                                        <i class="fas fa-sort sort-icon" onclick="sortTable('ip')"></i>
                                    </div>
                                </th>
                                <th>
                                    <div class="th-content">
                                        <span>Tarih</span>
                                        <i class="fas fa-sort sort-icon" onclick="sortTable('date')"></i>
                                    </div>
                                </th>
                                <th>Detaylar</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <!-- Veriler AJAX ile yüklenecek -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        <span id="paginationInfo">Yükleniyor...</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="btn btn-sm btn-outline-secondary" id="prevPage" onclick="changePage(-1)">
                            <i class="fas fa-chevron-left"></i> Önceki
                        </button>
                        <div class="page-numbers" id="pageNumbers">
                            <!-- Sayfa numaraları dinamik olarak oluşturulacak -->
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" id="nextPage" onclick="changePage(1)">
                            Sonraki <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="side-panel">
            <!-- Recent Activity -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h3><i class="fas fa-history"></i> Son Aktiviteler</h3>
                </div>
                <div class="panel-card-content">
                    <div class="activity-list" id="recentActivity">
                        <!-- AJAX ile yüklenecek -->
                    </div>
                </div>
            </div>

            <!-- Top Actions -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h3><i class="fas fa-chart-bar"></i> Popüler Aktiviteler</h3>
                </div>
                <div class="panel-card-content">
                    <div class="activity-stats">
                        <?php if (!empty($top_actions)): ?>
                            <?php foreach (array_slice($top_actions, 0, 5) as $action): ?>
                            <div class="activity-stat-item">
                                <div class="activity-stat-name"><?php echo htmlspecialchars($action['action']); ?></div>
                                <div class="activity-stat-bar">
                                    <div class="activity-stat-fill" style="width: <?php echo ($action['count'] / $top_actions[0]['count']) * 100; ?>%"></div>
                                </div>
                                <div class="activity-stat-count"><?php echo number_format($action['count']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--light-grey); padding: 1rem;">
                                <i class="fas fa-chart-bar" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>
                                <div>Henüz aktivite verisi yok</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h3><i class="fas fa-shield-alt"></i> Sistem Durumu</h3>
                </div>
                <div class="panel-card-content">
                    <div class="status-indicators">
                        <div class="status-item">
                            <span class="status-indicator status-success"></span>
                            <span>Audit Logging Aktif</span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator status-success"></span>
                            <span>Veritabanı Bağlantısı</span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator status-warning"></span>
                            <span>Disk Kullanımı: 75%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Audit Detail Modal -->
<div id="auditDetailModal" class="modal large-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="auditDetailTitle">Audit Log Detayları</h2>
            <button class="modal-close" onclick="closeAuditDetailModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-section">
                    <h3>Genel Bilgiler</h3>
                    <div class="detail-info">
                        <div class="detail-row">
                            <label>Log ID:</label>
                            <span id="detailLogId">-</span>
                        </div>
                        <div class="detail-row">
                            <label>Kullanıcı:</label>
                            <span id="detailUser">-</span>
                        </div>
                        <div class="detail-row">
                            <label>Aktivite:</label>
                            <span id="detailAction">-</span>
                        </div>
                        <div class="detail-row">
                            <label>Target Type:</label>
                            <span id="detailTargetType">-</span>
                        </div>
                        <div class="detail-row">
                            <label>Target ID:</label>
                            <span id="detailTargetId">-</span>
                        </div>
                        <div class="detail-row">
                            <label>IP Adresi:</label>
                            <span id="detailIpAddress">-</span>
                        </div>
                        <div class="detail-row">
                            <label>Tarih:</label>
                            <span id="detailCreatedAt">-</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>User Agent</h3>
                    <div class="detail-content">
                        <code id="detailUserAgent">-</code>
                    </div>
                </div>
                
                <div class="detail-section" id="oldValuesSection" style="display: none;">
                    <h3>Önceki Değerler</h3>
                    <div class="detail-content">
                        <pre id="detailOldValues">-</pre>
                    </div>
                </div>
                
                <div class="detail-section" id="newValuesSection" style="display: none;">
                    <h3>Yeni Değerler</h3>
                    <div class="detail-content">
                        <pre id="detailNewValues">-</pre>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAuditDetailModal()">Kapat</button>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Audit Log Dışa Aktar</h2>
            <button class="modal-close" onclick="closeExportModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="exportForm">
                <div class="form-group">
                    <label>Tarih Aralığı:</label>
                    <div class="date-range">
                        <input type="datetime-local" id="exportStartDate" class="form-control" required>
                        <span>-</span>
                        <input type="datetime-local" id="exportEndDate" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Format:</label>
                    <select id="exportFormat" class="form-control">
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                        <option value="xlsx">Excel (XLSX)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Filtreler:</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="includeUserAgent"> User Agent Bilgisi
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="includeValues" checked> Değişiklik Detayları
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="includeIpAddress" checked> IP Adresleri
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeExportModal()">İptal</button>
            <button class="btn btn-primary" onclick="executeExport()">
                <i class="fas fa-download"></i> Dışa Aktar
            </button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Yükleniyor...</span>
    </div>
</div>
<script src="js/audit.js"></script>
<script>
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AuditManager !== 'undefined') {
        AuditManager.init();
    }
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>