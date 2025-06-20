<?php
// /forum/create_topic.php - Birleşik oluşturma/düzenleme sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/forum_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/htmlpurifier-4.15.0/library/HTMLPurifier.auto.php';

// Setup HTML Purifier
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.SafeIframe', true);
$config->set('URI.SafeIframeRegexp', '%^(https?://)?(www\.youtube(?:-nocookie)?\.com/embed/)%');
$purifier = new HTMLPurifier($config);

// Session kontrolü
check_user_session_validity();

// Yetki kontrolü
if (!is_user_logged_in()) {
    $_SESSION['error_message'] = "Bu işlem için giriş yapmalısınız.";
    header('Location: /register.php?mode=login');
    exit;
}

if (!is_user_approved()) {
    $_SESSION['error_message'] = "Bu işlem için hesabınızın onaylanmış olması gerekmektedir.";
    header('Location: ');
    exit;
}

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'];
$is_edit_mode = false;
$editing_topic = null;

// Edit mode kontrolü
$edit_topic_id = (int) ($_GET['edit'] ?? 0);
if ($edit_topic_id) {
    // Düzenleme modu
    $editing_topic = get_forum_topic_by_id($pdo, $edit_topic_id, $current_user_id);

    if (!$editing_topic) {
        $_SESSION['error_message'] = "Düzenlenecek konu bulunamadı veya erişim yetkiniz bulunmuyor.";
        header('Location: ');
        exit;
    }

    // Düzenleme yetkisi kontrolü
    if (!can_user_edit_forum_topic($pdo, $edit_topic_id, $current_user_id)) {
        $_SESSION['error_message'] = "Bu konuyu düzenleme yetkiniz bulunmuyor.";
        header('Location: topic.php?id=' . $edit_topic_id);
        exit;
    }

    $is_edit_mode = true;
    $selected_category_id = $editing_topic['category_id'];
} else {
    // Oluşturma modu
    if (!has_permission($pdo, 'forum.topic.create')) {
        $_SESSION['error_message'] = "Konu oluşturma yetkiniz bulunmuyor.";
        header('Location: ');
        exit;
    }

    $selected_category_id = (int) ($_GET['category_id'] ?? 0);
}

// Kategori bilgilerini al
$selected_category = null;
$categories = get_accessible_forum_categories($pdo, $current_user_id);

// Belirli kategori seçilmişse kontrol et
if ($selected_category_id) {
    foreach ($categories as $category) {
        if ($category['id'] == $selected_category_id) {
            if ($is_edit_mode || can_user_create_forum_topic($pdo, $selected_category_id, $current_user_id)) {
                $selected_category = $category;
                break;
            }
        }
    }

    if (!$selected_category && !$is_edit_mode) {
        $_SESSION['error_message'] = "Seçilen kategoride konu oluşturma yetkiniz bulunmuyor.";
        header('Location: ');
        exit;
    }
}

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Rate limiting kontrolü (sadece oluşturma için)
    if (!$is_edit_mode && !check_rate_limit('forum_topic_create', 3, 3600)) { // 1 saatte 3 konu
        $_SESSION['error_message'] = "Çok fazla konu oluşturuyorsunuz. Lütfen 1 saat bekleyin.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Form verilerini al
    $title = trim($_POST['title'] ?? '');
    $dirty_content = $_POST['content'] ?? '';
    $content = $purifier->purify($dirty_content); // Sanitize content
    $form_category_id = (int) ($_POST['category_id'] ?? 0);

    $errors = [];
    // TAG İŞLEME - YENİ EKLENEN KISIM
   $tags_input = trim($_POST['tags'] ?? '');
$tags = [];
if (!empty($tags_input)) {
    $tags = array_filter(array_map('trim', explode(',', $tags_input)));
    $tags = array_unique($tags); // Tekrar edenleri kaldır
    $tags = array_slice($tags, 0, 5); // Maksimum 5 etiket
    
    // Her etiketi validasyondan geçir
    $validated_tags = [];
    foreach ($tags as $tag) {
        // Tag validasyonu
        if (strlen($tag) > 0 && strlen($tag) <= 30 && preg_match('/^[a-zA-Z0-9çğıöşüÇĞIİÖŞÜ\s\-_]+$/u', $tag)) {
            $validated_tags[] = strtolower($tag);
        }
    }
    $tags = $validated_tags;
}
$tags_string = !empty($tags) ? implode(',', $tags) : null;

    // EDIT MODE İÇİN TAG YÜKLEME
    if ($is_edit_mode && !isset($_SESSION['form_data'])) {
        $form_data = [
            'title' => $editing_topic['title'],
            'content' => $editing_topic['content'],
            'category_id' => $editing_topic['category_id'],
            'tags' => $editing_topic['tags'] ?? '' // Mevcut tag'ları yükle
        ];
    } else {
        $form_data = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);
    }
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
            if ($category['id'] == $form_category_id) {
                if ($is_edit_mode || can_user_create_forum_topic($pdo, $form_category_id, $current_user_id)) {
                    $category_valid = true;
                    break;
                }
            }
        }

        if (!$category_valid) {
            $errors[] = "Seçilen kategoride işlem yapma yetkiniz bulunmuyor.";
        }
    }

    // Hata yoksa işlemi gerçekleştir
