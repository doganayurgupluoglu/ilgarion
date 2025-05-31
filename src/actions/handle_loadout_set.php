<?php
// src/actions/handle_loadout_set.php
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// Hata gösterimini geliştirme için aç (canlıda kapat veya logla)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
// require_once BASE_PATH . '/src/functions/guide_functions.php'; // generate_slug gibi fonksiyonlar gerekirse

require_admin(); // Bu işlemleri sadece adminler yapabilir

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];
$default_redirect_page = $baseUrl . '/admin/manage_loadout_sets.php'; // Varsayılan dönüş sayfası

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $redirect_page = $default_redirect_page; // Başlangıçta varsayılanı ata

    if ($action === 'create_set') {
        if (!isset($_POST['set_name']) || empty(trim($_POST['set_name']))) {
            $_SESSION['error_message'] = "Teçhizat seti adı boş bırakılamaz.";
            // Form inputlarını session'a kaydet (new_loadout_set.php'de kullanılacak)
            $_SESSION['form_input_loadout'] = $_POST;
            header('Location: ' . $baseUrl . '/admin/new_loadout_set.php'); // Yeni set oluşturma sayfasına geri dön
            exit;
        }
        $set_name = trim($_POST['set_name']);
        $set_description = trim($_POST['set_description'] ?? null);
        $status = $_POST['status'] ?? 'draft';
        $visibility = $_POST['visibility'] ?? 'private'; // Admin oluşturuyorsa belki 'members_only' daha mantıklı? Şimdilik formdan geleni alalım.
        $assigned_role_ids = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                            ? array_map('intval', $_POST['assigned_role_ids'])
                            : [];

        // Görünürlük mantığı
        if ($visibility !== 'faction_only') {
            $assigned_role_ids = []; // Sadece faction_only durumunda roller geçerli
        }
        // 'private', 'public', 'members_only' durumlarında assigned_role_ids boş olmalı.

        $set_image_file = $_FILES['set_image'] ?? null;
        $set_image_db_path = null;

        if ($set_image_file && $set_image_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir_base = BASE_PATH . '/public/uploads/loadout_set_images/';
            if (!is_dir($upload_dir_base)) {
                if (!mkdir($upload_dir_base, 0775, true)) {
                    $_SESSION['error_message'] = "Yükleme klasörü oluşturulamadı: " . $upload_dir_base;
                    $_SESSION['form_input_loadout'] = $_POST;
                    header('Location: ' . $baseUrl . '/admin/new_loadout_set.php'); exit;
                }
            }
            if (!is_writable($upload_dir_base)) {
                $_SESSION['error_message'] = "Sunucu yapılandırma hatası: Set görseli yükleme klasörü yazılabilir değil.";
                error_log("Yükleme klasörü yazılabilir değil (loadout_set_images): " . $upload_dir_base);
                $_SESSION['form_input_loadout'] = $_POST;
                header('Location: ' . $baseUrl . '/admin/new_loadout_set.php'); exit;
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 2MB
            
            $file_tmp_name = $set_image_file['tmp_name'];
            $file_name_original = $set_image_file['name'];
            $file_size = $set_image_file['size'];
            $file_type = mime_content_type($file_tmp_name);

            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
                $new_filename = 'set_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir_base . $new_filename;
                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $set_image_db_path = 'uploads/loadout_set_images/' . $new_filename;
                } else {
                    $_SESSION['error_message'] = "Set görseli yüklenirken bir hata oluştu (dosya taşınamadı).";
                    error_log("Set görseli move_uploaded_file hatası: " . $file_name_original . " Hedef: " . $destination);
                }
            } else {
                 $_SESSION['error_message'] = "Set görseli için geçersiz dosya tipi veya boyutu.";
            }
            if (isset($_SESSION['error_message'])) {
                $_SESSION['form_input_loadout'] = $_POST;
                header('Location: ' . $baseUrl . '/admin/new_loadout_set.php'); exit;
            }
        } elseif ($set_image_file && $set_image_file['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['error_message'] = "Set görseli yüklenirken bir sorun oluştu. Hata kodu: " . $set_image_file['error'];
            $_SESSION['form_input_loadout'] = $_POST;
            header('Location: ' . $baseUrl . '/admin/new_loadout_set.php'); exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO loadout_sets (user_id, set_name, set_description, set_image_path, status, visibility) 
                 VALUES (:user_id, :set_name, :set_description, :set_image_path, :status, :visibility)"
            );
            if ($stmt->execute([
                ':user_id' => $loggedInUserId,
                ':set_name' => $set_name,
                ':set_description' => $set_description,
                ':set_image_path' => $set_image_db_path,
                ':status' => $status,
                ':visibility' => $visibility
            ])) {
                $new_set_id = $pdo->lastInsertId();
                // Rol atamalarını yap
                if ($visibility === 'faction_only' && !empty($assigned_role_ids)) {
                    $stmt_role_insert = $pdo->prepare("INSERT INTO loadout_set_visibility_roles (set_id, role_id) VALUES (?, ?)");
                    foreach ($assigned_role_ids as $role_id) {
                        $stmt_role_insert->execute([$new_set_id, $role_id]);
                    }
                }
                $pdo->commit();
                unset($_SESSION['form_input_loadout']);
                $_SESSION['success_message'] = "Teçhizat seti başarıyla oluşturuldu.";
                $redirect_page = $default_redirect_page;
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Teçhizat seti oluşturulurken bir veritabanı hatası oluştu.";
                $redirect_page = $baseUrl . '/admin/new_loadout_set.php';
                $_SESSION['form_input_loadout'] = $_POST;
            }
        } catch (PDOException $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log("Yeni teçhizat seti oluşturma hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Teçhizat seti oluşturulurken kritik bir veritabanı hatası oluştu.";
            $redirect_page = $baseUrl . '/admin/new_loadout_set.php';
            $_SESSION['form_input_loadout'] = $_POST;
        }
    }
    elseif ($action === 'update_set_details' && isset($_POST['set_id'], $_POST['edit_set_name'])) {
        $set_id_to_update = (int)$_POST['set_id'];
        $new_set_name = trim($_POST['edit_set_name']);
        $new_set_description = trim($_POST['edit_set_description'] ?? null);
        $status_update = $_POST['status'] ?? 'draft';
        $visibility_update = $_POST['visibility'] ?? 'private';
        $assigned_role_ids_update = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                                    ? array_map('intval', $_POST['assigned_role_ids'])
                                    : [];

        // Görünürlük mantığı
        if ($visibility_update !== 'faction_only') {
            $assigned_role_ids_update = [];
        }

        $new_set_image_file = $_FILES['edit_set_image'] ?? null;
        $existing_image_path = trim($_POST['existing_image_path'] ?? null);
        $delete_current_image = isset($_POST['delete_set_image']) && $_POST['delete_set_image'] == '1';

        $_SESSION['form_input_loadout_edit'] = $_POST; // Hata durumunda formu doldurmak için
        $edit_form_redirect_page = $baseUrl . '/admin/edit_loadout_set_details.php?set_id=' . $set_id_to_update;

        if (empty($new_set_name)) {
            $_SESSION['error_message'] = "Teçhizat seti adı boş bırakılamaz.";
            header('Location: ' . $edit_form_redirect_page);
            exit;
        }

        $thumbnail_db_path_to_update = $existing_image_path;

        if ($delete_current_image || ($new_set_image_file && $new_set_image_file['error'] === UPLOAD_ERR_OK)) {
            if (!empty($existing_image_path)) {
                $old_thumb_full_path = BASE_PATH . '/public/' . $existing_image_path;
                if (file_exists($old_thumb_full_path)) {
                    if (!unlink($old_thumb_full_path)) {
                        error_log("Eski set görseli silinemedi: " . $old_thumb_full_path);
                    } else {
                        error_log("Eski set görseli silindi: " . $old_thumb_full_path);
                    }
                }
            }
            $thumbnail_db_path_to_update = null;
        }

        if ($new_set_image_file && $new_set_image_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir_base = BASE_PATH . '/public/uploads/loadout_set_images/';
            if (!is_dir($upload_dir_base)) { if (!mkdir($upload_dir_base, 0775, true)) { /* Hata */ } }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; 
            
            $file_tmp_name = $new_set_image_file['tmp_name'];
            $file_name_original = $new_set_image_file['name'];
            
            if (in_array(mime_content_type($file_tmp_name), $allowed_types) && $new_set_image_file['size'] <= $max_size) {
                $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
                $new_filename_for_update = 'set_' . $set_id_to_update . '_' . time() . '.' . $file_extension;
                $destination = $upload_dir_base . $new_filename_for_update;
                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $thumbnail_db_path_to_update = 'uploads/loadout_set_images/' . $new_filename_for_update;
                } else {
                    $_SESSION['error_message'] = "Yeni set görseli yüklenirken bir hata oluştu (dosya taşınamadı).";
                }
            } else {
                 $_SESSION['error_message'] = "Yeni set görseli için geçersiz dosya tipi veya boyutu.";
                 if(!$delete_current_image) $thumbnail_db_path_to_update = $existing_image_path;
            }
            if (isset($_SESSION['error_message'])) {
                // $_SESSION['form_input_loadout_edit'] zaten yukarıda set edildi.
                header('Location: ' . $edit_form_redirect_page); exit;
            }
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE loadout_sets 
                 SET set_name = :set_name, set_description = :set_description, set_image_path = :set_image_path, 
                     status = :status, visibility = :visibility, updated_at = NOW() 
                 WHERE id = :set_id"
            );
            if ($stmt->execute([
                ':set_name' => $new_set_name,
                ':set_description' => $new_set_description,
                ':set_image_path' => $thumbnail_db_path_to_update,
                ':status' => $status_update,
                ':visibility' => $visibility_update,
                ':set_id' => $set_id_to_update
            ])) {
                // Rol atamalarını güncelle
                $stmt_delete_roles = $pdo->prepare("DELETE FROM loadout_set_visibility_roles WHERE set_id = ?");
                $stmt_delete_roles->execute([$set_id_to_update]);

                if ($visibility_update === 'faction_only' && !empty($assigned_role_ids_update)) {
                    $stmt_role_insert = $pdo->prepare("INSERT INTO loadout_set_visibility_roles (set_id, role_id) VALUES (?, ?)");
                    foreach ($assigned_role_ids_update as $role_id) {
                        $stmt_role_insert->execute([$set_id_to_update, $role_id]);
                    }
                }
                $pdo->commit();
                unset($_SESSION['form_input_loadout_edit']);
                $_SESSION['success_message'] = "Teçhizat seti detayları başarıyla güncellendi.";
                $redirect_page = $default_redirect_page;
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Set detayları güncellenirken bir veritabanı hatası oluştu.";
                $redirect_page = $edit_form_redirect_page;
                // $_SESSION['form_input_loadout_edit'] zaten yukarıda set edildi.
            }
        } catch (PDOException $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log("Teçhizat seti güncelleme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Set detayları güncellenirken kritik bir veritabanı hatası oluştu.";
            $redirect_page = $edit_form_redirect_page;
            // $_SESSION['form_input_loadout_edit'] zaten yukarıda set edildi.
        }
    } elseif ($action === 'delete_set' && isset($_POST['set_id'])) {
        $set_id_to_delete = (int)$_POST['set_id'];
        $current_image_path_delete = $_POST['current_image_path'] ?? null;
        try {
            $pdo->beginTransaction();
            $stmt_delete = $pdo->prepare("DELETE FROM loadout_sets WHERE id = ?");
            if ($stmt_delete->execute([$set_id_to_delete])) {
                if ($stmt_delete->rowCount() > 0) {
                    if (!empty($current_image_path_delete)) {
                        $full_image_path = BASE_PATH . '/public/' . $current_image_path_delete;
                        if (file_exists($full_image_path)) { unlink($full_image_path); }
                    }
                    $pdo->commit();
                    $_SESSION['success_message'] = "Teçhizat seti başarıyla silindi.";
                } else { $pdo->rollBack(); $_SESSION['error_message'] = "Set silinemedi."; }
            } else { $pdo->rollBack(); $_SESSION['error_message'] = "Set silinirken bir hata oluştu."; }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Teçhizat seti silme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Set silinirken kritik bir hata oluştu.";
        }
        $redirect_page = $default_redirect_page; // Silme sonrası her zaman listeye dön
    }
    else {
        $_SESSION['error_message'] = "Geçersiz teçhizat seti işlemi.";
        // $redirect_page zaten $default_redirect_page
    }

    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek veya eksik action.";
    $fallback_redirect = isset($_POST['set_id']) ? $baseUrl . '/admin/edit_loadout_set_details.php?set_id=' . (int)$_POST['set_id'] : $default_redirect_page;
    header('Location: ' . $fallback_redirect);
    exit;
}
?>
