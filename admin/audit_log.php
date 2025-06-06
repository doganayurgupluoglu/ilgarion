<?php
// public/admin/audit_log.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Yetki kontrolü - audit log görüntüleme yetkisi gerekli
require_permission($pdo, 'admin.audit_log.view');

$page_title = "Audit Log - Sistem İzleme";

// Export yetkisi kontrolü
$can_export = has_permission($pdo, 'admin.audit_log.export');

// Filtreleme parametreleri
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$target_type_filter = $_GET['target_type'] ?? '';
$ip_filter = $_GET['ip_filter'] ?? '';

// Export işlemi
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $can_export) {
    // Export için aynı filtreleri kullan ama limit olmadan
    $export_where_conditions = [];
    $export_params = [];

    if (!empty($action_filter) && validate_sql_input($action_filter, 'general')) {
        $export_where_conditions[] = "action LIKE :action";
        $export_params[':action'] = "%$action_filter%";
    }

    if (!empty($user_filter) && is_numeric($user_filter)) {
        $export_where_conditions[] = "user_id = :user_id";
        $export_params[':user_id'] = (int)$user_filter;
    }

    if (!empty($date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $export_where_conditions[] = "DATE(created_at) >= :date_from";
        $export_params[':date_from'] = $date_from;
    }

    if (!empty($date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $export_where_conditions[] = "DATE(created_at) <= :date_to";
        $export_params[':date_to'] = $date_to;
    }

    if (!empty($target_type_filter) && validate_sql_input($target_type_filter, 'general')) {
        $export_where_conditions[] = "target_type = :target_type";
        $export_params[':target_type'] = $target_type_filter;
    }

    if (!empty($ip_filter) && validate_sql_input($ip_filter, 'general')) {
        $export_where_conditions[] = "ip_address LIKE :ip_address";
        $export_params[':ip_address'] = "%$ip_filter%";
    }

    $export_where_clause = '';
    if (!empty($export_where_conditions)) {
        $export_where_clause = 'WHERE ' . implode(' AND ', $export_where_conditions);
    }

    try {
        $export_query = "
            SELECT 
                al.id,
                al.created_at,
                u.username,
                u.ingame_name,
                al.user_id,
                al.action,
                al.target_type,
                al.target_id,
                al.ip_address,
                al.user_agent,
                al.old_values,
                al.new_values
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            $export_where_clause
            ORDER BY al.created_at DESC
            LIMIT 5000
        ";
        
        $stmt_export = $pdo->prepare($export_query);
        foreach ($export_params as $key => $value) {
            $stmt_export->bindValue($key, $value);
        }
        $stmt_export->execute();
        $export_data = $stmt_export->fetchAll();

        // CSV export
        $filename = 'audit_log_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Tarih/Saat', 'Kullanıcı Adı', 'Oyun İçi İsim', 'Kullanıcı ID', 
            'İşlem', 'Hedef Tip', 'Hedef ID', 'IP Adresi', 'User Agent', 
            'Eski Değerler', 'Yeni Değerler'
        ]);

        // CSV data
        foreach ($export_data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['username'] ?: 'Sistem',
                $row['ingame_name'] ?: '-',
                $row['user_id'] ?: 'NULL',
                $row['action'],
                $row['target_type'] ?: '-',
                $row['target_id'] ?: '-',
                $row['ip_address'] ?: '-',
                $row['user_agent'] ?: '-',
                $row['old_values'] ?: '-',
                $row['new_values'] ?: '-'
            ]);
        }

        fclose($output);
        
        // Export işlemini audit log'a kaydet
        audit_log($pdo, $_SESSION['user_id'], 'audit_log_exported', 'system', null, null, [
            'exported_records' => count($export_data),
            'filters' => array_keys($export_params),
            'filename' => $filename
        ]);
        
        exit;
    } catch (Exception $e) {
        error_log("Audit log export hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Export işlemi sırasında hata oluştu.";
    }
}

// Güvenli filtreleme
$where_conditions = [];
$params = [];

if (!empty($action_filter) && validate_sql_input($action_filter, 'general')) {
    $where_conditions[] = "action LIKE :action";
    $params[':action'] = "%$action_filter%";
}

if (!empty($user_filter) && is_numeric($user_filter)) {
    $where_conditions[] = "user_id = :user_id";
    $params[':user_id'] = (int)$user_filter;
}

