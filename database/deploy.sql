-- ============================================================
-- Anand Jewellery - combined deployment import
-- Generated for Plesk/phpMyAdmin: no CREATE DATABASE / USE lines.
-- In phpMyAdmin: select your database FIRST, then Import this file.
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ===== schema.sql =====
-- Anand Jewellery eCommerce schema

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS addresses;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS product_variants;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ==================== ADMINS ====================
CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== USERS ====================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pwreset_user (user_id)
) ENGINE=InnoDB;

-- ==================== CATEGORIES (self-referencing: main + sub) ====================
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_slug (slug)
) ENGINE=InnoDB;

-- ==================== BRANDS ====================
CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    logo VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== PRODUCTS ====================
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED DEFAULT NULL,
    brand_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    sku VARCHAR(60) NOT NULL UNIQUE,
    short_description VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    specifications TEXT DEFAULT NULL,
    regular_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(12,2) DEFAULT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    weight VARCHAR(30) DEFAULT NULL,
    purity VARCHAR(30) DEFAULT NULL,
    status ENUM('active','inactive','draft') NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_products_category (category_id),
    INDEX idx_products_status (status),
    INDEX idx_products_featured (is_featured),
    INDEX idx_products_slug (slug),
    INDEX idx_products_sku (sku),
    FULLTEXT INDEX ft_products_search (name, short_description, description)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_pimages_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    variant_name VARCHAR(60) NOT NULL,
    variant_value VARCHAR(60) NOT NULL,
    price_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_quantity INT NOT NULL DEFAULT 0,
    sku_suffix VARCHAR(30) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_pvariants_product (product_id)
) ENGINE=InnoDB;

-- ==================== CART / WISHLIST ====================
CREATE TABLE carts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    INDEX idx_carts_user (user_id),
    INDEX idx_carts_session (session_id)
) ENGINE=InnoDB;

CREATE TABLE wishlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_wishlists_user (user_id),
    INDEX idx_wishlists_session (session_id)
) ENGINE=InnoDB;

-- ==================== ADDRESSES ====================
CREATE TABLE addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'India',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_addresses_user (user_id)
) ENGINE=InnoDB;

-- ==================== COUPONS ====================
CREATE TABLE coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value DECIMAL(12,2) NOT NULL,
    min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_discount DECIMAL(12,2) DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT NOT NULL DEFAULT 0,
    starts_at DATE DEFAULT NULL,
    expires_at DATE DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coupons_code (code)
) ENGINE=InnoDB;

-- ==================== ORDERS ====================
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    address_id INT UNSIGNED DEFAULT NULL,
    shipping_name VARCHAR(100) NOT NULL,
    shipping_phone VARCHAR(20) NOT NULL,
    shipping_address TEXT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    coupon_id INT UNSIGNED DEFAULT NULL,
    coupon_code VARCHAR(50) DEFAULT NULL,
    shipping_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
    payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    order_status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    notes VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (order_status),
    INDEX idx_orders_number (order_number)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    variant_id INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    sku VARCHAR(60) DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_orderitems_order (order_id),
    INDEX idx_orderitems_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    method VARCHAR(30) NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payments_order (order_id)
) ENGINE=InnoDB;

-- ==================== REVIEWS ====================
CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED DEFAULT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(150) DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    -- why a review was rejected, and when it was decided (see review-moderation.sql)
    rejection_reason VARCHAR(255) DEFAULT NULL,
    moderated_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_status (status)
) ENGINE=InnoDB;

-- ==================== SETTINGS ====================
CREATE TABLE settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB;

-- ===== seed.sql =====

-- ==================== ADMIN ====================
-- login: admin@anandjewellers.test / Admin@123
INSERT INTO admins (name, email, password_hash, role, status) VALUES
('Super Admin', 'admin@anandjewellers.test', '$2y$10$Urx.zVt1JmJnATkofwIpmuY4bgg8T.vUzxfjl0U8aeKE9bCJpjTNq', 'super_admin', 'active');

-- ==================== DEMO CUSTOMER ====================
-- login: demo@customer.test / Customer@123
INSERT INTO users (name, email, phone, password_hash, status) VALUES
('Demo Customer', 'demo@customer.test', '9876543210', '$2y$10$JmPB4fJKiad8XVxJJAwbYeV2mFfQ/nF/gji905Cme.O/wzmNSViJq', 'active');

