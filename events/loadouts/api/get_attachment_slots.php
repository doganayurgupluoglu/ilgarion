<?php
// /events/loadouts/api/get_attachment_slots.php - FIXED VERSION

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error handling - JSON formatında hata döndürmek için
ini_set('display_errors', 0); // PHP hatalarını JSON'a karışmasın diye kapat
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    require_once '../../../src/config/database.php';
    
    if (!isset($pdo)) {
        throw new Exception('PDO object not available');
    }
    
    // Test database connection
    $pdo->query("SELECT 1");
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Authentication check
$user_id = null;
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
} else {
    $user_id = (int)$_SESSION['user_id'];
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }
}

// Method check
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $parent_slot_id = isset($_GET['parent_slot_id']) ? (int)$_GET['parent_slot_id'] : 0;
    
    try {
        // Table check and creation
        $stmt = $pdo->query("SHOW TABLES LIKE 'weapon_attachment_slots'");
        if ($stmt->rowCount() == 0) {
            // Create table
            $create_sql = "
            CREATE TABLE IF NOT EXISTS `weapon_attachment_slots` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `slot_name` varchar(100) NOT NULL,
              `slot_type` varchar(100) NOT NULL,
              `parent_weapon_slots` varchar(50) NOT NULL,
              `display_order` int(11) NOT NULL DEFAULT 0,
              `icon_class` varchar(50) DEFAULT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `slot_name_UNIQUE` (`slot_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            $pdo->exec($create_sql);
            
            // Insert default data
            $insert_sql = "
            INSERT IGNORE INTO `weapon_attachment_slots` 
            (`slot_name`, `slot_type`, `parent_weapon_slots`, `display_order`, `icon_class`, `is_active`) 
            VALUES
            ('Nişangah/Optik', 'IronSight', '7,8,9', 1, 'fas fa-crosshairs', 1),
            ('Namlu Eklentisi', 'Barrel', '7,8,9', 2, 'fas fa-long-arrow-alt-right', 1),
            ('Alt Bağlantı', 'BottomAttachment', '7,8', 3, 'fas fa-grip-lines', 1)
            ";
            $pdo->exec($insert_sql);
        }
        
        // Query data - DÜZELTİLDİ
        if ($parent_slot_id > 0) {
            // FIXED: Parameter sadece bir kez kullanılıyor
            $query = "
                SELECT was.* 
                FROM weapon_attachment_slots was
                WHERE FIND_IN_SET(:parent_slot_id, was.parent_weapon_slots) > 0
                AND was.is_active = 1
                ORDER BY was.display_order ASC
            ";
            $params = [':parent_slot_id' => $parent_slot_id];
        } else {
            $query = "
                SELECT * FROM weapon_attachment_slots 
                WHERE is_active = 1 
                ORDER BY display_order ASC
            ";
            $params = [];
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process slots data
        foreach ($slots as &$slot) {
            $slot['parent_weapon_slots_array'] = array_map('intval', explode(',', $slot['parent_weapon_slots']));
        }
        
        $response = [
            'success' => true,
            'data' => $slots,
            'parent_slot_id' => $parent_slot_id,
            'count' => count($slots)
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
    
} elseif ($method === 'POST') {
    // POST handling for existing attachments
    $input = json_decode(file_get_contents('php://input'), true);
    $loadout_set_id = isset($input['loadout_set_id']) ? (int)$input['loadout_set_id'] : 0;
    
    if ($loadout_set_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid loadout set ID']);
        exit;
    }
    
    try {
        // Ownership check
        $stmt = $pdo->prepare("
            SELECT id FROM loadout_sets 
            WHERE id = :set_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':set_id' => $loadout_set_id,
            ':user_id' => $user_id
        ]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized or loadout not found']);
            exit;
        }
        
        // Check if attachment table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'loadout_weapon_attachments'");
        if ($stmt->rowCount() == 0) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'grouped' => [],
                'message' => 'Attachment table not found'
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
            ORDER BY lwa.parent_equipment_slot_id, was.display_order
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':set_id' => $loadout_set_id]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by parent slot
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
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>