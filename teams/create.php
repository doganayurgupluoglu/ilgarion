<?php
// /teams/create.php
session_start();

// Güvenlik ve gerekli dosyaları dahil et
require_once dirname(__DIR__) . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/team_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Login kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Veritabanı bağlantısı - projenizde $pdo değişkeni kullanılıyor
if (!isset($pdo)) {
    die('Veritabanı bağlantı hatası');
}

// Edit modu kontrolü
$edit_mode = false;
$team_id = null;
$team_data = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $team_id = (int)$_GET['id'];
    $edit_mode = true;
    
    // Takım verilerini getir
    $team_data = get_team_by_id($pdo, $team_id);
    if (!$team_data) {
        header('Location: /teams/?error=team_not_found');
        exit;
    }
    
    // Düzenleme yetkisi kontrolü
    if (!can_user_edit_team($pdo, $team_id, $_SESSION['user_id'])) {
        header('Location: /teams/detail.php?id=' . $team_id . '&error=no_permission');
        exit;
    }
}

// Takım oluşturma yetkisi kontrolü (sadece create modunda)
if (!$edit_mode && !has_permission($pdo, 'teams.create', $_SESSION['user_id'])) {
    header('Location: /teams/?error=no_create_permission');
    exit;
}

// Form submit kontrolü
$success_message = '';
$error_message = '';
$validation_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF token kontrolü
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Güvenlik hatası. Sayfayı yeniden yükleyip tekrar deneyin.');
        }
        
        // Form verilerini al ve validate et
        $form_data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'tag' => trim($_POST['tag'] ?? ''),
            'color' => trim($_POST['color'] ?? '#007bff'),
            'max_members' => (int)($_POST['max_members'] ?? 50),
            'is_recruitment_open' => isset($_POST['is_recruitment_open']) ? 1 : 0
        ];
        
        // Validasyon
        $validation_errors = validate_team_form($form_data, $edit_mode ? $team_id : null);
        
        if (empty($validation_errors)) {
            if ($edit_mode) {
                // Takım güncelleme
                $result = update_team($pdo, $team_id, $form_data, $_SESSION['user_id']);
                if ($result) {
                    $success_message = 'Takım başarıyla güncellendi!';
                    // Güncel verileri al
                    $team_data = get_team_by_id($pdo, $team_id);
                } else {
                    $error_message = 'Takım güncellenirken bir hata oluştu.';
                }
            } else {
                // Yeni takım oluşturma
                $result = create_team($pdo, $form_data, $_SESSION['user_id']);
                if ($result) {
                    $success_message = 'Takım başarıyla oluşturuldu!';
                    // Takım detay sayfasına yönlendir
                    header('Location: /teams/detail.php?id=' . $result . '&success=created');
                    exit;
                } else {
                    $error_message = 'Takım oluşturulurken bir hata oluştu.';
                }
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Team create/edit error: " . $e->getMessage());
    }
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mevcut form verilerini hazırla
$form_values = [
    'name' => $team_data['name'] ?? $_POST['name'] ?? '',
    'description' => $team_data['description'] ?? $_POST['description'] ?? '',
    'tag' => $team_data['tag'] ?? $_POST['tag'] ?? '',
    'color' => $team_data['color'] ?? $_POST['color'] ?? '#007bff',
    'max_members' => $team_data['max_members'] ?? $_POST['max_members'] ?? 50,
    'is_recruitment_open' => $team_data['is_recruitment_open'] ?? (isset($_POST['is_recruitment_open']) ? 1 : 1)
];

// Sayfa başlığı
$page_title = $edit_mode ? 'Takım Düzenle' : 'Yeni Takım Oluştur';

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

?>

<!-- Teams CSS -->
<link rel="stylesheet" href="/teams/css/create.css">

<div class="teams-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fas fa-<?= $edit_mode ? 'edit' : 'plus-circle' ?>"></i>
                    <?= htmlspecialchars($page_title) ?>
                </h1>
                <p><?= $edit_mode ? 'Takım bilgilerini güncelleyin' : 'Yeni bir takım oluşturun ve üyelerinizi yönetin' ?></p>
            </div>
            <div class="header-actions">
                <?php if ($edit_mode): ?>
                    <a href="/teams/detail.php?id=<?= $team_id ?>" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
                <?php else: ?>
                    <a href="/teams/" class="btn-secondary">
                        <i class="fas fa-list"></i> Takım Listesi
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Layout -->
    <div class="create-team-container">
        <!-- Sidebar -->
        <div class="view-sidebar">
            <div class="view-card">
                <div class="view-card-header">
                    <h6><i class="fas fa-lightbulb"></i> İpuçları</h6>
                </div>
                <div class="view-card-content">
                    <div class="tip-item">
                        <strong><i class="fas fa-tag"></i> Takım Adı:</strong>
                        <p>Yaratıcı ve akılda kalıcı bir takım adı seçin. @, !, ., - gibi özel karakterler kullanabilirsiniz. Örnek: "Phoenix@Rising", "Nova-Squadron!"</p>
                    </div>

                    <div class="tip-item">
                        <strong><i class="fas fa-align-left"></i> Açıklama:</strong>
                        <p>Takımınızın amacını, oyun tarzınızı (PvP, Mining, Exploration vb.) ve beklentilerinizi belirtin.</p>
                    </div>

                    <div class="tip-item">
                        <strong><i class="fas fa-palette"></i> Renk:</strong>
                        <p>Takımınızın kimliğini yansıtan bir renk seçin. Bu renk takım kartında ve profilde görünecek.</p>
                    </div>

                    <div class="tip-item">
                        <strong><i class="fas fa-users"></i> Üye Sayısı:</strong>
                        <p>Başlangıçta daha düşük bir limit koyup, ihtiyaç halinde artırabilirsiniz.</p>
                    </div>
                </div>
            </div>

            <?php if ($edit_mode): ?>
            <!-- Quick Actions -->
            <div class="view-card">
                <div class="view-card-header">
                    <h6><i class="fas fa-bolt"></i> Hızlı İşlemler</h6>
                </div>
                <div class="view-card-content">
                    <div class="quick-actions">
                        <a href="/teams/members.php?id=<?= $team_id ?>" class="btn-outline-primary">
                            <i class="fas fa-users"></i> Üyeleri Yönet
                        </a>
                        <a href="/teams/applications.php?id=<?= $team_id ?>" class="btn-outline-info">
                            <i class="fas fa-clipboard-list"></i> Başvuruları Görüntüle
                        </a>
                        <a href="/teams/settings.php?id=<?= $team_id ?>" class="btn-outline-secondary">
                            <i class="fas fa-cog"></i> Gelişmiş Ayarlar
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Main Content -->
        <div class="view-main-content">
            <div class="view-card">
                <div class="view-card-header">
                    <h5><i class="fas fa-info-circle"></i> Takım Bilgileri</h5>
                </div>
                <div class="view-card-content">
                    <form method="POST" id="teamForm" class="team-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <!-- Team Name -->
                        <div class="form-group">
                            <label for="name">
                                Takım Adı <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?= isset($validation_errors['name']) ? 'error' : '' ?>" 
                                   id="name" 
                                   name="name" 
                                   value="<?= htmlspecialchars($form_values['name']) ?>"
                                   maxlength="100" 
                                   required>
                            <?php if (isset($validation_errors['name'])): ?>
                                <div class="form-error">
                                    <?= htmlspecialchars($validation_errors['name']) ?>
                                </div>
                            <?php endif; ?>
                            <small class="form-help">
                                Takımınızın benzersiz adı. Özel karakterler (@, !, ., - vb.) kullanabilirsiniz. Maksimum 100 karakter.
                            </small>
                        </div>

                        <!-- Team Description -->
                        <div class="form-group">
                            <label for="description">Takım Açıklaması</label>
                            <textarea class="form-control <?= isset($validation_errors['description']) ? 'error' : '' ?>" 
                                      id="description" 
                                      name="description" 
                                      rows="4" 
                                      maxlength="1000"><?= htmlspecialchars($form_values['description']) ?></textarea>
                            <?php if (isset($validation_errors['description'])): ?>
                                <div class="form-error">
                                    <?= htmlspecialchars($validation_errors['description']) ?>
                                </div>
                            <?php endif; ?>
                            <small class="form-help">
                                Takımınızın amacını ve hedeflerini açıklayın. Maksimum 1000 karakter.
                            </small>
                        </div>

                        <!-- Team Tag and Color Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <!-- Team Tag -->
                                <label for="tag">Takım Kısaltması (Tag)</label>
                                <input type="text" 
                                       class="form-control <?= isset($validation_errors['tag']) ? 'error' : '' ?>" 
                                       id="tag" 
                                       name="tag"
                                       value="<?= htmlspecialchars($form_values['tag']) ?>"
                                       placeholder="Örn: IT@, SC#, [NOVA]..."
                                       maxlength="8"
                                       style="text-transform: uppercase;">
                                <?php if (isset($validation_errors['tag'])): ?>
                                    <div class="form-error">
                                        <?= htmlspecialchars($validation_errors['tag']) ?>
                                    </div>
                                <?php endif; ?>
                                <small class="form-help">
                                    Takımınızın kısa adı/kısaltması. Özel karakterler (@, #, [, ] vb.) kullanabilirsiniz. Maksimum 8 karakter.
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <!-- Team Color -->
                                <label for="color">Takım Rengi</label>
                                <div class="color-input-group">
                                    <input type="color" 
                                           class="form-control-color <?= isset($validation_errors['color']) ? 'error' : '' ?>" 
                                           id="color" 
                                           name="color" 
                                           value="<?= htmlspecialchars($form_values['color']) ?>">
                                    <input type="text" 
                                           class="form-control" 
                                           id="colorText" 
                                           value="<?= htmlspecialchars($form_values['color']) ?>" 
                                           readonly>
                                    <div class="color-preview" style="background-color: <?= htmlspecialchars($form_values['color']) ?>"></div>
                                </div>
                                <?php if (isset($validation_errors['color'])): ?>
                                    <div class="form-error">
                                        <?= htmlspecialchars($validation_errors['color']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Settings Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <!-- Max Members -->
                                <label for="max_members">Maksimum Üye Sayısı</label>
                                <input type="number" 
                                       class="form-control <?= isset($validation_errors['max_members']) ? 'error' : '' ?>" 
                                       id="max_members" 
                                       name="max_members" 
                                       value="<?= htmlspecialchars($form_values['max_members']) ?>"
                                       min="5" 
                                       max="500">
                                <?php if (isset($validation_errors['max_members'])): ?>
                                    <div class="form-error">
                                        <?= htmlspecialchars($validation_errors['max_members']) ?>
                                    </div>
                                <?php endif; ?>
                                <small class="form-help">
                                    5-500 arasında bir sayı giriniz.
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <!-- Recruitment Status -->
                                <label>Üye Alımı</label>
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" 
                                           id="is_recruitment_open" 
                                           name="is_recruitment_open"
                                           <?= $form_values['is_recruitment_open'] ? 'checked' : '' ?>>
                                    <label for="is_recruitment_open" class="checkbox-label">
                                        Yeni üye başvurularını kabul et
                                    </label>
                                </div>
                                <small class="form-help">
                                    Bu seçenek kapalıysa, takımınıza kimse başvuru yapamaz.
                                </small>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <div class="form-note">
                                <i class="fas fa-info-circle"></i>
                                Takım oluşturduktan sonra logo ve banner yükleyebilirsiniz.
                            </div>
                            <div class="action-buttons">
                                <a href="<?= $edit_mode ? '/teams/detail.php?id=' . $team_id : '/teams/' ?>" 
                                   class="btn-secondary">
                                    <i class="fas fa-times"></i> İptal
                                </a>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-<?= $edit_mode ? 'save' : 'plus' ?>"></i>
                                    <?= $edit_mode ? 'Güncelle' : 'Oluştur' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Teams JavaScript -->
<script src="js/teams.js"></script>

<script>
// Color picker synchronization
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('colorText').value = this.value;
});

// Form validation
document.getElementById('teamForm').addEventListener('submit', function(e) {
    let isValid = true;
    const name = document.getElementById('name').value.trim();
    
    if (name.length < 3 || name.length > 100) {
        isValid = false;
        showFieldError('name', 'Takım adı 3-100 karakter arasında olmalıdır.');
    }
    
    if (!isValid) {
        e.preventDefault();
    }
});

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('is-invalid');
    
    let feedback = field.parentNode.querySelector('.invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        field.parentNode.appendChild(feedback);
    }
    feedback.textContent = message;
}
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>