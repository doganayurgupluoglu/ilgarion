<?php
// src/functions/auth_functions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/**
 * Base URL'yi döndürür
 * @return string Base URL
 */
function get_auth_base_url(): string
{
    // Protokolü belirle (HTTP veya HTTPS)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

    // Sunucu adını al
    $host = $_SERVER['HTTP_HOST'];

    // Projenin çalıştığı alt dizini hesaba kat

    // Temel URL'i birleştir ve döndür
    return rtrim($protocol . $host);
}

/**
 * Session güvenliği için session ID'yi güvenli bir şekilde yeniler
 * @param bool $force Zorla yenileme yapılsın mı
 */
function regenerate_session_id_safely(bool $force = false): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Her 15 dakikada bir veya force edildiğinde session ID'yi yenile
        $last_regeneration = $_SESSION['last_regeneration'] ?? 0;
        $current_time = time();
        
        if ($force || ($current_time - $last_regeneration) > 900) { // 15 dakika = 900 saniye
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = $current_time;
            
            // Session güvenlik tokenini da yenile
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
            
            // Log this security event
            error_log("Session ID regenerated for user: " . ($_SESSION['user_id'] ?? 'anonymous') . " from IP: " . get_client_ip());
        }
    }
}

/**
 * Session hijacking koruması - fingerprint kontrolü
 * @return bool Session geçerliyse true
 */
function validate_session_fingerprint(): bool {
    $current_fingerprint = generate_session_fingerprint();
    
    if (!isset($_SESSION['session_fingerprint'])) {
        $_SESSION['session_fingerprint'] = $current_fingerprint;
        return true;
    }
    
    return $_SESSION['session_fingerprint'] === $current_fingerprint;
}

/**
 * Session fingerprint oluşturur
 * @return string Unique fingerprint
 */
function generate_session_fingerprint(): string {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    // IP adresinin son oktetini dahil etme (NAT/proxy için)
    $ip_partial = get_client_ip_partial();
    
    return hash('sha256', $user_agent . $accept_language . $accept_encoding . $ip_partial);
}

/**
 * Kısmi IP adresi döndürür (NAT/proxy uyumluluğu için)
 * @return string IP adresinin ilk 3 okteti
 */
function get_client_ip_partial(): string {
    $ip = get_client_ip();
    $ip_parts = explode('.', $ip);
    return implode('.', array_slice($ip_parts, 0, 3));
}

/**
 * Gerçek client IP adresini döndürür
 * @return string IP adresi
 */
function get_client_ip(): string {
    $ip_headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];

    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Multiple IP'ler varsa ilkini al
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // IP geçerliliğini kontrol et
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * CSRF token oluşturur
 * @return string CSRF token
 */
function generate_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token'ı doğrular - ESKİ FONKSİYON ADI
 * @param string $token Kontrol edilecek token
 * @return bool Token geçerliyse true
 */
function validate_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF token'ı doğrular - YENİ FONKSİYON ADI (validate_csrf_token ile aynı)
 * @param string $token Kontrol edilecek token
 * @return bool Token geçerliyse true
 */
function verify_csrf_token(string $token): bool {
    return validate_csrf_token($token);
}

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder
 * @return bool Giriş yapmışsa true
 */
function is_user_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Kullanıcının onaylanmış olup olmadığını kontrol eder
 * @return bool Onaylanmışsa true
 */
function is_user_approved(): bool {
    if (!is_user_logged_in()) {
        return false;
    }
    return ($_SESSION['user_status'] ?? '') === 'approved';
}

/**
 * Kullanıcının admin olup olmadığını kontrol eder
 * @return bool Admin ise true
 */
function is_user_admin(): bool {
    if (!is_user_approved()) {
        return false;
    }
    $user_roles = $_SESSION['user_roles'] ?? [];
    return in_array('Admin', $user_roles) || in_array('Super Admin', $user_roles);
}

/**
 * Kullanıcının belirli bir role sahip olup olmadığını kontrol eder
 * @param string $role Kontrol edilecek rol
 * @return bool Role sahipse true
 */
function user_has_role(string $role): bool {
    if (!is_user_approved()) {
        return false;
    }
    $user_roles = $_SESSION['user_roles'] ?? [];
    return in_array($role, $user_roles);
}

/**
 * Kullanıcının belirtilen rollerden herhangi birine sahip olup olmadığını kontrol eder
 * @param array $roles Kontrol edilecek roller
 * @return bool Herhangi bir role sahipse true
 */
