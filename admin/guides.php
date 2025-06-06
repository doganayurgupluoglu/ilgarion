<?php
// public/admin/guides.php

require_once '../../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi
// require_once BASE_PATH . '/src/functions/guide_functions.php'; // Gerekirse (örn: slug için)

// require_admin(); // Genel admin kontrolü yerine spesifik yetki
require_permission($pdo, 'guide.view_all'); // Admin panelinde tüm rehberleri görme yetkisi

$page_title = "Rehber Yönetimi (Admin)";

$can_create_guide_admin = has_permission($pdo, 'guide.create');
$can_edit_any_guide = has_permission($pdo, 'guide.edit_all');
$can_delete_any_guide = has_permission($pdo, 'guide.delete_all');
$all_guides = [];

// Filtreleme için durum
$filter_status = $_GET['status'] ?? 'all'; // Örn: ?status=published

try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    $sql = "SELECT 
                g.id, 
                g.title, 
                g.slug,
                g.status,
                g.created_at, 
                g.updated_at,
                g.view_count,
                g.like_count,
                u.username AS author_username,
                g.user_id AS author_id
            FROM guides g
            JOIN users u ON g.user_id = u.id";

    $params = [];
    $allowed_statuses = ['published', 'draft', 'archived'];
    if ($filter_status !== 'all' && in_array($filter_status, $allowed_statuses)) {
        $sql .= " WHERE g.status = :status";
        $params[':status'] = $filter_status;
    }
    
    $sql .= " ORDER BY g.updated_at DESC, g.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Admin rehber listesi çekme hatası: " . $e->getMessage());
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['error_message'] = "Rehberler yüklenirken bir sorun oluştu: " . $e->getMessage();
}

require_once BASE_PATH . '/src/includes/header.php'; //
require_once BASE_PATH . '/src/includes/navbar.php'; //
?>
<style>
.guides-admin-container {
    width: 100%;
    max-width: 1600px;
    margin: 30px auto;
    padding: 20px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - 150px - 130px);
}

.guides-admin-container .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.guides-admin-container .page-header h1 {
    color: var(--gold);
    font-size: 2rem;
    font-family: var(--font);
    margin: 0;
}
.guides-admin-container .page-header div:last-child { /* Butonları saran div için */
    display: flex;
    gap: 10px; /* Butonlar arası boşluk */
}
/* .btn-success ve .btn-secondary stillerinin genel CSS'ten geldiği varsayılır */


.guides-admin-container .message {
    padding: 12px 18px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-size: 0.95rem;
    border: 1px solid transparent;
    text-align: left;
}
.guides-admin-container .message.error-message {
    background-color: var(--transparent-red);
    color: var(--red);
    border-color: var(--dark-red);
}
.guides-admin-container .message.success-message {
    background-color: rgba(60, 166, 60, 0.15);
    color: #5cb85c;
    border-color: #4cae4c;
}

.filters-bar {
    margin-bottom: 25px;
    padding: 15px 20px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.filters-bar strong {
    color: var(--light-gold);
    margin-right: 5px;
    font-size: 0.95rem;
}
.filters-bar .btn.active {
    box-shadow: 0 0 8px var(--transparent-gold); /* Aktif filtreye vurgu */
    filter: brightness(1.1);
}
/* .btn-primary ve .btn-outline-secondary stillerinin var olduğu varsayılır */


.guides-admin-container .empty-message {
    text-align: center;
    font-size: 1.1rem;
    color: var(--light-grey);
    padding: 30px 20px;
    background-color: var(--charcoal);
    border-radius: 6px;
    border: 1px dashed var(--darker-gold-1);
}
.guides-admin-container .empty-message a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: bold;
    margin-left: 5px;
}
.guides-admin-container .empty-message a:hover {
    text-decoration: underline;
}

.table-responsive-wrapper {
    overflow-x: auto;
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}

.guides-table {
    width: 100%;
    min-width: 1000px; /* Tablo içeriğine göre ayarlanabilir */
    border-collapse: collapse;
}

.guides-table th,
.guides-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2);
    font-size: 0.9rem;
    color: var(--lighter-grey);
    vertical-align: middle;
    white-space: nowrap; /* Uzun içeriklerin taşmaması için */
}
.guides-table td:nth-child(2) { /* Başlık sütunu */
    white-space: normal; /* Başlıklar alt satıra kayabilsin */
    min-width: 250px;
}


.guides-table thead th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
}
.guides-table thead th:last-child { /* İşlemler sütunu */
    min-width: 220px; /* PHP'deki inline style yerine */
    text-align: right;
}


.guides-table tbody tr:hover {
    background-color: var(--darker-gold-2);
}
.guides-table tbody tr:last-child td {
    border-bottom: none;
}

.guides-table td a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
}
.guides-table td a:hover {
    color: var(--light-turquase);
    text-decoration: underline;
}

