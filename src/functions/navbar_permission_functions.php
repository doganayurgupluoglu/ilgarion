<?php
// src/functions/navbar_permission_functions.php

/**
 * Navbar için yetki kontrol fonksiyonları
 */

if (!function_exists('get_navbar_config_with_permissions')) {
    /**
     * Kullanıcının yetkilerine göre navbar yapılandırmasını döndürür
     * @param PDO $pdo Veritabanı bağlantısı
     * @param bool $is_logged_in Kullanıcı giriş yapmış mı
     * @return array Navbar yapılandırması
     */
    function get_navbar_config_with_permissions(PDO $pdo, bool $is_logged_in): array {
        $config = [
            'main_nav' => [],
            'user_nav' => [],
            'admin_nav' => []
        ];

        // Ana navigasyon öğeleri
        $main_nav_items = [
            [
                'title' => 'Ana Sayfa',
                'url' => 'index.php',
                'icon' => 'fa-home',
                'permission' => null,
                'show_to_guests' => true,
                'priority' => 1
            ],
            [
                'title' => 'Etkinlikler',
                'url' => 'events.php',
                'icon' => 'fa-calendar-alt',
                'permission' => 'event.view_public',
                'show_to_guests' => true,
                'priority' => 2
            ],
            [
                'title' => 'Galeri',
                'url' => 'gallery.php',
                'icon' => 'fa-images',
                'permission' => 'gallery.view_public',
                'show_to_guests' => true,
                'priority' => 3
            ],
            [
                'title' => 'Tartışmalar',
                'url' => 'discussions.php',
                'icon' => 'fa-comments',
                'permission' => 'discussion.view_approved',
                'show_to_guests' => false,
                'priority' => 4
            ],
            [
                'title' => 'Rehberler',
                'url' => 'guides.php',
                'icon' => 'fa-book-open',
                'permission' => 'guide.view_public',
                'show_to_guests' => true,
                'priority' => 5
            ],
            [
                'title' => 'Teçhizat Setleri',
                'url' => 'loadouts.php',
                'icon' => 'fa-user-shield',
                'permission' => 'loadout.view_published',
                'show_to_guests' => false,
                'priority' => 6
            ],
            [
                'title' => 'Üyeler',
                'url' => 'members.php',
                'icon' => 'fa-users',
                'permission' => 'discussion.view_approved',
                'show_to_guests' => false,
                'priority' => 7
            ]
        ];

        // Kullanıcı içerik oluşturma öğeleri
        $user_nav_items = [
            [
                'title' => 'Etkinlik Oluştur',
                'url' => 'create_event.php',
                'icon' => 'fa-calendar-plus',
                'permission' => 'event.create',
                'category' => 'create'
            ],
            [
                'title' => 'Rehber Yaz',
                'url' => 'create_guide.php',
                'icon' => 'fa-pen',
                'permission' => 'guide.create',
                'category' => 'create'
            ],
            [
                'title' => 'Fotoğraf Yükle',
                'url' => 'upload_photo.php',
                'icon' => 'fa-camera',
                'permission' => 'gallery.upload',
                'category' => 'create'
            ],
            [
                'title' => 'Tartışma Başlat',
                'url' => 'create_topic.php',
                'icon' => 'fa-comment-alt',
                'permission' => 'discussion.topic.create',
                'category' => 'create'
            ],
            [
                'title' => 'Teçhizat Seti Oluştur',
                'url' => 'create_loadout.php',
                'icon' => 'fa-plus-square',
                'permission' => 'loadout.manage_sets',
                'category' => 'create'
            ]
        ];

        // Admin navigasyon öğeleri
        $admin_nav_items = [
            [
                'title' => 'Admin Paneli',
                'url' => 'admin/index.php',
                'icon' => 'fa-shield-alt',
                'permission' => 'admin.panel.access',
                'category' => 'main'
            ],
            [
                'title' => 'Kullanıcı Yönetimi',
                'url' => 'admin/users.php',
                'icon' => 'fa-user-cog',
                'permission' => 'admin.users.view',
                'category' => 'users'
            ],
            [
                'title' => 'Rol Yönetimi',
                'url' => 'admin/manage_roles.php',
                'icon' => 'fa-user-tag',
                'permission' => 'admin.roles.view',
                'category' => 'security'
            ],
            [
                'title' => 'Audit Log',
                'url' => 'admin/audit_log.php',
                'icon' => 'fa-clipboard-list',
                'permission' => 'admin.audit_log.view',
                'category' => 'security'
            ],
            [
                'title' => 'Süper Admin Yönetimi',
                'url' => 'admin/manage_super_admins.php',
                'icon' => 'fa-crown',
                'permission' => 'admin.super_admin.view',
                'category' => 'security'
            ],
            [
                'title' => 'Etkinlik Yönetimi',
                'url' => 'admin/events.php',
                'icon' => 'fa-calendar-check',
                'permission' => 'event.edit_all',
                'category' => 'content'
            ],
            [
                'title' => 'Galeri Yönetimi',
                'url' => 'admin/gallery.php',
                'icon' => 'fa-photo-video',
                'permission' => 'gallery.manage_all',
                'category' => 'content'
            ]
        ];

        // Ana navigasyonu filtrele
        foreach ($main_nav_items as $item) {
            if (can_show_nav_item_detailed($item, $is_logged_in, $pdo)) {
                $config['main_nav'][] = $item;
            }
        }

        // Kullanıcı navigasyonunu filtrele
        if ($is_logged_in) {
            foreach ($user_nav_items as $item) {
                if (!$item['permission'] || has_permission($pdo, $item['permission'])) {
                    $config['user_nav'][] = $item;
                }
            }

            // Admin navigasyonunu filtrele
            foreach ($admin_nav_items as $item) {
                if (has_permission($pdo, $item['permission'])) {
                    $config['admin_nav'][] = $item;
                }
            }
        }

        return $config;
    }
}

