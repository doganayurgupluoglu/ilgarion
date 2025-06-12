<?php
// /events/index.php - Etkinlikler ana sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Events layout system include
require_once 'includes/events_layout.php';

// Session kontrolü - Ziyaretçiler için isteğe bağlı
$current_user_id = null;
$is_logged_in = false;

if (isset($_SESSION['user_id'])) {
    check_user_session_validity();
    $current_user_id = $_SESSION['user_id'];
    $is_logged_in = true;
    $is_approved = is_user_approved();
} else {
    $is_approved = false;
}

// Filtreleme parametreleri
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Yetki kontrolleri
$can_create_event = $is_logged_in && $is_approved && has_permission($pdo, 'event.create');
$can_edit_all = $is_logged_in && has_permission($pdo, 'event.edit_all');
$can_delete_all = $is_logged_in && has_permission($pdo, 'event.delete_all');
$can_view_all = $is_logged_in && has_permission($pdo, 'event.view_all');

// Etkinlikleri ve sayfa sayısını tutacak değişkenler
$upcoming_events = [];
$past_events = [];
$total_count = 0;
$total_pages = 0;
$is_filtering = !empty($status) || !empty($search) || $filter !== 'all';

// MEVCUT VERITABANI YAPISINA GÖRE etkinlikleri listele
try {
    $select_fields = "e.id, e.event_title, e.event_description, e.event_thumbnail_path,
           e.event_location, e.event_date, e.visibility, e.status, 
           e.created_at, e.created_by_user_id,
           u.username as creator_username,
           u.ingame_name as creator_ingame_name,
           (SELECT r.color FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = u.id 
            ORDER BY r.priority ASC LIMIT 1) as creator_role_color,
           (SELECT COUNT(*) FROM event_participations ep 
            WHERE ep.event_id = e.id AND ep.participation_status = 'confirmed') as confirmed_participants,
           (SELECT COUNT(*) FROM event_participations ep 
            WHERE ep.event_id = e.id) as total_participants";

    if ($is_filtering) {
        // FİLTRELİ GÖRÜNÜM LOGİĞİ
        $where_parts = [];
        $bind_params = [];
        
        // Görünürlük
        if (!$is_logged_in) {
            $where_parts[] = "e.visibility = 'public'";
        } else if (!$can_view_all) {
            $where_parts[] = "(e.visibility = 'public' OR e.visibility = 'members_only')";
        }
        
        // Status filtresi
        if (!empty($status)) {
            $where_parts[] = "e.status = ?";
            $bind_params[] = $status;
        } else {
            $where_parts[] = "e.status != 'draft'";
        }
        
        // Arama filtresi
        if (!empty($search)) {
            $where_parts[] = "(e.event_title LIKE ? OR e.event_description LIKE ? OR e.event_location LIKE ?)";
            $search_term = '%' . $search . '%';
            array_push($bind_params, $search_term, $search_term, $search_term);
        }
        
        // "My events" filtresi
        if ($is_logged_in && $filter === 'my_events') {
            $where_parts[] = "e.created_by_user_id = ?";
            $bind_params[] = $current_user_id;
        }
        
        $where_clause = !empty($where_parts) ? "WHERE " . implode(" AND ", $where_parts) : "";
        
        // Count sorgusu
        $count_sql = "SELECT COUNT(DISTINCT e.id) FROM events e $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($bind_params);
        $total_count = $count_stmt->fetchColumn();

        // Sıralama
        $order_clause = "ORDER BY e.event_date DESC"; // Varsayılan
        // Sıralama mantığı burada kalabilir...

        // Ana veri sorgusu
        $sql = "SELECT $select_fields FROM events e LEFT JOIN users u ON e.created_by_user_id = u.id $where_clause $order_clause LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $all_params = array_merge($bind_params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC); // Filtrelendiğinde tüm sonuçlar "upcoming" olarak kabul edilir

    } else {
        // VARSAYILAN (FİLTRESİZ) GÖRÜNÜM LOGİĞİ

        // 1. Yaklaşan etkinlikleri al (sayfalamalı)
        $upcoming_where_parts = ["(e.event_date >= NOW() AND e.status = 'published')"];
        $upcoming_bind_params = [];

        if (!$is_logged_in) {
            $upcoming_where_parts[] = "e.visibility = 'public'";
        } elseif (!$can_view_all) {
            $upcoming_where_parts[] = "(e.visibility = 'public' OR e.visibility = 'members_only')";
        }

        $upcoming_where_clause = "WHERE " . implode(" AND ", $upcoming_where_parts);
        
        $upcoming_count_sql = "SELECT COUNT(DISTINCT e.id) FROM events e $upcoming_where_clause";
        $upcoming_count_stmt = $pdo->prepare($upcoming_count_sql);
        $upcoming_count_stmt->execute($upcoming_bind_params);
        $total_count = $upcoming_count_stmt->fetchColumn();

        $upcoming_sql = "SELECT $select_fields FROM events e LEFT JOIN users u ON e.created_by_user_id = u.id $upcoming_where_clause ORDER BY e.event_date ASC LIMIT ? OFFSET ?";
        $upcoming_stmt = $pdo->prepare($upcoming_sql);
        $upcoming_all_params = array_merge($upcoming_bind_params, [$per_page, $offset]);
        $upcoming_stmt->execute($upcoming_all_params);
        $upcoming_events = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Geçmiş etkinlikleri al (sayfalamasız)
        $past_where_parts = ["(e.event_date < NOW() OR e.status IN ('completed', 'cancelled'))", "e.status != 'draft'"];
        $past_bind_params = [];

        if (!$is_logged_in) {
            $past_where_parts[] = "e.visibility = 'public'";
        } elseif (!$can_view_all) {
            $past_where_parts[] = "(e.visibility = 'public' OR e.visibility = 'members_only')";
        }
        
        $past_where_clause = "WHERE " . implode(" AND ", $past_where_parts);

        $past_sql = "SELECT $select_fields FROM events e LEFT JOIN users u ON e.created_by_user_id = u.id $past_where_clause ORDER BY e.event_date DESC";
        $past_stmt = $pdo->prepare($past_sql);
        $past_stmt->execute($past_bind_params);
        $past_events = $past_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Sayfalama hesaplamaları (her iki durum için de $total_count kullanılır)
    $total_pages = ceil($total_count / $per_page);
    
} catch (PDOException $e) {
    error_log("Events index error: " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlikler yüklenirken bir hata oluştu.";
    $upcoming_events = [];
    $past_events = [];
    $total_count = 0;
    $total_pages = 0;
}

// Sayfa başlığı ve breadcrumb
$page_title = "Etkinlikler - Ilgarion Turanis";
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="css/events_sidebar.css">
<link rel="stylesheet" href="css/events_index.css">

<div class="events-container">
    <!-- Sayfa Başlığı ve Eylemler -->
    <div class="events-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fas fa-calendar-alt"></i>
                    Etkinlikler
                </h1>
                <p class="page-description">
                    Star Citizen evreni için düzenlenen etkinlikler ve operasyonlar
                </p>
            </div>
            
            <div class="header-actions">
                <?php if ($can_create_event): ?>
                    <a href="create.php" class="btn-action-primary">
                        <i class="fas fa-plus"></i>
                        Yeni Etkinlik Oluştur
                    </a>
                <?php endif; ?>
                
                <a href="roles/" class="btn-action-secondary">
                    <i class="fas fa-user-tag"></i>
                    Etkinlik Rolleri
                </a>
            </div>
        </div>
    </div>

    <!-- Filtreler ve Arama -->
    <div class="events-filters">
        <form method="GET" class="filters-form" id="filtersForm">
            <div class="filter-row">
                <!-- Arama -->
                <div class="filter-group">
                    <label for="search">Arama:</label>
                    <div class="search-input-group">
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Etkinlik adı, açıklama veya lokasyon...">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Status Filtresi -->
                <div class="filter-group">
                    <label for="status">Durum:</label>
                    <select id="status" name="status" onchange="document.getElementById('filtersForm').submit()">
                        <option value="">Tümü</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Yayınlandı</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                        <?php if ($can_view_all): ?>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Taslak</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Filtre -->
                <?php if ($is_logged_in): ?>
                <div class="filter-group">
                    <label for="filter">Filtre:</label>
                    <select id="filter" name="filter" onchange="document.getElementById('filtersForm').submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <option value="my_events" <?= $filter === 'my_events' ? 'selected' : '' ?>>Oluşturduklarım</option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Sıralama -->
                <div class="filter-group">
                    <label for="sort">Sıralama:</label>
                    <select id="sort" name="sort" onchange="document.getElementById('filtersForm').submit()">
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>En Yakın Tarih</option>
                        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>En Uzak Tarih</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>En Yeni Oluşturulan</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>En Eski Oluşturulan</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>İsme Göre</option>
                    </select>
                </div>
            </div>
            
            <!-- Aktif filtreler -->
            <?php if (!empty($search) || !empty($status) || $filter !== 'all'): ?>
                <div class="active-filters">
                    <span class="filters-label">Aktif Filtreler:</span>
                    <?php if (!empty($search)): ?>
                        <span class="filter-tag">
                            Arama: "<?= htmlspecialchars($search) ?>"
                            <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status)): ?>
                        <span class="filter-tag">
                            Durum: <?= ucfirst($status) ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter !== 'all'): ?>
                        <span class="filter-tag">
                            Filtre: <?= $filter === 'my_events' ? 'Oluşturduklarım' : ucfirst($filter) ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'all'])) ?>">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="?" class="btn-clear-filters">Tüm Filtreleri Temizle</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Etkinlikler Listesi -->
    <div class="events-content">
        <!-- Events Header -->
        <div class="events-list-header">
            <h2>
                <i class="fas fa-calendar"></i>
                <?php
                if (!empty($search)) {
                    echo "Arama Sonuçları";
                } elseif ($filter === 'my_events') {
                    echo "Oluşturduğum Etkinlikler";
                } elseif (!empty($status)) {
                    echo ucfirst($status) . " Etkinlikler";
                } else {
                    echo "Yaklaşan Etkinlikler";
                }
                ?>
            </h2>
            <div class="events-count">
                <?= $total_count ?> etkinlik bulundu
            </div>
        </div>

        <?php if (empty($upcoming_events)): ?>
            <div class="empty-events">
                <i class="fas fa-calendar-times"></i>
                <h3>
                    <?php if ($is_filtering): ?>
                        Etkinlik Bulunamadı
                    <?php else: ?>
                        Yaklaşan Etkinlik Bulunamadı
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if ($is_filtering): ?>
                        Arama kriterlerinize uygun etkinlik bulunamadı. Filtreleri değiştirmeyi deneyin.
                    <?php else: ?>
                        Görünüşe göre ufukta yeni bir etkinlik yok.
                    <?php endif; ?>
                </p>
                <?php if ($can_create_event): ?>
                    <a href="create.php" class="btn-action-primary">
                        <i class="fas fa-plus"></i>
                        Yeni Etkinlik Oluştur
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Events Grid -->
            <div class="events-grid">
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="event-card" data-event-id="<?= $event['id'] ?>">
                        <!-- Event Thumbnail -->
                        <div class="event-thumbnail">
                            <?php if (!empty($event['event_thumbnail_path'])): ?>
                                <img src="<?= htmlspecialchars($event['event_thumbnail_path']) ?>" 
                                     alt="<?= htmlspecialchars($event['event_title']) ?>">
                            <?php else: ?>
                                <div class="default-thumbnail">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Badge -->
                            <?php
                                $event_date_obj = new DateTime($event['event_date']);
                                $now = new DateTime();
                                
                                $status_text = '';
                                $status_class = $event['status'];

                                if ($status_class == 'cancelled') {
                                    $status_text = 'İptal';
                                } elseif ($status_class == 'completed') {
                                    $status_text = 'Tamamlandı';
                                } elseif ($event_date_obj < $now && $status_class == 'published') {
                                    $status_text = 'Geçmiş';
                                    $status_class = 'past';
                                } elseif ($status_class == 'published') {
                                    $status_text = 'Aktif';
                                } else {
                                    $status_text = ucfirst($status_class);
                                }
                            ?>
                            <span class="status-badge status-<?= htmlspecialchars($status_class) ?>">
                                <?= htmlspecialchars($status_text) ?>
                            </span>
                            
                            <!-- Visibility Badge -->
                            <?php if ($event['visibility'] !== 'public'): ?>
                                <span class="visibility-badge">
                                    <i class="fas fa-<?= $event['visibility'] === 'members_only' ? 'users' : 'lock' ?>"></i>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Event Content -->
                        <div class="event-content">
                            <h3 class="event-title">
                                <a href="detail.php?id=<?= $event['id'] ?>">
                                    <?= htmlspecialchars($event['event_title']) ?>
                                </a>
                            </h3>
                            
                            <p class="event-description">
                                <?= htmlspecialchars(mb_substr($event['event_description'], 0, 120)) ?>
                                <?= mb_strlen($event['event_description']) > 120 ? '...' : '' ?>
                            </p>

                            <!-- Event Meta -->
                            <div class="event-meta">
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('d.m.Y H:i', strtotime($event['event_date'])) ?></span>
                                </div>
                                
                                <?php if (!empty($event['event_location'])): ?>
                                    <div class="event-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($event['event_location']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-participants">
                                    <i class="fas fa-users"></i>
                                    <span><?= $event['confirmed_participants'] ?> katılımcı</span>
                                    <?php if ($event['total_participants'] > $event['confirmed_participants']): ?>
                                        <small>(<?= $event['total_participants'] - $event['confirmed_participants'] ?> beklemede)</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-creator">
                                    <i class="fas fa-user"></i>
                                    <span>Oluşturan: </span>
                                    <?php if ($is_logged_in): ?>
                                        <span class="user-link" 
                                              data-user-id="<?= $event['created_by_user_id'] ?>"
                                              style="color: <?= htmlspecialchars($event['creator_role_color'] ?? '#bd912a') ?>; cursor: pointer; font-weight: 500;">
                                            <?= htmlspecialchars($event['creator_username'] ?? 'Bilinmeyen') ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #bd912a; font-weight: 500;">
                                            <?= htmlspecialchars($event['creator_username'] ?? 'Bilinmeyen') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Event Actions -->
                            <div class="event-actions">
                                <a href="detail.php?id=<?= $event['id'] ?>" class="btn-event-primary">
                                    <i class="fas fa-eye"></i>
                                    Detayları Gör
                                </a>
                                
                                <?php if ($is_logged_in): ?>
                                    <?php
                                        $event_date_obj = new DateTime($event['event_date']);
                                        $now = new DateTime();
                                        $is_active_for_participation = $event_date_obj >= $now && $event['status'] === 'published';
                                    ?>
                                    <?php if ($is_active_for_participation): ?>
                                        <button type="button" class="btn-event-secondary participate-btn" 
                                                data-event-id="<?= $event['id'] ?>">
                                            <i class="fas fa-hand-paper"></i>
                                            Katıl
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['created_by_user_id'] == $current_user_id || $can_edit_all): ?>
                                        <a href="edit.php?id=<?= $event['id'] ?>" class="btn-event-secondary">
                                            <i class="fas fa-edit"></i>
                                            Düzenle
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Geçmiş Etkinlikler Akordiyonu -->
    <?php if (!$is_filtering && !empty($past_events)): ?>
    <div class="past-events-accordion">
        <div class="accordion" id="pastEventsAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingPast">
                    <button class="accordion-button" type="button" id="past-events-toggle">
                        <span>
                            <i class="fas fa-history"></i>
                            Geçmiş Etkinlikler (<?= count($past_events) ?>)
                        </span>
                        <i class="fas fa-chevron-down arrow-icon"></i>
                    </button>
                </h2>
                <div id="collapsePast" class="accordion-collapse">
                    <div class="accordion-body">
                        <div class="events-grid">
                            <?php foreach ($past_events as $event): ?>
                                <div class="event-card" data-event-id="<?= $event['id'] ?>">
                                    <!-- Event Thumbnail -->
                                    <div class="event-thumbnail">
                                        <?php if (!empty($event['event_thumbnail_path'])): ?>
                                            <img src="<?= htmlspecialchars($event['event_thumbnail_path']) ?>" 
                                                 alt="<?= htmlspecialchars($event['event_title']) ?>">
                                        <?php else: ?>
                                            <div class="default-thumbnail">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Status Badge -->
                                        <?php
                                            $event_date_obj = new DateTime($event['event_date']);
                                            $now = new DateTime();
                                            
                                            $status_text = '';
                                            $status_class = $event['status'];

                                            if ($status_class == 'cancelled') {
                                                $status_text = 'İptal';
                                            } elseif ($status_class == 'completed') {
                                                $status_text = 'Tamamlandı';
                                            } elseif ($event_date_obj < $now) {
                                                $status_text = 'Geçmiş';
                                                $status_class = 'past';
                                            } else {
                                                $status_text = ucfirst($status_class);
                                            }
                                        ?>
                                        <span class="status-badge status-<?= htmlspecialchars($status_class) ?>">
                                            <?= htmlspecialchars($status_text) ?>
                                        </span>
                                        
                                        <!-- Visibility Badge -->
                                        <?php if ($event['visibility'] !== 'public'): ?>
                                            <span class="visibility-badge">
                                                <i class="fas fa-<?= $event['visibility'] === 'members_only' ? 'users' : 'lock' ?>"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Event Content -->
                                    <div class="event-content">
                                        <h3 class="event-title">
                                            <a href="detail.php?id=<?= $event['id'] ?>">
                                                <?= htmlspecialchars($event['event_title']) ?>
                                            </a>
                                        </h3>
                                        
                                        <p class="event-description">
                                            <?= htmlspecialchars(mb_substr($event['event_description'], 0, 120)) ?>
                                            <?= mb_strlen($event['event_description']) > 120 ? '...' : '' ?>
                                        </p>

                                        <!-- Event Meta -->
                                        <div class="event-meta">
                                            <div class="event-date">
                                                <i class="fas fa-calendar"></i>
                                                <span><?= date('d.m.Y H:i', strtotime($event['event_date'])) ?></span>
                                            </div>
                                            
                                            <?php if (!empty($event['event_location'])): ?>
                                                <div class="event-location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <span><?= htmlspecialchars($event['event_location']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="event-participants">
                                                <i class="fas fa-users"></i>
                                                <span><?= $event['confirmed_participants'] ?> katılımcı</span>
                                                <?php if ($event['total_participants'] > $event['confirmed_participants']): ?>
                                                    <small>(<?= $event['total_participants'] - $event['confirmed_participants'] ?> beklemede)</small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="event-creator">
                                                <i class="fas fa-user"></i>
                                                <span>Oluşturan: </span>
                                                <?php if ($is_logged_in): ?>
                                                    <span class="user-link" 
                                                          data-user-id="<?= $event['created_by_user_id'] ?>"
                                                          style="color: <?= htmlspecialchars($event['creator_role_color'] ?? '#bd912a') ?>; cursor: pointer; font-weight: 500;">
                                                        <?= htmlspecialchars($event['creator_username'] ?? 'Bilinmeyen') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #bd912a; font-weight: 500;">
                                                        <?= htmlspecialchars($event['creator_username'] ?? 'Bilinmeyen') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Event Actions -->
                                        <div class="event-actions">
                                            <a href="detail.php?id=<?= $event['id'] ?>" class="btn-event-primary">
                                                <i class="fas fa-eye"></i>
                                                Detayları Gör
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
        <div class="events-pagination">
            <nav aria-label="Sayfa navigasyonu">
                <div class="pagination-controls">
                    <!-- İlk sayfa ve önceki -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="btn-page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Sayfa numaraları -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="btn-page <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Sonraki ve son sayfa -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="btn-page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    Sayfa <?= $page ?> / <?= $total_pages ?> 
                    (Toplam <?= number_format($total_count) ?> etkinlik)
                </div>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- User Popover Include -->
<?php if ($is_logged_in): ?>
    <?php include BASE_PATH . '/src/includes/user_popover.php'; ?>
    <script src="../forum/js/forum.js"></script>
<?php endif; ?>

<script>
// Katılım butonları için JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Katılım butonları
    document.querySelectorAll('.participate-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.dataset.eventId;
            // TODO: Katılım modalı veya AJAX işlemi
            alert('Etkinlik katılım sistemi henüz geliştiriliyor...');
        });
    });
    
    // Kart hover efektleri
    document.querySelectorAll('.event-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Arama formu enter tuşu
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('filtersForm').submit();
        }
    });

    // Custom Accordion Logic
    const pastEventsToggle = document.getElementById('past-events-toggle');
    if (pastEventsToggle) {
        const content = document.getElementById('collapsePast');
        
        pastEventsToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            
            if (content.style.maxHeight) {
                content.style.maxHeight = null;
            } else {
                content.style.maxHeight = content.scrollHeight + "px";
            }
        });
    }
});
</script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>