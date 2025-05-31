<?php
require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/notification_functions.php';

require_login();

$page_title = "Tüm Bildirimlerim";
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read_page') {
    if (mark_all_notifications_as_read($pdo, $user_id)) {
        $_SESSION['success_message'] = "Tüm bildirimler okundu olarak işaretlendi.";
    } else {
        $_SESSION['error_message'] = "Bildirimler okundu olarak işaretlenirken bir hata oluştu.";
    }
    header('Location: ' . get_auth_base_url() . '/notifications.php');
    exit;
}

$all_user_notifications = get_all_notifications($pdo, $user_id);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<main class="main-content">
    <div class="container notifications-page-container">
        <div class="notifications-header">
            <h2><?php echo htmlspecialchars($page_title); ?></h2>
            <?php
            $has_unread_notifications = false;
            foreach ($all_user_notifications as $notification) {
                if (!$notification['is_read']) {
                    $has_unread_notifications = true;
                    break;
                }
            }
            if ($has_unread_notifications):
            ?>
                <form action="notifications.php" method="POST"> 
                    <input type="hidden" name="action" value="mark_all_read_page">
                    <button type="submit" class="btn btn-sm btn-mark-all-read">Tümünü Okundu İşaretle</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($all_user_notifications)): ?>
            <p class="no-notifications-message">
                Hiç bildiriminiz bulunmamaktadır.
            </p>
        <?php else: ?>
            <ul class="notifications-list"> 
                <?php foreach ($all_user_notifications as $notification): ?>
                    <li class="notification-list-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                        <a href="<?php echo htmlspecialchars($notification['link']); ?>&notif_id=<?php echo $notification['id']; ?>"
                           class="notification-link <?php echo $notification['is_read'] ? '' : 'font-weight-bold'; ?>"
                           data-notification-id="<?php echo $notification['id']; ?>"> 
                            <p class="notification-message"> 
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            <small class="notification-meta"> 
                                <span><?php echo date('d F Y, H:i', strtotime($notification['created_at'])); ?></span>
                                <?php if ($notification['is_read']): ?>
                                    <span class="status-tag-read">(Okundu)</span> 
                                <?php endif; ?>
                            </small>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>