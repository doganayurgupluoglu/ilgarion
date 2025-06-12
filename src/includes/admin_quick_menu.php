<?php
// src/includes/admin_quick_menu.php - Admin Quick Access Menu

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Admin yetkisi kontrolü
$show_admin_menu = false;
if (isset($pdo) && is_user_logged_in()) {
    $show_admin_menu = has_permission($pdo, 'admin.panel.access');
}

// Admin menüsü gösterilmeyecekse hiçbir şey yapma
if (!$show_admin_menu) {
    return;
}

// Admin quick menu items
$admin_menu_items = [
    [
        'section' => 'Dashboard',
        'items' => [
            [
                'url' => '/admin/dashboard.php',
                'icon' => 'fas fa-tachometer-alt',
                'text' => 'Ana Dashboard',
                'description' => 'Genel istatistikler ve özet',
                'permission' => 'admin.panel.access'
            ]
        ]
    ],
    [
        'section' => 'Kullanıcı Yönetimi',
        'items' => [
            [
                'url' => '/admin/users/',
                'icon' => 'fas fa-users-cog',
                'text' => 'Kullanıcı Yönetimi',
                'description' => 'Kullanıcıları görüntüle ve yönet',
                'permission' => 'admin.users.view'
            ],
            [
                'url' => '/admin/manage_roles/',
                'icon' => 'fas fa-user-tag',
                'text' => 'Rol Yönetimi',
                'description' => 'Rolleri ve yetkileri yönet',
                'permission' => 'admin.roles.view'
            ]
        ]
    ],
    [
        'section' => 'İçerik Yönetimi',
        'items' => [
            [
                'url' => '/admin/events/',
                'icon' => 'fas fa-calendar-check',
                'text' => 'Etkinlik Yönetimi',
                'description' => 'Etkinlikleri yönet ve denetle',
                'permission' => 'event.edit_all'
            ],
            [
                'url' => '/admin/forum/',
                'icon' => 'fas fa-comments-dollar',
                'text' => 'Forum Yönetimi',
                'description' => 'Forum kategorileri ve moderasyon',
                'permission' => 'forum.category.manage'
            ],
            [
                'url' => '/admin/gallery/',
                'icon' => 'fas fa-images',
                'text' => 'Galeri Yönetimi',
                'description' => 'Galeri içeriklerini yönet',
                'permission' => 'gallery.manage_all'
            ]
        ]
    ],
    [
        'section' => 'Sistem',
        'items' => [
            [
                'url' => '/admin/settings/',
                'icon' => 'fas fa-cogs',
                'text' => 'Sistem Ayarları',
                'description' => 'Site ayarları ve konfigürasyon',
                'permission' => 'admin.settings.view'
            ],
            [
                'url' => '/admin/audit/',
                'icon' => 'fas fa-clipboard-list',
                'text' => 'Audit Log',
                'description' => 'Sistem aktiviteleri ve loglar',
                'permission' => 'admin.audit_log.view'
            ]
        ]
    ]
];

// Yetkiye göre menü öğelerini filtrele
$filtered_menu = [];
foreach ($admin_menu_items as $section) {
    $filtered_items = [];
    foreach ($section['items'] as $item) {
        if (has_permission($pdo, $item['permission'])) {
            $filtered_items[] = $item;
        }
    }
    if (!empty($filtered_items)) {
        $filtered_menu[] = [
            'section' => $section['section'],
            'items' => $filtered_items
        ];
    }
}

// Eğer hiç menü öğesi yoksa gösterme
if (empty($filtered_menu)) {
    return;
}
?>

