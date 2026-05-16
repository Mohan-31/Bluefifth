-- ============================================================
-- OTP Migration — Phone-first authentication system
-- Run this in phpMyAdmin or MySQL CLI before deploying.
-- Database: ecommerce_referral_db
-- ============================================================

USE ecommerce_referral_db;

-- ============================================================
-- Table: otp_verifications
-- Tracks OTP send/verify state. The OTP code itself lives on
-- MSG91's side. We only record verification outcomes and
-- enforce rate limits locally.
-- ============================================================
CREATE TABLE IF NOT EXISTS otp_verifications (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    phone       VARCHAR(15)  NOT NULL,
    purpose     ENUM('checkout','profile') NOT NULL DEFAULT 'checkout',
    is_verified TINYINT(1)   NOT NULL DEFAULT 0,
    attempts    INT          NOT NULL DEFAULT 0,
    expires_at  DATETIME     NOT NULL,
    verified_at DATETIME     NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_purpose  (phone, purpose),
    INDEX idx_expires        (expires_at),
    INDEX idx_phone_verified (phone, is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: customer_addresses
-- Saved delivery addresses per user.  Supports multiple
-- addresses and a default flag for one-click repeat checkout.
-- ============================================================
CREATE TABLE IF NOT EXISTS customer_addresses (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    label        VARCHAR(50)  NOT NULL DEFAULT 'Home',
    full_name    VARCHAR(200) NOT NULL DEFAULT '',
    phone        VARCHAR(15)  NOT NULL DEFAULT '',
    email        VARCHAR(200) NOT NULL DEFAULT '',
    address_line TEXT         NOT NULL,
    apartment    VARCHAR(100) NOT NULL DEFAULT '',
    city         VARCHAR(100) NOT NULL,
    state        VARCHAR(100) NOT NULL,
    pincode      VARCHAR(10)  NOT NULL,
    is_default   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_default   (user_id, is_default),
    INDEX idx_user_id        (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Alter users table
-- Phone becomes the primary customer identity (unique index).
-- google_id column is kept but is no longer required.
-- ============================================================

-- Add unique index on phone if it does not already exist
-- (safe to run multiple times — IF NOT EXISTS guard)
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND INDEX_NAME   = 'idx_phone_unique'
);

SET @sql = IF(
    @idx_exists = 0,
    'ALTER TABLE users ADD UNIQUE INDEX idx_phone_unique (phone)',
    'SELECT "Index idx_phone_unique already exists, skipping" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- Done. Next step: run the app and test OTP flow in mock mode
-- (use OTP 123456 for any number while MSG91_AUTH_KEY = 'dev')
-- ============================================================
