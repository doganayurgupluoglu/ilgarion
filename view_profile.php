<?php
// view_profile.php - Kullanıcı Profil Görüntüleme Sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Giriş yapma zorunluluğu
require_login();

// URL parametrelerini al
$target_user_id = null;
$target_username = null;

if (isset($_GET['user_id'])) {
    $target_user_id = (int)$_GET['user_id'];
} elseif (isset($_GET['username'])) {
    $target_username = trim($_GET['username']);
} else {
    $_SESSION['error_message'] = "Görüntülenecek kullanıcı belirtilmedi.";
    header('Location: /index.php');
    exit;
}

// Hedef kullanıcının bilgilerini çek
$viewed_user_data = getViewedUserProfileData($pdo, $target_user_id, $target_username);

if (!$viewed_user_data) {
    $_SESSION['error_message'] = "Kullanıcı bulunamadı.";
    header('Location: /index.php');
    exit;
}

// Kendi profilini mi görüntülüyor kontrol et
$is_own_profile = $_SESSION['user_id'] == $viewed_user_data['id'];

// Sayfa başlığı
$page_title = "Profil - " . htmlspecialchars($viewed_user_data['username']);

// Sidebar için mevcut kullanıcı bilgileri (kendi sidebar'ı için)
$current_user_id = $_SESSION['user_id'];
$user_data = getUserProfileData($pdo, $current_user_id);

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Görüntülenecek kullanıcı profil verilerini çeker
 */
