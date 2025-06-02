<?php
// public/admin/users.php - Hiyerarşik Rol Sistemi ile Revize Edilmiş

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Yetki kontrolü - Kullanıcıları görüntüleme yetkisi gerekli
require_permission($pdo, 'admin.users.view');

$page_title = "Kullanıcı Yönetimi";
$can_edit_status = has_permission($pdo, 'admin.users.edit_status');
$can_assign_roles = has_permission($pdo, 'admin.users.assign_roles');
$is_super_admin_user = is_super_admin($pdo);

// Kullanıcının hiyerarşik konumunu belirle
$current_user_id = $_SESSION['user_id'];
$user_roles = get_user_roles($pdo, $current_user_id);
$user_highest_priority = 999;
$user_role_names = [];

foreach ($user_roles as $role) {
    $user_role_names[] = $role['name'];
    if ($role['priority'] < $user_highest_priority) {
        $user_highest_priority = $role['priority'];
    }
}

// Kullanıcının yönetebileceği rolleri belirle (kendi seviyesinden düşük olanlar)
$manageable_roles = [];
if ($can_assign_roles) {
    try {
        $manageable_query = "SELECT id, name, priority, description, color FROM roles WHERE priority > ? ORDER BY priority ASC";
        $stmt_manageable = execute_safe_query($pdo, $manageable_query, [$user_highest_priority]);
        $manageable_roles = $stmt_manageable->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Manageable roles query error: " . $e->getMessage());
    }
}

$pending_users = [];
$approved_users_with_roles = [];
$suspended_users = [];
$rejected_users = [];
$user_stats = [
    'manageable_users' => 0,
    'higher_hierarchy_users' => 0,
    'same_level_users' => 0
];

try {
    // Onay Bekleyen Kullanıcılar
    $stmt_pending = execute_safe_query($pdo, "SELECT id, username, email, ingame_name, status, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC");
    $pending_users = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

    // Onaylanmış Kullanıcılar ve Rolleri - Hiyerarşi kontrolü ile
    $sql_approved = "
        SELECT 
            u.id, u.username, u.email, u.ingame_name, u.status, u.created_at,
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.priority ASC SEPARATOR ',') AS roles_list,
            GROUP_CONCAT(DISTINCT r.id ORDER BY r.priority ASC SEPARATOR ',') AS role_ids,
            MIN(r.priority) as highest_priority,
            COUNT(DISTINCT r.id) as role_count
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.status = 'approved'
        GROUP BY u.id, u.username, u.email, u.ingame_name, u.status, u.created_at
        ORDER BY MIN(COALESCE(r.priority, 999)) ASC, u.username ASC
    ";
    $stmt_approved = execute_safe_query($pdo, $sql_approved);
    $approved_users_raw = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);

    // Kullanıcıları hiyerarşi durumuna göre işle
    foreach ($approved_users_raw as $user) {
        $user_priority = $user['highest_priority'] ?? 999;
        
        // Kullanıcının yönetilebilirlik durumunu belirle
        $can_manage_this_user = false;
        $hierarchy_status = 'equal';
        
        if ($is_super_admin_user) {
            $can_manage_this_user = true;
            $hierarchy_status = 'manageable';
        } elseif ($user['id'] == $current_user_id) {
            $can_manage_this_user = false;
            $hierarchy_status = 'self';
        } elseif ($user_priority > $user_highest_priority) {
            $can_manage_this_user = true;
            $hierarchy_status = 'manageable';
            $user_stats['manageable_users']++;
        } elseif ($user_priority < $user_highest_priority) {
            $hierarchy_status = 'higher';
            $user_stats['higher_hierarchy_users']++;
        } else {
            $hierarchy_status = 'equal';
            $user_stats['same_level_users']++;
        }
        
        $user['can_manage'] = $can_manage_this_user;
        $user['hierarchy_status'] = $hierarchy_status;
        $user['user_priority'] = $user_priority;
        
        $approved_users_with_roles[] = $user;
    }

    // Askıya Alınan Kullanıcılar
    $stmt_suspended = execute_safe_query($pdo, "SELECT id, username, email, ingame_name, status, created_at FROM users WHERE status = 'suspended' ORDER BY username ASC");
    $suspended_users = $stmt_suspended->fetchAll(PDO::FETCH_ASSOC);

    // Reddedilmiş Kullanıcılar
    $stmt_rejected = execute_safe_query($pdo, "SELECT id, username, email, ingame_name, status, created_at FROM users WHERE status = 'rejected' ORDER BY username ASC");
    $rejected_users = $stmt_rejected->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Kullanıcı listeleme hatası (admin/users.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcılar listelenirken bir hata oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.hierarchy-info-box {
    background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1));
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 4px solid var(--gold);
}

