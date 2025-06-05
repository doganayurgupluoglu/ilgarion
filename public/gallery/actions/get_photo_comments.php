<?php
// public/gallery/actions/get_photo_comments.php

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
    // Session kontrolü
    check_user_session_validity();
    
    $current_user_id = $_SESSION['user_id'] ?? null;
    
    // Parametreleri al
    $photo_id = (int)($_GET['photo_id'] ?? 0);
    
    if ($photo_id <= 0) {
        throw new Exception('Geçersiz fotoğraf ID\'si.');
    }
    
    // Fotoğraf erişim kontrolü
    $photo = get_photo_details($pdo, $photo_id, $current_user_id);
    if (!$photo) {
        throw new Exception('Fotoğraf bulunamadı veya erişim yetkiniz bulunmamaktadır.');
    }
    
    // Yorumları al
    $comments = get_photo_comments($pdo, $photo_id, $current_user_id);
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    // Hata logla
    error_log("Gallery comments fetch error: " . $e->getMessage());
    
    // Hata yanıtı
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>