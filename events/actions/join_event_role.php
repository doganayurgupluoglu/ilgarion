<?php
// events/actions/join_event_role.php - Etkinlik rolüne katılma işlemi

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Session kontrolü
if (!isset($_SESSION['user_id']) || !is_user_approved()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Katılım yetkisi kontrolü
if (!has_permission($pdo, 'event.join')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmuyor']);
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
    
    // Etkinlik kontrolü
    $event_stmt = $pdo->prepare("
        SELECT id, title, status, max_participants, requires_role_selection, visibility
        FROM events 
        WHERE id = ? AND status = 'active'
    ");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Etkinlik bulunamadı veya aktif değil.');
    }
    
    // Rol kontrolü
    $role_stmt = $pdo->prepare("
        SELECT id, role_name, visibility
        FROM event_roles 
        WHERE id = ? AND is_active = 1
    ");
    $role_stmt->execute([$role_id]);
    $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        throw new Exception('Rol bulunamadı veya aktif değil.');
    }
    
    // Rol görünürlüğü kontrolü
    if ($role['visibility'] === 'members_only' && !is_user_approved()) {
        throw new Exception('Bu role katılım için onaylı üye olmanız gerekiyor.');
    }
    
    // Etkinlik görünürlüğü kontrolü
    if ($event['visibility'] === 'role_restricted') {
        $role_check_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_roles ur
            JOIN event_visibility_roles evr ON ur.role_id = evr.role_id
            WHERE ur.user_id = ? AND evr.event_id = ?
        ");
        $role_check_stmt->execute([$user_id, $event_id]);
        if ($role_check_stmt->fetchColumn() == 0) {
            throw new Exception('Bu etkinliğe katılım yetkiniz bulunmuyor.');
        }
    }
    
    // Aynı role zaten katılmış mı kontrolü
    $participation_stmt = $pdo->prepare("
        SELECT id FROM event_participants 
        WHERE event_id = ? AND user_id = ? AND event_role_id = ?
    ");
    $participation_stmt->execute([$event_id, $user_id, $role_id]);
    if ($participation_stmt->fetch()) {
        throw new Exception('Bu role zaten katılmışsınız.');
    }
    
    // Kullanıcı bu etkinlikte başka bir role katılmış mı kontrolü
    $other_role_stmt = $pdo->prepare("
        SELECT er.role_name 
        FROM event_participants ep
        JOIN event_roles er ON ep.event_role_id = er.id
        WHERE ep.event_id = ? AND ep.user_id = ? AND ep.status = 'joined'
    ");
    $other_role_stmt->execute([$event_id, $user_id]);
    $other_role = $other_role_stmt->fetch();
    
    if ($other_role) {
        throw new Exception("Bu etkinlikte zaten '{$other_role['role_name']}' rolüne katılmışsınız. Önce o rolden ayrılmalısınız.");
    }
    
    // Katılım kaydı oluştur
    $insert_stmt = $pdo->prepare("
        INSERT INTO event_participants (event_id, user_id, event_role_id, status, joined_at)
        VALUES (?, ?, ?, 'joined', NOW())
    ");
    $insert_stmt->execute([$event_id, $user_id, $role_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "'{$role['role_name']}' rolü için '{$event['title']}' etkinliğine başarıyla katıldınız."
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Join event role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Join event role DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Role katılım işlemi sırasında bir hata oluştu.']);
}
?>