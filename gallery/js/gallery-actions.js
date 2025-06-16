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
        // Silme işlemi iptal edilirse bir şey yapma
        return;
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

/**
 * Toggles a comment between view and edit mode.
 * @param {number} commentId - The ID of the comment to edit.
 */
function toggleCommentEdit(commentId) {
    const commentDiv = document.getElementById(`comment-${commentId}`);
    if (!commentDiv) {
        console.error(`[Gallery Error] toggleCommentEdit: Comment container #comment-${commentId} not found.`);
        return;
    }

    const textP = commentDiv.querySelector('.comment-text');
    const editContainer = commentDiv.querySelector('.comment-edit-container');

    // DEFENSIVE CODING: Prevent crash if the HTML structure is wrong.
    if (!textP || !editContainer) {
        console.error(`[Gallery Error] toggleCommentEdit: HTML for comment #${commentId} is malformed. It's missing '.comment-text' or the crucial '.comment-edit-container'. This is an error in 'renderComments' function.`);
        console.log("Faulty HTML for this comment:", commentDiv.innerHTML);
        alert("Yorum düzenlenirken bir hata oluştu. Lütfen konsolu kontrol edin.");
        return;
    }

    // If already in edit mode, cancel
    if (editContainer.style.display !== 'none') {
        editContainer.style.display = 'none';
        textP.style.display = 'block';
        editContainer.innerHTML = ''; // Clear the edit form
        return;
    }

    // Switch to edit mode
    textP.style.display = 'none';
    editContainer.style.display = 'block';

    const currentText = textP.innerHTML.replace(/<br\s*[\/]?>/gi, "\n"); // br2nl

    editContainer.innerHTML = `
        <textarea class="comment-edit-textarea" rows="3">${currentText}</textarea>
        <div class="comment-edit-actions">
            <button class="btn-primary btn-sm" onclick="saveCommentEdit(${commentId})">
                <i class="fas fa-save"></i> Kaydet
            </button>
            <button class="btn-secondary btn-sm" onclick="toggleCommentEdit(${commentId})">
                <i class="fas fa-times"></i> İptal
            </button>
        </div>
    `;

    const textarea = editContainer.querySelector('textarea');
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
}

/**
 * Saves the edited comment content via an API call.
 * @param {number} commentId - The ID of the comment to save.
 */
async function saveCommentEdit(commentId) {
    const commentDiv = document.getElementById(`comment-${commentId}`);
    if (!commentDiv) {
        console.error(`[Gallery Error] saveCommentEdit: Comment container #comment-${commentId} not found.`);
        return;
    }
    const editContainer = commentDiv.querySelector('.comment-edit-container');
    const textarea = editContainer ? editContainer.querySelector('.comment-edit-textarea') : null;
    
    if (!textarea) {
        console.error(`[Gallery Error] saveCommentEdit: Textarea not found for comment #${commentId}.`);
        alert("Yorum kaydedilirken bir hata oluştu. Lütfen konsolu kontrol edin.");
        return;
    }

    const newContent = textarea.value;
    const saveButton = editContainer.querySelector('.btn-primary');
    const originalButtonText = saveButton.innerHTML;

    saveButton.disabled = true;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';

    try {
        const response = await fetch('/gallery/actions/edit_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId,
                content: newContent,
                csrf_token: csrfToken // Use the global csrfToken
            })
        });

        const result = await response.json();

        if (result.success) {
            const textP = commentDiv.querySelector('.comment-text');
            // Use the sanitized content returned from the server
            textP.innerHTML = result.new_content_html; 
            
            // Hide edit form and show the updated text
            editContainer.style.display = 'none';
            textP.style.display = 'block';
            editContainer.innerHTML = ''; // Clean up the edit form
            
            GalleryUtils.showNotification('Yorum başarıyla güncellendi.', 'success');
        } else {
            GalleryUtils.showNotification(result.message || 'Yorum güncellenemedi.', 'error');
        }
    } catch (error) {
        console.error('Error saving comment:', error);
        GalleryUtils.showNotification('Bir ağ hatası oluştu.', 'error');
    } finally {
        // Restore button state
        saveButton.disabled = false;
        saveButton.innerHTML = originalButtonText;
    }
}