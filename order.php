<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_login();
release_expired_orders($pdo);

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) redirect('my-orders.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (cancel_pending_order($pdo, $orderId, (int)$_SESSION['user']['id'])) {
        set_flash('ยกเลิกคำสั่งซื้อแล้ว');
    } else {
        set_flash('ไม่สามารถยกเลิกคำสั่งซื้อนี้ได้', 'error');
    }
    redirect('order.php?id=' . $orderId);
}

$stmt = $pdo->prepare('SELECT o.*, g.name AS game_name FROM orders o
                       JOIN accounts a ON a.id=o.account_id JOIN games g ON g.id=a.game_id
                       WHERE o.id=? AND o.user_id=?');
$stmt->execute([$orderId, $_SESSION['user']['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('ไม่พบคำสั่งซื้อ');
}

// แสดงข้อมูลล็อกอินเฉพาะเจ้าของคำสั่งซื้อที่ชำระแล้วเท่านั้น
$cred = null;
if (in_array($order['status'], ['paid', 'completed'], true)) {
    $c = $pdo->prepare('SELECT login_id_enc, login_secret_enc, note_enc FROM account_credentials WHERE account_id=?');
    $c->execute([$order['account_id']]);
    if ($row = $c->fetch()) {
        $cred = [
            'login_id' => decrypt_text($row['login_id_enc']),
            'secret'   => decrypt_text($row['login_secret_enc']),
            'note'     => $row['note_enc'] ? decrypt_text($row['note_enc']) : '',
        ];
    }
}
$pageTitle = 'คำสั่งซื้อ #' . $order['id'];
require 'includes/header.php';
?>
<div class="page-head"><span class="eyebrow">ORDER #<?= (int)$order['id'] ?></span><h1><?= e($order['account_title']) ?></h1><p><?= e($order['game_name']) ?></p></div>
<div class="checkout-layout">
  <section class="form-card">
    <?php if ($cred): ?>
      <h2>ข้อมูลล็อกอินไอดี</h2>
      <div class="cred-box">
        <div><span>ไอดี / อีเมล</span><code><?= e($cred['login_id']) ?></code></div>
        <div><span>รหัสผ่าน</span><code><?= e($cred['secret']) ?></code></div>
        <?php if ($cred['note'] !== ''): ?><div><span>ข้อมูลเพิ่มเติม</span><code><?= e($cred['note']) ?></code></div><?php endif; ?>
      </div>
      <p class="muted">แนะนำให้เปลี่ยนรหัสผ่านและผูกบัญชีของคุณทันทีหลังเข้าสู่ระบบเกม (ข้อมูลนี้เป็นข้อมูลทดลอง)</p>
    <?php elseif ($order['status'] === 'pending'): ?>
      <div class="notice">ยังไม่ได้ชำระเงิน ข้อมูลไอดีจะแสดงหลังชำระเงิน</div>
      <a class="btn btn-full" href="payment.php?order_id=<?= (int)$order['id'] ?>">ไปหน้าชำระเงิน</a>
      <form method="post" data-confirm="ยืนยันยกเลิกคำสั่งซื้อนี้?"><?= csrf_field() ?><button class="btn btn-full btn-ghost">ยกเลิกคำสั่งซื้อ</button></form>
    <?php else: ?>
      <div class="alert error">คำสั่งซื้อนี้ถูกยกเลิกแล้ว</div>
    <?php endif; ?>
  </section>
  <aside class="summary-card">
    <span class="eyebrow">SUMMARY</span>
    <div class="summary-total"><span>ยอดรวม</span><strong>฿<?= money($order['total']) ?></strong></div>
    <p><span class="status <?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></p>
    <p class="muted">สั่งซื้อเมื่อ <?= e($order['created_at']) ?></p>
  </aside>
</div>
<p><a class="text-link" href="my-orders.php">← กลับไปคำสั่งซื้อของฉัน</a></p>
<?php require 'includes/footer.php'; ?>
