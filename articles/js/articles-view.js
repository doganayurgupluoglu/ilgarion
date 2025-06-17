/**
 * Articles View JavaScript
 * /articles/js/articles-view.js
 */

'use strict';

class ArticleViewer {
    constructor() {
        this.isFullscreen = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkIframeLoad();
    }

    setupEventListeners() {
        // ESC tuşu ile fullscreen'den çık
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isFullscreen) {
                this.exitFullscreen();
            }
        });

        // Iframe yükleme durumunu izle
        const iframe = document.getElementById('documentIframe');
        if (iframe) {
            iframe.addEventListener('load', () => {
                this.onIframeLoad();
            });

            iframe.addEventListener('error', () => {
                this.onIframeError();
            });
            
            // Timeout kontrolü - 15 saniye sonra hala yüklenmediyse hata göster
            setTimeout(() => {
                const loadingOverlay = document.querySelector('.loading-overlay');
                if (loadingOverlay && loadingOverlay.style.display !== 'none') {
                    this.onIframeError();
                }
            }, 15000);
        }
    }

    checkIframeLoad() {
        const iframe = document.getElementById('documentIframe');
        if (iframe) {
            // Loading indicator göster - sadece sayfa ilk yüklendiğinde
            if (!iframe.src || iframe.src === 'about:blank') {
                this.showLoadingIndicator();
            }
        }
    }

    onIframeLoad() {
        this.hideLoadingIndicator();
    }

    onIframeError() {
        this.hideLoadingIndicator();
        
        // Hata mesajı göster
        const viewerContainer = document.getElementById('viewerContainer');
        if (viewerContainer) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'iframe-error-message';
            errorDiv.innerHTML = `
                <div class="error-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Döküman Yüklenemedi</h3>
                    <p>PDF önizlemesi yüklenirken bir hata oluştu. Muhtemel nedenler:</p>
                    <ul>
                        <li>Dosya erişilebilir değil</li>
                        <li>Google Docs Viewer sorunu</li>
                        <li>Ağ bağlantısı problemi</li>
                    </ul>
                    <button onclick="location.reload()" class="retry-btn">
                        <i class="fas fa-redo"></i> Sayfayı Yenile
                    </button>
                </div>
            `;
            
            // Mevcut iframe'i gizle
            const iframe = document.getElementById('documentIframe');
            if (iframe) {
                iframe.style.display = 'none';
            }
            
            viewerContainer.appendChild(errorDiv);
        }
    }

    showLoadingIndicator() {
        const viewerContainer = document.getElementById('viewerContainer');
        if (viewerContainer && !viewerContainer.querySelector('.loading-overlay')) {
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = `
                <div class="loading-content">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <p>Döküman yükleniyor...</p>
                </div>
            `;
            viewerContainer.appendChild(loadingOverlay);
        }
    }

    hideLoadingIndicator() {
        const loadingOverlay = document.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.remove();
        }
    }

    showAlert(message, type = 'info') {
        showAlert(message, type); // Global function'ı kullan
    }

    escapeHtml(text) {
        return escapeHtml(text); // Global function'ı kullan
    }
}

// Global functions
function toggleLike(articleId) {
    const likeBtn = document.getElementById('likeBtn');
    const likeText = document.getElementById('likeText');
    const likeCount = document.getElementById('likeCount');
    
    if (!likeBtn || !likeText || !likeCount) {
        console.error('Like button elements not found');
        return;
    }

    // Disable button during request
    likeBtn.disabled = true;
    
    // Show loading on button itself
    const originalContent = likeBtn.innerHTML;
    likeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>İşleniyor...</span>';
    
    const formData = new FormData();
    formData.append('article_id', articleId);
    formData.append('action', 'toggle_like');

    fetch('/articles/actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text(); // Önce text olarak al
    })
    .then(responseText => {
        console.log('Raw response:', responseText); // Ham response'u log'la
        
        try {
            const data = JSON.parse(responseText);
            console.log('Parsed data:', data);
            
            if (data.success) {
                // Update UI
                const isLiked = data.liked;
                const newCount = data.like_count;
                
                // Restore button first
                likeBtn.innerHTML = `
                    <i class="fas fa-heart"></i>
                    <span id="likeText">${isLiked ? 'Beğenildi' : 'Beğen'}</span>
                    <span id="likeCount">(${newCount})</span>
                `;
                
                // Update classes
                if (isLiked) {
                    likeBtn.classList.add('liked');
                } else {
                    likeBtn.classList.remove('liked');
                }
                
                // Show feedback
                showAlert(isLiked ? 'Makale beğenildi!' : 'Beğeni kaldırıldı.', 'success');
            } else {
                // Restore original content on error
                likeBtn.innerHTML = originalContent;
                showAlert(data.message || 'Bir hata oluştu.', 'error');
            }
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response was not JSON:', responseText);
            
            // Restore original content
            likeBtn.innerHTML = originalContent;
            showAlert('Sunucu hatası oluştu. Lütfen sayfayı yenileyin.', 'error');
        }
    })
    .catch(error => {
        console.error('Like error:', error);
        // Restore original content on error
        likeBtn.innerHTML = originalContent;
        showAlert('Ağ hatası oluştu.', 'error');
    })
    .finally(() => {
        likeBtn.disabled = false;
    });
}

