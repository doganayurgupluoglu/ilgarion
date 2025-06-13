// /admin/manage_roles/js/manage_roles.js - Complete Version

const ManageRoles = {
    // Konfigürasyon
    config: {
        apiBaseUrl: '/admin/manage_roles/api/',
        debounceTime: 300,
        animationDuration: 300
    },
    
    // State management
    state: {
        currentRoleId: null,
        originalPermissions: [],
        isLoading: false,
        searchTimeout: null,
        filterTimeout: null
    },
    
    // DOM elements cache
    elements: {},
    
    /**
     * Modülü başlat
     */
    init() {
        this.cacheElements();
        this.bindEvents();
        this.initializeFilters();
        console.log('ManageRoles initialized');
    },
    
    /**
     * DOM elementlerini cache'le
     */
    cacheElements() {
        this.elements = {
            // Search and filter
            roleSearch: document.getElementById('roleSearch'),
            roleFilter: document.getElementById('roleFilter'),
            rolesGrid: document.getElementById('rolesGrid'),
            
            // Edit role modal
            editRoleModal: document.getElementById('editRoleModal'),
            editRoleForm: document.getElementById('editRoleForm'),
            editRoleId: document.getElementById('editRoleId'),
            editRoleName: document.getElementById('editRoleName'),
            editRoleDescription: document.getElementById('editRoleDescription'),
            editRoleColor: document.getElementById('editRoleColor'),
            editRolePriority: document.getElementById('editRolePriority'),
            editModalTitle: document.getElementById('editModalTitle'),
            
            // Permissions modal
            permissionsModal: document.getElementById('permissionsModal'),
            permissionsRoleId: document.getElementById('permissionsRoleId'),
            permissionsContainer: document.getElementById('permissionsContainer'),
            permissionsModalTitle: document.getElementById('permissionsModalTitle'),
            permissionSearch: document.getElementById('permissionSearch'),
            
            // Loading
            loadingOverlay: document.getElementById('loadingOverlay')
        };
    },
    
    /**
     * Event listener'ları bağla
     */
    bindEvents() {
        // Search functionality
        if (this.elements.roleSearch) {
            this.elements.roleSearch.addEventListener('input', (e) => {
                this.debounce(() => this.filterRoles(), this.config.debounceTime);
            });
        }
        
        // Filter functionality
        if (this.elements.roleFilter) {
            this.elements.roleFilter.addEventListener('change', () => {
                this.filterRoles();
            });
        }
        
        // Permission search
        if (this.elements.permissionSearch) {
            this.elements.permissionSearch.addEventListener('input', (e) => {
                this.debounce(() => this.filterPermissions(), this.config.debounceTime);
            });
        }
        
        // Permission checkbox changes
        document.addEventListener('change', (e) => {
            if (e.target.matches('.permission-switch')) {
                this.handlePermissionChange(e.target);
            }
        });
        
        // Modal close on outside click
        this.elements.editRoleModal?.addEventListener('click', (e) => {
            if (e.target === this.elements.editRoleModal) {
                this.closeEditRoleModal();
            }
        });
        
        this.elements.permissionsModal?.addEventListener('click', (e) => {
            if (e.target === this.elements.permissionsModal) {
                this.closePermissionsModal();
            }
        });
        
        // Escape key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeEditRoleModal();
                this.closePermissionsModal();
            }
        });
        
        // Form validation
        if (this.elements.editRoleName) {
            this.elements.editRoleName.addEventListener('input', (e) => {
                this.validateRoleName(e.target.value);
            });
        }
    },
    
    /**
     * Filter sistemi başlat
     */
    initializeFilters() {
        this.filterRoles();
    },
    
    /**
     * Rolleri filtrele
     */
    filterRoles() {
        const searchTerm = this.elements.roleSearch?.value.toLowerCase() || '';
        const filterType = this.elements.roleFilter?.value || 'all';
        const roleCards = this.elements.rolesGrid?.querySelectorAll('.role-card') || [];
        
        let visibleCount = 0;
        
        roleCards.forEach(card => {
            const roleName = card.dataset.roleName || '';
            const isManageable = card.querySelector('.role-card-actions .btn:not(.btn-outline)') !== null;
            const isProtected = card.querySelector('.role-name .fa-shield-alt') !== null;
            
            let showCard = true;
            
            // Search filter
            if (searchTerm && !roleName.includes(searchTerm)) {
                showCard = false;
            }
            
            // Type filter
            if (filterType !== 'all') {
                switch (filterType) {
                    case 'manageable':
                        if (!isManageable) showCard = false;
                        break;
                    case 'protected':
                        if (!isProtected) showCard = false;
                        break;
                }
            }
            
            if (showCard) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });
        
        // No results message
        this.updateNoResultsMessage(visibleCount);
    },
    
    /**
     * Yetkileri filtrele - Enhanced
     */
    filterPermissions() {
        const searchTerm = this.elements.permissionSearch?.value.toLowerCase() || '';
        const permissionGroups = this.elements.permissionsContainer?.querySelectorAll('.permission-group') || [];
        
        permissionGroups.forEach(group => {
            const permissionCards = group.querySelectorAll('.permission-card');
            let visibleInGroup = 0;
            
            permissionCards.forEach(card => {
                const permissionName = card.querySelector('.permission-name')?.textContent.toLowerCase() || '';
                const permissionKey = card.querySelector('.permission-key')?.textContent.toLowerCase() || '';
                const searchText = permissionName + ' ' + permissionKey;
                
                if (!searchTerm || searchText.includes(searchTerm)) {
                    card.style.display = 'flex';
                    visibleInGroup++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Grup görünürlüğü
            const grid = group.querySelector('.permission-grid');
            if (visibleInGroup > 0) {
                group.style.display = 'block';
                if (grid && !grid.classList.contains('collapsed')) {
                    grid.style.display = 'grid';
                }
            } else {
                group.style.display = 'none';
            }
        });
    },
    
    /**
     * Sonuç bulunamadı mesajını güncelle
     */
    updateNoResultsMessage(visibleCount) {
        let noResultsMsg = this.elements.rolesGrid?.querySelector('.no-results-message');
        
        if (visibleCount === 0) {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: var(--light-grey);">
                        <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 0.5rem 0;">Rol bulunamadı</h3>
                        <p style="margin: 0;">Arama kriterlerinizi değiştirmeyi deneyin.</p>
                    </div>
                `;
                this.elements.rolesGrid.appendChild(noResultsMsg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    },
    
    /**
     * Rol düzenleme modalını aç
     */
    async openEditRoleModal(roleId = null) {
        this.state.currentRoleId = roleId;
        
        if (roleId) {
            // Mevcut rol düzenleme
            await this.loadRoleData(roleId);
            this.elements.editModalTitle.textContent = 'Rol Düzenle';
        } else {
            // Yeni rol oluşturma
            this.clearEditForm();
            this.elements.editModalTitle.textContent = 'Yeni Rol Oluştur';
        }
        
        this.showModal(this.elements.editRoleModal);
        this.elements.editRoleName?.focus();
    },
    
    /**
     * Rol düzenleme modalını kapat
     */
    closeEditRoleModal() {
        this.hideModal(this.elements.editRoleModal);
        this.state.currentRoleId = null;
        this.clearEditForm();
    },
    
    /**
     * Yetki yönetimi modalını aç
     */
    async openPermissionsModal(roleId) {
        this.state.currentRoleId = roleId;
        this.elements.permissionsRoleId.value = roleId;
        
        // Rol adını modal başlığında göster
        const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
        const roleName = roleCard?.querySelector('.role-name')?.textContent.trim() || 'Bilinmeyen Rol';
        this.elements.permissionsModalTitle.textContent = `${roleName} - Yetki Yönetimi`;
        
        // Mevcut yetkileri yükle
        await this.loadRolePermissions(roleId);
        
        this.showModal(this.elements.permissionsModal);
    },
    
    /**
     * Yetki yönetimi modalını kapat
     */
    closePermissionsModal() {
        this.hideModal(this.elements.permissionsModal);
        this.state.currentRoleId = null;
        this.clearPermissionSearch();
    },
    
    /**
     * Modal göster
     */
    showModal(modal) {
        if (!modal) return;
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    },
    
    /**
     * Modal gizle
     */
    hideModal(modal) {
        if (!modal) return;
        
        modal.classList.remove('show');
        
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, this.config.animationDuration);
    },
    
    /**
     * Rol verilerini yükle
     */
    async loadRoleData(roleId) {
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}get_role.php?id=${roleId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateEditForm(data.role);
            } else {
                this.showError(data.message || 'Rol verileri yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error loading role data:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Rol yetkilerini yükle
     */
    async loadRolePermissions(roleId) {
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}get_role_permissions.php?id=${roleId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populatePermissions(data.permissions);
                this.state.originalPermissions = [...data.permissions];
            } else {
                this.showError(data.message || 'Rol yetkileri yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error loading role permissions:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Düzenleme formunu doldur
     */
    populateEditForm(role) {
        this.elements.editRoleId.value = role.id;
        this.elements.editRoleName.value = role.name;
        this.elements.editRoleDescription.value = role.description || '';
        this.elements.editRoleColor.value = role.color;
        this.elements.editRolePriority.value = role.priority;
    },
    
    /**
     * Yetkileri checkbox'larda işaretle
     */
    populatePermissions(permissions) {
        const checkboxes = this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]') || [];
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = permissions.includes(checkbox.value);
            
            // Card state güncelle
            const card = checkbox.closest('.permission-card');
            if (card) {
                if (checkbox.checked) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            }
        });
        
        // İstatistikleri güncelle
        this.updatePermissionStats();
        this.updateGroupStats();
    },
    
    /**
     * Düzenleme formunu temizle
     */
    clearEditForm() {
        if (this.elements.editRoleForm) {
            this.elements.editRoleForm.reset();
            this.elements.editRoleColor.value = '#bd912a';
            this.elements.editRolePriority.value = '999';
        }
    },
    
    /**
     * Yetki aramasını temizle
     */
    clearPermissionSearch() {
        if (this.elements.permissionSearch) {
            this.elements.permissionSearch.value = '';
            this.filterPermissions();
        }
    },
    
    /**
     * Rol kaydet
     */
    async saveRole() {
        if (!this.validateForm()) {
            return;
        }
        
        try {
            this.showLoading();
            
            const formData = new FormData(this.elements.editRoleForm);
            const endpoint = this.state.currentRoleId ? 'update_role.php' : 'create_role.php';
            
            const response = await fetch(`${this.config.apiBaseUrl}${endpoint}`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message || 'Rol başarıyla kaydedildi');
                this.closeEditRoleModal();
                this.refreshRoleData();
            } else {
                this.showError(data.message || 'Rol kaydedilirken hata oluştu');
            }
        } catch (error) {
            console.error('Error saving role:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Yetkileri kaydet
     */
    async savePermissions() {
        try {
            this.showLoading();
            
            const checkboxes = this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
            const selectedPermissions = Array.from(checkboxes).map(cb => cb.value);
            
            const formData = new FormData();
            formData.append('role_id', this.state.currentRoleId);
            formData.append('permissions', JSON.stringify(selectedPermissions));
            
            const response = await fetch(`${this.config.apiBaseUrl}update_permissions.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message || 'Yetkiler başarıyla güncellendi');
                this.closePermissionsModal();
                this.refreshRoleData();
            } else {
                this.showError(data.message || 'Yetkiler güncellenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error saving permissions:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Rol sil
     */
    async deleteRole(roleId) {
        const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
        const roleName = roleCard?.querySelector('.role-name')?.textContent.trim() || 'Bu rol';
        
        if (!confirm(`"${roleName}" rolünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`)) {
            return;
        }
        
        try {
            this.showLoading();
            
            const formData = new FormData();
            formData.append('role_id', roleId);
            
            const response = await fetch(`${this.config.apiBaseUrl}delete_role.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message || 'Rol başarıyla silindi');
                this.refreshRoleData();
            } else {
                this.showError(data.message || 'Rol silinirken hata oluştu');
            }
        } catch (error) {
            console.error('Error deleting role:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Permission değişikliği handler
     */
    handlePermissionChange(checkbox) {
        const card = checkbox.closest('.permission-card');
        if (card) {
            if (checkbox.checked) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        }
        
        // İstatistikleri güncelle
        this.updatePermissionStats();
        this.updateGroupStats();
        this.updateChangeIndicator();
    },
    
    /**
     * Permission istatistiklerini güncelle
     */
    updatePermissionStats() {
        const selectedCount = this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]:checked').length || 0;
        const totalCount = this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]').length || 0;
        
        const selectedCountEl = document.getElementById('selectedCount');
        const totalCountEl = document.getElementById('totalCount');
        
        if (selectedCountEl) selectedCountEl.textContent = selectedCount;
        if (totalCountEl) totalCountEl.textContent = totalCount;
    },
    
    /**
     * Grup istatistiklerini güncelle
     */
    updateGroupStats() {
        const groups = this.elements.permissionsContainer?.querySelectorAll('.permission-group') || [];
        
        groups.forEach(group => {
            const checkboxes = group.querySelectorAll('input[type="checkbox"]');
            const checkedBoxes = group.querySelectorAll('input[type="checkbox"]:checked');
            const groupSelectedEl = group.querySelector('.group-selected');
            
            if (groupSelectedEl) {
                groupSelectedEl.textContent = `${checkedBoxes.length} seçili`;
                
                // Grup rengi güncelle
                if (checkedBoxes.length === 0) {
                    groupSelectedEl.style.color = 'var(--light-grey)';
                } else if (checkedBoxes.length === checkboxes.length) {
                    groupSelectedEl.style.color = 'var(--turquase)';
                } else {
                    groupSelectedEl.style.color = 'var(--gold)';
                }
            }
        });
    },
    
    /**
     * Değişiklik indikatörünü güncelle
     */
    updateChangeIndicator() {
        const currentPermissions = Array.from(
            this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]:checked') || []
        ).map(cb => cb.value);
        
        const originalPermissions = this.state.originalPermissions || [];
        const hasChanges = !this.arraysEqual(currentPermissions.sort(), originalPermissions.sort());
        
        const indicator = document.getElementById('changeIndicator');
        if (indicator) {
            if (hasChanges) {
                indicator.classList.add('has-changes');
                indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i> Kaydedilmemiş değişiklikler var';
            } else {
                indicator.classList.remove('has-changes');
                indicator.innerHTML = '<i class="fas fa-info-circle"></i> Değişiklik yapmadınız';
            }
        }
    },
    
    /**
     * Array eşitlik kontrolü
     */
    arraysEqual(a, b) {
        return a.length === b.length && a.every((val, i) => val === b[i]);
    },
    
    /**
     * Grup collapse/expand toggle
     */
    toggleGroupCollapse(groupName) {
        const group = this.elements.permissionsContainer?.querySelector(`.permission-group[data-group="${groupName}"]`);
        if (!group) return;
        
        const grid = group.querySelector('.permission-grid');
        const collapseBtn = group.querySelector('.group-collapse');
        
        if (grid && collapseBtn) {
            grid.classList.toggle('collapsed');
            collapseBtn.classList.toggle('collapsed');
            
            // Icon değiştir
            const icon = collapseBtn.querySelector('i');
            if (icon) {
                if (collapseBtn.classList.contains('collapsed')) {
                    icon.className = 'fas fa-chevron-down';
                } else {
                    icon.className = 'fas fa-chevron-up';
                }
            }
        }
    },
    
    /**
     * Tüm yetkileri seç - Enhanced
     */
    selectAllPermissions() {
        const checkboxes = this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]') || [];
        checkboxes.forEach(checkbox => {
            if (checkbox.style.display !== 'none' && !checkbox.disabled) {
                checkbox.checked = true;
                this.handlePermissionChange(checkbox);
            }
        });
    },
    
    /**
     * Tüm yetkileri temizle - Enhanced
     */
    clearAllPermissions() {
        const checkboxes = this.elements.permissionsContainer?.querySelectorAll('input[type="checkbox"]') || [];
        checkboxes.forEach(checkbox => {
            if (!checkbox.disabled) {
                checkbox.checked = false;
                this.handlePermissionChange(checkbox);
            }
        });
    },
    
    /**
     * Grup yetkilerini toggle et - Enhanced
     */
    toggleGroupPermissions(groupName) {
        const group = this.elements.permissionsContainer?.querySelector(`[data-group="${groupName}"]`);
        if (!group) return;
        
        const checkboxes = group.querySelectorAll('input[type="checkbox"]');
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
            cb.closest('.permission-card').style.display !== 'none' && !cb.disabled
        );
        
        if (visibleCheckboxes.length === 0) return;
        
        const allChecked = visibleCheckboxes.every(cb => cb.checked);
        const shouldCheck = !allChecked;
        
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = shouldCheck;
            this.handlePermissionChange(checkbox);
        });
    },
    
    /**
     * Form validasyonu
     */
    validateForm() {
        const roleName = this.elements.editRoleName?.value.trim();
        const priority = parseInt(this.elements.editRolePriority?.value);
        
        if (!roleName) {
            this.showError('Rol adı boş olamaz');
            this.elements.editRoleName?.focus();
            return false;
        }
        
        if (!this.validateRoleName(roleName)) {
            return false;
        }
        
        if (isNaN(priority) || priority < 1 || priority > 999) {
            this.showError('Öncelik 1-999 arasında olmalıdır');
            this.elements.editRolePriority?.focus();
            return false;
        }
        
        return true;
    },
    
    /**
     * Rol adı validasyonu
     */
    validateRoleName(roleName) {
        const regex = /^[a-z0-9_]{2,50}$/;
        
        if (!regex.test(roleName)) {
            this.showError('Rol adı 2-50 karakter olmalı ve sadece küçük harf, rakam ve alt çizgi içermelidir');
            return false;
        }
        
        return true;
    },
    
    /**
     * Sayfa verilerini yenile
     */
    refreshRoleData() {
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    },
    
    /**
     * Loading göster
     */
    showLoading() {
        this.state.isLoading = true;
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.style.display = 'flex';
            setTimeout(() => {
                this.elements.loadingOverlay.classList.add('show');
            }, 10);
        }
    },
    
    /**
     * Loading gizle
     */
    hideLoading() {
        this.state.isLoading = false;
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.classList.remove('show');
            setTimeout(() => {
                this.elements.loadingOverlay.style.display = 'none';
            }, this.config.animationDuration);
        }
    },
    
    /**
     * Başarı mesajı göster
     */
    showSuccess(message) {
        this.showNotification(message, 'success');
    },
    
    /**
     * Hata mesajı göster
     */
    showError(message) {
        this.showNotification(message, 'error');
    },
    
    /**
     * Bilgi mesajı göster
     */
    showInfo(message) {
        this.showNotification(message, 'info');
    },
    
    /**
     * Notification göster
     */
    showNotification(message, type = 'info') {
        // Mevcut notification'ları temizle
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(n => n.remove());
        
        // Yeni notification oluştur
        const notification = document.createElement('div');
        notification.className = `notification-toast notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // CSS stilleri
        notification.style.cssText = `
            position: fixed;
            top: calc(var(--navbar-height) + 2rem);
            right: 2rem;
            background: var(--card-bg);
            border: 1px solid var(--border-1-featured);
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            z-index: 30000;
            max-width: 400px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        `;
        
        // Type'a göre stil
        const borderColors = {
            success: 'var(--turquase)',
            error: 'var(--red)', 
            info: 'var(--gold)',
            warning: '#ff9800'
        };
        
        notification.style.borderLeftColor = borderColors[type] || borderColors.info;
        notification.style.borderLeftWidth = '4px';
        
        // Content styling
        const content = notification.querySelector('.notification-content');
        content.style.cssText = `
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--lighter-grey);
            font-size: 0.9rem;
            line-height: 1.4;
        `;
        
        const icon = notification.querySelector('.notification-content i');
        icon.style.color = borderColors[type] || borderColors.info;
        icon.style.fontSize = '1.1rem';
        
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: var(--light-grey);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            margin-left: auto;
        `;
        
        // DOM'a ekle
        document.body.appendChild(notification);
        
        // Animasyon
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            this.removeNotification(notification);
        }, 5000);
        
        // Close button hover
        closeBtn.addEventListener('mouseenter', () => {
            closeBtn.style.backgroundColor = 'var(--transparent-red)';
            closeBtn.style.color = 'var(--red)';
        });
        
        closeBtn.addEventListener('mouseleave', () => {
            closeBtn.style.backgroundColor = 'transparent';
            closeBtn.style.color = 'var(--light-grey)';
        });
    },
    
    /**
     * Notification icon'unu getir
     */
    getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-triangle'
        };
        return icons[type] || icons.info;
    },
    
    /**
     * Notification'ı kaldır
     */
    removeNotification(notification) {
        if (!notification || !notification.parentElement) return;
        
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.parentElement.removeChild(notification);
            }
        }, 300);
    },
    
    /**
     * Debounce utility
     */
    debounce(func, wait) {
        clearTimeout(this.state.searchTimeout);
        this.state.searchTimeout = setTimeout(func, wait);
    },
    
    /**
     * Rol detaylarını görüntüle
     */
    viewRoleDetails(roleId) {
        // Readonly modal olarak permissions modal'ını kullan
        this.openPermissionsModal(roleId);
        
        // Modal'ı readonly yap
        setTimeout(() => {
            const modal = this.elements.permissionsModal;
            const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
            const buttons = modal.querySelectorAll('.permission-actions button, .modal-footer .btn-primary');
            
            checkboxes.forEach(cb => cb.disabled = true);
            buttons.forEach(btn => btn.style.display = 'none');
            
            // Başlığı güncelle
            this.elements.permissionsModalTitle.textContent = 
                this.elements.permissionsModalTitle.textContent.replace('Yetki Yönetimi', 'Yetki Görüntüleme');
        }, 100);
    }
};

// Global fonksiyonlar (HTML'den çağrılabilir)
window.openCreateRoleModal = () => ManageRoles.openEditRoleModal();
window.editRole = (roleId) => ManageRoles.openEditRoleModal(roleId);
window.managePermissions = (roleId) => ManageRoles.openPermissionsModal(roleId);
window.deleteRole = (roleId) => ManageRoles.deleteRole(roleId);
window.viewRoleDetails = (roleId) => ManageRoles.viewRoleDetails(roleId);
window.refreshRoleData = () => ManageRoles.refreshRoleData();

// Modal fonksiyonları
window.closeEditRoleModal = () => ManageRoles.closeEditRoleModal();
window.closePermissionsModal = () => ManageRoles.closePermissionsModal();
window.saveRole = () => ManageRoles.saveRole();
window.savePermissions = () => ManageRoles.savePermissions();

// Permission fonksiyonları - Enhanced
window.selectAllPermissions = () => ManageRoles.selectAllPermissions();
window.clearAllPermissions = () => ManageRoles.clearAllPermissions();
window.toggleGroupPermissions = (groupName) => ManageRoles.toggleGroupPermissions(groupName);
window.toggleGroupCollapse = (groupName) => ManageRoles.toggleGroupCollapse(groupName);

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ManageRoles;
}