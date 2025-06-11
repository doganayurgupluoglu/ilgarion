<?php
// profile/security.php - Güvenlik Ayarları Sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Giriş yapma zorunluluğu
require_login();

$current_user_id = $_SESSION['user_id'];
$is_approved = is_user_approved();

// Kullanıcı verilerini çek
$user_data = getUserSecurityData($pdo, $current_user_id);

if (!$user_data) {
    $_SESSION['error_message'] = "Güvenlik bilgileriniz yüklenemedi.";
    header('Location: /profile.php');
    exit;
}

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'change_password':
            $result = processPasswordChange($pdo, $current_user_id, $_POST);
            break;
        case 'change_email':
            $result = processEmailChange($pdo, $current_user_id, $_POST);
            break;
        case 'revoke_sessions':
            $result = revokeAllSessions($pdo, $current_user_id);
            break;
        default:
            $result = ['success' => false, 'message' => 'Geçersiz işlem.'];
    }
    
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: /profile/security.php');
        exit;
    } else {
        $error_message = $result['message'];
    }
}

// Son giriş ve oturum bilgilerini çek
$login_history = getRecentLoginHistory($pdo, $current_user_id);
$active_sessions = getActiveSessions($pdo, $current_user_id);

$page_title = "Güvenlik Ayarları - " . htmlspecialchars($user_data['username']);

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Kullanıcı güvenlik verilerini çeker (sidebar için gerekli tüm bilgilerle)
 */
function getUserSecurityData(PDO $pdo, int $user_id): ?array {
    try {
        $query = "
            SELECT u.id, u.username, u.email, u.avatar_path, u.created_at, u.updated_at,
                   (SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_name,
                   (SELECT r.color FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_color
            FROM users u
            WHERE u.id = :user_id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return null;
        }
        
        // Avatar path düzeltme
        $avatar_path = $user['avatar_path'];
        if (empty($avatar_path)) {
            $avatar_path = '/assets/logo.png';
        } elseif (strpos($avatar_path, '../assets/') === 0) {
            $avatar_path = str_replace('../assets/', '/assets/', $avatar_path);
        } elseif (strpos($avatar_path, 'uploads/') === 0) {
            $avatar_path = '/' . $avatar_path;
        }
        
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'avatar_path' => $avatar_path,
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a'
        ];
        
    } catch (Exception $e) {
        error_log("Security data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Şifre değiştirme işlemi
 */
function processPasswordChange(PDO $pdo, int $user_id, array $post_data): array {
    try {
        $current_password = trim($post_data['current_password'] ?? '');
        $new_password = trim($post_data['new_password'] ?? '');
        $confirm_password = trim($post_data['confirm_password'] ?? '');
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'Tüm alanları doldurunuz.'];
        }
        
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'Yeni şifreler eşleşmiyor.'];
        }
        
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'Yeni şifre en az 8 karakter olmalıdır.'];
        }
        
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
            return ['success' => false, 'message' => 'Şifre en az bir büyük harf, bir küçük harf, bir rakam ve bir özel karakter içermelidir.'];
        }
        
        // Mevcut şifreyi kontrol et
        $current_password_query = "SELECT password FROM users WHERE id = :user_id";
        $stmt = execute_safe_query($pdo, $current_password_query, [':user_id' => $user_id]);
        $current_hash = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $current_hash)) {
            return ['success' => false, 'message' => 'Mevcut şifreniz hatalı.'];
        }
        
        // Yeni şifreyi güncelle
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
        execute_safe_query($pdo, $update_query, [
            ':password' => $new_hash,
            ':user_id' => $user_id
        ]);
        
        // Audit log
        audit_log($pdo, $user_id, 'password_changed', 'user', $user_id, null, [
            'changed_at' => date('Y-m-d H:i:s'),
            'ip_address' => get_client_ip()
        ]);
        
        return ['success' => true, 'message' => 'Şifreniz başarıyla değiştirildi.'];
        
    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Şifre değiştirme sırasında bir hata oluştu.'];
    }
}

/**
 * E-posta değiştirme işlemi
 */
