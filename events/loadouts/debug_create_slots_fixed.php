<?php
// /events/loadouts/debug_create_slots.php - Quick fix for attachment slots

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';

header('Content-Type: text/plain');

try {
    // 1. Create weapon_attachment_slots table if not exists
    $create_table_sql = "
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
    
    $pdo->exec($create_table_sql);
    echo "✅ weapon_attachment_slots table created/verified\n";
    
    // 2. Insert attachment slots data
    $insert_sql = "
    INSERT IGNORE INTO `weapon_attachment_slots` 
    (`slot_name`, `slot_type`, `parent_weapon_slots`, `display_order`, `icon_class`, `is_active`) 
    VALUES
    ('Nişangah/Optik', 'IronSight', '7,8,9', 1, 'fas fa-crosshairs', 1),
    ('Namlu Eklentisi', 'Barrel', '7,8,9', 2, 'fas fa-long-arrow-alt-right', 1),
    ('Alt Bağlantı', 'BottomAttachment', '7,8', 3, 'fas fa-grip-lines', 1)
    ";
    
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute();
    echo "✅ Attachment slots data inserted\n";
    
    // 3. Create loadout_weapon_attachments table if not exists
    $create_attachments_table = "
    CREATE TABLE IF NOT EXISTS `loadout_weapon_attachments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `loadout_set_id` int(11) NOT NULL,
      `parent_equipment_slot_id` int(11) NOT NULL,
      `attachment_slot_id` int(11) NOT NULL,
      `attachment_item_name` varchar(255) NOT NULL,
      `attachment_item_uuid` varchar(255) DEFAULT NULL,
      `attachment_item_type` varchar(100) DEFAULT NULL,
      `attachment_item_manufacturer` varchar(255) DEFAULT NULL,
      `attachment_notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_loadout_parent` (`loadout_set_id`,`parent_equipment_slot_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($create_attachments_table);
    echo "✅ loadout_weapon_attachments table created/verified\n";
    
    // 4. Verify data
    $stmt = $pdo->query("SELECT COUNT(*) FROM weapon_attachment_slots");
    $count = $stmt->fetchColumn();
    echo "✅ Total attachment slots: $count\n";
    
    if ($count >= 3) {
        echo "🎉 SUCCESS! Attachment slots ready to use.\n";
        echo "\nNext steps:\n";
        echo "1. Refresh your loadout creation page\n";
        echo "2. Add a weapon to slot 7, 8, or 9\n";
        echo "3. Attachment slots should appear automatically\n";
    } else {
        echo "⚠️ WARNING: Only $count slots found, expected at least 3\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>