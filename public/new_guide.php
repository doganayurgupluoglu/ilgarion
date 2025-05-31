<?php
// public/new_guide.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/guide_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

$page_title = "Yeni Rehber Oluştur";
$form_action = "create_guide";
$submit_button_text = "Rehberi Oluştur";
$guide_id_to_edit = null;

$current_user_id = $_SESSION['user_id'] ?? null;
$can_edit_all_guides = has_permission($pdo, 'guide.edit_all', $current_user_id);
$can_create_guides = has_permission($pdo, 'guide.create', $current_user_id);

// Düzenleme modu için
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $guide_id_to_edit = (int)$_GET['edit_id'];
    $page_title = "Rehberi Düzenle";
    $form_action = "update_guide";
    $submit_button_text = "Değişiklikleri Kaydet";

    // Rehber bilgilerini çek
    try {
        if (!isset($pdo)) { throw new Exception("Veritabanı bağlantısı bulunamadı."); }
        $stmt = $pdo->prepare("SELECT * FROM guides WHERE id = ?");
        $stmt->execute([$guide_id_to_edit]);
        $existing_guide = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_guide) {
            // Yetki kontrolü: Sahibi veya 'guide.edit_all' yetkisine sahip admin düzenleyebilir
            if (!($current_user_id == $existing_guide['user_id'] && has_permission($pdo, 'guide.edit_own', $current_user_id)) && !$can_edit_all_guides) {
                $_SESSION['error_message'] = "Bu rehberi düzenleme yetkiniz yok.";
                header('Location: ' . get_auth_base_url() . '/guides.php');
                exit;
            }
            // Form verilerini doldur
            $guide_data['title'] = $_SESSION['form_input']['guide_title'] ?? $existing_guide['title'];
            $guide_data['content_md'] = $_SESSION['form_input']['guide_content_md'] ?? $existing_guide['content_md'];
            $guide_data['status'] = $_SESSION['form_input']['guide_status'] ?? $existing_guide['status'];
            $guide_data['is_public_no_auth'] = isset($_SESSION['form_input']['is_public_no_auth']) ? (bool)$_SESSION['form_input']['is_public_no_auth'] : (bool)$existing_guide['is_public_no_auth'];
            $guide_data['is_members_only'] = isset($_SESSION['form_input']['is_members_only']) ? (bool)$_SESSION['form_input']['is_members_only'] : (bool)$existing_guide['is_members_only'];
            $guide_data['thumbnail_path'] = $existing_guide['thumbnail_path'];
            $guide_data['slug'] = $existing_guide['slug'];

            $stmt_assigned_roles = $pdo->prepare("SELECT role_id FROM guide_visibility_roles WHERE guide_id = ?");
            $stmt_assigned_roles->execute([$guide_id_to_edit]);
            $guide_data['assigned_role_ids'] = $_SESSION['form_input']['assigned_role_ids'] ?? $stmt_assigned_roles->fetchAll(PDO::FETCH_COLUMN);

        } else {
            $_SESSION['error_message'] = "Düzenlenecek rehber bulunamadı.";
            header('Location: ' . get_auth_base_url() . '/admin/guides.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Rehber bilgileri yüklenirken bir sorun oluştu: " . htmlspecialchars($e->getMessage());
        header('Location: ' . get_auth_base_url() . '/admin/guides.php');
        exit;
    }
} else { // Yeni rehber oluşturma modu
    if (!$can_create_guides) {
        $_SESSION['error_message'] = "Yeni rehber oluşturma yetkiniz bulunmamaktadır.";
        header('Location: ' . get_auth_base_url() . '/guides.php');
        exit;
    }
    // Yeni rehber için varsayılanlar
    $guide_data = [
        'title' => $_SESSION['form_input']['guide_title'] ?? '',
        'content_md' => $_SESSION['form_input']['guide_content_md'] ?? '',
        'status' => $_SESSION['form_input']['guide_status'] ?? 'draft',
        'is_public_no_auth' => isset($_SESSION['form_input']['is_public_no_auth']) ? (bool)$_SESSION['form_input']['is_public_no_auth'] : false,
        'is_members_only' => isset($_SESSION['form_input']['is_members_only']) ? (bool)$_SESSION['form_input']['is_members_only'] : true,
        'assigned_role_ids' => $_SESSION['form_input']['assigned_role_ids'] ?? [],
        'thumbnail_path' => null,
        'slug' => ''
    ];
}
unset($_SESSION['form_input']);

