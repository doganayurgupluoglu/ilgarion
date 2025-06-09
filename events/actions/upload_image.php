<?php
// /events/actions/upload_image.php - Markdown editor için resim yükleme

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// JSON response için header ayarla
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Hata raporlama fonksiyonları
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function send_success($message = 'İşlem başarılı', $data = []) {
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

try {
    // Session kontrolü
    check_user_session_validity();
    require_approved_user();
    
    // Sadece POST isteklerini kabul et
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Geçersiz istek metodu', 405);
    }
    
    // CSRF token kontrolü
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token)) {
        send_error('CSRF token eksik', 403);
    }
    
    if (!verify_csrf_token($csrf_token)) {
        send_error('Güvenlik hatası - CSRF token geçersiz', 403);
    }
    
    // Dosya kontrolü
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu çok büyük (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu çok büyük (form limit)',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
            UPLOAD_ERR_NO_FILE => 'Hiç dosya yüklenmedi',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı',
            UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
            UPLOAD_ERR_EXTENSION => 'PHP extension tarafından durduruldu'
        ];
        
        $error_code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error_message = $error_messages[$error_code] ?? 'Bilinmeyen yükleme hatası';
        send_error('Dosya yükleme hatası: ' . $error_message);
    }
    
    $file = $_FILES['image'];
    
    // Dosya boyutu kontrolü (5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        send_error('Dosya boyutu en fazla 5MB olabilir');
    }
    
    // Dosya tipi kontrolü
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    $file_type = $file['type'];
    if (!array_key_exists($file_type, $allowed_types)) {
        send_error('Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir');
    }
    
    // Dosyayı güvenlik için kontrol et
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!array_key_exists($detected_type, $allowed_types)) {
        send_error('Geçersiz dosya tipi tespit edildi');
    }
    
    // Upload klasörü oluştur
    $upload_dir = '../../uploads/events/images/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            send_error('Upload klasörü oluşturulamadı');
        }
    }
    
    // Benzersiz dosya adı oluştur
    $user_id = $_SESSION['user_id'];
    $extension = $allowed_types[$detected_type];
    $filename = 'event_img_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    $web_path = '../../uploads/events/images/' . $filename;
    
    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        send_error('Dosya kaydedilemedi');
    }
    
    // Resim boyutlarını kontrol et ve gerekirse resize et
    $image_info = getimagesize($filepath);
    if ($image_info === false) {
        unlink($filepath); // Geçersiz resim dosyasını sil
        send_error('Geçersiz resim dosyası');
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $max_dimension = 1920; // Maksimum genişlik veya yükseklik
    
    // Eğer resim çok büyükse resize et
    if ($width > $max_dimension || $height > $max_dimension) {
        $resized_path = resizeImage($filepath, $max_dimension, $extension);
        if ($resized_path) {
            unlink($filepath); // Orijinal dosyayı sil
            $filepath = $resized_path;
        }
    }
    
    // Alt text oluştur (dosya adından)
    $alt_text = pathinfo($file['name'], PATHINFO_FILENAME);
    $alt_text = preg_replace('/[^a-zA-Z0-9\s]/', '', $alt_text);
    $alt_text = trim($alt_text) ?: 'Yüklenen resim';
    
    // Başarılı response
    send_success('Resim başarıyla yüklendi', [
        'url' => $web_path,
        'filename' => $filename,
        'alt_text' => $alt_text,
        'size' => filesize($filepath),
        'dimensions' => [
            'width' => $image_info[0],
            'height' => $image_info[1]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Image upload error: " . $e->getMessage());
    send_error('Resim yükleme sırasında bir hata oluştu');
}

/**
 * Resmi yeniden boyutlandır
 */
function resizeImage($source_path, $max_dimension, $extension) {
    $image_info = getimagesize($source_path);
    if (!$image_info) return false;
    
    $width = $image_info[0];
    $height = $image_info[1];
    
    // Yeni boyutları hesapla
    if ($width > $height) {
        $new_width = $max_dimension;
        $new_height = intval($height * ($max_dimension / $width));
    } else {
        $new_height = $max_dimension;
        $new_width = intval($width * ($max_dimension / $height));
    }
    
    // Kaynak resmi yükle
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        case 'webp':
            $source_image = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source_image) return false;
    
    // Yeni resim oluştur
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // PNG ve GIF için transparanlık koruması
    if ($extension === 'png' || $extension === 'gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefill($new_image, 0, 0, $transparent);
    }
    
    // Resmi yeniden boyutlandır
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, 
                      $new_width, $new_height, $width, $height);
    
    // Yeni dosyayı kaydet
    $new_path = $source_path; // Aynı dosyanın üzerine yaz
    $quality = 85; // JPEG kalitesi
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($new_image, $new_path, $quality);
            break;
        case 'png':
            $result = imagepng($new_image, $new_path, 6); // 0-9 arası sıkıştırma
            break;
        case 'gif':
            $result = imagegif($new_image, $new_path);
            break;
        case 'webp':
            $result = imagewebp($new_image, $new_path, $quality);
            break;
        default:
            $result = false;
    }
    
    // Belleği temizle
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $result ? $new_path : false;
}
?>