<?php
// /events/create.php - Etkinlik oluşturma/düzenleme sayfası - Markdown desteği ile

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/webhook_functions.php';
require_once BASE_PATH . '/src/functions/Parsedown.php'; // Markdown parser

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
$event_id_param = $_GET['id'] ?? $_GET['edit'] ?? null;
if ($event_id_param && is_numeric($event_id_param)) {
    $event_id = (int)$event_id_param;
    
    try {
        // Etkinlik verilerini getir
        $stmt = $pdo->prepare("
            SELECT e.* 
            FROM events e 
            WHERE e.id = :id
        ");
        $stmt->execute([':id' => $event_id]);
        $event_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event_data) {
            // Düzenleme yetkisi kontrolü
            $can_edit = has_permission($pdo, 'event.edit_all') || 
                       (has_permission($pdo, 'event.edit_own') && $event_data['created_by_user_id'] == $current_user_id);
            
            if (!$can_edit) {
                $_SESSION['error_message'] = "Bu etkinliği düzenleme yetkiniz yok.";
                header('Location: index.php');
                exit;
            }
            $edit_mode = true;
        } else {
            $_SESSION['error_message'] = "Etkinlik bulunamadı.";
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

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Form gönderimi işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası - CSRF token geçersiz";
        header('Location: ' . $_SERVER['PHP_SELF'] . ($edit_mode ? "?id=$event_id" : ''));
        exit;
    }
    
    // Form verilerini al ve temizle
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $event_location = trim($_POST['event_location'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    $event_notes = trim($_POST['event_notes'] ?? '');
    $action = $_POST['action'] ?? 'save';
    
    // Status belirleme
    $status = ($action === 'draft') ? 'draft' : 'published';
    
    // Validasyon
    $errors = [];
    
    if (empty($event_title)) {
        $errors[] = "Etkinlik başlığı zorunludur.";
    }
    
    if (empty($event_description)) {
        $errors[] = "Etkinlik açıklaması zorunludur.";
    }
    
    if (empty($event_date)) {
        $errors[] = "Etkinlik tarihi zorunludur.";
    } else {
        $event_datetime = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);
        if (!$event_datetime) {
            $errors[] = "Geçersiz tarih formatı.";
        } else {
            // Geçmiş tarih kontrolü (sadece yeni etkinlikler için)
            if (!$edit_mode && $event_datetime < new DateTime()) {
                $errors[] = "Etkinlik tarihi gelecekte olmalıdır.";
            }
        }
    }
    
    if (!in_array($visibility, ['public', 'members_only', 'private'])) {
        $errors[] = "Geçersiz görünürlük ayarı.";
    }
    
    // Thumbnail yükleme işlemi
    $thumbnail_path = $edit_mode ? $event_data['event_thumbnail_path'] : null;
    if (isset($_FILES['event_thumbnail']) && $_FILES['event_thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleThumbnailUpload($_FILES['event_thumbnail']);
        if ($upload_result['success']) {
            $thumbnail_path = $upload_result['path'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }
    
    // Rol slotları işleme
    $role_slots = [];
    if (isset($_POST['role_slots']) && is_array($_POST['role_slots'])) {
        foreach ($_POST['role_slots'] as $slot) {
            if (!empty($slot['role_id']) && !empty($slot['slot_count'])) {
                $role_id = (int)$slot['role_id'];
                $slot_count = (int)$slot['slot_count'];
                
                if ($role_id > 0 && $slot_count > 0) {
                    $role_slots[] = [
                        'role_id' => $role_id,
                        'slot_count' => $slot_count
                    ];
                }
            }
        }
    }
    
    // Hata yoksa kaydet
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($edit_mode) {
                // Güncelleme
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET event_title = :title, event_description = :description, 
                        event_date = :date, event_location = :location, 
                        event_thumbnail_path = :thumbnail, visibility = :visibility, 
                        status = :status, event_notes = :notes, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title' => $event_title,
                    ':description' => $event_description,
                    ':date' => $event_date,
                    ':location' => $event_location,
                    ':thumbnail' => $thumbnail_path,
                    ':visibility' => $visibility,
                    ':status' => $status,
                    ':notes' => $event_notes,
                    ':id' => $event_id
                ]);
                
                // Mevcut rol slotlarını sil
                $delete_slots_stmt = $pdo->prepare("DELETE FROM event_role_slots WHERE event_id = :event_id");
                $delete_slots_stmt->execute([':event_id' => $event_id]);
                
                $message = "Etkinlik başarıyla güncellendi.";
            } else {
                // Yeni oluşturma
                $stmt = $pdo->prepare("
                    INSERT INTO events 
                    (created_by_user_id, event_title, event_description, event_date, 
                     event_location, event_thumbnail_path, visibility, status, event_notes)
                    VALUES (:user_id, :title, :description, :date, :location, :thumbnail, :visibility, :status, :notes)
                ");
                $stmt->execute([
                    ':user_id' => $current_user_id,
                    ':title' => $event_title,
                    ':description' => $event_description,
                    ':date' => $event_date,
                    ':location' => $event_location,
                    ':thumbnail' => $thumbnail_path,
                    ':visibility' => $visibility,
                    ':status' => $status,
                    ':notes' => $event_notes
                ]);
                
                $event_id = $pdo->lastInsertId();
                $message = "Etkinlik başarıyla oluşturuldu.";
            }
            
            // Rol slotlarını kaydet
            if (!empty($role_slots)) {
                $slot_stmt = $pdo->prepare("
                    INSERT INTO event_role_slots (event_id, role_id, slot_count)
                    VALUES (:event_id, :role_id, :slot_count)
                ");
                
                foreach ($role_slots as $slot) {
                    $slot_stmt->execute([
                        ':event_id' => $event_id,
                        ':role_id' => $slot['role_id'],
                        ':slot_count' => $slot['slot_count']
                    ]);
                }
            }
            
            $pdo->commit();
            
            // Webhook gönder (sadece yayınlanmış etkinlikler için)
            if ($status === 'published') {
                $webhook_action = $edit_mode ? 'updated' : 'created';
                $webhook_result = send_event_webhook($pdo, $event_id, $webhook_action);
                
                if (!$webhook_result) {
                    error_log("Webhook failed for event $event_id (action: $webhook_action)");
                    // Webhook hatası etkinlik oluşturmayı engellemesin, sadece log'a yazsın
                }
            }
            
            $_SESSION['success_message'] = $message;
            header('Location: detail.php?id=' . $event_id);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Event save error: " . $e->getMessage());
            $errors[] = "Etkinlik kaydedilirken bir hata oluştu: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Mevcut rolleri çek
try {
    $roles_stmt = $pdo->prepare("
        SELECT id, role_name, role_description, role_icon
        FROM event_roles
        ORDER BY role_name ASC
    ");
    $roles_stmt->execute();
    $available_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Roles fetch error: " . $e->getMessage());
    $available_roles = [];
}

// Mevcut rol slotlarını çek (düzenleme modu için)
$existing_role_slots = [];
if ($edit_mode) {
    try {
        $slots_stmt = $pdo->prepare("
            SELECT ers.role_id, ers.slot_count, er.role_name, er.role_icon
            FROM event_role_slots ers
            JOIN event_roles er ON ers.role_id = er.id
            WHERE ers.event_id = :event_id
            ORDER BY er.role_name ASC
        ");
        $slots_stmt->execute([':event_id' => $event_id]);
        $existing_role_slots = $slots_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Role slots fetch error: " . $e->getMessage());
    }
}

$page_title = $edit_mode ? "Etkinlik Düzenle" : "Yeni Etkinlik Oluştur";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => $edit_mode ? 'Etkinlik Düzenle' : 'Yeni Etkinlik Oluştur', 'url' => '', 'icon' => $edit_mode ? 'fas fa-edit' : 'fas fa-plus']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
events_layout_start($breadcrumb_items, $page_title);

// Thumbnail upload fonksiyonu
function handleThumbnailUpload($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Sadece JPG, PNG ve GIF dosyaları kabul edilir.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Dosya boyutu en fazla 5MB olabilir.'];
    }
    
    $upload_dir = 'uploads/events/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath];
    } else {
        return ['success' => false, 'error' => 'Dosya yüklenirken bir hata oluştu.'];
    }
}
?>
<link rel="stylesheet" href="css/events_sidebar.css">

<link rel="stylesheet" href="css/events_create.css">

<!-- Create Event Container -->
<div class="create-event-container">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fas fa-<?= $edit_mode ? 'edit' : 'plus' ?>"></i>
                    <?= $page_title ?>
                    <?php if ($edit_mode): ?>
                        <span class="edit-mode-badge">
                            <i class="fas fa-edit"></i>
                            Düzenleme Modu
                        </span>
                    <?php endif; ?>
                </h1>
                <p><?= $edit_mode ? 'Etkinlik bilgilerini güncelleyin ve değişikliklerinizi kaydedin.' : 'Yeni bir etkinlik oluşturun ve üyelerinizle paylaşın.' ?></p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Etkinliklere Dön
                </a>
                <?php if ($edit_mode): ?>
                    <a href="detail.php?id=<?= $event_id ?>" class="btn-secondary">
                        <i class="fas fa-eye"></i>
                        Önizleme
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Event Form -->
    <form id="eventForm" method="POST" enctype="multipart/form-data" class="event-form">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <!-- Temel Bilgiler -->
        <div class="form-section">
            <h3>
                <i class="fas fa-info-circle"></i>
                Temel Bilgiler
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_title">Etkinlik Başlığı *</label>
                    <input type="text" 
                           id="event_title" 
                           name="event_title" 
                           value="<?= $edit_mode ? htmlspecialchars($event_data['event_title']) : '' ?>" 
                           required 
                           maxlength="255"
                           class="form-input">
                    <small>Etkinliğiniz için açıklayıcı bir başlık girin</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group full-width">
                    <label for="event_description">Etkinlik Açıklaması * <span class="markdown-indicator">Markdown Destekli</span></label>
                    <div class="markdown-editor">
                        <div class="editor-tabs">
                            <button type="button" class="tab-button active" data-tab="editor">
                                <i class="fas fa-edit"></i> Düzenle
                            </button>
                            <button type="button" class="tab-button" data-tab="preview">
                                <i class="fas fa-eye"></i> Önizleme
                            </button>
                        </div>
                        
                        <div class="editor-toolbar">
                            <button type="button" class="toolbar-btn" data-action="bold" title="Kalın (Ctrl+B)">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="italic" title="İtalik (Ctrl+I)">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="strikethrough" title="Üstü Çizili">
                                <i class="fas fa-strikethrough"></i>
                            </button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" data-action="heading" title="Başlık">
                                <i class="fas fa-heading"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="quote" title="Alıntı">
                                <i class="fas fa-quote-right"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="code" title="Kod">
                                <i class="fas fa-code"></i>
                            </button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" data-action="list-ul" title="Madde İşaretli Liste">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="list-ol" title="Numaralı Liste">
                                <i class="fas fa-list-ol"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="link" title="Bağlantı">
                                <i class="fas fa-link"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="image" title="Resim URL'si">
                                <i class="fas fa-image"></i>
                            </button>
                            <button type="button" class="toolbar-btn" data-action="image-upload" title="Resim Yükle">
                                <i class="fas fa-upload"></i>
                            </button>
                        </div>
                        
                        <div class="editor-content">
                            <div class="tab-content active" id="editor-tab">
                                <textarea id="event_description" 
                                          name="event_description" 
                                          required
                                          class="markdown-textarea"
                                          placeholder="Etkinliğinizin detaylarını markdown formatında yazın...

Örnek kullanım:
# Ana Başlık
## Alt Başlık

**Kalın metin** ve *italik metin*

- Madde işaretli liste
- İkinci madde

1. Numaralı liste
2. İkinci madde

> Alıntı metni

`Kod örneği`

[Bağlantı metni](https://example.com)

![Resim açıklaması](https://example.com/resim.jpg)

💡 İpucu: Toolbar'daki 📤 butonunu kullanarak resim yükleyebilirsiniz!"><?= $edit_mode ? htmlspecialchars($event_data['event_description']) : '' ?></textarea>
                            </div>
                            
                            <div class="tab-content" id="preview-tab">
                                <div class="markdown-preview">
                                    <p class="preview-placeholder">Önizleme için açıklama yazın...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <small>Markdown formatını kullanarak zengin metin oluşturabilirsiniz</small>
                </div>
            </div>
        </div>

        <!-- Tarih ve Lokasyon -->
        <div class="form-section">
            <h3>
                <i class="fas fa-calendar-alt"></i>
                Tarih ve Lokasyon
            </h3>
            
            <div class="form-row two-columns">
                <div class="form-group">
                    <label for="event_date">Etkinlik Tarihi ve Saati *</label>
                    <input type="datetime-local" 
                           id="event_date" 
                           name="event_date" 
                           value="<?= $edit_mode ? date('Y-m-d\TH:i', strtotime($event_data['event_date'])) : '' ?>" 
                           required 
                           class="form-input">
                    <small>Etkinliğin başlayacağı tarih ve saat</small>
                </div>
                
                <div class="form-group">
                    <label for="event_location">Etkinlik Lokasyonu</label>
                    <input type="text" 
                           id="event_location" 
                           name="event_location" 
                           value="<?= $edit_mode ? htmlspecialchars($event_data['event_location']) : '' ?>" 
                           maxlength="255"
                           placeholder="Discord, Oyun Sunucusu, Fiziksel Adres vb."
                           class="form-input">
                    <small>Etkinliğin gerçekleşeceği yer</small>
                </div>
            </div>
        </div>

        <!-- Thumbnail -->
        <div class="form-section">
            <h3>
                <i class="fas fa-image"></i>
                Etkinlik Görseli
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_thumbnail">Etkinlik Thumbnail'i</label>
                    <div class="thumbnail-upload">
                        <input type="file" 
                               id="event_thumbnail" 
                               name="event_thumbnail" 
                               accept="image/*"
                               class="file-input">
                        <div class="thumbnail-preview" id="thumbnailPreview">
                            <?php if ($edit_mode && !empty($event_data['event_thumbnail_path'])): ?>
                                <img src="<?= htmlspecialchars($event_data['event_thumbnail_path']) ?>" 
                                     alt="Mevcut thumbnail" 
                                     class="current-thumbnail">
                            <?php else: ?>
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Görsel yüklemek için tıklayın</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small>JPG, PNG veya GIF formatında, maksimum 5MB</small>
                </div>
            </div>
        </div>

        <!-- Görünürlük ve Ayarlar -->
        <div class="form-section">
            <h3>
                <i class="fas fa-cog"></i>
                Görünürlük ve Ayarlar
            </h3>
            
            <div class="form-row two-columns">
                <div class="form-group">
                    <label for="visibility">Görünürlük</label>
                    <select id="visibility" name="visibility" class="form-select">
                        <option value="public" <?= (!$edit_mode || $event_data['visibility'] === 'public') ? 'selected' : '' ?>>
                            Herkese Açık
                        </option>
                        <option value="members_only" <?= ($edit_mode && $event_data['visibility'] === 'members_only') ? 'selected' : '' ?>>
                            Sadece Üyeler
                        </option>
                        <option value="private" <?= ($edit_mode && $event_data['visibility'] === 'private') ? 'selected' : '' ?>>
                            Özel
                        </option>
                    </select>
                    <small>Etkinliği kimler görebilir</small>
                </div>
            </div>
        </div>

        <!-- Rol Slotları -->
        <div class="form-section">
            <h3>
                <i class="fas fa-users"></i>
                Etkinlik Rolleri
                <button type="button" id="addRoleSlot" class="add-role-btn">
                    <i class="fas fa-plus"></i>
                    Rol Ekle
                </button>
            </h3>
            
            <div id="roleSlotsContainer" class="role-slots-container">
                <?php if ($edit_mode && !empty($existing_role_slots)): ?>
                    <?php foreach ($existing_role_slots as $index => $slot): ?>
                        <div class="role-slot" data-index="<?= $index ?>">
                            <div class="role-slot-content">
                                <div class="role-select-group">
                                    <label>Rol</label>
                                    <select name="role_slots[<?= $index ?>][role_id]" class="form-select role-select" required>
                                        <option value="">Rol Seçin</option>
                                        <?php foreach ($available_roles as $role): ?>
                                            <option value="<?= $role['id'] ?>" 
                                                    <?= $slot['role_id'] == $role['id'] ? 'selected' : '' ?>
                                                    data-icon="<?= htmlspecialchars($role['role_icon']) ?>">
                                                <?= htmlspecialchars($role['role_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="slot-count-group">
                                    <label>Slot Sayısı</label>
                                    <input type="number" 
                                           name="role_slots[<?= $index ?>][slot_count]" 
                                           value="<?= $slot['slot_count'] ?>"
                                           min="1" 
                                           max="50" 
                                           class="form-input slot-count-input" 
                                           required>
                                </div>
                                
                                <button type="button" class="remove-role-slot" title="Rolü Kaldır">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="role-slots-summary">
                    <div class="total-participants">
                        <span>Toplam Katılımcı: </span>
                        <strong id="totalParticipants">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Organize Eden Notları -->
        <div class="form-section">
            <h3>
                <i class="fas fa-sticky-note"></i>
                Özel Notlar
            </h3>
            
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="event_notes">Organize Eden Notları</label>
                    <textarea id="event_notes" 
                              name="event_notes" 
                              rows="4" 
                              maxlength="1000"
                              placeholder="Sadece sizin görebileceğiniz özel notlar..."
                              class="form-textarea"><?= $edit_mode ? htmlspecialchars($event_data['event_notes'] ?? '') : '' ?></textarea>
                    <small>Sadece organize eden kişi tarafından görülen notlar</small>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <div class="action-buttons">
                <button type="submit" name="action" value="save" class="btn-primary">
                    <i class="fas fa-save"></i>
                    <?= $edit_mode ? 'Değişiklikleri Kaydet' : 'Etkinliği Oluştur' ?>
                </button>
                
                <button type="submit" name="action" value="draft" class="btn-secondary">
                    <i class="fas fa-file-alt"></i>
                    Taslak Olarak Kaydet
                </button>
                
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    İptal Et
                </a>
            </div>
            
            <?php if ($edit_mode): ?>
                <div class="additional-actions">
                    <a href="detail.php?id=<?= $event_id ?>" class="btn-view">
                        <i class="fas fa-eye"></i>
                        Önizleme
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Server'dan JavaScript'e veri aktarımı -->
<script id="roleData" type="application/json">
<?= json_encode($available_roles, JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>

<script>
// Global değişkenleri ayarla
window.availableRoles = <?= json_encode($available_roles) ?>;
</script>

<!-- JavaScript dosyasını dahil et -->
<script src="js/events_create.js"></script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>