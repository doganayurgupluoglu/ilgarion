<?php
// public/guide_detail.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/guide_functions.php';
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$guide_slug = null;
if (isset($_GET['slug']) && !empty(trim($_GET['slug']))) {
    $guide_slug = trim($_GET['slug']);
} else {
    $_SESSION['error_message'] = "Geçersiz rehber adresi.";
    header('Location: ' . get_auth_base_url() . '/guides.php');
    exit;
}

// Bildirim okundu işaretleme
if (isset($_GET['notif_id']) && is_numeric($_GET['notif_id']) && is_user_logged_in()) {
    if (!function_exists('mark_notification_as_read')) { // Bu fonksiyon notification_functions.php'de olmalı
         require_once BASE_PATH . '/src/functions/notification_functions.php';
    }
    if (isset($pdo) && function_exists('mark_notification_as_read')) {
        mark_notification_as_read($pdo, (int)$_GET['notif_id'], $_SESSION['user_id'] ?? null);
    }
}

$guide = null;
$html_content = '';
$page_title = "Rehber Detayı";
$user_has_liked_this_guide = false;

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    $sql_guide_select = "SELECT
                g.*,
                u.id AS author_id,
                u.username AS author_username,
                u.avatar_path AS author_avatar_path,
                u.ingame_name AS author_ingame_name,
                u.discord_username AS author_discord_username,
                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS author_event_count,
                (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS author_gallery_count,
                (SELECT GROUP_CONCAT(r_author.name SEPARATOR ',')
                 FROM user_roles ur_author
                 JOIN roles r_author ON ur_author.role_id = r_author.id
                 WHERE ur_author.user_id = u.id) AS author_roles_list";

    if ($current_user_id) {
        $sql_guide_select .= ", (SELECT COUNT(*) FROM guide_likes gl WHERE gl.guide_id = g.id AND gl.user_id = :current_user_id_for_like) AS user_has_liked_this_guide_db";
    }

    $sql_guide_from_where = " FROM guides g
                              JOIN users u ON g.user_id = u.id
                              WHERE g.slug = :slug";

    $final_guide_sql = $sql_guide_select . $sql_guide_from_where;
    $stmt = $pdo->prepare($final_guide_sql);
    $guide_params = [':slug' => $guide_slug];
    if ($current_user_id) {
        $guide_params[':current_user_id_for_like'] = $current_user_id;
    }
    $stmt->execute($guide_params);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guide) {
        $user_has_liked_this_guide = isset($guide['user_has_liked_this_guide_db']) && $guide['user_has_liked_this_guide_db'] > 0;
        $can_view_this_guide = false;

        if ($guide['status'] === 'published') {
            if (($guide['is_public_no_auth'] ?? 0) == 1) {
                $can_view_this_guide = true;
            } elseif ($current_user_is_approved) { // Giriş yapmış ve onaylı kullanıcılar için diğer kontroller
                if (($guide['is_members_only'] ?? 0) == 1) {
                    $can_view_this_guide = true;
                } else { // Belirli rollere özel mi kontrol et
                    $stmt_guide_roles = $pdo->prepare("SELECT r.name FROM guide_visibility_roles gvr JOIN roles r ON gvr.role_id = r.id WHERE gvr.guide_id = ?");
                    $stmt_guide_roles->execute([$guide['id']]);
                    $guide_visible_to_role_names = $stmt_guide_roles->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($guide_visible_to_role_names) && !($guide['is_public_no_auth'] ?? 0) && !($guide['is_members_only'] ?? 0)) {
                        // Hiçbir özel rol atanmamışsa VE public/members değilse, varsayılan olarak sadece admin ve sahibi görebilir.
                        // Bu durum normalde oluşmamalı, bir rehber ya public, ya members ya da belirli rollere olmalı.
                        // Şimdilik, bu durumda sadece admin ve sahibine izin verelim.
                        if ($current_user_is_admin || ($current_user_id && $current_user_id == $guide['user_id'])) {
                            $can_view_this_guide = true;
                        }
                    } elseif (!empty($guide_visible_to_role_names)) {
                        $user_current_roles = $_SESSION['user_roles'] ?? [];
                        if (!empty(array_intersect($user_current_roles, $guide_visible_to_role_names))) {
                            $can_view_this_guide = true;
                        }
                    }
                }
            }
        }

        // Sahibi veya admin her zaman görebilir (taslaklar ve arşivlenmişler dahil)
        if ($current_user_is_logged_in && ($current_user_id == $guide['user_id'] || $current_user_is_admin)) {
            $can_view_this_guide = true;
        }

        if (!$can_view_this_guide) {
            $_SESSION['error_message'] = "Bu rehberi görüntüleme yetkiniz bulunmamaktadır veya rehber yayında değil.";
            header('Location: ' . get_auth_base_url() . '/guides.php');
            exit;
        }

        $page_title = htmlspecialchars($guide['title'] ?? 'Rehber');
        $html_content = convert_markdown_to_html_guide($guide['content_md'] ?? '');

        // Görüntülenme sayısını artır (sahibi veya admin değilse ve yayınlanmışsa)
        if ($guide['status'] === 'published' && $can_view_this_guide && !($current_user_id && $current_user_id == $guide['user_id']) && !$current_user_is_admin) {
            try {
                $stmt_view = $pdo->prepare("UPDATE guides SET view_count = view_count + 1 WHERE id = ?");
                $stmt_view->execute([$guide['id']]);
            } catch (PDOException $e_view) {
                error_log("Rehber görüntülenme (ID: ".$guide['id']."): " . $e_view->getMessage());
            }
        }
    } else {
        $_SESSION['error_message'] = "Rehber bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/guides.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Rehber detayı (Slug: ".$guide_slug."): " . $e->getMessage());
    $_SESSION['error_message'] = "Rehber yüklenirken bir hata oluştu.";
    // $guide null kalacak ve aşağıdaki HTML'de hata mesajı gösterilecek
}


