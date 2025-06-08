<?php
// events/create.php - Etkinlik oluşturma sayfası

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
        $_SESSION['error_message'] = "Güvenlik hatası - CSRF token geçersiz";
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
    
    // Thumbnail upload işlemi
    $thumbnail_path = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = BASE_PATH . '/uploads/events/thumbnails/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = uniqid('event_thumb_') . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_path)) {
                $thumbnail_path = '/uploads/events/thumbnails/' . $filename;
            }
        }
    }
    
    // Rol görünürlüğü
    $visibility_roles = [];
    if ($visibility === 'role_restricted' && !empty($_POST['visibility_roles'])) {
        $visibility_roles = array_map('intval', $_POST['visibility_roles']);
    }
    
    // Etkinlik rolleri
    $event_roles = [];
    if ($requires_role_selection && !empty($_POST['event_roles'])) {
        foreach ($_POST['event_roles'] as $role_data) {
            if (!empty($role_data['role_id'])) {
                $event_roles[] = [
                    'role_id' => (int)$role_data['role_id'],
                    'participant_limit' => !empty($role_data['participant_limit']) ? (int)$role_data['participant_limit'] : null
                ];
            }
        }
    }
    
    // Validasyon
    $errors = [];
    
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
    
    if ($requires_role_selection && empty($event_roles)) {
        $errors[] = "Rol seçimi zorunlu işaretlendiğinde en az bir rol eklemelisiniz.";
    }
    
    // Rol sınır kontrolleri
    foreach ($event_roles as $role) {
        if (isset($role['protected']) && $role['protected']) {
            // Korumalı roller için ek kontrol yok, zaten yukarıda yapıldı
            continue;
        }
        
        if (isset($role['participant_limit']) && $role['participant_limit'] < 1) {
            $errors[] = "Rol katılımcı sınırı 1'den az olamaz.";
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
            $suggested_loadout_id = null;
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($edit_mode) {
                // Güncelleme
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET title = :title, description = :description, location = :location,
                        event_type = :event_type, event_datetime = :event_datetime,
                        visibility = :visibility, max_participants = :max_participants,
                        suggested_loadout_id = :loadout_id, requires_role_selection = :requires_role_selection,
                        thumbnail_path = COALESCE(:thumbnail_path, thumbnail_path),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':location' => $location,
                    ':event_type' => $event_type,
                    ':event_datetime' => $event_datetime,
                    ':visibility' => $visibility,
                    ':max_participants' => $max_participants,
                    ':loadout_id' => $suggested_loadout_id,
                    ':requires_role_selection' => $requires_role_selection,
                    ':thumbnail_path' => $thumbnail_path,
                    ':id' => $event_id
                ]);
                
                $message = "Etkinlik başarıyla güncellendi.";
            } else {
                // Yeni oluşturma
                $stmt = $pdo->prepare("
                    INSERT INTO events 
                    (title, description, location, event_type, event_datetime, visibility, 
                     max_participants, suggested_loadout_id, requires_role_selection, 
                     thumbnail_path, created_by_user_id)
                    VALUES (:title, :description, :location, :event_type, :event_datetime, 
                           :visibility, :max_participants, :loadout_id, :requires_role_selection,
                           :thumbnail_path, :user_id)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':location' => $location,
                    ':event_type' => $event_type,
                    ':event_datetime' => $event_datetime,
                    ':visibility' => $visibility,
                    ':max_participants' => $max_participants,
                    ':loadout_id' => $suggested_loadout_id,
                    ':requires_role_selection' => $requires_role_selection,
                    ':thumbnail_path' => $thumbnail_path,
                    ':user_id' => $current_user_id
                ]);
                
                $event_id = $pdo->lastInsertId();
                $message = "Etkinlik başarıyla oluşturuldu.";
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = $message;
            header('Location: view.php?id=' . $event_id);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Event save error: " . $e->getMessage());
            $errors[] = "Etkinlik kaydedilirken bir hata oluştu.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Mevcut teçhizat setlerini çek
