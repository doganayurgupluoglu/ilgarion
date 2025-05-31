<?php
// public/user_discussions.php

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_login(); // Giriş yapmış olmak yeterli, onay durumu aşağıda profil sahibi için esnetilebilir.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu sayfayı görmek için giriş yapmalısınız.";
    header('Location: ' . get_auth_base_url() . '/login.php');
    exit;
}
// Oturum geçerliliğini kontrol et
if (function_exists('check_user_session_validity')) {
    check_user_session_validity();
}
// Sayfayı görüntüleyen kullanıcının en azından onaylı olması genel bir kural olabilir,
// ancak kendi profilinin konularını görüyorsa bu esnetilebilir.
// Şimdilik, profil sahibinin onay durumuna göre erişim kontrolü aşağıda yapılıyor.

$profile_user_id = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $profile_user_id = (int)$_GET['user_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si.";
    header('Location: ' . get_auth_base_url() . '/members.php');
    exit;
}

$viewed_user_data = null;
$user_started_topics = [];
$page_title = "Kullanıcı Tartışmaları";

$role_priority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];
$current_user_id_for_list = $_SESSION['user_id'];

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

    // Görüntülenen kullanıcının profilinin erişilebilir olup olmadığını kontrol et
    $can_view_profile_owner_page = false;
    if ($viewed_user_data) {
        if ($viewed_user_data['status'] === 'approved') {
            $can_view_profile_owner_page = true;
        } elseif (is_user_logged_in() && ($_SESSION['user_id'] == $profile_user_id || is_user_admin())) {
            // Kendi profilini veya admin ise onaylanmamış profili görebilir
            $can_view_profile_owner_page = true;
        }
    }

    if (!$can_view_profile_owner_page) {
        $_SESSION['error_message'] = "Kullanıcı bulunamadı veya bu kullanıcının tartışmalarını görüntüleme yetkiniz yok.";
        header('Location: ' . get_auth_base_url() . '/members.php');
        exit;
    }
    $page_title = htmlspecialchars($viewed_user_data['username']) . " Kullanıcısının Başlattığı Tartışmalar";

    // Konuları çekerken, görüntüleyen kullanıcının yetkilerine göre filtreleme ekleyelim
    // Bu kısım discussions.php'deki mantığa benzeyecek
    $sql_topics_base = "SELECT 
                dt.id, dt.title, dt.created_at AS topic_created_at, dt.last_reply_at,
                dt.reply_count, dt.is_locked, dt.is_public_no_auth, dt.is_members_only,
                (SELECT u_replier.username 
                 FROM discussion_posts dp_last
                 JOIN users u_replier ON dp_last.user_id = u_replier.id
                 WHERE dp_last.topic_id = dt.id 
                 ORDER BY dp_last.created_at DESC LIMIT 1) AS last_replier_username,
                utv.last_viewed_at
            FROM discussion_topics dt
            LEFT JOIN user_topic_views utv ON dt.id = utv.topic_id AND utv.user_id = :current_user_id
            WHERE dt.user_id = :profile_user_id";
    
    $visibility_conditions = [];
    $params_topics = [
        ':current_user_id' => $current_user_id_for_list,
        ':profile_user_id' => $profile_user_id
    ];

    if (is_user_admin() || has_permission($pdo, 'discussion.view_all', $current_user_id_for_list)) {
        // Admin veya view_all yetkisi olan her şeyi görür (profile_user_id filtresi zaten var)
    } else {
        $visibility_conditions[] = "dt.is_public_no_auth = 1"; // Herkesin görebileceği public konular
        if (is_user_approved()) { // Sadece onaylı kullanıcılar için ek koşullar
            $visibility_conditions[] = "dt.is_members_only = 1"; // Üyelere özel konular
            
            // Kullanıcının rollerini al
            $stmt_user_roles_page = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = :uid_for_roles");
            $stmt_user_roles_page->execute([':uid_for_roles' => $current_user_id_for_list]);
            $current_user_role_ids_page = $stmt_user_roles_page->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($current_user_role_ids_page)) {
                $role_placeholders = implode(',', array_fill(0, count($current_user_role_ids_page), '?'));
                $visibility_conditions[] = "(dt.is_public_no_auth = 0 AND dt.is_members_only = 0 AND EXISTS (
                                                SELECT 1 FROM discussion_topic_visibility_roles dtr 
                                                WHERE dtr.topic_id = dt.id AND dtr.role_id IN ($role_placeholders)
                                            ))";
                foreach ($current_user_role_ids_page as $role_id_param) {
                    $params_topics[] = $role_id_param; // Parametreleri PDO için doğru şekilde ekle
                }
            }
        }
        // Eğer profil sahibi kendi konularını görüyorsa, tüm kendi konularını görmeli (yukarıdaki admin/view_all durumu bunu zaten kapsar)
        // Ancak, eğer profil sahibi admin değilse ve kendi konularını görüyorsa, yukarıdaki filtreler uygulanır.
        // Bu, kullanıcının kendi başlattığı ama artık görme yetkisi olmayan (örn. rolü değişti) konuları görmesini engeller.
        // Eğer kendi başlattığı her şeyi görmesi isteniyorsa, bu filtreleme mantığına bir OR (dt.user_id = :current_user_id) eklenebilir.
        // Şimdilik, genel görünürlük kurallarına uymasını sağlıyoruz.

        if (!empty($visibility_conditions)) {
            $sql_topics_base .= " AND (" . implode(" OR ", $visibility_conditions) . ")";
        } else {
            // Giriş yapmamış veya onaylanmamış ve view_all yetkisi olmayan kullanıcı için
            // sadece is_public_no_auth = 1 olanları göstermesi gerekirdi, ama bu durum
            // en baştaki is_user_admin() || has_permission() ile zaten ele alınmış olmalı.
            // Eğer buraya düşerse, hiçbir şey görememeli.
            $sql_topics_base .= " AND 1=0"; // Hiçbir sonuç döndürmez
        }
    }
            
    $sql_topics_final = $sql_topics_base . " ORDER BY dt.last_reply_at DESC, dt.created_at DESC";
            
    $stmt_topics = $pdo->prepare($sql_topics_final);
    $stmt_topics->execute($params_topics);
    $raw_topics = $stmt_topics->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_topics as $topic) {
        $is_unread = false;
        // Unread logic (aynı kalabilir)
        // Hatalı kopyalanan blok kaldırıldı.
        if ($topic['last_reply_at'] !== null && ($topic['last_viewed_at'] === null || strtotime($topic['last_reply_at']) > strtotime($topic['last_viewed_at']))) {
            if ($topic['reply_count'] > 0) {
                 $is_unread = true;
            } elseif ($topic['reply_count'] == 0 && $profile_user_id != $current_user_id_for_list) {
                if ($topic['last_viewed_at'] === null) {
                    $is_unread = true;
                }
            }
        }
        $topic['is_unread'] = $is_unread;
        $user_started_topics[] = $topic;
    }

} catch (PDOException $e) {
    error_log("Kullanıcı tartışmaları (user_discussions.php) çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Kullanıcının tartışmaları yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* user_discussions.php için GÜNCELLENMİŞ Stiller */
.user-discussions-page-container {
    width: 100%;
    max-width: 1200px;
    margin: 30px auto;
    padding: 25px 35px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - var(--navbar-height, 70px) - 160px);
}

/* Sayfa Üstü Kontroller (view_hangar.php'deki .page-header-controls gibi) */
.page-top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px; /* Başlıktan önce boşluk */
}
.back-to-profile-link-uds { /* UDS: User Discussions Page */
    font-size: 0.95rem;
    color: var(--turquase);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 20px;
    border: 1px solid transparent; /* Hover için hazırlık */
    transition: all 0.2s ease;
}
.back-to-profile-link-uds:hover {
    color: var(--light-turquase);
    /* background-color: var(--transparent-turquase-2); */
    /* border-color: var(--turquase); */
    text-decoration: none; /* Alt çizgiyi kaldır, hover efekti yeterli */
}
.back-to-profile-link-uds i.fas {
    font-size: 0.9em;
}
.btn-start-new-topic-uds { /* UDS: User Discussions Page */
    background-color: var(--turquase); color: var(--black); padding: 9px 20px;
    border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 0.9rem;
    transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;
    border: none; box-shadow: 0 2px 8px rgba(var(--turquase-rgb,0,0,0),0.2); /* RGBA için turquase-rgb tanımlanmalı */
}
.btn-start-new-topic-uds:hover {
    background-color: var(--light-turquase); color: var(--darker-gold-2);
    transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--turquase-rgb,0,0,0),0.3);
}
.btn-start-new-topic-uds i.fas { font-size: 0.9em; }


