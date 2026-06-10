-- ============================================================
-- BLUEFIFTH E-COMMERCE — NEON DB (PostgreSQL) SCHEMA
-- Run this once in the Neon SQL Editor (neon.tech dashboard)
-- or via: psql $DATABASE_URL -f neon_schema.sql
--
-- Replaces all MySQL migration files. Safe to re-run (IF NOT EXISTS).
-- ============================================================

-- ============================================================
-- Reusable trigger function for updated_at auto-update
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- TABLE: admin_users
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id            SERIAL        PRIMARY KEY,
    username      VARCHAR(100)  NOT NULL UNIQUE,
    email         VARCHAR(255)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    full_name     VARCHAR(255)  DEFAULT NULL,
    role          VARCHAR(20)   NOT NULL DEFAULT 'admin'
                  CHECK (role IN ('super_admin','admin','editor')),
    permissions   JSONB         DEFAULT NULL,
    last_login    TIMESTAMP     DEFAULT NULL,
    is_active     BOOLEAN       DEFAULT TRUE,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER set_admin_users_updated_at
    BEFORE UPDATE ON admin_users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

INSERT INTO admin_users (id, username, email, password_hash, full_name, role, is_active)
VALUES (1, 'admin', 'admin@bluefifth.in', 'NEEDS_RESET', 'System Administrator', 'super_admin', TRUE)
ON CONFLICT DO NOTHING;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id                  SERIAL        PRIMARY KEY,
    name                VARCHAR(100)  NOT NULL,
    email               VARCHAR(150)  NOT NULL UNIQUE,
    phone               VARCHAR(20)   DEFAULT NULL,
    address             TEXT          DEFAULT NULL,
    city                VARCHAR(100)  DEFAULT NULL,
    state               VARCHAR(100)  DEFAULT NULL,
    pincode             VARCHAR(10)   DEFAULT NULL,
    user_type           VARCHAR(20)   NOT NULL DEFAULT 'registered'
                        CHECK (user_type IN ('registered','guest')),
    google_id           VARCHAR(100)  DEFAULT NULL,
    profile_image       TEXT          DEFAULT NULL,
    welcome_email_sent  BOOLEAN       DEFAULT FALSE,
    kyc_status          VARCHAR(20)   DEFAULT 'not_submitted'
                        CHECK (kyc_status IN ('not_submitted','pending','verified','rejected')),
    pan_number          VARCHAR(20)   DEFAULT NULL,
    aadhar_number       VARCHAR(20)   DEFAULT NULL,
    bank_account_number VARCHAR(50)   DEFAULT NULL,
    ifsc_code           VARCHAR(20)   DEFAULT NULL,
    upi_id              VARCHAR(100)  DEFAULT NULL,
    aadhar_front_path   VARCHAR(500)  DEFAULT NULL,
    aadhar_back_path    VARCHAR(500)  DEFAULT NULL,
    pan_front_path      VARCHAR(500)  DEFAULT NULL,
    pan_back_path       VARCHAR(500)  DEFAULT NULL,
    wallet_balance      NUMERIC(10,2) DEFAULT 0.00,
    referral_code       VARCHAR(20)   DEFAULT NULL,
    referred_by         VARCHAR(20)   DEFAULT NULL,
    status              VARCHAR(20)   DEFAULT 'active'
                        CHECK (status IN ('active','inactive')),
    last_login          TIMESTAMP     DEFAULT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_google_id  ON users (google_id);
CREATE INDEX IF NOT EXISTS idx_users_user_type  ON users (user_type);
CREATE INDEX IF NOT EXISTS idx_users_kyc_status ON users (kyc_status);
CREATE INDEX IF NOT EXISTS idx_users_status     ON users (status);
-- Phone unique index: allows multiple NULLs (same as MySQL behavior)
CREATE UNIQUE INDEX IF NOT EXISTS idx_phone_unique ON users (phone) WHERE phone IS NOT NULL;

