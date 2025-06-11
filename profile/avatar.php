<?php
// profile/avatar.php - Avatar Yönetimi Sayfası

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
$user_data = getUserAvatarData($pdo, $current_user_id);

if (!$user_data) {
    $_SESSION['error_message'] = "Kullanıcı bilgileriniz yüklenemedi.";
    header('Location: /profile.php');
    exit;
}

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_avatar':
                $result = uploadAvatar($pdo, $current_user_id, $_FILES);
                break;
            case 'remove_avatar':
                $result = removeAvatar($pdo, $current_user_id);
                break;

            default:
                $result = ['success' => false, 'message' => 'Geçersiz işlem.'];
        }
        
        // Sonuç mesajını session'a kaydet
        if ($result['success']) {
            $_SESSION['avatar_success_message'] = $result['message'];
        } else {
            $_SESSION['avatar_error_message'] = $result['message'];
        }
        
        // PRG Pattern: Redirect to prevent form resubmission
        header('Location: avatar.php');
        exit;
    }
}

// Session mesajlarını al ve temizle
$success_message = $_SESSION['avatar_success_message'] ?? null;
$error_message = $_SESSION['avatar_error_message'] ?? null;
unset($_SESSION['avatar_success_message'], $_SESSION['avatar_error_message']);

/**
 * Kullanıcının avatar verilerini çeker
 */
function getUserAvatarData(PDO $pdo, int $user_id): ?array {
    try {
        $query = "
            SELECT u.id, u.username, u.email, u.avatar_path,
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
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a'
        ];
        
    } catch (Exception $e) {
        error_log("Avatar data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Avatar yükleme işlemi
 */
function uploadAvatar(PDO $pdo, int $user_id, array $files): array {
    try {
        // Dosya kontrolü
        if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Dosya yükleme hatası.'];
        }
        
        $file = $files['avatar'];
        
        // Dosya boyutu kontrolü (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Dosya boyutu çok büyük. Maksimum 2MB olmalıdır.'];
        }
        
        // Dosya türü kontrolü
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return ['success' => false, 'message' => 'Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.'];
        }
        
        // Dosya boyutları kontrolü (getimagesize ile)
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return ['success' => false, 'message' => 'Geçersiz resim dosyası.'];
        }
        
        [$width, $height] = $image_info;
        
        // Minimum boyut kontrolü
        if ($width < 100 || $height < 100) {
            return ['success' => false, 'message' => 'Resim boyutu çok küçük. Minimum 100x100 piksel olmalıdır.'];
        }
        
        // Maksimum boyut kontrolü
        if ($width > 2000 || $height > 2000) {
            return ['success' => false, 'message' => 'Resim boyutu çok büyük. Maksimum 2000x2000 piksel olmalıdır.'];
        }
        
        // Upload klasörünü oluştur
        $upload_dir = BASE_PATH . '/uploads/avatars/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return ['success' => false, 'message' => 'Upload klasörü oluşturulamadı.'];
            }
        }
        
        // Benzersiz dosya adı oluştur
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . strtolower($file_extension);
        $upload_path = $upload_dir . $new_filename;
        $db_path = 'uploads/avatars/' . $new_filename;
        
        // Dosyayı taşı
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            return ['success' => false, 'message' => 'Dosya kaydedilemedi.'];
        }
        
        // Eski avatar'ı sil
        $old_avatar = getCurrentAvatarPath($pdo, $user_id);
        if ($old_avatar && strpos($old_avatar, 'uploads/') === 0) {
            $old_avatar_full_path = BASE_PATH . '/' . $old_avatar;
            if (file_exists($old_avatar_full_path)) {
                unlink($old_avatar_full_path);
            }
        }
        
        // Veritabanını güncelle
        $update_query = "UPDATE users SET avatar_path = :avatar_path WHERE id = :user_id";
        execute_safe_query($pdo, $update_query, [
            ':avatar_path' => $db_path,
            ':user_id' => $user_id
        ]);
        
        // Audit log
        audit_log($pdo, $user_id, 'avatar_uploaded', 'user', $user_id, null, [
            'new_avatar' => $db_path,
            'file_size' => $file['size'],
            'dimensions' => $width . 'x' . $height
        ]);
        
        return ['success' => true, 'message' => 'Avatar başarıyla güncellendi.'];
        
    } catch (Exception $e) {
        error_log("Avatar upload error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Avatar yüklenirken bir hata oluştu.'];
    }
}

/**
 * Avatar kaldırma işlemi
 */
