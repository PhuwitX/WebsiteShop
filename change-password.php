<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id=?');
    $stmt->execute([$_SESSION['user']['id']]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($old, $hash)) {
        $error = 'รหัสผ่านเดิมไม่ถูกต้อง';
    } elseif (strlen($new) < 8) {
        $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($new !== $confirm) {
        $error = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
    } elseif ($new === $old) {
        $error = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม';
    } else {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user']['id']]);
        session_regenerate_id(true);
        set_flash('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        redirect('index.php');
    }
}
$pageTitle = 'เปลี่ยนรหัสผ่าน';
require 'includes/header.php';
?>
<div class="auth-wrap"><form class="form-card" method="post">
  <span class="eyebrow">ACCOUNT</span><h1>เปลี่ยนรหัสผ่าน</h1><p class="muted">บัญชี: <?= e($_SESSION['user']['username']) ?></p>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>รหัสผ่านเดิม<input type="password" name="old_password" required autocomplete="current-password"></label>
  <label>รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
  <label>ยืนยันรหัสผ่านใหม่<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
  <button class="btn btn-full">บันทึกรหัสผ่านใหม่</button>
</form></div>
<?php require 'includes/footer.php'; ?>
