// /events/js/events_detail.js - Etkinlik detay sayfası JavaScript işlevleri - GÜNCELLENMİŞ VERSİYON

// Global değişkenler
let eventData = {};

// Sayfa yüklendiğinde çalışacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // Window'dan event data'yı al
    if (window.eventData) {
        eventData = window.eventData;
    }
    
    initializeComponents();
    setupEventListeners();
    updatePageStatus();
});

// Bileşenleri başlat
function initializeComponents() {
    // Smooth scroll for anchor links
    initializeSmoothScroll();
    
    // Image lazy loading
    initializeImageLazyLoading();
    
    // Tooltip system
    initializeTooltips();
    
    // Auto-refresh for participation status
    if (eventData.canParticipate) {
        startStatusRefresh();
    }
}

// Event listener'ları kur
function setupEventListeners() {
    // Katılım butonları
    const participateBtns = document.querySelectorAll('.participate-btn');
    participateBtns.forEach(btn => {
        btn.addEventListener('click', handleParticipation);
    });
    
    // Katılım durumu butonları (Maybe/Declined)
    const statusBtns = document.querySelectorAll('.participation-status-btn');
    statusBtns.forEach(btn => {
        btn.addEventListener('click', handleParticipationStatus);
    });
    
    // Ayrılma butonu
    const leaveBtns = document.querySelectorAll('.leave-event-btn');
    leaveBtns.forEach(btn => {
        btn.addEventListener('click', handleLeaveEvent);
    });
    
    // Rol katılım butonları
    const roleJoinBtns = document.querySelectorAll('.btn-role-join');
    roleJoinBtns.forEach(btn => {
        btn.addEventListener('click', handleRoleParticipation);
    });
    
    // Paylaşım butonları
    const shareBtns = document.querySelectorAll('.share-btn');
    shareBtns.forEach(btn => {
        btn.addEventListener('click', handleShare);
    });
    
    // Silme butonu
    const deleteBtn = document.querySelector('.delete-event-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', handleDeleteEvent);
    }
    
    // Katılımcı yönetimi butonları (sadece organize edenler)
    const approveBtns = document.querySelectorAll('.btn-approve');
    approveBtns.forEach(btn => {
        btn.addEventListener('click', handleParticipantApproval);
    });
    
    const removeBtns = document.querySelectorAll('.btn-remove');
    removeBtns.forEach(btn => {
        btn.addEventListener('click', handleParticipantRemoval);
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
}

// Etkinliğe katılım işlemi (role katıl)
function handleParticipation(event) {
    const button = event.target.closest('.participate-btn');
    if (!button) return;
    
    // Role selection modal'ı aç
    showRoleSelectionModal();
}

// Katılım durumu değiştirme (Maybe/Declined)
function handleParticipationStatus(event) {
    const button = event.target.closest('.participation-status-btn');
    if (!button) return;
    
    const eventId = button.getAttribute('data-event-id');
    const status = button.getAttribute('data-status');
    
    if (!eventId || !status) {
        showErrorMessage('Geçersiz parametreler');
        return;
    }
    
    const statusText = status === 'maybe' ? 'Belki Katılırım' : 'Katılmıyorum';
    
    if (!confirm(`Katılım durumunuzu "${statusText}" olarak güncellemek istediğinizden emin misiniz?`)) {
        return;
    }
    
    updateParticipationStatus(eventId, status);
}

// Katılım durumu güncelleme
function updateParticipationStatus(eventId, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('event_id', eventId);
    formData.append('status', status);
    formData.append('csrf_token', eventData.csrfToken);
    
    showLoadingState('Durum güncelleniyor...');
    
    fetch('actions/participate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showSuccessMessage(data.message);
            
            // Sayfayı yenile
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showErrorMessage(data.message || 'Durum güncellenirken bir hata oluştu.');
        }
    })
    .catch(error => {
        hideLoadingState();
        console.error('Status update error:', error);
        showErrorMessage('Bağlantı hatası oluştu. Lütfen tekrar deneyin.');
    });
}

