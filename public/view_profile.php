<?php
// public/view_profile.php

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

require_login(); // Sayfayı görmek için giriş yapmış olmak yeterli

$profile_user_id = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $profile_user_id = (int)$_GET['user_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si.";
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
}

$profile_user_data = null;
$user_gallery_photos_preview = [];
$user_hangar_ships_preview = [];
$gallery_photos_data_for_js_modal = []; // Modal için

// Rol öncelik sırası (renklendirme için)
$role_priority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];

try {
    // Kullanıcı ana bilgileri ve rolleri
    $sql_user = "SELECT 
                u.id, u.username, u.avatar_path, u.ingame_name, u.discord_username,
                u.profile_info, u.status, u.created_at AS member_since,
                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id AND status = 'active') AS active_event_count,
                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS total_event_count,
                (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS total_gallery_count,
                (SELECT GROUP_CONCAT(r.name SEPARATOR ',') 
                 FROM user_roles ur 
                 JOIN roles r ON ur.role_id = r.id 
                 WHERE ur.user_id = u.id) AS user_roles_list
            FROM users u
            WHERE u.id = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':user_id', $profile_user_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $profile_user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($profile_user_data) {
        // Kullanıcı 'approved' değilse ve bakan kişi admin değilse veya kendisi değilse erişimi engelle
        if ($profile_user_data['status'] !== 'approved' && 
            !(is_user_logged_in() && (is_user_admin() || $_SESSION['user_id'] == $profile_user_id))) {
            $_SESSION['error_message'] = "Bu kullanıcının profili görüntülenemiyor.";
            header('Location: ' . get_auth_base_url() . '/members.php'); // Üye listesine yönlendir
            exit;
        }
        $page_title = htmlspecialchars($profile_user_data['username']) . " Profili";

        // Son 5 galeri fotoğrafı
        $stmt_gallery = $pdo->prepare(
            "SELECT gp.id AS photo_id, gp.image_path, gp.description AS photo_description, gp.uploaded_at,
                    u_gal.id AS uploader_id, u_gal.username AS uploader_username, u_gal.avatar_path AS uploader_avatar_path,
                    u_gal.ingame_name AS uploader_ingame_name, u_gal.discord_username AS uploader_discord_username,
                    (SELECT COUNT(*) FROM events WHERE created_by_user_id = u_gal.id) AS uploader_event_count,
                    (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u_gal.id) AS uploader_gallery_count,
                    (SELECT GROUP_CONCAT(r_gal.name SEPARATOR ',') FROM user_roles ur_gal JOIN roles r_gal ON ur_gal.role_id = r_gal.id WHERE ur_gal.user_id = u_gal.id) AS uploader_roles_list,
                    (SELECT COUNT(*) FROM gallery_photo_likes gpl WHERE gpl.photo_id = gp.id) AS like_count"
            . (is_user_logged_in() ? ", (SELECT COUNT(*) FROM gallery_photo_likes gpl_user WHERE gpl_user.photo_id = gp.id AND gpl_user.user_id = ".$_SESSION['user_id'].") AS user_has_liked" : "") .
            " FROM gallery_photos gp 
              JOIN users u_gal ON gp.user_id = u_gal.id
              WHERE gp.user_id = :profile_user_id ORDER BY gp.uploaded_at DESC LIMIT 5"
        );
        $stmt_gallery->bindParam(':profile_user_id', $profile_user_id, PDO::PARAM_INT);
        $stmt_gallery->execute();
        $user_gallery_photos_preview = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);

        // Galeri modalı için JS verisi
        foreach ($user_gallery_photos_preview as $photo_item) {
            $gallery_photos_data_for_js_modal[] = [
                'photo_id' => (int)$photo_item['photo_id'],
                'image_path_full' => '/public/' . htmlspecialchars($photo_item['image_path'], ENT_QUOTES, 'UTF-8'),
                'photo_description' => htmlspecialchars($photo_item['photo_description'] ?: '', ENT_QUOTES, 'UTF-8'),
                'uploader_user_id' => (int)$photo_item['uploader_id'],
                'uploader_username' => htmlspecialchars($photo_item['uploader_username'], ENT_QUOTES, 'UTF-8'),
                'uploader_avatar_path_full' => !empty($photo_item['uploader_avatar_path']) ? '/public/' . htmlspecialchars($photo_item['uploader_avatar_path'], ENT_QUOTES, 'UTF-8') : '',
                'uploader_ingame_name' => htmlspecialchars($photo_item['uploader_ingame_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_discord_username' => htmlspecialchars($photo_item['uploader_discord_username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'uploader_event_count' => (int)($photo_item['uploader_event_count'] ?? 0),
                'uploader_gallery_count' => (int)($photo_item['uploader_gallery_count'] ?? 0),
                'uploader_roles_list' => htmlspecialchars($photo_item['uploader_roles_list'] ?? '', ENT_QUOTES, 'UTF-8'),
                'uploaded_at_formatted' => date('d M Y', strtotime($photo_item['uploaded_at'])), // Sadece tarih
                'like_count' => (int)($photo_item['like_count'] ?? 0),
                'user_has_liked' => isset($photo_item['user_has_liked']) ? (bool)$photo_item['user_has_liked'] : false
            ];
        }


        // İlk 5 hangar gemisi
        $stmt_hangar_ships = $pdo->prepare("SELECT ship_name, ship_image_url, quantity FROM user_hangar WHERE user_id = :profile_user_id ORDER BY ship_name ASC LIMIT 5");
        $stmt_hangar_ships->bindParam(':profile_user_id', $profile_user_id, PDO::PARAM_INT);
        $stmt_hangar_ships->execute();
        $user_hangar_ships_preview = $stmt_hangar_ships->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $_SESSION['error_message'] = "Kullanıcı bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/members.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Kullanıcı profili (view_profile) çekme hatası (ID: $profile_user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcı profili yüklenirken bir sorun oluştu.";
    header('Location: ' . get_auth_base_url() . '/members.php');
    exit;
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* view_profile.php için Modern Stiller */
.profile-page-v2 {
    width: 100%;
    max-width: 1400px; /* Sayfa genişliği */
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 160px);
}

.profile-grid-layout {
    display: grid;
    grid-template-columns: 350px 1fr; /* Sol sidebar, sağ ana içerik */
    gap: 30px;
}

/* Sol Sidebar Stilleri */
.profile-sidebar-v2 {
    padding: 25px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    align-self: flex-start; /* Grid içinde yukarıda başlasın */
    position: sticky; /* Sayfa kaydırılsa bile sabit kalabilir (opsiyonel) */
    top: calc(var(--navbar-height, 70px) + 20px); /* Navbar yüksekliği + boşluk */
}

.profile-sidebar-avatar-section {
    text-align: center;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.profile-main-avatar { /* .profile-avatar yerine */
    width: 160px;
    height: 160px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--gold);
    box-shadow: 0 0 20px var(--transparent-gold);
    margin-bottom: 15px;
}
.avatar-placeholder-profile { /* .avatar-placeholder.large-placeholder yerine */
    width: 160px; height: 160px; border-radius: 50%;
    background-color: var(--grey); color: var(--gold);
    display: flex; align-items: center; justify-content: center;
    font-size: 5rem; font-weight: bold;
    border: 4px solid var(--darker-gold-1);
    margin: 0 auto 15px auto;
}
.profile-sidebar-username {
    color: var(--gold); /* Varsayılan renk, JS ile üzerine yazılacak */
    font-size: 1.6rem;
    font-weight: 600;
    margin: 0 0 5px 0;
    word-break: break-all;
}
/* Sidebar için kullanıcı adı renkleri */
.profile-sidebar-username.username-role-admin { color: var(--gold) !important; }
.profile-sidebar-username.username-role-scg_uye { color: #A52A2A !important; }
.profile-sidebar-username.username-role-ilgarion_turanis { color: var(--turquase) !important; }
.profile-sidebar-username.username-role-member { color: var(--white) !important; }
.profile-sidebar-username.username-role-dis_uye { color: var(--light-grey) !important; }

.profile-sidebar-member-since {
    font-size: 0.85rem;
    color: var(--light-grey);
}
.profile-sidebar-roles {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
}
/* .role-badge stilleri style.css'de tanımlı olmalı (popover için olanlar) */

.profile-sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}
.profile-sidebar-nav li a {
    display: flex; /* İkon ve metin için */
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: var(--lighter-grey);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 6px;
    transition: background-color 0.2s ease, color 0.2s ease, padding-left 0.2s ease;
    border-bottom: 1px solid var(--darker-gold-2);
}
.profile-sidebar-nav li:last-child a { border-bottom: none; }
.profile-sidebar-nav li a:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    padding-left: 20px;
}
.profile-sidebar-nav li a i.fas {
    width: 18px; /* İkon hizalaması için */
    text-align: center;
    color: var(--light-gold); /* İkon rengi */
    font-size: 1em;
}
.profile-sidebar-nav li a:hover i.fas {
    color: var(--gold);
}
.fa-user-edit {
    color: var(--gold) !important;
}
.profile-sidebar-nav .btn-edit-my-profile:hover .fa-user-edit  {
    color: black !important;
}
.profile-sidebar-nav .btn-edit-my-profile { /* "Profilimi Düzenle" butonu */
    color: var(--gold);
    text-align: center;
    justify-content: center;
    font-weight: 600;
}
.profile-sidebar-nav .btn-edit-my-profile:hover {
    background-color: var(--gold);
    color: var(--black);
    padding-left: 15px; /* Hover'da padding-left değişmesin */
}


/* Sağ Ana İçerik Stilleri */
.profile-main-content-v2 {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.profile-info-card-v2,
.profile-activity-card-v2 {
    padding: 25px 30px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}
.profile-info-card-v2 h3,
.profile-activity-card-v2 h3 {
    color: var(--light-gold);
    font-size: 1.5rem;
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-2);
    font-family: var(--font);
    font-weight: 600;
}

.profile-info-list-v2 {
    list-style: none;
    padding: 0;
    margin: 0;
}
.profile-info-list-v2 li {
    display: flex; /* Label ve değeri yan yana getirmek için */
    padding: 10px 0;
    border-bottom: 1px dashed var(--darker-gold-2);
    font-size: 0.95rem;
    line-height: 1.6;
}
.profile-info-list-v2 li:last-child { border-bottom: none; }
.profile-info-list-v2 li strong.info-label {
    color: var(--light-grey);
    font-weight: 500;
    min-width: 180px; /* Label genişliği */
    flex-shrink: 0;
    margin-right: 15px;
}
.profile-info-list-v2 li span.info-value {
    color: var(--lighter-grey);
    word-break: break-word;
}
.profile-info-list-v2 li span.info-value.not-specified {
    color: var(--light-grey);
    font-style: italic;
}
.profile-info-list-v2 li .discord-info-v2 .fab.fa-discord { /* Discord ikonu için */
    color: #7289DA; margin-right: 6px;
}

.profile-bio-section-v2 { margin-top: 20px; }
.profile-bio-section-v2 h4 {
    color: var(--gold);
    font-size: 1.2rem;
    margin: 0 0 10px 0;
    font-weight: 600;
}
.profile-bio-text-v2 {
    line-height: 1.7;
    color: var(--lighter-grey);
    white-space: pre-wrap;
    font-size: 0.95rem;
    padding: 15px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
}

/* Galeri ve Hangar Önizleme Stilleri */
.profile-preview-section {
    padding: 25px;
    border-radius: 10px;
    margin-top: 30px; /* Üstteki kartlardan ayır */
    border: 1px solid var(--darker-gold-2);

}
.profile-preview-section h4 {
    color: var(--light-gold);
    font-size: 1.3rem;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--darker-gold-2);
    font-weight: 600;
}
.profile-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); /* Küçük önizleme kartları */
    gap: 15px;
    margin-bottom: 15px;
}
.preview-item-card {
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--grey);
    aspect-ratio: 1 / 0.75; /* Kart oranı (en/boy) */
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.preview-item-card:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.preview-item-card a { text-decoration: none; display:flex; flex-direction:column; height:100%; }
.preview-image {
    width: 100%;
    height: 70%; /* Resim alanının yüksekliği */
    object-fit: cover;
    background-color: var(--black);
    border-bottom: 1px solid var(--grey);
}
.preview-info {
    padding: 8px 10px;
    text-align: center;
    font-size: 0.8rem;
    color: var(--light-grey);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-grow: 1; /* Kalan alanı doldur */
    display: flex;
    align-items: center;
    justify-content: center;
}
.preview-info .ship-quantity {
    font-size:0.9em; color:var(--gold); font-weight:bold; margin-left:5px;
}

