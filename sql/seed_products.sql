-- =============================================================
-- bluefifth — Product Seed Data
-- Uses only images already present in uploads/products/
-- Import via phpMyAdmin or: mysql -u root ecommerce_referral_db < seed_products.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing product data so re-import is idempotent
TRUNCATE TABLE product_images;
DELETE FROM products;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE product_images AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- PRODUCTS
-- Categories: 1=Basics, 2=Premium, 3=Seasonal, 4=Limited Edition, 5=Luxury
-- =============================================================

INSERT INTO products
  (id, category_id, name, slug, description,
   main_image, product_image, image_gallery, image,
   care_instructions, price, stock_quantity, low_stock_threshold,
   sizes, status, featured, created_at)
VALUES

-- ---------------------------------------------------------------
-- BASICS (category_id = 1)
-- ---------------------------------------------------------------
(5, 1,
 'Essential White Tee',
 'essential-white-tee',
 'A timeless wardrobe staple crafted from 100% organic cotton. Relaxed fit with a subtle brand label. Soft, breathable, and built to last through every wash. The go-to foundation for any outfit.',
 '/ecommerce-project/uploads/products/product_5_1754145721_3692.jpg',
 '/ecommerce-project/uploads/products/product_5_1754145721_4135.jpg',
 '["/ecommerce-project/uploads/products/product_5_1754145721_3692.jpg","/ecommerce-project/uploads/products/product_5_1754145721_4135.jpg","/ecommerce-project/uploads/products/product_5_1754145721_6884.jpg","/ecommerce-project/uploads/products/product_5_1754145721_9389.jpg","/ecommerce-project/uploads/products/product_5_1754145721_9788.jpg"]',
 '/ecommerce-project/uploads/products/product_5_1754145721_3692.jpg',
 'Machine wash cold. Tumble dry low. Do not bleach. Iron on low heat.',
 699.00, 120, 10,
 '["XS","S","M","L","XL","XXL"]',
 'active', 1, NOW()),

(6, 1,
 'Classic Black Polo',
 'classic-black-polo',
 'Refined cotton pique polo with a two-button placket and ribbed collar. A versatile piece that moves effortlessly from casual to smart-casual. Tailored without being restrictive.',
 '/ecommerce-project/uploads/products/product_6_1754158691_2211.jpg',
 '/ecommerce-project/uploads/products/product_6_1754158691_2852.jpg',
 '["/ecommerce-project/uploads/products/product_6_1754158691_2211.jpg","/ecommerce-project/uploads/products/product_6_1754158691_2852.jpg","/ecommerce-project/uploads/products/product_6_1754158691_4606.jpg","/ecommerce-project/uploads/products/product_6_1754158691_4809.jpg","/ecommerce-project/uploads/products/product_6_1754158691_8817.jpg"]',
 '/ecommerce-project/uploads/products/product_6_1754158691_2211.jpg',
 'Machine wash cold inside out. Hang dry for best shape retention. Do not dry clean.',
 999.00, 85, 10,
 '["S","M","L","XL","XXL"]',
 'active', 0, NOW()),

-- ---------------------------------------------------------------
-- PREMIUM (category_id = 2)
-- ---------------------------------------------------------------
(7, 2,
 'Signature Linen Shirt',
 'signature-linen-shirt',
 'Woven from premium European linen, this relaxed-fit shirt offers unmatched breathability and a naturally textured finish. Features a classic collar, mother-of-pearl buttons, and a curved hem. Pairs with everything.',
 '/ecommerce-project/uploads/products/product_7_1754461861_2057.jpg',
 '/ecommerce-project/uploads/products/product_7_1754461861_3557.jpg',
 '["/ecommerce-project/uploads/products/product_7_1754461861_2057.jpg","/ecommerce-project/uploads/products/product_7_1754461861_3557.jpg","/ecommerce-project/uploads/products/product_7_1754461861_5892.jpg","/ecommerce-project/uploads/products/product_7_1754461861_7953.jpg","/ecommerce-project/uploads/products/product_7_1754461861_9325.jpg"]',
 '/ecommerce-project/uploads/products/product_7_1754461861_2057.jpg',
 'Hand wash or gentle machine cycle at 30°C. Do not tumble dry. Iron while damp for best results.',
 1899.00, 50, 8,
 '["S","M","L","XL"]',
 'active', 1, NOW()),

