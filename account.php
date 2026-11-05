<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
release_expired_orders($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('index.php');

$stmt = $pdo->prepare("SELECT a.*, g.name AS game_name FROM accounts a JOIN games g ON g.id=a.game_id
                       WHERE a.id=? AND a.status<>'hidden' AND g.status=1");
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) {
    set_flash('ไม่พบไอดีที่เลือก', 'error');
    redirect('index.php');
}
$gallery = [];
if ($u = safe_image_url($a['image'])) $gallery[] = $u;
foreach (get_account_images($pdo, (int)$a['id']) as $u) if (!in_array($u, $gallery, true)) $gallery[] = $u;
$pageTitle = $a['title'];
require 'includes/header.php';
?>
<div class="page-head"><span class="eyebrow"><?= e($a['game_name']) ?></span><h1><?= e($a['title']) ?></h1></div>
<div class="checkout-layout">
  <section class="form-card">
    <?php if ($gallery): ?>
    <div class="gallery" data-gallery>
      <a class="gallery-main" href="<?= e($gallery[0]) ?>" target="_blank" rel="noopener"><img id="gallery-main-img" class="detail-img" referrerpolicy="no-referrer" src="<?= e($gallery[0]) ?>" alt="<?= e($a['title']) ?>"></a>
      <?php if (count($gallery) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($gallery as $i => $g): ?>
          <button type="button" class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" data-src="<?= e($g) ?>" aria-label="รูปที่ <?= $i + 1 ?>"><img src="<?= e($g) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <dl class="spec">
      <dt>เกม</dt><dd><?= e($a['game_name']) ?></dd>
      <?php if ($a['rank_level']): ?><dt>แรงค์/เลเวล</dt><dd><?= e($a['rank_level']) ?></dd><?php endif; ?>
      <?php if ($a['server']): ?><dt>เซิร์ฟเวอร์</dt><dd><?= e($a['server']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($a['highlights']): ?><h3>จุดเด่นของไอดี</h3><p class="pre"><?= e($a['highlights']) ?></p><?php endif; ?>
    <?php if ($a['description']): ?><h3>รายละเอียด</h3><p class="pre"><?= e($a['description']) ?></p><?php endif; ?>
  </section>
  <aside class="summary-card">
    <span class="eyebrow">PRICE</span>
    <div class="summary-total"><span>ราคา</span><strong>฿<?= money($a['price']) ?></strong></div>
    <span class="status <?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span>
    <?php if ($a['status'] === 'available'): ?>
      <a class="btn btn-full" href="checkout.php?account_id=<?= (int)$a['id'] ?>">ซื้อไอดีนี้</a>
    <?php else: ?>
      <p class="muted">ไอดีนี้ไม่พร้อมขายในขณะนี้ <a class="text-link" href="index.php">ดูไอดีอื่น</a></p>
    <?php endif; ?>
  </aside>
</div>
<?php require 'includes/footer.php'; ?>
