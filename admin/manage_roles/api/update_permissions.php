<?php
// /admin/manage_roles/api/update_permissions.php
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
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.roles.edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

// Form verilerini al ve validate et
$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
$permissions_json = $_POST['permissions'] ?? '[]';

// Validasyon
if (!$role_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rol ID gerekli']);
    exit;
}

try {
    // Permissions JSON'ını decode et
    $permissions = json_decode($permissions_json, true);
    if (!is_array($permissions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz yetki formatı']);
        exit;
    }
    
    // Rolü yönetme yetkisi kontrol et
    if (!can_user_manage_role($pdo, $role_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu rolün yetkilerini düzenleme yetkiniz yok']);
        exit;
    }
    
    // Rol varlığını kontrol et
    $role_query = "SELECT name FROM roles WHERE id = :role_id";
    $stmt_role = execute_safe_query($pdo, $role_query, [':role_id' => $role_id]);
    $role = $stmt_role->fetch();
    
    if (!$role) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
        exit;
    }
    
    // Mevcut yetkileri al (audit için)
    $old_permissions = get_role_permissions($pdo, $role_id);
    
    // Süper admin özel yetkilerini filtrele
    $filtered_permissions = filter_super_admin_permissions($pdo, $permissions);
    
    // Her permission key'i validate et
    foreach ($filtered_permissions as $permission_key) {
        if (!validate_sql_input($permission_key, 'permission_key')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Geçersiz yetki anahtarı: $permission_key"]);
            exit;
        }
    }
    
    // Güvenlik etkisi analizi
    $security_impact = analyze_role_security_impact($pdo, $role_id, $old_permissions, $filtered_permissions);
    
    // Transaction ile güncelle
    $result = execute_safe_transaction($pdo, function($pdo) use ($role_id, $filtered_permissions, $old_permissions, $role, $security_impact) {
        // Role yetkileri ata
        $success = assign_permissions_to_role($pdo, $role_id, $filtered_permissions, $_SESSION['user_id']);
        
        if (!$success) {
            throw new Exception('Yetki atama işlemi başarısız');
        }
        
        // Rol güvenlik logu
        log_role_security_event($pdo, 'role_permissions_updated', $role_id, [
            'role_name' => $role['name'],
            'old_permission_count' => count($old_permissions),
            'new_permission_count' => count($filtered_permissions),
            'security_impact' => $security_impact['impact_level'],
            'affected_users' => $security_impact['affected_users'],
            'critical_changes' => $security_impact['critical_changes']
        ]);
        
        return true;
    });
    
    if ($result) {
        $response = [
            'success' => true,
            'message' => 'Yetkiler başarıyla güncellendi',
            'security_impact' => $security_impact
        ];
        
        // Yüksek riskli değişikliklerde uyarı mesajı ekle
        if ($security_impact['impact_level'] === 'high') {
            $response['warning'] = 'Yüksek riskli yetki değişiklikleri yapıldı. Lütfen kullanıcı erişimlerini kontrol edin.';
        }
        
        echo json_encode($response);
    } else {
        throw new Exception('Yetki güncelleme işlemi başarısız');
    }
    
} catch (Exception $e) {
    error_log("Update permissions error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Yetkiler güncellenirken hata oluştu']);
}
?>