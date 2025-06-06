<?php
// forum/actions/toggle_post_like.php - Tamamen düzeltilmiş versiyon

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

header('Content-Type: application/json');

// Debug için hata raporlamayı açalım
error_reporting(E_ALL);
ini_set('display_errors', 0); // JSON için 0 olmalı ama log'a yazılsın

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
if (!check_rate_limit('forum_like', 10, 60)) { // 1 dakikada 10 beğeni
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla beğeni işlemi yapıyorsunuz. Lütfen bir dakika bekleyin.'
    ]);
    exit;
}

// CSRF token kontrolü
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik hatası. Lütfen sayfayı yenileyin.'
    ]);
    exit;
}

// Yetki kontrolü
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
        'message' => 'Hesabınız henüz onaylanmamış.'
    ]);
    exit;
}

if (!has_permission($pdo, 'forum.post.like')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forum beğeni yetkiniz bulunmuyor.'
    ]);
    exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);

// Input validation
if (!$post_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz gönderi ID\'si.'
    ]);
    exit;
}

try {
    // Gönderinin var olup olmadığını kontrol et
    $post_query = "
        SELECT fp.*, ft.category_id, fc.visibility as category_visibility,
               fc.is_active as category_active
        FROM forum_posts fp
        JOIN forum_topics ft ON fp.topic_id = ft.id
        JOIN forum_categories fc ON ft.category_id = fc.id
        WHERE fp.id = :post_id
    ";
    
    $stmt = execute_safe_query($pdo, $post_query, [':post_id' => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Gönderi bulunamadı.'
        ]);
        exit;
    }
    
    // Kategori aktif mi kontrol et
    if (!$post['category_active']) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Bu kategori aktif değil.'
        ]);
        exit;
    }
    
    // Kategori erişim kontrolü
    $category = [
        'id' => $post['category_id'],
        'visibility' => $post['category_visibility']
    ];
    
    if (!can_user_access_forum_category($pdo, $category, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu kategoriye erişim yetkiniz bulunmuyor.'
        ]);
        exit;
    }
    
    // Kendi gönderisini beğenmeyi engelle
    if ($post['user_id'] == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Kendi gönderinizi beğenemezsiniz.'
        ]);
        exit;
    }
    
    // Mevcut beğeni durumunu kontrol et
    $like_check_query = "
        SELECT id FROM forum_post_likes 
        WHERE post_id = :post_id AND user_id = :user_id
    ";
    
    $stmt = execute_safe_query($pdo, $like_check_query, [
        ':post_id' => $post_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $existing_like = $stmt->fetch();
    $liked = false;
    
    // Transaction başlat
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
    
    try {
        if ($existing_like) {
            // Beğeniyi kaldır
            $delete_query = "
                DELETE FROM forum_post_likes 
                WHERE post_id = :post_id AND user_id = :user_id
            ";
            
            execute_safe_query($pdo, $delete_query, [
                ':post_id' => $post_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $liked = false;
            
            // Audit log
            audit_log($pdo, $_SESSION['user_id'], 'forum_post_unliked', 'forum_post', $post_id, null, [
                'target_user_id' => $post['user_id']
            ]);
            
        } else {
            // Beğeni ekle
            $insert_query = "
                INSERT INTO forum_post_likes (post_id, user_id, liked_at)
                VALUES (:post_id, :user_id, NOW())
            ";
            
            execute_safe_query($pdo, $insert_query, [
                ':post_id' => $post_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $liked = true;
            
            // Audit log
            audit_log($pdo, $_SESSION['user_id'], 'forum_post_liked', 'forum_post', $post_id, null, [
                'target_user_id' => $post['user_id']
            ]);
        }
        
        // Güncel beğeni sayısını al
        $count_query = "SELECT COUNT(*) FROM forum_post_likes WHERE post_id = :post_id";
        $stmt = execute_safe_query($pdo, $count_query, [':post_id' => $post_id]);
        $like_count = (int)$stmt->fetchColumn();
        
        // Transaction commit
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => $like_count,
            'message' => $liked ? 'Gönderi beğenildi.' : 'Beğeni kaldırıldı.'
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (SecurityException $e) {
    error_log("Forum post like security violation: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (DatabaseException $e) {
    error_log("Forum post like database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (PDOException $e) {
    error_log("Forum post like PDO error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("Forum post like general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>