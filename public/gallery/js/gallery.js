// public/gallery/js/gallery.js

let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

document.addEventListener('DOMContentLoaded', function() {
    // CSRF token'ı input'tan al
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        csrfToken = csrfInput.value;
    }

    // Upload form karakter sayacı
    const photoDescription = document.getElementById('photoDescription');
    const descCharCount = document.getElementById('desc-char-count');
    
    if (photoDescription && descCharCount) {
        photoDescription.addEventListener('input', function() {
            descCharCount.textContent = this.value.length;
            
            if (this.value.length > 950) {
                descCharCount.style.color = 'var(--red)';
            } else if (this.value.length > 800) {
                descCharCount.style.color = 'var(--gold)';
            } else {
                descCharCount.style.color = 'var(--light-grey)';
            }
        });
    }

    // Upload area event listeners
    setupUploadArea();
    
    // Filtre inputları
    setupFilterInputs();
});

// Upload Area Setup
function setupUploadArea() {
    const uploadArea = document.getElementById('uploadArea');
    const photoFile = document.getElementById('photoFile');
    
    if (!uploadArea || !photoFile) return;
    
    // Click to upload
    uploadArea.addEventListener('click', () => {
        photoFile.click();
    });
    
    // File input change
    photoFile.addEventListener('change', handleFileSelect);
    
    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            photoFile.files = files;
            handleFileSelect({ target: { files } });
        }
    });
}

// Dosya seçimi işleme
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Dosya boyutu kontrolü
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        showNotification('Dosya boyutu çok büyük. Maksimum 10MB olabilir.', 'error');
        return;
    }
    
    // Dosya tipi kontrolü
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    if (!allowedTypes.includes(file.type)) {
        showNotification('Desteklenmeyen dosya formatı. Sadece JPG, PNG, GIF, WEBP ve BMP dosyaları yükleyebilirsiniz.', 'error');
        return;
    }
    
    // Önizleme göster
    const reader = new FileReader();
    reader.onload = function(e) {
        showPhotoPreview(e.target.result);
    };
    reader.readAsDataURL(file);
}

// Fotoğraf önizlemesi göster
function showPhotoPreview(imageSrc) {
    const uploadArea = document.getElementById('uploadArea');
    const photoPreview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');
    
    if (uploadArea && photoPreview && previewImage) {
        uploadArea.style.display = 'none';
        previewImage.src = imageSrc;
        previewImage.onload = function() {
            console.log('Preview image loaded successfully');
        };
        previewImage.onerror = function() {
            console.error('Preview image failed to load:', imageSrc);
        };
        photoPreview.style.display = 'block';
    }
}

// Fotoğraf önizlemesini kaldır
function removePhotoPreview() {
    const uploadArea = document.getElementById('uploadArea');
    const photoPreview = document.getElementById('photoPreview');
    const photoFile = document.getElementById('photoFile');
    
    if (uploadArea && photoPreview && photoFile) {
        photoPreview.style.display = 'none';
        uploadArea.style.display = 'block';
        photoFile.value = '';
    }
}

// Upload modal aç
function openUploadModal() {
    const modal = document.getElementById('uploadModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Form'u sıfırla
        document.getElementById('uploadForm').reset();
        removePhotoPreview();
        hideUploadProgress();
    }
}

// Upload modal kapat
function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Upload progress göster/gizle
function showUploadProgress() {
    const progress = document.getElementById('uploadProgress');
    if (progress) {
        progress.style.display = 'block';
    }
}

function hideUploadProgress() {
    const progress = document.getElementById('uploadProgress');
    if (progress) {
        progress.style.display = 'none';
    }
}

function updateUploadProgress(percent) {
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    
    if (progressFill && progressText) {
        progressFill.style.width = percent + '%';
        progressText.textContent = `Yükleniyor... ${percent}%`;
    }
}

