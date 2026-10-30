<?php
/**
 * ไฟล์: account.php  (บัญชีของฉัน — ใช้ได้ทั้งเจ้าของหอและผู้เช่า)
 * เปลี่ยนรหัสผ่านของตัวเอง: ต้องกรอกรหัสเดิมให้ถูกก่อน แล้วจึงตั้งรหัสใหม่ได้
 * แก้ได้เฉพาะบัญชีที่ล็อกอินอยู่ (ใช้ id จากฐานข้อมูลของบัญชีนี้ ไม่รับ id จากฟอร์ม)
 */
const PAGE_ROLE = 'any';
require_once __DIR__ . '/includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    // ดึง Hash ปัจจุบันของบัญชีที่ล็อกอินอยู่ เพื่อตรวจรหัสเดิม
    $stmt = $conn->prepare('SELECT password_hash FROM project_users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $currentUser['id']);
    $stmt->execute();
    $hash = (string) ($stmt->get_result()->fetch_row()[0] ?? '');
    $stmt->close();

    if ($current === '') {
        $errors['current_password'] = 'กรอกรหัสผ่านปัจจุบัน';
    } elseif (!password_verify($current, $hash)) {
        $errors['current_password'] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    }

    if (strlen($new) < 8) {
        $errors['new_password'] = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
    } elseif (strlen($new) > 72) {
        $errors['new_password'] = 'รหัสผ่านใหม่ยาวเกินไป (ไม่เกิน 72 ตัวอักษร)';
    } elseif ($new === $current) {
        $errors['new_password'] = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม';
    }

    if (!isset($errors['new_password']) && $confirm !== $new) {
        $errors['confirm_password'] = 'ยืนยันรหัสผ่านไม่ตรงกับรหัสผ่านใหม่';
    }

    if (!$errors) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE project_users SET password_hash = ? WHERE id = ? LIMIT 1');
        $stmt->bind_param('si', $newHash, $currentUser['id']);
        $stmt->execute();
        $stmt->close();

        session_regenerate_id(true);   // ออก Session ID ใหม่หลังเปลี่ยนรหัสผ่าน
        flash('success', 'เปลี่ยนรหัสผ่านแล้ว ครั้งหน้าใช้รหัสผ่านใหม่ในการเข้าสู่ระบบ');
        redirect('account.php');
    }
}

$isOwner   = $currentUser['role'] === 'owner';
$pageTitle = 'บัญชีของฉัน';
$activeNav = 'account';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div>
        <h1>บัญชีของฉัน</h1>
        <p><?= e($currentUser['email']) ?> · <?= $isOwner ? 'เจ้าของหอ' : 'ผู้เช่า' ?></p>
    </div>
    <a class="btn" href="logout.php">ออกจากระบบ</a>
</header>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">เปลี่ยนรหัสผ่านไม่ได้ แก้ไขช่องที่มีข้อความสีแดงก่อน</div>
<?php endif; ?>

<form class="form panel form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <h2 class="h-sm">เปลี่ยนรหัสผ่าน</h2>

    <div class="field">
        <label for="current_password">รหัสผ่านปัจจุบัน</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required<?= invalid_attr($errors, 'current_password') ?>>
        <?= field_error($errors, 'current_password') ?>
    </div>
    <div class="field-row">
        <div class="field">
            <label for="new_password">รหัสผ่านใหม่</label>
            <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required<?= invalid_attr($errors, 'new_password') ?>>
            <span class="hint">อย่างน้อย 8 ตัวอักษร</span>
            <?= field_error($errors, 'new_password') ?>
        </div>
        <div class="field">
            <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required<?= invalid_attr($errors, 'confirm_password') ?>>
            <?= field_error($errors, 'confirm_password') ?>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="submit">เปลี่ยนรหัสผ่าน</button>
    </div>
    <?php if (!$isOwner): ?>
        <p class="hint">เปลี่ยนรหัสแล้วเจ้าของหอจะไม่รู้รหัสผ่านใหม่ของคุณ ถ้าลืมรหัส ให้แจ้งเจ้าของหอตั้งรหัสใหม่ให้</p>
    <?php endif; ?>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
