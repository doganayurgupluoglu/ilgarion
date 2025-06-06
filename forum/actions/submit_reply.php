<?php
// forum/actions/submit_reply.php - Dinamik yorum ekleme ile

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
if (!check_rate_limit('forum_reply', 30, 300)) { // 5 dakikada 5 yanıt
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla yanıt gönderiyorsunuz. Lütfen 5 dakika bekleyin.'
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

if (!has_permission($pdo, 'forum.topic.reply')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forum yanıtlama yetkiniz bulunmuyor.'
    ]);
    exit;
}

$topic_id = (int)($_POST['topic_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$add_dynamic = $_POST['add_dynamic'] ?? 'false'; // JS'den gelen dinamik ekleme isteği

// Input validation
if (!$topic_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz konu ID\'si.'
    ]);
    exit;
}

if (empty($content)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Yanıt içeriği boş olamaz.'
    ]);
    exit;
}

if (strlen($content) < 5) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Yanıt en az 5 karakter olmalıdır.'
    ]);
    exit;
}

if (strlen($content) > 10000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Yanıt en fazla 10.000 karakter olabilir.'
    ]);
    exit;
}

/**
 * Avatar path'ini düzeltir
 */
function fix_avatar_path($avatar_path) {
    if (empty($avatar_path)) {
        return '/assets/logo.png';
    }
    
    if (strpos($avatar_path, '../assets/') === 0) {
        return str_replace('../assets/', '/assets/', $avatar_path);
    }
    
    if (strpos($avatar_path, '/uploads/') === 0) {
        return '' . $avatar_path;
    }
    
    if (strpos($avatar_path, '/assets/') === 0 || strpos($avatar_path, '') === 0) {
        return $avatar_path;
    }
    
    return '/assets/logo.png';
}

try {
    // Konunun var olup olmadığını ve erişim yetkisini kontrol et
    $topic_query = "
        SELECT ft.*, fc.visibility as category_visibility
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
    
    // Konu kilitli mi kontrol et
    if ($topic['is_locked']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu konu yeni yanıtlara kapatılmıştır.'
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
        // Yanıtı ekle
        $insert_query = "
            INSERT INTO forum_posts (topic_id, user_id, content, created_at, updated_at)
            VALUES (:topic_id, :user_id, :content, NOW(), NOW())
        ";
        
        $insert_params = [
            ':topic_id' => $topic_id,
            ':user_id' => $_SESSION['user_id'],
            ':content' => $content
        ];
        
        $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
        $post_id = $pdo->lastInsertId();
        
        // Konu istatistiklerini güncelle
        $update_topic_query = "
            UPDATE forum_topics 
            SET reply_count = reply_count + 1,
                last_post_user_id = :user_id,
                last_post_at = NOW(),
                updated_at = NOW()
            WHERE id = :topic_id
        ";
        
        $update_params = [
            ':user_id' => $_SESSION['user_id'],
            ':topic_id' => $topic_id
        ];
        
        execute_safe_query($pdo, $update_topic_query, $update_params);
        
        // Toplam gönderi sayısını al (pagination için)
        $count_query = "SELECT COUNT(*) FROM forum_posts WHERE topic_id = :topic_id";
        $stmt = execute_safe_query($pdo, $count_query, [':topic_id' => $topic_id]);
        $total_posts = (int)$stmt->fetchColumn();
        
        // Son sayfayı hesapla
        $per_page = 10;
        $last_page = ceil($total_posts / $per_page);
        
        // Kullanıcı bilgilerini al (dinamik ekleme için)
        $user_query = "
            SELECT u.id, u.username, u.avatar_path, u.created_at,
                   r.name as role_name, r.color as role_color
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id AND r.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE u.id = :user_id
        ";
        
        $stmt = execute_safe_query($pdo, $user_query, [':user_id' => $_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Transaction commit
        $pdo->commit();
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'forum_post_created', 'forum_post', $post_id, null, [
            'topic_id' => $topic_id,
            'content_length' => strlen($content)
        ]);
        
        // Response verisi hazırla
        $response = [
            'success' => true,
            'message' => 'Yanıtınız başarıyla gönderildi.',
            'post_id' => $post_id,
            'redirect_url' => 'topic.php?id=' . $topic_id . '&page=' . $last_page . '#post-' . $post_id
        ];
        
        // Dinamik ekleme için ek veriler
        if ($add_dynamic === 'true' && $user_data) {
            $response['post_data'] = [
                'id' => $post_id,
                'user_id' => $user_data['id'],
                'username' => htmlspecialchars($user_data['username']),
                'avatar_path' => fix_avatar_path($user_data['avatar_path']),
                'role_name' => htmlspecialchars($user_data['role_name'] ?? 'Üye'),
                'role_color' => $user_data['role_color'] ?? '#bd912a',
                'join_date' => date('M Y', strtotime($user_data['created_at'])),
                'content_html' => parse_bbcode($content),
                'content_text' => htmlspecialchars(strip_tags($content)),
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (SecurityException $e) {
    error_log("Forum reply security violation: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Güvenlik ihlali tespit edildi.'
    ]);
} catch (DatabaseException $e) {
    error_log("Forum reply database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu.'
    ]);
} catch (Exception $e) {
    error_log("Forum reply general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>