<?php
// public/actions/mark_notification_read.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_log("--- mark_notification_read.php ACTION STARTED ---");

// Veritabanı bağlantısını ve BASE_PATH'i yükle
require_once dirname(__DIR__, 2) . '/src/config/database.php'; // $pdo burada tanımlanmalı
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/notification_functions.php';

$response = ['success' => false, 'error' => 'Bilinmeyen bir hata oluştu.', 'marked_as_read_now' => false, 'already_read' => false];

error_log("mark_notification_read.php: POST data: " . print_r($_POST, true));
error_log("mark_notification_read.php: SESSION user_id: " . ($_SESSION['user_id'] ?? 'YOK'));
error_log("mark_notification_read.php: PDO durumu: " . (isset($pdo) ? 'VAR' : 'YOK'));


if (!is_user_logged_in()) {
    $response['error'] = 'Bu işlem için giriş yapmalısınız.';
    error_log("mark_notification_read.php: Kullanıcı giriş yapmamış.");
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Geçersiz istek metodu.';
    error_log("mark_notification_read.php: Geçersiz istek metodu: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode($response);
    exit;
}

$user_id_session = $_SESSION['user_id'];
$action_from_post = $_POST['action'] ?? null;

error_log("mark_notification_read.php: Action: $action_from_post, User ID: $user_id_session");

if (!isset($pdo)) {
    $response['error'] = 'Veritabanı bağlantısı kurulamadı (PDO tanımsız).';
    error_log("mark_notification_read.php: PDO nesnesi tanımsız!");
    echo json_encode($response);
    exit;
}

if ($action_from_post === 'mark_read' && isset($_POST['notification_id'])) {
    $notification_id_from_post = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
    
    error_log("mark_notification_read.php: İşlenecek Bildirim ID (filtrelenmiş): $notification_id_from_post");

    if ($notification_id_from_post === false || $notification_id_from_post <= 0) {
        $response['error'] = 'Geçersiz bildirim ID\'si.';
        error_log("mark_notification_read.php: Geçersiz notification_id_from_post: " . $_POST['notification_id']);
        echo json_encode($response);
        exit;
    }
    
    try {
        $stmt_check_before = $pdo->prepare("SELECT is_read FROM notifications WHERE id = :notification_id AND user_id = :user_id");
        $stmt_check_before->execute([':notification_id' => $notification_id_from_post, ':user_id' => $user_id_session]);
        $status_before = $stmt_check_before->fetch(PDO::FETCH_ASSOC);

        if (!$status_before) {
            $response['error'] = 'Bildirim bulunamadı veya size ait değil.';
            error_log("mark_notification_read.php: Bildirim $notification_id_from_post, kullanıcı $user_id_session için bulunamadı (DB sorgusu).");
        } elseif ($status_before['is_read'] == 1) {
            $response['success'] = true;
            $response['already_read'] = true;
            $response['marked_as_read_now'] = false;
            unset($response['error']);
            error_log("mark_notification_read.php: Bildirim $notification_id_from_post zaten okunmuştu.");
        } else {
            error_log("mark_notification_read.php: mark_notification_as_read fonksiyonu çağrılıyor (ID: $notification_id_from_post, User: $user_id_session)");
            $mark_result = mark_notification_as_read($pdo, $notification_id_from_post, $user_id_session);
            error_log("mark_notification_read.php: mark_notification_as_read fonksiyon sonucu: " . ($mark_result ? 'true' : 'false'));
                
            if ($mark_result) {
                $response['success'] = true;
                $response['marked_as_read_now'] = true;
                $response['already_read'] = false;
                unset($response['error']); 
                error_log("mark_notification_read.php: Bildirim $notification_id_from_post başarıyla okundu olarak işaretlendi (fonksiyon true döndü).");
            } else {
                $response['error'] = 'Bildirim okundu olarak işaretlenemedi (fonksiyon false döndü).';
                error_log("mark_notification_read.php: Bildirim $notification_id_from_post okundu olarak işaretlenemedi (fonksiyon false döndü).");
            }
        }
    } catch (PDOException $e) {
        $response['error'] = 'Bildirim durumu kontrol edilirken veritabanı hatası: ' . $e->getMessage();
        error_log("mark_notification_read.php: DB Exception: " . $e->getMessage());
    }

} elseif ($action_from_post === 'mark_all_read') {
    error_log("mark_notification_read.php: mark_all_read işlemi başlatıldı.");
    if (mark_all_notifications_as_read($pdo, $user_id_session)) {
        $response['success'] = true;
        $response['marked_as_read_now'] = true;
        unset($response['error']);
        error_log("mark_notification_read.php: Tüm bildirimler okundu olarak işaretlendi.");
    } else {
        $response['error'] = 'Tüm bildirimler okundu olarak işaretlenemedi.';
        error_log("mark_notification_read.php: Tümünü okundu işaretleme başarısız.");
    }
} else {
    $response['error'] = 'Geçersiz işlem veya eksik parametre.';
    error_log("mark_notification_read.php: Geçersiz action ($action_from_post) veya eksik parametre.");
}

error_log("mark_notification_read.php: Final JSON response: " . json_encode($response));
echo json_encode($response);
exit;
?>
