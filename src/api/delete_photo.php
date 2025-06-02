<?php
// public/api/delete_photo.php

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
if (!check_rate_limit('photo_delete', 20, 300)) { // 5 dakikada 10 silme işlemi
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla silme işlemi yapıyorsunuz. Lütfen bekleyin.'
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
} catch (Exception $e) {
    error_log("Permission check error in delete_photo: " . $e->getMessage());
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

// Input validation
if (!$photo_id || !is_numeric($photo_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir fotoğraf ID\'si gereklidir.'
    ]);
    exit;
}

$photo_id = (int)$photo_id;
$current_user_id = $_SESSION['user_id'];

try {
    // Fotoğraf bilgilerini al
    $photo_query = "
        SELECT gp.id, gp.user_id, gp.image_path, gp.description,
               u.username as photo_owner
        FROM gallery_photos gp
        JOIN users u ON gp.user_id = u.id
        WHERE gp.id = :photo_id
    ";
    
    $stmt_photo = execute_safe_query($pdo, $photo_query, [':photo_id' => $photo_id]);
    $photo = $stmt_photo->fetch();
    
    if (!$photo) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Fotoğraf bulunamadı.'
        ]);
        exit;
    }

    // Silme yetkisi kontrolü
    $can_delete = false;
    $delete_reason = '';

    // Kendi fotoğrafını silebilir mi?
    if ($photo['user_id'] == $current_user_id && has_permission($pdo, 'gallery.delete_own')) {
        $can_delete = true;
        $delete_reason = 'own_photo';
    }
    
    // Herhangi bir fotoğrafı silebilir mi? (admin/moderatör)
    if (has_permission($pdo, 'gallery.delete_any')) {
        $can_delete = true;
        $delete_reason = 'admin_delete';
    }

    if (!$can_delete) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu fotoğrafı silme yetkiniz bulunmamaktadır.'
        ]);
        
        // Yetkisiz silme girişimi audit log
        audit_log($pdo, $current_user_id, 'unauthorized_photo_delete_attempt', 'gallery_photo', $photo_id, null, [
            'photo_owner' => $photo['photo_owner'],
            'photo_path' => $photo['image_path']
        ]);
        
        exit;
    }

    // Transaction başlat
    $pdo->beginTransaction();

    try {
        // Önce beğenileri sil
        $delete_likes_query = "DELETE FROM gallery_photo_likes WHERE photo_id = :photo_id";
        execute_safe_query($pdo, $delete_likes_query, [':photo_id' => $photo_id]);

        // Visibility role kayıtlarını sil (varsa)
        $delete_visibility_query = "DELETE FROM gallery_photo_visibility_roles WHERE photo_id = :photo_id";
        try {
            execute_safe_query($pdo, $delete_visibility_query, [':photo_id' => $photo_id]);
        } catch (PDOException $e) {
            // Tablo yoksa veya foreign key hatası varsa devam et
            if ($e->getCode() !== '42S02') { // Table doesn't exist
                error_log("Visibility roles delete error (non-critical): " . $e->getMessage());
            }
        }

        // Fotoğraf kaydını sil
        $delete_photo_query = "DELETE FROM gallery_photos WHERE id = :photo_id";
        $stmt_delete = execute_safe_query($pdo, $delete_photo_query, [':photo_id' => $photo_id]);

        if ($stmt_delete->rowCount() === 0) {
            throw new Exception("Fotoğraf veritabanından silinemedi.");
        }

        // Commit transaction
        $pdo->commit();

        // Fiziksel dosyayı sil
        $file_deleted = false;
        $full_file_path = BASE_PATH . '/public/' . $photo['image_path'];
        
        if (file_exists($full_file_path)) {
            if (unlink($full_file_path)) {
                $file_deleted = true;
            } else {
                error_log("Could not delete physical file: " . $full_file_path);
            }
        } else {
            error_log("Physical file not found: " . $full_file_path);
            $file_deleted = true; // Dosya zaten yoksa "başarılı" sayalım
        }

        // Audit log
        audit_log($pdo, $current_user_id, 'photo_deleted', 'gallery_photo', $photo_id, [
            'photo_path' => $photo['image_path'],
            'photo_description' => $photo['description'],
            'photo_owner' => $photo['photo_owner'],
            'photo_owner_id' => $photo['user_id']
        ], [
            'deleted_by_reason' => $delete_reason,
            'file_deleted' => $file_deleted
        ]);

        // Başarılı yanıt
        echo json_encode([
            'success' => true,
            'message' => 'Fotoğraf başarıyla silindi.',
            'file_deleted' => $file_deleted
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error in delete_photo: " . $e->getMessage());
    error_log("Photo ID: $photo_id, User ID: $current_user_id");
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (SecurityException $e) {
    error_log("Security violation in delete_photo: " . $e->getMessage());
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Güvenlik ihlali audit log
    audit_log($pdo, $current_user_id ?? null, 'security_violation', 'photo_delete', $photo_id ?? null, null, [
        'error' => $e->getMessage(),
        'ip_address' => get_client_ip()
    ]);
    
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (Exception $e) {
    error_log("General error in delete_photo: " . $e->getMessage());
    error_log("Photo ID: " . ($photo_id ?? 'unknown') . ", User ID: " . ($current_user_id ?? 'unknown'));
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>