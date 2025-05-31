<?php
// public/members.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php'; 
require_once BASE_PATH . '/src/functions/auth_functions.php'; 
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // YENİ FONKSİYONU İÇEREN DOSYA

if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity(); 
    }
}

$can_view_list = false;
$is_viewer_dis_uye = false;
$current_user_roles = is_user_logged_in() ? ($_SESSION['user_roles'] ?? []) : [];
$access_message_members = "";

if (is_user_logged_in() && is_user_approved()) {
    if (user_has_any_role(['admin', 'ilgarion_turanis', 'scg_uye', 'member'])) {
        $can_view_list = true;
    } elseif (in_array('dis_uye', $current_user_roles) && count($current_user_roles) === 1) {
        // Sadece 'dis_uye' ise, listeyi görebilir ama filtre 'dis_uye' olur.
        $is_viewer_dis_uye = true;
        $can_view_list = true; 
    } else {
        $access_message_members = "Üye listesini görüntüleme yetkiniz bulunmamaktadır.";
    }
} elseif (is_user_logged_in() && !is_user_approved()) {
    $access_message_members = "Üye listesini görüntüleyebilmek için hesabınızın onaylanmış olması gerekmektedir.";
} else { // Giriş yapılmamış
    $access_message_members = "Üye listesini görmek ve üyelerimizle tanışmak için lütfen <a href='" . get_auth_base_url() . "/login.php' style='color: var(--turquase); font-weight: bold;'>giriş yapın</a> ya da <a href='" . get_auth_base_url() . "/register.php' style='color: var(--turquase); font-weight: bold;'>kayıt olun</a>.";
    // Giriş yapmamış kullanıcılar için $can_view_list false kalacak ve aşağıdaki HTML'de mesaj gösterilecek.
}


$page_title = "Üye Listesi";
$approved_members_with_details = []; 
$search_term = trim($_GET['search_term'] ?? ''); 
$role_filter_from_get = trim($_GET['role_filter'] ?? 'all'); 

// $role_priority global olarak auth_functions.php veya config içinde tanımlı olmalı.
// Eğer formatting_functions.php içinde fallback varsa, burada tekrar tanımlamaya gerek yok.
// global $role_priority; // Eğer formatting_functions.php'de global olarak kullanılıyorsa

$role_display_names_map = [ 
    'admin' => 'Yöneticiler',
    'member' => 'Üyeler',
    'scg_uye' => 'SCG Üyeleri',
    'ilgarion_turanis' => 'Ilgarion Turanis',
    'dis_uye' => 'Dış Üyeler'
];
$role_icons_map = [ 
    'admin' => 'fas fa-user-shield',
    'member' => 'fas fa-user',
    'scg_uye' => 'fas fa-users-cog',
    'ilgarion_turanis' => 'fas fa-dragon',
    'dis_uye' => 'fas fa-user-tag'
];

