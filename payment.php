<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_login();
release_expired_orders($pdo);

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) redirect('my-orders.php');

$stmt = $pdo->prepare('SELECT o.*, p.status AS payment_status FROM orders o JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?');
$stmt->execute([$orderId, $_SESSION['user']['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('ไม่พบคำสั่งซื้อ');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paid = false;
    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare("SELECT id, account_id FROM orders WHERE id=? AND user_id=? AND status='pending' FOR UPDATE");
        $lock->execute([$orderId, $_SESSION['user']['id']]);
        $row = $lock->fetch();
        if ($row) {
            $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=?")->execute([$orderId]);
            $pdo->prepare("UPDATE payments SET status='verified' WHERE order_id=?")->execute([$orderId]);
            $pdo->prepare("UPDATE accounts SET status='sold' WHERE id=?")->execute([$row['account_id']]);
            $paid = true;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    if ($paid) {
        set_flash('จำลองการชำระเงินสำเร็จ (ไม่มีการตัดเงินจริง) ดูข้อมูลไอดีได้ด้านล่าง');
        redirect('order.php?id=' . $orderId);
    }
    set_flash('คำสั่งซื้อนี้หมดเวลาหรือถูกยกเลิกแล้ว', 'error');
    redirect('my-orders.php');
}
$pageTitle = 'ชำระเงินจำลอง';
require 'includes/header.php';
?>
<div class="auth-wrap"><section class="form-card">
  <span class="eyebrow">DEMO PAYMENT</span><h1>ชำระเงินจำลอง</h1>
  <div class="notice">หน้านี้เป็นเพียงการทดสอบ ห้ามโอนเงินจริง และไม่มีการเชื่อมต่อธนาคารหรือ Payment Gateway</div>
  <p>หมายเลขคำสั่งซื้อ: <strong>#<?= (int)$order['id'] ?></strong><br><?= e($order['account_title']) ?></p>
  <div class="summary-total"><span>ยอดทดสอบ</span><strong>฿<?= money($order['total']) ?></strong></div>
  <?php if ($order['status'] === 'pending'): ?>
    <p class="muted">กรุณากดยืนยันภายใน <?= (int)ORDER_TIMEOUT_MIN ?> นาทีหลังสร้างคำสั่งซื้อ ไม่เช่นนั้นระบบจะยกเลิกอัตโนมัติ</p>
    <form method="post"><?= csrf_field() ?><button class="btn btn-full">ยืนยันการชำระเงิน (จำลอง)</button></form>
  <?php elseif ($order['status'] === 'cancelled'): ?>
    <p class="alert error">คำสั่งซื้อนี้ถูกยกเลิกแล้ว</p>
  <?php else: ?>
    <p class="alert success">คำสั่งซื้อนี้ชำระเงินจำลองแล้ว</p>
  <?php endif; ?>
  <a class="text-link" href="order.php?id=<?= (int)$order['id'] ?>">ดูรายละเอียดคำสั่งซื้อ</a>
</section></div>
<?php require 'includes/footer.php'; ?>
