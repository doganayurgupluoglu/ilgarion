// security.js - Güvenlik sayfası JavaScript fonksiyonları

document.addEventListener('DOMContentLoaded', function() {
    initPasswordValidation();
    initEmailValidation();
    initSecurityTooltips();
    initSessionWarning();
    initFormSecurity();
});

/**
 * Şifre validasyonu
 */
function initPasswordValidation() {
    const passwordForm = document.getElementById('passwordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (!passwordForm || !newPassword || !confirmPassword) return;
    
    function validatePassword() {
        const password = newPassword.value;
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/;
        
        if (password.length < 8) {
            newPassword.setCustomValidity('Şifre en az 8 karakter olmalıdır.');
        } else if (!regex.test(password)) {
            newPassword.setCustomValidity('Şifre büyük harf, küçük harf, rakam ve özel karakter içermelidir.');
        } else {
            newPassword.setCustomValidity('');
        }
        
        // Görsel feedback
        updatePasswordStrength(password);
    }
    
    function validatePasswordMatch() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Şifreler eşleşmiyor.');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    function updatePasswordStrength(password) {
        // Şifre gücü kontrolü (isteğe bağlı)
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[@$!%*?&]/.test(password)) strength++;
        
        // Görsel feedback eklenebilir
        newPassword.classList.remove('weak', 'medium', 'strong');
        if (strength >= 4) {
            newPassword.classList.add('strong');
        } else if (strength >= 3) {
            newPassword.classList.add('medium');
        } else if (password.length > 0) {
            newPassword.classList.add('weak');
        }
    }
    
    newPassword.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('input', validatePasswordMatch);
    
    passwordForm.addEventListener('submit', function(e) {
        validatePassword();
        validatePasswordMatch();
        
        if (!passwordForm.checkValidity()) {
            e.preventDefault();
            showFormError('Lütfen tüm alanları doğru şekilde doldurun.');
        } else {
            showLoadingState(passwordForm);
        }
    });
}

/**
 * E-posta validasyonu
 */
function initEmailValidation() {
    const emailForm = document.getElementById('emailForm');
    const newEmail = document.getElementById('new_email');
    
    if (!emailForm || !newEmail) return;
    
    emailForm.addEventListener('submit', function(e) {
        const currentEmail = emailForm.dataset.currentEmail || '';
        
        if (newEmail.value === currentEmail) {
            e.preventDefault();
            showFormError('Yeni e-posta adresi mevcut adresinizle aynı olamaz.');
            return;
        }
        
        if (!confirm('E-posta adresinizi değiştirmek istediğinize emin misiniz?\n\nYeni adresinize doğrulama e-postası gönderilecektir.')) {
            e.preventDefault();
            return;
        }
        
        showLoadingState(emailForm);
    });
    
    // E-posta format kontrolü
    newEmail.addEventListener('blur', function() {
        if (newEmail.value && !isValidEmail(newEmail.value)) {
            newEmail.setCustomValidity('Geçerli bir e-posta adresi giriniz.');
        } else {
            newEmail.setCustomValidity('');
        }
    });
}

/**
 * Güvenlik ipuçları tooltip'leri
 */
function initSecurityTooltips() {
    const tooltips = {
        'new_password': 'Güçlü bir şifre seçin: En az 8 karakter, büyük/küçük harf, rakam ve özel karakterler kullanın.',
        'new_email': 'Yeni e-posta adresinize doğrulama e-postası gönderilecektir.',
        'password_confirm': 'Güvenlik nedeniyle mevcut şifrenizi doğrulamamız gerekiyor.',
        'current_password': 'Değişiklikleri onaylamak için mevcut şifrenizi girin.'
    };
    
    Object.keys(tooltips).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.setAttribute('title', tooltips[id]);
            element.setAttribute('data-tooltip', tooltips[id]);
        }
    });
}

/**
 * Session timeout uyarısı
 */
function initSessionWarning() {
    let sessionWarned = false;
    const SESSION_TIMEOUT = 14400000; // 4 saat (milisaniye)
    const WARNING_TIME = 13800000; // 3 saat 50 dakika
    
    setTimeout(function() {
        if (!sessionWarned) {
            sessionWarned = true;
            showSessionWarning();
        }
    }, WARNING_TIME);
}

/**
 * Form güvenliği
 */
