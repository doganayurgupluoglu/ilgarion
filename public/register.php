<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

$page_title = "Kayıt Ol";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<main class="main-content auth-page">
    <div class="register-bg-wrapper"></div>
    <div class="auth-container">
        <h2>Üye Kayıt Formu</h2>

        <form action="../src/actions/handle_register.php" method="POST">
            <div class="form-group">
                <label for="username">Kullanıcı Adı:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_SESSION['form_input']['username'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">E-posta Adresi:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['form_input']['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="ingame_name">Oyun İçi İsim (RSI Handle):</label>
                <input type="text" id="ingame_name" name="ingame_name" value="<?php echo htmlspecialchars($_SESSION['form_input']['ingame_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Şifre:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Şifre Tekrar:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn form-submit-btn">Kayıt Ol</button>
            </div>
        </form>
        <p class="sub-link">Zaten bir hesabın var mı? <a href="<?php echo get_auth_base_url(); ?>/login.php">Giriş Yap</a></p>
    </div>
</main>

<?php
if (isset($_SESSION['form_input'])) {
    unset($_SESSION['form_input']);
}

require_once BASE_PATH . '/src/includes/footer.php';
?>