$available_roles_for_filter = [];
if (isset($pdo)) { 
    if (is_user_admin()) {
        $stmt_all_roles = $pdo->query("SELECT name FROM roles ORDER BY FIELD(name, '".implode("','", ($role_priority ?? ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye']))."') , name ASC");
        if($stmt_all_roles) $available_roles_for_filter = $stmt_all_roles->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($can_view_list && !$is_viewer_dis_uye) { 
        $stmt_core_roles = $pdo->prepare("SELECT name FROM roles WHERE name != 'dis_uye' ORDER BY FIELD(name, '".implode("','", ($role_priority ?? ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye']))."') , name ASC");
        if($stmt_core_roles) {
            $stmt_core_roles->execute();
            $available_roles_for_filter = $stmt_core_roles->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}

// $can_view_list true ise ve $pdo tanımlıysa sorguyu çalıştır.
// Eğer $can_view_list false ise, $approved_members_with_details boş kalacak ve yukarıdaki $access_message_members gösterilecek.
if ($can_view_list && isset($pdo)) {
    try {
        // SQL sorgusunda user_event_count ve user_gallery_count isimlerini render_user_info_with_popover fonksiyonunun
        // beklediği alternatif isimlerle eşleştirmek için alias kullanabiliriz veya fonksiyonda daha fazla kontrol ekleyebiliriz.
        // Fonksiyona zaten bu alternatifleri kontrol etme özelliği ekledik.
        $base_sql_select = "SELECT u.id, u.username, u.ingame_name, u.discord_username, u.avatar_path,
                            (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS user_event_count,
                            (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS user_gallery_count,
                            GROUP_CONCAT(DISTINCT r.name SEPARATOR ',') AS user_roles_list";
        $base_sql_from = "FROM users u 
                          LEFT JOIN user_roles ur ON u.id = ur.user_id 
                          LEFT JOIN roles r ON ur.role_id = r.id";
        $base_sql_where_conditions = ["u.status = 'approved'"];
        $sql_group_by = "GROUP BY u.id";
        $sql_order_by = "ORDER BY u.username ASC";
        
        $params = [];

        if (!empty($search_term)) {
            $base_sql_where_conditions[] = "(u.username LIKE :search_username OR u.ingame_name LIKE :search_ingame_name OR u.discord_username LIKE :search_discord)";
            $params[':search_username'] = '%' . $search_term . '%';
            $params[':search_ingame_name'] = '%' . $search_term . '%';
            $params[':search_discord'] = '%' . $search_term . '%';
        }
        
        $effective_role_filter = $role_filter_from_get;
        if ($is_viewer_dis_uye) {
            $effective_role_filter = 'dis_uye'; 
        }

        if ($effective_role_filter !== 'all') {
            $base_sql_where_conditions[] = "EXISTS (
                                                SELECT 1 
                                                FROM user_roles ur_filter 
                                                JOIN roles r_filter ON ur_filter.role_id = r_filter.id 
                                                WHERE ur_filter.user_id = u.id AND r_filter.name = :role_filter_val
                                            )";
            $params[':role_filter_val'] = $effective_role_filter;
        }

        $sql = $base_sql_select . " " . $base_sql_from . " WHERE " . implode(" AND ", $base_sql_where_conditions) . " " . $sql_group_by . " " . $sql_order_by;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $approved_members_with_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Üye listesi çekme hatası (members.php): " . $e->getMessage());
        if (session_status() == PHP_SESSION_NONE) session_start();
        $_SESSION['error_message'] = "Üye listesi yüklenirken bir veritabanı sorunu oluştu.";
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* public/members.php için Stiller (önceki yanıttaki gibi) */
:root {
    --role-color-admin: var(--gold);
    --role-color-member: var(--lighter-grey);
    --role-color-scg_uye: #A52A2A; 
    --role-color-ilgarion_turanis: var(--turquase);
    --role-color-dis_uye: var(--light-grey);
    --filter-pill-bg: var(--grey);
    --filter-pill-text: var(--lighter-grey);
    --filter-pill-border: var(--darker-gold-1);
    --filter-pill-hover-bg: var(--darker-gold-1);
    --filter-pill-hover-text: var(--gold);
    --filter-pill-active-bg: var(--gold);
    --filter-pill-active-text: var(--darker-gold-2);
    --filter-pill-active-border: var(--gold);
}
/* Diğer CSS stilleri (önceki yanıttaki gibi) buraya eklenecek... */
/* ... (members.php için olan tüm CSS stillerini buraya kopyalayın) ... */
.members-page-container-v2 { 
    width: 100%;
    max-width: 1600px; 
    margin: 30px auto;
    padding: 25px 35px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 160px);
}
.page-header-members {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px; 
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.page-header-members .page-title-members {
    color: var(--gold);
    font-size: 2.2rem;
    font-family: var(--font);
    margin: 0;
    flex-grow: 1;
}
.members-page-description {
    font-size: 0.95rem;
    color: var(--light-grey);
    line-height: 1.6;
    margin-bottom: 30px;
    padding: 15px 20px;
    background-color: rgba(var(--darker-gold-2-rgb, 26, 17, 1), 0.5); 
    border-left: 4px solid var(--gold);
    border-radius: 0 8px 8px 0;
}
.members-page-description strong { color: var(--light-gold); }
.members-page-description a { color: var(--turquase); font-weight: 600; text-decoration: none; }
.members-page-description a:hover { text-decoration: underline; }

.members-page-description a { color: var(--turquase); font-weight: 600; text-decoration: none; }
    display: flex;
    flex-direction: column; 
    gap: 20px;
    margin-bottom: 25px;
}
.member-search-form-v2 {
    padding: 0; 
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.member-search-form-v2 .form-group { flex-grow: 1; margin-bottom: 0; position:relative; min-width:280px;}
.member-search-form-v2 label { display: none; }
.member-search-form-v2 input[type="text"] {
    width: 100%;
    padding: 12px 45px 12px 20px; 
    border: 1px solid var(--darker-gold-1);
    border-radius: 30px; 
    color: var(--gold);
    font-size: 0.95rem; 
    font-family: var(--font);
    transition: all 0.3s ease;
    background-color: transparent;
}
.member-search-form-v2 input[type="text"]::placeholder { color: var(--light-grey); opacity: 0.6; }
.member-search-form-v2 input[type="text"]:focus { 
    outline: none; 
    border-color: var(--gold); 
}
.member-search-form-v2 .btn-search-members {
    position: absolute;
    right: 8px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none;
    color: var(--gold); padding: 9px; font-size: 1.2rem; cursor: pointer;
    border-radius: 50%; width:36px; height:36px; 
    display:flex; align-items:center; justify-content:center;
    transition: background-color 0.2s ease;
}
.member-search-form-v2 .btn-search-members:hover { background-color: var(--transparent-gold); }
.member-search-form-v2 .btn-clear-search-members {
    padding: 12px 22px; font-size: 0.9rem; border-radius: 30px; 
    background-color: var(--grey); color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1); font-weight:500;
    transition: all 0.2s ease;
}
.member-search-form-v2 .btn-clear-search-members:hover { 
    background-color: var(--darker-gold-1); color: var(--white); transform: translateY(-1px);
}
.member-role-filters { 
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 10px 0; 
    border-top: 1px solid var(--darker-gold-2); 
    margin-top: 15px;
}
.member-role-filters .filter-pill-btn {
    padding: 8px 18px;
    font-size: 0.85rem;
    font-weight: 500;
    border-radius: 20px; 
    text-decoration: none;
    border: 1px solid var(--filter-pill-border);
    background-color: var(--filter-pill-bg);
    color: var(--filter-pill-text);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.member-role-filters .filter-pill-btn:hover {
    background-color: var(--filter-pill-hover-bg);
    color: var(--filter-pill-hover-text);
    transform: translateY(-1px);
}
.member-role-filters .filter-pill-btn.active {
    background-color: var(--filter-pill-active-bg);
    color: var(--filter-pill-active-text);
    border-color: var(--filter-pill-active-border);
    box-shadow: 0 1px 5px rgba(var(--gold-rgb), 0.3);
}
.member-role-filters .filter-pill-btn i.fas { font-size: 0.9em; }
.member-list-info-bar { 
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 8px 12px;
    background-color: rgba(var(--darker-gold-2-rgb), 0.3);
    border-radius: 6px;
    font-size: 0.9em;
    color: var(--light-grey);
}
.member-list-info-bar strong { color: var(--lighter-grey); }
.empty-message-members { 
    text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px;
    background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1);
    margin-top: 20px;
}
.empty-message-members a { color:var(--turquase); font-weight:bold; text-decoration:none; margin-top:10px; display:inline-block;}
.empty-message-members a:hover { text-decoration: underline; }
.members-table-wrapper-v2 {
    overflow-x: auto; 
    background-color: var(--charcoal);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}
.members-table-v2 {
    width: 100%;
    min-width: 850px; 
    border-collapse: collapse; 
}
.members-table-v2 th,
.members-table-v2 td {
    padding: 12px 15px; 
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2); 
    font-size: 0.9rem; 
    color: var(--lighter-grey);
    white-space: nowrap; 
    vertical-align: middle; 
}
.members-table-v2 thead th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem; 
    letter-spacing: 0.5px;
}
.members-table-v2 thead th.actions-column-header { 
    min-width: 280px; 
    text-align: right;
}
.members-table-v2 tbody tr { transition: background-color 0.2s ease; }
.members-table-v2 tbody tr:hover { background-color: var(--darker-gold-2); }
.members-table-v2 tbody tr:last-child td { border-bottom: none; }
.member-info-cell { 
    display: flex; 
    align-items: center;
    gap: 10px; 
    cursor: default; 
}
.member-avatar-thumbnail { 
    width: 36px; 
    height: 36px; 
    border-radius: 50%; 
    object-fit: cover; 
    border: 2px solid var(--darker-gold-1); 
    flex-shrink: 0; 
}
.avatar-placeholder.small-placeholder { 
    width: 36px; 
    height: 36px; 
    font-size: 1rem; 
    background-color: var(--grey); color: var(--gold); 
    display: flex; align-items: center; justify-content: center; 
    font-weight: bold; border: 2px solid var(--darker-gold-1); 
    flex-shrink: 0; line-height: 1; border-radius:50%;
}
.member-username-link {
    color: inherit !important; 
    text-decoration: none;
    font-weight: 600; 
    font-size: 0.95rem; 
}
.member-username-link:hover { text-decoration: underline; }
.member-info-cell.username-role-admin a.member-username-link, .member-info-cell.username-role-admin .member-username-link { color: var(--role-color-admin) !important; }
.member-info-cell.username-role-scg_uye a.member-username-link, .member-info-cell.username-role-scg_uye .member-username-link { color: var(--role-color-scg_uye) !important; }
.member-info-cell.username-role-ilgarion_turanis a.member-username-link, .member-info-cell.username-role-ilgarion_turanis .member-username-link { color: var(--role-color-ilgarion_turanis) !important; }
.member-info-cell.username-role-member a.member-username-link, .member-info-cell.username-role-member .member-username-link { color: var(--role-color-member) !important; }
.member-info-cell.username-role-dis_uye a.member-username-link, .member-info-cell.username-role-dis_uye .member-username-link { color: var(--role-color-dis_uye) !important; }
.members-table-v2 td.discord-cell { 
    color: var(--light-grey);
    font-style: italic;
}
.members-table-v2 td.discord-cell .fab.fa-discord { 
    color: #7289DA; 
    margin-right: 6px;
    font-size: 1.1em;
}
.actions-cell { text-align: right !important; white-space: nowrap; }
.actions-cell .btn { 
    margin-left: 8px; 
    padding: 7px 16px; 
    font-size: 0.82rem; 
    font-weight: 500; 
    border-radius: 20px; 
    text-decoration: none;  
    transition: opacity 0.2s ease, transform 0.2s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease; 
    border-width: 1px;
    border-style: solid;
    display: inline-flex; 
    align-items: center;
    gap: 6px; 
}
.actions-cell .btn:hover{ opacity:0.9; transform: translateY(-1px); }
.actions-cell .btn:first-child { margin-left:0;}
.actions-cell .btn i.fas, .actions-cell .btn i.fab { font-size: 0.9em; }
.btn-view-profile-pill { 
    background-color: var(--turquase); color: var(--black); border-color:var(--turquase);
}
.btn-view-profile-pill:hover { 
    background-color: var(--light-turquase); color: var(--darker-gold-2); border-color:var(--light-turquase);
}
.btn-view-gallery-pill { 
    background-color: var(--light-gold); color: var(--darker-gold-2); border-color:var(--light-gold);
}
.btn-view-gallery-pill:hover { 
    background-color: var(--gold); color: var(--black); border-color:var(--gold);
}
.btn-view-hangar-pill { 
    background-color: var(--grey); color: var(--lighter-grey); border-color:var(--grey);
}
.btn-view-hangar-pill:hover { 
    background-color: var(--darker-gold-1); color: var(--white); border-color:var(--darker-gold-1);
}
@media (max-width: 768px) {
    .members-page-container-v2 { padding: 20px; }
    .page-header-members { flex-direction: column; align-items: stretch; gap:15px; }
    .page-header-members .page-title-members { font-size: 2rem; text-align:center; }
    .member-controls-bar { gap: 15px; }
    .member-search-form-v2 { flex-direction:column; align-items:stretch;}
    .members-page-description { font-size:0.9rem; padding:12px 15px;}
    .member-list-info-bar { flex-direction:column; align-items:flex-start; gap:5px;}
    .member-role-filters { justify-content:center; } 
    .members-table-v2 th, .members-table-v2 td { padding: 10px 8px; font-size:0.85rem; white-space:normal; }
    .members-table-v2 td.actions-cell { white-space:normal; } 
    .actions-cell .btn { padding: 7px 12px; font-size:0.78rem;} 
    .actions-cell { display:flex; flex-direction:column; gap:8px; align-items:stretch; text-align:center !important;}
    .actions-cell .btn { width:100%; margin-left:0;}
}
</style>
<main>
    <div class="container members-page-container-v2">
        <div class="page-header-members">
            <h1 class="page-title-members"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <?php if (!$can_view_list): ?>
            <?php if (!is_user_logged_in()): ?>
                <p class="members-page-description" style="text-align:center; border-left-color: var(--turquase);">
                    Topluluğumuzun değerli üyelerini görmek ve onlarla etkileşime geçmek için lütfen 
                    <a href="<?php echo get_auth_base_url(); ?>/login.php">giriş yapın</a> veya 
                    <a href="<?php echo get_auth_base_url(); ?>/register.php">aramıza katılın</a>!
                </p>
            <?php else: ?>
                <p class="members-page-description" style="text-align:center; border-left-color: var(--light-gold);">
                    Üye listesini görüntüleyebilmek için hesabınızın onaylanmış ve uygun yetkilere sahip olması gerekmektedir. 
                    <?php if (!is_user_approved()): ?>
                        Hesabınız henüz onay bekliyor. Anlayışınız için teşekkür ederiz.
                    <?php elseif (user_has_role('dis_uye') && !$is_viewer_dis_uye): // Bu durum artık $can_view_list false ise oluşmamalı ama kontrol olarak kalsın
                    ?>
                        Mevcut 'Dış Üye' rolünüzle bu bölümü görüntüleyemezsiniz. 
                    <?php else: ?>
                        Bu bölümü görüntülemek için gerekli yetkiye sahip değilsiniz.
                    <?php endif; ?>
                    <br>Daha fazla bilgi için yöneticilerle iletişime geçebilir veya 
                    <a href="<?php echo get_auth_base_url(); ?>/index.php">ana sayfaya dönebilirsiniz</a>.
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p class="members-page-description">
                <strong>Ilgarion Turanis</strong> ailesinin aktif ve değerli üyeleri aşağıda listelenmiştir. 
                Bu liste, filomuzun gücünü ve çeşitliliğini yansıtmaktadır. 
                Üyelerimizin profillerini ziyaret ederek oyun içi isimleri, Discord kullanıcı adları ve topluluk içindeki diğer etkileşimleri hakkında daha fazla bilgi edinebilirsiniz. 
                Arama çubuğunu veya rol filtrelerini kullanarak belirli üyeleri hızlıca bulabilirsiniz.
            </p>

            <div class="member-controls-bar">
                <form method="GET" action="members.php" class="member-search-form-v2">
                    <div class="form-group">
                        <label for="search_term">Üye Ara</label>
                        <input type="text" id="search_term" name="search_term" class="form-control" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Kullanıcı Adı, Oyun İçi Ad veya Discord Adı...">
                        <button type="submit" class="btn-search-members" aria-label="Ara"><i class="fas fa-search"></i></button>
                    </div>
                    <?php if (!empty($search_term) || ($role_filter_from_get !== 'all' && !$is_viewer_dis_uye) ): ?>
                        <a href="members.php" class="btn btn-clear-search-members">Tüm Filtreleri Temizle</a>
                    <?php endif; ?>
                </form>

                <?php if (!empty($available_roles_for_filter) && !$is_viewer_dis_uye): ?>
                    <div class="member-role-filters">
                        <a href="members.php?search_term=<?php echo urlencode($search_term); ?>" 
                           class="filter-pill-btn <?php echo ($role_filter_from_get === 'all' ? 'active' : ''); ?>">
                           <i class="fas fa-users"></i> Tümü
                        </a>
                        <?php foreach ($available_roles_for_filter as $role_name_filter): ?>
                            <?php
                                $display_name_filter = $role_display_names_map[$role_name_filter] ?? ucfirst(str_replace('_', ' ', $role_name_filter));
                                $icon_filter = $role_icons_map[$role_name_filter] ?? 'fas fa-user-tag';
                            ?>
                            <a href="members.php?role_filter=<?php echo urlencode($role_name_filter); ?>&search_term=<?php echo urlencode($search_term); ?>" 
                               class="filter-pill-btn <?php echo ($role_filter_from_get === $role_name_filter ? 'active' : ''); ?>">
                               <i class="<?php echo $icon_filter; ?>"></i> <?php echo htmlspecialchars($display_name_filter); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <p class="empty-message-members" style="background-color: var(--transparent-red); color:var(--red); border-style:solid; border-color:var(--dark-red);"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
            <?php endif; ?>

            <?php if (empty($approved_members_with_details) && !isset($_SESSION['error_message'])): ?>
                <p class="empty-message-members">
                    <?php if (!empty($search_term) && $role_filter_from_get !== 'all'): ?>
                        "<strong><?php echo htmlspecialchars($search_term); ?></strong>" araması ile "<strong><?php echo htmlspecialchars($role_display_names_map[$role_filter_from_get] ?? $role_filter_from_get); ?></strong>" rolünde üye bulunamadı.
                    <?php elseif (!empty($search_term)): ?>
                        "<strong><?php echo htmlspecialchars($search_term); ?></strong>" ile eşleşen üye bulunamadı.
                    <?php elseif ($role_filter_from_get !== 'all'): ?>
                         "<strong><?php echo htmlspecialchars($role_display_names_map[$role_filter_from_get] ?? $role_filter_from_get); ?></strong>" rolünde üye bulunamadı.
                    <?php elseif ($is_viewer_dis_uye): ?>
                        Listelenecek başka dış üye bulunmamaktadır.
                    <?php else: ?>
                        Listelenecek onaylanmış üye bulunmamaktadır.
                    <?php endif; ?>
                     <br><a href="members.php">Tüm Filtreleri Temizle</a>
                </p>
            <?php elseif (!empty($approved_members_with_details)): ?>
                <div class="member-list-info-bar">
                    <span>
                        Toplam <strong><?php echo count($approved_members_with_details); ?></strong> üye bulundu
                        <?php 
                        if ($is_viewer_dis_uye) {
                            echo " (Sadece Dış Üyeler)";
                        } elseif ($role_filter_from_get !== 'all') {
                            echo " (Rol: <strong>" . htmlspecialchars($role_display_names_map[$role_filter_from_get] ?? $role_filter_from_get) . "</strong>)";
                        }
                        if (!empty($search_term)) {
                            echo " / Arama: <strong>'".htmlspecialchars($search_term)."'</strong>";
                        }
                        ?>.
                    </span>
                </div>
                <div class="members-table-wrapper-v2">
                    <table class="members-table-v2">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Oyun İçi İsim (RSI)</th>
                                <th>Discord</th>
                                <th class="actions-column-header">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approved_members_with_details as $member): ?>
                                <tr>
                                    <td>
                                        <?php
                                        // render_user_info_with_popover fonksiyonunu çağırıyoruz.
                                        // $member dizisi, fonksiyonun beklediği tüm anahtarları içermeli.
                                        // SQL sorgusunda 'user_event_count' ve 'user_gallery_count' gibi
                                        // alan adlarının fonksiyon beklentileriyle eşleştiğinden emin olun.
                                        // (Fonksiyon içinde bu farklı isimler için kontroller ekledik)
                                        echo render_user_info_with_popover(
                                            $member,
                                            'member-username-link',     // Link için class
                                            'member-avatar-thumbnail',  // Avatar için class
                                            'member-info-cell'          // Wrapper için ek class (önceden <span>'e atanıyordu)
                                        );
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['ingame_name'] ?: '-'); ?></td>
                                    <td class="discord-cell">
                                        <?php if (!empty($member['discord_username'])): ?>
                                            <i class="fab fa-discord"></i> <?php echo htmlspecialchars($member['discord_username']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell"> 
                                        <a href="view_profile.php?user_id=<?php echo htmlspecialchars($member['id'] ?? '0'); ?>" class="btn btn-view-profile-pill" title="Profili Gör"><i class="fas fa-user-circle"></i> Profil</a>
                                        <a href="user_gallery.php?user_id=<?php echo htmlspecialchars($member['id'] ?? '0'); ?>" class="btn btn-view-gallery-pill" title="Galerisi"><i class="fas fa-images"></i> Galeri</a>
                                        <a href="view_hangar.php?user_id=<?php echo htmlspecialchars($member['id'] ?? '0'); ?>" class="btn btn-view-hangar-pill" title="Hangarı"><i class="fas fa-rocket"></i> Hangar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
