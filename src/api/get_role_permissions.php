<?php
// src/api/get_role_permissions.php

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

// Yetki kontrolü
try {
    require_permission($pdo, 'admin.roles.view');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Bu API\'ye erişim yetkiniz bulunmamaktadır'
    ]);
    exit;
}

// Parametreleri al ve validate et
$role_id = $_GET['role_id'] ?? null;

if (!$role_id || !is_numeric($role_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir rol ID\'si gereklidir'
    ]);
    exit;
}

$role_id = (int)$role_id;

try {
    // Önce rolün var olup olmadığını kontrol et
    $role_check_query = "SELECT name FROM roles WHERE id = :role_id";
    $stmt_check = $pdo->prepare($role_check_query);
    $stmt_check->execute([':role_id' => $role_id]);
    $role = $stmt_check->fetch();
    
    if (!$role) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rol bulunamadı'
        ]);
        exit;
    }
    
    // Rol yetkilerini detaylı olarak çek
    $permissions_query = "
        SELECT 
            p.id,
            p.permission_key,
            p.permission_name,
            p.permission_group,
            p.is_active
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = :role_id AND p.is_active = 1
        ORDER BY p.permission_group ASC, p.permission_key ASC
    ";
    
    $stmt_permissions = $pdo->prepare($permissions_query);
    $stmt_permissions->execute([':role_id' => $role_id]);
    $permissions = $stmt_permissions->fetchAll(PDO::FETCH_ASSOC);
    
    // İstatistikler hesapla
    $stats = [
        'total_permissions' => count($permissions),
        'groups' => []
    ];
    
    // Grup bazında istatistikler
    foreach ($permissions as $permission) {
        $group = $permission['permission_group'];
        if (!isset($stats['groups'][$group])) {
            $stats['groups'][$group] = 0;
        }
        $stats['groups'][$group]++;
    }
    
    // Kullanıcı hiyerarşi bilgileri (log için)
    $current_user_id = $_SESSION['user_id'];
    $is_super_admin_user = is_super_admin($pdo);
    
    // Audit log - yetkiler görüntülendi
    audit_log($pdo, $current_user_id, 'role_permissions_viewed', 'role', $role_id, null, [
        'role_name' => $role['name'],
        'permission_count' => count($permissions),
        'access_method' => 'api_view'
    ]);
    
    echo json_encode([
        'success' => true,
        'role_id' => $role_id,
        'role_name' => $role['name'],
        'permissions' => $permissions,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("Rol permissions API veritabanı hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Rol permissions API genel hata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu'
    ]);
}