/* Sayfa Ana Başlığı */
.page-main-title-uds {
    color: var(--gold);
    font-size: 2.1rem;
    font-family: var(--font);
    margin: 0 0 30px 0; /* Alt kontrollerden sonra boşluk */
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
    text-align: left; /* Başlığı sola yasla */
}

/* Tartışma Listesi Stilleri (discussions.php'den alınan ve uyarlananlar) */
.discussion-list { list-style-type: none; padding: 0; margin: 0; }
.discussion-list-item {
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-left-width: 4px; /* Sol kenar vurgusu için */
    border-radius: 8px;
    margin-bottom: 18px; /* Öğeler arası boşluk artırıldı */
    padding: 18px 22px; /* İç padding artırıldı */
    display: flex;
    gap: 18px; /* Avatar ve içerik arası boşluk */
    align-items: flex-start;
    transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}
.discussion-list-item:hover {
    background-color: var(--darker-gold-2);
    border-color: var(--darker-gold); /* Ana border rengi */
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.discussion-list-item.topic-unread { border-left-color: var(--gold); }
.discussion-list-item.topic-unread:hover { border-left-color: var(--light-gold); }
.discussion-list-item.topic-read { border-left-color: var(--grey); }
.discussion-list-item.topic-read:hover { border-left-color: var(--light-grey); }

.discussion-avatar-area { flex-shrink: 0; padding-top: 3px; /* Başlıkla hizalamak için */ }
.creator-avatar-small-discussion { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--darker-gold-1); }
.avatar-placeholder-discussion { width: 45px; height: 45px; border-radius: 50%; background-color: var(--grey); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; border: 2px solid var(--darker-gold-1); line-height: 1; }
/* Popover tetikleyicisi için avatar alanına user-info-trigger class'ı eklenecek ve renk class'ları da */
.discussion-avatar-area.username-role-admin, .discussion-avatar-area.username-role-admin img, .discussion-avatar-area.username-role-admin div { border-color: var(--gold); /* Örnek */ }
/* Diğer roller için de benzer vurgular eklenebilir */


