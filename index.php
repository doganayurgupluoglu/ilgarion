<?php
// public/index.php - Rol ve Yetki Tabanlı Ana Sayfa

require_once 'src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Hata raporlamayı etkinleştir (geliştirme aşamasında)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "Ana Sayfa - ILGARION TURANIS";

// Kullanıcı oturum kontrolleri
if (is_user_logged_in()) {
    // Oturum geçerliliğini kontrol et
    check_user_session_validity();
}

// Sayfa erişim bilgilerini logla (opsiyonel)
try {
    if (function_exists('audit_log') && is_user_logged_in()) {
        audit_log($pdo, $_SESSION['user_id'] ?? null, 'homepage_accessed', 'page', null, null, [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => get_client_ip(),
            'access_time' => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    // Audit log hatası kritik değil, devam et
    error_log("Homepage audit log error: " . $e->getMessage());
}

// Kullanıcı bilgilerini ve yetkilerini al
$is_logged_in = is_user_logged_in();
$is_admin = false;
$user_permissions = [];
$user_roles = [];
$can_view_events = false;
$can_view_gallery = false;
$can_view_discussions = false;
$can_view_guides = false;
$can_view_loadouts = false;
$can_create_content = false;

if ($is_logged_in) {
    try {
        // Admin kontrolü
        $is_admin = is_admin($pdo);
        
        // Kullanıcı rollerini al
        $user_roles = get_user_roles($pdo);
        
        // Temel görüntüleme yetkilerini kontrol et
        $can_view_events = has_permission($pdo, 'event.view_public') || has_permission($pdo, 'event.view_members_only');
        $can_view_gallery = has_permission($pdo, 'gallery.view_public') || has_permission($pdo, 'gallery.view_approved');
        $can_view_discussions = has_permission($pdo, 'discussion.view_approved');
        $can_view_guides = has_permission($pdo, 'guide.view_public') || has_permission($pdo, 'guide.view_members_only');
        $can_view_loadouts = has_permission($pdo, 'loadout.view_published');
        
        // İçerik oluşturma yetkilerini kontrol et
        $can_create_content = has_permission($pdo, 'event.create') || 
                             has_permission($pdo, 'guide.create') || 
                             has_permission($pdo, 'gallery.upload') ||
                             has_permission($pdo, 'discussion.topic.create');
        
    } catch (Exception $e) {
        error_log("Permission check error on homepage: " . $e->getMessage());
        // Hata durumunda güvenli fallback
        $is_admin = false;
        $user_roles = [];
        $can_view_events = false;
        $can_view_gallery = false;
        $can_view_discussions = false;
        $can_view_guides = false;
        $can_view_loadouts = false;
        $can_create_content = false;
    }
} else {
    // Misafir kullanıcılar için herkese açık içerikleri kontrol et
    // Bu kontroller navbar.php'de de yapılıyor, ancak tutarlılık için burada da ekliyoruz
    $can_view_events = true; // Herkese açık etkinlikler
    $can_view_gallery = true; // Herkese açık galeri
    $can_view_guides = true; // Herkese açık rehberler
}

// Son etkinlikleri çek (yetki tabanlı)
$recent_events = [];
if ($can_view_events) {
    try {
        $events_query = "
            SELECT e.id, e.title, e.event_datetime, e.location, e.event_type, e.visibility,
                   u.username as creator_name
            FROM events e
            JOIN users u ON e.created_by_user_id = u.id
            WHERE e.status = 'active' 
            AND e.event_datetime >= NOW()
        ";
        
        // Yetki tabanlı filtreleme
        if (!$is_logged_in) {
            $events_query .= " AND (e.visibility = 'public' OR e.is_public_no_auth = 1)";
        } elseif (!$is_admin && !has_permission($pdo, 'event.view_all')) {
            $events_query .= " AND (
                e.visibility = 'public' OR 
                e.is_public_no_auth = 1 OR 
                (e.visibility = 'members_only' AND e.is_members_only = 1)
            )";
        }
        
        $events_query .= " ORDER BY e.event_datetime ASC LIMIT 3";
        
        $stmt = $pdo->prepare($events_query);
        $stmt->execute();
        $recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Recent events fetch error: " . $e->getMessage());
        $recent_events = [];
    }
}

// Son galeriye eklenen fotoğrafları çek (yetki tabanlı)
$recent_photos = [];
if ($can_view_gallery) {
    try {
        $photos_query = "
            SELECT gp.id, gp.image_path, gp.description, gp.uploaded_at,
                   u.username as uploader_name
            FROM gallery_photos gp
            JOIN users u ON gp.user_id = u.id
            WHERE 1=1
        ";
        
        // Yetki tabanlı filtreleme
        if (!$is_logged_in) {
            $photos_query .= " AND gp.is_public_no_auth = 1";
        } elseif (!$is_admin && !has_permission($pdo, 'gallery.view_all')) {
            $photos_query .= " AND (gp.is_public_no_auth = 1 OR gp.is_members_only = 1)";
        }
        
        $photos_query .= " ORDER BY gp.uploaded_at DESC LIMIT 6";
        
        $stmt = $pdo->prepare($photos_query);
        $stmt->execute();
        $recent_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Recent photos fetch error: " . $e->getMessage());
        $recent_photos = [];
    }
}

// Son tartışmaları çek (sadece giriş yapmış kullanıcılar için)
$recent_discussions = [];
if ($can_view_discussions) {
    try {
        $discussions_query = "
            SELECT dt.id, dt.title, dt.created_at, dt.reply_count, dt.last_reply_at,
                   u.username as creator_name
            FROM discussion_topics dt
            JOIN users u ON dt.user_id = u.id
            WHERE dt.is_locked = 0
            AND (dt.is_members_only = 1 OR dt.is_public_no_auth = 1)
            ORDER BY dt.last_reply_at DESC 
            LIMIT 5
        ";
        
        $stmt = $pdo->prepare($discussions_query);
        $stmt->execute();
        $recent_discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Recent discussions fetch error: " . $e->getMessage());
        $recent_discussions = [];
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';

?>
<style>
    .site-container {
        min-width: 100vw !important;
        padding: 0 !important;
        overflow: hidden !important;
    }
</style>
<main class="main-content">
    <!-- Hero Bölümü -->
    <section class="hero-section">
        <div class="hero-left">
            <h1>ILGARION TURANIS</h1>
            <p class="hero-subtitle">" Gerçekçilik, Takım Ruhu ve Taktiksel Üstünlük. "</p>
            <div class="hero-links">
                <?php if ($can_view_events): ?>
                    <a href="/events.php" class="btn">Yaklaşan Etkinlikleri Gör</a>
                <?php endif; ?>
                
                <?php if (!$is_logged_in): ?>
                    <a href="/register.php" class="btn btn-hero-action">Hemen Katıl!</a>
                <?php elseif ($can_create_content): ?>
                    <!-- <a href="/admin/index.php" class="btn btn-hero-action">
                        <i class="fas fa-plus"></i> İçerik Oluştur
                    </a> -->
                <?php endif; ?>
            </div>
            
            <?php if ($is_logged_in): ?>
                <!-- Kullanıcı Hoş Geldin Mesajı -->
                <div class="welcome-message">
                    <h3>Hoş geldin, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Pilot'); ?>!</h3>
                    <?php if (!empty($user_roles)): ?>
                        <p class="user-roles">
                            <i class="fas fa-star"></i> 
                             <?php echo htmlspecialchars(implode(', ', array_map(function($role) { 
                                return ucfirst(str_replace('_', ' ', $role['name'])); 
                            }, $user_roles))); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-right">
            <img class="scg-logo" src="/assets/scg-logo.png" alt="SCG Logo">
            <img class="scg-loga" src="/assets/ilgarion-banner-logo.png" alt="Ilgarion Turanis Banner">
        </div>
    </section>

    <!-- Yaklaşan Etkinlikler Bölümü -->
    <?php if ($can_view_events && !empty($recent_events)): ?>
    <section class="recent-events-section">
        <div class="section-header">
            <h2><i class="fas fa-calendar-alt"></i> Yaklaşan Etkinlikler</h2>
            <a href="/events.php" class="section-link">Tümünü Gör</a>
        </div>
        <div class="events-grid">
            <?php foreach ($recent_events as $event): ?>
                <div class="event-card">
                    <div class="event-header">
                        <span class="event-type event-type-<?php echo strtolower($event['event_type']); ?>">
                            <?php echo htmlspecialchars($event['event_type']); ?>
                        </span>
                        <span class="event-visibility">
                            <?php 
                            switch($event['visibility']) {
                                case 'public': echo '<i class="fas fa-globe"></i>'; break;
                                case 'members_only': echo '<i class="fas fa-users"></i>'; break;
                                case 'faction_only': echo '<i class="fas fa-shield-alt"></i>'; break;
                                default: echo '<i class="fas fa-eye"></i>';
                            }
                            ?>
                        </span>
                    </div>
                    <h3 class="event-title">
                        <a href="/event_detail.php?id=<?php echo $event['id']; ?>">
                            <?php echo htmlspecialchars($event['title']); ?>
                        </a>
                    </h3>
                    <div class="event-details">
                        <p class="event-datetime">
                            <i class="fas fa-clock"></i>
                            <?php echo date('d.m.Y H:i', strtotime($event['event_datetime'])); ?>
                        </p>
                        <?php if (!empty($event['location'])): ?>
                            <p class="event-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </p>
                        <?php endif; ?>
                        <p class="event-creator">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($event['creator_name']); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <!-- Ana İçerik Bölümü -->
    <section class="main-content-section">
        <h2>Son Duyurular ve Etkinlikler</h2>
        <?php if (!$is_logged_in): ?>
            <div class="guest-notice">
                <p><i class="fas fa-info-circle"></i> 
                   Tüm özelliklerimizden faydalanmak için 
                   <a href="/login.php">giriş yapın</a> 
                   veya <a href="/register.php">üye olun</a>.
                </p>
            </div>
        <?php endif; ?>
        
        <p>Henüz bir duyuru bulunmamaktadır. Yakında burada önemli duyurular, güncel etkinlikler ve
            fraksiyonumuzla ilgili daha fazla bilgi yer alacaktır.</p>
    </section>
    <!-- Son Galeri Fotoğrafları -->
    <?php if ($can_view_gallery && !empty($recent_photos)): ?>
    <section class="recent-gallery-section">
        <div class="section-header">
            <h2><i class="fas fa-images"></i> Son Eklenen Fotoğraflar</h2>
            <a href="/gallery.php" class="section-link">Galeriyi Gör</a>
        </div>
        <div class="gallery-grid">
            <?php foreach ($recent_photos as $photo): ?>
                <div class="gallery-item">
                    <a href="/gallery.php#photo-<?php echo $photo['id']; ?>">
                        <img src="<?php echo htmlspecialchars($photo['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($photo['description'] ?? 'Galeri Fotoğrafı'); ?>">
                        <div class="gallery-overlay">
                            <i class="fas fa-search-plus"></i>
                        </div>
                    </a>
                    <div class="gallery-info">
                        <small>
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($photo['uploader_name']); ?> |
                            <i class="fas fa-clock"></i> <?php echo date('d.m.Y', strtotime($photo['uploaded_at'])); ?>
                        </small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Son Tartışmalar -->
    <?php if ($can_view_discussions && !empty($recent_discussions)): ?>
    <section class="recent-discussions-section">
        <div class="section-header">
            <h2><i class="fas fa-comments"></i> Son Tartışmalar</h2>
            <a href="/discussions.php" class="section-link">Tümünü Gör</a>
        </div>
        <div class="discussions-list">
            <?php foreach ($recent_discussions as $discussion): ?>
                <div class="discussion-item">
                    <div class="discussion-content">
                        <h4 class="discussion-title">
                            <a href="/discussion_detail.php?id=<?php echo $discussion['id']; ?>">
                                <?php echo htmlspecialchars($discussion['title']); ?>
                            </a>
                        </h4>
                        <div class="discussion-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($discussion['creator_name']); ?></span>
                            <span><i class="fas fa-comments"></i> <?php echo $discussion['reply_count']; ?> yanıt</span>
                            <span><i class="fas fa-clock"></i> 
                                <?php echo date('d.m.Y H:i', strtotime($discussion['last_reply_at'] ?? $discussion['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Hızlı Eylemler (Giriş Yapmış Kullanıcılar İçin) -->
    <?php if ($is_logged_in): ?>
    <section class="quick-actions-section">
        <div class="section-header">
            <h2><i class="fas fa-bolt"></i> Hızlı Eylemler</h2>
        </div>
        <div class="quick-actions-grid">
            <?php if (has_permission($pdo, 'event.create')): ?>
                <a href="/create_event.php" class="quick-action-card">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Etkinlik Oluştur</span>
                </a>
            <?php endif; ?>
            
            <?php if (has_permission($pdo, 'guide.create')): ?>
                <a href="/new_guide.php" class="quick-action-card">
                    <i class="fas fa-pen"></i>
                    <span>Rehber Yaz</span>
                </a>
            <?php endif; ?>
            
            <?php if (has_permission($pdo, 'gallery.upload')): ?>
                <a href="/upload_photo.php" class="quick-action-card">
                    <i class="fas fa-camera"></i>
                    <span>Fotoğraf Yükle</span>
                </a>
            <?php endif; ?>
            
            <?php if (has_permission($pdo, 'discussion.topic.create')): ?>
                <a href="/create_topic.php" class="quick-action-card">
                    <i class="fas fa-comment-alt"></i>
                    <span>Tartışma Başlat</span>
                </a>
            <?php endif; ?>
            
            <a href="/edit_profile.php" class="quick-action-card">
                <i class="fas fa-user-edit"></i>
                <span>Profili Düzenle</span>
            </a>
            
            <a href="/edit_hangar.php" class="quick-action-card">
                <i class="fas fa-warehouse"></i>
                <span>Hangarı Yönet</span>
            </a>
        </div>
    </section>
    <?php endif; ?>



    <!-- Hakkımızda Bölümü -->
    <section class="about-section">
        <div class="about-section-text">
            <h3>Hakkımızda</h3>
            <p>
                Ilgarion Turanis: Gerçekliğin ve Taktiğin Buluşma Noktası<br><br>

                Star Citizen evreni, sonsuz fırsatlar ve tehlikelerle dolu bir uzay simülasyonundan çok daha
                fazlası.<br>
                Bu karmaşık sistemin karanlık yüzeylerinde, disiplinli ekip çalışması ve stratejik hareket kabiliyeti
                fark yaratır.<br>
                Tam da bu noktada Ilgarion Turanis sahneye çıkıyor.<br><br>

                Ilgarion Turanis; taktiksel düşünceyi, ekip koordinasyonunu ve gerçekçilik odaklı oynanışı merkezine
                alan bir SCG Fraksiyonudur.<br>
                Ana hedefimiz; yer görevleri, bunker operasyonları, keşif faaliyetleri ve taaruz temelli senaryolarda
                profesyonelce hareket eden, ne yaptığını bilen bir ekip yapısı oluşturmaktır.<br><br>

                Her operasyon bir planla başlar.<br>
                Her adım disiplinle atılır.<br>
                Her görev, yalnızca tamamlanmak için değil, mükemmel icra edilmek için yürütülür.<br>
                Bizimle birlikte görev alan her pilot, rastgele değil; bilinçli, hazırlıklı ve birbirine bağlı bir ekip
                yapısının parçası olur.<br><br>

                Eğer sen de:<br><br>

                Koordineli hareket etmeyi seviyor,<br>
                Gerçekçi ve planlı yer görevlerinden keyif alıyor,<br>
                Takım ruhunu bireyselliğin önüne koyuyorsan;<br><br>

                Ilgarion Turanis seni bekliyor.<br><br>

                <strong class="last-sentence">"Biz farkı şansa bırakmayız. Ilgarion'da her birey, görevle bütünleşmiş bir etki unsurudur. Bize katıl ve sen de farkını yarat!"</strong>
            </p>
        </div>
        
        <div class="main-card-container">
            <h3>Temas Noktaları</h3>
            <hr class="index-hr">
            <div class="card"><img src="/assets/rsi-logo.png" alt="RSI"></div>
            <div class="card"><i class="fa-brands fa-youtube"></i></div>
            <div class="card"><i class="fa-brands fa-discord"></i></div>
            <div class="card"><i class="fa-brands fa-instagram"></i></div>
        </div>
    </section>
</main>

<style>
/* Ana sayfa için ek stiller */
.welcome-message {
    margin-top: 20px;
    padding: 15px;
    background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1));
    border-radius: 8px;
    border-left: 4px solid var(--gold);
}

.welcome-message h3 {
    color: var(--gold);
    margin: 0 0 8px 0;
    font-size: 1.2rem;
}

.user-roles {
    color: var(--turquase);
    margin: 0;
    font-size: 0.9rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
    padding-bottom: 10px;
}

.section-header h2 {
    color: var(--gold);
    margin: 0;
    font-size: 1.5rem;
}

.section-link {
    color: var(--turquase);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.section-link:hover {
    color: var(--light-turquase);
}

.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.event-card {
    background-color: var(--charcoal);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid var(--darker-gold-1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.event-type {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
}

.event-type-genel { background-color: var(--grey); color: var(--white); }
.event-type-operasyon { background-color: var(--red); color: var(--white); }
.event-type-eğitim { background-color: var(--turquase); color: var(--black); }
.event-type-sosyal { background-color: var(--gold); color: var(--black); }

.event-title a {
    color: var(--lighter-grey);
    text-decoration: none;
    font-size: 1.1rem;
    font-weight: 600;
}

.event-title a:hover {
    color: var(--gold);
}

.event-details {
    margin-top: 10px;
}

.event-details p {
    margin: 5px 0;
    color: var(--light-grey);
    font-size: 0.85rem;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 0px;
}

.gallery-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}

.gallery-item img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.gallery-item:hover .gallery-overlay {
    opacity: 1;
}

.gallery-item:hover img {
    transform: scale(1.1);
}

.gallery-overlay i {
    color: var(--gold);
    font-size: 1.5rem;
}

.gallery-info {
    padding: 10px;
}

.gallery-info small {
    color: var(--light-grey);
    font-size: 0.75rem;
}

.discussions-list {
}

.discussion-item {
    border-radius: 8px;
    padding: 15px;
    border: 1px solid var(--darker-gold-1);
    border-left: 3px solid var(--turquase);
    transition: background-color 0.3s ease;
}

.discussion-item:hover {
    background-color: var(--darker-gold-2);
}

.discussion-title a {
    color: var(--lighter-grey);
    text-decoration: none;
    font-size: 1rem;
    font-weight: 600;
}

.discussion-title a:hover {
    color: var(--gold);
}

.discussion-meta {
    margin-top: 8px;
    display: flex;
    gap: 15px;
    font-size: 0.8rem;
    color: var(--light-grey);
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.quick-action-card {
    border: 1px solid var(--darker-gold-1);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: var(--lighter-grey);
    transition: all 0.3s ease;
}

.quick-action-card:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.quick-action-card i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: var(--turquase);
    transition: color 0.3s ease;
}

.quick-action-card:hover i {
    color: var(--gold);
}

.quick-action-card span {
    display: block;
    font-size: 0.9rem;
    font-weight: 500;
}

.guest-notice {
    background: linear-gradient(135deg, var(--transparent-turquase), rgba(61, 166, 162, 0.1));
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--turquase);
    text-align: center;
}

.guest-notice p {
    margin: 0;
    color: var(--lighter-grey);
}

.guest-notice a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 600;
}

.guest-notice a:hover {
    color: var(--light-turquase);
    text-decoration: underline;
}

.recent-events-section,
.recent-gallery-section,
.recent-discussions-section,
.quick-actions-section {
    margin: 40px 0;
    padding: 30px;
    border-radius: 12px;
    /* border: 1px solid var(--darker-gold-1); */
    width: 80%;
}

/* Responsive tasarım */
@media (max-width: 768px) {
    .events-grid,
    .gallery-grid,
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .discussion-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .hero-links {
        flex-direction: column;
        gap: 10px;
    }
    
    .welcome-message {
        margin-top: 15px;
        padding: 12px;
    }
}

/* Yetki durumu göstergeleri */
.permission-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
}

.permission-indicator.granted {
    background-color: var(--turquase);
    color: var(--black);
}

.permission-indicator.denied {
    background-color: var(--red);
    color: var(--white);
}

.permission-indicator.admin {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--black);
}

/* Animasyonlar */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.event-card,
.gallery-item,
.discussion-item,
.quick-action-card {
    animation: fadeInUp 0.6s ease forwards;
}

.event-card:nth-child(2) { animation-delay: 0.1s; }
.event-card:nth-child(3) { animation-delay: 0.2s; }
.gallery-item:nth-child(2) { animation-delay: 0.1s; }
.gallery-item:nth-child(3) { animation-delay: 0.2s; }
.gallery-item:nth-child(4) { animation-delay: 0.3s; }
.gallery-item:nth-child(5) { animation-delay: 0.4s; }
.gallery-item:nth-child(6) { animation-delay: 0.5s; }
.discussion-item:nth-child(2) { animation-delay: 0.1s; }
.discussion-item:nth-child(3) { animation-delay: 0.2s; }
.discussion-item:nth-child(4) { animation-delay: 0.3s; }
.discussion-item:nth-child(5) { animation-delay: 0.4s; }
.quick-action-card:nth-child(2) { animation-delay: 0.1s; }
.quick-action-card:nth-child(3) { animation-delay: 0.2s; }
.quick-action-card:nth-child(4) { animation-delay: 0.3s; }
.quick-action-card:nth-child(5) { animation-delay: 0.4s; }
.quick-action-card:nth-child(6) { animation-delay: 0.5s; }

/* Dark mode ve accessibility iyileştirmeleri */
@media (prefers-reduced-motion: reduce) {
    .event-card,
    .gallery-item,
    .discussion-item,
    .quick-action-card {
        animation: none;
    }
    
    .gallery-item img,
    .event-card,
    .quick-action-card {
        transition: none;
    }
}

/* Focus states for accessibility */
.event-title a:focus,
.gallery-item a:focus,
.discussion-title a:focus,
.quick-action-card:focus {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
}

/* Loading states (gelecekte AJAX içerik için) */
.loading-skeleton {
    background: linear-gradient(90deg, var(--charcoal) 25%, var(--darker-gold-2) 50%, var(--charcoal) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>