<?php
// /admin/users/api/get_user_skills.php
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
    // Kullanıcının mevcut skill tags'lerini getir
    $current_skills_query = "
        SELECT st.id, st.tag_name
        FROM user_skill_tags ust
        JOIN skill_tags st ON ust.skill_tag_id = st.id
        WHERE ust.user_id = :user_id
        ORDER BY st.tag_name ASC
    ";
    $current_skills_stmt = $pdo->prepare($current_skills_query);
    $current_skills_stmt->execute([':user_id' => $user_id]);
    $current_skills = $current_skills_stmt->fetchAll();
    
    // Kullanıcının sahip olmadığı skill tags'leri getir (eklenebilir skill tags)
    $available_skills_query = "
        SELECT st.id, st.tag_name
        FROM skill_tags st
        WHERE st.id NOT IN (
            SELECT ust.skill_tag_id 
            FROM user_skill_tags ust 
            WHERE ust.user_id = :user_id
        )
        ORDER BY st.tag_name ASC
    ";
    $available_skills_stmt = $pdo->prepare($available_skills_query);
    $available_skills_stmt->execute([':user_id' => $user_id]);
    $available_skills = $available_skills_stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'current_skills' => $current_skills,
        'available_skills' => $available_skills
    ]);

} catch (PDOException $e) {
    error_log("Get user skills error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Get user skills general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
}
?>