.discussion-info-area { flex-grow: 1; display: flex; flex-direction: column; gap: 6px; min-width:0; /* Taşan başlıklar için */}
.discussion-title { margin: 0; font-size: 1.25rem; line-height: 1.35; }
.discussion-title a { color: var(--lighter-grey); text-decoration: none; font-weight: 600; word-break: break-word; }
.discussion-title a:hover { color: var(--gold); text-decoration: underline; }
.discussion-list-item.topic-unread .discussion-title a { color: var(--white); }
.discussion-list-item.topic-unread .discussion-title a strong { color: var(--gold); }

.topic-status-badge.locked-topic { font-size: 0.78rem; padding: 3px 7px; border-radius: 4px; margin-left: 8px; font-weight: 500; vertical-align: middle; background-color: var(--dark-red); color: var(--white); border: 1px solid var(--red); }

.discussion-meta-user-page { font-size: 0.82rem; color: var(--light-grey); }
.discussion-meta-user-page .started-by-text { color: var(--lighter-grey); /* Popover'a gerek yok, zaten profil sahibinin konusu */ }

.discussion-stats-area { flex-shrink: 0; text-align: right; font-size: 0.88rem; color: var(--light-grey); min-width: 160px; display: flex; flex-direction: column; gap: 6px; align-items: flex-end; padding-top:3px; }
.discussion-stats-area .stat-item { display: block; }
.discussion-stats-area .last-reply { font-size: 0.82rem; color: var(--grey); }
.discussion-stats-area .last-reply strong { color: var(--light-grey); font-weight: 500; }

