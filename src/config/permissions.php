<?php
// src/config/permissions.php

/**
 * Sitedeki tüm yetkilerin tanımlandığı dizi.
 * Anahtar: Yetkinin benzersiz kodu (kod içinde kullanılacak)
 * Değer: Yetkinin admin panelinde görünecek açıklaması
 */
return [
    // Genel Admin Yetkileri
    'admin.panel.access' => 'Admin Paneline Erişim',
    'admin.settings.view' => 'Site Ayarlarını Görüntüleme',
    'admin.settings.edit' => 'Site Ayarlarını Düzenleme',

    // Kullanıcı Yönetimi Yetkileri
    'admin.users.view' => 'Kullanıcıları Listeleme/Görüntüleme',
    'admin.users.edit_status' => 'Kullanıcı Durumunu Değiştirme (onayla/reddet/askıya al)',
    'admin.users.assign_roles' => 'Kullanıcılara Rol Atama/Kaldırma',
    'admin.users.delete' => 'Kullanıcı Silme',

    // Rol Yönetimi Yetkileri
    'admin.roles.view' => 'Rolleri Listeleme/Görüntüleme',
    'admin.roles.create' => 'Yeni Rol Oluşturma',
    'admin.roles.edit' => 'Rol Düzenleme (isim, renk, yetkiler)',
    'admin.roles.delete' => 'Rol Silme',

    // Etkinlik Yetkileri
    'event.view_public' => 'Herkese Açık Etkinlikleri Görüntüleme',
    'event.view_members_only' => 'Sadece Üyelere Özel Etkinlikleri Görüntüleme',
    'event.view_faction_only' => 'Sadece Fraksiyona Özel Etkinlikleri Görüntüleme',
    'event.view_all' => 'Tüm Etkinlikleri Görüntüleme (admin/yetkili)',
    'event.create' => 'Yeni Etkinlik Oluşturma',
    'event.edit_own' => 'Kendi Etkinliğini Düzenleme',
    'event.edit_all' => 'Tüm Etkinlikleri Düzenleme (admin/yetkili)',
    'event.delete_own' => 'Kendi Etkinliğini Silme',
    'event.delete_all' => 'Tüm Etkinlikleri Silme (admin/yetkili)',
    'event.participate' => 'Etkinliğe Katılım Durumu Bildirme',
    'event.manage_participants' => 'Etkinlik Katılımcılarını Yönetme (admin/etkinlik sahibi)',

    // Galeri Yetkileri
    'gallery.view_public' => 'Herkese Açık Galeriyi Görüntüleme',
    'gallery.view_approved' => 'Onaylı Üyelere Açık Galeriyi Görüntüleme', // Genel galeri için
    'gallery.upload' => 'Galeriye Fotoğraf Yükleme',
    'gallery.like' => 'Galeri Fotoğraflarını Beğenme',
    'gallery.delete_own' => 'Kendi Fotoğrafını Silme',
    'gallery.delete_any' => 'Herhangi Bir Fotoğrafı Silme (admin/yetkili)',
    'gallery.manage_all' => 'Tüm Galeri İçeriğini Yönetme (admin)',

    // Tartışma Yetkileri
    'discussion.view_public' => 'Herkese Açık Tartışmaları Görüntüleme',
    'discussion.members_only' => 'Onaylı Üyelere Açık Tartışmaları Görüntüleme',
    'discussion.view_approved'=> 'Onaylı Üyelere Açık Tartışmaları Görüntüleme',
    'discussion.topic.create' => 'Yeni Tartışma Konusu Başlatma',
    'discussion.post.create' => 'Tartışmalara Yorum Yazma',
    'discussion.topic.edit_own' => 'Kendi Konusunu Düzenleme',
    'discussion.topic.edit_all' => 'Tüm Konuları Düzenleme (admin/moderatör)',
    'discussion.post.edit_own' => 'Kendi Yorumunu Düzenleme',
    'discussion.post.edit_all' => 'Tüm Yorumları Düzenleme (admin/moderatör)',
    'discussion.topic.delete_own' => 'Kendi Konusunu Silme',
    'discussion.topic.delete_all' => 'Tüm Konuları Silme (admin/moderatör)',
    'discussion.post.delete_own' => 'Kendi Yorumunu Silme',
    'discussion.post.delete_all' => 'Tüm Yorumları Silme (admin/moderatör)',
    'discussion.topic.lock' => 'Konu Kilitleme/Açma (admin/moderatör)',
    'discussion.topic.pin' => 'Konu Sabitleme/Kaldırma (admin/moderatör)',

    // Rehber Yetkileri
    'guide.view_public' => 'Herkese Açık Rehberleri Görüntüleme',
    'guide.view_members_only' => 'Sadece Üyelere Özel Rehberleri Görüntüleme',
    'guide.view_faction_only' => 'Sadece Fraksiyona Özel Rehberleri Görüntüleme',
    'guide.view_all' => 'Tüm Rehberleri Görüntüleme (admin/yetkili)', // Taslaklar dahil
    'guide.create' => 'Yeni Rehber Oluşturma',
    'guide.edit_own' => 'Kendi Rehberini Düzenleme',
    'guide.edit_all' => 'Tüm Rehberleri Düzenleme (admin/yetkili)',
    'guide.delete_own' => 'Kendi Rehberini Silme',
    'guide.delete_all' => 'Tüm Rehberleri Silme (admin/yetkili)',
    'guide.like' => 'Rehberleri Beğenme',

    // Teçhizat Seti Yetkileri
    'loadout.view_public' => 'Herkese Açık Teçhizat Setlerini Görüntüleme',
    'loadout.view_members_only' => 'Sadece Üyelere Özel Teçhizat Setlerini Görüntüleme',
    'loadout.view_published' => 'Yayınlanmış Teçhizat Setlerini Görüntüleme (üyeler için)',
    'loadout.manage_sets' => 'Teçhizat Setlerini Yönetme (oluşturma, düzenleme, silme - admin)',
    'loadout.manage_items' => 'Teçhizat Seti Itemlerini Yönetme (admin)',
    'loadout.manage_slots' => 'Ekipman Slotlarını Yönetme (admin)',

    // Profil Yetkileri
    'profile.view_own' => 'Kendi Profilini Görüntüleme',
    'profile.edit_own' => 'Kendi Profilini Düzenleme',
    // 'profile.view_any' => 'Herhangi Bir Kullanıcının Profilini Görüntüleme (admin/mod)',
    // 'profile.edit_any' => 'Herhangi Bir Kullanıcının Profilini Düzenleme (admin)',


    // Diğer Potansiyel Yetkiler
    // 'notifications.view' => 'Bildirimleri Görüntüleme',
    // ... sitenizin özelliklerine göre daha fazla yetki eklenebilir.
];
