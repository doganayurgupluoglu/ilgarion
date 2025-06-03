
<?php

// public/gallery.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/formatting_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

// Kullanıcı durum değişkenleri
$current_user_is_logged_in = is_user_logged_in();
$current_user_id = $current_user_is_logged_in ? ($_SESSION['user_id'] ?? null) : null;
$current_user_is_admin = $current_user_is_logged_in ? is_user_admin() : false;
$current_user_is_approved = $current_user_is_logged_in ? is_user_approved() : false;
$current_user_roles_names = $_SESSION['user_roles'] ?? [];

$page_title = "Galeri";

// Yetki kontrolleri
$can_upload_photo = $current_user_id && $current_user_is_approved && has_permission($pdo, 'gallery.upload', $current_user_id);
$can_like_photos = $current_user_id && $current_user_is_approved && has_permission($pdo, 'gallery.like', $current_user_id);
$can_delete_any = $current_user_id && has_permission($pdo, 'gallery.delete_any', $current_user_id);

$photos = [];
$total_photos = 0;
$current_page = 1;
$photos_per_page = 24;

// Sayfalama parametresi
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $current_page = (int)$_GET['page'];
}

$offset = ($current_page - 1) * $photos_per_page;

try {
    $sql_select_fields = "SELECT
                            gp.id, gp.image_path, gp.description, gp.uploaded_at, gp.user_id,
                            gp.is_public_no_auth, gp.is_members_only,
                            u.username AS uploader_username,
                            u.avatar_path AS uploader_avatar_path,
                            u.ingame_name AS uploader_ingame_name,
                            u.discord_username AS uploader_discord_username,
                            (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS uploader_photo_count,
                            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS uploader_event_count,
                            (SELECT GROUP_CONCAT(r.name SEPARATOR ',')
                             FROM user_roles ur_uploader
                             JOIN roles r ON ur_uploader.role_id = r.id
                             WHERE ur_uploader.user_id = u.id) AS uploader_roles_list,
                            COALESCE(like_count.total_likes, 0) AS like_count,
                            CASE WHEN user_like.user_id IS NOT NULL THEN 1 ELSE 0 END AS user_has_liked";

    $sql_from_join = " FROM gallery_photos gp 
                       JOIN users u ON gp.user_id = u.id
                       LEFT JOIN (
                           SELECT photo_id, COUNT(*) as total_likes
                           FROM gallery_photo_likes
                           GROUP BY photo_id
                       ) like_count ON gp.id = like_count.photo_id";

    if ($current_user_id) {
        $sql_from_join .= " LEFT JOIN gallery_photo_likes user_like ON gp.id = user_like.photo_id AND user_like.user_id = :current_user_id";
    }

    $sql_params = [];
    if ($current_user_id) {
        $sql_params[':current_user_id'] = $current_user_id;
    }

    $visibility_conditions_array = [];

    // Görünürlük koşulları
    if ($current_user_is_admin || ($current_user_id && has_permission($pdo, 'gallery.manage_all', $current_user_id))) {
        $visibility_conditions_array[] = "1=1"; // Admin her şeyi görür
    } else {
        $specific_visibility_or_conditions = [];
        
        // Herkese açık fotoğraflar
        if (has_permission($pdo, 'gallery.view_public', $current_user_id) || !$current_user_id) {
            $specific_visibility_or_conditions[] = "gp.is_public_no_auth = 1";
        }
        
        // Sadece üyelere açık fotoğraflar
        if ($current_user_id && $current_user_is_approved && has_permission($pdo, 'gallery.view_approved', $current_user_id)) {
            $specific_visibility_or_conditions[] = "(gp.is_members_only = 1 AND gp.is_public_no_auth = 0)";
        }

        // Faction-only fotoğraflar (gelecekte role-based visibility için hazır)
        if ($current_user_id && $current_user_is_approved) {
            $stmt_user_roles_ids = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :current_user_id_for_faction");
            $stmt_user_roles_ids->execute([':current_user_id_for_faction' => $current_user_id]);
            $user_role_ids_for_faction = $stmt_user_roles_ids->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($user_role_ids_for_faction)) {
                $role_placeholders_faction = [];
                foreach ($user_role_ids_for_faction as $idx => $role_id_faction) {
                    $placeholder_faction = ':faction_role_id_' . $idx;
                    $role_placeholders_faction[] = $placeholder_faction;
                    $sql_params[$placeholder_faction] = $role_id_faction;
                }
                $in_clause_faction = implode(',', $role_placeholders_faction);
                if (!empty($in_clause_faction)) {
                    $specific_visibility_or_conditions[] = "(EXISTS (
                        SELECT 1 FROM gallery_photo_visibility_roles gpvr_check
                        WHERE gpvr_check.photo_id = gp.id AND gpvr_check.role_id IN (" . $in_clause_faction . ")
                    ))";
                }
            }
        }

        if (!empty($specific_visibility_or_conditions)) {
            $visibility_conditions_array[] = "(" . implode(" OR ", $specific_visibility_or_conditions) . ")";
        } else {
            $visibility_conditions_array[] = "1=0"; // Hiçbir şey göremez
        }
    }

    $final_visibility_condition = implode(" AND ", $visibility_conditions_array);

    // Toplam sayı için sorgu
    $sql_count = "SELECT COUNT(DISTINCT gp.id)" . $sql_from_join . " WHERE ($final_visibility_condition)";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($sql_params);
    $total_photos = $stmt_count->fetchColumn();

    // Ana fotoğraf sorgusu
    $sql_photos = $sql_select_fields . $sql_from_join .
                  " WHERE ($final_visibility_condition)" .
                  " ORDER BY gp.uploaded_at DESC" .
                  " LIMIT $photos_per_page OFFSET $offset";
    
    $stmt_photos = $pdo->prepare($sql_photos);
    $stmt_photos->execute($sql_params);
    $photos = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Galeri fotoğrafları listeleme hatası (gallery.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Galeri yüklenirken bir veritabanı sorunu oluştu.";
}

