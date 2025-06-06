<?php
// src/actions/handle_admin_gallery_actions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php'; // Auth fonksiyonları
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// $baseUrl'i yönlendirmeler için alalım
$baseUrl = null;
$admin_gallery_page = $baseUrl . '/admin/gallery.php';
// user_gallery.php'den gelindiyse, o sayfaya geri dönmek için bir parametre alabiliriz
$redirect_page = $_POST['redirect_to'] ?? $admin_gallery_page; // Varsayılan olarak admin galerisine dön

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    $_SESSION['error_message'] = "Geçersiz istek veya eksik işlem.";
    header('Location: ' . $redirect_page);
    exit;
}

// Önce giriş yapılmış mı kontrol et
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu işlem için giriş yapmalısınız.";
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_POST['action'];

switch ($action) {
    case 'delete_photo':
        // Yetki kontrolü: Admin olmalı veya 'gallery.delete_any' yetkisine sahip olmalı
        // Eğer sadece kendi fotoğrafını silebiliyorsa, bu action burada olmamalı, src/actions/handle_gallery.php'de olmalı.
        // Bu dosya admin action'ları için olduğu için, admin yetkisi varsayıyoruz.
        require_admin(); // Veya has_permission($pdo, 'gallery.delete_any')

        if (!isset($_POST['photo_id'], $_POST['image_path'])) {
            $_SESSION['error_message'] = "Silme işlemi için eksik parametreler.";
            header('Location: ' . $redirect_page);
            exit;
        }
        $photo_id = (int)$_POST['photo_id'];
        $image_path_relative = $_POST['image_path'];

        // Fotoğrafın varlığını kontrol et (sahibini kontrol etmeye gerek yok, admin siliyor)
        try {
            $stmt_check_exist = $pdo->prepare("SELECT id FROM gallery_photos WHERE id = ?");
            $stmt_check_exist->execute([$photo_id]);
            if (!$stmt_check_exist->fetch()) {
                $_SESSION['info_message'] = "Fotoğraf bulunamadı (belki zaten silinmişti).";
                header('Location: ' . $redirect_page);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Galeri silme - fotoğraf varlık kontrol hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Fotoğraf bilgileri alınırken bir sorun oluştu.";
            header('Location: ' . $redirect_page);
            exit;
        }

        // 1. Fotoğrafı sunucudan sil
        $full_image_path_to_delete = BASE_PATH . '/public/' . $image_path_relative;
        $file_deleted_on_server = false;
        if (strpos($image_path_relative, 'uploads/gallery/') === 0 && file_exists($full_image_path_to_delete)) {
            if (unlink($full_image_path_to_delete)) {
                $file_deleted_on_server = true;
            } else {
                error_log("Galeri: Dosya silinemedi - " . $full_image_path_to_delete);
            }
        } elseif (strpos($image_path_relative, 'uploads/gallery/') === 0) {
             error_log("Galeri: Silinecek dosya sunucuda bulunamadı - " . $full_image_path_to_delete);
             $file_deleted_on_server = true; // Dosya zaten yoksa, DB'den silmeye devam et
        }


        // 2. Fotoğrafı veritabanından sil
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM gallery_photos WHERE id = ?");
            if ($stmt_delete->execute([$photo_id])) {
                if ($stmt_delete->rowCount() > 0) {
                    $_SESSION['success_message'] = "Fotoğraf başarıyla galeriden silindi.";
                } else {
                     $_SESSION['info_message'] = "Fotoğraf veritabanında bulunamadı (belki zaten silinmişti).";
                }
            } else {
                $_SESSION['error_message'] = "Fotoğraf veritabanından silinirken bir hata oluştu.";
            }
        } catch (PDOException $e) {
            error_log("Galeri silme (DB) hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Fotoğraf silinirken bir veritabanı hatası oluştu.";
        }
        header('Location: ' . $redirect_page);
        exit;

    case 'edit_description':
        // Bu case'in de başında yetkilendirme kontrolü olmalı (admin veya fotoğraf sahibi)
        if (!isset($_POST['photo_id'])) { /* ... */ }
        $photo_id_edit = (int)$_POST['photo_id'];
        // ... (fotoğraf sahibini çekme kodu) ...
        // if (!is_user_admin() && $current_user_id != $photo_owner_id_for_edit) { /* yetki yok */ }

        // ... (mevcut açıklama düzenleme kodunuz) ...
        // Bu case'i şimdilik kaldırıyoruz, update_photo_details daha kapsamlı olacak.
        // $_SESSION['info_message'] = "Açıklama düzenleme özelliği henüz tam olarak aktif değil."; // Placeholder
        // header('Location: ' . $redirect_page);
        // exit;
        // break; // edit_description için break

    case 'update_photo_details':
        require_admin(); // Sadece adminler fotoğraf detaylarını güncelleyebilir.
                         // Veya has_permission($pdo, 'gallery.manage_all') gibi bir yetki.

        if (!isset($_POST['photo_id']) || !is_numeric($_POST['photo_id'])) {
            $_SESSION['error_message'] = "Düzenlenecek fotoğraf ID'si geçersiz.";
            header('Location: ' . $admin_gallery_page); // Genel admin galeri sayfasına yönlendir
            exit;
        }
        $photo_id_to_update = (int)$_POST['photo_id'];
        $description = trim($_POST['description'] ?? '');

        if (empty($description)) {
            $_SESSION['error_message'] = "Fotoğraf açıklaması boş bırakılamaz.";
            $_SESSION['form_input_gallery_edit'] = $_POST; // Form girdilerini session'a kaydet
            header('Location: ' . $baseUrl . '/admin/edit_gallery_photo.php?photo_id=' . $photo_id_to_update);
            exit;
        }

        $is_public_no_auth = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
        $is_members_only = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
        $assigned_role_ids = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                            ? array_map('intval', $_POST['assigned_role_ids'])
                            : [];
        
        // Görünürlük mantığı
        if ($is_public_no_auth) {
            $is_members_only = false;
            $assigned_role_ids = [];
        } elseif (!empty($assigned_role_ids)) {
            $is_members_only = false;
        } elseif (!$is_members_only && empty($assigned_role_ids) && !$is_public_no_auth) {
            $_SESSION['error_message'] = "Lütfen bir görünürlük ayarı seçin (Herkese Açık, Tüm Üyelere veya Belirli Roller).";
            $_SESSION['form_input_gallery_edit'] = $_POST;
            header('Location: ' . $baseUrl . '/admin/edit_gallery_photo.php?photo_id=' . $photo_id_to_update);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // gallery_photos tablosunu güncelle
            $stmt_update_photo = $pdo->prepare(
                "UPDATE gallery_photos 
                 SET description = :description, 
                     is_public_no_auth = :is_public_no_auth, 
                     is_members_only = :is_members_only
                 WHERE id = :photo_id"
            );
            $params_update = [
                ':description' => $description,
                ':is_public_no_auth' => $is_public_no_auth ? 1 : 0,
                ':is_members_only' => $is_members_only ? 1 : 0,
                ':photo_id' => $photo_id_to_update
            ];
            $stmt_update_photo->execute($params_update);

            // gallery_photo_visibility_roles tablosunu güncelle
            // Önce mevcut rolleri sil
            $stmt_delete_roles = $pdo->prepare("DELETE FROM gallery_photo_visibility_roles WHERE photo_id = ?");
            $stmt_delete_roles->execute([$photo_id_to_update]);

            // Sonra yeni rolleri ekle (eğer varsa ve genel görünürlük değilse)
            if (!$is_public_no_auth && !$is_members_only && !empty($assigned_role_ids)) {
                $stmt_role_insert = $pdo->prepare("INSERT INTO gallery_photo_visibility_roles (photo_id, role_id) VALUES (?, ?)");
                foreach ($assigned_role_ids as $role_id) {
                    $stmt_role_insert->execute([$photo_id_to_update, $role_id]);
                }
            }

            $pdo->commit();
            unset($_SESSION['form_input_gallery_edit']);
            $_SESSION['success_message'] = "Fotoğraf detayları başarıyla güncellendi.";
            header('Location: ' . $admin_gallery_page . '?highlight_photo=' . $photo_id_to_update); // Admin galeriye dön, güncellenen fotoğrafı vurgula
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Admin galeri fotoğraf güncelleme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Fotoğraf güncellenirken bir veritabanı hatası oluştu.";
            $_SESSION['form_input_gallery_edit'] = $_POST;
            header('Location: ' . $baseUrl . '/admin/edit_gallery_photo.php?photo_id=' . $photo_id_to_update);
            exit;
        }
        break; // update_photo_details için break

    default:
        $_SESSION['error_message'] = "Geçersiz galeri işlem türü.";
        header('Location: ' . $redirect_page);
        exit;
}
?>
