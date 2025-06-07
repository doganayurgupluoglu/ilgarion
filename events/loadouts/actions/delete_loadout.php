<?php
// /events/loadouts/actions/delete_loadout.php - Teçhizat seti silme

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';

try {
    // Session kontrolü
    check_user_session_validity();
    require_approved_user();

    // Method kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }

    // JSON input al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Geçersiz JSON verisi');
    }

    // CSRF kontrolü
    if (!verify_csrf_token($input['csrf_token'] ?? '')) {
        throw new Exception('Güvenlik hatası - CSRF token geçersiz');
    }

    $loadout_id = (int)($input['loadout_id'] ?? 0);
    $current_user_id = $_SESSION['user_id'];

    if ($loadout_id <= 0) {
        throw new Exception('Geçersiz set ID');
    }

    // Loadout set bilgilerini al ve ownership kontrolü yap
    $stmt = $pdo->prepare("
        SELECT id, user_id, set_name, set_image_path
        FROM loadout_sets 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $loadout_id]);
    $loadout_set = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loadout_set) {
        throw new Exception('Teçhizat seti bulunamadı');
    }

    // Yetki kontrolü - kendi seti olmalı veya admin olmalı
    if ($loadout_set['user_id'] != $current_user_id && !has_permission($pdo, 'admin.panel.access')) {
        throw new Exception('Bu seti silme yetkiniz bulunmuyor');
    }

    // Transaction başlat
    $pdo->beginTransaction();

    try {
        // İlişkili tabloları temizle
        
        // 1. Weapon attachments
        $stmt = $pdo->prepare("DELETE FROM loadout_weapon_attachments WHERE loadout_set_id = :set_id");
        $stmt->execute([':set_id' => $loadout_id]);
        
        // 2. Set items
        $stmt = $pdo->prepare("DELETE FROM loadout_set_items WHERE loadout_set_id = :set_id");
        $stmt->execute([':set_id' => $loadout_id]);
        
        // 3. Visibility roles (eğer varsa)
        $stmt = $pdo->prepare("DELETE FROM loadout_set_visibility_roles WHERE set_id = :set_id");
        $stmt->execute([':set_id' => $loadout_id]);
        
        // 4. Ana loadout set kaydını sil
        $stmt = $pdo->prepare("DELETE FROM loadout_sets WHERE id = :id");
        $stmt->execute([':id' => $loadout_id]);
        
        // 5. Görsel dosyasını sil (eğer varsa)
        if (!empty($loadout_set['set_image_path'])) {
            $image_path = BASE_PATH . '/' . $loadout_set['set_image_path'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '"' . $loadout_set['set_name'] . '" teçhizat seti başarıyla silindi'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Loadout delete error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>