<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- ทั่วไป ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return number_format((float)$n, 2); }
function redirect(string $path) { header('Location: ' . BASE_URL . ltrim($path, '/')); exit; }

function status_label(string $s): string {
    $map = [
        'pending' => 'รอชำระเงิน', 'paid' => 'ชำระแล้ว', 'completed' => 'สำเร็จ', 'cancelled' => 'ยกเลิก',
        'available' => 'พร้อมขาย', 'reserved' => 'ถูกจอง', 'sold' => 'ขายแล้ว', 'hidden' => 'ซ่อน',
    ];
    return $map[$s] ?? $s;
}

// ยอมรับเฉพาะ URL รูปแบบ http/https
// ลิงก์ที่มีภาษาไทย/เว้นวรรค (เบราว์เซอร์เปิดได้) จะถูกเข้ารหัส %XX ให้อัตโนมัติ แทนการปฏิเสธ
function safe_image_url(?string $url): string {
    $url = trim((string)$url);
    if (!preg_match('~^https?://~i', $url)) return '';
    $url = preg_replace_callback('/[^\x21-\x7E]+/', fn($m) => rawurlencode($m[0]), $url);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

// แปลงข้อความ "URL บรรทัดละ 1 รูป" เป็นรายการ URL ที่ปลอดภัย (สูงสุด 10 รูป)
function parse_image_urls(string $text, int $max = 10): array {
    $out = [];
    foreach (preg_split('/\R/u', $text) as $line) {
        $u = safe_image_url($line);
        if ($u !== '' && strlen($u) <= 500 && !in_array($u, $out, true)) $out[] = $u;
        if (count($out) >= $max) break;
    }
    return $out;
}

// รูปเพิ่มเติมของไอดี (ตาราง account_images) ถ้ายังไม่ได้สร้างตารางจะคืนค่าว่างโดยไม่ error
function get_account_images(PDO $pdo, int $accountId): array {
    try {
        $s = $pdo->prepare('SELECT url FROM account_images WHERE account_id=? ORDER BY sort_order, id');
        $s->execute([$accountId]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { return []; }
}

/* ---------- Flash ---------- */
function set_flash(string $msg, string $type = 'success'): void { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function get_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

/* ---------- สิทธิ์ผู้ใช้ ---------- */
function is_logged_in(): bool { return !empty($_SESSION['user']); }
function is_admin(): bool { return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin'; }

function require_login(): void {
    if (is_logged_in()) return;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, BASE_URL) === 0) $uri = substr($uri, strlen(BASE_URL));
    // จำหน้าที่ต้องการไว้ เพื่อกลับมาหลังล็อกอิน (รับเฉพาะ path ภายในเว็บ)
    if (preg_match('/^[A-Za-z0-9_\-\/]+\.php(\?[A-Za-z0-9_=&\-]*)?$/', $uri)) $_SESSION['after_login'] = $uri;
    set_flash('กรุณาเข้าสู่ระบบก่อน', 'error');
    redirect('login.php');
}

function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); exit('ไม่มีสิทธิ์เข้าถึงหน้านี้'); }
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('CSRF token ไม่ถูกต้อง กรุณาย้อนกลับแล้วลองใหม่');
    }
}

/* ---------- เข้ารหัสข้อมูลล็อกอินของไอดีเกม ---------- */
function crypt_key(): string { return hash('sha256', APP_KEY, true); }

function encrypt_text(string $plain): string {
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', crypt_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_text(string $blob): string {
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 28) return '';
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', crypt_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

/* ---------- จัดการคำสั่งซื้อ ---------- */
// ยกเลิกคำสั่งซื้อที่หมดเวลา และคืนสถานะไอดีให้กลับมาพร้อมขาย
function release_expired_orders(PDO $pdo): void {
    $min = (int)ORDER_TIMEOUT_MIN;
    $cond = "status='pending' AND created_at < (NOW() - INTERVAL $min MINUTE)";
    try {
        $pdo->beginTransaction();
        $pdo->exec("UPDATE accounts SET status='available' WHERE status='reserved' AND id IN (SELECT account_id FROM (SELECT account_id FROM orders WHERE $cond) t)");
        $pdo->exec("UPDATE payments SET status='rejected' WHERE status='pending' AND order_id IN (SELECT id FROM (SELECT id FROM orders WHERE $cond) t)");
        $pdo->exec("UPDATE orders SET status='cancelled' WHERE $cond");
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

// ยกเลิกคำสั่งซื้อที่ยังรอชำระเงิน (ผู้ซื้อยกเลิกเอง หรือแอดมินยกเลิก)
function cancel_pending_order(PDO $pdo, int $orderId, ?int $userId = null): bool {
    try {
        $pdo->beginTransaction();
        $sql = "SELECT id, account_id FROM orders WHERE id=? AND status='pending'" . ($userId !== null ? ' AND user_id=?' : '') . ' FOR UPDATE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($userId !== null ? [$orderId, $userId] : [$orderId]);
        $order = $stmt->fetch();
        if (!$order) { $pdo->rollBack(); return false; }
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$orderId]);
        $pdo->prepare("UPDATE payments SET status='rejected' WHERE order_id=? AND status='pending'")->execute([$orderId]);
        $pdo->prepare("UPDATE accounts SET status='available' WHERE id=? AND status='reserved'")->execute([$order['account_id']]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}
