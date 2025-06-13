-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 13 Haz 2025, 02:27:33
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `ilgarion_turanis_db`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL COMMENT 'Yetki anahtarı (örn: admin.users.view)',
  `permission_name` varchar(255) NOT NULL COMMENT 'Yetki açıklaması',
  `permission_group` varchar(50) NOT NULL COMMENT 'Yetki grubu (örn: admin, event, gallery)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `permission_group`, `is_active`, `created_at`) VALUES
(1, 'admin.panel.access', 'Admin Paneline Erişim', 'admin', 1, '2025-05-31 22:01:11'),
(2, 'admin.settings.view', 'Site Ayarlarını Görüntüleme', 'admin', 1, '2025-05-31 22:01:11'),
(3, 'admin.settings.edit', 'Site Ayarlarını Düzenleme', 'admin', 1, '2025-05-31 22:01:11'),
(4, 'admin.users.view', 'Kullanıcıları Listeleme/Görüntüleme', 'admin', 1, '2025-05-31 22:01:11'),
(5, 'admin.users.edit_status', 'Kullanıcı Durumunu Değiştirme', 'admin', 1, '2025-05-31 22:01:11'),
(6, 'admin.users.assign_roles', 'Kullanıcılara Rol Atama/Kaldırma', 'admin', 1, '2025-05-31 22:01:11'),
(7, 'admin.users.delete', 'Kullanıcı Silme', 'admin', 1, '2025-05-31 22:01:11'),
(8, 'admin.roles.view', 'Rolleri Listeleme/Görüntüleme', 'admin', 1, '2025-05-31 22:01:11'),
(9, 'admin.roles.create', 'Yeni Rol Oluşturma', 'admin', 1, '2025-05-31 22:01:11'),
(10, 'admin.roles.edit', 'Rol Düzenleme', 'admin', 1, '2025-05-31 22:01:11'),
(11, 'admin.roles.delete', 'Rol Silme', 'admin', 1, '2025-05-31 22:01:11'),
(12, 'event.view_public', 'Herkese Açık Etkinlikleri Görüntüleme', 'event', 1, '2025-05-31 22:01:11'),
(13, 'event.view_members_only', 'Sadece Üyelere Özel Etkinlikleri Görüntüleme', 'event', 1, '2025-05-31 22:01:11'),
(14, 'event.view_faction_only', 'Sadece Fraksiyona Özel Etkinlikleri Görüntüleme', 'event', 1, '2025-05-31 22:01:11'),
(15, 'event.view_all', 'Tüm Etkinlikleri Görüntüleme', 'event', 1, '2025-05-31 22:01:11'),
(16, 'event.create', 'Yeni Etkinlik Oluşturma', 'event', 1, '2025-05-31 22:01:11'),
(17, 'event.edit_own', 'Kendi Etkinliğini Düzenleme', 'event', 1, '2025-05-31 22:01:11'),
(18, 'event.edit_all', 'Tüm Etkinlikleri Düzenleme', 'event', 1, '2025-05-31 22:01:11'),
(19, 'event.delete_own', 'Kendi Etkinliğini Silme', 'event', 1, '2025-05-31 22:01:11'),
(20, 'event.delete_all', 'Tüm Etkinlikleri Silme', 'event', 1, '2025-05-31 22:01:11'),
(21, 'event.participate', 'Etkinliğe Katılım Durumu Bildirme', 'event', 1, '2025-05-31 22:01:11'),
(22, 'event.manage_participants', 'Etkinlik Katılımcılarını Yönetme', 'event', 1, '2025-05-31 22:01:11'),
(23, 'gallery.view_public', 'Herkese Açık Galeriyi Görüntüleme', 'gallery', 1, '2025-05-31 22:01:11'),
(24, 'gallery.view_approved', 'Onaylı Üyelere Açık Galeriyi Görüntüleme', 'gallery', 1, '2025-05-31 22:01:11'),
(25, 'gallery.upload', 'Galeriye Fotoğraf Yükleme', 'gallery', 1, '2025-05-31 22:01:11'),
(26, 'gallery.like', 'Galeri Fotoğraflarını Beğenme', 'gallery', 1, '2025-05-31 22:01:11'),
(27, 'gallery.delete_own', 'Kendi Fotoğrafını Silme', 'gallery', 1, '2025-05-31 22:01:11'),
(28, 'gallery.delete_any', 'Herhangi Bir Fotoğrafı Silme', 'gallery', 1, '2025-05-31 22:01:11'),
(29, 'gallery.manage_all', 'Tüm Galeri İçeriğini Yönetme', 'gallery', 1, '2025-05-31 22:01:11'),
(55, 'loadout.view_public', 'Herkese Açık Teçhizat Setlerini Görüntüleme', 'loadout', 1, '2025-05-31 22:01:11'),
(56, 'loadout.view_members_only', 'Sadece Üyelere Özel Teçhizat Setlerini Görüntüleme', 'loadout', 1, '2025-05-31 22:01:11'),
(57, 'loadout.view_published', 'Yayınlanmış Teçhizat Setlerini Görüntüleme', 'loadout', 1, '2025-05-31 22:01:11'),
(58, 'loadout.manage_sets', 'Teçhizat Setlerini Yönetme', 'loadout', 1, '2025-05-31 22:01:11'),
(59, 'loadout.manage_items', 'Teçhizat Seti Itemlerini Yönetme', 'loadout', 1, '2025-05-31 22:01:11'),
(60, 'loadout.manage_slots', 'Ekipman Slotlarını Yönetme', 'loadout', 1, '2025-05-31 22:01:11'),
(63, 'admin.audit_log.view', 'Audit Log Görüntüleme', 'admin', 1, '2025-05-31 23:38:43'),
(64, 'admin.audit_log.export', 'Audit Log Dışa Aktarma', 'admin', 1, '2025-05-31 23:38:43'),
(65, 'admin.system.security', 'Sistem Güvenlik Yönetimi', 'admin', 1, '2025-05-31 23:38:43'),
(76, 'gallery.comment.create', 'Galeri Fotoğraflarına Yorum Yazma', 'gallery', 1, '2025-06-02 02:17:54'),
(77, 'gallery.comment.edit_own', 'Kendi Yorumunu Düzenleme', 'gallery', 1, '2025-06-02 02:17:54'),
(78, 'gallery.comment.edit_all', 'Tüm Yorumları Düzenleme', 'gallery', 1, '2025-06-02 02:17:54'),
(79, 'gallery.comment.delete_own', 'Kendi Yorumunu Silme', 'gallery', 1, '2025-06-02 02:17:54'),
(80, 'gallery.comment.delete_all', 'Tüm Yorumları Silme', 'gallery', 1, '2025-06-02 02:17:54'),
(81, 'gallery.comment.like', 'Yorumları Beğenme', 'gallery', 1, '2025-06-02 02:17:54'),
(82, 'forum.view_public', 'Herkese Açık Forum İçeriğini Görüntüleme', 'forum', 1, '2025-06-03 17:07:32'),
(83, 'forum.view_members_only', 'Sadece Üyelere Özel Forum İçeriğini Görüntüleme', 'forum', 1, '2025-06-03 17:07:32'),
(84, 'forum.view_faction_only', 'Sadece Fraksiyona Özel Forum İçeriğini Görüntüleme', 'forum', 1, '2025-06-03 17:07:32'),
(85, 'forum.topic.create', 'Forum Konusu Oluşturma', 'forum', 1, '2025-06-03 17:07:32'),
(86, 'forum.topic.reply', 'Forum Konularına Yanıt Verme', 'forum', 1, '2025-06-03 17:07:32'),
(87, 'forum.topic.edit_own', 'Kendi Forum Konusunu Düzenleme', 'forum', 1, '2025-06-03 17:07:32'),
(88, 'forum.topic.edit_all', 'Tüm Forum Konularını Düzenleme', 'forum', 1, '2025-06-03 17:07:32'),
(89, 'forum.topic.delete_own', 'Kendi Forum Konusunu Silme', 'forum', 1, '2025-06-03 17:07:32'),
(90, 'forum.topic.delete_all', 'Tüm Forum Konularını Silme', 'forum', 1, '2025-06-03 17:07:32'),
(91, 'forum.topic.pin', 'Forum Konularını Sabitleme', 'forum', 1, '2025-06-03 17:07:32'),
(92, 'forum.topic.lock', 'Forum Konularını Kilitleme', 'forum', 1, '2025-06-03 17:07:32'),
(93, 'forum.post.edit_own', 'Kendi Forum Gönderisini Düzenleme', 'forum', 1, '2025-06-03 17:07:32'),
(94, 'forum.post.edit_all', 'Tüm Forum Gönderilerini Düzenleme', 'forum', 1, '2025-06-03 17:07:32'),
(95, 'forum.post.delete_own', 'Kendi Forum Gönderisini Silme', 'forum', 1, '2025-06-03 17:07:32'),
(96, 'forum.post.delete_all', 'Tüm Forum Gönderilerini Silme', 'forum', 1, '2025-06-03 17:07:32'),
(97, 'forum.post.like', 'Forum Gönderilerini Beğenme', 'forum', 1, '2025-06-03 17:07:32'),
(98, 'forum.category.manage', 'Forum Kategorilerini Yönetme', 'forum', 1, '2025-06-03 17:07:32'),
(99, 'forum.tags.manage', 'Forum Etiketlerini Yönetme', 'forum', 1, '2025-06-03 17:07:32'),
(100, 'forum.view_all_categories', 'Tüm Forum Kategorilerini Görüntüleme', 'forum', 1, '2025-06-04 02:31:13'),
(101, 'loadout.manage_weapon_attachments', 'Silah Attachment\'larını Yönetme', 'loadout', 1, '2025-06-06 18:24:13'),
(102, 'event_role.view_public', 'Herkese Açık Etkinlik Rollerini Görüntüleme', 'event_role', 1, '2025-06-07 14:44:03'),
(103, 'event_role.view_members_only', 'Sadece Üyelere Özel Etkinlik Rollerini Görüntüleme', 'event_role', 1, '2025-06-07 14:44:03'),
(104, 'event_role.view_all', 'Tüm Etkinlik Rollerini Görüntüleme', 'event_role', 1, '2025-06-07 14:44:03'),
(105, 'event_role.create', 'Yeni Etkinlik Rolü Oluşturma', 'event_role', 1, '2025-06-07 14:44:03'),
(106, 'event_role.edit_own', 'Kendi Oluşturduğu Etkinlik Rollerini Düzenleme', 'event_role', 1, '2025-06-07 14:44:03'),
(107, 'event_role.edit_all', 'Tüm Etkinlik Rollerini Düzenleme', 'event_role', 1, '2025-06-07 14:44:03'),
(108, 'event_role.delete_own', 'Kendi Oluşturduğu Etkinlik Rollerini Silme', 'event_role', 1, '2025-06-07 14:44:03'),
(109, 'event_role.delete_all', 'Tüm Etkinlik Rollerini Silme', 'event_role', 1, '2025-06-07 14:44:03'),
(110, 'event_role.assign_to_events', 'Etkinliklere Rol Atama', 'event_role', 1, '2025-06-07 14:44:03'),
(111, 'event_role.join', 'Etkinlik Rollerine Katılım', 'event_role', 1, '2025-06-07 14:44:03'),
(112, 'event_role.manage_participants', 'Etkinlik Rol Katılımcılarını Yönetme', 'event_role', 1, '2025-06-07 14:44:03'),
(113, 'event_role.manage_requirements', 'Etkinlik Rol Gereksinimlerini Yönetme', 'event_role', 1, '2025-06-07 14:44:03'),
(114, 'event_role.verify_skills', 'Kullanıcı Skill Tag\'lerini Onaylama', 'event_role', 1, '2025-06-07 14:44:03'),
(115, 'event_role.view_statistics', 'Etkinlik Rol İstatistiklerini Görüntüleme', 'event_role', 1, '2025-06-07 14:44:03'),
(116, 'event_role.manage_all', 'Tüm Etkinlik Rol Sistemi Yönetimi', 'event_role', 1, '2025-06-07 14:44:03'),
(117, 'skill_tag.view_own', 'Kendi Skill Tag\'lerini Görüntüleme', 'skill_tag', 1, '2025-06-07 14:44:03'),
(118, 'skill_tag.view_all', 'Tüm Kullanıcı Skill Tag\'lerini Görüntüleme', 'skill_tag', 1, '2025-06-07 14:44:03'),
(119, 'skill_tag.add_own', 'Kendine Skill Tag Ekleme', 'skill_tag', 1, '2025-06-07 14:44:03'),
(121, 'skill_tag.manage_all', 'Tüm Skill Tag\'leri Yönetme', 'skill_tag', 1, '2025-06-07 14:44:03'),
(122, 'loadout.create_sets', 'Teçhizat Seti Oluşturma', 'loadout', 1, '2025-06-07 14:44:03'),
(123, 'loadout.edit_own_sets', 'Kendi Teçhizat Setlerini Düzenleme', 'loadout', 1, '2025-06-07 14:44:03'),
(124, 'loadout.edit_all_sets', 'Tüm Teçhizat Setlerini Düzenleme', 'loadout', 1, '2025-06-07 14:44:03'),
(125, 'loadout.delete_own_sets', 'Kendi Teçhizat Setlerini Silme', 'loadout', 1, '2025-06-07 14:44:03'),
(126, 'loadout.delete_all_sets', 'Tüm Teçhizat Setlerini Silme', 'loadout', 1, '2025-06-07 14:44:03'),
(127, 'loadout.publish_sets', 'Teçhizat Setlerini Yayınlama', 'loadout', 1, '2025-06-07 14:44:03'),
(128, 'loadout.approve_sets', 'Teçhizat Setlerini Onaylama', 'loadout', 1, '2025-06-07 14:44:03');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key_UNIQUE` (`permission_key`),
  ADD KEY `idx_permission_group` (`permission_group`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
