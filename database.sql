-- PhuwitShop : ตลาดซื้อขายไอดีเกม (ผู้ขาย = แอดมิน, เกม = กำหนดเอง, ชำระเงิน = จำลอง)
-- หมายเหตุ: ไฟล์นี้จะลบตารางของระบบเติมเกมเดิม (products, order_items ฯลฯ) แล้วสร้างใหม่
--           ตาราง users ไม่ถูกลบ บัญชีเดิมยังใช้ได้
-- ต้องสร้างฐานข้อมูลและเลือกฐานข้อมูลนั้นใน phpMyAdmin ก่อน Import
-- (XAMPP: สร้างชื่อ phuwitshop, collation utf8mb4_unicode_ci / InfinityFree: ใช้ฐานข้อมูลที่สร้างใน MySQL Databases)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, order_items, orders, products, account_images, account_credentials, accounts, games;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- เกม: แอดมินเพิ่ม/แก้ไขเองได้จากหน้าจัดการร้าน
CREATE TABLE games (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  image VARCHAR(500) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ไอดีเกมที่ลงขาย (ข้อมูลสาธารณะ) 1 แถว = 1 ไอดี ขายได้ครั้งเดียว
CREATE TABLE accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  rank_level VARCHAR(100) DEFAULT NULL,
  server VARCHAR(100) DEFAULT NULL,
  highlights TEXT DEFAULT NULL,
  description TEXT DEFAULT NULL,
  image VARCHAR(500) DEFAULT NULL,
  status ENUM('available','reserved','sold','hidden') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_accounts_list (status, game_id),
  CONSTRAINT fk_accounts_game FOREIGN KEY (game_id) REFERENCES games(id)
) ENGINE=InnoDB;

-- ข้อมูลล็อกอินของไอดี (เข้ารหัสด้วย APP_KEY) แยกตารางเพื่อไม่ให้หลุดไปกับคิวรีหน้าสาธารณะ
CREATE TABLE account_credentials (
  account_id INT PRIMARY KEY,
  login_id_enc TEXT NOT NULL,
  login_secret_enc TEXT NOT NULL,
  note_enc TEXT DEFAULT NULL,
  CONSTRAINT fk_cred_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- รูปเพิ่มเติมของไอดี (แสดงเป็นแกลเลอรีในหน้ารายละเอียด)
CREATE TABLE account_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  INDEX idx_account_images (account_id, sort_order),
  CONSTRAINT fk_images_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  account_id INT NOT NULL,
  account_title VARCHAR(200) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_orders_status (status, created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_account FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  method VARCHAR(50) NOT NULL DEFAULT 'demo',
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

INSERT INTO games (name, description) VALUES
('Mobile Legends', 'ไอดี Mobile Legends: Bang Bang'),
('Free Fire', 'ไอดี Free Fire'),
('PUBG Mobile', 'ไอดี PUBG Mobile'),
('Roblox', 'ไอดี Roblox'),
('ROV', 'ไอดี ROV (Arena of Valor)');
