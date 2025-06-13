<?php
// /admin/users/api/get_user_roles.php
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

// Yetki kontrolü - Süper admin bypass
if (!is_user_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Süper admin her şeyi yapabilir
$is_super_admin = is_super_admin($pdo, $_SESSION['user_id']);

if (!$is_super_admin && !has_permission($pdo, 'admin.users.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// ID parametresini kontrol et
$user_id = intval($_GET['id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı ID']);
    exit;
}

try {
    // Kullanıcının mevcut rollerini getir
    $current_roles_query = "
        SELECT r.id, r.name, r.description, r.color, r.priority
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = :user_id
        ORDER BY r.priority ASC
    ";
    $current_roles_stmt = $pdo->prepare($current_roles_query);
    $current_roles_stmt->execute([':user_id' => $user_id]);
    $current_roles = $current_roles_stmt->fetchAll();
    
    // Kullanıcının sahip olmadığı rolleri getir (eklenebilir roller)
    $available_roles_query = "
        SELECT r.id, r.name, r.description, r.color, r.priority
        FROM roles r
        WHERE r.id NOT IN (
            SELECT ur.role_id 
            FROM user_roles ur 
            WHERE ur.user_id = :user_id
        )
        ORDER BY r.priority ASC
    ";
    $available_roles_stmt = $pdo->prepare($available_roles_query);
    $available_roles_stmt->execute([':user_id' => $user_id]);
    $available_roles = $available_roles_stmt->fetchAll();
    
    // Yönetilebilir rolleri filtrele (süper admin için tüm roller)
    $manageable_available_roles = [];
    $manageable_current_roles = [];
    
    foreach ($available_roles as $role) {
        if ($is_super_admin || can_user_manage_role($pdo, $role['id'], $_SESSION['user_id'])) {
            $manageable_available_roles[] = $role;
        }
    }
    
    foreach ($current_roles as $role) {
        $role['can_remove'] = $is_super_admin || can_user_manage_role($pdo, $role['id'], $_SESSION['user_id']);
        $manageable_current_roles[] = $role;
    }
    
    echo json_encode([
        'success' => true,
        'current_roles' => $manageable_current_roles,
        'available_roles' => $manageable_available_roles
    ]);

} catch (PDOException $e) {
    error_log("Get user roles error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Get user roles general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
}
?>