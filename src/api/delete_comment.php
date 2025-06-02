<?php
// public/api/delete_comment.php

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

if (!check_rate_limit('comment_delete', 5, 300)) { // 5 dakikada 5 silme
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Çok fazla silme işlemi yapıyorsunuz.']);
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

if (!$comment_id || !is_numeric($comment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçerli bir yorum ID\'si gereklidir.']);
    exit;
}

$comment_id = (int)$comment_id;
$current_user_id = $_SESSION['user_id'];

try {
    // Yorumun var olup olmadığını ve silme yetkisini kontrol et
    $comment_check_query = "
        SELECT c.id, c.user_id, c.comment_text, c.photo_id, u.username
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

    // Silme yetkisi kontrolü
    $can_delete = false;
    $delete_reason = '';
    
    if ($comment['user_id'] == $current_user_id && has_permission($pdo, 'gallery.comment.delete_own')) {
        $can_delete = true;
        $delete_reason = 'own_comment';
    } elseif (has_permission($pdo, 'gallery.comment.delete_all')) {
        $can_delete = true;
        $delete_reason = 'admin_delete';
    }

    if (!$can_delete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu yorumu silme yetkiniz yok.']);
        
        // Yetkisiz silme girişimi audit log
        audit_log($pdo, $current_user_id, 'unauthorized_comment_delete_attempt', 'gallery_comment', $comment_id, null, [
            'comment_author' => $comment['username'],
            'photo_id' => $comment['photo_id']
        ]);
        
        exit;
    }

    // Transaction başlat
    $pdo->beginTransaction();

    try {
        // Önce yorum beğenilerini sil
        $delete_likes_query = "DELETE FROM gallery_comment_likes WHERE comment_id = :comment_id";
        execute_safe_query($pdo, $delete_likes_query, [':comment_id' => $comment_id]);

        // Alt yorumları sil (cascade)
        $delete_replies_query = "DELETE FROM gallery_photo_comments WHERE parent_comment_id = :comment_id";
        execute_safe_query($pdo, $delete_replies_query, [':comment_id' => $comment_id]);

        // Ana yorumu sil
        $delete_comment_query = "DELETE FROM gallery_photo_comments WHERE id = :comment_id";
        $stmt_delete = execute_safe_query($pdo, $delete_comment_query, [':comment_id' => $comment_id]);

        if ($stmt_delete->rowCount() === 0) {
            throw new Exception("Yorum silinemedi.");
        }

        $pdo->commit();

        // Audit log
        audit_log($pdo, $current_user_id, 'comment_deleted', 'gallery_comment', $comment_id, [
            'comment_text' => $comment['comment_text'],
            'comment_author' => $comment['username'],
            'comment_author_id' => $comment['user_id'],
            'photo_id' => $comment['photo_id']
        ], [
            'deleted_by_reason' => $delete_reason
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Yorum başarıyla silindi.',
            'comment_id' => $comment_id
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in delete_comment: " . $e->getMessage());
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu.']);
}
?>