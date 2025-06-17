<?php
// /teams/manage_tabs/members.php
// Bu dosya takım yönetimi sayfasının members tab'ında include edilir

// Bu dosya çalıştırıldığında gerekli değişkenler manage.php'den gelir:
// $team_data, $team_id, $is_team_owner, $current_user_id, $pdo, $team_members, $team_roles

// Üyeleri getir (eğer manage.php'de getirilmemişse)
if (!isset($team_members)) {
    $stmt = $pdo->prepare("
        SELECT tm.*, u.username, u.ingame_name, u.avatar_path, u.email,
               tr.name as role_name, tr.display_name as role_display_name, 
               tr.color as role_color, tr.priority as role_priority, tr.is_management
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        JOIN team_roles tr ON tm.team_role_id = tr.id
        WHERE tm.team_id = ? AND tm.status = 'active'
        ORDER BY tr.priority ASC, tm.joined_at ASC
    ");
    $stmt->execute([$team_id]);
    $team_members = $stmt->fetchAll();
}

// Roller listesi (eğer manage.php'de getirilmemişse)
if (!isset($team_roles)) {
    $stmt = $pdo->prepare("
        SELECT * FROM team_roles 
        WHERE team_id = ? 
        ORDER BY priority ASC
    ");
    $stmt->execute([$team_id]);
    $team_roles = $stmt->fetchAll();
}

// Yetki kontrolü
$can_manage_members = $is_team_owner || has_permission($pdo, 'teams.manage_members', $current_user_id);

// Üye istatistikleri
$member_stats = [
    'total' => count($team_members),
    'management' => count(array_filter($team_members, function($m) { 
        return isset($m['is_management']) && $m['is_management']; 
    })),
    'regular' => count(array_filter($team_members, function($m) { 
        return !isset($m['is_management']) || !$m['is_management']; 
    })),
    'recent_joins' => count(array_filter($team_members, function($m) { 
        return strtotime($m['joined_at']) > strtotime('-7 days'); 
    }))
];

// Rol dağılımı
$role_distribution = [];
foreach ($team_members as $member) {
    $role_key = $member['role_display_name'];
    if (!isset($role_distribution[$role_key])) {
        $role_distribution[$role_key] = [
            'count' => 0,
            'color' => $member['role_color'],
            'priority' => $member['role_priority']
        ];
    }
    $role_distribution[$role_key]['count']++;
}

// Rol önceliğine göre sırala
uasort($role_distribution, fn($a, $b) => $a['priority'] <=> $b['priority']);
?>

<!-- Members Tab Content -->
<div class="members-tab">
    <!-- Üye İstatistikleri -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Üye İstatistikleri</h3>
        </div>
        <div class="card-body">
            <div class="member-stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $member_stats['total'] ?>/<?= $team_data['max_members'] ?></div>
                        <div class="stat-label">Toplam Üye</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $member_stats['management'] ?></div>
                        <div class="stat-label">Yönetici</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $member_stats['regular'] ?></div>
                        <div class="stat-label">Normal Üye</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $member_stats['recent_joins'] ?></div>
                        <div class="stat-label">Son 7 Gün</div>
                    </div>
                </div>
            </div>
            
            <!-- Rol Dağılımı -->
            <?php if (!empty($role_distribution)): ?>
                <div class="role-distribution">
                    <h4><i class="fas fa-pie-chart"></i> Rol Dağılımı</h4>
                    <div class="role-stats">
                        <?php foreach ($role_distribution as $role_name => $role_data): ?>
                            <div class="role-stat-item">
                                <div class="role-color-indicator" style="background-color: <?= htmlspecialchars($role_data['color']) ?>"></div>
                                <span class="role-name"><?= htmlspecialchars($role_name) ?></span>
                                <span class="role-count"><?= $role_data['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Üye Arama ve Filtreleme -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-search"></i> Üye Arama</h3>
        </div>
        <div class="card-body">
            <div class="member-filters">
                <div class="filter-group">
                    <input type="text" 
                           id="memberSearch" 
                           class="form-control" 
                           placeholder="Üye adı veya oyun içi isim ara...">
                </div>
                
                <div class="filter-group">
                    <select id="roleFilter" class="form-control">
                        <option value="">Tüm Roller</option>
                        <?php foreach ($team_roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['name']) ?>">
                                <?= htmlspecialchars($role['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select id="joinDateFilter" class="form-control">
                        <option value="">Tüm Zamanlar</option>
                        <option value="7">Son 7 Gün</option>
                        <option value="30">Son 30 Gün</option>
                        <option value="90">Son 3 Ay</option>
                    </select>
                </div>
                
                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Temizle
                </button>
            </div>
        </div>
    </div>

    <!-- Üye Listesi -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Takım Üyeleri (<?= count($team_members) ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($team_members)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h4>Henüz üye yok</h4>
                    <p>Takımınıza henüz kimse katılmamış. Başvuruları bekleyin veya üye davet edin.</p>
                </div>
            <?php else: ?>
                <div class="members-list" id="membersList">
                    <?php foreach ($team_members as $member): ?>
                        <div class="member-card" 
                             data-username="<?= htmlspecialchars(strtolower($member['username'])) ?>"
                             data-ingame="<?= htmlspecialchars(strtolower($member['ingame_name'] ?? '')) ?>"
                             data-role="<?= htmlspecialchars($member['role_name']) ?>"
                             data-joined="<?= strtotime($member['joined_at']) ?>">
                            
                            <!-- Üye Bilgileri -->
                            <div class="member-info">
                                <div class="member-avatar">
                                    <?php if (!empty($member['avatar_path'])): ?>
                                        <img src="/<?= htmlspecialchars($member['avatar_path']) ?>" 
                                             alt="<?= htmlspecialchars($member['username']) ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($member['username'], 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Üye Durumu -->
                                    <?php if ($member['user_id'] == $team_data['created_by_user_id']): ?>
                                        <div class="member-badge owner-badge" title="Takım Lideri">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                    <?php elseif (isset($member['is_management']) && $member['is_management']): ?>
                                        <div class="member-badge management-badge" title="Yönetici">
                                            <i class="fas fa-star"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="member-details">
                                    <div class="member-names">
                                        <h5 class="member-username">
                                            <?= htmlspecialchars($member['username']) ?>
                                        </h5>
                                        <?php if (!empty($member['ingame_name'])): ?>
                                            <span class="member-ingame">
                                                <?= htmlspecialchars($member['ingame_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="member-meta">
                                        <span class="member-role" style="color: <?= htmlspecialchars($member['role_color']) ?>">
                                            <i class="fas fa-tag"></i>
                                            <?= htmlspecialchars($member['role_display_name']) ?>
                                        </span>
                                        
                                        <span class="member-joined">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('d.m.Y', strtotime($member['joined_at'])) ?>
                                        </span>
                                        
                                        <?php if (!empty($member['last_activity'])): ?>
                                            <span class="member-activity">
                                                <i class="fas fa-clock"></i>
                                                <?= date('d.m.Y', strtotime($member['last_activity'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Üye Eylemleri -->
                            <?php if ($can_manage_members && $member['user_id'] != $current_user_id): ?>
                                <div class="member-actions">
                                    <!-- Rol Değiştirme -->
                                    <?php if ($member['user_id'] != $team_data['created_by_user_id']): ?>
                                        <form method="post" class="role-change-form" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="change_member_role">
                                            <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                            
                                            <select name="new_role_id" 
                                                    class="form-control form-control-sm role-select"
                                                    data-member-name="<?= htmlspecialchars($member['username']) ?>"
                                                    data-original-role="<?= $member['team_role_id'] ?>">
                                                <?php foreach ($team_roles as $role): ?>
                                                    <option value="<?= $role['id'] ?>" 
                                                            <?= $role['id'] == $member['team_role_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($role['display_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        
                                        <!-- Üye Atma -->
                                        <form method="post" class="kick-form" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="kick_member">
                                            <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                            
                                            <button type="submit" 
                                                    class="btn btn-danger btn-sm"
                                                    data-action="kick"
                                                    data-member-name="<?= htmlspecialchars($member['username']) ?>"
                                                    title="Takımdan At">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="owner-label">
                                            <i class="fas fa-crown"></i> Takım Lideri
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Üye Yönetimi İpuçları -->
    <?php if ($can_manage_members): ?>
        <div class="manage-card">
            <div class="card-header">
                <h3><i class="fas fa-lightbulb"></i> Üye Yönetimi İpuçları</h3>
            </div>
            <div class="card-body">
                <div class="tips-grid">
                    <div class="tip-item">
                        <i class="fas fa-user-cog"></i>
                        <div>
                            <strong>Rol Değiştirme:</strong>
                            <p>Üyelerin rollerini değiştirmek için dropdown menüyü kullanın. Değişiklik otomatik olarak kaydedilir.</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Takım Lideri:</strong>
                            <p>Takım lideri atanamaz ve rolü değiştirilemez. Liderlik devri için iletişime geçin.</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <i class="fas fa-search"></i>
                        <div>
                            <strong>Üye Arama:</strong>
                            <p>Büyük takımlarda üyeleri bulmak için arama ve filtreleme özelliklerini kullanın.</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <i class="fas fa-history"></i>
                        <div>
                            <strong>Aktivite Takibi:</strong>
                            <p>Üyelerin son aktivite tarihlerini takip ederek aktif olmayan üyeleri belirleyin.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
// Members Tab JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Arama ve filtreleme
    const searchInput = document.getElementById('memberSearch');
    const roleFilter = document.getElementById('roleFilter');
    const joinDateFilter = document.getElementById('joinDateFilter');
    const membersList = document.getElementById('membersList');
    
    function filterMembers() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedRole = roleFilter.value;
        const selectedDays = joinDateFilter.value;
        const currentTime = Date.now() / 1000;
        
        const memberCards = membersList.querySelectorAll('.member-card');
        
        memberCards.forEach(card => {
            let show = true;
            
            // Arama filtresi
            if (searchTerm) {
                const username = card.dataset.username;
                const ingame = card.dataset.ingame;
                if (!username.includes(searchTerm) && !ingame.includes(searchTerm)) {
                    show = false;
                }
            }
            
            // Rol filtresi
            if (selectedRole && card.dataset.role !== selectedRole) {
                show = false;
            }
            
            // Tarih filtresi
            if (selectedDays) {
                const joinTime = parseInt(card.dataset.joined);
                const daysDiff = (currentTime - joinTime) / (24 * 60 * 60);
                if (daysDiff > parseInt(selectedDays)) {
                    show = false;
                }
            }
            
            card.style.display = show ? 'flex' : 'none';
        });
    }
    
    // Event listeners
    searchInput.addEventListener('input', filterMembers);
    roleFilter.addEventListener('change', filterMembers);
    joinDateFilter.addEventListener('change', filterMembers);
    
    // Rol değişikliği onayı
    document.querySelectorAll('.role-select').forEach(select => {
        const originalValue = select.value;
        select.dataset.originalValue = originalValue;
        
        select.addEventListener('change', function() {
            const memberName = this.dataset.memberName;
            const newRole = this.options[this.selectedIndex].text;
            
            if (confirm(`${memberName} adlı üyenin rolünü "${newRole}" olarak değiştirmek istediğinizden emin misiniz?`)) {
                this.closest('form').submit();
            } else {
                this.value = this.dataset.originalValue;
            }
        });
    });
});

// Filtreleri temizle
function clearFilters() {
    document.getElementById('memberSearch').value = '';
    document.getElementById('roleFilter').value = '';
    document.getElementById('joinDateFilter').value = '';
    
    // Tüm üyeleri göster
    document.querySelectorAll('.member-card').forEach(card => {
        card.style.display = 'flex';
    });
}
</script>