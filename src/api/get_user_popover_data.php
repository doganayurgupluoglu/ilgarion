<?php
// src/api/get_user_popover_data.php

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Parametreleri al ve validate et
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

try {
    // Kullanıcı bilgilerini çek
    $user_query = "
        SELECT u.id, u.username, u.email, u.avatar_path, u.ingame_name, 
               u.discord_username, u.created_at, u.status,
               r.name as primary_role_name, r.color as primary_role_color
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = :user_id
        ORDER BY r.priority ASC
        LIMIT 1
    ";
    
    $stmt = execute_safe_query($pdo, $user_query, [':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Kullanıcı bulunamadı'
        ]);
        exit;
    }

    // Kullanıcının onaylanmış olup olmadığını kontrol et
    if ($user['status'] !== 'approved') {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Kullanıcı bulunamadı'
        ]);
        exit;
    }

    // Forum istatistiklerini çek
    $forum_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM forum_topics WHERE user_id = :user_id) as forum_topics,
            (SELECT COUNT(*) FROM forum_posts WHERE user_id = :user_id) as forum_posts
    ";
    
    $stmt = execute_safe_query($pdo, $forum_stats_query, [':user_id' => $user_id]);
    $forum_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Online durumu kontrol et (son 15 dakika içinde aktivite)
    $is_online = false;
    if (isset($_SESSION['user_id'])) {
        // Basit online kontrolü - gerçek uygulamada daha karmaşık olabilir
        $online_query = "
            SELECT COUNT(*) FROM audit_log 
            WHERE user_id = :user_id 
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ";
        $stmt = execute_safe_query($pdo, $online_query, [':user_id' => $user_id]);
        $is_online = $stmt->fetchColumn() > 0;
    }

    // Mesajlaşma yetkisi kontrolü
    $current_user_id = $_SESSION['user_id'] ?? null;
    $can_message = false;
    
    if ($current_user_id && $current_user_id != $user_id && is_user_approved()) {
        // Kullanıcı giriş yapmışsa ve kendisi değilse mesaj gönderebilir
        $can_message = true;
    }

    // Avatar path'i düzenle
    $avatar_path = $user['avatar_path'];
    if ($avatar_path && !str_starts_with($avatar_path, 'http')) {
        if (str_starts_with($avatar_path, '/')) {
            $avatar_path = $avatar_path;
        } else {
            $avatar_path = '/' . $avatar_path;
        }
    }

    // Yanıt verisini hazırla
    $response_data = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'avatar_path' => $avatar_path,
        'ingame_name' => $user['ingame_name'],
        'discord_username' => $user['discord_username'],
        'created_at' => $user['created_at'],
        'primary_role_name' => $user['primary_role_name'],
        'primary_role_color' => $user['primary_role_color'] ?: '#bd912a',
        'forum_topics' => (int)($forum_stats['forum_topics'] ?? 0),
        'forum_posts' => (int)($forum_stats['forum_posts'] ?? 0),
        'is_online' => $is_online,
        'can_message' => $can_message
    ];

    echo json_encode([
        'success' => true,
        'user' => $response_data
    ]);

} catch (PDOException $e) {
    error_log("User popover API veritabanı hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("User popover API genel hata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu'
    ]);
}
?>