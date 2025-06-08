<?php
// events/create.php - Etkinlik oluşturma sayfası (TAM DÜZELTİLMİŞ VERSİYON)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/Parsedown.php';

// Events layout system include
require_once 'includes/events_layout.php';

// Session kontrolü
check_user_session_validity();
require_approved_user();

// Etkinlik oluşturma yetkisi kontrolü
if (!has_permission($pdo, 'event.create')) {
    $_SESSION['error_message'] = "Etkinlik oluşturmak için yetkiniz bulunmuyor.";
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$edit_mode = false;
$event_data = null;
$event_id = 0;

// CSRF token oluştur
$csrf_token = generate_csrf_token();

// Düzenleme modu kontrolü
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $event_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, ls.set_name as loadout_name 
            FROM events e 
            LEFT JOIN loadout_sets ls ON e.suggested_loadout_id = ls.id 
            WHERE e.id = :id AND (e.created_by_user_id = :user_id OR :can_edit_all = 1)
        ");
        $stmt->execute([
            ':id' => $event_id,
            ':user_id' => $current_user_id,
            ':can_edit_all' => has_permission($pdo, 'event.edit_all') ? 1 : 0
        ]);
        $event_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event_data) {
            $edit_mode = true;
        } else {
            $_SESSION['error_message'] = "Etkinlik bulunamadı veya düzenleme yetkiniz yok.";
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Event edit error: " . $e->getMessage());
        $_SESSION['error_message'] = "Etkinlik yüklenirken bir hata oluştu.";
        header('Location: index.php');
        exit;
    }
}

