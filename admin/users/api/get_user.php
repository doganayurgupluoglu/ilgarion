<?php
// /admin/users/api/get_user.php
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
if (!is_user_logged_in() || !has_permission($pdo, 'admin.users.view')) {
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
    // Kullanıcı bilgilerini getir
    $user_query = "
        SELECT 
            id,
            username,
            email,
            ingame_name,
            discord_username,
            avatar_path,
            status,
            profile_info,
            created_at,
            updated_at
        FROM users 
        WHERE id = :user_id
    ";
    
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([':user_id' => $user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    // Kullanıcının rollerini getir
    $roles_query = "
        SELECT r.id, r.name, r.description, r.color, r.priority
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = :user_id
        ORDER BY r.priority ASC
    ";
    $roles_stmt = $pdo->prepare($roles_query);
    $roles_stmt->execute([':user_id' => $user_id]);
    $user['roles'] = $roles_stmt->fetchAll();
    
    // Kullanıcının skill tags'lerini getir
    $skills_query = "
        SELECT st.id, st.tag_name
        FROM user_skill_tags ust
        JOIN skill_tags st ON ust.skill_tag_id = st.id
        WHERE ust.user_id = :user_id
        ORDER BY st.tag_name ASC
    ";
    $skills_stmt = $pdo->prepare($skills_query);
    $skills_stmt->execute([':user_id' => $user_id]);
    $user['skills'] = $skills_stmt->fetchAll();
    
    // Kullanıcı düzenlenebilir mi kontrolü
    $user['can_edit'] = can_user_manage_user_roles($pdo, $user_id, $_SESSION['user_id']);
    $user['can_delete'] = can_user_manage_user_roles($pdo, $user_id, $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (PDOException $e) {
    error_log("Get user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Get user general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
}
?>