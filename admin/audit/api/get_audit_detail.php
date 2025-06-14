<?php
// /admin/audit/api/get_audit_detail.php

header('Content-Type: application/json; charset=utf-8');

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
if (!is_user_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!has_permission($pdo, 'admin.audit_log.view')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    $logId = intval($_GET['id'] ?? 0);
    
    if ($logId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz log ID']);
        exit;
    }
    
    // Audit log detayını al
    $query = "
        SELECT 
            al.id,
            al.user_id,
            u.username,
            u.email,
            al.action,
            al.target_type,
            al.target_id,
            al.old_values,
            al.new_values,
            al.ip_address,
            al.user_agent,
            al.created_at
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.id = :log_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);
    $stmt->execute();
    
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Audit log bulunamadı']);
        exit;
    }
    
    // JSON değerlerini parse et
    if ($log['old_values']) {
        $log['old_values_parsed'] = json_decode($log['old_values'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log['old_values_parsed'] = null;
        }
    }
    
    if ($log['new_values']) {
        $log['new_values_parsed'] = json_decode($log['new_values'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log['new_values_parsed'] = null;
        }
    }
    
    // User agent bilgisini parse et
    $log['user_agent_info'] = parseUserAgent($log['user_agent']);
    
    // IP bilgisini geliştir
    $log['ip_info'] = getIpInfo($log['ip_address']);
    
    echo json_encode([
        'success' => true,
        'log' => $log
    ]);

} catch (Exception $e) {
    error_log("Audit detail API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Audit detayı yüklenirken hata oluştu'
    ]);
}

/**
 * User agent bilgisini parse et
 */
function parseUserAgent($userAgent) {
    if (empty($userAgent)) {
        return null;
    }
    
    $info = [
        'browser' => 'Bilinmeyen',
        'version' => '',
        'platform' => 'Bilinmeyen',
        'is_mobile' => false
    ];
    
    // Basit user agent parsing
    if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
        $info['browser'] = 'Chrome';
        $info['version'] = $matches[1];
    } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
        $info['browser'] = 'Firefox';
        $info['version'] = $matches[1];
    } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
        $info['browser'] = 'Safari';
        $info['version'] = $matches[1];
    } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
        $info['browser'] = 'Edge';
        $info['version'] = $matches[1];
    }
    
    // Platform detection
    if (strpos($userAgent, 'Windows') !== false) {
        $info['platform'] = 'Windows';
    } elseif (strpos($userAgent, 'Macintosh') !== false) {
        $info['platform'] = 'macOS';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        $info['platform'] = 'Linux';
    } elseif (strpos($userAgent, 'Android') !== false) {
        $info['platform'] = 'Android';
        $info['is_mobile'] = true;
    } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
        $info['platform'] = 'iOS';
        $info['is_mobile'] = true;
    }
    
    // Mobile detection
    if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
        $info['is_mobile'] = true;
    }
    
    return $info;
}

/**
 * IP adresi bilgilerini al
 */
function getIpInfo($ipAddress) {
    if (empty($ipAddress)) {
        return null;
    }
    
    $info = [
        'ip' => $ipAddress,
        'type' => 'Unknown',
        'is_local' => false,
        'is_private' => false
    ];
    
    // Local IP kontrolü
    if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
        $info['type'] = 'Localhost';
        $info['is_local'] = true;
        return $info;
    }
    
    // Private IP kontrolü
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
        $info['is_private'] = true;
        $info['type'] = 'Private Network';
    } else {
        $info['type'] = 'Public';
    }
    
    // IPv4 vs IPv6
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $info['version'] = 'IPv4';
    } elseif (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $info['version'] = 'IPv6';
    }
    
    return $info;
}
?>