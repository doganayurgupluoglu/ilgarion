<?php
// /teams/detail.php
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

// Team ID kontrolü
$team_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$team_id) {
    header('Location: /teams/?error=invalid_team');
    exit;
}

// Takım verilerini getir
$team_data = get_team_by_id($pdo, $team_id);
if (!$team_data) {
    header('Location: /teams/?error=team_not_found');
    exit;
}

// Kullanıcı yetkilerini kontrol et
$current_user_id = $_SESSION['user_id'];
$is_team_member = is_team_member($pdo, $team_id, $current_user_id);
$can_edit_team = can_user_edit_team($pdo, $team_id, $current_user_id);
$can_manage_members = has_team_permission($pdo, $team_id, $current_user_id, 'team.manage.members');
$can_view_applications = has_team_permission($pdo, $team_id, $current_user_id, 'team.manage.applications');
$has_pending_application = has_pending_application($pdo, $team_id, $current_user_id);

// Takım üyelerini getir
$team_members = get_team_members($pdo, $team_id);

// Takım başvurularını getir (yetki varsa)
$team_applications = [];
if ($can_view_applications) {
    $team_applications = get_team_applications($pdo, $team_id, 'pending');
}

// Takım istatistikleri
$team_stats = get_team_statistics($pdo, $team_id);

// Success/Error mesajları
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success_message = 'Takım başarıyla oluşturuldu!';
            break;
        case 'application_sent':
            $success_message = 'Başvurunuz gönderildi!';
            break;
        case 'application_withdrawn':
            $success_message = 'Başvurunuz geri çekildi.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_permission':
            $error_message = 'Bu işlem için yetkiniz yok.';
            break;
        case 'already_member':
            $error_message = 'Zaten bu takımın üyesisiniz.';
            break;
        case 'recruitment_closed':
            $error_message = 'Bu takım şu anda üye alımı yapmıyor.';
            break;
    }
}

// Başvuru işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Güvenlik hatası.';
    } else {
        switch ($_POST['action']) {
            case 'apply':
                if ($is_team_member) {
                    $error_message = 'Zaten bu takımın üyesisiniz.';
                } elseif ($has_pending_application) {
                    $error_message = 'Zaten bekleyen bir başvurunuz var.';
                } elseif (!$team_data['is_recruitment_open']) {
                    $error_message = 'Bu takım şu anda üye alımı yapmıyor.';
                } else {
                    $message = trim($_POST['message'] ?? '');
                    if (apply_to_team($pdo, $team_id, $current_user_id, $message)) {
                        header('Location: /teams/detail.php?id=' . $team_id . '&success=application_sent');
                        exit;
                    } else {
                        $error_message = 'Başvuru gönderilirken bir hata oluştu.';
                    }
                }
                break;
                
            case 'leave_team':
                if (!$is_team_member) {
                    $error_message = 'Bu takımın üyesi değilsiniz.';
                } else {
                    if (remove_team_member($pdo, $team_id, $current_user_id, $current_user_id, 'Kullanıcı takımdan ayrıldı')) {
                        header('Location: /teams/?success=left_team');
                        exit;
                    } else {
                        $error_message = 'Takımdan ayrılırken bir hata oluştu.';
                    }
                }
                break;
        }
    }
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = htmlspecialchars($team_data['name']) . ' - Takım Detayı';

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<!-- Teams CSS -->
<link rel="stylesheet" href="/teams/css/detail.css">

