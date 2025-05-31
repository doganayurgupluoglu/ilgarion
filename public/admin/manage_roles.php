<?php
// public/admin/manage_roles.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonlarını dahil et

// Bu sayfaya erişim için 'admin.roles.view' veya daha genel bir admin yetkisi kontrolü
require_permission($pdo, 'admin.roles.view');

$page_title = "Rol Yönetimi";
$roles = get_all_roles($pdo); // role_functions.php'den

$can_create_role = has_permission($pdo, 'admin.roles.create');
$can_edit_role = has_permission($pdo, 'admin.roles.edit');
$can_delete_role = has_permission($pdo, 'admin.roles.delete');

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* manage_roles.php için Stiller */
.admin-roles-container {
    width: 100%;
    max-width: 1200px; /* Daha geniş bir alan */
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
}

.page-header-admin {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.page-title-admin {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
}

.btn-create-role {
    background-color: var(--turquase);
    color: var(--black);
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.95rem;
    transition: background-color 0.3s ease, transform 0.2s ease;
}
.btn-create-role:hover {
    background-color: var(--light-turquase);
    color: var(--darker-gold-2);
    transform: translateY(-2px);
}

.admin-table-roles {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--charcoal);
    border-radius: 8px;
    overflow: hidden; /* Köşelerin düzgün görünmesi için */
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.admin-table-roles th,
.admin-table-roles td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2);
    font-size: 0.9rem;
    color: var(--lighter-grey);
    vertical-align: middle;
}

.admin-table-roles thead th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.admin-table-roles tbody tr:hover {
    background-color: var(--darker-gold-2);
}
.admin-table-roles tbody tr:last-child td {
    border-bottom: none;
}

.role-color-indicator {
    display: inline-block;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    margin-right: 10px;
    vertical-align: middle;
    border: 1px solid rgba(255,255,255,0.2);
}

.actions-cell-roles .btn {
    margin-right: 8px;
    padding: 6px 12px;
    font-size: 0.8rem;
}
.actions-cell-roles .btn:last-child {
    margin-right: 0;
}
/* .btn-warning ve .btn-danger stilleri style.css'den gelmeli */
</style>

<main class="main-content">
    <div class="container admin-container admin-roles-container">
        <div class="page-header-admin">
            <h1 class="page-title-admin"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($can_create_role): ?>
            <a href="edit_role.php" class="btn btn-create-role"><i class="fas fa-plus-circle"></i> Yeni Rol Oluştur</a>
            <?php endif; ?>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <?php if (empty($roles)): ?>
            <p class="info-message">Sistemde henüz tanımlanmış bir rol bulunmamaktadır.</p>
        <?php else: ?>
            <div class="table-responsive-wrapper" style="margin-top:20px;">
                <table class="admin-table-roles">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rol Adı</th>
                            <th>Açıklama</th>
                            <th>Renk</th>
                            <th>Yetki Sayısı</th>
                            <th style="min-width:180px; text-align:right;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($role['id']); ?></td>
                                <td>
                                    <span class="role-color-indicator" style="background-color: <?php echo htmlspecialchars($role['color']); ?>;"></span>
                                    <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($role['description'] ?: '- Yok -'); ?></td>
                                <td><?php echo htmlspecialchars($role['color']); ?></td>
                                <td>
                                    <?php
                                    $permissions_count = 0;
                                    if (!empty($role['permissions'])) {
                                        $permissions_array = explode(',', $role['permissions']);
                                        $permissions_count = count(array_filter($permissions_array)); // Boş elemanları sayma
                                    }
                                    echo $permissions_count;
                                    ?>
                                </td>
                                <td class="actions-cell-roles" style="text-align:right;">
                                    <?php if ($can_edit_role): ?>
                                    <a href="edit_role.php?role_id=<?php echo $role['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Düzenle</a>
                                    <?php endif; ?>
                                    <?php if ($can_delete_role && !in_array($role['name'], ['admin', 'member', 'dis_uye'])): ?>
                                        <form action="../src/actions/handle_roles.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu rolü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve bu role sahip kullanıcılar etkilenecektir.');">
                                            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                            <input type="hidden" name="action" value="delete_role">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i> Sil</button>
                                        </form>
                                    <?php elseif (in_array($role['name'], ['admin', 'member', 'dis_uye'])): ?>
                                        <button type="button" class="btn btn-sm btn-danger" disabled title="Bu temel rol silinemez."><i class="fas fa-trash-alt"></i> Sil</button>
                                    <?php endif; ?>
                                    <?php if (!$can_edit_role && !$can_delete_role): ?>
                                        Yetki Yok
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
