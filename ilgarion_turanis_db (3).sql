-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 31 May 2025, 00:22:08
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
(8, 4, 2, NULL, '21212', '2025-05-30 13:59:36'),
(10, 5, 1, NULL, '123123123', '2025-05-30 14:28:55'),
(12, 5, 1, NULL, '121', '2025-05-30 14:36:47'),
(13, 7, 1, NULL, '123123123', '2025-05-30 14:43:18'),
(14, 8, 1, NULL, '3123123', '2025-05-30 15:01:53'),
(15, 8, 1, NULL, '1', '2025-05-30 15:23:29'),
(19, 4, 1, NULL, '11', '2025-05-30 15:27:32'),
(20, 4, 1, NULL, '22', '2025-05-30 15:27:36'),
(21, 4, 1, 20, '>[ALINTI=\"admin\"]\r\n22\r\n[/ALINTI]\r\n\r\n11', '2025-05-30 15:27:40'),
(22, 4, 1, NULL, '1', '2025-05-30 15:33:25'),
(23, 4, 1, 22, '>[ALINTI=\"admin\"]\r\n1\r\n[/ALINTI]\r\n\r\n123123', '2025-05-30 22:17:05');

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
(4, 2, '12121', '2025-05-30 13:59:36', '2025-05-30 22:17:05', 5, 0, 0, 0, 1),
(5, 1, '123123123', '2025-05-30 14:28:55', '2025-05-30 14:36:47', 1, 0, 0, 0, 1),
(7, 1, '123123123', '2025-05-30 14:43:18', '2025-05-30 14:43:18', 0, 0, 0, 0, 1),
(8, 1, '2312312312', '2025-05-30 15:01:53', '2025-05-30 15:23:29', 1, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussion_topic_visibility_roles`
--

CREATE TABLE `discussion_topic_visibility_roles` (
  `topic_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tartışma konularının hangi rollere özel olduğunu belirtir';

--
-- Tablo döküm verisi `discussion_topic_visibility_roles`
--

INSERT INTO `discussion_topic_visibility_roles` (`topic_id`, `role_id`) VALUES
(8, 6);

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

--
-- Tablo döküm verisi `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `location`, `event_type`, `event_datetime`, `created_by_user_id`, `image_path_1`, `image_path_2`, `image_path_3`, `status`, `is_public_no_auth`, `is_members_only`, `visibility`, `max_participants`, `suggested_loadout_id`, `created_at`, `updated_at`) VALUES
(2, '1520', '12312312312', '3213123123123', 'Genel', '2025-12-15 10:00:00', 1, 'uploads/event_images/event_2_img1_1748609903.png', NULL, NULL, 'active', 0, 0, 'public', NULL, NULL, '2025-05-30 12:58:23', '2025-05-30 12:58:23'),
(4, '1231231', '123123123', '123123123', 'Genel', '2025-10-10 10:10:00', 2, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, 1, '2025-05-30 16:08:36', '2025-05-30 16:08:36'),
(5, '123123', '123123123', '12312', 'Genel', '2026-02-10 10:10:00', 1, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 16:09:09', '2025-05-30 16:09:09'),
(6, '12312', '12312312', '3123123123', 'Genel', '2026-10-10 10:10:00', 1, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 16:11:56', '2025-05-30 16:11:56'),
(7, '12312', '123123123', '', 'Genel', '2025-10-10 10:10:00', 1, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 16:14:26', '2025-05-30 16:14:26'),
(8, '123123', '12312', '', 'Genel', '2025-10-10 10:10:00', 2, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 16:15:32', '2025-05-30 16:15:32'),
(9, '123123', '123123', '', 'Genel', '2025-10-10 10:10:00', 1, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 16:15:47', '2025-05-30 16:15:47'),
(12, 'etkinlik', '121312312', '', 'Genel', '2026-02-20 20:20:00', 2, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 19:55:53', '2025-05-30 19:55:53'),
(13, '11111', 'dasşfklşsadf', '', 'Genel', '2026-02-10 10:10:00', 2, NULL, NULL, NULL, 'active', 0, 1, 'members_only', NULL, NULL, '2025-05-30 20:08:35', '2025-05-30 20:08:35');

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

--
-- Tablo döküm verisi `event_participants`
--

INSERT INTO `event_participants` (`id`, `event_id`, `user_id`, `participation_status`, `signed_up_at`) VALUES
(2, 2, 1, 'attending', '2025-05-30 12:58:23'),
(3, 2, 4, 'attending', '2025-05-30 13:15:07'),
(4, 4, 1, 'attending', '2025-05-30 16:08:45'),
(5, 7, 1, 'attending', '2025-05-30 16:14:26'),
(6, 8, 2, 'attending', '2025-05-30 16:15:32'),
(7, 9, 1, 'attending', '2025-05-30 16:15:47'),
(8, 13, 2, 'attending', '2025-05-30 20:08:35');

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
(5, 1, 'uploads/gallery/gallery_user1_1748609336_40be930d.png', '1212', 0, 1, '2025-05-30 12:48:56'),
(8, 1, 'uploads/gallery/gallery_user1_1748638452_93bf6038.png', '123123123', 0, 1, '2025-05-30 20:54:12'),
(9, 2, 'uploads/gallery/gallery_user2_1748641312_9f563e2a.png', 'Deneme', 1, 0, '2025-05-30 21:41:52');

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

--
-- Tablo döküm verisi `loadout_sets`
--

INSERT INTO `loadout_sets` (`id`, `user_id`, `set_name`, `set_description`, `set_image_path`, `visibility`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Keşif & Klavuz Operatörü (Scout / Pointman)', 'Deneme', 'uploads/loadout_set_images/set_1748612448_70471920.png', 'private', 'draft', '2025-05-30 13:40:48', NULL),
(2, 1, '123123', '123123', 'uploads/loadout_set_images/set_1748614233_9ce0d410.png', 'private', 'draft', '2025-05-30 14:10:33', NULL);

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

--
-- Tablo döküm verisi `loadout_set_items`
--

INSERT INTO `loadout_set_items` (`id`, `loadout_set_id`, `equipment_slot_id`, `custom_slot_name`, `item_name`, `item_api_uuid`, `item_type_api`, `item_manufacturer_api`, `item_notes`) VALUES
(1, 1, NULL, '123', 'Morozov-CH Backpack', 'c6ce6063-0d43-470a-aef3-49e65289a53e', 'Char_Armor_Backpack', 'Roussimoff Rehabilitation Systems', NULL),
(2, 1, NULL, '43', 'Morozov-SH Arms Terracotta', '0ebe3817-3cbe-4a2a-9453-fbf0a81d0cbe', 'Char_Armor_Arms', 'Roussimoff Rehabilitation Systems', NULL),
(3, 1, NULL, '16', 'Morozov-SH Core Thule', '348830d6-ba18-4efd-bb2d-73e126f66057', 'Char_Armor_Torso', 'Roussimoff Rehabilitation Systems', NULL),
(4, 1, NULL, '126', 'Morozov-SH Core Aftershock', 'e336099a-97ec-4f22-8530-1382a89e07db', 'Char_Armor_Torso', 'Roussimoff Rehabilitation Systems', NULL),
(5, 1, NULL, '65', 'Morozov-SH Helmet', '92b7a470-5842-48fe-bd53-dc9df9bb5271', 'Char_Armor_Helmet', 'Roussimoff Rehabilitation Systems', NULL),
(6, 1, NULL, '456', 'Morozov-SH Helmet', '92b7a470-5842-48fe-bd53-dc9df9bb5271', 'Char_Armor_Helmet', 'Roussimoff Rehabilitation Systems', NULL),
(7, 1, NULL, '17', 'Morozov-SH Core', '2dd4b126-eb45-427f-870d-4e1b4de3a22d', 'Char_Armor_Torso', 'Roussimoff Rehabilitation Systems', NULL),
(8, 2, 6, NULL, 'Morozov-CH Backpack', 'c6ce6063-0d43-470a-aef3-49e65289a53e', 'Char_Armor_Backpack', 'Roussimoff Rehabilitation Systems', NULL),
(9, 2, 3, NULL, 'Morozov-SH Arms Terracotta', '0ebe3817-3cbe-4a2a-9453-fbf0a81d0cbe', 'Char_Armor_Arms', 'Roussimoff Rehabilitation Systems', NULL),
(10, 2, 2, NULL, 'Morozov-SH Core', '2dd4b126-eb45-427f-870d-4e1b4de3a22d', 'Char_Armor_Torso', 'Roussimoff Rehabilitation Systems', NULL),
(11, 2, 1, NULL, 'Morozov-SH Helmet Terracotta', 'a798e7ef-f394-4e10-8432-0c4145627238', 'Char_Armor_Helmet', 'Roussimoff Rehabilitation Systems', NULL);

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
(44, 2, NULL, 1, '\"12121\" başlıklı konunuza bir yorum yapıldı.', '/public/discussion_detail.php?id=4#post-23&notif_id=44', 0, '2025-05-30 22:17:05');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#000000' COMMENT 'Rolü temsil eden renk kodu (örn: #FF0000)',
  `permissions` text DEFAULT NULL COMMENT 'Bu role ait yetkilerin virgülle ayrılmış listesi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `color`, `permissions`, `created_at`, `updated_at`) VALUES
(2, 'admin', 'Site Yöneticisi - Tüm yetkilere sahip', '#0091ff', 'event.view_public,event.view_members_only,event.view_faction_only,event.view_all,event.create,event.edit_own,event.edit_all,event.delete_own,event.delete_all,event.participate,event.manage_participants,gallery.view_public,gallery.view_approved,gallery.upload,gallery.like,gallery.delete_own,gallery.delete_any,gallery.manage_all,guide.view_public,guide.view_members_only,guide.view_faction_only,guide.view_all,guide.create,guide.edit_own,guide.edit_all,guide.delete_own,guide.delete_all,guide.like,discussion.view_public,discussion.members_only,discussion.view_approved,discussion.topic.create,discussion.post.create,discussion.topic.edit_own,discussion.topic.edit_all,discussion.post.edit_own,discussion.post.edit_all,discussion.topic.delete_own,discussion.topic.delete_all,discussion.post.delete_own,discussion.post.delete_all,discussion.topic.lock,discussion.topic.pin,loadout.view_public,loadout.view_members_only,loadout.view_published,loadout.manage_sets,loadout.manage_items,loadout.manage_slots,admin.settings.view,admin.settings.edit,admin.panel.access,admin.users.view,admin.users.edit_status,admin.users.assign_roles,admin.users.delete,admin.roles.view,admin.roles.create,admin.roles.edit,admin.roles.delete', '2025-05-30 11:51:48', '2025-05-30 15:54:15'),
(3, 'member', 'Standart Üye - Temel site özelliklerine erişim', '#0000ff', 'event.view_public,event.view_members_only,event.create,event.edit_own,event.participate,gallery.view_public,gallery.view_approved,gallery.upload,gallery.like,gallery.delete_own,guide.view_public,guide.view_members_only,guide.like,discussion.view_public,discussion.members_only,discussion.view_approved,loadout.view_public,loadout.view_published', '2025-05-30 11:51:48', '2025-05-30 22:02:48'),
(4, 'scg_uye', 'Star Citizen Grubu Üyesi - Özel SCG yetkileri', '#a52a2a', 'event.view_public,event.view_members_only,event.view_faction_only,event.participate,gallery.view_public,gallery.view_approved,gallery.upload,gallery.like,guide.view_public,guide.view_members_only,guide.view_faction_only,guide.like,discussion.view_public,discussion.view_approved,discussion.topic.create,discussion.post.create,loadout.view_public,loadout.view_published', '2025-05-30 11:51:48', '2025-05-30 20:15:48'),
(5, 'dis_uye', 'Dış Üye - Sınırlı erişim', '#808080', 'event.view_public,gallery.view_public,gallery.view_approved,guide.view_public,discussion.view_public,discussion.members_only,loadout.view_public', '2025-05-30 11:51:48', '2025-05-30 14:42:56'),
(6, 'ilgarion_turanis', 'Ilgarion Turanis Liderlik Rolü - Geniş yetkiler', '#3da6a2', 'event.view_all,event.create,event.edit_own,event.delete_own,gallery.upload,gallery.like,gallery.delete_own,guide.view_all,guide.create,guide.edit_own,guide.delete_own,guide.like,discussion.view_public,discussion.view_approved,discussion.topic.create,discussion.post.create,discussion.topic.edit_own,discussion.topic.edit_all,discussion.post.edit_own,discussion.post.edit_all,discussion.topic.delete_own,discussion.topic.delete_all,discussion.post.delete_own,discussion.post.delete_all,discussion.topic.lock,discussion.topic.pin,admin.panel.access,admin.users.view,admin.users.edit_status', '2025-05-30 11:51:48', '2025-05-30 14:34:11'),
(7, 'deneme', 'Deneme', '#1860dc', 'event.view_public,event.view_members_only,event.view_faction_only,event.view_all,event.create,event.edit_own,event.edit_all,event.delete_own,event.delete_all,event.participate,event.manage_participants,gallery.view_public,gallery.view_approved,gallery.upload,gallery.like,gallery.delete_own,gallery.delete_any,gallery.manage_all,guide.view_public,guide.view_members_only,guide.view_faction_only,guide.view_all,guide.create,guide.edit_own,guide.edit_all,guide.delete_own,guide.delete_all,guide.like,discussion.view_public,discussion.view_approved,discussion.topic.create,discussion.post.create,discussion.topic.edit_own,discussion.topic.edit_all,discussion.post.edit_own,discussion.post.edit_all,discussion.topic.delete_own,discussion.topic.delete_all,discussion.post.delete_own,discussion.post.delete_all,discussion.topic.lock,discussion.topic.pin,loadout.view_public,loadout.view_published,loadout.manage_sets,loadout.manage_items,loadout.manage_slots,admin.settings.view,admin.settings.edit,admin.panel.access,admin.users.view,admin.users.edit_status,admin.users.assign_roles,admin.users.delete,admin.roles.view,admin.roles.create,admin.roles.edit,admin.roles.delete', '2025-05-30 12:29:18', '2025-05-30 12:29:18');

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
(1, 'admin', '$2y$12$ZUDegf.5Yo/MvZE3wWe0z.OEFi7Njl9/w74TUsBmpXmCaM047Xkle', 'doganayurgupluoglu@gmail.com', NULL, 'uploads/avatars/avatar_user1_1748602974.png', 'approved', 'Cpt_Bosmang', '_doganay', '2025-05-30 11:01:51', '2025-05-30 11:02:54'),
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
(1, 2),
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
(23, 2, 4, '2025-05-30 16:08:01'),
(24, 1, 4, '2025-05-30 22:17:05'),
(31, 1, 5, '2025-05-30 14:36:47'),
(38, 1, 7, '2025-05-30 14:51:13'),
(41, 2, 7, '2025-05-30 15:01:12'),
(42, 1, 8, '2025-05-30 15:23:28');

--
-- Dökümü yapılmış tablolar için indeksler
--

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
-- Tablo için indeksler `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_UNIQUE` (`name`);

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
-- Tablo için AUTO_INCREMENT değeri `discussion_posts`
--
ALTER TABLE `discussion_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Tablo için AUTO_INCREMENT değeri `discussion_topics`
--
ALTER TABLE `discussion_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `equipment_slots`
--
ALTER TABLE `equipment_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photos`
--
ALTER TABLE `gallery_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photo_likes`
--
ALTER TABLE `gallery_photo_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `guides`
--
ALTER TABLE `guides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Tablo için AUTO_INCREMENT değeri `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

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
