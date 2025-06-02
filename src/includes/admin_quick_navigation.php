<?php
// src/includes/admin_quick_navigation.php - Simplified Version

$adminBaseUrl = get_auth_base_url();

// Basit yetki kontrolleri
$canManageUsers = has_permission($pdo, 'admin.users.view');
$canManageRoles = has_permission($pdo, 'admin.roles.view');
$canViewAuditLog = has_permission($pdo, 'admin.audit_log.view');
$canAccessAdminPanel = has_permission($pdo, 'admin.panel.access');
$isSuperAdmin = is_super_admin($pdo);

// Eğer admin yetkisi yoksa navigasyonu gösterme
if (!$canAccessAdminPanel) {
    return;
}

// Basit istatistikler
$quick_stats = [];
try {
    if ($canManageUsers) {
        $quick_stats['pending_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        $quick_stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();
    }
    
    if ($canViewAuditLog) {
        $quick_stats['security_events_today'] = $pdo->query("
            SELECT COUNT(*) FROM audit_log 
            WHERE (action LIKE '%security%' OR action LIKE '%unauthorized%') 
            AND DATE(created_at) = CURDATE()
        ")->fetchColumn();
    }
    
    $quick_stats['active_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Admin quick stats error: " . $e->getMessage());
}
?>

<style>
.admin-quick-nav {
    margin: 2rem 0;
    background: linear-gradient(135deg, var(--charcoal), var(--darker-gold-2));
    border-radius: 12px;
    border: 1px solid var(--darker-gold-1);
    overflow: hidden;
}

.admin-nav-header {
    background: rgba(189, 145, 42, 0.1);
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--darker-gold-1);
    display: flex;
    align-items: center;
    justify-content: between;
}

.admin-nav-title {
    color: var(--gold);
    font-size: 1.3rem;
    font-weight: 500;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-badge {
    margin-left: auto;
    background: var(--gold);
    color: var(--charcoal);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.super-admin-badge {
    background: linear-gradient(135deg, #ff6b6b, #ffd700);
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 0 5px rgba(255, 215, 0, 0.5); }
    to { box-shadow: 0 0 15px rgba(255, 215, 0, 0.8); }
}

.admin-nav-content {
    padding: 1.5rem;
}

.nav-section {
    margin-bottom: 2rem;
}

.nav-section:last-child {
    margin-bottom: 0;
}

.section-title {
    color: var(--light-gold);
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--grey);
    color: var(--lighter-grey);
    text-decoration: none;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-2);
    transition: all 0.2s ease;
    font-size: 0.9rem;
    font-weight: 500;
}

.nav-item:hover {
    background: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
    transform: translateY(-1px);
}

.nav-item.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.nav-item.premium {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    color: var(--charcoal);
    border-color: var(--gold);
}

.nav-item.premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(189, 145, 42, 0.3);
}

.nav-item.super-admin {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    border-color: #ff6b6b;
}

.nav-item.super-admin:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
}

.nav-icon {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.nav-item.premium .nav-icon,
.nav-item.super-admin .nav-icon {
    color: inherit;
}

.status-badge {
    margin-left: auto;
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-ok {
    background: var(--turquase);
    color: var(--black);
}

.status-no {
    background: var(--red);
    color: var(--white);
}

.status-super {
    background: #ffd700;
    color: var(--black);
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--darker-gold-2);
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: rgba(189, 145, 42, 0.05);
    border-radius: 8px;
    border: 1px solid var(--darker-gold-2);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--gold);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--light-grey);
}

.stat-item.warning .stat-number {
    color: #ff9800;
}

.stat-item.danger .stat-number {
    color: #f44336;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-nav-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        text-align: center;
    }
    
    .user-badge {
        margin-left: 0;
        justify-content: center;
    }
    
    .nav-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-stats {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
}
</style>

