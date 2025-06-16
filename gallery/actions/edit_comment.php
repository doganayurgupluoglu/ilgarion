<?php
// /gallery/actions/edit_comment.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Hata yönetimi için bir fonksiyon
function error_response($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method.');
}

// Kullanıcı girişi kontrolü
if (!is_user_logged_in()) {
    error_response('Authentication required.');
}

$current_user_id = $_SESSION['user_id'];

// Gelen veriyi al
$data = json_decode(file_get_contents('php://input'), true);

// CSRF token kontrolü
if (!isset($data['csrf_token']) || !verify_csrf_token($data['csrf_token'])) {
    error_response('Invalid CSRF token.');
}

// Gerekli verileri kontrol et
$comment_id = $data['comment_id'] ?? null;
$content = $data['content'] ?? null;

if (empty($comment_id) || !is_numeric($comment_id)) {
    error_response('Invalid comment ID.');
}

if ($content === null) {
    error_response('Comment content is required.');
}

// İçeriği temizle ve doğrula
$trimmed_content = trim($content);
if (mb_strlen($trimmed_content) < 1 || mb_strlen($trimmed_content) > 1000) {
    error_response('Comment must be between 1 and 1000 characters.');
}

try {
    // Yorumu ve sahibini bul
    $stmt = $pdo->prepare("SELECT user_id FROM gallery_photo_comments WHERE id = :id");
    $stmt->execute([':id' => $comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        error_response('Comment not found.');
    }

    // Yetki kontrolü
    $is_owner = ($comment['user_id'] == $current_user_id);
    $can_edit_any = has_permission($pdo, 'gallery.comment.edit_any');

    if (!$is_owner && !$can_edit_any) {
        error_response('You do not have permission to edit this comment.');
    }

    // Yorumu güncelle
    $update_stmt = $pdo->prepare("UPDATE gallery_photo_comments SET comment_text = :comment_text, updated_at = NOW() WHERE id = :id");
    $update_stmt->execute([
        ':comment_text' => $trimmed_content,
        ':id' => $comment_id
    ]);
    
    // HTMLPurifier'ı dahil et ve yapılandır
    require_once BASE_PATH . '/htmlpurifier-4.15.0/library/HTMLPurifier.auto.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.Allowed', ''); // Tüm HTML'i kaldır, sadece metin kalsın
    $config->set('Core.EscapeInvalidTags', true);
    $purifier = new HTMLPurifier($config);
    
    // Temizlenmiş içeriği al (satır sonlarını <br>'ye çevir)
    $safe_content_for_display = nl2br(htmlspecialchars($purifier->purify($trimmed_content), ENT_QUOTES, 'UTF-8'));

    if ($update_stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'new_content_html' => $safe_content_for_display
        ]);
    } else {
        // Hiçbir şey değişmediyse de başarı olarak kabul edilebilir
        echo json_encode([
            'success' => true,
            'message' => 'No changes made to the comment.',
            'new_content_html' => $safe_content_for_display
        ]);
    }

} catch (PDOException $e) {
    error_log('Comment edit error: ' . $e->getMessage());
    error_response('Database error during comment update.');
}
?> 