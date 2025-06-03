<!-- User Popover Component -->
<div id="userPopover" class="user-popover" style="display: none;">
    <div class="popover-content">
        <div class="popover-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Yükleniyor...</span>
        </div>
        
        <div class="popover-user-info" style="display: none;">
            <div class="popover-header">
                <div class="user-avatar">
                    <img src="" alt="Kullanıcı Avatarı" class="avatar-img">
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
    background: linear-gradient(135deg, var(--charcoal), var(--darker-gold-2));
    border: 1px solid var(--gold);
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
    background: rgba(189, 145, 42, 0.1);
    border-bottom: 1px solid var(--darker-gold-2);
    position: relative;
}

.user-avatar {
    flex-shrink: 0;
}

.avatar-img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 2px solid var(--gold);
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
    background: rgba(189, 145, 42, 0.2);
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
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.popover-close:hover {
    background: rgba(235, 0, 0, 0.2);
    color: var(--red);
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
    border-top: 1px solid var(--darker-gold-2);
    border-bottom: 1px solid var(--darker-gold-2);
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
    transform: translateY(-1px);
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
    border-color: transparent transparent var(--gold) transparent;
}

.user-popover.bottom::before {
    top: -6px;
    border-width: 0 6px 6px 6px;
    border-color: transparent transparent var(--gold) transparent;
}

.user-popover.top::before {
    top: auto;
    bottom: -6px;
    border-width: 6px 6px 0 6px;
    border-color: var(--gold) transparent transparent transparent;
}
</style>

<script>
let userPopoverTimeout;
let currentPopoverUserId = null;
let popoverVisible = false;

// User popover functionality
function showUserPopover(element, userId) {
    clearTimeout(userPopoverTimeout);
    
    if (currentPopoverUserId === userId && popoverVisible) {
        return; // Already showing this user's popover
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

function closeUserPopover() {
    const popover = document.getElementById('userPopover');
    popover.classList.remove('show');
    
    setTimeout(() => {
        popover.style.display = 'none';
        popoverVisible = false;
        currentPopoverUserId = null;
    }, 200);
}

function positionPopover(element, popover) {
    const rect = element.getBoundingClientRect();
    const popoverRect = popover.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;
    
    let top, left;
    
    // Calculate horizontal position
    left = rect.left + (rect.width / 2) - 175; // Center popover (350px / 2 = 175)
    
    // Ensure popover doesn't go off screen horizontally
    if (left < 10) {
        left = 10;
    } else if (left + 350 > viewportWidth - 10) {
        left = viewportWidth - 360;
    }
    
    // Calculate vertical position
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

async function fetchUserPopoverData(userId) {
    try {
        const response = await fetch(`/src/api/get_user_popover_data.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            populateUserPopover(data.user);
        } else {
            showPopoverError();
        }
    } catch (error) {
        console.error('Error fetching user data:', error);
        showPopoverError();
    }
}

function populateUserPopover(userData) {
    const popover = document.getElementById('userPopover');
    const loadingDiv = popover.querySelector('.popover-loading');
    const userInfoDiv = popover.querySelector('.popover-user-info');
    
    // Populate user info
    const avatarImg = userInfoDiv.querySelector('.avatar-img');
    avatarImg.src = userData.avatar_path || '/assets/logo.png';
    avatarImg.alt = `${userData.username} Avatarı`;
    
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
        // messageBtn.onclick = () => openMessageDialog(userData.id, userData.username);
    } else {
        messageBtn.style.display = 'none';
    }
    
    // Show user info, hide loading
    loadingDiv.style.display = 'none';
    userInfoDiv.style.display = 'block';
}

function showPopoverError() {
    const popover = document.getElementById('userPopover');
    const loadingDiv = popover.querySelector('.popover-loading');
    const errorDiv = popover.querySelector('.popover-error');
    
    loadingDiv.style.display = 'none';
    errorDiv.style.display = 'block';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    return date.toLocaleDateString('tr-TR', options);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // User link click handlers
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
    
    // Close popover when clicking outside
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('userPopover');
        const userLink = e.target.closest('.user-link');
        
        if (!popover.contains(e.target) && !userLink && popoverVisible) {
            closeUserPopover();
        }
    });
    
    // Close popover on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popoverVisible) {
            closeUserPopover();
        }
    });
    
    // Handle hover events for user links
    document.addEventListener('mouseenter', function(e) {
        const userLink = e.target.closest('.user-link');
        if (userLink) {
            clearTimeout(userPopoverTimeout);
            
            userPopoverTimeout = setTimeout(() => {
                const userId = userLink.getAttribute('data-user-id');
                if (userId && !popoverVisible) {
                    showUserPopover(userLink, userId);
                }
            }, 500); // Show after 500ms hover
        }
    }, true);
    
    document.addEventListener('mouseleave', function(e) {
        const userLink = e.target.closest('.user-link');
        if (userLink) {
            clearTimeout(userPopoverTimeout);
            
            // Don't auto-hide if mouse is over popover
            userPopoverTimeout = setTimeout(() => {
                const popover = document.getElementById('userPopover');
                if (!popover.matches(':hover') && popoverVisible) {
                    closeUserPopover();
                }
            }, 300);
        }
    }, true);
    
    // Keep popover open when hovering over it
    document.addEventListener('mouseenter', function(e) {
        if (e.target.closest('#userPopover')) {
            clearTimeout(userPopoverTimeout);
        }
    }, true);
    
    document.addEventListener('mouseleave', function(e) {
        if (e.target.closest('#userPopover')) {
            userPopoverTimeout = setTimeout(() => {
                closeUserPopover();
            }, 300);
        }
    }, true);
});
</script>