<?php
// /admin/users/api/add_user_role.php
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

// Sadece POST metoduna izin ver
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST metodu desteklenir']);
    exit;
}

// Yetki kontrolü - Süper admin bypass
if (!is_user_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Süper admin her şeyi yapabilir
$is_super_admin = is_super_admin($pdo, $_SESSION['user_id']);

if (!$is_super_admin && !has_permission($pdo, 'admin.users.assign_roles')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // JSON verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz JSON verisi']);
        exit;
    }
    
    $user_id = intval($input['user_id'] ?? 0);
    $role_id = intval($input['role_id'] ?? 0);
    
    if ($user_id <= 0 || $role_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı veya rol ID']);
        exit;
    }
    
    // Kullanıcının bu kullanıcıyı yönetme yetkisi var mı? (Süper admin bypass)
    if (!$is_super_admin && !can_user_manage_user_roles($pdo, $user_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu kullanıcıya rol atama yetkiniz yok']);
        exit;
    }
    
    // Bu rolü yönetme yetkisi var mı? (Süper admin bypass)
    if (!$is_super_admin && !can_user_manage_role($pdo, $role_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu rolü atama yetkiniz yok']);
        exit;
    }
    
    // Kullanıcı ve rol var mı kontrol et
    $user_check = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $user_check->execute([':user_id' => $user_id]);
    if (!$user_check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    $role_check = $pdo->prepare("SELECT id, name FROM roles WHERE id = :role_id");
    $role_check->execute([':role_id' => $role_id]);
    $role = $role_check->fetch();
    if (!$role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
        exit;
    }
    
    // Kullanıcının zaten bu rolü var mı?
    $existing_check = $pdo->prepare("SELECT COUNT(*) as count FROM user_roles WHERE user_id = :user_id AND role_id = :role_id");
    $existing_check->execute([':user_id' => $user_id, ':role_id' => $role_id]);
    if ($existing_check->fetch()['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Kullanıcının bu rolü zaten var']);
        exit;
    }
    
    // Rolü ekle
    $insert_query = "INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)";
    $insert_stmt = $pdo->prepare($insert_query);
    $result = $insert_stmt->execute([
        ':user_id' => $user_id,
        ':role_id' => $role_id
    ]);
    
    if (!$result) {
        throw new Exception('Rol eklenemedi');
    }
    
    echo json_encode([
        'success' => true,
        'message' => "'{$role['name']}' rolü başarıyla eklendi"
    ]);

} catch (PDOException $e) {
    error_log("Add user role error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Add user role general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>