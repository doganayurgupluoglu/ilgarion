<?php
// src/functions/enhanced_role_security.php

/**
 * Gelişmiş rol güvenlik kontrolleri ve hiyerarşi yönetimi
 */

/**
 * Kullanıcının belirli bir rolü yönetip yönetemeyeceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $target_role_id Yönetilmek istenen rol ID'si
 * @param int|null $user_id Kontrol edilecek kullanıcı ID'si
 * @return bool Yönetebilirse true
 */
function can_user_manage_role(PDO $pdo, int $target_role_id, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    // Süper admin her rolü yönetebilir
if (is_super_admin($pdo, $user_id)) {
        return true;
    }

    try {
        // Hedef rolün bilgilerini al
        $target_role_query = "SELECT name, priority FROM roles WHERE id = :role_id";
        $stmt_target = execute_safe_query($pdo, $target_role_query, [':role_id' => $target_role_id]);
        $target_role = $stmt_target->fetch();
        
        if (!$target_role) {
            return false;
        }

        // Kullanıcının en yüksek öncelikli rolünü al
        $user_roles = get_user_roles($pdo, $user_id);
        $user_highest_priority = 999;
        
        foreach ($user_roles as $role) {
            if ($role['priority'] < $user_highest_priority) {
                $user_highest_priority = $role['priority'];
            }
        }

        // Süper admin korumalı rolleri kontrol et - SADECE ADMIN
        $super_admin_protected_roles = ['admin']; // member, dis_uye kaldırıldı
        if (in_array($target_role['name'], $super_admin_protected_roles)) {
            return false; // Sadece süper adminler yönetebilir
        }

        // Hiyerarşi kontrolü - sadece daha düşük öncelikli rolleri yönetebilir
        return $target_role['priority'] > $user_highest_priority;
        
    } catch (Exception $e) {
        error_log("Role management check error: " . $e->getMessage());
        return false;
    }
}


/**
 * Kullanıcının belirli bir kullanıcının rollerini yönetip yönetemeyeceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $target_user_id Rolleri yönetilecek kullanıcı ID'si
 * @param int|null $manager_user_id Yönetici kullanıcı ID'si
 * @return bool Yönetebilirse true
 */