function user_has_any_role(array $roles): bool {
    if (!is_user_approved()) {
        return false;
    }
    $user_roles = $_SESSION['user_roles'] ?? [];
    return !empty(array_intersect($roles, $user_roles));
}

/**
 * Session geçerliliğini kontrol eder
 * @return bool Session geçerliyse true
 */
function check_user_session_validity(): bool {
    global $pdo;
    
    // Session başlatılmış mı kontrol et
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Kullanıcı giriş yapmış mı
    if (!is_user_logged_in()) {
        return false;
    }
    
    // Session timeout kontrolü (24 saat)
    $session_timeout = 24 * 60 * 60; // 24 saat
    $last_activity = $_SESSION['last_activity'] ?? 0;
    
    if ((time() - $last_activity) > $session_timeout) {
        logout_user("Oturumunuz zaman aşımına uğradı. Lütfen tekrar giriş yapın.");
        return false;
    }
    
    // Session fingerprint kontrolü
    if (!validate_session_fingerprint()) {
        logout_user("Güvenlik nedeniyle oturumunuz sonlandırıldı.");
        return false;
    }
    
    // Aktiviteyi güncelle
    update_user_activity();
    
    // Session ID'yi güvenli şekilde yenile
    regenerate_session_id_safely();
    
    // Database'den kullanıcı verilerini doğrula (opsiyonel)
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("
                SELECT u.status, GROUP_CONCAT(r.name) as roles
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$db_user || $db_user['status'] !== 'approved') {
                logout_user("Hesap durumunuzda değişiklik tespit edildi.");
                return false;
            }
            
            // Rolleri güncelle
            $current_db_roles = $db_user['roles'] ? explode(',', $db_user['roles']) : [];
            $session_roles = $_SESSION['user_roles'] ?? [];
            
            if ($current_db_roles !== $session_roles) {
                $_SESSION['user_roles'] = $current_db_roles;
                error_log("User roles updated for user ID: " . $_SESSION['user_id'] . 
                         " New roles: " . implode(',', $current_db_roles));
            }
        }
    } catch (PDOException $e) {
        error_log("Session validation error: " . $e->getMessage());
        // Database hatası durumunda session'ı sonlandırmayız, sadece log tutarız
    }
    
    return true;
}

/**
 * Onaylanmış kullanıcı gerektiren sayfalar için kontrol
 */
function require_approved_user(): void {
    if (!is_user_approved()) {
        $_SESSION['error_message'] = "Bu sayfaya erişmek için onaylanmış bir hesaba sahip olmalısınız.";
        header('Location: ' . get_auth_base_url() . '/register.php?status=approval_required');
        exit;
    }
}

/**
 * Giriş yapmayı zorunlu kılar
 */
function require_login(): void {
    if (!is_user_logged_in()) {
        $_SESSION['info_message'] = "Bu sayfayı görüntülemek için giriş yapmanız gerekmektedir.";
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . get_auth_base_url() . '/register.php?status=login_required');
        exit;
    }
    
    check_user_session_validity();
}

/**
 * Admin kullanıcı gerektiren sayfalar için kontrol
 */
function require_admin_user(): void {
    if (!is_user_admin()) {
        $_SESSION['error_message'] = "Bu sayfaya erişmek için admin yetkileriniz bulunmuyor.";
        header('Location: ' . get_auth_base_url() . '/index.php');
        exit;
    }
}

/**
 * Admin olmayı zorunlu kılar
 */
function require_admin(): void {
    require_login(); 
    if (!is_user_admin()) {
        $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır (Admin Gerekli).";
        
        // Yetkisiz erişim girişimini logla
        error_log("Unauthorized admin access attempt: User ID=" . ($_SESSION['user_id'] ?? 'unknown') . 
                 ", IP=" . get_client_ip() . ", URL=" . ($_SERVER['REQUEST_URI'] ?? ''));
        
        header('Location: ' . get_auth_base_url() . '/index.php?status=admin_required');
        exit;
    }
}

/**
 * Belirli bir role sahip olmayı zorunlu kılar
 * @param string|array $required_role Gerekli rol(ler)
 * @param string|null $redirect_url_on_fail Başarısız olursa yönlendirilecek URL
 */
