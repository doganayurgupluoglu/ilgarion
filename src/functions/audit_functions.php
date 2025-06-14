<?php
// /src/functions/audit_functions.php

/**
 * Audit log ekleme fonksiyonu
 */
function add_audit_log($pdo, $action, $target_type = null, $target_id = null, $old_values = null, $new_values = null, $user_id = null) {
    try {
        // Session'dan user ID'yi al
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        // IP adresini al
        $ip_address = get_client_ip();
        
        // User agent'ı al
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // JSON'a çevir
        $old_values_json = $old_values ? json_encode($old_values) : null;
        $new_values_json = $new_values ? json_encode($new_values) : null;
        
        $query = "
            INSERT INTO audit_log (
                user_id, action, target_type, target_id, 
                old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                :user_id, :action, :target_type, :target_id,
                :old_values, :new_values, :ip_address, :user_agent, NOW()
            )
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'user_id' => $user_id,
            'action' => $action,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'old_values' => $old_values_json,
            'new_values' => $new_values_json,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcı girişi audit log
 */
function audit_user_login($pdo, $user_id, $username, $login_method = 'standard') {
    add_audit_log($pdo, 'user_login', 'user', $user_id, null, [
        'username' => $username,
        'login_method' => $login_method,
        'timestamp' => date('Y-m-d H:i:s')
    ], $user_id);
}

/**
 * Kullanıcı çıkışı audit log
 */
function audit_user_logout($pdo, $user_id, $username) {
    add_audit_log($pdo, 'user_logout', 'user', $user_id, null, [
        'username' => $username,
        'timestamp' => date('Y-m-d H:i:s')
    ], $user_id);
}

/**
 * Rol oluşturma audit log
 */
function audit_role_created($pdo, $role_id, $role_data, $user_id = null) {
    add_audit_log($pdo, 'role_created', 'role', $role_id, null, $role_data, $user_id);
}

/**
 * Rol güncelleme audit log
 */
function audit_role_updated($pdo, $role_id, $old_data, $new_data, $user_id = null) {
    add_audit_log($pdo, 'role_updated', 'role', $role_id, $old_data, $new_data, $user_id);
}

/**
 * Rol silme audit log
 */
function audit_role_deleted($pdo, $role_id, $role_data, $user_id = null) {
    add_audit_log($pdo, 'role_deleted', 'role', $role_id, $role_data, null, $user_id);
}

/**
 * Rol yetkilerini güncelleme audit log
 */
function audit_role_permissions_updated($pdo, $role_id, $old_permissions, $new_permissions, $user_id = null) {
    add_audit_log($pdo, 'role_permissions_updated', 'role', $role_id, [
        'permissions' => $old_permissions
    ], [
        'permissions' => $new_permissions
    ], $user_id);
}

/**
 * Rol görüntüleme audit log
 */
function audit_role_viewed($pdo, $role_id, $role_name = null, $user_id = null) {
    add_audit_log($pdo, 'role_viewed', 'role', $role_id, null, [
        'role_name' => $role_name,
        'action_type' => 'get_role_data'
    ], $user_id);
}

/**
 * Kullanıcı oluşturma audit log
 */
function audit_user_created($pdo, $created_user_id, $user_data, $creator_user_id = null) {
    // Hassas bilgileri çıkar
    $safe_data = $user_data;
    unset($safe_data['password']);
    unset($safe_data['password_hash']);
    
    add_audit_log($pdo, 'user_created', 'user', $created_user_id, null, $safe_data, $creator_user_id);
}

/**
 * Kullanıcı güncelleme audit log
 */
function audit_user_updated($pdo, $updated_user_id, $old_data, $new_data, $updater_user_id = null) {
    // Hassas bilgileri çıkar
    $safe_old_data = $old_data;
    $safe_new_data = $new_data;
    
    unset($safe_old_data['password']);
    unset($safe_old_data['password_hash']);
    unset($safe_new_data['password']);
    unset($safe_new_data['password_hash']);
    
    add_audit_log($pdo, 'user_updated', 'user', $updated_user_id, $safe_old_data, $safe_new_data, $updater_user_id);
}

/**
 * Kullanıcı silme audit log
 */
function audit_user_deleted($pdo, $deleted_user_id, $user_data, $deleter_user_id = null) {
    // Hassas bilgileri çıkar
    $safe_data = $user_data;
    unset($safe_data['password']);
    unset($safe_data['password_hash']);
    
    add_audit_log($pdo, 'user_deleted', 'user', $deleted_user_id, $safe_data, null, $deleter_user_id);
}

/**
 * Kullanıcı rol atama audit log
 */
function audit_user_role_assigned($pdo, $user_id, $role_id, $role_name, $assigner_user_id = null) {
    add_audit_log($pdo, 'user_role_assigned', 'user', $user_id, null, [
        'role_id' => $role_id,
        'role_name' => $role_name,
        'action_type' => 'role_assignment'
    ], $assigner_user_id);
}

/**
 * Kullanıcı rol kaldırma audit log
 */
function audit_user_role_removed($pdo, $user_id, $role_id, $role_name, $remover_user_id = null) {
    add_audit_log($pdo, 'user_role_removed', 'user', $user_id, [
        'role_id' => $role_id,
        'role_name' => $role_name,
        'action_type' => 'role_removal'
    ], null, $remover_user_id);
}

/**
 * Sayfa erişimi audit log
 */
function audit_page_accessed($pdo, $page_name, $additional_data = null, $user_id = null) {
    $audit_data = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip_address' => get_client_ip(),
        'access_time' => date('Y-m-d H:i:s')
    ];
    
    if ($additional_data) {
        $audit_data = array_merge($audit_data, $additional_data);
    }
    
    add_audit_log($pdo, $page_name . '_accessed', 'page', null, null, $audit_data, $user_id);
}

/**
 * Sistem ayarları değişikliği audit log
 */
function audit_settings_changed($pdo, $setting_key, $old_value, $new_value, $user_id = null) {
    add_audit_log($pdo, 'settings_updated', 'setting', null, [
        'key' => $setting_key,
        'value' => $old_value
    ], [
        'key' => $setting_key,
        'value' => $new_value
    ], $user_id);
}

/**
 * Permission oluşturma audit log
 */
function audit_permission_created($pdo, $permission_id, $permission_data, $user_id = null) {
    add_audit_log($pdo, 'permission_created', 'permission', $permission_id, null, $permission_data, $user_id);
}

/**
 * Permission güncelleme audit log
 */
function audit_permission_updated($pdo, $permission_id, $old_data, $new_data, $user_id = null) {
    add_audit_log($pdo, 'permission_updated', 'permission', $permission_id, $old_data, $new_data, $user_id);
}

/**
 * Permission silme audit log
 */
function audit_permission_deleted($pdo, $permission_id, $permission_data, $user_id = null) {
    add_audit_log($pdo, 'permission_deleted', 'permission', $permission_id, $permission_data, null, $user_id);
}

/**
 * Güvenlik olayı audit log
 */
function audit_security_event($pdo, $event_type, $event_data, $user_id = null) {
    add_audit_log($pdo, 'security_' . $event_type, 'security', null, null, $event_data, $user_id);
}

/**
 * Başarısız giriş denemesi audit log
 */
function audit_failed_login($pdo, $username, $reason = 'invalid_credentials') {
    add_audit_log($pdo, 'login_failed', 'security', null, null, [
        'username' => $username,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ], null);
}

/**
 * Şifre değişikliği audit log
 */
function audit_password_changed($pdo, $user_id, $username, $changed_by_admin = false, $changer_user_id = null) {
    add_audit_log($pdo, 'password_changed', 'user', $user_id, null, [
        'username' => $username,
        'changed_by_admin' => $changed_by_admin,
        'timestamp' => date('Y-m-d H:i:s')
    ], $changer_user_id ?? $user_id);
}

/**
 * Email değişikliği audit log
 */
function audit_email_changed($pdo, $user_id, $old_email, $new_email, $user_id_changer = null) {
    add_audit_log($pdo, 'email_changed', 'user', $user_id, [
        'email' => $old_email
    ], [
        'email' => $new_email
    ], $user_id_changer ?? $user_id);
}

/**
 * Kullanıcı durumu değişikliği audit log
 */
function audit_user_status_changed($pdo, $user_id, $username, $old_status, $new_status, $changer_user_id = null) {
    add_audit_log($pdo, 'user_status_changed', 'user', $user_id, [
        'username' => $username,
        'status' => $old_status
    ], [
        'username' => $username,
        'status' => $new_status
    ], $changer_user_id);
}

/**
 * Veri export audit log
 */
function audit_data_exported($pdo, $export_type, $export_params, $user_id = null) {
    add_audit_log($pdo, 'data_exported', 'export', null, null, [
        'export_type' => $export_type,
        'parameters' => $export_params,
        'timestamp' => date('Y-m-d H:i:s')
    ], $user_id);
}

/**
 * Dosya yükleme audit log
 */
function audit_file_uploaded($pdo, $file_path, $file_info, $user_id = null) {
    add_audit_log($pdo, 'file_uploaded', 'file', null, null, [
        'file_path' => $file_path,
        'file_info' => $file_info,
        'timestamp' => date('Y-m-d H:i:s')
    ], $user_id);
}

/**
 * Dosya silme audit log
 */
function audit_file_deleted($pdo, $file_path, $file_info, $user_id = null) {
    add_audit_log($pdo, 'file_deleted', 'file', null, [
        'file_path' => $file_path,
        'file_info' => $file_info
    ], null, $user_id);
}

// get_client_ip() fonksiyonu auth_functions.php'de zaten tanımlı

/**
 * Audit log istatistiklerini al
 */
function get_audit_stats($pdo, $days = 30) {
    try {
        $query = "
            SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT action) as unique_actions,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) THEN 1 END) as last_period
            FROM audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Audit stats error: " . $e->getMessage());
        return [
            'total_logs' => 0,
            'unique_users' => 0,
            'unique_actions' => 0,
            'last_24h' => 0,
            'last_7_days' => 0,
            'last_period' => 0
        ];
    }
}

