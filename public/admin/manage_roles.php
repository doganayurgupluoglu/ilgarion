<?php
// public/admin/manage_roles.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Bu sayfaya erişim için yetki kontrolü
require_permission($pdo, 'admin.roles.view');

$page_title = "Rol Yönetimi";

// Güvenli sıralama parametreleri
$allowed_order_columns = ['id', 'name', 'description', 'priority', 'created_at'];
$order_by = $_GET['order_by'] ?? 'priority';
$direction = $_GET['direction'] ?? 'ASC';

// Input validation
if (!in_array($order_by, $allowed_order_columns)) {
    $order_by = 'priority';
}
if (!in_array(strtoupper($direction), ['ASC', 'DESC'])) {
    $direction = 'ASC';
}

// Güvenli rol listesi getir
$roles = get_all_roles($pdo, $order_by, $direction);

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
    max-width: 1200px;
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
    overflow: hidden;
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
    position: relative;
}

.sortable-header {
    cursor: pointer;
    user-select: none;
    padding-right: 20px;
}

.sortable-header:hover {
    background-color: var(--gold);
    color: var(--black);
}

.sort-indicator {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.7rem;
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

.security-notice {
    background-color: var(--transparent-gold);
    color: var(--light-gold);
    padding: 12px 15px;
    border-radius: 5px;
    font-size: 0.9rem;
    margin-bottom: 20px;
    border: 1px solid var(--gold);
    display: flex;
    align-items: center;
    gap: 10px;
}

.security-notice i {
    color: var(--gold);
}

.table-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px 15px;
    background-color: var(--darker-gold-2);
    border-radius: 5px;
}

.table-info {
    font-size: 0.9rem;
    color: var(--lighter-grey);
}

.sort-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.sort-select {
    padding: 5px 10px;
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    color: var(--lighter-grey);
    border-radius: 3px;
    font-size: 0.8rem;
}
</style>