// Form gönderimi işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası - CSRF token geçersiz. Lütfen sayfayı yenileyin ve tekrar deneyin.";
        header('Location: ' . $_SERVER['PHP_SELF'] . ($edit_mode ? "?edit=$event_id" : ''));
        exit;
    }
    
    // Form verilerini al ve temizle
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $event_type = trim($_POST['event_type'] ?? 'Genel');
    $event_datetime = $_POST['event_datetime'] ?? '';
    $visibility = $_POST['visibility'] ?? 'members_only';
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $suggested_loadout_id = !empty($_POST['suggested_loadout_id']) ? (int)$_POST['suggested_loadout_id'] : null;
    $requires_role_selection = isset($_POST['requires_role_selection']) ? 1 : 0;
    $visibility_roles = $_POST['visibility_roles'] ?? [];
    $event_roles = $_POST['event_roles'] ?? [];
    
    // YENI: Mevcut rol güncellemeleri ve silme işlemleri
    $existing_roles = $_POST['existing_roles'] ?? [];
    $remove_roles = $_POST['remove_roles'] ?? [];
    
    $errors = [];
    
    // Validasyon
    if (empty($title)) {
        $errors[] = "Etkinlik başlığı gereklidir.";
    } elseif (strlen($title) > 200) {
        $errors[] = "Etkinlik başlığı 200 karakterden uzun olamaz.";
    }
    
    if (empty($description)) {
        $errors[] = "Etkinlik açıklaması gereklidir.";
    }
    
    if (empty($event_datetime)) {
        $errors[] = "Etkinlik tarihi ve saati gereklidir.";
    } else {
        $event_date = DateTime::createFromFormat('Y-m-d\TH:i', $event_datetime);
        if (!$event_date) {
            $errors[] = "Geçersiz tarih formatı.";
        } elseif ($event_date <= new DateTime()) {
            $errors[] = "Etkinlik tarihi gelecekte olmalıdır.";
        }
    }
    
    if ($max_participants !== null && $max_participants < 1) {
        $errors[] = "Maksimum katılımcı sayısı 1'den az olamaz.";
    }
    
    if ($requires_role_selection && empty($event_roles) && empty($existing_roles)) {
        $errors[] = "Rol seçimi zorunlu işaretlendiğinde en az bir rol eklemelisiniz.";
    }
    
    // Rol sınır kontrolleri
    foreach ($event_roles as $role) {
        if (isset($role['slot_count']) && $role['slot_count'] < 1) {
            $errors[] = "Rol katılımcı sayısı 1'den az olamaz.";
        }
    }
    
    // Teçhizat seti kontrolü
    if ($suggested_loadout_id) {
        try {
            $loadout_stmt = $pdo->prepare("
                SELECT id FROM loadout_sets 
                WHERE id = :id AND status = 'published' 
                AND (visibility = 'public' OR (visibility = 'members_only' AND :is_member = 1))
            ");
            $loadout_stmt->execute([
                ':id' => $suggested_loadout_id,
                ':is_member' => is_user_approved() ? 1 : 0
            ]);
            if (!$loadout_stmt->fetch()) {
                $errors[] = "Seçilen teçhizat seti bulunamadı veya erişim yetkiniz yok.";
                $suggested_loadout_id = null;
            }
        } catch (PDOException $e) {
            error_log("Loadout check error: " . $e->getMessage());
            $errors[] = "Teçhizat seti kontrolünde hata oluştu.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($edit_mode) {
                // Etkinlik güncelleme
                $update_stmt = $pdo->prepare("
                    UPDATE events SET 
                    title = :title, description = :description, location = :location,
                    event_type = :event_type, event_datetime = :event_datetime, 
                    visibility = :visibility, max_participants = :max_participants,
                    suggested_loadout_id = :suggested_loadout_id,
                    requires_role_selection = :requires_role_selection,
                    updated_at = NOW()
                    WHERE id = :id
                ");
                
                $update_stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':location' => $location,
                    ':event_type' => $event_type,
                    ':event_datetime' => $event_datetime,
                    ':visibility' => $visibility,
                    ':max_participants' => $max_participants,
                    ':suggested_loadout_id' => $suggested_loadout_id,
                    ':requires_role_selection' => $requires_role_selection,
                    ':id' => $event_id
                ]);
                
                // YENI: Silinecek rolleri işle
                if (!empty($remove_roles)) {
                    foreach ($remove_roles as $slot_id) {
                        try {
                            // Önce katılımcıları kaldır
                            $remove_participants_stmt = $pdo->prepare("
                                DELETE FROM event_role_participants 
                                WHERE event_role_slot_id = :slot_id
                            ");
                            $remove_participants_stmt->execute([':slot_id' => $slot_id]);
                            
                            // event_participants tablosundan da kaldır
                            $remove_event_participants_stmt = $pdo->prepare("
                                DELETE FROM event_participants 
                                WHERE event_id = :event_id 
                                AND event_role_slot_id = :slot_id
                            ");
                            $remove_event_participants_stmt->execute([
                                ':event_id' => $event_id,
                                ':slot_id' => $slot_id
                            ]);
                            
                            // Sonra rol slotunu sil
                            $remove_slot_stmt = $pdo->prepare("
                                DELETE FROM event_role_slots 
                                WHERE id = :slot_id AND event_id = :event_id
                            ");
                            $remove_slot_stmt->execute([
                                ':slot_id' => $slot_id,
                                ':event_id' => $event_id
                            ]);
                            
                        } catch (PDOException $e) {
                            error_log("Role removal error: " . $e->getMessage());
                            $errors[] = "Rol silme işleminde hata oluştu.";
                        }
                    }
                }
                
                // YENI: Mevcut rol güncellemeleri
                foreach ($existing_roles as $slot_id => $role_data) {
                    try {
                        $update_stmt = $pdo->prepare("
                            UPDATE event_role_slots 
                            SET slot_count = :slot_count
                            WHERE id = :slot_id AND event_id = :event_id
                        ");
                        $update_stmt->execute([
                            ':slot_count' => (int)$role_data['slot_count'],
                            ':slot_id' => $slot_id,
                            ':event_id' => $event_id
                        ]);
                        
                    } catch (PDOException $e) {
                        error_log("Role update error: " . $e->getMessage());
                        $errors[] = "Rol güncelleme işleminde hata oluştu.";
                    }
                }
                
            } else {
                // Yeni etkinlik oluşturma
                $insert_stmt = $pdo->prepare("
                    INSERT INTO events (title, description, location, event_type, event_datetime, 
                                      visibility, max_participants, suggested_loadout_id, 
                                      requires_role_selection, created_by_user_id, created_at)
                    VALUES (:title, :description, :location, :event_type, :event_datetime, 
                            :visibility, :max_participants, :suggested_loadout_id, 
                            :requires_role_selection, :user_id, NOW())
                ");
                
                $insert_stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':location' => $location,
                    ':event_type' => $event_type,
                    ':event_datetime' => $event_datetime,
                    ':visibility' => $visibility,
                    ':max_participants' => $max_participants,
                    ':suggested_loadout_id' => $suggested_loadout_id,
                    ':requires_role_selection' => $requires_role_selection,
                    ':user_id' => $current_user_id
                ]);
                
                $event_id = $pdo->lastInsertId();
            }
            
            // Görünürlük rolleri işleme
            if ($visibility === 'role_restricted') {
                // Mevcut rolleri temizle
                $delete_visibility_stmt = $pdo->prepare("DELETE FROM event_visibility_roles WHERE event_id = ?");
                $delete_visibility_stmt->execute([$event_id]);
                
                // Yeni rolleri ekle
                if (!empty($visibility_roles)) {
                    $visibility_insert_stmt = $pdo->prepare("
                        INSERT INTO event_visibility_roles (event_id, role_id) VALUES (?, ?)
                    ");
                    foreach ($visibility_roles as $role_id) {
                        $visibility_insert_stmt->execute([$event_id, $role_id]);
                    }
                }
            }
            
            // Etkinlik rolleri işleme (yeni roller)
            if (!empty($event_roles)) {
                foreach ($event_roles as $role) {
                    if (!empty($role['role_id'])) {
                        $role_insert_stmt = $pdo->prepare("
                            INSERT INTO event_role_slots (event_id, event_role_id, slot_count, display_order)
                            VALUES (:event_id, :role_id, :slot_count, 0)
                        ");
                        $role_insert_stmt->execute([
                            ':event_id' => $event_id,
                            ':role_id' => $role['role_id'],
                            ':slot_count' => $role['slot_count'] ?? 1
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = $edit_mode ? "Etkinlik başarıyla güncellendi!" : "Etkinlik başarıyla oluşturuldu!";
            header("Location: view.php?id=$event_id");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Event save error: " . $e->getMessage());
            $errors[] = "Etkinlik kaydedilirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Teçhizat setlerini çek
try {
    $loadouts_stmt = $pdo->prepare("
        SELECT id, set_name, description 
        FROM loadout_sets 
        WHERE status = 'published' 
        AND (visibility = 'public' OR (visibility = 'members_only' AND :is_member = 1))
        ORDER BY set_name ASC
    ");
    $loadouts_stmt->execute([':is_member' => is_user_approved() ? 1 : 0]);
    $available_loadouts = $loadouts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Loadouts fetch error: " . $e->getMessage());
    $available_loadouts = [];
}

// Etkinlik rollerini çek
try {
    $roles_stmt = $pdo->prepare("
        SELECT id, role_name, icon_class, role_description
        FROM event_roles 
        WHERE is_active = 1 AND (visibility = 'public' OR (visibility = 'members_only' AND :is_member = 1))
        ORDER BY role_name ASC
    ");
    $roles_stmt->execute([':is_member' => is_user_approved() ? 1 : 0]);
    $available_event_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Event roles fetch error: " . $e->getMessage());
    $available_event_roles = [];
}

// GÜNCELLENEN: Mevcut etkinlik rolleri (düzenleme modu için) - DOĞRU TABLO YAPISI
$existing_event_roles = [];
$all_event_roles = []; // Tüm roller için yeni array
if ($edit_mode) {
    try {
        // Önce bu etkinlik için tanımlanmış TÜM rolleri getir - DOĞRU TABLO YAPISINA GÖRE
        $all_roles_stmt = $pdo->prepare("
    SELECT 
        ers.id as slot_id,
        ers.event_role_id,
        ers.slot_count,
        ers.filled_count,
        ers.display_order,
        er.role_name,
        er.icon_class,
        er.role_description,
        COALESCE(ers.filled_count, 0) as participant_count,
        CASE 
            WHEN ers.filled_count > 0 THEN 1 
            ELSE 0 
        END as has_participants
    FROM event_role_slots ers
    JOIN event_roles er ON ers.event_role_id = er.id
    WHERE ers.event_id = :event_id
    ORDER BY ers.display_order ASC, er.role_name ASC
");
        $all_roles_stmt->execute([':event_id' => $event_id]);
        $all_event_roles = $all_roles_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Katılımcısı olan rolleri ayrı olarak da tut (eski sistem uyumluluğu için)
        $existing_event_roles = array_filter($all_event_roles, function($role) {
            return $role['participant_count'] > 0;
        });
        
    } catch (PDOException $e) {
        error_log("All event roles fetch error: " . $e->getMessage());
        $all_event_roles = [];
        $existing_event_roles = [];
    }
}

// Sistem rollerini çek
try {
    $system_roles_stmt = $pdo->prepare("
        SELECT id, role_name, color 
        FROM roles 
        WHERE is_active = 1 
        ORDER BY priority ASC, role_name ASC
    ");
    $system_roles_stmt->execute();
    $available_system_roles = $system_roles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("System roles fetch error: " . $e->getMessage());
    $available_system_roles = [];
}

$page_title = $edit_mode ? "Etkinlik Düzenle" : "Yeni Etkinlik Oluştur";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => $edit_mode ? 'Etkinlik Düzenle' : 'Yeni Etkinlik', 'url' => '', 'icon' => $edit_mode ? 'fas fa-edit' : 'fas fa-plus']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Layout başlat
events_layout_start($breadcrumb_items, $page_title);
?>

<link rel="stylesheet" href="css/events_sidebar.css">
<link rel="stylesheet" href="css/create_event.css">

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="header-info">
            <h1>
                <i class="fas fa-calendar-plus"></i>
                <?= $edit_mode ? 'Etkinlik Düzenle' : 'Yeni Etkinlik Oluştur' ?>
            </h1>
            <p>Topluluk için yeni bir etkinlik planla</p>
        </div>
    </div>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h4><i class="fas fa-exclamation-triangle"></i> Hata!</h4>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Event Form -->
<div class="create-event-container">
    <form id="eventForm" method="POST" enctype="multipart/form-data">
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Temel Bilgiler -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Temel Bilgiler</h3>
            
            <div class="form-group">
                <label for="title">Etkinlik Başlığı *</label>
                <input type="text" id="title" name="title" required maxlength="200"
                       value="<?= $edit_mode ? htmlspecialchars($event_data['title']) : '' ?>"
                       placeholder="Etkinlik başlığını girin">
            </div>
            
            <div class="form-group">
                <label for="description">Açıklama *</label>
                <div class="markdown-editor">
                    <div class="editor-toolbar">
                        <button type="button" class="toolbar-btn" data-action="bold" title="Kalın">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="italic" title="İtalik">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="link" title="Link">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="list" title="Liste">
                            <i class="fas fa-list"></i>
                        </button>
                        <span class="toolbar-separator">|</span>
                        <button type="button" class="toolbar-btn" data-action="preview" title="Önizleme">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <textarea id="description" name="description" required rows="8"
                              placeholder="Etkinlik açıklamasını girin (Markdown desteklenir)"><?= $edit_mode ? htmlspecialchars($event_data['description']) : '' ?></textarea>
                    <div id="preview-area" style="display: none;"></div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="location">Konum</label>
                    <input type="text" id="location" name="location" 
                           value="<?= $edit_mode ? htmlspecialchars($event_data['location']) : '' ?>"
                           placeholder="Etkinlik konumu">
                </div>
                
                <div class="form-group">
                    <label for="event_type">Etkinlik Türü</label>
                    <select id="event_type" name="event_type">
                        <option value="Genel" <?= ($edit_mode && $event_data['event_type'] === 'Genel') ? 'selected' : '' ?>>Genel</option>
                        <option value="Operasyon" <?= ($edit_mode && $event_data['event_type'] === 'Operasyon') ? 'selected' : '' ?>>Operasyon</option>
                        <option value="Antrenman" <?= ($edit_mode && $event_data['event_type'] === 'Antrenman') ? 'selected' : '' ?>>Antrenman</option>
                        <option value="Sosyal" <?= ($edit_mode && $event_data['event_type'] === 'Sosyal') ? 'selected' : '' ?>>Sosyal</option>
                        <option value="Turnuva" <?= ($edit_mode && $event_data['event_type'] === 'Turnuva') ? 'selected' : '' ?>>Turnuva</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="event_datetime">Etkinlik Tarihi ve Saati *</label>
                <input type="datetime-local" id="event_datetime" name="event_datetime" required
                       value="<?= $edit_mode ? date('Y-m-d\TH:i', strtotime($event_data['event_datetime'])) : '' ?>">
            </div>
        </div>

        <!-- Katılım Ayarları -->
        <div class="form-section">
            <h3><i class="fas fa-users"></i> Katılım Ayarları</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="visibility">Görünürlük</label>
                    <select id="visibility" name="visibility">
                        <option value="public" <?= ($edit_mode && $event_data['visibility'] === 'public') ? 'selected' : '' ?>>
                            Herkese Açık
                        </option>
                        <option value="members_only" <?= ($edit_mode && $event_data['visibility'] === 'members_only') || !$edit_mode ? 'selected' : '' ?>>
                            Sadece Üyeler
                        </option>
                        <option value="role_restricted" <?= ($edit_mode && $event_data['visibility'] === 'role_restricted') ? 'selected' : '' ?>>
                            Belirli Roller
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="max_participants">Maksimum Katılımcı</label>
                    <input type="number" id="max_participants" name="max_participants" min="1" 
                           value="<?= $edit_mode ? $event_data['max_participants'] : '' ?>"
                           placeholder="Sınırsız için boş bırakın">
                    <small>Boş bırakılırsa sınırsız katılımcı</small>
                </div>
            </div>
            
            <!-- Rol Kısıtlaması -->
            <div id="role-restriction-container" style="display: none;">
                <div class="form-group">
                    <label>Katılabilecek Roller:</label>
                    <div class="roles-grid">
                        <?php foreach ($available_system_roles as $role): ?>
                            <label class="role-checkbox">
                                <input type="checkbox" name="visibility_roles[]" value="<?= $role['id'] ?>">
                                <span class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>">
                                    <?= htmlspecialchars($role['role_name']) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- GÜNCELLENEN: Etkinlik Rolleri -->
        <div class="form-section">
            <h3><i class="fas fa-user-tag"></i> Etkinlik Rolleri</h3>
            <p class="section-description">Etkinlik için roller belirleyin. Rol eklerseniz kullanıcılar sadece rollerle katılabilir.</p>
            
            <div id="event-roles-container">
                <div class="roles-header">
                    <h4>Etkinlik Rolleri</h4>
                    <button type="button" id="add-role-btn" class="btn-secondary">
                        <i class="fas fa-plus"></i> Rol Ekle
                    </button>
                </div>
                
                <!-- Mevcut Roller (Düzenleme Modu) -->
                <?php if ($edit_mode && !empty($all_event_roles)): ?>
                    <div class="existing-roles-section">
                        <h5><i class="fas fa-list"></i> Mevcut Etkinlik Rolleri</h5>
                        <p class="info-text">
                            <i class="fas fa-info-circle"></i>
                            Aşağıda bu etkinlik için tanımlanmış tüm roller gösterilmektedir. 
                            Katılımcısı olan roller <span class="text-warning">sarı</span> ile işaretlenmiştir.
                        </p>
                        
                        <div class="existing-roles-list">
                            <?php foreach ($all_event_roles as $index => $role): ?>
                                <div class="existing-role-item <?= $role['has_participants'] ? 'has-participants' : '' ?>" data-role-id="<?= $role['slot_id'] ?>">
                                    <div class="role-info">
                                        <i class="<?= htmlspecialchars($role['icon_class']) ?>"></i>
                                        <span class="role-name"><?= htmlspecialchars($role['role_name']) ?></span>
                                        
                                        <?php if ($role['has_participants']): ?>
                                            <span class="participant-count"><?= $role['participant_count'] ?>/<?= $role['slot_count'] ?> katılımcı</span>
                                            <span class="protected-badge">
                                                <i class="fas fa-shield-alt"></i> Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="empty-badge">
                                                <i class="fas fa-circle"></i> Boş (0/<?= $role['slot_count'] ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="role-actions">
                                        <!-- Katılımcı sayısı düzenleme -->
                                        <div class="participant-limit-edit">
                                            <label>Limit:</label>
                                            <input type="number" 
                                                   name="existing_roles[<?= $role['slot_id'] ?>][slot_count]" 
                                                   value="<?= $role['slot_count'] ?>" 
                                                   min="<?= $role['participant_count'] ?>" 
                                                   class="participant-limit-input"
                                                   data-original-value="<?= $role['slot_count'] ?>">
                                            <input type="hidden" 
                                                   name="existing_roles[<?= $role['slot_id'] ?>][event_role_id]" 
                                                   value="<?= $role['event_role_id'] ?>">
                                        </div>
                                        
                                        <!-- Silme butonu -->
                                        <?php if ($role['has_participants']): ?>
                                            <button type="button" class="btn-warning btn-small" 
                                                    onclick="showParticipantWarning('<?= htmlspecialchars($role['role_name']) ?>', <?= $role['participant_count'] ?>, '<?= $role['slot_id'] ?>')">
                                                <i class="fas fa-exclamation-triangle"></i> 
                                                Kaldır
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-danger btn-small remove-existing-role" 
                                                    data-slot-id="<?= $role['slot_id'] ?>"
                                                    data-role-name="<?= htmlspecialchars($role['role_name']) ?>">
                                                <i class="fas fa-trash"></i> 
                                                Sil
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Katılımcılı rol uyarı modalı -->
                        <div id="participantWarningModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3><i class="fas fa-exclamation-triangle text-warning"></i> Dikkat</h3>
                                    <span class="close" onclick="closeParticipantModal()">&times;</span>
                                </div>
                                <div class="modal-body">
                                    <p id="participantWarningText"></p>
                                    <div class="warning-options">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="forceRemoveRole">
                                            <span>Bu rolü ve tüm katılımcılarını etkinlikten kaldırmayı onaylıyorum</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn-secondary" onclick="closeParticipantModal()">İptal</button>
                                    <button type="button" class="btn-danger" onclick="confirmRoleRemoval()">
                                        <i class="fas fa-trash"></i> Rolü Kaldır
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Yeni Rol Ekleme Alanı -->
                <div class="new-roles-section">
                    <h5><i class="fas fa-plus"></i> Yeni Rol Ekle</h5>
                    <div id="roles-list"></div>
                </div>
            </div>
        </div>

        <!-- Ek Seçenekler -->
        <div class="form-section">
            <h3><i class="fas fa-cog"></i> Ek Seçenekler</h3>
            
            <div class="form-group">
                <label for="suggested_loadout_id">Önerilen Teçhizat Seti</label>
                <select id="suggested_loadout_id" name="suggested_loadout_id">
                    <option value="">Seçiniz</option>
                    <?php foreach ($available_loadouts as $loadout): ?>
                        <option value="<?= $loadout['id'] ?>" 
                                <?= ($edit_mode && $event_data['suggested_loadout_id'] == $loadout['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loadout['set_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Katılımcılar için önerilen teçhizat seti</small>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="requires_role_selection" value="1"
                           <?= ($edit_mode && $event_data['requires_role_selection']) ? 'checked' : '' ?>>
                    <span>Rol seçimi zorunlu</span>
                </label>
                <small>İşaretlenirse, kullanıcılar sadece belirlenen rollerle katılabilir</small>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-times"></i> İptal
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i>
                <?= $edit_mode ? 'Güncelle' : 'Oluştur' ?>
            </button>
        </div>
    </form>
</div>

<!-- Rol Template (JavaScript için) -->
<template id="role-template">
    <div class="role-item">
        <div class="role-header">
            <select class="role-select" name="event_roles[{{INDEX}}][role_id]" required>
                <option value="">Rol seçin</option>
                <?php foreach ($available_event_roles as $role): ?>
                    <option value="<?= $role['id'] ?>" data-icon="<?= htmlspecialchars($role['icon_class']) ?>">
                        <?= htmlspecialchars($role['role_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" class="participant-limit" name="event_roles[{{INDEX}}][slot_count]" 
                   placeholder="Katılımcı sayısı" min="1" value="1">
            <button type="button" class="btn-danger remove-role">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="role-description"></div>
    </div>
</template>

<script>
// Mevcut rol silme JavaScript fonksiyonları
let roleToRemove = null;

function showParticipantWarning(roleName, participantCount, slotId) {
    const modal = document.getElementById('participantWarningModal');
    const warningText = document.getElementById('participantWarningText');
    
    warningText.innerHTML = `
        <strong>"${roleName}"</strong> rolünde <strong>${participantCount}</strong> aktif katılımcı bulunmaktadır. 
        Bu rolü kaldırırsanız, bu katılımcılar etkinlikten çıkarılacaktır.
        <br><br>
        Bu işlemi yapmak istediğinizden emin misiniz?
    `;
    
    modal.style.display = 'block';
    roleToRemove = { name: roleName, count: participantCount, slotId: slotId };
}

function closeParticipantModal() {
    const modal = document.getElementById('participantWarningModal');
    modal.style.display = 'none';
    document.getElementById('forceRemoveRole').checked = false;
    roleToRemove = null;
}

function confirmRoleRemoval() {
    const forceRemove = document.getElementById('forceRemoveRole').checked;
    
    if (!forceRemove) {
        alert('Rolü kaldırmak için onay kutusunu işaretlemeniz gerekiyor.');
        return;
    }
    
    if (roleToRemove) {
        // Form'a hidden input ekleyerek işaretleyelim
        const form = document.getElementById('eventForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_roles[]';
        input.value = roleToRemove.slotId;
        form.appendChild(input);
        
        // Görsel olarak rolü kaldır
        const roleElement = document.querySelector(`[data-role-id="${roleToRemove.slotId}"]`);
        if (roleElement) {
            roleElement.style.opacity = '0.5';
            roleElement.innerHTML += '<div class="removal-notice"><i class="fas fa-times"></i> Kaldırılacak</div>';
        }
        
        closeParticipantModal();
        
        // Başarı mesajı
        showNotification(`"${roleToRemove.name}" rolü kaldırılmak üzere işaretlendi.`, 'success');
    }
}

// Boş rol silme
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-existing-role') || 
        e.target.closest('.remove-existing-role')) {
        
        const button = e.target.classList.contains('remove-existing-role') ? 
                      e.target : e.target.closest('.remove-existing-role');
        const slotId = button.dataset.slotId;
        const roleName = button.dataset.roleName;
        const roleItem = button.closest('.existing-role-item');
        
        if (confirm(`"${roleName}" rolünü silmek istediğinizden emin misiniz?`)) {
            // Form'a silme işareti ekle
            const form = document.getElementById('eventForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_roles[]';
            input.value = slotId;
            form.appendChild(input);
            
            // Görsel olarak rolü kaldır
            roleItem.style.opacity = '0.5';
            roleItem.innerHTML += '<div class="removal-notice"><i class="fas fa-times"></i> Silinecek</div>';
            
            showNotification(`"${roleName}" rolü silinmek üzere işaretlendi.`, 'success');
        }
    }
});

// Form gönderiminde rol güncellemelerini işle
document.getElementById('eventForm').addEventListener('submit', function(e) {
    // Mevcut rol güncellemelerini kontrol et
    const participantLimits = document.querySelectorAll('.participant-limit-input');
    let hasChanges = false;
    
    participantLimits.forEach(input => {
        const originalValue = input.getAttribute('data-original-value');
        if (originalValue && originalValue !== input.value) {
            hasChanges = true;
        }
    });
    
    // Rol kaldırma işlemleri var mı kontrol et
    const removeInputs = document.querySelectorAll('input[name="remove_roles[]"]');
    if (removeInputs.length > 0) {
        hasChanges = true;
    }
    
    if (hasChanges) {
        const confirmMsg = 'Roller üzerinde değişiklikler yaptınız. Bu değişiklikleri kaydetmek istediğinizden emin misiniz?';
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
    }
});

// Utility function for notifications
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}
</script>

<script src="js/create_event.js"></script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>