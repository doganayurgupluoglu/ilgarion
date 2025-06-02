<?php
// src/api/toggle_photo_like.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // API'de error display kapalı olmalı

try {
    require_once dirname(__DIR__) . '/../src/config/database.php';
    require_once BASE_PATH . '/src/functions/auth_functions.php';
    require_once BASE_PATH . '/src/functions/role_functions.php';
    require_once BASE_PATH . '/src/functions/sql_security_functions.php';
} catch (Exception $e) {
    error_log("API file include error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sistem hatası oluştu.'
    ]);
    exit;
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece POST istekleri kabul edilir'
    ]);
    exit;
}

// Rate limiting kontrolü
if (!check_rate_limit('photo_like', 30, 60)) { // 1 dakikada 30 like
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla beğeni işlemi yapıyorsunuz. Lütfen bekleyin.'
    ]);
    exit;
}

// Yetki kontrolü
try {
    if (!is_user_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Bu işlem için giriş yapmalısınız.'
        ]);
        exit;
    }

    if (!is_user_approved()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Hesabınızın onaylanmış olması gerekmektedir.'
        ]);
        exit;
    }

    if (!has_permission($pdo, 'gallery.like')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Fotoğraf beğenme yetkiniz bulunmamaktadır.'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Permission check error in toggle_photo_like: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Yetki kontrolü sırasında hata oluştu.'
    ]);
    exit;
}

// Input'u al ve validate et
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz JSON verisi.'
    ]);
    exit;
}

$photo_id = $data['photo_id'] ?? null;
$action = $data['action'] ?? null;

// Input validation
if (!$photo_id || !is_numeric($photo_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir fotoğraf ID\'si gereklidir.'
    ]);
    exit;
}

if (!in_array($action, ['like', 'unlike'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz işlem. Sadece "like" veya "unlike" kabul edilir.'
    ]);
    exit;
}

$photo_id = (int)$photo_id;
$current_user_id = $_SESSION['user_id'];

