<?php
// public/admin/edit_loadout_set_details.php

require_once '../../src/config/database.php'; 
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover için

require_admin(); 
// Alternatif: require_permission($pdo, 'loadout.manage_sets');

$set_id_to_edit = null;
$loadout_set_data = null;
$page_title = "Set Detaylarını Düzenle";

// $GLOBALS['role_priority'] global olarak tanımlı olmalı (örn: database.php içinde)
if (!isset($GLOBALS['role_priority'])) {
    $GLOBALS['role_priority'] = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];
}

if (isset($_GET['set_id']) && is_numeric($_GET['set_id'])) {
    $set_id_to_edit = (int)$_GET['set_id'];

    try {
        if (!isset($pdo)) {
            throw new Exception("Veritabanı bağlantısı bulunamadı.");
        }
        // SQL sorgusuna kullanıcı (creator) detaylarını ekle
        $sql_set_details = "SELECT 
                                ls.*, 
                                u.username AS creator_username, 
                                u.avatar_path AS creator_avatar_path,
                                u.ingame_name AS creator_ingame_name,
                                u.discord_username AS creator_discord_username,
                                (SELECT COUNT(*) FROM events WHERE created_by_user_id = u.id) AS creator_event_count,
                                (SELECT COUNT(*) FROM gallery_photos WHERE user_id = u.id) AS creator_gallery_count,
                                (SELECT GROUP_CONCAT(r.name ORDER BY FIELD(r.name, '" . implode("','", $GLOBALS['role_priority']) . "') SEPARATOR ',') 
                                 FROM user_roles ur_creator 
                                 JOIN roles r ON ur_creator.role_id = r.id 
                                 WHERE ur_creator.user_id = u.id) AS creator_roles_list
                            FROM loadout_sets ls
                            JOIN users u ON ls.user_id = u.id
                            WHERE ls.id = ?";
        $stmt = $pdo->prepare($sql_set_details);
        $stmt->execute([$set_id_to_edit]);
        $loadout_set_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loadout_set_data) {
            $_SESSION['error_message'] = "Düzenlenecek teçhizat seti bulunamadı.";
            header('Location: ' . get_auth_base_url() . '/admin/manage_loadout_sets.php');
            exit;
        }
        $page_title = "Düzenle: " . htmlspecialchars($loadout_set_data['set_name']);
    } catch (Exception $e) {
        error_log("Set detaylarını çekme hatası: " . $e->getMessage());
        if (session_status() == PHP_SESSION_NONE) session_start();
        $_SESSION['error_message'] = "Set bilgileri yüklenirken bir sorun oluştu.";
        header('Location: ' . get_auth_base_url() . '/admin/manage_loadout_sets.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Geçersiz set ID'si.";
    header('Location: ' . get_auth_base_url() . '/admin/manage_loadout_sets.php');
    exit;
}

$form_set_name = $_SESSION['form_input_loadout_edit']['edit_set_name'] ?? ($loadout_set_data['set_name'] ?? '');
$form_set_description = $_SESSION['form_input_loadout_edit']['edit_set_description'] ?? ($loadout_set_data['set_description'] ?? '');
$form_status = $_SESSION['form_input_loadout_edit']['status'] ?? ($loadout_set_data['status'] ?? 'draft');
$form_visibility = $_SESSION['form_input_loadout_edit']['visibility'] ?? ($loadout_set_data['visibility'] ?? 'members_only');

// Atanabilir rolleri çek
$assignable_roles_loadout_edit = [];
if (isset($pdo)) {
    try {
        $stmt_roles_loadout_edit = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member') ORDER BY name ASC");
        $assignable_roles_loadout_edit = $stmt_roles_loadout_edit->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Roller çekilirken hata (edit_loadout_set_details.php): " . $e->getMessage());
    }
}

// Sete atanmış mevcut rolleri çek
$current_assigned_role_ids = [];
if ($set_id_to_edit) {
    try {
        $stmt_assigned = $pdo->prepare("SELECT role_id FROM loadout_set_visibility_roles WHERE set_id = ?");
        $stmt_assigned->execute([$set_id_to_edit]);
        $current_assigned_role_ids = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Sete atanmış roller çekilirken hata: " . $e->getMessage());
    }
}
$form_assigned_role_ids = $_SESSION['form_input_loadout_edit']['assigned_role_ids'] ?? $current_assigned_role_ids;
unset($_SESSION['form_input_loadout_edit']);


require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* edit_loadout_set_details.php için Stiller (new_loadout_set.php'ye benzer) */
/* ... (new_loadout_set.php'deki .admin-container, .page-header, .form-card vb. stillerini buraya kopyalayabilirsiniz) ... */
.admin-container .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--darker-gold-1); }
.admin-container .page-header h1 { color: var(--gold); font-size: 1.8rem; font-family: var(--font); margin: 0; }
.admin-container .btn-secondary { color: var(--lighter-grey); background-color: var(--grey); border-color: var(--grey); }
.admin-container .btn-secondary:hover { color: var(--white); background-color: #494949; }
.admin-container .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; border-radius: 0.2rem; }

