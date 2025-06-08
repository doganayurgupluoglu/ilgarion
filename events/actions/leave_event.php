<?php
// events/actions/leave_event.php - Etkinlikten ayrılma işlemi

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// POST data kontrolü
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['event_id']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// CSRF kontrolü
if (!verify_csrf_token($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$event_id = (int)$input['event_id'];
$user_id = $_SESSION['user_id'];

if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Etkinlik ve katılım kontrolü
    $participation_stmt = $pdo->prepare("
        SELECT ep.id, e.title, er.role_name
        FROM event_participants ep
        JOIN events e ON ep.event_id = e.id
        LEFT JOIN event_roles er ON ep.event_role_id = er.id
        WHERE ep.event_id = ? AND ep.user_id = ? AND ep.status = 'joined'
    ");
    $participation_stmt->execute([$event_id, $user_id]);
    $participation = $participation_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participation) {
        throw new Exception('Bu etkinliğe katılımınız bulunamadı.');
    }
    
    // Katılımı güncelle
    $update_stmt = $pdo->prepare("
        UPDATE event_participants 
        SET status = 'declined'
        WHERE event_id = ? AND user_id = ?
    ");
    $update_stmt->execute([$event_id, $user_id]);
    
    if ($update_stmt->rowCount() === 0) {
        throw new Exception('Ayrılma işlemi yapılamadı.');
    }
    
    $pdo->commit();
    
    $message = "'{$participation['title']}' etkinliğinden başarıyla ayrıldınız.";
    if ($participation['role_name']) {
        $message = "'{$participation['role_name']}' rolünden '{$participation['title']}' etkinliğinden başarıyla ayrıldınız.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Leave event error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Leave event DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ayrılma işlemi sırasında bir hata oluştu.']);
}
?>

<?php
// events/actions/leave_event_role.php - Etkinlik rolünden ayrılma işlemi

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// POST data kontrolü
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['event_id']) || !isset($input['role_id']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// CSRF kontrolü
if (!verify_csrf_token($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$event_id = (int)$input['event_id'];
$role_id = (int)$input['role_id'];
$user_id = $_SESSION['user_id'];

if ($event_id <= 0 || $role_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event or role ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Katılım kontrolü
    $participation_stmt = $pdo->prepare("
        SELECT ep.id, e.title, er.role_name
        FROM event_participants ep
        JOIN events e ON ep.event_id = e.id
        JOIN event_roles er ON ep.event_role_id = er.id
        WHERE ep.event_id = ? AND ep.user_id = ? AND ep.event_role_id = ? AND ep.status = 'joined'
    ");
    $participation_stmt->execute([$event_id, $user_id, $role_id]);
    $participation = $participation_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participation) {
        throw new Exception('Bu role katılımınız bulunamadı.');
    }
    
    // Katılımı güncelle
    $update_stmt = $pdo->prepare("
        UPDATE event_participants 
        SET status = 'declined'
        WHERE event_id = ? AND user_id = ? AND event_role_id = ?
    ");
    $update_stmt->execute([$event_id, $user_id, $role_id]);
    
    if ($update_stmt->rowCount() === 0) {
        throw new Exception('Ayrılma işlemi yapılamadı.');
    }
    
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