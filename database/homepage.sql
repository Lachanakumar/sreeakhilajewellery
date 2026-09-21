-- Homepage hero sliders + category banner tiles (admin-managed).
USE anand_jewellery;

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
