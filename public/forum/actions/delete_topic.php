<?php
// public/forum/actions/delete_topic.php - Konu silme action

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(dirname(__DIR__))) . '/src/config/database.php';
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
    // Konu bilgilerini al
    $topic_query = "
        SELECT ft.*, fc.name as category_name, fc.slug as category_slug,
               fc.visibility as category_visibility
        FROM forum_topics ft
        JOIN forum_categories fc ON ft.category_id = fc.id
        WHERE ft.id = :topic_id AND fc.is_active = 1
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
    
    // Silme yetkisi kontrolü
    if (!can_user_delete_forum_topic($pdo, $topic_id, $_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu konuyu silme yetkiniz bulunmuyor.'
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
    
    // Transaction başlat
    $pdo->beginTransaction();
    
    try {
        // İlk önce post beğenilerini sil
        $delete_likes_query = "
            DELETE fpl FROM forum_post_likes fpl
            JOIN forum_posts fp ON fpl.post_id = fp.id
            WHERE fp.topic_id = :topic_id
        ";
        execute_safe_query($pdo, $delete_likes_query, [':topic_id' => $topic_id]);
        
        // Sonra postları sil
        $delete_posts_query = "DELETE FROM forum_posts WHERE topic_id = :topic_id";
        execute_safe_query($pdo, $delete_posts_query, [':topic_id' => $topic_id]);
        
        // Topic visibility rollerini sil
        $delete_visibility_query = "DELETE FROM forum_topic_visibility_roles WHERE topic_id = :topic_id";
        execute_safe_query($pdo, $delete_visibility_query, [':topic_id' => $topic_id]);
        
        // Topic tag'lerini sil
        $delete_tags_query = "DELETE FROM forum_topic_tags WHERE topic_id = :topic_id";
        execute_safe_query($pdo, $delete_tags_query, [':topic_id' => $topic_id]);
        
        // Son olarak konuyu sil
        $delete_topic_query = "DELETE FROM forum_topics WHERE id = :topic_id";
        execute_safe_query($pdo, $delete_topic_query, [':topic_id' => $topic_id]);
        
        // Transaction commit
        $pdo->commit();
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'forum_topic_deleted', 'forum_topic', $topic_id, [
            'topic_title' => $topic['title'],
            'category_id' => $topic['category_id'],
            'author_id' => $topic['user_id']
        ], null);
        
        echo json_encode([
            'success' => true,
            'message' => 'Konu başarıyla silindi.',
            'category_slug' => $topic['category_slug']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (SecurityException $e) {
    error_log("Forum topic delete security violation: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (DatabaseException $e) {
    error_log("Forum topic delete database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("Forum topic delete general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>