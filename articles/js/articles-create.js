/**
 * Articles Create JavaScript - Clean Version
 * /articles/js/articles-create.js
 */

'use strict';

class ArticleUpload {
    constructor() {
        this.form = document.getElementById('articleForm');
        this.fileInput = document.getElementById('pdf_file');
        this.previewArea = document.getElementById('previewArea');
        this.submitBtn = document.getElementById('submitBtn');
        this.submitText = document.getElementById('submitText');
        this.uploadModal = null;
        
        this.init();
    }

    init() {
        // Initial setup önce çalışsın
        this.handleUploadTypeChange();
        this.handleVisibilityChange();
        
        this.setupEventListeners();
        this.setupFormValidation();
        this.initializeModal();
    }

    initializeModal() {
        this.uploadModal = document.getElementById('uploadModal');
    }

    setupEventListeners() {
        // Dosya seçimi
        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        // Form gönderimi
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // Upload type değişimi
        const uploadTypeRadios = document.querySelectorAll('input[name="upload_type"]');
        uploadTypeRadios.forEach(radio => {
            radio.addEventListener('change', () => this.handleUploadTypeChange());
        });

        // Visibility değişimi
        const visibilitySelect = document.getElementById('visibility');
        if (visibilitySelect) {
            visibilitySelect.addEventListener('change', () => this.handleVisibilityChange());
        }

        // Başlık değişimi
        const titleInput = document.getElementById('title');
        if (titleInput) {
            titleInput.addEventListener('input', this.debounce(() => {
                this.updatePreview();
            }, 300));
        }

        // Açıklama değişimi
        const descriptionInput = document.getElementById('description');
        if (descriptionInput) {
            descriptionInput.addEventListener('input', this.debounce(() => {
                this.updatePreview();
            }, 300));
        }

        // Kategori değişimi
        const categorySelect = document.getElementById('category_id');
        if (categorySelect) {
            categorySelect.addEventListener('change', () => this.updatePreview());
        }

        // Form reset
        const resetBtn = this.form.querySelector('button[type="reset"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                setTimeout(() => {
                    this.resetPreview();
                    this.handleUploadTypeChange();
                    this.handleVisibilityChange();
                }, 100);
            });
        }

        // Modal overlay click to close
        if (this.uploadModal) {
            const overlay = this.uploadModal.querySelector('.modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => this.hideModal());
            }
        }

        // Initial setup (bu kısım setupEventListeners'ın sonundan kaldırıldı çünkü init'te çağırıyoruz)
    }

    setupDropZone() {
        // Mevcut drop zone'u kaldır
        const existingDropZone = document.querySelector('.file-drop-zone');
        if (existingDropZone) {
            existingDropZone.remove();
        }

        // File input'u drag & drop alanına çevir (sadece file_upload modunda)
        const uploadType = document.querySelector('input[name="upload_type"]:checked')?.value;
        
        if (this.fileInput && uploadType === 'file_upload') {
            const dropZone = this.createDropZone();
            this.fileInput.parentNode.insertBefore(dropZone, this.fileInput);
            this.fileInput.style.display = 'none';

            // Drag & Drop olayları
            dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
            dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
            dropZone.addEventListener('drop', this.handleDrop.bind(this));
            dropZone.addEventListener('click', () => this.fileInput.click());
        }
    }

    createDropZone() {
        const dropZone = document.createElement('div');
        dropZone.className = 'file-drop-zone';
        dropZone.innerHTML = `
            <i class="fas fa-cloud-upload-alt"></i>
            <h6>PDF Dosyasını Buraya Sürükleyin</h6>
            <p>veya <strong>tıklayarak seçin</strong></p>
        `;
        return dropZone;
    }

    handleDragOver(e) {
        e.preventDefault();
        e.currentTarget.classList.add('drag-over');
    }

    handleDragLeave(e) {
        e.preventDefault();
        e.currentTarget.classList.remove('drag-over');
    }

    handleDrop(e) {
        e.preventDefault();
        e.currentTarget.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (this.validateFile(file)) {
                // FileList'i simulate et
                const dt = new DataTransfer();
                dt.items.add(file);
                this.fileInput.files = dt.files;
                this.handleFileSelect({ target: { files: dt.files } });
            }
        }
    }

    handleUploadTypeChange() {
        const selectedType = document.querySelector('input[name="upload_type"]:checked')?.value;
        const fileUploadSection = document.getElementById('file-upload-section');
        const googleDocsSection = document.getElementById('google-docs-section');
        const fileInput = document.getElementById('pdf_file');
        const googleDocsInput = document.getElementById('google_docs_url');

        // Önce mevcut drop zone'u kaldır
        const existingDropZone = document.querySelector('.file-drop-zone');
        if (existingDropZone) {
            existingDropZone.remove();
        }

        if (selectedType === 'file_upload') {
            this.showSection(fileUploadSection);
            this.hideSection(googleDocsSection);
            fileInput.required = true;
            googleDocsInput.required = false;
            googleDocsInput.value = '';
            
            // File input'u göster ve drop zone oluştur
            fileInput.style.display = 'block';
            this.setupDropZone();
            
        } else if (selectedType === 'google_docs_link') {
            this.hideSection(fileUploadSection);
            this.showSection(googleDocsSection);
            fileInput.required = false;
            googleDocsInput.required = true;
            fileInput.value = '';
            
            this.resetPreview();
        }
    }

    handleVisibilityChange() {
        const selectedVisibility = document.getElementById('visibility').value;
        const rolesSection = document.getElementById('roles-section');
        const roleCheckboxes = document.querySelectorAll('input[name="selected_roles[]"]');

        if (selectedVisibility === 'specific_roles') {
            this.showSection(rolesSection);
            // Role validation event listener'ı ekle
            roleCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', this.validateRoleSelection.bind(this));
            });
        } else {
            this.hideSection(rolesSection);
            // Rol seçimini temizle
            roleCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }
    }

    showSection(element) {
        if (element) {
            element.style.display = 'flex';
        }
    }

    hideSection(element) {
        if (element) {
            element.style.display = 'none';
        }
    }

    validateFile(file) {
        // MIME type kontrolü
        if (file.type !== 'application/pdf') {
            this.showAlert('Sadece PDF dosyaları kabul edilir.', 'error');
            return false;
        }

        // Boyut kontrolü (10MB)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            this.showAlert('Dosya boyutu 10MB\'dan büyük olamaz.', 'error');
            return false;
        }

        return true;
    }

    handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            const file = files[0];
            if (this.validateFile(file)) {
                this.showFilePreview(file);
                this.updateDropZone(file);
            }
        }
    }

    showFilePreview(file) {
        const fileSize = this.formatFileSize(file.size);
        const fileName = file.name;
        
        this.previewArea.innerHTML = `
            <div class="preview-item fade-in">
                <div class="preview-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="preview-details">
                    <h6>${this.escapeHtml(fileName)}</h6>
                    <small>PDF Dosyası • ${fileSize}</small>
                </div>
                <div class="preview-size">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        `;
    }

    updateDropZone(file) {
        const dropZone = this.form.querySelector('.file-drop-zone');
        if (dropZone) {
            dropZone.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <h6>Dosya Seçildi</h6>
                <p>${this.escapeHtml(file.name)}</p>
                <small>${this.formatFileSize(file.size)}</small>
            `;
            dropZone.classList.add('border-success');
        }
    }

    resetPreview() {
        this.previewArea.innerHTML = `
            <div class="preview-empty">
                <i class="fas fa-file-pdf"></i>
                <p>PDF seçildiğinde önizleme burada görünecek</p>
            </div>
        `;

        // Drop zone'u reset et
        const dropZone = this.form.querySelector('.file-drop-zone');
        if (dropZone) {
            dropZone.innerHTML = `
                <i class="fas fa-cloud-upload-alt"></i>
                <h6>PDF Dosyasını Buraya Sürükleyin</h6>
                <p>veya <strong>tıklayarak seçin</strong></p>
            `;
            dropZone.classList.remove('border-success');
        }
    }

    updatePreview() {
        const title = document.getElementById('title').value;
        const description = document.getElementById('description').value;
        
        if (title.length > 0 || description.length > 0) {
            const existingPreview = this.previewArea.querySelector('.preview-item');
            if (existingPreview) {
                const details = existingPreview.querySelector('.preview-details h6');
                if (details && title) {
                    details.textContent = title;
                }
            }
        }
    }

    setupFormValidation() {
        const requiredFields = this.form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            field.addEventListener('blur', () => this.validateField(field));
            field.addEventListener('input', () => this.clearFieldError(field));
        });
    }

    validateRoleSelection() {
        const selectedRoles = document.querySelectorAll('input[name="selected_roles[]"]:checked');
        const rolesSection = document.getElementById('roles-section');
        
        // Hata mesajını kaldır
        const existingError = rolesSection.querySelector('.role-error');
        if (existingError) {
            existingError.remove();
        }

        if (document.getElementById('visibility').value === 'specific_roles' && selectedRoles.length === 0) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'role-error invalid-feedback';
            errorDiv.textContent = 'En az bir rol seçmelisiniz.';
            rolesSection.appendChild(errorDiv);
            return false;
        }
        
        return true;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'Bu alan zorunludur.';
        }

        // Upload type'a göre dosya/URL validasyonu
        const uploadType = document.querySelector('input[name="upload_type"]:checked')?.value;
        
        if (field.type === 'file' && uploadType === 'file_upload' && field.files.length === 0) {
            isValid = false;
            errorMessage = 'Lütfen bir PDF dosyası seçin.';
        }

        if (field.name === 'google_docs_url' && uploadType === 'google_docs_link') {
            if (!value) {
                isValid = false;
                errorMessage = 'Google Docs linki boş bırakılamaz.';
            } else if (!this.isValidURL(value)) {
                isValid = false;
                errorMessage = 'Geçerli bir URL formatı girin.';
            } else if (!this.isGoogleDocsURL(value)) {
                isValid = false;
                errorMessage = 'Sadece Google Docs veya Google Drive linkleri kabul edilir.';
            }
        }

        if (field.name === 'title' && value.length > 255) {
            isValid = false;
            errorMessage = 'Başlık 255 karakterden uzun olamaz.';
        }

        // Visibility specific roles kontrolü
        if (field.name === 'visibility' && value === 'specific_roles') {
            if (!this.validateRoleSelection()) {
                isValid = false;
                errorMessage = 'En az bir rol seçmelisiniz.';
            }
        }

        this.setFieldValidation(field, isValid, errorMessage);
        return isValid;
    }

    setFieldValidation(field, isValid, errorMessage) {
        field.classList.remove('is-valid', 'is-invalid');
        
        // Önceki hata mesajını kaldır
        const existingError = field.parentNode.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }

        if (!isValid) {
            field.classList.add('is-invalid');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = errorMessage;
            field.parentNode.appendChild(errorDiv);
        } else if (field.value.trim()) {
            field.classList.add('is-valid');
        }
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    handleFormSubmit(e) {
        e.preventDefault();
        
        // Upload type kontrolü
        const uploadType = document.querySelector('input[name="upload_type"]:checked')?.value;
        if (!uploadType) {
            this.showAlert('Lütfen yükleme türü seçin.', 'error');
            return;
        }

        // Visibility specific roles kontrolü
        const visibility = document.getElementById('visibility').value;
        if (visibility === 'specific_roles') {
            const selectedRoles = document.querySelectorAll('input[name="selected_roles[]"]:checked');
            if (selectedRoles.length === 0) {
                this.showAlert('Belirli rollere özel seçeneği için en az bir rol seçmelisiniz.', 'error');
                return;
            }
        }

        // Tüm alanları validasyondan geçir
        const requiredFields = this.form.querySelectorAll('[required]');
        let isFormValid = true;

        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isFormValid = false;
            }
        });

        // Upload type'a göre özel validasyon
        if (uploadType === 'file_upload') {
            const fileInput = document.getElementById('pdf_file');
            if (fileInput.files.length === 0) {
                this.showAlert('Lütfen bir PDF dosyası seçin.', 'error');
                isFormValid = false;
            }
        } else if (uploadType === 'google_docs_link') {
            const googleDocsInput = document.getElementById('google_docs_url');
            const url = googleDocsInput.value.trim();
            
            if (!url) {
                this.showAlert('Lütfen Google Docs linki girin.', 'error');
                isFormValid = false;
            } else if (!this.isValidURL(url)) {
                this.showAlert('Geçerli bir URL formatı girin.', 'error');
                isFormValid = false;
            } else if (!this.isGoogleDocsURL(url)) {
                this.showAlert('Sadece Google Docs veya Google Drive linkleri kabul edilir.', 'error');
                isFormValid = false;
            }
        }

        if (!isFormValid) {
            this.showAlert('Lütfen tüm zorunlu alanları doğru şekilde doldurun.', 'error');
            return;
        }

        this.startUpload();
    }

    startUpload() {
        // Butonu disable et ve loading state'e geçir
        this.submitBtn.disabled = true;
        this.submitBtn.innerHTML = `
            <i class="fas fa-spinner fa-spin"></i>
            <span>Yükleniyor...</span>
        `;

        // Upload modalını göster
        this.showModal();

        // Form data hazırla
        const formData = new FormData(this.form);

        // XMLHttpRequest ile upload progress tracking
        const xhr = new XMLHttpRequest();

        // Progress tracking
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                this.updateProgress(percentComplete);
            }
        });

        // Upload tamamlandığında
        xhr.addEventListener('load', () => {
            this.handleUploadComplete(xhr);
        });

        // Hata durumunda
        xhr.addEventListener('error', () => {
            this.handleUploadError('Ağ hatası oluştu. Lütfen tekrar deneyin.');
        });

        // Upload timeout
        xhr.timeout = 60000; // 60 saniye
        xhr.addEventListener('timeout', () => {
            this.handleUploadError('Upload timeout. Dosya çok büyük olabilir.');
        });

        // Request gönder
        xhr.open('POST', this.form.action || window.location.href);
        xhr.send(formData);
    }

    updateProgress(percent) {
        const progressFill = document.getElementById('uploadProgress');
        const progressText = document.getElementById('progressText');
        
        if (progressFill) {
            progressFill.style.width = percent + '%';
        }
        
        if (progressText) {
            progressText.textContent = percent + '%';
        }
    }

    handleUploadComplete(xhr) {
        if (xhr.status === 200) {
            const response = xhr.responseText;
            
            this.hideModal();
            
            if (response.includes('alert-success')) {
                this.showAlert('Makale başarıyla yüklendi!', 'success');
                
                setTimeout(() => {
                    window.location.href = '/articles/';
                }, 2000);
            } else {
                // Sayfayı yenile
                document.body.innerHTML = response;
                new ArticleUpload();
            }
        } else {
            this.handleUploadError(`HTTP ${xhr.status}: ${xhr.statusText}`);
        }
    }

    handleUploadError(errorMessage) {
        this.hideModal();
        this.resetSubmitButton();
        this.showAlert(errorMessage, 'error');
    }

    resetSubmitButton() {
        this.submitBtn.disabled = false;
        this.submitBtn.innerHTML = `
            <i class="fas fa-upload"></i>
            <span>Makale Yükle</span>
        `;
    }

    showModal() {
        if (this.uploadModal) {
            this.uploadModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    hideModal() {
        if (this.uploadModal) {
            this.uploadModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    showAlert(message, type = 'info') {
        // Mevcut alert'leri kaldır
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => {
            if (!alert.classList.contains('alert-permanent')) {
                alert.remove();
            }
        });

        // Yeni alert oluştur
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        
        const iconMap = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        };
        
        const icon = iconMap[type] || iconMap.info;
        
        alertDiv.innerHTML = `
            <i class="${icon}"></i>
            ${this.escapeHtml(message)}
        `;

        // Page header'dan sonra ekle
        const pageHeader = document.querySelector('.page-header');
        if (pageHeader) {
            pageHeader.parentNode.insertBefore(alertDiv, pageHeader.nextSibling);
        } else {
            // Fallback: site-container başına ekle
            const siteContainer = document.querySelector('.site-container');
            if (siteContainer) {
                siteContainer.insertBefore(alertDiv, siteContainer.firstChild);
            }
        }

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);

        // Alert'e scroll
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    isValidURL(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    isGoogleDocsURL(url) {
        return /^https:\/\/(docs|drive)\.google\.com\//.test(url);
    }

    debounce(func, wait) {
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

    // Utility: Form verilerini local storage'a kaydet (taslak)
    saveDraft() {
        const formData = new FormData(this.form);
        const data = {};
        
        for (const [key, value] of formData.entries()) {
            if (key !== 'pdf_file' && key !== 'csrf_token') {
                data[key] = value;
            }
        }
        
        localStorage.setItem('article_draft', JSON.stringify(data));
        this.showAlert('Taslak kaydedildi.', 'info');
    }

    // Utility: Taslağı yükle
    loadDraft() {
        const draft = localStorage.getItem('article_draft');
        if (draft) {
            try {
                const data = JSON.parse(draft);
                
                Object.keys(data).forEach(key => {
                    const input = this.form.querySelector(`[name="${key}"]`);
                    if (input) {
                        input.value = data[key];
                    }
                });
                
                this.showAlert('Taslak yüklendi.', 'info');
            } catch (e) {
                console.error('Draft load error:', e);
            }
        }
    }

    // Utility: Taslağı temizle
    clearDraft() {
        localStorage.removeItem('article_draft');
    }
}

// DOM hazır olduğunda initialize et
document.addEventListener('DOMContentLoaded', function() {
    const articleUpload = new ArticleUpload();
    
    // Auto-save draft every 30 seconds
    setInterval(() => {
        const title = document.getElementById('title');
        if (title && title.value.trim().length > 0) {
            articleUpload.saveDraft();
        }
    }, 30000);
    
    // Page unload'da draft kaydet
    window.addEventListener('beforeunload', () => {
        const title = document.getElementById('title');
        if (title && title.value.trim().length > 0) {
            articleUpload.saveDraft();
        }
    });
    
    // Ctrl+S ile draft kaydet
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            articleUpload.saveDraft();
        }
    });

    // ESC tuşu ile modal kapat
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('uploadModal');
            if (modal && modal.style.display === 'flex') {
                articleUpload.hideModal();
            }
        }
    });
});

// Global functions (external access için)
window.ArticleUpload = ArticleUpload;