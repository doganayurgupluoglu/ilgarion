<?php
// /events/loadouts/api/get_attachment_slots.php - FIXED VERSION

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error logging for debugging
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection with detailed error logging
try {
    require_once '../../../src/config/database.php';
} catch (Exception $e) {
    error_log("Database include error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Check if PDO exists
if (!isset($pdo)) {
    error_log("PDO object not available");
    echo json_encode(['success' => false, 'error' => 'Database not initialized']);
    exit;
}

// Authentication check - SIMPLIFIED and IMPROVED
try {
    if (!isset($_SESSION['user_id'])) {
        error_log("No user_id in session");
        echo json_encode(['success' => false, 'error' => 'Authentication required - no user_id in session']);
        exit;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    if ($user_id <= 0) {
        error_log("Invalid user_id: " . $_SESSION['user_id']);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }
    
    // Simple user existence check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    if (!$stmt->fetch()) {
        error_log("User not found: $user_id");
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    error_log("Auth successful for user: $user_id");
    
} catch (Exception $e) {
    error_log("Auth check failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Auth check failed: ' . $e->getMessage()]);
    exit;
}

// Method check
$method = $_SERVER['REQUEST_METHOD'];
error_log("Request method: $method");

if ($method === 'GET') {
    // Get weapon attachment slots for specific parent slot
    $parent_slot_id = isset($_GET['parent_slot_id']) ? (int)$_GET['parent_slot_id'] : 0;
    error_log("GET request for parent_slot_id: $parent_slot_id");
    
    try {
        // First, check if tables exist
        $tables_check = $pdo->query("SHOW TABLES LIKE 'weapon_attachment_slots'");
        if ($tables_check->rowCount() == 0) {
            error_log("weapon_attachment_slots table does not exist");
            echo json_encode(['success' => false, 'error' => 'Attachment slots table not found']);
            exit;
        }
        
        if ($parent_slot_id > 0) {
            // Specific parent slot için attachment slotları
            $query = "
                SELECT was.*, es.slot_name as parent_slot_name
                FROM weapon_attachment_slots was
                LEFT JOIN equipment_slots es ON es.id = :parent_slot_id
                WHERE FIND_IN_SET(:parent_slot_id, was.parent_weapon_slots) > 0
                AND was.is_active = 1
                ORDER BY was.display_order ASC
            ";
            $params = [':parent_slot_id' => $parent_slot_id];
            error_log("Executing query for parent slot $parent_slot_id");
        } else {
            // Tüm attachment slotları
            $query = "
                SELECT * FROM weapon_attachment_slots 
                WHERE is_active = 1 
                ORDER BY display_order ASC
            ";
            $params = [];
            error_log("Executing query for all slots");
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Query executed successfully, found " . count($slots) . " slots");
        
        // Parent weapon slots'ı array'e çevir
        foreach ($slots as &$slot) {
            $slot['parent_weapon_slots_array'] = array_map('intval', explode(',', $slot['parent_weapon_slots']));
        }
        
        $response = [
            'success' => true,
            'data' => $slots,
            'parent_slot_id' => $parent_slot_id,
            'debug_info' => [
                'query_executed' => true,
                'user_id' => $user_id,
                'slots_count' => count($slots)
            ]
        ];
        
        error_log("Sending successful response with " . count($slots) . " slots");
        echo json_encode($response);
        
    } catch (PDOException $e) {
        error_log("Database error in GET: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage(),
            'debug_info' => [
                'query' => isset($query) ? $query : 'Query not set',
                'params' => isset($params) ? $params : 'Params not set'
            ]
        ]);
    } catch (Exception $e) {
        error_log("General error in GET: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
    
} elseif ($method === 'POST') {
    // Get existing attachments for a loadout set
    $input = json_decode(file_get_contents('php://input'), true);
    $loadout_set_id = isset($input['loadout_set_id']) ? (int)$input['loadout_set_id'] : 0;
    $parent_slot_id = isset($input['parent_slot_id']) ? (int)$input['parent_slot_id'] : 0;
    
    error_log("POST request for loadout_set_id: $loadout_set_id, parent_slot_id: $parent_slot_id");
    
    if ($loadout_set_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid loadout set ID']);
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
            ':user_id' => $user_id
        ]);
        
        if (!$stmt->fetch()) {
            error_log("Unauthorized access to loadout $loadout_set_id by user $user_id");
            echo json_encode(['success' => false, 'error' => 'Unauthorized or loadout not found']);
            exit;
        }
        
        // Check if attachment table exists
        $attachment_table_check = $pdo->query("SHOW TABLES LIKE 'loadout_weapon_attachments'");
        if ($attachment_table_check->rowCount() == 0) {
            error_log("loadout_weapon_attachments table does not exist");
            // Return empty result instead of error for POST
            echo json_encode([
                'success' => true,
                'data' => [],
                'grouped' => [],
                'loadout_set_id' => $loadout_set_id,
                'message' => 'Attachment table not found - returning empty'
            ]);
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
        
        error_log("Found " . count($attachments) . " existing attachments");
        
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
        error_log("Database error in POST: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("General error in POST: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
    
} else {
    error_log("Invalid method: $method");
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>