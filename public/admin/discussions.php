<?php
// public/admin/discussions.php

require_once '../../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); 
require_permission($pdo, 'discussion.view_all'); // Admin panelinde tüm konuları görme yetkisi

$page_title = "Tartışma Yönetimi (Admin)";

$can_lock_topics = has_permission($pdo, 'discussion.topic.lock');
$can_pin_topics = has_permission($pdo, 'discussion.topic.pin');
$can_delete_any_topic = has_permission($pdo, 'discussion.topic.delete_all');
$all_topics = [];

$filter_locked = $_GET['locked_status'] ?? 'all';
$filter_pinned = $_GET['pinned_status'] ?? 'all';

try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    $sql = "SELECT 
                dt.id, 
                dt.title, 
                dt.created_at, 
                dt.last_reply_at,
                dt.reply_count,
                dt.is_locked,
                dt.is_pinned, /* SABİTLENMİŞ BİLGİSİ */
                dt.user_id AS topic_starter_id,
                u.username AS topic_starter_username
            FROM discussion_topics dt
            JOIN users u ON dt.user_id = u.id";

    $where_clauses = [];
    $params = []; // Parametreler için boş dizi

    if ($filter_locked === '1') {
        $where_clauses[] = "dt.is_locked = 1";
    } elseif ($filter_locked === '0') {
        $where_clauses[] = "dt.is_locked = 0";
    }

    if ($filter_pinned === '1') {
        $where_clauses[] = "dt.is_pinned = 1";
    } elseif ($filter_pinned === '0') {
        $where_clauses[] = "dt.is_pinned = 0";
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $sql .= " ORDER BY dt.is_pinned DESC, dt.last_reply_at DESC, dt.created_at DESC"; 
            
    $stmt = $pdo->prepare($sql);
    // execute() içine $params boş olsa bile göndermek sorun yaratmaz.
    // Eğer ileride $params dizisine :placeholder şeklinde parametreler eklenirse,
    // bu yapı çalışmaya devam edecektir.
    $stmt->execute($params); 
    $all_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Admin tartışma konularını çekme hatası: " . $e->getMessage());
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['error_message'] = "Tartışma konuları yüklenirken bir sorun oluştu: " . $e->getMessage();
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* admin/discussions.php için GÜNCELLENMİŞ Stiller */
.discussions-admin-container {
    width: 100%;
    max-width: 1600px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 102px);
}

/* Sayfa Üstü Kontroller (Başlık ve Admin Paneline Dön Butonu) */
.admin-page-top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px; /* Filtrelerden önce boşluk */
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.admin-page-top-controls .admin-page-title {
    color: var(--gold);
    font-size: 2.2rem;
    font-family: var(--font);
    margin: 0;
}
.admin-page-top-controls .btn-outline-secondary { /* Admin Paneline Dön Butonu */
    color: var(--turquase);
    border-color: var(--turquase);
    background-color: transparent;
    padding: 8px 18px;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 20px;
}
.admin-page-top-controls .btn-outline-secondary:hover {
    color: var(--white);
    background-color: var(--turquase);
}


/* Hızlı Navigasyon ve Mesaj Stilleri (mevcut) */
.admin-quick-navigation { /* ... (admin_quick_navigation.php'den gelen stil) ... */ }
.discussions-admin-container .message { /* ... (mevcut mesaj stilleri) ... */ }

/* Filtreleme Çubuğu */
.filters-bar {
    margin-bottom: 30px; /* Tablodan önce boşluk */
    padding: 20px 25px;
    background-color: rgba(var(--darker-gold-2-rgb, 26, 17, 1), 0.7);
    backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
    border-radius: 10px;
    display: flex;
    flex-direction: column; /* Filtre grupları alt alta */
    gap: 18px; /* Gruplar arası boşluk */
    border: 1px solid rgba(var(--darker-gold-1-rgb, 82, 56, 10), 0.6);
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 12px; /* Etiket ve butonlar arası */
    flex-wrap: wrap;
}
.filters-bar strong {
    color: var(--light-gold);
    margin-right: 8px;
    font-size: 1rem; /* Biraz büyütüldü */
    font-weight: 500;
}
.filters-bar .btn { /* Filtre butonları */
    padding: 7px 15px;
    font-size: 0.88rem;
    font-weight: 500;
    border-radius: 20px; /* Hap şeklinde */
    border-width: 1px;
    transition: all 0.2s ease;
}
.filters-bar .btn.active,
.filters-bar .btn-primary.active { /* Aktif filtre butonu */
    background-color: var(--gold);
    border-color: var(--gold);
    color: var(--darker-gold-2);
    box-shadow: 0 1px 5px var(--transparent-gold);
    transform: translateY(-1px);
}
.filters-bar .btn-outline-secondary:not(.active) {
    color: var(--lighter-grey);
    border-color: var(--grey);
    background-color: var(--grey);
}
.filters-bar .btn-outline-secondary:not(.active):hover {
    background-color: var(--darker-gold-1);
    border-color: var(--gold);
    color: var(--gold);
}


