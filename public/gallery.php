<?php
// public/gallery.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
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

$page_title = "Galeri";
$search_term = trim($_GET['search'] ?? '');
$filter_sort_by = $_GET['sort_by'] ?? 'newest';

$gallery_photos_data_for_js = [];
$gallery_photos_for_display = [];

$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;

// Sayfa görüntüleme yetkisi kontrolü
$can_view_gallery_page = false;
$access_message_gallery = "";

if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'gallery.view_all', $current_user_id))) {
    $can_view_gallery_page = true;
} elseif ($current_user_is_approved) {
    // Onaylı kullanıcılar, 'gallery.view_approved' (üyelere özel) VEYA 'gallery.view_public' VEYA rolleriyle eşleşenleri görebilir.
    // 'gallery.view_faction_only' gibi bir yetki varsa o da eklenebilir.
    if (has_permission($pdo, 'gallery.view_approved', $current_user_id) ||
        has_permission($pdo, 'gallery.view_public', $current_user_id)
        // Gerekirse buraya rollere özel genel bir görme yetkisi eklenebilir, örn: has_permission($pdo, 'gallery.view_restricted_by_role', $current_user_id)
       ) {
        $can_view_gallery_page = true;
    } else {
        $access_message_gallery = "Galeriyi görüntüleme yetkiniz bulunmamaktadır.";
    }
} elseif (has_permission($pdo, 'gallery.view_public', null)) { // Misafir kullanıcılar için public galeriyi görme
    $can_view_gallery_page = true;
} else {
     $access_message_gallery = "Galeriyi görmek için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> veya <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
}

if (!$can_view_gallery_page) {
    $_SESSION['error_message'] = $access_message_gallery ?: "Galeriyi görüntüleme yetkiniz bulunmamaktadır.";
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
}


