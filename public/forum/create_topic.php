<?php
// public/forum/create_topic.php

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

// Yetki kontrolü
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Konu oluşturmak için giriş yapmalısınız.";
    header('Location: /public/register.php?mode=login');
    exit;
}

if (!is_user_approved()) {
    $_SESSION['error_message'] = "Hesabınız henüz onaylanmamış.";
    header('Location: /public/forum/');
    exit;
}

if (!has_permission($pdo, 'forum.topic.create')) {
    $_SESSION['error_message'] = "Konu oluşturma yetkiniz bulunmuyor.";
    header('Location: /public/forum/');
    exit;
}

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'];

// Kategori seçimi
$selected_category_id = (int)($_GET['category_id'] ?? 0);
$selected_category = null;

// Kategorileri çek
$categories = get_accessible_forum_categories($pdo, $current_user_id);

// Eğer belirli bir kategori seçilmişse kontrol et
if ($selected_category_id) {
    foreach ($categories as $category) {
        if ($category['id'] == $selected_category_id && $category['can_create_topic']) {
            $selected_category = $category;
            break;
        }
    }
    
    if (!$selected_category) {
        $_SESSION['error_message'] = "Bu kategoride konu oluşturma yetkiniz bulunmuyor.";
        header('Location: /public/forum/');
        exit;
    }
}

// Form işleme
$errors = [];
$form_data = [
    'title' => '',
    'content' => '',
    'category_id' => $selected_category_id,
    'tags' => '',
    'visibility' => 'public'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Güvenlik hatası. Lütfen tekrar deneyin.";
    } else {
        // Rate limiting kontrolü
        if (!check_rate_limit('forum_create_topic', 3, 300)) { // 5 dakikada 3 konu
            $errors[] = "Çok fazla konu oluşturuyorsunuz. Lütfen 5 dakika bekleyin.";
        } else {
            // Form verilerini al
            $form_data['title'] = trim($_POST['title'] ?? '');
            $form_data['content'] = trim($_POST['content'] ?? '');
            $form_data['category_id'] = (int)($_POST['category_id'] ?? 0);
            $form_data['tags'] = trim($_POST['tags'] ?? '');
            $form_data['visibility'] = $_POST['visibility'] ?? 'public';
            
            // Validasyon
            if (empty($form_data['title'])) {
                $errors[] = "Konu başlığı boş olamaz.";
            } elseif (strlen($form_data['title']) < 5) {
                $errors[] = "Konu başlığı en az 5 karakter olmalıdır.";
            } elseif (strlen($form_data['title']) > 200) {
                $errors[] = "Konu başlığı en fazla 200 karakter olabilir.";
            }
            
            if (empty($form_data['content'])) {
                $errors[] = "Konu içeriği boş olamaz.";
            } elseif (strlen($form_data['content']) < 10) {
                $errors[] = "Konu içeriği en az 10 karakter olmalıdır.";
            } elseif (strlen($form_data['content']) > 50000) {
                $errors[] = "Konu içeriği en fazla 50.000 karakter olabilir.";
            }
            
            if (!$form_data['category_id']) {
                $errors[] = "Kategori seçimi zorunludur.";
            } else {
                // Kategori kontrolü
                $category_found = false;
                foreach ($categories as $category) {
                    if ($category['id'] == $form_data['category_id'] && $category['can_create_topic']) {
                        $category_found = true;
                        $selected_category = $category;
                        break;
                    }
                }
                
                if (!$category_found) {
                    $errors[] = "Seçilen kategoride konu oluşturma yetkiniz yok.";
                }
            }
            
            // Görünürlük kontrolü
            $allowed_visibilities = ['public'];
            if (has_permission($pdo, 'forum.view_members_only', $current_user_id)) {
                $allowed_visibilities[] = 'members_only';
            }
            if (has_permission($pdo, 'forum.view_faction_only', $current_user_id)) {
                $allowed_visibilities[] = 'faction_only';
            }
            
            if (!in_array($form_data['visibility'], $allowed_visibilities)) {
                $errors[] = "Seçilen görünürlük seviyesi için yetkiniz yok.";
            }
            
            // Etiket kontrolü
            if (!empty($form_data['tags'])) {
                $tag_names = array_map('trim', explode(',', $form_data['tags']));
                if (count($tag_names) > 5) {
                    $errors[] = "En fazla 5 etiket ekleyebilirsiniz.";
                }
                
                foreach ($tag_names as $tag) {
                    if (strlen($tag) > 30) {
                        $errors[] = "Etiketler en fazla 30 karakter olabilir.";
                        break;
                    }
                }
            }
            
            // Hata yoksa konuyu oluştur
            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Slug oluştur
                    $slug = create_forum_slug($form_data['title']);
                    
                    // Aynı slug varsa unique yap
                    $slug_check_query = "SELECT COUNT(*) FROM forum_topics WHERE slug = :slug";
                    $stmt = execute_safe_query($pdo, $slug_check_query, [':slug' => $slug]);
                    $slug_count = $stmt->fetchColumn();
                    
                    if ($slug_count > 0) {
                        $slug .= '-' . time();
                    }
                    
                    // Konuyu ekle
                    $insert_query = "
                        INSERT INTO forum_topics (category_id, user_id, title, slug, content, visibility, tags, created_at, updated_at)
                        VALUES (:category_id, :user_id, :title, :slug, :content, :visibility, :tags, NOW(), NOW())
                    ";
                    
                    $insert_params = [
                        ':category_id' => $form_data['category_id'],
                        ':user_id' => $current_user_id,
                        ':title' => $form_data['title'],
                        ':slug' => $slug,
                        ':content' => $form_data['content'],
                        ':visibility' => $form_data['visibility'],
                        ':tags' => $form_data['tags']
                    ];
                    
                    $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
                    $topic_id = $pdo->lastInsertId();
                    
                    // Etiketleri işle
                    if (!empty($form_data['tags'])) {
                        process_forum_tags($pdo, $topic_id, $form_data['tags']);
                    }
                    
                    $pdo->commit();
                    
                    // Audit log
                    audit_log($pdo, $current_user_id, 'forum_topic_created', 'forum_topic', $topic_id, null, [
                        'title' => $form_data['title'],
                        'category_id' => $form_data['category_id'],
                        'visibility' => $form_data['visibility']
                    ]);
                    
                    $_SESSION['success_message'] = "Konunuz başarıyla oluşturuldu.";
                    header('Location: /public/forum/topic.php?id=' . $topic_id);
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Forum topic creation error: " . $e->getMessage());
                    $errors[] = "Konu oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        }
    }
}

