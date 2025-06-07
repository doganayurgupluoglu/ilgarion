<?php
// /events/loadouts/index.php - Teçhizat Setleri Listeleme Sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// Pagination ayarları
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Filtreleme parametreleri
$visibility_filter = $_GET['visibility'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');

// Base query
$where_conditions = [];
$query_params = [];

// Visibility filter
if ($visibility_filter !== 'all') {
    if ($visibility_filter === 'public') {
        $where_conditions[] = "ls.visibility = 'public'";
    } elseif ($visibility_filter === 'members_only') {
        $where_conditions[] = "ls.visibility = 'members_only'";
    } elseif ($visibility_filter === 'my_sets' && is_user_logged_in()) {
        $where_conditions[] = "ls.user_id = :current_user_id";
        $query_params[':current_user_id'] = $_SESSION['user_id'];
    }
}

// Visibility kontrolü - kullanıcı durumuna göre
if (!is_user_logged_in()) {
    // Giriş yapmamış kullanıcılar sadece public görebilir
    $where_conditions[] = "ls.visibility = 'public'";
} elseif (!is_user_approved()) {
    // Onaylanmamış kullanıcılar sadece public görebilir
    $where_conditions[] = "ls.visibility = 'public'";
} else {
    // Onaylı kullanıcılar public ve members_only görebilir
    if ($visibility_filter === 'all') {
        $where_conditions[] = "ls.visibility IN ('public', 'members_only')";
    }
}

// Search filter
if (!empty($search_query)) {
    $where_conditions[] = "(ls.set_name LIKE :search OR ls.set_description LIKE :search OR u.username LIKE :search)";
    $query_params[':search'] = '%' . $search_query . '%';
}

// Status filter - sadece published olanları göster
$where_conditions[] = "ls.status = 'published'";

// WHERE clause oluştur
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Ana query - loadout setleri
    $query = "
        SELECT 
            ls.*,
            u.username,
            u.avatar_path,
            COUNT(DISTINCT lsi.id) as item_count,
            COUNT(DISTINCT lwa.id) as attachment_count
        FROM loadout_sets ls
        LEFT JOIN users u ON ls.user_id = u.id
        LEFT JOIN loadout_set_items lsi ON ls.id = lsi.loadout_set_id
        LEFT JOIN loadout_weapon_attachments lwa ON ls.id = lwa.loadout_set_id
        $where_clause
        GROUP BY ls.id
        ORDER BY ls.created_at DESC
        LIMIT :offset, :per_page
    ";
    
    $stmt = $pdo->prepare($query);
    
    // Parameters bind et
    foreach ($query_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $loadout_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam sayfa sayısı için count query
    $count_query = "
        SELECT COUNT(DISTINCT ls.id) as total
        FROM loadout_sets ls
        LEFT JOIN users u ON ls.user_id = u.id
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_query);
    foreach ($query_params as $key => $value) {
        if ($key !== ':offset' && $key !== ':per_page') {
            $count_stmt->bindValue($key, $value);
        }
    }
    $count_stmt->execute();
    $total_sets = $count_stmt->fetchColumn();
    $total_pages = ceil($total_sets / $per_page);
    
} catch (PDOException $e) {
    error_log("Loadouts listing error: " . $e->getMessage());
    $loadout_sets = [];
    $total_sets = 0;
    $total_pages = 0;
}

$page_title = "Teçhizat Setleri";

// Breadcrumb verileri
$breadcrumb_items = [
    ['text' => 'Ana Sayfa', 'url' => '/index.php', 'icon' => 'fas fa-home'],
    ['text' => 'Etkinlikler', 'url' => '/events/', 'icon' => 'fas fa-calendar'],
    ['text' => 'Teçhizat Setleri', 'url' => '', 'icon' => 'fas fa-user-astronaut']
];

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';

// Helper functions
function generate_breadcrumb($items) {
    $breadcrumb = '<nav class="breadcrumb-nav"><ol class="breadcrumb">';
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        $breadcrumb .= '<li class="breadcrumb-item' . ($isLast ? ' active' : '') . '">';
        if ($isLast || empty($item['url'])) {
            $breadcrumb .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
        } else {
            $breadcrumb .= '<a href="' . htmlspecialchars($item['url']) . '">';
            $breadcrumb .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
            $breadcrumb .= '</a>';
        }
        $breadcrumb .= '</li>';
    }
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 2592000) return floor($time/86400) . ' gün önce';
    return date('d.m.Y', strtotime($datetime));
}

function get_visibility_text($visibility) {
    switch ($visibility) {
        case 'public': return 'Herkese Açık';
        case 'members_only': return 'Üyelere Özel';
        case 'faction_only': return 'Fraksiyona Özel';
        case 'private': return 'Özel';
        default: return ucfirst($visibility);
    }
}

function get_visibility_icon($visibility) {
    switch ($visibility) {
        case 'public': return 'fas fa-globe';
        case 'members_only': return 'fas fa-users';
        case 'faction_only': return 'fas fa-shield-alt';
        case 'private': return 'fas fa-lock';
        default: return 'fas fa-eye';
    }
}
?>

