-- =====================================================================
-- Payment gateway integration — extends the EXISTING schema.
-- Safe to re-run.  Run:  mysql -u root anand_jewellery < database/payments.sql
-- =====================================================================
USE anand_jewellery;

-- ---------------------------------------------------------------------
-- 1. orders: richer payment_status + a fulfilment marker.
--    `fulfilled_at` is the idempotency guard for "stock reduced + coupon
--    consumed + cart cleared" — it can only ever happen once per order.
-- ---------------------------------------------------------------------
ALTER TABLE orders
    MODIFY payment_status ENUM('pending','processing','paid','failed','cancelled','refunded','partially_refunded')
        NOT NULL DEFAULT 'pending';

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_gateway VARCHAR(20) DEFAULT NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER total_amount,
    ADD COLUMN IF NOT EXISTS fulfilled_at DATETIME DEFAULT NULL AFTER payment_status;

CREATE INDEX IF NOT EXISTS idx_orders_payment_status ON orders (payment_status);

-- ---------------------------------------------------------------------
-- 2. payments: extend the existing table (do NOT create a second one).
-- ---------------------------------------------------------------------
ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED DEFAULT NULL AFTER order_id,
    ADD COLUMN IF NOT EXISTS gateway VARCHAR(20) NOT NULL DEFAULT 'cod' AFTER user_id,
    ADD COLUMN IF NOT EXISTS gateway_order_id VARCHAR(120) DEFAULT NULL AFTER transaction_id,
    ADD COLUMN IF NOT EXISTS gateway_payment_id VARCHAR(120) DEFAULT NULL AFTER gateway_order_id,
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER amount,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40) DEFAULT NULL AFTER currency,
    ADD COLUMN IF NOT EXISTS amount_refunded DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER currency,
    ADD COLUMN IF NOT EXISTS failure_reason VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS gateway_response_reference VARCHAR(190) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(80) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- widen the status enum, migrate the legacy 'success' value, then drop it
ALTER TABLE payments
    MODIFY status ENUM('pending','processing','paid','failed','cancelled','refunded','partially_refunded','success')
        NOT NULL DEFAULT 'pending';
UPDATE payments SET status = 'paid' WHERE status = 'success';
ALTER TABLE payments
    MODIFY status ENUM('pending','processing','paid','failed','cancelled','refunded','partially_refunded')
        NOT NULL DEFAULT 'pending';

-- backfill gateway/user for the pre-existing COD rows
UPDATE payments p
    JOIN orders o ON o.id = p.order_id
   SET p.user_id  = COALESCE(p.user_id, o.user_id),
       p.gateway  = CASE WHEN p.gateway = '' OR p.gateway IS NULL THEN COALESCE(NULLIF(p.method,''),'cod') ELSE p.gateway END,
       p.currency = COALESCE(NULLIF(p.currency,''), 'INR');

-- NULLs repeat freely in a MySQL unique index, so pending rows never collide.
-- This is what stops a duplicate webhook writing the same capture twice.
CREATE UNIQUE INDEX IF NOT EXISTS uq_payments_gw_payment ON payments (gateway, gateway_payment_id);
CREATE INDEX IF NOT EXISTS idx_payments_gw_order ON payments (gateway, gateway_order_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments (status);
CREATE INDEX IF NOT EXISTS idx_payments_user ON payments (user_id);

-- ---------------------------------------------------------------------
-- 3. payment_events — webhook de-duplication + audit trail.
--    UNIQUE(gateway, event_id) makes replayed webhooks a no-op.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway VARCHAR(20) NOT NULL,
    event_id VARCHAR(190) NOT NULL,
    event_type VARCHAR(80) DEFAULT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    order_id INT UNSIGNED DEFAULT NULL,
    gateway_payment_id VARCHAR(120) DEFAULT NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event (gateway, event_id),
    KEY idx_events_order (order_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. payment_refunds
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_refunds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(20) NOT NULL,
    gateway_refund_id VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    reason VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    admin_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY uq_refund (gateway, gateway_refund_id),
    KEY idx_refunds_order (order_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. payment_logs — non-sensitive diagnostic trail.
--    NEVER holds card numbers / CVV / PINs / keys / tokens.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway VARCHAR(20) DEFAULT NULL,
    order_id INT UNSIGNED DEFAULT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    reference VARCHAR(190) DEFAULT NULL,
    event VARCHAR(80) NOT NULL,
    status VARCHAR(40) DEFAULT NULL,
    error_code VARCHAR(80) DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    context TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logs_order (order_id),
    KEY idx_logs_gateway (gateway, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Gateway enable/disable + mode live in `settings` (keys stay in the
--    protected config file — never in the DB).
-- ---------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
    ('payment_mode', 'TEST'),
    ('payment_currency', 'INR'),
    ('gateway_cod_enabled', '1'),
    ('gateway_razorpay_enabled', '1'),
    ('gateway_stripe_enabled', '1'),
    ('gateway_paypal_enabled', '1'),
    ('gateway_phonepe_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
