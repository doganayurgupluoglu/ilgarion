<?php
// public/admin/edit_loadout_items.php

ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover için

// require_admin(); 
require_permission($pdo, 'loadout.manage_items'); // Item yönetme yetkisi

$set_id = null;
if (isset($_GET['set_id']) && is_numeric($_GET['set_id'])) {
    $set_id = (int)$_GET['set_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz teçhizat seti ID'si.";
    header('Location: ' . get_auth_base_url() . '/admin/manage_loadout_sets.php');
    exit;
}

$loadout_set_info_for_page = null; // Setin genel bilgileri ve oluşturan bilgisi
$standard_slots_for_php = []; 
$assigned_items_for_js = [];  
$page_title = "Teçhizat Seti Itemleri";

// $GLOBALS['role_priority'] global olarak tanımlı olmalı
if (!isset($GLOBALS['role_priority'])) {
    $GLOBALS['role_priority'] = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];
}


if (!isset($pdo)) {
    die("KRİTİK HATA: Veritabanı bağlantısı kurulamadı.");
}

try {
    // Set bilgilerini ve oluşturan kullanıcı bilgilerini çek
    $sql_set_info = "SELECT 
                        ls.*, 
                        u.username AS creator_username, 
                        u.avatar_path AS creator_avatar_path,
                        u.ingame_name AS creator_ingame_name,
                        u.discord_username AS creator_discord_username,
                        (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS creator_event_count,
                        (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS creator_gallery_count,
                        (SELECT GROUP_CONCAT(r.name ORDER BY FIELD(r.name, '" . implode("','", $GLOBALS['role_priority']) . "') SEPARATOR ',') 
                         FROM user_roles ur_creator 
                         JOIN roles r ON ur_creator.role_id = r.id 
                         WHERE ur_creator.user_id = u.id) AS creator_roles_list
                     FROM loadout_sets ls
                     JOIN users u ON ls.user_id = u.id
                     WHERE ls.id = ?";
    $stmt_set = $pdo->prepare($sql_set_info);
    $stmt_set->execute([$set_id]);
    $loadout_set_info_for_page = $stmt_set->fetch(PDO::FETCH_ASSOC);

    if (!$loadout_set_info_for_page) {
        $_SESSION['error_message'] = "Düzenlenecek teçhizat seti bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/admin/manage_loadout_sets.php');
        exit;
    }
    $page_title = "Item Düzenle: " . htmlspecialchars($loadout_set_info_for_page['set_name']);

    // Standart slotları çek
    $stmt_std_slots = $pdo->query("SELECT id, slot_name, slot_type FROM equipment_slots WHERE is_standard = 1 ORDER BY display_order ASC, slot_name ASC");
    $standard_slots_for_php = $stmt_std_slots->fetchAll(PDO::FETCH_ASSOC);

    // Atanmış item'ları çek
    $stmt_assigned = $pdo->prepare(
        "SELECT li.item_name, li.item_api_uuid, li.item_type_api, li.item_manufacturer_api, 
                li.equipment_slot_id, li.custom_slot_name, 
                es.slot_name AS standard_slot_name_from_db 
         FROM loadout_set_items li 
         LEFT JOIN equipment_slots es ON li.equipment_slot_id = es.id
         WHERE li.loadout_set_id = ?"
    );
    $stmt_assigned->execute([$set_id]);
    $raw_assigned_items = $stmt_assigned->fetchAll(PDO::FETCH_ASSOC);

    foreach($raw_assigned_items as $item) {
        $slot_key_js = '';
        if (!empty($item['equipment_slot_id']) && !empty($item['standard_slot_name_from_db'])) {
            $slot_key_js = htmlspecialchars($item['standard_slot_name_from_db'], ENT_QUOTES, 'UTF-8');
        } elseif (!empty($item['custom_slot_name'])) {
            $slot_key_js = 'custom_' . str_replace(' ', '_', htmlspecialchars($item['custom_slot_name'], ENT_QUOTES, 'UTF-8'));
        }
        if (!empty($slot_key_js)) {
            $assigned_items_for_js[$slot_key_js] = [
                'slot_id' => $item['equipment_slot_id'], 
                'custom_slot_name' => $item['custom_slot_name'], 
                'item_name' => $item['item_name'],
                'item_api_uuid' => $item['item_api_uuid'],
                'item_type_api' => $item['item_type_api'],
                'item_manufacturer_api' => $item['item_manufacturer_api']
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Teçhizat seti item düzenleme sayfası yükleme hatası (Set ID: $set_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Sayfa yüklenirken bir veritabanı sorunu oluştu.";
    // Hata durumunda $loadout_set_info_for_page null kalabilir, aşağıdaki HTML'de kontrol edilecek.
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* edit_loadout_items.php için Stiller (mevcut stil dosyanızla birleştirin) */
.loadout-editor-page-main {
    font-family: var(--font);
    color: var(--lighter-grey);
}
.loadout-items-editor-container {
    width: 100%;
    max-width: 1300px;
    margin: 30px auto;
    padding: 20px;
}
.loadout-items-editor-container .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start; /* Başlık ve butonlar için daha iyi hizalama */
    gap: 20px;
    margin-bottom: 20px; /* Hızlı navigasyondan önce boşluk */
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.loadout-items-editor-container .page-header div:first-child { flex-grow: 1; }
.loadout-items-editor-container .page-header h1 {
    color: var(--gold); font-size: 2rem; font-family: var(--font);
    margin: 0 0 8px 0; line-height: 1.3;
}
.loadout-items-editor-container .page-header .set-meta-info { /* Set ID, Açıklama ve Oluşturan için */
    font-size: 0.85em; color: var(--light-grey); display: block; margin-bottom: 5px;
}
.loadout-items-editor-container .page-header .set-meta-info strong { color: var(--lighter-grey); }
.set-creator-info-items-page { /* Oluşturan bilgisi için (popover ile) */
    display: inline-flex; align-items: center; gap: 6px; font-size:0.9em;
}
/* Popover için .user-info-trigger class'ı ve diğerleri style.css'den gelecek */
.creator-avatar-small-items-page { width: 22px; height: 22px; border-radius:50%; object-fit:cover; }
.creator-name-link-items-page { /* Renkler popover.js ve inline style ile */ }

.set-image-preview-items-page { /* Sağdaki küçük resim */
    max-width: 100px; max-height: 75px; object-fit: cover;
    border-radius: 6px; border: 1px solid var(--darker-gold-1);
    margin-left: 20px; flex-shrink: 0;
}
.loadout-items-editor-container .page-header .btn-secondary { align-self: center; }

/* Diğer stiller (slot listesi, item arama vb.) mevcut edit_loadout_items.php'deki gibi kalabilir */
.loadout-editor-grid-layout { display: grid; grid-template-columns: minmax(350px, 1.2fr) minmax(350px, 1fr); gap: 30px; }
.slots-summary-column, .item-interaction-column { background-color: var(--charcoal); padding: 25px; border-radius: 8px; border: 1px solid var(--darker-gold-1); display: flex; flex-direction: column; }
.column-title { color: var(--light-gold); font-size: 1.5rem; margin-top: 0; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--darker-gold-2); font-family: var(--font); }
.slot-summary-list { list-style-type: none; padding: 0; margin: 0 0 20px 0; flex-grow: 1; max-height: 65vh; overflow-y: auto; border: 1px solid var(--darker-gold-2); border-radius: 4px; background-color: var(--darker-gold-2); }
.slot-summary-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-bottom: 1px solid var(--charcoal); background-color: var(--grey); cursor: pointer; transition: background-color 0.2s, border-left-color 0.2s; }
.slot-summary-item:last-child { border-bottom: none; }
.slot-summary-item:hover { background-color: var(--darker-gold-1); }
.slot-summary-item.selected-slot { background-color: var(--darker-gold-1); border-left: 3px solid var(--gold); padding-left: 12px; }
.slot-summary-item.slot-empty .assigned-item-display-name { color: var(--light-grey); font-style: italic; }
.slot-summary-item .slot-name-display { font-weight: 500; color: var(--lighter-grey); margin-right: 10px; white-space: nowrap; }
.slot-summary-item .assigned-item-display-name { flex-grow: 1; text-align: right; color: var(--white); font-size: 0.9em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 10px; }
.remove-item-btn { background-color: transparent; color: var(--dark-red); border: none; font-size: 1.2rem; font-weight: bold; cursor: pointer; padding: 0 3px; line-height: 1; margin-left: 5px; }
.remove-item-btn:hover { color: var(--red); }
.custom-slot-adder-form { margin-top: auto; padding-top: 15px; border-top: 1px solid var(--darker-gold-2); }
.custom-slot-adder-form label { display: block; color: var(--lighter-grey); margin-bottom: 8px; font-size: 0.9rem; }
.custom-slot-adder-form .input-button-group { display: flex; gap: 10px; }
.custom-slot-adder-form .input-button-group input.form-control-sm { flex-grow: 1; }
.btn-outline-turquase { color: var(--turquase); border-color: var(--turquase); background-color: transparent; }
.btn-outline-turquase:hover { color: var(--white); background-color: var(--turquase); border-color: var(--turquase); }
.item-search-box { margin-bottom: 20px; }
.item-search-box label { display: block; color: var(--lighter-grey); margin-bottom: 8px; font-size: 0.95rem; font-weight: 500; }
.item-search-box .search-controls-wrapper { display:flex; gap:10px; align-items:center;}
.item-search-box .search-controls-wrapper input[type="text"]{ flex-grow:1; margin-bottom:0;}
.api-item-results-list { margin-top: 15px; background-color: var(--darker-gold-2); border-radius: 6px; border: 1px solid var(--darker-gold-1); min-height: 150px; flex-grow: 1; max-height: calc(65vh - 100px); overflow-y: auto; padding: 8px; }
.search-placeholder-text { color: var(--light-grey); font-style: italic; text-align: center; padding: 20px 10px; font-size: 0.9em; }
.api-search-item-no-image { padding: 10px 12px; color: var(--lighter-grey); cursor: pointer; border-bottom: 1px solid var(--grey); transition: background-color 0.2s ease, color 0.2s ease; }
.api-search-item-no-image:last-child { border-bottom: none; }
.api-search-item-no-image:hover, .api-search-item-no-image.is-highlighted { background-color: var(--darker-gold-1); color: var(--gold); }
.search-item-details-no-image .item-name { display: block; font-weight: 600; color: var(--light-gold); margin-bottom: 3px; font-size: 0.95em; }
.api-search-item-no-image:hover .search-item-details-no-image .item-name { color: var(--gold); }
.search-item-details-no-image .item-type-manufacturer { display: block; font-size: 0.8em; color: var(--light-grey); }
.selected-item-feedback-area { margin-top: 20px; padding: 15px; background-color: var(--darker-gold-1); border-radius: 6px; border: 1px solid var(--gold); }
.selected-item-feedback-area.hidden { display: none; }
.feedback-text { color: var(--light-turquase); font-size: 0.95em; font-weight: 500; margin:0; text-align: center; }
.feedback-text.error { color: var(--red); }
.form-actions-bottom { margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--darker-gold-1); text-align: center; }
.btn-lg { padding: 0.7rem 1.5rem; font-size: 1.2rem; border-radius: 0.3rem; font-weight: bold; }
.loadout-items-editor-container .form-control, .loadout-items-editor-container .form-control-sm { padding: 0.375rem 0.75rem; font-size: 0.9rem; font-family: var(--font); color: var(--white); background-color: var(--grey); border: 1px solid var(--darker-gold-1); border-radius: 0.25rem; width: 100%; box-sizing: border-box; }
.loadout-items-editor-container .form-control-sm { padding: 0.3rem 0.6rem; font-size: 0.85rem; }
.loadout-items-editor-container .form-control:focus, .loadout-items-editor-container .form-control-sm:focus { border-color: var(--gold); outline: 0; box-shadow: 0 0 0 0.2rem var(--transparent-gold); }
@media (max-width: 992px) { .loadout-editor-grid-layout { grid-template-columns: 1fr; } .item-interaction-column { margin-top: 30px; } .slot-summary-list, .api-item-results-list { max-height: 40vh; } }
@media (max-width: 768px) { .loadout-items-editor-container .page-header { flex-direction: column; align-items: center; text-align: center; } .loadout-items-editor-container .page-header .btn-secondary { margin-top: 15px; } .set-image-preview-items-page { margin-top: 10px; margin-left: 0; } .item-search-box .search-controls-wrapper { flex-direction: column; align-items: stretch; } .item-search-box .search-controls-wrapper .btn { width: 100%; } }

</style>
<main class="loadout-editor-page-main">
    <div class="container loadout-items-editor-container">
        <div class="page-header">
            <div>
                <h1><?php echo $page_title; ?></h1>
                <?php if ($loadout_set_info_for_page): ?>
                    <small class="set-meta-info">
                        Set ID: <?php echo htmlspecialchars($loadout_set_info_for_page['id']); ?> | 
                        Açıklama: <?php echo htmlspecialchars(mb_substr($loadout_set_info_for_page['set_description'] ?? 'Yok', 0, 70) . (mb_strlen($loadout_set_info_for_page['set_description'] ?? '') > 70 ? '...' : '')); ?>
                    </small>
                    <div class="set-creator-info-items-page">
                        Oluşturan: 
                        <?php
                        $creator_data_for_popover_items_page = [
                            'id' => $loadout_set_info_for_page['user_id'], // Seti oluşturanın ID'si
                            'username' => $loadout_set_info_for_page['creator_username'],
                            'avatar_path' => $loadout_set_info_for_page['creator_avatar_path'],
                            'ingame_name' => $loadout_set_info_for_page['creator_ingame_name'],
                            'discord_username' => $loadout_set_info_for_page['creator_discord_username'],
                            'user_event_count' => $loadout_set_info_for_page['creator_event_count'],
                            'user_gallery_count' => $loadout_set_info_for_page['creator_gallery_count'],
                            'user_roles_list' => $loadout_set_info_for_page['creator_roles_list']
                        ];
                        echo render_user_info_with_popover(
                            $pdo,
                            $creator_data_for_popover_items_page,
                            'creator-name-link-items-page',
                            'creator-avatar-small-items-page',
                            ''
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($loadout_set_info_for_page && !empty($loadout_set_info_for_page['set_image_path'])): ?>
                <img src="/public/<?php echo htmlspecialchars($loadout_set_info_for_page['set_image_path']); ?>" alt="Set Görseli" class="set-image-preview-items-page">
            <?php endif; ?>
            <a href="manage_loadout_sets.php" class="btn btn-sm btn-secondary">&laquo; Set Listesine Dön</a>
        </div>
        
        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <?php if (!$loadout_set_info_for_page): ?>
            <p class="message error-message">Teçhizat seti bilgileri yüklenemedi.</p>
        <?php else: ?>
            <?php // Session mesajları ?>
            <form action="../../src/actions/handle_loadout_items.php" method="POST" id="loadoutItemsForm">
                <input type="hidden" name="loadout_set_id" value="<?php echo $set_id; ?>">
                <input type="hidden" name="action" value="save_loadout_items">

                <div class="loadout-editor-grid-layout">
                    <div class="slots-summary-column">
                        <h3 class="column-title">Slot Durumu</h3>
                        <ul class="slot-summary-list" id="slotSummaryList">
                            {/* JS bu UL'yi dolduracak */}
                        </ul>
                        <div class="custom-slot-adder-form form-group">
                            <label for="customSlotNameInput">Yeni Özel Slot Ekle:</label>
                            <div class="input-button-group">
                                <input type="text" id="customSlotNameInput" class="form-control form-control-sm" placeholder="Özel slot adı...">
                                <button type="button" id="addCustomSlotButton" class="btn btn-sm btn-outline-turquase">Ekle</button>
                            </div>
                        </div>
                    </div>

                    <div class="item-interaction-column">
                        <h3 class="column-title">Item Ekle/Değiştir</h3>
                        <div class="form-group item-search-box">
                            <label for="item_search_query">Item Ara (API):</label>
                            <div class="search-controls-wrapper">
                                <input type="text" id="item_search_query" class="form-control form-control-sm" placeholder="Aranacak item adını yazın...">
                                <button type="button" id="searchItemApiButton" class="btn btn-sm btn-info">Ara</button>
                            </div>
                        </div>
                        
                        <div id="itemSearchResults" class="api-item-results-list">
                            <p class="search-placeholder-text">Arama sonuçları burada görünecek.</p>
                        </div>

                        <div id="selectedItemFeedbackArea" class="selected-item-feedback-area hidden">
                            <p id="selectedItemFeedbackText" class="feedback-text"></p>
                        </div>
                    </div>
                </div>

                <div class="form-actions-bottom">
                    <button type="submit" class="btn btn-primary btn-lg">Teçhizat Seti Değişikliklerini Kaydet</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
    // PHP'den gelen verileri JS'e aktar
    window.phpAssignedLoadoutItems = <?php echo json_encode($assigned_items_for_js ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    window.phpStandardSlots = <?php echo json_encode(array_map(function($slot) {
        return [
            'id' => (int)($slot['id'] ?? 0), 
            'name' => trim($slot['slot_name'] ?? 'HATA_SLOT_ADI_YOK'), 
            'type' => trim(strtolower($slot['slot_type'] ?? '')) 
        ];
    }, $standard_slots_for_php ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php // loadout_editor.js zaten footer.php'de yüklenecek ?>
<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
