<?php
// /admin/audit_test.php - Debug için test dosyası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../'));
}

echo "BASE_PATH: " . BASE_PATH . "<br>";

// Dosya varlığı kontrolleri
$files_to_check = [
    BASE_PATH . '/src/config/database.php',
    BASE_PATH . '/src/functions/auth_functions.php',
    BASE_PATH . '/src/functions/audit_functions.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file - VAR<br>";
    } else {
        echo "❌ $file - YOK<br>";
    }
}

try {
    require_once BASE_PATH . '/src/config/database.php';
    echo "✅ Database config yüklendi<br>";
    
    if (isset($pdo)) {
        echo "✅ PDO bağlantısı var<br>";
    } else {
        echo "❌ PDO bağlantısı yok<br>";
    }
} catch (Exception $e) {
    echo "❌ Database config hatası: " . $e->getMessage() . "<br>";
}

try {
    require_once BASE_PATH . '/src/functions/auth_functions.php';
    echo "✅ Auth functions yüklendi<br>";
} catch (Exception $e) {
    echo "❌ Auth functions hatası: " . $e->getMessage() . "<br>";
}

try {
    require_once BASE_PATH . '/src/functions/audit_functions.php';
    echo "✅ Audit functions yüklendi<br>";
    
    if (function_exists('add_audit_log')) {
        echo "✅ add_audit_log fonksiyonu var<br>";
    } else {
        echo "❌ add_audit_log fonksiyonu yok<br>";
    }
} catch (Exception $e) {
    echo "❌ Audit functions hatası: " . $e->getMessage() . "<br>";
}

// Session kontrol
if (isset($_SESSION['user_id'])) {
    echo "✅ User session var - ID: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ User session yok<br>";
}

// Test audit log
if (isset($pdo) && function_exists('add_audit_log')) {
    try {
        $result = add_audit_log($pdo, 'Test audit mesajı', 'test', null, null, [
            'test_data' => 'debug_test',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            echo "✅ Test audit log başarılı - ID: $result<br>";
        } else {
            echo "❌ Test audit log başarısız<br>";
        }
    } catch (Exception $e) {
        echo "❌ Test audit log hatası: " . $e->getMessage() . "<br>";
    }
}

// Audit tablosu kontrol
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM audit_log");
        $count = $stmt->fetchColumn();
        echo "✅ Audit tablosunda $count kayıt var<br>";
        
        // Son 5 kaydı göster
        $stmt = $pdo->query("SELECT id, action, created_at FROM audit_log ORDER BY id DESC LIMIT 5");
        $logs = $stmt->fetchAll();
        
        echo "<h3>Son 5 Audit Log:</h3>";
        foreach ($logs as $log) {
            echo "ID: {$log['id']} - {$log['action']} - {$log['created_at']}<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Audit tablosu hatası: " . $e->getMessage() . "<br>";
    }
}
?>