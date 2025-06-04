<!-- User Popover Component - public/forum/includes/user_popover.php -->
<div id="userPopover" class="user-popover" style="display: none;">
    <div class="popover-content">
        <div class="popover-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Yükleniyor...</span>
        </div>
        
        <div class="popover-user-info" style="display: none;">
            <div class="popover-header">
                <div class="user-avatar">
                    <img src="" alt="Kullanıcı Avatarı" class="user-img">
                </div>
                <div class="user-basic-info">
                    <h4 class="user-name"></h4>
                    <div class="user-role"></div>
                    <div class="user-status">
                        <span class="online-indicator"></span>
                        <span class="status-text"></span>
                    </div>
                </div>
                <button class="popover-close" onclick="closeUserPopover()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="popover-body">
                <div class="user-details">
                    <div class="detail-item">
                        <span class="detail-label">Oyun İçi İsim:</span>
                        <span class="detail-value ingame-name"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Discord:</span>
                        <span class="detail-value discord-username"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Üyelik Tarihi:</span>
                        <span class="detail-value join-date"></span>
                    </div>
                </div>
                
                <div class="forum-stats">
                    <div class="stat-item">
                        <div class="stat-number forum-topics">0</div>
                        <div class="stat-label">Konu</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number forum-posts">0</div>
                        <div class="stat-label">Gönderi</div>
                    </div>
                </div>
            </div>
            
            <div class="popover-footer">
                <a href="#" class="btn-view-profile">
                    <i class="fas fa-user"></i>
                    Profilini Görüntüle
                </a>
                <button class="btn-send-message" style="display: none;">
                    <i class="fas fa-envelope"></i>
                    Mesaj Gönder
                </button>
            </div>
        </div>
        
        <div class="popover-error" style="display: none;">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Kullanıcı bilgileri yüklenemedi.</span>
        </div>
    </div>
</div>

<style>
.user-popover {
    position: fixed;
    z-index: 9999;
    min-width: 300px;
    max-width: 350px;
    background: var(--card-bg-4);
    border: 1px solid var(--border-1-featured);
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    font-family: var(--font);
    color: var(--lighter-grey);
    opacity: 0;
    transform: scale(0.9);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
}

.user-popover.show {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

.popover-content {
    padding: 0;
    border-radius: 8px;
    overflow: hidden;
}

.popover-loading {
    padding: 20px;
    text-align: center;
    color: var(--light-grey);
}

.popover-loading i {
    margin-right: 8px;
}

.popover-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    background: var(--transparent-gold);
    border-bottom: 1px solid var(--border-1);
    position: relative;
}

.user-avatar {
    flex-shrink: 0;
}

.user-img {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 2px solid var(--light-gold);
    object-fit: cover;
}

.user-basic-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--lighter-grey);
    word-break: break-word;
}

.user-role {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 6px;
    padding: 2px 8px;
    border-radius: 12px;
    background: var(--card-bg-4);
    border: 1px solid var(--border-1-featured);
    color: var(--gold);
    display: inline-block;
}

.user-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
}

.online-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--light-grey);
}

.online-indicator.online {
    background: var(--turquase);
    box-shadow: 0 0 6px var(--turquase);
}

.popover-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: var(--light-grey);
    border: 1px solid var(--border-1);
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.popover-close:hover {
    background: var(--transparent-red);
    color: var(--red);
    border: 1px solid var(--red);
}

.popover-body {
    padding: 15px;
}

.user-details {
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.85rem;
}

.detail-label {
    color: var(--light-grey);
    font-weight: 500;
}

.detail-value {
    color: var(--lighter-grey);
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

.forum-stats {
    display: flex;
    justify-content: space-around;
    padding: 10px 0;
    border-top: 1px solid var(--border-1);
    border-bottom: 1px solid var(--border-1);
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--gold);
    font-family: var(--font);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--light-grey);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.popover-footer {
    padding: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-view-profile,
.btn-send-message {
    flex: 1;
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    text-align: center;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-family: var(--font);
}

.btn-view-profile {
    background: var(--gold);
    color: var(--charcoal);
}

.btn-view-profile:hover {
    background: var(--light-gold);
    text-decoration: none;
    color: var(--charcoal);
}

.btn-send-message {
    background: transparent;
    color: var(--turquase);
    border: 1px solid var(--turquase);
}

.btn-send-message:hover {
    background: var(--turquase);
    color: var(--charcoal);
    transform: translateY(-1px);
}

.popover-error {
    padding: 20px;
    text-align: center;
    color: var(--red);
}

.popover-error i {
    margin-right: 8px;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .user-popover {
        min-width: 280px;
        max-width: 90vw;
    }
    
    .popover-header {
        padding: 12px;
    }
    
    .popover-body {
        padding: 12px;
    }
    
    .popover-footer {
        padding: 12px;
        flex-direction: column;
    }
    
    .btn-view-profile,
    .btn-send-message {
        flex: none;
        width: 100%;
    }
}

/* Animation for popover arrow (optional) */
.user-popover::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 6px 6px 6px;
    border-color: transparent transparent var(--border-1-featured) transparent;
}

