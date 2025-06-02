<?php
// src/api/toggle_comment_like.php

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

if (!check_rate_limit('comment_like', 50, 60)) { // 1 dakikada 50 beğeni
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Çok fazla beğeni işlemi yapıyorsunuz.']);
    exit;
}

try {
    if (!is_user_logged_in() || !is_user_approved() || !has_permission($pdo, 'gallery.comment.like')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmamaktadır.']);
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
$action = $data['action'] ?? null;

if (!$comment_id || !is_numeric($comment_id) || !in_array($action, ['like', 'unlike'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler.']);
    exit;
}

$comment_id = (int)$comment_id;
$current_user_id = $_SESSION['user_id'];

try {
    // Yorumun var olup olmadığını kontrol et
    $comment_check_query = "
        SELECT c.id, c.photo_id, gp.is_public_no_auth, gp.is_members_only
        FROM gallery_photo_comments c
        JOIN gallery_photos gp ON c.photo_id = gp.id
        WHERE c.id = :comment_id
    ";
    
    $stmt_check = execute_safe_query($pdo, $comment_check_query, [':comment_id' => $comment_id]);
    $comment = $stmt_check->fetch();
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Yorum bulunamadı.']);
        exit;
    }

    // Fotoğraf görüntüleme yetkisi kontrolü
    $can_view = false;
    if ($comment['is_public_no_auth'] == 1) {
        $can_view = true;
    } elseif ($comment['is_members_only'] == 1 && is_user_approved()) {
        $can_view = true;
    } elseif (has_permission($pdo, 'gallery.manage_all')) {
        $can_view = true;
    }
    
    if (!$can_view) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu yorumu beğenme yetkiniz yok.']);
        exit;
    }

    // Mevcut beğeni durumunu kontrol et
    $existing_like_query = "SELECT id FROM gallery_comment_likes WHERE comment_id = :comment_id AND user_id = :user_id";
    $stmt_existing = execute_safe_query($pdo, $existing_like_query, [
        ':comment_id' => $comment_id,
        ':user_id' => $current_user_id
    ]);
    $existing_like = $stmt_existing->fetch();

    if ($action === 'like') {
        if (!$existing_like) {
            $insert_like_query = "INSERT INTO gallery_comment_likes (comment_id, user_id) VALUES (:comment_id, :user_id)";
            execute_safe_query($pdo, $insert_like_query, [':comment_id' => $comment_id, ':user_id' => $current_user_id]);
        }
    } elseif ($action === 'unlike') {
        if ($existing_like) {
            $delete_like_query = "DELETE FROM gallery_comment_likes WHERE comment_id = :comment_id AND user_id = :user_id";
            execute_safe_query($pdo, $delete_like_query, [':comment_id' => $comment_id, ':user_id' => $current_user_id]);
        }
    }

    // Güncel beğeni sayısını al
    $count_query = "SELECT COUNT(*) FROM gallery_comment_likes WHERE comment_id = :comment_id";
    $stmt_count = execute_safe_query($pdo, $count_query, [':comment_id' => $comment_id]);
    $like_count = $stmt_count->fetchColumn();

    echo json_encode([
        'success' => true,
        'like_count' => (int)$like_count,
        'user_has_liked' => $action === 'like'
    ]);

} catch (Exception $e) {
    error_log("Error in toggle_comment_like: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu.']);
}
?>