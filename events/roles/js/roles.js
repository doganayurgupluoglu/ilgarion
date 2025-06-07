/**
 * Etkinlik Rolleri Sayfası JavaScript
 * /events/roles/js/roles.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Modal elementleri
    const deleteModal = document.getElementById('deleteRoleModal');
    const deleteRoleName = document.getElementById('deleteRoleName');
    const confirmDeleteBtn = document.getElementById('confirmDeleteRole');
    const cancelBtns = document.querySelectorAll('[data-bs-dismiss="modal"]');
    
    // Global değişkenler
    let roleToDelete = null;
    let modalInstance = null;
    
    // Bootstrap kontrolü ve modal instance oluşturma
    if (deleteModal) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            modalInstance = new bootstrap.Modal(deleteModal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
        }
    }
    
    /**
     * Modal açma işlemi
     */
    function openModal() {
        if (modalInstance) {
            modalInstance.show();
        } else {
            // Manuel modal açma
            if (deleteModal) {
                deleteModal.style.display = 'flex';
                deleteModal.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Backdrop ekle
                if (!document.querySelector('.modal-backdrop')) {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        z-index: 1040;
                        width: 100vw;
                        height: 100vh;
                        background-color: rgba(0, 0, 0, 0.5);
                    `;
                    document.body.appendChild(backdrop);
                    
                    backdrop.addEventListener('click', closeModal);
                }
            }
        }
    }
    
    /**
     * Modal kapatma işlemleri
     */
    function closeModal() {
        if (modalInstance) {
            modalInstance.hide();
        } else {
            // Manuel modal kapatma
            if (deleteModal) {
                deleteModal.style.display = 'none';
                deleteModal.classList.remove('show');
                document.body.classList.remove('modal-open');
                
                // Backdrop'u kaldır
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        }
        roleToDelete = null;
    }
    
    /**
     * Rol silme butonlarına event listener ekle
     */
    function initDeleteButtons() {
        document.querySelectorAll('.delete-role').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                roleToDelete = this.dataset.roleId;
                const roleName = this.dataset.roleName;
                
                if (deleteRoleName) {
                    deleteRoleName.textContent = roleName;
                }
                
                openModal();
            });
        });
    }
    
    /**
     * İptal butonları için event listener
     */
    function initCancelButtons() {
        cancelBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
            });
        });
        
        // Modal backdrop'a tıklanınca kapatma
        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    closeModal();
                }
            });
        }
        
        // ESC tuşu ile kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && deleteModal && deleteModal.classList.contains('show')) {
                closeModal();
            }
        });
    }
    
    /**
     * CSRF Token alma
     */
    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
               document.querySelector('input[name="csrf_token"]')?.value || '';
    }
    
    /**
     * Rol silme onayı
     */
    function initConfirmDelete() {
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (!roleToDelete) {
                    showAlert('Silinecek rol seçilmedi!', 'error');
                    return;
                }
                
                // Butonu deaktif et
                confirmDeleteBtn.disabled = true;
                const originalContent = confirmDeleteBtn.innerHTML;
                confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
                
                // CSRF token'ı al
                const csrfToken = getCSRFToken();
                
                // AJAX ile silme isteği gönder
                fetch('actions/delete_role.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        role_id: parseInt(roleToDelete),
                        csrf_token: csrfToken
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert('Rol başarıyla silindi!', 'success');
                        
                        // Modal'ı kapat
                        closeModal();
                        
                        // Rol kartını kaldır
                        setTimeout(() => {
                            removeRoleCard(roleToDelete);
                        }, 1000);
                        
                    } else {
                        throw new Error(data.message || 'Bilinmeyen bir hata oluştu');
                    }
                })
                .catch(error => {
                    console.error('Delete role error:', error);
                    showAlert('Hata: ' + error.message, 'error');
                })
                .finally(() => {
                    // Butonu tekrar aktif et
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = originalContent;
                });
            });
        }
    }
    
    /**
     * Rol kartını DOM'dan kaldır
     */
    function removeRoleCard(roleId) {
        const roleCard = document.querySelector(`[data-role-id="${roleId}"]`);
        if (roleCard) {
            roleCard.style.transition = 'all 0.3s ease';
            roleCard.style.opacity = '0';
            roleCard.style.transform = 'scale(0.8)';
            
            setTimeout(() => {
                roleCard.remove();
                checkEmptyState();
            }, 300);
        } else {
            // Eğer kart bulunamazsa sayfayı yenile
            location.reload();
        }
    }
    
    /**
     * Boş durum kontrolü
     */
    function checkEmptyState() {
        const itemsGrid = document.querySelector('.items-grid');
        const roleCards = document.querySelectorAll('.item-card[data-role-id]');
        
        if (roleCards.length === 0 && itemsGrid) {
            itemsGrid.innerHTML = `
                <div class="empty-items">
                    <i class="fas fa-user-tag"></i>
                    <h3>Rol Bulunamadı</h3>
                    <p>Artık hiç rol kalmamış. Yeni rol oluşturmak ister misiniz?</p>
                    <a href="create.php" class="btn-action-primary">
                        <i class="fas fa-plus"></i>
                        Yeni Rol Oluştur
                    </a>
                </div>
            `;
        }
    }
    
    /**
     * Alert gösterme fonksiyonu
     */
    function showAlert(message, type = 'info') {
        // Mevcut alert'leri temizle
        document.querySelectorAll('.custom-alert').forEach(alert => alert.remove());
        
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const iconClass = type === 'success' ? 'fa-check-circle' : 
                         type === 'error' ? 'fa-exclamation-circle' : 
                         type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show custom-alert" role="alert" style="
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                max-width: 400px;
                background: var(--card-bg);
                border: 1px solid var(--border-1);
                color: var(--lighter-grey);
                border-radius: 8px;
                padding: 1rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            ">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas ${iconClass}" style="color: ${type === 'success' ? 'var(--turquase)' : type === 'error' ? 'var(--red)' : 'var(--gold)'};"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close" style="
                    position: absolute;
                    top: 0.5rem;
                    right: 0.5rem;
                    background: none;
                    border: none;
                    color: var(--light-grey);
                    font-size: 1.2rem;
                    cursor: pointer;
                    opacity: 0.7;
                " onclick="this.parentElement.remove()">&times;</button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        
        // 5 saniye sonra otomatik kapat
        setTimeout(() => {
            const alert = document.querySelector('.custom-alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(100%)';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    }
    
    /**
     * Arama formu iyileştirmeleri
     */
    function initSearchForm() {
        const searchInput = document.getElementById('search');
        const filtersForm = document.getElementById('filtersForm');
        
        if (searchInput && filtersForm) {
            // Enter tuşu ile arama
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filtersForm.submit();
                }
            });
            
            // Arama temizleme butonu
            if (searchInput.value.trim()) {
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.className = 'btn-action-secondary';
                clearBtn.innerHTML = '<i class="fas fa-times"></i>';
                clearBtn.title = 'Aramayı temizle';
                clearBtn.style.cssText = 'margin-left: 5px; padding: 0.5rem;';
                
                searchInput.parentNode.appendChild(clearBtn);
                
                clearBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    filtersForm.submit();
                });
            }
        }
    }
    
    /**
     * Kart hover efektleri
     */
    function initCardEffects() {
        document.querySelectorAll('.item-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }
    
    /**
     * Sayfa yüklenme animasyonları
     */
    function initPageAnimations() {
        const cards = document.querySelectorAll('.item-card');
        
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
    
    /**
     * Debug paneli toggle
     */
    function initDebugPanel() {
        const debugBtn = document.querySelector('a[href*="debug=1"]');
        if (debugBtn) {
            debugBtn.addEventListener('click', function(e) {
                if (window.location.href.includes('debug=1')) {
                    e.preventDefault();
                    window.location.href = window.location.href.replace(/[?&]debug=1/, '');
                }
            });
        }
    }
    
    /**
     * Tüm işlevleri başlat
     */
    function init() {
        try {
            initDeleteButtons();
            initCancelButtons();
            initConfirmDelete();
            initSearchForm();
            initCardEffects();
            initPageAnimations();
            initDebugPanel();
            
            console.log('Roles page JavaScript initialized successfully');
        } catch (error) {
            console.error('Error initializing roles page:', error);
        }
    }
    
    // Ana başlatma
    init();
    
    // Global erişim için window'a ekle
    window.RolesPage = {
        showAlert,
        closeModal,
        checkEmptyState,
        removeRoleCard,
        getCSRFToken
    };
});

/**
 * Sayfa yeniden yüklendiğinde modal'ın açık kalmasını önle
 */
window.addEventListener('beforeunload', function() {
    const modal = document.getElementById('deleteRoleModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
    
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.remove();
    }
    
    document.body.classList.remove('modal-open');
});

/**
 * Sayfa fokus aldığında modal durumunu kontrol et
 */
window.addEventListener('focus', function() {
    const modal = document.getElementById('deleteRoleModal');
    if (modal && modal.style.display === 'flex' && !modal.classList.contains('show')) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
});