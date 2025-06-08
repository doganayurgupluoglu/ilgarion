<?php
// ============================================================================
// events/actions/leave_event_role.php
// ============================================================================

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['event_id']) || !isset($input['slot_id']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!verify_csrf_token($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$event_id = (int)$input['event_id'];
$slot_id = (int)$input['slot_id'];
$user_id = $_SESSION['user_id'];

if ($event_id <= 0 || $slot_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event or slot ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Katılım kontrolü
    $participation_stmt = $pdo->prepare("
        SELECT erp.id, e.title, er.role_name
        FROM event_role_participants erp
        JOIN event_role_slots ers ON erp.event_role_slot_id = ers.id
        JOIN events e ON ers.event_id = e.id
        JOIN event_roles er ON ers.event_role_id = er.id
        WHERE erp.event_role_slot_id = ? AND erp.user_id = ? AND erp.status = 'active'
    ");
    $participation_stmt->execute([$slot_id, $user_id]);
    $participation = $participation_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participation) {
        throw new Exception('Bu role katılımınız bulunamadı.');
    }
    
    // Rol katılımını kaldır
    $remove_participant_stmt = $pdo->prepare("
        DELETE FROM event_role_participants 
        WHERE event_role_slot_id = ? AND user_id = ?
    ");
    $remove_participant_stmt->execute([$slot_id, $user_id]);
    
    // Slot dolu sayısını azalt
    $update_slot_stmt = $pdo->prepare("
        UPDATE event_role_slots 
        SET filled_count = GREATEST(0, filled_count - 1)
        WHERE id = ?
    ");
    $update_slot_stmt->execute([$slot_id]);
    
    // Ana katılımcı tablosundan da kaldır
    $remove_event_participant_stmt = $pdo->prepare("
        DELETE FROM event_participants 
        WHERE event_id = ? AND user_id = ? AND event_role_slot_id = ?
    ");
    $remove_event_participant_stmt->execute([$event_id, $user_id, $slot_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "'{$participation['role_name']}' rolünden '{$participation['title']}' etkinliğinden başarıyla ayrıldınız."
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Leave event role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Leave event role DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ayrılma işlemi sırasında bir hata oluştu.']);
}
?>