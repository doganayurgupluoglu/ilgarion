// /gallery/js/gallery-modal.js - Modal Module

const GalleryModal = {
    init() {
        // Modal initialization if needed
    },

    async openPhotoModal(photoId, focusComments = false) {
        try {
            // Fotoğraf detaylarını yükle
            const response = await fetch(`actions/get_photo_details.php?photo_id=${photoId}`);
            const data = await response.json();
            
            if (!data.success) {
                GalleryUtils.showNotification(data.message || 'Fotoğraf yüklenemedi.', 'error');
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
                    ${GalleryUtils.escapeHtml(data.photo.username)}
                </span> tarafından ${GalleryUtils.formatTimeAgo(data.photo.uploaded_at)} paylaşıldı
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
                            ${GalleryUtils.escapeHtml(data.photo.username)}
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tarih:</span>
                    <span class="detail-value">${GalleryUtils.formatDateTime(data.photo.uploaded_at)}</span>
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
                        ${GalleryUtils.escapeHtml(data.photo.description)}
                    </div>
                ` : ''}
                <div class="photo-actions-modal">
                    ${data.photo.can_like ? `
                        <button class="photo-action-modal-btn ${data.photo.user_liked ? 'liked' : ''}" 
                                onclick="togglePhotoLike(${photoId})">
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
            this.loadPhotoComments(photoId);
            
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
                        <a href="register.php?mode=login" class="btn-primary">
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
            GalleryUtils.showNotification('Fotoğraf detayları yüklenirken hata oluştu.', 'error');
        }
    },

    closePhotoModal() {
        const modal = document.getElementById('photoModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },

    async loadPhotoComments(photoId) {
        try {
            const response = await fetch(`actions/get_photo_comments.php?photo_id=${photoId}`);
            const data = await response.json();
            
            const photoComments = document.getElementById('photoComments');
            
            if (data.success && data.comments.length > 0) {
                let commentsHtml = '<h4><i class="fas fa-comments"></i> Yorumlar</h4>';
                
                data.comments.forEach(comment => {
                    commentsHtml += `
                        <div class="comment-item" id="comment-${comment.id}">
                            <div class="comment-header">
                                <div class="comment-author">
                                    <img src="/${comment.avatar_path || 'assets/logo.png'}" alt="${GalleryUtils.escapeHtml(comment.username)}">
                                    <div class="comment-user-info">
                                        <span class="comment-username user-link" data-user-id="${comment.user_id}" 
                                              style="color: ${comment.user_role_color || '#bd912a'}">
                                            ${GalleryUtils.escapeHtml(comment.username)}
                                        </span>
                                        <span class="comment-date">${GalleryUtils.formatTimeAgo(comment.created_at)}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="comment-content">
                                ${GalleryUtils.escapeHtml(comment.comment_text).replace(/\n/g, '<br>')}
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
};

window.submitPhotoComment = async (event, photoId) => {
    event.preventDefault();
    
    const form = event.target;
    const textArea = form.querySelector('#commentText');
    const submitBtn = form.querySelector('.btn-comment-submit');
    const originalText = submitBtn.innerHTML;
    
    if (!textArea.value.trim()) {
        GalleryUtils.showNotification('Yorum boş olamaz.', 'error');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
    
    try {
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        formData.append('photo_id', photoId);
        
        const response = await fetch('actions/add_photo_comment.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            GalleryUtils.showNotification(data.message, 'success');
            
            // Form'u temizle
            textArea.value = '';
            document.getElementById('comment-char-count').textContent = '0';
            
            // Yorumları yeniden yükle
            GalleryModal.loadPhotoComments(photoId);
            
            // Grid'deki yorum sayısını güncelle
            GalleryUtils.updatePhotoCommentCount(photoId, 1);
            
        } else {
            GalleryUtils.showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Comment submit error:', error);
        GalleryUtils.showNotification('Yorum gönderilirken hata oluştu.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
};