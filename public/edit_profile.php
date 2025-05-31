<?php
// public/edit_profile.php

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

require_approved_user(); // Sadece giriş yapmış ve ONAYLANMIŞ kullanıcılar bu sayfayı görebilir
require_permission($pdo, 'profile.edit_own'); // Kendi profilini düzenleme yetkisi

$page_title = "Profili Düzenle";

$user_id = $_SESSION['user_id'];
$current_user_data = null;

try {
    // SQL sorgusuna discord_username eklendi
    $stmt = $pdo->prepare("SELECT username, email, ingame_name, discord_username, profile_info, avatar_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_user_data) { 
        $_SESSION['error_message'] = "Kullanıcı bilgileri bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/profile.php');
        exit;
    }

} catch (PDOException $e) {
    error_log("Profil düzenleme için bilgi çekme hatası (ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Profil bilgileri yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<main class="main-content">
    <div class="container edit-profile-page-container">
        <h2 class="edit-profile-title"><?php echo htmlspecialchars($page_title); ?></h2>

        <?php
        // Hata veya başarı mesajları header.php tarafından zaten genel olarak gösteriliyor varsayıyoruz.
        ?>

        <?php if ($current_user_data): ?>
            <form action="../src/actions/handle_edit_profile.php" method="POST" enctype="multipart/form-data" class="edit-profile-form">
                <div class="profile-form-layout">
                    <div class="profile-form-sidebar">
                        <div class="form-group avatar-editor-group">
                            <label for="avatar">Profil Fotoğrafı</label>
                            <?php if (!empty($current_user_data['avatar_path'])): ?>
                                <div class="current-avatar-preview-wrapper">
                                    <img src="/public/<?php echo htmlspecialchars($current_user_data['avatar_path']); ?>" alt="Mevcut Avatar" class="current-avatar-img">
                                </div>
                            <?php else: ?>
                                <div class="avatar-placeholder-form large-placeholder">
                                    <?php echo strtoupper(substr(htmlspecialchars($current_user_data['username']), 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="avatar" name="avatar" class="input-file-avatar" accept="image/jpeg, image/png, image/gif">
                            <small class="form-text">Yeni bir fotoğraf yükleyerek değiştirin. JPG, PNG, GIF. Maksimum 2MB.</small>
                        </div>
                    </div>

                    <div class="profile-form-main">
                        <div class="form-group">
                            <label for="username_display">Kullanıcı Adı:</label>
                            <input type="text" id="username_display" name="username_display" value="<?php echo htmlspecialchars($current_user_data['username']); ?>" disabled>
                            <small class="form-text">Kullanıcı adları değiştirilemez.</small>
                        </div>
                        <div class="form-group">
                            <label for="email_display">E-posta Adresi:</label>
                            <input type="email" id="email_display" name="email_display" value="<?php echo htmlspecialchars($current_user_data['email']); ?>" disabled>
                            <small class="form-text">E-posta adresleri bu formdan değiştirilemez.</small>
                        </div>
                        <div class="form-group">
                            <label for="ingame_name">Oyun İçi İsim (RSI Handle):</label>
                            <input type="text" id="ingame_name" name="ingame_name" value="<?php echo htmlspecialchars($current_user_data['ingame_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="discord_username">Discord Kullanıcı Adı:</label>
                            <input type="text" id="discord_username" name="discord_username" value="<?php echo htmlspecialchars($current_user_data['discord_username'] ?? ''); ?>">
                            <small class="form-text">Örn: kullaniciadi#1234 veya kullanici.adi (Boş bırakılabilir)</small>
                        </div>
                        <div class="form-group">
                            <label for="profile_info">Profil Açıklaması:</label>
                            <textarea id="profile_info" name="profile_info" rows="6"><?php echo htmlspecialchars($current_user_data['profile_info'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-submit-profile">Değişiklikleri Kaydet</button>
                            <a href="profile.php" class="btn btn-secondary btn-cancel-profile">İptal</a>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p class="form-load-error">Profil düzenleme formu yüklenemedi. Lütfen daha sonra tekrar deneyin veya bir yönetici ile iletişime geçin.</p>
        <?php endif; ?>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
