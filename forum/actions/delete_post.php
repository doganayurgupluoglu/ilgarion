<?php
// forum/actions/delete_post.php - Yorum silme action

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
    // Gönderi bilgilerini al
    $post_query = "
        SELECT fp.*, ft.id as topic_id, ft.title as topic_title,
               ft.category_id, fc.visibility as category_visibility,
               u.username as author_username
        FROM forum_posts fp
        JOIN forum_topics ft ON fp.topic_id = ft.id
        JOIN forum_categories fc ON ft.category_id = fc.id
        JOIN users u ON fp.user_id = u.id
        WHERE fp.id = :post_id AND fc.is_active = 1
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
    
    // Silme yetkisi kontrolü
    if (!can_user_delete_forum_post($pdo, $post_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu gönderiyi silme yetkiniz bulunmuyor.'
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
    
    // Transaction başlat
    $pdo->beginTransaction();
    
    try {
        // İlk önce beğenileri sil
        $delete_likes_query = "DELETE FROM forum_post_likes WHERE post_id = :post_id";
        execute_safe_query($pdo, $delete_likes_query, [':post_id' => $post_id]);
        
        // Gönderiyi sil
        $delete_post_query = "DELETE FROM forum_posts WHERE id = :post_id";
        execute_safe_query($pdo, $delete_post_query, [':post_id' => $post_id]);
        
        // Konu istatistiklerini güncelle
        $update_topic_query = "
            UPDATE forum_topics ft
            SET reply_count = (
                SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id
            ),
            last_post_user_id = (
                SELECT fp2.user_id FROM forum_posts fp2 
                WHERE fp2.topic_id = ft.id 
                ORDER BY fp2.created_at DESC 
                LIMIT 1
            ),
            last_post_at = (
                SELECT fp3.created_at FROM forum_posts fp3 
                WHERE fp3.topic_id = ft.id 
                ORDER BY fp3.created_at DESC 
                LIMIT 1
            ),
            updated_at = NOW()
            WHERE ft.id = :topic_id
        ";
        
        execute_safe_query($pdo, $update_topic_query, [':topic_id' => $post['topic_id']]);
        
        // Transaction commit
        $pdo->commit();
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'forum_post_deleted', 'forum_post', $post_id, 
            ['post_content' => $post['content'], 'topic_id' => $post['topic_id']], null
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Gönderi başarıyla silindi.',
            'topic_id' => $post['topic_id']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (SecurityException $e) {
    error_log("Forum post delete security violation: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (DatabaseException $e) {
    error_log("Forum post delete database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("Forum post delete general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>