<?php
// public/loadouts.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'src/config/database.php'; 
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol ve yetki fonksiyonları
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover için

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$page_title = "Teçhizat Setleri"; // Daha genel bir başlık
$all_loadout_sets = [];

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

// Sayfa görüntüleme yetkisi kontrolü
$can_view_loadouts_page = false;
$access_message_loadouts = "";

// Gerekli yetkiler: loadout.view_public, loadout.view_members_only, loadout.view_published (veya faction_only), loadout.view_all
if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'loadout.view_all', $current_user_id))) {
    $can_view_loadouts_page = true;
} elseif ($current_user_is_logged_in) { // Giriş yapmış kullanıcılar için (onaylı veya değil, yetkiye bağlı)
    if (has_permission($pdo, 'loadout.view_published', $current_user_id) || // Yayınlanmışları görme (genel bir yetki)
        has_permission($pdo, 'loadout.view_members_only', $current_user_id) ||
        has_permission($pdo, 'loadout.view_public', $current_user_id)
        // Kullanıcının kendi özel setlerini görme durumu sorguda ele alınacak
       ) {
        $can_view_loadouts_page = true;
    } else {
        // Eğer kullanıcı giriş yapmış ama hiçbir görme yetkisi yoksa (kendi özel setleri hariç)
        // Kullanıcının kendi özel setleri varsa yine de sayfayı görebilmeli. Bu kontrol sorgu sonrası yapılabilir.
        // Şimdilik, en az bir genel görme yetkisi yoksa mesaj verelim.
        $access_message_loadouts = "Teçhizat setlerini görüntüleme yetkiniz bulunmamaktadır.";
    }
} elseif (has_permission($pdo, 'loadout.view_public', null)) { // Misafir kullanıcılar için public setleri görme
    $can_view_loadouts_page = true;
} else {
     $access_message_loadouts = "Teçhizat setlerini görmek için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
}

// Eğer can_view_loadouts_page hala false ise ve access_message_loadouts doluysa yönlendir.
// Kendi özel setlerini görme durumu için bu kontrol sorgudan sonra yapılabilir.
if (!$can_view_loadouts_page && !empty($access_message_loadouts)) {
    $_SESSION['error_message'] = $access_message_loadouts;
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
}
// Eğer $can_view_loadouts_page false ama $access_message_loadouts boşsa (yani giriş yapmış ama genel yetkisi yok),
// kullanıcının kendi özel setleri olup olmadığına bakılacak. Bu yüzden hemen exit yapmıyoruz.


// $GLOBALS['role_priority'] global olarak tanımlı olmalı (örn: database.php içinde)
// Eğer tanımlı değilse, burada varsayılan bir dizi sağlayabilirsiniz:
if (!isset($GLOBALS['role_priority'])) {
    $GLOBALS['role_priority'] = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];
}