(8, 2,
 'Merino Wool Crewneck',
 'merino-wool-crewneck',
 'Knit from extra-fine 18.5-micron Merino wool for exceptional softness against the skin. Ribbed cuffs and hem, reinforced shoulder seams, and a relaxed silhouette. Regulates temperature in any weather.',
 '/ecommerce-project/uploads/products/product_8_1754483229_1195.jpg',
 '/ecommerce-project/uploads/products/product_8_1754483229_2136.jpg',
 '["/ecommerce-project/uploads/products/product_8_1754483229_1195.jpg","/ecommerce-project/uploads/products/product_8_1754483229_2136.jpg","/ecommerce-project/uploads/products/product_8_1754483229_4679.jpg","/ecommerce-project/uploads/products/product_8_1754483229_5109.jpg","/ecommerce-project/uploads/products/product_8_1754483229_8582.jpg"]',
 '/ecommerce-project/uploads/products/product_8_1754483229_1195.jpg',
 'Dry clean preferred. Hand wash cold with wool detergent. Lay flat to dry. Do not wring or tumble dry.',
 2499.00, 35, 5,
 '["XS","S","M","L","XL"]',
 'active', 1, NOW()),

-- ---------------------------------------------------------------
-- SEASONAL (category_id = 3)
-- ---------------------------------------------------------------
(9, 3,
 'Summer Striped Kurta',
 'summer-striped-kurta',
 'Lightweight cotton kurta in a fresh multi-stripe weave. Mandarin collar, side slits, and a relaxed straight cut make it ideal for warm days and festive occasions alike. Cool, comfortable, and effortlessly stylish.',
 '/ecommerce-project/uploads/products/product_9_1754577374_1103.jpg',
 '/ecommerce-project/uploads/products/product_9_1754577374_1335.jpg',
 '["/ecommerce-project/uploads/products/product_9_1754577374_1103.jpg","/ecommerce-project/uploads/products/product_9_1754577374_1335.jpg","/ecommerce-project/uploads/products/product_9_1754577374_1352.jpg","/ecommerce-project/uploads/products/product_9_1754577374_2667.jpg","/ecommerce-project/uploads/products/product_9_1754577374_4876.jpg"]',
 '/ecommerce-project/uploads/products/product_9_1754577374_1103.jpg',
 'Machine wash gentle at 30°C. Do not bleach. Hang dry in shade to preserve the stripe.',
 1299.00, 70, 10,
 '["S","M","L","XL","XXL"]',
 'active', 0, NOW()),

(10, 3,
 'Monsoon Layer Jacket',
 'monsoon-layer-jacket',
 'Water-resistant shell jacket engineered for the Indian monsoon. Taped seams, a packable hood, mesh-lined underarm vents, and two deep zip pockets. Lightweight enough to scrunch into your bag.',
 '/ecommerce-project/uploads/products/product_10_1755557619_5955.jpg',
 '/ecommerce-project/uploads/products/product_10_1755557619_6018.jpg',
 '["/ecommerce-project/uploads/products/product_10_1755557619_5955.jpg","/ecommerce-project/uploads/products/product_10_1755557619_6018.jpg","/ecommerce-project/uploads/products/product_10_1755557619_7947.jpg","/ecommerce-project/uploads/products/product_10_1755557619_8677.jpg","/ecommerce-project/uploads/products/product_10_1755557619_8836.jpg"]',
 '/ecommerce-project/uploads/products/product_10_1755557619_5955.jpg',
 'Machine wash cold on gentle cycle. Do not tumble dry. Wipe zipper teeth with dry cloth after use.',
 2199.00, 40, 8,
 '["S","M","L","XL","XXL"]',
 'active', 0, NOW()),

-- ---------------------------------------------------------------
-- LIMITED EDITION (category_id = 4)
-- ---------------------------------------------------------------
(13, 4,
 'Artist Collab Oversized Tee',
 'artist-collab-oversized-tee',
 'From our collaboration with emerging Indian artists — a drop-shoulder tee on heavyweight 240gsm cotton with a hand-drawn original print. Only 200 pieces made worldwide. Each comes with a numbered certificate of authenticity.',
 '/ecommerce-project/uploads/products/product_13_1755586920_1071.jpeg',
 '/ecommerce-project/uploads/products/product_13_1755586920_3028.jpeg',
 '["/ecommerce-project/uploads/products/product_13_1755586920_1071.jpeg","/ecommerce-project/uploads/products/product_13_1755586920_3028.jpeg","/ecommerce-project/uploads/products/product_13_1755586920_4130.jpeg","/ecommerce-project/uploads/products/product_13_1755586920_8646.jpeg","/ecommerce-project/uploads/products/product_13_1755586920_9324.jpeg"]',
 '/ecommerce-project/uploads/products/product_13_1755586920_1071.jpeg',
 'Machine wash inside out on cold. Air dry flat. Do not iron directly over print area.',
 1499.00, 18, 5,
 '["S","M","L","XL"]',
 'active', 1, NOW()),