-- ==================== CATEGORIES: main + sub ====================
INSERT INTO categories (id, parent_id, name, slug, description, sort_order) VALUES
(1, NULL, 'Gold', 'gold', 'Exquisite gold jewellery collection', 1),
(2, NULL, 'Silver', 'silver', 'Elegant silver jewellery collection', 2),
(3, NULL, 'Diamond', 'diamond', 'Brilliant diamond jewellery collection', 3);

INSERT INTO categories (id, parent_id, name, slug, sort_order) VALUES
(10, 1, 'Rings', 'gold-rings', 1),
(11, 1, 'Necklaces', 'gold-necklaces', 2),
(12, 1, 'Pendants', 'gold-pendants', 3),
(13, 1, 'Bangles', 'gold-bangles', 4),
(14, 1, 'Earrings', 'gold-earrings', 5),
(15, 1, 'Armlets', 'gold-armlets', 6),
(16, 1, 'Head Jewelry', 'gold-head-jewelry', 7),
(17, 1, 'Waist Belts', 'gold-waist-belts', 8),
(18, 1, 'Nose Pins', 'gold-nose-pins', 9),
(20, 2, 'Rings', 'silver-rings', 1),
(21, 2, 'Chains', 'silver-chains', 2),
(22, 2, 'Earrings', 'silver-earrings', 3),
(30, 3, 'Rings', 'diamond-rings', 1),
(31, 3, 'Pendants', 'diamond-pendants', 2),
(32, 3, 'Earrings', 'diamond-earrings', 3);

-- ==================== BRANDS ====================
INSERT INTO brands (id, name, slug, status) VALUES
(1, 'Shivani Signature', 'shivani-signature', 'active'),
(2, 'Heritage Collection', 'heritage-collection', 'active'),
(3, 'Bridal Exclusive', 'bridal-exclusive', 'active');

