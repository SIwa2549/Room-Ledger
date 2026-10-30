<?php
/**
 * ไฟล์: bill_action.php  (CRUD บิล: UPDATE สถานะ / DELETE)
 * รับเฉพาะ POST จากปุ่มในหน้า bill_view.php
 *   action = paid    บันทึกว่าชำระแล้ว
 *   action = unpaid  ยกเลิกสถานะชำระแล้ว (กรณีกดผิด)
 *   action = delete  ลบบิล (เฉพาะบิลล่าสุดของห้องที่ยังไม่ชำระ)
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bills.php');
}
csrf_check();

$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string) ($_POST['action'] ?? '');

$stmt = $conn->prepare('SELECT b.id, b.room_id, b.billing_month, b.status, r.room_number,
                               (SELECT COUNT(*) FROM project_bills nb WHERE nb.room_id = b.room_id AND nb.billing_month > b.billing_month) AS later_count
                        FROM project_bills b JOIN project_rooms r ON r.id = b.room_id
                        WHERE b.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$id || !$bill) {
    flash('error', 'ไม่พบบิลที่ต้องการ');
    redirect('bills.php');
}
$label = 'บิลห้อง ' . $bill['room_number'] . ' เดือน ' . thai_date($bill['billing_month'], true);

switch ($action) {
    case 'paid':
    case 'unpaid':
        $from = $action === 'paid' ? 'unpaid' : 'paid';
        $stmt = $conn->prepare('UPDATE project_bills SET status = ? WHERE id = ? AND status = ? LIMIT 1');
        $stmt->bind_param('sis', $action, $id, $from);
        $stmt->execute();
        $stmt->close();
        flash('success', $action === 'paid' ? $label . ' ชำระแล้ว' : 'เปลี่ยน' . $label . ' กลับเป็นยังไม่ชำระ');
        redirect('bill_view.php?id=' . $id);

    case 'delete':
        if ($bill['status'] === 'paid' || (int) $bill['later_count'] > 0) {
            flash('error', 'ลบ' . $label . ' ไม่ได้ ลบได้เฉพาะบิลล่าสุดของห้องที่ยังไม่ชำระ');
            redirect('bill_view.php?id=' . $id);
        }
        $stmt = $conn->prepare('DELETE FROM project_bills WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'ลบ' . $label . ' แล้ว');
        redirect('bills.php?month=' . substr($bill['billing_month'], 0, 7));

    default:
        flash('error', 'ไม่รู้จักคำสั่งนี้');
        redirect('bill_view.php?id=' . $id);
}
