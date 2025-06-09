// /events/js/events_create.js - Etkinlik oluşturma sayfası JavaScript işlevleri

// Global değişkenler
let roleSlotIndex = document.querySelectorAll('.role-slot').length;
let availableRoles = [];
let parsedown = null;

// Sayfa yüklendiğinde çalışacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // Server'dan gelen available roles verisini al
    if (typeof window.availableRoles !== 'undefined') {
        availableRoles = window.availableRoles;
    }
    
    initializeComponents();
    setupEventListeners();
    updateTotalParticipants();
    
    // Eğer düzenleme modundaysa rol slot indexini güncelle
    const existingSlots = document.querySelectorAll('.role-slot');
    if (existingSlots.length > 0) {
        roleSlotIndex = existingSlots.length;
    }
});

// Bileşenleri başlat
function initializeComponents() {
    // Markdown editor'ü başlat
    initializeMarkdownEditor();
    
    // Thumbnail preview'ü başlat
    initializeThumbnailPreview();
    
    // Form validasyonunu başlat
    initializeFormValidation();
    
    // Rol slotları event listener'larını başlat
    initializeRoleSlots();
}

// Event listener'ları kur
function setupEventListeners() {
    // Rol ekleme butonu
    const addRoleBtn = document.getElementById('addRoleSlot');
    if (addRoleBtn) {
        addRoleBtn.addEventListener('click', addRoleSlot);
    }
    
    // Form submit
    const eventForm = document.getElementById('eventForm');
    if (eventForm) {
        eventForm.addEventListener('submit', handleFormSubmit);
    }
    
    // Thumbnail input
    const thumbnailInput = document.getElementById('event_thumbnail');
    if (thumbnailInput) {
        thumbnailInput.addEventListener('change', handleThumbnailChange);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
}

// Markdown editor'ü başlat
function initializeMarkdownEditor() {
    const editorTabs = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    const textarea = document.getElementById('event_description');
    const previewDiv = document.querySelector('.markdown-preview');
    const toolbarButtons = document.querySelectorAll('.toolbar-btn');
    
    if (!textarea || !previewDiv) return;
    
    // Tab değiştirme
    editorTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Tab butonlarını güncelle
            editorTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Tab içeriklerini güncelle
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            if (targetTab === 'preview') {
                document.getElementById('preview-tab').classList.add('active');
                updateMarkdownPreview();
            } else {
                document.getElementById('editor-tab').classList.add('active');
                textarea.focus();
            }
        });
    });
    
    // Toolbar butonları
    toolbarButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            executeMarkdownAction(action);
        });
    });
    
    // Textarea değişikliklerini dinle
    textarea.addEventListener('input', debounce(updateMarkdownPreview, 500));
    
    // İlk önizlemeyi oluştur
    if (textarea.value.trim()) {
        updateMarkdownPreview();
    }
}

// Markdown önizlemesini güncelle
function updateMarkdownPreview() {
    const textarea = document.getElementById('event_description');
    const previewDiv = document.querySelector('.markdown-preview');
    
    if (!textarea || !previewDiv) return;
    
    const markdownText = textarea.value.trim();
    
    if (!markdownText) {
        previewDiv.innerHTML = '<p class="preview-placeholder">Önizleme için açıklama yazın...</p>';
        return;
    }
    
    // Basit markdown parse işlemi (server-side Parsedown kullanılamadığı için)
    let html = parseMarkdownBasic(markdownText);
    previewDiv.innerHTML = html;
}

// Basit markdown parser (client-side)
function parseMarkdownBasic(text) {
    // Basit markdown dönüşümleri
    let html = text;
    
    // Başlıklar
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
    
    // Kalın ve italik
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Üstü çizili
    html = html.replace(/~~(.*?)~~/g, '<del>$1</del>');
    
    // Kod
    html = html.replace(/`(.*?)`/g, '<code>$1</code>');
    
    // Resimler
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" style="max-width: 100%; height: auto; border-radius: 4px; margin: 0.5rem 0;">');
    
    // Bağlantılar
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
    
    // Alıntı
    html = html.replace(/^> (.*$)/gim, '<blockquote>$1</blockquote>');
    
    // Liste işleme (basit)
    html = html.replace(/^\* (.*$)/gim, '<li>$1</li>');
    html = html.replace(/^- (.*$)/gim, '<li>$1</li>');
    html = html.replace(/^\d+\. (.*$)/gim, '<li>$1</li>');
    
    // Li öğelerini ul/ol ile sarma (basit yaklaşım)
    html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
    
    // Satır sonları
    html = html.replace(/\n/g, '<br>');
    
    return html;
}

