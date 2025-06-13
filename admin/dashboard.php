<?php
// /admin/dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Yetki kontrolü
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu sayfaya erişim için giriş yapmalısınız.";
    header('Location: ' . get_auth_base_url() . '/login.php');
    exit;
}

if (!has_permission($pdo, 'admin.panel.access')) {
    $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
    header('Location: /index.php');
    exit;
}

$page_title = 'Admin Dashboard';
$additional_css = ['../css/style.css', 'dashboard/css/dashboard.css'];
$additional_js = ['dashboard/js/dashboard.js'];

// Sistem istatistiklerini al - GERÇEK TABLO YAPISINA GÖRE
try {
    // Kullanıcı istatistikleri - users tablosu
    $user_stats_query = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_users,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_users,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_users,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_registrations,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_registrations
        FROM users
    ";
    $user_stats_stmt = $pdo->prepare($user_stats_query);
    $user_stats_stmt->execute();
    $user_stats = $user_stats_stmt->fetch();
    
    // Forum istatistikleri - forum tabloları
    $forum_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM forum_topics) as total_topics,
            (SELECT COUNT(*) FROM forum_posts) as total_posts,
            (SELECT COUNT(*) FROM forum_categories) as total_categories,
            (SELECT COUNT(*) FROM forum_topics WHERE DATE(created_at) = CURDATE()) as today_topics,
            (SELECT COUNT(*) FROM forum_posts WHERE DATE(created_at) = CURDATE()) as today_posts
    ";
    $forum_stats_stmt = $pdo->prepare($forum_stats_query);
    $forum_stats_stmt->execute();
    $forum_stats = $forum_stats_stmt->fetch();
    
    // Etkinlik istatistikleri - events tablosu
    $event_stats_query = "
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_events,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_events,
            SUM(CASE WHEN event_date > NOW() THEN 1 ELSE 0 END) as upcoming_events,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_events
        FROM events
    ";
    $event_stats_stmt = $pdo->prepare($event_stats_query);
    $event_stats_stmt->execute();
    $event_stats = $event_stats_stmt->fetch();
    
    // Galeri istatistikleri - gallery_photos tablosu (approval_status yok, visibility sistemli)
    $gallery_stats_query = "
        SELECT 
            COUNT(*) as total_photos,
            SUM(CASE WHEN is_public_no_auth = 1 THEN 1 ELSE 0 END) as public_photos,
            SUM(CASE WHEN is_members_only = 1 THEN 1 ELSE 0 END) as members_only_photos,
            SUM(CASE WHEN DATE(uploaded_at) = CURDATE() THEN 1 ELSE 0 END) as today_photos
        FROM gallery_photos
    ";
    $gallery_stats_stmt = $pdo->prepare($gallery_stats_query);
    $gallery_stats_stmt->execute();
    $gallery_stats = $gallery_stats_stmt->fetch();
    
    // Rol ve yetki istatistikleri
    $role_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM roles) as total_roles,
            (SELECT COUNT(*) FROM permissions WHERE is_active = 1) as active_permissions,
            (SELECT COUNT(*) FROM skill_tags) as total_skills,
            (SELECT COUNT(*) FROM user_roles) as total_role_assignments
    ";
    $role_stats_stmt = $pdo->prepare($role_stats_query);
    $role_stats_stmt->execute();
    $role_stats = $role_stats_stmt->fetch();
    
    // Son kayıt olan kullanıcılar
    $recent_users_query = "
        SELECT id, username, ingame_name, status, created_at, avatar_path
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ";
    $recent_users_stmt = $pdo->prepare($recent_users_query);
    $recent_users_stmt->execute();
    $recent_users = $recent_users_stmt->fetchAll();
    
    // Bekleyen onaylar ve aktiviteler
    $pending_users_count = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $recent_forum_activity = $pdo->query("SELECT COUNT(*) FROM forum_posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $recent_events_count = $pdo->query("SELECT COUNT(*) FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Varsayılan değerler
    $user_stats = ['total_users' => 0, 'approved_users' => 0, 'pending_users' => 0, 'suspended_users' => 0, 'rejected_users' => 0, 'today_registrations' => 0, 'week_registrations' => 0];
    $forum_stats = ['total_topics' => 0, 'total_posts' => 0, 'total_categories' => 0, 'today_topics' => 0, 'today_posts' => 0];
    $event_stats = ['total_events' => 0, 'published_events' => 0, 'draft_events' => 0, 'upcoming_events' => 0, 'today_events' => 0];
    $gallery_stats = ['total_photos' => 0, 'public_photos' => 0, 'members_only_photos' => 0, 'today_photos' => 0];
    $role_stats = ['total_roles' => 0, 'active_permissions' => 0, 'total_skills' => 0, 'total_role_assignments' => 0];
    $recent_users = [];
    $pending_users_count = 0;
    $recent_forum_activity = 0;
    $recent_events_count = 0;
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<link rel="stylesheet" href="/admin/dashboard/css/dashboard.css">
<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
        <span class="active"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</span>
    </nav>

    <!-- Ana Başlık -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Admin Dashboard
                </h1>
                <p class="page-subtitle">Sistem genel bakış ve yönetim merkezi</p>
            </div>
            
            <div class="page-actions">
                <button class="btn btn-secondary" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i>
                    Yenile
                </button>
            </div>
        </div>
    </div>

    <!-- Hızlı İstatistikler -->
    <div class="quick-stats-grid">
        <!-- Kullanıcı İstatistikleri -->
        <?php if (has_permission($pdo, 'admin.users.view')): ?>
        <div class="stat-card stat-card-primary clickable-card" onclick="location.href='/admin/users/'" style="cursor: pointer;">
            <div class="stat-header">
                <h3><i class="fas fa-users"></i> Kullanıcılar</h3>
                <a href="/admin/users/" class="stat-link" onclick="event.stopPropagation();">Yönet</a>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <span class="stat-number"><?= number_format($user_stats['total_users']) ?></span>
                    <span class="stat-label">Toplam Kullanıcı</span>
                </div>
                <div class="stat-breakdown">
                    <div class="stat-item clickable-stat" onclick="event.stopPropagation(); location.href='/admin/users/?status=approved'">
                        <span class="stat-value approved"><?= number_format($user_stats['approved_users']) ?></span>
                        <span class="stat-text">Onaylı</span>
                    </div>
                    <div class="stat-item clickable-stat" onclick="event.stopPropagation(); location.href='/admin/users/?status=pending'">
                        <span class="stat-value pending"><?= number_format($user_stats['pending_users']) ?></span>
                        <span class="stat-text">Bekleyen</span>
                    </div>
                    <div class="stat-item clickable-stat" onclick="event.stopPropagation(); location.href='/admin/users/?status=suspended'">
                        <span class="stat-value rejected"><?= number_format($user_stats['suspended_users'] + $user_stats['rejected_users']) ?></span>
                        <span class="stat-text">Askıya Alınan</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Forum İstatistikleri -->
        <div class="stat-card stat-card-info clickable-card" onclick="location.href='/forum/'" style="cursor: pointer;">
            <div class="stat-header">
                <h3><i class="fas fa-comments"></i> Forum</h3>
                <a href="/forum/" class="stat-link" onclick="event.stopPropagation();">Görüntüle</a>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <span class="stat-number"><?= number_format($forum_stats['total_topics']) ?></span>
                    <span class="stat-label">Toplam Konu</span>
                </div>
                <div class="stat-breakdown">
                    <div class="stat-item">
                        <span class="stat-value"><?= number_format($forum_stats['total_posts']) ?></span>
                        <span class="stat-text">Mesaj</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= number_format($forum_stats['total_categories']) ?></span>
                        <span class="stat-text">Kategori</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value today"><?= number_format($forum_stats['today_topics']) ?></span>
                        <span class="stat-text">Bugün</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Etkinlik İstatistikleri -->
        <div class="stat-card stat-card-success clickable-card" onclick="location.href='/events/'" style="cursor: pointer;">
            <div class="stat-header">
                <h3><i class="fas fa-calendar-alt"></i> Etkinlikler</h3>
                <a href="/events/" class="stat-link" onclick="event.stopPropagation();">Yönet</a>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <span class="stat-number"><?= number_format($event_stats['total_events']) ?></span>
                    <span class="stat-label">Toplam Etkinlik</span>
                </div>
                <div class="stat-breakdown">
                    <div class="stat-item">
                        <span class="stat-value approved"><?= number_format($event_stats['published_events']) ?></span>
                        <span class="stat-text">Yayında</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value pending"><?= number_format($event_stats['draft_events']) ?></span>
                        <span class="stat-text">Taslak</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value upcoming"><?= number_format($event_stats['upcoming_events']) ?></span>
                        <span class="stat-text">Yaklaşan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Galeri İstatistikleri -->
        <div class="stat-card stat-card-warning clickable-card" onclick="location.href='/gallery/'" style="cursor: pointer;">
            <div class="stat-header">
                <h3><i class="fas fa-images"></i> Galeri</h3>
                <a href="/gallery/" class="stat-link" onclick="event.stopPropagation();">Görüntüle</a>
            </div>
            <div class="stat-content">
                <div class="stat-main">
                    <span class="stat-number"><?= number_format($gallery_stats['total_photos']) ?></span>
                    <span class="stat-label">Toplam Fotoğraf</span>
                </div>
                <div class="stat-breakdown">
                    <div class="stat-item">
                        <span class="stat-value approved"><?= number_format($gallery_stats['public_photos']) ?></span>
                        <span class="stat-text">Herkese Açık</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value pending"><?= number_format($gallery_stats['members_only_photos']) ?></span>
                        <span class="stat-text">Üyelere Özel</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value today"><?= number_format($gallery_stats['today_photos']) ?></span>
                        <span class="stat-text">Bugün</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <!-- Sol Kolon -->
        <div class="dashboard-left">
            <!-- Hızlı Eylemler -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3><i class="fas fa-bolt"></i> Hızlı Eylemler</h3>
                </div>
                <div class="widget-content">
                    <div class="quick-actions">
                        <?php if (has_permission($pdo, 'admin.users.view')): ?>
                            <a href="/admin/users/" class="quick-action-btn">
                                <i class="fas fa-users-cog"></i>
                                <span>Kullanıcı Yönetimi</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (has_permission($pdo, 'admin.roles.view')): ?>
                            <a href="/admin/manage_roles/" class="quick-action-btn">
                                <i class="fas fa-user-tag"></i>
                                <span>Rol Yönetimi</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (has_permission($pdo, 'event.create')): ?>
                            <a href="/events/create.php" class="quick-action-btn">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Etkinlik Oluştur</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (has_permission($pdo, 'gallery.upload')): ?>
                            <a href="/gallery/upload.php" class="quick-action-btn">
                                <i class="fas fa-camera"></i>
                                <span>Fotoğraf Yükle</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (has_permission($pdo, 'forum.topic.create')): ?>
                            <a href="/forum/create_topic.php" class="quick-action-btn">
                                <i class="fas fa-file-alt"></i>
                                <span>Forum Konusu</span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="/admin/dashboard.php" class="quick-action-btn">
                            <i class="fas fa-sync-alt"></i>
                            <span>Dashboard Yenile</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bekleyen Onaylar ve Aktiviteler -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3><i class="fas fa-clock"></i> Güncel Aktiviteler</h3>
                </div>
                <div class="widget-content">
                    <div class="pending-items">
                        <?php if ($pending_users_count > 0): ?>
                            <div class="pending-item">
                                <a href="/admin/users/?status=pending">
                                    <i class="fas fa-user-plus"></i>
                                    <span><?= $pending_users_count ?> kullanıcı onay bekliyor</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($recent_forum_activity > 0): ?>
                            <div class="pending-item">
                                <a href="/forum/">
                                    <i class="fas fa-comments"></i>
                                    <span>Son 24 saatte <?= $recent_forum_activity ?> forum mesajı</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($recent_events_count > 0): ?>
                            <div class="pending-item">
                                <a href="/events/">
                                    <i class="fas fa-calendar"></i>
                                    <span>Son hafta <?= $recent_events_count ?> yeni etkinlik</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_stats['today_registrations'] > 0): ?>
                            <div class="pending-item">
                                <a href="/admin/users/">
                                    <i class="fas fa-user-check"></i>
                                    <span>Bugün <?= $user_stats['today_registrations'] ?> yeni kayıt</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($pending_users_count == 0 && $recent_forum_activity == 0 && $recent_events_count == 0 && $user_stats['today_registrations'] == 0): ?>
                            <div class="no-pending">
                                <i class="fas fa-check-circle"></i>
                                <span>Sistem sakin - yeni aktivite yok</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon -->
        <div class="dashboard-right">
            <!-- Son Kayıt Olan Kullanıcılar -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3><i class="fas fa-user-plus"></i> Son Kayıt Olanlar</h3>
                    <a href="/admin/users/" class="widget-link">Tümünü Gör</a>
                </div>
                <div class="widget-content">
                    <div class="recent-users">
                        <?php if (!empty($recent_users)): ?>
                            <?php foreach ($recent_users as $user): ?>
                                <div class="recent-user">
                                    <div class="user-avatar">
                                        <?php if ($user['avatar_path']): ?>
                                            <img src="/<?= htmlspecialchars($user['avatar_path']) ?>" alt="<?= htmlspecialchars($user['username']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name">
                                            <a href="/view_profile.php?user_id=<?= $user['id'] ?>" target="_blank">
                                                <?= htmlspecialchars($user['username']) ?>
                                            </a>
                                        </div>
                                        <div class="user-details">
                                            <span class="user-ingame"><?= htmlspecialchars($user['ingame_name']) ?></span>
                                            <span class="user-status status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
                                        </div>
                                        <div class="user-date">
                                            <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="user-actions">
                                        <?php if (has_permission($pdo, 'admin.users.edit_status')): ?>
                                            <a href="/admin/users/" class="action-link" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-users"></i>
                                <span>Henüz kullanıcı yok</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sistem Durumu -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3><i class="fas fa-server"></i> Sistem Durumu</h3>
                </div>
                <div class="widget-content">
                    <div class="system-stats">
                        <div class="system-stat">
                            <div class="stat-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?= number_format($role_stats['total_roles']) ?></div>
                                <div class="stat-name">Roller</div>
                            </div>
                        </div>
                        
                        <div class="system-stat">
                            <div class="stat-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?= number_format($role_stats['active_permissions']) ?></div>
                                <div class="stat-name">Aktif Yetki</div>
                            </div>
                        </div>
                        
                        <div class="system-stat">
                            <div class="stat-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?= number_format($role_stats['total_skills']) ?></div>
                                <div class="stat-name">Skill Tags</div>
                            </div>
                        </div>
                        
                        <div class="system-stat">
                            <div class="stat-icon">
                                <i class="fas fa-link"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?= number_format($role_stats['total_role_assignments']) ?></div>
                                <div class="stat-name">Rol Atamaları</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once BASE_PATH . '/src/includes/admin_quick_menu.php';
require_once BASE_PATH . '/src/includes/footer.php'; 
?>

<!-- JavaScript dosyalarını yükle -->
<script src="/admin/dashboard/js/dashboard.js"></script>
<script>
// Sayfa yüklendikten sonra JavaScript'i başlat
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AdminDashboard !== 'undefined') {
        AdminDashboard.init();
    }
});
</script>