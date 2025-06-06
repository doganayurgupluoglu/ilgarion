<?php
// public/edit_profile.php - Enhanced with Role & Permission System

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/formatting_functions.php';

// Temel yetki kontrolü - giriş yapmış ve onaylanmış kullanıcı olmalı
require_approved_user();

// Profil düzenleme yetkisi kontrolü
require_permission($pdo, 'profile.edit_own');

$page_title = "Profili Düzenle";
$user_id = $_SESSION['user_id'];
$current_user_data = null;
$user_permissions = [];
$user_roles_detailed = [];
$editable_fields = [];

// Kullanıcının sahip olduğu yetkileri kontrol et
$user_permissions = [
    'can_edit_profile' => has_permission($pdo, 'profile.edit_own', $user_id),
    'can_manage_avatar' => has_permission($pdo, 'profile.manage_avatar', $user_id),
    'can_view_hierarchy' => has_permission($pdo, 'profile.view_hierarchy', $user_id),
    'can_export_data' => has_permission($pdo, 'profile.export_data', $user_id),
    'is_admin' => is_admin($pdo, $user_id),
    'is_super_admin' => is_super_admin($pdo, $user_id)
];

// Düzenlenebilir alanları belirle (role-based)
$editable_fields = [
    'ingame_name' => true, // Herkes düzenleyebilir
    'discord_username' => true, // Herkes düzenleyebilir
    'profile_info' => true, // Herkes düzenleyebilir
    'avatar' => $user_permissions['can_manage_avatar'], // Yetki gerekli
    'email' => false, // Kimse bu formdan düzenleyemez (güvenlik)
    'username' => false // Kimse bu formdan düzenleyemez (güvenlik)
];

try {
    // Kullanıcı verilerini güvenli şekilde çek
    $query = "
        SELECT 
            u.username, u.email, u.ingame_name, u.discord_username, 
            u.profile_info, u.avatar_path, u.status, u.created_at,
            (SELECT COUNT(*) FROM user_roles ur WHERE ur.user_id = u.id) AS total_roles_count,
            (SELECT MIN(r.priority) 
             FROM user_roles ur 
             JOIN roles r ON ur.role_id = r.id 
             WHERE ur.user_id = u.id) AS highest_priority_level
        FROM users u 
        WHERE u.id = :user_id AND u.status = 'approved'
    ";
    
    $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
    $current_user_data = $stmt->fetch();

    if (!$current_user_data) {
        $_SESSION['error_message'] = "Profil bilgileriniz bulunamadı veya hesabınız onaylı değil.";
        audit_log($pdo, $user_id, 'profile_edit_unauthorized_access', 'user', $user_id, null, [
            'reason' => 'User data not found or not approved',
            'ip_address' => get_client_ip()
        ]);
        header('Location: ' . get_auth_base_url() . '/profile.php');
        exit;
    }

    // Kullanıcının rol bilgilerini çek
    $user_roles_detailed = get_user_roles($pdo, $user_id);

    // Güvenlik audit log
    audit_log($pdo, $user_id, 'profile_edit_page_accessed', 'user', $user_id, null, [
        'user_roles_count' => count($user_roles_detailed),
        'permissions' => array_keys(array_filter($user_permissions)),
        'editable_fields' => array_keys(array_filter($editable_fields)),
        'ip_address' => get_client_ip()
    ]);

} catch (SecurityException $e) {
    error_log("Güvenlik ihlali - Profil düzenleme erişimi (User ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Güvenlik ihlali tespit edildi. Profil düzenleme erişimi engellendi.";
    audit_log($pdo, $user_id, 'security_violation', 'profile_edit_access', $user_id, null, [
        'error' => $e->getMessage(),
        'ip_address' => get_client_ip()
    ]);
    header('Location: ' . get_auth_base_url() . '/profile.php');
    exit;
} catch (DatabaseException $e) {
    error_log("Veritabanı hatası - Profil düzenleme yükleme (User ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Profil düzenleme sayfası yüklenirken bir veritabanı hatası oluştu.";
} catch (Exception $e) {
    error_log("Genel hata - Profil düzenleme yükleme (User ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Profil düzenleme sayfası yüklenirken beklenmeyen bir hata oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Enhanced Edit Profile Styles with Role Integration */
.edit-profile-page-container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.edit-profile-title {
    color: var(--gold);
    font-size: 2.2rem;
    font-weight: 300;
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--darker-gold-2);
    padding-bottom: 15px;
}

.profile-form-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 40px;
    margin-top: 30px;
}

.profile-form-sidebar {
    padding: 25px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    align-self: flex-start;
    position: sticky;
    top: calc(var(--navbar-height, 70px) + 30px);
}

.profile-form-main {
    padding: 30px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

/* Avatar Editor Section */
.avatar-editor-group {
    text-align: center;
}

.avatar-editor-group label {
    display: block;
    color: var(--gold);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.current-avatar-preview-wrapper {
    margin-bottom: 20px;
}

.current-avatar-img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--gold);
    box-shadow: 0 0 20px rgba(189, 145, 42, 0.3);
    transition: all 0.3s ease;
}

.current-avatar-img:hover {
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(189, 145, 42, 0.5);
}

.avatar-placeholder-form {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--grey), var(--darker-grey));
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    font-weight: bold;
    border: 4px solid var(--darker-gold-1);
    margin: 0 auto 20px auto;
    transition: all 0.3s ease;
}

