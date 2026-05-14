-- ============================================================
-- BLUEFIFTH E-COMMERCE — COMPLETE DATABASE MIGRATION
-- phpMyAdmin-safe: can be imported on a fresh or existing DB.
-- Every statement uses IF NOT EXISTS / IF EXISTS so re-running
-- is always safe.
--
-- HOW TO IMPORT IN phpMyAdmin
--   1. Open phpMyAdmin → select database "ecommerce_referral_db"
--      (create it first if it doesn't exist)
--   2. Click "Import" tab → choose this file → click "Go"
--   3. After import, visit http://localhost/ecommerce-project/
--      setup-admin-password.php to set the admin bcrypt password
--   4. Delete setup-admin-password.php from the server
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET NAMES utf8mb4;

-- ============================================================
-- CREATE DATABASE (skip if already exists)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `ecommerce_referral_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `ecommerce_referral_db`;

-- ============================================================
-- TABLE: admin_users
-- ============================================================

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100)  NOT NULL,
  `email`         VARCHAR(255)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `full_name`     VARCHAR(255)  DEFAULT NULL,
  `role`          ENUM('super_admin','admin','editor') DEFAULT 'admin',
  `permissions`   LONGTEXT      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                  CHECK (json_valid(`permissions`)),
  `last_login`    TIMESTAMP     NULL DEFAULT NULL,
  `is_active`     TINYINT(1)    DEFAULT 1,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: username=admin, password set by setup-admin-password.php
INSERT IGNORE INTO `admin_users`
  (`id`,`username`,`email`,`password_hash`,`full_name`,`role`,`is_active`)
VALUES
  (1,'admin','admin@bluefifth.in','NEEDS_RESET','System Administrator','super_admin',1);

-- ============================================================
-- TABLE: users
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `name`                VARCHAR(100)  NOT NULL,
  `email`               VARCHAR(150)  NOT NULL,
  `phone`               VARCHAR(20)   DEFAULT NULL,
  `address`             TEXT          DEFAULT NULL,
  `city`                VARCHAR(100)  DEFAULT NULL,
  `state`               VARCHAR(100)  DEFAULT NULL,
  `pincode`             VARCHAR(10)   DEFAULT NULL,
  `user_type`           ENUM('registered','guest') NOT NULL DEFAULT 'registered',
  `google_id`           VARCHAR(100)  DEFAULT NULL,
  `profile_image`       TEXT          DEFAULT NULL,
  `welcome_email_sent`  TINYINT(1)    DEFAULT 0,
  `kyc_status`          ENUM('not_submitted','pending','verified','rejected') DEFAULT 'not_submitted',
  `pan_number`          VARCHAR(20)   DEFAULT NULL,
  `aadhar_number`       VARCHAR(20)   DEFAULT NULL,
  `bank_account_number` VARCHAR(50)   DEFAULT NULL,
  `ifsc_code`           VARCHAR(20)   DEFAULT NULL,
  `upi_id`              VARCHAR(100)  DEFAULT NULL,
  `aadhar_front_path`   VARCHAR(500)  DEFAULT NULL,
  `aadhar_back_path`    VARCHAR(500)  DEFAULT NULL,
  `pan_front_path`      VARCHAR(500)  DEFAULT NULL,
  `pan_back_path`       VARCHAR(500)  DEFAULT NULL,
  `wallet_balance`      DECIMAL(10,2) DEFAULT 0.00,
  `referral_code`       VARCHAR(20)   DEFAULT NULL,
  `referred_by`         VARCHAR(20)   DEFAULT NULL,
  `status`              ENUM('active','inactive') DEFAULT 'active',
  `last_login`          TIMESTAMP     NULL DEFAULT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_google_id`   (`google_id`),
  KEY `idx_user_type`   (`user_type`),
  KEY `idx_kyc_status`  (`kyc_status`),
  KEY `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: categories
-- ============================================================

CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `slug`        VARCHAR(255) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `image`       VARCHAR(500) DEFAULT NULL,
  `status`      ENUM('active','inactive') DEFAULT 'active',
  `sort_order`  INT(11)      DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `categories` (`id`,`name`,`slug`,`description`,`status`,`sort_order`) VALUES
(1, 'Basics',         'basics',         'Essential clothing items for everyday wear', 'active', 1),
(2, 'Premium',        'premium',        'High-quality premium collection',             'active', 2),
(3, 'Seasonal',       'seasonal',       'Seasonal collection items',                   'active', 3),
(4, 'Limited Edition','limited-edition','Exclusive limited edition pieces',            'active', 4),
(5, 'Luxury',         'luxury',         'Premium luxury products',                     'active', 5);

-- ============================================================
-- TABLE: products
-- ============================================================

CREATE TABLE IF NOT EXISTS `products` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `category_id`         INT(11)       NOT NULL,
  `name`                VARCHAR(255)  NOT NULL,
  `slug`                VARCHAR(255)  NOT NULL,
  `description`         TEXT          DEFAULT NULL,
  `main_image`          VARCHAR(500)  DEFAULT NULL,
  `product_image`       VARCHAR(500)  DEFAULT NULL,
  `image_gallery`       TEXT          DEFAULT NULL,
  `image`               VARCHAR(500)  DEFAULT NULL,
  `care_instructions`   TEXT          DEFAULT NULL,
  `price`               DECIMAL(10,2) NOT NULL,
  `stock_quantity`      INT(11)       DEFAULT 0,
  `low_stock_threshold` INT(11)       DEFAULT 10,
  `sizes`               LONGTEXT      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                        CHECK (json_valid(`sizes`)),
  `status`              ENUM('active','inactive','out_of_stock') DEFAULT 'active',
  `featured`            TINYINT(1)    DEFAULT 0,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category`    (`category_id`),
  KEY `idx_status`      (`status`),
  KEY `idx_featured`    (`featured`),
  KEY `idx_price`       (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: product_images
-- ============================================================

CREATE TABLE IF NOT EXISTS `product_images` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `product_id`  INT(11)      NOT NULL,
  `image_url`   VARCHAR(500) NOT NULL,
  `alt_text`    VARCHAR(255) DEFAULT NULL,
  `sort_order`  INT(11)      DEFAULT 0,
  `is_primary`  TINYINT(1)   DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: product_reviews
-- ============================================================

CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `product_id`     INT(11)      NOT NULL,
  `user_id`        INT(11)      DEFAULT NULL,
  `customer_name`  VARCHAR(255) DEFAULT NULL,
  `customer_email` VARCHAR(255) DEFAULT NULL,
  `rating`         TINYINT(1)   NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `review_text`    TEXT         DEFAULT NULL,
  `status`         ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: cart
-- ============================================================

CREATE TABLE IF NOT EXISTS `cart` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)   NOT NULL,
  `product_id` INT(11)   NOT NULL,
  `quantity`   INT(11)   NOT NULL DEFAULT 1,
  `size`       VARCHAR(10) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`,`size`),
  KEY `idx_user`    (`user_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: coupons  ← WAS COMPLETELY MISSING
-- ============================================================

CREATE TABLE IF NOT EXISTS `coupons` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `code`                VARCHAR(50)   NOT NULL,
  `discount_percentage` DECIMAL(5,2)  NOT NULL COMMENT 'Percentage off order total (1-99)',
  `description`         VARCHAR(500)  DEFAULT NULL,
  `usage_limit`         INT(11)       DEFAULT NULL COMMENT 'NULL = unlimited uses',
  `used_count`          INT(11)       NOT NULL DEFAULT 0,
  `is_active`           TINYINT(1)    NOT NULL DEFAULT 1,
  `expires_at`          DATETIME      DEFAULT NULL COMMENT 'NULL = never expires',
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code`   (`code`),
  KEY `idx_is_active`        (`is_active`),
  KEY `idx_expires_at`       (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo coupon to test immediately
INSERT IGNORE INTO `coupons` (`code`,`discount_percentage`,`description`,`usage_limit`,`is_active`,`expires_at`)
VALUES ('WELCOME10', 10.00, '10% off — welcome coupon', 100, 1, DATE_ADD(NOW(), INTERVAL 1 YEAR));

-- ============================================================
-- TABLE: orders  (+ missing coupon/combo columns)
-- ============================================================

CREATE TABLE IF NOT EXISTS `orders` (
  `id`                        INT(11)       NOT NULL AUTO_INCREMENT,
  `order_number`              VARCHAR(50)   NOT NULL,
  `user_id`                   INT(11)       DEFAULT NULL,
  `total_amount`              DECIMAL(10,2) NOT NULL,
  `tax_amount`                DECIMAL(10,2) DEFAULT 0.00,
  `shipping_amount`           DECIMAL(10,2) DEFAULT 0.00,
  `wallet_points_used`        DECIMAL(10,2) DEFAULT 0.00,
  `coupon_code`               VARCHAR(50)   DEFAULT NULL,
  `coupon_discount_percentage` DECIMAL(5,2) DEFAULT NULL,
  `coupon_discount_amount`    DECIMAL(10,2) DEFAULT NULL,
  `is_combo_applied`          TINYINT(1)    DEFAULT 0,
  `combo_savings`             DECIMAL(10,2) DEFAULT 0.00,
  `combo_type`                VARCHAR(50)   DEFAULT NULL,
  `final_amount`              DECIMAL(10,2) NOT NULL,
  `status`                    ENUM('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `payment_status`            ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  `payment_method`            VARCHAR(50)   DEFAULT NULL,
  `razorpay_payment_id`       VARCHAR(100)  DEFAULT NULL,
  `razorpay_order_id`         VARCHAR(100)  DEFAULT NULL,
  `shipping_address`          LONGTEXT      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                              CHECK (json_valid(`shipping_address`)),
  `billing_address`           LONGTEXT      CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
                              CHECK (json_valid(`billing_address`)),
  `referral_code`             VARCHAR(10)   DEFAULT NULL,
  `notes`                     TEXT          DEFAULT NULL,
  `shiprocket_order_id`       VARCHAR(50)   DEFAULT NULL,
  `shiprocket_shipment_id`    VARCHAR(50)   DEFAULT NULL,
  `tracking_number`           VARCHAR(100)  DEFAULT NULL,
  `estimated_delivery`        DATE          DEFAULT NULL,
  `courier_partner`           VARCHAR(100)  DEFAULT NULL,
  `shipping_method`           VARCHAR(50)   DEFAULT 'standard',
  `created_at`                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number`               (`order_number`),
  KEY `idx_user`                          (`user_id`),
  KEY `idx_status`                        (`status`),
  KEY `idx_payment_status`                (`payment_status`),
  KEY `idx_referral`                      (`referral_code`),
  KEY `idx_coupon_code`                   (`coupon_code`),
  KEY `idx_created`                       (`created_at`),
  KEY `idx_shiprocket_shipment_id`        (`shiprocket_shipment_id`),
  KEY `idx_tracking_number`               (`tracking_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: order_items
-- ============================================================

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `order_id`      INT(11)       NOT NULL,
  `product_id`    INT(11)       NOT NULL,
  `product_name`  VARCHAR(255)  NOT NULL,
  `product_price` DECIMAL(10,2) NOT NULL,
  `quantity`      INT(11)       NOT NULL,
  `size`          VARCHAR(10)   DEFAULT NULL,
  `total_price`   DECIMAL(10,2) NOT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order`   (`order_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: order_tracking
-- ============================================================

CREATE TABLE IF NOT EXISTS `order_tracking` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `order_id`            INT(11)      NOT NULL,
  `status`              VARCHAR(50)  NOT NULL,
  `description`         TEXT         DEFAULT NULL,
  `location`            VARCHAR(200) DEFAULT NULL,
  `courier_update_time` DATETIME     DEFAULT NULL,
  `updated_by`          VARCHAR(100) DEFAULT 'system',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id`  (`order_id`),
  KEY `idx_status`    (`status`),
  KEY `idx_created_at`(`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: wallet
-- ============================================================

CREATE TABLE IF NOT EXISTS `wallet` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)       NOT NULL,
  `points`         DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Available points (1 pt = ₹1)',
  `pending_points` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Points awaiting 7-day hold',
  `total_earned`   DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Lifetime points earned',
  `total_claimed`  DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total money paid out',
  `last_claim_date` DATE         DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: wallet_transactions
-- ============================================================

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `wallet_id`        INT(11)       NOT NULL,
  `points`           DECIMAL(10,2) NOT NULL COMMENT 'Positive=credit, Negative=debit',
  `tax_deducted`     DECIMAL(10,2) DEFAULT 0.00 COMMENT 'TDS amount deducted',
  `net_credited`     DECIMAL(10,2) DEFAULT NULL COMMENT 'Points after TDS',
  `transaction_type` ENUM('earned','used','claimed','bonus') NOT NULL,
  `reference_id`     INT(11)       DEFAULT NULL COMMENT 'FK to referral_purchases or claims',
  `description`      TEXT          DEFAULT NULL,
  `claimed_amount`   DECIMAL(10,2) DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_id`        (`wallet_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_created_at`       (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: referrals
-- ============================================================

CREATE TABLE IF NOT EXISTS `referrals` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11)       NOT NULL,
  `code`            VARCHAR(20)   NOT NULL,
  `link`            TEXT          NOT NULL,
  `visit_count`     INT(11)       DEFAULT 0,
  `purchase_count`  INT(11)       DEFAULT 0,
  `total_earnings`  DECIMAL(10,2) DEFAULT 0.00,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code`    (`code`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: referral_visits
-- ============================================================

CREATE TABLE IF NOT EXISTS `referral_visits` (
  `id`          INT(11)    NOT NULL AUTO_INCREMENT,
  `referral_id` INT(11)    NOT NULL,
  `visitor_ip`  VARCHAR(45) DEFAULT NULL,
  `visited_at`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_id` (`referral_id`),
  KEY `idx_visited_at`  (`visited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: referral_purchases
-- ============================================================

CREATE TABLE IF NOT EXISTS `referral_purchases` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `referral_id`     INT(11)       NOT NULL,
  `order_id`        VARCHAR(50)   NOT NULL,
  `amount`          DECIMAL(10,2) NOT NULL,
  `points_earned`   DECIMAL(10,2) NOT NULL,
  `purchase_month`  INT(11)       NOT NULL DEFAULT 1 COMMENT 'Month number since referral was created',
  `earning_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 5.00 COMMENT 'Commission rate used (%)',
  `status`          ENUM('credited','claimed','paid') DEFAULT 'credited',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_id` (`referral_id`),
  KEY `idx_order_id`    (`order_id`),
  KEY `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: claims
-- ============================================================

CREATE TABLE IF NOT EXISTS `claims` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)       NOT NULL,
  `points_claimed` DECIMAL(10,2) NOT NULL,
  `money_value`    DECIMAL(10,2) NOT NULL COMMENT '1 point = ₹1',
  `status`         ENUM('pending','processed','rejected') DEFAULT 'pending',
  `admin_notes`    TEXT          DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at`   TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_status`  (`user_id`,`status`),
  KEY `idx_status_date`  (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_monthly_earnings
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_monthly_earnings` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11)       NOT NULL,
  `purchase_month`  INT(11)       NOT NULL,
  `earning_rate`    DECIMAL(5,2)  NOT NULL,
  `purchase_count`  INT(11)       DEFAULT 0,
  `month_sales`     DECIMAL(10,2) DEFAULT 0.00,
  `month_points`    DECIMAL(10,2) DEFAULT 0.00,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: email_notifications
-- ============================================================

CREATE TABLE IF NOT EXISTS `email_notifications` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`          INT(11)      NOT NULL,
  `email_type`       VARCHAR(50)  NOT NULL DEFAULT '',
  `campaign_id`      VARCHAR(100) DEFAULT NULL,
  `subject`          VARCHAR(255) NOT NULL,
  `message`          TEXT         NOT NULL,
  `sent_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`           ENUM('sent','failed') DEFAULT 'sent',
  `recipient_count`  INT(11)      DEFAULT 1,
  `delivery_status`  ENUM('pending','sent','delivered','failed','bounced') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_user_type`       (`user_id`,`email_type`),
  KEY `idx_sent_at`         (`sent_at`),
  KEY `idx_campaign_id`     (`campaign_id`),
  KEY `idx_delivery_status` (`delivery_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: email_unsubscribes
-- ============================================================

CREATE TABLE IF NOT EXISTS `email_unsubscribes` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `email`             VARCHAR(255) NOT NULL,
  `user_id`           INT(11)      DEFAULT NULL,
  `unsubscribe_token` VARCHAR(255) NOT NULL,
  `unsubscribed_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason`            VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token`   (`unsubscribe_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: bulk_email_campaigns
-- ============================================================

CREATE TABLE IF NOT EXISTS `bulk_email_campaigns` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `campaign_name`       VARCHAR(255) NOT NULL,
  `subject`             VARCHAR(500) NOT NULL,
  `message`             LONGTEXT     NOT NULL,
  `recipient_groups`    LONGTEXT     CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
                        CHECK (json_valid(`recipient_groups`)),
  `total_recipients`    INT(11)      DEFAULT 0,
  `emails_sent`         INT(11)      DEFAULT 0,
  `emails_delivered`    INT(11)      DEFAULT 0,
  `emails_failed`       INT(11)      DEFAULT 0,
  `attachment_path`     VARCHAR(500) DEFAULT NULL,
  `created_by_admin_id` INT(11)      NOT NULL,
  `status`              ENUM('draft','sending','completed','failed') DEFAULT 'draft',
  `started_at`          TIMESTAMP    NULL DEFAULT NULL,
  `completed_at`        TIMESTAMP    NULL DEFAULT NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`     (`status`),
  KEY `idx_created_by` (`created_by_admin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: settings
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`         VARCHAR(100) NOT NULL,
  `setting_value`       TEXT         DEFAULT NULL,
  `setting_type`        ENUM('string','number','boolean','json') DEFAULT 'string',
  `setting_description` TEXT         DEFAULT NULL,
  `description`         TEXT         DEFAULT NULL,
  `is_editable`         TINYINT(1)   DEFAULT 1,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core settings (INSERT IGNORE so re-running won't overwrite)
INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`,`setting_type`,`description`) VALUES
('site_name',               'bluefifth',         'string',  'Website name'),
('site_description',        'Premium clothing',  'string',  'Website description'),
('currency',                'INR',               'string',  'Default currency'),
('currency_symbol',         '₹',                 'string',  'Currency symbol'),
('min_order_amount',        '500',               'number',  'Minimum order amount'),
('shipping_charge',         '0',                 'number',  'Default shipping charge'),
('free_shipping_threshold', '500',               'number',  'Free shipping above this amount'),
('low_stock_alert',         '10',                'number',  'Low stock alert threshold'),
('order_number_prefix',     'VLN',               'string',  'Order number prefix'),
('razorpay_key_id',         '',                  'string',  'Razorpay Key ID'),
('razorpay_key_secret',     '',                  'string',  'Razorpay Key Secret'),
('razorpay_mode',           'test',              'string',  'test or live'),
('enable_cod',              'true',              'boolean', 'Enable Cash on Delivery'),
('cod_charges',             '50',                'number',  'COD extra charge'),
('tax_rate',                '5',                 'number',  'Tax percentage'),
('email_notifications',     'true',              'boolean', 'Enable email notifications'),
('maintenance_mode',        'false',             'boolean', 'Maintenance mode'),
('first_month_rate',        '10',                'number',  'Referral first month rate (%)'),
('other_months_rate',       '5',                 'number',  'Referral ongoing rate (%)'),
('min_points_to_claim',     '100',               'number',  'Minimum ₹ to claim'),
('enable_referrals',        'true',              'boolean', 'Enable referral system'),
('auto_approve_claims',     'false',             'boolean', 'Auto-approve referral claims'),
('sendinblue_api_key',      '',                  'string',  'SendinBlue API key'),
('sendinblue_from_email',   '',                  'string',  'Sender email in SendinBlue'),
('sendinblue_from_name',    'bluefifth Team',    'string',  'Sender name'),
('shiprocket_email',        '',                  'string',  'Shiprocket login email'),
('shiprocket_password',     '',                  'string',  'Shiprocket login password'),
('shiprocket_enabled',      'false',             'boolean', 'Enable Shiprocket integration'),
('enable_reviews',          'true',              'boolean', 'Enable product reviews'),
('enable_wishlist',         'true',              'boolean', 'Enable wishlist'),
('default_product_image',   '/images/placeholder-product.jpg','string','Default product image'),
('default_timezone',        'Asia/Kolkata',      'string',  'Site timezone'),
('contact_email',           '',                  'string',  'Public contact email'),
('contact_phone',           '',                  'string',  'Public contact phone'),
('featured_products_limit', '8',                 'number',  'Max featured products on homepage'),
('low_stock_threshold',     '10',                'number',  'Global low stock threshold'),
('items_per_page',          '10',                'number',  'Admin pagination page size');

-- ============================================================
-- TABLE: shipping_rates_cache
-- ============================================================

CREATE TABLE IF NOT EXISTS `shipping_rates_cache` (
  `id`                     INT(11)       NOT NULL AUTO_INCREMENT,
  `pickup_pincode`         VARCHAR(6)    NOT NULL,
  `delivery_pincode`       VARCHAR(6)    NOT NULL,
  `weight`                 DECIMAL(5,2)  NOT NULL,
  `courier_company_id`     INT(11)       DEFAULT NULL,
  `courier_name`           VARCHAR(100)  DEFAULT NULL,
  `rate`                   DECIMAL(10,2) NOT NULL,
  `estimated_delivery_days` INT(11)      DEFAULT NULL,
  `cod_available`          TINYINT(1)    DEFAULT 0,
  `cached_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`             TIMESTAMP     NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 HOUR),
  PRIMARY KEY (`id`),
  KEY `idx_pincodes` (`pickup_pincode`,`delivery_pincode`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VIEWS
-- ============================================================

-- order_summary view
CREATE OR REPLACE VIEW `order_summary` AS
SELECT
  o.id,
  o.order_number,
  o.total_amount,
  o.final_amount,
  o.wallet_points_used,
  o.status,
  o.payment_status,
  o.created_at,
  u.name  AS customer_name,
  u.email AS customer_email,
  (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
FROM orders o
LEFT JOIN users u ON o.user_id = u.id;

-- product_inventory view
CREATE OR REPLACE VIEW `product_inventory` AS
SELECT
  p.id,
  p.name,
  p.price,
  p.stock_quantity,
  p.low_stock_threshold,
  p.status,
  c.name AS category_name,
  CASE
    WHEN p.stock_quantity = 0              THEN 'Out of Stock'
    WHEN p.stock_quantity <= p.low_stock_threshold THEN 'Low Stock'
    ELSE 'In Stock'
  END AS stock_status,
  (SELECT COUNT(*)    FROM order_items oi WHERE oi.product_id = p.id) AS total_orders,
  (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_id = p.id) AS total_sold
FROM products p
JOIN categories c ON p.category_id = c.id;

-- ============================================================
-- TRIGGERS
-- ============================================================

DROP TRIGGER IF EXISTS `update_stock_after_order`;
DELIMITER $$
CREATE TRIGGER `update_stock_after_order`
AFTER INSERT ON `order_items` FOR EACH ROW
BEGIN
  UPDATE products SET stock_quantity = stock_quantity - NEW.quantity WHERE id = NEW.product_id;
  UPDATE products SET status = 'out_of_stock' WHERE id = NEW.product_id AND stock_quantity <= 0;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `restore_stock_on_cancel`;
DELIMITER $$
CREATE TRIGGER `restore_stock_on_cancel`
AFTER UPDATE ON `orders` FOR EACH ROW
BEGIN
  IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
    UPDATE products p
    JOIN order_items oi ON p.id = oi.product_id
    SET p.stock_quantity = p.stock_quantity + oi.quantity
    WHERE oi.order_id = NEW.id;

    UPDATE products p
    JOIN order_items oi ON p.id = oi.product_id
    SET p.status = 'active'
    WHERE oi.order_id = NEW.id AND p.status = 'out_of_stock';
  END IF;
END$$
DELIMITER ;

-- ============================================================
-- PATCH SECTION — safe to run on an existing database
-- These ALTER statements add only the missing pieces.
-- ============================================================

-- Users: add missing columns if they don't exist yet
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `phone`               VARCHAR(20)   DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `address`             TEXT          DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `city`                VARCHAR(100)  DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `state`               VARCHAR(100)  DEFAULT NULL AFTER `city`,
  ADD COLUMN IF NOT EXISTS `pincode`             VARCHAR(10)   DEFAULT NULL AFTER `state`,
  ADD COLUMN IF NOT EXISTS `user_type`           ENUM('registered','guest') NOT NULL DEFAULT 'registered' AFTER `pincode`,
  ADD COLUMN IF NOT EXISTS `kyc_status`          ENUM('not_submitted','pending','verified','rejected') DEFAULT 'not_submitted' AFTER `user_type`,
  ADD COLUMN IF NOT EXISTS `pan_number`          VARCHAR(20)   DEFAULT NULL AFTER `kyc_status`,
  ADD COLUMN IF NOT EXISTS `aadhar_number`       VARCHAR(20)   DEFAULT NULL AFTER `pan_number`,
  ADD COLUMN IF NOT EXISTS `bank_account_number` VARCHAR(50)   DEFAULT NULL AFTER `aadhar_number`,
  ADD COLUMN IF NOT EXISTS `ifsc_code`           VARCHAR(20)   DEFAULT NULL AFTER `bank_account_number`,
  ADD COLUMN IF NOT EXISTS `upi_id`              VARCHAR(100)  DEFAULT NULL AFTER `ifsc_code`,
  ADD COLUMN IF NOT EXISTS `aadhar_front_path`   VARCHAR(500)  DEFAULT NULL AFTER `upi_id`,
  ADD COLUMN IF NOT EXISTS `aadhar_back_path`    VARCHAR(500)  DEFAULT NULL AFTER `aadhar_front_path`,
  ADD COLUMN IF NOT EXISTS `pan_front_path`      VARCHAR(500)  DEFAULT NULL AFTER `aadhar_back_path`,
  ADD COLUMN IF NOT EXISTS `pan_back_path`       VARCHAR(500)  DEFAULT NULL AFTER `pan_front_path`;

-- Orders: add missing coupon / combo columns
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `coupon_code`                VARCHAR(50)   DEFAULT NULL AFTER `referral_code`,
  ADD COLUMN IF NOT EXISTS `coupon_discount_percentage` DECIMAL(5,2)  DEFAULT NULL AFTER `coupon_code`,
  ADD COLUMN IF NOT EXISTS `coupon_discount_amount`     DECIMAL(10,2) DEFAULT NULL AFTER `coupon_discount_percentage`,
  ADD COLUMN IF NOT EXISTS `is_combo_applied`           TINYINT(1)    DEFAULT 0    AFTER `coupon_discount_amount`,
  ADD COLUMN IF NOT EXISTS `combo_savings`              DECIMAL(10,2) DEFAULT 0.00 AFTER `is_combo_applied`,
  ADD COLUMN IF NOT EXISTS `combo_type`                 VARCHAR(50)   DEFAULT NULL AFTER `combo_savings`;

-- Wallet transactions: add TDS columns
ALTER TABLE `wallet_transactions`
  ADD COLUMN IF NOT EXISTS `tax_deducted` DECIMAL(10,2) DEFAULT 0.00 AFTER `points`,
  ADD COLUMN IF NOT EXISTS `net_credited` DECIMAL(10,2) DEFAULT NULL AFTER `tax_deducted`;

-- Email notifications: relax enum to varchar to allow empty strings in existing data
ALTER TABLE `email_notifications`
  MODIFY COLUMN `email_type` VARCHAR(50) NOT NULL DEFAULT '';

-- ============================================================
-- IMAGE PATH FIX
-- Stored paths use /referral-system/ — project is /ecommerce-project/
-- ============================================================

UPDATE `product_images`
SET `image_url` = REPLACE(`image_url`, '/referral-system/', '/ecommerce-project/')
WHERE `image_url` LIKE '/referral-system/%';

UPDATE `products`
SET
  `main_image`    = REPLACE(`main_image`,    '/referral-system/', '/ecommerce-project/'),
  `product_image` = REPLACE(`product_image`, '/referral-system/', '/ecommerce-project/'),
  `image`         = REPLACE(`image`,         '/referral-system/', '/ecommerce-project/')
WHERE `main_image`    LIKE '/referral-system/%'
   OR `product_image` LIKE '/referral-system/%'
   OR `image`         LIKE '/referral-system/%';

UPDATE `categories`
SET `image` = REPLACE(`image`, '/referral-system/', '/ecommerce-project/')
WHERE `image` LIKE '/referral-system/%';

UPDATE `referrals`
SET `link` = REPLACE(`link`, 'http://localhost/referral-system/', 'http://localhost/ecommerce-project/')
WHERE `link` LIKE 'http://localhost/referral-system/%';

-- ============================================================
-- ADMIN PASSWORD — invalidate the plain-text value
-- The admin-auth.php now does password_verify() so '12345678'
-- will no longer work. Run setup-admin-password.php to write
-- a proper bcrypt hash.
-- ============================================================

UPDATE `admin_users`
SET `password_hash` = 'NEEDS_RESET'
WHERE `password_hash` NOT LIKE '$2y$%'
  AND `password_hash` NOT LIKE '$2a$%'
  AND `password_hash` != 'NEEDS_RESET';

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ============================================================
-- AFTER IMPORT CHECKLIST
-- ============================================================
-- 1. Browse to http://localhost/ecommerce-project/setup-admin-password.php
--    and confirm it says "✅ Admin password updated".
-- 2. DELETE setup-admin-password.php from the server.
-- 3. Open includes/config.php and confirm BASE_URL is correct.
-- 4. Rotate all exposed API keys:
--      - Google OAuth (config.php)
--      - Razorpay    (settings table, rows razorpay_key_id / _secret)
--      - SendinBlue  (settings table, row sendinblue_api_key)
--      - Shiprocket  (settings table, rows shiprocket_email / _password)
-- 5. Delete admin/api/error_log.txt (contains old session IDs).
-- ============================================================
