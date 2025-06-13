<?php
// /admin/manage_roles/api/get_role_permissions.php
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
    // Rolü yönetme yetkisi kontrol et (sadece görüntüleme için gevşek kontrol)
    $can_manage = can_user_manage_role($pdo, $role_id);
    
    // Rol yetkilerini getir
    $permissions = get_role_permissions($pdo, $role_id);
    
    // Audit log
    audit_log($pdo, $_SESSION['user_id'], 'role_permissions_viewed', 'role', $role_id, null, [
        'permission_count' => count($permissions),
        'can_manage' => $can_manage
    ]);
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'can_manage' => $can_manage
    ]);
    
} catch (Exception $e) {
    error_log("Get role permissions error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sunucu hatası oluştu']);
}
?>