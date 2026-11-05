<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_login();
release_expired_orders($pdo);

$accountId = filter_input(INPUT_GET, 'account_id', FILTER_VALIDATE_INT);
if (!$accountId) redirect('index.php');

$stmt = $pdo->prepare("SELECT a.id, a.title, a.price, a.rank_level, a.server, a.status, g.name AS game_name
                       FROM accounts a JOIN games g ON g.id=a.game_id
                       WHERE a.id=? AND g.status=1");
$stmt->execute([$accountId]);
$account = $stmt->fetch();
if (!$account || $account['status'] !== 'available') {
    set_flash('ไอดีนี้ไม่พร้อมขายแล้ว', 'error');
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pdo->beginTransaction();
        // ล็อกแถวไอดีและอ่านราคาปัจจุบันจากฐานข้อมูล ไม่รับราคาจากฟอร์ม
        $lock = $pdo->prepare('SELECT id, title, price, status FROM accounts WHERE id=? FOR UPDATE');
        $lock->execute([$accountId]);
        $current = $lock->fetch();
        if (!$current || $current['status'] !== 'available') {
            throw new RuntimeException('ไอดีนี้ถูกจองหรือขายไปแล้ว');
        }

        $pdo->prepare('INSERT INTO orders (user_id, account_id, account_title, total) VALUES (?,?,?,?)')
            ->execute([$_SESSION['user']['id'], $current['id'], $current['title'], $current['price']]);
        $orderId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE accounts SET status='reserved' WHERE id=?")->execute([$current['id']]);
        $pdo->prepare("INSERT INTO payments (order_id, method, status) VALUES (?, 'demo', 'pending')")->execute([$orderId]);

        $pdo->commit();
        redirect('payment.php?order_id=' . $orderId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'ไม่สามารถสร้างคำสั่งซื้อได้ กรุณาลองใหม่';
    }
}
$pageTitle = 'ยืนยันคำสั่งซื้อ';
require 'includes/header.php';
?>
<div class="page-head"><span class="eyebrow">CHECKOUT</span><h1>ยืนยันคำสั่งซื้อ</h1><p>ระบบจะจองไอดีนี้ให้คุณ <?= (int)ORDER_TIMEOUT_MIN ?> นาที เพื่อชำระเงิน</p></div>
<div class="checkout-layout">
  <form class="form-card" method="post">
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>เกม<input value="<?= e($account['game_name']) ?>" disabled></label>
    <label>ไอดีที่เลือก<input value="<?= e($account['title']) ?>" disabled></label>
    <p class="muted">หลังชำระเงิน ข้อมูลล็อกอินจะแสดงในหน้าคำสั่งซื้อของคุณ</p>
    <button class="btn btn-full">สร้างคำสั่งซื้อ</button>
  </form>
  <aside class="summary-card"><span class="eyebrow">ORDER SUMMARY</span><h2><?= e($account['title']) ?></h2><p><?= e($account['game_name']) ?></p><div class="summary-total"><span>ยอดรวม</span><strong>฿<?= money($account['price']) ?></strong></div></aside>
</div>
<?php require 'includes/footer.php'; ?>
