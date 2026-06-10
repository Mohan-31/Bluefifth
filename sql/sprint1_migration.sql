-- ============================================================
-- SPRINT 1 MIGRATION — Run once in Neon SQL Editor
-- Safe to re-run (uses IF NOT EXISTS / ALTER ... IF NOT EXISTS)
-- ============================================================

-- 1. OTP verifications — add otp_hash column, drop old purpose constraint
ALTER TABLE otp_verifications ADD COLUMN IF NOT EXISTS otp_hash VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE otp_verifications DROP CONSTRAINT IF EXISTS otp_verifications_purpose_check;

-- 2. wallet_transactions — allow 'held' transaction type
ALTER TABLE wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_transaction_type_check;
ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_transaction_type_check
    CHECK (transaction_type IN ('earned','used','claimed','bonus','held'));

-- 3. referral_purchases — add hold columns for 7-day return-window hold system
ALTER TABLE referral_purchases ADD COLUMN IF NOT EXISTS hold_until  TIMESTAMP DEFAULT NULL;
ALTER TABLE referral_purchases ADD COLUMN IF NOT EXISTS hold_status VARCHAR(20) NOT NULL DEFAULT 'none';

-- Add hold_status check constraint only if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'referral_purchases_hold_status_check'
          AND table_name = 'referral_purchases'
    ) THEN
        ALTER TABLE referral_purchases
            ADD CONSTRAINT referral_purchases_hold_status_check
            CHECK (hold_status IN ('none','hold','released'));
    END IF;
END$$;

-- 4. coupons — ensure used_count column exists (older schemas may be missing it)
ALTER TABLE coupons ADD COLUMN IF NOT EXISTS used_count INTEGER NOT NULL DEFAULT 0;

-- 5. Seed WELCOME10 coupon if not already present
INSERT INTO coupons (code, discount_percentage, description, usage_limit, is_active, expires_at)
VALUES ('WELCOME10', 10.00, '10% off — welcome coupon', 100, TRUE, NOW() + INTERVAL '1 year')
ON CONFLICT DO NOTHING;

-- Done.
