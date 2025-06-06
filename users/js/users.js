// users/js/users.js - User List Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeUserList();
});

/**
 * Ana başlatma fonksiyonu
 */
function initializeUserList() {
    setupSearchForm();
    setupFilterHandlers();
    setupUserCardInteractions();
    setupLazyLoading();
    setupKeyboardNavigation();
    
    // URL'den parametreleri al ve formu güncelle
    updateFormFromURL();
    
    // Sayfa yüklenme animasyonlarını başlat
    animateUserCards();
}

/**
 * Arama formu kurulumu
 */
function setupSearchForm() {
    const searchForm = document.querySelector('.search-form');
    const searchInput = document.querySelector('.search-input');
    
    if (!searchForm || !searchInput) return;
    
    // Debounced search - anlık arama
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Minimum 2 karakter
            if (this.value.length >= 2 || this.value.length === 0) {
                searchForm.submit();
            }
        }, 500);
    });
    
    // Enter tuşu ile arama
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimeout);
            searchForm.submit();
        }
    });
    
    // Arama input'una focus olunca tüm metni seç
    searchInput.addEventListener('focus', function() {
        this.select();
    });
    
    // Placeholder animasyonu
    animateSearchPlaceholder(searchInput);
}

/**
 * Filter değişiklik handler'ları
 */
function setupFilterHandlers() {
    const roleFilter = document.getElementById('role');
    const statusFilter = document.getElementById('status');
    const searchForm = document.querySelector('.search-form');
    
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            // Sayfa 1'e geri dön
            const pageInput = document.querySelector('input[name="page"]');
            if (pageInput) {
                pageInput.value = 1;
            }
            searchForm.submit();
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            // Sayfa 1'e geri dön
            const pageInput = document.querySelector('input[name="page"]');
            if (pageInput) {
                pageInput.value = 1;
            }
            searchForm.submit();
        });
    }
}

/**
 * User card etkileşimleri
 */
function setupUserCardInteractions() {
    const userCards = document.querySelectorAll('.user-card');
    
    userCards.forEach(card => {
        const userId = card.getAttribute('data-user-id');
        const userLinks = card.querySelectorAll('.user-link');
        const profileBtn = card.querySelector('.btn-view-profile');
        
        // User link'lere click handler ekle
        userLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                showUserPopover(this, userId);
            });
        });
        
        // Profile button'a click handler ekle
        if (profileBtn) {
            profileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showUserPopover(this, userId);
            });
        }
        
        // Card hover efektleri
        card.addEventListener('mouseenter', function() {
            this.classList.add('hovered');
            
            // Avatar glow effect
            const avatar = this.querySelector('.avatar-img');
            if (avatar) {
                avatar.style.boxShadow = '0 0 20px rgba(189, 145, 42, 0.3)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hovered');
            
            // Avatar glow effect kaldır
            const avatar = this.querySelector('.avatar-img');
            if (avatar) {
                avatar.style.boxShadow = '';
            }
        });
        
        // Double click ile profile
        card.addEventListener('dblclick', function() {
            const profileLink = `/public/view_profile.php?user_id=${userId}`;
            window.open(profileLink, '_blank');
        });
    });
}

/**
 * Lazy loading için intersection observer
 */
function setupLazyLoading() {
    const userCards = document.querySelectorAll('.user-card');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    const avatar = card.querySelector('.avatar-img');
                    
                    if (avatar && avatar.dataset.src) {
                        avatar.src = avatar.dataset.src;
                        avatar.removeAttribute('data-src');
                        observer.unobserve(card);
                    }
                }
            });
        });
        
        userCards.forEach(card => {
            imageObserver.observe(card);
        });
    }
}

/**
 * Klavye navigasyonu
 */
