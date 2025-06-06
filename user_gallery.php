<?php
// public/user_gallery.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_user_logged_in() || !is_user_approved()) {
    $_SESSION['error_message'] = "Kullanıcı galerilerini görmek için giriş yapmalı ve hesabınız onaylanmış olmalıdır.";
    header('Location: ' . get_auth_base_url() . '/login.php');
    exit;
}


$profile_user_id = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $profile_user_id = (int)$_GET['user_id'];
} else {
    if (is_user_logged_in()) {
        $profile_user_id = $_SESSION['user_id'];
    } else {
        $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si.";
        header('Location: ' . get_auth_base_url() . '/members.php'); 
        exit;
    }
}

$viewed_user_data = null; 
$page_title = "Kullanıcı Galerisi";
$user_gallery_photos_data_for_js = []; 
$user_gallery_photos_for_display = [];

$role_priority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];

try {
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

    if (!$viewed_user_data || ($viewed_user_data['status'] !== 'approved' && !(is_user_logged_in() && (is_user_admin() || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id))))) {
        $_SESSION['error_message'] = "Kullanıcı bulunamadı veya profili görüntülenemiyor.";
        header('Location: ' . get_auth_base_url() . '/members.php');
        exit;
    }
    $page_title = htmlspecialchars($viewed_user_data['username']) . " Kullanıcısının Galerisi";

    $sql_photos_select = "SELECT
                gp.id AS photo_id,
                gp.image_path,
                gp.description AS photo_description,
                gp.uploaded_at,
                (SELECT COUNT(*) FROM gallery_photo_likes gpl WHERE gpl.photo_id = gp.id) AS like_count";

    if (is_user_logged_in() && isset($_SESSION['user_id'])) {
        $sql_photos_select .= ", (SELECT COUNT(*) FROM gallery_photo_likes gpl_user WHERE gpl_user.photo_id = gp.id AND gpl_user.user_id = :current_user_id) AS user_has_liked";
    }
    
    $sql_photos_from_where_order = " FROM gallery_photos gp
            WHERE gp.user_id = :profile_user_id
            ORDER BY gp.uploaded_at DESC";

    $final_photos_sql = $sql_photos_select . $sql_photos_from_where_order;
    
    $stmt_photos = $pdo->prepare($final_photos_sql);
    $params_photos = [':profile_user_id' => $profile_user_id];
    if (is_user_logged_in() && isset($_SESSION['user_id'])) {
        $params_photos[':current_user_id'] = $_SESSION['user_id'];
    }
    $stmt_photos->execute($params_photos);
    $user_gallery_photos_for_display = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

    if ($user_gallery_photos_for_display) {
        foreach ($user_gallery_photos_for_display as $photo) {
            $user_gallery_photos_data_for_js[] = [
                'photo_id' => (int)$photo['photo_id'],
                'image_path_full' => '/public/' . htmlspecialchars($photo['image_path'], ENT_QUOTES, 'UTF-8'),
                'photo_description' => htmlspecialchars($photo['photo_description'] ?: '', ENT_QUOTES, 'UTF-8'),
                'uploader_user_id' => (int)$profile_user_id, 
                'uploader_username' => htmlspecialchars($viewed_user_data['username'], ENT_QUOTES, 'UTF-8'),
                'uploader_avatar_path_full' => !empty($viewed_user_data['avatar_path']) ? '/public/' . htmlspecialchars($viewed_user_data['avatar_path'], ENT_QUOTES, 'UTF-8') : '',
                'uploader_ingame_name' => htmlspecialchars($viewed_user_data['ingame_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_discord_username' => htmlspecialchars($viewed_user_data['discord_username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_event_count' => (int)($viewed_user_data['user_event_count'] ?? 0),
                'uploader_gallery_count' => (int)($viewed_user_data['user_gallery_count'] ?? 0),
                'uploader_roles_list' => htmlspecialchars($viewed_user_data['user_roles_list'] ?? '', ENT_QUOTES, 'UTF-8'),
                'uploaded_at_formatted' => date('d F Y, H:i', strtotime($photo['uploaded_at'])),
                'like_count' => (int)($photo['like_count'] ?? 0),
                'user_has_liked' => isset($photo['user_has_liked']) ? (bool)$photo['user_has_liked'] : false
            ];
        }
    }

} catch (PDOException $e) {
    error_log("Kullanıcı galerisi (user_gallery.php) çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcı galerisi yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* user_gallery.php için Stiller */
.user-gallery-page-container-v2 { 
    width: 100%;
    max-width: 1600px; 
    margin: 40px auto;
    padding: 0 25px; 
    font-family: var(--font);
    color: var(--lighter-grey);
}

/* YENİ: Sayfa Üstü Kontrol Butonları */
.page-top-controls-ug {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px; /* Başlıktan önce boşluk */
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-2);
}

.btn-outline-turquase { 
    color: var(--turquase); 
    border-color: var(--turquase); /* Explicitly set border-color */
    background-color: transparent;
    padding: 8px 18px; 
    font-size: 0.9rem; 
    font-weight: 500; 
    border-radius: 20px; 
    border: 1px solid var(--turquase); /* Ensure this uses the variable */
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    transition: all 0.2s ease; 
}
.btn-outline-turquase:hover { 
    color: var(--white); 
    background-color: var(--turquase); 
    border-color: var(--turquase);
    transform: translateY(-1px); 
}
.btn-outline-turquase.btn-sm { 
    padding: 6px 14px;
    font-size: 0.85rem;
}
.btn-outline-turquase i.fas { 
    font-size: 0.9em;
}
/* YENİ BUTON STİLİ SONU */

.user-gallery-header-v2 { 
    /* display: flex; Artık flex'e gerek yok, sadece başlık var */
    /* justify-content: space-between; */
    /* align-items: center; */
    text-align: center; /* Başlığı ortala */
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.user-gallery-header-v2 .page-title-container-ug { 
    /* Bu container artık gereksiz olabilir, başlık doğrudan header içinde olabilir */
}
.user-gallery-header-v2 h1.page-main-title-ug { 
    color: var(--gold);
    font-size: 2.2rem; 
    font-family: var(--font);
    margin: 0; /* Altındaki link kaldırıldığı için margin sıfırlanabilir */
    font-weight: 600;
}

.no-items-message-ug { 
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 40px 20px;
    border: 1px dashed var(--darker-gold-2); border-radius: 8px; margin-top: 20px;
}
.no-items-message-ug a { color: var(--turquase); font-weight: bold; text-decoration:none; }
.no-items-message-ug a:hover { text-decoration: underline; }

.gallery-grid-v2 { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; }
.gallery-card-v2 {
    background-color: transparent; 
    border: 1px solid var(--darker-gold-2); 
    border-radius: 10px; 
    overflow: hidden; 
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.3s ease-out, border-color 0.3s ease-out;
    position: relative; 
}
.gallery-card-v2:hover {
    box-shadow: 0 8px 25px rgba(var(--gold-rgb, 189, 145, 42), 0.12); 
    border-color: var(--darker-gold-1);
}

.gallery-image-link-v2 { 
    display: block; position: relative; overflow: hidden; 
    border-radius: 10px; 
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

.gallery-card-info-v2 { 
    position: absolute; 
    bottom: 0;
    left: 0;
    right: 0;
    padding: 10px 15px; 
    background-color: rgba(var(--charcoal-rgb, 34, 34, 34), 0.75); 
    backdrop-filter: blur(5px); 
    -webkit-backdrop-filter: blur(5px);
    border-top: 1px solid rgba(var(--darker-gold-1-rgb, 82, 56, 10), 0.5); 
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px; 
    border-radius: 0 0 10px 10px; 
    z-index: 1; 
    opacity: 0; 
    transform: translateY(10px); 
    transition: opacity 0.3s ease-out, transform 0.3s ease-out;
}
.gallery-card-v2:hover .gallery-card-info-v2 { 
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
    font-size: 0.9rem; font-weight: 500; text-decoration: none; color: var(--lighter-grey); 
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

.uploader-info-in-card-trigger-v2.username-role-admin a { color: var(--gold) !important; }
.uploader-info-in-card-trigger-v2.username-role-scg_uye a { color: #A52A2A !important; }
.uploader-info-in-card-trigger-v2.username-role-ilgarion_turanis a { color: var(--turquase) !important; }
.uploader-info-in-card-trigger-v2.username-role-member a { color: var(--white) !important; }
.uploader-info-in-card-trigger-v2.username-role-dis_uye a { color: var(--light-grey) !important; }

.gallery-card-admin-actions { 
    position: absolute;
    top: 10px; 
    right: 10px; 
    z-index: 5; 
    opacity: 0; 
    visibility: hidden; 
    transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out;
}
.gallery-card-v2:hover .gallery-card-admin-actions { 
    opacity: 1;
    visibility: visible;
}
.gallery-card-admin-actions .btn-delete-photo-card {
    background-color: rgba(var(--dark-red-rgb, 184, 29, 36), 0.6); 
    color: var(--white);
    border: 1px solid rgba(var(--red-rgb, 235, 0, 0), 0.4);
    border-radius: 50%;
    width: 32px; 
    height: 32px;
    padding: 0;
    font-size: 0.95rem; 
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.gallery-card-admin-actions .btn-delete-photo-card:hover {
    background-color: var(--red);
    color: var(--white);
    transform: scale(1.1);
}


@media (max-width: 768px) {
    .user-gallery-page-container-v2 { padding: 0 15px; margin:20px auto; }
    .page-top-controls-ug { flex-direction:column; align-items:stretch; gap:15px;} /* Mobil için butonlar alt alta */
    .user-gallery-header-v2 { text-align:center;} /* Başlık mobilde ortalansın */
    .gallery-grid-v2 { grid-template-columns: 1fr; gap:25px; }
    .gallery-image-v2 { height: 250px; }
    .gallery-card-v2 .gallery-card-info-v2 { 
        opacity: 1;
        transform: translateY(0);
        background-color: rgba(var(--charcoal-rgb, 34, 34, 34), 0.85); 
    }
    .gallery-card-v2 .gallery-card-admin-actions {
        opacity: 1;
        visibility: visible;
    }
    .gallery-card-info-v2 { flex-direction:column; align-items:flex-start; gap:8px;}
    .photo-description-preview-v2 { margin-left:0; white-space:normal; -webkit-line-clamp: 2; display:-webkit-box; -webkit-box-orient:vertical;}
}
</style>

<main class="main-content">
    <div class="container user-gallery-page-container-v2">
        <div class="page-top-controls-ug">
            <a href="view_profile.php?user_id=<?php echo $profile_user_id; ?>" class="btn-outline-turquase">
                <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($viewed_user_data['username']); ?> Profiline Geri Dön
            </a>
            <?php if (is_user_logged_in() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id): ?>
                <a href="upload_gallery_photo.php" class="btn-outline-turquase"><i class="fas fa-plus-circle"></i> Yeni Fotoğraf Yükle</a>
            <?php endif; ?>
        </div>

        <div class="user-gallery-header-v2">
            <div class="page-title-container-ug">
                <h1 class="page-main-title-ug"><?php echo $page_title; ?> (<?php echo count($user_gallery_photos_for_display); ?> Fotoğraf)</h1>
                <?php /* "Profiline Geri Dön" linki yukarı taşındı */ ?>
            </div>
            <?php /* "Yeni Fotoğraf Yükle" butonu yukarı taşındı */ ?>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="message error-message" style="text-align:center;"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="message success-message" style="text-align:center;"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
        <?php endif; ?>

        <?php if (empty($user_gallery_photos_for_display) && !(isset($_SESSION['error_message']) || isset($_SESSION['success_message'])) ): ?>
            <p class="no-items-message-ug">
                Bu kullanıcının galerisinde henüz hiç fotoğraf bulunmamaktadır.
                <?php if (is_user_logged_in() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id): ?>
                    <br><a href="upload_gallery_photo.php">İlk fotoğrafını sen yükle!</a>
                <?php endif; ?>
            </p>
        <?php elseif (!empty($user_gallery_photos_for_display)): ?>
            <div class="gallery-grid-v2">
                <?php foreach ($user_gallery_photos_for_display as $index => $photo): ?>
                    <?php
                        $uploader_roles_arr = !empty($viewed_user_data['user_roles_list']) ? explode(',', $viewed_user_data['user_roles_list']) : [];
                        $uploader_username_class = '';
                        foreach ($role_priority as $p_role) {
                            if (in_array($p_role, $uploader_roles_arr)) {
                                $uploader_username_class = 'username-role-' . $p_role;
                                break;
                            }
                        }
                        $description_excerpt = htmlspecialchars(mb_substr(strip_tags($photo['photo_description'] ?? ''), 0, 40, 'UTF-8'));
                        if (mb_strlen(strip_tags($photo['photo_description'] ?? ''), 'UTF-8') > 40) {
                            $description_excerpt .= '...';
                        }
                    ?>
                    <div class="gallery-card-v2" id="photo-card-<?php echo $photo['photo_id']; ?>">
                        <?php if (is_user_logged_in() && isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $profile_user_id || is_user_admin())): ?>
                            <div class="gallery-card-admin-actions">
                                <form action="../src/actions/handle_admin_gallery_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu fotoğrafı KALICI OLARAK galeriden silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="photo_id" value="<?php echo $photo['photo_id']; ?>">
                                    <input type="hidden" name="image_path" value="<?php echo htmlspecialchars($photo['image_path']); ?>">
                                    <input type="hidden" name="action" value="delete_photo">
                                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
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
                                <span class="uploader-info-in-card-trigger-v2 user-info-trigger <?php echo $uploader_username_class; ?>"
                                     data-user-id="<?php echo htmlspecialchars($profile_user_id); ?>"
                                     data-username="<?php echo htmlspecialchars($viewed_user_data['username']); ?>"
                                     data-avatar="<?php echo htmlspecialchars(!empty($viewed_user_data['avatar_path']) ? ('/public/' . $viewed_user_data['avatar_path']) : ''); ?>"
                                     data-ingame="<?php echo htmlspecialchars($viewed_user_data['ingame_name'] ?? 'N/A'); ?>"
                                     data-discord="<?php echo htmlspecialchars($viewed_user_data['discord_username'] ?? 'N/A'); ?>"
                                     data-event-count="<?php echo htmlspecialchars($viewed_user_data['user_event_count'] ?? '0'); ?>"
                                     data-gallery-count="<?php echo htmlspecialchars($viewed_user_data['user_gallery_count'] ?? '0'); ?>"
                                     data-roles="<?php echo htmlspecialchars($viewed_user_data['user_roles_list'] ?? ''); ?>">
                                    
                                    <?php if (!empty($viewed_user_data['avatar_path'])): ?>
                                        <img src="/public/<?php echo htmlspecialchars($viewed_user_data['avatar_path']); ?>" alt="<?php echo htmlspecialchars($viewed_user_data['username']); ?>" class="uploader-avatar-v2">
                                    <?php else: ?>
                                        <div class="avatar-placeholder-small-v2"><?php echo strtoupper(substr(htmlspecialchars($viewed_user_data['username']), 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <span class="uploader-username-v2">
                                        <a href="view_profile.php?user_id=<?php echo $profile_user_id; ?>" title="<?php echo htmlspecialchars($viewed_user_data['username']); ?> Profilini Gör">
                                            <?php echo htmlspecialchars($viewed_user_data['username']); ?>
                                        </a>
                                    </span>
                                </span>
                                <?php if (!empty($description_excerpt)): ?>
                                    <span class="photo-description-preview-v2" title="<?php echo htmlspecialchars($photo['photo_description'] ?? ''); ?>">
                                        - <?php echo $description_excerpt; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (is_user_approved()): ?>
                            <button class="like-button-gallery-v2 <?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'liked' : ''; ?>" 
                                    data-photo-id="<?php echo $photo['photo_id']; ?>"
                                    title="<?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'Beğenmekten Vazgeç' : 'Beğen'; ?>">
                                <i class="fas <?php echo (isset($photo['user_has_liked']) && $photo['user_has_liked']) ? 'fa-heart-crack' : 'fa-heart'; ?>"></i>
                                <span class="like-count"><?php echo $photo['like_count'] ?? 0; ?></span>
                            </button>
                            <?php else: ?>
                                <span class="like-button-gallery-v2" style="cursor:default; border-color:transparent;"> 
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
window.phpGalleryPhotos = <?php echo json_encode($user_gallery_photos_data_for_js); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const likeButtons = document.querySelectorAll('.like-button-gallery-v2');
    likeButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation(); 
            const photoId = this.dataset.photoId;
            const likeIcon = this.querySelector('i.fas');
            const likeCountSpan = this.querySelector('span.like-count');

            fetch('../src/actions/handle_gallery_like.php', {
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
