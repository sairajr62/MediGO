-- ============================================================
--  Medicine Availability Checker & Reservation System
--  Database schema
--  Import this file in phpMyAdmin or via:
--      mysql -u root -p < medicine_checker.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `medicine_checker`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `medicine_checker`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  users
--  Holds every account: admin, pharmacy owner, and normal user
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150)  NOT NULL,
    `email`      VARCHAR(190)  NOT NULL,
    `phone`      VARCHAR(20)   DEFAULT NULL,
    `password`   VARCHAR(255)  NOT NULL,          -- password_hash() output
    `role`       ENUM('admin','pharmacy','user') NOT NULL DEFAULT 'user',
    `address`    VARCHAR(255)  DEFAULT NULL,
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  pharmacies
--  One-to-one with a user whose role = 'pharmacy'
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `pharmacies`;
CREATE TABLE `pharmacies` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `pharmacy_name`  VARCHAR(190) NOT NULL,
    `address`        VARCHAR(255) DEFAULT NULL,
    `phone`          VARCHAR(20)  DEFAULT NULL,
    `license_number` VARCHAR(100) DEFAULT NULL,
    `status`         ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pharmacy_user` (`user_id`),
    CONSTRAINT `fk_pharmacy_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  medicines
--  Belongs to a pharmacy (1 pharmacy : N medicines)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `medicines`;
CREATE TABLE `medicines` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pharmacy_id`  INT UNSIGNED NOT NULL,
    `name`         VARCHAR(190) NOT NULL,
    `generic_name` VARCHAR(190) DEFAULT NULL,
    `category`     VARCHAR(100) DEFAULT NULL,
    `description`  TEXT         DEFAULT NULL,
    `manufacturer` VARCHAR(150) DEFAULT NULL,
    `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity`     INT UNSIGNED NOT NULL DEFAULT 0,
    `expiry_date`  DATE         DEFAULT NULL,
    `image`        VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_medicine_pharmacy` (`pharmacy_id`),
    KEY `idx_medicine_name` (`name`),
    KEY `idx_medicine_generic` (`generic_name`),
    KEY `idx_medicine_category` (`category`),
    CONSTRAINT `fk_medicine_pharmacy`
        FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  reservations
--  Links a user, a medicine and its pharmacy
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `medicine_id` INT UNSIGNED NOT NULL,
    `pharmacy_id` INT UNSIGNED NOT NULL,
    `quantity`    INT UNSIGNED NOT NULL DEFAULT 1,
    `status`      ENUM('pending','confirmed','collected','cancelled','expired') NOT NULL DEFAULT 'pending',
    `reserved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_res_user` (`user_id`),
    KEY `idx_res_medicine` (`medicine_id`),
    KEY `idx_res_pharmacy` (`pharmacy_id`),
    KEY `idx_res_status` (`status`),
    CONSTRAINT `fk_res_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_res_medicine`
        FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_res_pharmacy`
        FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  No seed data is inserted here (schema only).
--
--  To create your first administrator account, run the helper
--  script once from your browser:
--
--      http://localhost/medicine_checker/database/create_admin.php
--
--  It hashes the password with password_hash() and then tells
--  you to delete the script. Normal users and pharmacies sign
--  up through register.php.
-- ============================================================
