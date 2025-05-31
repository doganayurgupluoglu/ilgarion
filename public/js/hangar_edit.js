// public/js/hangar_edit.js (veya script.js içindeki ilgili bölüm)

document.addEventListener('DOMContentLoaded', function() {
    
    // --- Genel Adet Artırma/Azaltma Fonksiyonu ---
    function setupIndividualQuantityControls(controlContainer, minQty = 0) {
        const minusBtn = controlContainer.querySelector('.minus-btn');
        const plusBtn = controlContainer.querySelector('.plus-btn');
        // data-target-id artık span'in ID'si, data-input-id ise hidden input'un ID'si olacak
        const targetSpanId = minusBtn.dataset.targetId; 
        const targetInputId = minusBtn.dataset.inputId; 

        const targetSpan = document.getElementById(targetSpanId);
        const targetInput = document.getElementById(targetInputId);

        if (targetSpan && targetInput) {
            minusBtn.addEventListener('click', function() {
                let currentValue = parseInt(targetInput.value);
                if (currentValue > minQty) {
                    currentValue--;
                    targetInput.value = currentValue;
                    targetSpan.textContent = currentValue;
                }
            });
            plusBtn.addEventListener('click', function() {
                let currentValue = parseInt(targetInput.value);
                currentValue++;
                targetInput.value = currentValue;
                targetSpan.textContent = currentValue;
            });
        }
    }

    // Mevcut Hangar Adet Kontrolleri
    const updateHangarForm = document.getElementById('updateHangarForm');
    if (updateHangarForm) {
        updateHangarForm.querySelectorAll('.quantity-controls').forEach(controlDiv => {
            setupIndividualQuantityControls(controlDiv, 0); // Mevcut hangar için min 0
        });
    }

    // --- API Gemi Arama ve Sepet İşlemleri ---
    const searchButton = document.getElementById('searchShipApiButton');
    const searchInput = document.getElementById('ship_search_query');
    const searchResultsDiv = document.getElementById('shipSearchResults');
    
    const basketContainer = document.getElementById('basketItemsContainer');
    const addBasketButton = document.getElementById('addBasketButton');
    const addBasketToHangarForm = document.getElementById('addBasketToHangarForm');
    const basketItemCountSpan = document.getElementById('basketItemCount');

    let shipBasket = {}; // { api_id: { id, name, ..., quantity }, ... }

    if (searchButton && searchInput && searchResultsDiv && basketContainer && addBasketButton && addBasketToHangarForm && basketItemCountSpan) {
        // ... (performShipSearch ve addShipToBasket fonksiyonları aynı kalabilir) ...
        searchButton.addEventListener('click', performShipSearch);
        searchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                performShipSearch();
            }
        });

        function performShipSearch() {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                searchResultsDiv.innerHTML = '<p class="search-placeholder" style="color:var(--netflix-red, red);">Lütfen en az 2 karakter girin.</p>';
                return;
            }
            searchResultsDiv.innerHTML = '<p class="search-placeholder">Aranıyor...</p>';

            fetch(`../src/actions/search_ships_api.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchResultsDiv.innerHTML = ''; 
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(ship => {
                            const shipApiId = ship.id || (ship.name ? ship.name.replace(/\s+/g, '_') : `unknown_${Math.random().toString(36).substr(2, 9)}`);
                            let statusText = "";
                            let isSelectable = true;

                            if (typeof window.userHangarShipApiIds !== 'undefined' && window.userHangarShipApiIds.includes(String(shipApiId))) {
                                statusText = ' <small style="color:var(--light-turquase);">(Hangarında Mevcut)</small>';
                                isSelectable = false;
                            }
                            if (shipBasket[shipApiId]) {
                                statusText = ' <small style="color:var(--gold);">(Sepette)</small>';
                                isSelectable = false;
                            }

                            const shipCard = document.createElement('div');
                            shipCard.className = 'api-search-item-card';
                            if (!isSelectable) {
                                shipCard.classList.add('disabled-selection');
                                shipCard.style.opacity = '0.6';
                                shipCard.style.cursor = 'not-allowed';
                            } else {
                                shipCard.style.cursor = 'pointer';
                                shipCard.addEventListener('click', function() {
                                    addShipToBasket(shipApiId, ship);
                                });
                            }
                            const shipName = ship.name || 'İsimsiz Gemi';
                            const manufacturer = ship.manufacturer?.name || 'Bilinmeyen Üretici';
                            shipCard.innerHTML = `
                                <div class="search-item-image">
                                    <img src="${ship.media?.[0]?.source_url || 'https://via.placeholder.com/80x50.png?text=Gemi'}" alt="${shipName}">
                                </div>
                                <div class="search-item-details">
                                    <strong>${shipName}</strong>${statusText}<br>
                                    <small>${manufacturer} | Rol: ${ship.focus || 'N/A'} | Boyut: ${ship.size ? ship.size.charAt(0).toUpperCase() + ship.size.slice(1) : 'N/A'}</small>
                                </div>`;
                            searchResultsDiv.appendChild(shipCard);
                        });
                    } else {
                        searchResultsDiv.innerHTML = `<p class="search-placeholder">${data.message || 'Aramanızla eşleşen gemi bulunamadı.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('API Arama Hatası:', error);
                    searchResultsDiv.innerHTML = '<p class="search-placeholder" style="color:var(--netflix-red, red);">Arama sırasında bir hata oluştu.</p>';
                });
        }

        function addShipToBasket(shipApiId, shipData) {
            if (shipBasket[shipApiId] || (typeof window.userHangarShipApiIds !== 'undefined' && window.userHangarShipApiIds.includes(String(shipApiId)))) {
                return;
            }
            shipBasket[shipApiId] = {
                id: shipApiId, 
                name: shipData.name || 'Bilinmiyor',
                manufacturer: shipData.manufacturer?.name || null,
                focus: shipData.focus || null,
                size: shipData.size || null,
                image_url: shipData.media?.[0]?.source_url || null,
                quantity: 1 
            };
            renderBasket();
            performShipSearch(); 
        }
        
        // Sepetteki bir item'ın adetini ve gizli inputunu günceller
        function updateBasketItemQuantity(shipApiId, newQuantity) {
            const qty = Math.max(1, parseInt(newQuantity)); // Minimum 1 adet
            if (shipBasket[shipApiId]) {
                shipBasket[shipApiId].quantity = qty;
                // Gizli inputu veya span'i güncelle (renderBasket zaten yapıyor)
                renderBasket(); // Sepeti yeniden çizerek hem span'i hem de gizli input'u günceller
            }
        }

        function removeShipFromBasket(shipApiId) {
            delete shipBasket[shipApiId];
            renderBasket();
            performShipSearch();
        }

        function renderBasket() {
            basketContainer.innerHTML = '';
            let itemCount = 0;
            addBasketToHangarForm.querySelectorAll('input[type="hidden"][name^="selected_ships"]').forEach(input => input.remove()); // Önceki gizli inputları temizle

            for (const shipApiId in shipBasket) {
                if (shipBasket.hasOwnProperty(shipApiId)) {
                    itemCount++;
                    const ship = shipBasket[shipApiId];
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'basket-item';
                    
                    // Gizli inputları burada forma ekle
                    for (const prop in ship) {
                        if (ship.hasOwnProperty(prop)) {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = `selected_ships[${ship.id}][${prop}]`;
                            hiddenInput.value = ship[prop];
                            addBasketToHangarForm.appendChild(hiddenInput);
                        }
                    }
                    
                    // Görünen sepet öğesini oluştur
                    itemDiv.innerHTML = `
                        <span class="basket-item-name">${ship.name} (${ship.manufacturer || 'N/A'})</span>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn minus-btn" data-basket-id="${shipApiId}">-</button>
                            <span id="basket_quantity_span_${shipApiId}" class="quantity-display">${ship.quantity}</span>
                            <button type="button" class="quantity-btn plus-btn" data-basket-id="${shipApiId}">+</button>
                        </div>
                        <button type="button" class="remove-basket-item-btn" data-basket-id="${shipApiId}">&times;</button>
                    `;
                    basketContainer.appendChild(itemDiv);
                }
            }

            if (itemCount === 0) {
                basketContainer.innerHTML = '<p class="empty-basket-message">Sepete eklemek için arama sonuçlarından gemi seçin.</p>';
                addBasketButton.style.display = 'none';
            } else {
                addBasketButton.style.display = 'block'; // Veya inline-block
            }
            basketItemCountSpan.textContent = itemCount;

            // Sepetteki +/- butonları için olay dinleyicileri (span'i ve gizli inputu güncelleyecek)
            basketContainer.querySelectorAll('.minus-btn').forEach(btn => {
                btn.onclick = function() { 
                    const id = this.dataset.basketId;
                    updateBasketItemQuantity(id, shipBasket[id].quantity - 1);
                };
            });
            basketContainer.querySelectorAll('.plus-btn').forEach(btn => {
                btn.onclick = function() { 
                    const id = this.dataset.basketId;
                    updateBasketItemQuantity(id, shipBasket[id].quantity + 1);
                };
            });
            // Span'e tıklanarak doğrudan düzenleme istenirse buraya eklenebilir, şimdilik sadece +/-
            basketContainer.querySelectorAll('.remove-basket-item-btn').forEach(btn => {
                btn.onclick = function() { removeShipFromBasket(this.dataset.basketId); };
            });
        }
        
        addBasketToHangarForm.addEventListener('submit', function(e) {
            // Gizli inputlar zaten renderBasket içinde güncelleniyor ve forma ekleniyor.
            // Burada ekstra bir şey yapmaya gerek kalmayabilir.
            if (Object.keys(shipBasket).length === 0) {
                e.preventDefault(); // Sepet boşsa gönderme
                alert("Lütfen hangara eklemek için en az bir gemi seçin.");
            }
        });
        renderBasket(); // İlk yüklemede sepeti ayarla
    }
});

