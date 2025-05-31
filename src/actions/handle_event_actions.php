<?php
// src/actions/handle_event_actions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

require_approved_user(); // En azından onaylı kullanıcı olmalı

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];
$filter_status_from_request = $_POST['filter_status'] ?? $_GET['filter_status'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id'], $_POST['action'])) {
    $_SESSION['error_message'] = "Geçersiz istek veya eksik parametreler.";
    header('Location: ' . $baseUrl . '/events.php');
    exit;
}

$event_id = (int)$_POST['event_id'];
$action = $_POST['action'];

$redirect_url = $baseUrl . '/event_detail.php?id=' . $event_id;
$admin_redirect_url = $baseUrl . '/admin/events.php?status=' . urlencode($filter_status_from_request);

$event_data_for_auth = null;
try {
    $stmt_check = $pdo->prepare("SELECT created_by_user_id, image_path_1, image_path_2, image_path_3 FROM events WHERE id = ?");
    $stmt_check->execute([$event_id]);
    $event_data_for_auth = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$event_data_for_auth && $action !== 'delete_event') {
        $_SESSION['error_message'] = "İşlem yapılacak etkinlik bulunamadı.";
        header('Location: ' . (is_user_admin() ? $admin_redirect_url : $baseUrl . '/events.php'));
        exit;
    }
} catch (PDOException $e) {
    error_log("Event action - etkinlik çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "İşlem öncesi etkinlik bilgileri alınırken bir sorun oluştu.";
    header('Location: ' . (is_user_admin() ? $admin_redirect_url : $baseUrl . '/events.php'));
    exit;
}

$is_owner = ($event_data_for_auth && $event_data_for_auth['created_by_user_id'] == $loggedInUserId);
$sql = "";
$params = [];
$success_message = "";
$can_perform_action = false;

switch ($action) {
    case 'cancel_event':
    case 'activate_event': // İptal ve aktif etme için benzer yetki kontrolü
        $required_permission_edit_own = 'event.edit_own'; // Kendi etkinliğini yönetme
        $required_permission_edit_all = 'event.edit_all'; // Tüm etkinlikleri yönetme (admin/moderatör için)

        if (has_permission($pdo, $required_permission_edit_all, $loggedInUserId) || ($is_owner && has_permission($pdo, $required_permission_edit_own, $loggedInUserId))) {
            $can_perform_action = true;
            $new_status = ($action === 'cancel_event') ? 'cancelled' : 'active';
            $current_status_check = ($action === 'cancel_event') ? 'active' : ['cancelled', 'past']; // Dizi olarak kontrol

            $sql_where_status = is_array($current_status_check)
                ? "status IN ('" . implode("','", $current_status_check) . "')"
                : "status = :current_status_val";

            $sql = "UPDATE events SET status = :new_status WHERE id = :event_id AND $sql_where_status";
            $params = [':new_status' => $new_status, ':event_id' => $event_id];
            if (!is_array($current_status_check)) {
                $params[':current_status_val'] = $current_status_check;
            }

            $success_message = ($action === 'cancel_event') ? "Etkinlik başarıyla iptal edildi." : "Etkinlik başarıyla aktif edildi.";
        } else {
            $_SESSION['error_message'] = "Bu etkinliği yönetme yetkiniz yok.";
        }
        break;

    case 'delete_event':
        if (has_permission($pdo, 'event.delete_all', $loggedInUserId)) { // Sadece 'event.delete_all' yetkisine sahip olanlar silebilir
            $can_perform_action = true;
            if ($event_data_for_auth) {
                $images_to_delete = array_filter([$event_data_for_auth['image_path_1'], $event_data_for_auth['image_path_2'], $event_data_for_auth['image_path_3']]);
                foreach ($images_to_delete as $image_path) {
                    if (strpos($image_path, 'uploads/event_images/') === 0) {
                        $full_image_path = BASE_PATH . '/public/' . $image_path;
                        if (file_exists($full_image_path)) {
                            if(!unlink($full_image_path)){
                                 error_log("Etkinlik fotoğrafı silinemedi (delete_event): ".$full_image_path);
                            }
                        }
                    }
                }
            }
            $sql = "DELETE FROM events WHERE id = :event_id";
            $params = [':event_id' => $event_id];
            $success_message = "Etkinlik kalıcı olarak başarıyla silindi.";
            $redirect_url = $admin_redirect_url; // Silme sonrası admin listesine dön
        } else {
            $_SESSION['error_message'] = "Bu etkinliği silme yetkiniz yok.";
        }
        break;

    default:
        $_SESSION['error_message'] = "Geçersiz işlem türü.";
        break;
}

if (!$can_perform_action && !isset($_SESSION['error_message'])) {
    $_SESSION['error_message'] = "Bu işlem için yetkiniz bulunmamaktadır.";
}

if ($can_perform_action && !empty($sql)) {
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = $success_message;
            } else {
                $_SESSION['info_message'] = "İşlem yapıldı ancak hiçbir kayıt etkilenmedi.";
            }
        } else {
            $_SESSION['error_message'] = "İşlem sırasında bir veritabanı hatası oluştu.";
            error_log("Event action execute false. SQL: $sql Params: " . print_r($params, true) . " ErrorInfo: " . print_r($stmt->errorInfo(), true));
        }
    } catch (PDOException $e) {
        error_log("Event action veritabanı hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında kritik bir veritabanı hatası oluştu.";
    }
}

if (is_user_admin() && (strpos($_SERVER['HTTP_REFERER'] ?? '', '/admin/events.php') !== false || $action === 'delete_event')) {
    header('Location: ' . $admin_redirect_url);
} else {
    header('Location: ' . $redirect_url);
}
exit;