function shareArticle() {
    const url = window.location.href;
    const title = document.querySelector('.article-title')?.textContent || 'Makale';
    
    if (navigator.share) {
        navigator.share({
            title: title,
            text: 'Bu makaleyi sizinle paylaşmak istiyorum',
            url: url
        }).catch(error => {
            console.error('Share error:', error);
            fallbackShare(url);
        });
    } else {
        fallbackShare(url);
    }
}

function fallbackShare(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            const viewer = new ArticleViewer();
            viewer.showAlert('Link kopyalandı!', 'success');
        }).catch(() => {
            showShareModal(url);
        });
    } else {
        showShareModal(url);
    }
}

function showShareModal(url) {
    const modal = document.createElement('div');
    modal.className = 'share-modal';
    modal.innerHTML = `
        <div class="share-modal-overlay"></div>
        <div class="share-modal-content">
            <div class="share-modal-header">
                <h3>Makaleyi Paylaş</h3>
                <button onclick="this.closest('.share-modal').remove()" class="share-modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="share-modal-body">
                <p>Aşağıdaki linki kopyalayarak paylaşabilirsiniz:</p>
                <div class="share-url-container">
                    <input type="text" value="${url}" readonly class="share-url-input" id="shareUrlInput">
                    <button onclick="copyShareUrl()" class="share-copy-btn">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Modal overlay click to close
    modal.querySelector('.share-modal-overlay').addEventListener('click', () => {
        modal.remove();
    });
    
    // ESC to close
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
    
    // Auto select URL
    const input = document.getElementById('shareUrlInput');
    if (input) {
        input.select();
    }
}

function copyShareUrl() {
    const input = document.getElementById('shareUrlInput');
    if (input) {
        input.select();
        document.execCommand('copy');
        
        const copyBtn = document.querySelector('.share-copy-btn');
        if (copyBtn) {
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                copyBtn.innerHTML = originalText;
            }, 2000);
        }
        
        const viewer = new ArticleViewer();
        viewer.showAlert('Link kopyalandı!', 'success');
    }
}

function recordDownload(articleId) {
    // Download sayısını artır
    const formData = new FormData();
    formData.append('article_id', articleId);
    formData.append('action', 'record_download');

    fetch('/articles/actions.php', {
        method: 'POST',
        body: formData
    }).catch(error => {
        console.error('Download record error:', error);
    });
}

function deleteArticle(articleId) {
    if (!confirm('Bu makaleyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        return;
    }

    const deleteBtn = document.querySelector('.btn-delete');
    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Siliniyor...</span>';
    }

    const formData = new FormData();
    formData.append('article_id', articleId);
    formData.append('action', 'delete_article');

    fetch('/articles/actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Makale başarıyla silindi. Yönlendiriliyorsunuz...', 'success');
            
            setTimeout(() => {
                window.location.href = '/articles/';
            }, 2000);
        } else {
            showAlert(data.message || 'Silme işlemi başarısız.', 'error');
            
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> <span>Sil</span>';
            }
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showAlert('Ağ hatası oluştu.', 'error');
        
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> <span>Sil</span>';
        }
    });
}

// Global showAlert function (ArticleViewer'dan bağımsız)
function showAlert(message, type = 'info') {
    // Mevcut alert'leri kaldır
    const existingAlerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    existingAlerts.forEach(alert => alert.remove());

    // Yeni alert oluştur
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    const iconMap = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    const icon = iconMap[type] || iconMap.info;
    
    alertDiv.innerHTML = `
        <i class="${icon}"></i>
        ${escapeHtml(message)}
    `;

    // Site container'ı bul
    const siteContainer = document.querySelector('.site-container');
    if (!siteContainer) {
        console.error('Site container not found');
        return;
    }

    // Breadcrumb'ı bul
    const breadcrumb = document.querySelector('.breadcrumb');
    
    if (breadcrumb && breadcrumb.parentNode === siteContainer) {
        // Breadcrumb'dan sonra ekle
        const nextElement = breadcrumb.nextElementSibling;
        if (nextElement) {
            siteContainer.insertBefore(alertDiv, nextElement);
        } else {
            siteContainer.appendChild(alertDiv);
        }
    } else {
        // Breadcrumb yoksa container'ın başına ekle
        if (siteContainer.firstChild) {
            siteContainer.insertBefore(alertDiv, siteContainer.firstChild);
        } else {
            siteContainer.appendChild(alertDiv);
        }
    }

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);

    // Alert'e scroll
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Global escapeHtml function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleFullscreen() {
    const viewer = window.articleViewer;
    if (viewer.isFullscreen) {
        viewer.exitFullscreen();
    } else {
        viewer.enterFullscreen();
    }
}

// ArticleViewer class extension for fullscreen
ArticleViewer.prototype.enterFullscreen = function() {
    const viewerContainer = document.getElementById('viewerContainer');
    if (!viewerContainer) return;

    viewerContainer.classList.add('fullscreen');
    document.body.style.overflow = 'hidden';
    this.isFullscreen = true;

    // Update button text
    const fullscreenBtn = document.querySelector('[onclick="toggleFullscreen()"]');
    if (fullscreenBtn) {
        fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i> Çık';
    }
};

ArticleViewer.prototype.exitFullscreen = function() {
    const viewerContainer = document.getElementById('viewerContainer');
    if (!viewerContainer) return;

    viewerContainer.classList.remove('fullscreen');
    document.body.style.overflow = '';
    this.isFullscreen = false;

    // Update button text
    const fullscreenBtn = document.querySelector('[onclick="toggleFullscreen()"]');
    if (fullscreenBtn) {
        fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i> Tam Ekran';
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.articleViewer = new ArticleViewer();
});

// CSS for share modal and loading overlay
const additionalCSS = `
<style>
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(29, 26, 24, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.loading-content {
    text-align: center;
    color: var(--lighter-grey);
}

.loading-spinner {
    font-size: 3rem;
    color: var(--gold);
    margin-bottom: 1rem;
}

.loading-content p {
    font-size: 1.1rem;
    margin: 0;
    font-family: var(--font);
}

.share-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.share-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.share-modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    position: relative;
    animation: slideUp 0.3s ease;
}

.share-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.share-modal-header h3 {
    margin: 0;
    color: var(--gold);
    font-size: 1.3rem;
    font-family: var(--font);
}

.share-modal-close {
    background: none;
    border: none;
    color: var(--light-grey);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.share-modal-close:hover {
    background: var(--transparent-red);
    color: var(--red);
}

.share-modal-body {
    padding: 1.5rem;
}

.share-modal-body p {
    color: var(--light-grey);
    margin-bottom: 1rem;
    font-family: var(--font);
}

.share-url-container {
    display: flex;
    gap: 0.5rem;
}

.share-url-input {
    flex: 1;
    background: var(--card-bg-3);
    border: 1px solid var(--border-1);
    border-radius: 6px;
    padding: 0.75rem;
    color: var(--lighter-grey);
    font-family: var(--font);
    font-size: 0.9rem;
}

.share-url-input:focus {
    outline: none;
    border-color: var(--gold);
}

.share-copy-btn {
    background: var(--gold);
    color: var(--charcoal);
    border: none;
    border-radius: 6px;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.share-copy-btn:hover {
    background: var(--light-gold);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0; 
        transform: translateY(20px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}
</style>
`;

// Inject additional CSS
document.head.insertAdjacentHTML('beforeend', additionalCSS);