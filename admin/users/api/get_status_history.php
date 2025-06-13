<?php
// /admin/users/api/get_status_history.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.users.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// ID parametresini kontrol et
$user_id = intval($_GET['id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı ID']);
    exit;
}

try {
    // Kullanıcının var olup olmadığını kontrol et
    $user_check = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $user_check->execute([':user_id' => $user_id]);
    
    if (!$user_check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }
    
    // Audit log'dan durum değişikliklerini getir
    $history_query = "
        SELECT 
            action,
            old_data,
            new_data,
            created_at
        FROM audit_log 
        WHERE target_type = 'user' 
        AND target_id = :user_id 
        AND (action = 'user_status_changed' OR action = 'user_created')
        ORDER BY created_at DESC
        LIMIT 50
    ";
    
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([':user_id' => $user_id]);
    $raw_history = $history_stmt->fetchAll();
    
    $status_history = [];
    
    foreach ($raw_history as $record) {
        $old_data = json_decode($record['old_data'], true);
        $new_data = json_decode($record['new_data'], true);
        
        if ($record['action'] === 'user_created') {
            $status_history[] = [
                'status' => $new_data['status'] ?? 'pending',
                'action' => 'created',
                'created_at' => $record['created_at']
            ];
        } else if ($record['action'] === 'user_status_changed') {
            $status_history[] = [
                'status' => $new_data['status'] ?? 'unknown',
                'old_status' => $old_data['status'] ?? null,
                'action' => 'status_changed',
                'created_at' => $record['created_at']
            ];
        }
    }
    
    // Eğer audit log'da hiç kayıt yoksa, mevcut durumu ekle
    if (empty($status_history)) {
        $current_user = $pdo->prepare("SELECT status, created_at FROM users WHERE id = :user_id");
        $current_user->execute([':user_id' => $user_id]);
        $user_data = $current_user->fetch();
        
        if ($user_data) {
            $status_history[] = [
                'status' => $user_data['status'],
                'action' => 'current',
                'created_at' => $user_data['created_at']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'history' => $status_history
    ]);

} catch (PDOException $e) {
    error_log("Get status history error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Get status history general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
}
?>