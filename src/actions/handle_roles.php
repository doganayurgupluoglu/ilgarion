<?php
// src/actions/handle_roles.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

$baseUrl = get_auth_base_url();
$redirect_page = $baseUrl . '/admin/manage_roles.php';

// Rate limiting kontrolü
if (!check_rate_limit('role_management', 10, 300)) { // 5 dakikada 10 işlem
    $_SESSION['error_message'] = "Çok fazla işlem yapıyorsunuz. Lütfen 5 dakika bekleyin.";
    header('Location: ' . $redirect_page);
    exit;
}

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: ' . $redirect_page);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Input validation
    if (!validate_sql_input($action)) {
        $_SESSION['error_message'] = "Geçersiz işlem türü.";
        header('Location: ' . $redirect_page);
        exit;
    }

    try {
        // Yetki kontrolünü action'a göre yap
        if ($action === 'create_role') {
            require_permission($pdo, 'admin.roles.create');
        } elseif ($action === 'update_role') {
            require_permission($pdo, 'admin.roles.edit');
        } elseif ($action === 'delete_role') {
            require_permission($pdo, 'admin.roles.delete');
        } else {
            throw new SecurityException("Geçersiz rol yönetimi işlemi: $action");
        }

        if ($action === 'create_role' || $action === 'update_role') {
            // Input validation ve sanitization
            if (!isset($_POST['role_name']) || empty(trim($_POST['role_name']))) {
                $_SESSION['error_message'] = "Rol adı boş bırakılamaz.";
                $_SESSION['form_input_role_edit'] = $_POST;
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }

            $role_name = trim($_POST['role_name']);
            $role_description = trim($_POST['role_description'] ?? '');
            $role_color = trim($_POST['role_color'] ?? '#4a5568');
            $role_priority = isset($_POST['role_priority']) ? (int)$_POST['role_priority'] : 999;
            $permissions_array = $_POST['permissions'] ?? [];

            // Comprehensive input validation
            if (!validate_role_name($role_name)) {
                $_SESSION['error_message'] = "Rol adı geçersiz format. Sadece küçük harf, rakam ve alt çizgi (_) kullanın (2-50 karakter).";
                $_SESSION['form_input_role_edit'] = $_POST;
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }

            if (!validate_color_code($role_color)) {
                $_SESSION['error_message'] = "Renk kodu geçersiz format. #RRGGBB formatında olmalıdır.";
                $_SESSION['form_input_role_edit'] = $_POST;
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }

            if ($role_priority < 1 || $role_priority > 9999) {
                $_SESSION['error_message'] = "Rol önceliği 1-9999 arasında olmalıdır.";
                $_SESSION['form_input_role_edit'] = $_POST;
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }

            if (strlen($role_description) > 255) {
                $_SESSION['error_message'] = "Rol açıklaması 255 karakterden uzun olamaz.";
                $_SESSION['form_input_role_edit'] = $_POST;
                $form_redirect = $baseUrl . '/admin/edit_role.php';
                if ($action === 'update_role' && isset($_POST['role_id'])) {
                    $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                }
                header('Location: ' . $form_redirect);
                exit;
            }

            // Permissions validation
            if (!empty($permissions_array)) {
                foreach ($permissions_array as $permission) {
                    if (!validate_sql_input($permission, 'permission_key')) {
                        $_SESSION['error_message'] = "Geçersiz yetki tespit edildi: " . htmlspecialchars($permission);
                        $_SESSION['form_input_role_edit'] = $_POST;
                        $form_redirect = $baseUrl . '/admin/edit_role.php';
                        if ($action === 'update_role' && isset($_POST['role_id'])) {
                            $form_redirect .= '?role_id=' . (int)$_POST['role_id'];
                        }
                        header('Location: ' . $form_redirect);
                        exit;
                    }
                }
            }

            if ($action === 'create_role') {
                // Rol adının benzersiz olup olmadığını güvenli şekilde kontrol et
                $check_query = "SELECT id FROM roles WHERE name = :role_name";
                $check_params = [':role_name' => $role_name];
                $stmt_check = execute_safe_query($pdo, $check_query, $check_params);
                
                if ($stmt_check->fetch()) {
                    $_SESSION['error_message'] = "Bu rol adı ('" . htmlspecialchars($role_name) . "') zaten kullanılıyor.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                    header('Location: ' . $baseUrl . '/admin/edit_role.php');
                    exit;
                }

                // Basit güvenli rol oluşturma (transaction olmadan)
                try {
                    // Rol oluştur
                    $insert_query = "INSERT INTO roles (name, description, color, priority) VALUES (:name, :description, :color, :priority)";
                    $insert_params = [
                        ':name' => $role_name,
                        ':description' => $role_description,
                        ':color' => $role_color,
                        ':priority' => $role_priority
                    ];
                    $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
                    
                    $role_id = $pdo->lastInsertId();
                    
                    // Yetkileri ata
                    if (!empty($permissions_array)) {
                        assign_permissions_to_role($pdo, $role_id, $permissions_array, $_SESSION['user_id']);
                    }
                    
                    $result = $role_id;
                } catch (Exception $e) {
                    error_log("Rol oluşturma hatası: " . $e->getMessage());
                    $result = false;
                }

                if ($result) {
                    unset($_SESSION['form_input_role_edit']);
                    $_SESSION['success_message'] = "Rol başarıyla oluşturuldu.";
                    
                    // Audit log
                    audit_log($pdo, $_SESSION['user_id'], 'role_created', 'role', $result, null, [
                        'role_name' => $role_name,
                        'role_description' => $role_description,
                        'role_color' => $role_color,
                        'role_priority' => $role_priority,
                        'permissions_count' => count($permissions_array)
                    ]);
                } else {
                    $_SESSION['error_message'] = "Rol oluşturulurken bir hata oluştu.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                    $redirect_page = $baseUrl . '/admin/edit_role.php';
                }

            } elseif ($action === 'update_role') {
                if (!isset($_POST['role_id']) || !is_numeric($_POST['role_id'])) {
                    $_SESSION['error_message'] = "Düzenlenecek rol ID'si geçersiz.";
                    header('Location: ' . $redirect_page);
                    exit;
                }
                $role_id = (int)$_POST['role_id'];

                // Mevcut rol bilgilerini güvenli şekilde çek
                $get_role_query = "SELECT name, description, color, priority FROM roles WHERE id = :role_id";
                $get_role_params = [':role_id' => $role_id];
                $stmt_get_role = execute_safe_query($pdo, $get_role_query, $get_role_params);
                $current_role_db = $stmt_get_role->fetch();

                if (!$current_role_db) {
                    $_SESSION['error_message'] = "Düzenlenecek rol bulunamadı.";
                    header('Location: ' . $redirect_page);
                    exit;
                }

                // Temel rollerin adının değiştirilmesini engelle
                if (!is_role_name_editable($current_role_db['name']) && $current_role_db['name'] !== $role_name) {
                    $_SESSION['error_message'] = "Korumalı rollerin adı değiştirilemez.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                    header('Location: ' . $baseUrl . '/admin/edit_role.php?role_id=' . $role_id);
                    exit;
                }

                // Eğer rol adı değiştiyse, yeni adın benzersiz olup olmadığını kontrol et
                if ($current_role_db['name'] !== $role_name) {
                    $check_new_name_query = "SELECT id FROM roles WHERE name = :role_name AND id != :role_id";
                    $check_new_name_params = [':role_name' => $role_name, ':role_id' => $role_id];
                    $stmt_check_new_name = execute_safe_query($pdo, $check_new_name_query, $check_new_name_params);
                    
                    if ($stmt_check_new_name->fetch()) {
                        $_SESSION['error_message'] = "Bu rol adı ('" . htmlspecialchars($role_name) . "') zaten başka bir rol tarafından kullanılıyor.";
                        $_SESSION['form_input_role_edit'] = $_POST;
                        header('Location: ' . $baseUrl . '/admin/edit_role.php?role_id=' . $role_id);
                        exit;
                    }
                }

                // Basit güvenli rol güncelleme (transaction olmadan)
                try {
                    // Rol bilgilerini güncelle
                    $update_query = "UPDATE roles SET name = :name, description = :description, color = :color, priority = :priority WHERE id = :role_id";
                    $update_params = [
                        ':name' => $role_name,
                        ':description' => $role_description,
                        ':color' => $role_color,
                        ':priority' => $role_priority,
                        ':role_id' => $role_id
                    ];
                    execute_safe_query($pdo, $update_query, $update_params);
                    
                    // Yetkileri güncelle
                    assign_permissions_to_role($pdo, $role_id, $permissions_array, $_SESSION['user_id']);
                    
                    $result = true;
                } catch (Exception $e) {
                    error_log("Rol güncelleme hatası: " . $e->getMessage());
                    $result = false;
                }

                if ($result) {
                    unset($_SESSION['form_input_role_edit']);
                    $_SESSION['success_message'] = "Rol başarıyla güncellendi.";
                    
                    // Audit log
                    audit_log($pdo, $_SESSION['user_id'], 'role_updated', 'role', $role_id, [
                        'old_name' => $current_role_db['name'],
                        'old_description' => $current_role_db['description'],
                        'old_color' => $current_role_db['color'],
                        'old_priority' => $current_role_db['priority']
                    ], [
                        'new_name' => $role_name,
                        'new_description' => $role_description,
                        'new_color' => $role_color,
                        'new_priority' => $role_priority,
                        'permissions_count' => count($permissions_array)
                    ]);
                } else {
                    $_SESSION['error_message'] = "Rol güncellenirken bir hata oluştu.";
                    $_SESSION['form_input_role_edit'] = $_POST;
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

            // Rol bilgilerini güvenli şekilde çek
            $get_delete_role_query = "SELECT name, description FROM roles WHERE id = :role_id";
            $get_delete_role_params = [':role_id' => $role_id_to_delete];
            $stmt_check_delete = execute_safe_query($pdo, $get_delete_role_query, $get_delete_role_params);
            $role_to_delete = $stmt_check_delete->fetch();

            if (!$role_to_delete) {
                $_SESSION['error_message'] = "Silinecek rol bulunamadı.";
                header('Location: ' . $redirect_page);
                exit;
            }

            // Temel rolleri silmeyi engelle
            if (!is_role_deletable($role_to_delete['name'])) {
                $_SESSION['error_message'] = "Korumalı roller güvenlik nedeniyle silinemez.";
                
                // Audit log - yetkisiz silme girişimi
                audit_log($pdo, $_SESSION['user_id'], 'unauthorized_role_delete_attempt', 'role', $role_id_to_delete, null, [
                    'role_name' => $role_to_delete['name'],
                    'reason' => 'Protected role deletion attempt'
                ]);
                
                header('Location: ' . $redirect_page);
                exit;
            }

            // Bu role sahip kullanıcı sayısını kontrol et
            $user_count_query = "SELECT COUNT(*) FROM user_roles WHERE role_id = :role_id";
            $user_count_params = [':role_id' => $role_id_to_delete];
            $stmt_user_count = execute_safe_query($pdo, $user_count_query, $user_count_params);
            $user_count = $stmt_user_count->fetchColumn();

            // Basit güvenli rol silme (transaction olmadan)
            try {
                // Önce rol yetkilerini sil
                $delete_permissions_query = "DELETE FROM role_permissions WHERE role_id = :role_id";
                $delete_permissions_params = [':role_id' => $role_id_to_delete];
                execute_safe_query($pdo, $delete_permissions_query, $delete_permissions_params);
                
                // Kullanıcı-rol ilişkilerini sil
                $delete_user_roles_query = "DELETE FROM user_roles WHERE role_id = :role_id";
                $delete_user_roles_params = [':role_id' => $role_id_to_delete];
                execute_safe_query($pdo, $delete_user_roles_query, $delete_user_roles_params);
                
                // Rolü sil
                $delete_role_query = "DELETE FROM roles WHERE id = :role_id";
                $delete_role_params = [':role_id' => $role_id_to_delete];
                $stmt_delete = execute_safe_query($pdo, $delete_role_query, $delete_role_params);
                
                $result = $stmt_delete->rowCount() > 0;
            } catch (Exception $e) {
                error_log("Rol silme hatası: " . $e->getMessage());
                $result = false;
            }

            if ($result) {
                $_SESSION['success_message'] = "Rol başarıyla silindi." . ($user_count > 0 ? " ($user_count kullanıcı etkilendi)" : "");
                
                // Audit log
                audit_log($pdo, $_SESSION['user_id'], 'role_deleted', 'role', $role_id_to_delete, [
                    'role_name' => $role_to_delete['name'],
                    'role_description' => $role_to_delete['description'],
                    'affected_users' => $user_count
                ], null);
            } else {
                $_SESSION['error_message'] = "Rol silinemedi.";
            }

        } else {
            $_SESSION['error_message'] = "Geçersiz rol işlemi.";
        }

    } catch (SecurityException $e) {
        error_log("Güvenlik ihlali - Rol yönetimi (" . $action . "): " . $e->getMessage());
        $_SESSION['error_message'] = "Güvenlik ihlali tespit edildi. İşlem engellendi.";
        
        // Güvenlik ihlali audit log
        audit_log($pdo, $_SESSION['user_id'] ?? null, 'security_violation', 'role_management', null, null, [
            'action' => $action,
            'error' => $e->getMessage(),
            'post_data' => array_keys($_POST) // Sadece key'leri log'la, değerleri değil
        ]);
        
        if (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && ($action === 'update_role' || $action === 'create_role')) {
            $_SESSION['form_input_role_edit'] = $_POST;
            $redirect_page = $baseUrl . '/admin/edit_role.php' . ($action === 'update_role' ? '?role_id='.(int)$_POST['role_id'] : '');
        }
        
    } catch (DatabaseException $e) {
        error_log("Veritabanı hatası - Rol yönetimi (" . $action . "): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında bir veritabanı hatası oluştu. Lütfen tekrar deneyin.";
        
        if (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && ($action === 'update_role' || $action === 'create_role')) {
            $_SESSION['form_input_role_edit'] = $_POST;
            $redirect_page = $baseUrl . '/admin/edit_role.php' . ($action === 'update_role' ? '?role_id='.(int)$_POST['role_id'] : '');
        }
        
    } catch (Exception $e) {
        error_log("Genel hata - Rol yönetimi (" . $action . "): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında beklenmeyen bir hata oluştu.";
        
        if (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && ($action === 'update_role' || $action === 'create_role')) {
            $_SESSION['form_input_role_edit'] = $_POST;
            $redirect_page = $baseUrl . '/admin/edit_role.php' . ($action === 'update_role' ? '?role_id='.(int)$_POST['role_id'] : '');
        }
    }

    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek yöntemi.";
    
    // Audit log - geçersiz istek
    audit_log($pdo, $_SESSION['user_id'] ?? null, 'invalid_request', 'role_management', null, null, [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? ''
    ]);
    
    header('Location: ' . $redirect_page);
    exit;
}
?>