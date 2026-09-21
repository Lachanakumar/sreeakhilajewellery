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
