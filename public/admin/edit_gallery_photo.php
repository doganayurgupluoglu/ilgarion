<?php
// public/admin/edit_gallery_photo.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

require_admin(); // Sadece adminler erişebilir

$page_title = "Galeri Fotoğrafını Düzenle";
$photo_id_to_edit = null;
$photo_data = [];
$assignable_roles_gallery = [];

if (!isset($_GET['photo_id']) || !is_numeric($_GET['photo_id'])) {
    $_SESSION['error_message'] = "Geçersiz fotoğraf ID'si.";
    header('Location: ' . get_auth_base_url() . '/admin/gallery.php');
    exit;
}
$photo_id_to_edit = (int)$_GET['photo_id'];

// Atanabilir rolleri çek
try {
    $stmt_roles_gallery = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member') ORDER BY name ASC");
    $assignable_roles_gallery = $stmt_roles_gallery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Roller çekilirken hata (edit_gallery_photo.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Roller yüklenirken bir sorun oluştu.";
    // Rolleri çekemezsek formu göstermenin anlamı yok, admin galeriye yönlendir.
    header('Location: ' . get_auth_base_url() . '/admin/gallery.php');
    exit;
}

// Mevcut fotoğraf bilgilerini çek
try {
    $stmt_photo = $pdo->prepare("SELECT * FROM gallery_photos WHERE id = ?");
    $stmt_photo->execute([$photo_id_to_edit]);
    $existing_photo = $stmt_photo->fetch(PDO::FETCH_ASSOC);

    if ($existing_photo) {
        $photo_data['description'] = $_SESSION['form_input_gallery_edit']['description'] ?? $existing_photo['description'];
        $photo_data['is_public_no_auth'] = isset($_SESSION['form_input_gallery_edit']['is_public_no_auth']) ? (bool)$_SESSION['form_input_gallery_edit']['is_public_no_auth'] : (bool)$existing_photo['is_public_no_auth'];
        $photo_data['is_members_only'] = isset($_SESSION['form_input_gallery_edit']['is_members_only']) ? (bool)$_SESSION['form_input_gallery_edit']['is_members_only'] : (bool)$existing_photo['is_members_only'];
        $photo_data['image_path'] = $existing_photo['image_path']; // Sadece göstermek için

        $stmt_assigned_roles = $pdo->prepare("SELECT role_id FROM gallery_photo_visibility_roles WHERE photo_id = ?");
        $stmt_assigned_roles->execute([$photo_id_to_edit]);
        $photo_data['assigned_role_ids'] = $_SESSION['form_input_gallery_edit']['assigned_role_ids'] ?? $stmt_assigned_roles->fetchAll(PDO::FETCH_COLUMN);

    } else {
        $_SESSION['error_message'] = "Düzenlenecek fotoğraf bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/admin/gallery.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Fotoğraf bilgileri çekilirken hata (edit_gallery_photo.php): " . $e->getMessage());
    $_SESSION['error_message'] = "Fotoğraf bilgileri yüklenirken bir sorun oluştu.";
    header('Location: ' . get_auth_base_url() . '/admin/gallery.php');
    exit;
}
unset($_SESSION['form_input_gallery_edit']);


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* public/gallery/upload.php'den alınan stiller buraya da uygulanabilir veya ortak bir CSS dosyasına taşınabilir */
.edit-photo-form-container {
    max-width: 750px;
    margin: 40px auto;
    padding: 30px 35px;
    background-color: var(--charcoal);
    border-radius: 10px;
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    font-family: var(--font), serif;
}
.edit-photo-form-container h2 {
    color: var(--gold);
    font-size: 2rem;
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.edit-photo-form-container .form-group { margin-bottom: 22px; }
.edit-photo-form-container .form-group label {
    display: block; color: var(--lighter-grey); margin-bottom: 8px;
    font-size: 0.95rem; font-weight: 500;
}
.edit-photo-form-container .form-control {
    width: 100%; padding: 10px 14px; background-color: var(--grey);
    border: 1px solid var(--darker-gold-1); border-radius: 6px;
    color: var(--white); font-size: 1rem; line-height: 1.5; box-sizing: border-box;
}
.edit-photo-form-container .form-control:focus {
    outline: none; border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}
.edit-photo-form-container textarea.form-control { min-height: 100px; resize: vertical; }
.edit-photo-form-container .form-text-helper {
    display: block; font-size: 0.8rem; color: var(--light-grey);
    margin-top: 6px; line-height: 1.3;
}
.edit-photo-form-container .current-image-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 6px;
    border: 1px solid var(--darker-gold-1);
    margin-bottom: 15px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}
/* Görünürlük ayarları için stiller (upload.php'deki gibi) */
.edit-photo-form-container .form-section {
    margin-bottom: 25px; padding: 20px; background-color: var(--darker-gold-2);
    border-radius: 8px; border: 1px solid var(--darker-gold-1);
}
.edit-photo-form-container .form-section .section-legend {
    font-size: 1.2rem; color: var(--light-gold); font-weight: 600;
    margin-bottom: 18px; padding-bottom: 8px; border-bottom: 1px solid var(--darker-gold-1);
    display: flex; align-items: center; gap: 8px;
}
.edit-photo-form-container .form-section .section-legend i.fas { font-size: 1em; color: var(--gold); }
.edit-photo-form-container .visibility-options-group { margin-top: 10px; }
.edit-photo-form-container .visibility-checkbox-item { display: flex; flex-direction: column; margin-bottom: 12px; }
.edit-photo-form-container .visibility-checkbox-item label.main-label {
    font-size: 0.9rem; color: var(--lighter-grey); cursor: pointer;
    display: inline-flex; align-items: center; font-weight: normal; margin-bottom: 3px;
}
.edit-photo-form-container .visibility-checkbox-item input[type="checkbox"] {
    width: auto; margin-right: 8px; accent-color: var(--gold);
    transform: scale(1.1); vertical-align: middle;
}
.edit-photo-form-container .visibility-checkbox-item .role-desc-small {
    font-size: 0.8em; color: var(--light-grey); margin-left: 28px;
    display: block; opacity: 0.8; line-height: 1.2;
}
.edit-photo-form-container .visibility-roles-group {
    margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--darker-gold-1);
}
.edit-photo-form-container .visibility-roles-group .section-legend {
    font-size: 1.1rem; margin-bottom: 10px; padding-bottom: 0; border-bottom: none;
}
.edit-photo-form-container .form-group-submit {
    margin-top: 25px; margin-bottom: 0; display: flex;
    justify-content: flex-end; gap: 15px;
}
.edit-photo-form-container .btn-submit-edit,
.edit-photo-form-container .btn-cancel-edit {
    padding: 10px 25px; border-radius: 5px; font-size: 1rem;
    font-weight: bold; cursor: pointer; text-transform: uppercase;
    transition: background-color 0.3s ease, transform 0.2s ease;
    text-decoration: none; display: inline-block; text-align: center;
}
.edit-photo-form-container .btn-submit-edit {
    background-color: var(--gold); color: var(--darker-gold-2); border: none;
}
.edit-photo-form-container .btn-submit-edit:hover { background-color: var(--light-gold); transform: translateY(-2px); }
.edit-photo-form-container .btn-cancel-edit {
    background-color: var(--grey); color: var(--lighter-grey); border: 1px solid var(--darker-gold-1);
}
.edit-photo-form-container .btn-cancel-edit:hover { background-color: var(--darker-gold-1); color: var(--white); }
</style>

