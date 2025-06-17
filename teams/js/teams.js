/**
 * Teams Module JavaScript - /teams/js/teams.js
 * Projenizin mevcut yapısına uygun olarak düzenlenmiştir
 */

// ========================================
// GLOBAL VARIABLES
// ========================================

let teamsData = {
    currentTeam: null,
    currentUser: null,
    permissions: {},
    csrfToken: null
};

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    initializeTeamsModule();
});

function initializeTeamsModule() {
    // CSRF token'ı al
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        teamsData.csrfToken = csrfInput.value;
    }
    
    // Event listener'ları ekle
    initializeEventListeners();
    
    // Sayfa bazlı initialization
    initializePageSpecific();
    
    // Auto-hide alerts
    initializeAlerts();
    
    // Initialize enhancements
    initializeCharacterCounters();
    initializeAutoResizeTextareas();
    
    console.log('Teams module initialized');
}

// ========================================
// EVENT LISTENERS
// ========================================

function initializeEventListeners() {
    // Form validation
    const teamForms = document.querySelectorAll('.team-form');
    teamForms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
    
    // Color picker synchronization
    const colorPickers = document.querySelectorAll('input[type="color"]');
    colorPickers.forEach(picker => {
        picker.addEventListener('input', handleColorChange);
    });
    
    // Search functionality
    const searchInputs = document.querySelectorAll('.team-search');
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(handleSearch, 300));
    });
    
    // Filter functionality
    const filterSelects = document.querySelectorAll('.team-filter');
    filterSelects.forEach(select => {
        select.addEventListener('change', handleFilter);
    });
    
    // Button loading states
    const actionButtons = document.querySelectorAll('[data-action]');
    actionButtons.forEach(button => {
        button.addEventListener('click', handleActionButton);
    });
}

// ========================================
// FORM HANDLING
// ========================================

function handleFormSubmit(event) {
    const form = event.target;
    const formType = form.dataset.formType || 'team';
    
    // Form validation
    if (!validateForm(form, formType)) {
        event.preventDefault();
        return false;
    }
    
    // Loading state
    setFormLoading(form, true);
}

function validateForm(form, type) {
    let isValid = true;
    const errors = [];
    
    // Clear previous errors
    clearFormErrors(form);
    
    switch (type) {
        case 'team':
            isValid = validateTeamForm(form, errors);
            break;
        case 'application':
            isValid = validateApplicationForm(form, errors);
            break;
        default:
            isValid = validateGeneralForm(form, errors);
    }
    
    if (!isValid) {
        showFormErrors(form, errors);
    }
    
    return isValid;
}

function validateTeamForm(form, errors) {
    const name = form.querySelector('input[name="name"]');
    const maxMembers = form.querySelector('input[name="max_members"]');
    
    // Team name validation - sadece minimum kontroller
    if (name) {
        const nameValue = name.value.trim();
        if (nameValue.length < 3) {
            errors.push({ field: 'name', message: 'Takım adı en az 3 karakter olmalıdır.' });
        } else if (nameValue.length > 100) {
            errors.push({ field: 'name', message: 'Takım adı en fazla 100 karakter olmalıdır.' });
        }
        // Sadece HTML injection engelini tut
        else if (/<script|<iframe|javascript:/i.test(nameValue)) {
            errors.push({ field: 'name', message: 'Takım adı güvenlik açısından geçersiz içerik içeriyor.' });
        }
    }
    
    // Max members validation
    if (maxMembers) {
        const maxValue = parseInt(maxMembers.value);
        if (maxValue < 5 || maxValue > 500) {
            errors.push({ field: 'max_members', message: 'Maksimum üye sayısı 5-500 arasında olmalıdır.' });
        }
    }
    
    return errors.length === 0;
}

function validateApplicationForm(form, errors) {
    const message = form.querySelector('textarea[name="message"]');
    
    if (message) {
        const messageValue = message.value.trim();
        if (messageValue.length > 1000) {
            errors.push({ field: 'message', message: 'Başvuru mesajı en fazla 1000 karakter olmalıdır.' });
        }
    }
    
    return errors.length === 0;
}

function validateGeneralForm(form, errors) {
    // General form validation
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            errors.push({ 
                field: field.name, 
                message: `${field.dataset.label || field.name} alanı zorunludur.` 
            });
        }
    });
    
    return errors.length === 0;
}