if (!empty($date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $where_conditions[] = "DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $where_conditions[] = "DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($target_type_filter) && validate_sql_input($target_type_filter, 'general')) {
    $where_conditions[] = "target_type = :target_type";
    $params[':target_type'] = $target_type_filter;
}

if (!empty($ip_filter) && validate_sql_input($ip_filter, 'general')) {
    $where_conditions[] = "ip_address LIKE :ip_address";
    $params[':ip_address'] = "%$ip_filter%";
}

// WHERE clause oluştur
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Toplam kayıt sayısını al
$count_query = "SELECT COUNT(*) FROM audit_log $where_clause";
try {
    $stmt_count = execute_safe_query($pdo, $count_query, $params);
    $total_records = $stmt_count->fetchColumn();
} catch (Exception $e) {
    error_log("Audit log count hatası: " . $e->getMessage());
    $total_records = 0;
}

$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Audit log kayıtlarını çek
$audit_logs = [];
$query = "
    SELECT 
        al.*,
        u.username,
        u.ingame_name
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    $where_clause
    ORDER BY al.created_at DESC 
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $audit_logs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Audit log çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Audit log verileri yüklenirken hata oluştu.";
}

// Unique action'ları çek (filter için)
$unique_actions = [];
try {
    $actions_query = "SELECT DISTINCT action FROM audit_log ORDER BY action";
    $stmt_actions = execute_safe_query($pdo, $actions_query);
    $unique_actions = $stmt_actions->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Unique actions çekme hatası: " . $e->getMessage());
}

// Unique target types çek
$unique_target_types = [];
try {
    $target_types_query = "SELECT DISTINCT target_type FROM audit_log WHERE target_type IS NOT NULL ORDER BY target_type";
    $stmt_target_types = execute_safe_query($pdo, $target_types_query);
    $unique_target_types = $stmt_target_types->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Unique target types çekme hatası: " . $e->getMessage());
}

