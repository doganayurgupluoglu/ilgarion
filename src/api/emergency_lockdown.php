<?php
// src/api/emergency_lockdown.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece POST istekleri kabul edilir'
    ]);
    exit;
}

// Rate limiting - Acil durum işlemleri için çok sıkı
if (!check_rate_limit('emergency_lockdown', 2, 600)) { // 10 dakikada 2 deneme
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Çok fazla acil durum isteği. 10 dakika bekleyin.'
    ]);
    exit;
}

// Yetki kontrolü - Sadece süper adminler
try {
    if (!is_user_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Bu API\'ye erişim için giriş yapmalısınız'
        ]);
        exit;
    }

    if (!is_super_admin($pdo)) {
        // Yetkisiz erişim girişimini logla
        audit_log($pdo, $_SESSION['user_id'], 'unauthorized_emergency_lockdown_attempt', 'security', null, null, [
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ]);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Acil durum işlemleri sadece süper adminler tarafından kullanılabilir'
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

// JSON veriyi al
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz istek verisi'
    ]);
    exit;
}

$action = $data['action'];
$current_user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'lockdown':
            $result = execute_emergency_lockdown($pdo, $current_user_id);
            break;
            
        case 'unlock':
            $result = execute_emergency_unlock($pdo, $current_user_id);
            break;
            
        case 'partial_lockdown':
            $target_users = $data['target_users'] ?? [];
            $result = execute_partial_lockdown($pdo, $current_user_id, $target_users);
            break;
            
        case 'force_logout_all':
            $result = force_logout_all_users($pdo, $current_user_id);
            break;
            
        case 'suspend_user':
            $target_user_id = $data['user_id'] ?? null;
            $reason = $data['reason'] ?? 'Emergency suspension';
            $result = emergency_suspend_user($pdo, $current_user_id, $target_user_id, $reason);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Geçersiz acil durum işlemi'
            ]);
            exit;
    }
    
    echo json_encode($result);

} catch (Exception $e) {
    error_log("Emergency lockdown API error: " . $e->getMessage());
    
    // Kritik hata audit log
    audit_log($pdo, $current_user_id, 'emergency_lockdown_error', 'security', null, null, [
        'action' => $action,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Acil durum işlemi sırasında hata oluştu'
    ]);
}

/**
 * Tam sistem kilidi uygular
 */