<link rel="stylesheet" href="../../css/style.css">
<link rel="stylesheet" href="css/loadouts.css">


<div class="loadouts-page-container">
    <!-- Breadcrumb Navigation -->
    <?= generate_breadcrumb($breadcrumb_items) ?>

    <div class="loadouts-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1>
                        <i class="fas fa-user-astronaut"></i>
                        Teçhizat Setleri
                    </h1>
                    <p>Star Citizen için hazırlanmış teçhizat setlerini keşfedin ve kendi setinizi oluşturun</p>
                </div>
                <div class="header-actions">
                    <?php if (is_user_approved() && has_permission($pdo, 'loadout.manage_sets')): ?>
                        <a href="create_loadouts.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Yeni Set Oluştur
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="" class="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="search">Arama</label>
                        <input type="text" id="search" name="search" 
                               value="<?= htmlspecialchars($search_query) ?>" 
                               placeholder="Set adı, açıklama veya kullanıcı adı...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="visibility">Görünürlük</label>
                        <select id="visibility" name="visibility">
                            <option value="all" <?= $visibility_filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                            <option value="public" <?= $visibility_filter === 'public' ? 'selected' : '' ?>>Herkese Açık</option>
                            <?php if (is_user_approved()): ?>
                                <option value="members_only" <?= $visibility_filter === 'members_only' ? 'selected' : '' ?>>Üyelere Özel</option>
                                <option value="my_sets" <?= $visibility_filter === 'my_sets' ? 'selected' : '' ?>>Benim Setlerim</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i>
                            Filtrele
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <div class="results-info">
            <span>
                <strong><?= number_format($total_sets) ?></strong> teçhizat seti bulundu
                <?php if (!empty($search_query)): ?>
                    - "<strong><?= htmlspecialchars($search_query) ?></strong>" araması için
                <?php endif; ?>
            </span>
            <span>
                Sayfa <?= $page ?> / <?= max(1, $total_pages) ?>
            </span>
        </div>

        <!-- Loadouts Table -->
        <?php if (!empty($loadout_sets)): ?>
            <div class="loadouts-table-container">
                <table class="loadouts-table">
                    <thead>
                        <tr>
                            <th>Görsel</th>
                            <th>Set Bilgileri</th>
                            <th>Oluşturan</th>
                            <th>İstatistikler</th>
                            <th>Görünürlük</th>
                            <th>Oluşturma</th>
                            <?php if (is_user_logged_in()): ?>
                                <th>İşlemler</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loadout_sets as $set): ?>
                            <tr>
                                <!-- Görsel -->
                                <td>
                                    <div class="set-image">
                                        <?php if (!empty($set['set_image_path'])): ?>
                                            <img src="/<?= htmlspecialchars($set['set_image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($set['set_name']) ?>" 
                                                 onerror="this.parentElement.innerHTML='<div class=\'set-image-placeholder\'><i class=\'fas fa-user-astronaut\'></i></div>'">
                                        <?php else: ?>
                                            <div class="set-image-placeholder">
                                                <i class="fas fa-user-astronaut"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Set Bilgileri -->
                                <td>
                                    <div class="set-info">
                                        <h3>
                                            <a href="view.php?id=<?= $set['id'] ?>">
                                                <?= htmlspecialchars($set['set_name']) ?>
                                            </a>
                                        </h3>
                                        <div class="set-description">
                                            <?= htmlspecialchars($set['set_description']) ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Oluşturan -->
                                <td>
                                    <div class="creator-info">
                                        <img src="<?= !empty($set['avatar_path']) ? '/' . htmlspecialchars($set['avatar_path']) : '/assets/logo.png' ?>" 
                                             alt="<?= htmlspecialchars($set['username']) ?>" 
                                             class="creator-avatar"
                                             onerror="this.src='/assets/logo.png'">
                                        <span class="creator-name"><?= htmlspecialchars($set['username']) ?></span>
                                    </div>
                                </td>

                                <!-- İstatistikler -->
                                <td>
                                    <div class="set-stats">
                                        <div class="stat-item">
                                            <i class="fas fa-boxes"></i>
                                            <span><?= $set['item_count'] ?> item</span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="fas fa-puzzle-piece"></i>
                                            <span><?= $set['attachment_count'] ?> eklenti</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Görünürlük -->
                                <td>
                                    <span class="visibility-badge visibility-<?= $set['visibility'] ?>">
                                        <i class="<?= get_visibility_icon($set['visibility']) ?>"></i>
                                        <?= get_visibility_text($set['visibility']) ?>
                                    </span>
                                </td>

                                <!-- Oluşturma Tarihi -->
                                <td>
                                    <span title="<?= date('d.m.Y H:i', strtotime($set['created_at'])) ?>">
                                        <?= time_ago($set['created_at']) ?>
                                    </span>
                                </td>

                                <!-- İşlemler -->
                                <?php if (is_user_logged_in()): ?>
                                    <td>
                                        <div class="set-actions">
                                            <a href="view.php?id=<?= $set['id'] ?>" 
                                               class="btn-action" 
                                               title="Görüntüle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($set['user_id'] == $_SESSION['user_id'] || has_permission($pdo, 'loadout.manage_sets')): ?>
                                                <a href="create_loadouts.php?edit=<?= $set['id'] ?>" 
                                                   class="btn-action edit" 
                                                   title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button type="button" 
                                                        class="btn-action delete" 
                                                        title="Sil"
                                                        onclick="confirmDelete(<?= $set['id'] ?>, '<?= htmlspecialchars($set['set_name'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="loadouts-table-container">
                <div class="empty-state">
                    <i class="fas fa-user-astronaut"></i>
                    <h3>Henüz Teçhizat Seti Yok</h3>
                    <p>
                        <?php if (!empty($search_query)): ?>
                            "<strong><?= htmlspecialchars($search_query) ?></strong>" aramanız için sonuç bulunamadı. 
                            Farklı arama terimleri deneyin.
                        <?php elseif ($visibility_filter === 'my_sets'): ?>
                            Henüz hiç teçhizat seti oluşturmadınız. İlk setinizi oluşturmaya başlayın!
                        <?php else: ?>
                            Henüz hiç teçhizat seti paylaşılmamış. İlk seti siz oluşturun!
                        <?php endif; ?>
                    </p>
                    
                    <?php if (is_user_approved() && has_permission($pdo, 'loadout.manage_sets')): ?>
                        <a href="create_loadouts.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            İlk Seti Oluştur
                        </a>
                    <?php elseif (!is_user_logged_in()): ?>
                        <a href="/login.php" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Giriş Yap
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Silme Onayı</h3>
            <button class="modal-close" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">Bu teçhizat setini silmek istediğinizden emin misiniz?</p>
            <p><strong id="deleteSetName"></strong></p>
            <p style="color: var(--red); font-size: 0.9rem; margin-top: 1rem;">
                <i class="fas fa-warning"></i> Bu işlem geri alınamaz!
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeDeleteModal()">İptal</button>
            <button class="btn-delete" id="confirmDeleteBtn">
                <i class="fas fa-trash"></i>
                Sil
            </button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
}

