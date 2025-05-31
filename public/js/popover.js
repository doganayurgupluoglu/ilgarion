// public/js/popover.js
document.addEventListener('DOMContentLoaded', function () {
    console.log("Popover JS V2 Başlatıldı.");
    const userInfoPopover = document.getElementById('userInfoPopover'); // Yeni HTML'deki ID ile aynı

    if (!userInfoPopover) {
        console.warn("Popover HTML elementi (#userInfoPopover) sayfada bulunamadı.");
        return;
    }

    // Yeni popover içindeki elementler
    const popoverAvatarImg = document.getElementById('popoverAvatarV2');
    const popoverAvatarPlaceholder = document.getElementById('popoverAvatarPlaceholderV2');
    const popoverUsernameAnchor = document.getElementById('popoverUsernameV2');
    const popoverUserRolesDiv = document.getElementById('popoverUserRolesV2');
    const popoverIngameNameStrong = document.getElementById('popoverIngameNameV2');
    const popoverDiscordStrong = document.getElementById('popoverDiscordUsernameV2');
    const popoverEventCountStrong = document.getElementById('popoverEventCountV2');
    const popoverGalleryCountStrong = document.getElementById('popoverGalleryCountV2');
    const popoverAvatarLink = document.getElementById('popoverAvatarLinkV2');
    const popoverProfileLinkFooter = document.getElementById('popoverProfileLinkV2');

    let popoverTimeout;

    // Rol isimlerini daha okunaklı hale getirmek için bir map
    const roleDisplayNames = {
        'admin': 'Yönetici',
        'member': 'Üye',
        'scg_uye': 'SCG Üyesi',
        'ilgarion_turanis': 'Ilgarion Turanis',
        'dis_uye': 'Dış Üye'
    };

    // Kullanıcı adı için renk class'larını belirleyen map
    const roleUsernameClasses = {
        'admin': 'username-role-admin',
        'scg_uye': 'username-role-scg_uye',
        'ilgarion_turanis': 'username-role-ilgarion_turanis',
        'member': 'username-role-member',
        'dis_uye': 'username-role-dis_uye'
    };
    // En öncelikli rolden en az öncelikliye doğru sıralama (renklendirme için)
    const rolePriority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];


    document.body.addEventListener('mouseenter', function(event) {
        const triggerElement = event.target.closest('.user-info-trigger');
        if (!triggerElement) return;

        clearTimeout(popoverTimeout);
        const userData = triggerElement.dataset;

        if (!popoverUsernameAnchor || !popoverIngameNameStrong || !popoverUserRolesDiv) {
            console.error("Popover V2 iç HTML elementlerinden bazıları bulunamadı!");
            return;
        }
        
        // Kullanıcı Adı ve Profili Linkleri
        const username = userData.username || 'N/A';
        popoverUsernameAnchor.textContent = username;
        const profileUrl = userData.userId ? `/public/view_profile.php?user_id=${userData.userId}` : '#';
        popoverUsernameAnchor.href = profileUrl;
        if (popoverAvatarLink) popoverAvatarLink.href = profileUrl;
        if (popoverProfileLinkFooter) popoverProfileLinkFooter.href = profileUrl;

        // Temel Bilgiler
        if (popoverIngameNameStrong) popoverIngameNameStrong.textContent = userData.ingame || 'Belirtilmemiş';
        if (popoverDiscordStrong) popoverDiscordStrong.textContent = userData.discord || 'Belirtilmemiş';
        if (popoverEventCountStrong) popoverEventCountStrong.textContent = userData.eventCount || '0';
        if (popoverGalleryCountStrong) popoverGalleryCountStrong.textContent = userData.galleryCount || '0';

        // Avatar
        if (popoverAvatarImg && popoverAvatarPlaceholder) {
            if (userData.avatar && userData.avatar !== '') {
                popoverAvatarImg.src = userData.avatar;
                popoverAvatarImg.style.display = 'block';
                popoverAvatarPlaceholder.style.display = 'none';
            } else {
                popoverAvatarImg.style.display = 'none';
                popoverAvatarPlaceholder.textContent = (username ? username.charAt(0).toUpperCase() : 'U');
                popoverAvatarPlaceholder.style.display = 'flex';
            }
        }
        
        // Kullanıcı Rolleri ve Renklendirme
        popoverUserRolesDiv.innerHTML = ''; // Önceki rolleri temizle
        // Kullanıcı adı için mevcut tüm rol class'larını temizle
        popoverUsernameAnchor.className = 'popover-username-link-v2'; // Sadece temel class kalsın

        let primaryRoleClassApplied = false;
        if (userData.roles) { // data-roles="admin,scg_uye" gibi gelecek
            const roles = userData.roles.split(',').map(role => role.trim()).filter(role => role);
            
            // Kullanıcı adı için en öncelikli role göre renk ata
            for (const priorityRole of rolePriority) {
                if (roles.includes(priorityRole) && roleUsernameClasses[priorityRole]) {
                    popoverUsernameAnchor.classList.add(roleUsernameClasses[priorityRole]);
                    primaryRoleClassApplied = true;
                    break; 
                }
            }
            // Eğer öncelikli rollerden hiçbiri yoksa, ama roller varsa, ilk bulduğunu ata (veya default)
            if (!primaryRoleClassApplied && roles.length > 0 && roleUsernameClasses[roles[0]]) {
                 // popoverUsernameAnchor.classList.add(roleUsernameClasses[roles[0]]);
                 // Veya varsayılan bir renk class'ı eklenebilir. Şimdilik CSS'teki varsayılan (gold) kalacak.
            }


            // Roller için badge'leri oluştur
            roles.forEach(roleKey => {
                const roleName = roleDisplayNames[roleKey] || roleKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                const badge = document.createElement('span');
                badge.className = `role-badge role-${roleKey}`; // CSS'te role-admin, role-scg_uye gibi class'lar olmalı
                badge.textContent = roleName;
                popoverUserRolesDiv.appendChild(badge);
            });
        }
        if (popoverUserRolesDiv.children.length === 0){
            const badge = document.createElement('span');
            badge.className = `role-badge role-default`; // Hiç rolü yoksa
            badge.textContent = "Rol Yok";
            popoverUserRolesDiv.appendChild(badge);
        }


        // Popover Konumlandırma
        const rect = triggerElement.getBoundingClientRect();
        let topPosition = rect.bottom + window.scrollY + 8; // Biraz daha boşluk
        let leftPosition = rect.left + window.scrollX + (rect.width / 2) - (userInfoPopover.offsetWidth / 2); // Ortalamaya çalış

        userInfoPopover.style.display = 'block'; // Önce göster ki boyutları alınabilsin
        
        // Ekran sınırları kontrolü (daha gelişmiş olabilir)
        const popoverRect = userInfoPopover.getBoundingClientRect(); // Güncel boyutları al
        if (leftPosition + popoverRect.width > window.innerWidth - 10) { // Sağdan taşma
            leftPosition = window.innerWidth - popoverRect.width - 10;
        }
        if (leftPosition < 10) { // Soldan taşma
            leftPosition = 10;
        }
        if (topPosition + popoverRect.height > (window.innerHeight + window.scrollY - 10)) { // Alttan taşma
            topPosition = rect.top + window.scrollY - popoverRect.height - 8; // Üste al
        }
         if (topPosition < window.scrollY + 10) { // Üstten taşma (nadiren olur ama)
            topPosition = window.scrollY + 10;
        }

        userInfoPopover.style.left = `${leftPosition}px`;
        userInfoPopover.style.top = `${topPosition}px`;
        // userInfoPopover.classList.add('open'); // Eğer animasyon için class kullanıyorsan

    }, true); 

    document.body.addEventListener('mouseleave', function(event) {
        const triggerElement = event.target.closest('.user-info-trigger');
        if (triggerElement) {
            popoverTimeout = setTimeout(() => {
                const isPopoverHovered = userInfoPopover.matches(':hover');
                if (!isPopoverHovered) {
                    userInfoPopover.style.display = 'none';
                    // userInfoPopover.classList.remove('open'); // Eğer animasyon için class kullanıyorsan
                }
            }, 200); // 200ms gecikme, kullanıcı popover'a geçebilsin diye
        }
    }, true); 

    userInfoPopover.addEventListener('mouseenter', function() {
        clearTimeout(popoverTimeout);
    });

    userInfoPopover.addEventListener('mouseleave', function() {
        this.style.display = 'none';
        // this.classList.remove('open'); // Eğer animasyon için class kullanıyorsan
    });

});