try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    // SQL sorgusuna creator için popover'da kullanılacak tüm alanlar eklendi
    $sql_select_fields = "SELECT 
                ls.id, 
                ls.set_name, 
                ls.set_description, 
                ls.set_image_path,
                ls.status,                         -- Yeni sütun
                ls.visibility,                     -- Yeni sütun
                ls.user_id AS creator_user_id, 
                u.username AS creator_username,
                u.avatar_path AS creator_avatar_path,
                u.ingame_name AS creator_ingame_name, 
                u.discord_username AS creator_discord_username, 
                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS creator_event_count, 
                (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS creator_gallery_count, 
                (SELECT GROUP_CONCAT(r.name ORDER BY FIELD(r.name, '" . implode("','", $GLOBALS['role_priority']) . "') SEPARATOR ',') 
                    FROM user_roles ur_creator 
                    JOIN roles r ON ur_creator.role_id = r.id 
                    WHERE ur_creator.user_id = u.id) AS creator_roles_list, 
                ls.created_at,
                ls.updated_at,
                (SELECT COUNT(*) FROM loadout_set_items WHERE loadout_set_id = ls.id) AS item_count";

    $sql_from_join = " FROM loadout_sets ls JOIN users u ON ls.user_id = u.id";
    $where_clauses = [];
    $params = [];

    // Temel filtre: Sadece 'published' durumundakiler (admin değilse)
    if (!$current_user_is_admin && !has_permission($pdo, 'loadout.view_all', $current_user_id)) {
        $where_clauses[] = "ls.status = 'published'";
    }
    // Admin veya view_all yetkisi olanlar tüm status'leri görebilir (draft, archived dahil)

    // Görünürlük koşulları
    if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'loadout.view_all', $current_user_id))) {
        // Admin veya 'loadout.view_all' yetkisine sahip olanlar için ek bir görünürlük WHERE koşulu gerekmez
    } else {
        $visibility_or_conditions = [];
        // 1. Herkese açık setler
        if (has_permission($pdo, 'loadout.view_public', $current_user_id) || !$current_user_is_logged_in) {
            $visibility_or_conditions[] = "ls.visibility = 'public'";
        }

        // 2. Üyelere özel setler
        if ($current_user_is_approved && has_permission($pdo, 'loadout.view_members_only', $current_user_id)) {
            $visibility_or_conditions[] = "ls.visibility = 'members_only'";
        }
        
        // 3. Rol bazlı setler ('faction_only')
        if ($current_user_is_approved) { // Rol bazlı görme için kullanıcının onaylı olması
            $user_actual_role_ids_loadout = [];
            if ($current_user_id) {
                $stmt_user_role_ids_loadout = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :current_user_id_for_roles_loadout");
                $stmt_user_role_ids_loadout->execute([':current_user_id_for_roles_loadout' => $current_user_id]);
                $user_actual_role_ids_loadout = $stmt_user_role_ids_loadout->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($user_actual_role_ids_loadout)) {
                $role_placeholders_loadout_sql = [];
                foreach ($user_actual_role_ids_loadout as $idx_role_loadout => $role_id_val_loadout) {
                    $placeholder_role_loadout = ':user_role_id_for_loadout_visibility_' . $idx_role_loadout;
                    $role_placeholders_loadout_sql[] = $placeholder_role_loadout;
                    $params[$placeholder_role_loadout] = $role_id_val_loadout;
                }
                $in_clause_roles_loadout_sql = implode(',', $role_placeholders_loadout_sql);
                if (!empty($in_clause_roles_loadout_sql)) {
                     $visibility_or_conditions[] = "(ls.visibility = 'faction_only' AND EXISTS (
                                                    SELECT 1 FROM loadout_set_visibility_roles lsvr_check
                                                    WHERE lsvr_check.set_id = ls.id AND lsvr_check.role_id IN (" . $in_clause_roles_loadout_sql . ")
                                                 ))";
                }
            }
        }
        
        // 4. Kullanıcının kendi özel setleri
        if ($current_user_id) {
            $visibility_or_conditions[] = "(ls.visibility = 'private' AND ls.user_id = :current_user_id_for_private_loadout)";
            $params[':current_user_id_for_private_loadout'] = $current_user_id;
        }

        if (!empty($visibility_or_conditions)) {
            $where_clauses[] = "(" . implode(" OR ", $visibility_or_conditions) . ")";
        } else {
            // Eğer hiçbir OR koşulu oluşmadıysa ve kullanıcı giriş yapmamışsa (ve public görme yetkisi de yoksa)
            if (!$current_user_is_logged_in) {
                 $where_clauses[] = "1=0"; // Hiçbir sonuç döndürmez
            }
            // Giriş yapmış ama hiçbir genel yetkisi yoksa, sadece kendi özel setlerini görebilir (yukarıda eklendi)
            // Bu durumda $visibility_or_conditions boş kalabilir, sorun değil.
        }
    }
    
    $sql_where_string = "";
    if (!empty($where_clauses)) {
        $sql_where_string = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_order_by = " ORDER BY ls.status ASC, ls.set_name ASC"; // Önce duruma göre, sonra ada göre sırala
    
    $final_sql = $sql_select_fields . $sql_from_join . $sql_where_string . $sql_order_by;
            
    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params);
    $all_loadout_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eğer kullanıcı giriş yapmış, genel görme yetkisi yok ama kendi özel setleri de yoksa ve liste boşsa, o zaman erişim mesajı göster.
    if (!$can_view_loadouts_page && empty($all_loadout_sets) && $current_user_is_logged_in && empty($access_message_loadouts)) {
        $access_message_loadouts = "Görüntülenecek herhangi bir teçhizat seti bulunmamaktadır veya mevcut setleri görme yetkiniz yoktur.";
         $_SESSION['error_message'] = $access_message_loadouts;
         // Yönlendirme yapmayalım, boş sayfa mesajı gösterilsin.
    } elseif (!$can_view_loadouts_page && empty($all_loadout_sets) && !$current_user_is_logged_in && empty($access_message_loadouts)) {
        // Misafir ve public set yoksa
         $access_message_loadouts = "Henüz herkese açık bir teçhizat seti bulunmamaktadır.";
         $_SESSION['error_message'] = $access_message_loadouts;
    }


} catch (Exception $e) {
    error_log("Teçhizat setleri listeleme hatası (loadouts.php): " . $e->getMessage());
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['error_message'] = "Teçhizat setleri yüklenirken bir sorun oluştu: " . htmlspecialchars($e->getMessage());
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* public/loadouts.php için Tablo Tasarımı Stilleri */
:root {
    /* style.css'den gelen renkler kullanılacak, burada sadece RGB versiyonları için placeholder */
    /* Bu RGB değişkenleri JS ile style.css'deki ana renklerden türetilecek */
}

.loadouts-page-container-v3-table { 
    width: 100%;
    max-width: 1600px; 
    margin: 30px auto;
    padding: 25px 35px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 160px);
}