.modal-content {
    position: relative;
    background: var(--card-bg);
    border: 1px solid var(--border-1-featured);
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-1);
}

.modal-header h3 {
    margin: 0;
    color: var(--red);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: transparent;
    border: 1px solid var(--border-1);
    color: var(--light-grey);
    padding: 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: var(--red);
    color: var(--white);
    border-color: var(--red);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding: 1.5rem;
    border-top: 1px solid var(--border-1);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: transparent;
    color: var(--light-grey);
    border: 1px solid var(--border-1);
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: var(--card-bg-2);
    color: var(--lighter-grey);
}

.btn-delete {
    padding: 0.75rem 1.5rem;
    background: var(--red);
    color: var(--white);
    border: 1px solid var(--red);
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-delete:hover {
    background: var(--dark-red);
    transform: translateY(-1px);
}
</style>

<script>
let deleteSetId = null;

function confirmDelete(setId, setName) {
    deleteSetId = setId;
    document.getElementById('deleteSetName').textContent = setName;
    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    deleteSetId = null;
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteSetId) return;
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
    btn.disabled = true;
    
    // CSRF token al
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    fetch('actions/delete_loadout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            loadout_id: deleteSetId,
            csrf_token: csrfToken
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Başarılı silme - sayfayı yenile
            showMessage(data.message || 'Teçhizat seti başarıyla silindi', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showMessage(data.message || 'Silme işlemi başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showMessage('Silme sırasında bir hata oluştu', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        closeDeleteModal();
    });
});

// Modal dışına tıklayınca kapat
document.addEventListener('click', function(e) {
    if (e.target.matches('.modal-overlay')) {
        closeDeleteModal();
    }
});

// Escape tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Message gösterme fonksiyonu
function showMessage(message, type = 'info') {
    // Mevcut mesajları kaldır
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());
    
    // Yeni mesaj oluştur
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        padding: 1rem 1.5rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: var(--font);
        font-weight: 500;
        animation: slideInRight 0.3s ease;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;
    
    if (type === 'success') {
        messageDiv.style.background = 'rgba(40, 167, 69, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.style.border = '1px solid #28a745';
        messageDiv.innerHTML = '<i class="fas fa-check-circle"></i><span>' + message + '</span>';
    } else if (type === 'error') {
        messageDiv.style.background = 'rgba(220, 53, 69, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.style.border = '1px solid #dc3545';
        messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + message + '</span>';
    } else {
        messageDiv.style.background = 'rgba(23, 162, 184, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.style.border = '1px solid #17a2b8';
        messageDiv.innerHTML = '<i class="fas fa-info-circle"></i><span>' + message + '</span>';
    }
    
    document.body.appendChild(messageDiv);
    
    // 5 saniye sonra kaldır
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => {
            messageDiv.remove();
        }, 300);
    }, 5000);
}

// CSS animasyonu ekle
const style = document.createElement('style');
style.textContent = `
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
`;
document.head.appendChild(style);
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>