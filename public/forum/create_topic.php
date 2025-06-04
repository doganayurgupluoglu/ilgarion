<?php
// public/forum/create_topic.php - Basitleştirilmiş Görünürlük

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
    $_SESSION['error_message'] = "Konu oluşturmak için hesabınızın onaylanmış olması gerekmektedir.";
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

// Kategori ID'si (URL parametresinden)
$category_id = (int)($_GET['category_id'] ?? 0);
$selected_category = null;

// Erişilebilir kategorileri çek
$categories = get_accessible_forum_categories($pdo, $current_user_id);

// Belirli kategori seçilmişse kontrol et
if ($category_id) {
    foreach ($categories as $category) {
        if ($category['id'] == $category_id && can_user_create_forum_topic($pdo, $category_id, $current_user_id)) {
            $selected_category = $category;
            break;
        }
    }
    
    if (!$selected_category) {
        $_SESSION['error_message'] = "Seçilen kategoride konu oluşturma yetkiniz bulunmuyor.";
        header('Location: /public/forum/');
        exit;
    }
}

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: /public/forum/create_topic.php' . ($category_id ? '?category_id=' . $category_id : ''));
        exit;
    }
    
    // Rate limiting kontrolü
    if (!check_rate_limit('forum_topic_create', 15, 3600)) { // 1 saatte 3 konu
        $_SESSION['error_message'] = "Çok fazla konu oluşturuyorsunuz. Lütfen 1 saat bekleyin.";
        header('Location: /public/forum/create_topic.php' . ($category_id ? '?category_id=' . $category_id : ''));
        exit;
    }
    
    // Form verilerini al
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $form_category_id = (int)($_POST['category_id'] ?? 0);
    
    $errors = [];
    
    // Validasyon
    if (empty($title)) {
        $errors[] = "Konu başlığı boş olamaz.";
    } elseif (strlen($title) < 3) {
        $errors[] = "Konu başlığı en az 3 karakter olmalıdır.";
    } elseif (strlen($title) > 255) {
        $errors[] = "Konu başlığı en fazla 255 karakter olabilir.";
    }
    
    if (empty($content)) {
        $errors[] = "Konu içeriği boş olamaz.";
    } elseif (strlen($content) < 10) {
        $errors[] = "Konu içeriği en az 10 karakter olmalıdır.";
    } elseif (strlen($content) > 50000) {
        $errors[] = "Konu içeriği en fazla 50.000 karakter olabilir.";
    }
    
    if (!$form_category_id) {
        $errors[] = "Kategori seçimi zorunludur.";
    } else {
        // Kategori erişim kontrolü
        $category_valid = false;
        foreach ($categories as $category) {
            if ($category['id'] == $form_category_id && can_user_create_forum_topic($pdo, $form_category_id, $current_user_id)) {
                $category_valid = true;
                break;
            }
        }
        
        if (!$category_valid) {
            $errors[] = "Seçilen kategoride konu oluşturma yetkiniz bulunmuyor.";
        }
    }
    
    // Hata yoksa konuyu oluştur
    if (empty($errors)) {
        $topic_data = [
            'title' => $title,
            'content' => $content,
            'category_id' => $form_category_id
        ];
        
        try {
            $topic_id = create_forum_topic($pdo, $topic_data, $current_user_id);
            
            if ($topic_id) {
                $_SESSION['success_message'] = "Konu başarıyla oluşturuldu.";
                header('Location: /public/forum/topic.php?id=' . $topic_id);
                exit;
            } else {
                $errors[] = "Konu oluşturulurken bir hata oluştu.";
            }
        } catch (Exception $e) {
            error_log("Forum topic creation error: " . $e->getMessage());
            $errors[] = "Konu oluşturulurken beklenmeyen bir hata oluştu.";
        }
    }
    
    // Hata varsa formu tekrar göster
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        $_SESSION['form_data'] = [
            'title' => $title,
            'content' => $content,
            'category_id' => $form_category_id
        ];
    }
}

// Form verilerini al (hata durumunda)
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Sayfa başlığı
$page_title = "Yeni Konu Oluştur - Forum - Ilgarion Turanis";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/public/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Forum', 'url' => '/public/forum/', 'icon' => 'fas fa-comments'],
];

if ($selected_category) {
    $breadcrumb_items[] = ['text' => $selected_category['name'], 'url' => '/public/forum/category.php?slug=' . urlencode($selected_category['slug']), 'icon' => $selected_category['icon'] ?? 'fas fa-folder'];
}

