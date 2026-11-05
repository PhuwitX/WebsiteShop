<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
release_expired_orders($pdo);

// หมวดหมู่เกม (คลิกเพื่อกรอง) เรียงตามที่กำหนด
$games = $pdo->query("SELECT g.id, g.name, COUNT(a.id) AS total
                      FROM games g LEFT JOIN accounts a ON a.game_id=g.id AND a.status='available'
                      WHERE g.status=1 GROUP BY g.id, g.name
                      ORDER BY FIELD(g.name,'Free Fire','Roblox','PUBG Mobile','ROV','Mobile Legends'), g.name")->fetchAll();

$gameId = filter_input(INPUT_GET, 'game', FILTER_VALIDATE_INT) ?: 0;
$validIds = array_map('intval', array_column($games, 'id'));
if ($gameId && !in_array($gameId, $validIds, true)) $gameId = 0;
$allTotal = array_sum(array_map('intval', array_column($games, 'total')));

// แสดงไอดีที่พร้อมขาย (กรองตามเกมที่เลือกถ้ามี)
$sql = "SELECT a.id, a.title, a.price, a.rank_level, a.server, a.image, g.image AS game_image, g.name AS game_name
        FROM accounts a JOIN games g ON g.id=a.game_id
        WHERE a.status='available' AND g.status=1" . ($gameId ? ' AND a.game_id=?' : '') . "
        ORDER BY a.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($gameId ? [$gameId] : []);
$accounts = $stmt->fetchAll();

$activeName = '';
foreach ($games as $g) if ((int)$g['id'] === $gameId) $activeName = $g['name'];

$pageTitle = $activeName ?: 'ไอดีเกมทั้งหมด';
require 'includes/header.php';
?>
<section class="hero">
  <div class="hero-content">
    <span class="eyebrow">GAME ID MARKETPLACE</span>
    <h1>ไอดีเกมคุณภาพ<br><span>คัดโดยร้าน ซื้อได้ทันที</span></h1>
    <p>เลือกเกม เลือกไอดีที่ถูกใจ สร้างคำสั่งซื้อ แล้วรับข้อมูลล็อกอินในหน้าคำสั่งซื้อของคุณ</p>
    <a class="btn" href="#accounts">เลือกดูไอดี <span aria-hidden="true">→</span></a>
  </div>
  <div class="hero-art"><img class="hero-logo-img" src="<?= BASE_URL ?>assets/img/logo.svg" alt="PhuwitShop"></div>
</section>

<section class="section-heading" id="accounts">
  <div><span class="eyebrow">OUR CATALOG</span><h2><?= $activeName ? e($activeName) : 'ไอดีที่พร้อมขาย' ?></h2></div>
</section>

<nav class="chips" aria-label="หมวดหมู่เกม">
  <a class="chip <?= $gameId === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php#accounts">ทั้งหมด <small><?= $allTotal ?></small></a>
  <?php foreach ($games as $g): ?>
    <a class="chip <?= (int)$g['id'] === $gameId ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php?game=<?= (int)$g['id'] ?>#accounts"><?= e($g['name']) ?> <small><?= (int)$g['total'] ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if (!$accounts): ?>
  <div class="empty-state">ยังไม่มีไอดีในหมวดนี้</div>
<?php else: ?>
  <div class="product-grid">
  <?php foreach ($accounts as $a): $img = safe_image_url($a['image']) ?: safe_image_url($a['game_image']); ?>
    <article class="product-card">
      <a href="account.php?id=<?= (int)$a['id'] ?>" class="thumb-link">
      <?php if ($img): ?><img class="thumb" src="<?= e($img) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
      <?php else: ?><div class="product-icon"><?= e(mb_substr($a['game_name'], 0, 1, 'UTF-8')) ?></div><?php endif; ?>
      </a>
      <div class="product-game"><?= e($a['game_name']) ?></div>
      <h3><?= e($a['title']) ?></h3>
      <p class="tags">
        <?php if ($a['rank_level']): ?><span class="tag"><?= e($a['rank_level']) ?></span><?php endif; ?>
        <?php if ($a['server']): ?><span class="tag"><?= e($a['server']) ?></span><?php endif; ?>
      </p>
      <div class="product-bottom">
        <strong>฿<?= money($a['price']) ?></strong>
        <a class="btn btn-small" href="account.php?id=<?= (int)$a['id'] ?>">ดูรายละเอียด</a>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<div class="notice"><strong>หมายเหตุ:</strong> เว็บไซต์นี้เป็นระบบทดลอง การชำระเงินและข้อมูลไอดีเป็นข้อมูลจำลองเท่านั้น</div>
<?php require 'includes/footer.php'; ?>