// Yetki kontrolleri (butonlar için)
$can_edit_this_guide = false;
$can_delete_this_guide = false;
if ($current_user_id && $guide) {
    if (has_permission($pdo, 'guide.edit_all', $current_user_id) || (has_permission($pdo, 'guide.edit_own', $current_user_id) && $guide['user_id'] == $current_user_id)) {
        $can_edit_this_guide = true;
    }
    if (has_permission($pdo, 'guide.delete_all', $current_user_id) || (has_permission($pdo, 'guide.delete_own', $current_user_id) && $guide['user_id'] == $current_user_id)) {
        $can_delete_this_guide = true;
    }
}
$can_like_this_guide = $current_user_is_approved && has_permission($pdo, 'guide.like', $current_user_id);


// render_guide_meta_and_actions_detail_v2 fonksiyonunu bu dosyaya taşıyalım veya include edelim.
// Şimdilik bu dosyaya taşıdım.
if (!function_exists('determine_guide_visibility_tags_detail_v2')) {
    function determine_guide_visibility_tags_detail_v2(PDO $pdo_param, $guide_data_for_tag) {
        if (!$guide_data_for_tag) return '';
        $tags_html = '';
        // Bu map'leri globalden almak yerine burada tanımlayalım veya parametre olarak alalım
        $role_display_names_map_detail = ['admin' => 'Yönetici', 'member' => 'Üye', 'scg_uye' => 'SCG Üyesi', 'ilgarion_turanis' => 'Ilgarion Turanis', 'dis_uye' => 'Dış Üye'];
        $role_icons_map_detail = ['admin' => 'fas fa-user-shield', 'member' => 'fas fa-user', 'scg_uye' => 'fas fa-users-cog', 'ilgarion_turanis' => 'fas fa-dragon', 'dis_uye' => 'fas fa-user-tag'];
        $role_color_classes_map_detail = ['admin' => 'role-admin', 'member' => 'role-member', 'scg_uye' => 'role-scg_uye', 'ilgarion_turanis' => 'role-ilgarion_turanis', 'dis_uye' => 'role-dis_uye', 'public' => 'role-public', 'members-only' => 'role-members-only', 'draft' => 'role-draft', 'archived' => 'role-archived', 'restricted' => 'role-restricted', 'default' => 'role-default'];


        if (($guide_data_for_tag['status'] ?? 'draft') === 'draft') {
            $tags_html .= '<span class="guide-meta-item visibility-tag-detail '.htmlspecialchars($role_color_classes_map_detail['draft']).'" title="Bu bir taslaktır"><i class="fas fa-pencil-alt"></i> Taslak</span>';
        } elseif (($guide_data_for_tag['status'] ?? 'draft') === 'archived') {
            $tags_html .= '<span class="guide-meta-item visibility-tag-detail '.htmlspecialchars($role_color_classes_map_detail['archived']).'" title="Bu rehber arşivlenmiştir"><i class="fas fa-archive"></i> Arşivlendi</span>';
        }

        if (($guide_data_for_tag['status'] ?? 'draft') === 'published') {
            if (($guide_data_for_tag['is_public_no_auth'] ?? 0) == 1) {
                $tags_html .= '<span class="guide-meta-item visibility-tag-detail '.htmlspecialchars($role_color_classes_map_detail['public']).'" title="Herkese Açık"><i class="fas fa-globe"></i> Herkese Açık</span>';
            } elseif (($guide_data_for_tag['is_members_only'] ?? 0) == 1) {
                $tags_html .= '<span class="guide-meta-item visibility-tag-detail '.htmlspecialchars($role_color_classes_map_detail['members-only']).'" title="Sadece Üyelere Özel"><i class="fas fa-users"></i> Tüm Üyelere</span>';
            } else {
                $stmt_guide_roles = $pdo_param->prepare(
                    "SELECT r.name, r.description as role_desc FROM guide_visibility_roles gvr
                     JOIN roles r ON gvr.role_id = r.id
                     WHERE gvr.guide_id = ?"
                );
                $stmt_guide_roles->execute([$guide_data_for_tag['id']]);
                $visible_roles = $stmt_guide_roles->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($visible_roles)) {
                    foreach ($visible_roles as $role) {
                        $role_name_key = $role['name'];
                        $display_name = htmlspecialchars($role['role_desc'] ?: ($role_display_names_map_detail[$role_name_key] ?? ucfirst(str_replace('_', ' ', $role_name_key))));
                        $icon = $role_icons_map_detail[$role_name_key] ?? 'fas fa-user-tag';
                        $color_class = $role_color_classes_map_detail[$role_name_key] ?? 'role-default';
                        $tags_html .= '<span class="guide-meta-item visibility-tag-detail '.htmlspecialchars($color_class).'" title="'.htmlspecialchars($display_name).' rolüne özel"><i class="'.htmlspecialchars($icon).'"></i> '.htmlspecialchars($display_name).'</span>';
                    }
                } elseif (empty($tags_html)) { // Eğer yayınlanmış ama public/members değil ve rol de yoksa (normalde olmamalı)
                     $tags_html .= '<span class="guide-meta-item visibility-tag-detail '.htmlspecialchars($role_color_classes_map_detail['restricted']).'" title="Kısıtlı Erişim (Rol Atanmamış)"><i class="fas fa-lock"></i> Kısıtlı</span>';
                }
            }
        }
        return $tags_html;
    }
}


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* guide_detail.php için Event Detail Benzeri Stiller */
:root {
    /* style.css'den gelen renkler kullanılacak */
    --role-color-admin: var(--gold);
    --role-bg-admin: rgba(var(--gold-rgb), 0.2);
    --role-border-admin: var(--gold);
    --role-color-member: var(--lighter-grey);
    --role-bg-member: rgba(var(--lighter-grey-rgb), 0.15);
    --role-border-member: var(--light-grey);
    --role-color-scg_uye: #A52A2A;
    --role-bg-scg_uye: rgba(165, 42, 42, 0.15);
    --role-border-scg_uye: #A52A2A;
    --role-color-ilgarion_turanis: var(--turquase);
    --role-bg-ilgarion_turanis: rgba(var(--turquase-rgb), 0.15);
    --role-border-ilgarion_turanis: var(--turquase);
    --role-color-dis_uye: var(--light-grey);
    --role-bg-dis_uye: rgba(var(--light-grey-rgb), 0.2);
    --role-border-dis_uye: var(--grey);
    --role-color-public: var(--light-turquase);
    --role-bg-public: rgba(var(--turquase-rgb), 0.1);
    --role-border-public: var(--light-turquase);
    --role-color-members-only: var(--light-gold);
    --role-bg-members-only: rgba(var(--gold-rgb),0.1);
    --role-border-members-only: var(--light-gold);
    --role-color-draft: var(--light-grey);      
    --role-bg-draft: rgba(var(--grey-rgb),0.2);
    --role-border-draft: var(--grey);
    --role-color-archived: var(--grey);   
    --role-bg-archived: rgba(var(--light-grey-rgb),0.1);
    --role-border-archived: var(--light-grey);
    --role-color-default: var(--grey);
    --role-bg-default: var(--darker-gold-2);
    --role-border-default: var(--grey);
}

