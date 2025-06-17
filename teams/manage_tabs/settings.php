<?php
// /teams/manage_tabs/settings.php
// Bu dosya takım yönetimi sayfasının settings tab'ında include edilir

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
?>

<!-- Settings Tab Content -->
<div class="settings-tab">
    <!-- Temel Takım Ayarları -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-cog"></i> Temel Ayarlar</h3>
        </div>
        <div class="card-body">
            <form method="post" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="form_type" value="basic_settings">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">
                            <i class="fas fa-tag"></i> Takım Adı
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control" 
                               value="<?= htmlspecialchars($team_data['name']) ?>"
                               maxlength="100" 
                               required>
                        <small class="form-help">3-100 karakter arasında olmalıdır.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="tag" class="form-label">
                            <i class="fas fa-hashtag"></i> Takım Etiketi
                        </label>
                        <input type="text" 
                               id="tag" 
                               name="tag" 
                               class="form-control" 
                               value="<?= htmlspecialchars($team_data['tag'] ?? '') ?>"
                               placeholder="PvP, Mining, Exploration..."
                               maxlength="50">
                        <small class="form-help">Takımınızın odak alanını belirtir (isteğe bağlı).</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left"></i> Açıklama
                    </label>
                    <textarea id="description" 
                              name="description" 
                              class="form-control" 
                              rows="4" 
                              maxlength="1000"
                              placeholder="Takımınızı tanıtın..."><?= htmlspecialchars($team_data['description'] ?? '') ?></textarea>
                    <small class="form-help">Maksimum 1000 karakter.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="color" class="form-label">
                            <i class="fas fa-palette"></i> Takım Rengi
                        </label>
                        <div class="color-input-group">
                            <input type="color" 
                                   id="color" 
                                   name="color" 
                                   class="form-control color-picker" 
                                   value="<?= htmlspecialchars($team_data['color']) ?>">
                            <input type="text" 
                                   id="colorText" 
                                   class="form-control color-text" 
                                   value="<?= htmlspecialchars($team_data['color']) ?>" 
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   maxlength="7">
                        </div>
                        <small class="form-help">Takımınızın kimlik rengi.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_members" class="form-label">
                            <i class="fas fa-users"></i> Maksimum Üye Sayısı
                        </label>
                        <input type="number" 
                               id="max_members" 
                               name="max_members" 
                               class="form-control" 
                               value="<?= $team_data['max_members'] ?>"
                               min="1" 
                               max="100" 
                               required>
                        <small class="form-help">1-100 arasında olmalıdır.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" 
                               id="is_recruitment_open" 
                               name="is_recruitment_open"
                               <?= $team_data['is_recruitment_open'] ? 'checked' : '' ?>>
                        <label for="is_recruitment_open" class="checkbox-label">
                            <i class="fas fa-door-open"></i> Yeni üye alımına açık
                        </label>
                    </div>
                    <small class="form-help">Bu seçenek kapalıysa, takımınıza kimse başvuru yapamaz.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logo Yönetimi -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-image"></i> Logo Yönetimi</h3>
        </div>
        <div class="card-body">
            <div class="media-management">
                <div class="current-media">
                    <div class="media-preview">
                        <?php if ($team_data['logo_path']): ?>
                            <img src="/uploads/teams/logos/<?= htmlspecialchars($team_data['logo_path']) ?>" 
                                 alt="Mevcut Logo" 
                                 class="current-logo">
                        <?php else: ?>
                            <div class="placeholder-logo">
                                <i class="fas fa-image"></i>
                                <span>Logo Yok</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="media-info">
                        <h4>Mevcut Logo</h4>
                        <p>Logoyu güncellemek için aşağıdan yeni bir dosya seçin.</p>
                        <ul class="upload-requirements">
                            <li><i class="fas fa-check"></i> Format: JPEG, PNG, GIF veya WebP</li>
                            <li><i class="fas fa-check"></i> Maksimum boyut: 2MB</li>
                            <li><i class="fas fa-check"></i> Önerilen çözünürlük: 256x256 piksel</li>
                        </ul>
                    </div>
                </div>
                
                <form method="post" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="form_type" value="logo_upload">
                    
                    <div class="file-input-wrapper">
                        <input type="file" 
                               id="logo" 
                               name="logo" 
                               class="file-input" 
                               accept="image/jpeg,image/png,image/gif,image/webp" 
                               required>
                        <label for="logo" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Logo Dosyası Seç</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Logo Yükle
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Banner Yönetimi -->
    <div class="manage-card">
        <div class="card-header">
            <h3><i class="fas fa-panorama"></i> Banner Yönetimi</h3>
        </div>
        <div class="card-body">
            <div class="media-management">
                <div class="current-media">
                    <div class="media-preview banner-preview">
                        <?php if ($team_data['banner_path']): ?>
                            <img src="<?= htmlspecialchars($team_data['banner_path']) ?>" 
                                 alt="Mevcut Banner" 
                                 class="current-banner">
                        <?php else: ?>
                            <div class="placeholder-banner">
                                <i class="fas fa-panorama"></i>
                                <span>Banner Yok</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="media-info">
                        <h4>Mevcut Banner</h4>
                        <p>Banner'ı güncellemek için aşağıdan yeni bir dosya seçin.</p>
                        <ul class="upload-requirements">
                            <li><i class="fas fa-check"></i> Format: JPEG, PNG, GIF veya WebP</li>
                            <li><i class="fas fa-check"></i> Maksimum boyut: 5MB</li>
                            <li><i class="fas fa-check"></i> Önerilen çözünürlük: 1920x400 piksel</li>
                        </ul>
                    </div>
                </div>
                
                <form method="post" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="form_type" value="banner_upload">
                    
                    <div class="file-input-wrapper">
                        <input type="file" 
                               id="banner" 
                               name="banner" 
                               class="file-input" 
                               accept="image/jpeg,image/png,image/gif,image/webp" 
                               required>
                        <label for="banner" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Banner Dosyası Seç</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Banner Yükle
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tehlikeli İşlemler -->
    <div class="manage-card danger-zone">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Tehlikeli İşlemler</h3>
        </div>
        <div class="card-body">
            <div class="danger-actions">
                <div class="danger-item">
                    <div class="danger-info">
                        <h4><i class="fas fa-trash"></i> Takımı Sil</h4>
                        <p>Bu işlem geri alınamaz. Takım ve tüm verileri kalıcı olarak silinir.</p>
                    </div>
                    <button type="button" class="btn btn-danger" onclick="showDeleteModal()">
                        <i class="fas fa-trash-alt"></i> Takımı Sil
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Takım Silme Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Takımı Sil</h3>
            <button type="button" class="modal-close" onclick="hideDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="warning-text">
                <p><strong>DİKKAT:</strong> Bu işlem geri alınamaz!</p>
                <p>Takımı sildiğinizde:</p>
                <ul>
                    <li>Tüm takım verileri kalıcı olarak silinir</li>
                    <li>Tüm üyeler takımdan çıkarılır</li>
                    <li>Bekleyen başvurular iptal edilir</li>
                    <li>Takım logosu ve banner'ı silinir</li>
                </ul>
            </div>
            
            <form method="post" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="form_type" value="delete_team">
                
                <div class="form-group">
                    <label for="confirm_name" class="form-label">
                        Onaylamak için takım adını yazın: <strong><?= htmlspecialchars($team_data['name']) ?></strong>
                    </label>
                    <input type="text" 
                           id="confirm_name" 
                           name="confirm_name" 
                           class="form-control" 
                           placeholder="Takım adını buraya yazın..." 
                           required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" onclick="hideDeleteModal()">
                <i class="fas fa-times"></i> İptal
            </button>
            <button type="submit" form="deleteForm" class="btn btn-danger">
                <i class="fas fa-trash-alt"></i> Takımı Kalıcı Olarak Sil
            </button>
        </div>
    </div>
