-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 13 Haz 2025, 22:51:29
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

--
-- Tablo döküm verisi `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(2261, 1, 'role_viewed', 'role', 2, NULL, '{\"role_name\":\"admin\",\"action_type\":\"get_role_data\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 18:45:37'),
(2262, 1, 'role_viewed', 'role', 2, NULL, '{\"role_name\":\"admin\",\"action_type\":\"get_role_data\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 18:45:45'),
(2263, 1, 'role_permissions_viewed', 'role', 2, NULL, '{\"permission_count\":89,\"can_manage\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 18:45:47'),
(2264, 1, 'role_permissions_updated', 'role', 2, '{\"permissions\":[\"admin.panel.access\",\"admin.settings.view\",\"admin.settings.edit\",\"admin.users.view\",\"admin.users.edit_status\",\"admin.users.assign_roles\",\"admin.users.delete\",\"admin.roles.view\",\"admin.roles.create\",\"admin.roles.edit\",\"event.view_public\",\"event.view_members_only\",\"event.view_faction_only\",\"event.view_all\",\"event.create\",\"event.edit_own\",\"event.edit_all\",\"event.delete_own\",\"event.delete_all\",\"event.participate\",\"event.manage_participants\",\"gallery.view_public\",\"gallery.view_approved\",\"gallery.upload\",\"gallery.like\",\"gallery.delete_own\",\"gallery.delete_any\",\"gallery.manage_all\",\"loadout.view_public\",\"loadout.view_members_only\",\"loadout.view_published\",\"loadout.manage_sets\",\"loadout.manage_items\",\"loadout.manage_slots\",\"admin.audit_log.view\",\"admin.audit_log.export\",\"admin.system.security\",\"gallery.comment.create\",\"gallery.comment.edit_own\",\"gallery.comment.edit_all\",\"gallery.comment.delete_own\",\"gallery.comment.delete_all\",\"gallery.comment.like\",\"forum.view_public\",\"forum.view_members_only\",\"forum.view_faction_only\",\"forum.topic.create\",\"forum.topic.reply\",\"forum.topic.edit_own\",\"forum.topic.edit_all\",\"forum.topic.delete_own\",\"forum.topic.delete_all\",\"forum.topic.pin\",\"forum.topic.lock\",\"forum.post.edit_own\",\"forum.post.edit_all\",\"forum.post.delete_own\",\"forum.post.delete_all\",\"forum.post.like\",\"forum.category.manage\",\"forum.tags.manage\",\"forum.view_all_categories\",\"loadout.manage_weapon_attachments\",\"event_role.view_public\",\"event_role.view_members_only\",\"event_role.view_all\",\"event_role.create\",\"event_role.edit_own\",\"event_role.edit_all\",\"event_role.delete_own\",\"event_role.delete_all\",\"event_role.assign_to_events\",\"event_role.join\",\"event_role.manage_participants\",\"event_role.manage_requirements\",\"event_role.verify_skills\",\"event_role.view_statistics\",\"event_role.manage_all\",\"skill_tag.view_own\",\"skill_tag.view_all\",\"skill_tag.add_own\",\"skill_tag.manage_all\",\"loadout.create_sets\",\"loadout.edit_own_sets\",\"loadout.edit_all_sets\",\"loadout.delete_own_sets\",\"loadout.delete_all_sets\",\"loadout.publish_sets\",\"loadout.approve_sets\"]}', '{\"permissions\":[\"admin.audit_log.export\",\"admin.audit_log.view\",\"admin.panel.access\",\"admin.roles.create\",\"admin.roles.delete\",\"admin.roles.edit\",\"admin.roles.view\",\"admin.settings.edit\",\"admin.settings.view\",\"admin.system.security\",\"admin.users.assign_roles\",\"admin.users.delete\",\"admin.users.edit_status\",\"admin.users.view\",\"event.create\",\"event.delete_all\",\"event.delete_own\",\"event.edit_all\",\"event.edit_own\",\"event.manage_participants\",\"event.participate\",\"event.view_all\",\"event.view_faction_only\",\"event.view_members_only\",\"event.view_public\",\"event_role.assign_to_events\",\"event_role.create\",\"event_role.delete_all\",\"event_role.delete_own\",\"event_role.edit_all\",\"event_role.edit_own\",\"event_role.join\",\"event_role.manage_all\",\"event_role.manage_participants\",\"event_role.manage_requirements\",\"event_role.verify_skills\",\"event_role.view_all\",\"event_role.view_members_only\",\"event_role.view_public\",\"event_role.view_statistics\",\"forum.category.manage\",\"forum.post.delete_all\",\"forum.post.delete_own\",\"forum.post.edit_all\",\"forum.post.edit_own\",\"forum.post.like\",\"forum.tags.manage\",\"forum.topic.create\",\"forum.topic.delete_all\",\"forum.topic.delete_own\",\"forum.topic.edit_all\",\"forum.topic.edit_own\",\"forum.topic.lock\",\"forum.topic.pin\",\"forum.topic.reply\",\"forum.view_all_categories\",\"forum.view_faction_only\",\"forum.view_members_only\",\"forum.view_public\",\"gallery.comment.create\",\"gallery.comment.delete_all\",\"gallery.comment.delete_own\",\"gallery.comment.edit_all\",\"gallery.comment.edit_own\",\"gallery.comment.like\",\"gallery.delete_any\",\"gallery.delete_own\",\"gallery.like\",\"gallery.manage_all\",\"gallery.upload\",\"gallery.view_approved\",\"gallery.view_public\",\"loadout.approve_sets\",\"loadout.create_sets\",\"loadout.delete_all_sets\",\"loadout.delete_own_sets\",\"loadout.edit_all_sets\",\"loadout.edit_own_sets\",\"loadout.manage_items\",\"loadout.manage_sets\",\"loadout.manage_slots\",\"loadout.manage_weapon_attachments\",\"loadout.publish_sets\",\"loadout.view_members_only\",\"loadout.view_public\",\"loadout.view_published\",\"scg.hangar.view\",\"skill_tag.add_own\",\"skill_tag.manage_all\",\"skill_tag.view_all\",\"skill_tag.view_own\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 18:45:57'),
(2265, 1, 'role_permissions_updated', 'role', 2, NULL, '{\"action\":\"role_permissions_updated\",\"role_id\":2,\"user_ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"timestamp\":1749840357,\"session_id\":\"d31qj03k626sv1ps8d5dljf9pl\",\"details\":{\"role_name\":\"admin\",\"old_permission_count\":89,\"new_permission_count\":91,\"security_impact\":\"high\",\"affected_users\":4,\"critical_changes\":{\"added\":{\"4\":\"admin.roles.delete\"},\"removed\":[]}}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 18:45:57'),
(2266, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 20:46:07\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 18:46:07'),
(2267, 1, 'role_viewed', 'role', 2, NULL, '{\"role_name\":\"admin\",\"action_type\":\"get_role_data\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:46:51'),
(2268, 1, 'role_updated', 'role', 2, '{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#04ff00\",\"priority\":1}', '{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#0008ff\",\"priority\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:46:58'),
(2269, 1, 'role_updated', 'role', 2, NULL, '{\"action\":\"role_updated\",\"role_id\":2,\"user_ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"timestamp\":1749844018,\"session_id\":\"pnu0ocqb9g85lmgevsu68tug84\",\"details\":{\"old_values\":{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#04ff00\",\"priority\":1},\"new_values\":{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#0008ff\",\"priority\":1}}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:46:58'),
(2270, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 21:49:43\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:49:43'),
(2271, 1, 'role_viewed', 'role', 2, NULL, '{\"role_name\":\"admin\",\"action_type\":\"get_role_data\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:49:48'),
(2272, 1, 'role_permissions_viewed', 'role', 2, NULL, '{\"permission_count\":91,\"can_manage\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:49:50'),
(2273, 10, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 21:51:56\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:51:56'),
(2274, 10, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 21:51:59\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:51:59'),
(2275, 10, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:53:16'),
(2276, 10, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:53:18'),
(2277, 10, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:53:21'),
(2278, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 21:54:52\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:54:52'),
(2279, 1, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:55:08'),
(2280, 1, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:55:10'),
(2281, 1, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:55:14'),
(2282, 1, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:55:17'),
(2283, 1, 'user_popover_viewed', 'user', 1, NULL, '{\"target_user\":1,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:55:22'),
(2284, 10, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 21:56:25\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 19:56:25'),
(2285, 10, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:01:23\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:23'),
(2286, 1, 'role_viewed', 'role', 2, NULL, '{\"role_name\":\"admin\",\"action_type\":\"get_role_data\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:39'),
(2287, 1, 'role_updated', 'role', 2, '{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#0008ff\",\"priority\":1}', '{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#ffa200\",\"priority\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:44'),
(2288, 1, 'role_updated', 'role', 2, NULL, '{\"action\":\"role_updated\",\"role_id\":2,\"user_ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"timestamp\":1749844904,\"session_id\":\"7qnl11eqttbnaib55pjgue0tg4\",\"details\":{\"old_values\":{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#0008ff\",\"priority\":1},\"new_values\":{\"name\":\"admin\",\"description\":\"Site Y\\u00f6neticisi - T\\u00fcm yetkilere sahip\",\"color\":\"#ffa200\",\"priority\":1}}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:44'),
(2289, 1, 'role_viewed', 'role', 5, NULL, '{\"role_name\":\"uye\",\"action_type\":\"get_role_data\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:49'),
(2290, 1, 'role_permissions_viewed', 'role', 5, NULL, '{\"permission_count\":41,\"can_manage\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:51'),
(2291, 1, 'role_permissions_updated', 'role', 5, '{\"permissions\":[\"event.view_public\",\"event.view_members_only\",\"event.create\",\"event.edit_own\",\"event.delete_own\",\"event.participate\",\"gallery.view_public\",\"gallery.view_approved\",\"gallery.upload\",\"gallery.like\",\"gallery.delete_own\",\"loadout.view_public\",\"loadout.view_members_only\",\"loadout.view_published\",\"gallery.comment.create\",\"gallery.comment.edit_own\",\"gallery.comment.delete_own\",\"gallery.comment.like\",\"forum.view_public\",\"forum.view_members_only\",\"forum.view_faction_only\",\"forum.topic.create\",\"forum.topic.reply\",\"forum.topic.edit_own\",\"forum.topic.edit_all\",\"forum.topic.delete_own\",\"forum.topic.delete_all\",\"forum.topic.pin\",\"forum.topic.lock\",\"forum.post.edit_own\",\"forum.post.edit_all\",\"forum.post.delete_own\",\"forum.post.delete_all\",\"forum.post.like\",\"forum.category.manage\",\"forum.tags.manage\",\"event_role.view_public\",\"event_role.view_members_only\",\"event_role.view_all\",\"event_role.view_statistics\",\"skill_tag.view_own\"]}', '{\"permissions\":[\"event.create\",\"event.delete_own\",\"event.edit_own\",\"event.participate\",\"event.view_members_only\",\"event.view_public\",\"event_role.view_all\",\"event_role.view_members_only\",\"event_role.view_public\",\"event_role.view_statistics\",\"forum.category.manage\",\"forum.post.delete_all\",\"forum.post.delete_own\",\"forum.post.edit_all\",\"forum.post.edit_own\",\"forum.post.like\",\"forum.tags.manage\",\"forum.topic.create\",\"forum.topic.delete_all\",\"forum.topic.delete_own\",\"forum.topic.edit_all\",\"forum.topic.edit_own\",\"forum.topic.lock\",\"forum.topic.pin\",\"forum.topic.reply\",\"forum.view_faction_only\",\"forum.view_members_only\",\"forum.view_public\",\"gallery.comment.create\",\"gallery.comment.delete_own\",\"gallery.comment.edit_own\",\"gallery.comment.like\",\"gallery.delete_own\",\"gallery.like\",\"gallery.upload\",\"gallery.view_approved\",\"gallery.view_public\",\"loadout.view_members_only\",\"loadout.view_public\",\"loadout.view_published\",\"scg.hangar.view\",\"skill_tag.view_own\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:58'),
(2292, 1, 'role_permissions_updated', 'role', 5, NULL, '{\"action\":\"role_permissions_updated\",\"role_id\":5,\"user_ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"timestamp\":1749844918,\"session_id\":\"7qnl11eqttbnaib55pjgue0tg4\",\"details\":{\"role_name\":\"uye\",\"old_permission_count\":41,\"new_permission_count\":42,\"security_impact\":\"low\",\"affected_users\":2,\"critical_changes\":{\"added\":[],\"removed\":[]}}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:01:58'),
(2293, 10, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:02:00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:02:00'),
(2294, 10, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:07:04\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:07:04'),
(2295, 1, 'user_popover_viewed', 'user', 6, NULL, '{\"target_user\":6,\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:18:59'),
(2296, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:44:09\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:44:09'),
(2297, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:44:12\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:44:12'),
(2298, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:45:59\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:45:59'),
(2299, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:47:38\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:47:38'),
(2300, 1, 'homepage_accessed', 'page', NULL, NULL, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"access_time\":\"2025-06-13 22:47:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-13 20:47:45');

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
(12, 'Medikal Araç', 'FPS_Consumable', 1, 120),
(13, 'Multi-Tool Attachment', 'Utility,WeaponAttachment', 1, 130);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text NOT NULL,
  `event_thumbnail_path` varchar(255) DEFAULT NULL,
  `event_location` varchar(255) DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `visibility` enum('public','members_only','faction_only') NOT NULL DEFAULT 'members_only',
  `status` enum('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `registration_deadline` datetime DEFAULT NULL COMMENT 'Katılım son tarihi',
  `auto_approve` tinyint(1) DEFAULT 0 COMMENT 'Otomatik katılım onayı',
  `event_notes` text DEFAULT NULL COMMENT 'Organizatör notları',
  `max_participants` int(11) DEFAULT NULL COMMENT 'Maksimum katılımcı sayısı (opsiyonel sınır)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `events`
--

INSERT INTO `events` (`id`, `created_by_user_id`, `event_title`, `event_description`, `event_thumbnail_path`, `event_location`, `event_date`, `visibility`, `status`, `created_at`, `updated_at`, `registration_deadline`, `auto_approve`, `event_notes`, `max_participants`) VALUES
(1, 1, 'ASD Radiaton mission', 'ASD Radiaton mission tamamlanacak ve yarraklar atmosfere sokulacak', 'uploads/events/event_1749508994_6847638240872.png', 'Pyro V Pyro 1', '2025-06-10 15:26:00', 'members_only', 'published', '2025-06-09 12:27:22', '2025-06-09 22:43:14', NULL, 0, '', NULL),
(2, 1, 'Etkinlik 1', '# [SCG] ILGARION TURANIS SAHA OPERASYONLARI EL KİTABI (FPS)\r\n\r\n\r\n## ÖNSÖZ\r\n\r\nBu el kitabı, Ilgarion Turanis operatörlerinin Birleşik Dünya İmparatorluğu (UEE) sınırları dahilinde ve ötesindeki Birinci Şahıs Nişancı (FPS) odaklı operasyonlarında uyacakları standartları, prosedürleri ve taktikleri belirlemek amacıyla hazırlanmıştır. Her operatör, bu el kitabında belirtilen prensiplere ve SCG\'nin genel nizam ve yönetmeliklerine harfiyen uymakla mükelleftir.\r\n\r\n## İÇİNDEKİLER\r\n\r\n1.  BÖLÜM 1: TEMEL EMİRLER VE DİSİPLİN ESASLARI\r\n    * 1.1 Maksat\r\n    * 1.2 Disiplin Esasları ve Davranış Kuralları\r\n2.  BÖLÜM 2: OPERATÖR TEÇHİZATI - STANDART DONANIM (ILGARION TURANIS)\r\n    * 2.1 Zırh Teçhizatı\r\n    * 2.2 Silah ve Yardımcı Muharebe Teçhizatı\r\n    * 2.3 Mühimmat İkmali\r\n    * 2.4 Tıbbi Malzeme ve Teçhizat\r\n    * 2.5 Standart Donanım Şablonları ve Güncellemeler\r\n3.  BÖLÜM 3: FPS UZMANLIK ALANLARI VE TAKIM GÖREV DAĞILIMI\r\n    * 3.1 Genel Esaslar\r\n    * 3.2 Görev Tanımları\r\n4.  BÖLÜM 4: HAREKAT USULLERİ (SOP)\r\n    * 4.1 Görev Tevdii ve Harekata Hazırlık\r\n    * 4.2 İntikal ve Sızma Usulleri\r\n    * 4.3 Temas ve Alan Kontrolü Harekatı\r\n    * 4.4 Görev İcrası ve Geri İntikal\r\n5.  BÖLÜM 5: ANGAJMAN KURALLARI (ROE) - (SCG ONAYLI)\r\n    * 5.1 Angajman Durum Kodları\r\n    * 5.2 Hususi Durumlar ve Emirler\r\n6.  BÖLÜM 6: MUHAREBE DÜZENLERİ VE İNTİKAL TEKNİKLERİ (FPS)\r\n    * 6.1 Genel Prensipler\r\n    * 6.2 Meskun Mahal / Kapalı Alan (CQB) Muharebe Düzenleri ve Taktikleri\r\n    * 6.3 Açık Arazi / Genel İntikal Düzenleri\r\n7.  BÖLÜM 7: MUHABERE USULLERİ VE PROTOKOLLERİ\r\n    * 7.1 Temel Muhabere Disiplini\r\n    * 7.2 Standart Rapor Formatları ve Kodlamalar (Örnekler)', 'uploads/events/event_1749503194_68474cda585a5.png', 'Lorville', '2025-06-11 10:10:00', 'public', 'published', '2025-06-09 21:06:34', NULL, NULL, 0, '', NULL),
(3, 1, 'Etkinlik 1', '## BÖLÜM 1: TEMEL EMİRLER VE DİSİPLİN ESASLARI\r\n\r\n### 1.1 Maksat\r\n\r\nBu el kitabı, Ilgarion Turanis operatörlerinin tüm FPS görevlerinde (standart kontratlar, yüksek riskli Çatışmalı Bölge operasyonları, stratejik maden sahası kontrol görevleri dahil) harekat etkinliğini, ekip koordinasyonunu ve beka kabiliyetini azami seviyeye çıkarmayı hedefler. Her operatör, bu el kitabında sıralanan prensiplere ve SCG\'nin genel nizam ve yönetmeliklerine mutlak surette riayet edecektir.\r\n\r\n### 1.2 Disiplin Esasları ve Davranış Kuralları\r\n\r\nHer operatör, aşağıda belirtilen disiplin esaslarına ve davranış kurallarına uymakla yükümlüdür:\r\n\r\n* **Askeri Disiplin ve Profesyonellik:** Vazife başında ve vazife haricinde her türlü hal ve hareketinde askeri disiplin ve SCG\'nin gerektirdiği profesyonellik seviyesini muhafaza et. Davranışlarınla birliğin ve SCG\'nin saygınlığını ve etkinliğini temsil et.\r\n* **Vazife Odaklılık:** Verilen vazifenin dışına çıkma. Ana hedefe ve vazife gereklerine mutlak surette odaklan. Lüzumsuz angajmanlardan, sivil kayıplarına yol açabilecek eylemlerden ve özellikle adli sicil (CrimeStat) oluşturacak faaliyetlerden kaçın (vazife açıkça bunu gerektirmedikçe).\r\n* **Gayri Muharip Unsurlara Karşı Muamele:** Tespit edilmiş muharip olmayan unsurlara (siviller, dost kuvvetler, tarafsız şahıslar) karşı kesinlikle ateş açma veya hasmane tutum sergileme. Şüpheli durumlarda, angaje olmadan evvel mutlak surette teyit al veya Tim Lideri\'nin (TL) talimatını bekle.\r\n* **Harp Ganimeti ve Malzeme Disiplini:** Öncelik daima vazife ile ilgili malzemeler (kritik veriler, görev hedefleri) ve acil tıbbi yardım malzemelerindedir. Şahsi harp ganimeti toplama faaliyeti, harekat sahasının emniyeti tam olarak sağlandıktan ve Tim Lideri\'nin müsaadesi alındıktan sonra icra edilir. Malzeme paylaşımı, Tim Lideri\'nin direktifleri doğrultusunda yapılır.\r\n* **Muhabere Disiplini:** Telsiz (muhabere) disiplinine harfiyen uy. Muhabere kısa, açık, net ve sadece vazife ile ilgili bilgileri içermelidir. Muhabere ağını lüzumsuz meşgul etme.\r\n* **Gizlilik ve Bilgi Güvenliği:** Vazife detayları, harekat planları, operatör kimlikleri ve SCG\'ye ait tüm bilgiler gizlidir. Yetkisiz üçüncü şahıslarla paylaşımı kesinlikle yasaktır.\r\n* **Etkinlik Katılım Sorumluluğu:** Kayıt olunan görev ve etkinliklere iştirak esastır. Zorunlu hallerde katılım sağlanamayacaksa, durum derhal ilgili Tim Lideri\'ne veya Etkinlik Lideri\'ne (görevlendirildiyse) gerekçesiyle birlikte rapor edilir.\r\n\r\n![Ekran Grnts 15](../../uploads/events/images/event_img_1_1749503975_68474fe775358.png)', 'uploads/events/event_1749503487_68474dff03f16.png', 'Lorville', '2026-02-10 10:10:00', 'public', 'published', '2025-06-09 21:11:27', '2025-06-09 23:07:48', NULL, 0, '', NULL),
(5, 13, 'OPERASYON ŞARKIISISIIIASD', '<h2><b>İlgarion turanis sikiş etkinliğiii</b></h2><b><br /></b><img src=\"https://ilgarionturanis.com/uploads/gallery/gallery_user7_1748875886_23570e38.png\" alt=\"gallery_user7_1748875886_23570e38.png\" style=\"text-align:center;\" />', 'uploads/events/event_1749676553_6849f209a40cb.png', 'SUNO', '2025-06-12 12:42:00', 'public', 'published', '2025-06-10 09:43:01', '2025-06-12 01:32:22', NULL, 0, '', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_participations`
--