function initFormSecurity() {
    // Otomatik form temizleme (güvenlik için)
    window.addEventListener('beforeunload', function() {
        clearSensitiveFields();
    });
    
    // Sayfa gizlendiğinde şifre alanlarını temizle
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearSensitiveFields();
        }
    });
    
    // Form submit sonrası şifre alanlarını temizle
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            setTimeout(clearSensitiveFields, 1000);
        });
    });
}

/**
 * Yardımcı fonksiyonlar
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function showFormError(message) {
    // Mevcut error alert'leri temizle
    const existingAlerts = document.querySelectorAll('.alert-error.dynamic');
    existingAlerts.forEach(alert => alert.remove());
    
    // Yeni error alert oluştur
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-error dynamic';
    alertDiv.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i>
        ${message}
    `;
    
    // İlk güvenlik kartından önce ekle
    const firstCard = document.querySelector('.security-card');
    if (firstCard) {
        firstCard.parentNode.insertBefore(alertDiv, firstCard);
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // 5 saniye sonra otomatik kaldır
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function showLoadingState(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        // Original text'i sakla
        if (!submitBtn.dataset.originalText) {
            submitBtn.dataset.originalText = submitBtn.textContent;
        }
        submitBtn.textContent = 'İşleniyor...';
    }
    
    // Form alanlarını devre dışı bırak
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.disabled = true;
    });
}

function clearSensitiveFields() {
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.value = '';
    });
}

function showSessionWarning() {
    const modal = createSessionModal();
    document.body.appendChild(modal);
    
    // Modal'ı göster
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function createSessionModal() {
    const modal = document.createElement('div');
    modal.className = 'session-modal-overlay';
    modal.innerHTML = `
        <div class="session-modal">
            <div class="session-modal-header">
                <i class="fas fa-clock"></i>
                <h3>Oturum Uyarısı</h3>
            </div>
            <div class="session-modal-body">
                <p>Oturumunuz yakında sona erecek. Sayfayı yenileyerek oturumunuzu uzatmak ister misiniz?</p>
            </div>
            <div class="session-modal-footer">
                <button onclick="extendSession()" class="btn btn-primary">
                    <i class="fas fa-refresh"></i> Oturumu Uzat
                </button>
                <button onclick="closeSessionModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Kapat
                </button>
            </div>
        </div>
    `;
    
    return modal;
}

function extendSession() {
    window.location.reload();
}

function closeSessionModal() {
    const modal = document.querySelector('.session-modal-overlay');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Global fonksiyonlar (modal için)
window.extendSession = extendSession;
window.closeSessionModal = closeSessionModal;

/**
 * CSRF token yenileme (isteğe bağlı)
 */
function refreshCSRFToken() {
    fetch('/api/get-csrf-token.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.token) {
                // Tüm formlardaki CSRF token'ları güncelle
                document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                    input.value = data.token;
                });
            }
        })
        .catch(error => {
            console.warn('CSRF token yenilenemedi:', error);
        });
}

/**
 * Güvenlik seviyesi kontrolü
 */
function checkSecurityLevel() {
    const passwordField = document.getElementById('new_password');
    if (!passwordField) return;
    
    const password = passwordField.value;
    let score = 0;
    let feedback = [];
    
    // Uzunluk kontrolü
    if (password.length >= 8) score += 20;
    else feedback.push('En az 8 karakter');
    
    if (password.length >= 12) score += 10;
    
    // Karakter çeşitliliği
    if (/[a-z]/.test(password)) score += 15;
    else feedback.push('Küçük harf');
    
    if (/[A-Z]/.test(password)) score += 15;
    else feedback.push('Büyük harf');
    
    if (/\d/.test(password)) score += 15;
    else feedback.push('Rakam');
    
    if (/[@$!%*?&]/.test(password)) score += 15;
    else feedback.push('Özel karakter');
    
    // Tekrar kontrolü
    if (!/(.)\1{2,}/.test(password)) score += 10;
    else feedback.push('Tekrarlayan karakter yok');
    
    return {
        score: score,
        level: score >= 80 ? 'strong' : score >= 60 ? 'medium' : 'weak',
        feedback: feedback
    };
}

/**
 * Real-time güvenlik feedback'i
 */
function updateSecurityFeedback() {
    const passwordField = document.getElementById('new_password');
    const feedbackElement = document.getElementById('password-feedback');
    
    if (!passwordField || !feedbackElement) return;
    
    const security = checkSecurityLevel();
    
    feedbackElement.className = `security-feedback ${security.level}`;
    feedbackElement.innerHTML = `
        <div class="security-bar">
            <div class="security-fill" style="width: ${security.score}%"></div>
        </div>
        <div class="security-text">
            Güvenlik seviyesi: ${security.level === 'strong' ? 'Güçlü' : security.level === 'medium' ? 'Orta' : 'Zayıf'}
        </div>
        ${security.feedback.length > 0 ? `<div class="security-suggestions">Eksik: ${security.feedback.join(', ')}</div>` : ''}
    `;
}