// Upload form submit
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const photoFile = document.getElementById('photoFile');
            if (!photoFile.files[0]) {
                showNotification('Lütfen bir fotoğraf seçin.', 'error');
                return;
            }
            
            const submitBtn = document.getElementById('uploadSubmitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Button'u devre dışı bırak
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
            
            showUploadProgress();
            
            try {
                const formData = new FormData(this);
                
                const xhr = new XMLHttpRequest();
                
                // Progress tracking
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        updateUploadProgress(percent);
                    }
                });
                
                xhr.onload = function() {
                    try {
                        if (xhr.status !== 200) {
                            throw new Error(`HTTP ${xhr.status}: ${xhr.statusText}`);
                        }
                        
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            showNotification(response.message, 'success');
                            closeUploadModal();
                            
                            // Sayfayı yenile (yeni fotoğrafı göstermek için)
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showNotification(response.message || 'Yükleme başarısız oldu.', 'error');
                        }
                    } catch (error) {
                        console.error('Upload response parse error:', error);
                        console.error('Response text:', xhr.responseText);
                        showNotification('Yükleme sırasında bir hata oluştu: ' + error.message, 'error');
                    }
                };
                
                xhr.onerror = function() {
                    showNotification('Yükleme sırasında bir ağ hatası oluştu.', 'error');
                };
                
                xhr.open('POST', '/public/gallery/actions/upload_photo.php');
                xhr.send(formData);
                
            } catch (error) {
                console.error('Upload error:', error);
                showNotification('Yükleme sırasında bir hata oluştu: ' + error.message, 'error');
            } finally {
                // Button'u tekrar aktif et
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                hideUploadProgress();
            }
        });
    }
});

// Filtre sistemini ayarla
function setupFilterInputs() {
    const userFilter = document.getElementById('user-filter');
    
    if (userFilter) {
        let timeout;
        userFilter.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                updateGalleryFilters();
            }, 500); // 500ms delay
        });
        
        userFilter.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                updateGalleryFilters();
            }
        });
    }
}

// Galeri filtrelerini güncelle
function updateGalleryFilters() {
    const sortSelect = document.getElementById('sort-select');
    const userFilter = document.getElementById('user-filter');
    
    const params = new URLSearchParams();
    
    if (sortSelect && sortSelect.value !== 'newest') {
        params.set('sort', sortSelect.value);
    }
    
    if (userFilter && userFilter.value.trim()) {
        params.set('user', userFilter.value.trim());
    }
    
    const queryString = params.toString();
    const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
    
    window.location.href = newUrl;
}

// Kullanıcı filtresini temizle
function clearUserFilter() {
    const userFilter = document.getElementById('user-filter');
    if (userFilter) {
        userFilter.value = '';
        updateGalleryFilters();
    }
}

// Fotoğraf beğeni toggle
async function togglePhotoLike(photoId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    const likeBtn = document.querySelector(`[onclick*="togglePhotoLike(${photoId})"]`);
    if (!likeBtn) return;

    const originalText = likeBtn.innerHTML;
    likeBtn.disabled = true;
    likeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const response = await fetch('/public/gallery/actions/toggle_photo_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&photo_id=${photoId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const likeCount = likeBtn.querySelector('.like-count');
            
            if (data.liked) {
                likeBtn.classList.add('liked');
            } else {
                likeBtn.classList.remove('liked');
            }
            
            if (likeCount) {
                likeCount.textContent = data.like_count;
                likeCount.classList.add('updated');
                setTimeout(() => likeCount.classList.remove('updated'), 400);
            }
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Photo like toggle error:', error);
        showNotification('Beğeni işlemi başarısız oldu.', 'error');
    } finally {
        likeBtn.disabled = false;
        likeBtn.innerHTML = originalText;
    }
}

