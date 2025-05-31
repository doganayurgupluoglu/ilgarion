<?php
// src/includes/admin_quick_navigation.php

// ... (dosyanızın üst kısmı aynı kalacak) ...
// get_auth_base_url fonksiyonu artık mevcut olmalı
$adminBaseUrl = get_auth_base_url(); // /public döner

?>
<style>
    .admin-quick-navigation {
    margin-bottom: 20px;
    margin-top: 20px;
    padding: 25px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
}

.admin-quick-navigation h2 {
    font-size: 1.6rem;
    color: var(--gold);
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-1);
    font-family: var(--font);
}

.admin-navigation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.admin-navigation-grid .btn {
    display: flex; /* İkon ve metni yan yana getirmek için */
    align-items: center;
    justify-content: flex-start; /* İçeriği sola yasla */
    text-align: left;
    padding: 12px 18px;
    font-size: 1rem;
    font-weight: 500;
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.admin-navigation-grid .btn:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
    transform: none; /* .btn'den gelen hover efektini ezelim */
}

.admin-navigation-grid .btn i.fas {
    margin-right: 12px;
    font-size: 1.1em;
    width: 20px; /* İkonlar için sabit genişlik */
    text-align: center;
    color: var(--light-gold); /* İkon rengi */
}
.admin-navigation-grid .btn:hover i.fas {
    color: var(--gold);
}
.btn-outline-secondary {
    color: var(--lighter-grey);
    background-color: transparent;
    border: 1px solid var(--grey); /* Temanızdaki gri tonuyla kenarlık */
}

.btn-outline-secondary:hover,
.btn-outline-secondary:focus { /* Odaklanma durumunu da ekledim */
    color: var(--white);
    background-color: var(--grey); /* Üzerine gelince dolgu rengi */
    border-color: var(--grey); /* Kenarlık rengi aynı kalabilir veya vurgulanabilir */
    /* İsteğe bağlı: Hafif bir gölge veya transform efekti eklenebilir
    box-shadow: 0 0 5px var(--transparent-gold);
    transform: translateY(-1px); */
}

/* Eğer .btn sınıfınızda temel geçiş (transition) yoksa, buraya ekleyebilirsiniz */
.btn {
    /* ... mevcut .btn stilleriniz ... */
    transition: color .15s ease-in-out, background-color .15s ease-in-out, border-color .15s ease-in-out, box-shadow .15s ease-in-out, transform .2s ease-in-out;
}
.admin-container {
    width: 100%;
    max-width: 1600px; /* Admin içeriği için genişlik */
    min-height: calc(100vh - 150px - 130px); 
    margin: 0 auto;
    padding: 30px 20px;
    color: var(--lighter-grey);
} .admin-quick-navigation {
    margin-bottom: 20px;
    margin-top: 20px;
    padding: 25px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
}

.admin-quick-navigation h2 {
    font-size: 1.6rem;
    color: var(--gold);
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-1);
    font-family: var(--font);
}

.admin-navigation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Genişlik biraz ayarlandı */
    gap: 15px;
}

.admin-navigation-grid .btn {
    display: flex; 
    align-items: center;
    justify-content: flex-start; 
    text-align: left;
    padding: 12px 18px;
    font-size: 0.95rem; /* Font boyutu biraz ayarlandı */
    font-weight: 500;
    background-color: var(--grey);
    color: var(--lighter-grey);
    border: 1px solid var(--darker-gold-1);
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    border-radius: 6px; /* Köşeler biraz daha yumuşak */
}
.admin-navigation-grid .btn:hover {
    background-color: var(--darker-gold-1);
    color: var(--gold);
    border-color: var(--gold);
    transform: translateY(-2px); /* Hafif yukarı kalkma efekti */
}

.admin-navigation-grid .btn i.fas {
    margin-right: 12px;
    font-size: 1.05em; /* İkon boyutu biraz ayarlandı */
    width: 20px; 
    text-align: center;
    color: var(--light-gold); 
}
.admin-navigation-grid .btn:hover i.fas {
    color: var(--gold);
}
</style>
<div class="admin-quick-navigation">
    <h2>Hızlı Yönetim Linkleri</h2>
    <div class="admin-navigation-grid">
        <a href="/public/admin/index.php" class="btn">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="/public/admin/users.php" class="btn">
            <i class="fas fa-user-cog"></i> Kullanıcı Yönetimi
        </a>
        <a href="/public/admin/manage_roles.php" class="btn">
            <i class="fas fa-user-tag"></i> Rol Yönetimi
        </a>
        <a href="/public/admin/events.php" class="btn">
            <i class="fas fa-calendar-check"></i> Etkinlik Yönetimi
        </a>
        <a href="/public/admin/gallery.php" class="btn">
            <i class="fas fa-photo-video"></i> Galeri Yönetimi
        </a>
        <a href="/public/admin/discussions.php" class="btn">
            <i class="fas fa-comments"></i> Tartışma Yönetimi
        </a>
        <a href="/public/admin/guides.php" class="btn">
            <i class="fas fa-book-open"></i> Rehber Yönetimi
        </a>
        <a href="/public/admin/manage_loadout_sets.php" class="btn">
            <i class="fas fa-user-shield"></i> Teçhizat Setleri
        </a>
        <!-- <a href="/public/admin/equipment_slots.php" class="btn">
            <i class="fas fa-tasks"></i> Ekipman Slotları
        </a> -->
        <?php // Buraya ileride başka admin linkleri eklenebilir ?>
    </div>
</div>