.guide-detail-page-container-v3 {
    width: 100%;
    max-width: 1100px; /* event_detail ile benzer genişlik */
    margin: 30px auto;
    padding: 0; /* Ana container padding'i kaldırıldı, iç bölümler yönetecek */
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 102px);
}

.page-top-nav-controls-gd { 
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding: 0 20px; /* Yan boşluklar eklendi */
}
.back-to-guides-link-gd { 
    font-size: 0.9rem; color: var(--turquase); text-decoration: none;
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 16px; border-radius: 20px; 
    background-color: transparent;
    border: 1px solid var(--turquase);
    transition: all 0.2s ease;
}
.back-to-guides-link-gd:hover { color: var(--white); background-color: var(--turquase); text-decoration:none;}
.back-to-guides-link-gd i.fas { font-size: 0.85em; }

.btn-edit-guide-gd { 
    background-color: var(--gold); color: var(--darker-gold-2); padding: 9px 20px; 
    border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 0.9rem;
    transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;
    border: none; /* Kenarlık kaldırıldı, event_detail'deki gibi */
    box-shadow: 0 2px 5px rgba(0,0,0,0.15);
}
.btn-edit-guide-gd:hover { background-color: var(--light-gold); color: var(--black); transform: translateY(-1px); }
.btn-edit-guide-gd i.fas { font-size:0.9em;}