// Fotoğraf modal aç
async function openPhotoModal(photoId, focusComments = false) {
    try {
        // Fotoğraf detaylarını yükle
        const response = await fetch(`/public/gallery/actions/get_photo_details.php?photo_id=${photoId}`);
        const data = await response.json();
        
        if (!data.success) {
            showNotification(data.message || 'Fotoğraf yüklenemedi.', 'error');
            return;
        }
        
        const modal = document.getElementById('photoModal');
        const modalTitle = document.getElementById('photoModalTitle');
        const modalMeta = document.getElementById('photoModalMeta');
        const modalImage = document.getElementById('modalPhotoImage');
        const photoDetails = document.getElementById('photoDetails');
        const photoComments = document.getElementById('photoComments');
        const commentFormSection = document.getElementById('commentFormSection');
        
        // Modal başlığı ve meta bilgileri
        modalTitle.textContent = data.photo.description || 'Fotoğraf Detayları';
        modalMeta.innerHTML = `
            <span class="user-link" data-user-id="${data.photo.user_id}" style="color: ${data.photo.user_role_color || '#bd912a'}">
                ${escapeHtml(data.photo.username)}
            </span> tarafından ${formatTimeAgo(data.photo.uploaded_at)} paylaşıldı
        `;
        
        // Fotoğraf
        modalImage.src = '/' + data.photo.image_path;
        modalImage.alt = data.photo.description || 'Galeri Fotoğrafı';
        
        // Fotoğraf detayları
        photoDetails.innerHTML = `
            <h4><i class="fas fa-info-circle"></i> Fotoğraf Bilgileri</h4>
            <div class="detail-item">
                <span class="detail-label">Yükleyen:</span>
                <span class="detail-value">
                    <span class="user-link" data-user-id="${data.photo.user_id}" style="color: ${data.photo.user_role_color || '#bd912a'}">
                        ${escapeHtml(data.photo.username)}
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Tarih:</span>
                <span class="detail-value">${formatDateTime(data.photo.uploaded_at)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Beğeni:</span>
                <span class="detail-value">${data.photo.like_count}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Yorum:</span>
                <span class="detail-value">${data.photo.comment_count}</span>
            </div>
            ${data.photo.description ? `
                <div class="photo-description-full">
                    <strong>Açıklama:</strong><br>
                    ${escapeHtml(data.photo.description)}
                </div>
            ` : ''}
            <div class="photo-actions-modal">
                ${data.photo.can_like ? `
                    <button class="photo-action-modal-btn ${data.photo.user_liked ? 'liked' : ''}" 
                            onclick="togglePhotoLikeModal(${photoId})">
                        <i class="fas fa-heart"></i>
                        <span class="like-count">${data.photo.like_count}</span>
                    </button>
                ` : ''}
                ${data.photo.can_delete ? `
                    <button class="photo-action-modal-btn delete" onclick="confirmPhotoDeleteModal(${photoId})">
                        <i class="fas fa-trash"></i> Sil
                    </button>
                ` : ''}
            </div>
        `;
        
        // Yorumları yükle
        loadPhotoComments(photoId);
        
        // Yorum formu
        if (data.photo.can_comment) {
            commentFormSection.innerHTML = `
                <div class="comment-form">
                    <h4><i class="fas fa-comment"></i> Yorum Yap</h4>
                    <form id="commentForm" onsubmit="submitPhotoComment(event, ${photoId})">
                        <textarea id="commentText" name="comment_text" 
                                  placeholder="Yorumunuzu yazın..." 
                                  maxlength="1000" rows="3" required></textarea>
                        <div class="comment-form-actions">
                            <div class="comment-char-counter">
                                <span id="comment-char-count">0</span> / 1000 karakter
                            </div>
                            <button type="submit" class="btn-comment-submit">
                                <i class="fas fa-paper-plane"></i> Gönder
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            // Yorum form karakter sayacı
            const commentTextarea = document.getElementById('commentText');
            const commentCharCount = document.getElementById('comment-char-count');
            
            if (commentTextarea && commentCharCount) {
                commentTextarea.addEventListener('input', function() {
                    commentCharCount.textContent = this.value.length;
                    
                    if (this.value.length > 950) {
                        commentCharCount.style.color = 'var(--red)';
                    } else if (this.value.length > 800) {
                        commentCharCount.style.color = 'var(--gold)';
                    } else {
                        commentCharCount.style.color = 'var(--light-grey)';
                    }
                });
            }
        } else {
            commentFormSection.innerHTML = `
                <div class="comment-login-prompt">
                    <p>Yorum yapmak için giriş yapmanız gerekmektedir.</p>
                    <a href="/public/register.php?mode=login" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </a>
                </div>
            `;
        }
        
        // Modal'ı göster
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Eğer yorumlara focus isteniyorsa
        if (focusComments) {
            setTimeout(() => {
                photoComments.scrollIntoView({ behavior: 'smooth' });
            }, 300);
        }
        
    } catch (error) {
        console.error('Photo modal open error:', error);
        showNotification('Fotoğraf detayları yüklenirken hata oluştu.', 'error');
    }
}

// Fotoğraf modal kapat
function closePhotoModal() {
    const modal = document.getElementById('photoModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Fotoğraf yorumlarını yükle
async function loadPhotoComments(photoId) {
    try {
        const response = await fetch(`/public/gallery/actions/get_photo_comments.php?photo_id=${photoId}`);
        const data = await response.json();
        
        const photoComments = document.getElementById('photoComments');
        
        if (data.success && data.comments.length > 0) {
            let commentsHtml = '<h4><i class="fas fa-comments"></i> Yorumlar</h4>';
            
            data.comments.forEach(comment => {
                commentsHtml += `
                    <div class="comment-item" id="comment-${comment.id}">
                        <div class="comment-header">
                            <div class="comment-author">
                                <img src="/${comment.avatar_path || 'assets/logo.png'}" alt="${escapeHtml(comment.username)}">
                                <div class="comment-user-info">
                                    <span class="comment-username user-link" data-user-id="${comment.user_id}" 
                                          style="color: ${comment.user_role_color || '#bd912a'}">
                                        ${escapeHtml(comment.username)}
                                    </span>
                                    <span class="comment-date">${formatTimeAgo(comment.created_at)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="comment-content">
                            ${escapeHtml(comment.comment_text).replace(/\n/g, '<br>')}
                        </div>
                        <div class="comment-actions">
                            ${comment.can_like ? `
                                <button class="comment-action-btn ${comment.user_liked ? 'liked' : ''}" 
                                        onclick="toggleCommentLike(${comment.id})">
                                    <i class="fas fa-heart"></i>
                                    <span class="like-count">${comment.like_count}</span>
                                </button>
                            ` : ''}
                            ${comment.can_edit ? `
                                <button class="comment-action-btn" onclick="editComment(${comment.id})">
                                    <i class="fas fa-edit"></i> Düzenle
                                </button>
                            ` : ''}
                            ${comment.can_delete ? `
                                <button class="comment-action-btn" onclick="confirmCommentDelete(${comment.id})">
                                    <i class="fas fa-trash"></i> Sil
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            photoComments.innerHTML = commentsHtml;
        } else {
            photoComments.innerHTML = `
                <h4><i class="fas fa-comments"></i> Yorumlar</h4>
                <div class="no-comments">
                    <i class="fas fa-comment-slash"></i>
                    <p>Henüz yorum yapılmamış. İlk yorumu siz yapın!</p>
                </div>
            `;
        }
        
    } catch (error) {
        console.error('Comments load error:', error);
        document.getElementById('photoComments').innerHTML = `
            <h4><i class="fas fa-comments"></i> Yorumlar</h4>
            <div class="no-comments">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Yorumlar yüklenirken hata oluştu.</p>
            </div>
        `;
    }
}

// Modal'daki fotoğraf beğeni toggle
async function togglePhotoLikeModal(photoId) {
    await togglePhotoLike(photoId);
    
    // Modal'daki beğeni sayısını güncelle
    const modalLikeBtn = document.querySelector('.photo-action-modal-btn .like-count');
    const gridLikeBtn = document.querySelector(`.photo-item[data-photo-id="${photoId}"] .like-count`);
    
    if (modalLikeBtn && gridLikeBtn) {
        modalLikeBtn.textContent = gridLikeBtn.textContent;
    }
}

// Yorum gönder
async function submitPhotoComment(event, photoId) {
    event.preventDefault();
    
    const form = event.target;
    const textArea = form.querySelector('#commentText');
    const submitBtn = form.querySelector('.btn-comment-submit');
    const originalText = submitBtn.innerHTML;
    
    if (!textArea.value.trim()) {
        showNotification('Yorum boş olamaz.', 'error');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
    
    try {
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        formData.append('photo_id', photoId);
        
        const response = await fetch('/public/gallery/actions/add_photo_comment.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Form'u temizle
            textArea.value = '';
            document.getElementById('comment-char-count').textContent = '0';
            
            // Yorumları yeniden yükle
            loadPhotoComments(photoId);
            
            // Grid'deki yorum sayısını güncelle
            updatePhotoCommentCount(photoId, 1);
            
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Comment submit error:', error);
        showNotification('Yorum gönderilirken hata oluştu.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Grid'deki yorum sayısını güncelle
function updatePhotoCommentCount(photoId, change) {
    const commentBtn = document.querySelector(`.photo-item[data-photo-id="${photoId}"] .comment-count`);
    if (commentBtn) {
        const currentCount = parseInt(commentBtn.textContent) || 0;
        commentBtn.textContent = Math.max(0, currentCount + change);
    }
}

// Fotoğraf silme onayı
function confirmPhotoDelete(photoId) {
    if (confirm('Bu fotoğrafı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yorumlar da silinecektir.')) {
        deletePhoto(photoId);
    }
}

function confirmPhotoDeleteModal(photoId) {
    if (confirm('Bu fotoğrafı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yorumlar da silinecektir.')) {
        deletePhoto(photoId);
    }
}

// Fotoğraf sil
async function deletePhoto(photoId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    try {
        const response = await fetch('/public/gallery/actions/delete_photo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&photo_id=${photoId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Modal'ı kapat
            closePhotoModal();
            
            // Grid'den fotoğrafı kaldır
            const photoItem = document.querySelector(`.photo-item[data-photo-id="${photoId}"]`);
            if (photoItem) {
                photoItem.style.opacity = '0.5';
                photoItem.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    photoItem.remove();
                }, 500);
            }
            
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Photo delete error:', error);
        showNotification('Fotoğraf silinirken hata oluştu.', 'error');
    }
}

// Yardımcı fonksiyonlar
function escapeHtml(text) {
    if (!text) return '';
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return String(text).replace(/[&<>"']/g, function(m) { 
        return map[m]; 
    });
}

function formatTimeAgo(datetime) {
    const time = new Date().getTime() - new Date(datetime).getTime();
    const seconds = Math.floor(time / 1000);
    
    if (seconds < 60) return 'Az önce';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' dakika önce';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' saat önce';
    if (seconds < 604800) return Math.floor(seconds / 86400) + ' gün önce';
    if (seconds < 2592000) return Math.floor(seconds / 604800) + ' hafta önce';
    
    return new Date(datetime).toLocaleDateString('tr-TR');
}

function formatDateTime(datetime) {
    return new Date(datetime).toLocaleString('tr-TR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Notification function
function showNotification(message, type = 'info') {
    // Mevcut notification sistemini kullan
    if (window.forumManager && typeof window.forumManager.showNotification === 'function') {
        window.forumManager.showNotification(message, type);
        return;
    }
    
    // Basit fallback notification sistemi
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10001;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        cursor: pointer;
    `;
    
    // Tip'e göre renk ayarla
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animasyon
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Otomatik kaldır
    const timeoutId = setTimeout(() => {
        removeNotification(notification);
    }, 5000);
    
    // Tıklayınca kapat
    notification.addEventListener('click', () => {
        clearTimeout(timeoutId);
        removeNotification(notification);
    });
    
    function removeNotification(notif) {
        notif.style.opacity = '0';
        notif.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notif.parentNode) {
                notif.parentNode.removeChild(notif);
            }
        }, 300);
    }
}

// Modal dışına tıklayınca kapat
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeUploadModal();
        closePhotoModal();
    }
});

// ESC tuşu ile modal'ları kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUploadModal();
        closePhotoModal();
    }
});