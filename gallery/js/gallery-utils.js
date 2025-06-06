// /gallery/js/gallery-utils.js - Utilities Module

const GalleryUtils = {
    escapeHtml(text) {
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
    },

    formatTimeAgo(datetime) {
        const time = new Date().getTime() - new Date(datetime).getTime();
        const seconds = Math.floor(time / 1000);
        
        if (seconds < 60) return 'Az önce';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' dakika önce';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' saat önce';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' gün önce';
        if (seconds < 2592000) return Math.floor(seconds / 604800) + ' hafta önce';
        
        return new Date(datetime).toLocaleDateString('tr-TR');
    },

    formatDateTime(datetime) {
        return new Date(datetime).toLocaleString('tr-TR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    updatePhotoCommentCount(photoId, change) {
        const commentBtn = document.querySelector(`.photo-item[data-photo-id="${photoId}"] .comment-count`);
        if (commentBtn) {
            const currentCount = parseInt(commentBtn.textContent) || 0;
            commentBtn.textContent = Math.max(0, currentCount + change);
        }
    },

    showNotification(message, type = 'info') {
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
            this.removeNotification(notification);
        }, 5000);
        
        // Tıklayınca kapat
        notification.addEventListener('click', () => {
            clearTimeout(timeoutId);
            this.removeNotification(notification);
        });
    },

    removeNotification(notif) {
        notif.style.opacity = '0';
        notif.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notif.parentNode) {
                notif.parentNode.removeChild(notif);
            }
        }, 300);
    }
};