<?php
// src/actions/handle_register.php - GÜVENLİK İYİLEŞTİRMELERİ

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // audit_log fonksiyonu için
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

$baseUrl = get_auth_base_url();

// Varsayılan avatar ayarı
define('DEFAULT_AVATAR', '../assets/logo.png');

// 1. GÜVENLİK: Honeypot kontrolü (bot koruması)
if (!empty($_POST['honeypot'])) {
    // Bot tespit edildi, sessizce yönlendir
    header('Location: ' . $baseUrl . '/register.php');
    exit;
}

// 2. GÜVENLİK: Rate limiting kontrolü
if (!check_rate_limit('register', 3, 300)) { // 5 dakikada 3 kayıt denemesi
    $_SESSION['error_message'] = "Çok fazla kayıt denemesi yapıyorsunuz. Lütfen 5 dakika bekleyin.";
    if (function_exists('audit_log')) {
        audit_log($pdo, null, 'rate_limit_exceeded', 'registration', null, null, [
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    header('Location: ' . $baseUrl . '/register.php');
    exit;
}

// 3. GÜVENLİK: CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        if (function_exists('audit_log')) {
            audit_log($pdo, null, 'csrf_token_invalid', 'registration', null, null, [
                'ip_address' => get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
        header('Location: ' . $baseUrl . '/register.php');
        exit;
    }
}

// 4. GÜVENLİK: Şüpheli aktivite tespiti (basit versiyon)
if (function_exists('detect_suspicious_activity')) {
    detect_suspicious_activity('registration_attempt', [
        'ip' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 5. GÜVENLİK: Input sanitization ve validation
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $ingame_name = trim(filter_input(INPUT_POST, 'ingame_name', FILTER_SANITIZE_STRING));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 6. GÜVENLİK: Güçlü validasyon
    $errors = [];

    // Boş alan kontrolü
    if (empty($username)) $errors[] = "Kullanıcı adı gereklidir.";
    if (empty($email)) $errors[] = "E-posta adresi gereklidir.";
    if (empty($ingame_name)) $errors[] = "Oyun içi isim gereklidir.";
    if (empty($password)) $errors[] = "Şifre gereklidir.";
    if (empty($confirm_password)) $errors[] = "Şifre tekrarı gereklidir.";

    // Kullanıcı adı güvenlik kontrolü
    if (!empty($username)) {
        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Kullanıcı adı 3-50 karakter arasında olmalıdır.";
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Kullanıcı adı sadece harf, rakam ve alt çizgi içerebilir.";
        }
        // Reserved usernames kontrolü
        $reserved_usernames = ['admin', 'root', 'administrator', 'moderator', 'test', 'user', 'guest', 'api', 'system'];
        if (in_array(strtolower($username), $reserved_usernames)) {
            $errors[] = "Bu kullanıcı adı kullanılamaz.";
        }
    }

    // E-posta güvenlik kontrolü
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Geçerli bir e-posta adresi girin.";
        }
        // Tehlikeli domain kontrolü
        $dangerous_domains = ['10minutemail.com', 'mailinator.com', 'guerrillamail.com'];
        $email_domain = strtolower(substr(strrchr($email, "@"), 1));
        if (in_array($email_domain, $dangerous_domains)) {
            $errors[] = "Geçici e-posta adresleri kabul edilmez.";
        }
    }

    // 7. GÜVENLİK: Güçlü şifre politikası
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Şifre en az 8 karakter olmalıdır.";
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            $errors[] = "Şifre en az bir büyük harf, bir küçük harf ve bir rakam içermelidir.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Şifreler uyuşmuyor.";
        }
        // Yaygın şifreler kontrolü
        $common_passwords = ['password', '123456', 'qwerty', 'abc123', 'password123'];
        if (in_array(strtolower($password), $common_passwords)) {
            $errors[] = "Lütfen daha güvenli bir şifre seçin.";
        }
    }

    // Oyun içi isim kontrolü
    if (!empty($ingame_name)) {
        if (strlen($ingame_name) < 2 || strlen($ingame_name) > 100) {
            $errors[] = "Oyun içi isim 2-100 karakter arasında olmalıdır.";
        }
    }

    // Hata varsa geri dön
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        $_SESSION['form_input'] = [
            'username' => $username,
            'email' => $email,
            'ingame_name' => $ingame_name
        ];
        audit_log($pdo, null, 'registration_validation_failed', 'registration', null, null, [
            'username' => $username,
            'email' => $email,
            'errors' => $errors,
            'ip_address' => get_client_ip()
        ]);
        header('Location: ' . $baseUrl . '/register.php');
        exit;
    }

    try {
        // 8. GÜVENLİK: Benzersizlik kontrolü (güvenli sorgu)
        $uniqueness_query = "SELECT id, username, email FROM users WHERE username = :username OR email = :email";
        $stmt = $pdo->prepare($uniqueness_query);
        $stmt->execute([
            ':username' => $username,
            ':email' => $email
        ]);
        
        $existing_user = $stmt->fetch();
        if ($existing_user) {
            $conflict_type = ($existing_user['username'] === $username) ? 'username' : 'email';
            $_SESSION['error_message'] = "Bu " . ($conflict_type === 'username' ? 'kullanıcı adı' : 'e-posta adresi') . " zaten kullanılıyor.";
            $_SESSION['form_input'] = [
                'username' => $username,
                'email' => $email,
                'ingame_name' => $ingame_name
            ];
            audit_log($pdo, null, 'registration_conflict', 'registration', null, null, [
                'conflict_type' => $conflict_type,
                'attempted_username' => $username,
                'attempted_email' => $email,
                'ip_address' => get_client_ip()
            ]);
            header('Location: ' . $baseUrl . '/register.php');
            exit;
        }

        // 9. GÜVENLİK: Güvenli şifre hashleme (cost parametresi ile)
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);

        // 10. GÜVENLİK: Varsayılan rol kontrolü (dinamik ve güvenli)
        $default_role_query = "SELECT id FROM roles WHERE name = :role_name AND id > 1 LIMIT 1";
        $stmt_role = $pdo->prepare($default_role_query);
        $stmt_role->execute([':role_name' => 'dis_uye']);
        $default_role_id = $stmt_role->fetchColumn();
        
        if (!$default_role_id) {
            // Fallback: En düşük yetkili rolü bul (admin hariç)
            $fallback_role_query = "SELECT id FROM roles WHERE priority = (SELECT MAX(priority) FROM roles) LIMIT 1";
            $stmt_fallback = $pdo->prepare($fallback_role_query);
            $stmt_fallback->execute();
            $default_role_id = $stmt_fallback->fetchColumn() ?: 5; // Son çare: ID 5
        }

        // 11. GÜVENLİK: Transaction ile atomik işlem
        $pdo->beginTransaction();
        
        try {
            // Kullanıcıyı ekle
            $user_insert_query = "
                INSERT INTO users (username, email, password, ingame_name, avatar_path, status, created_at) 
                VALUES (:username, :email, :password, :ingame_name, :avatar_path, 'pending', NOW())
            ";
            $stmt_user = $pdo->prepare($user_insert_query);
            $stmt_user->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashed_password,
                ':ingame_name' => $ingame_name,
                ':avatar_path' => DEFAULT_AVATAR
            ]);
            
            $user_id = $pdo->lastInsertId();

            // Rol ata
            $role_insert_query = "INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)";
            $stmt_role = $pdo->prepare($role_insert_query);
            $stmt_role->execute([
                ':user_id' => $user_id,
                ':role_id' => $default_role_id
            ]);

            // Transaction commit
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // 12. GÜVENLİK: Başarı mesajı (bilgi sızıntısı önleme)
        $_SESSION['success_message'] = "Kayıt başarılı! Hesabınız admin onayı bekliyor.";
        
        // 13. GÜVENLİK: Kapsamlı audit log
        if (function_exists('audit_log')) {
            audit_log($pdo, null, 'user_registered_successfully', 'user', $user_id, null, [
                'username' => $username,
                'email' => $email,
                'ingame_name' => $ingame_name,
                'assigned_role_id' => $default_role_id,
                'default_avatar' => DEFAULT_AVATAR,
                'ip_address' => get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'registration_source' => 'web_form'
            ]);
        }

        // 14. GÜVENLİK: Session regeneration (session fixation önleme)
        session_regenerate_id(true);

        // Başarı sayfasına yönlendir
        header('Location: ' . $baseUrl . '/register.php?status=pending_approval');
        exit;

    } catch (SecurityException $e) {
        error_log("Güvenlik hatası - Kayıt: " . $e->getMessage());
        $_SESSION['error_message'] = "Güvenlik ihlali tespit edildi. İşlem engellendi.";
        audit_log($pdo, null, 'registration_security_violation', 'registration', null, null, [
            'error' => $e->getMessage(),
            'ip_address' => get_client_ip()
        ]);
        header('Location: ' . $baseUrl . '/register.php');
        exit;
        
    } catch (DatabaseException $e) {
        error_log("Veritabanı hatası - Kayıt: " . $e->getMessage());
        $_SESSION['error_message'] = "Sistemsel bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        header('Location: ' . $baseUrl . '/register.php');
        exit;
        
    } catch (PDOException $e) {
        error_log("Kayıt hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Kayıt işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.";
        $_SESSION['form_input'] = [
            'username' => $username,
            'email' => $email,
            'ingame_name' => $ingame_name
        ];
        header('Location: ' . $baseUrl . '/register.php');
        exit;
    }

} else {
    // GET isteği - register sayfasına yönlendir
    header('Location: ' . $baseUrl . '/register.php');
    exit;
}
?>