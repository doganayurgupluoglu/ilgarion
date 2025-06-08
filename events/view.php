<?php
// events/view.php - Etkinlik detay sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/Parsedown.php';

// Events layout system include
require_once 'includes/events_layout.php';

// Session kontrolü - Ziyaretçiler için isteğe bağlı
$current_user_id = null;
$is_logged_in = false;

if (isset($_SESSION['user_id'])) {
    try {
        check_user_session_validity();
        $current_user_id = $_SESSION['user_id'];

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
        $is_logged_in = true;
        $is_approved = is_user_approved();
    } catch (Exception $e) {
        $is_approved = false;
    }
} else {
    $is_approved = false;
}

// Etkinlik ID kontrolü
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$event_id) {
    $_SESSION['error_message'] = "Geçersiz etkinlik ID.";
    header('Location: index.php');
    exit;
}

// Yetki kontrolleri
$can_edit_all = $is_logged_in && has_permission($pdo, 'event.edit_all');
$can_delete_all = $is_logged_in && has_permission($pdo, 'event.delete_all');
$can_join_events = $is_logged_in && $is_approved && has_permission($pdo, 'event.join');

try {
    // Etkinlik detaylarını getir
    $event_stmt = $pdo->prepare("
        SELECT e.*, 
               u.username as creator_username,
               u.id as creator_user_id,
               u.ingame_name as creator_ingame_name,
               ls.set_name as suggested_loadout_name,
               ls.id as loadout_id,
               (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status = 'joined') as current_participants
        FROM events e
        LEFT JOIN users u ON e.created_by_user_id = u.id
        LEFT JOIN loadout_sets ls ON e.suggested_loadout_id = ls.id
        WHERE e.id = :event_id AND e.status != 'deleted'
    ");
    
    $event_stmt->execute([':event_id' => $event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $_SESSION['error_message'] = "Etkinlik bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    // Görünürlük kontrolü
    $can_view = false;
    
    switch ($event['visibility']) {
        case 'public':
            $can_view = true;
            break;
        case 'members_only':
            $can_view = $is_logged_in;
            break;
        case 'role_restricted':
            if ($is_logged_in) {
                // Kullanıcının etkinliği görme yetkisi var mı?
                $role_check_stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM user_roles ur
                    JOIN event_visibility_roles evr ON ur.role_id = evr.role_id
                    WHERE ur.user_id = :user_id AND evr.event_id = :event_id
                ");
                $role_check_stmt->execute([':user_id' => $current_user_id, ':event_id' => $event_id]);
                $can_view = $role_check_stmt->fetchColumn() > 0;
            }
            break;
    }
    
    if (!$can_view) {
        $_SESSION['error_message'] = "Bu etkinliği görüntüleme yetkiniz bulunmuyor.";
        header('Location: index.php');
        exit;
    }

} catch (PDOException $e) {
    error_log("Event view error: " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlik yüklenirken bir hata oluştu.";
    header('Location: index.php');
    exit;
}

// Kullanıcının katılım durumu
$user_participation = null;
$user_joined_roles = [];

if ($is_logged_in) {
    try {
        // Genel katılım durumu
        $participation_stmt = $pdo->prepare("
            SELECT ep.*, er.role_name, er.icon_class
            FROM event_participants ep
            LEFT JOIN event_roles er ON ep.event_role_id = er.id
            WHERE ep.event_id = :event_id AND ep.user_id = :user_id
        ");
        $participation_stmt->execute([':event_id' => $event_id, ':user_id' => $current_user_id]);
        $user_joined_roles = $participation_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($user_joined_roles)) {
            $user_participation = $user_joined_roles[0]; // İlk katılım durumu
        }
    } catch (PDOException $e) {
        error_log("User participation check error: " . $e->getMessage());
    }
}

// Etkinlik rollerini getir (eğer rol seçimi gerekliyse)
$event_roles = [];
$role_participants = [];

if ($event['requires_role_selection']) {
    try {
        // Etkinlik için rol listesi
        $roles_stmt = $pdo->prepare("
            SELECT DISTINCT er.id, er.role_name, er.icon_class, er.role_description,
                   COUNT(ep.id) as participant_count,
                   (CASE WHEN :user_id IS NOT NULL THEN 
                        EXISTS(SELECT 1 FROM event_participants ep2 
                               WHERE ep2.event_id = :event_id AND ep2.user_id = :user_id 
                               AND ep2.event_role_id = er.id AND ep2.status = 'joined')
                    ELSE 0 END) as user_joined
            FROM event_participants ep
            JOIN event_roles er ON ep.event_role_id = er.id
            WHERE ep.event_id = :event_id AND ep.status = 'joined'
            GROUP BY er.id, er.role_name, er.icon_class, er.role_description
            ORDER BY er.role_name ASC
        ");
        
        $params = [
            ':event_id' => $event_id,
            ':user_id' => $current_user_id
        ];
        
        $roles_stmt->execute($params);
        $event_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Her rol için katılımcıları getir
        foreach ($event_roles as &$role) {
            $participants_stmt = $pdo->prepare("
                SELECT ep.*, u.username, u.ingame_name, u.avatar_url,
                       ur.role_name as user_role, ur.color as user_role_color
                FROM event_participants ep
                JOIN users u ON ep.user_id = u.id
                LEFT JOIN user_roles ur ON u.role_id = ur.id
                WHERE ep.event_id = :event_id AND ep.event_role_id = :role_id 
                AND ep.status = 'joined'
                ORDER BY ep.joined_at ASC
            ");
            $participants_stmt->execute([':event_id' => $event_id, ':role_id' => $role['id']]);
            $role['participants'] = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        error_log("Event roles fetch error: " . $e->getMessage());
        $event_roles = [];
    }
} else {
    // Rol seçimi gereksizse, genel katılımcıları getir
    try {
        $participants_stmt = $pdo->prepare("
            SELECT ep.*, u.username, u.ingame_name, u.avatar_url,
                   ur.role_name as user_role, ur.color as user_role_color
            FROM event_participants ep
            JOIN users u ON ep.user_id = u.id
            LEFT JOIN user_roles ur ON u.role_id = ur.id
            WHERE ep.event_id = :event_id AND ep.status = 'joined'
            ORDER BY ep.joined_at ASC
        ");
        $participants_stmt->execute([':event_id' => $event_id]);
        $general_participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("General participants fetch error: " . $e->getMessage());
        $general_participants = [];
    }
}

// Markdown parser
$parsedown = new Parsedown();
$parsedown->setSafeMode(true);
$parsedown->setBreaksEnabled(true);

$page_title = htmlspecialchars($event['title']) . " - Etkinlik Detayı";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => $event['title'], 'url' => '', 'icon' => 'fas fa-calendar-day']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Layout başlat
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="css/events_sidebar.css">
<link rel="stylesheet" href="css/event_view.css">

<div class="event-view-container">
    <!-- Event Header -->
    <div class="event-header">
        <?php if ($event['thumbnail_path']): ?>
            <div class="event-thumbnail">
                <img src="<?= htmlspecialchars($event['thumbnail_path']) ?>" alt="<?= htmlspecialchars($event['title']) ?>">
            </div>
        <?php endif; ?>
        
        <div class="event-header-content">
            <div class="event-meta-tags">
                <span class="event-type-badge type-<?= strtolower($event['event_type']) ?>">
                    <?= htmlspecialchars($event['event_type']) ?>
                </span>
                <span class="event-status-badge status-<?= $event['status'] ?>">
                    <?php
                    switch($event['status']) {
                        case 'active': echo 'Aktif'; break;
                        case 'completed': echo 'Tamamlandı'; break;
                        case 'cancelled': echo 'İptal Edildi'; break;
                        default: echo ucfirst($event['status']);
                    }
                    ?>
                </span>
                <span class="visibility-badge visibility-<?= $event['visibility'] ?>">
                    <?php
                    switch($event['visibility']) {
                        case 'public': echo 'Herkese Açık'; break;
                        case 'members_only': echo 'Sadece Üyeler'; break;
                        case 'role_restricted': echo 'Rol Kısıtlı'; break;
                    }
                    ?>
                </span>
            </div>
            
            <h1 class="event-title"><?= htmlspecialchars($event['title']) ?></h1>
            
            <div class="event-quick-info">
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= date('d.m.Y H:i', strtotime($event['event_datetime'])) ?></span>
                </div>
                
                <?php if ($event['location']): ?>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= htmlspecialchars($event['location']) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <span>
                        <?= $event['current_participants'] ?>
                        <?php if ($event['max_participants']): ?>
                            / <?= $event['max_participants'] ?>
                        <?php endif; ?>
                        katılımcı
                    </span>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-user-tie"></i>
                    <span>
                        Düzenleyen: 
                        <a href="/profile.php?user=<?= $event['creator_user_id'] ?>" class="creator-link">
                            <?= htmlspecialchars($event['creator_ingame_name'] ?: $event['creator_username']) ?>
                        </a>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="event-actions">
            <?php if ($is_logged_in && ($event['created_by_user_id'] == $current_user_id || $can_edit_all)): ?>
                <a href="create.php?edit=<?= $event['id'] ?>" class="btn-action-secondary">
                    <i class="fas fa-edit"></i> Düzenle
                </a>
            <?php endif; ?>
            
            <?php if ($is_logged_in && ($event['created_by_user_id'] == $current_user_id || $can_delete_all)): ?>
                <button class="btn-action-danger delete-event" 
                        data-event-id="<?= $event['id'] ?>" 
                        data-event-title="<?= htmlspecialchars($event['title']) ?>">
                    <i class="fas fa-trash"></i> Sil
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Content -->
    <div class="event-content">
        <!-- Description -->
        <div class="content-section">
            <h2><i class="fas fa-file-text"></i> Etkinlik Açıklaması</h2>
            <div class="event-description">
                <?= $parsedown->text($event['description']) ?>
            </div>
        </div>

        <!-- Suggested Loadout -->
        <?php if ($event['suggested_loadout_name']): ?>
            <div class="content-section">
                <h2><i class="fas fa-shield-alt"></i> Önerilen Teçhizat</h2>
                <div class="suggested-loadout">
                    <a href="/loadouts/view.php?id=<?= $event['loadout_id'] ?>" class="loadout-link">
                        <i class="fas fa-external-link-alt"></i>
                        <?= htmlspecialchars($event['suggested_loadout_name']) ?>
                    </a>
                    <p>Bu etkinlik için önerilen teçhizat setini görüntülemek için tıklayın.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Participation Section -->
        <?php if ($can_join_events && $event['status'] === 'active'): ?>
            <div class="content-section" id="participation-section">
                <h2><i class="fas fa-user-check"></i> Katılım Bildirme</h2>
                
                <div class="participation-container">
                    <!-- Available Roles -->
                    <div class="available-roles">
                        <h3><i class="fas fa-user-tag"></i> Mevcut Roller</h3>
                        
                        <?php
                        // Tüm rolleri getir
                        try {
                            $all_roles_stmt = $pdo->prepare("
                                SELECT DISTINCT er.id, er.role_name, er.icon_class, er.role_description,
                                       COUNT(ep.id) as current_participants,
                                       MAX(CASE WHEN ep.user_id = ? AND ep.status = 'joined' THEN 1 ELSE 0 END) as user_joined
                                FROM event_roles er
                                LEFT JOIN event_participants ep ON er.id = ep.event_role_id AND ep.event_id = ? AND ep.status = 'joined'
                                WHERE er.is_active = 1 
                                AND (er.visibility = 'public' OR (er.visibility = 'members_only' AND ? = 1))
                                GROUP BY er.id, er.role_name, er.icon_class, er.role_description
                                ORDER BY er.role_name ASC
                            ");
                            $all_roles_stmt->execute([$current_user_id, $event_id, $is_approved ? 1 : 0]);
                            $available_roles = $all_roles_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $available_roles = [];
                        }
                        ?>
                        
                        <div class="roles-grid">
                            <?php foreach ($available_roles as $role): ?>
                                <div class="role-card <?= $role['user_joined'] ? 'user-joined' : '' ?>" 
                                     data-role-id="<?= $role['id'] ?>"
                                     data-event-id="<?= $event_id ?>">
                                    <div class="role-header">
                                        <div class="role-info">
                                            <i class="<?= htmlspecialchars($role['icon_class']) ?>"></i>
                                            <span class="role-name"><?= htmlspecialchars($role['role_name']) ?></span>
                                        </div>
                                        <div class="role-count">
                                            <span class="current"><?= $role['current_participants'] ?></span>
                                            <span class="separator">/</span>
                                            <span class="max">∞</span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($role['role_description']): ?>
                                        <div class="role-description">
                                            <?= nl2br(htmlspecialchars($role['role_description'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="role-actions">
                                        <?php if ($role['user_joined']): ?>
                                            <button class="btn-role-status joined" 
                                                    data-action="leave" 
                                                    data-role-id="<?= $role['id'] ?>">
                                                <i class="fas fa-check"></i> Katıldınız
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-role-status available" 
                                                    data-action="join" 
                                                    data-role-id="<?= $role['id'] ?>">
                                                <i class="fas fa-user-plus"></i> Katıl
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Participation Status Buttons -->
                    <div class="participation-status">
                        <?php if (empty($available_roles)): ?>
                            <!-- Rol yoksa normal katılım -->
                            <h3><i class="fas fa-clipboard-check"></i> Katılım Durumunuz</h3>
                            
                            <?php
                            // Kullanıcının genel katılım durumu
                            $user_status = 'not_responded';
                            if ($user_participation) {
                                $user_status = $user_participation['status'];
                            }
                            ?>
                            
                            <div class="status-buttons">
                                <button class="btn-status <?= $user_status === 'joined' ? 'active' : '' ?>" 
                                        data-status="joined" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Katılıyorum</span>
                                </button>
                                
                                <button class="btn-status <?= $user_status === 'maybe' ? 'active' : '' ?>" 
                                        data-status="maybe" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-question-circle"></i>
                                    <span>Belki Katılırım</span>
                                </button>
                                
                                <button class="btn-status <?= $user_status === 'declined' ? 'active' : '' ?>" 
                                        data-status="declined" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Katılmıyorum</span>
                                </button>
                            </div>
                            
                            <?php if ($user_participation): ?>
                                <div class="current-status">
                                    <p>
                                        <?php
                                        switch($user_status) {
                                            case 'joined':
                                                echo '<i class="fas fa-check-circle" style="color: var(--turquase);"></i> Bu etkinliğe katılacağınızı bildirdiniz.';
                                                break;
                                            case 'maybe':
                                                echo '<i class="fas fa-question-circle" style="color: var(--gold);"></i> Bu etkinliğe belki katılacağınızı bildirdiniz.';
                                                break;
                                            case 'declined':
                                                echo '<i class="fas fa-times-circle" style="color: var(--red);"></i> Bu etkinliğe katılmayacağınızı bildirdiniz.';
                                                break;
                                        }
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Rol varsa sadece belki/hayır butonları -->
                            <h3><i class="fas fa-clipboard-check"></i> Genel Katılım Durumu</h3>
                            <p class="role-info-text">
                                <i class="fas fa-info-circle"></i>
                                Bu etkinlik için roller belirlenmiş. Katılmak için yukarıdan bir rol seçin.
                            </p>
                            
                            <?php
                            $user_status = 'not_responded';
                            if ($user_participation) {
                                $user_status = $user_participation['status'];
                            }
                            ?>
                            
                            <div class="status-buttons limited">
                                <button class="btn-status <?= $user_status === 'maybe' ? 'active' : '' ?>" 
                                        data-status="maybe" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-question-circle"></i>
                                    <span>Belki Katılırım</span>
                                </button>
                                
                                <button class="btn-status <?= $user_status === 'declined' ? 'active' : '' ?>" 
                                        data-status="declined" data-event-id="<?= $event_id ?>">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Katılmıyorum</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Participants Lists -->
        <div class="content-section" id="participants-section">
            <h2><i class="fas fa-users"></i> Katılımcı Listeleri</h2>
            
            <?php
            // Katılımcıları durum ve role göre grupla
            try {
                $participants_stmt = $pdo->prepare("
                    SELECT ep.*, u.username, u.ingame_name, u.avatar_url,
                           ur.role_name as user_role, ur.color as user_role_color,
                           er.role_name as event_role_name, er.icon_class as event_role_icon
                    FROM event_participants ep
                    JOIN users u ON ep.user_id = u.id
                    LEFT JOIN user_roles ur ON u.role_id = ur.id
                    LEFT JOIN event_roles er ON ep.event_role_id = er.id
                    WHERE ep.event_id = ?
                    ORDER BY 
                        CASE ep.status 
                            WHEN 'joined' THEN 1 
                            WHEN 'maybe' THEN 2 
                            WHEN 'declined' THEN 3 
                        END,
                        er.role_name ASC,
                        ep.joined_at ASC
                ");
                $participants_stmt->execute([$event_id]);
                $all_participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Gruplama
                $participants_by_status = [
                    'joined' => [],
                    'maybe' => [],
                    'declined' => []
                ];
                
                foreach ($all_participants as $participant) {
                    $participants_by_status[$participant['status']][] = $participant;
                }
                
            } catch (PDOException $e) {
                $participants_by_status = ['joined' => [], 'maybe' => [], 'declined' => []];
            }
            ?>
            
            <div class="participants-tabs">
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="joined">
                        <i class="fas fa-check-circle"></i>
                        Katılıyor (<span id="joined-count"><?= count($participants_by_status['joined']) ?></span>)
                    </button>
                    <button class="tab-btn" data-tab="maybe">
                        <i class="fas fa-question-circle"></i>
                        Belki (<span id="maybe-count"><?= count($participants_by_status['maybe']) ?></span>)
                    </button>
                    <button class="tab-btn" data-tab="declined">
                        <i class="fas fa-times-circle"></i>
                        Katılmıyor (<span id="declined-count"><?= count($participants_by_status['declined']) ?></span>)
                    </button>
                </div>
                
                <!-- Katılıyor Tab -->
                <div class="tab-content active" id="joined-tab">
                    <?php if (!empty($participants_by_status['joined'])): ?>
                        <?php
                        // Role göre grupla
                        $joined_by_role = [];
                        foreach ($participants_by_status['joined'] as $participant) {
                            $role_key = $participant['event_role_id'] ? $participant['event_role_id'] : 'no_role';
                            if (!isset($joined_by_role[$role_key])) {
                                $joined_by_role[$role_key] = [
                                    'role_name' => $participant['event_role_name'] ?: 'Genel Katılım',
                                    'role_icon' => $participant['event_role_icon'] ?: 'fas fa-user',
                                    'participants' => []
                                ];
                            }
                            $joined_by_role[$role_key]['participants'][] = $participant;
                        }
                        ?>
                        
                        <?php foreach ($joined_by_role as $role_group): ?>
                            <div class="role-group">
                                <h4 class="role-group-title">
                                    <i class="<?= htmlspecialchars($role_group['role_icon']) ?>"></i>
                                    <?= htmlspecialchars($role_group['role_name']) ?>
                                    <span class="role-count">(<?= count($role_group['participants']) ?>)</span>
                                </h4>
                                
                                <div class="participants-grid">
                                    <?php foreach ($role_group['participants'] as $participant): ?>
                                        <div class="participant-card">
                                            <div class="participant-avatar">
                                                <?php if ($participant['avatar_url']): ?>
                                                    <img src="<?= htmlspecialchars($participant['avatar_url']) ?>" 
                                                         alt="<?= htmlspecialchars($participant['username']) ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-user"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="participant-info">
                                                <a href="/profile.php?user=<?= $participant['user_id'] ?>" class="participant-name">
                                                    <?= htmlspecialchars($participant['ingame_name'] ?: $participant['username']) ?>
                                                </a>
                                                <?php if ($participant['user_role']): ?>
                                                    <span class="participant-role" style="color: <?= htmlspecialchars($participant['user_role_color']) ?>">
                                                        <?= htmlspecialchars($participant['user_role']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="join-date">
                                                    <?= date('d.m.Y H:i', strtotime($participant['joined_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-participants">
                            <p>Henüz kimse bu etkinliğe katılacağını bildirmedi.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Belki Tab -->
                <div class="tab-content" id="maybe-tab">
                    <?php if (!empty($participants_by_status['maybe'])): ?>
                        <div class="participants-grid">
                            <?php foreach ($participants_by_status['maybe'] as $participant): ?>
                                <div class="participant-card maybe">
                                    <div class="participant-avatar">
                                        <?php if ($participant['avatar_url']): ?>
                                            <img src="<?= htmlspecialchars($participant['avatar_url']) ?>" 
                                                 alt="<?= htmlspecialchars($participant['username']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="participant-info">
                                        <a href="/profile.php?user=<?= $participant['user_id'] ?>" class="participant-name">
                                            <?= htmlspecialchars($participant['ingame_name'] ?: $participant['username']) ?>
                                        </a>
                                        <?php if ($participant['user_role']): ?>
                                            <span class="participant-role" style="color: <?= htmlspecialchars($participant['user_role_color']) ?>">
                                                <?= htmlspecialchars($participant['user_role']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="join-date">
                                            <?= date('d.m.Y H:i', strtotime($participant['joined_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-participants">
                            <p>Kimse "belki katılırım" yanıtını vermedi.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Katılmıyor Tab -->
                <div class="tab-content" id="declined-tab">
                    <?php if (!empty($participants_by_status['declined'])): ?>
                        <div class="participants-grid">
                            <?php foreach ($participants_by_status['declined'] as $participant): ?>
                                <div class="participant-card declined">
                                    <div class="participant-avatar">
                                        <?php if ($participant['avatar_url']): ?>
                                            <img src="<?= htmlspecialchars($participant['avatar_url']) ?>" 
                                                 alt="<?= htmlspecialchars($participant['username']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="participant-info">
                                        <a href="/profile.php?user=<?= $participant['user_id'] ?>" class="participant-name">
                                            <?= htmlspecialchars($participant['ingame_name'] ?: $participant['username']) ?>
                                        </a>
                                        <?php if ($participant['user_role']): ?>
                                            <span class="participant-role" style="color: <?= htmlspecialchars($participant['user_role_color']) ?>">
                                                <?= htmlspecialchars($participant['user_role']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="join-date">
                                            <?= date('d.m.Y H:i', strtotime($participant['joined_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-participants">
                            <p>Kimse "katılmıyorum" yanıtını vermedi.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Event Modal -->
<?php if ($is_logged_in && ($event['created_by_user_id'] == $current_user_id || $can_delete_all)): ?>
    <div id="deleteEventModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--red);"></i> Etkinlik Silme Onayı</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong id="deleteEventName"></strong> etkinliğini silmek istediğinizden emin misiniz?</p>
                <p style="color: var(--red); font-size: 0.9rem;">
                    <i class="fas fa-warning"></i> Bu işlem geri alınamaz ve tüm katılımcı verileri silinecektir.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action-secondary" onclick="closeDeleteModal()">İptal</button>
                <button type="button" class="btn-action-danger" onclick="confirmDeleteEvent()">Sil</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- CSRF Token -->
<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">

<!-- JavaScript -->
<script src="/events/js/event_view.js"></script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>