<?php
// /admin/users/index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Yetki kontrolü
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu sayfaya erişim için giriş yapmalısınız.";
    header('Location: ' . get_auth_base_url() . '/login.php');
    exit;
}

if (!has_permission($pdo, 'admin.users.view')) {
    $_SESSION['error_message'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
    header('Location: /index.php');
    exit;
}

$page_title = 'Kullanıcı Yönetimi';
$additional_css = ['../../css/style.css'];
$additional_css = ['../admin/users/css/manage_users.css'];
$additional_js = ['../admin/users/js/manage_users.js'];

// Kullanıcı istatistikleri
try {
    $stats_query = "
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_users,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
            COUNT(CASE WHEN status = 'suspended' THEN 1 END) as suspended_users,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_users
        FROM users
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute();
    $user_stats = $stats_stmt->fetch();

    // Roller listesi
    $roles_query = "SELECT id, name, color, priority FROM roles ORDER BY priority ASC";
    $roles_stmt = $pdo->prepare($roles_query);
    $roles_stmt->execute();
    $all_roles = $roles_stmt->fetchAll();

    // Skill tags listesi
    $skills_query = "SELECT id, tag_name FROM skill_tags ORDER BY tag_name ASC";
    $skills_stmt = $pdo->prepare($skills_query);
    $skills_stmt->execute();
    $all_skills = $skills_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("User management stats error: " . $e->getMessage());
    $user_stats = ['total_users' => 0, 'approved_users' => 0, 'pending_users' => 0, 'suspended_users' => 0, 'rejected_users' => 0];
    $all_roles = [];
    $all_skills = [];
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
        <span class="active"><i class="fas fa-users-cog"></i> Kullanıcı Yönetimi</span>
    </nav>

    <!-- Ana Başlık -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-title-section">
                <h1 class="page-title">
                    <i class="fas fa-users-cog"></i>
                    Kullanıcı Yönetimi
                </h1>
                <p class="page-subtitle">Kullanıcıları, rollerini ve skill tag'lerini yönetin</p>
            </div>
            
            <div class="page-actions">
                <?php if (has_permission($pdo, 'admin.users.edit_status')): ?>
                    <button class="btn btn-primary" onclick="openBulkActionsModal()">
                        <i class="fas fa-tasks"></i>
                        Toplu İşlemler
                    </button>
                <?php endif; ?>
                
                <button class="btn btn-secondary" onclick="refreshUserData()">
                    <i class="fas fa-sync-alt"></i>
                    Yenile
                </button>
            </div>
        </div>
    </div>

    <!-- İstatistik Kartları -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $user_stats['total_users'] ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-success">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $user_stats['approved_users'] ?></div>
                <div class="stat-label">Onaylı Kullanıcı</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-warning">
            <div class="stat-icon">
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $user_stats['pending_users'] ?></div>
                <div class="stat-label">Bekleyen Kullanıcı</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-danger">
            <div class="stat-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $user_stats['suspended_users'] + $user_stats['rejected_users'] ?></div>
                <div class="stat-label">Askıya Alınan/Reddedilen</div>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Listesi -->
    <div class="users-container">
        <div class="users-header">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Kullanıcı Listesi
            </h2>
            
            <div class="users-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Kullanıcı ara (isim, email, oyun içi isim)...">
                </div>
                
                <select id="statusFilter" class="filter-select">
                    <option value="all">Tüm Durumlar</option>
                    <option value="approved">Onaylı</option>
                    <option value="pending">Bekleyen</option>
                    <option value="suspended">Askıya Alınan</option>
                    <option value="rejected">Reddedilen</option>
                </select>

                <select id="roleFilter" class="filter-select">
                    <option value="all">Tüm Roller</option>
                    <?php foreach ($all_roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="users-table-container">
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllUsers"></th>
                        <th>Kullanıcı</th>
                        <th>İletişim</th>
                        <th>Durum</th>
                        <th>Roller</th>
                        <th>Skill Tags</th>
                        <th>Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Bu alan AJAX ile doldurulacak -->
                </tbody>
            </table>
            
            <div class="loading-placeholder" id="usersLoading">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Kullanıcılar yükleniyor...</span>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-container" id="paginationContainer">
            <!-- Pagination AJAX ile doldurulacak -->
        </div>
    </div>
</div>

<!-- Kullanıcı Düzenleme Modal -->
<div id="editUserModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-large">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="editModalTitle">Kullanıcı Düzenle</h4>
                <button type="button" class="modal-close" onclick="closeEditUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" id="editUserId" name="user_id">
                    
                    <div class="form-tabs">
                        <div class="tab-nav">
                            <button type="button" class="tab-btn active" data-tab="basic">Temel Bilgiler</button>
                            <button type="button" class="tab-btn" data-tab="roles">Roller</button>
                            <button type="button" class="tab-btn" data-tab="skills">Skill Tags</button>
                            <button type="button" class="tab-btn" data-tab="status">Durum</button>
                        </div>
                        
                        <!-- Temel Bilgiler Tab -->
                        <div class="tab-content active" id="tab-basic">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editUsername">Kullanıcı Adı</label>
                                    <input type="text" id="editUsername" name="username" class="form-control" readonly>
                                    <small class="form-hint">Kullanıcı adı değiştirilemez</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="editEmail">E-posta</label>
                                    <input type="email" id="editEmail" name="email" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editIngameName">Oyun İçi İsim</label>
                                    <input type="text" id="editIngameName" name="ingame_name" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="editDiscordUsername">Discord Kullanıcı Adı</label>
                                    <input type="text" id="editDiscordUsername" name="discord_username" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="editProfileInfo">Profil Açıklaması</label>
                                <textarea id="editProfileInfo" name="profile_info" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                        
                        <!-- Roller Tab -->
                        <div class="tab-content" id="tab-roles">
                            <div class="roles-management">
                                <div class="current-roles" id="currentRoles">
                                    <h5>Mevcut Roller</h5>
                                    <div class="roles-list" id="userRolesList">
                                        <!-- Mevcut roller buraya yüklenecek -->
                                    </div>
                                </div>
                                
                                <div class="available-roles" id="availableRoles">
                                    <h5>Eklenebilir Roller</h5>
                                    <div class="roles-list" id="availableRolesList">
                                        <!-- Mevcut olmayan roller buraya yüklenecek -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Skill Tags Tab -->
                        <div class="tab-content" id="tab-skills">
                            <div class="skills-management">
                                <div class="current-skills" id="currentSkills">
                                    <h5>Mevcut Skill Tags</h5>
                                    <div class="skills-list" id="userSkillsList">
                                        <!-- Mevcut skill tags buraya yüklenecek -->
                                    </div>
                                </div>
                                
                                <div class="available-skills" id="availableSkills">
                                    <h5>Eklenebilir Skill Tags</h5>
                                    <div class="skills-search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="skillsSearch" placeholder="Skill ara...">
                                    </div>
                                    <div class="skills-list" id="availableSkillsList">
                                        <!-- Mevcut olmayan skill tags buraya yüklenecek -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Durum Tab -->
                        <div class="tab-content" id="tab-status">
                            <div class="status-management">
                                <div class="form-group">
                                    <label for="editUserStatus">Kullanıcı Durumu</label>
                                    <select id="editUserStatus" name="status" class="form-control">
                                        <option value="pending">Bekliyor</option>
                                        <option value="approved">Onaylı</option>
                                        <option value="suspended">Askıya Alınmış</option>
                                        <option value="rejected">Reddedilmiş</option>
                                    </select>
                                </div>
                                
                                <div class="status-history" id="statusHistory">
                                    <h5>Durum Geçmişi</h5>
                                    <div class="history-list" id="statusHistoryList">
                                        <!-- Durum geçmişi buraya yüklenecek -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <div class="footer-info">
                        <span class="change-indicator" id="changeIndicator">
                            <i class="fas fa-info-circle"></i>
                            Değişiklik yapmadınız
                        </span>
                    </div>
                    
                    <div class="footer-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">İptal</button>
                        <button type="button" class="btn btn-primary" onclick="saveUser()">
                            <i class="fas fa-save"></i>
                            Kullanıcıyı Kaydet
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toplu İşlemler Modal -->
<div id="bulkActionsModal" class="modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Toplu İşlemler</h4>
                <button type="button" class="modal-close" onclick="closeBulkActionsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="selected-users-info" id="selectedUsersInfo">
                    <span id="selectedCount">0</span> kullanıcı seçili
                </div>
                
                <div class="bulk-actions-list">
                    <button class="bulk-action-btn" onclick="bulkChangeStatus('approved')">
                        <i class="fas fa-user-check"></i>
                        <span>Tümünü Onayla</span>
                    </button>
                    
                    <button class="bulk-action-btn" onclick="bulkChangeStatus('suspended')">
                        <i class="fas fa-user-slash"></i>
                        <span>Tümünü Askıya Al</span>
                    </button>
                    
                    <button class="bulk-action-btn" onclick="bulkChangeStatus('rejected')">
                        <i class="fas fa-user-times"></i>
                        <span>Tümünü Reddet</span>
                    </button>
                    
                    <button class="bulk-action-btn bulk-action-danger" onclick="bulkDeleteUsers()">
                        <i class="fas fa-trash"></i>
                        <span>Seçilenleri Sil</span>
                    </button>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkActionsModal()">İptal</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <span>İşleminiz gerçekleştiriliyor...</span>
    </div>
</div>

<?php 
require_once BASE_PATH . '/src/includes/admin_quick_menu.php';
require_once BASE_PATH . '/src/includes/footer.php'; 
?>

<!-- JavaScript dosyalarını yükle -->
<script src="/admin/users/js/manage_users.js"></script>
<script>
// Sayfa yüklendikten sonra JavaScript'i başlat
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ManageUsers !== 'undefined') {
        ManageUsers.init();
    }
});
</script>