try {
    $sql_select_fields = "SELECT
                    gp.id AS photo_id,
                    gp.image_path,
                    gp.description AS photo_description,
                    gp.is_public_no_auth,             -- Yeni sütun
                    gp.is_members_only,               -- Yeni sütun
                    gp.uploaded_at,
                    gp.user_id AS uploader_id,
                    u.username AS uploader_username,
                    u.avatar_path AS uploader_avatar_path,
                    u.ingame_name AS uploader_ingame_name,
                    u.discord_username AS uploader_discord_username,
                    (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS uploader_event_count,
                    (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS uploader_gallery_count,
                    (SELECT GROUP_CONCAT(r.name SEPARATOR ',')
                     FROM user_roles ur_uploader
                     JOIN roles r ON ur_uploader.role_id = r.id
                     WHERE ur_uploader.user_id = u.id) AS uploader_roles_list,
                    (SELECT COUNT(*) FROM gallery_photo_likes gpl WHERE gpl.photo_id = gp.id) AS like_count";

    if ($current_user_id) {
        $sql_select_fields .= ", (SELECT COUNT(*) FROM gallery_photo_likes gpl_user WHERE gpl_user.photo_id = gp.id AND gpl_user.user_id = :current_user_id_for_like) AS user_has_liked";
    }

    $sql_from_join = " FROM gallery_photos gp JOIN users u ON gp.user_id = u.id";
    $where_clauses = [];
    $params = [];

    if ($current_user_id) {
        $params[':current_user_id_for_like'] = $current_user_id;
    }

    // Görünürlük koşulları
    if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'gallery.view_all', $current_user_id))) {
        // Admin veya 'gallery.view_all' yetkisine sahip olanlar için ek bir WHERE koşulu gerekmez (tüm görünürlükleri görürler)
    } else {
        $visibility_or_conditions = [];
        // 1. Herkese açık fotoğraflar (gallery.view_public yetkisiyle veya misafir kullanıcı)
        if (has_permission($pdo, 'gallery.view_public', $current_user_id) || !$current_user_is_logged_in) {
            $visibility_or_conditions[] = "gp.is_public_no_auth = 1";
        }

        // 2. Onaylı üyelere açık fotoğraflar (gallery.view_approved yetkisiyle)
        if ($current_user_is_approved && has_permission($pdo, 'gallery.view_approved', $current_user_id)) {
            $visibility_or_conditions[] = "gp.is_members_only = 1";
        }
        
        // 3. Rol bazlı fotoğraflar
        if ($current_user_is_approved) { 
            $user_actual_role_ids_gallery = [];
            if ($current_user_id) {
                $stmt_user_role_ids_gallery = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :current_user_id_for_roles_gallery");
                $stmt_user_role_ids_gallery->execute([':current_user_id_for_roles_gallery' => $current_user_id]);
                $user_actual_role_ids_gallery = $stmt_user_role_ids_gallery->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($user_actual_role_ids_gallery)) {
                $role_placeholders_gallery_sql = [];
                foreach ($user_actual_role_ids_gallery as $idx_role_gallery => $role_id_val_gallery) {
                    $placeholder_role_gallery = ':user_role_id_for_gallery_visibility_' . $idx_role_gallery;
                    $role_placeholders_gallery_sql[] = $placeholder_role_gallery;
                    $params[$placeholder_role_gallery] = $role_id_val_gallery;
                }
                $in_clause_roles_gallery_sql = implode(',', $role_placeholders_gallery_sql);
                if (!empty($in_clause_roles_gallery_sql)) {
                     $visibility_or_conditions[] = "(gp.is_public_no_auth = 0 AND gp.is_members_only = 0 AND EXISTS (
                                                    SELECT 1 FROM gallery_photo_visibility_roles gpvr_check
                                                    WHERE gpvr_check.photo_id = gp.id AND gpvr_check.role_id IN (" . $in_clause_roles_gallery_sql . ")
                                                 ))";
                }
            }
        }
        
        if (!empty($visibility_or_conditions)) {
            $where_clauses[] = "(" . implode(" OR ", $visibility_or_conditions) . ")";
        } else {
            $where_clauses[] = "1=0"; 
        }
    }
    
    if (!empty($search_term)) {
        $where_clauses[] = "(u.username LIKE :search_username OR gp.description LIKE :search_description)";
        $params[':search_username'] = '%' . $search_term . '%';
        $params[':search_description'] = '%' . $search_term . '%';
    }
    
    $sql_where_string = "";
    if (!empty($where_clauses)) {
        $sql_where_string = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_order_by = " ORDER BY ";
    switch ($filter_sort_by) {
        case 'oldest':
            $sql_order_by .= "gp.uploaded_at ASC";
            break;
        case 'most_liked':
            $sql_order_by .= "like_count DESC, gp.uploaded_at DESC"; 
            break;
        case 'newest':
        default:
            $sql_order_by .= "gp.uploaded_at DESC";
            break;
    }

    $final_sql = $sql_select_fields . $sql_from_join . $sql_where_string . $sql_order_by;
    
    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params);
    $gallery_photos_for_display = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($gallery_photos_for_display) {
        foreach ($gallery_photos_for_display as $photo) {
            $gallery_photos_data_for_js[] = [
                'photo_id' => (int)$photo['photo_id'],
                'image_path_full' => '/public/' . htmlspecialchars($photo['image_path'], ENT_QUOTES, 'UTF-8'),
                'photo_description' => htmlspecialchars($photo['photo_description'] ?: '', ENT_QUOTES, 'UTF-8'),
                'uploader_user_id' => (int)$photo['uploader_id'],
                'uploader_username' => htmlspecialchars($photo['uploader_username'], ENT_QUOTES, 'UTF-8'),
                'uploader_avatar_path_full' => !empty($photo['uploader_avatar_path']) ? '/public/' . htmlspecialchars($photo['uploader_avatar_path'], ENT_QUOTES, 'UTF-8') : '',
                'uploader_ingame_name' => htmlspecialchars($photo['uploader_ingame_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_discord_username' => htmlspecialchars($photo['uploader_discord_username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_event_count' => (int)($photo['uploader_event_count'] ?? 0),
                'uploader_gallery_count' => (int)($photo['uploader_gallery_count'] ?? 0),
                'uploader_roles_list' => htmlspecialchars($photo['uploader_roles_list'] ?? '', ENT_QUOTES, 'UTF-8'),
                'uploaded_at_formatted' => date('d F Y, H:i', strtotime($photo['uploaded_at'])),
                'like_count' => (int)($photo['like_count'] ?? 0),
                'user_has_liked' => isset($photo['user_has_liked']) ? (bool)$photo['user_has_liked'] : false
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Galeri fotoğraflarını çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Galeri yüklenirken bir sorun oluştu.";
}

$can_upload_gallery = has_permission($pdo, 'gallery.upload');
$can_like_gallery = has_permission($pdo, 'gallery.like');

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* public/gallery.php için Stiller (mevcut gallery-page-container-v2 stillerinizle birleştirilebilir) */
.gallery-page-container-v2 {
    width: 100%;
    max-width: 1600px;
    margin: 40px auto;
    padding: 0 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.gallery-header-v2 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.gallery-header-v2 h2 {
    color: var(--gold);
    font-size: 2.4rem;
    font-family: var(--font);
    margin: 0;
    font-weight: 600;
}
/* .btn-outline-turquase stili navbar.php veya style.css'den gelmeli */

.gallery-controls-v2 {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 35px;
    padding: 15px 0;
    border-bottom: 1px solid var(--darker-gold-2);
}
.gallery-search-bar-v2 {
    display: flex;
    gap: 10px;
    flex-grow: 1;
    min-width: 280px;
    margin-right: 20px; /* Sağdaki filtrelerden ayırmak için */
}
.gallery-search-bar-v2 .form-group { margin-bottom: 0; position:relative; flex-grow:1;}
.gallery-search-bar-v2 label { display: none; }
.gallery-search-bar-v2 input[type="text"] {
    width: 100%;
    padding: 10px 40px 10px 18px;
    background-color: transparent;
    border: 1px solid var(--darker-gold-1);
    border-radius: 20px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    font-family: var(--font);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.gallery-search-bar-v2 input[type="text"]::placeholder { color: var(--light-grey); opacity: 0.7; }
.gallery-search-bar-v2 input[type="text"]:focus {
    outline: none; border-color: var(--gold);
    box-shadow: 0 0 0 2.5px var(--transparent-gold);
}
.gallery-search-bar-v2 .btn-search-gallery {
    position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--gold);
    padding: 8px; font-size: 1.1rem; cursor: pointer; border-radius: 50%;
    width:32px; height:32px; display:flex; align-items:center; justify-content:center;
}
.gallery-search-bar-v2 .btn-search-gallery:hover { background-color: var(--transparent-gold); }
.gallery-search-bar-v2 .btn-clear-search-gallery {
    padding: 9px 18px; font-size: 0.85rem; border-radius: 20px;
    background-color: transparent; color: var(--light-grey);
    border: 1px solid var(--darker-gold-1); font-weight:500;
    transition: all 0.2s ease;
}
.gallery-search-bar-v2 .btn-clear-search-gallery:hover { background-color: var(--darker-gold-1); color: var(--white); }

.gallery-filter-pills-v2 {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.gallery-filter-pills-v2 .btn {
    padding: 7px 16px; font-size: 0.85rem; font-weight: 500; border-radius: 18px;
    border-width: 1px; transition: all 0.2s ease;
    display: inline-flex; align-items: center; gap: 5px;
}
.gallery-filter-pills-v2 .btn.active-filter {
    background-color: var(--gold); border-color: var(--gold); color: var(--darker-gold-2);
    box-shadow: 0 1px 5px rgba(var(--gold-rgb), 0.25);
}
.gallery-filter-pills-v2 .btn:not(.active-filter) {
    background-color: transparent; border-color: var(--darker-gold-1); color: var(--lighter-grey);
}
.gallery-filter-pills-v2 .btn:not(.active-filter):hover {
    background-color: var(--darker-gold-1); border-color: var(--gold); color: var(--gold);
}
.gallery-filter-pills-v2 .btn i.fas { font-size: 0.9em; }

.no-photos-message-v2 {
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 40px 20px;
    border: 1px dashed var(--darker-gold-2); border-radius: 8px; margin-top: 20px;
}
.no-photos-message-v2 a { color: var(--turquase); font-weight: bold; text-decoration:none; }
.no-photos-message-v2 a:hover { text-decoration: underline; }

.gallery-grid-v2 {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
    gap: 30px; 
}

.gallery-card-v2 {
    background-color: transparent; 
    border: 1px solid var(--darker-gold-2); 
    border-radius: 10px; 
    overflow: hidden; /* Alt bilgi taşmasın diye */
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.3s ease-out, border-color 0.3s ease-out;
    position: relative; /* Alt bilgi için */
}
.gallery-card-v2:hover {
    box-shadow: 0 8px 25px rgba(var(--gold-rgb, 189, 145, 42), 0.12); 
    border-color: var(--darker-gold-1);
}

.gallery-image-link-v2 { 
    display: block; position: relative; overflow: hidden; 
    border-radius: 10px; /* Tüm köşeler yuvarlak, bilgi bölümü üzerine gelecek */
}
.gallery-image-v2 {
    width: 100%;
    height: 300px; 
    object-fit: cover;
    display: block;
    transition: transform 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.gallery-card-v2:hover .gallery-image-v2 {
    transform: scale(1.05); 
}
/* .gallery-image-link-v2::after kaldırıldı, bilgi bölümü bu işlevi görecek */

.gallery-card-info-v2 { 
    position: absolute; /* Resmin üzerine konumlandır */
    bottom: 0;
    left: 0;
    right: 0;
    padding: 10px 15px; /* İç boşluk ayarlandı */
    background-color: rgba(var(--charcoal-rgb, 34, 34, 34), 0.75); /* Yarı şeffaf arka plan */
    backdrop-filter: blur(5px); /* Arka planı bulanıklaştır */
    -webkit-backdrop-filter: blur(5px);
    border-top: 1px solid rgba(var(--darker-gold-1-rgb, 82, 56, 10), 0.5); /* Üstte ince ayırıcı */
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px; 
    border-radius: 0 0 10px 10px; /* Sadece alt köşeler kartla uyumlu */
    z-index: 1; /* Resmin üzerinde olduğundan emin ol */
    opacity: 0; /* Başlangıçta gizli */
    transform: translateY(10px); /* Aşağıdan gelsin */
    transition: opacity 0.3s ease-out, transform 0.3s ease-out;
}
.gallery-card-v2:hover .gallery-card-info-v2 { /* Hover'da görünür yap */
    opacity: 1;
    transform: translateY(0);
}

.uploader-info-wrapper-v2 { 
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0; 
    flex-grow: 1; 
}
.uploader-info-in-card-trigger-v2 { 
    display: inline-flex; align-items: center; gap: 8px; cursor:default;
    min-width: 0; 
}
.uploader-avatar-v2, .avatar-placeholder-small-v2 { 
    width: 28px; height: 28px; border-radius: 50%; 
    object-fit: cover; border: 1px solid var(--darker-gold-1); flex-shrink: 0; 
}
.avatar-placeholder-small-v2 { background-color: var(--grey); color: var(--gold); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size:0.9em; }
.uploader-username-v2 a { 
    font-size: 0.9rem; font-weight: 500; text-decoration: none; color: var(--lighter-grey); /* Renk güncellendi */
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.uploader-username-v2 a:hover { text-decoration: underline; color:var(--gold); }

.photo-description-preview-v2 {
    font-size: 0.85rem; color: var(--light-grey);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-left: 5px; 
    flex-shrink: 1; 
}

.like-button-gallery-v2 { 
    background-color: transparent;
    border: 1px solid var(--grey); 
    color: var(--light-grey);
    padding: 5px 10px; 
    border-radius: 15px; 
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1; 
    flex-shrink: 0; 
}
.like-button-gallery-v2:hover { border-color: var(--gold); color: var(--gold); }
.like-button-gallery-v2.liked { border-color: var(--red); color: var(--red); }
.like-button-gallery-v2.liked:hover { border-color: var(--dark-red); color: var(--dark-red); }
.like-button-gallery-v2 .like-count { font-weight: 600; min-width: 10px; text-align: left;}
.like-button-gallery-v2 i.fas { font-size: 0.95em; }

/* Kullanıcı adı renk class'ları */
.uploader-info-in-card-trigger-v2.username-role-admin a { color: var(--gold) !important; }
.uploader-info-in-card-trigger-v2.username-role-scg_uye a { color: #A52A2A !important; }
.uploader-info-in-card-trigger-v2.username-role-ilgarion_turanis a { color: var(--turquase) !important; }
.uploader-info-in-card-trigger-v2.username-role-member a { color: var(--white) !important; }
.uploader-info-in-card-trigger-v2.username-role-dis_uye a { color: var(--light-grey) !important; }


@media (max-width: 768px) {
    .gallery-page-container-v2 { padding: 0 15px; margin:20px auto; }
    .gallery-header-v2 { flex-direction:column; align-items:stretch; gap:15px; text-align:center;}
    .btn-outline-turquase { width:100%; justify-content:center;}
    .gallery-controls-v2 { flex-direction:column; gap:15px; padding:15px; border-radius:8px; border: 1px solid var(--darker-gold-2);}
    .gallery-search-bar-v2 { flex-direction:column; width:100%; margin-right:0;}
    .gallery-search-bar-v2 .btn-clear-search-gallery { width:100%;}
    .gallery-filter-pills-v2 { justify-content:center; width:100%;}
    .gallery-grid-v2 { grid-template-columns: 1fr; gap:25px; }
    .gallery-image-v2 { height: 250px; }
    /* Kart hover'da info her zaman görünür olsun mobilde */
    .gallery-card-v2 .gallery-card-info-v2 { 
        opacity: 1;
        transform: translateY(0);
        background-color: rgba(var(--charcoal-rgb, 34, 34, 34), 0.85); /* Mobilde biraz daha opak */
    }
    .gallery-card-info-v2 { flex-direction:column; align-items:flex-start; gap:8px;}
    .photo-description-preview-v2 { margin-left:0; white-space:normal; -webkit-line-clamp: 2; display:-webkit-box; -webkit-box-orient:vertical;}
}
</style>

<main class="main-content">
    <div class="container gallery-page-container-v2">
        <div class="gallery-header-v2">
            <h2><?php echo htmlspecialchars($page_title); ?> (<?php echo count($gallery_photos_for_display); ?> Fotoğraf)</h2>
            <?php if ($can_upload_gallery): ?>
                <a href="<?php echo get_auth_base_url(); ?>/gallery/upload.php" class="btn-outline-turquase"><i class="fas fa-plus-circle"></i> Fotoğraf Yükle</a>
            <?php endif; ?>
        </div>

        <div class="gallery-controls-v2">
            <form method="GET" action="gallery.php" class="gallery-search-bar-v2">
                <div class="form-group">
                    <label for="search">Arama</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Açıklama veya kullanıcı adıyla ara...">
                    <button type="submit" class="btn-search-gallery" aria-label="Ara"><i class="fas fa-search"></i></button>
                </div>
                <?php if (!empty($search_term) || $filter_sort_by !== 'newest'): ?>
                    <a href="gallery.php" class="btn btn-sm btn-clear-search-gallery">Filtreleri Temizle</a>
                <?php endif; ?>
            </form>
            <div class="gallery-filter-pills-v2">
                <a href="gallery.php?sort_by=newest<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>"
                   class="btn btn-sm <?php echo ($filter_sort_by === 'newest' ? 'active-filter' : ''); ?>">
                   <i class="fas fa-sort-amount-down"></i> En Yeni
                </a>
                <a href="gallery.php?sort_by=oldest<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>"
                   class="btn btn-sm <?php echo ($filter_sort_by === 'oldest' ? 'active-filter' : ''); ?>">
                   <i class="fas fa-sort-amount-up"></i> En Eski
                </a>
                <a href="gallery.php?sort_by=most_liked<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>"
                   class="btn btn-sm <?php echo ($filter_sort_by === 'most_liked' ? 'active-filter' : ''); ?>">
                   <i class="fas fa-fire"></i> En Popüler
                </a>
            </div>
        </div>

        <?php // Hata ve başarı mesajları header.php'de gösteriliyor ?>

        <?php if (!$current_user_is_logged_in && $can_view_gallery_page && !empty($gallery_photos_for_display)): ?>
            <div class="info-message" style="text-align: center; margin-bottom: 20px; background-color: var(--transparent-turquase-2); color: var(--turquase); border: 1px solid var(--turquase); padding: 10px; border-radius: 5px;">
                <i class="fas fa-info-circle"></i> Şu anda sadece herkese açık fotoğrafları görüntülüyorsunuz. Daha fazla fotoğrafa erişmek için <a href="<?php echo get_auth_base_url(); ?>/login.php" style="color: var(--light-turquase); font-weight:bold;">giriş yapın</a> ya da <a href="<?php echo get_auth_base_url(); ?>/register.php" style="color: var(--light-turquase); font-weight:bold;">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (empty($gallery_photos_for_display)): ?>
            <p class="no-photos-message-v2">
                <?php if (!empty($search_term)): ?>
                    "<?php echo htmlspecialchars($search_term); ?>" ile eşleşen fotoğraf bulunamadı.
                <?php else: ?>
                    Galeride henüz hiç fotoğraf bulunmamaktadır.
                    <?php if ($can_upload_gallery): ?>
                         İlk fotoğrafı <a href="<?php echo get_auth_base_url(); ?>/gallery/upload.php">sen yükle</a>!
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="gallery-grid-v2">
                <?php foreach ($gallery_photos_for_display as $index => $photo): ?>
                    <?php
                        $uploader_data_for_popover = [
                            'id' => $photo['uploader_id'],
                            'username' => $photo['uploader_username'],
                            'avatar_path' => $photo['uploader_avatar_path'],
                            'ingame_name' => $photo['uploader_ingame_name'],
                            'discord_username' => $photo['uploader_discord_username'],
                            'user_event_count' => $photo['uploader_event_count'],
                            'user_gallery_count' => $photo['uploader_gallery_count'],
                            'user_roles_list' => $photo['uploader_roles_list']
                        ];
                        $description_excerpt = htmlspecialchars(mb_substr(strip_tags($photo['photo_description'] ?? ''), 0, 40, 'UTF-8'));
                        if (mb_strlen(strip_tags($photo['photo_description'] ?? ''), 'UTF-8') > 40) {
                            $description_excerpt .= '...';
                        }

                        $can_delete_this_photo = false;
                        if ($current_user_id) {
                            if (has_permission($pdo, 'gallery.delete_any', $current_user_id)) {
                                $can_delete_this_photo = true;
                            } elseif (has_permission($pdo, 'gallery.delete_own', $current_user_id) && $photo['uploader_id'] == $current_user_id) {
                                $can_delete_this_photo = true;
                            }
                        }
                    ?>
                    <div class="gallery-card-v2" id="photo-card-<?php echo $photo['photo_id']; ?>">
                        <?php if ($can_delete_this_photo): ?>
                            <div class="gallery-card-admin-actions">
                                <form action="/src/actions/handle_gallery.php" method="POST" class="inline-form" onsubmit="return confirm('Bu fotoğrafı KALICI OLARAK galeriden silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="id" value="<?php echo $photo['photo_id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-delete-photo-card" title="Fotoğrafı Sil"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <a href="javascript:void(0);" class="gallery-image-link-v2" onclick="openGalleryModal(<?php echo $index; ?>)">
                            <img src="/public/<?php echo htmlspecialchars($photo['image_path']); ?>"
                                 alt="<?php echo htmlspecialchars($photo['photo_description'] ?: 'Galeri Fotoğrafı ' . $photo['photo_id'] ); ?>"
                                 class="gallery-image-v2">
                        </a>
                        
                        <div class="gallery-card-info-v2">
                            <div class="uploader-info-wrapper-v2">
                                <?php
                                // DÜZELTİLMİŞ ÇAĞRI: $pdo eklendi
                                echo render_user_info_with_popover(
                                    $pdo,
                                    $uploader_data_for_popover,
                                    'uploader-username-v2',
                                    'uploader-avatar-v2',
                                    'uploader-info-in-card-trigger-v2'
                                );
                                ?>
                                <?php if (!empty($description_excerpt)): ?>
                                    <span class="photo-description-preview-v2" title="<?php echo htmlspecialchars($photo['photo_description'] ?? ''); ?>">
                                        - <?php echo $description_excerpt; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($can_like_gallery): ?>
                            <button class="like-button-gallery-v2 <?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'liked' : ''; ?>" 
                                    data-photo-id="<?php echo $photo['photo_id']; ?>"
                                    title="<?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'Beğenmekten Vazgeç' : 'Beğen'; ?>">
                                <i class="fas <?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'fa-heart-crack' : 'fa-heart'; ?>"></i>
                                <span class="like-count"><?php echo $photo['like_count'] ?? 0; ?></span>
                            </button>
                            <?php else: ?>
                                <span class="like-button-gallery-v2" style="cursor:default; border-color:transparent; background-color:transparent !important;"> 
                                    <i class="fas fa-heart" style="color:var(--grey);"></i> 
                                    <span class="like-count"><?php echo $photo['like_count'] ?? 0; ?></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div> 
                <?php endforeach; ?>
            </div> 
        <?php endif; ?>
    </div> 
</main>

<script>
window.phpGalleryPhotos = <?php echo json_encode($gallery_photos_data_for_js); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const likeButtons = document.querySelectorAll('.like-button-gallery-v2');
    likeButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation(); 
            const photoId = this.dataset.photoId;
            const likeIcon = this.querySelector('i.fas');
            const likeCountSpan = this.querySelector('span.like-count');

            fetch('<?php echo get_auth_base_url(); ?>/src/actions/handle_gallery_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'photo_id=' + encodeURIComponent(photoId)
            })
            .then(response => {
                if (!response.ok) { return response.json().then(errData => { throw errData; }).catch(() => { throw new Error('Network response was not ok. Status: ' + response.status); }); }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (likeCountSpan) likeCountSpan.textContent = data.like_count;
                    if (data.action_taken === 'liked') {
                        this.classList.add('liked');
                        this.title = 'Beğenmekten Vazgeç';
                        if(likeIcon) likeIcon.className = 'fas fa-heart-crack';
                    } else if (data.action_taken === 'unliked') {
                        this.classList.remove('liked');
                        this.title = 'Beğen';
                        if(likeIcon) likeIcon.className = 'fas fa-heart';
                    }
                    if (typeof window.updateLikeStatusInModal === 'function') {
                        window.updateLikeStatusInModal(photoId, data.like_count, data.action_taken === 'liked');
                    }
                     if (window.phpGalleryPhotos && Array.isArray(window.phpGalleryPhotos)) {
                        const itemIndexInJsData = window.phpGalleryPhotos.findIndex(item => String(item.photo_id) === String(photoId));
                        if(itemIndexInJsData > -1) {
                            window.phpGalleryPhotos[itemIndexInJsData].like_count = data.like_count;
                            window.phpGalleryPhotos[itemIndexInJsData].user_has_liked = (data.action_taken === 'liked');
                        }
                    }
                } else {
                    alert("Hata: " + (data.error || "Beğeni işlemi sırasında bir sorun oluştu."));
                }
            })
            .catch(error => {
                let errorMessage = "Bir ağ hatası oluştu.";
                if (error && typeof error === 'object' && error.error) { errorMessage = error.error; } 
                else if (error instanceof Error) { errorMessage = error.message; }
                alert("Hata: " + errorMessage);
            });
        });
    });
    
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
    ['darker-gold-2', 'darker-gold-1', 'grey', 'gold', 'charcoal', 'turquase', 'transparent-gold', 'light-turquase', 'transparent-turquase-2', 'red', 'dark-red', 'transparent-red'].forEach(setRgbVar);
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