function setupKeyboardNavigation() {
    const searchInput = document.querySelector('.search-input');
    const userCards = document.querySelectorAll('.user-card');
    
    document.addEventListener('keydown', function(e) {
        // Ctrl+F ile arama kutusuna focus
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // ESC ile popover'ı kapat
        if (e.key === 'Escape') {
            if (window.closeUserPopover) {
                window.closeUserPopover();
            }
        }
    });
    
    // Arrow keys ile card navigasyonu
    let currentCardIndex = -1;
    
    document.addEventListener('keydown', function(e) {
        if (document.activeElement === searchInput) return;
        
        const cards = Array.from(userCards);
        
        switch(e.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                e.preventDefault();
                currentCardIndex = Math.min(currentCardIndex + 1, cards.length - 1);
                focusCard(cards[currentCardIndex]);
                break;
                
            case 'ArrowLeft':
            case 'ArrowUp':
                e.preventDefault();
                currentCardIndex = Math.max(currentCardIndex - 1, 0);
                focusCard(cards[currentCardIndex]);
                break;
                
            case 'Enter':
                if (currentCardIndex >= 0 && currentCardIndex < cards.length) {
                    const card = cards[currentCardIndex];
                    const userId = card.getAttribute('data-user-id');
                    const profileBtn = card.querySelector('.btn-view-profile');
                    if (profileBtn) {
                        showUserPopover(profileBtn, userId);
                    }
                }
                break;
        }
    });
}

/**
 * Card'a focus
 */
function focusCard(card) {
    if (!card) return;
    
    // Önceki focus'u kaldır
    document.querySelectorAll('.user-card.keyboard-focused').forEach(c => {
        c.classList.remove('keyboard-focused');
    });
    
    // Yeni focus
    card.classList.add('keyboard-focused');
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Focus CSS için
    card.style.outline = '2px solid var(--gold)';
    card.style.outlineOffset = '2px';
    
    setTimeout(() => {
        card.style.outline = '';
        card.style.outlineOffset = '';
    }, 2000);
}

/**
 * URL'den form parametrelerini güncelle
 */
function updateFormFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Search input
    const searchInput = document.querySelector('.search-input');
    if (searchInput && urlParams.has('search')) {
        searchInput.value = urlParams.get('search');
    }
    
    // Role filter
    const roleFilter = document.getElementById('role');
    if (roleFilter && urlParams.has('role')) {
        roleFilter.value = urlParams.get('role');
    }
    
    // Status filter
    const statusFilter = document.getElementById('status');
    if (statusFilter && urlParams.has('status')) {
        statusFilter.value = urlParams.get('status');
    }
}

/**
 * User card'ları animasyon ile göster
 */
function animateUserCards() {
    const userCards = document.querySelectorAll('.user-card');
    
    // Intersection Observer ile animasyon
    if ('IntersectionObserver' in window) {
        const animationObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        });
        
        userCards.forEach((card, index) => {
            // Başlangıçta gizle
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            // Observer'a ekle
            animationObserver.observe(card);
            
            // CSS transition ekle
            card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        });
    } else {
        // Fallback - tüm kartları göster
        userCards.forEach(card => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        });
    }
}

/**
 * Arama placeholder animasyonu
 */
function animateSearchPlaceholder(input) {
    if (!input) return;
    
    const placeholders = [
        'Kullanıcı adı ile ara...',
        'RSI handle ile ara...',
        'Discord adı ile ara...',
        'E-posta ile ara...'
    ];
    
    let currentIndex = 0;
    
    setInterval(() => {
        if (input === document.activeElement) return;
        
        currentIndex = (currentIndex + 1) % placeholders.length;
        input.placeholder = placeholders[currentIndex];
    }, 3000);
}

/**
 * User popover göster (user_popover.php'den gelir)
 */
function showUserPopover(element, userId) {
    // Global fonksiyon user_popover.php'de tanımlanmış
    if (window.showUserPopover) {
        window.showUserPopover(element, userId);
    } else {
        // Fallback - profile sayfasına git
        window.open(`/public/view_profile.php?user_id=${userId}`, '_blank');
    }
}

/**
 * Notification göster
 */
