/* view/js/gallery_photos.js - Gallery Photos Specific JavaScript */

// Debug mode - set to false for production
const GALLERY_DEBUG = false;

function debugLog(...args) {
    if (GALLERY_DEBUG) {
        console.log('[Gallery Debug]', ...args);
    }
}

function debugError(...args) {
    if (GALLERY_DEBUG) {
        console.error('[Gallery Error]', ...args);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Debug: Check for undefined values in DOM
    debugGalleryData();
    
    // Gallery specific functionality
    initializeGallery();
});

function debugGalleryData() {
    if (!GALLERY_DEBUG) return;
    
    console.log('[Gallery Debug] Starting DOM validation...');
    
    const images = document.querySelectorAll('.gallery-image');
    images.forEach((img, index) => {
        const src = img.src;
        const alt = img.alt;
        
        if (!src || src.includes('undefined') || src.includes('null')) {
            console.error(`[Gallery Debug] Image ${index} has invalid src:`, src);
        }
        if (!alt || alt.includes('undefined') || alt.includes('null')) {
            console.error(`[Gallery Debug] Image ${index} has invalid alt:`, alt);
        }
        
        console.log(`[Gallery Debug] Image ${index}:`, {
            src: src,
            alt: alt,
            complete: img.complete,
            naturalWidth: img.naturalWidth
        });
    });
    
    const links = document.querySelectorAll('.gallery-link[onclick]');
    links.forEach((link, index) => {
        const onclick = link.getAttribute('onclick');
        if (onclick && (onclick.includes('undefined') || onclick.includes('null'))) {
            console.error(`[Gallery Debug] Link ${index} has undefined in onclick:`, onclick);
        }
    });
    
    console.log('[Gallery Debug] DOM validation complete.');
}

function initializeGallery() {
    // Image error handling (no lazy loading)
    initializeImageErrorHandling();
    
    // Image lightbox functionality
    initializeLightbox();
    
    // Gallery grid masonry effect
    initializeMasonryEffect();
    
    // Category filter animations
    initializeCategoryFilters();
}

function initializeImageErrorHandling() {
    const images = document.querySelectorAll('.gallery-image');
    
    debugLog('Initializing error handling for', images.length, 'images');
    
    images.forEach((img, index) => {
        // Validate current src
        if (!img.src || img.src.includes('undefined') || img.src.includes('null')) {
            debugError(`Image ${index} has invalid src:`, img.src);
            img.src = '/assets/logo.png';
        }
        
        // Add error handler for load failures
        img.addEventListener('error', function(e) {
            debugError('Image load error:', {
                index: index,
                src: this.src,
                alt: this.alt
            });
            
            // Fallback to default image if not already
            if (!this.src.includes('/assets/logo.png')) {
                this.src = '/assets/logo.png';
            }
        });
        
        // Add load success handler
        img.addEventListener('load', function(e) {
            debugLog(`Image ${index} loaded successfully:`, this.src);
            this.classList.add('loaded');
        });
        
        debugLog(`Image ${index}:`, {
            src: img.src,
            alt: img.alt
        });
    });
}

function initializeLightbox() {
    const galleryLinks = document.querySelectorAll('.gallery-link');
    
    debugLog('Initializing lightbox for', galleryLinks.length, 'gallery links');
    
    galleryLinks.forEach((link, index) => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get data from attributes
            const imagePath = this.getAttribute('data-image-path');
            const imageTitle = this.getAttribute('data-image-title');
            const imageDescription = this.getAttribute('data-image-description');
            
            debugLog(`Gallery link ${index} clicked:`, {
                imagePath: imagePath,
                imageTitle: imageTitle,
                imageDescription: imageDescription
            });
            
            // Open modal with data
            openImageModal(imagePath, imageTitle, imageDescription);
        });
        
        // Also handle direct image clicks
        const image = link.querySelector('.gallery-image');
        if (image) {
            image.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Trigger the link click
                link.click();
            });
        }
    });
}

