<?php
// public/admin/new_loadout_set.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları için

// Yetki kontrolü: Sadece 'loadout.manage_sets' yetkisine sahip olanlar erişebilir.
// require_admin() yerine daha spesifik bir yetki kontrolü.
// Eğer tüm adminler bu yetkiye sahipse, require_admin() da kalabilir.
// Ancak, permissions.php'ye göre bu yetki adminlere özel olduğu için require_admin() yeterli olacaktır.
require_admin(); 
// Alternatif olarak: require_permission($pdo, 'loadout.manage_sets');

$page_title = "Yeni Teçhizat Seti Oluştur";

$form_set_name = $_SESSION['form_input_loadout']['set_name'] ?? '';
$form_set_description = $_SESSION['form_input_loadout']['set_description'] ?? '';
$form_status = $_SESSION['form_input_loadout']['status'] ?? 'draft'; // Varsayılan taslak
$form_visibility = $_SESSION['form_input_loadout']['visibility'] ?? 'members_only'; // Varsayılan üyelere özel
$form_assigned_role_ids = $_SESSION['form_input_loadout']['assigned_role_ids'] ?? [];
unset($_SESSION['form_input_loadout']);

// Atanabilir rolleri çek
$assignable_roles_loadout = [];
if (isset($pdo)) {
    try {
        $stmt_roles_loadout = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member') ORDER BY name ASC");
        $assignable_roles_loadout = $stmt_roles_loadout->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Roller çekilirken hata (new_loadout_set.php): " . $e->getMessage());
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* new_loadout_set.php için Stiller (mevcut stil dosyanızdaki .admin-container vb. ile uyumlu olmalı) */
.admin-container .page-header { /* Zaten style.css'de olabilir, kontrol edin */
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-1);
}
.admin-container .page-header h1 {
    color: var(--gold);
    font-size: 1.8rem; /* Admin başlıkları için */
    font-family: var(--font);
    margin: 0;
}
.admin-container .btn-secondary { /* Zaten style.css'de olabilir */
    color: var(--lighter-grey);
    background-color: var(--grey);
    border-color: var(--grey);
}
.admin-container .btn-secondary:hover {
    color: var(--white);
    background-color: #494949;
}
.admin-container .btn-sm { /* Zaten style.css'de olabilir */
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    border-radius: 0.2rem;
}

.admin-container .message { /* Zaten style.css'de olabilir */
    padding: 12px 18px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-size: 0.95rem;
    border: 1px solid transparent;
    text-align: left;
}
.admin-container .message.error-message {
    background-color: var(--transparent-red);
    color: var(--red);
    border-color: var(--dark-red);
}
.admin-container .message.success-message {
    background-color: rgba(60, 166, 60, 0.15);
    color: #5cb85c;
    border-color: #4cae4c;
}

.admin-container .form-card {
    background-color: var(--charcoal);
    padding: 25px 30px;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    margin-top: 25px;
    margin-bottom: 40px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.admin-container .form-card .form-group {
    margin-bottom: 22px;
}
.admin-container .form-card .form-group label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-size: 0.95rem;
    font-weight: 500;
}
.admin-container .form-card .form-control {
    width: 100%;
    padding: 10px 14px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 5px;
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
    box-sizing: border-box;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.admin-container .form-card .form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}
.admin-container .form-card textarea.form-control {
    min-height: 100px; 
    resize: vertical;
    line-height: 1.5;
}

.admin-container .form-card .form-control-file {
    display: block;
    width: 100%;
    padding: 0; 
    font-size: 0.9rem;
    font-family: var(--font);
    color: var(--lighter-grey);
    background-color: var(--grey); 
    border: 1px solid var(--darker-gold-1);
    border-radius: 5px;
    box-sizing: border-box;
    line-height: normal; 
}
.admin-container .form-card .form-control-file::file-selector-button {
    padding: 10px 15px; 
    margin-right: 10px;
    background-color: var(--light-gold);
    color: var(--darker-gold-2);
    border: none;
    border-right: 1px solid var(--darker-gold-1); 
    border-radius: 4px 0 0 4px;
    cursor: pointer;
    font-family: var(--font);
    font-weight: 500;
    transition: background-color 0.2s ease;
    height: calc(2.4rem + 2px); 
}
.admin-container .form-card .form-control-file::file-selector-button:hover {
    background-color: var(--gold);
}
.admin-container .form-card .form-text {
    display: block;
    font-size: 0.8rem;
    color: var(--light-grey);
    margin-top: 6px;
    line-height: 1.3;
}
.admin-container .form-card button.btn-success { /* Ana submit butonu için */
    font-weight: 500;
    padding: 10px 25px;
    /* Diğer .btn-success stilleri style.css'den gelmeli */
}

/* Görünürlük ayarları için stiller (new_guide.php'den uyarlanabilir) */
.admin-container .form-card .visibility-section legend {
    font-size: 1.1rem;
    color: var(--light-gold);
    font-weight: 500;
    margin-top: 15px;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.admin-container .form-card .visibility-checkbox-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.admin-container .form-card .visibility-checkbox-item input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
    accent-color: var(--gold);
    transform: scale(1.1);
}
.admin-container .form-card .visibility-checkbox-item label {
    font-weight: normal;
    font-size: 0.9rem;
    color: var(--lighter-grey);
    margin-bottom: 0; /* input ile aynı hizada olduğu için */
}
.admin-container .form-card #role_specific_options {
    padding-left: 20px;
    margin-top: 10px;
    border-left: 2px solid var(--darker-gold-1);
}
</style>
<main>
    <div class="admin-container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <div>
                <a href="manage_loadout_sets.php" class="btn btn-sm btn-secondary">&laquo; Set Yönetimine Dön</a>
            </div>
        </div>

        <?php 
        if (defined('BASE_PATH') && file_exists(BASE_PATH . '/src/includes/admin_quick_navigation.php')) {
            require BASE_PATH . '/src/includes/admin_quick_navigation.php';
        }
        ?>
        
        <?php // Session mesajları header.php'de gösteriliyor olabilir, gerekirse burada da gösterilebilir. ?>

        <div class="form-card"> 
            <form action="../../src/actions/handle_loadout_set.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_set">
                <div class="form-group">
                    <label for="set_name">Set Adı / Rol Adı:</label>
                    <input type="text" id="set_name" name="set_name" class="form-control" value="<?php echo htmlspecialchars($form_set_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="set_description">Set Açıklaması (Opsiyonel):</label>
                    <textarea id="set_description" name="set_description" class="form-control" rows="4"><?php echo htmlspecialchars($form_set_description); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="set_image">Set Görseli (Komple Teçhizat - Opsiyonel):</label>
                    <input type="file" id="set_image" name="set_image" class="form-control-file" accept="image/jpeg, image/png, image/gif">
                    <small class="form-text">İzin verilen formatlar: JPG, PNG, GIF. Maksimum 5MB.</small>
                </div>

                <fieldset class="form-group visibility-section">
                    <legend>Yayın Ayarları ve Görünürlük</legend>
                    <div class="form-group">
                        <label for="status">Yayın Durumu:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="draft" <?php echo ($form_status === 'draft' ? 'selected' : ''); ?>>Taslak Olarak Kaydet</option>
                            <option value="published" <?php echo ($form_status === 'published' ? 'selected' : ''); ?>>Hemen Yayınla</option>
                            <option value="archived" <?php echo ($form_status === 'archived' ? 'selected' : ''); ?>>Arşivle (Listede Görünmesin)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visibility">Görünürlük:</label>
                        <select name="visibility" id="visibility" class="form-control">
                            <option value="public" <?php echo ($form_visibility === 'public' ? 'selected' : ''); ?>>Herkese Açık</option>
                            <option value="members_only" <?php echo ($form_visibility === 'members_only' ? 'selected' : ''); ?>>Sadece Onaylı Üyelere</option>
                            <option value="faction_only" <?php echo ($form_visibility === 'faction_only' ? 'selected' : ''); ?>>Belirli Rollere Özel</option>
                            <option value="private" <?php echo ($form_visibility === 'private' ? 'selected' : ''); ?>>Özel (Sadece Oluşturan ve Adminler)</option>
                        </select>
                    </div>
                    <div id="role_specific_options" class="form-group" style="<?php echo ($form_visibility !== 'faction_only' ? 'display:none;' : ''); ?>">
                        <label>Hangi Roller Görebilir?</label>
                        <?php if (!empty($assignable_roles_loadout)): ?>
                            <?php foreach ($assignable_roles_loadout as $role):
                                $role_display_name = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                ?>
                                <div class="visibility-checkbox-item">
                                    <input type="checkbox" name="assigned_role_ids[]" id="loadout_role_<?php echo $role['id']; ?>"
                                           value="<?php echo $role['id']; ?>"
                                           <?php echo in_array($role['id'], $form_assigned_role_ids) ? 'checked' : ''; ?>>
                                    <label for="loadout_role_<?php echo $role['id']; ?>"><?php echo $role_display_name; ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="form-text">Sistemde atanabilir özel rol bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-success">Yeni Seti Oluştur</button>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const visibilitySelect = document.getElementById('visibility');
    const roleSpecificOptionsDiv = document.getElementById('role_specific_options');

    function toggleRoleOptions() {
        if (visibilitySelect.value === 'faction_only') {
            roleSpecificOptionsDiv.style.display = 'block';
        } else {
            roleSpecificOptionsDiv.style.display = 'none';
            // Diğer seçenekler seçildiğinde rollerin işaretini kaldırabiliriz
            const roleCheckboxes = roleSpecificOptionsDiv.querySelectorAll('input[type="checkbox"]');
            roleCheckboxes.forEach(cb => cb.checked = false);
        }
    }

    if (visibilitySelect && roleSpecificOptionsDiv) {
        visibilitySelect.addEventListener('change', toggleRoleOptions);
        toggleRoleOptions(); // Sayfa yüklendiğinde durumu ayarla
    }
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
