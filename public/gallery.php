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

// Sayfa herkese açık - yetki kontrolü yok
$can_view_gallery_page = true;

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

    // Görünürlük koşulları - sadece içerik için
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
                'photo_id' => (int) $photo['photo_id'],
                'image_path_full' => '/public/' . htmlspecialchars($photo['image_path'], ENT_QUOTES, 'UTF-8'),
                'photo_description' => htmlspecialchars($photo['photo_description'] ?: '', ENT_QUOTES, 'UTF-8'),
                'uploader_user_id' => (int) $photo['uploader_id'],
                'uploader_username' => htmlspecialchars($photo['uploader_username'], ENT_QUOTES, 'UTF-8'),
                'uploader_avatar_path_full' => !empty($photo['uploader_avatar_path']) ? '/public/' . htmlspecialchars($photo['uploader_avatar_path'], ENT_QUOTES, 'UTF-8') : '',
                'uploader_ingame_name' => htmlspecialchars($photo['uploader_ingame_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_discord_username' => htmlspecialchars($photo['uploader_discord_username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_event_count' => (int) ($photo['uploader_event_count'] ?? 0),
                'uploader_gallery_count' => (int) ($photo['uploader_gallery_count'] ?? 0),
                'uploader_roles_list' => htmlspecialchars($photo['uploader_roles_list'] ?? '', ENT_QUOTES, 'UTF-8'),
                'uploaded_at_formatted' => date('d F Y, H:i', strtotime($photo['uploaded_at'])),
                'like_count' => (int) ($photo['like_count'] ?? 0),
                'user_has_liked' => isset($photo['user_has_liked']) ? (bool) $photo['user_has_liked'] : false
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
    /* Modern Gallery Styles v2 - Clean & Minimal */
.gallery-page-container {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.gallery-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 3rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.gallery-header h2 {
    color: var(--gold);
    font-size: 2.5rem;
    font-family: var(--font);
    margin: 0;
    font-weight: 300;
    letter-spacing: -0.5px;
}

.gallery-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2.5rem;
    gap: 2rem;
}

.gallery-search-bar {
    display: flex;
    gap: 0.75rem;
    flex: 1;
    max-width: 400px;
}

.gallery-search-bar .form-group { 
    margin: 0; 
    position: relative; 
    flex: 1;
}

.gallery-search-bar label { 
    display: none; 
}

.gallery-search-bar input[type="text"] {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    background-color: transparent;
    border: 1px solid var(--grey);
    border-radius: 6px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    font-family: var(--font);
    transition: border-color 0.2s ease;
}

.gallery-search-bar input[type="text"]::placeholder { 
    color: var(--light-grey); 
    opacity: 0.6; 
}

.gallery-search-bar input[type="text"]:focus {
    outline: none; 
    border-color: var(--gold);
}

.btn-search-gallery {
    position: absolute; 
    right: 6px; 
    top: 50%; 
    transform: translateY(-50%);
    background: transparent; 
    border: none; 
    color: var(--light-grey);
    padding: 0.5rem; 
    font-size: 1rem; 
    cursor: pointer;
    width: 30px; 
    height: 30px; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    transition: color 0.2s ease;
}

.btn-search-gallery:hover { 
    color: var(--gold);
}

.btn-clear-search-gallery {
    padding: 0.75rem 1rem; 
    font-size: 0.85rem; 
    border-radius: 6px;
    background-color: transparent; 
    color: var(--light-grey);
    border: 1px solid var(--grey); 
    font-weight: 400;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
}

.btn-clear-search-gallery:hover { 
    border-color: var(--gold); 
    color: var(--gold);
}

.gallery-filter-pills {
    display: flex; 
    gap: 0.5rem;
}

.gallery-filter-pills .btn {
    padding: 0.75rem 1rem; 
    font-size: 0.85rem; 
    font-weight: 400; 
    border-radius: 6px;
    border: 1px solid var(--grey);
    transition: all 0.2s ease;
    display: inline-flex; 
    align-items: center; 
    gap: 0.5rem;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}

.gallery-filter-pills .btn.active-filter {
    background-color: var(--gold); 
    border-color: var(--gold); 
    color: var(--charcoal);
}

.gallery-filter-pills .btn:not(.active-filter) {
    background-color: transparent; 
    color: var(--light-grey);
}

.gallery-filter-pills .btn:not(.active-filter):hover {
    border-color: var(--gold); 
    color: var(--gold);
}

.gallery-filter-pills .btn i.fas { 
    font-size: 0.8em; 
}

.info-message {
    text-align: center; 
    margin-bottom: 2rem; 
    background-color: rgba(42, 189, 168, 0.1); 
    color: var(--turquase); 
    border: 1px solid rgba(42, 189, 168, 0.3); 
    padding: 1rem; 
    border-radius: 6px;
    font-size: 0.9rem;
}

.info-message a { 
    color: var(--turquase); 
    font-weight: 500; 
    text-decoration: none; 
}

.info-message a:hover { 
    text-decoration: underline; 
}

.no-photos-message {
    text-align: center; 
    font-size: 1rem; 
    color: var(--light-grey); 
    padding: 3rem 2rem;
    border: 1px dashed var(--grey); 
    border-radius: 6px; 
    margin-top: 2rem;
}

.no-photos-message a { 
    color: var(--turquase); 
    font-weight: 500; 
    text-decoration: none; 
}

.no-photos-message a:hover { 
    text-decoration: underline; 
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
    gap: 1.5rem; 
}

.gallery-card {
    background-color: var(--charcoal); 
    border: 1px solid var(--darker-gold-2); 
    border-radius: 8px; 
    overflow: hidden;
    transition: border-color 0.2s ease;
    position: relative;
}

.gallery-card:hover {
    border-color: var(--gold);
}

.gallery-card-admin-actions {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 10;
}

.btn-delete-photo-card {
    background-color: rgba(255, 82, 82, 0.9);
    border: none;
    color: var(--white);
    padding: 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.btn-delete-photo-card:hover {
    background-color: var(--red);
}

.gallery-image-link { 
    display: block; 
    line-height: 0;
}

.gallery-image {
    width: 100%;
    height: 220px; 
    object-fit: cover;
    display: block;
    transition: opacity 0.2s ease;
}

.gallery-card:hover .gallery-image {
    opacity: 0.95;
}

.gallery-card-info { 
    padding: 1rem;
    background-color: var(--charcoal);
    border-top: 1px solid var(--darker-gold-2);
}

.uploader-info-wrapper { 
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.uploader-details {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.uploader-info-in-card-trigger { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
    min-width: 0;
}

.uploader-avatar, .avatar-placeholder-small { 
    width: 24px; 
    height: 24px; 
    border-radius: 50%; 
    object-fit: cover; 
    border: 1px solid var(--darker-gold-1); 
    flex-shrink: 0; 
}

.avatar-placeholder-small { 
    background-color: var(--grey); 
    color: var(--gold); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 500; 
    font-size: 0.7em; 
}

.uploader-username a { 
    font-size: 0.85rem; 
    font-weight: 500; 
    text-decoration: none; 
    color: var(--lighter-grey);
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis;
    transition: color 0.2s ease;
}

.uploader-username a:hover { 
    color: var(--gold); 
}

.photo-description-preview {
    font-size: 0.8rem; 
    color: var(--light-grey);
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis;
    opacity: 0.8;
    margin-top: 0.25rem;
}

.like-button-gallery { 
    background-color: transparent;
    border: 1px solid var(--grey); 
    color: var(--light-grey);
    padding: 0.4rem 0.75rem; 
    border-radius: 4px; 
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
    font-weight: 500;
}

.like-button-gallery:hover { 
    border-color: var(--gold); 
    color: var(--gold);
}

.like-button-gallery.liked { 
    border-color: var(--red); 
    color: var(--red); 
}

.like-button-gallery.liked:hover { 
    opacity: 0.8;
}

.like-button-gallery .like-count { 
    font-weight: 500; 
    min-width: 8px; 
}

.like-button-gallery i.fas { 
    font-size: 0.9em; 
}

/* Username Role Colors */
.uploader-info-in-card-trigger.username-role-admin a { 
    color: var(--gold) !important; 
}

.uploader-info-in-card-trigger.username-role-scg_uye a { 
    color: #A52A2A !important; 
}

.uploader-info-in-card-trigger.username-role-ilgarion_turanis a { 
    color: var(--turquase) !important; 
}

.uploader-info-in-card-trigger.username-role-member a { 
    color: var(--white) !important; 
}

.uploader-info-in-card-trigger.username-role-dis_uye a { 
    color: var(--light-grey) !important; 
}

/* Upload Button */
.btn-outline-turquase {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    border: 1px solid var(--turquase);
    background-color: transparent;
    color: var(--turquase);
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-outline-turquase:hover {
    background-color: var(--turquase);
    color: var(--charcoal);
}

/* Responsive Design */
@media (max-width: 768px) {
    .gallery-page-container { 
        padding: 1.5rem 1rem; 
    }
    
    .gallery-header { 
        flex-direction: column; 
        align-items: stretch; 
        gap: 1rem; 
        text-align: center;
    }
    
    .gallery-header h2 {
        font-size: 2rem;
    }
    
    .btn-outline-turquase { 
        width: 100%; 
        justify-content: center;
    }
    
    .gallery-controls { 
        flex-direction: column; 
        gap: 1rem; 
        align-items: stretch;
    }
    
    .gallery-search-bar { 
        max-width: none;
    }
    
    .gallery-filter-pills { 
        justify-content: center; 
        flex-wrap: wrap;
    }
    
    .gallery-grid { 
        grid-template-columns: 1fr; 
        gap: 1.25rem; 
    }
    
    .gallery-card-info { 
        padding: 0.75rem;
    }
    
    .uploader-info-wrapper {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .photo-description-preview { 
        white-space: normal; 
        -webkit-line-clamp: 2; 
        display: -webkit-box; 
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-top: 0;
    }
}

@media (max-width: 480px) {
    .gallery-page-container {
        padding: 1rem 0.75rem;
    }
    
    .gallery-header h2 {
        font-size: 1.75rem;
    }
    
    .gallery-search-bar {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-clear-search-gallery {
        width: 100%;
        text-align: center;
    }
    
    .gallery-filter-pills .btn {
        font-size: 0.8rem;
        padding: 0.6rem 0.8rem;
    }
}
</style>

<main class="main-content">
    <div class="container gallery-page-container">
        <div class="gallery-header">
            <h2><?php echo htmlspecialchars($page_title); ?> (<?php echo count($gallery_photos_for_display); ?>
                Fotoğraf)</h2>
            <?php if ($can_upload_gallery): ?>
                <a href="<?php echo get_auth_base_url(); ?>/gallery/upload.php" class="btn-outline-turquase">
                    <i class="fas fa-plus-circle"></i> Fotoğraf Yükle
                </a>
            <?php endif; ?>
        </div>

        <div class="gallery-controls">
            <form method="GET" action="gallery.php" class="gallery-search-bar">
                <div class="form-group">
                    <label for="search">Arama</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_term); ?>"
                        placeholder="Açıklama veya kullanıcı adıyla ara...">
                    <button type="submit" class="btn-search-gallery" aria-label="Ara">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if (!empty($search_term) || $filter_sort_by !== 'newest'): ?>
                    <a href="gallery.php" class="btn-clear-search-gallery">
                        <i class="fas fa-times"></i> Filtreleri Temizle
                    </a>
                <?php endif; ?>
            </form>

            <div class="gallery-filter-pills">
                <a href="gallery.php?sort_by=newest<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="btn <?php echo ($filter_sort_by === 'newest' ? 'active-filter' : ''); ?>">
                    <i class="fas fa-sort-amount-down"></i> En Yeni
                </a>
                <a href="gallery.php?sort_by=oldest<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="btn <?php echo ($filter_sort_by === 'oldest' ? 'active-filter' : ''); ?>">
                    <i class="fas fa-sort-amount-up"></i> En Eski
                </a>
                <a href="gallery.php?sort_by=most_liked<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                    class="btn <?php echo ($filter_sort_by === 'most_liked' ? 'active-filter' : ''); ?>">
                    <i class="fas fa-fire"></i> En Popüler
                </a>
            </div>
        </div>

        <?php // Hata ve başarı mesajları header.php'de gösteriliyor ?>

        <?php if (!$current_user_is_logged_in && $can_view_gallery_page && !empty($gallery_photos_for_display)): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i>
                Şu anda sadece herkese açık fotoğrafları görüntülüyorsunuz. Daha fazla fotoğrafa erişmek için
                <a href="<?php echo get_auth_base_url(); ?>/login.php">giriş yapın</a> ya da
                <a href="<?php echo get_auth_base_url(); ?>/register.php">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (empty($gallery_photos_for_display)): ?>
            <p class="no-photos-message">
                <?php if (!empty($search_term)): ?>
                    <i class="fas fa-search"></i><br>
                    "<?php echo htmlspecialchars($search_term); ?>" ile eşleşen fotoğraf bulunamadı.
                <?php else: ?>
                    <i class="fas fa-images"></i><br>
                    Galeride henüz hiç fotoğraf bulunmamaktadır.
                    <?php if ($can_upload_gallery): ?>
                        <br>İlk fotoğrafı <a href="<?php echo get_auth_base_url(); ?>/gallery/upload.php">sen yükle</a>!
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="gallery-grid">
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
                    $description_excerpt = htmlspecialchars(mb_substr(strip_tags($photo['photo_description'] ?? ''), 0, 50, 'UTF-8'));
                    if (mb_strlen(strip_tags($photo['photo_description'] ?? ''), 'UTF-8') > 50) {
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
                    <div class="gallery-card" id="photo-card-<?php echo $photo['photo_id']; ?>">
                        <?php if ($can_delete_this_photo): ?>
                            <div class="gallery-card-admin-actions">
                                <form action="/src/actions/handle_gallery.php" method="POST" class="inline-form"
                                    onsubmit="return confirm('Bu fotoğrafı KALICI OLARAK galeriden silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="id" value="<?php echo $photo['photo_id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-delete-photo-card" title="Fotoğrafı Sil">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <a href="javascript:void(0);" class="gallery-image-link"
                            onclick="openGalleryModal(<?php echo $index; ?>)">
                            <img src="/public/<?php echo htmlspecialchars($photo['image_path']); ?>"
                                alt="<?php echo htmlspecialchars($photo['photo_description'] ?: 'Galeri Fotoğrafı ' . $photo['photo_id']); ?>"
                                class="gallery-image">
                        </a>

                        <div class="gallery-card-info">
                            <div class="uploader-info-wrapper">
                                <?php
                                // render_user_info_with_popover fonksiyon çağrısı
                                echo render_user_info_with_popover(
                                    $pdo,
                                    $uploader_data_for_popover,
                                    'uploader-username',
                                    'uploader-avatar',
                                    'uploader-info-in-card-trigger'
                                );
                                ?>
                                <?php if (!empty($description_excerpt)): ?>
                                    <span class="photo-description-preview"
                                        title="<?php echo htmlspecialchars($photo['photo_description'] ?? ''); ?>">
                                        - <?php echo $description_excerpt; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($can_like_gallery): ?>
                                <button
                                    class="like-button-gallery <?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'liked' : ''; ?>"
                                    data-photo-id="<?php echo $photo['photo_id']; ?>"
                                    title="<?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'Beğenmekten Vazgeç' : 'Beğen'; ?>">
                                    <i
                                        class="fas <?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'fa-heart-crack' : 'fa-heart'; ?>"></i>
                                    <span class="like-count"><?php echo $photo['like_count'] ?? 0; ?></span>
                                </button>
                            <?php else: ?>
                                <span class="like-button-gallery"
                                    style="cursor:default; border-color:transparent; background-color:transparent !important;">
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

    document.addEventListener('DOMContentLoaded', function () {
        // Like button functionality
        const likeButtons = document.querySelectorAll('.like-button-gallery');
        likeButtons.forEach(button => {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                const photoId = this.dataset.photoId;
                const likeIcon = this.querySelector('i.fas');
                const likeCountSpan = this.querySelector('span.like-count');

                // Add loading state
                const originalContent = this.innerHTML;
                this.style.opacity = '0.6';
                this.style.pointerEvents = 'none';

                fetch('<?php echo get_auth_base_url(); ?>/src/actions/handle_gallery_like.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'photo_id=' + encodeURIComponent(photoId)
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errData => {
                                throw errData;
                            }).catch(() => {
                                throw new Error('Network response was not ok. Status: ' + response.status);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (likeCountSpan) likeCountSpan.textContent = data.like_count;
                            if (data.action_taken === 'liked') {
                                this.classList.add('liked');
                                this.title = 'Beğenmekten Vazgeç';
                                if (likeIcon) likeIcon.className = 'fas fa-heart-crack';
                            } else if (data.action_taken === 'unliked') {
                                this.classList.remove('liked');
                                this.title = 'Beğen';
                                if (likeIcon) likeIcon.className = 'fas fa-heart';
                            }

                            // Update modal if exists
                            if (typeof window.updateLikeStatusInModal === 'function') {
                                window.updateLikeStatusInModal(photoId, data.like_count, data.action_taken === 'liked');
                            }

                            // Update JS data
                            if (window.phpGalleryPhotos && Array.isArray(window.phpGalleryPhotos)) {
                                const itemIndexInJsData = window.phpGalleryPhotos.findIndex(item => String(item.photo_id) === String(photoId));
                                if (itemIndexInJsData > -1) {
                                    window.phpGalleryPhotos[itemIndexInJsData].like_count = data.like_count;
                                    window.phpGalleryPhotos[itemIndexInJsData].user_has_liked = (data.action_taken === 'liked');
                                }
                            }
                        } else {
                            throw new Error(data.error || "Beğeni işlemi sırasında bir sorun oluştu.");
                        }
                    })
                    .catch(error => {
                        let errorMessage = "Bir ağ hatası oluştu.";
                        if (error && typeof error === 'object' && error.error) {
                            errorMessage = error.error;
                        } else if (error instanceof Error) {
                            errorMessage = error.message;
                        }

                        // Show error with better UX
                        const errorEl = document.createElement('div');
                        errorEl.style.cssText = `
                    position: fixed; top: 20px; right: 20px; z-index: 9999;
                    background: var(--red); color: white; padding: 1rem 1.5rem;
                    border-radius: 8px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                `;
                        errorEl.textContent = errorMessage;
                        document.body.appendChild(errorEl);

                        setTimeout(() => errorEl.remove(), 4000);
                    })
                    .finally(() => {
                        // Remove loading state
                        this.style.opacity = '';
                        this.style.pointerEvents = '';
                    });
            });
        });

        // Enhanced search functionality
        const searchInput = document.getElementById('search');
        const searchForm = searchInput?.closest('form');

        if (searchInput && searchForm) {
            let searchTimeout;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length > 2 || this.value.length === 0) {
                        // Auto-submit for better UX (optional)
                        // searchForm.submit();
                    }
                }, 500);
            });

            // Enhanced enter key handling
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchForm.submit();
                }
            });
        }

        // Smooth scroll to top when filters change
        const filterButtons = document.querySelectorAll('.gallery-filter-pills .btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function (e) {
                // Add loading indicator
                this.style.opacity = '0.7';
                setTimeout(() => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }, 100);
            });
        });

        // Intersection Observer for card animations
        if ('IntersectionObserver' in window) {
            const cardObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            document.querySelectorAll('.gallery-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                cardObserver.observe(card);
            });
        }
    });
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>