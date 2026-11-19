-- ============================================================
-- COSTPLAY WONDERLAND - Database Schema
-- Full E-Commerce + Rental Platform
-- ============================================================

CREATE DATABASE IF NOT EXISTS costplay_wonderland CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE costplay_wonderland;

-- ─────────────────────────────────────────────────────────────
-- USERS & ACCOUNTS
-- ─────────────────────────────────────────────────────────────

CREATE TABLE user_accounts (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  UNIQUE NOT NULL,
  email         VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('super_admin','admin','seller','customer') DEFAULT 'customer',
  avatar_url    VARCHAR(255) DEFAULT NULL,
  is_active     TINYINT(1)  DEFAULT 1,
  created_at    DATETIME    DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE customer_profiles (
  customer_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT  UNIQUE NOT NULL,
  first_name     VARCHAR(50),
  last_name      VARCHAR(50),
  phone          VARCHAR(20),
  address        TEXT,
  valid_id_type  VARCHAR(50),
  valid_id_no    VARCHAR(100),
  valid_id_image VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

CREATE TABLE login_logs (
  log_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  ip_address VARCHAR(45),
  user_agent TEXT,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  status     ENUM('success','failed') DEFAULT 'success',
  FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- STORES (Seller Shops)
-- ─────────────────────────────────────────────────────────────

CREATE TABLE stores (
  store_id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNIQUE NOT NULL,
  store_name    VARCHAR(100) NOT NULL,
  store_slug    VARCHAR(100) UNIQUE NOT NULL,
  description   TEXT,
  banner_url    VARCHAR(255),
  logo_url      VARCHAR(255),
  contact_phone VARCHAR(20),
  address       TEXT,
  is_verified   TINYINT(1) DEFAULT 0,
  is_active     TINYINT(1) DEFAULT 1,
  rating        DECIMAL(3,2) DEFAULT 0.00,
  total_sales   INT DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- PRODUCTS / CATALOG
-- ─────────────────────────────────────────────────────────────

CREATE TABLE media_titles (
  media_id    INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  media_type  ENUM('anime','manga','game','movie','series','other') NOT NULL,
  cover_image VARCHAR(255),
  description TEXT
);

CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) UNIQUE NOT NULL,
  icon        VARCHAR(100),
  description TEXT
);

CREATE TABLE products (
  product_id          INT AUTO_INCREMENT PRIMARY KEY,
  store_id            INT NOT NULL,
  media_id            INT DEFAULT NULL,
  category_id         INT DEFAULT NULL,
  product_name        VARCHAR(200) NOT NULL,
  character_name      VARCHAR(100),
  description         TEXT,
  listing_type        ENUM('sale','rent','both') NOT NULL DEFAULT 'both',
  sale_price          DECIMAL(10,2) DEFAULT NULL,
  rental_price_per_day DECIMAL(10,2) DEFAULT NULL,
  rental_deposit      DECIMAL(10,2) DEFAULT NULL,
  size                VARCHAR(50),
  stock_quantity      INT DEFAULT 1,
  rental_stock        INT DEFAULT 1,
  condition_rating    ENUM('new','like_new','good','fair') DEFAULT 'new',
  main_image          VARCHAR(255),
  images              TEXT,
  tags                VARCHAR(255),
  is_featured         TINYINT(1) DEFAULT 0,
  is_active           TINYINT(1) DEFAULT 1,
  view_count          INT DEFAULT 0,
  created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (store_id)    REFERENCES stores(store_id)      ON DELETE CASCADE,
  FOREIGN KEY (media_id)    REFERENCES media_titles(media_id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────────────────────────
-- CART
-- ─────────────────────────────────────────────────────────────

CREATE TABLE cart_items (
  cart_item_id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  product_id        INT NOT NULL,
  quantity          INT DEFAULT 1,
  item_type         ENUM('purchase','rental') NOT NULL,
  rental_start_date DATE DEFAULT NULL,
  rental_end_date   DATE DEFAULT NULL,
  added_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES user_accounts(user_id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id)   ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- ORDERS (Purchases)
-- ─────────────────────────────────────────────────────────────

CREATE TABLE orders (
  order_id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  store_id         INT NOT NULL,
  total_amount     DECIMAL(10,2) NOT NULL,
  shipping_address TEXT,
  order_status     ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  payment_status   ENUM('unpaid','paid','refunded') DEFAULT 'unpaid',
  payment_method   VARCHAR(50),
  notes            TEXT,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES user_accounts(user_id) ON DELETE CASCADE,
  FOREIGN KEY (store_id) REFERENCES stores(store_id)       ON DELETE CASCADE
);

CREATE TABLE order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  product_id    INT NOT NULL,
  quantity      INT NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(order_id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- RENTALS
-- ─────────────────────────────────────────────────────────────

CREATE TABLE rentals (
  rental_id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  store_id       INT NOT NULL,
  total_amount   DECIMAL(10,2) NOT NULL,
  deposit_amount DECIMAL(10,2) NOT NULL,
  rental_status  ENUM('pending','confirmed','active','returned','overdue','cancelled') DEFAULT 'pending',
  payment_status ENUM('unpaid','deposit_paid','fully_paid','refunded') DEFAULT 'unpaid',
  payment_method VARCHAR(50),
  notes          TEXT,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES user_accounts(user_id) ON DELETE CASCADE,
  FOREIGN KEY (store_id) REFERENCES stores(store_id)       ON DELETE CASCADE
);

CREATE TABLE rental_items (
  rental_item_id  INT AUTO_INCREMENT PRIMARY KEY,
  rental_id       INT NOT NULL,
  product_id      INT NOT NULL,
  rental_start_date DATE NOT NULL,
  rental_end_date   DATE NOT NULL,
  days_count      INT NOT NULL,
  daily_rate      DECIMAL(10,2) NOT NULL,
  deposit_per_item DECIMAL(10,2) NOT NULL,
  subtotal        DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (rental_id)   REFERENCES rentals(rental_id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id)  REFERENCES products(product_id)  ON DELETE CASCADE
);

CREATE TABLE return_records (
  return_id         INT AUTO_INCREMENT PRIMARY KEY,
  rental_id         INT NOT NULL,
  rental_item_id    INT NOT NULL,
  return_date       DATETIME NOT NULL,
  condition_status  ENUM('perfect','minor_damage','major_damage','lost') DEFAULT 'perfect',
  inspection_notes  TEXT,
  damage_fee        DECIMAL(10,2) DEFAULT 0.00,
  deposit_returned  DECIMAL(10,2) DEFAULT 0.00,
  inspected_by      INT DEFAULT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rental_id)      REFERENCES rentals(rental_id)       ON DELETE CASCADE,
  FOREIGN KEY (rental_item_id) REFERENCES rental_items(rental_item_id) ON DELETE CASCADE,
  FOREIGN KEY (inspected_by)   REFERENCES user_accounts(user_id)   ON DELETE SET NULL
);

-- ─────────────────────────────────────────────────────────────
-- PAYMENTS
-- ─────────────────────────────────────────────────────────────

CREATE TABLE payments (
  payment_id     INT AUTO_INCREMENT PRIMARY KEY,
  reference_type ENUM('order','rental') NOT NULL,
  reference_id   INT NOT NULL,
  amount         DECIMAL(10,2) NOT NULL,
  payment_method ENUM('gcash','maya','bank_transfer','cod','credit_card') NOT NULL,
  transaction_id VARCHAR(100),
  status         ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  paid_at        DATETIME DEFAULT NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────────────────────
-- REVIEWS & EXTRAS
-- ─────────────────────────────────────────────────────────────

CREATE TABLE reviews (
  review_id            INT AUTO_INCREMENT PRIMARY KEY,
  product_id           INT NOT NULL,
  user_id              INT NOT NULL,
  order_item_id        INT DEFAULT NULL,
  rating               TINYINT NOT NULL,
  title                VARCHAR(200),
  body                 TEXT,
  images               TEXT,
  is_verified_purchase TINYINT(1) DEFAULT 0,
  created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(product_id)   ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

CREATE TABLE wishlists (
  wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  product_id  INT NOT NULL,
  added_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_wishlist (user_id, product_id),
  FOREIGN KEY (user_id)    REFERENCES user_accounts(user_id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id)   ON DELETE CASCADE
);

CREATE TABLE notifications (
  notif_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    TEXT NOT NULL,
  type       VARCHAR(50),
  link       VARCHAR(255),
  is_read    TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────────────────────────
-- SEED DATA
-- ─────────────────────────────────────────────────────────────

-- Users (passwords all = "Admin@123" hashed)
INSERT INTO user_accounts (username, email, password_hash, role) VALUES
('superadmin',    'superadmin@costplay.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'),
('admin1',        'admin@costplay.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('otakustore_ph', 'otakustore@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller'),
('animehaven',    'animehaven@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller'),
('sakura_crafts', 'sakura@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller'),
('neon_cosplay',  'neon@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller'),
('konkai_shop',   'konkai@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller'),
('sakura_chan',   'sakura_chan@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('naruto_fan99',  'naruto99@example.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('luffy_d_monkey','luffy@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('tanjiro_kamado','tanjiro@example.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('deku_izuku',    'deku@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

-- Customer profiles
INSERT INTO customer_profiles (user_id, first_name, last_name, phone, address) VALUES
(8,  'Sakura',  'Reyes',   '09171234567', 'Quezon City, Metro Manila'),
(9,  'Marco',   'Santos',  '09281234567', 'Makati, Metro Manila'),
(10, 'Luffy',   'Garcia',  '09391234567', 'Pasig, Metro Manila'),
(11, 'Tanjiro', 'Dela Cruz','09451234567','Caloocan, Metro Manila'),
(12, 'Izuku',   'Midoriya', '09561234567','Taguig, Metro Manila');

-- Stores
INSERT INTO stores (user_id, store_name, store_slug, description, is_verified, rating) VALUES
(3, 'Otaku Store PH',   'otaku-store-ph',  'Your one-stop shop for premium anime cosplay costumes. We carry costumes from all major anime series with great quality and affordable prices.', 1, 4.80),
(4, 'Anime Haven',      'anime-haven',     'Specializing in handcrafted cosplay costumes and accessories. Each piece is made with love for the cosplay community.', 1, 4.65),
(5, 'Sakura Crafts',    'sakura-crafts',   'Handmade cosplay pieces with attention to detail. We also offer custom orders and alterations.', 1, 4.90),
(6, 'Neon Cosplay',     'neon-cosplay',    'Modern cosplay with LED and neon accents. Specializing in sci-fi and futuristic anime characters.', 0, 4.50),
(7, 'Konkai Shop',      'konkai-shop',     'Budget-friendly cosplay for every fan. Great for beginners and experienced cosplayers alike.', 1, 4.30);

-- Media Titles
INSERT INTO media_titles (title, media_type, cover_image) VALUES
('Attack on Titan',      'anime', 'https://picsum.photos/seed/aot/300/400'),
('Naruto Shippuden',     'anime', 'https://picsum.photos/seed/naruto/300/400'),
('One Piece',            'anime', 'https://picsum.photos/seed/onepiece/300/400'),
('Demon Slayer',         'anime', 'https://picsum.photos/seed/demonslayer/300/400'),
('My Hero Academia',     'anime', 'https://picsum.photos/seed/mha/300/400'),
('Genshin Impact',       'game',  'https://picsum.photos/seed/genshin/300/400'),
('Jujutsu Kaisen',       'anime', 'https://picsum.photos/seed/jjk/300/400'),
('Sword Art Online',     'anime', 'https://picsum.photos/seed/sao/300/400'),
('Final Fantasy XIV',    'game',  'https://picsum.photos/seed/ff14/300/400'),
('Chainsaw Man',         'anime', 'https://picsum.photos/seed/chainsawman/300/400'),
('Bleach',               'anime', 'https://picsum.photos/seed/bleach/300/400'),
('Fullmetal Alchemist',  'anime', 'https://picsum.photos/seed/fma/300/400');

-- Categories
INSERT INTO categories (name, slug, icon, description) VALUES
('Full Costume Set',  'full-set',    '🎭', 'Complete head-to-toe cosplay outfits'),
('Weapons & Props',   'weapons',     '⚔️', 'Replica swords, staffs, and other props'),
('Accessories',       'accessories', '💎', 'Jewelry, belts, bags, and small accessories'),
('Footwear',          'footwear',    '👟', 'Boots, shoes, and sandals for cosplay'),
('Wigs & Hair',       'wigs',        '💇', 'Styled wigs for various characters'),
('Armor & Armor Sets','armor',       '🛡️', 'Full armor suits and individual armor pieces');

-- Products
INSERT INTO products (store_id, media_id, category_id, product_name, character_name, description, listing_type, sale_price, rental_price_per_day, rental_deposit, size, stock_quantity, rental_stock, condition_rating, main_image, is_featured) VALUES
(1, 1, 1, 'Eren Yeager Survey Corps Uniform', 'Eren Yeager', 'Full Survey Corps uniform set including jacket, pants, belts, and ODM gear replica. High-quality fabric.', 'both', 2500.00, 350.00, 1500.00, 'M', 3, 2, 'new', 'https://picsum.photos/seed/eren/400/500', 1),
(1, 2, 1, 'Naruto Uzumaki Orange Jumpsuit', 'Naruto Uzumaki', 'The iconic orange jumpsuit of the Seventh Hokage. Complete set with headband.', 'both', 1800.00, 250.00, 1000.00, 'M', 5, 3, 'like_new', 'https://picsum.photos/seed/naruto2/400/500', 1),
(1, 7, 1, 'Gojo Satoru Uniform', 'Gojo Satoru', 'Dark blue uniform with signature blindfold. Premium quality fabric and stitching.', 'both', 2200.00, 300.00, 1200.00, 'L', 2, 1, 'new', 'https://picsum.photos/seed/gojo/400/500', 1),
(1, 4, 2, 'Nichirin Sword - Tanjiro Style', 'Tanjiro Kamado', 'Black Nichirin sword replica, non-sharp, safe for conventions. 90cm length.', 'sale', 1500.00, NULL, NULL, 'One Size', 10, 0, 'new', 'https://picsum.photos/seed/sword1/400/500', 0),
(1, 5, 1, 'Deku Full Cowl Hero Suit', 'Izuku Midoriya', 'Green full cowl hero suit with matching gloves and boots. Includes mask.', 'both', 3000.00, 450.00, 1800.00, 'M', 2, 1, 'new', 'https://picsum.photos/seed/deku/400/500', 1),

(2, 3, 1, 'Monkey D. Luffy Gear 5 Outfit', 'Monkey D. Luffy', 'White Gear 5 outfit with matching hat and sandals. High quality cotton blend.', 'both', 2800.00, 400.00, 1600.00, 'M', 3, 2, 'new', 'https://picsum.photos/seed/luffy/400/500', 1),
(2, 6, 1, 'Hu Tao Genshin Impact Costume', 'Hu Tao', 'Complete Hu Tao set with hat, dress, and accessories. Great for gaming conventions.', 'both', 3500.00, 500.00, 2000.00, 'S', 2, 1, 'new', 'https://picsum.photos/seed/hutao/400/500', 1),
(2, 11, 1, 'Ichigo Bankai Shinigami Robe', 'Ichigo Kurosaki', 'Full black shinigami robe set. Includes Zangetsu sword replica.', 'both', 2600.00, 380.00, 1400.00, 'L', 2, 1, 'like_new', 'https://picsum.photos/seed/ichigo/400/500', 0),
(2, 9, 1, 'Cloud Strife FFXIV Costume', 'Cloud Strife', 'Full Cloud Strife set with Buster Sword replica and SOLDIER uniform.', 'both', 4500.00, 650.00, 2500.00, 'M', 1, 1, 'new', 'https://picsum.photos/seed/cloud/400/500', 1),
(2, 5, 6, 'All Might Bronze Age Armor', 'All Might', 'Iconic All Might armor set with matching cape. Foam-based for comfort.', 'rent', NULL, 800.00, 3000.00, 'XL', 0, 1, 'like_new', 'https://picsum.photos/seed/allmight/400/500', 0),

(3, 4, 1, 'Nezuko Kamado Full Set', 'Nezuko Kamado', 'Handcrafted Nezuko kimono with matching bow and bamboo mouth piece prop. Exquisite detail.', 'both', 3200.00, 480.00, 2000.00, 'S', 3, 2, 'new', 'https://picsum.photos/seed/nezuko/400/500', 1),
(3, 6, 5, 'Raiden Shogun Wig', 'Raiden Shogun', 'Premium quality purple wig styled as Raiden Shogun. Heat resistant fibers.', 'sale', 800.00, NULL, NULL, 'One Size', 8, 0, 'new', 'https://picsum.photos/seed/raidenshogun2/400/500', 0),
(3, 7, 1, 'Nobara Kugisaki Uniform', 'Nobara Kugisaki', 'Full uniform set with accessories and hammer+nail prop replica.', 'both', 2400.00, 350.00, 1500.00, 'M', 2, 2, 'new', 'https://picsum.photos/seed/nobara/400/500', 0),
(3, 10, 1, 'Power Chainsaw Man Costume', 'Power', 'Complete Power costume with horns, cape, and blood splatter effects (washable paint).', 'both', 2800.00, 400.00, 1600.00, 'M', 2, 1, 'new', 'https://picsum.photos/seed/power/400/500', 1),
(3, 2, 2, 'Kunai Set + Shuriken Bundle', 'Various Naruto', 'Set of 10 foam kunai and 5 shuriken, convention-safe. Includes carrying pouch.', 'sale', 600.00, NULL, NULL, 'One Size', 20, 0, 'new', 'https://picsum.photos/seed/kunai/400/500', 0),

(4, 8, 1, 'Kirito Alicization Costume', 'Kirito', 'Blue Alicization robe with armor accents and LED highlights. Includes sword.', 'both', 3800.00, 550.00, 2200.00, 'M', 2, 1, 'new', 'https://picsum.photos/seed/kirito/400/500', 1),
(4, 8, 1, 'Asuna Alicization Uniform', 'Asuna Yuuki', 'White Alicization uniform with LED accents. Includes rapier prop.', 'both', 3600.00, 520.00, 2100.00, 'S', 2, 1, 'new', 'https://picsum.photos/seed/asuna/400/500', 0),
(4, 9, 6, 'Paladin Tank Armor Set', 'FFXIV Paladin', 'Full plate armor set with LED under-lighting effect. PVC + foam construction.', 'rent', NULL, 900.00, 3500.00, 'L', 0, 1, 'like_new', 'https://picsum.photos/seed/paladin/400/500', 0),
(4, 1, 1, 'Levi Ackerman Survey Corps Uniform', 'Levi Ackerman', 'Premium Levi uniform with ODM gear replica and dual blades prop.', 'both', 2900.00, 420.00, 1700.00, 'S', 3, 2, 'new', 'https://picsum.photos/seed/levi/400/500', 1),

(5, 2, 1, 'Kakashi Hatake Jonin Outfit', 'Kakashi Hatake', 'Classic Kakashi jonin vest and uniform. Includes mask and headband.', 'both', 1600.00, 220.00, 900.00, 'L', 4, 3, 'good', 'https://picsum.photos/seed/kakashi/400/500', 0),
(5, 3, 1, 'Zoro Three Sword Style Outfit', 'Roronoa Zoro', 'Zoro post-timeskip outfit with 3 katana replica set. Great value!', 'both', 2200.00, 320.00, 1300.00, 'L', 3, 2, 'like_new', 'https://picsum.photos/seed/zoro/400/500', 0),
(5, 12, 1, 'Edward Elric Alchemist Coat', 'Edward Elric', 'Red alchemy coat with auto-mail arm prop. Includes wig.', 'both', 2000.00, 280.00, 1100.00, 'S', 3, 2, 'good', 'https://picsum.photos/seed/edward/400/500', 0),
(5, 10, 1, 'Denji Chainsaw Man Costume', 'Denji', 'Full Denji outfit with chainsaw head prop. Includes blood effect accessories.', 'both', 2400.00, 350.00, 1400.00, 'M', 2, 1, 'new', 'https://picsum.photos/seed/denji/400/500', 1);

-- Sample Reviews
INSERT INTO reviews (product_id, user_id, rating, title, body, is_verified_purchase) VALUES
(1, 8,  5, 'Perfect Survey Corps Uniform!', 'The quality is amazing and it fits perfectly. Very detailed with the ODM gear replica. Highly recommend for AoT fans!', 1),
(2, 9,  4, 'Great Naruto Costume', 'The orange jumpsuit is vibrant and well-made. The headband is a nice touch. Slight delay in shipping but worth it.', 1),
(6, 10, 5, 'Best Luffy Gear 5 out there!', 'Found this for my AniFest appearance. The white outfit is superb quality and the hat is spot on!', 1),
(11,8,  5, 'Nezuko is Perfect!', 'The handcrafted details on this Nezuko costume are incredible. The bow is so cute and the bamboo prop is well-made.', 1),
(16,9,  4, 'Great Kirito costume with LED!', 'The LED highlights really make this costume stand out at night conventions. Great quality overall.', 1);

-- Notifications for users
INSERT INTO notifications (user_id, title, message, type) VALUES
(8, 'Welcome to Costplay Wonderland! 🎌', 'Start exploring thousands of cosplay costumes for sale and rent. Find your favorite character today!', 'welcome'),
(9, 'Welcome to Costplay Wonderland! 🎌', 'Start exploring thousands of cosplay costumes for sale and rent. Find your favorite character today!', 'welcome');
