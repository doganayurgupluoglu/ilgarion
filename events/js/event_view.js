// events/js/event_view.js - Etkinlik Detay Sayfası JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeEventView();
});

function initializeEventView() {
    // Initialize all components
    initializeParticipationButtons();
    initializeRoleButtons();
    initializeDeleteModal();
    
    // Get CSRF token and event ID
    const csrfToken = document.getElementById('csrf-token')?.value;
    const eventId = document.getElementById('current-event-id')?.value;
    
    if (!csrfToken || !eventId) {
        console.log('CSRF token or event ID not found - some features may be disabled');
        return;
    }
    
    // Store globally for use in functions
    window.eventData = {
        csrfToken: csrfToken,
        eventId: parseInt(eventId)
    };
}

// Participation Status Buttons
function initializeParticipationButtons() {
    const statusButtons = document.querySelectorAll('.btn-status');
    
    statusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const status = this.dataset.status;
            const eventId = this.dataset.eventId;
            
            if (!window.eventData?.csrfToken) {
                showNotification('Oturum hatası - lütfen sayfayı yenileyin', 'error');
                return;
            }
            
            updateParticipationStatus(eventId, status, this);
        });
    });
}

// Role Participation Buttons
function initializeRoleButtons() {
    // Join role buttons
    const joinButtons = document.querySelectorAll('.join-role');
    joinButtons.forEach(button => {
        button.addEventListener('click', function() {
            const slotId = this.dataset.slotId;
            const roleName = this.dataset.roleName;
            
            if (!window.eventData?.csrfToken) {
                showNotification('Oturum hatası - lütfen sayfayı yenileyin', 'error');
                return;
            }
            
            joinEventRole(slotId, roleName, this);
        });
    });
    
    // Leave role buttons
    const leaveButtons = document.querySelectorAll('.leave-role');
    leaveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const slotId = this.dataset.slotId;
            
            if (!window.eventData?.csrfToken) {
                showNotification('Oturum hatası - lütfen sayfayı yenileyin', 'error');
                return;
            }
            
            if (confirm('Bu rolden ayrılmak istediğinizden emin misiniz?')) {
                leaveEventRole(slotId, this);
            }
        });
    });
}

// Delete Modal
function initializeDeleteModal() {
    const deleteButton = document.querySelector('.delete-event');
    const modal = document.getElementById('deleteEventModal');
    const closeButtons = modal?.querySelectorAll('.close, .close-modal');
    const confirmButton = modal?.querySelector('.confirm-delete');
    
    if (!deleteButton || !modal) return;
    
    deleteButton.addEventListener('click', function() {
        const eventId = this.dataset.eventId;
        const eventTitle = this.dataset.eventTitle;
        
        document.getElementById('eventTitle').textContent = eventTitle;
        modal.style.display = 'block';
        
        // Store event ID for confirmation
        confirmButton.dataset.eventId = eventId;
    });
    
    // Close modal handlers
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    });
    
    // Click outside to close
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Confirm delete
    confirmButton?.addEventListener('click', function() {
        const eventId = this.dataset.eventId;
        
        if (!window.eventData?.csrfToken) {
            showNotification('Oturum hatası - lütfen sayfayı yenileyin', 'error');
            return;
        }
        
        deleteEvent(eventId);
    });
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            modal.style.display = 'none';
        }
    });
}

// API Functions
function updateParticipationStatus(eventId, status, buttonElement) {
    // Show loading state
    const originalHTML = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Güncelleniyor...';
    
    fetch('actions/update_participation_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event_id: parseInt(eventId),
            status: status,
            csrf_token: window.eventData.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            updateStatusButtons(status);
            showNotification(data.message, 'success');
            
            // Refresh page after short delay to show updated participant counts
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
    })
    .finally(() => {
        // Restore button state
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHTML;
    });
}

function joinEventRole(slotId, roleName, buttonElement) {
    // Show loading state
    const originalHTML = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Katılıyor...';
    
    fetch('actions/join_event_role.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event_id: window.eventData.eventId,
            slot_id: parseInt(slotId),
            csrf_token: window.eventData.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Refresh page to show updated role participation
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Rol katılımı sırasında bir hata oluştu.', 'error');
    })
    .finally(() => {
        // Restore button state
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHTML;
    });
}

