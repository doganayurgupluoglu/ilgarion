// js/hangar.js - Hangar Yönetimi JavaScript

// Sepet sistemi
let cart = [];

// API'den gemi arama
async function searchShips() {
    const searchName = document.getElementById('search_name').value.trim();
    const searchClassification = document.getElementById('search_classification').value;
    
    const loadingEl = document.getElementById('searchLoading');
    const resultsEl = document.getElementById('searchResults');
    
    // Yükleme göster
    loadingEl.style.display = 'block';
    resultsEl.innerHTML = '';
    
    try {
        const params = new URLSearchParams();
        
        if (searchName) {
            params.append('name', searchName);
        }
        
        if (searchClassification) {
            params.append('classification', searchClassification);
        }
        
        const response = await fetch(`../api/search_ships.php?${params.toString()}`);
        const data = await response.json();
        
        // Yükleme gizle
        loadingEl.style.display = 'none';
        
        if (!data.success) {
            resultsEl.innerHTML = `
                <div class="search-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Arama hatası: ${data.message}</p>
                </div>
            `;
            return;
        }
        
        if (data.data.length === 0) {
            resultsEl.innerHTML = `
                <div class="search-empty">
                    <i class="fas fa-search"></i>
                    <p>Arama kriterlerinize uygun gemi bulunamadı.</p>
                </div>
            `;
            return;
        }
        
        // Sonuçları göster
        displaySearchResults(data.data);
        
    } catch (error) {
        loadingEl.style.display = 'none';
        resultsEl.innerHTML = `
            <div class="search-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Arama sırasında bir hata oluştu: ${error.message}</p>
            </div>
        `;
        console.error('Search error:', error);
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

// Gemi düzenleme
function editShip(shipId) {
    // AJAX ile gemi bilgilerini al ve modal'ı doldur
    fetch(`../api/get_ship.php?id=${shipId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editShipId').value = shipId;
                document.getElementById('edit_quantity').value = data.ship.quantity;
                document.getElementById('edit_notes').value = data.ship.user_notes || '';
                document.getElementById('editShipModal').style.display = 'flex';
            } else {
                alert('Gemi bilgileri yüklenemedi.');
            }
        })
        .catch(error => {
            console.error('Edit ship error:', error);
            alert('Bir hata oluştu.');
        });
}

function closeEditShipModal() {
    document.getElementById('editShipModal').style.display = 'none';
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