try {
    $loadouts_stmt = $pdo->prepare("
        SELECT ls.id, ls.set_name, u.username
        FROM loadout_sets ls
        LEFT JOIN users u ON ls.user_id = u.id
        WHERE ls.status = 'published' 
        AND (ls.visibility = 'public' OR (ls.visibility = 'members_only' AND :is_member = 1))
        ORDER BY ls.set_name ASC
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

// Mevcut etkinlik rolleri (düzenleme modu için)
$existing_event_roles = [];
if ($edit_mode) {
    try {
        $event_roles_stmt = $pdo->prepare("
            SELECT er.id, er.role_name, er.icon_class,
                   COUNT(ep.id) as participant_count
            FROM event_participants ep
            JOIN event_roles er ON ep.event_role_id = er.id
            WHERE ep.event_id = ? AND ep.status = 'joined'
            GROUP BY er.id, er.role_name, er.icon_class
            HAVING COUNT(ep.id) > 0
            ORDER BY er.role_name ASC
        ");
        $event_roles_stmt->execute([$event_id]);
        $existing_event_roles = $event_roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Existing event roles fetch error: " . $e->getMessage());
        $existing_event_roles = [];
    }
}
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
            <p>Star Citizen operasyonları ve topluluk etkinlikleri düzenleyin</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<div class="create-event-container">
    <form id="eventForm" method="POST" enctype="multipart/form-data" class="event-form">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- Temel Bilgiler -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Temel Bilgiler</h3>
            
            <div class="form-group">
                <label for="title">Etkinlik Başlığı *</label>
                <input type="text" id="title" name="title" required 
                       value="<?= $edit_mode ? htmlspecialchars($event_data['title']) : '' ?>"
                       placeholder="örn: Operation Reclaim, Weekly Training, Community Mining">
                <small>Açıklayıcı ve çekici bir başlık seçin</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_type">Etkinlik Türü *</label>
                    <select id="event_type" name="event_type" required>
                        <option value="Genel" <?= ($edit_mode && $event_data['event_type'] === 'Genel') ? 'selected' : '' ?>>Genel</option>
                        <option value="Operasyon" <?= ($edit_mode && $event_data['event_type'] === 'Operasyon') ? 'selected' : '' ?>>Operasyon</option>
                        <option value="Eğitim" <?= ($edit_mode && $event_data['event_type'] === 'Eğitim') ? 'selected' : '' ?>>Eğitim</option>
                        <option value="Topluluk" <?= ($edit_mode && $event_data['event_type'] === 'Topluluk') ? 'selected' : '' ?>>Topluluk</option>
                        <option value="Yarışma" <?= ($edit_mode && $event_data['event_type'] === 'Yarışma') ? 'selected' : '' ?>>Yarışma</option>
                        <option value="Keşif" <?= ($edit_mode && $event_data['event_type'] === 'Keşif') ? 'selected' : '' ?>>Keşif</option>
                        <option value="Ticaret" <?= ($edit_mode && $event_data['event_type'] === 'Ticaret') ? 'selected' : '' ?>>Ticaret</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="event_datetime">Etkinlik Zamanı *</label>
                    <input type="datetime-local" id="event_datetime" name="event_datetime" required
                           value="<?= $edit_mode ? date('Y-m-d\TH:i', strtotime($event_data['event_datetime'])) : '' ?>">
                    <small>Türkiye saati (UTC+3) olarak girin</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="location">Etkinlik Yeri</label>
                <input type="text" id="location" name="location" 
                       value="<?= $edit_mode ? htmlspecialchars($event_data['location']) : '' ?>"
                       placeholder="örn: Stanton System, Crusader, Port Olisar">
                <small>Star Citizen evrenindeki konum (opsiyonel)</small>
            </div>
            
            <div class="form-group">
                <label for="thumbnail">Etkinlik Thumbnail'i</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                <small>Etkinlik için küçük bir görsel yükleyin (JPG, PNG, GIF, WebP)</small>
                <?php if ($edit_mode && $event_data['thumbnail_path']): ?>
                    <div class="current-thumbnail">
                        <img src="<?= htmlspecialchars($event_data['thumbnail_path']) ?>" alt="Mevcut thumbnail" style="max-width: 200px; max-height: 100px; margin-top: 10px;">
                        <p><small>Mevcut thumbnail (yeni yüklerseniz değiştirilir)</small></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Açıklama -->
        <div class="form-section">
            <h3><i class="fas fa-file-text"></i> Etkinlik Açıklaması</h3>
            
            <div class="form-group">
                <label for="description">Açıklama *</label>
                <div class="markdown-editor">
                    <div class="editor-toolbar">
                        <button type="button" class="editor-btn" data-action="bold" title="Kalın">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="editor-btn" data-action="italic" title="İtalik">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="editor-btn" data-action="heading" title="Başlık">
                            <i class="fas fa-heading"></i>
                        </button>
                        <button type="button" class="editor-btn" data-action="list" title="Liste">
                            <i class="fas fa-list-ul"></i>
                        </button>
                        <button type="button" class="editor-btn" data-action="link" title="Link">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="editor-btn" data-action="preview" title="Önizleme">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <textarea id="description" name="description" required rows="8" 
                              placeholder="Etkinliğinizi detaylı şekilde açıklayın... Markdown formatını kullanabilirsiniz."><?= $edit_mode ? htmlspecialchars($event_data['description']) : '' ?></textarea>
                    <div id="preview-area" class="preview-area" style="display: none;"></div>
                </div>
                <small>Markdown formatını kullanabilirsiniz. **kalın**, *italik*, # başlık, - liste</small>
            </div>
        </div>

        <!-- Görünürlük ve Katılım -->
        <div class="form-section">
            <h3><i class="fas fa-users"></i> Görünürlük ve Katılım</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="visibility">Etkinlik Görünürlüğü *</label>
                    <select id="visibility" name="visibility" required>
                        <option value="public" <?= ($edit_mode && $event_data['visibility'] === 'public') ? 'selected' : '' ?>>
                            Herkese Açık
                        </option>
                        <option value="members_only" <?= ($edit_mode && $event_data['visibility'] === 'members_only') ? 'selected' : '' ?>>
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

        <!-- Etkinlik Rolleri -->
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
                
                <!-- Bilgi Mesajı (Düzenleme Modu) -->
                <?php if ($edit_mode && !empty($existing_event_roles)): ?>
                    <div class="existing-roles-info">
                        <h5><i class="fas fa-info-circle"></i> Mevcut Katılımcılı Roller</h5>
                        <p class="info-text">
                            <i class="fas fa-exclamation-triangle"></i>
                            Aşağıdaki roller şu anda aktif katılımcılara sahip.
                        </p>
                        
                        <div class="existing-roles-list">
                            <?php foreach ($existing_event_roles as $role): ?>
                                <div class="existing-role-item">
                                    <div class="role-info">
                                        <i class="<?= htmlspecialchars($role['icon_class']) ?>"></i>
                                        <span class="role-name"><?= htmlspecialchars($role['role_name']) ?></span>
                                        <span class="participant-count">
                                            <i class="fas fa-users"></i>
                                            <?= $role['participant_count'] ?> aktif katılımcı
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div id="roles-list" class="roles-list">
                    <!-- Yeni roller dinamik olarak eklenecek -->
                </div>
            </div>
        </div>

        <!-- Önerilen Teçhizat -->
        <div class="form-section">
            <h3><i class="fas fa-shield-alt"></i> Önerilen Teçhizat</h3>
            
            <div class="form-group">
                <label for="suggested_loadout_id">Önerilen Teçhizat Seti</label>
                <select id="suggested_loadout_id" name="suggested_loadout_id">
                    <option value="">Teçhizat seti seçin (opsiyonel)</option>
                    <?php foreach ($available_loadouts as $loadout): ?>
                        <option value="<?= $loadout['id'] ?>" 
                                <?= ($edit_mode && $event_data['suggested_loadout_id'] == $loadout['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loadout['set_name']) ?> 
                            (<?= htmlspecialchars($loadout['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Katılımcılar için önerilen teçhizat seti</small>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" onclick="window.location.href='index.php'" class="btn-secondary">
                <i class="fas fa-times"></i> İptal
            </button>
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
            <input type="number" class="participant-limit" name="event_roles[{{INDEX}}][participant_limit]" 
                   placeholder="Katılımcı sayısı" min="1">
            <button type="button" class="btn-danger remove-role">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="role-description"></div>
    </div>
</template>

<script src="js/create_event.js"></script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>