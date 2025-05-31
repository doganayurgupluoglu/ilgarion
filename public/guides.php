<?php
// public/guides.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/guide_functions.php'; // get_excerpt_from_markdown
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et (her sayfanın başında olmalı)
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

// Helper fonksiyonlar (Eğer ayrı bir dosyada değillerse, burada tanımlanmalı)
// Bu fonksiyonlar çağrılmadan ÖNCE tanımlanmalı.
if (!function_exists('get_role_display_names_map')) {
    function get_role_display_names_map() {
        // Bu değerleri veritabanından veya bir config dosyasından çekmek daha dinamik olabilir.
        return ['admin' => 'Yönetici', 'member' => 'Üye', 'scg_uye' => 'SCG Üyesi', 'ilgarion_turanis' => 'Ilgarion Turanis', 'dis_uye' => 'Dış Üye'];
    }
}
if (!function_exists('get_role_icons_map')) {
    function get_role_icons_map() {
        return [
            'admin' => 'fas fa-user-shield',
            'member' => 'fas fa-user',
            'scg_uye' => 'fas fa-users-cog',
            'ilgarion_turanis' => 'fas fa-dragon',
            'dis_uye' => 'fas fa-user-tag',
            'public' => 'fas fa-globe',
            'members-only' => 'fas fa-users',
            'restricted' => 'fas fa-lock', // Kısıtlı erişim için yeni ikon
            'default' => 'fas fa-question-circle'
        ];
    }
}
if (!function_exists('get_role_color_classes_map')) {
    function get_role_color_classes_map() {
        // Bu class'lar CSS'te tanımlı olmalı (örn: .role-admin, .role-public)
        return [
            'admin' => 'role-admin',
            'member' => 'role-member',
            'scg_uye' => 'role-scg_uye',
            'ilgarion_turanis' => 'role-ilgarion_turanis',
            'dis_uye' => 'role-dis_uye',
            'public' => 'role-public',
            'members-only' => 'role-members-only',
            'restricted' => 'role-restricted', // Kısıtlı erişim için yeni bir class
            'default' => 'role-default'
        ];
    }
}


$page_title = "Rehberler";
$all_guides_with_visibility = [];

$filter_author_id = isset($_GET['author']) && is_numeric($_GET['author']) ? (int)$_GET['author'] : null;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Kullanıcı durumunu GÜNCEL SESSION'DAN al
$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;
$current_user_roles_names = $_SESSION['user_roles'] ?? []; // Rol isimleri dizisi

// Yetki kontrolleri
$can_create_guide = $current_user_is_approved && has_permission($pdo, 'guide.create', $current_user_id);
$can_like_guide = $current_user_is_approved && has_permission($pdo, 'guide.like', $current_user_id);


