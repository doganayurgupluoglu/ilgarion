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

    // Transaction başlat
    $pdo->beginTransaction();

    try {
        // Mevcut kullanıcı bilgilerini al
        $current_user_query = "SELECT * FROM users WHERE id = :user_id FOR UPDATE";
        $current_user_stmt = $pdo->prepare($current_user_query);
        $current_user_stmt->execute([':user_id' => $user_id]);
        $current_user = $current_user_stmt->fetch();
        
        if (!$current_user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
            exit;
        }
        
        // Güncellenecek alanları hazırla (users tablosu için)
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
            if ($email !== $current_user['email']) {
                $email_check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $email_check_stmt = $pdo->prepare($email_check_query);
                $email_check_stmt->execute([':email' => $email, ':user_id' => $user_id]);
                if ($email_check_stmt->fetch()) {
                    throw new Exception('Bu email adresi zaten kullanılıyor');
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
                throw new Exception('Geçersiz durum değeri');
            }
            
            if ($status !== $current_user['status']) {
                $update_fields[] = 'status = :status';
                $params[':status'] = $status;
            }
        }
        
        // users tablosunu güncelle (eğer değişiklik varsa)
        if (!empty($update_fields)) {
            $update_query = "UPDATE users SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = :user_id";
            $update_stmt = $pdo->prepare($update_query);
            if (!$update_stmt->execute($params)) {
                throw new Exception('Kullanıcı temel bilgileri güncellenemedi.');
            }
        }

        // Rolleri güncelle
        if (isset($input['roles_to_add']) && is_array($input['roles_to_add'])) {
            $add_role_stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            foreach ($input['roles_to_add'] as $role_id) {
                if (!$is_super_admin && !can_user_manage_role($pdo, (int)$role_id, $_SESSION['user_id'])) continue;
                $add_role_stmt->execute([':user_id' => $user_id, ':role_id' => (int)$role_id]);
            }
        }
        if (isset($input['roles_to_remove']) && is_array($input['roles_to_remove'])) {
            $remove_role_stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id");
            foreach ($input['roles_to_remove'] as $role_id) {
                if (!$is_super_admin && !can_user_manage_role($pdo, (int)$role_id, $_SESSION['user_id'])) continue;
                $remove_role_stmt->execute([':user_id' => $user_id, ':role_id' => (int)$role_id]);
            }
        }

        // Skill tag'leri güncelle
        if (isset($input['skills_to_add']) && is_array($input['skills_to_add'])) {
            $add_skill_stmt = $pdo->prepare("INSERT IGNORE INTO user_skill_tags (user_id, skill_tag_id) VALUES (:user_id, :skill_tag_id)");
            foreach ($input['skills_to_add'] as $skill_id) {
                $add_skill_stmt->execute([':user_id' => $user_id, ':skill_tag_id' => (int)$skill_id]);
            }
        }
        if (isset($input['skills_to_remove']) && is_array($input['skills_to_remove'])) {
            $remove_skill_stmt = $pdo->prepare("DELETE FROM user_skill_tags WHERE user_id = :user_id AND skill_tag_id = :skill_tag_id");
            foreach ($input['skills_to_remove'] as $skill_id) {
                $remove_skill_stmt->execute([':user_id' => $user_id, ':skill_tag_id' => (int)$skill_id]);
            }
        }
        
        // Transaction'ı onayla
        $pdo->commit();

        // Güncellenmiş kullanıcı bilgilerini getir (isteğe bağlı ama frontend için iyi)
        $updated_user_query = "SELECT id, username, email, ingame_name, discord_username, avatar_path, status, profile_info, created_at, updated_at FROM users WHERE id = :user_id";
        $updated_user_stmt = $pdo->prepare($updated_user_query);
        $updated_user_stmt->execute([':user_id' => $user_id]);
        $updated_user = $updated_user_stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Kullanıcı başarıyla güncellendi',
            'user' => $updated_user
        ]);

    } catch (Exception $e) {
        // Hata durumunda transaction'ı geri al
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Update user error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Bir hata oluştu: ' . $e->getMessage()
        ]);
    }

} catch (PDOException $e) {
    // Bu blok genel veritabanı bağlantı hataları için
    error_log("Update user PDO connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()
    ]);
}
?>