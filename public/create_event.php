<?php
// public/create_event.php

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

require_approved_user();
require_permission($pdo, 'event.create');

$page_title = "Yeni Etkinlik Oluştur";
$font_family_style = "font-family: var(--font), serif;";

$form_input = $_SESSION['form_input'] ?? [];
unset($_SESSION['form_input']);

$loadout_sets = [];
if (isset($pdo)) {
    try {
        $stmt_loadouts = $pdo->query("SELECT id, set_name FROM loadout_sets ORDER BY set_name ASC");
        $loadout_sets = $stmt_loadouts->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Teçhizat setleri çekilirken hata (create_event.php): " . $e->getMessage());
    }
}

// Atanabilir rolleri çek (admin ve member hariç, isteğe bağlı olarak dis_uye de hariç tutulabilir)
$assignable_roles_event = [];
if (isset($pdo)) {
    try {
        // Örnek olarak 'admin', 'member', 'dis_uye' rollerini doğrudan atama listesinden çıkaralım.
        // Bu rollerin ID'lerini bilmeniz veya isimleriyle sorgulamanız gerekebilir.
        // Şimdilik isimle hariç tutalım, daha sağlam bir çözüm için ID'leri kullanmak daha iyi olabilir.
        $stmt_assignable_roles = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member', 'dis_uye') ORDER BY name ASC");
        $assignable_roles_event = $stmt_assignable_roles->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Atanabilir roller çekilirken hata (create_event.php): " . $e->getMessage());
    }
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

<main class="main-content auth-page">
    <div class="create-event-container">
        <h2><?php echo htmlspecialchars($page_title); ?></h2>

        <form action="/src/actions/handle_create_event.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Etkinlik Başlığı:</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($form_input['title'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="event_datetime">Etkinlik Tarihi ve Saati:</label>
                <input type="datetime-local" id="event_datetime" name="event_datetime" class="form-control" value="<?php echo htmlspecialchars($form_input['event_datetime'] ?? ''); ?>" required style="color-scheme: dark;">
            </div>
            <div class="form-group">
                <label for="description">Etkinlik Açıklaması:</label>
                <textarea id="description" name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($form_input['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="location">Etkinlik Konumu (Opsiyonel):</label>
                <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($form_input['location'] ?? ''); ?>" placeholder="Örn: Stanton > Hurston > Lorville">
            </div>

            <div class="form-group">
                <label for="event_type">Etkinlik Tipi:</label>
                <select id="event_type" name="event_type" class="form-control">
                    <option value="Genel" <?php echo (($form_input['event_type'] ?? 'Genel') === 'Genel' ? 'selected' : ''); ?>>Genel</option>
                    <option value="Operasyon" <?php echo (($form_input['event_type'] ?? '') === 'Operasyon' ? 'selected' : ''); ?>>Operasyon</option>
                    <option value="Eğitim" <?php echo (($form_input['event_type'] ?? '') === 'Eğitim' ? 'selected' : ''); ?>>Eğitim</option>
                    <option value="Sosyal" <?php echo (($form_input['event_type'] ?? '') === 'Sosyal' ? 'selected' : ''); ?>>Sosyal</option>
                    <option value="Ticaret" <?php echo (($form_input['event_type'] ?? '') === 'Ticaret' ? 'selected' : ''); ?>>Ticaret</option>
                    <option value="Keşif" <?php echo (($form_input['event_type'] ?? '') === 'Keşif' ? 'selected' : ''); ?>>Keşif</option>
                    <option value="Yarış" <?php echo (($form_input['event_type'] ?? '') === 'Yarış' ? 'selected' : ''); ?>>Yarış</option>
                    <option value="PVP" <?php echo (($form_input['event_type'] ?? '') === 'PVP' ? 'selected' : ''); ?>>PVP</option>
                </select>
            </div>

            <div class="form-group">
                <fieldset class="visibility-options-event">
                    <legend>Görünürlük Ayarları</legend>
                    <div class="visibility-checkbox-item-event">
                        <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth_event" value="1"
                               <?php echo !empty($form_input['is_public_no_auth']) ? 'checked' : ''; ?>>
                        <label for="is_public_no_auth_event">Herkese Açık (Giriş Yapmamış Kullanıcılar Dahil)</label>
                    </div>
                    <div class="visibility-checkbox-item-event">
                        <input type="checkbox" name="is_members_only" id="is_members_only_event" value="1"
                               <?php echo (isset($form_input['is_members_only']) && $form_input['is_members_only'] == '1') || (!isset($form_input['is_public_no_auth']) && !isset($form_input['assigned_role_ids']) && !isset($form_input['is_members_only'])) ? 'checked' : ''; // Varsayılan olarak işaretli olabilir ?>>
                        <label for="is_members_only_event">Tüm Onaylı Üyelere Açık</label>
                    </div>

                    <div class="specific-roles-group-event" id="specific_roles_group_event">
                        <p style="font-size:0.9em; color:var(--light-grey); margin-bottom:10px;">Veya Sadece Belirli Rollere Açık:</p>
                        <?php if (!empty($assignable_roles_event)): ?>
                            <?php foreach ($assignable_roles_event as $role):
                                $role_display_name_event = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                $is_role_checked_event = isset($form_input['assigned_role_ids']) && is_array($form_input['assigned_role_ids']) && in_array($role['id'], $form_input['assigned_role_ids']);
                            ?>
                                <div class="visibility-checkbox-item-event">
                                    <input type="checkbox" name="assigned_role_ids[]" id="event_role_<?php echo $role['id']; ?>"
                                           value="<?php echo $role['id']; ?>" <?php echo $is_role_checked_event ? 'checked' : ''; ?>>
                                    <label for="event_role_<?php echo $role['id']; ?>"><?php echo $role_display_name_event; ?></label>
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
                <input type="number" id="max_participants" name="max_participants" class="form-control" value="<?php echo htmlspecialchars($form_input['max_participants'] ?? ''); ?>" min="1" placeholder="Boş bırakılırsa sınırsız">
            </div>

            <div class="form-group">
                <label for="suggested_loadout_id">Önerilen Teçhizat Seti (Opsiyonel):</label>
                <select id="suggested_loadout_id" name="suggested_loadout_id" class="form-control">
                    <option value="">-- Teçhizat Seti Seç --</option>
                    <?php if (!empty($loadout_sets)): ?>
                        <?php foreach ($loadout_sets as $loadout): ?>
                            <option value="<?php echo htmlspecialchars($loadout['id']); ?>" <?php echo (($form_input['suggested_loadout_id'] ?? null) == $loadout['id'] ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($loadout['set_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Kullanılabilir teçhizat seti bulunmuyor.</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="image_1">Etkinlik Fotoğrafı 1 (Opsiyonel): <span style="color:var(--gold); font-weight: bold;">İlk fotoğraf etkinlik kartı için kapak resmi olacaktır.</span></label>
                <input type="file" id="image_1" name="event_images[]" class="form-control-file" accept="image/jpeg, image/png, image/gif">
            </div>
            <div class="form-group">
                <label for="image_2">Etkinlik Fotoğrafı 2 (Opsiyonel):</label>
                <input type="file" id="image_2" name="event_images[]" class="form-control-file" accept="image/jpeg, image/png, image/gif">
            </div>
            <div class="form-group">
                <label for="image_3">Etkinlik Fotoğrafı 3 (Opsiyonel):</label>
                <input type="file" id="image_3" name="event_images[]" class="form-control-file" accept="image/jpeg, image/png, image/gif">
            </div>
            <small class="form-text-helper" style="display: block; margin-bottom: 20px;">İzin verilen formatlar: JPG, PNG, GIF. Maksimum boyut: 20MB (her biri için).</small>

            <div class="form-group form-group-submit">
                <button type="submit" class="btn-submit-event">Etkinliği Oluştur</button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const publicCheckboxEvent = document.getElementById('is_public_no_auth_event');
    const membersOnlyCheckboxEvent = document.getElementById('is_members_only_event');
    const specificRolesGroupEvent = document.getElementById('specific_roles_group_event');
    const roleCheckboxesEvent = specificRolesGroupEvent ? specificRolesGroupEvent.querySelectorAll('input[type="checkbox"]') : [];
    const roleLabelsEvent = specificRolesGroupEvent ? specificRolesGroupEvent.querySelectorAll('label') : [];

    function updateEventVisibilityOptions() {
        const isPublic = publicCheckboxEvent.checked;
        const isMembersOnly = membersOnlyCheckbox.checked;

        // "Herkese Açık" seçiliyse diğerlerini devre dışı bırak ve işareti kaldır
        if (isPublic) {
            membersOnlyCheckbox.checked = false;
            membersOnlyCheckbox.disabled = true;
            roleCheckboxesEvent.forEach(cb => {
                cb.checked = false;
                cb.disabled = true;
            });
            roleLabelsEvent.forEach(lbl => lbl.classList.add('disabled-label'));
            if (specificRolesGroupEvent) specificRolesGroupEvent.classList.add('disabled-group');
        } else {
            membersOnlyCheckbox.disabled = false;
            // "Herkese Açık" seçili değilse, "Tüm Üyelere Açık" durumuna bak
            if (isMembersOnly) {
                roleCheckboxesEvent.forEach(cb => {
                    cb.checked = false; // "Tüm üyelere" seçiliyse spesifik rolleri temizle
                    cb.disabled = true;
                });
                roleLabelsEvent.forEach(lbl => lbl.classList.add('disabled-label'));
                if (specificRolesGroupEvent) specificRolesGroupEvent.classList.add('disabled-group');
            } else { // Ne public ne de members only seçili ise, spesifik roller aktif
                roleCheckboxesEvent.forEach(cb => {
                    cb.disabled = false;
                });
                roleLabelsEvent.forEach(lbl => lbl.classList.remove('disabled-label'));
                if (specificRolesGroupEvent) specificRolesGroupEvent.classList.remove('disabled-group');
            }
        }
    }

    if (publicCheckboxEvent) {
        publicCheckboxEvent.addEventListener('change', updateEventVisibilityOptions);
    }
    if (membersOnlyCheckboxEvent) {
        membersOnlyCheckboxEvent.addEventListener('change', updateEventVisibilityOptions);
    }

    // Sayfa yüklendiğinde de durumu ayarla
    updateEventVisibilityOptions();
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