function processEmailChange(PDO $pdo, int $user_id, array $post_data): array {
    try {
        $new_email = trim($post_data['new_email'] ?? '');
        $password = trim($post_data['password_confirm'] ?? '');
        
        if (empty($new_email) || empty($password)) {
            return ['success' => false, 'message' => 'Tüm alanları doldurunuz.'];
        }
        
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Geçerli bir e-posta adresi giriniz.'];
        }
        
        // Mevcut şifreyi kontrol et
        $password_query = "SELECT password, email FROM users WHERE id = :user_id";
        $stmt = execute_safe_query($pdo, $password_query, [':user_id' => $user_id]);
        $user_data = $stmt->fetch();
        
        if (!password_verify($password, $user_data['password'])) {
            return ['success' => false, 'message' => 'Şifreniz hatalı.'];
        }
        
        if ($user_data['email'] === $new_email) {
            return ['success' => false, 'message' => 'Yeni e-posta adresi mevcut adresinizle aynı.'];
        }
        
        // E-posta kullanımda mı kontrol et
        $email_check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $stmt = execute_safe_query($pdo, $email_check_query, [
            ':email' => $new_email,
            ':user_id' => $user_id
        ]);
        
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Bu e-posta adresi zaten kullanımda.'];
        }
        
        // E-posta adresini güncelle
        $update_query = "UPDATE users SET email = :email, updated_at = NOW() WHERE id = :user_id";
        execute_safe_query($pdo, $update_query, [
            ':email' => $new_email,
            ':user_id' => $user_id
        ]);
        
        // Audit log
        audit_log($pdo, $user_id, 'email_changed', 'user', $user_id, 
            ['old_email' => $user_data['email']], 
            ['new_email' => $new_email]
        );
        
        return ['success' => true, 'message' => 'E-posta adresiniz başarıyla değiştirildi.'];
        
    } catch (Exception $e) {
        error_log("Email change error: " . $e->getMessage());
        return ['success' => false, 'message' => 'E-posta değiştirme sırasında bir hata oluştu.'];
    }
}

/**
 * Tüm oturumları sonlandır
 */
function revokeAllSessions(PDO $pdo, int $user_id): array {
    try {
        audit_log($pdo, $user_id, 'all_sessions_revoked', 'user', $user_id, null, [
            'revoked_at' => date('Y-m-d H:i:s'),
            'ip_address' => get_client_ip()
        ]);
        
        return ['success' => true, 'message' => 'Tüm oturumlar sonlandırıldı.'];
        
    } catch (Exception $e) {
        error_log("Session revoke error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Oturum sonlandırma sırasında bir hata oluştu.'];
    }
}

/**
 * Son giriş geçmişini çeker
 */
function getRecentLoginHistory(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT action, ip_address, user_agent, created_at, new_values
            FROM audit_log 
            WHERE user_id = :user_id 
            AND action IN ('login_success', 'login_failed', 'logout')
            ORDER BY created_at DESC 
            LIMIT 10
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll() ?: [];
        
    } catch (Exception $e) {
        error_log("Login history error: " . $e->getMessage());
        return [];
    }
}

/**
 * Aktif oturumları çeker
 */
function getActiveSessions(PDO $pdo, int $user_id): array {
    return [
        [
            'session_id' => substr(session_id(), 0, 8) . '...',
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor',
            'last_activity' => date('Y-m-d H:i:s'),
            'is_current' => true
        ]
    ];
}

/**
 * Zaman formatlama fonksiyonu
 */
function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    if ($time < 2592000) return floor($time/604800) . ' hafta önce';
    
    return date('d.m.Y H:i', strtotime($datetime));
}

/**
 * User agent'ı okunabilir hale getirir
 */
