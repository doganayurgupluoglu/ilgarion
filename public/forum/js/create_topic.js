// Create topic functionality - Basitleştirilmiş
let csrfToken = '<?= generate_csrf_token() ?>';

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
        
        // Disable submit button and show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Oluşturuluyor...';
        
        // If there's an error, the page will reload and button will be reset
        // If successful, user will be redirected
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

// BBCode editor functions
function insertBBCode(tag) {
    const textarea = document.getElementById('content');
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    let replacement;
    
    switch(tag) {
        case 'url':
            const url = prompt('URL girin:');
            if (url) {
                replacement = selectedText ? `[url=${url}]${selectedText}[/url]` : `[url=${url}][/url]`;
            } else {
                return;
            }
            break;
        case 'img':
            const imgUrl = prompt('Resim URL\'si girin:');
            if (imgUrl) {
                replacement = `[img]${imgUrl}[/img]`;
            } else {
                return;
            }
            break;
        default:
            replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Move cursor
    const newPos = start + replacement.length - (selectedText ? 0 : `[/${tag}]`.length);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    // Update character count
    textarea.dispatchEvent(new Event('input'));
}

function insertColorCode() {
    const select = document.getElementById('colorSelect');
    const color = select.value;
    
    if (!color) return;
    
    const textarea = document.getElementById('content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    const replacement = selectedText ? `[color=${color}]${selectedText}[/color]` : `[color=${color}][/color]`;
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    const newPos = start + replacement.length - (selectedText ? 0 : '[/color]'.length);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    select.value = '';
    textarea.dispatchEvent(new Event('input'));
}

function insertSizeCode() {
    const select = document.getElementById('sizeSelect');
    const size = select.value;
    
    if (!size) return;
    
    const textarea = document.getElementById('content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    const replacement = selectedText ? `[size=${size}]${selectedText}[/size]` : `[size=${size}][/size]`;
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    const newPos = start + replacement.length - (selectedText ? 0 : '[/size]'.length);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    select.value = '';
    textarea.dispatchEvent(new Event('input'));
}

function previewContent() {
    const content = document.getElementById('content').value;
    const previewDiv = document.getElementById('content-preview');
    const previewContent = previewDiv.querySelector('.preview-content');
    
    if (!content.trim()) {
        alert('Önizlemek için içerik girin.');
        return;
    }
    
    // Simple BBCode to HTML conversion for preview
    let html = content
        .replace(/\[b\](.*?)\[\/b\]/g, '<strong>$1</strong>')
        .replace(/\[i\](.*?)\[\/i\]/g, '<em>$1</em>')
        .replace(/\[u\](.*?)\[\/u\]/g, '<u>$1</u>')
        .replace(/\[s\](.*?)\[\/s\]/g, '<del>$1</del>')
        .replace(/\[url=(.*?)\](.*?)\[\/url\]/g, '<a href="$1" target="_blank" style="color: var(--turquase);">$2</a>')
        .replace(/\[url\](.*?)\[\/url\]/g, '<a href="$1" target="_blank" style="color: var(--turquase);">$1</a>')
        .replace(/\[img\](.*?)\[\/img\]/g, '<img src="$1" alt="User Image" style="max-width: 100%; height: auto; border-radius: 4px;">')
        .replace(/\[quote=(.*?)\](.*?)\[\/quote\]/g, '<blockquote class="forum-quote"><cite>$1 yazdı:</cite>$2</blockquote>')
        .replace(/\[quote\](.*?)\[\/quote\]/g, '<blockquote class="forum-quote">$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/g, '<pre class="forum-code">$1</pre>')
        .replace(/\[color=(.*?)\](.*?)\[\/color\]/g, '<span style="color: $1;">$2</span>')
        .replace(/\[size=(.*?)\](.*?)\[\/size\]/g, '<span style="font-size: $1;">$2</span>')
        .replace(/\n/g, '<br>');
    
    previewContent.innerHTML = html;
    previewDiv.style.display = 'block';
    previewDiv.scrollIntoView({ behavior: 'smooth' });
}

// Notification function
function showNotification(message, type) {
    // Simple notification - you can enhance this
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

// Clear draft when topic is successfully created
window.addEventListener('beforeunload', function() {
    // This will be called when navigating away, but we only want to clear
    // the draft if we're successfully creating a topic, not on errors
    // The form submission will handle this appropriately
});