.page-top-nav-controls-loadouts {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.page-top-nav-controls-loadouts .page-title-loadouts {
    color: var(--gold);
    font-size: 2.2rem;
    font-family: var(--font);
    margin: 0;
    flex-grow: 1;
}
.btn-manage-loadout-sets-admin { 
    background-color: var(--gold); 
    color: var(--darker-gold-2); 
    padding: 10px 22px;
    border-radius: 25px; 
    text-decoration: none; 
    font-weight: 600; 
    font-size: 0.95rem;
    transition: all 0.3s ease; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px;
    border: 1px solid var(--darker-gold-1);
}
.btn-manage-loadout-sets-admin:hover {
    background-color: var(--light-gold); 
    color: var(--black);
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px var(--transparent-gold); 
}
.btn-manage-loadout-sets-admin i.fas { font-size: 0.9em; }

.loadouts-page-description {
    font-size: 0.95rem;
    color: var(--light-grey);
    line-height: 1.6;
    margin-bottom: 30px;
    padding: 15px 20px;
    background-color: rgba(var(--darker-gold-2-rgb, 26, 17, 1), 0.5); 
    border-left: 4px solid var(--gold);
    border-radius: 0 8px 8px 0;
}
.loadouts-page-description strong { color: var(--light-gold); }

.empty-loadouts-message { 
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px;
    background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1);
    margin-top: 20px;
}
.empty-loadouts-message a {
    color: var(--turquase);
    font-weight: bold;
    text-decoration: none;
    margin-top: 10px;
    display: inline-block;
}
.empty-loadouts-message a:hover { text-decoration: underline; }

.loadouts-table-wrapper {
    overflow-x: auto; 
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.loadouts-table {
    width: 100%;
    min-width: 950px; 
    border-collapse: collapse; 
}

.loadouts-table th,
.loadouts-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2); 
    font-size: 0.9rem;
    color: var(--lighter-grey);
    vertical-align: middle; 
}