function openLightbox(image) {
    // Get image source (no data-src anymore)
    let imageSrc = image.src;
    
    // Validate image source
    if (!imageSrc || 
        imageSrc.includes('undefined') || 
        imageSrc.includes('null') || 
        imageSrc === '' || 
        typeof imageSrc !== 'string') {
        debugError('Invalid image src for lightbox:', imageSrc);
        imageSrc = '/assets/logo.png';
    }
    
    debugLog('Opening lightbox for image:', imageSrc);
    
    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox-overlay';
    lightbox.innerHTML = `
        <div class="lightbox-container">
            <div class="lightbox-close">&times;</div>
            <img src="${imageSrc}" alt="${image.alt || 'Fotoğraf'}" class="lightbox-image">
            <div class="lightbox-info">
                <h3>${image.closest('.gallery-item').querySelector('.gallery-title')?.textContent || 'Fotoğraf'}</h3>
                <p>${image.closest('.gallery-item').querySelector('.gallery-description')?.textContent || ''}</p>
            </div>
        </div>
    `;
    
    // Add lightbox styles
    lightbox.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    document.body.appendChild(lightbox);
    document.body.style.overflow = 'hidden';
    
    // Animate in
    setTimeout(() => {
        lightbox.style.opacity = '1';
    }, 10);
    
    // Close functionality
    const closeBtn = lightbox.querySelector('.lightbox-close');
    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });
    
    function closeLightbox() {
        lightbox.style.opacity = '0';
        document.body.style.overflow = '';
        setTimeout(() => {
            document.body.removeChild(lightbox);
        }, 300);
    }
}

function initializeMasonryEffect() {
    const grid = document.querySelector('.gallery-grid');
    if (!grid) return;
    
    debugLog('Initializing masonry effect for gallery grid');
    
    // Simple masonry-like effect - no random heights since we use fixed 750px
    const items = grid.querySelectorAll('.gallery-item');
    
    items.forEach((item, index) => {
        const img = item.querySelector('.gallery-image');
        if (img) {
            // Add subtle animation delay for cascading effect
            item.style.animationDelay = (index * 0.1) + 's';
            item.classList.add('fade-in');
            
            // If image is already loaded, trigger immediately
            if (img.complete && img.naturalWidth > 0) {
                debugLog(`Image ${index} already loaded, triggering animation`);
                item.classList.add('animate-in');
            } else {
                // Wait for load
                img.addEventListener('load', function() {
                    debugLog(`Image ${index} loaded, triggering animation`);
                    item.classList.add('animate-in');
                });
            }
        }
    });
}

function initializeCategoryFilters() {
    const categoryButtons = document.querySelectorAll('[data-category]');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.dataset.category;
            filterGalleryByCategory(category);
            
            // Update active state
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

function filterGalleryByCategory(category) {
    const items = document.querySelectorAll('.gallery-item');
    
    items.forEach(item => {
        const itemCategory = item.dataset.category;
        
        if (category === 'all' || itemCategory === category) {
            item.style.display = 'block';
            item.style.animation = 'fadeInUp 0.3s ease forwards';
        } else {
            item.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => {
                item.style.display = 'none';
            }, 300);
        }
    });
}

// Gallery specific utilities
function preloadGalleryImages() {
    const images = document.querySelectorAll('.gallery-image');
    const imagePromises = [];
    
    images.forEach(img => {
        const promise = new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = resolve;
            image.onerror = reject;
            
            // Get image source (no data-src anymore)
            let imageSrc = img.src;
            
            // Validate image source
            if (!imageSrc || 
                imageSrc.includes('undefined') || 
                imageSrc.includes('null') || 
                imageSrc === '' || 
                typeof imageSrc !== 'string') {
                debugError('Invalid image src for preload:', imageSrc);
                imageSrc = '/assets/logo.png';
            }
            
            debugLog('Preloading image:', imageSrc);
            image.src = imageSrc;
        });
        imagePromises.push(promise);
    });
    
    return Promise.all(imagePromises);
}

