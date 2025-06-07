// Global variables
let currentSearchResults = [];
let loadoutItems = new Map(); // slot_id -> item_data
let weaponAttachments = new Map(); // parent_slot_id -> Map(attachment_slot_id -> attachment_data)
let attachmentSlots = new Map(); // parent_slot_id -> [attachment_slot_definitions]
let isSearching = false;

// CSRF token
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// API Configuration - Kendi proxy'imizi kullan
const API_CONFIG = {
    BASE_URL: 'api/api.php', // Aynı klasördeki proxy
    TIMEOUT: 30000,
    MAX_RESULTS: 50
};

// Item type mapping for slot compatibility - GÜNCELLENMIŞ
const SLOT_TYPE_MAPPING = {
    'WeaponPersonal': ['Birincil Silah 1', 'Birincil Silah 2 (Sırtta)', 'İkincil Silah (Tabanca veya Medgun)', 'Yardımcı Modül/Gadget 1'],
    'Char_Armor_Helmet': ['Kask'],
    'Char_Armor_Torso': ['Gövde Zırhı'],
    'Char_Armor_Arms': ['Kol Zırhları'],
    'Char_Armor_Legs': ['Bacak Zırhları'],
    'Char_Armor_Backpack': ['Sırt Çantası'],
    'Char_Clothing_Undersuit': ['Alt Giyim'],
    'Char_Armor_Undersuit': ['Alt Giyim'],
    'fps_consumable': ['Medikal Araç'],
    'medical': ['Medikal Araç'],
    'gadget': ['Yardımcı Modül/Gadget 1', 'Medikal Araç'],
    'tool_multitool': ['Multi-Tool Attachment'],
    'tool': ['Multi-Tool Attachment'],
    'weaponattachment': ['Multi-Tool Attachment'],
    'toolattachment': ['Multi-Tool Attachment'],
    // WEAPON ATTACHMENTS - YENİ
    'IronSight': [], // Dynamic - will be filled by getAttachmentSlots()
    'Barrel': [], // Dynamic - will be filled by getAttachmentSlots()
    'BottomAttachment': [] // Dynamic - will be filled by getAttachmentSlots()
};

// Weapon slots that support attachments
const WEAPON_SLOTS = [7, 8, 9]; // Birincil Silah 1, Birincil Silah 2, İkincil Silah

// DOM Elements
const searchInput = document.getElementById('item_search');
const typeFilter = document.getElementById('type_filter');
const searchBtn = document.getElementById('search_btn');
const searchResults = document.querySelector('.search-results');
const searchPlaceholder = document.querySelector('.search-placeholder');
const searchLoading = document.querySelector('.search-loading');
const resultsContainer = document.getElementById('search_results_container');

// CSS stilleri için ek fonksiyon
function addAttachmentStyles() {
    if (document.getElementById('attachment-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'attachment-styles';
    style.textContent = `
        .attachment-slots-container {
            margin-top: 1rem;
            border: 1px solid var(--border-1);
            border-radius: 6px;
            padding: 1rem;
            background-color: var(--card-bg-2);
        }
        
        .attachment-slot {
            border: 1px solid var(--border-1);
            border-radius: 6px;
            background-color: var(--card-bg-3);
            padding: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 80px;
        }
        
        .attachment-slot:hover {
            border-color: var(--border-1-hover);
            transform: translateY(-2px);
        }
        
        .attachment-slot.has-attachment {
            border-color: var(--gold);
        }
        
        .attachment-slot.attachment-drag-over {
            border-color: var(--turquase);
            background: var(--transparent-turquase);
        }
        
        .attachment-clear-btn:hover {
            background: var(--red) !important;
            color: var(--white) !important;
        }
    `;
    document.head.appendChild(style);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    addAttachmentStyles(); // CSS stillerini ekle
    initializeEventListeners();
    initializeSlots();
    
    // Eğer edit mode'daysa mevcut verileri yükle
    const editMode = window.location.search.includes('edit=');
    if (editMode && typeof window.existingItems !== 'undefined') {
        loadExistingItems(window.existingItems);
    }
});

function initializeEventListeners() {
    // Search input events
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchItems();
        }
    });

    searchInput.addEventListener('input', debounce(function() {
        if (this.value.length >= 3) {
            searchItems();
        } else if (this.value.length === 0) {
            clearSearchResults();
        }
    }, 500));

    // Type filter change
    typeFilter.addEventListener('change', function() {
        if (searchInput.value.length >= 3) {
            searchItems();
        }
    });

    // Form submission
    const loadoutForm = document.getElementById('loadoutForm');
    if (loadoutForm) {
        loadoutForm.addEventListener('submit', handleFormSubmission);
    }

    // Modal events
    document.addEventListener('click', function(e) {
        if (e.target.matches('.modal-overlay')) {
            closeModal(e.target.closest('.modal').id);
        }
    });

    // Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal[style*="display: block"]');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
}

function initializeSlots() {
    const slots = document.querySelectorAll('.equipment-slot');
    slots.forEach(slot => {
        slot.addEventListener('click', function() {
            if (!this.classList.contains('has-item')) {
                focusSearchForSlot(this);
            }
        });

        // Drag and drop support
        slot.addEventListener('dragover', handleDragOver);
        slot.addEventListener('drop', handleDrop);
        slot.addEventListener('dragleave', handleDragLeave);
    });
}

