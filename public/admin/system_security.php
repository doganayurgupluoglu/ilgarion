<?php
// public/admin/system_security.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Yetki kontrolü - sistem güvenlik yönetimi yetkisi gerekli
require_permission($pdo, 'admin.system.security');

$page_title = "Sistem Güvenlik Yönetimi";

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// İşlem kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'clear_all_caches':
                emergency_clear_all_role_caches($pdo);
                $_SESSION['success_message'] = "Tüm kullanıcı cache'leri temizlendi.";
                break;
                
            case 'regenerate_sessions':
                // Aktif sessionları yenile
                session_regenerate_id(true);
                $_SESSION['success_message'] = "Session güvenliği yenilendi.";
                break;
                
            case 'cleanup_old_audit_logs':
                $days = (int)($_POST['cleanup_days'] ?? 90);
                if ($days < 30) $days = 30; // Minimum 30 gün
                
                $cleanup_query = "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
                $stmt = $pdo->prepare($cleanup_query);
                $stmt->execute([':days' => $days]);
                $deleted_count = $stmt->rowCount();
                
                audit_log($pdo, $_SESSION['user_id'], 'audit_log_cleanup', 'system', null, null, [
                    'deleted_count' => $deleted_count,
                    'cleanup_days' => $days
                ]);
                
                $_SESSION['success_message'] = "{$deleted_count} eski audit log kaydı temizlendi.";
                break;
                
            case 'security_scan':
                // Güvenlik taraması başlat
                $scan_results = perform_security_scan($pdo);
                $_SESSION['scan_results'] = $scan_results;
                $_SESSION['success_message'] = "Güvenlik taraması tamamlandı.";
                break;
                
            default:
                $_SESSION['error_message'] = "Geçersiz işlem.";
        }
    } catch (Exception $e) {
        error_log("System security action error: " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında hata oluştu: " . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Güvenlik raporu oluştur
$security_report = generate_role_security_report($pdo);

// Son güvenlik olayları
$recent_security_events = [];
try {
    $security_query = "
        SELECT * FROM audit_log 
        WHERE (action LIKE '%security%' OR action LIKE '%unauthorized%' OR action LIKE '%violation%') 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $stmt = execute_safe_query($pdo, $security_query);
    $recent_security_events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Recent security events query error: " . $e->getMessage());
}

// Aktif session sayısı
$active_sessions_count = 0;
try {
    $session_query = "
        SELECT COUNT(DISTINCT user_id) as active_count 
        FROM audit_log 
        WHERE action = 'user_login' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $stmt = execute_safe_query($pdo, $session_query);
    $result = $stmt->fetch();
    $active_sessions_count = $result['active_count'] ?? 0;
} catch (Exception $e) {
    error_log("Active sessions count error: " . $e->getMessage());
}

// Failed login attempts (son 24 saat)
$failed_logins = [];
try {
    $failed_query = "
        SELECT ip_address, COUNT(*) as attempt_count, MAX(created_at) as last_attempt
        FROM audit_log 
        WHERE action LIKE '%login%' AND action LIKE '%failed%' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip_address
        HAVING attempt_count >= 3
        ORDER BY attempt_count DESC
        LIMIT 10
    ";
    $stmt = execute_safe_query($pdo, $failed_query);
    $failed_logins = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed logins query error: " . $e->getMessage());
}

// Sistem ayarları
$system_settings = [];
try {
    $settings_query = "SELECT * FROM system_settings ORDER BY setting_key";
    $stmt = execute_safe_query($pdo, $settings_query);
    $system_settings = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("System settings query error: " . $e->getMessage());
}

/**
 * Güvenlik taraması gerçekleştirir
 */
function perform_security_scan(PDO $pdo): array {
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'issues' => [],
        'warnings' => [],
        'info' => []
    ];
    
    try {
        // 1. Süper admin kontrolü
        $super_admin_list = get_system_setting($pdo, 'super_admin_users', []);
        if (empty($super_admin_list)) {
            $results['issues'][] = 'Hiç süper admin tanımlanmamış!';
        } elseif (count($super_admin_list) > 5) {
            $results['warnings'][] = 'Çok fazla süper admin var (' . count($super_admin_list) . ')';
        }
        
        // 2. Admin rolleri kontrolü
        $admin_users_query = "
            SELECT COUNT(*) as admin_count 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE r.name = 'admin'
        ";
        $stmt = execute_safe_query($pdo, $admin_users_query);
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count == 0) {
            $results['issues'][] = 'Hiç admin rolüne sahip kullanıcı yok!';
        }
        
        // 3. Onaylanmamış kullanıcılar
        $pending_users_query = "SELECT COUNT(*) FROM users WHERE status = 'pending'";
        $stmt = execute_safe_query($pdo, $pending_users_query);
        $pending_count = $stmt->fetchColumn();
        
        if ($pending_count > 20) {
            $results['warnings'][] = "Çok fazla onay bekleyen kullanıcı var ({$pending_count})";
        }
        
        // 4. Eski audit log kayıtları
        $old_logs_query = "
            SELECT COUNT(*) 
            FROM audit_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ";
        $stmt = execute_safe_query($pdo, $old_logs_query);
        $old_logs_count = $stmt->fetchColumn();
        
        if ($old_logs_count > 10000) {
            $results['warnings'][] = "Çok fazla eski audit log kaydı var ({$old_logs_count})";
        }
        
        // 5. Yetki kontrolü
        $orphaned_permissions_query = "
            SELECT COUNT(*) 
            FROM role_permissions rp 
            LEFT JOIN roles r ON rp.role_id = r.id 
            WHERE r.id IS NULL
        ";
        $stmt = execute_safe_query($pdo, $orphaned_permissions_query);
        $orphaned_count = $stmt->fetchColumn();
        
        if ($orphaned_count > 0) {
            $results['issues'][] = "Yetim kalmış rol yetkileri var ({$orphaned_count})";
        }
        
        // 6. Başarılı kontroller
        $results['info'][] = "Güvenlik taraması tamamlandı";
        $results['info'][] = "Toplam kullanıcı sayısı: " . get_total_user_count($pdo);
        $results['info'][] = "Aktif roller: " . get_active_role_count($pdo);
        
    } catch (Exception $e) {
        $results['issues'][] = "Tarama sırasında hata: " . $e->getMessage();
    }
    
    return $results;
}

