<?php
// public/loadout_detail.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover için

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$set_id = null;
if (isset($_GET['set_id']) && is_numeric($_GET['set_id'])) {
    $set_id = (int)$_GET['set_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz teçhizat seti ID'si.";
    header('Location: ' . get_auth_base_url() . '/loadouts.php');
    exit;
}

$loadout_set_details = null;
$standard_slot_items = []; 
$custom_slot_items = [];   
$page_title = "Teçhizat Seti Detayı";

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

// $GLOBALS['role_priority'] global olarak tanımlı olmalı
if (!isset($GLOBALS['role_priority'])) {
    $GLOBALS['role_priority'] = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];
}

$slot_icons = [
    'Kask' => 'fas fa-hard-hat',
    'Gövde Zırhı' => 'fas fa-user-shield',
    'Kol Zırhları' => 'fas fa-hand-paper',
    'Bacak Zırhları' => 'fas fa-shoe-prints',
    'Alt Giyim' => 'fas fa-tshirt',
    'Sırt Çantası' => 'fas fa-briefcase',
    'Birincil Silah 1' => 'fa-solid fa-person-rifle',
    'Birincil Silah 2 (Sırtta)' => 'fa-solid fa-person-military-rifle',
    'İkincil Silah (Tabanca veya Medgun)' => 'fa-solid fa-gun',
    'Yardımcı Modül/Gadget 1' => 'fas fa-toolbox',
    'Medikal Araç' => 'fas fa-medkit',
    'Multi-Tool Attachment' => 'fas fa-tools',
    'default' => 'fas fa-cube'
];