.guide-main-header-gd {
    padding: 25px 30px; 
    border-radius: 8px; /* event_detail ile uyumlu */
    margin: 0 20px 30px 20px; /* Yan boşluklar ve alt boşluk */
    border: 1px solid var(--darker-gold-2); /* event_detail ile uyumlu */
    background-color: transparent; /* Arka plan kaldırıldı */
}
.guide-main-title-gd {
    color: var(--gold); font-size: 2.3rem; 
    font-family: var(--font); margin: 0 0 20px 0; 
    line-height: 1.3; text-align: left;
    padding-bottom: 18px; 
    border-bottom: 1px solid var(--darker-gold-1); 
}
.guide-status-alert-detail { 
    padding: 10px 15px; margin: 15px 0; border-radius: 6px; font-size: 0.95rem;
    border: 1px solid transparent; text-align:left; font-weight:500;
    display: flex; align-items: center; gap: 8px; 
}
.guide-status-alert-detail.draft { background-color: rgba(var(--light-gold-rgb),0.15) ; color: var(--light-gold); border-color: var(--gold); }
.guide-status-alert-detail.archived { background-color: rgba(var(--grey-rgb),0.2); color: var(--light-grey); border-color: var(--grey); }

.guide-meta-details-gd { 
    text-align: left; margin-top: 0; 
    padding-top: 15px; 
    font-size: 0.9rem; 
    color: var(--light-grey);
    display: flex; 
    flex-wrap: wrap; 
    gap: 10px 18px; 
    align-items: center;
}
.guide-meta-item { display: inline-flex; align-items: center; gap:6px; }
.guide-meta-item strong { color: var(--lighter-grey); font-weight: 500; }
.guide-meta-item .meta-icon { color: var(--light-gold); font-size:0.95em;}

.author-info-gd { 
    display: inline-flex; align-items: center; gap: 10px; cursor: default;
}
.author-avatar-gd { 
    width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold);
}
.avatar-placeholder-gd { 
    width: 36px; height: 36px; font-size:1.2rem; background-color:var(--grey); color:var(--gold); 
    display:flex; align-items:center; justify-content:center; font-weight:bold; 
    border:2px solid var(--darker-gold-1); border-radius:50%; line-height:1;
}
.author-name-link-gd { 
    color: inherit !important; font-weight: 600; font-size:1em; text-decoration:none;
}
.author-name-link-gd:hover { text-decoration:underline; }

