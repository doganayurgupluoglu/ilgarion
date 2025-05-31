<?php
// src/actions/handle_event_participation.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/notification_functions.php';

// Yetki kontrolü: Onaylı kullanıcı ve 'event.participate' yetkisi
require_approved_user();
// $pdo global scope'ta olmalı veya bu fonksiyona parametre olarak geçilmeli.
if (!isset($pdo)) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası.";
    header('Location: ' . (get_auth_base_url() . '/events.php')); // Genel bir yere yönlendir
    exit;
}
require_permission($pdo, 'event.participate');


$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['event_id'], $_POST['participation_status'])) {
        $_SESSION['error_message'] = "Eksik parametreler.";
        $redirect_url = isset($_POST['event_id']) ? $baseUrl . '/event_detail.php?id=' . (int)$_POST['event_id'] : $baseUrl . '/events.php';
        header('Location: ' . $redirect_url);
        exit;
    }

    $event_id = (int)$_POST['event_id'];
    $participation_status = trim($_POST['participation_status']);
    $allowed_statuses = ['attending', 'maybe', 'declined'];

    if (!in_array($participation_status, $allowed_statuses)) {
        $_SESSION['error_message'] = "Geçersiz katılım durumu.";
        header('Location: ' . $baseUrl . '/event_detail.php?id=' . $event_id);
        exit;
    }

    try {
        $stmt_event_check = $pdo->prepare("SELECT title, created_by_user_id, max_participants FROM events WHERE id = ? AND status = 'active'");
        $stmt_event_check->execute([$event_id]);
        $event_details = $stmt_event_check->fetch(PDO::FETCH_ASSOC);

        if (!$event_details) {
            $_SESSION['error_message'] = "Etkinlik bulunamadı veya aktif değil.";
            header('Location: ' . $baseUrl . '/events.php');
            exit;
        }

        if ($participation_status === 'attending' && $event_details['max_participants'] !== null) {
            $stmt_count_attending = $pdo->prepare("SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND participation_status = 'attending'");
            $stmt_count_attending->execute([$event_id]);
            $current_attending_count = (int)$stmt_count_attending->fetchColumn();

            $stmt_current_participation = $pdo->prepare("SELECT participation_status FROM event_participants WHERE event_id = ? AND user_id = ?");
            $stmt_current_participation->execute([$event_id, $loggedInUserId]);
            $user_current_status = $stmt_current_participation->fetchColumn();

            if ($user_current_status !== 'attending' && $current_attending_count >= $event_details['max_participants']) {
                $_SESSION['error_message'] = "Etkinlik için katılımcı kontenjanı dolu.";
                header('Location: ' . $baseUrl . '/event_detail.php?id=' . $event_id);
                exit;
            }
        }

        $pdo->beginTransaction();

        $stmt_check_existing = $pdo->prepare("SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?");
        $stmt_check_existing->execute([$event_id, $loggedInUserId]);
        $existing_participation_id = $stmt_check_existing->fetchColumn();

        if ($existing_participation_id) {
            $stmt_update = $pdo->prepare("UPDATE event_participants SET participation_status = ? WHERE id = ?");
            $stmt_update->execute([$participation_status, $existing_participation_id]);
            $_SESSION['success_message'] = "Etkinlik için katılım durumunuz güncellendi.";
        } else {
            $stmt_insert = $pdo->prepare("INSERT INTO event_participants (event_id, user_id, participation_status) VALUES (?, ?, ?)");
            $stmt_insert->execute([$event_id, $loggedInUserId, $participation_status]);
            $_SESSION['success_message'] = "Etkinliğe katılım durumunuz kaydedildi.";
        }

        $event_creator_id = (int)$event_details['created_by_user_id'];
        if ($loggedInUserId !== $event_creator_id) {
            $status_translation = [
                'attending' => 'katılıyor',
                'maybe' => 'belki katılacak',
                'declined' => 'katılmayacak'
            ];
            $notification_message = "\"" . htmlspecialchars($event_details['title']) . "\" etkinliğine " . ($status_translation[$participation_status] ?? 'durumunu belirtti') . ".";
            $notification_link_base = $baseUrl . '/event_detail.php?id=' . $event_id;
            // Bildirim ID'sini linke eklemek için önce bildirimi oluşturup ID'sini almamız gerekebilir.
            // Şimdilik notif_id olmadan linki oluşturalım. notification_functions.php'de bu iyileştirilebilir.

            // create_notification fonksiyonunu kullanalım (eğer varsa ve $pdo alıyorsa)
            if (function_exists('create_notification')) {
                 create_notification($pdo, $event_creator_id, $event_id, $loggedInUserId, $notification_message, $notification_link_base);
            } else {
                // Manuel ekleme (eski yöntem)
                $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, event_id, actor_user_id, message, link) VALUES (?, ?, ?, ?, ?)");
                $stmt_notify->execute([$event_creator_id, $event_id, $loggedInUserId, $notification_message, $notification_link_base]);
            }
        }

        $pdo->commit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Etkinlik katılımı kaydetme/güncelleme hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Katılım durumu işlenirken bir veritabanı hatası oluştu.";
    }

    header('Location: ' . $baseUrl . '/event_detail.php?id=' . $event_id);
    exit;

} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: ' . $baseUrl . '/events.php');
    exit;
}
