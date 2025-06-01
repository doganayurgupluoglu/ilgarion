<?php
// src/includes/admin_quick_navigation.php

// get_auth_base_url fonksiyonu artık mevcut olmalı
$adminBaseUrl = get_auth_base_url(); // /public döner

// Kullanıcının yetkilerini ve hiyerarşi durumunu kontrol et
$canManageRoles = has_permission($pdo, 'admin.roles.view');
$canCreateRoles = has_permission($pdo, 'admin.roles.create');
$canEditRoles = has_permission($pdo, 'admin.roles.edit');
$canDeleteRoles = has_permission($pdo, 'admin.roles.delete');

// Super Admin yetkileri - sadece super adminler görebilir/yönetebilir
$canViewSuperAdmins = is_super_admin($pdo) && has_permission($pdo, 'admin.super_admin.view');
$canManageSuperAdmins = is_super_admin($pdo) && has_permission($pdo, 'admin.super_admin.manage');

// Audit Log yetkileri
$canViewAuditLog = has_permission($pdo, 'admin.audit_log.view');
$canExportAuditLog = has_permission($pdo, 'admin.audit_log.export');

// Diğer admin yetkileri
$canManageUsers = has_permission($pdo, 'admin.users.view');
$canAccessAdminPanel = has_permission($pdo, 'admin.panel.access');
$canManageSystemSecurity = has_permission($pdo, 'admin.system.security');

// Eğer hiç admin yetkisi yoksa navigasyonu gösterme
if (!$canAccessAdminPanel) {
    return;
}

// Kullanıcının rol hiyerarşisindeki konumunu belirle
$current_user_id = $_SESSION['user_id'];
$user_roles = get_user_roles($pdo, $current_user_id);
$highest_priority = 999; // En düşük öncelik
$user_role_names = [];

foreach ($user_roles as $role) {
    $user_role_names[] = $role['name'];
    if ($role['priority'] < $highest_priority) {
        $highest_priority = $role['priority'];
    }
}

// Kullanıcının yönetebileceği rolleri belirle (sadece kendi hiyerarşisinden düşük olanları)
$manageable_roles = [];
if ($canManageUsers || $canEditRoles) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, priority, description FROM roles WHERE priority > ? ORDER BY priority ASC");
        $stmt->execute([$highest_priority]);
        $manageable_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Manageable roles query error: " . $e->getMessage());
    }
}

?>
<style>
    .admin-quick-navigation {
    margin-bottom: 20px;
    margin-top: 20px;
    padding: 25px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
}

.admin-quick-navigation h2 {
    font-size: 1.6rem;
    color: var(--gold);
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-1);
    font-family: var(--font);
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-hierarchy-info {
    background: linear-gradient(135deg, var(--transparent-gold), rgba(61, 166, 162, 0.1));
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--gold);
}

.hierarchy-status {
    color: var(--light-gold);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}

.hierarchy-status i {
    color: var(--gold);
}

.manageable-roles-info {
    color: var(--lighter-grey);
    font-size: 0.8rem;
    margin-top: 8px;
}

.admin-navigation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

.admin-navigation-grid .btn {
    display: flex; 
    align-items: center;
    justify-content: flex-start; 
    text-align: left;
    padding: 12px 18px;
    font-size: 0.95rem;
    font-weight: 500;
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    border-radius: 6px;
    text-decoration: none;
    position: relative;
}

.admin-navigation-grid .btn:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
    transform: translateY(-2px);
}

.admin-navigation-grid .btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.admin-navigation-grid .btn.premium {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--darker-gold-2);
    border-color: var(--gold);
}

.admin-navigation-grid .btn.premium:hover {
    background: linear-gradient(135deg, var(--light-gold), var(--gold));
    transform: translateY(-3px);
    box-shadow: 0 5px 15px var(--transparent-gold);
}

.admin-navigation-grid .btn.super-admin-only {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    border-color: #ff6b6b;
}

.admin-navigation-grid .btn.super-admin-only:hover {
    background: linear-gradient(135deg, #ee5a24, #ff6b6b);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
}

.admin-navigation-grid .btn i.fas {
    margin-right: 12px;
    font-size: 1.05em;
    width: 20px; 
    text-align: center;
    color: var(--light-gold);
}

.admin-navigation-grid .btn:hover i.fas {
    color: var(--gold);
}

.admin-navigation-grid .btn.premium i.fas,
.admin-navigation-grid .btn.super-admin-only i.fas {
    color: var(--darker-gold-2);
}

.permission-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
    font-weight: bold;
}