.admin-container .message { padding: 12px 18px; margin-bottom: 20px; border-radius: 6px; font-size: 0.95rem; border: 1px solid transparent; text-align: left; }
.admin-container .message.error-message { background-color: var(--transparent-red); color: var(--red); border-color: var(--dark-red); }
.admin-container .message.success-message { background-color: rgba(60, 166, 60, 0.15); color: #5cb85c; border-color: #4cae4c; }

.admin-container .form-card { background-color: var(--charcoal); padding: 25px 30px; border-radius: 8px; border: 1px solid var(--darker-gold-1); margin-top: 25px; margin-bottom: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.admin-container .form-card .form-group { margin-bottom: 22px; }
.admin-container .form-card .form-group label { display: block; color: var(--lighter-grey); margin-bottom: 8px; font-size: 0.95rem; font-weight: 500; }
.admin-container .form-card .form-control { width: 100%; padding: 10px 14px; background-color: var(--grey); border: 1px solid var(--darker-gold-1); border-radius: 5px; color: var(--white); font-size: 1rem; font-family: var(--font); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
.admin-container .form-card .form-control:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px var(--transparent-gold); }
.admin-container .form-card textarea.form-control { min-height: 100px; resize: vertical; line-height: 1.5; }
.admin-container .form-card .form-control-file { display: block; width: 100%; padding: 0; font-size: 0.9rem; font-family: var(--font); color: var(--lighter-grey); background-color: var(--grey); border: 1px solid var(--darker-gold-1); border-radius: 5px; box-sizing: border-box; line-height: normal; }
.admin-container .form-card .form-control-file::file-selector-button { padding: 10px 15px; margin-right: 10px; background-color: var(--light-gold); color: var(--darker-gold-2); border: none; border-right: 1px solid var(--darker-gold-1); border-radius: 4px 0 0 4px; cursor: pointer; font-family: var(--font); font-weight: 500; transition: background-color 0.2s ease; height: calc(2.4rem + 2px); }
.admin-container .form-card .form-control-file::file-selector-button:hover { background-color: var(--gold); }
.admin-container .form-card .form-text { display: block; font-size: 0.8rem; color: var(--light-grey); margin-top: 6px; line-height: 1.3; }
.admin-container .form-card button.btn-primary { font-weight: 500; padding: 10px 25px; }

.set-creator-info { /* Seti oluşturan bilgisi için */
    margin-bottom: 20px;
    padding: 10px 15px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    font-size: 0.9em;
    color: var(--light-grey);
    border: 1px solid var(--darker-gold-1);
}
.set-creator-info strong { color: var(--light-gold); }
/* Popover için .user-info-trigger class'ı kullanılacak */
.creator-avatar-small-admin { /* render_user_info_with_popover için avatar class'ı */
    width: 24px; height: 24px; border-radius: 50%; object-fit: cover; margin-right: 6px; vertical-align: middle;
}
.creator-name-link-admin { /* render_user_info_with_popover için link class'ı */
    /* Renkler popover.js ve inline style ile yönetilecek */
}

/* Görünürlük ayarları için stiller (new_loadout_set.php'den uyarlanabilir) */
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
    margin-bottom: 0;
}
.admin-container .form-card #role_specific_options_edit { /* ID güncellendi */
    padding-left: 20px;
    margin-top: 10px;
    border-left: 2px solid var(--darker-gold-1);
}
</style>
<main>
    <div class="admin-container">
        <div class="page-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="manage_loadout_sets.php" class="btn btn-sm btn-secondary">&laquo; Set Yönetimine Dön</a>
        </div>

        <?php 
        if (defined('BASE_PATH') && file_exists(BASE_PATH . '/src/includes/admin_quick_navigation.php')) {
            require BASE_PATH . '/src/includes/admin_quick_navigation.php';
        }
        ?>
        
        <?php // Session mesajları ?>

        <?php if ($loadout_set_data): ?>
            <div class="set-creator-info">
                <strong>Seti Oluşturan:</strong> 
                <?php
                $creator_data_for_popover_edit_details = [
                    'id' => $loadout_set_data['user_id'], // SQL'de user_id olarak geliyor
                    'username' => $loadout_set_data['creator_username'],
                    'avatar_path' => $loadout_set_data['creator_avatar_path'],
                    'ingame_name' => $loadout_set_data['creator_ingame_name'],
                    'discord_username' => $loadout_set_data['creator_discord_username'],
                    'user_event_count' => $loadout_set_data['creator_event_count'],
                    'user_gallery_count' => $loadout_set_data['creator_gallery_count'],
                    'user_roles_list' => $loadout_set_data['creator_roles_list']
                ];
                echo render_user_info_with_popover(
                    $pdo,
                    $creator_data_for_popover_edit_details,
                    'creator-name-link-admin',      // Link için CSS class'ı
                    'creator-avatar-small-admin',   // Avatar için CSS class'ı
                    ''                              // Wrapper span için ek class (boş olabilir)
                );
                ?>
                 | Oluşturulma: <?php echo date('d M Y, H:i', strtotime($loadout_set_data['created_at'])); ?>
                 <?php if ($loadout_set_data['updated_at'] && strtotime($loadout_set_data['updated_at']) > strtotime($loadout_set_data['created_at']) + 60): ?>
                    | Son Güncelleme: <?php echo date('d M Y, H:i', strtotime($loadout_set_data['updated_at'])); ?>
                 <?php endif; ?>
            </div>
        <?php endif; ?>


        <div class="form-card" style="margin-top:20px;">
            <form action="../../src/actions/handle_loadout_set.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_set_details">
                <input type="hidden" name="set_id" value="<?php echo $set_id_to_edit; ?>">
                <input type="hidden" name="existing_image_path" value="<?php echo htmlspecialchars($loadout_set_data['set_image_path'] ?? ''); ?>">

                <div class="form-group">
                    <label for="edit_set_name">Set Adı / Rol Adı:</label>
                    <input type="text" id="edit_set_name" name="edit_set_name" class="form-control" value="<?php echo htmlspecialchars($form_set_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_set_description">Set Açıklaması (Opsiyonel):</label>
                    <textarea id="edit_set_description" name="edit_set_description" class="form-control" rows="4"><?php echo htmlspecialchars($form_set_description); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_set_image">Set Görselini Değiştir (Opsiyonel):</label>
                    <?php if (!empty($loadout_set_data['set_image_path'])): ?>
                        <div style="margin-bottom:10px;">
                            <p style="font-size:0.9em; color:var(--light-grey);">Mevcut Görsel:</p>
                            <img src="/public/<?php echo htmlspecialchars($loadout_set_data['set_image_path']); ?>" alt="Mevcut Set Görseli" style="max-width:200px; max-height:150px; border-radius:4px; margin-right:10px; border:1px solid var(--grey);">
                            <br>
                            <label for="delete_set_image" style="font-weight:normal; font-size:0.9em; display:inline-flex; align-items:center; margin-top:5px;">
                                <input type="checkbox" name="delete_set_image" id="delete_set_image" value="1" style="width:auto; margin-right:5px; vertical-align:middle; accent-color: var(--red);">
                                Mevcut görseli sil
                            </label>
                        </div>
                        <small class="form-text">Yeni bir dosya seçerseniz mevcut görsel güncellenir. Sadece silmek için kutuyu işaretleyip kaydedin.</small>
                    <?php else: ?>
                        <small class="form-text">Henüz bir görsel yüklenmemiş. Yeni bir dosya seçebilirsiniz.</small>
                    <?php endif; ?>
                    <input type="file" id="edit_set_image" name="edit_set_image" class="form-control-file" accept="image/jpeg, image/png, image/gif" style="margin-top:10px;">
                    <small class="form-text">İzin verilen formatlar: JPG, PNG, GIF. Maksimum 5MB.</small>
                </div>

                <fieldset class="form-group visibility-section">
                    <legend>Yayın Ayarları ve Görünürlük</legend>
                    <div class="form-group">
                        <label for="status_edit">Yayın Durumu:</label>
                        <select name="status" id="status_edit" class="form-control">
                            <option value="draft" <?php echo ($form_status === 'draft' ? 'selected' : ''); ?>>Taslak</option>
                            <option value="published" <?php echo ($form_status === 'published' ? 'selected' : ''); ?>>Yayınlanmış</option>
                            <option value="archived" <?php echo ($form_status === 'archived' ? 'selected' : ''); ?>>Arşivlenmiş</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visibility_edit">Görünürlük:</label>
                        <select name="visibility" id="visibility_edit" class="form-control">
                            <option value="public" <?php echo ($form_visibility === 'public' ? 'selected' : ''); ?>>Herkese Açık</option>
                            <option value="members_only" <?php echo ($form_visibility === 'members_only' ? 'selected' : ''); ?>>Sadece Onaylı Üyelere</option>
                            <option value="faction_only" <?php echo ($form_visibility === 'faction_only' ? 'selected' : ''); ?>>Belirli Rollere Özel</option>
                            <option value="private" <?php echo ($form_visibility === 'private' ? 'selected' : ''); ?>>Özel (Sadece Oluşturan ve Adminler)</option>
                        </select>
                    </div>
                    <div id="role_specific_options_edit" class="form-group" style="<?php echo ($form_visibility !== 'faction_only' ? 'display:none;' : ''); ?>">
                        <label>Hangi Roller Görebilir?</label>
                        <?php if (!empty($assignable_roles_loadout_edit)): ?>
                            <?php foreach ($assignable_roles_loadout_edit as $role):
                                $role_display_name_edit = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                ?>
                                <div class="visibility-checkbox-item">
                                    <input type="checkbox" name="assigned_role_ids[]" id="loadout_edit_role_<?php echo $role['id']; ?>"
                                           value="<?php echo $role['id']; ?>"
                                           <?php echo in_array($role['id'], $form_assigned_role_ids) ? 'checked' : ''; ?>>
                                    <label for="loadout_edit_role_<?php echo $role['id']; ?>"><?php echo $role_display_name_edit; ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="form-text">Sistemde atanabilir özel rol bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const visibilitySelectEdit = document.getElementById('visibility_edit');
    const roleSpecificOptionsDivEdit = document.getElementById('role_specific_options_edit');

    function toggleRoleOptionsEdit() {
        if (visibilitySelectEdit.value === 'faction_only') {
            roleSpecificOptionsDivEdit.style.display = 'block';
        } else {
            roleSpecificOptionsDivEdit.style.display = 'none';
            // Diğer seçenekler seçildiğinde rollerin işaretini kaldırmıyoruz, çünkü mevcut atamalar olabilir.
            // Kullanıcı manuel olarak kaldırmalı veya farklı bir görünürlük seçtiğinde action tarafında temizlenmeli.
        }
    }

    if (visibilitySelectEdit && roleSpecificOptionsDivEdit) {
        visibilitySelectEdit.addEventListener('change', toggleRoleOptionsEdit);
        toggleRoleOptionsEdit(); // Sayfa yüklendiğinde durumu ayarla
    }
});
</script>

<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>
