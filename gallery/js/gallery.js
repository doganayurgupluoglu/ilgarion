// /gallery/js/gallery.js - Main entry point

/**
 * URL hash'ini analiz eder ve fotoğraf modalını buna göre yönetir.
 * Bu fonksiyon, sayfa yüklendiğinde ve hash değiştiğinde çağrılır.
 */
function handleGalleryHash() {
    const hash = window.location.hash;
    const modal = document.getElementById('photoModal');
    if (!modal) return;

    if (hash && hash.startsWith('#photo-')) {
        const parts = hash.substring(7).split('-comment');
        const photoId = parseInt(parts[0], 10);
        const focusComment = parts.length > 1;

        if (!isNaN(photoId)) {
            // Modalı aç veya zaten açıksa odaklanmayı yönet
            openPhotoModal(photoId, focusComment);
        }
    } else {
        // Hash boş veya geçersizse, açık olan modalı kapat
        const isModalOpen = modal.style.display !== 'none';
        if (isModalOpen) {
            closePhotoModal();
        }
    }
}

/**
 * Sayfa yüklendiğinde tüm olay dinleyicilerini ve başlangıç ​​işlevlerini ayarlar.
 */
function initializeGallery() {
    // CSRF token'ı global bir değişkene ata (diğer script'ler kullanabilir)
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    window.csrfToken = csrfInput ? csrfInput.value : '';

    // Kullanıcı popover'larını başlat (gallery-utils.js'den)
    if (typeof initializeUserPopovers === 'function') {
        initializeUserPopovers();
    }

    // Filtre olay dinleyicileri (gallery-filters.js'den)
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', updateGalleryFilters);
    }

    const userFilterInput = document.getElementById('user-filter');
    if (userFilterInput) {
        userFilterInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                updateGalleryFilters();
            }
        });
    }
    
    const clearFilterBtn = document.querySelector('.btn-clear-filter');
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', () => {
             // Input'u temizle ve filtreleri güncelle
            if(userFilterInput) userFilterInput.value = '';
            updateGalleryFilters();
        });
    }

    // Upload modal butonu (gallery-upload.js'den)
    const uploadBtn = document.querySelector('.btn-upload');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', openUploadModal);
    }

    // URL Hash yönetimi
    handleGalleryHash(); // Sayfa ilk yüklendiğinde hash'i kontrol et
    window.addEventListener('hashchange', handleGalleryHash); // Hash değişikliklerini dinle
}

// DOM yüklendiğinde galeriyi başlat
document.addEventListener('DOMContentLoaded', initializeGallery);

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

/**
 * -------------------------------------------------------------------------
 * URL Hash Based Modal Control
 * -------------------------------------------------------------------------
 * Handles opening/closing the modal based on the URL hash.
 * This allows for direct linking to photos and back/forward browser navigation.
 */

// Function to parse the hash and control the modal
function handleHashChange() {
    // If the hash was changed by our own code, just reset the flag and do nothing.
    if (window.isProgrammaticHashChange) {
        window.isProgrammaticHashChange = false;
        return;
    }

    const hash = window.location.hash;
    const modal = document.getElementById('photoModal');

    if (hash && hash.startsWith('#photo-')) {
        const parts = hash.substring(7).split('-comment');
        const photoId = parseInt(parts[0], 10);
        const focusComment = parts.length > 1;

        if (!isNaN(photoId)) {
            // Open the modal if a valid photo ID is found in the hash
            openPhotoModal(photoId, focusComment);
        }
    } else {
        // If there's no valid hash, close the modal if it's open
        if (modal && modal.style.display !== 'none') {
            closePhotoModal();
        }
    }
}

// Listen for hash changes (back/forward browser buttons)
window.addEventListener('hashchange', handleHashChange);

// Check the hash when the page loads for the first time
document.addEventListener('DOMContentLoaded', () => {
    // Use a small timeout to ensure the rest of the page is ready
    setTimeout(handleHashChange, 100);
});