.permission-badge.granted {
    background-color: var(--turquase);
    color: var(--black);
}

.permission-badge.denied {
    background-color: var(--red);
    color: var(--white);
}

.permission-badge.super-admin {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
}

.navigation-section {
    margin-bottom: 25px;
}

.navigation-section-title {
    font-size: 1.1rem;
    color: var(--light-gold);
    margin-bottom: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-stats-quick {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--darker-gold-1);
}

.admin-stat-quick {
    text-align: center;
    padding: 15px;
    background-color: var(--charcoal);
    border-radius: 6px;
    border: 1px solid var(--darker-gold-1);
}

.admin-stat-quick .stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 5px;
}

.admin-stat-quick .stat-label {
    font-size: 0.8rem;
    color: var(--lighter-grey);
}

.hierarchy-restriction-info {
    background-color: var(--transparent-turquase);
    padding: 10px 15px;
    border-radius: 5px;
    margin-top: 15px;
    border: 1px solid var(--turquase);
}

.hierarchy-restriction-info h4 {
    color: var(--turquase);
    margin: 0 0 8px 0;
    font-size: 0.9rem;
}

.hierarchy-restriction-info ul {
    margin: 0;
    padding-left: 20px;
    color: var(--lighter-grey);
    font-size: 0.8rem;
}

.super-admin-crown {
    animation: crown-glow 2s ease-in-out infinite alternate;
}