try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    $sql_base = "SELECT
                    g.id, g.title, g.slug, g.thumbnail_path, g.content_md,
                    g.status, g.created_at, g.updated_at, g.view_count, g.like_count,
                    g.is_public_no_auth, g.is_members_only,
                    u.id AS author_id, u.username AS author_username, u.avatar_path AS author_avatar_path,
                    u.ingame_name AS author_ingame_name, u.discord_username AS author_discord_username,
                    (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS author_event_count,
                    (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS author_gallery_count,
                    (SELECT GROUP_CONCAT(r_author.name SEPARATOR ',')
                     FROM user_roles ur_author
                     JOIN roles r_author ON ur_author.role_id = r_author.id
                     WHERE ur_author.user_id = u.id) AS author_roles_list,
                    GROUP_CONCAT(DISTINCT vr.role_id) AS visible_to_role_ids_concat";

    if ($current_user_id) {
        $sql_base .= ", (SELECT COUNT(*) FROM guide_likes gl WHERE gl.guide_id = g.id AND gl.user_id = :current_user_id_for_like_status) AS user_has_liked_this_guide";
    }

    $sql_from_join = " FROM guides g
                       JOIN users u ON g.user_id = u.id
                       LEFT JOIN guide_visibility_roles vr ON g.id = vr.guide_id";

    $where_clauses = [];
    $params = [];

    if ($current_user_id) {
        $params[':current_user_id_for_like_status'] = $current_user_id;
    }

    if ($current_user_is_admin) {
        // Admin her şeyi görür, özel bir WHERE koşuluna gerek yok (status hariç)
    } elseif ($current_user_is_approved) {
        $visibility_conditions = "(g.status = 'published' AND (
                                    g.is_public_no_auth = 1 OR
                                    (g.is_members_only = 1) OR
                                    (EXISTS (SELECT 1 FROM guide_visibility_roles gvr_check
                                             JOIN user_roles ur_check ON gvr_check.role_id = ur_check.role_id
                                             WHERE gvr_check.guide_id = g.id AND ur_check.user_id = :current_user_id_for_visibility))
                                 ))";
        $visibility_conditions .= " OR (g.user_id = :current_user_id_for_owner AND g.status = 'draft')";
        $where_clauses[] = $visibility_conditions;
        $params[':current_user_id_for_visibility'] = $current_user_id;
        $params[':current_user_id_for_owner'] = $current_user_id;
    } else {
        $where_clauses[] = "(g.status = 'published' AND g.is_public_no_auth = 1)";
    }

    if (!$current_user_is_admin) {
        $where_clauses[] = "g.status != 'archived'";
    }


    if ($filter_author_id) {
        $where_clauses[] = "g.user_id = :author_id";
        $params[':author_id'] = $filter_author_id;
    }

    if (!empty($search_term)) {
        $where_clauses[] = "(g.title LIKE :search_title OR g.content_md LIKE :search_content OR u.username LIKE :search_author)";
        $params[':search_title'] = '%' . $search_term . '%';
        $params[':search_content'] = '%' . $search_term . '%';
        $params[':search_author'] = '%' . $search_term . '%';
    }

    $sql_where_string = "";
    if (!empty($where_clauses)) {
        $sql_where_string = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_group_by_order_by = " GROUP BY g.id ORDER BY g.status ASC, g.updated_at DESC, g.created_at DESC";
    $final_sql = $sql_base . $sql_from_join . $sql_where_string . $sql_group_by_order_by;

    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params);
    $all_guides_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $role_display_names_map = get_role_display_names_map();
    $role_icons_map = get_role_icons_map();
    $role_color_classes_map = get_role_color_classes_map();


    foreach($all_guides_raw as $guide_raw){
        $temp_guide = $guide_raw;
        $visible_role_icons_classes = [];

        if($temp_guide['status'] === 'published'){
            if($temp_guide['is_public_no_auth'] == 1){
                $visible_role_icons_classes[] = ['icon' => ($role_icons_map['public'] ?? 'fas fa-globe'), 'color_class' => ($role_color_classes_map['public'] ?? 'role-default'), 'name' => 'Herkese Açık'];
            } elseif ($temp_guide['is_members_only'] == 1) {
                $visible_role_icons_classes[] = ['icon' => ($role_icons_map['members-only'] ?? 'fas fa-users'), 'color_class' => ($role_color_classes_map['members-only'] ?? 'role-default'), 'name' => 'Tüm Üyelere'];
            } elseif (!empty($temp_guide['visible_to_role_ids_concat'])) {
                $role_ids_array = array_unique(array_filter(explode(',', $temp_guide['visible_to_role_ids_concat'])));
                if(!empty($role_ids_array)){
                    $placeholders = implode(',', array_fill(0, count($role_ids_array), '?'));
                    $stmt_role_names = $pdo->prepare("SELECT name, description FROM roles WHERE id IN (" . $placeholders . ")");
                    $stmt_role_names->execute($role_ids_array);
                    $roles_data_for_guide = $stmt_role_names->fetchAll(PDO::FETCH_ASSOC);

                    foreach($roles_data_for_guide as $role_detail){
                        $role_name_key = $role_detail['name'];
                        $display_name = htmlspecialchars($role_detail['description'] ?: ($role_display_names_map[$role_name_key] ?? ucfirst(str_replace('_', ' ', $role_name_key))));
                        $visible_role_icons_classes[] = [
                            'icon' => $role_icons_map[$role_name_key] ?? 'fas fa-user-tag',
                            'color_class' => $role_color_classes_map[$role_name_key] ?? 'role-default',
                            'name' => $display_name
                        ];
                    }
                }
            }
        }
        if ($temp_guide['status'] === 'published' && empty($visible_role_icons_classes)) {
             $visible_role_icons_classes[] = ['icon' => ($role_icons_map['restricted'] ?? 'fas fa-lock'), 'color_class' => ($role_color_classes_map['restricted'] ?? 'role-default'), 'name' => 'Kısıtlı Erişim'];
        }


        $temp_guide['visibility_icons_classes'] = $visible_role_icons_classes;
        $all_guides_with_visibility[] = $temp_guide;
    }


} catch (Exception $e) {
    error_log("Rehber listesi çekme hatası (guides.php): " . $e->getMessage());
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['error_message'] = "Rehberler yüklenirken bir sorun oluştu.";
}


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* public/guides.php için CSS (mevcut stillerinizle birleştirin) */
/* ... (mevcut .guides-page-container-v2 ve diğer stilleriniz) ... */
.guides-page-container-v2 {
    width: 100%;
    max-width: 1700px;
    margin: 30px auto;
    padding: 25px 35px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 160px);
}