.visibility-tags-container-gd { /* Birden fazla etiket için sarmalayıcı */
    display: flex;
    flex-wrap: wrap;
    gap: 8px; /* Etiketler arası boşluk */
    margin-left: auto; /* Meta item'larından sonra sağa yasla */
}
.visibility-tag-detail {
    padding: 4px 10px; 
    border-radius: 18px; 
    font-size: 0.75rem; 
    font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px;
    border: 1px solid; display:inline-flex; align-items:center; gap:5px;
}
.visibility-tag-detail.role-public { background-color: var(--role-bg-public); border-color: var(--role-border-public); color: var(--role-color-public); }
.visibility-tag-detail.role-members-only { background-color: var(--role-bg-members-only); border-color: var(--role-border-members-only); color: var(--role-color-members-only); }
.visibility-tag-detail.role-draft { background-color: var(--role-bg-draft); border-color: var(--role-border-draft); color: var(--role-color-draft); }
.visibility-tag-detail.role-archived { background-color: var(--role-bg-archived); border-color: var(--role-border-archived); color: var(--role-color-archived); }
.visibility-tag-detail.role-admin { background-color: var(--role-bg-admin); border-color: var(--role-border-admin); color: var(--role-color-admin); }
.visibility-tag-detail.role-member { background-color: var(--role-bg-member); border-color: var(--role-border-member); color: var(--role-color-member); }
.visibility-tag-detail.role-scg_uye { background-color: var(--role-bg-scg_uye); border-color: var(--role-border-scg_uye); color: var(--role-color-scg_uye); }
.visibility-tag-detail.role-ilgarion_turanis { background-color: var(--role-bg-ilgarion_turanis); border-color: var(--role-border-ilgarion_turanis); color: var(--role-color-ilgarion_turanis); }
.visibility-tag-detail.role-dis_uye { background-color: var(--role-bg-dis_uye); border-color: var(--role-border-dis_uye); color: var(--role-color-dis_uye); }
.visibility-tag-detail.role-default { background-color: var(--role-bg-default); border-color: var(--role-border-default); color: var(--role-color-default); }


.guide-actions-bar-gd { 
    margin-top:20px; padding-top:20px;
    border-top:1px solid var(--darker-gold-1);
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;
}
.guide-like-section-gd { display:flex; align-items:center; gap:10px; }
.btn-like-guide-detail { 
    background-color:var(--turquase); color:var(--black);
    padding: 8px 18px; 
    border-radius: 20px; font-size: 0.9rem; font-weight:500;
    border:none; transition: all 0.2s ease; display:inline-flex; align-items:center; gap:6px;
}
.btn-like-guide-detail.liked { background-color:var(--dark-red); color:var(--white); }
.btn-like-guide-detail:hover:not(.liked) { background-color:var(--light-turquase); color:var(--darker-gold-2); }
.btn-like-guide-detail.liked:hover { background-color:var(--red); }
.btn-like-guide-detail .like-icon { font-size:1em; }
.btn-like-guide-detail .like-text { font-weight:600;}
.like-action-status-detail { font-size:0.85rem; color:var(--light-grey); font-style:italic;}

.guide-admin-owner-actions-gd { display:flex; gap:10px; }
.guide-admin-owner-actions-gd .btn-sm {
    font-size:0.88rem; padding:8px 16px; border-radius:20px; font-weight:500;
}


.guide-content-display-area {
    padding: 25px 30px; 
    border-radius: 8px; 
    margin: 0 20px 30px 20px; /* Yan boşluklar ve alt boşluk */
    border: 1px solid var(--darker-gold-2); /* event_detail ile uyumlu */
    background-color: transparent; /* Arka plan kaldırıldı */
}
.guide-content-display-area h3.section-title-gd { 
    color: var(--light-gold); font-size: 1.7rem; margin: 0 0 25px 0;
    padding-bottom: 15px; border-bottom: 1px solid var(--darker-gold-1);
    font-family: var(--font); font-weight: 600; display:flex; align-items:center; gap:10px;
}

.guide-thumbnail-container-detail {
    margin-bottom: 30px;
    text-align: center; 
    background-color: transparent; /* Arka plan kaldırıldı */
    padding: 0; /* Padding kaldırıldı, resim kendi boyutunda olsun */
    border-radius: 8px;
    /* border: 1px solid var(--darker-gold-1); -- Kenarlık kaldırıldı, resmin kendi kenarlığı olabilir */
}
.guide-thumbnail-container-detail img {
    max-width: 100%;
    width:auto; 
    max-height: 450px; 
    border-radius: 6px;
    border: 1px solid var(--grey);
    box-shadow: 0 5px 15px rgba(0,0,0,0.25);
}
.no-thumbnail-message {
    color: var(--light-grey); font-style: italic; padding: 20px 0; text-align:center;
}