// Markdown action'larını uygula
function executeMarkdownAction(action) {
    const textarea = document.getElementById('event_description');
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    let replacement = '';
    
    switch (action) {
        case 'bold':
            replacement = `**${selectedText || 'kalın metin'}**`;
            break;
        case 'italic':
            replacement = `*${selectedText || 'italik metin'}*`;
            break;
        case 'strikethrough':
            replacement = `~~${selectedText || 'üstü çizili metin'}~~`;
            break;
        case 'heading':
            replacement = `## ${selectedText || 'Başlık'}`;
            break;
        case 'quote':
            replacement = `> ${selectedText || 'Alıntı metni'}`;
            break;
        case 'code':
            replacement = `\`${selectedText || 'kod'}\``;
            break;
        case 'list-ul':
            replacement = `- ${selectedText || 'Liste öğesi'}`;
            break;
        case 'list-ol':
            replacement = `1. ${selectedText || 'Liste öğesi'}`;
            break;
        case 'link':
            const url = prompt('URL girin:', 'https://');
            if (url) {
                replacement = `[${selectedText || 'bağlantı metni'}](${url})`;
            }
            break;
        case 'image':
            const imageUrl = prompt('Resim URL\'sini girin:', 'https://');
            const altText = prompt('Alternatif metin (opsiyonel):', selectedText || 'resim');
            if (imageUrl) {
                replacement = `![${altText}](${imageUrl})`;
            }
            break;
        case 'image-upload':
            openImageUploadDialog();
            return; // Return early, image upload handles insertion separately
    }
    
    if (replacement) {
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        textarea.focus();
        
        // Cursor pozisyonunu ayarla
        const newCursorPos = start + replacement.length;
        textarea.setSelectionRange(newCursorPos, newCursorPos);
        
        // Önizlemeyi güncelle
        updateMarkdownPreview();
    }
}

// Thumbnail preview'ü başlat
function initializeThumbnailPreview() {
    const thumbnailInput = document.getElementById('event_thumbnail');
    const preview = document.getElementById('thumbnailPreview');
    
    if (thumbnailInput && preview) {
        // File input'a click olayını thumbnail preview'e bağla
        preview.addEventListener('click', function() {
            thumbnailInput.click();
        });
    }
}

// Thumbnail değişikliğini işle
function handleThumbnailChange(event) {
    const file = event.target.files[0];
    if (file) {
        // Dosya boyutu kontrolü
        if (file.size > 5 * 1024 * 1024) { // 5MB
            alert('Dosya boyutu en fazla 5MB olabilir.');
            event.target.value = '';
            return;
        }
        
        // Dosya tipi kontrolü
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Sadece JPG, PNG ve GIF dosyaları kabul edilir.');
            event.target.value = '';
            return;
        }
        
        // Önizleme oluştur
        const reader = new FileReader();
        reader.onload = function(e) {
            showThumbnailPreview(e.target.result);
        };
        reader.readAsDataURL(file);
    }
}

// Thumbnail önizlemesini göster
function showThumbnailPreview(imageSrc) {
    const preview = document.getElementById('thumbnailPreview');
    if (preview) {
        preview.innerHTML = `
            <img src="${imageSrc}" alt="Thumbnail önizleme" class="thumbnail-preview-img">
            <div class="thumbnail-overlay">
                <i class="fas fa-edit"></i>
                <span>Değiştirmek için tıklayın</span>
            </div>
        `;
    }
}

// Rol slotlarını başlat
function initializeRoleSlots() {
    // Mevcut rol slotlarının event listener'larını kur
    updateRoleSlotEventListeners();
}

// Rol slot event listener'larını güncelle
function updateRoleSlotEventListeners() {
    // Remove butonları
    const removeButtons = document.querySelectorAll('.remove-role-slot');
    removeButtons.forEach(btn => {
        btn.removeEventListener('click', removeRoleSlot); // Önceki listener'ı kaldır
        btn.addEventListener('click', removeRoleSlot);
    });
    
    // Slot count input'ları
    const slotInputs = document.querySelectorAll('.slot-count-input');
    slotInputs.forEach(input => {
        input.removeEventListener('input', updateTotalParticipants);
        input.addEventListener('input', updateTotalParticipants);
    });
}

