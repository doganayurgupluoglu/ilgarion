<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('is_user_logged_in')) {
    require_once BASE_PATH . '/src/functions/notification_functions.php';
}

$GLOBALS['unread_notification_count'] = 0;
$GLOBALS['notifications_for_dropdown'] = [];

if (isset($pdo) && function_exists('is_user_logged_in') && is_user_logged_in()) {
    if (function_exists('count_unread_notifications') && function_exists('get_unread_notifications')) {
        $current_user_id_for_notif = $_SESSION['user_id'];
        $GLOBALS['unread_notification_count'] = count_unread_notifications($pdo, $current_user_id_for_notif);
        $GLOBALS['notifications_for_dropdown'] = get_unread_notifications($pdo, $current_user_id_for_notif, 5);
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ILGARION TURANIS' : 'ILGARION TURANIS'; ?></title>
    
    <!-- Favicon -->
    <link rel="manifest" href="../../favicon_io/site.webmanifest">
    <link rel="icon" href="../../favicon_io/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="favicon_io/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="favicon_io/android-chrome-512x512.png">
    <meta name="theme-color" content="#ffffff">

    <!-- Core CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&family=Phudu:wght@300..900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/css/style.css">
    <?php 
    // Ek CSS dosyaları
    if (isset($additional_css) && is_array($additional_css)) {
        foreach ($additional_css as $css_file) {
            echo '<link rel="stylesheet" href="/css/' . htmlspecialchars($css_file) . '">';
        }
    }
    ?>
        
</head>
<body>
    <div class="site-container">

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="global-message error-message">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<div class="global-message success-message">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['info_message'])) {
            echo '<div class="global-message info-message">' . htmlspecialchars($_SESSION['info_message']) . '</div>';
            unset($_SESSION['info_message']);
        }
        ?>