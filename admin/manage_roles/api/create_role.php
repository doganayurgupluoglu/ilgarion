<?php
// /admin/manage_roles/api/create_role.php
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
if (!is_user_logged_in() || !has_permission($pdo, 'admin.roles.create')) {
    // Yetkisiz erişim denemesi audit log
    add_audit_log($pdo, 'Yetkisiz rol oluşturma denemesi', 'security', null, null, [
        'attempted_action' => 'create_role',
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
$role_name = trim($_POST['role_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$color = trim($_POST['color'] ?? '#bd912a');
$priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 999;

// Validasyon
if (!validate_role_name($role_name)) {
    // Başarısız rol oluşturma denemesi audit log
    add_audit_log($pdo, 'Geçersiz rol adı ile rol oluşturma denemesi', 'security', null, null, [
        'attempted_role_name' => $role_name,
        'error' => 'invalid_role_name_format'
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz rol adı formatı. Sadece küçük harf, rakam ve alt çizgi kullanın']);
    exit;
}

if (!validate_color_code($color)) {
    // Geçersiz renk kodu audit log
    add_audit_log($pdo, 'Geçersiz renk kodu ile rol oluşturma denemesi', 'security', null, null, [
        'attempted_role_name' => $role_name,
        'attempted_color' => $color,
        'error' => 'invalid_color_code'
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz renk kodu']);
    exit;
}

if ($priority < 1 || $priority > 999) {
    add_audit_log($pdo, 'Geçersiz öncelik değeri ile rol oluşturma denemesi', 'security', null, null, [
        'attempted_role_name' => $role_name,
        'attempted_priority' => $priority,
        'error' => 'invalid_priority_range'
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Öncelik 1-999 arasında olmalıdır']);
    exit;
}

if (strlen($description) > 255) {
    add_audit_log($pdo, 'Çok uzun açıklama ile rol oluşturma denemesi', 'security', null, null, [
        'attempted_role_name' => $role_name,
        'description_length' => strlen($description),
        'error' => 'description_too_long'
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Açıklama 255 karakterden uzun olamaz']);
    exit;
}

try {
    // Aynı isimde rol var mı kontrol et
    $duplicate_query = "SELECT id FROM roles WHERE name = :name";
    $stmt_duplicate = execute_safe_query($pdo, $duplicate_query, [':name' => $role_name]);
    
    if ($stmt_duplicate->fetch()) {
        // Duplicate rol adı denemesi audit log
        add_audit_log($pdo, 'Mevcut rol adı ile yeni rol oluşturma denemesi', 'security', null, null, [
            'attempted_role_name' => $role_name,
            'error' => 'duplicate_role_name'
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bu isimde bir rol zaten mevcut']);
        exit;
    }
    
    // Transaction ile oluştur
    $new_role_id = execute_safe_transaction($pdo, function($pdo) use ($role_name, $description, $color, $priority) {
        $insert_query = "
            INSERT INTO roles (name, description, color, priority, created_at, updated_at)
            VALUES (:name, :description, :color, :priority, NOW(), NOW())
        ";
        
        $insert_params = [
            ':name' => $role_name,
            ':description' => $description,
            ':color' => $color,
            ':priority' => $priority
        ];
        
        $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
        $new_role_id = $pdo->lastInsertId();
        
        // Başarılı rol oluşturma audit log (Türkçe mesaj)
        audit_role_created($pdo, $new_role_id, [
            'name' => $role_name,
            'description' => $description,
            'color' => $color,
            'priority' => $priority
        ], $_SESSION['user_id']);
        
        // Eski audit log sistemini de koru (backward compatibility)
        if (function_exists('audit_log')) {
            audit_log($pdo, $_SESSION['user_id'], 'role_created', 'role', $new_role_id, null, [
                'name' => $role_name,
                'description' => $description,
                'color' => $color,
                'priority' => $priority
            ]);
        }
        
        // Rol güvenlik logu
        if (function_exists('log_role_security_event')) {
            log_role_security_event($pdo, 'role_created', $new_role_id, [
                'role_name' => $role_name,
                'created_by' => $_SESSION['user_id']
            ]);
        }
        
        return $new_role_id;
    });
    
    if ($new_role_id) {
        echo json_encode([
            'success' => true,
            'message' => 'Rol başarıyla oluşturuldu',
            'role_id' => $new_role_id
        ]);
    } else {
        throw new Exception('Rol oluşturma işlemi başarısız');
    }
    
} catch (Exception $e) {
    // Hata durumunda audit log
    add_audit_log($pdo, 'Rol oluşturma hatası', 'error', null, null, [
        'attempted_role_name' => $role_name,
        'error_message' => $e->getMessage(),
        'error_type' => 'role_creation_failed'
    ]);
    
    error_log("Create role error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Rol oluşturulurken hata oluştu']);
}
?>