<?php
// public/create_event.php

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

require_approved_user();
require_permission($pdo, 'event.create');

$page_title = "Yeni Etkinlik Oluştur";

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

// Atanabilir rolleri çek
$assignable_roles_event = [];
if (isset($pdo)) {
    try {
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
/* Modern Create Event Page Styles */
.create-event-page-container {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: var(--font);
    color: var(--lighter-grey);
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.page-header h2 {
    color: var(--gold);
    font-size: 2.5rem;
    font-family: var(--font);
    margin: 0;
    font-weight: 300;
    letter-spacing: -0.5px;
}

.page-header .subtitle {
    color: var(--light-grey);
    font-size: 1rem;
    margin-top: 0.5rem;
    opacity: 0.8;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--turquase);
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
    padding: 0.5rem 0;
    transition: color 0.2s ease;
}

.back-link:hover {
    color: var(--light-turquase);
}

.create-event-form {
    background-color: var(--charcoal);
    padding: 2rem;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-2);
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--darker-gold-2);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    color: var(--gold);
    font-size: 1.25rem;
    font-weight: 400;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.form-group label .required {
    color: var(--red);
    margin-left: 0.25rem;
}

.form-group label .optional {
    color: var(--light-grey);
    font-weight: 400;
    font-size: 0.85em;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    background-color: rgba(34, 34, 34, 0.3);
    border: 1px solid var(--darker-gold-2);
    border-radius: 6px;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    font-family: var(--font);
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 2px rgba(189, 145, 42, 0.2);
    background-color: var(--darker-charcoal);
}

.form-control::placeholder {
    color: var(--light-grey);
    opacity: 0.7;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
    line-height: 1.5;
}

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23bd912a' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
    padding-right: 2.5rem;
    cursor: pointer;
}

/* File Upload Styling */
.file-upload-group {
    display: grid;
    gap: 1rem;
}

.file-upload-wrapper {
    position: relative;
    display: block;
    cursor: pointer;
    background-color: rgba(34, 34, 34, 0.3);
    border: 2px dashed var(--darker-gold-2);
    border-radius: 6px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.2s ease;
}

.file-upload-wrapper:hover {
    border-color: var(--gold);
    background-color: rgba(189, 145, 42, 0.05);
}

.file-upload-wrapper.has-file {
    border-style: solid;
    border-color: var(--gold);
    background-color: rgba(189, 145, 42, 0.1);
}

.file-upload-input {
    position: absolute;
    left: -9999px;
    opacity: 0;
}

.file-upload-content {
    pointer-events: none;
}

.file-upload-icon {
    font-size: 2rem;
    color: var(--gold);
    margin-bottom: 0.5rem;
}

