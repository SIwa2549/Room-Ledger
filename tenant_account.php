<?php
/**
 * ไฟล์: tenant_account.php  (เจ้าของหอจัดการบัญชีเข้าใช้งานของผู้เช่า)
 * รับเฉพาะ POST จากฟอร์มในหน้า tenant_edit.php
 *   action = create  สร้างบัญชีผู้เช่า (อีเมล + รหัสผ่าน)
 *   action = reset   ตั้งรหัสผ่านใหม่
 *   action = delete  ลบบัญชี (ข้อมูลผู้เช่าและบิลยังอยู่ครบ)
 */
require_once __DIR__ . '/includes/auth.php';   // เจ้าของหอเท่านั้น

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tenants.php');
}
csrf_check();

$tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action   = (string) ($_POST['action'] ?? '');

$stmt = $conn->prepare('SELECT t.id, t.full_name, u.id AS user_id, u.email
                        FROM project_tenants t
                        LEFT JOIN project_users u ON u.tenant_id = t.id
                        WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tenantId || !$tenant) {
    flash('error', 'ไม่พบผู้เช่าที่ต้องการ');
    redirect('tenants.php');
}
$back = 'tenant_edit.php?id=' . $tenantId . '#account';

/** ตรวจรหัสผ่านใหม่ คืนข้อความ error หรือ '' ถ้าผ่าน */
function password_problem(string $password): string
{
    if (strlen($password) < 8)   return 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    if (strlen($password) > 72)  return 'รหัสผ่านยาวเกินไป (ไม่เกิน 72 ตัวอักษร)';
    return '';
}

switch ($action) {
    case 'create':
        if ($tenant['user_id'] !== null) {
            flash('error', $tenant['full_name'] . ' มีบัญชีอยู่แล้ว');
            redirect($back);
        }
        $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'สร้างบัญชีไม่ได้: รูปแบบอีเมลไม่ถูกต้อง');
            redirect($back);
        }
        if ($problem = password_problem($password)) {
            flash('error', 'สร้างบัญชีไม่ได้: ' . $problem);
            redirect($back);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO project_users (email, password_hash, role, tenant_id) VALUES (?, ?, 'tenant', ?)");
        $stmt->bind_param('ssi', $email, $hash, $tenantId);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {   // อีเมลซ้ำกับบัญชีอื่น
                flash('error', 'สร้างบัญชีไม่ได้: อีเมล ' . $email . ' ถูกใช้แล้ว');
                redirect($back);
            }
            throw $e;
        }
        flash('success', 'สร้างบัญชีให้ ' . $tenant['full_name'] . ' แล้ว ใช้อีเมล ' . $email . ' เข้าสู่ระบบ');
        redirect($back);

    case 'reset':
        $password = (string) ($_POST['password'] ?? '');
        if ($tenant['user_id'] === null) {
            flash('error', 'ผู้เช่าคนนี้ยังไม่มีบัญชี');
            redirect($back);
        }
        if ($problem = password_problem($password)) {
            flash('error', 'ตั้งรหัสผ่านใหม่ไม่ได้: ' . $problem);
            redirect($back);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE project_users SET password_hash = ? WHERE id = ? AND role = 'tenant' LIMIT 1");
        $stmt->bind_param('si', $hash, $tenant['user_id']);
        $stmt->execute();
        $stmt->close();
        flash('success', 'ตั้งรหัสผ่านใหม่ให้ ' . $tenant['full_name'] . ' แล้ว');
        redirect($back);

    case 'delete':
        if ($tenant['user_id'] !== null) {
            $stmt = $conn->prepare("DELETE FROM project_users WHERE id = ? AND role = 'tenant' LIMIT 1");
            $stmt->bind_param('i', $tenant['user_id']);
            $stmt->execute();
            $stmt->close();
        }
        flash('success', 'ลบบัญชีของ ' . $tenant['full_name'] . ' แล้ว ข้อมูลผู้เช่าและบิลยังอยู่ครบ');
        redirect($back);

    default:
        flash('error', 'ไม่รู้จักคำสั่งนี้');
        redirect($back);
}
