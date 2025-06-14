<?php
// /admin/audit/api/get_recent_activity.php

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
    $limit = max(1, min(50, intval($_GET['limit'] ?? 10)));
    
    // Son aktiviteleri al
    $query = "
        SELECT 
            al.id,
            al.user_id,
            u.username,
            al.action,
            al.target_type,
            al.target_id,
            al.ip_address,
            al.created_at
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY al.created_at DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Her aktivite için ek bilgiler ekle
    foreach ($activities as &$activity) {
        $activity['relative_time'] = getRelativeTime($activity['created_at']);
        $activity['action_type'] = getActionType($activity['action']);
        $activity['display_text'] = generateDisplayText($activity);
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'total_count' => count($activities)
    ]);

} catch (Exception $e) {
    error_log("Recent activity API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Son aktiviteler yüklenirken hata oluştu'
    ]);
}

/**
 * Göreceli zamanı hesapla
 */
function getRelativeTime($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days > 0) {
        return $diff->days . ' gün önce';
    } elseif ($diff->h > 0) {
        return $diff->h . ' saat önce';
    } elseif ($diff->i > 0) {
        return $diff->i . ' dakika önce';
    } else {
        return 'Az önce';
    }
}

/**
 * Action tipini belirle
 */
function getActionType($action) {
    if (strpos($action, 'create') !== false || strpos($action, 'add') !== false) {
        return 'create';
    }
    if (strpos($action, 'update') !== false || strpos($action, 'edit') !== false) {
        return 'update';
    }
    if (strpos($action, 'delete') !== false || strpos($action, 'remove') !== false) {
        return 'delete';
    }
    if (strpos($action, 'login') !== false) {
        return 'login';
    }
    if (strpos($action, 'logout') !== false) {
        return 'logout';
    }
    if (strpos($action, 'view') !== false || strpos($action, 'access') !== false) {
        return 'view';
    }
    return 'other';
}

/**
 * Görüntülenecek metni oluştur
 */
function generateDisplayText($activity) {
    $username = $activity['username'] ?: 'System';
    $action = $activity['action'];
    $targetType = $activity['target_type'];
    $targetId = $activity['target_id'];
    
    // Action'ı daha okunabilir hale getir
    $actionText = str_replace('_', ' ', $action);
    $actionText = ucfirst($actionText);
    
    $text = $username . ' - ' . $actionText;
    
    if ($targetType && $targetId) {
        $text .= ' (' . $targetType . ' #' . $targetId . ')';
    } elseif ($targetType) {
        $text .= ' (' . $targetType . ')';
    }
    
    return $text;
}
?>