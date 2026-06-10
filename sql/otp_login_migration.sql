-- ============================================================
-- OTP Login Migration — Phone-first authentication (PostgreSQL/Neon)
-- Run once in Neon SQL Editor or via: psql $DATABASE_URL -f otp_login_migration.sql
-- Safe to re-run: uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS
-- ============================================================

CREATE TABLE IF NOT EXISTS otp_verifications (
    id          SERIAL        PRIMARY KEY,
    phone       VARCHAR(15)   NOT NULL,
    otp_hash    VARCHAR(255)  NOT NULL DEFAULT '',
    purpose     VARCHAR(20)   NOT NULL DEFAULT 'login',
    is_verified BOOLEAN       NOT NULL DEFAULT FALSE,
    attempts    INT           NOT NULL DEFAULT 0,
    expires_at  TIMESTAMP     NOT NULL,
    verified_at TIMESTAMP     DEFAULT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Add otp_hash if the table already existed without it
ALTER TABLE otp_verifications ADD COLUMN IF NOT EXISTS otp_hash VARCHAR(255) NOT NULL DEFAULT '';

-- Drop the old ENUM-based check constraint (was 'checkout','profile' from MySQL migration).
-- The purpose column now accepts any VARCHAR value; we validate it in application code.
ALTER TABLE otp_verifications DROP CONSTRAINT IF EXISTS otp_verifications_purpose_check;

CREATE INDEX IF NOT EXISTS idx_otp_phone ON otp_verifications(phone);
CREATE INDEX IF NOT EXISTS idx_otp_expires ON otp_verifications(expires_at);
CREATE INDEX IF NOT EXISTS idx_otp_phone_verified ON otp_verifications(phone, is_verified);
