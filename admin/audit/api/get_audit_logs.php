<?php
// /admin/audit/api/get_audit_logs.php

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));
}

// Debug: Dosya yollarını kontrol et
if (!file_exists(BASE_PATH . '/src/config/database.php')) {
    echo json_encode(['success' => false, 'message' => 'Database config file not found: ' . BASE_PATH . '/src/config/database.php']);
    exit;
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Debug: PDO bağlantısını kontrol et
if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

// Yetki kontrolü
if (!is_user_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!has_permission($pdo, 'admin.audit_log.view')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    // Parametreleri al
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    
    $sortField = $_GET['sort_field'] ?? 'created_at';
    $sortDirection = ($_GET['sort_direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    // Filtreler
    $search = trim($_GET['search'] ?? '');
    $action = trim($_GET['action'] ?? '');
    $user = trim($_GET['user'] ?? '');
    $targetType = trim($_GET['targetType'] ?? '');
    $ipAddress = trim($_GET['ipAddress'] ?? '');
    $startDate = trim($_GET['startDate'] ?? '');
    $endDate = trim($_GET['endDate'] ?? '');
    
    // Geçerli sıralama alanları
    $validSortFields = ['id', 'user_id', 'action', 'target_type', 'target_id', 'ip_address', 'created_at'];
    if (!in_array($sortField, $validSortFields)) {
        $sortField = 'created_at';
    }
    
    // SQL sorgusu oluştur
    $whereConditions = [];
    $params = [];
    
    // Arama filtresi
    if (!empty($search)) {
        $whereConditions[] = "(
            al.action LIKE :search OR 
            u.username LIKE :search OR 
            al.target_type LIKE :search OR 
            al.ip_address LIKE :search OR
            CAST(al.target_id AS CHAR) LIKE :search
        )";
        $params['search'] = "%$search%";
    }
    
    // Action filtresi
    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }
    
    // User filtresi
    if (!empty($user)) {
        $whereConditions[] = "u.username = :user";
        $params['user'] = $user;
    }
    
    // Target type filtresi
    if (!empty($targetType)) {
        $whereConditions[] = "al.target_type = :target_type";
        $params['target_type'] = $targetType;
    }
    
    // IP adresi filtresi
    if (!empty($ipAddress)) {
        $whereConditions[] = "al.ip_address LIKE :ip_address";
        $params['ip_address'] = "%$ipAddress%";
    }
    
    // Tarih aralığı filtresi
    if (!empty($startDate)) {
        $whereConditions[] = "al.created_at >= :start_date";
        $params['start_date'] = $startDate;
    }
    
    if (!empty($endDate)) {
        $whereConditions[] = "al.created_at <= :end_date";
        $params['end_date'] = $endDate;
    }
    
    // WHERE cümlesini oluştur
    $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Toplam kayıt sayısını al
    $countQuery = "
        SELECT COUNT(*) as total
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
    ";
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $limit);
    
    // Ana sorgu
    $query = "
        SELECT 
            al.id,
            al.user_id,
            u.username,
            al.action,
            al.target_type,
            al.target_id,
            al.old_values,
            al.new_values,
            al.ip_address,
            al.user_agent,
            al.created_at
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.$sortField $sortDirection
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    
    // Parametreleri bağla
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSON değerlerini decode et
    foreach ($logs as &$log) {
        if ($log['old_values']) {
            $log['old_values_parsed'] = json_decode($log['old_values'], true);
        }
        if ($log['new_values']) {
            $log['new_values_parsed'] = json_decode($log['new_values'], true);
        }
    }
    
    // Pagination bilgileri
    $pagination = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'items_per_page' => $limit,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => $pagination,
        'filters_applied' => [
            'search' => $search,
            'action' => $action,
            'user' => $user,
            'target_type' => $targetType,
            'ip_address' => $ipAddress,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

} catch (Exception $e) {
    error_log("Audit logs API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Audit logları yüklenirken hata oluştu'
    ]);
}
?>