function can_user_manage_user_roles(PDO $pdo, int $target_user_id, ?int $manager_user_id = null): bool {
    if ($manager_user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $manager_user_id = $_SESSION['user_id'];
    }

    // Kendini yönetemez (admin rolünü kaldırma gibi)
    if ($target_user_id === $manager_user_id) {
        return false;
    }

    // Süper admin herkesi yönetebilir
    if (is_super_admin($pdo, $manager_user_id)) {
        return true;
    }

    try {
        // Hedef kullanıcının rollerini al
        $target_user_roles = get_user_roles($pdo, $target_user_id);
        $target_highest_priority = 999;
        
        foreach ($target_user_roles as $role) {
            if ($role['priority'] < $target_highest_priority) {
                $target_highest_priority = $role['priority'];
            }
        }

        // Yönetici kullanıcının rollerini al  
        $manager_user_roles = get_user_roles($pdo, $manager_user_id);
        $manager_highest_priority = 999;
        
        foreach ($manager_user_roles as $role) {
            if ($role['priority'] < $manager_highest_priority) {
                $manager_highest_priority = $role['priority'];
            }
        }

        // Sadece daha düşük hiyerarşili kullanıcıları yönetebilir
        return $target_highest_priority > $manager_highest_priority;
        
    } catch (Exception $e) {
        error_log("User role management check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Süper admin yetkilerini kontrol eder ve kısıtlar
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $permission_keys Atanmak istenen yetki anahtarları
 * @param int|null $user_id İşlemi yapan kullanıcı ID'si
 * @return array Filtrelenmiş yetki anahtarları
 */
function filter_super_admin_permissions(PDO $pdo, array $permission_keys, ?int $user_id = null): array {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $user_id = $_SESSION['user_id'];
    }

    // Süper admin ise tüm yetkileri verebilir
    if (is_super_admin($pdo, $user_id)) {
        return $permission_keys;
    }

    // Süper admin özel yetkilerini filtrele
    $super_admin_only_permissions = [
        'admin.super_admin.view',
        'admin.super_admin.manage',
        'admin.audit_log.view',
        'admin.audit_log.export'
    ];

    return array_diff($permission_keys, $super_admin_only_permissions);
}

/**
 * Rol hiyerarşisini görselleştirmek için veri hazırlar
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si (yetki kontrolü için)
 * @return array Hiyerarşi verisi
 */
function get_role_hierarchy_data(PDO $pdo, ?int $user_id = null): array {
    try {
        $query = "
            SELECT r.*, 
                   COUNT(DISTINCT ur.user_id) as user_count,
                   COUNT(DISTINCT rp.permission_id) as permission_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.user_id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            GROUP BY r.id, r.name, r.description, r.color, r.priority
            ORDER BY r.priority ASC
        ";
        
        $stmt = execute_safe_query($pdo, $query);
        $roles = $stmt->fetchAll();
        
        $hierarchy = [];
        $user_highest_priority = 999;
        $is_super_admin_user = false;
        
        if ($user_id) {
            $is_super_admin_user = is_super_admin($pdo, $user_id);
            $user_roles = get_user_roles($pdo, $user_id);
            
            foreach ($user_roles as $role) {
                if ($role['priority'] < $user_highest_priority) {
                    $user_highest_priority = $role['priority'];
                }
            }
        }
        
        foreach ($roles as $role) {
            $role_data = [
                'id' => (int)$role['id'],
                'name' => $role['name'],
                'description' => $role['description'],
                'color' => $role['color'],
                'priority' => (int)$role['priority'],
                'user_count' => (int)$role['user_count'],
                'permission_count' => (int)$role['permission_count'],
                'is_protected' => !is_role_deletable($role['name']),
                'is_name_editable' => is_role_name_editable($role['name']),
                'can_manage' => $user_id ? can_user_manage_role($pdo, $role['id'], $user_id) : false,
                'hierarchy_level' => $role['priority']
            ];
            
            // Süper admin korumalı rolleri işaretle - SADECE ADMIN
            $super_admin_protected = ['admin']; // member, dis_uye kaldırıldı
            $role_data['is_super_admin_only'] = in_array($role['name'], $super_admin_protected);
            
            $hierarchy[] = $role_data;
        }
        
        return [
            'roles' => $hierarchy,
            'user_info' => [
                'highest_priority' => $user_highest_priority,
                'is_super_admin' => $is_super_admin_user,
                'manageable_count' => count(array_filter($hierarchy, function($r) { return $r['can_manage']; }))
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Role hierarchy data error: " . $e->getMessage());
        return ['roles' => [], 'user_info' => []];
    }
}

/**
 * Rol değişikliklerinin güvenlik etkilerini analiz eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $role_id Rol ID'si
 * @param array $old_permissions Eski yetkiler
 * @param array $new_permissions Yeni yetkiler
 * @return array Analiz sonucu
 */
function analyze_role_security_impact(PDO $pdo, int $role_id, array $old_permissions, array $new_permissions): array {
    try {
        // Bu role sahip kullanıcı sayısını al
        $user_count_query = "SELECT COUNT(*) FROM user_roles WHERE role_id = :role_id";
        $stmt = execute_safe_query($pdo, $user_count_query, [':role_id' => $role_id]);
        $affected_users = $stmt->fetchColumn();
        
        // Yetki değişikliklerini analiz et
        $added_permissions = array_diff($new_permissions, $old_permissions);
        $removed_permissions = array_diff($old_permissions, $new_permissions);
        
        // Kritik yetki değişikliklerini kontrol et
        $critical_permissions = [
            'admin.super_admin.manage',
            'admin.roles.delete',
            'admin.users.delete',
            'admin.system.security'
        ];
        
        $critical_added = array_intersect($added_permissions, $critical_permissions);
        $critical_removed = array_intersect($removed_permissions, $critical_permissions);
        
        $impact_level = 'low';
        if (!empty($critical_added) || !empty($critical_removed)) {
            $impact_level = 'high';
        } elseif (count($added_permissions) > 5 || count($removed_permissions) > 5 || $affected_users > 10) {
            $impact_level = 'medium';
        }
        
        return [
            'impact_level' => $impact_level,
            'affected_users' => $affected_users,
            'permissions_added' => count($added_permissions),
            'permissions_removed' => count($removed_permissions),
            'critical_changes' => [
                'added' => $critical_added,
                'removed' => $critical_removed
            ],
            'details' => [
                'added_permissions' => $added_permissions,
                'removed_permissions' => $removed_permissions
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Role security impact analysis error: " . $e->getMessage());
        return [
            'impact_level' => 'unknown',
            'affected_users' => 0,
            'permissions_added' => 0,
            'permissions_removed' => 0,
            'critical_changes' => ['added' => [], 'removed' => []],
            'details' => ['added_permissions' => [], 'removed_permissions' => []]
        ];
    }
}

/**
 * Rol yönetimi için güvenlik logları oluşturur
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $action İşlem türü
 * @param int $role_id Rol ID'si
 * @param array $details Ek detaylar
 * @param int|null $user_id İşlemi yapan kullanıcı
 */
function log_role_security_event(PDO $pdo, string $action, int $role_id, array $details = [], ?int $user_id = null): void {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    $security_context = [
        'action' => $action,
        'role_id' => $role_id,
        'user_ip' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'timestamp' => time(),
        'session_id' => session_id(),
        'details' => $details
    ];
    
    // Yüksek riskli işlemleri özel olarak logla
    $high_risk_actions = ['role_deleted', 'super_admin_permissions_assigned', 'critical_role_modified'];
    
    if (in_array($action, $high_risk_actions)) {
        error_log("HIGH RISK ROLE OPERATION: " . json_encode($security_context));
    }
    
    // Normal audit log
    audit_log($pdo, $user_id, $action, 'role', $role_id, null, $security_context);
}

/**
 * Acil durum için tüm rol cache'lerini temizler
 * @param PDO $pdo Veritabanı bağlantısı
 */
function emergency_clear_all_role_caches(PDO $pdo): void {
    try {
        // Tüm kullanıcıların cache'ini temizle
        $users_query = "SELECT id FROM users WHERE status = 'approved'";
        $stmt = execute_safe_query($pdo, $users_query);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($users as $user_id) {
            clear_user_permissions_cache($user_id);
        }
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'] ?? null, 'emergency_cache_clear', 'system', null, null, [
            'cleared_users' => count($users),
            'reason' => 'Role security maintenance'
        ]);
        
    } catch (Exception $e) {
        error_log("Emergency cache clear error: " . $e->getMessage());
    }
}

/**
 * Rol güvenlik durumunu raporlar
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array Güvenlik raporu
 */
function generate_role_security_report(PDO $pdo): array {
    try {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_roles' => 0,
            'protected_roles' => 0,
            'super_admin_count' => 0,
            'users_with_admin_rights' => 0,
            'orphaned_permissions' => 0,
            'security_issues' => []
        ];
        
        // Toplam rol sayısı
        $total_roles_query = "SELECT COUNT(*) FROM roles";
        $stmt = execute_safe_query($pdo, $total_roles_query);
        $report['total_roles'] = $stmt->fetchColumn();
        
        // Korumalı rol sayısı - SADECE ADMIN
        $protected_roles = ['admin']; // member, dis_uye kaldırıldı
        $report['protected_roles'] = count($protected_roles);
        
        // Süper admin sayısı
        $super_admin_list = get_system_setting($pdo, 'super_admin_users', []);
        $report['super_admin_count'] = count($super_admin_list);
        
        // Admin yetkisine sahip kullanıcı sayısı
        $admin_users_query = "
            SELECT COUNT(DISTINCT ur.user_id) 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE r.name = 'admin'
        ";
        $stmt = execute_safe_query($pdo, $admin_users_query);
        $report['users_with_admin_rights'] = $stmt->fetchColumn();
        
        // Güvenlik sorunlarını kontrol et
        if ($report['super_admin_count'] == 0) {
            $report['security_issues'][] = 'NO_SUPER_ADMIN: Hiç süper admin tanımlanmamış';
        }
        
        if ($report['users_with_admin_rights'] == 0) {
            $report['security_issues'][] = 'NO_ADMIN_USERS: Hiç admin rolüne sahip kullanıcı yok';
        }
        
        if ($report['super_admin_count'] > 5) {
            $report['security_issues'][] = 'TOO_MANY_SUPER_ADMINS: Çok fazla süper admin tanımlanmış';
        }
        
        return $report;
        
    } catch (Exception $e) {
        error_log("Role security report error: " . $e->getMessage());
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => 'Report generation failed: ' . $e->getMessage()
        ];
    }
}
?>