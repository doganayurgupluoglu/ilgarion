<?php
// /admin/manage_roles/api/get_role.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.roles.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz rol ID']);
    exit;
}

$role_id = (int)$_GET['id'];

try {
    // Rolü yönetme yetkisi kontrol et
    if (!can_user_manage_role($pdo, $role_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu rolü yönetme yetkiniz yok']);
        exit;
    }
    
    // Rol verilerini getir
    $query = "SELECT id, name, description, color, priority FROM roles WHERE id = :role_id";
    $stmt = execute_safe_query($pdo, $query, [':role_id' => $role_id]);
    $role = $stmt->fetch();
    
    if (!$role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
        exit;
    }
    
    // Audit log
    audit_log($pdo, $_SESSION['user_id'], 'role_viewed', 'role', $role_id, null, [
        'role_name' => $role['name'],
        'action_type' => 'get_role_data'
    ]);
    
    echo json_encode([
        'success' => true,
        'role' => $role
    ]);
    
} catch (Exception $e) {
    error_log("Get role error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sunucu hatası oluştu']);
}
?>