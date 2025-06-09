<?php
// /events/create.php - Etkinlik oluşturma/düzenleme sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_events_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

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
    $visibility = $_POST['visibility'] ?? 'members_only';
    $status = $_POST['status'] ?? 'draft';
    
    // Rol slotları
    $role_slots = $_POST['role_slots'] ?? [];
    
    // Validasyon
    $errors = [];
    
    if (empty($event_title)) {
        $errors[] = "Etkinlik adı gereklidir.";
    } elseif (strlen($event_title) < 5 || strlen($event_title) > 255) {
        $errors[] = "Etkinlik adı 5-255 karakter arasında olmalıdır.";
    }
    
    if (empty($event_description)) {
        $errors[] = "Etkinlik açıklaması gereklidir.";
    } elseif (strlen($event_description) < 20) {
        $errors[] = "Etkinlik açıklaması en az 20 karakter olmalıdır.";
    }
    
    if (empty($event_date)) {
        $errors[] = "Etkinlik tarihi gereklidir.";
    } else {
        $event_datetime = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);
        if (!$event_datetime) {
            $errors[] = "Geçersiz tarih formatı.";
        } elseif ($event_datetime <= new DateTime()) {
            $errors[] = "Etkinlik tarihi gelecekte olmalıdır.";
        }
    }
    
    if (!in_array($visibility, ['public', 'members_only', 'faction_only'])) {
        $visibility = 'members_only';
    }
    
    if (!in_array($status, ['draft', 'published'])) {
        $status = 'draft';
    }
    
    // Rol slotları validasyonu
    if (!empty($role_slots)) {
        foreach ($role_slots as $index => $slot) {
            $role_id = (int)($slot['role_id'] ?? 0);
            $slot_count = (int)($slot['slot_count'] ?? 0);
            
            if ($role_id <= 0) {
                $errors[] = "Geçersiz rol seçimi (Slot " . ($index + 1) . ").";
            }
            
            if ($slot_count <= 0 || $slot_count > 50) {
                $errors[] = "Slot sayısı 1-50 arasında olmalıdır (Slot " . ($index + 1) . ").";
            }
        }
    }
    
    // Thumbnail upload işlemi
    $thumbnail_path = $edit_mode ? $event_data['event_thumbnail_path'] : null;
    if (isset($_FILES['event_thumbnail']) && $_FILES['event_thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleThumbnailUpload($_FILES['event_thumbnail']);
        if ($upload_result['success']) {
            $thumbnail_path = $upload_result['path'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }
    
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
                        status = :status, updated_at = NOW()
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
                    ':id' => $event_id
                ]);
                
                // Mevcut rol slotlarını sil
                $del_stmt = $pdo->prepare("DELETE FROM event_role_slots WHERE event_id = :event_id");
                $del_stmt->execute([':event_id' => $event_id]);
                
                $message = "Etkinlik başarıyla güncellendi.";
            } else {
                // Yeni oluşturma
                $stmt = $pdo->prepare("
                    INSERT INTO events 
                    (created_by_user_id, event_title, event_description, event_date, 
                     event_location, event_thumbnail_path, visibility, status)
                    VALUES (:user_id, :title, :description, :date, :location, :thumbnail, :visibility, :status)
                ");
                $stmt->execute([
                    ':user_id' => $current_user_id,
                    ':title' => $event_title,
                    ':description' => $event_description,
                    ':date' => $event_date,
                    ':location' => $event_location,
                    ':thumbnail' => $thumbnail_path,
                    ':visibility' => $visibility,
                    ':status' => $status
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
                    $role_id = (int)$slot['role_id'];
                    $slot_count = (int)$slot['slot_count'];
                    
                    if ($role_id > 0 && $slot_count > 0) {
                        $slot_stmt->execute([
                            ':event_id' => $event_id,
                            ':role_id' => $role_id,
                            ':slot_count' => $slot_count
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = $message;
            header('Location: detail.php?id=' . $event_id);
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

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="header-info">
            <h1>
                <i class="fas fa-<?= $edit_mode ? 'edit' : 'plus' ?>"></i>
                <?= $edit_mode ? 'Etkinlik Düzenle' : 'Yeni Etkinlik Oluştur' ?>
            </h1>
            <p><?= $edit_mode ? 'Star Citizen etkinliğini düzenleyin ve rol slotlarını yönetin' : 'Star Citizen operasyonları için yeni etkinlik oluşturun' ?></p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Etkinliklere Dön
            </a>
            <?php if ($edit_mode): ?>
                <a href="detail.php?id=<?= $event_id ?>" class="btn-secondary">
                    <i class="fas fa-eye"></i> Etkinlik Detayını Gör
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="create-event-container">
    <form id="eventForm" method="POST" enctype="multipart/form-data" class="event-form">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <!-- 📝 Bölüm 1: Temel Bilgiler -->
        <div class="form-section">
            <h3>
                <i class="fas fa-info-circle"></i> 
                Temel Bilgiler
                <?php if ($edit_mode): ?>
                    <span class="edit-mode-badge">
                        <i class="fas fa-edit"></i> Düzenleme Modu
                    </span>
                <?php endif; ?>
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_title">Etkinlik Adı *</label>
                    <input type="text" id="event_title" name="event_title" required 
                           value="<?= $edit_mode ? htmlspecialchars($event_data['event_title']) : '' ?>"
                           placeholder="örn: Stanton Sistem Keşif Operasyonu">
                    <small>Etkinliğinizi en iyi tanımlayan açıklayıcı bir başlık</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="event_description">Etkinlik Açıklaması *</label>
                <textarea id="event_description" name="event_description" required rows="6"
                          placeholder="Etkinliğin amacını, hedeflerini ve katılımcılardan beklentileri detaylı şekilde açıklayın..."><?= $edit_mode ? htmlspecialchars($event_data['event_description']) : '' ?></textarea>
                <small>En az 20 karakter, ayrıntılı açıklama yapın</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_date">Etkinlik Tarihi ve Saati *</label>
                    <input type="datetime-local" id="event_date" name="event_date" required
                           value="<?= $edit_mode ? date('Y-m-d\TH:i', strtotime($event_data['event_date'])) : '' ?>">
                    <small>Etkinliğin başlayacağı tarih ve saat</small>
                </div>
                
                <div class="form-group">
                    <label for="event_location">Lokasyon</label>
                    <input type="text" id="event_location" name="event_location"
                           value="<?= $edit_mode ? htmlspecialchars($event_data['event_location']) : '' ?>"
                           placeholder="örn: Stanton > Crusader > Port Olisar">
                    <small>Star Citizen evrenindeki konum</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="visibility">Görünürlük</label>
                    <select id="visibility" name="visibility">
                        <option value="public" <?= ($edit_mode && $event_data['visibility'] === 'public') ? 'selected' : '' ?>>
                            🌐 Herkese Açık
                        </option>
                        <option value="members_only" <?= (!$edit_mode || $event_data['visibility'] === 'members_only') ? 'selected' : '' ?>>
                            👥 Sadece Üyeler
                        </option>
                        <option value="faction_only" <?= ($edit_mode && $event_data['visibility'] === 'faction_only') ? 'selected' : '' ?>>
                            🏛️ Sadece Fraksiyon
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Durum</label>
                    <select id="status" name="status">
                        <option value="draft" <?= (!$edit_mode || $event_data['status'] === 'draft') ? 'selected' : '' ?>>
                            📝 Taslak
                        </option>
                        <option value="published" <?= ($edit_mode && $event_data['status'] === 'published') ? 'selected' : '' ?>>
                            ✅ Yayınla
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 🖼️ Bölüm 2: Görsel Ayarları -->
        <div class="form-section">
            <h3><i class="fas fa-image"></i> Görsel Ayarları</h3>
            
            <div class="thumbnail-upload-section">
                <div class="upload-area">
                    <div class="form-group">
                        <label for="event_thumbnail">Etkinlik Görseli</label>
                        <input type="file" id="event_thumbnail" name="event_thumbnail" 
                               accept="image/jpeg,image/jpg,image/png,image/gif">
                        <small>JPG, PNG veya GIF formatında, maksimum 5MB</small>
                    </div>
                    
                    <div class="upload-info">
                        <div class="info-item">
                            <i class="fas fa-info-circle"></i>
                            <span>Önerilen boyut: 800x400 piksel</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-palette"></i>
                            <span>16:9 en boy oranı ideal</span>
                        </div>
                    </div>
                </div>
                
                <div class="thumbnail-preview">
                    <?php if ($edit_mode && !empty($event_data['event_thumbnail_path'])): ?>
                        <img id="preview-image" src="<?= htmlspecialchars($event_data['event_thumbnail_path']) ?>" 
                             alt="Mevcut thumbnail">
                        <div class="preview-overlay">
                            <span>Mevcut Görsel</span>
                        </div>
                    <?php else: ?>
                        <div id="preview-placeholder" class="preview-placeholder">
                            <i class="fas fa-image"></i>
                            <span>Görsel Önizlemesi</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 👥 Bölüm 3: Rol Slotları (ANA YENİLİK) -->
        <div class="form-section role-slots-section">
            <h3>
                <i class="fas fa-users-cog"></i> 
                Rol Slotları
                <span class="total-participants">
                    Toplam: <span id="total-count">0</span> katılımcı
                </span>
            </h3>
            <p class="section-description">
                Etkinliğiniz için gerekli rolleri ve her rolden kaç kişi gerektiğini belirleyin
            </p>
            
            <div id="role-slots-container">
                <?php if ($edit_mode && !empty($existing_role_slots)): ?>
                    <?php foreach ($existing_role_slots as $index => $slot): ?>
                        <div class="role-slot-card" data-index="<?= $index ?>">
                            <div class="role-slot-header">
                                <div class="role-info">
                                    <i class="<?= htmlspecialchars($slot['role_icon']) ?>"></i>
                                    <span class="role-name"><?= htmlspecialchars($slot['role_name']) ?></span>
                                </div>
                                <button type="button" class="btn-remove-slot" onclick="removeRoleSlot(<?= $index ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="role-slot-content">
                                <input type="hidden" name="role_slots[<?= $index ?>][role_id]" value="<?= $slot['role_id'] ?>">
                                <div class="slot-counter">
                                    <label>Slot Sayısı:</label>
                                    <div class="counter-controls">
                                        <button type="button" class="counter-btn" onclick="decrementSlot(<?= $index ?>)">-</button>
                                        <input type="number" name="role_slots[<?= $index ?>][slot_count]" 
                                               value="<?= $slot['slot_count'] ?>" min="1" max="50" 
                                               class="slot-count-input" onchange="updateTotalParticipants()">
                                        <button type="button" class="counter-btn" onclick="incrementSlot(<?= $index ?>)">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="add-role-section">
                <div class="role-selector">
                    <select id="new-role-select">
                        <option value="">Rol seçin...</option>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?= $role['id'] ?>" 
                                    data-name="<?= htmlspecialchars($role['role_name']) ?>"
                                    data-icon="<?= htmlspecialchars($role['role_icon']) ?>"
                                    data-description="<?= htmlspecialchars($role['role_description']) ?>">
                                <?= htmlspecialchars($role['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="add-role-btn" class="btn-add-role" onclick="addRoleSlot()">
                        <i class="fas fa-plus"></i>
                        Rol Ekle
                    </button>
                </div>
                
                <?php if (empty($available_roles)): ?>
                    <div class="no-roles-notice">
                        <i class="fas fa-info-circle"></i>
                        Henüz rol tanımlanmamış. <a href="roles/create.php">Buradan yeni rol oluşturabilirsiniz.</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Özet Panel -->
            <div class="summary-panel">
                <h4><i class="fas fa-chart-bar"></i> Etkinlik Özeti</h4>
                <div class="summary-stats">
                    <div class="stat-item">
                        <span class="stat-label">Toplam Rol:</span>
                        <span class="stat-value" id="total-roles">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Toplam Slot:</span>
                        <span class="stat-value" id="total-slots">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Durum:</span>
                        <span class="stat-value" id="config-status">⚠️ Eksik</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ⚙️ Bölüm 4: Son Ayarlar -->
        <div class="form-section">
            <h3><i class="fas fa-cogs"></i> Son Ayarlar</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="registration_deadline">Katılım Son Tarihi</label>
                    <input type="datetime-local" id="registration_deadline" name="registration_deadline"
                           value="<?= $edit_mode && !empty($event_data['registration_deadline']) ? date('Y-m-d\TH:i', strtotime($event_data['registration_deadline'])) : '' ?>">
                    <small>Bu tarihten sonra katılım başvuruları kabul edilmez</small>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_approve" value="1" 
                                   <?= ($edit_mode && !empty($event_data['auto_approve'])) ? 'checked' : '' ?>>
                            <span class="checkmark"></span>
                            Otomatik Onay
                        </label>
                        <small>Katılım başvuruları otomatik olarak onaylanır</small>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="event_notes">Ek Notlar</label>
                <textarea id="event_notes" name="event_notes" rows="3"
                          placeholder="Organizatör notları, özel talimatlar veya ek bilgiler..."><?= $edit_mode ? htmlspecialchars($event_data['event_notes'] ?? '') : '' ?></textarea>
                <small>Sadece organize eden kişi tarafından görülen notlar</small>
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

<script>
// Global değişkenler
let roleSlotIndex = <?= $edit_mode ? count($existing_role_slots) : 0 ?>;
const availableRoles = <?= json_encode($available_roles) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Thumbnail önizleme
    const thumbnailInput = document.getElementById('event_thumbnail');
    if (thumbnailInput) {
        thumbnailInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    showThumbnailPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Toplam katılımcı sayısını güncelle
    updateTotalParticipants();
    
    // Form validasyonu
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    // Otomatik taslak kaydetme (5 dakikada bir)
    if (<?= $edit_mode ? 'true' : 'false' ?>) {
        setInterval(autoSaveDraft, 5 * 60 * 1000);
    }
});

// Thumbnail önizleme fonksiyonu
function showThumbnailPreview(src) {
    const placeholder = document.getElementById('preview-placeholder');
    const existingImage = document.getElementById('preview-image');
    const previewContainer = document.querySelector('.thumbnail-preview');
    
    if (placeholder) {
        placeholder.style.display = 'none';
    }
    
    if (existingImage) {
        existingImage.src = src;
    } else {
        const img = document.createElement('img');
        img.id = 'preview-image';
        img.src = src;
        img.alt = 'Thumbnail önizlemesi';
        
        const overlay = document.createElement('div');
        overlay.className = 'preview-overlay';
        overlay.innerHTML = '<span>Yeni Görsel</span>';
        
        previewContainer.innerHTML = '';
        previewContainer.appendChild(img);
        previewContainer.appendChild(overlay);
    }
}

// Rol slotu ekleme
function addRoleSlot() {
    const select = document.getElementById('new-role-select');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption.value) {
        alert('Lütfen bir rol seçin.');
        return;
    }
    
    const roleId = selectedOption.value;
    const roleName = selectedOption.dataset.name;
    const roleIcon = selectedOption.dataset.icon;
    const roleDescription = selectedOption.dataset.description;
    
    // Aynı rol zaten eklenmiş mi kontrol et
    const existingSlots = document.querySelectorAll(`input[name*="[role_id]"][value="${roleId}"]`);
    if (existingSlots.length > 0) {
        alert('Bu rol zaten eklenmiş.');
        return;
    }
    
    const container = document.getElementById('role-slots-container');
    const roleSlotHtml = `
        <div class="role-slot-card" data-index="${roleSlotIndex}">
            <div class="role-slot-header">
                <div class="role-info">
                    <i class="${roleIcon}"></i>
                    <span class="role-name">${roleName}</span>
                </div>
                <button type="button" class="btn-remove-slot" onclick="removeRoleSlot(${roleSlotIndex})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="role-slot-content">
                <input type="hidden" name="role_slots[${roleSlotIndex}][role_id]" value="${roleId}">
                <div class="slot-counter">
                    <label>Slot Sayısı:</label>
                    <div class="counter-controls">
                        <button type="button" class="counter-btn" onclick="decrementSlot(${roleSlotIndex})">-</button>
                        <input type="number" name="role_slots[${roleSlotIndex}][slot_count]" 
                               value="1" min="1" max="50" 
                               class="slot-count-input" onchange="updateTotalParticipants()">
                        <button type="button" class="counter-btn" onclick="incrementSlot(${roleSlotIndex})">+</button>
                    </div>
                </div>
                <div class="role-description">
                    <i class="fas fa-info-circle"></i>
                    <span>${roleDescription}</span>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', roleSlotHtml);
    
    // Seçimi temizle
    select.selectedIndex = 0;
    
    // Animasyon
    const newCard = container.lastElementChild;
    newCard.style.opacity = '0';
    newCard.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        newCard.style.transition = 'all 0.3s ease';
        newCard.style.opacity = '1';
        newCard.style.transform = 'translateY(0)';
    }, 10);
    
    roleSlotIndex++;
    updateTotalParticipants();
}

// Rol slotu kaldırma
function removeRoleSlot(index) {
    const card = document.querySelector(`[data-index="${index}"]`);
    if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            card.remove();
            updateTotalParticipants();
        }, 300);
    }
}

// Slot sayısını artırma
function incrementSlot(index) {
    const input = document.querySelector(`[name="role_slots[${index}][slot_count]"]`);
    if (input) {
        const currentValue = parseInt(input.value) || 1;
        if (currentValue < 50) {
            input.value = currentValue + 1;
            updateTotalParticipants();
        }
    }
}

// Slot sayısını azaltma
function decrementSlot(index) {
    const input = document.querySelector(`[name="role_slots[${index}][slot_count]"]`);
    if (input) {
        const currentValue = parseInt(input.value) || 1;
        if (currentValue > 1) {
            input.value = currentValue - 1;
            updateTotalParticipants();
        }
    }
}

// Toplam katılımcı sayısını güncelleme
function updateTotalParticipants() {
    const slotInputs = document.querySelectorAll('.slot-count-input');
    let totalSlots = 0;
    let totalRoles = slotInputs.length;
    
    slotInputs.forEach(input => {
        totalSlots += parseInt(input.value) || 0;
    });
    
    document.getElementById('total-count').textContent = totalSlots;
    document.getElementById('total-roles').textContent = totalRoles;
    document.getElementById('total-slots').textContent = totalSlots;
    
    // Durum güncelleme
    const statusElement = document.getElementById('config-status');
    if (totalRoles === 0) {
        statusElement.innerHTML = '⚠️ Rol Gerekli';
        statusElement.className = 'stat-value status-warning';
    } else if (totalSlots === 0) {
        statusElement.innerHTML = '⚠️ Slot Gerekli';
        statusElement.className = 'stat-value status-warning';
    } else {
        statusElement.innerHTML = '✅ Hazır';
        statusElement.className = 'stat-value status-ready';
    }
}

// Form validasyonu
function validateForm() {
    const title = document.getElementById('event_title').value.trim();
    const description = document.getElementById('event_description').value.trim();
    const date = document.getElementById('event_date').value;
    const roleSlots = document.querySelectorAll('.role-slot-card');
    
    if (title.length < 5) {
        alert('Etkinlik adı en az 5 karakter olmalıdır.');
        document.getElementById('event_title').focus();
        return false;
    }
    
    if (description.length < 20) {
        alert('Etkinlik açıklaması en az 20 karakter olmalıdır.');
        document.getElementById('event_description').focus();
        return false;
    }
    
    if (!date) {
        alert('Etkinlik tarihi gereklidir.');
        document.getElementById('event_date').focus();
        return false;
    }
    
    const eventDate = new Date(date);
    const now = new Date();
    if (eventDate <= now) {
        alert('Etkinlik tarihi gelecekte olmalıdır.');
        document.getElementById('event_date').focus();
        return false;
    }
    
    if (roleSlots.length === 0) {
        alert('En az bir rol slotu eklemelisiniz.');
        return false;
    }
    
    return true;
}

// Otomatik taslak kaydetme
function autoSaveDraft() {
    if (!validateForm()) {
        return;
    }
    
    const formData = new FormData(document.getElementById('eventForm'));
    formData.set('action', 'auto_draft');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log('Taslak otomatik kaydedildi');
        showNotification('Taslak otomatik kaydedildi', 'info');
    })
    .catch(error => {
        console.error('Otomatik kaydetme hatası:', error);
    });
}

// Bildirim gösterme
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--card-bg);
        color: var(--lighter-grey);
        border: 1px solid var(--border-1);
        border-radius: 8px;
        padding: 1rem;
        z-index: 10000;
        max-width: 300px;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;

    if (type === 'success') {
        notification.style.borderLeftColor = 'var(--turquase)';
        notification.style.borderLeftWidth = '4px';
    } else if (type === 'error') {
        notification.style.borderLeftColor = 'var(--red)';
        notification.style.borderLeftWidth = '4px';
    } else if (type === 'info') {
        notification.style.borderLeftColor = 'var(--gold)';
        notification.style.borderLeftWidth = '4px';
    }

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Klavye kısayolları
document.addEventListener('keydown', function(e) {
    // Ctrl+S ile kaydet
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.querySelector('button[name="action"][value="save"]').click();
    }
    
    // Ctrl+D ile taslak kaydet
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        document.querySelector('button[name="action"][value="draft"]').click();
    }
});

// CSS animasyonları için style ekleme
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .status-warning { color: var(--gold); }
    .status-ready { color: var(--turquase); }
`;
document.head.appendChild(style);
</script>

<?php
events_layout_end();
include BASE_PATH . '/src/includes/footer.php';
?>