function showNotification(message, type = 'info') {
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
        z-index: 10000;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        cursor: pointer;
    `;
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    const timeoutId = setTimeout(() => {
        removeNotification(notification);
    }, 5000);
    
    notification.addEventListener('click', () => {
        clearTimeout(timeoutId);
        removeNotification(notification);
    });
    
    function removeNotification(notif) {
        notif.style.opacity = '0';
        notif.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notif.parentNode) {
                notif.parentNode.removeChild(notif);
            }
        }, 300);
    }
}

/**
 * Loading state'i user card'a ekle
 */
function addLoadingToCard(cardElement) {
    cardElement.classList.add('loading');
}

/**
 * Loading state'i user card'dan kaldır
 */
function removeLoadingFromCard(cardElement) {
    cardElement.classList.remove('loading');
}

/**
 * Grid layout'u yeniden hesapla
 */
function recalculateGrid() {
    const grid = document.querySelector('.users-grid');
    if (!grid) return;
    
    // CSS Grid'in yeniden hesaplanması için
    grid.style.display = 'none';
    grid.offsetHeight; // Force reflow
    grid.style.display = 'grid';
}

/**
 * Responsive breakpoint'leri kontrol et
 */
function checkResponsiveBreakpoints() {
    const width = window.innerWidth;
    const body = document.body;
    
    // CSS class'ları ekle
    body.classList.remove('mobile', 'tablet', 'desktop');
    
    if (width <= 480) {
        body.classList.add('mobile');
    } else if (width <= 768) {
        body.classList.add('tablet');
    } else {
        body.classList.add('desktop');
    }
}

/**
 * Window resize handler
 */
window.addEventListener('resize', debounce(() => {
    checkResponsiveBreakpoints();
    recalculateGrid();
}, 250));

/**
 * Debounce utility function
 */
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

/**
 * CSS animasyon utility'leri
 */
const AnimationUtils = {
    // Fade in animasyonu
    fadeIn(element, duration = 300) {
        element.style.opacity = '0';
        element.style.display = 'block';
        
        let start = null;
        function animate(timestamp) {
            if (!start) start = timestamp;
            const progress = timestamp - start;
            const opacity = Math.min(progress / duration, 1);
            
            element.style.opacity = opacity;
            
            if (progress < duration) {
                requestAnimationFrame(animate);
            }
        }
        
        requestAnimationFrame(animate);
    },
    
    // Slide up animasyonu
    slideUp(element, duration = 300) {
        element.style.transform = 'translateY(20px)';
        element.style.opacity = '0';
        
        let start = null;
        function animate(timestamp) {
            if (!start) start = timestamp;
            const progress = timestamp - start;
            const percentage = Math.min(progress / duration, 1);
            
            const translateY = 20 * (1 - percentage);
            element.style.transform = `translateY(${translateY}px)`;
            element.style.opacity = percentage;
            
            if (progress < duration) {
                requestAnimationFrame(animate);
            } else {
                element.style.transform = '';
                element.style.opacity = '';
            }
        }
        
        requestAnimationFrame(animate);
    }
};

/**
 * Accessibility features
 */
function setupAccessibility() {
    // High contrast mode detection
    if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
        document.body.classList.add('high-contrast');
    }
    
    // Reduced motion detection
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.body.classList.add('reduced-motion');
    }
    
    // Focus visible polyfill
    setupFocusVisible();
}

/**
 * Focus visible setup
 */
function setupFocusVisible() {
    let hadKeyboardEvent = true;
    
    const keyboardThrottledUpdateActiveElement = throttle(updateActiveElement, 66);
    
    function updateActiveElement() {
        if (hadKeyboardEvent && document.activeElement) {
            document.activeElement.classList.add('focus-visible');
        }
    }
    
    document.addEventListener('keydown', () => {
        hadKeyboardEvent = true;
        keyboardThrottledUpdateActiveElement();
    });
    
    document.addEventListener('mousedown', () => {
        hadKeyboardEvent = false;
    });
    
    document.addEventListener('focusin', () => {
        if (hadKeyboardEvent) {
            document.activeElement.classList.add('focus-visible');
        }
    });
    
    document.addEventListener('focusout', () => {
        document.querySelectorAll('.focus-visible').forEach(el => {
            el.classList.remove('focus-visible');
        });
    });
}

/**
 * Throttle utility function
 */
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
    };
}

/**
 * Performance monitoring
 */
function setupPerformanceMonitoring() {
    // Page load time tracking
    window.addEventListener('load', () => {
        const loadTime = performance.now();
        console.log(`Users page loaded in ${loadTime.toFixed(2)}ms`);
        
        // Send to analytics if available
        if (window.gtag) {
            gtag('event', 'page_load_time', {
                event_category: 'Performance',
                event_label: 'Users Page',
                value: Math.round(loadTime)
            });
        }
    });
    
    // Memory usage monitoring
    if ('memory' in performance) {
        setInterval(() => {
            const memory = performance.memory;
            if (memory.usedJSHeapSize > 50 * 1024 * 1024) { // 50MB
                console.warn('High memory usage detected:', memory);
            }
        }, 30000);
    }
}

/**
 * Error handling
 */
function setupErrorHandling() {
    window.addEventListener('error', (event) => {
        console.error('Users page error:', event.error);
        
        // User'a friendly error message
        showNotification('Bir hata oluştu. Sayfa yenilenecek.', 'error');
        
        // Auto refresh after 3 seconds
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    });
    
    // Promise rejection handling
    window.addEventListener('unhandledrejection', (event) => {
        console.error('Unhandled promise rejection:', event.reason);
        event.preventDefault();
    });
}

/**
 * Search analytics
 */
function trackSearchAnalytics(searchTerm, resultCount) {
    if (window.gtag) {
        gtag('event', 'search', {
            search_term: searchTerm,
            event_category: 'Users',
            custom_parameters: {
                result_count: resultCount
            }
        });
    }
}

/**
 * User interaction analytics
 */
function trackUserInteraction(action, userId) {
    if (window.gtag) {
        gtag('event', action, {
            event_category: 'User Interaction',
            event_label: `User ${userId}`,
            value: 1
        });
    }
}

/**
 * Filter usage analytics
 */
function trackFilterUsage(filterType, filterValue) {
    if (window.gtag) {
        gtag('event', 'filter_used', {
            event_category: 'Users Filter',
            event_label: `${filterType}: ${filterValue}`,
            value: 1
        });
    }
}

/**
 * Page visibility API integration
 */
function setupPageVisibility() {
    let visibilityStart = Date.now();
    
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Page became hidden
            const visibleTime = Date.now() - visibilityStart;
            console.log(`Page was visible for ${visibleTime}ms`);
        } else {
            // Page became visible
            visibilityStart = Date.now();
            
            // Refresh user data if page was hidden for more than 5 minutes
            const hiddenTime = Date.now() - (window.lastHiddenTime || 0);
            if (hiddenTime > 5 * 60 * 1000) {
                console.log('Refreshing user data after long absence');
                // Optional: refresh data
            }
        }
        
        if (document.hidden) {
            window.lastHiddenTime = Date.now();
        }
    });
}

/**
 * Advanced user card interactions
 */
function setupAdvancedCardInteractions() {
    const userCards = document.querySelectorAll('.user-card');
    
    userCards.forEach(card => {
        let hoverTimeout;
        
        // Advanced hover with delay
        card.addEventListener('mouseenter', function() {
            const userId = this.getAttribute('data-user-id');
            
            hoverTimeout = setTimeout(() => {
                // Preload user data for faster popover
                preloadUserData(userId);
                
                // Add visual feedback
                this.classList.add('hover-active');
            }, 500);
        });
        
        card.addEventListener('mouseleave', function() {
            clearTimeout(hoverTimeout);
            this.classList.remove('hover-active');
        });
        
        // Context menu (right click)
        card.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            
            const userId = this.getAttribute('data-user-id');
            showUserContextMenu(e, userId);
        });
        
        // Touch interactions for mobile
        let touchStartTime;
        card.addEventListener('touchstart', function() {
            touchStartTime = Date.now();
        });
        
        card.addEventListener('touchend', function(e) {
            const touchDuration = Date.now() - touchStartTime;
            
            // Long press for context menu on mobile
            if (touchDuration > 500) {
                e.preventDefault();
                const userId = this.getAttribute('data-user-id');
                showUserContextMenu(e, userId);
            }
        });
    });
}

/**
 * User data preloading
 */
function preloadUserData(userId) {
    if (window.userDataCache && window.userDataCache[userId]) {
        return; // Already cached
    }
    
    fetch(`/src/api/get_user_popover_data.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cache the data
                if (!window.userDataCache) {
                    window.userDataCache = {};
                }
                window.userDataCache[userId] = data.user;
            }
        })
        .catch(error => {
            console.log('Preload failed (non-critical):', error);
        });
}

