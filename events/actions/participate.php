<?php
// /events/actions/participate.php - Etkinlik katılım işlemleri

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// JSON response fonksiyonu
function send_json_response($success = false, $message = '', $data = null, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Error response fonksiyonu
function send_error($message, $status_code = 400) {
    send_json_response(false, $message, null, $status_code);
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Geçersiz istek metodu', 405);
}

// Session kontrolü
try {
    check_user_session_validity();
    require_approved_user();
} catch (Exception $e) {
    send_error('Oturum geçersiz veya yetkiniz yok', 401);
}

$current_user_id = $_SESSION['user_id'];

// CSRF token kontrolü
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    send_error('Güvenlik hatası - CSRF token geçersiz', 403);
}

// Action parametresini al
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'join_role':
            handle_join_role();
            break;
            
        case 'leave_event':
            handle_leave_event();
            break;
            
        case 'update_status':
            handle_update_status();
            break;
            
        case 'approve_participant':
            handle_approve_participant();
            break;
            
        case 'remove_participant':
            handle_remove_participant();
            break;
            
        default:
            send_error('Geçersiz işlem');
    }
} catch (Exception $e) {
    error_log("Participate action error: " . $e->getMessage());
    send_error('İşlem sırasında bir hata oluştu: ' . $e->getMessage());
}