/* Durum etiketleri için stiller */
.guides-table td[class*="status-"] {
    font-weight: bold;
    text-transform: capitalize;
    padding: 6px 10px; /* Etiket gibi görünmesi için */
    border-radius: 4px;
    font-size: 0.8em;
    text-align: center;
    color: var(--black); /* Çoğu durumda siyah iyi durur */
    display: inline-block; /* Sadece içeriği kadar yer kaplasın */
    min-width: 80px;
}
.status-published {
    background-color: var(--turquase); /* Veya bir yeşil tonu */
}
.status-draft {
    background-color: var(--light-gold);
    color: var(--darker-gold-2);
}
.status-archived {
    background-color: var(--grey);
    color: var(--lighter-grey);
}


.guides-table .actions-cell {
    text-align: right;
    white-space: nowrap;
}
.guides-table .actions-cell .btn,
.guides-table .actions-cell form {
    margin-left: 8px;
}
.guides-table .actions-cell .btn:first-child,
.guides-table .actions-cell form:first-child {
    margin-left: 0;
}
.guides-table .actions-cell form {
    display: inline-block; /* Formların yan yana durması için */
}
/* .btn-info, .btn-warning, .btn-danger, .btn-sm stillerinin
   genel CSS dosyanızda tanımlı olduğu varsayılır. */
</style>

<main>
    <div class="container guides-admin-container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($page_title); ?> (<?php echo count($all_guides); ?> Rehber)</h1>
            <div>
                <?php if ($can_create_guide_admin): ?>
                <a href="<?php echo get_auth_base_url(); ?>/new_guide.php" class="btn btn-success" style="margin-right:10px;">+ Yeni Rehber Oluştur</a>
                <?php endif; ?>
                <a href="<?php echo get_auth_base_url(); ?>/admin/index.php" class="btn btn-sm btn-secondary">&laquo; Admin Paneline Dön</a>
            </div>
        </div>
        <?php
        // Hızlı Yönetim Linklerini Dahil Et
        require_once BASE_PATH . '/src/includes/admin_quick_navigation.php';
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
        <div class="filters-bar">
            <strong>Filtrele (Durum):</strong>
            <a href="guides.php?status=all" class="btn btn-sm <?php echo ($filter_status === 'all' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Tümü</a>
            <a href="guides.php?status=published" class="btn btn-sm <?php echo ($filter_status === 'published' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Yayınlanmış</a>
            <a href="guides.php?status=draft" class="btn btn-sm <?php echo ($filter_status === 'draft' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Taslak</a>
            <a href="guides.php?status=archived" class="btn btn-sm <?php echo ($filter_status === 'archived' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Arşivlenmiş</a>
        </div>

        <?php if (empty($all_guides)): ?>
            <p class="empty-message">
                <?php echo ($filter_status !== 'all' ? 'Bu filtreyle eşleşen ' : ''); ?>Listelenecek rehber bulunmamaktadır.
                <?php if ($filter_status !== 'all'): ?>
                    <a href="guides.php?status=all">Tümünü Göster</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="table-responsive-wrapper">
                <table class="guides-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>Yazar</th>
                            <th>Durum</th>
                            <th>Okunma</th>
                            <th>Beğeni</th>
                            <th>Oluşturulma</th>
                            <th>Güncellenme</th>
                            <th style="min-width:200px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_guides as $guide): ?>
                            <tr>
                                <td><?php echo $guide['id']; ?></td>
                                <td>
                                    <a href="<?php echo get_auth_base_url(); ?>/guide_detail.php?slug=<?php echo htmlspecialchars($guide['slug']); ?>" target="_blank" title="Rehberi Görüntüle">
                                        <?php echo htmlspecialchars($guide['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo get_auth_base_url(); ?>/view_profile.php?user_id=<?php echo $guide['author_id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($guide['author_username']); ?>
                                    </a>
                                </td>
                                <td class="status-<?php echo htmlspecialchars($guide['status']); ?>">
                                    <?php 
                                        switch($guide['status']) {
                                            case 'published': echo 'Yayınlandı'; break;
                                            case 'draft': echo 'Taslak'; break;
                                            case 'archived': echo 'Arşivlendi'; break;
                                            default: echo ucfirst($guide['status']);
                                        }
                                    ?>
                                </td>
                                <td><?php echo $guide['view_count']; ?></td>
                                <td><?php echo $guide['like_count']; ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($guide['created_at'])); ?></td>
                                <td><?php echo $guide['updated_at'] ? date('d M Y H:i', strtotime($guide['updated_at'])) : '-'; ?></td>
                                <td class="actions-cell">
                                    <?php if ($can_edit_any_guide): ?>
                                    <a href="<?php echo get_auth_base_url(); ?>/new_guide.php?edit_id=<?php echo $guide['id']; ?>" class="btn btn-sm btn-warning">Düzenle</a>
                                    <?php endif; ?>
                                    <?php // Durum değiştirme butonları eklenebilir (AJAX veya form ile), bunun için de 'guide.edit_all' yetkisi kullanılabilir ?>
                                    <?php if ($can_delete_any_guide): ?>
                                    <form action="../../src/actions/handle_guide.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu rehberi ve tüm ilişkili verilerini KALICI OLARAK silmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="guide_id" value="<?php echo $guide['id']; ?>">
                                        <input type="hidden" name="action" value="delete_guide">
                                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (!$can_edit_any_guide && !$can_delete_any_guide): ?>
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

<?php require_once BASE_PATH . '/src/includes/footer.php'; // ?>