<!-- Admin Quick Menu Component -->
<div id="adminQuickMenu" class="admin-quick-menu" style="display: none;">
    <div class="admin-menu-container">
        <div class="admin-menu-header">
            <div class="admin-menu-title">
                <i class="fas fa-shield-alt"></i>
                <span>Admin Panel</span>
            </div>
            <button class="admin-menu-close" onclick="closeAdminQuickMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="admin-menu-content">
            <?php foreach ($filtered_menu as $section): ?>
                <div class="admin-menu-section">
                    <h6 class="admin-section-title"><?= htmlspecialchars($section['section']) ?></h6>
                    <div class="admin-menu-items">
                        <?php foreach ($section['items'] as $item): ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="admin-menu-item">
                                <div class="admin-item-icon">
                                    <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                </div>
                                <div class="admin-item-content">
                                    <span class="admin-item-title"><?= htmlspecialchars($item['text']) ?></span>
                                    <span class="admin-item-description"><?= htmlspecialchars($item['description']) ?></span>
                                </div>
                                <div class="admin-item-arrow">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="admin-menu-footer">
                <div class="admin-quick-stats">
                    <?php
                    // Hızlı istatistikler
                    try {
                        $stats = [];
                        
                        // Bekleyen kullanıcı sayısı
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
                        $stmt->execute();
                        $pending_users = $stmt->fetchColumn();
                        if ($pending_users > 0) {
                            $stats[] = ['icon' => 'fas fa-user-clock', 'text' => "$pending_users bekleyen üye"];
                        }
                        
                        // Bugünkü forum aktivitesi
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE DATE(created_at) = CURDATE()");
                        $stmt->execute();
                        $today_posts = $stmt->fetchColumn();
                        if ($today_posts > 0) {
                            $stats[] = ['icon' => 'fas fa-comment', 'text' => "$today_posts bugünkü gönderi"];
                        }
                        
                        // Bugünkü etkinlik sayısı
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE DATE(event_date) = CURDATE() AND status = 'published'");
                        $stmt->execute();
                        $today_events = $stmt->fetchColumn();
                        if ($today_events > 0) {
                            $stats[] = ['icon' => 'fas fa-calendar', 'text' => "$today_events bugünkü etkinlik"];
                        }
                        
                    } catch (Exception $e) {
                        $stats = [];
                    }
                    ?>
                    
                    <?php if (!empty($stats)): ?>
                        <div class="quick-stats-title">Hızlı Durum</div>
                        <?php foreach ($stats as $stat): ?>
                            <div class="quick-stat-item">
                                <i class="<?= htmlspecialchars($stat['icon']) ?>"></i>
                                <span><?= htmlspecialchars($stat['text']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="quick-stats-empty">
                            <i class="fas fa-check-circle"></i>
                            <span>Bekleyen işlem yok</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Quick Menu Toggle Button -->
<div class="admin-menu-toggle" onclick="toggleAdminQuickMenu()" title="Admin Menü">
    <i class="fas fa-shield-alt"></i>
    <span class="admin-toggle-text">Admin</span>
</div>

<style>
/* Admin Quick Menu Styles */
.admin-quick-menu {
    position: fixed;
    top: 0;
    right: 0;
    width: 380px;
    height: 100vh;
    background: var(--card-bg-4);
    border-left: 1px solid var(--border-1-featured);
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.5);
    z-index: 15000;
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-family: var(--font);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.admin-quick-menu.show {
    transform: translateX(0);
}

.admin-menu-container {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.admin-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    background: var(--gold);
    color: var(--charcoal);
    border-bottom: 1px solid var(--border-1-featured);
}

.admin-menu-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    font-size: 1.1rem;
}

.admin-menu-title i {
    font-size: 1.2rem;
}

.admin-menu-close {
    background: none;
    border: none;
    color: var(--charcoal);
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.admin-menu-close:hover {
    background: rgba(0, 0, 0, 0.1);
}

.admin-menu-content {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
}

.admin-menu-section {
    margin-bottom: 1.5rem;
}

.admin-section-title {
    color: var(--gold);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 0.75rem 0;
    padding: 0 1.5rem;
}

.admin-menu-items {
    display: flex;
    flex-direction: column;
}

.admin-menu-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    color: var(--lighter-grey);
    text-decoration: none;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.admin-menu-item:hover {
    background: var(--card-bg-2);
    border-left-color: var(--gold);
    text-decoration: none;
    color: var(--white);
}

.admin-item-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: var(--transparent-gold);
    border-radius: 6px;
    color: var(--gold);
    font-size: 0.9rem;
}

.admin-menu-item:hover .admin-item-icon {
    background: var(--gold);
    color: var(--charcoal);
}

.admin-item-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.admin-item-title {
    font-weight: 500;
    font-size: 0.9rem;
}