.loadouts-table thead th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    white-space: nowrap; 
}
.loadouts-table thead th:first-child { 
    width: 100px;
    text-align: center;
}
.loadouts-table thead th.description-column { min-width: 200px; }
.loadouts-table thead th.actions-column { min-width: 280px; text-align: right; }

.loadouts-table tbody tr:hover { background-color: var(--darker-gold-2); }
.loadouts-table tbody tr:last-child td { border-bottom: none; }

.set-thumbnail-table { 
    width: 80px;  
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
    background-color: var(--grey); 
    border: 1px solid var(--darker-gold-2);
    display: block; 
    margin: auto; 
}
.set-thumbnail-placeholder-table { 
    width: 80px; height: 50px;
    background-color: var(--grey);
    color: var(--light-grey);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75em;
    border-radius: 4px;
    border: 1px solid var(--darker-gold-2);
    margin: auto;
}

.loadouts-table td.set-name-cell a { 
    font-weight: 600;
    font-size: 1.05em; 
    color: var(--light-gold);
    text-decoration: none;
}
.loadouts-table td.set-name-cell a:hover {
    color: var(--gold);
    text-decoration: underline;
}

.loadouts-table td.creator-cell .user-info-trigger { 
    /* Stiller render_user_info_with_popover tarafından eklenen class'lar ve style.css üzerinden yönetilecek */
}
.creator-avatar-table { /* render_user_info_with_popover içinde $avatar_class olarak verilecek */
    width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--darker-gold-1);
}
.avatar-placeholder-table { /* render_user_info_with_popover içinde $avatar_class olarak verilecek */
    width: 28px; height: 28px; border-radius: 50%; background-color: var(--grey); color: var(--gold);
    display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold;
    border: 1px solid var(--darker-gold-1); line-height: 1;
}
.creator-name-link-table { /* render_user_info_with_popover içinde $link_class olarak verilecek */
    /* Renk, fonksiyon tarafından inline style ile veya popover.js tarafından popover içinde CSS class ile ayarlanacak */
    font-weight: 500; font-size: 0.9em; text-decoration: none;
}
.creator-name-link-table:hover { text-decoration: underline; }

/* Rol renkleri için CSS sınıfları (style.css'de tanımlı olmalı ve popover.js tarafından kullanılmalı) */
.user-info-trigger.username-role-admin a.creator-name-link-table { color: var(--gold) !important; }
.user-info-trigger.username-role-scg_uye a.creator-name-link-table { color: #A52A2A !important; }
.user-info-trigger.username-role-ilgarion_turanis a.creator-name-link-table { color: var(--turquase) !important; }
.user-info-trigger.username-role-member a.creator-name-link-table { color: var(--white) !important; }
.user-info-trigger.username-role-dis_uye a.creator-name-link-table { color: var(--light-grey) !important; }


.loadouts-table td.actions-cell {
    text-align: right;
    white-space: nowrap; 
}
.loadouts-table td.actions-cell .btn { 
    margin-left: 8px;
    font-size: 0.8rem; 
    padding: 7px 15px; 
    border-radius: 20px; 
    font-weight:500;
    transition: all 0.2s ease;
}
.loadouts-table td.actions-cell .btn:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}
.loadouts-table td.actions-cell .btn:first-child { margin-left: 0; }
.loadouts-table td.actions-cell .btn-primary { 
    background-color:var(--turquase); color:var(--black); border-color:var(--turquase);
}
.loadouts-table td.actions-cell .btn-primary:hover {
    background-color:var(--light-turquase); color:var(--darker-gold-2); border-color:var(--light-turquase);
}
.loadouts-table td.actions-cell .btn-warning { 
    background-color:var(--light-gold); color:var(--darker-gold-2); border-color:var(--light-gold);
}
.loadouts-table td.actions-cell .btn-warning:hover {
    background-color:var(--gold); color:var(--black); border-color:var(--gold);
}
.loadouts-table td.actions-cell .btn-info { 
    background-color:var(--gold); color:var(--darker-gold-2); border-color:var(--gold);
}
.loadouts-table td.actions-cell .btn-info:hover {
    background-color:var(--light-gold); color:var(--black); border-color:var(--light-gold);
}