<main class="main-content">
    <div class="container admin-container admin-roles-container">
        <div class="page-header-admin">
            <h1 class="page-title-admin"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($can_create_role): ?>
            <a href="edit_role.php" class="btn btn-create-role">
                <i class="fas fa-plus-circle"></i> Yeni Rol Oluştur
            </a>
            <?php endif; ?>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            <span>Bu sayfa gelişmiş SQL güvenlik koruması ile güvenlidir. Tüm işlemler audit log'a kaydedilir.</span>
        </div>

        <?php if (empty($roles)): ?>
            <p class="info-message">Sistemde henüz tanımlanmış bir rol bulunmamaktadır.</p>
        <?php else: ?>
            <div class="table-controls">
                <div class="table-info">
                    Toplam <?php echo count($roles); ?> rol listeleniyor
                </div>
                <div class="sort-controls">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                        <label for="order_by" style="font-size: 0.8rem;">Sırala:</label>
                        <select name="order_by" id="order_by" class="sort-select" onchange="this.form.submit()">
                            <option value="priority" <?php echo $order_by === 'priority' ? 'selected' : ''; ?>>Öncelik</option>
                            <option value="name" <?php echo $order_by === 'name' ? 'selected' : ''; ?>>Ad</option>
                            <option value="created_at" <?php echo $order_by === 'created_at' ? 'selected' : ''; ?>>Oluşturma Tarihi</option>
                        </select>
                        <select name="direction" class="sort-select" onchange="this.form.submit()">
                            <option value="ASC" <?php echo $direction === 'ASC' ? 'selected' : ''; ?>>Artan</option>
                            <option value="DESC" <?php echo $direction === 'DESC' ? 'selected' : ''; ?>>Azalan</option>
                        </select>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    </form>
                </div>
            </div>

            <div class="table-responsive-wrapper">
                <table class="admin-table-roles">
                    <thead>
                        <tr>
                            <th>
                                <a href="?order_by=id&direction=<?php echo $order_by === 'id' && $direction === 'ASC' ? 'DESC' : 'ASC'; ?>" 
                                   class="sortable-header" style="color: inherit; text-decoration: none;">
                                    ID
                                    <?php if ($order_by === 'id'): ?>
                                        <span class="sort-indicator"><?php echo $direction === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?order_by=name&direction=<?php echo $order_by === 'name' && $direction === 'ASC' ? 'DESC' : 'ASC'; ?>" 
                                   class="sortable-header" style="color: inherit; text-decoration: none;">
                                    Rol Adı
                                    <?php if ($order_by === 'name'): ?>
                                        <span class="sort-indicator"><?php echo $direction === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Açıklama</th>
                            <th>Renk</th>
                            <th>
                                <a href="?order_by=priority&direction=<?php echo $order_by === 'priority' && $direction === 'ASC' ? 'DESC' : 'ASC'; ?>" 
                                   class="sortable-header" style="color: inherit; text-decoration: none;">
                                    Öncelik
                                    <?php if ($order_by === 'priority'): ?>
                                        <span class="sort-indicator"><?php echo $direction === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
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
                                    <?php if (!is_role_deletable($role['name'])): ?>
                                        <span style="font-size: 0.7rem; color: var(--gold); margin-left: 5px;">[KORUMALΙ]</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($role['description'] ?: '- Yok -'); ?></td>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($role['color']); ?></span>
                                </td>
                                <td>
                                    <span style="font-weight: bold; color: var(--turquase);"><?php echo htmlspecialchars($role['priority']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $permissions_count = 0;
                                    try {
                                        $role_permissions = get_role_permissions($pdo, $role['id']);
                                        $permissions_count = count($role_permissions);
                                    } catch (Exception $e) {
                                        error_log("Rol yetkileri sayma hatası: " . $e->getMessage());
                                    }
                                    echo $permissions_count;
                                    ?>
                                </td>
                                <td class="actions-cell-roles" style="text-align:right;">
                                    <?php if ($can_edit_role): ?>
                                    <a href="edit_role.php?role_id=<?php echo $role['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete_role && is_role_deletable($role['name'])): ?>
                                        <form action="../src/actions/handle_roles.php" method="POST" style="display:inline;" 
                                              onsubmit="return confirm('Bu rolü silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve bu role sahip kullanıcılar etkilenecektir.\n\nRol: <?php echo htmlspecialchars($role['name']); ?>');">
                                            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                            <input type="hidden" name="action" value="delete_role">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash-alt"></i> Sil
                                            </button>
                                        </form>
                                    <?php elseif (!is_role_deletable($role['name'])): ?>
                                        <button type="button" class="btn btn-sm btn-danger" disabled 
                                                title="Bu temel rol güvenlik nedeniyle silinemez.">
                                            <i class="fas fa-shield-alt"></i> Korumalı
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$can_edit_role && !$can_delete_role): ?>
                                        <span style="font-size: 0.8rem; color: var(--light-grey);">Yetki Yok</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background-color: var(--darker-gold-2); border-radius: 5px;">
                <h4 style="color: var(--gold); margin: 0 0 10px 0;">Güvenlik Notları:</h4>
                <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                    <li>Korumalı roller (admin, member, dis_uye) güvenlik nedeniyle silinemez</li>
                    <li>Tüm rol işlemleri audit log'a kaydedilir ve izlenebilir</li>
                    <li>Rol öncelikleri sistem performansını etkiler (düşük sayı = yüksek öncelik)</li>
                    <li>SQL injection ve diğer güvenlik saldırılarına karşı korumalıdır</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tablo satırlarına hover efekti
    const tableRows = document.querySelectorAll('.admin-table-roles tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'var(--darker-gold-1)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Form submit'lerinde loading state
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
            }
        });
    });
    
    // CSRF token yenileme (sayfa 30 dakika açık kalırsa)
    setTimeout(function() {
        location.reload();
    }, 1800000); // 30 dakika
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>