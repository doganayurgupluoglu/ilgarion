<?php
// /teams/manage_tabs/applications.php
// Bu dosya takım yönetimi sayfasının applications tab'ında include edilir

// Bu dosya çalıştırıldığında gerekli değişkenler manage.php'den gelir:
// $team_data, $team_id, $is_team_owner, $current_user_id, $pdo, $is_team_manager

// Yetki kontrolü - başvuruları görme/yönetme yetkisi
$can_manage_applications = $is_team_owner || $is_team_manager || 
    has_team_permission($pdo, $team_id, $current_user_id, 'team.manage.applications') ||
    has_permission($pdo, 'teams.manage_applications', $current_user_id);

$can_view_applications = $can_manage_applications || 
    has_team_permission($pdo, $team_id, $current_user_id, 'team.view.applications');

if (!$can_view_applications) {
    echo '<div class="manage-card">';
    echo '<div class="card-body">';
    echo '<div class="alert alert-danger">';
    echo '<i class="fas fa-exclamation-triangle"></i> Başvuruları görüntüleme yetkiniz yok.';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    return;
}

// Başvuru işlemleri (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_applications) {
    // CSRF koruması
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        $application_id = isset($_POST['application_id']) && is_numeric($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        $notes = $_POST['notes'] ?? '';
        
        if ($application_id && in_array($action, ['approve', 'reject'])) {
            // Başvurunun bu takıma ait olduğunu doğrula
            $stmt = $pdo->prepare("
                SELECT ta.*, u.username, u.ingame_name 
                FROM team_applications ta
                JOIN users u ON ta.user_id = u.id
                WHERE ta.id = ? AND ta.team_id = ? AND ta.status = 'pending'
            ");
            $stmt->execute([$application_id, $team_id]);
            $application = $stmt->fetch();
            
            if ($application) {
                // team_functions.php fonksiyonunu kullan
                $review_action = $action === 'approve' ? 'approved' : 'rejected';
                if (review_team_application($pdo, $application_id, $review_action, $current_user_id, $notes)) {
                    $action_text = $action === 'approve' ? 'onaylandı' : 'reddedildi';
                    $success_message = $application['username'] . ' kullanıcısının başvurusu ' . $action_text . '.';
                    
                    // Başarılı işlemden sonra sayfayı yeniden yükle
                    header('Location: ?id=' . $team_id . '&tab=applications&success=' . urlencode($success_message));
                    exit;
                } else {
                    $error_message = 'Başvuru işlenirken bir hata oluştu.';
                }
            } else {
                $error_message = 'Başvuru bulunamadı veya işlenebilir durumda değil.';
            }
        }
        
        // Toplu işlemler
        if ($action === 'bulk_action' && isset($_POST['selected_applications']) && is_array($_POST['selected_applications'])) {
            $bulk_action = $_POST['bulk_action_type'] ?? '';
            $selected_apps = array_filter($_POST['selected_applications'], 'is_numeric');
            $success_count = 0;
            
            if ($bulk_action && count($selected_apps) > 0) {
                foreach ($selected_apps as $app_id) {
                    // Her başvurunun bu takıma ait olduğunu doğrula
                    $stmt = $pdo->prepare("
                        SELECT id FROM team_applications 
                        WHERE id = ? AND team_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$app_id, $team_id]);
                    
                    if ($stmt->fetch()) {
                        if (review_team_application($pdo, $app_id, $bulk_action === 'approve_all' ? 'approved' : 'rejected', $current_user_id, 'Toplu işlem')) {
                            $success_count++;
                        }
                    }
                }
                
                if ($success_count > 0) {
                    $action_text = $bulk_action === 'approve_all' ? 'onaylandı' : 'reddedildi';
                    $success_message = $success_count . ' başvuru ' . $action_text . '.';
                    header('Location: ?id=' . $team_id . '&tab=applications&success=' . urlencode($success_message));
                    exit;
                } else {
                    $error_message = 'Hiçbir başvuru işlenemedi.';
                }
            }
        }
    }
}

