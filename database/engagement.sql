-- Schemes, product enquiries, appointments, customer feedback.
USE anand_jewellery;

CREATE TABLE IF NOT EXISTS scheme_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schemes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    short_description VARCHAR(400) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    terms TEXT DEFAULT NULL,
    duration_months INT DEFAULT NULL,
    monthly_amount DECIMAL(12,2) DEFAULT NULL,
    benefits TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES scheme_categories(id) ON DELETE SET NULL,
    INDEX idx_schemes_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED DEFAULT NULL,
    scheme_id INT UNSIGNED DEFAULT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) NOT NULL,
    subject VARCHAR(180) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    status ENUM('new','in_progress','responded','closed') NOT NULL DEFAULT 'new',
    admin_note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_enquiries_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time VARCHAR(20) NOT NULL,
    purpose VARCHAR(120) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    items_json TEXT DEFAULT NULL,
    status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appointments_status (status),
    INDEX idx_appointments_date (appointment_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    category ENUM('service','product_quality','website','pricing','other') NOT NULL DEFAULT 'other',
    rating TINYINT UNSIGNED DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_feedback_status (status)
) ENGINE=InnoDB;

-- ---- Daily gold / silver rates ----
CREATE TABLE IF NOT EXISTS metal_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metal VARCHAR(30) NOT NULL,           -- gold_24k | gold_22k | gold_18k | silver
    label VARCHAR(60) NOT NULL,
    rate_per_gram DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'gram',
    effective_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_metal_date (metal, effective_date),
    INDEX idx_rates_metal (metal, effective_date)
) ENGINE=InnoDB;

INSERT INTO metal_rates (metal, label, rate_per_gram, unit, effective_date) VALUES
('gold_24k', '24K Gold', 7450.00, 'gram', CURDATE()),
('gold_22k', '22K Gold', 6830.00, 'gram', CURDATE()),
('gold_18k', '18K Gold', 5590.00, 'gram', CURDATE()),
('silver',   'Silver',      92.00, 'gram', CURDATE())
ON DUPLICATE KEY UPDATE rate_per_gram = VALUES(rate_per_gram);

-- ---- Visit counter (per day + running total via settings) ----
CREATE TABLE IF NOT EXISTS site_visits (
    visit_date DATE NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---- Membership columns on users ----
-- Email is now optional so customers can register with a mobile number only.
ALTER TABLE users MODIFY email VARCHAR(150) DEFAULT NULL;
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS membership_no VARCHAR(24) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS membership_tier ENUM('classic','silver','gold','platinum') NOT NULL DEFAULT 'classic',
    ADD COLUMN IF NOT EXISTS dob DATE DEFAULT NULL;
-- (unique index added separately so re-runs don't fail)
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_membership ON users (membership_no);

-- Backfill membership numbers for existing customers.
UPDATE users
SET membership_no = CONCAT('AJM', LPAD(id, 6, '0'))
WHERE membership_no IS NULL;

-- ---- Settings defaults ----
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_number', '919443583000'),
('whatsapp_message', 'Hello! I have a question about your jewellery.'),
('instagram_url', 'https://www.instagram.com/salemshivanijewellery/'),
('call_number', '+91 94435 83000'),
('app_ios_url', ''),
('app_android_url', ''),
('digigold_enabled', '1'),
('digigold_blurb', 'Buy 24K digital gold starting from just ₹1. Save daily, weekly or monthly and convert to jewellery or coins any time.'),
('appointment_times', '10:00 AM,11:00 AM,12:00 PM,01:00 PM,02:00 PM,03:00 PM,04:00 PM,05:00 PM,06:00 PM,07:00 PM'),
('show_metal_rates', '1'),
('quick_contact_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- ---- Sample scheme data ----
INSERT INTO scheme_categories (name, slug, description, sort_order) VALUES
('Gold Savings Schemes', 'gold-savings', 'Monthly savings plans that grow into gold jewellery.', 1),
('Diamond Schemes', 'diamond-schemes', 'Plans tailored for diamond jewellery purchases.', 2);

INSERT INTO schemes (category_id, name, slug, short_description, description, terms, duration_months, monthly_amount, benefits, sort_order) VALUES
(1, '11 Month Gold Plan', '11-month-gold-plan', 'Pay 11 monthly instalments, we pay the 12th. Redeem for gold jewellery.', 'Our flagship savings scheme. Choose a fixed monthly amount, pay 11 instalments, and the store contributes the final instalment as a bonus. At maturity redeem the full value against gold jewellery at the prevailing rate.', 'Minimum monthly instalment applies. Redemption in jewellery only, against making charges as per store policy. Instalments must be paid by the due date each month.', 12, 5000.00, 'One free instalment on maturity; lock-in of gold rate options; priority billing on redemption.', 1),
(1, 'Flexi Gold Saver', 'flexi-gold-saver', 'Save any amount, any month, for 12 months and redeem for gold.', 'A flexible plan for irregular savers. Deposit any amount whenever you like across 12 months; total accumulated value is redeemable for gold jewellery at maturity.', 'No bonus instalment. Redemption in jewellery only. Valid for 12 months from first deposit.', 12, NULL, 'Complete flexibility on amount and frequency; digital passbook; SMS reminders.', 2),
(2, 'Diamond Advantage Plan', 'diamond-advantage-plan', 'Save for 10 months towards diamond jewellery with an assured discount.', 'Designed for diamond buyers. Save a fixed amount for 10 months and receive an assured discount on making/value charges when you purchase diamond jewellery at maturity.', 'Applicable to diamond jewellery only. Discount as per current scheme circular. Instalments payable monthly.', 10, 10000.00, 'Assured discount on diamond jewellery; certificate of enrolment; dedicated relationship desk.', 1);
