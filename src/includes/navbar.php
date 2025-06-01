<?php
// src/includes/navbar.php - Yetki Tabanlı Navbar

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('get_auth_base_url')) {
    function get_auth_base_url() {
        return ''; // Varsayılan olarak kök dizin
    }
}
$baseUrl = get_auth_base_url();

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return isset($_SESSION['user_id']);
    }
}
$is_logged_in = is_user_logged_in();

if (!function_exists('is_user_admin')) {
    function is_user_admin() {
        global $pdo;
        return ($is_logged_in && is_admin($pdo));
    }
}
$is_admin = $is_logged_in && is_user_admin();

$unread_notification_count = $GLOBALS['unread_notification_count'] ?? 0;
$notifications_for_dropdown = $GLOBALS['notifications_for_dropdown'] ?? [];

$username = $is_logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
$user_id_for_links = $is_logged_in && isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_avatar_path = ''; // Varsayılan olarak boş

if ($is_logged_in && isset($_SESSION['user_avatar_path']) && !empty($_SESSION['user_avatar_path'])) {
    $user_avatar_path = '/public/' . htmlspecialchars($_SESSION['user_avatar_path']);
}

$current_page_navbar = basename($_SERVER['PHP_SELF']);

// Navbar menü yapılandırması - Yetki tabanlı
$nav_links_config_navbar = [
    [
        'title' => 'Ana Sayfa',
        'url' => 'index.php',
        'icon' => 'fa-home',
        'permission' => null, // Herkese açık
        'show_to_guests' => true
    ],
    [
        'title' => 'Etkinlikler',
        'url' => 'events.php',
        'icon' => 'fa-calendar-alt',
        'permission' => 'event.view_public',
        'show_to_guests' => true // Herkese açık etkinlikler için
    ],
    [
        'title' => 'Galeri',
        'url' => 'gallery.php',
        'icon' => 'fa-images',
        'permission' => 'gallery.view_public',
        'show_to_guests' => true // Herkese açık galeri için
    ],
    [
        'title' => 'Tartışmalar',
        'url' => 'discussions.php',
        'icon' => 'fa-comments',
        'permission' => 'discussion.view_public',
        'show_to_guests' => false // Sadece üyeler
    ],
    [
        'title' => 'Rehberler',
        'url' => 'guides.php',
        'icon' => 'fa-book-open',
        'permission' => 'guide.view_public',
        'show_to_guests' => true // Herkese açık rehberler için
    ],
    [
        'title' => 'Teçhizat Setleri',
        'url' => 'loadouts.php',
        'icon' => 'fa-user-shield',
        'permission' => 'loadout.view_public',
        'show_to_guests' => false // Sadece üyeler
    ],
    [
        'title' => 'Üyeler',
        'url' => 'members.php',
        'icon' => 'fa-users',
        'permission' => 'discussion.view_approved', // Onaylı üyelerin listesi
        'show_to_guests' => false
    ]
];

// Yetki kontrolü fonksiyonu
function can_show_nav_item($nav_item, $is_logged_in, $pdo = null) {
    global $is_logged_in;
    
    // Eğer misafir kullanıcıya gösterilmeyecekse ve kullanıcı giriş yapmamışsa
    if (!$nav_item['show_to_guests'] && !$is_logged_in) {
        return false;
    }
    
    // Eğer yetki belirtilmemişse (herkese açık)
    if ($nav_item['permission'] === null) {
        return true;
    }
    
    // Eğer giriş yapmamışsa ve yetki gerekliyse
    if (!$is_logged_in) {
        return false;
    }
    
    // Eğer kullanıcı giriş yapmışsa ve PDO mevcut ise yetki kontrolü yap
    if ($pdo && function_exists('has_permission')) {
        return has_permission($pdo, $nav_item['permission']);
    }
    
    // Varsayılan olarak göster (fallback)
    return $is_logged_in;
}

// Admin menü öğeleri - Sadece admin paneline erişimi olan kullanıcılar için
$admin_nav_items = [];
if ($is_logged_in && isset($pdo) && function_exists('has_permission')) {
    if (has_permission($pdo, 'admin.panel.access')) {
        $admin_nav_items = [
            [
                'title' => 'Admin Paneli',
                'url' => 'admin/index.php',
                'icon' => 'fa-shield-alt',
                'permission' => 'admin.panel.access'
            ]
        ];
        
        // Ek admin menüleri
        if (has_permission($pdo, 'admin.roles.view')) {
            $admin_nav_items[] = [
                'title' => 'Rol Yönetimi',
                'url' => 'admin/manage_roles.php',
                'icon' => 'fa-user-tag',
                'permission' => 'admin.roles.view'
            ];
        }
        
        if (has_permission($pdo, 'admin.users.view')) {
            $admin_nav_items[] = [
                'title' => 'Kullanıcı Yönetimi', 
                'url' => 'admin/users.php',
                'icon' => 'fa-user-cog',
                'permission' => 'admin.users.view'
            ];
        }
        
        if (has_permission($pdo, 'admin.audit_log.view')) {
            $admin_nav_items[] = [
                'title' => 'Audit Log',
                'url' => 'admin/audit_log.php',
                'icon' => 'fa-clipboard-list',
                'permission' => 'admin.audit_log.view'
            ];
        }
    }
}