<main class="main-content">
    <div class="container edit-photo-form-container">
        <h2><?php echo htmlspecialchars($page_title); ?> (ID: <?php echo $photo_id_to_edit; ?>)</h2>

        <?php // Hata/Başarı mesajları header.php'de gösteriliyor ?>

        <div class="current-image-preview">
            <img src="/public/<?php echo htmlspecialchars($photo_data['image_path']); ?>" alt="Mevcut Fotoğraf">
        </div>

        <form action="/src/actions/handle_admin_gallery_actions.php" method="POST">
            <input type="hidden" name="action" value="update_photo_details">
            <input type="hidden" name="photo_id" value="<?php echo $photo_id_to_edit; ?>">

            <div class="form-group">
                <label for="description">Fotoğraf Açıklaması:</label>
                <textarea id="description" name="description" class="form-control" rows="4" maxlength="500" required placeholder="Fotoğraf için kısa ve açıklayıcı bir metin..."><?php echo htmlspecialchars($photo_data['description']); ?></textarea>
            </div>

            <!-- Görünürlük Ayarları Bölümü -->
            <div class="form-section">
                <h3 class="section-legend"><i class="fas fa-eye"></i>Görünürlük Ayarları</h3>
                <fieldset class="form-group visibility-options-group">
                    <div class="visibility-main-options">
                        <div class="visibility-checkbox-item">
                            <label for="is_public_no_auth_gallery_edit" class="main-label">
                                <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth_gallery_edit" value="1"
                                       <?php echo !empty($photo_data['is_public_no_auth']) ? 'checked' : ''; ?>>
                                Herkese Açık (Giriş Yapmamış Kullanıcılar Dahil)
                            </label>
                            <small class="role-desc-small">Bu seçenek işaretliyse, aşağıdaki diğer görünürlük ayarları yok sayılır.</small>
                        </div>
                        <div class="visibility-checkbox-item">
                            <label for="is_members_only_gallery_edit" class="main-label">
                                <input type="checkbox" name="is_members_only" id="is_members_only_gallery_edit" value="1"
                                       <?php echo !empty($photo_data['is_members_only']) ? 'checked' : ''; ?>>
                                Tüm Onaylı Üyelere Açık
                            </label>
                            <small class="role-desc-small">"Herkese Açık" seçili değilse ve belirli roller seçilmemişse bu geçerli olur.</small>
                        </div>
                    </div>

                    <div class="visibility-roles-group">
                        <h4 class="section-legend" style="font-size: 1.1rem; margin-bottom:10px;"><i class="fas fa-users-cog"></i>Veya Sadece Belirli Rollere Açık:</h4>
                        <?php if (!empty($assignable_roles_gallery)): ?>
                            <?php foreach ($assignable_roles_gallery as $role):
                                $role_display_name_gallery = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                ?>
                                <div class="visibility-checkbox-item">
                                    <label for="gallery_edit_role_<?php echo $role['id']; ?>" class="main-label">
                                        <input type="checkbox" name="assigned_role_ids[]" id="gallery_edit_role_<?php echo $role['id']; ?>"
                                               value="<?php echo $role['id']; ?>"
                                               <?php echo in_array($role['id'], $photo_data['assigned_role_ids']) ? 'checked' : ''; ?>>
                                        <?php echo $role_display_name_gallery; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="form-text-helper">Sistemde atanabilir özel rol bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                     <small class="form-text-helper" style="margin-top:15px; padding:8px; background-color:var(--grey); border-radius:4px; border-left:3px solid var(--turquase);">
                        <strong>Not:</strong> Bir fotoğraf ya "Herkese Açık" ya da "Tüm Üyelere Açık" ya da "Belirli Rollere Özel" olabilir.
                        "Herkese Açık" seçiliyse diğerleri geçersizdir. Sadece belirli rollere özel yapmak için diğer iki seçeneğin işaretini kaldırın.
                    </small>
                </fieldset>
            </div>
            <!-- Görünürlük Ayarları Bölümü Sonu -->

            <div class="form-group form-group-submit">
                <a href="<?php echo get_auth_base_url(); ?>/admin/gallery.php" class="btn-cancel-edit">İptal</a>
                <button type="submit" class="btn-submit-edit">Değişiklikleri Kaydet</button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const publicNoAuthCheckboxGalleryEdit = document.getElementById('is_public_no_auth_gallery_edit');
    const membersOnlyCheckboxGalleryEdit = document.getElementById('is_members_only_gallery_edit');
    const roleCheckboxesGalleryEdit = document.querySelectorAll('input[name="assigned_role_ids[]"]');
    const visibilityRolesGroupGalleryEdit = document.querySelector('.visibility-roles-group');

    function updateGalleryEditVisibilityOptions() {
        if (!publicNoAuthCheckboxGalleryEdit || !membersOnlyCheckboxGalleryEdit || !visibilityRolesGroupGalleryEdit) return;

        const isPublicGalleryEdit = publicNoAuthCheckboxGalleryEdit.checked;
        membersOnlyCheckboxGalleryEdit.disabled = isPublicGalleryEdit;
        roleCheckboxesGalleryEdit.forEach(cb => {
            cb.disabled = isPublicGalleryEdit;
        });
        
        const roleCheckboxesContainerEdit = visibilityRolesGroupGalleryEdit;
        if (roleCheckboxesContainerEdit) {
            roleCheckboxesContainerEdit.style.opacity = isPublicGalleryEdit ? '0.5' : '1';
        }

        if (isPublicGalleryEdit) {
            membersOnlyCheckboxGalleryEdit.checked = false;
            roleCheckboxesGalleryEdit.forEach(cb => {
                cb.checked = false;
            });
        }
    }

    if (publicNoAuthCheckboxGalleryEdit) {
        publicNoAuthCheckboxGalleryEdit.addEventListener('change', updateGalleryEditVisibilityOptions);
        updateGalleryEditVisibilityOptions(); // Sayfa yüklendiğinde de durumu ayarla
    }
});
</script>

<?php
if (isset($_SESSION['form_input_gallery_edit'])) {
    unset($_SESSION['form_input_gallery_edit']);
}
require_once BASE_PATH . '/src/includes/footer.php';
?>
