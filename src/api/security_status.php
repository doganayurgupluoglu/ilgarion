<?php
// src/api/security_status.php

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

    if (!has_permission($pdo, 'admin.system.security')) {
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

try {
    $security_status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'safe', // safe, warning, critical
        'critical_issues' => 0,
        'warnings' => 0,
        'info' => [],
        'details' => []
    ];

    // 1. Süper admin kontrolü
    $super_admin_list = get_system_setting($pdo, 'super_admin_users', []);
    if (empty($super_admin_list)) {
        $security_status['critical_issues']++;
        $security_status['details'][] = [
            'type' => 'critical',
            'category' => 'super_admin',
            'message' => 'Hiç süper admin tanımlanmamış!',
            'severity' => 'high'
        ];
        $security_status['overall_status'] = 'critical';
    }

    // 2. Admin kullanıcı kontrolü
    $admin_users_query = "
        SELECT COUNT(*) as admin_count 
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name = 'admin'
    ";
    $stmt = execute_safe_query($pdo, $admin_users_query);
    $admin_count = $stmt->fetchColumn();
    
    if ($admin_count == 0) {
        $security_status['critical_issues']++;
        $security_status['details'][] = [
            'type' => 'critical',
            'category' => 'admin_users',
            'message' => 'Hiç admin rolüne sahip kullanıcı yok!',
            'severity' => 'high'
        ];
        $security_status['overall_status'] = 'critical';
    }

    // 3. Son 1 saatteki başarısız login denemeleri
    $failed_logins_query = "
        SELECT COUNT(*) as failed_count,
               COUNT(DISTINCT ip_address) as unique_ips
        FROM audit_log 
        WHERE action LIKE '%login%' AND action LIKE '%failed%' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $stmt = execute_safe_query($pdo, $failed_logins_query);
    $failed_data = $stmt->fetch();
    
    if ($failed_data['failed_count'] > 50) {
        $security_status['critical_issues']++;
        $security_status['details'][] = [
            'type' => 'critical',
            'category' => 'brute_force',
            'message' => "Son 1 saatte {$failed_data['failed_count']} başarısız login denemesi!",
            'severity' => 'high',
            'data' => $failed_data
        ];
        $security_status['overall_status'] = 'critical';
    } elseif ($failed_data['failed_count'] > 20) {
        $security_status['warnings']++;
        $security_status['details'][] = [
            'type' => 'warning',
            'category' => 'suspicious_logins',
            'message' => "Son 1 saatte {$failed_data['failed_count']} başarısız login denemesi",
            'severity' => 'medium',
            'data' => $failed_data
        ];
        if ($security_status['overall_status'] === 'safe') {
            $security_status['overall_status'] = 'warning';
        }
    }

    // 4. Güvenlik ihlalleri kontrolü (son 1 saat)
    $violations_query = "
        SELECT COUNT(*) as violation_count
        FROM audit_log 
        WHERE (action LIKE '%violation%' OR action LIKE '%unauthorized%') 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $stmt = execute_safe_query($pdo, $violations_query);
    $violation_count = $stmt->fetchColumn();
    
    if ($violation_count > 5) {
        $security_status['critical_issues']++;
        $security_status['details'][] = [
            'type' => 'critical',
            'category' => 'security_violations',
            'message' => "Son 1 saatte {$violation_count} güvenlik ihlali!",
            'severity' => 'high'
        ];
        $security_status['overall_status'] = 'critical';
    } elseif ($violation_count > 0) {
        $security_status['warnings']++;
        $security_status['details'][] = [
            'type' => 'warning',
            'category' => 'security_violations',
            'message' => "Son 1 saatte {$violation_count} güvenlik ihlali",
            'severity' => 'medium'
        ];
        if ($security_status['overall_status'] === 'safe') {
            $security_status['overall_status'] = 'warning';
        }
    }

    // 5. Aktif session sayısı kontrolü
    $active_sessions_query = "
        SELECT COUNT(DISTINCT user_id) as active_count 
        FROM audit_log 
        WHERE action IN ('user_login', 'session_activity') 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ";
    $stmt = execute_safe_query($pdo, $active_sessions_query);
    $active_sessions = $stmt->fetchColumn();
    
    if ($active_sessions > 100) {
        $security_status['warnings']++;
        $security_status['details'][] = [
            'type' => 'warning',
            'category' => 'high_activity',
            'message' => "Çok yüksek aktivite: {$active_sessions} aktif session",
            'severity' => 'medium'
        ];
        if ($security_status['overall_status'] === 'safe') {
            $security_status['overall_status'] = 'warning';
        }
    }

    // 6. Sistem sağlığı kontrolleri
    $db_status = check_database_health($pdo);
    if (!$db_status['healthy']) {
        $security_status['warnings']++;
        $security_status['details'][] = [
            'type' => 'warning',
            'category' => 'database',
            'message' => 'Veritabanı performans sorunu tespit edildi',
            'severity' => 'medium',
            'data' => $db_status
        ];
        if ($security_status['overall_status'] === 'safe') {
            $security_status['overall_status'] = 'warning';
        }
    }

    // 7. IP bazlı risk analizi
    $risky_ips = detect_risky_ips($pdo);
    if (!empty($risky_ips)) {
        $security_status['warnings']++;
        $security_status['details'][] = [
            'type' => 'warning',
            'category' => 'risky_ips',
            'message' => count($risky_ips) . ' şüpheli IP adresi tespit edildi',
            'severity' => 'medium',
            'data' => $risky_ips
        ];
        if ($security_status['overall_status'] === 'safe') {
            $security_status['overall_status'] = 'warning';
        }
    }

    // 8. Sistem kaynak kullanımı
    $memory_usage = memory_get_usage(true);
    $memory_limit = ini_get('memory_limit');
    $memory_limit_bytes = return_bytes($memory_limit);
    $memory_usage_percent = ($memory_usage / $memory_limit_bytes) * 100;
    
    if ($memory_usage_percent > 90) {
        $security_status['warnings']++;
        $security_status['details'][] = [
            'type' => 'warning',
            'category' => 'system_resources',
            'message' => "Yüksek bellek kullanımı: {$memory_usage_percent}%",
            'severity' => 'medium'
        ];
        if ($security_status['overall_status'] === 'safe') {
            $security_status['overall_status'] = 'warning';
        }
    }

    // Pozitif bilgiler
    if ($security_status['critical_issues'] === 0 && $security_status['warnings'] === 0) {
        $security_status['info'][] = 'Sistem güvenliği normal seviyede';
        $security_status['info'][] = 'Aktif tehdit tespit edilmedi';
    }

    $security_status['info'][] = "Süper admin sayısı: " . count($super_admin_list);
    $security_status['info'][] = "Admin kullanıcı sayısı: {$admin_count}";
    $security_status['info'][] = "Aktif session sayısı: {$active_sessions}";

    // Audit log
    audit_log($pdo, $_SESSION['user_id'], 'security_status_checked', 'system', null, null, [
        'overall_status' => $security_status['overall_status'],
        'critical_issues' => $security_status['critical_issues'],
        'warnings' => $security_status['warnings']
    ]);

    echo json_encode([
        'success' => true,
        'status' => $security_status
    ]);

} catch (PDOException $e) {
    error_log("Security status API veritabanı hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Security status API genel hata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu'
    ]);
}

