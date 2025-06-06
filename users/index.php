<?php
// users/index.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Session kontrolü
check_user_session_validity();

// Sayfa başlığı
$page_title = "Üye Listesi - Ilgarion Turanis";

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = is_user_logged_in();
$is_approved = is_user_approved();

// Arama parametreleri
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Güvenli arama sorgusu oluştur
try {
    $where_conditions = ["u.status = 'approved'"];
    $params = [];
    
    if (!empty($search)) {
        // Basit güvenlik kontrolü
        if (strlen($search) > 100) {
            throw new Exception("Search term too long");
        }
        
        // Güvenli LIKE pattern oluştur - % ve _ karakterlerini escape et
        $search_escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $search_pattern = '%' . $search_escaped . '%';
        
        $where_conditions[] = "(u.username LIKE ? OR u.ingame_name LIKE ? OR u.discord_username LIKE ?)";
        $params[] = $search_pattern;
        $params[] = $search_pattern;
        $params[] = $search_pattern;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Toplam kullanıcı sayısını al
    $count_query = "
        SELECT COUNT(DISTINCT u.id) 
        FROM users u 
        WHERE {$where_clause}
    ";
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    // Kullanıcıları çek
    $users_query = "
        SELECT u.id, u.username, u.ingame_name, u.discord_username, u.avatar_path, u.created_at,
               GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ',') AS roles_list,
               MIN(r.priority) as highest_priority,
               (SELECT name FROM roles WHERE id = (
                   SELECT role_id FROM user_roles WHERE user_id = u.id 
                   ORDER BY (SELECT priority FROM roles WHERE id = role_id) ASC LIMIT 1
               )) as primary_role_name,
               (SELECT color FROM roles WHERE id = (
                   SELECT role_id FROM user_roles WHERE user_id = u.id 
                   ORDER BY (SELECT priority FROM roles WHERE id = role_id) ASC LIMIT 1
               )) as primary_role_color
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE {$where_clause}
        GROUP BY u.id
        ORDER BY u.username ASC
        LIMIT ?, ?
    ";
    
    // LIMIT için ek parametreler ekle
    $params[] = $offset;
    $params[] = $per_page;
    
    $stmt = $pdo->prepare($users_query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sayfa sayısını hesapla
    $total_pages = ceil($total_users / $per_page);
    
} catch (Exception $e) {
    error_log("Users list error: " . $e->getMessage());
    $users = [];
    $total_users = 0;
    $total_pages = 0;
    $search_error = "Arama sırasında bir hata oluştu: " . $e->getMessage();
}

// Avatar path düzeltme fonksiyonu
function fix_avatar_path($avatar_path) {
    if (empty($avatar_path)) {
        return '/assets/logo.png';
    }
    
    if (strpos($avatar_path, '../assets/') === 0) {
        return str_replace('../assets/', '/assets/', $avatar_path);
    }
    
    if (strpos($avatar_path, 'uploads/') === 0) {
        return '' . $avatar_path;
    }
    
    if (strpos($avatar_path, '/assets/') === 0 || strpos($avatar_path, '') === 0) {
        return $avatar_path;
    }
    
    return '/assets/logo.png';
}

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => 'index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Üye Listesi', 'url' => '', 'icon' => 'fas fa-users']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="css/users.css">
<link rel="stylesheet" href="../css/style.css">

