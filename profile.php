<?php
// profile.php - Ana Profil Sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Giriş yapma zorunluluğu
require_login();

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'];
$is_approved = is_user_approved();

// Kullanıcı profil verilerini çek
$user_data = getUserProfileData($pdo, $current_user_id);

if (!$user_data) {
    $_SESSION['error_message'] = "Profil bilgileriniz yüklenemedi.";
    header('Location: /index.php');
    exit;
}

// Sayfa başlığı
$page_title = "Profil - " . htmlspecialchars($user_data['username']);

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Kullanıcı profil verilerini çeker
 */
function getUserProfileData(PDO $pdo, int $user_id): ?array {
    try {
        // Temel kullanıcı bilgileri ve birinci rol
        $query = "
            SELECT u.id, u.username, u.email, u.ingame_name, u.discord_username,
                   u.avatar_path, u.profile_info, u.status, u.created_at, u.updated_at,
                   (SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_name,
                   (SELECT r.color FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_color,
                   GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.priority ASC SEPARATOR ', ') AS all_roles
            FROM users u
            LEFT JOIN user_roles ur2 ON u.id = ur2.user_id
            LEFT JOIN roles r2 ON ur2.role_id = r2.id
            WHERE u.id = :user_id
            GROUP BY u.id
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
        
        // Forum istatistikleri
        $forum_stats = getForumStats($pdo, $user_id);
        
        // Galeri istatistikleri
        $gallery_stats = getGalleryStats($pdo, $user_id);
        
        // Hangar istatistikleri
        $hangar_stats = getHangarStats($pdo, $user_id);
        
        // Skill tags
        $skill_tags = getUserSkillTags($pdo, $user_id);
        
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'ingame_name' => $user['ingame_name'] ?: '',
            'discord_username' => $user['discord_username'] ?: '',
            'avatar_path' => $avatar_path,
            'profile_info' => $user['profile_info'] ?: '',
            'status' => $user['status'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a',
            'all_roles' => $user['all_roles'] ?: '',
            'forum_stats' => $forum_stats,
            'gallery_stats' => $gallery_stats,
            'hangar_stats' => $hangar_stats,
            'skill_tags' => $skill_tags
        ];
        
    } catch (Exception $e) {
        error_log("Profile data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Forum istatistiklerini çeker
 */
function getForumStats(PDO $pdo, int $user_id): array {
    try {
        $topic_query = "SELECT COUNT(*) FROM forum_topics WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $topic_query, [':user_id' => $user_id]);
        $topic_count = $stmt->fetchColumn();
        
        $post_query = "SELECT COUNT(*) FROM forum_posts WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $post_query, [':user_id' => $user_id]);
        $post_count = $stmt->fetchColumn();
        
        $likes_query = "
            SELECT 
                (SELECT COUNT(*) FROM forum_topic_likes ftl 
                 JOIN forum_topics ft ON ftl.topic_id = ft.id 
                 WHERE ft.user_id = :user_id) +
                (SELECT COUNT(*) FROM forum_post_likes fpl 
                 JOIN forum_posts fp ON fpl.post_id = fp.id 
                 WHERE fp.user_id = :user_id) as total_likes
        ";
        $stmt = execute_safe_query($pdo, $likes_query, [':user_id' => $user_id]);
        $likes_received = $stmt->fetchColumn();
        
        return [
            'topics' => (int)$topic_count,
            'posts' => (int)$post_count,
            'likes_received' => (int)$likes_received
        ];
    } catch (Exception $e) {
        error_log("Forum stats error: " . $e->getMessage());
        return ['topics' => 0, 'posts' => 0, 'likes_received' => 0];
    }
}

/**
 * Galeri istatistiklerini çeker
 */
function getGalleryStats(PDO $pdo, int $user_id): array {
    try {
        $photos_query = "SELECT COUNT(*) FROM gallery_photos WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $photos_query, [':user_id' => $user_id]);
        $photos_count = $stmt->fetchColumn();
        
        $likes_query = "
            SELECT COUNT(*) FROM gallery_photo_likes gpl 
            JOIN gallery_photos gp ON gpl.photo_id = gp.id 
            WHERE gp.user_id = :user_id
        ";
        $stmt = execute_safe_query($pdo, $likes_query, [':user_id' => $user_id]);
        $photo_likes = $stmt->fetchColumn();
        
        return [
            'photos' => (int)$photos_count,
            'likes_received' => (int)$photo_likes
        ];
    } catch (Exception $e) {
        error_log("Gallery stats error: " . $e->getMessage());
        return ['photos' => 0, 'likes_received' => 0];
    }
}

/**
 * Hangar istatistiklerini çeker
 */
function getHangarStats(PDO $pdo, int $user_id): array {
    try {
        $ships_query = "SELECT COUNT(*), SUM(quantity) FROM user_hangar WHERE user_id = :user_id";
        $stmt = execute_safe_query($pdo, $ships_query, [':user_id' => $user_id]);
        $result = $stmt->fetch();
        
        return [
            'unique_ships' => (int)($result[0] ?? 0),
            'total_ships' => (int)($result[1] ?? 0)
        ];
    } catch (Exception $e) {
        error_log("Hangar stats error: " . $e->getMessage());
        return ['unique_ships' => 0, 'total_ships' => 0];
    }
}

/**
 * Kullanıcı skill tags'lerini çeker
 */
function getUserSkillTags(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT st.id, st.tag_name
            FROM skill_tags st
            JOIN user_skill_tags ust ON st.id = ust.skill_tag_id
            WHERE ust.user_id = :user_id
            ORDER BY st.tag_name ASC
        ";
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log("Skill tags error: " . $e->getMessage());
        return [];
    }
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
            <li class="breadcrumb-item active">
                <i class="fas fa-user"></i> Profil
            </li>
        </ol>
    </nav>

    <div class="profile-container">
        <!-- Sidebar -->
        <?php include 'src/includes/profile_sidebar.php'; ?>

        <!-- Ana Profil İçeriği -->
        <div class="profile-main-content">
            <!-- Profil Header -->
            <div class="profile-header">
                <div class="profile-avatar-section">
                    <div class="profile-avatar">
                        <img src="<?= htmlspecialchars($user_data['avatar_path']) ?>" 
                             alt="<?= htmlspecialchars($user_data['username']) ?> Avatarı" 
                             class="avatar-img">
                        <div class="avatar-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                </div>
                
                <div class="profile-info-section">
                    <h1 class="profile-username"><?= htmlspecialchars($user_data['username']) ?></h1>
                    <div class="profile-role" style="color: <?= htmlspecialchars($user_data['primary_role_color']) ?>">
                        <i class="fas fa-badge"></i> <?= htmlspecialchars($user_data['primary_role_name']) ?>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-gamepad"></i> Oyun İçi İsim:
                            </span>
                            <span class="detail-value">
                                <?= htmlspecialchars($user_data['ingame_name']) ?: 'Belirtilmemiş' ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fab fa-discord"></i> Discord:
                            </span>
                            <span class="detail-value">
                                <?= htmlspecialchars($user_data['discord_username']) ?: 'Belirtilmemiş' ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-calendar-alt"></i> Üyelik Tarihi:
                            </span>
                            <span class="detail-value">
                                <?= date('d.m.Y', strtotime($user_data['created_at'])) ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-clock"></i> Son Güncelleme:
                            </span>
                            <span class="detail-value">
                                <?= formatTimeAgo($user_data['updated_at']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="/profile/edit.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Profili Düzenle
                    </a>
                </div>
            </div>

            <!-- Profil Hakkında -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i> Hakkımda
                </h3>
                <div class="profile-about">
                    <?php if (!empty($user_data['profile_info'])): ?>
                        <p><?= nl2br(htmlspecialchars($user_data['profile_info'])) ?></p>
                    <?php else: ?>
                        <p class="no-content">Henüz bir açıklama eklenmemiş.</p>
                        <a href="/profile/edit.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-plus"></i> Açıklama Ekle
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Roller -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-users-cog"></i> Roller
                </h3>
                <div class="user-roles">
                    <?php if (!empty($user_data['all_roles'])): ?>
                        <?php $roles = explode(', ', $user_data['all_roles']); ?>
                        <?php foreach ($roles as $role): ?>
                            <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-content">Henüz rol atanmamış.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Beceri Etiketleri -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-tags"></i> Beceri Alanları
                </h3>
                <div class="skill-tags">
                    <?php if (!empty($user_data['skill_tags'])): ?>
                        <?php foreach ($user_data['skill_tags'] as $skill): ?>
                            <span class="skill-tag">
                                <i class="fas fa-star"></i> <?= htmlspecialchars($skill['tag_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-content">Henüz beceri alanı eklenmemiş.</p>
                        <a href="/profile/edit.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-plus"></i> Beceri Ekle
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- İstatistikler -->
            <div class="profile-stats-grid">
                <!-- Forum İstatistikleri -->
                <div class="stat-card">
                    <div class="stat-header">
                        <h4><i class="fas fa-comments"></i> Forum Aktivitesi</h4>
                    </div>
                    <div class="stat-content">
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['forum_stats']['topics']) ?></span>
                            <span class="stat-label">Konu</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['forum_stats']['posts']) ?></span>
                            <span class="stat-label">Gönderi</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['forum_stats']['likes_received']) ?></span>
                            <span class="stat-label">Beğeni</span>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="/forum/" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt"></i> Foruma Git
                        </a>
                    </div>
                </div>

                <!-- Galeri İstatistikleri -->
                <div class="stat-card">
                    <div class="stat-header">
                        <h4><i class="fas fa-images"></i> Galeri Aktivitesi</h4>
                    </div>
                    <div class="stat-content">
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['gallery_stats']['photos']) ?></span>
                            <span class="stat-label">Fotoğraf</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['gallery_stats']['likes_received']) ?></span>
                            <span class="stat-label">Beğeni</span>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="/gallery/" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt"></i> Galeriye Git
                        </a>
                    </div>
                </div>

                <!-- Hangar İstatistikleri -->
                <div class="stat-card">
                    <div class="stat-header">
                        <h4><i class="fas fa-space-shuttle"></i> Hangar</h4>
                    </div>
                    <div class="stat-content">
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['hangar_stats']['unique_ships']) ?></span>
                            <span class="stat-label">Farklı Gemi</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($user_data['hangar_stats']['total_ships']) ?></span>
                            <span class="stat-label">Toplam Gemi</span>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="/profile/hangar.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt"></i> Hangarı Görüntüle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>