<?php
// src/functions/role_functions.php

// SQL güvenlik fonksiyonlarını dahil et
require_once dirname(dirname(__DIR__)) . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

/**
 * Kullanıcının belirli bir yetkiye sahip olup olmadığını kontrol eder (SQL Güvenli)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $permission Kontrol edilecek yetki
 * @param int|null $user_id Kullanıcı ID (opsiyonel, belirtilmezse session'dan alınır)
 * @return bool
 */
function has_permission(PDO $pdo, string $permission, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    // Input validation
    if (!validate_sql_input($permission, 'permission_key')) {
        error_log("Invalid permission string detected: $permission");
        return false;
    }

    // Süper admin kontrolü (güvenli yöntem)
    if (is_super_admin($pdo, $user_id)) {
        return true;
    }

    // Önce cache'den kontrol et
    $cached_permissions = get_cached_user_permissions($user_id);
    
    if ($cached_permissions !== null) {
        return in_array($permission, $cached_permissions);
    }

    try {
        // Normalize edilmiş tablolardan yetkileri çek (güvenli sorgu)
        $query = "
            SELECT DISTINCT p.permission_key
            FROM user_roles ur 
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id AND p.is_active = 1
        ";
        
        $params = [':user_id' => $user_id];
        $stmt = execute_safe_query($pdo, $query, $params);
        $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Cache'e kaydet
        cache_user_permissions($user_id, $user_permissions);
        
        return in_array($permission, $user_permissions);
        
    } catch (Exception $e) {
        error_log("Yetki kontrolü hatası (Kullanıcı ID: $user_id, Yetki: $permission): " . $e->getMessage());
        audit_log($pdo, $user_id, 'permission_check_failed', 'permission', null, null, [
            'permission' => $permission,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Kullanıcının süper admin olup olmadığını kontrol eder (güvenli yöntem)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID
 * @return bool
 */
function is_super_admin(PDO $pdo, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    try {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = :key";
        $params = [':key' => 'super_admin_users'];
        $stmt = execute_safe_query($pdo, $query, $params);
        $super_admin_data = $stmt->fetchColumn();
        
        if ($super_admin_data) {
            $super_admin_ids = json_decode($super_admin_data, true);
            if (is_array($super_admin_ids)) {
                return in_array($user_id, array_map('intval', $super_admin_ids));
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Süper admin kontrolü hatası (Kullanıcı ID: $user_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının admin olup olmadığını kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID
 * @return bool
 */
function is_admin(PDO $pdo, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    // Önce süper admin kontrolü
    if (is_super_admin($pdo, $user_id)) {
        return true;
    }

    try {
        $query = "
            SELECT COUNT(*) 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = :user_id AND r.name = :role_name
        ";
        $params = [':user_id' => $user_id, ':role_name' => 'admin'];
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchColumn() > 0;
        
    } catch (Exception $e) {
        error_log("Admin kontrolü hatası (Kullanıcı ID: $user_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının yetkilerini cache'den getirir
 * @param int $user_id Kullanıcı ID
 * @return array|null Cache'deki yetkiler veya null
 */
function get_cached_user_permissions(int $user_id): ?array {
    $cache_key = "user_permissions_$user_id";
    $cache_time_key = "user_permissions_time_$user_id";
    
    if (isset($_SESSION[$cache_key]) && isset($_SESSION[$cache_time_key])) {
        // Cache 5 dakikadan eskiyse yenile
        if (time() - $_SESSION[$cache_time_key] < 5) {
            return $_SESSION[$cache_key];
        } else {
            // Cache süresi dolmuş, temizle
            unset($_SESSION[$cache_key], $_SESSION[$cache_time_key]);
        }
    }
    
    return null;
}

/**
 * Kullanıcının yetkilerini cache'e kaydeder
 * @param int $user_id Kullanıcı ID
 * @param array $permissions Yetkiler dizisi
 */
function cache_user_permissions(int $user_id, array $permissions): void {
    $cache_key = "user_permissions_$user_id";
    $cache_time_key = "user_permissions_time_$user_id";
    
    $_SESSION[$cache_key] = $permissions;
    $_SESSION[$cache_time_key] = time();
}

/**
 * Kullanıcının yetki cache'ini temizler
 * @param int $user_id Kullanıcı ID
 */
function clear_user_permissions_cache(int $user_id): void {
    $cache_key = "user_permissions_$user_id";
    $cache_time_key = "user_permissions_time_$user_id";
    
    unset($_SESSION[$cache_key], $_SESSION[$cache_time_key]);
}

/**
 * Kullanıcının tüm rollerini güvenli şekilde getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID (opsiyonel, belirtilmezse session'dan alınır)
 * @return array Kullanıcının rollerini içeren dizi
 */
function get_user_roles(PDO $pdo, ?int $user_id = null): array {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $user_id = $_SESSION['user_id'];
    }

    try {
        $query = "
            SELECT r.id, r.name, r.description, r.color, r.priority
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = :user_id
            ORDER BY r.priority ASC, r.name ASC
        ";
        
        $params = [':user_id' => $user_id];
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Kullanıcı rolleri getirme hatası (Kullanıcı ID: $user_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcıya rol atar (güvenli versiyon)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 * @param int $role_id Rol ID
 * @param int|null $granted_by_user_id İşlemi yapan kullanıcı ID (audit için)
 * @return bool Başarılıysa true, aksi halde false
 */
function assign_role(PDO $pdo, int $user_id, int $role_id, ?int $granted_by_user_id = null): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($user_id, $role_id, $granted_by_user_id) {
            // Kullanıcının bu role zaten sahip olup olmadığını kontrol et
            $check_query = "SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id AND role_id = :role_id";
            $check_params = [':user_id' => $user_id, ':role_id' => $role_id];
            $stmt_check = execute_safe_query($pdo, $check_query, $check_params);
            
            if ($stmt_check->fetchColumn() > 0) {
                return true; // Zaten sahip, işlem başarılı sayılır
            }

            $insert_query = "INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)";
            $insert_params = [':user_id' => $user_id, ':role_id' => $role_id];
            $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
            
            // Cache'i temizle
            clear_user_permissions_cache($user_id);
            
            // Audit log
            audit_log($pdo, $granted_by_user_id, 'role_assigned', 'user', $user_id, null, [
                'role_id' => $role_id,
                'target_user_id' => $user_id
            ]);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Rol atama hatası (Kullanıcı ID: $user_id, Rol ID: $role_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcıdan rol kaldırır (güvenli versiyon)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 * @param int $role_id Rol ID
 * @param int|null $removed_by_user_id İşlemi yapan kullanıcı ID (audit için)
 * @return bool Başarılıysa true, aksi halde false
 */
function remove_role(PDO $pdo, int $user_id, int $role_id, ?int $removed_by_user_id = null): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($user_id, $role_id, $removed_by_user_id) {
            $delete_query = "DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id";
            $delete_params = [':user_id' => $user_id, ':role_id' => $role_id];
            $stmt = execute_safe_query($pdo, $delete_query, $delete_params);
            
            // Cache'i temizle
            clear_user_permissions_cache($user_id);
            
            // Audit log
            audit_log($pdo, $removed_by_user_id, 'role_removed', 'user', $user_id, null, [
                'role_id' => $role_id,
                'target_user_id' => $user_id
            ]);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Rol kaldırma hatası (Kullanıcı ID: $user_id, Rol ID: $role_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Sistemdeki tüm rolleri güvenli şekilde getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $order_by Sıralama sütunu
 * @param string $direction Sıralama yönü
 * @return array Tüm rolleri içeren dizi
 */
function get_all_roles(PDO $pdo, string $order_by = 'priority', string $direction = 'ASC'): array {
    try {
        $allowed_columns = ['id', 'name', 'description', 'priority', 'created_at'];
        $safe_order = create_safe_order_by($order_by, $direction, $allowed_columns);
        
        $query = "SELECT id, name, description, color, priority FROM roles ORDER BY $safe_order";
        $stmt = execute_safe_query($pdo, $query);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Tüm rolleri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Sistemdeki tüm yetkileri gruplar halinde güvenli şekilde getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array Grup bazında yetkiler
 */
function get_all_permissions_grouped(PDO $pdo): array {
    try {
        $query = "
            SELECT permission_key, permission_name, permission_group 
            FROM permissions 
            WHERE is_active = 1 
            ORDER BY permission_group ASC, permission_key ASC
        ";
        
        $stmt = execute_safe_query($pdo, $query);
        $permissions = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($permissions as $perm) {
            $group_name = ucfirst($perm['permission_group']);
            $grouped[$group_name][$perm['permission_key']] = $perm['permission_name'];
        }
        
        return $grouped;
    } catch (Exception $e) {
        error_log("Yetkileri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Rol hiyerarşisini güvenli şekilde getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array Rol öncelik sırası
 */
function get_role_hierarchy(PDO $pdo): array {
    try {
        $query = "SELECT name FROM roles ORDER BY priority ASC";
        $stmt = execute_safe_query($pdo, $query);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Rol hiyerarşisi getirme hatası: " . $e->getMessage());
        // Fallback to static hierarchy - SADECE ADMIN KORUNACAK
        return ['admin'];
    }
}

/**
 * Kullanıcı arama fonksiyonu (güvenli)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $search_term Arama terimi
 * @param string $search_type Arama tipi
 * @param int $limit Sonuç limiti
 * @return array Kullanıcı listesi
 */
function search_users_safely(PDO $pdo, string $search_term, string $search_type = 'contains', int $limit = 50): array {
    try {
        // Input validation
        if (!validate_sql_input($search_term)) {
            throw new SecurityException("Invalid search term detected");
        }
        
        $like_pattern = create_safe_like_pattern($search_term, $search_type);
        $safe_limit = create_safe_limit($limit, 0, 100);
        
        $query = "SELECT id, username, email, status, ingame_name 
                  FROM users 
                  WHERE username LIKE :search OR email LIKE :search OR ingame_name LIKE :search
                  ORDER BY username ASC 
                  $safe_limit";
        
        $params = [':search' => $like_pattern];
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Kullanıcı arama hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının en yüksek öncelikli rolünü getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $user_roles Kullanıcının rolleri
 * @return string|null En yüksek öncelikli rol veya null
 */
function get_primary_role(PDO $pdo, array $user_roles): ?string {
    if (empty($user_roles)) {
        return null;
    }
    
    // En düşük priority değerine sahip rol en yüksek önceliklidir
    $primary_role = null;
    $highest_priority = 999999;
    
    foreach ($user_roles as $role) {
        if ($role['priority'] < $highest_priority) {
            $highest_priority = $role['priority'];
            $primary_role = $role['name'];
        }
    }
    
    return $primary_role;
}

/**
 * Belirli bir yetkiye sahip olmayı zorunlu kılar, değilse yönlendirir.
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $permission Gerekli yetki anahtarı
 * @param string|null $redirect_url Başarısız olursa yönlendirilecek URL (opsiyonel)
 */
function require_permission(PDO $pdo, string $permission, ?string $redirect_url = null): void {
    if (!is_user_logged_in()) {
        $_SESSION['error_message'] = "Bu işlemi yapmak için giriş yapmalısınız.";
        header('Location: ' . (get_auth_base_url() . '/login.php?status=login_required_for_permission'));
        exit;
    }
    
    if (!has_permission($pdo, $permission)) {
        $_SESSION['error_message'] = "Bu işlem için gerekli yetkiye sahip değilsiniz.";
        if ($redirect_url === null) {
            $redirect_url =  '/index.php?status=permission_denied';
        }
        
        // Audit log - yetkisiz erişim girişimi
        audit_log($pdo, $_SESSION['user_id'] ?? null, 'unauthorized_access_attempt', 'permission', null, null, [
            'required_permission' => $permission,
            'requested_url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Kullanıcının yetki listesini günceller (rolleri değiştiğinde çağrılır)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 */
function refresh_user_permissions(PDO $pdo, int $user_id): void {
    clear_user_permissions_cache($user_id);
    
    // Yeni yetkileri cache'e yükle
    has_permission($pdo, 'dummy_permission_to_load_cache', $user_id);
}

/**
 * Role yetki atar (güvenli versiyon)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $role_id Rol ID
 * @param array $permission_keys Yetki anahtarları dizisi
 * @param int|null $granted_by_user_id İşlemi yapan kullanıcı ID
 * @return bool Başarılıysa true
 */
function assign_permissions_to_role(PDO $pdo, int $role_id, array $permission_keys, ?int $granted_by_user_id = null): bool {
    try {
        // Rolün mevcut yetkilerini al (audit için)
        $old_query = "
            SELECT p.permission_key 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = :role_id
        ";
        $old_params = [':role_id' => $role_id];
        $stmt_old = execute_safe_query($pdo, $old_query, $old_params);
        $old_permissions = $stmt_old->fetchAll(PDO::FETCH_COLUMN);
        
        // Mevcut yetkileri sil
        $delete_query = "DELETE FROM role_permissions WHERE role_id = :role_id";
        $delete_params = [':role_id' => $role_id];
        execute_safe_query($pdo, $delete_query, $delete_params);
        
        // Yeni yetkileri ekle
        if (!empty($permission_keys)) {
            foreach ($permission_keys as $permission_key) {
                // Input validation - permission key için özel validation
                if (!validate_sql_input($permission_key, 'permission_key')) {
                    error_log("Invalid permission key skipped: $permission_key");
                    continue; // Geçersiz olanı atla, hata verme
                }
                
                $permission_query = "SELECT id FROM permissions WHERE permission_key = :key AND is_active = 1";
                $permission_params = [':key' => $permission_key];
                $stmt_permission = execute_safe_query($pdo, $permission_query, $permission_params);
                $permission_id = $stmt_permission->fetchColumn();
                
                if ($permission_id) {
                    $insert_query = "INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
                    $insert_params = [':role_id' => $role_id, ':permission_id' => $permission_id];
                    execute_safe_query($pdo, $insert_query, $insert_params);
                }
            }
        }
        
        // Bu role sahip tüm kullanıcıların cache'ini temizle
        $users_query = "SELECT user_id FROM user_roles WHERE role_id = :role_id";
        $users_params = [':role_id' => $role_id];
        $stmt_users = execute_safe_query($pdo, $users_query, $users_params);
        $affected_users = $stmt_users->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($affected_users as $user_id) {
            clear_user_permissions_cache($user_id);
        }
        
        // Audit log (hata verirse skip et)
        try {
            audit_log($pdo, $granted_by_user_id, 'role_permissions_updated', 'role', $role_id, 
                ['permissions' => $old_permissions], 
                ['permissions' => $permission_keys]
            );
        } catch (Exception $e) {
            error_log("Audit log hatası (not critical): " . $e->getMessage());
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Role yetki atama hatası (Rol ID: $role_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Rolün yetkilerini güvenli şekilde getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $role_id Rol ID
 * @return array Yetki anahtarları dizisi
 */
function get_role_permissions(PDO $pdo, int $role_id): array {
    try {
        $query = "
            SELECT p.permission_key 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = :role_id AND p.is_active = 1
            ORDER BY p.permission_key ASC
        ";
        $params = [':role_id' => $role_id];
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Rol yetkileri getirme hatası (Rol ID: $role_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Audit log kaydı oluşturur
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id İşlemi yapan kullanıcı ID
 * @param string $action Yapılan işlem
 * @param string $target_type Hedef tip (role, user, permission vb.)
 * @param int|null $target_id Hedef ID
 * @param array|null $old_values Eski değerler
 * @param array|null $new_values Yeni değerler
 */
function audit_log(PDO $pdo, ?int $user_id, string $action, string $target_type, ?int $target_id, ?array $old_values, ?array $new_values): void {
    try {
        $query = "
            INSERT INTO audit_log (user_id, action, target_type, target_id, old_values, new_values, ip_address, user_agent)
            VALUES (:user_id, :action, :target_type, :target_id, :old_values, :new_values, :ip_address, :user_agent)
        ";
        
        $params = [
            ':user_id' => $user_id,
            ':action' => $action,
            ':target_type' => $target_type,
            ':target_id' => $target_id,
            ':old_values' => $old_values ? json_encode($old_values) : null,
            ':new_values' => $new_values ? json_encode($new_values) : null,
            ':ip_address' => get_client_ip(),
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        execute_safe_query($pdo, $query, $params, false); // Audit log'da güvenlik analizi yapmayalım
    } catch (Exception $e) {
        error_log("Audit log kaydetme hatası: " . $e->getMessage());
    }
}

/**
 * Kritik rollerin silinmesini engeller
 * @param string $role_name Rol adı
 * @return bool Silinebilirse true, silinmemesi gerekiyorsa false
 */
function is_role_deletable(string $role_name): bool {
    // SADECE ADMIN ROL KORUNACAK - diğer roller kaldırıldı
    $protected_roles = ['admin']; // member, dis_uye, super_admin kaldırıldı
    return !in_array($role_name, $protected_roles);
}

/**
 * Kritik rollerin önemli alanlarının değiştirilmesini engeller
 * @param string $role_name Rol adı
 * @return bool Düzenlenebilirse true, düzenlenmemesi gerekiyorsa false
 */
function is_role_name_editable(string $role_name): bool {
    // SADECE ADMIN ROL KORUNACAK - diğer roller kaldırıldı
    $protected_roles = ['admin']; // member, dis_uye, super_admin kaldırıldı
    return !in_array($role_name, $protected_roles);
}

/**
 * Input validation: Rol adı format kontrolü
 * @param string $role_name Kontrol edilecek rol adı
 * @return bool Geçerliyse true
 */
function validate_role_name(string $role_name): bool {
    // 2-50 karakter, sadece küçük harf, rakam ve alt çizgi
    return preg_match('/^[a-z0-9_]{2,50}$/', $role_name) === 1;
}

/**
 * Input validation: Rol adı format kontrolü
 * @param string $role_name Kontrol edilecek rol adı
 * @return bool Geçerliyse true
 */


/**
 * Input validation: Renk kodu format kontrolü
 * @param string $color_code Kontrol edilecek renk kodu
 * @return bool Geçerliyse true
 */
function validate_color_code(string $color_code): bool {
    // Hex renk kodu formatı (#rrggbb)
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color_code) === 1;
}

/**
 * Sistem ayarı getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $setting_key Ayar anahtarı
 * @param mixed $default_value Varsayılan değer
 * @return mixed Ayar değeri
 */
function get_system_setting(PDO $pdo, string $setting_key, $default_value = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $setting_key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default_value;
        }
        
        $value = $result['setting_value'];
        
        switch ($result['setting_type']) {
            case 'integer':
                return (int)$value;
            case 'boolean':
                return (bool)$value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    } catch (PDOException $e) {
        error_log("Sistem ayarı getirme hatası ($setting_key): " . $e->getMessage());
        return $default_value;
    }
}

/**
 * Sistem ayarı kaydeder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $setting_key Ayar anahtarı
 * @param mixed $setting_value Ayar değeri
 * @param string $setting_type Ayar tipi
 * @param string|null $description Açıklama
 * @param int|null $updated_by_user_id İşlemi yapan kullanıcı ID
 * @return bool Başarılıysa true
 */
function set_system_setting(PDO $pdo, string $setting_key, $setting_value, string $setting_type = 'string', ?string $description = null, ?int $updated_by_user_id = null): bool {
    try {
        // Değeri tipine göre dönüştür
        switch ($setting_type) {
            case 'json':
                $value_to_store = is_string($setting_value) ? $setting_value : json_encode($setting_value);
                break;
            default:
                $value_to_store = (string)$setting_value;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description) 
            VALUES (:key, :value, :type, :description)
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                description = VALUES(description)
        ");
        
        $result = $stmt->execute([
            ':key' => $setting_key,
            ':value' => $value_to_store,
            ':type' => $setting_type,
            ':description' => $description
        ]);
        
        if ($result) {
            // Audit log
            try {
                audit_log($pdo, $updated_by_user_id, 'system_setting_updated', 'system_setting', null, null, [
                    'setting_key' => $setting_key,
                    'setting_value' => $value_to_store,
                    'setting_type' => $setting_type
                ]);
            } catch (Exception $e) {
                error_log("Audit log hatası (system setting): " . $e->getMessage());
            }
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Sistem ayarı kaydetme hatası ($setting_key): " . $e->getMessage());
        return false;
    }
}