function clearFormErrors(form) {
    form.querySelectorAll('.error').forEach(field => {
        field.classList.remove('error');
    });
    
    form.querySelectorAll('.form-error').forEach(error => {
        error.remove();
    });
}

function showFormErrors(form, errors) {
    errors.forEach(error => {
        const field = form.querySelector(`[name="${error.field}"]`);
        if (field) {
            field.classList.add('error');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.textContent = error.message;
            
            // Insert after the field
            field.parentNode.insertBefore(errorDiv, field.nextSibling);
        }
    });
}

function setFormLoading(form, loading) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;
    
    if (loading) {
        submitButton.classList.add('btn-loading');
        submitButton.disabled = true;
    } else {
        submitButton.classList.remove('btn-loading');
        submitButton.disabled = false;
    }
}

// ========================================
// COLOR PICKER HANDLING
// ========================================

function handleColorChange(event) {
    const colorPicker = event.target;
    const colorValue = colorPicker.value;
    
    // Update text input if exists
    const textInput = document.getElementById('colorText');
    if (textInput) {
        textInput.value = colorValue;
    }
    
    // Update preview if exists
    const preview = document.querySelector('.color-preview');
    if (preview) {
        preview.style.backgroundColor = colorValue;
    }
    
    // Update CSS custom property for live preview
    document.documentElement.style.setProperty('--team-color', colorValue);
}

// ========================================
// SEARCH AND FILTER
// ========================================

function handleSearch(event) {
    const searchTerm = event.target.value.toLowerCase().trim();
    const searchableElements = document.querySelectorAll('[data-searchable]');
    
    searchableElements.forEach(element => {
        const searchText = element.dataset.searchable.toLowerCase();
        const isVisible = searchText.includes(searchTerm) || searchTerm === '';
        
        element.style.display = isVisible ? '' : 'none';
    });
    
    updateNoResultsMessage(searchableElements, searchTerm);
}

function handleFilter(event) {
    const filterValue = event.target.value;
    const filterType = event.target.dataset.filterType;
    const filterableElements = document.querySelectorAll(`[data-filter-${filterType}]`);
    
    filterableElements.forEach(element => {
        const elementValue = element.dataset[`filter${capitalize(filterType)}`];
        const isVisible = filterValue === '' || elementValue === filterValue;
        
        element.style.display = isVisible ? '' : 'none';
    });
    
    updateNoResultsMessage(filterableElements, filterValue);
}

function updateNoResultsMessage(elements, searchTerm) {
    const visibleElements = Array.from(elements).filter(el => el.style.display !== 'none');
    const noResultsMsg = document.querySelector('.no-results-message');
    
    if (visibleElements.length === 0 && searchTerm) {
        if (!noResultsMsg) {
            const container = elements[0]?.parentNode;
            if (container) {
                const msg = document.createElement('div');
                msg.className = 'no-results-message';
                msg.style.textAlign = 'center';
                msg.style.padding = '2rem';
                msg.style.color = 'var(--light-grey)';
                msg.innerHTML = `
                    <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h5>Sonuç bulunamadı</h5>
                    <p>Arama kriterlerinizi değiştirip tekrar deneyin.</p>
                `;
                container.appendChild(msg);
            }
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}

// ========================================
// ACTION BUTTONS
// ========================================

function handleActionButton(event) {
    const button = event.target.closest('button');
    const action = button.dataset.action;
    
    if (!action) return;
    
    // Confirmation for destructive actions
    const destructiveActions = ['delete', 'remove', 'kick', 'ban'];
    if (destructiveActions.includes(action)) {
        const confirmMessage = getConfirmationMessage(action);
        if (!confirm(confirmMessage)) {
            event.preventDefault();
            return false;
        }
    }
    
    // Set loading state
    setButtonLoading(button, true);
    
    // If it's a form submit, let it proceed
    // Otherwise handle AJAX
    if (button.type !== 'submit') {
        event.preventDefault();
        handleAjaxAction(button, action);
    }
}

function getConfirmationMessage(action) {
    const messages = {
        'delete': 'Bu öğeyi silmek istediğinizden emin misiniz?',
        'remove': 'Bu öğeyi kaldırmak istediğinizden emin misiniz?',
        'kick': 'Bu üyeyi takımdan atmak istediğinizden emin misiniz?',
        'ban': 'Bu üyeyi banlamak istediğinizden emin misiniz?'
    };
    
    return messages[action] || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?';
}

function handleAjaxAction(button, action) {
    // This would be implemented for specific AJAX actions
    // For now, just remove loading state after a delay
    setTimeout(() => {
        setButtonLoading(button, false);
    }, 1000);
}

function setButtonLoading(button, loading) {
    if (loading) {
        button.classList.add('btn-loading');
        button.disabled = true;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
}

// ========================================
// ALERTS
// ========================================

function initializeAlerts() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            hideAlert(alert);
        }, 5000);
        
        // Add click to dismiss
        alert.addEventListener('click', function() {
            hideAlert(this);
        });
    });
}