.guide-content-html { 
    line-height: 1.8; 
    font-size: 1.05rem; 
    color: var(--lighter-grey); 
    word-wrap: break-word;
}
.guide-content-html > *:first-child { margin-top: 0 !important; }
.guide-content-html h1, .guide-content-html h2, .guide-content-html h3, 
.guide-content-html h4, .guide-content-html h5, .guide-content-html h6 { 
    font-family: var(--font); 
    color: var(--light-gold); 
    margin-top: 2em; 
    margin-bottom: 1em; 
    line-height: 1.35; 
    padding-bottom: 0.5em; 
    border-bottom: 1px solid var(--darker-gold-2); 
    font-weight:600;
}
.guide-content-html h1 { font-size: 2em; color: var(--gold); } 
.guide-content-html h2 { font-size: 1.75em; } 
.guide-content-html h3 { font-size: 1.5em; }
.guide-content-html h4 { font-size: 1.25em; color: var(--gold); } 
.guide-content-html h5 { font-size: 1.1em; } 
.guide-content-html h6 { font-size: 1em; color: var(--light-grey); text-transform: uppercase; letter-spacing: 0.5px; border-bottom:none;}

.guide-content-html p { margin-bottom: 1.25em; }
.guide-content-html ul, .guide-content-html ol { margin: 0 0 1.25em 25px; padding-left: 20px; }
.guide-content-html ul li, .guide-content-html ol li { margin-bottom: 0.6em; line-height: 1.65; }
.guide-content-html ul { list-style-type: disc; } 
.guide-content-html ul ul { list-style-type: circle; margin-top: 0.5em;} 
.guide-content-html ul ul ul { list-style-type: square; }
.guide-content-html ol { list-style-type: decimal; } 
.guide-content-html ol ol { list-style-type: lower-alpha; margin-top: 0.5em;} 
.guide-content-html ol ol ol { list-style-type: lower-roman; }
.guide-content-html li > p { margin-bottom: 0.5em; }

.guide-content-html blockquote { 
    border-left: 5px solid var(--gold); 
    padding: 15px 22px; 
    margin: 1.6em 0; 
    background-color: rgba(var(--darker-gold-2-rgb), 0.7); /* Hafif transparan */
    color: var(--light-grey); 
    border-radius: 0 6px 6px 0; 
    font-style: italic;
}
.guide-content-html blockquote p:last-child { margin-bottom: 0; } 
.guide-content-html blockquote strong { color: var(--light-gold); font-style:normal; }

.guide-content-html pre { 
    background-color: var(--black); 
    border: 1px solid var(--darker-gold-1); 
    padding: 1.1em; 
    border-radius: 6px; 
    overflow-x: auto; 
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; 
    font-size: 0.9rem; 
    line-height: 1.6; 
    color: #abb2bf; 
    margin-bottom: 1.6em; 
}
.guide-content-html code { 
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; 
    background-color: var(--grey); 
    padding: 0.2em 0.55em; 
    border-radius: 4px; 
    font-size: 0.9rem; 
    color: var(--light-turquase); 
}
.guide-content-html pre > code { background-color: transparent; padding: 0; border-radius: 0; font-size: inherit; color: inherit; border: none; }

.guide-content-html table { 
    width: 100%; border-collapse: collapse; margin-bottom: 1.6em; 
    border: 1px solid var(--darker-gold-1); font-size: 0.92em; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.08); border-radius:6px; overflow:hidden; 
}
.guide-content-html th, .guide-content-html td { 
    border: 1px solid var(--darker-gold-1); padding: 10px 14px; text-align: left; 
}
.guide-content-html thead th { 
    background-color: var(--darker-gold-1); color: var(--gold); font-weight: 600; 
}
.guide-content-html tbody tr:nth-child(even) { background-color: var(--darker-gold-2); } 
.guide-content-html tbody tr:hover { background-color: var(--grey); }

.guide-content-html a { color: var(--turquase); text-decoration: none; font-weight: 500; } 
.guide-content-html a:hover { text-decoration: underline; color: var(--light-turquase); }
.guide-content-html img { 
    max-width: 100%; height: auto; border-radius: 6px; 
    margin: 1.6em auto; display: block; border: 1px solid var(--darker-gold-1); 
    box-shadow: 0 3px 8px rgba(0,0,0,0.12); 
}
.guide-content-html hr { 
    border: 0; border-top: 2px solid var(--darker-gold-1); margin: 2.5em 0; 
}
.guide-content-html strong { color: var(--gold); } 
.guide-content-html em { color: var(--lighter-grey); font-style: italic; }

