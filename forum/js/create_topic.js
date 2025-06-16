// public/forum/js/create_topic.js - Final and most robust version

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createTopicForm');
    if (!form) return;

    // --- Form-level Enter Key Prevention ---
    // This is the most reliable way to prevent form submission on Enter press
    // from any input field except textareas and submit buttons.
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.nodeName === 'INPUT' && e.target.type !== 'submit') {
            e.preventDefault();
        }
    });

    // --- Tag Input System ---
    const tagsInput = document.getElementById('tagsInput');
    const tagsDisplay = document.getElementById('tagsDisplay');
    const tagsHidden = document.getElementById('tagsHidden');
    const tagsCountEl = document.getElementById('tags-count');

    if (tagsInput) {
        const maxTags = 5;
        let tags = [];

        const updateTagsDisplay = () => {
            tagsDisplay.innerHTML = '';
            tags.forEach(tag => {
                const tagEl = document.createElement('div');
                tagEl.className = 'tag-item';
                tagEl.innerHTML = `<span>${tag}</span><i class="fas fa-times remove-tag" data-tag="${tag}"></i>`;
                tagsDisplay.appendChild(tagEl);
            });
            tagsHidden.value = tags.join(',');
            tagsCountEl.textContent = tags.length;
            tagsInput.style.display = tags.length >= maxTags ? 'none' : 'block';
            handleAutoSave(); // Trigger save on tag change
        };

        const addTag = (tagValue) => {
            const tag = tagValue.trim().toLowerCase().replace(/,/g, '');
            if (tag && !tags.includes(tag) && tags.length < maxTags) {
                tags.push(tag);
                updateTagsDisplay();
            }
            tagsInput.value = '';
        };

        const removeTag = (tagValue) => {
            tags = tags.filter(t => t !== tagValue);
            updateTagsDisplay();
        };

        const loadInitialTags = () => {
            const initialTags = (tagsHidden.value || '').split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
            if (initialTags.length > 0) {
                tags = [...new Set(initialTags)].slice(0, maxTags);
                updateTagsDisplay();
            }
        };

        tagsInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                addTag(this.value);
            }
        });

        tagsInput.addEventListener('blur', () => addTag(tagsInput.value));
        tagsDisplay.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-tag')) {
                removeTag(e.target.dataset.tag);
            }
        });

        loadInitialTags();
    }

    // --- Other Form Logic (Counters, Validation, Drafts) ---
    const titleInput = document.getElementById('title');
    const titleCountEl = document.getElementById('title-count');
    const editor = form.querySelector('.wysiwyg-content');
    const categorySelect = document.getElementById('category_id');
    const submitBtn = form.querySelector('button[type="submit"]');
    const isEditMode = new URLSearchParams(window.location.search).has('edit');
    let autoSaveTimeout;

    const updateTitleCounter = () => {
        if (!titleInput || !titleCountEl) return;
        const len = titleInput.value.length;
        titleCountEl.textContent = len;
        if (len > 230) titleCountEl.style.color = 'var(--red)';
        else if (len > 200) titleCountEl.style.color = 'var(--gold)';
        else titleCountEl.style.color = 'var(--light-grey)';
    };

    const handleAutoSave = () => {
        if (isEditMode) return;
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            const draft = {
                title: titleInput.value,
                content: editor.innerHTML,
                category_id: categorySelect.value,
                tags: tagsHidden.value,
                timestamp: Date.now()
            };
            localStorage.setItem('forum_topic_draft', JSON.stringify(draft));
        }, 2000);
    };

    const loadDraft = () => {
        if (isEditMode) return;
        const savedDraft = localStorage.getItem('forum_topic_draft');
        if (!savedDraft) return;

        try {
            const draft = JSON.parse(savedDraft);
            if (Date.now() - draft.timestamp > 86400000) { // 24 hours
                localStorage.removeItem('forum_topic_draft');
                return;
            }
            
            const isFormEmpty = titleInput.value.trim() === '' && (editor.innerHTML.trim() === '' || editor.innerHTML.trim() === '<p><br></p>');
            if (isFormEmpty && confirm('Kaydedilmiş bir taslak bulundu. Yüklemek ister misiniz?')) {
                titleInput.value = draft.title || '';
                editor.innerHTML = draft.content || '';
                categorySelect.value = draft.category_id || '';
                tagsHidden.value = draft.tags || '';
                
                if(tagsInput) tagsInput.dispatchEvent(new Event('loadInitial')); // Custom event to reload tags
                updateTitleCounter();
                showNotification('Taslak yüklendi.', 'success');
            }
        } catch (e) {
            localStorage.removeItem('forum_topic_draft');
        }
    };

    form.addEventListener('submit', function(e) {
        const title = titleInput.value.trim();
        const content = editor.innerHTML.trim();
        
        if (title.length < 3) {
            e.preventDefault();
            return showNotification('Konu başlığı en az 3 karakter olmalıdır.', 'error');
        }
        if ((content.length < 10) || content === '<p><br></p>') {
             e.preventDefault();
            return showNotification('Konu içeriği en az 10 karakter olmalıdır.', 'error');
        }
        if (!categorySelect.value) {
            e.preventDefault();
            return showNotification('Lütfen bir kategori seçin.', 'error');
        }
        
        localStorage.removeItem('forum_topic_draft');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
    });
    
    // Initial calls & event listeners
    titleInput.addEventListener('input', updateTitleCounter);
    if (!isEditMode) {
        titleInput.addEventListener('input', handleAutoSave);
        editor.addEventListener('input', handleAutoSave);
        categorySelect.addEventListener('change', handleAutoSave);
    }
    
    // Re-initialize tags if draft is loaded
    if(tagsInput) tagsInput.addEventListener('loadInitial', () => {
        const tags = tagsHidden.value.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
        const tagsComponent = document.getElementById('tagsInput');
        if (tagsComponent) {
             const event = new Event('reloadTags', {bubbles: true});
             event.tags = tags;
             tagsComponent.dispatchEvent(event);
        }
    });

    updateTitleCounter();
    loadDraft();
});


function showNotification(message, type = 'info') {
    if (window.forumManager && typeof window.forumManager.showNotification === 'function') {
        window.forumManager.showNotification(message, type);
        return;
    }

    const container = document.body;
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;

    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };

    notification.style.cssText = `
        position: fixed; top: 80px; right: 20px; padding: 15px 20px; border-radius: 6px; 
        color: white; font-weight: 500; z-index: 10000; max-width: 400px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.3); opacity: 0; 
        transform: translateX(100%); transition: all 0.3s ease; cursor: pointer;
        background-color: ${colors[type] || colors.info};
    `;

    notification.textContent = message;
    container.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);

    const timeoutId = setTimeout(() => removeNotification(notification), 4000);

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