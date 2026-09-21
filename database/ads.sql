-- Promotional / offer banners for the homepage (admin-managed).
USE anand_jewellery;

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