.page-top-nav-controls-guides {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px; 
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.page-top-nav-controls-guides .page-title-guides {
    color: var(--gold);
    font-size: 2.2rem;
    font-family: var(--font);
    margin: 0;
    flex-grow: 1;
}
.btn-create-new-guide-v2 { 
    background-color: transparent; 
    color: var(--turquase); 
    padding: 10px 22px;
    border-radius: 25px; 
    text-decoration: none; 
    font-weight: 600; 
    font-size: 0.95rem;
    transition: all 0.3s ease; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px;
    border: 1px solid var(--turquase); 
}
.btn-create-new-guide-v2:hover {
    background-color: var(--turquase); 
    color: var(--darker-gold-2);
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px var(--transparent-turquase-2); 
}
.btn-create-new-guide-v2 i.fas { font-size: 0.9em; }

.guides-page-description {
    font-size: 0.95rem;
    color: var(--light-grey);
    line-height: 1.6;
    margin-bottom: 30px;
    padding: 15px 20px;
    background-color: rgba(var(--darker-gold-2-rgb), 0.5); 
    border-left: 4px solid var(--gold);
    border-radius: 0 8px 8px 0;
}
.guides-page-description strong {
    color: var(--light-gold);
}

.guides-filter-bar {
    margin-bottom: 35px;
    padding: 10px 0; 
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.guides-search-form { display: flex; flex-grow: 1; gap: 12px; min-width: 250px; }
.guides-search-form .form-group { flex-grow: 1; margin-bottom: 0; position:relative; }
.guides-search-form label { display: none; }
.guides-search-form input[type="text"] {
    width: 100%;
    padding: 10px 38px 10px 16px; 
    background-color: transparent; 
    border: 1px solid var(--darker-gold-1);
    border-radius: 20px; 
    color: var(--gold);
    font-size: 0.88rem;
    font-family: var(--font);
}
.guides-search-form input[type="text"]::placeholder { color: var(--light-grey); opacity: 0.7; }
.guides-search-form input[type="text"]:focus { 
    outline: none; 
    border-color: var(--gold); 
    box-shadow: 0 0 0 2.5px var(--transparent-gold); 
}
.guides-search-form .btn-search-guides {
    position: absolute;
    right: 3px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none;
    color: var(--gold); padding: 7px; font-size: 1rem; cursor: pointer;
    border-radius: 50%; width:30px; height:30px;
    display:flex; align-items:center; justify-content:center;
}
.guides-search-form .btn-search-guides:hover { background-color: var(--transparent-gold); }

.guides-filter-bar .btn-clear-filters { 
    padding: 9px 16px; font-size: 0.85rem; border-radius: 20px;
    background-color: var(--grey); color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1); font-weight:500;
}
.guides-filter-bar .btn-clear-filters:hover { background-color: var(--darker-gold-1); color: var(--white); }


.guides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); 
    gap: 28px; 
}

