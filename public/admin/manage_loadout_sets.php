<?php
// public/admin/manage_loadout_sets.php

require_once '../../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); 
require_permission($pdo, 'loadout.manage_sets'); // Setleri yönetme ana yetkisi

$page_title = "Teçhizat Setlerini Yönet";

$can_manage_items = has_permission($pdo, 'loadout.manage_items');
// Set detaylarını düzenleme ve silme için 'loadout.manage_sets' yetkisinin yeterli olduğunu varsayıyoruz.
// Daha granüler yetkiler (örn: loadout.edit_set_details, loadout.delete_set) eklenebilir.
$loadout_sets = [];

try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }
    $stmt = $pdo->query("SELECT ls.id, ls.set_name, ls.set_description, ls.set_image_path, u.username AS creator_username, ls.created_at 
                         FROM loadout_sets ls
                         JOIN users u ON ls.user_id = u.id
                         ORDER BY ls.set_name ASC");
    $loadout_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Teçhizat setlerini çekme hatası: " . $e->getMessage());
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['error_message'] = "Teçhizat setleri yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php'; //
require_once BASE_PATH . '/src/includes/navbar.php'; //
?>
<?php // CSS stilleri harici style.css dosyasından gelecek ?>
<style>
    .admin-container .page-header div:last-child { /* Headerdaki butonları saran div için */
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Sayfa içi akışta kullanılacak mesaj stilleri (eğer globalde farklıysa) */
.admin-container .message {
    padding: 12px 18px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-size: 0.95rem;
    border: 1px solid transparent;
    text-align: left;
}
.admin-container .message.error-message {
    background-color: var(--transparent-red);
    color: var(--red);
    border-color: var(--dark-red);
}
.admin-container .message.success-message {
    background-color: rgba(60, 166, 60, 0.15);
    color: #5cb85c;
    border-color: #4cae4c;
}

.admin-container > h3 { /* "Mevcut Teçhizat Setleri" başlığı */
    margin-top: 30px;
    color: var(--light-gold);
    font-size: 1.8rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--darker-gold-1);
    font-family: var(--font);
}

.admin-container .empty-message {
    text-align: center;
    font-size: 1.1rem;
    color: var(--light-grey);
    padding: 30px 20px;
    background-color: var(--charcoal);
    border-radius: 6px;
    border: 1px dashed var(--darker-gold-1);
}
.admin-container .empty-message a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
}
.admin-container .empty-message a:hover {
    text-decoration: underline;
}

.table-responsive-wrapper {
    overflow-x: auto;
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.data-table { /* Genel admin data tablosu stili */
    width: 100%;
    min-width: 900px; /* İçeriğe göre ayarlanabilir */
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2);
    font-size: 0.9rem;
    color: var(--lighter-grey);
    vertical-align: middle;
}

.data-table thead th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    white-space: nowrap;
}

.data-table tbody tr:hover {
    background-color: var(--darker-gold-2);
}
.data-table tbody tr:last-child td {
    border-bottom: none;
}

.data-table td a {
    color: var(--turquase);
    text-decoration: none;
}
.data-table td a:hover {
    color: var(--light-turquase);
    text-decoration: underline;
}

/* .sets-table için özel ayarlar (data-table'ı genişletir) */
.sets-table thead th:nth-child(1) { /* ID */
    width: 50px;
    text-align: center;
}
.sets-table thead th:nth-child(2) { /* Görsel */
    width: 100px;
    text-align: center;
}
.sets-table thead th:last-child { /* İşlemler */
    min-width: 320px; /* Butonlara yer açmak için */
    text-align: right;
}

.set-thumbnail-small-list {
    width: 80px;
    height: 50px;
    object-fit: cover;
    border-radius: 3px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75em;
    color: var(--light-grey);
    margin: auto;
    text-align: center;
}
img.set-thumbnail-small-list {
    padding: 0; /* img ise padding olmaz */
}

.sets-table .actions-cell {
    text-align: right;
    white-space: nowrap;
}
.sets-table .actions-cell .btn,
.sets-table .actions-cell form {
    margin-left: 8px;
}
.sets-table .actions-cell .btn:first-child,
.sets-table .actions-cell form:first-child {
    margin-left: 0;
}
.sets-table .actions-cell form {
    display: inline-block;
}

