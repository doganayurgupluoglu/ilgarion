<?php
// forum/actions/toggle_topic_like.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

header('Content-Type: application/json');

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
if (!check_rate_limit('topic_like', 100, 60)) { // 1 dakikada 10 beğeni
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

$topic_id = (int)($_POST['topic_id'] ?? 0);

// Input validation
if (!$topic_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz konu ID\'si.'
    ]);
    exit;
}

try {
    // Konunun var olup olmadığını kontrol et
    $topic_query = "
        SELECT ft.*, fc.visibility as category_visibility,
               fc.is_active as category_active
        FROM forum_topics ft
        JOIN forum_categories fc ON ft.category_id = fc.id
        WHERE ft.id = :topic_id
    ";
    
    $stmt = execute_safe_query($pdo, $topic_query, [':topic_id' => $topic_id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Konu bulunamadı.'
        ]);
        exit;
    }
    
    // Kategori aktif mi kontrol et
    if (!$topic['category_active']) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Bu kategori aktif değil.'
        ]);
        exit;
    }
    
    // Kategori erişim kontrolü
    $category = [
        'id' => $topic['category_id'],
        'visibility' => $topic['category_visibility']
    ];
    
    if (!can_user_access_forum_category($pdo, $category, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu kategoriye erişim yetkiniz bulunmuyor.'
        ]);
        exit;
    }
    
    // Kendi konusunu beğenmeyi engelle
    if ($topic['user_id'] == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Kendi konunuzu beğenemezsiniz.'
        ]);
        exit;
    }
    
    // Mevcut beğeni durumunu kontrol et
    $like_check_query = "
        SELECT id FROM forum_topic_likes 
        WHERE topic_id = :topic_id AND user_id = :user_id
    ";
    
    $stmt = execute_safe_query($pdo, $like_check_query, [
        ':topic_id' => $topic_id,
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
                DELETE FROM forum_topic_likes 
                WHERE topic_id = :topic_id AND user_id = :user_id
            ";
            
            execute_safe_query($pdo, $delete_query, [
                ':topic_id' => $topic_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $liked = false;
            
            // Audit log
            audit_log($pdo, $_SESSION['user_id'], 'forum_topic_unliked', 'forum_topic', $topic_id, null, [
                'target_user_id' => $topic['user_id']
            ]);
            
        } else {
            // Beğeni ekle
            $insert_query = "
                INSERT INTO forum_topic_likes (topic_id, user_id, liked_at)
                VALUES (:topic_id, :user_id, NOW())
            ";
            
            execute_safe_query($pdo, $insert_query, [
                ':topic_id' => $topic_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $liked = true;
            
            // Audit log
            audit_log($pdo, $_SESSION['user_id'], 'forum_topic_liked', 'forum_topic', $topic_id, null, [
                'target_user_id' => $topic['user_id']
            ]);
        }
        
        // Güncel beğeni verilerini al
        $like_data = get_topic_like_data($pdo, $topic_id, $_SESSION['user_id']);
        
        // Transaction commit
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => $like_data['like_count'],
            'liked_users' => $like_data['liked_users'],
            'message' => $liked ? 'Konu beğenildi.' : 'Beğeni kaldırıldı.'
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (SecurityException $e) {
    error_log("Forum topic like security violation: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (DatabaseException $e) {
    error_log("Forum topic like database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("Forum topic like general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>