// Sayfa sayısını hesapla
$total_pages = $total_photos > 0 ? ceil($total_photos / $photos_per_page) : 1;
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Gallery Page Styles */
.gallery-page-container {
    width: 100%;
    max-width: 1400px !important;
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

.gallery-stats {
    font-size: 0.9rem;
    color: var(--light-grey);
    margin-top: 0.5rem;
}

.btn-upload-photo {
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

.btn-upload-photo:hover {
    background-color: var(--turquase);
    color: var(--charcoal);
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

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.photo-card {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    max-height: 250px;
}

.photo-card:hover {
    border-color: var(--gold);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}
.photo-card:hover .photo-card-info {
    opacity: 1;
}

.photo-card-image-wrapper {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
}

.photo-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.photo-card:hover .photo-card-image {
    transform: scale(1.05);
}



.photo-card:hover .photo-card-overlay {
    opacity: 1;
}

.photo-overlay-content {
    color: white;
    width: 100%;
}

.photo-overlay-title {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.photo-overlay-date {
    font-size: 0.75rem;
    opacity: 0.9;
}

.photo-card-info {
    position: absolute;
    display: flex;
    align-items: flex-end;
    bottom: 0;
    left: 0;
    min-width: 100%;
    height: 100%;
    z-index: 2000;
    padding: 1rem;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;

}

.photo-card-description {
    font-size: 0.85rem;
    color: white;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: auto !important;
}

.photo-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    width: 100%;
}

.photo-uploader-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 80;
}

.uploader-avatar-small {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-1);
    flex-shrink: 0;
}

.uploader-name-link a {
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    color: var(--lighter-grey);
    transition: color 0.2s ease;
}

.uploader-name-link a:hover {
    color: var(--gold);
}

.photo-actions {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 0.75rem;
}

.like-button {
    background: none;
    border: none;
    color: var(--light-grey);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.like-button:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--turquase);
}

.like-button.liked {
    color: var(--turquase);
}

.like-button.liked i {
    color: var(--turquase);
}

.delete-button {
    background: none;
    border: none;
    color: var(--red);
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.8rem;
}

.delete-button:hover {
    background-color: rgba(255, 0, 0, 0.1);
    color: #ff4444;
}

/* Username Role Colors */
.uploader-name-link.username-role-admin a { color: var(--gold) !important; }
.uploader-name-link.username-role-scg_uye a { color: #A52A2A !important; }
.uploader-name-link.username-role-ilgarion_turanis a { color: var(--turquase) !important; }
.uploader-name-link.username-role-member a { color: var(--white) !important; }
.uploader-name-link.username-role-dis_uye a { color: var(--light-grey) !important; }

/* Pagination */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin: 3rem 0;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.pagination a,
.pagination span {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--darker-gold-2);
    background-color: var(--charcoal);
    color: var(--lighter-grey);
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.pagination a:hover {
    border-color: var(--gold);
    background-color: var(--darker-gold-2);
    color: var(--gold);
}

.pagination .current {
    background-color: var(--gold);
    color: var(--charcoal);
    border-color: var(--gold);
    font-weight: 500;
}

.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Upload Modal */
.upload-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
}

.upload-modal-content {
    background-color: var(--charcoal);
    margin: 5% auto;
    padding: 2rem;
    border: 1px solid var(--darker-gold-1);
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.upload-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.upload-modal-title {
    font-size: 1.5rem;
    color: var(--gold);
    margin: 0;
    font-family: var(--font);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--light-grey);
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s ease;
}

.close-modal:hover {
    color: var(--gold);
}

.upload-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-size: 0.9rem;
    color: var(--lighter-grey);
    font-weight: 500;
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 0.75rem;
    border: 1px solid var(--darker-gold-2);
    background-color: var(--grey);
    color: var(--lighter-grey);
    border-radius: 6px;
    font-family: var(--font);
    transition: border-color 0.2s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--gold);
}

.file-upload-area {
    border: 2px dashed var(--darker-gold-2);
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.2s ease;
    cursor: pointer;
}

.file-upload-area:hover,
.file-upload-area.dragover {
    border-color: var(--turquase);
    background-color: rgba(42, 189, 168, 0.05);
}

.file-upload-icon {
    font-size: 2rem;
    color: var(--turquase);
    margin-bottom: 0.5rem;
}

.file-upload-text {
    color: var(--light-grey);
    font-size: 0.9rem;
}

.file-upload-input {
    display: none;
}

.upload-preview {
    display: none;
    margin-top: 1rem;
}

.preview-image {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid var(--darker-gold-2);
}

.visibility-options {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.visibility-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid var(--darker-gold-2);
    border-radius: 6px;
    transition: all 0.2s ease;
}

.visibility-option:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.visibility-option input[type="radio"] {
    margin: 0;
}

.visibility-option-content {
    flex: 1;
}

.visibility-option-title {
    font-weight: 500;
    color: var(--lighter-grey);
    font-size: 0.9rem;
}

.visibility-option-desc {
    font-size: 0.8rem;
    color: var(--light-grey);
    margin-top: 0.25rem;
}

.btn-upload-submit {
    padding: 0.75rem 1.5rem;
    background-color: var(--turquase);
    color: var(--charcoal);
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 1rem;
}

.btn-upload-submit:hover {
    background-color: var(--light-gold);
    transform: translateY(-1px);
}

.btn-upload-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Photo Modal with Comments */
.photo-modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(5px);
}

