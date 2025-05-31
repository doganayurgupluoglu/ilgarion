<?php
// src/actions/handle_login.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH

$baseUrl = '/public';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($usernameOrEmail) || empty($password)) {
        $_SESSION['error_message'] = "Kullanıcı adı/e-posta ve şifre boş bırakılamaz.";
        header('Location: ' . $baseUrl . '/login.php');
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
                    // $_SESSION['user_role'] artık kullanılmayacak, yerine $_SESSION['user_roles'] dizisi gelecek
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
                    $roles_data = $stmt_roles->fetchAll(PDO::FETCH_COLUMN); // Sadece rol isimlerini al
                    
                    $_SESSION['user_roles'] = $roles_data; // Kullanıcının rollerini bir dizi olarak session'a ata
                    // Örneğin: $_SESSION['user_roles'] = ['admin', 'scg_uye']; veya ['dis_uye'];

                    // Eski $_SESSION['user_role'] değişkenini (eğer hala kullanılıyorsa diye)
                    // geçici olarak ilk role veya admin ise 'admin'e ayarlayabiliriz,
                    // ama ideal olan tüm rol kontrollerini $_SESSION['user_roles'] üzerinden yapmaktır.
                    // Şimdilik, 'admin' rolü varsa onu önceliklendirelim, yoksa ilk rolü alalım (veya 'member' gibi bir varsayılan).
                    if (in_array('admin', $roles_data)) {
                        $_SESSION['user_role_legacy_compatibility'] = 'admin'; // Geçici uyumluluk için
                    } elseif (!empty($roles_data)) {
                        $_SESSION['user_role_legacy_compatibility'] = $roles_data[0]; // İlk rolü al
                    } else {
                        $_SESSION['user_role_legacy_compatibility'] = 'member'; // Varsayılan (rolü yoksa)
                    }
                    // === ROLLERİ EKLEME SONU ===

                    header('Location: ' . $baseUrl . '/index.php');
                    exit;
                } elseif ($user['status'] === 'pending') {
                    $_SESSION['error_message'] = "Hesabınız henüz admin tarafından onaylanmamış.";
                    header('Location: ' . $baseUrl . '/login.php?status=pending_approval');
                    exit;
                } elseif ($user['status'] === 'rejected' || $user['status'] === 'suspended') {
                    $_SESSION['error_message'] = "Hesabınız reddedilmiş veya askıya alınmıştır. Giriş yapamazsınız.";
                    header('Location: ' . $baseUrl . '/login.php');
                    exit;
                }
            } else {
                $_SESSION['error_message'] = "Kullanıcı adı veya şifre hatalı.";
                header('Location: ' . $baseUrl . '/login.php');
                exit;
            }
        } else {
            $_SESSION['error_message'] = "Kullanıcı adı veya şifre hatalı.";
            header('Location: ' . $baseUrl . '/login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Giriş Veritabanı Hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Giriş sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
        header('Location: ' . $baseUrl . '/login.php');
        exit;
    }
} else {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}
?>