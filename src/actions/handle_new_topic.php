<?php
// src/actions/handle_new_topic.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/notification_functions.php'; // Bildirimler için

// Yetki kontrolü
require_approved_user();
require_permission($pdo, 'discussion.topic.create');

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['content'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    // Yeni görünürlük alanları
    $is_public_no_auth_topic_form = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
    $is_members_only_topic_form = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
    $assigned_role_ids_topic_form = isset($_POST['assigned_role_ids_topic']) && is_array($_POST['assigned_role_ids_topic'])
                                  ? array_map('intval', $_POST['assigned_role_ids_topic'])
                                  : [];

    // `discussion_topics` tablosu için bayrakları belirle
    $db_is_public_no_auth_topic = 0;
    $db_is_members_only_topic = 0; // Varsayılan olarak false yapalım, mantığa göre true olacak

    if ($is_public_no_auth_topic_form) {
        $db_is_public_no_auth_topic = 1;
        $assigned_role_ids_topic_form = []; // Herkese açıksa, rol ataması olmaz
    } elseif ($is_members_only_topic_form) {
        $db_is_members_only_topic = 1;
        $assigned_role_ids_topic_form = []; // Tüm üyelere açıksa, spesifik rol ataması olmaz
    } elseif (empty($assigned_role_ids_topic_form)) {
        // Ne public, ne members_only, ne de spesifik rol seçilmemişse, varsayılan olarak members_only yapalım.
        $db_is_members_only_topic = 1;
    }
    // Eğer $assigned_role_ids_topic_form doluysa ve diğerleri false ise,
    // $db_is_public_no_auth_topic = 0 ve $db_is_members_only_topic = 0 olacak.
    // Bu durumda `discussion_topic_visibility_roles` tablosu kullanılacak.

    if (empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Başlık ve mesaj içeriği boş bırakılamaz.";
        $_SESSION['form_input'] = $_POST; // Form girdilerini sakla
        header('Location: ' . $baseUrl . '/new_discussion_topic.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt_topic = $pdo->prepare(
            "INSERT INTO discussion_topics (user_id, title, last_reply_at, is_public_no_auth, is_members_only)
             VALUES (?, ?, NOW(), ?, ?)"
        );
        $stmt_topic->execute([$loggedInUserId, $title, $db_is_public_no_auth_topic, $db_is_members_only_topic]);
        $new_topic_id = $pdo->lastInsertId();

        $stmt_post = $pdo->prepare("INSERT INTO discussion_posts (topic_id, user_id, content) VALUES (?, ?, ?)");
        $stmt_post->execute([$new_topic_id, $loggedInUserId, $content]);
        $new_post_id = $pdo->lastInsertId(); // İlk postun ID'si (bildirim linki için)

        // Eğer spesifik roller seçildiyse ve public/members_only değilse, ilişki tablosuna ekle
        if ($db_is_public_no_auth_topic == 0 && $db_is_members_only_topic == 0 && !empty($assigned_role_ids_topic_form)) {
            $stmt_topic_role_insert = $pdo->prepare("INSERT INTO discussion_topic_visibility_roles (topic_id, role_id) VALUES (?, ?)");
            foreach ($assigned_role_ids_topic_form as $role_id_to_assign_topic) {
                $stmt_topic_role_insert->execute([$new_topic_id, $role_id_to_assign_topic]);
            }
        }

        // Bildirim gönderme (aynı kalacak, ama linki güncelleyebiliriz)
        $stmt_users = $pdo->prepare("SELECT id FROM users WHERE status = 'approved' AND id != ?");
        $stmt_users->execute([$loggedInUserId]);
        $approved_user_ids = $stmt_users->fetchAll(PDO::FETCH_COLUMN);

        if ($approved_user_ids) {
            $notification_message = "\"" . htmlspecialchars($title) . "\" başlıklı yeni bir tartışma başlatıldı.";
            // Bildirim linki ilk posta değil, konunun kendisine gitmeli.
            // Eğer ilk posta gitmesi isteniyorsa, $new_post_id kullanılabilir.
            $notification_link = $baseUrl . '/discussion_detail.php?id=' . $new_topic_id;

            // create_notification fonksiyonunu kullanalım
            if (function_exists('create_notification')) {
                foreach ($approved_user_ids as $notify_user_id) {
                    create_notification($pdo, $notify_user_id, null, $loggedInUserId, $notification_message, $notification_link);
                }
            } else { // Fallback
                $stmt_notify_manual = $pdo->prepare("INSERT INTO notifications (user_id, event_id, actor_user_id, message, link, is_read) VALUES (?, NULL, ?, ?, ?, 0)");
                foreach ($approved_user_ids as $notify_user_id) {
                    $stmt_notify_manual->execute([$notify_user_id, $loggedInUserId, $notification_message, $notification_link]);
                }
            }
        }


        $pdo->commit();
        $_SESSION['success_message'] = "Yeni tartışma başlığı başarıyla oluşturuldu!";
        header('Location: ' . $baseUrl . '/discussion_detail.php?id=' . $new_topic_id);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Yeni tartışma konusu oluşturma hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Tartışma oluşturulurken bir veritabanı hatası oluştu.";
        $_SESSION['form_input'] = $_POST; // Form girdilerini sakla
        header('Location: ' . $baseUrl . '/new_discussion_topic.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: ' . $baseUrl . '/discussions.php');
    exit;
}
