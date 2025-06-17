<?php
// /teams/index.php
session_start();

// Güvenlik ve gerekli dosyaları dahil et
require_once dirname(__DIR__) . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/team_functions.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Login kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Veritabanı bağlantısı
if (!isset($pdo)) {
    die('Veritabanı bağlantı hatası');
}

try {
    $current_user_id = $_SESSION['user_id'];
    
    // Arama ve filtreleme parametreleri
    $search = $_GET['search'] ?? '';
    $tag_filter = $_GET['tag'] ?? '';
    $recruitment_filter = $_GET['recruitment'] ?? '';
    $sort_by = $_GET['sort'] ?? 'newest';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 12;
    $offset = ($page - 1) * $per_page;
    
    // Base query
    $where_conditions = ['t.status = "active"'];
    $params = [];
    
    // Arama filtresi
    if (!empty($search)) {
        $where_conditions[] = '(t.name LIKE :search OR t.description LIKE :search OR t.tag LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    
    // Tag filtresi
    if (!empty($tag_filter)) {
        $where_conditions[] = 't.tag = :tag';
        $params[':tag'] = $tag_filter;
    }
    
    // Recruitment filtresi
    if ($recruitment_filter === 'open') {
        $where_conditions[] = 't.is_recruitment_open = 1';
    } elseif ($recruitment_filter === 'closed') {
        $where_conditions[] = 't.is_recruitment_open = 0';
    }
    
    // Sıralama
    $order_by = 'ORDER BY t.created_at DESC';
    switch ($sort_by) {
        case 'name':
            $order_by = 'ORDER BY t.name ASC';
            break;
        case 'members':
            $order_by = 'ORDER BY member_count DESC, t.created_at DESC';
            break;
        case 'oldest':
            $order_by = 'ORDER BY t.created_at ASC';
            break;
        case 'newest':
        default:
            $order_by = 'ORDER BY t.created_at DESC';
            break;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Toplam takım sayısı
    $count_query = "
        SELECT COUNT(*) 
        FROM teams t
        $where_clause
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_teams = $stmt->fetchColumn();
    $total_pages = ceil($total_teams / $per_page);
    
    // Takımları getir
    $teams_query = "
        SELECT t.*, 
               u.username as creator_username,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id AND tm.status = 'active') as member_count,
               (SELECT COUNT(*) FROM team_applications ta WHERE ta.team_id = t.id AND ta.status = 'pending') as pending_applications
        FROM teams t
        LEFT JOIN users u ON t.created_by_user_id = u.id
        $where_clause
        $order_by
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($teams_query);
    $stmt->execute($params);
    $teams = $stmt->fetchAll();
    
    // Kullanıcının üyesi olduğu takımları al
    $user_teams_query = "
        SELECT team_id FROM team_members 
        WHERE user_id = ? AND status = 'active'
    ";
    $stmt = $pdo->prepare($user_teams_query);
    $stmt->execute([$current_user_id]);
    $user_team_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Kullanıcının bekleyen başvuruları
    $pending_applications_query = "
        SELECT team_id FROM team_applications 
        WHERE user_id = ? AND status = 'pending'
    ";
    $stmt = $pdo->prepare($pending_applications_query);
    $stmt->execute([$current_user_id]);
    $pending_application_team_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Mevcut tag'ları getir
    $tags_query = "
        SELECT DISTINCT tag FROM teams 
        WHERE tag IS NOT NULL AND tag != '' AND status = 'active'
        ORDER BY tag ASC
    ";
    $stmt = $pdo->prepare($tags_query);
    $stmt->execute();
    $available_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Teams index error: " . $e->getMessage());
    $teams = [];
    $total_teams = 0;
    $total_pages = 0;
    $available_tags = [];
}

// Success/Error mesajları
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'left_team':
            $success_message = 'Takımdan başarıyla ayrıldınız.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_team':
            $error_message = 'Geçersiz takım.';
            break;
        case 'team_not_found':
            $error_message = 'Takım bulunamadı.';
            break;
        case 'database_error':
            $error_message = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            break;
    }
}

