<?php
// src/actions/handle_delete_discussion_post.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'] ?? null;

if (!$loggedInUserId) { /* Giriş yapılmamışsa işlem yapma */ }

if (function_exists('check_user_session_validity')) {
    check_user_session_validity();
}
require_approved_user(); // Onaylı kullanıcı olmalı

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'], $_POST['topic_id'])) {
    $post_id = (int)$_POST['post_id'];
    $topic_id = (int)$_POST['topic_id']; // Yönlendirme için
    $redirect_url = $baseUrl . '/discussion_detail.php?id=' . $topic_id . '#post-' . $post_id; // Yorumun olduğu yere odaklan

    $post_owner_id = null;
    try {
        $stmt_check_post = $pdo->prepare("SELECT user_id FROM discussion_posts WHERE id = ? AND topic_id = ?");
        $stmt_check_post->execute([$post_id, $topic_id]);
        $post_owner_id = $stmt_check_post->fetchColumn();

        if (!$post_owner_id) {
            $_SESSION['error_message'] = "Silinecek yorum bulunamadı veya konuya ait değil.";
            header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id);
            exit;
        }
    } catch (PDOException $e) { /* Hata işleme */ }

    $can_delete_this_specific_post = false;
    if (has_permission($pdo, 'discussion.post.delete_all', $loggedInUserId)) {
        $can_delete_this_specific_post = true;
    } elseif (has_permission($pdo, 'discussion.post.delete_own', $loggedInUserId) && $post_owner_id == $loggedInUserId) {
        $can_delete_this_specific_post = true;
    }

    if ($can_delete_this_specific_post) {
        try {
            $pdo->beginTransaction();
            // Yorumu sil
            $stmt_delete_post = $pdo->prepare("DELETE FROM discussion_posts WHERE id = ?");
            $stmt_delete_post->execute([$post_id]);

            // Konunun reply_count'unu güncelle
            if ($stmt_delete_post->rowCount() > 0) {
                $stmt_update_topic_count = $pdo->prepare("UPDATE discussion_topics SET reply_count = GREATEST(0, reply_count - 1) WHERE id = ?");
                $stmt_update_topic_count->execute([$topic_id]);
                // Son yanıt zamanını da güncellemek gerekebilir, ama bu biraz daha karmaşık. Şimdilik sadece sayıyı azaltıyoruz.
            }
            $pdo->commit();
            $_SESSION['success_message'] = "Yorum başarıyla silindi.";
            // Silindikten sonra konunun başına yönlendirelim, çünkü silinen post artık olmayacak.
            header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $topic_id);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Yorum silme hatası (Post ID: $post_id): " . $e->getMessage());
            $_SESSION['error_message'] = "Yorum silinirken bir veritabanı hatası oluştu.";
        }
    } else {
        $_SESSION['error_message'] = "Bu yorumu silme yetkiniz bulunmamaktadır.";
    }
    header('Location: ' . $redirect_url); // Hata durumunda veya yetki yoksa aynı yere
    exit;
} else { /* ... */ }
