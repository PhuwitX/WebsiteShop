<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
release_expired_orders($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    try {
        if ($action === 'save') {
            $gameId = filter_var($_POST['game_id'] ?? 0, FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
            $rank = trim($_POST['rank_level'] ?? '');
            $server = trim($_POST['server'] ?? '');
            $highlights = trim($_POST['highlights'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $extraImages = parse_image_urls((string)($_POST['extra_images'] ?? ''));
            $status = ($_POST['status'] ?? '') === 'hidden' ? 'hidden' : 'available';
            $loginId = trim($_POST['login_id'] ?? '');
            $secret = (string)($_POST['login_secret'] ?? '');
            $note = trim($_POST['note'] ?? '');

            if (!$gameId) throw new RuntimeException('กรุณาเลือกเกม');
            $g = $pdo->prepare('SELECT COUNT(*) FROM games WHERE id=?');
            $g->execute([$gameId]);
            if (!$g->fetchColumn()) throw new RuntimeException('ไม่พบเกมที่เลือก');
            if ($title === '' || mb_strlen($title) > 200) throw new RuntimeException('กรุณากรอกชื่อไอดี (ไม่เกิน 200 ตัวอักษร)');
            if ($price === false || $price <= 0 || $price > 9999999) throw new RuntimeException('ราคาต้องมากกว่า 0');
            if (mb_strlen($rank) > 100 || mb_strlen($server) > 100) throw new RuntimeException('แรงค์และเซิร์ฟเวอร์ต้องไม่เกิน 100 ตัวอักษร');
            if ($image !== '') {
                $image = safe_image_url($image);
                if ($image === '' || strlen($image) > 500) throw new RuntimeException('URL รูปภาพไม่ถูกต้อง ต้องขึ้นต้นด้วย http:// หรือ https:// และยาวไม่เกิน 500 ตัวอักษร');
            }
            if (!$id && ($loginId === '' || $secret === '')) throw new RuntimeException('ไอดีใหม่ต้องกรอกข้อมูลล็อกอินและรหัสผ่าน');

            $pdo->beginTransaction();
            if ($id) {
                $cur = $pdo->prepare('SELECT status FROM accounts WHERE id=? FOR UPDATE');
                $cur->execute([$id]);
                $st = $cur->fetchColumn();
                if (!in_array($st, ['available', 'hidden'], true)) throw new RuntimeException('แก้ไขไม่ได้ เพราะไอดีนี้ถูกจองหรือขายแล้ว');
                $pdo->prepare('UPDATE accounts SET game_id=?, title=?, price=?, rank_level=?, server=?, highlights=?, description=?, image=?, status=? WHERE id=?')
                    ->execute([$gameId, $title, $price, $rank ?: null, $server ?: null, $highlights ?: null, $desc ?: null, $image ?: null, $status, $id]);
                // ช่องล็อกอินเว้นว่าง = ใช้ค่าเดิม
                $sets = []; $p = [];
                if ($loginId !== '') { $sets[] = 'login_id_enc=?'; $p[] = encrypt_text($loginId); }
                if ($secret !== '')  { $sets[] = 'login_secret_enc=?'; $p[] = encrypt_text($secret); }
                if ($note !== '')    { $sets[] = 'note_enc=?'; $p[] = encrypt_text($note); }
                if ($sets) {
                    $p[] = $id;
                    $pdo->prepare('UPDATE account_credentials SET ' . implode(',', $sets) . ' WHERE account_id=?')->execute($p);
                }
                $newId = $id;
                $msg = 'แก้ไขไอดีแล้ว';
            } else {
                $pdo->prepare('INSERT INTO accounts (game_id,title,price,rank_level,server,highlights,description,image,status) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$gameId, $title, $price, $rank ?: null, $server ?: null, $highlights ?: null, $desc ?: null, $image ?: null, $status]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO account_credentials (account_id,login_id_enc,login_secret_enc,note_enc) VALUES (?,?,?,?)')
                    ->execute([$newId, encrypt_text($loginId), encrypt_text($secret), $note !== '' ? encrypt_text($note) : null]);
                $msg = 'ลงขายไอดีแล้ว';
            }
            // รูปเพิ่มเติม: ลบของเดิมแล้วบันทึกชุดใหม่
            // ถ้ายังไม่ได้รัน migrate_add_images.sql ตารางนี้จะไม่มี: ข้ามส่วนนี้ ไม่ให้รูปหลักและข้อมูลอื่นบันทึกไม่ผ่าน
            try {
                $pdo->prepare('DELETE FROM account_images WHERE account_id=?')->execute([$newId]);
                $ins = $pdo->prepare('INSERT INTO account_images (account_id,url,sort_order) VALUES (?,?,?)');
                foreach ($extraImages as $i => $u) $ins->execute([$newId, $u, $i]);
            } catch (PDOException $e) {
                if ($extraImages) $msg .= ' (ยังไม่ได้บันทึกรูปเพิ่มเติม: ต้องรัน migrate_add_images.sql ก่อน)';
            }
            $pdo->commit();
            set_flash($msg);
            redirect('admin/accounts.php');
        } elseif ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE accounts SET status = IF(status='available','hidden','available') WHERE id=? AND status IN ('available','hidden')")->execute([$id]);
            redirect('admin/accounts.php');
        } elseif ($action === 'delete' && $id) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE account_id=?');
            $c->execute([$id]);
            if ($c->fetchColumn() > 0) throw new RuntimeException('ลบไม่ได้ เพราะมีคำสั่งซื้อที่เกี่ยวข้อง');
            $pdo->prepare("DELETE FROM accounts WHERE id=? AND status IN ('available','hidden')")->execute([$id]);
            set_flash('ลบไอดีแล้ว');
            redirect('admin/accounts.php');
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่';
    }
}

$edit = null;
if ($editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT)) {
    $s = $pdo->prepare("SELECT * FROM accounts WHERE id=? AND status IN ('available','hidden')");
    $s->execute([$editId]);
    $edit = $s->fetch() ?: null;
}
$f = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($edit ?: ['status' => 'available']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $edit) $f['extra_images'] = implode("\n", get_account_images($pdo, (int)$edit['id']));
$games = $pdo->query('SELECT id, name, status FROM games ORDER BY name')->fetchAll();
$accounts = $pdo->query('SELECT a.id, a.title, a.price, a.status, g.name AS game_name FROM accounts a JOIN games g ON g.id=a.game_id ORDER BY a.id DESC LIMIT 200')->fetchAll();

$pageTitle = 'จัดการไอดีเกม';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head"><span class="eyebrow">ADMIN</span><h1>ไอดีเกมที่ขาย</h1><p>ข้อมูลล็อกอินถูกเข้ารหัสในฐานข้อมูล และแสดงให้ผู้ซื้อหลังชำระเงินเท่านั้น</p></div>
<form class="form-card" method="post">
  <h2><?= $edit ? 'แก้ไขไอดี #' . (int)$edit['id'] : 'ลงขายไอดีใหม่' ?></h2>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="form-row">
    <label>เกม<select name="game_id" required>
      <option value="">-- เลือกเกม --</option>
      <?php foreach ($games as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)($f['game_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?><?= $g['status'] ? '' : ' (ซ่อน)' ?></option><?php endforeach; ?>
    </select></label>
    <label>ราคา (฿)<input name="price" type="number" step="0.01" min="0.01" required value="<?= e($f['price'] ?? '') ?>"></label>
  </div>
  <label>ชื่อไอดี / หัวข้อประกาศ<input name="title" required maxlength="200" value="<?= e($f['title'] ?? '') ?>"></label>
  <div class="form-row">
    <label>แรงค์ / เลเวล<input name="rank_level" maxlength="100" value="<?= e($f['rank_level'] ?? '') ?>"></label>
    <label>เซิร์ฟเวอร์<input name="server" maxlength="100" value="<?= e($f['server'] ?? '') ?>"></label>
  </div>
  <label>จุดเด่น (บรรทัดละ 1 ข้อ)<textarea name="highlights" rows="3"><?= e($f['highlights'] ?? '') ?></textarea></label>
  <label>รายละเอียดเพิ่มเติม<textarea name="description" rows="3"><?= e($f['description'] ?? '') ?></textarea></label>
  <label>URL รูปหลัก (ไม่บังคับ) — ใช้เป็นรูปปกในหน้าร้าน<input name="image" maxlength="500" placeholder="https://..." value="<?= e($f['image'] ?? '') ?>"></label>
  <label>URL รูปเพิ่มเติมสำหรับหน้ารายละเอียด (บรรทัดละ 1 รูป สูงสุด 10 รูป)<textarea name="extra_images" rows="4" placeholder="https://.../รูป1.jpg&#10;https://.../รูป2.jpg"><?= e($f['extra_images'] ?? '') ?></textarea></label>
  <label>สถานะ<select name="status">
    <option value="available" <?= ($f['status'] ?? '') !== 'hidden' ? 'selected' : '' ?>>พร้อมขาย</option>
    <option value="hidden" <?= ($f['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>ซ่อน</option>
  </select></label>
  <h3>ข้อมูลล็อกอิน <?= $edit ? '<small class="muted">(เว้นว่าง = ใช้ค่าเดิม)</small>' : '' ?></h3>
  <div class="form-row">
    <label>ไอดี / อีเมลล็อกอิน<input name="login_id" autocomplete="off" <?= $edit ? '' : 'required' ?>></label>
    <label>รหัสผ่าน<input name="login_secret" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></label>
  </div>
  <label>ข้อมูลเพิ่มเติมสำหรับผู้ซื้อ (เช่น อีเมลกู้คืน)<input name="note" autocomplete="off"></label>
  <button class="btn"><?= $edit ? 'บันทึกการแก้ไข' : 'ลงขาย' ?></button>
  <?php if ($edit): ?><a class="text-link" href="accounts.php">ยกเลิกการแก้ไข</a><?php endif; ?>
</form>

<div class="table-wrap"><table><thead><tr><th>#</th><th>เกม</th><th>ไอดี</th><th>ราคา</th><th>สถานะ</th><th></th></tr></thead><tbody>
<?php foreach ($accounts as $a): $editable = in_array($a['status'], ['available', 'hidden'], true); ?>
<tr>
  <td><?= (int)$a['id'] ?></td>
  <td><?= e($a['game_name']) ?></td>
  <td><?= e($a['title']) ?></td>
  <td>฿<?= money($a['price']) ?></td>
  <td><span class="status <?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
  <td class="actions">
  <?php if ($editable): ?>
    <a class="btn btn-small btn-ghost" href="accounts.php?edit=<?= (int)$a['id'] ?>">แก้ไข</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-small btn-ghost"><?= $a['status'] === 'available' ? 'ซ่อน' : 'แสดง' ?></button></form>
    <form method="post" data-confirm="ลบไอดีนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-small btn-danger">ลบ</button></form>
  <?php else: ?><span class="muted">ล็อก</span><?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
