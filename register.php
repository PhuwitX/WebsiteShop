<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
if (is_logged_in()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (mb_strlen($username) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'กรุณากรอกชื่อผู้ใช้อย่างน้อย 3 ตัวอักษร อีเมลให้ถูกต้อง และรหัสผ่านอย่างน้อย 8 ตัวอักษร';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username,email,password) VALUES (?,?,?)');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            set_flash('สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ');
            redirect('login.php');
        } catch (PDOException $e) {
            $error = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
        }
    }
}
$pageTitle = 'สมัครสมาชิก';
require 'includes/header.php';
?>
<div class="auth-wrap"><form class="form-card" method="post">
  <span class="eyebrow">CREATE ACCOUNT</span><h1>สมัครสมาชิก</h1><p class="muted">สร้างบัญชีเพื่อทดลองสั่งซื้อสินค้า</p>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>ชื่อผู้ใช้<input name="username" required minlength="3" maxlength="100" value="<?= e($_POST['username'] ?? '') ?>"></label>
  <label>อีเมล<input type="email" name="email" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>"></label>
  <label>รหัสผ่าน<input type="password" name="password" required minlength="8"></label>
  <button class="btn btn-full">สมัครสมาชิก</button>
  <p class="form-foot">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
</form></div>
<?php require 'includes/footer.php'; ?>
