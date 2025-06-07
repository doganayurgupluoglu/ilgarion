<?php
// /events/roles/actions/delete_role.php - Etkinlik rolü silme işlemi

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// JSON response için header ayarla
header('Content-Type: application/json');

// Hata raporlama
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Başarı response
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
        send_error('Geçersiz istek metodu');
    }
    
    // JSON verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Geçersiz JSON verisi');
    }
    
    // CSRF token kontrolü
    if (!verify_csrf_token($input['csrf_token'] ?? '')) {
        send_error('Güvenlik hatası - CSRF token geçersiz', 403);
    }
    
    // Role ID kontrolü
    $role_id = $input['role_id'] ?? null;
    if (!$role_id || !is_numeric($role_id)) {
        send_error('Geçersiz rol ID');
    }
    
    $role_id = (int)$role_id;
    $current_user_id = $_SESSION['user_id'];
    
    // Yetki kontrolleri
    $can_delete_all = has_permission($pdo, 'event_role.delete_all');
    
    // Rolü getir ve yetki kontrolü yap
    $stmt = $pdo->prepare("
        SELECT er.*, 
               u.username as creator_username,
               (SELECT COUNT(*) FROM event_role_assignments era WHERE era.event_role_id = er.id) as assignment_count,
               (SELECT COUNT(*) FROM event_participants ep 
                WHERE ep.event_role_id = er.id AND ep.status = 'confirmed') as active_participants
        FROM event_roles er
        LEFT JOIN users u ON er.created_by_user_id = u.id
        WHERE er.id = :role_id
    ");
    
    $stmt->execute([':role_id' => $role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        send_error('Rol bulunamadı', 404);
    }
    
    // Silme yetkisi kontrolü
    if ($role['created_by_user_id'] != $current_user_id && !$can_delete_all) {
        send_error('Bu rolü silme yetkiniz bulunmuyor', 403);
    }
    
    // Aktif katılımcı kontrolü
    if ($role['active_participants'] > 0) {
        send_error(
            "Bu rol şu anda {$role['active_participants']} aktif katılımcı tarafından kullanılmakta. " .
            "Rolü silebilmek için önce tüm katılımcıların atamalarını kaldırmanız gerekiyor.",
            400
        );
    }
    
    $pdo->beginTransaction();
    
    try {
        // 1. Önce rol gereksinimlerini sil
        $delete_requirements = $pdo->prepare("DELETE FROM event_role_requirements WHERE event_role_id = :role_id");
        $delete_requirements->execute([':role_id' => $role_id]);
        $deleted_requirements = $delete_requirements->rowCount();
        
        // 2. Geçmiş atamaları sil (sadece tamamlanmış/iptal edilmiş etkinlikler için)
        $delete_assignments = $pdo->prepare("
            DELETE era FROM event_role_assignments era
            INNER JOIN events e ON era.event_id = e.id
            WHERE era.event_role_id = :role_id 
            AND e.status IN ('completed', 'cancelled')
        ");
        $delete_assignments->execute([':role_id' => $role_id]);
        $deleted_assignments = $delete_assignments->rowCount();
        
        // 3. Aktif olmayan katılımcı kayıtlarını sil
        $delete_participants = $pdo->prepare("
            DELETE ep FROM event_participants ep
            INNER JOIN events e ON ep.event_id = e.id
            WHERE ep.event_role_id = :role_id 
            AND (ep.status IN ('cancelled', 'rejected') OR e.status IN ('completed', 'cancelled'))
        ");
        $delete_participants->execute([':role_id' => $role_id]);
        $deleted_participants = $delete_participants->rowCount();
        
        // 4. Son olarak rolü sil
        $delete_role = $pdo->prepare("DELETE FROM event_roles WHERE id = :role_id");
        $delete_role->execute([':role_id' => $role_id]);
        
        if ($delete_role->rowCount() === 0) {
            throw new Exception('Rol silinemedi - beklenmeyen hata');
        }
        
        $pdo->commit();
        
        // Log kaydı
        error_log(sprintf(
            "Role deleted by user %d: Role ID %d ('%s'), Requirements: %d, Assignments: %d, Participants: %d",
            $current_user_id,
            $role_id,
            $role['role_name'],
            $deleted_requirements,
            $deleted_assignments,
            $deleted_participants
        ));
        
        send_success('Rol başarıyla silindi', [
            'deleted_role_id' => $role_id,
            'deleted_requirements' => $deleted_requirements,
            'deleted_assignments' => $deleted_assignments,
            'deleted_participants' => $deleted_participants
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Role deletion error (ID: $role_id, User: $current_user_id): " . $e->getMessage());
        send_error('Rol silinirken bir hata oluştu: ' . $e->getMessage());
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in role deletion: " . $e->getMessage());
    
    // Foreign key constraint hatalarını kullanıcı dostu hale getir
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        send_error(
            'Bu rol başka kayıtlar tarafından kullanıldığı için silinemez. ' .
            'Önce rolü kullanan tüm etkinlikleri ve katılımcıları kaldırmanız gerekiyor.'
        );
    }
    
    send_error('Veritabanı hatası oluştu');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("General error in role deletion: " . $e->getMessage());
    send_error('Beklenmeyen bir hata oluştu: ' . $e->getMessage());
}
?>