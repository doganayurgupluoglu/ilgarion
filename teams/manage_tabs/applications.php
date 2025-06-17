<?php
// /teams/manage_tabs/applications.php
// Bu dosya teams/manage.php içinde include edilir

if (!defined('BASE_PATH') || !isset($team_data)) {
    die('Bu dosya doğrudan erişilemez.');
}
?>

<div class="applications-content">
    <!-- Applications Header -->
    <div class="applications-header">
        <div class="header-info">
            <h2>Başvuru Yönetimi</h2>
            <p>
                <?= count($pending_applications) ?> bekleyen başvuru
                <?php if (isset($recent_applications)): ?>
                    - Son işlenen: <?= count($recent_applications) ?>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="header-actions">
            <div class="capacity-info">
                <span class="capacity-text">
                    Kapasite: <?= $team_stats['total_members'] ?>/<?= $team_data['max_members'] ?>
                </span>
                <?php if ($team_stats['total_members'] >= $team_data['max_members']): ?>
                    <span class="capacity-warning">
                        <i class="fas fa-exclamation-triangle"></i> Takım Dolu
                    </span>
                <?php elseif ($team_stats['total_members'] >= $team_data['max_members'] * 0.9): ?>
                    <span class="capacity-warning">
                        <i class="fas fa-exclamation-circle"></i> Neredeyse Dolu
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="recruitment-status">
                <?php if ($team_data['is_recruitment_open']): ?>
                    <span class="status-badge open">
                        <i class="fas fa-unlock"></i> Başvurular Açık
                    </span>
                <?php else: ?>
                    <span class="status-badge closed">
                        <i class="fas fa-lock"></i> Başvurular Kapalı
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Applications Section -->
    <?php if (!empty($pending_applications)): ?>
        <div class="applications-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-clock"></i> 
                    Bekleyen Başvurular (<?= count($pending_applications) ?>)
                </h3>
                <div class="section-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()">
                        <i class="fas fa-check-square"></i> Tümünü Seç
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()" disabled>
                        <i class="fas fa-check"></i> Seçilenleri Onayla
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkReject()" disabled>
                        <i class="fas fa-times"></i> Seçilenleri Reddet
                    </button>
                </div>
            </div>
            
            <div class="applications-list">
                <?php foreach ($pending_applications as $application): ?>
                    <div class="application-card" data-application-id="<?= $application['id'] ?>">
                        <div class="application-header">
                            <div class="application-select">
                                <input type="checkbox" class="application-checkbox" value="<?= $application['id'] ?>">
                            </div>
                            
                            <div class="applicant-info">
                                <!-- Avatar -->
                                <div class="applicant-avatar">
                                    <?php if ($application['avatar_path']): ?>
                                        <img src="/uploads/avatars/<?= htmlspecialchars($application['avatar_path']) ?>" 
                                             alt="<?= htmlspecialchars($application['username']) ?>" 
                                             class="avatar-img">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($application['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Basic Info -->
                                <div class="applicant-details">
                                    <h4 class="applicant-name">
                                        <a href="#" class="user-link" data-user-id="<?= $application['user_id'] ?>">
                                            <?= htmlspecialchars($application['username']) ?>
                                        </a>
                                    </h4>
                                    
                                    <?php if ($application['ingame_name']): ?>
                                        <div class="applicant-ingame">
                                            <i class="fas fa-gamepad"></i>
                                            <?= htmlspecialchars($application['ingame_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="application-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d.m.Y H:i', strtotime($application['applied_at'])) ?>
                                        
                                        <span class="time-ago">
                                            <?php
                                            $hours_ago = (new DateTime())->diff(new DateTime($application['applied_at']))->h;
                                            $days_ago = (new DateTime())->diff(new DateTime($application['applied_at']))->days;
                                            
                                            if ($days_ago == 0) {
                                                if ($hours_ago == 0) {
                                                    echo 'Az önce';
                                                } else {
                                                    echo $hours_ago . ' saat önce';
                                                }
                                            } elseif ($days_ago == 1) {
                                                echo 'Dün';
                                            } else {
                                                echo $days_ago . ' gün önce';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <!-- User Join Date -->
                                    <div class="user-join-date">
                                        <i class="fas fa-user-plus"></i>
                                        Siteye üye: <?= date('d.m.Y', strtotime($application['user_created_at'])) ?>
                                        
                                        <?php
                                        $membership_days = (new DateTime())->diff(new DateTime($application['user_created_at']))->days;
                                        if ($membership_days < 30) {
                                            echo '<span class="new-user-badge">Yeni Üye</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="quick-actions">
                                <form method="POST" style="display: inline;" class="approve-form">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="approve_application">
                                    <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                                    
                                    <button type="submit" class="btn btn-success btn-sm" 
                                            data-action="approve"
                                            data-applicant-name="<?= htmlspecialchars($application['username']) ?>"
                                            title="Başvuruyu onayla"
                                            <?= ($team_stats['total_members'] >= $team_data['max_members']) ? 'disabled' : '' ?>>
                                        <i class="fas fa-check"></i> Onayla
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="showRejectModal(<?= $application['id'] ?>, '<?= htmlspecialchars($application['username']) ?>')"
                                        title="Başvuruyu reddet">
                                    <i class="fas fa-times"></i> Reddet
                                </button>
                                
                                <a href="/view_profile.php?user_id=<?= $application['user_id'] ?>" 
                                   class="btn btn-outline-secondary btn-sm" 
                                   title="Profili görüntüle" target="_blank">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Application Message -->
                        <?php if ($application['message']): ?>
                            <div class="application-message">
                                <div class="message-header">
                                    <i class="fas fa-comment"></i> Başvuru Mesajı:
                                </div>
                                <div class="message-content">
                                    <?= nl2br(htmlspecialchars($application['message'])) ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="application-message empty">
                                <i class="fas fa-comment-slash"></i> Başvuru mesajı bırakılmamış.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="applications-section">
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>Bekleyen başvuru bulunmuyor</h3>
                <p>
                    <?php if ($team_data['is_recruitment_open']): ?>
                        Yeni başvurular geldiğinde burada görünecekler.
                    <?php else: ?>
                        Başvurular şu anda kapalı. Başvuru almak için takım ayarlarından başvuruları açabilirsiniz.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Applications Section -->
    <?php if (!empty($recent_applications)): ?>
        <div class="applications-section recent-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-history"></i> 
                    Son İşlenen Başvurular (<?= count($recent_applications) ?>)
                </h3>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleRecentSection()">
                    <i class="fas fa-eye"></i> <span id="recentToggleText">Göster</span>
                </button>
            </div>
            
            <div class="recent-applications" id="recentApplications" style="display: none;">
                <?php foreach ($recent_applications as $application): ?>
                    <div class="recent-application-card <?= $application['status'] ?>">
                        <div class="recent-header">
                            <div class="applicant-basic">
                                <span class="applicant-name"><?= htmlspecialchars($application['username']) ?></span>
                                <span class="application-status status-<?= $application['status'] ?>">
                                    <?php if ($application['status'] === 'approved'): ?>
                                        <i class="fas fa-check-circle"></i> Onaylandı
                                    <?php else: ?>
                                        <i class="fas fa-times-circle"></i> Reddedildi
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="recent-meta">
                                <span class="review-date">
                                    <?= date('d.m.Y H:i', strtotime($application['reviewed_at'])) ?>
                                </span>
                                <?php if ($application['reviewer_username']): ?>
                                    <span class="reviewer">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($application['reviewer_username']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($application['admin_notes']): ?>
                            <div class="admin-notes">
                                <i class="fas fa-sticky-note"></i>
                                <?= nl2br(htmlspecialchars($application['admin_notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="rejectForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle"></i> Başvuruyu Reddet
                    </h5>
                    <button type="button" class="btn-close" onclick="closeRejectModal()"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="reject_application">
                    <input type="hidden" name="application_id" id="rejectApplicationId">
                    
                    <p><strong id="rejectApplicantName"></strong> adlı kullanıcının başvurusunu reddetmek istediğinizden emin misiniz?</p>
                    
                    <div class="form-group">
                        <label for="admin_notes" class="form-label">Red Nedeni (İsteğe Bağlı):</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                  placeholder="Başvurunun neden reddedildiğini belirtebilirsiniz..."></textarea>
                        <small class="form-text text-muted">
                            Bu not sadece yöneticiler tarafından görülür.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Başvuruyu Reddet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Application management functionality
let selectedApplications = new Set();

document.addEventListener('DOMContentLoaded', function() {
    // Checkbox handling
    document.querySelectorAll('.application-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedApplications.add(this.value);
            } else {
                selectedApplications.delete(this.value);
            }
            updateBulkButtons();
        });
    });
    
    // Approve button confirmations
    document.querySelectorAll('[data-action="approve"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const applicantName = this.dataset.applicantName;
            if (!confirm(`${applicantName} adlı kullanıcının başvurusunu onaylamak istediğinizden emin misiniz?\n\nOnaylanan kullanıcı otomatik olarak takıma eklenecektir.`)) {
                e.preventDefault();
            }
        });
    });
});

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.application-checkbox');
    const allSelected = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allSelected;
        if (checkbox.checked) {
            selectedApplications.add(checkbox.value);
        } else {
            selectedApplications.delete(checkbox.value);
        }
    });
    
    updateBulkButtons();
}