function removeAvatar(PDO $pdo, int $user_id): array {
    try {
        // Mevcut avatar'ı al
        $current_avatar = getCurrentAvatarPath($pdo, $user_id);
        
        // Eğer upload edilmiş avatar varsa dosyayı sil
        if ($current_avatar && strpos($current_avatar, 'uploads/') === 0) {
            $avatar_full_path = BASE_PATH . '/' . $current_avatar;
            if (file_exists($avatar_full_path)) {
                unlink($avatar_full_path);
            }
        }
        
        // Veritabanını güncelle (NULL yapar)
        $update_query = "UPDATE users SET avatar_path = NULL WHERE id = :user_id";
        execute_safe_query($pdo, $update_query, [':user_id' => $user_id]);
        
        // Audit log
        audit_log($pdo, $user_id, 'avatar_removed', 'user', $user_id, ['old_avatar' => $current_avatar], null);
        
        return ['success' => true, 'message' => 'Avatar kaldırıldı. Varsayılan avatar kullanılacak.'];
        
    } catch (Exception $e) {
        error_log("Avatar remove error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Avatar kaldırılırken bir hata oluştu.'];
    }
}

/**
 * Mevcut avatar path'ini getirir
 */
function getCurrentAvatarPath(PDO $pdo, int $user_id): ?string {
    try {
        $query = "SELECT avatar_path FROM users WHERE id = :user_id";
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        error_log("Get current avatar error: " . $e->getMessage());
        return null;
    }
}

$page_title = "Avatar Yönetimi - " . ($_SESSION['username'] ?? 'Profil');

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/css/profile.css">
<link rel="stylesheet" href="css/avatar.css">
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
                <i class="fas fa-image"></i> Avatar
            </li>
        </ol>
    </nav>

    <div class="profile-container">
        <!-- Sidebar -->
        <?php include '../src/includes/profile_sidebar.php'; ?>

        <!-- Ana İçerik -->
        <div class="profile-main-content">
            <!-- Avatar Header -->
            <div class="avatar-header">
                <div class="avatar-title-section">
                    <h1 class="avatar-title">
                        <i class="fas fa-image"></i> Avatar Yönetimi
                    </h1>
                    <p class="avatar-description">
                        Profil resminizi yükleyin ve özelleştirin.
                    </p>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <!-- Mevcut Avatar -->
            <div class="current-avatar-section">
                <h3 class="section-title">
                    <i class="fas fa-user-circle"></i> Mevcut Avatar
                </h3>
                
                <div class="current-avatar-display">
                    <div class="avatar-preview">
                        <img src="<?= htmlspecialchars($user_data['avatar_path']) ?>" 
                             alt="<?= htmlspecialchars($user_data['username']) ?> Avatar" 
                             class="avatar-image">
                        <div class="avatar-info">
                            <h4 class="avatar-username"><?= htmlspecialchars($user_data['username']) ?></h4>
                            <div class="avatar-role" style="color: <?= htmlspecialchars($user_data['primary_role_color']) ?>">
                                <i class="fas fa-badge"></i> <?= htmlspecialchars($user_data['primary_role_name']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($user_data['avatar_path']) && $user_data['avatar_path'] !== '/assets/logo.png'): ?>
                        <div class="avatar-actions">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="remove_avatar">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Avatar\'ı kaldırmak istediğinizden emin misiniz?')">
                                    <i class="fas fa-trash"></i> Avatar'ı Kaldır
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Avatar Yükleme -->
            <div class="avatar-upload-section">
                <h3 class="section-title">
                    <i class="fas fa-upload"></i> Yeni Avatar Yükle
                </h3>
                
                <form method="POST" enctype="multipart/form-data" class="avatar-upload-form">
                    <input type="hidden" name="action" value="upload_avatar">
                    
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-text">
                            <h4>Dosyayı buraya sürükleyin veya seçin</h4>
                            <p>JPG, PNG, GIF, WebP formatları desteklenir</p>
                            <p>Maksimum boyut: 2MB, Minimum: 100x100px</p>
                        </div>
                        <input type="file" name="avatar" id="avatarFile" accept="image/*" required>
                    </div>
                    
                    <div class="file-preview" id="filePreview" style="display: none;">
                        <img id="previewImage" src="" alt="Preview">
                        <div class="preview-info">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="clearFileSelection()">
                                <i class="fas fa-times"></i> Temizle
                            </button>
                        </div>
                    </div>
                    
                    <div class="upload-actions">
                        <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                            <i class="fas fa-upload"></i> Avatar'ı Yükle
                        </button>
                    </div>
                </form>
            </div>

            <!-- Avatar Önerileri -->
            <div class="avatar-tips-section">
                <h3 class="section-title">
                    <i class="fas fa-lightbulb"></i> Avatar Önerileri
                </h3>
                
                <div class="tips-grid">
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-ruler"></i>
                        </div>
                        <div class="tip-content">
                            <h4>Boyut</h4>
                            <p>Minimum 100x100, maksimum 2000x2000 piksel. Kare formatı ideal.</p>
                        </div>
                    </div>
                    
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-file-image"></i>
                        </div>
                        <div class="tip-content">
                            <h4>Format</h4>
                            <p>JPG, PNG, GIF veya WebP formatlarını kullanın. Maksimum 2MB boyut.</p>
                        </div>
                    </div>
                    

                    
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="tip-content">
                            <h4>Güvenlik</h4>
                            <p>Kişisel bilgilerinizi içeren görseller kullanmayın.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/avatar.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>