// events/js/create_event.js - TAM GÜNCELLENMİŞ JavaScript DOSYASI

document.addEventListener('DOMContentLoaded', function() {
    initializeEventForm();
});

function initializeEventForm() {
    // DOM elements
    const visibilitySelect = document.getElementById('visibility');
    const roleRestrictionContainer = document.getElementById('role-restriction-container');
    const addRoleBtn = document.getElementById('add-role-btn');
    const rolesList = document.getElementById('roles-list');
    const descriptionTextarea = document.getElementById('description');
    const previewArea = document.getElementById('preview-area');
    
    let roleIndex = 0;
    
    // Initialize visibility handling
    handleVisibilityChange();
    if (visibilitySelect) {
        visibilitySelect.addEventListener('change', handleVisibilityChange);
    }
    
    // Add role button
    if (addRoleBtn) {
        addRoleBtn.addEventListener('click', addEventRole);
    }
    
    // Markdown editor
    initializeMarkdownEditor();
    
    // Form validation
    const eventForm = document.getElementById('eventForm');
    if (eventForm) {
        eventForm.addEventListener('submit', validateForm);
    }
    
    // Initialize existing role handlers
    initializeExistingRoleHandlers();
    
    // Functions
    function handleVisibilityChange() {
        if (!visibilitySelect || !roleRestrictionContainer) return;
        
        const value = visibilitySelect.value;
        if (value === 'role_restricted') {
            roleRestrictionContainer.style.display = 'block';
        } else {
            roleRestrictionContainer.style.display = 'none';
            // Clear selected roles
            const checkboxes = roleRestrictionContainer.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        }
    }
    
    function addEventRole() {
        const template = document.getElementById('role-template');
        if (!template || !rolesList) return;
        
        const clone = document.importNode(template.content, true);
        
        // Replace template variables
        const html = clone.querySelector('.role-item').outerHTML.replace(/\{\{INDEX\}\}/g, roleIndex);
        
        const div = document.createElement('div');
        div.innerHTML = html;
        const roleItem = div.firstElementChild;
        
        // Add event listeners
        const removeBtn = roleItem.querySelector('.remove-role');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                roleItem.remove();
                updateRoleIndexes();
            });
        }
        
        const roleSelect = roleItem.querySelector('.role-select');
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                updateRoleDescription(this, roleItem);
            });
        }
        
        rolesList.appendChild(roleItem);
        roleIndex++;
        
        // Focus on the new role select
        const newRoleSelect = roleItem.querySelector('.role-select');
        if (newRoleSelect) {
            newRoleSelect.focus();
        }
    }
    
    function updateRoleDescription(selectElement, roleItem) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const descriptionDiv = roleItem.querySelector('.role-description');
        
        if (!descriptionDiv) return;
        
        if (selectedOption.value) {
            // AJAX call to get role description
            fetch(`actions/get_role_description.php?id=${selectedOption.value}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        descriptionDiv.innerHTML = `
                            <i class="${data.role.icon_class}"></i>
                            ${data.role.role_description || 'Açıklama bulunmuyor.'}
                        `;
                        descriptionDiv.style.display = 'block';
                    } else {
                        descriptionDiv.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Role description fetch error:', error);
                    descriptionDiv.style.display = 'none';
                });
        } else {
            descriptionDiv.style.display = 'none';
        }
    }
    
    function updateRoleIndexes() {
        const roleItems = rolesList.querySelectorAll('.role-item');
        roleItems.forEach((item, index) => {
            const inputs = item.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.name;
                if (name) {
                    input.name = name.replace(/\[\d+\]/, `[${index}]`);
                }
            });
        });
        roleIndex = roleItems.length;
    }
    
    function initializeExistingRoleHandlers() {
        // Store original values for change detection
        const participantLimitInputs = document.querySelectorAll('.participant-limit-input');
        participantLimitInputs.forEach(input => {
            input.setAttribute('data-original-value', input.value);
        });
        
        // Add change detection
        participantLimitInputs.forEach(input => {
            input.addEventListener('input', function() {
                const originalValue = this.getAttribute('data-original-value');
                const currentValue = this.value;
                
                if (originalValue !== currentValue) {
                    this.style.borderColor = '#ffc107';
                    this.style.boxShadow = '0 0 0 2px rgba(255, 193, 7, 0.2)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        });
    }
    
    function validateForm(e) {
        const form = e.target;
        const errors = [];
        let isValid = true;
        
        // Title validation
        const title = form.querySelector('#title');
        if (title && (!title.value.trim() || title.value.length > 200)) {
            errors.push('Etkinlik başlığı 1-200 karakter arasında olmalıdır.');
            isValid = false;
        }
        
        // Description validation
        const description = form.querySelector('#description');
        if (description && !description.value.trim()) {
            errors.push('Etkinlik açıklaması gereklidir.');
            isValid = false;
        }
        
        // Date validation
        const eventDateTime = form.querySelector('#event_datetime');
        if (eventDateTime && eventDateTime.value) {
            const eventDate = new Date(eventDateTime.value);
            const now = new Date();
            if (eventDate <= now) {
                errors.push('Etkinlik tarihi gelecekte olmalıdır.');
                isValid = false;
            }
        }
        
        // Max participants validation
        const maxParticipants = form.querySelector('#max_participants');
        if (maxParticipants && maxParticipants.value && parseInt(maxParticipants.value) < 1) {
            errors.push('Maksimum katılımcı sayısı 1\'den az olamaz.');
            isValid = false;
        }
        
        // Role validation
        const requiresRoleSelection = form.querySelector('input[name="requires_role_selection"]');
        if (requiresRoleSelection && requiresRoleSelection.checked) {
            const newRoles = form.querySelectorAll('.role-select');
            const existingRoles = form.querySelectorAll('input[name^="existing_roles"]');
            const removeRoles = form.querySelectorAll('input[name="remove_roles[]"]');
            
            let hasValidRoles = false;
            
            // Check new roles
            newRoles.forEach(select => {
                if (select.value) {
                    hasValidRoles = true;
                }
            });
            
            // Check existing roles (subtract removed ones)
            if (existingRoles.length > removeRoles.length) {
                hasValidRoles = true;
            }
            
            if (!hasValidRoles) {
                errors.push('Rol seçimi zorunlu işaretlendiğinde en az bir rol eklemelisiniz.');
                isValid = false;
            }
        }
        
        // Visibility validation
        if (visibilitySelect && visibilitySelect.value === 'role_restricted') {
            const checkedRoles = document.querySelectorAll('input[name="visibility_roles[]"]:checked');
            if (checkedRoles.length === 0) {
                errors.push('Rol kısıtlaması seçildiğinde en az bir rol seçmelisiniz.');
                isValid = false;
            }
        }
        
        // Role limit validation
        const participantLimits = form.querySelectorAll('.participant-limit');
        participantLimits.forEach(input => {
            if (input.value && parseInt(input.value) < 1) {
                errors.push('Rol katılımcı sınırları 1\'den az olamaz.');
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Lütfen aşağıdaki hataları düzeltin:\n\n' + errors.join('\n'), 'danger');
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            
            // Re-enable button after a delay (in case of errors)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        }
        
        return true;
    }
}

// Markdown Editor Functions
function initializeMarkdownEditor() {
    const toolbar = document.querySelector('.editor-toolbar');
    const textarea = document.getElementById('description');
    const previewArea = document.getElementById('preview-area');
    
    if (!toolbar || !textarea) return;
    
    // Toolbar button handlers
    toolbar.addEventListener('click', function(e) {
        if (e.target.classList.contains('toolbar-btn') || e.target.closest('.toolbar-btn')) {
            e.preventDefault();
            const btn = e.target.classList.contains('toolbar-btn') ? e.target : e.target.closest('.toolbar-btn');
            const action = btn.dataset.action;
            handleToolbarAction(action, textarea);
        }
    });
    
    // Real-time preview (optional)
    textarea.addEventListener('input', debounce(function() {
        updatePreview();
    }, 500));
    
    function handleToolbarAction(action, textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        let replacement = '';
        let cursorOffset = 0;
        
        switch (action) {
            case 'bold':
                replacement = `**${selectedText || 'kalın metin'}**`;
                cursorOffset = selectedText ? 0 : -2;
                break;
            case 'italic':
                replacement = `*${selectedText || 'italik metin'}*`;
                cursorOffset = selectedText ? 0 : -1;
                break;
            case 'link':
                const url = selectedText.startsWith('http') ? selectedText : 'https://';
                const linkText = selectedText.startsWith('http') ? 'link metni' : selectedText || 'link metni';
                replacement = `[${linkText}](${url})`;
                cursorOffset = selectedText ? 0 : -1;
                break;
            case 'list':
                const lines = selectedText.split('\n');
                replacement = lines.map(line => `- ${line}`).join('\n');
                if (!selectedText) {
                    replacement = '- liste öğesi';
                    cursorOffset = 0;
                }
                break;
            case 'preview':
                togglePreview();
                return;
        }
        
        // Insert the replacement text
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        
        // Set cursor position
        const newCursorPos = start + replacement.length + cursorOffset;
        textarea.setSelectionRange(newCursorPos, newCursorPos);
        textarea.focus();
    }
    
    function togglePreview() {
        if (previewArea.style.display === 'none') {
            updatePreview();
            previewArea.style.display = 'block';
            textarea.style.display = 'none';
        } else {
            previewArea.style.display = 'none';
            textarea.style.display = 'block';
            textarea.focus();
        }
    }
    
    function updatePreview() {
        if (!previewArea) return;
        
        const content = textarea.value;
        
        // Simple markdown parsing (basic implementation)
        let html = content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>')
            .replace(/^- (.*$)/gim, '<li>$1</li>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/\n/g, '<br>');
        
        // Wrap list items
        html = html.replace(/(<li>.*<\/li>)/g, '<ul>$1</ul>');
        
        previewArea.innerHTML = html || '<em>Önizleme alanı...</em>';
    }
}

// Utility Functions
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

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// File upload preview
document.addEventListener('change', function(e) {
    if (e.target.type === 'file' && e.target.name === 'thumbnail') {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Remove existing preview
                const existingPreview = document.querySelector('.thumbnail-preview');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                // Create new preview
                const preview = document.createElement('div');
                preview.className = 'thumbnail-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Thumbnail preview" style="max-width: 200px; max-height: 100px; margin-top: 10px; border-radius: 4px;">
                    <p><small>Yeni thumbnail önizleme</small></p>
                `;
                
                e.target.parentElement.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
    }
});