function require_role($required_role, ?string $redirect_url_on_fail = null): void { 
    require_approved_user();

    $has_required_role = false;
    if (is_array($required_role)) {
        $has_required_role = user_has_any_role($required_role);
    } else {
        $has_required_role = user_has_role((string)$required_role);
    }

    if (!$has_required_role) {
        $_SESSION['error_message'] = "Bu içeriğe erişmek için gerekli role sahip değilsiniz.";
        
        // Yetkisiz erişim girişimini logla
        $required_roles_str = is_array($required_role) ? implode(',', $required_role) : $required_role;
        error_log("Unauthorized role access attempt: User ID=" . ($_SESSION['user_id'] ?? 'unknown') . 
                 ", Required roles=" . $required_roles_str . ", IP=" . get_client_ip() . 
                 ", URL=" . ($_SERVER['REQUEST_URI'] ?? ''));
        
        if ($redirect_url_on_fail === null) {
            $redirect_url_on_fail = get_auth_base_url() . '/index.php?status=role_required';
        }
        header('Location: ' . $redirect_url_on_fail);
        exit;
    }
}

/**
 * Session'ı güvenli şekilde temizler ve çıkış yapar
 */
function logout_user(string $message = "Güvenlik nedeniyle oturumunuz sonlandırıldı."): void {
    // Çıkış logla
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $ip = get_client_ip();
        error_log("User logout: ID=$user_id, IP=$ip, Reason: $message");
    }

    // Remember me cookie'sini temizle (eğer fonksiyon varsa)
    if (function_exists('clear_remember_me_cookie')) {
        clear_remember_me_cookie();
    }

    // Session verilerini temizle
    $_SESSION = array();
    
    // Session cookie'sini sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Session'ı yok et
    @session_destroy();
    
    // Yeni session başlat ve mesajı koy
    session_start();
    $_SESSION['info_message'] = $message; 
    
    header('Location: ' . get_auth_base_url() . '/register.php?status=logged_out');
    exit;
}

/**
 * Kullanıcı profil bilgilerini döndürür
 * @return array|null Kullanıcı bilgileri veya null
 */
function get_user_profile(): ?array {
    if (!is_user_logged_in()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['user_email'] ?? '',
        'ingame_name' => $_SESSION['user_ingame_name'] ?? '',
        'discord' => $_SESSION['user_discord'] ?? '',
        'avatar' => $_SESSION['user_avatar'] ?? '',
        'status' => $_SESSION['user_status'] ?? '',
        'roles' => $_SESSION['user_roles'] ?? [],
        'login_time' => $_SESSION['login_time'] ?? 0,
        'last_activity' => $_SESSION['last_activity'] ?? 0
    ];
}

/**
 * Kullanıcı bilgilerini session'dan döndürür
 * @return array Kullanıcı bilgileri
 */
function get_current_user_info(): array {
    if (!is_user_logged_in()) {
        return [];
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'ingame_name' => $_SESSION['user_ingame_name'] ?? '',
        'discord' => $_SESSION['user_discord'] ?? '',
        'avatar' => $_SESSION['user_avatar'] ?? '',
        'status' => $_SESSION['user_status'] ?? '',
        'roles' => $_SESSION['user_roles'] ?? [],
        'login_time' => $_SESSION['login_time'] ?? 0,
        'last_activity' => $_SESSION['last_activity'] ?? 0
    ];
}

/**
 * Kullanıcı kimlik doğrulaması yapar
 * @param string $username Kullanıcı adı veya email
 * @param string $password Şifre
 * @return array Sonuç dizisi [success, message, user_data]
 */
