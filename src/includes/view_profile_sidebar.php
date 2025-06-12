<?php
// src/includes/view_profile_sidebar.php - Kullanıcı Profil Görüntüleme Sidebar

// Sidebar'ın kullanacağı kullanıcı verisini belirle
$sidebar_user = null;
$user_to_fetch_id = null;

// Görüntülenecek kullanıcı ID'sini belirle. Öncelik her zaman GET parametresinde.
if (isset($_GET['user_id'])) {
    $user_to_fetch_id = (int)$_GET['user_id'];
} 
// Eğer GET'de yoksa ve ana sayfadan bir veri geldiyse, oradaki ID'yi kullan.
// Bu, /view/ altındaki sayfalar dışında view_profile.php gibi ana profil sayfasını destekler.
elseif (isset($viewed_user_data) && isset($viewed_user_data['id'])) {
    $user_to_fetch_id = (int)$viewed_user_data['id'];
}

// Eğer bir kullanıcı ID'si belirleyebildiysek, istatistikleri her zaman veritabanından taze olarak çek.
// Bu, potansiyel olarak eski veya eksik olan $viewed_user_data['..._stats'] verilerini kullanmaktan kaçınır.
if ($user_to_fetch_id) {
    $sidebar_user = getSidebarUserData($pdo, $user_to_fetch_id);
} 
// Hiçbir şekilde ID bulunamazsa sidebar'ı gösterme
else {
    return;
}


if (!$sidebar_user) {
    return; // Kullanıcı bulunamazsa sidebar'ı gösterme
}

$current_user_id = $_SESSION['user_id'];
$is_own_profile = $current_user_id == $sidebar_user['id'];

/**
 * Sidebar için kullanıcı verilerini çeker (bağımsız fonksiyon)
 */
function getSidebarUserData(PDO $pdo, int $user_id): ?array {
    try {
        // Temel kullanıcı bilgileri ve roller
        $query = "
            SELECT u.id, u.username, u.avatar_path,
                   (SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_name,
                   (SELECT r.color FROM roles r JOIN user_roles ur ON r.id = ur.role_id 
                    WHERE ur.user_id = u.id ORDER BY r.priority ASC LIMIT 1) as primary_role_color
            FROM users u
            WHERE u.id = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
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
        } elseif (strpos($avatar_path, 'uploads/') === 0 && strpos($avatar_path, '/') !== 0) {
            $avatar_path = '/' . $avatar_path;
        }
        
        // İstatistikleri çek
        $stats = getSidebarUserStats($pdo, $user_id);
        
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'avatar_path' => $avatar_path,
            'primary_role_name' => $user['primary_role_name'] ?: 'Üye',
            'primary_role_color' => $user['primary_role_color'] ?: '#bd912a',
            'forum_stats' => $stats['forum'],
            'gallery_stats' => $stats['gallery'],
            'hangar_stats' => $stats['hangar']
        ];
        
    } catch (Exception $e) {
        error_log("Sidebar user data error: " . $e->getMessage());
        return null;
    }
}

/**
 * Sidebar için kullanıcı istatistiklerini çeker - VERİTABANI ŞEMASINA UYGUN
 */