try {
    // Önce fotoğrafın var olup olmadığını ve görüntüleme yetkisi olup olmadığını kontrol et
    $photo_check_query = "
        SELECT gp.id, gp.user_id, gp.is_public_no_auth, gp.is_members_only,
               u.username as photo_owner
        FROM gallery_photos gp
        JOIN users u ON gp.user_id = u.id
        WHERE gp.id = :photo_id
    ";
    
    $stmt_check = execute_safe_query($pdo, $photo_check_query, [':photo_id' => $photo_id]);
    $photo = $stmt_check->fetch();
    
    if (!$photo) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Fotoğraf bulunamadı.'
        ]);
        exit;
    }

    // Görüntüleme yetkisi kontrolü (basit versiyon)
    $can_view = false;
    
    if ($photo['is_public_no_auth'] == 1) {
        $can_view = true;
    } elseif ($photo['is_members_only'] == 1 && has_permission($pdo, 'gallery.view_approved')) {
        $can_view = true;
    } elseif (has_permission($pdo, 'gallery.manage_all')) {
        $can_view = true; // Admin her şeyi görebilir
    }
    
    // Faction-based görünürlük kontrolü (gelecek için hazır)
    if (!$can_view) {
        $stmt_user_roles = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :user_id");
        $stmt_user_roles->execute([':user_id' => $current_user_id]);
        $user_role_ids = $stmt_user_roles->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($user_role_ids)) {
            $role_placeholders = [];
            $role_params = [];
            foreach ($user_role_ids as $idx => $role_id) {
                $placeholder = ':role_id_' . $idx;
                $role_placeholders[] = $placeholder;
                $role_params[$placeholder] = $role_id;
            }
            
            $visibility_query = "
                SELECT COUNT(*) 
                FROM gallery_photo_visibility_roles 
                WHERE photo_id = :photo_id AND role_id IN (" . implode(',', $role_placeholders) . ")
            ";
            $visibility_params = array_merge([':photo_id' => $photo_id], $role_params);
            $stmt_visibility = execute_safe_query($pdo, $visibility_query, $visibility_params);
            
            if ($stmt_visibility->fetchColumn() > 0) {
                $can_view = true;
            }
        }
    }
    
    if (!$can_view) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu fotoğrafı görüntüleme yetkiniz bulunmamaktadır.'
        ]);
        exit;
    }

    // Mevcut beğeni durumunu kontrol et
    $existing_like_query = "SELECT id FROM gallery_photo_likes WHERE photo_id = :photo_id AND user_id = :user_id";
    $stmt_existing = execute_safe_query($pdo, $existing_like_query, [
        ':photo_id' => $photo_id,
        ':user_id' => $current_user_id
    ]);
    $existing_like = $stmt_existing->fetch();

    $result = ['success' => false, 'message' => ''];

    if ($action === 'like') {
        if ($existing_like) {
            // Zaten beğenmiş
            $result = [
                'success' => true,
                'message' => 'Fotoğraf zaten beğenilmiş.',
                'action' => 'already_liked'
            ];
        } else {
            // Beğeni ekle
            $insert_like_query = "INSERT INTO gallery_photo_likes (photo_id, user_id) VALUES (:photo_id, :user_id)";
            execute_safe_query($pdo, $insert_like_query, [
                ':photo_id' => $photo_id,
                ':user_id' => $current_user_id
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Fotoğraf beğenildi.',
                'action' => 'liked'
            ];
            
            // Audit log
            audit_log($pdo, $current_user_id, 'photo_liked', 'gallery_photo', $photo_id, null, [
                'photo_owner' => $photo['photo_owner'],
                'action' => 'like'
            ]);
        }
    } elseif ($action === 'unlike') {
        if (!$existing_like) {
            // Zaten beğenmemiş
            $result = [
                'success' => true,
                'message' => 'Fotoğraf zaten beğenilmemiş.',
                'action' => 'already_unliked'
            ];
        } else {
            // Beğeniyi kaldır
            $delete_like_query = "DELETE FROM gallery_photo_likes WHERE photo_id = :photo_id AND user_id = :user_id";
            execute_safe_query($pdo, $delete_like_query, [
                ':photo_id' => $photo_id,
                ':user_id' => $current_user_id
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Beğeni kaldırıldı.',
                'action' => 'unliked'
            ];
            
            // Audit log
            audit_log($pdo, $current_user_id, 'photo_unliked', 'gallery_photo', $photo_id, null, [
                'photo_owner' => $photo['photo_owner'],
                'action' => 'unlike'
            ]);
        }
    }

    // Güncel beğeni sayısını al
    $count_query = "SELECT COUNT(*) FROM gallery_photo_likes WHERE photo_id = :photo_id";
    $stmt_count = execute_safe_query($pdo, $count_query, [':photo_id' => $photo_id]);
    $like_count = $stmt_count->fetchColumn();

    $result['like_count'] = (int)$like_count;
    $result['user_has_liked'] = $action === 'like' ? true : false;

    // Başarılı yanıt
    echo json_encode($result);

} catch (PDOException $e) {
    error_log("Database error in toggle_photo_like: " . $e->getMessage());
    error_log("Photo ID: $photo_id, User ID: $current_user_id, Action: $action");
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (SecurityException $e) {
    error_log("Security violation in toggle_photo_like: " . $e->getMessage());
    
    // Güvenlik ihlali audit log
    audit_log($pdo, $current_user_id ?? null, 'security_violation', 'photo_like', $photo_id ?? null, null, [
        'action' => $action ?? 'unknown',
        'error' => $e->getMessage(),
        'ip_address' => get_client_ip()
    ]);
    
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (Exception $e) {
    error_log("General error in toggle_photo_like: " . $e->getMessage());
    error_log("Photo ID: " . ($photo_id ?? 'unknown') . ", User ID: " . ($current_user_id ?? 'unknown'));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>