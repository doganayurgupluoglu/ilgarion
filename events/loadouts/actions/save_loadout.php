<?php
// /events/loadouts/actions/save_loadout.php - Düzeltilmiş versiyon

// Error reporting için
error_reporting(E_ALL);
ini_set('display_errors', 0); // JSON response bozulmasın diye
ini_set('log_errors', 1);

// JSON response header
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Debug logging function
function debug_log($message, $data = null) {
    $log_message = "[LOADOUT_SAVE] " . $message;
    if ($data !== null) {
        $log_message .= " Data: " . json_encode($data);
    }
    error_log($log_message);
}

debug_log("Save loadout request started", $_POST);

try {
    // Session kontrolü
    check_user_session_validity();
    require_approved_user();

    // Method kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }

    // CSRF kontrolü
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        throw new Exception('Güvenlik hatası - CSRF token geçersiz');
    }

    // Yetki kontrolü
    if (!has_permission($pdo, 'loadout.manage_sets')) {
        throw new Exception('Bu işlem için yetkiniz bulunmuyor');
    }

    $current_user_id = $_SESSION['user_id'];
    $action = $_POST['form_action'] ?? ''; // 'action' yerine 'form_action' kullan

    debug_log("Action: $action, User ID: $current_user_id");

    // PDO transaction başlat
    $pdo->beginTransaction();
    
    if ($action === 'create') {
        $result = createLoadoutSet($pdo, $current_user_id, $_POST, $_FILES);
    } elseif ($action === 'update') {
        $result = updateLoadoutSet($pdo, $current_user_id, $_POST, $_FILES);
    } else {
        throw new Exception('Geçersiz işlem: ' . $action);
    }
    
    $pdo->commit();
    debug_log("Transaction committed successfully", $result);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $error_message = $e->getMessage();
    debug_log("Error occurred: " . $error_message);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Kaydetme sırasında bir hata oluştu: ' . $error_message
    ]);
}

function createLoadoutSet($pdo, $user_id, $post_data, $files) {
    debug_log("Creating new loadout set");
    
    // Temel validasyon
    $set_name = trim($post_data['set_name'] ?? '');
    $set_description = trim($post_data['set_description'] ?? '');
    $visibility = $post_data['visibility'] ?? 'private';
    
    if (empty($set_name)) {
        throw new Exception('Set adı gereklidir');
    }
    
    if (empty($set_description)) {
        throw new Exception('Set açıklaması gereklidir');
    }
    
    // Visibility validation
    $valid_visibility = ['private', 'members_only', 'public', 'faction_only'];
    if (!in_array($visibility, $valid_visibility)) {
        $visibility = 'private';
    }
    
    debug_log("Basic validation passed", [
        'set_name' => $set_name,
        'set_description' => $set_description,
        'visibility' => $visibility
    ]);
    
    // Görsel yükleme
    $image_path = null;
    if (!empty($files['set_image']['tmp_name'])) {
        $image_path = uploadSetImage($files['set_image'], $user_id);
        debug_log("Image uploaded successfully", ['path' => $image_path]);
    }
    
    // Loadout set oluştur
    $stmt = $pdo->prepare("
        INSERT INTO loadout_sets (user_id, set_name, set_description, set_image_path, visibility, status)
        VALUES (:user_id, :set_name, :set_description, :image_path, :visibility, 'published')
    ");
    
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':set_name' => $set_name,
        ':set_description' => $set_description,
        ':image_path' => $image_path,
        ':visibility' => $visibility
    ]);
    
    if (!$result) {
        throw new Exception('Loadout set kaydedilemedi');
    }
    
    $loadout_set_id = $pdo->lastInsertId();
    debug_log("Loadout set created with ID: $loadout_set_id");
    
    // Items kaydet
    if (!empty($post_data['loadout_items'])) {
        saveLoadoutItems($pdo, $loadout_set_id, $post_data['loadout_items']);
        debug_log("Items saved successfully");
    }
    
    // Weapon attachments kaydet
    if (!empty($post_data['weapon_attachments'])) {
        saveWeaponAttachments($pdo, $loadout_set_id, $post_data['weapon_attachments']);
        debug_log("Weapon attachments saved successfully");
    }
    
    return [
        'success' => true,
        'message' => 'Teçhizat seti başarıyla oluşturuldu',
        'redirect' => '/events/loadouts/view.php?id=' . $loadout_set_id
    ];
}