// Atanabilir rolleri çek (admin ve member hariç)
$assignable_roles = [];
if (isset($pdo)) {
    try {
        $stmt_roles = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member') ORDER BY name ASC");
        $assignable_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Roller çekilirken hata (new_guide.php): " . $e->getMessage());
    }
}


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
:root {
    /* style.css'den gelen renkler kullanılacak */
}

.guide-form-page-container {
    width: 100%;
    max-width: 1000px; /* Form için daha geniş alan */
    margin: 40px auto;
    padding: 30px 35px; /* İç boşluklar */
    font-family: var(--font);
    color: var(--lighter-grey);
    background-color: var(--charcoal);
    border-radius: 12px; /* Daha yuvarlak köşeler */
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
}

.guide-form-page-container .form-page-title {
    color: var(--gold);
    font-size: 2.3rem; /* Başlık biraz daha büyük */
    text-align: center;
    margin: 0 0 35px 0; /* Alt boşluk arttı */
    font-family: var(--font);
    font-weight: 600;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-2);
}

.guide-form .form-section {
    margin-bottom: 30px;
    padding: 25px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}
.guide-form .form-section legend,
.guide-form .form-section .section-legend { /* Fieldset legend veya div başlığı için */
    font-size: 1.3rem; /* Bölüm başlığı */
    color: var(--light-gold);
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--darker-gold-1);
    display: flex;
    align-items: center;
    gap: 10px;
}
.guide-form .form-section legend i.fas,
.guide-form .form-section .section-legend i.fas {
    font-size: 1.1em;
    color: var(--gold);
}


.guide-form .form-group {
    margin-bottom: 22px;
}
.guide-form .form-group label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-size: 0.95rem; /* Label fontu biraz daha küçük */
    font-weight: 500;
}

.guide-form .form-control,
.guide-form .form-control-file,
.guide-form select.form-control {
    width: 100%;
    padding: 12px 16px; /* Input iç boşluğu */
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px; /* Daha yumuşak köşeler */
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
    line-height: 1.5;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.guide-form .form-control:focus,
.guide-form .form-control-file:focus,
.guide-form select.form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.guide-form textarea.form-control {
    min-height: 400px; /* Markdown editörü için daha fazla alan */
    resize: vertical;
    line-height: 1.6;
    font-family: monospace; /* Markdown için monospace font daha iyi olabilir */
    font-size: 0.95rem;
}

.guide-form select.form-control {
    appearance: none; /* Tarayıcı varsayılan okunu kaldır */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23bd912a' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E"); /* Özel ok ikonu (gold renginde) */
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
    padding-right: 2.5rem; /* Ok için yer bırak */
}

.guide-form .form-text {
    display: block;
    font-size: 0.85rem; /* Yardımcı metinler */
    color: var(--light-grey);
    margin-top: 8px;
    line-height: 1.4;
}
.guide-form .form-text a {
    color: var(--turquase);
    text-decoration: none;
}
.guide-form .form-text a:hover {
    text-decoration: underline;
}

/* Thumbnail Yükleme Alanı */
.current-thumbnail-info {
    margin-bottom: 15px;
    padding: 15px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    border: 1px solid var(--darker-gold-1);
}
.current-thumbnail-info p {
    font-size: 0.9rem;
    color: var(--light-gold);
    margin: 0 0 10px 0;
    font-weight: 500;
}
.current-thumbnail-image {
    max-width: 200px;
    max-height: 150px;
    border-radius: 4px;
    border: 1px solid var(--grey);
    display: block;
    margin-bottom: 10px;
}
.delete-thumbnail-label {
    display: inline-flex;
    align-items: center;
    font-size: 0.85rem;
    color: var(--lighter-grey);
    cursor: pointer;
    font-weight: normal;
}
.delete-thumbnail-label input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
    accent-color: var(--red); /* Silme için kırmızı vurgu */
    transform: scale(1.1);
}

