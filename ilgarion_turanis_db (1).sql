-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 01 Haz 2025, 11:31:33
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
-- Tablo için tablo yapısı `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'role, user, permission vb.',
  `target_id` int(11) DEFAULT NULL COMMENT 'Hedef objenin IDsi',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Değişiklik öncesi değerler' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Değişiklik sonrası değerler' CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussion_posts`
--

CREATE TABLE `discussion_posts` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL COMMENT 'Bu mesajın hangi konuya ait olduğu',
  `user_id` int(11) NOT NULL COMMENT 'Bu mesajı yazan kullanıcının IDsi',
  `parent_post_id` int(11) DEFAULT NULL COMMENT 'Eğer bu bir yanıtsa, yanıtlanan mesajın IDsi',
  `content` text NOT NULL COMMENT 'Mesajın veya yorumun içeriği',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Mesajın oluşturulma zamanı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `discussion_posts`
--

INSERT INTO `discussion_posts` (`id`, `topic_id`, `user_id`, `parent_post_id`, `content`, `created_at`) VALUES
(24, 9, 2, NULL, 'Değerli SCG organizasyon üyeleri ve kıymetli takım arkadaşlarımız,\r\n\r\nILGARION TURANIS web sitemizi sizler için daha işlevsel, erişilebilir ve estetik hale getirmek adına sürekli olarak geliştirmeye çalışıyoruz. Bu süreçte sizlerin geri bildirimleri bizim için son derece önemli.\r\n\r\nWeb sitemizde görmek istediğiniz yeni özellikler, iyileştirmemizi düşündüğünüz bölümler ya da genel olarak kullanıcı deneyimini artıracak her türlü önerinizi bu konu başlığı altında bizimle paylaşabilirsiniz.\r\n\r\nBelki bir içerik eksikliği fark ettiniz, belki daha iyi bir navigasyon yapısı önerebilirsiniz ya da sadece genel bir fikir belirtmek istiyorsunuz — her türlü düşünce bizim için kıymetlidir.\r\n\r\nUnutmayın, bu platform hepimizin ortak kullanımına açık bir yapı taşı ve ancak birlikte şekillendirebiliriz. Katkılarınız hem topluluğumuzu güçlendirecek hem de siteyi daha kaliteli bir hale getirecektir.\r\n\r\nŞimdiden zaman ayırıp geri bildirimde bulunduğunuz için teşekkür ederiz.\r\n\r\n— ILGARION TURANIS Yönetim Ekibi', '2025-06-01 01:59:26'),
(25, 9, 1, NULL, 'Harika', '2025-06-01 02:00:28'),
(26, 9, 2, 25, '>[ALINTI=\"admin\"]\r\nHarika\r\n[/ALINTI]\r\n\r\nMüthiş', '2025-06-01 02:01:56');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussion_topics`
--

CREATE TABLE `discussion_topics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Konuyu açan kullanıcının IDsi',
  `title` varchar(255) NOT NULL COMMENT 'Konu başlığı',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Konunun oluşturulma zamanı',
  `last_reply_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Konuya son cevap yazılma zamanı',
  `reply_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Konudaki toplam cevap sayısı (ilk mesaj hariç)',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Konu yorumlara kapalı mı? (0=Hayır, 1=Evet)',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Konu sabitlenmişse true (1)',
  `is_public_no_auth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1: Herkese açık (giriş yapmayanlar dahil)',
  `is_members_only` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Sadece onaylı tüm üyelere açık (varsayılan)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `discussion_topics`
--

INSERT INTO `discussion_topics` (`id`, `user_id`, `title`, `created_at`, `last_reply_at`, `reply_count`, `is_locked`, `is_pinned`, `is_public_no_auth`, `is_members_only`) VALUES
(9, 2, 'ILGARION TURANIS Web Sitesi – Görüş ve Önerilerinize Açığız!', '2025-06-01 01:59:26', '2025-06-01 02:01:56', 2, 0, 0, 0, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussion_topic_visibility_roles`
--