// Yeni rol slotu ekle
function addRoleSlot() {
    const container = document.getElementById('roleSlotsContainer');
    const summary = container.querySelector('.role-slots-summary');
    
    if (!container || availableRoles.length === 0) {
        alert('Henüz hiç etkinlik rolü oluşturulmamış. Önce rol oluşturun.');
        return;
    }
    
    const roleSlotHtml = `
        <div class="role-slot" data-index="${roleSlotIndex}">
            <div class="role-slot-content">
                <div class="role-select-group">
                    <label>Rol</label>
                    <select name="role_slots[${roleSlotIndex}][role_id]" class="form-select role-select" required>
                        <option value="">Rol Seçin</option>
                        ${availableRoles.map(role => 
                            `<option value="${role.id}" data-icon="${escapeHtml(role.role_icon || '')}">${escapeHtml(role.role_name)}</option>`
                        ).join('')}
                    </select>
                </div>
                
                <div class="slot-count-group">
                    <label>Slot Sayısı</label>
                    <input type="number" 
                           name="role_slots[${roleSlotIndex}][slot_count]" 
                           value="1"
                           min="1" 
                           max="50" 
                           class="form-input slot-count-input" 
                           required>
                </div>
                
                <button type="button" class="remove-role-slot" title="Rolü Kaldır">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    
    // Summary'den önce ekle
    summary.insertAdjacentHTML('beforebegin', roleSlotHtml);
    
    roleSlotIndex++;
    updateRoleSlotEventListeners();
    updateTotalParticipants();
}

// Rol slotunu kaldır
function removeRoleSlot(event) {
    const roleSlot = event.target.closest('.role-slot');
    if (roleSlot) {
        roleSlot.remove();
        updateTotalParticipants();
    }
}

// Toplam katılımcı sayısını güncelle
function updateTotalParticipants() {
    const slotInputs = document.querySelectorAll('.slot-count-input');
    let total = 0;
    
    slotInputs.forEach(input => {
        const value = parseInt(input.value) || 0;
        total += value;
    });
    
    const totalElement = document.getElementById('totalParticipants');
    if (totalElement) {
        totalElement.textContent = total;
    }
}

// Form validasyonunu başlat
function initializeFormValidation() {
    const form = document.getElementById('eventForm');
    if (form) {
        // Real-time validasyon için input event'lerini dinle
        const requiredInputs = form.querySelectorAll('input[required], textarea[required], select[required]');
        requiredInputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
    }
}

// Tekil alan validasyonu
function validateField(event) {
    const field = event.target;
    const value = field.value.trim();
    
    // Required field kontrolü
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'Bu alan zorunludur.');
        return false;
    }
    
    // Özel validasyonlar
    switch (field.id) {
        case 'event_title':
            if (value.length < 3) {
                showFieldError(field, 'Başlık en az 3 karakter olmalıdır.');
                return false;
            }
            break;
            
        case 'event_description':
            if (value.length < 10) {
                showFieldError(field, 'Açıklama en az 10 karakter olmalıdır.');
                return false;
            }
            break;
            
        case 'event_date':
            const eventDate = new Date(value);
            const now = new Date();
            if (eventDate < now) {
                showFieldError(field, 'Etkinlik tarihi gelecekte olmalıdır.');
                return false;
            }
            break;
    }
    
    clearFieldError(field);
    return true;
}

// Alan hatasını göster
function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

// Alan hatasını temizle
function clearFieldError(field) {
    field.classList.remove('error');
    
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Form submit işlemi
function handleFormSubmit(event) {
    const form = event.target;
    
    // Tüm alanları validate et
    let isValid = true;
    const requiredFields = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    requiredFields.forEach(field => {
        if (!validateField({ target: field })) {
            isValid = false;
        }
    });
    
    // Rol slotları kontrolü
    const roleSlots = form.querySelectorAll('.role-slot');
    if (roleSlots.length === 0) {
        const shouldContinue = confirm('Hiç rol slotu eklenmemiş. Etkinlik katılımcısız olarak oluşturulacak. Devam etmek istiyor musunuz?');
        if (!shouldContinue) {
            event.preventDefault();
            return;
        }
    }
    
    if (!isValid) {
        event.preventDefault();
        alert('Lütfen tüm gerekli alanları doğru şekilde doldurun.');
        
        // İlk hatalı alana scroll et
        const firstError = form.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    } else {
        // Form gönderilmeden önce loading göster
        showFormLoading();
    }
}

// Form loading durumunu göster
function showFormLoading() {
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => {
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.setAttribute('data-original-text', originalText);
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    });
}

// Keyboard shortcuts
function handleKeyboardShortcuts(event) {
    // Sadece textarea focus'undayken çalış
    if (event.target.id !== 'event_description') return;
    
    if (event.ctrlKey || event.metaKey) {
        switch (event.key.toLowerCase()) {
            case 'b':
                event.preventDefault();
                executeMarkdownAction('bold');
                break;
            case 'i':
                event.preventDefault();
                executeMarkdownAction('italic');
                break;
            case 's':
                event.preventDefault();
                document.getElementById('eventForm').submit();
                break;
        }
    }
}

// Utility fonksiyonlar

// HTML escape
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Debounce fonksiyonu
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

// Server'dan gelen verileri JavaScript'e aktar
window.addEventListener('DOMContentLoaded', function() {
    // PHP'den JavaScript'e veri aktarımı için
    const roleData = document.getElementById('roleData');
    if (roleData) {
        try {
            availableRoles = JSON.parse(roleData.textContent);
        } catch (e) {
            console.error('Role data parse error:', e);
            availableRoles = [];
        }
    }
});

// Auto-save fonksiyonu (draft olarak kaydet)
function autoSave() {
    const form = document.getElementById('eventForm');
    if (!form) return;
    
    const formData = new FormData(form);
    formData.set('action', 'draft');
    formData.set('auto_save', '1');
    
    fetch(form.action || window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Auto-save successful');
            // Başarılı auto-save bildirimi (opsiyonel)
        }
    })
    .catch(error => {
        console.error('Auto-save error:', error);
    });
}

// Auto-save'i belirli aralıklarla çalıştır (5 dakika)
setInterval(autoSave, 5 * 60 * 1000);

// Image upload functions
function openImageUploadDialog() {
    // Create hidden file input
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            handleImageUpload(file);
        }
        // Remove the temporary input
        document.body.removeChild(fileInput);
    });
    
    document.body.appendChild(fileInput);
    fileInput.click();
}

function handleImageUpload(file) {
    // Validate file
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!allowedTypes.includes(file.type)) {
        alert('Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.');
        return;
    }
    
    if (file.size > maxSize) {
        alert('Dosya boyutu en fazla 5MB olabilir.');
        return;
    }
    
    // Show loading indicator
    const textarea = document.getElementById('event_description');
    const loadingText = `![Resim yükleniyor...](data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDJWNk00IDEySDE2IiBzdHJva2U9IiM0Q0FGOUIiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+CjxhbmltYXRlVHJhbnNmb3JtIGF0dHJpYnV0ZU5hbWU9InRyYW5zZm9ybSIgdHlwZT0icm90YXRlIiB2YWx1ZXM9IjAgMTIgMTI7MzYwIDEyIDEyIiBkdXI9IjFzIiByZXBlYXRDb3VudD0iaW5kZWZpbml0ZSIvPgo8L3N2Zz4K)`;
    
    // Insert loading placeholder
    insertTextAtCursor(textarea, loadingText);
    updateMarkdownPreview();
    
    // Create FormData for upload
    const formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'upload_image');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    // Upload image
    fetch('actions/upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Replace loading text with actual image
            const imageMarkdown = `![${data.alt_text || 'Yüklenen resim'}](${data.url})`;
            textarea.value = textarea.value.replace(loadingText, imageMarkdown);
            updateMarkdownPreview();
        } else {
            // Remove loading text and show error
            textarea.value = textarea.value.replace(loadingText, '');
            alert('Resim yükleme hatası: ' + (data.message || 'Bilinmeyen hata'));
            updateMarkdownPreview();
        }
    })
    .catch(error => {
        // Remove loading text and show error
        textarea.value = textarea.value.replace(loadingText, '');
        alert('Resim yükleme hatası: ' + error.message);
        updateMarkdownPreview();
    });
}

function insertTextAtCursor(textarea, text) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
    
    // Set cursor position after inserted text
    const newCursorPos = start + text.length;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    textarea.focus();
}