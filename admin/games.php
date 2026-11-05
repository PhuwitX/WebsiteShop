<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    try {
        if ($action === 'save') {
            $name = trim($_POST['name'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;
            if ($name === '' || mb_strlen($name) > 150) throw new RuntimeException('กรุณากรอกชื่อเกม (ไม่เกิน 150 ตัวอักษร)');
            if ($image !== '') {
                $image = safe_image_url($image);
                if ($image === '' || strlen($image) > 500) throw new RuntimeException('URL รูปภาพไม่ถูกต้อง ต้องขึ้นต้นด้วย http:// หรือ https:// และยาวไม่เกิน 500 ตัวอักษร');
            }
            if ($id) {
                $pdo->prepare('UPDATE games SET name=?, image=?, description=?, status=? WHERE id=?')->execute([$name, $image ?: null, $desc ?: null, $status, $id]);
                set_flash('แก้ไขเกมแล้ว');
            } else {
                $pdo->prepare('INSERT INTO games (name,image,description,status) VALUES (?,?,?,?)')->execute([$name, $image ?: null, $desc ?: null, $status]);
                set_flash('เพิ่มเกมแล้ว');
            }
            redirect('admin/games.php');
        } elseif ($action === 'toggle' && $id) {
            $pdo->prepare('UPDATE games SET status = 1 - status WHERE id=?')->execute([$id]);
            redirect('admin/games.php');
        } elseif ($action === 'delete' && $id) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM accounts WHERE game_id=?');
            $c->execute([$id]);
            if ($c->fetchColumn() > 0) throw new RuntimeException('ลบไม่ได้ เพราะมีไอดีในเกมนี้ ให้ปิดการแสดงผลแทน');
            $pdo->prepare('DELETE FROM games WHERE id=?')->execute([$id]);
            set_flash('ลบเกมแล้ว');
            redirect('admin/games.php');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$edit = null;
if ($editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT)) {
    $s = $pdo->prepare('SELECT * FROM games WHERE id=?');
    $s->execute([$editId]);
    $edit = $s->fetch() ?: null;
}
$f = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST + ['status' => null] : ($edit ?: ['status' => 1]);
$games = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM accounts a WHERE a.game_id=g.id AND a.status='available') AS total, (SELECT COUNT(*) FROM accounts a WHERE a.game_id=g.id AND a.status='sold') AS sold FROM games g ORDER BY g.name")->fetchAll();

$pageTitle = 'จัดการเกม';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head"><span class="eyebrow">ADMIN</span><h1>จัดการเกม</h1><p>เพิ่มเกมที่ต้องการขายไอดีได้เอง</p></div>
<form class="form-card" method="post">
  <h2><?= $edit ? 'แก้ไขเกม' : 'เพิ่มเกมใหม่' ?></h2>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <label>ชื่อเกม<input name="name" required maxlength="150" value="<?= e($f['name'] ?? '') ?>"></label>
  <label>URL รูปเกม (ไม่บังคับ)<input name="image" maxlength="500" placeholder="https://..." value="<?= e($f['image'] ?? '') ?>"></label>
  <label>คำอธิบาย<textarea name="description" rows="2"><?= e($f['description'] ?? '') ?></textarea></label>
  <label class="check"><input type="checkbox" name="status" value="1" <?= !empty($f['status']) ? 'checked' : '' ?>> แสดงในหน้าร้าน</label>
  <button class="btn"><?= $edit ? 'บันทึกการแก้ไข' : 'เพิ่มเกม' ?></button>
  <?php if ($edit): ?><a class="text-link" href="games.php">ยกเลิกการแก้ไข</a><?php endif; ?>
</form>

<div class="table-wrap"><table><thead><tr><th>เกม</th><th>ไอดีพร้อมขาย</th><th>ขายแล้ว</th><th>สถานะ</th><th></th></tr></thead><tbody>
<?php foreach ($games as $g): ?>
<tr>
  <td><?= e($g['name']) ?></td>
  <td><?= (int)$g['total'] ?></td>
  <td><?= (int)$g['sold'] ?></td>
  <td><span class="status <?= $g['status'] ? 'available' : 'hidden' ?>"><?= $g['status'] ? 'แสดง' : 'ซ่อน' ?></span></td>
  <td class="actions">
    <a class="btn btn-small btn-ghost" href="games.php?edit=<?= (int)$g['id'] ?>">แก้ไข</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-small btn-ghost"><?= $g['status'] ? 'ซ่อน' : 'แสดง' ?></button></form>
    <form method="post" data-confirm="ลบเกม <?= e($g['name']) ?> ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-small btn-danger">ลบ</button></form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
