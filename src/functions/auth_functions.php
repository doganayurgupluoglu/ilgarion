<?php
// src/functions/auth_functions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Base URL'yi döndürür
 * @return string Base URL
 */
function get_auth_base_url(): string {
    return '/public';
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
 * İstemci IP adresini güvenli şekilde alır
 * @return string IP adresi
 */
function get_client_ip(): string {
    $ip_headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Proxy/Load Balancer
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];
    
    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * IP adresinin kısmi halini alır (fingerprint için)
 * @return string Kısmi IP
 */
function get_client_ip_partial(): string {
    $ip = get_client_ip();
    $ip_parts = explode('.', $ip);
    
    // IPv4 için ilk 3 okteti kullan
    if (count($ip_parts) === 4) {
        return implode('.', array_slice($ip_parts, 0, 3));
    }
    
    // IPv6 için farklı bir yaklaşım
    if (strpos($ip, ':') !== false) {
        $ipv6_parts = explode(':', $ip);
        return implode(':', array_slice($ipv6_parts, 0, 4));
    }
    
    return $ip;
}

/**
 * CSRF token oluşturur
 * @return string CSRF token
 */
function generate_csrf_token(): string {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) { // 1 saat
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token'ı doğrular
 * @param string $token Kontrol edilecek token
 * @return bool Geçerliyse true
 */
function verify_csrf_token(string $token): bool {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Token süresi dolmuş mu kontrol et (1 saat)
    if ((time() - $_SESSION['csrf_token_time']) > 3600) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate limiting kontrolü
 * @param string $action İşlem türü
 * @param int $max_attempts Maksimum deneme sayısı
 * @param int $time_window Zaman penceresi (saniye)
 * @return bool İzin veriliyorsa true
 */
function check_rate_limit(string $action, int $max_attempts = 5, int $time_window = 300): bool {
    $ip = get_client_ip();
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $rate_data = $_SESSION[$key];
    $current_time = time();
    
    // Zaman penceresi geçtiyse sıfırla
    if (($current_time - $rate_data['first_attempt']) > $time_window) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => $current_time];
        return true;
    }
    
    // Limit aşıldı mı kontrol et
    if ($rate_data['count'] >= $max_attempts) {
        error_log("Rate limit exceeded for action '$action' from IP: $ip");
        return false;
    }
    
    // Sayacı artır
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Login attempt kaydet (güvenlik için)
 * @param string $username Kullanıcı adı
 * @param bool $success Başarılı mı
 * @param string $reason Başarısızlık nedeni
 */
function log_login_attempt(string $username, bool $success, string $reason = ''): void {
    $ip = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $log_message = "LOGIN $status: User='$username', IP='$ip', Reason='$reason', UserAgent='$user_agent', Time='$timestamp'";
    error_log($log_message);
    
    // Başarısız girişimleri session'da da tut
    if (!$success) {
        $key = "failed_logins_$ip";
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        $_SESSION[$key][] = [
            'username' => $username,
            'time' => time(),
            'reason' => $reason
        ];
        
        // Eski kayıtları temizle (24 saat)
        $_SESSION[$key] = array_filter($_SESSION[$key], function($attempt) {
            return (time() - $attempt['time']) < 86400;
        });
    }
}

/**
 * Kullanıcı oturum geçerliliğini kontrol eder (güvenlik odaklı)
 */
function check_user_session_validity(): void {
    global $pdo;

    if (!isset($pdo)) {
        error_log("check_user_session_validity: PDO bağlantısı mevcut değil!");
        logout_user("Sistem hatası nedeniyle oturum sonlandırıldı. (DB Err)");
        return;
    }

    // Session güvenlik kontrolü
    if (!validate_session_fingerprint()) {
        logout_user("Session güvenlik ihlali tespit edildi.");
        return;
    }

    // Session ID'yi periyodik olarak yenile
    regenerate_session_id_safely();

    if (isset($_SESSION['user_id'])) {
        try {
            // Session timeout kontrolü (4 saat)
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 14400) {
                logout_user("Oturum süresi doldu. Güvenlik nedeniyle çıkış yapıldı.");
                return;
            }

            // Son aktivite zamanını güncelle
            $_SESSION['last_activity'] = time();

            // Kullanıcının durumunu ve rollerini tek bir sorguyla çek
            $stmt = $pdo->prepare("
                SELECT u.status, u.username,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ',') AS roles_list
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE u.id = :user_id
                GROUP BY u.id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $userDataFromDb = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userDataFromDb === false) {
                logout_user("Hesabınız artık mevcut değil.");
                return; 
            }

            $current_db_status = $userDataFromDb['status'];
            $current_db_roles_str = $userDataFromDb['roles_list'];
            $current_db_roles_array = $current_db_roles_str ? explode(',', $current_db_roles_str) : [];
            sort($current_db_roles_array);

            $session_status = $_SESSION['user_status'] ?? null;
            $session_roles_array = $_SESSION['user_roles'] ?? [];
            sort($session_roles_array);

            // Durum kontrolü
            if ($current_db_status !== 'approved') {
                logout_user("Hesap durumunuz '" . htmlspecialchars($current_db_status) . "' olarak değişti.");
                return;
            } elseif ($session_status !== $current_db_status) {
                $_SESSION['user_status'] = $current_db_status;
            }

            // Rol kontrolü ve güncellemesi
            if ($current_db_roles_array !== $session_roles_array) {
                $_SESSION['user_roles'] = $current_db_roles_array;
                
                // Rol değişikliği logla
                error_log("User roles updated for user {$_SESSION['user_id']}: " . 
                         "Old roles: " . implode(',', $session_roles_array) . 
                         " New roles: " . implode(',', $current_db_roles_array));
                
                // Permission cache'ini temizle
                if (function_exists('clear_user_permissions_cache')) {
                    clear_user_permissions_cache($_SESSION['user_id']);
                }
            }

            // Kullanıcı adı güncellemesi (görüntüleme için)
            if (!isset($_SESSION['username']) || $_SESSION['username'] !== $userDataFromDb['username']) {
                $_SESSION['username'] = $userDataFromDb['username'];
            }

        } catch (PDOException $e) {
            error_log("Kullanıcı oturum geçerliliği kontrol hatası: " . $e->getMessage());
            logout_user("Oturumunuz doğrulanırken bir veritabanı hatası oluştu.");
        }
    } elseif (is_user_logged_in()) { 
        logout_user("Oturum bilgilerinizde tutarsızlık tespit edildi.");
    }
}

