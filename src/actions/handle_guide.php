<?php
// src/actions/handle_guide.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/guide_functions.php';

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'] ?? null;
$default_redirect_page = $baseUrl . '/admin/guides.php'; // Admin için varsayılan

if (!$loggedInUserId) { // Önce genel bir giriş kontrolü
    $_SESSION['error_message'] = "Bu işlem için giriş yapmalısınız.";
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}
// Oturum geçerliliğini de kontrol edelim
if (function_exists('check_user_session_validity')) {
    check_user_session_validity();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $redirect_page = $default_redirect_page;

    if ($action === 'create_guide') {
        require_permission($pdo, 'guide.create'); // Yetki kontrolü

        if (!isset($_POST['guide_title'], $_POST['guide_content_md']) ||
            empty(trim($_POST['guide_title'])) || empty(trim($_POST['guide_content_md']))) {
            $_SESSION['error_message'] = "Rehber başlığı ve içeriği boş bırakılamaz.";
            $_SESSION['form_input'] = $_POST;
            header('Location: ' . $baseUrl . '/new_guide.php');
            exit;
        }
        // ... (form verilerini alma ve thumbnail işleme kodları aynı kalacak) ...
        $title = trim($_POST['guide_title']);
        $content_md = trim($_POST['guide_content_md']);
        $status = $_POST['guide_status'] ?? 'draft';
        $is_public_no_auth = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
        $is_members_only = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
        $assigned_role_ids = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                            ? array_map('intval', $_POST['assigned_role_ids'])
                            : [];

        if ($is_public_no_auth) {
            $is_members_only = false;
            $assigned_role_ids = [];
        } elseif (!empty($assigned_role_ids)) {
            $is_members_only = false;
        } elseif (!$is_members_only && empty($assigned_role_ids) && !$is_public_no_auth) {
            $_SESSION['error_message'] = "Lütfen bir görünürlük ayarı seçin.";
            $_SESSION['form_input'] = $_POST;
            header('Location: ' . $baseUrl . '/new_guide.php');
            exit;
        }

        $thumbnail_file = $_FILES['guide_thumbnail'] ?? null;
        $thumbnail_db_path = null;

        if ($thumbnail_file && $thumbnail_file['error'] === UPLOAD_ERR_OK) {
            // ... (thumbnail yükleme mantığı aynı) ...
            $upload_dir_base = BASE_PATH . '/public/uploads/guide_thumbnails/';
            if (!is_dir($upload_dir_base)) { if (!mkdir($upload_dir_base, 0775, true)) { /* Hata */ } }
            if (!is_writable($upload_dir_base)) { /* Hata */ }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 15 * 1024 * 1024;
            $file_tmp_name = $thumbnail_file['tmp_name'];
            $file_name_original = basename($thumbnail_file['name']);
            $file_type = mime_content_type($file_tmp_name);
            $file_size = $thumbnail_file['size'];
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
                $new_filename = 'guide_thumb_new_' . time() . '_' . substr(bin2hex(random_bytes(8)), 0, 8) . '.' . $file_extension;
                $destination = $upload_dir_base . $new_filename;
                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $thumbnail_db_path = 'uploads/guide_thumbnails/' . $new_filename;
                } else { $_SESSION['error_message'] = "Thumbnail yüklenemedi."; }
            } else { $_SESSION['error_message'] = "Geçersiz thumbnail tipi/boyutu."; }
            if (isset($_SESSION['error_message'])) {
                $_SESSION['form_input'] = $_POST; header('Location: ' . $baseUrl . '/new_guide.php'); exit;
            }
        } elseif ($thumbnail_file && $thumbnail_file['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['error_message'] = "Thumbnail yükleme hatası: Kod " . $thumbnail_file['error'];
            $_SESSION['form_input'] = $_POST; header('Location: ' . $baseUrl . '/new_guide.php'); exit;
        }


        try {
            $pdo->beginTransaction();
            $slug = get_unique_slug($pdo, $title);
            $stmt = $pdo->prepare(
                "INSERT INTO guides (user_id, title, slug, thumbnail_path, content_md, status, is_public_no_auth, is_members_only)
                 VALUES (:user_id, :title, :slug, :thumbnail_path, :content_md, :status, :is_public_no_auth, :is_members_only)"
            );
            $params_insert = [
                ':user_id' => $loggedInUserId,
                ':title' => $title,
                ':slug' => $slug,
                ':thumbnail_path' => $thumbnail_db_path,
                ':content_md' => $content_md,
                ':status' => $status,
                ':is_public_no_auth' => $is_public_no_auth ? 1 : 0,
                ':is_members_only' => $is_members_only ? 1 : 0
            ];
            $stmt->execute($params_insert);
            $new_guide_id = $pdo->lastInsertId();

            // Thumbnail adını ID ile güncelle (oluşturma sonrası)
            if ($thumbnail_db_path && strpos($thumbnail_db_path, 'guide_thumb_new_') !== false) {
                $path_parts_new = pathinfo($thumbnail_db_path);
                $final_filename_with_id = 'guide_thumb_' . $new_guide_id . '_' . time() . '.' . $path_parts_new['extension']; // time() eklendi
                $old_full_path_new = BASE_PATH . '/public/' . $thumbnail_db_path;
                $new_full_path_new = $upload_dir_base . $final_filename_with_id;
                if (file_exists($old_full_path_new) && rename($old_full_path_new, $new_full_path_new)) {
                    $thumbnail_db_path_final = 'uploads/guide_thumbnails/' . $final_filename_with_id;
                    $stmt_update_thumb = $pdo->prepare("UPDATE guides SET thumbnail_path = ? WHERE id = ?");
                    $stmt_update_thumb->execute([$thumbnail_db_path_final, $new_guide_id]);
                } else {
                    error_log("Yeni rehber thumbnail'ı yeniden adlandırılamadı: $old_full_path_new -> $new_full_path_new");
                }
            }


            if (!$is_public_no_auth && !$is_members_only && !empty($assigned_role_ids)) {
                $stmt_role_insert = $pdo->prepare("INSERT INTO guide_visibility_roles (guide_id, role_id) VALUES (?, ?)");
                foreach ($assigned_role_ids as $role_id) {
                    $stmt_role_insert->execute([$new_guide_id, $role_id]);
                }
            }
            $pdo->commit();
            $_SESSION['success_message'] = "Rehber başarıyla oluşturuldu!";
            $redirect_page = $baseUrl . '/guide_detail.php?slug=' . $slug;

        } catch (PDOException $e) {
            // ... (hata işleme aynı) ...
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log("Rehber oluşturma DB hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Rehber oluşturulurken veritabanı hatası oluştu.";
            $_SESSION['form_input'] = $_POST;
            $redirect_page = $baseUrl . '/new_guide.php';
        }

    } elseif ($action === 'update_guide' && isset($_POST['guide_id'])) {
        $guide_id_to_edit = (int)$_POST['guide_id'];

        // Önce rehberin sahibini çekelim
        $stmt_guide_owner = $pdo->prepare("SELECT user_id, slug, thumbnail_path FROM guides WHERE id = ?");
        $stmt_guide_owner->execute([$guide_id_to_edit]);
        $guide_owner_data = $stmt_guide_owner->fetch(PDO::FETCH_ASSOC);

        if (!$guide_owner_data) {
            $_SESSION['error_message'] = "Düzenlenecek rehber bulunamadı.";
            header('Location: ' . $default_redirect_page);
            exit;
        }

        $can_edit_this = false;
        if (has_permission($pdo, 'guide.edit_all', $loggedInUserId)) {
            $can_edit_this = true;
        } elseif (has_permission($pdo, 'guide.edit_own', $loggedInUserId) && $guide_owner_data['user_id'] == $loggedInUserId) {
            $can_edit_this = true;
        }

        if (!$can_edit_this) {
            $_SESSION['error_message'] = "Bu rehberi düzenleme yetkiniz yok.";
            header('Location: ' . $baseUrl . '/guide_detail.php?slug=' . $guide_owner_data['slug']);
            exit;
        }

        // ... (form verilerini alma ve thumbnail işleme kodları büyük ölçüde aynı) ...
        $title = trim($_POST['guide_title']);
        $content_md = trim($_POST['guide_content_md']);
        $status = $_POST['guide_status'] ?? 'draft';
        $is_public_no_auth = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
        $is_members_only = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
        $assigned_role_ids = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                            ? array_map('intval', $_POST['assigned_role_ids'])
                            : [];
        $delete_thumbnail_flag = isset($_POST['delete_thumbnail']) && $_POST['delete_thumbnail'] == '1';

        if ($is_public_no_auth) {
            $is_members_only = false; $assigned_role_ids = [];
        } elseif (!empty($assigned_role_ids)) {
            $is_members_only = false;
        } elseif (!$is_members_only && empty($assigned_role_ids) && !$is_public_no_auth) {
             $_SESSION['error_message'] = "Lütfen bir görünürlük ayarı seçin.";
            $_SESSION['form_input'] = $_POST;
            header('Location: ' . $baseUrl . '/new_guide.php?edit_id='.$guide_id_to_edit);
            exit;
        }

        $thumbnail_db_path_update = $guide_owner_data['thumbnail_path']; // Mevcutu koru
        $thumbnail_file_update = $_FILES['guide_thumbnail'] ?? null;

        if ($delete_thumbnail_flag) {
            if (!empty($thumbnail_db_path_update) && file_exists(BASE_PATH . '/public/' . $thumbnail_db_path_update)) {
                unlink(BASE_PATH . '/public/' . $thumbnail_db_path_update);
            }
            $thumbnail_db_path_update = null;
        }

        if ($thumbnail_file_update && $thumbnail_file_update['error'] === UPLOAD_ERR_OK) {
            // ... (yeni thumbnail yükleme ve eskiyi silme mantığı aynı) ...
            $upload_dir_base_update = BASE_PATH . '/public/uploads/guide_thumbnails/';
            // (Klasör kontrolü ve yazılabilirlik kontrolü burada da olmalı)
            $allowed_types_update = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size_update = 15 * 1024 * 1024;
            // ... (dosya tipi, boyutu kontrolü) ...
            if (!empty($thumbnail_db_path_update) && file_exists(BASE_PATH . '/public/' . $thumbnail_db_path_update)) {
                 unlink(BASE_PATH . '/public/' . $thumbnail_db_path_update);
            }
            $file_extension_update = strtolower(pathinfo($thumbnail_file_update['name'], PATHINFO_EXTENSION));
            $new_filename_update = 'guide_thumb_' . $guide_id_to_edit . '_' . time() . '.' . $file_extension_update;
            $destination_update = $upload_dir_base_update . $new_filename_update;
            if (move_uploaded_file($thumbnail_file_update['tmp_name'], $destination_update)) {
                $thumbnail_db_path_update = 'uploads/guide_thumbnails/' . $new_filename_update;
            } else { $_SESSION['error_message'] = "Thumbnail güncellenirken yüklenemedi."; /* Hata ve yönlendirme */ }
            // ... (hata durumunda yönlendirme) ...
        } elseif ($thumbnail_file_update && $thumbnail_file_update['error'] !== UPLOAD_ERR_NO_FILE) {
             $_SESSION['error_message'] = "Thumbnail yükleme hatası: Kod " . $thumbnail_file_update['error'];
             $_SESSION['form_input'] = $_POST; header('Location: ' . $baseUrl . '/new_guide.php?edit_id='.$guide_id_to_edit); exit;
        }


        try {
            $pdo->beginTransaction();
            // Slug'ı güncelleme (opsiyonel, genellikle değiştirilmez ama başlık değişirse düşünülebilir)
            // $new_slug_for_update = get_unique_slug($pdo, $title, $guide_id_to_edit);

            $sql_update_guide = "UPDATE guides SET
                                    title = :title,
                                    thumbnail_path = :thumbnail_path,
                                    content_md = :content_md,
                                    status = :status,
                                    is_public_no_auth = :is_public_no_auth,
                                    is_members_only = :is_members_only,
                                    updated_at = NOW()
                                 WHERE id = :guide_id";
            $params_update_guide = [
                ':title' => $title,
                ':thumbnail_path' => $thumbnail_db_path_update,
                ':content_md' => $content_md,
                ':status' => $status,
                ':is_public_no_auth' => $is_public_no_auth ? 1 : 0,
                ':is_members_only' => $is_members_only ? 1 : 0,
                ':guide_id' => $guide_id_to_edit
            ];
            $stmt = $pdo->prepare($sql_update_guide);
            $stmt->execute($params_update_guide);

            $stmt_delete_roles = $pdo->prepare("DELETE FROM guide_visibility_roles WHERE guide_id = ?");
            $stmt_delete_roles->execute([$guide_id_to_edit]);

            if (!$is_public_no_auth && !$is_members_only && !empty($assigned_role_ids)) {
                $stmt_role_insert = $pdo->prepare("INSERT INTO guide_visibility_roles (guide_id, role_id) VALUES (?, ?)");
                foreach ($assigned_role_ids as $role_id) {
                    $stmt_role_insert->execute([$guide_id_to_edit, $role_id]);
                }
            }
            $pdo->commit();
            $_SESSION['success_message'] = "Rehber başarıyla güncellendi.";
            $redirect_page = $baseUrl . '/guide_detail.php?slug=' . ($guide_owner_data['slug'] ?: generate_slug($title));

        } catch (PDOException $e) {
            // ... (hata işleme aynı) ...
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log("Rehber güncelleme DB hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Rehber güncellenirken veritabanı hatası oluştu.";
            $_SESSION['form_input'] = $_POST;
            $redirect_page = $baseUrl . '/new_guide.php?edit_id='.$guide_id_to_edit;
        }


    } elseif ($action === 'delete_guide' && isset($_POST['guide_id'])) {
        $guide_id_to_delete = (int)$_POST['guide_id'];

        $stmt_guide_owner_delete = $pdo->prepare("SELECT user_id, thumbnail_path FROM guides WHERE id = ?");
        $stmt_guide_owner_delete->execute([$guide_id_to_delete]);
        $guide_to_delete_info = $stmt_guide_owner_delete->fetch(PDO::FETCH_ASSOC);

        if (!$guide_to_delete_info) {
            $_SESSION['error_message'] = "Silinecek rehber bulunamadı.";
            header('Location: ' . $default_redirect_page);
            exit;
        }

        $can_delete_this = false;
        if (has_permission($pdo, 'guide.delete_all', $loggedInUserId)) {
            $can_delete_this = true;
        } elseif (has_permission($pdo, 'guide.delete_own', $loggedInUserId) && $guide_to_delete_info['user_id'] == $loggedInUserId) {
            $can_delete_this = true;
        }

        if (!$can_delete_this) {
            $_SESSION['error_message'] = "Bu rehberi silme yetkiniz yok.";
            header('Location: ' . $default_redirect_page);
            exit;
        }

        try {
            $pdo->beginTransaction();
            // İlişkili kayıtlar (visibility_roles, likes) ON DELETE CASCADE ile silinmeli (DB şemasında)
            // Eğer DB'de CASCADE yoksa, burada manuel silme yapılmalı:
            // $pdo->prepare("DELETE FROM guide_visibility_roles WHERE guide_id = ?")->execute([$guide_id_to_delete]);
            // $pdo->prepare("DELETE FROM guide_likes WHERE guide_id = ?")->execute([$guide_id_to_delete]);

            $stmt_delete_guide = $pdo->prepare("DELETE FROM guides WHERE id = ?");
            $stmt_delete_guide->execute([$guide_id_to_delete]);

            if (!empty($guide_to_delete_info['thumbnail_path'])) {
                $thumbnail_to_delete_full_path = BASE_PATH . '/public/' . $guide_to_delete_info['thumbnail_path'];
                if (file_exists($thumbnail_to_delete_full_path)) {
                    unlink($thumbnail_to_delete_full_path);
                }
            }
            $pdo->commit();
            $_SESSION['success_message'] = "Rehber başarıyla silindi.";

        } catch (PDOException $e) {
            // ... (hata işleme aynı) ...
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Rehber silme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Rehber silinirken veritabanı hatası oluştu.";
        }
        $redirect_page = $default_redirect_page;

    } else {
        $_SESSION['error_message'] = "Geçersiz işlem türü.";
    }

    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    $fallback_redirect = isset($_POST['guide_id']) && is_numeric($_POST['guide_id'])
                       ? ($baseUrl . '/new_guide.php?edit_id='.(int)$_POST['guide_id'])
                       : $default_redirect_page;
    header('Location: ' . $fallback_redirect);
    exit;
}
