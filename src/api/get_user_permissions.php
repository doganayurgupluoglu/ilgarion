<?php
// src/api/get_user_permissions.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece GET isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece GET istekleri kabul edilir'
    ]);
    exit;
}

// Yetki kontrolü - Giriş yapmış kullanıcı olmalı
if (!is_user_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Bu API\'ye erişim için giriş yapmalısınız'
    ]);
    exit;
}

try {
    $current_user_id = $_SESSION['user_id'];
    $is_super_admin_user = is_super_admin($pdo, $current_user_id);
    
    // Kullanıcının tüm yetkilerini al
    $user_permissions = [];
    
    if ($is_super_admin_user) {
        // Süper admin ise tüm aktif yetkileri al
        $query = "SELECT permission_key FROM permissions WHERE is_active = 1 ORDER BY permission_key ASC";
        $stmt = execute_safe_query($pdo, $query);
        $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Normal kullanıcı ise sadece sahip olduğu yetkileri al
        $query = "
            SELECT DISTINCT p.permission_key
            FROM user_roles ur 
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id AND p.is_active = 1
            ORDER BY p.permission_key ASC
        ";
        
        $params = [':user_id' => $current_user_id];
        $stmt = execute_safe_query($pdo, $query, $params);
        $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Kullanıcının rol bilgilerini al
    $user_roles = get_user_roles($pdo, $current_user_id);
    $user_highest_priority = 999;
    $role_names = [];
    
    foreach ($user_roles as $role) {
        $role_names[] = $role['name'];
        if ($role['priority'] < $user_highest_priority) {
            $user_highest_priority = $role['priority'];
        }
    }
    
    // İstatistikler
    $stats = [
        'total_permissions' => count($user_permissions),
        'user_roles_count' => count($role_names),
        'hierarchy_level' => $user_highest_priority,
        'is_super_admin' => $is_super_admin_user
    ];
    
    // Yetkileri gruplar halinde organize et
    $grouped_permissions = [];
    if (!empty($user_permissions)) {
        $permissions_query = "
            SELECT permission_key, permission_name, permission_group 
            FROM permissions 
            WHERE permission_key IN (" . str_repeat('?,', count($user_permissions) - 1) . "?) 
            AND is_active = 1
            ORDER BY permission_group ASC, permission_key ASC
        ";
        
        $stmt = $pdo->prepare($permissions_query);
        $stmt->execute($user_permissions);
        $permission_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($permission_details as $permission) {
            $group = $permission['permission_group'];
            if (!isset($grouped_permissions[$group])) {
                $grouped_permissions[$group] = [];
            }
            $grouped_permissions[$group][$permission['permission_key']] = $permission['permission_name'];
        }
    }
    
    // Audit log - API erişimi
    audit_log($pdo, $current_user_id, 'user_permissions_accessed', 'user', $current_user_id, null, [
        'permission_count' => count($user_permissions),
        'access_method' => 'api',
        'is_super_admin' => $is_super_admin_user
    ]);
    
    echo json_encode([
        'success' => true,
        'user_id' => $current_user_id,
        'permissions' => $user_permissions,
        'grouped_permissions' => $grouped_permissions,
        'user_roles' => $role_names,
        'stats' => $stats,
        'hierarchy' => [
            'level' => $user_highest_priority,
            'can_manage_priority_above' => $user_highest_priority + 1,
            'is_super_admin' => $is_super_admin_user
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("User permissions API veritabanı hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("User permissions API genel hata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu'
    ]);
}
?>