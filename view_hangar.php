<?php
// public/view_hangar.php

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

require_login(); // Sayfayı görmek için giriş yapmış olmak yeterli

$profile_user_id = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $profile_user_id = (int)$_GET['user_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si.";
    header('Location: ' . get_auth_base_url() . '/members.php'); // Üye listesine yönlendir
    exit;
}

$viewed_user_data = null; // Artık tüm kullanıcı bilgilerini burada tutacağız
$page_title = "Hangar"; // Varsayılan
$hangar_ships_for_display = [];
$hangar_ships_data_for_js_modal = [];

// Rol öncelik sırası (renklendirme için)
$role_priority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];

try {
    // Görüntülenen kullanıcının bilgilerini ve rollerini çek
    $sql_user = "SELECT 
                    u.id, u.username, u.avatar_path, u.status,
                    u.ingame_name, u.discord_username,
                    (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS user_event_count,
                    (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS user_gallery_count,
                    (SELECT GROUP_CONCAT(r.name SEPARATOR ',') 
                     FROM user_roles ur 
                     JOIN roles r ON ur.role_id = r.id 
                     WHERE ur.user_id = u.id) AS user_roles_list
                 FROM users u WHERE u.id = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':user_id', $profile_user_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $viewed_user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$viewed_user_data || ($viewed_user_data['status'] !== 'approved' && !(is_user_logged_in() && (is_user_admin() || $_SESSION['user_id'] == $profile_user_id)))) {
        $_SESSION['error_message'] = "Kullanıcı bulunamadı veya profili görüntülenemiyor.";
        header('Location: ' . get_auth_base_url() . '/members.php');
        exit;
    }
    $page_title = htmlspecialchars($viewed_user_data['username']) . " Kullanıcısının Hangarı";

    // Kullanıcının hangarındaki gemileri çek
    $sql_hangar = "SELECT
                id, ship_api_id, ship_name, ship_manufacturer,
                ship_image_url, quantity, added_at, user_notes
            FROM user_hangar
            WHERE user_id = :profile_user_id
            ORDER BY ship_name ASC";
    $stmt_hangar = $pdo->prepare($sql_hangar);
    $stmt_hangar->bindParam(':profile_user_id', $profile_user_id, PDO::PARAM_INT);
    $stmt_hangar->execute();
    $hangar_ships_for_display = $stmt_hangar->fetchAll(PDO::FETCH_ASSOC);

    // Modal için JavaScript'e aktarılacak veriyi hazırla
    foreach ($hangar_ships_for_display as $ship) {
        // Modal caption için gemi adı, üretici ve adet bilgisi
        $description_for_modal = htmlspecialchars($ship['ship_name'], ENT_QUOTES, 'UTF-8');
        if (!empty($ship['ship_manufacturer'])) {
            $description_for_modal .= ' (' . htmlspecialchars($ship['ship_manufacturer'], ENT_QUOTES, 'UTF-8') . ')';
        }
        // $description_for_modal .= ' - Adet: ' . htmlspecialchars($ship['quantity'], ENT_QUOTES, 'UTF-8'); // Açıklamaya adet eklemeyelim, ayrı gösterilecek
        if (!empty($ship['user_notes'])) {
            // Kullanıcı notları modalda ayrı bir yerde gösterilebilir veya kısa bir özeti eklenebilir.
            // Şimdilik sadece gemi adı ve üreticiyi ana açıklama olarak alıyoruz.
        }

        $hangar_ships_data_for_js_modal[] = [
            // Galeri modalının beklediği alan adlarını kullanalım:
            'photo_id' => 'hangar_item_' . $ship['id'], // Benzersiz bir ID (beğeni için değil, sadece item takibi için)
            'image_path_full' => htmlspecialchars($ship['ship_image_url'] ?: 'https://via.placeholder.com/800x600.png?text=' . urlencode($ship['ship_name']), ENT_QUOTES, 'UTF-8'),
            'photo_description' => htmlspecialchars($ship['user_notes'] ?: ($ship['ship_name'] . ' gemisine ait not bulunmamaktadır.'), ENT_QUOTES, 'UTF-8'), // Ana açıklama olarak kullanıcı notları
            'uploader_user_id' => (int)$profile_user_id, // Hangar sahibi = yükleyen
            'uploader_username' => htmlspecialchars($viewed_user_data['username'], ENT_QUOTES, 'UTF-8'),
            'uploader_avatar_path_full' => !empty($viewed_user_data['avatar_path']) ? '/public/' . htmlspecialchars($viewed_user_data['avatar_path'], ENT_QUOTES, 'UTF-8') : '',
            'uploader_ingame_name' => htmlspecialchars($viewed_user_data['ingame_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'uploader_discord_username' => htmlspecialchars($viewed_user_data['discord_username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'uploader_event_count' => (int)($viewed_user_data['user_event_count'] ?? 0),
            'uploader_gallery_count' => (int)($viewed_user_data['user_gallery_count'] ?? 0),
            'uploader_roles_list' => htmlspecialchars($viewed_user_data['user_roles_list'] ?? '', ENT_QUOTES, 'UTF-8'),
            'uploaded_at_formatted' => "Adet: " . htmlspecialchars($ship['quantity'], ENT_QUOTES, 'UTF-8'), // Tarih yerine adet bilgisi
            'like_count' => 0, // Hangar gemileri için beğeni yok
            'user_has_liked' => false // Hangar gemileri için beğeni yok
        ];
    }

} catch (PDOException $e) {
    error_log("Kullanıcı hangarı (view_hangar.php) çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcının hangar bilgileri yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* view_hangar.php için Modern Stiller */
.view-hangar-page { /* .gallery-page-container ile benzer */
    width: 100%;
    max-width: 1700px;
    margin: 30px auto;
    padding: 25px 35px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 102px);
}

.page-header-controls { /* .gallery-header ile benzer */
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0px;
    padding-bottom: 20px;
    border-bottom: none;
}
.page-title-main { /* .gallery-header h2 ile benzer */
    color: var(--gold);
    font-size: 2.2rem; /* Biraz daha küçük olabilir */
    font-family: var(--font);
    margin: 0;
    text-align: center;
}
.btn-outline-turquase { /* Zaten style.css'de olabilir */
    color: var(--turquase); border-color: var(--turquase); background-color: transparent;
    padding: 8px 18px; font-size: 0.9rem; font-weight: 500; border-radius: 20px;
}
.btn-outline-turquase:hover { color: var(--white); background-color: var(--turquase); }
.btn-outline-turquase.btn-sm { padding: 6px 14px; font-size: 0.85rem; }


.empty-message { /* style.css'den */ }

.hangar-ship-grid-view { /* .gallery-grid ile benzer */
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* Kart genişliği ayarlanabilir */
    gap: 25px;
    margin-top: 30px;
}

.ship-display-card-item { /* .gallery-card ile benzer */
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.ship-display-card-item:hover {
    transform: translateY(-0px);
    box-shadow: 0 8px 20px rgba(var(--gold-rgb, 189, 145, 42), 0.15);
}

.ship-display-image-link { /* .gallery-image-link ile benzer */
    display: block;
    position: relative;
    overflow: hidden;
    border-radius: 10px 10px 0 0;
}
.ship-display-image { /* .gallery-image ile benzer */
    width: 100%;
    height: 200px; /* Gemi kartı için resim yüksekliği */
    object-fit: cover; /* veya contain, gemi görsellerine göre */
    display: block;
    transition: transform 0.4s ease;
    background-color: var(--black); /* Placeholder arka planı */
}
.ship-display-card-item:hover .ship-display-image {
    transform: scale(1.05);
}

.ship-display-card-info {
    padding: 15px 18px;
    background-color: var(--darker-gold-2);
    border-top: 1px solid var(--darker-gold-1);
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-radius: 0 0 10px 10px;
}
.ship-display-card-name {
    font-family: var(--font);
    color: var(--light-gold);
    font-size: 1.15rem; /* Biraz daha büyük */
    font-weight: 600;
    margin: 0 0 5px 0;
    line-height: 1.3;
}
.ship-display-card-manufacturer {
    font-family: var(--font);
    color: var(--light-grey);
    font-size: 0.8rem;
    margin-bottom: 8px;
    font-style: italic;
}
.ship-display-card-quantity {
    font-family: var(--font);
    color: var(--gold);
    font-size: 0.95rem;
    font-weight: 500;
    margin: 0;
}
.ship-user-notes-preview {
    font-size: 0.8rem;
    color: var(--lighter-grey);
    margin-top: 8px;
    font-style: italic;
    opacity: 0.8;
    max-height: 3.6em; /* Yaklaşık 3 satır */
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Modal Stilleri (style.css'deki #galleryModal ve .caption-v2-horizontal stilleri kullanılacak) */
/* Modal içindeki beğeni butonu bu sayfada gizlenecek veya olmayacak */
#galleryModal .caption-v2-horizontal .caption-v2-actions .like-button-modal,
#galleryModal .caption-v2-horizontal .caption-v2-actions .like-display-modal {
    /* Hangar modalında beğeni olmayacağı için gizleyebiliriz veya JS ile hiç oluşturmayabiliriz */
    /* display: none !important; // Bu, JS'de koşullu render tercih edilirse daha iyi */
}
.caption-v2 {
    min-width: 80%;
}
.like-button-modal {
    display: none;
}
</style>

<main class="main-content">
    <div class="container view-hangar-page">
        <div class="page-header-controls">
            <a href="view_profile.php?user_id=<?php echo $profile_user_id; ?>" class="btn btn-sm btn-outline-turquase">
                <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($viewed_user_data['username']); ?> Profiline Dön
            </a>
            <?php if (is_user_logged_in() && $_SESSION['user_id'] == $profile_user_id): ?>
                <a href="edit_hangar.php" class="btn btn-outline-turquase btn-sm">
                    <i class="fas fa-cog"></i> Hangarımı Düzenle
                </a>
            <?php endif; ?>
        </div>
        <h1 class="page-title-main">
            <?php echo $page_title; ?> (<?php echo count($hangar_ships_for_display); ?> Gemi Türü)
        </h1>

        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="empty-message" style="background-color: var(--transparent-red); color:var(--red); border-style:solid; border-color:var(--dark-red);"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
             <p class="empty-message" style="background-color: rgba(60, 166, 60, 0.2); color:#4CAF50; border-style:solid; border-color:#388E3C;"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
        <?php endif; ?>


        <?php if (empty($hangar_ships_for_display) && !(isset($_SESSION['error_message']) || isset($_SESSION['success_message'])) ): ?>
            <p class="empty-message">
                Bu kullanıcının hangarında henüz hiç gemi bulunmamaktadır.
                <?php if (is_user_logged_in() && $_SESSION['user_id'] == $profile_user_id): ?>
                    <br><a href="edit_hangar.php" style="color:var(--turquase); font-weight:bold; margin-top:10px; display:inline-block;">Hemen gemilerini ekle!</a>
                <?php endif; ?>
            </p>
        <?php elseif (!empty($hangar_ships_for_display)): ?>
            <div class="hangar-ship-grid-view">
                <?php foreach ($hangar_ships_for_display as $index => $ship): ?>
                    <div class="ship-display-card-item">
                        <a href="javascript:void(0);" class="ship-display-image-link" onclick="openHangarShipModal(<?php echo $index; ?>)">
                            <img src="<?php echo htmlspecialchars($ship['ship_image_url'] ?: 'https://via.placeholder.com/300x180.png?text=' . urlencode($ship['ship_name'])); ?>"
                                 alt="<?php echo htmlspecialchars($ship['ship_name']); ?>"
                                 class="ship-display-image">
                        </a>
                        <div class="ship-display-card-info">
                            <div>
                                <h5 class="ship-display-card-name">
                                    <?php echo htmlspecialchars($ship['ship_name']); ?>
                                </h5>
                                <?php if (!empty($ship['ship_manufacturer'])): ?>
                                <p class="ship-display-card-manufacturer">
                                    <?php echo htmlspecialchars($ship['ship_manufacturer']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="ship-display-card-quantity">
                                    Adet: <?php echo htmlspecialchars($ship['quantity']); ?>
                                </p>
                                <?php if (!empty($ship['user_notes'])): ?>
                                    <p class="ship-user-notes-preview" title="<?php echo htmlspecialchars($ship['user_notes']); ?>">
                                        Not: <?php echo htmlspecialchars(mb_substr($ship['user_notes'], 0, 50) . (mb_strlen($ship['user_notes']) > 50 ? '...' : '')); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php // Modal HTML'i footer.php'den gelecek. ?>

<script>
// Bu değişken, openHangarShipModal() fonksiyonunun modalı doğru verilerle doldurması için
// ve modals.js içindeki displayRichModalImageAtIndex tarafından kullanılacak.
// Hangar gemileri için 'like_count' ve 'user_has_liked' alanları 0 veya false olacak.
window.phpHangarShipDataForModal = <?php echo json_encode($hangar_ships_data_for_js_modal); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // modals.js içindeki openHangarShipModal fonksiyonu bu sayfada kullanılacak.
    // Popover.js de modal içindeki .user-info-trigger ile çalışacak.
    // Bu sayfaya özel ek JS gerekirse buraya eklenebilir.

    // Modal açıldığında beğeni butonunu gizle/değiştir (Hangar için beğeni yok)
    const originalOpenHangarModal = window.openHangarShipModal;
    window.openHangarShipModal = function(index) {
        if (typeof originalOpenHangarModal === 'function') {
            originalOpenHangarModal(index); // Önce modalı normal şekilde açsın
        }
        // Şimdi modal içindeki beğeni butonunu gizleyelim veya farklı bir şey gösterelim
        const modalLikeButtonContainer = document.querySelector('#galleryModal .caption-v2-horizontal .caption-v2-actions');
        if (modalLikeButtonContainer) {
            // modalLikeButtonContainer.innerHTML = '<small style="color:var(--light-grey); font-style:italic;">Hangar Gemi Detayı</small>';
            // Veya sadece gizle:
            modalLikeButtonContainer.style.display = 'none';
        }
    };
    // Modal kapandığında beğeni butonunu tekrar görünür yap (eğer galeri için kullanılacaksa)
    const originalCloseRichNavModal = window.closeRichNavModal;
    window.closeRichNavModal = function() {
        if (typeof originalCloseRichNavModal === 'function') {
            originalCloseRichNavModal();
        }
        const modalLikeButtonContainer = document.querySelector('#galleryModal .caption-v2-horizontal .caption-v2-actions');
        if (modalLikeButtonContainer) {
            modalLikeButtonContainer.style.display = ''; // Tekrar görünür yap
        }
    };
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>