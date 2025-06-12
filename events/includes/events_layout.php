<?php
// events/includes/events_layout.php - Events sayfaları için layout wrapper

/**
 * Events layout wrapper
 * Bu dosya events altındaki tüm sayfalar için ortak layout sağlar
 * 
 * Kullanım:
 * 1. Sayfa başında bu dosyayı include edin
 * 2. events_layout_start() fonksiyonunu çağırın
 * 3. Sayfa içeriğinizi yazın
 * 4. events_layout_end() fonksiyonunu çağırın
 */

// Layout başlatma fonksiyonu
function events_layout_start($breadcrumb_items = [], $page_title = 'Etkinlikler') {
    ?>
    <link rel="stylesheet" href="../css/events_sidebar.css">
    
    <div class="events-layout-container">
        <!-- Breadcrumb Navigation -->
        <?php if (!empty($breadcrumb_items)): ?>
            <?= generate_events_breadcrumb($breadcrumb_items) ?>
        <?php endif; ?>

        <div class="events-layout-content">
            <!-- Sidebar -->
            <aside class="events-sidebar-container">
                <?php 
                global $pdo;
                include __DIR__ . '/events_sidebar.php'; 
                ?>
            </aside>

            <!-- Main Content Area -->
            <main class="events-main-content">
    <?php
}

// Layout bitirme fonksiyonu
function events_layout_end() {
    ?>
            </main>
        </div>
    </div>
    <?php
}

// Breadcrumb helper fonksiyonu
function generate_events_breadcrumb($items) {
    if (empty($items)) return '';
    
    $breadcrumb = '<nav class="events-breadcrumb-nav"><ol class="events-breadcrumb">';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        $breadcrumb .= '<li class="breadcrumb-item' . ($isLast ? ' active' : '') . '">';
        
        if ($isLast || empty($item['url'])) {
            $breadcrumb .= '<i class="' . ($item['icon'] ?? 'fas fa-folder') . '"></i> ' . htmlspecialchars($item['text']);
        } else {
            $breadcrumb .= '<a href="' . htmlspecialchars($item['url']) . '">';
            $breadcrumb .= '<i class="' . ($item['icon'] ?? 'fas fa-folder') . '"></i> ' . htmlspecialchars($item['text']);
            $breadcrumb .= '</a>';
        }
        
        $breadcrumb .= '</li>';
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

// CSS sınıfları için yardımcı fonksiyon
function get_sidebar_active_class($current_section, $target_section) {
    return $current_section === $target_section ? 'active' : '';
}

// Sayfa tespiti için yardımcı fonksiyon
function get_current_events_section() {
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    
    if ($current_dir === 'loadouts' || strpos($_SERVER['REQUEST_URI'], '/loadouts/') !== false) {
        return 'loadouts';
    } elseif ($current_dir === 'roles' || strpos($_SERVER['REQUEST_URI'], '/roles/') !== false) {
        return 'roles';
    } elseif ($current_dir === 'events' && $current_page === 'index') {
        return 'events';
    } else {
        return 'events';
    }
}

// Yetki kontrolü için yardımcı fonksiyon
function can_access_events_section($section) {
    switch ($section) {
        case 'events':
            return true; // Etkinlikleri herkes görebilir
        case 'loadouts':
            return true; // Teçhizat setlerini herkes görebilir
        case 'roles':
            return true; // Rolleri artık herkes görebilir
        default:
            return false;
    }
}
?>