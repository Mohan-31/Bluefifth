-- =============================================================
-- schema_patch.sql — Fix missing columns found during flow audit
-- Import via phpMyAdmin or: mysql -u root ecommerce_referral_db < schema_patch.sql
-- =============================================================

-- 1. referral_purchases: add hold_until and hold_status columns
--    (checkout.php inserts into these — missing columns caused silent INSERT failure)
ALTER TABLE `referral_purchases`
  ADD COLUMN IF NOT EXISTS `hold_until`  DATETIME NULL                                          AFTER `status`,
  ADD COLUMN IF NOT EXISTS `hold_status` ENUM('hold','released','immediate','canceled')
                                         NOT NULL DEFAULT 'immediate'                            AFTER `hold_until`;

-- 2. wallet_transactions: add 'held' to transaction_type ENUM
--    (checkout.php logs referral hold with type='held' which violated the ENUM constraint)
ALTER TABLE `wallet_transactions`
  MODIFY COLUMN `transaction_type`
    ENUM('earned','used','claimed','bonus','held') NOT NULL;

-- 3. categories: add hsn_code column
--    (invoice.php and manage-categories.php reference c.hsn_code — missing column)
ALTER TABLE `categories`
  ADD COLUMN IF NOT EXISTS `hsn_code` VARCHAR(20) NULL DEFAULT NULL AFTER `description`;

-- Verify
SELECT 'referral_purchases columns:' AS info;
DESCRIBE referral_purchases;

SELECT 'wallet_transactions.transaction_type:' AS info;
SHOW COLUMNS FROM wallet_transactions LIKE 'transaction_type';

SELECT 'categories.hsn_code:' AS info;
SHOW COLUMNS FROM categories LIKE 'hsn_code';
