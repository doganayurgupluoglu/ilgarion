<?php
// /events/loadouts/actions/save_loadout.php - Weapon Attachments ile güncellenmiş

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();
require_approved_user();

// CSRF kontrolü
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Güvenlik hatası']);
    exit;
}

// Yetki kontrolü
if (!has_permission($pdo, 'loadout.manage_sets')) {
    echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmuyor']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $pdo->beginTransaction();
    
    if ($action === 'create') {
        $result = createLoadoutSet($pdo, $current_user_id, $_POST, $_FILES);
    } elseif ($action === 'update') {
        $result = updateLoadoutSet($pdo, $current_user_id, $_POST, $_FILES);
    } else {
        throw new Exception('Geçersiz işlem');
    }
    
    $pdo->commit();
    echo json_encode($result);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Loadout save error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Kaydetme sırasında bir hata oluştu: ' . $e->getMessage()
    ]);
}

function createLoadoutSet($pdo, $user_id, $post_data, $files) {
    // Temel validasyon
    $set_name = trim($post_data['set_name'] ?? '');
    $set_description = trim($post_data['set_description'] ?? '');
    $visibility = $post_data['visibility'] ?? 'private';
    
    if (empty($set_name) || empty($set_description)) {
        throw new Exception('Set adı ve açıklama gereklidir');
    }
    
    // Görsel yükleme
    $image_path = null;
    if (!empty($files['set_image']['tmp_name'])) {
        $image_path = uploadSetImage($files['set_image'], $user_id);
    }
    
    // Loadout set oluştur
    $stmt = $pdo->prepare("
        INSERT INTO loadout_sets (user_id, set_name, set_description, set_image_path, visibility, status)
        VALUES (:user_id, :set_name, :set_description, :image_path, :visibility, 'published')
    ");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':set_name' => $set_name,
        ':set_description' => $set_description,
        ':image_path' => $image_path,
        ':visibility' => $visibility
    ]);
    
    $loadout_set_id = $pdo->lastInsertId();
    
    // Items kaydet
    if (!empty($post_data['loadout_items'])) {
        saveLoadoutItems($pdo, $loadout_set_id, $post_data['loadout_items']);
    }
    
    // Weapon attachments kaydet
    if (!empty($post_data['weapon_attachments'])) {
        saveWeaponAttachments($pdo, $loadout_set_id, $post_data['weapon_attachments']);
    }
    
    return [
        'success' => true,
        'message' => 'Teçhizat seti başarıyla oluşturuldu',
        'redirect' => '/events/loadouts/view.php?id=' . $loadout_set_id
    ];
}

function updateLoadoutSet($pdo, $user_id, $post_data, $files) {
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
    
    // Temel validasyon
    $set_name = trim($post_data['set_name'] ?? '');
    $set_description = trim($post_data['set_description'] ?? '');
    $visibility = $post_data['visibility'] ?? 'private';
    
    if (empty($set_name) || empty($set_description)) {
        throw new Exception('Set adı ve açıklama gereklidir');
    }
    
    // Görsel güncelleme
    $image_path = $existing_set['set_image_path'];
    if (!empty($files['set_image']['tmp_name'])) {
        $new_image_path = uploadSetImage($files['set_image'], $user_id);
        
        // Eski görseli sil - düzeltilmiş path
        if ($image_path) {
            $web_root = dirname(dirname(dirname(__DIR__))); // 3 seviye yukarı
            $old_image_full_path = $web_root . '/' . $image_path;
            if (file_exists($old_image_full_path)) {
                unlink($old_image_full_path);
            }
        }
        
        $image_path = $new_image_path;
    }
    
    // Set bilgilerini güncelle
    $stmt = $pdo->prepare("
        UPDATE loadout_sets 
        SET set_name = :set_name, set_description = :set_description, 
            set_image_path = :image_path, visibility = :visibility, updated_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':set_name' => $set_name,
        ':set_description' => $set_description,
        ':image_path' => $image_path,
        ':visibility' => $visibility,
        ':id' => $loadout_id,
        ':user_id' => $user_id
    ]);
    
    // Mevcut itemleri sil
    $stmt = $pdo->prepare("DELETE FROM loadout_set_items WHERE loadout_set_id = :set_id");
    $stmt->execute([':set_id' => $loadout_id]);
    
    // Mevcut weapon attachments'ları sil
    $stmt = $pdo->prepare("DELETE FROM loadout_weapon_attachments WHERE loadout_set_id = :set_id");
    $stmt->execute([':set_id' => $loadout_id]);
    
    // Yeni itemleri kaydet
    if (!empty($post_data['loadout_items'])) {
        saveLoadoutItems($pdo, $loadout_id, $post_data['loadout_items']);
    }
    
    // Yeni weapon attachments kaydet
    if (!empty($post_data['weapon_attachments'])) {
        saveWeaponAttachments($pdo, $loadout_id, $post_data['weapon_attachments']);
    }
    
    return [
        'success' => true,
        'message' => 'Teçhizat seti başarıyla güncellendi',
        'redirect' => '/events/loadouts/view.php?id=' . $loadout_id
    ];
}