.hierarchy-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.hierarchy-stat {
    background: var(--charcoal);
    padding: 15px;
    border-radius: 6px;
    text-align: center;
    border: 1px solid var(--darker-gold-1);
}

.hierarchy-stat .number {
    font-size: 1.8rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 5px;
}

.hierarchy-stat .label {
    font-size: 0.9rem;
    color: var(--lighter-grey);
}

.user-row {
    position: relative;
}

.user-row.hierarchy-higher {
    background-color: rgba(255, 107, 107, 0.1);
    border-left: 3px solid #ff6b6b;
}

.user-row.hierarchy-equal {
    background-color: rgba(255, 193, 7, 0.1);
    border-left: 3px solid #ffc107;
}

.user-row.hierarchy-manageable {
    background-color: rgba(40, 167, 69, 0.1);
    border-left: 3px solid #28a745;
}

.user-row.hierarchy-self {
    background-color: rgba(61, 166, 162, 0.1);
    border-left: 3px solid var(--turquase);
}

.hierarchy-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.hierarchy-indicator.higher {
    background-color: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
}

.hierarchy-indicator.equal {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.hierarchy-indicator.manageable {
    background-color: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.hierarchy-indicator.self {
    background-color: rgba(61, 166, 162, 0.2);
    color: var(--turquase);
}

.role-badge {
    display: inline-block;
    padding: 2px 8px;
    margin: 2px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.quick-action-disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.quick-action-tooltip {
    position: relative;
}

.quick-action-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: var(--black);
    color: var(--white);
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 5px;
}

.manageable-roles-info {
    background: var(--transparent-turquase);
    padding: 15px;
    border-radius: 6px;
    margin-top: 15px;
    border: 1px solid var(--turquase);
}

.manageable-roles-info h4 {
    color: var(--turquase);
    margin: 0 0 10px 0;
    font-size: 1rem;
}

.manageable-roles-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.manageable-role-badge {
    background: var(--turquase);
    color: var(--black);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}
</style>

<main class="main-content">
    <div class="container admin-container">
        <h2 class="page-section-title"><?php echo htmlspecialchars($page_title); ?></h2>
        
        <?php
        // Hızlı Yönetim Linklerini Dahil Et
        if (file_exists(BASE_PATH . '/src/includes/admin_quick_navigation.php')) {
            require BASE_PATH . '/src/includes/admin_quick_navigation.php';
        }
        ?>

        <!-- Hiyerarşi Bilgi Kutusu -->
        <div class="hierarchy-info-box">
            <h3 style="color: var(--gold); margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-sitemap"></i>
                Kullanıcı Yönetimi Hiyerarşi Durumu
                <?php if ($is_super_admin_user): ?>
                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: bold;">SÜPER ADMİN</span>
                <?php endif; ?>
            </h3>
            
            <div style="color: var(--light-gold); margin-bottom: 15px;">
                <strong>Yetki Seviyeniz:</strong> 
                <?php echo implode(', ', array_map('ucfirst', $user_role_names)); ?>
                (Hiyerarşi: <?php echo $user_highest_priority; ?>)
            </div>

            <div class="hierarchy-stats">
                <div class="hierarchy-stat">
                    <div class="number manageable"><?php echo $user_stats['manageable_users']; ?></div>
                    <div class="label">Yönetilebilir Kullanıcı</div>
                </div>
                <div class="hierarchy-stat">
                    <div class="number" style="color: #ffc107;"><?php echo $user_stats['same_level_users']; ?></div>
                    <div class="label">Eşit Seviye Kullanıcı</div>
                </div>
                <div class="hierarchy-stat">
                    <div class="number" style="color: #ff6b6b;"><?php echo $user_stats['higher_hierarchy_users']; ?></div>
                    <div class="label">Üst Seviye Kullanıcı</div>
                </div>
                <div class="hierarchy-stat">
                    <div class="number" style="color: var(--turquase);"><?php echo count($manageable_roles); ?></div>
                    <div class="label">Atayabileceğiniz Rol</div>
                </div>
            </div>

            <?php if (!empty($manageable_roles)): ?>
            <div class="manageable-roles-info">
                <h4><i class="fas fa-user-tag"></i> Atayabileceğiniz Roller:</h4>
                <div class="manageable-roles-list">
                    <?php foreach ($manageable_roles as $role): ?>
                        <!-- <span class="manageable-role-badge" style="background-color: <?php echo $role['color']; ?>; color: white;"> -->
                            <?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?> (<?php echo $role['priority']; ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Onay Bekleyen Kullanıcılar -->
        <section id="pending-users" class="user-section">
            <h3>Onay Bekleyen Kullanıcılar (<?php echo count($pending_users); ?>)</h3>
            <?php if (!empty($pending_users)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Oyun İçi İsim</th>
                            <th>Kayıt Tarihi</th>
                            <th class="cell-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['ingame_name']); ?></td>
                                <td><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <?php if ($can_edit_status): ?>
                                    <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn btn-approve">
                                            <i class="fas fa-check"></i> Onayla
                                        </button>
                                    </form>
                                    <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn btn-reject">
                                            <i class="fas fa-times"></i> Reddet
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="hierarchy-indicator" style="background-color: rgba(108, 117, 125, 0.2); color: #6c757d;">
                                        <i class="fas fa-lock"></i> Yetki Yok
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: var(--lighter-grey); padding: 20px;">
                    <i class="fas fa-user-check" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    Onay bekleyen kullanıcı bulunmamaktadır.
                </p>
            <?php endif; ?>
        </section>

        <!-- Onaylanmış Kullanıcılar -->
        <section id="approved-users" class="user-section">
            <h3>Onaylanmış Kullanıcılar (<?php echo count($approved_users_with_roles); ?>)</h3>
            <?php if (!empty($approved_users_with_roles)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Roller & Hiyerarşi</th>
                            <th class="cell-center" style="min-width: 300px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved_users_with_roles as $user): ?>
                            <tr class="user-row hierarchy-<?php echo $user['hierarchy_status']; ?>">
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['id'] == $current_user_id): ?>
                                        <span style="color: var(--turquase); margin-left: 8px; font-size: 0.8rem;">
                                            <i class="fas fa-user"></i> (Siz)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <div style="margin-bottom: 8px;">
                                        <?php if (!empty($user['roles_list'])): ?>
                                            <?php 
                                            $roles_array = explode(',', $user['roles_list']);
                                            $role_ids_array = explode(',', $user['role_ids'] ?? '');
                                            
                                            foreach ($roles_array as $index => $role_key): 
                                                $role_id = $role_ids_array[$index] ?? '';
                                                
                                                // Rol rengini belirle
                                                $role_color = '#6c757d'; // Varsayılan gri
                                                foreach ($manageable_roles as $manageable_role) {
                                                    if ($manageable_role['name'] === $role_key) {
                                                        // $role_color = $manageable_role['color'];
                                                        break;
                                                    }
                                                }
                                                
                                                // Rol adını daha okunabilir hale getir
                                                $role_display = match($role_key) {
                                                    'admin' => 'Yönetici',
                                                    'member' => 'Üye',
                                                    'scg_uye' => 'SCG Üyesi',
                                                    'ilgarion_turanis' => 'Ilgarion Turanis',
                                                    'dis_uye' => 'Dış Üye',
                                                    default => ucfirst(str_replace('_', ' ', $role_key))
                                                };
                                            ?>
                                                <span class="role-badge" style="background-color: <?php echo $role_color; ?>;">
                                                    <?php echo $role_display; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="role-badge" style="background-color: #6c757d;">Rol Atanmamış</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="hierarchy-indicator <?php echo $user['hierarchy_status']; ?>">
                                        <?php 
                                        $hierarchy_text = match($user['hierarchy_status']) {
                                            'manageable' => '<i class="fas fa-arrow-down"></i> Yönetilebilir',
                                            'higher' => '<i class="fas fa-arrow-up"></i> Üst Seviye',
                                            'equal' => '<i class="fas fa-minus"></i> Eşit Seviye',
                                            'self' => '<i class="fas fa-user"></i> Kendiniz',
                                            default => 'Bilinmiyor'
                                        };
                                        echo $hierarchy_text;
                                        ?>
                                        <span style="margin-left: 5px; font-size: 0.9em;">(<?php echo $user['user_priority']; ?>)</span>
                                    </div>
                                </td>
                                <td class="actions-cell">
                                    <?php if ($user['can_manage'] && $can_assign_roles): ?>
                                        <a href="edit_user_roles.php?user_id=<?php echo $user['id']; ?>" 
                                           class="action-btn btn-primary" 
                                           style="text-decoration:none; background-color: var(--gold); border-color: var(--gold); color: var(--darker-gold-2);">
                                            <i class="fas fa-user-cog"></i> Rolleri Yönet
                                        </a>
                                    <?php elseif (!$user['can_manage'] && $can_assign_roles): ?>
                                        <span class="action-btn quick-action-disabled quick-action-tooltip" 
                                              data-tooltip="Üst seviye kullanıcıyı yönetemezsiniz">
                                            <i class="fas fa-lock"></i> Yönetilemez
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($user['can_manage'] && $can_edit_status && $user['id'] != $current_user_id): ?>
                                        <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="action-btn btn-suspend quick-action-tooltip" 
                                                    data-tooltip="Kullanıcıyı askıya al">
                                                <i class="fas fa-user-slash"></i> Askıya Al
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!$can_assign_roles && !$can_edit_status): ?>
                                        <span class="hierarchy-indicator" style="background-color: rgba(108, 117, 125, 0.2); color: #6c757d;">
                                            <i class="fas fa-lock"></i> Yetki Yok
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: var(--lighter-grey); padding: 20px;">
                    <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    Onaylanmış kullanıcı bulunmamaktadır.
                </p>
            <?php endif; ?>
        </section>

        <!-- Askıya Alınan Kullanıcılar -->
        <section id="suspended-users" class="user-section">
            <h3>Askıya Alınan Kullanıcılar (<?php echo count($suspended_users); ?>)</h3>
            <?php if (!empty($suspended_users)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th class="cell-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suspended_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="actions-cell">
                                    <?php if ($can_edit_status): ?>
                                    <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="reinstate_approved">
                                        <button type="submit" class="action-btn btn-reinstate">
                                            <i class="fas fa-user-check"></i> Askıdan Çıkar
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="hierarchy-indicator" style="background-color: rgba(108, 117, 125, 0.2); color: #6c757d;">
                                        <i class="fas fa-lock"></i> Yetki Yok
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: var(--lighter-grey); padding: 20px;">
                    <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    Askıya alınan kullanıcı bulunmamaktadır.
                </p>
            <?php endif; ?>
        </section>

        <!-- Reddedilmiş Kullanıcılar -->
        <section id="rejected-users" class="user-section">
            <h3>Reddedilmiş Kullanıcılar (<?php echo count($rejected_users); ?>)</h3>
            <?php if (!empty($rejected_users)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th class="cell-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejected_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="actions-cell">
                                    <?php if ($can_edit_status): ?>
                                    <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn btn-approve">
                                            <i class="fas fa-redo"></i> Tekrar Değerlendir
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="hierarchy-indicator" style="background-color: rgba(108, 117, 125, 0.2); color: #6c757d;">
                                        <i class="fas fa-lock"></i> Yetki Yok
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: var(--lighter-grey); padding: 20px;">
                    <i class="fas fa-user-times" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    Reddedilmiş kullanıcı bulunmamaktadır.
                </p>
            <?php endif; ?>
        </section>

        <!-- Hiyerarşi Açıklaması -->
        <div style="margin-top: 30px; padding: 20px; background-color: var(--transparent-gold); border-radius: 8px; border: 1px solid var(--gold);">
            <h4 style="color: var(--gold); margin: 0 0 15px 0;">
                <i class="fas fa-info-circle"></i> Hiyerarşi Sistemi Hakkında
            </h4>
            <div style="color: var(--light-gold); line-height: 1.6;">
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Yönetilebilir:</strong> Kendi seviyenizden düşük öncelikli kullanıcıları yönetebilirsiniz</li>
                    <li><strong>Eşit Seviye:</strong> Aynı hiyerarşi seviyesindeki kullanıcıları yönetemezsiniz</li>
                    <li><strong>Üst Seviye:</strong> Kendinizden yüksek seviyedeki kullanıcıları yönetemezsiniz</li>
                    <li><strong>Süper Admin:</strong> Tüm kullanıcıları ve rolleri yönetebilir</li>
                </ul>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--darker-gold-1); font-size: 0.9rem;">
                    <strong>Not:</strong> Düşük sayı = Yüksek öncelik. Örnek: Admin (1) > Ilgarion Turanis (2) > SCG Üyesi (3)
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Confirmation for sensitive actions
document.addEventListener('DOMContentLoaded', function() {
    // Askıya alma onayı
    const suspendButtons = document.querySelectorAll('button[type="submit"][class*="btn-suspend"]');
    suspendButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const username = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            if (!confirm(`"${username}" kullanıcısını askıya almak istediğinizden emin misiniz?`)) {
                e.preventDefault();
            }
        });
    });

    // Reddetme onayı
    const rejectButtons = document.querySelectorAll('button[type="submit"][class*="btn-reject"]');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const username = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            if (!confirm(`"${username}" kullanıcısını reddetmek istediğinizden emin misiniz?`)) {
                e.preventDefault();
            }
        });
    });

    // Onaylama için pozitif onay
    const approveButtons = document.querySelectorAll('button[type="submit"][class*="btn-approve"]');
    approveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const username = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            if (!confirm(`"${username}" kullanıcısını onaylamak istediğinizden emin misiniz?`)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>