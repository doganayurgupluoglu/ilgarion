// Create topic functionality
let csrfToken = '';

document.addEventListener('DOMContentLoaded', function() {
    // Character counters
    setupCharacterCounters();
    
    // Form validation
    setupFormValidation();
    
    // Auto-save draft
    setupAutoSave();
});

function setupCharacterCounters() {
    const titleInput = document.getElementById('title');
    const contentInput = document.getElementById('content');
    const titleCount = document.getElementById('title-count');
    const contentCount = document.getElementById('content-count');
    
    if (titleInput && titleCount) {
        titleInput.addEventListener('input', function() {
            titleCount.textContent = this.value.length;
            
            if (this.value.length > 230) {
                titleCount.style.color = 'var(--red)';
            } else if (this.value.length > 200) {
                titleCount.style.color = 'var(--gold)';
            } else {
                titleCount.style.color = 'var(--light-grey)';
            }
        });
        
        // Initial count
        titleCount.textContent = titleInput.value.length;
    }
    
    if (contentInput && contentCount) {
        contentInput.addEventListener('input', function() {
            contentCount.textContent = this.value.length;
            
            if (this.value.length > 45000) {
                contentCount.style.color = 'var(--red)';
            } else if (this.value.length > 40000) {
                contentCount.style.color = 'var(--gold)';
            } else {
                contentCount.style.color = 'var(--light-grey)';
            }
        });
        
        // Initial count
        contentCount.textContent = contentInput.value.length;
    }
}

function setupFormValidation() {
    const form = document.getElementById('createTopicForm');
    
    form.addEventListener('submit', function(e) {
        // Temel validasyon
        const title = document.getElementById('title').value.trim();
        const content = document.getElementById('content').value.trim();
        const categoryId = document.getElementById('category_id').value;
        
        if (!title || title.length < 3) {
            e.preventDefault();
            alert('Konu başlığı en az 3 karakter olmalıdır.');
            return false;
        }
        
        if (!content || content.length < 10) {
            e.preventDefault();
            alert('Konu içeriği en az 10 karakter olmalıdır.');
            return false;
        }
        
        if (!categoryId) {
            e.preventDefault();
            alert('Lütfen bir kategori seçin.');
            return false;
        }
        
        // Form başarıyla gönderilirse taslağı temizle
        if (this.checkValidity()) {
            localStorage.removeItem('forum_topic_draft');
        }
        
        // Disable submit button and show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Oluşturuluyor...';
    });
}

function setupAutoSave() {
    let saveTimeout;
    const titleInput = document.getElementById('title');
    const contentInput = document.getElementById('content');
    
    function saveToLocalStorage() {
        const draftData = {
            title: titleInput.value,
            content: contentInput.value,
            category_id: document.getElementById('category_id').value,
            timestamp: Date.now()
        };
        
        localStorage.setItem('forum_topic_draft', JSON.stringify(draftData));
    }
    
    function handleInput() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveToLocalStorage, 2000); // Save after 2 seconds of no typing
    }
    
    if (titleInput) titleInput.addEventListener('input', handleInput);
    if (contentInput) contentInput.addEventListener('input', handleInput);
    
    // Load draft on page load
    loadDraft();
}

function loadDraft() {
    const savedDraft = localStorage.getItem('forum_topic_draft');
    if (!savedDraft) return;
    
    try {
        const draftData = JSON.parse(savedDraft);
        
        // Check if draft is less than 24 hours old
        if (Date.now() - draftData.timestamp > 86400000) {
            localStorage.removeItem('forum_topic_draft');
            return;
        }
        
        // Only load if form is empty
        const titleInput = document.getElementById('title');
        const contentInput = document.getElementById('content');
        
        if (titleInput.value === '' && contentInput.value === '') {
            if (confirm('Kaydedilmiş bir taslak bulundu. Yüklemek ister misiniz?')) {
                titleInput.value = draftData.title || '';
                contentInput.value = draftData.content || '';
                
                if (draftData.category_id) {
                    document.getElementById('category_id').value = draftData.category_id;
                }
                
                // Update character counters
                titleInput.dispatchEvent(new Event('input'));
                contentInput.dispatchEvent(new Event('input'));
            }
        }
    } catch (e) {
        console.error('Error loading draft:', e);
        localStorage.removeItem('forum_topic_draft');
    }
}

function saveDraft() {
    const titleInput = document.getElementById('title');
    const contentInput = document.getElementById('content');
    
    if (!titleInput.value.trim() && !contentInput.value.trim()) {
        alert('Kaydedilecek içerik bulunamadı.');
        return;
    }
    
    const draftData = {
        title: titleInput.value,
        content: contentInput.value,
        category_id: document.getElementById('category_id').value,
        timestamp: Date.now()
    };
    
    localStorage.setItem('forum_topic_draft', JSON.stringify(draftData));
    showNotification('Taslak kaydedildi.', 'success');
}

// Notification function
function showNotification(message, type) {
    // Simple notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--gold);
        color: var(--charcoal);
        padding: 1rem 1.5rem;
        border-radius: 6px;
        z-index: 10000;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    if (type === 'error') {
        notification.style.background = 'var(--red)';
        notification.style.color = 'white';
    } else if (type === 'success') {
        notification.style.background = 'var(--turquase)';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}