/**
 * Kullanıcıyı güvenli şekilde çıkış yapar
 * @param string $message Çıkış mesajı
 */
function logout_user(string $message = "Güvenlik nedeniyle oturumunuz sonlandırıldı."): void {
    // Çıkış logla
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $ip = get_client_ip();
        error_log("User logout: ID=$user_id, IP=$ip, Reason: $message");
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
    
    header('Location: ' . get_auth_base_url() . '/login.php?status=logged_out');
    exit;
}

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder
 * @return bool Giriş yaptıysa true
 */
function is_user_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_status']);
}

/**
 * Kullanıcının onaylı olup olmadığını kontrol eder
 * @return bool Onaylıysa true
 */
function is_user_approved(): bool {
    return is_user_logged_in() && 
           isset($_SESSION['user_status']) && 
           $_SESSION['user_status'] === 'approved';
}

/**
 * Kullanıcının belirli bir role sahip olup olmadığını kontrol eder
 * @param string $role_name Rol adı
 * @return bool Role sahipse true
 */
function user_has_role(string $role_name): bool {
    if (!is_user_logged_in() || !isset($_SESSION['user_roles']) || !is_array($_SESSION['user_roles'])) {
        return false;
    }
    return in_array(trim($role_name), $_SESSION['user_roles']);
}

