<?php
// /events/roles/view.php - Etkinlik rol detay sayfası - MEVCUT DB YAPISINA GÖRE

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
    try {
        check_user_session_validity();
        $current_user_id = $_SESSION['user_id'];
        $is_logged_in = true;
        $is_approved = is_user_approved();
    } catch (Exception $e) {
        $is_approved = false;
    }
} else {
    $is_approved = false;
}

// Rol ID kontrolü
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$role_id) {
    $_SESSION['error_message'] = "Geçersiz rol ID.";
    header('Location: index.php');
    exit;
}

// Yetki kontrolleri
$can_edit_all = $is_logged_in && has_permission($pdo, 'event_role.edit_all');
$can_delete_all = $is_logged_in && has_permission($pdo, 'event_role.delete_all');
$can_view_private = $is_logged_in && has_permission($pdo, 'event_role.view_private');

try {
    // MEVCUT TABLO YAPISINA GÖRE rol detayları sorgusu
    $role_stmt = $pdo->prepare("
        SELECT er.id, er.role_name, er.role_description, er.role_icon, 
               er.suggested_loadout_id, er.created_at
        FROM event_roles er
        WHERE er.id = :role_id
    ");
    
    $role_stmt->execute([':role_id' => $role_id]);
    $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        $_SESSION['error_message'] = "Rol bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    // Teçhizat seti bilgisi
    $role['loadout_name'] = null;
    if ($role['suggested_loadout_id']) {
        $loadout_stmt = $pdo->prepare("SELECT set_name FROM loadout_sets WHERE id = :id");
        $loadout_stmt->execute([':id' => $role['suggested_loadout_id']]);
        $loadout_result = $loadout_stmt->fetch(PDO::FETCH_ASSOC);
        if ($loadout_result) {
            $role['loadout_name'] = $loadout_result['set_name'];
        }
    }
    
    // Usage count
    $usage_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_role_slots WHERE role_id = :role_id");
    $usage_stmt->execute([':role_id' => $role_id]);
    $role['usage_count'] = $usage_stmt->fetchColumn();
    
    // Requirements count
    $req_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_role_requirements WHERE role_id = :role_id");
    $req_stmt->execute([':role_id' => $role_id]);
    $role['requirements_count'] = $req_stmt->fetchColumn();
    
    // MEVCUT TABLO YAPISINA GÖRE rol gereksinimlerini getir
    $requirements = [];
    try {
        $requirements_stmt = $pdo->prepare("
            SELECT err.role_id, err.skill_tag_id, st.tag_name
            FROM event_role_requirements err
            JOIN skill_tags st ON err.skill_tag_id = st.id
            WHERE err.role_id = :role_id
            ORDER BY st.tag_name ASC
        ");
        $requirements_stmt->execute([':role_id' => $role_id]);
        $requirements = $requirements_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Requirements fetch error: " . $e->getMessage());
        $requirements = [];
    }
    
    // MEVCUT TABLO YAPISINA GÖRE kullanıcının skill tag'lerini kontrol et
    $user_skill_tags = [];
    $user_meets_requirements = true;
    
    if ($is_logged_in && !empty($requirements)) {
        try {
            // Kullanıcının sahip olduğu skill tag'leri al
            $user_tags_stmt = $pdo->prepare("
                SELECT ust.skill_tag_id
                FROM user_skill_tags ust
                WHERE ust.user_id = :user_id
            ");
            $user_tags_stmt->execute([':user_id' => $current_user_id]);
            $user_tag_results = $user_tags_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $user_skill_tags = array_map('intval', $user_tag_results);
            
            // Gereksinimleri kontrol et - Her gereksinim için ayrı kontrol
            $required_tag_ids = array_column($requirements, 'skill_tag_id');
            $required_tag_ids = array_map('intval', $required_tag_ids);
            
            // Kullanıcının tüm gerekli tag'lere sahip olup olmadığını kontrol et
            $missing_tags = array_diff($required_tag_ids, $user_skill_tags);
            $user_meets_requirements = empty($missing_tags);
            
        } catch (PDOException $e) {
            error_log("User skill tags fetch error: " . $e->getMessage());
            $user_meets_requirements = false;
        }
    }
    
    // Bu rolün kullanıldığı etkinlikleri getir
    $recent_events = [];
    try {
        $events_stmt = $pdo->prepare("
            SELECT DISTINCT e.id, e.event_title as title, e.event_date as event_datetime, e.status,
                   ers.slot_count, 
                   (SELECT COUNT(*) FROM event_participations ep 
                    WHERE ep.event_id = e.id AND ep.role_slot_id = ers.id 
                    AND ep.participation_status = 'confirmed') as active_participants
            FROM events e
            JOIN event_role_slots ers ON e.id = ers.event_id
            WHERE ers.role_id = :role_id
            ORDER BY e.event_date DESC
            LIMIT 10
        ");
        $events_stmt->execute([':role_id' => $role_id]);
        $recent_events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Recent events fetch error: " . $e->getMessage());
        $recent_events = [];
    }
    
} catch (PDOException $e) {
    error_log("Role view PDO error: " . $e->getMessage());
    $_SESSION['error_message'] = "Rol detayları yüklenirken bir hata oluştu.";
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    error_log("Role view general error: " . $e->getMessage());
    $_SESSION['error_message'] = "Beklenmeyen bir hata oluştu.";
    header('Location: index.php');
    exit;
}

// Sayfa başlığı ve breadcrumb
$page_title = htmlspecialchars($role['role_name']) . " - Etkinlik Rolü";
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Roller', 'url' => '/events/roles/', 'icon' => 'fas fa-user-tag'],
    ['text' => $role['role_name'], 'url' => '', 'icon' => $role['role_icon'] ?? 'fas fa-user']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="../css/events_sidebar.css">
<link rel="stylesheet" href="css/roles.css">
<link rel="stylesheet" href="css/view.css">

<div class="loadout-view-container">
    <!-- Rol Başlığı ve Eylemler -->
    <div class="set-meta">
        <div class="set-header">
            <div class="set-info">
                <div class="set-title-group">
                    <h1><i class="<?= htmlspecialchars($role['role_icon']) ?>"></i><?= htmlspecialchars($role['role_name']) ?></h1>
                    <div class="set-meta-info">
                        <?php if ($role['usage_count'] > 0): ?>
                            <span class="usage-count">
                                <i class="fas fa-calendar"></i>
                                <?= $role['usage_count'] ?> etkinlikte kullanıldı
                            </span>
                        <?php endif; ?>

                        <span class="creation-date">
                            <i class="fas fa-clock"></i>
                            <?= date('d.m.Y H:i', strtotime($role['created_at'])) ?>
                        </span>
                        
                        <?php if (!empty($role['loadout_name'])): ?>
                            <span class="loadout-info">
                                <i class="fas fa-shield-alt"></i>
                                Önerilen Set: 
                                <a href="../loadouts/view.php?id=<?= $role['suggested_loadout_id'] ?>" 
                                   style="color: var(--gold); text-decoration: none; font-weight: 500;">
                                    <?= htmlspecialchars($role['loadout_name']) ?>
                                </a>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Eylem Butonları - Şimdilik basit yetki kontrolleri -->
            <?php if ($is_logged_in): ?>
                <div class="set-actions">
                    <?php if ($can_edit_all): ?>
                        <a href="create.php?id=<?= $role['id'] ?>" class="btn-action-secondary">
                            <i class="fas fa-edit"></i>
                            Düzenle
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($can_delete_all): ?>
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Rol İçeriği -->
    <div class="loadout-content">
        <!-- Rol Açıklaması -->
        <div class="content-section">
            <h2><i class="fas fa-info-circle"></i> Rol Açıklaması</h2>
            <div class="role-description">
                <p><?= nl2br(htmlspecialchars($role['role_description'])) ?></p>
            </div>
        </div>

        <!-- MEVCUT TABLO YAPISINA GÖRE Rol Gereksinimleri -->
        <?php if (!empty($requirements)): ?>
            <div class="content-section">
                <h2>
                    <i class="fas fa-check-circle"></i> 
                    Rol Gereksinimleri
                    <?php if ($is_logged_in): ?>
                        <span style="font-size: 0.8rem; margin-left: 1rem; padding: 0.25rem 0.75rem; border-radius: 15px; <?= $user_meets_requirements ? 'background: rgba(61, 166, 162, 0.1); color: var(--turquase); border: 1px solid var(--turquase);' : 'background: rgba(235, 0, 0, 0.1); color: var(--red); border: 1px solid var(--red);' ?>">
                            <?= $user_meets_requirements ? 'Tüm Gereksinimleri Karşılıyorsunuz' : 'Bazı Gereksinimleri Karşılamıyorsunuz' ?>
                        </span>
                    <?php endif; ?>
                </h2>
                
                <div class="requirements-list">
                    <?php foreach ($requirements as $req): ?>
                        <div class="requirement-item required">
                            <div class="requirement-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="requirement-content">
                                <span class="requirement-label">
                                    Skill Tag: <?= htmlspecialchars($req['tag_name']) ?>
                                </span>
                                <span class="requirement-type">
                                    Zorunlu
                                    <?php if ($is_logged_in): ?>
                                        <?php
                                        $has_tag = in_array((int)$req['skill_tag_id'], $user_skill_tags);
                                        ?>
                                        - 
                                        <span style="<?= $has_tag ? 'color: var(--turquase);' : 'color: var(--red);' ?>">
                                            <?= $has_tag ? '✓ Sahipsiniz' : '✗ Sahip Değilsiniz' ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Son Kullanılan Etkinlikler -->
        <?php if (!empty($recent_events)): ?>
            <div class="content-section">
                <h2><i class="fas fa-calendar-alt"></i> Bu Rolün Kullanıldığı Etkinlikler</h2>
                <div class="recent-events">
                    <?php foreach ($recent_events as $event): ?>
                        <div class="event-card">
                            <div class="event-info">
                                <h4>
                                    <a href="../detail.php?id=<?= $event['id'] ?>">
                                        <?= htmlspecialchars($event['title']) ?>
                                    </a>
                                </h4>
                                <div class="event-meta">
                                    <span class="event-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d.m.Y H:i', strtotime($event['event_datetime'])) ?>
                                    </span>
                                    <span class="event-status status-<?= $event['status'] ?>">
                                        <?php
                                        switch($event['status']) {
                                            case 'published': echo 'Yayınlandı'; break;
                                            case 'draft': echo 'Taslak'; break;
                                            case 'cancelled': echo 'İptal Edildi'; break;
                                            case 'completed': echo 'Tamamlandı'; break;
                                            default: echo ucfirst($event['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="event-participants">
                                    <i class="fas fa-users"></i>
                                    <?= $event['active_participants'] ?? 0 ?> / <?= $event['slot_count'] ?> katılımcı
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rol Silme Modal -->
<?php if ($is_logged_in && $can_delete_all): ?>
    <div id="deleteRoleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--red);"></i> Rol Silme Onayı</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong id="deleteRoleName"></strong> rolünü silmek istediğinizden emin misiniz?</p>
                <p style="color: var(--red); font-size: 0.9rem;">
                    <i class="fas fa-warning"></i> Bu işlem geri alınamaz ve rol tüm etkinliklerden kaldırılacaktır.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action-secondary" onclick="closeDeleteModal()">İptal</button>
                <button type="button" class="btn-action-danger" onclick="confirmDeleteRole()">Sil</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Rol silme işlemleri
<?php if ($is_logged_in && $can_delete_all): ?>
    let currentDeleteRoleId = null;

    // Silme butonuna tıklama
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-role')) {
            e.preventDefault();
            const button = e.target.closest('.delete-role');
            currentDeleteRoleId = button.dataset.roleId;
            const roleName = button.dataset.roleName;
            
            document.getElementById('deleteRoleName').textContent = roleName;
            document.getElementById('deleteRoleModal').style.display = 'block';
        }
    });

    // Modal kapatma
    function closeDeleteModal() {
        document.getElementById('deleteRoleModal').style.display = 'none';
        currentDeleteRoleId = null;
    }

    // Modal dışına tıklama ile kapatma
    window.onclick = function(event) {
        const modal = document.getElementById('deleteRoleModal');
        if (event.target === modal) {
            closeDeleteModal();
        }
    }

    // Silme onaylama
    function confirmDeleteRole() {
        if (!currentDeleteRoleId) return;

        fetch('actions/delete_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                role_id: currentDeleteRoleId,
                csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showNotification(data.message, 'error');
            }
            closeDeleteModal();
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Bir hata oluştu', 'error');
            closeDeleteModal();
        });
    }
<?php endif; ?>

// Bildirim gösterme fonksiyonu
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--card-bg);
        color: var(--lighter-grey);
        border: 1px solid var(--border-1);
        border-radius: 8px;
        padding: 1rem;
        z-index: 10000;
        max-width: 300px;
        animation: slideIn 0.3s ease;
    `;

    if (type === 'success') {
        notification.style.borderLeftColor = 'var(--green)';
        notification.style.borderLeftWidth = '4px';
    } else if (type === 'error') {
        notification.style.borderLeftColor = 'var(--red)';
        notification.style.borderLeftWidth = '4px';
    }

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// CSS animasyonları
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>