<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
if (is_logged_in()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id,username,email,password,role FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['user'] = $user;
        set_flash('เข้าสู่ระบบสำเร็จ');
        $next = $_SESSION['after_login'] ?? 'index.php';
        unset($_SESSION['after_login']);
        redirect($next);
    }
    $error = 'ชื่อผู้ใช้/อีเมล หรือรหัสผ่านไม่ถูกต้อง';
}
$pageTitle = 'เข้าสู่ระบบ';
require 'includes/header.php';
?>
<div class="auth-wrap"><form class="form-card" method="post">
  <span class="eyebrow">WELCOME BACK</span><h1>เข้าสู่ระบบ</h1><p class="muted">เข้าสู่ระบบ PhuwitShop</p>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>ชื่อผู้ใช้หรืออีเมล<input name="login" required value="<?= e($_POST['login'] ?? '') ?>"></label>
  <label>รหัสผ่าน<input type="password" name="password" required></label>
  <button class="btn btn-full">เข้าสู่ระบบ</button>
  <p class="form-foot">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
</form></div>
<?php require 'includes/footer.php'; ?>