/**
 * Keyboard shortcuts
 */
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+S ile form kaydetme
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const activeForm = document.querySelector('form:focus-within');
            if (activeForm) {
                activeForm.submit();
            }
        }
        
        // Escape ile modal kapatma
        if (e.key === 'Escape') {
            closeSessionModal();
        }
    });
}

/**
 * Activity log filtreleme
 */
function initActivityFilters() {
    const filterButtons = document.querySelectorAll('.activity-filter');
    const activityItems = document.querySelectorAll('.activity-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // Aktif buton stilini güncelle
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Aktiviteleri filtrele
            activityItems.forEach(item => {
                const action = item.dataset.action;
                if (filter === 'all' || action === filter) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Copy to clipboard fonksiyonu
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Panoya kopyalandı!', 'success');
        }).catch(() => {
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '-1000px';
    textArea.style.left = '-1000px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast('Panoya kopyalandı!', 'success');
    } catch (err) {
        showToast('Kopyalama başarısız!', 'error');
    }
    
    document.body.removeChild(textArea);
}

/**
 * Toast bildirimleri
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Animasyon
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Otomatik kaldırma
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Form auto-save (draft)
 */
function initAutoSave() {
    const forms = document.querySelectorAll('form[data-autosave]');
    
    forms.forEach(form => {
        const formId = form.id || 'security-form';
        
        // Kayıtlı taslağı yükle
        loadDraft(form, formId);
        
        // Input değişikliklerini izle
        form.addEventListener('input', debounce(() => {
            saveDraft(form, formId);
        }, 1000));
    });
}

function saveDraft(form, formId) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        // Şifre alanlarını kaydetme
        if (key.includes('password')) continue;
        data[key] = value;
    }
    
    try {
        localStorage.setItem(`security_draft_${formId}`, JSON.stringify(data));
    } catch (e) {
        console.warn('Draft kaydedilemedi:', e);
    }
}

function loadDraft(form, formId) {
    try {
        const draftData = localStorage.getItem(`security_draft_${formId}`);
        if (draftData) {
            const data = JSON.parse(draftData);
            
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field && !key.includes('password')) {
                    field.value = data[key];
                }
            });
        }
    } catch (e) {
        console.warn('Draft yüklenemedi:', e);
    }
}

function clearDraft(formId) {
    try {
        localStorage.removeItem(`security_draft_${formId}`);
    } catch (e) {
        console.warn('Draft temizlenemedi:', e);
    }
}

/**
 * Debounce utility
 */
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

// Sayfa yüklendiğinde ek fonksiyonları başlat
document.addEventListener('DOMContentLoaded', function() {
    initKeyboardShortcuts();
    initActivityFilters();
    initAutoSave();
});

// Session modal stilleri (dinamik olarak eklenir)
const sessionModalStyles = `
    .session-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .session-modal-overlay.show {
        opacity: 1;
    }
    
    .session-modal {
        background: var(--card-bg);
        border: 1px solid var(--border-1);
        border-radius: 12px;
        max-width: 400px;
        width: 90%;
        transform: translateY(-20px);
        transition: transform 0.3s ease;
    }
    
    .session-modal-overlay.show .session-modal {
        transform: translateY(0);
    }
    
    .session-modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-1);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .session-modal-header h3 {
        margin: 0;
        color: var(--gold);
        font-size: 1.2rem;
    }
    
    .session-modal-header i {
        color: var(--gold);
        font-size: 1.5rem;
    }
    
    .session-modal-body {
        padding: 1.5rem;
        color: var(--light-grey);
        line-height: 1.6;
    }
    
    .session-modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-1);
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }
    
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 10001;
    }
    
    .toast.show {
        transform: translateX(0);
    }
    
    .toast-success {
        background: var(--success-color);
    }
    
    .toast-error {
        background: var(--red);
    }
    
    .toast-info {
        background: var(--gold);
        color: var(--dark-bg);
    }
`;

// Stilleri head'e ekle
if (!document.getElementById('security-modal-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'security-modal-styles';
    styleSheet.textContent = sessionModalStyles;
    document.head.appendChild(styleSheet);
}