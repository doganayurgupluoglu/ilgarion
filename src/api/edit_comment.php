
<?php
// public/api/edit_comment.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    echo json_encode(['success' => false, 'message' => 'Sistem hatası oluştu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit;
}

if (!check_rate_limit('comment_edit', 10, 300)) { // 5 dakikada 10 düzenleme
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Çok fazla düzenleme yapıyorsunuz.']);
    exit;
}

try {
    if (!is_user_logged_in() || !is_user_approved()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Yetki kontrolü hatası.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz JSON verisi.']);
    exit;
}

$comment_id = $data['comment_id'] ?? null;
$comment_text = trim($data['comment_text'] ?? '');

if (!$comment_id || !is_numeric($comment_id) || empty($comment_text) || strlen($comment_text) < 2 || strlen($comment_text) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz yorum verisi.']);
    exit;
}

$comment_id = (int)$comment_id;
$current_user_id = $_SESSION['user_id'];

try {
    // Yorumun var olup olmadığını ve düzenleme yetkisini kontrol et
    $comment_check_query = "
        SELECT c.id, c.user_id, c.comment_text, u.username
        FROM gallery_photo_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = :comment_id
    ";
    
    $stmt_check = execute_safe_query($pdo, $comment_check_query, [':comment_id' => $comment_id]);
    $comment = $stmt_check->fetch();
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Yorum bulunamadı.']);
        exit;
    }

    // Düzenleme yetkisi kontrolü
    $can_edit = false;
    if ($comment['user_id'] == $current_user_id && has_permission($pdo, 'gallery.comment.edit_own')) {
        $can_edit = true;
    } elseif (has_permission($pdo, 'gallery.comment.edit_all')) {
        $can_edit = true;
    }

    if (!$can_edit) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu yorumu düzenleme yetkiniz yok.']);
        exit;
    }

    // Yorumu güncelle
    $update_query = "
        UPDATE gallery_photo_comments 
        SET comment_text = :comment_text, is_edited = 1, updated_at = CURRENT_TIMESTAMP
        WHERE id = :comment_id
    ";
    
    execute_safe_query($pdo, $update_query, [
        ':comment_text' => $comment_text,
        ':comment_id' => $comment_id
    ]);

    // Audit log
    audit_log($pdo, $current_user_id, 'comment_edited', 'gallery_comment', $comment_id, [
        'old_text' => $comment['comment_text']
    ], [
        'new_text' => $comment_text,
        'original_author' => $comment['username']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Yorum başarıyla güncellendi.',
        'comment_id' => $comment_id
    ]);

} catch (Exception $e) {
    error_log("Error in edit_comment: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu.']);
}
?>