// Filtreleme parametreleri - manage.php'de tanımlanmamış olsa bile burada tanımla
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Başvuruları getir - manage.php'deki veriyi geçersiz kıl çünkü filtreleme gerekiyor
$query = "
    SELECT ta.*, u.username, u.ingame_name, u.avatar_path, u.email,
           reviewer.username as reviewer_username
    FROM team_applications ta
    JOIN users u ON ta.user_id = u.id
    LEFT JOIN users reviewer ON ta.reviewed_by_user_id = reviewer.id
    WHERE ta.team_id = ?
";

$params = [$team_id];

// Durum filtresi
if ($status_filter !== 'all') {
    $query .= " AND ta.status = ?";
    $params[] = $status_filter;
}

// Arama filtresi
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.ingame_name LIKE ? OR ta.message LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY ta.applied_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$team_applications = $stmt->fetchAll();

// Başvuru istatistikleri
$application_stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total' => count($team_applications)
];

foreach ($team_applications as $app) {
    if (isset($application_stats[$app['status']])) {
        $application_stats[$app['status']]++;
    }
}

// Son 30 gün içindeki başvuru trendi
$stmt = $pdo->prepare("
    SELECT DATE(applied_at) as date, COUNT(*) as count
    FROM team_applications 
    WHERE team_id = ? AND applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(applied_at)
    ORDER BY date DESC
    LIMIT 7
");
$stmt->execute([$team_id]);
$recent_applications = $stmt->fetchAll();

// Durum renklerini tanımla
$status_colors = [
    'pending' => 'warning',
    'approved' => 'success', 
    'rejected' => 'danger',
    'withdrawn' => 'secondary'
];

$status_labels = [
    'pending' => 'Beklemede',
    'approved' => 'Onaylandı',
    'rejected' => 'Reddedildi', 
    'withdrawn' => 'Geri Çekildi'
];
?>

<!-- Applications Tab Content -->
<div class="applications-tab">
    <!-- Başvuru İstatistikleri -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Başvuru İstatistikleri</h3>
        </div>
        <div class="card-body">
            <div class="application-stats-grid">
                <div class="stat-item pending">
                    <div class="stat-value"><?= $application_stats['pending'] ?></div>
                    <div class="stat-label">Bekleyen</div>
                </div>
                <div class="stat-item approved">
                    <div class="stat-value"><?= $application_stats['approved'] ?></div>
                    <div class="stat-label">Onaylanan</div>
                </div>
                <div class="stat-item rejected">
                    <div class="stat-value"><?= $application_stats['rejected'] ?></div>
                    <div class="stat-label">Reddedilen</div>
                </div>
                <div class="stat-item total">
                    <div class="stat-value"><?= $application_stats['total'] ?></div>
                    <div class="stat-label">Toplam</div>
                </div>
            </div>
            
            <?php if (count($recent_applications) > 0): ?>
                <div class="recent-trend">
                    <h5><i class="fas fa-trending-up"></i> Son Hafta</h5>
                    <div class="trend-items">
                        <?php foreach ($recent_applications as $day): ?>
                            <div class="trend-item">
                                <span class="date"><?= date('d.m', strtotime($day['date'])) ?></span>
                                <span class="count"><?= $day['count'] ?> başvuru</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Arama ve Filtreler -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtreler</h3>
        </div>
        <div class="card-body">
            <form method="get" class="application-filters">
                <input type="hidden" name="id" value="<?= $team_id ?>">
                <input type="hidden" name="tab" value="applications">
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="status">Durum:</label>
                        <select name="status" id="status" class="form-select">
                            <option value="all" <?= ($status_filter === 'all') ? 'selected' : '' ?>>Tümü</option>
                            <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : '' ?>>Beklemede</option>
                            <option value="approved" <?= ($status_filter === 'approved') ? 'selected' : '' ?>>Onaylandı</option>
                            <option value="rejected" <?= ($status_filter === 'rejected') ? 'selected' : '' ?>>Reddedildi</option>
                            <option value="withdrawn" <?= ($status_filter === 'withdrawn') ? 'selected' : '' ?>>Geri Çekildi</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Arama:</label>
                        <input type="text" 
                               name="search" 
                               id="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Kullanıcı adı, oyun içi isim veya mesaj..." 
                               class="form-control">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrele
                        </button>
                        <a href="?id=<?= $team_id ?>&tab=applications" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Temizle
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Başvuru Listesi -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> Başvurular (<?= count($team_applications) ?>)</h3>
            
            <?php if ($can_manage_applications && count(array_filter($team_applications, fn($app) => $app['status'] === 'pending')) > 1): ?>
                <div class="bulk-actions">
                    <form method="post" id="bulkActionForm" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="bulk_action">
                        <select name="bulk_action_type" required>
                            <option value="">Toplu İşlem Seçin</option>
                            <option value="approve_all">Hepsini Onayla</option>
                            <option value="reject_all">Hepsini Reddet</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Uygula</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="cancelBulkAction()">İptal</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBulkAction()">
                        <i class="fas fa-tasks"></i> Toplu İşlem
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (count($team_applications) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>Başvuru Bulunamadı</h4>
                    <p>Seçilen kriterlere uygun başvuru bulunmuyor.</p>
                    <?php if (!empty($search) || $status_filter !== 'pending'): ?>
                        <a href="?id=<?= $team_id ?>&tab=applications" class="btn btn-outline-primary">
                            <i class="fas fa-refresh"></i> Tüm Başvuruları Göster
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="applications-list">
                    <?php foreach ($team_applications as $application): ?>
                        <div class="application-card" data-status="<?= $application['status'] ?>">
                            <div class="application-header">
                                <div class="applicant-info">
                                    <?php if ($can_manage_applications && $application['status'] === 'pending'): ?>
                                        <input type="checkbox" 
                                               name="selected_applications[]" 
                                               value="<?= $application['id'] ?>"
                                               class="bulk-checkbox" 
                                               style="display: none;">
                                    <?php endif; ?>
                                    
                                    <div class="applicant-avatar">
                                        <?php if ($application['avatar_path']): ?>
                                            <img src="/<?= htmlspecialchars($application['avatar_path']) ?>" 
                                                 alt="<?= htmlspecialchars($application['username']) ?>">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <?= strtoupper(substr($application['username'], 0, 2)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="applicant-details">
                                        <div class="applicant-names">
                                            <h5 class="applicant-username"><?= htmlspecialchars($application['username']) ?></h5>
                                            <?php if ($application['ingame_name']): ?>
                                                <span class="applicant-ingame"><?= htmlspecialchars($application['ingame_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="application-meta">
                                            <span class="application-date">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('d.m.Y H:i', strtotime($application['applied_at'])) ?>
                                            </span>
                                            
                                            <span class="application-status status-<?= $application['status'] ?>">
                                                <?= $status_labels[$application['status']] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($can_manage_applications && $application['status'] === 'pending'): ?>
                                    <div class="application-actions">
                                        <button type="button" 
                                                class="btn btn-sm btn-success" 
                                                onclick="showReviewModal(<?= $application['id'] ?>, 'approve', '<?= htmlspecialchars($application['username']) ?>')">
                                            <i class="fas fa-check"></i> Onayla
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                onclick="showReviewModal(<?= $application['id'] ?>, 'reject', '<?= htmlspecialchars($application['username']) ?>')">
                                            <i class="fas fa-times"></i> Reddet
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($application['message']): ?>
                                <div class="application-message">
                                    <h6><i class="fas fa-comment"></i> Başvuru Mesajı:</h6>
                                    <p><?= nl2br(htmlspecialchars($application['message'])) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($application['status'] !== 'pending'): ?>
                                <div class="application-review">
                                    <div class="review-info">
                                        <span class="reviewed-by">
                                            <i class="fas fa-user-check"></i>
                                            <?= htmlspecialchars($application['reviewer_username'] ?? 'Sistem') ?> tarafından değerlendirildi
                                        </span>
                                        <span class="reviewed-date">
                                            <?= date('d.m.Y H:i', strtotime($application['reviewed_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($application['admin_notes']): ?>
                                        <div class="admin-notes">
                                            <strong>Yönetici Notu:</strong>
                                            <p><?= nl2br(htmlspecialchars($application['admin_notes'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Başvuru Değerlendirme Modal -->
<?php if ($can_manage_applications): ?>
<div id="reviewModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="hideReviewModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="reviewModalTitle">Başvuruyu Değerlendir</h3>
            <button type="button" class="modal-close" onclick="hideReviewModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="post" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="reviewAction">
            <input type="hidden" name="application_id" id="reviewApplicationId">
            
            <div class="review-summary">
                <p id="reviewSummaryText"></p>
            </div>
            
            <div class="form-group">
                <label for="notes">Yönetici Notu (İsteğe Bağlı):</label>
                <textarea name="notes" 
                          id="notes" 
                          class="form-control" 
                          rows="3" 
                          placeholder="Başvuru ile ilgili notlarınızı buraya yazabilirsiniz..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary" id="reviewSubmitBtn">
                    <i class="fas fa-check"></i> Onayla
                </button>
                <button type="button" class="btn btn-secondary" onclick="hideReviewModal()">
                    İptal
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Başvuru değerlendirme modal işlevleri
function showReviewModal(applicationId, action, username) {
    const modal = document.getElementById('reviewModal');
    const title = document.getElementById('reviewModalTitle');
    const summaryText = document.getElementById('reviewSummaryText');
    const actionInput = document.getElementById('reviewAction');
    const applicationIdInput = document.getElementById('reviewApplicationId');
    const submitBtn = document.getElementById('reviewSubmitBtn');
    const notesTextarea = document.getElementById('notes');
    
    // Modal verilerini ayarla
    actionInput.value = action;
    applicationIdInput.value = applicationId;
    notesTextarea.value = '';
    
    if (action === 'approve') {
        title.textContent = 'Başvuruyu Onayla';
        summaryText.innerHTML = `<strong>${username}</strong> kullanıcısının başvurusunu onaylamak istediğinizden emin misiniz?`;
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Onayla';
    } else {
        title.textContent = 'Başvuruyu Reddet';
        summaryText.innerHTML = `<strong>${username}</strong> kullanıcısının başvurusunu reddetmek istediğinizden emin misiniz?`;
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Reddet';
    }
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideReviewModal() {
    const modal = document.getElementById('reviewModal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// Toplu işlem fonksiyonları
let bulkActionMode = false;

function toggleBulkAction() {
    bulkActionMode = !bulkActionMode;
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    const bulkForm = document.getElementById('bulkActionForm');
    const toggleBtn = document.querySelector('[onclick="toggleBulkAction()"]');
    
    if (bulkActionMode) {
        checkboxes.forEach(cb => cb.style.display = 'inline-block');
        bulkForm.style.display = 'flex';
        toggleBtn.innerHTML = '<i class="fas fa-times"></i> İptal';
        toggleBtn.className = 'btn btn-sm btn-outline-danger';
    } else {
        cancelBulkAction();
    }
}

function cancelBulkAction() {
    bulkActionMode = false;
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    const bulkForm = document.getElementById('bulkActionForm');
    const toggleBtn = document.querySelector('[onclick="toggleBulkAction()"]');
    
    checkboxes.forEach(cb => {
        cb.style.display = 'none';
        cb.checked = false;
    });
    bulkForm.style.display = 'none';
    toggleBtn.innerHTML = '<i class="fas fa-tasks"></i> Toplu İşlem';
    toggleBtn.className = 'btn btn-sm btn-outline-primary';
}

// Form submit işlemi için checkbox kontrolü
document.getElementById('bulkActionForm')?.addEventListener('submit', function(e) {
    const checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    if (checkedBoxes.length === 0) {
        e.preventDefault();
        alert('Lütfen en az bir başvuru seçin.');
        return false;
    }
    
    const actionType = this.querySelector('[name="bulk_action_type"]').value;
    const actionText = actionType === 'approve_all' ? 'onaylamak' : 'reddetmek';
    
    if (!confirm(`${checkedBoxes.length} başvuruyu ${actionText} istediğinizden emin misiniz?`)) {
        e.preventDefault();
        return false;
    }
    
    // Seçili checkbox'ları forma ekle
    checkedBoxes.forEach(cb => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'selected_applications[]';
        hiddenInput.value = cb.value;
        this.appendChild(hiddenInput);
    });
});

// ESC tuşu ile modal kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideReviewModal();
    }
});
</script>