<?php
// public/forum/search.php - Güncellenmiş Layout ve Tag Fix

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

// Arama parametreleri
$search_query = trim($_GET['q'] ?? '');
$search_type = $_GET['type'] ?? 'all'; // all, topics, posts, tags, users, tag
$category_filter = (int)($_GET['category'] ?? 0);
$sort_by = $_GET['sort'] ?? 'relevance'; // relevance, date, title
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Minimum arama uzunluğu kontrolü
$min_search_length = 2;
$search_results = [];
$total_results = 0;
$search_info = [];

// Arama işlemi
if (!empty($search_query) && strlen($search_query) >= $min_search_length) {
    
    // Rate limiting
    if (!check_rate_limit('forum_search', 60, 300)) { // 5 dakikada 30 arama
        $_SESSION['error_message'] = "Çok fazla arama yapıyorsunuz. Lütfen 5 dakika bekleyin.";
        $search_query = '';
    } else {
        try {
            $search_results = perform_forum_search($pdo, $search_query, $search_type, $current_user_id, $category_filter, $sort_by, $per_page, $offset);
            $total_results = $search_results['total'] ?? 0;
            $search_info = $search_results['info'] ?? [];
            
            // Audit log - arama işlemi
            if ($current_user_id) {
                audit_log($pdo, $current_user_id, 'forum_search_performed', 'search', null, null, [
                    'query' => $search_query,
                    'type' => $search_type,
                    'results_count' => $total_results
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Forum search error: " . $e->getMessage());
            error_log("Search parameters - Query: $search_query, Type: $search_type, Category: $category_filter");
            $_SESSION['error_message'] = "Arama sırasında bir hata oluştu: " . $e->getMessage();
            $search_results = ['results' => [], 'total' => 0];
        }
    }
}

// Popüler tag'lar (öneriler için)
$popular_tags = get_popular_tags($pdo, 15);

// Toplam sayfa sayısı
$total_pages = $total_results > 0 ? ceil($total_results / $per_page) : 0;

// Sayfa başlığı
$page_title = "Forum Arama" . (!empty($search_query) ? " - " . htmlspecialchars($search_query) : "") . " - Ilgarion Turanis";

// Breadcrumb
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/public/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Forum', 'url' => '/public/forum/', 'icon' => 'fas fa-comments'],
    ['text' => 'Arama', 'url' => '', 'icon' => 'fas fa-search']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

/**
 * Detaylı forum arama fonksiyonu - DÜZELTME
 */
function perform_forum_search(PDO $pdo, string $query, string $type, ?int $user_id, int $category_filter, string $sort, int $limit, int $offset): array {
    if (strlen($query) < 2) {
        return ['results' => [], 'total' => 0, 'info' => []];
    }
    
    // Güvenlik için query temizle
    if (!validate_sql_input($query, 'general')) {
        throw new SecurityException("Invalid search query detected");
    }
    
    // Erişilebilir kategorileri al
    $accessible_categories = get_accessible_forum_categories($pdo, $user_id);
    $accessible_category_ids = array_column($accessible_categories, 'id');
    
    if (empty($accessible_category_ids)) {
        return ['results' => [], 'total' => 0, 'info' => []];
    }
    
    // Kategori filtresi uygula
    if ($category_filter > 0 && in_array($category_filter, $accessible_category_ids)) {
        $accessible_category_ids = [$category_filter];
    }
    
    $search_term = '%' . $query . '%';
    
    $results = [];
    $total = 0;
    $info = [
        'topics' => 0,
        'posts' => 0,
        'users' => 0,
        'tags' => 0
    ];
    
    // Sıralama belirleme
    $order_clause = match($sort) {
        'date' => 'ORDER BY created_at DESC',
        'title' => 'ORDER BY title ASC',
        default => 'ORDER BY relevance_score DESC, created_at DESC'
    };
    
    try {
        // TAG ARAMA FİXİ - Özel tag arama işlemi
        if ($type === 'tag') {
            // Tag araması - name veya slug ile ara
            $tag_results = get_topics_by_tag($pdo, $query, $user_id, $limit, $offset);
            
            // Kategori filtresi varsa uygula
            if ($category_filter > 0 && !empty($tag_results['topics'])) {
                $filtered_topics = array_filter($tag_results['topics'], function($topic) use ($category_filter) {
                    return $topic['category_id'] == $category_filter;
                });
                $tag_results['topics'] = array_values($filtered_topics);
                $tag_results['total'] = count($filtered_topics);
            }
            
            return $tag_results;
        }
        
        if ($type === 'all' || $type === 'topics') {
            // Konularda ara - Güvenli prepared statement ile
            $topics_query = "
                SELECT 'topic' as result_type, 'Konu' as type_label,
                       ft.id, ft.title, LEFT(ft.content, 300) as content,
                       ft.tags, ft.view_count, ft.reply_count,
                       CONCAT('/public/forum/topic.php?id=', ft.id) as url,
                       u.username, COALESCE(ur.color, '#bd912a') as user_role_color, 
                       ft.user_id, ft.created_at,
                       fc.name as category_name, COALESCE(fc.color, '#bd912a') as category_color,
                       (CASE 
                           WHEN ft.title LIKE ? THEN 3
                           WHEN ft.content LIKE ? THEN 2
                           WHEN ft.tags LIKE ? THEN 1
                           ELSE 0
                       END) as relevance_score
                FROM forum_topics ft
                JOIN forum_categories fc ON ft.category_id = fc.id
                JOIN users u ON ft.user_id = u.id
                LEFT JOIN user_roles uur ON u.id = uur.user_id
                LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                    SELECT MIN(r2.priority) FROM user_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = u.id
                )
                WHERE ft.category_id IN (" . implode(',', array_fill(0, count($accessible_category_ids), '?')) . ") 
                AND (ft.title LIKE ? OR ft.content LIKE ? OR ft.tags LIKE ?)
                HAVING relevance_score > 0
                {$order_clause}
            ";
            
            // Parametreleri hazırla
            $topic_params = array_merge(
                [$search_term, $search_term, $search_term], // relevance hesabı için
                $accessible_category_ids, // kategori filtreleri için
                [$search_term, $search_term, $search_term] // WHERE koşulu için
            );
            
            if ($type === 'topics') {
                $topics_query .= " LIMIT ? OFFSET ?";
                $topic_params = array_merge($topic_params, [$limit, $offset]);
            } else {
                $topics_query .= " LIMIT 10";
            }
            
            $stmt = $pdo->prepare($topics_query);
            $stmt->execute($topic_params);
            $topic_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_merge($results, $topic_results);
            $info['topics'] = count($topic_results);
        }
        
        if ($type === 'all' || $type === 'posts') {
            // Gönderilerde ara
            $posts_query = "
                SELECT 'post' as result_type, 'Gönderi' as type_label,
                       fp.id, ft.title, LEFT(fp.content, 300) as content,
                       '' as tags, 0 as view_count, 0 as reply_count,
                       CONCAT('/public/forum/topic.php?id=', ft.id, '#post-', fp.id) as url,
                       u.username, COALESCE(ur.color, '#bd912a') as user_role_color, 
                       fp.user_id, fp.created_at,
                       fc.name as category_name, COALESCE(fc.color, '#bd912a') as category_color,
                       (CASE 
                           WHEN fp.content LIKE ? THEN 2
                           WHEN ft.title LIKE ? THEN 1
                           ELSE 0
                       END) as relevance_score
                FROM forum_posts fp
                JOIN forum_topics ft ON fp.topic_id = ft.id
                JOIN forum_categories fc ON ft.category_id = fc.id
                JOIN users u ON fp.user_id = u.id
                LEFT JOIN user_roles uur ON u.id = uur.user_id
                LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                    SELECT MIN(r2.priority) FROM user_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = u.id
                )
                WHERE ft.category_id IN (" . implode(',', array_fill(0, count($accessible_category_ids), '?')) . ") 
                AND fp.content LIKE ?
                HAVING relevance_score > 0
                {$order_clause}
            ";
            
            $post_params = array_merge(
                [$search_term, $search_term], // relevance hesabı için
                $accessible_category_ids, // kategori filtreleri için
                [$search_term] // WHERE koşulu için
            );
            
            if ($type === 'posts') {
                $posts_query .= " LIMIT ? OFFSET ?";
                $post_params = array_merge($post_params, [$limit, $offset]);
            } else {
                $posts_query .= " LIMIT 5";
            }
            
            $stmt = $pdo->prepare($posts_query);
            $stmt->execute($post_params);
            $post_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_merge($results, $post_results);
            $info['posts'] = count($post_results);
        }
        
        if ($type === 'all' || $type === 'tags') {
            // Tag'larda ara
            $tags_query = "
                SELECT 'tag' as result_type, 'Etiket' as type_label,
                       ft.id, ft.name as title, CONCAT(ft.usage_count, ' konuda kullanılmış') as content,
                       '' as tags, ft.usage_count as view_count, 0 as reply_count,
                       CONCAT('/public/forum/search.php?q=', REPLACE(ft.name, ' ', '%20'), '&type=tags') as url,
                       '' as username, '#bd912a' as user_role_color, 0 as user_id, ft.created_at,
                       'Etiket' as category_name, '#bd912a' as category_color,
                       (CASE 
                           WHEN ft.name LIKE ? THEN 3
                           ELSE 0
                       END) as relevance_score
                FROM forum_tags ft
                WHERE ft.name LIKE ? AND ft.usage_count > 0
                HAVING relevance_score > 0
                ORDER BY ft.usage_count DESC, ft.name ASC
            ";
            
            $tag_params = [$search_term, $search_term];
            
            if ($type === 'tags') {
                $tags_query .= " LIMIT ? OFFSET ?";
                $tag_params = array_merge($tag_params, [$limit, $offset]);
            } else {
                $tags_query .= " LIMIT 5";
            }
            
            $stmt = $pdo->prepare($tags_query);
            $stmt->execute($tag_params);
            $tag_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_merge($results, $tag_results);
            $info['tags'] = count($tag_results);
        }
        
        if (($type === 'all' || $type === 'users') && $user_id) {
            // Kullanıcılarda ara (sadece giriş yapmış kullanıcılar için)
            $users_query = "
                SELECT 'user' as result_type, 'Kullanıcı' as type_label,
                       u.id, u.username as title, 
                       CONCAT('Oyun adı: ', COALESCE(u.ingame_name, 'Belirtilmemiş')) as content,
                       '' as tags, 0 as view_count, 0 as reply_count,
                       CONCAT('/public/view_profile.php?user_id=', u.id) as url,
                       u.username, COALESCE(ur.color, '#bd912a') as user_role_color, 
                       u.id as user_id, u.created_at,
                       'Kullanıcı' as category_name, COALESCE(ur.color, '#bd912a') as category_color,
                       (CASE 
                           WHEN u.username LIKE ? THEN 3
                           WHEN u.ingame_name LIKE ? THEN 2
                           ELSE 0
                       END) as relevance_score
                FROM users u
                LEFT JOIN user_roles uur ON u.id = uur.user_id
                LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                    SELECT MIN(r2.priority) FROM user_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = u.id
                )
                WHERE u.status = 'approved' 
                AND (u.username LIKE ? OR u.ingame_name LIKE ?)
                HAVING relevance_score > 0
                ORDER BY relevance_score DESC, u.username ASC
            ";
            
            $user_params = [$search_term, $search_term, $search_term, $search_term];
            
            if ($type === 'users') {
                $users_query .= " LIMIT ? OFFSET ?";
                $user_params = array_merge($user_params, [$limit, $offset]);
            } else {
                $users_query .= " LIMIT 5";
            }
            
            $stmt = $pdo->prepare($users_query);
            $stmt->execute($user_params);
            $user_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_merge($results, $user_results);
            $info['users'] = count($user_results);
        }
        
        // Toplam sonuç sayısını hesapla
        if ($type === 'all') {
            $total = count($results);
            // Relevance skoruna göre sırala
            usort($results, function($a, $b) use ($sort) {
                if ($sort === 'relevance') {
                    return $b['relevance_score'] <=> $a['relevance_score'];
                } elseif ($sort === 'date') {
                    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
                } else {
                    return strcmp($a['title'], $b['title']);
                }
            });
            
            // Sayfalama için slice
            $results = array_slice($results, $offset, $limit);
        } else {
            $total = count($results);
        }
        
        return [
            'results' => $results,
            'total' => $total,
            'info' => $info
        ];
        
    } catch (PDOException $e) {
        error_log("Forum search database error: " . $e->getMessage());
        error_log("Query: " . $query . ", Type: " . $type . ", Category: " . $category_filter);
        throw new DatabaseException("Search operation failed: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Forum search general error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Arama sonucu highlight fonksiyonu
 */
function highlight_search_terms(string $text, string $search_query): string {
    if (empty($search_query) || empty($text)) {
        return htmlspecialchars($text);
    }
    
    $text = htmlspecialchars($text);
    $search_terms = array_filter(explode(' ', $search_query));
    
    foreach ($search_terms as $term) {
        $term = trim($term);
        if (strlen($term) >= 2) {
            $pattern = '/(' . preg_quote($term, '/') . ')/iu';
            $text = preg_replace($pattern, '<mark class="search-highlight">$1</mark>', $text);
        }
    }
    
    return $text;
}

/**
 * Zaman formatı
 */
function format_search_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time / 60) . ' dk önce';
    if ($time < 86400) return floor($time / 3600) . ' sa önce';
    if ($time < 604800) return floor($time / 86400) . ' gün önce';
    
    return date('d.m.Y', strtotime($datetime));
}
?>

<link rel="stylesheet" href="/public/forum/css/forum.css">
<link rel="stylesheet" href="/public/forum/css/search.css">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <?= generate_forum_breadcrumb($breadcrumb_items) ?>

    <!-- Search Header -->
    <div class="search-header">
        <h1><i class="fas fa-search"></i> Forum Arama</h1>
        <p>Konular, gönderiler, etiketler ve kullanıcılar arasında arama yapın.</p>
    </div>

    <!-- Kompakt Search Form -->
    <div class="search-form-container">
        <form method="GET" action="/public/forum/search.php" class="search-form">
            <div class="search-input-group">
                <div class="search-input-container">
                    <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" 
                           placeholder="Arama yapmak istediğiniz kelimeyi girin..." 
                           class="search-input" maxlength="100" required>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <div class="search-filters">
                <div class="filter-group">
                    <label>Arama Türü</label>
                    <select name="type" class="filter-select">
                        <option value="all" <?= $search_type === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <option value="topics" <?= $search_type === 'topics' ? 'selected' : '' ?>>Konular</option>
                        <option value="posts" <?= $search_type === 'posts' ? 'selected' : '' ?>>Gönderiler</option>
                        <option value="tags" <?= $search_type === 'tags' ? 'selected' : '' ?>>Etiketler</option>
                        <?php if ($is_logged_in): ?>
                            <option value="users" <?= $search_type === 'users' ? 'selected' : '' ?>>Kullanıcılar</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Kategori</label>
                    <select name="category" class="filter-select">
                        <option value="0">Tüm Kategoriler</option>
                        <?php
                        $categories = get_accessible_forum_categories($pdo, $current_user_id);
                        $category_filter = (int)($_GET['category'] ?? 0);
                        foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sıralama</label>
                    <select name="sort" class="filter-select">
                        <option value="relevance" <?= $sort_by === 'relevance' ? 'selected' : '' ?>>İlgililik</option>
                        <option value="date" <?= $sort_by === 'date' ? 'selected' : '' ?>>Tarih</option>
                        <option value="title" <?= $sort_by === 'title' ? 'selected' : '' ?>>Başlık</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($search_query)): ?>
        <?php if (strlen($search_query) < $min_search_length): ?>
            <!-- Minimum uzunluk uyarısı -->
            <div class="search-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Arama terimi en az <?= $min_search_length ?> karakter olmalıdır.
            </div>
        <?php elseif (empty($search_results['results'])): ?>
            <!-- Sonuç bulunamadı -->
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Sonuç Bulunamadı</h3>
                <p><strong>"<?= htmlspecialchars($search_query) ?>"</strong> araması için hiçbir sonuç bulunamadı.</p>
                
                <div class="search-suggestions">
                    <h4>Öneriler:</h4>
                    <ul>
                        <li>Farklı anahtar kelimeler deneyin</li>
                        <li>Daha genel terimler kullanın</li>
                        <li>Yazım hatalarını kontrol edin</li>
                        <li>Farklı bir arama türü seçin</li>
                    </ul>
                </div>

                <?php if (!empty($popular_tags)): ?>
                    <div class="popular-tags-suggestion">
                        <h4>Popüler Etiketler:</h4>
                        <div class="tags-list">
                            <?php foreach ($popular_tags as $tag): ?>
                                <a href="/public/forum/search.php?q=<?= urlencode($tag['name']) ?>&type=tags" 
                                   class="suggestion-tag">
                                    <?= htmlspecialchars($tag['name']) ?> 
                                    <span><?= $tag['usage_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            
            <!-- Arama sonuçları -->
            <div class="search-results">
                <?php if ($search_type === 'tag' && isset($search_results['tag'])): ?>
                    <!-- Özel tag sayfası görünümü -->
                    <div class="tag-results-header">
                        <div class="tag-info">
                            <h2>
                                <i class="fas fa-tag"></i>
                                "<?= htmlspecialchars($search_results['tag']['name'] ?? $search_query) ?>" Etiketi
                            </h2>
                            <p><?= number_format($total_results) ?> konu bu etikete sahip</p>
                        </div>
                    </div>

                    <?php if (!empty($search_results['topics'])): ?>
                        <div class="tag-topics-list">
                            <?php foreach ($search_results['topics'] as $topic): ?>
                                <div class="topic-item">
                                    <div class="topic-header">
                                        <h3 class="topic-title">
                                            <a href="/public/forum/topic.php?id=<?= $topic['id'] ?>">
                                                <?php if ($topic['is_pinned']): ?>
                                                    <i class="fas fa-thumbtack text-warning"></i>
                                                <?php endif; ?>
                                                <?php if ($topic['is_locked']): ?>
                                                    <i class="fas fa-lock text-secondary"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($topic['title']) ?>
                                            </a>
                                        </h3>
                                        <div class="topic-meta">
                                            <span class="topic-category">
                                                <i class="<?= $topic['category_icon'] ?? 'fas fa-folder' ?>" 
                                                   style="color: <?= $topic['category_color'] ?? '#bd912a' ?>"></i>
                                                <a href="/public/forum/category.php?slug=<?= urlencode($topic['category_slug']) ?>"
                                                   style="color: <?= $topic['category_color'] ?? '#bd912a' ?>">
                                                    <?= htmlspecialchars($topic['category_name']) ?>
                                                </a>
                                            </span>
                                            <span class="topic-author">
                                                <i class="fas fa-user"></i>
                                                <span style="color: <?= $topic['author_role_color'] ?? '#bd912a' ?>">
                                                    <?= htmlspecialchars($topic['author_username']) ?>
                                                </span>
                                            </span>
                                            <span class="topic-date">
                                                <i class="fas fa-clock"></i>
                                                <?= format_search_time_ago($topic['created_at']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="topic-content">
                                        <p><?= htmlspecialchars(substr(strip_tags($topic['content']), 0, 200)) ?>...</p>
                                    </div>
                                    
                                    <div class="topic-stats">
                                        <span class="stat-item">
                                            <i class="fas fa-eye"></i>
                                            <?= number_format($topic['view_count']) ?> görüntüleme
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-comments"></i>
                                            <?= number_format($topic['reply_count']) ?> yanıt
                                        </span>
                                        <?php if (!empty($topic['tags'])): ?>
                                            <div class="topic-tags">
                                                <?php
                                                $tags = array_filter(array_map('trim', explode(',', $topic['tags'])));
                                                foreach (array_slice($tags, 0, 3) as $tag):
                                                ?>
                                                    <a href="/public/forum/search.php?q=<?= urlencode($tag) ?>&type=tags" 
                                                       class="topic-tag <?= $tag === $search_query ? 'active' : '' ?>">
                                                        <?= htmlspecialchars($tag) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Normal arama sonuçları -->
                    <div class="results-header">
                        <div class="results-info">
                            <h2>Arama Sonuçları</h2>
                            <p>
                                <strong>"<?= htmlspecialchars($search_query) ?>"</strong> için 
                                <span class="results-count"><?= number_format($total_results) ?></span> sonuç bulundu
                            </p>
                            
                            <?php if ($search_type === 'all' && !empty($search_info)): ?>
                                <div class="results-breakdown">
                                    <?php if ($search_info['topics'] > 0): ?>
                                        <span class="breakdown-item">
                                            <i class="fas fa-comment-dots"></i>
                                            <?= $search_info['topics'] ?> Konu
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($search_info['posts'] > 0): ?>
                                        <span class="breakdown-item">
                                            <i class="fas fa-comment"></i>
                                            <?= $search_info['posts'] ?> Gönderi
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($search_info['tags'] > 0): ?>
                                        <span class="breakdown-item">
                                            <i class="fas fa-tags"></i>
                                            <?= $search_info['tags'] ?> Etiket
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($search_info['users'] > 0): ?>
                                        <span class="breakdown-item">
                                            <i class="fas fa-users"></i>
                                            <?= $search_info['users'] ?> Kullanıcı
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="results-list">
                        <?php foreach ($search_results['results'] as $result): ?>
                            <div class="result-item result-<?= $result['result_type'] ?>">
                                <div class="result-icon">
                                    <?php
                                    $icon_map = [
                                        'topic' => 'fas fa-comment-dots',
                                        'post' => 'fas fa-comment',
                                        'tag' => 'fas fa-tag',
                                        'user' => 'fas fa-user'
                                    ];
                                    $icon = $icon_map[$result['result_type']] ?? 'fas fa-file';
                                    ?>
                                    <i class="<?= $icon ?>"></i>
                                </div>

                                <div class="result-content">
                                    <div class="result-header">
                                        <h3 class="result-title">
                                            <a href="<?= $result['url'] ?>">
                                                <?= highlight_search_terms($result['title'], $search_query) ?>
                                            </a>
                                        </h3>
                                        <span class="result-type-badge"><?= $result['type_label'] ?></span>
                                    </div>

                                    <div class="result-description">
                                        <?= highlight_search_terms($result['content'], $search_query) ?>
                                    </div>

                                    <div class="result-meta">
                                        <div class="result-meta-left">
                                            <?php if (!empty($result['username'])): ?>
                                                <span class="result-author">
                                                    <i class="fas fa-user"></i>
                                                    <span style="color: <?= $result['user_role_color'] ?? '#bd912a' ?>">
                                                        <?= htmlspecialchars($result['username']) ?>
                                                    </span>
                                                </span>
                                            <?php endif; ?>

                                            <?php if (!empty($result['category_name']) && $result['result_type'] !== 'user'): ?>
                                                <span class="result-category">
                                                    <i class="fas fa-folder"></i>
                                                    <span style="color: <?= $result['category_color'] ?? '#bd912a' ?>">
                                                        <?= htmlspecialchars($result['category_name']) ?>
                                                    </span>
                                                </span>
                                            <?php endif; ?>

                                            <span class="result-date">
                                                <i class="fas fa-clock"></i>
                                                <?= format_search_time_ago($result['created_at']) ?>
                                            </span>
                                        </div>

                                        <div class="result-meta-right">
                                            <?php if ($result['result_type'] === 'topic'): ?>
                                                <?php if ($result['view_count'] > 0): ?>
                                                    <span class="result-stat">
                                                        <i class="fas fa-eye"></i>
                                                        <?= number_format($result['view_count']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($result['reply_count'] > 0): ?>
                                                    <span class="result-stat">
                                                        <i class="fas fa-comments"></i>
                                                        <?= number_format($result['reply_count']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php elseif ($result['result_type'] === 'tag'): ?>
                                                <span class="result-stat">
                                                    <i class="fas fa-hashtag"></i>
                                                    <?= $result['view_count'] ?> kullanım
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($result['tags']) && $result['result_type'] === 'topic'): ?>
                                        <div class="result-tags">
                                            <?php
                                            $tags = array_filter(array_map('trim', explode(',', $result['tags'])));
                                            foreach (array_slice($tags, 0, 3) as $tag):
                                            ?>
                                                <a href="/public/forum/search.php?q=<?= urlencode($tag) ?>&type=tags" 
                                                   class="result-tag">
                                                    <?= htmlspecialchars($tag) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="search-pagination">
                        <div class="pagination-info">
                            Sayfa <?= $page ?> / <?= $total_pages ?> 
                            (Toplam <?= number_format($total_results) ?> sonuç)
                        </div>

                        <nav class="pagination-nav">
                            <?php
                            $base_url = "/public/forum/search.php?q=" . urlencode($search_query) . 
                                       "&type=" . urlencode($search_type) . 
                                       "&category=" . $category_filter . 
                                       "&sort=" . urlencode($sort_by);
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?= $base_url ?>&page=1" class="page-btn">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="page-btn">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="<?= $base_url ?>&page=<?= $i ?>" 
                                   class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="page-btn">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="<?= $base_url ?>&page=<?= $total_pages ?>" class="page-btn">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- İlk yüklenme - arama önerileri -->
        <div class="search-welcome">
            <div class="search-tips">
                <h3><i class="fas fa-lightbulb"></i> Arama İpuçları</h3>
                <div class="tips-grid">
                    <div class="tip-item">
                        <i class="fas fa-search"></i>
                        <h4>Kelime Arama</h4>
                        <p>Aradığınız kelimeyi yazın. Örnek: "mining", "trading"</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-quote-left"></i>
                        <h4>Tam İfade</h4>
                        <p>Tırnak içinde arayın: "star citizen"</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-filter"></i>
                        <h4>Filtreleme</h4>
                        <p>Türe göre sonuçları filtreleyin</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-tags"></i>
                        <h4>Etiket Arama</h4>
                        <p>Popüler etiketlere tıklayarak ilgili konuları bulun</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($popular_tags)): ?>
                <div class="popular-tags-section">
                    <h3><i class="fas fa-fire"></i> Popüler Etiketler</h3>
                    <div class="popular-tags-grid">
                        <?php foreach ($popular_tags as $tag): ?>
                            <a href="/public/forum/search.php?q=<?= urlencode($tag['name']) ?>&type=tags" 
                               class="popular-tag">
                                <span class="tag-name"><?= htmlspecialchars($tag['name']) ?></span>
                                <span class="tag-count"><?= number_format($tag['usage_count']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="/public/forum/js/forum.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>