// Role katılım işlemi
function handle_join_role() {
    global $pdo, $current_user_id;
    
    $event_id = (int)($_POST['event_id'] ?? 0);
    $role_slot_id = (int)($_POST['role_slot_id'] ?? 0);
    
    if (!$event_id || !$role_slot_id) {
        send_error('Geçersiz etkinlik veya rol ID');
    }
    
    // Etkinlik ve rol slot kontrolü
    $stmt = $pdo->prepare("
        SELECT e.id as event_id, e.status, e.event_date,
               ers.id as slot_id, ers.slot_count, ers.role_id,
               er.role_name,
               (SELECT COUNT(*) FROM event_participations ep2 
                WHERE ep2.role_slot_id = ers.id AND ep2.participation_status = 'confirmed') as current_participants
        FROM events e
        JOIN event_role_slots ers ON e.id = ers.event_id
        JOIN event_roles er ON ers.role_id = er.id
        WHERE e.id = :event_id AND ers.id = :role_slot_id AND e.status = 'published'
    ");
    
    $stmt->execute([
        ':event_id' => $event_id,
        ':role_slot_id' => $role_slot_id
    ]);
    
    $event_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event_data) {
        send_error('Etkinlik veya rol bulunamadı');
    }
    
    // Etkinlik tarih kontrolü
    if (new DateTime($event_data['event_date']) < new DateTime()) {
        send_error('Geçmiş etkinliklere katılamazsınız');
    }
    
    // Slot doluluk kontrolü
    if ($event_data['current_participants'] >= $event_data['slot_count']) {
        send_error('Bu rol için boş slot bulunmuyor');
    }
    
    // Kullanıcının bu etkinlikte zaten katılımı var mı kontrol et
    $existing_stmt = $pdo->prepare("
        SELECT id, participation_status, role_slot_id 
        FROM event_participations 
        WHERE event_id = :event_id AND user_id = :user_id
    ");
    
    $existing_stmt->execute([
        ':event_id' => $event_id,
        ':user_id' => $current_user_id
    ]);
    
    $existing_participation = $existing_stmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->beginTransaction();
    
    try {
        if ($existing_participation) {
            // Mevcut katılımı güncelle
            $update_stmt = $pdo->prepare("
                UPDATE event_participations 
                SET role_slot_id = :role_slot_id, 
                    participation_status = 'confirmed',
                    updated_at = NOW()
                WHERE id = :participation_id
            ");
            
            $update_stmt->execute([
                ':role_slot_id' => $role_slot_id,
                ':participation_id' => $existing_participation['id']
            ]);
            
            $message = "Rol atanmanız güncellendi";
        } else {
            // Yeni katılım kaydı oluştur
            $insert_stmt = $pdo->prepare("
                INSERT INTO event_participations (event_id, user_id, role_slot_id, participation_status)
                VALUES (:event_id, :user_id, :role_slot_id, 'confirmed')
            ");
            
            $insert_stmt->execute([
                ':event_id' => $event_id,
                ':user_id' => $current_user_id,
                ':role_slot_id' => $role_slot_id
            ]);
            
            $message = "Role başarıyla katıldınız";
        }
        
        $pdo->commit();
        
        send_json_response(true, $message, [
            'role_name' => $event_data['role_name'],
            'participation_status' => 'confirmed'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Etkinlikten ayrılma işlemi
function handle_leave_event() {
    global $pdo, $current_user_id;
    
    $event_id = (int)($_POST['event_id'] ?? 0);
    
    if (!$event_id) {
        send_error('Geçersiz etkinlik ID');
    }
    
    // Kullanıcının katılımını kontrol et
    $stmt = $pdo->prepare("
        SELECT ep.id, e.event_date, e.status
        FROM event_participations ep
        JOIN events e ON ep.event_id = e.id
        WHERE ep.event_id = :event_id AND ep.user_id = :user_id
    ");
    
    $stmt->execute([
        ':event_id' => $event_id,
        ':user_id' => $current_user_id
    ]);
    
    $participation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participation) {
        send_error('Bu etkinlikte katılımınız bulunmuyor');
    }
    
    // Etkinlik tarih kontrolü (1 saat öncesine kadar çıkabilir)
    $event_time = new DateTime($participation['event_date']);
    $now = new DateTime();
    $time_diff = $event_time->getTimestamp() - $now->getTimestamp();
    
    if ($time_diff < 3600) { // 1 saat = 3600 saniye
        send_error('Etkinlik başlamadan 1 saat öncesine kadar çıkabilirsiniz');
    }
    
    // Katılımı sil
    $delete_stmt = $pdo->prepare("
        DELETE FROM event_participations 
        WHERE id = :participation_id
    ");
    
    $delete_stmt->execute([':participation_id' => $participation['id']]);
    
    send_json_response(true, 'Etkinlikten başarıyla ayrıldınız');
}

// Katılım durumu güncelleme (maybe/declined)
function handle_update_status() {
    global $pdo, $current_user_id;
    
    $event_id = (int)($_POST['event_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    if (!$event_id || !in_array($status, ['maybe', 'declined'])) {
        send_error('Geçersiz parametreler');
    }
    
    // Etkinlik kontrolü
    $event_stmt = $pdo->prepare("
        SELECT id, status, event_date 
        FROM events 
        WHERE id = :event_id AND status = 'published'
    ");
    
    $event_stmt->execute([':event_id' => $event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        send_error('Etkinlik bulunamadı');
    }
    
    // Etkinlik tarih kontrolü
    if (new DateTime($event['event_date']) < new DateTime()) {
        send_error('Geçmiş etkinlikler için durum değiştiremezsiniz');
    }
    
    // Mevcut katılımı kontrol et
    $participation_stmt = $pdo->prepare("
        SELECT id, participation_status 
        FROM event_participations 
        WHERE event_id = :event_id AND user_id = :user_id
    ");
    
    $participation_stmt->execute([
        ':event_id' => $event_id,
        ':user_id' => $current_user_id
    ]);
    
    $existing = $participation_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Mevcut kaydı güncelle
        $update_stmt = $pdo->prepare("
            UPDATE event_participations 
            SET participation_status = :status,
                role_slot_id = NULL,
                updated_at = NOW()
            WHERE id = :participation_id
        ");
        
        $update_stmt->execute([
            ':status' => $status,
            ':participation_id' => $existing['id']
        ]);
    } else {
        // Yeni kayıt oluştur
        $insert_stmt = $pdo->prepare("
            INSERT INTO event_participations (event_id, user_id, participation_status)
            VALUES (:event_id, :user_id, :status)
        ");
        
        $insert_stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $current_user_id,
            ':status' => $status
        ]);
    }
    
    $status_text = $status === 'maybe' ? 'Belki Katılırım' : 'Katılmıyorum';
    send_json_response(true, "Durumunuz \"$status_text\" olarak güncellendi", [
        'status' => $status,
        'status_text' => $status_text
    ]);
}

// Katılımcıyı onaylama (organize edenler için)
function handle_approve_participant() {
    global $pdo, $current_user_id;
    
    $participation_id = (int)($_POST['participation_id'] ?? 0);
    
    if (!$participation_id) {
        send_error('Geçersiz katılım ID');
    }
    
    // Katılım kaydını ve yetkileri kontrol et
    $stmt = $pdo->prepare("
        SELECT ep.*, e.created_by_user_id
        FROM event_participations ep
        JOIN events e ON ep.event_id = e.id
        WHERE ep.id = :participation_id
    ");
    
    $stmt->execute([':participation_id' => $participation_id]);
    $participation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participation) {
        send_error('Katılım kaydı bulunamadı');
    }
    
    // Yetki kontrolü - etkinlik sahibi veya admin
    $can_manage = has_permission($pdo, 'event.manage_participants') || 
                  $participation['created_by_user_id'] == $current_user_id;
    
    if (!$can_manage) {
        send_error('Bu işlemi yapma yetkiniz yok', 403);
    }
    
    // Onaylama
    $update_stmt = $pdo->prepare("
        UPDATE event_participations 
        SET participation_status = 'confirmed', updated_at = NOW()
        WHERE id = :participation_id
    ");
    
    $update_stmt->execute([':participation_id' => $participation_id]);
    
    send_json_response(true, 'Katılımcı onaylandı');
}

// Katılımcıyı kaldırma (organize edenler için)
function handle_remove_participant() {
    global $pdo, $current_user_id;
    
    $participation_id = (int)($_POST['participation_id'] ?? 0);
    
    if (!$participation_id) {
        send_error('Geçersiz katılım ID');
    }
    
    // Katılım kaydını ve yetkileri kontrol et
    $stmt = $pdo->prepare("
        SELECT ep.*, e.created_by_user_id, u.username
        FROM event_participations ep
        JOIN events e ON ep.event_id = e.id
        JOIN users u ON ep.user_id = u.id
        WHERE ep.id = :participation_id
    ");
    
    $stmt->execute([':participation_id' => $participation_id]);
    $participation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participation) {
        send_error('Katılım kaydı bulunamadı');
    }
    
    // Yetki kontrolü - etkinlik sahibi veya admin
    $can_manage = has_permission($pdo, 'event.manage_participants') || 
                  $participation['created_by_user_id'] == $current_user_id;
    
    if (!$can_manage) {
        send_error('Bu işlemi yapma yetkiniz yok', 403);
    }
    
    // Kaldırma
    $delete_stmt = $pdo->prepare("
        DELETE FROM event_participations 
        WHERE id = :participation_id
    ");
    
    $delete_stmt->execute([':participation_id' => $participation_id]);
    
    send_json_response(true, $participation['username'] . ' etkinlikten kaldırıldı');
}
?>