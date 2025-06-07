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

// Session kontrolü
check_user_session_validity();
require_approved_user();

// Rol görüntüleme yetkisi kontrolü
if (!has_permission($pdo, 'event_role.view_public')) {
    $_SESSION['error_message'] = "Etkinlik rollerini görüntülemek için yetkiniz bulunmuyor.";
    header('Location: /events/');
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Filtreleme parametreleri
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$category = $_GET['category'] ?? '';
$visibility = $_GET['visibility'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Yetki kontrolleri
$can_create_role = has_permission($pdo, 'event_role.create');
$can_edit_all = has_permission($pdo, 'event_role.edit_all');
$can_delete_all = has_permission($pdo, 'event_role.delete_all');
$can_view_private = has_permission($pdo, 'event_role.view_private');

// WHERE koşulları oluşturma
$where_conditions = [];
$params = [];

// Temel görünürlük filtresi
if (!$can_view_private) {
    $where_conditions[] = "(er.visibility = 'public' OR (er.visibility = 'members_only' AND :is_member = 1) OR er.created_by_user_id = :user_id)";
    $params[':is_member'] = 1; // Onaylı kullanıcı
    $params[':user_id'] = $current_user_id;
}

// Filtreleme
switch ($filter) {
    case 'my_roles':
        $where_conditions[] = "er.created_by_user_id = :current_user_id";
        $params[':current_user_id'] = $current_user_id;
        break;
    case 'public':
        $where_conditions[] = "er.visibility = 'public'";
        break;
    case 'private':
        if ($can_view_private) {
            $where_conditions[] = "er.visibility = 'private'";
        }
        break;
    case 'members_only':
        $where_conditions[] = "er.visibility = 'members_only'";
        break;
}

// Arama
if (!empty($search)) {
    $where_conditions[] = "(er.role_name LIKE :search OR er.role_description LIKE :search OR u.username LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Kategori filtresi (isteğe bağlı - gelecekte kullanılabilir)
if (!empty($category)) {
    $where_conditions[] = "er.category = :category";
    $params[':category'] = $category;
}

// Sıralama
$order_by = "er.created_at DESC";
switch ($sort) {
    case 'name':
        $order_by = "er.role_name ASC";
        break;
    case 'oldest':
        $order_by = "er.created_at ASC";
        break;
    case 'most_used':
        $order_by = "usage_count DESC, er.created_at DESC";
        break;
    case 'newest':
    default:
        $order_by = "er.created_at DESC";
        break;
}

// Ana sorgu
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Toplam sayı sorgusu
    $count_sql = "
        SELECT COUNT(DISTINCT er.id)
        FROM event_roles er
        LEFT JOIN users u ON er.created_by_user_id = u.id
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    
    // Ana veri sorgusu
    $sql = "
        SELECT er.*, 
               u.username as creator_username,
               u.display_name as creator_display_name,
               ls.set_name as loadout_name,
               (SELECT COUNT(*) FROM event_role_assignments era WHERE era.event_role_id = er.id) as usage_count,
               (SELECT COUNT(*) FROM event_role_requirements err WHERE err.event_role_id = er.id) as requirements_count
        FROM event_roles er
        LEFT JOIN users u ON er.created_by_user_id = u.id
        LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id
        $where_clause
        ORDER BY $order_by
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sayfalama hesaplamaları
    $total_pages = ceil($total_count / $per_page);
    
} catch (PDOException $e) {
    error_log("Roles index error: " . $e->getMessage());
    $_SESSION['error_message'] = "Roller yüklenirken bir hata oluştu.";
    $roles = [];
    $total_count = 0;
    $total_pages = 0;
}

// Sayfa başlığı ve breadcrumb
$page_title = "Etkinlik Rolleri";
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Roller', 'url' => '/events/roles/', 'icon' => 'fas fa-user-tag']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);
?>

<div class="roles-index-container">
    <!-- Sayfa Başlığı ve Eylemler -->
    <div class="page-header">
        <div class="page-title-section">
            <h1>
                <i class="fas fa-user-tag"></i>
                Etkinlik Rolleri
            </h1>
            <p class="page-description">
                Etkinliklerde kullanılmak üzere oluşturulmuş roller ve görevler.
            </p>
        </div>
        
        <div class="page-actions">
            <?php if ($can_create_role): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Yeni Rol Oluştur
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtreler ve Arama -->
    <div class="filters-section">
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
                        <option value="my_roles" <?= $filter === 'my_roles' ? 'selected' : '' ?>>Oluşturduklarım</option>
                        <option value="public" <?= $filter === 'public' ? 'selected' : '' ?>>Herkese Açık</option>
                        <option value="members_only" <?= $filter === 'members_only' ? 'selected' : '' ?>>Sadece Üyeler</option>
                        <?php if ($can_view_private): ?>
                            <option value="private" <?= $filter === 'private' ? 'selected' : '' ?>>Özel</option>
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
                        <option value="most_used" <?= $sort === 'most_used' ? 'selected' : '' ?>>En Çok Kullanılan</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Sonuç Bilgisi -->
    <div class="results-info">
        <span class="results-count">
            <strong><?= number_format($total_count) ?></strong> rol bulundu
        </span>
        
        <?php if (!empty($search) || $filter !== 'all'): ?>
            <div class="active-filters">
                <?php if (!empty($search)): ?>
                    <span class="filter-tag">
                        Arama: "<?= htmlspecialchars($search) ?>"
                        <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="remove-filter">×</a>
                    </span>
                <?php endif; ?>
                
                <?php if ($filter !== 'all'): ?>
                    <span class="filter-tag">
                        Filtre: <?= ucfirst($filter) ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'all'])) ?>" class="remove-filter">×</a>
                    </span>
                <?php endif; ?>
                
                <a href="?" class="clear-filters">Tüm Filtreleri Temizle</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Roller Listesi -->
    <div class="roles-grid">
        <?php if (empty($roles)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <h3>Rol Bulunamadı</h3>
                <p>
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        Arama kriterlerinize uygun rol bulunamadı. Filtreleri değiştirmeyi deneyin.
                    <?php else: ?>
                        Henüz hiç etkinlik rolü oluşturulmamış.
                    <?php endif; ?>
                </p>
                <?php if ($can_create_role): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        İlk Rolü Oluştur
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($roles as $role): ?>
                <div class="role-card" data-role-id="<?= $role['id'] ?>">
                    <!-- Rol İkonu -->
                    <div class="role-icon">
                        <i class="<?= htmlspecialchars($role['role_icon'] ?? 'fas fa-user') ?>"></i>
                    </div>

                    <!-- Rol Bilgileri -->
                    <div class="role-info">
                        <h3 class="role-name">
                            <a href="view.php?id=<?= $role['id'] ?>">
                                <?= htmlspecialchars($role['role_name']) ?>
                            </a>
                        </h3>
                        
                        <p class="role-description">
                            <?= htmlspecialchars(mb_substr($role['role_description'], 0, 120)) ?>
                            <?= mb_strlen($role['role_description']) > 120 ? '...' : '' ?>
                        </p>

                        <!-- Rol Özellikleri -->
                        <div class="role-properties">
                            <div class="property-item">
                                <i class="fas fa-eye"></i>
                                <span class="visibility-<?= $role['visibility'] ?>">
                                    <?php
                                    switch ($role['visibility']) {
                                        case 'public':
                                            echo 'Herkese Açık';
                                            break;
                                        case 'members_only':
                                            echo 'Sadece Üyeler';
                                            break;
                                        case 'private':
                                            echo 'Özel';
                                            break;
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php if ($role['usage_count'] > 0): ?>
                                <div class="property-item">
                                    <i class="fas fa-users"></i>
                                    <span><?= $role['usage_count'] ?> kullanım</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($role['requirements_count'] > 0): ?>
                                <div class="property-item">
                                    <i class="fas fa-list-check"></i>
                                    <span><?= $role['requirements_count'] ?> gereksinim</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($role['loadout_name']): ?>
                                <div class="property-item">
                                    <i class="fas fa-user-astronaut"></i>
                                    <span><?= htmlspecialchars($role['loadout_name']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Oluşturan Kişi -->
                        <div class="role-creator">
                            <i class="fas fa-user-circle"></i>
                            <span>
                                <?= htmlspecialchars($role['creator_display_name'] ?: $role['creator_username']) ?>
                            </span>
                            <span class="creation-date">
                                <?= date('d.m.Y', strtotime($role['created_at'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Rol Eylemleri -->
                    <div class="role-actions">
                        <a href="view.php?id=<?= $role['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i>
                            Görüntüle
                        </a>
                        
                        <?php if ($role['created_by_user_id'] == $current_user_id || $can_edit_all): ?>
                            <a href="create.php?edit=<?= $role['id'] ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-edit"></i>
                                Düzenle
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($role['created_by_user_id'] == $current_user_id || $can_delete_all): ?>
                            <button class="btn btn-sm btn-danger" onclick="confirmDeleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['role_name']) ?>')">
                                <i class="fas fa-trash"></i>
                                Sil
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                        Önceki
                    </a>
                <?php endif; ?>
                
                <div class="pagination-info">
                    <span>Sayfa <?= $page ?> / <?= $total_pages ?></span>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn">
                        Sonraki
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Silme Onayı Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Rol Silme Onayı</h3>
            <button class="modal-close" onclick="closeDeleteModal()">×</button>
        </div>
        <div class="modal-body">
            <p>
                <strong id="roleNameToDelete"></strong> adlı rolü silmek istediğinizden emin misiniz?
            </p>
            <p class="warning-text">
                <i class="fas fa-exclamation-triangle"></i>
                Bu işlem geri alınamaz. Rol silindiğinde, bu role atanmış tüm katılımcılar da otomatik olarak atamalarını kaybedecektir.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">İptal</button>
            <button class="btn btn-danger" onclick="deleteRole()">Sil</button>
        </div>
    </div>
</div>

<style>/* events/roles/css/index.css - Etkinlik Rolleri Ana Sayfa Stilleri */

.roles-index-container {
   max-width: 1600px;
   margin: 0 auto;
   font-family: var(--font);
   color: var(--lighter-grey);
}

/* Sayfa Başlığı */
.page-header {
    background: linear-gradient(135deg, var(--card-bg), var(--card-bg-2));
    border: 1px solid var(--border-1-featured);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.page-title-section h1 {
   margin: 0 0 0.75rem 0;
   color: var(--gold);
   font-size: 2rem;
   font-weight: 600;
   display: flex;
   align-items: center;
   gap: 0.75rem;
}


.page-description {
   color: var(--light-grey);
   margin: 0;
   font-size: 1.1rem;
   line-height: 1.5;
}

.page-actions .btn {
   display: inline-flex;
   align-items: center;
   gap: 0.5rem;
   padding: 0.75rem 1.25rem;
   border-radius: 6px;
   font-weight: 500;
   text-decoration: none;
   transition: all 0.2s ease;
   border: none;
   cursor: pointer;
   font-family: var(--font);
   font-size: 0.9rem;
   background: var(--gold);
   color: var(--charcoal);
}

.page-actions .btn:hover {
   background: var(--light-gold);
   transform: translateY(-1px);
   text-decoration: none;
   color: var(--charcoal);
}

/* Filtreler Bölümü */
.filters-section {
   background: var(--card-bg);
   border: 1px solid var(--border-1);
   border-radius: 8px;
   padding: 1.5rem;
   margin-bottom: 1.5rem;
}

.filters-form .filter-row {
   display: flex;
   gap: 1.5rem;
   align-items: end;
   flex-wrap: wrap;
}

.filter-group {
   display: flex;
   flex-direction: column;
   gap: 0.5rem;
   min-width: 120px;
}

.filter-group label {
   font-weight: 600;
   color: var(--light-grey);
   font-size: 0.9rem;
   text-transform: uppercase;
   letter-spacing: 0.5px;
}

.filter-group input,
.filter-group select {
   padding: 0.75rem 1rem;
   border: 1px solid var(--border-1);
   border-radius: 6px;
   font-size: 0.95rem;
   background: var(--card-bg-2);
   color: var(--lighter-grey);
   transition: all 0.2s ease;
}

.filter-group input:focus,
.filter-group select:focus {
   outline: none;
   border-color: var(--turquase);
   background: var(--card-bg-3);
   box-shadow: 0 0 0 2px rgba(115, 228, 224, 0.2);
}

.search-input-group {
   display: flex;
   gap: 0;
}

.search-input-group input {
   border-radius: 6px 0 0 6px;
   border-right: none;
   min-width: 300px;
}

.search-btn {
   background: var(--turquase);
   color: var(--charcoal);
   border: 1px solid var(--turquase);
   border-radius: 0 6px 6px 0;
   padding: 0.75rem 1rem;
   cursor: pointer;
   transition: all 0.2s ease;
   font-weight: 500;
}

.search-btn:hover {
   background: var(--gold);
   border-color: var(--gold);
   transform: translateY(-1px);
}

/* Sonuç Bilgisi */
.results-info {
   display: flex;
   justify-content: space-between;
   align-items: center;
   margin-bottom: 1.5rem;
   padding: 1rem 0;
   border-bottom: 1px solid var(--border-1);
}

.results-count {
   font-size: 1.1rem;
   color: var(--lighter-grey);
   font-weight: 500;
}

.active-filters {
   display: flex;
   gap: 0.75rem;
   align-items: center;
   flex-wrap: wrap;
}

.filter-tag {
   background: var(--transparent-turquase);
   color: var(--turquase);
   padding: 0.5rem 0.75rem;
   border-radius: 20px;
   font-size: 0.85rem;
   display: inline-flex;
   align-items: center;
   gap: 0.5rem;
   border: 1px solid var(--turquase);
   font-weight: 500;
}

.remove-filter {
   color: var(--turquase);
   text-decoration: none;
   font-weight: 600;
   padding: 0 0.25rem;
   border-radius: 50%;
   transition: all 0.2s ease;
}

.remove-filter:hover {
   background: rgba(115, 228, 224, 0.2);
   transform: scale(1.1);
}

.clear-filters {
   color: var(--red);
   text-decoration: none;
   font-size: 0.9rem;
   font-weight: 500;
   transition: all 0.2s ease;
}

.clear-filters:hover {
   color: var(--gold);
   text-decoration: underline;
}

/* Roller Grid */
.roles-grid {
   display: grid;
   grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
   gap: 1.5rem;
   margin-bottom: 2rem;
}

.role-card {
   background: var(--card-bg);
   border: 1px solid var(--border-1);
   border-radius: 8px;
   padding: 1.5rem;
   transition: all 0.3s ease;
   position: relative;
   overflow: hidden;
}

.role-card::before {
   content: '';
   position: absolute;
   top: 0;
   left: 0;
   right: 0;
   height: 3px;
   background: linear-gradient(90deg, var(--turquase), var(--gold));
   transform: scaleX(0);
   transition: transform 0.3s ease;
}

.role-card:hover {
   transform: translateY(-4px);
   border-color: var(--border-1-hover);
   box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.role-card:hover::before {
   transform: scaleX(1);
}

.role-icon {
   text-align: center;
   margin-bottom: 1rem;
}

.role-icon i {
   font-size: 2.5rem;
   color: var(--turquase);
   padding: 1rem;
   background: var(--transparent-turquase);
   border-radius: 50%;
   width: 80px;
   height: 80px;
   display: inline-flex;
   align-items: center;
   justify-content: center;
   border: 1px solid var(--turquase);
   transition: all 0.3s ease;
}

.role-card:hover .role-icon i {
   background: var(--transparent-gold);
   color: var(--gold);
   border-color: var(--gold);
   transform: scale(1.1);
}

.role-name {
   margin: 0 0 0.75rem 0;
   text-align: center;
}

.role-name a {
   color: var(--lighter-grey);
   text-decoration: none;
   font-size: 1.3rem;
   font-weight: 600;
   transition: all 0.2s ease;
}

.role-name a:hover {
   color: var(--gold);
}

.role-description {
   color: var(--light-grey);
   line-height: 1.6;
   margin-bottom: 1rem;
   text-align: center;
   font-size: 0.95rem;
}

.role-properties {
   display: flex;
   flex-wrap: wrap;
   gap: 0.5rem;
   margin-bottom: 1rem;
   justify-content: center;
}

.property-item {
   display: flex;
   align-items: center;
   gap: 0.5rem;
   font-size: 0.85rem;
   color: var(--light-grey);
   background: var(--card-bg-2);
   padding: 0.5rem 0.75rem;
   border-radius: 20px;
   border: 1px solid var(--border-1);
   transition: all 0.2s ease;
}

.property-item:hover {
   background: var(--card-bg-3);
   border-color: var(--border-1-hover);
}

.property-item i {
   font-size: 0.8rem;
   color: var(--turquase);
}

.visibility-public {
   background: rgba(40, 167, 69, 0.15);
   color: #28a745;
   border-color: rgba(40, 167, 69, 0.3);
}

.visibility-members_only {
   background: var(--transparent-turquase);
   color: var(--turquase);
   border-color: var(--turquase);
}

.visibility-private {
   background: rgba(220, 53, 69, 0.15);
   color: var(--red);
   border-color: rgba(220, 53, 69, 0.3);
}

.role-creator {
   display: flex;
   align-items: center;
   gap: 0.75rem;
   font-size: 0.9rem;
   color: var(--light-grey);
   margin-bottom: 1.25rem;
   justify-content: center;
   padding-top: 1rem;
   border-top: 1px solid var(--border-1);
}

.role-creator i {
   color: var(--turquase);
   font-size: 1rem;
}

.creation-date {
   color: var(--light-grey);
   font-size: 0.85rem;
}

.role-actions {
   display: flex;
   gap: 0.5rem;
   justify-content: center;
   flex-wrap: wrap;
}

.role-actions .btn {
   padding: 0.5rem 1rem;
   font-size: 0.85rem;
   border-radius: 6px;
   transition: all 0.2s ease;
   border: none;
   cursor: pointer;
   font-family: var(--font);
   text-decoration: none;
   display: inline-flex;
   align-items: center;
   gap: 0.5rem;
   font-weight: 500;
}

.btn-primary {
   background: var(--gold);
   color: var(--charcoal);
}

.btn-primary:hover {
   background: var(--light-gold);
   transform: translateY(-1px);
}

.btn-secondary {
   background: transparent;
   color: var(--light-grey);
   border: 1px solid var(--border-1);
}

.btn-secondary:hover {
   background: var(--card-bg-2);
   color: var(--lighter-grey);
   border-color: var(--turquase);
   transform: translateY(-1px);
}

.btn-danger {
   background: transparent;
   color: var(--red);
   border: 1px solid var(--red);
}

.btn-danger:hover {
   background: var(--red);
   color: var(--charcoal);
   transform: translateY(-1px);
}

/* Empty State */
.empty-state {
   text-align: center;
   padding: 4rem 1.5rem;
   grid-column: 1 / -1;
   background: var(--card-bg);
   border: 1px solid var(--border-1);
   border-radius: 8px;
}

.empty-icon {
   font-size: 4rem;
   color: var(--light-grey);
   margin-bottom: 1.5rem;
   opacity: 0.5;
}

.empty-state h3 {
   color: var(--lighter-grey);
   margin-bottom: 0.75rem;
   font-size: 1.5rem;
   font-weight: 500;
}

.empty-state p {
   color: var(--light-grey);
   margin-bottom: 1.5rem;
   max-width: 500px;
   margin-left: auto;
   margin-right: auto;
   line-height: 1.6;
}

/* Sayfalama */
.pagination-container {
   display: flex;
   justify-content: center;
   margin-top: 2.5rem;
}

.pagination {
   display: flex;
   align-items: center;
   gap: 1.5rem;
   background: var(--card-bg);
   padding: 1rem 1.5rem;
   border-radius: 8px;
   border: 1px solid var(--border-1);
}

.pagination-btn {
   display: flex;
   align-items: center;
   gap: 0.5rem;
   padding: 0.75rem 1.25rem;
   background: var(--turquase);
   color: var(--charcoal);
   text-decoration: none;
   border-radius: 6px;
   transition: all 0.2s ease;
   font-weight: 500;
}

.pagination-btn:hover {
   background: var(--gold);
   color: var(--charcoal);
   transform: translateY(-1px);
   text-decoration: none;
}

.pagination-info {
   color: var(--lighter-grey);
   font-weight: 500;
   font-size: 0.95rem;
}

/* Modal Stilleri */
.modal {
   position: fixed;
   top: 0;
   left: 0;
   width: 100%;
   height: 100%;
   background: rgba(0, 0, 0, 0.8);
   display: flex;
   align-items: center;
   justify-content: center;
   z-index: 1000;
   backdrop-filter: blur(4px);
}

.modal-content {
   background: var(--card-bg);
   border: 1px solid var(--border-1-featured);
   border-radius: 8px;
   width: 90%;
   max-width: 500px;
   max-height: 90vh;
   overflow-y: auto;
   box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
   animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
   from {
       opacity: 0;
       transform: translateY(-20px) scale(0.95);
   }
   to {
       opacity: 1;
       transform: translateY(0) scale(1);
   }
}

.modal-header {
   display: flex;
   justify-content: space-between;
   align-items: center;
   padding: 1.5rem;
   border-bottom: 1px solid var(--border-1);
}

.modal-header h3 {
   margin: 0;
   color: var(--gold);
   font-size: 1.3rem;
   font-weight: 600;
}

.modal-close {
   background: none;
   border: none;
   font-size: 1.5rem;
   cursor: pointer;
   color: var(--light-grey);
   padding: 0.25rem;
   border-radius: 4px;
   transition: all 0.2s ease;
}

.modal-close:hover {
   color: var(--lighter-grey);
   background: var(--card-bg-2);
}

.modal-body {
   padding: 1.5rem;
}

.modal-body p {
   margin-bottom: 1rem;
   line-height: 1.6;
   color: var(--lighter-grey);
}

.warning-text {
   color: var(--red);
   font-size: 0.9rem;
   margin-top: 1rem;
   padding: 1rem;
   background: rgba(220, 53, 69, 0.1);
   border-radius: 6px;
   border-left: 4px solid var(--red);
}

.warning-text i {
   margin-right: 0.5rem;
   color: var(--red);
}

.modal-footer {
   display: flex;
   justify-content: flex-end;
   gap: 0.75rem;
   padding: 1.5rem;
   border-top: 1px solid var(--border-1);
   background: var(--card-bg-2);
}

/* Responsive Design */
@media (max-width: 1200px) {
   .roles-grid {
       grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
   }
}

@media (max-width: 992px) {
   .roles-index-container {
       padding: 1.5rem;
   }
   
   .roles-grid {
       grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
       gap: 1rem;
   }
}

@media (max-width: 768px) {
   .roles-index-container {
       padding: 1rem;
   }

   .page-header {
       flex-direction: column;
       gap: 1.5rem;
       align-items: stretch;
   }

   .page-title-section h1 {
       font-size: 1.6rem;
   }

   .filters-form .filter-row {
       flex-direction: column;
       align-items: stretch;
       gap: 1rem;
   }

   .filter-group {
       min-width: auto;
   }

   .search-input-group input {
       min-width: auto;
   }

   .roles-grid {
       grid-template-columns: 1fr;
       gap: 1rem;
   }

   .role-card {
       padding: 1.25rem;
   }

   .results-info {
       flex-direction: column;
       gap: 1rem;
       align-items: flex-start;
   }

   .active-filters {
       width: 100%;
       justify-content: flex-start;
   }

   .pagination {
       flex-direction: column;
       gap: 1rem;
       text-align: center;
   }

   .modal-content {
       width: 95%;
       margin: 1rem;
   }

   .modal-header,
   .modal-body,
   .modal-footer {
       padding: 1.25rem;
   }

   .modal-footer {
       flex-direction: column;
   }
}

@media (max-width: 480px) {
   .roles-index-container {
       padding: 0.75rem;
   }

   .role-actions {
       flex-direction: column;
   }

   .role-actions .btn {
       width: 100%;
       justify-content: center;
   }

   .property-item {
       font-size: 0.8rem;
       padding: 0.4rem 0.6rem;
   }

   .role-icon i {
       font-size: 2rem;
       width: 60px;
       height: 60px;
   }

   .page-title-section h1 {
       font-size: 1.4rem;
   }
}

/* Dark theme optimizations */
@media (prefers-color-scheme: dark) {
   .role-card:hover {
       box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
   }
   
   .modal {
       background: rgba(0, 0, 0, 0.9);
   }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
   .role-card::before {
       transition: none;
   }
   
   .modal-content {
       animation: none;
   }
   
   * {
       transition-duration: 0.01ms !important;
   }
}

/* Focus states */
.role-card:focus-within,
.btn:focus,
.filter-group input:focus,
.filter-group select:focus {
   outline: 2px solid var(--turquase);
   outline-offset: 2px;
}

.modal-close:focus {
   outline: 2px solid var(--gold);
   outline-offset: 2px;
}

/* Print styles */
@media print {
   .page-actions,
   .filters-section,
   .role-actions,
   .pagination-container {
       display: none;
   }
   
   .roles-grid {
       grid-template-columns: repeat(2, 1fr);
       gap: 1rem;
   }
   
   .role-card {
       break-inside: avoid;
       box-shadow: none;
       border: 1px solid #ccc;
   }
   
   .role-card::before {
       display: none;
   }
}</style>
<script>
let roleToDelete = null;

function confirmDeleteRole(roleId, roleName) {
    roleToDelete = roleId;
    document.getElementById('roleNameToDelete').textContent = roleName;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    roleToDelete = null;
}

function deleteRole() {
    if (!roleToDelete) return;
    
    // AJAX ile silme işlemi
    fetch('actions/delete_role.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            role_id: roleToDelete,
            csrf_token: '<?= generate_csrf_token() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Başarılı silme
            location.reload();
        } else {
            alert('Hata: ' + (data.message || 'Rol silinemedi.'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
    })
    .finally(() => {
        closeDeleteModal();
    });
}

// Modal dışına tıklandığında kapat
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// ESC tuşu ile modal'ı kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Arama formu enter tuşu ile gönderim
document.getElementById('search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('filtersForm').submit();
    }
});

// Sayfa yüklendiğinde arama inputuna odaklan (eğer boşsa)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    if (searchInput && !searchInput.value.trim()) {
        searchInput.focus();
    }
});
</script>

<?php
// Layout sonlandır
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>t