<div class="teams-container">
    <!-- Team Header -->
    <div class="team-detail-header" style="--team-color: <?= htmlspecialchars($team_data['color']) ?>;">
        <div class="team-detail-content">
            <div class="team-detail-info">
                <div class="team-logo-section">
                    <?php if ($team_data['logo_path']): ?>
                        <img src="<?= htmlspecialchars($team_data['logo_path']) ?>" 
                             alt="<?= htmlspecialchars($team_data['name']) ?> Logo" 
                             class="team-detail-logo">
                    <?php else: ?>
                        <div class="team-detail-logo-placeholder">
                            <i class="fas fa-users"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="team-basic-info">
                    <h1 class="team-detail-name"><?= htmlspecialchars($team_data['name']) ?></h1>
                    
                    <div class="team-meta-info">
                        <?php if ($team_data['tag']): ?>
                            <span class="team-detail-tag">[<?= htmlspecialchars($team_data['tag']) ?>]</span>
                        <?php endif; ?>
                        
                        <span class="team-creator">
                            <i class="fas fa-crown"></i>
                            Kurucu: <?= htmlspecialchars($team_data['creator_username']) ?>
                        </span>
                        
                        <span class="team-created">
                            <i class="fas fa-calendar"></i>
                            <?= date('d.m.Y', strtotime($team_data['created_at'])) ?>
                        </span>
                    </div>
                    
                    <div class="team-detail-stats">
                        <div class="team-detail-stat">
                            <span class="team-detail-stat-value"><?= $team_stats['total_members'] ?></span>
                            <span class="team-detail-stat-label">Üye</span>
                        </div>
                        <div class="team-detail-stat">
                            <span class="team-detail-stat-value"><?= $team_data['max_members'] ?></span>
                            <span class="team-detail-stat-label">Kapasite</span>
                        </div>
                        <?php if ($can_view_applications): ?>
                        <div class="team-detail-stat">
                            <span class="team-detail-stat-value"><?= $team_stats['pending_applications'] ?></span>
                            <span class="team-detail-stat-label">Bekleyen</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="team-actions-section">
                <?php if ($can_edit_team): ?>
                    <a href="/teams/create.php?id=<?= $team_id ?>" class="btn-primary">
                        <i class="fas fa-edit"></i> Takımı Düzenle
                    </a>
                <?php endif; ?>
                
                <?php if ($is_team_member): ?>
                    <button type="button" class="btn-secondary" onclick="showLeaveTeamModal()">
                        <i class="fas fa-sign-out-alt"></i> Takımdan Ayrıl
                    </button>
                <?php elseif (!$has_pending_application && $team_data['is_recruitment_open']): ?>
                    <button type="button" class="btn-primary" onclick="showApplicationModal()">
                        <i class="fas fa-paper-plane"></i> Başvur
                    </button>
                <?php elseif ($has_pending_application): ?>
                    <span class="recruitment-status pending">
                        <i class="fas fa-clock"></i> Başvuru Beklemede
                    </span>
                <?php elseif (!$team_data['is_recruitment_open']): ?>
                    <span class="recruitment-status closed">
                        <i class="fas fa-times"></i> Üye Alımı Kapalı
                    </span>
                <?php endif; ?>
                
                <?php if ($can_manage_members): ?>
                    <a href="/teams/applications.php?id=<?= $team_id ?>" class="btn-outline-info">
                        <i class="fas fa-clipboard-list"></i> 
                        Başvurular <?= $team_stats['pending_applications'] > 0 ? '(' . $team_stats['pending_applications'] . ')' : '' ?>
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

    <!-- Main Content -->
    <div class="view-container">
        <!-- Main Content -->
        <div class="view-main-content">
            <!-- Team Description -->
            <?php if ($team_data['description']): ?>
            <div class="view-card">
                <div class="view-card-header">
                    <h5><i class="fas fa-info-circle"></i> Takım Hakkında</h5>
                </div>
                <div class="view-card-content">
                    <p class="team-description-text"><?= nl2br(htmlspecialchars($team_data['description'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Team Members -->
            <div class="view-card">
                <div class="view-card-header">
                    <h5><i class="fas fa-users"></i> Takım Üyeleri (<?= count($team_members) ?>)</h5>
                    <?php if ($can_manage_members): ?>
                        <a href="/teams/members.php?id=<?= $team_id ?>" class="btn-outline-primary btn-sm">
                            <i class="fas fa-cog"></i> Üye Yönetimi
                        </a>
                    <?php endif; ?>
                </div>
                <div class="view-card-content">
                    <?php if (empty($team_members)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Henüz takım üyesi bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="member-list">
                            <?php foreach ($team_members as $member): ?>
                                <div class="member-card" data-member-id="<?= $member['id'] ?>">
                                    <div class="member-header">
                                        <div class="member-avatar-section">
                                            <?php if ($member['avatar_path']): ?>
                                                <img src="<?= htmlspecialchars($member['avatar_path']) ?>" 
                                                     alt="<?= htmlspecialchars($member['username']) ?>" 
                                                     class="member-avatar">
                                            <?php else: ?>
                                                <div class="member-avatar-placeholder">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="member-info">
                                            <h6 class="member-name"><?= htmlspecialchars($member['username']) ?></h6>
                                            <?php if ($member['ingame_name']): ?>
                                                <p class="member-ingame"><?= htmlspecialchars($member['ingame_name']) ?></p>
                                            <?php endif; ?>
                                            <span class="member-role" style="background-color: <?= htmlspecialchars($member['role_color']) ?>">
                                                <?= htmlspecialchars($member['role_display_name']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="member-meta">
                                        <span class="member-joined">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('d.m.Y', strtotime($member['joined_at'])) ?>
                                        </span>
                                        <span class="member-status <?= $member['status'] ?>">
                                            <i class="fas fa-circle"></i>
                                            <?= ucfirst($member['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="view-sidebar">
            <!-- Team Stats -->
            <div class="view-card">
                <div class="view-card-header">
                    <h6><i class="fas fa-chart-bar"></i> İstatistikler</h6>
                </div>
                <div class="view-card-content">
                    <div class="team-stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= $team_stats['total_members'] ?></div>
                            <div class="stat-label">Toplam Üye</div>
                        </div>
                        
                        <?php if (!empty($team_stats['role_distribution'])): ?>
                            <?php foreach ($team_stats['role_distribution'] as $role): ?>
                                <div class="stat-item">
                                    <div class="stat-value" style="color: <?= htmlspecialchars($role['color']) ?>">
                                        <?= $role['count'] ?>
                                    </div>
                                    <div class="stat-label"><?= htmlspecialchars($role['display_name']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Team Info -->
            <div class="view-card">
                <div class="view-card-header">
                    <h6><i class="fas fa-info"></i> Takım Bilgileri</h6>
                </div>
                <div class="view-card-content">
                    <div class="team-info-list">
                        <div class="info-item">
                            <span class="info-label">Durum:</span>
                            <span class="info-value">
                                <?php if ($team_data['is_recruitment_open']): ?>
                                    <span class="recruitment-status open">
                                        <i class="fas fa-check"></i> Üye Alımı Açık
                                    </span>
                                <?php else: ?>
                                    <span class="recruitment-status closed">
                                        <i class="fas fa-times"></i> Üye Alımı Kapalı
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Kapasite:</span>
                            <span class="info-value">
                                <?= $team_stats['total_members'] ?> / <?= $team_data['max_members'] ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Oluşturulma:</span>
                            <span class="info-value">
                                <?= date('d.m.Y', strtotime($team_data['created_at'])) ?>
                            </span>
                        </div>
                        
                        <?php if ($team_data['updated_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Son Güncelleme:</span>
                            <span class="info-value">
                                <?= date('d.m.Y', strtotime($team_data['updated_at'])) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <?php if ($is_team_member || $can_edit_team): ?>
            <div class="view-card">
                <div class="view-card-header">
                    <h6><i class="fas fa-bolt"></i> Hızlı İşlemler</h6>
                </div>
                <div class="view-card-content">
                    <div class="quick-actions">
                        <?php if ($can_edit_team): ?>
                            <a href="/teams/create.php?id=<?= $team_id ?>" class="btn-outline-primary">
                                <i class="fas fa-edit"></i> Takımı Düzenle
                            </a>
                            <a href="/teams/settings.php?id=<?= $team_id ?>" class="btn-outline-secondary">
                                <i class="fas fa-cog"></i> Gelişmiş Ayarlar
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($can_manage_members): ?>
                            <a href="/teams/members.php?id=<?= $team_id ?>" class="btn-outline-info">
                                <i class="fas fa-users"></i> Üye Yönetimi
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($can_view_applications && $team_stats['pending_applications'] > 0): ?>
                            <a href="/teams/applications.php?id=<?= $team_id ?>" class="btn-outline-warning">
                                <i class="fas fa-clipboard-list"></i> 
                                Bekleyen Başvurular (<?= $team_stats['pending_applications'] ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Application Modal -->
<?php if (!$is_team_member && !$has_pending_application && $team_data['is_recruitment_open']): ?>
<div id="applicationModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="hideApplicationModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h4>Takıma Başvur</h4>
            <button class="modal-close" onclick="hideApplicationModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="application-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="apply">
            
            <div class="modal-body">
                <p>
                    <strong><?= htmlspecialchars($team_data['name']) ?></strong> takımına başvurmak istediğinizden emin misiniz?
                </p>
                
                <div class="form-group">
                    <label for="application_message">Başvuru Mesajı (İsteğe bağlı)</label>
                    <textarea id="application_message" 
                              name="message" 
                              class="form-control" 
                              rows="4" 
                              maxlength="1000"
                              placeholder="Kendinizi tanıtın ve neden bu takıma katılmak istediğinizi açıklayın..."></textarea>
                    <small class="form-help">Maksimum 1000 karakter</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="hideApplicationModal()">
                    İptal
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Başvuru Gönder
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Leave Team Modal -->
<?php if ($is_team_member): ?>
<div id="leaveTeamModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="hideLeaveTeamModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h4>Takımdan Ayrıl</h4>
            <button class="modal-close" onclick="hideLeaveTeamModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="leave_team">
            
            <div class="modal-body">
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>
                        <strong><?= htmlspecialchars($team_data['name']) ?></strong> takımından ayrılmak istediğinizden emin misiniz?
                    </p>
                    <p class="warning-text">
                        Bu işlem geri alınamaz. Tekrar katılmak için yeniden başvuru yapmanız gerekecek.
                    </p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="hideLeaveTeamModal()">
                    İptal
                </button>
                <button type="submit" class="btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Takımdan Ayrıl
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Teams JavaScript -->
<script src="/teams/js/teams.js"></script>

<script>
// Set team color for CSS
document.documentElement.style.setProperty('--team-color', '<?= htmlspecialchars($team_data['color']) ?>');

// Modal functions
function showApplicationModal() {
    document.getElementById('applicationModal').style.display = 'block';
}

function hideApplicationModal() {
    document.getElementById('applicationModal').style.display = 'none';
}

function showLeaveTeamModal() {
    document.getElementById('leaveTeamModal').style.display = 'block';
}

function hideLeaveTeamModal() {
    document.getElementById('leaveTeamModal').style.display = 'none';
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideApplicationModal();
        hideLeaveTeamModal();
    }
});
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>