.admin-item-description {
    font-size: 0.75rem;
    color: var(--light-grey);
    line-height: 1.3;
}

.admin-item-arrow {
    color: var(--light-grey);
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.admin-menu-item:hover .admin-item-arrow {
    color: var(--gold);
    transform: translateX(3px);
}

.admin-menu-footer {
    margin-top: auto;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-1);
    background: var(--card-bg-3);
}

.admin-quick-stats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.quick-stats-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.quick-stat-item,
.quick-stats-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--light-grey);
}

.quick-stat-item i,
.quick-stats-empty i {
    color: var(--gold);
    width: 14px;
    text-align: center;
}

.quick-stats-empty {
    justify-content: center;
    padding: 0.5rem;
    font-style: italic;
}

/* Admin Menu Toggle Button */
.admin-menu-toggle {
    position: fixed;
    top: calc(var(--navbar-height) + 1rem);
    right: 1rem;
    background: var(--gold);
    color: var(--charcoal);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    z-index: 14000;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-1-featured);
}

.admin-menu-toggle:hover {
    background: var(--light-gold);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.admin-toggle-text {
    font-family: var(--font);
}

/* Overlay */
.admin-menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 14999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.admin-menu-overlay.show {
    opacity: 1;
    visibility: visible;
}

/* Responsive Design */
@media (max-width: 768px) {
    .admin-quick-menu {
        width: 100%;
        border-left: none;
    }
    
    .admin-menu-toggle {
        right: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .admin-toggle-text {
        display: none;
    }
}

@media (max-width: 480px) {
    .admin-menu-item {
        padding: 0.75rem 1rem;
    }
    
    .admin-item-icon {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
    
    .admin-item-title {
        font-size: 0.85rem;
    }
    
    .admin-item-description {
        font-size: 0.7rem;
    }
}

/* Animation Enhancements */
@keyframes adminMenuSlideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.admin-quick-menu.show {
    animation: adminMenuSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Scrollbar Styling for Admin Menu */
.admin-menu-content::-webkit-scrollbar {
    width: 6px;
}

.admin-menu-content::-webkit-scrollbar-track {
    background: var(--card-bg-3);
}

.admin-menu-content::-webkit-scrollbar-thumb {
    background: var(--border-1);
    border-radius: 3px;
}

.admin-menu-content::-webkit-scrollbar-thumb:hover {
    background: var(--border-1-hover);
}

/* Focus States for Accessibility */
.admin-menu-item:focus,
.admin-menu-close:focus,
.admin-menu-toggle:focus {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
}
</style>

<script>
// Admin Quick Menu JavaScript
let adminMenuVisible = false;
let adminMenuOverlay = null;

/**
 * Admin menüyü aç/kapat
 */
function toggleAdminQuickMenu() {
    if (adminMenuVisible) {
        closeAdminQuickMenu();
    } else {
        openAdminQuickMenu();
    }
}

/**
 * Admin menüyü aç
 */
function openAdminQuickMenu() {
    const menu = document.getElementById('adminQuickMenu');
    if (!menu) return;
    
    // Overlay oluştur
    if (!adminMenuOverlay) {
        adminMenuOverlay = document.createElement('div');
        adminMenuOverlay.className = 'admin-menu-overlay';
        adminMenuOverlay.onclick = closeAdminQuickMenu;
        document.body.appendChild(adminMenuOverlay);
    }
    
    // Menüyü göster
    menu.style.display = 'flex';
    
    // Animation için timeout
    setTimeout(() => {
        menu.classList.add('show');
        if (adminMenuOverlay) {
            adminMenuOverlay.classList.add('show');
        }
        adminMenuVisible = true;
        
        // Body scroll'u engelle
        document.body.style.overflow = 'hidden';
        
        // İlk focusable element'e focus
        const firstFocusable = menu.querySelector('.admin-menu-item, .admin-menu-close');
        if (firstFocusable) {
            firstFocusable.focus();
        }
    }, 10);
    
    // Analytics tracking
    if (window.gtag) {
        gtag('event', 'admin_menu_opened', {
            event_category: 'Admin Interface',
            value: 1
        });
    }
}

/**
 * Admin menüyü kapat
 */
function closeAdminQuickMenu() {
    const menu = document.getElementById('adminQuickMenu');
    if (!menu) return;
    
    menu.classList.remove('show');
    if (adminMenuOverlay) {
        adminMenuOverlay.classList.remove('show');
    }
    
    adminMenuVisible = false;
    
    // Body scroll'u geri yükle
    document.body.style.overflow = '';
    
    setTimeout(() => {
        menu.style.display = 'none';
        if (adminMenuOverlay && adminMenuOverlay.parentNode) {
            adminMenuOverlay.parentNode.removeChild(adminMenuOverlay);
            adminMenuOverlay = null;
        }
    }, 300);
}

/**
 * Klavye event handler'ları
 */
function setupAdminMenuKeyboardEvents() {
    document.addEventListener('keydown', function(e) {
        if (!adminMenuVisible) return;
        
        switch(e.key) {
            case 'Escape':
                e.preventDefault();
                closeAdminQuickMenu();
                break;
                
            case 'Tab':
                // Tab navigation içinde tut
                const menu = document.getElementById('adminQuickMenu');
                const focusableElements = menu.querySelectorAll('.admin-menu-item, .admin-menu-close');
                const firstFocusable = focusableElements[0];
                const lastFocusable = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
                break;
        }
    });
}

/**
 * Admin menü item click tracking
 */
function trackAdminMenuClick(url, title) {
    if (window.gtag) {
        gtag('event', 'admin_menu_click', {
            event_category: 'Admin Interface',
            event_label: title,
            value: 1
        });
    }
}

/**
 * Responsive handling
 */
function handleAdminMenuResize() {
    if (adminMenuVisible && window.innerWidth <= 768) {
        const menu = document.getElementById('adminQuickMenu');
        if (menu) {
            menu.style.width = '100%';
        }
    }
}

/**
 * Performance monitoring
 */
function monitorAdminMenuPerformance() {
    const toggleButton = document.querySelector('.admin-menu-toggle');
    if (!toggleButton) return;
    
    let clickTime;
    toggleButton.addEventListener('mousedown', () => {
        clickTime = performance.now();
    });
    
    toggleButton.addEventListener('click', () => {
        if (clickTime) {
            const responseTime = performance.now() - clickTime;
            if (responseTime > 100) { // 100ms'den fazla
                console.warn('Admin menu response time:', responseTime + 'ms');
            }
        }
    });
}

/**
 * Notification system integration
 */
function checkAdminNotifications() {
    // Periyodik olarak admin bildirimlerini kontrol et
    setInterval(async () => {
        try {
            const response = await fetch('/src/api/admin_notifications.php');
            const data = await response.json();
            
            if (data.success && data.notifications > 0) {
                updateAdminMenuBadge(data.notifications);
            }
        } catch (error) {
            console.log('Admin notification check failed (non-critical):', error);
        }
    }, 30000); // 30 saniyede bir
}

/**
 * Admin menü badge güncelle
 */
function updateAdminMenuBadge(count) {
    const toggle = document.querySelector('.admin-menu-toggle');
    if (!toggle) return;
    
    let badge = toggle.querySelector('.admin-badge');
    if (!badge && count > 0) {
        badge = document.createElement('span');
        badge.className = 'admin-badge';
        badge.style.cssText = `
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid var(--card-bg);
        `;
        toggle.style.position = 'relative';
        toggle.appendChild(badge);
    }
    
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setupAdminMenuKeyboardEvents();
    monitorAdminMenuPerformance();
    checkAdminNotifications();
    
    // Window resize handler
    window.addEventListener('resize', handleAdminMenuResize);
    
    // Menu item click tracking
    document.addEventListener('click', function(e) {
        const adminMenuItem = e.target.closest('.admin-menu-item');
        if (adminMenuItem) {
            const title = adminMenuItem.querySelector('.admin-item-title')?.textContent;
            const url = adminMenuItem.href;
            if (title && url) {
                trackAdminMenuClick(url, title);
            }
        }
    });
});

// Global functions
window.toggleAdminQuickMenu = toggleAdminQuickMenu;
window.closeAdminQuickMenu = closeAdminQuickMenu;
window.openAdminQuickMenu = openAdminQuickMenu;
</script>