<?php
// public/admin/test_super_admin.php - ERROR LOG İLE
require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Super Admin Test - ERROR LOG DEBUG</h1>";

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    echo "<p>User ID: $user_id</p>";
    
    // Error log'u temizle
    error_log("=== SUPER ADMIN TEST BAŞLADI ===");
    
    // is_super_admin'i çağır
    $result = is_super_admin($pdo, $user_id);
    echo "<p><strong>is_super_admin result: " . ($result ? 'TRUE ✅' : 'FALSE ❌') . "</strong></p>";
    
    echo "<h3>Error Log'u kontrol edin:</h3>";
    echo "<p>XAMPP'de: C:\\xampp\\apache\\logs\\error.log</p>";
    echo "<p>Veya PHP error log dosyasında 'is_super_admin' kelimesini arayın</p>";
    
    // Son error'ı göster
    $last_error = error_get_last();
    if ($last_error) {
        echo "<h3>Son PHP Hatası:</h3>";
        echo "<pre>" . print_r($last_error, true) . "</pre>";
    }
}
?>