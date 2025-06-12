// view/js/view_common.js - View Modülleri Ortak JavaScript

/**
 * View Common Utilities
 */
class ViewCommon {
    constructor() {
        this.initializeCommonFeatures();
    }

    /**
     * Ortak özellikleri başlat
     */
    initializeCommonFeatures() {
        this.initializeAnimations();
        this.initializeTooltips();
        this.initializeLazyLoading();
        this.initializeResponsive();
    }

    /**
     * Sayfa animasyonlarını başlat
     */
    initializeAnimations() {
        // Fade-in animasyonları için intersection observer
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Animasyon yapılacak elementleri gözlemle
        document.querySelectorAll('.view-card, .view-header, .view-filters').forEach(el => {
            observer.observe(el);
        });
    }

    /**
     * Tooltip'leri başlat
     */
    initializeTooltips() {
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip.bind(this));
            element.addEventListener('mouseleave', this.hideTooltip.bind(this));
        });
    }

    /**
     * Tooltip göster
     */
    showTooltip(e) {
        const element = e.target;
        const title = element.getAttribute('title');
        
        if (!title) return;

        // Title'ı geçici olarak kaldır
        element.setAttribute('data-original-title', title);
        element.removeAttribute('title');

        // Tooltip elementini oluştur
        const tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.textContent = title;
        document.body.appendChild(tooltip);

        // Tooltip pozisyonunu ayarla
        const rect = element.getBoundingClientRect();
        tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';

        // Tooltip'i göster
        setTimeout(() => tooltip.classList.add('show'), 10);
    }

    /**
     * Tooltip'i gizle
     */
    hideTooltip(e) {
        const element = e.target;
        const originalTitle = element.getAttribute('data-original-title');
        
        if (originalTitle) {
            element.setAttribute('title', originalTitle);
            element.removeAttribute('data-original-title');
        }

        // Tooltip'i kaldır
        const tooltip = document.querySelector('.custom-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    /**
     * Lazy loading için resim yükleme
     */
    initializeLazyLoading() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const src = img.getAttribute('data-src');
                    
                    if (src) {
                        img.setAttribute('src', src);
                        img.removeAttribute('data-src');
                        img.classList.remove('lazy-load');
                        img.classList.add('lazy-loaded');
                    }
                    
                    observer.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    /**
     * Responsive davranışları başlat
     */
    initializeResponsive() {
        let resizeTimeout;
        
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.handleResize();
            }, 250);
        });

        // İlk yükleme
        this.handleResize();
    }

    /**
     * Resize olayını yönet
     */
    handleResize() {
        const width = window.innerWidth;
        
        // Mobile sidebar toggle
        if (width <= 968) {
            this.addMobileSidebarToggle();
        } else {
            this.removeMobileSidebarToggle();
        }
    }

    /**
     * Mobile sidebar toggle ekle
     */
    addMobileSidebarToggle() {
        const sidebar = document.querySelector('.view-profile-sidebar, .profile-sidebar');
        const container = document.querySelector('.view-container, .profile-container');
        
        if (!sidebar || !container) return;

        // Toggle button'ı var mı kontrol et
        let toggleBtn = document.querySelector('.mobile-sidebar-toggle');
        
        if (!toggleBtn) {
            toggleBtn = document.createElement('button');
            toggleBtn.className = 'mobile-sidebar-toggle';
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            toggleBtn.setAttribute('aria-label', 'Sidebar\'ı Aç/Kapat');
            
            // Toggle fonksiyonu
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
                toggleBtn.classList.toggle('active');
            });

            // Header'a ekle veya container'ın başına ekle
            const header = document.querySelector('.view-header');
            if (header) {
                header.appendChild(toggleBtn);
            } else {
                container.insertBefore(toggleBtn, container.firstChild);
            }
        }

        // Sidebar dışına tıklamada kapat
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
                toggleBtn.classList.remove('active');
            }
        });
    }

    /**
     * Mobile sidebar toggle kaldır
     */
    removeMobileSidebarToggle() {
        const toggleBtn = document.querySelector('.mobile-sidebar-toggle');
        const sidebar = document.querySelector('.view-profile-sidebar, .profile-sidebar');
        
        if (toggleBtn) {
            toggleBtn.remove();
        }
        
        if (sidebar) {
            sidebar.classList.remove('mobile-open');
        }
    }

    /**
     * Loading state göster
     */
    showLoading(container) {
        const loadingHtml = `
            <div class="view-loading">
                <div class="loading-spinner"></div>
                <span>Yükleniyor...</span>
            </div>
        `;
        
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        if (container) {
            container.innerHTML = loadingHtml;
        }
    }

    /**
     * Loading state gizle
     */
    hideLoading(container) {
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        const loading = container?.querySelector('.view-loading');
        if (loading) {
            loading.remove();
        }
    }

    /**
     * Empty state göster
     */
    showEmpty(container, options = {}) {
        const defaults = {
            icon: 'fas fa-inbox',
            title: 'İçerik Bulunamadı',
            text: 'Gösterilecek bir şey yok.',
            action: null
        };
        
        const config = { ...defaults, ...options };
        
        const emptyHtml = `
            <div class="view-empty">
                <div class="view-empty-icon">
                    <i class="${config.icon}"></i>
                </div>
                <h3 class="view-empty-title">${config.title}</h3>
                <p class="view-empty-text">${config.text}</p>
                ${config.action ? `<div class="view-empty-action">${config.action}</div>` : ''}
            </div>
        `;
        
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        if (container) {
            container.innerHTML = emptyHtml;
        }
    }

    /**
     * Notification göster
     */
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = this.getNotificationIcon(type);
        notification.innerHTML = `
            <div class="notification-content">
                <i class="${icon}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Notification container'ını oluştur veya bul
        let container = document.querySelector('.notification-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-container';
            document.body.appendChild(container);
        }

        container.appendChild(notification);

        // Animasyon ile göster
        setTimeout(() => notification.classList.add('show'), 10);

        // Kapama olayı
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });

        // Otomatik kapanma
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }
            }, duration);
        }
    }

    /**
     * Notification icon'u getir
     */
    getNotificationIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        return icons[type] || icons.info;
    }

    /**
     * URL parametrelerini parse et
     */
    parseUrlParams() {
        const params = {};
        const urlParams = new URLSearchParams(window.location.search);
        
        for (const [key, value] of urlParams) {
            params[key] = value;
        }
        
        return params;
    }

    /**
     * URL'i güncelle (history push olmadan)
     */
    updateUrl(params) {
        const url = new URL(window.location);
        
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== '') {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        
        window.history.replaceState({}, '', url);
    }

    /**
     * Format tarih
     */
    formatDate(dateString, format = 'relative') {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        if (format === 'relative') {
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const weeks = Math.floor(days / 7);
            const months = Math.floor(days / 30);

            if (seconds < 60) return 'Az önce';
            if (minutes < 60) return `${minutes} dakika önce`;
            if (hours < 24) return `${hours} saat önce`;
            if (days < 7) return `${days} gün önce`;
            if (weeks < 4) return `${weeks} hafta önce`;
            if (months < 12) return `${months} ay önce`;
            
            return date.toLocaleDateString('tr-TR');
        }

        return date.toLocaleDateString('tr-TR');
    }

    /**
     * Sayı formatla
     */
    formatNumber(number) {
        return new Intl.NumberFormat('tr-TR').format(number);
    }
}

// Global olarak kullanılabilir hale getir
window.ViewCommon = ViewCommon;

// Sayfa yüklendiğinde başlat
document.addEventListener('DOMContentLoaded', () => {
    window.viewCommon = new ViewCommon();
});

// CSS for custom tooltips and notifications
const style = document.createElement('style');
style.textContent = `
/* Custom Tooltips */
.custom-tooltip {
    position: absolute;
    background: var(--card-bg-4);
    color: var(--lighter-grey);
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    border: 1px solid var(--border-1);
    z-index: 10000;
    opacity: 0;
    transform: translateY(5px);
    transition: all 0.2s ease;
    pointer-events: none;
    max-width: 200px;
    word-wrap: break-word;
}

