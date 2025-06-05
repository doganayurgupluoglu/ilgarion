<?php
// public/gallery/actions/delete_photo.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(dirname(__DIR__))) . '/src/config/database.php';
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
    
    // Parametreleri al
    $photo_id = (int)($_POST['photo_id'] ?? 0);
    
    if ($photo_id <= 0) {
        throw new Exception('Geçersiz fotoğraf ID\'si.');
    }
    
    // Rate limiting
    if (!is_super_admin($pdo, $_SESSION['user_id']) && !check_rate_limit('photo_delete', 5, 300)) {
    throw new Exception('Çok fazla silme girişimi. Lütfen biraz bekleyin.');
}
    
    // Fotoğrafı sil
    $result = delete_photo($pdo, $photo_id, $_SESSION['user_id']);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Başarılı yanıt
    echo json_encode($result);
    
} catch (Exception $e) {
    // Hata logla
    error_log("Gallery photo delete error for user " . ($_SESSION['user_id'] ?? 'unknown') . ": " . $e->getMessage());
    
    // Hata yanıtı
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>  