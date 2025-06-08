<?php
// events/actions/preview_markdown.php - Markdown önizleme

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/Parsedown.php';

// Session kontrolü
if (!isset($_SESSION['user_id']) || !is_user_approved()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// POST data kontrolü
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No content provided']);
    exit;
}

$content = trim($input['content']);

if (empty($content)) {
    echo json_encode([
        'success' => true,
        'html' => '<p><em>Önizlenecek içerik yok...</em></p>'
    ]);
    exit;
}

try {
    // Parsedown ile markdown'ı HTML'e çevir
    $parsedown = new Parsedown();
    
    // Güvenlik için safe mode aktif
    $parsedown->setSafeMode(true);
    
    // Satır sonlarını break'e çevir
    $parsedown->setBreaksEnabled(true);
    
    // HTML'e çevir
    $html = $parsedown->text($content);
    
    // Boş veya sadece whitespace ise
    if (empty(trim(strip_tags($html)))) {
        $html = '<p><em>Önizlenecek içerik yok...</em></p>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log("Markdown preview error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Preview generation failed'
    ]);
}
?>