-- ==================== PRODUCTS (migrated from static catalog) ====================
INSERT INTO products (id, category_id, brand_id, name, slug, sku, short_description, description, specifications, regular_price, sale_price, stock_quantity, weight, purity, status, is_featured, created_at) VALUES
(1, 10, 1, 'Gold Engagement Ring', 'gold-engagement-ring', 'SJ-R001', 'A stunning 1-carat round brilliant cut diamond solitaire set in a classic 18K white gold four-prong setting.', 'Material: Pure 22 Karat Gold (916 Hallmark). Weight: 8 grams (approximate, subject to final weight). Design: Intricately detailed engagement ring featuring a central floral motif with a polished finish.', 'Purity: 22K Gold (916) | Weight: 8 grams (approx.) | Ideal For: Engagements, Anniversaries | Occasion: Weddings, Festivals', 150000.00, 125000.00, 12, '8g', '22K (916)', 'active', 1, '2026-08-01 10:00:00'),
(2, 11, 1, '24K Gold Rope Chain', '24k-gold-rope-chain', 'SJ-N002', 'A classic 24-inch rope chain crafted from solid 24K yellow gold, perfect for daily wear or special occasions.', 'This 24K Gold Rope Chain is a masterpiece of craftsmanship. Featuring a classic twisted design, the rope chain offers a rich luster and a substantial feel. Made from solid 24K yellow gold, it is highly durable and tarnish-resistant.', 'Metal: 24K Yellow Gold | Length: 24 Inches | Width: 3mm | Weight: 12.0g | Closure: Lobster Claw', 95000.00, 85000.00, 8, '12g', '24K', 'active', 1, '2026-08-05 10:00:00'),
(3, 12, 2, 'Emerald Cut Gold Pendant', 'emerald-cut-gold-pendant', 'SJ-P003', 'A sophisticated emerald cut pendant surrounded by a diamond halo in 14K gold.', 'Exude elegance with this Emerald Cut Gold Pendant. The center stone is precision-cut into a classic emerald shape, encircled by a brilliant halo of pave diamonds. Suspended from a delicate 14K yellow gold chain.', 'Center Stone: 1.5ct Emerald Cut | Accent Stones: 0.25ct Diamonds | Metal: 14K Yellow Gold | Chain: 18 Inches Included', 75000.00, 60000.00, 15, '5g', '14K', 'active', 0, '2026-08-10 10:00:00'),
(4, 13, 2, 'Traditional Gold Bangles', 'traditional-gold-bangles', 'SJ-B004', 'A set of 2 intricately carved 22K yellow gold bangles, perfect for weddings and heritage events.', 'Our Traditional Gold Bangles are a tribute to heritage and craftsmanship. Hand-carved by master artisans, these bangles feature intricate floral and geometric patterns. Crafted from pure 22K yellow gold.', 'Metal: 22K Yellow Gold | Weight: 24.0g (Set of 2) | Diameter: 2.4/2.6/2.8 Sizes Available | Finish: Matte and Glossy Mix', 350000.00, 320000.00, 5, '24g', '22K (916)', 'active', 1, '2026-08-12 10:00:00'),
(5, 14, 3, 'Gold Jhumka Earrings', 'gold-jhumka-earrings', 'SJ-E005', 'Traditional 22K gold jhumka earrings with intricate temple jewellery design and hanging bells.', 'These exquisite Gold Jhumka Earrings showcase traditional South Indian craftsmanship. Made from 22K gold with detailed filigree work and delicate hanging bells that create a beautiful sound with every movement.', 'Metal: 22K Yellow Gold | Weight: 8.5g per pair | Type: Jhumka | Design: Temple Jewellery', 52000.00, 45000.00, 20, '8.5g', '22K (916)', 'active', 1, '2026-08-15 10:00:00'),
(6, 11, 3, 'Gold Kasu Mala Necklace', 'gold-kasu-mala-necklace', 'SJ-N006', 'Traditional gold kasu mala necklace with embossed coin design, a classic bridal essential.', 'The Gold Kasu Mala is a timeless piece of South Indian bridal jewellery. Each gold coin is meticulously embossed with traditional motifs and linked together to form a stunning long necklace.', 'Metal: 22K Yellow Gold | Weight: 35.0g | Length: 36 Inches | Design: Embossed Coins', 300000.00, 275000.00, 4, '35g', '22K (916)', 'active', 0, '2026-08-18 10:00:00'),
(7, 15, 1, 'Gold Vanki Armlet', 'gold-vanki-armlet', 'SJ-A007', 'Elegant 22K gold vanki armlet with peacock motif design, a traditional bridal ornament.', 'This stunning Gold Vanki Armlet features an intricate peacock motif design, symbolizing grace and beauty. Crafted from 22K gold with fine detailing.', 'Metal: 22K Yellow Gold | Weight: 15.0g | Design: Peacock Motif | Size: Adjustable', 110000.00, 95000.00, 6, '15g', '22K (916)', 'active', 0, '2026-08-20 10:00:00'),
(8, 11, 3, 'Gold Antique Haram Set', 'gold-antique-haram-set', 'SJ-H008', 'Grand antique gold haram necklace set with matching earrings, perfect for weddings.', 'This magnificent Antique Gold Haram Set is a statement piece for grand occasions. The long haram necklace features intricate antique gold work with traditional patterns, paired with matching drop earrings.', 'Metal: 22K Yellow Gold | Weight: 55.0g (Set) | Includes: Haram + Earrings | Finish: Antique', 500000.00, 450000.00, 3, '55g', '22K (916)', 'active', 1, '2026-08-22 10:00:00'),
(9, 16, 2, 'Gold Maang Tikka', 'gold-maang-tikka', 'SJ-M009', 'Traditional 22K gold maang tikka with ruby and pearl detailing for bridal looks.', 'Complete your bridal look with this exquisite Gold Maang Tikka. Crafted from 22K gold and adorned with genuine rubies and lustrous pearls.', 'Metal: 22K Yellow Gold | Stones: Rubies and Pearls | Weight: 12.5g | Chain Length: Adjustable', 85000.00, 75000.00, 10, '12.5g', '22K (916)', 'active', 0, '2026-08-24 10:00:00'),
(10, 10, 1, 'Gold Wedding Ring Set', 'gold-wedding-ring-set', 'SJ-W010', 'Pair of matching 22K gold wedding bands with diamond accents.', 'Celebrate your union with this timeless Gold Wedding Ring Set. Includes two matching 22K gold bands featuring subtle diamond accents and a high-polish finish.', 'Metal: 22K Yellow Gold | Accents: Small Diamonds | Weight: 8.0g (Pair) | Finish: High Polish', 170000.00, 150000.00, 9, '8g', '22K (916)', 'active', 1, '2026-08-27 10:00:00'),
(11, 17, 2, 'Gold Oddiyanam Waist Belt', 'gold-oddiyanam-waist-belt', 'SJ-O011', 'Luxurious 22K gold oddiyanam (waist belt) with traditional Lakshmi motif.', 'This stunning Gold Oddiyanam is a masterpiece of South Indian jewellery. Featuring the Goddess Lakshmi motif in the center, surrounded by intricate floral patterns.', 'Metal: 22K Yellow Gold | Weight: 85.0g | Design: Lakshmi Motif | Length: Adjustable up to 40 inches', 600000.00, 550000.00, 2, '85g', '22K (916)', 'active', 0, '2026-08-29 10:00:00'),
(12, 18, 3, 'Gold Nath Nose Pin', 'gold-nath-nose-pin', 'SJ-N012', 'Traditional 22K gold nath (nose ring) with pearl drops and ruby center.', 'Add a touch of tradition with this Gold Nath Nose Pin. Featuring a central ruby stone surrounded by delicate gold work and hanging pearl drops.', 'Metal: 22K Yellow Gold | Stones: Ruby and Pearls | Weight: 6.5g | Type: Screw/Nut', 72000.00, 65000.00, 18, '6.5g', '22K (916)', 'active', 0, '2026-09-01 10:00:00');

