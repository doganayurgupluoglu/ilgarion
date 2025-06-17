<?php
// /teams/manage_tabs/settings.php
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

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Güvenlik hatası.';
    } else {
        try {
            $action = $_POST['settings_action'];
            
            switch ($action) {
                case 'update_general':
                    $stmt = $pdo->prepare("
                        UPDATE teams 
                        SET name = ?, description = ?, tag = ?, color = ?, max_members = ?, 
                            is_recruitment_open = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([
                        trim($_POST['team_name']),
                        trim($_POST['team_description']),
                        trim($_POST['team_tag']),
                        $_POST['team_color'],
                        (int)$_POST['max_members'],
                        isset($_POST['is_recruitment_open']) ? 1 : 0,
                        $team_id
                    ]);
                    
                    if ($result) {
                        $success_message = 'Genel ayarlar güncellendi.';
                        // Güncel verileri al
                        $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
                        $stmt->execute([$team_id]);
                        $team_data = $stmt->fetch();
                    } else {
                        $error_message = 'Güncelleme sırasında hata oluştu.';
                    }
                    break;
                    
                case 'update_status':
                    $stmt = $pdo->prepare("
                        UPDATE teams 
                        SET status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([
                        $_POST['team_status'],
                        $team_id
                    ]);
                    
                    if ($result) {
                        $success_message = 'Takım durumu güncellendi.';
                        $team_data['status'] = $_POST['team_status'];
                    } else {
                        $error_message = 'Durum güncellenirken hata oluştu.';
                    }
                    break;
                    
                case 'transfer_leadership':
                    $new_leader_id = (int)$_POST['new_leader_id'];
                    
                    // Yeni liderin takım üyesi olduğunu kontrol et
                    $stmt = $pdo->prepare("
                        SELECT tm.id FROM team_members tm
                        WHERE tm.team_id = ? AND tm.user_id = ? AND tm.status = 'active'
                    ");
                    $stmt->execute([$team_id, $new_leader_id]);
                    
                    if (!$stmt->fetch()) {
                        throw new Exception('Seçilen kullanıcı aktif takım üyesi değil.');
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Takım sahipliğini devret
                    $stmt = $pdo->prepare("
                        UPDATE teams 
                        SET created_by_user_id = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_leader_id, $team_id]);
                    
                    // Eski liderin rolünü değiştir (owner rolünden çıkar)
                    $stmt = $pdo->prepare("
                        SELECT id FROM team_roles 
                        WHERE team_id = ? AND is_default = 1 
                        LIMIT 1
                    ");
                    $stmt->execute([$team_id]);
                    $default_role_id = $stmt->fetchColumn();
                    
                    if ($default_role_id) {
                        $stmt = $pdo->prepare("
                            UPDATE team_members 
                            SET team_role_id = ?
                            WHERE team_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$default_role_id, $team_id, $current_user_id]);
                    }
                    
                    // Yeni lideri owner rolüne ata
                    $stmt = $pdo->prepare("
                        SELECT id FROM team_roles 
                        WHERE team_id = ? AND name = 'owner' 
                        LIMIT 1
                    ");
                    $stmt->execute([$team_id]);
                    $owner_role_id = $stmt->fetchColumn();
                    
                    if ($owner_role_id) {
                        $stmt = $pdo->prepare("
                            UPDATE team_members 
                            SET team_role_id = ?
                            WHERE team_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$owner_role_id, $team_id, $new_leader_id]);
                    }
                    
                    $pdo->commit();
                    
                    // Kullanıcıyı takım detay sayfasına yönlendir (artık yönetici değil)
                    header('Location: /teams/detail.php?id=' . $team_id . '&success=leadership_transferred');
                    exit;
                    
                case 'delete_team':
                    // Güvenlik kontrolü - sadece boş takımlar silinebilir
                    if ($team_stats['total_members'] > 1) {
                        throw new Exception('Takımda başka üyeler bulunduğu sürece takım silinemez.');
                    }
                    
                    $pdo->beginTransaction();
                    
                    // İlişkili verileri sil
                    $pdo->prepare("DELETE FROM team_applications WHERE team_id = ?")->execute([$team_id]);
                    $pdo->prepare("DELETE FROM team_members WHERE team_id = ?")->execute([$team_id]);
                    $pdo->prepare("DELETE FROM team_role_permissions WHERE team_role_id IN (SELECT id FROM team_roles WHERE team_id = ?)")->execute([$team_id]);
                    $pdo->prepare("DELETE FROM team_roles WHERE team_id = ?")->execute([$team_id]);
                    $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$team_id]);
                    
                    $pdo->commit();
                    
                    header('Location: /teams/?success=team_deleted');
                    exit;
                    
                default:
                    $error_message = 'Geçersiz işlem.';
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}

// Takım üyelerini liderlik devri için getir
$stmt = $pdo->prepare("
    SELECT tm.user_id, u.username, tr.display_name as role_name
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    JOIN team_roles tr ON tm.team_role_id = tr.id
    WHERE tm.team_id = ? AND tm.status = 'active' AND tm.user_id != ?
    ORDER BY tr.priority ASC, u.username ASC
");
$stmt->execute([$team_id, $current_user_id]);
$potential_leaders = $stmt->fetchAll();
?>

<div class="settings-content">
    <!-- Settings Header -->
    <div class="settings-header">
        <div class="header-info">
            <h2>Takım Ayarları</h2>
            <p>Takımınızın tüm ayarlarını buradan yönetebilirsiniz</p>
        </div>
        
        <div class="header-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Bu sayfadaki değişiklikler takım üyelerini etkileyebilir. Dikkatli olun.</span>
        </div>
    </div>

    <!-- Settings Sections -->
    <div class="settings-sections">
        
        <!-- General Settings -->
        <div class="settings-section">
            <div class="section-header">
                <h3><i class="fas fa-cog"></i> Genel Ayarlar</h3>
                <p>Takımın temel bilgilerini düzenleyin</p>
            </div>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="settings_action" value="update_general">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="team_name" class="form-label">Takım Adı:</label>
                        <input type="text" class="form-control" id="team_name" name="team_name" 
                               value="<?= htmlspecialchars($team_data['name']) ?>" 
                               maxlength="100" required>
                        <small class="form-text">Takımınızın görünen adı</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="team_tag" class="form-label">Takım Etiketi:</label>
                        <select class="form-control" id="team_tag" name="team_tag">
                            <option value="">Etiket Seçin</option>
                            <?php
                            $tags = ['PvP', 'PvE', 'Exploration', 'Trading', 'Mining', 'Racing', 'Casual', 'Hardcore', 'Military', 'Pirate', 'Mercenary', 'Industrial', 'Rescue'];
                            foreach ($tags as $tag): 
                            ?>
                                <option value="<?= $tag ?>" <?= $team_data['tag'] === $tag ? 'selected' : '' ?>>
                                    <?= $tag ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Takımınızın oyun tarzını belirtin</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="team_color" class="form-label">Takım Rengi:</label>
                        <input type="color" class="form-control color-input" id="team_color" name="team_color" 
                               value="<?= htmlspecialchars($team_data['color']) ?>">
                        <small class="form-text">Takımın temsil rengi</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_members" class="form-label">Maksimum Üye Sayısı:</label>
                        <input type="number" class="form-control" id="max_members" name="max_members" 
                               value="<?= $team_data['max_members'] ?>" 
                               min="<?= $team_stats['total_members'] ?>" max="500" required>
                        <small class="form-text">Mevcut üye sayısından az olamaz (<?= $team_stats['total_members'] ?>)</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="team_description" class="form-label">Takım Açıklaması:</label>
                    <textarea class="form-control" id="team_description" name="team_description" 
                              rows="4" maxlength="1000"><?= htmlspecialchars($team_data['description']) ?></textarea>
                    <small class="form-text">Takımınızı tanıtan açıklama (1000 karakter max)</small>
                </div>
                
                <div class="form-check-section">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_recruitment_open" 
                               name="is_recruitment_open" <?= $team_data['is_recruitment_open'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_recruitment_open">
                            <i class="fas fa-unlock"></i> Başvurular Açık
                            <small>Yeni üyelerin takıma başvurabilmesini sağlar</small>
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Genel Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>

        <!-- Team Status -->
        <div class="settings-section">
            <div class="section-header">
                <h3><i class="fas fa-flag"></i> Takım Durumu</h3>
                <p>Takımın genel durumunu belirleyin</p>
            </div>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="settings_action" value="update_status">
                
                <div class="status-options">
                    <div class="status-option">
                        <input type="radio" id="status_active" name="team_status" value="active" 
                               <?= $team_data['status'] === 'active' ? 'checked' : '' ?>>
                        <label for="status_active" class="status-label active">
                            <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="status-info">
                                <strong>Aktif</strong>
                                <small>Takım normale çalışıyor ve yeni üyeler kabul ediyor</small>
                            </div>
                        </label>
                    </div>
                    
                    <div class="status-option">
                        <input type="radio" id="status_inactive" name="team_status" value="inactive" 
                               <?= $team_data['status'] === 'inactive' ? 'checked' : '' ?>>
                        <label for="status_inactive" class="status-label inactive">
                            <div class="status-icon"><i class="fas fa-pause-circle"></i></div>
                            <div class="status-info">
                                <strong>İnaktif</strong>
                                <small>Takım geçici olarak pasif durumda</small>
                            </div>
                        </label>
                    </div>
                    
                    <div class="status-option">
                        <input type="radio" id="status_disbanded" name="team_status" value="disbanded" 
                               <?= $team_data['status'] === 'disbanded' ? 'checked' : '' ?>>
                        <label for="status_disbanded" class="status-label disbanded">
                            <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                            <div class="status-info">
                                <strong>Dağıtılmış</strong>
                                <small>Takım kapatılmış durumda (geri döndürülebilir)</small>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-warning" 
                            onclick="return confirm('Takım durumunu değiştirmek istediğinizden emin misiniz?')">
                        <i class="fas fa-flag"></i> Durumu Güncelle
                    </button>
                </div>
            </form>
        </div>

        <!-- Leadership Transfer -->
        <div class="settings-section danger-section">
            <div class="section-header">
                <h3><i class="fas fa-crown"></i> Liderlik Devri</h3>
                <p>Takım liderliğini başka bir üyeye devredin</p>
            </div>
            
            <?php if (!empty($potential_leaders)): ?>
                <form method="POST" class="settings-form" id="transferForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="settings_action" value="transfer_leadership">
                    
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Dikkat!</strong>
                            <p>Liderlik devrini gerçekleştirdikten sonra geri alamazsınız. Yeni lider sizi takımdan atabilir veya rolünüzü değiştirebilir.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_leader_id" class="form-label">Yeni Lider Seçin:</label>
                        <select class="form-control" id="new_leader_id" name="new_leader_id" required>
                            <option value="">Lider seçin...</option>
                            <?php foreach ($potential_leaders as $member): ?>
                                <option value="<?= $member['user_id'] ?>">
                                    <?= htmlspecialchars($member['username']) ?> (<?= htmlspecialchars($member['role_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Sadece aktif takım üyeleri seçilebilir</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger" 
                                onclick="return confirm('Liderliği devretmek istediğinizden kesinlikle emin misiniz?\n\nBu işlem GERİ ALINAMAZ!')">
                            <i class="fas fa-crown"></i> Liderliği Devret
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Liderlik devri için takımda en az bir aktif üye bulunması gerekir.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Danger Zone -->
        <div class="settings-section danger-section">
            <div class="section-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Tehlikeli İşlemler</h3>
                <p>Bu işlemler geri alınamaz. Çok dikkatli olun!</p>
            </div>
            
            <div class="danger-actions">
                <div class="danger-action">
                    <div class="danger-info">
                        <h4>Takımı Sil</h4>
                        <p>Takımı ve tüm verilerini kalıcı olarak siler. Bu işlem geri alınamaz!</p>
                        <ul>
                            <li>Tüm takım verileri silinir</li>
                            <li>Roller ve yetkiler kaldırılır</li>
                            <li>Başvuru geçmişi silinir</li>
                            <li>Bu işlem geri alınamaz</li>
                        </ul>
                    </div>
                    
                    <div class="danger-button">
                        <?php if ($team_stats['total_members'] <= 1): ?>
                            <button type="button" class="btn btn-danger" onclick="showDeleteConfirmation()">
                                <i class="fas fa-trash"></i> Takımı Sil
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger" disabled title="Takımda başka üyeler bulunduğu sürece silinemez">
                                <i class="fas fa-lock"></i> Takım Silinemez
                            </button>
                            <small class="text-muted">Önce tüm üyeleri takımdan çıkarmalısınız</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content danger-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-trash"></i> Takımı Kalıcı Olarak Sil
                </h5>
                <button type="button" class="btn-close" onclick="closeDeleteModal()"></button>
            </div>
            <div class="modal-body">
                <div class="danger-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Bu işlem geri alınamaz!</h4>
                </div>
                
                <p>Aşağıdaki işlemler gerçekleşecek:</p>
                <ul>
                    <li>Takım "<strong><?= htmlspecialchars($team_data['name']) ?></strong>" kalıcı olarak silinecek</li>
                    <li>Tüm roller ve yetkiler kaldırılacak</li>
                    <li>Başvuru geçmişi silinecek</li>
                    <li>Bu veriler bir daha geri getirilemeyecek</li>
                </ul>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="settings_action" value="delete_team">
                    
                    <div class="form-group">
                        <label for="confirmText" class="form-label">
                            Onaylamak için takım adını yazın: <strong><?= htmlspecialchars($team_data['name']) ?></strong>
                        </label>
                        <input type="text" class="form-control" id="confirmText" 
                               placeholder="Takım adını buraya yazın..." required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> İptal
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Takımı Kalıcı Olarak Sil
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Team name confirmation for delete
    const confirmText = document.getElementById('confirmText');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const teamName = '<?= addslashes($team_data['name']) ?>';
    
    if (confirmText && confirmBtn) {
        confirmText.addEventListener('input', function() {
            confirmBtn.disabled = this.value !== teamName;
        });
    }
    
    // Color preview update
    const colorInput = document.getElementById('team_color');
    if (colorInput) {
        colorInput.addEventListener('input', function() {
            // Update team name color preview
            const teamNameInput = document.getElementById('team_name');
            if (teamNameInput) {
                teamNameInput.style.color = this.value;
            }
        });
    }
    
    // Max members validation
    const maxMembersInput = document.getElementById('max_members');
    if (maxMembersInput) {
        maxMembersInput.addEventListener('input', function() {
            const currentMembers = <?= $team_stats['total_members'] ?>;
            if (parseInt(this.value) < currentMembers) {
                this.setCustomValidity(`Maksimum üye sayısı mevcut üye sayısından (${currentMembers}) az olamaz.`);
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

// Delete modal functions
function showDeleteConfirmation() {
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('confirmText').value = '';
        document.getElementById('confirmDeleteBtn').disabled = true;
    }, 300);
}

function confirmDelete() {
    if (confirm('Son uyarı: Bu işlem GERİ ALINAMAZ. Takımı silmek istediğinizden kesinlikle emin misiniz?')) {
        document.getElementById('deleteForm').submit();
    }
}

// Modal event handlers
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>