// Kullanıcı özel menü öğeleri - Giriş yapmış kullanıcılar için
$user_nav_items = [];
if ($is_logged_in && isset($pdo) && function_exists('has_permission')) {
    // İçerik oluşturma yetkileri
    if (has_permission($pdo, 'event.create')) {
        $user_nav_items[] = [
            'title' => 'Etkinlik Oluştur',
            'url' => 'create_event.php',
            'icon' => 'fa-plus-circle',
            'permission' => 'event.create'
        ];
    }
    
    if (has_permission($pdo, 'guide.create')) {
        $user_nav_items[] = [
            'title' => 'Rehber Yaz',
            'url' => 'create_guide.php',
            'icon' => 'fa-pen',
            'permission' => 'guide.create'
        ];
    }
    
    if (has_permission($pdo, 'gallery.upload')) {
        $user_nav_items[] = [
            'title' => 'Fotoğraf Yükle',
            'url' => 'upload_photo.php',
            'icon' => 'fa-camera',
            'permission' => 'gallery.upload'
        ];
    }
}

if (!function_exists('hexToRgb')) {
    function hexToRgb($hex) {
        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return "$r, $g, $b";
    }
}

// Logo yolu
$logo_path_navbar = '/assets/logo.png'; 

?>

<style>
    :root {
        --font-main: 'Roboto', sans-serif;
        --navbar-height: 70px;
    }

    body {
        padding-top: var(--navbar-height); 
    }

    .dynamic-navbar {
        background-color: rgba(0,0,0,96); 
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--dark-gold-green-border);
        padding: 0 20px;
        height: var(--navbar-height);
        display: flex;
        align-items: center;
        justify-content: space-between; /* Güncellenmiş: space-evenly yerine space-between */
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .dynamic-navbar.scrolled {
        background-color: transparent; 
        border-bottom: 1px solid var(--border-color);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    .dynamic-navbar-brand {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #b8845c;
        flex-shrink: 0; /* Logo'nun küçülmesini engelle */
    }

    .dynamic-navbar-brand img {
        height: 70px; 
        transition: transform 0.3s ease;
        filter: drop-shadow(0 0 8px var(--darker-gold-1)); 
    }

    .dynamic-navbar-brand img:hover {
        transform: scale(1.08); 
    }

    .dynamic-navbar-brand .site-title {
        color: var(--light-gold);
        margin-left: 15px; 
        font-size: 1.5rem; 
        font-weight: 700;
        font-family: var(--font-main);
    }
    
    .navbar-center-links {
        display: flex;
        justify-content: center;
        flex-grow: 1; /* Orta bölümün genişlemesini sağla */
        margin: 0 20px; /* Sağ sol margin */
    }
    
    .navbar-center-links ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center; 
        gap: 5px;
        flex-wrap: wrap; /* Responsive için wrap */
    }
    
    .navbar-center-links ul li a {
        color: var(--text-color, #b3b3b3);
        text-decoration: none;
        padding: 10px 14px; 
        border-radius: 20px; 
        font-weight: 500; 
        font-size: 0.9rem; 
        transition: background-color 0.25s ease, color 0.25s ease, transform 0.15s ease, box-shadow 0.2s ease;
        position: relative; 
        letter-spacing: 0.3px;
        display: flex; 
        align-items: center;
        gap: 6px;
        white-space: nowrap; /* Metinlerin satır atlamasını engelle */
    }
    
    .navbar-center-links ul li a:hover {
        background-color: var(--surface-color, #1E1E1E); 
        color: var(--secondary-color, #45D0C1); 
        transform: translateY(-1px); 
        box-shadow: 0 2px 6px rgba(0,0,0,0.1); 
    }
    
    .navbar-center-links ul li a.active-nav-link { 
        color: var(--secondary-color, #45D0C1);
        font-weight: 600; 
        background-color: rgba(var(--secondary-color-rgb, 69, 208, 193), 0.15); 
    }
    
    .navbar-center-links ul li a.active-nav-link::after {
        content: '';
        position: absolute;
        bottom: 4px; 
        left: 50%;
        transform: translateX(-50%);
        width: 50%; 
        height: 2.5px; 
        background-color: var(--secondary-color, #45D0C1);
        border-radius: 2px;
    }
    
    .navbar-center-links ul li a .fas { 
        font-size: 0.9em; 
        opacity: 0.8;
    }
    
    .navbar-center-links ul li a:hover .fas,
    .navbar-center-links ul li a.active-nav-link .fas {
        opacity: 1;
    }

    /* Yetki tabanlı stil eklentileri */
    .nav-item-premium {
        position: relative;
    }
    
    .nav-item-premium::before {
        content: '★';
        position: absolute;
        top: -2px;
        right: -2px;
        color: var(--gold);
        font-size: 0.6rem;
        animation: sparkle 2s ease-in-out infinite;
    }
    
    @keyframes sparkle {
        0%, 100% { opacity: 0.7; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.2); }
    }
    
    .nav-item-admin {
        border-left: 2px solid var(--red);
        background: linear-gradient(90deg, rgba(255,0,0,0.1), transparent);
    }
    
    .nav-item-user-content {
        border-left: 2px solid var(--turquase);
        background: linear-gradient(90deg, rgba(61, 166, 162, 0.1), transparent);
    }

    .dynamic-navbar-actions {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-shrink: 0; /* Action'ların küçülmesini engelle */
    }

    /* Dropdown menü stilleri */
    .navbar-dropdown {
        position: relative;
    }
    
    .navbar-dropdown-content {
        position: absolute;
        top: 100%;
        left: 0;
        background-color: var(--charcoal);
        border: 1px solid var(--darker-gold-1);
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        z-index: 1001;
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
        pointer-events: none;
        min-width: 200px;
        overflow: hidden;
    }
    
    .navbar-dropdown:hover .navbar-dropdown-content {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    
    .navbar-dropdown-content a {
        display: block !important;
        padding: 12px 16px !important;
        color: var(--lighter-grey) !important;
        text-decoration: none !important;
        border-radius: 0 !important;
        font-size: 0.9rem !important;
        transition: background-color 0.2s ease !important;
    }
    
    .navbar-dropdown-content a:hover {
        background-color: var(--darker-gold-1) !important;
        color: var(--gold) !important;
        transform: none !important;
        box-shadow: none !important;
    }
    
    .navbar-dropdown-content a .fas {
        margin-right: 8px;
        width: 16px;
        text-align: center;
    }

    /* Diğer mevcut stiller... */
    .nav-action-item {
        position: relative;
    }

    .nav-icon-btn,
    .nav-text-btn {
        color: var(--text-color);
        text-decoration: none;
        font-size: 1rem;
        padding: 10px 15px;
        border-radius: 6px;
        transition: background-color 0.3s ease, color 0.3s ease;
        cursor: pointer;
        background: none;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-icon-btn {
        font-size: 1.3rem;
        padding: 10px;
    }

    .nav-icon-btn:hover,
    .nav-text-btn:hover {
        background-color: var(--surface-color);
        color: var(--secondary-color);
    }

    .nav-text-btn.login-btn {
        border: 1px solid var(--secondary-color);
        color: var(--secondary-color);
    }

    .nav-text-btn.login-btn:hover {
        background-color: var(--secondary-color);
        color: var(--background-color);
    }

    .nav-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--gold);
        transition: transform 0.3s ease;
    }

    .nav-icon-btn:hover .nav-user-avatar {
        transform: scale(1.1);
    }

    .nav-user-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--surface-color);
        color: var(--primary-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: bold;
        border: 2px solid var(--border-color);
        transition: transform 0.3s ease;
    }

    .nav-icon-btn:hover .nav-user-avatar-placeholder {
        transform: scale(1.1);
    }

    .notification-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: var(--accent-color);
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 0.7rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--background-color);
    }

    /* Diğer mevcut dropdown paneli stilleri burada kalacak... */
    .dropdown-panel {
        position: absolute;
        top: calc(var(--navbar-height) - 10px);
        right: 0;
        width: 300px;
        background-color: var(--charcoal); 
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        z-index: 1001;
        opacity: 0;
        transform: translateY(10px) scale(0.95);
        transition: opacity 0.3s ease, transform 0.3s ease;
        pointer-events: none;
        padding: 15px;
        overflow-y: auto;
        max-height: calc(100vh - var(--navbar-height) - 20px);
    }

    .dropdown-panel.open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    /* Mobile responsive */
    .mobile-menu-trigger {
        display: none;
        background: none;
        border: none;
        color: var(--primary-color);
        font-size: 1.8rem;
        cursor: pointer;
        padding: 5px;
        z-index: 1005;
    }

    @media (max-width: 991.98px) { 
        .dynamic-navbar-brand .site-title { display: none; } 
        .navbar-center-links { display: none; }
        .dynamic-navbar-actions .desktop-only { display: none; }
        .mobile-menu-trigger { display: block; }
        .dynamic-navbar { justify-content: space-between; } 
    }
    
    @media (min-width: 992px) {
        .mobile-menu-trigger { display: none !important; } 
        .mobile-menu-overlay { display: none !important; } 
    }

     :root {
        --font-main: 'Roboto', sans-serif;
        --navbar-height: 70px;
    }

    body {
        padding-top: var(--navbar-height); 
    }

    .dynamic-navbar {
        background-color: rgba(0,0,0,96); 
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--dark-gold-green-border);
        padding: 0 20px;
        height: var(--navbar-height);
        display: flex;
        align-items: center;
        justify-content: space-evenly; /* Güncellendi */
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .dynamic-navbar.scrolled {
        background-color: transparent; 
        border-bottom: 1px solid var(--border-color);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    .dynamic-navbar-brand {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #b8845c;
    }

    .dynamic-navbar-brand img {
        height: 70px; 
        transition: transform 0.3s ease;
        filter: drop-shadow(0 0 8px var(--darker-gold-1)); 
    }

    .dynamic-navbar-brand img:hover {
        transform: scale(1.08); 
    }

    .dynamic-navbar-brand .site-title {
        color: var(--light-gold);
        margin-left: 15px; 
        font-size: 1.5rem; 
        font-weight: 700;
        font-family: var(--font-main);
    }
    
    .navbar-center-links {
        display: flex;
        justify-content: center; 
    }
    .navbar-center-links ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center; 
        gap: 5px; 
    }
    .navbar-center-links ul li a {
        color: var(--text-color, #b3b3b3);
        text-decoration: none;
        padding: 10px 14px; 
        border-radius: 20px; 
        font-weight: 500; 
        font-size: 0.9rem; 
        transition: background-color 0.25s ease, color 0.25s ease, transform 0.15s ease, box-shadow 0.2s ease;
        position: relative; 
        letter-spacing: 0.3px;
        display: flex; 
        align-items: center;
        gap: 6px;
    }
    .navbar-center-links ul li a:hover {
        background-color: var(--surface-color, #1E1E1E); 
        color: var(--secondary-color, #45D0C1); 
        transform: translateY(-1px); 
        box-shadow: 0 2px 6px rgba(0,0,0,0.1); 
    }
    .navbar-center-links ul li a.active-nav-link { 
        color: var(--secondary-color, #45D0C1);
        font-weight: 600; 
        background-color: rgba(var(--secondary-color-rgb, 69, 208, 193), 0.15); 
    }
    .navbar-center-links ul li a.active-nav-link::after {
        content: '';
        position: absolute;
        bottom: 4px; 
        left: 50%;
        transform: translateX(-50%);
        width: 50%; 
        height: 2.5px; 
        background-color: var(--secondary-color, #45D0C1);
        border-radius: 2px;
    }
    .navbar-center-links ul li a .fas { 
        font-size: 0.9em; 
        opacity: 0.8;
    }
    .navbar-center-links ul li a:hover .fas,
    .navbar-center-links ul li a.active-nav-link .fas {
        opacity: 1;
    }

    .dynamic-navbar-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .nav-action-item {
        position: relative;
    }

    .nav-icon-btn,
    .nav-text-btn {
        color: var(--text-color);
        text-decoration: none;
        font-size: 1rem;
        padding: 10px 15px;
        border-radius: 6px;
        transition: background-color 0.3s ease, color 0.3s ease;
        cursor: pointer;
        background: none;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-icon-btn {
        font-size: 1.3rem;
        padding: 10px;
    }

    .nav-icon-btn:hover,
    .nav-text-btn:hover {
        background-color: var(--surface-color);
        color: var(--secondary-color);
    }

    .nav-text-btn.login-btn {
        border: 1px solid var(--secondary-color);
        color: var(--secondary-color);
    }

    .nav-text-btn.login-btn:hover {
        background-color: var(--secondary-color);
        color: var(--background-color);
    }

    .nav-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--gold);
        transition: transform 0.3s ease;
    }

    .nav-icon-btn:hover .nav-user-avatar {
        transform: scale(1.1);
    }

    .nav-user-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--surface-color);
        color: var(--primary-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: bold;
        border: 2px solid var(--border-color);
        transition: transform 0.3s ease;
    }

    .nav-icon-btn:hover .nav-user-avatar-placeholder {
        transform: scale(1.1);
    }

    .notification-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: var(--accent-color);
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 0.7rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--background-color);
    }

    .dropdown-panel {
        position: absolute;
        top: calc(var(--navbar-height) - 10px);
        right: 0;
        width: 300px;
        background-color: var(--charcoal); 
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        z-index: 1001;
        opacity: 0;
        transform: translateY(10px) scale(0.95);
        transition: opacity 0.3s ease, transform 0.3s ease;
        pointer-events: none;
        padding: 15px;
        overflow-y: auto;
        max-height: calc(100vh - var(--navbar-height) - 20px);
    }

    .dropdown-panel.open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    .dropdown-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 10px;
        margin-bottom: 10px;
        border-bottom: 1px solid var(--darker-gold-2);
        color: var(--gold);
        font-size: 1.1rem;
    }

    .dropdown-panel-header .close-dropdown-btn {
        background: none;
        border: none;
        color: var(--text-color-muted);
        font-size: 1.5rem;
        cursor: pointer;
    }

    .dropdown-panel-header .close-dropdown-btn:hover {
        color: var(--accent-color);
    }

    .dropdown-panel--user {
        width: 320px;
    }

    .user-dropdown-profile {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .user-dropdown-profile img,
    .user-dropdown-profile .avatar-placeholder-dropdown {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin-right: 15px;
        object-fit: cover;
        border: 2px solid var(--gold);
        object-fit: cover;
    }

    .user-dropdown-profile .avatar-placeholder-dropdown {
        background-color: var(--background-color);
        font-size: 1.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--gold);

    }

    .user-dropdown-profile div h4 {
        margin: 0 0 5px;
        color: var(--gold);
    }

    .user-dropdown-profile div a {
        font-size: 0.9em;
        color: var(--turquase);
        text-decoration: none;
    }

    .user-dropdown-profile div a:hover {
        text-decoration: underline;
        color: var(--light-turquase)
    }

    .dropdown-menu-section h5 {
        color: var(--gold);
        font-size: 0.8rem;
        text-transform: uppercase;
        margin: 15px 0 8px 0;
        letter-spacing: 0.5px;
    }

    .dropdown-menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .dropdown-menu-list li a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 5px;
        color: var(--lighter-grey);
        text-decoration: none;
        font-size: 0.95rem;
        border-radius: 4px;
        transition: background-color 0.2s ease, color 0.2s ease, padding-left 0.2s ease;
    }

    .dropdown-menu-list li a:hover {
        background-color: var(--grey);
        color: var(--light-turquase);
        padding-left: 10px;
    }

    .dropdown-menu-list li a i.fa-fw {
        width: 18px;
        text-align: center;
        color: var(--text-color-muted);
        transition: color 0.2s ease;
    }

    .dropdown-menu-list li a:hover i.fa-fw {
        color: var(--secondary-color);
    }

    .dropdown-menu-list li.logout-item a {
        color: var(--red);
    }

    .dropdown-menu-list li.logout-item a:hover {
        background-color: var(--red);
        color: var(--black);
    }

    .dropdown-menu-list li.logout-item a:hover i.fa-fw {
        color: var(--surface-color);
    }

    .dropdown-panel--notifications .notification-item {
        display: flex;
        align-items: flex-start;
        padding: 10px 0;
        border-bottom: 1px solid var(--gold);
        font-size: 0.9rem;
        color: var(--text-color);
        text-decoration: none;
        transition: background-color 0.2s ease;
    }

    .dropdown-panel--notifications .notification-item:last-child {
        border-bottom: none;
    }

    .dropdown-panel--notifications .notification-item:hover {
        background-color: var(--background-color);
    }

    .dropdown-panel--notifications .notification-item.unread {
        font-weight: bold;
    }

    .dropdown-panel--notifications .notification-icon {
        margin-right: 10px;
        color: var(--secondary-color);
    }

    .dropdown-panel--notifications .no-notifications {
        padding: 20px;
        text-align: center;
        color: var(--text-color-muted);
    }

    .all-notifications-link {
        display: block;
        text-align: center;
        padding: 10px;
        margin-top: 10px;
        color: var(--secondary-color);
        text-decoration: none;
        font-weight: bold;
        border-radius: 4px;
        background-color: var(--background-color);
    }

    .all-notifications-link:hover {
        background-color: var(--primary-color);
        color: var(--background-color);
    }

    .mobile-menu-trigger {
        display: none;
        background: none;
        border: none;
        color: var(--primary-color);
        font-size: 1.8rem;
        cursor: pointer;
        padding: 5px;
        z-index: 1005;
    }

    .mobile-menu-overlay {
        position: fixed;
        top: 0;
        left: -100%;
        width: 100%;
        max-width: 350px;
        height: 100vh;
        background-color: var(--surface-color); 
        box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
        z-index: 1002;
        padding: 20px;
        padding-top: calc(var(--navbar-height) + 20px);
        overflow-y: auto;
        transition: left 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        display: flex;
        flex-direction: column;
    }

    .mobile-menu-overlay.open {
        left: 0;
    }

    .mobile-menu-overlay .user-profile-summary-mobile {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .mobile-menu-overlay .user-profile-summary-mobile img,
    .mobile-menu-overlay .user-profile-summary-mobile .avatar-placeholder-nav {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        margin-right: 12px;
    }

    .mobile-menu-overlay .user-profile-summary-mobile div h4 {
        margin: 0 0 3px;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    .mobile-menu-overlay .user-profile-summary-mobile div a {
        font-size: 0.8em;
        color: var(--secondary-color);
        text-decoration: none;
    }

    .mobile-menu-overlay .mobile-menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
        flex-grow: 1;
    }

    .mobile-menu-overlay .mobile-menu-list li a {
        display: block;
        padding: 12px 0;
        color: var(--text-color);
        text-decoration: none;
        font-size: 1.1rem;
        border-bottom: 1px solid var(--border-color);
        transition: color 0.2s ease, padding-left 0.2s ease;
    }

    .mobile-menu-overlay .mobile-menu-list li:last-child a {
        border-bottom: none;
    }

    .mobile-menu-overlay .mobile-menu-list li a:hover {
        color: var(--secondary-color);
        padding-left: 8px;
    }

    .mobile-menu-overlay .mobile-menu-list li a i.fa-fw {
        margin-right: 10px;
        color: var(--text-color-muted);
    }

    .mobile-menu-overlay .mobile-menu-list li a:hover i.fa-fw {
        color: var(--secondary-color);
    }

    .mobile-menu-overlay .mobile-auth-buttons {
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }

    .mobile-menu-overlay .mobile-auth-buttons a {
        display: block;
        text-align: center;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
    }

    .mobile-menu-overlay .mobile-auth-buttons a.login-btn-mobile {
        background-color: var(--secondary-color);
        color: var(--background-color);
    }

    .mobile-menu-overlay .mobile-auth-buttons a.register-btn-mobile {
        background-color: var(--primary-color);
        color: var(--background-color);
    }

    .hamburger-icon {
        width: 28px;
        height: 22px;
        position: relative;
        transform: rotate(0deg);
        transition: .5s ease-in-out;
        cursor: pointer;
    }

    .hamburger-icon span {
        display: block;
        position: absolute;
        height: 3px;
        width: 100%;
        background: var(--primary-color);
        border-radius: 3px;
        opacity: 1;
        left: 0;
        transform: rotate(0deg);
        transition: .25s ease-in-out;
    }

    .hamburger-icon span:nth-child(1) { top: 0px; }
    .hamburger-icon span:nth-child(2), .hamburger-icon span:nth-child(3) { top: 9px; }
    .hamburger-icon span:nth-child(4) { top: 18px; }
    .mobile-menu-trigger.open .hamburger-icon span:nth-child(1) { top: 9px; width: 0%; left: 50%;}
    .mobile-menu-trigger.open .hamburger-icon span:nth-child(2) { transform: rotate(45deg); }
    .mobile-menu-trigger.open .hamburger-icon span:nth-child(3) { transform: rotate(-45deg); }
    .mobile-menu-trigger.open .hamburger-icon span:nth-child(4) { top: 9px; width: 0%; left: 50%; }

    @media (max-width: 991.98px) { 
        .dynamic-navbar-brand .site-title { display: none; } 
        .navbar-center-links { display: none; }
        .dynamic-navbar-actions .desktop-only { display: none; }
        .mobile-menu-trigger { display: block; }
        .dynamic-navbar { justify-content: space-between; } 
    }
    @media (min-width: 992px) {
        .mobile-menu-trigger { display: none !important; } 
        .mobile-menu-overlay { display: none !important; } 
    }

</style>

<nav class="dynamic-navbar" id="dynamicNavbar">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>/index.php" class="dynamic-navbar-brand">
        <img src="<?php echo htmlspecialchars($logo_path_navbar); ?>" alt="Site Logosu"> 
        <span class="site-title">ILGARION TURANIS</span>
    </a>
    
    <div class="navbar-center-links">
        <ul>
            <?php 
            // Ana menü öğelerini göster
            foreach ($nav_links_config_navbar as $nav_item): 
                if (can_show_nav_item($nav_item, $is_logged_in, $pdo ?? null)):
            ?>
                <li>
                    <a href="<?php echo htmlspecialchars($baseUrl . '/' . $nav_item['url']); ?>" 
                       class="<?php echo ($current_page_navbar === $nav_item['url']) ? 'active-nav-link' : ''; ?>">
                        <i class="fas <?php echo htmlspecialchars($nav_item['icon']); ?> fa-fw"></i> 
                        <?php echo htmlspecialchars($nav_item['title']); ?>
                    </a>
                </li>
            <?php 
                endif;
            endforeach; 
            ?>
            
            <?php if (!empty($user_nav_items)): ?>
                <!-- Kullanıcı içerik oluşturma menüsü -->
                <li class="navbar-dropdown">
                    <a href="#" class="nav-item-user-content">
                        <i class="fas fa-plus fa-fw"></i> Oluştur
                        <i class="fas fa-chevron-down" style="margin-left: 5px; font-size: 0.7em;"></i>
                    </a>
                    <div class="navbar-dropdown-content">
                        <?php foreach ($user_nav_items as $user_item): ?>
                            <?php if (isset($pdo) && has_permission($pdo, $user_item['permission'])): ?>
                                <a href="<?php echo htmlspecialchars($baseUrl . '/' . $user_item['url']); ?>">
                                    <i class="fas <?php echo htmlspecialchars($user_item['icon']); ?>"></i>
                                    <?php echo htmlspecialchars($user_item['title']); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endif; ?>
            
            <?php if (!empty($admin_nav_items)): ?>
                <!-- Admin menüsü -->
                <li class="navbar-dropdown">
                    <a href="#" class="nav-item-admin">
                        <i class="fas fa-shield-alt fa-fw"></i> Admin
                        <i class="fas fa-chevron-down" style="margin-left: 5px; font-size: 0.7em;"></i>
                    </a>
                    <div class="navbar-dropdown-content">
                        <?php foreach ($admin_nav_items as $admin_item): ?>
                            <?php if (isset($pdo) && has_permission($pdo, $admin_item['permission'])): ?>
                                <a href="<?php echo htmlspecialchars($baseUrl . '/' . $admin_item['url']); ?>">
                                    <i class="fas <?php echo htmlspecialchars($admin_item['icon']); ?>"></i>
                                    <?php echo htmlspecialchars($admin_item['title']); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="dynamic-navbar-actions">
        <?php if ($is_logged_in): ?>
            <div class="nav-action-item desktop-only">
                <button type="button" class="nav-icon-btn" id="notificationsTriggerBtn" title="Bildirimler">
                    <i style="color: var(--gold)" class="fa-regular fa-bell"></i>
                    <?php if ($unread_notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notification_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-panel dropdown-panel--notifications" id="notificationsDropdown">
                    <div class="dropdown-panel-header">
                        <span>Bildirimler</span>
                        <button class="close-dropdown-btn" data-target="notificationsDropdown">&times;</button>
                    </div>
                    <div class="dropdown-panel-content">
                         <?php if (empty($notifications_for_dropdown)): ?>
                            <p class="no-notifications">Okunmamış bildiriminiz bulunmamaktadır.</p>
                        <?php else: ?>
                            <?php foreach ($notifications_for_dropdown as $notification): ?>
                                <?php
                                $actor_username_display = '';
                                if (!empty($notification['actor_username'])) {
                                    $actor_username_display = htmlspecialchars($notification['actor_username']) . ' ';
                                }
                                $message_text = htmlspecialchars($notification['message'] ?? 'bir bildirim gönderdi.');
                                $final_display_message = $actor_username_display . $message_text;
                                ?>
                                <a href="<?php echo htmlspecialchars($notification['link'] ?? '#'); ?>&notif_id=<?php echo $notification['id']; ?>"
                                    class="notification-item <?php echo empty($notification['is_read']) ? 'unread' : ''; ?>">
                                    <i class="fas <?php echo htmlspecialchars($notification['icon'] ?? 'fa-info-circle'); ?> notification-icon"></i>
                                    <div>
                                        <p style="margin:0 0 3px;"><?php echo $final_display_message; ?></p>
                                        <small style="color:var(--text-color-muted);"><?php echo htmlspecialchars($notification['time_ago'] ?? date('d M Y, H:i', strtotime($notification['created_at'] ?? 'now'))); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            <a href="<?php echo htmlspecialchars($baseUrl); ?>/notifications.php" class="all-notifications-link"><i class="fas fa-envelope-open-text"></i> Tüm Bildirimleri Gör</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="nav-action-item desktop-only">
                <button type="button" class="nav-icon-btn" id="userMenuTriggerBtn" title="Kullanıcı Menüsü">
                    <?php if ($user_avatar_path): ?>
                        <img src="<?php echo htmlspecialchars($user_avatar_path); ?>" alt="Avatar" class="nav-user-avatar">
                    <?php else: ?>
                        <span class="nav-user-avatar-placeholder"><?php echo strtoupper(substr($username, 0, 1)); ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-panel dropdown-panel--user" id="userMenuDropdown">
                    <div class="dropdown-panel-header">
                        <span>Menü</span>
                        <button class="close-dropdown-btn" data-target="userMenuDropdown">&times;</button>
                    </div>
                    <div class="user-dropdown-profile">
                        <?php if ($user_avatar_path): ?>
                            <img src="<?php echo htmlspecialchars($user_avatar_path); ?>" alt="Avatar">
                        <?php else: ?>
                            <span class="avatar-placeholder-dropdown"><?php echo strtoupper(substr($username, 0, 1)); ?></span>
                        <?php endif; ?>
                        <div>
                            <h4><?php echo $username; ?></h4>
                            <a href="<?php echo htmlspecialchars($baseUrl); ?>/profile.php">Profilini Görüntüle</a>
                        </div>
                    </div>

                    <div class="dropdown-menu-section">
                        <h5>Profil & Ayarlar</h5>
                        <ul class="dropdown-menu-list">
                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/edit_profile.php"><i class="fas fa-user-edit fa-fw"></i> Profili Düzenle</a></li>
                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/edit_hangar.php"><i class="fas fa-warehouse fa-fw"></i> Hangarımı Düzenle</a></li>
                        </ul>
                    </div>
                    <div class="dropdown-menu-section">
                        <h5>İçeriklerim</h5>
                        <ul class="dropdown-menu-list">
                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user_gallery.php?user_id=<?php echo $user_id_for_links; ?>"><i class="fas fa-images fa-fw"></i> Galerim</a></li>
                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/view_hangar.php?user_id=<?php echo $user_id_for_links; ?>"><i class="fas fa-rocket fa-fw"></i> Hangarım</a></li>
                        </ul>
                    </div>
                    <?php if ($is_admin): ?>
                        <div class="dropdown-menu-section">
                            <h5>Yönetim</h5>
                            <ul class="dropdown-menu-list">
                                <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/index.php"><i class="fas fa-shield-alt fa-fw"></i> Admin Paneli</a></li>
                                <?php if (isset($pdo) && has_permission($pdo, 'admin.roles.view')): ?>
                                <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/admin/manage_roles.php"><i class="fas fa-user-tag fa-fw"></i> Rol Yönetimi</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <hr style="border-color: var(--gold); margin: 15px 0;">
                    <ul class="dropdown-menu-list">
                        <li class="logout-item"><a href="<?php echo htmlspecialchars($baseUrl); ?>/logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Çıkış Yap</a></li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="nav-action-item desktop-only">
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/login.php" class="nav-text-btn login-btn"><span>Giriş Yap</span></a>
            </div>
            <div class="nav-action-item desktop-only">
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/register.php" class="nav-text-btn"><span>Kayıt Ol</span></a>
            </div>
        <?php endif; ?>

        <button type="button" class="mobile-menu-trigger" id="mobileMenuTriggerBtn" aria-label="Menüyü Aç/Kapat" aria-expanded="false">
            <div class="hamburger-icon">
                <span></span><span></span><span></span><span></span>
            </div>
        </button>
    </div>
</nav>

<!-- Mobile Menu Overlay - Yetki tabanlı -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay">
    <?php if ($is_logged_in): ?>
        <div class="user-profile-summary-mobile">
            <?php if ($user_avatar_path): ?>
                <img src="<?php echo htmlspecialchars($user_avatar_path); ?>" alt="Avatar">
            <?php else: ?>
                <span class="avatar-placeholder-nav"><?php echo strtoupper(substr($username, 0, 1)); ?></span>
            <?php endif; ?>
            <div>
                <h4><?php echo $username; ?></h4>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/profile.php">Profili Görüntüle</a>
            </div>
        </div>
    <?php endif; ?>

    <ul class="mobile-menu-list">
        <!-- Ana menü öğeleri -->
        <?php foreach ($nav_links_config_navbar as $nav_item): ?>
            <?php if (can_show_nav_item($nav_item, $is_logged_in, $pdo ?? null)): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($baseUrl . '/' . $nav_item['url']); ?>">
                        <i class="fas <?php echo htmlspecialchars($nav_item['icon']); ?> fa-fw"></i> 
                        <?php echo htmlspecialchars($nav_item['title']); ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if ($is_logged_in): ?>
            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/notifications.php"><i class="fas fa-bell fa-fw"></i> Bildirimler
                    <?php if ($unread_notification_count > 0)
                        echo "<span class='notification-badge' style='position:relative; top:-1px; right:-5px; font-size:0.6em;'>$unread_notification_count</span>"; ?></a>
            </li>
            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/edit_profile.php"><i class="fas fa-user-edit fa-fw"></i> Profili Düzenle</a></li>
            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user_gallery.php?user_id=<?php echo $user_id_for_links; ?>"><i class="fas fa-images fa-fw"></i> Galerim</a></li>
            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/view_hangar.php?user_id=<?php echo $user_id_for_links; ?>"><i class="fas fa-rocket fa-fw"></i> Hangarım</a></li>
            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/edit_hangar.php"><i class="fas fa-warehouse fa-fw"></i> Hangarı Yönet</a></li>
            
            <!-- Kullanıcı içerik oluşturma linkleri -->
            <?php foreach ($user_nav_items as $user_item): ?>
                <?php if (isset($pdo) && has_permission($pdo, $user_item['permission'])): ?>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/' . $user_item['url']); ?>">
                        <i class="fas <?php echo htmlspecialchars($user_item['icon']); ?> fa-fw"></i> 
                        <?php echo htmlspecialchars($user_item['title']); ?>
                    </a></li>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Admin linkleri -->
            <?php foreach ($admin_nav_items as $admin_item): ?>
                <?php if (isset($pdo) && has_permission($pdo, $admin_item['permission'])): ?>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/' . $admin_item['url']); ?>">
                        <i class="fas <?php echo htmlspecialchars($admin_item['icon']); ?> fa-fw"></i> 
                        <?php echo htmlspecialchars($admin_item['title']); ?>
                    </a></li>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <li style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:10px;">
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/logout.php" style="color:var(--accent-color);">
                    <i class="fas fa-sign-out-alt fa-fw"></i> Çıkış Yap
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <?php if (!$is_logged_in): ?>
        <div class="mobile-auth-buttons">
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/login.php" class="login-btn-mobile">Giriş Yap</a>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/register.php" class="register-btn-mobile">Kayıt Ol</a>
        </div>
    <?php endif; ?>
</div>

<script>
    // Navbar JavaScript kodları
    document.addEventListener('DOMContentLoaded', function () {
        const navbar = document.getElementById('dynamicNavbar');
        const notificationsTriggerBtn = document.getElementById('notificationsTriggerBtn');
        const notificationsDropdown = document.getElementById('notificationsDropdown');
        const userMenuTriggerBtn = document.getElementById('userMenuTriggerBtn');
        const userMenuDropdown = document.getElementById('userMenuDropdown');
        const mobileMenuTriggerBtn = document.getElementById('mobileMenuTriggerBtn');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

        let openDropdown = null;

        // Scroll efekti
        if (navbar) { 
            window.addEventListener('scroll', function () {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
        }

        // Dropdown toggle fonksiyonu
        function toggleDropdown(triggerBtn, dropdownElement) {
            if (!dropdownElement) return; 
            if (openDropdown && openDropdown !== dropdownElement) {
                openDropdown.classList.remove('open');
            }
            dropdownElement.classList.toggle('open');
            openDropdown = dropdownElement.classList.contains('open') ? dropdownElement : null;
        }

        // Notification dropdown
        if (notificationsTriggerBtn && notificationsDropdown) {
            notificationsTriggerBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                toggleDropdown(this, notificationsDropdown);
            });
        }

        // User menu dropdown
        if (userMenuTriggerBtn && userMenuDropdown) {
            userMenuTriggerBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                toggleDropdown(this, userMenuDropdown);
            });
        }

        // Close dropdown buttons
        document.querySelectorAll('.close-dropdown-btn').forEach(button => {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                const targetDropdownId = this.dataset.target;
                const targetDropdown = document.getElementById(targetDropdownId);
                if (targetDropdown) {
                    targetDropdown.classList.remove('open');
                    if (openDropdown === targetDropdown) {
                        openDropdown = null;
                    }
                }
            });
        });

        // Outside click to close dropdowns
        document.addEventListener('click', function (event) {
            if (openDropdown) {
                let clickedInsideTrigger = false;
                if (notificationsTriggerBtn && notificationsTriggerBtn.contains(event.target)) clickedInsideTrigger = true;
                if (userMenuTriggerBtn && userMenuTriggerBtn.contains(event.target)) clickedInsideTrigger = true;
                
                const isClickInsideDropdown = openDropdown.contains(event.target);

                if (!clickedInsideTrigger && !isClickInsideDropdown) {
                    openDropdown.classList.remove('open');
                    openDropdown = null;
                }
            }
        });

        // ESC key to close dropdowns
        document.addEventListener('keydown', function (event) {
            if (event.key === "Escape" && openDropdown) {
                openDropdown.classList.remove('open');
                openDropdown = null;
            }
        });

        // Mobile menu
        if (mobileMenuTriggerBtn && mobileMenuOverlay) {
            mobileMenuTriggerBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                mobileMenuOverlay.classList.toggle('open');
                this.classList.toggle('open'); 
                this.setAttribute('aria-expanded', mobileMenuOverlay.classList.contains('open'));
                if (mobileMenuOverlay.classList.contains('open') && openDropdown) {
                    openDropdown.classList.remove('open');
                    openDropdown = null;
                }
            });

            // Close mobile menu when clicking on links
            mobileMenuOverlay.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function () {
                    mobileMenuOverlay.classList.remove('open');
                    mobileMenuTriggerBtn.classList.remove('open');
                    mobileMenuTriggerBtn.setAttribute('aria-expanded', 'false');
                });
            });
        }

        // Permission-based navigation highlighting
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.navbar-center-links a');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href').includes(currentPath.split('/').pop())) {
                link.classList.add('active-nav-link');
            }
        });

        // Dropdown hover effects for better UX
        const dropdownItems = document.querySelectorAll('.navbar-dropdown');
        dropdownItems.forEach(item => {
            let hoverTimeout;
            
            item.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                const dropdown = this.querySelector('.navbar-dropdown-content');
                if (dropdown) {
                    dropdown.style.opacity = '1';
                    dropdown.style.transform = 'translateY(0)';
                    dropdown.style.pointerEvents = 'auto';
                }
            });
            
            item.addEventListener('mouseleave', function() {
                const dropdown = this.querySelector('.navbar-dropdown-content');
                hoverTimeout = setTimeout(() => {
                    if (dropdown) {
                        dropdown.style.opacity = '0';
                        dropdown.style.transform = 'translateY(10px)';
                        dropdown.style.pointerEvents = 'none';
                    }
                }, 150);
            });
        });
    });

    // Permission debug helper (development only)
    <?php if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE): ?>
    function debugUserPermissions() {
        console.log('User Logged In:', <?php echo $is_logged_in ? 'true' : 'false'; ?>);
        console.log('User Admin:', <?php echo $is_admin ? 'true' : 'false'; ?>);
        <?php if ($is_logged_in && isset($pdo)): ?>
        console.log('Available Nav Items:', <?php echo json_encode(array_column($nav_links_config_navbar, 'title')); ?>);
        console.log('User Nav Items:', <?php echo json_encode(array_column($user_nav_items, 'title')); ?>);
        console.log('Admin Nav Items:', <?php echo json_encode(array_column($admin_nav_items, 'title')); ?>);
        <?php endif; ?>
    }
    
    // Call on page load for debugging
    debugUserPermissions();
    <?php endif; ?>
</script>