/**
 * Kullanıcının belirtilen rollerden herhangi birine sahip olup olmadığını kontrol eder
 * @param array $role_names Rol adları dizisi
 * @return bool Herhangi birine sahipse true
 */
function user_has_any_role(array $role_names): bool {
    if (!is_user_logged_in() || !isset($_SESSION['user_roles']) || !is_array($_SESSION['user_roles'])) {
        return false;
    }
    foreach ($role_names as $role_name) {
        if (in_array(trim($role_name), $_SESSION['user_roles'])) {
            return true;
        }
    }
    return false;
}

/**
 * Kullanıcının admin olup olmadığını kontrol eder
 * @return bool Admin ise true
 */
function is_user_admin(): bool {
    return user_has_role('admin');
}

/**
 * Kullanıcının SCG üyesi olup olmadığını kontrol eder
 * @return bool SCG üyesi ise true
 */
function is_scg_uye(): bool {
    return user_has_role('scg_uye');
}

/**
 * Kullanıcının Ilgarion Turanis üyesi olup olmadığını kontrol eder
 * @return bool Ilgarion Turanis üyesi ise true
 */
function is_ilgarion_turanis(): bool {
    return user_has_role('ilgarion_turanis');
}

/**
 * Kullanıcının dış üye olup olmadığını kontrol eder
 * @return bool Dış üye ise true
 */
function is_dis_uye(): bool {
    return user_has_role('dis_uye');
}

/**
 * Giriş yapmayı zorunlu kılar
 */
function require_login(): void {
    if (!is_user_logged_in()) {
        $_SESSION['error_message'] = "Bu sayfayı görüntülemek için giriş yapmalısınız.";
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . get_auth_base_url() . '/login.php?status=login_required');
        exit;
    }
    
    check_user_session_validity();
}

/**
 * Onaylı kullanıcı olmayı zorunlu kılar
 */
function require_approved_user(): void {
    require_login(); 
    if (!is_user_approved()) {
        $_SESSION['info_message'] = "Bu sayfayı görüntülemek için hesabınızın onaylanmış olması gerekmektedir.";
        header('Location: ' . get_auth_base_url() . '/login.php?status=approval_required');
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
 * Güvenli login işlemi (rate limiting ile)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $username Kullanıcı adı
 * @param string $password Şifre
 * @return array Login sonucu ['success' => bool, 'message' => string, 'user_data' => array|null]
 */
function secure_login(PDO $pdo, string $username, string $password): array {
    // Rate limiting kontrolü
    if (!check_rate_limit('login', 5, 900)) { // 15 dakikada 5 deneme
        log_login_attempt($username, false, 'Rate limit exceeded');
        return [
            'success' => false,
            'message' => 'Çok fazla başarısız deneme. 15 dakika sonra tekrar deneyin.',
            'user_data' => null
        ];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, password, email, status, ingame_name, discord_username, avatar_path,
                   GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ',') AS roles_list
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.username = :username OR u.email = :username
            GROUP BY u.id
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            log_login_attempt($username, false, 'User not found');
            return [
                'success' => false,
                'message' => 'Kullanıcı adı veya şifre hatalı.',
                'user_data' => null
            ];
        }

        if (!password_verify($password, $user['password'])) {
            log_login_attempt($username, false, 'Invalid password');
            return [
                'success' => false,
                'message' => 'Kullanıcı adı veya şifre hatalı.',
                'user_data' => null
            ];
        }

        if ($user['status'] !== 'approved') {
            log_login_attempt($username, false, 'Account not approved: ' . $user['status']);
            $status_messages = [
                'pending' => 'Hesabınız henüz onaylanmamış. Lütfen bekleyin.',
                'rejected' => 'Hesabınız reddedilmiş. Lütfen yönetici ile iletişime geçin.',
                'suspended' => 'Hesabınız askıya alınmış. Lütfen yönetici ile iletişime geçin.'
            ];
            return [
                'success' => false,
                'message' => $status_messages[$user['status']] ?? 'Hesap durumu geçersiz.',
                'user_data' => null
            ];
        }

        // Başarılı login
        log_login_attempt($username, true);
        
        return [
            'success' => true,
            'message' => 'Giriş başarılı.',
            'user_data' => $user
        ];

    } catch (PDOException $e) {
        error_log("Login database error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Sistem hatası. Lütfen tekrar deneyin.',
            'user_data' => null
        ];
    }
}

