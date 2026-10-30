<?php
/**
 * ไฟล์: request_action.php  (เจ้าของหอ: เปลี่ยนสถานะแจ้งซ่อม)
 * รับเฉพาะ POST จากปุ่มในหน้า requests.php
 *   action = done     ปิดงาน (บันทึกเวลาและผู้ปิดงาน)
 *   action = pending  เปิดงานอีกครั้ง (กรณีกดผิดหรือยังแก้ไม่หาย)
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('requests.php');
}
csrf_check();

$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string) ($_POST['action'] ?? '');
$show   = (string) ($_POST['show'] ?? '');
$back   = 'requests.php' . (in_array($show, ['done', 'all'], true) ? '?show=' . $show : '');

$stmt = $conn->prepare('SELECT q.id, r.room_number
                        FROM project_requests q
                        JOIN project_tenants t ON t.id = q.tenant_id
                        JOIN project_rooms r   ON r.id = t.room_id
                        WHERE q.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$id || !$req) {
    flash('error', 'ไม่พบรายการแจ้งซ่อมที่ต้องการ');
    redirect($back);
}
$label = 'งานแจ้งซ่อมห้อง ' . $req['room_number'];

switch ($action) {
    case 'done':
        $stmt = $conn->prepare("UPDATE project_requests SET status = 'done', completed_at = NOW(), completed_by = ?
                                WHERE id = ? AND status = 'pending' LIMIT 1");
        $stmt->bind_param('ii', $currentUser['id'], $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'ปิด' . $label . ' แล้ว');
        redirect($back);

    case 'pending':
        $stmt = $conn->prepare("UPDATE project_requests SET status = 'pending', completed_at = NULL, completed_by = NULL
                                WHERE id = ? AND status = 'done' LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'เปิด' . $label . ' อีกครั้งแล้ว');
        redirect($back);

    default:
        flash('error', 'ไม่รู้จักคำสั่งนี้');
        redirect($back);
}