function hideAlert(alert) {
    alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
        alert.remove();
    }, 300);
}

function showAlert(message, type = 'info') {
    const alertContainer = document.querySelector('.teams-container');
    if (!alertContainer) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-10px)';
    
    const iconClass = {
        'success': 'fa-check-circle',
        'danger': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    }[type] || 'fa-info-circle';
    
    alert.innerHTML = `
        <i class="fas ${iconClass}"></i>
        ${message}
    `;
    
    // Insert at the beginning of container
    alertContainer.insertBefore(alert, alertContainer.firstChild);
    
    // Animate in
    setTimeout(() => {
        alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        alert.style.opacity = '1';
        alert.style.transform = 'translateY(0)';
    }, 10);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        hideAlert(alert);
    }, 5000);
    
    // Click to dismiss
    alert.addEventListener('click', function() {
        hideAlert(this);
    });
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

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

function capitalize(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function formatDate(dateString, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    const formatOptions = { ...defaultOptions, ...options };
    return new Date(dateString).toLocaleDateString('tr-TR', formatOptions);
}

function generateSlug(text) {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '') // Remove special characters
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/-+/g, '-') // Replace multiple hyphens with single
        .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens
}

// ========================================
// PAGE SPECIFIC INITIALIZATION
// ========================================

function initializePageSpecific() {
    const currentPage = getCurrentPage();
    
    switch (currentPage) {
        case 'teams-create':
            initializeTeamCreate();
            break;
        case 'teams-index':
            initializeTeamsIndex();
            break;
        case 'teams-detail':
            initializeTeamDetail();
            break;
    }
}

function getCurrentPage() {
    const path = window.location.pathname;
    const filename = path.split('/').pop().split('.')[0];
    
    if (path.includes('/teams/')) {
        if (filename === 'index' || filename === '') {
            return 'teams-index';
        }
        return 'teams-' + filename;
    }
    
    return 'unknown';
}

function initializeTeamCreate() {
    console.log('Initializing team create page');
    
    // Slug generation from team name
    const nameInput = document.querySelector('input[name="name"]');
    const slugPreview = document.querySelector('.slug-preview');
    
    if (nameInput) {
        // Remove any potential input blocking
        nameInput.removeAttribute('readonly');
        nameInput.removeAttribute('disabled');
        
        nameInput.addEventListener('input', function() {
            const slug = generateSlug(this.value);
            if (slugPreview) {
                slugPreview.textContent = slug || 'takım-adı';
            }
        });
        
        // Less aggressive validation - only on blur
        nameInput.addEventListener('blur', function() {
            validateSingleField(this);
        });
    }
    
    // Tag input - özel karakterlere izin ver
    const tagInput = document.querySelector('input[name="tag"]');
    if (tagInput) {
        tagInput.addEventListener('input', function() {
            // Auto uppercase
            this.value = this.value.toUpperCase();
            
            // Limit to 8 characters
            if (this.value.length > 8) {
                this.value = this.value.substring(0, 8);
            }
        });
        
        tagInput.addEventListener('blur', function() {
            validateSingleField(this);
        });
    }
    
    // Color picker initialization
    const colorPicker = document.getElementById('color');
    if (colorPicker) {
        // Set initial preview
        handleColorChange({ target: colorPicker });
    }
    
    // Max members input validation
    const maxMembersInput = document.querySelector('input[name="max_members"]');
    if (maxMembersInput) {
        maxMembersInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value < 5) this.value = 5;
            if (value > 500) this.value = 500;
        });
    }
}

function initializeTeamsIndex() {
    console.log('Initializing teams index page');
    
    // Initialize team filters
    initializeTeamFilters();
    
    // Initialize team cards hover effects
    initializeTeamCards();
}