function authenticate_user(string $username, string $password): array {
    global $pdo;

    if (!isset($pdo)) {
        return [
            'success' => false,
            'message' => 'Veritabanı bağlantısı mevcut değil.',
            'user_data' => null
        ];
    }

    try {
        // Rate limiting kontrolü
        $ip = get_client_ip();
        $failed_attempts = $_SESSION["failed_logins_$ip"] ?? [];
        
        // Son 15 dakikada 5'ten fazla başarısız deneme varsa engelle
        $recent_failures = array_filter($failed_attempts, function($attempt) {
            return (time() - $attempt['time']) < 900; // 15 dakika
        });
        
        if (count($recent_failures) >= 5) {
            log_login_attempt($username, false, 'Rate limit exceeded');
            return [
                'success' => false,
                'message' => 'Çok fazla başarısız deneme. 15 dakika sonra tekrar deneyin.',
                'user_data' => null
            ];
        }

        // Kullanıcıyı bul (username veya email ile)
        $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.password, u.status, u.ingame_name,
               u.discord_username, u.avatar_path,
               GROUP_CONCAT(r.name) as roles_list
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE (u.username = ? OR u.email = ?)
        GROUP BY u.id
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            record_failed_login_attempt($username);
            log_login_attempt($username, false, 'User not found');
            return [
                'success' => false,
                'message' => 'Kullanıcı adı veya şifre hatalı.',
                'user_data' => null
            ];
        }

        // Şifre kontrolü
        if (!password_verify($password, $user['password'])) {
            record_failed_login_attempt($username);
            log_login_attempt($username, false, 'Wrong password');
            return [
                'success' => false,
                'message' => 'Kullanıcı adı veya şifre hatalı.',
                'user_data' => null
            ];
        }

        // Hesap durumu kontrolü
        if ($user['status'] !== 'approved') {
            log_login_attempt($username, false, 'Account not approved: ' . $user['status']);
            $status_messages = [
                'pending' => 'Hesabınız henüz onaylanmamış. Lütfen admin onayını bekleyin.',
                'suspended' => 'Hesabınız askıya alınmış. Lütfen yöneticiler ile iletişime geçin.',
                'banned' => 'Hesabınız yasaklanmış.',
            ];
            return [
                'success' => false,
                'message' => $status_messages[$user['status']] ?? 'Hesap durumunuz giriş yapmaya uygun değil.',
                'user_data' => null
            ];
        }

        // Başarılı giriş - failed attempts temizle
        clear_login_attempts($username);
        
        // Roller dizisi oluştur
        $roles = $user['roles_list'] ? explode(',', $user['roles_list']) : [];

        $user_data = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'status' => $user['status'],
            'ingame_name' => $user['ingame_name'],
            'discord' => $user['discord_username'],
            'avatar' => $user['avatar_path'],
            'roles' => $roles
        ];

        log_login_attempt($username, true, 'Successful login');
        
        return [
            'success' => true,
            'message' => 'Giriş başarılı!',
            'user_data' => $user_data
        ];

    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Giriş sırasında bir hata oluştu. Lütfen tekrar deneyin.',
            'user_data' => null
        ];
    }
}

/**
 * Güvenli session oluşturur
 * @param array $user_data Kullanıcı verileri
 */
function create_secure_session(array $user_data): void {
    // Session hijacking koruması
    session_regenerate_id(true);
    
    // Session verilerini ayarla
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['user_email'] = $user_data['email'];
    $_SESSION['user_status'] = $user_data['status'];
    $_SESSION['user_ingame_name'] = $user_data['ingame_name'];
    $_SESSION['user_discord'] = $user_data['discord'];
    $_SESSION['user_avatar'] = $user_data['avatar'];
    $_SESSION['user_roles'] = $user_data['roles'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Güvenlik fingerprint'i oluştur
    $_SESSION['session_fingerprint'] = generate_session_fingerprint();
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
    
    // Login logu
    $ip = get_client_ip();
    error_log("Secure session created for user: " . $user_data['username'] . " (ID: " . $user_data['id'] . ") from IP: $ip");
}

/**
 * Güvenli şifre hash'i oluşturur
 * @param string $password Şifre
 * @return string Hash'lenmiş şifre
 */
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterasyon
        'threads' => 3,         // 3 thread
    ]);
}

/**
 * Şifre doğrulaması yapar
 * @param string $password Girilen şifre
 * @param string $hash Veritabanındaki hash
 * @return bool Şifre doğruysa true
 */
function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Şifre güvenlik kontrolü yapar
 * @param string $password Kontrol edilecek şifre
 * @return array Sonuç dizisi [valid, message, strength]
 */
