<?php
// /articles/view.php

require_once '../src/config/database.php';
require_once '../src/functions/auth_functions.php';
require_once '../src/functions/role_functions.php';

// Session kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$article = null;
$error_message = '';
$user_can_edit = false;
$user_can_delete = false;
$user_has_liked = false;
$related_articles = [];

// Article ID kontrolü
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($article_id <= 0) {
    header('Location: /articles/?error=invalid_id');
    exit;
}

try {
    // Makaleyi detaylarıyla birlikte çek
    $article_query = "
        SELECT a.*, 
               u.username as uploaded_by_username,
               u.ingame_name as uploaded_by_ingame_name,
               c.name as category_name,
               c.color as category_color,
               c.icon as category_icon,
               DATE_FORMAT(a.upload_date, '%d.%m.%Y %H:%i') as formatted_upload_date,
               CASE 
                   WHEN a.file_size IS NOT NULL AND a.file_size < 1024 THEN CONCAT(a.file_size, ' B')
                   WHEN a.file_size IS NOT NULL AND a.file_size < 1048576 THEN CONCAT(ROUND(a.file_size/1024, 1), ' KB')
                   WHEN a.file_size IS NOT NULL THEN CONCAT(ROUND(a.file_size/1048576, 1), ' MB')
                   ELSE NULL
               END as formatted_file_size
        FROM articles a
        LEFT JOIN users u ON a.uploaded_by = u.id
        LEFT JOIN article_categories c ON a.category_id = c.id
        WHERE a.id = ?
    ";
    
    $stmt = $pdo->prepare($article_query);
    $stmt->execute([$article_id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        header('Location: /articles/?error=not_found');
        exit;
    }

    // Görünürlük kontrolü
    $can_view = false;
    
    if ($article['visibility'] === 'public') {
        $can_view = true;
    } elseif ($article['visibility'] === 'members_only') {
        $can_view = is_user_logged_in();
    } elseif ($article['visibility'] === 'specific_roles') {
        if (is_user_logged_in()) {
            // Kullanıcının bu makaleyi görebileceği rolleri kontrol et
            $role_check_query = "
                SELECT COUNT(*) 
                FROM article_visibility_roles avr
                JOIN user_roles ur ON avr.role_id = ur.role_id
                WHERE avr.article_id = ? AND ur.user_id = ?
            ";
            $stmt = $pdo->prepare($role_check_query);
            $stmt->execute([$article_id, $_SESSION['user_id']]);
            $can_view = $stmt->fetchColumn() > 0;
        }
    }

    // Admin/moderator her zaman görebilir
    if (is_user_logged_in() && has_permission($pdo, 'article.view_all')) {
        $can_view = true;
    }

    if (!$can_view) {
        header('Location: /articles/?error=access_denied');
        exit;
    }

    // Görüntülenme sayısını artır (sadece farklı kullanıcılar için)
    if (is_user_logged_in()) {
        // Bu kullanıcı bugün bu makaleyi görüntüledi mi?
        $view_check_query = "
            SELECT COUNT(*) FROM article_views 
            WHERE article_id = ? AND user_id = ? AND DATE(viewed_at) = CURDATE()
        ";
        $stmt = $pdo->prepare($view_check_query);
        $stmt->execute([$article_id, $_SESSION['user_id']]);
        
        if ($stmt->fetchColumn() == 0) {
            // Görüntüleme kaydı ekle
            $view_insert_query = "
                INSERT INTO article_views (article_id, user_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($view_insert_query);
            $stmt->execute([
                $article_id, 
                $_SESSION['user_id'], 
                $_SERVER['REMOTE_ADDR'] ?? '', 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            // View count güncelle
            $update_view_query = "UPDATE articles SET view_count = view_count + 1 WHERE id = ?";
            $stmt = $pdo->prepare($update_view_query);
            $stmt->execute([$article_id]);
        }
    } else {
        // Anonim kullanıcı için IP bazlı kontrol
        $view_check_query = "
            SELECT COUNT(*) FROM article_views 
            WHERE article_id = ? AND ip_address = ? AND user_id IS NULL AND DATE(viewed_at) = CURDATE()
        ";
        $stmt = $pdo->prepare($view_check_query);
        $stmt->execute([$article_id, $_SERVER['REMOTE_ADDR'] ?? '']);
        
        if ($stmt->fetchColumn() == 0) {
            $view_insert_query = "
                INSERT INTO article_views (article_id, user_id, ip_address, user_agent) 
                VALUES (?, NULL, ?, ?)
            ";
            $stmt = $pdo->prepare($view_insert_query);
            $stmt->execute([
                $article_id, 
                $_SERVER['REMOTE_ADDR'] ?? '', 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            $update_view_query = "UPDATE articles SET view_count = view_count + 1 WHERE id = ?";
            $stmt = $pdo->prepare($update_view_query);
            $stmt->execute([$article_id]);
            
            // Article array'ı güncelle
            $article['view_count']++;
        }
    }

    // Kullanıcı yetkilerini kontrol et
    if (is_user_logged_in()) {
        // Kendi makalesi mi?
        $is_owner = ($article['uploaded_by'] == $_SESSION['user_id']);
        
        // Edit yetkisi
        $user_can_edit = $is_owner && has_permission($pdo, 'article.edit_own') || 
                        has_permission($pdo, 'article.edit_all');
        
        // Delete yetkisi
        $user_can_delete = $is_owner && has_permission($pdo, 'article.delete_own') || 
                          has_permission($pdo, 'article.delete_all');

        // Beğeni durumu
        if (has_permission($pdo, 'article.like')) {
            $like_check_query = "SELECT COUNT(*) FROM article_likes WHERE article_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($like_check_query);
            $stmt->execute([$article_id, $_SESSION['user_id']]);
            $user_has_liked = $stmt->fetchColumn() > 0;
        }
    }

    // Benzer makaleler (aynı kategoriden)
    if ($article['category_id']) {
        $related_query = "
            SELECT a.id, a.title, a.description, a.thumbnail_path, a.upload_date, a.view_count, a.like_count,
                   u.username as uploaded_by_username,
                   DATE_FORMAT(a.upload_date, '%d.%m.%Y') as formatted_date
            FROM articles a
            LEFT JOIN users u ON a.uploaded_by = u.id
            WHERE a.category_id = ? AND a.id != ? AND a.status = 'approved'
            ORDER BY a.upload_date DESC
            LIMIT 4
        ";
        $stmt = $pdo->prepare($related_query);
        $stmt->execute([$article['category_id'], $article_id]);
        $related_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Tags'i parse et
    $tags = [];
    if ($article['tags']) {
        $tags = json_decode($article['tags'], true) ?? [];
    }

} catch (Exception $e) {
    error_log("Article view error: " . $e->getMessage());
    $error_message = "Makale yüklenirken bir hata oluştu.";
}

// Page title
$page_title = $article ? htmlspecialchars($article['title']) . ' - Makaleler' : 'Makale Bulunamadı';

// Breadcrumb
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Makaleler', 'url' => '/articles/', 'icon' => 'fas fa-file-pdf'],
    ['text' => $article ? $article['title'] : 'Makale', 'url' => '', 'icon' => 'fas fa-eye']
];

include '../src/includes/header.php';
include '../src/includes/navbar.php';
?>

<link rel="stylesheet" href="/articles/css/articles-view.css">

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/index.php" class="breadcrumb-item">
            <i class="fas fa-home"></i> Ana Sayfa
        </a>
        <span class="breadcrumb-separator">/</span>
        <a href="/articles/" class="breadcrumb-item">
            <i class="fas fa-file-pdf"></i> Makaleler
        </a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">
            <i class="fas fa-eye"></i> <?= $article ? htmlspecialchars($article['title']) : 'Makale' ?>
        </span>
    </nav>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php elseif ($article): ?>
        
        <!-- Article Header -->
        <div class="article-header">
            <div class="article-header-content">
                <div class="article-main-info">
                    <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
                    
                    <div class="article-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($article['uploaded_by_username']) ?></span>
                            <?php if ($article['uploaded_by_ingame_name']): ?>
                                <small>(<?= htmlspecialchars($article['uploaded_by_ingame_name']) ?>)</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?= $article['formatted_upload_date'] ?></span>
                        </div>
                        
                        <?php if ($article['category_name']): ?>
                        <div class="meta-item">
                            <i class="<?= htmlspecialchars($article['category_icon']) ?>"></i>
                            <span class="category-badge" style="background-color: <?= htmlspecialchars($article['category_color']) ?>;">
                                <?= htmlspecialchars($article['category_name']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <i class="fas fa-eye"></i>
                            <span><?= number_format($article['view_count']) ?> görüntülenme</span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-heart"></i>
                            <span><?= number_format($article['like_count']) ?> beğeni</span>
                        </div>
                        
                        <?php if ($article['formatted_file_size']): ?>
                        <div class="meta-item">
                            <i class="fas fa-file"></i>
                            <span><?= $article['formatted_file_size'] ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($article['description']): ?>
                    <div class="article-description">
                        <?= nl2br(htmlspecialchars($article['description'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Tags -->
                    <?php if (!empty($tags)): ?>
                    <div class="article-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Article Actions -->
                <div class="article-actions">
                    <?php if (is_user_logged_in() && has_permission($pdo, 'article.like')): ?>
                    <button class="btn-action btn-like <?= $user_has_liked ? 'liked' : '' ?>" 
                            onclick="toggleLike(<?= $article['id'] ?>)" 
                            id="likeBtn">
                        <i class="fas fa-heart"></i>
                        <span id="likeText"><?= $user_has_liked ? 'Beğenildi' : 'Beğen' ?></span>
                        <span id="likeCount">(<?= $article['like_count'] ?>)</span>
                    </button>
                    <?php endif; ?>

                    <?php if ($article['upload_type'] === 'file_upload' && has_permission($pdo, 'article.download')): ?>
                    <a href="/articles/download.php?id=<?= $article['id'] ?>" 
                       class="btn-action btn-download" 
                       onclick="recordDownload(<?= $article['id'] ?>)">
                        <i class="fas fa-download"></i>
                        <span>İndir</span>
                    </a>
                    <?php endif; ?>

                    <button class="btn-action btn-share" onclick="shareArticle()">
                        <i class="fas fa-share-alt"></i>
                        <span>Paylaş</span>
                    </button>

                    <?php if ($user_can_edit): ?>
                    <a href="/articles/edit.php?id=<?= $article['id'] ?>" class="btn-action btn-edit">
                        <i class="fas fa-edit"></i>
                        <span>Düzenle</span>
                    </a>
                    <?php endif; ?>

                    <?php if ($user_can_delete): ?>
                    <button class="btn-action btn-delete" onclick="deleteArticle(<?= $article['id'] ?>)">
                        <i class="fas fa-trash"></i>
                        <span>Sil</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Article Content -->
        <div class="article-content">
            <!-- Thumbnail -->
            <?php if ($article['thumbnail_path']): ?>
            <div class="article-thumbnail">
                <img src="<?= htmlspecialchars($article['thumbnail_path']) ?>" 
                     alt="<?= htmlspecialchars($article['title']) ?>" 
                     class="thumbnail-image">
            </div>
            <?php endif; ?>

            <!-- PDF Viewer -->
            <div class="article-viewer">
                <div class="viewer-header">
                    <h3>
                        <i class="fas fa-file-pdf"></i>
                        <?= $article['upload_type'] === 'file_upload' ? 'PDF Dökümanı' : 'Google Dokümanı' ?>
                    </h3>
                    <div class="viewer-controls">
                        <button onclick="toggleFullscreen()" class="viewer-btn">
                            <i class="fas fa-expand"></i>
                            Tam Ekran
                        </button>
                        <?php if ($article['upload_type'] === 'google_docs_link'): ?>
                        <a href="<?= htmlspecialchars($article['google_docs_url']) ?>" 
                           target="_blank" 
                           class="viewer-btn">
                            <i class="fas fa-external-link-alt"></i>
                            Yeni Sekmede Aç
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="viewer-container" id="viewerContainer">
                    <iframe src="<?= htmlspecialchars($article['iframe_link']) ?>" 
                            class="document-iframe"
                            id="documentIframe"
                            frameborder="0">
                    </iframe>
                </div>
            </div>
        </div>

        <!-- Related Articles -->
        <?php if (!empty($related_articles)): ?>
        <div class="related-articles">
            <h3>
                <i class="fas fa-files"></i>
                Benzer Makaleler
            </h3>
            <div class="related-articles-grid">
                <?php foreach ($related_articles as $related): ?>
                <div class="related-article-card">
                    <a href="/articles/view.php?id=<?= $related['id'] ?>" class="related-article-link">
                        <?php if ($related['thumbnail_path']): ?>
                            <img src="<?= htmlspecialchars($related['thumbnail_path']) ?>" 
                                 alt="<?= htmlspecialchars($related['title']) ?>"
                                 class="related-thumbnail">
                        <?php else: ?>
                            <div class="related-placeholder">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="related-content">
                            <h4><?= htmlspecialchars($related['title']) ?></h4>
                            <?php if ($related['description']): ?>
                                <p><?= htmlspecialchars(substr($related['description'], 0, 100)) ?>...</p>
                            <?php endif; ?>
                            <div class="related-meta">
                                <span><i class="fas fa-eye"></i> <?= number_format($related['view_count']) ?></span>
                                <span><i class="fas fa-heart"></i> <?= number_format($related['like_count']) ?></span>
                                <span><i class="fas fa-calendar"></i> <?= $related['formatted_date'] ?></span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script src="/articles/js/articles-view.js"></script>

<?php include '../src/includes/footer.php'; ?>