-- ==================== PRODUCT IMAGES ====================
INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES
(1, 'assets/img/product/items/ring.jpg', 1, 0), (1, 'assets/img/product/items/ring1.jpg', 0, 1),
(2, 'assets/img/product/items/ropechain.jpg', 1, 0), (2, 'assets/img/product/items/ropechain1.jpg', 0, 1),
(3, 'assets/img/product/items/pendant.jpg', 1, 0), (3, 'assets/img/product/items/pendant1.jpg', 0, 1),
(4, 'assets/img/product/items/bangles.jpg', 1, 0), (4, 'assets/img/product/items/bangles1.jpg', 0, 1),
(5, 'assets/img/product/items/earring.jpg', 1, 0), (5, 'assets/img/product/items/earring1.jpg', 0, 1),
(6, 'assets/img/product/items/kasumala.jpg', 1, 0), (6, 'assets/img/product/items/kasumala1.jpg', 0, 1),
(7, 'assets/img/product/items/vanki.jpg', 1, 0), (7, 'assets/img/product/items/vanki1.jpg', 0, 1),
(8, 'assets/img/product/items/haram.jpg', 1, 0), (8, 'assets/img/product/items/haram1.jpg', 0, 1),
(9, 'assets/img/product/items/maang-tikka.jpg', 1, 0), (9, 'assets/img/product/items/maang-tikka1.jpg', 0, 1),
(10, 'assets/img/product/items/wedding-ring.jpg', 1, 0), (10, 'assets/img/product/items/wedding-ring1.jpg', 0, 1),
(11, 'assets/img/product/items/oddiyanam.jpg', 1, 0), (11, 'assets/img/product/items/oddiyanam1.jpg', 0, 1),
(12, 'assets/img/product/items/nath-nose-pin.jpg', 1, 0), (12, 'assets/img/product/items/nath-nose-pin1.jpg', 0, 1);

-- ==================== PRODUCT VARIANTS (sizes, where applicable) ====================
INSERT INTO product_variants (product_id, variant_name, variant_value, price_adjustment, stock_quantity, sku_suffix) VALUES
(1, 'Size', '12', 0, 3, 'S12'),
(1, 'Size', '14', 0, 3, 'S14'),
(1, 'Size', '16', 0, 3, 'S16'),
(1, 'Size', '18', 0, 3, 'S18'),
(2, 'Length', '18 inch', -5000, 2, 'L18'),
(2, 'Length', '20 inch', -2000, 2, 'L20'),
(2, 'Length', '22 inch', 0, 2, 'L22'),
(2, 'Length', '24 inch', 3000, 2, 'L24'),
(4, 'Diameter', '2.4 inch', 0, 2, 'D24'),
(4, 'Diameter', '2.6 inch', 0, 2, 'D26'),
(4, 'Diameter', '2.8 inch', 0, 1, 'D28'),
(10, 'Ring Size', '14', 0, 4, 'S14'),
(10, 'Ring Size', '16', 0, 3, 'S16'),
(10, 'Ring Size', '18', 0, 2, 'S18');

