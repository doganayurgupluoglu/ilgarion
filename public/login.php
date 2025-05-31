<?php
require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

$page_title = "Giriş Yap";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<main class="main-content auth-page">
    <div class="auth-bg-wrapper"></div>
    <div class="auth-container">
        <h2>Giriş Yap</h2>

        <?php
        if (isset($_SESSION['error_message']) && !isset($GLOBALS['general_message_displayed_in_header'])) {
            echo '<p class="message error-message">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            unset($_SESSION['error_message']);
        }
        if (isset($_GET['status']) && $_GET['status'] === 'pending_approval' && !isset($GLOBALS['general_message_displayed_in_header'])) {
            echo '<p class="message info-message">Kaydınız admin onayı bekliyor. Onaylandıktan sonra giriş yapabilirsiniz.</p>';
        }
        if (isset($_GET['status']) && $_GET['status'] === 'logged_out' && !isset($GLOBALS['general_message_displayed_in_header'])) {
            echo '<p class="message success-message">Başarıyla çıkış yaptınız.</p>';
        }
         if (isset($_GET['status']) && $_GET['status'] === 'login_required' && !isset($GLOBALS['general_message_displayed_in_header'])) {
            echo '<p class="message info-message">Bu sayfayı görmek için giriş yapmalısınız.</p>';
        }
        ?>

        <form class="login-form" action="../src/actions/handle_login.php" method="POST">
            <div class="form-group">
                <label for="username">Kullanıcı Adı veya E-posta:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Şifre:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn form-submit-btn">Giriş Yap</button>
            </div>
        </form>
        <p class="sub-link">Hesabın yok mu? <a href="register.php">Kayıt Ol</a></p>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>