.guide-card-v2 {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s ease-out, box-shadow 0.25s ease-out, border-color 0.25s ease-out;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.guide-card-v2:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(var(--gold-rgb), 0.18);
    border-color: var(--darker-gold);
}

.guide-card-thumbnail-link { display: block; position: relative; overflow:hidden; border-radius: 10px 10px 0 0;}
.guide-card-thumbnail {
    width: 100%;
    height: 210px; 
    object-fit: cover;
    display: block;
    transition: transform 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.guide-card-v2:hover .guide-card-thumbnail { transform: scale(1.05); }

.guide-status-overlay {
    position: absolute;
    top: 12px; right: 12px;
    padding: 5px 12px;
    border-radius: 18px; 
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--charcoal); 
    z-index: 2;
    border: 1px solid rgba(0,0,0,0.15);
    backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
}
.guide-status-overlay.status-published { background-color: rgba(var(--turquase-rgb), 0.85); }
.guide-status-overlay.status-draft { background-color: rgba(var(--light-gold-rgb), 0.9); } 
.guide-status-overlay.status-archived { background-color: rgba(var(--grey-rgb), 0.85); color:var(--lighter-grey); }

.guide-card-content-v2 { padding: 16px 20px; flex-grow: 1; display: flex; flex-direction: column; }
.guide-card-title-v2 { margin: 0 0 8px 0; font-size: 1.3rem; line-height: 1.35; }
.guide-card-title-v2 a { color: var(--light-gold); text-decoration: none; font-weight: 600; }
.guide-card-title-v2 a:hover { color: var(--gold); text-decoration: underline; }

.guide-card-excerpt-v2 {
    font-size: 0.88rem; color: var(--lighter-grey);
    line-height: 1.5; margin-bottom: 12px; flex-grow: 1;
    display: -webkit-box; -webkit-line-clamp: 3; 
    -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;
    min-height: calc(1.5em * 3); 
}

