// /events/loadouts/js/create_loadout.js - Tip Filtresi Düzeltildi

// Global variables
let currentSearchResults = [];
let loadoutItems = new Map(); // slot_id -> item_data
let isSearching = false;

// CSRF token
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// API Configuration - Kendi proxy'imizi kullan
const API_CONFIG = {
    BASE_URL: 'api.php', // Aynı klasördeki proxy
    TIMEOUT: 30000,
    MAX_RESULTS: 50
};

// Item type mapping for slot compatibility
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
    'toolattachment': ['Multi-Tool Attachment']
};

// DOM Elements
const searchInput = document.getElementById('item_search');
const typeFilter = document.getElementById('type_filter');
const searchBtn = document.getElementById('search_btn');
const searchResults = document.querySelector('.search-results');
const searchPlaceholder = document.querySelector('.search-placeholder');
const searchLoading = document.querySelector('.search-loading');
const resultsContainer = document.getElementById('search_results_container');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeSlots();
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

function getCompatibleSlots(item) {
    const compatibleSlots = [];
    const itemType = item.type || '';
    const itemSubType = item.sub_type || '';
    
    console.log('Checking compatibility for:', itemType, itemSubType);
    
    // Type-based compatibility
    Object.entries(SLOT_TYPE_MAPPING).forEach(([type, slotNames]) => {
        if (itemType.includes(type) || itemSubType.includes(type)) {
            compatibleSlots.push(...slotNames);
            console.log('Compatible with:', slotNames, 'due to type:', type);
        }
    });

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
    console.log('Final compatible slots:', uniqueSlots);
    return uniqueSlots;
}

function assignItemToFirstCompatibleSlot(item, compatibleSlots) {
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
    const slotId = slot.dataset.slotId;
    const slotContent = slot.querySelector('.slot-content');
    const slotEmpty = slot.querySelector('.slot-empty');
    const slotItem = slot.querySelector('.slot-item');
    const clearBtn = slot.querySelector('.slot-clear-btn');

    // Store item data
    loadoutItems.set(parseInt(slotId), item);

    // Update UI
    slot.classList.add('has-item');
    slotEmpty.style.display = 'none';
    slotItem.style.display = 'flex';
    clearBtn.style.display = 'inline-flex';

    // Update item display - görsel olmadan
    const itemName = slotItem.querySelector('.item-name');
    const itemManufacturer = slotItem.querySelector('.item-manufacturer');

    itemName.textContent = item.name || 'Unknown Item';
    itemManufacturer.textContent = item.manufacturer?.name || 'Unknown Manufacturer';

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
    loadoutItems.delete(slotId);

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

// Form Submission
function handleFormSubmission(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
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
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    submitBtn.disabled = true;
    
    // Submit form
    fetch(e.target.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
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
        showMessage('Kaydetme sırasında bir hata oluştu', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Load existing items (for edit mode)
function loadExistingItems(existingItems) {
    existingItems.forEach(itemData => {
        const slotId = itemData.equipment_slot_id;
        if (!slotId) return;
        
        const slot = document.querySelector(`[data-slot-id="${slotId}"]`);
        if (!slot) return;
        
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
    });
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
    container.insertBefore(messageDiv, container.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => {
            messageDiv.remove();
        }, 300);
    }, 5000);
}

// Export functions for global access
window.clearSlot = clearSlot;
window.closeModal = closeModal;
window.searchItems = searchItems;
window.loadExistingItems = loadExistingItems;
window.clearFilter = clearFilter;