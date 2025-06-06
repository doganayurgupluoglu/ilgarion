<?php
// /events/loadouts/actions/save_loadout.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// JSON response function
function json_response($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Session kontrolü
check_user_session_validity();

// Kullanıcı giriş ve onay kontrolü
if (!is_user_approved()) {
    json_response(false, "Bu işlem için giriş yapmalı ve hesabınız onaylanmış olmalıdır.");
}

// Kullanıcı yetkisi kontrolü
if (!has_permission($pdo, 'loadout.manage_sets')) {
    json_response(false, "Teçhizat seti oluşturmak için yetkiniz bulunmuyor.");
}

// CSRF token kontrolü
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    json_response(false, "Güvenlik doğrulaması başarısız.");
}

// Rate limiting
if (!check_rate_limit('loadout_save', 10, 300)) { // 5 dakikada 10 deneme
    json_response(false, "Çok fazla deneme. Lütfen biraz bekleyin.");
}

$current_user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'create';

try {
    // Input validation
    $set_name = trim($_POST['set_name'] ?? '');
    $set_description = trim($_POST['set_description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'private';
    $loadout_items_json = $_POST['loadout_items'] ?? '{}';

    // Validate required fields
    if (empty($set_name)) {
        json_response(false, "Set ismi gereklidir.");
    }

    if (strlen($set_name) < 3 || strlen($set_name) > 255) {
        json_response(false, "Set ismi 3-255 karakter arasında olmalıdır.");
    }

    if (empty($set_description)) {
        json_response(false, "Set açıklaması gereklidir.");
    }

    if (strlen($set_description) < 10 || strlen($set_description) > 1000) {
        json_response(false, "Set açıklaması 10-1000 karakter arasında olmalıdır.");
    }

    if (!in_array($visibility, ['private', 'members_only', 'public', 'faction_only'])) {
        json_response(false, "Geçersiz görünürlük ayarı.");
    }

    // Parse loadout items
    $loadout_items = json_decode($loadout_items_json, true);
    if ($loadout_items === null && $loadout_items_json !== '{}') {
        json_response(false, "Teçhizat verileri geçersiz format.");
    }

    // File upload handling
    $uploaded_image_path = null;
    $is_update = ($action === 'update');
    $loadout_id = null;

    if ($is_update) {
        $loadout_id = (int)($_POST['loadout_id'] ?? 0);
        if (!$loadout_id) {
            json_response(false, "Geçersiz set ID'si.");
        }

        // Check ownership
        $stmt = $pdo->prepare("SELECT user_id, set_image_path FROM loadout_sets WHERE id = :id");
        $stmt->execute([':id' => $loadout_id]);
        $existing_set = $stmt->fetch();

        if (!$existing_set || $existing_set['user_id'] != $current_user_id) {
            json_response(false, "Bu seti düzenleme yetkiniz bulunmuyor.");
        }

        $uploaded_image_path = $existing_set['set_image_path']; // Keep existing image as default
    }

    // Handle file upload
    if (isset($_FILES['set_image']) && $_FILES['set_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleImageUpload($_FILES['set_image'], $current_user_id);
        if ($upload_result['success']) {
            $uploaded_image_path = $upload_result['path'];
        } else {
            json_response(false, $upload_result['message']);
        }
    } elseif (!$is_update) {
        // New loadout requires image
        json_response(false, "Set görseli gereklidir.");
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        if ($is_update) {
            // Update existing loadout
            $update_query = "
                UPDATE loadout_sets 
                SET set_name = :name, 
                    set_description = :description, 
                    visibility = :visibility,
                    " . ($uploaded_image_path !== $existing_set['set_image_path'] ? "set_image_path = :image_path," : "") . "
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND user_id = :user_id
            ";

            $update_params = [
                ':name' => $set_name,
                ':description' => $set_description,
                ':visibility' => $visibility,
                ':id' => $loadout_id,
                ':user_id' => $current_user_id
            ];

            if ($uploaded_image_path !== $existing_set['set_image_path']) {
                $update_params[':image_path'] = $uploaded_image_path;
            }

            $stmt = $pdo->prepare($update_query);
            $stmt->execute($update_params);

            // Delete existing items
            $delete_items_stmt = $pdo->prepare("DELETE FROM loadout_set_items WHERE loadout_set_id = :set_id");
            $delete_items_stmt->execute([':set_id' => $loadout_id]);

            // Audit log
            audit_log($pdo, $current_user_id, 'loadout_set_updated', 'loadout_set', $loadout_id, null, [
                'set_name' => $set_name,
                'visibility' => $visibility
            ]);

        } else {
            // Create new loadout
            $insert_query = "
                INSERT INTO loadout_sets (user_id, set_name, set_description, set_image_path, visibility, status, created_at, updated_at)
                VALUES (:user_id, :name, :description, :image_path, :visibility, 'draft', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ";

            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([
                ':user_id' => $current_user_id,
                ':name' => $set_name,
                ':description' => $set_description,
                ':image_path' => $uploaded_image_path,
                ':visibility' => $visibility
            ]);

            $loadout_id = $pdo->lastInsertId();

            // Audit log
            audit_log($pdo, $current_user_id, 'loadout_set_created', 'loadout_set', $loadout_id, null, [
                'set_name' => $set_name,
                'visibility' => $visibility
            ]);
        }

        // Insert/Update items
        if (!empty($loadout_items)) {
            $item_insert_query = "
                INSERT INTO loadout_set_items (
                    loadout_set_id, equipment_slot_id, item_name, item_api_uuid, 
                    item_type_api, item_manufacturer_api, item_notes
                ) VALUES (
                    :set_id, :slot_id, :item_name, :item_uuid, 
                    :item_type, :item_manufacturer, :item_notes
                )
            ";
            $item_stmt = $pdo->prepare($item_insert_query);

            foreach ($loadout_items as $slot_id => $item_data) {
                // Validate slot ID
                if (!is_numeric($slot_id) || $slot_id <= 0) {
                    continue;
                }

                // Validate slot exists
                $slot_check_stmt = $pdo->prepare("SELECT id FROM equipment_slots WHERE id = :slot_id AND is_standard = 1");
                $slot_check_stmt->execute([':slot_id' => $slot_id]);
                if (!$slot_check_stmt->fetchColumn()) {
                    continue; // Skip invalid slots
                }

                $item_stmt->execute([
                    ':set_id' => $loadout_id,
                    ':slot_id' => $slot_id,
                    ':item_name' => substr($item_data['item_name'] ?? '', 0, 255),
                    ':item_uuid' => substr($item_data['item_api_uuid'] ?? '', 0, 255),
                    ':item_type' => substr($item_data['item_type_api'] ?? '', 0, 100),
                    ':item_manufacturer' => substr($item_data['item_manufacturer_api'] ?? '', 0, 255),
                    ':item_notes' => substr($item_data['item_notes'] ?? '', 0, 500)
                ]);
            }
        }

        // Update set status to published if it has items
        if (!empty($loadout_items)) {
            $status_update_stmt = $pdo->prepare("UPDATE loadout_sets SET status = 'published' WHERE id = :id");
            $status_update_stmt->execute([':id' => $loadout_id]);
        }

        $pdo->commit();

        $message = $is_update ? 'Teçhizat seti başarıyla güncellendi.' : 'Teçhizat seti başarıyla oluşturuldu.';
        json_response(true, $message, [
            'loadout_id' => $loadout_id,
            'redirect' => '/events/loadouts/view.php?id=' . $loadout_id
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Loadout save transaction error: " . $e->getMessage());
        json_response(false, "Kaydetme sırasında bir veritabanı hatası oluştu.");
    }

} catch (Exception $e) {
    error_log("Loadout save error: " . $e->getMessage());
    json_response(false, "Kaydetme sırasında bir hata oluştu.");
}

/**
 * Handle image upload for loadout sets
 */
function handleImageUpload($file, $user_id) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Dosya yükleme hatası oluştu.'];
    }

    // Check file size (5MB max)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Dosya boyutu 5MB\'dan büyük olamaz.'];
    }

    // Check file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Sadece JPEG, PNG ve GIF dosyaları kabul edilir.'];
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    $filename = "loadout_user{$user_id}_{$timestamp}_{$random}.{$extension}";

    // Upload directory
    $upload_dir = BASE_PATH . '/public/uploads/loadouts/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Upload dizini oluşturulamadı.'];
        }
    }

    $upload_path = $upload_dir . $filename;
    $relative_path = 'uploads/loadouts/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Optional: Resize image if too large
        resizeImageIfNeeded($upload_path, 1200, 800);
        
        return ['success' => true, 'path' => $relative_path];
    } else {
        return ['success' => false, 'message' => 'Dosya yüklenemedi.'];
    }
}

/**
 * Resize image if it's too large
 */
function resizeImageIfNeeded($file_path, $max_width = 1200, $max_height = 800) {
    try {
        $image_info = getimagesize($file_path);
        if (!$image_info) return false;

        $width = $image_info[0];
        $height = $image_info[1];
        $mime_type = $image_info['mime'];

        // Check if resize is needed
        if ($width <= $max_width && $height <= $max_height) {
            return true;
        }

        // Calculate new dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);

        // Create image resource
        switch ($mime_type) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($file_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($file_path);
                break;
            default:
                return false;
        }

        if (!$source) return false;

        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG and GIF
        if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefill($new_image, 0, 0, $transparent);
        }

        // Resize
        imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // Save resized image
        switch ($mime_type) {
            case 'image/jpeg':
                imagejpeg($new_image, $file_path, 85);
                break;
            case 'image/png':
                imagepng($new_image, $file_path, 6);
                break;
            case 'image/gif':
                imagegif($new_image, $file_path);
                break;
        }

        // Clean up
        imagedestroy($source);
        imagedestroy($new_image);

        return true;

    } catch (Exception $e) {
        error_log("Image resize error: " . $e->getMessage());
        return false;
    }
}
?>