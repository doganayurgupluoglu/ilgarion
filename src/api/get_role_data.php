<?php
// src/api/get_role_data.php - Düzeltilmiş Hiyerarşi Kontrolü

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hata raporlamayı etkinleştir (geliştirme aşamasında)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once BASE_PATH . '/src/functions/auth_functions.php';
    require_once BASE_PATH . '/src/functions/role_functions.php';
    require_once BASE_PATH . '/src/functions/sql_security_functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Dosya yükleme hatası: ' . $e->getMessage()
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

// Yetki kontrolü
try {
    if (!is_user_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Bu API\'ye erişim için giriş yapmalısınız'
        ]);
        exit;
    }

    // Rol görüntüleme yetkisi kontrolü
    if (!has_permission($pdo, 'admin.roles.view')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu API\'ye erişim yetkiniz bulunmamaktadır'
        ]);
        exit;
    }
} catch (Exception $e) {
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
        'message' => 'Geçerli bir rol ID\'si gereklidir'
    ]);
    exit;
}

$role_id = (int)$role_id;

try {
    // Kullanıcının hiyerarşik yetkilerini kontrol et
    $current_user_id = $_SESSION['user_id'];
    $is_super_admin_user = is_super_admin($pdo);
    
    // Kullanıcının hiyerarşik konumunu belirle
    $user_roles = get_user_roles($pdo, $current_user_id);
    $user_highest_priority = 999;
    foreach ($user_roles as $role) {
        if ($role['priority'] < $user_highest_priority) {
            $user_highest_priority = $role['priority'];
        }
    }
    
    // ⚠️ DÜZELTME: Rol bilgilerini basit sorgu ile çek
    $role_query = "
        SELECT r.*, 
               COUNT(DISTINCT ur.user_id) as user_count
        FROM roles r
        LEFT JOIN user_roles ur ON r.id = ur.user_id
        WHERE r.id = :role_id
        GROUP BY r.id, r.name, r.description, r.color, r.priority, r.created_at, r.updated_at
    ";
    
    $stmt = $pdo->prepare($role_query);
    $stmt->execute([':role_id' => $role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Rol bulunamadı'
        ]);
        exit;
    }
    
    // ⚠️ DÜZELTME: Hiyerarşi kontrolü - PHP tarafında yapılacak
    $can_manage = false;
    
    if ($is_super_admin_user) {
        // Süper admin her rolü yönetebilir
        $can_manage = true;
    } else {
        // Normal admin: sadece daha düşük öncelikli rolleri yönetebilir
        // DÜŞÜK SAYI = YÜKSEK ÖNCELİK, bu yüzden hedef rolün priority'si büyük olmalı
        if ($role['priority'] > $user_highest_priority) {
            $can_manage = true;
        }
        
        // Admin rolü özel kontrolü - sadece süper adminler yönetebilir
        if ($role['name'] === 'admin' && !$is_super_admin_user) {
            $can_manage = false;
        }
    }
    
    // Hiyerarşi kontrolü - Kullanıcı bu rolü yönetebilir mi?
    if (!$can_manage) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bu rolü düzenleme yetkiniz bulunmamaktadır (hiyerarşi kısıtlaması)',
            'debug' => [
                'user_priority' => $user_highest_priority,
                'role_priority' => $role['priority'], 
                'role_name' => $role['name'],
                'is_super_admin' => $is_super_admin_user,
                'can_manage_logic' => "Rol priority ({$role['priority']}) > User priority ({$user_highest_priority}) = " . ($role['priority'] > $user_highest_priority ? 'true' : 'false')
            ]
        ]);
        exit;
    }
    
    // Rol yetkilerini çek
    $permissions_query = "
        SELECT p.permission_key
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = :role_id AND p.is_active = 1
        ORDER BY p.permission_key ASC
    ";
    
    $stmt_permissions = $pdo->prepare($permissions_query);
    $stmt_permissions->execute([':role_id' => $role_id]);
    $permissions = $stmt_permissions->fetchAll(PDO::FETCH_COLUMN);
    
    // Korumalı rollerin kontrolü için fonksiyonları kontrol et
    $is_protected = false;
    $is_name_editable = true;
    
    if (function_exists('is_role_deletable')) {
        $is_protected = !is_role_deletable($role['name']);
    }
    
    if (function_exists('is_role_name_editable')) {
        $is_name_editable = is_role_name_editable($role['name']);
    }
    
    // Rol verisini hazırla
    $role_data = [
        'id' => (int)$role['id'],
        'name' => $role['name'],
        'description' => $role['description'],
        'color' => $role['color'],
        'priority' => (int)$role['priority'],
        'user_count' => (int)$role['user_count'],
        'can_manage' => $can_manage,
        'permissions' => $permissions,
        'is_protected' => $is_protected,
        'is_name_editable' => $is_name_editable,
        'created_at' => $role['created_at'],
        'updated_at' => $role['updated_at']
    ];
    
    // Audit log - API erişimi
    try {
        if (function_exists('audit_log')) {
            audit_log($pdo, $current_user_id, 'role_data_accessed', 'role', $role_id, null, [
                'role_name' => $role['name'],
                'access_method' => 'api',
                'can_manage' => $can_manage
            ]);
        }
    } catch (Exception $e) {
        // Audit log hatası kritik değil, devam et
        error_log("Audit log hatası: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'role' => $role_data,
        'user_hierarchy' => [
            'user_priority' => $user_highest_priority,
            'is_super_admin' => $is_super_admin_user,
            'can_manage_this_role' => $can_manage
        ],
        'debug' => [
            'hierarchy_logic' => "User priority: {$user_highest_priority}, Role priority: {$role['priority']}, Can manage: " . ($can_manage ? 'YES' : 'NO'),
            'role_name' => $role['name']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Rol data API veritabanı hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu',
        'debug' => $e->getMessage() // Geliştirme aşamasında
    ]);
} catch (Exception $e) {
    error_log("Rol data API genel hata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu',
        'debug' => $e->getMessage(), // Geliştirme aşamasında
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>