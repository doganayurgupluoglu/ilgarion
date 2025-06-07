<?php
// /events/loadouts/index.php - DÜZELTİLMİŞ VERSİYON

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// Pagination ayarları
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Filtreleme parametreleri
$visibility_filter = $_GET['visibility'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');

// Base query
$where_conditions = [];
$query_params = [];

// Visibility filter
if ($visibility_filter !== 'all') {
    if ($visibility_filter === 'public') {
        $where_conditions[] = "ls.visibility = 'public'";
    } elseif ($visibility_filter === 'members_only') {
        $where_conditions[] = "ls.visibility = 'members_only'";
    } elseif ($visibility_filter === 'my_sets' && is_user_logged_in()) {
        $where_conditions[] = "ls.user_id = :current_user_id";
        $query_params[':current_user_id'] = $_SESSION['user_id'];
    }
}

// Visibility kontrolü - kullanıcı durumuna göre
if (!is_user_logged_in()) {
    // Giriş yapmamış kullanıcılar sadece public görebilir
    $where_conditions[] = "ls.visibility = 'public'";
} elseif (!is_user_approved()) {
    // Onaylanmamış kullanıcılar sadece public görebilir
    $where_conditions[] = "ls.visibility = 'public'";
} else {
    // Onaylı kullanıcılar public ve members_only görebilir
    if ($visibility_filter === 'all') {
        $where_conditions[] = "ls.visibility IN ('public', 'members_only')";
    }
}

// Search filter
if (!empty($search_query)) {
    $where_conditions[] = "(ls.set_name LIKE :search OR ls.set_description LIKE :search OR u.username LIKE :search)";
    $query_params[':search'] = '%' . $search_query . '%';
}

// Status filter - sadece published olanları göster
$where_conditions[] = "ls.status = 'published'";

// WHERE clause oluştur
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Önce weapon attachments tablosunun var olup olmadığını kontrol et
    $table_check = $pdo->query("SHOW TABLES LIKE 'loadout_weapon_attachments'");
    $has_weapon_attachments_table = $table_check->rowCount() > 0;
    
    // Ana query - loadout setleri (DÜZELTEN SORGU)
    if ($has_weapon_attachments_table) {
        $query = "
            SELECT 
                ls.*,
                u.username,
                u.avatar_path,
                r.name as primary_role_name,
                r.color as primary_role_color,
                COUNT(DISTINCT lsi.id) as item_count,
                COUNT(DISTINCT lwa.id) as attachment_count
            FROM loadout_sets ls
            LEFT JOIN users u ON ls.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id AND r.priority = (
                SELECT MIN(r2.priority) 
                FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            LEFT JOIN loadout_set_items lsi ON ls.id = lsi.loadout_set_id
            LEFT JOIN loadout_weapon_attachments lwa ON ls.id = lwa.loadout_set_id
            $where_clause
            GROUP BY ls.id, ls.set_name, ls.set_description, ls.visibility, ls.status, ls.created_at, ls.updated_at, ls.user_id, ls.set_image_path, u.username, u.avatar_path, r.name, r.color
            ORDER BY ls.created_at DESC
            LIMIT :offset, :per_page
        ";
    } else {
        // Weapon attachments tablosu yoksa daha basit sorgu
        $query = "
            SELECT 
                ls.*,
                u.username,
                u.avatar_path,
                r.name as primary_role_name,
                r.color as primary_role_color,
                COUNT(DISTINCT lsi.id) as item_count,
                0 as attachment_count
            FROM loadout_sets ls
            LEFT JOIN users u ON ls.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id AND r.priority = (
                SELECT MIN(r2.priority) 
                FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            LEFT JOIN loadout_set_items lsi ON ls.id = lsi.loadout_set_id
            $where_clause
            GROUP BY ls.id, ls.set_name, ls.set_description, ls.visibility, ls.status, ls.created_at, ls.updated_at, ls.user_id, ls.set_image_path, u.username, u.avatar_path, r.name, r.color
            ORDER BY ls.created_at DESC
            LIMIT :offset, :per_page
        ";
    }
    
    $stmt = $pdo->prepare($query);
    
    // Parameters bind et
    foreach ($query_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $loadout_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG: Kaç sonuç bulundu?
    error_log("Loadouts query executed. Found " . count($loadout_sets) . " results");
    
    // Toplam sayfa sayısı için count query (DÜZELTEN COUNT SORGU)
    $count_query = "
        SELECT COUNT(DISTINCT ls.id) as total
        FROM loadout_sets ls
        LEFT JOIN users u ON ls.user_id = u.id
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_query);
    foreach ($query_params as $key => $value) {
        if ($key !== ':offset' && $key !== ':per_page') {
            $count_stmt->bindValue($key, $value);
        }
    }
    $count_stmt->execute();
    $total_sets = $count_stmt->fetchColumn();
    $total_pages = ceil($total_sets / $per_page);
    
    error_log("Total loadout sets found: $total_sets, Total pages: $total_pages");
    
} catch (PDOException $e) {
    error_log("Loadouts listing error: " . $e->getMessage());
    error_log("Query: " . ($query ?? 'Query not set'));
    error_log("Params: " . json_encode($query_params));
    
    $loadout_sets = [];
    $total_sets = 0;
    $total_pages = 0;
    
    // Kullanıcıya hata mesajı göster
    $_SESSION['error_message'] = "Teçhizat setleri yüklenirken bir hata oluştu.";
}

$page_title = "Teçhizat Setleri";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Teçhizat Setleri', 'url' => '', 'icon' => 'fas fa-user-astronaut']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Helper functions
function generate_breadcrumb($items) {
    $breadcrumb = '<nav class="breadcrumb-nav"><ol class="breadcrumb">';
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        $breadcrumb .= '<li class="breadcrumb-item' . ($isLast ? ' active' : '') . '">';
        if ($isLast || empty($item['url'])) {
            $breadcrumb .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
        } else {
            $breadcrumb .= '<a href="' . htmlspecialchars($item['url']) . '">';
            $breadcrumb .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
            $breadcrumb .= '</a>';
        }
        $breadcrumb .= '</li>';
    }
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 2592000) return floor($time/86400) . ' gün önce';
    return date('d.m.Y', strtotime($datetime));
}