function execute_emergency_lockdown(PDO $pdo, int $admin_user_id): array {
    try {
        $lockdown_time = time();
        
        // 1. Sistem kilit ayarını aktifleştir
        set_system_setting($pdo, 'emergency_lockdown', true, 'boolean', 'Emergency lockdown activated', $admin_user_id);
        set_system_setting($pdo, 'lockdown_time', $lockdown_time, 'integer', 'Lockdown activation time', $admin_user_id);
        set_system_setting($pdo, 'lockdown_admin', $admin_user_id, 'integer', 'Admin who activated lockdown', $admin_user_id);
        
        // 2. Tüm kullanıcı cache'lerini temizle
        emergency_clear_all_role_caches($pdo);
        
        // 3. Aktif session'ları sonlandır (süper adminler hariç)
        $terminated_sessions = terminate_non_super_admin_sessions($pdo);
        
        // 4. Tüm kullanıcıları askıya al (süper adminler hariç)
        $suspended_users = suspend_all_users_except_super_admins($pdo, $admin_user_id);
        
        // 5. Rate limiting'i sıkılaştır
        apply_emergency_rate_limits();
        
        // Audit log
        audit_log($pdo, $admin_user_id, 'emergency_lockdown_activated', 'security', null, null, [
            'lockdown_time' => $lockdown_time,
            'terminated_sessions' => $terminated_sessions,
            'suspended_users' => $suspended_users,
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return [
            'success' => true,
            'message' => 'Acil durum kilidi başarıyla aktifleştirildi',
            'data' => [
                'lockdown_time' => date('Y-m-d H:i:s', $lockdown_time),
                'terminated_sessions' => $terminated_sessions,
                'suspended_users' => $suspended_users,
                'lockdown_admin' => $admin_user_id
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Emergency lockdown execution error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Sistem kilidini kaldırır
 */
function execute_emergency_unlock(PDO $pdo, int $admin_user_id): array {
    try {
        $unlock_time = time();
        
        // 1. Sistem kilit ayarını deaktifleştir
        set_system_setting($pdo, 'emergency_lockdown', false, 'boolean', 'Emergency lockdown deactivated', $admin_user_id);
        set_system_setting($pdo, 'unlock_time', $unlock_time, 'integer', 'Lockdown deactivation time', $admin_user_id);
        set_system_setting($pdo, 'unlock_admin', $admin_user_id, 'integer', 'Admin who deactivated lockdown', $admin_user_id);
        
        // 2. Askıya alınan kullanıcıları geri yükle (manuel kontrol gerekli)
        $query = "UPDATE users SET status = 'approved' WHERE status = 'emergency_suspended'";
        $stmt = execute_safe_query($pdo, $query);
        $restored_users = $stmt->rowCount();
        
        // 3. Cache'leri temizle
        emergency_clear_all_role_caches($pdo);
        
        // 4. Rate limiting'i normale döndür
        restore_normal_rate_limits();
        
        // Audit log
        audit_log($pdo, $admin_user_id, 'emergency_lockdown_deactivated', 'security', null, null, [
            'unlock_time' => $unlock_time,
            'restored_users' => $restored_users,
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return [
            'success' => true,
            'message' => 'Acil durum kilidi başarıyla kaldırıldı',
            'data' => [
                'unlock_time' => date('Y-m-d H:i:s', $unlock_time),
                'restored_users' => $restored_users,
                'unlock_admin' => $admin_user_id
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Emergency unlock execution error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Kısmi kilit uygular (belirli kullanıcılar)
 */
function execute_partial_lockdown(PDO $pdo, int $admin_user_id, array $target_users): array {
    try {
        if (empty($target_users)) {
            return [
                'success' => false,
                'message' => 'Hedef kullanıcı listesi boş'
            ];
        }
        
        $suspended_count = 0;
        $errors = [];
        
        foreach ($target_users as $user_id) {
            if (!is_numeric($user_id)) {
                $errors[] = "Geçersiz kullanıcı ID: $user_id";
                continue;
            }
            
            $user_id = (int)$user_id;
            
            // Süper adminleri askıya alma
            if (is_super_admin($pdo, $user_id)) {
                $errors[] = "Süper admin askıya alınamaz: $user_id";
                continue;
            }
            
            try {
                $update_query = "UPDATE users SET status = 'emergency_suspended' WHERE id = :user_id AND status = 'approved'";
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([':user_id' => $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $suspended_count++;
                    
                    // Kullanıcının cache'ini temizle
                    clear_user_permissions_cache($user_id);
                    
                    // Audit log
                    audit_log($pdo, $admin_user_id, 'emergency_user_suspended', 'user', $user_id, null, [
                        'reason' => 'Partial emergency lockdown',
                        'suspended_by' => $admin_user_id
                    ]);
                }
            } catch (Exception $e) {
                $errors[] = "Kullanıcı $user_id askıya alınamadı: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'message' => "Kısmi acil durum kilidi uygulandı",
            'data' => [
                'suspended_count' => $suspended_count,
                'total_requested' => count($target_users),
                'errors' => $errors
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Partial lockdown execution error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Tüm kullanıcıları zorla çıkış yapar
 */
function force_logout_all_users(PDO $pdo, int $admin_user_id): array {
    try {
        // Session tablosu varsa (eğer kullanıyorsanız)
        // Bu örnekte audit_log'dan aktif session'ları tespit ediyoruz
        
        $active_sessions_query = "
            SELECT DISTINCT user_id 
            FROM audit_log 
            WHERE action IN ('user_login', 'session_activity') 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
            AND user_id IS NOT NULL
        ";
        
        $stmt = execute_safe_query($pdo, $active_sessions_query);
        $active_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $logged_out_count = 0;
        
        // Her aktif kullanıcı için logout kaydı oluştur
        foreach ($active_users as $user_id) {
            // Süper adminleri çıkış yaptırma (kendisi hariç)
            if (is_super_admin($pdo, $user_id) && $user_id != $admin_user_id) {
                continue;
            }
            
            // Cache temizle
            clear_user_permissions_cache($user_id);
            
            // Logout audit log
            audit_log($pdo, $admin_user_id, 'force_logout', 'user', $user_id, null, [
                'reason' => 'Emergency force logout',
                'forced_by' => $admin_user_id
            ]);
            
            $logged_out_count++;
        }
        
        // Global logout flag ayarla
        set_system_setting($pdo, 'force_logout_all', time(), 'integer', 'Force logout all users timestamp', $admin_user_id);
        
        return [
            'success' => true,
            'message' => 'Tüm kullanıcılar zorla çıkış yaptırıldı',
            'data' => [
                'logged_out_count' => $logged_out_count,
                'total_active' => count($active_users),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Force logout all users error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Acil kullanıcı askıya alma
 */
function emergency_suspend_user(PDO $pdo, int $admin_user_id, ?int $target_user_id, string $reason): array {
    try {
        if (!$target_user_id) {
            return [
                'success' => false,
                'message' => 'Hedef kullanıcı ID\'si gerekli'
            ];
        }
        
        // Süper admin kontrolü
        if (is_super_admin($pdo, $target_user_id)) {
            return [
                'success' => false,
                'message' => 'Süper adminler askıya alınamaz'
            ];
        }
        
        // Kendini askıya alma
        if ($target_user_id == $admin_user_id) {
            return [
                'success' => false,
                'message' => 'Kendinizi askıya alamazsınız'
            ];
        }
        
        // Kullanıcıyı askıya al
        $update_query = "UPDATE users SET status = 'emergency_suspended' WHERE id = :user_id";
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([':user_id' => $target_user_id]);
        
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'Kullanıcı bulunamadı veya zaten askıda'
            ];
        }
        
        // Cache temizle
        clear_user_permissions_cache($target_user_id);
        
        // Audit log
        audit_log($pdo, $admin_user_id, 'emergency_user_suspended', 'user', $target_user_id, null, [
            'reason' => $reason,
            'suspended_by' => $admin_user_id,
            'suspension_type' => 'emergency'
        ]);
        
        return [
            'success' => true,
            'message' => 'Kullanıcı acil durumda askıya alındı',
            'data' => [
                'target_user_id' => $target_user_id,
                'reason' => $reason,
                'suspended_by' => $admin_user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Emergency suspend user error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Süper admin olmayan session'ları sonlandırır
 */
function terminate_non_super_admin_sessions(PDO $pdo): int {
    // Bu fonksiyon session management sisteminize göre uyarlanmalı
    // Şimdilik audit log ile takip ettiğimiz için basit bir implementation
    
    try {
        $terminated_count = 0;
        
        // Session cleanup işlemi burada yapılacak
        // Örnek: session_destroy() veya session tablosundan silme
        
        return $terminated_count;
    } catch (Exception $e) {
        error_log("Terminate sessions error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Süper adminler hariç tüm kullanıcıları askıya alır
 */
function suspend_all_users_except_super_admins(PDO $pdo, int $admin_user_id): int {
    try {
        $super_admin_list = get_system_setting($pdo, 'super_admin_users', []);
        
        if (empty($super_admin_list)) {
            $super_admin_list = [$admin_user_id]; // En azından işlemi yapanı koru
        }
        
        // IN clause için güvenli parametreler oluştur
        $placeholders = str_repeat('?,', count($super_admin_list) - 1) . '?';
        
        $update_query = "
            UPDATE users 
            SET status = 'emergency_suspended' 
            WHERE status = 'approved' 
            AND id NOT IN ($placeholders)
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute($super_admin_list);
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Suspend all users error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Acil durum rate limitlerini uygular
 */
function apply_emergency_rate_limits(): void {
    // Session'da acil durum rate limit flag'i ayarla
    $_SESSION['emergency_rate_limits'] = [
        'login' => ['max' => 2, 'window' => 600], // 10 dakikada 2 deneme
        'api' => ['max' => 10, 'window' => 300],   // 5 dakikada 10 istek
        'general' => ['max' => 5, 'window' => 300] // 5 dakikada 5 işlem
    ];
}

/**
 * Normal rate limitlerini geri yükler
 */
function restore_normal_rate_limits(): void {
    unset($_SESSION['emergency_rate_limits']);
}
?>