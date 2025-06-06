<?php
// actions/upload_photo.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/gallery_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

try {
    // Güvenlik kontrolleri
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Geçersiz istek metodu.');
    }
    
    if (!isset($_SESSION['user_id']) || !is_user_approved()) {
        throw new Exception('Bu işlem için giriş yapmanız ve hesabınızın onaylanmış olması gerekmektedir.');
    }
    
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        throw new Exception('Güvenlik token hatası. Sayfayı yenileyin ve tekrar deneyin.');
    }
    
    // Rate limiting
    if (!is_super_admin($pdo, $_SESSION['user_id']) && !check_rate_limit('photo_upload', 5, 3600)) {
    throw new Exception('Çok fazla fotoğraf yükleme girişimi. Lütfen bir saat sonra tekrar deneyin.');
}
    
    // Detaylı dosya kontrolü
    if (!isset($_FILES['photo'])) {
        throw new Exception('Fotoğraf dosyası bulunamadı.');
    }
    
    $file = $_FILES['photo'];
    
    // Upload hata kontrolü
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu sunucu limitini aşıyor.',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi. Lütfen tekrar deneyin.',
            UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Sunucuda geçici klasör bulunamadı.',
            UPLOAD_ERR_CANT_WRITE => 'Dosya sunucuya yazılamadı.',
            UPLOAD_ERR_EXTENSION => 'Dosya uzantısı sunucu tarafından engellenmiş.'
        ];
        
        $message = $error_messages[$file['error']] ?? 'Bilinmeyen dosya hatası: ' . $file['error'];
        throw new Exception($message);
    }
    
    // Dosya varlık kontrolü
    if (!is_uploaded_file($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        throw new Exception('Geçerli bir dosya yüklenmedi.');
    }
    
    // Dosya boyutu kontrolü (10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        throw new Exception('Dosya boyutu çok büyük. Maksimum 10MB olabilir. Dosya boyutu: ' . round($file['size'] / 1024 / 1024, 2) . 'MB');
    }
    
    if ($file['size'] <= 0) {
        throw new Exception('Dosya boyutu geçersiz.');
    }
    
    // MIME type kontrolü
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $detected_type = finfo_file($file_info, $file['tmp_name']);
    finfo_close($file_info);
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    
    if (!in_array($detected_type, $allowed_types)) {
        throw new Exception('Desteklenmeyen dosya formatı: ' . $detected_type . '. Sadece JPG, PNG, GIF, WEBP ve BMP dosyaları yükleyebilirsiniz.');
    }
    
    // Dosya uzantısı kontrolü
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Geçersiz dosya uzantısı: .' . $file_extension);
    }
    
    // Form verilerini al ve temizle
    $form_data = [
        'description' => trim($_POST['description'] ?? ''),
        'visibility' => $_POST['visibility'] ?? 'members_only'
    ];
    
    // Açıklama uzunluk kontrolü
    if (strlen($form_data['description']) > 1000) {
        throw new Exception('Açıklama çok uzun. Maksimum 1000 karakter olabilir.');
    }
    
    // Görünürlük kontrolü
    if (!in_array($form_data['visibility'], ['public', 'members_only'])) {
        $form_data['visibility'] = 'members_only';
    }
    
    // Upload klasörünü kontrol et ve oluştur
    $upload_dir = BASE_PATH . '/uploads/gallery/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Upload klasörü oluşturulamadı.');
        }
    }
    
    if (!is_writable($upload_dir)) {
        throw new Exception('Upload klasörüne yazma izni yok.');
    }
    
    // Benzersiz dosya adı oluştur
    $unique_id = uniqid(mt_rand(), true);
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $filename = "gallery_user{$_SESSION['user_id']}_{$unique_id}.{$file_extension}";
    $file_path = $upload_dir . $filename;
    $relative_path = '/uploads/gallery/' . $filename;
    
    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Dosya sunucuya yüklenemedi. Lütfen tekrar deneyin.');
    }
    
    // Dosyanın gerçekten yüklendiğini kontrol et
    if (!file_exists($file_path) || filesize($file_path) <= 0) {
        // Hatalı dosya varsa sil
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        throw new Exception('Dosya yükleme işlemi başarısız oldu.');
    }
    
    // Veritabanı işlemi - transaction kullan
    $pdo->beginTransaction();
    
    try {
        // Görünürlük ayarları
        $is_public_no_auth = ($form_data['visibility'] === 'public') ? 1 : 0;
        $is_members_only = $is_public_no_auth ? 0 : 1;
        
        // Veritabanına kaydet
        $insert_query = "
            INSERT INTO gallery_photos (user_id, image_path, description, is_public_no_auth, is_members_only, uploaded_at)
            VALUES (:user_id, :image_path, :description, :is_public_no_auth, :is_members_only, NOW())
        ";
        
        $insert_params = [
            ':user_id' => $_SESSION['user_id'],
            ':image_path' => $relative_path,
            ':description' => $form_data['description'],
            ':is_public_no_auth' => $is_public_no_auth,
            ':is_members_only' => $is_members_only
        ];
        
        $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
        $photo_id = $pdo->lastInsertId();
        
        if (!$photo_id) {
            throw new Exception('Veritabanı kayıt hatası.');
        }
        
        // Transaction'ı commit et
        $pdo->commit();
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'gallery_photo_uploaded', 'gallery_photo', $photo_id, null, [
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $detected_type,
            'visibility' => $form_data['visibility']
        ]);
        
        // Başarılı yanıt
        echo json_encode([
            'success' => true,
            'message' => 'Fotoğraf başarıyla yüklendi.',
            'photo_id' => $photo_id,
            'photo_path' => $relative_path,
            'file_size' => $file['size'],
            'file_type' => $detected_type
        ]);
        
    } catch (Exception $db_error) {
        // Transaction'ı rollback et
        $pdo->rollBack();
        
        // Dosyayı sil
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        throw new Exception('Veritabanı hatası: ' . $db_error->getMessage());
    }
    
} catch (Exception $e) {
    // Hata durumunda dosyayı temizle
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Detaylı hata logla
    $error_context = [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'file_info' => $_FILES['photo'] ?? 'no file',
        'post_data' => $_POST,
        'error_message' => $e->getMessage()
    ];
    
    error_log("Gallery upload error: " . json_encode($error_context));
    
    // Hata yanıtı
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>