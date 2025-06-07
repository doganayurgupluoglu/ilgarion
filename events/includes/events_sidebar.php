<?php
// events/includes/events_sidebar.php - Events sayfaları için sidebar (YETKİ KONTROLLÜ)

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

// Yetki kontrolleri - YENİ YETKİLER
$can_create_event = is_user_approved() && has_permission($pdo, 'event.create');
$can_create_loadout = is_user_approved() && has_permission($pdo, 'loadout.create_sets');
$can_view_roles = is_user_approved() && has_permission($pdo, 'event_role.view_public');
$can_create_role = is_user_approved() && has_permission($pdo, 'event_role.create');
$can_manage_participants = has_permission($pdo, 'event_role.manage_participants');
$can_view_statistics = has_permission($pdo, 'event_role.view_statistics');
$can_manage_skill_tags = has_permission($pdo, 'skill_tag.verify_others');
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
            <?php if ($can_view_roles): ?>
                <li class="menu-item <?= $main_section === 'roles' ? 'active' : '' ?>">
                    <a href="/events/roles/">
                        <i class="fas fa-user-tag"></i>
                        <span>Roller</span>
                    </a>
                </li>
            <?php endif; ?>
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

    <!-- Roller Alt Menüsü - YENİ YETKİ KONTROLLÜ -->
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
                    <?php if ($can_create_role): ?>
                        <li class="submenu-item <?= $current_page === 'create' ? 'active' : '' ?>">
                            <a href="/events/roles/create.php">
                                <i class="fas fa-plus"></i>
                                <span>Yeni Rol Oluştur</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($can_create_role): ?>
                    <li class="submenu-item <?= isset($_GET['filter']) && $_GET['filter'] === 'my_roles' ? 'active' : '' ?>">
                        <a href="/events/roles/?filter=my_roles">
                            <i class="fas fa-user"></i>
                            <span>Oluşturduklarım</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($can_view_statistics): ?>
                        <li class="submenu-item <?= $current_page === 'statistics' ? 'active' : '' ?>">
                            <a href="/events/roles/statistics.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>İstatistikler</span>
                            </a>
                        </li>
                    <?php endif; ?>
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

        <!-- Roller için özel yönetim menüsü -->
        <?php if ($can_manage_participants || $can_manage_skill_tags): ?>
            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="fas fa-cogs"></i>
                    Rol Yönetimi
                </h3>
                <ul class="sidebar-submenu">
                    <?php if ($can_manage_participants): ?>
                        <li class="submenu-item <?= $current_page === 'manage_participants' ? 'active' : '' ?>">
                            <a href="/events/roles/manage_participants.php">
                                <i class="fas fa-users-cog"></i>
                                <span>Katılımcı Yönetimi</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($can_manage_skill_tags): ?>
                        <li class="submenu-item <?= $current_page === 'skill_tags' ? 'active' : '' ?>">
                            <a href="/events/roles/skill_tags.php">
                                <i class="fas fa-tags"></i>
                                <span>Skill Tag Yönetimi</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Teçhizat Setleri Alt Menüsü - GÜNCELLENECEK -->
    <?php if ($main_section === 'loadouts'): ?>
        <div class="sidebar-section">
            <ul class="sidebar-submenu">
                <li class="submenu-item <?= $current_page === 'index' && !isset($_GET['visibility']) ? 'active' : '' ?>">
                    <a href="/events/loadouts/">
                        <i class="fas fa-list"></i>
                        <span>Tüm Setler</span>
                    </a>
                </li>
                <?php if ($can_create_loadout): ?>
                    <li class="submenu-item <?= $current_page === 'create' ? 'active' : '' ?>">
                        <a href="/events/loadouts/create.php">
                            <i class="fas fa-plus"></i>
                            <span>Yeni Set Oluştur</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (is_user_logged_in()): ?>
                    <li class="submenu-item <?= isset($_GET['filter']) && $_GET['filter'] === 'my_loadouts' ? 'active' : '' ?>">
                        <a href="/events/loadouts/?filter=my_loadouts">
                            <i class="fas fa-user"></i>
                            <span>Setlerim</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= isset($_GET['visibility']) && $_GET['visibility'] === 'public' ? 'active' : '' ?>">
                        <a href="/events/loadouts/?visibility=public">
                            <i class="fas fa-globe"></i>
                            <span>Herkese Açık</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= isset($_GET['visibility']) && $_GET['visibility'] === 'members_only' ? 'active' : '' ?>">
                        <a href="/events/loadouts/?visibility=members_only">
                            <i class="fas fa-users"></i>
                            <span>Sadece Üyeler</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- İstatistikler - ROLLER İÇİN -->
    <?php if ($main_section === 'roles' && $can_view_statistics): ?>
        <div class="sidebar-section sidebar-stats">
            <h3 class="sidebar-title">
                <i class="fas fa-chart-line"></i>
                Rol İstatistikleri
            </h3>
            <div class="stats-grid">
                <?php
                // Basit istatistikler çek
                try {
                    // Toplam rol sayısı
                    $total_roles_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_roles WHERE is_active = 1");
                    $total_roles_stmt->execute();
                    $total_roles = $total_roles_stmt->fetchColumn();
                    
                    // Kullanılan roller (etkinliklerde)
                    $used_roles_stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT event_role_id) 
                        FROM event_role_slots ers 
                        JOIN events e ON ers.event_id = e.id 
                        WHERE e.status != 'cancelled'
                    ");
                    $used_roles_stmt->execute();
                    $used_roles = $used_roles_stmt->fetchColumn();
                    
                    // Bu ay oluşturulan roller
                    $this_month_stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM event_roles 
                        WHERE is_active = 1 AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                        AND YEAR(created_at) = YEAR(CURRENT_DATE())
                    ");
                    $this_month_stmt->execute();
                    $this_month_roles = $this_month_stmt->fetchColumn();
                    
                    // Aktif katılımcılar
                    $active_participants_stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM event_role_participants erp
                        JOIN event_role_slots ers ON erp.event_role_slot_id = ers.id
                        JOIN events e ON ers.event_id = e.id
                        WHERE erp.status = 'active' AND e.status = 'active'
                    ");
                    $active_participants_stmt->execute();
                    $active_participants = $active_participants_stmt->fetchColumn();
                    
                } catch (PDOException $e) {
                    error_log("Sidebar stats error: " . $e->getMessage());
                    $total_roles = $used_roles = $this_month_roles = $active_participants = 0;
                }
                ?>
                
                <div class="stat-item-sidebar">
                    <div class="stat-number"><?= $total_roles ?></div>
                    <div class="stat-label">Toplam Rol</div>
                </div>
                
                <div class="stat-item-sidebar">
                    <div class="stat-number"><?= $used_roles ?></div>
                    <div class="stat-label">Kullanılan</div>
                </div>
                
                <div class="stat-item-sidebar">
                    <div class="stat-number"><?= $this_month_roles ?></div>
                    <div class="stat-label">Bu Ay</div>
                </div>
                
                <div class="stat-item-sidebar">
                    <div class="stat-number"><?= $active_participants ?></div>
                    <div class="stat-label">Katılımcı</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>