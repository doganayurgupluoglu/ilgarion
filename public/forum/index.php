<?php
// public/forum/index.php - Forum Ana Sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';

// Oturum kontrolü (opsiyonel - misafirler de görebilir)
if (is_user_logged_in()) {
    check_user_session_validity();
}

$page_title = "Forum Tartışma Panelları - Ilgarion Turanis";
$current_user_id = $_SESSION['user_id'] ?? null;

// Forum kategorilerini ve istatistikleri al
$forum_categories = get_accessible_forum_categories($pdo, $current_user_id);
$forum_stats = get_forum_statistics($pdo, $current_user_id);
$recent_topics = get_recent_forum_topics($pdo, 5, $current_user_id);

// Kullanıcının forum yetkilerini kontrol et
$can_create_category = $current_user_id && has_permission($pdo, 'forum.category.create', $current_user_id);

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.forum-page {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.forum-header {
    margin-bottom: 2rem;
    text-align: center;
}

.forum-title {
    color: var(--gold);
    font-size: 2.5rem;
    font-weight: 300;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.forum-subtitle {
    color: var(--light-grey);
    font-size: 1.1rem;
    line-height: 1.6;
    max-width: 800px;
    margin: 0 auto;
}

.breadcrumb {
    background: rgba(34, 34, 34, 0.6);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 2rem;
    font-size: 0.9rem;
}

.breadcrumb-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
}

.breadcrumb-item:not(:last-child)::after {
    content: '›';
    margin-left: 0.75rem;
    color: var(--gold);
    font-weight: bold;
}

.breadcrumb-link {
    color: var(--turquase);
    text-decoration: none;
    transition: color 0.2s ease;
}

.breadcrumb-link:hover {
    color: var(--light-gold);
}

.breadcrumb-current {
    color: var(--lighter-grey);
    font-weight: 500;
}

.forum-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 2rem;
    align-items: start;
}

.forum-main {
    min-width: 0;
}

.forum-sidebar {
    background: rgba(34, 34, 34, 0.8);
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    position: sticky;
    top: 2rem;
}

.category-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.category-card {
    background: rgba(42, 42, 42, 0.9);
    border: 1px solid var(--darker-gold-2);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.category-card:hover {
    border-color: var(--gold);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(189, 145, 42, 0.15);
}

.category-header {
    padding: 1.25rem 1.5rem;
    border-left: 4px solid;
    position: relative;
    cursor: pointer;
}

.category-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(189, 145, 42, 0.05), transparent);
    pointer-events: none;
}

.category-info {
    position: relative;
    z-index: 2;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.category-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--charcoal);
    font-weight: bold;
    flex-shrink: 0;
}

.category-name {
    color: var(--lighter-grey);
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0;
    line-height: 1.2;
}

.category-description {
    color: var(--light-grey);
    font-size: 0.95rem;
    line-height: 1.4;
    margin: 0;
}

.category-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 2fr;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: rgba(26, 26, 26, 0.6);
    border-top: 1px solid var(--darker-gold-2);
    font-size: 0.9rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 1.1rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--light-grey);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.last-activity {
    text-align: left;
}

