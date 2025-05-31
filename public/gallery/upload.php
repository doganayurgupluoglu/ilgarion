<?php
// public/gallery/upload.php (eski dosya adı public/upload_gallery_photo.php idi, bu daha mantıklı)
// Eğer eski dosya adını kullanıyorsanız, onu güncelleyin. Ben yeni isimlendirmeyi baz alıyorum.

require_once __DIR__ . '/../../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol ve yetki fonksiyonları

// Sayfaya erişim için 'gallery.upload' yetkisi ve onaylı kullanıcı kontrolü
require_approved_user(); // Önce onaylı kullanıcı mı diye bakar (içinde check_user_session_validity var)
require_permission($pdo, 'gallery.upload'); // Sonra spesifik yetkiyi kontrol eder

$page_title = "Galeriye Fotoğraf Yükle";
$font_family_style = "font-family: var(--font), serif;";

// Atanabilir rolleri çek (admin ve member hariç)
$assignable_roles_gallery = [];
if (isset($pdo)) {
    try {
        $stmt_roles_gallery = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member') ORDER BY name ASC");
        $assignable_roles_gallery = $stmt_roles_gallery->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Roller çekilirken hata (gallery/upload.php): " . $e->getMessage());
        // Hata durumunda $assignable_roles_gallery boş kalacak, formda uyarı gösterilebilir.
    }
}

// Formdan gelen eski girdileri almak için (hata durumunda)
$form_input_gallery = $_SESSION['form_input_gallery'] ?? [];
$description_value = htmlspecialchars($form_input_gallery['description'] ?? '');
$is_public_checked = isset($form_input_gallery['is_public_no_auth']) && $form_input_gallery['is_public_no_auth'] == '1';
$is_members_only_checked = isset($form_input_gallery['is_members_only']) && $form_input_gallery['is_members_only'] == '1';
if (empty($form_input_gallery) && !isset($form_input_gallery['is_public_no_auth']) && !isset($form_input_gallery['is_members_only'])) {
    // Eğer form daha önce hiç gönderilmediyse veya session'da veri yoksa, varsayılan olarak members_only seçili olsun
    $is_members_only_checked = true;
}
$assigned_role_ids_value = $form_input_gallery['assigned_role_ids'] ?? [];


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* public/gallery/upload.php için Stiller */
.upload-form-container-gallery {
    max-width: 700px; /* Form genişliği */
    margin: 40px auto;
    padding: 30px 35px;
    background-color: var(--charcoal);
    border-radius: 10px;
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

.upload-form-container-gallery h2 {
    color: var(--gold);
    font-size: 2rem;
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-2);
    <?php echo $font_family_style; ?>
}

.upload-form-container-gallery .form-group {
    margin-bottom: 22px;
}

.upload-form-container-gallery .form-group label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-size: 0.95rem;
    <?php echo $font_family_style; ?>
    font-weight: 500;
}

/* Görünürlük ayarları için new_guide.php'den alınan stiller */
.upload-form-container-gallery .form-section {
    margin-bottom: 25px;
    padding: 20px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}
.upload-form-container-gallery .form-section .section-legend {
    font-size: 1.2rem;
    color: var(--light-gold);
    font-weight: 600;
    margin-bottom: 18px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--darker-gold-1);
    display: flex;
    align-items: center;
    gap: 8px;
}
.upload-form-container-gallery .form-section .section-legend i.fas {
    font-size: 1em;
    color: var(--gold);
}
.upload-form-container-gallery .visibility-options-group {
    margin-top: 10px;
}
.upload-form-container-gallery .visibility-checkbox-item {
    display: flex;
    flex-direction: column;
    margin-bottom: 12px;
}
.upload-form-container-gallery .visibility-checkbox-item label.main-label {
    font-size: 0.9rem; /* Biraz daha küçük label */
    color: var(--lighter-grey);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    font-weight: normal;
    margin-bottom: 3px;
}
.upload-form-container-gallery .visibility-checkbox-item input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
    accent-color: var(--gold);
    transform: scale(1.1);
    vertical-align: middle;
}
.upload-form-container-gallery .visibility-checkbox-item .role-desc-small {
    font-size: 0.8em;
    color: var(--light-grey);
    margin-left: 28px; /* Checkbox ve label metninden sonra başlasın */
    display: block;
    opacity: 0.8;
    line-height: 1.2;
}
.upload-form-container-gallery .visibility-roles-group {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px dashed var(--darker-gold-1);
}
.upload-form-container-gallery .visibility-roles-group .section-legend { /* "Belirli Rollere Açık" başlığı */
    font-size: 1.1rem;
    margin-bottom: 10px;
    padding-bottom: 0;
    border-bottom: none;
}
/* Stil sonu */

