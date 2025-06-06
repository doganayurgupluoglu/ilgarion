<?php
//  forum/actions/edit_post.php - Post düzenleme API

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

// Parametreleri al
$post_id = (int)($_POST['post_id'] ?? 0);
$type = $_POST['type'] ?? 'post'; // 'post' veya 'topic'
$content = trim($_POST['content'] ?? '');

// Input validation
if (!$post_id && $type === 'post') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz gönderi ID\'si.'
    ]);
    exit;
}

if (empty($content)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'İçerik boş olamaz.'
    ]);
    exit;
}

if (strlen($content) < 5) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'İçerik en az 5 karakter olmalıdır.'
    ]);
    exit;
}

if (strlen($content) > 50000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'İçerik en fazla 10.000 karakter olabilir.'
    ]);
    exit;
}

try {
    if ($type === 'topic') {
        // Topic ID'yi POST'dan al
        $topic_id = (int)($_POST['topic_id'] ?? 0);
        
        if (!$topic_id) {
            throw new Exception('Geçersiz konu ID\'si.');
        }
        
        // Konu bilgilerini al
        $topic_query = "
            SELECT ft.*, fc.visibility as category_visibility
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.id = :topic_id AND fc.is_active = 1
        ";
        
        $stmt = execute_safe_query($pdo, $topic_query, [':topic_id' => $topic_id]);
        $topic = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$topic) {
            throw new Exception('Konu bulunamadı.');
        }
        
        // Düzenleme yetkisi kontrolü
        if (!can_user_edit_forum_topic($pdo, $topic_id, $_SESSION['user_id'])) {
            throw new Exception('Bu konuyu düzenleme yetkiniz bulunmuyor.');
        }
        
        // Konuyu güncelle
        $update_query = "
            UPDATE forum_topics 
            SET content = :content, updated_at = NOW()
            WHERE id = :topic_id
        ";
        
        $stmt = execute_safe_query($pdo, $update_query, [
            ':content' => $content,
            ':topic_id' => $topic_id
        ]);
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'forum_topic_edited', 'forum_topic', $topic_id, 
            ['content' => $topic['content']], 
            ['content' => $content]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Konu başarıyla güncellendi.',
            'content_html' => parse_bbcode($content)
        ]);
        
    } else {
        // Post düzenleme
        $post_query = "
            SELECT fp.*, ft.id as topic_id, ft.category_id, fc.visibility as category_visibility
            FROM forum_posts fp
            JOIN forum_topics ft ON fp.topic_id = ft.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE fp.id = :post_id AND fc.is_active = 1
        ";
        
        $stmt = execute_safe_query($pdo, $post_query, [':post_id' => $post_id]);
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
            'visibility' => $post['category_visibility']
        ];
        
        if (!can_user_access_forum_category($pdo, $category, $_SESSION['user_id'])) {
            throw new Exception('Bu kategoriye erişim yetkiniz bulunmuyor.');
        }
        
        // Gönderiyi güncelle
        $update_query = "
            UPDATE forum_posts 
            SET content = :content, 
                is_edited = 1, 
                edited_at = NOW(), 
                edited_by_user_id = :editor_id,
                updated_at = NOW()
            WHERE id = :post_id
        ";
        
        $stmt = execute_safe_query($pdo, $update_query, [
            ':content' => $content,
            ':editor_id' => $_SESSION['user_id'],
            ':post_id' => $post_id
        ]);
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'forum_post_edited', 'forum_post', $post_id, 
            ['content' => $post['content']], 
            ['content' => $content]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Gönderi başarıyla güncellendi.',
            'content_html' => parse_bbcode($content),
            'edited_at' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    error_log("Forum post edit error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>