.empty-message { /* style.css'den */ text-align: center; font-size: 1.1rem; color: var(--light-grey); padding: 30px 20px; background-color: var(--charcoal); border-radius: 6px; border: 1px dashed var(--darker-gold-1); margin-top:20px;}
.empty-message a { color:var(--turquase); font-weight:bold;}
.empty-message a:hover { color:var(--light-turquase);}

/* Responsive */
@media (max-width: 768px) {
    .user-discussions-page-container { padding: 20px; }
    .page-top-controls { flex-direction: column; align-items: flex-start; gap:15px; }
    .btn-start-new-topic-uds { width:100%; text-align:center; justify-content:center;}
    .page-main-title-uds { font-size: 1.9rem; }
    .discussion-list-item { flex-direction: column; align-items: flex-start; gap: 12px; padding: 15px; }
    .discussion-stats-area { text-align: left; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--darker-gold-2); width: 100%; min-width: 0; align-items: flex-start; }
    .discussion-title { font-size: 1.15rem; }
}
.btn-outline-turquase { /* Zaten style.css'de olabilir */
    color: var(--turquase); border-color: var(--turquase); background-color: transparent;
    padding: 8px 18px; font-size: 0.9rem; font-weight: 500; border-radius: 20px; border: 1px solid;
}
.btn-outline-turquase:hover { color: var(--white); background-color: var(--turquase); }
.btn-outline-turquase.btn-sm { padding: 6px 14px; font-size: 0.85rem; }

</style>

