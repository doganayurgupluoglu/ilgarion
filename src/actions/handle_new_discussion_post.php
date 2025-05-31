<?php
// src/actions/handle_new_discussion_post.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları (has_permission için)
require_once BASE_PATH . '/src/functions/notification_functions.php'; // create_notification için

require_approved_user(); // Onaylı kullanıcı olmalı

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic_id'], $_POST['comment_content'])) {
    $topic_id = (int)$_POST['topic_id'];
    $comment_content = trim($_POST['comment_content']);
    $parent_post_id = isset($_POST['parent_post_id']) && !empty($_POST['parent_post_id']) && is_numeric($_POST['parent_post_id'])
                      ? (int)$_POST['parent_post_id']
                      : null;

    if (empty($comment_content)) {
        $_SESSION['error_message'] = "Yorum içeriği boş bırakılamaz.";
        header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id . '#newCommentForm');
        exit;
    }

    // Yorum yapma yetkisini kontrol et
    if (!has_permission($pdo, 'discussion.post.create', $loggedInUserId)) {
        $_SESSION['error_message'] = "Bu konuda yorum yapma yetkiniz bulunmamaktadır.";
        header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id);
        exit;
    }

    $topic_info = null;
    $parent_post_author_id = null;

    try {
        $stmt_topic_check = $pdo->prepare("SELECT user_id AS topic_starter_id, title, is_locked FROM discussion_topics WHERE id = ?");
        $stmt_topic_check->execute([$topic_id]);
        $topic_info = $stmt_topic_check->fetch(PDO::FETCH_ASSOC);

        if (!$topic_info) {
            $_SESSION['error_message'] = "Yorum yapılacak konu bulunamadı.";
            header('Location: ' . $baseUrl . '/discussions.php');
            exit;
        }
        if ($topic_info['is_locked'] == 1) {
            $_SESSION['error_message'] = "Bu konu yorumlara kapatılmıştır.";
            header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id);
            exit;
        }

        if ($parent_post_id) {
            $stmt_parent_author = $pdo->prepare("SELECT user_id FROM discussion_posts WHERE id = ? AND topic_id = ?");
            $stmt_parent_author->execute([$parent_post_id, $topic_id]);
            $parent_post_author_id = $stmt_parent_author->fetchColumn();
            if (!$parent_post_author_id) {
                $parent_post_id = null; // Yanıtlanan post bulunamazsa, normal yorum olarak devam et
                error_log("handle_new_discussion_post.php: Yanıtlanan post (ID: $parent_post_id) bulunamadı veya konuya ait değil. Normal yorum olarak işleniyor.");
            }
        }

    } catch (PDOException $e) {
        error_log("Yorum yapma - ön kontrol hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Yorum yaparken bir sorun oluştu (ön kontrol).";
        header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt_post = $pdo->prepare("INSERT INTO discussion_posts (topic_id, user_id, content, parent_post_id) VALUES (?, ?, ?, ?)");
        $stmt_post->execute([$topic_id, $loggedInUserId, $comment_content, $parent_post_id]);
        $new_post_id = $pdo->lastInsertId();

        // Yönlendirme linkini burada, $new_post_id alındıktan sonra oluştur.
        $redirect_link_after_post = $baseUrl . '/discussion_detail.php?id=' . $topic_id . '#post-' . $new_post_id;


        $stmt_update_topic = $pdo->prepare("UPDATE discussion_topics SET reply_count = reply_count + 1, last_reply_at = NOW() WHERE id = ?");
        $stmt_update_topic->execute([$topic_id]);

        // Bildirimler
        $topic_starter_id = (int)$topic_info['topic_starter_id'];

        // 1. Konu sahibine bildirim (eğer yorum yapan kişi konu sahibi değilse)
        if ($loggedInUserId !== $topic_starter_id) {
            $message_for_topic_starter = "\"" . htmlspecialchars($topic_info['title']) . "\" başlıklı konunuza bir yorum yapıldı.";
            create_notification(
                $pdo,
                $topic_starter_id,
                null, // event_id
                $loggedInUserId,
                $message_for_topic_starter,
                $baseUrl . '/discussion_detail.php?id=' . $topic_id . '#post-' . $new_post_id // Temel link
            );
        }

        // 2. Yanıtlanan yorumun sahibine bildirim (eğer varsa, yorum yapan kişi kendisi değilse VE konu sahibi değilse)
        if ($parent_post_id && $parent_post_author_id && $loggedInUserId !== (int)$parent_post_author_id) {
            if ((int)$parent_post_author_id !== $topic_starter_id || $loggedInUserId === $topic_starter_id) { // Mükerrer bildirim engelleme
                 $message_for_parent_author = "\"" . htmlspecialchars($topic_info['title']) . "\" konusundaki yorumunuza yanıt verildi.";
                 create_notification(
                    $pdo,
                    (int)$parent_post_author_id,
                    null, // event_id
                    $loggedInUserId,
                    $message_for_parent_author,
                    $baseUrl . '/discussion_detail.php?id=' . $topic_id . '#post-' . $new_post_id // Temel link
                );
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Yorumunuz başarıyla eklendi.";
        header('Location: ' . $redirect_link_after_post);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Yeni yorum ekleme veya bildirim hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Yorumunuz eklenirken bir veritabanı hatası oluştu.";
        header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id . '#newCommentForm');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    $fallback_redirect = $topic_id ? ($baseUrl . '/discussion_detail.php?id=' . $topic_id) : ($baseUrl . '/discussions.php');
    header('Location: ' . $fallback_redirect);
    exit;
}
?>