CREATE TABLE `event_participations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_slot_id` int(11) DEFAULT NULL COMMENT 'Eğer bir role katılıyorsa',
  `participation_status` enum('pending','confirmed','maybe','declined','cancelled') DEFAULT 'pending' COMMENT 'Katılım durumu: pending=beklemede, confirmed=onaylandı, maybe=belki, declined=katılmıyor, cancelled=iptal edildi',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Oluşturulma tarihi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `event_participations`
--

INSERT INTO `event_participations` (`id`, `event_id`, `user_id`, `role_slot_id`, `participation_status`, `registered_at`, `updated_at`, `created_at`) VALUES
(2, 3, 1, 6, 'confirmed', '2025-06-09 23:59:40', '2025-06-10 01:15:15', '2025-06-09 23:59:40'),
(4, 2, 13, 1, 'confirmed', '2025-06-10 09:35:48', NULL, '2025-06-10 09:35:48'),
(5, 3, 13, 6, 'confirmed', '2025-06-10 09:36:06', NULL, '2025-06-10 09:36:06'),
(11, 5, 1, NULL, 'confirmed', '2025-06-12 01:27:40', '2025-06-12 01:27:44', '2025-06-12 01:27:40');

--
-- Tetikleyiciler `event_participations`
--
DELIMITER $$
CREATE TRIGGER `set_confirmed_on_role_assignment` BEFORE INSERT ON `event_participations` FOR EACH ROW BEGIN
  IF NEW.role_slot_id IS NOT NULL THEN
    SET NEW.participation_status = 'confirmed';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `set_confirmed_on_role_update` BEFORE UPDATE ON `event_participations` FOR EACH ROW BEGIN
  IF NEW.role_slot_id IS NOT NULL THEN
    SET NEW.participation_status = 'confirmed';
  ELSEIF OLD.role_slot_id IS NOT NULL AND NEW.role_slot_id IS NULL THEN
    -- Role'den çıkarıldıysa status'ü maybe yap
    SET NEW.participation_status = 'maybe';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_roles`
--

CREATE TABLE `event_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `role_description` text DEFAULT NULL,
  `role_icon` varchar(50) DEFAULT NULL COMMENT 'Font Awesome icon class',
  `suggested_loadout_id` int(11) DEFAULT NULL COMMENT 'Önerilen loadout set ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `event_roles`
