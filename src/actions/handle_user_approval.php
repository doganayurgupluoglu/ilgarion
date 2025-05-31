<?php
// src/actions/handle_user_approval.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php'; // Auth fonksiyonları
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); // Genel admin kontrolü yerine action bazlı kontrol yapılacak

$baseUrl = get_auth_base_url(); // Yönlendirmeler için /public

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $user_id_to_manage = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $current_admin_id = $_SESSION['user_id'];

    if ($user_id_to_manage === $current_admin_id && in_array($action, ['reject', 'suspend', 'remove_role'])) {
        if ($action === 'remove_role' && ($_POST['role_to_remove'] ?? '') === 'admin') {
            $_SESSION['error_message'] = "Kendi admin yetkinizi bu arayüzden kaldıramazsınız.";
            header('Location: ' . $baseUrl . '/admin/users.php');
            exit;
        } elseif ($action !== 'remove_role') { // reject, suspend için
             $_SESSION['error_message'] = "Kendi hesabınız üzerinde bu işlemi gerçekleştiremezsiniz.";
             header('Location: ' . $baseUrl . '/admin/users.php');
             exit;
        }
    }

    $new_status = null;
    // $new_role artık doğrudan kullanılmayacak, rol atama/kaldırma işlemleri yapılacak.
    $success_message = "";
    $error_message = "";

    try {
        // Yetki kontrolünü action'a göre yap
        if (in_array($action, ['approve', 'reject', 'suspend', 'reinstate_approved'])) {
            require_permission($pdo, 'admin.users.edit_status');
        } elseif (in_array($action, ['assign_role', 'remove_role'])) {
            require_permission($pdo, 'admin.users.assign_roles');
        } else {
            throw new Exception("Geçersiz kullanıcı yönetimi işlemi.");
        }

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
                            // "dis_uye" rolünün ID'sini dinamik olarak alalım
                            $stmt_dis_uye_role = $pdo->prepare("SELECT id FROM roles WHERE name = 'dis_uye'");
                            $stmt_dis_uye_role->execute();
                            $default_role_id_on_approve = $stmt_dis_uye_role->fetchColumn();
                            if ($default_role_id_on_approve) {
                                $stmt_assign_default_role = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                                $stmt_assign_default_role->execute([$user_id_to_manage, $default_role_id_on_approve]);
                            }
                        }
                        $success_message = "Kullanıcı başarıyla onaylandı.";
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
                } else { $error_message = "Kullanıcı reddedilirken bir hata oluştu.";}
                break;

            case 'suspend':
                $new_status = 'suspended';
                $stmt_update_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id");
                $stmt_update_status->bindParam(':status', $new_status);
                $stmt_update_status->bindParam(':user_id', $user_id_to_manage, PDO::PARAM_INT);
                 if ($stmt_update_status->execute()) {
                    $success_message = "Kullanıcı başarıyla askıya alındı.";
                } else { $error_message = "Kullanıcı askıya alınırken bir hata oluştu.";}
                break;

            case 'reinstate_approved':
                $new_status = 'approved';
                $stmt_update_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :user_id AND (status = 'suspended' OR status = 'rejected')");
                $stmt_update_status->bindParam(':status', $new_status);
                $stmt_update_status->bindParam(':user_id', $user_id_to_manage, PDO::PARAM_INT);
                if ($stmt_update_status->execute()) {
                     if ($stmt_update_status->rowCount() > 0) {
                        $success_message = "Kullanıcı tekrar onaylı duruma getirildi.";
                    } else {
                        $error_message = "Kullanıcı zaten onaylı veya uygun durumda değil.";
                    }
                } else { $error_message = "Kullanıcı durumu güncellenirken bir hata oluştu.";}
                break;
            
            case 'assign_role':
                if (isset($_POST['role_to_assign'])) {
                    $role_name_to_assign = trim($_POST['role_to_assign']);
                    
                    $stmt_role_id = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
                    $stmt_role_id->execute([$role_name_to_assign]);
                    $role_id = $stmt_role_id->fetchColumn();

                    if ($role_id) {
                        $stmt_check_existing_role = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
                        $stmt_check_existing_role->execute([$user_id_to_manage, $role_id]);
                        if ($stmt_check_existing_role->fetchColumn() == 0) {
                            $stmt_insert_role = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                            if ($stmt_insert_role->execute([$user_id_to_manage, $role_id])) {
                                $success_message = "'".htmlspecialchars($role_name_to_assign)."' rolü kullanıcıya atandı.";
                            } else { $error_message = "Rol atanırken veritabanı hatası."; }
                        } else {
                            $error_message = "Kullanıcı zaten '".htmlspecialchars($role_name_to_assign)."' rolüne sahip.";
                        }
                    } else {
                        $error_message = "Geçersiz rol adı: ".htmlspecialchars($role_name_to_assign);
                    }
                } else { $error_message = "Atanacak rol belirtilmedi."; }
                break;

            case 'remove_role':
                if (isset($_POST['role_to_remove'])) {
                    $role_name_to_remove = trim($_POST['role_to_remove']);
                    
                    $stmt_role_id = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
                    $stmt_role_id->execute([$role_name_to_remove]);
                    $role_id = $stmt_role_id->fetchColumn();

                    if ($role_id) {
                        $stmt_delete_role = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
                        if ($stmt_delete_role->execute([$user_id_to_manage, $role_id])) {
                             if ($stmt_delete_role->rowCount() > 0) {
                                $success_message = "'".htmlspecialchars($role_name_to_remove)."' rolü kullanıcıdan kaldırıldı.";
                            } else {
                                $error_message = "Kullanıcının zaten '".htmlspecialchars($role_name_to_remove)."' rolü yoktu veya rol kaldırılamadı.";
                            }
                        } else { $error_message = "Rol kaldırılırken veritabanı hatası."; }
                    } else {
                        $error_message = "Geçersiz rol adı: ".htmlspecialchars($role_name_to_remove);
                    }
                } else { $error_message = "Kaldırılacak rol belirtilmedi."; }
                break;

            default: // Bu case artık switch öncesi kontrolle gereksiz kalıyor ama zararı yok.
                $error_message = "Geçersiz kullanıcı işlemi.";
                break;
        }

        if (!empty($success_message)) {
            $_SESSION['success_message'] = $success_message;
        }
        if (!empty($error_message)) {
            $_SESSION['error_message'] = $error_message;
        }

    } catch (PDOException $e) {
        error_log("Kullanıcı yönetimi işlemi hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında bir veritabanı hatası oluştu.";
    }

    header('Location: ' . $baseUrl . '/admin/users.php');
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: ' . $baseUrl . '/admin/users.php');
    exit;
}
?>