.guide-detail-page-container-v3 .empty-message { 
    text-align: center; font-size: 1.1rem; color: var(--red); padding: 30px 20px; 
    background-color: var(--transparent-red); border: 1px solid var(--dark-red); border-radius: 6px;
    margin: 0 20px; /* Yan boşluklar */
}

@media (max-width: 768px) {
    .guide-detail-page-container-v3 { padding: 0; } /* Ana container padding mobil için sıfırlandı */
    .page-top-nav-controls-gd { flex-direction:column; align-items:stretch; gap:15px; padding: 0 15px 15px 15px;} /* Mobil için padding eklendi */
    .back-to-guides-link-gd, .btn-edit-guide-gd { width:100%; text-align:center; justify-content:center;}
    .guide-main-header-gd { padding: 20px; margin: 0 15px 20px 15px;}
    .guide-main-title-gd { font-size:2rem; }
    .guide-meta-details-gd { flex-direction:column; align-items:flex-start; gap:10px;} /* Meta item'lar alt alta */
    .visibility-tags-container-gd { margin-left:0; justify-content:flex-start; width:100%;}
    .guide-actions-bar-gd { flex-direction:column; align-items:stretch; }
    .guide-like-section-gd, .guide-admin-owner-actions-gd { width:100%; display:flex; justify-content:center;}
    .guide-admin-owner-actions-gd .btn-sm { flex-grow:1; text-align:center;}
    .guide-content-display-area { padding:20px; margin: 0 15px 20px 15px;}
    .guide-content-html { font-size:1rem; }
}
.visibility-tags-container-gd {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
}
.visibility-tag-detail {
    padding: 4px 10px;
    border-radius: 18px;
    font-size: 0.75rem;
    font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px;
    border: 1px solid; display:inline-flex; align-items:center; gap:5px;
}
/* ... (rol renk class'ları style.css'den gelmeli) ... */
</style>

