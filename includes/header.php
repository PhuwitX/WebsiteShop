<?php
require_once __DIR__ . '/functions.php';
$flash = get_flash();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'หน้าแรก') ?> | PhuwitShop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/favicon.svg">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="<?= BASE_URL ?>index.php"><img class="brand-logo" src="<?= BASE_URL ?>assets/img/logo.svg" alt="" width="36" height="36">PHUWIT<span>SHOP</span></a>
    <nav class="nav-links">
      <a href="<?= BASE_URL ?>index.php">ไอดีเกมทั้งหมด</a>
      <?php if (is_logged_in()): ?>
        <a href="<?= BASE_URL ?>my-orders.php">คำสั่งซื้อของฉัน</a>
      <?php endif; ?>
      <?php $portfolioUrl = defined('PORTFOLIO_URL') ? trim(PORTFOLIO_URL) : ''; ?>
      <?php if (preg_match('#^https?://#i', $portfolioUrl)): ?>
        <a href="<?= e($portfolioUrl) ?>" target="_blank" rel="noopener">Portfolio</a>
      <?php else: ?>
        <span class="nav-note">Portfolio: ใส่ลิงก์ของคุณเองใน config/database.php (PORTFOLIO_URL)</span>
      <?php endif; ?>
      <?php if (is_logged_in()): ?>
        <?php if (is_admin()): ?><a href="<?= BASE_URL ?>admin/index.php">จัดการร้าน</a><?php endif; ?>
        <a class="nav-user" href="<?= BASE_URL ?>change-password.php"><?= e($_SESSION['user']['username']) ?></a>
        <a class="btn btn-small btn-ghost" href="<?= BASE_URL ?>logout.php">ออกจากระบบ</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>login.php">เข้าสู่ระบบ</a>
        <a class="btn btn-small" href="<?= BASE_URL ?>register.php">สมัครสมาชิก</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