--

INSERT INTO `event_roles` (`id`, `role_name`, `role_description`, `role_icon`, `suggested_loadout_id`, `created_at`) VALUES
(6, 'Vehicle Gunner', 'Araç silah operatörüüü', 'fas fa-user', 9, '2025-06-09 12:07:45'),
(8, 'Turretçi', 'Turretçidir kendisi', 'fas fa-crosshairs', 10, '2025-06-10 10:14:53'),
(9, 'King', 'Doğanay olabilir. Bence o olur gibi.', 'fas fa-star', 10, '2025-06-10 10:19:07');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_role_requirements`
--

CREATE TABLE `event_role_requirements` (
  `role_id` int(11) NOT NULL,
  `skill_tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `event_role_requirements`
--

INSERT INTO `event_role_requirements` (`role_id`, `skill_tag_id`) VALUES
(6, 8),
(6, 12),
(9, 8),
(9, 12);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_role_slots`
--

CREATE TABLE `event_role_slots` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `slot_count` int(11) NOT NULL DEFAULT 1 COMMENT 'Bu rolden kaç kişi gerekli'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `event_role_slots`
--

INSERT INTO `event_role_slots` (`id`, `event_id`, `role_id`, `slot_count`) VALUES
(1, 2, 6, 5),
(6, 3, 6, 4),
(35, 5, 9, 10),
(36, 5, 8, 10),
(37, 5, 6, 50);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_visibility_roles`
--