<main class="main-content">
    <div class="container guide-detail-page-container-v3">

        <div class="page-top-nav-controls-gd">
            <a href="guides.php" class="back-to-guides-link-gd"><i class="fas fa-arrow-left"></i> Tüm Rehberlere Dön</a>
            <?php if ($guide && $can_edit_this_guide): ?>
                <a href="<?php echo get_auth_base_url(); ?>/new_guide.php?edit_id=<?php echo htmlspecialchars($guide['id'] ?? '0'); ?>" class="btn-edit-guide-gd"><i class="fas fa-edit"></i> Rehberi Düzenle</a>
            <?php endif; ?>
        </div>

        <?php if ($guide): ?>
            <div class="guide-main-header-gd">
                <h1 class="guide-main-title-gd"><?php echo htmlspecialchars($guide['title'] ?? 'Rehber Başlığı Yok'); ?></h1>

                <?php if (($guide['status'] ?? 'draft') === 'draft'): ?>
                    <p class="guide-status-alert-detail draft"><i class="fas fa-pencil-ruler"></i> Bu bir taslak rehberdir ve sadece sizin tarafınızdan (veya adminler tarafından) görüntülenebilir.</p>
                <?php elseif (($guide['status'] ?? 'draft') === 'archived'): ?>
                    <p class="guide-status-alert-detail archived"><i class="fas fa-archive"></i> Bu rehber arşivlenmiştir ve listelerde görünmez (sadece adminler ve sahibi görebilir).</p>
                <?php endif; ?>

                <div class="guide-meta-details-gd">
                    <?php
                        $author_data_for_popover_detail = [
                            'id' => $guide['author_id'],
                            'username' => $guide['author_username'],
                            'avatar_path' => $guide['author_avatar_path'],
                            'ingame_name' => $guide['author_ingame_name'],
                            'discord_username' => $guide['author_discord_username'],
                            'user_event_count' => $guide['author_event_count'],
                            'user_gallery_count' => $guide['author_gallery_count'],
                            'user_roles_list' => $guide['author_roles_list']
                        ];
                        echo render_user_info_with_popover(
                            $pdo, // $pdo eklendi
                            $author_data_for_popover_detail,
                            'author-name-link-gd',
                            'author-avatar-gd',
                            'author-info-gd'
                        );
                    ?>
                    <span class="guide-meta-item"><i class="fas fa-calendar-alt meta-icon"></i>Oluşturulma: <strong><?php echo date('d M Y', strtotime($guide['created_at'] ?? 'now')); ?></strong></span>
                    <?php if (($guide['updated_at'] ?? null) && strtotime($guide['updated_at']) > strtotime($guide['created_at'] ?? 'now') + 60 ): ?>
                        <span class="guide-meta-item"><i class="fas fa-edit meta-icon"></i>Güncellenme: <strong><?php echo date('d M Y', strtotime($guide['updated_at'])); ?></strong></span>
                    <?php endif; ?>
                    <span class="guide-meta-item"><i class="fas fa-eye meta-icon"></i>Görüntülenme: <strong><?php echo htmlspecialchars($guide['view_count'] ?? '0'); ?></strong></span>
                    <span class="guide-meta-item" id="likeCountDisplay_top"><i class="fas fa-thumbs-up meta-icon"></i>Beğeni: <strong><?php echo htmlspecialchars($guide['like_count'] ?? '0'); ?></strong></span>
                    <div class="visibility-tags-container-gd">
                        <?php echo determine_guide_visibility_tags_detail_v2($pdo, $guide); ?>
                    </div>
                </div>

                <div class="guide-actions-bar-gd">
                    <div class="guide-like-section-gd">
                        <?php if ($can_like_this_guide): ?>
                            <button type="button" class="btn btn-like-guide-detail <?php echo $user_has_liked_this_guide ? 'liked btn-danger' : 'btn-success'; ?>"
                                    data-guide-id="<?php echo htmlspecialchars($guide['id'] ?? '0'); ?>" id="likeButton_top"
                                    title="<?php echo $user_has_liked_this_guide ? 'Beğenmekten Vazgeç' : 'Beğen'; ?>">
                                <span class="like-icon" id="likeIcon_top"><i class="fas <?php echo $user_has_liked_this_guide ? 'fa-heart-crack' : 'fa-heart'; ?>"></i></span>
                                <span class="like-text"><?php echo $user_has_liked_this_guide ? ' Vazgeç' : ' Beğen'; ?></span>
                            </button>
                            <span class="like-action-status-detail" id="likeActionStatus_top"></span>
                        <?php endif; ?>
                    </div>
                    <div class="guide-admin-owner-actions-gd">
                        <?php if ($can_delete_this_guide): ?>
                            <form action="/src/actions/handle_guide.php" method="POST" class="inline-form" onsubmit="return confirm('Bu rehberi ve tüm ilişkili verilerini KALICI OLARAK silmek istediğinizden emin misiniz?');">
                                <input type="hidden" name="guide_id" value="<?php echo htmlspecialchars($guide['id'] ?? '0'); ?>">
                                <input type="hidden" name="action" value="delete_guide">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Sil</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="guide-content-display-area">
                <h3 class="section-title-gd"><i class="fas fa-file-alt"></i>Rehber İçeriği</h3>
                <?php if (!empty($guide['thumbnail_path'])): ?>
                    <div class="guide-thumbnail-container-detail">
                        <img src="/public/<?php echo htmlspecialchars($guide['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($guide['title'] ?? 'Rehber Thumbnail'); ?> Thumbnail">
                    </div>
                <?php else: ?>
                    <p class="no-thumbnail-message">Bu rehber için bir küçük resim bulunmamaktadır.</p>
                <?php endif; ?>

                <article class="guide-content-html">
                    <?php echo $html_content; ?>
                </article>
            </div>

        <?php else: ?>
            <p class="empty-message">Rehber bulunamadı veya yüklenirken bir sorun oluştu.</p>
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
                    r = parseInt(hexColor[1] + hexColor[1], 16); g = parseInt(hexColor[2] + hexColor[2], 16); b = parseInt(hexColor[3] + hexColor[3], 16);
                } else if (hexColor.length === 7) {
                    r = parseInt(hexColor.slice(1, 3), 16); g = parseInt(hexColor.slice(3, 5), 16); b = parseInt(hexColor.slice(5, 7), 16);
                } else { return; }
                document.documentElement.style.setProperty(`--${varName}-rgb`, `${r},${g},${b}`);
            }
        }
    };
    const themeColorsForRgbDetail = [ /* ... style.css'deki gibi ... */
        'gold', 'transparent-gold', 'darker-gold-1', 'darker-gold-2',
        'turquase', 'light-turquase', 'transparent-turquase-2',
        'charcoal', 'grey', 'light-grey', 'lighter-grey', 'white', 'black',
        'light-gold', 'dark-red', 'red'
    ];
    themeColorsForRgbDetail.forEach(colorName => setRgbVar(`--${colorName}`));
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