function validate_password_strength(string $password): array {
    $errors = [];
    $strength = 0;
    
    // Minimum uzunluk kontrolü
    if (strlen($password) < 8) {
        $errors[] = 'Şifre en az 8 karakter olmalıdır';
    } else {
        $strength += 20;
    }
    
    // Büyük harf kontrolü
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'En az bir büyük harf içermelidir';
    } else {
        $strength += 20;
    }
    
    // Küçük harf kontrolü
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'En az bir küçük harf içermelidir';
    } else {
        $strength += 20;
    }
    
    // Rakam kontrolü
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'En az bir rakam içermelidir';
    } else {
        $strength += 20;
    }
    
    // Özel karakter kontrolü
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'En az bir özel karakter içermelidir';
    } else {
        $strength += 20;
    }
    
    // Uzunluk bonusu
    if (strlen($password) >= 12) {
        $strength += 10;
    }
    
    // Yaygın şifre kontrolü
    $common_passwords = ['password', '123456', 'qwerty', 'abc123', 'password123'];
    if (in_array(strtolower($password), $common_passwords)) {
        $errors[] = 'Çok yaygın bir şifre kullanıyorsunuz';
        $strength -= 30;
    }
    
    $strength = max(0, min(100, $strength));
    
    return [
        'valid' => empty($errors),
        'message' => empty($errors) ? 'Şifre güçlü' : implode(', ', $errors),
        'strength' => $strength
    ];
}

/**
 * Email adresinin geçerliliğini kontrol eder
 * @param string $email Email adresi
 * @return bool Geçerliyse true
 */
function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Kullanıcı adının geçerliliğini kontrol eder
 * @param string $username Kullanıcı adı
 * @return array Sonuç dizisi [valid, message]
 */
function validate_username(string $username): array {
    // Uzunluk kontrolü
    if (strlen($username) < 3 || strlen($username) > 50) {
        return ['valid' => false, 'message' => 'Kullanıcı adı 3-50 karakter arasında olmalıdır'];
    }
    
    // Karakter kontrolü (sadece harf, rakam, alt çizgi)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['valid' => false, 'message' => 'Kullanıcı adı sadece harf, rakam ve alt çizgi içerebilir'];
    }
    
    // Başlangıç ve bitiş kontrolü (alt çizgi ile başlayamaz/bitemez)
    if (preg_match('/^_|_$/', $username)) {
        return ['valid' => false, 'message' => 'Kullanıcı adı alt çizgi ile başlayamaz veya bitemez'];
    }
    
    // Yasaklı kelimeler
    $forbidden_words = ['admin', 'administrator', 'root', 'system', 'user', 'test', 'guest', 'null', 'undefined'];
    if (in_array(strtolower($username), $forbidden_words)) {
        return ['valid' => false, 'message' => 'Bu kullanıcı adı kullanılamaz'];
    }
    
    return ['valid' => true, 'message' => 'Kullanıcı adı geçerli'];
}

/**
 * Kullanıcının son aktivite zamanını günceller
 */
function update_user_activity(): void {
    if (is_user_logged_in()) {
        $_SESSION['last_activity'] = time();
        
        // Şüpheli aktivite tespiti
        detect_suspicious_activity('page_access');
    }
}

/**
 * Rate limiting kontrolü yapar
 * @param string $action Eylem türü
 * @param int $limit Limit
 * @param int $window Zaman penceresi (saniye)
 * @return bool Limit aşılmadıysa true
 */
function check_rate_limit(string $action, int $limit = 5, int $window = 300): bool {
    $ip = get_client_ip();
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Eski kayıtları temizle
    $current_time = time();
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($current_time, $window) {
        return ($current_time - $timestamp) < $window;
    });
    
    // Limit kontrolü
    if (count($_SESSION[$key]) >= $limit) {
        return false;
    }
    
    // Yeni kayıt ekle
    $_SESSION[$key][] = $current_time;
    
    return true;
}

/**
 * XSS koruması için string'i temizler
 * @param string $input Temizlenecek string
 * @return string Temizlenmiş string
 */
function sanitize_input(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Output için string'i temizler
 * @param string $input Temizlenecek string
 * @return string Temizlenmiş string
 */
function sanitize_output(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * SQL injection koruması için string'i temizler
 * @param string $input Temizlenecek string
 * @return string Temizlenmiş string
 */
function sanitize_sql_input(string $input): string {
    return trim(strip_tags($input));
}

/**
 * Şüpheli aktivite tespiti
 * @param string $activity_type Aktivite türü
 */
function detect_suspicious_activity(string $activity_type): void {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $ip = get_client_ip();
    $current_time = time();
    
    // Activity log key
    $key = "user_activity_{$user_id}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Son 5 dakikadaki aktiviteleri say
    $recent_activities = array_filter($_SESSION[$key], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 300; // 5 dakika
    });
    
    // Çok fazla aktivite varsa şüpheli say
    if (count($recent_activities) > 50) {
        error_log("SUSPICIOUS ACTIVITY: User ID $user_id from IP $ip - Too many activities in 5 minutes");
        
        // Session'ı kilitle
        $_SESSION['account_locked'] = true;
        $_SESSION['lock_reason'] = 'Şüpheli aktivite tespit edildi';
        $_SESSION['lock_time'] = $current_time;
    }
    
    // Aktiviteyi kaydet
    $_SESSION[$key][] = $current_time;
    
    // Eski kayıtları temizle (1 saatten eski)
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 3600;
    });
}

