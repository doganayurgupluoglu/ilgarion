<?php
// ============================================================================
// events/actions/join_event_role.php
// ============================================================================

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || !is_user_approved()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!has_permission($pdo, 'event.join')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmuyor']);
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
    
    // Etkinlik kontrolü
    $event_stmt = $pdo->prepare("
        SELECT id, title, status, visibility, requires_role_selection
        FROM events 
        WHERE id = ? AND status = 'active'
    ");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Etkinlik bulunamadı veya aktif değil.');
    }
    
    if (!$event['requires_role_selection']) {
        throw new Exception('Bu etkinlik rol seçimi gerektirmiyor.');
    }
    
    // Slot kontrolü
    $slot_stmt = $pdo->prepare("
        SELECT ers.*, er.role_name, er.icon_class, er.visibility
        FROM event_role_slots ers
        JOIN event_roles er ON ers.event_role_id = er.id
        WHERE ers.id = ? AND ers.event_id = ? AND er.is_active = 1
    ");
    $slot_stmt->execute([$slot_id, $event_id]);
    $slot = $slot_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$slot) {
        throw new Exception('Rol slotu bulunamadı veya aktif değil.');
    }
    
    // Slot dolu mu kontrol et
    if ($slot['filled_count'] >= $slot['slot_count']) {
        throw new Exception('Bu rol için yer kalmamış.');
    }
    
    // Rol görünürlüğü kontrolü
    if ($slot['visibility'] === 'members_only' && !is_user_approved()) {
        throw new Exception('Bu role katılım için onaylı üye olmanız gerekiyor.');
    }
    
    // Etkinlik görünürlüğü kontrolü
    if ($event['visibility'] === 'role_restricted') {
        $role_check_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM roles r
            JOIN event_visibility_roles evr ON r.id = evr.role_id
            WHERE evr.event_id = ? AND r.id = (SELECT role_id FROM users WHERE id = ?)
        ");
        $role_check_stmt->execute([$event_id, $user_id]);
        if ($role_check_stmt->fetchColumn() == 0) {
            throw new Exception('Bu etkinliğe katılım yetkiniz bulunmuyor.');
        }
    }
    
    // Kullanıcı bu etkinlikte başka bir role katılmış mı kontrol et
    $other_role_stmt = $pdo->prepare("
        SELECT er.role_name 
        FROM event_role_participants erp
        JOIN event_role_slots ers ON erp.event_role_slot_id = ers.id
        JOIN event_roles er ON ers.event_role_id = er.id
        WHERE ers.event_id = ? AND erp.user_id = ? AND erp.status = 'active'
    ");
    $other_role_stmt->execute([$event_id, $user_id]);
    $other_role = $other_role_stmt->fetch();
    
    if ($other_role) {
        throw new Exception("Bu etkinlikte zaten '{$other_role['role_name']}' rolüne katılmışsınız. Önce o rolden ayrılmalısınız.");
    }
    
    // Aynı slota zaten katılmış mı kontrol et
    $participation_stmt = $pdo->prepare("
        SELECT id FROM event_role_participants 
        WHERE event_role_slot_id = ? AND user_id = ? AND status = 'active'
    ");
    $participation_stmt->execute([$slot_id, $user_id]);
    if ($participation_stmt->fetch()) {
        throw new Exception('Bu role zaten katılmışsınız.');
    }
    
    // Rol slot katılımı ekle
    $insert_participant_stmt = $pdo->prepare("
        INSERT INTO event_role_participants (event_role_slot_id, user_id, status, joined_at)
        VALUES (?, ?, 'active', NOW())
    ");
    $insert_participant_stmt->execute([$slot_id, $user_id]);
    
    // Slot dolu sayısını artır
    $update_slot_stmt = $pdo->prepare("
        UPDATE event_role_slots 
        SET filled_count = filled_count + 1
        WHERE id = ?
    ");
    $update_slot_stmt->execute([$slot_id]);
    
    // Ana katılımcı tablosuna da ekle (uyumluluk için)
    $insert_event_participant_stmt = $pdo->prepare("
        INSERT INTO event_participants (event_id, user_id, participation_status, event_role_slot_id, signed_up_at)
        VALUES (?, ?, 'attending', ?, NOW())
        ON DUPLICATE KEY UPDATE 
            participation_status = 'attending',
            event_role_slot_id = VALUES(event_role_slot_id),
            signed_up_at = NOW()
    ");
    $insert_event_participant_stmt->execute([$event_id, $user_id, $slot_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "'{$slot['role_name']}' rolü için '{$event['title']}' etkinliğine başarıyla katıldınız."
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