/**
 * User context menu
 */
function showUserContextMenu(event, userId) {
    // Remove existing context menu
    const existingMenu = document.querySelector('.user-context-menu');
    if (existingMenu) {
        existingMenu.remove();
    }
    
    const menu = document.createElement('div');
    menu.className = 'user-context-menu';
    menu.style.cssText = `
        position: fixed;
        top: ${event.clientY}px;
        left: ${event.clientX}px;
        background: var(--card-bg);
        border: 1px solid var(--border-1-featured);
        border-radius: 6px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        z-index: 10000;
        min-width: 180px;
        padding: 0.5rem 0;
        font-family: var(--font);
    `;
    
    const menuItems = [
        {
            icon: 'fas fa-user',
            text: 'Profili Görüntüle',
            action: () => window.open(`/public/view_profile.php?user_id=${userId}`, '_blank')
        },
        {
            icon: 'fas fa-eye',
            text: 'Hızlı Görünüm',
            action: () => showUserPopover(event.target, userId)
        }
    ];
    
    menuItems.forEach(item => {
        const menuItem = document.createElement('div');
        menuItem.className = 'context-menu-item';
        menuItem.style.cssText = `
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            color: var(--lighter-grey);
            transition: all 0.2s ease;
        `;
        
        menuItem.innerHTML = `
            <i class="${item.icon}" style="color: var(--gold); width: 16px;"></i>
            <span>${item.text}</span>
        `;
        
        menuItem.addEventListener('mouseenter', function() {
            this.style.background = 'var(--border-1)';
        });
        
        menuItem.addEventListener('mouseleave', function() {
            this.style.background = '';
        });
        
        menuItem.addEventListener('click', function() {
            item.action();
            menu.remove();
        });
        
        menu.appendChild(menuItem);
    });
    
    document.body.appendChild(menu);
    
    // Remove menu on click outside
    const removeMenu = (e) => {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', removeMenu);
        }
    };
    
    setTimeout(() => {
        document.addEventListener('click', removeMenu);
    }, 100);
    
    // Position adjustment if menu goes off screen
    const rect = menu.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    if (rect.right > viewportWidth) {
        menu.style.left = (event.clientX - rect.width) + 'px';
    }
    
    if (rect.bottom > viewportHeight) {
        menu.style.top = (event.clientY - rect.height) + 'px';
    }
}

