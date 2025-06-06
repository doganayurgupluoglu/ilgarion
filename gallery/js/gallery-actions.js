// gallery/js/gallery-actions.js - Actions Module

const GalleryActions = {
    async togglePhotoLike(photoId) {
        if (!csrfToken) {
            GalleryUtils.showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
            return;
        }

        // Hem grid'deki hem de modal'daki butonları bul
        const gridLikeBtn = document.querySelector(`.photo-item[data-photo-id="${photoId}"] .like-btn`);
        const modalLikeBtn = document.querySelector('.photo-action-modal-btn:not(.delete)');
        
        const likeBtns = [gridLikeBtn, modalLikeBtn].filter(btn => btn !== null);
        
        if (likeBtns.length === 0) return;

        // Tüm butonları disable et ve loading göster
        const originalTexts = [];
        likeBtns.forEach((btn, index) => {
            originalTexts[index] = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        });

        try {
            const response = await fetch('actions/toggle_photo_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&photo_id=${photoId}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Grid'deki butonu güncelle
                if (gridLikeBtn) {
                    const gridLikeCount = gridLikeBtn.querySelector('.like-count');
                    
                    if (data.liked) {
                        gridLikeBtn.classList.add('liked');
                    } else {
                        gridLikeBtn.classList.remove('liked');
                    }
                    
                    if (gridLikeCount) {
                        gridLikeCount.textContent = data.like_count;
                        gridLikeCount.classList.add('updated');
                        setTimeout(() => gridLikeCount.classList.remove('updated'), 400);
                    }
                    
                    // Grid butonunun orijinal içeriğini güncelle
                    gridLikeBtn.innerHTML = `<i class="fas fa-heart"></i><span class="like-count">${data.like_count}</span>`;
                }
                
                // Modal'daki butonu güncelle
                if (modalLikeBtn) {
                    const modalLikeCount = modalLikeBtn.querySelector('.like-count');
                    
                    if (data.liked) {
                        modalLikeBtn.classList.add('liked');
                    } else {
                        modalLikeBtn.classList.remove('liked');
                    }
                    
                    if (modalLikeCount) {
                        modalLikeCount.textContent = data.like_count;
                        modalLikeCount.classList.add('updated');
                        setTimeout(() => modalLikeCount.classList.remove('updated'), 400);
                    }
                    
                    // Modal butonunun orijinal içeriğini güncelle
                    modalLikeBtn.innerHTML = `<i class="fas fa-heart"></i><span class="like-count">${data.like_count}</span>`;
                }
                
                // Photo details'deki like count'u da güncelle
                const detailLikeValue = document.querySelector('.detail-item .detail-value');
                if (detailLikeValue && detailLikeValue.closest('.detail-item').querySelector('.detail-label').textContent.includes('Beğeni')) {
                    detailLikeValue.textContent = data.like_count;
                }
                
                GalleryUtils.showNotification(data.message, 'success');
            } else {
                // Hata durumunda orijinal içerikleri geri yükle
                likeBtns.forEach((btn, index) => {
                    btn.innerHTML = originalTexts[index];
                });
                GalleryUtils.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Photo like toggle error:', error);
            // Hata durumunda orijinal içerikleri geri yükle
            likeBtns.forEach((btn, index) => {
                btn.innerHTML = originalTexts[index];
            });
            GalleryUtils.showNotification('Beğeni işlemi başarısız oldu.', 'error');
        } finally {
            // Butonları tekrar aktif et
            likeBtns.forEach(btn => {
                btn.disabled = false;
            });
        }
    },

    confirmPhotoDelete(photoId) {
        if (confirm('Bu fotoğrafı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yorumlar da silinecektir.')) {
            this.deletePhoto(photoId);
        }
    },

    confirmPhotoDeleteModal(photoId) {
        if (confirm('Bu fotoğrafı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yorumlar da silinecektir.')) {
            this.deletePhoto(photoId);
        }
    },

    async deletePhoto(photoId) {
        if (!csrfToken) {
            GalleryUtils.showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
            return;
        }

        try {
            const response = await fetch('actions/delete_photo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&photo_id=${photoId}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                GalleryUtils.showNotification(data.message, 'success');
                
                // Modal'ı kapat
                GalleryModal.closePhotoModal();
                
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
                GalleryUtils.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Photo delete error:', error);
            GalleryUtils.showNotification('Fotoğraf silinirken hata oluştu.', 'error');
        }
    },

    async toggleCommentLike(commentId) {
        if (!csrfToken) {
            GalleryUtils.showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
            return;
        }

        const likeBtn = document.querySelector(`[onclick*="toggleCommentLike(${commentId})"]`);
        if (!likeBtn) return;

        const originalText = likeBtn.innerHTML;
        likeBtn.disabled = true;
        likeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            // Önce test endpoint'ini dene
            const testResponse = await fetch('actions/toggle_comment_like.php?test=1');
            if (!testResponse.ok) {
                throw new Error(`Test request failed: ${testResponse.status}`);
            }
            
            const testData = await testResponse.json();
            console.log('Test response:', testData);

            // Asıl isteği gönder
            const response = await fetch('actions/toggle_comment_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&comment_id=${commentId}`
            });
            
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Response'un JSON olup olmadığını kontrol et
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response received:', text);
                throw new Error('Sunucudan geçersiz yanıt alındı. Lütfen sayfayı yenileyin.');
            }
            
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
                
                GalleryUtils.showNotification(data.message, 'success');
            } else {
                GalleryUtils.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Comment like toggle error:', error);
            if (error.message.includes('JSON')) {
                GalleryUtils.showNotification('Sunucu hatası. Lütfen sayfayı yenileyin ve tekrar deneyin.', 'error');
            } else {
                GalleryUtils.showNotification('Yorum beğeni işlemi başarısız oldu.', 'error');
            }
        } finally {
            likeBtn.disabled = false;
            likeBtn.innerHTML = originalText;
        }
    },

    editComment(commentId) {
        // Yorum düzenleme fonksiyonu - gelecekte implement edilebilir
        GalleryUtils.showNotification('Yorum düzenleme özelliği yakında eklenecek.', 'info');
    },

    confirmCommentDelete(commentId) {
        if (confirm('Bu yorumu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
            this.deleteComment(commentId);
        }
    },

    async deleteComment(commentId) {
        if (!csrfToken) {
            GalleryUtils.showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
            return;
        }

        try {
            const response = await fetch('actions/delete_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&comment_id=${commentId}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                GalleryUtils.showNotification(data.message, 'success');
                
                // Yorumu DOM'dan kaldır
                const commentElement = document.getElementById(`comment-${commentId}`);
                if (commentElement) {
                    commentElement.style.opacity = '0.5';
                    commentElement.style.transform = 'translateY(-10px)';
                    
                    setTimeout(() => {
                        commentElement.remove();
                    }, 300);
                }
                
                // Yorum sayısını güncelle
                GalleryUtils.updatePhotoCommentCount(data.photo_id, -1);
                
            } else {
                GalleryUtils.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Comment delete error:', error);
            GalleryUtils.showNotification('Yorum silinirken hata oluştu.', 'error');
        }
    }
};