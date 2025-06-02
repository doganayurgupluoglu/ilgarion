<?php
// src/actions/handle_user_approval.php - Hiyerarşik Güvenlik Kontrolü ile Güncellenmiş

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
if (!check_rate_limit('user_management', 10, 300)) { // 5 dakikada 10 işlem
    $_SESSION['error_message'] = "Çok fazla işlem yapıyorsunuz. Lütfen 5 dakika bekleyin.";
    header('Location: ' . $redirect_page);
    exit;
}

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        audit_log($pdo, $_SESSION['user_id'] ?? null, 'csrf_token_validation_failed', 'user_management', null, null, [
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'post_action' => $_POST['action'] ?? 'unknown'
        ]);
        header('Location: ' . $redirect_page);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $user_id_to_manage = (int) $_POST['user_id'];
    $action = $_POST['action'];
    $current_admin_id = $_SESSION['user_id'];

    // Input validation
    if (!validate_sql_input($action, 'action')) {
        $_SESSION['error_message'] = "Geçersiz işlem türü.";
        audit_log($pdo, $current_admin_id, 'invalid_action_attempt', 'user_management', $user_id_to_manage, null, [
            'action' => $action,
            'reason' => 'Invalid action format'
        ]);
        header('Location: ' . $redirect_page);
        exit;
    }

    // Kullanıcının kendini yönetmesini engelle (belirli işlemler için)
    if ($user_id_to_manage === $current_admin_id && in_array($action, ['reject', 'suspend', 'remove_role'])) {
        if ($action === 'remove_role' && ($_POST['role_to_remove'] ?? '') === 'admin') {
            $_SESSION['error_message'] = "Kendi admin yetkinizi bu arayüzden kaldıramazsınız.";
            audit_log($pdo, $current_admin_id, 'self_admin_removal_attempt', 'user_management', $user_id_to_manage, null, [
                'reason' => 'User tried to remove own admin role'
            ]);
            header('Location: ' . $redirect_page);
            exit;
        } elseif ($action !== 'remove_role') {
            $_SESSION['error_message'] = "Kendi hesabınız üzerinde bu işlemi gerçekleştiremezsiniz.";
            audit_log($pdo, $current_admin_id, 'self_management_attempt', 'user_management', $user_id_to_manage, null, [
                'action' => $action,
                'reason' => 'User tried to manage own account'
            ]);
            header('Location: ' . $redirect_page);
            exit;
        }
    }

    try {
        // Kullanıcının hiyerarşik konumunu ve yetkilerini belirle
        $is_super_admin_user = is_super_admin($pdo);
        $user_roles = get_user_roles($pdo, $current_admin_id);
        $user_highest_priority = 999;

        foreach ($user_roles as $role) {
            if ($role['priority'] < $user_highest_priority) {
                $user_highest_priority = $role['priority'];
            }
        }

        // Hedef kullanıcının bilgilerini ve hiyerarşik konumunu al
        $target_user_query = "
            SELECT u.id, u.username, u.status,
                   MIN(r.priority) as target_user_priority,
                   GROUP_CONCAT(DISTINCT r.name SEPARATOR ',') as target_user_roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = ?
            GROUP BY u.id, u.username, u.status
        ";
        $stmt_target = execute_safe_query($pdo, $target_user_query, [$user_id_to_manage]);
        $target_user = $stmt_target->fetch();

        if (!$target_user) {
            $_SESSION['error_message'] = "Hedef kullanıcı bulunamadı.";
            header('Location: ' . $redirect_page);
            exit;
        }

        $target_user_priority = $target_user['target_user_priority'] ?? 999;
        $target_user_roles = $target_user['target_user_roles'] ? explode(',', $target_user['target_user_roles']) : [];

        // Hiyerarşi kontrolü - Süper admin değilse
        if (!$is_super_admin_user && $user_id_to_manage !== $current_admin_id) {
            if ($target_user_priority <= $user_highest_priority) {
                $_SESSION['error_message'] = "Bu kullanıcıyı yönetme yetkiniz bulunmamaktadır (hiyerarşi kısıtlaması).";
                audit_log($pdo, $current_admin_id, 'hierarchy_violation_attempt', 'user_management', $user_id_to_manage, null, [
                    'action' => $action,
                    'admin_priority' => $user_highest_priority,
                    'target_priority' => $target_user_priority,
                    'reason' => 'Admin tried to manage higher or equal hierarchy user'
                ]);
                header('Location: ' . $redirect_page);
                exit;
            }
        }

        // Action bazında yetki kontrolü
        if (in_array($action, ['approve', 'reject', 'suspend', 'reinstate_approved'])) {
            require_permission($pdo, 'admin.users.edit_status');
        } elseif (in_array($action, ['assign_role', 'remove_role'])) {
            require_permission($pdo, 'admin.users.assign_roles');
        } else {
            throw new SecurityException("Geçersiz kullanıcı yönetimi işlemi: $action");
        }

        $success_message = "";
        $error_message = "";

        switch ($action) {
            case 'approve':
                $new_status = 'approved';
                $stmt_update_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id AND status = 'pending'");
                $stmt_update_status->bindParam(':status', $new_status);
                $stmt_update_status->bindParam(':user_id', $user_id_to_manage, PDO::PARAM_INT);

                if ($stmt_update_status->execute()) {
                    if ($stmt_update_status->rowCount() > 0) {
                        // Varsayılan olarak "Dış üye" rolünü ata (eğer henüz rolü yoksa)
                        $stmt_check_roles = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ?");
                        $stmt_check_roles->execute([$user_id_to_manage]);

                        if ($stmt_check_roles->fetchColumn() == 0) {
                            $stmt_dis_uye_role = $pdo->prepare("SELECT id FROM roles WHERE name = 'dis_uye'");
                            $stmt_dis_uye_role->execute();
                            $default_role_id = $stmt_dis_uye_role->fetchColumn();

                            if ($default_role_id) {
                                $stmt_assign_default_role = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                                $stmt_assign_default_role->execute([$user_id_to_manage, $default_role_id]);
                            }
                        }

                        $success_message = "Kullanıcı başarıyla onaylandı.";

                        // Audit log
                        audit_log(
                            $pdo,
                            $current_admin_id,
                            'user_approved',
                            'user',
                            $user_id_to_manage,
                            ['old_status' => 'pending'],
                            ['new_status' => 'approved', 'target_username' => $target_user['username']]
                        );
                    } else {
                        $error_message = "Kullanıcı zaten onaylanmış veya onay bekleyen durumda değil.";
                    }
                } else {
                    $error_message = "Kullanıcı onaylanırken bir hata oluştu.";
                }
                break;

            case 'reject':
                $new_status = 'rejected';
                $stmt_update_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id");
                $stmt_update_status->bindParam(':status', $new_status);
                $stmt_update_status->bindParam(':user_id', $user_id_to_manage, PDO::PARAM_INT);

                if ($stmt_update_status->execute()) {
                    $success_message = "Kullanıcı başarıyla reddedildi.";

                    // Audit log
                    audit_log(
                        $pdo,
                        $current_admin_id,
                        'user_rejected',
                        'user',
                        $user_id_to_manage,
                        ['old_status' => $target_user['status']],
                        ['new_status' => 'rejected', 'target_username' => $target_user['username']]
                    );
                } else {
                    $error_message = "Kullanıcı reddedilirken bir hata oluştu.";
                }
                break;

            case 'suspend':
                $new_status = 'suspended';
                $stmt_update_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id");
                $stmt_update_status->bindParam(':status', $new_status);
                $stmt_update_status->bindParam(':user_id', $user_id_to_manage, PDO::PARAM_INT);

                if ($stmt_update_status->execute()) {
                    $success_message = "Kullanıcı başarıyla askıya alındı.";

                    // Audit log
                    audit_log(
                        $pdo,
                        $current_admin_id,
                        'user_suspended',
                        'user',
                        $user_id_to_manage,
                        ['old_status' => $target_user['status']],
                        ['new_status' => 'suspended', 'target_username' => $target_user['username']]
                    );
                } else {
                    $error_message = "Kullanıcı askıya alınırken bir hata oluştu.";
                }
                break;

            case 'reinstate_approved':
                $new_status = 'approved';
                $stmt_update_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id AND (status = 'suspended' OR status = 'rejected')");
                $stmt_update_status->bindParam(':status', $new_status);
                $stmt_update_status->bindParam(':user_id', $user_id_to_manage, PDO::PARAM_INT);

                if ($stmt_update_status->execute()) {
                    if ($stmt_update_status->rowCount() > 0) {
                        $success_message = "Kullanıcı tekrar onaylı duruma getirildi.";

                        // Audit log
                        audit_log(
                            $pdo,
                            $current_admin_id,
                            'user_reinstated',
                            'user',
                            $user_id_to_manage,
                            ['old_status' => $target_user['status']],
                            ['new_status' => 'approved', 'target_username' => $target_user['username']]
                        );
                    } else {
                        $error_message = "Kullanıcı zaten onaylı veya uygun durumda değil.";
                    }
                } else {
                    $error_message = "Kullanıcı durumu güncellenirken bir hata oluştu.";
                }
                break;

           case 'assign_role':
    if (isset($_POST['role_to_assign'])) {
        $role_name_to_assign = trim($_POST['role_to_assign']);
        
        // Input validation
        if (!validate_sql_input($role_name_to_assign, 'role_name')) {
            throw new SecurityException("Invalid role name format: $role_name_to_assign");
        }
        
        $stmt_role_id = $pdo->prepare("SELECT id, priority FROM roles WHERE name = ?");
        $stmt_role_id->execute([$role_name_to_assign]);
        $role_data = $stmt_role_id->fetch();

        if ($role_data) {
            $role_id = $role_data['id'];
            $role_priority = $role_data['priority'];
            
            // Hiyerarşi kontrolü - Atanacak rol admin'in seviyesinden düşük olmalı
            if (!$is_super_admin_user && $role_priority <= $user_highest_priority) {
                throw new SecurityException("Cannot assign role with higher or equal priority");
            }

            // Admin rolünü atama özel kontrolü - SADECE ADMIN KORUNACAK
            if ($role_name_to_assign === 'admin' && !$is_super_admin_user) {
                $_SESSION['error_message'] = "Admin rolü sadece süper adminler tarafından atanabilir.";
                audit_log($pdo, $current_admin_id, 'unauthorized_admin_role_assignment_attempt', 'user_management', $user_id_to_manage, null, [
                    'role_name' => $role_name_to_assign,
                    'reason' => 'Non-super admin tried to assign admin role'
                ]);
                header('Location: ' . $redirect_page);
                exit;
            }

            $stmt_check_existing_role = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
            $stmt_check_existing_role->execute([$user_id_to_manage, $role_id]);
            
            if ($stmt_check_existing_role->fetchColumn() == 0) {
                if (assign_role($pdo, $user_id_to_manage, $role_id, $current_admin_id)) {
                    $success_message = "'".htmlspecialchars($role_name_to_assign)."' rolü kullanıcıya atandı.";
                } else {
                    $error_message = "Rol atanırken bir hata oluştu.";
                }
            } else {
                $error_message = "Kullanıcı zaten '".htmlspecialchars($role_name_to_assign)."' rolüne sahip.";
            }
        } else {
            $error_message = "Geçersiz rol adı: ".htmlspecialchars($role_name_to_assign);
        }
    } else {
        $error_message = "Atanacak rol belirtilmedi.";
    }
    break;
            case 'remove_role':
                if (isset($_POST['role_to_remove'])) {
                    $role_name_to_remove = trim($_POST['role_to_remove']);

                    // Input validation
                    if (!validate_sql_input($role_name_to_remove, 'role_name')) {
                        throw new SecurityException("Invalid role name format: $role_name_to_remove");
                    }

                    $stmt_role_id = $pdo->prepare("SELECT id, priority FROM roles WHERE name = ?");
                    $stmt_role_id->execute([$role_name_to_remove]);
                    $role_data = $stmt_role_id->fetch();

                    if ($role_data) {
                        $role_id = $role_data['id'];
                        $role_priority = $role_data['priority'];

                        // Hiyerarşi kontrolü - Kaldırılacak rol admin'in seviyesinden düşük olmalı
                        if (!$is_super_admin_user && $role_priority <= $user_highest_priority) {
                            throw new SecurityException("Cannot remove role with higher or equal priority");
                        }

                        // Admin rolünü kaldırma özel kontrolü - SADECE ADMIN KORUNACAK
                        if ($role_name_to_remove === 'admin' && !$is_super_admin_user) {
                            $_SESSION['error_message'] = "Admin rolü sadece süper adminler tarafından kaldırılabilir.";
                            audit_log($pdo, $current_admin_id, 'unauthorized_admin_role_removal_attempt', 'user_management', $user_id_to_manage, null, [
                                'role_name' => $role_name_to_remove,
                                'reason' => 'Non-super admin tried to remove admin role'
                            ]);
                            header('Location: ' . $redirect_page);
                            exit;
                        }

                        if (remove_role($pdo, $user_id_to_manage, $role_id, $current_admin_id)) {
                            $success_message = "'" . htmlspecialchars($role_name_to_remove) . "' rolü kullanıcıdan kaldırıldı.";
                        } else {
                            $error_message = "Rol kaldırılırken bir hata oluştu.";
                        }
                    } else {
                        $error_message = "Geçersiz rol adı: " . htmlspecialchars($role_name_to_remove);
                    }
                } else {
                    $error_message = "Kaldırılacak rol belirtilmedi.";
                }
                break;

            default:
                throw new SecurityException("Geçersiz kullanıcı işlemi: $action");
        }

        if (!empty($success_message)) {
            $_SESSION['success_message'] = $success_message;
        }
        if (!empty($error_message)) {
            $_SESSION['error_message'] = $error_message;
        }

    } catch (SecurityException $e) {
        error_log("Güvenlik ihlali - Kullanıcı yönetimi ($action): " . $e->getMessage());
        $_SESSION['error_message'] = "Güvenlik ihlali tespit edildi. İşlem engellendi.";

        // Güvenlik ihlali audit log
        audit_log($pdo, $current_admin_id, 'security_violation', 'user_management', $user_id_to_manage, null, [
            'action' => $action,
            'error' => $e->getMessage(),
            'admin_priority' => $user_highest_priority ?? null,
            'target_priority' => $target_user_priority ?? null,
            'target_username' => $target_user['username'] ?? 'unknown'
        ]);

    } catch (DatabaseException $e) {
        error_log("Veritabanı hatası - Kullanıcı yönetimi ($action): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında bir veritabanı hatası oluştu.";

    } catch (PDOException $e) {
        error_log("Kullanıcı yönetimi işlemi hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında bir veritabanı hatası oluştu.";

    } catch (Exception $e) {
        error_log("Genel hata - Kullanıcı yönetimi ($action): " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında beklenmeyen bir hata oluştu.";
    }

    header('Location: ' . $redirect_page);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";

    // Audit log - geçersiz istek
    audit_log($pdo, $_SESSION['user_id'] ?? null, 'invalid_request', 'user_management', null, null, [
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