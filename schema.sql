-- ============================================================
-- Adama City Administration — Call 9141
-- Full database schema (fresh install)
-- Database name: callcenter9141  (or 9141 — match config.php)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `callcenter9141`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `callcenter9141`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- categories (4 problem types)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `icon` VARCHAR(64)  DEFAULT NULL,
  `slug` VARCHAR(64)  DEFAULT NULL,
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`id`, `name`, `icon`, `slug`) VALUES
  (1, 'Al-seerummaa / Illegal',     '⚖️', 'illegal'),
  (2, 'Rakkoo Nageenyaa / Security','🛡️', 'security'),
  (3, 'Rakkoo Tajaajila / Service', '🛠️', 'service'),
  (4, 'Balaa Tasaa / Emergency',    '🚨', 'emergency')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- departments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(150) NOT NULL,
  `contact_phone` VARCHAR(32)  DEFAULT NULL,
  `contact_email` VARCHAR(150) DEFAULT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `departments` (`name`, `contact_phone`, `contact_email`) VALUES
  ('Police / Poolisii',        '0911000001', 'police@adama.gov.et'),
  ('Traffic / Trafikaa',       '0911000002', 'traffic@adama.gov.et'),
  ('Fire & Emergency',         '0911000003', 'fire@adama.gov.et'),
  ('City Services / Tajaajila','0911000004', 'services@adama.gov.et')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- users
