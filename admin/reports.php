<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
release_expired_orders($pdo);

// ยอดขาย = คำสั่งซื้อสถานะ "ชำระแล้ว" หรือ "สำเร็จ" นับตามวันที่ชำระเงิน
const SALE_STATUS = "o.status IN ('paid','completed')";
const SALE_DATE   = 'COALESCE(o.paid_at, o.created_at)';

$views = [
    'day'   => ['label' => 'รายวัน',  'fmt' => '%Y-%m-%d', 're' => '/^\d{4}-\d{2}-\d{2}$/', 'limit' => 31],
    'month' => ['label' => 'รายเดือน', 'fmt' => '%Y-%m',    're' => '/^\d{4}-\d{2}$/',       'limit' => 24],
    'year'  => ['label' => 'รายปี',   'fmt' => '%Y',       're' => '/^\d{4}$/',             'limit' => 10],
];
$view = $_GET['view'] ?? 'day';
if (!isset($views[$view])) $view = 'day';
$v = $views[$view];

$period = (string)($_GET['period'] ?? '');
if (!preg_match($v['re'], $period)) $period = '';

// สรุปด้านบน: วันนี้ / เดือนนี้ / ปีนี้ / ทั้งหมด
$sum = function (string $where) use ($pdo): array {
    $r = $pdo->query('SELECT COUNT(*) AS n, COALESCE(SUM(o.total),0) AS s FROM orders o WHERE ' . SALE_STATUS . ($where ? " AND $where" : ''))->fetch();
    return ['n' => (int)$r['n'], 's' => $r['s']];
};
$d = SALE_DATE;
$cards = [
    'วันนี้'   => $sum("DATE($d) = CURDATE()"),
    'เดือนนี้' => $sum("DATE_FORMAT($d,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')"),
    'ปีนี้'    => $sum("YEAR($d) = YEAR(CURDATE())"),
    'ทั้งหมด'  => $sum(''),
];

// ตารางสรุปตามช่วงเวลา
$rows = $pdo->query("SELECT DATE_FORMAT($d, '{$v['fmt']}') AS period, COUNT(*) AS n, SUM(o.total) AS s,
                            COUNT(DISTINCT o.user_id) AS buyers
                     FROM orders o WHERE " . SALE_STATUS . "
                     GROUP BY period ORDER BY period DESC LIMIT {$v['limit']}")->fetchAll();
$max = 0;
foreach ($rows as $r) $max = max($max, (float)$r['s']);

// รายการคำสั่งซื้อของช่วงที่เลือก (ใครเป็นคนซื้อ)
$detail = [];
if ($period !== '') {
    $st = $pdo->prepare("SELECT o.id, o.account_title, o.total, o.status, $d AS sold_at, u.id AS user_id, u.username, u.email
                         FROM orders o JOIN users u ON u.id=o.user_id
                         WHERE " . SALE_STATUS . " AND DATE_FORMAT($d, '{$v['fmt']}') = ?
                         ORDER BY sold_at DESC LIMIT 500");
    $st->execute([$period]);
    $detail = $st->fetchAll();
}

$pageTitle = 'รายงานยอดขาย';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head"><span class="eyebrow">ADMIN</span><h1>รายงานยอดขาย</h1><p>นับเฉพาะคำสั่งซื้อที่ชำระแล้วหรือสำเร็จ (ข้อมูลจำลอง)</p></div>

<div class="stat-grid">
<?php foreach ($cards as $label => $c): ?>
  <div class="stat"><span><?= e($label) ?> · <?= $c['n'] ?> ออเดอร์</span><strong>฿<?= money($c['s']) ?></strong></div>
<?php endforeach; ?>
</div>

<div class="chips">
<?php foreach ($views as $key => $vv): ?>
  <a class="chip <?= $key === $view ? 'active' : '' ?>" href="reports.php?view=<?= $key ?>"><?= e($vv['label']) ?></a>
<?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="empty-state">ยังไม่มียอดขาย</div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>ช่วงเวลา</th><th>จำนวนออเดอร์</th><th>ผู้ซื้อ (คน)</th><th>ยอดขาย</th><th class="bar-col"></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $pct = $max > 0 ? round($r['s'] / $max * 100) : 0; ?>
    <tr class="<?= $r['period'] === $period ? 'row-active' : '' ?>">
      <td><strong><?= e($r['period']) ?></strong></td>
      <td><?= (int)$r['n'] ?></td>
      <td><?= (int)$r['buyers'] ?></td>
      <td>฿<?= money($r['s']) ?></td>
      <td class="bar-col"><div class="bar"><span style="width:<?= $pct ?>%"></span></div></td>
      <td><a class="btn btn-small btn-ghost" href="reports.php?view=<?= $view ?>&amp;period=<?= urlencode($r['period']) ?>#detail">ดูผู้ซื้อ</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<?php if ($period !== ''): ?>
<section id="detail">
  <h2>รายการขายของ <?= e($period) ?></h2>
  <?php if (!$detail): ?><div class="empty-state">ไม่พบรายการ</div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>เลขที่</th><th>ผู้ซื้อ</th><th>ไอดี</th><th>ยอด</th><th>เวลา</th></tr></thead>
    <tbody>
    <?php foreach ($detail as $o): ?>
      <tr>
        <td>#<?= (int)$o['id'] ?></td>
        <td><a class="text-link" href="members.php?id=<?= (int)$o['user_id'] ?>"><?= e($o['username']) ?></a><br><small class="muted"><?= e($o['email']) ?></small></td>
        <td><?= e($o['account_title']) ?></td>
        <td>฿<?= money($o['total']) ?></td>
        <td><?= e($o['sold_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