// Sayfa başlığı
$page_title = 'Takımlar';

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<!-- Teams CSS -->
<link rel="stylesheet" href="/teams/css/index.css">

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/" class="breadcrumb-item">
            <i class="fas fa-home"></i> Ana Sayfa
        </a>
        <span class="breadcrumb-item active">
            <i class="fas fa-users"></i> Takımlar
        </span>
    </nav>

    <div class="teams-container">
        <!-- Page Header -->
        <div class="teams-header">
            <div class="header-content">
                <div class="header-info">
                    <h1><i class="fas fa-users"></i> Takımlar</h1>
                    <p>Star Citizen oyuncularının oluşturduğu takımlara katılın veya kendi takımınızı kurun</p>
                </div>
                
                <div class="header-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?= $total_teams ?></div>
                        <div class="stat-label">Toplam Takım</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($user_team_ids) ?></div>
                        <div class="stat-label">Üyesi Olduğunuz</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($pending_application_team_ids) ?></div>
                        <div class="stat-label">Bekleyen Başvuru</div>
                    </div>
                </div>
                
                <div class="header-actions">
                    <?php if (has_permission($pdo, 'teams.create', $current_user_id)): ?>
                        <a href="/teams/create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Yeni Takım Oluştur
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <!-- <div class="teams-filters">
            <form method="GET" action="" class="filters-form">
                <div class="filter-row">
                    <div class="search-group">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Takım adı, açıklama veya tag ara..." 
                                   class="search-input">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <select name="tag" class="filter-select">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach ($available_tags as $tag): ?>
                                <option value="<?= htmlspecialchars($tag) ?>" 
                                        <?= $tag_filter === $tag ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tag) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="recruitment" class="filter-select">
                            <option value="">Tüm Takımlar</option>
                            <option value="open" <?= $recruitment_filter === 'open' ? 'selected' : '' ?>>
                                Üye Alımı Açık
                            </option>
                            <option value="closed" <?= $recruitment_filter === 'closed' ? 'selected' : '' ?>>
                                Üye Alımı Kapalı
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>
                                En Yeni
                            </option>
                            <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>
                                En Eski
                            </option>
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>
                                İsme Göre
                            </option>
                            <option value="members" <?= $sort_by === 'members' ? 'selected' : '' ?>>
                                Üye Sayısına Göre
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrele
                        </button>
                        <a href="/teams/" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Temizle
                        </a>
                    </div>
                </div>
            </form>
        </div> -->

        <!-- Results Info -->
        <!-- <div class="results-info">
            <div class="results-count">
                <?= $total_teams ?> takım bulundu
                <?php if (!empty($search) || !empty($tag_filter) || !empty($recruitment_filter)): ?>
                    <span class="filter-active">
                        (filtrelenmiş)
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="page-info">
                    Sayfa <?= $page ?> / <?= $total_pages ?>
                </div>
            <?php endif; ?>
        </div> -->

        <!-- Teams Grid -->
        <div class="teams-grid">
            <?php if (empty($teams)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>Takım Bulunamadı</h3>
                    <p>
                        <?php if (!empty($search) || !empty($tag_filter) || !empty($recruitment_filter)): ?>
                            Arama kriterlerinize uygun takım bulunamadı. Filtreleri değiştirmeyi deneyin.
                        <?php else: ?>
                            Henüz hiç takım oluşturulmamış. İlk takımı siz oluşturun!
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($search) || !empty($tag_filter) || !empty($recruitment_filter)): ?>
                        <a href="/teams/" class="btn btn-outline-primary">
                            <i class="fas fa-refresh"></i> Tüm Takımları Göster
                        </a>
                    <?php endif; ?>
                    
                    <?php if (has_permission($pdo, 'teams.create', $current_user_id)): ?>
                        <a href="/teams/create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Yeni Takım Oluştur
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($teams as $team): ?>
                    <div class="team-card" data-team-id="<?= $team['id'] ?>">
                        <!-- Team Banner -->
                        <div class="team-banner" 
                             <?php if ($team['banner_path']): ?>
                                 style="background-image: url('<?= htmlspecialchars($team['banner_path']) ?>');"
                             <?php endif; ?>>
                            
                            <!-- Team Avatar -->
                            <div class="team-avatar" style="border-color: <?= htmlspecialchars($team['color']) ?>;">
                                <?php if ($team['logo_path']): ?>
                                    <img src="/uploads/teams/logos/<?= htmlspecialchars($team['logo_path']) ?>" 
                                         alt="<?= htmlspecialchars($team['name']) ?>">
                                <?php else: ?>
                                    <div class="avatar-placeholder" style="background: <?= htmlspecialchars($team['color']) ?>20; color: <?= htmlspecialchars($team['color']) ?>;">
                                        <?= strtoupper(substr($team['name'], 0, 2)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Team Status Badges -->
                            <div class="team-badges">
                                <?php if ($team['is_recruitment_open']): ?>
                                    <span class="badge recruitment-open">
                                        <i class="fas fa-unlock"></i> Üye Alımı Açık
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (in_array($team['id'], $user_team_ids)): ?>
                                    <span class="badge member-badge">
                                        <i class="fas fa-check"></i> Üyesiniz
                                    </span>
                                <?php elseif (in_array($team['id'], $pending_application_team_ids)): ?>
                                    <span class="badge pending-badge">
                                        <i class="fas fa-clock"></i> Başvuru Beklemede
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Team Content -->
                        <div class="team-content">
                            <!-- Team Header -->
                            <div class="team-header-info">
                                <h3 class="team-name">
                                    <a href="/teams/detail.php?id=<?= $team['id'] ?>" 
                                       style="color: <?= htmlspecialchars($team['color']) ?>;">
                                        <?= htmlspecialchars($team['name']) ?>
                                    </a>
                                </h3>
                                
                                <div class="team-meta">
                                    <?php if ($team['tag']): ?>
                                        <span class="team-tag" 
                                              style="background: <?= htmlspecialchars($team['color']) ?>20; color: <?= htmlspecialchars($team['color']) ?>; border-color: <?= htmlspecialchars($team['color']) ?>;">
                                            <?= htmlspecialchars($team['tag']) ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="team-founder">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($team['creator_username']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Team Description -->
                            <?php if ($team['description']): ?>
                                <div class="team-description">
                                    <?php 
                                    $description = $team['description'];
                                    if (strlen($description) > 150) {
                                        $description = substr($description, 0, 150) . '...';
                                    }
                                    echo nl2br(htmlspecialchars($description));
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Team Stats -->
                            <div class="team-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <span><?= $team['member_count'] ?>/<?= $team['max_members'] ?> üye</span>
                                </div>
                                
                                <div class="stat-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('d.m.Y', strtotime($team['created_at'])) ?></span>
                                </div>
                                
                                <?php if (in_array($team['id'], $user_team_ids) && $team['pending_applications'] > 0): ?>
                                    <div class="stat-item pending-indicator">
                                        <i class="fas fa-clock"></i>
                                        <span><?= $team['pending_applications'] ?> başvuru</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Team Actions -->
                            <div class="team-actions">
                                <a href="/teams/detail.php?id=<?= $team['id'] ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-eye"></i> Detay
                                </a>
                                
                                <?php if (in_array($team['id'], $user_team_ids)): ?>
                                    <?php if ($team['created_by_user_id'] == $current_user_id || has_permission($pdo, 'teams.edit_all', $current_user_id)): ?>
                                        <a href="/teams/manage.php?id=<?= $team['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-cog"></i> Yönet
                                        </a>
                                    <?php else: ?>
                                        <span class="member-indicator">
                                            <i class="fas fa-check-circle"></i> Üyesiniz
                                        </span>
                                    <?php endif; ?>
                                <?php elseif (in_array($team['id'], $pending_application_team_ids)): ?>
                                    <span class="pending-indicator">
                                        <i class="fas fa-clock"></i> Başvuru Beklemede
                                    </span>
                                <?php elseif ($team['is_recruitment_open'] && $team['member_count'] < $team['max_members']): ?>
                                    <a href="/teams/detail.php?id=<?= $team['id'] ?>?apply=1" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Başvur
                                    </a>
                                <?php else: ?>
                                    <span class="recruitment-closed">
                                        <i class="fas fa-lock"></i> 
                                        <?= $team['member_count'] >= $team['max_members'] ? 'Dolu' : 'Kapalı' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Capacity Bar -->
                        <div class="capacity-bar">
                            <div class="capacity-fill" 
                                 style="width: <?= $team['max_members'] > 0 ? round(($team['member_count'] / $team['max_members']) * 100, 1) : 0 ?>%; background: <?= htmlspecialchars($team['color']) ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="pagination-link prev">
                            <i class="fas fa-chevron-left"></i> Önceki
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                           class="pagination-link">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="pagination-link active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                               class="pagination-link"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                           class="pagination-link"><?= $total_pages ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="pagination-link next">
                            Sonraki <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Search form auto-submit on filter change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('.filter-select');
    const searchInput = document.querySelector('.search-input');
    
    // Auto-submit form when filter changes
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Debounced search
    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 500);
        });
    }
    
    // Team card animations
    const teamCards = document.querySelectorAll('.team-card');
    teamCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Enhanced team card interactions
    teamCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
});
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>