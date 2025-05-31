<?php
// src/includes/admin_quick_navigation.php

// get_auth_base_url fonksiyonu artık mevcut olmalı
$adminBaseUrl = get_auth_base_url(); // /public döner

// Kullanıcının yetkilerini kontrol et
$canManageRoles = has_permission($pdo, 'admin.roles.view');
$canManageSuperAdmins = has_permission($pdo, 'admin.super_admin.view');
$canViewAuditLog = has_permission($pdo, 'admin.audit_log.view');
$canManageUsers = has_permission($pdo, 'admin.users.view');
$canAccessAdminPanel = has_permission($pdo, 'admin.panel.access');

// Eğer hiç admin yetkisi yoksa navigasyonu gösterme
if (!$canAccessAdminPanel) {
    return;
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

.admin-navigation-grid .btn.premium i.fas {
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
</style>

<div class="admin-quick-navigation">
    <h2><i class="fas fa-tachometer-alt"></i> Hızlı Yönetim Paneli</h2>
    
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
            </a>
            <?php else: ?>
            <span class="btn disabled">
                <i class="fas fa-user-cog"></i> Kullanıcı Yönetimi
                <span class="permission-badge denied">Yetki Yok</span>
            </span>
            <?php endif; ?>
            
            <?php if ($canManageRoles): ?>
            <a href="/public/admin/manage_roles.php" class="btn">
                <i class="fas fa-user-tag"></i> Rol Yönetimi
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
            <?php if ($canManageSuperAdmins): ?>
            <a href="/public/admin/manage_super_admins.php" class="btn premium">
                <i class="fas fa-crown"></i> Süper Admin Yönetimi
            </a>
            <?php else: ?>
            <span class="btn disabled">
                <i class="fas fa-crown"></i> Süper Admin Yönetimi
                <span class="permission-badge denied">Yetki Yok</span>
            </span>
            <?php endif; ?>
            
            <?php if ($canViewAuditLog): ?>
            <a href="/public/admin/audit_log.php" class="btn premium">
                <i class="fas fa-clipboard-list"></i> Audit Log
            </a>
            <?php else: ?>
            <span class="btn disabled">
                <i class="fas fa-clipboard-list"></i> Audit Log
                <span class="permission-badge denied">Yetki Yok</span>
            </span>
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
        }
        
        $quick_stats['active_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn();
        
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
        
        <?php if (isset($quick_stats['security_events_today'])): ?>
        <div class="admin-stat-quick" style="<?php echo $quick_stats['security_events_today'] > 0 ? 'border-color: var(--red);' : ''; ?>">
            <div class="stat-number" style="<?php echo $quick_stats['security_events_today'] > 0 ? 'color: var(--red);' : ''; ?>">
                <?php echo $quick_stats['security_events_today']; ?>
            </div>
            <div class="stat-label">Güvenlik Olayı (Bugün)</div>
        </div>
        <?php endif; ?>
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
                if ($canManageSuperAdmins) $activePermissions[] = 'Süper Admin';
                if ($canViewAuditLog) $activePermissions[] = 'Audit Log';
                if (is_super_admin($pdo)) $activePermissions[] = '<strong style="color: var(--gold);">SÜPER ADMİN</strong>';
                
                echo !empty($activePermissions) ? implode(', ', $activePermissions) : 'Temel Admin Paneli';
                ?>
            </span>
        </div>
    </div>
</div>