-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2026 at 11:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `callcenter9141`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(150) DEFAULT NULL,
  `role` varchar(64) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `summary` varchar(500) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `role`, `action`, `entity_type`, `entity_id`, `summary`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 06:56:55'),
(2, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 08:28:08'),
(3, 1, 'System Administrator', 'administrator', 'help_reply', 'citizen_help', 1, 'Replied to citizen help #1', 'naaa', '::1', '2026-09-02 08:38:46'),
(4, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 08:39:12'),
(5, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 08:39:22'),
(6, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 08:54:49'),
(7, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 08:54:55'),
(8, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 08:55:02'),
(9, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 08:55:31'),
(10, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 09:02:57'),
(11, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 09:03:06'),
(12, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 09:04:22'),
(13, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 09:04:31'),
(14, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 09:05:40'),
(15, 5, 'Camera Operator', 'camera_operator', 'login', 'user', 5, 'Camera Operator logged in', NULL, '::1', '2026-09-02 09:05:53'),
(16, 5, 'Camera Operator', 'camera_operator', 'logout', 'user', 5, 'Camera Operator logged out', NULL, '::1', '2026-09-02 09:06:24'),
(17, 5, 'Camera Operator', 'camera_operator', 'login', 'user', 5, 'Camera Operator logged in', NULL, '::1', '2026-09-02 09:09:53'),
(18, 5, 'Camera Operator', 'camera_operator', 'logout', 'user', 5, 'Camera Operator logged out', NULL, '::1', '2026-09-02 09:10:03'),
(19, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 09:10:07'),
(20, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 09:10:12'),
(21, 3, 'Supervisor', 'supervisor', 'login', 'user', 3, 'Supervisor logged in', NULL, '::1', '2026-09-02 09:10:18'),
(22, 3, 'Supervisor', 'supervisor', 'login', 'user', 3, 'Supervisor logged in', NULL, '::1', '2026-09-02 19:21:17'),
(23, 3, 'Supervisor', 'supervisor', 'logout', 'user', 3, 'Supervisor logged out', NULL, '::1', '2026-09-02 19:55:08'),
(24, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 19:55:11'),
(25, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 19:58:22'),
(26, 3, 'Supervisor', 'supervisor', 'login', 'user', 3, 'Supervisor logged in', NULL, '::1', '2026-09-02 19:58:25'),
(27, 3, 'Supervisor', 'supervisor', 'logout', 'user', 3, 'Supervisor logged out', NULL, '::1', '2026-09-02 19:58:30'),
(28, 4, 'Police', 'department_officer', 'login', 'user', 4, 'Police logged in', NULL, '::1', '2026-09-02 19:59:01'),
(29, 4, 'Police', 'department_officer', 'logout', 'user', 4, 'Police logged out', NULL, '::1', '2026-09-02 20:04:46'),
(30, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 20:04:56'),
(31, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 20:16:29'),
(32, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 20:16:36'),
(33, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 20:18:50'),
(34, 4, 'Police', 'department_officer', 'login', 'user', 4, 'Police logged in', NULL, '::1', '2026-09-02 20:18:54'),
(35, 4, 'Police', 'department_officer', 'logout', 'user', 4, 'Police logged out', NULL, '::1', '2026-09-02 20:24:01'),
(36, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 20:24:05'),
(37, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 20:24:49'),
(38, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 20:25:36'),
(39, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 20:26:05'),
(40, 7, 'City Services', 'department_officer', 'login', 'user', 7, 'City Services logged in', NULL, '::1', '2026-09-02 20:26:26'),
(41, 7, 'City Services', 'department_officer', 'logout', 'user', 7, 'City Services logged out', NULL, '::1', '2026-09-02 20:29:48'),
(42, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 20:41:55'),
(43, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 20:47:14'),
(44, 8, 'Fire & Emergency', 'department_officer', 'login', 'user', 8, 'Fire & Emergency logged in', NULL, '::1', '2026-09-02 20:47:24'),
(45, 8, 'Fire & Emergency', 'department_officer', 'logout', 'user', 8, 'Fire & Emergency logged out', NULL, '::1', '2026-09-02 20:48:53'),
(46, 5, 'Camera Operator', 'camera_operator', 'login', 'user', 5, 'Camera Operator logged in', NULL, '::1', '2026-09-02 20:48:57'),
(47, 5, 'Camera Operator', 'camera_operator', 'logout', 'user', 5, 'Camera Operator logged out', NULL, '::1', '2026-09-02 20:49:00'),
(48, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 20:49:03'),
(49, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 20:50:27'),
(50, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 20:50:49'),
(51, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 20:56:15'),
(52, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 20:56:27'),
(53, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 20:57:45'),
(54, 9, 'Traffic', 'department_officer', 'login', 'user', 9, 'Traffic logged in', NULL, '::1', '2026-09-02 20:57:51'),
(55, 9, 'Traffic', 'department_officer', 'logout', 'user', 9, 'Traffic logged out', NULL, '::1', '2026-09-02 20:59:23'),
(56, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-02 20:59:32'),
(57, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-02 21:02:52'),
(58, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-02 21:02:56'),
(59, 2, 'Call Operator', 'operator', 'help_reply', 'citizen_help', 2, 'Replied to citizen help #2', 'fdvfd', '::1', '2026-09-02 21:03:15'),
(60, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-02 21:03:19'),
(61, 3, 'Supervisor', 'supervisor', 'login', 'user', 3, 'Supervisor logged in', NULL, '::1', '2026-09-02 21:03:23'),
(62, 3, 'Supervisor', 'supervisor', 'login', 'user', 3, 'Supervisor logged in', NULL, '::1', '2026-09-03 08:46:50'),
(63, 3, 'Supervisor', 'supervisor', 'logout', 'user', 3, 'Supervisor logged out', NULL, '::1', '2026-09-03 08:47:37'),
(64, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-03 08:47:44'),
(65, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-03 09:15:17'),
(66, 1, 'System Administrator', 'administrator', 'login', 'user', 1, 'System Administrator logged in', NULL, '::1', '2026-09-03 09:15:38'),
(67, 1, 'System Administrator', 'administrator', 'logout', 'user', 1, 'System Administrator logged out', NULL, '::1', '2026-09-03 09:17:50'),
(68, 2, 'Call Operator', 'operator', 'login', 'user', 2, 'Call Operator logged in', NULL, '::1', '2026-09-03 09:17:58'),
(69, 2, 'Call Operator', 'operator', 'logout', 'user', 2, 'Call Operator logged out', NULL, '::1', '2026-09-03 09:18:12'),
(70, 7, 'City Services', 'department_officer', 'login', 'user', 7, 'City Services logged in', NULL, '::1', '2026-09-03 09:18:18'),
(71, 7, 'City Services', 'department_officer', 'logout', 'user', 7, 'City Services logged out', NULL, '::1', '2026-09-03 09:19:16'),
(72, 8, 'Fire & Emergency', 'department_officer', 'login', 'user', 8, 'Fire & Emergency logged in', NULL, '::1', '2026-09-03 09:19:36'),
(73, 8, 'Fire & Emergency', 'department_officer', 'logout', 'user', 8, 'Fire & Emergency logged out', NULL, '::1', '2026-09-03 09:19:41'),
(74, 4, 'Police', 'department_officer', 'login', 'user', 4, 'Police logged in', NULL, '::1', '2026-09-03 09:19:46'),
(75, 4, 'Police', 'department_officer', 'logout', 'user', 4, 'Police logged out', NULL, '::1', '2026-09-03 09:20:04'),
(76, 3, 'Supervisor', 'supervisor', 'login', 'user', 3, 'Supervisor logged in', NULL, '::1', '2026-09-03 09:20:09'),
(77, 3, 'Supervisor', 'supervisor', 'logout', 'user', 3, 'Supervisor logged out', NULL, '::1', '2026-09-03 09:20:23'),
(78, 5, 'Camera Operator', 'camera_operator', 'login', 'user', 5, 'Camera Operator logged in', NULL, '::1', '2026-09-03 09:20:35');

-- --------------------------------------------------------

--
-- Table structure for table `ai_detections`
--

CREATE TABLE `ai_detections` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `attachment_id` int(11) DEFAULT NULL,
  `model` varchar(64) NOT NULL DEFAULT 'adama-local-v1',
  `summary` text DEFAULT NULL,
  `detections_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detections_json`)),
  `frames_analyzed` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cameras`
--

CREATE TABLE `cameras` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `stream_url` text DEFAULT NULL,
  `stream_type` enum('hls','http','rtsp','mjpeg','webrtc') NOT NULL DEFAULT 'hls',
  `status` varchar(32) NOT NULL DEFAULT 'online',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `camera_clips`
--

CREATE TABLE `camera_clips` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `duration_sec` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `slug` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`, `slug`) VALUES
(1, 'Al-seerummaa / Illegal', '⚖️', 'illegal'),
(2, 'Rakkoo Nageenyaa / Security', '🛡️', 'security'),
(3, 'Rakkoo Tajaajila / Service', '🛠️', 'service'),
(4, 'Balaa Tasaa / Emergency', '🚨', 'emergency');

-- --------------------------------------------------------

--
-- Table structure for table `citizen_feedback`
--

CREATE TABLE `citizen_feedback` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(32) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `citizen_feedback`
--

INSERT INTO `citizen_feedback` (`id`, `tracking_code`, `name`, `phone`, `rating`, `message`, `created_at`) VALUES
(1, '9141-842137', NULL, '0904190352', 5, 'galatooma', '2026-09-02 21:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `citizen_help`
--

CREATE TABLE `citizen_help` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(32) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','seen','answered') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reply_message` text DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL,
  `replied_name` varchar(150) DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `citizen_help`
--

INSERT INTO `citizen_help` (`id`, `tracking_code`, `name`, `phone`, `message`, `status`, `created_at`, `reply_message`, `replied_by`, `replied_name`, `replied_at`) VALUES
(1, NULL, NULL, '0904190352', 'hhhioj', 'answered', '2026-09-02 08:38:17', 'naaa', 1, 'System Administrator', '2026-09-02 08:38:46'),
(2, NULL, 'Hasan', '0904190352', 'support', 'answered', '2026-09-02 21:01:35', 'fdvfd', 2, 'Call Operator', '2026-09-02 21:03:15'),
(3, NULL, NULL, '0904190352', 'ddzcdz', 'new', '2026-09-03 08:55:28', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_phone` varchar(32) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `contact_phone`, `contact_email`, `created_at`) VALUES
(1, 'Police / Poolisii', '0911000001', 'police@adama.gov.et', '2026-09-02 08:26:19'),
(2, 'Traffic / Trafikaa', '0911000002', 'traffic@adama.gov.et', '2026-09-02 08:26:19'),
(3, 'Fire & Emergency', '0911000003', 'fire@adama.gov.et', '2026-09-02 08:26:19'),
(4, 'City Services / Tajaajila', '0911000004', 'services@adama.gov.et', '2026-09-02 08:26:19'),
(5, 'Camera Control Room', '0904190352', 'camera@adama.gov.et', '2026-09-02 09:02:37');

-- --------------------------------------------------------

--
-- Table structure for table `direct_messages`
--

CREATE TABLE `direct_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `direct_messages`
--

INSERT INTO `direct_messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 8, 'sfdvegrdfr', 0, '2026-09-02 21:02:47');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(32) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `caller_name` varchar(150) DEFAULT NULL,
  `caller_phone` varchar(32) DEFAULT NULL,
  `gender` enum('male','female','unspecified') DEFAULT 'unspecified',
  `address` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('new','assigned','ongoing','solved','unsolved') NOT NULL DEFAULT 'new',
  `assigned_department_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `source` varchar(64) DEFAULT 'web',
  `channel` varchar(32) DEFAULT 'web',
  `response_time_minutes` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `stale_alert_sent` tinyint(1) NOT NULL DEFAULT 0,
  `satisfaction_rating` tinyint(4) DEFAULT NULL,
  `satisfaction_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `tracking_code`, `category_id`, `caller_name`, `caller_phone`, `gender`, `address`, `location`, `latitude`, `longitude`, `description`, `priority`, `status`, `assigned_department_id`, `operator_id`, `source`, `channel`, `response_time_minutes`, `resolved_at`, `stale_alert_sent`, `satisfaction_rating`, `satisfaction_comment`, `created_at`, `updated_at`) VALUES
(1, '9141-D0B939', 1, NULL, '0904190352', 'male', 'Bokku', '08', 8.5520000, 39.2600000, 'aAa', 'high', 'solved', 1, NULL, 'web', 'web', -36, '2026-09-02 10:54:07', 1, 5, 'thanks', '2026-09-02 08:30:35', '2026-09-02 09:10:53'),
(2, '9141-AA0241', 1, 'Caala', '0904190352', 'male', 'ganda Haara', '08', 8.5280000, 39.2700000, 'rakkoo nageenya  nanno ganda haara', 'critical', 'solved', 1, NULL, 'web', 'web', -48, '2026-09-02 21:59:48', 1, NULL, NULL, '2026-09-02 19:48:17', '2026-09-02 19:59:48'),
(3, '9141-E85171', 1, 'Saabir', '0904190352', 'male', 'Bokku', '01', 8.5550000, 39.2550000, 'hghuhijijk', 'high', 'solved', 1, NULL, 'web', 'web', -46, '2026-09-02 22:04:43', 1, NULL, NULL, '2026-09-02 19:50:16', '2026-09-02 20:04:43'),
(4, '9141-9BCD31', 1, 'Kuulan', '0904190352', 'male', 'Gada', '02', 8.5390000, 39.2750000, 'gfjyuuyu8kiu', 'high', 'unsolved', 1, NULL, 'web', 'web', -49, '2026-09-02 22:02:55', 0, NULL, NULL, '2026-09-02 19:51:46', '2026-09-02 20:02:55'),
(5, '9141-022E4F', 1, 'jaafer', '0904190352', 'male', 'hora', '03', 8.5450000, 39.2680000, 'kjnsajijiduwjdxkmlwm', 'high', 'solved', 1, NULL, 'web', 'web', -53, '2026-09-02 22:00:48', 0, NULL, NULL, '2026-09-02 19:52:45', '2026-09-02 20:00:48'),
(6, '9141-1C08D6', 2, 'Haadiya', '0904190352', 'female', 'Bolee', '05', 8.5480000, 39.2780000, 'wqfAGthjyukiulugfcghxgxdhh', 'high', 'ongoing', 1, NULL, 'web', 'web', NULL, NULL, 1, NULL, NULL, '2026-09-02 20:11:41', '2026-09-02 20:19:11'),
(7, '9141-D8E4B5', 2, 'Goobana', '0904190352', 'male', 'moger', '06', 8.5330000, 39.2850000, 'rdgttr5dyrthntyhtkiu', 'critical', 'solved', 1, NULL, 'web', 'web', -53, '2026-09-02 22:19:31', 1, NULL, NULL, '2026-09-02 20:12:45', '2026-09-02 20:19:31'),
(8, '9141-F6E47A', 2, 'Leenco', '0904190352', 'male', 'Ejere', '07', 8.5520000, 39.2600000, 'xergerhyi78g77ytf6u', 'high', 'unsolved', 1, NULL, 'web', 'web', -54, '2026-09-02 22:19:42', 1, NULL, NULL, '2026-09-02 20:13:32', '2026-09-02 20:19:42'),
(9, '9141-CCE6CC', 2, 'Galaan', '0904190352', 'male', 'hora', '08', 8.5310640, 39.2605030, 'dgfrgrr5grt5t', 'high', 'solved', 1, NULL, 'web', 'web', -55, '2026-09-02 22:19:54', 0, NULL, NULL, '2026-09-02 20:14:41', '2026-09-02 20:19:54'),
(10, '9141-81C94C', 3, 'Muna', '0904190352', 'male', 'dambla', '04', 8.5360000, 39.2620000, 'sdfefedr', 'high', 'solved', 4, NULL, 'web', 'web', -54, '2026-09-02 22:28:44', 0, NULL, NULL, '2026-09-02 20:22:17', '2026-09-02 20:28:44'),
(11, '9141-FA5ED5', 3, 'Abdu', '0904190352', 'male', 'Ba\'atu', '06', 8.5330000, 39.2850000, 'tf6ur56y5y', 'high', 'ongoing', 4, NULL, 'web', 'web', NULL, NULL, 0, NULL, NULL, '2026-09-02 20:23:15', '2026-09-02 20:28:19'),
(12, '9141-812FEA', 3, 'Milkessa', '0904190352', 'male', 'dambla', '07', 8.5520000, 39.2600000, 'tfchcgfumjh', 'high', 'solved', 4, NULL, 'web', 'web', -56, '2026-09-02 22:28:06', 0, NULL, NULL, '2026-09-02 20:23:52', '2026-09-02 20:28:06'),
(13, '9141-82EADD', 4, 'Naol', '0904190352', 'male', 'dambla', '06', 8.5330000, 39.2850000, 'efdfrefdrf', 'critical', 'solved', 3, NULL, 'web', 'web', -47, '2026-09-02 22:48:48', 1, NULL, NULL, '2026-09-02 20:35:22', '2026-09-02 20:48:48'),
(14, '9141-5A823C', 4, 'Goobana', '0904190352', 'male', 'Bokku', '09', 8.5580000, 39.2720000, 'njbjknkjjjk', 'critical', 'solved', 3, NULL, 'web', 'web', -48, '2026-09-02 22:48:27', 1, NULL, NULL, '2026-09-02 20:36:05', '2026-09-02 20:48:27'),
(15, '9141-97677A', 4, 'Caala', '0904190352', 'male', 'ganda Haara', '05', 8.5480000, 39.2780000, 'mjbgvfygujhih', 'critical', 'solved', 3, NULL, 'web', 'web', -51, '2026-09-02 22:48:11', 1, NULL, NULL, '2026-09-02 20:39:24', '2026-09-02 20:48:11'),
(16, '9141-A86EA9', 4, 'Gameda', '0904190352', 'male', 'Ejree', '03', 8.5450000, 39.2680000, 'ooihiuhfrtyuytvgbhnbmnm', 'high', 'ongoing', 3, NULL, 'web', 'web', NULL, NULL, 1, NULL, NULL, '2026-09-02 20:41:21', '2026-09-02 20:48:00'),
(17, '9141-A22EE4', 4, 'Naol', '0904190352', 'male', 'Bole', '02', 8.5390000, 39.2750000, 'scxdvfddfg', 'critical', 'solved', 3, NULL, 'web', 'web', -55, '2026-09-02 22:47:46', 0, NULL, NULL, '2026-09-02 20:43:06', '2026-09-02 20:47:46'),
(18, '9141-1933C3', 4, 'Musa', '0904190352', 'male', 'ganda Haara', '09', 8.5580000, 39.2720000, 'fgbgfbgfb', 'high', 'solved', 2, NULL, 'web', 'web', -53, '2026-09-02 22:59:11', 0, NULL, NULL, '2026-09-02 20:52:23', '2026-09-02 20:59:11'),
(19, '9141-8B2BB9', 4, 'Samud', '0904190352', 'male', 'dambla', '07', 8.5520000, 39.2600000, 'sfjuyjuhmhjumuyjmiuo', 'high', 'solved', 2, NULL, 'web', 'web', -54, '2026-09-02 22:59:01', 0, NULL, NULL, '2026-09-02 20:53:11', '2026-09-02 20:59:01'),
(20, '9141-35C0E0', 4, 'Naol', '0904190352', 'male', 'Ba\'atu', '08', 8.5280000, 39.2700000, 'ythjy7fujjgykiulio;op;poooooooo;iojhhg', 'high', 'solved', 2, NULL, 'web', 'web', -55, '2026-09-02 22:58:49', 0, NULL, NULL, '2026-09-02 20:54:04', '2026-09-02 20:58:49'),
(21, '9141-1DAEAA', 4, 'Guutaa', '0960236616', 'male', 'Ganda qore', '02', 8.5390000, 39.2750000, 'trui87o89i9,kmnp', 'high', 'solved', 2, NULL, 'web', 'web', -56, '2026-09-02 22:58:36', 0, NULL, NULL, '2026-09-02 20:55:04', '2026-09-02 20:58:36'),
(22, '9141-BBE820', 4, 'Milkessa', '0904190352', 'male', 'Bole', '11', 8.5350000, 39.2800000, 'ewfiuki,l.liui', 'high', 'solved', 2, NULL, 'web', 'web', -58, '2026-09-02 22:58:25', 0, 5, 'galatooma', '2026-09-02 20:56:06', '2026-09-02 21:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `event_attachments`
--

CREATE TABLE `event_attachments` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(32) NOT NULL DEFAULT 'document',
  `file_size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_attachments`
--

INSERT INTO `event_attachments` (`id`, `event_id`, `file_path`, `original_name`, `file_type`, `file_size`, `created_at`) VALUES
(1, 1, 'uploads/77a334d3eb830aa2.jpg', 'fr2.jpg', 'image', 44646, '2026-09-02 08:30:35'),
(2, 2, 'uploads/c107328c105444b5.jpg', 'illegal1.jpg', 'image', 43767, '2026-09-02 19:48:17'),
(3, 3, 'uploads/dce36bae4947f53e.jpg', 'iillegal act.jpg', 'image', 24900, '2026-09-02 19:50:16'),
(4, 4, 'uploads/e23ac0d3cf034ae5.jpg', 'illegal.jpg', 'image', 15335, '2026-09-02 19:51:46'),
(5, 5, 'uploads/5a1efee6d40a026e.jpg', 'illegal1.jpg', 'image', 43767, '2026-09-02 19:52:45'),
(6, 6, 'uploads/880c50861035c86c.jpg', 'secrty.jpg', 'image', 15041, '2026-09-02 20:11:41'),
(7, 7, 'uploads/d4fdea30f6fc00a3.jpg', 'secrty.jpg', 'image', 15041, '2026-09-02 20:12:45'),
(8, 8, 'uploads/de7c310e149aaac7.png', 'secrty3.png', 'image', 3694, '2026-09-02 20:13:32'),
(9, 9, 'uploads/1b96bc821e290cb4.jpg', 'secrty6.jpg', 'image', 43025, '2026-09-02 20:14:41'),
(10, 10, 'uploads/eca493e2464f9113.jpg', 'serv 2.jpg', 'image', 40800, '2026-09-02 20:22:17'),
(11, 11, 'uploads/204059420b26c327.jpg', 'serv3.jpg', 'image', 31761, '2026-09-02 20:23:15'),
(12, 12, 'uploads/f93f3115aaffd661.jpg', 'serv3.jpg', 'image', 31761, '2026-09-02 20:23:52'),
(13, 13, 'uploads/af998d44839af10b.jpg', 'fire1.jpg', 'image', 32976, '2026-09-02 20:35:22'),
(14, 14, 'uploads/18e8f7a573c58beb.jpg', 'fr2.jpg', 'image', 44646, '2026-09-02 20:36:05'),
(15, 15, 'uploads/bdb147c22a220621.jpg', 'fire7.jpg', 'image', 23267, '2026-09-02 20:39:24'),
(16, 16, 'uploads/99093371c2afcc99.jpg', 'fire1.jpg', 'image', 32976, '2026-09-02 20:41:21'),
(17, 17, 'uploads/4bec799293403e48.jpg', 'fire4.jpg', 'image', 48025, '2026-09-02 20:43:06'),
(18, 19, 'uploads/182dfada35d23e95.jpg', 'didrty4.jpg', 'image', 61405, '2026-09-02 20:53:12'),
(19, 20, 'uploads/e7b352718c55b7af.jpg', 'disrt1.jpg', 'image', 15874, '2026-09-02 20:54:04'),
(20, 21, 'uploads/9a123420d68f7f47.jpg', 'didrty4.jpg', 'image', 61405, '2026-09-02 20:55:04'),
(21, 22, 'uploads/dbb0ced2a7551ffd.jpg', 'sochi lafa.jpg', 'image', 12458, '2026-09-02 20:56:06');

-- --------------------------------------------------------

--
-- Table structure for table `event_logs`
--

CREATE TABLE `event_logs` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `action` varchar(64) NOT NULL,
  `note` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_logs`
--

INSERT INTO `event_logs` (`id`, `event_id`, `action`, `note`, `changed_by`, `changed_at`) VALUES
(1, 1, 'created', 'Event submitted via public web form', NULL, 0),
(2, 1, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(3, 1, 'escalated', 'Escalated to Police / Poolisii', 1, 0),
(4, 1, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(5, 1, 'status_change', 'Status set to solved', 1, 0),
(6, 1, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(7, 2, 'created', 'Event submitted via public web form', NULL, 0),
(8, 2, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(9, 3, 'created', 'Event submitted via public web form', NULL, 0),
(10, 3, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(11, 4, 'created', 'Event submitted via public web form', NULL, 0),
(12, 4, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(13, 5, 'created', 'Event submitted via public web form', NULL, 0),
(14, 5, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(15, 5, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(16, 5, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(17, 4, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(18, 4, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(19, 4, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(20, 4, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(21, 3, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(22, 3, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(23, 2, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(24, 2, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(25, 2, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(26, 2, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(27, 5, 'status_change', 'Status set to solved', 4, 0),
(28, 5, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(29, 2, 'status_change', 'Status set to solved', 4, 0),
(30, 2, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(31, 5, 'status_change', 'Status set to solved', 4, 0),
(32, 5, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(33, 4, 'status_change', 'Status set to unsolved', 4, 0),
(34, 4, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(35, 3, 'status_change', 'Status set to solved', 4, 0),
(36, 3, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(37, 6, 'created', 'Event submitted via public web form', NULL, 0),
(38, 6, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(39, 7, 'created', 'Event submitted via public web form', NULL, 0),
(40, 7, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(41, 8, 'created', 'Event submitted via public web form', NULL, 0),
(42, 8, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(43, 9, 'created', 'Event submitted via public web form', NULL, 0),
(44, 9, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(45, 9, 'escalated', 'Escalated to Police / Poolisii', 1, 0),
(46, 9, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(47, 6, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(48, 6, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(49, 7, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(50, 7, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(51, 8, 'escalated', 'Escalated to Police / Poolisii', 2, 0),
(52, 8, 'sms', 'SMS attempt failed for 0911000001: SMS disabled', NULL, 0),
(53, 6, 'status_change', 'Status set to ongoing', 4, 0),
(54, 7, 'status_change', 'Status set to solved', 4, 0),
(55, 7, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(56, 8, 'status_change', 'Status set to unsolved', 4, 0),
(57, 8, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(58, 9, 'status_change', 'Status set to solved', 4, 0),
(59, 9, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(60, 10, 'created', 'Event submitted via public web form', NULL, 0),
(61, 10, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(62, 11, 'created', 'Event submitted via public web form', NULL, 0),
(63, 11, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(64, 12, 'created', 'Event submitted via public web form', NULL, 0),
(65, 12, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(66, 12, 'escalated', 'Escalated to City Services / Tajaajila', 2, 0),
(67, 12, 'sms', 'SMS attempt failed for 0911000004: SMS disabled', NULL, 0),
(68, 11, 'escalated', 'Escalated to City Services / Tajaajila', 2, 0),
(69, 11, 'sms', 'SMS attempt failed for 0911000004: SMS disabled', NULL, 0),
(70, 10, 'escalated', 'Escalated to City Services / Tajaajila', 2, 0),
(71, 10, 'sms', 'SMS attempt failed for 0911000004: SMS disabled', NULL, 0),
(72, 12, 'status_change', 'Status set to solved', 7, 0),
(73, 12, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(74, 11, 'status_change', 'Status set to ongoing', 7, 0),
(75, 10, 'status_change', 'Status set to solved', 7, 0),
(76, 10, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(77, 13, 'created', 'Event submitted via public web form', NULL, 0),
(78, 13, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(79, 14, 'created', 'Event submitted via public web form', NULL, 0),
(80, 14, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(81, 15, 'created', 'Event submitted via public web form', NULL, 0),
(82, 15, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(83, 16, 'created', 'Event submitted via public web form', NULL, 0),
(84, 16, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(85, 17, 'created', 'Event submitted via public web form', NULL, 0),
(86, 17, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(87, 17, 'escalated', 'Escalated to Fire & Emergency', 2, 0),
(88, 17, 'sms', 'SMS attempt failed for 0911000003: SMS disabled', NULL, 0),
(89, 13, 'escalated', 'Escalated to Fire & Emergency', 2, 0),
(90, 13, 'sms', 'SMS attempt failed for 0911000003: SMS disabled', NULL, 0),
(91, 14, 'escalated', 'Escalated to Fire & Emergency', 2, 0),
(92, 14, 'sms', 'SMS attempt failed for 0911000003: SMS disabled', NULL, 0),
(93, 15, 'escalated', 'Escalated to Fire & Emergency', 2, 0),
(94, 15, 'sms', 'SMS attempt failed for 0911000003: SMS disabled', NULL, 0),
(95, 16, 'escalated', 'Escalated to Fire & Emergency', 2, 0),
(96, 16, 'sms', 'SMS attempt failed for 0911000003: SMS disabled', NULL, 0),
(97, 17, 'status_change', 'Status set to solved', 8, 0),
(98, 17, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(99, 16, 'status_change', 'Status set to ongoing', 8, 0),
(100, 15, 'status_change', 'Status set to solved', 8, 0),
(101, 15, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(102, 14, 'status_change', 'Status set to solved', 8, 0),
(103, 14, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(104, 13, 'status_change', 'Status set to solved', 8, 0),
(105, 13, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(106, 18, 'created', 'Event submitted via public web form', NULL, 0),
(107, 18, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(108, 19, 'created', 'Event submitted via public web form', NULL, 0),
(109, 19, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(110, 20, 'created', 'Event submitted via public web form', NULL, 0),
(111, 20, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(112, 21, 'created', 'Event submitted via public web form', NULL, 0),
(113, 21, 'sms', 'SMS attempt failed for 0960236616: SMS disabled', NULL, 0),
(114, 22, 'created', 'Event submitted via public web form', NULL, 0),
(115, 22, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(116, 18, 'escalated', 'Escalated to Traffic / Trafikaa', 2, 0),
(117, 18, 'sms', 'SMS attempt failed for 0911000002: SMS disabled', NULL, 0),
(118, 19, 'escalated', 'Escalated to Traffic / Trafikaa', 2, 0),
(119, 19, 'sms', 'SMS attempt failed for 0911000002: SMS disabled', NULL, 0),
(120, 20, 'escalated', 'Escalated to Traffic / Trafikaa', 2, 0),
(121, 20, 'sms', 'SMS attempt failed for 0911000002: SMS disabled', NULL, 0),
(122, 21, 'escalated', 'Escalated to Traffic / Trafikaa', 2, 0),
(123, 21, 'sms', 'SMS attempt failed for 0911000002: SMS disabled', NULL, 0),
(124, 22, 'escalated', 'Escalated to Traffic / Trafikaa', 2, 0),
(125, 22, 'sms', 'SMS attempt failed for 0911000002: SMS disabled', NULL, 0),
(126, 22, 'status_change', 'Status set to solved', 9, 0),
(127, 22, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(128, 21, 'status_change', 'Status set to solved', 9, 0),
(129, 21, 'sms', 'SMS attempt failed for 0960236616: SMS disabled', NULL, 0),
(130, 20, 'status_change', 'Status set to solved', 9, 0),
(131, 20, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(132, 19, 'status_change', 'Status set to solved', 9, 0),
(133, 19, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0),
(134, 18, 'status_change', 'Status set to solved', 9, 0),
(135, 18, 'sms', 'SMS attempt failed for 0904190352: SMS disabled', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `followups`
--

CREATE TABLE `followups` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `followup_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` varchar(32) DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `help_requests`
--

CREATE TABLE `help_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `tracking_code` varchar(32) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('open','answered','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000001_create_cache_table', 1),
(2, '0001_01_01_000002_create_jobs_table', 1),
(3, '2024_01_01_000001_create_callcenter_tables', 1),
(4, '2026_09_01_204637_create_personal_access_tokens_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `type` varchar(64) DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `event_id`, `type`, `title`, `message`, `is_urgent`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-D0B939', 1, 0, '2026-09-02 08:30:35'),
(2, 2, 1, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-D0B939', 1, 0, '2026-09-02 08:30:35'),
(3, 1, 1, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Al-seerummaa / Illegal — 9141-D0B939', 1, 0, '2026-09-02 08:35:48'),
(4, 2, 1, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Al-seerummaa / Illegal — 9141-D0B939', 1, 0, '2026-09-02 08:35:48'),
(5, 1, NULL, 'citizen_help', 'Gargaarsa lammii', '0904190352 — hhhioj', 1, 0, '2026-09-02 08:38:17'),
(6, 2, NULL, 'citizen_help', 'Gargaarsa lammii', '0904190352 — hhhioj', 1, 0, '2026-09-02 08:38:17'),
(7, 3, NULL, 'citizen_help', 'Gargaarsa lammii', '0904190352 — hhhioj', 1, 0, '2026-09-02 08:38:17'),
(8, 4, 1, 'escalation', 'Event escalated to your department', '9141-D0B939', 1, 1, '2026-09-02 08:53:57'),
(9, 1, 1, 'escalation', 'Event escalated', '9141-D0B939', 0, 0, '2026-09-02 08:53:58'),
(10, 3, 1, 'escalation', 'Event escalated', '9141-D0B939', 0, 0, '2026-09-02 08:53:58'),
(11, 1, 1, 'status_update', 'Report status updated', '9141-D0B939 — Solved', 0, 0, '2026-09-02 08:54:07'),
(12, 3, 1, 'status_update', 'Report status updated', '9141-D0B939 — Solved', 0, 0, '2026-09-02 08:54:07'),
(13, 1, 2, 'new_event', '🚨 New critical priority event', 'Al-seerummaa / Illegal — 9141-AA0241', 1, 0, '2026-09-02 19:48:17'),
(14, 2, 2, 'new_event', '🚨 New critical priority event', 'Al-seerummaa / Illegal — 9141-AA0241', 1, 0, '2026-09-02 19:48:17'),
(15, 1, 3, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-E85171', 1, 0, '2026-09-02 19:50:16'),
(16, 2, 3, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-E85171', 1, 0, '2026-09-02 19:50:16'),
(17, 1, 4, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-9BCD31', 1, 0, '2026-09-02 19:51:46'),
(18, 2, 4, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-9BCD31', 1, 0, '2026-09-02 19:51:46'),
(19, 1, 5, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-022E4F', 1, 0, '2026-09-02 19:52:45'),
(20, 2, 5, 'new_event', '🚨 New high priority event', 'Al-seerummaa / Illegal — 9141-022E4F', 1, 0, '2026-09-02 19:52:45'),
(21, 1, 2, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Al-seerummaa / Illegal — 9141-AA0241', 1, 0, '2026-09-02 19:55:11'),
(22, 2, 2, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Al-seerummaa / Illegal — 9141-AA0241', 1, 0, '2026-09-02 19:55:11'),
(23, 4, 5, 'escalation', 'Event escalated to your department', '9141-022E4F', 1, 1, '2026-09-02 19:55:30'),
(24, 1, 5, 'escalation', 'Event escalated', '9141-022E4F', 0, 0, '2026-09-02 19:55:30'),
(25, 3, 5, 'escalation', 'Event escalated', '9141-022E4F', 0, 0, '2026-09-02 19:55:30'),
(26, 1, 3, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Al-seerummaa / Illegal — 9141-E85171', 1, 0, '2026-09-02 19:55:30'),
(27, 2, 3, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Al-seerummaa / Illegal — 9141-E85171', 1, 0, '2026-09-02 19:55:30'),
(28, 4, 4, 'escalation', 'Event escalated to your department', '9141-9BCD31', 1, 1, '2026-09-02 19:56:02'),
(29, 1, 4, 'escalation', 'Event escalated', '9141-9BCD31', 0, 0, '2026-09-02 19:56:02'),
(30, 3, 4, 'escalation', 'Event escalated', '9141-9BCD31', 0, 0, '2026-09-02 19:56:02'),
(31, 4, 4, 'escalation', 'Event escalated to your department', '9141-9BCD31', 1, 1, '2026-09-02 19:56:10'),
(32, 1, 4, 'escalation', 'Event escalated', '9141-9BCD31', 0, 0, '2026-09-02 19:56:10'),
(33, 3, 4, 'escalation', 'Event escalated', '9141-9BCD31', 0, 0, '2026-09-02 19:56:10'),
(34, 4, 3, 'escalation', 'Event escalated to your department', '9141-E85171', 1, 1, '2026-09-02 19:56:22'),
(35, 1, 3, 'escalation', 'Event escalated', '9141-E85171', 0, 0, '2026-09-02 19:56:22'),
(36, 3, 3, 'escalation', 'Event escalated', '9141-E85171', 0, 0, '2026-09-02 19:56:22'),
(37, 4, 2, 'escalation', 'Event escalated to your department', '9141-AA0241', 1, 1, '2026-09-02 19:56:37'),
(38, 1, 2, 'escalation', 'Event escalated', '9141-AA0241', 0, 0, '2026-09-02 19:56:37'),
(39, 3, 2, 'escalation', 'Event escalated', '9141-AA0241', 0, 0, '2026-09-02 19:56:37'),
(40, 4, 2, 'escalation', 'Event escalated to your department', '9141-AA0241', 1, 1, '2026-09-02 19:56:42'),
(41, 1, 2, 'escalation', 'Event escalated', '9141-AA0241', 0, 0, '2026-09-02 19:56:42'),
(42, 3, 2, 'escalation', 'Event escalated', '9141-AA0241', 0, 0, '2026-09-02 19:56:42'),
(43, 1, 5, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-022E4F — Furameera', 0, 0, '2026-09-02 19:59:26'),
(44, 3, 5, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-022E4F — Furameera', 0, 0, '2026-09-02 19:59:26'),
(45, 1, 2, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-AA0241 — Furameera', 0, 0, '2026-09-02 19:59:48'),
(46, 3, 2, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-AA0241 — Furameera', 0, 0, '2026-09-02 19:59:48'),
(47, 1, 5, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-022E4F — Furameera', 0, 0, '2026-09-02 20:00:48'),
(48, 3, 5, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-022E4F — Furameera', 0, 0, '2026-09-02 20:00:48'),
(49, 1, 4, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-9BCD31 — Hin furamne', 0, 0, '2026-09-02 20:02:55'),
(50, 3, 4, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-9BCD31 — Hin furamne', 0, 0, '2026-09-02 20:02:55'),
(51, 1, 3, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-E85171 — Furameera', 0, 0, '2026-09-02 20:04:43'),
(52, 3, 3, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-E85171 — Furameera', 0, 0, '2026-09-02 20:04:43'),
(53, 1, 6, 'new_event', 'New high priority event', 'Rakkoo Nageenyaa / Security — 9141-1C08D6', 0, 0, '2026-09-02 20:11:41'),
(54, 2, 6, 'new_event', 'New high priority event', 'Rakkoo Nageenyaa / Security — 9141-1C08D6', 0, 0, '2026-09-02 20:11:41'),
(55, 1, 7, 'new_event', 'New critical priority event', 'Rakkoo Nageenyaa / Security — 9141-D8E4B5', 0, 0, '2026-09-02 20:12:45'),
(56, 2, 7, 'new_event', 'New critical priority event', 'Rakkoo Nageenyaa / Security — 9141-D8E4B5', 0, 0, '2026-09-02 20:12:45'),
(57, 1, 8, 'new_event', 'New high priority event', 'Rakkoo Nageenyaa / Security — 9141-F6E47A', 0, 0, '2026-09-02 20:13:32'),
(58, 2, 8, 'new_event', 'New high priority event', 'Rakkoo Nageenyaa / Security — 9141-F6E47A', 0, 0, '2026-09-02 20:13:32'),
(59, 1, 9, 'new_event', 'New high priority event', 'Rakkoo Nageenyaa / Security — 9141-CCE6CC', 0, 0, '2026-09-02 20:14:41'),
(60, 2, 9, 'new_event', 'New high priority event', 'Rakkoo Nageenyaa / Security — 9141-CCE6CC', 0, 0, '2026-09-02 20:14:41'),
(61, 4, 9, 'escalation', 'Event escalated to your department', '9141-CCE6CC', 1, 0, '2026-09-02 20:16:17'),
(62, 1, 9, 'escalation', 'Event escalated', '9141-CCE6CC', 0, 0, '2026-09-02 20:16:17'),
(63, 3, 9, 'escalation', 'Event escalated', '9141-CCE6CC', 0, 0, '2026-09-02 20:16:17'),
(64, 1, 6, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Rakkoo Nageenyaa / Security — 9141-1C08D6', 1, 0, '2026-09-02 20:16:49'),
(65, 2, 6, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Rakkoo Nageenyaa / Security — 9141-1C08D6', 1, 0, '2026-09-02 20:16:49'),
(66, 4, 6, 'escalation', 'Event escalated to your department', '9141-1C08D6', 1, 0, '2026-09-02 20:16:55'),
(67, 1, 6, 'escalation', 'Event escalated', '9141-1C08D6', 0, 0, '2026-09-02 20:16:55'),
(68, 3, 6, 'escalation', 'Event escalated', '9141-1C08D6', 0, 0, '2026-09-02 20:16:55'),
(69, 1, 7, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Rakkoo Nageenyaa / Security — 9141-D8E4B5', 1, 0, '2026-09-02 20:17:49'),
(70, 2, 7, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Rakkoo Nageenyaa / Security — 9141-D8E4B5', 1, 0, '2026-09-02 20:17:49'),
(71, 4, 7, 'escalation', 'Event escalated to your department', '9141-D8E4B5', 1, 0, '2026-09-02 20:17:58'),
(72, 1, 7, 'escalation', 'Event escalated', '9141-D8E4B5', 0, 0, '2026-09-02 20:17:58'),
(73, 3, 7, 'escalation', 'Event escalated', '9141-D8E4B5', 0, 0, '2026-09-02 20:17:58'),
(74, 1, 8, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Rakkoo Nageenyaa / Security — 9141-F6E47A', 1, 0, '2026-09-02 20:18:35'),
(75, 2, 8, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Rakkoo Nageenyaa / Security — 9141-F6E47A', 1, 0, '2026-09-02 20:18:35'),
(76, 4, 8, 'escalation', 'Event escalated to your department', '9141-F6E47A', 1, 0, '2026-09-02 20:18:41'),
(77, 1, 8, 'escalation', 'Event escalated', '9141-F6E47A', 0, 0, '2026-09-02 20:18:41'),
(78, 3, 8, 'escalation', 'Event escalated', '9141-F6E47A', 0, 0, '2026-09-02 20:18:41'),
(79, 1, 6, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-1C08D6 — Adeemsa irra jira', 0, 0, '2026-09-02 20:19:11'),
(80, 3, 6, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-1C08D6 — Adeemsa irra jira', 0, 0, '2026-09-02 20:19:11'),
(81, 1, 7, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-D8E4B5 — Furameera', 0, 0, '2026-09-02 20:19:31'),
(82, 3, 7, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-D8E4B5 — Furameera', 0, 0, '2026-09-02 20:19:31'),
(83, 1, 8, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-F6E47A — Hin furamne', 0, 0, '2026-09-02 20:19:42'),
(84, 3, 8, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-F6E47A — Hin furamne', 0, 0, '2026-09-02 20:19:42'),
(85, 1, 9, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-CCE6CC — Furameera', 0, 0, '2026-09-02 20:19:54'),
(86, 3, 9, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-CCE6CC — Furameera', 0, 0, '2026-09-02 20:19:54'),
(87, 1, 10, 'new_event', 'New high priority event', 'Rakkoo Tajaajila / Service — 9141-81C94C', 0, 0, '2026-09-02 20:22:17'),
(88, 2, 10, 'new_event', 'New high priority event', 'Rakkoo Tajaajila / Service — 9141-81C94C', 0, 0, '2026-09-02 20:22:17'),
(89, 1, 11, 'new_event', 'New high priority event', 'Rakkoo Tajaajila / Service — 9141-FA5ED5', 0, 0, '2026-09-02 20:23:15'),
(90, 2, 11, 'new_event', 'New high priority event', 'Rakkoo Tajaajila / Service — 9141-FA5ED5', 0, 0, '2026-09-02 20:23:15'),
(91, 1, 12, 'new_event', 'New high priority event', 'Rakkoo Tajaajila / Service — 9141-812FEA', 0, 0, '2026-09-02 20:23:52'),
(92, 2, 12, 'new_event', 'New high priority event', 'Rakkoo Tajaajila / Service — 9141-812FEA', 0, 0, '2026-09-02 20:23:52'),
(93, 7, 12, 'escalation', 'Event escalated to your department', '9141-812FEA', 1, 0, '2026-09-02 20:24:17'),
(94, 1, 12, 'escalation', 'Event escalated', '9141-812FEA', 0, 0, '2026-09-02 20:24:17'),
(95, 3, 12, 'escalation', 'Event escalated', '9141-812FEA', 0, 0, '2026-09-02 20:24:17'),
(96, 7, 11, 'escalation', 'Event escalated to your department', '9141-FA5ED5', 1, 0, '2026-09-02 20:24:30'),
(97, 1, 11, 'escalation', 'Event escalated', '9141-FA5ED5', 0, 0, '2026-09-02 20:24:30'),
(98, 3, 11, 'escalation', 'Event escalated', '9141-FA5ED5', 0, 0, '2026-09-02 20:24:30'),
(99, 7, 10, 'escalation', 'Event escalated to your department', '9141-81C94C', 1, 0, '2026-09-02 20:24:43'),
(100, 1, 10, 'escalation', 'Event escalated', '9141-81C94C', 0, 0, '2026-09-02 20:24:43'),
(101, 3, 10, 'escalation', 'Event escalated', '9141-81C94C', 0, 0, '2026-09-02 20:24:43'),
(102, 1, 12, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-812FEA — Furameera', 0, 0, '2026-09-02 20:28:06'),
(103, 3, 12, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-812FEA — Furameera', 0, 0, '2026-09-02 20:28:06'),
(104, 1, 11, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-FA5ED5 — Adeemsa irra jira', 0, 0, '2026-09-02 20:28:19'),
(105, 3, 11, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-FA5ED5 — Adeemsa irra jira', 0, 0, '2026-09-02 20:28:19'),
(106, 1, 10, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-81C94C — Furameera', 0, 0, '2026-09-02 20:28:44'),
(107, 3, 10, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-81C94C — Furameera', 0, 0, '2026-09-02 20:28:44'),
(108, 1, 13, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-82EADD', 0, 0, '2026-09-02 20:35:22'),
(109, 2, 13, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-82EADD', 0, 0, '2026-09-02 20:35:22'),
(110, 1, 14, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-5A823C', 0, 0, '2026-09-02 20:36:05'),
(111, 2, 14, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-5A823C', 0, 0, '2026-09-02 20:36:05'),
(112, 1, 15, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-97677A', 0, 0, '2026-09-02 20:39:24'),
(113, 2, 15, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-97677A', 0, 0, '2026-09-02 20:39:24'),
(114, 1, 16, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-A86EA9', 0, 0, '2026-09-02 20:41:21'),
(115, 2, 16, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-A86EA9', 0, 0, '2026-09-02 20:41:21'),
(116, 1, 13, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-82EADD', 1, 0, '2026-09-02 20:41:55'),
(117, 2, 13, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-82EADD', 1, 0, '2026-09-02 20:41:55'),
(118, 1, 14, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-5A823C', 1, 0, '2026-09-02 20:41:55'),
(119, 2, 14, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-5A823C', 1, 0, '2026-09-02 20:41:55'),
(120, 1, 17, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-A22EE4', 0, 0, '2026-09-02 20:43:06'),
(121, 2, 17, 'new_event', 'New critical priority event', 'Balaa Tasaa / Emergency — 9141-A22EE4', 0, 0, '2026-09-02 20:43:06'),
(122, 8, 17, 'escalation', 'Event escalated to your department', '9141-A22EE4', 1, 0, '2026-09-02 20:43:23'),
(123, 1, 17, 'escalation', 'Event escalated', '9141-A22EE4', 0, 0, '2026-09-02 20:43:23'),
(124, 3, 17, 'escalation', 'Event escalated', '9141-A22EE4', 0, 0, '2026-09-02 20:43:23'),
(125, 8, 13, 'escalation', 'Event escalated to your department', '9141-82EADD', 1, 0, '2026-09-02 20:43:50'),
(126, 1, 13, 'escalation', 'Event escalated', '9141-82EADD', 0, 0, '2026-09-02 20:43:50'),
(127, 3, 13, 'escalation', 'Event escalated', '9141-82EADD', 0, 0, '2026-09-02 20:43:50'),
(128, 8, 14, 'escalation', 'Event escalated to your department', '9141-5A823C', 1, 0, '2026-09-02 20:44:09'),
(129, 1, 14, 'escalation', 'Event escalated', '9141-5A823C', 0, 0, '2026-09-02 20:44:09'),
(130, 3, 14, 'escalation', 'Event escalated', '9141-5A823C', 0, 0, '2026-09-02 20:44:09'),
(131, 1, 15, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-97677A', 1, 0, '2026-09-02 20:44:39'),
(132, 2, 15, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-97677A', 1, 0, '2026-09-02 20:44:39'),
(133, 8, 15, 'escalation', 'Event escalated to your department', '9141-97677A', 1, 0, '2026-09-02 20:45:16'),
(134, 1, 15, 'escalation', 'Event escalated', '9141-97677A', 0, 0, '2026-09-02 20:45:16'),
(135, 3, 15, 'escalation', 'Event escalated', '9141-97677A', 0, 0, '2026-09-02 20:45:16'),
(136, 1, 16, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-A86EA9', 1, 0, '2026-09-02 20:46:22'),
(137, 2, 16, 'escalating_alert', '⏳ Escalating: unhandled 5+ min', 'Balaa Tasaa / Emergency — 9141-A86EA9', 1, 0, '2026-09-02 20:46:22'),
(138, 8, 16, 'escalation', 'Event escalated to your department', '9141-A86EA9', 1, 0, '2026-09-02 20:46:31'),
(139, 1, 16, 'escalation', 'Event escalated', '9141-A86EA9', 0, 0, '2026-09-02 20:46:31'),
(140, 3, 16, 'escalation', 'Event escalated', '9141-A86EA9', 0, 0, '2026-09-02 20:46:31'),
(141, 1, 17, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-A22EE4 — Furameera', 0, 0, '2026-09-02 20:47:46'),
(142, 3, 17, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-A22EE4 — Furameera', 0, 0, '2026-09-02 20:47:46'),
(143, 1, 16, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-A86EA9 — Adeemsa irra jira', 0, 0, '2026-09-02 20:48:00'),
(144, 3, 16, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-A86EA9 — Adeemsa irra jira', 0, 0, '2026-09-02 20:48:00'),
(145, 1, 15, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-97677A — Furameera', 0, 0, '2026-09-02 20:48:11'),
(146, 3, 15, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-97677A — Furameera', 0, 0, '2026-09-02 20:48:11'),
(147, 1, 14, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-5A823C — Furameera', 0, 0, '2026-09-02 20:48:27'),
(148, 3, 14, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-5A823C — Furameera', 0, 0, '2026-09-02 20:48:27'),
(149, 1, 13, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-82EADD — Furameera', 0, 0, '2026-09-02 20:48:48'),
(150, 3, 13, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-82EADD — Furameera', 0, 0, '2026-09-02 20:48:48'),
(151, 1, 18, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-1933C3', 0, 0, '2026-09-02 20:52:23'),
(152, 2, 18, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-1933C3', 0, 0, '2026-09-02 20:52:23'),
(153, 1, 19, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-8B2BB9', 0, 0, '2026-09-02 20:53:11'),
(154, 2, 19, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-8B2BB9', 0, 0, '2026-09-02 20:53:11'),
(155, 1, 20, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-35C0E0', 0, 0, '2026-09-02 20:54:04'),
(156, 2, 20, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-35C0E0', 0, 0, '2026-09-02 20:54:04'),
(157, 1, 21, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-1DAEAA', 0, 0, '2026-09-02 20:55:04'),
(158, 2, 21, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-1DAEAA', 0, 0, '2026-09-02 20:55:04'),
(159, 1, 22, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-BBE820', 0, 0, '2026-09-02 20:56:06'),
(160, 2, 22, 'new_event', 'New high priority event', 'Balaa Tasaa / Emergency — 9141-BBE820', 0, 0, '2026-09-02 20:56:06'),
(161, 9, 18, 'escalation', 'Event escalated to your department', '9141-1933C3', 1, 0, '2026-09-02 20:56:41'),
(162, 1, 18, 'escalation', 'Event escalated', '9141-1933C3', 0, 0, '2026-09-02 20:56:41'),
(163, 3, 18, 'escalation', 'Event escalated', '9141-1933C3', 0, 0, '2026-09-02 20:56:41'),
(164, 9, 19, 'escalation', 'Event escalated to your department', '9141-8B2BB9', 1, 0, '2026-09-02 20:56:51'),
(165, 1, 19, 'escalation', 'Event escalated', '9141-8B2BB9', 0, 0, '2026-09-02 20:56:51'),
(166, 3, 19, 'escalation', 'Event escalated', '9141-8B2BB9', 0, 0, '2026-09-02 20:56:51'),
(167, 9, 20, 'escalation', 'Event escalated to your department', '9141-35C0E0', 1, 0, '2026-09-02 20:57:05'),
(168, 1, 20, 'escalation', 'Event escalated', '9141-35C0E0', 0, 0, '2026-09-02 20:57:05'),
(169, 3, 20, 'escalation', 'Event escalated', '9141-35C0E0', 0, 0, '2026-09-02 20:57:05'),
(170, 9, 21, 'escalation', 'Event escalated to your department', '9141-1DAEAA', 1, 0, '2026-09-02 20:57:18'),
(171, 1, 21, 'escalation', 'Event escalated', '9141-1DAEAA', 0, 0, '2026-09-02 20:57:18'),
(172, 3, 21, 'escalation', 'Event escalated', '9141-1DAEAA', 0, 0, '2026-09-02 20:57:18'),
(173, 9, 22, 'escalation', 'Event escalated to your department', '9141-BBE820', 1, 0, '2026-09-02 20:57:37'),
(174, 1, 22, 'escalation', 'Event escalated', '9141-BBE820', 0, 0, '2026-09-02 20:57:37'),
(175, 3, 22, 'escalation', 'Event escalated', '9141-BBE820', 0, 0, '2026-09-02 20:57:37'),
(176, 1, 22, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-BBE820 — Furameera', 0, 0, '2026-09-02 20:58:25'),
(177, 3, 22, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-BBE820 — Furameera', 0, 0, '2026-09-02 20:58:25'),
(178, 1, 21, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-1DAEAA — Furameera', 0, 0, '2026-09-02 20:58:36'),
(179, 3, 21, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-1DAEAA — Furameera', 0, 0, '2026-09-02 20:58:36'),
(180, 1, 20, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-35C0E0 — Furameera', 0, 0, '2026-09-02 20:58:49'),
(181, 3, 20, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-35C0E0 — Furameera', 0, 0, '2026-09-02 20:58:49'),
(182, 1, 19, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-8B2BB9 — Furameera', 0, 0, '2026-09-02 20:59:01'),
(183, 3, 19, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-8B2BB9 — Furameera', 0, 0, '2026-09-02 20:59:01'),
(184, 1, 18, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-1933C3 — Furameera', 0, 0, '2026-09-02 20:59:11'),
(185, 3, 18, 'status_update', 'Haalli Gabaasaa Haaromfame', '9141-1933C3 — Furameera', 0, 0, '2026-09-02 20:59:11'),
(186, 1, NULL, 'citizen_feedback', 'Yaada lammii (★★★★★)', '0904190352 · 9141-842137 · galatooma', 0, 0, '2026-09-02 21:01:13'),
(187, 2, NULL, 'citizen_feedback', 'Yaada lammii (★★★★★)', '0904190352 · 9141-842137 · galatooma', 0, 0, '2026-09-02 21:01:13'),
(188, 3, NULL, 'citizen_feedback', 'Yaada lammii (★★★★★)', '0904190352 · 9141-842137 · galatooma', 0, 0, '2026-09-02 21:01:13'),
(189, 1, NULL, 'citizen_help', 'Gargaarsa lammii', 'Hasan · 0904190352 — support', 1, 0, '2026-09-02 21:01:35'),
(190, 2, NULL, 'citizen_help', 'Gargaarsa lammii', 'Hasan · 0904190352 — support', 1, 0, '2026-09-02 21:01:35'),
(191, 3, NULL, 'citizen_help', 'Gargaarsa lammii', 'Hasan · 0904190352 — support', 1, 0, '2026-09-02 21:01:35'),
(192, 3, 16, 'citizen_dm', 'Ergaa lammii → Supervisor', 'Gameda · 0904190352 · 9141-A86EA9 — maaal irrr', 1, 0, '2026-09-02 21:04:55'),
(193, 1, NULL, 'citizen_help', 'Citizen help request', '0904190352 — ddzcdz', 1, 0, '2026-09-03 08:55:28'),
(194, 2, NULL, 'citizen_help', 'Citizen help request', '0904190352 — ddzcdz', 1, 0, '2026-09-03 08:55:28'),
(195, 3, NULL, 'citizen_help', 'Citizen help request', '0904190352 — ddzcdz', 1, 0, '2026-09-03 08:55:28');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_generations`
--

CREATE TABLE `report_generations` (
  `id` int(11) NOT NULL,
  `report_type` varchar(64) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `filters_json` text DEFAULT NULL,
  `file_path` varchar(512) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('description_word_limit', '150', '2026-09-02 08:26:21'),
('operator_alert_minutes', '5', '2026-09-02 08:26:21'),
('session_idle_minutes', '20', '2026-09-02 08:26:21'),
('sla_hours_critical', '1', '2026-09-02 08:26:21'),
('sla_hours_high', '4', '2026-09-02 08:26:21'),
('sla_hours_low', '72', '2026-09-02 08:26:21'),
('sla_hours_medium', '24', '2026-09-02 08:26:21'),
('sms_api_key', '', '2026-09-02 08:26:21'),
('sms_callback_url', '', '2026-09-02 08:26:21'),
('sms_enabled', '0', '2026-09-02 08:26:21'),
('sms_gateway_method', 'GET', '2026-09-02 08:26:21'),
('sms_gateway_url', '', '2026-09-02 08:26:21'),
('sms_identifier', '', '2026-09-02 08:26:21'),
('sms_provider', 'afromessage', '2026-09-02 08:26:21'),
('sms_sender_id', '9141', '2026-09-02 08:26:21');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `message_id` varchar(64) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `provider` varchar(32) NOT NULL DEFAULT 'afromessage',
  `raw_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stream_recordings`
--

CREATE TABLE `stream_recordings` (
  `id` int(11) NOT NULL,
  `camera_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `file_path` varchar(512) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `duration_sec` int(11) NOT NULL DEFAULT 60,
  `status` enum('recording','done','failed') NOT NULL DEFAULT 'recording',
  `pid` int(11) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_messages`
--

CREATE TABLE `supervisor_messages` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `direction` enum('to_public','to_supervisor') NOT NULL DEFAULT 'to_public',
  `supervisor_id` int(11) DEFAULT NULL,
  `supervisor_name` varchar(150) DEFAULT NULL,
  `citizen_name` varchar(150) DEFAULT NULL,
  `citizen_phone` varchar(32) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_messages`
--

INSERT INTO `supervisor_messages` (`id`, `event_id`, `direction`, `supervisor_id`, `supervisor_name`, `citizen_name`, `citizen_phone`, `message`, `is_read`, `created_at`) VALUES
(1, 16, 'to_supervisor', NULL, NULL, 'Gameda', '0904190352', 'maaal irrr', 0, '2026-09-02 21:04:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('administrator','operator','supervisor','department_officer','camera_operator') NOT NULL DEFAULT 'operator',
  `department_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `username`, `password_hash`, `role`, `department_id`, `status`, `failed_attempts`, `locked_until`, `created_at`) VALUES
(1, 'System Administrator', 'admin@adama.gov.et', '0904190352', 'admin', '$2y$10$4Kk90NYDEddSdQNrSiChUuiWXpVY3mX8Gq/4ULArBSq6KayLR9dHC', 'administrator', NULL, 'active', 0, NULL, '2026-09-02 08:26:19'),
(2, 'Call Operator', 'operator@adama.gov.et', '0911222222', 'Call Center Operator', '$2y$10$UU.EHb5MUGgIqT/1qsRJpu3pU8dsmCzGp3PkyMDcLCXqbGjwYqPIO', 'operator', NULL, 'active', 0, NULL, '2026-09-02 08:26:19'),
(3, 'Supervisor', 'super@adama.gov.et', '0911333333', 'supervisor', '$2y$10$gQOEzg3tCpyb8/eYAZDb7OWf4eRLgAxIxJLz2h3F4RGbZluV3iDK.', 'supervisor', NULL, 'active', 0, NULL, '2026-09-02 08:26:19'),
(4, 'Police', 'police@adama.gov.et', '0911444444', 'Police', '$2y$10$0XDafEvzoYjrRpktmZeUKuY1lth3T0iQd65/sEI3GlvzTr5PbqFbi', 'department_officer', 1, 'active', 0, NULL, '2026-09-02 08:26:19'),
(5, 'Camera Operator', 'camera@adama.gov.et', '0911555555', 'camera Control Room', '$2y$10$Oh9GMPshyR59l7pgWJpEqODb8vc7CWbMwW7uF.Ea0BT9qjIp1THZS', 'camera_operator', NULL, 'active', 0, NULL, '2026-09-02 08:26:19'),
(7, 'City Services', 'service@adama.gov.et', '0904190352', 'City Service', '$2y$10$wPc2y1/5ys0nIG3g5NzpXOwxDq53yhUK4bl.Ygw4RCsaW70oeNXCW', 'department_officer', 4, 'active', 0, NULL, '2026-09-02 08:59:00'),
(8, 'Fire & Emergency', 'fire@adama.gov.et', '0904190352', 'Fire & Emergency', '$2y$10$aYRmh7SlRBTtkvpdB8VtiuYUxOrnVUFEQiDwjpr9JF3x/EYAnbX0m', 'department_officer', 3, 'active', 0, NULL, '2026-09-02 09:00:52'),
(9, 'Traffic', 'traffic@adama.gov.et', '0904190352', 'Traffic', '$2y$10$PyWDLVKyv2GeWhrhkCCoOeaJSefYyllcKMTRlk0iXj0PlmAewWHNW', 'department_officer', 2, 'active', 0, NULL, '2026-09-02 09:01:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_act_created` (`created_at`),
  ADD KEY `idx_act_user` (`user_id`),
  ADD KEY `idx_act_action` (`action`);

--
-- Indexes for table `ai_detections`
--
ALTER TABLE `ai_detections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_event` (`event_id`),
  ADD KEY `idx_ai_att` (`attachment_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `cameras`
--
ALTER TABLE `cameras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cam_status` (`status`);

--
-- Indexes for table `camera_clips`
--
ALTER TABLE `camera_clips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clip_camera` (`camera_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`);

--
-- Indexes for table `citizen_feedback`
--
ALTER TABLE `citizen_feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `citizen_help`
--
ALTER TABLE `citizen_help`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `direct_messages`
--
ALTER TABLE `direct_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dm_sender` (`sender_id`),
  ADD KEY `idx_dm_receiver` (`receiver_id`),
  ADD KEY `idx_dm_pair` (`sender_id`,`receiver_id`),
  ADD KEY `idx_dm_unread` (`receiver_id`,`is_read`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_events_tracking` (`tracking_code`),
  ADD KEY `idx_events_status` (`status`),
  ADD KEY `idx_events_priority` (`priority`),
  ADD KEY `idx_events_category` (`category_id`),
  ADD KEY `idx_events_dept` (`assigned_department_id`),
  ADD KEY `idx_events_created` (`created_at`),
  ADD KEY `fk_events_operator` (`operator_id`);

--
-- Indexes for table `event_attachments`
--
ALTER TABLE `event_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_event` (`event_id`),
  ADD KEY `idx_att_type` (`file_type`);

--
-- Indexes for table `event_logs`
--
ALTER TABLE `event_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_event` (`event_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fb_user` (`user_id`);

--
-- Indexes for table `followups`
--
ALTER TABLE `followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fu_event` (`event_id`);

--
-- Indexes for table `help_requests`
--
ALTER TABLE `help_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_read` (`user_id`,`is_read`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `report_generations`
--
ALTER TABLE `report_generations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rg_user` (`generated_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_message_id` (`message_id`),
  ADD KEY `idx_sms_phone` (`phone`),
  ADD KEY `idx_sms_event` (`event_id`),
  ADD KEY `idx_sms_status` (`status`),
  ADD KEY `fk_sms_user` (`user_id`);

--
-- Indexes for table `stream_recordings`
--
ALTER TABLE `stream_recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rec_camera` (`camera_id`),
  ADD KEY `idx_rec_event` (`event_id`),
  ADD KEY `idx_rec_status` (`status`);

--
-- Indexes for table `supervisor_messages`
--
ALTER TABLE `supervisor_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sm_event` (`event_id`),
  ADD KEY `idx_sm_dir` (`direction`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_dept` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `ai_detections`
--
ALTER TABLE `ai_detections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cameras`
--
ALTER TABLE `cameras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `camera_clips`
--
ALTER TABLE `camera_clips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `citizen_feedback`
--
ALTER TABLE `citizen_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `citizen_help`
--
ALTER TABLE `citizen_help`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `direct_messages`
--
ALTER TABLE `direct_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `event_attachments`
--
ALTER TABLE `event_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `event_logs`
--
ALTER TABLE `event_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `followups`
--
ALTER TABLE `followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `help_requests`
--
ALTER TABLE `help_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_generations`
--
ALTER TABLE `report_generations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stream_recordings`
--
ALTER TABLE `stream_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supervisor_messages`
--
ALTER TABLE `supervisor_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_detections`
--
ALTER TABLE `ai_detections`
  ADD CONSTRAINT `fk_ai_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `event_attachments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ai_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `camera_clips`
--
ALTER TABLE `camera_clips`
  ADD CONSTRAINT `fk_clips_camera` FOREIGN KEY (`camera_id`) REFERENCES `cameras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_department` FOREIGN KEY (`assigned_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_attachments`
--
ALTER TABLE `event_attachments`
  ADD CONSTRAINT `fk_att_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_logs`
--
ALTER TABLE `event_logs`
  ADD CONSTRAINT `fk_logs_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `followups`
--
ALTER TABLE `followups`
  ADD CONSTRAINT `fk_fu_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_generations`
--
ALTER TABLE `report_generations`
  ADD CONSTRAINT `fk_rg_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `fk_sms_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sms_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stream_recordings`
--
ALTER TABLE `stream_recordings`
  ADD CONSTRAINT `fk_rec_camera` FOREIGN KEY (`camera_id`) REFERENCES `cameras` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rec_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