-- ==================== COUPONS ====================
INSERT INTO coupons (code, type, value, min_order_amount, max_discount, usage_limit, starts_at, expires_at, status) VALUES
('WELCOME10', 'percent', 10.00, 5000.00, 15000.00, 200, '2026-01-01', '2026-12-31', 'active'),
('FLAT2000', 'fixed', 2000.00, 20000.00, NULL, 100, '2026-01-01', '2026-12-31', 'active');

-- ==================== SETTINGS ====================
INSERT INTO settings (setting_key, setting_value) VALUES
-- Identity rows are created empty on purpose: they are filled in from
-- Admin -> Settings. Seeding a real name/email/phone here means a fresh
-- install silently publishes whichever shop was last hardcoded.
('site_name', ''),
('site_tagline', ''),
('site_email', ''),
('site_phone', ''),
('site_address', ''),
('currency_symbol', '&#8377;'),
('shipping_flat_rate', '250'),
('free_shipping_threshold', '50000'),
('tax_percent', '3'),
-- Notification recipients. Blank means "use site_email"; each one is
-- editable in Admin -> Settings -> Notifications.
('notify_enabled', '1'),
('notify_email_from', ''),
('notify_email_contact', ''),
('notify_email_appointment', ''),
('notify_email_enquiry', ''),
('notify_email_feedback', ''),
('notify_email_order', ''),
-- Mail transport. 'mail' uses the local relay; 'smtp' uses includes/smtp.php
-- with the settings below. Configured in Admin -> Settings -> Mail Server.
('mail_transport', 'mail'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_auth', '1'),
('smtp_username', ''),
('smtp_password', ''),
('notify_from_name', ''),
-- Social links. Blank hides that icon in the footer; each one is editable in
-- Admin -> Settings -> Social. Keys match social_networks() in includes/social.php.
('social_facebook', ''),
('social_instagram', ''),
('social_whatsapp', ''),
('social_youtube', ''),
('social_twitter', '');

-- ===== homepage.sql =====
-- Homepage hero sliders + category banner tiles (admin-managed).

CREATE TABLE IF NOT EXISTS sliders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subtitle VARCHAR(150) DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description VARCHAR(400) DEFAULT NULL,
    button_label VARCHAR(60) DEFAULT NULL,
    button_url VARCHAR(255) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    title_color VARCHAR(20) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot ENUM('feature','wide','tile') NOT NULL DEFAULT 'tile',
    title VARCHAR(150) NOT NULL,
    link_label VARCHAR(40) NOT NULL DEFAULT 'SHOP NOW',
    image VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed with the current hardcoded homepage content so nothing changes visually.
INSERT INTO sliders (subtitle, title, description, button_label, button_url, image, title_color, sort_order) VALUES
('Trending Now', 'Premium Quality\nBest Prices', 'Shop the latest designs in luxury jewelry with exclusive styles crafted for every occasion', 'Shop Now', 'products.php', 'assets/img/slider/slider1.png', '#c7a047', 1),
('New Gold Collection', 'Shine in Every\nMoment', 'Discover our exclusive collection of exquisite jewelry and traditional pieces at unbeatable prices', 'Shop Now', 'products.php', 'assets/img/slider/slider2.png', NULL, 2);

INSERT INTO banners (slot, title, link_label, image, link_url, sort_order) VALUES
('feature', 'New Collections',        'SHOP NOW', 'assets/img/banner/newcollection.jpg', 'category.php?slug=gold', 1),
('wide',    'Traditional Collections', 'SHOP NOW', 'assets/img/banner/traditional.jpg',   'products.php',            1),
('wide',    'Haram Collections',       'SHOP NOW', 'assets/img/banner/haram.jpg',          'category.php?slug=gold-necklaces', 2),
('tile',    'Necklaces Collections',   'SHOP NOW', 'assets/img/banner/necklaces.jpg',      'category.php?slug=gold-necklaces', 1),
('tile',    'Earrings Collections',    'SHOP NOW', 'assets/img/banner/earrings.jpg',       'category.php?slug=gold-earrings',  2);

-- ===== ads.sql =====
-- Promotional / offer banners for the homepage (admin-managed).

CREATE TABLE IF NOT EXISTS ad_banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    format ENUM('strip','card') NOT NULL DEFAULT 'strip',
    position ENUM('after_hero','after_banners','mid_products','before_footer') NOT NULL DEFAULT 'after_banners',
    title VARCHAR(150) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description VARCHAR(400) DEFAULT NULL,
    button_label VARCHAR(60) DEFAULT NULL,
    button_url VARCHAR(255) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    bg_color VARCHAR(20) DEFAULT NULL,
    text_theme ENUM('light','dark') NOT NULL DEFAULT 'light',
    starts_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ads_position (position, status)
) ENGINE=InnoDB;

