<?php
// src/includes/navbar_data.php

// Bu dosya navbar.php'den önce include edilmeli
// Gerekli fonksiyonların yüklü olduğundan emin ol

if (!function_exists('is_user_logged_in')) {
    require_once BASE_PATH . '/src/functions/auth_functions.php';
}

if (!function_exists('has_permission')) {
    require_once BASE_PATH . '/src/functions/role_functions.php';
}

// Navbar için global değişkenleri başlat
$GLOBALS['unread_notification_count'] = 0;
$GLOBALS['notifications_for_dropdown'] = [];

// Kullanıcı giriş yapmışsa bildirim verilerini yükle
if (is_user_logged_in()) {
    try {
        $user_id = (int)$_SESSION['user_id'];
        
        // Okunmamış bildirim sayısı
        $notification_count_query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $stmt_count = $pdo->prepare($notification_count_query);
        $stmt_count->execute([':user_id' => $user_id]);
        $GLOBALS['unread_notification_count'] = (int)$stmt_count->fetchColumn();
        
        // Dropdown için son bildirimler
        $notifications_query = "
            SELECT n.*, 
                   u.username as actor_username,
                   CASE 
                       WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN CONCAT(TIMESTAMPDIFF(MINUTE, n.created_at, NOW()), ' dakika önce')
                       WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN CONCAT(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), ' saat önce')
                       WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN CONCAT(TIMESTAMPDIFF(DAY, n.created_at, NOW()), ' gün önce')
                       ELSE DATE_FORMAT(n.created_at, '%d.%m.%Y %H:%i')
                   END as time_ago,
                   CASE 
                       WHEN n.event_id IS NOT NULL THEN 'fa-calendar'
                       WHEN n.message LIKE '%tartışma%' THEN 'fa-comments'
                       WHEN n.message LIKE '%galeri%' THEN 'fa-images'
                       WHEN n.message LIKE '%rehber%' THEN 'fa-book'
                       ELSE 'fa-info-circle'
                   END as icon
            FROM notifications n
            LEFT JOIN users u ON n.actor_user_id = u.id
            WHERE n.user_id = :user_id AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT 5
        ";
        
        $stmt_notifications = $pdo->prepare($notifications_query);
        $stmt_notifications->execute([':user_id' => $user_id]);
        $GLOBALS['notifications_for_dropdown'] = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Navbar notification loading error: " . $e->getMessage());
        $GLOBALS['unread_notification_count'] = 0;
        $GLOBALS['notifications_for_dropdown'] = [];
    }
}
?>