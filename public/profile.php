<?php
// public/profile.php - Enhanced with Role & Permission System

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/formatting_functions.php';

// Temel yetki kontrolü - giriş yapmış ve onaylanmış kullanıcı olmalı
require_approved_user();

// Profil görüntüleme yetkisi kontrolü
require_permission($pdo, 'profile.view_own');

$page_title = "Profilim";
$user_id = $_SESSION['user_id'];

$profile_user_data = null;
$user_gallery_photos_preview = [];
$user_hangar_ships_preview = [];
$gallery_photos_data_for_js_modal = [];
$user_permissions = [];
$user_roles_detailed = [];
$user_hierarchy_info = [];

// Rol öncelik sırası (renklendirme için)
$role_priority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];

try {
    // === KULLANICI TEMEL BİLGİLERİ VE ROL/YETKİ BİLGİLERİ ===
    $sql_user = "
        SELECT 
            u.id, u.username, u.email, u.avatar_path, u.ingame_name, u.discord_username,
            u.profile_info, u.status, u.created_at AS member_since,
            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id AND status = 'active') AS active_event_count,
            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS total_event_count,
            (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS total_gallery_count,
            (SELECT COUNT(DISTINCT ur.role_id) FROM user_roles ur WHERE ur.user_id = u.id) AS total_roles_count,
            (SELECT MIN(r.priority) FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = u.id) AS highest_priority_level
        FROM users u
        WHERE u.id = :user_id AND u.status = 'approved'
    ";
    
    $stmt_user = execute_safe_query($pdo, $sql_user, [':user_id' => $user_id]);
    $profile_user_data = $stmt_user->fetch();

    if (!$profile_user_data) {
        $_SESSION['error_message'] = "Profil bilgilerinize erişilemedi.";
        header('Location: ' . get_auth_base_url() . '/index.php');
        exit;
    }

    // === KULLANICI ROLLERİNİ DETAYLI OLARAK ÇEK ===
    $user_roles_detailed = get_user_roles($pdo, $user_id);
    
    // === KULLANICI YETKİLERİNİ ÇEK ===
    if (is_super_admin($pdo, $user_id)) {
        // Süper admin ise tüm aktif yetkileri al
        $query_permissions = "SELECT permission_key FROM permissions WHERE is_active = 1 ORDER BY permission_group, permission_key";
        $stmt_permissions = execute_safe_query($pdo, $query_permissions);
        $user_permissions = $stmt_permissions->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Normal kullanıcı ise sadece sahip olduğu yetkileri al
        $query_permissions = "
            SELECT DISTINCT p.permission_key
            FROM user_roles ur 
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id AND p.is_active = 1
            ORDER BY p.permission_group, p.permission_key
        ";
        $stmt_permissions = execute_safe_query($pdo, $query_permissions, [':user_id' => $user_id]);
        $user_permissions = $stmt_permissions->fetchAll(PDO::FETCH_COLUMN);
    }

    // === HİYERARŞİ BİLGİLERİNİ HESAPLA ===
    $user_hierarchy_info = [
        'is_super_admin' => is_super_admin($pdo, $user_id),
        'is_admin' => is_admin($pdo, $user_id),
        'highest_priority' => $profile_user_data['highest_priority_level'] ?? 999,
        'total_permissions' => count($user_permissions),
        'can_access_admin_panel' => has_permission($pdo, 'admin.panel.access', $user_id),
        'can_manage_users' => has_permission($pdo, 'admin.users.view', $user_id),
        'can_manage_roles' => has_permission($pdo, 'admin.roles.view', $user_id),
        'can_create_events' => has_permission($pdo, 'event.create', $user_id),
        'can_upload_gallery' => has_permission($pdo, 'gallery.upload', $user_id),
        'can_create_guides' => has_permission($pdo, 'guide.create', $user_id),
        'can_manage_loadouts' => has_permission($pdo, 'loadout.manage_sets', $user_id)
    ];

    // === KULLANICI GALERİ FOTOĞRAFLARI (YETKİ KONTROLLÜ) ===
    if (has_permission($pdo, 'gallery.view_approved', $user_id)) {
        $gallery_query = "
            SELECT gp.id AS photo_id, gp.image_path, gp.description AS photo_description, gp.uploaded_at,
                   u_gal.id AS uploader_id, u_gal.username AS uploader_username, u_gal.avatar_path AS uploader_avatar_path,
                   u_gal.ingame_name AS uploader_ingame_name, u_gal.discord_username AS uploader_discord_username,
                   (SELECT COUNT(*) FROM events WHERE created_by_user_id = u_gal.id) AS uploader_event_count,
                   (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u_gal.id) AS uploader_gallery_count,
                   (SELECT GROUP_CONCAT(r_gal.name SEPARATOR ',') 
                    FROM user_roles ur_gal 
                    JOIN roles r_gal ON ur_gal.role_id = r_gal.id 
                    WHERE ur_gal.user_id = u_gal.id) AS uploader_roles_list,
                   (SELECT COUNT(*) FROM gallery_photo_likes gpl WHERE gpl.photo_id = gp.id) AS like_count,
                   (SELECT COUNT(*) FROM gallery_photo_likes gpl_user 
                    WHERE gpl_user.photo_id = gp.id AND gpl_user.user_id = :user_id_likes) AS user_has_liked
            FROM gallery_photos gp 
            JOIN users u_gal ON gp.user_id = u_gal.id
            WHERE gp.user_id = :profile_user_id 
              AND (gp.is_public_no_auth = 1 OR gp.is_members_only = 1)
            ORDER BY gp.uploaded_at DESC 
            LIMIT 5
        ";
        
        $stmt_gallery = execute_safe_query($pdo, $gallery_query, [
            ':profile_user_id' => $user_id,
            ':user_id_likes' => $user_id
        ]);
        $user_gallery_photos_preview = $stmt_gallery->fetchAll();

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
                'uploaded_at_formatted' => date('d M Y', strtotime($photo_item['uploaded_at'])),
                'like_count' => (int)($photo_item['like_count'] ?? 0),
                'user_has_liked' => (bool)($photo_item['user_has_liked'] ?? false)
            ];
        }
    }

    // === KULLANICI HANGAR GEMİLERİ (YETKİ KONTROLLÜ) ===
    if (has_permission($pdo, 'loadout.view_members_only', $user_id)) {
        $hangar_query = "
            SELECT ship_name, ship_image_url, quantity, ship_manufacturer, ship_focus, ship_size, added_at
            FROM user_hangar 
            WHERE user_id = :user_id 
            ORDER BY ship_name ASC 
            LIMIT 5
        ";
        $stmt_hangar_ships = execute_safe_query($pdo, $hangar_query, [':user_id' => $user_id]);
        $user_hangar_ships_preview = $stmt_hangar_ships->fetchAll();
    }

    // === GÜVENLİK AUDIT LOG ===
    audit_log($pdo, $user_id, 'profile_viewed', 'user', $user_id, null, [
        'user_roles_count' => count($user_roles_detailed),
        'permissions_count' => count($user_permissions),
        'is_super_admin' => $user_hierarchy_info['is_super_admin'],
        'highest_priority' => $user_hierarchy_info['highest_priority']
    ]);

} catch (SecurityException $e) {
    error_log("Güvenlik ihlali - Profil erişimi (User ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Güvenlik ihlali tespit edildi. Profil erişimi engellendi.";
    audit_log($pdo, $user_id, 'security_violation', 'profile_access', $user_id, null, [
        'error' => $e->getMessage(),
        'ip_address' => get_client_ip()
    ]);
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
} catch (DatabaseException $e) {
    error_log("Veritabanı hatası - Profil yükleme (User ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Profil bilgileri yüklenirken bir veritabanı hatası oluştu.";
} catch (Exception $e) {
    error_log("Genel hata - Profil yükleme (User ID: $user_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Profil bilgileri yüklenirken beklenmeyen bir hata oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Enhanced Profile Styles with Role/Permission Integration */
.profile-page-v2 {
    width: 100%;
    max-width: 1400px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 160px);
}

.profile-grid-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 30px;
}

.profile-sidebar-v2 {
    padding: 25px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    align-self: flex-start;
    position: sticky;
    top: calc(var(--navbar-height, 70px) + 30px);
}

/* Avatar and User Info Section */
.profile-sidebar-avatar-section {
    text-align: center;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--darker-gold-2);
}

.profile-main-avatar {
    width: 160px; 
    height: 160px; 
    border-radius: 50%; 
    object-fit: cover;
    border: 4px solid var(--gold); 
    box-shadow: 0 0 20px var(--transparent-gold);
    margin-bottom: 15px;
}

.avatar-placeholder-profile {
    width: 160px; 
    height: 160px; 
    border-radius: 50%;
    background-color: var(--grey); 
    color: var(--gold);
    display: flex; 
    align-items: center; 
    justify-content: center;
    font-size: 5rem; 
    font-weight: bold;
    border: 4px solid var(--darker-gold-1);
    margin: 0 auto 15px auto;
}

.profile-sidebar-username {
    color: var(--gold);
    font-size: 1.6rem; 
    font-weight: 600; 
    margin: 0 0 5px 0; 
    word-break: break-all;
}

/* Role-based username colors */
.profile-sidebar-username.username-role-admin { color: #bd912a !important; }
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

/* Enhanced Role Badges */
.role-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid;
}

.role-badge.role-admin {
    background: linear-gradient(135deg, #bd912a, #d4a74a);
    color: var(--charcoal);
    border-color: #bd912a;
    box-shadow: 0 2px 8px rgba(189, 145, 42, 0.3);
}

.role-badge.role-scg_uye {
    background: linear-gradient(135deg, #A52A2A, #cd5c5c);
    color: white;
    border-color: #A52A2A;
}

.role-badge.role-ilgarion_turanis {
    background: linear-gradient(135deg, var(--turquase), #5dbdb3);
    color: var(--charcoal);
    border-color: var(--turquase);
}

.role-badge.role-member {
    background: linear-gradient(135deg, var(--grey), var(--light-grey));
    color: var(--charcoal);
    border-color: var(--grey);
}

.role-badge.role-dis_uye {
    background: linear-gradient(135deg, #808080, #a0a0a0);
    color: white;
    border-color: #808080;
}

/* Hierarchy Info Display - Removed */

/* Navigation with Permission Checks */
.profile-sidebar-nav {
    list-style: none;
    padding: 0;
}

.profile-sidebar-nav li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 18px;
    color: var(--lighter-grey);
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
    border-bottom: 1px solid var(--darker-gold-2);
}

.profile-sidebar-nav li:last-child a {
    border-bottom: none;
}

.profile-sidebar-nav li a:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    padding-left: 23px;
    transform: translateX(3px);
}

.profile-sidebar-nav li a i.fas {
    width: 20px;
    text-align: center;
    color: var(--light-gold);
    font-size: 1.05em;
    transition: color 0.2s ease;
}

.profile-sidebar-nav li a:hover i.fas {
    color: var(--gold);
}

/* Special styling for admin panel link */
.profile-sidebar-nav .admin-panel-link {
    background: linear-gradient(135deg, rgba(189, 145, 42, 0.1), rgba(189, 145, 42, 0.05));
    border: 1px solid rgba(189, 145, 42, 0.3);
    color: var(--gold) !important;
}

.profile-sidebar-nav .admin-panel-link:hover {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--charcoal) !important;
}

.profile-sidebar-nav .admin-panel-link i {
    color: var(--gold) !important;
}

.profile-sidebar-nav .admin-panel-link:hover i {
    color: var(--charcoal) !important;
}

/* Edit Profile Button */
.profile-sidebar-nav .btn-edit-my-profile {
    color: var(--gold);
    text-align: center;
    justify-content: center;
    font-weight: 600;
    background-color: transparent;
}

.profile-sidebar-nav .btn-edit-my-profile:hover {
    background-color: var(--gold);
    color: var(--charcoal);
    padding-left: 18px;
}

.profile-sidebar-nav .btn-edit-my-profile:hover .fa-user-edit {
    color: var(--charcoal) !important;
}

.profile-sidebar-nav .fa-user-edit {
    color: var(--gold) !important;
}

/* Main Content Areas */
.profile-main-content-v2 {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.profile-info-card-v2, .profile-activity-card-v2, .profile-permissions-card-v2 {
    padding: 25px 30px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.profile-info-card-v2 h3, .profile-activity-card-v2 h3, .profile-permissions-card-v2 h3 {
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
    display: flex;
    padding: 10px 0;
    border-bottom: 1px dashed var(--darker-gold-2);
    font-size: 0.95rem;
    line-height: 1.6;
}

.profile-info-list-v2 li:last-child {
    border-bottom: none;
}

.profile-info-list-v2 li strong.info-label {
    color: var(--light-grey);
    font-weight: 500;
    min-width: 200px;
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

/* Discord Info Styling */
.discord-info-v2 .fab.fa-discord {
    color: #7289DA;
    margin-right: 6px;
}

/* Bio Section */
.profile-bio-section-v2 {
    margin-top: 20px;
}

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

/* Permissions Display - Removed for cleaner profile */

/* Preview Sections */
.profile-preview-section {
    padding: 25px;
    border-radius: 10px;
    border: 1px solid var(--darker-gold-2);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
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
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 18px;
    margin-bottom: 15px;
}

.preview-item-card {
    background-color: var(--darker-gold-2);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--grey);
    aspect-ratio: 1 / 0.8;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.preview-item-card:hover {
    transform: translateY(-4px) scale(1.04);
    box-shadow: 0 6px 15px rgba(189, 145, 42, 0.15);
    border-color: var(--darker-gold-1);
}

.preview-item-card a {
    text-decoration: none;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.preview-image {
    width: 100%;
    height: 75%;
    object-fit: cover;
    background-color: var(--black);
    border-bottom: 1px solid var(--grey);
}

.preview-info {
    padding: 10px;
    text-align: center;
    font-size: 0.85rem;
    color: var(--light-grey);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-grow: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-info .ship-quantity {
    font-size: 0.9em;
    color: var(--gold);
    font-weight: bold;
    margin-left: 5px;
}

.view-all-link {
    display: block;
    text-align: right;
    font-size: 0.95rem;
    margin-top: 10px;
}

.view-all-link a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 500;
}

.view-all-link a:hover {
    text-decoration: underline;
    color: var(--light-turquase);
}

.no-preview-items {
    font-size: 0.9rem;
    color: var(--light-grey);
    font-style: italic;
    text-align: center;
    padding: 15px;
}

/* Responsive Design */
@media (max-width: 992px) {
    .profile-grid-layout {
        grid-template-columns: 1fr;
    }
    
    .profile-sidebar-v2 {
        position: static;
        margin-bottom: 30px;
        grid-row: 1;
        top: 0;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .profile-page-v2 {
        padding: 2rem;
    }
    
    .profile-container {
        padding: 0 1rem;
    }
    
    .profile-info-list-v2 li {
        flex-direction: column;
        gap: 5px;
    }
    
    .profile-info-list-v2 li strong.info-label {
        min-width: auto;
        margin-right: 0;
    }
}

.profile-page-v2 .empty-message {
    text-align: center;
    font-size: 1.1rem;
    color: var(--red);
    padding: 30px 20px;
    background-color: var(--transparent-red);
    border: 1px solid var(--dark-red);
    border-radius: 6px;
}

/* Super Admin Badge */
.super-admin-badge {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 8px;
    margin-left: 8px;
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 0 5px rgba(255, 107, 107, 0.5); }
    to { box-shadow: 0 0 15px rgba(255, 107, 107, 0.8); }
}
</style>

<main>
    <div class="container profile-page-v2">
        <?php if ($profile_user_data): ?>
            <div class="profile-grid-layout">
                <aside class="profile-sidebar-v2">
                    <div class="profile-sidebar-avatar-section">
                        <?php if (!empty($profile_user_data['avatar_path'])): ?>
                            <img src="/public/<?php echo htmlspecialchars($profile_user_data['avatar_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($profile_user_data['username']); ?> Avatar" 
                                 class="profile-main-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder-profile">
                                <?php echo strtoupper(substr(htmlspecialchars($profile_user_data['username']), 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                        $profile_username_color_class = '';
                        $profile_user_roles_arr = [];
                        foreach ($user_roles_detailed as $role) {
                            $profile_user_roles_arr[] = $role['name'];
                        }
                        
                        // En yüksek öncelikli rolün rengini belirle
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
                            data-roles="<?php echo htmlspecialchars(implode(',', $profile_user_roles_arr)); ?>">
                            <?php echo htmlspecialchars($profile_user_data['username']); ?>
                            <?php if ($user_hierarchy_info['is_super_admin']): ?>
                                <span class="super-admin-badge">SÜPER ADMİN</span>
                            <?php endif; ?>
                        </h2>
                        
                        <p class="profile-sidebar-member-since">
                            Üyelik Tarihi: <?php echo date('F Y', strtotime($profile_user_data['member_since'])); ?>
                        </p>
                        
                        <?php if (!empty($user_roles_detailed)): ?>
                            <div class="profile-sidebar-roles">
                                <?php
                                $roleDisplayNames = [
                                    'admin' => 'Yönetici', 
                                    'member' => 'Üye', 
                                    'scg_uye' => 'SCG Üyesi', 
                                    'ilgarion_turanis' => 'Ilgarion Turanis', 
                                    'dis_uye' => 'Dış Üye'
                                ];
                                foreach ($user_roles_detailed as $role) {
                                    $role_key = $role['name'];
                                    $displayName = $roleDisplayNames[$role_key] ?? ucfirst(str_replace('_', ' ', $role_key));
                                    echo '<span class="role-badge role-' . htmlspecialchars($role_key) . '">' . htmlspecialchars($displayName) . '</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <!-- Hierarchy Information - Removed for cleaner profile view -->
                    </div>
                    
                    <ul class="profile-sidebar-nav">
                        <li>
                            <a href="edit_profile.php" class="btn-edit-my-profile">
                                <i class="fas fa-user-edit"></i> Profilimi Düzenle
                            </a>
                        </li>
                        
                        <?php if ($user_hierarchy_info['can_manage_loadouts']): ?>
                        <li>
                            <a href="edit_hangar.php">
                                <i class="fas fa-cogs"></i> Hangarımı Yönet
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($user_hierarchy_info['can_upload_gallery']): ?>
                        <li>
                            <a href="user_gallery.php?user_id=<?php echo $user_id; ?>">
                                <i class="fas fa-images"></i> Galerim
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li>
                            <a href="view_hangar.php?user_id=<?php echo $user_id; ?>">
                                <i class="fas fa-rocket"></i> Hangarım (Görünüm)
                            </a>
                        </li>
                        
                        <li>
                            <a href="user_discussions.php?user_id=<?php echo $user_id; ?>">
                                <i class="fas fa-comments"></i> Başlattığım Tartışmalar
                            </a>
                        </li>
                        
                        <?php if ($user_hierarchy_info['can_access_admin_panel']): ?>
                        <li>
                            <a href="<?php echo get_auth_base_url(); ?>/admin/index.php" class="admin-panel-link">
                                <i class="fas fa-shield-alt"></i> Admin Paneli
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </aside>

                <div class="profile-main-content-v2">
                    <!-- Genel Bilgiler -->
                    <div class="profile-info-card-v2">
                        <h3>Genel Bilgilerim</h3>
                        <ul class="profile-info-list-v2">
                            <li>
                                <strong class="info-label">E-posta Adresim:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['email'] ?? 'N/A'); ?></span>
                            </li>
                            <li>
                                <strong class="info-label">Oyun İçi İsmim (RSI):</strong>
                                <span class="info-value <?php echo empty($profile_user_data['ingame_name']) ? 'not-specified' : ''; ?>">
                                    <?php echo htmlspecialchars($profile_user_data['ingame_name'] ?: '- Belirtilmemiş -'); ?>
                                </span>
                            </li>
                            <li>
                                <strong class="info-label">Discord Kullanıcı Adım:</strong>
                                <span class="info-value <?php echo empty($profile_user_data['discord_username']) ? 'not-specified' : ''; ?>">
                                    <?php if (!empty($profile_user_data['discord_username'])): ?>
                                        <span class="discord-info-v2">
                                            <i class="fab fa-discord"></i> 
                                            <?php echo htmlspecialchars($profile_user_data['discord_username']); ?>
                                        </span>
                                    <?php else: ?>
                                        - Belirtilmemiş -
                                    <?php endif; ?>
                                </span>
                            </li>
                        </ul>
                        
                        <?php if (!empty($profile_user_data['profile_info'])): ?>
                            <div class="profile-bio-section-v2">
                                <h4>Hakkımda</h4>
                                <p class="profile-bio-text-v2"><?php echo nl2br(htmlspecialchars($profile_user_data['profile_info'])); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="profile-bio-section-v2">
                                <h4>Hakkımda</h4>
                                <p class="profile-bio-text-v2" style="font-style:italic; color:var(--light-grey);">
                                    Henüz bir profil açıklaması eklemedin. 
                                    <a href="edit_profile.php" style="color:var(--turquase);">Şimdi ekle!</a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Aktivitelerim -->
                    <div class="profile-activity-card-v2">
                        <h3>Aktivitelerim</h3>
                        <ul class="profile-info-list-v2">
                            <?php if ($user_hierarchy_info['can_create_events']): ?>
                            <li>
                                <strong class="info-label">Oluşturduğum Aktif Etkinlik Sayısı:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['active_event_count']); ?></span>
                            </li>
                            <li>
                                <strong class="info-label">Toplam Oluşturduğum Etkinlik:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['total_event_count']); ?></span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($user_hierarchy_info['can_upload_gallery']): ?>
                            <li>
                                <strong class="info-label">Galerideki Fotoğraf Sayım:</strong>
                                <span class="info-value"><?php echo htmlspecialchars($profile_user_data['total_gallery_count']); ?></span>
                            </li>
                            <?php endif; ?>
                            
                            <li>
                                <strong class="info-label">Toplam Rol Sayım:</strong>
                                <span class="info-value"><?php echo count($user_roles_detailed); ?></span>
                            </li>
                        </ul>
                    </div>

                    <!-- Rol ve Yetki Bilgileri bölümü kaldırıldı -->

                    <!-- Galeri Fotoğrafları (Yetki kontrollü) -->
                    <?php if (!empty($user_gallery_photos_preview) && $user_hierarchy_info['can_upload_gallery']): ?>
                    <div class="profile-preview-section">
                        <h4>Son Yüklediğim Fotoğraflar</h4>
                        <div class="profile-preview-grid">
                            <?php foreach ($user_gallery_photos_preview as $index => $photo): ?>
                                <div class="preview-item-card">
                                    <a href="javascript:void(0);" onclick="openGalleryModal(<?php echo $index; ?>)" 
                                       title="<?php echo htmlspecialchars($photo['photo_description'] ?: 'Fotoğrafı görüntüle'); ?>">
                                        <img src="/public/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($photo['photo_description'] ?: 'Galeri fotoğrafı'); ?>" 
                                             class="preview-image">
                                        <?php if(!empty($photo['photo_description'])): ?>
                                            <span class="preview-info">
                                                <?php echo htmlspecialchars(mb_substr($photo['photo_description'], 0, 20) . (mb_strlen($photo['photo_description']) > 20 ? '...' : '')); ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($profile_user_data['total_gallery_count']) && $profile_user_data['total_gallery_count'] > count($user_gallery_photos_preview)): ?>
                            <p class="view-all-link">
                                <a href="user_gallery.php?user_id=<?php echo $user_id; ?>">
                                    Tüm Fotoğraflarımı Gör (<?php echo $profile_user_data['total_gallery_count']; ?>) &raquo;
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Hangar Gemileri (Yetki kontrollü) -->
                    <?php if (!empty($user_hangar_ships_preview) && $user_hierarchy_info['can_manage_loadouts']): ?>
                    <div class="profile-preview-section">
                        <h4>Hangarım (İlk 5 Gemi)</h4>
                        <div class="profile-preview-grid">
                            <?php foreach ($user_hangar_ships_preview as $ship): ?>
                                <div class="preview-item-card">
                                    <a href="view_hangar.php?user_id=<?php echo $user_id; ?>" 
                                       title="<?php echo htmlspecialchars($ship['ship_name']); ?> hangarını gör">
                                        <img src="<?php echo htmlspecialchars($ship['ship_image_url'] ?: 'https://via.placeholder.com/150x90.png?text=' . urlencode($ship['ship_name'])); ?>" 
                                             alt="<?php echo htmlspecialchars($ship['ship_name']); ?>" 
                                             class="preview-image">
                                        <span class="preview-info">
                                            <?php echo htmlspecialchars($ship['ship_name']); ?>
                                            <span class="ship-quantity">(x<?php echo htmlspecialchars($ship['quantity']); ?>)</span>
                                        </span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $total_unique_ships_in_hangar_profile = 0;
                        if(isset($pdo)) {
                            $stmt_total_hangar_count_profile = execute_safe_query(
                                $pdo, 
                                "SELECT COUNT(DISTINCT ship_api_id) FROM user_hangar WHERE user_id = ?", 
                                [$user_id]
                            );
                            $total_unique_ships_in_hangar_profile = $stmt_total_hangar_count_profile->fetchColumn();
                        }
                        ?>
                        <?php if ($total_unique_ships_in_hangar_profile > count($user_hangar_ships_preview)): ?>
                            <p class="view-all-link">
                                <a href="view_hangar.php?user_id=<?php echo $user_id; ?>">
                                    Tam Hangarımı Gör (<?php echo $total_unique_ships_in_hangar_profile; ?> gemi türü) &raquo;
                                </a>
                            </p>
                        <?php elseif ($total_unique_ships_in_hangar_profile > 0): ?>
                             <p class="view-all-link">
                                <a href="view_hangar.php?user_id=<?php echo $user_id; ?>">Tam Hangarımı Gör &raquo;</a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php else: ?>
            <p class="empty-message">Profil bilgileri yüklenemedi.</p>
        <?php endif; ?>
    </div>
</main>

<script>
// Galeri modalı için JavaScript
window.phpGalleryPhotos = <?php echo json_encode($gallery_photos_data_for_js_modal); ?>;

// Profil sayfası için ek JavaScript işlevleri
document.addEventListener('DOMContentLoaded', function() {
    // Rol badge'larına hover efektleri
    const roleBadges = document.querySelectorAll('.role-badge');
    roleBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Permission kartlarına tıklama ile genişletme
    const permissionGroups = document.querySelectorAll('.permission-group');
    permissionGroups.forEach(group => {
        const title = group.querySelector('.permission-group-title');
        const list = group.querySelector('.permission-list');
        
        if (title && list) {
            title.style.cursor = 'pointer';
            title.addEventListener('click', function() {
                if (list.style.display === 'none') {
                    list.style.display = 'block';
                    this.style.color = 'var(--gold)';
                } else {
                    list.style.display = 'none';
                    this.style.color = 'var(--light-grey)';
                }
            });
        }
    });
    
    // Hierarchy info animasyonu
    const hierarchyInfo = document.querySelector('.profile-hierarchy-info');
    if (hierarchyInfo) {
        hierarchyInfo.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(42, 189, 168, 0.15)';
        });
        hierarchyInfo.addEventListener('mouseleave', function() {
            this.style.background = 'rgba(42, 189, 168, 0.1)';
        });
    }
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>