<div class="users-page-container">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ul class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../index.php">
                    <i class="fas fa-home"></i>
                    Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-users"></i>
                Üye Listesi
            </li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="users-header">
        <h1><i class="fas fa-users"></i> Üye Listesi</h1>
        <p>Ilgarion Turanis topluluğunun onaylı üyelerini keşfedin</p>
    </div>

    <!-- Search Form -->
    <div class="search-form-container">
        <form class="search-form" method="GET" action="">
            <div class="search-input-group">
                <div class="search-input-container">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Kullanıcı adı, oyun içi isim veya Discord adı ile ara..." 
                           value="<?= htmlspecialchars($search) ?>"
                           maxlength="100">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <?php if (!empty($search)): ?>
                <a href="?" class="clear-search">
                    <i class="fas fa-times"></i> Aramayı Temizle
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Search Results Info -->
    <?php if (!empty($search)): ?>
        <div class="search-results-info">
            <p>
                <strong><?= number_format($total_users) ?></strong> üye bulundu 
                "<strong><?= htmlspecialchars($search) ?></strong>" araması için
            </p>
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (isset($search_error)): ?>
        <div class="search-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($search_error) ?>
        </div>
    <?php endif; ?>

    <!-- Users List -->
    <?php if (!empty($users)): ?>
        <div class="users-list-container">
            <div class="users-table-header">
                <h3>
                    <i class="fas fa-list"></i> 
                    <?= empty($search) ? 'Tüm Üyeler' : 'Arama Sonuçları' ?>
                </h3>
                <div class="users-count">
                    <?= number_format($total_users) ?> üye
                </div>
            </div>

            <div class="users-table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th class="col-avatar"></th>
                            <th class="col-username">Kullanıcı</th>
                            <th class="col-role">Rol</th>
                            <th class="col-ingame">Oyun İçi İsim</th>
                            <th class="col-discord">Discord</th>
                            <th class="col-joined">Katılım Tarihi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td class="col-avatar">
                                    <div class="user-avatar">
                                        <img src="<?= fix_avatar_path($user['avatar_path']) ?>" 
                                             alt="<?= htmlspecialchars($user['username']) ?> Avatar" 
                                             class="avatar-img">
                                    </div>
                                </td>
                                <td class="col-username">
                                    <span class="user-link" 
                                          data-user-id="<?= $user['id'] ?>"
                                          style="color: <?= $user['primary_role_color'] ?? '#bd912a' ?>">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </span>
                                </td>
                                <td class="col-role">
                                    <span class="user-role" 
                                          style="color: <?= $user['primary_role_color'] ?? '#bd912a' ?>">
                                        <?= htmlspecialchars($user['primary_role_name'] ?? 'Üye') ?>
                                    </span>
                                </td>
                                <td class="col-ingame">
                                    <span class="ingame-name">
                                        <?= htmlspecialchars($user['ingame_name'] ?: 'Belirtilmemiş') ?>
                                    </span>
                                </td>
                                <td class="col-discord">
                                    <span class="discord-name">
                                        <?php if (!empty($user['discord_username'])): ?>
                                            <i class="fab fa-discord"></i>
                                            <?= htmlspecialchars($user['discord_username']) ?>
                                        <?php else: ?>
                                            <span class="not-specified">Belirtilmemiş</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="col-joined">
                                    <span class="join-date">
                                        <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="users-pagination">
                <div class="pagination-info">
                    Sayfa <?= $page ?> / <?= $total_pages ?>
                    (Toplam <?= number_format($total_users) ?> üye)
                </div>

                <nav class="pagination-nav">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="page-btn <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- No Users Found -->
        <div class="no-users">
            <div class="no-users-icon">
                <i class="fas fa-users-slash"></i>
            </div>
            <h3>
                <?= empty($search) ? 'Henüz üye bulunamadı' : 'Arama sonucu bulunamadı' ?>
            </h3>
            <p>
                <?php if (empty($search)): ?>
                    Henüz onaylı üye bulunmuyor.
                <?php else: ?>
                    "<strong><?= htmlspecialchars($search) ?></strong>" araması için sonuç bulunamadı.
                    <br>Farklı anahtar kelimeler deneyebilirsiniz.
                <?php endif; ?>
            </p>
            
            <?php if (!empty($search)): ?>
                <a href="?" class="btn-clear-search">
                    <i class="fas fa-arrow-left"></i> Tüm Üyeleri Görüntüle
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>