CREATE TABLE `event_visibility_roles` (
  `event_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_categories`
--

CREATE TABLE `forum_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'Font Awesome icon class',
  `color` varchar(7) DEFAULT '#bd912a' COMMENT 'Kategori rengi',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Gösterim sırası',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `visibility` enum('public','members_only','faction_only') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `forum_categories`
--

INSERT INTO `forum_categories` (`id`, `name`, `description`, `slug`, `icon`, `color`, `display_order`, `is_active`, `visibility`, `created_at`, `updated_at`) VALUES
(1, 'Ilgarion Turanis', 'Ilgarion Turanis üyelerine özel kategori.', 'ilgarion-turanis', 'fas fa-comments', '#73E4E0', 2, 1, 'public', '2025-06-03 17:07:32', '2025-06-06 00:01:32'),
(3, 'Forum Kuralları, Öneriler ve Duyurular', 'Forumun işleyişiyle ilgili kuralların, resmi duyuruların ve kullanıcı önerilerinin dikkate alındığı bölümdür. Katılmadan önce kuralları okumanız tavsiye edilir.', 'forum-kural-öner-duyurular', 'fas fa-rocket', '#b8845c', 3, 1, 'public', '2025-06-03 17:07:32', '2025-06-04 15:06:35'),
(4, ' Oynanış Rehberleri ve Taktikler', 'Yeni başlayanlar için rehberler, deneyimli oyunculardan ipuçları ve görev stratejileri.', 'oynanıs-rehberleri-ve-taktikler', 'fas fa-user-plus', '#b8845c', 4, 1, 'public', '2025-06-03 17:07:32', '2025-06-04 15:07:21'),
(5, 'Ekonomi, Ticaret ve Madencilik', 'UEC kazanma yolları, ticaret rotaları, mining teknikleri ve yatırım tavsiyeleri burada.', 'ekonomi-ticaret-madencilik', 'fas fa-space-shuttle', '#b8845c', 5, 1, 'public', '2025-06-04 03:56:13', '2025-06-04 15:05:55'),
(6, 'PVP, FPS ve Güvenlik Operasyonları', 'Dogfight’lar, FPS çarpışmaları ve güvenlik görevleri üzerine taktiksel sohbetler.', 'pvp-fps-ve-guvenlik-operasyonları', 'fas fa-tasks', '#b8845c', 6, 1, 'public', '2025-06-04 03:56:13', '2025-06-04 15:05:38'),
(7, 'Genel Star Citizen Tartışmaları', 'Oyunla ilgili genel konular, güncellemeler, haberler ve topluluk sohbetleri burada.', 'genel-star-citizen-tartışmaları', 'fas fa-tasks', '#b8845c', 3, 1, 'public', '2025-06-04 03:56:13', '2025-06-04 15:13:08'),
(8, 'Deneme', 'Zort zurt', 'deneme', 'fas fa-comments', '#bd912a', 0, 1, 'faction_only', '2025-06-13 11:31:13', '2025-06-13 11:31:13'),
(9, 'Star Citizen Global', 'SCG Üyelerine özel kategori.', 'star-citizen-global', 'fas fa-space-shuttle', '#bd912a', 2, 1, 'public', '2025-06-13 19:58:09', '2025-06-13 19:58:25');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_category_visibility_roles`
--

CREATE TABLE `forum_category_visibility_roles` (
  `category_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `forum_category_visibility_roles`
--

INSERT INTO `forum_category_visibility_roles` (`category_id`, `role_id`) VALUES
(8, 68);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  `edited_by_user_id` int(11) DEFAULT NULL,
  `like_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_post_likes`
--

CREATE TABLE `forum_post_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_tags`
--

CREATE TABLE `forum_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#bd912a',
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `forum_tags`
--

INSERT INTO `forum_tags` (`id`, `name`, `slug`, `color`, `usage_count`, `created_at`) VALUES
(1, 'başlangıç', 'baslangic', '#bd912a', 0, '2025-06-05 11:32:13'),
(2, 'soru', 'soru', '#bd912a', 0, '2025-06-05 11:32:13'),
(3, 'haber', 'haber', '#bd912a', 0, '2025-06-05 11:32:13'),
(4, 'mining', 'mining', '#bd912a', 0, '2025-06-05 11:32:13'),
(5, 'combat', 'combat', '#bd912a', 0, '2025-06-05 11:32:13'),
(6, 'star citizen', 'star-citizen', '#bd912a', 0, '2025-06-05 11:33:45'),
(7, 'pyro', 'pyro', '#bd912a', 0, '2025-06-05 11:33:45'),
(8, 'hull-c', 'hull-c', '#bd912a', 0, '2025-06-05 11:33:45'),
(9, 'rehber', 'rehber', '#bd912a', 0, '2025-06-05 11:33:45'),
(10, 'tartışma', 'tartisma', '#bd912a', 0, '2025-06-05 15:02:12'),
(11, 'exploration', 'exploration', '#bd912a', 0, '2025-06-05 15:02:12'),
(12, 'öneri', 'oneri', '#bd912a', 0, '2025-06-05 15:02:12'),
(13, 'deneme', 'deneme', '#bd912a', 0, '2025-06-06 20:47:19');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_topics`
--

CREATE TABLE `forum_topics` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL COMMENT 'İlk gönderi içeriği',
  `visibility` enum('public','members_only','faction_only') NOT NULL DEFAULT 'public',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Sabitlenmiş konu mu?',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Kilitli konu mu?',
  `view_count` int(11) NOT NULL DEFAULT 0,
  `reply_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Yanıt sayısı',
  `last_post_user_id` int(11) DEFAULT NULL COMMENT 'Son gönderiyi yapan kullanıcı',
  `last_post_at` timestamp NULL DEFAULT NULL COMMENT 'Son gönderi zamanı',
  `tags` varchar(500) DEFAULT NULL COMMENT 'Virgülle ayrılmış etiketler',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_topic_likes`
--

CREATE TABLE `forum_topic_likes` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_topic_tags`
--

CREATE TABLE `forum_topic_tags` (
  `topic_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `forum_topic_visibility_roles`
--

CREATE TABLE `forum_topic_visibility_roles` (
  `topic_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gallery_comment_likes`
--

CREATE TABLE `gallery_comment_likes` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Galeri yorumları için beğeni sistemi';

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
(10, 4, 'uploads/gallery/gallery_user4_1748009937_9d48b962.png', '', 0, 1, '2025-05-23 14:18:57'),
(11, 1, 'uploads/gallery/gallery_user1_1748013808_eb1dc946.jpg', '', 0, 1, '2025-05-23 15:23:28'),
(12, 7, 'uploads/gallery/gallery_user7_1748014812_c4dd7be5.jpg', '', 0, 1, '2025-05-23 15:40:12'),
(13, 7, 'uploads/gallery/gallery_user7_1748014825_f2467d47.jpg', '', 0, 1, '2025-05-23 15:40:25'),
(14, 7, 'uploads/gallery/gallery_user7_1748014832_98d27209.jpg', '', 0, 1, '2025-05-23 15:40:32'),
(15, 7, 'uploads/gallery/gallery_user7_1748014848_f585e08e.jpg', '', 0, 1, '2025-05-23 15:40:48'),
(16, 7, 'uploads/gallery/gallery_user7_1748014868_ba6e0896.jpg', '', 0, 1, '2025-05-23 15:41:08'),
(17, 7, 'uploads/gallery/gallery_user7_1748015022_2900c6fe.jpg', '', 0, 1, '2025-05-23 15:43:42'),
(18, 7, 'uploads/gallery/gallery_user7_1748015050_ee4af680.jpg', '', 0, 1, '2025-05-23 15:44:10'),
(19, 7, 'uploads/gallery/gallery_user7_1748015064_1b259d4d.jpg', '', 0, 1, '2025-05-23 15:44:24'),
(20, 7, 'uploads/gallery/gallery_user7_1748015084_4a839680.jpg', '', 0, 1, '2025-05-23 15:44:44'),
(21, 7, 'uploads/gallery/gallery_user7_1748015104_b0271b2a.jpg', '', 0, 1, '2025-05-23 15:45:04'),
(22, 7, 'uploads/gallery/gallery_user7_1748015337_fe088081.png', '', 0, 1, '2025-05-23 15:48:57'),
(23, 7, 'uploads/gallery/gallery_user7_1748016112_23af335b.png', '', 0, 1, '2025-05-23 16:01:52'),
(24, 1, 'uploads/gallery/gallery_user1_1748016918_b631f663.png', '', 0, 1, '2025-05-23 16:15:18'),
(26, 7, 'uploads/gallery/gallery_user7_1748049738_aa2211a2.jpg', '', 0, 1, '2025-05-24 01:22:18'),
(28, 7, 'uploads/gallery/gallery_user7_1748049841_38db116a.jpg', '', 0, 1, '2025-05-24 01:24:01'),
(29, 1, 'uploads/gallery/gallery_user1_1748212710_3bf12760.png', '', 0, 1, '2025-05-25 22:38:30'),
(30, 1, 'uploads/gallery/gallery_user1_1748216354_380aa3c1.png', '', 0, 1, '2025-05-25 23:39:14'),
(34, 7, 'uploads/gallery/gallery_user7_1748568276_edde91f1.webp', 'bloom', 0, 1, '2025-05-30 01:24:36'),
(35, 7, 'uploads/gallery/gallery_user7_1748629049_ddc74ed5.png', 'orbital laser', 0, 1, '2025-05-30 18:17:29'),
(37, 13, 'uploads/gallery/gallery_user13_1748823839_e51d1641.png', 'Mekan hazır.', 0, 1, '2025-06-02 00:23:59'),
(38, 7, 'uploads/gallery/gallery_user7_1748825114_ecc2b18d.png', 'after orbital laser', 0, 1, '2025-06-02 00:45:14'),
(39, 13, 'uploads/gallery/gallery_user13_1748841875_4c4c5963.png', 'Bir idrisimiz yok ama polaris de çok can yakıyor..', 0, 1, '2025-06-02 05:24:35'),
(40, 7, 'uploads/gallery/gallery_user7_1748875013_c7e07cc7.png', 'orbital laser engage phase', 0, 1, '2025-06-02 14:36:54'),
(41, 7, 'uploads/gallery/gallery_user7_1748875886_23570e38.png', 'orbital blast', 0, 1, '2025-06-02 14:51:26'),
(42, 7, 'uploads/gallery/gallery_user7_1748875936_f1baa7cc.png', 'ATLS geo mining', 0, 1, '2025-06-02 14:52:16'),
(43, 7, 'uploads/gallery/gallery_user7_1748875965_d4e03da7.png', 'ATLS GEO mining', 0, 1, '2025-06-02 14:52:45'),
(45, 7, 'uploads/gallery/gallery_user7_1748876036_6b314a2b.png', 'Lorville - Teasa  Spaceport', 0, 1, '2025-06-02 14:53:56'),
(46, 7, 'uploads/gallery/gallery_user7_1748877305_e0e8712a.png', 'aberdeen clouds w polaris', 0, 1, '2025-06-02 15:15:05'),
(47, 13, 'uploads/gallery/gallery_user13_1748921244_d8f47c85.png', 'Pyroda devri alem.', 0, 1, '2025-06-03 03:27:24'),
(48, 13, 'uploads/gallery/gallery_user13_1748923749_549a8c66.png', 'Syulen keyifli gemi.', 0, 1, '2025-06-03 04:09:09'),
(79, 1, 'uploads/gallery/gallery_user1_823854967684ac9f905ae02.25370047.gif', 'zooort', 1, 0, '2025-06-12 12:37:13');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gallery_photo_comments`
--

CREATE TABLE `gallery_photo_comments` (
  `id` int(11) NOT NULL,
  `photo_id` int(11) NOT NULL COMMENT 'Yorumun ait olduğu fotoğraf ID''si',
  `user_id` int(11) NOT NULL COMMENT 'Yorumu yazan kullanıcının ID''si',
  `parent_comment_id` int(11) DEFAULT NULL COMMENT 'Eğer bu bir yanıtsa, yanıtlanan yorumun ID''si',
  `comment_text` text NOT NULL COMMENT 'Yorum içeriği',
  `is_edited` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Yorum düzenlenmiş mi?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Yorumun oluşturulma zamanı',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Son düzenlenme zamanı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Galeri fotoğrafları için yorum sistemi';

--
-- Tablo döküm verisi `gallery_photo_comments`
--

INSERT INTO `gallery_photo_comments` (`id`, `photo_id`, `user_id`, `parent_comment_id`, `comment_text`, `is_edited`, `created_at`, `updated_at`) VALUES
(27, 38, 1, NULL, '1', 0, '2025-06-10 01:41:12', NULL),
(28, 48, 1, NULL, '1', 0, '2025-06-10 01:42:00', NULL);

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

--
-- Tablo döküm verisi `gallery_photo_likes`
--

INSERT INTO `gallery_photo_likes` (`id`, `photo_id`, `user_id`, `liked_at`) VALUES
(64, 45, 1, '2025-06-10 01:55:30'),
(65, 46, 1, '2025-06-13 12:47:58');

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
(9, 1, 'Keşif & Klavuz Operatörü (Scout / Pointman)', 'Timin intikal düzeninin en önünde ilerleyerek ileri keşif yapar, muhtemel kontakları, pusu noktalarını ve tehlikeli bölgeleri erken safhada tespit eder.\r\nEmniyetli intikal rotalarını belirler, timin muharebe düzenlerinin yönlendirilmesine ve uygulanmasına yardımcı olur.\r\nGizlilik ve sessiz hareket prensiplerine azami riayet gösterir.\r\nScalpel Piyade Tüfeği, OT8-RF 8x taktik dürbün ile teçhiz edilmiştir. P8-AR Taarruz Silahı üzerinde Gamma Duo 2x holografik nişangah bulunmaktadır.', 'uploads/loadouts/loadout_user1_1749509727_93db6922.png', 'public', 'published', '2025-06-07 01:42:20', '2025-06-09 22:55:27'),
(10, 1, 'Deneme Seti', 'Deneme setidir bir önemi yoktur', 'uploads/loadouts/loadout_user1_1749509255_e0d968ef.png', 'members_only', 'published', '2025-06-09 22:47:35', NULL),
(11, 13, 'Star Kitten', 'Bu teçhizat, düşman üzerinde şok ve tereddüt etkisi yaratarak sizi öncelikli hedef olarak belirlemesine neden olacaktır. Düşman, \'kendine bu zararı göze alan bir unsurun, hasmına neler yapabileceğini\' hesaplayarak ateş gücünü üzerinize yoğunlaştıracaktır.', 'uploads/loadouts/loadout_user13_1749560728_10d1a858.png', 'public', 'published', '2025-06-10 13:05:28', NULL);

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
(97, 10, 1, NULL, 'Morozov-SH Helmet', '92b7a470-5842-48fe-bd53-dc9df9bb5271', 'Char_Armor_Helmet', 'Roussimoff Rehabilitation Systems', ''),
(98, 10, 2, NULL, 'Morozov-SH Core', '2dd4b126-eb45-427f-870d-4e1b4de3a22d', 'Char_Armor_Torso', 'Roussimoff Rehabilitation Systems', ''),
(99, 10, 3, NULL, 'Morozov-SH Arms Terracotta', '0ebe3817-3cbe-4a2a-9453-fbf0a81d0cbe', 'Char_Armor_Arms', 'Roussimoff Rehabilitation Systems', ''),
(100, 10, 4, NULL, 'Aves Legs', '74dd7626-27c8-4b4c-bda4-8192f251f4d5', 'Char_Armor_Legs', 'CC\'s Conversions', ''),
(101, 10, 5, NULL, 'TCS-4 Undersuit', 'd4d18f9f-bd28-4b43-a199-9f89223aa3de', 'Char_Armor_Undersuit', 'Clark Defense Systems', ''),
(102, 10, 6, NULL, 'Morozov-CH Backpack', 'c6ce6063-0d43-470a-aef3-49e65289a53e', 'Char_Armor_Backpack', 'Roussimoff Rehabilitation Systems', ''),
(103, 10, 7, NULL, 'FS-9 LMG', '6f1674b1-fb58-4661-9114-f418862751d2', 'WeaponPersonal', 'Behring Applied Technology', ''),
(104, 10, 8, NULL, 'Parallax Energy Assault Rifle', 'b144a16c-bba4-427e-9f78-bd379f9509f8', 'WeaponPersonal', '<= PLACEHOLDER =>', ''),
(105, 10, 9, NULL, 'ParaMed Medical Device', '3ad9e5f0-8c8f-42b4-adc9-fed1435c8d26', 'WeaponPersonal', 'Curelife', ''),
(106, 10, 10, NULL, 'MK-4 Frag Grenade', '46691e82-b633-4d4a-b967-3fce30a0a01c', 'WeaponPersonal', 'Behring Applied Technology', ''),
(107, 10, 12, NULL, 'MedPen (Hemozal)', '7d50411f-088c-4c99-b85a-a6eaf95504c3', 'FPS_Consumable', 'Curelife', ''),
(118, 9, 1, NULL, 'Balor HCH Helmet Black', 'f44a56dd-522f-404f-800c-c501407252e8', 'Char_Armor_Helmet', 'Clark Defense Systems', ''),
(119, 9, 2, NULL, 'ADP-mk4 Core Woodland', '8eabeef4-5d8d-4db4-a4df-9fa151062b42', 'Char_Armor_Torso', 'Clark Defense Systems', ''),
(120, 9, 3, NULL, 'ADP-mk4 Arms Woodland', 'bef9cd69-24f0-434a-ba7a-2bf751228093', 'Char_Armor_Arms', 'Clark Defense Systems', ''),
(121, 9, 4, NULL, 'ADP-mk4 Legs Woodland', 'b400fde5-495b-47b1-a9d8-210b1d5cb4fd', 'Char_Armor_Legs', 'Clark Defense Systems', ''),
(122, 9, 6, NULL, 'CSP-68H Backpack', 'fb904353-c334-4674-874c-874b64a4c6db', 'Char_Armor_Backpack', 'Clark Defense Systems', ''),
(123, 9, 7, NULL, 'Scalpel Sniper Rifle', 'bab1362b-0860-4a52-ad5b-dfb63d12f651', 'WeaponPersonal', 'Kastak Arms', ''),
(124, 9, 8, NULL, 'P8-AR Rifle', '53042ca6-a9ba-4b0e-8c36-a11a5f47feb9', 'WeaponPersonal', 'Behring Applied Technology', ''),
(125, 9, 9, NULL, 'ParaMed Medical Device', '3ad9e5f0-8c8f-42b4-adc9-fed1435c8d26', 'WeaponPersonal', 'Curelife', ''),
(126, 9, 10, NULL, 'Pyro RYT Multi-Tool', '396ccb0d-c251-484d-998e-cc3616a37ee5', 'WeaponPersonal', 'Greycat Industrial', ''),
(127, 9, 12, NULL, 'MedPen (Hemozal)', '7d50411f-088c-4c99-b85a-a6eaf95504c3', 'FPS_Consumable', 'Curelife', ''),
(128, 11, 1, NULL, 'Fieldsbury Dark Bear Helmet Guava', '0090e00a-4340-4032-82b8-32def1b39d99', 'Char_Armor_Helmet', 'CC\'s Conversions', ''),
(129, 11, 5, NULL, 'Star Kitten Racing Flight Suit', '95b17843-9a67-470d-b6c2-4bfab8c70ba2', 'Char_Armor_Undersuit', 'Mirai', ''),
(130, 11, 7, NULL, 'LH86 \"Takahashi Racing\" Pistol', 'c5b90a4e-d944-408d-afaa-48ec0cf571fb', 'WeaponPersonal', 'Gemini', '');

--
-- Tetikleyiciler `loadout_set_items`
--
DELIMITER $$
CREATE TRIGGER `cleanup_weapon_attachments_after_item_delete` AFTER DELETE ON `loadout_set_items` FOR EACH ROW BEGIN
    -- Eğer silinen item bir silahsa ve ana silah slotundaysa, attachmentlarını da sil
    IF OLD.equipment_slot_id IN (7, 8, 9) THEN
        DELETE FROM `loadout_weapon_attachments` 
        WHERE `loadout_set_id` = OLD.loadout_set_id 
        AND `parent_equipment_slot_id` = OLD.equipment_slot_id;
    END IF;
END
$$
DELIMITER ;

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
-- Tablo için tablo yapısı `loadout_weapon_attachments`
--

CREATE TABLE `loadout_weapon_attachments` (
  `id` int(11) NOT NULL,
  `loadout_set_id` int(11) NOT NULL COMMENT 'Hangi loadout setine ait',
  `parent_equipment_slot_id` int(11) NOT NULL COMMENT 'Ana silah slotu (7,8,9)',
  `attachment_slot_id` int(11) NOT NULL COMMENT 'Attachment slot ID',
  `attachment_item_name` varchar(255) NOT NULL COMMENT 'Attachment adı',
  `attachment_item_uuid` varchar(255) DEFAULT NULL COMMENT 'API UUID',
  `attachment_item_type` varchar(100) DEFAULT NULL COMMENT 'API tip (IronSight, Barrel, BottomAttachment)',
  `attachment_item_manufacturer` varchar(255) DEFAULT NULL COMMENT 'Üretici',
  `attachment_notes` text DEFAULT NULL COMMENT 'Kullanıcı notları',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Loadout setlerindeki weapon attachmentları';

--
-- Tablo döküm verisi `loadout_weapon_attachments`
--

INSERT INTO `loadout_weapon_attachments` (`id`, `loadout_set_id`, `parent_equipment_slot_id`, `attachment_slot_id`, `attachment_item_name`, `attachment_item_uuid`, `attachment_item_type`, `attachment_item_manufacturer`, `attachment_notes`, `created_at`) VALUES
(41, 10, 7, 1, 'Gamma Plus (3x Holographic)', 'fd9d06a9-5cf8-4325-9149-538d576e5146', 'IronSight', 'NV-TAC', '', '2025-06-09 22:47:35'),
(42, 10, 7, 2, 'Tacit Suppressor1', '1217e61b-1a57-4d94-8aa4-16d5fcb59adf', 'Barrel', 'ArmaMod', '', '2025-06-09 22:47:35'),
(43, 10, 7, 3, 'FieldLite Flashlight', '60fab7a8-617c-4962-8391-69221ed761d6', 'BottomAttachment', 'NV-TAC', '', '2025-06-09 22:47:35'),
(44, 10, 8, 1, 'Gamma Plus (3x Holographic)', 'fd9d06a9-5cf8-4325-9149-538d576e5146', 'IronSight', 'NV-TAC', '', '2025-06-09 22:47:35'),
(45, 10, 8, 3, 'FieldLite Flashlight', '60fab7a8-617c-4962-8391-69221ed761d6', 'BottomAttachment', 'NV-TAC', '', '2025-06-09 22:47:35'),
(52, 9, 7, 1, 'Gamma Plus (3x Holographic)', 'fd9d06a9-5cf8-4325-9149-538d576e5146', 'IronSight', 'NV-TAC', '', '2025-06-09 22:55:27'),
(53, 9, 7, 2, 'Tacit Suppressor2', 'dffa4527-2748-4f52-a7da-e57aae1c293c', 'Barrel', 'ArmaMod', '', '2025-06-09 22:55:27'),
(54, 9, 7, 3, 'FieldLite Flashlight', '60fab7a8-617c-4962-8391-69221ed761d6', 'BottomAttachment', 'NV-TAC', '', '2025-06-09 22:55:27'),
(55, 9, 8, 1, 'Gamma Plus (3x Holographic)', 'fd9d06a9-5cf8-4325-9149-538d576e5146', 'IronSight', 'NV-TAC', '', '2025-06-09 22:55:27'),
(56, 9, 8, 2, 'Tacit Suppressor2', 'dffa4527-2748-4f52-a7da-e57aae1c293c', 'Barrel', 'ArmaMod', '', '2025-06-09 22:55:27'),
(57, 9, 8, 3, 'FieldLite Flashlight', '60fab7a8-617c-4962-8391-69221ed761d6', 'BottomAttachment', 'NV-TAC', '', '2025-06-09 22:55:27'),
(58, 11, 7, 1, 'Gamma (1x Holographic)', 'e812e76a-4068-4e91-8511-45a26039aa12', 'IronSight', 'NV-TAC', '', '2025-06-10 13:05:28'),
(59, 11, 7, 2, 'Veil Flash Hider1', '04f546d6-651a-47f4-b0ae-64ed2b37557b', 'Barrel', 'ArmaMod', '', '2025-06-10 13:05:28'),
(60, 11, 7, 3, 'FieldLite Flashlight Blue', '30c2a2e9-fba9-40b5-830c-0f9ad43b18ad', 'BottomAttachment', 'NV-TAC', '', '2025-06-10 13:05:28');

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
(128, 'loadout.approve_sets', 'Teçhizat Setlerini Onaylama', 'loadout', 1, '2025-06-07 14:44:03'),
(129, 'scg.hangar.view', 'SCG Hangar Görüntüleme', 'scg', 1, '2025-06-13 18:44:01');

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
(2, 'admin', 'Site Yöneticisi - Tüm yetkilere sahip', '#ffa200', '2025-05-30 11:51:48', '2025-06-13 20:01:44', 1),
(5, 'uye', 'Standart üye temel erişimlere sahip', '#fcfcfc', '2025-05-30 11:51:48', '2025-06-13 11:30:02', 5),
(68, 'scg', 'Star Citizen Global Üyesi', '#9a3c3c', '2025-06-13 11:29:54', '2025-06-13 18:35:36', 6);

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
(2, 1, '2025-06-13 18:45:57'),
(2, 2, '2025-06-13 18:45:57'),
(2, 3, '2025-06-13 18:45:57'),
(2, 4, '2025-06-13 18:45:57'),
(2, 5, '2025-06-13 18:45:57'),
(2, 6, '2025-06-13 18:45:57'),
(2, 7, '2025-06-13 18:45:57'),
(2, 8, '2025-06-13 18:45:57'),
(2, 9, '2025-06-13 18:45:57'),
(2, 10, '2025-06-13 18:45:57'),
(2, 11, '2025-06-13 18:45:57'),
(2, 12, '2025-06-13 18:45:57'),
(2, 13, '2025-06-13 18:45:57'),
(2, 14, '2025-06-13 18:45:57'),
(2, 15, '2025-06-13 18:45:57'),
(2, 16, '2025-06-13 18:45:57'),
(2, 17, '2025-06-13 18:45:57'),
(2, 18, '2025-06-13 18:45:57'),
(2, 19, '2025-06-13 18:45:57'),
(2, 20, '2025-06-13 18:45:57'),
(2, 21, '2025-06-13 18:45:57'),
(2, 22, '2025-06-13 18:45:57'),
(2, 23, '2025-06-13 18:45:57'),
(2, 24, '2025-06-13 18:45:57'),
(2, 25, '2025-06-13 18:45:57'),
(2, 26, '2025-06-13 18:45:57'),
(2, 27, '2025-06-13 18:45:57'),
(2, 28, '2025-06-13 18:45:57'),
(2, 29, '2025-06-13 18:45:57'),
(2, 55, '2025-06-13 18:45:57'),
(2, 56, '2025-06-13 18:45:57'),
(2, 57, '2025-06-13 18:45:57'),
(2, 58, '2025-06-13 18:45:57'),
(2, 59, '2025-06-13 18:45:57'),
(2, 60, '2025-06-13 18:45:57'),
(2, 63, '2025-06-13 18:45:57'),
(2, 64, '2025-06-13 18:45:57'),
(2, 65, '2025-06-13 18:45:57'),
(2, 76, '2025-06-13 18:45:57'),
(2, 77, '2025-06-13 18:45:57'),
(2, 78, '2025-06-13 18:45:57'),
(2, 79, '2025-06-13 18:45:57'),
(2, 80, '2025-06-13 18:45:57'),
(2, 81, '2025-06-13 18:45:57'),
(2, 82, '2025-06-13 18:45:57'),
(2, 83, '2025-06-13 18:45:57'),
(2, 84, '2025-06-13 18:45:57'),
(2, 85, '2025-06-13 18:45:57'),
(2, 86, '2025-06-13 18:45:57'),
(2, 87, '2025-06-13 18:45:57'),
(2, 88, '2025-06-13 18:45:57'),
(2, 89, '2025-06-13 18:45:57'),
(2, 90, '2025-06-13 18:45:57'),
(2, 91, '2025-06-13 18:45:57'),
(2, 92, '2025-06-13 18:45:57'),
(2, 93, '2025-06-13 18:45:57'),
(2, 94, '2025-06-13 18:45:57'),
(2, 95, '2025-06-13 18:45:57'),
(2, 96, '2025-06-13 18:45:57'),
(2, 97, '2025-06-13 18:45:57'),
(2, 98, '2025-06-13 18:45:57'),
(2, 99, '2025-06-13 18:45:57'),
(2, 100, '2025-06-13 18:45:57'),
(2, 101, '2025-06-13 18:45:57'),
(2, 102, '2025-06-13 18:45:57'),
(2, 103, '2025-06-13 18:45:57'),
(2, 104, '2025-06-13 18:45:57'),
(2, 105, '2025-06-13 18:45:57'),
(2, 106, '2025-06-13 18:45:57'),
(2, 107, '2025-06-13 18:45:57'),
(2, 108, '2025-06-13 18:45:57'),
(2, 109, '2025-06-13 18:45:57'),
(2, 110, '2025-06-13 18:45:57'),
(2, 111, '2025-06-13 18:45:57'),
(2, 112, '2025-06-13 18:45:57'),
(2, 113, '2025-06-13 18:45:57'),
(2, 114, '2025-06-13 18:45:57'),
(2, 115, '2025-06-13 18:45:57'),
(2, 116, '2025-06-13 18:45:57'),
(2, 117, '2025-06-13 18:45:57'),
(2, 118, '2025-06-13 18:45:57'),
(2, 119, '2025-06-13 18:45:57'),
(2, 121, '2025-06-13 18:45:57'),
(2, 122, '2025-06-13 18:45:57'),
(2, 123, '2025-06-13 18:45:57'),
(2, 124, '2025-06-13 18:45:57'),
(2, 125, '2025-06-13 18:45:57'),
(2, 126, '2025-06-13 18:45:57'),
(2, 127, '2025-06-13 18:45:57'),
(2, 128, '2025-06-13 18:45:57'),
(2, 129, '2025-06-13 18:45:57'),
(5, 12, '2025-06-13 20:01:58'),
(5, 13, '2025-06-13 20:01:58'),
(5, 16, '2025-06-13 20:01:58'),
(5, 17, '2025-06-13 20:01:58'),
(5, 19, '2025-06-13 20:01:58'),
(5, 21, '2025-06-13 20:01:58'),
(5, 23, '2025-06-13 20:01:58'),
(5, 24, '2025-06-13 20:01:58'),
(5, 25, '2025-06-13 20:01:58'),
(5, 26, '2025-06-13 20:01:58'),
(5, 27, '2025-06-13 20:01:58'),
(5, 55, '2025-06-13 20:01:58'),
(5, 56, '2025-06-13 20:01:58'),
(5, 57, '2025-06-13 20:01:58'),
(5, 76, '2025-06-13 20:01:58'),
(5, 77, '2025-06-13 20:01:58'),
(5, 79, '2025-06-13 20:01:58'),
(5, 81, '2025-06-13 20:01:58'),
(5, 82, '2025-06-13 20:01:58'),
(5, 83, '2025-06-13 20:01:58'),
(5, 84, '2025-06-13 20:01:58'),
(5, 85, '2025-06-13 20:01:58'),
(5, 86, '2025-06-13 20:01:58'),
(5, 87, '2025-06-13 20:01:58'),
(5, 88, '2025-06-13 20:01:58'),
(5, 89, '2025-06-13 20:01:58'),
(5, 90, '2025-06-13 20:01:58'),
(5, 91, '2025-06-13 20:01:58'),
(5, 92, '2025-06-13 20:01:58'),
(5, 93, '2025-06-13 20:01:58'),
(5, 94, '2025-06-13 20:01:58'),
(5, 95, '2025-06-13 20:01:58'),
(5, 96, '2025-06-13 20:01:58'),
(5, 97, '2025-06-13 20:01:58'),
(5, 98, '2025-06-13 20:01:58'),
(5, 99, '2025-06-13 20:01:58'),
(5, 102, '2025-06-13 20:01:58'),
(5, 103, '2025-06-13 20:01:58'),
(5, 104, '2025-06-13 20:01:58'),
(5, 115, '2025-06-13 20:01:58'),
(5, 117, '2025-06-13 20:01:58'),
(5, 129, '2025-06-13 20:01:58'),
(68, 129, '2025-06-13 18:44:01');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `skill_tags`
--

CREATE TABLE `skill_tags` (
  `id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `skill_tags`
--

INSERT INTO `skill_tags` (`id`, `tag_name`, `created_at`) VALUES
(8, 'Vehicle Operator', '2025-06-09 12:07:45'),
(12, 'Combat', '2025-06-09 19:35:51');

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
(1, 'super_admin_users', '[1]', 'json', 'Süper admin kullanıcı ID listesi', '2025-06-02 14:27:22');

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
(1, 'Doğanay', '$2y$12$ZUDegf.5Yo/MvZE3wWe0z.OEFi7Njl9/w74TUsBmpXmCaM047Xkle', 'doganayurgupluoglu@gmail.com', 'Deneme açıklaması', 'uploads/avatars/avatar_1_1749678135.gif', 'approved', 'Cpt_Bosmang', '_doganay', '2025-05-30 11:01:51', '2025-06-11 21:42:15'),
(4, 'Sirjamesss', '$2y$10$s59QfVjCUWgqKjvcP3tZo.Q1BRgPD8/1sm4/jRZGH7/4WqA/yE4Tm', 'mehmettt68100@gmail.com', NULL, NULL, 'approved', 'Sirjamesss', NULL, '2025-05-23 14:13:02', '2025-05-23 14:13:29'),
(5, 'DreadGG', '$2y$10$A1kgEwzUTw9MmAyWtinMt.ngJYgSHdZpx0ni.1XmK4GPAL8FDDBDq', 'extremejazz22.gg@gmail.com', NULL, NULL, 'approved', 'DreadGG', NULL, '2025-05-23 15:24:09', '2025-05-23 15:25:10'),
(6, 'Tunyukuk', '$2y$10$Jpdn7jdV6aNGZORJiW.IbenaD5jcxbL977BZitwR4aqkrPpwazpDK', 'saitzafergunes@gmail.com', NULL, NULL, 'approved', 'Tunyukuk', NULL, '2025-05-23 15:38:08', '2025-05-23 15:39:13'),
(7, 'hanmirgen', '$2y$10$oCA.KJqnscKgd508mdWs2.uXl93JJuA/RZ7Tvtj1lgpf.eNUhqsIG', 'rtuk-rtuk@hotmail.com', NULL, 'uploads/avatars/avatar_user7_1748569110.gif', 'approved', 'hanmirgen', '! HanMirgen', '2025-05-23 15:39:04', '2025-05-30 01:39:31'),
(8, 'alpysl', '$2y$10$AGWs3rvnenvyfx5LqyMRBOiFXfgbMQf053LT8yhQpTZNXZ2fkZV7C', 'alperensyesil@gmail.com', NULL, NULL, 'approved', 'AlperenS', 'alpysl', '2025-05-23 15:59:38', '2025-05-23 16:09:50'),
(9, 'Boomer.1312', '$2y$10$U.T4eGbkg7BdGECpuTyrDOhI/IhIjSpvIYFo3UuxljfFrCo00xyyS', 'scubadiver.burak@hotmail.com', NULL, NULL, 'approved', 'Boomer.1312', NULL, '2025-05-23 16:23:17', '2025-05-23 16:23:43'),
(10, 'test', '$2y$10$hzJEZusBxMXSkTUsjmKeYeIAgGFPGBVgIKZI8/E1d6aSNnMenPBae', 'test@test.com', NULL, NULL, 'approved', 'tester', NULL, '2025-05-23 17:07:46', '2025-06-11 21:17:48'),
(11, 'hatbak16', '$2y$10$wHFAgVNEick0a0KTA8rhIuLYoJx.4OogweerDw7LhF53JktKVJHfW', 'hatbak16@windowslive.com', NULL, NULL, 'approved', 'Hatbak', NULL, '2025-05-23 18:21:47', '2025-05-23 20:32:55'),
(12, '61DeJaVu61', '$2y$10$GnXIDoetED4YFu6f84TzLOBx6uobo5aRRKGO5/52UM.nJwilKhPM.', 'starctz616161@gmail.com', NULL, NULL, 'approved', '61DeJaVu61', NULL, '2025-05-23 21:27:49', '2025-05-23 21:33:31'),
(13, 'OzelTech', '$2y$10$l9MFdQ7eXBu3yc1AyGUJY.FX2l65uPKU7K/2Ys.t9mFKdJJU5Fu2a', 'ounyayar@gmail.com', NULL, 'uploads/avatars/avatar_user13_1748037646.jpg', 'approved', 'Ozeltech', NULL, '2025-05-23 21:57:29', '2025-05-23 22:00:46'),
(15, 'NecroP', '$2y$10$E.MVpPHT7ILSIwES6Ltkz.FSGfktNuFYkNkDDaLNC.mtbF9bsGBPu', 'Necrop@gmail.com', NULL, NULL, 'approved', 'NecroP', NULL, '2025-06-04 09:49:37', '2025-06-04 10:14:32'),
(17, 'Alprstayn', '$2y$10$siMszdTi2KLhnJNebjIWzeESC0xgZxDIUIwQvtQ1FoIacdNvs0FaG', 'alperozturkerz25oyun@gmail.com', NULL, NULL, 'approved', 'ALPRSTAYN', NULL, '2025-06-04 10:35:57', '2025-06-13 12:30:45'),
(18, 'Aietes', '$2y$10$K0QMvMhSeyLpIvsAVgMCC.We1b6Zj7KtScEmOADuEoKLe30SB4ezu', 'obi08@hotmail.com', 'SCG', 'uploads/avatars/avatar_user18_1749037638.jpg', 'approved', 'Aietes', 'aietes', '2025-06-04 10:37:38', '2025-06-04 11:47:18'),
(19, 'delidolo', '$2y$10$gbz82GL3LR5mSPoNnwlMMOlE9xNMbfSNDA8h5DgFlQhtMnE99O0VS', 'theroling26@gmail.com', NULL, NULL, 'approved', 'AZ4Z1L', NULL, '2025-06-05 09:20:11', '2025-06-13 11:56:56');

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
  `has_lti` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Lifetime Insurance var mı?',
  `user_notes` text DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `user_hangar`
--

INSERT INTO `user_hangar` (`id`, `user_id`, `ship_api_id`, `ship_name`, `ship_manufacturer`, `ship_focus`, `ship_size`, `ship_image_url`, `quantity`, `has_lti`, `user_notes`, `added_at`) VALUES
(1, 1, '168', 'Mercury', 'Crusader Industries', 'Medium Cargo / Medium Data', 'medium', 'https://media.robertsspaceindustries.com/219rro1mjtov6/source.jpg', 1, 0, NULL, '2025-05-23 20:10:40'),
(2, 1, '281', 'CSV-SM', 'Argo Astronautics', 'Freight', 'vehicle', 'https://media.robertsspaceindustries.com/yrzxl9cs9zhjy/source.jpg', 1, 0, NULL, '2025-05-23 20:10:40'),
(3, 1, '287', 'Guardian QI', 'Mirai', 'Heavy Fighter', 'small', 'https://media.robertsspaceindustries.com/efsw63dokhn35/source.jpg', 1, 1, NULL, '2025-05-23 20:10:40'),
(4, 13, '116', 'Polaris', 'Roberts Space Industries', 'Corvette', 'capital', 'https://media.robertsspaceindustries.com/oe0wikh6g3ltm/source.jpg', 1, 1, NULL, '2025-05-23 22:04:18'),
(5, 13, '8', '315p', 'Origin Jumpworks', 'Pathfinder', 'small', 'https://media.robertsspaceindustries.com/tclw2w16unsyq/source.jpg', 1, 1, NULL, '2025-05-23 22:05:47'),
(6, 13, '36', 'Merchantman', 'Banu', 'Heavy Freight', 'large', 'https://media.robertsspaceindustries.com/gmtme5pca7eis/source.jpg', 1, 1, 'UCUZA ALDIM', '2025-05-23 22:05:47'),
(7, 13, '103', 'Crucible', 'Anvil Aerospace', 'Heavy Repair', 'large', 'https://media.robertsspaceindustries.com/q81gvelwf2usv/source.jpg', 1, 1, 'ÇIKSIN ARTIK.', '2025-05-23 22:05:47'),
(8, 13, '141', '600i Explorer', 'Origin Jumpworks', 'Expedition', 'large', 'https://media.robertsspaceindustries.com/nsl0zel8gmfxl/source.jpg', 1, 1, NULL, '2025-05-23 22:05:47'),
(10, 13, '296', 'ATLS GEO', 'Argo Astronautics', 'Industrial', 'vehicle', 'https://media.robertsspaceindustries.com/rbkutfuffvdy7/source.jpg', 1, 1, 'AŞK VAR BU ALETTE', '2025-05-23 22:05:47'),
(11, 7, '37', 'F7A Hornet Mk II', 'Anvil Aerospace', 'Medium Fighter', 'small', 'https://media.robertsspaceindustries.com/fbn41urx9yszc/source.jpg', 1, 0, NULL, '2025-05-23 22:20:42'),
(12, 7, '168', 'Mercury', 'Crusader Industries', 'Medium Cargo / Medium Data', 'medium', 'https://media.robertsspaceindustries.com/219rro1mjtov6/source.jpg', 1, 0, NULL, '2025-05-23 22:20:42'),
(13, 7, '217', 'Perseus', 'Roberts Space Industries', 'Gunboat', 'large', 'https://media.robertsspaceindustries.com/rofj7fmgtekyg/source.jpg', 1, 0, NULL, '2025-05-23 22:20:42'),
(14, 7, '279', 'Starlancer TAC', 'MISC', 'Patrol', 'medium', 'https://media.robertsspaceindustries.com/emx6dhzo9kbox/source.jpg', 1, 0, NULL, '2025-05-23 22:20:42'),
(15, 7, '282', 'Paladin', 'Anvil Aerospace', 'Gunship', 'medium', 'https://media.robertsspaceindustries.com/7dbgpx4iv3dut/source.jpg', 1, 0, NULL, '2025-05-23 22:20:42'),
(16, 7, '298', 'Guardian MX', 'Mirai', 'Heavy Fighter', 'small', 'https://media.robertsspaceindustries.com/e92jsru2uvimx/source.jpg', 1, 0, NULL, '2025-05-23 22:20:42'),
(17, 7, '181', 'Ranger TR', 'Tumbril', 'Combat', 'vehicle', 'https://media.robertsspaceindustries.com/eehhr9ql9y04w/source.jpg', 1, 0, NULL, '2025-05-23 22:21:21'),
(18, 7, '210', 'G12a', 'Origin Jumpworks', 'Military', 'vehicle', 'https://media.robertsspaceindustries.com/2btmuamt8zv4g/source.jpg', 1, 0, NULL, '2025-05-23 22:21:21'),
(19, 7, '271', 'Pulse', 'Mirai', 'Combat', 'vehicle', 'https://media.robertsspaceindustries.com/slp0u937q6i57/source.jpg', 1, 0, NULL, '2025-05-23 22:21:21'),
(20, 13, '37', 'F7A Hornet Mk II', 'Anvil Aerospace', 'Medium Fighter', 'small', 'https://media.robertsspaceindustries.com/fbn41urx9yszc/source.jpg', 1, 1, NULL, '2025-05-23 22:30:07'),
(21, 13, '169', 'Valkyrie', 'Anvil Aerospace', 'Dropship', 'small', 'https://media.robertsspaceindustries.com/yjh17ca5zprfr/source.jpg', 1, 1, NULL, '2025-05-23 22:30:21'),
(22, 13, '256', 'Zeus Mk II MR', 'Roberts Space Industries', 'Interdiction', 'medium', 'https://media.robertsspaceindustries.com/pj51owg973q4h/source.jpg', 1, 1, NULL, '2025-05-23 22:30:39'),
(24, 13, '232', 'Legionnaire', 'Anvil Aerospace', 'Boarding', 'small', 'https://media.robertsspaceindustries.com/qxgdodjdhuvsr/source.jpeg', 1, 1, NULL, '2025-05-23 22:30:47'),
(25, 13, '237', 'A1 Spirit ', 'Crusader Industries', 'Bomber', 'medium', 'https://media.robertsspaceindustries.com/nsqe4f3nl1mqn/source.jpg', 1, 1, NULL, '2025-05-24 00:20:23'),
(26, 13, '297', 'MTC', 'Greycat Industrial', 'Combat', 'vehicle', 'https://media.robertsspaceindustries.com/mbsnp3745enyi/source.jpg', 1, 1, NULL, '2025-05-24 00:20:37'),
(27, 13, '249', 'Storm', 'Tumbril', 'Combat Support', 'vehicle', 'https://media.robertsspaceindustries.com/kwrokktl2sfx0/source.jpg', 1, 1, NULL, '2025-05-24 00:20:52'),
(28, 13, '264', 'Storm AA', 'Tumbril', 'Combat Support', 'vehicle', 'https://media.robertsspaceindustries.com/x42epibkm0264/source.jpg', 1, 1, NULL, '2025-05-24 00:20:52'),
(29, 6, '37', 'F7A Hornet Mk II', 'Anvil Aerospace', 'Medium Fighter', 'small', 'https://media.robertsspaceindustries.com/fbn41urx9yszc/source.jpg', 1, 0, NULL, '2025-05-24 17:08:30'),
(30, 6, '45', 'Constellation Andromeda', 'Roberts Space Industries', 'Medium Freight / Gun Ship', 'large', 'https://media.robertsspaceindustries.com/x1aflxx72d3xs/source.jpg', 1, 0, NULL, '2025-05-24 17:08:30'),
(31, 6, '165', 'Vulture', 'Drake Interplanetary', 'Light Salvage', 'small', 'https://media.robertsspaceindustries.com/ryxb5u7q09x06/source.jpg', 1, 0, NULL, '2025-05-24 17:08:30'),
(32, 6, '173', 'Arrow', 'Anvil Aerospace', 'Light Fighter', 'small', 'https://media.robertsspaceindustries.com/je860sn8tg87z/source.jpg', 1, 0, NULL, '2025-05-24 17:08:30'),
(33, 6, '295', 'Golem', 'Drake Interplanetary', 'Mining', 'small', 'https://media.robertsspaceindustries.com/yzx7t45a965dk/source.jpg', 1, 0, NULL, '2025-05-24 17:08:30'),
(34, 6, '282', 'Paladin', 'Anvil Aerospace', 'Gunship', 'medium', 'https://media.robertsspaceindustries.com/7dbgpx4iv3dut/source.jpg', 1, 0, NULL, '2025-05-24 17:08:47'),
(35, 9, '37', 'F7A Hornet Mk II', 'Anvil Aerospace', 'Medium Fighter', 'small', 'https://media.robertsspaceindustries.com/fbn41urx9yszc/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(36, 9, '141', '600i Explorer', 'Origin Jumpworks', 'Expedition', 'large', 'https://media.robertsspaceindustries.com/nsl0zel8gmfxl/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(37, 9, '168', 'Mercury', 'Crusader Industries', 'Medium Cargo / Medium Data', 'medium', 'https://media.robertsspaceindustries.com/219rro1mjtov6/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(38, 9, '173', 'Arrow', 'Anvil Aerospace', 'Light Fighter', 'small', 'https://media.robertsspaceindustries.com/je860sn8tg87z/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(39, 9, '217', 'Perseus', 'Roberts Space Industries', 'Gunboat', 'large', 'https://media.robertsspaceindustries.com/rofj7fmgtekyg/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(40, 9, '279', 'Starlancer TAC', 'MISC', 'Patrol', 'medium', 'https://media.robertsspaceindustries.com/emx6dhzo9kbox/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(41, 9, '298', 'Guardian MX', 'Mirai', 'Heavy Fighter', 'small', 'https://media.robertsspaceindustries.com/e92jsru2uvimx/source.jpg', 1, 0, NULL, '2025-05-25 12:10:12'),
(43, 4, '51', 'Reclaimer', 'Aegis Dynamics', 'Heavy Salvage', 'large', 'https://media.robertsspaceindustries.com/mp4b03l05po17/source.jpg', 1, 0, NULL, '2025-05-30 23:32:00'),
(44, 4, '274', 'Ironclad', 'Drake Interplanetary', 'Armored Freight', 'large', 'https://media.robertsspaceindustries.com/gtz4uouxebp3u/source.jpg', 1, 0, NULL, '2025-05-31 00:03:54'),
(45, 4, '37', 'F7A Hornet Mk II', 'Anvil Aerospace', 'Medium Fighter', 'small', 'https://media.robertsspaceindustries.com/fbn41urx9yszc/source.jpg', 1, 0, NULL, '2025-05-31 00:04:04'),
(48, 18, '36', 'Merchantman', 'Banu', 'Heavy Freight', 'large', 'https://media.robertsspaceindustries.com/gmtme5pca7eis/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(49, 18, '37', 'F7A Hornet Mk II', 'Anvil Aerospace', 'Medium Fighter', 'small', 'https://media.robertsspaceindustries.com/fbn41urx9yszc/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(50, 18, '51', 'Reclaimer', 'Aegis Dynamics', 'Heavy Salvage', 'large', 'https://media.robertsspaceindustries.com/mp4b03l05po17/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(51, 18, '63', 'Javelin', 'Aegis Dynamics', 'Destroyer', 'capital', 'https://media.robertsspaceindustries.com/oc89p5ksizcla/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(52, 18, '71', 'Orion', 'Roberts Space Industries', 'Heavy Mining', 'capital', 'https://media.robertsspaceindustries.com/b3nwvt5ye3zj0/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(53, 18, '88', 'Starfarer', 'MISC', 'Heavy Refuelling', 'large', 'https://media.robertsspaceindustries.com/wcxbs18v57gxv/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(54, 18, '91', 'Genesis', 'Crusader Industries', 'Passenger', 'large', 'https://media.robertsspaceindustries.com/gpdjd9p1jnxj4/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(55, 18, '103', 'Crucible', 'Anvil Aerospace', 'Heavy Repair', 'large', 'https://media.robertsspaceindustries.com/q81gvelwf2usv/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(56, 18, '116', 'Polaris', 'Roberts Space Industries', 'Corvette', 'capital', 'https://media.robertsspaceindustries.com/oe0wikh6g3ltm/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(57, 18, '165', 'Vulture', 'Drake Interplanetary', 'Light Salvage', 'small', 'https://media.robertsspaceindustries.com/ryxb5u7q09x06/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(58, 18, '168', 'Mercury', 'Crusader Industries', 'Medium Cargo / Medium Data', 'medium', 'https://media.robertsspaceindustries.com/219rro1mjtov6/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(59, 18, '261', 'F8C Lightning', 'Anvil Aerospace', 'Heavy Fighter', 'small', 'https://media.robertsspaceindustries.com/j6rvfrkux5nrm/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(60, 18, '266', 'Cutter Rambler', 'Drake Interplanetary', 'Starter / Expedition', 'small', 'https://media.robertsspaceindustries.com/7xwtmjrvqlyee/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(61, 18, '268', 'MPUV Tractor', 'Argo Astronautics', 'Cargo Loader', 'snub', 'https://media.robertsspaceindustries.com/i9guillde6qib/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(62, 18, '274', 'Ironclad', 'Drake Interplanetary', 'Armored Freight', 'large', 'https://media.robertsspaceindustries.com/gtz4uouxebp3u/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(63, 18, '295', 'Golem', 'Drake Interplanetary', 'Mining', 'small', 'https://media.robertsspaceindustries.com/yzx7t45a965dk/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(64, 18, '296', 'ATLS GEO', 'Argo Astronautics', 'Industrial', 'vehicle', 'https://media.robertsspaceindustries.com/rbkutfuffvdy7/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(65, 18, '297', 'MTC', 'Greycat Industrial', 'Combat', 'vehicle', 'https://media.robertsspaceindustries.com/mbsnp3745enyi/source.jpg', 1, 0, NULL, '2025-06-04 11:06:22'),
(67, 1, 'api_216', 'Nomad', 'Consolidated Outland', 'Starter', NULL, 'https://media.robertsspaceindustries.com/inqdpb67v815c/source.jpg', 1, 1, NULL, '2025-06-11 00:23:27'),
(68, 1, 'api_1', 'Aurora ES', 'Roberts Space Industries', 'Starter / Pathfinder', 'Small', 'https://media.robertsspaceindustries.com/e1i4i2ixe6ouo/source.jpg', 1, 0, NULL, '2025-06-11 00:23:38'),
(69, 13, 'api_154', 'Nova', 'Tumbril', 'Combat', 'Vehicle', 'https://media.robertsspaceindustries.com/698j1tw6sqq4t/source.jpg', 1, 1, NULL, '2025-06-11 00:28:25');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_remember_tokens`
--

CREATE TABLE `user_remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
(1, 68),
(6, 68),
(7, 2),
(10, 5),
(13, 2),
(13, 68),
(18, 68),
(19, 2),
(19, 5);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_skill_tags`
--

CREATE TABLE `user_skill_tags` (
  `user_id` int(11) NOT NULL,
  `skill_tag_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `user_skill_tags`
--

INSERT INTO `user_skill_tags` (`user_id`, `skill_tag_id`, `added_at`) VALUES
(1, 8, '2025-06-09 19:34:03'),
(1, 12, '2025-06-13 11:26:58'),
(19, 12, '2025-06-13 12:02:03');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `weapon_attachment_slots`
--

CREATE TABLE `weapon_attachment_slots` (
  `id` int(11) NOT NULL,
  `slot_name` varchar(100) NOT NULL COMMENT 'Slot adı (örn: Nişangah, Namlu Eklentisi)',
  `slot_type` varchar(100) NOT NULL COMMENT 'API tip (IronSight, Barrel, BottomAttachment)',
  `parent_weapon_slots` varchar(50) NOT NULL COMMENT 'Hangi ana silah slotlarında görünecek (7,8,9)',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Gösterim sırası',
  `icon_class` varchar(50) DEFAULT NULL COMMENT 'Font Awesome icon class',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Aktif mi?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Silah attachment slotları tanımları';

--
-- Tablo döküm verisi `weapon_attachment_slots`
--

INSERT INTO `weapon_attachment_slots` (`id`, `slot_name`, `slot_type`, `parent_weapon_slots`, `display_order`, `icon_class`, `is_active`, `created_at`) VALUES
(1, 'Nişangah/Optik', 'IronSight', '7,8,9', 1, 'fas fa-crosshairs', 1, '2025-06-06 18:24:13'),
(2, 'Namlu Eklentisi', 'Barrel', '7,8,9', 2, 'fas fa-long-arrow-alt-right', 1, '2025-06-06 18:24:13'),
(3, 'Alt Bağlantı', 'BottomAttachment', '7,8', 3, 'fas fa-grip-lines', 1, '2025-06-06 18:24:13');

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
  ADD KEY `fk_e_creator` (`created_by_user_id`),
  ADD KEY `idx_date` (`event_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_registration_deadline` (`registration_deadline`),
  ADD KEY `idx_auto_approve` (`auto_approve`);

--
-- Tablo için indeksler `event_participations`
--
ALTER TABLE `event_participations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_user` (`event_id`,`user_id`),
  ADD KEY `fk_ep_event` (`event_id`),
  ADD KEY `fk_ep_user` (`user_id`),
  ADD KEY `fk_ep_slot` (`role_slot_id`),
  ADD KEY `idx_status` (`participation_status`),
  ADD KEY `idx_event_participations_status` (`event_id`,`participation_status`),
  ADD KEY `idx_event_participations_user` (`user_id`,`participation_status`);

--
-- Tablo için indeksler `event_roles`
--
ALTER TABLE `event_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_er_loadout` (`suggested_loadout_id`);

--
-- Tablo için indeksler `event_role_requirements`
--
ALTER TABLE `event_role_requirements`
  ADD PRIMARY KEY (`role_id`,`skill_tag_id`),
  ADD KEY `fk_err_role` (`role_id`),
  ADD KEY `fk_err_skill` (`skill_tag_id`);

--
-- Tablo için indeksler `event_role_slots`
--
ALTER TABLE `event_role_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_role` (`event_id`,`role_id`),
  ADD KEY `fk_ers_event` (`event_id`),
  ADD KEY `fk_ers_role` (`role_id`);

--
-- Tablo için indeksler `event_visibility_roles`
--
ALTER TABLE `event_visibility_roles`
  ADD PRIMARY KEY (`event_id`,`role_id`),
  ADD KEY `fk_evr_event` (`event_id`),
  ADD KEY `fk_evr_role` (`role_id`);

--
-- Tablo için indeksler `forum_categories`
--
ALTER TABLE `forum_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug_UNIQUE` (`slug`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_active` (`is_active`);

--
-- Tablo için indeksler `forum_category_visibility_roles`
--
ALTER TABLE `forum_category_visibility_roles`
  ADD PRIMARY KEY (`category_id`,`role_id`),
  ADD KEY `fk_fcvr_category` (`category_id`),
  ADD KEY `fk_fcvr_role` (`role_id`);

--
-- Tablo için indeksler `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fp_topic` (`topic_id`),
  ADD KEY `fk_fp_user` (`user_id`),
  ADD KEY `fk_fp_edited_by` (`edited_by_user_id`),
  ADD KEY `idx_topic_created` (`topic_id`,`created_at`);

--
-- Tablo için indeksler `forum_post_likes`
--
ALTER TABLE `forum_post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_post_user_like` (`post_id`,`user_id`),
  ADD KEY `fk_fpl_post` (`post_id`),
  ADD KEY `fk_fpl_user` (`user_id`);

--
-- Tablo için indeksler `forum_tags`
--
ALTER TABLE `forum_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_UNIQUE` (`name`),
  ADD UNIQUE KEY `slug_UNIQUE` (`slug`),
  ADD KEY `idx_usage_count` (`usage_count`);

--
-- Tablo için indeksler `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug_UNIQUE` (`slug`),
  ADD KEY `fk_ft_category` (`category_id`),
  ADD KEY `fk_ft_user` (`user_id`),
  ADD KEY `fk_ft_last_post_user` (`last_post_user_id`),
  ADD KEY `idx_category_pinned_updated` (`category_id`,`is_pinned`,`updated_at`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_tags` (`tags`);

--
-- Tablo için indeksler `forum_topic_likes`
--
ALTER TABLE `forum_topic_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_topic_user_like` (`topic_id`,`user_id`),
  ADD KEY `fk_ftl_topic` (`topic_id`),
  ADD KEY `fk_ftl_user` (`user_id`);

--
-- Tablo için indeksler `forum_topic_tags`
--
ALTER TABLE `forum_topic_tags`
  ADD PRIMARY KEY (`topic_id`,`tag_id`),
  ADD KEY `fk_ftt_topic` (`topic_id`),
  ADD KEY `fk_ftt_tag` (`tag_id`);

--
-- Tablo için indeksler `forum_topic_visibility_roles`
--
ALTER TABLE `forum_topic_visibility_roles`
  ADD PRIMARY KEY (`topic_id`,`role_id`),
  ADD KEY `fk_ftvr_topic` (`topic_id`),
  ADD KEY `fk_ftvr_role` (`role_id`);

--
-- Tablo için indeksler `gallery_comment_likes`
--
ALTER TABLE `gallery_comment_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_comment_user_like` (`comment_id`,`user_id`) COMMENT 'Bir kullanıcı bir yorumu sadece bir kez beğenebilir',
  ADD KEY `fk_gallery_comment_likes_comment` (`comment_id`),
  ADD KEY `fk_gallery_comment_likes_user` (`user_id`);

--
-- Tablo için indeksler `gallery_photos`
--
ALTER TABLE `gallery_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gallery_photos_users_idx` (`user_id`);

--
-- Tablo için indeksler `gallery_photo_comments`
--
ALTER TABLE `gallery_photo_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gallery_comments_photo` (`photo_id`),
  ADD KEY `fk_gallery_comments_user` (`user_id`),
  ADD KEY `fk_gallery_comments_parent` (`parent_comment_id`),
  ADD KEY `idx_photo_created` (`photo_id`,`created_at`);

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
-- Tablo için indeksler `loadout_weapon_attachments`
--
ALTER TABLE `loadout_weapon_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lwa_loadout_set` (`loadout_set_id`),
  ADD KEY `fk_lwa_parent_slot` (`parent_equipment_slot_id`),
  ADD KEY `fk_lwa_attachment_slot` (`attachment_slot_id`),
  ADD KEY `idx_loadout_parent` (`loadout_set_id`,`parent_equipment_slot_id`),
  ADD KEY `idx_attachment_type_parent` (`attachment_item_type`,`parent_equipment_slot_id`),
  ADD KEY `idx_attachment_uuid` (`attachment_item_uuid`);

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
-- Tablo için indeksler `skill_tags`
--
ALTER TABLE `skill_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name_UNIQUE` (`tag_name`);

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
-- Tablo için indeksler `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Tablo için indeksler `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_users_idx` (`user_id`),
  ADD KEY `fk_user_roles_roles_idx` (`role_id`);

--
-- Tablo için indeksler `user_skill_tags`
--
ALTER TABLE `user_skill_tags`
  ADD PRIMARY KEY (`user_id`,`skill_tag_id`),
  ADD KEY `fk_ust_user` (`user_id`),
  ADD KEY `fk_ust_skill` (`skill_tag_id`);

--
-- Tablo için indeksler `weapon_attachment_slots`
--
ALTER TABLE `weapon_attachment_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slot_name_UNIQUE` (`slot_name`),
  ADD KEY `idx_slot_type` (`slot_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2301;

--
-- Tablo için AUTO_INCREMENT değeri `equipment_slots`
--
ALTER TABLE `equipment_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `event_participations`
--
ALTER TABLE `event_participations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `event_roles`
--
ALTER TABLE `event_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `event_role_slots`
--
ALTER TABLE `event_role_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Tablo için AUTO_INCREMENT değeri `forum_categories`
--
ALTER TABLE `forum_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- Tablo için AUTO_INCREMENT değeri `forum_post_likes`
--
ALTER TABLE `forum_post_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Tablo için AUTO_INCREMENT değeri `forum_tags`
--
ALTER TABLE `forum_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `forum_topics`
--
ALTER TABLE `forum_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Tablo için AUTO_INCREMENT değeri `forum_topic_likes`
--
ALTER TABLE `forum_topic_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_comment_likes`
--
ALTER TABLE `gallery_comment_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photos`
--
ALTER TABLE `gallery_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photo_comments`
--
ALTER TABLE `gallery_photo_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Tablo için AUTO_INCREMENT değeri `gallery_photo_likes`
--
ALTER TABLE `gallery_photo_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- Tablo için AUTO_INCREMENT değeri `guides`
--
ALTER TABLE `guides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `guide_likes`
--
ALTER TABLE `guide_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `loadout_sets`
--
ALTER TABLE `loadout_sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `loadout_set_items`
--
ALTER TABLE `loadout_set_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- Tablo için AUTO_INCREMENT değeri `loadout_weapon_attachments`
--
ALTER TABLE `loadout_weapon_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- Tablo için AUTO_INCREMENT değeri `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- Tablo için AUTO_INCREMENT değeri `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- Tablo için AUTO_INCREMENT değeri `skill_tags`
--
ALTER TABLE `skill_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Tablo için AUTO_INCREMENT değeri `user_hangar`
--
ALTER TABLE `user_hangar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- Tablo için AUTO_INCREMENT değeri `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `weapon_attachment_slots`
--
ALTER TABLE `weapon_attachment_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_e_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `event_participations`
--
ALTER TABLE `event_participations`
  ADD CONSTRAINT `fk_ep_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ep_slot` FOREIGN KEY (`role_slot_id`) REFERENCES `event_role_slots` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `event_roles`
--
ALTER TABLE `event_roles`
  ADD CONSTRAINT `fk_er_loadout` FOREIGN KEY (`suggested_loadout_id`) REFERENCES `loadout_sets` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `event_role_requirements`
--
ALTER TABLE `event_role_requirements`
  ADD CONSTRAINT `fk_err_role` FOREIGN KEY (`role_id`) REFERENCES `event_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_err_skill` FOREIGN KEY (`skill_tag_id`) REFERENCES `skill_tags` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `event_role_slots`
--
ALTER TABLE `event_role_slots`
  ADD CONSTRAINT `fk_ers_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ers_role` FOREIGN KEY (`role_id`) REFERENCES `event_roles` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `event_visibility_roles`
--
ALTER TABLE `event_visibility_roles`
  ADD CONSTRAINT `fk_evr_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `forum_category_visibility_roles`
--
ALTER TABLE `forum_category_visibility_roles`
  ADD CONSTRAINT `fk_fcvr_category` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fcvr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `fk_fp_edited_by` FOREIGN KEY (`edited_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fp_topic` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `forum_post_likes`
--
ALTER TABLE `forum_post_likes`
  ADD CONSTRAINT `fk_fpl_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fpl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD CONSTRAINT `fk_ft_category` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ft_last_post_user` FOREIGN KEY (`last_post_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ft_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `forum_topic_likes`
--
ALTER TABLE `forum_topic_likes`
  ADD CONSTRAINT `fk_ftl_topic` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ftl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `forum_topic_tags`
--
ALTER TABLE `forum_topic_tags`
  ADD CONSTRAINT `fk_ftt_tag` FOREIGN KEY (`tag_id`) REFERENCES `forum_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ftt_topic` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `forum_topic_visibility_roles`
--
ALTER TABLE `forum_topic_visibility_roles`
  ADD CONSTRAINT `fk_ftvr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ftvr_topic` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `gallery_comment_likes`
--
ALTER TABLE `gallery_comment_likes`
  ADD CONSTRAINT `fk_gallery_comment_likes_comment` FOREIGN KEY (`comment_id`) REFERENCES `gallery_photo_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gallery_comment_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `gallery_photos`
--
ALTER TABLE `gallery_photos`
  ADD CONSTRAINT `fk_gallery_photos_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `gallery_photo_comments`
--
ALTER TABLE `gallery_photo_comments`
  ADD CONSTRAINT `fk_gallery_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `gallery_photo_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gallery_comments_photo` FOREIGN KEY (`photo_id`) REFERENCES `gallery_photos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gallery_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Tablo kısıtlamaları `loadout_weapon_attachments`
--
ALTER TABLE `loadout_weapon_attachments`
  ADD CONSTRAINT `fk_lwa_attachment_slot` FOREIGN KEY (`attachment_slot_id`) REFERENCES `weapon_attachment_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lwa_loadout_set` FOREIGN KEY (`loadout_set_id`) REFERENCES `loadout_sets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lwa_parent_slot` FOREIGN KEY (`parent_equipment_slot_id`) REFERENCES `equipment_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Tablo kısıtlamaları `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  ADD CONSTRAINT `user_remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_user_roles_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Tablo kısıtlamaları `user_skill_tags`
--
ALTER TABLE `user_skill_tags`
  ADD CONSTRAINT `fk_ust_skill` FOREIGN KEY (`skill_tag_id`) REFERENCES `skill_tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ust_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Olaylar
--
CREATE DEFINER=`u426745395_itdatabase`@`localhost` EVENT `cleanup_remember_tokens` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-13 15:58:08' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
  DELETE FROM user_remember_tokens WHERE expires_at < NOW();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