function leaveEventRole(slotId, buttonElement) {
    // Show loading state
    const originalHTML = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ayrılıyor...';
    
    fetch('actions/leave_event_role.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event_id: window.eventData.eventId,
            slot_id: parseInt(slotId),
            csrf_token: window.eventData.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Refresh page to show updated role participation
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Rolden ayrılma sırasında bir hata oluştu.', 'error');
    })
    .finally(() => {
        // Restore button state
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHTML;
    });
}

function deleteEvent(eventId) {
    const modal = document.getElementById('deleteEventModal');
    const confirmButton = modal.querySelector('.confirm-delete');
    
    // Show loading state
    const originalHTML = confirmButton.innerHTML;
    confirmButton.disabled = true;
    confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
    
    fetch('actions/delete_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event_id: parseInt(eventId),
            csrf_token: window.eventData.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            modal.style.display = 'none';
            
            // Redirect to events list after short delay
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Etkinlik silme sırasında bir hata oluştu.', 'error');
    })
    .finally(() => {
        // Restore button state
        confirmButton.disabled = false;
        confirmButton.innerHTML = originalHTML;
    });
}

// UI Helper Functions
function updateStatusButtons(activeStatus) {
    const statusButtons = document.querySelectorAll('.btn-status');
    
    statusButtons.forEach(button => {
        const buttonStatus = button.dataset.status;
        
        if (buttonStatus === activeStatus) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    // Icon based on type
    let icon = 'fas fa-info-circle';
    switch (type) {
        case 'success':
            icon = 'fas fa-check-circle';
            break;
        case 'error':
            icon = 'fas fa-exclamation-triangle';
            break;
        case 'warning':
            icon = 'fas fa-exclamation-circle';
            break;
    }
    
    notification.innerHTML = `
        <div class="notification-content">
            <i class="${icon}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
    
    // Slide in animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
}

// Utility Functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
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

// Enhanced error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    
    // Show user-friendly error for critical failures
    if (e.error && e.error.message.includes('fetch')) {
        showNotification('Bağlantı hatası. İnternet bağlantınızı kontrol edin.', 'error');
    }
});

// Handle visibility change (tab switching)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Page became visible again - could refresh data if needed
        console.log('Page became visible again');
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + R to refresh (allow default behavior)
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        // Let default refresh happen
        return;
    }
    
    // ESC to close any open modals
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal[style*="block"]');
        if (openModal) {
            openModal.style.display = 'none';
        }
    }
});

// Touch device enhancements
if ('ontouchstart' in window) {
    // Add touch-friendly classes
    document.body.classList.add('touch-device');
    
    // Improve button interactions on touch devices
    const buttons = document.querySelectorAll('button, .btn-status, .join-role, .leave-role');
    buttons.forEach(button => {
        button.addEventListener('touchstart', function() {
            this.classList.add('touch-active');
        });
        
        button.addEventListener('touchend', function() {
            setTimeout(() => {
                this.classList.remove('touch-active');
            }, 150);
        });
    });
}

// Performance monitoring
if ('PerformanceObserver' in window) {
    const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
            if (entry.entryType === 'navigation') {
                console.log(`Page load time: ${entry.loadEventEnd - entry.loadEventStart}ms`);
            }
        }
    });
    
    observer.observe({ entryTypes: ['navigation'] });
}

// Add notification styles if not already present
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            max-width: 400px;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            opacity: 0;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification-success {
            background: var(--green, #28a745);
            color: white;
        }
        
        .notification-error {
            background: var(--red, #dc3545);
            color: white;
        }
        
        .notification-warning {
            background: var(--gold, #ffc107);
            color: var(--dark-bg, #1a1a1a);
        }
        
        .notification-info {
            background: var(--turquase, #17a2b8);
            color: white;
        }
        
        .notification-content {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .notification-content i {
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .notification-content span {
            flex: 1;
            font-weight: 500;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
            flex-shrink: 0;
        }
        
        .notification-close:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .touch-device .notification {
            right: 10px;
            left: 10px;
            max-width: none;
        }
        
        @media (max-width: 480px) {
            .notification {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    `;
    document.head.appendChild(style);
}

console.log('Event view initialized successfully');