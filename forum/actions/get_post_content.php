<?php
// forum/actions/get_post_content.php - Post içeriğini getir

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

header('Content-Type: application/json');

// Sadece GET isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece GET istekleri kabul edilir'
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

$post_id = (int)($_GET['post_id'] ?? 0);
$type = $_GET['type'] ?? 'post';

if (!$post_id && $type === 'post') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz gönderi ID\'si.'
    ]);
    exit;
}

try {
    if ($type === 'topic') {
        // Topic ID'yi GET'ten al
        $topic_id = (int)($_GET['topic_id'] ?? 0);
        
        if (!$topic_id) {
            throw new Exception('Geçersiz konu ID\'si.');
        }
        
        // Konu içeriğini al
        $query = "
            SELECT ft.content, ft.title, ft.user_id
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.id = :topic_id AND fc.is_active = 1
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':topic_id' => $topic_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            throw new Exception('Konu bulunamadı.');
        }
        
        // Düzenleme yetkisi kontrolü
        if (!can_user_edit_forum_topic($pdo, $topic_id, $_SESSION['user_id'])) {
            throw new Exception('Bu konuyu düzenleme yetkiniz bulunmuyor.');
        }
        
        echo json_encode([
            'success' => true,
            'content' => $data['content'],
            'title' => $data['title']
        ]);
        
    } else {
        // Post içeriğini al
        $query = "
            SELECT fp.content, fp.user_id, ft.category_id, fc.visibility
            FROM forum_posts fp
            JOIN forum_topics ft ON fp.topic_id = ft.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE fp.id = :post_id AND fc.is_active = 1
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':post_id' => $post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            throw new Exception('Gönderi bulunamadı.');
        }
        
        // Düzenleme yetkisi kontrolü
        if (!can_user_edit_forum_post($pdo, $post_id, $_SESSION['user_id'])) {
            throw new Exception('Bu gönderiyi düzenleme yetkiniz bulunmuyor.');
        }
        
        // Kategori erişim kontrolü
        $category = [
            'id' => $post['category_id'],
            'visibility' => $post['visibility']
        ];
        
        if (!can_user_access_forum_category($pdo, $category, $_SESSION['user_id'])) {
            throw new Exception('Bu kategoriye erişim yetkiniz bulunmuyor.');
        }
        
        echo json_encode([
            'success' => true,
            'content' => $post['content']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get post content error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>