// Rol seçim modal'ını göster
function showRoleSelectionModal() {
    const roles = document.querySelectorAll('.role-card');
    if (roles.length === 0) {
        alert('Bu etkinlik için henüz rol tanımlanmamış.');
        return;
    }
    
    // Available roles'ları topla
    const availableRoles = [];
    roles.forEach(roleCard => {
        const roleJoinBtn = roleCard.querySelector('.btn-role-join');
        if (roleJoinBtn) {
            const slotId = roleJoinBtn.getAttribute('data-slot-id');
            const roleName = roleJoinBtn.getAttribute('data-role-name');
            if (slotId && roleName) {
                availableRoles.push({ slotId, roleName });
            }
        }
    });
    
    if (availableRoles.length === 0) {
        alert('Bu etkinlikte tüm roller dolu.');
        return;
    }
    
    // Gelişmiş rol seçim modal'ı (HTML modal kullan)
    showAdvancedRoleModal(availableRoles);
}

// Gelişmiş rol seçim modal'ı
function showAdvancedRoleModal(availableRoles) {
    // Modal HTML'ini oluştur
    const modalHtml = `
        <div class="modal-overlay" id="roleSelectionModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-tag"></i> Rol Seçimi</h3>
                    <button type="button" class="modal-close" onclick="closeRoleModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Katılmak istediğiniz rolü seçin:</p>
                    <div class="role-selection-grid">
                        ${availableRoles.map((role, index) => `
                            <div class="role-option" data-slot-id="${role.slotId}" data-role-name="${role.roleName}">
                                <div class="role-option-content">
                                    <i class="fas fa-user"></i>
                                    <span class="role-name">${role.roleName}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeRoleModal()">
                        <i class="fas fa-times"></i>
                        İptal
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Modal'ı sayfaya ekle
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Rol seçeneklerine click listener ekle
    const roleOptions = document.querySelectorAll('.role-option');
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            const slotId = this.getAttribute('data-slot-id');
            const roleName = this.getAttribute('data-role-name');
            
            closeRoleModal();
            joinEventRole(slotId, roleName);
        });
    });
    
    // ESC tuşu ile kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRoleModal();
        }
    });
}

// Rol modal'ını kapat
function closeRoleModal() {
    const modal = document.getElementById('roleSelectionModal');
    if (modal) {
        modal.remove();
    }
}

// Belirli role katılım
function handleRoleParticipation(event) {
    const button = event.target.closest('.btn-role-join');
    if (!button) return;
    
    const slotId = button.getAttribute('data-slot-id');
    const roleName = button.getAttribute('data-role-name');
    
    if (confirm(`"${roleName}" rolüne katılmak istediğinizden emin misiniz?`)) {
        joinEventRole(slotId, roleName);
    }
}

// Etkinlik rolüne katıl
function joinEventRole(slotId, roleName) {
    const formData = new FormData();
    formData.append('action', 'join_role');
    formData.append('event_id', eventData.id);
    formData.append('role_slot_id', slotId);
    formData.append('csrf_token', eventData.csrfToken);
    
    // Loading state
    showLoadingState('Katılım işleminiz gerçekleştiriliyor...');
    
    fetch('actions/participate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showSuccessMessage(`"${roleName}" rolüne başarıyla katıldınız!`);
            
            // Sayfayı yenile
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showErrorMessage(data.message || 'Katılım sırasında bir hata oluştu.');
        }
    })
    .catch(error => {
        hideLoadingState();
        console.error('Participation error:', error);
        showErrorMessage('Bağlantı hatası oluştu. Lütfen tekrar deneyin.');
    });
}

// Etkinlikten ayrılma
function handleLeaveEvent(event) {
    const button = event.target.closest('.leave-event-btn');
    if (!button) return;
    
    if (!confirm('Etkinlikten ayrılmak istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve rolünüzden de çıkarılacaksınız.')) {
        return;
    }
    
    const eventId = button.getAttribute('data-event-id');
    
    const formData = new FormData();
    formData.append('action', 'leave_event');
    formData.append('event_id', eventId);
    formData.append('csrf_token', eventData.csrfToken);
    
    showLoadingState('Etkinlikten ayrılıyorsunuz...');
    
    fetch('actions/participate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showSuccessMessage(data.message);
            
            // Sayfayı yenile
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showErrorMessage(data.message || 'Ayrılma sırasında bir hata oluştu.');
        }
    })
    .catch(error => {
        hideLoadingState();
        console.error('Leave event error:', error);
        showErrorMessage('Bağlantı hatası oluştu. Lütfen tekrar deneyin.');
    });
}

