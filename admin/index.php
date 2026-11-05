<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
release_expired_orders($pdo);

$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$stats = [
    'สมาชิก'             => (int)$one("SELECT COUNT(*) FROM users WHERE role='user'"),
    'เกมที่เปิดอยู่'        => (int)$one('SELECT COUNT(*) FROM games WHERE status=1'),
    'ไอดีพร้อมขาย'        => (int)$one("SELECT COUNT(*) FROM accounts WHERE status='available'"),
    'ไอดีขายแล้ว'          => (int)$one("SELECT COUNT(*) FROM accounts WHERE status='sold'"),
    'ออเดอร์รอชำระ'       => (int)$one("SELECT COUNT(*) FROM orders WHERE status='pending'"),
    'ยอดขายวันนี้ (฿)'    => money($one("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','completed') AND DATE(COALESCE(paid_at,created_at))=CURDATE()")),
    'ยอดขายเดือนนี้ (฿)'  => money($one("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','completed') AND DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")),
    'ยอดขายปีนี้ (฿)'     => money($one("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','completed') AND YEAR(COALESCE(paid_at,created_at))=YEAR(CURDATE())")),
    'ยอดขายรวม (฿)'     => money($one("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','completed')")),
];
$pageTitle = 'จัดการร้าน';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head"><span class="eyebrow">ADMIN</span><h1>ภาพรวมร้าน</h1><p>ผู้ขายในระบบนี้คือแอดมินเท่านั้น</p></div>
<div class="stat-grid">
<?php foreach ($stats as $label => $value): ?>
  <div class="stat"><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
<?php endforeach; ?>
</div>
<p><a class="btn" href="accounts.php">+ ลงขายไอดีใหม่</a> <a class="btn btn-ghost" href="reports.php">ดูรายงานยอดขาย</a> <a class="btn btn-ghost" href="members.php">ดูสมาชิก</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
