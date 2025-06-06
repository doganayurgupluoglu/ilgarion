<?php
// /events/loadouts/debug/check_directories.php
// Bu dosyayı çalıştırarak mevcut dizin yapınızı kontrol edin

echo "<h2>Dizin Yapısı Kontrolü</h2>";

echo "<h3>Mevcut Dizin Bilgileri:</h3>";
echo "<strong>__FILE__:</strong> " . __FILE__ . "<br>";
echo "<strong>__DIR__:</strong> " . __DIR__ . "<br>";
echo "<strong>getcwd():</strong> " . getcwd() . "<br>";

echo "<h3>Relative Path Testleri:</h3>";

// 1 seviye yukarı
$one_up = dirname(__DIR__);
echo "<strong>1 seviye yukarı:</strong> " . $one_up . "<br>";
echo "<strong>Exists:</strong> " . (is_dir($one_up) ? "✅" : "❌") . "<br>";

// 2 seviye yukarı  
$two_up = dirname(dirname(__DIR__));
echo "<strong>2 seviye yukarı:</strong> " . $two_up . "<br>";
echo "<strong>Exists:</strong> " . (is_dir($two_up) ? "✅" : "❌") . "<br>";

// 3 seviye yukarı
$three_up = dirname(dirname(dirname(__DIR__)));
echo "<strong>3 seviye yukarı:</strong> " . $three_up . "<br>";
echo "<strong>Exists:</strong> " . (is_dir($three_up) ? "✅" : "❌") . "<br>";

echo "<h3>Upload Dizini Test:</h3>";

// Farklı upload path seçenekleri test et
$upload_options = [
    'option1' => $one_up . '/uploads/',
    'option2' => $two_up . '/uploads/',
    'option3' => $three_up . '/uploads/',
    'option4' => dirname(__DIR__) . '/uploads/',
    'option5' => '../uploads/',
    'option6' => '../../uploads/',
    'option7' => '../../../uploads/'
];

foreach ($upload_options as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;
    
    echo "<strong>{$name}:</strong> {$path}<br>";
    echo "&nbsp;&nbsp;Exists: " . ($exists ? "✅" : "❌") . "<br>";
    echo "&nbsp;&nbsp;Writable: " . ($writable ? "✅" : "❌") . "<br>";
    echo "<br>";
}

echo "<h3>Mevcut uploads Dizinlerini Ara:</h3>";

function findUploadsDir($start_dir, $max_depth = 5) {
    $found_dirs = [];
    
    function searchDir($dir, $depth, $max_depth, &$found_dirs) {
        if ($depth > $max_depth) return;
        
        try {
            if (!is_dir($dir)) return;
            
            $contents = scandir($dir);
            foreach ($contents as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $item_path = $dir . '/' . $item;
                if (is_dir($item_path)) {
                    if ($item === 'uploads') {
                        $found_dirs[] = $item_path;
                    }
                    // Recursive search
                    searchDir($item_path, $depth + 1, $max_depth, $found_dirs);
                }
            }
        } catch (Exception $e) {
            // Permission denied vs., ignore
        }
    }
    
    searchDir($start_dir, 0, $max_depth, $found_dirs);
    return $found_dirs;
}

$uploads_dirs = findUploadsDir(dirname(dirname(dirname(__DIR__))));

if (empty($uploads_dirs)) {
    echo "<p>❌ Hiç uploads dizini bulunamadı</p>";
} else {
    echo "<p>✅ Bulunan uploads dizinleri:</p>";
    foreach ($uploads_dirs as $dir) {
        $writable = is_writable($dir);
        echo "<strong>{$dir}</strong> - Writable: " . ($writable ? "✅" : "❌") . "<br>";
    }
}

echo "<h3>Document Root Test:</h3>";
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    echo "<strong>DOCUMENT_ROOT:</strong> {$doc_root}<br>";
    echo "<strong>Exists:</strong> " . (is_dir($doc_root) ? "✅" : "❌") . "<br>";
    
    $doc_uploads = $doc_root . '/uploads/';
    echo "<strong>DOCUMENT_ROOT/uploads:</strong> {$doc_uploads}<br>";
    echo "<strong>Exists:</strong> " . (is_dir($doc_uploads) ? "✅" : "❌") . "<br>";
    echo "<strong>Writable:</strong> " . (is_writable($doc_uploads) ? "✅" : "❌") . "<br>";
} else {
    echo "<p>❌ DOCUMENT_ROOT bulunamadı</p>";
}

echo "<h3>Gallery Uploads Kontrolü:</h3>";
// Galeri uploads'ını bul ve aynı yapıyı kullan
$gallery_paths = [
    '../../../uploads/gallery/',
    '../../uploads/gallery/',
    '../uploads/gallery/',
    dirname(dirname(dirname(__DIR__))) . '/uploads/gallery/'
];

foreach ($gallery_paths as $path) {
    if (is_dir($path)) {
        echo "<strong>✅ Gallery uploads bulundu:</strong> {$path}<br>";
        $parent_uploads = dirname($path);
        echo "<strong>Parent uploads dir:</strong> {$parent_uploads}<br>";
        echo "<strong>Loadouts için önerilen:</strong> {$parent_uploads}/loadouts/<br>";
        break;
    }
}

echo "<h3>Önerilen Çözüm:</h3>";
echo "<p>Bu çıktıyı kontrol ederek hangi uploads dizininin kullanılması gerektiğini belirleyin.</p>";
echo "<p>Genellikle gallery uploads ile aynı seviyede bir loadouts klasörü oluşturmak en mantıklısıdır.</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2, h3 { color: #333; }
strong { color: #666; }
p { background: white; padding: 10px; border-radius: 4px; }
</style>