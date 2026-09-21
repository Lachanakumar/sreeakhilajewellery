USE anand_jewellery;

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
