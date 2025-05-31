<?php
// public/edit_event.php

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

$event_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $event_id = (int)$_GET['id'];
} else {
    $_SESSION['error_message'] = "Geçersiz etkinlik ID'si.";
    header('Location: ' . get_auth_base_url() . '/events.php');
    exit;
}

$event = null;
$event_assigned_role_ids = []; // Etkinliğe atanmış rollerin ID'leri

try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :event_id");
    $stmt->bindParam(':event_id', $event_id, PDO::PARAM_INT);
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $_SESSION['error_message'] = "Düzenlenecek etkinlik bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/events.php');
        exit;
    }

    // Etkinliğe atanmış rolleri çek
    $stmt_event_roles = $pdo->prepare("SELECT role_id FROM event_visibility_roles WHERE event_id = ?");
    $stmt_event_roles->execute([$event_id]);
    $event_assigned_role_ids = $stmt_event_roles->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Etkinlik düzenleme için bilgi çekme hatası (ID: $event_id): " . $e->getMessage());
    $_SESSION['error_message'] = "Etkinlik bilgileri yüklenirken bir sorun oluştu.";
    header('Location: ' . get_auth_base_url() . '/events.php');
    exit;
}

require_approved_user();
$current_user_id_edit = $_SESSION['user_id'];
$can_edit_this_event = false;
if (has_permission($pdo, 'event.edit_all', $current_user_id_edit)) {
    $can_edit_this_event = true;
} elseif (has_permission($pdo, 'event.edit_own', $current_user_id_edit) && $event['created_by_user_id'] == $current_user_id_edit) {
    $can_edit_this_event = true;
}

if (!$can_edit_this_event) {
    $_SESSION['error_message'] = "Bu etkinliği düzenleme yetkiniz bulunmamaktadır.";
    header('Location: ' . get_auth_base_url() . '/event_detail.php?id=' . $event_id);
    exit;
}

$event_datetime_local = '';
if (!empty($event['event_datetime'])) {
    $dt = new DateTime($event['event_datetime']);
    $event_datetime_local = $dt->format('Y-m-d\TH:i');
}

$page_title = "Etkinliği Düzenle";
$form_title = htmlspecialchars($event['title']);
$font_family_style = "font-family: var(--font), serif;";

$loadout_sets_edit = [];
if (isset($pdo)) {
    try {
        $stmt_loadouts_edit = $pdo->query("SELECT id, set_name FROM loadout_sets ORDER BY set_name ASC");
        $loadout_sets_edit = $stmt_loadouts_edit->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* Hata loglama */ }
}

$assignable_roles_event_edit = [];
if (isset($pdo)) {
    try {
        $stmt_assignable_roles_edit = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member', 'dis_uye') ORDER BY name ASC");
        $assignable_roles_event_edit = $stmt_assignable_roles_edit->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* Hata loglama */ }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* create_event.php için Stiller (mevcut stillerinizle birleştirin) */
.create-event-container { /* edit_event için de bu class'ı kullanalım */
    max-width: 850px;
    background-color: var(--charcoal);
    padding: 30px 40px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--darker-gold-1);
    margin: 30px auto;
    box-sizing: border-box;
}
.create-event-container h2 {
    color: var(--gold);
    font-size: 2.2rem;
    text-align: center;
    margin-bottom: 30px;
    <?php echo $font_family_style; ?>
}
.create-event-container .form-group { margin-bottom: 22px; }
.create-event-container .form-group label {
    display: block; color: var(--lighter-grey); margin-bottom: 8px;
    font-size: 0.95rem; <?php echo $font_family_style; ?> font-weight: 500;
}
.create-event-container .form-control,
.create-event-container select.form-control {
    width: 100%; padding: 10px 14px; background-color: var(--grey);
    border: 1px solid var(--darker-gold-1); border-radius: 5px; color: var(--white);
    font-size: 1rem; <?php echo $font_family_style; ?> line-height: 1.5; box-sizing: border-box;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.create-event-container .form-control:focus,
.create-event-container select.form-control:focus {
    outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px var(--transparent-gold);
}
.create-event-container textarea.form-control { min-height: 120px; resize: vertical; }
.create-event-container select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23bd912a' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em;
    padding-right: 2.5rem; color-scheme: dark;
}
.create-event-container input[type="file"].form-control-file { /* ... (mevcut stil) ... */ }
.create-event-container input[type="file"].form-control-file::file-selector-button { /* ... (mevcut stil) ... */ }
.create-event-container .form-text-helper { /* ... (mevcut stil) ... */ }
.create-event-container .form-group-submit { /* ... (mevcut stil) ... */ }
.create-event-container .btn-submit-event { /* ... (mevcut stil) ... */ }

