// events/js/create_event.js - Etkinlik oluşturma sayfası JavaScript

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
    visibilitySelect.addEventListener('change', handleVisibilityChange);
    
    // Initialize role selection handling (container always visible now)
    // No checkbox to handle anymore
    
    // Add role button
    addRoleBtn.addEventListener('click', addEventRole);
    
    // Markdown editor
    initializeMarkdownEditor();
    
    // Form validation
    document.getElementById('eventForm').addEventListener('submit', validateForm);
    
    // Functions
    function handleVisibilityChange() {
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
        const clone = document.importNode(template.content, true);
        
        // Replace template variables
        const html = clone.querySelector('.role-item').outerHTML.replace(/\{\{INDEX\}\}/g, roleIndex);
        
        const div = document.createElement('div');
        div.innerHTML = html;
        const roleItem = div.firstChild;
        
        // Add event listeners
        const roleSelect = roleItem.querySelector('.role-select');
        const removeBtn = roleItem.querySelector('.remove-role');
        const descriptionDiv = roleItem.querySelector('.role-description');
        
        roleSelect.addEventListener('change', function() {
            updateRoleDescription(this, descriptionDiv);
        });
        
        removeBtn.addEventListener('click', function() {
            roleItem.remove();
        });
        
        rolesList.appendChild(roleItem);
        roleIndex++;
        
        // Focus on the new role select
        roleSelect.focus();
    }
    
    function updateRoleDescription(selectElement, descriptionDiv) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        
        if (selectedOption.value) {
            // Get role description via AJAX
            fetch(`actions/get_role_description.php?id=${selectedOption.value}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        descriptionDiv.innerHTML = `
                            <strong><i class="${data.icon_class}"></i> ${data.role_name}</strong><br>
                            ${data.description}
                        `;
                        descriptionDiv.classList.add('show');
                    }
                })
                .catch(error => {
                    console.error('Error fetching role description:', error);
                });
        } else {
            descriptionDiv.classList.remove('show');
        }
    }
    
    function initializeMarkdownEditor() {
        const toolbar = document.querySelector('.editor-toolbar');
        const textarea = document.getElementById('description');
        let isPreviewMode = false;
        
        toolbar.addEventListener('click', function(e) {
            if (e.target.closest('.editor-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.editor-btn');
                const action = btn.dataset.action;
                
                switch (action) {
                    case 'bold':
                        wrapText(textarea, '**', '**');
                        break;
                    case 'italic':
                        wrapText(textarea, '*', '*');
                        break;
                    case 'heading':
                        insertAtCursor(textarea, '## ');
                        break;
                    case 'list':
                        insertAtCursor(textarea, '- ');
                        break;
                    case 'link':
                        wrapText(textarea, '[', '](url)');
                        break;
                    case 'preview':
                        togglePreview();
                        break;
                }
                
                // Update button states
                document.querySelectorAll('.editor-btn').forEach(b => b.classList.remove('active'));
                if (action === 'preview' && isPreviewMode) {
                    btn.classList.add('active');
                }
            }
        });
        
        function wrapText(textarea, before, after) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            const replacement = before + selectedText + after;
            
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + before.length, start + before.length + selectedText.length);
        }
        
        function insertAtCursor(textarea, text) {
            const start = textarea.selectionStart;
            textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(start);
            textarea.focus();
            textarea.setSelectionRange(start + text.length, start + text.length);
        }
        
        function togglePreview() {
            if (isPreviewMode) {
                // Switch back to edit mode
                textarea.style.display = 'block';
                previewArea.style.display = 'none';
                isPreviewMode = false;
            } else {
                // Switch to preview mode
                renderMarkdownPreview();
                textarea.style.display = 'none';
                previewArea.style.display = 'block';
                isPreviewMode = true;
            }
        }
        
        function renderMarkdownPreview() {
            const content = textarea.value;
            
            // Send to server for Parsedown processing
            fetch('actions/preview_markdown.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content: content })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    previewArea.innerHTML = data.html;
                } else {
                    previewArea.innerHTML = '<p style="color: var(--red);">Önizleme yüklenemedi</p>';
                }
            })
            .catch(error => {
                console.error('Preview error:', error);
                previewArea.innerHTML = '<p style="color: var(--red);">Önizleme yüklenemedi</p>';
            });
        }
    }
    
    function validateForm(e) {
        const form = e.target;
        let isValid = true;
        const errors = [];
        
        // Basic validation
        const title = document.getElementById('title').value.trim();
        const description = document.getElementById('description').value.trim();
        const eventDateTime = document.getElementById('event_datetime').value;
        
        if (!title) {
            errors.push('Etkinlik başlığı gereklidir.');
            isValid = false;
        }
        
        if (!description) {
            errors.push('Etkinlik açıklaması gereklidir.');
            isValid = false;
        }
        
        if (!eventDateTime) {
            errors.push('Etkinlik tarihi ve saati gereklidir.');
            isValid = false;
        } else {
            const eventDate = new Date(eventDateTime);
            const now = new Date();
            if (eventDate <= now) {
                errors.push('Etkinlik tarihi gelecekte olmalıdır.');
                isValid = false;
            }
        }
        
        // Visibility validation
        if (visibilitySelect.value === 'role_restricted') {
            const checkedRoles = document.querySelectorAll('input[name="visibility_roles[]"]:checked');
            if (checkedRoles.length === 0) {
                errors.push('Rol kısıtlaması seçildiğinde en az bir rol seçmelisiniz.');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Lütfen aşağıdaki hataları düzeltin:\n\n' + errors.join('\n'));
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
        
        // Re-enable button after a delay (in case of errors)
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 10000);
        
        return true;
    }
}

// Utility functions
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