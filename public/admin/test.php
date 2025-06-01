<?php
// public/admin/debug_roles.php - Geçici hata ayıklama dosyası

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

echo "<h1>Rol Yönetimi Hata Ayıklama</h1>";

// 1. Temel bağlantı kontrolü
echo "<h2>1. Veritabanı Bağlantısı</h2>";
try {
    if (isset($pdo)) {
        echo "✅ PDO bağlantısı mevcut<br>";
        echo "PDO Durumu: " . ($pdo ? "Bağlı" : "Bağlı değil") . "<br>";
    } else {
        echo "❌ PDO bağlantısı bulunamadı<br>";
    }
} catch (Exception $e) {
    echo "❌ PDO hatası: " . $e->getMessage() . "<br>";
}

// 2. Session kontrolü
echo "<h2>2. Session Kontrolü</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session aktif<br>";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Yok') . "<br>";
    echo "Username: " . ($_SESSION['username'] ?? 'Yok') . "<br>";
} else {
    echo "❌ Session aktif değil<br>";
}

// 3. Fonksiyon varlık kontrolü
echo "<h2>3. Fonksiyon Kontrolleri</h2>";
$required_functions = [
    'has_permission',
    'is_super_admin', 
    'get_user_roles',
    'execute_safe_query',
    'create_safe_order_by'
];

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "✅ $func fonksiyonu mevcut<br>";
    } else {
        echo "❌ $func fonksiyonu bulunamadı<br>";
    }
}

// 4. Tablo varlık kontrolü
echo "<h2>4. Veritabanı Tabloları</h2>";
$required_tables = ['roles', 'user_roles', 'role_permissions', 'permissions', 'users'];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ $table tablosu mevcut<br>";
        } else {
            echo "❌ $table tablosu bulunamadı<br>";
        }
    } catch (Exception $e) {
        echo "❌ $table kontrolü hatası: " . $e->getMessage() . "<br>";
    }
}

// 5. Yetki kontrolleri
echo "<h2>5. Yetki Kontrolleri</h2>";
try {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        echo "Kullanıcı ID: $user_id<br>";
        
        // Admin.roles.view yetkisi
        if (function_exists('has_permission')) {
            $can_view = has_permission($pdo, 'admin.roles.view');
            echo "admin.roles.view yetkisi: " . ($can_view ? "✅ Var" : "❌ Yok") . "<br>";
        }
        
        // Süper admin kontrolü
        if (function_exists('is_super_admin')) {
            $is_super = is_super_admin($pdo);
            echo "Süper admin: " . ($is_super ? "✅ Evet" : "❌ Hayır") . "<br>";
        }
        
        // Kullanıcı rolleri
        if (function_exists('get_user_roles')) {
            $user_roles = get_user_roles($pdo, $user_id);
            echo "Kullanıcı rolleri: " . count($user_roles) . " adet<br>";
            foreach ($user_roles as $role) {
                echo "- {$role['name']} (Öncelik: {$role['priority']})<br>";
            }
        }
    } else {
        echo "❌ Giriş yapılmamış<br>";
    }
} catch (Exception $e) {
    echo "❌ Yetki kontrolü hatası: " . $e->getMessage() . "<br>";
}

// 6. Basit rol sorgusu testi
echo "<h2>6. Basit Rol Sorgusu</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM roles");
    $total = $stmt->fetchColumn();
    echo "✅ Toplam rol sayısı: $total<br>";
    
    if ($total > 0) {
        $stmt2 = $pdo->query("SELECT id, name, priority FROM roles ORDER BY priority ASC LIMIT 5");
        $roles = $stmt2->fetchAll();
        echo "İlk 5 rol:<br>";
        foreach ($roles as $role) {
            echo "- ID: {$role['id']}, Ad: {$role['name']}, Öncelik: {$role['priority']}<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Rol sorgusu hatası: " . $e->getMessage() . "<br>";
}

// 7. Karmaşık sorgu testi
echo "<h2>7. Karmaşık Sorgu Testi</h2>";
try {
    $user_id = $_SESSION['user_id'] ?? 1;
    $is_super_admin = is_super_admin($pdo) ? 1 : 0;
    
    $query = "
        SELECT r.*, 
               COUNT(DISTINCT ur.user_id) as user_count,
               COUNT(DISTINCT rp.permission_id) as permission_count,
               CASE 
                   WHEN r.priority <= :user_priority AND :is_super_admin = 0 THEN 0
                   WHEN r.name IN ('admin', 'member', 'dis_uye') AND :is_super_admin = 0 THEN 0
                   ELSE 1
               END as can_manage
        FROM roles r
        LEFT JOIN user_roles ur ON r.id = ur.user_id
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        GROUP BY r.id, r.name, r.description, r.color, r.priority, r.created_at, r.updated_at
        ORDER BY r.priority ASC
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_priority' => 999,
        ':is_super_admin' => $is_super_admin
    ]);
    $roles = $stmt->fetchAll();
    
    echo "✅ Karmaşık sorgu başarılı, " . count($roles) . " sonuç<br>";
    foreach ($roles as $role) {
        echo "- {$role['name']}: {$role['user_count']} kullanıcı, {$role['permission_count']} yetki<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Karmaşık sorgu hatası: " . $e->getMessage() . "<br>";
    echo "Hata detayı: " . $e->getTraceAsString() . "<br>";
}

// 8. Permission grubu testi
echo "<h2>8. Permission Grupları</h2>";
try {
    if (function_exists('get_all_permissions_grouped')) {
        $permission_groups = get_all_permissions_grouped($pdo);
        echo "✅ Permission grupları: " . count($permission_groups) . " grup<br>";
        foreach ($permission_groups as $group_name => $permissions) {
            echo "- $group_name: " . count($permissions) . " yetki<br>";
        }
    } else {
        echo "❌ get_all_permissions_grouped fonksiyonu bulunamadı<br>";
    }
} catch (Exception $e) {
    echo "❌ Permission grupları hatası: " . $e->getMessage() . "<br>";
}

// 9. Error log kontrolü
echo "<h2>9. Son Hatalar</h2>";
if (function_exists('error_get_last')) {
    $last_error = error_get_last();
    if ($last_error) {
        echo "Son PHP hatası:<br>";
        echo "Tip: " . $last_error['type'] . "<br>";
        echo "Mesaj: " . $last_error['message'] . "<br>";
        echo "Dosya: " . $last_error['file'] . "<br>";
        echo "Satır: " . $last_error['line'] . "<br>";
    } else {
        echo "✅ Aktif PHP hatası yok<br>";
    }
}

echo "<h2>Test Tamamlandı</h2>";
echo "<p><a href='manage_roles.php'>Rol Yönetimi Sayfasına Dön</a></p>";
?>