// Katılımcı onaylama (organize edenler için)
function handleParticipantApproval(event) {
    const button = event.target.closest('.btn-approve');
    if (!button) return;
    
    const participationId = button.getAttribute('data-participation-id');
    
    if (!confirm('Bu katılımcıyı onaylamak istediğinizden emin misiniz?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'approve_participant');
    formData.append('participation_id', participationId);
    formData.append('csrf_token', eventData.csrfToken);
    
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    fetch('actions/participate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            
            // Katılımcıyı onaylanan listesine taşı
            const participantCard = button.closest('.participant-card');
            if (participantCard) {
                moveParticipantToConfirmed(participantCard);
            }
        } else {
            button.innerHTML = originalContent;
            button.disabled = false;
            showErrorMessage(data.message || 'Onaylama sırasında bir hata oluştu.');
        }
    })
    .catch(error => {
        button.innerHTML = originalContent;
        button.disabled = false;
        console.error('Approve participant error:', error);
        showErrorMessage('Bağlantı hatası oluştu.');
    });
}

// Katılımcı kaldırma (organize edenler için)
function handleParticipantRemoval(event) {
    const button = event.target.closest('.btn-remove');
    if (!button) return;
    
    const participationId = button.getAttribute('data-participation-id');
    
    if (!confirm('Bu katılımcıyı etkinlikten kaldırmak istediğinizden emin misiniz?\n\nBu işlem geri alınamaz.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_participant');
    formData.append('participation_id', participationId);
    formData.append('csrf_token', eventData.csrfToken);
    
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    fetch('actions/participate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            
            // Katılımcıyı listeden kaldır
            const participantCard = button.closest('.participant-card');
            if (participantCard) {
                removeParticipantFromList(participantCard);
            }
        } else {
            button.innerHTML = originalContent;
            button.disabled = false;
            showErrorMessage(data.message || 'Kaldırma sırasında bir hata oluştu.');
        }
    })
    .catch(error => {
        button.innerHTML = originalContent;
        button.disabled = false;
        console.error('Remove participant error:', error);
        showErrorMessage('Bağlantı hatası oluştu.');
    });
}

// Paylaşım işlemi
function handleShare(event) {
    const button = event.target.closest('.share-btn');
    if (!button) return;
    
    const eventId = button.getAttribute('data-event-id');
    const url = window.location.href;
    const title = document.title;
    
    // Web Share API destekleniyorsa kullan
    if (navigator.share) {
        navigator.share({
            title: title,
            text: 'Bu etkinliği kontrol et!',
            url: url
        }).catch(console.error);
    } else {
        // Fallback: URL'yi kopyala
        navigator.clipboard.writeText(url).then(() => {
            showSuccessMessage('Etkinlik linki kopyalandı!');
        }).catch(() => {
            // Son çare: prompt ile göster
            prompt('Etkinlik linkini kopyalayın:', url);
        });
    }
}

// Etkinlik silme
function handleDeleteEvent(event) {
    const button = event.target.closest('.delete-event-btn');
    if (!button) return;
    
    const eventId = button.getAttribute('data-event-id');
    
    if (!confirm('Bu etkinliği silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve tüm katılımcı verileri silinecektir.')) {
        return;
    }
    
    // İkinci onay
    if (!confirm('Gerçekten emin misiniz? Bu işlem GERİ ALINAMAZ!')) {
        return;
    }
    
    showLoadingState('Etkinlik siliniyor...');
    
    // Delete endpoint'ine istek gönder
    window.location.href = `actions/delete_event.php?id=${eventId}`;
}

// Keyboard shortcuts
function handleKeyboardShortcuts(event) {
    // Ctrl/Cmd + Enter: Etkinliğe katıl
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        const participateBtn = document.querySelector('.participate-btn');
        if (participateBtn && !participateBtn.disabled) {
            participateBtn.click();
        }
    }
    
    // ESC: Modal'ları kapat
    if (event.key === 'Escape') {
        closeRoleModal();
    }
}

// Katılım durumu güncelleme döngüsü
function startStatusRefresh() {
    // Her 30 saniyede bir katılım durumunu kontrol et
    setInterval(checkParticipationStatus, 30000);
}

