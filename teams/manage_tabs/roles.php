<?php
// /teams/manage_tabs/roles.php
// Bu dosya takım yönetimi sayfasının roles tab'ında include edilir

// Bu dosya çalıştırıldığında gerekli değişkenler manage.php'den gelir:
// $team_data, $team_id, $is_team_owner, $current_user_id, $pdo

// Yetki kontrolü
if (!$is_team_owner && !has_permission($pdo, 'teams.edit_all', $current_user_id)) {
    echo '<div class="manage-card">';
    echo '<div class="card-body">';
    echo '<div class="alert alert-danger">';
    echo '<i class="fas fa-exclamation-triangle"></i> Bu ayarlara erişim yetkiniz yok.';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    return;
}

// Roller ve yetkileri getir (eğer manage.php'de getirilmemişse)
if (!isset($team_roles_detailed)) {
    $stmt = $pdo->prepare("
        SELECT tr.*, COUNT(tm.id) as member_count
        FROM team_roles tr
        LEFT JOIN team_members tm ON tr.id = tm.team_role_id AND tm.status = 'active'
        WHERE tr.team_id = ?
        GROUP BY tr.id
        ORDER BY tr.priority ASC
    ");
    $stmt->execute([$team_id]);
    $team_roles_detailed = $stmt->fetchAll();
}

// Takım yetkilerini getir
$stmt = $pdo->prepare("
    SELECT * FROM team_permissions 
    WHERE is_active = 1 
    ORDER BY permission_group, permission_name
");
$stmt->execute();
$all_permissions = $stmt->fetchAll();

// Yetkileri gruplara ayır
$permission_groups = [];
foreach ($all_permissions as $permission) {
    $group = $permission['permission_group'];
    if (!isset($permission_groups[$group])) {
        $permission_groups[$group] = [];
    }
    $permission_groups[$group][] = $permission;
}

// Her rol için mevcut yetkileri getir
$role_permissions = [];
foreach ($team_roles_detailed as $role) {
    $stmt = $pdo->prepare("
        SELECT tp.permission_key 
        FROM team_role_permissions trp
        JOIN team_permissions tp ON trp.team_permission_id = tp.id
        WHERE trp.team_role_id = ?
    ");
    $stmt->execute([$role['id']]);
    $role_permissions[$role['id']] = array_column($stmt->fetchAll(), 'permission_key');
}

// Grup isimlerini Türkçeleştir
$group_names = [
    'management' => 'Yönetim',
    'moderation' => 'Moderasyon', 
    'members' => 'Üye İşlemleri',
    'content' => 'İçerik',
    'view' => 'Görüntüleme',
    'communication' => 'İletişim'
];
?>

<!-- Roles Tab Content -->
<div class="roles-tab">
    <!-- Rol İstatistikleri -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Rol İstatistikleri</h3>
        </div>
        <div class="card-body">
            <div class="role-stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= count($team_roles_detailed) ?></div>
                        <div class="stat-label">Toplam Rol</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?= count(array_filter($team_roles_detailed, function($r) { 
                                return isset($r['is_management']) && $r['is_management']; 
                            })) ?>
                        </div>
                        <div class="stat-label">Yönetici Rol</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= count($all_permissions) ?></div>
                        <div class="stat-label">Mevcut Yetki</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?= count(array_filter($team_roles_detailed, function($r) { 
                                return isset($r['is_default']) && $r['is_default']; 
                            })) ?>
                        </div>
                        <div class="stat-label">Varsayılan Rol</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Rol Oluşturma -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Yeni Rol Oluştur</h3>
        </div>
        <div class="card-body">
            <form method="post" class="role-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="create_role">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role_name" class="form-label">
                            <i class="fas fa-tag"></i> Rol Adı
                        </label>
                        <input type="text" 
                               id="role_name" 
                               name="role_name" 
                               class="form-control" 
                               placeholder="Örn: Moderatör"
                               maxlength="50" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_display_name" class="form-label">
                            <i class="fas fa-eye"></i> Görünen Ad
                        </label>
                        <input type="text" 
                               id="role_display_name" 
                               name="role_display_name" 
                               class="form-control" 
                               placeholder="Örn: Takım Moderatörü"
                               maxlength="100" 
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role_color" class="form-label">
                            <i class="fas fa-palette"></i> Rol Rengi
                        </label>
                        <div class="color-input-group">
                            <input type="color" 
                                   id="role_color" 
                                   name="role_color" 
                                   class="form-control color-picker" 
                                   value="#6c757d">
                            <input type="text" 
                                   id="role_color_text" 
                                   class="form-control color-text" 
                                   value="#6c757d" 
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   maxlength="7">
                        </div>
                        <small class="form-help">Rolün görünüm rengi.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_priority" class="form-label">
                            <i class="fas fa-sort-numeric-down"></i> Öncelik
                        </label>
                        <input type="number" 
                               id="role_priority" 
                               name="role_priority" 
                               class="form-control" 
                               value="100"
                               min="1" 
                               max="999" 
                               required>
                        <small class="form-help">Düşük sayı = Yüksek öncelik.</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" 
                                   id="is_management" 
                                   name="is_management">
                            <label for="is_management" class="checkbox-label">
                                <i class="fas fa-crown"></i> Yönetici Rolü
                            </label>
                        </div>
                        <small class="form-help">Yönetici rolleri takım yönetimi yapabilir.</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" 
                                   id="is_default" 
                                   name="is_default">
                            <label for="is_default" class="checkbox-label">
                                <i class="fas fa-star"></i> Varsayılan Rol
                            </label>
                        </div>
                        <small class="form-help">Yeni üyeler bu role atanır.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role_description" class="form-label">
                        <i class="fas fa-align-left"></i> Açıklama
                    </label>
                    <textarea id="role_description" 
                              name="role_description" 
                              class="form-control" 
                              rows="3" 
                              maxlength="500"
                              placeholder="Rol açıklaması..."></textarea>
                    <small class="form-help">Rolün görevlerini açıklayın.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Rol Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mevcut Roller -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-users-cog"></i> Mevcut Roller (<?= count($team_roles_detailed) ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($team_roles_detailed)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-tag"></i>
                    <h4>Henüz rol yok</h4>
                    <p>Takımınız için roller oluşturun ve yetkileri düzenleyin.</p>
                </div>
            <?php else: ?>
                <div class="roles-list">
                    <?php foreach ($team_roles_detailed as $role): ?>
                        <div class="role-card" data-role-id="<?= $role['id'] ?>">
                            <!-- Rol Bilgileri -->
                            <div class="role-header">
                                <div class="role-info">
                                    <div class="role-color-indicator" 
                                         style="background-color: <?= htmlspecialchars($role['color']) ?>"></div>
                                    
                                    <div class="role-details">
                                        <h4 class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>">
                                            <?= htmlspecialchars($role['display_name']) ?>
                                            
                                            <?php if (isset($role['is_management']) && $role['is_management']): ?>
                                                <span class="role-badge management-badge">
                                                    <i class="fas fa-crown"></i> Yönetici
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($role['is_default']) && $role['is_default']): ?>
                                                <span class="role-badge default-badge">
                                                    <i class="fas fa-star"></i> Varsayılan
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        
                                        <div class="role-meta">
                                            <span class="role-priority">
                                                <i class="fas fa-sort-numeric-down"></i>
                                                Öncelik: <?= $role['priority'] ?>
                                            </span>
                                            
                                            <span class="role-members">
                                                <i class="fas fa-users"></i>
                                                <?= $role['member_count'] ?> üye
                                            </span>
                                            
                                            <span class="role-permissions">
                                                <i class="fas fa-shield-alt"></i>
                                                <?= count($role_permissions[$role['id']] ?? []) ?> yetki
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($role['description'])): ?>
                                            <p class="role-description">
                                                <?= htmlspecialchars($role['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="role-actions">
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="togglePermissions(<?= $role['id'] ?>)">
                                        <i class="fas fa-cog"></i> Yetkileri Düzenle
                                    </button>
                                    
                                    <?php if ($role['name'] !== 'owner' && $role['member_count'] == 0): ?>
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete_role">
                                            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                            
                                            <button type="submit" 
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Bu rolü silmek istediğinizden emin misiniz?')"
                                                    title="Rolü Sil">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Yetki Düzenleme Paneli -->
                            <div class="role-permissions-panel" id="permissions-<?= $role['id'] ?>" style="display: none;">
                                <form method="post" class="permissions-form">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="update_role_permissions">
                                    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                    
                                    <div class="permissions-header">
                                        <h5><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($role['display_name']) ?> Yetkileri</h5>
                                    </div>
                                    
                                    <div class="permissions-groups">
                                        <?php foreach ($permission_groups as $group_key => $permissions): ?>
                                            <div class="permission-group">
                                                <h6 class="group-header">
                                                    <i class="fas fa-folder"></i>
                                                    <?= $group_names[$group_key] ?? ucfirst($group_key) ?>
                                                </h6>
                                                
                                                <div class="permissions-grid">
                                                    <?php foreach ($permissions as $permission): ?>
                                                        <div class="permission-item">
                                                            <input type="checkbox" 
                                                                   id="perm_<?= $role['id'] ?>_<?= $permission['id'] ?>"
                                                                   name="permissions[]" 
                                                                   value="<?= $permission['id'] ?>"
                                                                   <?= in_array($permission['permission_key'], $role_permissions[$role['id']] ?? []) ? 'checked' : '' ?>>
                                                            <label for="perm_<?= $role['id'] ?>_<?= $permission['id'] ?>" 
                                                                   class="permission-label">
                                                                <?= htmlspecialchars($permission['permission_name']) ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="permissions-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Yetkileri Kaydet
                                        </button>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-secondary"
                                                onclick="togglePermissions(<?= $role['id'] ?>)">
                                            <i class="fas fa-times"></i> İptal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rol Yönetimi İpuçları -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-lightbulb"></i> Rol Yönetimi İpuçları</h3>
        </div>
        <div class="card-body">
            <div class="tips-grid">
                <div class="tip-item">
                    <i class="fas fa-sort-numeric-down"></i>
                    <div>
                        <strong>Öncelik Sistemi:</strong>
                        <p>Düşük sayı yüksek öncelik anlamına gelir. Owner rolü her zaman en yüksek önceliklidir (1).</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-star"></i>
                    <div>
                        <strong>Varsayılan Rol:</strong>
                        <p>Yeni katılan üyeler otomatik olarak varsayılan role atanır. Sadece bir rol varsayılan olabilir.</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Yetki Sistemi:</strong>
                        <p>Roller farklı yetki gruplarına sahip olabilir. Yönetici rolleri otomatik olarak tüm yetkilere sahiptir.</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <i class="fas fa-trash-alt"></i>
                    <div>
                        <strong>Rol Silme:</strong>
                        <p>Sadece üyesi olmayan roller silinebilir. Owner rolü hiçbir zaman silinemez.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Roles Tab JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Color picker synchronization
    const colorPicker = document.getElementById('role_color');
    const colorText = document.getElementById('role_color_text');
    
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value.toUpperCase();
        });
        
        colorText.addEventListener('input', function() {
            const value = this.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                colorPicker.value = value;
            }
        });
    }
    
    // Form validation
    const roleForm = document.querySelector('.role-form');
    if (roleForm) {
        roleForm.addEventListener('submit', function(e) {
            const roleName = document.getElementById('role_name').value.trim();
            const displayName = document.getElementById('role_display_name').value.trim();
            const priority = parseInt(document.getElementById('role_priority').value);
            
            if (roleName.length < 2 || roleName.length > 50) {
                e.preventDefault();
                alert('Rol adı 2-50 karakter arasında olmalıdır.');
                return;
            }
            
            if (displayName.length < 2 || displayName.length > 100) {
                e.preventDefault();
                alert('Görünen ad 2-100 karakter arasında olmalıdır.');
                return;
            }
            
            if (priority < 1 || priority > 999) {
                e.preventDefault();
                alert('Öncelik 1-999 arasında olmalıdır.');
                return;
            }
        });
    }
});

// Yetki panelini aç/kapat
function togglePermissions(roleId) {
    const panel = document.getElementById('permissions-' + roleId);
    if (panel) {
        if (panel.style.display === 'none' || panel.style.display === '') {
            panel.style.display = 'block';
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            panel.style.display = 'none';
        }
    }
}

// Checkbox grup seçimi
function toggleGroupPermissions(groupCheckbox, groupName) {
    const groupContainer = groupCheckbox.closest('.permission-group');
    const checkboxes = groupContainer.querySelectorAll('input[type="checkbox"]:not(.group-checkbox)');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = groupCheckbox.checked;
    });
}

// Form submit confirmation
document.addEventListener('submit', function(e) {
    if (e.target.classList.contains('permissions-form')) {
        const roleName = e.target.closest('.role-card').querySelector('.role-name').textContent.trim();
        if (!confirm(`${roleName} rolünün yetkilerini güncellemek istediğinizden emin misiniz?`)) {
            e.preventDefault();
        }
    }
});
</script>