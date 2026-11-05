<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_login();
release_expired_orders($pdo);

$stmt = $pdo->prepare('SELECT o.id, o.account_title, o.total, o.status, o.created_at, g.name AS game_name
                       FROM orders o JOIN accounts a ON a.id=o.account_id JOIN games g ON g.id=a.game_id
                       WHERE o.user_id=? ORDER BY o.id DESC');
$stmt->execute([$_SESSION['user']['id']]);
$orders = $stmt->fetchAll();
$pageTitle = 'คำสั่งซื้อของฉัน';
require 'includes/header.php';
?>
<div class="page-head"><span class="eyebrow">ORDER HISTORY</span><h1>คำสั่งซื้อของฉัน</h1><p>ติดตามรายการสั่งซื้อและดูข้อมูลไอดีที่ชำระแล้ว</p></div>
<?php if (!$orders): ?><div class="empty-state">ยังไม่มีคำสั่งซื้อ <a href="index.php">เลือกชมไอดีเกม</a></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>เลขที่</th><th>เกม</th><th>ไอดี</th><th>ยอดรวม</th><th>สถานะ</th><th>วันที่</th><th></th></tr></thead><tbody>
<?php foreach ($orders as $o): ?>
<tr>
  <td>#<?= (int)$o['id'] ?></td>
  <td><?= e($o['game_name']) ?></td>
  <td><?= e($o['account_title']) ?></td>
  <td>฿<?= money($o['total']) ?></td>
  <td><span class="status <?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
  <td><?= e($o['created_at']) ?></td>
  <td>
    <?php if ($o['status'] === 'pending'): ?><a class="btn btn-small" href="payment.php?order_id=<?= (int)$o['id'] ?>">ชำระเงิน</a>
    <?php else: ?><a class="text-link" href="order.php?id=<?= (int)$o['id'] ?>">รายละเอียด</a><?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