.empty-message { /* ... (mevcut stil) ... */ }
.table-responsive-wrapper { /* ... (mevcut stil) ... */ }
.topics-table { width: 100%; min-width: 900px; /* Genişletildi */ border-collapse: collapse; }
.topics-table th, .topics-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--darker-gold-2); font-size: 0.9rem; color: var(--lighter-grey); vertical-align: middle; }
.topics-table thead th { background-color: var(--darker-gold-1); color: var(--gold); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; white-space: nowrap; }
.topics-table thead th.actions-column-header { min-width: 320px; /* Butonlar için genişletildi */ text-align: right; }
.topics-table tbody tr:hover { background-color: var(--darker-gold-2); }
.topics-table td a { color: var(--turquase); text-decoration: none; font-weight: 500; }
.topics-table td a:hover { color: var(--light-turquase); text-decoration: underline; }
.status-badge { padding: 4px 9px; border-radius: 15px; font-size: 0.78em; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display:inline-block; }
.locked-topic { background-color: var(--dark-red); color: var(--white); }
.open-topic { background-color: var(--turquase); color: var(--black); }

.pinned-topic-icon { /* Sabitlenmiş konu ikonu için */
    color: var(--turquase); /* veya var(--gold) */
    margin-left: 8px;
    font-size: 1em; /* Başlıkla orantılı */
    vertical-align: middle;
}

.topics-table .actions-cell { text-align: right; white-space: nowrap; }
.topics-table .actions-cell .btn, .topics-table .actions-cell form { margin-left: 8px; /* Butonlar arası boşluk */}
.topics-table .actions-cell form { display: inline-block; }
/* .btn-sm, .btn-info, .btn-success, .btn-warning, .btn-danger, .btn-primary, .btn-secondary stilleri style.css'den */
.topics-table .actions-cell .btn-pin-action { /* Sabitleme butonu için özel stil */
    padding: 6px 10px; /* Diğer sm butonlarla uyumlu */
}
.topics-table .actions-cell .btn-pin-action i.fas {
    font-size: 0.9em; /* İkon boyutu */
}