.file-upload-text {
    color: var(--lighter-grey);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.file-upload-hint {
    color: var(--light-grey);
    font-size: 0.8rem;
}

.file-upload-filename {
    color: var(--gold);
    font-weight: 500;
    font-size: 0.9rem;
    margin-top: 0.5rem;
    display: none;
}

/* Visibility Options */
.visibility-section {
    background-color: rgba(34, 34, 34, 0.3);
    border: 1px solid var(--darker-gold-2);
    border-radius: 6px;
    padding: 1.5rem;
}

.visibility-option {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 6px;
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.visibility-option:hover {
    background-color: rgba(82, 56, 10, 0.1);
}

.visibility-option.selected {
    background-color: rgba(189, 145, 42, 0.1);
    border: 1px solid var(--darker-gold-1);
}

.visibility-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.visibility-checkbox {
    margin-right: 1rem;
    margin-top: 0.25rem;
    accent-color: var(--gold);
    transform: scale(1.2);
}

.visibility-content {
    flex: 1;
}

.visibility-title {
    color: var(--lighter-grey);
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.visibility-description {
    color: var(--light-grey);
    font-size: 0.85rem;
    line-height: 1.4;
}

.role-selection {
    margin-top: 1rem;
    padding: 1rem;
    background-color: rgba(82, 56, 10, 0.1);
    border-radius: 6px;
    border-left: 3px solid var(--gold);
    display: none;
}

.role-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.role-option {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background-color: rgba(34, 34, 34, 0.3);
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.role-option:hover {
    background-color: rgba(82, 56, 10, 0.2);
}

.role-checkbox {
    margin-right: 0.75rem;
    accent-color: var(--gold);
}

.role-label {
    color: var(--lighter-grey);
    font-size: 0.85rem;
    cursor: pointer;
}

/* Form Help Text */
.form-help {
    background-color: rgba(42, 189, 168, 0.1);
    border: 1px solid rgba(42, 189, 168, 0.3);
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
}

.form-help-title {
    color: var(--turquase);
    font-weight: 500;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-help-text {
    color: var(--lighter-grey);
    font-size: 0.85rem;
    line-height: 1.4;
}

/* Submit Section */
.submit-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 2rem;
    border-top: 1px solid var(--darker-gold-2);
    margin-top: 2rem;
}

.submit-info {
    color: var(--light-grey);
    font-size: 0.85rem;
}

.btn-submit {
    background-color: var(--gold);
    color: var(--charcoal);
    padding: 1rem 2rem;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-submit:hover {
    background-color: var(--light-gold);
    transform: translateY(-1px);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Character Counter */
.character-counter {
    font-size: 0.8rem;
    color: var(--light-grey);
    text-align: right;
    margin-top: 0.25rem;
}

.character-counter.error {
    color: var(--red);
}

/* Responsive Design */
@media (max-width: 768px) {
    .create-event-page-container {
        padding: 1.5rem 1rem;
    }
    
    .page-header h2 {
        font-size: 2rem;
    }
    
    .create-event-form {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .role-grid {
        grid-template-columns: 1fr;
    }
    
    .submit-section {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .btn-submit {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .create-event-page-container {
        padding: 1rem 0.75rem;
    }
    
    .create-event-form {
        padding: 1rem;
    }
}
</style>

<main class="main-content">
    <div class="create-event-page-container">
        <a href="events.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Etkinliklere Geri Dön
        </a>
        
        <div class="page-header">
            <h2><?php echo htmlspecialchars($page_title); ?></h2>
            <p class="subtitle">Topluluğunuz için yeni bir etkinlik oluşturun</p>
        </div>

        <form action="/src/actions/handle_create_event.php" method="POST" enctype="multipart/form-data" class="create-event-form" id="createEventForm">
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Temel Bilgiler
                </h3>
                
                <div class="form-group">
                    <label for="title">
                        Etkinlik Başlığı <span class="required">*</span>
                    </label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($form_input['title'] ?? ''); ?>" 
                           placeholder="Etkinliğiniz için çekici bir başlık yazın"
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="event_datetime">
                            Etkinlik Tarihi ve Saati <span class="required">*</span>
                        </label>
                        <input type="datetime-local" id="event_datetime" name="event_datetime" class="form-control" 
                               value="<?php echo htmlspecialchars($form_input['event_datetime'] ?? ''); ?>" 
                               required style="color-scheme: dark;">
                    </div>
                    
                    <div class="form-group">
                        <label for="event_type">
                            Etkinlik Tipi <span class="required">*</span>
                        </label>
                        <select id="event_type" name="event_type" class="form-control" required>
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
                </div>

                <div class="form-group">
                    <label for="description">
                        Etkinlik Açıklaması <span class="required">*</span>
                    </label>
                    <textarea id="description" name="description" class="form-control" rows="6" 
                              placeholder="Etkinliğinizin detaylarını, amaçlarını ve beklentilerini açıklayın..."
                              required><?php echo htmlspecialchars($form_input['description'] ?? ''); ?></textarea>
                    <div class="character-counter" id="descriptionCounter">0 karakter</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="location">
                            Etkinlik Konumu <span class="optional">(Opsiyonel)</span>
                        </label>
                        <input type="text" id="location" name="location" class="form-control" 
                               value="<?php echo htmlspecialchars($form_input['location'] ?? ''); ?>" 
                               placeholder="Örn: Stanton > Hurston > Lorville">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_participants">
                            Maksimum Katılımcı <span class="optional">(Opsiyonel)</span>
                        </label>
                        <input type="number" id="max_participants" name="max_participants" class="form-control" 
                               value="<?php echo htmlspecialchars($form_input['max_participants'] ?? ''); ?>" 
                               min="1" placeholder="Sınırsız">
                    </div>
                </div>
            </div>

            <!-- Equipment Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-user-shield"></i>
                    Teçhizat ve Donanım
                </h3>
                
                <div class="form-group">
                    <label for="suggested_loadout_id">
                        Önerilen Teçhizat Seti <span class="optional">(Opsiyonel)</span>
                    </label>
                    <select id="suggested_loadout_id" name="suggested_loadout_id" class="form-control">
                        <option value="">-- Teçhizat Seti Seç --</option>
                        <?php if (!empty($loadout_sets)): ?>
                            <?php foreach ($loadout_sets as $loadout): ?>
                                <option value="<?php echo htmlspecialchars($loadout['id']); ?>" 
                                        <?php echo (($form_input['suggested_loadout_id'] ?? null) == $loadout['id'] ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($loadout['set_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Kullanılabilir teçhizat seti bulunmuyor.</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Visibility Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-eye"></i>
                    Görünürlük Ayarları
                </h3>
                
                <div class="visibility-section">
                    <div class="visibility-option" id="publicOption">
                        <input type="checkbox" name="is_public_no_auth" id="is_public_no_auth" value="1" 
                               class="visibility-checkbox" <?php echo !empty($form_input['is_public_no_auth']) ? 'checked' : ''; ?>>
                        <div class="visibility-content">
                            <div class="visibility-title">Herkese Açık</div>
                            <div class="visibility-description">
                                Giriş yapmamış kullanıcılar dahil herkes bu etkinliği görebilir ve katılabilir.
                            </div>
                        </div>
                    </div>

                    <div class="visibility-option" id="membersOption">
                        <input type="checkbox" name="is_members_only" id="is_members_only" value="1" 
                               class="visibility-checkbox" 
                               <?php echo (isset($form_input['is_members_only']) && $form_input['is_members_only'] == '1') || (!isset($form_input['is_public_no_auth']) && !isset($form_input['assigned_role_ids']) && !isset($form_input['is_members_only'])) ? 'checked' : ''; ?>>
                        <div class="visibility-content">
                            <div class="visibility-title">Tüm Onaylı Üyelere Açık</div>
                            <div class="visibility-description">
                                Sadece onaylanmış topluluk üyeleri bu etkinliği görebilir ve katılabilir.
                            </div>
                        </div>
                    </div>

                    <div class="visibility-option" id="rolesOption">
                        <input type="checkbox" id="specific_roles_toggle" class="visibility-checkbox">
                        <div class="visibility-content">
                            <div class="visibility-title">Belirli Rollere Özel</div>
                            <div class="visibility-description">
                                Sadece seçtiğiniz rollere sahip üyeler bu etkinliği görebilir ve katılabilir.
                            </div>
                            
                            <div class="role-selection" id="roleSelection">
                                <div class="visibility-description" style="margin-bottom: 0.75rem;">
                                    Bu etkinliği görebilecek rolleri seçin:
                                </div>
                                <div class="role-grid">
                                    <?php if (!empty($assignable_roles_event)): ?>
                                        <?php foreach ($assignable_roles_event as $role):
                                            $role_display_name = htmlspecialchars($role['description'] ?: ucfirst(str_replace('_', ' ', $role['name'])));
                                            $is_role_checked = isset($form_input['assigned_role_ids']) && is_array($form_input['assigned_role_ids']) && in_array($role['id'], $form_input['assigned_role_ids']);
                                        ?>
                                            <div class="role-option">
                                                <input type="checkbox" name="assigned_role_ids[]" 
                                                       id="role_<?php echo $role['id']; ?>"
                                                       value="<?php echo $role['id']; ?>" 
                                                       class="role-checkbox"
                                                       <?php echo $is_role_checked ? 'checked' : ''; ?>>
                                                <label for="role_<?php echo $role['id']; ?>" class="role-label">
                                                    <?php echo $role_display_name; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="visibility-description">Atanabilir özel rol bulunmuyor.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-help">
                    <div class="form-help-title">
                        <i class="fas fa-info-circle"></i>
                        Görünürlük Rehberi
                    </div>
                    <div class="form-help-text">
                        <strong>Herkese Açık:</strong> En geniş katılım için ideal. Herkes görebilir.<br>
                        <strong>Onaylı Üyelere:</strong> Topluluk içi etkinlikler için uygundur.<br>
                        <strong>Belirli Roller:</strong> Özel operasyonlar veya rol-bazlı etkinlikler için kullanın.
                    </div>
                </div>
            </div>

            <!-- Media Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-images"></i>
                    Etkinlik Görselleri
                </h3>
                
                <div class="file-upload-group">
                    <div class="form-group">
                        <label class="file-upload-wrapper" for="image_1" id="upload1">
                            <input type="file" id="image_1" name="event_images[]" class="file-upload-input" 
                                   accept="image/jpeg,image/png,image/gif">
                            <div class="file-upload-content">
                                <div class="file-upload-icon">
                                    <i class="fas fa-image"></i>
                                </div>
                                <div class="file-upload-text">Ana Kapak Fotoğrafı</div>
                                <div class="file-upload-hint">Etkinlik kartında görünecek ana görsel</div>
                                <div class="file-upload-filename"></div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="file-upload-wrapper" for="image_2" id="upload2">
                            <input type="file" id="image_2" name="event_images[]" class="file-upload-input" 
                                   accept="image/jpeg,image/png,image/gif">
                            <div class="file-upload-content">
                                <div class="file-upload-icon">
                                    <i class="fas fa-images"></i>
                                </div>
                                <div class="file-upload-text">Ek Fotoğraf 2</div>
                                <div class="file-upload-hint">İsteğe bağlı ek görsel</div>
                                <div class="file-upload-filename"></div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="file-upload-wrapper" for="image_3" id="upload3">
                            <input type="file" id="image_3" name="event_images[]" class="file-upload-input" 
                                   accept="image/jpeg,image/png,image/gif">
                            <div class="file-upload-content">
                                <div class="file-upload-icon">
                                    <i class="fas fa-images"></i>
                                </div>
                                <div class="file-upload-text">Ek Fotoğraf 3</div>
                                <div class="file-upload-hint">İsteğe bağlı ek görsel</div>
                                <div class="file-upload-filename"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-help">
                    <div class="form-help-title">
                        <i class="fas fa-upload"></i>
                        Dosya Gereksinimleri
                    </div>
                    <div class="form-help-text">
                        <strong>Desteklenen formatlar:</strong> JPG, PNG, GIF<br>
                        <strong>Maksimum boyut:</strong> 20MB (her dosya için)<br>
                        <strong>Önerilen boyut:</strong> 1920x1080 piksel (16:9 oran)
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="submit-section">
                <div class="submit-info">
                    <i class="fas fa-info-circle"></i>
                    Etkinlik oluşturulduktan sonra düzenleyebilirsiniz
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-plus-circle"></i>
                    Etkinliği Oluştur
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form elements
    const form = document.getElementById('createEventForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Visibility options
    const publicCheckbox = document.getElementById('is_public_no_auth');
    const membersCheckbox = document.getElementById('is_members_only');
    const rolesToggle = document.getElementById('specific_roles_toggle');
    const roleSelection = document.getElementById('roleSelection');
    const roleCheckboxes = document.querySelectorAll('input[name="assigned_role_ids[]"]');
    
    // Visibility option containers
    const publicOption = document.getElementById('publicOption');
    const membersOption = document.getElementById('membersOption');
    const rolesOption = document.getElementById('rolesOption');

    // Character counter for description
    const description = document.getElementById('description');
    const descriptionCounter = document.getElementById('descriptionCounter');
    
    function updateDescriptionCounter() {
        const length = description.value.length;
        descriptionCounter.textContent = `${length} karakter`;
        descriptionCounter.classList.toggle('error', length < 10);
    }
    
    description.addEventListener('input', updateDescriptionCounter);
    updateDescriptionCounter();

    // File upload handling
    function initFileUploads() {
        const fileInputs = document.querySelectorAll('.file-upload-input');
        
        fileInputs.forEach((input) => {
            const wrapper = input.closest('.file-upload-wrapper');
            const filename = wrapper.querySelector('.file-upload-filename');
            
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    wrapper.classList.add('has-file');
                    filename.textContent = file.name;
                    filename.style.display = 'block';
                    
                    // Validate file size (20MB)
                    if (file.size > 20 * 1024 * 1024) {
                        alert('Dosya boyutu 20MB\'dan büyük olamaz.');
                        this.value = '';
                        wrapper.classList.remove('has-file');
                        filename.style.display = 'none';
                        return;
                    }
                    
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Sadece JPG, PNG ve GIF formatları desteklenir.');
                        this.value = '';
                        wrapper.classList.remove('has-file');
                        filename.style.display = 'none';
                        return;
                    }
                } else {
                    wrapper.classList.remove('has-file');
                    filename.style.display = 'none';
                }
            });
        });
    }

    // Visibility logic
    function updateVisibilityOptions() {
        const isPublic = publicCheckbox.checked;
        const isMembers = membersCheckbox.checked;
        const hasSelectedRoles = Array.from(roleCheckboxes).some(cb => cb.checked);
        
        // Update visual states
        publicOption.classList.toggle('selected', isPublic);
        membersOption.classList.toggle('selected', isMembers);
        rolesOption.classList.toggle('selected', hasSelectedRoles);
        
        if (isPublic) {
            // Public selected - disable others
            membersCheckbox.checked = false;
            rolesToggle.checked = false;
            roleCheckboxes.forEach(cb => cb.checked = false);
            
            membersOption.classList.add('disabled');
            rolesOption.classList.add('disabled');
            roleSelection.style.display = 'none';
            
        } else if (isMembers) {
            // Members only selected - disable roles
            rolesToggle.checked = false;
            roleCheckboxes.forEach(cb => cb.checked = false);
            
            membersOption.classList.remove('disabled');
            rolesOption.classList.add('disabled');
            roleSelection.style.display = 'none';
            
        } else if (hasSelectedRoles) {
            // Roles selected - disable members only
            membersCheckbox.checked = false;
            rolesToggle.checked = true;
            
            membersOption.classList.add('disabled');
            rolesOption.classList.remove('disabled');
            roleSelection.style.display = 'block';
            
        } else {
            // Nothing selected - enable all options
            membersOption.classList.remove('disabled');
            rolesOption.classList.remove('disabled');
            roleSelection.style.display = rolesToggle.checked ? 'block' : 'none';
        }
    }

    function initVisibilityHandlers() {
        publicCheckbox.addEventListener('change', updateVisibilityOptions);
        membersCheckbox.addEventListener('change', updateVisibilityOptions);
        
        rolesToggle.addEventListener('change', function() {
            if (this.checked) {
                roleSelection.style.display = 'block';
                // Auto-select first role if none selected
                const hasSelected = Array.from(roleCheckboxes).some(cb => cb.checked);
                if (!hasSelected && roleCheckboxes.length > 0) {
                    roleCheckboxes[0].checked = true;
                }
            } else {
                roleSelection.style.display = 'none';
                roleCheckboxes.forEach(cb => cb.checked = false);
            }
            updateVisibilityOptions();
        });
        
        roleCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const hasSelected = Array.from(roleCheckboxes).some(cb => cb.checked);
                rolesToggle.checked = hasSelected;
                if (hasSelected) {
                    roleSelection.style.display = 'block';
                }
                updateVisibilityOptions();
            });
        });
        
        // Initial state
        updateVisibilityOptions();
    }

    // Form validation
    function validateForm() {
        const title = document.getElementById('title').value.trim();
        const datetime = document.getElementById('event_datetime').value;
        const description = document.getElementById('description').value.trim();
        
        if (!title) {
            alert('Etkinlik başlığı gereklidir.');
            document.getElementById('title').focus();
            return false;
        }
        
        if (!datetime) {
            alert('Etkinlik tarihi ve saati gereklidir.');
            document.getElementById('event_datetime').focus();
            return false;
        }
        
        // Check if date is in the future
        const eventDate = new Date(datetime);
        const now = new Date();
        if (eventDate <= now) {
            alert('Etkinlik tarihi gelecekte olmalıdır.');
            document.getElementById('event_datetime').focus();
            return false;
        }
        
        if (!description) {
            alert('Etkinlik açıklaması gereklidir.');
            document.getElementById('description').focus();
            return false;
        }
        
        if (description.length < 10) {
            alert('Etkinlik açıklaması en az 10 karakter olmalıdır.');
            document.getElementById('description').focus();
            return false;
        }
        
        // Check visibility settings
        const isPublic = publicCheckbox.checked;
        const isMembers = membersCheckbox.checked;
        const hasSelectedRoles = Array.from(roleCheckboxes).some(cb => cb.checked);
        
        if (!isPublic && !isMembers && !hasSelectedRoles) {
            alert('Lütfen bir görünürlük seçeneği seçin.');
            return false;
        }
        
        return true;
    }

    // Form submission
    function initFormSubmission() {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            // Show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Oluşturuluyor...';
            
            // Submit form after a short delay for visual feedback
            setTimeout(() => {
                this.submit();
            }, 500);
        });
    }

    // Enhanced datetime input
    function initDateTimeEnhancements() {
        const datetimeInput = document.getElementById('event_datetime');
        
        // Set minimum date to current time
        const now = new Date();
        const minDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        datetimeInput.min = minDateTime;
        
        // Suggest default time (next hour) if empty
        if (!datetimeInput.value) {
            const nextHour = new Date(now);
            nextHour.setHours(now.getHours() + 1, 0, 0, 0);
            const defaultDateTime = new Date(nextHour.getTime() - nextHour.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            datetimeInput.value = defaultDateTime;
        }
    }

    // Initialize all features
    initFileUploads();
    initVisibilityHandlers();
    initFormSubmission();
    initDateTimeEnhancements();
    
    // Enhanced accessibility and UX
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', function() {
            this.style.transform = 'scale(1.005)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        input.addEventListener('blur', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // Auto-save to sessionStorage (prevents data loss on accidental refresh)
    const formElements = form.querySelectorAll('input:not([type="file"]), textarea, select');
    
    // Load saved data
    formElements.forEach(element => {
        const savedValue = sessionStorage.getItem(`create_event_${element.name}`);
        if (savedValue && !element.value) {
            if (element.type === 'checkbox') {
                element.checked = savedValue === 'true';
            } else {
                element.value = savedValue;
            }
        }
    });
    
    // Save on change
    formElements.forEach(element => {
        element.addEventListener('change', function() {
            const value = this.type === 'checkbox' ? this.checked : this.value;
            sessionStorage.setItem(`create_event_${this.name}`, value);
        });
    });
    
    // Clear on successful submit
    form.addEventListener('submit', function() {
        setTimeout(() => {
            formElements.forEach(element => {
                sessionStorage.removeItem(`create_event_${element.name}`);
            });
        }, 1000);
    });

    // Initial updates
    updateVisibilityOptions();
    updateDescriptionCounter();
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>