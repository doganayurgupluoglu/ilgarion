<?php
// forum/create_category.php - Forum kategorisi oluşturma sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
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

// Yetki kontrolü
if (!has_permission($pdo, 'forum.category.manage', $current_user_id)) {
    // Yetkisiz erişim durumunda ana sayfaya yönlendir
    header('Location: index.php');
    exit;
}

// Sayfa başlığı
$page_title = "Kategori Oluştur - Forum - Ilgarion Turanis";

// Form gönderildi mi kontrol et
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $slug = create_slug($name);
    $icon = trim($_POST['icon'] ?? 'fas fa-comments');
    $color = trim($_POST['color'] ?? '#bd912a');
    $display_order = (int)($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $visibility = $_POST['visibility'] ?? 'public';
    $role_ids = $_POST['role_ids'] ?? [];

    // Form validasyonu
    $errors = [];

    if (empty($name)) {
        $errors[] = "Kategori adı boş olamaz.";
    }

    if (strlen($name) > 100) {
        $errors[] = "Kategori adı en fazla 100 karakter olabilir.";
    }

    if (strlen($description) > 1000) {
        $errors[] = "Kategori açıklaması en fazla 1000 karakter olabilir.";
    }

    // Slug kontrolü
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_categories WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Bu kategori adı zaten kullanılıyor. Lütfen farklı bir ad seçin.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Kategori oluştur
            $stmt = $pdo->prepare("
                INSERT INTO forum_categories 
                (name, description, slug, icon, color, display_order, is_active, visibility, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $name,
                $description,
                $slug,
                $icon,
                $color,
                $display_order,
                $is_active,
                $visibility
            ]);

            $category_id = $pdo->lastInsertId();

            // Rol bazlı erişim kontrolü için rol-kategori ilişkilerini ekle
            if ($visibility === 'faction_only' && !empty($role_ids)) {
                $role_stmt = $pdo->prepare("
                    INSERT INTO forum_category_visibility_roles 
                    (category_id, role_id) VALUES (?, ?)
                ");
                
                foreach ($role_ids as $role_id) {
                    $role_stmt->execute([$category_id, $role_id]);
                }
            }

            // Audit log için kayıt ekle
            add_audit_log($pdo, $current_user_id, 'create', 'forum_category', $category_id, null, [
                'name' => $name,
                'description' => $description,
                'visibility' => $visibility
            ]);

            $pdo->commit();
            $success_message = "Kategori başarıyla oluşturuldu.";
            
            // Başarılı oluşturma sonrası forum ana sayfasına yönlendir
            header("Location: index.php?success=category_created");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Kategori oluşturulurken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Tüm rolleri getir
$roles = get_all_roles($pdo);

// Sayfa içeriği
include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="css/forum.css">
<link rel="stylesheet" href="css/create_category.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="forum-page-container">
    <!-- Breadcrumb -->
    <nav class="forum-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="index.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="index.php">
                    <i class="fas fa-comments"></i> Forum
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-folder-plus"></i> Kategori Oluştur
            </li>
        </ol>
    </nav>

    <!-- Form Container -->
    <div class="create-category-container">
        <div class="form-header">
            <h1><i class="fas fa-folder-plus"></i> Yeni Forum Kategorisi Oluştur</h1>
            <p>Yeni bir forum kategorisi eklemek için aşağıdaki formu doldurun.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="create-category-form">
            <div class="form-group">
                <label for="name">Kategori Adı <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                <small class="form-text">Kategorinin görüntülenecek adı (max 100 karakter)</small>
            </div>

            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <small class="form-text">Kategori hakkında kısa bir açıklama (max 1000 karakter)</small>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="icon">Simge</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i id="icon-preview" class="fas fa-comments"></i></span>
                        </div>
                        <input type="text" id="icon" name="icon" class="form-control" 
                               value="<?= htmlspecialchars($_POST['icon'] ?? 'fas fa-comments') ?>">
                    </div>
                    <small class="form-text">Font Awesome simge sınıfı (örn: fas fa-comments)</small>
                </div>

                <div class="form-group col-md-6">
                    <label for="color">Renk</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text color-preview" id="color-preview"></span>
                        </div>
                        <input type="text" id="color" name="color" class="form-control" 
                               value="<?= htmlspecialchars($_POST['color'] ?? '#bd912a') ?>">
                    </div>
                    <small class="form-text">Kategori rengi (HEX formatında, örn: #bd912a)</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="display_order">Görüntüleme Sırası</label>
                    <input type="number" id="display_order" name="display_order" class="form-control" min="0" 
                           value="<?= htmlspecialchars($_POST['display_order'] ?? '0') ?>">
                    <small class="form-text">Kategorinin görüntülenme sırası (düşük değerler üstte gösterilir)</small>
                </div>

                <div class="form-group col-md-6">
                    <label for="visibility">Görünürlük</label>
                    <select id="visibility" name="visibility" class="form-control">
                        <option value="public" <?= (($_POST['visibility'] ?? '') === 'public') ? 'selected' : '' ?>>Herkese Açık</option>
                        <option value="members_only" <?= (($_POST['visibility'] ?? '') === 'members_only') ? 'selected' : '' ?>>Sadece Üyelere</option>
                        <option value="faction_only" <?= (($_POST['visibility'] ?? '') === 'faction_only') ? 'selected' : '' ?>>Sadece Belirli Rollere</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="roles-container" style="display: none;">
                <label>Erişim İzni Olan Roller</label>
                <div class="roles-list">
                    <?php foreach ($roles as $role): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="role_ids[]" 
                                   value="<?= $role['id'] ?>" id="role_<?= $role['id'] ?>"
                                   <?= in_array($role['id'], $_POST['role_ids'] ?? []) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                <span class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>">
                                    <?= htmlspecialchars($role['name']) ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="form-text">Bu kategoriye erişim izni olan rolleri seçin</small>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                       <?= (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Aktif</label>
                <small class="form-text">İşaretlenirse kategori aktif olur, aksi halde gizlenir</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Kategori Oluştur
                </button>
                <a href="index.php" class="btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Renk önizleme
    const colorInput = document.getElementById('color');
    const colorPreview = document.getElementById('color-preview');
    
    function updateColorPreview() {
        colorPreview.style.backgroundColor = colorInput.value;
    }
    
    colorInput.addEventListener('input', updateColorPreview);
    updateColorPreview();
    
    // İkon önizleme
    const iconInput = document.getElementById('icon');
    const iconPreview = document.getElementById('icon-preview');
    
    function updateIconPreview() {
        iconPreview.className = iconInput.value;
    }
    
    iconInput.addEventListener('input', updateIconPreview);
    
    // Görünürlük değiştiğinde rol seçimini göster/gizle
    const visibilitySelect = document.getElementById('visibility');
    const rolesContainer = document.getElementById('roles-container');
    
    function toggleRolesVisibility() {
        rolesContainer.style.display = visibilitySelect.value === 'faction_only' ? 'block' : 'none';
    }
    
    visibilitySelect.addEventListener('change', toggleRolesVisibility);
    toggleRolesVisibility();
});
</script>



<?php
// Slug oluşturma fonksiyonu
function create_slug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Audit log ekleme fonksiyonu
function add_audit_log($pdo, $user_id, $action, $target_type, $target_id, $old_values, $new_values) {
    $stmt = $pdo->prepare("
        INSERT INTO audit_log 
        (user_id, action, target_type, target_id, old_values, new_values, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $action,
        $target_type,
        $target_id,
        $old_values ? json_encode($old_values) : null,
        $new_values ? json_encode($new_values) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

include BASE_PATH . '/src/includes/footer.php';
?> 