if (empty($errors)) {
    try {
        if ($is_edit_mode) {
            // Konu güncelleme
            $pdo->beginTransaction();
            
            try {
                // Konuyu güncelle
                $update_query = "
                    UPDATE forum_topics 
                    SET title = :title, content = :content, category_id = :category_id, tags = :tags, updated_at = NOW()
                    WHERE id = :topic_id AND user_id = :user_id
                ";
                
                $update_params = [
                    ':title' => $title,
                    ':content' => $content,
                    ':category_id' => $form_category_id,
                    ':tags' => $tags_string,
                    ':topic_id' => $edit_topic_id,
                    ':user_id' => $current_user_id
                ];
                
                // Admin/moderatör farklı kullanıcının konusunu düzenleyebilir
                if (has_permission($pdo, 'forum.topic.edit_all', $current_user_id)) {
                    unset($update_params[':user_id']);
                    $update_query = str_replace(' AND user_id = :user_id', '', $update_query);
                }
                
                $stmt = execute_safe_query($pdo, $update_query, $update_params);
                
                if ($stmt->rowCount() > 0) {
                    // Mevcut tag ilişkilerini sil
                    $delete_tags_query = "DELETE FROM forum_topic_tags WHERE topic_id = :topic_id";
                    execute_safe_query($pdo, $delete_tags_query, [':topic_id' => $edit_topic_id]);
                    
                    // Yeni tag'ları ekle
                    if (!empty($tags)) {
                        update_topic_tags($pdo, $edit_topic_id, $tags);
                    }
                    
                    $pdo->commit();
                    
                    // Audit log
                    audit_log($pdo, $current_user_id, 'forum_topic_edited', 'forum_topic', $edit_topic_id, 
                        ['title' => $editing_topic['title'], 'content' => $editing_topic['content'], 'tags' => $editing_topic['tags']], 
                        ['title' => $title, 'content' => $content, 'tags' => $tags_string]
                    );
                    
                    $_SESSION['success_message'] = "Konu başarıyla güncellendi.";
                    header('Location: topic.php?id=' . $edit_topic_id);
                    exit;
                } else {
                    $pdo->rollBack();
                    $errors[] = "Konu güncellenirken bir hata oluştu.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // Yeni konu oluşturma
            $topic_data = [
                'title' => $title,
                'content' => $content,
                'category_id' => $form_category_id,
                'tags' => $tags_string,
                'tags_array' => $tags // Tag array'i de gönder
            ];
            
            $topic_id = create_forum_topic($pdo, $topic_data, $current_user_id);
            
            if ($topic_id) {
                $_SESSION['success_message'] = "Konu başarıyla oluşturuldu.";
                header('Location: topic.php?id=' . $topic_id);
                exit;
            } else {
                $errors[] = "Konu oluşturulurken bir hata oluştu.";
            }
        }
    } catch (Exception $e) {
        error_log("Forum topic create/edit error: " . $e->getMessage());
        $errors[] = "Beklenmeyen bir hata oluştu.";
    }
}

// Form verilerini sakla (hata durumunda)
if (!empty($errors)) {
    $_SESSION['error_message'] = implode('<br>', $errors);
    $_SESSION['form_data'] = [
        'title' => $title,
        'content' => $content,
        'category_id' => $form_category_id,
        'tags' => $tags_string // Tag'ları da sakla
    ];
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

// Form verilerini al (hata durumunda veya edit mode'da)
if ($is_edit_mode && !isset($_SESSION['form_data'])) {
    $form_data = [
        'title' => $editing_topic['title'],
        'content' => $editing_topic['content'],
        'category_id' => $editing_topic['category_id']
    ];
} else {
    $form_data = $_SESSION['form_data'] ?? [];
    unset($_SESSION['form_data']);
}

// Sayfa başlığı
$page_title = ($is_edit_mode ? "Konu Düzenle" : "Yeni Konu Oluştur") . " - Forum - Ilgarion Turanis";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Forum', 'url' => '/forum/', 'icon' => 'fas fa-comments'],
];

if ($selected_category) {
    $breadcrumb_items[] = ['text' => $selected_category['name'], 'url' => 'category.php?slug=' . urlencode($selected_category['slug']), 'icon' => $selected_category['icon'] ?? 'fas fa-folder'];
}

if ($is_edit_mode) {
    $breadcrumb_items[] = ['text' => $editing_topic['title'], 'url' => 'topic.php?id=' . $edit_topic_id, 'icon' => 'fas fa-comment-dots'];
    $breadcrumb_items[] = ['text' => 'Düzenle', 'url' => '', 'icon' => 'fas fa-edit'];
} else {
    $breadcrumb_items[] = ['text' => 'Yeni Konu', 'url' => '', 'icon' => 'fas fa-plus'];
}

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="css/forum.css">
<link rel="stylesheet" href="css/create_topic.css">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <?= generate_forum_breadcrumb($breadcrumb_items) ?>

    <!-- Page Header -->
    <div class="create-topic-header">
        <h1>
            <i class="<?= $is_edit_mode ? 'fas fa-edit' : 'fas fa-plus' ?>"></i>
            <?= $is_edit_mode ? 'Konu Düzenle' : 'Yeni Konu Oluştur' ?>
        </h1>
        <p>
            <?php if ($is_edit_mode): ?>
                Konu başlığınızı ve içeriğinizi güncelleyin. Değişiklikleriniz kaydedildikten sonra konunun güncelleme
                tarihi değişecektir.
            <?php else: ?>
                Topluluğumuzla paylaşmak istediğiniz konuları buradan oluşturabilirsiniz. Konu başlığınızın açıklayıcı
                olmasına ve uygun kategoride oluşturmanıza dikkat edin.
            <?php endif; ?>
        </p>
    </div>

    <!-- Create/Edit Topic Form -->
    <div class="create-topic-form">
        <form id="createTopicForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <!-- Category Selection -->
            <div class="form-group">
                <label for="category_id">Kategori <span class="required">*</span></label>
                <select name="category_id" id="category_id" required <?= $is_edit_mode && !has_permission($pdo, 'forum.topic.edit_all', $current_user_id) ? 'disabled' : '' ?>>
                    <option value="">Kategori seçin...</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($is_edit_mode || can_user_create_forum_topic($pdo, $category['id'], $current_user_id)): ?>
                            <option value="<?= $category['id'] ?>" <?= ($form_data['category_id'] ?? $selected_category_id) == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <?php if ($is_edit_mode && !has_permission($pdo, 'forum.topic.edit_all', $current_user_id)): ?>
                    <input type="hidden" name="category_id"
                        value="<?= $form_data['category_id'] ?? $selected_category_id ?>">
                    <small class="form-help" style="color: var(--gold);">Kategori değiştirmek için yönetici yetkisi
                        gereklidir.</small>
                <?php else: ?>
                    <small class="form-help">Konunuzun en uygun olduğu kategoriyi seçin.</small>
                <?php endif; ?>
            </div>

            <!-- Topic Title -->
            <div class="form-group">
                <label for="title">Konu Başlığı <span class="required">*</span></label>
                <input type="text" name="title" id="title" required
                    value="<?= htmlspecialchars($form_data['title'] ?? '') ?>"
                    placeholder="Açıklayıcı bir başlık yazın..." minlength="3" maxlength="255">
                <div class="title-counter">
                    <span id="title-count">0</span> / 255 karakter
                </div>
                <small class="form-help">Konunuzu en iyi şekilde açıklayan bir başlık yazın.</small>
            </div>

            <!-- Topic Tags - Konu Başlığı ile İçerik arasına ekleyin -->
            <div class="form-group">
                <label for="tags">Etiketler</label>
                <div class="tags-input-container">
                    <input type="text" id="tagsInput" placeholder="Etiket yazın ve Enter'a basın..." maxlength="30">
                    <div class="tags-display" id="tagsDisplay"></div>
                    <input type="hidden" name="tags" id="tagsHidden"
                        value="<?= htmlspecialchars($form_data['tags'] ?? '') ?>">
                </div>
                <div class="tags-info">
                    <div class="tags-counter">
                        <span id="tags-count">0</span> / 5 etiket
                    </div>
                    <div class="tags-suggestions" id="tagsSuggestions"></div>
                </div>
                <small class="form-help">
                    Konunuzla ilgili etiketler ekleyin. Maksimum 5 etiket, her etiket en fazla 30 karakter olabilir.
                </small>
            </div>
            <!-- Content Editor -->
            <div class="form-group">
                <label for="content">Konu İçeriği <span class="required">*</span></label>

                <?php
                // Setup variables for the editor
                $textarea_name = 'content';
                $initial_content = $form_data['content'] ?? '';
                // Include the WYSIWYG editor
                require BASE_PATH . '/editor/wysiwyg_editor.php';
                ?>
                
                <small class="form-help">
                    İçeriğinizi yazarken paragraflar arasında boşluk bırakabilirsiniz.
                </small>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="<?= $is_edit_mode ? 'fas fa-save' : 'fas fa-paper-plane' ?>"></i>
                    <?= $is_edit_mode ? 'Değişiklikleri Kaydet' : 'Konuyu Oluştur' ?>
                </button>

                <?php if (!$is_edit_mode): ?>
                    <button type="button" class="btn-draft" onclick="saveDraft()">
                        <i class="fas fa-save"></i> Taslak Kaydet
                    </button>
                <?php endif; ?>

                <a href="<?php
                if ($is_edit_mode) {
                    echo 'topic.php?id=' . $edit_topic_id;
                } elseif ($selected_category) {
                    echo 'category.php?slug=' . urlencode($selected_category['slug']);
                } else {
                    echo '';
                }
                ?>" class="btn-cancel">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>

    <!-- Guidelines -->
    <div class="forum-guidelines">
        <h3><i class="fas fa-info-circle"></i> <?= $is_edit_mode ? 'Düzenleme' : 'Konu Oluşturma' ?> Kuralları</h3>
        <ul>
            <?php if ($is_edit_mode): ?>
                <li>Değişiklikleriniz konu listesinde güncelleme tarihi olarak görünecektir.</li>
                <li>Önemli değişiklikler yapıyorsanız konunun sonuna açıklama ekleyebilirsiniz.</li>
                <li>Başkalarının yanıtlarının anlamını bozacak şekilde değişiklik yapmayın.</li>
            <?php else: ?>
                <li>Forum kurallarına özen göstererek paylaşım yapın.</li>
                <li>Konunuzun başlığı açıklayıcı ve anlaşılır olmalıdır.</li>
                <li>Uygun kategoriyi seçmeye özen gösterin.</li>
            <?php endif; ?>
            <li>Spam, küfür veya hakaret içeren içerik paylaşmayın.</li>
            <li>Telif hakkı ihlali yapacak içerik yayınlamayın.</li>
            <li>Kişisel bilgi veya hassas veriler paylaşmayın.</li>
            <?php if (!$is_edit_mode): ?>
                <li>Konuyu yanlış kategoride açtıysanız moderatörler tarafından taşınabilir.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<script src="js/forum.js"></script>
<script src="js/create_topic.js"></script>
<script>
    // Edit mode için özel ayarlamalar
    document.addEventListener('DOMContentLoaded', function () {
        const isEditMode = <?= $is_edit_mode ? 'true' : 'false' ?>;

        if (isEditMode) {
            // Edit mode'da draft özelliklerini devre dışı bırak
            localStorage.removeItem('forum_topic_draft');

            // Form submit text'ini güncelle
            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.addEventListener('click', function () {
                    if (this.querySelector('i')) {
                        this.querySelector('i').className = 'fas fa-spinner fa-spin';
                    }
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
                });
            }
        }
    });
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>