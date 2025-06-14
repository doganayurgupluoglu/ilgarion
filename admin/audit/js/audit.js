// /admin/audit/js/audit.js

const AuditManager = {
    // Konfigürasyon
    config: {
        apiBaseUrl: '/admin/audit/api/',
        debounceTime: 300,
        animationDuration: 300,
        itemsPerPage: 25,
        refreshInterval: 30000 // 30 saniye
    },
    
    // State management
    state: {
        currentPage: 1,
        totalPages: 1,
        totalItems: 0,
        isLoading: false,
        searchTimeout: null,
        filterTimeout: null,
        sortField: 'created_at',
        sortDirection: 'desc',
        filters: {
            search: '',
            action: '',
            user: '',
            time: '',
            startDate: '',
            endDate: '',
            targetType: '',
            ipAddress: ''
        },
        refreshTimer: null
    },
    
    // DOM elements cache
    elements: {},
    
    /**
     * Modülü başlat
     */
    init() {
        this.cacheElements();
        this.bindEvents();
        this.initializeDatepickers();
        this.loadAuditData();
        this.loadRecentActivity();
        this.startAutoRefresh();
        console.log('AuditManager initialized');
    },
    
    /**
     * DOM elementlerini cache'le
     */
    cacheElements() {
        this.elements = {
            // Search and filters
            auditSearch: document.getElementById('auditSearch'),
            actionFilter: document.getElementById('actionFilter'),
            userFilter: document.getElementById('userFilter'),
            timeFilter: document.getElementById('timeFilter'),
            
            // Advanced filters
            advancedFilters: document.getElementById('advancedFilters'),
            startDate: document.getElementById('startDate'),
            endDate: document.getElementById('endDate'),
            targetTypeFilter: document.getElementById('targetTypeFilter'),
            ipFilter: document.getElementById('ipFilter'),
            
            // Table and pagination
            auditTable: document.getElementById('auditTable'),
            auditTableBody: document.getElementById('auditTableBody'),
            paginationInfo: document.getElementById('paginationInfo'),
            pageNumbers: document.getElementById('pageNumbers'),
            prevPage: document.getElementById('prevPage'),
            nextPage: document.getElementById('nextPage'),
            
            // Side panel
            recentActivity: document.getElementById('recentActivity'),
            
            // Modals
            auditDetailModal: document.getElementById('auditDetailModal'),
            exportModal: document.getElementById('exportModal'),
            exportForm: document.getElementById('exportForm'),
            
            // Loading
            loadingOverlay: document.getElementById('loadingOverlay')
        };
    },
    
    /**
     * Event listener'ları bağla
     */
    bindEvents() {
        // Search functionality
        if (this.elements.auditSearch) {
            this.elements.auditSearch.addEventListener('input', (e) => {
                this.debounce(() => {
                    this.state.filters.search = e.target.value;
                    this.state.currentPage = 1;
                    this.loadAuditData();
                }, this.config.debounceTime);
            });
        }
        
        // Page action buttons
        const refreshBtn = document.querySelector('button[onclick="refreshAuditData()"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshAuditData();
            });
        }
        
        const exportBtn = document.querySelector('button[onclick="exportAuditLog()"]');
        if (exportBtn) {
            exportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportAuditLog();
            });
        }
        
        const advancedFiltersBtn = document.querySelector('button[onclick="toggleAdvancedFilters()"]');
        if (advancedFiltersBtn) {
            advancedFiltersBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleAdvancedFilters();
            });
        }
        
        // Export modal buttons
        const executeExportBtn = document.querySelector('button[onclick="executeExport()"]');
        if (executeExportBtn) {
            executeExportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.executeExport();
            });
        }
        
        // Advanced filter buttons
        const applyFiltersBtn = document.querySelector('button[onclick="applyAdvancedFilters()"]');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyAdvancedFilters();
            });
        }
        
        const clearFiltersBtn = document.querySelector('button[onclick="clearAdvancedFilters()"]');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.clearAdvancedFilters();
            });
        }
        
        // Filter changes
        ['actionFilter', 'userFilter', 'timeFilter', 'targetTypeFilter'].forEach(filterId => {
            const element = this.elements[filterId];
            if (element) {
                element.addEventListener('change', (e) => {
                    const filterKey = filterId.replace('Filter', '').replace('action', 'action').replace('user', 'user').replace('time', 'time').replace('targetType', 'targetType');
                    this.state.filters[filterKey] = e.target.value;
                    this.state.currentPage = 1;
                    this.handleTimeFilter(e.target.value);
                    this.loadAuditData();
                });
            }
        });
        
        // Advanced filter inputs
        if (this.elements.startDate) {
            this.elements.startDate.addEventListener('change', (e) => {
                this.state.filters.startDate = e.target.value;
                this.loadAuditData();
            });
        }
        
        if (this.elements.endDate) {
            this.elements.endDate.addEventListener('change', (e) => {
                this.state.filters.endDate = e.target.value;
                this.loadAuditData();
            });
        }
        
        if (this.elements.ipFilter) {
            this.elements.ipFilter.addEventListener('input', (e) => {
                this.debounce(() => {
                    this.state.filters.ipAddress = e.target.value;
                    this.loadAuditData();
                }, this.config.debounceTime);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'f':
                        e.preventDefault();
                        this.elements.auditSearch?.focus();
                        break;
                    case 'r':
                        e.preventDefault();
                        this.refreshAuditData();
                        break;
                    case 'e':
                        e.preventDefault();
                        this.exportAuditLog();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
        
        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });
    },
    
    /**
     * Tarih seçicileri başlat
     */
    initializeDatepickers() {
        const now = new Date();
        const oneMonthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
        
        // Export modal için varsayılan tarihler
        if (document.getElementById('exportStartDate')) {
            document.getElementById('exportStartDate').value = oneMonthAgo.toISOString().slice(0, 16);
            document.getElementById('exportEndDate').value = now.toISOString().slice(0, 16);
        }
    },
    
    /**
     * Zaman filtresi işle
     */
    handleTimeFilter(timeValue) {
        const now = new Date();
        let startDate = '';
        let endDate = '';
        
        switch(timeValue) {
            case 'today':
                startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString().slice(0, 16);
                endDate = now.toISOString().slice(0, 16);
                break;
            case 'week':
                startDate = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000)).toISOString().slice(0, 16);
                endDate = now.toISOString().slice(0, 16);
                break;
            case 'month':
                startDate = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate()).toISOString().slice(0, 16);
                endDate = now.toISOString().slice(0, 16);
                break;
            case 'custom':
                this.toggleAdvancedFilters();
                return;
        }
        
        this.state.filters.startDate = startDate;
        this.state.filters.endDate = endDate;
        
        if (this.elements.startDate) this.elements.startDate.value = startDate;
        if (this.elements.endDate) this.elements.endDate.value = endDate;
    },
    
    /**
     * Audit verilerini yükle
     */
    async loadAuditData() {
        try {
            this.showLoading();
            
            const params = new URLSearchParams({
                page: this.state.currentPage,
                limit: this.config.itemsPerPage,
                sort_field: this.state.sortField,
                sort_direction: this.state.sortDirection,
                ...this.state.filters
            });
            
            const url = `${this.config.apiBaseUrl}get_audit_logs.php?${params}`;
            console.log('Requesting:', url); // Debug log
            
            const response = await fetch(url);
            
            // Response'u text olarak al ve kontrol et
            const responseText = await response.text();
            console.log('Response status:', response.status);
            console.log('Response text:', responseText.substring(0, 500)); // İlk 500 karakter
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // JSON parse etmeye çalış
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response was:', responseText);
                throw new Error('Server yanıtı geçerli JSON formatında değil');
            }
            
            if (data.success) {
                this.populateAuditTable(data.logs);
                this.updatePagination(data.pagination);
            } else {
                this.showError(data.message || 'Audit verileri yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error loading audit data:', error);
            this.showError('Ağ hatası oluştu: ' + error.message);
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Son aktiviteleri yükle
     */
    async loadRecentActivity() {
        try {
            const url = `${this.config.apiBaseUrl}get_recent_activity.php?limit=10`;
            console.log('Recent activity URL:', url); // Debug log
            
            const response = await fetch(url);
            const responseText = await response.text();
            
            console.log('Recent activity response:', responseText.substring(0, 200));
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Recent activity JSON Parse Error:', e);
                console.error('Response was:', responseText);
                // Recent activity başarısız olsa da sayfa çalışsın
                return;
            }
            
            if (data.success) {
                this.populateRecentActivity(data.activities);
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
            // Recent activity hatasını kullanıcıya gösterme, sessizce geç
        }
    },
    
    /**
     * Audit tablosunu doldur
     */
    populateAuditTable(logs) {
        if (!this.elements.auditTableBody) return;
        
        if (logs.length === 0) {
            this.elements.auditTableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--light-grey);">
                        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5; display: block;"></i>
                        <div>Kriterlere uygun audit log bulunamadı</div>
                    </td>
                </tr>
            `;
            return;
        }
        
        const rows = logs.map(log => {
            const actionClass = this.getActionClass(log.action);
            const userAvatar = this.getUserAvatar(log.username || 'System');
            const relativeTime = this.getRelativeTime(log.created_at);
            
            return `
                <tr data-log-id="${log.id}">
                    <td>
                        <span style="font-family: var(--mono-font); color: var(--light-grey);">#${log.id}</span>
                    </td>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">${userAvatar}</div>
                            <div class="user-details">
                                <div class="username">${this.escapeHtml(log.username || 'System')}</div>
                                <div class="user-id">ID: ${log.user_id || 'N/A'}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="action-badge ${actionClass}">${this.escapeHtml(log.action)}</span>
                    </td>
                    <td>
                        <div class="target-info">
                            <div class="target-type">${this.escapeHtml(log.target_type || 'N/A')}</div>
                            <div class="target-id">${log.target_id || 'N/A'}</div>
                        </div>
                    </td>
                    <td>
                        <code style="font-size: 0.85rem; color: var(--light-grey);">${this.escapeHtml(log.ip_address || 'N/A')}</code>
                    </td>
                    <td>
                        <div class="date-info">
                            <div class="date-primary">${this.formatDate(log.created_at)}</div>
                            <div class="date-relative">${relativeTime}</div>
                        </div>
                    </td>
                    <td>
                        <button class="detail-btn" onclick="viewAuditDetail(${log.id})">
                            <i class="fas fa-eye"></i> Detay
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        this.elements.auditTableBody.innerHTML = rows;
    },
    
    /**
     * Son aktiviteleri doldur
     */
    populateRecentActivity(activities) {
        if (!this.elements.recentActivity) return;
        
        if (activities.length === 0) {
            this.elements.recentActivity.innerHTML = `
                <div style="text-align: center; color: var(--light-grey); padding: 2rem;">
                    <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5; display: block;"></i>
                    <div>Son aktivite bulunamadı</div>
                </div>
            `;
            return;
        }
        
        const activityHtml = activities.map(activity => {
            const icon = this.getActivityIcon(activity.action);
            const relativeTime = this.getRelativeTime(activity.created_at);
            
            return `
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="${icon}"></i>
                    </div>
                    <div class="activity-details">
                        <div class="activity-action">${this.escapeHtml(activity.action)}</div>
                        <div class="activity-time">${relativeTime}</div>
                    </div>
                </div>
            `;
        }).join('');
        
        this.elements.recentActivity.innerHTML = activityHtml;
    },
    
    /**
     * Pagination güncelle
     */
    updatePagination(pagination) {
        this.state.totalPages = pagination.total_pages;
        this.state.totalItems = pagination.total_items;
        this.state.currentPage = pagination.current_page;
        
        // Pagination bilgisi
        if (this.elements.paginationInfo) {
            const start = ((pagination.current_page - 1) * this.config.itemsPerPage) + 1;
            const end = Math.min(start + this.config.itemsPerPage - 1, pagination.total_items);
            this.elements.paginationInfo.textContent = 
                `${start}-${end} / ${pagination.total_items} kayıt`;
        }
        
        // Sayfa numaraları
        if (this.elements.pageNumbers) {
            this.elements.pageNumbers.innerHTML = this.generatePageNumbers();
        }
        
        // Prev/Next butonları
        if (this.elements.prevPage) {
            this.elements.prevPage.disabled = pagination.current_page <= 1;
        }
        if (this.elements.nextPage) {
            this.elements.nextPage.disabled = pagination.current_page >= pagination.total_pages;
        }
    },
    
    /**
     * Sayfa numaralarını oluştur
     */
    generatePageNumbers() {
        const current = this.state.currentPage;
        const total = this.state.totalPages;
        const delta = 2;
        const range = [];
        const rangeWithDots = [];
        
        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
            range.push(i);
        }
        
        if (current - delta > 2) {
            rangeWithDots.push(1, '...');
        } else {
            rangeWithDots.push(1);
        }
        
        rangeWithDots.push(...range);
        
        if (current + delta < total - 1) {
            rangeWithDots.push('...', total);
        } else if (total > 1) {
            rangeWithDots.push(total);
        }
        
        return rangeWithDots.map(page => {
            if (page === '...') {
                return '<span class="page-dots">...</span>';
            }
            
            const isActive = page === current;
            return `
                <button class="page-btn ${isActive ? 'active' : ''}" 
                        onclick="goToPage(${page})"
                        ${isActive ? 'disabled' : ''}>
                    ${page}
                </button>
            `;
        }).join('');
    },
    
    /**
     * Audit detayını görüntüle
     */
    async viewAuditDetail(logId) {
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}get_audit_detail.php?id=${logId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateAuditDetail(data.log);
                this.showModal(this.elements.auditDetailModal);
            } else {
                this.showError(data.message || 'Audit detayı yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error loading audit detail:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Audit detayını doldur
     */
    populateAuditDetail(log) {
        // Genel bilgiler
        this.setElementText('detailLogId', log.id);
        this.setElementText('detailUser', log.username || 'System');
        this.setElementText('detailAction', log.action);
        this.setElementText('detailTargetType', log.target_type || 'N/A');
        this.setElementText('detailTargetId', log.target_id || 'N/A');
        this.setElementText('detailIpAddress', log.ip_address || 'N/A');
        this.setElementText('detailCreatedAt', this.formatDateTime(log.created_at));
        this.setElementText('detailUserAgent', log.user_agent || 'N/A');
        
        // Eski değerler
        const oldValuesSection = document.getElementById('oldValuesSection');
        const oldValuesElement = document.getElementById('detailOldValues');
        if (log.old_values) {
            try {
                const oldValues = typeof log.old_values === 'string' ? 
                    JSON.parse(log.old_values) : log.old_values;
                oldValuesElement.textContent = JSON.stringify(oldValues, null, 2);
                oldValuesSection.style.display = 'block';
            } catch (e) {
                oldValuesElement.textContent = log.old_values;
                oldValuesSection.style.display = 'block';
            }
        } else {
            oldValuesSection.style.display = 'none';
        }
        
        // Yeni değerler
        const newValuesSection = document.getElementById('newValuesSection');
        const newValuesElement = document.getElementById('detailNewValues');
        if (log.new_values) {
            try {
                const newValues = typeof log.new_values === 'string' ? 
                    JSON.parse(log.new_values) : log.new_values;
                newValuesElement.textContent = JSON.stringify(newValues, null, 2);
                newValuesSection.style.display = 'block';
            } catch (e) {
                newValuesElement.textContent = log.new_values;
                newValuesSection.style.display = 'block';
            }
        } else {
            newValuesSection.style.display = 'none';
        }
    },
    
    /**
     * Export modalını aç
     */
    exportAuditLog() {
        this.showModal(this.elements.exportModal);
    },
    
    /**
     * Export işlemini gerçekleştir
     */
    async executeExport() {
        try {
            this.showLoading();
            
            const formData = new FormData();
            formData.append('start_date', document.getElementById('exportStartDate').value);
            formData.append('end_date', document.getElementById('exportEndDate').value);
            formData.append('format', document.getElementById('exportFormat').value);
            formData.append('include_user_agent', document.getElementById('includeUserAgent').checked);
            formData.append('include_values', document.getElementById('includeValues').checked);
            formData.append('include_ip_address', document.getElementById('includeIpAddress').checked);
            
            const response = await fetch(`${this.config.apiBaseUrl}export_audit_logs.php`, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                
                const format = document.getElementById('exportFormat').value;
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                a.download = `audit_log_${timestamp}.${format}`;
                
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.closeExportModal();
                this.showSuccess('Audit log başarıyla dışa aktarıldı');
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Error exporting audit log:', error);
            this.showError('Export işlemi sırasında hata oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Gelişmiş filtreleri aç/kapat
     */
    toggleAdvancedFilters() {
        if (this.elements.advancedFilters) {
            const isVisible = this.elements.advancedFilters.style.display !== 'none';
            this.elements.advancedFilters.style.display = isVisible ? 'none' : 'block';
        }
    },
    
    /**
     * Gelişmiş filtreleri uygula
     */
    applyAdvancedFilters() {
        this.state.filters.startDate = this.elements.startDate?.value || '';
        this.state.filters.endDate = this.elements.endDate?.value || '';
        this.state.filters.targetType = this.elements.targetTypeFilter?.value || '';
        this.state.filters.ipAddress = this.elements.ipFilter?.value || '';
        
        this.state.currentPage = 1;
        this.loadAuditData();
    },
    
    /**
     * Gelişmiş filtreleri temizle
     */
    clearAdvancedFilters() {
        if (this.elements.startDate) this.elements.startDate.value = '';
        if (this.elements.endDate) this.elements.endDate.value = '';
        if (this.elements.targetTypeFilter) this.elements.targetTypeFilter.value = '';
        if (this.elements.ipFilter) this.elements.ipFilter.value = '';
        
        this.state.filters.startDate = '';
        this.state.filters.endDate = '';
        this.state.filters.targetType = '';
        this.state.filters.ipAddress = '';
        
        this.state.currentPage = 1;
        this.loadAuditData();
    },
    
    /**
     * Tabloyu sırala
     */
    sortTable(field) {
        if (this.state.sortField === field) {
            this.state.sortDirection = this.state.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.state.sortField = field;
            this.state.sortDirection = 'desc';
        }
        
        this.updateSortIcons();
        this.loadAuditData();
    },
    
    /**
     * Sıralama ikonlarını güncelle
     */
    updateSortIcons() {
        const sortIcons = document.querySelectorAll('.sort-icon');
        sortIcons.forEach(icon => {
            icon.className = 'fas fa-sort sort-icon';
        });
        
        const currentSortIcon = document.querySelector(`[onclick="sortTable('${this.state.sortField}')"]`);
        if (currentSortIcon) {
            currentSortIcon.className = `fas fa-sort-${this.state.sortDirection === 'asc' ? 'up' : 'down'} sort-icon`;
        }
    },
    
    /**
     * Sayfa değiştir
     */
    changePage(direction) {
        const newPage = this.state.currentPage + direction;
        if (newPage >= 1 && newPage <= this.state.totalPages) {
            this.state.currentPage = newPage;
            this.loadAuditData();
        }
    },
    
    /**
     * Belirli sayfaya git
     */
    goToPage(page) {
        if (page >= 1 && page <= this.state.totalPages && page !== this.state.currentPage) {
            this.state.currentPage = page;
            this.loadAuditData();
        }
    },
    
    /**
     * Verileri yenile
     */
    refreshAuditData() {
        this.loadAuditData();
        this.loadRecentActivity();
        this.showSuccess('Veriler güncellendi');
    },
    
    /**
     * Otomatik yenilemeyi başlat
     */
    startAutoRefresh() {
        this.state.refreshTimer = setInterval(() => {
            this.loadRecentActivity();
        }, this.config.refreshInterval);
    },
    
    /**
     * Otomatik yenilemeyi durdur
     */
    stopAutoRefresh() {
        if (this.state.refreshTimer) {
            clearInterval(this.state.refreshTimer);
            this.state.refreshTimer = null;
        }
    },
    
    // Modal fonksiyonları
    showModal(modal) {
        if (!modal) return;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.classList.add('show'), 10);
    },
    
    hideModal(modal) {
        if (!modal) return;
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, this.config.animationDuration);
    },
    
    closeAuditDetailModal() {
        this.hideModal(this.elements.auditDetailModal);
    },
    
    closeExportModal() {
        this.hideModal(this.elements.exportModal);
    },
    
    closeAllModals() {
        this.closeAuditDetailModal();
        this.closeExportModal();
    },
    
    // Loading fonksiyonları
    showLoading() {
        this.state.isLoading = true;
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.classList.add('show');
        }
    },
    
    hideLoading() {
        this.state.isLoading = false;
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.classList.remove('show');
        }
    },
    
    // Utility fonksiyonları
    debounce(func, wait) {
        clearTimeout(this.state.searchTimeout);
        this.state.searchTimeout = setTimeout(func, wait);
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    setElementText(id, text) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = text;
        }
    },
    
    getActionClass(action) {
        if (action.includes('create') || action.includes('add')) return 'create';
        if (action.includes('update') || action.includes('edit')) return 'update';
        if (action.includes('delete') || action.includes('remove')) return 'delete';
        if (action.includes('view') || action.includes('access')) return 'view';
        if (action.includes('login') || action.includes('logout')) return 'login';
        return 'view';
    },
    
    getUserAvatar(username) {
        if (!username || username === 'System') return 'SYS';
        return username.charAt(0).toUpperCase();
    },
    
    getActivityIcon(action) {
        if (action.includes('create') || action.includes('add')) return 'fas fa-plus';
        if (action.includes('update') || action.includes('edit')) return 'fas fa-edit';
        if (action.includes('delete') || action.includes('remove')) return 'fas fa-trash';
        if (action.includes('login')) return 'fas fa-sign-in-alt';
        if (action.includes('logout')) return 'fas fa-sign-out-alt';
        if (action.includes('view') || action.includes('access')) return 'fas fa-eye';
        return 'fas fa-cog';
    },
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('tr-TR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('tr-TR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    },
    
    getRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        
        if (diffMins < 1) return 'Şimdi';
        if (diffMins < 60) return `${diffMins} dakika önce`;
        if (diffHours < 24) return `${diffHours} saat önce`;
        if (diffDays < 7) return `${diffDays} gün önce`;
        return this.formatDate(dateString);
    },
    
    showSuccess(message) {
        // Toast notification implementation
        this.showToast(message, 'success');
    },
    
    showError(message) {
        // Toast notification implementation
        this.showToast(message, 'error');
    },
    
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => document.body.removeChild(toast), 300);
        }, 3000);
    }
};

