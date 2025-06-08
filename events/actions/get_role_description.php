<?php
// events/actions/get_role_description.php - Rol açıklaması getirme

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

// Session kontrolü
if (!isset($_SESSION['user_id']) || !is_user_approved()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET parametresi kontrolü
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($role_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, role_name, role_description, icon_class, visibility
        FROM event_roles 
        WHERE id = :id AND is_active = 1 
        AND (visibility = 'public' OR (visibility = 'members_only' AND :is_member = 1))
    ");
    
    $stmt->execute([
        ':id' => $role_id,
        ':is_member' => 1 // User is already verified as approved
    ]);
    
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        exit;
    }
    
    // Return role data
    echo json_encode([
        'success' => true,
        'id' => $role['id'],
        'role_name' => $role['role_name'],
        'description' => $role['role_description'],
        'icon_class' => $role['icon_class']
    ]);
    
} catch (PDOException $e) {
    error_log("Get role description error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>