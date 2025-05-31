<?php
// public/admin/index.php

// 1. Temel yapılandırma ve yetkilendirme
// Bu dosya public/admin/ altında olduğu için, proje köküne çıkmak için ../../ kullanılır.
require_once '../../src/config/database.php'; // $pdo ve BASE_PATH
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


$page_title = "Admin Dashboard";

// 2. İstatistikleri Çekme
$stats = [
    'total_users' => 0,
    'pending_users' => 0,
    'approved_users' => 0,
    'total_events' => 0,
    'active_events' => 0,
    'past_events' => 0,
    'total_gallery_photos' => 0,
    'total_discussion_topics' => 0,
    'total_discussion_posts' => 0,
    'total_guides' => 0,
    'published_guides' => 0,
    'total_loadout_sets' => 0
];

try {
    if (!isset($pdo)) {
        throw new Exception("Veritabanı bağlantısı bulunamadı.");
    }

    // Kullanıcı İstatistikleri
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['pending_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $stats['approved_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();

    // Etkinlik İstatistikleri
    $stats['total_events'] = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $stats['active_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn();
    $stats['past_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'past'")->fetchColumn();
    
    // Galeri İstatistikleri
    $stats['total_gallery_photos'] = $pdo->query("SELECT COUNT(*) FROM gallery_photos")->fetchColumn();

    // Tartışma İstatistikleri
    $stats['total_discussion_topics'] = $pdo->query("SELECT COUNT(*) FROM discussion_topics")->fetchColumn();
    $stats['total_discussion_posts'] = $pdo->query("SELECT COUNT(*) FROM discussion_posts")->fetchColumn();
    
    // Rehber İstatistikleri
    $stats['total_guides'] = $pdo->query("SELECT COUNT(*) FROM guides")->fetchColumn();
    $stats['published_guides'] = $pdo->query("SELECT COUNT(*) FROM guides WHERE status = 'published'")->fetchColumn();

    // Teçhizat Seti İstatistikleri
    $stats['total_loadout_sets'] = $pdo->query("SELECT COUNT(*) FROM loadout_sets")->fetchColumn();

} catch (Exception $e) { // Hem PDOException hem de genel Exception yakala
    error_log("Admin dashboard istatistik çekme hatası: " . $e->getMessage());
    if (session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['error_message'] = "İstatistikler yüklenirken bir veritabanı hatası oluştu: " . $e->getMessage();
    // header.php bu mesajı gösterecek, ancak $pdo tanımsızsa header.php de sorun yaşayabilir.
    // Eğer $pdo tanımsızsa, header.php'yi çağırmadan önce bir die() daha iyi olabilir.
}

require_once BASE_PATH . '/src/includes/header.php'; //
require_once BASE_PATH . '/src/includes/navbar.php'; //
?>
<style>
.admin-dashboard-container {
    width: 100%;
    max-width: 1600px;
    margin: 30px auto;
    padding: 20px;
    font-family: var(--font);
    color: var(--lighter-grey);
    min-height: calc(100vh - 150px - 130px);
}

.admin-dashboard-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.admin-dashboard-header h1 {
    color: var(--gold);
    font-size: 2.2rem;
    font-family: var(--font);
    margin: 0 0 10px 0;
}

.admin-dashboard-header p {
    font-size: 1.1rem;
    color: var(--light-grey);
    margin: 0;
}
.admin-dashboard-header p strong {
    color: var(--light-gold);
    font-weight: 600;
}

/* In-flow error message (eğer global .message.error-message farklıysa) */
.admin-dashboard-container .message.error-message {
    background-color: var(--transparent-red);
    color: var(--red);
    border: 1px solid var(--dark-red);
    padding: 12px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    text-align: left;
}

.dashboard-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card-link {
    text-decoration: none;
    color: inherit;
    display: block; /* Kartın tamamını tıklanabilir yapar */
}

.stat-card {
    background-color: var(--charcoal);
    padding: 25px 20px;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    border-left-width: 5px; /* Vurgu için sol kenarlık */
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    text-align: left;
    display: flex;
    flex-direction: column;
    height: 100%; /* Kartların aynı yükseklikte olmasına yardımcı olur (grid içinde) */
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px var(--transparent-gold);
}

.stat-card h3 {
    font-size: 1.2rem; /* Biraz daha küçük başlık */
    color: var(--light-gold);
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    font-family: var(--font);
    font-weight: 500;
}

.stat-icon {
    margin-right: 12px;
    font-size: 1.5em; /* İkon boyutu */
    width: 30px; /* İkon için sabit genişlik */
    text-align: center;
    color: var(--gold); /* Varsayılan ikon rengi */
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--white);
    margin: 0 0 8px 0;
    line-height: 1.1;
}

.stat-description {
    font-size: 0.85rem;
    color: var(--light-grey);
    line-height: 1.4;
    margin-top: auto; /* Açıklamayı kartın altına iter */
}

/* Kartlara özel vurgu renkleri */
.stat-card.users { border-left-color: var(--turquase); }
.stat-card.users .stat-icon { color: var(--turquase); }

.stat-card.pending { border-left-color: var(--light-gold); }
.stat-card.pending .stat-icon { color: var(--light-gold); }

.stat-card.events { border-left-color: #4CAF50; } /* Yeşilimsi bir ton */
.stat-card.events .stat-icon { color: #4CAF50; }

.stat-card.gallery { border-left-color: #2196F3; } /* Mavimsi bir ton */
.stat-card.gallery .stat-icon { color: #2196F3; }

.stat-card.discussions { border-left-color: #FF9800; } /* Turuncumsu bir ton */
.stat-card.discussions .stat-icon { color: #FF9800; }

.stat-card.guides { border-left-color: #9C27B0; } /* Morumsu bir ton */
.stat-card.guides .stat-icon { color: #9C27B0; }

.stat-card.loadouts { border-left-color: #E91E63; } /* Pembemsi bir ton */
.stat-card.loadouts .stat-icon { color: #E91E63; }



</style>

<main>
    <div class="container admin-dashboard-container">
        <div class="admin-dashboard-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Merhaba, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>! Yönetim paneline hoş geldiniz.</p>
        </div>
         <?php 
        // YENİ: Hızlı Yönetim Linklerini include et
        // Bu dosya BASE_PATH . '/src/includes/admin_quick_navigation.php' yolunda olmalı
        if (defined('BASE_PATH') && file_exists(BASE_PATH . '/src/includes/admin_quick_navigation.php')) {
            require_once BASE_PATH . '/src/includes/admin_quick_navigation.php';
        } else {
            echo "<p style='color:red; text-align:center;'>Hata: Admin navigasyon menüsü yüklenemedi.</p>";
        }
        ?>
        <?php if (isset($_SESSION['error_message'])): // İstatistik çekme hatası olduysa göster ?>
            <p class="message error-message"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
        <?php endif; ?>

        <div class="dashboard-stats-grid">
            <a href="users.php" class="stat-card-link">
                <div class="stat-card users">
                    <h3><i class="fas fa-users stat-icon"></i>Toplam Üye</h3>
                    <p class="stat-number"><?php echo $stats['total_users']; ?></p>
                    <p class="stat-description"><?php echo $stats['approved_users']; ?> Onaylı</p>
                </div>
            </a>
            <a href="users.php?status_filter=pending" class="stat-card-link">
                <div class="stat-card pending">
                    <h3><i class="fas fa-user-clock stat-icon"></i>Onay Bekleyen</h3>
                    <p class="stat-number"><?php echo $stats['pending_users']; ?></p>
                    <p class="stat-description">Kullanıcı onayı gerekiyor</p>
                </div>
            </a>
            <a href="events.php" class="stat-card-link">
                <div class="stat-card events">
                    <h3><i class="fas fa-calendar-alt stat-icon"></i>Etkinlikler</h3>
                    <p class="stat-number"><?php echo $stats['total_events']; ?></p>
                    <p class="stat-description"><?php echo $stats['active_events']; ?> Aktif / <?php echo $stats['past_events']; ?> Geçmiş</p>
                </div>
            </a>
             <a href="<?php echo get_auth_base_url(); ?>/admin/gallery.php" class="stat-card-link">
                <div class="stat-card gallery">
                    <h3><i class="fas fa-images stat-icon"></i>Galeri Fotoğrafları</h3>
                    <p class="stat-number"><?php echo $stats['total_gallery_photos']; ?></p>
                    <p class="stat-description">Toplam yüklenen</p>
                </div>
            </a>
            <a href="<?php echo get_auth_base_url(); ?>/admin/discussions.php" class="stat-card-link"> 
                <div class="stat-card discussions">
                    <h3><i class="fas fa-comments stat-icon"></i>Tartışmalar</h3>
                    <p class="stat-number"><?php echo $stats['total_discussion_topics']; ?></p>
                    <p class="stat-description"><?php echo $stats['total_discussion_posts']; ?> Toplam Yorum</p>
                </div>
            </a>
            <a href="<?php echo get_auth_base_url(); ?>/admin/guides.php" class="stat-card-link"> 
                <div class="stat-card guides">
                    <h3><i class="fas fa-book-open stat-icon"></i>Rehberler</h3>
                    <p class="stat-number"><?php echo $stats['total_guides']; ?></p>
                    <p class="stat-description"><?php echo $stats['published_guides']; ?> Yayınlanmış</p>
                </div>
            </a>
             <a href="manage_loadout_sets.php" class="stat-card-link">
                <div class="stat-card loadouts">
                    <h3><i class="fas fa-shield-alt stat-icon"></i>Teçhizat Setleri</h3>
                    <p class="stat-number"><?php echo $stats['total_loadout_sets']; ?></p>
                    <p class="stat-description">Tanımlanmış set sayısı</p>
                </div>
            </a>
        </div>
        
       
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php'; //
?>