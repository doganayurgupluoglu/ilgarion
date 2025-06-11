<?php
// src/includes/profile_sidebar.php - Profil Yönetimi Sidebar

// Mevcut sayfa URL'sini al
$current_page = $_SERVER['REQUEST_URI'];
$current_page = parse_url($current_page, PHP_URL_PATH);
$current_page = basename($current_page, '.php');

// Sidebar menü öğeleri
$sidebar_items = [
    [
        'id' => 'overview',
        'title' => 'Profil Özeti',
        'icon' => 'fas fa-user',
        'url' => '/profile.php',
        'active' => in_array($current_page, ['profile', 'index'])
    ],
    [
        'id' => 'edit',
        'title' => 'Profili Düzenle',
        'icon' => 'fas fa-edit',
        'url' => '/profile/edit.php',
        'active' => $current_page === 'edit'
    ],
    [
        'id' => 'avatar',
        'title' => 'Avatar Yönetimi',
        'icon' => 'fas fa-image',
        'url' => '/profile/avatar.php',
        'active' => $current_page === 'avatar'
    ],
    [
        'id' => 'hangar',
        'title' => 'Hangar Yönetimi',
        'icon' => 'fas fa-space-shuttle',
        'url' => '/profile/hangar.php',
        'active' => $current_page === 'hangar'
    ],
    [
        'id' => 'security',
        'title' => 'Güvenlik Ayarları',
        'icon' => 'fas fa-shield-alt',
        'url' => '/profile/security.php',
        'active' => $current_page === 'security'
    ],
    [
        'id' => 'privacy',
        'title' => 'Gizlilik Ayarları',
        'icon' => 'fas fa-eye-slash',
        'url' => '/profile/privacy.php',
        'active' => $current_page === 'privacy'
    ]
];

// Kullanıcı bilgilerini al (global $user_data varsa kullan, yoksa session'dan)
$sidebar_user = $user_data ?? [
    'username' => $_SESSION['username'] ?? 'Kullanıcı',
    'avatar_path' => $_SESSION['avatar_path'] ?? '/assets/logo.png',
    'primary_role_name' => $_SESSION['primary_role_name'] ?? 'Üye',
    'primary_role_color' => $_SESSION['primary_role_color'] ?? '#bd912a'
];

// Avatar path düzeltme
$sidebar_avatar = $sidebar_user['avatar_path'];
if (empty($sidebar_avatar)) {
    $sidebar_avatar = '/assets/logo.png';
} elseif (strpos($sidebar_avatar, '../assets/') === 0) {
    $sidebar_avatar = str_replace('../assets/', '/assets/', $sidebar_avatar);
} elseif (strpos($sidebar_avatar, 'uploads/') === 0 && strpos($sidebar_avatar, '/') !== 0) {
    $sidebar_avatar = '/' . $sidebar_avatar;
}
?>

<div class="profile-sidebar">
    <!-- Sidebar Header - Kullanıcı Özeti -->
    <div class="sidebar-header">
        <div class="sidebar-user-info">
            <div class="sidebar-avatar">
                <img src="<?= htmlspecialchars($sidebar_avatar) ?>" 
                     alt="<?= htmlspecialchars($sidebar_user['username']) ?> Avatar"
                     class="sidebar-avatar-img">
            </div>
            <div class="sidebar-user-details">
                <h4 class="sidebar-username"><?= htmlspecialchars($sidebar_user['username']) ?></h4>
                <span class="sidebar-role" style="color: <?= htmlspecialchars($sidebar_user['primary_role_color']) ?>">
                    <?= htmlspecialchars($sidebar_user['primary_role_name']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <?php foreach ($sidebar_items as $item): ?>
                <li class="sidebar-menu-item <?= $item['active'] ? 'active' : '' ?>">
                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                       class="sidebar-menu-link <?= $item['active'] ? 'active' : '' ?>"
                       title="<?= htmlspecialchars($item['title']) ?>">
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span class="sidebar-menu-text"><?= htmlspecialchars($item['title']) ?></span>
                        <?php if ($item['active']): ?>
                            <div class="active-indicator"></div>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-quick-actions">
            <h5 class="quick-actions-title">Hızlı Erişim</h5>
            <div class="quick-actions-grid">
                <a href="/forum/" class="quick-action-btn" title="Forum">
                    <i class="fas fa-comments"></i>
                    <span>Forum</span>
                </a>
                <a href="/gallery/" class="quick-action-btn" title="Galeri">
                    <i class="fas fa-images"></i>
                    <span>Galeri</span>
                </a>
                <a href="/loadouts/" class="quick-action-btn" title="Teçhizat">
                    <i class="fas fa-cogs"></i>
                    <span>Teçhizat</span>
                </a>
                <a href="/events/" class="quick-action-btn" title="Etkinlikler">
                    <i class="fas fa-calendar"></i>
                    <span>Etkinlik</span>
                </a>
            </div>
        </div>

        <div class="sidebar-logout">
            <a href="/auth/logout.php" class="logout-btn" onclick="return confirm('Çıkış yapmak istediğinizden emin misiniz?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
    </div>
</div>