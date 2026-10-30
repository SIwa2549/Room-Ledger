<?php
/**
 * ไฟล์: my_bill.php  (หน้าของผู้เช่า)   —  my_bill.php?id=5
 * ใบแจ้งค่าเช่า 1 ใบ ของผู้เช่าที่ล็อกอินอยู่
 *
 * Authorization (ตรวจเจ้าของข้อมูล): WHERE b.id = ? AND b.tenant_id = ?
 * ถ้าผู้เช่าห้อง 101 แก้ id ใน URL เป็นบิลของห้องอื่น จะไม่พบข้อมูล เพราะ tenant_id ไม่ตรง
 * และขึ้นข้อความเดียวกับกรณีบิลไม่มีอยู่จริง เพื่อไม่ให้รู้ว่าบิลของคนอื่นมีอยู่
 */
const PAGE_ROLE = 'tenant';
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tenantId = (int) $currentUser['tenant_id'];

$bill = null;
if ($id) {
    $stmt = $conn->prepare('SELECT b.*, r.room_number, t.full_name, t.phone, u.email AS created_by_email
                            FROM project_bills b
                            JOIN project_rooms r   ON r.id = b.room_id
                            JOIN project_tenants t ON t.id = b.tenant_id
                            JOIN project_users u   ON u.id = b.created_by
                            WHERE b.id = ? AND b.tenant_id = ?
                            LIMIT 1');
    $stmt->bind_param('ii', $id, $tenantId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$bill) {
    flash('error', 'ไม่พบบิลนี้ในบัญชีของคุณ');
    redirect('my_bills.php');
}

$month   = substr($bill['billing_month'], 0, 10);
$isPaid  = $bill['status'] === 'paid';
$overdue = !$isPaid && $month < current_month();
$b = bill_breakdown(
    (float) $bill['rent_amount'],
    (int) $bill['elec_prev'], (int) $bill['elec_curr'], (float) $bill['elec_rate'],
    $bill['water_curr'] === null ? null : (int) $bill['water_prev'],
    $bill['water_curr'] === null ? null : (int) $bill['water_curr'],
    (float) $bill['water_rate']
);

$pageTitle = 'ใบแจ้งค่าเช่า ' . thai_date($month, true);
$activeNav = 'my_bills';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link no-print" href="my_bills.php">บิลของฉัน</a>

<header class="page-head">
    <div class="drawer-title">
        <h1>บิลเดือน <?= thai_date($month, true) ?></h1>
        <?php if ($isPaid): ?>
            <span class="pill pill-free">ชำระแล้ว</span>
        <?php else: ?>
            <span class="pill pill-<?= $overdue ? 'overdue' : 'rented' ?>"><?= $overdue ? 'ค้างชำระ' : 'รอชำระ' ?></span>
        <?php endif; ?>
    </div>
    <div class="actions no-print">
        <button class="btn" type="button" onclick="window.print()">พิมพ์ใบแจ้งค่าเช่า</button>
    </div>
</header>

<?php require __DIR__ . '/includes/invoice.php'; ?>

<?php if (!$isPaid): ?>
    <p class="hint no-print">ชำระแล้ว เจ้าของหอจะเป็นผู้บันทึกสถานะในระบบ</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