@media (max-width: 768px) {
    .loadouts-page-container-v3-table { padding: 20px; }
    .page-top-nav-controls-loadouts { flex-direction: column; align-items: stretch; gap:15px; }
    .btn-manage-loadout-sets-admin { width:100%; text-align:center; justify-content:center;}
    .page-top-nav-controls-loadouts .page-title-loadouts { font-size: 2rem; text-align:center; }
    .loadouts-page-description { font-size:0.9rem; padding:12px 15px;}
    .loadouts-table th, .loadouts-table td { padding: 10px 8px; font-size:0.85rem;}
    .loadouts-table td.actions-cell .btn { padding: 6px 10px; font-size:0.75rem;}
}

</style>

<main class="main-content">
    <div class="container loadouts-page-container-v3-table">
        <div class="page-top-nav-controls-loadouts">
            <h1 class="page-title-loadouts"><?php echo htmlspecialchars($page_title); ?> (<?php echo count($all_loadout_sets); ?> Set)</h1>
            <?php if (is_user_admin()): ?>
                <a href="<?php echo get_auth_base_url(); ?>/admin/manage_loadout_sets.php" class="btn-manage-loadout-sets-admin"><i class="fas fa-cog"></i> Setleri Yönet (Admin)</a>
            <?php endif; ?>
        </div>

        <p class="loadouts-page-description">
            Takım liderleri tarafından özenle hazırlanan bu teçhizat setleri, çeşitli operasyon türleri ve görev gereksinimleri göz önünde bulundurularak tasarlanmıştır. 
            Amaç, takımın genel etkinliğini ve üyelerin sahada karşılaşabileceği durumlara hazırlığını en üst düzeye çıkarmaktır. 
            Bu setler, üyelerimize standart bir hazırlık seviyesi sunarken, aynı zamanda bireysel modifikasyonlara da olanak tanıyacak şekilde bir temel oluşturmayı hedefler.
        </p>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="empty-loadouts-message" style="background-color: var(--transparent-red); color:var(--red); border-style:solid; border-color:var(--dark-red);"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
             <p class="empty-loadouts-message" style="background-color: rgba(var(--turquase-rgb, 61, 166, 162), 0.15); color:var(--turquase); border-style:solid; border-color:var(--turquase);"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
        <?php endif; ?>

        <?php if (!$current_user_is_logged_in && $can_view_loadouts_page && !empty($all_loadout_sets)): ?>
            <div class="info-message" style="text-align: center; margin-bottom: 20px; background-color: var(--transparent-turquase-2); color: var(--turquase); border: 1px solid var(--turquase); padding: 10px; border-radius: 5px;">
                <i class="fas fa-info-circle"></i> Şu anda sadece herkese açık teçhizat setlerini görüntülüyorsunuz. Daha fazla sete erişmek için <a href="<?php echo get_auth_base_url(); ?>/login.php" style="color: var(--light-turquase); font-weight:bold;">giriş yapın</a> ya da <a href="<?php echo get_auth_base_url(); ?>/register.php" style="color: var(--light-turquase); font-weight:bold;">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (empty($all_loadout_sets) && !(isset($_SESSION['error_message']) || isset($_SESSION['success_message'])) ): ?>
            <p class="empty-loadouts-message">
                Henüz hiç standart teçhizat seti tanımlanmamış.
                <?php if (is_user_admin()): ?>
                    <br><a href="<?php echo get_auth_base_url(); ?>/admin/new_loadout_set.php">İlk seti şimdi oluşturun!</a>
                <?php endif; ?>
            </p>
        <?php elseif (!empty($all_loadout_sets)): ?>
            <div class="loadouts-table-wrapper">
                <table class="loadouts-table">
                    <thead>
                        <tr>
                            <th>Görsel</th>
                            <th>Set Adı / Rol</th>
                            <th class="description-column">Açıklama</th>
                            <th>Item Sayısı</th>
                            <th>Oluşturan</th>
                            <th>Oluşturulma Tarihi</th>
                            <th class="actions-column">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_loadout_sets as $set): ?>
                            <?php
                                $description_excerpt_table = mb_substr(strip_tags($set['set_description'] ?? ''), 0, 60, 'UTF-8');
                                if (mb_strlen(strip_tags($set['set_description'] ?? ''), 'UTF-8') > 60) {
                                    $description_excerpt_table .= '...';
                                }
                                // Popover için veri dizisi
                                $creator_data_for_popover_loadouts = [
                                    'id' => $set['creator_user_id'],
                                    'username' => $set['creator_username'],
                                    'avatar_path' => $set['creator_avatar_path'],
                                    'ingame_name' => $set['creator_ingame_name'],
                                    'discord_username' => $set['creator_discord_username'],
                                    'user_event_count' => $set['creator_event_count'],
                                    'user_gallery_count' => $set['creator_gallery_count'],
                                    'user_roles_list' => $set['creator_roles_list']
                                ];
                            ?>
                            <tr>
                                <td>
                                    <?php if (!empty($set['set_image_path'])): ?>
                                        <img src="<?php echo '' . htmlspecialchars($set['set_image_path']); ?>" alt="<?php echo htmlspecialchars($set['set_name'] ?? 'Set Görseli'); ?>" class="set-thumbnail-table">
                                    <?php else: ?>
                                        <div class="set-thumbnail-placeholder-table">Görsel Yok</div>
                                    <?php endif; ?>
                                </td>
                                <td class="set-name-cell">
                                    <a href="loadout_detail.php?set_id=<?php echo htmlspecialchars($set['id']); ?>">
                                        <?php echo htmlspecialchars($set['set_name'] ?? 'İsimsiz Set'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($description_excerpt_table); ?></td>
                                <td style="text-align:center;"><?php echo htmlspecialchars($set['item_count'] ?? '0'); ?></td>
                                <td class="creator-cell">
                                    <?php 
                                    // render_user_info_with_popover fonksiyonunu çağır
                                    echo render_user_info_with_popover(
                                        $pdo, // PDO bağlantısı
                                        $creator_data_for_popover_loadouts, // Kullanıcı verileri
                                        'creator-name-link-table', // Link için CSS class'ı
                                        'creator-avatar-table',    // Avatar için CSS class'ı
                                        ''                         // Wrapper span için ek class (boş olabilir)
                                    );
                                    ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($set['created_at'] ?? 'now')); ?></td>
                                <td class="actions-cell">
                                    <a href="loadout_detail.php?set_id=<?php echo htmlspecialchars($set['id']); ?>" class="btn btn-sm btn-primary">Detaylar</a>
                                    <?php if (is_user_admin() || (is_user_logged_in() && $_SESSION['user_id'] == ($set['creator_user_id'] ?? null)) ): ?>
                                        <a href="<?php echo get_auth_base_url(); ?>/admin/edit_loadout_set_details.php?set_id=<?php echo htmlspecialchars($set['id']); ?>" class="btn btn-sm btn-info">Seti Düzenle</a>
                                        <a href="<?php echo get_auth_base_url(); ?>/admin/edit_loadout_items.php?set_id=<?php echo htmlspecialchars($set['id']); ?>" class="btn btn-sm btn-warning">Item Düzenle</a>
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

    const themeColorsForRgb = [
        'gold', 'transparent-gold', 'darker-gold-1', 'darker-gold-2', 
        'turquase', 'light-turquase', 'transparent-turquase-2', 
        'charcoal', 'grey', 'light-grey', 'lighter-grey', 'white', 'black',
        'light-gold'
    ];
    themeColorsForRgb.forEach(colorName => setRgbVar(`--${colorName}`));
    // Popover.js zaten footer'da yüklü olduğu için çalışacaktır.
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
