// /admin/users/js/manage_users.js - Complete Version

const ManageUsers = {
    // Konfigürasyon
    config: {
        apiBaseUrl: '/admin/users/api/',
        debounceTime: 300,
        animationDuration: 300,
        perPage: 20
    },
    
    // State management
    state: {
        currentUserId: null,
        currentPage: 1,
        totalPages: 1,
        isLoading: false,
        searchTimeout: null,
        filterTimeout: null,
        selectedUsers: new Set(),
        hasChanges: false,
        originalUserData: null
    },
    
    // DOM elements cache
    elements: {},
    
    /**
     * Modülü başlat
     */
    init() {
        this.cacheElements();
        this.bindEvents();
        this.loadUsersData();
        console.log('ManageUsers initialized');
    },
    
    /**
     * DOM elementlerini cache'le
     */
    cacheElements() {
        this.elements = {
            // Search and filter
            userSearch: document.getElementById('userSearch'),
            statusFilter: document.getElementById('statusFilter'),
            roleFilter: document.getElementById('roleFilter'),
            usersTable: document.getElementById('usersTable'),
            usersTableBody: document.getElementById('usersTableBody'),
            usersLoading: document.getElementById('usersLoading'),
            selectAllUsers: document.getElementById('selectAllUsers'),
            paginationContainer: document.getElementById('paginationContainer'),
            
            // Edit user modal
            editUserModal: document.getElementById('editUserModal'),
            editUserForm: document.getElementById('editUserForm'),
            editUserId: document.getElementById('editUserId'),
            editUsername: document.getElementById('editUsername'),
            editEmail: document.getElementById('editEmail'),
            editIngameName: document.getElementById('editIngameName'),
            editDiscordUsername: document.getElementById('editDiscordUsername'),
            editProfileInfo: document.getElementById('editProfileInfo'),
            editUserStatus: document.getElementById('editUserStatus'),
            editModalTitle: document.getElementById('editModalTitle'),
            
            // Tabs
            tabButtons: document.querySelectorAll('.tab-btn'),
            tabContents: document.querySelectorAll('.tab-content'),
            
            // Roles management
            userRolesList: document.getElementById('userRolesList'),
            availableRolesList: document.getElementById('availableRolesList'),
            
            // Skills management
            userSkillsList: document.getElementById('userSkillsList'),
            availableSkillsList: document.getElementById('availableSkillsList'),
            skillsSearch: document.getElementById('skillsSearch'),
            
            // Status history
            statusHistoryList: document.getElementById('statusHistoryList'),
            
            // Bulk actions modal
            bulkActionsModal: document.getElementById('bulkActionsModal'),
            selectedUsersInfo: document.getElementById('selectedUsersInfo'),
            selectedCount: document.getElementById('selectedCount'),
            
            // Loading and change indicator
            loadingOverlay: document.getElementById('loadingOverlay'),
            changeIndicator: document.getElementById('changeIndicator')
        };
    },
    
    /**
     * Event listener'ları bağla
     */
    bindEvents() {
        // Search functionality
        if (this.elements.userSearch) {
            this.elements.userSearch.addEventListener('input', (e) => {
                this.debounce(() => {
                    this.state.currentPage = 1;
                    this.loadUsersData();
                }, this.config.debounceTime);
            });
        }
        
        // Status filter
        if (this.elements.statusFilter) {
            this.elements.statusFilter.addEventListener('change', () => {
                this.state.currentPage = 1;
                this.loadUsersData();
            });
        }
        
        // Role filter
        if (this.elements.roleFilter) {
            this.elements.roleFilter.addEventListener('change', () => {
                this.state.currentPage = 1;
                this.loadUsersData();
            });
        }
        
        // Select all users
        if (this.elements.selectAllUsers) {
            this.elements.selectAllUsers.addEventListener('change', (e) => {
                this.toggleSelectAllUsers(e.target.checked);
            });
        }
        
        // Tab switching
        this.elements.tabButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(btn.dataset.tab);
            });
        });
        
        // Skills search
        if (this.elements.skillsSearch) {
            this.elements.skillsSearch.addEventListener('input', (e) => {
                this.filterSkills(e.target.value);
            });
        }
        
        // Form change detection
        if (this.elements.editUserForm) {
            this.elements.editUserForm.addEventListener('input', () => {
                this.detectChanges();
            });
        }
        
        // Modal click outside to close
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                if (e.target === this.elements.editUserModal) {
                    this.closeEditUserModal();
                } else if (e.target === this.elements.bulkActionsModal) {
                    this.closeBulkActionsModal();
                }
            }
        });
    },
    
    /**
     * Kullanıcı verilerini yükle
     */
    async loadUsersData() {
        try {
            // Loading'i göster
            this.showUsersLoading();
            
            const params = new URLSearchParams({
                page: this.state.currentPage,
                per_page: this.config.perPage,
                search: this.elements.userSearch?.value || '',
                status: this.elements.statusFilter?.value || 'all',
                role: this.elements.roleFilter?.value || 'all'
            });
            
            const response = await fetch(`${this.config.apiBaseUrl}get_users.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateUsersTable(data.users);
                this.updatePagination(data.pagination);
                this.state.totalPages = data.pagination.total_pages;
            } else {
                this.showError(data.message || 'Kullanıcılar yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            // Her durumda loading'i gizle
            this.hideUsersLoading();
        }
    },
    
    /**
     * Kullanıcı tablosunu doldur
     */
    populateUsersTable(users) {
        if (!this.elements.usersTableBody) return;
        
        // Loading'i gizle
        if (this.elements.usersLoading) {
            this.elements.usersLoading.style.display = 'none';
        }
        
        this.elements.usersTableBody.innerHTML = '';
        
        if (users.length === 0) {
            this.elements.usersTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--light-grey);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        Hiç kullanıcı bulunamadı
                    </td>
                </tr>
            `;
            return;
        }
        
        users.forEach(user => {
            const row = this.createUserRow(user);
            this.elements.usersTableBody.appendChild(row);
        });
        
        // Clear selected users when reloading
        this.state.selectedUsers.clear();
        this.updateSelectAllState();
        this.updateBulkActionsState();
    },
    
    /**
     * Kullanıcı satırı oluştur
     */
    createUserRow(user) {
        const row = document.createElement('tr');
        row.dataset.userId = user.id;
        
        // Avatar URL veya placeholder
        const avatarHtml = user.avatar_path ? 
            `<img src="/${user.avatar_path}" alt="${user.username}" class="user-avatar">` :
            `<div class="user-avatar-placeholder"><i class="fas fa-user"></i></div>`;
        
        // Roller
        const rolesHtml = user.roles?.map(role => 
            `<span class="role-tag" style="border-color: ${role.color}; color: ${role.color};">${role.name}</span>`
        ).join('') || '<span style="color: var(--light-grey); font-style: italic;">Rol yok</span>';
        
        // Skill tags
        const skillsHtml = user.skills?.map(skill => 
            `<span class="skill-tag">${skill.tag_name}</span>`
        ).join('') || '<span style="color: var(--light-grey); font-style: italic;">Skill yok</span>';
        
        // Durum badge
        const statusBadge = `<span class="status-badge ${user.status}">${this.getStatusText(user.status)}</span>`;
        
        // Tarih formatla
        const createdDate = new Date(user.created_at).toLocaleDateString('tr-TR', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="user-checkbox" data-user-id="${user.id}">
            </td>
            <td>
                <div class="user-info">
                    ${avatarHtml}
                    <div class="user-details">
                        <h4 class="user-name">
                            <a href="/view_profile.php?user_id=${user.id}" class="user-profile-link" target="_blank">
                                ${this.escapeHtml(user.username)}
                            </a>
                        </h4>
                        <p class="user-ingame">${this.escapeHtml(user.ingame_name)}</p>
                    </div>
                </div>
            </td>
            <td>
                <div class="user-contact">
                    <div class="user-email">
                        <i class="fas fa-envelope"></i>
                        ${this.escapeHtml(user.email)}
                    </div>
                    ${user.discord_username ? `
                        <div class="user-discord">
                            <i class="fab fa-discord"></i>
                            ${this.escapeHtml(user.discord_username)}
                        </div>
                    ` : ''}
                </div>
            </td>
            <td>${statusBadge}</td>
            <td><div class="user-roles">${rolesHtml}</div></td>
            <td><div class="user-skills">${skillsHtml}</div></td>
            <td><span class="user-date">${createdDate}</span></td>
            <td>
                <div class="user-actions">
                    <button class="action-btn" onclick="editUser(${user.id})" title="Düzenle">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn" onclick="viewUserDetails(${user.id})" title="Detayları Görüntüle">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${this.canDeleteUser(user) ? `
                        <button class="action-btn danger" onclick="deleteUser(${user.id})" title="Sil">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
        
        // Checkbox event listener
        const checkbox = row.querySelector('.user-checkbox');
        checkbox.addEventListener('change', (e) => {
            if (e.target.checked) {
                this.state.selectedUsers.add(parseInt(user.id));
            } else {
                this.state.selectedUsers.delete(parseInt(user.id));
            }
            this.updateSelectAllState();
            this.updateBulkActionsState();
        });
        
        return row;
    },
    
    /**
     * Durum metnini getir
     */
    getStatusText(status) {
        const statusTexts = {
            'pending': 'Bekliyor',
            'approved': 'Onaylı',
            'suspended': 'Askıya Alınmış',
            'rejected': 'Reddedilmiş'
        };
        return statusTexts[status] || status;
    },
    
    /**
     * Kullanıcı silinebilir mi kontrolü
     */
    canDeleteUser(user) {
        // Bu kontrolü backend'den gelen yetki bilgisine göre yapabilirsiniz
        return user.can_delete !== false;
    },
    
    /**
     * Pagination güncelle
     */
    updatePagination(pagination) {
        if (!this.elements.paginationContainer) return;
        
        const { current_page, total_pages, total_users, start_index, end_index } = pagination;
        
        let paginationHtml = `
            <div class="pagination-info">
                ${total_users} kullanıcıdan ${start_index}-${end_index} arası gösteriliyor
            </div>
            <div class="pagination">
        `;
        
        // Previous button
        paginationHtml += `
            <button class="page-btn" ${current_page <= 1 ? 'disabled' : ''} 
                    onclick="ManageUsers.goToPage(${current_page - 1})">
                <i class="fas fa-chevron-left"></i>
            </button>
        `;
        
        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(total_pages, current_page + 2);
        
        if (startPage > 1) {
            paginationHtml += `<button class="page-btn" onclick="ManageUsers.goToPage(1)">1</button>`;
            if (startPage > 2) {
                paginationHtml += `<span class="page-btn" style="cursor: default;">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `
                <button class="page-btn ${i === current_page ? 'active' : ''}" 
                        onclick="ManageUsers.goToPage(${i})">${i}</button>
            `;
        }
        
        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                paginationHtml += `<span class="page-btn" style="cursor: default;">...</span>`;
            }
            paginationHtml += `<button class="page-btn" onclick="ManageUsers.goToPage(${total_pages})">${total_pages}</button>`;
        }
        
        // Next button
        paginationHtml += `
            <button class="page-btn" ${current_page >= total_pages ? 'disabled' : ''} 
                    onclick="ManageUsers.goToPage(${current_page + 1})">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
        
        paginationHtml += '</div>';
        
        this.elements.paginationContainer.innerHTML = paginationHtml;
    },
    
    /**
     * Sayfaya git
     */
    goToPage(page) {
        if (page < 1 || page > this.state.totalPages || page === this.state.currentPage) {
            return;
        }
        
        this.state.currentPage = page;
        this.loadUsersData();
    },
    
    /**
     * Tüm kullanıcıları seç/seçimi kaldır
     */
    toggleSelectAllUsers(checked) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            const userId = parseInt(checkbox.dataset.userId);
            if (checked) {
                this.state.selectedUsers.add(userId);
            } else {
                this.state.selectedUsers.delete(userId);
            }
        });
        this.updateBulkActionsState();
    },
    
    /**
     * Select all state'ini güncelle
     */
    updateSelectAllState() {
        if (!this.elements.selectAllUsers) return;
        
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        
        if (checkboxes.length === 0) {
            this.elements.selectAllUsers.indeterminate = false;
            this.elements.selectAllUsers.checked = false;
        } else if (checkedBoxes.length === checkboxes.length) {
            this.elements.selectAllUsers.indeterminate = false;
            this.elements.selectAllUsers.checked = true;
        } else if (checkedBoxes.length > 0) {
            this.elements.selectAllUsers.indeterminate = true;
            this.elements.selectAllUsers.checked = false;
        } else {
            this.elements.selectAllUsers.indeterminate = false;
            this.elements.selectAllUsers.checked = false;
        }
    },
    
    /**
     * Bulk actions state'ini güncelle
     */
    updateBulkActionsState() {
        if (this.elements.selectedCount) {
            this.elements.selectedCount.textContent = this.state.selectedUsers.size;
        }
    },
    
    /**
     * Kullanıcı düzenleme modalını aç
     */
    async openEditUserModal(userId) {
        this.state.currentUserId = userId;
        this.state.hasChanges = false;
        
        // Modal başlığını güncelle
        this.elements.editModalTitle.textContent = 'Kullanıcı Düzenle';
        
        // Kullanıcı verilerini yükle
        await this.loadUserData(userId);
        
        // İlk tab'ı aktif yap
        this.switchTab('basic');
        
        this.showModal(this.elements.editUserModal);
    },
    
    /**
     * Kullanıcı verilerini yükle
     */
    async loadUserData(userId) {
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}get_user.php?id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateEditForm(data.user);
                this.state.originalUserData = JSON.parse(JSON.stringify(data.user));
                await this.loadUserRoles(userId);
                await this.loadUserSkills(userId);
                await this.loadStatusHistory(userId);
            } else {
                this.showError(data.message || 'Kullanıcı verileri yüklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error loading user data:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Düzenleme formunu doldur
     */
    populateEditForm(user) {
        if (this.elements.editUserId) this.elements.editUserId.value = user.id;
        if (this.elements.editUsername) this.elements.editUsername.value = user.username;
        if (this.elements.editEmail) this.elements.editEmail.value = user.email;
        if (this.elements.editIngameName) this.elements.editIngameName.value = user.ingame_name;
        if (this.elements.editDiscordUsername) this.elements.editDiscordUsername.value = user.discord_username || '';
        if (this.elements.editProfileInfo) this.elements.editProfileInfo.value = user.profile_info || '';
        if (this.elements.editUserStatus) this.elements.editUserStatus.value = user.status;
    },
    
    /**
     * Kullanıcı rollerini yükle
     */
    async loadUserRoles(userId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}get_user_roles.php?id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateUserRoles(data.current_roles, data.available_roles);
            }
        } catch (error) {
            console.error('Error loading user roles:', error);
        }
    },
    
    /**
     * Kullanıcı rollerini doldur
     */
    populateUserRoles(currentRoles, availableRoles) {
        // Mevcut roller
        if (this.elements.userRolesList) {
            this.elements.userRolesList.innerHTML = '';
            currentRoles.forEach(role => {
                const roleElement = this.createRoleElement(role, 'current');
                this.elements.userRolesList.appendChild(roleElement);
            });
            
            if (currentRoles.length === 0) {
                this.elements.userRolesList.innerHTML = '<p style="color: var(--light-grey); font-style: italic;">Henüz hiç rol atanmamış</p>';
            }
        }
        
        // Mevcut olmayan roller
        if (this.elements.availableRolesList) {
            this.elements.availableRolesList.innerHTML = '';
            availableRoles.forEach(role => {
                const roleElement = this.createRoleElement(role, 'available');
                this.elements.availableRolesList.appendChild(roleElement);
            });
            
            if (availableRoles.length === 0) {
                this.elements.availableRolesList.innerHTML = '<p style="color: var(--light-grey); font-style: italic;">Atanabilir başka rol yok</p>';
            }
        }
    },
    
    /**
     * Rol elementi oluştur
     */
    createRoleElement(role, type) {
        const div = document.createElement('div');
        div.className = 'role-item';
        div.dataset.roleId = role.id;
        
        const actionButton = type === 'current' ? 
            `<button class="role-action-btn remove" onclick="ManageUsers.removeUserRole(${role.id})" title="Rolü Kaldır">
                <i class="fas fa-times"></i>
            </button>` :
            `<button class="role-action-btn" onclick="ManageUsers.addUserRole(${role.id})" title="Rol Ekle">
                <i class="fas fa-plus"></i>
            </button>`;
        
        div.innerHTML = `
            <div class="role-info">
                <h6 class="role-name" style="color: ${role.color};">${this.escapeHtml(role.name)}</h6>
                <p class="role-description">${this.escapeHtml(role.description)}</p>
            </div>
            <div class="role-actions">
                ${actionButton}
            </div>
        `;
        
        return div;
    },
    
    /**
     * Kullanıcıya rol ekle
     */
    async addUserRole(roleId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}add_user_role.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.state.currentUserId,
                    role_id: roleId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadUserRoles(this.state.currentUserId);
                this.markAsChanged();
                this.showSuccess('Rol başarıyla eklendi');
            } else {
                this.showError(data.message || 'Rol eklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error adding user role:', error);
            this.showError('Ağ hatası oluştu');
        }
    },
    
    /**
     * Kullanıcıdan rol kaldır
     */
    async removeUserRole(roleId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}remove_user_role.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.state.currentUserId,
                    role_id: roleId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadUserRoles(this.state.currentUserId);
                this.markAsChanged();
                this.showSuccess('Rol başarıyla kaldırıldı');
            } else {
                this.showError(data.message || 'Rol kaldırılırken hata oluştu');
            }
        } catch (error) {
            console.error('Error removing user role:', error);
            this.showError('Ağ hatası oluştu');
        }
    },
    
    /**
     * Kullanıcı skill tags'lerini yükle
     */
    async loadUserSkills(userId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}get_user_skills.php?id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateUserSkills(data.current_skills, data.available_skills);
            }
        } catch (error) {
            console.error('Error loading user skills:', error);
        }
    },
    
    /**
     * Kullanıcı skill tags'lerini doldur
     */
    populateUserSkills(currentSkills, availableSkills) {
        // Mevcut skills
        if (this.elements.userSkillsList) {
            this.elements.userSkillsList.innerHTML = '';
            currentSkills.forEach(skill => {
                const skillElement = this.createSkillElement(skill, 'current');
                this.elements.userSkillsList.appendChild(skillElement);
            });
            
            if (currentSkills.length === 0) {
                this.elements.userSkillsList.innerHTML = '<p style="color: var(--light-grey); font-style: italic;">Henüz hiç skill tag eklenmemiş</p>';
            }
        }
        
        // Mevcut olmayan skills
        this.availableSkillsData = availableSkills;
        this.filterSkills(''); // İlk yüklemede tümünü göster
    },
    
    /**
     * Skill elementi oluştur
     */
    createSkillElement(skill, type) {
        const div = document.createElement('div');
        div.className = 'skill-item';
        div.dataset.skillId = skill.id;
        
        const actionButton = type === 'current' ? 
            `<button class="skill-action-btn remove" onclick="ManageUsers.removeUserSkill(${skill.id})" title="Skill Tag Kaldır">
                <i class="fas fa-times"></i>
            </button>` :
            `<button class="skill-action-btn" onclick="ManageUsers.addUserSkill(${skill.id})" title="Skill Tag Ekle">
                <i class="fas fa-plus"></i>
            </button>`;
        
        div.innerHTML = `
            <div class="skill-info">
                <h6 class="skill-name">${this.escapeHtml(skill.tag_name)}</h6>
            </div>
            <div class="skill-actions">
                ${actionButton}
            </div>
        `;
        
        return div;
    },
    
    /**
     * Skill'leri filtrele
     */
    filterSkills(searchTerm) {
        if (!this.elements.availableSkillsList || !this.availableSkillsData) return;
        
        const filteredSkills = this.availableSkillsData.filter(skill => 
            skill.tag_name.toLowerCase().includes(searchTerm.toLowerCase())
        );
        
        this.elements.availableSkillsList.innerHTML = '';
        
        filteredSkills.forEach(skill => {
            const skillElement = this.createSkillElement(skill, 'available');
            this.elements.availableSkillsList.appendChild(skillElement);
        });
        
        if (filteredSkills.length === 0) {
            this.elements.availableSkillsList.innerHTML = '<p style="color: var(--light-grey); font-style: italic;">Eklenebilir skill tag bulunamadı</p>';
        }
    },
    
    /**
     * Kullanıcıya skill ekle
     */
    async addUserSkill(skillId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}add_user_skill.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.state.currentUserId,
                    skill_id: skillId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadUserSkills(this.state.currentUserId);
                this.markAsChanged();
                this.showSuccess('Skill tag başarıyla eklendi');
            } else {
                this.showError(data.message || 'Skill tag eklenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error adding user skill:', error);
            this.showError('Ağ hatası oluştu');
        }
    },
    
    /**
     * Kullanıcıdan skill kaldır
     */
    async removeUserSkill(skillId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}remove_user_skill.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.state.currentUserId,
                    skill_id: skillId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadUserSkills(this.state.currentUserId);
                this.markAsChanged();
                this.showSuccess('Skill tag başarıyla kaldırıldı');
            } else {
                this.showError(data.message || 'Skill tag kaldırılırken hata oluştu');
            }
        } catch (error) {
            console.error('Error removing user skill:', error);
            this.showError('Ağ hatası oluştu');
        }
    },
    
    /**
     * Durum geçmişini yükle
     */
    async loadStatusHistory(userId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}get_status_history.php?id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateStatusHistory(data.history);
            }
        } catch (error) {
            console.error('Error loading status history:', error);
        }
    },
    
    /**
     * Durum geçmişini doldur
     */
    populateStatusHistory(history) {
        if (!this.elements.statusHistoryList) return;
        
        this.elements.statusHistoryList.innerHTML = '';
        
        if (history.length === 0) {
            this.elements.statusHistoryList.innerHTML = '<p style="color: var(--light-grey); font-style: italic;">Durum geçmişi bulunamadı</p>';
            return;
        }
        
        history.forEach(item => {
            const historyElement = document.createElement('div');
            historyElement.className = 'history-item';
            
            const date = new Date(item.created_at).toLocaleDateString('tr-TR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            historyElement.innerHTML = `
                <div class="history-icon status-badge ${item.status}">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="history-info">
                    <h6 class="history-status">${this.getStatusText(item.status)}</h6>
                    <p class="history-date">${date}</p>
                </div>
            `;
            
            this.elements.statusHistoryList.appendChild(historyElement);
        });
    },
    
    /**
     * Tab değiştir
     */
    switchTab(tabName) {
        // Tüm tab button'ları ve content'leri gizle
        this.elements.tabButtons.forEach(btn => btn.classList.remove('active'));
        this.elements.tabContents.forEach(content => content.classList.remove('active'));
        
        // Seçili tab'ı aktif yap
        const activeTabBtn = document.querySelector(`[data-tab="${tabName}"]`);
        const activeTabContent = document.getElementById(`tab-${tabName}`);
        
        if (activeTabBtn) activeTabBtn.classList.add('active');
        if (activeTabContent) activeTabContent.classList.add('active');
    },
    
    /**
     * Değişiklikleri tespit et
     */
    detectChanges() {
        if (!this.state.originalUserData) return;
        
        const formData = new FormData(this.elements.editUserForm);
        const currentData = Object.fromEntries(formData);
        
        let hasChanges = false;
        
        // Temel form alanlarını kontrol et
        if (currentData.email !== this.state.originalUserData.email ||
            currentData.ingame_name !== this.state.originalUserData.ingame_name ||
            currentData.discord_username !== (this.state.originalUserData.discord_username || '') ||
            currentData.profile_info !== (this.state.originalUserData.profile_info || '') ||
            currentData.status !== this.state.originalUserData.status) {
            hasChanges = true;
        }
        
        this.state.hasChanges = hasChanges;
        this.updateChangeIndicator();
    },
    
    /**
     * Değişiklik göstergesini güncelle
     */
    updateChangeIndicator() {
        if (!this.elements.changeIndicator) return;
        
        if (this.state.hasChanges) {
            this.elements.changeIndicator.className = 'change-indicator has-changes';
            this.elements.changeIndicator.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                Kaydedilmemiş değişiklikler var
            `;
        } else {
            this.elements.changeIndicator.className = 'change-indicator';
            this.elements.changeIndicator.innerHTML = `
                <i class="fas fa-info-circle"></i>
                Değişiklik yapmadınız
            `;
        }
    },
    
    /**
     * Değişikliği işaretle
     */
    markAsChanged() {
        this.state.hasChanges = true;
        this.updateChangeIndicator();
    },
    
    /**
     * Kullanıcıyı kaydet
     */
    async saveUser() {
        if (!this.state.currentUserId) return;
        
        try {
            this.showLoading();
            
            const formData = new FormData(this.elements.editUserForm);
            const userData = Object.fromEntries(formData);
            userData.user_id = this.state.currentUserId;
            
            const response = await fetch(`${this.config.apiBaseUrl}update_user.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Kullanıcı başarıyla güncellendi');
                this.state.hasChanges = false;
                this.updateChangeIndicator();
                this.loadUsersData(); // Tabloyu yenile
            } else {
                this.showError(data.message || 'Kullanıcı güncellenirken hata oluştu');
            }
        } catch (error) {
            console.error('Error saving user:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Kullanıcı düzenleme modalını kapat
     */
    closeEditUserModal() {
        if (this.state.hasChanges) {
            if (!confirm('Kaydedilmemiş değişiklikler var. Çıkmak istediğinizden emin misiniz?')) {
                return;
            }
        }
        
        this.hideModal(this.elements.editUserModal);
        this.state.currentUserId = null;
        this.state.hasChanges = false;
        this.state.originalUserData = null;
    },
    
    /**
     * Kullanıcı sil
     */
    async deleteUser(userId) {
        if (!confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
            return;
        }
        
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}delete_user.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Kullanıcı başarıyla silindi');
                this.loadUsersData();
            } else {
                this.showError(data.message || 'Kullanıcı silinirken hata oluştu');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Toplu işlemler modalını aç
     */
    openBulkActionsModal() {
        if (this.state.selectedUsers.size === 0) {
            this.showError('Lütfen önce kullanıcı seçin');
            return;
        }
        
        this.updateBulkActionsState();
        this.showModal(this.elements.bulkActionsModal);
    },
    
    /**
     * Toplu işlemler modalını kapat
     */
    closeBulkActionsModal() {
        this.hideModal(this.elements.bulkActionsModal);
    },
    
    /**
     * Toplu durum değiştir
     */
    async bulkChangeStatus(newStatus) {
        if (this.state.selectedUsers.size === 0) return;
        
        const statusText = this.getStatusText(newStatus);
        
        if (!confirm(`Seçili ${this.state.selectedUsers.size} kullanıcının durumunu "${statusText}" olarak değiştirmek istediğinizden emin misiniz?`)) {
            return;
        }
        
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}bulk_change_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: Array.from(this.state.selectedUsers),
                    status: newStatus
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`${data.affected_count} kullanıcının durumu başarıyla güncellendi`);
                this.state.selectedUsers.clear();
                this.closeBulkActionsModal();
                this.loadUsersData();
            } else {
                this.showError(data.message || 'Toplu durum güncelleme hatası');
            }
        } catch (error) {
            console.error('Error bulk changing status:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Toplu kullanıcı sil
     */
    async bulkDeleteUsers() {
        if (this.state.selectedUsers.size === 0) return;
        
        if (!confirm(`Seçili ${this.state.selectedUsers.size} kullanıcıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`)) {
            return;
        }
        
        try {
            this.showLoading();
            
            const response = await fetch(`${this.config.apiBaseUrl}bulk_delete_users.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: Array.from(this.state.selectedUsers)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`${data.affected_count} kullanıcı başarıyla silindi`);
                this.state.selectedUsers.clear();
                this.closeBulkActionsModal();
                this.loadUsersData();
            } else {
                this.showError(data.message || 'Toplu silme hatası');
            }
        } catch (error) {
            console.error('Error bulk deleting users:', error);
            this.showError('Ağ hatası oluştu');
        } finally {
            this.hideLoading();
        }
    },
    
    /**
     * Kullanıcı detaylarını görüntüle (readonly modal)
     */
    async viewUserDetails(userId) {
        await this.openEditUserModal(userId);
        
        // Modal'ı readonly yap
        setTimeout(() => {
            const modal = this.elements.editUserModal;
            const inputs = modal.querySelectorAll('input:not([type="checkbox"]), textarea, select');
            const buttons = modal.querySelectorAll('.role-action-btn, .skill-action-btn, .modal-footer .btn-primary');
            
            inputs.forEach(input => input.disabled = true);
            buttons.forEach(btn => btn.style.display = 'none');
            
            // Başlığı güncelle
            this.elements.editModalTitle.textContent = 'Kullanıcı Detayları (Görüntüleme)';
        }, 100);
    },
    
    /**
     * Verileri yenile
     */
    refreshUserData() {
        this.state.selectedUsers.clear();
        this.updateSelectAllState();
        this.updateBulkActionsState();
        this.state.currentPage = 1; // Sayfa 1'e dön
        this.loadUsersData();
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
     * Users Loading göster
     */
    showUsersLoading() {
        if (this.elements.usersLoading) {
            this.elements.usersLoading.style.display = 'flex';
        }
    },
    
    /**
     * Users Loading gizle
     */
    hideUsersLoading() {
        if (this.elements.usersLoading) {
            this.elements.usersLoading.style.display = 'none';
        }
    },
    
    /**
     * Loading göster
     */
    showLoading() {
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.style.display = 'flex';
        }
        this.state.isLoading = true;
    },
    
    /**
     * Loading gizle
     */
    hideLoading() {
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.style.display = 'none';
        }
        this.state.isLoading = false;
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
     * Bildirim göster
     */
    showNotification(message, type = 'info') {
        // Basit bir notification sistemi
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'var(--success-color, #28a745)' : 
                         type === 'error' ? 'var(--danger-color, #dc3545)' : 'var(--gold)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 17000;
            animation: slideInRight 0.3s ease;
            max-width: 400px;
            word-wrap: break-word;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 
                                 type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
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
     * Debounce utility
     */
    debounce(func, wait) {
        clearTimeout(this.state.searchTimeout);
        this.state.searchTimeout = setTimeout(func, wait);
    }
};

// Global fonksiyonlar (HTML'den çağrılabilir)
window.editUser = (userId) => ManageUsers.openEditUserModal(userId);
window.viewUserDetails = (userId) => ManageUsers.viewUserDetails(userId);
window.deleteUser = (userId) => ManageUsers.deleteUser(userId);
window.refreshUserData = () => ManageUsers.refreshUserData();

// Modal fonksiyonları
window.closeEditUserModal = () => ManageUsers.closeEditUserModal();
window.saveUser = () => ManageUsers.saveUser();

// Bulk actions
window.openBulkActionsModal = () => ManageUsers.openBulkActionsModal();
window.closeBulkActionsModal = () => ManageUsers.closeBulkActionsModal();
window.bulkChangeStatus = (status) => ManageUsers.bulkChangeStatus(status);
window.bulkDeleteUsers = () => ManageUsers.bulkDeleteUsers();

// CSS animasyonları için stil ekle
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
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
        
        .notification {
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .notification:hover {
            transform: translateX(-5px);
        }
    `;
    document.head.appendChild(style);
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ManageUsers;
}