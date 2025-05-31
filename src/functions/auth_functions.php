<?php
// src/functions/auth_functions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function get_auth_base_url() {
    return '/public';
}

function check_user_session_validity() {
    global $pdo; // Fonksiyon içinde $pdo'yu global olarak tanımla

    if (!isset($pdo)) {
        error_log("check_user_session_validity: PDO bağlantısı mevcut değil! Bu fonksiyon çağrılmadan önce database.php include edilmiş olmalı.");
        // Kritik hata, güvenli çıkış yap
        if (function_exists('logout_user')) {
            logout_user("Sistem hatası nedeniyle oturum sonlandırıldı. (DB Err)");
        } else {
            session_unset();
            session_destroy();
            // get_auth_base_url() burada çağrılabilir ama basit bir yola yönlendirelim
            header('Location: /public/login.php?status=session_error_db');
            exit;
        }
        return;
    }

    if (isset($_SESSION['user_id'])) {
        try {
            // Kullanıcının durumunu ve rollerini tek bir sorguyla çek
            $stmt = $pdo->prepare("
                SELECT u.status, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name ASC SEPARATOR ',') AS roles_list
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE u.id = :user_id
                GROUP BY u.id
            ");
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $userDataFromDb = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userDataFromDb === false) {
                // Kullanıcı veritabanında bulunamadı (belki silindi)
                logout_user("Hesabınız artık mevcut değil.");
                return; 
            }

            $current_db_status = $userDataFromDb['status'];
            $current_db_roles_str = $userDataFromDb['roles_list'];
            $current_db_roles_array = $current_db_roles_str ? explode(',', $current_db_roles_str) : [];
            sort($current_db_roles_array); // Karşılaştırma için sırala

            $session_status = $_SESSION['user_status'] ?? null;
            $session_roles_array = $_SESSION['user_roles'] ?? [];
            sort($session_roles_array); // Karşılaştırma için sırala

            // Durum kontrolü
            if ($current_db_status !== 'approved') {
                 logout_user("Hesap durumunuz '" . htmlspecialchars($current_db_status) . "' olarak değişti. Lütfen bir yönetici ile iletişime geçin.");
                 return;
            } elseif ($session_status !== $current_db_status) {
                // Veritabanındaki durum 'approved' ama session'daki farklıydı, session'ı güncelle.
                $_SESSION['user_status'] = $current_db_status;
            }

            // Rol kontrolü ve güncellemesi
            if ($current_db_roles_array !== $session_roles_array) {
                $_SESSION['user_roles'] = $current_db_roles_array;
                // İsteğe bağlı: Kullanıcıya rollerinin güncellendiğine dair bir bilgi mesajı gösterilebilir.
                // Şimdilik sadece session'ı güncelliyoruz, sayfa bazlı yetki kontrolleri geri kalanı halledecek.
                // Örneğin: $_SESSION['info_message'] = "Yetkileriniz güncellendi. Sayfayı yenileyin.";
            }

        } catch (PDOException $e) {
            error_log("Kullanıcı oturum geçerliliği (status/role) kontrol hatası: " . $e->getMessage());
            logout_user("Oturumunuz doğrulanırken bir veritabanı hatası oluştu.");
        }
    } elseif (is_user_logged_in()) { 
        // $_SESSION['user_id'] yok ama is_user_logged_in (eski bir session kontrolüne göre) true ise, bu tutarsızlık.
        logout_user("Oturum bilgilerinizde tutarsızlık tespit edildi.");
    }
}

function logout_user($message = "Güvenlik nedeniyle oturumunuz sonlandırıldı.") {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy(); // Hata basmasını engellemek için @
    
    session_start(); // Yeni session başlatıp mesajı oraya koy
    $_SESSION['info_message'] = $message; 
    header('Location: ' . get_auth_base_url() . '/login.php?status=logged_out');
    exit;
}

function is_user_logged_in() {
    // Sadece user_id'nin varlığına bakmak yeterli, check_user_session_validity daha detaylı kontrol yapacak.
    return isset($_SESSION['user_id']);
}

function is_user_approved() {
    // Bu fonksiyon çağrılmadan önce check_user_session_validity çağrılmış olmalı.
    return is_user_logged_in() && isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'approved';
}

function user_has_role(string $role_name): bool {
    // Bu fonksiyon çağrılmadan önce check_user_session_validity çağrılmış olmalı.
    if (!is_user_logged_in() || !isset($_SESSION['user_roles']) || !is_array($_SESSION['user_roles'])) {
        return false;
    }
    return in_array(trim($role_name), $_SESSION['user_roles']);
}

function user_has_any_role(array $role_names): bool {
    // Bu fonksiyon çağrılmadan önce check_user_session_validity çağrılmış olmalı.
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

function is_user_admin() {
    return user_has_role('admin');
}

function is_scg_uye() {
    return user_has_role('scg_uye');
}

function is_ilgarion_turanis() {
    return user_has_role('ilgarion_turanis');
}

function is_dis_uye() {
    return user_has_role('dis_uye');
}


function require_login() {
    if (!is_user_logged_in()) {
        $_SESSION['error_message'] = "Bu sayfayı görüntülemek için giriş yapmalısınız.";
        header('Location: ' . get_auth_base_url() . '/login.php?status=login_required');
        exit;
    }
    // check_user_session_validity() burada çağrılıyor.
    // Bu, $pdo'nun global scope'ta veya bu fonksiyon içinde erişilebilir olmasını gerektirir.
    // database.php'nin her sayfanın başında include edildiğini varsayıyoruz.
    check_user_session_validity(); 
}

function require_approved_user() {
    require_login(); 
    if (!is_user_approved()) { // is_user_approved güncel session'a göre çalışacak
         $_SESSION['info_message'] = "Bu sayfayı görüntülemek için hesabınızın onaylanmış olması gerekmektedir.";
         header('Location: ' . get_auth_base_url() . '/login.php?status=approval_required');
         exit;
    }
}

function require_admin() {
    require_login(); 
    if (!is_user_admin()) { 
        $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır (Admin Gerekli).";
        header('Location: ' . get_auth_base_url() . '/index.php?status=admin_required');
        exit;
    }
}

function require_role($required_role, ?string $redirect_url_on_fail = null) { 
    require_approved_user(); // Önce onaylı kullanıcı olmalı (bu da check_user_session_validity'yi çağırır)

    $has_required_role = false;
    if (is_array($required_role)) {
        $has_required_role = user_has_any_role($required_role); // Güncel session rollerini kullanır
    } else {
        $has_required_role = user_has_role((string)$required_role); // Güncel session rollerini kullanır
    }

    if (!$has_required_role) {
        $_SESSION['error_message'] = "Bu içeriğe erişmek için gerekli role sahip değilsiniz.";
        if ($redirect_url_on_fail === null) {
            $redirect_url_on_fail = get_auth_base_url() . '/index.php?status=role_required';
        }
        header('Location: ' . $redirect_url_on_fail);
        exit;
    }
}

?>