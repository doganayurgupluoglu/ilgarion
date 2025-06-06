// /gallery/js/gallery-upload.js - Upload Module

const GalleryUpload = {
    init() {
        this.setupUploadArea();
        this.setupUploadForm();
    },

    setupUploadArea() {
        const uploadArea = document.getElementById('uploadArea');
        const photoFile = document.getElementById('photoFile');
        
        if (!uploadArea || !photoFile) return;
        
        // Click to upload
        uploadArea.addEventListener('click', () => {
            photoFile.click();
        });
        
        // File input change
        photoFile.addEventListener('change', this.handleFileSelect.bind(this));
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                photoFile.files = files;
                this.handleFileSelect({ target: { files } });
            }
        });
    },

    handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        // Dosya boyutu kontrolü
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            GalleryUtils.showNotification('Dosya boyutu çok büyük. Maksimum 10MB olabilir.', 'error');
            return;
        }
        
        // Dosya tipi kontrolü
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        if (!allowedTypes.includes(file.type)) {
            GalleryUtils.showNotification('Desteklenmeyen dosya formatı. Sadece JPG, PNG, GIF, WEBP ve BMP dosyaları yükleyebilirsiniz.', 'error');
            return;
        }
        
        // Önizleme göster
        const reader = new FileReader();
        
        reader.onload = (e) => {
            try {
                this.showPhotoPreview(e.target.result);
            } catch (error) {
                console.error('Preview generation error:', error);
                GalleryUtils.showNotification('Önizleme oluşturulamadı, ancak dosya yine de yüklenebilir.', 'warning');
            }
        };
        
        reader.onerror = () => {
            console.error('FileReader error');
            GalleryUtils.showNotification('Dosya okuma hatası. Lütfen tekrar deneyin.', 'error');
        };
        
        reader.readAsDataURL(file);
    },

    showPhotoPreview(imageSrc) {
        const uploadArea = document.getElementById('uploadArea');
        const photoPreview = document.getElementById('photoPreview');
        const previewImage = document.getElementById('previewImage');
        
        if (uploadArea && photoPreview && previewImage) {
            uploadArea.style.display = 'none';
            
            // Error handler'ı önce ayarla
            previewImage.onerror = () => {
                console.error('Preview image failed to load');
                // Geri fallback yap
                uploadArea.style.display = 'block';
                photoPreview.style.display = 'none';
                GalleryUtils.showNotification('Önizleme yüklenemedi, ancak dosya yine de yüklenebilir.', 'warning');
            };
            
            previewImage.onload = () => {
                photoPreview.style.display = 'block';
            };
            
            // Src'yi en son ayarla
            previewImage.src = imageSrc;
        }
    },

    removePhotoPreview() {
        const uploadArea = document.getElementById('uploadArea');
        const photoPreview = document.getElementById('photoPreview');
        const photoFile = document.getElementById('photoFile');
        
        if (uploadArea && photoPreview && photoFile) {
            photoPreview.style.display = 'none';
            uploadArea.style.display = 'block';
            photoFile.value = '';
        }
    },

    openModal() {
        const modal = document.getElementById('uploadModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Form'u sıfırla
            document.getElementById('uploadForm').reset();
            this.removePhotoPreview();
            this.hideUploadProgress();
        }
    },

    closeModal() {
        const modal = document.getElementById('uploadModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },

    showUploadProgress() {
        const progress = document.getElementById('uploadProgress');
        if (progress) {
            progress.style.display = 'block';
        }
    },

    hideUploadProgress() {
        const progress = document.getElementById('uploadProgress');
        if (progress) {
            progress.style.display = 'none';
        }
    },

    updateUploadProgress(percent) {
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        if (progressFill && progressText) {
            progressFill.style.width = percent + '%';
            progressText.textContent = `Yükleniyor... ${percent}%`;
        }
    },

    setupUploadForm() {
        const uploadForm = document.getElementById('uploadForm');
        
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const photoFile = document.getElementById('photoFile');
                if (!photoFile.files[0]) {
                    GalleryUtils.showNotification('Lütfen bir fotoğraf seçin.', 'error');
                    return;
                }
                
                const submitBtn = document.getElementById('uploadSubmitBtn');
                const originalText = submitBtn.innerHTML;
                
                // Button'u devre dışı bırak
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
                
                this.showUploadProgress();
                
                try {
                    const formData = new FormData(uploadForm);
                    
                    const xhr = new XMLHttpRequest();
                    
                    // Progress tracking
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            this.updateUploadProgress(percent);
                        }
                    });
                    
                    xhr.onload = () => {
                        try {
                            if (xhr.status !== 200) {
                                throw new Error(`HTTP ${xhr.status}: ${xhr.statusText}`);
                            }
                            
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                GalleryUtils.showNotification(response.message, 'success');
                                this.closeModal();
                                
                                // Sayfayı yenile (yeni fotoğrafı göstermek için)
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                GalleryUtils.showNotification(response.message || 'Yükleme başarısız oldu.', 'error');
                            }
                        } catch (error) {
                            console.error('Upload response parse error:', error);
                            console.error('Response text:', xhr.responseText);
                            GalleryUtils.showNotification('Yükleme sırasında bir hata oluştu: ' + error.message, 'error');
                        }
                    };
                    
                    xhr.onerror = () => {
                        GalleryUtils.showNotification('Yükleme sırasında bir ağ hatası oluştu.', 'error');
                    };
                    
                    xhr.open('POST', 'actions/upload_photo.php');
                    xhr.send(formData);
                    
                } catch (error) {
                    console.error('Upload error:', error);
                    GalleryUtils.showNotification('Yükleme sırasında bir hata oluştu: ' + error.message, 'error');
                } finally {
                    // Button'u tekrar aktif et
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    this.hideUploadProgress();
                }
            });
        }
    }
};

// Global functions for backward compatibility
window.removePhotoPreview = () => GalleryUpload.removePhotoPreview();