/**
 * Infinite scroll (optional feature)
 */
function setupInfiniteScroll() {
    if (window.location.search.includes('infinite=true')) {
        let loading = false;
        let page = 1;
        
        window.addEventListener('scroll', throttle(() => {
            if (loading) return;
            
            const scrollPosition = window.scrollY + window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            
            if (scrollPosition >= documentHeight - 1000) {
                loading = true;
                loadMoreUsers(++page);
            }
        }, 200));
    }
}

/**
 * Load more users for infinite scroll
 */
function loadMoreUsers(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    url.searchParams.set('ajax', 'true');
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            const newCards = tempDiv.querySelectorAll('.user-card');
            const grid = document.querySelector('.users-grid');
            
            newCards.forEach((card, index) => {
                setTimeout(() => {
                    grid.appendChild(card);
                    AnimationUtils.slideUp(card);
                }, index * 100);
            });
            
            loading = false;
        })
        .catch(error => {
            console.error('Load more users failed:', error);
            loading = false;
        });
}

// Initialize all features
document.addEventListener('DOMContentLoaded', function() {
    setupAccessibility();
    setupPerformanceMonitoring();
    setupErrorHandling();
    setupPageVisibility();
    setupAdvancedCardInteractions();
    setupInfiniteScroll();
    
    checkResponsiveBreakpoints();
    
    // Track page view
    if (window.gtag) {
        gtag('event', 'page_view', {
            page_title: 'Users List',
            page_location: window.location.href
        });
    }
});

// Export for global access
window.UsersPage = {
    showNotification,
    trackUserInteraction,
    trackFilterUsage,
    trackSearchAnalytics,
    recalculateGrid,
    AnimationUtils
};