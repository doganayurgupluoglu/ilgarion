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

// BBCode editor functions - Genişletilmiş
function insertBBCode(tag, param = null) {
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
            
        case 'email':
            const email = prompt('E-posta adresi girin:');
            if (email) {
                replacement = selectedText ? `[email=${email}]${selectedText}[/email]` : `[email=${email}][/email]`;
            } else {
                return;
            }
            break;
            
        case 'youtube':
            const youtube = prompt('YouTube video URL\'si girin:');
            if (youtube) {
                replacement = `[youtube]${youtube}[/youtube]`;
            } else {
                return;
            }
            break;
            
        case 'spoiler':
            if (param) {
                replacement = `[spoiler=${param}]${selectedText || 'Spoiler içeriği'}[/spoiler]`;
            } else {
                const spoilerTitle = prompt('Spoiler başlığı (opsiyonel):');
                if (spoilerTitle) {
                    replacement = `[spoiler=${spoilerTitle}]${selectedText || 'Spoiler içeriği'}[/spoiler]`;
                } else {
                    replacement = `[spoiler]${selectedText || 'Spoiler içeriği'}[/spoiler]`;
                }
            }
            break;
            
        case 'code':
            const language = prompt('Programlama dili (opsiyonel - php, javascript, python, vb.):');
            if (language) {
                replacement = `[code=${language}]${selectedText || 'Kod buraya'}[/code]`;
            } else {
                replacement = `[code]${selectedText || 'Kod buraya'}[/code]`;
            }
            break;
            
        case 'list':
            const listType = prompt('Liste tipi:\n• Boş bırakın = işaretli liste\n• 1 = numaralı liste\n• a = küçük harf\n• A = büyük harf');
            if (listType) {
                replacement = `[list=${listType}]\n[*]Öğe 1\n[*]Öğe 2\n[*]Öğe 3\n[/list]`;
            } else {
                replacement = `[list]\n[*]Öğe 1\n[*]Öğe 2\n[*]Öğe 3\n[/list]`;
            }
            break;
            
        case 'table':
            replacement = `[table]\n[tr]\n[th]Başlık 1[/th]\n[th]Başlık 2[/th]\n[/tr]\n[tr]\n[td]Hücre 1[/td]\n[td]Hücre 2[/td]\n[/tr]\n[/table]`;
            break;
            
        case 'font':
            replacement = selectedText ? `[font=Arial]${selectedText}[/font]` : `[font=Arial][/font]`;
            break;
            
        case 'highlight':
            const highlightColor = prompt('Vurgu rengi (sarı, pembe, yeşil veya hex kod):') || 'yellow';
            replacement = `[highlight=${highlightColor}]${selectedText || 'Vurgulanan metin'}[/highlight]`;
            break;
            
        case 'center':
        case 'left':
        case 'right':
        case 'justify':
        case 'hr':
        case 'sub':
        case 'sup':
            if (tag === 'hr') {
                replacement = '[hr]';
            } else {
                replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
            }
            break;
            
        default:
            replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Move cursor
    const newPos = start + replacement.length - (selectedText || tag === 'hr' ? 0 : `[/${tag}]`.length);
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

function insertFontCode() {
    const select = document.getElementById('fontSelect');
    const font = select.value;
    
    if (!font) return;
    
    const textarea = document.getElementById('content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    const replacement = selectedText ? `[font=${font}]${selectedText}[/font]` : `[font=${font}][/font]`;
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    const newPos = start + replacement.length - (selectedText ? 0 : '[/font]'.length);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    select.value = '';
    textarea.dispatchEvent(new Event('input'));
}

// BBCode to HTML parser for preview
function parseBBCode(text) {
    // HTML güvenliği için escape
    text = text.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
    
    // BBCode dönüşümleri
    const bbcodeRules = [
        // Basit formatlar
        { pattern: /\[b\](.*?)\[\/b\]/gi, replacement: '<strong>$1</strong>' },
        { pattern: /\[i\](.*?)\[\/i\]/gi, replacement: '<em>$1</em>' },
        { pattern: /\[u\](.*?)\[\/u\]/gi, replacement: '<u>$1</u>' },
        { pattern: /\[s\](.*?)\[\/s\]/gi, replacement: '<del>$1</del>' },
        
        // Sub ve Sup
        { pattern: /\[sub\](.*?)\[\/sub\]/gi, replacement: '<sub>$1</sub>' },
        { pattern: /\[sup\](.*?)\[\/sup\]/gi, replacement: '<sup>$1</sup>' },
        
        // Linkler
        { pattern: /\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/gi, replacement: '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>' },
        { pattern: /\[url\](https?:\/\/[^\[]+)\[\/url\]/gi, replacement: '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>' },
        
        // Email
        { pattern: /\[email\]([\w\.\-]+@[\w\.\-]+)\[\/email\]/gi, replacement: '<a href="mailto:$1">$1</a>' },
        { pattern: /\[email=([\w\.\-]+@[\w\.\-]+)\](.*?)\[\/email\]/gi, replacement: '<a href="mailto:$1">$2</a>' },
        
        // Resimler
        { pattern: /\[img\](https?:\/\/[^\[]+)\[\/img\]/gi, replacement: '<img src="$1" alt="User Image" style="max-width: 100%; height: auto; border-radius: 4px; margin: 0.5rem 0;" loading="lazy">' },
        { pattern: /\[img=([\d]+)x([\d]+)\](https?:\/\/[^\[]+)\[\/img\]/gi, replacement: '<img src="$3" width="$1" height="$2" alt="User Image" style="max-width: 100%; height: auto; border-radius: 4px; margin: 0.5rem 0;" loading="lazy">' },
        
        // YouTube
        { pattern: /\[youtube\](?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)\[\/youtube\]/gi, replacement: '<div class="forum-video"><iframe width="560" height="315" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe></div>' },
        
        // Renkler
        { pattern: /\[color=(#[a-fA-F0-9]{3,6}|red|blue|green|yellow|orange|purple|pink|black|white|gray|grey|brown|cyan|magenta|lime|navy|olive|maroon|teal|silver|gold|indigo|violet|crimson)\](.*?)\[\/color\]/gi, replacement: '<span style="color: $1;">$2</span>' },
        
        // Boyutlar
        { pattern: /\[size=([1-7]|[0-9]{1,2}px|[0-9]{1,2}pt|[0-9]{1,2}em|small|medium|large|x-large|xx-large)\](.*?)\[\/size\]/gi, replacement: '<span style="font-size: $1;">$2</span>' },
        
        // Font
        { pattern: /\[font=(Arial|Helvetica|Times|Courier|Verdana|Tahoma|Georgia|Palatino|Comic Sans MS|Impact)\](.*?)\[\/font\]/gi, replacement: '<span style="font-family: $1;">$2</span>' },
        
        // Hizalama
        { pattern: /\[center\](.*?)\[\/center\]/gi, replacement: '<div style="text-align: center;">$1</div>' },
        { pattern: /\[left\](.*?)\[\/left\]/gi, replacement: '<div style="text-align: left;">$1</div>' },
        { pattern: /\[right\](.*?)\[\/right\]/gi, replacement: '<div style="text-align: right;">$1</div>' },
        { pattern: /\[justify\](.*?)\[\/justify\]/gi, replacement: '<div style="text-align: justify;">$1</div>' },
        
        // Spoiler
        { pattern: /\[spoiler\](.*?)\[\/spoiler\]/gi, replacement: '<details class="forum-spoiler"><summary>Spoiler (görmek için tıklayın)</summary>$1</details>' },
        { pattern: /\[spoiler=(.*?)\](.*?)\[\/spoiler\]/gi, replacement: '<details class="forum-spoiler"><summary>$1</summary>$2</details>' },
        
        // Highlight
        { pattern: /\[highlight\](.*?)\[\/highlight\]/gi, replacement: '<mark style="background-color: yellow; color: black;">$1</mark>' },
        { pattern: /\[highlight=(#[a-fA-F0-9]{3,6}|yellow|lime|cyan|pink|orange)\](.*?)\[\/highlight\]/gi, replacement: '<mark style="background-color: $1; color: black;">$2</mark>' },
        
        // Horizontal line
        { pattern: /\[hr\]/gi, replacement: '<hr class="forum-hr">' },
        
        // Code blocks
        { pattern: /\[code\](.*?)\[\/code\]/gi, replacement: '<pre class="forum-code"><code>$1</code></pre>' },
        { pattern: /\[code=(php|javascript|python|html|css|sql|cpp|java|csharp)\](.*?)\[\/code\]/gi, replacement: '<pre class="forum-code" data-language="$1"><code>$2</code></pre>' },
        
        // Table
        { pattern: /\[table\](.*?)\[\/table\]/gi, replacement: '<table class="forum-table">$1</table>' },
        { pattern: /\[tr\](.*?)\[\/tr\]/gi, replacement: '<tr>$1</tr>' },
        { pattern: /\[td\](.*?)\[\/td\]/gi, replacement: '<td>$1</td>' },
        { pattern: /\[th\](.*?)\[\/th\]/gi, replacement: '<th>$1</th>' },
        
        // List items (önce bunları işle)
        { pattern: /\[\*\](.*)$/gm, replacement: '<li>$1</li>' }
    ];
    
    // BBCode'ları uygula
    bbcodeRules.forEach(rule => {
        text = text.replace(rule.pattern, rule.replacement);
    });
    
    // Listeleri işle
    text = text.replace(/\[list\](.*?)\[\/list\]/gi, '<ul class="forum-list">$1</ul>');
    text = text.replace(/\[list=1\](.*?)\[\/list\]/gi, '<ol class="forum-list">$1</ol>');
    text = text.replace(/\[list=a\](.*?)\[\/list\]/gi, '<ol class="forum-list" type="a">$1</ol>');
    text = text.replace(/\[list=A\](.*?)\[\/list\]/gi, '<ol class="forum-list" type="A">$1</ol>');
    
    // Quote'ları işle (basit versiyon)
    text = text.replace(/\[quote\](.*?)\[\/quote\]/gi, '<blockquote class="forum-quote">$1</blockquote>');
    text = text.replace(/\[quote=(.*?)\](.*?)\[\/quote\]/gi, '<blockquote class="forum-quote"><cite>$1 yazdı:</cite>$2</blockquote>');
    
    // Satır sonlarını <br> ile değiştir
    text = text.replace(/\n/g, '<br>');
    
    return text;
}

function previewContent() {
    const content = document.getElementById('content').value;
    const previewDiv = document.getElementById('content-preview');
    const previewContent = previewDiv.querySelector('.preview-content');
    
    if (!content.trim()) {
        alert('Önizlemek için içerik girin.');
        return;
    }
    
    // Parse BBCode and show preview
    previewContent.innerHTML = parseBBCode(content);
    previewDiv.style.display = 'block';
    previewDiv.scrollIntoView({ behavior: 'smooth' });
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