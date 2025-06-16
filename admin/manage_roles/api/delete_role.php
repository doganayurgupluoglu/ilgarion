<?php
// /admin/manage_roles/api/delete_role.php
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
if (!is_user_logged_in() || !has_permission($pdo, 'admin.roles.delete')) {
    // Yetkisiz rol silme denemesi audit log
    add_audit_log($pdo, 'Yetkisiz rol silme denemesi', 'security', null, null, [
        'attempted_action' => 'delete_role',
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

// Validasyon
if (!$role_id) {
    add_audit_log($pdo, 'Geçersiz rol ID ile silme denemesi', 'security', null, null, [
        'attempted_role_id' => $role_id,
        'error' => 'missing_role_id'
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rol ID gerekli']);
    exit;
}

try {
    // Rolü yönetme yetkisi kontrol et
    if (!can_user_manage_role($pdo, $role_id)) {
        add_audit_log($pdo, 'Yetki dışı rol silme denemesi', 'security', 'role', $role_id, null, [
            'attempted_role_id' => $role_id,
            'error' => 'insufficient_permissions'
        ]);
        
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu rolü silme yetkiniz yok']);
        exit;
    }
    
    // Rol varlığını ve bilgilerini kontrol et
    $role_query = "SELECT name, description FROM roles WHERE id = :role_id";
    $stmt_role = execute_safe_query($pdo, $role_query, [':role_id' => $role_id]);
    $role = $stmt_role->fetch();
    
    if (!$role) {
        add_audit_log($pdo, 'Olmayan rol silme denemesi', 'security', 'role', $role_id, null, [
            'attempted_role_id' => $role_id,
            'error' => 'role_not_found'
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rol bulunamadı']);
        exit;
    }
    
    // Korumalı rol kontrolü
    if (!is_role_deletable($role['name'])) {
        add_audit_log($pdo, 'Korumalı rol silme denemesi', 'security', 'role', $role_id, null, [
            'role_name' => $role['name'],
            'error' => 'protected_role'
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bu rol silinemez (korumalı rol)']);
        exit;
    }
    
    // Role atanmış kullanıcı sayısını kontrol et
    $user_count_query = "SELECT COUNT(*) FROM user_roles WHERE role_id = :role_id";
    $stmt_count = execute_safe_query($pdo, $user_count_query, [':role_id' => $role_id]);
    $user_count = $stmt_count->fetchColumn();
    
    if ($user_count > 0) {
        add_audit_log($pdo, 'Kullanıcılı rol silme denemesi', 'security', 'role', $role_id, null, [
            'role_name' => $role['name'],
            'assigned_user_count' => $user_count,
            'error' => 'role_has_users'
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Bu rol $user_count kullanıcıya atanmış durumda. Önce kullanıcıları farklı rollere taşıyın."
        ]);
        exit;
    }
    
    // Rol yetkilerini al (audit için)
    $role_permissions = get_role_permissions($pdo, $role_id);
    
    // Transaction ile sil
    $result = execute_safe_transaction($pdo, function($pdo) use ($role_id, $role, $role_permissions) {
        // Önce rol yetkilerini sil
        $delete_permissions_query = "DELETE FROM role_permissions WHERE role_id = :role_id";
        execute_safe_query($pdo, $delete_permissions_query, [':role_id' => $role_id]);
        
        // Görünürlük rollerini temizle
        $visibility_tables = [
            'event_visibility_roles',
            'forum_category_visibility_roles',
            'forum_topic_visibility_roles',
            'gallery_photo_visibility_roles',
            'guide_visibility_roles',
            'loadout_set_visibility_roles'
        ];
        
        foreach ($visibility_tables as $table) {
            try {
                $delete_visibility_query = "DELETE FROM $table WHERE role_id = :role_id";
                execute_safe_query($pdo, $delete_visibility_query, [':role_id' => $role_id]);
            } catch (Exception $e) {
                // Tablo yoksa veya başka hata varsa logla ama devam et
                error_log("Warning: Could not clean visibility table $table: " . $e->getMessage());
            }
        }
        
        // Rolü sil
        $delete_role_query = "DELETE FROM roles WHERE id = :role_id";
        $stmt = execute_safe_query($pdo, $delete_role_query, [':role_id' => $role_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Rol silinemedi');
        }
        
        // Başarılı rol silme audit log (Türkçe mesaj)
        audit_role_deleted($pdo, $role_id, [
            'name' => $role['name'],
            'description' => $role['description'],
            'permission_count' => count($role_permissions),
            'permissions' => $role_permissions
        ], $_SESSION['user_id']);
        
        // Eski audit log sistemini de koru (backward compatibility)
        if (function_exists('audit_log')) {
            audit_log($pdo, $_SESSION['user_id'], 'role_deleted', 'role', $role_id, [
                'name' => $role['name'],
                'description' => $role['description'],
                'permission_count' => count($role_permissions),
                'permissions' => $role_permissions
            ], null);
        }
        
        // Rol güvenlik logu
        if (function_exists('log_role_security_event')) {
            log_role_security_event($pdo, 'role_deleted', $role_id, [
                'role_name' => $role['name'],
                'permission_count' => count($role_permissions),
                'deleted_by' => $_SESSION['user_id']
            ]);
        }
        
        return true;
    });
    
    if ($result) {
        // Acil durum cache temizliği
        if (function_exists('emergency_clear_all_role_caches')) {
            emergency_clear_all_role_caches($pdo);
        }
        
        echo json_encode([
            'success' => true,
            'message' => "'{$role['name']}' rolü başarıyla silindi"
        ]);
    } else {
        throw new Exception('Rol silme işlemi başarısız');
    }
    
} catch (Exception $e) {
    // Hata durumunda audit log
    add_audit_log($pdo, 'Rol silme hatası', 'error', 'role', $role_id ?? null, null, [
        'attempted_role_id' => $role_id ?? null,
        'role_name' => $role['name'] ?? 'unknown',
        'error_message' => $e->getMessage(),
        'error_type' => 'role_deletion_failed'
    ]);
    
    error_log("Delete role error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Rol silinirken hata oluştu']);
}
?>