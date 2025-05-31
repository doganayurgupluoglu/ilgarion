<?php
// src/actions/handle_gallery_upload.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';

require_approved_user(); // Sadece onaylanmış kullanıcılar

$baseUrl = get_auth_base_url(); 
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $image_file = $_FILES['gallery_image'] ?? null;

    // Yeni görünürlük alanları
    $is_public_no_auth = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
    $is_members_only = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
    $assigned_role_ids = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                        ? array_map('intval', $_POST['assigned_role_ids'])
                        : [];

    // Form girdilerini session'a kaydet (hata durumunda geri doldurmak için)
    $_SESSION['form_input_gallery'] = $_POST;

    if (empty($description)) {
        $_SESSION['error_message'] = "Fotoğraf açıklaması boş bırakılamaz.";
        header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
        exit;
    }

    // Görünürlük mantığı
    if ($is_public_no_auth) {
        $is_members_only = false;
        $assigned_role_ids = [];
    } elseif (!empty($assigned_role_ids)) {
        $is_members_only = false; // Belirli roller seçiliyse, genel üye görünürlüğü olmamalı
    } elseif (!$is_members_only && empty($assigned_role_ids) && !$is_public_no_auth) {
        // Eğer hiçbiri seçili değilse, varsayılan olarak members_only yapabilir veya hata verebiliriz.
        // Şimdilik handle_guide.php'deki gibi hata verelim.
        $_SESSION['error_message'] = "Lütfen bir görünürlük ayarı seçin (Herkese Açık, Tüm Üyelere veya Belirli Roller).";
        header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
        exit;
    }


    if (isset($image_file) && $image_file['error'] === UPLOAD_ERR_OK) {
        $upload_dir_base = BASE_PATH . '/public/uploads/gallery/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 20 * 1024 * 1024; 

        if (!is_dir($upload_dir_base)) {
            if (!mkdir($upload_dir_base, 0775, true)) {
                $_SESSION['error_message'] = "Galeri yükleme klasörü oluşturulamadı.";
                error_log("Galeri yükleme klasörü oluşturulamadı: " . $upload_dir_base);
                header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
                exit;
            }
        }
         if (!is_writable($upload_dir_base)) {
            $_SESSION['error_message'] = "Sunucu yapılandırma hatası: Galeri yükleme klasörü yazılabilir değil.";
            error_log("Galeri yükleme klasörü yazılabilir değil: " . $upload_dir_base);
            header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
            exit;
        }

        $file_tmp_name = $image_file['tmp_name'];
        $file_name_original = $image_file['name'];
        $file_size = $image_file['size'];

        if (!file_exists($file_tmp_name) || !is_uploaded_file($file_tmp_name)) {
             $_SESSION['error_message'] = "Geçici dosya bulunamadı veya geçersiz ($file_name_original).";
             header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
             exit;
        }
        $file_type = mime_content_type($file_tmp_name);

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error_message'] = "Geçersiz dosya tipi ($file_name_original - Tip: $file_type). Sadece JPG, PNG, GIF.";
            header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
            exit;
        }
        if ($file_size > $max_size) {
            $_SESSION['error_message'] = "Dosya boyutu çok büyük ($file_name_original). Maksimum " . ($max_size / 1024 / 1024) . "MB.";
            header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
            exit;
        }

        $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
        $new_filename = 'gallery_user' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
        $destination = $upload_dir_base . $new_filename;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $db_image_path = 'uploads/gallery/' . $new_filename;

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "INSERT INTO gallery_photos (user_id, image_path, description, is_public_no_auth, is_members_only) 
                     VALUES (:user_id, :image_path, :description, :is_public_no_auth, :is_members_only)"
                );
                $params_insert = [
                    ':user_id' => $user_id,
                    ':image_path' => $db_image_path,
                    ':description' => $description,
                    ':is_public_no_auth' => $is_public_no_auth ? 1 : 0,
                    ':is_members_only' => $is_members_only ? 1 : 0
                ];

                if ($stmt->execute($params_insert)) {
                    $new_photo_id = $pdo->lastInsertId();

                    // Eğer belirli roller seçilmişse, gallery_photo_visibility_roles tablosuna ekle
                    if (!$is_public_no_auth && !$is_members_only && !empty($assigned_role_ids)) {
                        $stmt_role_insert = $pdo->prepare("INSERT INTO gallery_photo_visibility_roles (photo_id, role_id) VALUES (?, ?)");
                        foreach ($assigned_role_ids as $role_id) {
                            $stmt_role_insert->execute([$new_photo_id, $role_id]);
                        }
                    }
                    $pdo->commit();
                    unset($_SESSION['form_input_gallery']); // Başarılıysa form girdilerini temizle
                    $_SESSION['success_message'] = "Fotoğraf başarıyla galeriye yüklendi!";
                    header('Location: ' . $baseUrl . '/gallery.php');
                    exit;
                } else {
                    $pdo->rollBack();
                    $_SESSION['error_message'] = "Fotoğraf bilgileri veritabanına kaydedilirken bir hata oluştu.";
                    if (file_exists($destination)) unlink($destination);
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Galeri fotoğrafı DB kaydetme hatası: " . $e->getMessage());
                $_SESSION['error_message'] = "Veritabanı hatası oluştu: " . $e->getMessage();
                 if (file_exists($destination)) unlink($destination);
            }
        } else {
            $_SESSION['error_message'] = "Fotoğraf sunucuya yüklenirken bir hata oluştu.";
            error_log("move_uploaded_file galeride başarısız: " . $file_name_original . " Hedef: " . $destination . " PHP Hata: " . print_r(error_get_last(), true));
        }
    } elseif (isset($image_file) && $image_file['error'] !== UPLOAD_ERR_NO_FILE) {
        $_SESSION['error_message'] = "Fotoğraf yüklenirken bir sorun oluştu. Hata kodu: " . $image_file['error'];
    } else { // Dosya seçilmemişse
        $_SESSION['error_message'] = "Lütfen bir fotoğraf dosyası seçin.";
    }

    header('Location: ' . $baseUrl . '/gallery/upload.php'); // Yönlendirme yolu güncellendi
    exit;

} else {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}
?>
