<?php
// src/actions/handle_login.php - Remember Me özelliği eklenmiş temiz versiyon

require_once '../config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Form verilerini al
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$remember_me = $_POST['remember_me'] ?? ''; // Remember me kontrolü
$csrf_token = $_POST['csrf_token'] ?? '';

// CSRF token kontrolü
if (!validate_csrf_token($csrf_token)) {
    header('Location: ' . get_auth_base_url() . '/index.php?mode=login');
    exit;
}

// Honeypot kontrolü (bot koruması)
if (!empty($_POST['honeypot'])) {
    header('Location: ' . get_auth_base_url() . '/index.php?mode=login');
    exit;
}

// Form validasyonu
if (empty($username) || empty($password)) {
    header('Location: ' . get_auth_base_url() . '/index.php?mode=login');
    exit;
}

// Rate limiting kontrolü
if (!check_rate_limit('login', 5, 900)) { // 5 deneme 15 dakikada
    header('Location: ' . get_auth_base_url() . '/index.php?mode=login');
    exit;
}

// Login işlemi
$login_result = authenticate_user($username, $password);

if ($login_result['success']) {
    // Session oluştur
    create_secure_session($login_result['user_data']);
    
    // REMEMBER ME ÖZELLİĞİ
    if ($remember_me === '1') {
        set_remember_me_cookie($login_result['user_data']['id']);
    }
    
    
    // Redirect after login
    $redirect_url = $_SESSION['redirect_after_login'] ?? get_auth_base_url() . '/index.php';
    unset($_SESSION['redirect_after_login']);
    
    // Başarılı giriş logı
    error_log("Successful login: User=" . $login_result['user_data']['username'] . 
             ", IP=" . get_client_ip() . 
             ", RememberMe=" . ($remember_me === '1' ? 'Yes' : 'No'));
    
    header('Location: ' . $redirect_url);
    exit;
    
} else {
    $_SESSION['error_message'] = $login_result['message'];
    
    // Form verilerini geri döndür (şifre hariç)
    $_SESSION['form_input'] = [
        'username' => $username
    ];
    
    header('Location: ' . get_auth_base_url() . '/index.php?mode=login');
    exit;
}
?>