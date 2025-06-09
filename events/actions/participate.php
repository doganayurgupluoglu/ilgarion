<?php
// /events/actions/participate.php - Etkinlik katılım işlemleri

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// JSON response için header ayarla
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Hata raporlama fonksiyonları
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function send_success($message = 'İşlem başarılı', $data = []) {
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

try {
    // Session kontrolü
    check_user_session_validity();
    require_approved_user();
    
    // Sadece POST isteklerini kabul et
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Geçersiz istek metodu', 405);
    }
    
    // CSRF token kontrolü
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token)) {
        send_error('CSRF token eksik', 403);
    }
    
    if (!verify_csrf_token($csrf_token)) {
        send_error('Güvenlik hatası - CSRF token geçersiz', 403);
    }
    
    $action = $_POST['action'] ?? '';
    $event_id = (int)($_POST['event_id'] ?? 0);
    $current_user_id = $_SESSION['user_id'];
    
    if (!$event_id) {
        send_error('Geçersiz etkinlik ID');
    }
    
    // Etkinlik kontrolü
    $event_stmt = $pdo->prepare("
        SELECT id, event_title, event_date, status, visibility, created_by_user_id 
        FROM events 
        WHERE id = :event_id
    ");
    $event_stmt->execute([':event_id' => $event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        send_error('Etkinlik bulunamadı');
    }
    
    // Etkinlik durumu kontrolü
    if ($event['status'] !== 'published') {
        send_error('Bu etkinliğe şu anda katılım sağlanamaz');
    }
    
    // Etkinlik tarihi kontrolü (geçmiş etkinlik)
    if (strtotime($event['event_date']) < time()) {
        send_error('Geçmiş etkinliklere katılım sağlanamaz');
    }
    
    // Görünürlük kontrolü
    if ($event['visibility'] === 'private') {
        $can_participate = has_permission($pdo, 'event.view_all') || $event['created_by_user_id'] == $current_user_id;
        if (!$can_participate) {
            send_error('Bu etkinliğe katılım yetkiniz yok');
        }
    }
    
    switch ($action) {
        case 'join_role':
            handleJoinRole($pdo, $event_id, $current_user_id);
            break;
            
        case 'leave_event':
            handleLeaveEvent($pdo, $event_id, $current_user_id);
            break;
            
        default:
            send_error('Geçersiz işlem');
    }
    
} catch (Exception $e) {
    error_log("Participation error: " . $e->getMessage());
    send_error('Katılım işlemi sırasında bir hata oluştu');
}

/**
 * Belirli role katılım işlemi
 */
function handleJoinRole($pdo, $event_id, $user_id) {
    $role_slot_id = (int)($_POST['role_slot_id'] ?? 0);
    
    if (!$role_slot_id) {
        send_error('Geçersiz rol slot ID');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mevcut katılımı kontrol et
        $existing_stmt = $pdo->prepare("
            SELECT id FROM event_participations 
            WHERE event_id = :event_id AND user_id = :user_id
        ");
        $existing_stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $user_id
        ]);
        
        if ($existing_stmt->fetch()) {
            $pdo->rollBack();
            send_error('Bu etkinliğe zaten katılmışsınız');
        }
        
        // Rol slot kontrolü
        $slot_stmt = $pdo->prepare("
            SELECT ers.slot_count, er.role_name,
                   COUNT(ep.id) as current_participants
            FROM event_role_slots ers
            JOIN event_roles er ON ers.role_id = er.id
            LEFT JOIN event_participations ep ON ers.id = ep.role_slot_id 
                AND ep.participation_status = 'confirmed'
            WHERE ers.id = :slot_id AND ers.event_id = :event_id
            GROUP BY ers.id, ers.slot_count, er.role_name
        ");
        $slot_stmt->execute([
            ':slot_id' => $role_slot_id,
            ':event_id' => $event_id
        ]);
        
        $slot_info = $slot_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$slot_info) {
            $pdo->rollBack();
            send_error('Geçersiz rol slot');
        }
        
        // Slot dolu mu kontrol et
        if ($slot_info['current_participants'] >= $slot_info['slot_count']) {
            $pdo->rollBack();
            send_error('Bu rol için tüm slotlar dolu');
        }
        
        // Katılımı kaydet (otomatik onay veya beklemede)
        $auto_approve = has_permission($pdo, 'event.auto_approve') || 
                       $slot_info['current_participants'] < $slot_info['slot_count'];
        
        $participation_status = $auto_approve ? 'confirmed' : 'pending';
        
        $insert_stmt = $pdo->prepare("
            INSERT INTO event_participations 
            (event_id, user_id, role_slot_id, participation_status, joined_at)
            VALUES (:event_id, :user_id, :role_slot_id, :status, NOW())
        ");
        
        $insert_stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $user_id,
            ':role_slot_id' => $role_slot_id,
            ':status' => $participation_status
        ]);
        
        $pdo->commit();
        
        $message = $participation_status === 'confirmed' 
            ? 'Etkinliğe başarıyla katıldınız!' 
            : 'Katılım talebiniz gönderildi, onay bekliyor.';
            
        send_success($message, [
            'participation_status' => $participation_status,
            'role_name' => $slot_info['role_name']
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Join role error: " . $e->getMessage());
        send_error('Katılım kaydedilirken bir hata oluştu');
    }
}

/**
 * Etkinlikten ayrılma işlemi
 */
function handleLeaveEvent($pdo, $event_id, $user_id) {
    try {
        $pdo->beginTransaction();
        
        // Mevcut katılımı kontrol et
        $participation_stmt = $pdo->prepare("
            SELECT ep.id, er.role_name
            FROM event_participations ep
            JOIN event_role_slots ers ON ep.role_slot_id = ers.id
            JOIN event_roles er ON ers.role_id = er.id
            WHERE ep.event_id = :event_id AND ep.user_id = :user_id
        ");
        $participation_stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $user_id
        ]);
        
        $participation = $participation_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$participation) {
            $pdo->rollBack();
            send_error('Bu etkinliğe katılmamışsınız');
        }
        
        // Katılımı sil
        $delete_stmt = $pdo->prepare("
            DELETE FROM event_participations 
            WHERE event_id = :event_id AND user_id = :user_id
        ");
        $delete_stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $user_id
        ]);
        
        $pdo->commit();
        
        send_success('Etkinlikten başarıyla ayrıldınız', [
            'role_name' => $participation['role_name']
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Leave event error: " . $e->getMessage());
        send_error('Ayrılma işlemi sırasında bir hata oluştu');
    }
}
?>