// Forum JavaScript Functionality

class ForumManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupSearchForm();
        this.setupCategoryCards();
        this.setupLazyLoading();
        this.setupKeyboardNavigation();
        this.setupTooltips();
    }

    setupSearchForm() {
        const searchForm = document.querySelector('.forum-search');
        const searchInput = document.querySelector('.search-input');
        
        if (searchForm && searchInput) {
            // Auto-complete functionality
            this.setupAutoComplete(searchInput);
            
            // Search form validation
            searchForm.addEventListener('submit', (e) => {
                const query = searchInput.value.trim();
                if (query.length < 2) {
                    e.preventDefault();
                    this.showNotification('Arama terimi en az 2 karakter olmalıdır.', 'warning');
                    searchInput.focus();
                }
            });

            // Clear search on escape
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    this.hideAutoComplete();
                }
            });
        }
    }

    setupAutoComplete(input) {
        let timeout;
        const autocompleteContainer = this.createAutoCompleteContainer(input);

        input.addEventListener('input', (e) => {
            clearTimeout(timeout);
            const query = e.target.value.trim();

            if (query.length < 2) {
                this.hideAutoComplete();
                return;
            }

            timeout = setTimeout(() => {
                this.fetchAutoCompleteSuggestions(query, autocompleteContainer);
            }, 300);
        });

        // Hide autocomplete when clicking outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !autocompleteContainer.contains(e.target)) {
                this.hideAutoComplete();
            }
        });
    }

    createAutoCompleteContainer(input) {
        const container = document.createElement('div');
        container.className = 'autocomplete-container';
        container.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--darker-gold-2);
            border: 1px solid var(--gold);
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        `;
        
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(container);
        return container;
    }

    async fetchAutoCompleteSuggestions(query, container) {
        try {
            const response = await fetch(`/src/api/forum_autocomplete.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.suggestions.length > 0) {
                this.displayAutoCompleteSuggestions(data.suggestions, container);
            } else {
                this.hideAutoComplete();
            }
        } catch (error) {
            console.error('Autocomplete error:', error);
            this.hideAutoComplete();
        }
    }

    displayAutoCompleteSuggestions(suggestions, container) {
        container.innerHTML = '';
        
        suggestions.forEach(suggestion => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.style.cssText = `
                padding: 10px 15px;
                cursor: pointer;
                border-bottom: 1px solid var(--transparent-gold);
                color: var(--white);
                transition: background 0.2s ease;
            `;
            
            item.innerHTML = `
                <div class="suggestion-type">${this.getSuggestionTypeIcon(suggestion.type)} ${suggestion.type_label}</div>
                <div class="suggestion-title">${this.highlightText(suggestion.title, suggestion.query)}</div>
            `;
            
            item.addEventListener('mouseenter', () => {
                item.style.background = 'var(--transparent-gold)';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.background = 'transparent';
            });
            
            item.addEventListener('click', () => {
                window.location.href = suggestion.url;
            });
            
            container.appendChild(item);
        });
        
        container.style.display = 'block';
    }

    getSuggestionTypeIcon(type) {
        const icons = {
            'category': '<i class="fas fa-folder"></i>',
            'topic': '<i class="fas fa-comment-dots"></i>',
            'user': '<i class="fas fa-user"></i>',
            'tag': '<i class="fas fa-tag"></i>'
        };
        return icons[type] || '<i class="fas fa-search"></i>';
    }

    highlightText(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark style="background: var(--gold); color: var(--black);">$1</mark>');
    }

    hideAutoComplete() {
        const container = document.querySelector('.autocomplete-container');
        if (container) {
            container.style.display = 'none';
        }
    }

    setupCategoryCards() {
        const categoryCards = document.querySelectorAll('.category-card');
        
        categoryCards.forEach(card => {
            // Add click-to-navigate functionality
            card.addEventListener('click', (e) => {
                if (e.target.tagName !== 'A' && !e.target.closest('a')) {
                    const link = card.querySelector('.category-header h3 a');
                    if (link) {
                        window.location.href = link.href;
                    }
                }
            });

            // Add keyboard navigation
            card.setAttribute('tabindex', '0');
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const link = card.querySelector('.category-header h3 a');
                    if (link) {
                        window.location.href = link.href;
                    }
                }
            });

            // Add hover effect animation
            this.setupCardHoverEffect(card);
        });
    }

    setupCardHoverEffect(card) {
        const icon = card.querySelector('.category-icon');
        
        card.addEventListener('mouseenter', () => {
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
        });
    }

    setupLazyLoading() {
        const lazyElements = document.querySelectorAll('.lazy-load');
        
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadElement(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            });

            lazyElements.forEach(element => {
                observer.observe(element);
            });
        } else {
            // Fallback for older browsers
            lazyElements.forEach(element => {
                this.loadElement(element);
            });
        }
    }

    loadElement(element) {
        if (element.dataset.src) {
            element.src = element.dataset.src;
            element.classList.remove('lazy-load');
            element.classList.add('loaded');
        }
    }

    setupKeyboardNavigation() {
        // Global keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Escape to close modals/overlays
            if (e.key === 'Escape') {
                this.closeActiveOverlays();
            }
        });

        // Arrow key navigation for category cards
        this.setupArrowNavigation('.category-card');
    }

    setupArrowNavigation(selector) {
        const elements = document.querySelectorAll(selector);
        
        elements.forEach((element, index) => {
            element.addEventListener('keydown', (e) => {
                let targetIndex;
                
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        targetIndex = (index + 1) % elements.length;
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        targetIndex = (index - 1 + elements.length) % elements.length;
                        break;
                    default:
                        return;
                }
                
                elements[targetIndex].focus();
            });
        });
    }

    setupTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element, text) {
        const tooltip = this.getOrCreateTooltip();
        tooltip.textContent = text;
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = `${rect.left + rect.width / 2}px`;
        tooltip.style.top = `${rect.top - 10}px`;
        tooltip.style.display = 'block';
        
        setTimeout(() => {
            tooltip.classList.add('show');
        }, 10);
    }

    hideTooltip() {
        const tooltip = document.querySelector('.forum-tooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
            setTimeout(() => {
                tooltip.style.display = 'none';
            }, 200);
        }
    }

    getOrCreateTooltip() {
        let tooltip = document.querySelector('.forum-tooltip');
        
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'forum-tooltip';
            tooltip.style.cssText = `
                position: fixed;
                background: var(--darker-gold-2);
                color: var(--white);
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 0.85rem;
                z-index: 9999;
                pointer-events: none;
                opacity: 0;
                transform: translateX(-50%) translateY(-100%);
                transition: opacity 0.2s ease;
                border: 1px solid var(--gold);
            `;
            
            document.body.appendChild(tooltip);
        }
        
        return tooltip;
    }

    closeActiveOverlays() {
        this.hideAutoComplete();
        this.hideTooltip();
        
        // Close any open modals
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            modal.classList.remove('show');
        });
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `forum-notification notification-${type}`;
        
        const icons = {
            'success': '<i class="fas fa-check-circle"></i>',
            'error': '<i class="fas fa-exclamation-triangle"></i>',
            'warning': '<i class="fas fa-exclamation-circle"></i>',
            'info': '<i class="fas fa-info-circle"></i>'
        };
        
        notification.innerHTML = `
            ${icons[type] || icons.info}
            <span>${message}</span>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--darker-gold-2);
            border: 1px solid var(--${type === 'error' ? 'red' : 'gold'});
            color: var(--white);
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 300px;
            max-width: 500px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            this.removeNotification(notification);
        }, duration);
        
        // Manual close
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            this.removeNotification(notification);
        });
        
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: var(--light-grey);
            cursor: pointer;
            padding: 0;
            margin-left: auto;
        `;
    }

    removeNotification(notification) {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// Utility Functions
function formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = now - time;
    
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
    
    return time.toLocaleDateString('tr-TR');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Initialize forum functionality when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.forumManager = new ForumManager();
    
    // Add loading states to async operations
    const asyncButtons = document.querySelectorAll('[data-async]');
    asyncButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.classList.contains('loading')) {
                this.classList.add('loading');
                this.disabled = true;
                
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
                
                // Reset after 10 seconds max
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.disabled = false;
                    this.innerHTML = originalText;
                }, 10000);
            }
        });
    });
});

// Export for use in other files
window.ForumUtils = {
    formatTimeAgo,
    debounce,
    throttle
};