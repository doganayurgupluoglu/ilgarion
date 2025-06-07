<?php
// events/includes/events_sidebar.php - Events sayfaları için sidebar

// Mevcut sayfa tespiti
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Ana bölüm tespiti
$main_section = '';
if ($current_dir === 'loadouts' || strpos($_SERVER['REQUEST_URI'], '/loadouts/') !== false) {
    $main_section = 'loadouts';
} elseif ($current_dir === 'roles' || strpos($_SERVER['REQUEST_URI'], '/roles/') !== false) {
    $main_section = 'roles';
} elseif ($current_dir === 'events' && $current_page === 'index') {
    $main_section = 'events';
} else {
    $main_section = 'events'; // Default
}

// Yetki kontrolleri
$can_create_event = is_user_approved() && has_permission($pdo, 'event.create');
$can_create_loadout = is_user_approved() && has_permission($pdo, 'loadout.manage_sets');
$can_view_roles = is_user_approved(); // Roller henüz tasarlanmadığı için basit kontrol
?>

<div class="events-sidebar">
    <!-- Ana Bölümler -->
    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="fas fa-calendar-alt"></i>
            Ana Bölümler
        </h3>
        <ul class="sidebar-menu">
            <li class="menu-item <?= $main_section === 'events' ? 'active' : '' ?>">
                <a href="/events/">
                    <i class="fas fa-calendar"></i>
                    <span>Etkinlikler</span>
                </a>
            </li>
            <li class="menu-item <?= $main_section === 'roles' ? 'active' : '' ?>">
                <a href="/events/roles/">
                    <i class="fas fa-user-tag"></i>
                    <span>Roller</span>
                </a>
            </li>
            <li class="menu-item <?= $main_section === 'loadouts' ? 'active' : '' ?>">
                <a href="/events/loadouts/">
                    <i class="fas fa-user-astronaut"></i>
                    <span>Teçhizat Setleri</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Etkinlikler Alt Menüsü -->
    <?php if ($main_section === 'events'): ?>
        <div class="sidebar-section">
            <ul class="sidebar-submenu">
                <li class="submenu-item <?= $current_page === 'index' ? 'active' : '' ?>">
                    <a href="/events/">
                        <i class="fas fa-list"></i>
                        <span>Tüm Etkinlikler</span>
                    </a>
                </li>
                <?php if ($can_create_event): ?>
                    <li class="submenu-item <?= $current_page === 'create' ? 'active' : '' ?>">
                        <a href="/events/create.php">
                            <i class="fas fa-plus"></i>
                            <span>Yeni Etkinlik Oluştur</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (is_user_logged_in()): ?>
                    <li class="submenu-item <?= isset($_GET['filter']) && $_GET['filter'] === 'my_events' ? 'active' : '' ?>">
                        <a href="/events/?filter=my_events">
                            <i class="fas fa-user"></i>
                            <span>Etkinliklerim</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= isset($_GET['filter']) && $_GET['filter'] === 'participating' ? 'active' : '' ?>">
                        <a href="/events/?filter=participating">
                            <i class="fas fa-calendar-check"></i>
                            <span>Katıldıklarım</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Roller Alt Menüsü -->
    <?php if ($main_section === 'roles'): ?>
        <div class="sidebar-section">
            <ul class="sidebar-submenu">
                <?php if ($can_view_roles): ?>
                    <li class="submenu-item <?= $current_page === 'index' ? 'active' : '' ?>">
                        <a href="/events/roles/">
                            <i class="fas fa-list"></i>
                            <span>Tüm Roller</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page === 'create' ? 'active' : '' ?>">
                        <a href="/events/roles/create.php">
                            <i class="fas fa-plus"></i>
                            <span>Yeni Rol Oluştur</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="submenu-item disabled">
                        <span>
                            <i class="fas fa-lock"></i>
                            <span>Erişim Gerekli</span>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Teçhizat Setleri Alt Menüsü -->
    <?php if ($main_section === 'loadouts'): ?>
        <div class="sidebar-section">
            
            <ul class="sidebar-submenu">
                <li class="submenu-item <?= $current_page === 'index' && !isset($_GET['visibility']) ? 'active' : '' ?>">
                    <a href="/events/loadouts/">
                        <i class="fas fa-list"></i>
                        <span>Tüm Setler</span>
                    </a>
                </li>
                <li class="submenu-item <?= isset($_GET['visibility']) && $_GET['visibility'] === 'public' ? 'active' : '' ?>">
                    <a href="/events/loadouts/?visibility=public">
                        <i class="fas fa-globe"></i>
                        <span>Herkese Açık</span>
                    </a>
                </li>
                <?php if (is_user_approved()): ?>
                    <li class="submenu-item <?= isset($_GET['visibility']) && $_GET['visibility'] === 'members_only' ? 'active' : '' ?>">
                        <a href="/events/loadouts/?visibility=members_only">
                            <i class="fas fa-users"></i>
                            <span>Üyelere Özel</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (is_user_logged_in()): ?>
                    <li class="submenu-item <?= isset($_GET['visibility']) && $_GET['visibility'] === 'my_sets' ? 'active' : '' ?>">
                        <a href="/events/loadouts/?visibility=my_sets">
                            <i class="fas fa-user"></i>
                            <span>Setlerim</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($can_create_loadout): ?>
                    <li class="submenu-item <?= $current_page === 'create_loadouts' ? 'active' : '' ?>">
                        <a href="/events/loadouts/create_loadouts.php">
                            <i class="fas fa-plus"></i>
                            <span>Yeni Set Oluştur</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- İstatistikler (Opsiyonel) -->
    <?php if (is_user_logged_in()): ?>
        <div class="sidebar-section sidebar-stats">
            <h3 class="sidebar-title">
                <i class="fas fa-chart-line"></i>
                Hızlı Bilgi
            </h3>
            <div class="stats-grid">
                <?php
                try {
                    // Aktif etkinlik sayısı
                    $active_events_stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM events 
                        WHERE status = 'active' AND event_datetime > NOW()
                    ");
                    $active_events_stmt->execute();
                    $active_events_count = $active_events_stmt->fetchColumn();

                    // Teçhizat seti sayısı (kullanıcının görebileceği)
                    $loadout_visibility = !is_user_logged_in() ? "visibility = 'public'" : 
                                          (!is_user_approved() ? "visibility = 'public'" : 
                                          "visibility IN ('public', 'members_only')");
                    
                    $loadouts_stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM loadout_sets 
                        WHERE status = 'published' AND $loadout_visibility
                    ");
                    $loadouts_stmt->execute();
                    $loadouts_count = $loadouts_stmt->fetchColumn();
                } catch (Exception $e) {
                    $active_events_count = 0;
                    $loadouts_count = 0;
                }
                ?>
                <div class="stat-item-sidebar">
                    <div class="stat-number"><?= $active_events_count ?></div>
                    <div class="stat-label">Aktif Etkinlik</div>
                </div>
                <div class="stat-item-sidebar">
                    <div class="stat-number"><?= $loadouts_count ?></div>
                    <div class="stat-label">Teçhizat Seti</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>