/* Yeni Görünürlük Alanı Stilleri */
.visibility-options-event {
    padding: 20px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    margin-top: 10px;
    border: 1px solid var(--darker-gold-1);
}
.visibility-options-event legend {
    font-size: 1.1rem;
    color: var(--light-gold);
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--darker-gold-1);
    width: 100%;
}
.visibility-checkbox-item-event {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
.visibility-checkbox-item-event input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
    accent-color: var(--gold);
    transform: scale(1.15);
}
.visibility-checkbox-item-event label {
    font-size: 0.95rem;
    color: var(--lighter-grey);
    cursor: pointer;
    font-weight: normal; /* Ana label'lar normal */
    margin-bottom: 0; /* Checkbox ile aynı hizada */
}
.visibility-checkbox-item-event label.disabled-label {
    color: var(--light-grey);
    cursor: not-allowed;
    opacity: 0.7;
}
.specific-roles-group-event {
    margin-top: 15px;
    padding-left: 25px; /* İçeri girinti */
    border-left: 2px solid var(--darker-gold-1);
}
.specific-roles-group-event.disabled-group {
    opacity: 0.6;
    pointer-events: none;
}
.specific-roles-group-event .visibility-checkbox-item-event label {
    font-weight: normal;
}
/* ... (mevcut create_event.php stilleriniz aynı kalacak) ... */
.create-event-container {
    max-width: 850px; /* Formun genişliği biraz artırıldı */
    background-color: var(--charcoal);
    padding: 30px 40px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--darker-gold-1);
    margin: 30px auto; /* Sayfada ortalamak için */
    box-sizing: border-box;
}

.create-event-container h2 {
    color: var(--gold);
    font-size: 2.2rem;
    text-align: center;
    margin-bottom: 30px;
    <?php echo $font_family_style; ?>
}

.create-event-container .form-group {
    margin-bottom: 22px; /* Form grupları arası boşluk */
}

.create-event-container .form-group label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-size: 0.95rem; /* Label fontu biraz daha belirgin */
    <?php echo $font_family_style; ?>
    font-weight: 500;
}

