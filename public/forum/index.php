<?php
// public/forum/index.php - Basitleştirilmiş Rol Kontrolü

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(__DIR__)) . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = is_user_logged_in();
$is_approved = is_user_approved();

// Sayfa başlığı
$page_title = "Forum - Ilgarion Turanis";

// Kategorileri çek (sadece erişilebilir olanlar)
$categories = get_accessible_forum_categories($pdo, $current_user_id);

// Forum istatistikleri
$forum_stats = get_forum_statistics($pdo, $current_user_id);

// Son konuları çek
$recent_topics = get_recent_forum_topics($pdo, 5, $current_user_id);

// Arama parametreleri
$search_query = $_GET['search'] ?? '';
$search_results = [];
if (!empty($search_query)) {
    $search_results = search_forum_content($pdo, $search_query, $current_user_id);
}

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/public/forum/css/forum.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <nav class="forum-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/public/index.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-comments"></i> Forum
            </li>
        </ol>
    </nav>

    <!-- Forum Header -->
    <div class="forum-header">
        <div class="forum-title">
            <h1><i class="fas fa-comments"></i> Forum Tartışma Panelları</h1>
            <p>Organizasyonumuzla ve Star Citizen evreniyle ilgili tartışmalara katılın, bilgi paylaşın ve topluluğumuzla etkileşimde bulunun.</p>
        </div>
        
        <div class="forum-actions">
            <!-- Arama Çubuğu -->
            <form class="forum-search" method="GET" action="">
                <div class="search-input-group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" 
                           placeholder="Kategori, konu veya kullanıcı ara..." class="search-input">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <?php if ($is_approved && !empty($categories)): ?>
                <a href="/public/forum/create_topic.php" class="btn-new-topic">
                    <i class="fas fa-plus"></i> Yeni Konu Aç
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($search_query)): ?>
        <!-- Arama Sonuçları -->
        <div class="search-results">
            <h3><i class="fas fa-search"></i> "<?= htmlspecialchars($search_query) ?>" için arama sonuçları</h3>
            
            <?php if (empty($search_results)): ?>
                <div class="no-results">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Aramanızla eşleşen sonuç bulunamadı.</p>
                    <a href="/public/forum/" class="btn-secondary">Tüm Konuları Görüntüle</a>
                </div>
            <?php else: ?>
                <div class="search-results-list">
                    <?php foreach ($search_results as $result): ?>
                        <div class="search-result-item">
                            <div class="result-type">
                                <i class="fas fa-<?= $result['type'] === 'topic' ? 'comment-dots' : 'comments' ?>"></i>
                                <?= $result['type'] === 'topic' ? 'Konu' : 'Gönderi' ?>
                            </div>
                            <div class="result-content">
                                <h4><a href="<?= $result['url'] ?>"><?= htmlspecialchars($result['title']) ?></a></h4>
                                <p><?= htmlspecialchars(substr($result['content'], 0, 150)) ?>...</p>
                                <div class="result-meta">
                                    <span class="author">
                                        <span class="user-link" data-user-id="<?= $result['user_id'] ?>" 
                                              style="color: <?= $result['user_role_color'] ?? '#bd912a' ?>">
                                            <?= htmlspecialchars($result['username']) ?>
                                        </span>
                                    </span>
                                    <span class="date"><?= format_time_ago($result['created_at']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Forum Kategorileri -->
        <div class="forum-categories">
            <?php if (empty($categories)): ?>
                <div class="no-categories">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Erişilebilir kategori bulunmuyor</h3>
                    <?php if (!$is_logged_in): ?>
                        <p>Forum kategorilerini görüntülemek için giriş yapmanız gerekebilir.</p>
                        <div class="login-actions">
                            <a href="/public/register.php?mode=login" class="btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Giriş Yap
                            </a>
                            <a href="/public/register.php" class="btn-secondary">
                                <i class="fas fa-user-plus"></i> Kayıt Ol
                            </a>
                        </div>
                    <?php elseif (!$is_approved): ?>
                        <p>Forum kategorilerini görüntülemek için hesabınızın onaylanması gerekmektedir.</p>
                    <?php else: ?>
                        <p>Size uygun forum kategorisi henüz bulunmuyor.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <div class="category-card">
                        <div class="category-icon" style="color: <?= htmlspecialchars($category['color']) ?>">
                            <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
                        </div>
                        
                        <div class="category-content">
                            <div class="category-header">
                                <h3>
                                    <a href="/public/forum/category.php?slug=<?= urlencode($category['slug']) ?>" 
                                       style="color: <?= htmlspecialchars($category['color']) ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </a>
                                </h3>
                            </div>
                            
                            <p class="category-description">
                                <?= htmlspecialchars($category['description']) ?>
                            </p>
                        </div>
                        
                        <div class="category-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($category['topic_count']) ?></div>
                                <div class="stat-label">Konu</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($category['post_count']) ?></div>
                                <div class="stat-label">Gönderi</div>
                            </div>
                        </div>
                        
                        <div class="category-latest">
                            <?php if ($category['last_topic_title']): ?>
                                <div class="latest-topic">
                                    <div class="latest-title">
                                        <a href="/public/forum/topic.php?id=<?= $category['last_topic_id'] ?>">
                                            <?= htmlspecialchars(substr($category['last_topic_title'], 0, 30)) ?>
                                            <?= strlen($category['last_topic_title']) > 30 ? '...' : '' ?>
                                        </a>
                                    </div>
                                    <div class="latest-meta">
                                        <span class="user-link" data-user-id="<?= $category['last_post_user_id'] ?>"
                                              style="color: <?= $category['last_post_user_role_color'] ?? '#bd912a' ?>">
                                            <?= htmlspecialchars($category['last_post_username']) ?>
                                        </span>
                                        <span class="time"><?= format_time_ago($category['last_post_at']) ?></span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-activity">
                                    <i class="fas fa-clock"></i>
                                    <span>Henüz aktivite yok</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Forum İstatistikleri ve Son Konular -->
        <div class="forum-sidebar-content">
            <div class="row">
                <div class="col-md-8">
                    <!-- Son Konular -->
                    <?php if (!empty($recent_topics)): ?>
                        <div class="recent-topics">
                            <h3><i class="fas fa-clock"></i> Son Konular</h3>
                            <div class="recent-topics-list">
                                <?php foreach ($recent_topics as $topic): ?>
                                    <div class="recent-topic-item">
                                        <div class="topic-category" style="background-color: <?= $topic['category_color'] ?>33">
                                            <span style="color: <?= $topic['category_color'] ?>">
                                                <?= htmlspecialchars($topic['category_name']) ?>
                                            </span>
                                        </div>
                                        <div class="topic-content">
                                            <h4>
                                                <a href="/public/forum/topic.php?id=<?= $topic['id'] ?>">
                                                    <?= htmlspecialchars($topic['title']) ?>
                                                </a>
                                            </h4>
                                            <div class="topic-meta">
                                                <span class="author">
                                                    <span class="user-link" data-user-id="<?= $topic['user_id'] ?>"
                                                          style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                                                        <?= htmlspecialchars($topic['author_username']) ?>
                                                    </span>
                                                </span>
                                                <span class="date"><?= format_time_ago($topic['updated_at']) ?></span>
                                                <span class="replies"><?= $topic['reply_count'] ?> yanıt</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <!-- Forum İstatistikleri -->
                    <div class="forum-stats-card">
                        <h3><i class="fas fa-chart-bar"></i> Forum İstatistikleri</h3>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-icon"><i class="fas fa-comments"></i></div>
                                <div class="stat-info">
                                    <div class="stat-number"><?= number_format($forum_stats['total_topics']) ?></div>
                                    <div class="stat-text">Toplam Konu</div>
                                </div>
                            </div>
                            
                            <div class="stat-box">
                                <div class="stat-icon"><i class="fas fa-comment-dots"></i></div>
                                <div class="stat-info">
                                    <div class="stat-number"><?= number_format($forum_stats['total_posts']) ?></div>
                                    <div class="stat-text">Toplam Gönderi</div>
                                </div>
                            </div>
                            
                            <div class="stat-box">
                                <div class="stat-icon"><i class="fas fa-users"></i></div>
                                <div class="stat-info">
                                    <div class="stat-number"><?= number_format($forum_stats['total_members']) ?></div>
                                    <div class="stat-text">Toplam Üye</div>
                                </div>
                            </div>
                            
                            <?php if ($forum_stats['newest_member']): ?>
                                <div class="stat-box newest-member">
                                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-text">En Yeni Üye</div>
                                        <div class="stat-member"><?= htmlspecialchars($forum_stats['newest_member']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Yeni Gelenler İçin Tavsiyeler -->
                    <?php if (!$is_logged_in): ?>
                        <div class="forum-welcome-card">
                            <h3><i class="fas fa-info-circle"></i> Foruma Hoş Geldiniz</h3>
                            <p>Tartışmalara katılmak ve yorum yapmak için hesap oluşturmanız gerekmektedir.</p>
                            <div class="welcome-actions">
                                <a href="/public/register.php" class="btn-primary">
                                    <i class="fas fa-user-plus"></i> Kayıt Ol
                                </a>
                                <a href="/public/register.php?mode=login" class="btn-secondary">
                                    <i class="fas fa-sign-in-alt"></i> Giriş Yap
                                </a>
                            </div>
                        </div>
                    <?php elseif (!$is_approved): ?>
                        <div class="forum-info-card">
                            <h3><i class="fas fa-clock"></i> Onay Bekleniyor</h3>
                            <p>Hesabınız admin onayı bekliyor. Onaylandıktan sonra forum tartışmalarına katılabileceksiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/public/forum/includes/user_popover.php'; ?>

<script src="/public/forum/js/forum.js"></script>

<?php
// Zaman formatlama fonksiyonu
function format_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    if ($time < 2592000) return floor($time/604800) . ' hafta önce';
    
    return date('d.m.Y', strtotime($datetime));
}

include BASE_PATH . '/src/includes/footer.php';
?>