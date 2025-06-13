// forum/js/create_category.js - Forum Kategori Oluşturma JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const colorInput = document.getElementById('color');
    const colorPreview = document.getElementById('color-preview');
    const iconInput = document.getElementById('icon');
    const iconPreview = document.getElementById('icon-preview');
    const visibilitySelect = document.getElementById('visibility');
    const rolesContainer = document.getElementById('roles-container');
    const form = document.querySelector('.create-category-form');
    const nameInput = document.getElementById('name');
    const descriptionInput = document.getElementById('description');
    
    // Number input z-index fix
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        // Force z-index and positioning
        input.style.position = 'relative';
        input.style.zIndex = '1';
        input.style.webkitAppearance = 'none';
        input.style.mozAppearance = 'textfield';
        
        // Remove native spinner buttons
        input.addEventListener('wheel', (e) => {
            e.preventDefault();
        });
        
        // Ensure parent has lower z-index
        input.parentNode.style.zIndex = '1';
    });
    
    // Fix form row stacking context
    const formRows = document.querySelectorAll('.form-row');
    formRows.forEach((row, index) => {
        const groups = row.querySelectorAll('.form-group');
        groups.forEach((group, groupIndex) => {
            // Icon input gets higher z-index
            if (group.querySelector('#icon')) {
                group.style.zIndex = '10';
            } else {
                group.style.zIndex = '1';
            }
        });
    });
    initColorPreview();
    initIconPreview();
    initVisibilityToggle();
    initFormValidation();
    initCharacterCounters();
    initFormProgress();
    initSlugPreview();
    initAutoSave();
    initKeyboardShortcuts();
    
    // Color Preview Enhancement
    function initColorPreview() {
        function updateColorPreview() {
            const color = colorInput.value;
            if (isValidHexColor(color)) {
                colorPreview.style.backgroundColor = color;
                colorInput.classList.remove('error');
                colorInput.classList.add('success');
            } else {
                colorPreview.style.backgroundColor = '#bd912a';
                colorInput.classList.add('error');
                colorInput.classList.remove('success');
            }
        }
        
        colorInput.addEventListener('input', debounce(updateColorPreview, 300));
        colorInput.addEventListener('blur', updateColorPreview);
        updateColorPreview();
        
        // Color picker enhancement
        const colorPicker = document.createElement('input');
        colorPicker.type = 'color';
        colorPicker.style.display = 'none';
        colorInput.parentNode.appendChild(colorPicker);
        
        colorPreview.addEventListener('click', () => {
            colorPicker.click();
        });
        
        colorPicker.addEventListener('change', () => {
            colorInput.value = colorPicker.value;
            updateColorPreview();
        });
    }
    
    // Icon Preview Enhancement
    function initIconPreview() {
        const iconSuggestions = [
            'fas fa-comments', 'fas fa-users', 'fas fa-gamepad', 'fas fa-rocket',
            'fas fa-shield-alt', 'fas fa-star', 'fas fa-cog', 'fas fa-globe',
            'fas fa-book', 'fas fa-lightbulb', 'fas fa-trophy', 'fas fa-heart',
            'fas fa-fire', 'fas fa-bolt', 'fas fa-gem', 'fas fa-crown'
        ];
        
        function updateIconPreview() {
            const iconClass = iconInput.value.trim();
            if (iconClass) {
                // Remove old classes and add new ones
                iconPreview.className = iconClass;
                iconInput.classList.remove('error');
                iconInput.classList.add('success');
            } else {
                iconPreview.className = 'fas fa-comments';
                iconInput.classList.add('error');
                iconInput.classList.remove('success');
            }
        }
        
        iconInput.addEventListener('input', debounce(updateIconPreview, 300));
        iconInput.addEventListener('blur', updateIconPreview);
        
        // Create icon suggestion dropdown
        const suggestionContainer = document.createElement('div');
        suggestionContainer.className = 'icon-suggestions';
        suggestionContainer.style.cssText = `
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: 0 !important;
            background: var(--card-bg-2) !important;
            border: 2px solid var(--border-1) !important;
            border-radius: 8px !important;
            max-height: 200px !important;
            overflow-y: auto !important;
            z-index: 9999 !important;
            display: none !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3) !important;
            backdrop-filter: blur(10px) !important;
            margin-top: 2px !important;
        `;
        
        // Set the parent container positioning
        iconInput.parentNode.style.position = 'relative';
        iconInput.parentNode.style.zIndex = '10';
        iconInput.parentNode.appendChild(suggestionContainer);
        
        // Populate suggestions
        iconSuggestions.forEach(icon => {
            const item = document.createElement('div');
            item.style.cssText = `
                padding: 0.5rem 1rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                transition: background 0.2s ease;
            `;
            item.innerHTML = `<i class="${icon}"></i> ${icon}`;
            
            item.addEventListener('click', () => {
                iconInput.value = icon;
                updateIconPreview();
                suggestionContainer.style.display = 'none';
            });
            
            item.addEventListener('mouseenter', () => {
                item.style.background = 'var(--card-bg-3)';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.background = 'transparent';
            });
            
            suggestionContainer.appendChild(item);
        });
        
        // Show/hide suggestions
        iconInput.addEventListener('focus', () => {
            suggestionContainer.style.display = 'block';
        });
        
        document.addEventListener('click', (e) => {
            if (!iconInput.contains(e.target) && !suggestionContainer.contains(e.target)) {
                suggestionContainer.style.display = 'none';
            }
        });
    }
    
    // Visibility Toggle Enhancement
    function initVisibilityToggle() {
        function toggleRolesVisibility() {
            const shouldShow = visibilitySelect.value === 'faction_only';
            rolesContainer.style.display = shouldShow ? 'block' : 'none';
            
            // Animate the container
            if (shouldShow) {
                rolesContainer.style.opacity = '0';
                rolesContainer.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    rolesContainer.style.transition = 'all 0.3s ease';
                    rolesContainer.style.opacity = '1';
                    rolesContainer.style.transform = 'translateY(0)';
                }, 10);
            }
        }
        
        visibilitySelect.addEventListener('change', toggleRolesVisibility);
        toggleRolesVisibility();
    }
    
    // Form Validation Enhancement
    function initFormValidation() {
        const submitBtn = form.querySelector('.btn-primary');
        const originalText = submitBtn.innerHTML;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm()) {
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Oluşturuluyor...';
                submitBtn.disabled = true;
                
                // Submit after short delay for UX
                setTimeout(() => {
                    form.submit();
                }, 500);
            }
        });
        
        function validateForm() {
            let isValid = true;
            const errors = [];
            
            // Name validation
            if (!nameInput.value.trim()) {
                showFieldError(nameInput, 'Kategori adı gereklidir');
                isValid = false;
            } else if (nameInput.value.length > 100) {
                showFieldError(nameInput, 'Kategori adı en fazla 100 karakter olabilir');
                isValid = false;
            } else {
                clearFieldError(nameInput);
            }
            
            // Description validation
            if (descriptionInput.value.length > 1000) {
                showFieldError(descriptionInput, 'Açıklama en fazla 1000 karakter olabilir');
                isValid = false;
            } else {
                clearFieldError(descriptionInput);
            }
            
            // Color validation
            if (!isValidHexColor(colorInput.value)) {
                showFieldError(colorInput, 'Geçerli bir HEX renk kodu girin (örn: #bd912a)');
                isValid = false;
            } else {
                clearFieldError(colorInput);
            }
            
            // Icon validation
            if (!iconInput.value.trim()) {
                showFieldError(iconInput, 'Simge sınıfı gereklidir');
                isValid = false;
            } else {
                clearFieldError(iconInput);
            }
            
            // Roles validation for faction_only
            if (visibilitySelect.value === 'faction_only') {
                const checkedRoles = rolesContainer.querySelectorAll('input[type="checkbox"]:checked');
                if (checkedRoles.length === 0) {
                    showError('Rol bazlı görünürlük için en az bir rol seçmelisiniz');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        function showFieldError(field, message) {
            clearFieldError(field);
            field.classList.add('error');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.style.cssText = `
                color: #dc3545;
                font-size: 0.8rem;
                margin-top: 0.25rem;
                animation: fadeInUp 0.3s ease;
            `;
            errorDiv.textContent = message;
            
            field.parentNode.appendChild(errorDiv);
        }
        
        function clearFieldError(field) {
            field.classList.remove('error');
            const errorDiv = field.parentNode.querySelector('.field-error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
        
        function showError(message) {
            const existingAlert = document.querySelector('.alert-danger');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = message;
            alert.style.animation = 'fadeInUp 0.3s ease';
            
            form.insertBefore(alert, form.firstChild);
            
            // Scroll to alert
            alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
    }
    
    // Character Counters
    function initCharacterCounters() {
        addCharacterCounter(nameInput, 100);
        addCharacterCounter(descriptionInput, 1000);
        
        function addCharacterCounter(input, maxLength) {
            const counter = document.createElement('div');
            counter.className = 'char-counter';
            input.parentNode.appendChild(counter);
            
            function updateCounter() {
                const currentLength = input.value.length;
                const remaining = maxLength - currentLength;
                
                counter.textContent = `${currentLength}/${maxLength}`;
                
                if (remaining < 20) {
                    counter.className = 'char-counter danger';
                } else if (remaining < 50) {
                    counter.className = 'char-counter warning';
                } else {
                    counter.className = 'char-counter';
                }
            }
            
            input.addEventListener('input', updateCounter);
            updateCounter();
        }
    }
    
    // Form Progress Indicator
    function initFormProgress() {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'form-progress';
        progressContainer.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.9rem; color: var(--light-grey);">Form Tamamlanma</span>
                <span id="progress-text" style="font-size: 0.8rem; color: var(--gold);">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
        `;
        
        form.insertBefore(progressContainer, form.firstChild);
        
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        
        function updateProgress() {
            const fields = [nameInput, descriptionInput, iconInput, colorInput];
            const filledFields = fields.filter(field => field.value.trim() !== '').length;
            const progress = Math.round((filledFields / fields.length) * 100);
            
            progressFill.style.width = progress + '%';
            progressText.textContent = progress + '%';
        }
        
        // Update progress on input
        [nameInput, descriptionInput, iconInput, colorInput].forEach(input => {
            input.addEventListener('input', debounce(updateProgress, 200));
        });
        
        updateProgress();
    }
    
    // Slug Preview
    function initSlugPreview() {
        const slugPreview = document.createElement('div');
        slugPreview.style.cssText = `
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--card-bg-2);
            border: 1px solid var(--border-1);
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--light-grey);
        `;
        
        nameInput.parentNode.appendChild(slugPreview);
        
        function updateSlugPreview() {
            const slug = createSlug(nameInput.value);
            slugPreview.innerHTML = `<strong>URL:</strong> /forum/category/${slug}`;
        }
        
        nameInput.addEventListener('input', debounce(updateSlugPreview, 300));
        updateSlugPreview();
    }
    
    // Auto Save to localStorage
    function initAutoSave() {
        const formData = {
            name: nameInput,
            description: descriptionInput,
            icon: iconInput,
            color: colorInput,
            display_order: document.getElementById('display_order'),
            visibility: visibilitySelect
        };
        
        // Load saved data
        Object.keys(formData).forEach(key => {
            const savedValue = localStorage.getItem(`category_form_${key}`);
            if (savedValue && formData[key]) {
                formData[key].value = savedValue;
            }
        });
        
        // Save data on input
        Object.keys(formData).forEach(key => {
            if (formData[key]) {
                formData[key].addEventListener('input', debounce(() => {
                    localStorage.setItem(`category_form_${key}`, formData[key].value);
                }, 500));
            }
        });
        
        // Clear saved data on successful submit
        form.addEventListener('submit', () => {
            Object.keys(formData).forEach(key => {
                localStorage.removeItem(`category_form_${key}`);
            });
        });
        
        // Show auto-save indicator
        let autoSaveTimeout;
        Object.values(formData).forEach(input => {
            if (input) {
                input.addEventListener('input', () => {
                    showAutoSaveIndicator();
                });
            }
        });
        
        function showAutoSaveIndicator() {
            clearTimeout(autoSaveTimeout);
            
            let indicator = document.getElementById('auto-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'auto-save-indicator';
                indicator.style.cssText = `
                    position: fixed;
                    top: 5rem;
                    right: 20px;
                    background: var(--gold);
                    color: var(--charcoal);
                    padding: 0.5rem 1rem;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    z-index: 1000;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                `;
                document.body.appendChild(indicator);
            }
            
            indicator.textContent = '💾 Otomatik kaydedildi';
            indicator.style.transform = 'translateX(0)';
            
            autoSaveTimeout = setTimeout(() => {
                indicator.style.transform = 'translateX(100%)';
            }, 2000);
        }
    }
    
    // Keyboard Shortcuts
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
            
            // Ctrl/Cmd + Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
            
            // Escape to clear current field
            if (e.key === 'Escape') {
                if (document.activeElement && document.activeElement.tagName === 'INPUT') {
                    document.activeElement.blur();
                }
            }
        });
        
        // Show keyboard shortcuts help
        const helpButton = document.createElement('button');
        helpButton.type = 'button';
        helpButton.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gold);
            color: var(--charcoal);
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 1000;
            transition: all 0.3s ease;
        `;
        helpButton.innerHTML = '?';
        helpButton.title = 'Klavye kısayollarını göster';
        
        helpButton.addEventListener('click', showKeyboardHelp);
        document.body.appendChild(helpButton);
        
        function showKeyboardHelp() {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.8);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            `;
            
            modal.innerHTML = `
                <div style="
                    background: var(--card-bg);
                    border: 1px solid var(--border-1);
                    border-radius: 12px;
                    padding: 2rem;
                    max-width: 400px;
                    width: 90%;
                ">
                    <h3 style="color: var(--gold); margin-bottom: 1rem;">Klavye Kısayolları</h3>
                    <div style="color: var(--light-grey); line-height: 1.6;">
                        <div><kbd>Ctrl + S</kbd> - Formu kaydet</div>
                        <div><kbd>Ctrl + Enter</kbd> - Formu gönder</div>
                        <div><kbd>Esc</kbd> - Aktif alanı temizle</div>
                    </div>
                    <button onclick="this.closest('.modal').remove()" style="
                        margin-top: 1rem;
                        background: var(--gold);
                        color: var(--charcoal);
                        border: none;
                        padding: 0.5rem 1rem;
                        border-radius: 6px;
                        cursor: pointer;
                    ">Tamam</button>
                </div>
            `;
            
            modal.className = 'modal';
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            document.body.appendChild(modal);
        }
    }
    
    // Utility Functions
    function isValidHexColor(color) {
        return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(color);
    }
    
    function createSlug(string) {
        return string
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9-]/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
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
    
    // Form Enhancement Completion
    console.log('✅ Forum kategori oluşturma sayfası geliştirildi');
    
    // Add smooth scrolling to form errors
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && node.classList && node.classList.contains('alert-danger')) {
                        node.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
    
    // Enhanced role selection with search
    const roleCheckboxes = document.querySelectorAll('input[name="role_ids[]"]');
    if (roleCheckboxes.length > 6) {
        addRoleSearch();
    }
    
    function addRoleSearch() {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Rol ara...';
        searchInput.style.cssText = `
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            background: var(--card-bg);
            border: 1px solid var(--border-1);
            border-radius: 6px;
            color: var(--lighter-grey);
        `;
        
        rolesContainer.insertBefore(searchInput, rolesContainer.querySelector('.roles-list'));
        
        searchInput.addEventListener('input', debounce((e) => {
            const searchTerm = e.target.value.toLowerCase();
            const roleItems = rolesContainer.querySelectorAll('.form-check');
            
            roleItems.forEach(item => {
                const roleName = item.querySelector('.role-name').textContent.toLowerCase();
                const shouldShow = roleName.includes(searchTerm);
                item.style.display = shouldShow ? 'flex' : 'none';
            });
        }, 200));
    }
    
    // Add tooltips for better UX
    const tooltips = {
        'name': 'Kategori adı forum ana sayfasında görüntülenecektir',
        'description': 'Kategorinin amacını açıklayan kısa bir metin',
        'icon': 'Font Awesome simge sınıfı (örn: fas fa-comments)',
        'color': 'Kategorinin temsil rengini belirler',
        'display_order': 'Küçük sayılar üstte görüntülenir',
        'visibility': 'Kategoriye kimler erişebileceğini belirler'
    };
    
    Object.keys(tooltips).forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            const label = field.parentNode.querySelector('label');
            if (label) {
                const tooltip = document.createElement('span');
                tooltip.innerHTML = ' ℹ️';
                tooltip.style.cursor = 'help';
                tooltip.title = tooltips[fieldName];
                label.appendChild(tooltip);
            }
        }
    });
    
    // Initialize triggerred features
    setTimeout(() => {
        // Trigger initial validations and previews
        colorInput.dispatchEvent(new Event('input'));
        iconInput.dispatchEvent(new Event('input'));
        if (nameInput.value) {
            nameInput.dispatchEvent(new Event('input'));
        }
    }, 100);
});