.user-popover.bottom::before {
    top: -6px;
    border-width: 0 6px 6px 6px;
    border-color: transparent transparent var(--border-1-featured) transparent;
}

.user-popover.top::before {
    top: auto;
    bottom: -6px;
    border-width: 6px 6px 0 6px;
    border-color: var(--border-1-featured) transparent transparent transparent;
}
</style>

<script>
// ✅ DÜZELTEN JAVASCRIPT KODU - BURAYA YAPIŞTIRILACAK
let userPopoverTimeout;
let currentPopoverUserId = null;
let popoverVisible = false;

// Düzeltilmiş tarih formatlama fonksiyonu
function formatDate(dateString) {
    try {
        // MySQL datetime formatını parse et: "2025-06-03 17:32:08"
        let date;
        
        if (dateString.includes(' ')) {
            // MySQL datetime format: "2025-06-03 17:32:08"
            date = new Date(dateString.replace(' ', 'T')); // ISO formatına çevir
        } else {
            // Zaten ISO format
            date = new Date(dateString);
        }
        
        // Geçerli tarih kontrolü
        if (isNaN(date.getTime())) {
            console.warn('Invalid date:', dateString);
            return 'Geçersiz tarih';
        }
        
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        
        return date.toLocaleDateString('tr-TR', options);
    } catch (error) {
        console.error('Date formatting error:', error, 'for date:', dateString);
        return 'Tarih formatı hatası';
    }
}

// User popover gösterme - sadece tıklama ile
function showUserPopover(element, userId) {
    clearTimeout(userPopoverTimeout);
    
    if (currentPopoverUserId === userId && popoverVisible) {
        return; // Zaten açık
    }
    
    const popover = document.getElementById('userPopover');
    const loadingDiv = popover.querySelector('.popover-loading');
    const userInfoDiv = popover.querySelector('.popover-user-info');
    const errorDiv = popover.querySelector('.popover-error');
    
    // Reset popover state
    loadingDiv.style.display = 'block';
    userInfoDiv.style.display = 'none';
    errorDiv.style.display = 'none';
    
    // Position popover
    positionPopover(element, popover);
    
    // Show popover
    popover.style.display = 'block';
    setTimeout(() => {
        popover.classList.add('show');
    }, 10);
    
    popoverVisible = true;
    currentPopoverUserId = userId;
    
    // Fetch user data
    fetchUserPopoverData(userId);
}

// Popover kapatma
function closeUserPopover() {
    clearTimeout(userPopoverTimeout);
    const popover = document.getElementById('userPopover');
    popover.classList.remove('show');
    
    setTimeout(() => {
        popover.style.display = 'none';
        popoverVisible = false;
        currentPopoverUserId = null;
    }, 200);
}

// Popover konumlandırma
function positionPopover(element, popover) {
    const rect = element.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;
    
    let top, left;
    
    // Horizontal position - merkez
    left = rect.left + (rect.width / 2) - 175; // 350px width / 2 = 175
    
    // Ekrandan taşma kontrolü
    if (left < 10) {
        left = 10;
    } else if (left + 350 > viewportWidth - 10) {
        left = viewportWidth - 360;
    }
    
    // Vertical position
    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;
    
    if (spaceBelow >= 300 || spaceBelow > spaceAbove) {
        // Show below
        top = rect.bottom + 10;
        popover.className = 'user-popover bottom';
    } else {
        // Show above
        top = rect.top - 10;
        popover.className = 'user-popover top';
        popover.style.transform = 'translateY(-100%)';
    }
    
    popover.style.left = left + 'px';
    popover.style.top = top + 'px';
}

