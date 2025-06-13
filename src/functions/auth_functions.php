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
 * CSRF token'ı doğrular
 * @param string $token Kontrol edilecek token
 * @return bool Token geçerliyse true
 */
function validate_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
            log_login_attempt($username, false, 'User not found');
            return [
                'success' => false,
                'message' => 'Kullanıcı adı veya şifre hatalı.',
                'user_data' => null
            ];
        }

        // Şifre kontrolü
        if (!password_verify($password, $user['password'])) {
            log_login_attempt($username, false, 'Invalid password');
            return [
                'success' => false,
                'message' => 'Kullanıcı adı veya şifre hatalı.',
                'user_data' => null
            ];
        }

        // Hesap durumu kontrolü
        if ($user['status'] !== 'approved') {
            log_login_attempt($username, false, 'Account status: ' . $user['status']);
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
 * REMEMBER ME ÖZELLİĞİ - YENİ FONKSİYONLAR
 */

/**
 * Remember me cookie'si oluşturur
 * @param int $user_id Kullanıcı ID'si
 */
function set_remember_me_cookie(int $user_id): void {
    global $pdo;
    
    try {
        // Rastgele token oluştur
        $token = bin2hex(random_bytes(32));
        $selector = bin2hex(random_bytes(16));
        $token_hash = hash('sha256', $token);
        
        // Token'ı veritabanına kaydet (30 gün geçerli)
        $expires = time() + (30 * 24 * 60 * 60); // 30 gün
        
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
        setcookie(
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
        
        error_log("Remember me cookie set for user ID: $user_id");
        
    } catch (PDOException $e) {
        error_log("Remember me cookie creation error: " . $e->getMessage());
    }
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

/**
 * Sayfa yüklendiğinde remember me kontrolü yapar
 * Giriş sayfası değilse ve kullanıcı giriş yapmamışsa cookie'yi kontrol eder
 */
function auto_login_check(): void {
    // Zaten giriş yapmışsa veya giriş sayfasındaysa kontrol etme
    if (is_user_logged_in() || strpos($_SERVER['REQUEST_URI'], 'auth.php') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'register.php') !== false) {
        return;
    }
    
    // Remember me cookie'sini kontrol et
    $user_data = check_remember_me_cookie();
    
    if ($user_data) {
        // Otomatik giriş yap
        create_secure_session($user_data);
        
        // Log the auto-login
        error_log("Auto-login successful for user: " . $user_data['username'] . " from IP: " . get_client_ip());
        
        // Sayfayı yenile (opsiyonel)
        header("Refresh: 0");
        exit;
    }
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
        'multiple_failed_logins' => count($_SESSION["failed_logins_$ip"] ?? []),
        'session_fingerprint_mismatch' => !validate_session_fingerprint(),
        'unusual_user_agent' => strlen($user_agent) < 10 || strlen($user_agent) > 500,
        'rapid_requests' => isset($_SESSION['last_request_time']) && 
                           (time() - $_SESSION['last_request_time']) < 1
    ];
    
    $suspicion_level = array_sum($suspicious_indicators);
    
    if ($suspicion_level >= 2) {
        $log_details = json_encode(array_merge($details, $suspicious_indicators));
        error_log("SUSPICIOUS ACTIVITY: $activity, User: $user_id, IP: $ip, Details: $log_details");
        
        // Yüksek risk durumunda session'ı sonlandır
        if ($suspicion_level >= 3 && is_user_logged_in()) {
            logout_user("Şüpheli aktivite tespit edildi. Güvenlik nedeniyle oturum sonlandırıldı.");
        }
    }
    
    $_SESSION['last_request_time'] = time();
}

/**
 * Login denemelerini loglar
 * @param string $username Kullanıcı adı
 * @param bool $success Başarılı mı?
 * @param string $reason Sebep
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

            // Status değişikliği kontrolü
            if ($session_status !== $current_db_status) {
                if ($current_db_status !== 'approved') {
                    logout_user("Hesap durumunuz değişti. Lütfen tekrar giriş yapın.");
                    return;
                } else {
                    // Approved duruma geçtiyse session'ı güncelle
                    $_SESSION['user_status'] = $current_db_status;
                }
            }

            // Roller değişmişse güncelle
            if ($session_roles_array !== $current_db_roles_array) {
                $_SESSION['user_roles'] = $current_db_roles_array;
                error_log("User roles updated for user ID: " . $_SESSION['user_id'] . 
                         " New roles: " . implode(',', $current_db_roles_array));
            }

        } catch (PDOException $e) {
            error_log("Session validation error: " . $e->getMessage());
            logout_user("Oturumunuz doğrulanırken bir veritabanı hatası oluştu.");
        }
    } elseif (is_user_logged_in()) { 
        logout_user("Oturum bilgilerinizde tutarsızlık tespit edildi.");
    }
}

/**
 * Kullanıcıyı güvenli şekilde çıkış yapar (Remember Me ile güncellenmiş)
 * @param string $message Çıkış mesajı
 */
function logout_user(string $message = "Güvenlik nedeniyle oturumunuz sonlandırıldı."): void {
    // Çıkış logla
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $ip = get_client_ip();
        error_log("User logout: ID=$user_id, IP=$ip, Reason: $message");
    }

    // Remember me cookie'sini temizle
    clear_remember_me_cookie();

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
 * Onaylı kullanıcı olmayı zorunlu kılar
 */
function require_approved_user(): void {
    require_login(); 
    if (!is_user_approved()) {
        $_SESSION['info_message'] = "Bu sayfayı görüntülemek için hesabınızın onaylanmış olması gerekmektedir.";
        header('Location: ' . get_auth_base_url() . '/register.php?status=approval_required');
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
function sanitize_output(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * SQL injection koruması için string'i temizler
 * @param string $input Temizlenecek string
 * @return string Temizlenmiş string
 */
function sanitize_input(string $input): string {
    return trim(strip_tags($input));
}

/**
 * Dosya upload güvenliği kontrolü
 * @param array $file $_FILES array elemanı
 * @param array $allowed_types İzin verilen MIME tipleri
 * @param int $max_size Maksimum dosya boyutu (byte)
 * @return array Sonuç dizisi [valid, message]
 */
function validate_file_upload(array $file, array $allowed_types = [], int $max_size = 5242880): array {
    // Dosya yüklendi mi kontrolü
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
            'valid' => false, 
            'message' => $error_messages[$file['error']] ?? 'Bilinmeyen dosya hatası'
        ];
    }
    
    // Boyut kontrolü
    if ($file['size'] > $max_size) {
        return [
            'valid' => false, 
            'message' => 'Dosya boyutu çok büyük (Max: ' . number_format($max_size / 1024 / 1024, 1) . ' MB)'
        ];
    }
    
    // MIME type kontrolü
    if (!empty($allowed_types)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return [
                'valid' => false, 
                'message' => 'Dosya tipi desteklenmiyor. İzin verilen tipler: ' . implode(', ', $allowed_types)
            ];
        }
    }
    
    return ['valid' => true, 'message' => 'Dosya geçerli'];
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

?>