.guide-card-footer-v2 {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 10px; border-top: 1px solid var(--darker-gold-2);
    font-size: 0.8rem; color: var(--light-grey);
}
.guide-author-info-v2 { 
    display: inline-flex; align-items: center; gap: 7px; cursor:default; min-width:0;
}
.author-avatar-guide-card { 
    width: 26px; height: 26px; border-radius: 50%; object-fit: cover; border: 1px solid var(--darker-gold-1); flex-shrink:0;
}
.avatar-placeholder-guide-card { 
    width: 26px; height: 26px; border-radius: 50%; background-color: var(--grey); color: var(--gold);
    display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;
    border: 1px solid var(--darker-gold-1); line-height: 1; flex-shrink:0;
}
.author-name-link-guide-card { 
    color: inherit !important; font-weight: 500; font-size: 0.85em; text-decoration: none;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.author-name-link-guide-card:hover { text-decoration: underline; }

.guide-author-info-v2.username-role-admin a { color: var(--role-color-admin) !important; }
.guide-author-info-v2.username-role-member a { color: var(--role-color-member) !important; }
.guide-author-info-v2.username-role-scg_uye a { color: var(--role-color-scg_uye) !important; }
.guide-author-info-v2.username-role-ilgarion_turanis a { color: var(--role-color-ilgarion_turanis) !important; }
.guide-author-info-v2.username-role-dis_uye a { color: var(--role-color-dis_uye) !important; }


.guide-stats-and-visibility { 
    display: flex;
    flex-direction: column; 
    align-items: flex-end; 
    gap: 6px;
}
.guide-stats-v2 { display: flex; gap: 10px; align-items:center; }
.guide-stats-v2 .stat-item i.fas { margin-right: 3px; font-size:0.85em; }

.guide-visibility-tags { 
    display: flex;
    flex-wrap: nowrap; 
    gap: 4px;
    justify-content: flex-end; 
    max-width: 150px; 
    overflow: hidden;
}
.visibility-role-tag {
    font-size: 0.65rem; 
    padding: 2px 7px;
    border-radius: 10px; 
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border: 1px solid;
    display: inline-flex; 
    align-items: center;
    gap: 3px;
    white-space: nowrap; 
}
.visibility-role-tag i.fas { font-size: 0.8em; }

.visibility-role-tag.role-admin { background-color: var(--role-bg-admin); border-color: var(--role-border-admin); color: var(--role-color-admin); }
.visibility-role-tag.role-member { background-color: var(--role-bg-member); border-color: var(--role-border-member); color: var(--role-color-member); }
.visibility-role-tag.role-scg_uye { background-color: var(--role-bg-scg_uye); border-color: var(--role-border-scg_uye); color: var(--role-color-scg_uye); }
.visibility-role-tag.role-ilgarion_turanis { background-color: var(--role-bg-ilgarion_turanis); border-color: var(--role-border-ilgarion_turanis); color: var(--role-color-ilgarion_turanis); }
.visibility-role-tag.role-dis_uye { background-color: var(--role-bg-dis_uye); border-color: var(--role-border-dis_uye); color: var(--role-color-dis_uye); }
.visibility-role-tag.role-public { background-color: var(--role-bg-public); border-color: var(--role-border-public); color: var(--role-color-public); }
.visibility-role-tag.role-members-only { background-color: var(--role-bg-members-only); border-color: var(--role-border-members-only); color: var(--role-color-members-only); }
.visibility-role-tag.role-default { background-color: var(--role-bg-default); border-color: var(--role-border-default); color: var(--role-color-default); }


.no-guides-message { 
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px;
    background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1);
    margin-top: 20px;
}
.no-guides-message a { color:var(--turquase); font-weight:bold;}
.no-guides-message a:hover { color:var(--light-turquase);}

.guest-info-message {
    padding: 15px 20px;
    margin: 0 0 30px 0; 
    background-color: rgba(var(--turquase-rgb), 0.1);
    border: 1px solid var(--turquase);
    border-radius: 8px;
    color: var(--light-turquase);
    font-size: 0.95rem;
    text-align: center;
    line-height: 1.6;
}
.guest-info-message a {
    color: var(--gold);
    font-weight: 600;
    text-decoration: none;
}
.guest-info-message a:hover {
    text-decoration: underline;
}


@media (max-width: 768px) {
    .guides-page-container-v2 { padding: 20px; }
    .page-top-nav-controls-guides { flex-direction: column; align-items: stretch; gap:15px; }
    .btn-create-new-guide-v2 { width:100%; text-align:center; justify-content:center;}
    .page-top-nav-controls-guides .page-title-guides { font-size: 2rem; text-align:center; }
    .guides-filter-bar { flex-direction:column; align-items:stretch; }
    .guides-search-form input[type="text"] { width:100%; }
    .guides-grid { grid-template-columns: 1fr; gap:25px; } 
    .guide-card-thumbnail { height: 200px; }
    .guide-card-footer-v2 { flex-direction:column; align-items:flex-start; gap:8px;}
    .guide-stats-and-visibility { align-items:flex-start; width:100%;}
    .guide-visibility-tags { justify-content:flex-start;}
}

