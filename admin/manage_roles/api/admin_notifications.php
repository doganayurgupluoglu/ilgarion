<?php
// src/api/admin_notifications.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.panel.access')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    $notifications = 0;
    $details = [];
    
    // Bekleyen kullanıcı sayısı
    if (has_permission($pdo, 'admin.users.view')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
        $stmt->execute();
        $pending_users = $stmt->fetchColumn();
        
        if ($pending_users > 0) {
            $notifications += $pending_users;
            $details[] = [
                'type' => 'pending_users',
                'count' => $pending_users,
                'message' => "$pending_users bekleyen üye onayı"
            ];
        }
    }
    
    // Bugünkü kritik audit log sayısı
    if (has_permission($pdo, 'admin.audit_log.view')) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM audit_log 
            WHERE DATE(created_at) = CURDATE() 
            AND action IN ('role_deleted', 'user_deleted', 'permission_updated')
        ");
        $stmt->execute();
        $critical_logs = $stmt->fetchColumn();
        
        if ($critical_logs > 0) {
            $details[] = [
                'type' => 'critical_logs',
                'count' => $critical_logs,
                'message' => "$critical_logs kritik sistem değişikliği"
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'details' => $details,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Admin notifications error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'notifications' => 0,
        'details' => []
    ]);
}
?>