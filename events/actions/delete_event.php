<?php
// events/actions/delete_event.php - Etkinlik silme işlemi

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

// Yetki kontrolleri
$can_delete_all = has_permission($pdo, 'event.delete_all');

try {
    $pdo->beginTransaction();
    
    // Etkinlik kontrolü ve yetki doğrulama
    $event_stmt = $pdo->prepare("
        SELECT id, title, created_by_user_id, thumbnail_path,
               (SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND status = 'joined') as participant_count
        FROM events 
        WHERE id = ?
    ");
    $event_stmt->execute([$event_id, $event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Etkinlik bulunamadı.');
    }
    
    // Silme yetkisi kontrolü
    if ($event['created_by_user_id'] != $user_id && !$can_delete_all) {
        throw new Exception('Bu etkinliği silme yetkiniz bulunmuyor.');
    }
    
    // Aktif katılımcı kontrolü
    if ($event['participant_count'] > 0) {
        throw new Exception(
            "Bu etkinlikte {$event['participant_count']} aktif katılımcı bulunmakta. " .
            "Etkinliği silebilmek için önce tüm katılımcıların etkinlikten ayrılmasını sağlamanız gerekiyor."
        );
    }
    
    // Thumbnail dosyasını sil
    if ($event['thumbnail_path']) {
        $thumbnail_full_path = BASE_PATH . $event['thumbnail_path'];
        if (file_exists($thumbnail_full_path)) {
            unlink($thumbnail_full_path);
        }
    }
    
    // Katılımcı kayıtlarını sil (güvenlik için)
    $delete_participants = $pdo->prepare("DELETE FROM event_participants WHERE event_id = ?");
    $delete_participants->execute([$event_id]);
    
    // Etkinliği sil
    $delete_event = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $delete_event->execute([$event_id]);
    
    if ($delete_event->rowCount() === 0) {
        throw new Exception('Etkinlik silinemedi.');
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "'{$event['title']}' etkinliği başarıyla silindi."
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete event error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete event DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Silme işlemi sırasında bir hata oluştu.']);
}
?>