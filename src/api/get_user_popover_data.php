<?php
// src/api/get_user_popover_data.php - Tarih düzeltmeli versiyon

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // JSON için display_errors kapalı olmalı

// JSON header
header('Content-Type: application/json');

// Session kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Base path kontrolü
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}

try {
    // Required files
    if (file_exists(BASE_PATH . '/src/config/database.php')) {
        require_once BASE_PATH . '/src/config/database.php';
    } else {
        throw new Exception('Database config not found');
    }

    // Basic functions
    if (!function_exists('is_user_approved')) {
        function is_user_approved() {
            return isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'approved';
        }
    }

    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in() {
            return isset($_SESSION['user_id']) && isset($_SESSION['user_status']);
        }
    }

} catch (Exception $e) {
    error_log("User Popover API - Include Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sistem dosyaları yüklenemedi'
    ]);
    exit;
}

// Method kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Sadece GET istekleri kabul edilir'
    ]);
    exit;
}

// User ID kontrolü
$user_id = $_GET['user_id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir kullanıcı ID\'si gereklidir'
    ]);
    exit;
}

$user_id = (int)$user_id;

// PDO kontrolü
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("User Popover API - PDO not available");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantısı mevcut değil'
    ]);
    exit;
}

try {
    // Kullanıcı bilgilerini çek
    $user_query = "
        SELECT u.id, u.username, u.avatar_path, u.ingame_name, 
               u.discord_username, u.created_at, u.status,
               r.name as primary_role_name, r.color as primary_role_color
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = ?
        ORDER BY r.priority ASC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($user_query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Kullanıcı bulunamadı'
        ]);
        exit;
    }

    // Kullanıcı onaylı değilse gizle
    if ($user['status'] !== 'approved') {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Kullanıcı bulunamadı'
        ]);
        exit;
    }

    // Forum istatistikleri (basit)
    $forum_topics = 0;
    $forum_posts = 0;
    
    try {
        $topics_stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE user_id = ?");
        $topics_stmt->execute([$user_id]);
        $forum_topics = (int)$topics_stmt->fetchColumn();
        
        $posts_stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE user_id = ?");
        $posts_stmt->execute([$user_id]);
        $forum_posts = (int)$posts_stmt->fetchColumn();
    } catch (Exception $e) {
        // Forum tabloları yoksa varsayılan değerler
        error_log("Forum stats error: " . $e->getMessage());
    }

    // Online durumu (basit)
    $is_online = false;
    if (isset($_SESSION['user_id'])) {
        try {
            $online_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM audit_log 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $online_stmt->execute([$user_id]);
            $is_online = $online_stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            // Audit tablosu yoksa offline kabul et
            $is_online = false;
        }
    }

    // Mesaj gönderme yetkisi
    $current_user_id = $_SESSION['user_id'] ?? null;
    $can_message = ($current_user_id && $current_user_id != $user_id && is_user_logged_in());

    // Avatar path düzeltmesi
    $avatar_path = $user['avatar_path'];
    if ($avatar_path) {
        // ../assets/ -> /assets/
        if (strpos($avatar_path, '../assets/') === 0) {
            $avatar_path = str_replace('../assets/', '/assets/', $avatar_path);
        }
        // uploads/ -> /public/uploads/
        elseif (strpos($avatar_path, 'uploads/') === 0) {
            $avatar_path = '/public/' . $avatar_path;
        }
        // /assets/ veya /public/ ile başlıyorsa dokunma
    } else {
        $avatar_path = '/assets/logo.png';
    }

    // ✅ Tarih formatı düzeltmesi - ISO formatında gönder
    $created_at_iso = $user['created_at']; // ISO format: 2025-06-03 17:32:08
    
    // Response data
    $response_data = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'avatar_path' => $avatar_path,
        'ingame_name' => $user['ingame_name'] ?: 'Belirtilmemiş',
        'discord_username' => $user['discord_username'] ?: 'Belirtilmemiş',
        'created_at' => $created_at_iso, // ISO formatında gönder
        'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
        'primary_role_color' => $user['primary_role_color'] ?: '#bd912a',
        'forum_topics' => $forum_topics,
        'forum_posts' => $forum_posts,
        'is_online' => $is_online,
        'can_message' => $can_message
    ];

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'user' => $response_data
    ]);

} catch (PDOException $e) {
    error_log("User Popover API - Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası'
    ]);
} catch (Exception $e) {
    error_log("User Popover API - General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sistem hatası'
    ]);
}
?>