try {
    // SQL sorgusuna creator için popover'da kullanılacak tüm alanlar zaten ekliydi.
    // Ayrıca status ve visibility sütunlarını da çekiyoruz.
    $stmt_set = $pdo->prepare("SELECT ls.*, u.username AS creator_username, u.id AS creator_id,
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
                               WHERE ls.id = ?");
    $stmt_set->execute([$set_id]);
    $loadout_set_details = $stmt_set->fetch(PDO::FETCH_ASSOC);

    if (!$loadout_set_details) {
        $_SESSION['error_message'] = "Teçhizat seti bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/loadouts.php');
        exit;
    }
    $page_title = htmlspecialchars($loadout_set_details['set_name']);

    // Yetkilendirme Kontrolü
    $can_view_this_loadout = false;
    if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'loadout.view_all', $current_user_id))) {
        $can_view_this_loadout = true;
    } elseif ($loadout_set_details['status'] === 'published') {
        if ($loadout_set_details['visibility'] === 'public' && (has_permission($pdo, 'loadout.view_public', $current_user_id) || !$current_user_is_logged_in) ) {
            $can_view_this_loadout = true;
        } elseif ($current_user_is_approved && $loadout_set_details['visibility'] === 'members_only' && has_permission($pdo, 'loadout.view_members_only', $current_user_id)) {
            $can_view_this_loadout = true;
        } elseif ($current_user_is_approved && $loadout_set_details['visibility'] === 'faction_only') {
            $user_role_ids_loadout_detail = [];
            if ($current_user_id) {
                $stmt_user_roles_ld = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :user_id");
                $stmt_user_roles_ld->execute([':user_id' => $current_user_id]);
                $user_role_ids_loadout_detail = $stmt_user_roles_ld->fetchAll(PDO::FETCH_COLUMN);
            }
            if (!empty($user_role_ids_loadout_detail)) {
                $stmt_set_roles_ld = $pdo->prepare("SELECT role_id FROM loadout_set_visibility_roles WHERE set_id = ?");
                $stmt_set_roles_ld->execute([$set_id]);
                $allowed_role_ids_for_set = $stmt_set_roles_ld->fetchAll(PDO::FETCH_COLUMN);
                if (!empty(array_intersect($user_role_ids_loadout_detail, $allowed_role_ids_for_set))) {
                    $can_view_this_loadout = true;
                }
            }
        }
    }
    // Kullanıcı kendi özel veya taslak setini her zaman görebilir
    if ($current_user_id && $loadout_set_details['user_id'] == $current_user_id) {
        $can_view_this_loadout = true;
    }

    if (!$can_view_this_loadout) {
        $_SESSION['error_message'] = "Bu teçhizat setini görüntüleme yetkiniz bulunmamaktadır veya set yayında değil.";
        header('Location: ' . get_auth_base_url() . '/loadouts.php');
        exit;
    }

    // Eğer buraya kadar geldiyse, kullanıcı seti görebilir. Item'ları çek.
    $sql_items = "SELECT 
                    li.item_name, 
                    li.item_api_uuid, 
                    li.item_type_api, 
                    li.item_manufacturer_api,
                    li.custom_slot_name,
                    es.slot_name AS standard_slot_name,
                    es.display_order AS standard_slot_display_order
                  FROM loadout_set_items li
                  LEFT JOIN equipment_slots es ON li.equipment_slot_id = es.id
                  WHERE li.loadout_set_id = :set_id
                  ORDER BY es.display_order ASC, es.slot_name ASC, li.custom_slot_name ASC, li.item_name ASC";
                  
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->bindParam(':set_id', $set_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $items_raw = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items_raw as $item_data) {
        if (!empty($item_data['standard_slot_name'])) {
            $slot_key = sprintf('%03d_%s', $item_data['standard_slot_display_order'] ?? 999, $item_data['standard_slot_name']);
            $standard_slot_items[$slot_key]['name'] = $item_data['standard_slot_name'];
            $standard_slot_items[$slot_key]['items'][] = $item_data;
        } elseif (!empty($item_data['custom_slot_name'])) {
            $custom_slot_items[$item_data['custom_slot_name']][] = $item_data;
        } else {
            // Bu durum normalde oluşmamalı, her item ya standart ya da özel bir slota atanmalı.
            // Güvenlik için 'Diğer' kategorisine atayabiliriz.
            $custom_slot_items['Diğer (Atanmamış Slot)'][] = $item_data;
        }
    }
    ksort($standard_slot_items); 
    ksort($custom_slot_items);   

} catch (PDOException $e) {
    error_log("Teçhizat seti detayı çekme hatası (Set ID: $set_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Teçhizat seti detayı yüklenirken bir sorun oluştu.";
    // Hata durumunda $loadout_set_details null kalacak ve aşağıdaki HTML'de kontrol edilecek.
}

$is_set_owner = (is_user_logged_in() && isset($loadout_set_details['user_id']) && isset($_SESSION['user_id']) && $loadout_set_details['user_id'] == $_SESSION['user_id']);
$is_admin_user = is_user_admin();

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* loadout_detail.php için Modernize Edilmiş Stiller v6 - İnce Ayar */
.loadout-detail-page-v4 { 
    width: 100%;
    max-width: 1600px; 
    margin: 40px auto;
    padding: 0 20px; 
    font-family: var(--font);
    color: var(--lighter-grey);
}

.page-top-nav-controls-ld {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px; 
    border-bottom: 1px solid var(--darker-gold-2);
}
.back-to-loadouts-link-ld {
    color: var(--turquase); border-color: var(--turquase); background-color: transparent;
    padding: 8px 16px; font-size: 0.9rem; font-weight: 500; border-radius: 20px; border: 1px solid;
    text-decoration: none; display: inline-flex; align-items: center; gap: 7px;
    transition: all 0.2s ease;
}
.back-to-loadouts-link-ld:hover { color: var(--white); background-color: var(--turquase); }
.back-to-loadouts-link-ld i.fas { font-size: 0.85em; }

.btn-edit-loadout-ld {
    background-color: var(--gold); color: var(--darker-gold-2); padding: 8px 16px;
    border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 0.9rem;
    transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px;
    border: 1px solid var(--gold);
}
.btn-edit-loadout-ld:hover { background-color: var(--light-gold); color: var(--black); transform: translateY(-1px); border-color:var(--light-gold);}
.btn-edit-loadout-ld i.fas { font-size:0.85em;}

.loadout-header-section-ld {
    margin-bottom: 35px;
    text-align: center;
}
.loadout-set-name-ld {
    color: var(--gold);
    font-size: 2.6rem; 
    font-weight: 700;
    margin: 0 0 10px 0;
    letter-spacing: -0.3px;
}
.loadout-meta-info-ld {
    font-size: 0.9rem;
    color: var(--light-grey);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px; /* Oluşturan ve tarihler arası boşluk */
}
.loadout-meta-info-ld .creator-info-ld { /* Bu user-info-trigger class'ını alacak */
    /* Stiller render_user_info_with_popover ve CSS class'ları ile yönetilecek */
}
.creator-avatar-ld { /* render_user_info_with_popover içinde $avatar_class olarak verilecek */
    width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--gold);
}
.avatar-placeholder-ld { /* render_user_info_with_popover içinde $avatar_class olarak verilecek */
    width: 28px; height: 28px; font-size:0.9rem; background-color:var(--grey); color:var(--gold); display:flex; align-items:center; justify-content:center; font-weight:bold; border:1px solid var(--darker-gold-1); border-radius:50%;
}
.creator-name-link-ld { /* render_user_info_with_popover içinde $link_class olarak verilecek */
    /* Renk, fonksiyon tarafından inline style ile veya popover.js tarafından popover içinde CSS class ile ayarlanacak */
    font-weight: 500; text-decoration:none;
}
.creator-name-link-ld:hover { text-decoration:underline; }
/* Rol renkleri için CSS sınıfları (style.css'de tanımlı olmalı ve popover.js tarafından kullanılmalı) */
.user-info-trigger.username-role-admin a.creator-name-link-ld { color: var(--gold) !important; }
.user-info-trigger.username-role-scg_uye a.creator-name-link-ld { color: #A52A2A !important; }
.user-info-trigger.username-role-ilgarion_turanis a.creator-name-link-ld { color: var(--turquase) !important; }
.user-info-trigger.username-role-member a.creator-name-link-ld { color: var(--white) !important; }
.user-info-trigger.username-role-dis_uye a.creator-name-link-ld { color: var(--light-grey) !important; }

.loadout-meta-info-ld .meta-divider { margin: 0 8px; opacity:0.7; }

.loadout-main-content-grid-ld {
    display: grid;
    grid-template-columns: 4fr 10fr; 
    gap: 35px; 
    margin-bottom: 35px;
    align-items: flex-start; /* Sütunları yukarıda hizala */
}

.loadout-visual-column-ld { 
    position: sticky; /* Sayfa kaydırıldığında sabit kalması için */
    top: calc(var(--navbar-height, 70px) + 20px); /* Navbar yüksekliği + boşluk */
    height: auto; /* İçeriğe göre yükseklik */
    max-height: calc(100vh - var(--navbar-height, 70px) - 40px); /* Maksimum yükseklik */
    overflow: hidden; /* Taşmaları gizle */
}
.loadout-set-image-display-ld {
    width: 100%;
    height: auto; /* Orantılı yükseklik */
    max-height: 700px; /* Maksimum resim yüksekliği */
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    display: flex; 
    align-items: center;
    justify-content: center;
    background-color: var(--darker-gold-2); /* Resim yoksa arka plan */
}
.loadout-set-image-display-ld img {
    width: 100%;
    height: auto; 
    max-height: 700px;
    display: block;
    object-fit: contain; /* Resmi sığdır, kesme */
}
.no-set-image-text-ld {
    text-align: center; padding: 30px 20px; font-style: italic;
    color: var(--light-grey); border: 1px dashed var(--darker-gold-2);
    border-radius: 8px; min-height:200px; display:flex; align-items:center; justify-content:center;
    width:100%; /* Görsel yoksa da alanı kaplasın */
}

.loadout-items-column-ld { 
    /* Sağ sütun: Item listesi */
}
.loadout-items-section-ld h2.section-title-ld {
    color: var(--gold); font-size: 1.8rem; margin: 0 0 20px 0; 
    padding-bottom: 12px; border-bottom: 1px solid var(--darker-gold-1); 
    font-weight: 600; display:flex; align-items:center; gap:10px;
}
.loadout-items-section-ld h2.section-title-ld i.fas { font-size:0.9em;}

.items-list-container-ld { 
    column-count: 2;
    column-gap: 25px;
    max-height: 730px; 
    overflow-y: auto; /* Dikey scroll eklendi */
    padding-right: 10px; 
    padding-bottom: 1px; 
}
.items-list-container-ld::-webkit-scrollbar { width: 8px; }
.items-list-container-ld::-webkit-scrollbar-track { background: var(--darker-gold-2); border-radius:4px;}
.items-list-container-ld::-webkit-scrollbar-thumb { background: var(--grey); border-radius:4px;}
.items-list-container-ld::-webkit-scrollbar-thumb:hover { background: var(--darker-gold-1); }

.slot-category-ld { margin-bottom: 25px; } 
.slot-category-title-ld { 
    font-size: 1.3rem; 
    color: var(--light-gold); 
    margin: 0 0 15px 0; 
    padding-bottom: 8px; 
    border-bottom: 1px solid var(--darker-gold-2); 
    font-weight: 500;
    break-before: column; 
}
.slot-group-ld { 
    margin-bottom: 15px; 
    break-inside: avoid-column;
    background-color: var(--darker-gold-2); 
    padding: 10px 12px; 
    border-radius: 6px; 
    border: 1px solid var(--darker-gold-1);
}
.slot-title-ld { 
    font-size: 1.05rem; 
    color: var(--gold);
    font-weight: 600;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px dashed var(--grey); 
    padding-bottom: 5px;
}
.slot-title-ld i.fas { 
    font-size: 0.9em;
    opacity: 0.8;
}

.item-entry-ld { 
    padding: 3px 0; 
    font-size: 0.9rem; 
    color: var(--lighter-grey);
}
.item-entry-ld .item-name-only { 
    font-weight: 500;
    font-size: .8rem;
}
.item-manufacturer-only { 
    font-size: 0.75em;
    color: var(--light-grey);
    font-style: italic;
}

.no-items-in-slot-ld { font-style: italic; color: var(--light-grey); font-size: 0.85rem; padding: 3px 0; }
.no-items-assigned-ld {
    text-align: center; padding: 20px; font-style: italic;
    border: 1px dashed var(--darker-gold-2); border-radius: 8px;
}

.loadout-description-fullwidth-ld { 
    margin-top: 40px; 
    padding: 25px;
    border: 1px solid var(--darker-gold-2);
    border-radius: 10px;
}
.loadout-description-fullwidth-ld h3 {
    color: var(--light-gold); font-size: 1.6rem; margin: 0 0 15px 0;
    padding-bottom: 10px; border-bottom: 1px solid var(--darker-gold-1);
    font-weight: 600; display:flex; align-items:center; gap:10px;
}
.loadout-description-fullwidth-ld h3 i.fas { color:var(--gold); }
.loadout-description-text-ld p { line-height: 1.7; font-size: 0.95rem; margin-bottom: 1em; }
.loadout-description-text-ld p:last-child { margin-bottom: 0; }

@media (max-width: 992px) { 
    .loadout-main-content-grid-ld {
        grid-template-columns: 1fr; 
    }
    .loadout-visual-column-ld {
        order: 1; 
        position: static; 
        height: auto; 
        max-height: 450px; 
    }
    .loadout-set-image-display-ld {
        height: auto; 
        max-height: 450px;
    }
    .loadout-items-column-ld {
        order: 2; 
    }
    .items-list-container-ld {
        max-height: none; 
        overflow-y: visible;
        column-count: 1; 
    }
}

@media (max-width: 768px) {
    .loadout-detail-page-v4 { padding: 0 15px; margin: 20px auto; }
    .page-top-nav-controls-ld { flex-direction:column; align-items:stretch; gap:15px;}
    .back-to-loadouts-link-ld, .btn-edit-loadout-ld { width:100%; justify-content:center; }
    .loadout-set-name-ld { font-size: 2.2rem; }
    .loadout-description-fullwidth-ld, .loadout-items-section-ld h2.section-title-ld { padding-left:0; padding-right:0;}
}

</style>

<main>
    <div class="loadout-detail-page-v4">
        <?php if ($loadout_set_details): ?>
            <div class="page-top-nav-controls-ld">
                <a href="loadouts.php" class="back-to-loadouts-link-ld"><i class="fas fa-arrow-left"></i> Tüm Setlere Dön</a>
                <?php if ($is_admin_user || $is_set_owner): ?>
                    <a href="<?php echo get_auth_base_url(); ?>/admin/edit_loadout_items.php?set_id=<?php echo $set_id; ?>" class="btn-edit-loadout-ld"><i class="fas fa-edit"></i> Seti Düzenle (Admin)</a>
                <?php endif; ?>
            </div>

            <div class="loadout-header-section-ld">
                <h1 class="loadout-set-name-ld"><?php echo $page_title; ?></h1>
                <div class="loadout-meta-info-ld">
                    <?php
                        // Popover için veri dizisi
                        $creator_data_for_popover_detail = [
                            'id' => $loadout_set_details['creator_id'],
                            'username' => $loadout_set_details['creator_username'],
                            'avatar_path' => $loadout_set_details['creator_avatar_path'],
                            'ingame_name' => $loadout_set_details['creator_ingame_name'],
                            'discord_username' => $loadout_set_details['creator_discord_username'],
                            'user_event_count' => $loadout_set_details['creator_event_count'],
                            'user_gallery_count' => $loadout_set_details['creator_gallery_count'],
                            'user_roles_list' => $loadout_set_details['creator_roles_list']
                        ];
                        echo "Oluşturan: "; // "Oluşturan:" metnini popover dışına alıyoruz
                        echo render_user_info_with_popover(
                            $pdo,
                            $creator_data_for_popover_detail,
                            'creator-name-link-ld',      // Link için CSS class'ı
                            'creator-avatar-ld',         // Avatar için CSS class'ı
                            'creator-info-ld user-info-trigger' // Wrapper için ek class ve popover tetikleyici
                        );
                    ?>
                    <span class="meta-divider">|</span>
                    <span>Oluşturulma: <?php echo date('d M Y', strtotime($loadout_set_details['created_at'])); ?></span>
                    <?php if ($loadout_set_details['updated_at'] && strtotime($loadout_set_details['updated_at']) > strtotime($loadout_set_details['created_at']) + 60): ?>
                        <span class="meta-divider">|</span>
                        <span>Son Güncelleme: <?php echo date('d M Y, H:i', strtotime($loadout_set_details['updated_at'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="loadout-main-content-grid-ld">
                <div class="loadout-visual-column-ld">
                    <?php if (!empty($loadout_set_details['set_image_path'])): ?>
                        <div class="loadout-set-image-display-ld">
                            <img src="<?php echo htmlspecialchars($loadout_set_details['set_image_path']); ?>" alt="<?php echo htmlspecialchars($loadout_set_details['set_name']); ?> Görseli">
                        </div>
                    <?php else: ?>
                        <p class="no-set-image-text-ld">Bu teçhizat seti için bir görsel yüklenmemiş.</p>
                    <?php endif; ?>
                </div>

                <div class="loadout-items-column-ld">
                    <div class="loadout-items-section-ld">
                        <h2 class="section-title-ld"><i class="fas fa-list-ul"></i>Teçhizat Listesi</h2>
                        <?php if (empty($standard_slot_items) && empty($custom_slot_items)): ?>
                            <p class="no-items-assigned-ld">Bu teçhizat setine henüz item atanmamış.</p>
                        <?php else: ?>
                            <div class="items-list-container-ld">
                                <?php if (!empty($standard_slot_items)): ?>
                                    <div class="slot-category-ld">
                                        <?php foreach ($standard_slot_items as $slot_data): ?>
                                            <div class="slot-group-ld">
                                                <h4 class="slot-title-ld">
                                                    <i class="<?php echo $slot_icons[$slot_data['name']] ?? $slot_icons['default']; ?>"></i>
                                                    <?php echo htmlspecialchars($slot_data['name']); ?>
                                                </h4>
                                                <?php if (!empty($slot_data['items'])): ?>
                                                    <?php foreach($slot_data['items'] as $item): ?>
                                                        <div class="item-entry-ld">
                                                            <span class="item-name-only"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                            <?php if(!empty($item['item_manufacturer_api'])): ?>
                                                                <small class="item-manufacturer-only">(<?php echo htmlspecialchars($item['item_manufacturer_api']); ?>)</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="no-items-in-slot-ld">- Boş -</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($custom_slot_items)): ?>
                                    <div class="slot-category-ld">
                                        <h3 class="slot-category-title-ld">Özel Ekipman Slotları</h3>
                                        <?php foreach ($custom_slot_items as $custom_slot_name => $items_in_slot): ?>
                                            <div class="slot-group-ld">
                                                <h4 class="slot-title-ld">
                                                     <i class="<?php echo $slot_icons['default']; ?>"></i>
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', $custom_slot_name)); ?>
                                                </h4>
                                                <?php foreach($items_in_slot as $item): ?>
                                                    <div class="item-entry-ld">
                                                        <span class="item-name-only"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                        <?php if(!empty($item['item_manufacturer_api'])): ?>
                                                            <small class="item-manufacturer-only">(<?php echo htmlspecialchars($item['item_manufacturer_api']); ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div> 
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($loadout_set_details['set_description'])): ?>
                <div class="loadout-description-fullwidth-ld">
                    <h3><i class="fas fa-file-alt"></i>Set Açıklaması</h3>
                    <div class="loadout-description-text-ld">
                        <p><?php echo nl2br(htmlspecialchars($loadout_set_details['set_description'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="message error-message" style="text-align:center;">Teçhizat seti bilgileri yüklenemedi veya bulunamadı.</p>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rootStyles = getComputedStyle(document.documentElement);
    const setRgbVar = (varNameFull) => {
        const coreColorMatch = varNameFull.match(/^--([a-zA-Z0-9\-]+)$/);
        if (coreColorMatch) {
            const varName = coreColorMatch[1]; 
            const hexColor = rootStyles.getPropertyValue(varNameFull).trim();
            if (hexColor && hexColor.startsWith('#') && hexColor.length >= 4) { 
                let r, g, b;
                if (hexColor.length === 4) { 
                    r = parseInt(hexColor[1] + hexColor[1], 16);
                    g = parseInt(hexColor[2] + hexColor[2], 16);
                    b = parseInt(hexColor[3] + hexColor[3], 16);
                } else if (hexColor.length === 7) { 
                    r = parseInt(hexColor.slice(1, 3), 16);
                    g = parseInt(hexColor.slice(3, 5), 16);
                    b = parseInt(hexColor.slice(5, 7), 16);
                } else {
                    return; 
                }
                document.documentElement.style.setProperty(`--${varName}-rgb`, `${r},${g},${b}`);
            }
        }
    };

    const themeColorsForRgbDetail = [
        'gold', 'transparent-gold', 'darker-gold-1', 'darker-gold-2', 
        'turquase', 'light-turquase', 'transparent-turquase-2', 
        'charcoal', 'grey', 'light-grey', 'lighter-grey', 'white', 'black',
        'light-gold'
    ];
    themeColorsForRgbDetail.forEach(colorName => setRgbVar(`--${colorName}`));
    // Popover.js zaten footer'da yüklü olduğu için çalışacaktır.
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
