<?php
// events/actions/update_participation_status.php - Katılım durumu güncelleme

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

if (!isset($input['event_id']) || !isset($input['status']) || !isset($input['csrf_token'])) {
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
$status = $input['status'];
$user_id = $_SESSION['user_id'];

if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

// Status validation
$valid_statuses = ['joined', 'maybe', 'declined'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Etkinlik kontrolü
    $event_stmt = $pdo->prepare("
        SELECT id, title, status, visibility
        FROM events 
        WHERE id = ? AND status = 'active'
    ");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Etkinlik bulunamadı veya aktif değil.');
    }
    
    // Görünürlük kontrolü
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
    
    // Mevcut katılım durumunu kontrol et
    $participation_stmt = $pdo->prepare("
        SELECT id, status, event_role_id 
        FROM event_participants 
        WHERE event_id = ? AND user_id = ?
    ");
    $participation_stmt->execute([$event_id, $user_id]);
    $existing_participation = $participation_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_participation) {
        // Güncelleme
        $update_stmt = $pdo->prepare("
            UPDATE event_participants 
            SET status = ?, joined_at = NOW()
            WHERE event_id = ? AND user_id = ?
        ");
        $update_stmt->execute([$status, $event_id, $user_id]);
        
        $action_text = 'güncellendi';
    } else {
        // Yeni kayıt
        $insert_stmt = $pdo->prepare("
            INSERT INTO event_participants (event_id, user_id, status, joined_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insert_stmt->execute([$event_id, $user_id, $status]);
        
        $action_text = 'kaydedildi';
    }
    
    $pdo->commit();
    
    // Status message
    $status_messages = [
        'joined' => "'{$event['title']}' etkinliğine katılacağınız başarıyla {$action_text}.",
        'maybe' => "'{$event['title']}' etkinliği için 'belki katılırım' durumunuz başarıyla {$action_text}.",
        'declined' => "'{$event['title']}' etkinliği için 'katılmıyorum' durumunuz başarıyla {$action_text}."
    ];
    
    echo json_encode([
        'success' => true,
        'message' => $status_messages[$status]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Update participation status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Update participation status DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Durum güncelleme işlemi sırasında bir hata oluştu.']);
}
?>