// User data getirme
async function fetchUserPopoverData(userId) {
    try {
        const response = await fetch(`/src/api/get_user_popover_data.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            populateUserPopover(data.user);
        } else {
            showPopoverError(data.message || 'Kullanıcı bilgileri yüklenemedi');
        }
    } catch (error) {
        console.error('Error fetching user data:', error);
        showPopoverError('Ağ hatası oluştu');
    }
}

// Popover doldurma
function populateUserPopover(userData) {
    const popover = document.getElementById('userPopover');
    const loadingDiv = popover.querySelector('.popover-loading');
    const userInfoDiv = popover.querySelector('.popover-user-info');
    
    // Avatar
    const avatarImg = userInfoDiv.querySelector('.user-img');
    avatarImg.src = userData.avatar_path || '/assets/logo.png';
    avatarImg.alt = `${userData.username} Avatarı`;
    
    // User info
    userInfoDiv.querySelector('.user-name').textContent = userData.username;
    userInfoDiv.querySelector('.user-role').textContent = userData.primary_role_name || 'Üye';
    userInfoDiv.querySelector('.user-role').style.color = userData.primary_role_color || '#bd912a';
    
    // Online status
    const onlineIndicator = userInfoDiv.querySelector('.online-indicator');
    const statusText = userInfoDiv.querySelector('.status-text');
    if (userData.is_online) {
        onlineIndicator.classList.add('online');
        statusText.textContent = 'Çevrimiçi';
    } else {
        onlineIndicator.classList.remove('online');
        statusText.textContent = 'Çevrimdışı';
    }
    
    // User details
    userInfoDiv.querySelector('.ingame-name').textContent = userData.ingame_name || 'Belirtilmemiş';
    userInfoDiv.querySelector('.discord-username').textContent = userData.discord_username || 'Belirtilmemiş';
    
    // ✅ Düzeltilmiş tarih formatı
    userInfoDiv.querySelector('.join-date').textContent = formatDate(userData.created_at);
    
    // Forum stats
    userInfoDiv.querySelector('.forum-topics').textContent = userData.forum_topics || 0;
    userInfoDiv.querySelector('.forum-posts').textContent = userData.forum_posts || 0;
    
    // Profile link
    const profileLink = userInfoDiv.querySelector('.btn-view-profile');
    profileLink.href = `/public/view_profile.php?user_id=${userData.id}`;
    
    // Message button
    const messageBtn = userInfoDiv.querySelector('.btn-send-message');
    if (userData.can_message) {
        messageBtn.style.display = 'flex';
    } else {
        messageBtn.style.display = 'none';
    }
    
    // Show user info, hide loading
    loadingDiv.style.display = 'none';
    userInfoDiv.style.display = 'block';
}

// Hata gösterme
function showPopoverError(message = 'Kullanıcı bilgileri yüklenemedi.') {
    const popover = document.getElementById('userPopover');
    const loadingDiv = popover.querySelector('.popover-loading');
    const errorDiv = popover.querySelector('.popover-error');
    
    loadingDiv.style.display = 'none';
    errorDiv.style.display = 'block';
    
    const errorSpan = errorDiv.querySelector('span');
    if (errorSpan) {
        errorSpan.textContent = message;
    }
}

// ✅ Düzeltilmiş Event Listeners - Sadece tıklama ve dışına tıklama
document.addEventListener('DOMContentLoaded', function() {
    // User link click handlers - sadece tıklama ile aç
    document.addEventListener('click', function(e) {
        const userLink = e.target.closest('.user-link');
        if (userLink) {
            e.preventDefault();
            const userId = userLink.getAttribute('data-user-id');
            if (userId) {
                showUserPopover(userLink, userId);
            }
        }
    });
    
    // ✅ Dışına tıklama ile kapat
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('userPopover');
        const userLink = e.target.closest('.user-link');
        const closeBtn = e.target.closest('.popover-close');
        
        // Close button'a tıklandıysa kapat
        if (closeBtn) {
            closeUserPopover();
            return;
        }
        
        // Popover dışına tıklandıysa ve user link değilse kapat
        if (!popover.contains(e.target) && !userLink && popoverVisible) {
            closeUserPopover();
        }
    });
    
    // ✅ Escape tuşu ile kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popoverVisible) {
            closeUserPopover();
        }
    });
});

// Global fonksiyonlar
window.closeUserPopover = closeUserPopover;
</script>