function saveLoadoutItems($pdo, $loadout_set_id, $items_json) {
    $items_data = json_decode($items_json, true);
    
    if (!is_array($items_data)) {
        throw new Exception('Geçersiz items verisi');
    }
    
    foreach ($items_data as $slot_id => $item_data) {
        if (empty($item_data['item_name'])) continue;
        
        $stmt = $pdo->prepare("
            INSERT INTO loadout_set_items 
            (loadout_set_id, equipment_slot_id, item_name, item_api_uuid, item_type_api, item_manufacturer_api, item_notes)
            VALUES (:set_id, :slot_id, :item_name, :uuid, :type, :manufacturer, :notes)
        ");
        
        $stmt->execute([
            ':set_id' => $loadout_set_id,
            ':slot_id' => (int)$slot_id,
            ':item_name' => $item_data['item_name'] ?? '',
            ':uuid' => $item_data['item_api_uuid'] ?? null,
            ':type' => $item_data['item_type_api'] ?? null,
            ':manufacturer' => $item_data['item_manufacturer_api'] ?? null,
            ':notes' => $item_data['item_notes'] ?? null
        ]);
    }
}

function saveWeaponAttachments($pdo, $loadout_set_id, $attachments_json) {
    $attachments_data = json_decode($attachments_json, true);
    
    if (!is_array($attachments_data)) {
        throw new Exception('Geçersiz attachments verisi');
    }
    
    foreach ($attachments_data as $parent_slot_id => $attachment_slots) {
        if (!is_array($attachment_slots)) continue;
        
        foreach ($attachment_slots as $attachment_slot_id => $attachment_data) {
            if (empty($attachment_data['attachment_item_name'])) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO loadout_weapon_attachments 
                (loadout_set_id, parent_equipment_slot_id, attachment_slot_id, attachment_item_name, 
                 attachment_item_uuid, attachment_item_type, attachment_item_manufacturer, attachment_notes)
                VALUES (:set_id, :parent_slot_id, :attachment_slot_id, :item_name, :uuid, :type, :manufacturer, :notes)
            ");
            
            $stmt->execute([
                ':set_id' => $loadout_set_id,
                ':parent_slot_id' => (int)$parent_slot_id,
                ':attachment_slot_id' => (int)$attachment_slot_id,
                ':item_name' => $attachment_data['attachment_item_name'] ?? '',
                ':uuid' => $attachment_data['attachment_item_uuid'] ?? null,
                ':type' => $attachment_data['attachment_item_type'] ?? null,
                ':manufacturer' => $attachment_data['attachment_item_manufacturer'] ?? null,
                ':notes' => $attachment_data['attachment_notes'] ?? null
            ]);
        }
    }
}

function uploadSetImage($file, $user_id) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Dosya yükleme hatası');
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
        throw new Exception('Sadece JPG, JPEG ve PNG dosyaları kabul edilir');
    }
    
    // Unique dosya adı oluştur
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    $filename = "loadout_user{$user_id}_{$timestamp}_{$random}.{$extension}";
    
    // Upload klasörünü mevcut dizin yapısına göre düzenle
    // BASE_PATH'den çıkarak web root'a ulaş
    $web_root = dirname(dirname(dirname(__DIR__))); // 3 seviye yukarı
    $upload_dir = $web_root . '/uploads/loadouts/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_path = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Dosya yükleme başarısız');
    }
    
    // Web'den erişilebilir relative path döndür
    return 'uploads/loadouts/' . $filename;
}
?>