.guide-form input[type="file"].form-control-file::file-selector-button {
    padding: 10px 18px; /* Buton padding'i */
    margin-right: 12px;
    background-color: var(--light-gold);
    color: var(--darker-gold-2);
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-family: var(--font);
    font-weight: 500;
    transition: background-color 0.2s ease;
}
.guide-form input[type="file"].form-control-file::file-selector-button:hover {
    background-color: var(--gold);
}


/* Görünürlük Seçenekleri */
.visibility-options-group { /* Fieldset için genel stil */
    /* .form-section'dan stil alıyor */
}
.visibility-main-options, .visibility-roles-group {
    margin-top: 15px;
}
.visibility-roles-group legend { /* "Belirli Rollere Açık" başlığı */
    font-size: 1.1rem;
    color: var(--light-gold);
    margin-bottom: 12px;
    padding-bottom: 0;
    border-bottom: none;
}

.visibility-checkbox-item {
    display: flex; /* Label ve açıklamayı daha iyi hizalamak için */
    flex-direction: column; /* Açıklama alta gelsin */
    margin-bottom: 15px; /* Checkbox'lar arası boşluk */
}
.visibility-checkbox-item label.main-label { /* Asıl checkbox ve metnini içeren label */
    font-size: 0.95rem;
    color: var(--lighter-grey);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    font-weight: normal; /* Label'lar normal fontta */
    margin-bottom: 4px; /* Açıklama ile arasına boşluk */
}
.visibility-checkbox-item input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
    accent-color: var(--gold); /* Checkbox işaret rengi */
    transform: scale(1.2); /* Checkbox'ı biraz büyüt */
    vertical-align: middle;
}
.visibility-checkbox-item .role-desc-small {
    font-size: 0.82em;
    color: var(--light-grey);
    margin-left: 32px; /* Checkbox ve label metninden sonra başlasın */
    display: block;
    opacity: 0.85;
    line-height: 1.3;
}