/**
 * Hesap kilitli mi kontrol eder
 * @return bool Kilitliyse true
 */
function is_account_locked(): bool {
    if (!isset($_SESSION['account_locked']) || !$_SESSION['account_locked']) {
        return false;
    }
    
    $lock_time = $_SESSION['lock_time'] ?? 0;
    $current_time = time();
    
    // 30 dakika sonra kilidi aç
    if (($current_time - $lock_time) > 1800) {
        unset($_SESSION['account_locked']);
        unset($_SESSION['lock_reason']);
        unset($_SESSION['lock_time']);
        return false;
    }
    
    return true;
}

/**
 * Güvenlik olayını loglar
 * @param string $event Olay türü
 * @param array $details Olay detayları
 */
function log_security_event(string $event, array $details = []): void {
    $user_id = $_SESSION['user_id'] ?? 'anonymous';
    $ip = get_client_ip();
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = [
        'timestamp' => $timestamp,
        'event' => $event,
        'user_id' => $user_id,
        'ip' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'details' => $details
    ];
    
    error_log("SECURITY EVENT: " . json_encode($log_entry));
}

/**
 * Brute force saldırı koruması
 * @param string $identifier Kimlik (email/username)
 * @return bool Daha fazla deneme yapılabilirse true
 */
function check_brute_force_protection(string $identifier): bool {
    $ip = get_client_ip();
    $key = "login_attempts_{$ip}_{$identifier}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    $current_time = time();
    
    // Son 15 dakikadaki denemeleri kontrol et
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 900; // 15 dakika
    });
    
    // 5 deneme hakkı var
    return count($_SESSION[$key]) < 5;
}

/**
 * Başarısız giriş denemesini kaydet
 * @param string $identifier Kimlik (email/username)
 */
function record_failed_login_attempt(string $identifier): void {
    $ip = get_client_ip();
    $key = "login_attempts_{$ip}_{$identifier}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    $_SESSION[$key][] = time();
    
    log_security_event('failed_login', [
        'identifier' => $identifier,
        'attempts_count' => count($_SESSION[$key])
    ]);
}

/**
 * Başarılı giriş sonrası brute force verilerini temizle
 * @param string $identifier Kimlik (email/username)
 */
function clear_login_attempts(string $identifier): void {
    $ip = get_client_ip();
    $key = "login_attempts_{$ip}_{$identifier}";
    unset($_SESSION[$key]);
}

/**
 * Login girişimini loglar
 * @param string $username Kullanıcı adı
 * @param bool $success Başarılı mı
 * @param string $reason Sebep
 */
function log_login_attempt(string $username, bool $success, string $reason = ''): void {
    $ip = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $timestamp = date('Y-m-d H:i:s');
    
    $status = $success ? 'SUCCESS' : 'FAILED';
    $log_message = "LOGIN_$status: Username=$username, IP=$ip, Reason=$reason, UserAgent=$user_agent, Time=$timestamp";
    
    error_log($log_message);
}

/**
 * File upload güvenlik kontrolü
 * @param array $file $_FILES array elemanı
 * @param array $allowed_types İzin verilen MIME tipleri
 * @param int $max_size Maximum dosya boyutu (byte)
 * @return array [success, message, safe_filename]
 */