// Auto-resize textarea
document.addEventListener('input', function(e) {
    if (e.target.tagName === 'TEXTAREA') {
        e.target.style.height = 'auto';
        e.target.style.height = e.target.scrollHeight + 'px';
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + B for bold
    if ((e.ctrlKey || e.metaKey) && e.key === 'b' && e.target.id === 'description') {
        e.preventDefault();
        handleToolbarAction('bold', e.target);
    }
    
    // Ctrl/Cmd + I for italic
    if ((e.ctrlKey || e.metaKey) && e.key === 'i' && e.target.id === 'description') {
        e.preventDefault();
        handleToolbarAction('italic', e.target);
    }
    
    // Ctrl/Cmd + K for link
    if ((e.ctrlKey || e.metaKey) && e.key === 'k' && e.target.id === 'description') {
        e.preventDefault();
        handleToolbarAction('link', e.target);
    }
    
    // Escape key to close modals
    if (e.key === 'Escape') {
        const modal = document.querySelector('.modal[style*="block"]');
        if (modal) {
            modal.style.display = 'none';
        }
    }
});

// Initialize tooltips (if you have a tooltip library)
document.addEventListener('DOMContentLoaded', function() {
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            // Simple tooltip implementation
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.title;
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 0.5rem;
                border-radius: 4px;
                font-size: 0.8rem;
                z-index: 1000;
                pointer-events: none;
                white-space: nowrap;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            `;
            
            document.body.appendChild(tooltip);
            this.setAttribute('data-original-title', this.title);
            this.removeAttribute('title');
            
            const updateTooltipPosition = (e) => {
                tooltip.style.left = e.pageX + 10 + 'px';
                tooltip.style.top = e.pageY - tooltip.offsetHeight - 10 + 'px';
            };
            
            this.addEventListener('mousemove', updateTooltipPosition);
            updateTooltipPosition({ pageX: this.getBoundingClientRect().left, pageY: this.getBoundingClientRect().top });
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip');
            if (tooltip) {
                tooltip.remove();
            }
            if (this.getAttribute('data-original-title')) {
                this.title = this.getAttribute('data-original-title');
                this.removeAttribute('data-original-title');
            }
        });
    });
});

// Form auto-save functionality (optional)
let autoSaveInterval;
let formData = {};

function initializeAutoSave() {
    const form = document.getElementById('eventForm');
    if (!form) return;
    
    // Save form data every 30 seconds
    autoSaveInterval = setInterval(() => {
        saveFormData();
    }, 30000);
    
    // Save on page unload
    window.addEventListener('beforeunload', function(e) {
        const hasUnsavedChanges = checkForUnsavedChanges();
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'Kaydedilmemiş değişiklikleriniz var. Sayfadan çıkmak istediğinizden emin misiniz?';
            saveFormData();
        }
    });
    
    // Load saved data on page load
    loadFormData();
}

function saveFormData() {
    const form = document.getElementById('eventForm');
    if (!form) return;
    
    const data = new FormData(form);
    const serialized = {};
    
    for (let [key, value] of data.entries()) {
        if (serialized[key]) {
            if (Array.isArray(serialized[key])) {
                serialized[key].push(value);
            } else {
                serialized[key] = [serialized[key], value];
            }
        } else {
            serialized[key] = value;
        }
    }
    
    localStorage.setItem('event_form_autosave', JSON.stringify({
        data: serialized,
        timestamp: Date.now(),
        url: window.location.href
    }));
}

function loadFormData() {
    try {
        const saved = localStorage.getItem('event_form_autosave');
        if (!saved) return;
        
        const parsedData = JSON.parse(saved);
        
        // Check if data is recent (within 1 hour) and for the same page
        if (Date.now() - parsedData.timestamp > 3600000 || parsedData.url !== window.location.href) {
            localStorage.removeItem('event_form_autosave');
            return;
        }
        
        // Ask user if they want to restore
        if (confirm('Kaydedilmemiş form verileriniz bulundu. Geri yüklemek ister misiniz?')) {
            restoreFormData(parsedData.data);
            showNotification('Form verileri geri yüklendi.', 'success');
        } else {
            localStorage.removeItem('event_form_autosave');
        }
    } catch (error) {
        console.error('Auto-save load error:', error);
        localStorage.removeItem('event_form_autosave');
    }
}

function restoreFormData(data) {
    const form = document.getElementById('eventForm');
    if (!form) return;
    
    Object.keys(data).forEach(key => {
        const elements = form.querySelectorAll(`[name="${key}"]`);
        elements.forEach(element => {
            if (element.type === 'checkbox' || element.type === 'radio') {
                if (Array.isArray(data[key])) {
                    element.checked = data[key].includes(element.value);
                } else {
                    element.checked = element.value === data[key];
                }
            } else {
                element.value = Array.isArray(data[key]) ? data[key][0] : data[key];
            }
        });
    });
}

function checkForUnsavedChanges() {
    const form = document.getElementById('eventForm');
    if (!form) return false;
    
    const currentData = new FormData(form);
    const currentSerialized = {};
    
    for (let [key, value] of currentData.entries()) {
        currentSerialized[key] = value;
    }
    
    return JSON.stringify(currentSerialized) !== JSON.stringify(formData);
}

// Initialize auto-save when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only enable auto-save in create mode, not edit mode
    if (!window.location.href.includes('edit=')) {
        initializeAutoSave();
    }
});

// Clean up auto-save data when form is successfully submitted
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('eventForm');
    if (form) {
        form.addEventListener('submit', function() {
            // Clear auto-save data on successful submission
            setTimeout(() => {
                localStorage.removeItem('event_form_autosave');
            }, 1000);
        });
    }
});

// Additional utility functions for role management
function handleToolbarAction(action, textarea) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    let replacement = '';
    let cursorOffset = 0;
    
    switch (action) {
        case 'bold':
            replacement = `**${selectedText || 'kalın metin'}**`;
            cursorOffset = selectedText ? 0 : -2;
            break;
        case 'italic':
            replacement = `*${selectedText || 'italik metin'}*`;
            cursorOffset = selectedText ? 0 : -1;
            break;
        case 'link':
            const url = selectedText.startsWith('http') ? selectedText : 'https://';
            const linkText = selectedText.startsWith('http') ? 'link metni' : selectedText || 'link metni';
            replacement = `[${linkText}](${url})`;
            cursorOffset = selectedText ? 0 : -1;
            break;
        case 'list':
            const lines = selectedText.split('\n');
            replacement = lines.map(line => `- ${line}`).join('\n');
            if (!selectedText) {
                replacement = '- liste öğesi';
                cursorOffset = 0;
            }
            break;
    }
    
    // Insert the replacement text
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Set cursor position
    const newCursorPos = start + replacement.length + cursorOffset;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    textarea.focus();
}

// Enhanced role validation
function validateRoleConfiguration() {
    const form = document.getElementById('eventForm');
    if (!form) return true;
    
    const roleSelects = form.querySelectorAll('.role-select');
    const selectedRoles = [];
    let hasError = false;
    
    // Check for duplicate role selections
    roleSelects.forEach(select => {
        if (select.value) {
            if (selectedRoles.includes(select.value)) {
                showNotification('Aynı rol birden fazla kez seçilemez.', 'danger');
                select.focus();
                hasError = true;
                return false;
            }
            selectedRoles.push(select.value);
        }
    });
    
    return !hasError;
}

// Enhanced form submission with better error handling
function enhancedFormSubmission(e) {
    const form = e.target;
    
    // Validate role configuration
    if (!validateRoleConfiguration()) {
        e.preventDefault();
        return false;
    }
    
    // Show confirmation for role removals
    const removeInputs = form.querySelectorAll('input[name="remove_roles[]"]');
    if (removeInputs.length > 0) {
        const confirmMsg = `${removeInputs.length} rol kaldırılacak. Bu işlem geri alınamaz. Devam etmek istediğinizden emin misiniz?`;
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
    }
    
    // Check for participant limit changes
    const changedLimits = [];
    const limitInputs = form.querySelectorAll('.participant-limit-input');
    limitInputs.forEach(input => {
        const original = input.getAttribute('data-original-value');
        if (original && original !== input.value) {
            changedLimits.push({
                role: input.closest('.existing-role-item').querySelector('.role-name').textContent,
                oldLimit: original,
                newLimit: input.value
            });
        }
    });
    
    if (changedLimits.length > 0) {
        let confirmMsg = 'Aşağıdaki roller için katılımcı limitleri değiştirilecek:\n\n';
        changedLimits.forEach(change => {
            confirmMsg += `${change.role}: ${change.oldLimit} → ${change.newLimit}\n`;
        });
        confirmMsg += '\nDevam etmek istediğinizden emin misiniz?';
        
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
}

// Add enhanced form submission handler
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('eventForm');
    if (form) {
        form.addEventListener('submit', enhancedFormSubmission);
    }
});

// Performance optimization: Lazy load role descriptions
const roleDescriptionCache = new Map();

function getCachedRoleDescription(roleId) {
    if (roleDescriptionCache.has(roleId)) {
        return Promise.resolve(roleDescriptionCache.get(roleId));
    }
    
    return fetch(`actions/get_role_description.php?id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                roleDescriptionCache.set(roleId, data.role);
                return data.role;
            }
            throw new Error('Failed to fetch role description');
        });
}

