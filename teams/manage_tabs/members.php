<?php
// /teams/manage_tabs/members.php
// Bu dosya teams/manage.php içinde include edilir

if (!defined('BASE_PATH') || !isset($team_data)) {
    die('Bu dosya doğrudan erişilemez.');
}
?>

<div class="members-content">
    <!-- Members Header -->
    <div class="members-header">
        <div class="header-info">
            <h2>Takım Üyeleri</h2>
            <p><?= count($team_members) ?> üye - <?= $team_data['max_members'] - count($team_members) ?> boş slot</p>
        </div>
        
        <div class="header-filters">
            <!-- Role Filter -->
            <select id="roleFilter" class="form-select">
                <option value="">Tüm Roller</option>
                <?php foreach ($team_stats['role_distribution'] as $role): ?>
                    <option value="<?= htmlspecialchars($role['display_name']) ?>">
                        <?= htmlspecialchars($role['display_name']) ?> (<?= $role['count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Status Filter -->
            <select id="statusFilter" class="form-select">
                <option value="">Tüm Durumlar</option>
                <option value="active">Aktif</option>
                <option value="inactive">İnaktif</option>
                <option value="banned">Banlı</option>
            </select>
            
            <!-- Search -->
            <div class="search-box">
                <input type="text" id="memberSearch" placeholder="Üye ara..." class="form-control">
                <i class="fas fa-search"></i>
            </div>
        </div>
    </div>

    <!-- Members List -->
    <div class="members-list">
        <?php if (empty($team_members)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>Henüz takım üyesi bulunmuyor</h3>
                <p>İlk üyeler takıma katıldığında burada görünecekler.</p>
            </div>
        <?php else: ?>
            <?php foreach ($team_members as $member): ?>
                <div class="member-card" 
                     data-member-id="<?= $member['id'] ?>"
                     data-role="<?= htmlspecialchars($member['role_display_name']) ?>"
                     data-status="<?= $member['status'] ?>"
                     data-username="<?= htmlspecialchars($member['username']) ?>">
                    
                    <!-- Member Header -->
                    <div class="member-header">
                        <div class="member-basic-info">
                            <!-- Avatar -->
                            <div class="member-avatar">
                                <?php if ($member['avatar_path']): ?>
                                    <img src="/uploads/avatars/<?= htmlspecialchars($member['avatar_path']) ?>" 
                                         alt="<?= htmlspecialchars($member['username']) ?>" 
                                         class="avatar-img">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?= strtoupper(substr($member['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Status Indicator -->
                                <div class="status-indicator <?= $member['status'] ?>"></div>
                            </div>
                            
                            <!-- Member Info -->
                            <div class="member-info">
                                <div class="member-name-section">
                                    <h4 class="member-name">
                                        <a href="#" class="user-link" data-user-id="<?= $member['user_id'] ?>" 
                                           style="color: <?= htmlspecialchars($member['role_color']) ?>;">
                                            <?= htmlspecialchars($member['username']) ?>
                                        </a>
                                        
                                        <?php if ($member['user_id'] == $team_data['created_by_user_id']): ?>
                                            <i class="fas fa-crown owner-crown" title="Takım Sahibi" 
                                               style="color: <?= htmlspecialchars($member['role_color']) ?>;"></i>
                                        <?php endif; ?>
                                        
                                        <?php if ($member['is_management']): ?>
                                            <i class="fas fa-shield-alt management-badge" title="Yönetici" 
                                               style="color: <?= htmlspecialchars($member['role_color']) ?>;"></i>
                                        <?php endif; ?>
                                    </h4>
                                    
                                    <div class="member-role">
                                        <span class="role-badge" style="background-color: <?= htmlspecialchars($member['role_color']) ?>; color: white;">
                                            <?= htmlspecialchars($member['role_display_name']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($member['ingame_name']): ?>
                                    <div class="member-ingame">
                                        <i class="fas fa-gamepad"></i>
                                        <?= htmlspecialchars($member['ingame_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Member Stats -->
                        <div class="member-stats">
                            <div class="stat-item">
                                <div class="stat-label">Katılım</div>
                                <div class="stat-value"><?= date('d.m.Y', strtotime($member['joined_at'])) ?></div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-label">Üyelik</div>
                                <div class="stat-value">
                                    <?php
                                    $days = (new DateTime())->diff(new DateTime($member['joined_at']))->days;
                                    if ($days < 30) {
                                        echo $days . ' gün';
                                    } elseif ($days < 365) {
                                        echo round($days / 30) . ' ay';
                                    } else {
                                        echo round($days / 365, 1) . ' yıl';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <?php if ($member['last_activity']): ?>
                                <div class="stat-item">
                                    <div class="stat-label">Son Aktivite</div>
                                    <div class="stat-value">
                                        <?php
                                        $activity_days = (new DateTime())->diff(new DateTime($member['last_activity']))->days;
                                        if ($activity_days == 0) {
                                            echo 'Bugün';
                                        } elseif ($activity_days == 1) {
                                            echo 'Dün';
                                        } elseif ($activity_days < 7) {
                                            echo $activity_days . ' gün önce';
                                        } elseif ($activity_days < 30) {
                                            echo round($activity_days / 7) . ' hafta önce';
                                        } else {
                                            echo round($activity_days / 30) . ' ay önce';
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Member Actions -->
                    <div class="member-actions">
                        <?php 
                        $can_modify_member = true;
                        
                        // Kendini değiştiremez
                        if ($member['user_id'] == $current_user_id) {
                            $can_modify_member = false;
                        }
                        
                        // Takım sahibini değiştiremez (sadece kendisi değiştirebilir)
                        if ($member['user_id'] == $team_data['created_by_user_id'] && !$is_team_owner) {
                            $can_modify_member = false;
                        }
                        
                        // Yönetici olmayan, yöneticiyi değiştiremez
                        if ($member['is_management'] && !$is_team_owner && !$is_team_manager) {
                            $can_modify_member = false;
                        }
                        ?>
                        
                        <?php if ($can_modify_member): ?>
                            <!-- Role Change -->
                            <div class="action-group">
                                <label class="action-label">Rol:</label>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="change_member_role">
                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                    
                                    <select name="new_role_id" class="role-select form-select" 
                                            data-member-name="<?= htmlspecialchars($member['username']) ?>"
                                            data-original-value="<?= $member['team_role_id'] ?>">
                                        <?php
                                        // Rolleri priority'ye göre getir
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM team_roles 
                                            WHERE team_id = ? 
                                            ORDER BY priority ASC
                                        ");
                                        $stmt->execute([$team_id]);
                                        $available_roles = $stmt->fetchAll();
                                        
                                        foreach ($available_roles as $role):
                                            // Owner rolü sadece mevcut owner tarafından atanabilir
                                            if ($role['name'] === 'owner' && !$is_team_owner) continue;
                                            
                                            // Yönetim rolleri sadece owner tarafından atanabilir
                                            if ($role['is_management'] && !$is_team_owner) continue;
                                        ?>
                                            <option value="<?= $role['id'] ?>" 
                                                    <?= $role['id'] == $member['team_role_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($role['display_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            
                            <!-- Member Status Actions -->
                            <?php if ($member['status'] === 'active'): ?>
                                <div class="action-group">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="kick_member">
                                        <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                        
                                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                data-action="kick" 
                                                data-member-name="<?= htmlspecialchars($member['username']) ?>"
                                                title="Üyeyi takımdan at">
                                            <i class="fas fa-user-times"></i> At
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($member['status'] === 'inactive'): ?>
                                <div class="action-group">
                                    <span class="status-text inactive">İnaktif</span>
                                </div>
                            <?php elseif ($member['status'] === 'banned'): ?>
                                <div class="action-group">
                                    <span class="status-text banned">Banlı</span>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="action-group">
                                <?php if ($member['user_id'] == $current_user_id): ?>
                                    <span class="status-text own">Siz</span>
                                <?php elseif ($member['user_id'] == $team_data['created_by_user_id']): ?>
                                    <span class="status-text owner">Takım Sahibi</span>
                                <?php else: ?>
                                    <span class="status-text protected">Korumalı</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- View Profile -->
                        <div class="action-group">
                            <a href="/view_profile.php?user_id=<?= $member['user_id'] ?>" 
                               class="btn btn-outline-secondary btn-sm" 
                               title="Profili görüntüle" target="_blank">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Additional Notes (if any) -->
                    <?php if ($member['notes']): ?>
                        <div class="member-notes">
                            <div class="notes-header">
                                <i class="fas fa-sticky-note"></i> Yönetici Notları:
                            </div>
                            <div class="notes-content">
                                <?= nl2br(htmlspecialchars($member['notes'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Members Summary -->
    <?php if (!empty($team_members)): ?>
        <div class="members-summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Toplam Üye</div>
                    <div class="summary-value"><?= count($team_members) ?></div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-label">Aktif Üye</div>
                    <div class="summary-value">
                        <?= count(array_filter($team_members, function($m) { return $m['status'] === 'active'; })) ?>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-label">Yönetici</div>
                    <div class="summary-value">
                        <?= count(array_filter($team_members, function($m) { return $m['is_management'] && $m['status'] === 'active'; })) ?>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-label">Boş Slot</div>
                    <div class="summary-value"><?= $team_data['max_members'] - count($team_members) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter and search functionality
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const memberSearch = document.getElementById('memberSearch');
    const memberCards = document.querySelectorAll('.member-card');
    
    function filterMembers() {
        const roleValue = roleFilter.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        const searchValue = memberSearch.value.toLowerCase();
        
        memberCards.forEach(card => {
            const role = card.dataset.role.toLowerCase();
            const status = card.dataset.status.toLowerCase();
            const username = card.dataset.username.toLowerCase();
            
            const roleMatch = !roleValue || role.includes(roleValue);
            const statusMatch = !statusValue || status === statusValue;
            const searchMatch = !searchValue || username.includes(searchValue);
            
            if (roleMatch && statusMatch && searchMatch) {
                card.style.display = 'block';
                card.style.animation = 'fadeInUp 0.3s ease-out';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Update visible count
        const visibleCount = document.querySelectorAll('.member-card[style*="display: block"], .member-card:not([style*="display: none"])').length;
        const headerInfo = document.querySelector('.members-header .header-info p');
        if (headerInfo) {
            headerInfo.textContent = `${visibleCount} üye görüntüleniyor`;
        }
    }
    
    // Event listeners
    roleFilter.addEventListener('change', filterMembers);
    statusFilter.addEventListener('change', filterMembers);
    memberSearch.addEventListener('input', filterMembers);
    
    // Role change handling
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            const memberName = this.dataset.memberName;
            const roleName = this.options[this.selectedIndex].text;
            const originalValue = this.dataset.originalValue;
            
            if (this.value !== originalValue) {
                if (confirm(`${memberName} adlı üyenin rolünü "${roleName}" olarak değiştirmek istediğinizden emin misiniz?`)) {
                    this.closest('form').submit();
                } else {
                    // Reset to original value
                    this.value = originalValue;
                }
            }
        });
    });
    
    // Kick member confirmation
    document.querySelectorAll('[data-action="kick"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const memberName = this.dataset.memberName;
            if (!confirm(`${memberName} adlı üyeyi takımdan atmak istediğinizden emin misiniz?\n\nBu işlem geri alınamaz.`)) {
                e.preventDefault();
            }
        });
    });
});
</script>