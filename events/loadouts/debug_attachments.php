<?php
// /events/loadouts/debug_attachments.php
// Bu dosyayı çalıştırarak attachment sistem debug edin

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';

echo "<h2>🔧 Attachment System Debug</h2>";

// 1. Database Tables Check
echo "<h3>1. Database Tables Kontrolü</h3>";

$tables_to_check = [
    'weapon_attachment_slots',
    'loadout_weapon_attachments',
    'equipment_slots',
    'loadout_sets'
];

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "<strong>$table:</strong> " . ($exists ? "✅ Exists" : "❌ Missing") . "<br>";
        
        if ($exists) {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "&nbsp;&nbsp;Row count: $count<br>";
        }
    } catch (Exception $e) {
        echo "<strong>$table:</strong> ❌ Error: " . $e->getMessage() . "<br>";
    }
    echo "<br>";
}

// 2. Weapon Attachment Slots Data
echo "<h3>2. Weapon Attachment Slots Data</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM weapon_attachment_slots ORDER BY display_order");
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($slots)) {
        echo "❌ <strong>weapon_attachment_slots tablosu boş!</strong><br>";
        echo "Migration SQL'i çalıştırmanız gerekiyor.<br><br>";
        
        echo "<h4>Çalıştırmanız gereken SQL:</h4>";
        echo "<textarea style='width:100%;height:200px;'>";
        echo "INSERT INTO weapon_attachment_slots (slot_name, slot_type, parent_weapon_slots, display_order, icon_class) VALUES
('Nişangah/Optik', 'IronSight', '7,8,9', 1, 'fas fa-crosshairs'),
('Namlu Eklentisi', 'Barrel', '7,8,9', 2, 'fas fa-long-arrow-alt-right'),
('Alt Bağlantı', 'BottomAttachment', '7,8', 3, 'fas fa-grip-lines');";
        echo "</textarea><br><br>";
    } else {
        echo "✅ <strong>Slots found:</strong><br>";
        foreach ($slots as $slot) {
            echo "- ID: {$slot['id']}, Name: {$slot['slot_name']}, Type: {$slot['slot_type']}, Parents: {$slot['parent_weapon_slots']}<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// 3. API Endpoint Test
echo "<h3>3. API Endpoint Test</h3>";

$api_url = 'api/get_attachment_slots.php?parent_slot_id=7';
echo "<strong>Testing:</strong> $api_url<br>";

// Simulate API call
try {
    if (file_exists('api/get_attachment_slots.php')) {
        echo "✅ API file exists<br>";
        
        // Test database query directly
        $stmt = $pdo->prepare("
            SELECT was.*, es.slot_name as parent_slot_name
            FROM weapon_attachment_slots was
            LEFT JOIN equipment_slots es ON es.id = 7
            WHERE FIND_IN_SET(7, was.parent_weapon_slots) > 0
            AND was.is_active = 1
            ORDER BY was.display_order ASC
        ");
        $stmt->execute();
        $api_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<strong>API Query Result:</strong><br>";
        if (empty($api_result)) {
            echo "❌ No attachment slots found for weapon slot 7<br>";
        } else {
            echo "✅ Found " . count($api_result) . " attachment slots:<br>";
            foreach ($api_result as $slot) {
                echo "- {$slot['slot_name']} ({$slot['slot_type']})<br>";
            }
        }
        
    } else {
        echo "❌ API file missing: api/get_attachment_slots.php<br>";
    }
} catch (Exception $e) {
    echo "❌ API Test Error: " . $e->getMessage() . "<br>";
}

// 4. JavaScript Console Test
echo "<h3>4. JavaScript Console Test</h3>";
echo "<p>Tarayıcı konsol'unu açın (F12) ve şu komutu çalıştırın:</p>";
echo "<code style='background:#f0f0f0;padding:10px;display:block;'>
// Test weapon attachment slot creation<br>
console.log('Testing weapon slots:', window.WEAPON_SLOTS);<br>
if (typeof window.createAttachmentSlots === 'function') {<br>
&nbsp;&nbsp;console.log('✅ createAttachmentSlots function exists');<br>
} else {<br>
&nbsp;&nbsp;console.log('❌ createAttachmentSlots function missing');<br>
}
</code>";

// 5. Equipment Slots Check
echo "<h3>5. Equipment Slots Check (Weapon Slots)</h3>";
try {
    $stmt = $pdo->query("SELECT id, slot_name FROM equipment_slots WHERE id IN (7,8,9)");
    $weapon_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Weapon Slots (7,8,9):</strong><br>";
    foreach ($weapon_slots as $slot) {
        echo "- ID: {$slot['id']}, Name: {$slot['slot_name']}<br>";
    }
    
    if (count($weapon_slots) < 3) {
        echo "❌ <strong>Missing weapon slots!</strong> Should have IDs 7, 8, 9<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// 6. File Structure Check
echo "<h3>6. File Structure Check</h3>";

$required_files = [
    'js/create_loadout.js',
    'css/create_loadout.css',
    'api/get_attachment_slots.php',
    'actions/save_loadout.php'
];

foreach ($required_files as $file) {
    $exists = file_exists($file);
    echo "<strong>$file:</strong> " . ($exists ? "✅ Exists" : "❌ Missing") . "<br>";
    
    if ($exists && pathinfo($file, PATHINFO_EXTENSION) === 'js') {
        $content = file_get_contents($file);
        $has_attachment_functions = strpos($content, 'createAttachmentSlots') !== false;
        echo "&nbsp;&nbsp;Has attachment functions: " . ($has_attachment_functions ? "✅" : "❌") . "<br>";
    }
}

// 7. Quick Fix Commands
echo "<h3>7. 🚀 Quick Fix Commands</h3>";

if (empty($slots)) {
    echo "<h4>Step 1: Create attachment slots table data</h4>";
    echo "<button onclick='createAttachmentSlots()'>Create Attachment Slots Data</button><br><br>";
}

echo "<h4>Step 2: Test API manually</h4>";
echo "<button onclick='testAPI()'>Test API Call</button><br><br>";

echo "<h4>Step 3: Test JavaScript functions</h4>";
echo "<button onclick='testJS()'>Test JavaScript Functions</button><br><br>";

?>

<script>
function createAttachmentSlots() {
    if (confirm('Attachment slots verilerini oluşturmak istiyor musunuz?')) {
        fetch('debug_create_slots.php', {method: 'POST'})
        .then(response => response.text())
        .then(data => {
            alert('Result: ' + data);
            location.reload();
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }
}

function testAPI() {
    fetch('api/get_attachment_slots.php?parent_slot_id=7')
    .then(response => response.json())
    .then(data => {
        console.log('API Response:', data);
        alert('API Response (check console): ' + (data.success ? 'Success' : 'Failed'));
    })
    .catch(error => {
        console.error('API Error:', error);
        alert('API Error: ' + error);
    });
}

function testJS() {
    // Test if loadout JavaScript is loaded
    if (typeof WEAPON_SLOTS !== 'undefined') {
        console.log('✅ WEAPON_SLOTS defined:', WEAPON_SLOTS);
    } else {
        console.log('❌ WEAPON_SLOTS not defined');
    }
    
    if (typeof createAttachmentSlots === 'function') {
        console.log('✅ createAttachmentSlots function exists');
        // Test creating attachment slots for slot 7
        createAttachmentSlots(7);
    } else {
        console.log('❌ createAttachmentSlots function missing');
    }
    
    alert('JavaScript test completed - check console (F12)');
}

// Auto-check on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Attachment Debug Page Loaded');
    console.log('Current URL:', window.location.href);
    
    // Check if we're on the loadout creation page
    if (window.location.href.includes('create_loadouts.php')) {
        console.log('✅ On loadout creation page');
    } else {
        console.log('ℹ️ Not on loadout creation page');
    }
});
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; line-height: 1.6; }
h2, h3, h4 { color: #333; margin-top: 2rem; }
strong { color: #666; }
code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
button { 
    background: #007cba; color: white; border: none; padding: 10px 20px; 
    border-radius: 4px; cursor: pointer; margin: 5px; 
}
button:hover { background: #005a8b; }
textarea { font-family: monospace; font-size: 12px; }
</style>