.photo-modal-content {
    position: relative;
    max-width: 95vw;
    max-height: 95vh;
    margin: 2.5vh auto;
    display: flex;
    background-color: var(--charcoal);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
}

.photo-modal-image-section {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #000;
    position: relative;
    min-width: 60%;
}

.photo-modal-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.photo-modal-sidebar {
    width: 400px;
    background-color: var(--charcoal);
    display: flex;
    flex-direction: column;
    border-left: 1px solid var(--darker-gold-2);
}

.photo-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.photo-modal-title {
    font-size: 1.2rem;
    font-weight: 500;
    color: var(--gold);
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

.photo-modal-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.modal-uploader-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-1);
}

.modal-uploader-info {
    flex: 1;
}

.modal-uploader-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--lighter-grey);
    margin-bottom: 0.25rem;
}

.modal-upload-date {
    font-size: 0.8rem;
    color: var(--light-grey);
}

.photo-modal-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modal-like-button {
    background: none;
    border: none;
    color: var(--light-grey);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-like-button:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--turquase);
}

.modal-like-button.liked {
    color: var(--turquase);
}

.modal-delete-button {
    background: none;
    border: none;
    color: var(--red);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-delete-button:hover {
    background-color: rgba(255, 0, 0, 0.1);
}

.photo-modal-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    z-index: 1002;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.photo-modal-close:hover {
    background: rgba(0, 0, 0, 0.8);
    color: var(--gold);
}

.comments-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;

}

.comments-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
    background-color: rgba(0, 0, 0, 0.1);
}

.comments-title {
    font-size: 1rem;
    font-weight: 500;
    color: var(--gold);
    margin: 0;
}

.comments-list {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.comment-item {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.comment-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.comment-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-1);
    flex-shrink: 0;
}

.comment-content {
    flex: 1;
    min-width: 0;
}

.comment-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.comment-author {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--lighter-grey);
}

.comment-date {
    font-size: 0.75rem;
    color: var(--light-grey);
}

.comment-text {
    font-size: 0.85rem;
    color: var(--lighter-grey);
    line-height: 1.4;
    margin-bottom: 0.5rem;
    word-wrap: break-word;
}

.comment-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.comment-like-btn,
.comment-reply-btn,
.comment-edit-btn,
.comment-delete-btn {
    background: none;
    border: none;
    color: var(--light-grey);
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.comment-like-btn:hover,
.comment-reply-btn:hover {
    color: var(--turquase);
    background-color: rgba(42, 189, 168, 0.1);
}

.comment-edit-btn:hover {
    color: var(--gold);
    background-color: rgba(255, 215, 0, 0.1);
}

.comment-delete-btn:hover {
    color: var(--red);
    background-color: rgba(255, 0, 0, 0.1);
}

.comment-like-btn.liked {
    color: var(--turquase);
}

.reply-item {
    margin-left: 2rem;
    margin-top: 0.75rem;
    padding-left: 1rem;
    border-left: 2px solid var(--darker-gold-2);
}

.comment-form {
    display: flex;
    align-items: center;
    justify-content: center;
    justify-self: end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--darker-gold-2);
    background-color: rgba(0, 0, 0, 0.1);
}

.comment-input-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    display: flex;
    gap: 0.75rem;
    width: 100%;
}

.comment-user-avatar {
    width: 60px;
    height: 60px;
    display: none;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--darker-gold-1);
    flex-shrink: 0;
}

.comment-input {
    flex: 1;
    background-color: var(--grey);
    border: 1px solid var(--gold);
    border-radius: 6px;
    padding: 0.75rem;
    color: var(--lighter-grey);
    font-family: var(--font);
    font-size: 0.85rem;
    resize: vertical;
    width: 100%;
    min-height: 60px;
    max-height: 100%;
}

.comment-input:focus {
    outline: none;
    border-color: var(--gold);
}