// Search Functions - TİP FİLTRESİ DÜZELTİLDİ
async function searchItems() {
    const query = searchInput.value.trim();
    const filterType = typeFilter.value;

    if (query.length < 2) {
        showMessage('En az 2 karakter girmelisiniz', 'error');
        return;
    }

    if (isSearching) return;

    console.log('Searching for:', query, 'with filter:', filterType);
    showSearchLoading();
    isSearching = true;

    try {
        const response = await fetch(API_CONFIG.BASE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                query: query,
                filter_type: filterType // Filtreyi de gönder
            })
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        console.log('API Response:', data);
        
        // Data kontrolü
        let items = Array.isArray(data) ? data : (data.data || []);
        
        // Eğer tip filtresi seçildiyse, client-side'da da filtrele
        if (filterType && filterType.trim() !== '') {
            items = items.filter(item => {
                const itemType = item.type || '';
                const itemSubType = item.sub_type || '';
                
                // Tam eşleşme veya içerme kontrolü
                return itemType.includes(filterType) || 
                       itemSubType.includes(filterType) ||
                       itemType.toLowerCase().includes(filterType.toLowerCase()) ||
                       itemSubType.toLowerCase().includes(filterType.toLowerCase());
            });
            console.log('Filtered results:', items.length, 'items');
        }
        
        if (items.length > 0) {
            currentSearchResults = items.slice(0, API_CONFIG.MAX_RESULTS);
            displaySearchResults(currentSearchResults);
        } else {
            showNoResults();
        }

    } catch (error) {
        console.error('Search error:', error);
        showSearchError('Arama hatası: ' + error.message);
    } finally {
        hideSearchLoading();
        isSearching = false;
    }
}

