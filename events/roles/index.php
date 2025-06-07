<?php
// /events/roles/index.php - Etkinlik rolleri ana sayfa

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Events layout system include
require_once '../includes/events_layout.php';

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

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Filtreleme parametreleri
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$category = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Yetki kontrolleri - Ziyaretçiler için güvenli
$can_create_role = $is_logged_in && $is_approved && has_permission($pdo, 'event_role.create');
$can_edit_all = $is_logged_in && has_permission($pdo, 'event_role.edit_all');
$can_delete_all = $is_logged_in && has_permission($pdo, 'event_role.delete_all');
$can_view_private = $is_logged_in && has_permission($pdo, 'event_role.view_private');

// BASIT VE GÜVENLİ SQL SORGULARI
try {
    // Temel WHERE koşulları - Sadece herkese açık ve aktif roller
    $where_parts = ["er.is_active = 1", "er.visibility = 'public'"];
    $bind_params = [];
    
    // Kullanıcı filtreleri - Sadece giriş yapmış kullanıcılar için
    if ($is_logged_in && $filter === 'my_roles') {
        $where_parts[] = "er.created_by_user_id = ?";
        $bind_params[] = $current_user_id;
    }
    
    // Arama filtresi
    if (!empty($search)) {
        $where_parts[] = "(er.role_name LIKE ? OR er.role_description LIKE ? OR u.username LIKE ?)";
        $search_term = '%' . $search . '%';
        $bind_params[] = $search_term;
        $bind_params[] = $search_term;
        $bind_params[] = $search_term;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_parts);
    
    // Sıralama
    $order_clause = "ORDER BY er.created_at DESC";
    switch ($sort) {
        case 'name':
            $order_clause = "ORDER BY er.role_name ASC";
            break;
        case 'oldest':
            $order_clause = "ORDER BY er.created_at ASC";
            break;
        case 'newest':
        default:
            $order_clause = "ORDER BY er.created_at DESC";
            break;
    }
    
    // Debug: Toplam aktif rol sayısı
    $debug_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_roles WHERE is_active = 1");
    $debug_stmt->execute();
    $total_roles_in_db = $debug_stmt->fetchColumn();
    
    // COUNT sorgusu
    $count_sql = "
        SELECT COUNT(DISTINCT er.id)
        FROM event_roles er
        LEFT JOIN users u ON er.created_by_user_id = u.id
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($bind_params);
    $total_count = $count_stmt->fetchColumn();
    
    // Ana veri sorgusu
    $sql = "
        SELECT er.id, er.role_name, er.role_description, er.icon_class, 
               er.visibility, er.created_at, er.created_by_user_id,
               u.username as creator_username,
               u.ingame_name as creator_ingame_name,
               u.id as creator_user_id,
               (SELECT r.color FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE ur.user_id = u.id 
                ORDER BY r.priority ASC LIMIT 1) as creator_role_color,
               (SELECT COUNT(*) FROM event_role_slots ers WHERE ers.event_role_id = er.id) as usage_count,
               (SELECT COUNT(*) FROM event_role_requirements err WHERE err.event_role_id = er.id) as requirements_count,
               ls.set_name as loadout_name
        FROM event_roles er
        LEFT JOIN users u ON er.created_by_user_id = u.id
        LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id
        $where_clause
        $order_clause
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // Parametreleri ekle
    $all_params = array_merge($bind_params, [$per_page, $offset]);
    $stmt->execute($all_params);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sayfalama hesaplamaları
    $total_pages = ceil($total_count / $per_page);
    
    // Debug bilgileri
    $debug_info = [
        'total_roles_in_db' => $total_roles_in_db,
        'filtered_count' => $total_count,
        'returned_roles' => count($roles),
        'where_clause' => $where_clause,
        'bind_params_count' => count($bind_params),
        'search_term' => $search,
        'filter' => $filter,
        'user_permissions' => [
            'is_logged_in' => $is_logged_in,
            'is_approved' => $is_approved,
            'create' => $can_create_role
        ]
    ];
    
} catch (PDOException $e) {
    error_log("Roles index error: " . $e->getMessage());
    $_SESSION['error_message'] = "Roller yüklenirken bir hata oluştu: " . $e->getMessage();
    $roles = [];
    $total_count = 0;
    $total_pages = 0;
    $debug_info = ['error' => $e->getMessage()];
}

// Sayfa başlığı ve breadcrumb
$page_title = "Etkinlik Rolleri - Ilgarion Turanis";
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Roller', 'url' => '/events/roles/', 'icon' => 'fas fa-user-tag']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="../css/events_sidebar.css">
<link rel="stylesheet" href="css/roles.css">