CREATE TABLE `discussion_topic_visibility_roles` (
  `topic_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tartışma konularının hangi rollere özel olduğunu belirtir';

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `equipment_slots`
--

CREATE TABLE `equipment_slots` (
  `id` int(11) NOT NULL,
  `slot_name` varchar(100) NOT NULL,
  `slot_type` varchar(100) DEFAULT NULL COMMENT 'Bu slota uygun item tipi (API filtrelemesi için)',
  `is_standard` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Standart slot, 0: Kullanıcı tanımlı',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Slotların gösterim sırası için'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `equipment_slots`
--

INSERT INTO `equipment_slots` (`id`, `slot_name`, `slot_type`, `is_standard`, `display_order`) VALUES
(1, 'Kask', 'Char_Armor_Helmet', 1, 10),
(2, 'Gövde Zırhı', 'Char_Armor_Torso', 1, 20),
(3, 'Kol Zırhları', 'Char_Armor_Arms', 1, 30),
(4, 'Bacak Zırhları', 'Char_Armor_Legs', 1, 40),
(5, 'Alt Giyim', 'Char_Clothing_Undersuit,Char_Armor_Undersuit', 1, 50),
(6, 'Sırt Çantası', 'Char_Armor_Backpack', 1, 60),
(7, 'Birincil Silah 1', 'WeaponPersonal', 1, 70),
(8, 'Birincil Silah 2 (Sırtta)', 'WeaponPersonal', 1, 80),
(9, 'İkincil Silah (Tabanca veya Medgun)', 'WeaponPersonal', 1, 90),
(10, 'Yardımcı Modül/Gadget 1', 'weaponpersonal,gadget', 1, 100),
(12, 'Medikal Araç', 'fps_consumable,medical,gadget', 1, 120),
(13, 'Multi-Tool Attachment', 'tool_multitool,tool,weaponattachment,toolattachment', 1, 130);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL COMMENT 'Etkinlik konumu (örn: Stanton > Hurston > Lorville)',
  `event_type` enum('Genel','Operasyon','Eğitim','Sosyal','Ticaret','Keşif','Yarış','PVP') NOT NULL DEFAULT 'Genel' COMMENT 'Etkinlik türü',
  `event_datetime` datetime NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `image_path_1` varchar(255) DEFAULT NULL,
  `image_path_2` varchar(255) DEFAULT NULL,
  `image_path_3` varchar(255) DEFAULT NULL,
  `status` enum('active','past','cancelled') NOT NULL DEFAULT 'active',
  `is_public_no_auth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1: Herkese açık (giriş yapmayanlar dahil)',
  `is_members_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1: Sadece onaylı tüm üyelere açık',
  `visibility` enum('public','members_only','faction_only') NOT NULL DEFAULT 'public' COMMENT 'Etkinlik görünürlük seviyesi',
  `max_participants` int(11) DEFAULT NULL COMMENT 'Maksimum katılımcı sayısı, NULL ise sınırsız',
  `suggested_loadout_id` int(11) DEFAULT NULL COMMENT 'Önerilen teçhizat seti ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `participation_status` enum('attending','maybe','declined') NOT NULL DEFAULT 'attending',
  `signed_up_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_visibility_roles`
--

CREATE TABLE `event_visibility_roles` (
  `event_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Etkinliklerin hangi rollere özel olduğunu belirtir';

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gallery_photos`
--

CREATE TABLE `gallery_photos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Yükleyen kullanıcı IDsi',
  `image_path` varchar(255) NOT NULL COMMENT 'Sunucudaki fotoğrafın yolu (public klasörüne göreli)',
  `description` text DEFAULT NULL COMMENT 'Fotoğraf açıklaması',
  `is_public_no_auth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1: Herkese açık (giriş yapmayanlar dahil)',
  `is_members_only` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Sadece onaylı tüm üyelere açık',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `gallery_photos`
--

INSERT INTO `gallery_photos` (`id`, `user_id`, `image_path`, `description`, `is_public_no_auth`, `is_members_only`, `uploaded_at`) VALUES
(11, 2, 'uploads/gallery/gallery_user2_1748741249_98f44c1b.png', 'kjlkkllk', 1, 0, '2025-06-01 01:27:29');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gallery_photo_likes`
--

CREATE TABLE `gallery_photo_likes` (
  `id` int(11) NOT NULL,
  `photo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gallery_photo_visibility_roles`
--

CREATE TABLE `gallery_photo_visibility_roles` (
  `photo_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Galeri fotoğraflarının hangi rollere özel olduğunu belirtir';

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `guides`
--

CREATE TABLE `guides` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `content_md` text NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `is_public_no_auth` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'True ise herkese (login olmayanlar dahil) görünür',
  `is_members_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'True ise sadece onaylı tüm üyelere görünür',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `view_count` int(11) NOT NULL DEFAULT 0,
  `like_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `guide_likes`
--

CREATE TABLE `guide_likes` (
  `id` int(11) NOT NULL,
  `guide_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `guide_visibility_roles`
--

CREATE TABLE `guide_visibility_roles` (
  `guide_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `loadout_sets`
--

CREATE TABLE `loadout_sets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Seti oluşturan veya setin atandığı kullanıcı IDsi',
  `set_name` varchar(255) NOT NULL,
  `set_description` text DEFAULT NULL,
  `set_image_path` varchar(255) DEFAULT NULL COMMENT 'Kullanıcının yüklediği komple set görseli',
  `visibility` enum('public','members_only','faction_only','private') NOT NULL DEFAULT 'private' COMMENT 'Setin görünürlük seviyesi',
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft' COMMENT 'Setin yayın durumu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `loadout_set_items`
--

CREATE TABLE `loadout_set_items` (
  `id` int(11) NOT NULL,
  `loadout_set_id` int(11) NOT NULL,
  `equipment_slot_id` int(11) DEFAULT NULL COMMENT 'Eğer standart bir slot ise',
  `custom_slot_name` varchar(100) DEFAULT NULL COMMENT 'Eğer kullanıcı tanımlı bir slot ise',
  `item_name` varchar(255) NOT NULL COMMENT 'Ekipmanın adı',
  `item_api_uuid` varchar(255) DEFAULT NULL COMMENT 'API''den gelen UUID',
  `item_type_api` varchar(100) DEFAULT NULL COMMENT 'API''den gelen tip/subtype',
  `item_manufacturer_api` varchar(255) DEFAULT NULL COMMENT 'API''den gelen üretici',
  `item_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `loadout_set_visibility_roles`
--

CREATE TABLE `loadout_set_visibility_roles` (
  `set_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teçhizat setlerinin hangi rollere özel olduğunu belirtir';

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Bildirimi alacak kullanıcı IDsi',
  `event_id` int(11) DEFAULT NULL COMMENT 'İlgili etkinlik IDsi (varsa)',
  `actor_user_id` int(11) DEFAULT NULL COMMENT 'Bildirime neden olan kullanıcının IDsi',
  `message` varchar(255) NOT NULL COMMENT 'Bildirim mesajı',
  `link` varchar(255) DEFAULT NULL COMMENT 'Bildirim tıklandığında gidilecek link',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Okundu mu? (0 = okunmadı, 1 = okundu)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `event_id`, `actor_user_id`, `message`, `link`, `is_read`, `created_at`) VALUES
(48, 1, NULL, 2, '\"ILGARION TURANIS Web Sitesi – Görüş ve Önerilerinize Açığız!\" başlıklı yeni bir tartışma başlatıldı.', '/public/discussion_detail.php?id=9&notif_id=48', 1, '2025-06-01 01:59:26'),
(49, 3, NULL, 2, '\"ILGARION TURANIS Web Sitesi – Görüş ve Önerilerinize Açığız!\" başlıklı yeni bir tartışma başlatıldı.', '/public/discussion_detail.php?id=9&notif_id=49', 0, '2025-06-01 01:59:26'),
(50, 4, NULL, 2, '\"ILGARION TURANIS Web Sitesi – Görüş ve Önerilerinize Açığız!\" başlıklı yeni bir tartışma başlatıldı.', '/public/discussion_detail.php?id=9&notif_id=50', 0, '2025-06-01 01:59:26'),
(51, 2, NULL, 1, '\"ILGARION TURANIS Web Sitesi – Görüş ve Önerilerinize Açığız!\" başlıklı konunuza bir yorum yapıldı.', '/public/discussion_detail.php?id=9#post-25&notif_id=51', 0, '2025-06-01 02:00:28'),
(52, 1, NULL, 2, '\"ILGARION TURANIS Web Sitesi – Görüş ve Önerilerinize Açığız!\" konusundaki yorumunuza yanıt verildi.', '/public/discussion_detail.php?id=9#post-26&notif_id=52', 0, '2025-06-01 02:01:56');

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
(30, 'discussion.view_public', 'Herkese Açık Tartışmaları Görüntüleme', 'discussion', 1, '2025-05-31 22:01:11'),
(31, 'discussion.members_only', 'Onaylı Üyelere Açık Tartışmaları Görüntüleme', 'discussion', 1, '2025-05-31 22:01:11'),
(32, 'discussion.view_approved', 'Onaylı Üyelere Açık Tartışmaları Görüntüleme', 'discussion', 1, '2025-05-31 22:01:11'),
(33, 'discussion.topic.create', 'Yeni Tartışma Konusu Başlatma', 'discussion', 1, '2025-05-31 22:01:11'),
(34, 'discussion.post.create', 'Tartışmalara Yorum Yazma', 'discussion', 1, '2025-05-31 22:01:11'),
(35, 'discussion.topic.edit_own', 'Kendi Konusunu Düzenleme', 'discussion', 1, '2025-05-31 22:01:11'),
(36, 'discussion.topic.edit_all', 'Tüm Konuları Düzenleme', 'discussion', 1, '2025-05-31 22:01:11'),
(37, 'discussion.post.edit_own', 'Kendi Yorumunu Düzenleme', 'discussion', 1, '2025-05-31 22:01:11'),
(38, 'discussion.post.edit_all', 'Tüm Yorumları Düzenleme', 'discussion', 1, '2025-05-31 22:01:11'),
(39, 'discussion.topic.delete_own', 'Kendi Konusunu Silme', 'discussion', 1, '2025-05-31 22:01:11'),
(40, 'discussion.topic.delete_all', 'Tüm Konuları Silme', 'discussion', 1, '2025-05-31 22:01:11'),
(41, 'discussion.post.delete_own', 'Kendi Yorumunu Silme', 'discussion', 1, '2025-05-31 22:01:11'),
(42, 'discussion.post.delete_all', 'Tüm Yorumları Silme', 'discussion', 1, '2025-05-31 22:01:11'),
(43, 'discussion.topic.lock', 'Konu Kilitleme/Açma', 'discussion', 1, '2025-05-31 22:01:11'),
(44, 'discussion.topic.pin', 'Konu Sabitleme/Kaldırma', 'discussion', 1, '2025-05-31 22:01:11'),
(45, 'guide.view_public', 'Herkese Açık Rehberleri Görüntüleme', 'guide', 1, '2025-05-31 22:01:11'),
(46, 'guide.view_members_only', 'Sadece Üyelere Özel Rehberleri Görüntüleme', 'guide', 1, '2025-05-31 22:01:11'),
(47, 'guide.view_faction_only', 'Sadece Fraksiyona Özel Rehberleri Görüntüleme', 'guide', 1, '2025-05-31 22:01:11'),
(48, 'guide.view_all', 'Tüm Rehberleri Görüntüleme', 'guide', 1, '2025-05-31 22:01:11'),
(49, 'guide.create', 'Yeni Rehber Oluşturma', 'guide', 1, '2025-05-31 22:01:11'),
(50, 'guide.edit_own', 'Kendi Rehberini Düzenleme', 'guide', 1, '2025-05-31 22:01:11'),
(51, 'guide.edit_all', 'Tüm Rehberleri Düzenleme', 'guide', 1, '2025-05-31 22:01:11'),
(52, 'guide.delete_own', 'Kendi Rehberini Silme', 'guide', 1, '2025-05-31 22:01:11'),
(53, 'guide.delete_all', 'Tüm Rehberleri Silme', 'guide', 1, '2025-05-31 22:01:11'),
(54, 'guide.like', 'Rehberleri Beğenme', 'guide', 1, '2025-05-31 22:01:11'),
(55, 'loadout.view_public', 'Herkese Açık Teçhizat Setlerini Görüntüleme', 'loadout', 1, '2025-05-31 22:01:11'),
(56, 'loadout.view_members_only', 'Sadece Üyelere Özel Teçhizat Setlerini Görüntüleme', 'loadout', 1, '2025-05-31 22:01:11'),
(57, 'loadout.view_published', 'Yayınlanmış Teçhizat Setlerini Görüntüleme', 'loadout', 1, '2025-05-31 22:01:11'),
(58, 'loadout.manage_sets', 'Teçhizat Setlerini Yönetme', 'loadout', 1, '2025-05-31 22:01:11'),
(59, 'loadout.manage_items', 'Teçhizat Seti Itemlerini Yönetme', 'loadout', 1, '2025-05-31 22:01:11'),
(60, 'loadout.manage_slots', 'Ekipman Slotlarını Yönetme', 'loadout', 1, '2025-05-31 22:01:11'),
(61, 'admin.super_admin.view', 'Süper Admin Listesini Görüntüleme', 'admin', 1, '2025-05-31 23:38:43'),
(62, 'admin.super_admin.manage', 'Süper Admin Ekleme/Çıkarma', 'admin', 1, '2025-05-31 23:38:43'),
(63, 'admin.audit_log.view', 'Audit Log Görüntüleme', 'admin', 1, '2025-05-31 23:38:43'),
(64, 'admin.audit_log.export', 'Audit Log Dışa Aktarma', 'admin', 1, '2025-05-31 23:38:43'),
(65, 'admin.system.security', 'Sistem Güvenlik Yönetimi', 'admin', 1, '2025-05-31 23:38:43');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#000000' COMMENT 'Rolü temsil eden renk kodu (örn: #FF0000)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `priority` int(11) NOT NULL DEFAULT 999 COMMENT 'Rol öncelik sırası (düşük sayı = yüksek öncelik)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `color`, `created_at`, `updated_at`, `priority`) VALUES
(2, 'admin', 'Site Yöneticisi - Tüm yetkilere sahip', '#0091ff', '2025-05-30 11:51:48', '2025-05-31 22:01:11', 1),
(3, 'member', 'Standart Üye - Temel site özelliklerine erişim', '#0000ff', '2025-05-30 11:51:48', '2025-05-31 22:01:11', 4),
(4, 'scg_uye', 'Star Citizen Grubu Üyesi - Özel SCG yetkileri', '#a52a2a', '2025-05-30 11:51:48', '2025-05-31 22:01:11', 3),
(5, 'dis_uye', 'Dış Üye - Sınırlı erişim', '#808080', '2025-05-30 11:51:48', '2025-05-31 22:01:11', 5),
(6, 'ilgarion_turanis', 'Ilgarion Turanis Liderlik Rolü - Geniş yetkiler', '#3da6a2', '2025-05-30 11:51:48', '2025-05-31 22:01:11', 2),
(7, 'gozlerine_hasretim', 'Denemeeeee', '#ff0000', '2025-05-30 12:29:18', '2025-06-01 08:46:40', 3);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `granted_at`) VALUES
(2, 1, '2025-06-01 06:37:17'),
(2, 2, '2025-06-01 06:37:17'),
(2, 3, '2025-06-01 06:37:17'),
(2, 4, '2025-06-01 06:37:17'),
(2, 5, '2025-06-01 06:37:17'),
(2, 6, '2025-06-01 06:37:17'),
(2, 7, '2025-06-01 06:37:17'),
(2, 8, '2025-06-01 06:37:17'),
(2, 9, '2025-06-01 06:37:17'),
(2, 10, '2025-06-01 06:37:17'),
(2, 11, '2025-06-01 06:37:17'),
(2, 12, '2025-06-01 06:37:17'),
(2, 13, '2025-06-01 06:37:17'),
(2, 14, '2025-06-01 06:37:17'),
(2, 15, '2025-06-01 06:37:17'),
(2, 16, '2025-06-01 06:37:17'),
(2, 17, '2025-06-01 06:37:17'),
(2, 18, '2025-06-01 06:37:17'),
(2, 19, '2025-06-01 06:37:17'),
(2, 20, '2025-06-01 06:37:17'),
(2, 21, '2025-06-01 06:37:17'),
(2, 22, '2025-06-01 06:37:17'),
(2, 23, '2025-06-01 06:37:17'),
(2, 24, '2025-06-01 06:37:17'),
(2, 25, '2025-06-01 06:37:17'),
(2, 26, '2025-06-01 06:37:17'),
(2, 27, '2025-06-01 06:37:17'),
(2, 28, '2025-06-01 06:37:17'),
(2, 29, '2025-06-01 06:37:17'),
(2, 30, '2025-06-01 06:37:17'),
(2, 31, '2025-06-01 06:37:17'),
(2, 32, '2025-06-01 06:37:17'),
(2, 33, '2025-06-01 06:37:17'),
(2, 34, '2025-06-01 06:37:17'),
(2, 35, '2025-06-01 06:37:17'),
(2, 36, '2025-06-01 06:37:17'),
(2, 37, '2025-06-01 06:37:17'),
(2, 38, '2025-06-01 06:37:17'),
(2, 39, '2025-06-01 06:37:17'),
(2, 40, '2025-06-01 06:37:17'),
(2, 41, '2025-06-01 06:37:17'),
(2, 42, '2025-06-01 06:37:17'),
(2, 43, '2025-06-01 06:37:17'),
(2, 44, '2025-06-01 06:37:17'),
(2, 45, '2025-06-01 06:37:17'),
(2, 46, '2025-06-01 06:37:17'),
(2, 47, '2025-06-01 06:37:17'),
(2, 48, '2025-06-01 06:37:17'),
(2, 49, '2025-06-01 06:37:17'),
(2, 50, '2025-06-01 06:37:17'),
(2, 51, '2025-06-01 06:37:17'),
(2, 52, '2025-06-01 06:37:17'),
(2, 53, '2025-06-01 06:37:17'),
(2, 54, '2025-06-01 06:37:17'),
(2, 55, '2025-06-01 06:37:17'),
(2, 56, '2025-06-01 06:37:17'),
(2, 57, '2025-06-01 06:37:17'),
(2, 58, '2025-06-01 06:37:17'),
(2, 59, '2025-06-01 06:37:17'),
(2, 60, '2025-06-01 06:37:17'),
(2, 61, '2025-06-01 06:37:17'),
(2, 62, '2025-06-01 06:37:17'),
(2, 63, '2025-06-01 06:37:17'),
(2, 64, '2025-06-01 06:37:17'),
(2, 65, '2025-06-01 06:37:17'),
(3, 1, '2025-06-01 08:53:45'),
(3, 2, '2025-06-01 08:53:45'),
(3, 3, '2025-06-01 08:53:45'),
(3, 4, '2025-06-01 08:53:45'),
(3, 5, '2025-06-01 08:53:45'),
(3, 6, '2025-06-01 08:53:45'),
(3, 7, '2025-06-01 08:53:45'),
(3, 8, '2025-06-01 08:53:45'),
(3, 9, '2025-06-01 08:53:45'),
(3, 10, '2025-06-01 08:53:45'),
(3, 11, '2025-06-01 08:53:45'),
(3, 12, '2025-06-01 08:53:45'),
(3, 13, '2025-06-01 08:53:45'),
(3, 14, '2025-06-01 08:53:45'),
(3, 15, '2025-06-01 08:53:45'),
(3, 16, '2025-06-01 08:53:45'),
(3, 17, '2025-06-01 08:53:45'),
(3, 18, '2025-06-01 08:53:45'),
(3, 19, '2025-06-01 08:53:45'),
(3, 20, '2025-06-01 08:53:45'),
(3, 21, '2025-06-01 08:53:45'),
(3, 22, '2025-06-01 08:53:45'),
(3, 23, '2025-06-01 08:53:45'),
(3, 24, '2025-06-01 08:53:45'),
(3, 25, '2025-06-01 08:53:45'),
(3, 26, '2025-06-01 08:53:45'),
(3, 27, '2025-06-01 08:53:45'),
(3, 28, '2025-06-01 08:53:45'),
(3, 29, '2025-06-01 08:53:45'),
(3, 30, '2025-06-01 08:53:45'),
(3, 31, '2025-06-01 08:53:45'),
(3, 32, '2025-06-01 08:53:45'),
(3, 33, '2025-06-01 08:53:45'),
(3, 34, '2025-06-01 08:53:45'),
(3, 35, '2025-06-01 08:53:45'),
(3, 36, '2025-06-01 08:53:45'),
(3, 37, '2025-06-01 08:53:45'),
(3, 38, '2025-06-01 08:53:45'),
(3, 39, '2025-06-01 08:53:45'),
(3, 40, '2025-06-01 08:53:45'),
(3, 41, '2025-06-01 08:53:45'),
(3, 42, '2025-06-01 08:53:45'),
(3, 43, '2025-06-01 08:53:45'),
(3, 44, '2025-06-01 08:53:45'),
(3, 45, '2025-06-01 08:53:45'),
(3, 46, '2025-06-01 08:53:45'),
(3, 47, '2025-06-01 08:53:45'),
(3, 48, '2025-06-01 08:53:45'),
(3, 49, '2025-06-01 08:53:45'),
(3, 50, '2025-06-01 08:53:45'),
(3, 51, '2025-06-01 08:53:45'),
(3, 52, '2025-06-01 08:53:45'),
(3, 53, '2025-06-01 08:53:45'),
(3, 54, '2025-06-01 08:53:45'),
(3, 55, '2025-06-01 08:53:45'),
(3, 56, '2025-06-01 08:53:45'),
(3, 57, '2025-06-01 08:53:45'),
(3, 58, '2025-06-01 08:53:45'),
(3, 59, '2025-06-01 08:53:45'),
(3, 60, '2025-06-01 08:53:45'),
(3, 61, '2025-06-01 08:53:45'),
(3, 62, '2025-06-01 08:53:45'),
(3, 63, '2025-06-01 08:53:45'),
(3, 64, '2025-06-01 08:53:45'),
(3, 65, '2025-06-01 08:53:45'),
(4, 12, '2025-05-31 22:06:18'),
(4, 13, '2025-05-31 22:06:18'),
(4, 14, '2025-05-31 22:06:18'),
(4, 21, '2025-05-31 22:06:18'),
(4, 23, '2025-05-31 22:06:18'),
(4, 24, '2025-05-31 22:06:18'),
(4, 25, '2025-05-31 22:06:18'),
(4, 26, '2025-05-31 22:06:18'),
(4, 30, '2025-05-31 22:06:18'),
(4, 32, '2025-05-31 22:06:18'),
(4, 33, '2025-05-31 22:06:18'),
(4, 34, '2025-05-31 22:06:18'),
(4, 45, '2025-05-31 22:06:18'),
(4, 46, '2025-05-31 22:06:18'),
(4, 47, '2025-05-31 22:06:18'),
(4, 54, '2025-05-31 22:06:18'),
(4, 55, '2025-05-31 22:06:18'),
(4, 57, '2025-05-31 22:06:18'),
(5, 12, '2025-05-31 22:06:18'),
(5, 23, '2025-05-31 22:06:18'),
(5, 24, '2025-05-31 22:06:18'),
(5, 30, '2025-05-31 22:06:18'),
(5, 45, '2025-05-31 22:06:18'),
(5, 55, '2025-05-31 22:06:18'),
(6, 1, '2025-06-01 06:17:04'),
(6, 2, '2025-06-01 06:17:04'),
(6, 3, '2025-06-01 06:17:04'),
(6, 4, '2025-06-01 06:17:04'),
(6, 5, '2025-06-01 06:17:04'),
(6, 7, '2025-06-01 06:17:04'),
(6, 8, '2025-06-01 06:17:04'),
(6, 10, '2025-06-01 06:17:04'),
(6, 12, '2025-06-01 06:17:04'),
(6, 13, '2025-06-01 06:17:04'),
(6, 14, '2025-06-01 06:17:04'),
(6, 15, '2025-06-01 06:17:04'),
(6, 17, '2025-06-01 06:17:04'),
(6, 18, '2025-06-01 06:17:04'),
(6, 21, '2025-06-01 06:17:04'),
(6, 22, '2025-06-01 06:17:04'),
(6, 23, '2025-06-01 06:17:04'),
(6, 24, '2025-06-01 06:17:04'),
(6, 25, '2025-06-01 06:17:04'),
(6, 26, '2025-06-01 06:17:04'),
(6, 27, '2025-06-01 06:17:04'),
(6, 28, '2025-06-01 06:17:04'),
(6, 29, '2025-06-01 06:17:04'),
(6, 30, '2025-06-01 06:17:04'),
(6, 32, '2025-06-01 06:17:04'),
(6, 33, '2025-06-01 06:17:04'),
(6, 35, '2025-06-01 06:17:04'),
(6, 36, '2025-06-01 06:17:04'),
(6, 37, '2025-06-01 06:17:04'),
(6, 39, '2025-06-01 06:17:04'),
(6, 40, '2025-06-01 06:17:04'),
(6, 43, '2025-06-01 06:17:04'),
(6, 44, '2025-06-01 06:17:04'),
(6, 45, '2025-06-01 06:17:04'),
(6, 46, '2025-06-01 06:17:04'),
(6, 47, '2025-06-01 06:17:04'),
(6, 48, '2025-06-01 06:17:04'),
(6, 49, '2025-06-01 06:17:04'),
(6, 50, '2025-06-01 06:17:04'),
(6, 51, '2025-06-01 06:17:04'),
(6, 52, '2025-06-01 06:17:04'),
(6, 53, '2025-06-01 06:17:04'),
(6, 54, '2025-06-01 06:17:04'),
(6, 55, '2025-06-01 06:17:04'),
(6, 56, '2025-06-01 06:17:04'),
(6, 57, '2025-06-01 06:17:04'),
(6, 58, '2025-06-01 06:17:04'),
(6, 59, '2025-06-01 06:17:04'),
(6, 60, '2025-06-01 06:17:04'),
(6, 61, '2025-06-01 06:17:04'),
(6, 62, '2025-06-01 06:17:04'),
(6, 65, '2025-06-01 06:17:04'),
(7, 1, '2025-06-01 08:47:04'),
(7, 2, '2025-06-01 08:47:04'),
(7, 3, '2025-06-01 08:47:04'),
(7, 4, '2025-06-01 08:47:04'),
(7, 5, '2025-06-01 08:47:04'),
(7, 6, '2025-06-01 08:47:04'),
(7, 7, '2025-06-01 08:47:04'),
(7, 8, '2025-06-01 08:47:04'),
(7, 9, '2025-06-01 08:47:04'),
(7, 10, '2025-06-01 08:47:04'),
(7, 11, '2025-06-01 08:47:04'),
(7, 12, '2025-06-01 08:47:04'),
(7, 13, '2025-06-01 08:47:04'),
(7, 14, '2025-06-01 08:47:04'),
(7, 15, '2025-06-01 08:47:04'),
(7, 16, '2025-06-01 08:47:04'),
(7, 17, '2025-06-01 08:47:04'),
(7, 18, '2025-06-01 08:47:04'),
(7, 19, '2025-06-01 08:47:04'),
(7, 20, '2025-06-01 08:47:04'),
(7, 21, '2025-06-01 08:47:04'),
(7, 22, '2025-06-01 08:47:04'),
(7, 23, '2025-06-01 08:47:04'),
(7, 24, '2025-06-01 08:47:04'),
(7, 25, '2025-06-01 08:47:04'),
(7, 26, '2025-06-01 08:47:04'),
(7, 27, '2025-06-01 08:47:04'),
(7, 28, '2025-06-01 08:47:04'),
(7, 29, '2025-06-01 08:47:04'),
(7, 30, '2025-06-01 08:47:04'),
(7, 31, '2025-06-01 08:47:04'),
(7, 32, '2025-06-01 08:47:04'),
(7, 33, '2025-06-01 08:47:04'),
(7, 34, '2025-06-01 08:47:04'),
(7, 35, '2025-06-01 08:47:04'),
(7, 36, '2025-06-01 08:47:04'),
(7, 37, '2025-06-01 08:47:04'),
(7, 38, '2025-06-01 08:47:04'),
(7, 39, '2025-06-01 08:47:04'),
(7, 40, '2025-06-01 08:47:04'),
(7, 41, '2025-06-01 08:47:04'),
(7, 42, '2025-06-01 08:47:04'),
(7, 43, '2025-06-01 08:47:04'),
(7, 44, '2025-06-01 08:47:04'),
(7, 45, '2025-06-01 08:47:04'),
(7, 46, '2025-06-01 08:47:04'),
(7, 47, '2025-06-01 08:47:04'),
(7, 48, '2025-06-01 08:47:04'),
(7, 49, '2025-06-01 08:47:04'),
(7, 50, '2025-06-01 08:47:04'),
(7, 51, '2025-06-01 08:47:04'),
(7, 52, '2025-06-01 08:47:04'),
(7, 53, '2025-06-01 08:47:04'),
(7, 54, '2025-06-01 08:47:04'),
(7, 55, '2025-06-01 08:47:04'),
(7, 56, '2025-06-01 08:47:04'),
(7, 57, '2025-06-01 08:47:04'),
(7, 58, '2025-06-01 08:47:04'),
(7, 59, '2025-06-01 08:47:04'),
(7, 60, '2025-06-01 08:47:04'),
(7, 61, '2025-06-01 08:47:04'),
(7, 62, '2025-06-01 08:47:04'),
(7, 63, '2025-06-01 08:47:04'),
(7, 64, '2025-06-01 08:47:04'),
(7, 65, '2025-06-01 08:47:04');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'super_admin_users', '[1]', 'json', 'Süper admin kullanıcı ID listesi', '2025-06-01 01:14:12');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `profile_info` text DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `ingame_name` varchar(255) NOT NULL,
  `discord_username` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `profile_info`, `avatar_path`, `status`, `ingame_name`, `discord_username`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$12$ZUDegf.5Yo/MvZE3wWe0z.OEFi7Njl9/w74TUsBmpXmCaM047Xkle', 'doganayurgupluoglu@gmail.com', NULL, 'uploads/avatars/avatar_user1_1748602974.png', 'approved', 'Cpt_Bosmang', '_doganay', '2025-05-30 11:01:51', '2025-05-30 22:56:01'),
(2, 'test', '$2y$10$.ovtIyA3gdvc/cCypSEUhuP2QhBjspIbyfRQ9VC8x8ToMhcXWySKy', 'fatih54king@gmail.com', NULL, NULL, 'approved', 'asd', NULL, '2025-05-30 12:31:22', '2025-05-30 12:42:27'),
(3, 'test1', '$2y$10$SJx5eH2mTOK/gy2kTV/VMuwxFfbgs2P4cxWG4fkNofWhNFQnnPJbu', 'test1@test1.com', NULL, NULL, 'approved', 'test1', NULL, '2025-05-30 12:40:34', '2025-05-30 12:40:56'),
(4, 'test2', '$2y$10$wY/UL/R/bX6.Ec9ZPpkaQeLbVACx6kjpZ.jQRblEbXNRHnpCp4abC', 'test@test.com', NULL, NULL, 'approved', 'test2', NULL, '2025-05-30 12:42:48', '2025-05-30 12:42:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_hangar`
--

CREATE TABLE `user_hangar` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ship_api_id` varchar(255) NOT NULL COMMENT 'API''deki gemi IDsi veya benzersiz adı',
  `ship_name` varchar(255) NOT NULL,
  `ship_manufacturer` varchar(255) DEFAULT NULL,
  `ship_focus` varchar(255) DEFAULT NULL,
  `ship_size` varchar(255) DEFAULT NULL,
  `ship_image_url` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `user_notes` text DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(2, 3),
(4, 3);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_topic_views`
--

CREATE TABLE `user_topic_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `last_viewed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `user_topic_views`
--

INSERT INTO `user_topic_views` (`id`, `user_id`, `topic_id`, `last_viewed_at`) VALUES
(86, 2, 9, '2025-06-01 02:02:28'),
(91, 1, 9, '2025-06-01 05:43:02');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Tablo için indeksler `discussion_posts`
--
ALTER TABLE `discussion_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_discussion_posts_topics_idx` (`topic_id`),
  ADD KEY `fk_discussion_posts_users_idx` (`user_id`),
  ADD KEY `fk_discussion_posts_parent_idx` (`parent_post_id`);

--
-- Tablo için indeksler `discussion_topics`
--
ALTER TABLE `discussion_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_discussion_topics_users_idx` (`user_id`);

--
-- Tablo için indeksler `discussion_topic_visibility_roles`
--
ALTER TABLE `discussion_topic_visibility_roles`
  ADD PRIMARY KEY (`topic_id`,`role_id`),
  ADD KEY `fk_dtvr_topic_idx` (`topic_id`),
  ADD KEY `fk_dtvr_role_idx` (`role_id`);

--
-- Tablo için indeksler `equipment_slots`
--
ALTER TABLE `equipment_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slot_name_UNIQUE` (`slot_name`);

--
-- Tablo için indeksler `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_events_users_idx` (`created_by_user_id`),
  ADD KEY `fk_event_suggested_loadout` (`suggested_loadout_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_event_datetime` (`event_datetime`);

--
-- Tablo için indeksler `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_user_participation` (`event_id`,`user_id`) COMMENT 'Bir kullanıcı bir etkinliğe birden fazla kez katılamaz/durum bildiremez',
  ADD KEY `fk_event_participants_events_idx` (`event_id`),
  ADD KEY `fk_event_participants_users_idx` (`user_id`);

--
-- Tablo için indeksler `event_visibility_roles`
--
ALTER TABLE `event_visibility_roles`
  ADD PRIMARY KEY (`event_id`,`role_id`),
  ADD KEY `fk_evr_role` (`role_id`);

--
-- Tablo için indeksler `gallery_photos`
--
ALTER TABLE `gallery_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gallery_photos_users_idx` (`user_id`);

--
-- Tablo için indeksler `gallery_photo_likes`
--
ALTER TABLE `gallery_photo_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_photo_user_like` (`photo_id`,`user_id`),
  ADD KEY `fk_gallery_photo_likes_photos_idx` (`photo_id`),
  ADD KEY `fk_gallery_photo_likes_users_idx` (`user_id`);

--
-- Tablo için indeksler `gallery_photo_visibility_roles`
--
ALTER TABLE `gallery_photo_visibility_roles`
  ADD PRIMARY KEY (`photo_id`,`role_id`),
  ADD KEY `fk_gpvr_role` (`role_id`);

--
-- Tablo için indeksler `guides`
--
ALTER TABLE `guides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug_UNIQUE` (`slug`),
  ADD KEY `fk_guides_users_idx` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_visibility_flags` (`is_public_no_auth`,`is_members_only`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- Tablo için indeksler `guide_likes`
--
ALTER TABLE `guide_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_guide_user_like` (`guide_id`,`user_id`) COMMENT 'Bir kullanıcı bir rehberi sadece bir kez beğenebilir',
  ADD KEY `fk_guide_likes_guides_idx` (`guide_id`),
  ADD KEY `fk_guide_likes_users_idx` (`user_id`);

--
-- Tablo için indeksler `guide_visibility_roles`
--
ALTER TABLE `guide_visibility_roles`
  ADD PRIMARY KEY (`guide_id`,`role_id`),
  ADD KEY `fk_guide_visibility_guides_idx` (`guide_id`),
  ADD KEY `fk_guide_visibility_roles_idx` (`role_id`);

--
-- Tablo için indeksler `loadout_sets`
--
ALTER TABLE `loadout_sets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_loadout_sets_users_idx` (`user_id`);

--
-- Tablo için indeksler `loadout_set_items`
--
ALTER TABLE `loadout_set_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_loadout_set_items_sets_idx` (`loadout_set_id`),
  ADD KEY `fk_loadout_set_items_slots_idx` (`equipment_slot_id`);

--
-- Tablo için indeksler `loadout_set_visibility_roles`
--
ALTER TABLE `loadout_set_visibility_roles`
  ADD PRIMARY KEY (`set_id`,`role_id`),
  ADD KEY `fk_lsvr_role` (`role_id`);

--
-- Tablo için indeksler `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_users_idx` (`user_id`),
  ADD KEY `fk_notifications_events_idx` (`event_id`),
  ADD KEY `fk_notifications_actor_users_idx` (`actor_user_id`),
  ADD KEY `idx_user_read_status` (`user_id`,`is_read`);

--
-- Tablo için indeksler `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key_UNIQUE` (`permission_key`),
  ADD KEY `idx_permission_group` (`permission_group`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Tablo için indeksler `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_UNIQUE` (`name`);

--
-- Tablo için indeksler `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_role` (`role_id`),
  ADD KEY `fk_rp_permission` (`permission_id`);

--
-- Tablo için indeksler `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key_UNIQUE` (`setting_key`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username_UNIQUE` (`username`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`);

--
-- Tablo için indeksler `user_hangar`
--
ALTER TABLE `user_hangar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_ship_api_id` (`user_id`,`ship_api_id`) COMMENT 'Bir kullanıcı aynı gemiyi birden fazla kez (farklı notla vs olmadan) ekleyemesin diye, quantity ile yönetilebilir',
  ADD KEY `fk_user_hangar_users_idx` (`user_id`);

--
-- Tablo için indeksler `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_users_idx` (`user_id`),
  ADD KEY `fk_user_roles_roles_idx` (`role_id`);

--
-- Tablo için indeksler `user_topic_views`
--
ALTER TABLE `user_topic_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_topic_view` (`user_id`,`topic_id`),
  ADD KEY `fk_user_topic_views_users_idx` (`user_id`),
  ADD KEY `fk_user_topic_views_topics_idx` (`topic_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;

--
-- Tablo için AUTO_INCREMENT değeri `discussion_posts`
--
ALTER TABLE `discussion_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Tablo için AUTO_INCREMENT değeri `discussion_topics`
--
ALTER TABLE `discussion_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `equipment_slots`
--
ALTER TABLE `equipment_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Tablo için AUTO_INCREMENT değeri `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photos`
--
ALTER TABLE `gallery_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photo_likes`
--
ALTER TABLE `gallery_photo_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `guides`
--
ALTER TABLE `guides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `guide_likes`
--
ALTER TABLE `guide_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `loadout_sets`
--
ALTER TABLE `loadout_sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `loadout_set_items`
--
ALTER TABLE `loadout_set_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- Tablo için AUTO_INCREMENT değeri `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- Tablo için AUTO_INCREMENT değeri `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- Tablo için AUTO_INCREMENT değeri `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `user_hangar`
--
ALTER TABLE `user_hangar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `user_topic_views`
--
ALTER TABLE `user_topic_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `discussion_posts`
--
ALTER TABLE `discussion_posts`
  ADD CONSTRAINT `fk_discussion_posts_parent` FOREIGN KEY (`parent_post_id`) REFERENCES `discussion_posts` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_discussion_posts_topics` FOREIGN KEY (`topic_id`) REFERENCES `discussion_topics` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_discussion_posts_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `discussion_topics`
--
ALTER TABLE `discussion_topics`
  ADD CONSTRAINT `fk_discussion_topics_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `discussion_topic_visibility_roles`
--
ALTER TABLE `discussion_topic_visibility_roles`
  ADD CONSTRAINT `fk_dtvr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dtvr_topic` FOREIGN KEY (`topic_id`) REFERENCES `discussion_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_event_suggested_loadout` FOREIGN KEY (`suggested_loadout_id`) REFERENCES `loadout_sets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_users` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `fk_event_participants_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_event_participants_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `event_visibility_roles`
--
ALTER TABLE `event_visibility_roles`
  ADD CONSTRAINT `fk_evr_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `gallery_photos`
--
ALTER TABLE `gallery_photos`
  ADD CONSTRAINT `fk_gallery_photos_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `gallery_photo_likes`
--
ALTER TABLE `gallery_photo_likes`
  ADD CONSTRAINT `fk_gallery_photo_likes_photos` FOREIGN KEY (`photo_id`) REFERENCES `gallery_photos` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_gallery_photo_likes_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `gallery_photo_visibility_roles`
--
ALTER TABLE `gallery_photo_visibility_roles`
  ADD CONSTRAINT `fk_gpvr_photo` FOREIGN KEY (`photo_id`) REFERENCES `gallery_photos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gpvr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `guides`
--
ALTER TABLE `guides`
  ADD CONSTRAINT `fk_guides_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `guide_likes`
--
ALTER TABLE `guide_likes`
  ADD CONSTRAINT `fk_guide_likes_guides` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_guide_likes_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `guide_visibility_roles`
--
ALTER TABLE `guide_visibility_roles`
  ADD CONSTRAINT `fk_guide_visibility_guides` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_guide_visibility_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `loadout_sets`
--
ALTER TABLE `loadout_sets`
  ADD CONSTRAINT `fk_loadout_sets_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `loadout_set_items`
--
ALTER TABLE `loadout_set_items`
  ADD CONSTRAINT `fk_loadout_set_items_sets` FOREIGN KEY (`loadout_set_id`) REFERENCES `loadout_sets` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_loadout_set_items_slots` FOREIGN KEY (`equipment_slot_id`) REFERENCES `equipment_slots` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `loadout_set_visibility_roles`
--
ALTER TABLE `loadout_set_visibility_roles`
  ADD CONSTRAINT `fk_lsvr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lsvr_set` FOREIGN KEY (`set_id`) REFERENCES `loadout_sets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_actor_users` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_notifications_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `user_hangar`
--
ALTER TABLE `user_hangar`
  ADD CONSTRAINT `fk_user_hangar_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_user_roles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `user_topic_views`
--
ALTER TABLE `user_topic_views`
  ADD CONSTRAINT `fk_user_topic_views_topics_fk` FOREIGN KEY (`topic_id`) REFERENCES `discussion_topics` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_user_topic_views_users_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
