<?php
// src/actions/handle_user_roles_update.php - Hiyerarşik Güvenlik Kontrolü ile Güncellenmiş

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

$baseUrl = get_auth_base_url();
$redirect_page = $baseUrl . '/admin/users.php';

// Rate limiting kontrolü
if (!check_rate_limit('user_roles_update', 5, 300)) { // 5 dakikada 5 işlem
    $_SESSION['error_message'] = "Çok fazla rol güncelleme işlemi yapıyorsunuz. Lütfen 5 dakika bekleyin.";
    header('Location: ' . $redirect_page);
    exit;
}

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        audit_log($pdo, $_SESSION['user_id'] ?? null, 'csrf_token_validation_failed', 'user_roles_update', null, null, [
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        header('Location: ' . $redirect_page);
        exit;
    }
}

// Yetki kontrolü
try {
    if (isset($pdo)) {
        require_permission($pdo, 'admin.users.assign_roles');
    } else {
        $_SESSION['error_message'] = "Veritabanı bağlantısı yapılandırılamadı.";
        error_log("handle_user_roles_update.php: PDO nesnesi bulunamadı.");
        header('Location: ' . $redirect_page);
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Yetki kontrolü hatası: " . $e->getMessage();
    header('Location: ' . $redirect_page);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id_to_update'])) {
    $user_id_to_update = (int)$_POST['user_id_to_update'];
    $assigned_role_ids_from_form = isset($_POST['assigned_roles']) && is_array($_POST['assigned_roles'])
                                  ? array_map('intval', $_POST['assigned_roles'])
                                  : [];

    $current_admin_id = $_SESSION['user_id'];
    $is_super_admin_user = is_super_admin($pdo);

    try {
        // Kullanıcının hiyerarşik konumunu belirle
        $admin_roles = get_user_roles($pdo, $current_admin_id);
        $admin_highest_priority = 999;
        
        foreach ($admin_roles as $role) {
            if ($role['priority'] < $admin_highest_priority) {
                $admin_highest_priority = $role['priority'];
            }
        }

        // Hedef kullanıcının mevcut bilgilerini al
        $target_user_query = "
            SELECT u.id, u.username, u.status,
                   MIN(r.priority) as current_highest_priority,
                   GROUP_CONCAT(DISTINCT r.name SEPARATOR ',') as current_roles,
                   GROUP_CONCAT(DISTINCT r.id SEPARATOR ',') as current_role_ids
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = ?
            GROUP BY u.id, u.username, u.status
        ";
        $stmt_target = execute_safe_query($pdo, $target_user_query, [$user_id_to_update]);
        $target_user = $stmt_target->fetch();

        if (!$target_user) {
            $_SESSION['error_message'] = "Hedef kullanıcı bulunamadı.";
            header('Location: ' . $redirect_page);
            exit;
        }

        $target_current_priority = $target_user['current_highest_priority'] ?? 999;
        $current_role_ids = $target_user['current_role_ids'] ? array_map('intval', explode(',', $target_user['current_role_ids'])) : [];

        // Hiyerarşi kontrolü - Kendini düzenleyemez
        if ($user_id_to_update == $current_admin_id) {
            $_SESSION['error_message'] = "Kendi rollerinizi bu arayüzden düzenleyemezsiniz.";
            audit_log($pdo, $current_admin_id, 'self_role_edit_attempt', 'user_roles', $user_id_to_update, null, [
                'reason' => 'User tried to edit own roles'
            ]);
            header('Location: ' . $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update);
            exit;
        }

        // Hiyerarşi kontrolü - Üst seviye kullanıcıyı düzenleyemez
        if (!$is_super_admin_user && $target_current_priority <= $admin_highest_priority) {
            $_SESSION['error_message'] = "Bu kullanıcıyı yönetme yetkiniz bulunmamaktadır (hiyerarşi kısıtlaması).";
            audit_log($pdo, $current_admin_id, 'hierarchy_violation_attempt', 'user_roles', $user_id_to_update, null, [
                'admin_priority' => $admin_highest_priority,
                'target_priority' => $target_current_priority,
                'reason' => 'Admin tried to manage higher hierarchy user'
            ]);
            header('Location: ' . $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update);
            exit;
        }

        // Kullanıcının yönetebileceği rolleri belirle
        $manageable_roles_query = $is_super_admin_user 
            ? "SELECT id, name, priority FROM roles" 
            : "SELECT id, name, priority FROM roles WHERE priority > ?";
        
        $manageable_params = $is_super_admin_user ? [] : [$admin_highest_priority];
        $stmt_manageable = execute_safe_query($pdo, $manageable_roles_query, $manageable_params);
        $manageable_roles = $stmt_manageable->fetchAll(PDO::FETCH_ASSOC);
        $manageable_role_ids = array_column($manageable_roles, 'id');

        // Güvenlik kontrolü: Formdan gelen rollerin yönetilebilir olup olmadığını kontrol et
        $invalid_roles = array_diff($assigned_role_ids_from_form, $manageable_role_ids);
        if (!empty($invalid_roles)) {
            $_SESSION['error_message'] = "Yönetme yetkiniz olmayan roller tespit edildi.";
            audit_log($pdo, $current_admin_id, 'unauthorized_role_assignment_attempt', 'user_roles', $user_id_to_update, null, [
                'invalid_role_ids' => $invalid_roles,
                'attempted_roles' => $assigned_role_ids_from_form,
                'manageable_roles' => $manageable_role_ids
            ]);
            header('Location: ' . $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update);
            exit;
        }

        // Korumalı rolleri belirle ve formdan gelenlere ekle
        $protected_roles = [];
        foreach ($current_role_ids as $current_role_id) {
            // Bu rolün priority'sini kontrol et
            $role_priority_query = "SELECT priority FROM roles WHERE id = ?";
            $stmt_priority = execute_safe_query($pdo, $role_priority_query, [$current_role_id]);
            $role_priority = $stmt_priority->fetchColumn();
            
            // Eğer admin bu rolü yönetemiyorsa korumalı olarak işaretle
            if (!$is_super_admin_user && $role_priority <= $admin_highest_priority) {
                $protected_roles[] = $current_role_id;
            }
        }

        // Korumalı rolleri final rol listesine ekle
        $final_role_ids = array_unique(array_merge($assigned_role_ids_from_form, $protected_roles));

        // ⚠️ SADECE ADMIN ROLÜ KORUNACAK - DİĞER ROLLER ARTIK KORUNMUYOR
        // Admin rolü kontrolü (özel durum)
        $admin_role_query = "SELECT id FROM roles WHERE name = 'admin'";
        $stmt_admin_role = execute_safe_query($pdo, $admin_role_query);
        $admin_role_id = $stmt_admin_role->fetchColumn();

        $admin_role_warning_messages = [];

        if ($admin_role_id && in_array($admin_role_id, $current_role_ids)) {
            // Kullanıcının admin rolü var, kaldırılmaya çalışılıyor mu?
            if (!in_array($admin_role_id, $assigned_role_ids_from_form)) {
                // Admin rolünü kaldırma işlemi - sadece süper adminler yapabilir
                if (!$is_super_admin_user) {
                    // Admin rolünü korumalı olarak işaretle - süper admin değilse kaldıramaz
                    if (!in_array($admin_role_id, $final_role_ids)) {
                        $final_role_ids[] = $admin_role_id;
                    }
                    
                    // Uyarı mesajı ekle
                    $admin_role_warning_messages[] = "Admin rolü sadece süper adminler tarafından kaldırılabilir. Bu rol korundu.";
                    
                    // Audit log - yetkisiz admin rol kaldırma girişimi
                    audit_log($pdo, $current_admin_id, 'unauthorized_admin_role_removal_attempt', 'user_roles_update', $user_id_to_update, null, [
                        'target_user_id' => $user_id_to_update,
                        'attempted_by_non_super_admin' => true,
                        'admin_role_protected' => true
                    ]);
                }
                // Süper admin ise admin rolünü kaldırabilir - özel işlem gerekmez
            }
        }

        // Admin rolü atama kontrolü (yeni ekleme)
        if ($admin_role_id && in_array($admin_role_id, $assigned_role_ids_from_form)) {
            // Kullanıcıya admin rolü atanmaya çalışılıyor
            if (!in_array($admin_role_id, $current_role_ids)) {
                // Yeni admin rolü atama - sadece süper adminler yapabilir
                if (!$is_super_admin_user) {
                    // Admin rolünü listeden çıkar - süper admin değilse atayamaz
                    $final_role_ids = array_diff($final_role_ids, [$admin_role_id]);
                    
                    // Uyarı mesajı ekle
                    $admin_role_warning_messages[] = "Admin rolü sadece süper adminler tarafından atanabilir. Bu rol atanmadı.";
                    
                    // Audit log - yetkisiz admin rol atama girişimi
                    audit_log($pdo, $current_admin_id, 'unauthorized_admin_role_assignment_attempt', 'user_roles_update', $user_id_to_update, null, [
                        'target_user_id' => $user_id_to_update,
                        'attempted_by_non_super_admin' => true,
                        'admin_role_blocked' => true
                    ]);
                }
                // Süper admin ise admin rolünü atayabilir - özel işlem gerekmez
            }
        }

        // Transaction başlat
        $pdo->beginTransaction();

        try {
            // Değişiklikleri audit için kaydet
            $old_roles_query = "
                SELECT p.permission_key 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                JOIN role_permissions rp ON r.id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ?
            ";
            $stmt_old_roles = execute_safe_query($pdo, $old_roles_query, [$user_id_to_update]);
            $old_permissions = $stmt_old_roles->fetchAll(PDO::FETCH_COLUMN);

            // Mevcut tüm rolleri sil (korumalı olanlar dışında)
            if (!empty($protected_roles)) {
                // Sadece yönetilebilir rolleri sil
                $delete_manageable_query = "DELETE FROM user_roles WHERE user_id = ? AND role_id NOT IN (" . 
                    str_repeat('?,', count($protected_roles) - 1) . "?)";
                $delete_params = array_merge([$user_id_to_update], $protected_roles);
                execute_safe_query($pdo, $delete_manageable_query, $delete_params);
            } else {
                // Tüm rolleri sil
                $delete_all_query = "DELETE FROM user_roles WHERE user_id = ?";
                execute_safe_query($pdo, $delete_all_query, [$user_id_to_update]);
            }

            // Yeni rolleri ekle (korumalı olanlar zaten var, duplicat'ı engelle)
            if (!empty($final_role_ids)) {
                foreach ($final_role_ids as $role_id) {
                    // Rolün var olup olmadığını kontrol et
                    $role_exists_query = "SELECT id FROM roles WHERE id = ?";
                    $stmt_role_exists = execute_safe_query($pdo, $role_exists_query, [$role_id]);
                    
                    if ($stmt_role_exists->fetch()) {
                        // Bu rolün zaten atanıp atanmadığını kontrol et
                        $role_assigned_query = "SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?";
                        $stmt_role_assigned = execute_safe_query($pdo, $role_assigned_query, [$user_id_to_update, $role_id]);
                        
                        if ($stmt_role_assigned->fetchColumn() == 0) {
                            $insert_role_query = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                            execute_safe_query($pdo, $insert_role_query, [$user_id_to_update, $role_id]);
                        }
                    } else {
                        error_log("Geçersiz rol ID ($role_id) kullanıcıya ($user_id_to_update) atanmaya çalışıldı.");
                    }
                }
            }

            $pdo->commit();

            // Kullanıcının permission cache'ini temizle
            clear_user_permissions_cache($user_id_to_update);

            // Başarı mesajı oluştur
            $success_message = "Kullanıcının rolleri başarıyla güncellendi.";
            
            // Admin rolü uyarı mesajlarını ekle
            if (!empty($admin_role_warning_messages)) {
                $_SESSION['warning_message'] = implode(' ', $admin_role_warning_messages);
            }
            
            $_SESSION['success_message'] = $success_message;

            // Yeni yetkiler için audit log
            $new_roles_query = "
                SELECT p.permission_key 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                JOIN role_permissions rp ON r.id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ?
            ";
            $stmt_new_roles = execute_safe_query($pdo, $new_roles_query, [$user_id_to_update]);
            $new_permissions = $stmt_new_roles->fetchAll(PDO::FETCH_COLUMN);

            // Audit log
            audit_log($pdo, $current_admin_id, 'user_roles_updated', 'user', $user_id_to_update, 
                [
                    'old_permissions_count' => count($old_permissions),
                    'old_role_ids' => $current_role_ids,
                    'target_username' => $target_user['username']
                ],
                [
                    'new_permissions_count' => count($new_permissions),
                    'new_role_ids' => $final_role_ids,
                    'protected_roles_count' => count($protected_roles),
                    'manageable_roles_assigned' => count($assigned_role_ids_from_form),
                    'admin_hierarchy_level' => $admin_highest_priority,
                    'admin_role_warnings' => $admin_role_warning_messages
                ]
            );

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (SecurityException $e) {
        error_log("Güvenlik ihlali - Kullanıcı rolleri güncelleme: " . $e->getMessage());
        $_SESSION['error_message'] = "Güvenlik ihlali tespit edildi. İşlem engellendi.";
        $redirect_page = $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update;
        
        // Güvenlik ihlali audit log
        audit_log($pdo, $current_admin_id, 'security_violation', 'user_roles_update', $user_id_to_update, null, [
            'error' => $e->getMessage(),
            'attempted_roles' => $assigned_role_ids_from_form ?? [],
            'admin_priority' => $admin_highest_priority ?? null
        ]);
        
    } catch (DatabaseException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Veritabanı hatası - Kullanıcı rolleri güncelleme: " . $e->getMessage());
        $_SESSION['error_message'] = "Kullanıcı rolleri güncellenirken bir veritabanı hatası oluştu.";
        $redirect_page = $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Kullanıcı rolleri güncelleme hatası (Kullanıcı ID: $user_id_to_update): " . $e->getMessage());
        $_SESSION['error_message'] = "Kullanıcı rolleri güncellenirken bir veritabanı hatası oluştu.";
        $redirect_page = $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Genel hata - Kullanıcı rolleri güncelleme: " . $e->getMessage());
        $_SESSION['error_message'] = "Kullanıcı rolleri güncellenirken beklenmeyen bir hata oluştu.";
        $redirect_page = $baseUrl . '/admin/edit_user_roles.php?user_id=' . $user_id_to_update;
    }

    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    
    // Audit log - geçersiz istek
    audit_log($pdo, $_SESSION['user_id'] ?? null, 'invalid_request', 'user_roles_update', null, null, [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'ip_address' => get_client_ip(),
        'post_data_keys' => array_keys($_POST ?? [])
    ]);
    
    header('Location: ' . $redirect_page);
    exit;
}
?>