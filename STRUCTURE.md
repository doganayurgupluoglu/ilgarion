# Proje Klasör ve Dosya Yapısı

## Kök Dizin
```
├── public/           # Genel erişime açık dosyalar
├── src/             # Kaynak kodlar
└── assets/          # Statik dosyalar
```

## public/ Dizini
```
├── actions/
│   └── mark_notification_read.php
│
├── admin/
│   ├── audit_log.php
│   ├── discussions.php
│   ├── edit_gallery_photo.php
│   ├── edit_loadout_items.php
│   ├── edit_loadout_set_details.php
│   ├── edit_user_roles.php
│   ├── events.php
│   ├── gallery.php
│   ├── guides.php
│   ├── index.php
│   ├── manage_loadout_sets.php
│   ├── manage_roles.php
│   ├── manage_super_admins.php
│   ├── new_loadout_set.php
│   ├── system_security.php
│   ├── test.php
│   ├── test_super_admin.php
│   └── users.php
│
├── api/
│
├── css/
│
├── favicon_io/
│
├── gallery/
│
├── js/
│
├── uploads/
│
├── api_item_search_test.php
├── create_event.php
├── discussion_detail.php
├── discussions.php
├── edit_event.php
├── edit_hangar.php
├── edit_profile.php
├── event_detail.php
├── events.php
├── gallery.php
├── guide_detail.php
├── guides.php
├── index.php
├── loadout_detail.php
├── loadouts.php
├── logout.php
├── members.php
├── new_discussion_topic.php
├── new_guide.php
├── notifications.php
├── profile.php
├── register.php
├── upload_photo.php
├── user_discussions.php
├── user_gallery.php
├── view_hangar.php
└── view_profile.php
```

## src/ Dizini
```
├── actions/        # İşlem fonksiyonları
├── api/           # API işlemleri
├── config/        # Yapılandırma dosyaları
├── database/      # Veritabanı işlemleri
├── functions/     # Yardımcı fonksiyonlar
└── includes/      # Dahil edilen dosyalar
```

## assets/ Dizini
```
└── (Statik dosyalar)
```

Bu yapı, projenin tam dosya ve klasör organizasyonunu göstermektedir. Her bir dizin kendi içinde ilgili dosyaları ve alt dizinleri barındırmaktadır.

### Önemli Notlar:
1. `public/` dizini web sunucusunun doğrudan erişebildiği dosyaları içerir
2. `src/` dizini kaynak kodları ve iş mantığını içerir
3. `assets/` dizini statik dosyaları (resimler, fontlar vb.) içerir
4. Admin paneli `public/admin/` dizininde bulunmaktadır
5. Tüm PHP sayfaları `public/` dizininde yer almaktadır 