if (!function_exists('can_show_nav_item_detailed')) {
    /**
     * Gelişmiş navigasyon öğesi görünürlük kontrolü
     * @param array $nav_item Navigasyon öğesi
     * @param bool $is_logged_in Kullanıcı giriş yapmış mı
     * @param PDO $pdo Veritabanı bağlantısı
     * @return bool Gösterilecek mi
     */
    function can_show_nav_item_detailed(array $nav_item, bool $is_logged_in, PDO $pdo): bool {
        // Misafir kullanıcıya gösterilmeyecekse ve kullanıcı giriş yapmamışsa
        if (!$nav_item['show_to_guests'] && !$is_logged_in) {
            return false;
        }

        // Yetki belirtilmemişse (herkese açık)
        if (!$nav_item['permission']) {
            return true;
        }

        // Giriş yapmamışsa ve yetki gerekliyse
        if (!$is_logged_in) {
            return false;
        }

        // Yetki kontrolü
        return has_permission($pdo, $nav_item['permission']);
    }
}

if (!function_exists('get_user_notification_count')) {
    /**
     * Kullanıcının okunmamış bildirim sayısını döndürür
     * @param PDO $pdo Veritabanı bağlantısı
     * @param int $user_id Kullanıcı ID
     * @return int Okunmamış bildirim sayısı
     */
    function get_user_notification_count(PDO $pdo, int $user_id): int {
        try {
            $query = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
            $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Notification count error: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_user_notifications_for_dropdown')) {
    /**
     * Dropdown için kullanıcının son bildirimleri
     * @param PDO $pdo Veritabanı bağlantısı
     * @param int $user_id Kullanıcı ID
     * @param int $limit Maksimum bildirim sayısı
     * @return array Bildirimler
     */
    function get_user_notifications_for_dropdown(PDO $pdo, int $user_id, int $limit = 5): array {
        try {
            $query = "
                SELECT n.*, 
                       u.username as actor_username,
                       CASE 
                           WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN CONCAT(TIMESTAMPDIFF(MINUTE, n.created_at, NOW()), ' dakika önce')
                           WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN CONCAT(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), ' saat önce')
                           WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN CONCAT(TIMESTAMPDIFF(DAY, n.created_at, NOW()), ' gün önce')
                           ELSE DATE_FORMAT(n.created_at, '%d.%m.%Y %H:%i')
                       END as time_ago,
                       CASE 
                           WHEN n.event_id IS NOT NULL THEN 'fa-calendar'
                           WHEN n.message LIKE '%tartışma%' THEN 'fa-comments'
                           WHEN n.message LIKE '%galeri%' THEN 'fa-images'
                           WHEN n.message LIKE '%rehber%' THEN 'fa-book'
                           ELSE 'fa-info-circle'
                       END as icon
                FROM notifications n
                LEFT JOIN users u ON n.actor_user_id = u.id
                WHERE n.user_id = :user_id AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT :limit
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Notifications dropdown error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('get_navbar_breadcrumb')) {
    /**
     * Mevcut sayfa için breadcrumb oluşturur
     * @param string $current_page Mevcut sayfa
     * @param array $nav_config Navbar yapılandırması
     * @return array Breadcrumb dizisi
     */
    function get_navbar_breadcrumb(string $current_page, array $nav_config): array {
        $breadcrumb = [
            ['title' => 'Ana Sayfa', 'url' => 'index.php']
        ];

        // Ana navigasyonda ara
        foreach ($nav_config['main_nav'] as $item) {
            if ($item['url'] === $current_page) {
                $breadcrumb[] = ['title' => $item['title'], 'url' => $item['url']];
                return $breadcrumb;
            }
        }

        // Admin navigasyonunda ara
        foreach ($nav_config['admin_nav'] as $item) {
            if ($item['url'] === $current_page) {
                $breadcrumb[] = ['title' => 'Admin', 'url' => 'admin/index.php'];
                $breadcrumb[] = ['title' => $item['title'], 'url' => $item['url']];
                return $breadcrumb;
            }
        }

        return $breadcrumb;
    }
}

if (!function_exists('render_nav_item_with_permissions')) {
    /**
     * Yetki durumuna göre navigasyon öğesini render eder
     * @param array $item Navigasyon öğesi
     * @param bool $is_active Aktif mi
     * @param string $base_url Base URL
     * @return string HTML çıktısı
     */
    function render_nav_item_with_permissions(array $item, bool $is_active, string $base_url): string {
        $active_class = $is_active ? 'active-nav-link' : '';
        $special_class = '';
        
        // Özel kategoriler için ek sınıflar
        if (isset($item['category'])) {
            switch ($item['category']) {
                case 'security':
                    $special_class = 'nav-item-admin';
                    break;
                case 'create':
                    $special_class = 'nav-item-user-content';
                    break;
                case 'premium':
                    $special_class = 'nav-item-premium';
                    break;
            }
        }
        
        $url = htmlspecialchars($base_url . '/' . $item['url']);
        $title = htmlspecialchars($item['title']);
        $icon = htmlspecialchars($item['icon']);
        
        return sprintf(
            '<a href="%s" class="%s %s"><i class="fas %s fa-fw"></i> %s</a>',
            $url,
            $active_class,
            $special_class,
            $icon,
            $title
        );
    }
}

if (!function_exists('get_user_role_display_info')) {
    /**
     * Kullanıcının rol bilgilerini görüntüleme için hazırlar
     * @param PDO $pdo Veritabanı bağlantısı
     * @param int $user_id Kullanıcı ID
     * @return array Rol görüntüleme bilgileri
     */
    function get_user_role_display_info(PDO $pdo, int $user_id): array {
        try {
            $user_roles = get_user_roles($pdo, $user_id);
            $is_super_admin_user = is_super_admin($pdo, $user_id);
            
            if ($is_super_admin_user) {
                return [
                    'primary_role' => 'Süper Admin',
                    'primary_role_color' => '#ffd700',
                    'all_roles' => array_column($user_roles, 'name'),
                    'role_count' => count($user_roles),
                    'highest_priority' => 0,
                    'is_super_admin' => true
                ];
            }
            
            if (empty($user_roles)) {
                return [
                    'primary_role' => 'Üye',
                    'primary_role_color' => '#808080',
                    'all_roles' => [],
                    'role_count' => 0,
                    'highest_priority' => 999,
                    'is_super_admin' => false
                ];
            }
            
            // En yüksek öncelikli rolü bul
            $primary_role = $user_roles[0];
            foreach ($user_roles as $role) {
                if ($role['priority'] < $primary_role['priority']) {
                    $primary_role = $role;
                }
            }
            
            return [
                'primary_role' => ucfirst(str_replace('_', ' ', $primary_role['name'])),
                'primary_role_color' => $primary_role['color'],
                'all_roles' => array_column($user_roles, 'name'),
                'role_count' => count($user_roles),
                'highest_priority' => $primary_role['priority'],
                'is_super_admin' => false
            ];
            
        } catch (Exception $e) {
            error_log("User role display info error: " . $e->getMessage());
            return [
                'primary_role' => 'Üye',
                'primary_role_color' => '#808080',
                'all_roles' => [],
                'role_count' => 0,
                'highest_priority' => 999,
                'is_super_admin' => false
            ];
        }
    }
}

if (!function_exists('log_navbar_access')) {
    /**
     * Navbar erişimlerini loglar (analytics için)
     * @param PDO $pdo Veritabanı bağlantısı
     * @param int|null $user_id Kullanıcı ID
     * @param array $accessed_items Erişilen menü öğeleri
     */
    function log_navbar_access(PDO $pdo, ?int $user_id, array $accessed_items): void {
        try {
            if (count($accessed_items) === 0) return;
            
            // Sadece önemli sayfalara erişimleri logla
            $important_pages = ['admin/', 'create_', 'manage_', 'upload_'];
            $important_accesses = [];
            
            foreach ($accessed_items as $item) {
                foreach ($important_pages as $important) {
                    if (strpos($item['url'], $important) !== false) {
                        $important_accesses[] = $item['title'];
                        break;
                    }
                }
            }
            
            if (!empty($important_accesses)) {
                audit_log($pdo, $user_id, 'navbar_access', 'navigation', null, null, [
                    'accessed_items' => $important_accesses,
                    'total_visible_items' => count($accessed_items),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'timestamp' => time()
                ]);
            }
        } catch (Exception $e) {
            error_log("Navbar access logging error: " . $e->getMessage());
        }
    }
}

if (!function_exists('get_quick_actions_for_user')) {
    /**
     * Kullanıcı için hızlı eylemler menüsü oluşturur
     * @param PDO $pdo Veritabanı bağlantısı
     * @param int $user_id Kullanıcı ID
     * @return array Hızlı eylemler
     */
    function get_quick_actions_for_user(PDO $pdo, int $user_id): array {
        $quick_actions = [];
        
        // İçerik oluşturma eylemleri
        $content_actions = [
            ['title' => 'Etkinlik Oluştur', 'url' => 'create_event.php', 'icon' => 'fa-calendar-plus', 'permission' => 'event.create'],
            ['title' => 'Tartışma Başlat', 'url' => 'create_topic.php', 'icon' => 'fa-comment-alt', 'permission' => 'discussion.topic.create'],
            ['title' => 'Fotoğraf Yükle', 'url' => 'upload_photo.php', 'icon' => 'fa-camera', 'permission' => 'gallery.upload'],
            ['title' => 'Rehber Yaz', 'url' => 'create_guide.php', 'icon' => 'fa-pen', 'permission' => 'guide.create']
        ];
        
        foreach ($content_actions as $action) {
            if (has_permission($pdo, $action['permission'])) {
                $quick_actions[] = $action;
            }
        }
        
        // Yönetim eylemleri (admin kullanıcılar için)
        if (has_permission($pdo, 'admin.panel.access')) {
            $admin_actions = [
                ['title' => 'Kullanıcı Onayla', 'url' => 'admin/users.php?filter=pending', 'icon' => 'fa-user-check', 'permission' => 'admin.users.edit_status'],
                ['title' => 'Rol Yönetimi', 'url' => 'admin/manage_roles.php', 'icon' => 'fa-user-tag', 'permission' => 'admin.roles.view'],
                ['title' => 'Audit Log', 'url' => 'admin/audit_log.php', 'icon' => 'fa-clipboard-list', 'permission' => 'admin.audit_log.view']
            ];
            
            foreach ($admin_actions as $action) {
                if (has_permission($pdo, $action['permission'])) {
                    $quick_actions[] = $action;
                }
            }
        }
        
        return array_slice($quick_actions, 0, 8); // Maksimum 8 hızlı eylem
    }
}

if (!function_exists('check_navbar_security')) {
    /**
     * Navbar güvenlik kontrolü yapar
     * @param array $nav_config Navbar yapılandırması
     * @param int|null $user_id Kullanıcı ID
     * @return array Güvenlik kontrol sonucu
     */
    function check_navbar_security(array $nav_config, ?int $user_id): array {
        $security_issues = [];
        $recommendations = [];
        
        // Giriş yapmayan kullanıcılar için kontroller
        if (!$user_id) {
            $public_item_count = 0;
            foreach ($nav_config['main_nav'] as $item) {
                if ($item['show_to_guests']) {
                    $public_item_count++;
                }
            }
            
            if ($public_item_count < 2) {
                $recommendations[] = "Misafir kullanıcılar için daha fazla genel içerik gösterilmeli";
            }
            
            return [
                'issues' => $security_issues,
                'recommendations' => $recommendations,
                'security_level' => 'guest'
            ];
        }
        
        // Admin kullanıcılar için kontroller
        $admin_item_count = count($nav_config['admin_nav']);
        if ($admin_item_count > 10) {
            $security_issues[] = "Çok fazla admin menü öğesi güvenlik riski oluşturabilir";
        }
        
        // Kullanıcı navigasyon öğeleri kontrolü
        $user_item_count = count($nav_config['user_nav']);
        if ($user_item_count > 8) {
            $recommendations[] = "Kullanıcı menüsü basitleştirilebilir";
        }
        
        $security_level = 'low';
        if (!empty($security_issues)) {
            $security_level = 'medium';
        }
        if (count($security_issues) > 2) {
            $security_level = 'high';
        }
        
        return [
            'issues' => $security_issues,
            'recommendations' => $recommendations,
            'security_level' => $security_level,
            'total_nav_items' => count($nav_config['main_nav']) + count($nav_config['user_nav']) + count($nav_config['admin_nav'])
        ];
    }
}

if (!function_exists('cache_navbar_permissions')) {
    /**
     * Navbar yetki bilgilerini cache'ler
     * @param int $user_id Kullanıcı ID
     * @param array $nav_config Navbar yapılandırması
     */
    function cache_navbar_permissions(int $user_id, array $nav_config): void {
        $cache_key = "navbar_permissions_$user_id";
        $cache_data = [
            'timestamp' => time(),
            'nav_config' => $nav_config,
            'user_id' => $user_id
        ];
        
        $_SESSION[$cache_key] = $cache_data;
    }
}

if (!function_exists('get_cached_navbar_permissions')) {
    /**
     * Cache'den navbar yetki bilgilerini getirir
     * @param int $user_id Kullanıcı ID
     * @return array|null Cache'deki veriler veya null
     */
    function get_cached_navbar_permissions(int $user_id): ?array {
        $cache_key = "navbar_permissions_$user_id";
        
        if (isset($_SESSION[$cache_key])) {
            $cache_data = $_SESSION[$cache_key];
            
            // Cache 5 dakikadan eskiyse geçersiz
            if (time() - $cache_data['timestamp'] < 300) {
                return $cache_data['nav_config'];
            } else {
                unset($_SESSION[$cache_key]);
            }
        }
        
        return null;
    }
}

if (!function_exists('clear_navbar_cache')) {
    /**
     * Navbar cache'ini temizler
     * @param int|null $user_id Belirli kullanıcı için temizle, null ise tümü
     */
    function clear_navbar_cache(?int $user_id = null): void {
        if ($user_id) {
            $cache_key = "navbar_permissions_$user_id";
            unset($_SESSION[$cache_key]);
        } else {
            // Tüm navbar cache'lerini temizle
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'navbar_permissions_') === 0) {
                    unset($_SESSION[$key]);
                }
            }
        }
    }
}
?>