/**
 * En çok kullanılan action'ları al
 */
function get_top_actions($pdo, $limit = 10, $days = 30) {
    try {
        $query = "
            SELECT action, COUNT(*) as count
            FROM audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY action
            ORDER BY count DESC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Top actions error: " . $e->getMessage());
        return [];
    }
}

/**
 * En aktif kullanıcıları al
 */
function get_top_users($pdo, $limit = 10, $days = 7) {
    try {
        $query = "
            SELECT u.username, COUNT(al.id) as activity_count
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND al.user_id IS NOT NULL
            GROUP BY al.user_id, u.username
            ORDER BY activity_count DESC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Top users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Güvenlik olaylarını al
 */
function get_security_events($pdo, $limit = 50, $days = 7) {
    try {
        $query = "
            SELECT 
                al.id,
                al.user_id,
                u.username,
                al.action,
                al.new_values,
                al.ip_address,
                al.created_at
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.action LIKE 'security_%' 
            OR al.action LIKE 'login_failed'
            OR al.action LIKE '%_failed'
            AND al.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY al.created_at DESC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Security events error: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcı aktivite geçmişini al
 */
function get_user_activity_history($pdo, $user_id, $limit = 100, $days = 30) {
    try {
        $query = "
            SELECT 
                id,
                action,
                target_type,
                target_id,
                new_values,
                ip_address,
                created_at
            FROM audit_log
            WHERE user_id = :user_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("User activity history error: " . $e->getMessage());
        return [];
    }
}

/**
 * Audit log temizleme (eski kayıtları sil)
 */
function cleanup_old_audit_logs($pdo, $days_to_keep = 365) {
    try {
        $query = "
            DELETE FROM audit_log
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':days', $days_to_keep, PDO::PARAM_INT);
        $stmt->execute();
        
        $deleted_count = $stmt->rowCount();
        
        // Temizleme işlemini logla
        add_audit_log($pdo, 'audit_log_cleanup', 'system', null, null, [
            'deleted_records' => $deleted_count,
            'days_kept' => $days_to_keep,
            'cleanup_date' => date('Y-m-d H:i:s')
        ]);
        
        return $deleted_count;
        
    } catch (Exception $e) {
        error_log("Audit log cleanup error: " . $e->getMessage());
        return false;
    }
}
?>