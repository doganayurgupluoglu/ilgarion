// /admin/dashboard/js/dashboard.js

const AdminDashboard = {
    // Konfigürasyon
    config: {
        refreshInterval: 300000, // 5 dakika
        animationDuration: 300
    },
    
    // State management
    state: {
        autoRefreshEnabled: true,
        refreshTimer: null,
        isLoading: false
    },
    
    // DOM elements cache
    elements: {},
    
    /**
     * Dashboard'u başlat
     */
    init() {
        this.cacheElements();
        this.bindEvents();
        this.startAutoRefresh();
        this.animateCounters();
        console.log('Admin Dashboard initialized');
    },
    
    /**
     * DOM elementlerini cache'le
     */
    cacheElements() {
        this.elements = {
            // Stats
            statNumbers: document.querySelectorAll('.stat-number'),
            statValues: document.querySelectorAll('.stat-value'),
            quickActionBtns: document.querySelectorAll('.quick-action-btn'),
            pendingItems: document.querySelectorAll('.pending-item'),
            
            // Widgets
            widgets: document.querySelectorAll('.dashboard-widget'),
            statCards: document.querySelectorAll('.stat-card'),
            
            // User elements
            recentUsers: document.querySelectorAll('.recent-user'),
            userActions: document.querySelectorAll('.action-link')
        };
    },
    
    /**
     * Event listener'ları bağla
     */
    bindEvents() {
        // Hover effects for stat cards
        this.elements.statCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.animateStatCard(card, 'enter');
            });
            
            card.addEventListener('mouseleave', () => {
                this.animateStatCard(card, 'leave');
            });
        });
        
        // Click effects for quick actions
        this.elements.quickActionBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.animateClick(btn);
            });
        });
        
        // Auto refresh toggle (optional feature)
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                this.refreshDashboard();
            }
        });
        
        // Page visibility change (pause refresh when tab is not active)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoRefresh();
            } else {
                this.resumeAutoRefresh();
            }
        });
    },
    
    /**
     * Sayaçları animasyon ile göster
     */
    animateCounters() {
        this.elements.statNumbers.forEach(element => {
            this.animateCounter(element);
        });
        
        this.elements.statValues.forEach(element => {
            if (element.textContent && !isNaN(element.textContent)) {
                this.animateCounter(element);
            }
        });
    },
    
    /**
     * Tekil sayaç animasyonu
     */
    animateCounter(element) {
        const target = parseInt(element.textContent) || 0;
        const duration = 2000;
        const steps = 60;
        const stepDuration = duration / steps;
        const increment = target / steps;
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current);
        }, stepDuration);
    },
    
    /**
     * Stat card hover animasyonu
     */
    animateStatCard(card, action) {
        const icon = card.querySelector('.stat-header i');
        const number = card.querySelector('.stat-number');
        
        if (action === 'enter') {
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(10deg)';
            }
            if (number) {
                number.style.transform = 'scale(1.05)';
            }
        } else {
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
            if (number) {
                number.style.transform = 'scale(1)';
            }
        }
    },
    
    /**
     * Click animasyonu
     */
    animateClick(element) {
        element.style.transform = 'scale(0.95)';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 150);
    },
    
    /**
     * Dashboard'u yenile
     */
    async refreshDashboard() {
        if (this.state.isLoading) return;
        
        try {
            this.state.isLoading = true;
            this.showRefreshIndicator();
            
            // Sayfayı yenile (basit yöntem)
            window.location.reload();
            
        } catch (error) {
            console.error('Dashboard refresh error:', error);
            this.showError('Dashboard yenilenirken hata oluştu');
        } finally {
            this.state.isLoading = false;
            this.hideRefreshIndicator();
        }
    },
    
    /**
     * Otomatik yenilemeyi başlat
     */
    startAutoRefresh() {
        if (!this.state.autoRefreshEnabled) return;
        
        this.state.refreshTimer = setInterval(() => {
            if (!document.hidden) {
                this.refreshDashboard();
            }
        }, this.config.refreshInterval);
        
        console.log('Auto refresh started (5 min interval)');
    },
    
    /**
     * Otomatik yenilemeyi duraklat
     */
    pauseAutoRefresh() {
        if (this.state.refreshTimer) {
            clearInterval(this.state.refreshTimer);
            this.state.refreshTimer = null;
        }
    },
    
    /**
     * Otomatik yenilemeyi devam ettir
     */
    resumeAutoRefresh() {
        if (this.state.autoRefreshEnabled && !this.state.refreshTimer) {
            this.startAutoRefresh();
        }
    },
    
    /**
     * Yenileme göstergesini göster
     */
    showRefreshIndicator() {
        // Basit bir loading indicator
        const indicator = document.createElement('div');
        indicator.id = 'refresh-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--gold);
            color: var(--charcoal);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 16000;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        `;
        
        indicator.innerHTML = `
            <i class="fas fa-sync-alt fa-spin"></i>
            <span>Dashboard yenileniyor...</span>
        `;
        
        document.body.appendChild(indicator);
    },
    
    /**
     * Yenileme göstergesini gizle
     */
    hideRefreshIndicator() {
        const indicator = document.getElementById('refresh-indicator');
        if (indicator) {
            indicator.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 300);
        }
    },
    
    /**
     * Hata mesajı göster
     */
    showError(message) {
        const notification = document.createElement('div');
        notification.className = 'dashboard-notification error';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--danger-color, #dc3545);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            z-index: 16000;
            animation: slideInRight 0.3s ease;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-exclamation-circle"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // 5 saniye sonra kaldır
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
        
        // Tıklayınca kaldır
        notification.addEventListener('click', () => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        });
    },
    
    /**
     * HTML escape
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Widget'ları animasyonlu olarak göster
     */
    animateWidgets() {
        this.elements.widgets.forEach((widget, index) => {
            widget.style.opacity = '0';
            widget.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                widget.style.transition = 'all 0.5s ease';
                widget.style.opacity = '1';
                widget.style.transform = 'translateY(0)';
            }, index * 100);
        });
    },
    
    /**
     * Dashboard verilerini güncelle (AJAX ile)
     */
    async updateStats() {
        try {
            const response = await fetch('/admin/dashboard/api/get_stats.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateStatDisplay(data.stats);
            }
        } catch (error) {
            console.error('Stats update error:', error);
        }
    },
    
    /**
     * İstatistik görünümünü güncelle
     */
    updateStatDisplay(stats) {
        // Bu fonksiyon gerçek zamanlı güncellemeler için kullanılabilir
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                this.animateCounter(element);
            }
        });
    }
};

// Global fonksiyonlar
window.refreshDashboard = () => AdminDashboard.refreshDashboard();

// CSS animasyonları için stil ekle
if (!document.querySelector('#dashboard-animations')) {
    const style = document.createElement('style');
    style.id = 'dashboard-animations';
    style.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .dashboard-notification {
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .dashboard-notification:hover {
            transform: translateX(-5px);
        }
        
        .stat-card .stat-header i,
        .stat-card .stat-number {
            transition: all 0.3s ease;
        }
        
        .quick-action-btn {
            transition: all 0.3s ease;
        }
    `;
    document.head.appendChild(style);
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminDashboard;
}