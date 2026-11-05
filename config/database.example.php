<?php
// ===== ตั้งค่าให้ตรงกับเครื่องของคุณ (ค่าเริ่มต้นของ XAMPP) =====
// InfinityFree: DB_HOST = 'sqlXXX.infinityfree.com', DB_NAME/DB_USER ขึ้นต้นด้วย if0_xxxxxxxx (ดูที่ MySQL Databases)
const DB_HOST = 'localhost';
const DB_NAME = 'phuwitshop'; // ต้องตรงกับชื่อฐานข้อมูลที่สร้างจริง (สร้างโดยตั้งชื่อว่า phuwitshop)
const DB_USER = 'root';
const DB_PASS = '';

// path ของเว็บบนเซิร์ฟเวอร์ ต้องขึ้นต้นและลงท้ายด้วย /
// ปล่อยว่าง '' = ให้ระบบหาเองอัตโนมัติ (ใช้ได้ทั้ง XAMPP และ InfinityFree) ถ้าหาผิดค่อยกำหนดเอง เช่น '/shop/'
const BASE_URL_MANUAL = '';
(function () {
    if (BASE_URL_MANUAL !== '') { define('BASE_URL', BASE_URL_MANUAL); return; }
    $root = realpath(__DIR__ . '/..');
    $doc  = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $base = '/';
    if ($root && $doc && strpos($root, $doc) === 0) {
        $base = '/' . trim(str_replace('\\', '/', substr($root, strlen($doc))), '/') . '/';
        $base = str_replace('//', '/', $base);
    }
    define('BASE_URL', $base);
})();

// ใช้เวลาไทยทั้งใน PHP และ MySQL เพื่อให้รายงานยอดขายรายวันไม่เหลื่อมวัน
date_default_timezone_set('Asia/Bangkok');

// คีย์ใช้เข้ารหัสข้อมูลล็อกอินของไอดีเกม (AES-256-GCM)
// เปลี่ยนเป็นข้อความสุ่มยาวๆ ก่อนเพิ่มข้อมูล และห้ามเปลี่ยนภายหลัง ไม่งั้นถอดรหัสข้อมูลเดิมไม่ได้
const APP_KEY = 'CHANGE_ME_RANDOM_64_HEX_CHARS';

// ลิงก์ Portfolio ของคุณ (ต้องขึ้นต้นด้วย http:// หรือ https://) ถ้าเว้นว่าง เมนูจะขึ้นข้อความให้ใส่ลิงก์เอง
const PORTFOLIO_URL = '';

// เวลาที่ระบบจองไอดีไว้ให้ผู้ซื้อ (นาที) เกินแล้วจะยกเลิกคำสั่งซื้ออัตโนมัติ
const ORDER_TIMEOUT_MIN = 30;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบค่าใน config/database.php');
}