function updateBulkButtons() {
    const bulkApprove = document.querySelector('button[onclick="bulkApprove()"]');
    const bulkReject = document.querySelector('button[onclick="bulkReject()"]');
    const hasSelection = selectedApplications.size > 0;
    
    if (bulkApprove) bulkApprove.disabled = !hasSelection;
    if (bulkReject) bulkReject.disabled = !hasSelection;
}

function bulkApprove() {
    if (selectedApplications.size === 0) return;
    
    const count = selectedApplications.size;
    if (!confirm(`Seçilen ${count} başvuruyu onaylamak istediğinizden emin misiniz?\n\nOnaylanan kullanıcılar otomatik olarak takıma eklenecektir.`)) {
        return;
    }
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="bulk_approve">
        <input type="hidden" name="application_ids" value="${Array.from(selectedApplications).join(',')}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function bulkReject() {
    if (selectedApplications.size === 0) return;
    
    const count = selectedApplications.size;
    if (!confirm(`Seçilen ${count} başvuruyu reddetmek istediğinizden emin misiniz?`)) {
        return;
    }
    
    const notes = prompt('Red nedeni (isteğe bağlı):');
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="bulk_reject">
        <input type="hidden" name="application_ids" value="${Array.from(selectedApplications).join(',')}">
        <input type="hidden" name="admin_notes" value="${notes || ''}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Reject modal functions
function showRejectModal(applicationId, applicantName) {
    document.getElementById('rejectApplicationId').value = applicationId;
    document.getElementById('rejectApplicantName').textContent = applicantName;
    document.getElementById('admin_notes').value = '';
    
    const modal = document.getElementById('rejectModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// Recent applications toggle
function toggleRecentSection() {
    const section = document.getElementById('recentApplications');
    const toggleText = document.getElementById('recentToggleText');
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
        toggleText.textContent = 'Gizle';
    } else {
        section.style.display = 'none';
        toggleText.textContent = 'Göster';
    }
}

// Close modal on overlay click
document.getElementById('rejectModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});
</script>