/**
 * Veritabanı sağlığını kontrol eder
 */
function check_database_health(PDO $pdo): array {
    try {
        $start_time = microtime(true);
        
        // Basit bir sorgu ile response time ölç
        $stmt = execute_safe_query($pdo, "SELECT 1");
        $stmt->fetch();
        
        $response_time = (microtime(true) - $start_time) * 1000; // ms
        
        // Tablo durumlarını kontrol et
        $table_check = $pdo->query("SHOW TABLE STATUS");
        $tables = $table_check->fetchAll();
        
        $total_size = 0;
        $fragmented_tables = 0;
        
        foreach ($tables as $table) {
            $total_size += $table['Data_length'] + $table['Index_length'];
            if ($table['Data_free'] > 0) {
                $fragmented_tables++;
            }
        }
        
        return [
            'healthy' => $response_time < 100 && $fragmented_tables < 5,
            'response_time_ms' => round($response_time, 2),
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'fragmented_tables' => $fragmented_tables,
            'total_tables' => count($tables)
        ];
        
    } catch (Exception $e) {
        return [
            'healthy' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Riskli IP adreslerini tespit eder
 */
function detect_risky_ips(PDO $pdo): array {
    try {
        $risky_ips_query = "
            SELECT 
                ip_address,
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN action LIKE '%failed%' THEN 1 END) as failed_attempts,
                COUNT(CASE WHEN action LIKE '%violation%' THEN 1 END) as violations,
                MIN(created_at) as first_seen,
                MAX(created_at) as last_seen,
                COUNT(DISTINCT user_id) as different_users
            FROM audit_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND ip_address IS NOT NULL
            GROUP BY ip_address
            HAVING (
                failed_attempts > 10 OR 
                violations > 2 OR 
                (total_attempts > 50 AND failed_attempts / total_attempts > 0.3)
            )
            ORDER BY (failed_attempts + violations * 2) DESC
            LIMIT 20
        ";
        
        $stmt = execute_safe_query($pdo, $risky_ips_query);
        $risky_ips = $stmt->fetchAll();
        
        // Risk skorunu hesapla
        foreach ($risky_ips as &$ip) {
            $risk_score = 0;
            $risk_score += $ip['failed_attempts'] * 1;
            $risk_score += $ip['violations'] * 5;
            $risk_score += ($ip['different_users'] > 5) ? 10 : 0;
            
            $ip['risk_score'] = $risk_score;
            $ip['risk_level'] = $risk_score > 50 ? 'high' : ($risk_score > 20 ? 'medium' : 'low');
        }
        
        return $risky_ips;
        
    } catch (Exception $e) {
        error_log("Risky IPs detection error: " . $e->getMessage());
        return [];
    }
}

/**
 * Byte değerini sayıya çevirir (memory_limit için)
 */
function return_bytes(string $val): int {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}
?>