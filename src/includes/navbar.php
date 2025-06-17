<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gerekli dosyaları dahil et
// BASE_PATH tanımını kontrol et ve ayarla
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Kullanıcı durumunu ve bilgilerini al
$is_logged_in = is_user_logged_in();
$user_info = null;
$current_user_id = null;

if ($is_logged_in) {
    $current_user_id = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.avatar_path,
                   (SELECT r.color FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_color
            FROM users u WHERE u.id = :user_id
        ");
        $stmt->execute([':user_id' => $current_user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Avatar yolu kontrolü ve düzeltmesi
        if (empty($user_info['avatar_path'])) {
            $user_info['avatar_path'] = '/assets/logo.png';
        } elseif (strpos($user_info['avatar_path'], 'uploads/avatars/') === 0) {
            $user_info['avatar_path'] = '/' . $user_info['avatar_path'];
        }

    } catch (PDOException $e) {
        error_log("Navbar user info fetch error: " . $e->getMessage());
        $is_logged_in = false; // Hata durumunda çıkış yapmış gibi davran
    }
}


// "Oluştur" dropdown için yetki kontrolü
$can_create_anything = false;
$create_permissions = [];
if ($is_logged_in) {
    $perms_to_check = [
        'forum.topic.create' => ['url' => '/forum/create_topic.php', 'text' => 'Konu Aç', 'icon' => 'fas fa-file-alt'],
        'event.create' => ['url' => '/events/create.php', 'text' => 'Etkinlik Oluştur', 'icon' => 'fas fa-calendar-plus'],
        'loadout.create_sets' => ['url' => '/profile/loadouts/create.php', 'text' => 'Teçhizat Seti Oluştur', 'icon' => 'fas fa-user-shield'],
        'event_role.create' => ['url' => '/events/roles/create.php', 'text' => 'Rol Oluştur', 'icon' => 'fas fa-user-tag'],
        'gallery.upload' => ['url' => '/gallery/upload.php', 'text' => 'Fotoğraf Yükle', 'icon' => 'fas fa-camera']
    ];
    foreach ($perms_to_check as $perm => $details) {
        if (has_permission($pdo, $perm)) {
            $create_permissions[$perm] = $details;
            $can_create_anything = true;
        }
    }
}

// Get current page URI for active link styling
$current_page_uri = $_SERVER['REQUEST_URI'];

// Yönetim paneli erişim yetkisi
$can_access_admin_panel = $is_logged_in && has_permission($pdo, 'admin.panel.access');

?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/css/navbar.css">

<nav class="main-navbar">
    <div class="navbar-container">
        <!-- Sol Taraf -->
        <div class="navbar-left">
            <a href="/index.php" class="navbar-brand-link">
                <img src="/assets/scg-logo.png" alt="SCG Logo" class="logo-img">
                <span class="navbar-brand-text">Star Citizen Global</span>
            </a>
        </div>

        <!-- Orta -->
        <div class="navbar-center">
            <ul class="navbar-nav">
                <li class="nav-item"><a href="/forum/" class="nav-link <?= (strpos($current_page_uri, '/forum/') === 0) ? 'active' : '' ?>"><i class="fas fa-comments"></i><span>Forum</span></a></li>
                
                <li class="nav-item"><a href="/events/" class="nav-link <?= (strpos($current_page_uri, '/events/') === 0) ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i><span>Etkinlikler</span></a></li>
                <li class="nav-item"><a href="/gallery/" class="nav-link <?= (strpos($current_page_uri, '/gallery/') === 0) ? 'active' : '' ?>"><i class="fas fa-images"></i><span>Galeri</span></a></li>
                <li class="nav-item"><a href="/users/" class="nav-link <?= (strpos($current_page_uri, '/users/') === 0) ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Üyeler</span></a></li>
                <li class="nav-item"><a href="/erkul.php" class="nav-link <?= (strpos($current_page_uri, '/erkul.php') === 0) ? 'active' : '' ?>"><i class="fa-solid fa-calculator"></i><span>Erkul</span></a></li>
                <li class="nav-item"><a href="/teams/" class="nav-link <?= (strpos($current_page_uri, '/teams/') === 0) ? 'active' : '' ?>"><i class="fas fa-comments"></i><span>Takımlar</span></a></li>
                <?php if (has_permission($pdo, 'scg.hangar.view')): ?>
                <li class="nav-item"><a href="/scghangar/" class="nav-link <?= (strpos($current_page_uri, '/scghangar/') === 0) ? 'active' : '' ?>" style="color: #9a3c3c;"><i class="fas fa-rocket"></i><span>SCG Hangar</span></a></li>
                <?php endif; ?>

                <?php if ($can_create_anything): ?>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-plus-circle"></i><span>Oluştur</span><i class="fas fa-caret-down"></i>
                    </a>
                    <div class="dropdown-menu create-dropdown">
                        <?php foreach ($create_permissions as $perm => $details): ?>
                            <a class="dropdown-item" href="<?= htmlspecialchars($details['url']) ?>">
                                <i class="<?= htmlspecialchars($details['icon']) ?>"></i>
                                <span><?= htmlspecialchars($details['text']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Sağ Taraf -->
        <div class="navbar-right">
            <?php if (!$is_logged_in): ?>
                <div class="auth-links">
                    <a href="/register.php?mode=login" class="btn btn-nav-secondary">Giriş Yap</a>
                    <a href="/register.php?mode=register" class="btn btn-nav-primary">Kayıt Ol</a>
                </div>
            <?php else: ?>
                <div class="user-actions">
                    <div class="nav-item dropdown notification-dropdown">
                        <a href="#" class="nav-link notification-bell" data-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="notification-count" style="display: none;"></span>
                        </a>
                        <div class="dropdown-menu notification-menu">
                            <div class="notification-header">Bildirimler</div>
                            <div class="notification-items">
                                <!-- Örnek Bildirim -->
                                <div class="notification-item">
                                    <p>Sitemize hoşgeldin!</p>
                                    <small>Az önce</small>
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="/notifications.php">Tümünü Gör</a>
                            </div>
                        </div>
                    </div>

                    <div class="nav-item dropdown profile-dropdown">
                        <a href="#" class="nav-link profile-avatar-link" data-toggle="dropdown">
                            <img src="<?= htmlspecialchars($user_info['avatar_path']) ?>" alt="Kullanıcı Avatarı" class="profile-avatar-sm">
                        </a>
                        <div class="dropdown-menu profile-menu-dropdown">
                            <div class="profile-dropdown-header">
                                <img src="<?= htmlspecialchars($user_info['avatar_path']) ?>" alt="Kullanıcı Avatarı" class="profile-avatar-md">
                                <div class="profile-dropdown-userinfo">
                                    <span class="username" style="color: <?= htmlspecialchars($user_info['primary_role_color'] ?? '#bd912a') ?>;">
                                        <?= htmlspecialchars($user_info['username']) ?>
                                    </span>
                                    <a href="/profile.php" class="view-profile-link">Profilimi Görüntüle</a>
                                </div>
                            </div>

                            <div class="dropdown-divider"></div>

                            <div class="dropdown-section">
                                <h6 class="dropdown-header">Profil ve Ayarlar</h6>
                                <a class="dropdown-item" href="/profile/edit.php"><i class="fas fa-edit"></i> Profili Düzenle</a>
                                <a class="dropdown-item" href="/profile/avatar.php"><i class="fas fa-camera"></i> Avatarımı Değiştir</a>
                                <a class="dropdown-item" href="/profile/hangar.php"><i class="fas fa-space-shuttle"></i> Hangarımı Yönet</a>
                                <a class="dropdown-item" href="/profile/security.php"><i class="fas fa-shield-alt"></i> Güvenlik Ayarları</a>
                            </div>

                            <div class="dropdown-divider"></div>

                            <div class="dropdown-section">
                                <h6 class="dropdown-header">İçeriklerim</h6>
                                <a class="dropdown-item" href="/view/gallery_photos.php?user_id=<?= $current_user_id ?>"><i class="fas fa-images"></i> Galerim</a>
                                <a class="dropdown-item" href="/view/hangar_ships.php?user_id=<?= $current_user_id ?>"><i class="fas fa-rocket"></i> Hangarım</a>
                                <a class="dropdown-item" href="/view/forum_topics.php?user_id=<?= $current_user_id ?>"><i class="fas fa-comments"></i> Forum Aktivitem</a>
                            </div>
                            
                            <?php if ($can_access_admin_panel): ?>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-section">
                                <h6 class="dropdown-header">Yönetim</h6>
                                <a class="dropdown-item" href="/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Yönetim Paneli</a>
                                <a class="dropdown-item" href="/admin/users/"><i class="fas fa-users-cog"></i> Kullanıcı Yönetimi</a>
                            </div>
                            <?php endif; ?>

                            <div class="dropdown-divider"></div>

                            <a class="dropdown-item logout-link" href="/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                Çıkış Yap
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('[data-toggle="dropdown"]');

    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            
            const menu = this.nextElementSibling;
            
            // Check if the current menu is open before closing all others
            const isCurrentlyOpen = menu.style.display === 'block';

            // Close all open dropdown menus
            document.querySelectorAll('.dropdown-menu').forEach(function(m) {
                m.style.display = 'none';
            });
            
            // If the clicked menu wasn't already open, open it.
            // This allows clicking the toggle again to close its menu.
            if (!isCurrentlyOpen) {
                menu.style.display = 'block';
            }
        });
    });

    // Add a global click listener to close dropdowns when clicking outside
    window.addEventListener('click', function(event) {
        // If the click is not on a dropdown toggle and not inside a dropdown menu, close all menus.
        if (!event.target.closest('[data-toggle="dropdown"]') && !event.target.closest('.dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });

    // Navbar scroll effect
    const navbar = document.querySelector('.main-navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
});
</script>
