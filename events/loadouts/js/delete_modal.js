let deleteSetId = null;

function confirmDelete(setId, setName) {
    deleteSetId = setId;
    document.getElementById('deleteSetName').textContent = setName;
    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    deleteSetId = null;
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteSetId) return;
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
    btn.disabled = true;
    
    // CSRF token al
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= generate_csrf_token() ?>';
    
    fetch('../actions/delete_loadout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            loadout_id: deleteSetId,
            csrf_token: csrfToken
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Başarılı silme - sayfayı yenile
            showMessage(data.message || 'Teçhizat seti başarıyla silindi', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showMessage(data.message || 'Silme işlemi başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showMessage('Silme sırasında bir hata oluştu', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        closeDeleteModal();
    });
});

// Modal dışına tıklayınca kapat
document.addEventListener('click', function(e) {
    if (e.target.matches('.modal-overlay')) {
        closeDeleteModal();
    }
});

// Escape tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Message gösterme fonksiyonu
function showMessage(message, type = 'info') {
    // Mevcut mesajları kaldır
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());
    
    // Yeni mesaj oluştur
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        padding: 1rem 1.5rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: var(--font);
        font-weight: 500;
        animation: slideInRight 0.3s ease;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;
    
    if (type === 'success') {
        messageDiv.style.background = 'rgba(40, 167, 69, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.style.border = '1px solid #28a745';
        messageDiv.innerHTML = '<i class="fas fa-check-circle"></i><span>' + message + '</span>';
    } else if (type === 'error') {
        messageDiv.style.background = 'rgba(220, 53, 69, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.style.border = '1px solid #dc3545';
        messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + message + '</span>';
    } else {
        messageDiv.style.background = 'rgba(23, 162, 184, 0.9)';
        messageDiv.style.color = '#fff';
        messageDiv.style.border = '1px solid #17a2b8';
        messageDiv.innerHTML = '<i class="fas fa-info-circle"></i><span>' + message + '</span>';
    }
    
    document.body.appendChild(messageDiv);
    
    // 5 saniye sonra kaldır
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => {
            messageDiv.remove();
        }, 300);
    }, 5000);
}

// CSS animasyonu ekle
const style = document.createElement('style');
style.textContent = `
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
`;
document.head.appendChild(style);