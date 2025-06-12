/* view/js/hangar_ships.js - Hangar Ships Specific JavaScript */

document.addEventListener('DOMContentLoaded', function() {
    // Hangar specific functionality
    initializeHangar();
});

function initializeHangar() {
    // Ship card interactions
    initializeShipCards();
    
    // Filter and sort functionality
    initializeShipFilters();
    
    // Ship comparison feature
    initializeShipComparison();
    
    // Hangar statistics
    updateHangarStatistics();
}

function initializeShipCards() {
    const shipCards = document.querySelectorAll('.ship-card');
    
    shipCards.forEach(card => {
        // Hover effects and interactions
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) rotateX(5deg)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) rotateX(0)';
        });
        
        // Ship detail toggle
        const shipInfo = card.querySelector('.ship-info');
        const shipSpecs = card.querySelector('.ship-specs');
        
        if (shipSpecs) {
            shipSpecs.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleShipDetails(card);
            });
        }
    });
}

function toggleShipDetails(card) {
    const details = card.querySelector('.ship-details-expanded');
    
    if (details) {
        details.remove();
    } else {
        const expandedDetails = createExpandedDetails(card);
        card.appendChild(expandedDetails);
    }
}

function createExpandedDetails(card) {
    const details = document.createElement('div');
    details.className = 'ship-details-expanded';
    details.innerHTML = `
        <div class="expanded-specs">
            <h4>Detaylı Özellikler</h4>
            <div class="spec-grid">
                <div class="spec-item">
                    <span class="spec-label">Hız</span>
                    <span class="spec-value">1,200 m/s</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Zırh</span>
                    <span class="spec-value">Orta</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Yakıt Kapasitesi</span>
                    <span class="spec-value">583 L</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Silah Yuvaları</span>
                    <span class="spec-value">4 x S3</span>
                </div>
            </div>
        </div>
    `;
    
    details.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--card-bg-2);
        border: 1px solid var(--border-1);
        border-top: none;
        border-radius: 0 0 12px 12px;
        padding: 1rem;
        z-index: 10;
        animation: slideDown 0.3s ease;
    `;
    
    return details;
}

function initializeShipFilters() {
    // Manufacturer filter
    const manufacturerFilter = document.querySelector('#manufacturer-filter');
    if (manufacturerFilter) {
        manufacturerFilter.addEventListener('change', applyFilters);
    }
    
    // Size filter
    const sizeFilter = document.querySelector('#size-filter');
    if (sizeFilter) {
        sizeFilter.addEventListener('change', applyFilters);
    }
    
    // Role filter
    const roleFilter = document.querySelector('#role-filter');
    if (roleFilter) {
        roleFilter.addEventListener('change', applyFilters);
    }
    
    // Sort functionality
    const sortSelect = document.querySelector('#sort-ships');
    if (sortSelect) {
        sortSelect.addEventListener('change', sortShips);
    }
}

function applyFilters() {
    const manufacturer = document.querySelector('#manufacturer-filter')?.value || 'all';
    const size = document.querySelector('#size-filter')?.value || 'all';
    const role = document.querySelector('#role-filter')?.value || 'all';
    
    const ships = document.querySelectorAll('.ship-card');
    
    ships.forEach(ship => {
        const shipManufacturer = ship.dataset.manufacturer;
        const shipSize = ship.dataset.size;
        const shipRole = ship.dataset.role;
        
        const showManufacturer = manufacturer === 'all' || shipManufacturer === manufacturer;
        const showSize = size === 'all' || shipSize === size;
        const showRole = role === 'all' || shipRole === role;
        
        if (showManufacturer && showSize && showRole) {
            ship.style.display = 'block';
            ship.style.animation = 'fadeInUp 0.3s ease';
        } else {
            ship.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                ship.style.display = 'none';
            }, 300);
        }
    });
    
    updateFilteredCount();
}

function sortShips() {
    const sortBy = document.querySelector('#sort-ships')?.value || 'name';
    const container = document.querySelector('.ships-grid');
    const ships = Array.from(container.querySelectorAll('.ship-card'));
    
    ships.sort((a, b) => {
        switch (sortBy) {
            case 'name':
                return a.dataset.name.localeCompare(b.dataset.name);
            case 'manufacturer':
                return a.dataset.manufacturer.localeCompare(b.dataset.manufacturer);
            case 'size':
                const sizeOrder = { 'small': 1, 'medium': 2, 'large': 3, 'capital': 4 };
                return sizeOrder[a.dataset.size] - sizeOrder[b.dataset.size];
            case 'date':
                return new Date(b.dataset.acquired) - new Date(a.dataset.acquired);
            default:
                return 0;
        }
    });
    
    // Animate out
    ships.forEach(ship => {
        ship.style.animation = 'fadeOut 0.2s ease';
    });
    
    // Reorder and animate in
    setTimeout(() => {
        ships.forEach(ship => {
            container.appendChild(ship);
            ship.style.animation = 'fadeInUp 0.3s ease';
        });
    }, 200);
}

function initializeShipComparison() {
    const compareButtons = document.querySelectorAll('.compare-ship');
    const comparisonPanel = document.getElementById('comparison-panel');
    let selectedShips = [];
    
    compareButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const shipCard = this.closest('.ship-card');
            toggleShipComparison(shipCard);
        });
    });
}

function toggleShipComparison(shipCard) {
    // Implementation for ship comparison feature
    const shipName = shipCard.dataset.name;
    console.log(`Toggling comparison for ${shipName}`);
    
    // Add visual feedback
    shipCard.classList.toggle('selected-for-comparison');
}

function updateHangarStatistics() {
    const ships = document.querySelectorAll('.ship-card');
    const stats = {
        total: ships.length,
        manufacturers: new Set(),
        sizes: { small: 0, medium: 0, large: 0, capital: 0 },
        totalValue: 0
    };
    
    ships.forEach(ship => {
        stats.manufacturers.add(ship.dataset.manufacturer);
        stats.sizes[ship.dataset.size]++;
        stats.totalValue += parseInt(ship.dataset.value || 0);
    });
    
    // Update stats display
    const statsContainer = document.querySelector('.hangar-statistics');
    if (statsContainer) {
        statsContainer.innerHTML = `
            <div class="stat-item">
                <span class="stat-label">Toplam Gemi</span>
                <span class="stat-value">${stats.total}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Üretici Sayısı</span>
                <span class="stat-value">${stats.manufacturers.size}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">En Çok</span>
                <span class="stat-value">${getMostCommonSize(stats.sizes)}</span>
            </div>
        `;
    }
}

function getMostCommonSize(sizes) {
    let maxCount = 0;
    let mostCommon = 'small';
    
    Object.entries(sizes).forEach(([size, count]) => {
        if (count > maxCount) {
            maxCount = count;
            mostCommon = size;
        }
    });
    
    const sizeNames = {
        small: 'Küçük',
        medium: 'Orta',
        large: 'Büyük',
        capital: 'Capital'
    };
    
    return sizeNames[mostCommon];
}

function updateFilteredCount() {
    const visibleShips = document.querySelectorAll('.ship-card[style*="block"], .ship-card:not([style*="none"])');
    const countDisplay = document.querySelector('.filtered-count');
    
    if (countDisplay) {
        countDisplay.textContent = `${visibleShips.length} gemi görüntüleniyor`;
    }
}

// Hangar specific utilities
function getShipImage(shipName) {
    // Placeholder function for ship images
    return `/assets/images/ships/${shipName.toLowerCase().replace(/\s+/g, '-')}.jpg`;
}

function formatShipValue(value) {
    if (value >= 1000000) {
        return (value / 1000000).toFixed(1) + 'M aUEC';
    } else if (value >= 1000) {
        return (value / 1000).toFixed(0) + 'K aUEC';
    }
    return value + ' aUEC';
} 