-- สำหรับผู้ที่มีฐานข้อมูลอยู่แล้ว: รันไฟล์นี้ใน phpMyAdmin (แท็บ SQL) เพื่อเพิ่มตารางรูปเพิ่มเติม
-- ไม่ลบข้อมูลเดิม
-- เลือกฐานข้อมูลของคุณใน phpMyAdmin ก่อนรันไฟล์นี้
CREATE TABLE IF NOT EXISTS account_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  INDEX idx_account_images (account_id, sort_order),
  CONSTRAINT fk_images_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;