function initializeTeamDetail() {
    console.log('Initializing team detail page');
    
    // Set team color CSS variable from data attribute
    const teamColor = document.body.dataset.teamColor;
    if (teamColor) {
        document.documentElement.style.setProperty('--team-color', teamColor);
    }
    
    // Initialize member management
    initializeMemberManagement();
}

// ========================================
// SPECIFIC PAGE HELPERS
// ========================================

function validateSingleField(field) {
    const fieldName = field.name;
    const fieldValue = field.value.trim();
    
    // Clear previous errors
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.form-error');
    if (existingError) {
        existingError.remove();
    }
    
    let errorMessage = '';
    
    switch (fieldName) {
        case 'name':
            if (fieldValue.length < 3) {
                errorMessage = 'Takım adı en az 3 karakter olmalıdır.';
            } else if (fieldValue.length > 100) {
                errorMessage = 'Takım adı en fazla 100 karakter olmalıdır.';
            } else if (/<script|<iframe|javascript:/i.test(fieldValue)) {
                errorMessage = 'Takım adı güvenlik açısından geçersiz içerik içeriyor.';
            }
            break;
            
        case 'description':
            if (fieldValue.length > 1000) {
                errorMessage = 'Açıklama en fazla 1000 karakter olmalıdır.';
            }
            break;
            
        case 'max_members':
            const numValue = parseInt(fieldValue);
            if (numValue < 5 || numValue > 500 || isNaN(numValue)) {
                errorMessage = 'Maksimum üye sayısı 5-500 arasında olmalıdır.';
            }
            break;
    }
    
    if (errorMessage) {
        field.classList.add('error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error';
        errorDiv.textContent = errorMessage;
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
        
        return false;
    }
    
    return true;
}

function initializeTeamFilters() {
    const filterForm = document.querySelector('.team-filters');
    if (!filterForm) return;
    
    const inputs = filterForm.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('change', applyTeamFilters);
    });
}

function applyTeamFilters() {
    const filterForm = document.querySelector('.team-filters');
    if (!filterForm) return;
    
    const formData = new FormData(filterForm);
    const filters = Object.fromEntries(formData);
    
    // Apply filters to visible teams
    filterVisibleTeams(filters);
    
    // Update URL (optional)
    const params = new URLSearchParams(formData);
    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', newUrl);
}

function filterVisibleTeams(filters) {
    const teamCards = document.querySelectorAll('.team-card');
    let visibleCount = 0;
    
    teamCards.forEach(card => {
        let isVisible = true;
        
        // Check each filter
        Object.entries(filters).forEach(([key, value]) => {
            if (value && card.dataset[key] !== value) {
                isVisible = false;
            }
        });
        
        // Search filter
        if (filters.search && isVisible) {
            const searchText = (
                card.querySelector('.team-name')?.textContent +
                ' ' +
                card.querySelector('.team-description')?.textContent
            ).toLowerCase();
            
            isVisible = searchText.includes(filters.search.toLowerCase());
        }
        
        card.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
    });
    
    // Update results count
    updateResultsCount(visibleCount);
}

function updateResultsCount(count) {
    const resultsCounter = document.querySelector('.results-count');
    if (resultsCounter) {
        resultsCounter.textContent = `${count} takım bulundu`;
    }
}

