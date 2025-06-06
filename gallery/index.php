<?php
// gallery/index.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/gallery_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Session kontrolü
check_user_session_validity();

// Kullanıcı bilgileri
$current_user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = is_user_logged_in();
$is_approved = is_user_approved();

// Sayfa parametreleri
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Sıralama parametreleri
$sort = $_GET['sort'] ?? 'newest';
$allowed_sorts = ['newest', 'oldest', 'most_liked', 'most_commented'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'newest';
}

// Kullanıcı filtresi
$user_filter = $_GET['user'] ?? '';

// Fotoğrafları çek
$photos_data = get_gallery_photos($pdo, $current_user_id, $per_page, $offset, $sort, $user_filter);
$photos = $photos_data['photos'];
$total_photos = $photos_data['total'];
$total_pages = ceil($total_photos / $per_page);

// İstatistikler
$gallery_stats = get_gallery_statistics($pdo, $current_user_id);

// Upload yetkisi kontrolü
$can_upload = $is_approved && has_permission($pdo, 'gallery.upload', $current_user_id);

// Sayfa başlığı
$page_title = "Galeri - Ilgarion Turanis";

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<link rel="stylesheet" href="/gallery/css/gallery.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="gallery-page-container">
    <!-- Breadcrumb -->
    <nav class="gallery-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/index.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-images"></i> Galeri
            </li>
        </ol>
    </nav>

    <!-- Galeri Header -->
    <div class="gallery-header">
        <div class="gallery-title">
            <h1><i class="fas fa-images"></i> Fotoğraf Galerisi</h1>
            <p>Topluluğumuzun Star Citizen deneyimlerini görsel olarak paylaştığı galeri.</p>
        </div>
        
        <div class="gallery-stats">
            <div class="stat-item">
                <div class="stat-number"><?= number_format($gallery_stats['total_photos']) ?></div>
                <div class="stat-label">Toplam Fotoğraf</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($gallery_stats['total_contributors']) ?></div>
                <div class="stat-label">Katkıda Bulunan</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($gallery_stats['total_likes']) ?></div>
                <div class="stat-label">Toplam Beğeni</div>
            </div>
        </div>
    </div>

    <!-- Galeri Kontrolleri -->
    <div class="gallery-controls">
        <div class="gallery-filters">
            <!-- Sıralama -->
            <div class="filter-group">
                <label for="sort-select">Sıralama:</label>
                <select id="sort-select" onchange="updateGalleryFilters()">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>En Yeni</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>En Eski</option>
                    <option value="most_liked" <?= $sort === 'most_liked' ? 'selected' : '' ?>>En Beğenilen</option>
                    <option value="most_commented" <?= $sort === 'most_commented' ? 'selected' : '' ?>>En Çok Yorumlanan</option>
                </select>
            </div>
            
            <!-- Kullanıcı Filtresi -->
            <div class="filter-group">
                <label for="user-filter">Kullanıcı:</label>
                <input type="text" id="user-filter" placeholder="Kullanıcı ara..." value="<?= htmlspecialchars($user_filter) ?>">
                <button type="button" onclick="clearUserFilter()" class="btn-clear-filter">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="gallery-actions">
            <?php if ($can_upload): ?>
                <button class="btn-upload" onclick="openUploadModal()">
                    <i class="fas fa-plus"></i> Fotoğraf Yükle
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fotoğraf Grid -->
    <div class="gallery-grid">
        <?php if (empty($photos)): ?>
            <div class="no-photos">
                <i class="fas fa-images"></i>
                <h3>Henüz fotoğraf bulunmuyor</h3>
                <?php if (!$is_logged_in): ?>
                    <p>Fotoğraf paylaşımları görmek için giriş yapın.</p>
                    <div class="login-actions">
                        <a href="/register.php?mode=login" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Giriş Yap
                        </a>
                        <a href="/register.php" class="btn-secondary">
                            <i class="fas fa-user-plus"></i> Kayıt Ol
                        </a>
                    </div>
                <?php elseif (!$is_approved): ?>
                    <p>Fotoğraf paylaşımları görmek için hesabınızın onaylanması gerekmektedir.</p>
                <?php elseif ($can_upload): ?>
                    <p>İlk fotoğrafı siz paylaşın!</p>
                    <button class="btn-primary" onclick="openUploadModal()">
                        <i class="fas fa-plus"></i> Fotoğraf Yükle
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($photos as $photo): ?>
                <div class="photo-item" data-photo-id="<?= $photo['id'] ?>">
                    <div class="photo-container" onclick="openPhotoModal(<?= $photo['id'] ?>)">
                        <img src="/<?= htmlspecialchars($photo['image_path']) ?>" 
                             alt="<?= htmlspecialchars($photo['description'] ?: 'Galeri Fotoğrafı') ?>"
                             loading="lazy"
                             class="photo-image">
                        
                        <div class="photo-overlay">
                            <div class="photo-actions">
                                <button class="photo-action-btn like-btn" 
                                        onclick="event.stopPropagation(); togglePhotoLike(<?= $photo['id'] ?>)"
                                        <?= $photo['user_liked'] ? 'class="liked"' : '' ?>>
                                    <i class="fas fa-heart"></i>
                                    <span class="like-count"><?= $photo['like_count'] ?></span>
                                </button>
                                
                                <button class="photo-action-btn comment-btn" 
                                        onclick="event.stopPropagation(); openPhotoModal(<?= $photo['id'] ?>, true)">
                                    <i class="fas fa-comment"></i>
                                    <span class="comment-count"><?= $photo['comment_count'] ?></span>
                                </button>
                                
                                <?php if ($current_user_id && ($photo['user_id'] == $current_user_id || has_permission($pdo, 'gallery.delete_any', $current_user_id))): ?>
                                    <button class="photo-action-btn delete-btn" 
                                            onclick="event.stopPropagation(); confirmPhotoDelete(<?= $photo['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="photo-info">
                        <div class="photo-meta">
                            <span class="user-link" data-user-id="<?= $photo['user_id'] ?>" 
                                  style="color: <?= htmlspecialchars($photo['user_role_color'] ?? '#bd912a') ?>">
                                <?= htmlspecialchars($photo['username']) ?>
                            </span>
                            <span class="upload-date"><?= format_time_ago($photo['uploaded_at']) ?></span>
                        </div>
                        
                        <?php if (!empty($photo['description'])): ?>
                            <div class="photo-description">
                                <?= htmlspecialchars(substr($photo['description'], 0, 100)) ?>
                                <?= strlen($photo['description']) > 100 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="gallery-pagination">
            <div class="pagination-info">
                Sayfa <?= $page ?> / <?= $total_pages ?> (Toplam <?= number_format($total_photos) ?> fotoğraf)
            </div>
            
            <div class="pagination-controls">
                <?php if ($page > 1): ?>
                    <a href="?page=1&sort=<?= urlencode($sort) ?>&user=<?= urlencode($user_filter) ?>" class="btn-page">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?= $page - 1 ?>&sort=<?= urlencode($sort) ?>&user=<?= urlencode($user_filter) ?>" class="btn-page">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?= $i ?>&sort=<?= urlencode($sort) ?>&user=<?= urlencode($user_filter) ?>" 
                       class="btn-page <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&sort=<?= urlencode($sort) ?>&user=<?= urlencode($user_filter) ?>" class="btn-page">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?= $total_pages ?>&sort=<?= urlencode($sort) ?>&user=<?= urlencode($user_filter) ?>" class="btn-page">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<?php if ($can_upload): ?>
    <div id="uploadModal" class="gallery-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeUploadModal()"></div>
        <div class="modal-content upload-modal">
            <div class="modal-header">
                <h3><i class="fas fa-upload"></i> Fotoğraf Yükle</h3>
                <button type="button" class="modal-close" onclick="closeUploadModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-content">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h4>Fotoğraf yüklemek için tıklayın veya sürükleyip bırakın</h4>
                            <p>Maksimum dosya boyutu: 10MB</p>
                            <p>Desteklenen formatlar: JPG, JPEG, PNG, GIF, WEBP, BMP</p>
                        </div>
                        <input type="file" id="photoFile" name="photo" accept="image/*" style="display: none;">
                    </div>
                    
                    <div class="photo-preview" id="photoPreview" style="display: none;">
                        <img id="previewImage" src="" alt="Önizleme">
                        <button type="button" class="btn-remove-photo" onclick="removePhotoPreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label for="photoDescription">Açıklama (Opsiyonel):</label>
                        <textarea id="photoDescription" name="description" 
                                  placeholder="Fotoğraf hakkında bir açıklama yazın..." 
                                  maxlength="1000" rows="3"></textarea>
                        <div class="char-counter">
                            <span id="desc-char-count">0</span> / 1000 karakter
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Görünürlük:</label>
                        <div class="visibility-options">
                            <label class="radio-option">
                                <input type="radio" name="visibility" value="public" checked>
                                <span class="radio-custom"></span>
                                <span class="radio-label">
                                    <i class="fas fa-globe"></i> Herkese Açık
                                    <small>Giriş yapmayan kullanıcılar da görebilir</small>
                                </span>
                            </label>
                            
                            <label class="radio-option">
                                <input type="radio" name="visibility" value="members_only">
                                <span class="radio-custom"></span>
                                <span class="radio-label">
                                    <i class="fas fa-users"></i> Sadece Üyelere Açık
                                    <small>Sadece onaylı üyeler görebilir</small>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="upload-progress" id="uploadProgress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div class="progress-text" id="progressText">Yükleniyor... 0%</div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="submit" class="btn-submit" id="uploadSubmitBtn">
                            <i class="fas fa-upload"></i> Yükle
                        </button>
                        <button type="button" class="btn-cancel" onclick="closeUploadModal()">
                            <i class="fas fa-times"></i> İptal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Photo Modal -->
