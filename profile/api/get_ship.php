<?php
// api/get_ship.php - Hangar gemisi bilgilerini getir (Düzeltilmiş)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Dosya yollarını düzelt
$base_path = dirname(dirname(__DIR__)); // profile/api/ dan iki üst klasör
require_once $base_path . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// OPTIONS request için (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// HTTP method kontrolü
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Authentication kontrolü
if (!is_user_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Input validation
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ship ID']);
    exit;
}

$ship_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// ID sıfır veya negatif kontrolü
if ($ship_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ship ID']);
    exit;
}

try {
    // Güvenli sorgu ile gemi bilgilerini al
    $query = "
        SELECT id, ship_api_id, ship_name, ship_manufacturer, ship_focus, 
               ship_size, ship_image_url, quantity, user_notes, added_at
        FROM user_hangar 
        WHERE id = :ship_id AND user_id = :user_id
    ";
    
    $stmt = execute_safe_query($pdo, $query, [
        ':ship_id' => $ship_id,
        ':user_id' => $user_id
    ]);
    
    $ship = $stmt->fetch();
    
    if (!$ship) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ship not found']);
        exit;
    }
    
    // Veriyi temizle ve güvenli hale getir
    $cleaned_ship = [
        'id' => (int)$ship['id'],
        'ship_api_id' => htmlspecialchars($ship['ship_api_id'], ENT_QUOTES, 'UTF-8'),
        'ship_name' => htmlspecialchars($ship['ship_name'], ENT_QUOTES, 'UTF-8'),
        'ship_manufacturer' => htmlspecialchars($ship['ship_manufacturer'] ?? '', ENT_QUOTES, 'UTF-8'),
        'ship_focus' => htmlspecialchars($ship['ship_focus'] ?? '', ENT_QUOTES, 'UTF-8'),
        'ship_size' => htmlspecialchars($ship['ship_size'] ?? '', ENT_QUOTES, 'UTF-8'),
        'ship_image_url' => $ship['ship_image_url'] ? filter_var($ship['ship_image_url'], FILTER_VALIDATE_URL) : null,
        'quantity' => (int)$ship['quantity'],
        'user_notes' => htmlspecialchars($ship['user_notes'] ?? '', ENT_QUOTES, 'UTF-8'),
        'added_at' => $ship['added_at']
    ];
    
    // Başarılı response
    echo json_encode([
        'success' => true,
        'ship' => $cleaned_ship
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Get ship database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Get ship error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>