// Recent security events (son 24 saat)
$security_events_count = 0;
try {
    $security_query = "SELECT COUNT(*) FROM audit_log WHERE action LIKE '%security%' OR action LIKE '%unauthorized%' OR action LIKE '%violation%' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt_security = execute_safe_query($pdo, $security_query);
    $security_events_count = $stmt_security->fetchColumn();
} catch (Exception $e) {
    error_log("Security events count hatası: " . $e->getMessage());
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.audit-log-container {
    width: 100%;
    max-width: 1600px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
}

.page-header-audit {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.page-title-audit {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.permission-info {
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

.permission-info i {
    color: var(--gold);
}

.security-alert {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.security-alert.low {
    background: linear-gradient(135deg, #27ae60, #229954);
}

.security-alert.medium {
    background: linear-gradient(135deg, #f39c12, #e67e22);
}

.audit-filters {
    background-color: var(--charcoal);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border: 1px solid var(--darker-gold-1);
}

.audit-filters h3 {
    color: var(--light-gold);
    margin: 0 0 15px 0;
    font-size: 1.2rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    color: var(--lighter-grey);
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.filter-control {
    padding: 8px 12px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 4px;
    color: var(--white);
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 15px;
}

.audit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.audit-stat-card {
    background-color: var(--charcoal);
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid var(--gold);
    text-align: center;
}

.audit-stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 5px;
}

.audit-stat-label {
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--charcoal);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.audit-table th,
.audit-table td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2);
    font-size: 0.85rem;
    vertical-align: top;
}

.audit-table th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
}

.audit-table tbody tr:hover {
    background-color: var(--darker-gold-2);
}

.audit-action {
    font-weight: 600;
    color: var(--turquase);
    background-color: rgba(61, 166, 162, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.audit-user {
    color: var(--light-gold);
    font-weight: 500;
}

.audit-target {
    color: var(--lighter-grey);
    font-family: monospace;
    font-size: 0.8rem;
}

.audit-data {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--light-grey);
    font-size: 0.8rem;
    cursor: pointer;
    position: relative;
}

.audit-data:hover {
    background-color: var(--darker-gold-1);
    white-space: normal;
    word-break: break-all;
}

.audit-timestamp {
    color: var(--lighter-grey);
    font-size: 0.8rem;
    white-space: nowrap;
}

.pagination-audit {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 25px;
    padding: 20px;
}

.pagination-info {
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.no-logs {
    text-align: center;
    padding: 60px 20px;
    color: var(--light-grey);
    font-style: italic;
}

.security-badge {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
}

.json-preview {
    background-color: var(--grey);
    padding: 10px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.75rem;
    max-height: 200px;
    overflow-y: auto;
    margin-top: 5px;
    border: 1px solid var(--darker-gold-1);
}

.expand-btn {
    background: none;
    border: none;
    color: var(--gold);
    cursor: pointer;
    font-size: 0.8rem;
    text-decoration: underline;
}

.export-section {
    background-color: var(--darker-gold-2);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.export-info {
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.export-actions {
    display: flex;
    gap: 10px;
}
</style>

<main class="main-content">
    <div class="container admin-container audit-log-container">
        <div class="page-header-audit">
            <h1 class="page-title-audit"><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="header-actions">
                <a href="manage_super_admins.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-crown"></i> Süper Admin Yönetimi
                </a>
                <a href="manage_roles.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Rol Yönetimine Dön
                </a>
            </div>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <div class="permission-info">
            <i class="fas fa-info-circle"></i>
            <span>Bu sayfayı görüntülemek için 'admin.audit_log.view' yetkisine sahip olmanız gerekmektedir.</span>
        </div>

        <!-- Güvenlik durumu -->
        <?php 
        $security_class = 'low';
        $security_message = 'Son 24 saatte güvenlik olayı tespit edilmedi.';
        $security_icon = 'fa-shield-alt';
        
        if ($security_events_count > 10) {
            $security_class = 'high';
            $security_message = "Son 24 saatte {$security_events_count} güvenlik olayı tespit edildi! Acil inceleme gerekebilir.";
            $security_icon = 'fa-exclamation-triangle';
        } elseif ($security_events_count > 0) {
            $security_class = 'medium';
            $security_message = "Son 24 saatte {$security_events_count} güvenlik olayı tespit edildi.";
            $security_icon = 'fa-info-circle';
        }
        ?>
        <div class="security-alert <?php echo $security_class; ?>">
            <i class="fas <?php echo $security_icon; ?>"></i>
            <span><?php echo $security_message; ?></span>
        </div>

        <!-- Export bölümü -->
        <?php if ($can_export): ?>
        <div class="export-section">
            <div class="export-info">
                <i class="fas fa-download"></i>
                <strong>Export İşlemi:</strong> Mevcut filtrelerle eşleşen audit log kayıtlarını CSV formatında dışa aktarabilirsiniz (maksimum 5000 kayıt).
            </div>
            <div class="export-actions">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="btn btn-sm btn-success"
                   onclick="return confirm('Audit log verilerini CSV formatında dışa aktarmak istediğinizden emin misiniz?\n\nBu işlem audit log\'a kaydedilecektir.');">
                    <i class="fas fa-file-csv"></i> CSV Olarak İndir
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="audit-stats">
            <div class="audit-stat-card">
                <div class="audit-stat-number"><?php echo number_format($total_records); ?></div>
                <div class="audit-stat-label">Toplam Kayıt</div>
            </div>
            <div class="audit-stat-card">
                <div class="audit-stat-number"><?php echo $total_pages; ?></div>
                <div class="audit-stat-label">Sayfa</div>
            </div>
            <div class="audit-stat-card">
                <div class="audit-stat-number"><?php echo count($unique_actions); ?></div>
                <div class="audit-stat-label">Farklı İşlem</div>
            </div>
            <div class="audit-stat-card">
                <div class="audit-stat-number"><?php echo count($unique_target_types); ?></div>
                <div class="audit-stat-label">Hedef Tipi</div>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="audit-filters">
            <h3><i class="fas fa-filter"></i> Gelişmiş Filtreler</h3>
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>İşlem:</label>
                        <select name="action" class="filter-control">
                            <option value="">Tüm İşlemler</option>
                            <?php foreach ($unique_actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" 
                                        <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($action); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Hedef Tipi:</label>
                        <select name="target_type" class="filter-control">
                            <option value="">Tüm Tipler</option>
                            <?php foreach ($unique_target_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo $target_type_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Kullanıcı ID:</label>
                        <input type="number" name="user" class="filter-control" 
                               value="<?php echo htmlspecialchars($user_filter); ?>" 
                               placeholder="Kullanıcı ID">
                    </div>
                    <div class="filter-group">
                        <label>IP Adresi:</label>
                        <input type="text" name="ip_filter" class="filter-control" 
                               value="<?php echo htmlspecialchars($ip_filter); ?>" 
                               placeholder="IP adresi">
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Başlangıç Tarihi:</label>
                        <input type="date" name="date_from" class="filter-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Bitiş Tarihi:</label>
                        <input type="date" name="date_to" class="filter-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Sayfa Başına:</label>
                        <select name="per_page" class="filter-control">
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <a href="?" class="btn btn-sm btn-secondary">Temizle</a>
                    <button type="submit" class="btn btn-sm btn-primary">Filtrele</button>
                </div>
            </form>
        </div>

        <?php if (empty($audit_logs)): ?>
            <div class="no-logs">
                <i class="fas fa-search"></i><br>
                Filtreye uygun audit log kaydı bulunamadı.
            </div>
        <?php else: ?>
            <!-- Audit Log Tablosu -->
            <div style="overflow-x: auto;">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Tarih/Saat</th>
                            <th>Kullanıcı</th>
                            <th>İşlem</th>
                            <th>Hedef</th>
                            <th>IP</th>
                            <th>Eski Değerler</th>
                            <th>Yeni Değerler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_logs as $log): ?>
                            <tr>
                                <td class="audit-timestamp">
                                    <?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="audit-user">
                                    <?php if ($log['username']): ?>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                        <?php if ($log['ingame_name']): ?>
                                            <br><small style="color: var(--light-grey);">
                                                (<?php echo htmlspecialchars($log['ingame_name']); ?>)
                                            </small>
                                        <?php endif; ?>
                                        <br><small style="color: var(--light-grey);">ID: <?php echo $log['user_id']; ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--light-grey);">
                                            <?php echo $log['user_id'] ? "ID: {$log['user_id']}" : 'Sistem'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="audit-action"><?php echo htmlspecialchars($log['action']); ?></span>
                                    <?php if (strpos($log['action'], 'security') !== false || strpos($log['action'], 'unauthorized') !== false || strpos($log['action'], 'violation') !== false): ?>
                                        <br><span class="security-badge">Güvenlik</span>
                                    <?php endif; ?>
                                </td>
                                <td class="audit-target">
                                    <?php if ($log['target_type']): ?>
                                        <?php echo htmlspecialchars($log['target_type']); ?>
                                        <?php if ($log['target_id']): ?>
                                            <br>ID: <?php echo htmlspecialchars($log['target_id']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--light-grey);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="audit-target">
                                    <?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?>
                                </td>
                                <td class="audit-data">
                                    <?php if ($log['old_values']): ?>
                                        <button class="expand-btn" onclick="toggleJson(this, 'old')">Göster</button>
                                        <div class="json-preview" style="display: none;">
                                            <pre><?php 
                                            $decoded_old = json_decode($log['old_values'], true);
                                            echo htmlspecialchars(json_encode($decoded_old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                            ?></pre>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--light-grey);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="audit-data">
                                    <?php if ($log['new_values']): ?>
                                        <button class="expand-btn" onclick="toggleJson(this, 'new')">Göster</button>
                                        <div class="json-preview" style="display: none;">
                                            <pre><?php 
                                            $decoded_new = json_decode($log['new_values'], true);
                                            echo htmlspecialchars(json_encode($decoded_new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                            ?></pre>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--light-grey);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-audit">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-chevron-left"></i> Önceki
                    </a>
                <?php endif; ?>
                
                <div class="pagination-info">
                    Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?> 
                    (<?php echo number_format($total_records); ?> kayıt)
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="btn btn-sm btn-outline-secondary">
                        Sonraki <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Yardım Kutusu -->
        <div style="margin-top: 30px; padding: 20px; background-color: var(--darker-gold-2); border-radius: 8px;">
            <h4 style="color: var(--gold); margin: 0 0 15px 0;">Audit Log Türleri ve Açıklamaları:</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <div>
                    <h5 style="color: var(--light-gold); margin: 0 0 10px 0;">Rol Yönetimi:</h5>
                    <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                        <li><strong>role_created/updated/deleted:</strong> Rol CRUD işlemleri</li>
                        <li><strong>role_assigned/removed:</strong> Kullanıcılara rol atama/kaldırma</li>
                        <li><strong>role_permissions_updated:</strong> Rol yetkilerinin değiştirilmesi</li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: var(--light-gold); margin: 0 0 10px 0;">Güvenlik Olayları:</h5>
                    <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                        <li><strong>unauthorized_access_attempt:</strong> Yetkisiz erişim denemeleri</li>
                        <li><strong>permission_check_failed:</strong> Başarısız yetki kontrolleri</li>
                        <li><strong>security_violation:</strong> Güvenlik ihlalleri</li>
                        <li><strong>super_admin_added/removed:</strong> Süper admin değişiklikleri</li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: var(--light-gold); margin: 0 0 10px 0;">Sistem İşlemleri:</h5>
                    <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                        <li><strong>system_setting_updated:</strong> Sistem ayarları değişiklikleri</li>
                        <li><strong>audit_log_exported:</strong> Audit log dışa aktarma</li>
                        <li><strong>invalid_request:</strong> Geçersiz istekler</li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background-color: var(--transparent-gold); border-radius: 4px;">
                <strong style="color: var(--gold);">💡 İpucu:</strong>
                <span style="color: var(--lighter-grey); font-size: 0.9rem;">
                    Güvenlik olaylarını izlemek için "security", "unauthorized" veya "violation" kelimelerini İşlem filtresinde arayabilirsiniz.
                    Belirli bir kullanıcının tüm aktivitelerini görmek için Kullanıcı ID filtresini kullanın.
                </span>
            </div>
        </div>
    </div>
</main>

<script>
function toggleJson(button, type) {
    const jsonDiv = button.nextElementSibling;
    if (jsonDiv.style.display === 'none') {
        jsonDiv.style.display = 'block';
        button.textContent = 'Gizle';
    } else {
        jsonDiv.style.display = 'none';
        button.textContent = 'Göster';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form on select change
    const selects = document.querySelectorAll('.audit-filters select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            // Optional: Auto-submit on filter change
            // this.form.submit();
        });
    });
    
    // Real-time search for IP addresses
    const ipFilter = document.querySelector('input[name="ip_filter"]');
    if (ipFilter) {
        ipFilter.addEventListener('input', function() {
            // Simple IP validation visual feedback
            const value = this.value;
            const isValidIP = /^(\d{1,3}\.){0,3}\d{0,3}$/.test(value) || value === '';
            this.style.borderColor = isValidIP ? '' : 'var(--red)';
        });
    }
    
    // Enhanced security event highlighting
    const securityRows = document.querySelectorAll('.audit-table tbody tr');
    securityRows.forEach(row => {
        const actionCell = row.querySelector('.audit-action');
        if (actionCell && actionCell.textContent.toLowerCase().includes('security')) {
            row.style.borderLeft = '3px solid var(--red)';
            row.style.backgroundColor = 'rgba(231, 76, 60, 0.05)';
        }
    });
    
    // Export confirmation with details
    const exportLinks = document.querySelectorAll('a[href*="export=csv"]');
    exportLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const url = new URL(this.href);
            const params = url.searchParams;
            let filterCount = 0;
            let filterDetails = [];
            
            params.forEach((value, key) => {
                if (value && key !== 'export') {
                    filterCount++;
                    filterDetails.push(`${key}: ${value}`);
                }
            });
            
            const message = `Audit log verilerini CSV formatında dışa aktarmak istediğinizden emin misiniz?\n\n` +
                          `Aktif Filtre Sayısı: ${filterCount}\n` +
                          (filterDetails.length > 0 ? `Filtreler:\n- ${filterDetails.join('\n- ')}\n\n` : '') +
                          `Bu işlem audit log'a kaydedilecektir.\n\n` +
                          `Maksimum 5000 kayıt dışa aktarılacaktır.`;
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+F için filtre alanına odaklan
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const firstFilter = document.querySelector('.filter-control');
            if (firstFilter) firstFilter.focus();
        }
        
        // Escape tuşu ile JSON önizlemelerini kapat
        if (e.key === 'Escape') {
            const openPreviews = document.querySelectorAll('.json-preview[style*="block"]');
            openPreviews.forEach(preview => {
                preview.style.display = 'none';
                const button = preview.previousElementSibling;
                if (button && button.classList.contains('expand-btn')) {
                    button.textContent = 'Göster';
                }
            });
        }
    });
    
    // Tooltip for complex data
    const auditDataCells = document.querySelectorAll('.audit-data');
    auditDataCells.forEach(cell => {
        const content = cell.textContent.trim();
        if (content.length > 50) {
            cell.title = content;
        }
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>