.comment-submit-btn {
    outline: none;
    background-color: var(--gold);
    height: 59px;
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
    position: absolute;
    top: 0;
    right: 0px;
    color: var(--charcoal);
    border: none;
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.comment-submit-btn:hover {
    background-color: var(--light-gold);
    transform: translateY(-0px) !important;
}

.comment-submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.no-comments {
    text-align: center;
    color: var(--light-grey);
    font-size: 0.9rem;
    padding: 2rem;
}

.comment-editing {
    background-color: rgba(255, 215, 0, 0.05);
    border: 1px solid rgba(255, 215, 0, 0.2);
    border-radius: 6px;
    padding: 0.5rem;
}

.comment-edit-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.comment-edit-input {
    background-color: var(--grey);
    border: 1px solid var(--gold);
    border-radius: 4px;
    padding: 0.5rem;
    color: var(--lighter-grey);
    font-family: var(--font);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 50px;
}

.comment-edit-actions {
    display: flex;
    gap: 0.5rem;
}

.comment-save-btn {
    background-color: var(--turquase);
    color: var(--charcoal);
    border: none;
    border-radius: 4px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    cursor: pointer;
}

.comment-cancel-btn {
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: none;
    border-radius: 4px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    cursor: pointer;
}

/* Username Role Colors for Comments */
.comment-author.username-role-admin { color: var(--gold) !important; }
.comment-author.username-role-scg_uye { color: #A52A2A !important; }
.comment-author.username-role-ilgarion_turanis { color: var(--turquase) !important; }
.comment-author.username-role-member { color: var(--white) !important; }
.comment-author.username-role-dis_uye { color: var(--light-grey) !important; }

.no-photos-message {
    text-align: center;
    font-size: 1.1rem;
    color: var(--light-grey);
    padding: 4rem 2rem;
    border: 1px dashed var(--grey);
    border-radius: 8px;
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

/* Loading States */
.loading {
    opacity: 0.5;
    pointer-events: none;
}

.upload-progress {
    display: none;
    margin-top: 1rem;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background-color: var(--darker-gold-2);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background-color: var(--turquase);
    width: 0%;
    transition: width 0.3s ease;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .photo-modal-content {
        flex-direction: column;
        max-width: 95vw;
        max-height: 95vh;
    }
    
    .photo-modal-image-section {
        min-width: auto;
        max-height: 60vh;
    }
    
    .photo-modal-sidebar {
        width: 100%;
        max-height: 35vh;
    }
    
    .comments-list {
        max-height: 200px;
    }
}

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
    
    .btn-upload-photo {
        width: 100%;
        justify-content: center;
    }
    
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.25rem;
    }
    
    .upload-modal-content {
        width: 95%;
        margin: 10% auto;
        padding: 1.5rem;
    }
    
    .photo-modal-sidebar {
        max-height: 40vh;
    }
    
    .comment-input-wrapper {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .comment-user-avatar {
        align-self: flex-start;
    }
}

@media (max-width: 480px) {
    .gallery-page-container {
        padding: 1rem 0.75rem;
    }
    
    .gallery-header h2 {
        font-size: 1.75rem;
    }
    
    .gallery-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .upload-modal-content {
        margin: 5% auto;
        padding: 1rem;
    }
    
    .photo-modal-close {
        top: 10px;
        right: 15px;
        width: 40px;
        height: 40px;
        font-size: 1.5rem;
    }
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
}

/* Animation keyframes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.photo-card {
    animation: fadeInUp 0.5s ease forwards;
}
</style>

<main class="main-content">
    <div class="container gallery-page-container">
        <div class="gallery-header">
            <div>
                <h2><?php echo htmlspecialchars($page_title); ?></h2>
                <div class="gallery-stats">
                    Toplam <?php echo number_format($total_photos); ?> fotoğraf
                    <?php if ($total_pages > 1): ?>
                        • Sayfa <?php echo $current_page; ?> / <?php echo $total_pages; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($can_upload_photo): ?>
                <button type="button" class="btn-upload-photo" onclick="openUploadModal()">
                    <i class="fas fa-cloud-upload-alt"></i> Fotoğraf Yükle
                </button>
            <?php endif; ?>
        </div>

        <?php if (!$current_user_is_logged_in && !empty($photos)): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> 
                Şu anda sadece herkese açık fotoğrafları görüntülüyorsunuz. Daha fazla içeriğe erişmek için 
                <a href="<?php echo get_auth_base_url(); ?>/login.php">giriş yapın</a> ya da 
                <a href="<?php echo get_auth_base_url(); ?>/register.php">kayıt olun</a>.
            </div>
        <?php endif; ?>

        <?php if (empty($photos)): ?>
            <div class="no-photos-message">
                <i class="fas fa-images" style="font-size: 3rem; color: var(--gold); margin-bottom: 1rem;"></i><br>
                <strong>Henüz fotoğraf bulunmamaktadır.</strong><br>
                <?php if ($can_upload_photo): ?>
                    İlk fotoğrafı <a href="#" onclick="openUploadModal(); return false;">sen yükle</a>!
                <?php else: ?>
                    Yakında harika fotoğraflar burada olacak.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($photos as $photo): ?>
                    <div class="photo-card" onclick="openPhotoModal(<?php echo $photo['id']; ?>)">
                        <div class="photo-card-image-wrapper">
                            <img src="/public/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($photo['description'] ?: 'Galeri Fotoğrafı'); ?>" 
                                 class="photo-card-image"
                                 loading="lazy">
                          
                        </div>
                        <div class="photo-card-info">
                            
                            <div class="photo-card-footer">
                                <?php
                                $uploader_data_for_popover = [
                                    'id' => $photo['user_id'],
                                    'username' => $photo['uploader_username'],
                                    'avatar_path' => $photo['uploader_avatar_path'],
                                    'ingame_name' => $photo['uploader_ingame_name'],
                                    'discord_username' => $photo['uploader_discord_username'],
                                    'user_event_count' => $photo['uploader_event_count'],
                                    'user_gallery_count' => $photo['uploader_photo_count'],
                                    'user_roles_list' => $photo['uploader_roles_list']
                                ];
                                echo render_user_info_with_popover(
                                    $pdo,
                                    $uploader_data_for_popover,
                                    'uploader-name-link',
                                    'uploader-avatar-small',
                                    'photo-uploader-info'
                                );
                                ?>
                                
                                <div class="photo-actions" onclick="event.stopPropagation();">
                                    <?php if (!empty($photo['description'])): ?>
                                <p class="photo-card-description">
                                    <?php echo htmlspecialchars($photo['description']); ?>
                                </p>
                            <?php endif; ?>
                                    <?php if ($can_like_photos): ?>
                                        <button type="button" 
                                                class="like-button <?php echo $photo['user_has_liked'] ? 'liked' : ''; ?>" 
                                                onclick="toggleLike(<?php echo $photo['id']; ?>, this)">
                                            <i class="<?php echo $photo['user_has_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                            <span class="like-count"><?php echo $photo['like_count']; ?></span>
                                        </button>
                                    <?php else: ?>
                                        <span class="like-count-display">
                                            <i class="fas fa-heart" style="color: var(--light-grey);"></i>
                                            <?php echo $photo['like_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete_any || ($current_user_id == $photo['user_id'] && has_permission($pdo, 'gallery.delete_own', $current_user_id))): ?>
                                        <button type="button" 
                                                class="delete-button" 
                                                onclick="deletePhoto(<?php echo $photo['id']; ?>)"
                                                title="Fotoğrafı Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=1">&laquo; İlk</a>
                            <a href="?page=<?php echo $current_page - 1; ?>">&lsaquo; Önceki</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; İlk</span>
                            <span class="disabled">&lsaquo; Önceki</span>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $current_page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>">Sonraki &rsaquo;</a>
                            <a href="?page=<?php echo $total_pages; ?>">Son &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Sonraki &rsaquo;</span>
                            <span class="disabled">Son &raquo;</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Upload Modal -->
<?php if ($can_upload_photo): ?>
<div id="uploadModal" class="upload-modal">
    <div class="upload-modal-content">
        <div class="upload-modal-header">
            <h3 class="upload-modal-title">Fotoğraf Yükle</h3>
            <button type="button" class="close-modal" onclick="closeUploadModal()">&times;</button>
        </div>
        <form id="uploadForm" class="upload-form" enctype="multipart/form-data">
            <div class="form-group">
                <label>Fotoğraf Seç</label>
                <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        Fotoğraf yüklemek için tıklayın veya sürükleyip bırakın<br>
                        <small>JPG, PNG, GIF (Max: 10MB)</small>
                    </div>
                </div>
                <input type="file" 
                       id="fileInput" 
                       name="photo" 
                       class="file-upload-input" 
                       accept="image/*" 
                       required>
                <div id="uploadPreview" class="upload-preview">
                    <img id="previewImage" class="preview-image" alt="Önizleme">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Açıklama (Opsiyonel)</label>
                <textarea id="description" 
                          name="description" 
                          rows="3" 
                          placeholder="Fotoğraf hakkında kısa bir açıklama yazın..."></textarea>
            </div>

            <div class="form-group">
                <label>Görünürlük Ayarları</label>
                <div class="visibility-options">
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="public" checked>
                        <div class="visibility-option-content">
                            <div class="visibility-option-title">
                                <i class="fas fa-globe"></i> Herkese Açık
                            </div>
                            <div class="visibility-option-desc">
                                Tüm ziyaretçiler bu fotoğrafı görebilir
                            </div>
                        </div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="members">
                        <div class="visibility-option-content">
                            <div class="visibility-option-title">
                                <i class="fas fa-users"></i> Sadece Üyeler
                            </div>
                            <div class="visibility-option-desc">
                                Sadece kayıtlı ve onaylı üyeler görebilir
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="upload-progress">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">Yükleniyor...</div>
            </div>

            <button type="submit" class="btn-upload-submit">
                <i class="fas fa-upload"></i> Fotoğrafı Yükle
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Photo Modal with Comments -->
<div id="photoModal" class="photo-modal">
    <span class="photo-modal-close" onclick="closePhotoModal()">&times;</span>
    <div class="photo-modal-content">
        <!-- Sol taraf - Fotoğraf -->
        <div class="photo-modal-image-section">
            <img id="modalImage" class="photo-modal-image" alt="Fotoğraf">
        </div>
        
        <!-- Sağ taraf - Bilgiler ve Yorumlar -->
        <div class="photo-modal-sidebar">
            <!-- Fotoğraf Bilgileri -->
            <div class="photo-modal-header">
                <div class="photo-modal-title" id="modalTitle"></div>
                <div class="photo-modal-meta">
                    <img id="modalUploaderAvatar" class="modal-uploader-avatar" alt="Avatar">
                    <div class="modal-uploader-info">
                        <div class="modal-uploader-name" id="modalUploaderName"></div>
                        <div class="modal-upload-date" id="modalDate"></div>
                    </div>
                </div>
                <div class="photo-modal-actions">
                    <?php if ($can_like_photos): ?>
                    <button id="modalLikeButton" class="modal-like-button" onclick="togglePhotoLikeInModal()">
                        <i id="modalLikeIcon" class="far fa-heart"></i>
                        <span id="modalLikeCount">0</span>
                    </button>
                    <?php endif; ?>
                    
                    <button id="modalDeleteButton" class="modal-delete-button" onclick="deletePhotoFromModal()" style="display: none;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <!-- Yorumlar Bölümü -->
            <div class="comments-section">
                <div class="comments-header">
                    <h4 class="comments-title">
                        Yorumlar (<span id="commentsCount">0</span>)
                    </h4>
                </div>
                
                <div id="commentsList" class="comments-list">
                    <div class="no-comments">
                        Henüz yorum yapılmamış. İlk yorumu sen yap!
                    </div>
                </div>
                
                <!-- Yorum Yazma Formu -->
                <?php if ($current_user_is_approved && has_permission($pdo, 'gallery.comment.create')): ?>
                <div class="comment-form">
                    <div class="comment-input-wrapper">
                        <img src="<?php echo !empty($_SESSION['user_avatar']) ? '/public/' . htmlspecialchars($_SESSION['user_avatar']) : '' . strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>" 
                             class="comment-user-avatar" 
                             alt="Avatar">
                        <div style="flex: 1; position: relative; overflow: hidden !important;">
                            <textarea id="commentInput" 
                                      class="comment-input" 
                                      placeholder="Yorumunuzu yazın..." 
                                      maxlength="500"></textarea>
                            <button id="commentSubmitBtn" class="comment-submit-btn" onclick="submitComment()">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="comment-form">
                    <div style="text-align: center; color: var(--light-grey); font-size: 0.85rem; padding: 1rem;">
                        <?php if (!$current_user_is_logged_in): ?>
                            Yorum yapmak için <a href="<?php echo get_auth_base_url(); ?>/login.php" style="color: var(--turquase);">giriş yapın</a>.
                        <?php elseif (!$current_user_is_approved): ?>
                            Yorum yapmak için hesabınızın onaylanmış olması gerekmektedir.
                        <?php else: ?>
                            Yorum yapma yetkiniz bulunmamaktadır.
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Fotoğraf verileri (JavaScript için)
const photosData = <?php echo json_encode($photos); ?>;

// Modal için global değişkenler
let currentPhotoId = null;
let currentPhotoData = null;

// Upload Modal Functions
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    resetUploadForm();
}

function resetUploadForm() {
    document.getElementById('uploadForm').reset();
    document.getElementById('uploadPreview').style.display = 'none';
    document.querySelector('.upload-progress').style.display = 'none';
    document.querySelector('.progress-fill').style.width = '0%';
}

// Güncellenmiş openPhotoModal fonksiyonu
function openPhotoModal(photoId) {
    currentPhotoId = photoId;
    const photo = photosData.find(p => p.id == photoId);
    if (!photo) return;

    currentPhotoData = photo;

    const modal = document.getElementById('photoModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    const modalUploaderName = document.getElementById('modalUploaderName');
    const modalUploaderAvatar = document.getElementById('modalUploaderAvatar');
    const modalDate = document.getElementById('modalDate');
    const modalLikeButton = document.getElementById('modalLikeButton');
    const modalLikeIcon = document.getElementById('modalLikeIcon');
    const modalLikeCount = document.getElementById('modalLikeCount');
    const modalDeleteButton = document.getElementById('modalDeleteButton');

    // Fotoğraf bilgilerini doldur
    modalImage.src = '/public/' + photo.image_path;
    modalImage.alt = photo.description || 'Galeri Fotoğrafı';
    modalTitle.textContent = photo.description || 'Başlıksız Fotoğraf';
    modalUploaderName.textContent = photo.uploader_username;
    modalUploaderAvatar.src = photo.uploader_avatar_path ? 
        '/public/' + photo.uploader_avatar_path : 
        'https://via.placeholder.com/32x32/666666/ffffff?text=' + photo.uploader_username.charAt(0).toUpperCase();
    modalDate.textContent = new Date(photo.uploaded_at).toLocaleDateString('tr-TR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    // Beğeni durumunu güncelle
    if (modalLikeButton) {
        if (photo.user_has_liked) {
            modalLikeButton.classList.add('liked');
            modalLikeIcon.classList.remove('far');
            modalLikeIcon.classList.add('fas');
        } else {
            modalLikeButton.classList.remove('liked');
            modalLikeIcon.classList.remove('fas');
            modalLikeIcon.classList.add('far');
        }
        modalLikeCount.textContent = photo.like_count || 0;
    }

    // Silme butonunu göster/gizle
    const canDelete = <?php echo json_encode($can_delete_any); ?> || 
                     (<?php echo json_encode($current_user_id); ?> == photo.user_id && <?php echo json_encode(has_permission($pdo, 'gallery.delete_own', $current_user_id) ?? false); ?>);
    
    if (canDelete && modalDeleteButton) {
        modalDeleteButton.style.display = 'block';
    } else if (modalDeleteButton) {
        modalDeleteButton.style.display = 'none';
    }

    // Yorumları yükle
    loadComments(photoId);

    // Modal'ı göster
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePhotoModal() {
    document.getElementById('photoModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Yorumları yükleme fonksiyonu
function loadComments(photoId) {
    fetch(`/src/api/get_photo_comments.php?photo_id=${photoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayComments(data.comments);
                document.getElementById('commentsCount').textContent = data.total_comments;
            } else {
                console.error('Yorumlar yüklenemedi:', data.message);
            }
        })
        .catch(error => {
            console.error('Yorum yükleme hatası:', error);
        });
}

// Yorumları gösterme fonksiyonu
function displayComments(comments) {
    const commentsList = document.getElementById('commentsList');
    
    if (!comments || comments.length === 0) {
        commentsList.innerHTML = '<div class="no-comments">Henüz yorum yapılmamış. İlk yorumu sen yap!</div>';
        return;
    }

    const commentsHtml = comments.map(comment => {
        const canEdit = <?php echo json_encode($current_user_id); ?> == comment.user_id && <?php echo json_encode(has_permission($pdo, 'gallery.comment.edit_own', $current_user_id) ?? false); ?>;
        const canDelete = <?php echo json_encode(has_permission($pdo, 'gallery.comment.delete_all', $current_user_id) ?? false); ?> || 
                         (<?php echo json_encode($current_user_id); ?> == comment.user_id && <?php echo json_encode(has_permission($pdo, 'gallery.comment.delete_own', $current_user_id) ?? false); ?>);
        const canLike = <?php echo json_encode(has_permission($pdo, 'gallery.comment.like', $current_user_id) ?? false); ?>;

        return `
            <div class="comment-item" data-comment-id="${comment.id}">
                <img src="${comment.avatar_path ? '/public/' + comment.avatar_path : 'https://via.placeholder.com/28x28/666666/ffffff?text=' + comment.username.charAt(0).toUpperCase()}" 
                     class="comment-avatar" alt="Avatar">
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="comment-author username-role-${comment.primary_role || 'member'}">${comment.username}</span>
                        <span class="comment-date">${new Date(comment.created_at).toLocaleDateString('tr-TR')}</span>
                    </div>
                    <div class="comment-text" id="comment-text-${comment.id}">${comment.comment_text}</div>
                    <div class="comment-actions">
                        ${canLike ? `
                            <button class="comment-like-btn ${comment.user_has_liked ? 'liked' : ''}" 
                                    onclick="toggleCommentLike(${comment.id}, this)">
                                <i class="${comment.user_has_liked ? 'fas' : 'far'} fa-heart"></i> 
                                <span class="comment-like-count">${comment.like_count || 0}</span>
                            </button>
                        ` : ''}
                        
                        ${canEdit ? `
                            <button class="comment-edit-btn" onclick="editComment(${comment.id})">
                                <i class="fas fa-edit"></i> Düzenle
                            </button>
                        ` : ''}
                        
                        ${canDelete ? `
                            <button class="comment-delete-btn" onclick="deleteComment(${comment.id})">
                                <i class="fas fa-trash"></i> Sil
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    commentsList.innerHTML = commentsHtml;
}

// Yorum gönderme fonksiyonu
function submitComment() {
    const commentInput = document.getElementById('commentInput');
    const submitBtn = document.getElementById('commentSubmitBtn');
    const commentText = commentInput.value.trim();

    if (!commentText || commentText.length < 5) {
        alert('Lütfen en az 5 karakter uzunluğunda bir yorum yazın.');
        return;
    }

    if (commentText.length > 500) {
        alert('Yorum 500 karakterden uzun olamaz.');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';

    fetch('/src/api/add_photo_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            photo_id: currentPhotoId,
            comment_text: commentText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            commentInput.value = '';
            loadComments(currentPhotoId); // Yorumları yeniden yükle
        } else {
            alert('Yorum gönderilemedi: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Yorum gönderme hatası:', error);
        alert('Ağ hatası oluştu.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Yorum Yap';
    });
}

// Modal'da beğeni fonksiyonu
function togglePhotoLikeInModal() {
    if (!currentPhotoData) return;

    const likeButton = document.getElementById('modalLikeButton');
    const likeIcon = document.getElementById('modalLikeIcon');
    const likeCount = document.getElementById('modalLikeCount');
    const isLiked = likeButton.classList.contains('liked');

    likeButton.disabled = true;

    fetch('/src/api/toggle_photo_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            photo_id: currentPhotoId,
            action: isLiked ? 'unlike' : 'like'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isLiked) {
                likeButton.classList.remove('liked');
                likeIcon.classList.remove('fas');
                likeIcon.classList.add('far');
                likeCount.textContent = parseInt(likeCount.textContent) - 1;
                currentPhotoData.user_has_liked = false;
                currentPhotoData.like_count = parseInt(likeCount.textContent);
            } else {
                likeButton.classList.add('liked');
                likeIcon.classList.remove('far');
                likeIcon.classList.add('fas');
                likeCount.textContent = parseInt(likeCount.textContent) + 1;
                currentPhotoData.user_has_liked = true;
                currentPhotoData.like_count = parseInt(likeCount.textContent);
            }

            // Ana galeri sayfasındaki beğeni sayısını da güncelle
            const mainLikeButton = document.querySelector(`[onclick*="toggleLike(${currentPhotoId}"]`);
            if (mainLikeButton) {
                const mainLikeCount = mainLikeButton.querySelector('.like-count');
                if (mainLikeCount) {
                    mainLikeCount.textContent = likeCount.textContent;
                }
                if (isLiked) {
                    mainLikeButton.classList.remove('liked');
                    const mainIcon = mainLikeButton.querySelector('i');
                    if (mainIcon) {
                        mainIcon.classList.remove('fas');
                        mainIcon.classList.add('far');
                    }
                } else {
                    mainLikeButton.classList.add('liked');
                    const mainIcon = mainLikeButton.querySelector('i');
                    if (mainIcon) {
                        mainIcon.classList.remove('far');
                        mainIcon.classList.add('fas');
                    }
                }
            }
        } else {
            alert('İşlem başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ağ hatası oluştu.');
    })
    .finally(() => {
        likeButton.disabled = false;
    });
}

// Modal'dan fotoğraf silme
function deletePhotoFromModal() {
    if (!currentPhotoId) return;

    if (!confirm('Bu fotoğrafı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        return;
    }

    fetch('/src/api/delete_photo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            photo_id: currentPhotoId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Fotoğraf başarıyla silindi.');
            closePhotoModal();
            location.reload(); // Sayfayı yenile
        } else {
            alert('Silme işlemi başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ağ hatası oluştu.');
    });
}

// Yorum beğeni fonksiyonu
function toggleCommentLike(commentId, button) {
    const likeCountSpan = button.querySelector('.comment-like-count');
    const heartIcon = button.querySelector('i');
    const isLiked = button.classList.contains('liked');

    button.disabled = true;

    fetch('/src/api/toggle_comment_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            comment_id: commentId,
            action: isLiked ? 'unlike' : 'like'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isLiked) {
                button.classList.remove('liked');
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
                likeCountSpan.textContent = parseInt(likeCountSpan.textContent) - 1;
            } else {
                button.classList.add('liked');
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
                likeCountSpan.textContent = parseInt(likeCountSpan.textContent) + 1;
            }
        } else {
            alert('İşlem başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ağ hatası oluştu.');
    })
    .finally(() => {
        button.disabled = false;
    });
}

// Yorum düzenleme fonksiyonu
function editComment(commentId) {
    const commentTextElement = document.getElementById(`comment-text-${commentId}`);
    const currentText = commentTextElement.textContent;

    const editForm = `
        <div class="comment-editing">
            <div class="comment-edit-form">
                <textarea class="comment-edit-input" id="edit-input-${commentId}" maxlength="500">${currentText}</textarea>
                <div class="comment-edit-actions">
                    <button class="comment-save-btn" onclick="saveComment(${commentId})">Kaydet</button>
                    <button class="comment-cancel-btn" onclick="cancelEdit(${commentId}, '${currentText.replace(/'/g, "\\'")}')">İptal</button>
                </div>
            </div>
        </div>
    `;

    commentTextElement.innerHTML = editForm;
}

// Yorum kaydetme fonksiyonu
function saveComment(commentId) {
    const editInput = document.getElementById(`edit-input-${commentId}`);
    const newText = editInput.value.trim();

    if (!newText || newText.length < 2) {
        alert('Lütfen en az 2 karakter uzunluğunda bir yorum yazın.');
        return;
    }

    if (newText.length > 500) {
        alert('Yorum 500 karakterden uzun olamaz.');
        return;
    }

    fetch('/src/api/edit_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            comment_id: commentId,
            comment_text: newText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadComments(currentPhotoId); // Yorumları yeniden yükle
        } else {
            alert('Yorum güncellenemedi: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Yorum güncelleme hatası:', error);
        alert('Ağ hatası oluştu.');
    });
}

// Yorum düzenlemeyi iptal etme
function cancelEdit(commentId, originalText) {
    const commentTextElement = document.getElementById(`comment-text-${commentId}`);
    commentTextElement.textContent = originalText;
}

// Yorum silme fonksiyonu
function deleteComment(commentId) {
    if (!confirm('Bu yorumu silmek istediğinizden emin misiniz?')) {
        return;
    }

    fetch('/src/api/delete_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            comment_id: commentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadComments(currentPhotoId); // Yorumları yeniden yükle
        } else {
            alert('Yorum silinemedi: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Yorum silme hatası:', error);
        alert('Ağ hatası oluştu.');
    });
}

// Like functionality (Ana galeri için)
function toggleLike(photoId, button) {
    const likeCountSpan = button.querySelector('.like-count');
    const heartIcon = button.querySelector('i');
    const isLiked = button.classList.contains('liked');
    
    button.disabled = true;
    
    fetch('/src/api/toggle_photo_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            photo_id: photoId,
            action: isLiked ? 'unlike' : 'like'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isLiked) {
                button.classList.remove('liked');
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
                likeCountSpan.textContent = parseInt(likeCountSpan.textContent) - 1;
            } else {
                button.classList.add('liked');
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
                likeCountSpan.textContent = parseInt(likeCountSpan.textContent) + 1;
            }
        } else {
            alert('İşlem başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ağ hatası oluştu.');
    })
    .finally(() => {
        button.disabled = false;
    });
}

// Delete functionality (Ana galeri için)
function deletePhoto(photoId) {
    if (!confirm('Bu fotoğrafı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        return;
    }
    
    fetch('/src/api/delete_photo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            photo_id: photoId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Fotoğraf başarıyla silindi.');
            location.reload();
        } else {
            alert('Silme işlemi başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ağ hatası oluştu.');
    });
}

// File Upload Handling
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.querySelector('.file-upload-area');
    const uploadPreview = document.getElementById('uploadPreview');
    const previewImage = document.getElementById('previewImage');
    const uploadForm = document.getElementById('uploadForm');

    if (fileInput) {
        // File input change
        fileInput.addEventListener('change', handleFileSelect);

        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect({ target: { files: files } });
            }
        });

        // Form submit
        uploadForm.addEventListener('submit', handleUploadSubmit);
    }

    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Lütfen geçerli bir resim dosyası seçin.');
            return;
        }

        // Validate file size (5MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Dosya boyutu 10MB\'dan küçük olmalıdır.');
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            uploadPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    function handleUploadSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = document.getElementById('fileInput');
        const description = document.getElementById('description').value;
        const visibility = document.querySelector('input[name="visibility"]:checked').value;

        if (!fileInput.files[0]) {
            alert('Lütfen bir fotoğraf seçin.');
            return;
        }

        formData.append('photo', fileInput.files[0]);
        formData.append('description', description);
        formData.append('visibility', visibility);

        // Show progress
        const progressContainer = document.querySelector('.upload-progress');
        const progressFill = document.querySelector('.progress-fill');
        const submitButton = document.querySelector('.btn-upload-submit');
        
        progressContainer.style.display = 'block';
        submitButton.disabled = true;
        submitButton.textContent = 'Yükleniyor...';

        // Upload with XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressFill.style.width = percentComplete + '%';
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Fotoğraf başarıyla yüklendi!');
                        closeUploadModal();
                        location.reload(); // Refresh to show new photo
                    } else {
                        alert('Hata: ' + (response.message || 'Bilinmeyen hata'));
                    }
                } catch (e) {
                    alert('Sunucu yanıtı işlenirken hata oluştu.');
                }
            } else {
                alert('Yükleme sırasında hata oluştu. Lütfen tekrar deneyin.');
            }
            
            // Reset form state
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-upload"></i> Fotoğrafı Yükle';
            progressContainer.style.display = 'none';
            progressFill.style.width = '0%';
        });

        xhr.addEventListener('error', function() {
            alert('Yükleme sırasında ağ hatası oluştu.');
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-upload"></i> Fotoğrafı Yükle';
            progressContainer.style.display = 'none';
        });

        xhr.open('POST', '/public/upload_photo.php');
        xhr.send(formData);
    }

    // Enter tuşu ile yorum gönderme
    const commentInput = document.getElementById('commentInput');
    if (commentInput) {
        commentInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                submitComment();
            }
        });
    }
});

// Modal close on outside click
window.addEventListener('click', function(event) {
    const uploadModal = document.getElementById('uploadModal');
    const photoModal = document.getElementById('photoModal');
    
    if (event.target === uploadModal) {
        closeUploadModal();
    }
    
    if (event.target === photoModal) {
        closePhotoModal();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('uploadModal').style.display === 'block') {
            closeUploadModal();
        }
        if (document.getElementById('photoModal').style.display === 'block') {
            closePhotoModal();
        }
    }
});

// Intersection Observer for smooth animations
if ('IntersectionObserver' in window) {
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    document.querySelectorAll('.photo-card').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        cardObserver.observe(card);
    });
}
</script>
