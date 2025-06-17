<?php
// /articles/create.php

require_once '../src/config/database.php';
require_once '../src/functions/auth_functions.php';
require_once '../src/functions/role_functions.php';

// Session kontrolü - sadece aktif değilse başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Yetki kontrolü
require_permission($pdo, 'article.create');

$error_message = '';
$success_message = '';
$categories = [];
$roles = [];

try {
    // Kategorileri çek
    $category_query = "SELECT * FROM article_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
    $stmt = $pdo->prepare($category_query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rolleri çek (specific_roles seçeneği için)
    $roles_query = "SELECT id, name, color FROM roles ORDER BY priority ASC, name ASC";
    $stmt = $pdo->prepare($roles_query);
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Data fetch error: " . $e->getMessage());
    $error_message = "Veriler yüklenirken hata oluştu.";
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF kontrolü
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Güvenlik hatası. Lütfen sayfayı yenileyin.");
        }

        // Form verilerini al ve temizle
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $visibility = $_POST['visibility'] ?? 'public';
        $upload_type = $_POST['upload_type'] ?? 'file_upload';
        $google_docs_url = trim($_POST['google_docs_url'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $status = has_permission($pdo, 'article.approve') ? ($_POST['status'] ?? 'approved') : 'pending';
        $selected_roles = isset($_POST['selected_roles']) ? $_POST['selected_roles'] : [];

        // Validation
        if (empty($title)) {
            throw new Exception("Başlık boş bırakılamaz.");
        }

        if (strlen($title) > 255) {
            throw new Exception("Başlık çok uzun (max 255 karakter).");
        }

        if (!in_array($visibility, ['public', 'members_only', 'specific_roles'])) {
            throw new Exception("Geçersiz görünürlük ayarı.");
        }

        if (!in_array($upload_type, ['file_upload', 'google_docs_link'])) {
            throw new Exception("Geçersiz yükleme türü.");
        }

        if (!in_array($status, ['pending', 'approved', 'draft'])) {
            throw new Exception("Geçersiz durum seçimi.");
        }

        // Upload type'a göre validasyon
        if ($upload_type === 'file_upload') {
            // Dosya kontrolü
            if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("PDF dosyası seçilmedi veya yükleme hatası oluştu.");
            }
        } elseif ($upload_type === 'google_docs_link') {
            // Google Docs URL kontrolü
            if (empty($google_docs_url)) {
                throw new Exception("Google Docs linki boş bırakılamaz.");
            }
            
            if (!filter_var($google_docs_url, FILTER_VALIDATE_URL)) {
                throw new Exception("Geçerli bir URL formatı girin.");
            }
            
            // Google Docs/Drive link kontrolü
            if (!preg_match('/^https:\/\/(docs|drive)\.google\.com\//', $google_docs_url)) {
                throw new Exception("Sadece Google Docs veya Google Drive linkleri kabul edilir.");
            }
        }

        // Specific roles validasyonu
        if ($visibility === 'specific_roles') {
            if (empty($selected_roles) || !is_array($selected_roles)) {
                throw new Exception("Belirli roller seçildiğinde en az bir rol seçmelisiniz.");
            }
            
            // Seçilen rollerin geçerli olduğunu kontrol et
            $role_ids = array_map('intval', $selected_roles);
            $placeholders = str_repeat('?,', count($role_ids) - 1) . '?';
            $role_check_query = "SELECT COUNT(*) FROM roles WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($role_check_query);
            $stmt->execute($role_ids);
            
            if ($stmt->fetchColumn() != count($role_ids)) {
                throw new Exception("Seçilen rollerden bazıları geçersiz.");
            }
        }

        // Upload işlemleri
        $file_name = null;
        $file_path = null;
        $file_size = null;
        $detected_mime = null;
        $iframe_link = null;
        $thumbnail_path = null;

        // Thumbnail işleme (opsiyonel)
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbnail_file = $_FILES['thumbnail'];
            
            // Thumbnail validasyonu
            $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_thumbnail_size = 2 * 1024 * 1024; // 2MB
            
            $thumbnail_validation = validate_file_upload($thumbnail_file, $allowed_image_types, $max_thumbnail_size);
            if ($thumbnail_validation['success']) {
                // Thumbnail dosya yolu oluştur
                $upload_year = date('Y');
                $upload_month = date('m');
                $thumbnail_dir = "../uploads/articles/thumbnails/$upload_year/$upload_month/";
                
                if (!is_dir($thumbnail_dir)) {
                    mkdir($thumbnail_dir, 0755, true);
                }

                $thumbnail_extension = pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION);
                $thumbnail_filename = bin2hex(random_bytes(16)) . '.' . $thumbnail_extension;
                $thumbnail_path_full = $thumbnail_dir . $thumbnail_filename;
                $thumbnail_path = "/uploads/articles/thumbnails/$upload_year/$upload_month/" . $thumbnail_filename;

                // Thumbnail'i taşı
                if (move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_path_full)) {
                    // Başarılı
                } else {
                    $thumbnail_path = null; // Hata durumunda null yap
                }
            }
        }

        if ($upload_type === 'file_upload') {
            $file = $_FILES['pdf_file'];
            
            // Dosya güvenlik kontrolü
            $allowed_types = ['application/pdf'];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            $file_validation = validate_file_upload($file, $allowed_types, $max_size);
            if (!$file_validation['success']) {
                throw new Exception($file_validation['message']);
            }

            // Dosya boyutu ve MIME tipi kontrolü
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($detected_mime !== 'application/pdf') {
                throw new Exception("Sadece PDF dosyaları kabul edilir.");
            }

            // Dosya yolu oluştur
            $upload_year = date('Y');
            $upload_month = date('m');
            $upload_dir = "../uploads/articles/$upload_year/$upload_month/";
            
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Upload klasörü oluşturulamadı.");
                }
            }

            // Güvenli dosya adı oluştur
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_filename = bin2hex(random_bytes(16)) . '.' . $file_extension;
            $file_path_full = $upload_dir . $safe_filename;
            $file_path = "/uploads/articles/$upload_year/$upload_month/" . $safe_filename;

            // Dosyayı taşı
            if (!move_uploaded_file($file['tmp_name'], $file_path_full)) {
                throw new Exception("Dosya yüklenemedi.");
            }

            $file_name = $file['name'];
            $file_size = $file['size'];
            
            // Google Docs Viewer iframe linki oluştur
            $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $base_url .= $_SERVER['HTTP_HOST'];
            $full_file_url = $base_url . $file_path;
            
            $iframe_link = "https://docs.google.com/viewer?url=" . urlencode($full_file_url) . "&embedded=true";
            
        } elseif ($upload_type === 'google_docs_link') {
            // Google Docs linki için iframe oluştur
            if (strpos($google_docs_url, '/edit') !== false) {
                // Edit linkini preview linkine çevir
                $iframe_link = str_replace('/edit', '/preview', $google_docs_url);
            } else {
                $iframe_link = $google_docs_url;
            }
        }

        // Tags'i JSON formatına çevir
        $tags_array = array_filter(array_map('trim', explode(',', $tags)));
        $tags_json = !empty($tags_array) ? json_encode($tags_array) : null;

        // Veritabanına kaydet
        $pdo->beginTransaction();

        $insert_query = "
            INSERT INTO articles (
                title, description, upload_type, file_name, file_path, file_size, mime_type, 
                google_docs_url, iframe_link, thumbnail_path, uploaded_by, visibility, category_id, tags, status
            ) VALUES (
                :title, :description, :upload_type, :file_name, :file_path, :file_size, :mime_type,
                :google_docs_url, :iframe_link, :thumbnail_path, :uploaded_by, :visibility, :category_id, :tags, :status
            )
        ";

        $stmt = $pdo->prepare($insert_query);
        $result = $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':upload_type' => $upload_type,
            ':file_name' => $file_name,
            ':file_path' => $file_path,
            ':file_size' => $file_size,
            ':mime_type' => $detected_mime,
            ':google_docs_url' => $upload_type === 'google_docs_link' ? $google_docs_url : null,
            ':iframe_link' => $iframe_link,
            ':thumbnail_path' => $thumbnail_path,
            ':uploaded_by' => $_SESSION['user_id'],
            ':visibility' => $visibility,
            ':category_id' => $category_id,
            ':tags' => $tags_json,
            ':status' => $status
        ]);

        if (!$result) {
            throw new Exception("Veritabanı kayıt hatası.");
        }

        $article_id = $pdo->lastInsertId();

        // Specific roles ise rolleri kaydet
        if ($visibility === 'specific_roles' && !empty($selected_roles)) {
            foreach ($selected_roles as $role_id) {
                $role_insert_query = "INSERT INTO article_visibility_roles (article_id, role_id) VALUES (?, ?)";
                $stmt = $pdo->prepare($role_insert_query);
                $stmt->execute([$article_id, (int)$role_id]);
            }
        }

        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'article_created', 'article', $article_id, null, [
            'title' => $title,
            'upload_type' => $upload_type,
            'file_size' => $file_size,
            'status' => $status,
            'visibility' => $visibility,
            'selected_roles' => $visibility === 'specific_roles' ? $selected_roles : null
        ]);

        $pdo->commit();

        $success_message = "Makale başarıyla " . ($upload_type === 'file_upload' ? 'yüklendi' : 'eklendi') . "! " . 
                          ($status === 'pending' ? "Onay bekliyor." : "Hemen yayınlandı.");

        // 2 saniye sonra yönlendir
        header("refresh:2;url=/articles/");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        // Yüklenen dosyayı sil (eğer veritabanı hatası varsa)
        if (isset($file_path_full) && file_exists($file_path_full)) {
            unlink($file_path_full);
        }
        
        error_log("Article create error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Breadcrumb
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Makaleler', 'url' => '/articles/', 'icon' => 'fas fa-file-pdf'],
    ['text' => 'Yeni Makale', 'url' => '', 'icon' => 'fas fa-plus']
];

