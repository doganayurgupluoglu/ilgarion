<?php
// public/admin/events.php

// Hata gösterimini aktif et (sorun çözülünce kaldırılabilir)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Temel yapılandırma (BASE_PATH ve $pdo'yu tanımlar)
// Bu dosya public/admin/ altında olduğu için src/config/database.php'ye ../../ ile erişir.
require_once '../../src/config/database.php'; // <<< DÜZELTİLDİ

// 2. Yetkilendirme fonksiyonları
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); // Genel admin kontrolü yerine spesifik yetki
require_permission($pdo, 'event.view_all'); // Admin panelinde tüm etkinlikleri görme yetkisi

$page_title = "Etkinlik Yönetimi (Admin)";

$can_create_event_admin = has_permission($pdo, 'event.create'); // Bu yetki normalde kullanıcılar için, adminler de sahip olabilir.
$can_edit_any_event = has_permission($pdo, 'event.edit_all');
$can_delete_any_event = has_permission($pdo, 'event.delete_all');
// Etkinlik durumunu değiştirme (iptal/aktif) için de event.edit_all kullanılabilir veya yeni bir yetki (örn: event.manage_status) eklenebilir.
// Şimdilik event.edit_all varsayalım.

$all_events = [];
$filter_status = $_GET['status'] ?? 'all';

try {
    $sql = "SELECT
                e.id AS event_id,
                e.title,
                e.event_datetime,
                e.status,
                e.created_by_user_id,
                u.username AS creator_username
            FROM events e
            JOIN users u ON e.created_by_user_id = u.id";

    $params = [];
    if ($filter_status !== 'all' && in_array($filter_status, ['active', 'past', 'cancelled'])) {
        $sql .= " WHERE e.status = :status";
        $params[':status'] = $filter_status;
    }
    $sql .= " ORDER BY e.event_datetime DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin etkinlik listeleme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlikler listelenirken bir veritabanı hatası oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php'; // Bu header içinde <head> etiketi ve CSS dosyasının linki olmalı
require_once BASE_PATH . '/src/includes/navbar.php'; //

$status_translations = [
    'active' => 'Aktif',
    'past' => 'Geçmiş',
    'cancelled' => 'İptal Edilmiş'
];
?>

<main class="main-content">
    <div class="container admin-container">
        <div class="page-header">
            <h2 class="page-title"><?php echo htmlspecialchars($page_title); ?> (<?php echo count($all_events); ?>)</h2>
            <?php if ($can_create_event_admin): // Adminin de yeni etkinlik oluşturma yetkisi varsa butonu göster ?>
            <a href="<?php echo get_auth_base_url(); ?>/create_event.php" class="btn btn-success btn-create-event">+ Yeni Etkinlik Oluştur</a>
            <?php endif; ?>
        </div>
           <?php
        // Hızlı Yönetim Linklerini Dahil Et
        require_once BASE_PATH . '/src/includes/admin_quick_navigation.php';
        ?>
        <div class="status-filters">
            Filtrele:
            <a href="events.php?status=all" class="btn btn-sm filter-btn <?php echo ($filter_status === 'all' ? 'btn-primary' : 'btn-light'); ?>">Tümü</a>
            <a href="events.php?status=active" class="btn btn-sm filter-btn <?php echo ($filter_status === 'active' ? 'btn-primary' : 'btn-light'); ?>">Aktif</a>
            <a href="events.php?status=past" class="btn btn-sm filter-btn <?php echo ($filter_status === 'past' ? 'btn-primary' : 'btn-light'); ?>">Geçmiş</a>
            <a href="events.php?status=cancelled" class="btn btn-sm filter-btn <?php echo ($filter_status === 'cancelled' ? 'btn-primary' : 'btn-light'); ?>">İptal Edilmiş</a>
        </div>

        <?php if (empty($all_events) && $filter_status !== 'all'): ?>
            <p class="info-message">Bu filtreyle eşleşen etkinlik bulunmamaktadır.</p>
        <?php elseif (empty($all_events)): ?>
             <p class="info-message">Gösterilecek hiç etkinlik bulunmamaktadır.</p>
        <?php else: ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Başlık</th>
                        <th>Tarih/Saat</th>
                        <th>Oluşturan</th>
                        <th>Durum</th>
                        <th class="th-actions">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_events as $event): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['event_id']); ?></td>
                            <td><a href="<?php echo get_auth_base_url(); ?>/event_detail.php?id=<?php echo $event['event_id']; ?>"><?php echo htmlspecialchars($event['title']); ?></a></td>
                            <td><?php echo date('d M Y, H:i', strtotime($event['event_datetime'])); ?></td>
                            <td><?php echo htmlspecialchars($event['creator_username']); ?> (ID: <?php echo $event['created_by_user_id']; ?>)</td>
                            <td><?php echo htmlspecialchars($status_translations[$event['status']] ?? ucfirst($event['status'])); ?></td>
                            <td class="td-actions">
                                <?php if ($can_edit_any_event): ?>
                                <a href="<?php echo get_auth_base_url(); ?>/edit_event.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-warning action-btn">Düzenle</a>
                                <?php endif; ?>

                                <?php if ($can_edit_any_event): // Durum değiştirme için de edit_all yetkisi varsayılıyor ?>
                                <?php if ($event['status'] === 'active'): ?>
                                    <form action="../../src/actions/handle_event_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu etkinliği iptal etmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                        <input type="hidden" name="action" value="cancel_event">
                                        <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger action-btn">İptal Et</button>
                                    </form>
                                <?php elseif ($event['status'] === 'cancelled' || $event['status'] === 'past'): ?>
                                     <form action="../../src/actions/handle_event_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu etkinliği tekrar aktif yapmak istediğinizden emin misiniz?');">
                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                        <input type="hidden" name="action" value="activate_event">
                                        <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                        <button type="submit" class="btn btn-sm btn-success action-btn">Aktif Et</button>
                                    </form>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($can_delete_any_event): ?>
                                <form action="../../src/actions/handle_event_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu etkinliği KALICI OLARAK silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">
                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">Sil</button>
                                </form>
                                <?php endif; ?>
                                <?php if (!$can_edit_any_event && !$can_delete_any_event): ?>
                                    Yetki Yok
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php'; //
?>
