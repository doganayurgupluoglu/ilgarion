<?php
// /teams/manage_tabs/roles.php
// Bu dosya teams/manage.php içinde include edilir

if (!defined('BASE_PATH') || !isset($team_data)) {
    die('Bu dosya doğrudan erişilemez.');
}

// Sadece team owner bu sayfayı görebilir
if (!$is_team_owner) {
    echo '<div class="access-denied">
            <i class="fas fa-lock"></i>
            <h3>Erişim Reddedildi</h3>
            <p>Bu sayfayı görüntülemek için takım sahibi olmalısınız.</p>
          </div>';
    return;
}
?>

<div class="roles-content">
    <!-- Roles Header -->
    <div class="roles-header">
        <div class="header-info">
            <h2>Rol Yönetimi</h2>
            <p>Takım rollerini ve yetkilerini yönetin</p>
        </div>
        
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="showCreateRoleModal()">
                <i class="fas fa-plus"></i> Yeni Rol Oluştur
            </button>
        </div>
    </div>

    <!-- Roles List -->
    <div class="roles-list">
        <?php if (empty($team_roles)): ?>
            <div class="empty-state">
                <i class="fas fa-user-tag"></i>
                <h3>Henüz rol bulunmuyor</h3>
                <p>İlk rolünüzü oluşturmak için "Yeni Rol Oluştur" butonuna tıklayın.</p>
            </div>
        <?php else: ?>
            <?php foreach ($team_roles as $role): ?>
                <div class="role-card" data-role-id="<?= $role['id'] ?>">
                    <!-- Role Header -->
                    <div class="role-header">
                        <div class="role-basic-info">
                            <div class="role-color-indicator" style="background-color: <?= htmlspecialchars($role['color']) ?>;"></div>
                            
                            <div class="role-info">
                                <h4 class="role-name" style="color: <?= htmlspecialchars($role['color']) ?>;">
                                    <?= htmlspecialchars($role['display_name']) ?>
                                    
                                    <?php if ($role['name'] === 'owner'): ?>
                                        <i class="fas fa-crown owner-indicator" title="Takım Sahibi Rolü"></i>
                                    <?php endif; ?>
                                    
                                    <?php if ($role['is_management']): ?>
                                        <i class="fas fa-shield-alt management-indicator" title="Yönetici Rolü"></i>
                                    <?php endif; ?>
                                    
                                    <?php if ($role['is_default']): ?>
                                        <span class="default-badge">Varsayılan</span>
                                    <?php endif; ?>
                                </h4>
                                
                                <div class="role-details">
                                    <span class="role-priority">Öncelik: <?= $role['priority'] ?></span>
                                    <span class="role-members"><?= $role['member_count'] ?> üye</span>
                                </div>
                                
                                <?php if ($role['description']): ?>
                                    <div class="role-description">
                                        <?= htmlspecialchars($role['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="role-actions">
                            <?php if ($role['name'] !== 'owner'): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="showEditRoleModal(<?= $role['id'] ?>)">
                                    <i class="fas fa-edit"></i> Düzenle
                                </button>
                                
                                <?php if ($role['member_count'] == 0): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['display_name']) ?>')">
                                        <i class="fas fa-trash"></i> Sil
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="protected-role">
                                    <i class="fas fa-lock"></i> Korumalı Rol
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Role Permissions -->
                    <div class="role-permissions">
                        <div class="permissions-header">
                            <h5>
                                <i class="fas fa-key"></i> Yetkiler
                                <button type="button" class="btn btn-outline-secondary btn-xs" 
                                        onclick="togglePermissions(<?= $role['id'] ?>)">
                                    <i class="fas fa-eye"></i> Göster/Gizle
                                </button>
                            </h5>
                        </div>
                        
                        <div class="permissions-list" id="permissions-<?= $role['id'] ?>" style="display: none;">
                            <?php
                            // Bu rolün yetkilerini getir
                            $stmt = $pdo->prepare("
                                SELECT tp.permission_key, tp.permission_name, tp.permission_group
                                FROM team_role_permissions trp
                                JOIN team_permissions tp ON trp.team_permission_id = tp.id
                                WHERE trp.team_role_id = ? AND tp.is_active = 1
                                ORDER BY tp.permission_group, tp.permission_name
                            ");
                            $stmt->execute([$role['id']]);
                            $role_permissions = $stmt->fetchAll();
                            
                            if (empty($role_permissions)):
                            ?>
                                <div class="no-permissions">
                                    <i class="fas fa-minus-circle"></i>
                                    Bu rolün özel yetkisi bulunmuyor.
                                </div>
                            <?php else: ?>
                                <div class="permissions-grid">
                                    <?php 
                                    $grouped_permissions = [];
                                    foreach ($role_permissions as $perm) {
                                        $grouped_permissions[$perm['permission_group']][] = $perm;
                                    }
                                    
                                    foreach ($grouped_permissions as $group => $permissions): 
                                    ?>
                                        <div class="permission-group">
                                            <div class="group-name"><?= ucfirst($group) ?></div>
                                            <div class="group-permissions">
                                                <?php foreach ($permissions as $perm): ?>
                                                    <span class="permission-item">
                                                        <i class="fas fa-check"></i>
                                                        <?= htmlspecialchars($perm['permission_name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Role Management Info -->
    <div class="role-info-section">
        <div class="info-card">
            <div class="info-header">
                <h3><i class="fas fa-info-circle"></i> Rol Yönetimi Bilgileri</h3>
            </div>
            <div class="info-content">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-sort-numeric-down"></i></div>
                        <div class="info-text">
                            <strong>Öncelik Sistemi:</strong>
                            Düşük sayı = Yüksek yetki. Owner rolü her zaman en yüksek önceliğe sahiptir.
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-star"></i></div>
                        <div class="info-text">
                            <strong>Varsayılan Rol:</strong>
                            Yeni üyeler otomatik olarak varsayılan role atanır.
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="info-text">
                            <strong>Yönetici Rolleri:</strong>
                            Takım yönetim yetkilerine sahip roller üye yönetimi yapabilir.
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-trash"></i></div>
                        <div class="info-text">
                            <strong>Rol Silme:</strong>
                            Sadece üyesi olmayan roller silinebilir. Owner rolü silinemez.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Role Modal -->
<div id="roleModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="roleForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="roleModalTitle">
                        <i class="fas fa-user-tag"></i> Yeni Rol Oluştur
                    </h5>
                    <button type="button" class="btn-close" onclick="closeRoleModal()"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" id="roleAction" value="create_role">
                    <input type="hidden" name="role_id" id="roleId">
                    
                    <!-- Basic Role Info -->
                    <div class="role-basic-section">
                        <h6>Temel Bilgiler</h6>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="roleName" class="form-label">Rol Adı:</label>
                                <input type="text" class="form-control" id="roleName" name="role_name" 
                                       maxlength="50" required>
                                <small class="form-text">Sistem içinde kullanılacak rol adı (örn: officer)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="roleDisplayName" class="form-label">Görünen Ad:</label>
                                <input type="text" class="form-control" id="roleDisplayName" name="display_name" 
                                       maxlength="100" required>
                                <small class="form-text">Kullanıcılara gösterilen ad (örn: Subay)</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="roleColor" class="form-label">Rol Rengi:</label>
                                <input type="color" class="form-control color-input" id="roleColor" name="color" 
                                       value="#6c757d">
                                <small class="form-text">Rol için özel renk seçin</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="rolePriority" class="form-label">Öncelik:</label>
                                <input type="number" class="form-control" id="rolePriority" name="priority" 
                                       min="1" max="999" value="100" required>
                                <small class="form-text">Düşük sayı = Yüksek yetki</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="roleDescription" class="form-label">Açıklama:</label>
                            <textarea class="form-control" id="roleDescription" name="description" 
                                      rows="3" maxlength="500"></textarea>
                            <small class="form-text">Rolün görevlerini açıklayın (isteğe bağlı)</small>
                        </div>
                        
                        <!-- Role Settings -->
                        <div class="role-settings">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isManagement" name="is_management">
                                <label class="form-check-label" for="isManagement">
                                    <i class="fas fa-shield-alt"></i> Yönetici Rolü
                                    <small>Takım yönetim yetkilerine sahip olsun</small>
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isDefault" name="is_default">
                                <label class="form-check-label" for="isDefault">
                                    <i class="fas fa-star"></i> Varsayılan Rol
                                    <small>Yeni üyeler bu role atansın</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permissions Section -->
                    <div class="permissions-section">
                        <h6>Yetkiler</h6>
                        <p class="text-muted">Bu role verilecek özel yetkileri seçin:</p>
                        
                        <div class="permissions-tabs">
                            <?php
                            // Yetkileri gruplara ayır
                            $grouped_permissions = [];
                            foreach ($team_permissions as $permission) {
                                $grouped_permissions[$permission['permission_group']][] = $permission;
                            }
                            ?>
                            
                            <div class="tab-nav">
                                <?php $first = true; foreach ($grouped_permissions as $group => $permissions): ?>
                                    <button type="button" class="tab-btn <?= $first ? 'active' : '' ?>" 
                                            onclick="switchPermissionTab('<?= $group ?>')">
                                        <?= ucfirst($group) ?>
                                    </button>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="tab-content">
                                <?php $first = true; foreach ($grouped_permissions as $group => $permissions): ?>
                                    <div class="tab-pane <?= $first ? 'active' : '' ?>" id="tab-<?= $group ?>">
                                        <div class="permissions-list">
                                            <?php foreach ($permissions as $permission): ?>
                                                <div class="permission-item">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input permission-checkbox" 
                                                               id="perm-<?= $permission['id'] ?>" 
                                                               name="permissions[]" 
                                                               value="<?= $permission['id'] ?>">
                                                        <label class="form-check-label" for="perm-<?= $permission['id'] ?>">
                                                            <strong><?= htmlspecialchars($permission['permission_name']) ?></strong>
                                                            <small><?= htmlspecialchars($permission['permission_key']) ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="submit" class="btn btn-primary" id="roleSubmitBtn">
                        <i class="fas fa-save"></i> Rolü Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Role management functionality
let currentEditRoleId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate role name from display name
    document.getElementById('roleDisplayName').addEventListener('input', function() {
        const displayName = this.value;
        const roleName = displayName.toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 50);
        document.getElementById('roleName').value = roleName;
    });
    
    // Default role checkbox handling
    document.getElementById('isDefault').addEventListener('change', function() {
        if (this.checked) {
            alert('Dikkat: Bu rol varsayılan rol olarak ayarlanırsa, mevcut varsayılan rol bu özelliğini kaybedecektir.');
        }
    });
});

// Role Modal Functions
function showCreateRoleModal() {
    document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-user-tag"></i> Yeni Rol Oluştur';
    document.getElementById('roleAction').value = 'create_role';
    document.getElementById('roleSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Rolü Oluştur';
    
    // Reset form
    document.getElementById('roleForm').reset();
    document.getElementById('roleId').value = '';
    currentEditRoleId = null;
    
    // Reset permissions
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
    
    // Switch to first tab
    switchPermissionTab(Object.keys(<?= json_encode($grouped_permissions) ?>)[0]);
    
    openRoleModal();
}

function showEditRoleModal(roleId) {
    currentEditRoleId = roleId;
    document.getElementById('roleModalTitle').innerHTML = '<i class="fas fa-edit"></i> Rol Düzenle';
    document.getElementById('roleAction').value = 'edit_role';
    document.getElementById('roleId').value = roleId;
    document.getElementById('roleSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Değişiklikleri Kaydet';
    
    // Load role data via AJAX or from page data
    loadRoleData(roleId);
    
    openRoleModal();
}

function openRoleModal() {
    const modal = document.getElementById('roleModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function closeRoleModal() {
    const modal = document.getElementById('roleModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function loadRoleData(roleId) {
    // Bu fonksiyon AJAX ile rol verilerini yükleyebilir
    // Şimdilik sayfa verilerini kullanacağız
    const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
    if (roleCard) {
        // Basic implementation - gerçek projede AJAX kullanın
        console.log('Loading role data for role ID:', roleId);
    }
}

// Permission Tab Functions
function switchPermissionTab(group) {
    // Remove active from all tabs
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
    
    // Add active to clicked tab
    document.querySelector(`button[onclick="switchPermissionTab('${group}')"]`).classList.add('active');
    document.getElementById(`tab-${group}`).classList.add('active');
}

// Permission Display Functions
function togglePermissions(roleId) {
    const permissionsList = document.getElementById(`permissions-${roleId}`);
    const isVisible = permissionsList.style.display !== 'none';
    
    if (isVisible) {
        permissionsList.style.display = 'none';
    } else {
        permissionsList.style.display = 'block';
    }
}

// Role Actions
function deleteRole(roleId, roleName) {
    if (!confirm(`"${roleName}" rolünü silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz.`)) {
        return;
    }
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="delete_role">
        <input type="hidden" name="role_id" value="${roleId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Modal event handlers
document.getElementById('roleModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRoleModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRoleModal();
    }
});
</script>