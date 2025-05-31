<?php
require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

require_approved_user();

$page_title = "Galeriye Fotoğraf Yükle";
$font_family_style = "font-family: var(--font), serif;";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
input[type="file"]::file-selector-button {
    padding: 8px 15px;
    margin-left: 12px;
    margin-right: 12px; 
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
input[type="file"]::file-selector-button:hover {
    background-color: var(--gold);
    color: var(--black); 
    border-color: var(--light-gold);
}
input[type="file"] {
    font-family: var(--font), serif;
    color: var(--lighter-grey);
}
</style>
<main class="main-content auth-page">
    <div class="auth-container upload-form-container" style="max-width: 600px; background-color: var(--charcoal); padding: 30px 40px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); width: 100%; margin-left: auto; margin-right: auto; border: 1px solid var(--darker-gold-1); box-sizing: border-box;">
        <h2 style="color: var(--gold); font-size: 2.2rem; text-align: center; margin-bottom: 30px; <?php echo $font_family_style; ?>"><?php echo htmlspecialchars($page_title); ?></h2>

        <form action="../src/actions/handle_gallery_upload.php" method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 20px;">
                <label for="gallery_image" style="display: block; color: var(--lighter-grey); margin-bottom: 8px; font-size: 0.9rem; <?php echo $font_family_style; ?> font-weight: 500;">Fotoğraf Seçin:</label>
                <input type="file" id="gallery_image" name="gallery_image" accept="image/jpeg, image/png, image/gif" required style="width: 100%; padding: 8px 0px; background-color: var(--charcoal); border: 1px solid var(--darker-gold-1); border-radius: 5px; color: var(--lighter-grey); font-size: 0.9rem; <?php echo $font_family_style; ?> line-height: 1.5; box-sizing: border-box;">
                <small style="display: block; font-size: 0.8rem; color: var(--light-grey); margin-top: 6px; line-height: 1.3; <?php echo $font_family_style; ?>">İzin verilen formatlar: JPG, PNG, GIF. Maksimum boyut: 20MB.</small>
            </div>
            <div style="margin-bottom: 20px;">
                <label for="description" style="display: block; color: var(--lighter-grey); margin-bottom: 8px; font-size: 0.9rem; <?php echo $font_family_style; ?> font-weight: 500;"><span style="color: var(--red)">SEO uyumluluğu açısından zorunludur. </span>Fotoğraf Açıklaması:</label>
                <textarea id="description" name="description" rows="4" maxlength="500" required style="width: 100%; padding: 10px 14px; background-color: var(--grey); border: 1px solid var(--darker-gold-1); border-radius: 5px; color: var(--white); font-size: 1rem; <?php echo $font_family_style; ?> line-height: 1.5; min-height: 80px; resize: vertical; box-sizing: border-box;"></textarea> 
            </div>
            <div class="form-group form-group-submit" style="margin-top: 25px; margin-bottom: 0; text-align: right;">
                <button type="submit" class="btn form-submit-btn" style="min-width: 180px; padding: 10px 25px; background-color: var(--gold); color: var(--darker-gold-2); border: none; border-radius: 5px; font-size: 1.05rem; <?php echo $font_family_style; ?> font-weight: bold; cursor: pointer; text-transform: uppercase;">Fotoğrafı Yükle</button>
            </div>
        </form>
         <p class="sub-link-alt" style="text-align: center; margin-top: 25px; font-size: 0.95rem; color: var(--light-grey); <?php echo $font_family_style; ?>">
            <a href="gallery.php" style="color: var(--turquase); text-decoration: none; font-weight: bold;">&laquo; Galeriye Geri Dön</a>
        </p>
    </div>
</main>
<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>