function getViewedUserProfileData(PDO $pdo, ?int $user_id, ?string $username): ?array {
    try {
        // Kullanıcıyı ID veya username ile bul
        if ($user_id) {
            $condition = "u.id = :identifier";
            $param = [':identifier' => $user_id];
        } else {
            $condition = "u.username = :identifier";
            $param = [':identifier' => $username];
        }
        
        // Temel kullanıcı bilgileri ve roller
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
            WHERE $condition
            GROUP BY u.id
        ";
        
        $stmt = execute_safe_query($pdo, $query, $param);
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
        $forum_stats = getForumStats($pdo, $user['id']);
        
        // Galeri istatistikleri ve preview
        $gallery_stats = getGalleryStats($pdo, $user['id']);
        $gallery_preview = getGalleryPreview($pdo, $user['id']);
        
        // Hangar istatistikleri ve preview
        $hangar_stats = getHangarStats($pdo, $user['id']);
        $hangar_preview = getHangarPreview($pdo, $user['id']);
        
        // Skill tags
        $skill_tags = getUserSkillTags($pdo, $user['id']);
        
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
            'gallery_preview' => $gallery_preview,
            'hangar_stats' => $hangar_stats,
            'hangar_preview' => $hangar_preview,
            'skill_tags' => $skill_tags
        ];
        
    } catch (Exception $e) {
        error_log("Viewed profile data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Kullanıcı profil verilerini çeker (mevcut profile.php'den)
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
            'avatar_path' => $avatar_path,
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a'
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
        
        $topic_likes_query = "
            SELECT COUNT(*) FROM forum_topic_likes ftl 
            JOIN forum_topics ft ON ftl.topic_id = ft.id 
            WHERE ft.user_id = :user_id
        ";
        $stmt = execute_safe_query($pdo, $topic_likes_query, [':user_id' => $user_id]);
        $topic_likes_received = $stmt->fetchColumn();

        $post_likes_query = "
            SELECT COUNT(*) FROM forum_post_likes fpl 
            JOIN forum_posts fp ON fpl.post_id = fp.id 
            WHERE fp.user_id = :user_id
        ";
        $stmt = execute_safe_query($pdo, $post_likes_query, [':user_id' => $user_id]);
        $post_likes_received = $stmt->fetchColumn();
        
        $likes_received = (int)$topic_likes_received + (int)$post_likes_received;
        
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
 * Galeri önizlemesi çeker
 */
function getGalleryPreview(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT id, title, file_path, created_at,
                   (SELECT COUNT(*) FROM gallery_photo_likes WHERE photo_id = gp.id) as like_count
            FROM gallery_photos gp 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 6
        ";
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log("Gallery preview error: " . $e->getMessage());
        return [];
    }
}

/**
 * Hangar istatistiklerini çeker
 */
function getHangarStats(PDO $pdo, int $user_id): array {
    try {
        $ships_query = "
            SELECT 
                COUNT(id) as unique_ships, 
                SUM(quantity) as total_ships 
            FROM user_hangar 
            WHERE user_id = :user_id
        ";
        $stmt = execute_safe_query($pdo, $ships_query, [':user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'unique_ships' => (int)($result['unique_ships'] ?? 0),
            'total_ships' => (int)($result['total_ships'] ?? 0)
        ];
    } catch (Exception $e) {
        error_log("Hangar stats error: " . $e->getMessage());
        return ['unique_ships' => 0, 'total_ships' => 0];
    }
}

/**
 * Hangar önizlemesi çeker
 */
function getHangarPreview(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT uh.id, uh.ship_name, uh.manufacturer, uh.quantity, uh.acquired_date,
                   si.image_path, si.size_category, si.role_category
            FROM user_hangar uh
            LEFT JOIN ship_info si ON uh.ship_name = si.ship_name
            WHERE uh.user_id = :user_id 
            ORDER BY uh.acquired_date DESC 
            LIMIT 8
        ";
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log("Hangar preview error: " . $e->getMessage());
        return [];
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
            <?php if ($is_own_profile): ?>
            <li class="breadcrumb-item">
                <a href="/profile.php">
                    <i class="fas fa-user"></i> Profil
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-eye"></i> Görüntüle
            </li>
            <?php else: ?>
            <li class="breadcrumb-item active">
                <i class="fas fa-user"></i> <?= htmlspecialchars($viewed_user_data['username']) ?>
            </li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="profile-container">
        <!-- Sidebar -->
        <?php include 'src/includes/view_profile_sidebar.php'; ?>

        <!-- Ana Profil İçeriği -->
        <div class="profile-main-content">
            <!-- Profil Header -->
            <div class="profile-header">
                <div class="profile-avatar-section">
                    <div class="profile-avatar">
                        <img src="<?= htmlspecialchars($viewed_user_data['avatar_path']) ?>" 
                             alt="<?= htmlspecialchars($viewed_user_data['username']) ?> Avatarı" 
                             class="avatar-img">
                    </div>
                </div>
                
                <div class="profile-info-section">
                    <h1 class="profile-username" style="color: <?= htmlspecialchars($viewed_user_data['primary_role_color']) ?>"><?= htmlspecialchars($viewed_user_data['username']) ?></h1>
                    <div class="profile-role" style="color: <?= htmlspecialchars($viewed_user_data['primary_role_color']) ?>">
                        <?= htmlspecialchars($viewed_user_data['primary_role_name']) ?>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-gamepad"></i> Oyun İçi İsim:
                            </span>
                            <span class="detail-value">
                                <?= htmlspecialchars($viewed_user_data['ingame_name']) ?: 'Belirtilmemiş' ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fab fa-discord"></i> Discord:
                            </span>
                            <span class="detail-value">
                                <?= htmlspecialchars($viewed_user_data['discord_username']) ?: 'Belirtilmemiş' ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-calendar-alt"></i> Üyelik Tarihi:
                            </span>
                            <span class="detail-value">
                                <?= date('d.m.Y', strtotime($viewed_user_data['created_at'])) ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">
                                <i class="fas fa-clock"></i> Son Aktivite:
                            </span>
                            <span class="detail-value">
                                <?= formatTimeAgo($viewed_user_data['updated_at']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <?php if ($is_own_profile): ?>
                        <a href="/profile/edit.php" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Profili Düzenle
                        </a>
                    <?php else: ?>
                        <a href="/profile.php" class="btn btn-secondary">
                            <i class="fas fa-user"></i> Kendi Profilim
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profil Hakkında -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i> Hakkında
                </h3>
                <div class="profile-about">
                    <?php if (!empty($viewed_user_data['profile_info'])): ?>
                        <p><?= nl2br(htmlspecialchars($viewed_user_data['profile_info'])) ?></p>
                    <?php else: ?>
                        <p class="no-content">Henüz bir açıklama eklenmemiş.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Yetenekler -->
            <?php if (!empty($viewed_user_data['skill_tags'])): ?>
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-tags"></i> Yetenekler
                </h3>
                <div class="skill-tags">
                    <?php foreach ($viewed_user_data['skill_tags'] as $tag): ?>
                        <span class="skill-tag"><?= htmlspecialchars($tag['tag_name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- İstatistikler -->
            <div class="profile-stats-grid">
                <!-- Forum İstatistikleri -->
                <div class="stat-card">
                    <div class="stat-header">
                        <h4><i class="fas fa-comments"></i> Forum Aktivitesi</h4>
                    </div>
                    <div class="stat-content">
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($viewed_user_data['forum_stats']['topics']) ?></span>
                            <span class="stat-label">Konu</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($viewed_user_data['forum_stats']['posts']) ?></span>
                            <span class="stat-label">Gönderi</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($viewed_user_data['forum_stats']['likes_received']) ?></span>
                            <span class="stat-label">Beğeni</span>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="/view/forum_topics.php?user_id=<?= $viewed_user_data['id'] ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt"></i> Konularını Görüntüle
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
                            <span class="stat-number"><?= number_format($viewed_user_data['gallery_stats']['photos']) ?></span>
                            <span class="stat-label">Fotoğraf</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($viewed_user_data['gallery_stats']['likes_received']) ?></span>
                            <span class="stat-label">Beğeni</span>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="/view/gallery_photos.php?user_id=<?= $viewed_user_data['id'] ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt"></i> Fotoğraflarını Görüntüle
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
                            <span class="stat-number"><?= number_format($viewed_user_data['hangar_stats']['unique_ships']) ?></span>
                            <span class="stat-label">Farklı Gemi</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($viewed_user_data['hangar_stats']['total_ships']) ?></span>
                            <span class="stat-label">Toplam Gemi</span>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="/view/hangar_ships.php?user_id=<?= $viewed_user_data['id'] ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt"></i> Hangarını Görüntüle
                        </a>
                    </div>
                </div>
            </div>

            <!-- Galeri Önizleme -->
            <?php if (!empty($viewed_user_data['gallery_preview'])): ?>
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-camera"></i> Son Fotoğraflar
                    <a href="/view/gallery_photos.php?user_id=<?= $viewed_user_data['id'] ?>" class="btn btn-outline btn-sm" style="margin-left: auto;">
                        <i class="fas fa-arrow-right"></i> Tümünü Gör
                    </a>
                </h3>
                <div class="gallery-preview-grid">
                    <?php foreach ($viewed_user_data['gallery_preview'] as $photo): ?>
                        <div class="gallery-preview-item">
                            <a href="/gallery/photo.php?id=<?= $photo['id'] ?>" class="gallery-preview-link">
                                <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($photo['title']) ?>" 
                                     class="gallery-preview-img">
                                <div class="gallery-preview-overlay">
                                    <div class="gallery-preview-info">
                                        <h5><?= htmlspecialchars($photo['title']) ?></h5>
                                        <div class="gallery-preview-stats">
                                            <span><i class="fas fa-heart"></i> <?= $photo['like_count'] ?></span>
                                            <span><i class="fas fa-clock"></i> <?= formatTimeAgo($photo['created_at']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hangar Önizleme -->
            <?php if (!empty($viewed_user_data['hangar_preview'])): ?>
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-rocket"></i> Son Eklenen Gemiler
                    <a href="/view/hangar_ships.php?user_id=<?= $viewed_user_data['id'] ?>" class="btn btn-outline btn-sm" style="margin-left: auto;">
                        <i class="fas fa-arrow-right"></i> Tüm Hangar
                    </a>
                </h3>
                <div class="hangar-preview-grid">
                    <?php foreach ($viewed_user_data['hangar_preview'] as $ship): ?>
                        <div class="hangar-preview-item">
                            <div class="hangar-ship-image">
                                <?php if (!empty($ship['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($ship['image_path']) ?>" 
                                         alt="<?= htmlspecialchars($ship['ship_name']) ?>" 
                                         class="hangar-ship-img">
                                <?php else: ?>
                                    <div class="hangar-ship-placeholder">
                                        <i class="fas fa-space-shuttle"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="hangar-ship-info">
                                <h5 class="hangar-ship-name"><?= htmlspecialchars($ship['ship_name']) ?></h5>
                                <p class="hangar-ship-manufacturer"><?= htmlspecialchars($ship['manufacturer']) ?></p>
                                <div class="hangar-ship-details">
                                    <span class="hangar-ship-quantity">
                                        <i class="fas fa-hashtag"></i> <?= $ship['quantity'] ?>
                                    </span>
                                    <?php if (!empty($ship['size_category'])): ?>
                                    <span class="hangar-ship-size">
                                        <i class="fas fa-expand-arrows-alt"></i> <?= htmlspecialchars($ship['size_category']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="hangar-ship-acquired">
                                    <i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($ship['acquired_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Skill Tags */
.skill-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.skill-tag {
    background: var(--card-bg-2);
    color: var(--gold);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1px solid var(--border-1);
    transition: all 0.3s ease;
}

.skill-tag:hover {
    background: var(--gold);
    color: var(--body-bg);
    transform: translateY(-2px);
}

/* Gallery Preview */
.gallery-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.gallery-preview-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 1;
    border: 1px solid var(--border-1);
    transition: transform 0.3s ease;
}

.gallery-preview-item:hover {
    transform: translateY(-4px);
}

.gallery-preview-link {
    display: block;
    position: relative;
    width: 100%;
    height: 100%;
}

.gallery-preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: flex-end;
    padding: 1rem;
}

.gallery-preview-item:hover .gallery-preview-overlay {
    opacity: 1;
}

.gallery-preview-info h5 {
    margin: 0 0 0.5rem 0;
    color: var(--white);
    font-size: 0.9rem;
    font-weight: 500;
}

.gallery-preview-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--light-grey);
}

/* Hangar Preview */
.hangar-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}

.hangar-preview-item {
    background: var(--card-bg-2);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.hangar-preview-item:hover {
    transform: translateY(-4px);
    border-color: var(--gold);
}

.hangar-ship-image {
    height: 120px;
    overflow: hidden;
    background: var(--card-bg-3);
    display: flex;
    align-items: center;
    justify-content: center;
}

.hangar-ship-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hangar-ship-placeholder {
    color: var(--light-grey);
    font-size: 2rem;
}

.hangar-ship-info {
    padding: 1rem;
}

.hangar-ship-name {
    margin: 0 0 0.25rem 0;
    color: var(--gold);
    font-size: 1rem;
    font-weight: 600;
}

.hangar-ship-manufacturer {
    margin: 0 0 0.75rem 0;
    color: var(--light-grey);
    font-size: 0.85rem;
}

.hangar-ship-details {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.5rem;
    font-size: 0.8rem;
    color: var(--lighter-grey);
}

.hangar-ship-acquired {
    font-size: 0.75rem;
    color: var(--light-grey);
}

/* Section title flex */
.section-title {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
}

/* Responsive */
@media (max-width: 768px) {
    .gallery-preview-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .hangar-preview-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .profile-actions {
        flex-direction: row;
        justify-content: center;
    }
}
</style>

<?php include BASE_PATH . '/src/includes/footer.php'; ?> 