function getSidebarUserStats(PDO $pdo, int $user_id): array {
    $stats = [
        'forum' => ['topics' => 0, 'posts' => 0, 'likes_received' => 0],
        'gallery' => ['photos' => 0, 'likes_received' => 0],
        'hangar' => ['unique_ships' => 0, 'total_ships' => 0]
    ];
    
    try {
        // Forum istatistikleri - database şemasına göre doğru sorgular
        $topic_stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE user_id = ?");
        $topic_stmt->execute([$user_id]);
        $stats['forum']['topics'] = (int)$topic_stmt->fetchColumn();
        
        $post_stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE user_id = ?");
        $post_stmt->execute([$user_id]);
        $stats['forum']['posts'] = (int)$post_stmt->fetchColumn();
        
        // Forum beğenileri - topic beğenileri
        $topic_likes_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM forum_topic_likes ftl 
            JOIN forum_topics ft ON ftl.topic_id = ft.id 
            WHERE ft.user_id = ?
        ");
        $topic_likes_stmt->execute([$user_id]);
        $topic_likes = (int)$topic_likes_stmt->fetchColumn();
        
        // Forum beğenileri - post beğenileri  
        $post_likes_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM forum_post_likes fpl 
            JOIN forum_posts fp ON fpl.post_id = fp.id 
            WHERE fp.user_id = ?
        ");
        $post_likes_stmt->execute([$user_id]);
        $post_likes = (int)$post_likes_stmt->fetchColumn();
        
        $stats['forum']['likes_received'] = $topic_likes + $post_likes;
        
        // Galeri istatistikleri - database şemasına göre
        $photos_stmt = $pdo->prepare("SELECT COUNT(*) FROM gallery_photos WHERE user_id = ?");
        $photos_stmt->execute([$user_id]);
        $stats['gallery']['photos'] = (int)$photos_stmt->fetchColumn();
        
        $gallery_likes_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM gallery_photo_likes gpl 
            JOIN gallery_photos gp ON gpl.photo_id = gp.id 
            WHERE gp.user_id = ?
        ");
        $gallery_likes_stmt->execute([$user_id]);
        $stats['gallery']['likes_received'] = (int)$gallery_likes_stmt->fetchColumn();
        
        // Hangar istatistikleri - database şemasına göre
        $hangar_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as unique_ships,
                SUM(quantity) as total_ships,
                SUM(CASE WHEN has_lti = 1 THEN 1 ELSE 0 END) as lti_ships
            FROM user_hangar 
            WHERE user_id = ?
        ");
        $hangar_stmt->execute([$user_id]);
        $hangar_result = $hangar_stmt->fetch();
        
        $stats['hangar']['unique_ships'] = (int)($hangar_result['unique_ships'] ?? 0);
        $stats['hangar']['total_ships'] = (int)($hangar_result['total_ships'] ?? 0);
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Sidebar user stats error: " . $e->getMessage());
        return $stats; // Default sıfır değerlerle dön
    }
}

// Avatar path düzeltme
$sidebar_avatar = $sidebar_user['avatar_path'];
if (empty($sidebar_avatar)) {
    $sidebar_avatar = '/assets/logo.png';
} elseif (strpos($sidebar_avatar, '../assets/') === 0) {
    $sidebar_avatar = str_replace('../assets/', '/assets/', $sidebar_avatar);
} elseif (strpos($sidebar_avatar, 'uploads/') === 0 && strpos($sidebar_avatar, '/') !== 0) {
    $sidebar_avatar = '/' . $sidebar_avatar;
}

// Aktif sayfa URL'sini al
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Sidebar menü öğeleri - görüntülenen kullanıcıya özel
$sidebar_items = [
    [
        'id' => 'overview',
        'title' => 'Profil Özeti',
        'icon' => 'fas fa-user',
        'url' => '/view_profile.php?user_id=' . $sidebar_user['id'],
        'active' => $current_path === '/view_profile.php'
    ],
    [
        'id' => 'forum',
        'title' => 'Forum Konuları',
        'icon' => 'fas fa-comments',
        'url' => '/view/forum_topics.php?user_id=' . $sidebar_user['id'],
        'active' => $current_path === '/view/forum_topics.php',
        'badge' => $sidebar_user['forum_stats']['topics'] ?? 0
    ],
    [
        'id' => 'posts',
        'title' => 'Forum Gönderileri',
        'icon' => 'fas fa-comment',
        'url' => '/view/forum_posts.php?user_id=' . $sidebar_user['id'],
        'active' => $current_path === '/view/forum_posts.php',
        'badge' => $sidebar_user['forum_stats']['posts'] ?? 0
    ],
    [
        'id' => 'gallery',
        'title' => 'Galeri Fotoğrafları',
        'icon' => 'fas fa-images',
        'url' => '/view/gallery_photos.php?user_id=' . $sidebar_user['id'],
        'active' => $current_path === '/view/gallery_photos.php',
        'badge' => $sidebar_user['gallery_stats']['photos'] ?? 0
    ],
    [
        'id' => 'hangar',
        'title' => 'Hangar Gemileri',
        'icon' => 'fas fa-space-shuttle',
        'url' => '/view/hangar_ships.php?user_id=' . $sidebar_user['id'],
        'active' => $current_path === '/view/hangar_ships.php',
        'badge' => $sidebar_user['hangar_stats']['unique_ships'] ?? 0
    ]
];

