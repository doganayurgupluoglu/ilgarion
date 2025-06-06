// /gallery/js/gallery.js - Main File

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

    // Modülleri başlat
    GalleryUpload.init();
    GalleryFilters.init();
    GalleryModal.init();
});

// Modal dışına tıklayınca kapat
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        GalleryUpload.closeModal();
        GalleryModal.closePhotoModal();
    }
});

// ESC tuşu ile modal'ları kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        GalleryUpload.closeModal();
        GalleryModal.closePhotoModal();
    }
});

// Global functions
window.openUploadModal = () => GalleryUpload.openModal();
window.closeUploadModal = () => GalleryUpload.closeModal();
window.openPhotoModal = (photoId, focusComments) => GalleryModal.openPhotoModal(photoId, focusComments);
window.closePhotoModal = () => GalleryModal.closePhotoModal();
window.togglePhotoLike = (photoId) => GalleryActions.togglePhotoLike(photoId);
window.toggleCommentLike = (commentId) => GalleryActions.toggleCommentLike(commentId);
window.editComment = (commentId) => GalleryActions.editComment(commentId);
window.confirmCommentDelete = (commentId) => GalleryActions.confirmCommentDelete(commentId);
window.confirmPhotoDelete = (photoId) => GalleryActions.confirmPhotoDelete(photoId);
window.confirmPhotoDeleteModal = (photoId) => GalleryActions.confirmPhotoDeleteModal(photoId);
window.updateGalleryFilters = () => GalleryFilters.updateFilters();
window.clearUserFilter = () => GalleryFilters.clearUserFilter();