.create-event-container .form-control,
.create-event-container select.form-control { /* select eklendi */
    width: 100%;
    padding: 10px 14px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 5px;
    color: var(--white);
    font-size: 1rem;
    <?php echo $font_family_style; ?>
    line-height: 1.5;
    box-sizing: border-box;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.create-event-container .form-control:focus,
.create-event-container select.form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.create-event-container textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.create-event-container select.form-control {
    appearance: none; /* Tarayıcı varsayılan okunu kaldır */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23bd912a' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E"); /* Özel ok (var(--gold) renginde) */
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
    padding-right: 2.5rem; /* Ok için yer bırak */
    color-scheme: dark; /* select için dark mode uyumu */
}


.create-event-container input[type="file"].form-control-file {
    width: 100%;
    padding: 8px 0px; /* Düğme dışındaki alan için padding */
    background-color: var(--charcoal); /* Arka planı input ile aynı olsun */
    border: 1px solid var(--darker-gold-1);
    border-radius: 5px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    <?php echo $font_family_style; ?>
    line-height: 1.5;
    box-sizing: border-box;
}

.create-event-container input[type="file"].form-control-file::file-selector-button {
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
.create-event-container input[type="file"].form-control-file::file-selector-button:hover {
    background-color: var(--gold);
    color: var(--black);
    border-color: var(--light-gold);
}

.create-event-container .form-text-helper { /* Yardımcı metinler için */
    display: block;
    font-size: 0.8rem;
    color: var(--light-grey);
    margin-top: 6px;
    line-height: 1.3;
    <?php echo $font_family_style; ?>
}
.create-event-container .form-group-submit {
    margin-top: 30px;
    margin-bottom: 0;
    text-align: right;
}
.create-event-container .btn-submit-event { /* Ana submit butonu */
    min-width: 180px;
    padding: 10px 25px;
    background-color: var(--gold);
    color: var(--darker-gold-2);
    border: none;
    border-radius: 5px;
    font-size: 1.05rem;
    <?php echo $font_family_style; ?>
    font-weight: bold;
    cursor: pointer;
    text-transform: uppercase;
    transition: background-color 0.3s ease, transform 0.2s ease;
}
.create-event-container .btn-submit-event:hover {
    background-color: var(--light-gold);
    transform: translateY(-2px);
}
</style>

<main class="main-content">
    <div class="create-event-container">
        <h2 style="color: var(--gold); font-size: 2rem; text-align: center; margin-bottom: 15px; <?php echo $font_family_style; ?>"><?php echo $page_title; ?></h2>
        <p style="color: var(--light-gold); font-size: 1.3rem; text-align: center; margin-bottom: 30px; <?php echo $font_family_style; ?> border-bottom: 1px solid var(--darker-gold-2); padding-bottom:15px;">"<?php echo $form_title; ?>"</p>

        <form action="/src/actions/handle_edit_event.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">

            <div class="form-group">
                <label for="title">Etkinlik Başlığı:</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($event['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="event_datetime">Etkinlik Tarihi ve Saati:</label>
                <input type="datetime-local" id="event_datetime" name="event_datetime" class="form-control" value="<?php echo $event_datetime_local; ?>" required style="color-scheme: dark;">
            </div>
            <div class="form-group">
                <label for="description">Etkinlik Açıklaması:</label>
                <textarea id="description" name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($event['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="location">Etkinlik Konumu (Opsiyonel):</label>
                <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>" placeholder="Örn: Stanton > Hurston > Lorville">
            </div>
            <div class="form-group">
                <label for="event_type">Etkinlik Tipi:</label>
                <select id="event_type" name="event_type" class="form-control">
                    <?php $event_types = ['Genel', 'Operasyon', 'Eğitim', 'Sosyal', 'Ticaret', 'Keşif', 'Yarış', 'PVP']; ?>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($event['event_type'] === $type ? 'selected' : ''); ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <fieldset class="visibility-options-event">
                    <legend>Görünürlük Ayarları</legend>
                    <div class="visibility-checkbox-item-event">
                        <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth_event_edit" value="1"
                               <?php echo !empty($event['is_public_no_auth']) ? 'checked' : ''; ?>>
                        <label for="is_public_no_auth_event_edit">Herkese Açık (Giriş Yapmamış Kullanıcılar Dahil)</label>
                    </div>
                    <div class="visibility-checkbox-item-event">
                        <input type="checkbox" name="is_members_only" id="is_members_only_event_edit" value="1"
                               <?php echo !empty($event['is_members_only']) ? 'checked' : ''; ?>>
                        <label for="is_members_only_event_edit">Tüm Onaylı Üyelere Açık</label>
                    </div>

                    <div class="specific-roles-group-event" id="specific_roles_group_event_edit">
                        <p style="font-size:0.9em; color:var(--light-grey); margin-bottom:10px;">Veya Sadece Belirli Rollere Açık:</p>
                        <?php if (!empty($assignable_roles_event_edit)): ?>
                            <?php foreach ($assignable_roles_event_edit as $role):
                                $role_display_name_event_edit = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                $is_role_assigned_to_event = in_array($role['id'], $event_assigned_role_ids);
                            ?>
                                <div class="visibility-checkbox-item-event">
                                    <input type="checkbox" name="assigned_role_ids[]" id="event_edit_role_<?php echo $role['id']; ?>"
                                           value="<?php echo $role['id']; ?>" <?php echo $is_role_assigned_to_event ? 'checked' : ''; ?>>
                                    <label for="event_edit_role_<?php echo $role['id']; ?>"><?php echo $role_display_name_event_edit; ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="form-text-helper">Atanabilir özel rol bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                </fieldset>
                 <small class="form-text-helper" style="margin-top:5px; padding:8px; background-color:var(--grey); border-radius:4px; border-left:3px solid var(--turquase);">
                    <b>Not:</b> "Herkese Açık" seçilirse diğer ayarlar yok sayılır. "Tüm Onaylı Üyelere Açık" seçilirse, belirli rol seçimleri yok sayılır. Sadece belirli rollere özel yapmak için ilk iki seçeneğin işaretini kaldırın.
                </small>
            </div>

            <div class="form-group">
                <label for="max_participants">Maksimum Katılımcı Sayısı (Opsiyonel):</label>
                <input type="number" id="max_participants" name="max_participants" class="form-control" value="<?php echo htmlspecialchars($event['max_participants'] ?? ''); ?>" min="1" placeholder="Boş bırakılırsa sınırsız">
            </div>
            <div class="form-group">
                <label for="suggested_loadout_id_edit">Önerilen Teçhizat Seti (Opsiyonel):</label>
                <select id="suggested_loadout_id_edit" name="suggested_loadout_id" class="form-control">
                    <option value="">-- Teçhizat Seti Seç --</option>
                    <?php if (!empty($loadout_sets_edit)): ?>
                        <?php foreach ($loadout_sets_edit as $loadout_item): ?>
                            <option value="<?php echo htmlspecialchars($loadout_item['id']); ?>" <?php echo (($event['suggested_loadout_id'] ?? null) == $loadout_item['id'] ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($loadout_item['set_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <fieldset style="border: 1px solid var(--darker-gold-1); padding: 20px; border-radius: 6px; margin-top: 30px; margin-bottom: 20px; background-color: var(--darker-gold-2);">
                <legend style="padding: 0 10px; font-size: 1.1rem; font-weight: 600; color: var(--light-gold); <?php echo $font_family_style; ?>">Mevcut Fotoğraflar ve Güncelleme</legend>
                <?php for ($i = 1; $i <= 3; $i++):
                    $image_path_key = 'image_path_' . $i;
                    $current_image = $event[$image_path_key] ?? null;
                ?>
                    <div style="margin-bottom: 20px; padding-bottom:15px; <?php if ($i < 3) echo 'border-bottom: 1px dashed var(--grey);'; ?> ">
                        <label for="image_<?php echo $i; ?>" style="display: block; color: var(--lighter-grey); margin-bottom: 8px; font-size: 0.9rem; <?php echo $font_family_style; ?> font-weight: 500;">Etkinlik Fotoğrafı <?php echo $i; ?>:</label>
                        <?php if (!empty($current_image)): ?>
                            <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                                <img src="/public/<?php echo htmlspecialchars($current_image); ?>" alt="Mevcut Fotoğraf <?php echo $i; ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid var(--gold);">
                                <label style="display: flex; align-items: center; font-size: 0.85rem; color: var(--light-grey); cursor:pointer; <?php echo $font_family_style; ?>">
                                    <input type="checkbox" name="delete_image_<?php echo $i; ?>" id="delete_image_<?php echo $i; ?>" value="1" style="margin-right: 8px; width:16px; height:16px; accent-color: var(--gold);">
                                    Bu fotoğrafı sil
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="image_<?php echo $i; ?>" name="event_images[]" class="form-control-file" accept="image/jpeg, image/png, image/gif">
                        <small class="form-text-helper" style="display: block; font-size: 0.8rem; color: var(--light-grey); margin-top: 5px;"><?php echo !empty($current_image) ? 'Yeni bir dosya seçerseniz bu fotoğraf güncellenir.' : 'Yeni fotoğraf ekleyebilirsiniz.'; ?></small>
                    </div>
                <?php endfor; ?>
                <small class="form-text-helper" style="display: block; margin-top: 10px; text-align:center; <?php echo $font_family_style; ?>">İzin verilen formatlar: JPG, PNG, GIF. Maksimum boyut: 20MB (her biri için).</small>
            </fieldset>

            <div class="form-group-submit" style="margin-top: 30px; margin-bottom:0; display:flex; justify-content: flex-end; align-items:center; gap:15px;">
                <a href="event_detail.php?id=<?php echo $event_id; ?>" class="btn-cancel-upload" style="background-color: var(--grey); color: var(--lighter-grey); border: 1px solid var(--darker-gold-1); padding: 10px 25px; border-radius: 5px; text-decoration:none;">İptal</a>
                <button type="submit" class="btn-submit-event">Değişiklikleri Kaydet</button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const publicCheckboxEventEdit = document.getElementById('is_public_no_auth_event_edit');
    const membersOnlyCheckboxEventEdit = document.getElementById('is_members_only_event_edit');
    const specificRolesGroupEventEdit = document.getElementById('specific_roles_group_event_edit');
    const roleCheckboxesEventEdit = specificRolesGroupEventEdit ? specificRolesGroupEventEdit.querySelectorAll('input[type="checkbox"]') : [];
    const roleLabelsEventEdit = specificRolesGroupEventEdit ? specificRolesGroupEventEdit.querySelectorAll('label') : [];

    function updateEventEditVisibilityOptions() {
        const isPublic = publicCheckboxEventEdit.checked;
        const isMembersOnly = membersOnlyCheckboxEventEdit.checked;

        if (isPublic) {
            membersOnlyCheckboxEventEdit.checked = false;
            membersOnlyCheckboxEventEdit.disabled = true;
            roleCheckboxesEventEdit.forEach(cb => {
                cb.checked = false;
                cb.disabled = true;
            });
            roleLabelsEventEdit.forEach(lbl => lbl.classList.add('disabled-label'));
            if (specificRolesGroupEventEdit) specificRolesGroupEventEdit.classList.add('disabled-group');
        } else {
            membersOnlyCheckboxEventEdit.disabled = false;
            if (isMembersOnly) {
                roleCheckboxesEventEdit.forEach(cb => {
                    cb.checked = false;
                    cb.disabled = true;
                });
                roleLabelsEventEdit.forEach(lbl => lbl.classList.add('disabled-label'));
                if (specificRolesGroupEventEdit) specificRolesGroupEventEdit.classList.add('disabled-group');
            } else {
                roleCheckboxesEventEdit.forEach(cb => {
                    cb.disabled = false;
                });
                roleLabelsEventEdit.forEach(lbl => lbl.classList.remove('disabled-label'));
                if (specificRolesGroupEventEdit) specificRolesGroupEventEdit.classList.remove('disabled-group');
            }
        }
    }

    if (publicCheckboxEventEdit) {
        publicCheckboxEventEdit.addEventListener('change', updateEventEditVisibilityOptions);
    }
    if (membersOnlyCheckboxEventEdit) {
        membersOnlyCheckboxEventEdit.addEventListener('change', updateEventEditVisibilityOptions);
    }
    updateEventEditVisibilityOptions(); // Sayfa yüklendiğinde de durumu ayarla
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
