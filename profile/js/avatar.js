// js/avatar.js - Avatar Yönetimi JavaScript (Gravatar'sız)

document.addEventListener('DOMContentLoaded', function() {
    initializeAvatarUpload();
});

// Avatar yükleme sistemini başlat
function initializeAvatarUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('avatarFile');
    const filePreview = document.getElementById('filePreview');
    const previewImage = document.getElementById('previewImage');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadBtn = document.getElementById('uploadBtn');

    if (!uploadArea || !fileInput) return;

    // Drag and drop olayları
    uploadArea.addEventListener('dragover', handleDragOver);
    uploadArea.addEventListener('dragleave', handleDragLeave);
    uploadArea.addEventListener('drop', handleDrop);
    
    // Dosya seçimi
    fileInput.addEventListener('change', handleFileSelect);
    
    // Click to upload
    uploadArea.addEventListener('click', () => fileInput.click());
}

// Drag over işlemi
function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.add('dragover');
}

// Drag leave işlemi
function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('dragover');
}

// Drop işlemi
function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('avatarFile');
        fileInput.files = files;
        handleFileSelect({ target: { files: files } });
    }
}

// Dosya seçimi işlemi
function handleFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Dosya türü kontrolü
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showAlert('Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.', 'error');
        clearFileSelection();
        return;
    }
    
    // Dosya boyutu kontrolü (2MB)
    if (file.size > 2 * 1024 * 1024) {
        showAlert('Dosya boyutu çok büyük. Maksimum 2MB olmalıdır.', 'error');
        clearFileSelection();
        return;
    }
    
    // Dosya önizlemesi
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            // Boyut kontrolü
            if (this.width < 100 || this.height < 100) {
                showAlert('Resim boyutu çok küçük. Minimum 100x100 piksel olmalıdır.', 'error');
                clearFileSelection();
                return;
            }
            
            if (this.width > 2000 || this.height > 2000) {
                showAlert('Resim boyutu çok büyük. Maksimum 2000x2000 piksel olmalıdır.', 'error');
                clearFileSelection();
                return;
            }
            
            // Önizlemeyi göster
            showFilePreview(e.target.result, file);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// Dosya önizlemesini göster
function showFilePreview(imageSrc, file) {
    const filePreview = document.getElementById('filePreview');
    const previewImage = document.getElementById('previewImage');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadBtn = document.getElementById('uploadBtn');
    
    previewImage.src = imageSrc;
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    filePreview.style.display = 'flex';
    uploadBtn.disabled = false;
    
    // Upload area'yı gizle
    document.getElementById('uploadArea').style.display = 'none';
}

// Dosya seçimini temizle
function clearFileSelection() {
    const fileInput = document.getElementById('avatarFile');
    const filePreview = document.getElementById('filePreview');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadArea = document.getElementById('uploadArea');
    
    fileInput.value = '';
    filePreview.style.display = 'none';
    uploadBtn.disabled = true;
    uploadArea.style.display = 'block';
}

// Dosya boyutunu formatla
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Alert gösterme fonksiyonu
function showAlert(message, type = 'info') {
    // Mevcut alert'leri temizle
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Yeni alert oluştur
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-exclamation-triangle' : 
                 'fa-info-circle';
    
    alertDiv.innerHTML = `
        <i class="fas ${icon}"></i>
        ${message}
    `;
    
    // Alert'i sayfaya ekle
    const container = document.querySelector('.profile-main-content');
    const firstSection = container.querySelector('.avatar-header').nextElementSibling;
    container.insertBefore(alertDiv, firstSection);
    
    // 5 saniye sonra otomatik olarak kaldır
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
    
    // Alert'e tıklandığında kaldır
    alertDiv.addEventListener('click', () => {
        alertDiv.remove();
    });
    
    // Sayfayı alert'e kaydır
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

// Form submit olaylarını yakalama
document.addEventListener('submit', function(e) {
    const form = e.target;
    
    // Avatar upload formu
    if (form.classList.contains('avatar-upload-form')) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
    }
});

