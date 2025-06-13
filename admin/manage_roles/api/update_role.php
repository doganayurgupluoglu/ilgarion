<?php
// /admin/manage_roles/api/update_role.php
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
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.roles.edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

// Form verilerini al ve validate et
$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
$role_name = trim($_POST['role_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$color = trim($_POST['color'] ?? '#bd912a');
$priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 999;

// Validasyon
if (!$role_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rol ID gerekli']);
    exit;
}

if (!validate_role_name($role_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz rol adı formatı']);
    exit;
}

if (!validate_color_code($color)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz renk kodu']);
    exit;
}

if ($priority < 1 || $priority > 999) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Öncelik 1-999 arasında olmalıdır']);
    exit;
}

try {
    // Rolü yönetme yetkisi kontrol et
    if (!can_user_manage_role($pdo, $role_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu rolü düzenleme yetkiniz yok']);
        exit;
    }
    
    // Mevcut rol verilerini al
    $old_role_query = "SELECT name, description, color, priority FROM roles WHERE id = :role_id";
    $stmt_old = execute_safe_query($pdo, $old_role_query, [':role_id' => $role_id]);
    $old_role = $stmt_old->fetch();
    
    if (!$old_role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
        exit;
    }
    
    // Rol adı değişiyorsa ve korumalı rol ise kontrol et
    if ($old_role['name'] !== $role_name && !is_role_name_editable($old_role['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bu rolün adı değiştirilemez']);
        exit;
    }
    
    // Aynı isimde başka rol var mı kontrol et
    $duplicate_query = "SELECT id FROM roles WHERE name = :name AND id != :role_id";
    $stmt_duplicate = execute_safe_query($pdo, $duplicate_query, [
        ':name' => $role_name,
        ':role_id' => $role_id
    ]);
    
    if ($stmt_duplicate->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bu isimde bir rol zaten mevcut']);
        exit;
    }
    
    // Transaction ile güncelle
    $result = execute_safe_transaction($pdo, function($pdo) use ($role_id, $role_name, $description, $color, $priority, $old_role) {
        $update_query = "
            UPDATE roles 
            SET name = :name, description = :description, color = :color, priority = :priority, updated_at = NOW()
            WHERE id = :role_id
        ";
        
        $update_params = [
            ':name' => $role_name,
            ':description' => $description,
            ':color' => $color,
            ':priority' => $priority,
            ':role_id' => $role_id
        ];
        
        $stmt = execute_safe_query($pdo, $update_query, $update_params);
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'role_updated', 'role', $role_id, $old_role, [
            'name' => $role_name,
            'description' => $description,
            'color' => $color,
            'priority' => $priority
        ]);
        
        // Rol güvenlik logu
        log_role_security_event($pdo, 'role_updated', $role_id, [
            'old_values' => $old_role,
            'new_values' => [
                'name' => $role_name,
                'description' => $description,
                'color' => $color,
                'priority' => $priority
            ]
        ]);
        
        return true;
    });
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Rol başarıyla güncellendi'
        ]);
    } else {
        throw new Exception('Rol güncelleme işlemi başarısız');
    }
    
} catch (Exception $e) {
    error_log("Update role error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Rol güncellenirken hata oluştu']);
}
?>