// Global fonksiyonlar (HTML'den çağrılabilir)
window.refreshAuditData = () => AuditManager.refreshAuditData();
window.exportAuditLog = () => AuditManager.exportAuditLog();
window.executeExport = () => AuditManager.executeExport();
window.toggleAdvancedFilters = () => AuditManager.toggleAdvancedFilters();
window.applyAdvancedFilters = () => AuditManager.applyAdvancedFilters();
window.clearAdvancedFilters = () => AuditManager.clearAdvancedFilters();
window.sortTable = (field) => AuditManager.sortTable(field);
window.changePage = (direction) => AuditManager.changePage(direction);
window.goToPage = (page) => AuditManager.goToPage(page);
window.viewAuditDetail = (logId) => AuditManager.viewAuditDetail(logId);
window.closeAuditDetailModal = () => AuditManager.closeAuditDetailModal();
window.closeExportModal = () => AuditManager.closeExportModal();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuditManager;
}

// CSS için gerekli toast stilleri
const toastStyles = `
<style>
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    min-width: 300px;
    background: var(--card-bg);
    border: 1px solid var(--border-1);
    border-radius: 8px;
    padding: 1rem;
    z-index: 30000;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.toast.show {
    transform: translateX(0);
    opacity: 1;
}

.toast.toast-success {
    border-left: 4px solid var(--success);
}

.toast.toast-error {
    border-left: 4px solid var(--danger);
}

.toast.toast-info {
    border-left: 4px solid var(--turquase);
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--lighter-grey);
}

.toast-content i {
    font-size: 1.2rem;
}

.toast-success .toast-content i {
    color: var(--success);
}

.toast-error .toast-content i {
    color: var(--danger);
}

.toast-info .toast-content i {
    color: var(--turquase);
}
</style>
`;

// Toast stillerini head'e ekle
if (!document.querySelector('#toast-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'toast-styles';
    styleElement.innerHTML = toastStyles;
    document.head.appendChild(styleElement);
}