function get_total_user_count(PDO $pdo): int {
    try {
        $stmt = execute_safe_query($pdo, "SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function get_active_role_count(PDO $pdo): int {
    try {
        $stmt = execute_safe_query($pdo, "SELECT COUNT(*) FROM roles");
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.security-container {
    width: 100%;
    max-width: 1400px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
}

.page-header-security {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.page-title-security {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.security-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.security-card {
    background-color: var(--charcoal);
    border-radius: 12px;
    padding: 25px;
    border: 1px solid var(--darker-gold-1);
    transition: all 0.3s ease;
}

.security-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    border-color: var(--gold);
}

.security-card.critical {
    border-left: 4px solid var(--red);
}

.security-card.warning {
    border-left: 4px solid var(--warning);
}

.security-card.safe {
    border-left: 4px solid var(--turquase);
}

.security-card.info {
    border-left: 4px solid var(--blue);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.card-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--lighter-grey);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.card-icon.critical { background-color: var(--red); }
.card-icon.warning { background-color: var(--warning); }
.card-icon.safe { background-color: var(--turquase); }
.card-icon.info { background-color: var(--blue); }

.security-metric {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--darker-gold-2);
}

.security-metric:last-child {
    border-bottom: none;
}

.metric-label {
    color: var(--light-grey);
    font-size: 0.9rem;
}

.metric-value {
    font-weight: bold;
    font-size: 1.1rem;
}

.metric-value.critical { color: var(--red); }
.metric-value.warning { color: var(--warning); }
.metric-value.safe { color: var(--turquase); }

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.security-actions {
    background-color: var(--darker-gold-2);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.security-events {
    background-color: var(--charcoal);
    padding: 25px;
    border-radius: 12px;
    border: 1px solid var(--darker-gold-1);
}

.events-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.events-table th,
.events-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--darker-gold-2);
    font-size: 0.9rem;
}

.events-table th {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
}

.events-table tbody tr:hover {
    background-color: var(--darker-gold-2);
}

.event-critical {
    background-color: rgba(231, 76, 60, 0.1);
    border-left: 3px solid var(--red);
}

.scan-results {
    background-color: var(--charcoal);
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
    border: 1px solid var(--darker-gold-1);
}

.scan-result-item {
    padding: 10px 15px;
    margin: 8px 0;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.scan-result-item.issue {
    background-color: rgba(231, 76, 60, 0.1);
    border: 1px solid var(--red);
    color: var(--red);
}

.scan-result-item.warning {
    background-color: rgba(243, 156, 18, 0.1);
    border: 1px solid var(--warning);
    color: var(--warning);
}

.scan-result-item.info {
    background-color: rgba(61, 166, 162, 0.1);
    border: 1px solid var(--turquase);
    color: var(--turquase);
}

.cleanup-form {
    display: flex;
    gap: 15px;
    align-items: end;
    margin-top: 15px;
}

.form-group-small {
    flex: 1;
}

.form-group-small label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.form-control-small {
    padding: 8px 12px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 4px;
    color: var(--white);
    font-size: 0.9rem;
    width: 100%;
}

.system-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-card {
    background-color: var(--darker-gold-2);
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.info-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 5px;
}

.info-label {
    color: var(--lighter-grey);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.failed-logins {
    background-color: var(--charcoal);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
    margin-top: 20px;
}

.ip-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
}

.ip-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    border-left: 3px solid var(--red);
}

.ip-address {
    font-family: monospace;
    color: var(--red);
    font-weight: bold;
}

.attempt-count {
    background-color: var(--red);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
}
</style>

<main class="main-content">
    <div class="container admin-container security-container">
        <div class="page-header-security">
            <h1 class="page-title-security">
                <i class="fas fa-shield-alt"></i>
                <?php echo htmlspecialchars($page_title); ?>
            </h1>
            <div class="header-actions">
                <a href="audit_log.php" class="btn btn-sm btn-info">
                    <i class="fas fa-clipboard-list"></i> Audit Log
                </a>
                <a href="manage_roles.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-user-tag"></i> Rol Yönetimi
                </a>
            </div>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <!-- Güvenlik Özeti -->
        <div class="security-grid">
            <div class="security-card <?php echo ($security_report['super_admin_count'] == 0) ? 'critical' : 'safe'; ?>">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-icon <?php echo ($security_report['super_admin_count'] == 0) ? 'critical' : 'safe'; ?>">
                            <i class="fas fa-crown"></i>
                        </div>
                        Süper Admin Durumu
                    </h3>
                </div>
                <div class="security-metric">
                    <span class="metric-label">Süper Admin Sayısı:</span>
                    <span class="metric-value <?php echo ($security_report['super_admin_count'] == 0) ? 'critical' : 'safe'; ?>">
                        <?php echo $security_report['super_admin_count']; ?>
                    </span>
                </div>
                <div class="security-metric">
                    <span class="metric-label">Admin Kullanıcıları:</span>
                    <span class="metric-value <?php echo ($security_report['users_with_admin_rights'] == 0) ? 'critical' : 'safe'; ?>">
                        <?php echo $security_report['users_with_admin_rights']; ?>
                    </span>
                </div>
            </div>

            <div class="security-card safe">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-icon safe">
                            <i class="fas fa-users"></i>
                        </div>
                        Kullanıcı Durumu
                    </h3>
                </div>
                <div class="security-metric">
                    <span class="metric-label">Aktif Sessionlar:</span>
                    <span class="metric-value safe"><?php echo $active_sessions_count; ?></span>
                </div>
                <div class="security-metric">
                    <span class="metric-label">Toplam Rol:</span>
                    <span class="metric-value safe"><?php echo $security_report['total_roles']; ?></span>
                </div>
            </div>

            <div class="security-card <?php echo (count($failed_logins) > 0) ? 'warning' : 'safe'; ?>">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-icon <?php echo (count($failed_logins) > 0) ? 'warning' : 'safe'; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        Güvenlik Tehditleri
                    </h3>
                </div>
                <div class="security-metric">
                    <span class="metric-label">Başarısız Login (24h):</span>
                    <span class="metric-value <?php echo (count($failed_logins) > 0) ? 'warning' : 'safe'; ?>">
                        <?php echo count($failed_logins); ?> IP
                    </span>
                </div>
                <div class="security-metric">
                    <span class="metric-label">Güvenlik Olayları:</span>
                    <span class="metric-value <?php echo (count($recent_security_events) > 0) ? 'warning' : 'safe'; ?>">
                        <?php echo count($recent_security_events); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Güvenlik İşlemleri -->
        <div class="security-actions">
            <h3 style="color: var(--gold); margin-bottom: 20px;">
                <i class="fas fa-tools"></i> Güvenlik İşlemleri
            </h3>
            
            <div class="action-buttons">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="clear_all_caches">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Tüm kullanıcı cache\'lerini temizlemek istediğinizden emin misiniz?')">
                        <i class="fas fa-broom"></i> Cache Temizle
                    </button>
                </form>

                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="regenerate_sessions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Session Yenile
                    </button>
                </form>

                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="security_scan">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-search"></i> Güvenlik Taraması
                    </button>
                </form>
            </div>

            <!-- ACİL DURUM İŞLEMLERİ - Sadece Süper Adminler -->
            <?php if (is_super_admin($pdo)): ?>
            <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #ff416c, #ff4b2b); border-radius: 8px; border: 2px solid var(--red);">
                <h4 style="color: white; margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    ACİL DURUM İŞLEMLERİ
                    <i class="fas fa-crown" style="color: #ffd700;"></i>
                </h4>
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 20px; font-size: 0.9rem;">
                    ⚠️ Bu işlemler sadece süper adminler tarafından kullanılabilir ve geri alınamaz!
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <!-- Tam Sistem Kilidi -->
                    <button onclick="emergencyLockdown()" class="btn btn-danger" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <i class="fas fa-lock"></i> TAM SİSTEM KİLİDİ
                    </button>
                    
                    <!-- Zorla Tüm Çıkış -->
                    <button onclick="forceLogoutAll()" class="btn btn-warning" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                        <i class="fas fa-sign-out-alt"></i> ZORLA TÜMÜNÜ ÇIKAR
                    </button>
                    
                    <!-- Kısmi Kilit -->
                    <button onclick="openPartialLockdownModal()" class="btn btn-info" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <i class="fas fa-user-lock"></i> KISMİ KİLİT
                    </button>
                    
                    <!-- Sistem Kilidini Kaldır -->
                    <button onclick="emergencyUnlock()" class="btn btn-success" style="background: linear-gradient(135deg, #27ae60, #229954);">
                        <i class="fas fa-unlock"></i> KİLİDİ KALDIR
                    </button>
                </div>
                
                <!-- Acil Kullanıcı Askıya Alma -->
                <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 6px;">
                    <h5 style="color: white; margin: 0 0 10px 0;">Acil Kullanıcı Askıya Alma:</h5>
                    <div style="display: flex; gap: 10px; align-items: end;">
                        <div style="flex: 1;">
                            <input type="number" id="emergencyUserId" placeholder="Kullanıcı ID" 
                                   style="padding: 8px; border: none; border-radius: 4px; width: 100%;">
                        </div>
                        <div style="flex: 2;">
                            <input type="text" id="emergencyReason" placeholder="Askıya alma nedeni" 
                                   style="padding: 8px; border: none; border-radius: 4px; width: 100%;">
                        </div>
                        <button onclick="emergencySuspendUser()" class="btn btn-danger">
                            <i class="fas fa-user-slash"></i> ASKIYA AL
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Audit Log Temizleme -->
            <form method="POST" class="cleanup-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="cleanup_old_audit_logs">
                <div class="form-group-small">
                    <label for="cleanup_days">Eski Audit Log Temizleme:</label>
                    <input type="number" id="cleanup_days" name="cleanup_days" 
                           class="form-control-small" value="90" min="30" max="365">
                </div>
                <button type="submit" class="btn btn-danger" 
                        onclick="return confirm('Bu işlem geri alınamaz! Devam etmek istediğinizden emin misiniz?')">
                    <i class="fas fa-trash"></i> Temizle
                </button>
            </form>
        </div>

        <!-- Tarama Sonuçları -->
        <?php if (isset($_SESSION['scan_results'])): ?>
        <div class="scan-results">
            <h3 style="color: var(--gold); margin-bottom: 15px;">
                <i class="fas fa-search"></i> Son Güvenlik Taraması Sonuçları
            </h3>
            <p style="color: var(--light-grey); margin-bottom: 15px;">
                Tarih: <?php echo $_SESSION['scan_results']['timestamp']; ?>
            </p>
            
            <?php foreach ($_SESSION['scan_results']['issues'] as $issue): ?>
            <div class="scan-result-item issue">
                <i class="fas fa-exclamation-circle"></i>
                <strong>SORUN:</strong> <?php echo htmlspecialchars($issue); ?>
            </div>
            <?php endforeach; ?>
            
            <?php foreach ($_SESSION['scan_results']['warnings'] as $warning): ?>
            <div class="scan-result-item warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>UYARI:</strong> <?php echo htmlspecialchars($warning); ?>
            </div>
            <?php endforeach; ?>
            
            <?php foreach ($_SESSION['scan_results']['info'] as $info): ?>
            <div class="scan-result-item info">
                <i class="fas fa-info-circle"></i>
                <strong>BİLGİ:</strong> <?php echo htmlspecialchars($info); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php unset($_SESSION['scan_results']); ?>
        <?php endif; ?>

        <!-- Son Güvenlik Olayları -->
        <?php if (!empty($recent_security_events)): ?>
        <div class="security-events">
            <h3 style="color: var(--gold); margin-bottom: 15px;">
                <i class="fas fa-exclamation-triangle"></i> Son Güvenlik Olayları
            </h3>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Kullanıcı</th>
                        <th>İşlem</th>
                        <th>IP Adresi</th>
                        <th>Detaylar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_security_events as $event): ?>
                    <tr class="<?php echo strpos($event['action'], 'violation') !== false ? 'event-critical' : ''; ?>">
                        <td><?php echo date('d.m.Y H:i', strtotime($event['created_at'])); ?></td>
                        <td><?php echo $event['user_id'] ? "ID: {$event['user_id']}" : 'Sistem'; ?></td>
                        <td><?php echo htmlspecialchars($event['action']); ?></td>
                        <td style="font-family: monospace;"><?php echo htmlspecialchars($event['ip_address'] ?: '-'); ?></td>
                        <td>
                            <?php if ($event['new_values']): ?>
                            <button onclick="toggleDetails(this)" class="btn btn-sm btn-outline-secondary">Detay</button>
                            <div style="display: none; margin-top: 10px; font-size: 0.8rem; background: var(--darker-gold-2); padding: 10px; border-radius: 4px;">
                                <pre><?php echo htmlspecialchars(json_encode(json_decode($event['new_values']), JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Başarısız Login Denemeleri -->
        <?php if (!empty($failed_logins)): ?>
        <div class="failed-logins">
            <h3 style="color: var(--red); margin-bottom: 15px;">
                <i class="fas fa-ban"></i> Şüpheli IP Adresleri (Son 24 Saat)
            </h3>
            <div class="ip-list">
                <?php foreach ($failed_logins as $failed): ?>
                <div class="ip-item">
                    <div>
                        <div class="ip-address"><?php echo htmlspecialchars($failed['ip_address']); ?></div>
                        <small style="color: var(--light-grey);">
                            Son deneme: <?php echo date('d.m.Y H:i', strtotime($failed['last_attempt'])); ?>
                        </small>
                    </div>
                    <div class="attempt-count"><?php echo $failed['attempt_count']; ?> deneme</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sistem Bilgileri -->
        <div style="margin-top: 30px;">
            <h3 style="color: var(--gold); margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> Sistem Bilgileri
            </h3>
            <div class="system-info-grid">
                <div class="info-card">
                    <div class="info-number"><?php echo get_total_user_count($pdo); ?></div>
                    <div class="info-label">Toplam Kullanıcı</div>
                </div>
                <div class="info-card">
                    <div class="info-number"><?php echo get_active_role_count($pdo); ?></div>
                    <div class="info-label">Aktif Rol</div>
                </div>
                <div class="info-card">
                    <div class="info-number">
                        <?php 
                        try {
                            $stmt = execute_safe_query($pdo, "SELECT COUNT(*) FROM permissions WHERE is_active = 1");
                            echo $stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo "N/A";
                        }
                        ?>
                    </div>
                    <div class="info-label">Aktif Yetki</div>
                </div>
                <div class="info-card">
                    <div class="info-number">
                        <?php 
                        try {
                            $stmt = execute_safe_query($pdo, "SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()");
                            echo $stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo "N/A";
                        }
                        ?>
                    </div>
                    <div class="info-label">Bugünkü Aktivite</div>
                </div>
            </div>
        </div>

        <!-- Sistem Ayarları -->
        <?php if (!empty($system_settings)): ?>
        <div style="margin-top: 30px; background-color: var(--charcoal); padding: 20px; border-radius: 8px; border: 1px solid var(--darker-gold-1);">
            <h3 style="color: var(--gold); margin-bottom: 15px;">
                <i class="fas fa-cog"></i> Sistem Ayarları
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <?php foreach ($system_settings as $setting): ?>
                <div style="background-color: var(--darker-gold-2); padding: 15px; border-radius: 6px;">
                    <div style="color: var(--gold); font-weight: bold; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($setting['setting_key']); ?>
                    </div>
                    <div style="color: var(--light-grey); font-size: 0.9rem; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($setting['description'] ?: 'Açıklama yok'); ?>
                    </div>
                    <div style="font-family: monospace; font-size: 0.8rem; color: var(--lighter-grey); background: var(--grey); padding: 8px; border-radius: 4px;">
                        <?php 
                        if ($setting['setting_type'] === 'json') {
                            $decoded = json_decode($setting['setting_value'], true);
                            echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT));
                        } else {
                            echo htmlspecialchars($setting['setting_value']);
                        }
                        ?>
                    </div>
                    <div style="margin-top: 5px; font-size: 0.8rem; color: var(--light-grey);">
                        Tip: <?php echo htmlspecialchars($setting['setting_type']); ?> | 
                        Güncelleme: <?php echo date('d.m.Y H:i', strtotime($setting['updated_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Güvenlik İpuçları -->
        <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1)); border-radius: 8px; border: 1px solid var(--gold);">
            <h4 style="color: var(--gold); margin: 0 0 15px 0;">
                <i class="fas fa-lightbulb"></i> Güvenlik İpuçları
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div>
                    <h5 style="color: var(--light-gold); margin: 0 0 8px 0;">Düzenli Kontroller:</h5>
                    <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                        <li>Haftada bir güvenlik taraması yapın</li>
                        <li>Audit log'ları düzenli inceleyin</li>
                        <li>Başarısız login denemelerini takip edin</li>
                        <li>Rol ve yetki değişikliklerini monitör edin</li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: var(--light-gold); margin: 0 0 8px 0;">Cache Yönetimi:</h5>
                    <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                        <li>Rol değişikliklerinden sonra cache temizleyin</li>
                        <li>Şüpheli aktivite sonrası tüm cache'leri temizleyin</li>
                        <li>Session'ları düzenli yenileyin</li>
                    </ul>
                </div>
                <div>
                    <h5 style="color: var(--light-gold); margin: 0 0 8px 0;">Acil Durum:</h5>
                    <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey); font-size: 0.9rem;">
                        <li>Şüpheli aktivite durumunda tüm session'ları sonlandırın</li>
                        <li>Kritik yetki değişikliklerini audit log'dan takip edin</li>
                        <li>IP bazlı engellemeler için fail2ban kullanın</li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background-color: rgba(231, 76, 60, 0.1); border-radius: 4px; border: 1px solid var(--red);">
                <strong style="color: var(--red);">⚠️ Önemli Hatırlatma:</strong>
                <span style="color: var(--lighter-grey); font-size: 0.9rem; margin-left: 10px;">
                    Bu sayfadaki işlemler sistem güvenliğini doğrudan etkiler. Lütfen dikkatli kullanın ve 
                    kritik işlemlerden önce yedek alın. Tüm işlemler audit log'a kaydedilir.
                </span>
            </div>
        </div>

        <!-- Son Güncelleme Bilgisi -->
        <div style="text-align: center; margin-top: 30px; padding: 15px; color: var(--light-grey); font-size: 0.8rem;">
            <i class="fas fa-clock"></i> 
            Sayfa son güncelleme: <?php echo date('d.m.Y H:i:s'); ?> | 
            PHP Bellek Kullanımı: <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB
        </div>
    </div>

    <!-- Kısmi Kilit Modal -->
    <div id="partialLockdownModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px; background-color: var(--charcoal);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--red), var(--danger-color));">
                <h2 class="modal-title" style="color: white;">
                    <i class="fas fa-user-lock"></i> Kısmi Sistem Kilidi
                </h2>
                <button type="button" class="modal-close" onclick="closePartialLockdownModal()" style="color: white;">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: rgba(231, 76, 60, 0.1); padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid var(--red);">
                    <strong style="color: var(--red);">⚠️ DİKKAT:</strong>
                    <span style="color: var(--lighter-grey); font-size: 0.9rem; margin-left: 10px;">
                        Seçilen kullanıcılar anında askıya alınacak ve oturumları sonlandırılacaktır!
                    </span>
                </div>
                
                <div class="form-group-modal">
                    <label style="color: var(--lighter-grey);">Askıya Alınacak Kullanıcı ID'leri (virgülle ayırın):</label>
                    <textarea id="partialLockdownUsers" rows="3" style="width: 100%; padding: 10px; background: var(--grey); border: 1px solid var(--darker-gold-1); border-radius: 4px; color: var(--white);" 
                              placeholder="Örnek: 5, 8, 12, 15"></textarea>
                    <small style="color: var(--light-grey); font-size: 0.8rem;">
                        Süper adminler askıya alınamaz. Geçersiz ID'ler otomatik olarak atlanır.
                    </small>
                </div>
                
                <div class="form-group-modal">
                    <label style="color: var(--lighter-grey);">Askıya Alma Nedeni:</label>
                    <input type="text" id="partialLockdownReason" style="width: 100%; padding: 10px; background: var(--grey); border: 1px solid var(--darker-gold-1); border-radius: 4px; color: var(--white);" 
                           placeholder="Güvenlik ihlali, şüpheli aktivite vb." value="Kısmi acil durum kilidi">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePartialLockdownModal()">İptal</button>
                <button type="button" class="btn btn-danger" onclick="executePartialLockdown()">
                    <i class="fas fa-user-lock"></i> Askıya Al
                </button>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('System Security sayfası yüklendi');
    
    // Auto-refresh için timer (5 dakikada bir)
    let refreshTimer = setInterval(function() {
        const refreshButton = document.createElement('div');
        refreshButton.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--turquase);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            z-index: 1000;
            cursor: pointer;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        `;
        refreshButton.innerHTML = '<i class="fas fa-sync-alt"></i> Güvenlik verilerini yenile';
        refreshButton.onclick = function() {
            window.location.reload();
        };
        
        document.body.appendChild(refreshButton);
        
        setTimeout(() => {
            if (refreshButton.parentNode) {
                refreshButton.parentNode.removeChild(refreshButton);
            }
        }, 10000);
    }, 300000); // 5 dakika
    
    // Sayfa kapatılırken timer'ı temizle
    window.addEventListener('beforeunload', function() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
    });
    
    // Form submission'ları için loading göster
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
                
                // 30 saniye sonra geri yükle (hata durumu için)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 30000);
            }
        });
    });
    
    // Gerçek zamanlı güvenlik durumu kontrolü
    function checkSecurityStatus() {
        fetch('/src/api/security_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.critical_issues > 0) {
                    showSecurityAlert('Kritik güvenlik sorunu tespit edildi!', 'danger');
                }
            })
            .catch(error => {
                console.log('Security status check failed:', error);
            });
    }
    
    // 2 dakikada bir güvenlik durumu kontrol et
    setInterval(checkSecurityStatus, 120000);
    
    // İlk kontrol
    setTimeout(checkSecurityStatus, 5000);
});

function toggleDetails(button) {
    const details = button.nextElementSibling;
    if (details.style.display === 'none') {
        details.style.display = 'block';
        button.textContent = 'Gizle';
    } else {
        details.style.display = 'none';
        button.textContent = 'Detay';
    }
}

function showSecurityAlert(message, type = 'warning') {
    const alert = document.createElement('div');
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'danger' ? 'var(--red)' : 'var(--warning)'};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        z-index: 10001;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        font-weight: bold;
        animation: slideDown 0.3s ease;
    `;
    alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => {
                alert.parentNode.removeChild(alert);
            }, 300);
        }
    }, 5000);
}

