<?php
// /events/loadouts/api/get_attachment_slots.php - Weapon attachment slots API

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Authentication check - BASITLEŞTIRILMIŞ
try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Authentication required - no user_id in session']);
        exit;
    }
    
    // Basit user check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Auth check failed: ' . $e->getMessage()]);
    exit;
}

// Method check
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get weapon attachment slots for specific parent slot
    $parent_slot_id = (int)($_GET['parent_slot_id'] ?? 0);
    
    try {
        if ($parent_slot_id > 0) {
            // Specific parent slot için attachment slotları
            $stmt = $pdo->prepare("
                SELECT was.*, es.slot_name as parent_slot_name
                FROM weapon_attachment_slots was
                LEFT JOIN equipment_slots es ON es.id = :parent_slot_id
                WHERE FIND_IN_SET(:parent_slot_id, was.parent_weapon_slots) > 0
                AND was.is_active = 1
                ORDER BY was.display_order ASC
            ");
            $stmt->execute([':parent_slot_id' => $parent_slot_id]);
        } else {
            // Tüm attachment slotları
            $stmt = $pdo->prepare("
                SELECT * FROM weapon_attachment_slots 
                WHERE is_active = 1 
                ORDER BY display_order ASC
            ");
            $stmt->execute();
        }
        
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parent weapon slots'ı array'e çevir
        foreach ($slots as &$slot) {
            $slot['parent_weapon_slots_array'] = array_map('intval', explode(',', $slot['parent_weapon_slots']));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $slots,
            'parent_slot_id' => $parent_slot_id
        ]);
        
    } catch (PDOException $e) {
        error_log("Get attachment slots error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred'
        ]);
    }
    
} elseif ($method === 'POST') {
    // Get existing attachments for a loadout set
    $input = json_decode(file_get_contents('php://input'), true);
    $loadout_set_id = (int)($input['loadout_set_id'] ?? 0);
    $parent_slot_id = (int)($input['parent_slot_id'] ?? 0);
    
    if ($loadout_set_id <= 0) {
        echo json_encode(['error' => 'Invalid loadout set ID']);
        exit;
    }
    
    try {
        // User ownership check
        $stmt = $pdo->prepare("
            SELECT id FROM loadout_sets 
            WHERE id = :set_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':set_id' => $loadout_set_id,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Unauthorized or loadout not found']);
            exit;
        }
        
        // Get existing attachments
        $query = "
            SELECT 
                lwa.*,
                was.slot_name,
                was.slot_type,
                was.icon_class,
                es.slot_name as parent_slot_name
            FROM loadout_weapon_attachments lwa
            JOIN weapon_attachment_slots was ON lwa.attachment_slot_id = was.id
            JOIN equipment_slots es ON lwa.parent_equipment_slot_id = es.id
            WHERE lwa.loadout_set_id = :set_id
        ";
        
        $params = [':set_id' => $loadout_set_id];
        
        if ($parent_slot_id > 0) {
            $query .= " AND lwa.parent_equipment_slot_id = :parent_slot_id";
            $params[':parent_slot_id'] = $parent_slot_id;
        }
        
        $query .= " ORDER BY lwa.parent_equipment_slot_id, was.display_order";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by parent slot for easier frontend handling
        $grouped_attachments = [];
        foreach ($attachments as $attachment) {
            $parent_id = $attachment['parent_equipment_slot_id'];
            if (!isset($grouped_attachments[$parent_id])) {
                $grouped_attachments[$parent_id] = [];
            }
            $grouped_attachments[$parent_id][] = $attachment;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $attachments,
            'grouped' => $grouped_attachments,
            'loadout_set_id' => $loadout_set_id
        ]);
        
    } catch (PDOException $e) {
        error_log("Get existing attachments error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred'
        ]);
    }
    
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>