// /events/js/events_detail.js - Etkinlik detay sayfası JavaScript işlevleri

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
    const participateBtn = document.querySelector('.participate-btn');
    if (participateBtn) {
        participateBtn.addEventListener('click', handleParticipation);
    }
    
    const leaveBtn = document.querySelector('.leave-event-btn');
    if (leaveBtn) {
        leaveBtn.addEventListener('click', handleLeaveEvent);
    }
    
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

// Etkinliğe katılım işlemi
function handleParticipation(event) {
    const button = event.target.closest('.participate-btn');
    if (!button) return;
    
    // Role selection modal'ı aç
    showRoleSelectionModal();
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
    
    // Basit rol seçimi (daha sonra modal ile değiştirilebilir)
    let roleOptions = 'Katılmak istediğiniz rolü seçin:\n\n';
    availableRoles.forEach((role, index) => {
        roleOptions += `${index + 1}. ${role.roleName}\n`;
    });
    
    const selection = prompt(roleOptions + '\nRol numarasını girin:');
    const roleIndex = parseInt(selection) - 1;
    
    if (roleIndex >= 0 && roleIndex < availableRoles.length) {
        const selectedRole = availableRoles[roleIndex];
        joinEventRole(selectedRole.slotId, selectedRole.roleName);
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
    
    if (!confirm('Etkinlikten ayrılmak istediğinizden emin misiniz?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'leave_event');
    formData.append('event_id', eventData.id);
    formData.append('csrf_token', eventData.csrfToken);
    
    // Loading state
    showLoadingState('Etkinlikten ayrılıyorsunuz...');
    
    fetch('actions/participate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showSuccessMessage('Etkinlikten başarıyla ayrıldınız.');
            
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

// Etkinlik paylaşımı
function handleShare(event) {
    const button = event.target.closest('.share-btn');
    if (!button) return;
    
    const eventUrl = window.location.href;
    const shareText = `${eventData.title} - Star Citizen Etkinliği`;
    
    // Web Share API destekleniyorsa kullan
    if (navigator.share) {
        navigator.share({
            title: shareText,
            text: `${eventData.title} etkinliğine katılın!`,
            url: eventUrl
        })
        .then(() => {
            showSuccessMessage('Etkinlik başarıyla paylaşıldı!');
        })
        .catch(error => {
            if (error.name !== 'AbortError') {
                console.error('Share error:', error);
                fallbackShare(eventUrl, shareText);
            }
        });
    } else {
        fallbackShare(eventUrl, shareText);
    }
}

// Fallback paylaşım (clipboard'a kopyala)
function fallbackShare(url, text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url)
        .then(() => {
            showSuccessMessage('Etkinlik linki panoya kopyalandı!');
        })
        .catch(() => {
            showShareModal(url, text);
        });
    } else {
        showShareModal(url, text);
    }
}

// Paylaşım modal'ını göster
function showShareModal(url, text) {
    const modal = document.createElement('div');
    modal.className = 'share-modal-overlay';
    modal.innerHTML = `
        <div class="share-modal">
            <div class="share-modal-header">
                <h3><i class="fas fa-share"></i> Etkinliği Paylaş</h3>
                <button class="close-modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="share-modal-content">
                <p>Etkinlik linkini kopyalayın:</p>
                <div class="share-url-container">
                    <input type="text" value="${url}" readonly class="share-url-input">
                    <button class="copy-url-btn"><i class="fas fa-copy"></i></button>
                </div>
                <div class="share-buttons">
                    <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}" 
                       target="_blank" class="share-btn-twitter">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" 
                       target="_blank" class="share-btn-facebook">
                        <i class="fab fa-facebook"></i> Facebook
                    </a>
                    <a href="https://discord.com/channels/@me" 
                       target="_blank" class="share-btn-discord">
                        <i class="fab fa-discord"></i> Discord
                    </a>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Modal event listeners
    const closeBtn = modal.querySelector('.close-modal');
    const copyBtn = modal.querySelector('.copy-url-btn');
    const urlInput = modal.querySelector('.share-url-input');
    
    closeBtn.addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    });
    
    copyBtn.addEventListener('click', () => {
        urlInput.select();
        document.execCommand('copy');
        showSuccessMessage('Link panoya kopyalandı!');
        document.body.removeChild(modal);
    });
    
    // ESC tuşuyla kapatma
    const handleEsc = (e) => {
        if (e.key === 'Escape') {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', handleEsc);
        }
    };
    document.addEventListener('keydown', handleEsc);
}

// Etkinlik silme
function handleDeleteEvent(event) {
    const button = event.target.closest('.delete-event-btn');
    if (!button) return;
    
    const confirmText = `"${eventData.title}" etkinliğini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`;
    
    if (!confirm(confirmText)) {
        return;
    }
    
    // İkinci onay
    const doubleConfirm = prompt('Silme işlemini onaylamak için "SİL" yazın:');
    if (doubleConfirm !== 'SİL') {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_event');
    formData.append('event_id', eventData.id);
    formData.append('csrf_token', eventData.csrfToken);
    
    // Loading state
    showLoadingState('Etkinlik siliniyor...');
    
    fetch('actions/delete_event.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showSuccessMessage('Etkinlik başarıyla silindi.');
            
            // Ana sayfaya yönlendir
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            showErrorMessage(data.message || 'Silme sırasında bir hata oluştu.');
        }
    })
    .catch(error => {
        hideLoadingState();
        console.error('Delete event error:', error);
        showErrorMessage('Bağlantı hatası oluştu. Lütfen tekrar deneyin.');
    });
}

// Katılımcı onaylama
function handleParticipantApproval(event) {
    const button = event.target.closest('.btn-approve');
    if (!button) return;
    
    const participationId = button.getAttribute('data-participation-id');
    
    const formData = new FormData();
    formData.append('action', 'approve_participant');
    formData.append('participation_id', participationId);
    formData.append('csrf_token', eventData.csrfToken);
    
    // Loading state for button
    const originalContent = button.innerHTML;
    button.innerHTML = '<div class="loading-spinner"></div>';
    button.disabled = true;
    
    fetch('actions/manage_participants.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Katılımcıyı onaylanan listesine taşı
            moveParticipantToConfirmed(button.closest('.participant-card'));
            showSuccessMessage('Katılımcı onaylandı.');
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

// Katılımcı kaldırma
function handleParticipantRemoval(event) {
    const button = event.target.closest('.btn-remove');
    if (!button) return;
    
    if (!confirm('Bu katılımcıyı etkinlikten kaldırmak istediğinizden emin misiniz?')) {
        return;
    }
    
    const participationId = button.getAttribute('data-participation-id');
    
    const formData = new FormData();
    formData.append('action', 'remove_participant');
    formData.append('participation_id', participationId);
    formData.append('csrf_token', eventData.csrfToken);
    
    // Loading state for button
    const originalContent = button.innerHTML;
    button.innerHTML = '<div class="loading-spinner"></div>';
    button.disabled = true;
    
    fetch('actions/manage_participants.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Katılımcıyı listeden kaldır
            removeParticipantFromList(button.closest('.participant-card'));
            showSuccessMessage('Katılımcı kaldırıldı.');
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

// Katılımcıyı onaylanan listesine taşı
function moveParticipantToConfirmed(participantCard) {
    const confirmedGroup = document.querySelector('.group-title.status-confirmed').closest('.participants-group');
    const confirmedGrid = confirmedGroup.querySelector('.participants-grid');
    
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

// Katılımcıyı listeden kaldır
function removeParticipantFromList(participantCard) {
    // Animation
    participantCard.style.opacity = '0';
    participantCard.style.transform = 'scale(0.9)';
    
    setTimeout(() => {
        participantCard.remove();
        updateParticipantCounts();
    }, 300);
}

// Katılımcı sayılarını güncelle
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

// Keyboard shortcuts
function handleKeyboardShortcuts(event) {
    // ESC tuşu ile modal'ları kapat
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.share-modal-overlay, .role-selection-modal');
        modals.forEach(modal => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        });
    }
    
    // Ctrl/Cmd + S ile etkinliği düzenle (eğer yetki varsa)
    if ((event.ctrlKey || event.metaKey) && event.key === 's' && eventData.canEdit) {
        event.preventDefault();
        window.location.href = `create.php?id=${eventData.id}`;
    }
    
    // Ctrl/Cmd + Shift + S ile paylaş
    if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'S') {
        event.preventDefault();
        const shareBtn = document.querySelector('.share-btn');
        if (shareBtn) {
            shareBtn.click();
        }
    }
}

// Smooth scroll için anchor linkleri
function initializeSmoothScroll() {
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Image lazy loading
function initializeImageLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });
        
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => imageObserver.observe(img));
    }
}

// Tooltip system
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
    
    if (!tooltipText) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    element._tooltip = tooltip;
}

function hideTooltip(event) {
    const element = event.target;
    if (element._tooltip) {
        document.body.removeChild(element._tooltip);
        delete element._tooltip;
    }
}

// Status refresh (participation status'u kontrol et)
function startStatusRefresh() {
    // Her 30 saniyede bir katılım durumunu kontrol et
    setInterval(checkParticipationStatus, 30000);
}

function checkParticipationStatus() {
    fetch(`actions/check_participation.php?event_id=${eventData.id}`)
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
        const participateButtons = document.querySelectorAll('.participate-btn, .btn-role-join');
        participateButtons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-clock"></i> Etkinlik Sona Erdi';
            btn.classList.add('disabled');
        });
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
            <button class="notification-close"><i class="fas fa-times"></i></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.add('notification-fade-out');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }, 5000);
    
    // Close button
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.classList.add('notification-fade-out');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    });
    
    // Show animation
    setTimeout(() => {
        notification.classList.add('notification-show');
    }, 100);
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('tr-TR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

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