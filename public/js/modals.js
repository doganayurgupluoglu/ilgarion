// public/js/modals.js
document.addEventListener('DOMContentLoaded', function () {
    console.log("Modals JS V2 Başlatıldı.");

    // --- Etkinlik Detay Sayfası Basit Resim Modalı (imageModal) ---
    const eventDetailModalElement = document.getElementById('imageModal');
    if (eventDetailModalElement) {
        const eventDetailModalImg = document.getElementById('eventDetailModalImage');
        const eventDetailCaptionText = document.getElementById('eventDetailCaption');
        const eventDetailCloseBtn = document.getElementById('closeEventDetailModalSpan');

        window.openEventDetailModal = function(imgElement) {
            if (eventDetailModalElement && eventDetailModalImg && eventDetailCaptionText && imgElement) {
                eventDetailModalImg.src = imgElement.src;
                eventDetailCaptionText.innerHTML = imgElement.alt;
                eventDetailModalElement.style.display = "block";
            } else {
                console.error("Etkinlik detay modalı için elementler/resim bulunamadı.");
            }
        };
        window.closeEventDetailModal = function() {
            if (eventDetailModalElement) eventDetailModalElement.style.display = "none";
        };
        if (eventDetailCloseBtn) eventDetailCloseBtn.onclick = closeEventDetailModal;
        eventDetailModalElement.addEventListener('click', function(event) { 
            if (event.target == eventDetailModalElement) closeEventDetailModal(); 
        });
        document.addEventListener('keydown', function(event) { 
            if (event.key === "Escape" && eventDetailModalElement.style.display === "block") closeEventDetailModal(); 
        });
    }

    // --- GALERİ VE HANGAR İÇİN GÜNCELLENMİŞ NAVİGASYONLU MODAL (galleryModal) ---
    const richNavModalElement = document.getElementById('galleryModal'); 
    if (richNavModalElement) {
        const modalImg = document.getElementById('galleryModalImage');
        
        // Yeni caption ve popover tetikleyici elementleri
        const modalUploaderTriggerSpan = document.getElementById('modalUploaderInfoTrigger'); // HTML'deki yeni ID
        const modalUploaderAvatarImg = document.getElementById('modalUploaderAvatar');
        const modalUploaderAvatarPlaceholder = document.getElementById('modalUploaderAvatarPlaceholder');
        const modalUploaderUsernameLink = document.getElementById('modalUploaderUsername');
        const modalPhotoDescriptionP = document.getElementById('modalPhotoDescription');
        const modalPhotoDateSmall = document.getElementById('modalPhotoDate');
        
        // Beğeni elementleri
        const modalLikeButton = document.getElementById('modalLikeButton'); // Giriş yapmış kullanıcılar için
        const modalLikeCountSpan = document.getElementById('modalLikeCount'); // Like butonundaki sayı
        const modalLikeIcon = modalLikeButton ? modalLikeButton.querySelector('.like-icon-modal') : null;
        const modalLikeCountStaticSpan = document.getElementById('modalLikeCountStatic'); // Giriş yapmamışlar için sayı

        const closeBtn = document.getElementById('closeGalleryModalSpan');
        const prevBtn = document.getElementById('prevGalleryModalButton');
        const nextBtn = document.getElementById('nextGalleryModalButton');
        
        let currentRichModalIndex = 0;
        let currentRichModalItems = []; // Bu, window.phpGalleryPhotos veya window.phpHangarShipDataForModal olacak
        
        // Rol öncelik sırası ve class'ları (popover.js'teki ile aynı olmalı)
        const rolePriority = ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];
        const roleUsernameClassesModal = {
            'admin': 'username-role-admin',
            'scg_uye': 'username-role-scg_uye',
            'ilgarion_turanis': 'username-role-ilgarion_turanis',
            'member': 'username-role-member',
            'dis_uye': 'username-role-dis_uye'
        };

        function displayRichModalImageAtIndex(index) {
            if (!currentRichModalItems || !currentRichModalItems[index]) {
                console.error("Zengin modal için resim verisi bulunamadı, index: " + index, currentRichModalItems);
                if(richNavModalElement) richNavModalElement.style.display = "none"; 
                return;
            }
            currentRichModalIndex = index;
            const itemData = currentRichModalItems[currentRichModalIndex];

            if (modalImg) {
                 modalImg.src = itemData.image_path_full;
                 modalImg.alt = itemData.photo_description || ('Resim ' + (currentRichModalIndex + 1));
            }

            // Yükleyen Kullanıcı Bilgileri ve Popover data attribute'ları
            if (modalUploaderTriggerSpan) {
                modalUploaderTriggerSpan.dataset.userId = itemData.uploader_user_id || '';
                modalUploaderTriggerSpan.dataset.username = itemData.uploader_username || 'Bilinmiyor';
                modalUploaderTriggerSpan.dataset.avatar = itemData.uploader_avatar_path_full || '';
                modalUploaderTriggerSpan.dataset.ingame = itemData.uploader_ingame_name || 'N/A';
                modalUploaderTriggerSpan.dataset.discord = itemData.uploader_discord_username || 'N/A';
                modalUploaderTriggerSpan.dataset.eventCount = String(itemData.uploader_event_count || '0');
                modalUploaderTriggerSpan.dataset.galleryCount = String(itemData.uploader_gallery_count || '0');
                modalUploaderTriggerSpan.dataset.roles = itemData.uploader_roles_list || '';

                // Yükleyen kullanıcı adı için rol bazlı renk class'ını ayarla
                modalUploaderTriggerSpan.className = 'caption-v2-uploader-trigger-wrapper user-info-trigger'; // Temel class'ları sıfırla
                const uploaderRoles = (itemData.uploader_roles_list || '').split(',').map(r => r.trim()).filter(r => r);
                
                for (const priorityRole of rolePriority) {
                    if (uploaderRoles.includes(priorityRole) && roleUsernameClassesModal[priorityRole]) {
                        modalUploaderTriggerSpan.classList.add(roleUsernameClassesModal[priorityRole]);
                        break; 
                    }
                }
            }

            if (modalUploaderAvatarImg && modalUploaderAvatarPlaceholder) {
                if (itemData.uploader_avatar_path_full) {
                    modalUploaderAvatarImg.src = itemData.uploader_avatar_path_full;
                    modalUploaderAvatarImg.style.display = 'block';
                    modalUploaderAvatarPlaceholder.style.display = 'none';
                } else {
                    modalUploaderAvatarImg.style.display = 'none';
                    modalUploaderAvatarPlaceholder.textContent = (itemData.uploader_username ? itemData.uploader_username.charAt(0).toUpperCase() : 'U');
                    modalUploaderAvatarPlaceholder.style.display = 'flex';
                }
            }
            if (modalUploaderUsernameLink) {
                modalUploaderUsernameLink.textContent = itemData.uploader_username || 'Bilinmiyor';
                modalUploaderUsernameLink.href = itemData.uploader_user_id ? `/public/view_profile.php?user_id=${itemData.uploader_user_id}` : '#';
            }
            if (modalPhotoDescriptionP) {
                modalPhotoDescriptionP.innerHTML = itemData.photo_description ? String(itemData.photo_description).replace(/\n/g, "<br>") : '<em>Açıklama yok.</em>';
            }
            if (modalPhotoDateSmall) {
                modalPhotoDateSmall.textContent = "Yüklenme: " + (itemData.uploaded_at_formatted || '');
            }

            // Beğeni Butonu ve Sayısı
            if (modalLikeButton) { // Giriş yapmış kullanıcılar için
                modalLikeButton.dataset.photoId = String(itemData.photo_id || ''); // Stringe çevirerek emin olalım
                modalLikeButton.classList.toggle('liked', itemData.user_has_liked || false);
                modalLikeButton.title = (itemData.user_has_liked ? 'Beğenmekten Vazgeç' : 'Beğen');
                if (modalLikeIcon) modalLikeIcon.className = `fas ${itemData.user_has_liked ? 'fa-heart-crack' : 'fa-heart'} like-icon-modal`;
                if (modalLikeCountSpan) modalLikeCountSpan.textContent = String(itemData.like_count || 0);
            } else if (modalLikeCountStaticSpan) { // Giriş yapmamış kullanıcılar için
                 if (modalLikeCountStaticSpan) modalLikeCountStaticSpan.textContent = String(itemData.like_count || 0);
            }

            updateRichNavModalButtons();
        }

        function updateRichNavModalButtons() {
            if (!currentRichModalItems || currentRichModalItems.length === 0) return;
            if (prevBtn) prevBtn.style.display = (currentRichModalIndex > 0) ? "block" : "none";
            if (nextBtn) nextBtn.style.display = (currentRichModalIndex < currentRichModalItems.length - 1) ? "block" : "none";
        }

        function showPrevRichModalImage() { if (currentRichModalIndex > 0) displayRichModalImageAtIndex(currentRichModalIndex - 1); }
        function showNextRichModalImage() { if (currentRichModalIndex < currentRichModalItems.length - 1) displayRichModalImageAtIndex(currentRichModalIndex + 1); }
        
        window.openGalleryModal = function(index) { 
            if (typeof window.phpGalleryPhotos !== 'undefined' && Array.isArray(window.phpGalleryPhotos)) {
                currentRichModalItems = window.phpGalleryPhotos; 
                if (richNavModalElement && currentRichModalItems.length > 0 && typeof currentRichModalItems[index] !== 'undefined') {
                    displayRichModalImageAtIndex(index);
                    richNavModalElement.style.display = "flex"; 
                } else if (richNavModalElement) { console.warn("Galeri için resim verisi boş veya index geçersiz."); }
            } else { console.error("window.phpGalleryPhotos bulunamadı veya dizi değil."); }
        };
        
        window.openHangarShipModal = function(index) { 
            if (typeof window.phpHangarShipDataForModal !== 'undefined' && Array.isArray(window.phpHangarShipDataForModal)) {
                currentRichModalItems = window.phpHangarShipDataForModal;
                 if (richNavModalElement && currentRichModalItems.length > 0 && typeof currentRichModalItems[index] !== 'undefined') {
                    displayRichModalImageAtIndex(index); // Hangar için de aynı display fonksiyonunu kullanıyoruz
                    richNavModalElement.style.display = "flex";
                } else if (richNavModalElement) { console.warn("Hangar için resim verisi boş veya index geçersiz."); }
            } else { console.error("window.phpHangarShipDataForModal bulunamadı veya dizi değil."); }
        };

        window.closeRichNavModal = function() { if (richNavModalElement) richNavModalElement.style.display = "none"; };

        if (closeBtn) closeBtn.onclick = closeRichNavModal;
        if (prevBtn) prevBtn.onclick = showPrevRichModalImage;
        if (nextBtn) nextBtn.onclick = showNextRichModalImage;
        richNavModalElement.addEventListener('click', function(event) { 
            if (event.target == richNavModalElement) closeRichNavModal(); 
        });
        document.addEventListener('keydown', function(event) { 
            if (richNavModalElement && richNavModalElement.style.display === "flex") { 
                if (event.key === "Escape") closeRichNavModal(); 
                else if (event.key === "ArrowLeft") showPrevRichModalImage(); 
                else if (event.key === "ArrowRight") showNextRichModalImage(); 
            }
        });

        // Modal içindeki beğeni butonu için AJAX işleyicisi
        if (modalLikeButton) {
            modalLikeButton.addEventListener('click', function() {
                const photoId = this.dataset.photoId;
                if (!photoId) {
                    console.error("Modal like button photoId not found!");
                    return;
                }
                
                fetch('../src/actions/handle_gallery_like.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'photo_id=' + encodeURIComponent(photoId)
                })
                .then(response => {
                    if (!response.ok) { return response.json().then(err => { throw err; });}
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Modal içindeki beğeni durumunu ve sayısını güncelle
                        if(modalLikeCountSpan) modalLikeCountSpan.textContent = data.like_count;
                        this.classList.toggle('liked', data.action_taken === 'liked');
                        this.title = (data.action_taken === 'liked' ? 'Beğenmekten Vazgeç' : 'Beğen');
                        if (modalLikeIcon) modalLikeIcon.className = `fas ${data.action_taken === 'liked' ? 'fa-heart-crack' : 'fa-heart'} like-icon-modal`;
                        
                        // Ana sayfadaki (gallery.php) kartın beğeni durumunu da güncelle
                        const cardLikeButton = document.querySelector(`.like-button-gallery[data-photo-id="${photoId}"]`);
                        if (cardLikeButton) {
                            const cardLikeCountSpan = cardLikeButton.querySelector('span.like-count');
                            const cardLikeIcon = cardLikeButton.querySelector('i.fas');
                            if (cardLikeCountSpan) cardLikeCountSpan.textContent = data.like_count;
                            cardLikeButton.classList.toggle('liked', data.action_taken === 'liked');
                            cardLikeButton.title = (data.action_taken === 'liked' ? 'Beğenmekten Vazgeç' : 'Beğen');
                           if(cardLikeIcon) cardLikeIcon.className = `fas ${data.action_taken === 'liked' ? 'fa-heart-crack' : 'fa-heart'}`;
                        }

                        // window.phpGalleryPhotos dizisindeki ilgili öğeyi de güncelle (önemli!)
                        if (window.phpGalleryPhotos && Array.isArray(window.phpGalleryPhotos)) {
                            const itemIndexInJsData = window.phpGalleryPhotos.findIndex(item => String(item.photo_id) === String(photoId));
                            if(itemIndexInJsData > -1) {
                                window.phpGalleryPhotos[itemIndexInJsData].like_count = data.like_count;
                                window.phpGalleryPhotos[itemIndexInJsData].user_has_liked = (data.action_taken === 'liked');
                            }
                        }
                         // Eğer hangar modalı için ayrı bir JS dizisi varsa (örn: window.phpHangarShipDataForModal)
                         // ve o da beğeni içeriyorsa, onu da benzer şekilde güncellemek gerekebilir.
                         // Şimdilik sadece gallery.php için yapıyoruz.

                    } else {
                        alert("Hata: " + (data.error || "Modal beğeni işlemi sırasında bir sorun oluştu."));
                    }
                })
                .catch(error => {
                    console.error('Fetch Hatası (Modal Galeri Beğeni):', error);
                    alert("Hata: " + (error.error || "Bir ağ hatası oluştu."));
                });
            });
        }

        // Bu fonksiyon, ana sayfadaki (gallery.php) beğeni butonu tıklandığında çağrılabilir.
        window.updateLikeStatusInModal = function(photoId, newLikeCount, isLikedNow) {
            // Modal açık mı ve doğru fotoğraf mı gösteriliyor?
            if (richNavModalElement.style.display === "flex" && 
                currentRichModalItems && 
                currentRichModalItems[currentRichModalIndex] &&
                String(currentRichModalItems[currentRichModalIndex].photo_id) === String(photoId)) {
                
                if (modalLikeButton && modalLikeCountSpan) {
                    modalLikeCountSpan.textContent = newLikeCount;
                    modalLikeButton.classList.toggle('liked', isLikedNow);
                    modalLikeButton.title = isLikedNow ? 'Beğenmekten Vazgeç' : 'Beğen';
                    if (modalLikeIcon) modalLikeIcon.className = `fas ${isLikedNow ? 'fa-heart-crack' : 'fa-heart'} like-icon-modal`;
                }
                 // window.phpGalleryPhotos dizisindeki ilgili öğeyi de güncelle (tekrar, tutarlılık için)
                if (window.phpGalleryPhotos && Array.isArray(window.phpGalleryPhotos)) {
                    const itemIndexInJsData = window.phpGalleryPhotos.findIndex(item => String(item.photo_id) === String(photoId));
                    if(itemIndexInJsData > -1) {
                        window.phpGalleryPhotos[itemIndexInJsData].like_count = newLikeCount;
                        window.phpGalleryPhotos[itemIndexInJsData].user_has_liked = isLikedNow;
                    }
                }
            }
        };

    } // richNavModalElement kontrolü sonu
});