/**
 * Güvenli session başlatır
 * @param array $user_data Kullanıcı verileri
 */
function create_secure_session(array $user_data): void {
    // Eski session'ı temizle
    session_regenerate_id(true);
    
    // Güvenlik verileri
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['user_status'] = $user_data['status'];
    $_SESSION['user_roles'] = $user_data['roles_list'] ? explode(',', $user_data['roles_list']) : [];
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    $_SESSION['session_fingerprint'] = generate_session_fingerprint();
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
    $_SESSION['login_time'] = time();
    $_SESSION['login_ip'] = get_client_ip();
    
    // Opsiyonel kullanıcı bilgileri
    $_SESSION['user_email'] = $user_data['email'] ?? '';
    $_SESSION['user_ingame_name'] = $user_data['ingame_name'] ?? '';
    $_SESSION['user_discord'] = $user_data['discord_username'] ?? '';
    $_SESSION['user_avatar'] = $user_data['avatar_path'] ?? '';
}

/**
 * Suspicious activity detection
 * @param string $activity Aktivite türü
 * @param array $details Ek detaylar
 */
function detect_suspicious_activity(string $activity, array $details = []): void {
    $ip = get_client_ip();
    $user_id = $_SESSION['user_id'] ?? 'anonymous';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $suspicious_indicators = [
        'multiple_failed_logins' => count($_SESSION["failed_logins_$ip"] ?? []) > 3,
        'unusual_user_agent' => strlen($user_agent) < 10 || strlen($user_agent) > 500,
        'no_javascript' => !isset($_SESSION['js_enabled']), // Frontend'den set edilmeli
    ];
    
    $is_suspicious = false;
    $triggered_indicators = [];
    
    foreach ($suspicious_indicators as $indicator => $condition) {
        if ($condition) {
            $is_suspicious = true;
            $triggered_indicators[] = $indicator;
        }
    }
    
    if ($is_suspicious) {
        $log_data = [
            'activity' => $activity,
            'user_id' => $user_id,
            'ip' => $ip,
            'indicators' => $triggered_indicators,
            'details' => $details,
            'user_agent' => $user_agent,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("SUSPICIOUS ACTIVITY DETECTED: " . json_encode($log_data));
        
        // Çok şüpheli durumda session'ı sonlandır
        if (count($triggered_indicators) >= 2) {
            logout_user("Şüpheli aktivite tespit edildi. Güvenlik nedeniyle çıkış yapıldı.");
        }
    }
}

/**
 * Security headers ayarlar
 */
function set_security_headers(): void {
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Type sniffing prevention
    header('X-Content-Type-Options: nosniff');
    
    // Clickjacking protection
    header('X-Frame-Options: SAMEORIGIN');
    
    // HSTS (HTTPS kullanıyorsanız)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (sitenize göre ayarlayın)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com fonts.googleapis.com; img-src *; font-src 'self' cdnjs.cloudflare.com fonts.gstatic.com; connect-src 'self'; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com;");
}

/**
 * İki faktörlü kimlik doğrulama için QR kod oluşturur (gelecek özellik)
 * @param string $username Kullanıcı adı
 * @param string $secret Secret key
 * @return string QR kod URL'i
 */
function generate_2fa_qr_url(string $username, string $secret): string {
    $app_name = urlencode('Ilgarion Turanis');
    $qr_url = "otpauth://totp/{$app_name}:{$username}?secret={$secret}&issuer={$app_name}";
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_url);
}

// Güvenlik başlıklarını otomatik olarak ayarla
set_security_headers();

?>