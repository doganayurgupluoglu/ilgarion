<?php
// profile/edit.php - Profil Düzenleme Sayfası

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
$user_data = getUserEditData($pdo, $current_user_id);

if (!$user_data) {
    $_SESSION['error_message'] = "Profil bilgileriniz yüklenemedi.";
    header('Location: /profile.php');
    exit;
}

// Sidebar için temel kullanıcı bilgilerini hazırla (getUserEditData'dan gelecek)
// Bu sayede sidebar'da rol bilgileri görünecek

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = processProfileUpdate($pdo, $current_user_id, $_POST);
    
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: /profile.php');
        exit;
    } else {
        $error_message = $result['message'];
    }
}

// Mevcut skill tags'leri çek
$all_skill_tags = getAllSkillTags($pdo);
$user_skill_tags = getUserSkillTagIds($pdo, $current_user_id);

$page_title = "Profili Düzenle - " . htmlspecialchars($user_data['username']);

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Kullanıcı düzenleme verilerini çeker
 */
function getUserEditData(PDO $pdo, int $user_id): ?array {
    try {
        $query = "
            SELECT u.id, u.username, u.email, u.ingame_name, u.discord_username,
                   u.avatar_path, u.profile_info, u.status,
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
            'ingame_name' => $user['ingame_name'] ?: '',
            'discord_username' => $user['discord_username'] ?: '',
            'avatar_path' => $avatar_path,
            'profile_info' => $user['profile_info'] ?: '',
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a'
        ];
        
    } catch (Exception $e) {
        error_log("Profile edit data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Tüm skill tags'leri çeker
 */
function getAllSkillTags(PDO $pdo): array {
    try {
        $query = "SELECT id, tag_name FROM skill_tags ORDER BY tag_name ASC";
        $stmt = execute_safe_query($pdo, $query);
        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log("Skill tags error: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının skill tag ID'lerini çeker
 */
function getUserSkillTagIds(PDO $pdo, int $user_id): array {
    try {
        $query = "SELECT skill_tag_id FROM user_skill_tags WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        error_log("User skill tags error: " . $e->getMessage());
        return [];
    }
}

/**
 * Profil güncelleme işlemi
 */
function processProfileUpdate(PDO $pdo, int $user_id, array $post_data): array {
    try {
        // Validation
        $ingame_name = trim($post_data['ingame_name'] ?? '');
        $discord_username = trim($post_data['discord_username'] ?? '');
        $profile_info = trim($post_data['profile_info'] ?? '');
        $selected_skills = $post_data['skill_tags'] ?? [];
        
        // Validation
        if (strlen($ingame_name) > 255) {
            return ['success' => false, 'message' => 'Oyun içi isim çok uzun.'];
        }
        
        if (strlen($discord_username) > 255) {
            return ['success' => false, 'message' => 'Discord kullanıcı adı çok uzun.'];
        }
        
        if (strlen($profile_info) > 2000) {
            return ['success' => false, 'message' => 'Profil açıklaması çok uzun (maksimum 2000 karakter).'];
        }
        
        // Discord username formatı kontrolü
        if (!empty($discord_username) && !preg_match('/^[a-zA-Z0-9._]{2,32}$/', $discord_username)) {
            return ['success' => false, 'message' => 'Discord kullanıcı adı geçersiz format.'];
        }
        
        // Transaction başlat
        $pdo->beginTransaction();
        
        // Kullanıcı bilgilerini güncelle
        $update_query = "
            UPDATE users 
            SET ingame_name = :ingame_name,
                discord_username = :discord_username,
                profile_info = :profile_info,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ";
        
        $update_params = [
            ':ingame_name' => $ingame_name ?: null,
            ':discord_username' => $discord_username ?: null,
            ':profile_info' => $profile_info ?: null,
            ':user_id' => $user_id
        ];
        
        $stmt = execute_safe_query($pdo, $update_query, $update_params);
        
        // Skill tags güncelle
        // Önce mevcut skill tags'leri sil
        $delete_skills_query = "DELETE FROM user_skill_tags WHERE user_id = :user_id";
        execute_safe_query($pdo, $delete_skills_query, [':user_id' => $user_id]);
        
        // Yeni skill tags'leri ekle
        if (!empty($selected_skills)) {
            $insert_skill_query = "INSERT INTO user_skill_tags (user_id, skill_tag_id) VALUES (:user_id, :skill_tag_id)";
            $insert_stmt = $pdo->prepare($insert_skill_query);
            
            foreach ($selected_skills as $skill_id) {
                $skill_id = (int)$skill_id;
                if ($skill_id > 0) {
                    $insert_stmt->execute([
                        ':user_id' => $user_id,
                        ':skill_tag_id' => $skill_id
                    ]);
                }
            }
        }
        
        // Audit log kaydı
        $audit_query = "
            INSERT INTO audit_log (user_id, action, target_type, target_id, new_values, ip_address, user_agent) 
            VALUES (:user_id, 'profile_updated', 'user', :target_id, :new_values, :ip, :user_agent)
        ";
        
        $new_values = json_encode([
            'ingame_name' => $ingame_name,
            'discord_username' => $discord_username,
            'profile_info_length' => strlen($profile_info),
            'skill_tags_count' => count($selected_skills)
        ]);
        
        execute_safe_query($pdo, $audit_query, [
            ':user_id' => $user_id,
            ':target_id' => $user_id,
            ':new_values' => $new_values,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Profil bilgileriniz başarıyla güncellendi.'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Profile update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Profil güncellenirken bir hata oluştu.'];
    }
}
?>

<link rel="stylesheet" href="/css/profile.css">
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
                <i class="fas fa-edit"></i> Düzenle
            </li>
        </ol>
    </nav>

    <div class="profile-container">
        <!-- Sidebar -->
        <?php include '../src/includes/profile_sidebar.php'; ?>

        <!-- Ana İçerik -->
        <div class="profile-main-content">
            <!-- Form Header -->
            <div class="form-header">
                <h1 class="form-title">
                    <i class="fas fa-edit"></i> Profil Bilgilerini Düzenle
                </h1>
                <p class="form-description">
                    Profil bilgilerinizi güncelleyin ve topluluktaki görünümünüzü özelleştirin.
                </p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Profil Düzenleme Formu -->
            <form method="POST" class="profile-edit-form" id="profileEditForm">
                <!-- Temel Bilgiler -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Temel Bilgiler
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Kullanıcı Adı
                            </label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($user_data['username']) ?>"
                                   disabled
                                   title="Kullanıcı adı değiştirilemez">
                            <small class="form-help">Kullanıcı adınız değiştirilemez.</small>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> E-posta Adresi
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($user_data['email']) ?>"
                                   disabled
                                   title="E-posta adresi güvenlik ayarlarından değiştirilebilir">
                            <small class="form-help">
                                E-posta adresinizi <a href="/profile/security.php">güvenlik ayarları</a> sayfasından değiştirebilirsiniz.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Oyun Bilgileri -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-gamepad"></i> Star Citizen Bilgileri
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="ingame_name" class="form-label">
                                <i class="fas fa-rocket"></i> Oyun İçi İsim
                            </label>
                            <input type="text" 
                                   id="ingame_name" 
                                   name="ingame_name" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($user_data['ingame_name']) ?>"
                                   maxlength="255"
                                   placeholder="Star Citizen'daki karakter adınız">
                            <small class="form-help">Star Citizen evrenindeki karakter adınızı girin.</small>
                        </div>

                        <div class="form-group">
                            <label for="discord_username" class="form-label">
                                <i class="fab fa-discord"></i> Discord Kullanıcı Adı
                            </label>
                            <input type="text" 
                                   id="discord_username" 
                                   name="discord_username" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($user_data['discord_username']) ?>"
                                   maxlength="255"
                                   placeholder="discord_kullanici_adi"
                                   pattern="[a-zA-Z0-9._]{2,32}">
                            <small class="form-help">Discord kullanıcı adınızı girin (@ işareti olmadan).</small>
                        </div>
                    </div>
                </div>

                <!-- Profil Açıklaması -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Hakkımda
                    </h3>
                    
                    <div class="form-group">
                        <label for="profile_info" class="form-label">
                            <i class="fas fa-edit"></i> Profil Açıklaması
                        </label>
                        <textarea id="profile_info" 
                                  name="profile_info" 
                                  class="form-textarea" 
                                  maxlength="2000"
                                  rows="6"
                                  placeholder="Kendiniz hakkında kısa bir açıklama yazın..."><?= htmlspecialchars($user_data['profile_info']) ?></textarea>
                        <small class="form-help">
                            <span id="charCount">0</span>/2000 karakter kullanıldı.
                        </small>
                    </div>
                </div>

                <!-- Beceri Alanları -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tags"></i> Beceri Alanları
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-star"></i> Hangi alanlarda yeteneklisiniz?
                        </label>
                        <div class="skill-tags-grid">
                            <?php foreach ($all_skill_tags as $skill): ?>
                                <label class="skill-checkbox">
                                    <input type="checkbox" 
                                           name="skill_tags[]" 
                                           value="<?= $skill['id'] ?>"
                                           <?= in_array($skill['id'], $user_skill_tags) ? 'checked' : '' ?>>
                                    <span class="skill-checkbox-custom">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <span class="skill-name"><?= htmlspecialchars($skill['tag_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-help">
                            Star Citizen'da hangi alanlarda yetenekli olduğunuzu belirtin. Bu bilgiler etkinlik organizasyonlarında kullanılabilir.
                        </small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Değişiklikleri Kaydet
                    </button>
                    <a href="/profile.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> İptal Et
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Karakter sayacı
    const profileInfo = document.getElementById('profile_info');
    const charCount = document.getElementById('charCount');
    
    function updateCharCount() {
        const count = profileInfo.value.length;
        charCount.textContent = count;
        
        if (count > 1800) {
            charCount.style.color = 'var(--red)';
        } else if (count > 1500) {
            charCount.style.color = 'var(--gold)';
        } else {
            charCount.style.color = 'var(--light-grey)';
        }
    }
    
    profileInfo.addEventListener('input', updateCharCount);
    updateCharCount(); // İlk yükleme
    
    // Form validation
    const form = document.getElementById('profileEditForm');
    
    form.addEventListener('submit', function(e) {
        const discordUsername = document.getElementById('discord_username').value;
        
        // Discord username validation
        if (discordUsername && !/^[a-zA-Z0-9._]{2,32}$/.test(discordUsername)) {
            e.preventDefault();
            alert('Discord kullanıcı adı geçersiz format. Sadece harf, rakam, nokta ve alt çizgi kullanabilirsiniz.');
            return;
        }
        
        // Character limit validation
        if (profileInfo.value.length > 2000) {
            e.preventDefault();
            alert('Profil açıklaması çok uzun. Maksimum 2000 karakter olmalıdır.');
            return;
        }
    });
    
    // Skill tags selection feedback
    const skillCheckboxes = document.querySelectorAll('input[name="skill_tags[]"]');
    
    skillCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selectedCount = document.querySelectorAll('input[name="skill_tags[]"]:checked').length;
            
            if (selectedCount > 10) {
                this.checked = false;
                alert('Maksimum 10 beceri alanı seçebilirsiniz.');
            }
        });
    });
});
</script>

<style>
/* Form özel stilleri */
.form-header {
    background: linear-gradient(135deg, var(--card-bg), var(--card-bg-2));
    border: 1px solid var(--border-1);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
}

.form-title {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 600;
    color: var(--gold);
}

.form-description {
    margin: 0;
    color: var(--light-grey);
    font-size: 1.1rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-error {
    background: var(--transparent-red);
    border: 1px solid var(--red);
    color: var(--red);
}

.profile-edit-form {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-section {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    padding: 2rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--lighter-grey);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.form-input,
.form-textarea {
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-1);
    border-radius: 8px;
    background: var(--card-bg-2);
    color: var(--lighter-grey);
    font-family: var(--font);
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--gold);
    background: var(--card-bg-3);
}

.form-input:disabled {
    background: var(--card-bg);
    color: var(--light-grey);
    cursor: not-allowed;
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

.form-help {
    color: var(--light-grey);
    font-size: 0.8rem;
}

.form-help a {
    color: var(--gold);
    text-decoration: none;
}

.form-help a:hover {
    text-decoration: underline;
}

.skill-tags-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.skill-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--card-bg-2);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.skill-checkbox:hover {
    background: var(--card-bg-3);
    border-color: var(--border-1-hover);
}

.skill-checkbox input[type="checkbox"] {
    display: none;
}

.skill-checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-1);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.skill-checkbox input[type="checkbox"]:checked + .skill-checkbox-custom {
    background: var(--turquase);
    border-color: var(--turquase);
    color: var(--white);
}

.skill-name {
    font-size: 0.9rem;
    color: var(--lighter-grey);
    font-weight: 500;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    padding: 2rem 0;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
}

@media (max-width: 768px) {
    .form-header {
        padding: 1.5rem;
    }
    
    .form-title {
        font-size: 1.6rem;
    }
    
    .form-section {
        padding: 1.5rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .skill-tags-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>