function updateLoadoutSet($pdo, $user_id, $post_data, $files) {
    debug_log("Updating existing loadout set");
    
    $loadout_id = (int)($post_data['loadout_id'] ?? 0);
    
    if ($loadout_id <= 0) {
        throw new Exception('Geçersiz set ID');
    }
    
    // Ownership kontrolü
    $stmt = $pdo->prepare("SELECT id, set_image_path FROM loadout_sets WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $loadout_id, ':user_id' => $user_id]);
    $existing_set = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_set) {
        throw new Exception('Set bulunamadı veya düzenleme yetkiniz yok');
    }
    
    debug_log("Ownership verified for loadout ID: $loadout_id");
    
    // Temel validasyon
    $set_name = trim($post_data['set_name'] ?? '');
    $set_description = trim($post_data['set_description'] ?? '');
    $visibility = $post_data['visibility'] ?? 'private';
    
    if (empty($set_name)) {
        throw new Exception('Set adı gereklidir');
    }
    
    if (empty($set_description)) {
        throw new Exception('Set açıklaması gereklidir');
    }
    
    // Visibility validation
    $valid_visibility = ['private', 'members_only', 'public', 'faction_only'];
    if (!in_array($visibility, $valid_visibility)) {
        $visibility = 'private';
    }
    
    // Görsel güncelleme
    $image_path = $existing_set['set_image_path'];
    if (!empty($files['set_image']['tmp_name'])) {
        $new_image_path = uploadSetImage($files['set_image'], $user_id);
        
        // Eski görseli sil
        if ($image_path) {
            $old_image_full_path = BASE_PATH . '/' . $image_path;
            if (file_exists($old_image_full_path)) {
                unlink($old_image_full_path);
                debug_log("Old image deleted: $old_image_full_path");
            }
        }
        
        $image_path = $new_image_path;
        debug_log("New image uploaded: $image_path");
    }
    
    // Set bilgilerini güncelle
    $stmt = $pdo->prepare("
        UPDATE loadout_sets 
        SET set_name = :set_name, set_description = :set_description, 
            set_image_path = :image_path, visibility = :visibility, updated_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");
    
    $result = $stmt->execute([
        ':set_name' => $set_name,
        ':set_description' => $set_description,
        ':image_path' => $image_path,
        ':visibility' => $visibility,
        ':id' => $loadout_id,
        ':user_id' => $user_id
    ]);
    
    if (!$result) {
        throw new Exception('Loadout set güncellenemedi');
    }
    
    debug_log("Loadout set updated successfully");
    
    // Mevcut itemleri sil
    $stmt = $pdo->prepare("DELETE FROM loadout_set_items WHERE loadout_set_id = :set_id");
    $stmt->execute([':set_id' => $loadout_id]);
    debug_log("Existing items deleted");
    
    // Mevcut weapon attachments'ları sil
    $stmt = $pdo->prepare("DELETE FROM loadout_weapon_attachments WHERE loadout_set_id = :set_id");
    $stmt->execute([':set_id' => $loadout_id]);
    debug_log("Existing weapon attachments deleted");
    
    // Yeni itemleri kaydet
    if (!empty($post_data['loadout_items'])) {
        saveLoadoutItems($pdo, $loadout_id, $post_data['loadout_items']);
        debug_log("New items saved");
    }
    
    // Yeni weapon attachments kaydet
    if (!empty($post_data['weapon_attachments'])) {
        saveWeaponAttachments($pdo, $loadout_id, $post_data['weapon_attachments']);
        debug_log("New weapon attachments saved");
    }
    
    return [
        'success' => true,
        'message' => 'Teçhizat seti başarıyla güncellendi',
        'redirect' => '/events/loadouts/view.php?id=' . $loadout_id
    ];
}

function saveLoadoutItems($pdo, $loadout_set_id, $items_json) {
    debug_log("Saving loadout items", ['loadout_set_id' => $loadout_set_id, 'items_json' => $items_json]);
    
    if (empty($items_json)) {
        debug_log("No items to save");
        return;
    }
    
    $items_data = json_decode($items_json, true);
    
    if (!is_array($items_data)) {
        throw new Exception('Geçersiz items verisi - JSON decode failed');
    }
    
    if (empty($items_data)) {
        debug_log("Items data is empty array");
        return;
    }
    
    $saved_count = 0;
    foreach ($items_data as $slot_id => $item_data) {
        if (empty($item_data['item_name'])) {
            debug_log("Skipping empty item for slot: $slot_id");
            continue;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO loadout_set_items 
            (loadout_set_id, equipment_slot_id, item_name, item_api_uuid, item_type_api, item_manufacturer_api, item_notes)
            VALUES (:set_id, :slot_id, :item_name, :uuid, :type, :manufacturer, :notes)
        ");
        
        $result = $stmt->execute([
            ':set_id' => $loadout_set_id,
            ':slot_id' => (int)$slot_id,
            ':item_name' => $item_data['item_name'] ?? '',
            ':uuid' => $item_data['item_api_uuid'] ?? null,
            ':type' => $item_data['item_type_api'] ?? null,
            ':manufacturer' => $item_data['item_manufacturer_api'] ?? null,
            ':notes' => $item_data['item_notes'] ?? null
        ]);
        
        if ($result) {
            $saved_count++;
            debug_log("Saved item for slot $slot_id: " . ($item_data['item_name'] ?? 'Unknown'));
        } else {
            debug_log("Failed to save item for slot $slot_id");
        }
    }
    
    debug_log("Saved $saved_count items total");
}

function saveWeaponAttachments($pdo, $loadout_set_id, $attachments_json) {
    debug_log("Saving weapon attachments", ['loadout_set_id' => $loadout_set_id, 'attachments_json' => $attachments_json]);
    
    if (empty($attachments_json)) {
        debug_log("No attachments to save");
        return;
    }
    
    $attachments_data = json_decode($attachments_json, true);
    
    if (!is_array($attachments_data)) {
        throw new Exception('Geçersiz attachments verisi - JSON decode failed');
    }
    
    if (empty($attachments_data)) {
        debug_log("Attachments data is empty array");
        return;
    }
    
    // Weapon attachment tablosunun var olup olmadığını kontrol et
    try {
        $pdo->query("SELECT 1 FROM loadout_weapon_attachments LIMIT 1");
    } catch (PDOException $e) {
        debug_log("loadout_weapon_attachments table does not exist, skipping attachment save");
        return;
    }
    
    $saved_count = 0;
    foreach ($attachments_data as $parent_slot_id => $attachment_slots) {
        if (!is_array($attachment_slots)) {
            debug_log("Invalid attachment slots for parent $parent_slot_id");
            continue;
        }
        
        foreach ($attachment_slots as $attachment_slot_id => $attachment_data) {
            if (empty($attachment_data['attachment_item_name'])) {
                debug_log("Skipping empty attachment for parent $parent_slot_id, slot $attachment_slot_id");
                continue;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO loadout_weapon_attachments 
                (loadout_set_id, parent_equipment_slot_id, attachment_slot_id, attachment_item_name, 
                 attachment_item_uuid, attachment_item_type, attachment_item_manufacturer, attachment_notes)
                VALUES (:set_id, :parent_slot_id, :attachment_slot_id, :item_name, :uuid, :type, :manufacturer, :notes)
            ");
            
            $result = $stmt->execute([
                ':set_id' => $loadout_set_id,
                ':parent_slot_id' => (int)$parent_slot_id,
                ':attachment_slot_id' => (int)$attachment_slot_id,
                ':item_name' => $attachment_data['attachment_item_name'] ?? '',
                ':uuid' => $attachment_data['attachment_item_uuid'] ?? null,
                ':type' => $attachment_data['attachment_item_type'] ?? null,
                ':manufacturer' => $attachment_data['attachment_item_manufacturer'] ?? null,
                ':notes' => $attachment_data['attachment_notes'] ?? null
            ]);
            
            if ($result) {
                $saved_count++;
                debug_log("Saved attachment for parent $parent_slot_id, slot $attachment_slot_id: " . ($attachment_data['attachment_item_name'] ?? 'Unknown'));
            } else {
                debug_log("Failed to save attachment for parent $parent_slot_id, slot $attachment_slot_id");
            }
        }
    }
    
    debug_log("Saved $saved_count attachments total");
}

function uploadSetImage($file, $user_id) {
    debug_log("Starting image upload for user: $user_id");
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Dosya yükleme hatası: ' . $file['error']);
    }
    
    // Dosya boyutu kontrolü (5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Dosya boyutu 5MB\'den küçük olmalıdır');
    }
    
    // Dosya tipi kontrolü
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Sadece JPG, JPEG ve PNG dosyaları kabul edilir. Dosya tipi: ' . $mime_type);
    }
    
    debug_log("File validation passed", [
        'size' => $file['size'],
        'mime_type' => $mime_type,
        'original_name' => $file['name']
    ]);
    
    // Unique dosya adı oluştur
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    $filename = "loadout_user{$user_id}_{$timestamp}_{$random}.{$extension}";
    
    // Upload klasörünü BASE_PATH kullanarak oluştur
    $upload_dir = BASE_PATH . '/uploads/loadouts/';
    
    // Klasörü oluştur
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Upload klasörü oluşturulamadı: ' . $upload_dir);
        }
        debug_log("Created upload directory: $upload_dir");
    }
    
    $upload_path = $upload_dir . $filename;
    
    debug_log("Attempting to move uploaded file", [
        'from' => $file['tmp_name'],
        'to' => $upload_path,
        'filename' => $filename
    ]);
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Dosya yükleme başarısız - move_uploaded_file failed');
    }
    
    // Web'den erişilebilir relative path döndür
    $relative_path = 'uploads/loadouts/' . $filename;
    debug_log("File uploaded successfully", ['relative_path' => $relative_path, 'full_path' => $upload_path]);
    
    return $relative_path;
}
?>