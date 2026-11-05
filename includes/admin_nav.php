<?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
<nav class="admin-nav">
  <?php foreach (['index.php' => 'ภาพรวม', 'games.php' => 'เกม', 'accounts.php' => 'ไอดีเกมที่ขาย', 'orders.php' => 'คำสั่งซื้อ', 'reports.php' => 'รายงานยอดขาย', 'members.php' => 'สมาชิก'] as $file => $label): ?>
    <a class="<?= $current === $file ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/<?= $file ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
