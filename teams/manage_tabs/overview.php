<?php
// /teams/manage_tabs/overview.php
// Bu dosya teams/manage.php içinde include edilir

if (!defined('BASE_PATH') || !isset($team_data)) {
    die('Bu dosya doğrudan erişilemez.');
}
?>

<div class="overview-content">
    <!-- Dashboard Cards -->
    <div class="dashboard-grid">
        <!-- Team Statistics Card -->
        <div class="dashboard-card stats-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Takım İstatistikleri</h3>
            </div>
            <div class="card-content">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $team_stats['total_members'] ?></div>
                            <div class="stat-label">Toplam Üye</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $team_stats['weekly_active'] ?></div>
                            <div class="stat-label">Haftalık Aktif</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $team_stats['monthly_active'] ?></div>
                            <div class="stat-label">Aylık Aktif</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $team_stats['pending_applications'] ?></div>
                            <div class="stat-label">Bekleyen Başvuru</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= round(($team_stats['total_members'] / $team_data['max_members']) * 100) ?>%</div>
                            <div class="stat-label">Kapasite Doluluk</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $team_data['max_members'] - $team_stats['total_members'] ?></div>
                            <div class="stat-label">Boş Slot</div>
                        </div>
                    </div>
                </div>
                
                <!-- Capacity Progress Bar -->
                <div class="capacity-progress">
                    <div class="progress-label">
                        <span>Takım Kapasitesi</span>
                        <span><?= $team_stats['total_members'] ?>/<?= $team_data['max_members'] ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= ($team_stats['total_members'] / $team_data['max_members']) * 100 ?>%; background-color: <?= htmlspecialchars($team_data['color']) ?>;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Distribution Card -->
        <div class="dashboard-card roles-card">
            <div class="card-header">
                <h3><i class="fas fa-user-tag"></i> Rol Dağılımı</h3>
            </div>
            <div class="card-content">
                <?php if (!empty($team_stats['role_distribution'])): ?>
                    <div class="role-distribution">
                        <?php foreach ($team_stats['role_distribution'] as $role): ?>
                            <div class="role-item">
                                <div class="role-info">
                                    <div class="role-color" style="background-color: <?= htmlspecialchars($role['color']) ?>;"></div>
                                    <span class="role-name"><?= htmlspecialchars($role['display_name']) ?></span>
                                </div>
                                <div class="role-stats">
                                    <span class="role-count"><?= $role['count'] ?></span>
                                    <span class="role-percentage"><?= round(($role['count'] / $team_stats['total_members']) * 100) ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-tag"></i>
                        <p>Henüz rol dağılımı bulunmuyor.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="dashboard-card actions-card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Hızlı İşlemler</h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <a href="?id=<?= $team_id ?>&tab=members" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-info">
                            <div class="action-title">Üye Yönetimi</div>
                            <div class="action-desc">Üyeleri görüntüle ve yönet</div>
                        </div>
                    </a>
                    
                    <?php if ($team_stats['pending_applications'] > 0): ?>
                        <a href="?id=<?= $team_id ?>&tab=applications" class="action-btn highlight">
                            <div class="action-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="action-info">
                                <div class="action-title">Bekleyen Başvurular</div>
                                <div class="action-desc"><?= $team_stats['pending_applications'] ?> başvuru bekliyor</div>
                            </div>
                            <div class="action-badge"><?= $team_stats['pending_applications'] ?></div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($is_team_owner): ?>
                        <a href="?id=<?= $team_id ?>&tab=roles" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <div class="action-info">
                                <div class="action-title">Rol Yönetimi</div>
                                <div class="action-desc">Rolleri düzenle ve yetkileri ayarla</div>
                            </div>
                        </a>
                        
                        <a href="?id=<?= $team_id ?>&tab=settings" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="action-info">
                                <div class="action-title">Takım Ayarları</div>
                                <div class="action-desc">Genel ayarları değiştir</div>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <a href="/teams/detail.php?id=<?= $team_id ?>" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="action-info">
                            <div class="action-title">Takımı Görüntüle</div>
                            <div class="action-desc">Herkese açık görünümü gör</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activities Card -->
        <div class="dashboard-card activities-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Son Aktiviteler</h3>
                <small>Son 30 gün</small>
            </div>
            <div class="card-content">
                <?php if (!empty($recent_activities)): ?>
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php if ($activity['type'] === 'member_joined'): ?>
                                        <i class="fas fa-user-plus text-success"></i>
                                    <?php elseif ($activity['type'] === 'application_submitted'): ?>
                                        <i class="fas fa-paper-plane text-info"></i>
                                    <?php else: ?>
                                        <i class="fas fa-info-circle text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <?php if ($activity['type'] === 'member_joined'): ?>
                                            <strong><?= htmlspecialchars($activity['username']) ?></strong> 
                                            takıma katıldı
                                            <?php if ($activity['role_name']): ?>
                                                (<?= htmlspecialchars($activity['role_name']) ?>)
                                            <?php endif; ?>
                                        <?php elseif ($activity['type'] === 'application_submitted'): ?>
                                            <strong><?= htmlspecialchars($activity['username']) ?></strong> 
                                            takıma başvurdu
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('d.m.Y H:i', strtotime($activity['date'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>Son 30 günde aktivite bulunmuyor.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Team Health Card -->
        <div class="dashboard-card health-card">
            <div class="card-header">
                <h3><i class="fas fa-heartbeat"></i> Takım Sağlığı</h3>
            </div>
            <div class="card-content">
                <div class="health-metrics">
                    <!-- Activity Rate -->
                    <div class="health-metric">
                        <div class="metric-label">Haftalık Aktivite Oranı</div>
                        <div class="metric-bar">
                            <?php $weekly_rate = $team_stats['total_members'] > 0 ? ($team_stats['weekly_active'] / $team_stats['total_members']) * 100 : 0; ?>
                            <div class="metric-fill" style="width: <?= $weekly_rate ?>%; background-color: <?= $weekly_rate >= 70 ? '#28a745' : ($weekly_rate >= 40 ? '#ffc107' : '#dc3545') ?>;"></div>
                        </div>
                        <div class="metric-value"><?= round($weekly_rate) ?>%</div>
                    </div>
                    
                    <!-- Recruitment Status -->
                    <div class="health-metric">
                        <div class="metric-label">Üye Alım Durumu</div>
                        <div class="metric-status">
                            <?php if ($team_data['is_recruitment_open']): ?>
                                <span class="status-badge status-open">
                                    <i class="fas fa-unlock"></i> Açık
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-closed">
                                    <i class="fas fa-lock"></i> Kapalı
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Capacity Status -->
                    <div class="health-metric">
                        <div class="metric-label">Kapasite Durumu</div>
                        <div class="metric-status">
                            <?php 
                            $capacity_rate = ($team_stats['total_members'] / $team_data['max_members']) * 100;
                            if ($capacity_rate >= 90): 
                            ?>
                                <span class="status-badge status-full">
                                    <i class="fas fa-exclamation-triangle"></i> Neredeyse Dolu
                                </span>
                            <?php elseif ($capacity_rate >= 70): ?>
                                <span class="status-badge status-high">
                                    <i class="fas fa-chart-line"></i> Yüksek
                                </span>
                            <?php elseif ($capacity_rate >= 40): ?>
                                <span class="status-badge status-medium">
                                    <i class="fas fa-chart-bar"></i> Orta
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-low">
                                    <i class="fas fa-chart-bar"></i> Düşük
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pending Applications -->
                    <?php if ($team_stats['pending_applications'] > 0): ?>
                        <div class="health-metric">
                            <div class="metric-label">Bekleyen İşlemler</div>
                            <div class="metric-status">
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> <?= $team_stats['pending_applications'] ?> Başvuru
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Health Score -->
                <?php
                $health_score = 0;
                $health_score += min($weekly_rate * 0.4, 40); // Max 40 points for activity
                $health_score += ($team_data['is_recruitment_open'] && $capacity_rate < 90) ? 20 : 10; // 20 points for open recruitment
                $health_score += ($capacity_rate >= 40 && $capacity_rate <= 85) ? 20 : 10; // 20 points for optimal capacity
                $health_score += ($team_stats['pending_applications'] === 0) ? 20 : 10; // 20 points for no pending apps
                
                $health_color = $health_score >= 80 ? '#28a745' : ($health_score >= 60 ? '#ffc107' : '#dc3545');
                ?>
                <div class="health-score">
                    <div class="score-label">Genel Sağlık Skoru</div>
                    <div class="score-circle" style="border-color: <?= $health_color ?>;">
                        <span style="color: <?= $health_color ?>;"><?= round($health_score) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>