function validate_file_upload(array $file, array $allowed_types = [], int $max_size = 5242880): array {
    // Dosya yüklendi mi kontrol et
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Dosya seçilmedi'];
    }
    
    // Upload hatası kontrol et
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu çok büyük (php.ini limiti)',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu çok büyük (form limiti)',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmi olarak yüklendi',
            UPLOAD_ERR_NO_FILE => 'Dosya yüklenmedi',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı',
            UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
            UPLOAD_ERR_EXTENSION => 'Dosya uzantısı engellendi'
        ];
        
        return [
            'success' => false, 
            'message' => $error_messages[$file['error']] ?? 'Bilinmeyen dosya hatası'
        ];
    }
    
    // Dosya boyutu kontrol et
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Dosya çok büyük (Max: ' . ($max_size / 1024 / 1024) . 'MB)'];
    }
    
    // MIME tipi kontrol et
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!empty($allowed_types) && !in_array($detected_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Desteklenmeyen dosya türü'];
    }
    
    // Güvenli dosya adı oluştur
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    return [
        'success' => true,
        'message' => 'Dosya geçerli',
        'safe_filename' => $safe_filename,
        'original_name' => $file['name'],
        'detected_type' => $detected_type
    ];
}

/**
 * Two-Factor Authentication token oluştur
 * @return string 6 haneli token
 */
