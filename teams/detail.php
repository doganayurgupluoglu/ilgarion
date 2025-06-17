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

try {
    // Takım verilerini getir
    $stmt = $pdo->prepare("
        SELECT t.*, u.username as creator_username
        FROM teams t
        LEFT JOIN users u ON t.created_by_user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    $team_data = $stmt->fetch();
    
    if (!$team_data) {
        header('Location: /teams/?error=team_not_found');
        exit;
    }
    
    // Kullanıcı yetkilerini kontrol et
    $current_user_id = $_SESSION['user_id'];
    
    // Takım üyeliği kontrolü - hem active hem de başvuru durumunu kontrol et
    $stmt = $pdo->prepare("
        SELECT tm.*, tr.name as role_name, tr.display_name as role_display_name, 
               tr.color as role_color, tr.is_management, tr.priority
        FROM team_members tm
        JOIN team_roles tr ON tm.team_role_id = tr.id
        WHERE tm.team_id = ? AND tm.user_id = ? AND tm.status = 'active'
    ");
    $stmt->execute([$team_id, $current_user_id]);
    $user_membership = $stmt->fetch();
    
    $is_team_member = !empty($user_membership);
    $user_role_priority = $user_membership['priority'] ?? 999;
    $is_team_manager = $user_membership['is_management'] ?? false;
    
    // Takım sahibi kontrolü
    $is_team_owner = ($team_data['created_by_user_id'] == $current_user_id);
    
    // Genel yetki kontrolü
    $can_edit_team = $is_team_owner || has_permission($pdo, 'teams.edit_all', $current_user_id);
    $can_manage_members = $is_team_manager || $is_team_owner;
    
    // Başvuru durumu kontrolü - herhangi bir durumda başvuru var mı?
    $stmt = $pdo->prepare("
        SELECT status FROM team_applications 
        WHERE team_id = ? AND user_id = ? 
        ORDER BY applied_at DESC LIMIT 1
    ");
    $stmt->execute([$team_id, $current_user_id]);
    $latest_application_status = $stmt->fetchColumn();
    
    // Sadece pending başvuruları "bekleyen" olarak say
    $has_pending_application = ($latest_application_status === 'pending');
    
    // Eğer approved bir başvuru varsa ve henüz üye değilse, büyük ihtimalle trigger çalışmamıştır
    if ($latest_application_status === 'approved' && !$is_team_member) {
        // Manuel olarak üye ekle
        $stmt = $pdo->prepare("
            SELECT id FROM team_roles 
            WHERE team_id = ? AND is_default = 1 
            LIMIT 1
        ");
        $stmt->execute([$team_id]);
        $default_role_id = $stmt->fetchColumn();
        
        if ($default_role_id) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO team_members (team_id, user_id, team_role_id, status, joined_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$team_id, $current_user_id, $default_role_id]);
            
            // Üyelik durumunu yeniden kontrol et
            $stmt = $pdo->prepare("
                SELECT tm.*, tr.name as role_name, tr.display_name as role_display_name, 
                       tr.color as role_color, tr.is_management, tr.priority
                FROM team_members tm
                JOIN team_roles tr ON tm.team_role_id = tr.id
                WHERE tm.team_id = ? AND tm.user_id = ? AND tm.status = 'active'
            ");
            $stmt->execute([$team_id, $current_user_id]);
            $user_membership = $stmt->fetch();
            $is_team_member = !empty($user_membership);
        }
    }
    
    // Takım üyelerini getir
    $stmt = $pdo->prepare("
        SELECT tm.*, tr.name as role_name, tr.display_name as role_display_name,
               tr.color as role_color, tr.priority, tr.is_management,
               u.username, u.ingame_name, u.avatar_path
        FROM team_members tm
        JOIN team_roles tr ON tm.team_role_id = tr.id
        JOIN users u ON tm.user_id = u.id
        WHERE tm.team_id = ? AND tm.status = 'active'
        ORDER BY tr.priority ASC, tm.joined_at ASC
    ");
    $stmt->execute([$team_id]);
    $team_members = $stmt->fetchAll();
    
    // Takım başvurularını getir (yetki varsa)
    $team_applications = [];
    if ($can_manage_members) {
        $stmt = $pdo->prepare("
            SELECT ta.*, u.username, u.ingame_name, u.avatar_path
            FROM team_applications ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.team_id = ? AND ta.status = 'pending'
            ORDER BY ta.applied_at ASC
        ");
        $stmt->execute([$team_id]);
        $team_applications = $stmt->fetchAll();
    }
    
    // Takım istatistikleri
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_members,
            COUNT(CASE WHEN tm.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_members
        FROM team_members tm
        WHERE tm.team_id = ? AND tm.status = 'active'
    ");
    $stmt->execute([$team_id]);
    $team_stats = $stmt->fetch();
    
    // Bekleyen başvuru sayısı
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_applications
        FROM team_applications
        WHERE team_id = ? AND status = 'pending'
    ");
    $stmt->execute([$team_id]);
    $team_stats['pending_applications'] = $stmt->fetchColumn();
    
    // Rol dağılımı
    $stmt = $pdo->prepare("
        SELECT tr.display_name, tr.color, COUNT(*) as count
        FROM team_members tm
        JOIN team_roles tr ON tm.team_role_id = tr.id
        WHERE tm.team_id = ? AND tm.status = 'active'
        GROUP BY tr.id
        ORDER BY tr.priority ASC
    ");
    $stmt->execute([$team_id]);
    $team_stats['role_distribution'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Team detail error: " . $e->getMessage());
    header('Location: /teams/?error=database_error');
    exit;
}

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
        case 'left_team':
            $success_message = 'Takımdan ayrıldınız.';
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
        case 'team_full':
            $error_message = 'Takım dolu.';
            break;
    }
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Başvuru işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Güvenlik hatası.';
    } else {
        try {
            if ($_POST['action'] === 'apply') {
                // Başvuru kontrolleri
                if ($is_team_member) {
                    throw new Exception('Zaten bu takımın üyesisiniz.');
                }
                
                if (!$team_data['is_recruitment_open']) {
                    throw new Exception('Bu takım şu anda üye alımı yapmıyor.');
                }
                
                if ($team_stats['total_members'] >= $team_data['max_members']) {
                    throw new Exception('Takım dolu.');
                }
                
                if ($has_pending_application) {
                    throw new Exception('Zaten bekleyen bir başvurunuz var.');
                }
                
                // Başvuru oluştur
                $application_message = trim($_POST['message'] ?? '');
                
                $stmt = $pdo->prepare("
                    INSERT INTO team_applications (team_id, user_id, message, status, applied_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $result = $stmt->execute([$team_id, $current_user_id, $application_message]);
                
                if ($result) {
                    header('Location: /teams/detail.php?id=' . $team_id . '&success=application_sent');
                    exit;
                } else {
                    throw new Exception('Başvuru gönderilirken hata oluştu.');
                }
                
            } elseif ($_POST['action'] === 'withdraw_application') {
                if (!$has_pending_application) {
                    throw new Exception('Geri çekilecek başvuru bulunamadı.');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE team_applications 
                    SET status = 'withdrawn'
                    WHERE team_id = ? AND user_id = ? AND status = 'pending'
                ");
                $result = $stmt->execute([$team_id, $current_user_id]);
                
                if ($result) {
                    header('Location: /teams/detail.php?id=' . $team_id . '&success=application_withdrawn');
                    exit;
                } else {
                    throw new Exception('İşlem gerçekleştirilemedi.');
                }
                
            } elseif ($_POST['action'] === 'leave_team') {
                if (!$is_team_member) {
                    throw new Exception('Bu takımın üyesi değilsiniz.');
                }
                
                if ($is_team_owner) {
                    throw new Exception('Takım lideri takımdan ayrılamaz. Önce liderliği devretmelisiniz.');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE team_members 
                    SET status = 'inactive'
                    WHERE team_id = ? AND user_id = ?
                ");
                $result = $stmt->execute([$team_id, $current_user_id]);
                
                if ($result) {
                    header('Location: /teams/?success=left_team');
                    exit;
                } else {
                    throw new Exception('İşlem gerçekleştirilemedi.');
                }
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Sayfa başlığı
$page_title = htmlspecialchars($team_data['name']) . ' - Takım Detayları';

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<!-- Teams CSS -->
<link rel="stylesheet" href="/teams/css/detail.css">

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/" class="breadcrumb-item">
            <i class="fas fa-home"></i> Ana Sayfa
        </a>
        <span class="breadcrumb-item">
            <a href="/teams/">Takımlar</a>
        </span>
        <span class="breadcrumb-item active">
            <i class="fas fa-users"></i> <?= htmlspecialchars($team_data['name']) ?>
        </span>
    </nav>

    <div class="team-detail-container">
        <!-- Team Header -->
        <div class="team-header">
            <!-- Team Banner -->
            <div class="team-banner <?= $team_data['banner_path'] ? 'has-image' : '' ?>" 
                 <?= $team_data['banner_path'] ? 'style="--banner-image: url(' . htmlspecialchars($team_data['banner_path']) . ')"' : '' ?>>
            </div>
            
            <!-- Team Info -->
            <div class="team-info">
                <!-- Team Logo -->
                <?php if ($team_data['logo_path']): ?>
                    <img src="<?= htmlspecialchars($team_data['logo_path']) ?>" 
                         alt="<?= htmlspecialchars($team_data['name']) ?>" 
                         class="team-logo">
                <?php else: ?>
                    <div class="team-logo">
                        <?= strtoupper(substr($team_data['name'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Team Main Info -->
                <div class="team-main-info">
                    <h1 class="team-name" style="color: <?= htmlspecialchars($team_data['color']) ?>">
                        <?= htmlspecialchars($team_data['name']) ?>
                    </h1>
                    
                    <div class="team-meta">
                        <?php if ($team_data['tag']): ?>
                            <span class="team-tag" style="color: <?= htmlspecialchars($team_data['color']) ?>; border-color: <?= htmlspecialchars($team_data['color']) ?>; background-color: <?= htmlspecialchars($team_data['color']) ?>20;">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($team_data['tag']) ?>
                            </span>
                        <?php endif; ?>
                        
                        <span class="team-status <?= $team_data['status'] ?>">
                            <i class="fas fa-circle"></i>
                            <?= ucfirst($team_data['status']) ?>
                        </span>
                        
                        <span class="recruitment-status <?= $team_data['is_recruitment_open'] ? 'open' : 'closed' ?>">
                            <i class="fas fa-<?= $team_data['is_recruitment_open'] ? 'unlock' : 'lock' ?>"></i>
                            <?= $team_data['is_recruitment_open'] ? 'Üye Alımı Açık' : 'Üye Alımı Kapalı' ?>
                        </span>
                    </div>
                    
                    <div class="team-stats">
                        <div class="team-stat">
                            <i class="fas fa-users"></i>
                            <span><?= $team_stats['total_members'] ?>/<?= $team_data['max_members'] ?> üye</span>
                        </div>
                        
                        <div class="team-stat">
                            <i class="fas fa-user-plus"></i>
                            <span><?= htmlspecialchars($team_data['creator_username']) ?> tarafından kuruldu</span>
                        </div>
                        
                        <div class="team-stat">
                            <i class="fas fa-calendar"></i>
                            <span><?= date('d.m.Y', strtotime($team_data['created_at'])) ?></span>
                        </div>
                        
                        <?php if ($can_manage_members && $team_stats['pending_applications'] > 0): ?>
                            <div class="team-stat">
                                <i class="fas fa-clock"></i>
                                <span><?= $team_stats['pending_applications'] ?> bekleyen başvuru</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Team Actions -->
                <div class="team-actions">
                    <?php if ($is_team_member): ?>
                        <?php if ($can_manage_members): ?>
                            <a href="/teams/manage.php?id=<?= $team_id ?>" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Takımı Yönet
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($can_edit_team): ?>
                            <a href="/teams/create.php?id=<?= $team_id ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Düzenle
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!$is_team_owner): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Takımdan ayrılmak istediğinizden emin misiniz?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="leave_team">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt"></i> Takımdan Ayrıl
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($has_pending_application): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="withdraw_application">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Başvuruyu Geri Çek
                                </button>
                            </form>
                            <span class="text-warning">
                                <i class="fas fa-clock"></i> Başvurunuz değerlendiriliyor
                            </span>
                        <?php elseif ($team_data['is_recruitment_open'] && $team_stats['total_members'] < $team_data['max_members'] && !$latest_application_status): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applicationModal">
                                <i class="fas fa-plus"></i> Takıma Başvur
                            </button>
                        <?php else: ?>
                            <span class="text-muted">
                                <i class="fas fa-lock"></i> 
                                <?= !$team_data['is_recruitment_open'] ? 'Üye alımı kapalı' : 'Takım dolu' ?>
                            </span>
                        <?php endif; ?>
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
        <div class="team-content">
            <!-- Main Content -->
            <div class="team-main-content">
                <!-- Team Description -->
                <?php if ($team_data['description']): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-info-circle"></i> 
                                Takım Hakkında
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="team-description">
                                <?= nl2br(htmlspecialchars($team_data['description'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Team Members -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-users"></i> 
                            Takım Üyeleri (<?= count($team_members) ?>)
                        </h3>
                        <?php if ($can_manage_members): ?>
                            <div class="card-actions">
                                <a href="/teams/members.php?id=<?= $team_id ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-cog"></i> Üye Yönetimi
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <?php if (empty($team_members)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>Henüz takım üyesi bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="members-list">
                                <?php foreach ($team_members as $member): ?>
                                    <div class="member-card">
                                        <?php if ($member['avatar_path']): ?>
                                            <img src="/uploads/avatars/<?= htmlspecialchars($member['avatar_path']) ?>" 
                                                 alt="<?= htmlspecialchars($member['username']) ?>" 
                                                 class="member-avatar">
                                        <?php else: ?>
                                            <div class="member-avatar">
                                                <?= strtoupper(substr($member['username'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="member-info">
                                            <div class="member-name">
                                                <a href="#" class="user-link" data-user-id="<?= $member['user_id'] ?>" 
                                                   title="<?= htmlspecialchars($member['username']) ?> profilini görüntüle"
                                                   style="color: <?= htmlspecialchars($member['role_color']) ?>;">
                                                    <?= htmlspecialchars($member['username']) ?>
                                                </a>
                                                <?php if ($member['user_id'] == $team_data['created_by_user_id']): ?>
                                                    <i class="fas fa-crown" title="Takım Lideri" style="color: <?= htmlspecialchars($member['role_color']) ?>;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($member['ingame_name']): ?>
                                                <div class="member-ingame">
                                                    <?= htmlspecialchars($member['ingame_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="member-role">
                                                <span class="role-badge" style="background-color: <?= htmlspecialchars($member['role_color']) ?>; color: white;">
                                                    <?= htmlspecialchars($member['role_display_name']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="member-status">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('d.m.Y', strtotime($member['joined_at'])) ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($can_manage_members && $member['user_id'] != $current_user_id): ?>
                                            <div class="member-actions">
                                                <a href="/teams/members.php?id=<?= $team_id ?>&member=<?= $member['user_id'] ?>" 
                                                   class="btn btn-outline-secondary btn-xs">
                                                    <i class="fas fa-cog"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Applications (if user can manage) -->
                <?php if ($can_manage_members && !empty($team_applications)): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-clock"></i> 
                                Bekleyen Başvurular (<?= count($team_applications) ?>)
                            </h3>
                            <div class="card-actions">
                                <a href="/teams/applications.php?id=<?= $team_id ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-list"></i> Tümünü Görüntüle
                                </a>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="activity-list">
                                <?php foreach (array_slice($team_applications, 0, 5) as $application): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-text">
                                                <strong><?= htmlspecialchars($application['username']) ?></strong>
                                                takıma başvurdu
                                                <?php if ($application['message']): ?>
                                                    <br><small>"<?= htmlspecialchars(substr($application['message'], 0, 100)) ?><?= strlen($application['message']) > 100 ? '...' : '' ?>"</small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-time">
                                                <?= date('d.m.Y H:i', strtotime($application['applied_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="team-sidebar">
                <!-- Team Stats -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> İstatistikler</h3>
                    </div>
                    <div class="card-content">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value"><?= $team_stats['total_members'] ?></div>
                                <div class="stat-label">Toplam Üye</div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-value"><?= $team_stats['active_members'] ?></div>
                                <div class="stat-label">Aktif Üye</div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-value"><?= $team_data['max_members'] - $team_stats['total_members'] ?></div>
                                <div class="stat-label">Boş Kapasite</div>
                            </div>
                            
                            <?php if ($can_manage_members): ?>
                                <div class="stat-item">
                                    <div class="stat-value"><?= $team_stats['pending_applications'] ?></div>
                                    <div class="stat-label">Bekleyen Başvuru</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($team_stats['role_distribution'])): ?>
                            <div class="role-distribution">
                                <h4>Rol Dağılımı</h4>
                                <?php foreach ($team_stats['role_distribution'] as $role): ?>
                                    <div class="role-item">
                                        <span class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>">
                                            <?= htmlspecialchars($role['display_name']) ?>
                                        </span>
                                        <span class="role-count"><?= $role['count'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recruitment Status -->
                <?php if (!$is_team_member): ?>
                    <div class="content-card recruitment-card">
                        <div class="card-content">
                            <div class="recruitment-status <?= $team_data['is_recruitment_open'] ? 'open' : 'closed' ?>">
                                <i class="fas fa-<?= $team_data['is_recruitment_open'] ? 'unlock' : 'lock' ?>"></i>
                                <?= $team_data['is_recruitment_open'] ? 'Üye Alımı Açık' : 'Üye Alımı Kapalı' ?>
                            </div>
                            
                            <?php if ($team_data['is_recruitment_open']): ?>
                                <div class="recruitment-info">
                                    Bu takım yeni üyeler arıyor. Başvuru yapabilir ve takıma katılabilirsiniz.
                                </div>
                                
                                <?php if (!$has_pending_application && $team_stats['total_members'] < $team_data['max_members']): ?>
                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#applicationModal">
                                        <i class="fas fa-plus"></i> Takıma Başvur
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="recruitment-info">
                                    Bu takım şu anda yeni üye kabul etmiyor.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Team Management (for managers) -->
                <?php if ($can_manage_members): ?>
                    <div class="content-card management-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tools"></i> Yönetim</h3>
                        </div>
                        <div class="card-content">
                            <div class="management-actions">
                                <a href="/teams/members.php?id=<?= $team_id ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                    <i class="fas fa-users"></i> Üye Yönetimi
                                </a>
                                
                                <a href="/teams/applications.php?id=<?= $team_id ?>" class="btn btn-outline-info btn-sm w-100 mb-2">
                                    <i class="fas fa-clipboard-list"></i> Başvuru Yönetimi
                                    <?php if ($team_stats['pending_applications'] > 0): ?>
                                        <span class="badge badge-warning"><?= $team_stats['pending_applications'] ?></span>
                                    <?php endif; ?>
                                </a>
                                
                                <?php if ($can_edit_team): ?>
                                    <a href="/teams/create.php?id=<?= $team_id ?>" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                                        <i class="fas fa-edit"></i> Takım Ayarları
                                    </a>
                                <?php endif; ?>
                                
                                <a href="/teams/roles.php?id=<?= $team_id ?>" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-user-tag"></i> Rol Yönetimi
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Application Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1" aria-labelledby="applicationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="applicationModalLabel">
                        <i class="fas fa-user-plus"></i> <?= htmlspecialchars($team_data['name']) ?> Takımına Başvur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="apply">
                    
                    <div class="form-group">
                        <label for="message" class="form-label">Başvuru Mesajı (İsteğe Bağlı)</label>
                        <textarea class="form-control" id="message" name="message" rows="4" 
                                  placeholder="Kendinizi tanıtın, takıma neden katılmak istediğinizi belirtin..."></textarea>
                        <small class="form-text text-muted">
                            Bu mesaj takım yöneticileri tarafından görülecektir.
                        </small>
                    </div>
                    
                    <div class="team-info-summary">
                        <h6>Takım Bilgileri:</h6>
                        <ul>
                            <li><strong>Maksimum Üye:</strong> <?= $team_data['max_members'] ?></li>
                            <li><strong>Mevcut Üye:</strong> <?= $team_stats['total_members'] ?></li>
                            <li><strong>Boş Kapasite:</strong> <?= $team_data['max_members'] - $team_stats['total_members'] ?></li>
                            <?php if ($team_data['tag']): ?>
                                <li><strong>Takım Türü:</strong> <?= htmlspecialchars($team_data['tag']) ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Başvuru Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functionality
document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const modal = document.getElementById('applicationModal');
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    const modalCloses = document.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
    const modalOverlay = modal;

    // Open modal
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const targetModal = document.querySelector(this.getAttribute('data-bs-target'));
            if (targetModal) {
                openModal(targetModal);
            }
        });
    });

    // Close modal
    modalCloses.forEach(closeBtn => {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal);
            }
        });
    });

    // Close modal on overlay click
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    }

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });

    function openModal(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Add show class with slight delay for animation
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        // Focus first input
        const firstInput = modal.querySelector('input, textarea, select, button');
        if (firstInput) {
            firstInput.focus();
        }
    }

    function closeModal(modal) {
        modal.classList.remove('show');
        
        // Wait for animation to complete before hiding
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }

    // Form validation for application modal
    const applicationForm = document.querySelector('#applicationModal form');
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(e) {
            const messageField = this.querySelector('#message');
            
            // Optional validation - you can add more rules here
            if (messageField && messageField.value.trim().length > 1000) {
                e.preventDefault();
                alert('Mesaj çok uzun. Lütfen 1000 karakterden kısa tutun.');
                messageField.focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });
    }

    // Character counter for message textarea
    const messageTextarea = document.querySelector('#message');
    if (messageTextarea) {
        // Create character counter
        const counterDiv = document.createElement('div');
        counterDiv.className = 'character-counter';
        counterDiv.style.cssText = 'text-align: right; font-size: 0.8rem; color: var(--light-grey); margin-top: 0.25rem;';
        messageTextarea.parentNode.appendChild(counterDiv);
        
        function updateCounter() {
            const current = messageTextarea.value.length;
            const max = 1000;
            counterDiv.textContent = `${current}/${max} karakter`;
            
            if (current > max * 0.9) {
                counterDiv.style.color = 'var(--red)';
            } else if (current > max * 0.7) {
                counterDiv.style.color = '#ffc107';
            } else {
                counterDiv.style.color = 'var(--light-grey)';
            }
        }
        
        messageTextarea.addEventListener('input', updateCounter);
        updateCounter(); // Initial count
    }

    // Confirm dialogs for dangerous actions
    // Leave team confirmation
    const leaveTeamForms = document.querySelectorAll('form[onsubmit*="confirm"]');
    leaveTeamForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Takımdan ayrılmak istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                e.preventDefault();
            }
        });
    });
    
    // Withdraw application confirmation
    const withdrawForms = document.querySelectorAll('input[value="withdraw_application"]');
    withdrawForms.forEach(input => {
        input.closest('form').addEventListener('submit', function(e) {
            if (!confirm('Başvurunuzu geri çekmek istediğinizden emin misiniz?')) {
                e.preventDefault();
            }
        });
    });

    // Auto-refresh for real-time updates (optional)
    <?php if ($can_manage_members): ?>
        // Refresh page every 5 minutes if user can manage members (to see new applications)
        setTimeout(function() {
            if (document.hidden === false) {
                location.reload();
            }
        }, 300000); // 5 minutes
    <?php endif; ?>
});
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>

<!-- User Popover Component -->
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>