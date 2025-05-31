<?php
// public/new_discussion_topic.php
require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

// Oturum ve rol geçerliliğini kontrol et
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}

// Yetki kontrolü: Onaylı kullanıcı ve 'discussion.topic.create' yetkisi
require_approved_user();
require_permission($pdo, 'discussion.topic.create');

$page_title = "Yeni Tartışma Başlat";
$font_family_style = "font-family: var(--font), serif;"; // Stil için

// Form girdilerini (eğer varsa session'dan) al
$form_input = $_SESSION['form_input'] ?? [];
unset($_SESSION['form_input']); // Aldıktan sonra temizle

// Atanabilir rolleri çek (admin ve member hariç)
$assignable_roles_discussion = [];
if (isset($pdo)) {
    try {
        $stmt_assignable_roles = $pdo->query("SELECT id, name, description FROM roles WHERE name NOT IN ('admin', 'member', 'dis_uye') ORDER BY name ASC");
        $assignable_roles_discussion = $stmt_assignable_roles->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Atanabilir roller çekilirken hata (new_discussion_topic.php): " . $e->getMessage());
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>
<style>
/* new_discussion_topic.php için Stiller (create_event.php'ye benzer) */
.new-topic-container-ux {
    max-width: 850px;
    margin: 40px auto;
    padding: 30px 35px;
    background-color: var(--charcoal);
    border-radius: 10px;
    border: 1px solid var(--darker-gold-1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}
.new-topic-container-ux h2 {
    color: var(--gold); font-size: 2.1rem; text-align: center;
    margin-bottom: 25px; padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-2);
    <?php echo $font_family_style; ?>
}
.new-topic-container-ux .form-group { margin-bottom: 22px; }
.new-topic-container-ux .form-group label {
    display: block; color: var(--lighter-grey); margin-bottom: 8px;
    font-size: 0.95rem; <?php echo $font_family_style; ?> font-weight: 500;
}
.new-topic-container-ux .form-control {
    width: 100%; padding: 11px 15px; background-color: var(--grey);
    border: 1px solid var(--darker-gold-1); border-radius: 6px; color: var(--white);
    font-size: 1rem; <?php echo $font_family_style; ?> line-height: 1.5; box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.new-topic-container-ux .form-control:focus {
    outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px var(--transparent-gold);
}
.new-topic-container-ux textarea.form-control { min-height: 150px; resize: vertical; }
.new-topic-container-ux .form-group-submit {
    margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;
}
.new-topic-container-ux .btn-submit-topic,
.new-topic-container-ux .btn-cancel-topic {
    padding: 10px 25px; border-radius: 5px; font-size: 1rem;
    <?php echo $font_family_style; ?> font-weight: bold; cursor: pointer;
    text-transform: uppercase; text-decoration: none; display: inline-block; text-align:center;
}
.new-topic-container-ux .btn-submit-topic {
    background-color: var(--gold); color: var(--darker-gold-2); border: none;
}
.new-topic-container-ux .btn-submit-topic:hover { background-color: var(--light-gold); }
.new-topic-container-ux .btn-cancel-topic {
    background-color: var(--grey); color: var(--lighter-grey); border: 1px solid var(--darker-gold-1);
}
.new-topic-container-ux .btn-cancel-topic:hover { background-color: var(--darker-gold-1); color: var(--white); }

/* Görünürlük Alanı Stilleri (create_event.php'den uyarlandı) */
.visibility-options-discussion {
    padding: 20px; background-color: var(--darker-gold-2); border-radius: 6px;
    margin-top: 10px; border: 1px solid var(--darker-gold-1);
}
.visibility-options-discussion legend {
    font-size: 1.1rem; color: var(--light-gold); font-weight: 600; margin-bottom: 15px;
    padding-bottom: 10px; border-bottom: 1px solid var(--darker-gold-1); width: 100%;
}
.visibility-checkbox-item-discussion { display: flex; align-items: center; margin-bottom: 12px; }
.visibility-checkbox-item-discussion input[type="checkbox"] {
    width: auto; margin-right: 10px; accent-color: var(--gold); transform: scale(1.15);
}
.visibility-checkbox-item-discussion label {
    font-size: 0.95rem; color: var(--lighter-grey); cursor: pointer;
    font-weight: normal; margin-bottom: 0;
}
.visibility-checkbox-item-discussion label.disabled-label { color: var(--light-grey); cursor: not-allowed; opacity: 0.7; }
.specific-roles-group-discussion { margin-top: 15px; padding-left: 25px; border-left: 2px solid var(--darker-gold-1); }
.specific-roles-group-discussion.disabled-group { opacity: 0.6; pointer-events: none; }
.specific-roles-group-discussion .visibility-checkbox-item-discussion label { font-weight: normal; }
.form-text-helper { font-size: 0.8rem; color: var(--light-grey); margin-top: 6px; line-height: 1.3; }

</style>

<main class="main-content auth-page">
    <div class="new-topic-container-ux">
        <h2><?php echo htmlspecialchars($page_title); ?></h2>

        <form action="/src/actions/handle_new_topic.php" method="POST">
            <div class="form-group">
                <label for="title">Başlık:</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($form_input['title'] ?? ''); ?>" required maxlength="250">
            </div>
            <div class="form-group">
                <label for="content">İlk Mesajınız:</label>
                <textarea id="content" name="content" class="form-control" rows="10" required><?php echo htmlspecialchars($form_input['content'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <fieldset class="visibility-options-discussion">
                    <legend>Konu Görünürlüğü</legend>
                    <div class="visibility-checkbox-item-discussion">
                        <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth_topic" value="1"
                               <?php echo !empty($form_input['is_public_no_auth']) ? 'checked' : ''; ?>>
                        <label for="is_public_no_auth_topic">Herkese Açık (Giriş Yapmamış Kullanıcılar Dahil)</label>
                    </div>
                    <div class="visibility-checkbox-item-discussion">
                        <input type="checkbox" name="is_members_only" id="is_members_only_topic" value="1"
                               <?php echo (isset($form_input['is_members_only']) && $form_input['is_members_only'] == '1') || (!isset($form_input['is_public_no_auth']) && !isset($form_input['assigned_role_ids_topic']) && !isset($form_input['is_members_only'])) ? 'checked' : ''; ?>>
                        <label for="is_members_only_topic">Tüm Onaylı Üyelere Açık</label>
                    </div>

                    <div class="specific-roles-group-discussion" id="specific_roles_group_topic">
                        <p style="font-size:0.9em; color:var(--light-grey); margin-bottom:10px;">Veya Sadece Belirli Rollere Açık:</p>
                        <?php if (!empty($assignable_roles_discussion)): ?>
                            <?php foreach ($assignable_roles_discussion as $role):
                                $role_display_name_topic = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                $is_role_checked_topic = isset($form_input['assigned_role_ids_topic']) && is_array($form_input['assigned_role_ids_topic']) && in_array($role['id'], $form_input['assigned_role_ids_topic']);
                            ?>
                                <div class="visibility-checkbox-item-discussion">
                                    <input type="checkbox" name="assigned_role_ids_topic[]" id="topic_role_<?php echo $role['id']; ?>"
                                           value="<?php echo $role['id']; ?>" <?php echo $is_role_checked_topic ? 'checked' : ''; ?>>
                                    <label for="topic_role_<?php echo $role['id']; ?>"><?php echo $role_display_name_topic; ?></label>
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

            <div class="form-group form-group-submit">
                <a href="discussions.php" class="btn-cancel-topic">İptal</a>
                <button type="submit" class="btn-submit-topic">Konuyu Başlat</button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const publicCheckboxTopic = document.getElementById('is_public_no_auth_topic');
    const membersOnlyCheckboxTopic = document.getElementById('is_members_only_topic');
    const specificRolesGroupTopic = document.getElementById('specific_roles_group_topic');
    const roleCheckboxesTopic = specificRolesGroupTopic ? specificRolesGroupTopic.querySelectorAll('input[type="checkbox"]') : [];
    const roleLabelsTopic = specificRolesGroupTopic ? specificRolesGroupTopic.querySelectorAll('label') : [];

    function updateTopicVisibilityOptions() {
        const isPublic = publicCheckboxTopic.checked;
        const isMembersOnly = membersOnlyCheckboxTopic.checked;

        if (isPublic) {
            membersOnlyCheckboxTopic.checked = false;
            membersOnlyCheckboxTopic.disabled = true;
            roleCheckboxesTopic.forEach(cb => { cb.checked = false; cb.disabled = true; });
            roleLabelsTopic.forEach(lbl => lbl.classList.add('disabled-label'));
            if (specificRolesGroupTopic) specificRolesGroupTopic.classList.add('disabled-group');
        } else {
            membersOnlyCheckboxTopic.disabled = false;
            if (isMembersOnly) {
                roleCheckboxesTopic.forEach(cb => { cb.checked = false; cb.disabled = true; });
                roleLabelsTopic.forEach(lbl => lbl.classList.add('disabled-label'));
                if (specificRolesGroupTopic) specificRolesGroupTopic.classList.add('disabled-group');
            } else {
                roleCheckboxesTopic.forEach(cb => { cb.disabled = false; });
                roleLabelsTopic.forEach(lbl => lbl.classList.remove('disabled-label'));
                if (specificRolesGroupTopic) specificRolesGroupTopic.classList.remove('disabled-group');
            }
        }
    }

    if (publicCheckboxTopic) {
        publicCheckboxTopic.addEventListener('change', updateTopicVisibilityOptions);
    }
    if (membersOnlyCheckboxTopic) {
        membersOnlyCheckboxTopic.addEventListener('change', updateTopicVisibilityOptions);
    }
    updateTopicVisibilityOptions(); // Sayfa yüklendiğinde de durumu ayarla
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