<div class="admin-quick-nav">
    <div class="admin-nav-header">
        <h3 class="admin-nav-title">
            <i class="fas fa-tachometer-alt"></i>
            Admin Panel
        </h3>
        <div class="user-badge <?php echo $isSuperAdmin ? 'super-admin-badge' : ''; ?>">
            <?php if ($isSuperAdmin): ?>
                <i class="fas fa-crown"></i>
                Süper Admin
            <?php else: ?>
                <i class="fas fa-user-shield"></i>
                Admin
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-nav-content">
        <!-- Ana Yönetim -->
        <div class="nav-section">
            <div class="section-title">
                <i class="fas fa-cog"></i>
                Ana Yönetim
            </div>
            <div class="nav-grid">
                <a href="/public/admin/index.php" class="nav-item">
                    <i class="fas fa-home nav-icon"></i>
                    Dashboard
                </a>
                
                <a href="/public/admin/users.php" class="nav-item <?php echo $canManageUsers ? 'premium' : 'disabled'; ?>">
                    <i class="fas fa-users nav-icon"></i>
                    Kullanıcılar
                    <span class="status-badge <?php echo $canManageUsers ? 'status-ok' : 'status-no'; ?>">
                        <?php echo $canManageUsers ? 'OK' : 'NO'; ?>
                    </span>
                </a>
                
                <a href="/public/admin/manage_roles.php" class="nav-item <?php echo $canManageRoles ? 'premium' : 'disabled'; ?>">
                    <i class="fas fa-user-tag nav-icon"></i>
                    Roller
                    <span class="status-badge <?php echo $canManageRoles ? 'status-ok' : 'status-no'; ?>">
                        <?php echo $canManageRoles ? 'OK' : 'NO'; ?>
                    </span>
                </a>
                
                <a href="/public/admin/events.php" class="nav-item">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    Etkinlikler
                </a>
            </div>
        </div>

        <!-- İçerik Yönetimi -->
        <div class="nav-section">
            <div class="section-title">
                <i class="fas fa-edit"></i>
                İçerik
            </div>
            <div class="nav-grid">
                <a href="/public/admin/gallery.php" class="nav-item">
                    <i class="fas fa-images nav-icon"></i>
                    Galeri
                </a>
                
                <a href="/public/admin/guides.php" class="nav-item">
                    <i class="fas fa-book-open nav-icon"></i>
                    Rehberler
                </a>
                
                <a href="/public/admin/discussions.php" class="nav-item">
                    <i class="fas fa-comments nav-icon"></i>
                    Tartışmalar
                </a>
            </div>
        </div>

        <!-- Güvenlik -->
        <?php if ($canViewAuditLog || $isSuperAdmin): ?>
        <div class="nav-section">
            <div class="section-title">
                <i class="fas fa-shield-alt"></i>
                Güvenlik
            </div>
            <div class="nav-grid">
                <?php if ($canViewAuditLog): ?>
                <a href="/public/admin/audit_log.php" class="nav-item premium">
                    <i class="fas fa-clipboard-list nav-icon"></i>
                    Audit Log
                    <span class="status-badge status-ok">OK</span>
                </a>
                <?php endif; ?>
                
                <?php if ($isSuperAdmin): ?>
                <a href="/public/admin/manage_super_admins.php" class="nav-item super-admin">
                    <i class="fas fa-crown nav-icon"></i>
                    Süper Adminler
                    <span class="status-badge status-super">SÜPER</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hızlı İstatistikler -->
        <?php if (!empty($quick_stats)): ?>
        <div class="quick-stats">
            <?php if (isset($quick_stats['pending_users'])): ?>
            <div class="stat-item <?php echo $quick_stats['pending_users'] > 0 ? 'warning' : ''; ?>">
                <div class="stat-number"><?php echo $quick_stats['pending_users']; ?></div>
                <div class="stat-label">Onay Bekleyen</div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($quick_stats['total_users'])): ?>
            <div class="stat-item">
                <div class="stat-number"><?php echo $quick_stats['total_users']; ?></div>
                <div class="stat-label">Aktif Üye</div>
            </div>
            <?php endif; ?>
            
            <div class="stat-item">
                <div class="stat-number"><?php echo $quick_stats['active_events']; ?></div>
                <div class="stat-label">Aktif Etkinlik</div>
            </div>
            
            <?php if (isset($quick_stats['security_events_today'])): ?>
            <div class="stat-item <?php echo $quick_stats['security_events_today'] > 0 ? 'danger' : ''; ?>">
                <div class="stat-number"><?php echo $quick_stats['security_events_today']; ?></div>
                <div class="stat-label">Güvenlik Olayı</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Yetki Bilgisi -->
        <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(42, 189, 168, 0.1); border-radius: 8px; border: 1px solid rgba(42, 189, 168, 0.3);">
            <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--turquase); font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i>
                <strong>
                    <?php if ($isSuperAdmin): ?>
                        🔥 Süper Admin - Sınırsız Yetki
                    <?php else: ?>
                        Admin Yetkileriniz Aktif
                    <?php endif; ?>
                </strong>
            </div>
            <?php if (!$isSuperAdmin): ?>
            <div style="font-size: 0.8rem; color: var(--light-grey); margin-top: 0.5rem;">
                Hiyerarşik rol sistemi - sadece alt seviye rolleri yönetebilirsiniz.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basit hover efektleri
    const navItems = document.querySelectorAll('.nav-item:not(.disabled)');
    navItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            if (this.classList.contains('premium')) {
                this.style.boxShadow = '0 6px 20px rgba(189, 145, 42, 0.4)';
            } else if (this.classList.contains('super-admin')) {
                this.style.boxShadow = '0 6px 20px rgba(255, 107, 107, 0.4)';
            } else {
                this.style.boxShadow = '0 4px 15px rgba(189, 145, 42, 0.2)';
            }
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.boxShadow = 'none';
        });
    });
    
    // İstatistik kartları için hover
    const statItems = document.querySelectorAll('.stat-item');
    statItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(189, 145, 42, 0.2)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Bekleyen kullanıcı sayısı için attention efekti
    const pendingUsersNumber = document.querySelector('.stat-item.warning .stat-number');
    if (pendingUsersNumber && parseInt(pendingUsersNumber.textContent) > 0) {
        pendingUsersNumber.style.animation = 'pulse 2s infinite';
    }
});
</script>