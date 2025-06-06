<?php
// src/api/get_user_popover_data.php - User Popover Data API

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// CORS headers (gerekirse)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // User ID kontrolü
    $user_id = (int) ($_GET['user_id'] ?? 0);
    
    if (!$user_id) {
        throw new Exception('Kullanıcı ID gereklidir.');
    }
    
    // Rate limiting basit kontrolü
    $current_user_id = $_SESSION['user_id'] ?? null;
    $rate_limit_key = "popover_requests_" . ($current_user_id ?: 'guest');
    
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'timestamp' => time()];
    }
    
    $rate_data = $_SESSION[$rate_limit_key];
    
    // Son 1 dakikada 30'dan fazla istek varsa engelle
    if (time() - $rate_data['timestamp'] < 60 && $rate_data['count'] > 30) {
        throw new Exception('Çok fazla istek. Lütfen bekleyin.');
    }
    
    // Rate limit sayacını güncelle
    if (time() - $rate_data['timestamp'] >= 60) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'timestamp' => time()];
    } else {
        $_SESSION[$rate_limit_key]['count']++;
    }
    
    // Kullanıcı verilerini çek
    $user_data = getUserPopoverData($pdo, $user_id, $current_user_id);
    
    if (!$user_data) {
        throw new Exception('Kullanıcı bulunamadı veya erişim izniniz yok.');
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user_data
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * User popover için gerekli verileri çeker
 */
function getUserPopoverData(PDO $pdo, int $target_user_id, ?int $current_user_id): ?array {
    try {
        // Temel kullanıcı bilgileri
        $query = "
            SELECT u.id, u.username, u.email, u.ingame_name, u.discord_username,
                   u.avatar_path, u.status, u.created_at, u.profile_info,
                   GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ',') AS roles_list,
                   (SELECT r2.name FROM roles r2 JOIN user_roles ur2 ON r2.id = ur2.role_id 
                    WHERE ur2.user_id = u.id ORDER BY r2.priority ASC LIMIT 1) as primary_role_name,
                   (SELECT r2.color FROM roles r2 JOIN user_roles ur2 ON r2.id = ur2.role_id 
                    WHERE ur2.user_id = u.id ORDER BY r2.priority ASC LIMIT 1) as primary_role_color
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = :user_id
            GROUP BY u.id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $target_user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return null;
        }
        
        // Sadece onaylanmış kullanıcılar görülebilir (admin hariç)
        if ($user['status'] !== 'approved' && $current_user_id) {
            // Admin kontrolü
            if (!has_permission($pdo, 'admin.users.view', $current_user_id)) {
                return null;
            }
        } elseif ($user['status'] !== 'approved' && !$current_user_id) {
            return null;
        }
        
        // Avatar path düzeltme
        $avatar_path = $user['avatar_path'];
        if (empty($avatar_path)) {
            $avatar_path = '/assets/logo.png';
        } elseif (strpos($avatar_path, '../assets/') === 0) {
            $avatar_path = str_replace('../assets/', '/assets/', $avatar_path);
        } elseif (strpos($avatar_path, 'uploads/') === 0) {
            $avatar_path = '/' . $avatar_path;
        } elseif (strpos($avatar_path, '/assets/') !== 0 && strpos($avatar_path, '/') !== 0) {
            $avatar_path = '/assets/logo.png';
        }
        
        // Forum istatistikleri
        $forum_stats = getForumStats($pdo, $target_user_id);
        
        // Online durumu (basit - son 15 dakika içinde aktivite)
        $is_online = checkUserOnlineStatus($pdo, $target_user_id);
        
        // Mesaj gönderme izni
        $can_message = ($current_user_id && 
                       $current_user_id != $target_user_id && 
                       is_user_approved());
        
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'], // Sadece admin görebilir
            'ingame_name' => $user['ingame_name'] ?: '',
            'discord_username' => $user['discord_username'] ?: '',
            'avatar_path' => $avatar_path,
            'status' => $user['status'],
            'created_at' => $user['created_at'],
            'profile_info' => $user['profile_info'] ?: '',
            'roles_list' => $user['roles_list'] ?: '',
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a',
            'forum_topics' => $forum_stats['topics'],
            'forum_posts' => $forum_stats['posts'],
            'is_online' => $is_online,
            'can_message' => $can_message
        ];
        
    } catch (Exception $e) {
        error_log("User popover data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Forum istatistiklerini çeker
 */
function getForumStats(PDO $pdo, int $user_id): array {
    try {
        // Topic sayısı
        $topic_query = "SELECT COUNT(*) FROM forum_topics WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $topic_query, [':user_id' => $user_id]);
        $topic_count = $stmt->fetchColumn();
        
        // Post sayısı  
        $post_query = "SELECT COUNT(*) FROM forum_posts WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $post_query, [':user_id' => $user_id]);
        $post_count = $stmt->fetchColumn();
        
        return [
            'topics' => (int)$topic_count,
            'posts' => (int)$post_count
        ];
        
    } catch (Exception $e) {
        error_log("Forum stats error: " . $e->getMessage());
        return ['topics' => 0, 'posts' => 0];
    }
}

/**
 * Kullanıcının online durumunu kontrol eder
 */
function checkUserOnlineStatus(PDO $pdo, int $user_id): bool {
    try {
        // Basit online kontrolü - audit log tablosundan son aktivite
        $query = "
            SELECT created_at 
            FROM audit_log 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        $last_activity = $stmt->fetchColumn();
        
        if (!$last_activity) {
            return false;
        }
        
        // Son 15 dakika içinde aktivite varsa online
        $last_activity_time = strtotime($last_activity);
        $current_time = time();
        
        return ($current_time - $last_activity_time) <= (15 * 60); // 15 dakika
        
    } catch (Exception $e) {
        error_log("Online status check error: " . $e->getMessage());
        return false;
    }
}

/**
 * IP adresi güvenlik kontrolü
 */
function validateRequestSecurity(): bool {
    // Temel güvenlik kontrolleri
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Bot kontrolü
    $bots = ['bot', 'spider', 'crawler', 'scraper'];
    foreach ($bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return false;
        }
    }
    
    // Referer kontrolü (aynı domain)
    if ($referer && !empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (strpos($referer, $host) === false) {
            error_log("Invalid referer for popover API: " . $referer);
            // Sadece log, bloklamayalım (CDN'ler için)
        }
    }
    
    return true;
}

// IP güvenlik kontrolü
if (!validateRequestSecurity()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Erişim reddedildi.'
    ]);
    exit;
}

// İstatistik loglama (opsiyonel)
if (function_exists('audit_log') && isset($current_user_id) && isset($user_id)) {
    try {
        audit_log($pdo, $current_user_id, 'user_popover_viewed', 'user', $user_id, null, [
            'target_user' => $user_id,
            'ip' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Audit log hatası kritik değil
        error_log("Audit log error in popover API: " . $e->getMessage());
    }
}
?>