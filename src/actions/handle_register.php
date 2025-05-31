<?php
// src/actions/handle_register.php

// Session'ı başlat (mesajlar için ve ileride giriş durumu için)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı bağlantısını ve BASE_PATH'i yükle
require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH burada
// auth_functions.php dosyasını dahil et (get_auth_base_url() fonksiyonu burada)
require_once BASE_PATH . '/src/functions/auth_functions.php'; // EKLENDİ

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $ingame_name = trim($_POST['ingame_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Hata mesajları için bir dizi
    $errors = [];

    // Temel Doğrulamalar
    if (empty($username)) {
        $errors[] = "Kullanıcı adı boş bırakılamaz.";
    }
    if (empty($email)) {
        $errors[] = "E-posta adresi boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir e-posta adresi giriniz.";
    }
    if (empty($ingame_name)) {
        $errors[] = "Oyun içi isim boş bırakılamaz.";
    }
    if (empty($password)) {
        $errors[] = "Şifre boş bırakılamaz.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Şifreler uyuşmuyor.";
    }
    if (strlen($password) < 6 && !empty($password)) {
        $errors[] = "Şifre en az 6 karakter olmalıdır.";
    }

    // Veritabanında kullanıcı adı veya e-posta kontrolü
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            if ($stmt->fetch()) {
                $errors[] = "Bu kullanıcı adı zaten alınmış.";
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            if ($stmt->fetch()) {
                $errors[] = "Bu e-posta adresi zaten kayıtlı.";
            }
        } catch (PDOException $e) {
            error_log("Veritabanı hatası (kullanıcı kontrolü): " . $e->getMessage());
            $errors[] = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }

    // Hata yoksa kullanıcıyı kaydet
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction(); // İşlemi başlat

            $stmt_user = $pdo->prepare("INSERT INTO users (username, email, password, ingame_name, status) VALUES (:username, :email, :password, :ingame_name, 'pending')");
            $stmt_user->bindParam(':username', $username);
            $stmt_user->bindParam(':email', $email);
            $stmt_user->bindParam(':password', $hashed_password);
            $stmt_user->bindParam(':ingame_name', $ingame_name);
            $stmt_user->execute();
            $new_user_id = $pdo->lastInsertId();

            $default_role_id = 3; // "Dış üye" rolünün ID'si (roles tablonuzdaki ID ile eşleşmeli)

            $stmt_user_role = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            $stmt_user_role->bindParam(':user_id', $new_user_id, PDO::PARAM_INT);
            $stmt_user_role->bindParam(':role_id', $default_role_id, PDO::PARAM_INT);
            $stmt_user_role->execute();

            $pdo->commit(); // Her şey yolundaysa işlemi onayla

            $_SESSION['success_message'] = "Kaydınız başarıyla oluşturuldu! Hesabınız admin onayı bekliyor. Onaylandıktan sonra giriş yapabilirsiniz.";
            
            // get_auth_base_url() artık tanımlı olmalı
            $redirect_url = get_auth_base_url() . '/login.php?status=pending_approval';
            header('Location: ' . $redirect_url);
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // Hata olursa işlemi geri al
            }
            error_log("Kayıt sırasında veritabanı hatası: " . $e->getMessage());
            $errors[] = "Kayıt sırasında kritik bir veritabanı hatası oluştu.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        $_SESSION['form_input']['username'] = $username;
        $_SESSION['form_input']['email'] = $email;
        $_SESSION['form_input']['ingame_name'] = $ingame_name;
        
        // get_auth_base_url() artık tanımlı olmalı
        $redirect_url = get_auth_base_url() . '/register.php';
        header('Location: ' . $redirect_url);
        exit;
    }

} else {
    // get_auth_base_url() artık tanımlı olmalı
    $redirect_url = get_auth_base_url() . '/index.php';
    header('Location: ' . $redirect_url);
    exit;
}
?>