$breadcrumb_items[] = ['text' => 'Yeni Konu', 'url' => '', 'icon' => 'fas fa-plus'];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/public/forum/css/forum.css">
<link rel="stylesheet" href="/public/forum/css/create_topic.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <?= generate_forum_breadcrumb($breadcrumb_items) ?>

    <!-- Page Header -->
    <div class="create-topic-header">
        <h1><i class="fas fa-plus"></i> Yeni Konu Oluştur</h1>
        <p>Topluluğumuzla paylaşmak istediğiniz konuları buradan oluşturabilirsiniz. Konu başlığınızın açıklayıcı olmasına ve uygun kategoride oluşturmanıza dikkat edin.</p>
    </div>

    <!-- Create Topic Form -->
    <div class="create-topic-form">
        <form id="createTopicForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <!-- Category Selection -->
            <div class="form-group">
                <label for="category_id">Kategori <span class="required">*</span></label>
                <select name="category_id" id="category_id" required>
                    <option value="">Kategori seçin...</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if (can_user_create_forum_topic($pdo, $category['id'], $current_user_id)): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= ($form_data['category_id'] ?? $category_id) == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">Konunuzun en uygun olduğu kategoriyi seçin.</small>
            </div>
            
            <!-- Topic Title -->
            <div class="form-group">
                <label for="title">Konu Başlığı <span class="required">*</span></label>
                <input type="text" name="title" id="title" required 
                       value="<?= htmlspecialchars($form_data['title'] ?? '') ?>"
                       placeholder="Açıklayıcı bir başlık yazın..."
                       minlength="3" maxlength="255">
                <div class="title-counter">
                    <span id="title-count">0</span> / 255 karakter
                </div>
                <small class="form-help">Konunuzu en iyi şekilde açıklayan bir başlık yazın.</small>
            </div>
            
            <!-- Content Editor -->
            <div class="form-group">
                <label for="content">Konu İçeriği <span class="required">*</span></label>
                
                <!-- Editor Toolbar -->
                <div class="editor-toolbar">
                    <div class="toolbar-group">
                        <button type="button" class="editor-btn" onclick="insertBBCode('b')" title="Kalın">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('i')" title="İtalik">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('u')" title="Altı Çizili">
                            <i class="fas fa-underline"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('s')" title="Üstü Çizili">
                            <i class="fas fa-strikethrough"></i>
                        </button>
                    </div>
                    
                    <div class="toolbar-group">
                        <button type="button" class="editor-btn" onclick="insertBBCode('url')" title="Link Ekle">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('img')" title="Resim Ekle">
                            <i class="fas fa-image"></i>
                        </button>
                    </div>
                    
                    <div class="toolbar-group">
                        <button type="button" class="editor-btn" onclick="insertBBCode('quote')" title="Alıntı">
                            <i class="fas fa-quote-left"></i>
                        </button>
                        <button type="button" class="editor-btn" onclick="insertBBCode('code')" title="Kod Bloğu">
                            <i class="fas fa-code"></i>
                        </button>
                    </div>
                    
                    <div class="toolbar-group">
                        <select id="colorSelect" onchange="insertColorCode()" title="Renk">
                            <option value="">Renk</option>
                            <option value="red" style="color: red;">Kırmızı</option>
                            <option value="blue" style="color: blue;">Mavi</option>
                            <option value="green" style="color: green;">Yeşil</option>
                            <option value="orange" style="color: orange;">Turuncu</option>
                            <option value="purple" style="color: purple;">Mor</option>
                        </select>
                        
                        <select id="sizeSelect" onchange="insertSizeCode()" title="Boyut">
                            <option value="">Boyut</option>
                            <option value="8px">Çok Küçük</option>
                            <option value="10px">Küçük</option>
                            <option value="12px">Normal</option>
                            <option value="14px">Büyük</option>
                            <option value="18px">Çok Büyük</option>
                        </select>
                    </div>
                </div>
                
                <textarea name="content" id="content" rows="15" required 
                          placeholder="Konu içeriğinizi buraya yazın... BBCode kullanabilirsiniz."
                          minlength="10" maxlength="50000"><?= htmlspecialchars($form_data['content'] ?? '') ?></textarea>
                
                <div class="content-info">
                    <div class="char-counter">
                        <span id="content-count">0</span> / 50000 karakter
                    </div>
                    <button type="button" class="btn-preview" onclick="previewContent()">
                        <i class="fas fa-eye"></i> Önizle
                    </button>
                </div>
                
                <small class="form-help">
                    BBCode kullanabilirsiniz: [b]kalın[/b], [i]italik[/i], [url=link]bağlantı[/url], vb.
                </small>
            </div>
            
            <!-- Preview Area -->
            <div id="content-preview" class="content-preview" style="display: none;">
                <h4><i class="fas fa-eye"></i> İçerik Önizlemesi</h4>
                <div class="preview-content"></div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Konuyu Oluştur
                </button>
                
                <button type="button" class="btn-draft" onclick="saveDraft()">
                    <i class="fas fa-save"></i> Taslak Kaydet
                </button>
                
                <a href="<?= $selected_category ? '/public/forum/category.php?slug=' . urlencode($selected_category['slug']) : '/public/forum/' ?>" 
                   class="btn-cancel">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>
    
    <!-- Guidelines -->
    <div class="forum-guidelines">
        <h3><i class="fas fa-info-circle"></i> Konu Oluşturma Kuralları</h3>
        <ul>
            <li>Konunuzun başlığı açıklayıcı ve anlaşılır olmalıdır.</li>
            <li>Uygun kategoriyi seçmeye özen gösterin.</li>
            <li>Spam, küfür veya hakaret içeren içerik paylaşmayın.</li>
            <li>Telif hakkı ihlali yapacak içerik yayınlamayın.</li>
            <li>Kişisel bilgi veya hassas veriler paylaşmayın.</li>
            <li>Konuyu yanlış kategoride açtıysanız moderatörler tarafından taşınabilir.</li>
        </ul>
    </div>
</div>

<script src="/public/forum/js/forum.js"></script>
<script src="/public/forum/js/create_topic.js"></script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>