// Sayfa yüklendiğinde kontroller
window.addEventListener('load', function() {
    console.log('Avatar sayfası yüklendi');
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + U = Upload area'ya focus
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        const uploadArea = document.getElementById('uploadArea');
        if (uploadArea && uploadArea.style.display !== 'none') {
            uploadArea.click();
        }
    }
    
    // ESC = File selection'ı temizle
    if (e.key === 'Escape') {
        const filePreview = document.getElementById('filePreview');
        if (filePreview && filePreview.style.display !== 'none') {
            clearFileSelection();
        }
    }
});

// Paste ile resim yükleme
document.addEventListener('paste', function(e) {
    const uploadArea = document.getElementById('uploadArea');
    
    // Sadece upload area görünürken paste'i kabul et
    if (!uploadArea || uploadArea.style.display === 'none') {
        return;
    }
    
    const items = e.clipboardData.items;
    
    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        
        if (item.type.indexOf('image') !== -1) {
            e.preventDefault();
            
            const file = item.getAsFile();
            const fileInput = document.getElementById('avatarFile');
            
            // File input'a atama (modern tarayıcılarda çalışır)
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            // File select event'ini tetikle
            handleFileSelect({ target: { files: [file] } });
            break;
        }
    }
});

// Performance optimizasyonu için lazy loading
if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
}

// Image validation helper
function validateImageFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const validation = {
                    isValid: true,
                    width: this.width,
                    height: this.height,
                    aspectRatio: this.width / this.height,
                    errors: []
                };
                
                // Boyut kontrolleri
                if (this.width < 100 || this.height < 100) {
                    validation.isValid = false;
                    validation.errors.push('Minimum boyut: 100x100 piksel');
                }
                
                if (this.width > 2000 || this.height > 2000) {
                    validation.isValid = false;
                    validation.errors.push('Maksimum boyut: 2000x2000 piksel');
                }
                
                // Aşırı uzun/geniş kontrol
                if (validation.aspectRatio > 3 || validation.aspectRatio < 0.33) {
                    validation.errors.push('Görüntü çok uzun veya çok geniş. Kare formatı önerilir.');
                }
                
                resolve(validation);
            };
            img.onerror = () => reject('Geçersiz resim dosyası');
            img.src = e.target.result;
        };
        reader.onerror = () => reject('Dosya okunamadı');
        reader.readAsDataURL(file);
    });
}

// File drop visual feedback
function enhanceDropZone() {
    const uploadArea = document.getElementById('uploadArea');
    if (!uploadArea) return;
    
    let dragCounter = 0;
    
    uploadArea.addEventListener('dragenter', function(e) {
        e.preventDefault();
        dragCounter++;
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        dragCounter--;
        if (dragCounter === 0) {
            this.classList.remove('dragover');
        }
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        dragCounter = 0;
        this.classList.remove('dragover');
    });
}

// Initialize enhanced features
document.addEventListener('DOMContentLoaded', function() {
    enhanceDropZone();
    
    // File size progress için future extension
    window.avatarUploadProgress = function(percent) {
        console.log(`Upload progress: ${percent}%`);
        // Progress bar implementation buraya eklenebilir
    };
});

// Error handling ve user feedback
function handleUploadError(error) {
    console.error('Upload error:', error);
    
    let userMessage = 'Dosya yüklenirken bir hata oluştu.';
    
    if (error.includes('size')) {
        userMessage = 'Dosya boyutu çok büyük.';
    } else if (error.includes('type')) {
        userMessage = 'Desteklenmeyen dosya formatı.';
    } else if (error.includes('network')) {
        userMessage = 'Bağlantı hatası. Lütfen tekrar deneyin.';
    }
    
    showAlert(userMessage, 'error');
    clearFileSelection();
}

// Success callback
function handleUploadSuccess(response) {
    showAlert('Avatar başarıyla güncellendi!', 'success');
    
    // Sayfayı yenile (avatar güncellemesi için)
    setTimeout(() => {
        window.location.reload();
    }, 2000);
}