// Sayfa başlığı
$page_title = "Yeni Konu Oluştur - Forum - Ilgarion Turanis";

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
            <li class="breadcrumb-item">
                <a href="/public/forum/">
                    <i class="fas fa-comments"></i> Forum
                </a>
            </li>
            <?php if ($selected_category): ?>
                <li class="breadcrumb-item">
                    <a href="/public/forum/category.php?slug=<?= urlencode($selected_category['slug']) ?>" 
                       style="color: <?= htmlspecialchars($selected_category['color']) ?>">
                        <?= htmlspecialchars($selected_category['name']) ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active">
                <i class="fas fa-plus"></i> Yeni Konu
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="create-topic-header">
        <h1><i class="fas fa-plus"></i> Yeni Konu Oluştur</h1>
        <p>Topluluğumuzla paylaşmak istediğiniz konuyu burada oluşturabilirsiniz.</p>
    </div>

    <!-- Hata Mesajları -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="create-topic-form-container">
        <form action="" method="POST" class="create-topic-form">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <!-- Kategori Seçimi -->
            <div class="form-group">
                <label for="category_id" class="form-label">
                    <i class="fas fa-folder"></i> Kategori *
                </label>
                <select id="category_id" name="category_id" required class="form-control">
                    <option value="">Kategori seçin</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category['can_create_topic']): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= ($form_data['category_id'] == $category['id']) ? 'selected' : '' ?>
                                    data-color="<?= htmlspecialchars($category['color']) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">Konunuzu uygun kategoriye yerleştirin.</div>
            </div>

            <!-- Konu Başlığı -->
            <div class="form-group">
                <label for="title" class="form-label">
                    <i class="fas fa-heading"></i> Konu Başlığı *
                </label>
                <input type="text" id="title" name="title" required 
                       value="<?= htmlspecialchars($form_data['title']) ?>"
                       placeholder="Konunuzun başlığını girin..."
                       maxlength="200" class="form-control">
                <div class="form-help">Açıklayıcı ve dikkat çekici bir başlık seçin (5-200 karakter).</div>
            </div>

            <!-- Görünürlük -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-eye"></i> Görünürlük
                </label>
                <div class="visibility-options">
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="public" 
                               <?= ($form_data['visibility'] == 'public') ? 'checked' : '' ?>>
                        <div class="option-content">
                            <i class="fas fa-globe"></i>
                            <span class="option-title">Herkese Açık</span>
                            <span class="option-desc">Tüm ziyaretçiler görebilir</span>
                        </div>
                    </label>
                    
                    <?php if (has_permission($pdo, 'forum.view_members_only', $current_user_id)): ?>
                        <label class="visibility-option">
                            <input type="radio" name="visibility" value="members_only"
                                   <?= ($form_data['visibility'] == 'members_only') ? 'checked' : '' ?>>
                            <div class="option-content">
                                <i class="fas fa-users"></i>
                                <span class="option-title">Sadece Üyeler</span>
                                <span class="option-desc">Onaylı üyeler görebilir</span>
                            </div>
                        </label>
                    <?php endif; ?>
                    
                    <?php if (has_permission($pdo, 'forum.view_faction_only', $current_user_id)): ?>
                        <label class="visibility-option">
                            <input type="radio" name="visibility" value="faction_only"
                                   <?= ($form_data['visibility'] == 'faction_only') ? 'checked' : '' ?>>
                            <div class="option-content">
                                <i class="fas fa-shield-alt"></i>
                                <span class="option-title">Fraksiyona Özel</span>
                                <span class="option-desc">Sadece fraksiyon üyeleri görebilir</span>
                            </div>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <!-- İçerik -->
            <div class="form-group">
                <label for="content" class="form-label">
                    <i class="fas fa-edit"></i> Konu İçeriği *
                </label>
                <textarea id="content" name="content" required 
                          placeholder="Konunuzun detaylarını buraya yazın..."
                          rows="12" class="form-control"><?= htmlspecialchars($form_data['content']) ?></textarea>
                <div class="form-help">
                    Konunuzu detaylı bir şekilde açıklayın (10-50.000 karakter).
                    <span class="char-counter">
                        <span id="char-count">0</span> / 50.000
                    </span>
                </div>
            </div>

            <!-- Etiketler -->
            <div class="form-group">
                <label for="tags" class="form-label">
                    <i class="fas fa-tags"></i> Etiketler
                </label>
                <input type="text" id="tags" name="tags" 
                       value="<?= htmlspecialchars($form_data['tags']) ?>"
                       placeholder="Etiketleri virgülle ayırın (örn: strateji, savaş, keşif)"
                       class="form-control">
                <div class="form-help">Konunuzu kategorize etmek için etiketler ekleyin (en fazla 5 adet, her biri max 30 karakter).</div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <div class="action-buttons">
                    <button type="submit" class="btn-create">
                        <i class="fas fa-paper-plane"></i> Konuyu Oluştur
                    </button>
                    <button type="button" class="btn-preview" onclick="previewTopic()">
                        <i class="fas fa-eye"></i> Önizleme
                    </button>
                    <a href="<?= $selected_category ? '/public/forum/category.php?slug=' . urlencode($selected_category['slug']) : '/public/forum/' ?>" 
                       class="btn-cancel">
                        <i class="fas fa-times"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Önizleme -->
    <div id="topic-preview" class="topic-preview" style="display: none;">
        <h3><i class="fas fa-eye"></i> Önizleme</h3>
        <div class="preview-content">
            <div class="preview-header">
                <h4 id="preview-title">Konu Başlığı</h4>
                <div class="preview-meta">
                    <span class="preview-category" id="preview-category"></span>
                    <span class="preview-visibility" id="preview-visibility"></span>
                </div>
                <div class="preview-tags" id="preview-tags"></div>
            </div>
            <div class="preview-body">
                <div id="preview