// Admin kontrolü - admin ise ek seçenekler ekle
if (has_permission($pdo, 'admin.users.view')) {
    $sidebar_items[] = [
        'id' => 'admin',
        'title' => 'Yönetici İşlemleri',
        'icon' => 'fas fa-shield-alt',
        'url' => '/admin/user_management.php?user_id=' . $sidebar_user['id'],
        'active' => $current_path === '/admin/user_management.php',
        'admin_only' => true
    ];
}
?>

<div class="profile-sidebar view-profile-sidebar">
    <!-- Sidebar Header - Görüntülenen Kullanıcı -->
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
                <?php if ($is_own_profile): ?>
                <div class="sidebar-user-status">
                    <span class="status-badge own-profile">
                        <i class="fas fa-eye"></i> Kendi Profilin
                    </span>
                </div>
                <?php endif; ?>
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
                        <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                            <span class="sidebar-badge"><?= number_format($item['badge']) ?></span>
                        <?php endif; ?>
                        <?php if ($item['active']): ?>
                            <div class="active-indicator"></div>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- İstatistik Özeti -->
    <div class="sidebar-stats">
        <h5 class="sidebar-stats-title">Aktivite Özeti</h5>
        <div class="sidebar-stats-grid">
            <div class="sidebar-stat-item">
                <div class="sidebar-stat-number"><?= number_format($sidebar_user['forum_stats']['topics'] + $sidebar_user['forum_stats']['posts']) ?></div>
                <div class="sidebar-stat-label">Forum</div>
            </div>
            <div class="sidebar-stat-item">
                <div class="sidebar-stat-number"><?= number_format($sidebar_user['gallery_stats']['photos']) ?></div>
                <div class="sidebar-stat-label">Fotoğraf</div>
            </div>
            <div class="sidebar-stat-item">
                <div class="sidebar-stat-number"><?= number_format($sidebar_user['hangar_stats']['total_ships']) ?></div>
                <div class="sidebar-stat-label">Gemi</div>
            </div>
            <div class="sidebar-stat-item">
                <div class="sidebar-stat-number"><?= number_format($sidebar_user['forum_stats']['likes_received'] + $sidebar_user['gallery_stats']['likes_received']) ?></div>
                <div class="sidebar-stat-label">Beğeni</div>
            </div>
        </div>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-actions">
            <h5 class="sidebar-actions-title">İşlemler</h5>
            <div class="sidebar-actions-grid">
                <?php if (!$is_own_profile): ?>
                    <a href="/profile.php" 
                       class="sidebar-action-btn secondary" title="Kendi Profilim">
                        <i class="fas fa-user"></i>
                        <span>Profilim</span>
                    </a>
                    
                    <?php if (has_permission($pdo, 'users.report')): ?>
                    <a href="/reports/user.php?user_id=<?= $sidebar_user['id'] ?>" 
                       class="sidebar-action-btn warning" title="Kullanıcıyı Rapor Et">
                        <i class="fas fa-flag"></i>
                        <span>Rapor Et</span>
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/profile/edit.php" 
                       class="sidebar-action-btn primary" title="Profili Düzenle">
                        <i class="fas fa-edit"></i>
                        <span>Düzenle</span>
                    </a>
                    
                    <a href="/profile/avatar.php" 
                       class="sidebar-action-btn secondary" title="Avatar Değiştir">
                        <i class="fas fa-image"></i>
                        <span>Avatar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-navigation">
            <a href="/index.php" class="sidebar-nav-btn" title="Ana Sayfa">
                <i class="fas fa-home"></i>
                <span>Ana Sayfa</span>
            </a>
            <a href="/users/" class="sidebar-nav-btn" title="Üye Listesi">
                <i class="fas fa-users"></i>
                <span>Üyeler</span>
            </a>
        </div>
    </div>