-- ============================================================
-- TABLE: categories
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          SERIAL       PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE,
    description TEXT         DEFAULT NULL,
    image       VARCHAR(500) DEFAULT NULL,
    status      VARCHAR(20)  DEFAULT 'active'
                CHECK (status IN ('active','inactive')),
    sort_order  INTEGER      DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER set_categories_updated_at
    BEFORE UPDATE ON categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

INSERT INTO categories (id, name, slug, description, status, sort_order) VALUES
(1, 'Basics',         'basics',          'Essential clothing items for everyday wear', 'active', 1),
(2, 'Premium',        'premium',         'High-quality premium collection',             'active', 2),
(3, 'Seasonal',       'seasonal',        'Seasonal collection items',                   'active', 3),
(4, 'Limited Edition','limited-edition', 'Exclusive limited edition pieces',            'active', 4),
(5, 'Luxury',         'luxury',          'Premium luxury products',                     'active', 5)
ON CONFLICT DO NOTHING;

-- ============================================================
-- TABLE: products
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id                  SERIAL        PRIMARY KEY,
    category_id         INTEGER       NOT NULL,
    name                VARCHAR(255)  NOT NULL,
    slug                VARCHAR(255)  NOT NULL UNIQUE,
    description         TEXT          DEFAULT NULL,
    main_image          VARCHAR(500)  DEFAULT NULL,
    product_image       VARCHAR(500)  DEFAULT NULL,
    image_gallery       TEXT          DEFAULT NULL,
    image               VARCHAR(500)  DEFAULT NULL,
    care_instructions   TEXT          DEFAULT NULL,
    price               NUMERIC(10,2) NOT NULL,
    stock_quantity      INTEGER       DEFAULT 0,
    low_stock_threshold INTEGER       DEFAULT 10,
    sizes               JSONB         DEFAULT NULL,
    status              VARCHAR(20)   DEFAULT 'active'
                        CHECK (status IN ('active','inactive','out_of_stock')),
    featured            BOOLEAN       DEFAULT FALSE,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_products_category ON products (category_id);
CREATE INDEX IF NOT EXISTS idx_products_status   ON products (status);
CREATE INDEX IF NOT EXISTS idx_products_featured ON products (featured);
CREATE INDEX IF NOT EXISTS idx_products_price    ON products (price);