<div id="photoModal" class="gallery-modal" style="display: none;">
    <div class="modal-overlay" onclick="closePhotoModal()"></div>
    <div class="modal-content photo-modal">
        <div class="modal-header">
            <div class="photo-modal-info">
                <span class="photo-modal-title" id="photoModalTitle">Fotoğraf Detayları</span>
                <div class="photo-modal-meta" id="photoModalMeta"></div>
            </div>
            <button type="button" class="modal-close" onclick="closePhotoModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body photo-modal-body">
            <div class="photo-display">
                <img id="modalPhotoImage" src="" alt="Fotoğraf">
            </div>
            
            <div class="photo-sidebar">
                <div class="photo-details" id="photoDetails">
                    <!-- Photo details will be loaded here -->
                </div>
                
                <div class="photo-comments" id="photoComments">
                    <!-- Comments will be loaded here -->
                </div>
                
                <div class="comment-form-section" id="commentFormSection">
                    <!-- Comment form will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Popover Include -->
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>

<script src="/gallery/js/gallery-utils.js"></script>
<script src="/gallery/js/gallery-upload.js"></script>
<script src="/gallery/js/gallery-modal.js"></script>
<script src="/gallery/js/gallery-actions.js"></script>
<script src="/gallery/js/gallery-filters.js"></script>
<script src="/gallery/js/gallery.js"></script>

<?php
// Zaman formatlama fonksiyonu
function format_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    if ($time < 2592000) return floor($time/604800) . ' hafta önce';
    
    return date('d.m.Y', strtotime($datetime));
}

include BASE_PATH . '/src/includes/footer.php';
?>