<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
release_expired_orders($pdo);

$one = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

// ----- หน้าดูรายละเอียดสมาชิก 1 คน: ประวัติการซื้อ -----
$memberId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($memberId) {
    $m = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id=?");
    $m->execute([$memberId]);
    $member = $m->fetch();
    if (!$member) { set_flash('ไม่พบสมาชิก', 'error'); redirect('admin/members.php'); }
    $o = $pdo->prepare("SELECT id, account_title, total, status, created_at, paid_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 200");
    $o->execute([$memberId]);
    $orders = $o->fetchAll();
    $spent = 0; $bought = 0;
    foreach ($orders as $x) if (in_array($x['status'], ['paid', 'completed'], true)) { $spent += $x['total']; $bought++; }

    $pageTitle = 'สมาชิก ' . $member['username'];
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/admin_nav.php';
    ?>
    <div class="page-head"><span class="eyebrow">MEMBER</span><h1><?= e($member['username']) ?></h1><p><?= e($member['email']) ?> · สมัครเมื่อ <?= e($member['created_at']) ?></p></div>
    <div class="stat-grid">
      <div class="stat"><span>ซื้อสำเร็จ (ออเดอร์)</span><strong><?= $bought ?></strong></div>
      <div class="stat"><span>ยอดซื้อรวม</span><strong>฿<?= money($spent) ?></strong></div>
    </div>
    <?php if (!$orders): ?><div class="empty-state">สมาชิกคนนี้ยังไม่เคยสั่งซื้อ</div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>เลขที่</th><th>ไอดี</th><th>ยอด</th><th>สถานะ</th><th>วันที่สั่ง</th><th>วันที่ชำระ</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $x): ?>
        <tr>
          <td>#<?= (int)$x['id'] ?></td><td><?= e($x['account_title']) ?></td><td>฿<?= money($x['total']) ?></td>
          <td><span class="status <?= e($x['status']) ?>"><?= e(status_label($x['status'])) ?></span></td>
          <td><?= e($x['created_at']) ?></td><td><?= e($x['paid_at'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
    <p><a class="text-link" href="members.php">← กลับรายชื่อสมาชิก</a></p>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ----- รายชื่อสมาชิกทั้งหมด -----
$total   = $one("SELECT COUNT(*) FROM users WHERE role='user'");
$buyers  = $one("SELECT COUNT(DISTINCT user_id) FROM orders WHERE status IN ('paid','completed')");
$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = "u.role='user'";
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
    $params = [$like, $like];
}
$st = $pdo->prepare("SELECT u.id, u.username, u.email, u.created_at,
        COUNT(o.id) AS orders_n, COALESCE(SUM(o.total),0) AS spent, MAX(COALESCE(o.paid_at, o.created_at)) AS last_buy
    FROM users u LEFT JOIN orders o ON o.user_id=u.id AND o.status IN ('paid','completed')
    WHERE $where GROUP BY u.id, u.username, u.email, u.created_at
    ORDER BY spent DESC, u.created_at DESC LIMIT 500");
$st->execute($params);
$members = $st->fetchAll();

$pageTitle = 'สมาชิก';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head"><span class="eyebrow">ADMIN</span><h1>สมาชิก</h1><p>รายชื่อสมาชิกทั้งหมดและยอดการซื้อ (เรียงจากยอดซื้อสูงสุด)</p></div>
<div class="stat-grid">
  <div class="stat"><span>สมาชิกทั้งหมด</span><strong><?= $total ?></strong></div>
  <div class="stat"><span>เคยซื้อสำเร็จ</span><strong><?= $buyers ?></strong></div>
  <div class="stat"><span>ยังไม่เคยซื้อ</span><strong><?= max(0, $total - $buyers) ?></strong></div>
</div>
<form class="filter-bar" method="get">
  <input name="q" placeholder="ค้นหาชื่อผู้ใช้หรืออีเมล" value="<?= e($q) ?>">
  <button class="btn">ค้นหา</button>
</form>
<?php if (!$members): ?><div class="empty-state">ไม่พบสมาชิก</div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>ชื่อผู้ใช้</th><th>อีเมล</th><th>สมัครเมื่อ</th><th>ซื้อแล้ว (ออเดอร์)</th><th>ยอดซื้อรวม</th><th>ซื้อล่าสุด</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $u): ?>
    <tr>
      <td><strong><?= e($u['username']) ?></strong></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['created_at']) ?></td>
      <td><?= (int)$u['orders_n'] ?></td>
      <td>฿<?= money($u['spent']) ?></td>
      <td><?= $u['last_buy'] ? e($u['last_buy']) : '<span class="muted">-</span>' ?></td>
      <td><a class="btn btn-small btn-ghost" href="members.php?id=<?= (int)$u['id'] ?>">ประวัติซื้อ</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
