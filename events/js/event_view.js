// events/js/event_view.js - Etkinlik detay sayfası JavaScript (Yenilendi)

document.addEventListener('DOMContentLoaded', function() {
    initializeEventView();
});

function initializeEventView() {
    // Modal elements
    const deleteModal = document.getElementById('deleteEventModal');
    const closeModalBtn = deleteModal?.querySelector('.close');
    
    // Button elements
    const deleteEventBtn = document.querySelector('.delete-event');
    const roleStatusBtns = document.querySelectorAll('.btn-role-status');
    const statusBtns = document.querySelectorAll('.btn-status');
    const tabBtns = document.querySelectorAll('.tab-btn');
    
    // Initialize event listeners
    initializeModalHandlers();
    initializeRoleHandlers();
    initializeStatusHandlers();
    initializeTabHandlers();
    
    // Functions
    function initializeModalHandlers() {
        if (deleteEventBtn) {
            deleteEventBtn.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                const eventTitle = this.dataset.eventTitle;
                showDeleteModal(eventId, eventTitle);
            });
        }
        
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', closeDeleteModal);
        }
        
        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    closeDeleteModal();
                }
            });
        }
        
        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && deleteModal && deleteModal.style.display === 'block') {
                closeDeleteModal();
            }
        });
    }
    
    function initializeRoleHandlers() {
        roleStatusBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.action;
                const roleId = this.dataset.roleId;
                const eventId = this.closest('.role-card').dataset.eventId;
                
                if (action === 'join') {
                    joinEventRole(eventId, roleId, this);
                } else if (action === 'leave') {
                    leaveEventRole(eventId, roleId, this);
                }
            });
        });
    }
    
    function initializeStatusHandlers() {
        statusBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const status = this.dataset.status;
                const eventId = this.dataset.eventId;
                updateParticipationStatus(eventId, status, this);
            });
        });
    }
    
    function initializeTabHandlers() {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                switchTab(tabName);
            });
        });
    }
    
    function showDeleteModal(eventId, eventTitle) {
        if (!deleteModal) return;
        
        document.getElementById('deleteEventName').textContent = eventTitle;
        deleteModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Store event ID for deletion
        window.currentDeleteEventId = eventId;
    }
    
    function closeDeleteModal() {
        if (!deleteModal) return;
        
        deleteModal.style.display = 'none';
        document.body.style.overflow = '';
        window.currentDeleteEventId = null;
    }
    
    function joinEventRole(eventId, roleId, btn) {
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Katılınıyor...';
        
        fetch('actions/join_event_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                event_id: eventId,
                role_id: roleId,
                csrf_token: getCSRFToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                
                // Update button state
                btn.dataset.action = 'leave';
                btn.className = 'btn-role-status joined';
                btn.innerHTML = '<i class="fas fa-check"></i> Katıldınız';
                
                // Update role card
                const roleCard = btn.closest('.role-card');
                roleCard.classList.add('user-joined');
                
                // Update role count
                updateRoleCount(roleId, 1);
                
                // Update participation status to joined
                updateStatusButtonsState('joined');
                
                // Refresh participants if needed
                setTimeout(() => {
                    updateParticipantCounts();
                }, 500);
                
            } else {
                showNotification(data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Role katılım işlemi sırasında bir hata oluştu.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
    
    function leaveEventRole(eventId, roleId, btn) {
        if (!confirm('Bu rolden ayrılmak istediğinizden emin misiniz?')) {
            return;
        }
        
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ayrılınıyor...';
        
        fetch('actions/leave_event_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                event_id: eventId,
                role_id: roleId,
                csrf_token: getCSRFToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                
                // Update button state
                btn.dataset.action = 'join';
                btn.className = 'btn-role-status available';
                btn.innerHTML = '<i class="fas fa-user-plus"></i> Katıl';
                
                // Update role card
                const roleCard = btn.closest('.role-card');
                roleCard.classList.remove('user-joined');
                
                // Update role count
                updateRoleCount(roleId, -1);
                
                // Update participation status
                updateStatusButtonsState('not_responded');
                
                // Refresh participants if needed
                setTimeout(() => {
                    updateParticipantCounts();
                }, 500);
                
            } else {
                showNotification(data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Rolden ayrılma işlemi sırasında bir hata oluştu.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
    
    function updateParticipationStatus(eventId, status, btn) {
        const originalState = btn.classList.contains('active');
        
        // Temporarily update UI
        document.querySelectorAll('.btn-status').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        btn.disabled = true;
        
        fetch('actions/update_participation_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                event_id: eventId,
                status: status,
                csrf_token: getCSRFToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                
                // Update current status display
                updateCurrentStatusDisplay(status);
                
                // Update participant counts
                setTimeout(() => {
                    updateParticipantCounts();
                }, 500);
                
            } else {
                showNotification(data.message, 'error');
                
                // Revert UI state
                document.querySelectorAll('.btn-status').forEach(b => b.classList.remove('active'));
                if (originalState) {
                    btn.classList.add('active');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Durum güncelleme işlemi sırasında bir hata oluştu.', 'error');
            
            // Revert UI state
            document.querySelectorAll('.btn-status').forEach(b => b.classList.remove('active'));
            if (originalState) {
                btn.classList.add('active');
            }
        })
        .finally(() => {
            btn.disabled = false;
        });
    }
    
    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');
    }
    
    function updateRoleCount(roleId, change) {
        const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
        if (roleCard) {
            const countElement = roleCard.querySelector('.role-count .current');
            if (countElement) {
                const currentCount = parseInt(countElement.textContent) || 0;
                countElement.textContent = Math.max(0, currentCount + change);
            }
        }
    }
    
    function updateStatusButtonsState(status) {
        document.querySelectorAll('.btn-status').forEach(btn => {
            btn.classList.remove('active');
        });
        
        if (status !== 'not_responded') {
            const statusBtn = document.querySelector(`[data-status="${status}"]`);
            if (statusBtn) {
                statusBtn.classList.add('active');
            }
        }
    }
    
    function updateCurrentStatusDisplay(status) {
        const statusDisplay = document.querySelector('.current-status');
        if (!statusDisplay) return;
        
        let message = '';
        switch(status) {
            case 'joined':
                message = '<i class="fas fa-check-circle" style="color: var(--turquase);"></i> Bu etkinliğe katılacağınızı bildirdiniz.';
                break;
            case 'maybe':
                message = '<i class="fas fa-question-circle" style="color: var(--gold);"></i> Bu etkinliğe belki katılacağınızı bildirdiniz.';
                break;
            case 'declined':
                message = '<i class="fas fa-times-circle" style="color: var(--red);"></i> Bu etkinliğe katılmayacağınızı bildirdiniz.';
                break;
        }
        
        statusDisplay.querySelector('p').innerHTML = message;
        
        // Show status display if hidden
        statusDisplay.style.display = 'block';
    }
    
    function updateParticipantCounts() {
        // This could fetch updated counts via AJAX if needed
        // For now, we'll refresh the page after a delay to show updated participants
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }
}

// Global functions accessible from HTML
window.confirmDeleteEvent = function() {
    const eventId = window.currentDeleteEventId;
    if (!eventId) return;
    
    const btn = document.querySelector('#deleteEventModal .btn-action-danger');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
    
    fetch('actions/delete_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            event_id: eventId,
            csrf_token: getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.href = '/events/';
            }, 1500);
        } else {
            showNotification(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Silme işlemi sırasında bir hata oluştu.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
};

window.closeDeleteModal = function() {
    const deleteModal = document.getElementById('deleteEventModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
        document.body.style.overflow = '';
        window.currentDeleteEventId = null;
    }
};

// Utility functions
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : '';
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-icon">
                <i class="fas fa-${getNotificationIcon(type)}"></i>
            </div>
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--card-bg);
        border: 1px solid var(--border-1);
        border-radius: 8px;
        padding: 1rem;
        max-width: 400px;
        z-index: 1001;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        animation: slideInRight 0.3s ease;
    `;
    
    const content = notification.querySelector('.notification-content');
    content.style.cssText = `
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--lighter-grey);
    `;
    
    const icon = notification.querySelector('.notification-icon');
    icon.style.cssText = `
        color: ${getNotificationColor(type)};
        font-size: 1.2rem;
    `;
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        color: var(--light-grey);
        cursor: pointer;
        padding: 0.25rem;
        margin-left: auto;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    switch (type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-circle';
        case 'warning': return 'exclamation-triangle';
        default: return 'info-circle';
    }
}

function getNotificationColor(type) {
    switch (type) {
        case 'success': return '#28a745';
        case 'error': return 'var(--red)';
        case 'warning': return 'var(--gold)';
        default: return 'var(--turquase)';
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);