// Accessibility improvements
document.addEventListener('DOMContentLoaded', function() {
    // Add ARIA labels and descriptions
    const form = document.getElementById('eventForm');
    if (form) {
        // Add form description
        form.setAttribute('aria-describedby', 'form-description');
        
        // Add required field indicators
        const requiredFields = form.querySelectorAll('input[required], textarea[required], select[required]');
        requiredFields.forEach(field => {
            const label = form.querySelector(`label[for="${field.id}"]`);
            if (label && !label.textContent.includes('*')) {
                label.innerHTML += ' <span class="required-indicator" aria-label="gerekli">*</span>';
            }
        });
        
        // Add keyboard navigation for role items
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.classList.contains('btn-secondary')) {
                e.preventDefault();
                e.target.click();
            }
        });
    }
    
    // Improve modal accessibility
    const modal = document.getElementById('participantWarningModal');
    if (modal) {
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'modal-title');
        
        // Focus management
        modal.addEventListener('show', function() {
            const firstFocusable = modal.querySelector('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocusable) {
                firstFocusable.focus();
            }
        });
    }
});

// Error handling and user feedback improvements
function handleAjaxError(error, context = '') {
    console.error(`AJAX Error ${context}:`, error);
    showNotification(`Bir hata oluştu${context ? ` (${context})` : ''}. Lütfen tekrar deneyin.`, 'danger');
}

// Final initialization check
document.addEventListener('DOMContentLoaded', function() {
    console.log('Event form JavaScript initialized successfully');
    
    // Check for required elements
    const requiredElements = ['eventForm', 'add-role-btn', 'roles-list'];
    const missingElements = requiredElements.filter(id => !document.getElementById(id));
    
    if (missingElements.length > 0) {
        console.warn('Missing required elements:', missingElements);
    }
});