-- Roles: administrator, operator, supervisor,
--        department_officer, camera_operator
-- Default password for all seed users: Admin@9141
-- (change after first login)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `full_name`        VARCHAR(150) NOT NULL,
  `email`            VARCHAR(150) DEFAULT NULL,
  `phone`            VARCHAR(32)  DEFAULT NULL,
  `username`         VARCHAR(80)  NOT NULL,
  `password_hash`    VARCHAR(255) NOT NULL,
  `role`             ENUM('administrator','operator','supervisor','department_officer','camera_operator')
                       NOT NULL DEFAULT 'operator',
  `department_id`    INT DEFAULT NULL,
  `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `failed_attempts`  INT NOT NULL DEFAULT 0,
  `locked_until`     DATETIME DEFAULT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_dept` (`department_id`),
  CONSTRAINT `fk_users_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password for ALL seed users = Admin@9141  (bcrypt)
INSERT INTO `users` (`full_name`, `email`, `phone`, `username`, `password_hash`, `role`, `department_id`, `status`) VALUES
  ('System Administrator', 'admin@adama.gov.et',  '0911111111', 'admin',
   '$2y$10$IMRZ8pvYgy6N1JPazYQrKOMnu6z2ST73O2aP62NEXEAbHGTxnrkkS', 'administrator', NULL, 'active'),
  ('Call Operator',        'operator@adama.gov.et','0911222222', 'operator',
   '$2y$10$IMRZ8pvYgy6N1JPazYQrKOMnu6z2ST73O2aP62NEXEAbHGTxnrkkS', 'operator', NULL, 'active'),
  ('Supervisor',           'super@adama.gov.et',   '0911333333', 'supervisor',
   '$2y$10$IMRZ8pvYgy6N1JPazYQrKOMnu6z2ST73O2aP62NEXEAbHGTxnrkkS', 'supervisor', NULL, 'active'),
  ('Dept Officer',         'dept@adama.gov.et',    '0911444444', 'officer',
   '$2y$10$IMRZ8pvYgy6N1JPazYQrKOMnu6z2ST73O2aP62NEXEAbHGTxnrkkS', 'department_officer', 1, 'active'),
  ('Camera Operator',      'camera@adama.gov.et',  '0911555555', 'camera',
   '$2y$10$IMRZ8pvYgy6N1JPazYQrKOMnu6z2ST73O2aP62NEXEAbHGTxnrkkS', 'camera_operator', NULL, 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ------------------------------------------------------------
-- events (main incidents / taateewwan)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id`                      INT AUTO_INCREMENT PRIMARY KEY,
  `tracking_code`           VARCHAR(32)  NOT NULL,
  `category_id`             INT DEFAULT NULL,
  `caller_name`             VARCHAR(150) DEFAULT NULL,
  `caller_phone`            VARCHAR(32)  DEFAULT NULL,
  `gender`                  ENUM('male','female','unspecified') DEFAULT 'unspecified',
  `address`                 VARCHAR(255) DEFAULT NULL,
  `location`                VARCHAR(255) DEFAULT NULL,
  `latitude`                DECIMAL(10,7) DEFAULT NULL,
  `longitude`               DECIMAL(10,7) DEFAULT NULL,
  `description`             TEXT,
  `priority`                ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status`                  ENUM('new','assigned','ongoing','solved','unsolved') NOT NULL DEFAULT 'new',
  `assigned_department_id`  INT DEFAULT NULL,
  `operator_id`             INT DEFAULT NULL,
  `source`                  VARCHAR(64)  DEFAULT 'web',
  `channel`                 VARCHAR(32)  DEFAULT 'web',
  `response_time_minutes`   INT DEFAULT NULL,
  `resolved_at`             DATETIME DEFAULT NULL,
  `stale_alert_sent`        TINYINT(1) NOT NULL DEFAULT 0,
  `satisfaction_rating`     TINYINT DEFAULT NULL,
  `satisfaction_comment`    TEXT DEFAULT NULL,
  `created_at`              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_events_tracking` (`tracking_code`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_priority` (`priority`),
  KEY `idx_events_category` (`category_id`),
  KEY `idx_events_dept` (`assigned_department_id`),
  KEY `idx_events_created` (`created_at`),
  CONSTRAINT `fk_events_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_department`
    FOREIGN KEY (`assigned_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_operator`
    FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- event_attachments (uploads / media)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_attachments` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `event_id`      INT NOT NULL,
  `file_path`     VARCHAR(512) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_type`     VARCHAR(32)  NOT NULL DEFAULT 'document',
  `file_size`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_att_event` (`event_id`),
  KEY `idx_att_type` (`file_type`),
  CONSTRAINT `fk_att_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- event_logs (audit trail)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `event_id`   INT NOT NULL,
  `action`     VARCHAR(64)  NOT NULL,
  `note`       TEXT DEFAULT NULL,
  `changed_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_logs_event` (`event_id`),
  CONSTRAINT `fk_logs_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- followups
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `followups` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `event_id`      INT NOT NULL,
  `followup_date` DATE DEFAULT NULL,
  `remarks`       TEXT,
  `status`        VARCHAR(32) DEFAULT 'pending',
  `created_by`    INT DEFAULT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_fu_event` (`event_id`),
  CONSTRAINT `fk_fu_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `event_id`   INT DEFAULT NULL,
  `type`       VARCHAR(64)  DEFAULT 'info',
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT,
  `is_urgent`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- feedback (staff)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `message`    TEXT NOT NULL,
  `rating`     TINYINT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_fb_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- settings (key/value)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('sla_hours_critical',      '1'),
  ('sla_hours_high',          '4'),
  ('sla_hours_medium',        '24'),
  ('sla_hours_low',           '72'),
  ('description_word_limit',  '150'),
  ('session_idle_minutes',    '20'),
  ('operator_alert_minutes',  '5'),
  ('sms_enabled',             '0'),
  ('sms_gateway_url',         ''),
  ('sms_gateway_method',      'GET'),
  ('sms_api_key',             ''),
  ('sms_sender_id',           '9141'),
  ('sms_provider',            'afromessage'),
  ('sms_identifier',          ''),
  ('sms_callback_url',        '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ------------------------------------------------------------
-- cameras (Room Camera / live streams)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cameras` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(120) NOT NULL,
  `location`    VARCHAR(255) DEFAULT NULL,
  `stream_url`  TEXT DEFAULT NULL,
  `stream_type` ENUM('hls','http','rtsp','mjpeg','webrtc') NOT NULL DEFAULT 'hls',
  `status`      VARCHAR(32) NOT NULL DEFAULT 'online',
  `latitude`    DECIMAL(10,7) DEFAULT NULL,
  `longitude`   DECIMAL(10,7) DEFAULT NULL,
  `notes`       TEXT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_cam_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- camera_clips (optional recordings linked to cameras)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `camera_clips` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `camera_id`    INT NOT NULL,
  `file_path`    VARCHAR(512) NOT NULL,
  `duration_sec` INT DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_clip_camera` (`camera_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- stream_recordings (live capture jobs)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stream_recordings` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `camera_id`     INT DEFAULT NULL,
  `event_id`      INT DEFAULT NULL,
  `file_path`     VARCHAR(512) NOT NULL,
  `location`      VARCHAR(255) DEFAULT NULL,
  `duration_sec`  INT NOT NULL DEFAULT 60,
  `status`        ENUM('recording','done','failed') NOT NULL DEFAULT 'recording',
  `pid`           INT DEFAULT NULL,
  `started_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `finished_at`   TIMESTAMP NULL,
  `error_message` TEXT,
  KEY `idx_rec_camera` (`camera_id`),
  KEY `idx_rec_event` (`event_id`),
  KEY `idx_rec_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ai_detections
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_detections` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `event_id`         INT NOT NULL,
  `attachment_id`    INT DEFAULT NULL,
  `model`            VARCHAR(64) NOT NULL DEFAULT 'adama-local-v1',
  `summary`          TEXT,
  `detections_json`  JSON,
  `frames_analyzed`  INT DEFAULT 0,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ai_event` (`event_id`),
  KEY `idx_ai_att` (`attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- report_generations (optional history of CSV/report exports)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_generations` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `report_type`   VARCHAR(64) DEFAULT NULL,
  `generated_by`  INT DEFAULT NULL,
  `filters_json`  TEXT DEFAULT NULL,
  `file_path`     VARCHAR(512) DEFAULT NULL,
  `generated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rg_user` (`generated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ------------------------------------------------------------
-- sms_logs (AfroMessage delivery tracking)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`     INT DEFAULT NULL,
  `user_id`      INT DEFAULT NULL,
  `phone`        VARCHAR(32)  NOT NULL,
  `message_id`   VARCHAR(64)  DEFAULT NULL,
  `message`      TEXT         NOT NULL,
  `status`       VARCHAR(32)  NOT NULL DEFAULT 'pending',
  `provider`     VARCHAR(32)  NOT NULL DEFAULT 'afromessage',
  `raw_response` TEXT,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sms_message_id` (`message_id`),
  KEY `idx_sms_phone` (`phone`),
  KEY `idx_sms_event` (`event_id`),
  KEY `idx_sms_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONE
-- Import:
--   mysql -u root -p < schema.sql
-- Or phpMyAdmin → Import → schema.sql
--
-- config.php:
--   define('DB_NAME', 'callcenter9141');  // or '9141' if you rename DB
--
-- Default logins (password for all = Admin@9141) — change after first login:
--   admin      / Admin@9141   → administrator
--   operator   / Admin@9141   → operator
--   supervisor / Admin@9141   → supervisor
--   officer    / Admin@9141   → department_officer
--   camera     / Admin@9141   → camera_operator
-- ============================================================
