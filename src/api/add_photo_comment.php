<?php
// public/api/add_photo_comment.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
if (!check_rate_limit('photo_comment', 20, 300)) { // 5 dakikada 20 yorum
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla yorum yapıyorsunuz. Lütfen bekleyin.'
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

    if (!has_permission($pdo, 'gallery.comment.create')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Yorum yazma yetkiniz bulunmamaktadır.'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Permission check error in add_photo_comment: " . $e->getMessage());
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
$comment_text = trim($data['comment_text'] ?? '');
$parent_comment_id = $data['parent_comment_id'] ?? null;

// Input validation
if (!$photo_id || !is_numeric($photo_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir fotoğraf ID\'si gereklidir.'
    ]);
    exit;
}

if (empty($comment_text)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Yorum metni boş olamaz.'
    ]);
    exit;
}

if (strlen($comment_text) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Yorum en az 2 karakter uzunluğunda olmalıdır.'
    ]);
    exit;
}

if (strlen($comment_text) > 500) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Yorum 500 karakterden uzun olamaz.'
    ]);
    exit;
}

// Parent comment validation
if ($parent_comment_id !== null && (!is_numeric($parent_comment_id) || $parent_comment_id <= 0)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz üst yorum ID\'si.'
    ]);
    exit;
}

$photo_id = (int)$photo_id;
$parent_comment_id = $parent_comment_id ? (int)$parent_comment_id : null;
$current_user_id = $_SESSION['user_id'];

try {
    // Fotoğrafın var olup olmadığını ve görüntüleme yetkisi olup olmadığını kontrol et
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
    } elseif ($photo['is_members_only'] == 1 && is_user_approved()) {
        $can_view = true;
    } elseif (has_permission($pdo, 'gallery.manage_all')) {
        $can_view = true; // Admin her şeyi görebilir
    }
    
    if (!$can_view) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu fotoğrafa yorum yapma yetkiniz bulunmamaktadır.'
        ]);
        exit;
    }

    // Parent comment kontrolü (eğer belirtilmişse)
    if ($parent_comment_id) {
        $parent_check_query = "SELECT id FROM gallery_photo_comments WHERE id = :parent_id AND photo_id = :photo_id";
        $stmt_parent = execute_safe_query($pdo, $parent_check_query, [
            ':parent_id' => $parent_comment_id,
            ':photo_id' => $photo_id
        ]);
        
        if (!$stmt_parent->fetch()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Yanıtlanacak yorum bulunamadı.'
            ]);
            exit;
        }
    }

    // İçerik filtreleme (basit spam ve kötü söz kontrolü)
    $filtered_text = $comment_text;
    
    // Basit spam kontrolü
    if (preg_match('/(.)\1{10,}/', $filtered_text)) { // Aynı karakterin 10+ kez tekrarı
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Yorum spam içerik olarak algılandı.'
        ]);
        exit;
    }

    // Yorumu ekle
    $insert_query = "
        INSERT INTO gallery_photo_comments (photo_id, user_id, parent_comment_id, comment_text)
        VALUES (:photo_id, :user_id, :parent_comment_id, :comment_text)
    ";
    
    $insert_params = [
        ':photo_id' => $photo_id,
        ':user_id' => $current_user_id,
        ':parent_comment_id' => $parent_comment_id,
        ':comment_text' => $filtered_text
    ];

    $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
    $comment_id = $pdo->lastInsertId();

    if (!$comment_id) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Yorum veritabanına kaydedilemedi.'
        ]);
        exit;
    }

    // Audit log
    audit_log($pdo, $current_user_id, 'photo_comment_added', 'gallery_photo', $photo_id, null, [
        'comment_id' => $comment_id,
        'comment_length' => strlen($filtered_text),
        'photo_owner' => $photo['photo_owner'],
        'is_reply' => $parent_comment_id ? true : false,
        'parent_comment_id' => $parent_comment_id
    ]);

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'Yorum başarıyla eklendi.',
        'comment_id' => $comment_id,
        'photo_id' => $photo_id
    ]);

} catch (PDOException $e) {
    error_log("Database error in add_photo_comment: " . $e->getMessage());
    error_log("Photo ID: $photo_id, User ID: $current_user_id");
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (SecurityException $e) {
    error_log("Security violation in add_photo_comment: " . $e->getMessage());
    
    // Güvenlik ihlali audit log
    audit_log($pdo, $current_user_id ?? null, 'security_violation', 'photo_comment', $photo_id ?? null, null, [
        'error' => $e->getMessage(),
        'comment_text' => substr($comment_text ?? '', 0, 100),
        'ip_address' => get_client_ip()
    ]);
    
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (Exception $e) {
    error_log("General error in add_photo_comment: " . $e->getMessage());
    error_log("Photo ID: " . ($photo_id ?? 'unknown') . ", User ID: " . ($current_user_id ?? 'unknown'));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>