function checkParticipationStatus() {
    if (!eventData.id) return;
    
    fetch(`api/event_status.php?event_id=${eventData.id}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.participation) {
            // Katılım durumu değiştiyse sayfayı yenile
            if (data.participation.status !== eventData.userParticipation?.participation_status) {
                window.location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Status check error:', error);
    });
}

// Sayfa durumunu güncelle
function updatePageStatus() {
    // Etkinlik tarihini kontrol et
    const eventDate = new Date(eventData.eventDate);
    const now = new Date();
    
    if (eventDate < now) {
        // Geçmiş etkinlik
        const participateButtons = document.querySelectorAll('.participate-btn, .btn-role-join, .participation-status-btn');
        participateButtons.forEach(btn => {
            btn.disabled = true;
            if (btn.classList.contains('participate-btn')) {
                btn.innerHTML = '<i class="fas fa-clock"></i> Etkinlik Sona Erdi';
            }
            btn.classList.add('disabled');
        });
        
        // Uyarı mesajı ekle
        const eventActions = document.querySelector('.event-actions');
        if (eventActions && !eventActions.querySelector('.event-ended-warning')) {
            const warning = document.createElement('div');
            warning.className = 'event-ended-warning';
            warning.innerHTML = '<i class="fas fa-info-circle"></i> Bu etkinlik sona ermiştir.';
            eventActions.insertBefore(warning, eventActions.firstChild);
        }
    }
}

// Utility Functions

function moveParticipantToConfirmed(participantCard) {
    const confirmedGroup = document.querySelector('.group-title.status-confirmed')?.closest('.participants-group');
    const confirmedGrid = confirmedGroup?.querySelector('.participants-grid');
    
    if (!confirmedGrid) return;
    
    // Approve butonunu kaldır
    const approveBtn = participantCard.querySelector('.btn-approve');
    if (approveBtn) {
        approveBtn.remove();
    }
    
    // Katılımcıyı taşı
    confirmedGrid.appendChild(participantCard);
    
    // Pending grup sayısını güncelle
    updateParticipantCounts();
    
    // Animation
    participantCard.style.transform = 'scale(1.05)';
    setTimeout(() => {
        participantCard.style.transform = 'scale(1)';
    }, 200);
}

function removeParticipantFromList(participantCard) {
    // Animation
    participantCard.style.opacity = '0';
    participantCard.style.transform = 'scale(0.9)';
    
    setTimeout(() => {
        participantCard.remove();
        updateParticipantCounts();
    }, 300);
}

function updateParticipantCounts() {
    const confirmedCards = document.querySelectorAll('.status-confirmed .participant-card');
    const pendingCards = document.querySelectorAll('.status-pending .participant-card');
    
    // Group title'ları güncelle
    const confirmedTitle = document.querySelector('.group-title.status-confirmed');
    const pendingTitle = document.querySelector('.group-title.status-pending');
    
    if (confirmedTitle) {
        const count = confirmedCards.length;
        confirmedTitle.innerHTML = confirmedTitle.innerHTML.replace(/\(\d+\)/, `(${count})`);
    }
    
    if (pendingTitle) {
        const count = pendingCards.length;
        pendingTitle.innerHTML = pendingTitle.innerHTML.replace(/\(\d+\)/, `(${count})`);
    }
    
    // Ana katılımcı sayısını güncelle
    const participantsCount = document.querySelector('.participants-count');
    if (participantsCount) {
        const totalCount = confirmedCards.length + pendingCards.length;
        participantsCount.textContent = `(${totalCount} kişi)`;
    }
}

// UI Helper Functions

function showLoadingState(message = 'İşlem gerçekleştiriliyor...') {
    const existingLoader = document.querySelector('.loading-overlay');
    if (existingLoader) return;
    
    const loader = document.createElement('div');
    loader.className = 'loading-overlay';
    loader.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner-large"></div>
            <p>${message}</p>
        </div>
    `;
    
    document.body.appendChild(loader);
}

function hideLoadingState() {
    const loader = document.querySelector('.loading-overlay');
    if (loader) {
        loader.remove();
    }
}

function showSuccessMessage(message) {
    showNotification(message, 'success');
}

function showErrorMessage(message) {
    showNotification(message, 'error');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Otomatik kaldırma
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Component initialization functions

function initializeSmoothScroll() {
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

function initializeImageLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    }
}

function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(event) {
    const element = event.target;
    const tooltipText = element.getAttribute('data-tooltip');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    
    element._tooltip = tooltip;
}

function hideTooltip(event) {
    const element = event.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}