/* Form Aksiyon Butonları */
.form-actions-guide {
    margin-top: 35px;
    padding-top: 25px;
    border-top: 1px solid var(--darker-gold-2);
    display: flex;
    justify-content: flex-end; /* Butonları sağa yasla */
    gap: 15px;
}
.form-actions-guide .btn {
    padding: 12px 28px; /* Butonlara daha fazla padding */
    font-size: 1rem;
    font-weight: 600; /* Buton yazısı daha belirgin */
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.form-actions-guide .btn-primary { /* Kaydet/Oluştur butonu */
    background-color: var(--gold);
    color: var(--darker-gold-2);
    border-color: var(--gold);
}
.form-actions-guide .btn-primary:hover {
    background-color: var(--light-gold);
    border-color: var(--light-gold);
    color: var(--black);
}
.form-actions-guide .btn-secondary { /* İptal butonu */
    background-color: var(--grey);
    color: var(--lighter-grey);
    border-color: var(--grey);
}
.form-actions-guide .btn-secondary:hover {
    background-color: var(--darker-gold-1);
    color: var(--white);
}

/* Responsive Ayarlamalar */
@media (max-width: 768px) {
    .guide-form-page-container {
        padding: 20px;
        margin: 20px auto;
    }
    .guide-form-page-container .form-page-title {
        font-size: 1.9rem;
        margin-bottom: 25px;
    }
    .guide-form .form-section {
        padding: 20px;
    }
    .guide-form .form-section legend,
    .guide-form .form-section .section-legend {
        font-size: 1.15rem;
    }
    .guide-form textarea.form-control {
        min-height: 300px; /* Mobilde textarea yüksekliği */
    }
    .form-actions-guide {
        flex-direction: column-reverse; /* Mobilde butonlar alt alta, kaydet üstte */
        gap: 12px;
    }
    .form-actions-guide .btn {
        width: 100%; /* Butonlar tam genişlik */
    }
}

</style>

<main class="main-content">
    <div class="container guide-form-page-container">
        <h2 class="form-page-title"><?php echo htmlspecialchars($page_title); ?></h2>

        <?php // Hata/Başarı mesajları header.php'de gösteriliyor ?>

        <form action="../src/actions/handle_guide.php" method="POST" enctype="multipart/form-data" id="guideForm" class="guide-form">
            <input type="hidden" name="action" value="<?php echo $form_action; ?>">
            <?php if ($guide_id_to_edit): ?>
                <input type="hidden" name="guide_id" value="<?php echo $guide_id_to_edit; ?>">
            <?php endif; ?>

            <div class="form-section">
                <h3 class="section-legend"><i class="fas fa-heading"></i>Temel Bilgiler</h3>
                <div class="form-group">
                    <label for="guide_title">Rehber Başlığı:</label>
                    <input type="text" id="guide_title" name="guide_title" class="form-control" value="<?php echo htmlspecialchars($guide_data['title']); ?>" required maxlength="250" placeholder="Dikkat çekici bir başlık girin...">
                </div>

                <div class="form-group">
                    <label for="guide_thumbnail">Rehber Küçük Resmi (Thumbnail):</label>
                    <?php if ($guide_id_to_edit && !empty($guide_data['thumbnail_path'])): ?>
                        <div class="current-thumbnail-info">
                            <p>Mevcut Thumbnail:</p>
                            <img src="/public/<?php echo htmlspecialchars($guide_data['thumbnail_path']); ?>" alt="Mevcut Thumbnail" class="current-thumbnail-image">
                            <label for="delete_thumbnail" class="delete-thumbnail-label">
                                <input type="checkbox" name="delete_thumbnail" id="delete_thumbnail" value="1">
                                Mevcut thumbnail'ı sil
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="guide_thumbnail" name="guide_thumbnail" class="form-control-file" accept="image/jpeg, image/png, image/gif">
                    <small class="form-text">İzin verilen formatlar: JPG, PNG, GIF. Maksimum boyut: 15MB.</small>
                </div>
            </div>

            <div class="form-section">
                 <h3 class="section-legend"><i class="fab fa-markdown"></i>Rehber İçeriği</h3>
                <div class="form-group">
                    <label for="guide_content_md">İçerik (Markdown Editörü):</label>
                    <textarea id="guide_content_md" name="guide_content_md" class="form-control" rows="25" required placeholder="Rehberinizi Markdown sözdizimini kullanarak yazın..."><?php echo htmlspecialchars($guide_data['content_md']); ?></textarea>
                    <small class="form-text">Yardım için: <a href="https://www.markdownguide.org/basic-syntax/" target="_blank" rel="noopener noreferrer">Markdown Temel Sözdizimi</a></small>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-legend"><i class="fas fa-cogs"></i>Yayın Ayarları</h3>
                <div class="form-group">
                    <label for="guide_status">Yayın Durumu:</label>
                    <select name="guide_status" id="guide_status" class="form-control">
                        <option value="published" <?php echo ($guide_data['status'] === 'published' ? 'selected' : ''); ?>>Hemen Yayınla</option>
                        <option value="draft" <?php echo ($guide_data['status'] === 'draft' ? 'selected' : ''); ?>>Taslak Olarak Kaydet</option>
                        <?php if ($current_user_is_admin): // Sadece adminler arşivleyebilsin ?>
                            <option value="archived" <?php echo ($guide_data['status'] === 'archived' ? 'selected' : ''); ?>>Arşivle (Listede Görünmesin)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <fieldset class="form-group visibility-options-group" style="background:transparent; border:none; padding:0; margin-top:0;">
                    <legend style="font-size: 1.1rem; color: var(--light-gold); font-weight: 600; margin-bottom: 15px; padding-bottom:10px; border-bottom:1px solid var(--darker-gold-1);"><i class="fas fa-eye" style="margin-right:8px; color:var(--gold);"></i>Görünürlük</legend>
                    <div class="visibility-main-options">
                        <div class="visibility-checkbox-item">
                            <label for="is_public_no_auth" class="main-label">
                                <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth" value="1"
                                       <?php echo !empty($guide_data['is_public_no_auth']) ? 'checked' : ''; ?>>
                                Herkese Açık (Giriş Yapmamış Kullanıcılar Dahil)
                            </label>
                            <small class="role-desc-small">Bu seçenek işaretliyse, aşağıdaki diğer görünürlük ayarları yok sayılır.</small>
                        </div>
                        <div class="visibility-checkbox-item">
                            <label for="is_members_only" class="main-label">
                                <input type="checkbox" name="is_members_only" id="is_members_only" value="1"
                                       <?php echo !empty($guide_data['is_members_only']) ? 'checked' : ''; ?>>
                                Tüm Onaylı Üyelere Açık
                            </label>
                            <small class="role-desc-small">"Herkese Açık" seçili değilse ve belirli roller seçilmemişse bu geçerli olur.</small>
                        </div>
                    </div>

                    <div class="visibility-roles-group" style="margin-top:25px;">
                        <legend><i class="fas fa-users-cog" style="margin-right:8px; color:var(--gold);"></i>Veya Sadece Belirli Rollere Açık:</legend>
                        <?php if (!empty($assignable_roles)): ?>
                            <?php foreach ($assignable_roles as $role):
                                $role_display_name = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                ?>
                                <div class="visibility-checkbox-item">
                                    <label for="role_<?php echo $role['id']; ?>" class="main-label">
                                        <input type="checkbox" name="assigned_role_ids[]" id="role_<?php echo $role['id']; ?>"
                                               value="<?php echo $role['id']; ?>"
                                               <?php echo in_array($role['id'], $guide_data['assigned_role_ids']) ? 'checked' : ''; ?>>
                                        <?php echo $role_display_name; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="form-text">Sistemde atanabilir özel rol bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                     <small class="form-text" style="margin-top:20px; padding:10px; background-color:var(--grey); border-radius:4px; border-left:3px solid var(--turquase);">
                        <strong>Not:</strong> Bir rehber ya "Herkese Açık" ya da "Tüm Üyelere Açık" ya da "Belirli Rollere Özel" olabilir. "Herkese Açık" en geniş kapsamdır.
                        Eğer "Tüm Üyelere Açık" seçiliyse ve aynı zamanda belirli roller de seçilirse, rehber sadece o belirli rollere değil, TÜM onaylı üyelere açık olacaktır.
                        Sadece belirli rollere özel yapmak için "Tüm Üyelere Açık" seçeneğinin işaretini kaldırın. Adminler her zaman tüm rehberleri görebilir.
                    </small>
                </fieldset>
            </div>

            <div class="form-actions-guide">
                <a href="<?php echo $guide_id_to_edit && !empty($guide_data['slug']) ? (get_auth_base_url() . '/guide_detail.php?slug=' . htmlspecialchars($guide_data['slug'])) : (get_auth_base_url() . '/guides.php'); ?>" class="btn btn-secondary">İptal</a>
                <button type="submit" class="btn btn-primary"><?php echo $submit_button_text; ?></button>
            </div>
        </form>
    </div>
</main>

<script>
// ... (mevcut JavaScript kodunuz aynı kalabilir) ...
document.addEventListener('DOMContentLoaded', function() {
    const publicNoAuthCheckbox = document.getElementById('is_public_no_auth');
    const membersOnlyCheckbox = document.getElementById('is_members_only');
    const roleCheckboxes = document.querySelectorAll('input[name="assigned_role_ids[]"]');
    const visibilityRolesGroup = document.querySelector('.visibility-roles-group');

    function updateVisibilityOptions() {
        if (!publicNoAuthCheckbox || !membersOnlyCheckbox || !visibilityRolesGroup) return;

        const isPublic = publicNoAuthCheckbox.checked;
        membersOnlyCheckbox.disabled = isPublic;
        roleCheckboxes.forEach(cb => {
            cb.disabled = isPublic;
        });
        visibilityRolesGroup.style.opacity = isPublic ? '0.5' : '1';
        visibilityRolesGroup.style.pointerEvents = isPublic ? 'none' : 'auto';

        if (isPublic) {
            membersOnlyCheckbox.checked = false;
            roleCheckboxes.forEach(cb => {
                cb.checked = false;
            });
        }
    }

    if (publicNoAuthCheckbox) {
        publicNoAuthCheckbox.addEventListener('change', updateVisibilityOptions);
        updateVisibilityOptions(); // Sayfa yüklendiğinde de durumu ayarla
    }
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
