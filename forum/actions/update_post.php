<?php
// /forum/actions/update_post.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/htmlpurifier-4.15.0/library/HTMLPurifier.auto.php';

// Setup HTML Purifier
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.SafeIframe', true);
$config->set('URI.SafeIframeRegexp', '%^(https?://)?(www\.youtube(?:-nocookie)?\.com/embed/)%');
$purifier = new HTMLPurifier($config);

header('Content-Type: application/json');

// Basic validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!is_user_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to edit.']);
    exit;
}

// Get POST data
$post_id = (int)($_POST['id'] ?? 0);
$content_type = $_POST['type'] ?? '';
$dirty_content = $_POST['content'] ?? '';

if (!$post_id || !in_array($content_type, ['topic', 'post'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID or content type.']);
    exit;
}

// Sanitize the content
$clean_content = $purifier->purify($dirty_content);

$current_user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    if ($content_type === 'topic') {
        $table = 'forum_topics';
        $sql_get_author = "SELECT user_id FROM $table WHERE id = :id";
    } else { // 'post'
        $table = 'forum_posts';
        $sql_get_author = "SELECT user_id FROM $table WHERE id = :id";
    }

    // Check if the user is the author or has permission to edit
    $stmt = $pdo->prepare($sql_get_author);
    $stmt->execute([':id' => $post_id]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$author) {
        throw new Exception('Post or topic not found.');
    }

    $can_edit_others_permission = $content_type === 'topic' ? 'forum.topic.edit.others' : 'forum.post.edit.others';

    if ($author['user_id'] != $current_user_id && !has_permission($pdo, $can_edit_others_permission)) {
         throw new Exception('You do not have permission to edit this content.');
    }

    // Update the content
    $update_sql = "UPDATE $table SET content = :content, updated_at = NOW() WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':content' => $clean_content,
        ':id' => $post_id
    ]);

    $pdo->commit();

    // Log the action
    $action_type = $content_type === 'topic' ? 'forum_topic_edited' : 'forum_post_edited';
    audit_log($pdo, $current_user_id, $action_type, $content_type, $post_id, null, ['content_length' => strlen($clean_content)]);

    echo json_encode([
        'success' => true,
        'message' => 'Content updated successfully.',
        'clean_content' => $clean_content
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 