// CSS animasyonları
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    @keyframes slideUp {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }
    
    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid var(--darker-gold-1);
        border-top: 5px solid var(--gold);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Emergency functions
window.emergencyLockdown = function() {
    if (confirm('ACİL DURUM: Tüm kullanıcı session\'larını sonlandır ve sistemi kilitlemek istediğinizden emin misiniz?')) {
        fetch('/src/api/emergency_lockdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'lockdown' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Acil durum kilidi aktif edildi!');
                window.location.reload();
            }
        });
    }
};

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+R: Güvenlik taraması
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        const scanForm = document.querySelector('form input[value="security_scan"]');
        if (scanForm) {
            scanForm.parentNode.submit();
        }
    }
    
    // Ctrl+Shift+C: Cache temizle
    if (e.ctrlKey && e.shiftKey && e.key === 'C') {
        e.preventDefault();
        const cacheForm = document.querySelector('form input[value="clear_all_caches"]');
        if (cacheForm && confirm('Tüm cache\'leri temizlemek istediğinizden emin misiniz?')) {
            cacheForm.parentNode.submit();
        }
    }
});

console.log('System Security JavaScript yüklendi - Güvenlik modülü aktif');

// ACİL DURUM FONKSİYONLARI

// Tam sistem kilidi
function emergencyLockdown() {
    if (confirm('⚠️ ACİL DURUM: TAM SİSTEM KİLİDİ\n\nBu işlem:\n• Tüm kullanıcıları (süper adminler hariç) askıya alacak\n• Tüm session\'ları sonlandıracak\n• Sistemi tamamen kilitleyecek\n\nDevam etmek istediğinizden EMİN misiniz?')) {
        if (confirm('Bu işlem GERİ ALINAMAZ!\n\nSon kez soruyorum: Tam sistem kilidini aktifleştirmek istediğinizden emin misiniz?')) {
            showLoadingOverlay('Sistem kilitleniyor...');
            
            fetch('/src/api/emergency_lockdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'lockdown' })
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingOverlay();
                if (data.success) {
                    showEmergencyAlert('SİSTEM KİLİTLENDİ!', 'critical');
                    setTimeout(() => window.location.reload(), 3000);
                } else {
                    showEmergencyAlert('Hata: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoadingOverlay();
                showEmergencyAlert('Bağlantı hatası: ' + error.message, 'error');
            });
        }
    }
}

// Sistem kilidini kaldır
function emergencyUnlock() {
    if (confirm('Sistem kilidini kaldırmak istediğinizden emin misiniz?\n\nBu işlem askıya alınan kullanıcıları geri yükleyecektir.')) {
        showLoadingOverlay('Sistem kilidi kaldırılıyor...');
        
        fetch('/src/api/emergency_lockdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'unlock' })
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingOverlay();
            if (data.success) {
                showEmergencyAlert('Sistem kilidi kaldırıldı', 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showEmergencyAlert('Hata: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            showEmergencyAlert('Bağlantı hatası: ' + error.message, 'error');
        });
    }
}

// Zorla tüm çıkış
function forceLogoutAll() {
    if (confirm('⚠️ ZORLA TÜMÜNÜ ÇIKAR\n\nTüm kullanıcıları (kendiniz hariç) zorla çıkış yaptırmak istediğinizden emin misiniz?\n\nBu işlem anında uygulanacaktır!')) {
        showLoadingOverlay('Tüm kullanıcılar çıkış yaptırılıyor...');
        
        fetch('/src/api/emergency_lockdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'force_logout_all' })
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingOverlay();
            if (data.success) {
                showEmergencyAlert(`${data.data.logged_out_count} kullanıcı çıkış yaptırıldı`, 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showEmergencyAlert('Hata: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            showEmergencyAlert('Bağlantı hatası: ' + error.message, 'error');
        });
    }
}

// Kısmi kilit modal açma
function openPartialLockdownModal() {
    const modal = document.getElementById('partialLockdownModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    }
}

// Kısmi kilit modal kapama
function closePartialLockdownModal() {
    const modal = document.getElementById('partialLockdownModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        // Formu temizle
        document.getElementById('partialLockdownUsers').value = '';
        document.getElementById('partialLockdownReason').value = 'Kısmi acil durum kilidi';
    }
}

// Kısmi kilit uygulama
function executePartialLockdown() {
    const usersInput = document.getElementById('partialLockdownUsers').value.trim();
    const reason = document.getElementById('partialLockdownReason').value.trim();
    
    if (!usersInput) {
        alert('Lütfen askıya alınacak kullanıcı ID\'lerini girin!');
        return;
    }
    
    // ID'leri parse et
    const userIds = usersInput.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
    
    if (userIds.length === 0) {
        alert('Geçerli kullanıcı ID\'si bulunamadı!');
        return;
    }
    
    if (confirm(`${userIds.length} kullanıcıyı askıya almak istediğinizden emin misiniz?\n\nKullanıcılar: ${userIds.join(', ')}\nNeden: ${reason}`)) {
        closePartialLockdownModal();
        showLoadingOverlay('Seçili kullanıcılar askıya alınıyor...');
        
        fetch('/src/api/emergency_lockdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ 
                action: 'partial_lockdown',
                target_users: userIds,
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingOverlay();
            if (data.success) {
                let message = `${data.data.suspended_count} kullanıcı askıya alındı`;
                if (data.data.errors && data.data.errors.length > 0) {
                    message += `\n\nHatalar:\n${data.data.errors.join('\n')}`;
                }
                showEmergencyAlert(message, 'success');
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showEmergencyAlert('Hata: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            showEmergencyAlert('Bağlantı hatası: ' + error.message, 'error');
        });
    }
}

// Acil kullanıcı askıya alma
function emergencySuspendUser() {
    const userId = document.getElementById('emergencyUserId').value.trim();
    const reason = document.getElementById('emergencyReason').value.trim();
    
    if (!userId || !reason) {
        alert('Lütfen kullanıcı ID\'si ve askıya alma nedenini girin!');
        return;
    }
    
    if (!Number.isInteger(parseInt(userId))) {
        alert('Geçerli bir kullanıcı ID\'si girin!');
        return;
    }
    
    if (confirm(`Kullanıcı ${userId}'yi askıya almak istediğinizden emin misiniz?\n\nNeden: ${reason}\n\nBu işlem anında uygulanacaktır!`)) {
        showLoadingOverlay('Kullanıcı askıya alınıyor...');
        
        fetch('/src/api/emergency_lockdown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ 
                action: 'suspend_user',
                user_id: parseInt(userId),
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingOverlay();
            if (data.success) {
                showEmergencyAlert(`Kullanıcı ${userId} askıya alındı`, 'success');
                // Formu temizle
                document.getElementById('emergencyUserId').value = '';
                document.getElementById('emergencyReason').value = '';
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showEmergencyAlert('Hata: ' + data.message, 'error');
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            showEmergencyAlert('Bağlantı hatası: ' + error.message, 'error');
        });
    }
}

// Loading overlay göster
function showLoadingOverlay(message = 'İşleniyor...') {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.style.display = 'flex';
    overlay.innerHTML = `
        <div style="text-align: center; color: white;">
            <div class="loading-spinner"></div>
            <p style="margin-top: 20px; font-size: 1.1rem;">${message}</p>
        </div>
    `;
    document.body.appendChild(overlay);
}

// Loading overlay gizle
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Acil durum uyarısı göster
function showEmergencyAlert(message, type = 'info') {
    const colors = {
        'critical': 'linear-gradient(135deg, #e74c3c, #c0392b)',
        'error': 'linear-gradient(135deg, #e74c3c, #c0392b)', 
        'success': 'linear-gradient(135deg, #27ae60, #229954)',
        'warning': 'linear-gradient(135deg, #f39c12, #e67e22)',
        'info': 'linear-gradient(135deg, #3498db, #2980b9)'
    };
    
    const icons = {
        'critical': 'fas fa-exclamation-triangle',
        'error': 'fas fa-times-circle',
        'success': 'fas fa-check-circle',
        'warning': 'fas fa-exclamation-circle',
        'info': 'fas fa-info-circle'
    };
    
    const alert = document.createElement('div');
    alert.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: ${colors[type] || colors.info};
        color: white;
        padding: 25px 30px;
        border-radius: 12px;
        z-index: 10002;
        max-width: 500px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        font-weight: bold;
        text-align: center;
        animation: emergencyAlertShow 0.3s ease;
    `;
    
    alert.innerHTML = `
        <i class="${icons[type] || icons.info}" style="font-size: 2rem; margin-bottom: 15px;"></i>
        <div style="font-size: 1.1rem; line-height: 1.4; white-space: pre-line;">${message}</div>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.animation = 'emergencyAlertHide 0.3s ease';
            setTimeout(() => {
                alert.parentNode.removeChild(alert);
            }, 300);
        }
    }, type === 'critical' ? 8000 : 5000);
}

// Modal kapatma için dış alan tıklama
window.onclick = function(event) {
    const modal = document.getElementById('partialLockdownModal');
    if (event.target === modal) {
        closePartialLockdownModal();
    }
};

// ESC tuşu ile modal kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePartialLockdownModal();
    }
});

// CSS animasyonları ekle
const emergencyStyle = document.createElement('style');
emergencyStyle.textContent = `
    @keyframes emergencyAlertShow {
        from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
        to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }
    @keyframes emergencyAlertHide {
        from { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        to { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
    }
    
    .modal {
        position: fixed;
        z-index: 10000;
        left: 50%;
        top: 50%;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.7);
        backdrop-filter: blur(5px);
    }
    
    .modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        animation: modalSlideIn 0.3s ease;
    }
    
    .modal-header {
        padding: 20px 25px;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid var(--darker-gold-1);
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        background-color: var(--darker-gold-2);
        border-radius: 0 0 12px 12px;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: all 0.2s ease;
    }
    
    .modal-close:hover {
        background-color: rgba(255,255,255,0.2);
        transform: rotate(90deg);
    }
    
    @keyframes modalSlideIn {
        from { opacity: 0; transform: translate(-50%, -60%) scale(0.9); }
        to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }
`;
document.head.appendChild(emergencyStyle);
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>