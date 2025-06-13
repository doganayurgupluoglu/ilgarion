<?php
// /admin/users/api/delete_user.php
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

if (!$is_super_admin && !has_permission($pdo, 'admin.users.delete')) {
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
    
    // User ID kontrolü
    $user_id = intval($input['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı ID']);
        exit;
    }
    
    // Kendini silmeye çalışıyor mu?
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Kendi hesabınızı silemezsiniz']);
        exit;
    }
    
    // Kullanıcının bu kullanıcıyı silme yetkisi var mı? (Süper admin bypass)
    if (!$is_super_admin && !can_user_manage_user_roles($pdo, $user_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu kullanıcıyı silme yetkiniz yok']);
        exit;
    }
    
    // Silinecek kullanıcının var olup olmadığını kontrol et
    $user_check_query = "SELECT id, username, email FROM users WHERE id = :user_id";
    $user_check_stmt = $pdo->prepare($user_check_query);
    $user_check_stmt->execute([':user_id' => $user_id]);
    $user_to_delete = $user_check_stmt->fetch();
    
    if (!$user_to_delete) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    // Transaction başlat
    $pdo->beginTransaction();
    
    try {
        // İlişkili verileri sil (Foreign key constraints nedeniyle)
        
        // 1. User roles sil
        $delete_roles_query = "DELETE FROM user_roles WHERE user_id = :user_id";
        $delete_roles_stmt = $pdo->prepare($delete_roles_query);
        $delete_roles_stmt->execute([':user_id' => $user_id]);
        
        // 2. User skill tags sil
        $delete_skills_query = "DELETE FROM user_skill_tags WHERE user_id = :user_id";
        $delete_skills_stmt = $pdo->prepare($delete_skills_query);
        $delete_skills_stmt->execute([':user_id' => $user_id]);
        
        // 3. User hangar sil (eğer varsa)
        $delete_hangar_query = "DELETE FROM user_hangar WHERE user_id = :user_id";
        $delete_hangar_stmt = $pdo->prepare($delete_hangar_query);
        $delete_hangar_stmt->execute([':user_id' => $user_id]);
        
        // 4. Forum posts ve topics'e NULL set et (cascade yerine)
        $nullify_forum_query = "UPDATE forum_posts SET user_id = NULL WHERE user_id = :user_id";
        $nullify_forum_stmt = $pdo->prepare($nullify_forum_query);
        $nullify_forum_stmt->execute([':user_id' => $user_id]);
        
        $nullify_topics_query = "UPDATE forum_topics SET user_id = NULL WHERE user_id = :user_id";
        $nullify_topics_stmt = $pdo->prepare($nullify_topics_query);
        $nullify_topics_stmt->execute([':user_id' => $user_id]);
        
        // 5. Events'e NULL set et
        $nullify_events_query = "UPDATE events SET created_by_user_id = NULL WHERE created_by_user_id = :user_id";
        $nullify_events_stmt = $pdo->prepare($nullify_events_query);
        $nullify_events_stmt->execute([':user_id' => $user_id]);
        
        // 6. Event participations sil
        $delete_participations_query = "DELETE FROM event_participations WHERE user_id = :user_id";
        $delete_participations_stmt = $pdo->prepare($delete_participations_query);
        $delete_participations_stmt->execute([':user_id' => $user_id]);
        
        // 7. Gallery photos'a NULL set et
        $nullify_gallery_query = "UPDATE gallery_photos SET user_id = NULL WHERE user_id = :user_id";
        $nullify_gallery_stmt = $pdo->prepare($nullify_gallery_query);
        $nullify_gallery_stmt->execute([':user_id' => $user_id]);
        
        // 8. Loadout sets'e NULL set et
        $nullify_loadouts_query = "UPDATE loadout_sets SET user_id = NULL WHERE user_id = :user_id";
        $nullify_loadouts_stmt = $pdo->prepare($nullify_loadouts_query);
        $nullify_loadouts_stmt->execute([':user_id' => $user_id]);
        
        // 9. Son olarak kullanıcıyı sil
        $delete_user_query = "DELETE FROM users WHERE id = :user_id";
        $delete_user_stmt = $pdo->prepare($delete_user_query);
        $result = $delete_user_stmt->execute([':user_id' => $user_id]);
        
        if (!$result) {
            throw new Exception('Kullanıcı silinemedi');
        }
        
        // Transaction'ı tamamla
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Kullanıcı başarıyla silindi',
            'deleted_user' => [
                'id' => $user_to_delete['id'],
                'username' => $user_to_delete['username'],
                'email' => $user_to_delete['email']
            ]
        ]);
        
    } catch (Exception $e) {
        // Transaction'ı geri al
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Delete user PDO error: " . $e->getMessage());
    error_log("User ID: " . ($user_id ?? 'N/A'));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Delete user general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>