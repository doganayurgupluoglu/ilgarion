<?php
// src/actions/handle_roles.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); // Genel admin kontrolü yerine action bazlı kontrol yapılacak

$baseUrl = get_auth_base_url();
$redirect_page = $baseUrl . '/admin/manage_roles.php'; // Varsayılan yönlendirme manage_roles.php olmalı;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // Yetki kontrolünü action'a göre yap
        if ($action === 'create_role') {
            require_permission($pdo, 'admin.roles.create');
        } elseif ($action === 'update_role') {
            require_permission($pdo, 'admin.roles.edit');
        } elseif ($action === 'delete_role') {
            require_permission($pdo, 'admin.roles.delete');
        } else {
            throw new Exception("Geçersiz rol yönetimi işlemi.");
        }

        if ($action === 'create_role' || $action === 'update_role') {
            if (!isset($_POST['role_name']) || empty(trim($_POST['role_name']))) {
                $_SESSION['error_message'] = "Rol adı boş bırakılamaz.";
                $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }

            $role_name = trim($_POST['role_name']);
            $role_description = trim($_POST['role_description'] ?? null);
            $role_color = trim($_POST['role_color'] ?? '#000000');
            $permissions_array = $_POST['permissions'] ?? [];
            $permissions_string = !empty($permissions_array) ? implode(',', array_map('trim', $permissions_array)) : null;

            // Rol adı formatını kontrol et (sadece küçük harf, rakam ve alt çizgi)
            if (!preg_match('/^[a-z0-9_]+$/', $role_name)) {
                $_SESSION['error_message'] = "Rol adı sadece küçük harf, rakam ve alt çizgi (_) içerebilir.";
                $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                 if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }


            if ($action === 'create_role') {
                // Rol adının benzersiz olup olmadığını kontrol et
                $stmt_check = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt_check->execute([$role_name]);
                if ($stmt_check->fetch()) {
                    $_SESSION['error_message'] = "Bu rol adı ('" . htmlspecialchars($role_name) . "') zaten kullanılıyor.";
                    $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                    header('Location: ' . $baseUrl . '/admin/edit_role.php');
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO roles (name, description, color, permissions) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$role_name, $role_description, $role_color, $permissions_string])) {
                    unset($_SESSION['form_input_role_edit']); // Başarılıysa session'ı temizle
                    $_SESSION['success_message'] = "Rol başarıyla oluşturuldu.";
                } else {
                    $_SESSION['error_message'] = "Rol oluşturulurken bir veritabanı hatası oluştu.";
                    $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                    $redirect_page = $baseUrl . '/admin/edit_role.php';
                }
            } elseif ($action === 'update_role') {
                if (!isset($_POST['role_id']) || !is_numeric($_POST['role_id'])) {
                    $_SESSION['error_message'] = "Düzenlenecek rol ID'si geçersiz.";
                    header('Location: ' . $redirect_page);
                    exit;
                }
                $role_id = (int)$_POST['role_id'];

                // Temel rollerin adının değiştirilmesini engelle
                $stmt_get_role = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                $stmt_get_role->execute([$role_id]);
                $current_role_db = $stmt_get_role->fetch(PDO::FETCH_ASSOC);

                if ($current_role_db && in_array($current_role_db['name'], ['admin', 'member', 'dis_uye']) && $current_role_db['name'] !== $role_name) {
                     $_SESSION['error_message'] = "Temel rollerin ('admin', 'member', 'dis_uye') adı değiştirilemez.";
                     $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                     header('Location: ' . $baseUrl . '/admin/edit_role.php?role_id=' . $role_id);
                     exit;
                }
                // Eğer rol adı değiştiyse, yeni adın benzersiz olup olmadığını kontrol et
                if ($current_role_db && $current_role_db['name'] !== $role_name) {
                    $stmt_check_new_name = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
                    $stmt_check_new_name->execute([$role_name, $role_id]);
                    if ($stmt_check_new_name->fetch()) {
                        $_SESSION['error_message'] = "Bu rol adı ('" . htmlspecialchars($role_name) . "') zaten başka bir rol tarafından kullanılıyor.";
                        $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                        header('Location: ' . $baseUrl . '/admin/edit_role.php?role_id=' . $role_id);
                        exit;
                    }
                }


                $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ?, color = ?, permissions = ? WHERE id = ?");
                if ($stmt->execute([$role_name, $role_description, $role_color, $permissions_string, $role_id])) {
                    unset($_SESSION['form_input_role_edit']); // Başarılıysa session'ı temizle
                    $_SESSION['success_message'] = "Rol başarıyla güncellendi.";
                } else {
                    $_SESSION['error_message'] = "Rol güncellenirken bir veritabanı hatası oluştu.";
                     $_SESSION['form_input_role_edit'] = $_POST; // Session anahtarı güncellendi
                    $redirect_page = $baseUrl . '/admin/edit_role.php?role_id=' . $role_id;
                }
            }
        } elseif ($action === 'delete_role') {
            if (!isset($_POST['role_id']) || !is_numeric($_POST['role_id'])) {
                $_SESSION['error_message'] = "Silinecek rol ID'si geçersiz.";
                header('Location: ' . $redirect_page);
                exit;
            }
            $role_id_to_delete = (int)$_POST['role_id'];

            // Temel rolleri silmeyi engelle
            $stmt_check_delete = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $stmt_check_delete->execute([$role_id_to_delete]);
            $role_name_to_delete = $stmt_check_delete->fetchColumn();

            if (in_array($role_name_to_delete, ['admin', 'member', 'dis_uye'])) {
                $_SESSION['error_message'] = "Temel roller ('admin', 'member', 'dis_uye') silinemez.";
                header('Location: ' . $redirect_page);
                exit;
            }

            // user_roles tablosundaki ilişkiler CASCADE ON DELETE ile otomatik silinecektir.
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            if ($stmt->execute([$role_id_to_delete])) {
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Rol başarıyla silindi.";
                } else {
                    $_SESSION['error_message'] = "Rol silinemedi (belki zaten silinmişti).";
                }
            } else {
                $_SESSION['error_message'] = "Rol silinirken bir veritabanı hatası oluştu.";
            }
        } else {
            $_SESSION['error_message'] = "Geçersiz rol işlemi.";
        }
    } catch (PDOException $e) {
        error_log("Rol yönetimi hatası (" . $action . "): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında kritik bir veritabanı hatası oluştu.";
        if (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && ($action === 'update_role' || $action === 'create_role')) {
            $_SESSION['form_input_role_edit'] = $_POST; // Form verilerini koru, session anahtarı güncellendi
            $redirect_page = $baseUrl . '/admin/edit_role.php' . ($action === 'update_role' ? '?role_id='.(int)$_POST['role_id'] : '');
        }
    }
    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: ' . $redirect_page);
    exit;
}
