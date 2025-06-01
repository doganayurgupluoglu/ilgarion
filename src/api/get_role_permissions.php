<?php
// src/api/get_role_permissions.php - DÜZELTME

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error handling ve debugging için
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once BASE_PATH . '/src/functions/auth_functions.php';
    require_once BASE_PATH . '/src/functions/role_functions.php';
    require_once BASE_PATH . '/src/functions/sql_security_functions.php';
} catch (Exception $e) {
    error_log("File include error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Dosya yükleme hatası: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    exit;
}

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

// Yetki kontrolü - DÜZELTME: Try-catch ekledik
try {
    if (!is_user_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Bu API\'ye erişim için giriş yapmalısınız'
        ]);
        exit;
    }

    if (!has_permission($pdo, 'admin.roles.view')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu API\'ye erişim yetkiniz bulunmamaktadır'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Permission check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Yetki kontrolü hatası: ' . $e->getMessage()
    ]);
    exit;
}

// Parametreleri al ve validate et
$role_id = $_GET['role_id'] ?? null;

if (!$role_id || !is_numeric($role_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir rol ID\'si gereklidir',
        'received_role_id' => $role_id
    ]);
    exit;
}

$role_id = (int)$role_id;

try {
    // Önce rolün var olup olmadığını kontrol et
    $role_check_query = "SELECT name, description FROM roles WHERE id = :role_id";
    $stmt_check = $pdo->prepare($role_check_query);
    $stmt_check->execute([':role_id' => $role_id]);
    $role = $stmt_check->fetch();
    
    if (!$role) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rol bulunamadı',
            'role_id' => $role_id
        ]);
        exit;
    }
    
    // Rol yetkilerini detaylı olarak çek - DÜZELTME: Daha güvenli sorgu
    $permissions_query = "
        SELECT 
            p.id,
            p.permission_key,
            p.permission_name,
            p.permission_group,
            p.is_active,
            rp.granted_at
        FROM role_permissions rp
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = :role_id AND p.is_active = 1
        ORDER BY p.permission_group ASC, p.permission_key ASC
    ";
    
    $stmt_permissions = $pdo->prepare($permissions_query);
    $stmt_permissions->execute([':role_id' => $role_id]);
    $permissions = $stmt_permissions->fetchAll(PDO::FETCH_ASSOC);
    
    // İstatistikler hesapla
    $stats = [
        'total_permissions' => count($permissions),
        'groups' => [],
        'group_counts' => []
    ];
    
    // Grup bazında organize et - DÜZELTME: Daha detaylı organizasyon
    $grouped_permissions = [];
    foreach ($permissions as $permission) {
        $group = ucfirst($permission['permission_group']);
        
        if (!isset($grouped_permissions[$group])) {
            $grouped_permissions[$group] = [];
            $stats['groups'][$group] = 0;
        }
        
        $grouped_permissions[$group][] = [
            'id' => $permission['id'],
            'key' => $permission['permission_key'],
            'name' => $permission['permission_name'],
            'granted_at' => $permission['granted_at']
        ];
        
        $stats['groups'][$group]++;
    }
    
    // Kullanıcı hiyerarşi bilgileri (log için)
    $current_user_id = $_SESSION['user_id'];
    $is_super_admin_user = is_super_admin($pdo);
    
    // DÜZELTME: Audit log'u try-catch içine aldık
    try {
        audit_log($pdo, $current_user_id, 'role_permissions_viewed', 'role', $role_id, null, [
            'role_name' => $role['name'],
            'permission_count' => count($permissions),
            'access_method' => 'api_view'
        ]);
    } catch (Exception $e) {
        // Audit log hatası kritik değil, sadece logla
        error_log("Audit log error (non-critical): " . $e->getMessage());
    }
    
    // BAŞARILI YANIT
    echo json_encode([
        'success' => true,
        'role_id' => $role_id,
        'role_name' => $role['name'],
        'role_description' => $role['description'],
        'permissions' => $permissions,
        'grouped_permissions' => $grouped_permissions,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'query_executed' => true,
            'permission_count' => count($permissions),
            'group_count' => count($grouped_permissions)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Rol permissions API veritabanı hatası: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Query context: role_id = " . $role_id);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu',
        'error_code' => $e->getCode(),
        'debug' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Exception $e) {
    error_log("Rol permissions API genel hata: " . $e->getMessage());
    error_log("Error context: role_id = " . $role_id);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu',
        'debug' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>