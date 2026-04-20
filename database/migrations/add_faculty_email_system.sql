-- Migration: Add Faculty Registration & Email Verification System
-- Date: 2026-04-19
-- Description: Adds faculty table, email OTP system, and email verification for students/cashiers

-- ============================================================
-- 1. Create faculty table
-- ============================================================
CREATE TABLE IF NOT EXISTS `faculty` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `faculty_id_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'bcrypt hash',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `id_declaration` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Faculty agreed to ID declaration',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `faculty_id_no` (`faculty_id_no`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Create email_otps table (reusable for students, faculty, cashiers)
-- ============================================================
CREATE TABLE IF NOT EXISTS `email_otps` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` enum('student','faculty','cashier') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` enum('verification','password_reset') COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `attempts` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`user_type`, `user_id`, `purpose`),
  KEY `idx_otp_expires` (`expires_at`),
  KEY `idx_otp_email` (`email`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Add email verification columns to students table
-- ============================================================
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `email_verified` tinyint(1) NOT NULL DEFAULT '0' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `email_verified_at` datetime DEFAULT NULL AFTER `email_verified`;

-- ============================================================
-- 4. Add email and verification columns to cashiers table
-- ============================================================
ALTER TABLE `cashiers`
  ADD COLUMN IF NOT EXISTS `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `username`,
  ADD COLUMN IF NOT EXISTS `email_verified` tinyint(1) NOT NULL DEFAULT '0' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `email_verified_at` datetime DEFAULT NULL AFTER `email_verified`;

-- Add unique constraint on email (only for non-null emails)
ALTER TABLE `cashiers`
  ADD UNIQUE KEY `email` (`email`);

-- ============================================================
-- 5. Update audit_log actor_type enum to include faculty
-- ============================================================
ALTER TABLE `audit_log`
  MODIFY `actor_type` enum('admin','cashier','student','faculty') COLLATE utf8mb4_unicode_ci NOT NULL;

-- ============================================================
-- 6. Update orders table to support faculty (optional)
-- ============================================================
-- Orders can now be linked to faculty as well
ALTER TABLE `orders`
  MODIFY `student_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL for walk-in, can also reference faculty';

-- ============================================================
-- 7. Grandfather existing users - mark them as verified
-- ============================================================
-- Students who registered before email verification was required
UPDATE `students`
SET `email_verified` = 1,
    `email_verified_at` = `created_at`
WHERE `is_active` = 1
  AND `email_verified` = 0;

-- Cashiers don't need email to be verified immediately (email is optional for them)
-- But if they have an email, mark existing ones as verified
UPDATE `cashiers`
SET `email_verified` = 1,
    `email_verified_at` = `created_at`
WHERE `email` IS NOT NULL
  AND `email` != ''
  AND `email_verified` = 0;

-- ============================================================
-- 8. Clean up expired OTPs periodically (optional cleanup query)
-- ============================================================
-- Run this periodically via cron or scheduled task:
-- DELETE FROM `email_otps` WHERE `expires_at` < NOW();