<?php
// forum/actions/toggle_topic_lock.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
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

if (!has_permission($pdo, 'forum.topic.lock')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Konu kilitleme yetkiniz bulunmuyor.'
    ]);
    exit;
}

$topic_id = (int)($_POST['topic_id'] ?? 0);
$locked = filter_var($_POST['locked'] ?? false, FILTER_VALIDATE_BOOLEAN);

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
    
    // Konuyu güncelle
    $update_query = "
        UPDATE forum_topics 
        SET is_locked = :locked, updated_at = NOW()
        WHERE id = :topic_id
    ";
    
    $stmt = execute_safe_query($pdo, $update_query, [
        ':locked' => $locked ? 1 : 0,
        ':topic_id' => $topic_id
    ]);
    
    // Audit log
    audit_log($pdo, $_SESSION['user_id'], $locked ? 'forum_topic_locked' : 'forum_topic_unlocked', 'forum_topic', $topic_id, 
        ['was_locked' => $topic['is_locked']], 
        ['is_locked' => $locked]
    );
    
    echo json_encode([
        'success' => true,
        'locked' => $locked,
        'message' => $locked ? 'Konu kilitlendi.' : 'Konu kilidi açıldı.'
    ]);
    
} catch (Exception $e) {
    error_log("Forum topic lock error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu.'
    ]);
}
?>