$page_title = 'Yeni Makale Yükle';
include '../src/includes/header.php';
include '../src/includes/navbar.php';
?>

<link rel="stylesheet" href="/articles/css/articles.css">

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
            <i class="fas fa-plus"></i> Yeni Makale
        </span>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1><i class="fas fa-plus"></i> Yeni Makale Yükle</h1>
                <p>PDF makale yükleyin ve toplulukla paylaşın</p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <!-- Main Form -->
    <div class="article-form-container">
        <form method="POST" enctype="multipart/form-data" id="articleForm" class="article-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-layout">
                <!-- Sol Kolon - Temel Bilgiler -->
                <div class="form-main">
                    
                    <!-- Başlık -->
                    <div class="form-group">
                        <label for="title" class="form-label required">
                            <i class="fas fa-heading"></i>
                            Makale Başlığı
                        </label>
                        <input type="text" 
                               class="form-input" 
                               id="title" 
                               name="title" 
                               maxlength="255" 
                               placeholder="Makalenizin başlığını girin..."
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               required>
                        <div class="form-help">Maximum 255 karakter</div>
                    </div>

                    <!-- Açıklama -->
                    <div class="form-group">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left"></i>
                            Açıklama
                        </label>
                        <textarea class="form-textarea" 
                                  id="description" 
                                  name="description" 
                                  rows="4" 
                                  placeholder="Makale hakkında kısa bir açıklama yazın..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        <div class="form-help">Makale hakkında detaylı bilgi verin</div>
                    </div>

                    <!-- Yükleme Türü -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-upload"></i>
                            Yükleme Türü
                        </label>
                        <div class="upload-type-selector">
                            <label class="upload-option">
                                <input type="radio" name="upload_type" value="file_upload" 
                                       <?= (($_POST['upload_type'] ?? 'file_upload') === 'file_upload') ? 'checked' : '' ?>>
                                <div class="option-content">
                                    <i class="fas fa-file-upload"></i>
                                    <span>Dosya Yükle</span>
                                    <small>PDF dosyası yükleyin</small>
                                </div>
                            </label>
                            <label class="upload-option">
                                <input type="radio" name="upload_type" value="google_docs_link" 
                                       <?= (($_POST['upload_type'] ?? '') === 'google_docs_link') ? 'checked' : '' ?>>
                                <div class="option-content">
                                    <i class="fab fa-google-drive"></i>
                                    <span>Google Docs/Drive Linki</span>
                                    <small>Mevcut Google dokümanı bağlayın</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- PDF Dosyası (Dosya yükleme için) -->
                    <div class="form-group" id="file-upload-section">
                        <label for="pdf_file" class="form-label required">
                            <i class="fas fa-file-pdf"></i>
                            PDF Dosyası
                        </label>
                        <input type="file" 
                               class="form-input file-input" 
                               id="pdf_file" 
                               name="pdf_file" 
                               accept=".pdf,application/pdf">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Sadece PDF dosyaları kabul edilir. Maximum 10MB.
                        </div>
                    </div>

                    <!-- Google Docs URL (Link paylaşımı için) -->
                    <div class="form-group" id="google-docs-section" style="display: none;">
                        <label for="google_docs_url" class="form-label required">
                            <i class="fab fa-google-drive"></i>
                            Google Docs/Drive Linki
                        </label>
                        <input type="url" 
                               class="form-input" 
                               id="google_docs_url" 
                               name="google_docs_url" 
                               placeholder="https://docs.google.com/document/d/..."
                               value="<?= htmlspecialchars($_POST['google_docs_url'] ?? '') ?>">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Google Docs veya Google Drive linkinizi buraya yapıştırın. Linkin herkese açık olduğundan emin olun.
                        </div>
                    </div>

                    <!-- Etiketler -->
                    <div class="form-group">
                        <label for="tags" class="form-label">
                            <i class="fas fa-tags"></i>
                            Etiketler
                        </label>
                        <input type="text" 
                               class="form-input" 
                               id="tags" 
                               name="tags" 
                               placeholder="etiket1, etiket2, etiket3..."
                               value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                        <div class="form-help">Virgülle ayırarak birden fazla etiket ekleyebilirsiniz</div>
                    </div>

                    <!-- Thumbnail (İsteğe bağlı) -->
                    <div class="form-group">
                        <label for="thumbnail" class="form-label">
                            <i class="fas fa-image"></i>
                            Thumbnail (İsteğe bağlı)
                        </label>
                        <input type="file" 
                               class="form-input file-input" 
                               id="thumbnail" 
                               name="thumbnail" 
                               accept="image/*">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Makale için özel bir kapak resmi yükleyebilirsiniz. JPG, PNG formatları kabul edilir. Max 2MB.
                        </div>
                    </div>

                </div>

                <!-- Sağ Kolon - Ayarlar -->
                <div class="form-sidebar">
                    
                    <!-- Kategori -->
                    <div class="form-group">
                        <label for="category_id" class="form-label">
                            <i class="fas fa-folder"></i>
                            Kategori
                        </label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">Kategori Seçin...</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        data-color="<?= htmlspecialchars($category['color']) ?>"
                                        <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Görünürlük -->
                    <div class="form-group">
                        <label for="visibility" class="form-label required">
                            <i class="fas fa-eye"></i>
                            Görünürlük
                        </label>
                        <select class="form-select" id="visibility" name="visibility" required>
                            <option value="public" <?= (($_POST['visibility'] ?? 'public') === 'public') ? 'selected' : '' ?>>
                                Herkese Açık
                            </option>
                            <option value="members_only" <?= (($_POST['visibility'] ?? '') === 'members_only') ? 'selected' : '' ?>>
                                Sadece Üyeler
                            </option>
                            <option value="specific_roles" <?= (($_POST['visibility'] ?? '') === 'specific_roles') ? 'selected' : '' ?>>
                                Belirli Rollere Özel
                            </option>
                        </select>
                    </div>

                    <!-- Rol Seçimi (Belirli rollere özel için) -->
                    <div class="form-group" id="roles-section" style="display: none;">
                        <label class="form-label required">
                            <i class="fas fa-users-cog"></i>
                            Erişebilecek Roller
                        </label>
                        <div class="roles-selector">
                            <?php foreach ($roles as $role): ?>
                                <label class="role-option">
                                    <input type="checkbox" 
                                           name="selected_roles[]" 
                                           value="<?= $role['id'] ?>"
                                           <?= (isset($_POST['selected_roles']) && in_array($role['id'], $_POST['selected_roles'])) ? 'checked' : '' ?>>
                                    <div class="role-content" style="border-left: 3px solid <?= htmlspecialchars($role['color']) ?>;">
                                        <span class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>;">
                                            <?= htmlspecialchars($role['name']) ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Bu makalenin sadece seçilen rollerdeki üyeler tarafından görülmesini istiyorsanız rolleri seçin.
                        </div>
                    </div>

                    <?php if (has_permission($pdo, 'article.approve')): ?>
                    <!-- Durum (Sadece admin görebilir) -->
                    <div class="form-group">
                        <label for="status" class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Durum
                        </label>
                        <select class="form-select" id="status" name="status">
                            <option value="pending" <?= (($_POST['status'] ?? 'pending') === 'pending') ? 'selected' : '' ?>>
                                Onay Bekliyor
                            </option>
                            <option value="approved" <?= (($_POST['status'] ?? '') === 'approved') ? 'selected' : '' ?>>
                                Onaylandı
                            </option>
                            <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>
                                Taslak
                            </option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Önizleme Alanı -->
                    <div class="preview-card">
                        <div class="preview-header">
                            <h6><i class="fas fa-eye"></i> Önizleme</h6>
                        </div>
                        <div class="preview-body" id="previewArea">
                            <div class="preview-empty">
                                <i class="fas fa-file-pdf"></i>
                                <p>PDF seçildiğinde önizleme burada görünecek</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bilgi Kartı -->
                    <div class="info-card">
                        <div class="info-header">
                            <h6><i class="fas fa-info-circle"></i> Bilgiler</h6>
                        </div>
                        <div class="info-body">
                            <ul class="info-list">
                                <li><i class="fas fa-check"></i> Maximum dosya boyutu: 10MB</li>
                                <li><i class="fas fa-check"></i> Sadece PDF formatı kabul edilir</li>
                                <li><i class="fas fa-check"></i> Dosya güvenlik taramasından geçer</li>
                                <li><i class="fas fa-check"></i> Google Docs Viewer ile görüntülenir</li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Form Butonları -->
            <div class="form-actions">
                <a href="/articles/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-upload"></i>
                        <span id="submitText">Makale Yükle</span>
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i>
                        Temizle
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal" id="uploadModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-upload"></i> Makale Yükleniyor...</h3>
        </div>
        <div class="modal-body">
            <div class="upload-progress">
                <div class="progress-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <p>Lütfen bekleyin, dosyanız işleniyor...</p>
                <div class="progress-bar">
                    <div class="progress-fill" id="uploadProgress"></div>
                </div>
                <div class="progress-text" id="progressText">0%</div>
            </div>
        </div>
    </div>
</div>

<script src="/articles/js/articles-create.js"></script>

<?php include '../src/includes/footer.php'; ?>