</div>

<script>
// Settings Tab JavaScript

// Color picker synchronization
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('color');
    const colorText = document.getElementById('colorText');
    
    if (colorPicker && colorText) {
        // Color picker değiştiğinde text input'u güncelle
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value.toUpperCase();
        });
        
        // Text input değiştiğinde color picker'ı güncelle
        colorText.addEventListener('input', function() {
            const value = this.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                colorPicker.value = value;
            }
        });
    }
    
    // File input change events
    const fileInputs = document.querySelectorAll('.file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const label = this.nextElementSibling;
            const span = label.querySelector('span');
            if (this.files.length > 0) {
                span.textContent = this.files[0].name;
                label.style.color = 'var(--gold)';
            } else {
                span.textContent = label.dataset.originalText || 'Dosya Seç';
                label.style.color = '';
            }
        });
        
        // Store original text
        const label = input.nextElementSibling;
        const span = label.querySelector('span');
        label.dataset.originalText = span.textContent;
    });
    
    // File upload validation
    const logoInput = document.getElementById('logo');
    const bannerInput = document.getElementById('banner');
    
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            validateImageFile(this, 2 * 1024 * 1024, 'Logo'); // 2MB
        });
    }
    
    if (bannerInput) {
        bannerInput.addEventListener('change', function() {
            validateImageFile(this, 5 * 1024 * 1024, 'Banner'); // 5MB
        });
    }
    
    // Form validation
    const basicForm = document.querySelector('form[input[name="form_type"][value="basic_settings"]]');
    if (basicForm) {
        basicForm.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const maxMembers = parseInt(document.getElementById('max_members').value);
            
            if (name.length < 3 || name.length > 100) {
                e.preventDefault();
                alert('Takım adı 3-100 karakter arasında olmalıdır.');
                return;
            }
            
            if (maxMembers < 1 || maxMembers > 100) {
                e.preventDefault();
                alert('Maksimum üye sayısı 1-100 arasında olmalıdır.');
                return;
            }
        });
    }
});

// Modal functions
function showDeleteModal() {
    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = '';
    
    // Reset form
    const form = document.getElementById('deleteForm');
    if (form) {
        form.reset();
    }
}

// Close modal on outside click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteModal');
    if (e.target === modal) {
        hideDeleteModal();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
});

// File validation function
function validateImageFile(input, maxSize, type) {
    const file = input.files[0];
    if (!file) return;
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!allowedTypes.includes(file.type)) {
        alert(type + ' için sadece JPEG, PNG, GIF veya WebP formatında dosya yükleyebilirsiniz.');
        input.value = '';
        return;
    }
    
    if (file.size > maxSize) {
        const maxSizeMB = maxSize / (1024 * 1024);
        alert(type + ' dosyası maksimum ' + maxSizeMB + 'MB olabilir.');
        input.value = '';
        return;
    }
}
</script>