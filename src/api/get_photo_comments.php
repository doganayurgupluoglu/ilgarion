<?php
// public/api/get_photo_comments.php

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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece GET isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece GET istekleri kabul edilir'
    ]);
    exit;
}

// Parametreleri al ve validate et
$photo_id = $_GET['photo_id'] ?? null;

if (!$photo_id || !is_numeric($photo_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir fotoğraf ID\'si gereklidir.'
    ]);
    exit;
}

$photo_id = (int)$photo_id;
$current_user_id = $_SESSION['user_id'] ?? null;

try {
    // Önce fotoğrafın var olup olmadığını ve görüntüleme yetkisi olup olmadığını kontrol et
    $photo_check_query = "
        SELECT gp.id, gp.user_id, gp.is_public_no_auth, gp.is_members_only
        FROM gallery_photos gp
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

    // Basit görüntüleme yetkisi kontrolü
    $can_view = false;
    
    if ($photo['is_public_no_auth'] == 1) {
        $can_view = true;
    } elseif ($current_user_id && $photo['is_members_only'] == 1 && is_user_approved()) {
        $can_view = true;
    } elseif ($current_user_id && has_permission($pdo, 'gallery.manage_all')) {
        $can_view = true; // Admin her şeyi görebilir
    }
    
    if (!$can_view) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu fotoğrafın yorumlarını görüntüleme yetkiniz bulunmamaktadır.'
        ]);
        exit;
    }

    // Yorumları getir
    $comments_query = "
        SELECT 
            c.id,
            c.comment_text,
            c.is_edited,
            c.created_at,
            c.updated_at,
            u.id as user_id,
            u.username,
            u.avatar_path,
            u.ingame_name,
            (SELECT GROUP_CONCAT(r.name ORDER BY r.priority ASC SEPARATOR ',')
             FROM user_roles ur 
             JOIN roles r ON ur.role_id = r.id 
             WHERE ur.user_id = u.id) AS user_roles_list,
            COALESCE(like_count.total_likes, 0) AS like_count
    ";

    if ($current_user_id) {
        $comments_query .= ",
            CASE WHEN user_like.user_id IS NOT NULL THEN 1 ELSE 0 END AS user_has_liked";
    } else {
        $comments_query .= ",
            0 AS user_has_liked";
    }

    $comments_query .= "
        FROM gallery_photo_comments c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN (
            SELECT comment_id, COUNT(*) as total_likes
            FROM gallery_comment_likes
            GROUP BY comment_id
        ) like_count ON c.id = like_count.comment_id
    ";

    if ($current_user_id) {
        $comments_query .= "
            LEFT JOIN gallery_comment_likes user_like ON c.id = user_like.comment_id AND user_like.user_id = :current_user_id
        ";
    }

    $comments_query .= "
        WHERE c.photo_id = :photo_id AND c.parent_comment_id IS NULL
        ORDER BY c.created_at ASC
    ";

    $params = [':photo_id' => $photo_id];
    if ($current_user_id) {
        $params[':current_user_id'] = $current_user_id;
    }

    $stmt_comments = execute_safe_query($pdo, $comments_query, $params);
    $comments = $stmt_comments->fetchAll();

    // Yorumları işle
    $processed_comments = [];
    foreach ($comments as $comment) {
        $user_roles = $comment['user_roles_list'] ? explode(',', $comment['user_roles_list']) : [];
        $primary_role = !empty($user_roles) ? $user_roles[0] : 'member';

        $processed_comments[] = [
            'id' => (int)$comment['id'],
            'comment_text' => $comment['comment_text'],
            'is_edited' => (bool)$comment['is_edited'],
            'created_at' => $comment['created_at'],
            'updated_at' => $comment['updated_at'],
            'user_id' => (int)$comment['user_id'],
            'username' => $comment['username'],
            'avatar_path' => $comment['avatar_path'],
            'ingame_name' => $comment['ingame_name'],
            'primary_role' => $primary_role,
            'user_roles' => $user_roles,
            'like_count' => (int)$comment['like_count'],
            'user_has_liked' => (bool)$comment['user_has_liked']
        ];
    }

    // Toplam yorum sayısını al
    $count_query = "SELECT COUNT(*) FROM gallery_photo_comments WHERE photo_id = :photo_id";
    $stmt_count = execute_safe_query($pdo, $count_query, [':photo_id' => $photo_id]);
    $total_comments = $stmt_count->fetchColumn();

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'comments' => $processed_comments,
        'total_comments' => (int)$total_comments,
        'photo_id' => $photo_id
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_photo_comments: " . $e->getMessage());
    error_log("Photo ID: $photo_id, User ID: " . ($current_user_id ?? 'guest'));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("General error in get_photo_comments: " . $e->getMessage());
    error_log("Photo ID: " . ($photo_id ?? 'unknown') . ", User ID: " . ($current_user_id ?? 'guest'));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>