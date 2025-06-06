// /gallery/js/gallery-filters.js - Filters Module

const GalleryFilters = {
    init() {
        this.setupFilterInputs();
    },

    setupFilterInputs() {
        const userFilter = document.getElementById('user-filter');
        
        if (userFilter) {
            let timeout;
            userFilter.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.updateFilters();
                }, 500); // 500ms delay
            });
            
            userFilter.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.updateFilters();
                }
            });
        }
    },

    updateFilters() {
        const sortSelect = document.getElementById('sort-select');
        const userFilter = document.getElementById('user-filter');
        
        const params = new URLSearchParams();
        
        if (sortSelect && sortSelect.value !== 'newest') {
            params.set('sort', sortSelect.value);
        }
        
        if (userFilter && userFilter.value.trim()) {
            params.set('user', userFilter.value.trim());
        }
        
        const queryString = params.toString();
        const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
        
        window.location.href = newUrl;
    },

    clearUserFilter() {
        const userFilter = document.getElementById('user-filter');
        if (userFilter) {
            userFilter.value = '';
            this.updateFilters();
        }
    }
};