-- A couple of starter examples (inactive so they don't change the live page).
INSERT INTO ad_banners (format, position, title, subtitle, description, button_label, button_url, image, bg_color, text_theme, expires_at, sort_order, status) VALUES
('strip', 'after_banners', 'Bridal Gold Collection', 'LIMITED TIME OFFER', 'Flat 15% off making charges on all bridal sets this festive season.', 'Shop the Offer', 'products.php?on_sale=1', 'assets/img/banner/traditional.jpg', '#1f1b16', 'light', DATE_ADD(NOW(), INTERVAL 14 DAY), 1, 'inactive'),
('card', 'after_banners', 'New Diamond Arrivals', 'JUST IN', NULL, 'Explore', 'category.php?slug=diamond', 'assets/img/banner/necklaces.jpg', NULL, 'light', NULL, 1, 'inactive'),
('card', 'after_banners', 'Silver Under 5000', 'BUDGET PICKS', NULL, 'Browse', 'category.php?slug=silver', 'assets/img/banner/earrings.jpg', NULL, 'light', NULL, 2, 'inactive');

-- ===== engagement.sql =====
-- Schemes, product enquiries, appointments, customer feedback.

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
('digigold_blurb', 'Buy 24K digital gold starting from just â‚¹1. Save daily, weekly or monthly and convert to jewellery or coins any time.'),
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

-- ===== payments.sql =====
-- =====================================================================
-- Payment gateway integration â€” extends the EXISTING schema.
-- Safe to re-run.  Run:  mysql -u root anand_jewellery < database/payments.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. orders: richer payment_status + a fulfilment marker.
--    `fulfilled_at` is the idempotency guard for "stock reduced + coupon
--    consumed + cart cleared" â€” it can only ever happen once per order.
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
-- 3. payment_events â€” webhook de-duplication + audit trail.
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
-- 5. payment_logs â€” non-sensitive diagnostic trail.
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
--    protected config file â€” never in the DB).
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

-- ===== product-fields.sql =====
-- Extra product fields introduced with the redesigned product form.
-- Safe to re-run: each ALTER is guarded by an IF NOT EXISTS check.

-- Making charge is stored as product metadata only; it does NOT take part in
-- cart/checkout pricing (regular_price / sale_price still drive every total).
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS making_charge DECIMAL(12,2) NULL AFTER purity;

-- Whether the product may appear in the homepage product rows
-- (Featured / New Arrivals / Best Sellers / Special Offers).
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS show_on_homepage TINYINT(1) NOT NULL DEFAULT 1 AFTER is_featured;

-- Existing catalogue keeps its current behaviour.
UPDATE products SET show_on_homepage = 1 WHERE show_on_homepage IS NULL;

-- ===== refund-sync.sql =====
-- Refund status sync: orders left reporting a pre-refund payment state.
-- Safe to re-run: the statement is idempotent and only touches orders that are
-- already marked Refunded.

UPDATE orders
   SET payment_status = 'refunded'
 WHERE order_status = 'refunded'
   AND payment_status <> 'refunded';

-- ===== review-moderation.sql =====
-- Why a review was rejected, and who decided. deploy.sql creates these two
-- columns with the reviews table, but a database that predates them only gets
-- them from here: without them every Approve and Reject in the admin fails on
-- "Unknown column 'rejection_reason'" and the page dies with it.
-- Safe to re-run: both ALTERs are guarded by IF NOT EXISTS.

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL AFTER status;

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS moderated_at DATETIME NULL AFTER rejection_reason;

-- Reviews decided before the column existed keep their status but have no
-- timestamp to show; fall back to when the review was posted.
UPDATE reviews
   SET moderated_at = created_at
 WHERE moderated_at IS NULL
   AND status <> 'pending';

SET FOREIGN_KEY_CHECKS = 1;
