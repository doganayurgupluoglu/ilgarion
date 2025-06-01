<?php
// src/actions/handle_roles.php - Güvenlik Açığı Kapatılmış Versiyon

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
if (!check_rate_limit('role_management', 15, 300)) { // 5 dakikada 15 işlem
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
    if (!validate_sql_input($action, 'action')) {
        $_SESSION['error_message'] = "Geçersiz işlem türü.";
        header('Location: ' . $redirect_page);
        exit;
    }

    try {
        // Kullanıcının hiyerarşik konumunu belirle
        $current_user_id = $_SESSION['user_id'];
        $is_super_admin_user = is_super_admin($pdo);
        
        $user_roles = get_user_roles($pdo, $current_user_id);
        $user_highest_priority = 999;
        foreach ($user_roles as $role) {
            if ($role['priority'] < $user_highest_priority) {
                $user_highest_priority = $role['priority'];
            }
        }

        // Kullanıcının sahip olduğu yetkileri al - KRİTİK GÜVENLİK KONTROLÜ
        $user_permissions = [];
        if ($is_super_admin_user) {
            // Süper admin tüm yetkilere sahip
            $all_perms_query = "SELECT permission_key FROM permissions WHERE is_active = 1";
            $stmt_all_perms = execute_safe_query($pdo, $all_perms_query);
            $user_permissions = $stmt_all_perms->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Normal kullanıcının yetkileri
            $user_perms_query = "
                SELECT DISTINCT p.permission_key
                FROM user_roles ur 
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = :user_id AND p.is_active = 1
            ";
            $stmt_user_perms = execute_safe_query($pdo, $user_perms_query, [':user_id' => $current_user_id]);
            $user_permissions = $stmt_user_perms->fetchAll(PDO::FETCH_COLUMN);
        }

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
                header('Location: ' . $redirect_page);
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
                header('Location: ' . $redirect_page);
                exit;
            }

            if (!validate_color_code($role_color)) {
                $_SESSION['error_message'] = "Renk kodu geçersiz format. #RRGGBB formatında olmalıdır.";
                $_SESSION['form_input_role_edit'] = $_POST;
                header('Location: ' . $redirect_page);
                exit;
            }

            if ($role_priority < 1 || $role_priority > 9999) {
                $_SESSION['error_message'] = "Rol önceliği 1-9999 arasında olmalıdır.";
                $_SESSION['form_input_role_edit'] = $_POST;
                header('Location: ' . $redirect_page);
                exit;
            }

            if (strlen($role_description) > 255) {
                $_SESSION['error_message'] = "Rol açıklaması 255 karakterden uzun olamaz.";
                $_SESSION['form_input_role_edit'] = $_POST;
                header('Location: ' . $redirect_page);
                exit;
            }

            // Hiyerarşi kontrolü - Yeni rol kullanıcının seviyesinden yüksek olamaz
            if (!$is_super_admin_user && $role_priority <= $user_highest_priority) {
                $_SESSION['error_message'] = "Kendi hiyerarşi seviyenizden yüksek veya eşit öncelikli rol oluşturamazsınız. Öncelik değeri {$user_highest_priority}'den büyük olmalıdır.";
                $_SESSION['form_input_role_edit'] = $_POST;
                header('Location: ' . $redirect_page);
                exit;
            }

            // ⚠️ KRİTİK GÜVENLİK KONTROLÜ: YETKİ ATAMA KISITLAMASI
            if (!empty($permissions_array)) {
                foreach ($permissions_array as $permission) {
                    if (!validate_sql_input($permission, 'permission_key')) {
                        $_SESSION['error_message'] = "Geçersiz yetki tespit edildi: " . htmlspecialchars($permission);
                        $_SESSION['form_input_role_edit'] = $_POST;
                        header('Location: ' . $redirect_page);
                        exit;
                    }
                }
                
                // Kullanıcının sahip olmadığı yetkileri atamaya çalışıyor mu kontrol et
                if (!$is_super_admin_user) {
                    $unauthorized_permissions = array_diff($permissions_array, $user_permissions);
                    if (!empty($unauthorized_permissions)) {
                        $_SESSION['error_message'] = "Sahip olmadığınız yetkileri atamazsınız: " . implode(', ', $unauthorized_permissions);
                        $_SESSION['form_input_role_edit'] = $_POST;
                        
                        // Güvenlik ihlali audit log
                        audit_log($pdo, $current_user_id, 'unauthorized_permission_assignment_attempt', 'role_management', null, null, [
                            'action' => $action,
                            'attempted_permissions' => $unauthorized_permissions,
                            'user_permissions_count' => count($user_permissions),
                            'role_name' => $role_name
                        ]);
                        
                        header('Location: ' . $redirect_page);
                        exit;
                    }
                }
                
                // Süper admin özel yetkilerini kontrol et
                $super_admin_only_permissions = ['admin.super_admin.view', 'admin.super_admin.manage'];
                $has_super_admin_perms = array_intersect($permissions_array, $super_admin_only_permissions);
                
                if (!empty($has_super_admin_perms) && !$is_super_admin_user) {
                    $_SESSION['error_message'] = "Süper admin yetkilerini sadece mevcut süper adminler atayabilir.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                    
                    // Güvenlik ihlali audit log
                    audit_log($pdo, $current_user_id, 'unauthorized_super_admin_permission_attempt', 'role_management', null, null, [
                        'action' => $action,
                        'attempted_super_admin_permissions' => $has_super_admin_perms,
                        'role_name' => $role_name
                    ]);
                    
                    header('Location: ' . $redirect_page);
                    exit;
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
                    header('Location: ' . $redirect_page);
                    exit;
                }

                // Güvenli rol oluşturma (transaction ile)
                try {
                    $pdo->beginTransaction();
                    
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
                    
                    // Yetkileri ata (sadece kullanıcının sahip olduğu yetkiler)
                    if (!empty($permissions_array)) {
                        $filtered_permissions = $is_super_admin_user ? $permissions_array : array_intersect($permissions_array, $user_permissions);
                        assign_permissions_to_role($pdo, $role_id, $filtered_permissions, $current_user_id);
                    }
                    
                    $pdo->commit();
                    $result = $role_id;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("Rol oluşturma hatası: " . $e->getMessage());
                    $result = false;
                }

                if ($result) {
                    unset($_SESSION['form_input_role_edit']);
                    $_SESSION['success_message'] = "Rol başarıyla oluşturuldu.";
                    
                    // Audit log
                    audit_log($pdo, $current_user_id, 'role_created', 'role', $result, null, [
                        'role_name' => $role_name,
                        'role_description' => $role_description,
                        'role_color' => $role_color,
                        'role_priority' => $role_priority,
                        'permissions_count' => count($permissions_array),
                        'filtered_permissions_count' => count($filtered_permissions ?? []),
                        'created_by_hierarchy_level' => $user_highest_priority
                    ]);
                } else {
                    $_SESSION['error_message'] = "Rol oluşturulurken bir hata oluştu.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                }

            } elseif ($action === 'update_role') {
                if (!isset($_POST['role_id']) || !is_numeric($_POST['role_id'])) {
                    $_SESSION['error_message'] = "Düzenlenecek rol ID'si geçersiz.";
                    header('Location: ' . $redirect_page);
                    exit;
                }
                $role_id = (int)$_POST['role_id'];

                // Mevcut rol bilgilerini güvenli şekilde çek ve hiyerarşi kontrolü yap
                $get_role_query = "SELECT name, description, color, priority FROM roles WHERE id = :role_id";
                $get_role_params = [':role_id' => $role_id];
                $stmt_get_role = execute_safe_query($pdo, $get_role_query, $get_role_params);
                $current_role_db = $stmt_get_role->fetch();

                if (!$current_role_db) {
                    $_SESSION['error_message'] = "Düzenlenecek rol bulunamadı.";
                    header('Location: ' . $redirect_page);
                    exit;
                }

                // Hiyerarşi kontrolü - Bu rolü düzenleyebilir mi?
                if (!$is_super_admin_user && $current_role_db['priority'] <= $user_highest_priority) {
                    $_SESSION['error_message'] = "Bu rolü düzenleme yetkiniz bulunmamaktadır (hiyerarşi kısıtlaması).";
                    header('Location: ' . $redirect_page);
                    exit;
                }

                // Süper admin korumalı rollerin kontrolü
                $super_admin_protected_roles = ['admin'];
                if (in_array($current_role_db['name'], $super_admin_protected_roles) && !$is_super_admin_user) {
                    $_SESSION['error_message'] = "Bu rol sadece süper adminler tarafından düzenlenebilir.";
                    header('Location: ' . $redirect_page);
                    exit;
                }

                // Temel rollerin adının değiştirilmesini engelle
                if (!is_role_name_editable($current_role_db['name']) && $current_role_db['name'] !== $role_name) {
                    $_SESSION['error_message'] = "Korumalı rollerin adı değiştirilemez.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                    header('Location: ' . $redirect_page);
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
                        header('Location: ' . $redirect_page);
                        exit;
                    }
                }

                // Öncelik değişikliği hiyerarşi kontrolü
                if (!$is_super_admin_user && $role_priority <= $user_highest_priority) {
                    $_SESSION['error_message'] = "Rolün önceliğini kendi hiyerarşi seviyenizden yüksek veya eşit bir değere ayarlayamazsınız.";
                    $_SESSION['form_input_role_edit'] = $_POST;
                    header('Location: ' . $redirect_page);
                    exit;
                }

                // Güvenli rol güncelleme (transaction ile)
                try {
                    $pdo->beginTransaction();
                    
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
                    
                    // Yetkileri güncelle (sadece kullanıcının sahip olduğu yetkiler)
                    $filtered_permissions = $is_super_admin_user ? $permissions_array : array_intersect($permissions_array, $user_permissions);
                    assign_permissions_to_role($pdo, $role_id, $filtered_permissions, $current_user_id);
                    
                    $pdo->commit();
                    $result = true;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log("Rol güncelleme hatası: " . $e->getMessage());
                    $result = false;
                }

                if ($result) {
                    unset($_SESSION['form_input_role_edit']);
                    $_SESSION['success_message'] = "Rol başarıyla güncellendi.";
                    
                    // Audit log
                    audit_log($pdo, $current_user_id, 'role_updated', 'role', $role_id, [
                        'old_name' => $current_role_db['name'],
                        'old_description' => $current_role_db['description'],
                        'old_color' => $current_role_db['color'],
                        'old_priority' => $current_role_db['priority']
                    ], [
                        'new_name' => $role_name,
                        'new_description' => $role_description,
                        'new_color' => $role_color,
                        'new_priority' => $role_priority,
                        'permissions_count' => count($permissions_array),
                        'filtered_permissions_count' => count($filtered_permissions ?? []),
                        'updated_by_hierarchy_level' => $user_highest_priority
                    ]);
                } else {
                    $_SESSION['error_message'] = "Rol güncellenirken bir hata oluştu.";
                    $_SESSION['form_input_role_edit'] = $_POST;
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
            $get_delete_role_query = "SELECT name, description, priority FROM roles WHERE id = :role_id";
            $get_delete_role_params = [':role_id' => $role_id_to_delete];
            $stmt_check_delete = execute_safe_query($pdo, $get_delete_role_query, $get_delete_role_params);
            $role_to_delete = $stmt_check_delete->fetch();

            if (!$role_to_delete) {
                $_SESSION['error_message'] = "Silinecek rol bulunamadı.";
                header('Location: ' . $redirect_page);
                exit;
            }

            // Hiyerarşi kontrolü - Bu rolü silebilir mi?
            if (!$is_super_admin_user && $role_to_delete['priority'] <= $user_highest_priority) {
                $_SESSION['error_message'] = "Bu rolü silme yetkiniz bulunmamaktadır (hiyerarşi kısıtlaması).";
                
                // Audit log - yetkisiz silme girişimi
                audit_log($pdo, $current_user_id, 'unauthorized_role_delete_attempt', 'role', $role_id_to_delete, null, [
                    'role_name' => $role_to_delete['name'],
                    'reason' => 'Hierarchy restriction',
                    'user_hierarchy_level' => $user_highest_priority,
                    'role_priority' => $role_to_delete['priority']
                ]);
                
                header('Location: ' . $redirect_page);
                exit;
            }

            // Temel rolleri silmeyi engelle
            if (!is_role_deletable($role_to_delete['name'])) {
                $_SESSION['error_message'] = "Korumalı roller güvenlik nedeniyle silinemez.";
                
                // Audit log - yetkisiz silme girişimi
                audit_log($pdo, $current_user_id, 'unauthorized_role_delete_attempt', 'role', $role_id_to_delete, null, [
                    'role_name' => $role_to_delete['name'],
                    'reason' => 'Protected role deletion attempt'
                ]);
                
                header('Location: ' . $redirect_page);
                exit;
            }

            // Süper admin korumalı rollerin kontrolü
            $super_admin_protected_roles = ['admin'];
            if (in_array($role_to_delete['name'], $super_admin_protected_roles) && !$is_super_admin_user) {
                $_SESSION['error_message'] = "Bu rol sadece süper adminler tarafından silinebilir.";
                
                // Audit log - yetkisiz silme girişimi
                audit_log($pdo, $current_user_id, 'unauthorized_role_delete_attempt', 'role', $role_id_to_delete, null, [
                    'role_name' => $role_to_delete['name'],
                    'reason' => 'Super admin only role deletion attempt'
                ]);
                
                header('Location: ' . $redirect_page);
                exit;
            }

            // Bu role sahip kullanıcı sayısını kontrol et
            $user_count_query = "SELECT COUNT(*) FROM user_roles WHERE role_id = :role_id";
            $user_count_params = [':role_id' => $role_id_to_delete];
            $stmt_user_count = execute_safe_query($pdo, $user_count_query, $user_count_params);
            $user_count = $stmt_user_count->fetchColumn();

            // Güvenli rol silme (transaction ile)
            try {
                $pdo->beginTransaction();
                
                // Önce rol yetkilerini sil
                $delete_permissions_query = "DELETE FROM role_permissions WHERE role_id = :role_id";
                $delete_permissions_params = [':role_id' => $role_id_to_delete];
                execute_safe_query($pdo, $delete_permissions_query, $delete_permissions_params);
                
                // Kullanıcı-rol ilişkilerini sil
                $delete_user_roles_query = "DELETE FROM user_roles WHERE role_id = :role_id";
                $delete_user_roles_params = [':role_id' => $role_id_to_delete];
                execute_safe_query($pdo, $delete_user_roles_query, $delete_user_roles_params);
                
                // İlgili visibility tabloları temizle
                $visibility_tables = [
                    'event_visibility_roles',
                    'gallery_photo_visibility_roles', 
                    'guide_visibility_roles',
                    'discussion_topic_visibility_roles',
                    'loadout_set_visibility_roles'
                ];
                
                foreach ($visibility_tables as $table) {
                    try {
                        $delete_visibility_query = "DELETE FROM $table WHERE role_id = :role_id";
                        $stmt_visibility = $pdo->prepare($delete_visibility_query);
                        $stmt_visibility->execute([':role_id' => $role_id_to_delete]);
                    } catch (PDOException $e) {
                        // Tablo yoksa devam et
                        if ($e->getCode() !== '42S02') { // Table doesn't exist error code
                            throw $e;
                        }
                    }
                }
                
                // Rolü sil
                $delete_role_query = "DELETE FROM roles WHERE id = :role_id";
                $delete_role_params = [':role_id' => $role_id_to_delete];
                $stmt_delete = execute_safe_query($pdo, $delete_role_query, $delete_role_params);
                
                $pdo->commit();
                $result = $stmt_delete->rowCount() > 0;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Rol silme hatası: " . $e->getMessage());
                $result = false;
            }

            if ($result) {
                $_SESSION['success_message'] = "Rol başarıyla silindi." . ($user_count > 0 ? " ($user_count kullanıcı etkilendi)" : "");
                
                // Audit log
                audit_log($pdo, $current_user_id, 'role_deleted', 'role', $role_id_to_delete, [
                    'role_name' => $role_to_delete['name'],
                    'role_description' => $role_to_delete['description'],
                    'role_priority' => $role_to_delete['priority'],
                    'affected_users' => $user_count,
                    'deleted_by_hierarchy_level' => $user_highest_priority
                ], null);
                
                // Etkilenen kullanıcıların permission cache'ini temizle
                if ($user_count > 0) {
                    $affected_users_query = "SELECT DISTINCT user_id FROM user_roles WHERE role_id = :role_id";
                    $stmt_affected = $pdo->prepare($affected_users_query);
                    $stmt_affected->execute([':role_id' => $role_id_to_delete]);
                    $affected_users = $stmt_affected->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($affected_users as $affected_user_id) {
                        clear_user_permissions_cache($affected_user_id);
                    }
                }
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
        audit_log($pdo, $current_user_id ?? null, 'security_violation', 'role_management', null, null, [
            'action' => $action,
            'error' => $e->getMessage(),
            'post_data_keys' => array_keys($_POST ?? []),
            'user_hierarchy_level' => $user_highest_priority ?? null,
            'is_super_admin' => $is_super_admin_user ?? false,
            'user_permissions_count' => count($user_permissions ?? [])
        ]);
        
    } catch (DatabaseException $e) {
        error_log("Veritabanı hatası - Rol yönetimi (" . $action . "): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında bir veritabanı hatası oluştu. Lütfen tekrar deneyin.";
        
        if (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && in_array($action, ['update_role', 'create_role'])) {
            $_SESSION['form_input_role_edit'] = $_POST;
        }
        
    } catch (Exception $e) {
        error_log("Genel hata - Rol yönetimi (" . $action . "): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında beklenmeyen bir hata oluştu.";
        
        if (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && in_array($action, ['update_role', 'create_role'])) {
            $_SESSION['form_input_role_edit'] = $_POST;
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
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'ip_address' => get_client_ip()
    ]);
    
    header('Location: ' . $redirect_page);
    exit;
}
?>