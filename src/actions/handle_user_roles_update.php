<?php
// src/actions/handle_user_roles_update.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// Yetki kontrolü: 'admin.users.assign_roles'
// require_admin(); // Genel admin kontrolü yerine spesifik yetki
if (isset($pdo)) { // $pdo'nun tanımlı olduğundan emin olalım
    require_permission($pdo, 'admin.users.assign_roles');
} else {
    $_SESSION['error_message'] = "Veritabanı bağlantısı yapılandırılamadı.";
    error_log("handle_user_roles_update.php: PDO nesnesi bulunamadı.");
    header('Location: ' . get_auth_base_url() . '/admin/users.php'); // Genel bir admin sayfasına yönlendir
    exit;
}

$baseUrl = get_auth_base_url();
$redirect_page = $baseUrl . '/admin/users.php'; // Varsayılan yönlendirme

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id_to_update'])) {
    $user_id_to_update = (int)$_POST['user_id_to_update'];
    $assigned_role_ids_from_form = isset($_POST['assigned_roles']) && is_array($_POST['assigned_roles'])
                                  ? array_map('intval', $_POST['assigned_roles'])
                                  : [];

    // Admin kendi admin rolünü kaldıramaz kontrolü
    if ($user_id_to_update == $_SESSION['user_id']) {
        // Kullanıcının mevcut rollerini DB'den çek
        $stmt_current_roles = $pdo->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
        $stmt_current_roles->execute([$user_id_to_update]);
        $current_db_role_names = $stmt_current_roles->fetchAll(PDO::FETCH_COLUMN);

        // Eğer admin rolü DB'de varsa ama formdan gelmiyorsa (yani kaldırılmak isteniyorsa)
        if (in_array('admin', $current_db_role_names)) {
            $admin_role_id_db = null;
            $stmt_admin_id = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $stmt_admin_id->execute();
            $admin_role_id_db = $stmt_admin_id->fetchColumn();

            if ($admin_role_id_db && !in_array($admin_role_id_db, $assigned_role_ids_from_form)) {
                $_SESSION['error_message'] = "Kendi admin yetkinizi bu arayüzden kaldıramazsınız.";
                header('Location: ' . $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update);
                exit;
            }
        }
    }


    try {
        $pdo->beginTransaction();

        // 1. Kullanıcının mevcut tüm rollerini sil
        $stmt_delete_old_roles = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt_delete_old_roles->execute([$user_id_to_update]);

        // 2. Formdan gelen yeni rolleri ekle
        if (!empty($assigned_role_ids_from_form)) {
            $stmt_insert_new_role = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($assigned_role_ids_from_form as $role_id) {
                // Rolün var olup olmadığını kontrol et (güvenlik için)
                $stmt_check_role_exists = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
                $stmt_check_role_exists->execute([$role_id]);
                if ($stmt_check_role_exists->fetch()) {
                    $stmt_insert_new_role->execute([$user_id_to_update, $role_id]);
                } else {
                    error_log("Geçersiz rol ID ($role_id) kullanıcıya ($user_id_to_update) atanmaya çalışıldı.");
                }
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Kullanıcının rolleri başarıyla güncellendi.";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Kullanıcı rolleri güncelleme hatası (Kullanıcı ID: $user_id_to_update): " . $e->getMessage());
        $_SESSION['error_message'] = "Kullanıcı rolleri güncellenirken bir veritabanı hatası oluştu.";
        $redirect_page = $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update; // Hata durumunda formu tekrar göster
    }

    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: ' . $redirect_page);
    exit;
}