function parseUserAgent($user_agent) {
    if (strpos($user_agent, 'Chrome') !== false) {
        return 'Chrome Tarayıcısı';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        return 'Firefox Tarayıcısı';
    } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
        return 'Safari Tarayıcısı';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        return 'Microsoft Edge';
    } else {
        return 'Bilinmeyen Tarayıcı';
    }
}
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="css/security.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="profile-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/index.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="/profile.php">
                    <i class="fas fa-user"></i> Profil
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-shield-alt"></i> Güvenlik
            </li>
        </ol>
    </nav>

    <div class="profile-container">
        <!-- Sidebar -->
        <?php include '../src/includes/profile_sidebar.php'; ?>

        <!-- Ana İçerik -->
        <div class="profile-main-content">
            <!-- Security Header -->
            <div class="security-header">
                <h1 class="security-title">
                    <i class="fas fa-shield-alt"></i>
                    Güvenlik Ayarları
                </h1>
                <p class="security-description">
                    Hesabınızın güvenliğini sağlamak için şifrenizi ve e-posta adresinizi yönetin
                </p>
            </div>

            <!-- Bildirimler -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Güvenlik Kartları Grid -->
            <div class="security-grid">
                <!-- Şifre Değiştirme -->
                <div class="profile-section">
                    <h3 class="section-title">
                        <i class="fas fa-key"></i>
                        Şifre Değiştir
                    </h3>
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Mevcut Şifre</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-input" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="new_password" class="form-label">Yeni Şifre</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-input" 
                                   required>
                            <small class="form-help">
                                En az 8 karakter, büyük/küçük harf, rakam ve özel karakter içermelidir.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-input" 
                                   required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full-width">
                            <i class="fas fa-save"></i>
                            Şifreyi Değiştir
                        </button>
                    </form>
                </div>

                <!-- E-posta Değiştirme -->
                <div class="profile-section">
                    <h3 class="section-title">
                        <i class="fas fa-envelope"></i>
                        E-posta Adresi
                    </h3>
                    <div class="current-email">
                        <strong>Mevcut E-posta:</strong> <?= htmlspecialchars($user_data['email']) ?>
                    </div>
                    
                    <form method="POST" id="emailForm">
                        <input type="hidden" name="action" value="change_email">
                        
                        <div class="form-group">
                            <label for="new_email" class="form-label">Yeni E-posta Adresi</label>
                            <input type="email" 
                                   id="new_email" 
                                   name="new_email" 
                                   class="form-input" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm" class="form-label">Şifreniz</label>
                            <input type="password" 
                                   id="password_confirm" 
                                   name="password_confirm" 
                                   class="form-input" 
                                   required>
                            <small class="form-help">
                                Değişikliği onaylamak için mevcut şifrenizi girin.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full-width">
                            <i class="fas fa-save"></i>
                            E-postayı Değiştir
                        </button>
                    </form>
                </div>
            </div>

            <!-- Oturum Yönetimi -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-devices"></i>
                    Oturum Yönetimi
                </h3>
                <p>Aktif oturumlarınızı görüntüleyin ve yönetin.</p>
                
                <?php foreach ($active_sessions as $session): ?>
                    <div class="session-item <?= $session['is_current'] ? 'session-current' : '' ?>">
                        <div class="session-info">
                            <div><strong><?= parseUserAgent($session['user_agent']) ?></strong>
                                <?php if ($session['is_current']): ?>
                                    <span class="session-badge">Mevcut Oturum</span>
                                <?php endif; ?>
                            </div>
                            <div class="session-details">
                                IP: <?= htmlspecialchars($session['ip_address']) ?> • 
                                Son Aktivite: <?= formatTimeAgo($session['last_activity']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="revoke_sessions">
                    <button type="submit" class="btn btn-danger btn-full-width" 
                            onclick="return confirm('Diğer tüm cihazlardan çıkış yapmak istediğinize emin misiniz?')">
                        <i class="fas fa-sign-out-alt"></i>
                        Tüm Diğer Oturumları Sonlandır
                    </button>
                </form>
            </div>

            <!-- Giriş Geçmişi -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-history"></i>
                    Son Giriş Aktivitesi
                </h3>
                <div class="activity-list">
                    <?php if (empty($login_history)): ?>
                        <p class="no-content">Henüz giriş geçmişi bulunmuyor.</p>
                    <?php else: ?>
                        <?php foreach ($login_history as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-action">
                                        <?php
                                        $action_labels = [
                                            'login_success' => '<i class="fas fa-sign-in-alt text-success"></i> Başarılı Giriş',
                                            'login_failed' => '<i class="fas fa-times-circle text-danger"></i> Başarısız Giriş',
                                            'logout' => '<i class="fas fa-sign-out-alt text-warning"></i> Çıkış'
                                        ];
                                        echo $action_labels[$activity['action']] ?? htmlspecialchars($activity['action']);
                                        ?>
                                    </div>
                                    <div class="activity-details">
                                        IP: <?= htmlspecialchars($activity['ip_address']) ?> • 
                                        <?= parseUserAgent($activity['user_agent'] ?? '') ?>
                                    </div>
                                </div>
                                <div class="activity-time">
                                    <?= formatTimeAgo($activity['created_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hesap Bilgileri -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Hesap Bilgileri
                </h3>
                <div class="account-info">
                    <div class="info-item">
                        <span class="info-label">Kullanıcı Adı:</span>
                        <span class="info-value"><?= htmlspecialchars($user_data['username']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">E-posta Adresi:</span>
                        <span class="info-value"><?= htmlspecialchars($user_data['email']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Hesap Oluşturma Tarihi:</span>
                        <span class="info-value"><?= date('d.m.Y H:i', strtotime($user_data['created_at'])) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Son Güncelleme:</span>
                        <span class="info-value"><?= formatTimeAgo($user_data['updated_at']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Birincil Rol:</span>
                        <span class="info-value" style="color: <?= htmlspecialchars($user_data['primary_role_color']) ?>">
                            <?= htmlspecialchars($user_data['primary_role_name']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('emailForm').dataset.currentEmail = '<?= htmlspecialchars($user_data['email']) ?>';
</script>
<script src="js/security.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>