.avatar-placeholder-form:hover {
    background: linear-gradient(135deg, var(--darker-gold-1), var(--grey));
    transform: scale(1.05);
}

.input-file-avatar {
    width: 100%;
    padding: 12px;
    background: rgba(66, 66, 66, 0.6);
    border: 2px dashed var(--darker-gold-1);
    border-radius: 8px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    margin-bottom: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.input-file-avatar:hover {
    border-color: var(--gold);
    background: rgba(189, 145, 42, 0.1);
}

.input-file-avatar:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(189, 145, 42, 0.2);
}

/* Permission Info Section */
.permission-info-section {
    margin-top: 25px;
    padding: 15px;
    background: rgba(42, 189, 168, 0.1);
    border-radius: 8px;
    border-left: 3px solid var(--turquase);
}

.permission-info-title {
    color: var(--turquase);
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.permission-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.permission-list li {
    padding: 4px 0;
    font-size: 0.85rem;
    color: var(--light-grey);
    display: flex;
    align-items: center;
    gap: 8px;
}

.permission-list li::before {
    content: '✓';
    color: var(--turquase);
    font-weight: bold;
    width: 16px;
}

.permission-list li.disabled {
    opacity: 0.5;
}

.permission-list li.disabled::before {
    content: '✗';
    color: var(--light-grey);
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    color: var(--light-grey);
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 8px;
    transition: color 0.2s ease;
}

.form-group label:hover {
    color: var(--gold);
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 14px 16px;
    background: rgba(66, 66, 66, 0.6);
    border: 2px solid transparent;
    border-radius: 8px;
    color: var(--lighter-grey);
    font-size: 1rem;
    font-family: var(--font);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
    backdrop-filter: blur(10px);
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    background: rgba(34, 34, 34, 0.9);
    border-color: var(--gold);
    box-shadow: 
        0 0 0 3px rgba(189, 145, 42, 0.1),
        0 8px 25px rgba(189, 145, 42, 0.15);
    transform: translateY(-1px);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: var(--light-grey);
    opacity: 0.7;
}

.form-group input:disabled,
.form-group textarea:disabled {
    background: rgba(100, 100, 100, 0.3);
    color: var(--light-grey);
    cursor: not-allowed;
    border-color: var(--grey);
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.6;
}

.form-text {
    font-size: 0.85rem;
    color: var(--light-grey);
    margin-top: 6px;
    line-height: 1.4;
}

.form-text.security-note {
    color: var(--turquase);
    font-weight: 500;
}

.form-text.disabled-note {
    color: var(--light-grey);
    font-style: italic;
}

/* Field Status Indicators */
.field-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: 8px;
    font-size: 0.8rem;
}

.field-status.editable {
    color: var(--turquase);
}

.field-status.restricted {
    color: var(--light-grey);
}

.field-status.admin-only {
    color: var(--gold);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 40px;
    padding-top: 25px;
    border-top: 1px solid var(--darker-gold-2);
}

