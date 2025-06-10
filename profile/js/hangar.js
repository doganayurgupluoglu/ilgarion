// js/hangar.js - Hangar Yönetimi JavaScript

// Sepet sistemi
let cart = [];

// API'den gemi arama
async function searchShips() {
    console.log('searchShips başladı');
    
    const searchName = document.getElementById('search_name')?.value.trim() || '';
    const searchClassification = document.getElementById('search_classification')?.value || '';
    
    const loadingEl = document.getElementById('searchLoading');
    const resultsEl = document.getElementById('searchResults');
    
    // Yükleme göster
    if (loadingEl) loadingEl.style.display = 'block';
    if (resultsEl) resultsEl.innerHTML = '';
    
    try {
        const params = new URLSearchParams();
        
        if (searchName) {
            params.append('name', searchName);
        }
        
        if (searchClassification) {
            params.append('classification', searchClassification);
        }
        
        // URL'yi dinamik olarak oluştur
        const currentPath = window.location.pathname;
        const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
        const apiUrl = basePath + 'api/search_ships.php' + (params.toString() ? '?' + params.toString() : '');
        
        console.log('API URL:', apiUrl);
        console.log('Tam URL:', window.location.origin + apiUrl);
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', Object.fromEntries(response.headers.entries()));
        
        // Raw response'u al
        const rawText = await response.text();
        console.log('Raw response length:', rawText.length);
        console.log('Raw response preview:', rawText.substring(0, 200));
        
        // JSON parse et
        let data;
        try {
            data = JSON.parse(rawText);
            console.log('Parsed data:', data);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            console.log('Non-JSON response:', rawText);
            
            if (rawText.includes('<!DOCTYPE') || rawText.includes('<html')) {
                throw new Error('Sunucu HTML döndürdü - PHP hatası olabilir');
            } else if (rawText.includes('Fatal error') || rawText.includes('Parse error')) {
                throw new Error('PHP hatası: ' + rawText.match(/Fatal error[^<]*/)?.[0] || 'Bilinmeyen PHP hatası');
            } else if (rawText.trim() === '') {
                throw new Error('Sunucudan boş yanıt alındı');
            } else {
                throw new Error('Geçersiz JSON yanıtı');
            }
        }
        
        // Yükleme gizle
        if (loadingEl) loadingEl.style.display = 'none';
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${data.message || 'Sunucu hatası'}`);
        }
        
        if (!data.success) {
            console.log('API error response:', data);
            if (resultsEl) {
                resultsEl.innerHTML = `
                    <div class="search-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>API Hatası: ${data.message}</p>
                        ${data.debug ? `
                            <details style="margin-top: 1rem;">
                                <summary>Debug Bilgileri (Geliştirici için)</summary>
                                <pre style="background: #2a2a2a; color: #fff; padding: 1rem; border-radius: 4px; font-size: 12px; overflow: auto; max-height: 300px;">${JSON.stringify(data.debug, null, 2)}</pre>
                            </details>
                        ` : ''}
                        <button class="btn btn-secondary btn-sm" onclick="searchShips()" style="margin-top: 1rem;">
                            <i class="fas fa-redo"></i> Tekrar Dene
                        </button>
                    </div>
                `;
            }
            return;
        }
        
        if (!data.data || data.data.length === 0) {
            if (resultsEl) {
                resultsEl.innerHTML = `
                    <div class="search-empty">
                        <i class="fas fa-search"></i>
                        <p>Arama kriterlerinize uygun gemi bulunamadı.</p>
                        <small>Farklı arama terimleri deneyin.</small>
                    </div>
                `;
            }
            return;
        }
        
        // Başarılı - sonuçları göster
        console.log('Displaying results:', data.data.length, 'ships');
        displaySearchResults(data.data);
        
    } catch (error) {
        console.error('Search error:', error);
        if (loadingEl) loadingEl.style.display = 'none';
        
        if (resultsEl) {
            resultsEl.innerHTML = `
                <div class="search-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Hata: ${error.message}</p>
                    <details style="margin-top: 1rem;">
                        <summary>Teknik Detaylar</summary>
                        <pre style="background: #2a2a2a; color: #fff; padding: 1rem; border-radius: 4px; font-size: 12px;">${error.stack || error.message}</pre>
                    </details>
                    <button class="btn btn-secondary btn-sm" onclick="searchShips()" style="margin-top: 1rem;">
                        <i class="fas fa-redo"></i> Tekrar Dene
                    </button>
                </div>
            `;
        }
    }
}

// Arama sonuçlarını göster
function displaySearchResults(ships) {
    const resultsEl = document.getElementById('searchResults');
    
    let html = `<div class="ships-search-grid">`;
    
    ships.forEach(ship => {
        const imageUrl = ship.media && ship.media.length > 0 ? ship.media[0].source_url : '/assets/ship-placeholder.png';
        const manufacturer = ship.manufacturer ? ship.manufacturer.name : 'Bilinmeyen';
        const price = ship.price ? `${ship.price}` : 'Fiyat Yok';
        
        html += `
            <div class="search-ship-card" data-ship='${JSON.stringify(ship)}'>
                <div class="search-ship-image">
                    <img src="${imageUrl}" alt="${ship.name}" onerror="this.src='/assets/ship-placeholder.png'">
                    <div class="ship-overlay">
                        <button class="btn btn-add-to-cart" onclick="addToCart(this)">
                            <i class="fas fa-cart-plus"></i> Sepete Ekle
                        </button>
                    </div>
                </div>
                <div class="search-ship-info">
                    <h4 class="search-ship-name">${ship.name}</h4>
                    <div class="search-ship-details">
                        <span class="manufacturer">${manufacturer}</span>
                        ${ship.size ? `<span class="size">${ship.size}</span>` : ''}
                        ${ship.focus ? `<span class="focus">${ship.focus}</span>` : ''}
                    </div>
                    <div class="search-ship-specs">
                        ${ship.max_crew ? `<span><i class="fas fa-users"></i> ${ship.max_crew} crew</span>` : ''}
                        <span class="price">${price}</span>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `</div>`;
    resultsEl.innerHTML = html;
}

// Sepete ekleme
function addToCart(button) {
    const shipCard = button.closest('.search-ship-card');
    const shipData = JSON.parse(shipCard.dataset.ship);
    
    // Sepette zaten var mı kontrol et
    const existingIndex = cart.findIndex(item => item.id === shipData.id);
    
    if (existingIndex !== -1) {
        // Miktarı artır
        cart[existingIndex].quantity++;
    } else {
        // Yeni öğe ekle
        cart.push({
            ...shipData,
            quantity: 1
        });
    }
    
    updateCartUI();
    
    // Başarı mesajı
    button.innerHTML = '<i class="fas fa-check"></i> Eklendi';
    button.style.background = 'var(--turquase)';
    setTimeout(() => {
        button.innerHTML = '<i class="fas fa-cart-plus"></i> Sepete Ekle';
        button.style.background = '';
    }, 2000);
}

// Sepet UI güncellemesi
function updateCartUI() {
    const cartButton = document.getElementById('cartButton');
    const cartCount = document.getElementById('cartCount');
    
    if (cart.length > 0) {
        cartButton.style.display = 'inline-flex';
        cartCount.textContent = cart.length;
    } else {
        cartButton.style.display = 'none';
    }
}

// Sepet modalını aç
function openCartModal() {
    if (cart.length === 0) {
        alert('Sepetiniz boş.');
        return;
    }
    
    displayCartItems();
    document.getElementById('cartModal').style.display = 'flex';
}

// Sepet öğelerini göster
function displayCartItems() {
    const cartItemsEl = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        cartItemsEl.innerHTML = `
            <div class="cart-empty">
                <i class="fas fa-shopping-cart"></i>
                <p>Sepetiniz boş.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    cart.forEach((ship, index) => {
        const imageUrl = ship.media && ship.media.length > 0 ? ship.media[0].source_url : '/assets/ship-placeholder.png';
        const manufacturer = ship.manufacturer ? ship.manufacturer.name : 'Bilinmeyen';
        
        html += `
            <div class="cart-item">
                <div class="cart-item-image">
                    <img src="${imageUrl}" alt="${ship.name}" onerror="this.src='/assets/ship-placeholder.png'">
                </div>
                <div class="cart-item-info">
                    <h4 class="cart-item-name">${ship.name}</h4>
                    <div class="cart-item-details">
                        <span>${manufacturer}</span>
                        ${ship.size ? `<span>${ship.size}</span>` : ''}
                        ${ship.focus ? `<span>${ship.focus}</span>` : ''}
                    </div>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button onclick="changeQuantity(${index}, -1)" class="qty-btn">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="quantity">${ship.quantity}</span>
                        <button onclick="changeQuantity(${index}, 1)" class="qty-btn">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <button onclick="removeFromCart(${index})" class="remove-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItemsEl.innerHTML = html;
}

// Miktar değiştirme
function changeQuantity(index, change) {
    cart[index].quantity = Math.max(1, cart[index].quantity + change);
    displayCartItems();
    updateCartUI();
}

// Sepetten kaldırma
function removeFromCart(index) {
    cart.splice(index, 1);
    displayCartItems();
    updateCartUI();
}

// Sepeti temizle
function clearCart() {
    if (confirm('Sepeti tamamen temizlemek istediğinizden emin misiniz?')) {
        cart = [];
        updateCartUI();
        displayCartItems();
    }
}

// Sepeti hangara kaydet
async function saveCartToHangar() {
    if (cart.length === 0) {
        alert('Sepetiniz boş.');
        return;
    }
    
    if (!confirm(`${cart.length} gemiyi hangarınıza eklemek istediğinizden emin misiniz?`)) {
        return;
    }
    
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="add_ships_from_cart">
            <input type="hidden" name="cart_ships" value='${JSON.stringify(cart)}'>
        `;
        document.body.appendChild(form);
        form.submit();
        
    } catch (error) {
        alert('Kaydetme sırasında bir hata oluştu: ' + error.message);
        console.error('Save error:', error);
    }
}

// Arama temizleme
function clearSearch() {
    document.getElementById('search_name').value = '';
    document.getElementById('search_classification').value = '';
    document.getElementById('searchResults').innerHTML = `
        <div class="search-help">
            <i class="fas fa-info-circle"></i>
            Gemi aramak için yukarıdaki alanları kullanın. Boş bırakırsanız tüm gemiler listelenir.
        </div>
    `;
}

// Modal fonksiyonları
function openShipSearchModal() {
    document.getElementById('shipSearchModal').style.display = 'flex';
}

function closeShipSearchModal() {
    document.getElementById('shipSearchModal').style.display = 'none';
}

function closeCartModal() {
    document.getElementById('cartModal').style.display = 'none';
}

// Gemi silme
function deleteShip(shipId) {
    if (!confirm('Bu gemiyi hangarınızdan kaldırmak istediğinizden emin misiniz?')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_ship">
        <input type="hidden" name="ship_id" value="${shipId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Enter tuşu ile arama
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_name');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchShips();
            }
        });
    }

    // Modal dışı tıklama ile kapatma
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });

    // ESC tuşu ile modal kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
});