</style>
<main>
    <div class="container discussions-admin-container">
        <div class="admin-page-top-controls">
            <h1 class="admin-page-title"><?php echo htmlspecialchars($page_title); ?> (<?php echo count($all_topics); ?> Konu)</h1>
            <a href="<?php echo get_auth_base_url(); ?>/admin/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Admin Paneline Dön
            </a>
        </div>

        <?php require_once BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <?php
        if (isset($_SESSION['error_message'])) { /* ... */ }
        if (isset($_SESSION['success_message'])) { /* ... */ }
        ?>
        <div class="filters-bar">
            <div class="filter-group">
                <strong>Durum (Kilit):</strong>
                <a href="discussions.php?locked_status=all&pinned_status=<?php echo htmlspecialchars($filter_pinned); ?>" class="btn btn-sm <?php echo ($filter_locked === 'all' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Tümü</a>
                <a href="discussions.php?locked_status=0&pinned_status=<?php echo htmlspecialchars($filter_pinned); ?>" class="btn btn-sm <?php echo ($filter_locked === '0' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Açık</a>
                <a href="discussions.php?locked_status=1&pinned_status=<?php echo htmlspecialchars($filter_pinned); ?>" class="btn btn-sm <?php echo ($filter_locked === '1' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Kilitli</a>
            </div>
            <div class="filter-group">
                <strong>Durum (Sabit):</strong>
                <a href="discussions.php?pinned_status=all&locked_status=<?php echo htmlspecialchars($filter_locked); ?>" class="btn btn-sm <?php echo ($filter_pinned === 'all' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Tümü</a>
                <a href="discussions.php?pinned_status=1&locked_status=<?php echo htmlspecialchars($filter_locked); ?>" class="btn btn-sm <?php echo ($filter_pinned === '1' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Sabitlenmiş</a>
                <a href="discussions.php?pinned_status=0&locked_status=<?php echo htmlspecialchars($filter_locked); ?>" class="btn btn-sm <?php echo ($filter_pinned === '0' ? 'btn-primary active' : 'btn-outline-secondary'); ?>">Sabitlenmemiş</a>
            </div>
        </div>

        <?php if (empty($all_topics)): ?>
            <p class="empty-message">
                <?php echo (($filter_locked !== 'all' || $filter_pinned !== 'all') ? 'Bu filtrelerle eşleşen ' : ''); ?>listelenecek tartışma konusu bulunmamaktadır.
                <?php if ($filter_locked !== 'all' || $filter_pinned !== 'all'): ?>
                    <a href="discussions.php?locked_status=all&pinned_status=all">Tümünü Göster</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="table-responsive-wrapper">
                <table class="topics-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>Başlatan</th>
                            <th>Yorum Sayısı</th>
                            <th>Son Aktivite</th>
                            <th>Durum</th>
                            <th class="actions-column-header">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_topics as $topic): ?>
                            <tr>
                                <td><?php echo $topic['id']; ?></td>
                                <td>
                                    <a href="<?php echo get_auth_base_url(); ?>/discussion_detail.php?id=<?php echo $topic['id']; ?>" target="_blank" title="Konuyu Görüntüle">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                    <?php if ($topic['is_pinned']): ?>
                                        <i class="fas fa-thumbtack pinned-topic-icon" title="Sabitlenmiş Konu"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_auth_base_url(); ?>/view_profile.php?user_id=<?php echo $topic['topic_starter_id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($topic['topic_starter_username']); ?>
                                    </a>
                                </td>
                                <td><?php echo $topic['reply_count']; ?></td>
                                <td><?php echo $topic['last_reply_at'] ? date('d M Y, H:i', strtotime($topic['last_reply_at'])) : date('d M Y, H:i', strtotime($topic['created_at'])); ?></td>
                                <td>
                                    <?php if ($topic['is_locked']): ?>
                                        <span class="status-badge locked-topic">Kilitli</span>
                                    <?php else: ?>
                                        <span class="status-badge open-topic">Açık</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="<?php echo get_auth_base_url(); ?>/discussion_detail.php?id=<?php echo $topic['id']; ?>" target="_blank" class="btn btn-sm btn-info">Gör</a>
                                    <?php if ($can_lock_topics): ?>
                                    <form action="../../src/actions/handle_topic_actions.php" method="POST" class="inline-form">
                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_lock">
                                        <input type="hidden" name="locked_status" value="<?php echo htmlspecialchars($filter_locked); ?>">
                                        <input type="hidden" name="pinned_status" value="<?php echo htmlspecialchars($filter_pinned); ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $topic['is_locked'] ? 'btn-success' : 'btn-warning'; ?>">
                                            <?php echo $topic['is_locked'] ? 'Aç' : 'Kilitle'; ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($can_pin_topics): ?>
                                    <form action="../../src/actions/handle_topic_actions.php" method="POST" class="inline-form">
                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_pin">
                                        <input type="hidden" name="locked_status" value="<?php echo htmlspecialchars($filter_locked); ?>">
                                        <input type="hidden" name="pinned_status" value="<?php echo htmlspecialchars($filter_pinned); ?>">
                                        <button type="submit" class="btn btn-sm btn-pin-action <?php echo $topic['is_pinned'] ? 'btn-secondary' : 'btn-primary'; ?>" title="<?php echo $topic['is_pinned'] ? 'Sabitlemeyi Kaldır' : 'Konuyu Sabitle'; ?>">
                                            <i class="fas <?php echo $topic['is_pinned'] ? 'fa-unlink' : 'fa-thumbtack'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($can_delete_any_topic): ?>
                                    <form action="../../src/actions/handle_topic_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu tartışma konusunu ve tüm yorumlarını KALICI OLARAK silmek istediğinizden emin misiniz?');">
                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                        <input type="hidden" name="action" value="delete_topic">
                                        <input type="hidden" name="locked_status" value="<?php echo htmlspecialchars($filter_locked); ?>">
                                        <input type="hidden" name="pinned_status" value="<?php echo htmlspecialchars($filter_pinned); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (!$can_lock_topics && !$can_pin_topics && !$can_delete_any_topic): ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // RGB Renkleri için CSS Değişkenlerini Tanımla (gerekirse)
    const rootStyles = getComputedStyle(document.documentElement);
    const setRgbVar = (varName) => {
        const hexColor = rootStyles.getPropertyValue(`--${varName}`).trim();
        if (hexColor && hexColor.startsWith('#')) {
            const r = parseInt(hexColor.slice(1, 3), 16);
            const g = parseInt(hexColor.slice(3, 5), 16);
            const b = parseInt(hexColor.slice(5, 7), 16);
            document.documentElement.style.setProperty(`--${varName}-rgb`, `${r}, ${g}, ${b}`);
        }
    };
    ['darker-gold-2', 'darker-gold-1', 'grey', 'gold', 'charcoal', 'turquase', 'red', 'dark-red'].forEach(setRgbVar);
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