<main class="main-content">
    <div class="container user-discussions-page-container">
        <div class="page-top-controls">
            <?php if ($viewed_user_data): ?>
            <a href="view_profile.php?user_id=<?php echo $profile_user_id; ?>" class="btn-outline-turquase">
                <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($viewed_user_data['username']); ?> Profiline Geri Dön
            </a>
            <?php endif; ?>
            <div class="user-discussions-actions">
                <a href="new_discussion_topic.php" class="btn-outline-turquase"><i class="fas fa-plus-circle"></i> Yeni Tartışma Başlat</a>
            </div>
        </div>

        <h1 class="page-main-title-uds"><?php echo $page_title; ?> (<?php echo count($user_started_topics); ?> Konu)</h1>

        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="empty-message" style="background-color: var(--transparent-red); color:var(--red); border-style:solid; border-color:var(--dark-red);"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
             <p class="empty-message" style="background-color: rgba(var(--turquase-rgb,0,0,0), 0.15); color:var(--turquase); border-style:solid; border-color:var(--turquase);"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
        <?php endif; ?>

        <?php if (empty($user_started_topics) && !(isset($_SESSION['error_message']) || isset($_SESSION['success_message'])) ): ?>
            <p class="empty-message">
                <?php echo htmlspecialchars($viewed_user_data['username']); ?> henüz hiç tartışma konusu başlatmamış.
                <?php if (is_user_logged_in() && $_SESSION['user_id'] == $profile_user_id): ?>
                    <br><a href="new_discussion_topic.php">İlk konunu sen başlat!</a>
                <?php endif; ?>
            </p>
        <?php elseif (!empty($user_started_topics)): ?>
            <ul class="discussion-list">
                <?php foreach ($user_started_topics as $topic): ?>
                    <?php
                        $starter_username_class = '';
                        if ($viewed_user_data) { // $viewed_user_data null değilse
                            $starter_roles_arr = !empty($viewed_user_data['user_roles_list']) ? explode(',', $viewed_user_data['user_roles_list']) : [];
                            foreach ($role_priority as $p_role) {
                                if (in_array($p_role, $starter_roles_arr)) {
                                    $starter_username_class = 'username-role-' . $p_role;
                                    break;
                                }
                            }
                        }
                    ?>
                    <li class="discussion-list-item <?php echo $topic['is_unread'] ? 'topic-unread' : 'topic-read'; ?>">
                        <div class="discussion-avatar-area user-info-trigger <?php echo $starter_username_class; ?>"
                             data-user-id="<?php echo htmlspecialchars($profile_user_id); ?>"
                             data-username="<?php echo htmlspecialchars($viewed_user_data['username']); ?>"
                             data-avatar="<?php echo htmlspecialchars(!empty($viewed_user_data['avatar_path']) ? ('/public/' . $viewed_user_data['avatar_path']) : ''); ?>"
                             data-ingame="<?php echo htmlspecialchars($viewed_user_data['ingame_name'] ?? 'N/A'); ?>"
                             data-discord="<?php echo htmlspecialchars($viewed_user_data['discord_username'] ?? 'N/A'); ?>"
                             data-event-count="<?php echo htmlspecialchars($viewed_user_data['user_event_count'] ?? '0'); ?>"
                             data-gallery-count="<?php echo htmlspecialchars($viewed_user_data['user_gallery_count'] ?? '0'); ?>"
                             data-roles="<?php echo htmlspecialchars($viewed_user_data['user_roles_list'] ?? ''); ?>">
                            
                             <?php if (!empty($viewed_user_data['avatar_path'])): ?>
                                <img src="/public/<?php echo htmlspecialchars($viewed_user_data['avatar_path']); ?>" alt="<?php echo htmlspecialchars($viewed_user_data['username']); ?>" class="creator-avatar-small-discussion">
                            <?php else: ?>
                                <div class="avatar-placeholder-discussion"><?php echo strtoupper(substr(htmlspecialchars($viewed_user_data['username']), 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="discussion-info-area">
                            <h3 class="discussion-title">
                                <a href="discussion_detail.php?id=<?php echo $topic['id']; ?>" class="<?php echo $topic['is_unread'] ? 'unread-link-style' : ''; ?>">
                                    <?php if ($topic['is_unread']): ?><strong><?php endif; ?>
                                    <?php echo htmlspecialchars($topic['title']); ?>
                                    <?php if ($topic['is_unread']): ?></strong><?php endif; ?>
                                    <?php if ($topic['is_locked']): ?>
                                        <span class="topic-status-badge locked-topic" title="Bu konu yorumlara kapatılmıştır.">🔒 Kilitli</span>
                                    <?php endif; ?>
                                </a>
                            </h3>
                            <small class="discussion-meta-user-page">
                                <span class="started-by-text"><?php echo htmlspecialchars($viewed_user_data['username']); // Zaten bu kullanıcının konuları listeleniyor ?></span>
                                tarafından <?php echo date('d M Y, H:i', strtotime($topic['topic_created_at'])); ?> tarihinde başlatıldı.
                            </small>
                        </div>
                        <div class="discussion-stats-area">
                            <span class="stat-item"><i class="fas fa-comments" style="margin-right:5px; color:var(--light-grey);"></i><?php echo $topic['reply_count']; ?> Yorum</span>
                            <?php if ($topic['reply_count'] > 0 && !empty($topic['last_replier_username'])): ?>
                                <span class="stat-item last-reply">
                                    Son Yorum: 
                                    <strong><?php echo htmlspecialchars($topic['last_replier_username']); ?></strong>
                                    (<?php echo date('d M Y, H:i', strtotime($topic['last_reply_at'])); ?>)
                                </span>
                            <?php elseif($topic['reply_count'] == 0): ?>
                                <span class="stat-item last-reply">Henüz yorum yok</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Popover.js zaten footer'da yüklendiği için .user-info-trigger class'ı ile çalışacaktır.
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
    ['darker-gold-2', 'darker-gold-1', 'grey', 'gold', 'charcoal', 'turquase'].forEach(setRgbVar);
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
