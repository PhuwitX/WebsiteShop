<?php
// รันครั้งเดียวเพื่อสร้างบัญชีแอดมินและไอดีตัวอย่าง แล้วลบไฟล์นี้ทิ้งทันที
require_once 'config/database.php';
require_once 'includes/functions.php';

$messages = [];
$exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($exists) {
    $messages[] = 'มีบัญชีแอดมินอยู่แล้ว ไม่สร้างซ้ำ';
} else {
    $pdo->prepare("INSERT INTO users (username,email,password,role) VALUES ('admin','admin@example.com',?, 'admin')
                   ON DUPLICATE KEY UPDATE role='admin', password=VALUES(password)")
        ->execute([password_hash('Admin123!', PASSWORD_DEFAULT)]);
    $messages[] = 'สร้างบัญชีแอดมินแล้ว (admin / Admin123!)';
}

if (!$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()) {
    $gameIds = $pdo->query('SELECT id FROM games ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
    $samples = [
        ['ไอดีตัวอย่าง แรงค์ Mythic', 899, 'Mythic', 'Asia', "สกิน 120+ ตัว\nฮีโร่ครบ"],
        ['ไอดีตัวอย่าง แรงค์ Heroic', 450, 'Heroic', 'Asia', "สกิน 40+ ตัว\nอีเวนต์ครบ"],
        ['ไอดีตัวอย่าง เริ่มต้น', 129, 'Level 30', 'SEA', "เหมาะสำหรับมือใหม่"],
    ];
    foreach ($samples as $i => $s) {
        if (!isset($gameIds[$i])) break;
        $pdo->prepare('INSERT INTO accounts (game_id,title,price,rank_level,server,highlights) VALUES (?,?,?,?,?,?)')
            ->execute([$gameIds[$i], $s[0], $s[1], $s[2], $s[3], $s[4]]);
        $accId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO account_credentials (account_id,login_id_enc,login_secret_enc,note_enc) VALUES (?,?,?,?)')
            ->execute([$accId, encrypt_text('demo_user_' . $accId . '@example.com'), encrypt_text('demo-pass-' . $accId), encrypt_text('ข้อมูลตัวอย่างสำหรับทดสอบเท่านั้น')]);
    }
    $messages[] = 'เพิ่มไอดีตัวอย่างแล้ว';
}
$pageTitle = 'ตั้งค่าระบบ';
require 'includes/header.php';
?>
<div class="auth-wrap"><section class="form-card">
  <span class="eyebrow">SETUP</span><h1>ตั้งค่าระบบ</h1>
  <?php foreach ($messages as $m): ?><div class="alert success"><?= e($m) ?></div><?php endforeach; ?>
  <div class="notice"><strong>สำคัญ:</strong> ลบไฟล์ <code>setup_admin.php</code> ทันที และเปลี่ยนรหัสผ่านแอดมินก่อนใช้งานต่อ</div>
  <a class="btn btn-full" href="login.php">ไปหน้าเข้าสู่ระบบ</a>
</section></div>
<?php require 'includes/footer.php'; ?>