.last-topic {
    color: var(--lighter-grey);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.last-topic-link {
    color: var(--turquase);
    text-decoration: none;
    transition: color 0.2s ease;
}

.last-topic-link:hover {
    color: var(--light-gold);
}

.last-meta {
    color: var(--light-grey);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.forum-stats {
    margin-bottom: 2rem;
}

.stats-header {
    color: var(--gold);
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-box {
    background: rgba(26, 26, 26, 0.6);
    border: 1px solid var(--darker-gold-2);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 0.25rem;
}

.stat-name {
    color: var(--light-grey);
    font-size: 0.85rem;
}

.recent-activity {
    margin-top: 2rem;
}

.recent-topics-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.recent-topic-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(189, 145, 42, 0.1);
}

.recent-topic-item:last-child {
    border-bottom: none;
}

.recent-topic-title {
    color: var(--turquase);
    text-decoration: none;
    font-size: 0.9rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.2s ease;
}

.recent-topic-title:hover {
    color: var(--light-gold);
}

.recent-topic-meta {
    color: var(--light-grey);
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.visibility-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.visibility-public {
    background: rgba(46, 204, 113, 0.2);
    color: #2ecc71;
    border: 1px solid rgba(46, 204, 113, 0.3);
}

.visibility-members {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.visibility-faction {
    background: rgba(155, 89, 182, 0.2);
    color: #9b59b6;
    border: 1px solid rgba(155, 89, 182, 0.3);
}

.create-topic-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--gold) 0%, var(--light-gold) 100%);
    color: var(--charcoal);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.create-topic-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(189, 145, 42, 0.4);
}

.login-prompt {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    color: #3498db;
    margin-bottom: 2rem;
}

.login-prompt a {
    color: var(--turquase);
    text-decoration: none;
    font-weight: 600;
}

.login-prompt a:hover {
    color: var(--light-gold);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--light-grey);
}

.empty-state-icon {
    font-size: 3rem;
    color: var(--darker-gold-1);
    margin-bottom: 1rem;
}