/* Rol etiketleri için genel stil (guide_detail.php'deki ile benzer) */
.guide-visibility-tags {
    display: flex;
    flex-wrap: wrap; /* Etiketler sığmazsa alt satıra kaysın */
    gap: 6px; /* Etiketler arası boşluk */
    justify-content: flex-end; /* Sağda veya kartın altında olabilir */
    margin-top: 5px; /* Üstündeki elemandan boşluk */
}
.visibility-role-tag {
    font-size: 0.7rem; /* Etiket boyutu biraz daha küçük */
    padding: 3px 8px;
    border-radius: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border: 1px solid;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    line-height: 1.2; /* Dikey hizalama için */
}
.visibility-role-tag i.fas { font-size: 0.85em; }

/* Rol renkleri için CSS sınıfları (style.css'e eklenmeli veya burada tanımlanmalı) */
/* Örnekler (guide_detail.php'deki stillerle aynı olmalı): */
.visibility-role-tag.role-public { background-color: var(--role-bg-public); border-color: var(--role-border-public); color: var(--role-color-public); }
.visibility-role-tag.role-members-only { background-color: var(--role-bg-members-only); border-color: var(--role-border-members-only); color: var(--role-color-members-only); }
.visibility-role-tag.role-admin { background-color: var(--role-bg-admin); border-color: var(--role-border-admin); color: var(--role-color-admin); }
.visibility-role-tag.role-scg_uye { background-color: var(--role-bg-scg_uye); border-color: var(--role-border-scg_uye); color: var(--role-color-scg_uye); }
.visibility-role-tag.role-ilgarion_turanis { background-color: var(--role-bg-ilgarion_turanis); border-color: var(--role-border-ilgarion_turanis); color: var(--role-color-ilgarion_turanis); }
/* Diğer roller için de benzer tanımlamalar... */
.visibility-role-tag.role-restricted { background-color: rgba(128,0,0,0.1); border-color: #800000; color: #DC143C; } /* Kısıtlı erişim için */
.visibility-role-tag.role-default { background-color: var(--role-bg-default); border-color: var(--role-border-default); color: var(--role-color-default); }


/* Kart içindeki beğeni butonu (JS ile dinamik olarak güncellenecek) */
.btn-like-guide {
    background-color: transparent;
    border: 1px solid var(--grey);
    color: var(--light-grey);
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-like-guide:hover:not(.liked) {
    border-color: var(--gold);
    color: var(--gold);
}
.btn-like-guide.liked {
    border-color: var(--red);
    color: var(--red);
    background-color: var(--transparent-red);
}
.btn-like-guide.liked:hover {
    border-color: var(--dark-red);
    color: var(--dark-red);
}
.btn-like-guide .like-icon { font-size: 0.9em; }
.btn-like-guide .like-text-card { font-weight: 500; }

.guide-card-footer-v2 .guide-stats-and-visibility {
    display: flex;
    flex-direction: column;
    align-items: flex-end; /* Sağa yasla */
    gap: 8px; /* İstatistikler ve etiketler arası boşluk */
}
.guide-card-footer-v2 .guide-stats-v2 {
    /* Mevcut stiliniz */
}
</style>

<main class="main-content">
    <div class="container guides-page-container-v2">
        <div class="page-top-nav-controls-guides">
            <h1 class="page-title-guides"><?php echo htmlspecialchars($page_title); ?> (<?php echo count($all_guides_with_visibility); ?> Rehber)</h1>
            <?php if ($can_create_guide): ?>
                <a href="new_guide.php" class="btn-create-new-guide-v2"><i class="fas fa-plus-circle"></i> Yeni Rehber Oluştur</a>
            <?php endif; ?>
        </div>

        <p class="guides-page-description">
            Topluluğumuzun bilgi birikimini ve deneyimlerini paylaştığı bu alanda, Star Citizen evrenindeki çeşitli konularda hazırlanmış rehberlere ulaşabilirsiniz.
            Bu rehberler, üyelerimiz ve yetkililerimiz tarafından özenle hazırlanmıştır.
        </p>

        <div class="guides-filter-bar">
            <form method="GET" action="guides.php" class="guides-search-form">
                <div class="form-group">
                    <label for="search_guide">Rehber Ara</label>
                    <input type="text" id="search_guide" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Başlık, içerik veya yazara göre ara...">
                    <button type="submit" class="btn-search-guides"><i class="fas fa-search"></i></button>
                </div>
            </form>
            <?php if (!empty($search_term) || $filter_author_id): ?>
                <a href="guides.php" class="btn btn-sm btn-clear-filters">Filtreleri Temizle</a>
            <?php endif; ?>
        </div>

        <?php // Hata ve başarı mesajları header.php'de gösteriliyor ?>

        <?php if (!$current_user_is_logged_in && !empty($all_guides_with_visibility)): ?>
            <div class="info-message" style="text-align: center; margin-bottom: 20px; background-color: var(--transparent-turquase-2); color: var(--turquase); border: 1px solid var(--turquase); padding: 10px; border-radius: 5px;">
                <i class="fas fa-info-circle"></i> Şu anda sadece herkese açık rehberleri görüntülüyorsunuz. Daha fazla rehbere erişmek için <a href="<?php echo get_auth_base_url(); ?>/login.php" style="color: var(--light-turquase); font-weight:bold;">giriş yapın</a> ya da <a href="<?php echo get_auth_base_url(); ?>/register.php" style="color: var(--light-turquase); font-weight:bold;">kayıt olun</a>.
            </div>
        <?php endif; ?>


        <?php if (empty($all_guides_with_visibility)): ?>
            <p class="no-guides-message">
                <?php if (!empty($search_term) || $filter_author_id): ?>
                    Belirtilen kriterlere uygun rehber bulunamadı.
                <?php elseif (!$current_user_is_logged_in): ?>
                    Henüz herkese açık bir rehber paylaşılmamış. <a href="<?php echo get_auth_base_url(); ?>/login.php">Giriş yaparak</a> veya <a href="<?php echo get_auth_base_url(); ?>/register.php">kayıt olarak</a> daha fazla içeriğe ulaşabilirsiniz.
                <?php else: ?>
                    Henüz hiç rehber yayınlanmamış veya görüntüleme yetkiniz olan bir rehber bulunmuyor.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="guides-grid">
                <?php foreach ($all_guides_with_visibility as $guide): ?>
                    <?php
                        $author_data_for_popover = [
                            'id' => $guide['author_id'],
                            'username' => $guide['author_username'],
                            'avatar_path' => $guide['author_avatar_path'],
                            'ingame_name' => $guide['author_ingame_name'],
                            'discord_username' => $guide['author_discord_username'],
                            'user_event_count' => $guide['author_event_count'],
                            'user_gallery_count' => $guide['author_gallery_count'],
                            'user_roles_list' => $guide['author_roles_list']
                        ];
                        $excerpt = get_excerpt_from_markdown($guide['content_md'] ?? '', 140);
                        $user_has_liked_this = isset($guide['user_has_liked_this_guide']) && $guide['user_has_liked_this_guide'] > 0;
                    ?>
                    <div class="guide-card-v2">
                        <a href="guide_detail.php?slug=<?php echo htmlspecialchars($guide['slug']); ?>" class="guide-card-thumbnail-link">
                            <?php if ($guide['status'] !== 'published'): ?>
                                <span class="guide-status-overlay status-<?php echo htmlspecialchars($guide['status']); ?>">
                                    <?php
                                        switch($guide['status']){
                                            case 'draft': echo 'Taslak'; break;
                                            case 'archived': echo 'Arşiv'; break;
                                        }
                                    ?>
                                </span>
                            <?php endif; ?>
                            <img src="<?php echo !empty($guide['thumbnail_path']) ? ('/public/' . htmlspecialchars($guide['thumbnail_path'])) : 'https://via.placeholder.com/400x210/1a1101/bd912a?text=' . urlencode(substr(htmlspecialchars($guide['title'] ?? 'Rehber'),0,20)); ?>"
                                 alt="<?php echo htmlspecialchars($guide['title'] ?? 'Rehber'); ?> Thumbnail"
                                 class="guide-card-thumbnail">
                        </a>
                        <div class="guide-card-content-v2">
                            <h3 class="guide-card-title-v2">
                                <a href="guide_detail.php?slug=<?php echo htmlspecialchars($guide['slug']); ?>">
                                    <?php echo htmlspecialchars($guide['title'] ?? 'Başlık Yok'); ?>
                                </a>
                            </h3>
                            <p class="guide-card-excerpt-v2"><?php echo htmlspecialchars($excerpt); ?></p>
                            <div class="guide-card-footer-v2">
                                <?php
                                echo render_user_info_with_popover(
                                    $pdo, // $pdo eklendi
                                    $author_data_for_popover,
                                    'author-name-link-guide-card',
                                    'author-avatar-guide-card',
                                    'guide-author-info-v2'
                                );
                                ?>
                                <div class="guide-stats-and-visibility">
                                    <span class="guide-stats-v2">
                                        <span class="stat-item" title="Görüntülenme"><i class="fas fa-eye"></i> <?php echo htmlspecialchars($guide['view_count'] ?? '0'); ?></span>
                                        <?php if ($can_like_guide): ?>
                                            <button type="button" class="btn-like-guide <?php echo $user_has_liked_this ? 'liked' : ''; ?>" data-guide-id="<?php echo $guide['id']; ?>" title="<?php echo $user_has_liked_this ? 'Beğenmekten Vazgeç' : 'Beğen'; ?>">
                                                <span class="like-icon"><i class="fas <?php echo $user_has_liked_this ? 'fa-heart-crack' : 'fa-heart'; ?>"></i></span>
                                                <span class="like-text-card"><?php echo htmlspecialchars($guide['like_count'] ?? '0'); ?></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="stat-item" title="Beğeni"><i class="fas fa-thumbs-up"></i> <?php echo htmlspecialchars($guide['like_count'] ?? '0'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (($guide['status'] ?? '') === 'published' && !empty($guide['visibility_icons_classes'])): ?>
                                        <div class="guide-visibility-tags" title="Bu rehberi görebilecek roller/gruplar">
                                            <?php foreach($guide['visibility_icons_classes'] as $vis_info): ?>
                                                <span class="visibility-role-tag <?php echo htmlspecialchars($vis_info['color_class'] ?? 'role-default'); ?>" title="<?php echo htmlspecialchars($vis_info['name'] ?? ''); ?>">
                                                    <i class="<?php echo htmlspecialchars($vis_info['icon'] ?? 'fas fa-question-circle'); ?>"></i>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// guides.js dosyasındaki beğeni scripti zaten bu sayfada çalışacaktır.
// Popover scripti de footer.php'den genel olarak yükleniyor.
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
                } else { return; }
                document.documentElement.style.setProperty(`--${varName}-rgb`, `${r},${g},${b}`);
            }
        }
    };
    const themeColorsForRgb = [ /* ... style.css'deki gibi ... */
        'gold', 'transparent-gold', 'darker-gold-1', 'darker-gold-2',
        'turquase', 'light-turquase', 'transparent-turquase-2',
        'charcoal', 'grey', 'light-grey', 'lighter-grey', 'white', 'black',
        'light-gold', 'red', 'dark-red', 'transparent-red'
    ];
    themeColorsForRgb.forEach(colorName => setRgbVar(`--${colorName}`));

    // Beğeni butonları için event listener (eğer guides.js'de yoksa veya özelleştirilecekse)
    // guides.js'deki mevcut beğeni scripti bu sayfadaki .btn-like-guide class'lı butonları hedeflemeli.
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
