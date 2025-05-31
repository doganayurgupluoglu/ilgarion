<?php
// src/actions/handle_edit_profile.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php'; // Auth fonksiyonları
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

require_approved_user(); // Sadece onaylanmış kullanıcılar profillerini düzenleyebilir
// Giriş yapmış kullanıcının kendi profilini düzenleme yetkisi olmalı
if (isset($pdo)) { // $pdo'nun tanımlı olduğundan emin olalım
    require_permission($pdo, 'profile.edit_own');
} else {
    // $pdo tanımlı değilse kritik bir hata var demektir, işlemi durdur.
    // Bu durum normalde config.php doğru include edildiyse oluşmaz.
    $_SESSION['error_message'] = "Veritabanı bağlantısı yapılandırılamadı.";
    error_log("handle_edit_profile.php: PDO nesnesi bulunamadı.");
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
}


$baseUrl = get_auth_base_url(); // Yönlendirmeler için /public
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ingame_name = trim($_POST['ingame_name']);
    $discord_username = isset($_POST['discord_username']) ? trim($_POST['discord_username']) : null;
    $profile_info = isset($_POST['profile_info']) ? trim($_POST['profile_info']) : null;
    $avatar_file = $_FILES['avatar'] ?? null; // Null coalescing operatörü eklendi
    $delete_current_avatar = isset($_POST['delete_current_avatar']) && $_POST['delete_current_avatar'] == '1'; // Avatar silme checkbox'ı için

    $update_fields = [];
    $params = [];

    if (empty($ingame_name)) {
        $_SESSION['error_message'] = "Oyun içi isim boş bırakılamaz.";
        header('Location: ' . $baseUrl . '/edit_profile.php');
        exit;
    }
    $update_fields[] = "ingame_name = :ingame_name";
    $params[':ingame_name'] = $ingame_name;

    $update_fields[] = "discord_username = :discord_username";
    $params[':discord_username'] = !empty($discord_username) ? $discord_username : null;

    $update_fields[] = "profile_info = :profile_info";
    $params[':profile_info'] = !empty($profile_info) ? $profile_info : null;

    $new_avatar_path_for_db = null;
    $session_avatar_updated = false;

    // Önce mevcut avatarı alalım (silme işlemi için)
    $stmt_old_avatar_check = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt_old_avatar_check->execute([$user_id]);
    $old_avatar_db_path = $stmt_old_avatar_check->fetchColumn();

    // Eğer "Mevcut avatarı sil" seçiliyse ve yeni bir avatar yüklenmiyorsa
    if ($delete_current_avatar && !($avatar_file && $avatar_file['error'] === UPLOAD_ERR_OK)) {
        if ($old_avatar_db_path && file_exists(BASE_PATH . '/public/' . $old_avatar_db_path)) {
            unlink(BASE_PATH . '/public/' . $old_avatar_db_path);
        }
        $new_avatar_path_for_db = null; // Veritabanında null olarak ayarlanacak
        $update_fields[] = "avatar_path = :avatar_path"; // SQL'e ekle
        $params[':avatar_path'] = null;
        unset($_SESSION['user_avatar_path']); // Session'dan kaldır
        $session_avatar_updated = true;
    }
    // Yeni avatar yükleme işlemi
    elseif ($avatar_file && $avatar_file['error'] === UPLOAD_ERR_OK) {
        $upload_dir_base = BASE_PATH . '/public/uploads/avatars/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 20 * 1024 * 1024; // 20MB

        if (!is_dir($upload_dir_base) || !is_writable($upload_dir_base)) {
             $_SESSION['error_message'] = "Sunucu yapılandırma hatası: Avatar yükleme klasörü ($upload_dir_base) mevcut değil veya yazılabilir değil.";
             error_log("Avatar yükleme klasörü hatası: " . $upload_dir_base);
             header('Location: ' . $baseUrl . '/edit_profile.php');
             exit;
        }

        $file_tmp_name = $avatar_file['tmp_name'];
        $file_name_original = $avatar_file['name'];
        $file_size = $avatar_file['size'];
        
        if (!file_exists($file_tmp_name) || !is_uploaded_file($file_tmp_name)) {
             $_SESSION['error_message'] = "Geçici avatar dosyası bulunamadı veya geçersiz ($file_name_original).";
             header('Location: ' . $baseUrl . '/edit_profile.php');
             exit;
        }
        $file_type = mime_content_type($file_tmp_name);

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error_message'] = "Geçersiz dosya tipi ($file_name_original). Sadece JPG, PNG veya GIF yükleyebilirsiniz.";
            header('Location: ' . $baseUrl . '/edit_profile.php');
            exit;
        }
        if ($file_size > $max_size) {
            $_SESSION['error_message'] = "Dosya boyutu çok büyük ($file_name_original). Maksimum " . ($max_size / 1024 / 1024) . "MB.";
            header('Location: ' . $baseUrl . '/edit_profile.php');
            exit;
        }

        // Yeni avatar yükleniyorsa, eski avatarı (varsa) sunucudan sil
        if ($old_avatar_db_path && file_exists(BASE_PATH . '/public/' . $old_avatar_db_path)) {
            unlink(BASE_PATH . '/public/' . $old_avatar_db_path);
        }

        $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
        $new_filename = 'avatar_user' . $user_id . '_' . time() . '.' . $file_extension;
        $destination = $upload_dir_base . $new_filename;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $new_avatar_path_for_db = 'uploads/avatars/' . $new_filename; 
            $update_fields[] = "avatar_path = :avatar_path";
            $params[':avatar_path'] = $new_avatar_path_for_db;
            $_SESSION['user_avatar_path'] = $new_avatar_path_for_db; // === SESSION GÜNCELLEME ===
            $session_avatar_updated = true;
        } else {
            $_SESSION['error_message'] = "Avatar yüklenirken bir hata oluştu ($file_name_original).";
            error_log("move_uploaded_file avatar için başarısız: " . $file_name_original . " Hedef: " . $destination . " PHP Hata: " . print_r(error_get_last(), true));
            header('Location: ' . $baseUrl . '/edit_profile.php');
            exit;
        }
    } elseif ($avatar_file && $avatar_file['error'] !== UPLOAD_ERR_NO_FILE && $avatar_file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "Avatar yüklenirken bir sorun oluştu. Hata kodu: " . $avatar_file['error'];
        header('Location: ' . $baseUrl . '/edit_profile.php');
        exit;
    }

    // Veritabanını güncelle
    if (!empty($update_fields)) {
        $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = :user_id";
        $params[':user_id'] = $user_id;

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['success_message'] = "Profil başarıyla güncellendi.";
                // Session avatarı yukarıda zaten güncellendi veya kaldırıldı
                header('Location: ' . $baseUrl . '/profile.php');
                exit;
            } else {
                $_SESSION['error_message'] = "Profil güncellenirken bir veritabanı hatası oluştu (execute false).";
                error_log("Profil güncelleme execute false. SQL: $sql Params: " . print_r($params, true) . " ErrorInfo: " . print_r($stmt->errorInfo(), true));
            }
        } catch (PDOException $e) {
            error_log("Profil güncelleme veritabanı hatası: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            $_SESSION['error_message'] = "Profil güncellenirken kritik bir veritabanı hatası oluştu.";
        }
    } elseif ($session_avatar_updated) { // Eğer sadece avatar silindi ve başka bir alan güncellenmediyse
        $_SESSION['success_message'] = "Profil (avatar) başarıyla güncellendi.";
        header('Location: ' . $baseUrl . '/profile.php');
        exit;
    } else {
        $_SESSION['info_message'] = "Güncellenecek bir değişiklik yapılmadı.";
        header('Location: ' . $baseUrl . '/edit_profile.php');
        exit;
    }

    header('Location: ' . $baseUrl . '/edit_profile.php');
    exit;

} else {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}
?>