(14, 4,
 'Heritage Block Print Shirt',
 'heritage-block-print-shirt',
 'Hand block-printed in Jaipur using natural vegetable dyes on organic cotton voile. Each piece carries the subtle imperfections of the artisan\'s hand, making it genuinely one-of-a-kind. Limited run of 150 pieces per colourway.',
 '/ecommerce-project/uploads/products/product_14_1755609089_2242.jpg',
 '/ecommerce-project/uploads/products/product_14_1755609089_7209.jpg',
 '["/ecommerce-project/uploads/products/product_14_1755609089_2242.jpg","/ecommerce-project/uploads/products/product_14_1755609089_7209.jpg","/ecommerce-project/uploads/products/product_14_1755609089_7383.jpg","/ecommerce-project/uploads/products/product_14_1755609089_7914.jpg","/ecommerce-project/uploads/products/product_14_1755609089_8805.jpg"]',
 '/ecommerce-project/uploads/products/product_14_1755609089_2242.jpg',
 'Hand wash only with mild detergent. Dry in shade. First wash separately — natural dye may run slightly.',
 1799.00, 22, 5,
 '["S","M","L","XL"]',
 'active', 1, NOW()),

-- ---------------------------------------------------------------
-- LUXURY (category_id = 5)
-- ---------------------------------------------------------------
(15, 5,
 'Handwoven Silk Bandhgala',
 'handwoven-silk-bandhgala',
 'A contemporary interpretation of the bandhgala in pure handwoven Banarasi silk. Minimal surface embellishment, structured shoulders, and a slim mandarin collar deliver understated grandeur. Ideal for weddings, receptions, and formal occasions.',
 '/ecommerce-project/uploads/products/product_15_1755848060_4208.png',
 '/ecommerce-project/uploads/products/product_15_1755848060_7299.png',
 '["/ecommerce-project/uploads/products/product_15_1755848060_4208.png","/ecommerce-project/uploads/products/product_15_1755848060_7299.png","/ecommerce-project/uploads/products/product_15_1755848060_8299.png","/ecommerce-project/uploads/products/product_15_1755848060_9589.png"]',
 '/ecommerce-project/uploads/products/product_15_1755848060_4208.png',
 'Dry clean only. Store in breathable garment bag away from direct sunlight. Do not iron. Keep away from perfume and deodorant.',
 8999.00, 12, 3,
 '["38","40","42","44","46"]',
 'active', 1, NOW());

-- =============================================================
-- PRODUCT IMAGES (normalised gallery table)
-- =============================================================

INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary) VALUES

-- Product 5 — Essential White Tee
(5, '/ecommerce-project/uploads/products/product_5_1754145721_3692.jpg', 'Essential White Tee - Front view', 1, 1),
(5, '/ecommerce-project/uploads/products/product_5_1754145721_4135.jpg', 'Essential White Tee - Back view', 2, 0),
(5, '/ecommerce-project/uploads/products/product_5_1754145721_6884.jpg', 'Essential White Tee - Detail', 3, 0),
(5, '/ecommerce-project/uploads/products/product_5_1754145721_9389.jpg', 'Essential White Tee - Side view', 4, 0),
(5, '/ecommerce-project/uploads/products/product_5_1754145721_9788.jpg', 'Essential White Tee - Flat lay', 5, 0),

-- Product 6 — Classic Black Polo
(6, '/ecommerce-project/uploads/products/product_6_1754158691_2211.jpg', 'Classic Black Polo - Front view', 1, 1),
(6, '/ecommerce-project/uploads/products/product_6_1754158691_2852.jpg', 'Classic Black Polo - Back view', 2, 0),
(6, '/ecommerce-project/uploads/products/product_6_1754158691_4606.jpg', 'Classic Black Polo - Detail', 3, 0),
(6, '/ecommerce-project/uploads/products/product_6_1754158691_4809.jpg', 'Classic Black Polo - Side view', 4, 0),
(6, '/ecommerce-project/uploads/products/product_6_1754158691_8817.jpg', 'Classic Black Polo - Styled', 5, 0),

-- Product 7 — Signature Linen Shirt
(7, '/ecommerce-project/uploads/products/product_7_1754461861_2057.jpg', 'Signature Linen Shirt - Front view', 1, 1),
(7, '/ecommerce-project/uploads/products/product_7_1754461861_3557.jpg', 'Signature Linen Shirt - Back view', 2, 0),
(7, '/ecommerce-project/uploads/products/product_7_1754461861_5892.jpg', 'Signature Linen Shirt - Detail', 3, 0),
(7, '/ecommerce-project/uploads/products/product_7_1754461861_7953.jpg', 'Signature Linen Shirt - Side view', 4, 0),
(7, '/ecommerce-project/uploads/products/product_7_1754461861_9325.jpg', 'Signature Linen Shirt - Styled', 5, 0),