CREATE TRIGGER set_products_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- TABLE: product_images
-- ============================================================
CREATE TABLE IF NOT EXISTS product_images (
    id          SERIAL       PRIMARY KEY,
    product_id  INTEGER      NOT NULL,
    image_url   VARCHAR(500) NOT NULL,
    alt_text    VARCHAR(255) DEFAULT NULL,
    sort_order  INTEGER      DEFAULT 0,
    is_primary  BOOLEAN      DEFAULT FALSE,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_product_images_product_id ON product_images (product_id);
CREATE INDEX IF NOT EXISTS idx_product_images_is_primary ON product_images (is_primary);

-- ============================================================
-- TABLE: product_reviews
-- ============================================================
CREATE TABLE IF NOT EXISTS product_reviews (
    id             SERIAL       PRIMARY KEY,
    product_id     INTEGER      NOT NULL,
    user_id        INTEGER      DEFAULT NULL,
    customer_name  VARCHAR(255) DEFAULT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    rating         SMALLINT     NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text    TEXT         DEFAULT NULL,
    status         VARCHAR(20)  DEFAULT 'pending'
                   CHECK (status IN ('pending','approved','rejected')),
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_product_reviews_product_id ON product_reviews (product_id);
CREATE INDEX IF NOT EXISTS idx_product_reviews_user_id    ON product_reviews (user_id);
CREATE INDEX IF NOT EXISTS idx_product_reviews_status     ON product_reviews (status);

CREATE TRIGGER set_product_reviews_updated_at
    BEFORE UPDATE ON product_reviews
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- TABLE: cart
-- ============================================================
CREATE TABLE IF NOT EXISTS cart (
    id         SERIAL      PRIMARY KEY,
    user_id    INTEGER     NOT NULL,
    product_id INTEGER     NOT NULL,
    quantity   INTEGER     NOT NULL DEFAULT 1,
    size       VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id, size)
);

CREATE INDEX IF NOT EXISTS idx_cart_user    ON cart (user_id);
CREATE INDEX IF NOT EXISTS idx_cart_product ON cart (product_id);

CREATE TRIGGER set_cart_updated_at
    BEFORE UPDATE ON cart
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- TABLE: coupons
-- ============================================================
CREATE TABLE IF NOT EXISTS coupons (
    id                  SERIAL        PRIMARY KEY,
    code                VARCHAR(50)   NOT NULL UNIQUE,
    discount_percentage NUMERIC(5,2)  NOT NULL,
    description         VARCHAR(500)  DEFAULT NULL,
    usage_limit         INTEGER       DEFAULT NULL,
    used_count          INTEGER       NOT NULL DEFAULT 0,
    is_active           BOOLEAN       NOT NULL DEFAULT TRUE,
    expires_at          TIMESTAMP     DEFAULT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_coupons_is_active  ON coupons (is_active);
CREATE INDEX IF NOT EXISTS idx_coupons_expires_at ON coupons (expires_at);

CREATE TRIGGER set_coupons_updated_at
    BEFORE UPDATE ON coupons
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

INSERT INTO coupons (code, discount_percentage, description, usage_limit, is_active, expires_at)
VALUES ('WELCOME10', 10.00, '10% off — welcome coupon', 100, TRUE, NOW() + INTERVAL '1 year')
ON CONFLICT DO NOTHING;

-- ============================================================
-- TABLE: orders
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id                         SERIAL        PRIMARY KEY,
    order_number               VARCHAR(50)   NOT NULL UNIQUE,
    user_id                    INTEGER       DEFAULT NULL,
    total_amount               NUMERIC(10,2) NOT NULL,
    tax_amount                 NUMERIC(10,2) DEFAULT 0.00,
    shipping_amount            NUMERIC(10,2) DEFAULT 0.00,
    wallet_points_used         NUMERIC(10,2) DEFAULT 0.00,
    coupon_code                VARCHAR(50)   DEFAULT NULL,
    coupon_discount_percentage NUMERIC(5,2)  DEFAULT NULL,
    coupon_discount_amount     NUMERIC(10,2) DEFAULT NULL,
    is_combo_applied           BOOLEAN       DEFAULT FALSE,
    combo_savings              NUMERIC(10,2) DEFAULT 0.00,
    combo_type                 VARCHAR(50)   DEFAULT NULL,
    final_amount               NUMERIC(10,2) NOT NULL,
    status                     VARCHAR(20)   DEFAULT 'pending'
                               CHECK (status IN ('pending','processing','shipped','delivered','cancelled','refunded')),
    payment_status             VARCHAR(20)   DEFAULT 'pending'
                               CHECK (payment_status IN ('pending','paid','failed','refunded')),
    payment_method             VARCHAR(50)   DEFAULT NULL,
    razorpay_payment_id        VARCHAR(100)  DEFAULT NULL,
    razorpay_order_id          VARCHAR(100)  DEFAULT NULL,
    shipping_address           JSONB         DEFAULT NULL,
    billing_address            JSONB         DEFAULT NULL,
    referral_code              VARCHAR(10)   DEFAULT NULL,
    notes                      TEXT          DEFAULT NULL,
    shiprocket_order_id        VARCHAR(50)   DEFAULT NULL,
    shiprocket_shipment_id     VARCHAR(50)   DEFAULT NULL,
    tracking_number            VARCHAR(100)  DEFAULT NULL,
    estimated_delivery         DATE          DEFAULT NULL,
    courier_partner            VARCHAR(100)  DEFAULT NULL,
    shipping_method            VARCHAR(50)   DEFAULT 'standard',
    created_at                 TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_orders_user           ON orders (user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status         ON orders (status);
CREATE INDEX IF NOT EXISTS idx_orders_payment_status ON orders (payment_status);
CREATE INDEX IF NOT EXISTS idx_orders_referral       ON orders (referral_code);
CREATE INDEX IF NOT EXISTS idx_orders_coupon_code    ON orders (coupon_code);
CREATE INDEX IF NOT EXISTS idx_orders_created        ON orders (created_at);
CREATE INDEX IF NOT EXISTS idx_orders_shipment_id    ON orders (shiprocket_shipment_id);
CREATE INDEX IF NOT EXISTS idx_orders_tracking       ON orders (tracking_number);

CREATE TRIGGER set_orders_updated_at
    BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- TABLE: order_items
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id            SERIAL        PRIMARY KEY,
    order_id      INTEGER       NOT NULL,
    product_id    INTEGER       NOT NULL,
    product_name  VARCHAR(255)  NOT NULL,
    product_price NUMERIC(10,2) NOT NULL,
    quantity      INTEGER       NOT NULL,
    size          VARCHAR(10)   DEFAULT NULL,
    total_price   NUMERIC(10,2) NOT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_order_items_order   ON order_items (order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items (product_id);

-- ============================================================
-- TABLE: order_tracking
-- ============================================================
CREATE TABLE IF NOT EXISTS order_tracking (
    id                  SERIAL       PRIMARY KEY,
    order_id            INTEGER      NOT NULL,
    status              VARCHAR(50)  NOT NULL,
    description         TEXT         DEFAULT NULL,
    location            VARCHAR(200) DEFAULT NULL,
    courier_update_time TIMESTAMP    DEFAULT NULL,
    updated_by          VARCHAR(100) DEFAULT 'system',
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_order_tracking_order_id    ON order_tracking (order_id);
CREATE INDEX IF NOT EXISTS idx_order_tracking_status      ON order_tracking (status);
CREATE INDEX IF NOT EXISTS idx_order_tracking_created_at  ON order_tracking (created_at);

-- ============================================================
-- TABLE: order_returns
-- ============================================================
CREATE TABLE IF NOT EXISTS order_returns (
    id                   SERIAL       PRIMARY KEY,
    order_id             INTEGER      NOT NULL,
    shiprocket_return_id VARCHAR(100) DEFAULT NULL,
    return_status        VARCHAR(50)  DEFAULT 'requested',
    return_awb           VARCHAR(100) DEFAULT NULL,
    return_reason        TEXT         DEFAULT NULL,
    photo_path           VARCHAR(500) DEFAULT NULL,
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_order_returns_order_id  ON order_returns (order_id);
CREATE INDEX IF NOT EXISTS idx_order_returns_shipocket ON order_returns (shiprocket_return_id);
CREATE INDEX IF NOT EXISTS idx_order_returns_status    ON order_returns (return_status);

CREATE TRIGGER set_order_returns_updated_at
    BEFORE UPDATE ON order_returns
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- TABLE: wallet
-- ============================================================
CREATE TABLE IF NOT EXISTS wallet (
    id              SERIAL        PRIMARY KEY,
    user_id         INTEGER       NOT NULL UNIQUE,
    points          NUMERIC(10,2) DEFAULT 0.00,
    pending_points  NUMERIC(10,2) DEFAULT 0.00,
    total_earned    NUMERIC(10,2) DEFAULT 0.00,
    total_claimed   NUMERIC(10,2) DEFAULT 0.00,
    last_claim_date DATE          DEFAULT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wallet_user_id ON wallet (user_id);

CREATE TRIGGER set_wallet_updated_at
    BEFORE UPDATE ON wallet
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- TABLE: wallet_transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id               SERIAL        PRIMARY KEY,
    wallet_id        INTEGER       NOT NULL,
    points           NUMERIC(10,2) NOT NULL,
    tax_deducted     NUMERIC(10,2) DEFAULT 0.00,
    net_credited     NUMERIC(10,2) DEFAULT NULL,
    transaction_type VARCHAR(20)   NOT NULL
                     CHECK (transaction_type IN ('earned','used','claimed','bonus','held')),
    reference_id     INTEGER       DEFAULT NULL,
    description      TEXT          DEFAULT NULL,
    claimed_amount   NUMERIC(10,2) DEFAULT NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wallet_tx_wallet_id        ON wallet_transactions (wallet_id);
CREATE INDEX IF NOT EXISTS idx_wallet_tx_transaction_type ON wallet_transactions (transaction_type);
CREATE INDEX IF NOT EXISTS idx_wallet_tx_created_at       ON wallet_transactions (created_at);

-- ============================================================
-- TABLE: referrals
-- ============================================================
CREATE TABLE IF NOT EXISTS referrals (
    id             SERIAL        PRIMARY KEY,
    user_id        INTEGER       NOT NULL UNIQUE,
    code           VARCHAR(20)   NOT NULL UNIQUE,
    link           TEXT          NOT NULL,
    visit_count    INTEGER       DEFAULT 0,
    purchase_count INTEGER       DEFAULT 0,
    total_earnings NUMERIC(10,2) DEFAULT 0.00,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_referrals_code ON referrals (code);

-- ============================================================
-- TABLE: referral_visits
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_visits (
    id          SERIAL      PRIMARY KEY,
    referral_id INTEGER     NOT NULL,
    visitor_ip  VARCHAR(45) DEFAULT NULL,
    visited_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_referral_visits_referral_id ON referral_visits (referral_id);
CREATE INDEX IF NOT EXISTS idx_referral_visits_visited_at  ON referral_visits (visited_at);

-- ============================================================
-- TABLE: referral_purchases
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_purchases (
    id             SERIAL        PRIMARY KEY,
    referral_id    INTEGER       NOT NULL,
    order_id       VARCHAR(50)   NOT NULL,
    amount         NUMERIC(10,2) NOT NULL,
    points_earned  NUMERIC(10,2) NOT NULL,
    purchase_month INTEGER       NOT NULL DEFAULT 1,
    earning_rate   NUMERIC(5,2)  NOT NULL DEFAULT 5.00,
    status         VARCHAR(20)   DEFAULT 'credited'
                   CHECK (status IN ('credited','claimed','paid')),
    hold_until     TIMESTAMP     DEFAULT NULL,
    hold_status    VARCHAR(20)   NOT NULL DEFAULT 'none'
                   CHECK (hold_status IN ('none','hold','released')),
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_referral_purchases_referral_id ON referral_purchases (referral_id);
CREATE INDEX IF NOT EXISTS idx_referral_purchases_order_id    ON referral_purchases (order_id);
CREATE INDEX IF NOT EXISTS idx_referral_purchases_status      ON referral_purchases (status);

-- ============================================================
-- TABLE: claims
-- ============================================================
CREATE TABLE IF NOT EXISTS claims (
    id             SERIAL        PRIMARY KEY,
    user_id        INTEGER       NOT NULL,
    points_claimed NUMERIC(10,2) NOT NULL,
    money_value    NUMERIC(10,2) NOT NULL,
    status         VARCHAR(20)   DEFAULT 'pending'
                   CHECK (status IN ('pending','processed','rejected')),
    admin_notes    TEXT          DEFAULT NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at   TIMESTAMP     DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_claims_user_status ON claims (user_id, status);
CREATE INDEX IF NOT EXISTS idx_claims_status_date ON claims (status, created_at);

-- ============================================================
-- TABLE: user_monthly_earnings
-- ============================================================
CREATE TABLE IF NOT EXISTS user_monthly_earnings (
    id             SERIAL        PRIMARY KEY,
    user_id        INTEGER       NOT NULL,
    purchase_month INTEGER       NOT NULL,
    earning_rate   NUMERIC(5,2)  NOT NULL,
    purchase_count INTEGER       DEFAULT 0,
    month_sales    NUMERIC(10,2) DEFAULT 0.00,
    month_points   NUMERIC(10,2) DEFAULT 0.00,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_user_monthly_earnings_user_id ON user_monthly_earnings (user_id);

-- ============================================================
-- TABLE: email_notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS email_notifications (
    id              SERIAL       PRIMARY KEY,
    user_id         INTEGER      NOT NULL,
    email_type      VARCHAR(50)  NOT NULL DEFAULT '',
    campaign_id     VARCHAR(100) DEFAULT NULL,
    subject         VARCHAR(255) NOT NULL,
    message         TEXT         NOT NULL,
    sent_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status          VARCHAR(20)  DEFAULT 'sent'
                    CHECK (status IN ('sent','failed')),
    recipient_count INTEGER      DEFAULT 1,
    delivery_status VARCHAR(20)  DEFAULT 'pending'
                    CHECK (delivery_status IN ('pending','sent','delivered','failed','bounced'))
);

CREATE INDEX IF NOT EXISTS idx_email_notif_user_type       ON email_notifications (user_id, email_type);
CREATE INDEX IF NOT EXISTS idx_email_notif_sent_at         ON email_notifications (sent_at);
CREATE INDEX IF NOT EXISTS idx_email_notif_campaign_id     ON email_notifications (campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_notif_delivery_status ON email_notifications (delivery_status);

-- ============================================================
-- TABLE: email_unsubscribes
-- ============================================================
CREATE TABLE IF NOT EXISTS email_unsubscribes (
    id                SERIAL       PRIMARY KEY,
    email             VARCHAR(255) NOT NULL UNIQUE,
    user_id           INTEGER      DEFAULT NULL,
    unsubscribe_token VARCHAR(255) NOT NULL,
    unsubscribed_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason            VARCHAR(500) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_email_unsub_user_id ON email_unsubscribes (user_id);
CREATE INDEX IF NOT EXISTS idx_email_unsub_token   ON email_unsubscribes (unsubscribe_token);

-- ============================================================
-- TABLE: bulk_email_campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS bulk_email_campaigns (
    id                  SERIAL       PRIMARY KEY,
    campaign_name       VARCHAR(255) NOT NULL,
    subject             VARCHAR(500) NOT NULL,
    message             TEXT         NOT NULL,
    recipient_groups    JSONB        NOT NULL,
    total_recipients    INTEGER      DEFAULT 0,
    emails_sent         INTEGER      DEFAULT 0,
    emails_delivered    INTEGER      DEFAULT 0,
    emails_failed       INTEGER      DEFAULT 0,
    attachment_path     VARCHAR(500) DEFAULT NULL,
    created_by_admin_id INTEGER      NOT NULL,
    status              VARCHAR(20)  DEFAULT 'draft'
                        CHECK (status IN ('draft','sending','completed','failed')),
    started_at          TIMESTAMP    DEFAULT NULL,
    completed_at        TIMESTAMP    DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bulk_email_status     ON bulk_email_campaigns (status);
CREATE INDEX IF NOT EXISTS idx_bulk_email_created_by ON bulk_email_campaigns (created_by_admin_id);
CREATE INDEX IF NOT EXISTS idx_bulk_email_created_at ON bulk_email_campaigns (created_at);

-- ============================================================
-- TABLE: settings
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id                  SERIAL       PRIMARY KEY,
    setting_key         VARCHAR(100) NOT NULL UNIQUE,
    setting_value       TEXT         DEFAULT NULL,
    setting_type        VARCHAR(20)  DEFAULT 'string'
                        CHECK (setting_type IN ('string','number','boolean','json')),
    setting_description TEXT         DEFAULT NULL,
    description         TEXT         DEFAULT NULL,
    is_editable         BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER set_settings_updated_at
    BEFORE UPDATE ON settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name',               'bluefifth',                        'string',  'Website name'),
('site_description',        'Premium clothing',                 'string',  'Website description'),
('currency',                'INR',                              'string',  'Default currency'),
('currency_symbol',         '₹',                                'string',  'Currency symbol'),
('min_order_amount',        '500',                              'number',  'Minimum order amount'),
('shipping_charge',         '0',                                'number',  'Default shipping charge'),
('free_shipping_threshold', '500',                              'number',  'Free shipping above this amount'),
('low_stock_alert',         '10',                               'number',  'Low stock alert threshold'),
('order_number_prefix',     'VLN',                              'string',  'Order number prefix'),
('razorpay_key_id',         '',                                 'string',  'Razorpay Key ID'),
('razorpay_key_secret',     '',                                 'string',  'Razorpay Key Secret'),
('razorpay_mode',           'test',                             'string',  'test or live'),
('enable_cod',              'true',                             'boolean', 'Enable Cash on Delivery'),
('cod_charges',             '50',                               'number',  'COD extra charge'),
('tax_rate',                '5',                                'number',  'Tax percentage'),
('email_notifications',     'true',                             'boolean', 'Enable email notifications'),
('maintenance_mode',        'false',                            'boolean', 'Maintenance mode'),
('first_month_rate',        '10',                               'number',  'Referral first month rate (%)'),
('other_months_rate',       '5',                                'number',  'Referral ongoing rate (%)'),
('min_points_to_claim',     '100',                              'number',  'Minimum ₹ to claim'),
('enable_referrals',        'true',                             'boolean', 'Enable referral system'),
('auto_approve_claims',     'false',                            'boolean', 'Auto-approve referral claims'),
('sendinblue_api_key',      '',                                 'string',  'SendinBlue API key'),
('sendinblue_from_email',   '',                                 'string',  'Sender email in SendinBlue'),
('sendinblue_from_name',    'bluefifth Team',                   'string',  'Sender name'),
('shiprocket_email',        '',                                 'string',  'Shiprocket login email'),
('shiprocket_password',     '',                                 'string',  'Shiprocket login password'),
('shiprocket_enabled',      'false',                            'boolean', 'Enable Shiprocket integration'),
('enable_reviews',          'true',                             'boolean', 'Enable product reviews'),
('enable_wishlist',         'true',                             'boolean', 'Enable wishlist'),
('default_product_image',   '/images/placeholder-product.jpg', 'string',  'Default product image'),
('default_timezone',        'Asia/Kolkata',                     'string',  'Site timezone'),
('contact_email',           '',                                 'string',  'Public contact email'),
('contact_phone',           '',                                 'string',  'Public contact phone'),
('featured_products_limit', '8',                                'number',  'Max featured products on homepage'),
('low_stock_threshold',     '10',                               'number',  'Global low stock threshold'),
('items_per_page',          '10',                               'number',  'Admin pagination page size')
ON CONFLICT DO NOTHING;

-- ============================================================
-- TABLE: shipping_rates_cache
-- ============================================================
CREATE TABLE IF NOT EXISTS shipping_rates_cache (
    id                      SERIAL        PRIMARY KEY,
    pickup_pincode          VARCHAR(6)    NOT NULL,
    delivery_pincode        VARCHAR(6)    NOT NULL,
    weight                  NUMERIC(5,2)  NOT NULL,
    courier_company_id      INTEGER       DEFAULT NULL,
    courier_name            VARCHAR(100)  DEFAULT NULL,
    rate                    NUMERIC(10,2) NOT NULL,
    estimated_delivery_days INTEGER       DEFAULT NULL,
    cod_available           BOOLEAN       DEFAULT FALSE,
    cached_at               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at              TIMESTAMP     NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL '1 hour')
);

CREATE INDEX IF NOT EXISTS idx_shipping_cache_pincodes   ON shipping_rates_cache (pickup_pincode, delivery_pincode);
CREATE INDEX IF NOT EXISTS idx_shipping_cache_expires_at ON shipping_rates_cache (expires_at);

-- ============================================================
-- TABLE: otp_verifications
-- ============================================================
CREATE TABLE IF NOT EXISTS otp_verifications (
    id          SERIAL        PRIMARY KEY,
    phone       VARCHAR(15)   NOT NULL,
    otp_hash    VARCHAR(255)  NOT NULL DEFAULT '',
    purpose     VARCHAR(20)   NOT NULL DEFAULT 'login',
    is_verified BOOLEAN       NOT NULL DEFAULT FALSE,
    attempts    INTEGER       NOT NULL DEFAULT 0,
    expires_at  TIMESTAMP     NOT NULL,
    verified_at TIMESTAMP     DEFAULT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_otp_phone_purpose  ON otp_verifications (phone, purpose);
CREATE INDEX IF NOT EXISTS idx_otp_expires        ON otp_verifications (expires_at);
CREATE INDEX IF NOT EXISTS idx_otp_phone_verified ON otp_verifications (phone, is_verified);

-- ============================================================
-- TABLE: customer_addresses
-- ============================================================
CREATE TABLE IF NOT EXISTS customer_addresses (
    id           SERIAL       PRIMARY KEY,
    user_id      INTEGER      NOT NULL,
    label        VARCHAR(50)  NOT NULL DEFAULT 'Home',
    full_name    VARCHAR(200) NOT NULL DEFAULT '',
    phone        VARCHAR(15)  NOT NULL DEFAULT '',
    email        VARCHAR(200) NOT NULL DEFAULT '',
    address_line TEXT         NOT NULL,
    apartment    VARCHAR(100) NOT NULL DEFAULT '',
    city         VARCHAR(100) NOT NULL,
    state        VARCHAR(100) NOT NULL,
    pincode      VARCHAR(10)  NOT NULL,
    is_default   BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_customer_addr_user_default ON customer_addresses (user_id, is_default);
CREATE INDEX IF NOT EXISTS idx_customer_addr_user_id      ON customer_addresses (user_id);

CREATE TRIGGER set_customer_addresses_updated_at
    BEFORE UPDATE ON customer_addresses
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- VIEWS
-- ============================================================

CREATE OR REPLACE VIEW order_summary AS
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

CREATE OR REPLACE VIEW product_inventory AS
SELECT
    p.id,
    p.name,
    p.price,
    p.stock_quantity,
    p.low_stock_threshold,
    p.status,
    c.name AS category_name,
    CASE
        WHEN p.stock_quantity = 0                          THEN 'Out of Stock'
        WHEN p.stock_quantity <= p.low_stock_threshold     THEN 'Low Stock'
        ELSE 'In Stock'
    END AS stock_status,
    (SELECT COUNT(*)          FROM order_items oi WHERE oi.product_id = p.id) AS total_orders,
    (SELECT SUM(oi.quantity)  FROM order_items oi WHERE oi.product_id = p.id) AS total_sold
FROM products p
JOIN categories c ON p.category_id = c.id;

-- ============================================================
-- TRIGGERS — Stock management (PL/pgSQL)
-- ============================================================

CREATE OR REPLACE FUNCTION fn_update_stock_after_order()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE products
    SET stock_quantity = stock_quantity - NEW.quantity
    WHERE id = NEW.product_id;

    UPDATE products
    SET status = 'out_of_stock'
    WHERE id = NEW.product_id AND stock_quantity <= 0;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS update_stock_after_order ON order_items;
CREATE TRIGGER update_stock_after_order
    AFTER INSERT ON order_items
    FOR EACH ROW EXECUTE FUNCTION fn_update_stock_after_order();

CREATE OR REPLACE FUNCTION fn_restore_stock_on_cancel()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status <> 'cancelled' THEN
        -- Restore stock quantities (PostgreSQL UPDATE ... FROM syntax)
        UPDATE products
        SET stock_quantity = products.stock_quantity + subq.qty
        FROM (
            SELECT product_id, SUM(quantity) AS qty
            FROM order_items
            WHERE order_id = NEW.id
            GROUP BY product_id
        ) subq
        WHERE products.id = subq.product_id;

        -- Re-activate out-of-stock products that now have stock
        UPDATE products
        SET status = 'active'
        FROM order_items oi
        WHERE products.id = oi.product_id
          AND oi.order_id = NEW.id
          AND products.status = 'out_of_stock';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS restore_stock_on_cancel ON orders;
CREATE TRIGGER restore_stock_on_cancel
    AFTER UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION fn_restore_stock_on_cancel();

-- ============================================================
-- Reset sequences to match seeded IDs
-- ============================================================
SELECT setval('admin_users_id_seq', COALESCE((SELECT MAX(id) FROM admin_users), 1));
SELECT setval('categories_id_seq',  COALESCE((SELECT MAX(id) FROM categories), 1));

-- ============================================================
-- AFTER RUNNING THIS SCHEMA:
-- 1. Visit /setup-admin-password.php to set a bcrypt admin password
-- 2. Delete setup-admin-password.php from the server
-- 3. Set all env vars in Vercel dashboard (see .env.example)
-- 4. Enter your Razorpay / SendinBlue / Shiprocket keys
--    via Admin Panel → Settings after first login
-- ============================================================