// public/js/script.js

document.addEventListener('DOMContentLoaded', function () {

    // --- Etkinlik Detay Sayfası Basit Resim Modalı (imageModal) ---
    const eventDetailModalElement = document.getElementById('imageModal');
    if (eventDetailModalElement) {
        const eventDetailModalImg = document.getElementById('eventDetailModalImage'); // HTML'deki ID ile eşleşmeli (view_profile'da eventDetailModalImage kullanmıştık)
        const eventDetailCaptionText = document.getElementById('eventDetailCaption'); // HTML'deki ID ile eşleşmeli
        const eventDetailCloseBtn = document.getElementById('closeEventDetailModalSpan'); // HTML'deki ID

        window.openEventDetailModal = function(imgElement) {
            if (eventDetailModalElement && eventDetailModalImg && eventDetailCaptionText && imgElement) {
                eventDetailModalImg.src = imgElement.src;
                eventDetailCaptionText.innerHTML = imgElement.alt;
                eventDetailModalElement.style.display = "block";
            } else {
                console.error("Etkinlik detay modalı için gerekli elementler veya tıklanan resim bulunamadı.");
            }
        };

        window.closeEventDetailModal = function() {
            if (eventDetailModalElement) {
                eventDetailModalElement.style.display = "none";
            }
        };

        if (eventDetailCloseBtn) {
            eventDetailCloseBtn.onclick = closeEventDetailModal;
        }

        eventDetailModalElement.addEventListener('click', function(event) {
            if (event.target == eventDetailModalElement) {
                closeEventDetailModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape" && eventDetailModalElement.style.display === "block") {
                closeEventDetailModal();
            }
        });
    }

    // --- Galeri Sayfası ve Hangar Görüntüleme Sayfası İçin Ortak Navigasyonlu Modal (galleryModal) ---
    const richNavModalElement = document.getElementById('galleryModal'); // Hem gallery.php hem de view_hangar.php bu ID'yi kullanacak

    if (richNavModalElement) {
        const modalImg = document.getElementById('galleryModalImage');
        const captionText = document.getElementById('galleryCaptionText');
        const closeBtn = document.getElementById('closeGalleryModalSpan');
        const prevBtn = document.getElementById('prevGalleryModalButton');
        const nextBtn = document.getElementById('nextGalleryModalButton');

        let currentRichModalIndex = 0;
        let currentRichModalItems = []; // O anki sayfadan gelen veriyle dolacak (phpGalleryPhotos veya phpHangarShipDataForModal)

        // Bu fonksiyon, photoData objesinden caption HTML'ini oluşturur
        // photoData'nın beklenen alanları: uploader_avatar_path_full, uploader_username, photo_description, uploaded_at_formatted
        function buildRichModalCaptionHTML(photoData) {
            if (!photoData) return "";
            let captionHTML = '<div class="caption-flex-container">';
            captionHTML += '<div class="caption-left">';
            captionHTML += '<div class="caption-uploader-info">';
            if (photoData.uploader_avatar_path_full) {
                captionHTML += `<img src="${photoData.uploader_avatar_path_full}" alt="${photoData.uploader_username || ''}" style="width:30px; height:30px; border-radius:50%; margin-right:10px; object-fit:cover;">`;
            } else {
                captionHTML += `<div class="avatar-placeholder" style="width:30px; height:30px; border-radius:50%; background-color:#555; display:flex; align-items:center; justify-content:center; margin-right:10px; color:#fff; font-weight:bold; font-size:0.9em;">${(photoData.uploader_username || 'U').charAt(0).toUpperCase()}</div>`;
            }
            captionHTML += `<span><strong>${photoData.uploader_username || 'Bilinmiyor'}</strong></span>`;
            captionHTML += '</div>';
            if (photoData.photo_description) {
                captionHTML += `<p class="caption-description" style="font-size:0.9em; line-height:1.4; margin-bottom:5px; word-wrap:break-word;">${String(photoData.photo_description).replace(/\n/g, "<br>")}</p>`;
            }
            captionHTML += '</div>';
            captionHTML += '<div class="caption-right" style="text-align:right; white-space:nowrap;">';
            captionHTML += `<small class="caption-date" style="font-size:0.8em; color:#bbb;">${photoData.uploaded_at_formatted || 'Tarih Yok'}</small>`;
            captionHTML += '</div>';
            captionHTML += '</div>';
            return captionHTML;
        }

        function displayRichModalImageAtIndex(index) {
            if (!currentRichModalItems || !currentRichModalItems[index]) {
                console.error("Zengin modal için resim verisi bulunamadı, index: " + index, currentRichModalItems);
                richNavModalElement.style.display = "none"; // Hata varsa modalı kapat
                return;
            }
            currentRichModalIndex = index;
            const itemData = currentRichModalItems[currentRichModalIndex];

            if (modalImg) {
                 modalImg.src = itemData.image_path_full;
                 // photo_description hem galeri hem de hangar verisinde var (hangar için gemi adı+üretici yaptık)
                 modalImg.alt = itemData.photo_description || ('Resim ' + (currentRichModalIndex + 1));
            }
            if (captionText) captionText.innerHTML = buildRichModalCaptionHTML(itemData);
            
            updateRichNavModalButtons();
        }

        function updateRichNavModalButtons() {
            if (!currentRichModalItems || currentRichModalItems.length === 0) return;
            if (prevBtn) prevBtn.style.display = (currentRichModalIndex > 0) ? "block" : "none";
            if (nextBtn) nextBtn.style.display = (currentRichModalIndex < currentRichModalItems.length - 1) ? "block" : "none";
        }

        function showPrevRichModalImage() {
            if (currentRichModalIndex > 0) {
                displayRichModalImageAtIndex(currentRichModalIndex - 1);
            }
        }

        function showNextRichModalImage() {
            if (currentRichModalIndex < currentRichModalItems.length - 1) {
                displayRichModalImageAtIndex(currentRichModalIndex + 1);
            }
        }
        
        // Global open fonksiyonları (HTML'den çağrılacaklar)
        window.openGalleryModal = function(index) { // gallery.php tarafından çağrılır
            if (typeof window.phpGalleryPhotos !== 'undefined' && Array.isArray(window.phpGalleryPhotos)) {
                currentRichModalItems = window.phpGalleryPhotos;
                if (richNavModalElement && currentRichModalItems.length > 0) {
                    displayRichModalImageAtIndex(index);
                    richNavModalElement.style.display = "block";
                } else if (richNavModalElement) {
                     console.warn("Galeri için resim verisi boş. Modal açılmıyor.");
                }
            } else {
                console.error("window.phpGalleryPhotos bulunamadı veya dizi değil.");
            }
        };

        window.openHangarShipModal = function(index) { // view_hangar.php tarafından çağrılır
            if (typeof window.phpHangarShipDataForModal !== 'undefined' && Array.isArray(window.phpHangarShipDataForModal)) {
                currentRichModalItems = window.phpHangarShipDataForModal;
                 if (richNavModalElement && currentRichModalItems.length > 0) {
                    displayRichModalImageAtIndex(index);
                    richNavModalElement.style.display = "block";
                } else if (richNavModalElement) {
                    console.warn("Hangar için resim verisi boş. Modal açılmıyor.");
                }
            } else {
                console.error("window.phpHangarShipDataForModal bulunamadı veya dizi değil.");
            }
        };

        // Ortak Kapatma Fonksiyonu
        window.closeRichNavModal = function() {
            if (richNavModalElement) richNavModalElement.style.display = "none";
        };

        // Olay Dinleyicileri (sadece richNavModalElement varsa eklenir)
        if (closeBtn) closeBtn.onclick = closeRichNavModal;
        if (prevBtn) prevBtn.onclick = showPrevRichModalImage;
        if (nextBtn) nextBtn.onclick = showNextRichModalImage;

        richNavModalElement.addEventListener('click', function(event) {
            if (event.target == richNavModalElement) {
                closeRichNavModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (richNavModalElement.style.display === "block") { // Sadece bu modal aktifse
                if (event.key === "Escape") closeRichNavModal();
                else if (event.key === "ArrowLeft") showPrevRichModalImage();
                else if (event.key === "ArrowRight") showNextRichModalImage();
            }
        });
    }
});