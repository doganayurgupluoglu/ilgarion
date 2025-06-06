<?php
// src/actions/handle_login.php - Register.php yönlendirme ile güncellenmiş

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';

$baseUrl = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($usernameOrEmail) || empty($password)) {
        $_SESSION['error_message'] = "Kullanıcı adı/e-posta ve şifre boş bırakılamaz.";
        header('Location: ' . $baseUrl . '/register.php?mode=login');
        exit;
    }

    try {
        // Kullanıcıyı çekerken avatar_path'i de seçiyoruz
        $stmt = $pdo->prepare("SELECT id, username, email, password, status, avatar_path FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'approved') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_status'] = $user['status'];

                    // AVATAR YOLUNU SESSION'A EKLEME
                    if (!empty($user['avatar_path'])) {
                        $_SESSION['user_avatar_path'] = $user['avatar_path'];
                    } else {
                        unset($_SESSION['user_avatar_path']);
                    }

                    // === KULLANICININ ROLLERİNİ ÇEKME VE SESSION'A EKLEME ===
                    $stmt_roles = $pdo->prepare("
                        SELECT r.name 
                        FROM roles r
                        JOIN user_roles ur ON r.id = ur.role_id
                        WHERE ur.user_id = ?
                    ");
                    $stmt_roles->execute([$user['id']]);
                    $roles_data = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
                    
                    $_SESSION['user_roles'] = $roles_data;

                    // Eski $_SESSION['user_role'] değişkenini geçici uyumluluk için
                    if (in_array('admin', $roles_data)) {
                        $_SESSION['user_role_legacy_compatibility'] = 'admin';
                    } elseif (!empty($roles_data)) {
                        $_SESSION['user_role_legacy_compatibility'] = $roles_data[0];
                    } else {
                        $_SESSION['user_role_legacy_compatibility'] = 'member';
                    }
                    // === ROLLERİ EKLEME SONU ===

                    header('Location: ' . $baseUrl . '/index.php');
                    exit;
                    
                } elseif ($user['status'] === 'pending') {
                    // DEĞIŞIKLIK: Register.php'e yönlendir ve özel status ile
                    $_SESSION['info_message'] = "Hesabınız henüz admin tarafından onaylanmamış. Onaylandıktan sonra giriş yapabilirsiniz.";
                    header('Location: ' . $baseUrl . '/register.php?mode=login&status=pending_approval');
                    exit;
                    
                } elseif ($user['status'] === 'rejected') {
                    // DEĞIŞIKLIK: Register.php'e yönlendir
                    $_SESSION['error_message'] = "Hesabınız reddedilmiştir. Giriş yapamazsınız. Yeni bir hesap oluşturabilir veya yönetici ile iletişime geçebilirsiniz.";
                    header('Location: ' . $baseUrl . '/register.php?mode=login&status=account_rejected');
                    exit;
                    
                } elseif ($user['status'] === 'suspended') {
                    // DEĞIŞIKLIK: Register.php'e yönlendir
                    $_SESSION['error_message'] = "Hesabınız askıya alınmıştır. Giriş yapamazsınız. Yönetici ile iletişime geçin.";
                    header('Location: ' . $baseUrl . '/register.php?mode=login&status=account_suspended');
                    exit;
                }
            } else {
                $_SESSION['error_message'] = "Kullanıcı adı veya şifre hatalı.";
                header('Location: ' . $baseUrl . '/register.php?mode=login');
                exit;
            }
        } else {
            $_SESSION['error_message'] = "Kullanıcı adı veya şifre hatalı.";
            header('Location: ' . $baseUrl . '/register.php?mode=login');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Giriş Veritabanı Hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Giriş sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
        header('Location: ' . $baseUrl . '/register.php?mode=login');
        exit;
    }
} else {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}
?>