function get_visibility_text($visibility) {
    switch ($visibility) {
        case 'public': return 'Herkese Açık';
        case 'members_only': return 'Üyelere Özel';
        case 'faction_only': return 'Fraksiyona Özel';
        case 'private': return 'Özel';
        default: return ucfirst($visibility);
    }
}

function get_visibility_icon($visibility) {
    switch ($visibility) {
        case 'public': return 'fas fa-globe';
        case 'members_only': return 'fas fa-users';
        case 'faction_only': return 'fas fa-shield-alt';
        case 'private': return 'fas fa-lock';
        default: return 'fas fa-eye';
    }
}
?>

<link rel="stylesheet" href="../../css/style.css">
<link rel="stylesheet" href="css/loadouts.css">

<div class="loadouts-page-container">
    <!-- Breadcrumb Navigation -->
    <?= generate_breadcrumb($breadcrumb_items) ?>

    <div class="loadouts-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1>
                        <i class="fas fa-user-astronaut"></i>
                        Teçhizat Setleri
                    </h1>
                    <p>Star Citizen için hazırlanmış teçhizat setlerini keşfedin ve kendi setinizi oluşturun</p>
                </div>
                <div class="header-actions">
                    <?php if (is_user_approved() && has_permission($pdo, 'loadout.manage_sets')): ?>
                        <a href="create_loadouts.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Yeni Set Oluştur
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DEBUG INFO (geçici) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
            <div style="background: #333; color: #fff; padding: 1rem; margin: 1rem 0; border-radius: 4px; font-family: monospace;">
                <strong>DEBUG INFO:</strong><br>
                Total loadout sets: <?= $total_sets ?><br>
                Current page: <?= $page ?><br>
                Results per page: <?= $per_page ?><br>
                Offset: <?= $offset ?><br>
                Where conditions: <?= implode(' AND ', $where_conditions) ?><br>
                Visibility filter: <?= $visibility_filter ?><br>
                User logged in: <?= is_user_logged_in() ? 'Yes' : 'No' ?><br>
                User approved: <?= is_user_approved() ? 'Yes' : 'No' ?><br>
                Has weapon attachments table: <?= isset($has_weapon_attachments_table) && $has_weapon_attachments_table ? 'Yes' : 'No' ?><br>
                Loadout sets found: <?= count($loadout_sets) ?>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" action="" class="search-form">
                <div class="search-input-container">
                    <input type="text" id="search" name="search" 
                           value="<?= htmlspecialchars($search_query) ?>" 
                           placeholder="Set adı, açıklama veya kullanıcı adı arayın...">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <div class="results-info">
            <span>
                <strong><?= number_format($total_sets) ?></strong> teçhizat seti bulundu
                <?php if (!empty($search_query)): ?>
                    - "<strong><?= htmlspecialchars($search_query) ?></strong>" araması için
                <?php endif; ?>
            </span>
            <span>
                Sayfa <?= $page ?> / <?= max(1, $total_pages) ?>
            </span>
        </div>

        <!-- Loadouts Table -->
        <?php if (!empty($loadout_sets)): ?>
            <div class="loadouts-table-container">
                <table class="loadouts-table">
                    <thead>
                        <tr>
                            <th>Görsel</th>
                            <th>Set Bilgileri</th>
                            <th>Oluşturan</th>
                            <th>İstatistikler</th>
                            <th>Görünürlük</th>
                            <th>Oluşturma</th>
                            <?php if (is_user_logged_in()): ?>
                                <th>İşlemler</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loadout_sets as $set): ?>
                            <tr>
                                <!-- Görsel -->
                                <td>
                                    <div class="set-image">
                                        <?php if (!empty($set['set_image_path'])): ?>
                                            <img src="/<?= htmlspecialchars($set['set_image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($set['set_name']) ?>" 
                                                 onerror="this.parentElement.innerHTML='<div class=\'set-image-placeholder\'><i class=\'fas fa-user-astronaut\'></i></div>'">
                                        <?php else: ?>
                                            <div class="set-image-placeholder">
                                                <i class="fas fa-user-astronaut"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Set Bilgileri -->
                                <td>
                                    <div class="set-info">
                                        <h3>
                                            <a href="view.php?id=<?= $set['id'] ?>">
                                                <?= htmlspecialchars($set['set_name']) ?>
                                            </a>
                                        </h3>
                                        <div class="set-description">
                                            <?= htmlspecialchars($set['set_description']) ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Oluşturan -->
                                <td>
                                    <div class="creator-info">
                                        <img src="<?= !empty($set['avatar_path']) ? '/' . htmlspecialchars($set['avatar_path']) : '/assets/logo.png' ?>" 
                                             alt="<?= htmlspecialchars($set['username']) ?>" 
                                             class="creator-avatar"
                                             onerror="this.src='/assets/logo.png'">
                                        <span class="user-link creator-name" 
                                              data-user-id="<?= $set['user_id'] ?>"
                                              style="color: <?= $set['primary_role_color'] ?? '#bd912a' ?>; cursor: pointer;">
                                            <?= htmlspecialchars($set['username']) ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- İstatistikler -->
                                <td>
                                    <div class="set-stats">
                                        <div class="stat-item">
                                            <i class="fas fa-boxes"></i>
                                            <span><?= $set['item_count'] ?> item</span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="fas fa-puzzle-piece"></i>
                                            <span><?= $set['attachment_count'] ?> eklenti</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Görünürlük -->
                                <td>
                                    <span class="visibility-badge visibility-<?= $set['visibility'] ?>">
                                        <i class="<?= get_visibility_icon($set['visibility']) ?>"></i>
                                        <?= get_visibility_text($set['visibility']) ?>
                                    </span>
                                </td>

                                <!-- Oluşturma Tarihi -->
                                <td>
                                    <span title="<?= date('d.m.Y H:i', strtotime($set['created_at'])) ?>">
                                        <?= time_ago($set['created_at']) ?>
                                    </span>
                                </td>

                                <!-- İşlemler -->
                                <?php if (is_user_logged_in()): ?>
                                    <td>
                                        <div class="set-actions">
                                            <a href="view.php?id=<?= $set['id'] ?>" 
                                               class="btn-action" 
                                               title="Görüntüle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($set['user_id'] == $_SESSION['user_id'] || has_permission($pdo, 'loadout.manage_sets')): ?>
                                                <a href="create_loadouts.php?edit=<?= $set['id'] ?>" 
                                                   class="btn-action edit" 
                                                   title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button type="button" 
                                                        class="btn-action delete" 
                                                        title="Sil"
                                                        onclick="confirmDelete(<?= $set['id'] ?>, '<?= htmlspecialchars($set['set_name'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="loadouts-table-container">
                <div class="empty-state">
                    <i class="fas fa-user-astronaut"></i>
                    <h3>Henüz Teçhizat Seti Yok</h3>
                    <p>
                        <?php if (!empty($search_query)): ?>
                            "<strong><?= htmlspecialchars($search_query) ?></strong>" aramanız için sonuç bulunamadı. 
                            Farklı arama terimleri deneyin.
                        <?php elseif ($visibility_filter === 'my_sets'): ?>
                            Henüz hiç teçhizat seti oluşturmadınız. İlk setinizi oluşturmaya başlayın!
                        <?php else: ?>
                            Henüz hiç teçhizat seti paylaşılmamış. İlk seti siz oluşturun!
                        <?php endif; ?>
                    </p>
                    
                    <?php if (is_user_approved() && has_permission($pdo, 'loadout.manage_sets')): ?>
                        <a href="create_loadouts.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            İlk Seti Oluştur
                        </a>
                    <?php elseif (!is_user_logged_in()): ?>
                        <a href="/login.php" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Giriş Yap
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Silme Onayı</h3>
            <button class="modal-close" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">Bu teçhizat setini silmek istediğinizden emin misiniz?</p>
            <p><strong id="deleteSetName"></strong></p>
            <p style="color: var(--red); font-size: 0.9rem; margin-top: 1rem;">
                <i class="fas fa-warning"></i> Bu işlem geri alınamaz!
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">İptal</button>
            <button class="btn-delete" id="confirmDeleteBtn">
                <i class="fas fa-trash"></i>
                Sil
            </button>
        </div>
    </div>
</div>


<!-- User Popover Include -->
<script src="js/delete_modal.js"></script>
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>

<!-- CSRF Token için meta tag ekle -->
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">

<?php include BASE_PATH . '/src/includes/footer.php'; ?>