.btn {
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, var(--gold) 0%, var(--light-gold) 100%);
    color: var(--charcoal);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 
        0 12px 30px rgba(189, 145, 42, 0.4),
        0 0 0 1px rgba(189, 145, 42, 0.2);
    background: linear-gradient(135deg, var(--light-gold) 0%, var(--gold) 100%);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-secondary {
    background: rgba(66, 66, 66, 0.8);
    color: var(--lighter-grey);
    border: 1px solid var(--grey);
}

.btn-secondary:hover {
    background: rgba(66, 66, 66, 1);
    color: var(--white);
    border-color: var(--light-grey);
    transform: translateY(-1px);
}

.btn-export {
    background: linear-gradient(135deg, var(--turquase) 0%, #5dbdb3 100%);
    color: var(--charcoal);
    margin-left: auto;
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(42, 189, 168, 0.3);
}

/* Responsive Design */
@media (max-width: 992px) {
    .profile-form-layout {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .profile-form-sidebar {
        position: static;
        order: 2;
    }
    
    .profile-form-main {
        order: 1;
    }
}

@media (max-width: 768px) {
    .edit-profile-page-container {
        padding: 15px;
    }
    
    .edit-profile-title {
        font-size: 1.8rem;
    }
    
    .profile-form-sidebar,
    .profile-form-main {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}

.form-load-error {
    text-align: center;
    font-size: 1.1rem;
    color: var(--red);
    padding: 30px 20px;
    background-color: var(--transparent-red);
    border: 1px solid var(--dark-red);
    border-radius: 6px;
    margin-top: 30px;
}

/* Role Badge in Form */
.user-role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 2px;
}

.user-role-badge.role-admin {
    background: linear-gradient(135deg, #bd912a, #d4a74a);
    color: var(--charcoal);
}

.user-role-badge.role-scg_uye {
    background: linear-gradient(135deg, #A52A2A, #cd5c5c);
    color: white;
}

.user-role-badge.role-ilgarion_turanis {
    background: linear-gradient(135deg, var(--turquase), #5dbdb3);
    color: var(--charcoal);
}

.user-role-badge.role-member {
    background: linear-gradient(135deg, var(--grey), var(--light-grey));
    color: var(--charcoal);
}

.user-role-badge.role-dis_uye {
    background: linear-gradient(135deg, #808080, #a0a0a0);
    color: white;
}

/* Security Alert Box */
.security-alert {
    background: rgba(189, 145, 42, 0.1);
    border: 1px solid rgba(189, 145, 42, 0.3);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
    color: var(--gold);
}

.security-alert h4 {
    color: var(--gold);
    margin: 0 0 10px 0;
    font-size: 1rem;
}

.security-alert ul {
    margin: 0;
    padding-left: 20px;
    font-size: 0.9rem;
    line-height: 1.6;
}
</style>

<main class="main-content">
    <div class="container edit-profile-page-container">
        <h2 class="edit-profile-title"><?php echo htmlspecialchars($page_title); ?></h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['warning_message']; unset($_SESSION['warning_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Güvenlik Uyarısı -->
        <div class="security-alert">
            <h4><i class="fas fa-shield-alt"></i> Güvenlik Bilgileri</h4>
            <ul>
                <li>Kişisel bilgilerinizi güncellerken dikkatli olun</li>
                <li>Avatar dosyaları maksimum 2MB olmalı ve güvenli formatlarda (JPG, PNG, GIF) olmalıdır</li>
                <li>Profil açıklamanızda kişisel bilgiler (telefon, adres vb.) paylaşmayın</li>
                <?php if ($user_permissions['is_admin']): ?>
                <li><strong>Admin uyarısı:</strong> Profil değişiklikleriniz audit log'a kaydedilmektedir</li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($current_user_data): ?>
            <form action="../src/actions/handle_edit_profile.php" method="POST" enctype="multipart/form-data" class="edit-profile-form">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="profile-form-layout">
                    <div class="profile-form-sidebar">
                        <!-- Avatar Düzenleme -->
                        <?php if ($editable_fields['avatar']): ?>
                        <div class="form-group avatar-editor-group">
                            <label for="avatar">
                                Profil Fotoğrafı 
                                <span class="field-status editable">
                                </span>
                            </label>
                            
                            <?php if (!empty($current_user_data['avatar_path'])): ?>
                                <div class="current-avatar-preview-wrapper">
                                    <img src="/public/<?php echo htmlspecialchars($current_user_data['avatar_path']); ?>" 
                                         alt="Mevcut Avatar" class="current-avatar-img">
                                </div>
                            <?php else: ?>
                                <div class="avatar-placeholder-form">
                                    <?php echo strtoupper(substr(htmlspecialchars($current_user_data['username']), 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <input type="file" id="avatar" name="avatar" class="input-file-avatar" 
                                   accept="image/jpeg, image/png, image/gif">
                            <small class="form-text">Yeni bir fotoğraf yükleyerek değiştirin. JPG, PNG, GIF. Maksimum 2MB.</small>
                        </div>
                        <?php else: ?>
                        <div class="form-group avatar-editor-group">
                            <label>
                                Profil Fotoğrafı 
                                <span class="field-status restricted">
                                    <i class="fas fa-lock"></i> Kısıtlı
                                </span>
                            </label>
                            
                            <?php if (!empty($current_user_data['avatar_path'])): ?>
                                <div class="current-avatar-preview-wrapper">
                                    <img src="/public/<?php echo htmlspecialchars($current_user_data['avatar_path']); ?>" 
                                         alt="Mevcut Avatar" class="current-avatar-img">
                                </div>
                            <?php else: ?>
                                <div class="avatar-placeholder-form">
                                    <?php echo strtoupper(substr(htmlspecialchars($current_user_data['username']), 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <small class="form-text disabled-note">Avatar değiştirme yetkiniz bulunmuyor.</small>
                        </div>
                        <?php endif; ?>

                        <!-- Kullanıcı Yetkileri Bilgisi -->
                        

                    
                    </div>

                    <div class="profile-form-main">
                        <!-- Kullanıcı Adı (Düzenlenemez) -->
                        <div class="form-group">
                            <label for="username_display">
                                Kullanıcı Adı
                                <span class="field-status restricted">
                                    <i class="fas fa-lock"></i> Değiştirilemez
                                </span>
                            </label>
                            <input type="text" id="username_display" name="username_display" 
                                   value="<?php echo htmlspecialchars($current_user_data['username']); ?>" 
                                   disabled>
                            <small class="form-text security-note">
                                Kullanıcı adları güvenlik nedeniyle değiştirilemez.
                            </small>
                        </div>

                        <!-- E-posta (Düzenlenemez) -->
                        <div class="form-group">
                            <label for="email_display">
                                E-posta Adresi
                                <span class="field-status restricted">
                                    <i class="fas fa-lock"></i> Değiştirilemez
                                </span>
                            </label>
                            <input type="email" id="email_display" name="email_display" 
                                   value="<?php echo htmlspecialchars($current_user_data['email']); ?>" 
                                   disabled>
                            <small class="form-text security-note">
                                E-posta adresleri güvenlik nedeniyle bu formdan değiştirilemez.
                            </small>
                        </div>

                        <!-- Oyun İçi İsim -->
                        <div class="form-group">
                            <label for="ingame_name">
                                Oyun İçi İsim (RSI Handle)
                                <span class="field-status editable">
                                </span>
                            </label>
                            <input type="text" id="ingame_name" name="ingame_name" 
                                   value="<?php echo htmlspecialchars($current_user_data['ingame_name'] ?? ''); ?>" 
                                   required
                                   maxlength="100"
                                   placeholder="Star Citizen karakterinizin adı">
                            <small class="form-text">
                                Star Citizen oyunundaki resmi karakter isminizi girin.
                            </small>
                        </div>

                        <!-- Discord Kullanıcı Adı -->
                        <div class="form-group">
                            <label for="discord_username">
                                Discord Kullanıcı Adı
                                <span class="field-status editable">
                                </span>
                            </label>
                            <input type="text" id="discord_username" name="discord_username" 
                                   value="<?php echo htmlspecialchars($current_user_data['discord_username'] ?? ''); ?>"
                                   maxlength="50"
                                   placeholder="örn: kullaniciadi#1234">
                            <small class="form-text">
                                Discord kullanıcı adınızı girin. Boş bırakılabilir.
                            </small>
                        </div>

                        <!-- Profil Açıklaması -->
                        <div class="form-group">
                            <label for="profile_info">
                                Profil Açıklaması
                                <span class="field-status editable">
                                </span>
                            </label>
                            <textarea id="profile_info" name="profile_info" 
                                      rows="6" 
                                      maxlength="2000"
                                      placeholder="Kendiniz hakkında kısa bir açıklama yazın..."><?php echo htmlspecialchars($current_user_data['profile_info'] ?? ''); ?></textarea>
                            <small class="form-text">
                                Maksimum 2000 karakter. Kişisel bilgilerinizi (telefon, adres vb.) paylaşmayın.
                            </small>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-submit-profile">
                                <i class="fas fa-save"></i> Değişiklikleri Kaydet
                            </button>
                            <a href="profile.php" class="btn btn-secondary btn-cancel-profile">
                                <i class="fas fa-times"></i> İptal
                            </a>
                            
                            <?php if ($user_permissions['can_export_data']): ?>
                            <a href="../src/actions/export_profile_data.php" class="btn btn-export" 
                               onclick="return confirm('Profil verilerinizi JSON formatında dışa aktarmak istediğinizden emin misiniz?');">
                                <i class="fas fa-download"></i> Verilerimi İndir
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p class="form-load-error">
                <i class="fas fa-exclamation-triangle"></i>
                Profil düzenleme formu yüklenemedi. Lütfen daha sonra tekrar deneyin veya bir yönetici ile iletişime geçin.
            </p>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('.edit-profile-form');
    const submitBtn = document.querySelector('.btn-submit-profile');
    
    if (form && submitBtn) {
        // Avatar file validation
        const avatarInput = document.getElementById('avatar');
        if (avatarInput) {
            avatarInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Size check (2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        alert('Dosya boyutu 2MB\'dan küçük olmalıdır.');
                        this.value = '';
                        return;
                    }
                    
                    // Type check
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Sadece JPG, PNG ve GIF dosyaları yüklenebilir.');
                        this.value = '';
                        return;
                    }
                    
                    // Preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.querySelector('.current-avatar-img');
                        if (preview) {
                            preview.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Form submission
        form.addEventListener('submit', function(e) {
            const ingameName = document.getElementById('ingame_name');
            if (ingameName && !ingameName.value.trim()) {
                e.preventDefault();
                alert('Oyun içi isim zorunludur.');
                ingameName.focus();
                return;
            }
            
            // Loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            submitBtn.disabled = true;
        });
        
        // Auto-save draft functionality (for longer descriptions)
        const profileInfo = document.getElementById('profile_info');
        if (profileInfo) {
            let saveTimeout;
            profileInfo.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    const draftKey = 'profile_draft_' + <?php echo $user_id; ?>;
                    localStorage.setItem(draftKey, this.value);
                }, 1000);
            });
            
            // Load draft on page load
            const draftKey = 'profile_draft_' + <?php echo $user_id; ?>;
            const savedDraft = localStorage.getItem(draftKey);
            if (savedDraft && !profileInfo.value.trim()) {
                profileInfo.value = savedDraft;
            }
        }
    }
    
    // Permission tooltips
    const permissionItems = document.querySelectorAll('.permission-list li');
    permissionItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            if (this.classList.contains('disabled')) {
                this.title = 'Bu özellik için yetkiniz bulunmuyor';
            } else {
                this.title = 'Bu özelliği kullanabilirsiniz';
            }
        });
    });
    
    // Role badge hover effects
    const roleBadges = document.querySelectorAll('.user-role-badge');
    roleBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Security alert auto-hide
    const securityAlert = document.querySelector('.security-alert');
    if (securityAlert) {
        setTimeout(() => {
            securityAlert.style.opacity = '0.8';
        }, 10000);
    }
    
    // Clear draft on successful form submission
    window.addEventListener('beforeunload', function(e) {
        if (form && form.querySelector('input[name="csrf_token"]')) {
            const draftKey = 'profile_draft_' + <?php echo $user_id; ?>;
            localStorage.removeItem(draftKey);
        }
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>