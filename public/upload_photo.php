<?php
// public/upload_photo.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Upload'da error display kapalı olmalı

try {
    require_once '../src/config/database.php';
    require_once BASE_PATH . '/src/functions/auth_functions.php';
    require_once BASE_PATH . '/src/functions/role_functions.php';
    require_once BASE_PATH . '/src/functions/sql_security_functions.php';
} catch (Exception $e) {
    error_log("Upload file include error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Sistem hatası oluştu.'
    ]);
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
if (!check_rate_limit('photo_upload', 10, 3600)) { // 1 saatte 10 upload
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla fotoğraf yüklüyorsunuz. Lütfen bekleyin.'
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

    if (!has_permission($pdo, 'gallery.upload')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Fotoğraf yükleme yetkiniz bulunmamaktadır.'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Permission check error in upload_photo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Yetki kontrolü sırasında hata oluştu.'
    ]);
    exit;
}

// Input validation
$description = trim($_POST['description'] ?? '');
$visibility = $_POST['visibility'] ?? 'members';

// Visibility validation
if (!in_array($visibility, ['public', 'members'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz görünürlük ayarı.'
    ]);
    exit;
}

// Description validation
if (strlen($description) > 500) {
    echo json_encode([
        'success' => false,
        'message' => 'Açıklama 500 karakterden uzun olamaz.'
    ]);
    exit;
}

// File upload validation
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'Dosya boyutu çok büyük.',
        UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu çok büyük.',
        UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
        UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
        UPLOAD_ERR_NO_TMP_DIR => 'Geçici dizin bulunamadı.',
        UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı.',
        UPLOAD_ERR_EXTENSION => 'Dosya yükleme engellenmiş.'
    ];
    
    $error_message = $upload_errors[$_FILES['photo']['error']] ?? 'Bilinmeyen yükleme hatası.';
    echo json_encode([
        'success' => false,
        'message' => $error_message
    ]);
    exit;
}

$file = $_FILES['photo'];
$current_user_id = $_SESSION['user_id'];

try {
    // Dosya güvenlik kontrolleri
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    // MIME type kontrolü
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.'
        ]);
        exit;
    }

    // Dosya boyutu kontrolü
    if ($file['size'] > $max_file_size) {
        echo json_encode([
            'success' => false,
            'message' => 'Dosya boyutu 5MB\'dan büyük olamaz.'
        ]);
        exit;
    }

    // Dosya adı güvenlik kontrolü
    $original_name = basename($file['name']);
    $path_info = pathinfo($original_name);
    $extension = strtolower($path_info['extension'] ?? '');
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed_extensions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz dosya uzantısı.'
        ]);
        exit;
    }

    // Upload dizinini oluştur
    $upload_dir = BASE_PATH . '/public/uploads/gallery/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Could not create upload directory: " . $upload_dir);
            echo json_encode([
                'success' => false,
                'message' => 'Upload dizini oluşturulamadı.'
            ]);
            exit;
        }
    }

    // Benzersiz dosya adı oluştur
    $timestamp = time();
    $random_string = substr(md5(uniqid(rand(), true)), 0, 8);
    $new_filename = "gallery_user{$current_user_id}_{$timestamp}_{$random_string}.{$extension}";
    $upload_path = $upload_dir . $new_filename;
    $relative_path = 'uploads/gallery/' . $new_filename;

    // Dosyayı yükle
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        error_log("Could not move uploaded file to: " . $upload_path);
        echo json_encode([
            'success' => false,
            'message' => 'Dosya yüklenemedi.'
        ]);
        exit;
    }

    // Görünürlük ayarlarını belirle
    $is_public_no_auth = ($visibility === 'public') ? 1 : 0;
    $is_members_only = ($visibility === 'members') ? 1 : 0;

    // Veritabanına kaydet
    $insert_query = "
        INSERT INTO gallery_photos (user_id, image_path, description, is_public_no_auth, is_members_only)
        VALUES (:user_id, :image_path, :description, :is_public_no_auth, :is_members_only)
    ";
    
    $insert_params = [
        ':user_id' => $current_user_id,
        ':image_path' => $relative_path,
        ':description' => $description,
        ':is_public_no_auth' => $is_public_no_auth,
        ':is_members_only' => $is_members_only
    ];

    $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
    $photo_id = $pdo->lastInsertId();

    if (!$photo_id) {
        // Veritabanı hatası durumunda dosyayı sil
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Fotoğraf veritabanına kaydedilemedi.'
        ]);
        exit;
    }

    // Opsiyonel: Resim boyutlarını alabilir ve thumbnail oluşturabilirsiniz
    $image_info = getimagesize($upload_path);
    $width = $image_info[0] ?? 0;
    $height = $image_info[1] ?? 0;

    // Audit log
    audit_log($pdo, $current_user_id, 'photo_uploaded', 'gallery_photo', $photo_id, null, [
        'file_name' => $new_filename,
        'original_name' => $original_name,
        'file_size' => $file['size'],
        'mime_type' => $mime_type,
        'dimensions' => "{$width}x{$height}",
        'visibility' => $visibility,
        'description_length' => strlen($description)
    ]);

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'Fotoğraf başarıyla yüklendi!',
        'photo_id' => $photo_id,
        'file_path' => $relative_path,
        'file_name' => $new_filename,
        'dimensions' => [
            'width' => $width,
            'height' => $height
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in upload_photo: " . $e->getMessage());
    error_log("User ID: $current_user_id, File: " . ($new_filename ?? 'unknown'));
    
    // Hata durumunda yüklenen dosyayı sil
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("General error in upload_photo: " . $e->getMessage());
    error_log("User ID: $current_user_id, File: " . ($original_name ?? 'unknown'));
    
    // Hata durumunda yüklenen dosyayı sil
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>