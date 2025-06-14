<?php
// /admin/users/api/add_user_skill.php
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

if (!$is_super_admin && !has_permission($pdo, 'skill_tag.manage_all')) {
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
    $skill_id = intval($input['skill_id'] ?? 0);
    
    if ($user_id <= 0 || $skill_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı veya skill ID']);
        exit;
    }
    
    // Kullanıcının bu kullanıcıyı yönetme yetkisi var mı? (Süper admin bypass)
    if (!$is_super_admin && !can_user_manage_user_roles($pdo, $user_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu kullanıcıya skill tag ekleme yetkiniz yok']);
        exit;
    }
    
    // Kullanıcı ve skill tag var mı kontrol et
    $user_check = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $user_check->execute([':user_id' => $user_id]);
    if (!$user_check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    $skill_check = $pdo->prepare("SELECT id, tag_name FROM skill_tags WHERE id = :skill_id");
    $skill_check->execute([':skill_id' => $skill_id]);
    $skill = $skill_check->fetch();
    if (!$skill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Skill tag bulunamadı']);
        exit;
    }
    
    // Kullanıcının zaten bu skill tag'i var mı?
    $existing_check = $pdo->prepare("SELECT COUNT(*) as count FROM user_skill_tags WHERE user_id = :user_id AND skill_tag_id = :skill_tag_id");
    $existing_check->execute([':user_id' => $user_id, ':skill_tag_id' => $skill_id]);
    if ($existing_check->fetch()['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Kullanıcının bu skill tag\\\'i zaten var']);
        exit;
    }
    
    // Skill tag'i ekle
    $insert_query = "INSERT INTO user_skill_tags (user_id, skill_tag_id) VALUES (:user_id, :skill_tag_id)";
    $insert_stmt = $pdo->prepare($insert_query);
    $result = $insert_stmt->execute([
        ':user_id' => $user_id,
        ':skill_tag_id' => $skill_id
    ]);
    
    if (!$result) {
        throw new Exception('Skill tag eklenemedi');
    }
    
    echo json_encode([
        'success' => true,
        'message' => "'{$skill['tag_name']}' skill tag'i başarıyla eklendi"
    ]);

} catch (PDOException $e) {
    error_log("Add user skill error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Add user skill general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>