@keyframes crown-glow {
    from { text-shadow: 0 0 5px #ffd700; }
    to { text-shadow: 0 0 15px #ffd700, 0 0 25px #ffd700; }
}
</style>

<div class="admin-quick-navigation">
    <h2>
        <i class="fas fa-tachometer-alt"></i> 
        Hızlı Yönetim Paneli
        <?php if (is_super_admin($pdo)): ?>
            <i class="fas fa-crown super-admin-crown" style="color: #ffd700; margin-left: auto;" title="Süper Admin"></i>
        <?php endif; ?>
    </h2>
    
    <!-- Kullanıcı Hiyerarşi Bilgisi -->
    <div class="user-hierarchy-info">
        <div class="hierarchy-status">
            <i class="fas fa-layer-group"></i>
            <strong>Yetki Durumunuz:</strong>
            <?php if (is_super_admin($pdo)): ?>
                <span style="color: #ffd700; font-weight: bold;">SÜPER ADMİN</span> - Sınırsız yetki
            <?php else: ?>
                <span style="color: var(--turquase);"><?php echo implode(', ', array_map('ucfirst', $user_role_names)); ?></span>
                (Hiyerarşi seviyesi: <?php echo $highest_priority; ?>)
            <?php endif; ?>
        </div>
        
        <?php if (!is_super_admin($pdo) && !empty($manageable_roles)): ?>
        <div class="manageable-roles-info">
            <strong>Yönetebileceğiniz roller:</strong> 
            <?php echo implode(', ', array_map(function($role) { return ucfirst($role['name']); }, $manageable_roles)); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Ana Yönetim Araçları -->
    <div class="navigation-section">
        <div class="navigation-section-title">
            <i class="fas fa-tools"></i> Ana Yönetim Araçları
        </div>
        <div class="admin-navigation-grid">
            <a href="/public/admin/index.php" class="btn">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <?php if ($canManageUsers): ?>
            <a href="/public/admin/users.php" class="btn">
                <i class="fas fa-user-cog"></i> Kullanıcı Yönetimi
                <?php if (!empty($manageable_roles)): ?>
                    <small style="font-size: 0.7rem; margin-left: 5px;">(<?php echo count($manageable_roles); ?> rol)</small>
                <?php endif; ?>
            </a>
            <?php else: ?>
            <span class="btn disabled">
                <i class="fas fa-user-cog"></i> Kullanıcı Yönetimi
                <span class="permission-badge denied">Yetki Yok</span>
            </span>
            <?php endif; ?>
            
            <?php if ($canManageRoles): ?>
            <a href="/public/admin/manage_roles.php" class="btn premium">
                <i class="fas fa-user-tag"></i> Rol Yönetimi
                <?php if ($canCreateRoles && $canEditRoles && $canDeleteRoles): ?>
                    <span class="permission-badge granted">Tam Yetki</span>
                <?php elseif ($canEditRoles): ?>
                    <span class="permission-badge granted">Düzenleme</span>
                <?php else: ?>
                    <span class="permission-badge granted">Görüntüleme</span>
                <?php endif; ?>
            </a>
            <?php else: ?>
            <span class="btn disabled">
                <i class="fas fa-user-tag"></i> Rol Yönetimi
                <span class="permission-badge denied">Yetki Yok</span>
            </span>
            <?php endif; ?>
            
            <a href="/public/admin/events.php" class="btn">
                <i class="fas fa-calendar-check"></i> Etkinlik Yönetimi
            </a>
            
            <a href="/public/admin/gallery.php" class="btn">
                <i class="fas fa-photo-video"></i> Galeri Yönetimi
            </a>
            
            <a href="/public/admin/discussions.php" class="btn">
                <i class="fas fa-comments"></i> Tartışma Yönetimi
            </a>
        </div>
    </div>
    
    <!-- İçerik Yönetimi -->
    <div class="navigation-section">
        <div class="navigation-section-title">
            <i class="fas fa-edit"></i> İçerik Yönetimi
        </div>
        <div class="admin-navigation-grid">
            <a href="/public/admin/guides.php" class="btn">
                <i class="fas fa-book-open"></i> Rehber Yönetimi
            </a>
            
            <a href="/public/admin/manage_loadout_sets.php" class="btn">
                <i class="fas fa-user-shield"></i> Teçhizat Setleri
            </a>
        </div>
    </div>
    
    <!-- Güvenlik ve Sistem -->
    <div class="navigation-section">
        <div class="navigation-section-title">
            <i class="fas fa-shield-alt"></i> Güvenlik ve Sistem
        </div>
        <div class="admin-navigation-grid">
            <?php if ($canViewSuperAdmins || $canManageSuperAdmins): ?>
            <a href="/public/admin/manage_super_admins.php" class="btn super-admin-only">
                <i class="fas fa-crown"></i> Süper Admin Yönetimi
                <span class="permission-badge super-admin">SÜPER ADMİN</span>
            </a>
            <?php else: ?>
            <span class="btn disabled" style="display: none;">
                <i class="fas fa-crown"></i> Süper Admin Yönetimi
                <span class="permission-badge super-admin">Sadece Süper Admin</span>
            </span>
            <?php endif; ?>
            
            <?php if ($canViewAuditLog): ?>
            <a href="/public/admin/audit_log.php" class="btn premium">
                <i class="fas fa-clipboard-list"></i> Audit Log
                <?php if ($canExportAuditLog): ?>
                    <span class="permission-badge granted">Export</span>
                <?php else: ?>
                    <span class="permission-badge granted">Görüntüleme</span>
                <?php endif; ?>
            </a>
            <?php else: ?>
            <span class="btn disabled">
                <i class="fas fa-clipboard-list"></i> Audit Log
                <span class="permission-badge denied">Yetki Yok</span>
            </span>
            <?php endif; ?>
            
            <?php if ($canManageSystemSecurity): ?>
            <a href="/public/admin/system_security.php" class="btn premium">
                <i class="fas fa-shield-alt"></i> Sistem Güvenliği
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Hızlı İstatistikler -->
    <?php
    // Sadece gerekli istatistikleri çek
    $quick_stats = [];
    try {
        if ($canManageUsers) {
            $quick_stats['pending_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
            $quick_stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();
        }
        
        if ($canViewAuditLog) {
            $quick_stats['security_events_today'] = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE (action LIKE '%security%' OR action LIKE '%unauthorized%' OR action LIKE '%violation%') AND DATE(created_at) = CURDATE()")->fetchColumn();
            $quick_stats['total_audit_entries'] = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
        }
        
        $quick_stats['active_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn();
        
        if ($canManageRoles) {
            $quick_stats['total_roles'] = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
            $quick_stats['manageable_roles_count'] = count($manageable_roles);
        }
        
    } catch (PDOException $e) {
        error_log("Admin quick stats hatası: " . $e->getMessage());
    }
    ?>
    
    <?php if (!empty($quick_stats)): ?>
    <div class="admin-stats-quick">
        <?php if (isset($quick_stats['pending_users'])): ?>
        <div class="admin-stat-quick">
            <div class="stat-number"><?php echo $quick_stats['pending_users']; ?></div>
            <div class="stat-label">Onay Bekleyen</div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($quick_stats['total_users'])): ?>
        <div class="admin-stat-quick">
            <div class="stat-number"><?php echo $quick_stats['total_users']; ?></div>
            <div class="stat-label">Aktif Üye</div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($quick_stats['active_events'])): ?>
        <div class="admin-stat-quick">
            <div class="stat-number"><?php echo $quick_stats['active_events']; ?></div>
            <div class="stat-label">Aktif Etkinlik</div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($quick_stats['total_roles']) && $canManageRoles): ?>
        <div class="admin-stat-quick">
            <div class="stat-number"><?php echo $quick_stats['manageable_roles_count']; ?>/<?php echo $quick_stats['total_roles']; ?></div>
            <div class="stat-label">Yönetilebilir Rol</div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($quick_stats['security_events_today'])): ?>
        <div class="admin-stat-quick" style="<?php echo $quick_stats['security_events_today'] > 0 ? 'border-color: var(--red);' : ''; ?>">
            <div class="stat-number" style="<?php echo $quick_stats['security_events_today'] > 0 ? 'color: var(--red);' : ''; ?>">
                <?php echo $quick_stats['security_events_today']; ?>
            </div>
            <div class="stat-label">Güvenlik Olayı (Bugün)</div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($quick_stats['total_audit_entries'])): ?>
        <div class="admin-stat-quick">
            <div class="stat-number"><?php echo number_format($quick_stats['total_audit_entries']); ?></div>
            <div class="stat-label">Toplam Audit Kaydı</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Hiyerarşi Kısıtlama Bilgisi -->
    <?php if (!is_super_admin($pdo) && ($canManageUsers || $canManageRoles)): ?>
    <div class="hierarchy-restriction-info">
        <h4><i class="fas fa-info-circle"></i> Hiyerarşi Kısıtlamaları:</h4>
        <ul>
            <li>Sadece kendi rolünüzden <strong>düşük öncelikli</strong> rolleri yönetebilirsiniz</li>
            <li>Süper Admin yetkilerini sadece <strong>mevcut süper adminler</strong> yönetebilir</li>
            <li>Kendi admin rolünüzü <strong>kaldıramazsınız</strong></li>
            <?php if (!empty($manageable_roles)): ?>
            <li>Şu anda <strong><?php echo count($manageable_roles); ?> rol</strong> yönetme yetkiniz var</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Yetki Bilgisi -->
    <div style="margin-top: 20px; padding: 15px; background-color: var(--transparent-gold); border-radius: 6px; border: 1px solid var(--gold);">
        <div style="display: flex; align-items: center; gap: 10px; color: var(--light-gold); font-size: 0.9rem;">
            <i class="fas fa-info-circle"></i>
            <span>
                <strong>Aktif Yetkileriniz:</strong>
                <?php 
                $activePermissions = [];
                if ($canManageUsers) $activePermissions[] = 'Kullanıcı Yönetimi';
                if ($canManageRoles) $activePermissions[] = 'Rol Yönetimi';
                if ($canViewSuperAdmins || $canManageSuperAdmins) $activePermissions[] = 'Süper Admin Yönetimi';
                if ($canViewAuditLog) $activePermissions[] = 'Audit Log';
                if ($canManageSystemSecurity) $activePermissions[] = 'Sistem Güvenliği';
                if (is_super_admin($pdo)) $activePermissions[] = '<strong style="color: #ffd700;">🔥 SÜPER ADMİN - SINIRSIZ YETKİ</strong>';
                
                echo !empty($activePermissions) ? implode(', ', $activePermissions) : 'Temel Admin Paneli';
                ?>
            </span>
        </div>
        
        <?php if (!is_super_admin($pdo) && $highest_priority < 999): ?>
        <div style="margin-top: 8px; font-size: 0.8rem; color: var(--lighter-grey);">
            <strong>Hiyerarşi Seviyeniz:</strong> <?php echo $highest_priority; ?> 
            (Daha düşük sayı = Daha yüksek yetki)
        </div>
        <?php endif; ?>
    </div>
</div>