<div class="loadout-view-container">
    <!-- Debug Bilgileri (Geliştirme aşamasında) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info" style="background: var(--card-bg); border: 1px solid var(--border-1); color: var(--lighter-grey); padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">
            <h5>Debug Bilgileri:</h5>
            <pre style="color: var(--lighter-grey); white-space: pre-wrap;"><?= htmlspecialchars(json_encode($debug_info, JSON_PRETTY_PRINT)) ?></pre>
        </div>
    <?php endif; ?>

    <!-- Sayfa Başlığı ve Eylemler -->
    <div class="set-meta">
        <div class="set-header">
            <h1>
                <i class="fas fa-user-tag"></i>
                Etkinlik Rolleri
            </h1>
            <p class="page-description">
                Etkinliklerde kullanılmak üzere oluşturulmuş herkese açık roller.
            </p>
        </div>
        
        <div class="set-actions">
            <?php if ($can_create_role): ?>
                <a href="create.php" class="btn-action-primary">
                    <i class="fas fa-plus"></i>
                    Yeni Rol Oluştur
                </a>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <a href="?debug=1" class="btn-action-secondary">
                    <i class="fas fa-bug"></i>
                    Debug Bilgileri
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtreler ve Arama -->
    <div class="set-meta">
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
                               placeholder="Rol adı, açıklama veya oluşturan kişi...">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Filtre -->
                <div class="filter-group">
                    <label for="filter">Filtre:</label>
                    <select id="filter" name="filter" onchange="document.getElementById('filtersForm').submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <?php if ($is_logged_in): ?>
                            <option value="my_roles" <?= $filter === 'my_roles' ? 'selected' : '' ?>>Oluşturduklarım</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Sıralama -->
                <div class="filter-group">
                    <label for="sort">Sıralama:</label>
                    <select id="sort" name="sort" onchange="document.getElementById('filtersForm').submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>En Yeni</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>En Eski</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>İsme Göre</option>
                    </select>
                </div>
            </div>
            
            <!-- Aktif filtre bilgisi -->
            <?php if (!empty($search) || $filter !== 'all'): ?>
                <div class="active-filters" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-1);">
                    <span class="filters-label">Aktif Filtreler:</span>
                    <?php if (!empty($search)): ?>
                        <span class="filter-tag">
                            Arama: "<?= htmlspecialchars($search) ?>"
                            <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter !== 'all'): ?>
                        <span class="filter-tag">
                            Filtre: <?= ucfirst($filter) ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'all'])) ?>">×</a>
                        </span>
                    <?php endif; ?>
                    <a href="?" class="btn-action-secondary" style="margin-left: 10px;">Tüm Filtreleri Temizle</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Roller Listesi -->
    <div class="loadout-items-section">
        <!-- Items Header -->
        <div class="items-header">
            <h2>
                <i class="fas fa-users"></i>
                Herkese Açık Roller
            </h2>
            <div class="items-count">
                <?= $total_count ?> rol bulundu
            </div>
        </div>

        <?php if (empty($roles)): ?>
            <div class="empty-items">
                <i class="fas fa-user-tag"></i>
                <h3>Rol Bulunamadı</h3>
                <p>
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        Arama kriterlerinize uygun rol bulunamadı. Filtreleri değiştirmeyi deneyin.
                    <?php else: ?>
                        Henüz hiç herkese açık etkinlik rolü oluşturulmamış.
                    <?php endif; ?>
                </p>
                <?php if ($can_create_role): ?>
                    <a href="create.php" class="btn-action-primary">
                        <i class="fas fa-plus"></i>
                        İlk Rolü Oluştur
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Items Grid -->
            <div class="items-grid">
                <?php foreach ($roles as $role): ?>
                    <div class="item-card" data-role-id="<?= $role['id'] ?>">
                        <!-- Item Header -->
                        <div class="item-header">
                            <div class="item-slot">
                                <i class="<?= htmlspecialchars($role['icon_class'] ?? 'fas fa-user') ?>"></i>
                                <span><?= htmlspecialchars($role['role_name']) ?></span>
                            </div>
                        </div>

                        <!-- Item Content -->
                        <div class="item-content">
                            <h3 class="item-name">
                                <a href="view.php?id=<?= $role['id'] ?>" style="color: var(--lighter-grey); text-decoration: none;">
                                    <?= htmlspecialchars($role['role_name']) ?>
                                </a>
                            </h3>
                            
                            <p class="item-notes">
                                <i class="fas fa-quote-left"></i>
                                <?= htmlspecialchars(mb_substr($role['role_description'], 0, 150)) ?>
                                <?= mb_strlen($role['role_description']) > 150 ? '...' : '' ?>
                            </p>

                            <!-- Rol Detayları -->
                            <div class="role-details">
                                <?php if ($role['usage_count'] > 0): ?>
                                    <div class="item-manufacturer">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= $role['usage_count'] ?> etkinlikte kullanıldı</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($role['requirements_count'] > 0): ?>
                                    <div class="item-type">
                                        <i class="fas fa-check-circle"></i>
                                        <span><?= $role['requirements_count'] ?> gereksinim</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($role['loadout_name'])): ?>
                                    <div class="item-manufacturer">
                                        <i class="fas fa-shield-alt"></i>
                                        <span><?= htmlspecialchars($role['loadout_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-type">
                                    <i class="fas fa-user"></i>
                                    <span>Oluşturan: </span>
                                    <?php if ($is_logged_in): ?>
                                        <span class="user-link" 
                                              data-user-id="<?= $role['creator_user_id'] ?>"
                                              style="color: <?= htmlspecialchars($role['creator_role_color'] ?? '#bd912a') ?>; cursor: pointer; font-weight: 500;">
                                            <?= htmlspecialchars($role['creator_username'] ?? 'Bilinmeyen') ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #bd912a; font-weight: 500;">
                                            <?= htmlspecialchars($role['creator_username'] ?? 'Bilinmeyen') ?>
                                        </span>
                                    <?php endif; ?>
                                    <small style="margin-left: auto; color: var(--light-grey);">
                                        <?= date('d.m.Y', strtotime($role['created_at'])) ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Eylemler -->
                            <div class="set-actions" style="margin-top: 1rem;">
                                <a href="view.php?id=<?= $role['id'] ?>" class="btn-action-secondary">
                                    <i class="fas fa-eye"></i>
                                    Görüntüle
                                </a>
                                
                                <?php if ($is_logged_in && ($role['created_by_user_id'] == $current_user_id || $can_edit_all)): ?>
                                    <a href="edit.php?id=<?= $role['id'] ?>" class="btn-action-secondary">
                                        <i class="fas fa-edit"></i>
                                        Düzenle
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($is_logged_in && ($role['created_by_user_id'] == $current_user_id || $can_delete_all)): ?>
                                    <button type="button" 
                                            class="btn-action-secondary delete-role" 
                                            data-role-id="<?= $role['id'] ?>"
                                            data-role-name="<?= htmlspecialchars($role['role_name']) ?>"
                                            style="background: transparent; border: 1px solid var(--red); color: var(--red);">
                                        <i class="fas fa-trash"></i>
                                        Sil
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
        <div class="set-meta">
            <nav aria-label="Sayfa navigasyonu">
                <div class="pagination" style="display: flex; justify-content: center; gap: 0.5rem; align-items: center;">
                    <!-- İlk sayfa ve önceki -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="btn-action-secondary">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-action-secondary">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Sayfa numaraları -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="<?= $i === $page ? 'btn-action-primary' : 'btn-action-secondary' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Sonraki ve son sayfa -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-action-secondary">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="btn-action-secondary">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; margin-top: 1rem; color: var(--light-grey);">
                    Sayfa <?= $page ?> / <?= $total_pages ?> 
                    (Toplam <?= number_format($total_count) ?> rol)
                </div>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- User Popover Include - Sadece giriş yapmış kullanıcılar için -->
<?php if ($is_logged_in): ?>
    <?php include BASE_PATH . '/src/includes/user_popover.php'; ?>
<?php endif; ?>

<!-- Rol Silme Modalı -->
<?php if ($is_logged_in): ?>
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-1); border-radius: 8px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-1); padding: 1.5rem;">
                <h5 class="modal-title" id="deleteRoleModalLabel" style="color: var(--gold); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--red);"></i>
                    Rolü Sil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; color: var(--light-grey); font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; color: var(--lighter-grey);">
                <p><strong id="deleteRoleName" style="color: var(--gold);"></strong> adlı rolü silmek istediğinizden emin misiniz?</p>
                <p style="color: var(--light-grey);">Bu işlem geri alınamaz ve rol ile ilişkili tüm veriler silinecektir.</p>
                <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid var(--red); border-radius: 6px; padding: 1rem; margin-top: 1rem;">
                    <i class="fas fa-info-circle" style="color: var(--red);"></i>
                    Bu rol aktif etkinliklerde kullanılıyorsa silinmeyebilir.
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-1); padding: 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" class="btn-action-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> İptal
                </button>
                <button type="button" class="btn-action-primary" id="confirmDeleteRole" style="background: var(--red); border-color: var(--red);">
                    <i class="fas fa-trash"></i> Sil
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CSRF Token Meta Tag -->
<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">

<!-- JavaScript Dosyası -->
<script src="js/roles.js"></script>

<!-- Forum.js for user popover functionality - Sadece giriş yapmış kullanıcılar için -->
<?php if ($is_logged_in): ?>
    <script src="../forum/js/forum.js"></script>
<?php endif; ?>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>