function addGalleryAnimations() {
    const items = document.querySelectorAll('.gallery-item');
    
    items.forEach((item, index) => {
        item.style.animationDelay = (index * 0.1) + 's';
        item.classList.add('animate-in');
    });
}

// Image modal function for gallery
function openImageModal(imagePath, title, description) {
    // Debug log to track the issue
    debugLog('openImageModal called with:', {
        imagePath: imagePath,
        title: title,
        description: description,
        imagePathType: typeof imagePath,
        titleType: typeof title,
        descriptionType: typeof description,
        imagePathLength: imagePath ? imagePath.length : 'null',
        titleLength: title ? title.length : 'null'
    });
    
    // More comprehensive validation
    if (!imagePath || 
        imagePath === 'undefined' || 
        imagePath === '' || 
        imagePath === 'null' || 
        typeof imagePath !== 'string' ||
        imagePath.trim() === '') {
        debugError('INVALID IMAGE PATH - openImageModal failed:', {
            provided: imagePath,
            type: typeof imagePath
        });
        // Don't return, use fallback instead
        imagePath = '/assets/logo.png';
    }
    
    // Ensure title and description are strings
    const safeTitle = (title && typeof title === 'string' && title !== 'undefined' && title !== 'null') 
        ? title : 'Fotoğraf';
    const safeDescription = (description && typeof description === 'string' && description !== 'undefined' && description !== 'null') 
        ? description : '';
    
    debugLog('Using safe values:', {
        safeImagePath: imagePath,
        safeTitle: safeTitle,
        safeDescription: safeDescription
    });
    
    const modal = document.createElement('div');
    modal.className = 'image-modal-overlay';
    modal.innerHTML = `
        <div class="image-modal-container">
            <div class="image-modal-close">&times;</div>
            <img src="${imagePath}" alt="${safeTitle}" class="image-modal-image">
            <div class="image-modal-info">
                <h3>${safeTitle}</h3>
                ${safeDescription ? `<p>${safeDescription}</p>` : ''}
            </div>
        </div>
    `;
    
    // Add modal styles
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
        cursor: pointer;
    `;
    
    const container = modal.querySelector('.image-modal-container');
    container.style.cssText = `
        max-width: 90vw;
        max-height: 90vh;
        position: relative;
        cursor: default;
        display: flex;
        flex-direction: column;
        align-items: center;
    `;
    
    const image = modal.querySelector('.image-modal-image');
    image.style.cssText = `
        max-width: 100%;
        max-height: 80vh;
        width: auto;
        height: auto;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        object-fit: contain;
    `;
    
    const closeBtn = modal.querySelector('.image-modal-close');
    closeBtn.style.cssText = `
        position: absolute;
        top: -15px;
        right: -15px;
        background: #EB0000;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 20px;
        font-weight: bold;
        z-index: 10001;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    `;
    
    const info = modal.querySelector('.image-modal-info');
    info.style.cssText = `
        background: #1D1A18;
        padding: 1rem;
        margin-top: 1rem;
        border-radius: 8px;
        border: 1px solid #59524c46;
        text-align: center;
        color: #b3b3b3;
        max-width: 100%;
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    debugLog('Modal added to DOM, animating in...');
    
    // Animate in
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
    
    // Close functionality
    closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        debugLog('Close button clicked');
        closeModal();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            debugLog('Modal background clicked');
            closeModal();
        }
    });
    
    container.addEventListener('click', (e) => {
        e.stopPropagation();
    });
    
    // Keyboard navigation
    const handleKeydown = (e) => {
        if (e.key === 'Escape') {
            debugLog('ESC key pressed, closing modal');
            closeModal();
        }
    };
    
    document.addEventListener('keydown', handleKeydown);
    
    function closeModal() {
        debugLog('Closing modal...');
        modal.style.opacity = '0';
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleKeydown);
        setTimeout(() => {
            if (document.body.contains(modal)) {
                document.body.removeChild(modal);
                debugLog('Modal removed from DOM');
            }
        }, 300);
    }
} 