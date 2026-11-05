<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
release_expired_orders($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    if ($id && $action === 'complete') {
        $n = $pdo->prepare("UPDATE orders SET status='completed' WHERE id=? AND status='paid'");
        $n->execute([$id]);
        set_flash($n->rowCount() ? 'ปิดงานคำสั่งซื้อแล้ว' : 'ทำรายการไม่ได้', $n->rowCount() ? 'success' : 'error');
    } elseif ($id && $action === 'cancel') {
        if (cancel_pending_order($pdo, $id)) set_flash('ยกเลิกคำสั่งซื้อและคืนไอดีเข้าร้านแล้ว');
        else set_flash('ยกเลิกได้เฉพาะคำสั่งซื้อที่รอชำระเงิน', 'error');
    }
    redirect('admin/orders.php');
}

$orders = $pdo->query('SELECT o.id, o.account_title, o.total, o.status, o.created_at, u.username
                       FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 200')->fetchAll();
$pageTitle = 'จัดการคำสั่งซื้อ';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head"><span class="eyebrow">ADMIN</span><h1>คำสั่งซื้อ</h1><p>ชำระเงินจำลองแล้วจะเป็น "ชำระแล้ว" แอดมินกด "ปิดงาน" เมื่อส่งมอบเรียบร้อย</p></div>
<?php if (!$orders): ?><div class="empty-state">ยังไม่มีคำสั่งซื้อ</div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>เลขที่</th><th>ผู้ซื้อ</th><th>ไอดี</th><th>ยอดรวม</th><th>สถานะ</th><th>วันที่</th><th></th></tr></thead><tbody>
<?php foreach ($orders as $o): ?>
<tr>
  <td>#<?= (int)$o['id'] ?></td>
  <td><?= e($o['username']) ?></td>
  <td><?= e($o['account_title']) ?></td>
  <td>฿<?= money($o['total']) ?></td>
  <td><span class="status <?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
  <td><?= e($o['created_at']) ?></td>
  <td class="actions">
  <?php if ($o['status'] === 'paid'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="btn btn-small">ปิดงาน</button></form>
  <?php elseif ($o['status'] === 'pending'): ?>
    <form method="post" data-confirm="ยกเลิกคำสั่งซื้อนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="btn btn-small btn-danger">ยกเลิก</button></form>
  <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