-- Product 8 — Merino Wool Crewneck
(8, '/ecommerce-project/uploads/products/product_8_1754483229_1195.jpg', 'Merino Wool Crewneck - Front view', 1, 1),
(8, '/ecommerce-project/uploads/products/product_8_1754483229_2136.jpg', 'Merino Wool Crewneck - Back view', 2, 0),
(8, '/ecommerce-project/uploads/products/product_8_1754483229_4679.jpg', 'Merino Wool Crewneck - Detail', 3, 0),
(8, '/ecommerce-project/uploads/products/product_8_1754483229_5109.jpg', 'Merino Wool Crewneck - Side view', 4, 0),
(8, '/ecommerce-project/uploads/products/product_8_1754483229_8582.jpg', 'Merino Wool Crewneck - Styled', 5, 0),

-- Product 9 — Summer Striped Kurta
(9, '/ecommerce-project/uploads/products/product_9_1754577374_1103.jpg', 'Summer Striped Kurta - Front view', 1, 1),
(9, '/ecommerce-project/uploads/products/product_9_1754577374_1335.jpg', 'Summer Striped Kurta - Back view', 2, 0),
(9, '/ecommerce-project/uploads/products/product_9_1754577374_1352.jpg', 'Summer Striped Kurta - Detail', 3, 0),
(9, '/ecommerce-project/uploads/products/product_9_1754577374_2667.jpg', 'Summer Striped Kurta - Side view', 4, 0),
(9, '/ecommerce-project/uploads/products/product_9_1754577374_4876.jpg', 'Summer Striped Kurta - Styled', 5, 0),

-- Product 10 — Monsoon Layer Jacket
(10, '/ecommerce-project/uploads/products/product_10_1755557619_5955.jpg', 'Monsoon Layer Jacket - Front view', 1, 1),
(10, '/ecommerce-project/uploads/products/product_10_1755557619_6018.jpg', 'Monsoon Layer Jacket - Back view', 2, 0),
(10, '/ecommerce-project/uploads/products/product_10_1755557619_7947.jpg', 'Monsoon Layer Jacket - Detail', 3, 0),
(10, '/ecommerce-project/uploads/products/product_10_1755557619_8677.jpg', 'Monsoon Layer Jacket - Hood detail', 4, 0),
(10, '/ecommerce-project/uploads/products/product_10_1755557619_8836.jpg', 'Monsoon Layer Jacket - Styled', 5, 0),

-- Product 13 — Artist Collab Oversized Tee
(13, '/ecommerce-project/uploads/products/product_13_1755586920_1071.jpeg', 'Artist Collab Tee - Front view', 1, 1),
(13, '/ecommerce-project/uploads/products/product_13_1755586920_3028.jpeg', 'Artist Collab Tee - Back view', 2, 0),
(13, '/ecommerce-project/uploads/products/product_13_1755586920_4130.jpeg', 'Artist Collab Tee - Print detail', 3, 0),
(13, '/ecommerce-project/uploads/products/product_13_1755586920_8646.jpeg', 'Artist Collab Tee - Side view', 4, 0),
(13, '/ecommerce-project/uploads/products/product_13_1755586920_9324.jpeg', 'Artist Collab Tee - Styled', 5, 0),

-- Product 14 — Heritage Block Print Shirt
(14, '/ecommerce-project/uploads/products/product_14_1755609089_2242.jpg', 'Heritage Block Print Shirt - Front view', 1, 1),
(14, '/ecommerce-project/uploads/products/product_14_1755609089_7209.jpg', 'Heritage Block Print Shirt - Back view', 2, 0),
(14, '/ecommerce-project/uploads/products/product_14_1755609089_7383.jpg', 'Heritage Block Print Shirt - Print detail', 3, 0),
(14, '/ecommerce-project/uploads/products/product_14_1755609089_7914.jpg', 'Heritage Block Print Shirt - Side view', 4, 0),
(14, '/ecommerce-project/uploads/products/product_14_1755609089_8805.jpg', 'Heritage Block Print Shirt - Styled', 5, 0),

-- Product 15 — Handwoven Silk Bandhgala
(15, '/ecommerce-project/uploads/products/product_15_1755848060_4208.png', 'Handwoven Silk Bandhgala - Front view', 1, 1),
(15, '/ecommerce-project/uploads/products/product_15_1755848060_7299.png', 'Handwoven Silk Bandhgala - Back view', 2, 0),
(15, '/ecommerce-project/uploads/products/product_15_1755848060_8299.png', 'Handwoven Silk Bandhgala - Collar detail', 3, 0),
(15, '/ecommerce-project/uploads/products/product_15_1755848060_9589.png', 'Handwoven Silk Bandhgala - Styled', 4, 0);

-- =============================================================
-- Verify
-- =============================================================
SELECT
  p.id,
  p.name,
  c.name AS category,
  p.price,
  p.stock_quantity,
  p.featured,
  COUNT(pi.id) AS image_count
FROM products p
JOIN categories c ON p.category_id = c.id
LEFT JOIN product_images pi ON pi.product_id = p.id
GROUP BY p.id
ORDER BY p.id;