</div>

<style>
/* View Profile Sidebar Özel Stiller */
.view-profile-sidebar .sidebar-user-status {
    margin-top: 0.5rem;
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 500;
}

.status-badge.own-profile {
    background: rgba(184, 132, 92, 0.1);
    color: var(--gold);
    border: 1px solid var(--gold);
}

.status-badge.other-profile {
    background: var(--card-bg-3);
    color: var(--light-grey);
    border: 1px solid var(--border-1);
}

/* Sidebar Badge */
.sidebar-badge {
    display: none;
    background: var(--gold);
    color: var(--body-bg);
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.125rem 0.375rem;
    border-radius: 10px;
    margin-left: auto;
}

/* Sidebar Stats */
.sidebar-stats {
    padding: 1rem;
    border-top: 1px solid var(--border-1);
    border-bottom: 1px solid var(--border-1);
    background: var(--card-bg-2);
}

.sidebar-stats-title {
    margin: 0 0 0.75rem 0;
    font-size: 0.8rem;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.sidebar-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.sidebar-stat-item {
    text-align: center;
    padding: 0.5rem;
    background: var(--card-bg-3);
    border-radius: 6px;
    border: 1px solid var(--border-1);
    transition: all 0.3s ease;
}

.sidebar-stat-item:hover {
    background: var(--card-bg-4);
    transform: translateY(-1px);
}

.sidebar-stat-number {
    font-size: 1rem;
    font-weight: 600;
    color: var(--lighter-grey);
    margin-bottom: 0.125rem;
}

.sidebar-stat-label {
    font-size: 0.7rem;
    color: var(--light-grey);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Sidebar Actions */
.sidebar-actions {
    margin-bottom: 1rem;
}

.sidebar-actions-title {
    margin: 0 0 0.75rem 0;
    font-size: 0.8rem;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.sidebar-actions-grid {
    display: grid;
    gap: 0.5rem;
}

.sidebar-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid;
}

.sidebar-action-btn.primary {
    background: var(--gold);
    color: var(--body-bg);
    border-color: var(--gold);
}

.sidebar-action-btn.primary:hover {
    background: var(--light-gold);
    transform: translateY(-2px);
    text-decoration: none;
    color: var(--body-bg);
}

.sidebar-action-btn.secondary {
    background: transparent;
    color: var(--turquase);
    border-color: var(--turquase);
}

.sidebar-action-btn.secondary:hover {
    background: var(--turquase);
    color: var(--body-bg);
    transform: translateY(-2px);
    text-decoration: none;
}

.sidebar-action-btn.warning {
    background: transparent;
    color: var(--red);
    border-color: var(--red);
}

.sidebar-action-btn.warning:hover {
    background: var(--red);
    color: var(--white);
    transform: translateY(-2px);
    text-decoration: none;
}

/* Sidebar Navigation */
.sidebar-navigation {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.sidebar-nav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.75rem 0.5rem;
    background: var(--card-bg-3);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    color: var(--light-grey);
    text-decoration: none;
    font-size: 0.75rem;
    transition: all 0.3s ease;
}

.sidebar-nav-btn:hover {
    background: var(--turquase);
    color: var(--body-bg);
    transform: translateY(-2px);
    text-decoration: none;
}

.sidebar-nav-btn i {
    font-size: 1.1rem;
}

/* Responsive */
@media (max-width: 968px) {
    .sidebar-stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .sidebar-stat-item {
        padding: 0.375rem;
    }
    
    .sidebar-stat-number {
        font-size: 0.9rem;
    }
    
    .sidebar-stat-label {
        font-size: 0.65rem;
    }
}

@media (max-width: 768px) {
    .sidebar-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .sidebar-action-btn {
        padding: 0.5rem;
        font-size: 0.8rem;
    }
    
    .sidebar-action-btn span {
        display: none;
    }
}
</style> 