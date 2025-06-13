<?php
// /admin/users/api/update_user.php
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

if (!$is_super_admin && !has_permission($pdo, 'admin.users.edit_status')) {
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
    
    // Gerekli alanları kontrol et
    $user_id = intval($input['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı ID']);
        exit;
    }
    
    // Kullanıcının bu kullanıcıyı yönetme yetkisi var mı? (Süper admin bypass)
    if (!$is_super_admin && !can_user_manage_user_roles($pdo, $user_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu kullanıcıyı düzenleme yetkiniz yok']);
        exit;
    }
    
    // Mevcut kullanıcı bilgilerini al
    $current_user_query = "SELECT * FROM users WHERE id = :user_id";
    $current_user_stmt = $pdo->prepare($current_user_query);
    $current_user_stmt->execute([':user_id' => $user_id]);
    $current_user = $current_user_stmt->fetch();
    
    if (!$current_user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    // Güncellenecek alanları hazırla
    $update_fields = [];
    $params = [':user_id' => $user_id];
    
    // Email kontrolü
    if (isset($input['email']) && !empty(trim($input['email']))) {
        $email = trim($input['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Geçersiz email formatı']);
            exit;
        }
        
        // Email benzersizliği kontrolü (sadece farklıysa)
        if ($email !== $current_user['email']) {
            $email_check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
            $email_check_stmt = $pdo->prepare($email_check_query);
            $email_check_stmt->execute([':email' => $email, ':user_id' => $user_id]);
            
            if ($email_check_stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Bu email adresi zaten kullanılıyor']);
                exit;
            }
            
            $update_fields[] = 'email = :email';
            $params[':email'] = $email;
        }
    }
    
    // Oyun içi isim
    if (isset($input['ingame_name']) && !empty(trim($input['ingame_name']))) {
        $ingame_name = trim($input['ingame_name']);
        if ($ingame_name !== $current_user['ingame_name']) {
            $update_fields[] = 'ingame_name = :ingame_name';
            $params[':ingame_name'] = $ingame_name;
        }
    }
    
    // Discord kullanıcı adı (boş olabilir)
    if (isset($input['discord_username'])) {
        $discord_username = trim($input['discord_username']);
        $discord_username = !empty($discord_username) ? $discord_username : null;
        
        if ($discord_username !== $current_user['discord_username']) {
            $update_fields[] = 'discord_username = :discord_username';
            $params[':discord_username'] = $discord_username;
        }
    }
    
    // Profil bilgisi (boş olabilir)
    if (isset($input['profile_info'])) {
        $profile_info = trim($input['profile_info']);
        $profile_info = !empty($profile_info) ? $profile_info : null;
        
        if ($profile_info !== $current_user['profile_info']) {
            $update_fields[] = 'profile_info = :profile_info';
            $params[':profile_info'] = $profile_info;
        }
    }
    
    // Durum kontrolü
    if (isset($input['status'])) {
        $status = $input['status'];
        $valid_statuses = ['pending', 'approved', 'suspended', 'rejected'];
        
        if (!in_array($status, $valid_statuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Geçersiz durum değeri']);
            exit;
        }
        
        if ($status !== $current_user['status']) {
            $update_fields[] = 'status = :status';
            $params[':status'] = $status;
        }
    }
    
    // Hiç güncelleme yapılacak alan yoksa
    if (empty($update_fields)) {
        echo json_encode(['success' => true, 'message' => 'Hiçbir değişiklik yapılmadı']);
        exit;
    }
    
    // Güncelleme sorgusunu hazırla ve çalıştır
    $update_query = "UPDATE users SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = :user_id";
    
    $update_stmt = $pdo->prepare($update_query);
    $result = $update_stmt->execute($params);
    
    if (!$result) {
        throw new Exception('Kullanıcı güncellenemedi');
    }
    
    // Güncellenmiş kullanıcı bilgilerini getir
    $updated_user_query = "
        SELECT 
            id, username, email, ingame_name, discord_username, 
            avatar_path, status, profile_info, created_at, updated_at
        FROM users 
        WHERE id = :user_id
    ";
    $updated_user_stmt = $pdo->prepare($updated_user_query);
    $updated_user_stmt->execute([':user_id' => $user_id]);
    $updated_user = $updated_user_stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Kullanıcı başarıyla güncellendi',
        'user' => $updated_user
    ]);

} catch (PDOException $e) {
    error_log("Update user PDO error: " . $e->getMessage());
    error_log("SQL Query: " . ($update_query ?? 'N/A'));
    error_log("Parameters: " . print_r($params ?? [], true));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Update user general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>