.empty-state-text {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.empty-state-subtext {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .forum-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .forum-sidebar {
        position: static;
        order: -1;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .forum-page {
        padding: 1rem 0.75rem;
    }
    
    .forum-title {
        font-size: 2rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .category-stats {
        grid-template-columns: 1fr;
        gap: 0.75rem;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .category-header {
        padding: 1rem;
    }
    
    .category-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .category-icon {
        width: 35px;
        height: 35px;
    }
    
    .category-name {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .breadcrumb {
        padding: 0.5rem 0.75rem;
    }
    
    .breadcrumb-list {
        font-size: 0.8rem;
    }
    
    .forum-sidebar {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <div class="forum-page">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item">
                    <a href="<?php echo get_auth_base_url(); ?>/index.php" class="breadcrumb-link">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <span class="breadcrumb-current">Forum</span>
                </li>
            </ol>
        </nav>

        <!-- Forum Header -->
        <header class="forum-header">
            <h1 class="forum-title">
                <i class="fas fa-comments"></i>
                Forum Tartışma Panelları
            </h1>
            <p class="forum-subtitle">
                Organizasyonumuzla ve Star Citizen evreniyle ilgili tartışmalara katılın, bilgi paylaşın ve 
                topluluğumuzla etkileşimde bulunun.
            </p>
        </header>

        <!-- Mesajlar -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Login Prompt for Guests -->
        <?php if (!$current_user_id): ?>
        <div class="login-prompt">
            <i class="fas fa-info-circle"></i>
            <strong>Misafir olarak forum kategorilerini görüntüleyebilirsiniz.</strong><br>
            Konu açmak ve yanıtlamak için <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=login">giriş yapın</a> 
            veya <a href="<?php echo get_auth_base_url(); ?>/auth.php?mode=register">kayıt olun</a>.
        </div>
        <?php endif; ?>

        <div class="forum-layout">
            <!-- Main Content -->
            <main class="forum-main">
                <!-- Categories List -->
                <?php if (empty($forum_categories)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="empty-state-text">Henüz erişebileceğiniz forum kategorisi bulunmuyor</div>
                        <div class="empty-state-subtext">
                            <?php if (!$current_user_id): ?>
                                Giriş yaparak daha fazla kategori görüntüleyebilirsiniz
                            <?php else: ?>
                                Yeni kategoriler eklendikçe burada görünecektir
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="category-list">
                        <?php foreach ($forum_categories as $category): ?>
                            <article class="category-card" data-category-id="<?php echo $category['id']; ?>">
                                <header class="category-header" style="border-left-color: <?php echo htmlspecialchars($category['color']); ?>">
                                    <div class="category-info">
                                        <div class="category-title">
                                            <div class="category-icon" style="background-color: <?php echo htmlspecialchars($category['color']); ?>">
                                                <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                                            </div>
                                            <div>
                                                <h2 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h2>
                                                <div class="visibility-indicator visibility-<?php echo $category['visibility'] === 'members_only' ? 'members' : ($category['visibility'] === 'faction_only' ? 'faction' : 'public'); ?>">
                                                    <?php
                                                    $visibility_icons = [
                                                        'public' => 'fas fa-globe',
                                                        'members_only' => 'fas fa-users',
                                                        'faction_only' => 'fas fa-shield-alt'
                                                    ];
                                                    $visibility_texts = [
                                                        'public' => 'Herkese Açık',
                                                        'members_only' => 'Sadece Üyeler',
                                                        'faction_only' => 'Sadece Fraksiyon'
                                                    ];
                                                    ?>
                                                    <i class="<?php echo $visibility_icons[$category['visibility']]; ?>"></i>
                                                    <?php echo $visibility_texts[$category['visibility']]; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                                    </div>
                                </header>
                                
                                <div class="category-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo number_format($category['topic_count']); ?></span>
                                        <span class="stat-label">Konular</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo number_format($category['post_count']); ?></span>
                                        <span class="stat-label">Mesajlar</span>
                                    </div>
                                    <div class="last-activity">
                                        <?php if ($category['last_topic_title']): ?>
                                            <div class="last-topic">
                                                <a href="<?php echo get_auth_base_url(); ?>/forum/topic.php?id=<?php echo $category['last_topic_id']; ?>" 
                                                   class="last-topic-link" onclick="event.stopPropagation();">
                                                    <?php echo htmlspecialchars($category['last_topic_title']); ?>
                                                </a>
                                            </div>
                                            <div class="last-meta">
                                                <span>
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($category['last_post_username'] ?? 'Bilinmeyen'); ?>
                                                </span>
                                                <?php if ($category['last_post_at']): ?>
                                                    <span data-timestamp="<?php echo strtotime($category['last_post_at']); ?>">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo date('d.m.Y H:i', strtotime($category['last_post_at'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="last-topic" style="color: var(--light-grey); font-style: italic;">
                                                Henüz konu yok
                                                <?php if ($category['can_create_topic']): ?>
                                                    <br>
                                                    <a href="<?php echo get_auth_base_url(); ?>/forum/create_topic.php?category=<?php echo $category['id']; ?>" 
                                                       style="color: var(--turquase); font-size: 0.8rem; text-decoration: none;"
                                                       onclick="event.stopPropagation();">
                                                        İlk konuyu sen aç!
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Create Category Button (Admin Only) -->
                <?php if ($can_create_category): ?>
                    <div style="margin-top: 2rem; text-align: center;">
                        <a href="<?php echo get_auth_base_url(); ?>/admin/forum_categories.php" class="create-topic-btn">
                            <i class="fas fa-plus"></i>
                            Kategori Yönetimi
                        </a>
                    </div>
                <?php endif; ?>
            </main>

            <!-- Sidebar -->
            <aside class="forum-sidebar">
                <!-- Forum Statistics -->
                <section class="forum-stats">
                    <h3 class="stats-header">
                        <i class="fas fa-chart-bar"></i>
                        İstatistikler
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <span class="stat-value"><?php echo number_format($forum_stats['total_topics']); ?></span>
                            <span class="stat-name">Toplam Konu</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><?php echo number_format($forum_stats['total_posts']); ?></span>
                            <span class="stat-name">Toplam Mesaj</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><?php echo number_format($forum_stats['total_members']); ?></span>
                            <span class="stat-name">Toplam Üye</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><?php echo $forum_stats['online_members']; ?></span>
                            <span class="stat-name">Çevrimiçi Üye</span>
                        </div>
                    </div>
                    
                    <?php if ($forum_stats['newest_member']): ?>
                    <div style="text-align: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--darker-gold-2);">
                        <div style="color: var(--light-grey); font-size: 0.85rem; margin-bottom: 0.5rem;">
                            En Yeni Üye
                        </div>
                        <div style="color: var(--turquase); font-weight: 600;">
                            <?php echo htmlspecialchars($forum_stats['newest_member']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Recent Activity -->
                <?php if (!empty($recent_topics)): ?>
                <section class="recent-activity">
                    <h3 class="stats-header">
                        <i class="fas fa-clock"></i>
                        Son Aktiviteler
                    </h3>
                    <ul class="recent-topics-list">
                        <?php foreach ($recent_topics as $topic): ?>
                            <li class="recent-topic-item">
                                <a href="<?php echo get_auth_base_url(); ?>/forum/topic.php?id=<?php echo $topic['id']; ?>" 
                                   class="recent-topic-title">
                                    <?php echo htmlspecialchars($topic['title']); ?>
                                </a>
                                <div class="recent-topic-meta">
                                    <span style="color: <?php echo htmlspecialchars($topic['category_color']); ?>;">
                                        <i class="fas fa-folder"></i>
                                        <?php echo htmlspecialchars($topic['category_name']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($topic['author_username']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d.m.Y', strtotime($topic['created_at'])); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <!-- Quick Actions for Logged Users -->
                <?php if ($current_user_id && is_user_approved()): ?>
                <section style="margin-top: 2rem;">
                    <h3 class="stats-header">
                        <i class="fas fa-bolt"></i>
                        Hızlı İşlemler
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if (has_permission($pdo, 'forum.topic.create', $current_user_id)): ?>
                            <a href="<?php echo get_auth_base_url(); ?>/forum/create_topic.php" 
                               class="create-topic-btn" style="justify-content: center;">
                                <i class="fas fa-plus"></i>
                                Yeni Konu Aç
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo get_auth_base_url(); ?>/forum/search.php" 
                           style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; 
                                  background: rgba(52, 152, 219, 0.2); color: #3498db; text-decoration: none; 
                                  border-radius: 6px; font-size: 0.9rem; justify-content: center; 
                                  border: 1px solid rgba(52, 152, 219, 0.3); transition: all 0.2s ease;">
                            <i class="fas fa-search"></i>
                            Forum'da Ara
                        </a>
                        
                        <a href="<?php echo get_auth_base_url(); ?>/forum/latest.php" 
                           style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; 
                                  background: rgba(155, 89, 182, 0.2); color: #9b59b6; text-decoration: none; 
                                  border-radius: 6px; font-size: 0.9rem; justify-content: center; 
                                  border: 1px solid rgba(155, 89, 182, 0.3); transition: all 0.2s ease;">
                            <i class="fas fa-history"></i>
                            Son Konular
                        </a>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Online Users (if available) -->
                <section style="margin-top: 2rem;">
                    <h3 class="stats-header">
                        <i class="fas fa-users"></i>
                        Kim Çevrimiçi?
                    </h3>
                    <div style="background: rgba(26, 26, 26, 0.6); border: 1px solid var(--darker-gold-2); 
                                border-radius: 8px; padding: 1rem; text-align: center; color: var(--light-grey); 
                                font-size: 0.9rem;">
                        <div style="margin-bottom: 0.5rem;">
                            <span style="color: var(--gold); font-weight: bold;"><?php echo $forum_stats['total_members']; ?></span> 
                            kayıtlı üye
                        </div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">
                            Şu anda <span style="color: var(--turquase);"><?php echo $forum_stats['online_members']; ?></span> üye çevrimiçi
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Kategori kartlarına tıklama olayı ekleme
    const categoryCards = document.querySelectorAll('.category-card');
    
    categoryCards.forEach(card => {
        const header = card.querySelector('.category-header');
        const categoryId = card.dataset.categoryId;
        
        if (header && categoryId) {
            header.addEventListener('click', function() {
                window.location.href = `<?php echo get_auth_base_url(); ?>/forum/category.php?id=${categoryId}`;
            });
        }
        
        // Hover efektleri
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
        });
    });
    
    // Son konu linklerine tıklama takibi
    const recentTopicLinks = document.querySelectorAll('.recent-topic-title');
    
    recentTopicLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Analytics veya izleme kodları buraya eklenebilir
            console.log('Forum topic clicked:', this.textContent);
        });
    });
    
    // Son kategori linklerine tıklama takibi
    const lastTopicLinks = document.querySelectorAll('.last-topic-link');
    
    lastTopicLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Analytics veya izleme kodları buraya eklenebilir
            console.log('Last topic clicked:', this.textContent);
        });
    });
    
    // Sidebar hızlı işlem butonları
    const quickActionBtns = document.querySelectorAll('.forum-sidebar a');
    
    quickActionBtns.forEach(btn => {
        if (!btn.classList.contains('create-topic-btn')) {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        }
    });
    
    // Dinamik zaman güncellemesi (opsiyonel)
    function updateTimestamps() {
        const timeElements = document.querySelectorAll('[data-timestamp]');
        
        timeElements.forEach(element => {
            const timestamp = parseInt(element.dataset.timestamp);
            const now = Math.floor(Date.now() / 1000);
            const diff = now - timestamp;
            
            let timeText = '';
            
            if (diff < 60) {
                timeText = 'Az önce';
            } else if (diff < 3600) {
                timeText = Math.floor(diff / 60) + ' dakika önce';
            } else if (diff < 86400) {
                timeText = Math.floor(diff / 3600) + ' saat önce';
            } else {
                timeText = Math.floor(diff / 86400) + ' gün önce';
            }
            
            element.textContent = timeText;
        });
    }
    
    // Her 30 saniyede bir zaman damgalarını güncelle
    setInterval(updateTimestamps, 30000);
    
    // Sayfa yüklendiğinde ilk güncelleme
    updateTimestamps();
    
    // Kategorilere data-category-id attribute ekleme
    const categoryCardsWithIds = document.querySelectorAll('.category-card');
    categoryCardsWithIds.forEach((card, index) => {
        // PHP'den kategori ID'lerini alabilmek için karta data attribute ekle
        const categoryHeader = card.querySelector('.category-header');
        if (categoryHeader) {
            categoryHeader.style.cursor = 'pointer';
        }
    });
    
    // Klavye navigasyonu
    document.addEventListener('keydown', function(e) {
        // Ctrl+F ile forum arama
        if (e.ctrlKey && e.key === 'f') {
            const searchLink = document.querySelector('a[href*="search"]');
            if (searchLink) {
                e.preventDefault();
                searchLink.click();
            }
        }
        
        // Ctrl+N ile yeni konu (eğer yetki varsa)
        if (e.ctrlKey && e.key === 'n') {
            const newTopicBtn = document.querySelector('a[href*="create_topic"]');
            if (newTopicBtn) {
                e.preventDefault();
                newTopicBtn.click();
            }
        }
    });
    
    // Responsive menü toggle (mobil için)
    function handleResponsiveLayout() {
        const forumLayout = document.querySelector('.forum-layout');
        const sidebar = document.querySelector('.forum-sidebar');
        
        if (window.innerWidth <= 1024 && sidebar) {
            // Mobilde sidebar'ı üste taşı
            sidebar.style.order = '-1';
        } else if (sidebar) {
            sidebar.style.order = '0';
        }
    }
    
    // Sayfa yüklendiğinde ve pencere boyutu değiştiğinde responsive kontrolü
    handleResponsiveLayout();
    window.addEventListener('resize', handleResponsiveLayout);
    
    // Kategori kartları için daha detaylı hover efektleri
    categoryCards.forEach(card => {
        const icon = card.querySelector('.category-icon');
        const originalTransform = icon ? icon.style.transform : '';
        
        card.addEventListener('mouseenter', function() {
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
                icon.style.transition = 'transform 0.3s ease';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            if (icon) {
                icon.style.transform = originalTransform;
            }
        });
    });
});

// Sayfa yüklenme animasyonları
window.addEventListener('load', function() {
    const categoryCards = document.querySelectorAll('.category-card');
    
    categoryCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>