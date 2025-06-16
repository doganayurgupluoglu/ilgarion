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
require_once BASE_PATH . '/src/functions/audit_functions.php'; // Audit log eklendi

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.roles.edit')) {
    // Yetkisiz yetki güncelleme denemesi audit log
    add_audit_log($pdo, 'Yetkisiz rol yetkileri güncelleme denemesi', 'security', null, null, [
        'attempted_action' => 'update_permissions',
        'ip_address' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
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
    add_audit_log($pdo, 'Geçersiz rol ID ile yetki güncelleme denemesi', 'security', null, null, [
        'attempted_role_id' => $role_id,
        'error' => 'missing_role_id'
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rol ID gerekli']);
    exit;
}

try {
    // Permissions JSON'ını decode et
    $permissions = json_decode($permissions_json, true);
    if (!is_array($permissions)) {
        add_audit_log($pdo, 'Geçersiz yetki formatı ile güncelleme denemesi', 'security', 'role', $role_id, null, [
            'attempted_permissions_json' => substr($permissions_json, 0, 200), // İlk 200 karakter
            'error' => 'invalid_permissions_format'
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Geçersiz yetki formatı']);
        exit;
    }
    
    // Rolü yönetme yetkisi kontrol et
    if (!can_user_manage_role($pdo, $role_id)) {
        add_audit_log($pdo, 'Yetki dışı rol yetkilerini güncelleme denemesi', 'security', 'role', $role_id, null, [
            'attempted_role_id' => $role_id,
            'error' => 'insufficient_permissions'
        ]);
        
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu rolün yetkilerini düzenleme yetkiniz yok']);
        exit;
    }
    
    // Rol varlığını kontrol et
    $role_query = "SELECT name FROM roles WHERE id = :role_id";
    $stmt_role = execute_safe_query($pdo, $role_query, [':role_id' => $role_id]);
    $role = $stmt_role->fetch();
    
    if (!$role) {
        add_audit_log($pdo, 'Olmayan rol yetkilerini güncelleme denemesi', 'security', 'role', $role_id, null, [
            'attempted_role_id' => $role_id,
            'error' => 'role_not_found'
        ]);
        
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
            add_audit_log($pdo, 'Geçersiz yetki anahtarı ile güncelleme denemesi', 'security', 'role', $role_id, null, [
                'role_name' => $role['name'],
                'invalid_permission_key' => $permission_key,
                'error' => 'invalid_permission_key'
            ]);
            
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Geçersiz yetki anahtarı: $permission_key"]);
            exit;
        }
    }
    
    // Güvenlik etkisi analizi
    $security_impact = analyze_role_security_impact($pdo, $role_id, $old_permissions, $filtered_permissions);
    
    // Yetki değişiklikleri detayları
    $added_permissions = array_diff($filtered_permissions, $old_permissions);
    $removed_permissions = array_diff($old_permissions, $filtered_permissions);
    
    // Transaction ile güncelle
    $result = execute_safe_transaction($pdo, function($pdo) use ($role_id, $filtered_permissions, $old_permissions, $role, $security_impact, $added_permissions, $removed_permissions) {
        // Role yetkileri ata
        $success = assign_permissions_to_role($pdo, $role_id, $filtered_permissions, $_SESSION['user_id']);
        
        if (!$success) {
            throw new Exception('Yetki atama işlemi başarısız');
        }
        
        // Başarılı rol yetkileri güncelleme audit log (Türkçe mesaj)
        audit_role_permissions_updated($pdo, $role_id, $old_permissions, $filtered_permissions, $_SESSION['user_id']);
        
        // Detaylı yetki değişiklikleri audit log
        if (!empty($added_permissions) || !empty($removed_permissions)) {
            add_audit_log($pdo, 'Rol yetki değişiklikleri detayı', 'role', $role_id, [
                'role_name' => $role['name'],
                'old_permission_count' => count($old_permissions),
                'removed_permissions' => $removed_permissions
            ], [
                'role_name' => $role['name'],
                'new_permission_count' => count($filtered_permissions),
                'added_permissions' => $added_permissions,
                'security_impact' => $security_impact['impact_level']
            ]);
        }
        
        // Yüksek riskli değişikliklerde özel audit log
        if ($security_impact['impact_level'] === 'high') {
            add_audit_log($pdo, 'Yüksek riskli yetki değişikliği yapıldı', 'security', 'role', $role_id, null, [
                'role_name' => $role['name'],
                'affected_users' => $security_impact['affected_users'],
                'critical_changes' => $security_impact['critical_changes'],
                'risk_level' => 'high'
            ]);
        }
        
        // Rol güvenlik logu
        if (function_exists('log_role_security_event')) {
            log_role_security_event($pdo, 'role_permissions_updated', $role_id, [
                'role_name' => $role['name'],
                'old_permission_count' => count($old_permissions),
                'new_permission_count' => count($filtered_permissions),
                'security_impact' => $security_impact['impact_level'],
                'affected_users' => $security_impact['affected_users'],
                'critical_changes' => $security_impact['critical_changes']
            ]);
        }
        
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
    // Hata durumunda audit log
    add_audit_log($pdo, 'Rol yetkileri güncelleme hatası', 'error', 'role', $role_id ?? null, null, [
        'attempted_role_id' => $role_id ?? null,
        'role_name' => $role['name'] ?? 'unknown',
        'error_message' => $e->getMessage(),
        'error_type' => 'permissions_update_failed'
    ]);
    
    error_log("Update permissions error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Yetkiler güncellenirken hata oluştu']);
}
?>