function generate_2fa_token(): string {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Email verification token oluştur
 * @return string Verification token
 */
function generate_verification_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Password reset token oluştur
 * @return string Reset token
 */
function generate_password_reset_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Token'ın geçerliliğini kontrol et
 * @param string $token Token
 * @param string $stored_token Saklanan token
 * @param int $expiry Token geçerlilik süresi (saniye)
 * @param int $created_time Token oluşturulma zamanı
 * @return bool Geçerliyse true
 */
function verify_token(string $token, string $stored_token, int $expiry, int $created_time): bool {
    // Token eşleşiyor mu
    if (!hash_equals($stored_token, $token)) {
        return false;
    }
    
    // Token süresi dolmuş mu
    if ((time() - $created_time) > $expiry) {
        return false;
    }
    
    return true;
}

/**
 * Kullanıcı için unique slug oluştur
 * @param string $username Kullanıcı adı
 * @return string Unique slug
 */
function generate_user_slug(string $username): string {
    // Türkçe karakterleri dönüştür
    $slug = str_replace(
        ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'],
        ['c', 'g', 'i', 'o', 's', 'u', 'C', 'G', 'I', 'I', 'O', 'S', 'U'],
        $username
    );
    
    // Alphanumeric olmayan karakterleri temizle
    $slug = preg_replace('/[^a-zA-Z0-9_]/', '', $slug);
    $slug = strtolower($slug);
    
    // Benzersizlik için timestamp ekle
    return $slug . '_' . time();
}

/**
 * Remember Me özelliği için fonksiyonlar
 */

/**
 * Remember me cookie'si oluşturur - handle_login.php'nin beklediği fonksiyon
 * @param int $user_id Kullanıcı ID'si
 * @return bool Başarılıysa true
 */
function set_remember_me_cookie(int $user_id): bool {
    global $pdo;
    
    try {
        // Rastgele token oluştur
        $token = bin2hex(random_bytes(32));
        $selector = bin2hex(random_bytes(16));
        $token_hash = hash('sha256', $token);
        
        // Token'ı veritabanına kaydet (30 gün geçerli)
        $expires = time() + (30 * 24 * 60 * 60); // 30 gün
        
        // Remember tokens tablosu kontrol et - yoksa oluştur
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                selector VARCHAR(32) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_selector (selector),
                INDEX idx_user_expires (user_id, expires_at)
            )
        ");
        
        // Eski remember me token'larını sil
        $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? OR expires_at < NOW()");
        $stmt->execute([$user_id]);
        
        // Yeni token'ı ekle
        $stmt = $pdo->prepare("
            INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) 
            VALUES (?, ?, ?, FROM_UNIXTIME(?))
        ");
        $stmt->execute([$user_id, $selector, $token_hash, $expires]);
        
        // Cookie'yi set et
        $cookie_value = $selector . ':' . $token;
        $cookie_set = setcookie(
            'remember_me',
            $cookie_value,
            [
                'expires' => $expires,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        if ($cookie_set) {
            error_log("Remember me cookie set for user ID: $user_id");
            return true;
        } else {
            error_log("Failed to set remember me cookie for user ID: $user_id");
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("Remember me cookie creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Remember Me cookie oluştur - alternatif fonksiyon
 * @param int $user_id Kullanıcı ID
 * @param string $username Kullanıcı adı
 * @return bool Başarılıysa true
 */
function create_remember_me_cookie(int $user_id, string $username): bool {
    return set_remember_me_cookie($user_id);
}

/**
 * Remember me cookie'sini kontrol eder ve kullanıcıyı otomatik giriş yapar
 * @return array|null Kullanıcı verileri veya null
 */
function check_remember_me_cookie(): ?array {
    global $pdo;
    
    if (!isset($_COOKIE['remember_me'])) {
        return null;
    }
    
    try {
        $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($cookie_parts) !== 2) {
            clear_remember_me_cookie();
            return null;
        }
        
        [$selector, $token] = $cookie_parts;
        $token_hash = hash('sha256', $token);
        
        // Token'ı veritabanından kontrol et
        $stmt = $pdo->prepare("
            SELECT rt.user_id, rt.token_hash, u.username, u.email, u.status, u.ingame_name,
                   u.discord_username, u.avatar_path,
                   GROUP_CONCAT(r.name) as roles_list
            FROM user_remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE rt.selector = ? AND rt.expires_at > NOW() AND u.status = 'approved'
            GROUP BY rt.user_id
        ");
        $stmt->execute([$selector]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !hash_equals($user['token_hash'], $token_hash)) {
            clear_remember_me_cookie();
            return null;
        }
        
        // Token'ı yenile (güvenlik için)
        $new_token = bin2hex(random_bytes(32));
        $new_token_hash = hash('sha256', $new_token);
        $new_expires = time() + (30 * 24 * 60 * 60);
        
        $stmt = $pdo->prepare("
            UPDATE user_remember_tokens 
            SET token_hash = ?, expires_at = FROM_UNIXTIME(?) 
            WHERE selector = ?
        ");
        $stmt->execute([$new_token_hash, $new_expires, $selector]);
        
        // Cookie'yi yenile
        $new_cookie_value = $selector . ':' . $new_token;
        setcookie(
            'remember_me',
            $new_cookie_value,
            [
                'expires' => $new_expires,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        error_log("Auto-login via remember me for user: " . $user['username']);
        
        return [
            'id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'status' => $user['status'],
            'ingame_name' => $user['ingame_name'],
            'discord_username' => $user['discord_username'],
            'avatar_path' => $user['avatar_path'],
            'roles_list' => $user['roles_list']
        ];
        
    } catch (PDOException $e) {
        error_log("Remember me check error: " . $e->getMessage());
        clear_remember_me_cookie();
        return null;
    }
}

/**
 * Remember me cookie'sini temizler
 */
function clear_remember_me_cookie(): void {
    global $pdo;
    
    if (isset($_COOKIE['remember_me'])) {
        $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($cookie_parts) === 2) {
            $selector = $cookie_parts[0];
            
            try {
                // Veritabanından token'ı sil
                $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
                $stmt->execute([$selector]);
            } catch (PDOException $e) {
                error_log("Remember me token deletion error: " . $e->getMessage());
            }
        }
        
        // Cookie'yi sil
        setcookie(
            'remember_me',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
}

// REMEMBER ME ÖZELLİĞİ İÇİN OTOMATİK KONTROL
// Bu kodu dosyanın sonuna ekleyin veya index.php'de çağırın
// Sadece giriş sayfası değilse ve giriş yapmamışsa kontrol et
if (!is_user_logged_in() && 
    !isset($_GET['mode']) && 
    strpos($_SERVER['REQUEST_URI'], 'auth.php') === false && 
    strpos($_SERVER['REQUEST_URI'], 'register.php') === false &&
    strpos($_SERVER['REQUEST_URI'], 'api/') === false) {
    
    // Remember me cookie kontrolü
    $user_data = check_remember_me_cookie();
    if ($user_data) {
        create_secure_session($user_data);
        error_log("Auto-login via remember me for user: " . $user_data['username']);
        header("Refresh: 0");
        exit;
    }
}

function auto_login_check(): void {
    // Zaten giriş yapmışsa kontrol etme
    if (is_user_logged_in()) {
        return;
    }
       // ↓ BU SATIRI EKLE ↓
       $current_page = $_SERVER['REQUEST_URI'] ?? '';
    // Giriş sayfalarında kontrol etme
    $auth_pages = ['auth.php', 'register.php', 'login.php', 'handle_login.php', 'handle_register.php'];
    
    // API çağrılarında kontrol etme
    if (strpos($current_page, '/api/') !== false || strpos($current_page, '/actions/') !== false) {
        return;
    }
    
    // Remember me cookie kontrolü ve otomatik giriş
    $user_data = check_remember_me_cookie();
    if ($user_data) {
        create_secure_session($user_data);
        // Güvenli log kaydı
    }
}

?>