function displaySearchResults(results) {
    console.log('Displaying', results.length, 'results');
    hideSearchElements();
    resultsContainer.style.display = 'block';
    resultsContainer.innerHTML = '';

    if (results.length === 0) {
        showNoResults();
        return;
    }

    const filterType = typeFilter.value;
    if (filterType) {
        // Filtre info göster
        const filterInfo = document.createElement('div');
        filterInfo.className = 'filter-info';
        filterInfo.innerHTML = `
            <div style="background: var(--transparent-gold); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.8rem;">
                <i class="fas fa-filter"></i> Filtre: <strong>${getFilterDisplayName(filterType)}</strong>
                <button onclick="clearFilter()" style="float: right; background: none; border: none; color: var(--red); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        resultsContainer.appendChild(filterInfo);
    }

    results.forEach((item, index) => {
        console.log(`Item ${index}:`, item);
        const resultElement = createSearchResultElement(item);
        resultsContainer.appendChild(resultElement);
    });
}

function getFilterDisplayName(filterType) {
    const filterNames = {
        'WeaponPersonal': 'Silahlar',
        'Char_Armor_Helmet': 'Kasklar',
        'Char_Armor_Torso': 'Gövde Zırhları',
        'Char_Armor_Arms': 'Kol Zırhları',
        'Char_Armor_Legs': 'Bacak Zırhları',
        'Char_Armor_Backpack': 'Sırt Çantaları',
        'Char_Clothing_Undersuit': 'Alt Giysiler',
        'fps_consumable': 'Tüketilebilir'
    };
    return filterNames[filterType] || filterType;
}

function clearFilter() {
    typeFilter.value = '';
    if (searchInput.value.length >= 3) {
        searchItems();
    }
}

function createSearchResultElement(item) {
    const div = document.createElement('div');
    div.className = 'search-result-item';
    div.draggable = true;

    // Compatibility check
    const compatibleSlots = getCompatibleSlots(item);
    if (compatibleSlots.length > 0) {
        div.classList.add('compatible');
    }

    const itemName = escapeHtml(item.name || 'Unknown Item');
    const itemType = escapeHtml(item.type || 'Unknown Type');
    const itemSubType = escapeHtml(item.sub_type || '');
    const manufacturerName = escapeHtml(item.manufacturer?.name || 'Unknown Manufacturer');

    // Filtre match'ini vurgula
    const filterType = typeFilter.value;
    let typeDisplay = itemType;
    if (filterType && (itemType.includes(filterType) || itemSubType.includes(filterType))) {
        typeDisplay = highlightMatch(itemType, filterType);
    }

    div.innerHTML = `
        <div class="result-info">
            <div class="result-name">${itemName}</div>
            <div class="result-meta">
                <div class="result-type">${typeDisplay}${itemSubType && itemSubType !== 'UNDEFINED' ? ' / ' + itemSubType : ''}</div>
                <div class="result-manufacturer">${manufacturerName}</div>
            </div>
            ${compatibleSlots.length > 0 ? `
                <div class="compatible-slots">
                    <small>Uyumlu: ${compatibleSlots.join(', ')}</small>
                </div>
            ` : ''}
        </div>
    `;

    // Event listeners
    div.addEventListener('click', () => {
        if (compatibleSlots.length > 0) {
            assignItemToFirstCompatibleSlot(item, compatibleSlots);
        } else {
            showMessage('Bu item hiçbir slotla uyumlu değil', 'error');
        }
    });

    div.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('application/json', JSON.stringify(item));
        e.dataTransfer.effectAllowed = 'copy';
        console.log('Drag started for item:', item.name, 'type:', item.type, 'sub_type:', item.sub_type);
    });

    // Store item data
    div.itemData = item;

    return div;
}

function highlightMatch(text, filter) {
    if (!filter) return text;
    const regex = new RegExp(`(${escapeRegex(filter)})`, 'gi');
    return text.replace(regex, '<mark style="background: var(--gold); color: var(--charcoal); padding: 0 2px;">$1</mark>');
}

function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// DÜZELTİLMİŞ - ATTACHMENT COMPATIBILITY
function getCompatibleSlots(item) {
    const compatibleSlots = [];
    const itemType = item.type || '';
    const itemSubType = item.sub_type || '';
    
    console.log('Checking compatibility for:', item.name, 'Type:', itemType, 'SubType:', itemSubType);
    
    // WEAPON ATTACHMENTS - ÖZEL KONTROL (DÜZELTİLMİŞ)
    if (itemType === 'WeaponAttachment') {
        console.log('Processing WeaponAttachment with sub_type:', itemSubType);
        
        // API'den gelen sub_type'a göre mapping - DÜZELTİLMİŞ
        const attachmentMapping = {
            'Barrel': ['Namlu Eklentisi'],
            'IronSight': ['Nişangah/Optik'], 
            'BottomAttachment': ['Alt Bağlantı'],
            'Utility': ['Alt Bağlantı'], // Utility'i de alt bağlantı olarak kabul et
            'Magazine': ['Alt Bağlantı'] // Magazine'i de alt bağlantı olarak kabul et
        };
        
        // Sub_type ile eşleşen attachment slotları bul
        for (const [subType, slotNames] of Object.entries(attachmentMapping)) {
            if (itemSubType === subType || itemSubType.includes(subType)) {
                compatibleSlots.push(...slotNames);
                console.log('WeaponAttachment matched sub_type:', subType, 'compatible with:', slotNames);
                break; // İlk eşleşmeyi al
            }
        }
        
        // Eğer hiç eşleşme yoksa, tüm attachment slotlarını listele
        if (compatibleSlots.length === 0) {
            console.log('No specific sub_type match, checking all attachment slots...');
            // Tüm attachment slot isimlerini döndür
            compatibleSlots.push('Nişangah/Optik', 'Namlu Eklentisi', 'Alt Bağlantı');
        }
    } else {
        // Normal item type mapping (silahlar, zırhlar vs.)
        Object.entries(SLOT_TYPE_MAPPING).forEach(([type, slotNames]) => {
            if (itemType.includes(type) || itemSubType.includes(type)) {
                compatibleSlots.push(...slotNames);
                console.log('Compatible with:', slotNames, 'due to type:', type);
            }
        });
    }

    // Multi-type compatibility
    if (item.slot_type) {
        const slotTypes = item.slot_type.split(',').map(s => s.trim());
        slotTypes.forEach(slotType => {
            if (SLOT_TYPE_MAPPING[slotType]) {
                compatibleSlots.push(...SLOT_TYPE_MAPPING[slotType]);
            }
        });
    }

    const uniqueSlots = [...new Set(compatibleSlots)];
    console.log('Final compatible slots for', item.name, ':', uniqueSlots);
    return uniqueSlots;
}

function assignItemToFirstCompatibleSlot(item, compatibleSlots) {
    // WEAPON ATTACHMENT ise attachment slotlarına yerleştir
    if (item.type === 'WeaponAttachment') {
        assignAttachmentToFirstCompatibleSlot(item, compatibleSlots);
        return;
    }
    
    // Normal item ise normal slotlara yerleştir
    // Find first empty compatible slot
    for (const slotName of compatibleSlots) {
        const slot = findSlotByName(slotName);
        if (slot && !slot.classList.contains('has-item')) {
            assignItemToSlot(item, slot);
            return;
        }
    }

    // If no empty slots, ask user to choose
    showSlotSelectionModal(item, compatibleSlots);
}

function assignAttachmentToFirstCompatibleSlot(attachment, compatibleSlots) {
    console.log('🎯 Looking for attachment slots:', compatibleSlots);
    
    // Find first empty compatible attachment slot
    for (const slotName of compatibleSlots) {
        console.log('Checking for attachment slots with name:', slotName);
        
        // Find attachment slots by name across all weapon slots
        const attachmentSlots = document.querySelectorAll(`.attachment-slot[data-slot-name="${slotName}"]`);
        console.log('Found attachment slots:', attachmentSlots.length);
        
        for (const attachmentSlot of attachmentSlots) {
            console.log('Checking attachment slot:', attachmentSlot.dataset);
            
            if (!attachmentSlot.classList.contains('has-attachment')) {
                const parentSlotId = parseInt(attachmentSlot.dataset.parentSlotId);
                const attachmentSlotId = parseInt(attachmentSlot.dataset.attachmentSlotId);
                
                console.log('Found empty slot - assigning attachment to parent:', parentSlotId, 'attachment slot:', attachmentSlotId);
                assignAttachmentToSlot(attachment, parentSlotId, attachmentSlotId);
                return;
            }
        }
    }
    
    console.log('No empty attachment slots found');
    showMessage(`${attachment.name || 'Bu attachment'} için uygun boş slot bulunamadı. Önce bir silah ekleyin (slot 7, 8 veya 9).`, 'warning');
}

function findSlotByName(slotName) {
    const slots = document.querySelectorAll('.equipment-slot');
    for (const slot of slots) {
        if (slot.dataset.slotName === slotName) {
            return slot;
        }
    }
    return null;
}

function assignItemToSlot(item, slot) {
    const slotId = parseInt(slot.dataset.slotId);
    const slotContent = slot.querySelector('.slot-content');
    const slotEmpty = slot.querySelector('.slot-empty');
    const slotItem = slot.querySelector('.slot-item');
    const clearBtn = slot.querySelector('.slot-clear-btn');

    // Store item data
    loadoutItems.set(slotId, item);

    // Update UI
    slot.classList.add('has-item');
    slotEmpty.style.display = 'none';
    slotItem.style.display = 'flex';
    clearBtn.style.display = 'inline-flex';

    // Update item display
    const itemName = slotItem.querySelector('.item-name');
    const itemManufacturer = slotItem.querySelector('.item-manufacturer');

    itemName.textContent = item.name || 'Unknown Item';
    itemManufacturer.textContent = item.manufacturer?.name || 'Unknown Manufacturer';

    // Check if this is a weapon slot and create attachment slots
    if (WEAPON_SLOTS.includes(slotId) && item.type === 'WeaponPersonal') {
        createAttachmentSlots(slotId);
    }

    // Show success message
    showMessage(`${item.name || 'Item'} ${slot.dataset.slotName} slotuna eklendi`, 'success');

    // Add visual feedback
    slot.style.transform = 'scale(1.05)';
    setTimeout(() => {
        slot.style.transform = '';
    }, 200);
}

function clearSlot(slotId) {
    const slot = document.querySelector(`[data-slot-id="${slotId}"]`);
    if (!slot) return;

    const slotContent = slot.querySelector('.slot-content');
    const slotEmpty = slot.querySelector('.slot-empty');
    const slotItem = slot.querySelector('.slot-item');
    const clearBtn = slot.querySelector('.slot-clear-btn');

    // Remove from data
    loadoutItems.delete(parseInt(slotId));

    // If this is a weapon slot, remove attachments too
    if (WEAPON_SLOTS.includes(parseInt(slotId))) {
        removeAttachmentSlots(parseInt(slotId));
    }

    // Update UI
    slot.classList.remove('has-item');
    slotEmpty.style.display = 'block';
    slotItem.style.display = 'none';
    clearBtn.style.display = 'none';

    // Add visual feedback
    slot.style.transform = 'scale(0.95)';
    setTimeout(() => {
        slot.style.transform = '';
    }, 200);
}

// Drag and Drop Functions
function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    this.classList.add('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    this.classList.remove('drag-over');

    try {
        const item = JSON.parse(e.dataTransfer.getData('application/json'));
        const slotName = this.dataset.slotName;
        const compatibleSlots = getCompatibleSlots(item);

        if (compatibleSlots.includes(slotName)) {
            assignItemToSlot(item, this);
        } else {
            showMessage(`${item.name || 'Bu item'} ${slotName} slotuna uyumlu değil`, 'error');
        }
    } catch (error) {
        console.error('Drop error:', error);
        showMessage('Item yerleştirme hatası', 'error');
    }
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

// Modal Functions
function showSlotSelectionModal(item, compatibleSlots) {
    const modal = document.getElementById('confirmModal');
    const modalTitle = modal.querySelector('.modal-header h3');
    const modalBody = modal.querySelector('.modal-body');
    const confirmBtn = modal.querySelector('#confirmBtn');

    modalTitle.textContent = 'Slot Seçin';
    
    modalBody.innerHTML = `
        <p>${escapeHtml(item.name || 'Bu item')} için uygun slot seçin:</p>
        <div id="slot-selection" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;">
            ${compatibleSlots.map(slotName => {
                const slot = findSlotByName(slotName);
                const isEmpty = slot && !slot.classList.contains('has-item');
                return `
                    <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border: 1px solid var(--border-1); border-radius: 4px; cursor: ${isEmpty ? 'pointer' : 'not-allowed'}; opacity: ${isEmpty ? '1' : '0.5'};">
                        <input type="radio" name="selected_slot" value="${slotName}" ${isEmpty ? '' : 'disabled'}>
                        <span>${slotName} ${isEmpty ? '' : '(Dolu)'}</span>
                    </label>
                `;
            }).join('')}
        </div>
    `;

    confirmBtn.onclick = function() {
        const selectedSlot = modalBody.querySelector('input[name="selected_slot"]:checked');
        if (selectedSlot) {
            const slot = findSlotByName(selectedSlot.value);
            if (slot) {
                assignItemToSlot(item, slot);
            }
        }
        closeModal('confirmModal');
    };

    showModal('confirmModal');
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Search UI Functions
function showSearchLoading() {
    hideSearchElements();
    searchLoading.style.display = 'block';
}

function hideSearchLoading() {
    searchLoading.style.display = 'none';
}

function hideSearchElements() {
    searchPlaceholder.style.display = 'none';
    searchLoading.style.display = 'none';
    resultsContainer.style.display = 'none';
}

function clearSearchResults() {
    hideSearchElements();
    searchPlaceholder.style.display = 'block';
    currentSearchResults = [];
}

function showNoResults() {
    hideSearchElements();
    resultsContainer.style.display = 'block';
    const filterType = typeFilter.value;
    const filterText = filterType ? ` "${getFilterDisplayName(filterType)}" tipinde` : '';
    
    resultsContainer.innerHTML = `
        <div style="text-align: center; padding: 2rem; color: var(--light-grey);">
            <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>${filterText} arama kriterlerinize uygun sonuç bulunamadı</p>
            <small>Farklı arama terimleri deneyin${filterType ? ' veya filtreyi kaldırın' : ''}</small>
            ${filterType ? `<br><button onclick="clearFilter()" style="margin-top: 1rem; background: var(--red); color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Filtreyi Kaldır</button>` : ''}
        </div>
    `;
}

function showSearchError(message) {
    hideSearchElements();
    resultsContainer.style.display = 'block';
    resultsContainer.innerHTML = `
        <div style="text-align: center; padding: 2rem; color: var(--red);">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
            <p>${escapeHtml(message)}</p>
            <small>Lütfen daha sonra tekrar deneyin</small>
        </div>
    `;
}

function focusSearchForSlot(slot) {
    searchInput.focus();
    
    // Highlight the slot temporarily
    slot.style.borderColor = 'var(--turquase)';
    slot.style.boxShadow = '0 0 10px rgba(115, 228, 224, 0.3)';
    
    setTimeout(() => {
        slot.style.borderColor = '';
        slot.style.boxShadow = '';
    }, 2000);
}

// WEAPON ATTACHMENT FUNCTIONS - YENİ

async function createAttachmentSlots(parentSlotId) {
    console.log('🔧 Creating attachment slots for parent slot:', parentSlotId);
    
    try {
        // URL'i düzelt - relative path sorununu çöz
        const apiUrl = `api/get_attachment_slots.php?parent_slot_id=${parentSlotId}`;
        console.log('🔧 Calling API:', apiUrl);
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin' // Session cookies'i gönder
        });
        
        console.log('🔧 Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const responseText = await response.text();
        console.log('🔧 Raw response text:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('🔧 JSON parse error:', jsonError);
            console.error('🔧 Response was:', responseText);
            throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
        }
        
        console.log('🔧 Parsed API response:', data);
        
        if (!data.success) {
            console.error('🔧 API returned error:', data.error);
            // Fallback yapalım hata durumunda
            createFallbackAttachmentSlots(parentSlotId);
            return;
        }
        
        const attachmentSlotDefs = data.data || [];
        console.log('🔧 Attachment slot definitions:', attachmentSlotDefs);
        
        if (attachmentSlotDefs.length === 0) {
            console.warn('🔧 No attachment slots found for parent slot:', parentSlotId);
            createFallbackAttachmentSlots(parentSlotId);
            return;
        }
        
        // Store attachment slots
        attachmentSlots.set(parentSlotId, attachmentSlotDefs);
        
        // Update slot type mapping for attachments
        updateAttachmentSlotMapping(attachmentSlotDefs);
        
        // Create UI elements
        createAttachmentSlotsUI(parentSlotId, attachmentSlotDefs);
        
        console.log('✅ Attachment slots created successfully for parent slot:', parentSlotId);
        
    } catch (error) {
        console.error('❌ Error creating attachment slots:', error);
        
        // Fallback - basit attachment slotları oluştur
        console.log('🔧 Attempting fallback attachment slots creation...');
        createFallbackAttachmentSlots(parentSlotId);
    }
}

// FALLBACK ATTACHMENT SLOTS - API çalışmazsa kullan
function createFallbackAttachmentSlots(parentSlotId) {
    console.log('🔧 Creating fallback attachment slots for parent slot:', parentSlotId);
    
    // Fallback attachment slot tanımları
    const fallbackSlots = [
        {
            id: 1,
            slot_name: 'Nişangah/Optik',
            slot_type: 'IronSight',
            icon_class: 'fas fa-crosshairs'
        },
        {
            id: 2,
            slot_name: 'Namlu Eklentisi',
            slot_type: 'Barrel',
            icon_class: 'fas fa-long-arrow-alt-right'
        },
        {
            id: 3,
            slot_name: 'Alt Bağlantı',
            slot_type: 'BottomAttachment',
            icon_class: 'fas fa-grip-lines'
        }
    ];
    
    // Store attachment slots
    attachmentSlots.set(parentSlotId, fallbackSlots);
    
    // Update slot type mapping
    updateAttachmentSlotMapping(fallbackSlots);
    
    // Create UI elements
    createAttachmentSlotsUI(parentSlotId, fallbackSlots);
}

function updateAttachmentSlotMapping(attachmentSlotDefs) {
    // Clear previous mappings
    SLOT_TYPE_MAPPING['IronSight'] = [];
    SLOT_TYPE_MAPPING['Barrel'] = [];
    SLOT_TYPE_MAPPING['BottomAttachment'] = [];
    
    // Update mappings with dynamic slot names
    attachmentSlotDefs.forEach(slotDef => {
        if (SLOT_TYPE_MAPPING[slotDef.slot_type]) {
            SLOT_TYPE_MAPPING[slotDef.slot_type].push(slotDef.slot_name);
        }
    });
    
    console.log('Updated attachment slot mappings:', SLOT_TYPE_MAPPING);
}

function createAttachmentSlotsUI(parentSlotId, attachmentSlotDefs) {
    const parentSlot = document.querySelector(`[data-slot-id="${parentSlotId}"]`);
    if (!parentSlot) {
        console.error('Parent slot not found:', parentSlotId);
        return;
    }
    
    console.log('Creating attachment UI for parent slot:', parentSlotId, 'with', attachmentSlotDefs.length, 'attachment slots');
    
    // Remove existing attachment slots
    removeAttachmentSlots(parentSlotId);
    
    // Create attachment slots container
    const attachmentContainer = document.createElement('div');
    attachmentContainer.className = 'attachment-slots-container';
    attachmentContainer.id = `attachment-slots-${parentSlotId}`;
    
    // Add header
    const header = document.createElement('h4');
    header.innerHTML = '<i class="fas fa-puzzle-piece"></i> Silah Eklentileri';
    header.style.margin = '0 0 1rem 0';
    header.style.color = 'var(--gold)';
    header.style.fontSize = '1rem';
    header.style.fontWeight = '500';
    header.style.display = 'flex';
    header.style.alignItems = 'center';
    header.style.gap = '0.5rem';
    header.style.borderBottom = '1px solid var(--border-1)';
    header.style.paddingBottom = '0.5rem';
    attachmentContainer.appendChild(header);
    
    // Create slots grid
    const slotsGrid = document.createElement('div');
    slotsGrid.style.display = 'grid';
    slotsGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
    slotsGrid.style.gap = '1rem';
    
    attachmentSlotDefs.forEach(slotDef => {
        const attachmentSlot = createAttachmentSlotElement(parentSlotId, slotDef);
        slotsGrid.appendChild(attachmentSlot);
    });
    
    attachmentContainer.appendChild(slotsGrid);
    
    // Insert after parent slot in the DOM
    const parentSlotContainer = parentSlot.closest('.equipment-slot');
    if (parentSlotContainer && parentSlotContainer.parentNode) {
        // Try to insert after the parent slot
        const nextSibling = parentSlotContainer.nextSibling;
        if (nextSibling) {
            parentSlotContainer.parentNode.insertBefore(attachmentContainer, nextSibling);
        } else {
            parentSlotContainer.parentNode.appendChild(attachmentContainer);
        }
    } else {
        // Fallback: insert after the slots container
        const slotsContainer = document.querySelector('.slots-container');
        if (slotsContainer) {
            slotsContainer.appendChild(attachmentContainer);
        }
    }
    
    console.log('✅ Attachment container inserted into DOM:', attachmentContainer.id);
    
    // Initialize attachment slots data structure
    if (!weaponAttachments.has(parentSlotId)) {
        weaponAttachments.set(parentSlotId, new Map());
    }
}

function createAttachmentSlotElement(parentSlotId, slotDef) {
    const attachmentSlot = document.createElement('div');
    attachmentSlot.className = 'attachment-slot';
    attachmentSlot.dataset.parentSlotId = parentSlotId;
    attachmentSlot.dataset.attachmentSlotId = slotDef.id;
    attachmentSlot.dataset.slotType = slotDef.slot_type;
    attachmentSlot.dataset.slotName = slotDef.slot_name;
    
    attachmentSlot.innerHTML = `
        <div class="attachment-slot-header">
            <span class="attachment-slot-name">
                <i class="${slotDef.icon_class || 'fas fa-puzzle-piece'}"></i>
                ${escapeHtml(slotDef.slot_name)}
            </span>
            <button type="button" class="attachment-clear-btn" onclick="clearAttachmentSlot(${parentSlotId}, ${slotDef.id})" style="display: none;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="attachment-slot-content">
            <div class="attachment-empty">
                <i class="${slotDef.icon_class || 'fas fa-puzzle-piece'}"></i>
                <div>Boş</div>
                <small>${escapeHtml(slotDef.slot_type)}</small>
            </div>
            <div class="attachment-item" style="display: none;">
                <div class="attachment-info">
                    <span class="attachment-name"></span>
                    <small class="attachment-manufacturer"></small>
                </div>
            </div>
        </div>
    `;
    
    // Event listeners
    attachmentSlot.addEventListener('click', function() {
        if (!this.classList.contains('has-attachment')) {
            focusSearchForAttachment(this);
        }
    });
    
    // Drag and drop - DÜZELTİLMİŞ
    attachmentSlot.addEventListener('dragover', handleAttachmentDragOver);
    attachmentSlot.addEventListener('drop', handleAttachmentDrop);
    attachmentSlot.addEventListener('dragleave', handleAttachmentDragLeave);
    
    return attachmentSlot;
}

function removeAttachmentSlots(parentSlotId) {
    const existingContainer = document.getElementById(`attachment-slots-${parentSlotId}`);
    if (existingContainer) {
        existingContainer.remove();
    }
    
    // Clear data
    weaponAttachments.delete(parentSlotId);
    attachmentSlots.delete(parentSlotId);
}

function assignAttachmentToSlot(attachment, parentSlotId, attachmentSlotId) {
    const attachmentSlot = document.querySelector(
        `[data-parent-slot-id="${parentSlotId}"][data-attachment-slot-id="${attachmentSlotId}"]`
    );
    
    if (!attachmentSlot) {
        console.error('Attachment slot not found:', parentSlotId, attachmentSlotId);
        return;
    }
    
    // Store attachment data
    if (!weaponAttachments.has(parentSlotId)) {
        weaponAttachments.set(parentSlotId, new Map());
    }
    weaponAttachments.get(parentSlotId).set(attachmentSlotId, attachment);
    
    // Update UI
    const attachmentEmpty = attachmentSlot.querySelector('.attachment-empty');
    const attachmentItem = attachmentSlot.querySelector('.attachment-item');
    const clearBtn = attachmentSlot.querySelector('.attachment-clear-btn');
    
    attachmentSlot.classList.add('has-attachment');
    attachmentEmpty.style.display = 'none';
    attachmentItem.style.display = 'block';
    clearBtn.style.display = 'inline-flex';
    
    // Update attachment display
    const attachmentName = attachmentItem.querySelector('.attachment-name');
    const attachmentManufacturer = attachmentItem.querySelector('.attachment-manufacturer');
    
    attachmentName.textContent = attachment.name || 'Unknown Attachment';
    attachmentManufacturer.textContent = attachment.manufacturer?.name || 'Unknown Manufacturer';
    
    // Visual feedback
    attachmentSlot.style.transform = 'scale(1.05)';
    setTimeout(() => {
        attachmentSlot.style.transform = '';
    }, 200);
    
    console.log('✅ Attachment assigned successfully:', attachment.name);
    showMessage(`${attachment.name || 'Attachment'} ${attachmentSlot.dataset.slotName} slotuna eklendi`, 'success');
}

function clearAttachmentSlot(parentSlotId, attachmentSlotId) {
    const attachmentSlot = document.querySelector(
        `[data-parent-slot-id="${parentSlotId}"][data-attachment-slot-id="${attachmentSlotId}"]`
    );
    
    if (!attachmentSlot) return;
    
    // Remove from data
    if (weaponAttachments.has(parentSlotId)) {
        weaponAttachments.get(parentSlotId).delete(attachmentSlotId);
    }
    
    // Update UI
    const attachmentEmpty = attachmentSlot.querySelector('.attachment-empty');
    const attachmentItem = attachmentSlot.querySelector('.attachment-item');
    const clearBtn = attachmentSlot.querySelector('.attachment-clear-btn');
    
    attachmentSlot.classList.remove('has-attachment');
    attachmentEmpty.style.display = 'block';
    attachmentItem.style.display = 'none';
    clearBtn.style.display = 'none';
    
    // Visual feedback
    attachmentSlot.style.transform = 'scale(0.95)';
    setTimeout(() => {
        attachmentSlot.style.transform = '';
    }, 200);
}

function focusSearchForAttachment(attachmentSlot) {
    const slotType = attachmentSlot.dataset.slotType;
    
    // Focus search
    searchInput.focus();
    searchInput.placeholder = `${attachmentSlot.dataset.slotName} için arama yapın...`;
    
    // Highlight the attachment slot
    attachmentSlot.style.borderColor = 'var(--turquase)';
    attachmentSlot.style.boxShadow = '0 0 10px rgba(115, 228, 224, 0.3)';
    
    setTimeout(() => {
        attachmentSlot.style.borderColor = '';
        attachmentSlot.style.boxShadow = '';
        searchInput.placeholder = 'Item adı yazın (örn: Morozov, Helmet)';
    }, 3000);
}

// Attachment Drag and Drop Functions - DÜZELTİLMİŞ
function handleAttachmentDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'copy';
    this.classList.add('attachment-drag-over');
    console.log('Attachment drag over:', this.dataset.slotName);
}

function handleAttachmentDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('attachment-drag-over');
    
    console.log('Attachment drop on:', this.dataset.slotName, 'expected type:', this.dataset.slotType);
    
    try {
        const attachment = JSON.parse(e.dataTransfer.getData('application/json'));
        const parentSlotId = parseInt(this.dataset.parentSlotId);
        const attachmentSlotId = parseInt(this.dataset.attachmentSlotId);
        const expectedType = this.dataset.slotType;
        
        console.log('Dropped attachment:', attachment.name, 'type:', attachment.type, 'sub_type:', attachment.sub_type);
        console.log('Expected type:', expectedType);
        
        // Attachment uyumluluk kontrolü - DÜZELTİLMİŞ
        if (attachment.type === 'WeaponAttachment') {
            let isCompatible = false;
            
            // Sub_type kontrolü
            if (attachment.sub_type === expectedType) {
                isCompatible = true;
            } else {
                // Alternative matching
                const typeMapping = {
                    'IronSight': ['IronSight', 'Optic', 'Sight'],
                    'Barrel': ['Barrel', 'Muzzle'],
                    'BottomAttachment': ['BottomAttachment', 'Utility', 'Magazine', 'Underbarrel']
                };
                
                if (typeMapping[expectedType]) {
                    isCompatible = typeMapping[expectedType].some(type => 
                        attachment.sub_type.includes(type) || type.includes(attachment.sub_type)
                    );
                }
            }
            
            if (isCompatible) {
                assignAttachmentToSlot(attachment, parentSlotId, attachmentSlotId);
            } else {
                showMessage(`${attachment.name || 'Bu attachment'} ${this.dataset.slotName} slotuna uyumlu değil (${expectedType} gerekli, ${attachment.sub_type} var)`, 'error');
            }
        } else {
            showMessage(`Bu item bir silah eklentisi değil`, 'error');
        }
    } catch (error) {
        console.error('Attachment drop error:', error);
        showMessage('Attachment yerleştirme hatası', 'error');
    }
}

function handleAttachmentDragLeave(e) {
    e.preventDefault();
    this.classList.remove('attachment-drag-over');
}

// DÜZELTİLMİŞ FORM SUBMISSION
function handleFormSubmission(e) {
    e.preventDefault();
    
    console.log('Form submission started');
    console.log('Current loadout items:', loadoutItems);
    console.log('Current weapon attachments:', weaponAttachments);
    
    const form = e.target;
    console.log('Form element:', form);
    console.log('Form action attribute:', form.getAttribute('action'));
    
    const formData = new FormData(form);
    
    // Validation
    const setName = formData.get('set_name');
    const setDescription = formData.get('set_description');
    
    if (!setName || !setDescription) {
        showMessage('Set adı ve açıklama gereklidir', 'error');
        return;
    }
    
    // Add loadout items to form data
    const itemsData = {};
    loadoutItems.forEach((item, slotId) => {
        itemsData[slotId] = {
            item_name: item.name || '',
            item_api_uuid: item.uuid || '',
            item_type_api: item.type || '',
            item_manufacturer_api: item.manufacturer?.name || '',
            item_notes: ''
        };
    });
    
    formData.append('loadout_items', JSON.stringify(itemsData));
    console.log('Items data:', itemsData);
    
    // Add weapon attachments to form data
    const attachmentsData = {};
    weaponAttachments.forEach((attachmentMap, parentSlotId) => {
        attachmentsData[parentSlotId] = {};
        attachmentMap.forEach((attachment, attachmentSlotId) => {
            attachmentsData[parentSlotId][attachmentSlotId] = {
                attachment_item_name: attachment.name || '',
                attachment_item_uuid: attachment.uuid || '',
                attachment_item_type: attachment.sub_type || attachment.type || '',
                attachment_item_manufacturer: attachment.manufacturer?.name || '',
                attachment_notes: ''
            };
        });
    });
    
    formData.append('weapon_attachments', JSON.stringify(attachmentsData));
    console.log('Attachments data:', attachmentsData);
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    submitBtn.disabled = true;
    
    // Form action URL'ini düzelt - getAttribute kullan çünkü form.action input element döndürebilir
    let formAction = form.getAttribute('action');
    if (!formAction || formAction.includes('[object')) {
        formAction = 'actions/save_loadout.php'; // Fallback
    }
    console.log('Using form action:', formAction);
    
    // Submit form
    fetch(formAction, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(responseText => {
        console.log('Raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            console.error('Response was:', responseText);
            throw new Error('Sunucudan geçersiz yanıt alındı');
        }
        
        console.log('Parsed response:', data);
        
        if (data.success) {
            showMessage(data.message || 'Başarıyla kaydedildi', 'success');
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
        } else {
            showMessage(data.message || 'Kaydetme sırasında bir hata oluştu', 'error');
        }
    })
    .catch(error => {
        console.error('Submit error:', error);
        showMessage('Kaydetme sırasında bir hata oluştu: ' + error.message, 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Load existing items (for edit mode) - WEAPON ATTACHMENTS İLE GÜNCELLENMİŞ
async function loadExistingItems(existingItems) {
    console.log('Loading existing items:', existingItems);
    
    // First load main items
    for (const itemData of existingItems) {
        const slotId = itemData.equipment_slot_id;
        if (!slotId) continue;
        
        const slot = document.querySelector(`[data-slot-id="${slotId}"]`);
        if (!slot) continue;
        
        // Create item object from existing data
        const item = {
            name: itemData.item_name,
            uuid: itemData.item_api_uuid,
            type: itemData.item_type_api,
            manufacturer: {
                name: itemData.item_manufacturer_api
            }
        };
        
        assignItemToSlot(item, slot);
    }
    
    // Then load existing attachments if in edit mode
    const loadoutId = getLoadoutIdFromUrl();
    if (loadoutId) {
        // Wait a bit for attachment slots to be created
        setTimeout(() => {
            loadExistingAttachments(loadoutId);
        }, 500);
    }
}

async function loadExistingAttachments(loadoutSetId) {
    try {
        console.log('Loading existing attachments for loadout:', loadoutSetId);
        
        const response = await fetch('api/get_attachment_slots.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                loadout_set_id: loadoutSetId
            }),
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('Failed to load existing attachments:', data.error);
            return;
        }
        
        // Load attachments grouped by parent slot
        const groupedAttachments = data.grouped || {};
        console.log('Grouped attachments:', groupedAttachments);
        
        for (const [parentSlotId, attachments] of Object.entries(groupedAttachments)) {
            const parentSlotIdInt = parseInt(parentSlotId);
            
            // Ensure attachment slots are created first
            if (WEAPON_SLOTS.includes(parentSlotIdInt) && loadoutItems.has(parentSlotIdInt)) {
                // Load each attachment
                for (const attachmentData of attachments) {
                    const attachment = {
                        name: attachmentData.attachment_item_name,
                        uuid: attachmentData.attachment_item_uuid,
                        type: attachmentData.attachment_item_type,
                        sub_type: attachmentData.attachment_item_type,
                        manufacturer: {
                            name: attachmentData.attachment_item_manufacturer
                        }
                    };
                    
                    assignAttachmentToSlot(
                        attachment,
                        parentSlotIdInt,
                        attachmentData.attachment_slot_id
                    );
                }
            }
        }
        
        console.log('Loaded existing attachments successfully');
        
    } catch (error) {
        console.error('Error loading existing attachments:', error);
    }
}

function getLoadoutIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const editParam = urlParams.get('edit');
    return editParam ? parseInt(editParam) : null;
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showMessage(message, type = 'info') {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${escapeHtml(message)}</span>
    `;
    
    // Insert at top of page
    const container = document.querySelector('.loadout-page-container');
    if (container) {
        container.insertBefore(messageDiv, container.firstChild);
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => {
            messageDiv.remove();
        }, 300);
    }, 5000);
}

// Export functions for global access - GÜNCELLENMİŞ
window.clearSlot = clearSlot;
window.clearAttachmentSlot = clearAttachmentSlot;
window.closeModal = closeModal;
window.searchItems = searchItems;
window.loadExistingItems = loadExistingItems;
window.clearFilter = clearFilter;
window.createAttachmentSlots = createAttachmentSlots;
window.assignAttachmentToSlot = assignAttachmentToSlot;