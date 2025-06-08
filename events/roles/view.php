<?php
// /events/roles/view.php - Etkinlik rol detay sayfası

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
    // Rol detaylarını getir
    $role_stmt = $pdo->prepare("
        SELECT er.*, 
               u.username as creator_username,
               u.id as creator_user_id
        FROM event_roles er
        LEFT JOIN users u ON er.created_by_user_id = u.id
        WHERE er.id = :role_id AND er.is_active = 1
    ");
    
    $role_stmt->execute([':role_id' => $role_id]);
    $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        $_SESSION['error_message'] = "Rol bulunamadı veya aktif değil.";
        header('Location: index.php');
        exit;
    }
    
    // Görünürlük kontrolü
    if ($role['visibility'] === 'members_only' && !$is_logged_in) {
        $_SESSION['error_message'] = "Bu rolü görüntülemek için giriş yapmanız gerekiyor.";
        header('Location: ../../auth/login.php');
        exit;
    }
    
    // Creator role color
    $creator_role_color = '#bd912a';
    if ($role['creator_user_id']) {
        $color_stmt = $pdo->prepare("
            SELECT r.color 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = :user_id 
            ORDER BY r.priority ASC 
            LIMIT 1
        ");
        $color_stmt->execute([':user_id' => $role['creator_user_id']]);
        $color_result = $color_stmt->fetch(PDO::FETCH_ASSOC);
        if ($color_result) {
            $creator_role_color = $color_result['color'];
        }
    }
    $role['creator_role_color'] = $creator_role_color;
    
    // Teçhizat seti bilgisi - BASIT!
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
    $usage_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_role_slots WHERE event_role_id = :role_id");
    $usage_stmt->execute([':role_id' => $role_id]);
    $role['usage_count'] = $usage_stmt->fetchColumn();
    
    // Requirements count
    $req_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_role_requirements WHERE event_role_id = :role_id");
    $req_stmt->execute([':role_id' => $role_id]);
    $role['requirements_count'] = $req_stmt->fetchColumn();
    
    // Rol gereksinimlerini getir - YENİ YAPI
    $requirements = [];
    try {
        $requirements_stmt = $pdo->prepare("
            SELECT err.*, 
                   GROUP_CONCAT(st.tag_name ORDER BY st.tag_name ASC SEPARATOR ', ') as tag_names
            FROM event_role_requirements err
            LEFT JOIN skill_tags st ON FIND_IN_SET(st.id, err.skill_tag_ids) > 0
            WHERE err.event_role_id = :role_id
            GROUP BY err.id
            ORDER BY err.is_required DESC, err.id ASC
        ");
        $requirements_stmt->execute([':role_id' => $role_id]);
        $requirements = $requirements_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $requirements = [];
    }
    
    // Kullanıcının skill tag'lerini kontrol et (giriş yapmışsa)
    $user_skill_tags = [];
    $user_meets_requirements = true;
    $debug_user_query_result = null;
    
    if ($is_logged_in && !empty($requirements)) {
        try {
            // Yeni tablo yapısına göre user tag'leri çek
            $user_tags_stmt = $pdo->prepare("
                SELECT tag_ids 
                FROM user_skill_tags 
                WHERE user_id = :user_id
            ");
            $user_tags_stmt->execute([':user_id' => $current_user_id]);
            $user_tags_result = $user_tags_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug için sorgu sonucunu sakla
            $debug_user_query_result = $user_tags_result;
            
            if ($user_tags_result && !empty($user_tags_result['tag_ids'])) {
                // String'leri integer'a çevir ve trim'le
                $user_skill_tags = array_map('intval', array_map('trim', explode(',', $user_tags_result['tag_ids'])));
            }
            
            // Debug için - geliştirme aşamasında silinebilir
            error_log("User skill tags: " . print_r($user_skill_tags, true));
            
            // Gereksinimleri kontrol et - HER ZORUNLU GEREKSİNİM AYRI AYRI KONTROL EDİLMELİ
            foreach ($requirements as $requirement) {
                if ($requirement['is_required']) {
                    // Gereksinim tag'lerini de integer'a çevir
                    $required_tag_ids = array_map('intval', array_map('trim', explode(',', $requirement['skill_tag_ids'])));
                    $has_required_tags = false;
                    
                    // Debug için - geliştirme aşamasında silinebilir
                    error_log("Required tag IDs for requirement: " . print_r($required_tag_ids, true));
                    
                    // TÜM TAG'LERE SAHİP OLMA KONTROLÜ
                    $has_required_tags = count(array_intersect($required_tag_ids, $user_skill_tags)) === count($required_tag_ids);
                    
                    // Debug için
                    $intersect_count = count(array_intersect($required_tag_ids, $user_skill_tags));
                    $required_count = count($required_tag_ids);
                    error_log("Intersection count: $intersect_count, Required count: $required_count, Has all tags: " . ($has_required_tags ? 'YES' : 'NO'));
                    
                    // Eğer bu zorunlu gereksinimi karşılamıyorsa, tümünü karşılamıyor
                    if (!$has_required_tags) {
                        $user_meets_requirements = false;
                        error_log("User does NOT meet requirement with tags: " . print_r($required_tag_ids, true));
                        break; // Artık kontrol etmeye gerek yok
                    }
                }
            }
            
            error_log("Final user_meets_requirements: " . ($user_meets_requirements ? 'true' : 'false'));
            
        } catch (PDOException $e) {
            error_log("User skill tags fetch error: " . $e->getMessage());
            $user_meets_requirements = false; // Hata durumunda false yap
        }
    }
    
    // Bu rolün kullanıldığı etkinlikleri getir
    $recent_events = [];
    try {
        $events_stmt = $pdo->prepare("
            SELECT DISTINCT e.id, e.title, e.event_datetime, e.status,
                   ers.slot_count, ers.filled_count,
                   (SELECT COUNT(*) FROM event_role_participants erp 
                    WHERE erp.event_role_slot_id = ers.id AND erp.status = 'active') as active_participants
            FROM events e
            JOIN event_role_slots ers ON e.id = ers.event_id
            WHERE ers.event_role_id = :role_id
            ORDER BY e.event_datetime DESC
            LIMIT 10
        ");
        $events_stmt->execute([':role_id' => $role_id]);
        $recent_events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recent_events = [];
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Rol detayları yüklenirken bir hata oluştu.";
    header('Location: index.php');
    exit;
} catch (Exception $e) {
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
    ['text' => $role['role_name'], 'url' => '', 'icon' => $role['icon_class'] ?? 'fas fa-user']
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
                    <h1><i class="<?= htmlspecialchars($role['icon_class']) ?>"></i><?= htmlspecialchars($role['role_name']) ?></h1>
                    <div class="set-meta-info">
                        <?php if ($role['usage_count'] > 0): ?>
                            <span class="usage-count">
                                <i class="fas fa-calendar"></i>
                                <?= $role['usage_count'] ?> etkinlikte kullanıldı
                            </span>
                        <?php endif; ?>
                        <span class="creator-info">
                            <i class="fas fa-user"></i>
                            Oluşturan: 
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
                        </span>

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
            
            <!-- Eylem Butonları -->
            <?php if ($is_logged_in): ?>
                <div class="set-actions">
                    <?php if ($role['created_by_user_id'] == $current_user_id || $can_edit_all): ?>
                        <a href="edit.php?id=<?= $role['id'] ?>" class="btn-action-secondary">
                            <i class="fas fa-edit"></i>
                            Düzenle
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($role['created_by_user_id'] == $current_user_id || $can_delete_all): ?>
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

        <!-- Rol Gereksinimleri - YENİ YAPI -->
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
                
                <!-- DEBUG BİLGİLERİ -->
                <?php if ($is_logged_in): ?>
                    <div style="background: #1a1a1a; color: #00ff00; padding: 1rem; margin: 1rem 0; border-radius: 5px; font-family: monospace; font-size: 12px;">
                        <strong>DEBUG BİLGİLERİ:</strong><br>
                        Kullanıcı ID: <?= $current_user_id ?><br>
                        SQL Sorgu Sonucu: <?= $debug_user_query_result ? json_encode($debug_user_query_result) : 'NULL (kayıt yok)' ?><br>
                        Ham tag_ids: "<?= $debug_user_query_result['tag_ids'] ?? 'YOK' ?>"<br>
                        Kullanıcı Tag'leri: [<?= implode(', ', $user_skill_tags) ?>]<br>
                        Gereksinim sayısı: <?= count($requirements) ?><br>
                        Son durum: <?= $user_meets_requirements ? 'TRUE' : 'FALSE' ?><br><br>
                        
                        <?php foreach ($requirements as $index => $req): ?>
                            <?php 
                            $req_tag_ids = array_map('intval', array_map('trim', explode(',', $req['skill_tag_ids'])));
                            $has_match = false;
                            $matching_tags = [];
                            
                            // TÜM TAG'LER KONTROLÜ
                            $intersection = array_intersect($req_tag_ids, $user_skill_tags);
                            $matching_tags = array_values($intersection);
                            $has_match = count($intersection) === count($req_tag_ids); // TÜM tag'lere sahip mi?
                            ?>
                            Gereksinim <?= $index + 1 ?>:<br>
                            - Gerekli Tag'ler: [<?= implode(', ', $req_tag_ids) ?>]<br>
                            - Sahip Olunan Tag'ler: [<?= implode(', ', $matching_tags) ?>]<br>
                            - Eksik Tag'ler: [<?= implode(', ', array_diff($req_tag_ids, $user_skill_tags)) ?>]<br>
                            - Zorunlu: <?= $req['is_required'] ? 'EVET' : 'HAYIR' ?><br>
                            - TÜM Tag'lere Sahip: <?= $has_match ? 'EVET' : 'HAYIR' ?><br><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="requirements-list">
                    <?php foreach ($requirements as $req): ?>
                        <div class="requirement-item <?= $req['is_required'] ? 'required' : 'preferred' ?>">
                            <div class="requirement-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="requirement-content">
                                <span class="requirement-label">
                                    Skill Tag'ler: <?= htmlspecialchars($req['tag_names']) ?>
                                </span>
                                <span class="requirement-type">
                                    <?= $req['is_required'] ? 'Zorunlu' : 'Tercih Edilen' ?>
                                    <?php if ($is_logged_in): ?>
                                        <?php
                                        // Tag ID'leri integer'a çevir
                                        $required_tag_ids = array_map('intval', array_map('trim', explode(',', $req['skill_tag_ids'])));
                                        
                                        // TÜM TAG'LERE SAHİP Mİ KONTROLÜ
                                        $intersection = array_intersect($required_tag_ids, $user_skill_tags);
                                        $has_all_required = count($intersection) === count($required_tag_ids);
                                        ?>
                                        - 
                                        <span style="<?= $has_all_required ? 'color: var(--turquase);' : 'color: var(--red);' ?>">
                                            <?php if ($req['is_required']): ?>
                                                <?= $has_all_required ? '✓ Tüm Tag\'lere Sahip' : '✗ Eksik Tag\'ler Var' ?>
                                            <?php else: ?>
                                                <?= $has_all_required ? '✓ Tüm Tag\'lere Sahip' : '○ Eksik Tag\'ler Var' ?>
                                            <?php endif; ?>
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
                                            case 'active': echo 'Aktif'; break;
                                            case 'completed': echo 'Tamamlandı'; break;
                                            case 'cancelled': echo 'İptal Edildi'; break;
                                            default: echo ucfirst($event['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="event-participants">
                                    <i class="fas fa-users"></i>
                                    <?= $event['active_participants'] ?> / <?= $event['slot_count'] ?> katılımcı
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Popover Include - Sadece giriş yapmış kullanıcılar için -->
<?php if ($is_logged_in): ?>
    <?php include BASE_PATH . '/src/includes/user_popover.php'; ?>
<?php endif; ?>

<!-- Rol Silme Modal -->
<?php if ($is_logged_in && ($role['created_by_user_id'] == $current_user_id || $can_delete_all)): ?>
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
<?php if ($is_logged_in && ($role['created_by_user_id'] == $current_user_id || $can_delete_all)): ?>
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