/* Admin hızlı navigasyon ve mesaj stillerinin
   genel admin CSS'inden geldiği varsayılır. */
</style>
<main>
    <div class="admin-container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <div>
                <?php // Yeni set oluşturma da manage_sets yetkisine bağlı olabilir, zaten sayfa başında kontrol ediliyor. ?>
                <a href="new_loadout_set.php" class="btn btn-sm btn-success" style="margin-right:10px;">+ Yeni Teçhizat Seti Oluştur</a>
                <a href="<?php echo get_auth_base_url(); ?>/admin/index.php" class="btn btn-sm btn-secondary">&laquo; Admin Paneline Dön</a>
            </div>
        </div>

        <?php
        // Admin Hızlı Navigasyon Menüsünü Dahil Et
        if (defined('BASE_PATH') && file_exists(BASE_PATH . '/src/includes/admin_quick_navigation.php')) {
            require BASE_PATH . '/src/includes/admin_quick_navigation.php';
        }
        ?>

        <?php
        // Session mesajları
        if (isset($_SESSION['error_message'])) {
            echo '<p class="message error-message">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<p class="message success-message">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
            unset($_SESSION['success_message']);
        }
        ?>
        
        <h3 style="margin-top:30px; color:var(--light-gold);">Mevcut Teçhizat Setleri (<?php echo count($loadout_sets); ?>)</h3>
        <?php if (empty($loadout_sets)): ?>
            <p class="empty-message">Henüz hiç teçhizat seti oluşturulmamış. <a href="new_loadout_set.php">İlk seti şimdi oluşturun!</a></p>
        <?php else: ?>
            <div class="table-responsive-wrapper">
                <table class="data-table sets-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">ID</th>
                            <th style="width:100px;">Görsel</th>
                            <th>Set Adı / Rol</th>
                            <th>Açıklama</th>
                            <th>Oluşturan</th>
                            <th style="min-width:300px;">İşlemler</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loadout_sets as $set): ?>
                            <tr>
                                <td><?php echo $set['id']; ?></td>
                                <td>
                                    <?php if (!empty($set['set_image_path'])): ?>
                                        <img src="/public/<?php echo htmlspecialchars($set['set_image_path']); ?>" alt="<?php echo htmlspecialchars($set['set_name']); ?>" class="set-thumbnail-small-list">
                                    <?php else: ?>
                                        <div class="set-thumbnail-small-list placeholder-image">Görsel Yok</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($set['set_name']); ?></td>
                                <td><?php echo htmlspecialchars(mb_substr($set['set_description'] ?? '', 0, 50, 'UTF-8')) . (mb_strlen($set['set_description'] ?? '', 'UTF-8') > 50 ? '...' : ''); ?></td>
                                <td>
                                     <a href="/view_profile.php?user_id=<?php echo $set['user_id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($set['creator_username']); ?>
                                    </a>
                                </td>
                                <td class="actions-cell">
                                    <?php if ($can_manage_items): ?>
                                    <a style="color: black;" href="edit_loadout_items.php?set_id=<?php echo $set['id']; ?>" class="btn btn-sm btn-primary">Itemları Düzenle</a>
                                    <?php endif; ?>
                                    <?php // Set detaylarını düzenleme ve silme 'loadout.manage_sets' yetkisine bağlı (sayfa başındaki kontrol) ?>
                                    <a style="color: black;" href="edit_loadout_set_details.php?set_id=<?php echo $set['id']; ?>" class="btn btn-sm btn-warning">Set Detaylarını Düzenle</a>
                                    <form action="../../src/actions/handle_loadout_set.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu teçhizat setini ve tüm item atamalarını KALICI OLARAK silmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="set_id" value="<?php echo $set['id']; ?>">
                                        <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($set['set_image_path'] ?? ''); ?>">
                                        <input type="hidden" name="action" value="delete_set">
                                        <button type="submit" class="btn btn-sm btn-danger">Seti Sil</button>
                                    </form>
                                    <?php if (!$can_manage_items): ?>
                                        <small> (Item düzenleme yetkiniz yok)</small>
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

<?php require_once BASE_PATH . '/src/includes/footer.php'; // ?>