function initializeTeamCards() {
    const teamCards = document.querySelectorAll('.team-card');
    
    teamCards.forEach(card => {
        // Add staggered animation
        const index = Array.from(teamCards).indexOf(card);
        card.style.animationDelay = `${index * 0.1}s`;
        
        // Enhanced hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-6px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
}

function initializeMemberManagement() {
    // Member action buttons
    const memberActions = document.querySelectorAll('.member-action');
    memberActions.forEach(button => {
        button.addEventListener('click', handleMemberAction);
    });
    
    // Application action buttons
    const appActions = document.querySelectorAll('.application-action');
    appActions.forEach(button => {
        button.addEventListener('click', handleApplicationAction);
    });
}

function handleMemberAction(event) {
    event.preventDefault();
    
    const button = event.target.closest('button');
    const action = button.dataset.action;
    const memberId = button.dataset.memberId;
    const teamId = button.dataset.teamId;
    
    if (!action || !memberId) {
        showAlert('Geçersiz işlem parametreleri.', 'danger');
        return;
    }
    
    // Confirmation for destructive actions
    const destructiveActions = ['kick', 'ban', 'remove'];
    if (destructiveActions.includes(action)) {
        const confirmMessage = getMemberConfirmationMessage(action);
        if (!confirm(confirmMessage)) {
            return;
        }
    }
    
    // Show loading
    setButtonLoading(button, true);
    
    // Simulate AJAX request (replace with actual implementation)
    setTimeout(() => {
        setButtonLoading(button, false);
        
        if (destructiveActions.includes(action)) {
            // Remove member card from UI
            const memberCard = button.closest('.member-card');
            if (memberCard) {
                memberCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                memberCard.style.opacity = '0';
                memberCard.style.transform = 'translateY(-10px)';
                setTimeout(() => memberCard.remove(), 300);
            }
            
            showAlert('İşlem başarıyla tamamlandı.', 'success');
        }
    }, 1000);
}

function handleApplicationAction(event) {
    event.preventDefault();
    
    const button = event.target.closest('button');
    const action = button.dataset.action;
    const applicationId = button.dataset.applicationId;
    
    if (!action || !applicationId) {
        showAlert('Geçersiz işlem parametreleri.', 'danger');
        return;
    }
    
    const confirmMessage = getApplicationConfirmationMessage(action);
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Show loading
    setButtonLoading(button, true);
    
    // Simulate AJAX request (replace with actual implementation)
    setTimeout(() => {
        setButtonLoading(button, false);
        
        // Update application status in UI
        const applicationCard = button.closest('.application-card');
        if (applicationCard) {
            const statusElement = applicationCard.querySelector('.application-status');
            if (statusElement) {
                statusElement.className = `application-status ${action === 'approve' ? 'approved' : 'rejected'}`;
                statusElement.textContent = action === 'approve' ? 'Onaylandı' : 'Reddedildi';
            }
            
            // Hide action buttons
            const actionsElement = applicationCard.querySelector('.application-actions');
            if (actionsElement) {
                actionsElement.style.display = 'none';
            }
        }
        
        showAlert(`Başvuru ${action === 'approve' ? 'onaylandı' : 'reddedildi'}.`, 'success');
    }, 1000);
}

function getMemberConfirmationMessage(action) {
    const messages = {
        'kick': 'Bu üyeyi takımdan atmak istediğinizden emin misiniz?',
        'ban': 'Bu üyeyi takımdan banlamak istediğinizden emin misiniz?',
        'remove': 'Bu üyeyi takımdan çıkarmak istediğinizden emin misiniz?'
    };
    
    return messages[action] || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?';
}

function getApplicationConfirmationMessage(action) {
    const messages = {
        'approve': 'Bu başvuruyu onaylamak istediğinizden emin misiniz?',
        'reject': 'Bu başvuruyu reddetmek istediğinizden emin misiniz?'
    };
    
    return messages[action] || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?';
}

// ========================================
// FORM ENHANCEMENT
// ========================================

// Character counter for textareas
function initializeCharacterCounters() {
    const textareas = document.querySelectorAll('textarea[maxlength]');
    
    textareas.forEach(textarea => {
        const maxLength = parseInt(textarea.getAttribute('maxlength'));
        
        // Create counter element
        const counter = document.createElement('div');
        counter.className = 'character-counter';
        counter.style.textAlign = 'right';
        counter.style.fontSize = '0.875rem';
        counter.style.color = 'var(--light-grey)';
        counter.style.marginTop = '0.25rem';
        
        // Insert after textarea
        textarea.parentNode.insertBefore(counter, textarea.nextSibling);
        
        // Update counter
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${remaining} karakter kaldı`;
            
            if (remaining < 50) {
                counter.style.color = 'var(--warning-color, #ffc107)';
            } else if (remaining < 0) {
                counter.style.color = 'var(--danger-color, #dc3545)';
            } else {
                counter.style.color = 'var(--light-grey)';
            }
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter(); // Initial count
    });
}

// Auto-resize textareas
function initializeAutoResizeTextareas() {
    const textareas = document.querySelectorAll('textarea[data-auto-resize]');
    
    textareas.forEach(textarea => {
        function resize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        
        textarea.addEventListener('input', resize);
        resize(); // Initial resize
    });
}

// ========================================
// EXPORT FOR GLOBAL ACCESS
// ========================================

// Make some functions globally available
window.TeamsModule = {
    showAlert,
    setButtonLoading,
    validateForm,
    formatDate,
    generateSlug,
    handleColorChange
};