.custom-tooltip.show {
    opacity: 1;
    transform: translateY(0);
}

/* Notification System */
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification {
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    padding: 1rem;
    min-width: 300px;
    max-width: 400px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transform: translateX(400px);
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.notification.show {
    transform: translateX(0);
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.notification-close {
    background: none;
    border: none;
    color: var(--light-grey);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.notification-close:hover {
    background: var(--card-bg-2);
    color: var(--lighter-grey);
}

.notification-success {
    border-color: var(--turquase);
}

.notification-success .notification-content i {
    color: var(--turquase);
}

.notification-error {
    border-color: var(--red);
}

.notification-error .notification-content i {
    color: var(--red);
}

.notification-warning {
    border-color: var(--gold);
}

.notification-warning .notification-content i {
    color: var(--gold);
}

.notification-info {
    border-color: var(--light-grey);
}

.notification-info .notification-content i {
    color: var(--light-grey);
}

/* Mobile Sidebar Toggle */
.mobile-sidebar-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: var(--gold);
    color: var(--body-bg);
    border: none;
    border-radius: 8px;
    width: 45px;
    height: 45px;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.mobile-sidebar-toggle:hover {
    background: var(--light-gold);
    transform: scale(1.05);
}

.mobile-sidebar-toggle.active {
    background: var(--red);
}

@media (max-width: 968px) {
    .mobile-sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .view-profile-sidebar,
    .profile-sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        height: 100vh;
        z-index: 1000;
        transition: left 0.3s ease;
        overflow-y: auto;
    }
    
    .view-profile-sidebar.mobile-open,
    .profile-sidebar.mobile-open {
        left: 0;
    }
}

/* Lazy Loading */
img.lazy-load {
    opacity: 0;
    transition: opacity 0.3s ease;
}

img.lazy-loaded {
    opacity: 1;
}
`;

document.head.appendChild(style); 