.upload-form-container-gallery .form-control,
.upload-form-container-gallery input[type="file"].form-control-file {
    width: 100%;
    padding: 10px 14px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--white);
    font-size: 1rem;
    <?php echo $font_family_style; ?>
    line-height: 1.5;
    box-sizing: border-box;
}
.upload-form-container-gallery input[type="file"].form-control-file {
    padding: 8px 0px; /* file input için özel padding */
}

.upload-form-container-gallery .form-control:focus,
.upload-form-container-gallery input[type="file"].form-control-file:focus-within { /* focus-within daha iyi olabilir */
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.upload-form-container-gallery textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.upload-form-container-gallery input[type="file"].form-control-file::file-selector-button {
    padding: 8px 15px;
    margin-left: 10px; /* Sol boşluk */
    margin-right: 10px; /* Sağ boşluk */
    background-color: var(--light-gold);
    color: var(--darker-gold-2);
    border: 1px solid var(--gold);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font), serif;
    font-size: 0.9rem;
    font-weight: 500;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
.upload-form-container-gallery input[type="file"].form-control-file::file-selector-button:hover {
    background-color: var(--gold);
    color: var(--black);
    border-color: var(--light-gold);
}

.upload-form-container-gallery .form-text-helper {
    display: block;
    font-size: 0.8rem;
    color: var(--light-grey);
    margin-top: 6px;
    line-height: 1.3;
}
.upload-form-container-gallery .form-group-submit {
    margin-top: 25px;
    margin-bottom: 0;
    display: flex; /* Butonları yan yana getirmek için */
    justify-content: flex-end; /* Butonları sağa yasla */
    gap: 15px; /* Butonlar arası boşluk */
}
.upload-form-container-gallery .btn-submit-upload,
.upload-form-container-gallery .btn-cancel-upload {
    padding: 10px 25px;
    border-radius: 5px;
    font-size: 1rem;
    <?php echo $font_family_style; ?>
    font-weight: bold;
    cursor: pointer;
    text-transform: uppercase;
    transition: background-color 0.3s ease, transform 0.2s ease;
    text-decoration: none; /* Linkler için */
    display: inline-block; /* Linkler için */
    text-align: center;
}
.upload-form-container-gallery .btn-submit-upload {
    background-color: var(--gold);
    color: var(--darker-gold-2);
    border: none;
}
.upload-form-container-gallery .btn-submit-upload:hover {
    background-color: var(--light-gold);
    transform: translateY(-2px);
}
.upload-form-container-gallery .btn-cancel-upload {
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
}
.upload-form-container-gallery .btn-cancel-upload:hover {
    background-color: var(--darker-gold-1);
    color: var(--white);
}
</style>

<main class="main-content auth-page">
    <div class="upload-form-container-gallery">
        <h2><?php echo htmlspecialchars($page_title); ?></h2>

        <?php // Hata/Başarı mesajları header.php'de gösteriliyor ?>

        <form action="/src/actions/handle_gallery_upload.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="gallery_image">Fotoğraf Seçin:</label>
                <input type="file" id="gallery_image" name="gallery_image" class="form-control-file" accept="image/jpeg, image/png, image/gif" required>
                <small class="form-text-helper">İzin verilen formatlar: JPG, PNG, GIF. Maksimum boyut: 20MB.</small>
            </div>
            <div class="form-group">
                <label for="description">Fotoğraf Açıklaması (SEO için zorunlu):</label>
                <textarea id="description" name="description" class="form-control" rows="4" maxlength="500" required placeholder="Fotoğrafınız için kısa ve açıklayıcı bir metin girin..."><?php echo $description_value; ?></textarea>
            </div>

            <!-- Görünürlük Ayarları Bölümü -->
            <div class="form-section">
                <h3 class="section-legend"><i class="fas fa-eye"></i>Görünürlük Ayarları</h3>
                <fieldset class="form-group visibility-options-group">
                    <div class="visibility-main-options">
                        <div class="visibility-checkbox-item">
                            <label for="is_public_no_auth_gallery" class="main-label">
                                <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth_gallery" value="1"
                                       <?php echo $is_public_checked ? 'checked' : ''; ?>>
                                Herkese Açık (Giriş Yapmamış Kullanıcılar Dahil)
                            </label>
                            <small class="role-desc-small">Bu seçenek işaretliyse, aşağıdaki diğer görünürlük ayarları yok sayılır.</small>
                        </div>
                        <div class="visibility-checkbox-item">
                            <label for="is_members_only_gallery" class="main-label">
                                <input type="checkbox" name="is_members_only" id="is_members_only_gallery" value="1"
                                       <?php echo $is_members_only_checked ? 'checked' : ''; ?>>
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
                                    <label for="gallery_role_<?php echo $role['id']; ?>" class="main-label">
                                        <input type="checkbox" name="assigned_role_ids[]" id="gallery_role_<?php echo $role['id']; ?>"
                                               value="<?php echo $role['id']; ?>"
                                               <?php echo in_array($role['id'], $assigned_role_ids_value) ? 'checked' : ''; ?>>
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
                <a href="<?php echo get_auth_base_url(); ?>/gallery.php" class="btn-cancel-upload">İptal</a>
                <button type="submit" class="btn-submit-upload">Fotoğrafı Yükle</button>
            </div>
        </form>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const publicNoAuthCheckboxGallery = document.getElementById('is_public_no_auth_gallery');
    const membersOnlyCheckboxGallery = document.getElementById('is_members_only_gallery');
    const roleCheckboxesGallery = document.querySelectorAll('input[name="assigned_role_ids[]"]');
    const visibilityRolesGroupGallery = document.querySelector('.visibility-roles-group'); // Bu class'ı role listesini içeren div'e ekleyin

    function updateGalleryVisibilityOptions() {
        if (!publicNoAuthCheckboxGallery || !membersOnlyCheckboxGallery || !visibilityRolesGroupGallery) return;

        const isPublicGallery = publicNoAuthCheckboxGallery.checked;
        membersOnlyCheckboxGallery.disabled = isPublicGallery;
        roleCheckboxesGallery.forEach(cb => {
            cb.disabled = isPublicGallery;
        });
        
        // Opaklık ve tıklanabilirlik ayarı
        const roleCheckboxesContainer = visibilityRolesGroupGallery; // En dıştaki div
        if (roleCheckboxesContainer) {
            roleCheckboxesContainer.style.opacity = isPublicGallery ? '0.5' : '1';
            // pointerEvents inputların kendisinde yönetildiği için genel container'a gerek yok gibi,
            // ama isterseniz ekleyebilirsiniz:
            // roleCheckboxesContainer.style.pointerEvents = isPublicGallery ? 'none' : 'auto';
        }


        if (isPublicGallery) {
            membersOnlyCheckboxGallery.checked = false;
            roleCheckboxesGallery.forEach(cb => {
                cb.checked = false;
            });
        }
    }

    if (publicNoAuthCheckboxGallery) {
        publicNoAuthCheckboxGallery.addEventListener('change', updateGalleryVisibilityOptions);
        // Sayfa yüklendiğinde de durumu ayarla
        updateGalleryVisibilityOptions();
    }
});
</script>
<?php
// Form girdilerini session'dan temizle (eğer varsa)
if (isset($_SESSION['form_input_gallery'])) { // Session anahtarını değiştirdik
    unset($_SESSION['form_input_gallery']);
}
require_once BASE_PATH . '/src/includes/footer.php';
?>