.view-all-link {
    display: block;
    text-align: right;
    font-size: 0.9rem;
    margin-top: 10px;
}
.view-all-link a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
}
.view-all-link a:hover { text-decoration: underline; color: var(--light-turquase); }
.no-preview-items {
    font-size: 0.9rem;
    color: var(--light-grey);
    font-style: italic;
    text-align: center;
    padding: 15px;
}

/* Responsive Ayarlamalar */
@media (max-width: 992px) {
    .profile-grid-layout {
        grid-template-columns: 1fr; /* Tek sütun */
    }
    .profile-sidebar-v2 {
        position: static; /* Sabit kalmasın */
        margin-bottom: 30px;
        grid-row: 1; /* En üste al */
    }
    .profile-sidebar-avatar-section { padding-bottom: 15px; margin-bottom:15px;}
    .profile-main-avatar, .avatar-placeholder-profile { width:140px; height:140px; font-size:4rem;}
    .profile-sidebar-username { font-size:1.4rem;}
}
@media (max-width: 600px) {
    .profile-page-v2 { padding: 15px; }
    .profile-sidebar-v2, .profile-info-card-v2, .profile-activity-card-v2, .profile-preview-section { padding: 20px; }
    .profile-info-list-v2 li { flex-direction:column; align-items:flex-start; gap:3px;}
    .profile-info-list-v2 li strong.info-label { min-width:0; margin-bottom:2px; color:var(--gold);}
    .profile-preview-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>

<main>
    <div class="container profile-page-v2">
        <?php if ($profile_user_data): ?>
            <div class="profile-grid-layout">
                <aside class="profile-sidebar-v2">
                    <div class="profile-sidebar-avatar-section">
                        <?php if (!empty($profile_user_data['avatar_path'])): ?>
                            <img src="/public/<?php echo htmlspecialchars($profile_user_data['avatar_path']); ?>" alt="<?php echo htmlspecialchars($profile_user_data['username']); ?> Avatar" class="profile-main-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder-profile">
                                <?php echo strtoupper(substr(htmlspecialchars($profile_user_data['username']), 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <?php
                            $profile_username_color_class = '';
                            $profile_user_roles_arr = !empty($profile_user_data['user_roles_list']) ? explode(',', $profile_user_data['user_roles_list']) : [];
                            foreach ($role_priority as $p_role) {
                                if (in_array($p_role, $profile_user_roles_arr)) {
                                    $profile_username_color_class = 'username-role-' . $p_role;
                                    break;
                                }
                            }
                        ?>
                        <h2 class="profile-sidebar-username <?php echo $profile_username_color_class; ?> user-info-trigger"
                            data-user-id="<?php echo htmlspecialchars($profile_user_data['id']); ?>"
                            data-username="<?php echo htmlspecialchars($profile_user_data['username']); ?>"
                            data-avatar="<?php echo htmlspecialchars(!empty($profile_user_data['avatar_path']) ? ('/public/' . $profile_user_data['avatar_path']) : ''); ?>"
                            data-ingame="<?php echo htmlspecialchars($profile_user_data['ingame_name'] ?? 'N/A'); ?>"
                            data-discord="<?php echo htmlspecialchars($profile_user_data['discord_username'] ?? 'N/A'); ?>"
                            data-event-count="<?php echo htmlspecialchars($profile_user_data['total_event_count'] ?? '0'); ?>"
                            data-gallery-count="<?php echo htmlspecialchars($profile_user_data['total_gallery_count'] ?? '0'); ?>"
                            data-roles="<?php echo htmlspecialchars($profile_user_data['user_roles_list'] ?? ''); ?>">
                            <?php echo htmlspecialchars($profile_user_data['username']); ?>
                        </h2>
                        <p class="profile-sidebar-member-since">Üyelik Tarihi: <?php echo date('F Y', strtotime($profile_user_data['member_since'])); ?></p>
                        <?php if (!empty($profile_user_roles_arr)): ?>
                            <div class="profile-sidebar-roles">
                                <?php
                                $roleDisplayNames = ['admin' => 'Yönetici', 'member' => 'Üye', 'scg_uye' => 'SCG Üyesi', 'ilgarion_turanis' => 'Ilgarion Turanis', 'dis_uye' => 'Dış Üye'];
                                foreach ($profile_user_roles_arr as $role_key) {
                                    $displayName = $roleDisplayNames[$role_key] ?? ucfirst(str_replace('_', ' ', $role_key));
                                    echo '<span class="role-badge role-' . htmlspecialchars($role_key) . '">' . htmlspecialchars($displayName) . '</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <ul class="profile-sidebar-nav">
                        <?php if (is_user_logged_in() && $_SESSION['user_id'] == $profile_user_id): ?>
                            <li><a href="edit_profile.php" class="btn-edit-my-profile"><i class="fas fa-user-edit"></i> Profilimi Düzenle</a></li>
                            <li><a href="edit_hangar.php"><i class="fas fa-cogs"></i> Hangarımı Yönet</a></li>
                        <?php endif; ?>
                        <li><a href="user_gallery.php?user_id=<?php echo $profile_user_id; ?>"><i class="fas fa-images"></i> Kullanıcının Galerisi</a></li>
                        <li><a href="view_hangar.php?user_id=<?php echo $profile_user_id; ?>"><i class="fas fa-rocket"></i> Kullanıcının Hangarı</a></li>
                        <li><a href="user_discussions.php?user_id=<?php echo $profile_user_id; ?>"><i class="fas fa-comments"></i> Başlattığı Tartışmalar</a></li>
                        <?php if (is_user_admin()): ?>
                            <li><a href="<?php echo get_auth_base_url(); ?>/admin/users.php#user_<?php echo $profile_user_id; ?>" style="color: var(--gold);"><i class="fas fa-user-shield"></i> Admin: Kullanıcıyı Yönet</a></li>
                        <?php endif; ?>
                    </ul>
                </aside>

                <div class="profile-main-content-v2">
                    <div class="profile-info-card-v2">
                        <h3>Kullanıcı Detayları</h3>
                        <ul class="profile-info-list-v2">
                            <li>
                                <strong class="info-label">Oyun İçi İsim (RSI):</strong>
                                <span class="info-value <?php echo empty($profile_user_data['ingame_name']) ? 'not-specified' : ''; ?>">
                                    <?php echo htmlspecialchars($profile_user_data['ingame_name'] ?: '- Belirtilmemiş -'); ?>
                                </span>
                            </li>
                            <li>
                                <strong class="info-label">Discord:</strong>
                                <span class="info-value <?php echo empty($profile_user_data['discord_username']) ? 'not-specified' : ''; ?>">
                                    <?php if (!empty($profile_user_data['discord_username'])): ?>
                                        <span class="discord-info-v2"><i class="fab fa-discord"></i> <?php echo htmlspecialchars($profile_user_data['discord_username']); ?></span>
                                    <?php else: ?>
                                        - Belirtilmemiş -
                                    <?php endif; ?>
                                </span>
                            </li>
                             <li>
                                <strong class="info-label">Aktif Etkinlik Sayısı:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['active_event_count']); ?></span>
                            </li>
                            <li>
                                <strong class="info-label">Toplam Etkinlik Sayısı:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['total_event_count']); ?></span>
                            </li>
                            <li>
                                <strong class="info-label">Galerideki Fotoğraf Sayısı:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['total_gallery_count']); ?></span>
                            </li>
                        </ul>
                        <?php if (!empty($profile_user_data['profile_info'])): ?>
                            <div class="profile-bio-section-v2">
                                <h4>Hakkında</h4>
                                <p class="profile-bio-text-v2"><?php echo nl2br(htmlspecialchars($profile_user_data['profile_info'])); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="profile-bio-section-v2">
                                <h4>Hakkında</h4>
                                <p class="profile-bio-text-v2" style="font-style:italic; color:var(--light-grey);">Kullanıcı henüz bir profil açıklaması eklememiş.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($user_gallery_photos_preview)): ?>
                    <div class="profile-preview-section">
                        <h4>Son Galeri Fotoğrafları</h4>
                        <div class="profile-preview-grid">
                            <?php foreach ($user_gallery_photos_preview as $index => $photo): ?>
                                <div class="preview-item-card">
                                    <a href="javascript:void(0);" onclick="openGalleryModal(<?php echo $index; ?>)" title="<?php echo htmlspecialchars($photo['photo_description'] ?: 'Fotoğrafı görüntüle'); ?>">
                                        <img src="/public/<?php echo htmlspecialchars($photo['image_path']); ?>" alt="<?php echo htmlspecialchars($photo['photo_description'] ?: 'Galeri fotoğrafı'); ?>" class="preview-image">
                                        <?php if(!empty($photo['photo_description'])): ?>
                                            <span class="preview-info"><?php echo htmlspecialchars(mb_substr($photo['photo_description'],0,20).(mb_strlen($photo['photo_description']) > 20 ? '...' : '')); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($profile_user_data['total_gallery_count'] > count($user_gallery_photos_preview)): ?>
                            <p class="view-all-link"><a href="user_gallery.php?user_id=<?php echo $profile_user_id; ?>">Tüm Fotoğrafları Gör (<?php echo $profile_user_data['total_gallery_count']; ?>) &raquo;</a></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($user_hangar_ships_preview)): ?>
                    <div class="profile-preview-section">
                        <h4>Hangar Önizlemesi (İlk 5 Gemi)</h4>
                        <div class="profile-preview-grid">
                            <?php foreach ($user_hangar_ships_preview as $ship): ?>
                                <div class="preview-item-card">
                                    <a href="view_hangar.php?user_id=<?php echo $profile_user_id; ?>" title="<?php echo htmlspecialchars($ship['ship_name']); ?> hangarını gör">
                                        <img src="<?php echo htmlspecialchars($ship['ship_image_url'] ?: 'https://via.placeholder.com/150x90.png?text=' . urlencode($ship['ship_name'])); ?>" alt="<?php echo htmlspecialchars($ship['ship_name']); ?>" class="preview-image">
                                        <span class="preview-info">
                                            <?php echo htmlspecialchars($ship['ship_name']); ?>
                                            <span class="ship-quantity">(x<?php echo htmlspecialchars($ship['quantity']); ?>)</span>
                                        </span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                         <?php
                            $stmt_total_hangar_count = $pdo->prepare("SELECT COUNT(DISTINCT ship_api_id) FROM user_hangar WHERE user_id = ?");
                            $stmt_total_hangar_count->execute([$profile_user_id]);
                            $total_unique_ships_in_hangar = $stmt_total_hangar_count->fetchColumn();
                         ?>
                        <?php if ($total_unique_ships_in_hangar > count($user_hangar_ships_preview)): ?>
                            <p class="view-all-link"><a href="view_hangar.php?user_id=<?php echo $profile_user_id; ?>">Tam Hangarını Gör (<?php echo $total_unique_ships_in_hangar; ?> gemi türü) &raquo;</a></p>
                        <?php elseif ($total_unique_ships_in_hangar > 0) :?>
                             <p class="view-all-link"><a href="view_hangar.php?user_id=<?php echo $profile_user_id; ?>">Tam Hangarını Gör &raquo;</a></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php else: ?>
            <p class="empty-message" style="background-color: var(--transparent-red); color:var(--red); border-style:solid; border-color:var(--dark-red);">Profil bilgileri yüklenemedi veya kullanıcı bulunamadı.</p>
        <?php endif; ?>
    </div>
</main>

<?php // GALERİ MODAL HTML (footer.php'de zaten olmalı, ama bu sayfa galeriyi kullandığı için burada da referans verilebilir) ?>
<?php // Eğer footer.php'de #galleryModal tanımlıysa, buraya tekrar eklemeye gerek yok. ?>

<script>
// Bu sayfa galeri modalını kullandığı için, window.phpGalleryPhotos'u doldurmamız gerekiyor.
window.phpGalleryPhotos = <?php echo json_encode($gallery_photos_data_for_js_modal); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Popover script'i zaten footer.php'de genel olarak yükleniyor olmalı.
    // Modal açma/kapama ve navigasyon script'leri de (modals.js) genel olarak yükleniyor olmalı.
    // Bu sayfaya özel ek JS gerekirse buraya eklenebilir.
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>