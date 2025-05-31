<?php
// public/admin/users.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); // Genel admin kontrolü yerine spesifik yetki
require_permission($pdo, 'admin.users.view'); // Kullanıcıları görüntüleme yetkisi

$page_title = "Kullanıcı Yönetimi";
$can_edit_status = has_permission($pdo, 'admin.users.edit_status');
$can_assign_roles = has_permission($pdo, 'admin.users.assign_roles');

$pending_users = [];
$approved_users_with_roles = []; // Artık rolleri de içerecek
$suspended_users = [];
$rejected_users = [];

try {
    // Onay Bekleyen Kullanıcılar
    $stmt_pending = $pdo->prepare("SELECT id, username, email, ingame_name, status, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC");
    $stmt_pending->execute();
    $pending_users = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

    // Onaylanmış Kullanıcılar ve Rolleri
    // Bu sorgu biraz daha karmaşık olacak çünkü user_roles ve roles tablolarını JOIN etmemiz gerekiyor.
    $sql_approved = "SELECT 
                        u.id, u.username, u.email, u.ingame_name, u.status, u.created_at,
                        GROUP_CONCAT(r.name ORDER BY r.name ASC SEPARATOR ', ') AS roles_list
                     FROM users u
                     LEFT JOIN user_roles ur ON u.id = ur.user_id
                     LEFT JOIN roles r ON ur.role_id = r.id
                     WHERE u.status = 'approved'
                     GROUP BY u.id
                     ORDER BY u.username ASC";
    $stmt_approved = $pdo->prepare($sql_approved);
    $stmt_approved->execute();
    $approved_users_with_roles = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);


    // Askıya Alınan Kullanıcılar
    $stmt_suspended = $pdo->prepare("SELECT id, username, email, ingame_name, status, created_at FROM users WHERE status = 'suspended' ORDER BY username ASC");
    $stmt_suspended->execute();
    $suspended_users = $stmt_suspended->fetchAll(PDO::FETCH_ASSOC);

    // Reddedilmiş Kullanıcılar
    $stmt_rejected = $pdo->prepare("SELECT id, username, email, ingame_name, status, created_at FROM users WHERE status = 'rejected' ORDER BY username ASC");
    $stmt_rejected->execute();
    $rejected_users = $stmt_rejected->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Kullanıcı listeleme hatası (admin/users.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcılar listelenirken bir veritabanı hatası oluştu.";
}


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<main class="main-content">
    <div class="container admin-container">
        <h2 class="page-section-title"><?php echo htmlspecialchars($page_title); ?></h2>
        <?php
        // Hızlı Yönetim Linklerini Dahil Et
        if (file_exists(BASE_PATH . '/src/includes/admin_quick_navigation.php')) {
            require BASE_PATH . '/src/includes/admin_quick_navigation.php';
        }
        ?>
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
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn btn-approve">Onayla</button>
                                    </form>
                                    <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn btn-reject">Reddet</button>
                                    </form>
                                    <?php else: ?>
                                    Yetki Yok
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Onay bekleyen kullanıcı bulunmamaktadır.</p>
            <?php endif; ?>
        </section>

        <section id="approved-users" class="user-section">
            <h3>Onaylanmış Kullanıcılar (<?php echo count($approved_users_with_roles); ?>)</h3>
             <?php if (!empty($approved_users_with_roles)): ?>
                <table class="admin-table">
                     <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Roller</th>
                            <th class="cell-center" style="min-width: 280px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved_users_with_roles as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php 
                                    // Rolleri daha okunaklı göstermek için (isteğe bağlı)
                                    $roles_display = [];
                                    if (!empty($user['roles_list'])) {
                                        $roles_array = explode(', ', $user['roles_list']);
                                        foreach ($roles_array as $role_key) {
                                            switch ($role_key) {
                                                case 'admin': $roles_display[] = 'Yönetici'; break;
                                                case 'member': $roles_display[] = 'Üye'; break;
                                                case 'scg_uye': $roles_display[] = 'SCG Üyesi'; break;
                                                case 'ilgarion_turanis': $roles_display[] = 'Ilgarion Turanis'; break;
                                                case 'dis_uye': $roles_display[] = 'Dış Üye'; break;
                                                default: $roles_display[] = ucfirst($role_key);
                                            }
                                        }
                                    }
                                    echo !empty($roles_display) ? implode(', ', $roles_display) : 'Rol Atanmamış';
                                    ?>
                                </td>
                                <td class="actions-cell">
                                     <?php if ($can_assign_roles): ?>
                                     <a href="edit_user_roles.php?user_id=<?php echo $user['id']; ?>" class="action-btn btn-primary" style="text-decoration:none; background-color: var(--gold); border-color: var(--gold); color: var(--darker-gold-2);">Rolleri Yönet</a>
                                     <?php endif; ?>
                                     <?php if ($can_edit_status): ?>
                                     <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="action-btn btn-suspend">Askıya Al</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($can_assign_roles): ?>
                                        <?php 
                                            $user_actual_roles = !empty($user['roles_list']) ? explode(', ', $user['roles_list']) : [];
                                            if (!in_array('admin', $user_actual_roles)): 
                                        ?>
                                            <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="assign_role"> <input type="hidden" name="role_to_assign" value="admin">
                                                <button type="submit" class="action-btn btn-warning">Admin Yap</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($user['id'] != $_SESSION['user_id']): // Kendi adminliğini alamasın ?>
                                            <form action="../../src/actions/handle_user_approval.php" method="POST" class="inline-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="remove_role"> <input type="hidden" name="role_to_remove" value="admin">
                                                <button type="submit" class="action-btn btn-info" style="background-color: var(--light-turquase); border-color: var(--light-turquase); color: var(--black);">Adminliği Al</button>
                                            </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!$can_assign_roles && !$can_edit_status): ?>
                                    Yetki Yok
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Onaylanmış kullanıcı bulunmamaktadır.</p>
            <?php endif; ?>
        </section>

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
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="reinstate_approved">
                                        <button type="submit" class="action-btn btn-reinstate">Askıdan Çıkar</button>
                                    </form>
                                    <?php else: ?>
                                    Yetki Yok
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Askıya alınan kullanıcı bulunmamaktadır.</p>
            <?php endif; ?>
        </section>

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
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="approve"> <button type="submit" class="action-btn btn-approve">Tekrar Değerlendir/Onayla</button>
                                    </form>
                                    <?php else: ?>
                                    Yetki Yok
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Reddedilmiş kullanıcı bulunmamaktadır.</p>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
