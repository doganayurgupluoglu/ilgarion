<?php
// /teams/manage_tabs/overview.php
// Bu dosya takım yönetimi sayfasının overview tab'ında include edilir

// Bu dosya çalıştırıldığında gerekli değişkenler manage.php'den gelir:
// $team_data, $team_id, $team_stats, $recent_activities, $current_user_id, $pdo

// Ek istatistikleri hesapla
$growth_stats = [];

// Son 3 aylık üye katılım trendi
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(joined_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM team_members 
    WHERE team_id = ? AND status = 'active'
    AND joined_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY DATE_FORMAT(joined_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$team_id]);
$monthly_joins = $stmt->fetchAll();

// Bu ayın katılım sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM team_members 
    WHERE team_id = ? AND status = 'active'
    AND MONTH(joined_at) = MONTH(NOW()) 
    AND YEAR(joined_at) = YEAR(NOW())
");
$stmt->execute([$team_id]);
$this_month_joins = $stmt->fetchColumn();

// Geçen ayın katılım sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM team_members 
    WHERE team_id = ? AND status = 'active'
    AND MONTH(joined_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
    AND YEAR(joined_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
");
$stmt->execute([$team_id]);
$last_month_joins = $stmt->fetchColumn();

// Büyüme oranı hesapla
$growth_rate = 0;
if ($last_month_joins > 0) {
    $growth_rate = (($this_month_joins - $last_month_joins) / $last_month_joins) * 100;
}

// Rol dağılımı
$stmt = $pdo->prepare("
    SELECT 
        tr.display_name, 
        tr.color, 
        tr.priority,
        COUNT(*) as count
    FROM team_members tm
    JOIN team_roles tr ON tm.team_role_id = tr.id
    WHERE tm.team_id = ? AND tm.status = 'active'
    GROUP BY tr.id, tr.display_name, tr.color, tr.priority
    ORDER BY tr.priority ASC
");
$stmt->execute([$team_id]);
$role_stats = $stmt->fetchAll();

// Son başvurular (overview için kısıtlı)
$stmt = $pdo->prepare("
    SELECT ta.*, u.username, u.ingame_name, u.avatar_path
    FROM team_applications ta
    JOIN users u ON ta.user_id = u.id
    WHERE ta.team_id = ? AND ta.status = 'pending'
    ORDER BY ta.applied_at DESC
    LIMIT 5
");
$stmt->execute([$team_id]);
$recent_applications = $stmt->fetchAll();

// Takım yaşı hesapla
$team_age_days = floor((time() - strtotime($team_data['created_at'])) / (60 * 60 * 24));
$team_age_text = '';
if ($team_age_days < 30) {
    $team_age_text = $team_age_days . ' gün';
} elseif ($team_age_days < 365) {
    $team_age_text = floor($team_age_days / 30) . ' ay';
} else {
    $years = floor($team_age_days / 365);
    $months = floor(($team_age_days % 365) / 30);
    $team_age_text = $years . ' yıl' . ($months > 0 ? ' ' . $months . ' ay' : '');
}

// Ortalama üye aktifliği
$stmt = $pdo->prepare("
    SELECT AVG(DATEDIFF(NOW(), COALESCE(last_activity, joined_at))) as avg_days_since_activity
    FROM team_members 
    WHERE team_id = ? AND status = 'active'
");
$stmt->execute([$team_id]);
$avg_activity_days = $stmt->fetchColumn();

// Aktivite seviyesi
$activity_level = 'low';
$activity_text = 'Düşük';
$activity_color = 'danger';

if ($avg_activity_days !== null) {
    if ($avg_activity_days <= 7) {
        $activity_level = 'high';
        $activity_text = 'Yüksek';
        $activity_color = 'success';
    } elseif ($avg_activity_days <= 30) {
        $activity_level = 'medium';
        $activity_text = 'Orta';
        $activity_color = 'warning';
    }
}
?>

<!-- Overview Tab Content -->
<div class="overview-tab">
    <!-- Ana İstatistikler -->
    <div class="overview-stats">
        <div class="overview-stat-card">
            <div class="stat-icon">
                <i class="fas fa-users" style="color: var(--gold);"></i>
            </div>
            <div class="stat-content">
                <div class="overview-stat-value"><?= $team_stats['total_members'] ?></div>
                <div class="overview-stat-label">Toplam Üye</div>
                <div class="stat-sublabel"><?= $team_data['max_members'] ?> maksimum</div>
            </div>
        </div>
        
        <div class="overview-stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-clock" style="color: var(--turquase);"></i>
            </div>
            <div class="stat-content">
                <div class="overview-stat-value"><?= $team_stats['weekly_active'] ?></div>
                <div class="overview-stat-label">Haftalık Aktif</div>
                <div class="stat-sublabel">Son 7 gün</div>
            </div>
        </div>
        
        <div class="overview-stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check" style="color: var(--success);"></i>
            </div>
            <div class="stat-content">
                <div class="overview-stat-value"><?= $team_stats['monthly_active'] ?></div>
                <div class="overview-stat-label">Aylık Aktif</div>
                <div class="stat-sublabel">Son 30 gün</div>
            </div>
        </div>
        
        <div class="overview-stat-card">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list" style="color: var(--warning);"></i>
            </div>
            <div class="stat-content">
                <div class="overview-stat-value"><?= $team_stats['pending_applications'] ?></div>
                <div class="overview-stat-label">Bekleyen Başvuru</div>
                <?php if ($team_stats['pending_applications'] > 0): ?>
                    <div class="stat-sublabel">
                        <a href="?id=<?= $team_id ?>&tab=applications" style="color: var(--gold); text-decoration: none;">
                            İncele →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="stat-sublabel">Yeni başvuru yok</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="overview-content">
        <!-- Sol Kolon -->
        <div class="overview-left">
            <!-- Takım Bilgileri -->
            <div class="manage-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Takım Bilgileri</h3>
                </div>
                <div class="card-body">
                    <div class="team-info-grid">
                        <div class="info-item">
                            <span class="info-label">Takım Yaşı:</span>
                            <span class="info-value"><?= $team_age_text ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Aktivite Seviyesi:</span>
                            <span class="info-value">
                                <span class="badge badge-<?= $activity_color ?>"><?= $activity_text ?></span>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Üye Alımı:</span>
                            <span class="info-value">
                                <?php if ($team_data['is_recruitment_open']): ?>
                                    <span class="badge badge-success">Açık</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Kapalı</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Bu Ay Katılan:</span>
                            <span class="info-value">
                                <?= $this_month_joins ?> üye
                                <?php if ($growth_rate != 0): ?>
                                    <small class="growth-indicator <?= $growth_rate > 0 ? 'positive' : 'negative' ?>">
                                        <?= $growth_rate > 0 ? '+' : '' ?><?= number_format($growth_rate, 1) ?>%
                                    </small>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Takım Lideri:</span>
                            <span class="info-value"><?= htmlspecialchars($team_data['creator_username']) ?></span>
                        </div>
                        
                        <?php if ($team_data['tag']): ?>
                            <div class="info-item">
                                <span class="info-label">Kategori:</span>
                                <span class="info-value">
                                    <span class="team-tag" style="background: <?= htmlspecialchars($team_data['color']) ?>20; color: <?= htmlspecialchars($team_data['color']) ?>;">
                                        <?= htmlspecialchars($team_data['tag']) ?>
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Rol Dağılımı -->
            <div class="manage-card">
                <div class="card-header">
                    <h3><i class="fas fa-users-cog"></i> Rol Dağılımı</h3>
                </div>
                <div class="card-body">
                    <?php if (count($role_stats) > 0): ?>
                        <div class="role-distribution">
                            <?php foreach ($role_stats as $role): ?>
                                <div class="role-item">
                                    <div class="role-info">
                                        <div class="role-color" style="background: <?= htmlspecialchars($role['color']) ?>;"></div>
                                        <span class="role-name"><?= htmlspecialchars($role['display_name']) ?></span>
                                    </div>
                                    <div class="role-count">
                                        <span class="count"><?= $role['count'] ?></span>
                                        <span class="percentage">
                                            (<?= $team_stats['total_members'] > 0 ? round(($role['count'] / $team_stats['total_members']) * 100, 1) : 0 ?>%)
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-users-slash"></i>
                            <p>Henüz üye bulunmuyor</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon -->
        <div class="overview-right">
            <!-- Son Aktiviteler -->
            <div class="manage-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Son Aktiviteler</h3>
                </div>
                <div class="card-body">
                    <?php if (count($recent_activities) > 0): ?>
                        <div class="activity-timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php if ($activity['type'] === 'member_joined'): ?>
                                            <i class="fas fa-user-plus" style="color: var(--success);"></i>
                                        <?php elseif ($activity['type'] === 'application_received'): ?>
                                            <i class="fas fa-file-alt" style="color: var(--warning);"></i>
                                        <?php else: ?>
                                            <i class="fas fa-circle" style="color: var(--light-grey);"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <?php if ($activity['type'] === 'member_joined'): ?>
                                                <strong><?= htmlspecialchars($activity['username']) ?></strong> takıma katıldı
                                                <?php if ($activity['role_name']): ?>
                                                    <span class="role-badge"><?= htmlspecialchars($activity['role_name']) ?></span>
                                                <?php endif; ?>
                                            <?php elseif ($activity['type'] === 'application_received'): ?>
                                                <strong><?= htmlspecialchars($activity['username']) ?></strong> başvuru yaptı
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-time">
                                            <?= time_ago($activity['date']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-clock"></i>
                            <p>Son 30 günde aktivite bulunmuyor</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Son Başvurular -->
            <?php if (count($recent_applications) > 0): ?>
                <div class="manage-card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope"></i> Son Başvurular</h3>
                        <a href="?id=<?= $team_id ?>&tab=applications" class="card-link">
                            Tümünü Gör <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="applications-preview">
                            <?php foreach ($recent_applications as $app): ?>
                                <div class="application-preview-item">
                                    <div class="applicant-info">
                                        <div class="applicant-avatar">
                                            <?php if ($app['avatar_path']): ?>
                                                <img src="/uploads/avatars/<?= htmlspecialchars($app['avatar_path']) ?>" 
                                                     alt="<?= htmlspecialchars($app['username']) ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <?= strtoupper(substr($app['username'], 0, 2)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="applicant-details">
                                            <div class="applicant-name"><?= htmlspecialchars($app['username']) ?></div>
                                            <?php if ($app['ingame_name']): ?>
                                                <div class="applicant-ingame"><?= htmlspecialchars($app['ingame_name']) ?></div>
                                            <?php endif; ?>
                                            <div class="apply-time"><?= time_ago($app['applied_at']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="preview-actions">
                                        <a href="?id=<?= $team_id ?>&tab=applications" class="btn btn-sm btn-outline-primary">
                                            İncele
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hızlı Eylemler -->
            <div class="manage-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Hızlı Eylemler</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="?id=<?= $team_id ?>&tab=members" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <span>Üyeleri Yönet</span>
                        </a>
                        
                        <?php if ($team_stats['pending_applications'] > 0): ?>
                            <a href="?id=<?= $team_id ?>&tab=applications" class="quick-action-btn urgent">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Başvuruları İncele</span>
                                <div class="action-badge"><?= $team_stats['pending_applications'] ?></div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_team_owner): ?>
                            <a href="?id=<?= $team_id ?>&tab=roles" class="quick-action-btn">
                                <i class="fas fa-user-tag"></i>
                                <span>Rolleri Düzenle</span>
                            </a>
                            
                            <a href="?id=<?= $team_id ?>&tab=settings" class="quick-action-btn">
                                <i class="fas fa-cog"></i>
                                <span>Takım Ayarları</span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="/teams/detail.php?id=<?= $team_id ?>" class="quick-action-btn">
                            <i class="fas fa-external-link-alt"></i>
                            <span>Takımı Görüntüle</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Zaman gösterimi için yardımcı fonksiyon
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 2592000) return floor($time/86400) . ' gün önce';
    return date('d.m.Y', strtotime($datetime));
}
?>