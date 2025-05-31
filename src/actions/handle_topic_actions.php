<?php
// src/actions/handle_topic_actions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'] ?? null;

if (!$loggedInUserId) {
    $_SESSION['error_message'] = "Bu işlem için giriş yapmalısınız.";
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}
// Oturum geçerliliğini ve onay durumunu kontrol et
if (function_exists('check_user_session_validity')) {
    check_user_session_validity(); // Bu fonksiyon session'ı sonlandırabilir
}
require_approved_user(); // Eylemleri sadece onaylı kullanıcılar yapabilsin

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_id'], $_POST['action'])) {
    $topic_id = (int)$_POST['topic_id'];
    $action = $_POST['action'];

    $topic = null;
    try {
        $stmt_topic_info = $pdo->prepare("SELECT user_id, is_locked, is_pinned FROM discussion_topics WHERE id = ?");
        $stmt_topic_info->execute([$topic_id]);
        $topic = $stmt_topic_info->fetch(PDO::FETCH_ASSOC);

        if (!$topic) {
            $_SESSION['error_message'] = "İşlem yapılacak konu bulunamadı.";
            header('Location: ' . $baseUrl . '/admin/discussions.php');
            exit;
        }
    } catch (PDOException $e) { /* Hata işleme */ }

    $redirect_url = $baseUrl . '/admin/discussions.php'; // Varsayılan olarak admin listesine dön
    $redirect_url .= '?locked_status=' . ($_POST['locked_status'] ?? $_GET['locked_status'] ?? 'all');
    $redirect_url .= '&pinned_status=' . ($_POST['pinned_status'] ?? $_GET['pinned_status'] ?? 'all');

    $can_perform_this_specific_action = false;

    switch ($action) {
        case 'toggle_lock':
            if (has_permission($pdo, 'discussion.topic.lock', $loggedInUserId)) {
                $can_perform_this_specific_action = true;
                $new_lock_status = $topic['is_locked'] ? 0 : 1;
                $success_message = $new_lock_status ? "Konu başarıyla yorumlara kapatıldı." : "Konu başarıyla yorumlara açıldı.";
                try {
                    $stmt_update = $pdo->prepare("UPDATE discussion_topics SET is_locked = ? WHERE id = ?");
                    if ($stmt_update->execute([$new_lock_status, $topic_id])) {
                        $_SESSION['success_message'] = $success_message;
                    } else { $_SESSION['error_message'] = "Konu kilit durumu güncellenirken bir hata oluştu.";}
                } catch (PDOException $e) { /* Hata işleme */ }
            } else { $_SESSION['error_message'] = "Konu kilitleme/açma yetkiniz yok."; }
            break;

        case 'toggle_pin':
            if (has_permission($pdo, 'discussion.topic.pin', $loggedInUserId)) {
                $can_perform_this_specific_action = true;
                $new_pin_status = $topic['is_pinned'] ? 0 : 1;
                $success_message = $new_pin_status ? "Konu başarıyla sabitlendi." : "Konu sabitlemesi başarıyla kaldırıldı.";
                try {
                    $stmt_update = $pdo->prepare("UPDATE discussion_topics SET is_pinned = ? WHERE id = ?");
                    if ($stmt_update->execute([$new_pin_status, $topic_id])) {
                        $_SESSION['success_message'] = $success_message;
                    } else { $_SESSION['error_message'] = "Konu sabitleme durumu güncellenirken bir hata oluştu."; }
                } catch (PDOException $e) { /* Hata işleme */ }
            } else { $_SESSION['error_message'] = "Konu sabitleme yetkiniz yok."; }
            break;

        case 'delete_topic':
            $can_delete_this_specific_topic = false;
            if (has_permission($pdo, 'discussion.topic.delete_all', $loggedInUserId)) {
                $can_delete_this_specific_topic = true;
            } elseif (has_permission($pdo, 'discussion.topic.delete_own', $loggedInUserId) && $topic['user_id'] == $loggedInUserId) {
                $can_delete_this_specific_topic = true;
            }

            if ($can_delete_this_specific_topic) {
                $can_perform_this_specific_action = true;
                try {
                    // İlişkili kayıtlar (posts, visibility_roles, views) DB'de ON DELETE CASCADE ile silinmeli.
                    // Eğer değilse, burada manuel silme yapılmalı.
                    $stmt_delete_topic = $pdo->prepare("DELETE FROM discussion_topics WHERE id = ?");
                    if ($stmt_delete_topic->execute([$topic_id])) {
                        if ($stmt_delete_topic->rowCount() > 0) {
                            $_SESSION['success_message'] = "Tartışma konusu ve tüm yorumları başarıyla silindi.";
                        } else { $_SESSION['error_message'] = "Konu silinemedi (belki zaten silinmişti)."; }
                    } else { $_SESSION['error_message'] = "Konu silinirken bir veritabanı hatası oluştu."; }
                } catch (PDOException $e) { /* Hata işleme */ }
            } else { $_SESSION['error_message'] = "Bu konuyu silme yetkiniz yok."; }
            break;
        default:
            $_SESSION['error_message'] = "Geçersiz konu işlemi.";
            break;
    }
    header('Location: ' . $redirect_url);
    exit;
} else { /* ... */ }
