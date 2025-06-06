// public/js/loadout_editor.js
document.addEventListener('DOMContentLoaded', function() {
    const loadoutItemsEditorContainer = document.querySelector('.loadout-items-editor-container');
    if (!loadoutItemsEditorContainer) {
        // console.log("Loadout editor ana container'ı bulunamadı, bu sayfada loadout_editor.js scripti çalışmayacak.");
        return; 
    }
    console.log("Loadout Item Editor (Akıllı Atama v10 - Tam Kod) Scripti Başlatıldı.");

    // --- Element Seçicileri ---
    const slotListUL = document.getElementById('slotSummaryList');
    const searchInput = document.getElementById('item_search_query');
    const searchButton = document.getElementById('searchItemApiButton');
    const searchResultsDiv = document.getElementById('itemSearchResults');
    const loadoutItemsForm = document.getElementById('loadoutItemsForm');
    const customSlotNameInput = document.getElementById('customSlotNameInput');
    const addCustomSlotButton = document.getElementById('addCustomSlotButton');
    const selectedItemFeedbackArea = document.getElementById('selectedItemFeedbackArea');
    const selectedItemFeedbackText = document.getElementById('selectedItemFeedbackText');

    // --- Global Durum Değişkenleri ---
    let currentLoadoutConfig = {}; // { slotKey: {item_details...}, ... }
    if (typeof window.phpAssignedLoadoutItems !== 'undefined' && window.phpAssignedLoadoutItems) {
        try {
            // Derin kopya oluşturarak global değişkeni doğrudan değiştirmeyi engelle
            currentLoadoutConfig = JSON.parse(JSON.stringify(window.phpAssignedLoadoutItems));
        } catch(e) {
            console.error("window.phpAssignedLoadoutItems parse edilemedi:", e, window.phpAssignedLoadoutItems);
        }
    }
    const standardSlots = (typeof window.phpStandardSlots !== 'undefined' && Array.isArray(window.phpStandardSlots)) ? window.phpStandardSlots : [];
    
    console.log("JS - Başlangıçtaki Atanmış Item'lar (currentLoadoutConfig):", JSON.parse(JSON.stringify(currentLoadoutConfig)));
    console.log("JS - Standart Slot Verileri (standardSlots):", JSON.parse(JSON.stringify(standardSlots)));

    // --- Yardımcı Fonksiyonlar ---
    function htmlEntities(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showFeedback(message, isError = false) {
        if (selectedItemFeedbackText && selectedItemFeedbackArea) {
            selectedItemFeedbackText.textContent = message;
            selectedItemFeedbackArea.style.color = isError ? 'var(--netflix-red, #E50914)' : 'var(--light-gold, #FFEE75)';
            selectedItemFeedbackArea.style.display = 'block';
            setTimeout(() => { 
                if (selectedItemFeedbackArea) selectedItemFeedbackArea.style.display = 'none'; 
            }, 4000);
        } else {
            console.log((isError ? "HATA: " : "BİLGİ: ") + message);
        }
    }

    // --- Slot Listesini Render Etme ---
    function renderSlotList() {
        if (!slotListUL) { console.error("ID'si 'slotSummaryList' olan UL elementi bulunamadı."); return; }
        slotListUL.innerHTML = ''; 

        if (standardSlots.length === 0) {
            console.warn("Render edilecek standart slot bulunamadı (standardSlots JS dizisi boş).");
        }

        standardSlots.forEach(slot => {
            if (typeof slot.name === 'undefined' || slot.name === null || slot.name === 'HATA_SLOT_ADI_YOK') {
                console.error("HATA: Standart slotun adı (slot.name) tanımsız veya hatalı!", slot);
                const errorLi = document.createElement('li');
                errorLi.style.color = 'red';
                errorLi.textContent = 'Hatalı Slot Verisi! (Ad Tanımsız)';
                slotListUL.appendChild(errorLi);
                return; 
            }

            const slotKey = htmlEntities(slot.name); 
            const assignedItem = currentLoadoutConfig[slotKey];
            const listItem = document.createElement('li');
            listItem.className = `slot-summary-item ${assignedItem ? 'slot-filled' : 'slot-empty'}`;
            listItem.id = `slot_display_std_${slot.id}`; 
            listItem.dataset.slotKey = slotKey; 
            listItem.dataset.slotId = slot.id;
            listItem.dataset.slotName = slot.name;
            listItem.dataset.slotType = slot.type || '';
            listItem.dataset.isCustom = 'false';
            
            let itemHTML = `<span class="slot-name-display">${htmlEntities(slot.name)}:</span>
                            <span class="assigned-item-display-name">
                                ${assignedItem ? htmlEntities(assignedItem.item_name) : 'Boş'}
                            </span>`;
            if (assignedItem) {
                itemHTML += `<button type="button" class="remove-item-btn" data-slot-key="${slotKey}">&times;</button>`;
            }
            listItem.innerHTML = itemHTML;
            slotListUL.appendChild(listItem);
        });

        for (const slotKey in currentLoadoutConfig) {
            if (currentLoadoutConfig.hasOwnProperty(slotKey) && slotKey.startsWith('custom_')) {
                const customSlotName = currentLoadoutConfig[slotKey].custom_slot_name || slotKey.substring(7).replace(/_/g, ' ');
                const assignedItem = currentLoadoutConfig[slotKey];
                const listItem = document.createElement('li');
                listItem.className = 'slot-summary-item slot-filled custom-slot-display';
                listItem.id = `slot_display_${slotKey}`;
                listItem.dataset.slotKey = slotKey; 
                listItem.dataset.slotName = customSlotName;
                listItem.dataset.isCustom = 'true'; // Özel slot olduğunu belirt
                let itemHTML = `<span class="slot-name-display">${htmlEntities(customSlotName)} (Özel):</span>
                                <span class="assigned-item-display-name">${htmlEntities(assignedItem.item_name)}</span>
                                <button type="button" class="remove-item-btn" data-slot-key="${slotKey}">&times;</button>`;
                listItem.innerHTML = itemHTML;
                slotListUL.appendChild(listItem);
            }
        }
        attachRemoveButtonListenersToSlots();
    }
    
    function attachRemoveButtonListenersToSlots() {
        if (!slotListUL) return;
        slotListUL.querySelectorAll('.remove-item-btn').forEach(btn => {
            if (btn.dataset.listenerAttached === 'true') return;
            btn.addEventListener('click', function() {
                const slotKeyToRemove = this.dataset.slotKey;
                if (currentLoadoutConfig[slotKeyToRemove]) {
                    const removedItemName = currentLoadoutConfig[slotKeyToRemove].item_name;
                    delete currentLoadoutConfig[slotKeyToRemove];
                    renderSlotList(); 
                    showFeedback(`"${htmlEntities(removedItemName)}" item'ı "${htmlEntities(slotKeyToRemove.replace("custom_","").replace(/_/g," "))}" slotundan çıkarıldı.`);
                    console.log(`${slotKeyToRemove} slotundan item çıkarıldı (config güncellendi).`);
                }
            });
            btn.dataset.listenerAttached = 'true';
        });
    }

    if (addCustomSlotButton && customSlotNameInput && slotListUL) {
        addCustomSlotButton.addEventListener('click', function() {
            const newSlotName = customSlotNameInput.value.trim();
            if (newSlotName === '') { alert("Lütfen özel slot için bir ad girin."); return; }
            const newSlotKey = 'custom_' + newSlotName.replace(/\s+/g, '_');

            if (currentLoadoutConfig[newSlotKey] || standardSlots.find(s => s.name === newSlotName) || slotListUL.querySelector(`li[data-slot-name="${newSlotName}"]`)) {
                alert("Bu isimde bir slot zaten mevcut veya standart bir slot adıyla aynı."); return;
            }
            
            const listItem = document.createElement('li');
            listItem.className = 'slot-summary-item slot-empty custom-slot-display';
            listItem.id = `slot_display_${newSlotKey}`;
            listItem.dataset.slotKey = newSlotKey;
            listItem.dataset.slotName = newSlotName;
            listItem.dataset.isCustom = 'true';
            listItem.innerHTML = `<span class="slot-name-display">${htmlEntities(newSlotName)} (Özel):</span>
                                  <span class="assigned-item-display-name">Boş</span>`;
            slotListUL.appendChild(listItem);
            customSlotNameInput.value = '';
            console.log("Özel slot eklendi (arayüze):", newSlotName);
            // Bu yeni özel slota da "Çıkar" butonu item atanınca renderSlotList ile eklenecek.
            // Veya boş özel slotları silmek için ayrı bir mekanizma gerekebilir.
        });
    }
    
    if (searchButton && searchInput && searchResultsDiv) {
        searchButton.addEventListener('click', performItemSearch);
        searchInput.addEventListener('keypress', function(e){ if(e.key === 'Enter'){ e.preventDefault(); performItemSearch();}});
    }

    function performItemSearch() {
        const query = searchInput.value.trim();
        if (query.length < 2) { 
            if(searchResultsDiv) searchResultsDiv.innerHTML = '<p class="search-placeholder-text">Aramak için en az 2 karakter girin.</p>'; 
            return; 
        }
        if(searchResultsDiv) searchResultsDiv.innerHTML = '<p class="search-placeholder-text">API aranıyor...</p>';
        if (selectedItemFeedbackArea) selectedItemFeedbackArea.style.display = 'none';

        fetch(`../../src/actions/search_items_star_citizen_wiki_api.php?query=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                if(searchResultsDiv) searchResultsDiv.innerHTML = '';
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'api-search-item-no-image';
                        const itemName = item.name || 'İsimsiz';
                        const itemManufacturer = item.manufacturer?.name || 'Bilinmiyor';
                        const itemTypeApi = item.type || 'N/A';
                        
                        itemDiv.innerHTML = `
                            <div class="search-item-details-no-image">
                                <span class="item-name">${htmlEntities(itemName)}</span>
                                <span class="item-type-manufacturer">${htmlEntities(itemManufacturer)} - ${htmlEntities(itemTypeApi)}</span>
                            </div>`;
                        itemDiv.addEventListener('click', function() {
                            attemptToAssignItemToSlot(item); 
                        });
                        if(searchResultsDiv) searchResultsDiv.appendChild(itemDiv);
                    });
                } else { 
                    if(searchResultsDiv) searchResultsDiv.innerHTML = `<p class="search-placeholder-text">${data.message || 'Sonuç bulunamadı.'}</p>`;
                }
            })
            .catch(error => { 
                console.error("API item arama hatası:", error); 
                if(searchResultsDiv) searchResultsDiv.innerHTML = '<p class="search-placeholder-text" style="color:red;">Item aranırken bir hata oluştu.</p>'; 
            });
    }
    
    function isItemTypeMatch(itemApiType, itemApiSubType, slotDefinitionType) {
        if (!slotDefinitionType) return false; 
        
        const expectedSlotTypes = slotDefinitionType.split(',').map(t => t.trim().toLowerCase());
        const currentItemApiType = itemApiType ? itemApiType.trim().toLowerCase() : null;
        const currentItemApiSubType = itemApiSubType && itemApiSubType.toLowerCase() !== "undefined" ? itemApiSubType.trim().toLowerCase() : null;

        console.log(`Eşleştirme: Item Tipi="${currentItemApiType}", AltTipi="${currentItemApiSubType}" vs Slot Tipleri="${expectedSlotTypes.join(',')}"`);

        if (currentItemApiType && expectedSlotTypes.includes(currentItemApiType)) {
            console.log(`  -> Eşleşme (ana tip): "${currentItemApiType}"`); return true;
        }
        if (currentItemApiSubType && expectedSlotTypes.includes(currentItemApiSubType)) {
            console.log(`  -> Eşleşme (alt tip): "${currentItemApiSubType}"`); return true;
        }
        // Ekstra genel kontroller (örneğin, bir "Weapon" slotu tüm "WeaponPersonal"ları kabul edebilir)
        if (expectedSlotTypes.includes('weapon') && currentItemApiType && currentItemApiType.startsWith('weapon')) {
             console.log(`  -> Eşleşme (genel weapon): "${currentItemApiType}"`); return true;
        }
        if (expectedSlotTypes.includes('armor') && currentItemApiType && currentItemApiType.includes('armor')) {
             console.log(`  -> Eşleşme (genel armor): "${currentItemApiType}"`); return true;
        }

        console.log(`  -> Eşleşme bulunamadı.`);
        return false;
    }

    function attemptToAssignItemToSlot(itemData) {
        if (!itemData || !itemData.name) { showFeedback("Geçersiz item verisi.", true); return; }
        const itemNameSafe = htmlEntities(itemData.name);
        const itemApiType = itemData.type; 
        const itemApiSubType = itemData.sub_type;
        let assigned = false;
        let targetSlotKey = null;
        let message = "";

        console.log(`"${itemNameSafe}" için slot atanmaya çalışılıyor. API Tipi: ${itemApiType}, Alt Tipi: ${itemApiSubType}`);

        // 1. Uygun BOŞ standart slot ara
        for (const slot of standardSlots) {
            const slotKey = htmlEntities(slot.name);
            if (isItemTypeMatch(itemApiType, itemApiSubType, slot.type) && !currentLoadoutConfig[slotKey]) {
                assignItemToConfiguration(slotKey, itemData, true, slot.id);
                targetSlotKey = slotKey;
                message = `"${itemNameSafe}" item'ı, boş olan "${htmlEntities(slot.name)}" slotuna atandı.`;
                assigned = true;
                break;
            }
        }

        // 2. Boş slot yoksa, DOLU ama uygun tipteki standart slotu DEĞİŞTİRME onayı iste
        if (!assigned) {
            for (const slot of standardSlots) {
                const slotKey = htmlEntities(slot.name);
                if (isItemTypeMatch(itemApiType, itemApiSubType, slot.type) && currentLoadoutConfig[slotKey]) {
                    const oldItemName = currentLoadoutConfig[slotKey].item_name;
                    if (confirm(`"${htmlEntities(slot.name)}" slotu şu an "${htmlEntities(oldItemName)}" ile dolu.\nBu item'ı "${itemNameSafe}" ile değiştirmek ister misiniz?`)) {
                        assignItemToConfiguration(slotKey, itemData, true, slot.id);
                        targetSlotKey = slotKey;
                        message = `"${itemNameSafe}" item'ı, "${htmlEntities(slot.name)}" slotuna atandı (eskisi değiştirildi).`;
                    } else {
                        message = `"${itemNameSafe}" için "${htmlEntities(slot.name)}" slotuna atama iptal edildi.`;
                        targetSlotKey = "user_cancelled_replace"; // Özel bir durum, işlem bitti ama atama yok
                    }
                    assigned = true; 
                    break; 
                }
            }
        }
        
        // 3. Hala atanamadıysa VE kullanıcı bir değiştirme işlemini İPTAL ETMEDİYSE, özel slot sor
        if (!assigned || (targetSlotKey && targetSlotKey !== "user_cancelled_replace" && !currentLoadoutConfig[targetSlotKey])) {
             if (targetSlotKey !== "user_cancelled_replace") { // Kullanıcı açıkça iptal etmediyse özel slot sor
                const newCustomSlotName = prompt(`"${itemNameSafe}" için uygun standart slot bulunamadı/seçilmedi.\nBu item'ı atamak için yeni bir ÖZEL SLOT ADI girin (veya iptal için boş bırakın):`);
                if (newCustomSlotName && newCustomSlotName.trim() !== "") {
                    const customSlotNameTrimmed = newCustomSlotName.trim();
                    const newSlotKey = 'custom_' + customSlotNameTrimmed.replace(/\s+/g, '_');
                    if (currentLoadoutConfig[newSlotKey] || standardSlots.find(s => s.name === customSlotNameTrimmed) || (slotListUL && slotListUL.querySelector(`li[data-slot-name="${customSlotNameTrimmed}"]`))) {
                        message = "Girdiğiniz özel slot adı zaten mevcut veya standart bir slot adıyla aynı.";
                        showFeedback(message, true); return; // Fonksiyondan tamamen çık
                    }
                    // Arayüze yeni özel slotu ekle (eğer yoksa)
                    if (slotListUL && !slotListUL.querySelector(`li[data-slot-key="${newSlotKey}"]`)) {
                        const listItem = document.createElement('li');
                        listItem.className = 'slot-summary-item slot-empty custom-slot-display';
                        listItem.id = `slot_display_${newSlotKey}`;
                        listItem.dataset.slotKey = newSlotKey;
                        listItem.dataset.slotName = customSlotNameTrimmed;
                        listItem.dataset.isCustom = 'true';
                        listItem.innerHTML = `<span class="slot-name-display">${htmlEntities(customSlotNameTrimmed)} (Özel):</span>
                                              <span class="assigned-item-display-name">Boş</span>`;
                        slotListUL.appendChild(listItem);
                        // attachRemoveButtonListenersToSlots(); // renderSlotList zaten çağıracak
                    }
                    assignItemToConfiguration(newSlotKey, itemData, false); // standardSlotId null olacak
                    targetSlotKey = newSlotKey; 
                    message = `"${itemNameSafe}" item'ı, yeni özel slot olan "${htmlEntities(customSlotNameTrimmed)}"e atandı.`;
                } else if (!targetSlotKey) { // Standart slot bulunamadı VE özel slot da girilmedi
                     message = `"${itemNameSafe}" için atama yapılmadı (özel slot oluşturulmadı).`;
                }
            }
        }

        if (targetSlotKey && targetSlotKey !== "user_cancelled_replace") { 
            renderSlotList(); 
        }
        
        showFeedback(message || `"${itemNameSafe}" için bir işlem yapılmadı veya atama iptal edildi.`);
       // if (searchInput) searchInput.value = ''; 
       // if (searchResultsDiv) searchResultsDiv.innerHTML = '<p class="search-placeholder-text">Başka bir item arayın veya değişiklikleri kaydedin.</p>';
    }

    function assignItemToConfiguration(slotKey, itemData, isStandard, standardSlotId = null) {
        currentLoadoutConfig[slotKey] = {
            slot_id: isStandard ? standardSlotId : null,
            custom_slot_name: isStandard ? null : slotKey.substring(7).replace(/_/g, ' '),
            item_name: itemData.name || 'Bilinmiyor',
            item_api_uuid: itemData.uuid || null,
            item_type_api: itemData.type || null, 
            item_manufacturer_api: itemData.manufacturer?.name || null
        };
        console.log("Config'e atandı:", slotKey, currentLoadoutConfig[slotKey]);
    }

    if (loadoutItemsForm) {
        loadoutItemsForm.addEventListener('submit', function(e) {
            this.querySelectorAll('input[type="hidden"][name^="items["]').forEach(inp => inp.remove());
            for (const slotKeyInConfig in currentLoadoutConfig) {
                if (currentLoadoutConfig.hasOwnProperty(slotKeyInConfig)) {
                    const item = currentLoadoutConfig[slotKeyInConfig];
                    if (!item || !item.item_name) continue; // Boş veya adı olmayan item'ları gönderme

                    function createHiddenInput(property, value) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `items[${slotKeyInConfig}][${property}]`;
                        input.value = value !== null && typeof value !== 'undefined' ? value : '';
                        loadoutItemsForm.appendChild(input);
                    }
                    createHiddenInput('slot_id', item.slot_id);
                    createHiddenInput('custom_slot_name', item.custom_slot_name);
                    createHiddenInput('item_name', item.item_name);
                    createHiddenInput('item_api_uuid', item.item_api_uuid);
                    createHiddenInput('item_type_api', item.item_type_api);
                    createHiddenInput('item_manufacturer_api', item.item_manufacturer_api);
                }
            }
        });
    }
    renderSlotList(); 
});