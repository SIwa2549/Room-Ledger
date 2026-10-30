<?php
/**
 * ไฟล์: bill_view.php  (CRUD บิล: READ รายใบ)
 * ใบแจ้งค่าเช่า 1 ใบ พร้อมปุ่ม บันทึกว่าชำระแล้ว / แก้ไข / ลบ / พิมพ์   —  bill_view.php?id=1
 * bill_view.php?id=1&confirm=delete = หน้ายืนยันก่อนลบบิล
 */
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบบิลที่ต้องการ');
    redirect('bills.php');
}

$stmt = $conn->prepare('SELECT b.*, r.room_number, r.id AS room_id, t.full_name, t.phone, u.email AS created_by_email,
                               (SELECT COUNT(*) FROM project_bills nb WHERE nb.room_id = b.room_id AND nb.billing_month > b.billing_month) AS later_count
                        FROM project_bills b
                        JOIN project_rooms r   ON r.id = b.room_id
                        JOIN project_tenants t ON t.id = b.tenant_id
                        JOIN project_users u   ON u.id = b.created_by
                        WHERE b.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$bill) {
    flash('error', 'ไม่พบบิลที่ต้องการ อาจถูกลบไปแล้ว');
    redirect('bills.php');
}

$month   = substr($bill['billing_month'], 0, 10);
$isPaid  = $bill['status'] === 'paid';
$isLast  = (int) $bill['later_count'] === 0;     // แก้/ลบได้เฉพาะบิลล่าสุดของห้อง เพื่อไม่ให้เลขมิเตอร์เดือนต่อไปผิด
$canEdit = !$isPaid && $isLast;
$overdue = !$isPaid && $month < current_month();
$wantDelete = ($_GET['confirm'] ?? '') === 'delete' && $canEdit;

$b = bill_breakdown(
    (float) $bill['rent_amount'],
    (int) $bill['elec_prev'], (int) $bill['elec_curr'], (float) $bill['elec_rate'],
    $bill['water_curr'] === null ? null : (int) $bill['water_prev'],
    $bill['water_curr'] === null ? null : (int) $bill['water_curr'],
    (float) $bill['water_rate']
);

$pageTitle = 'บิลห้อง ' . $bill['room_number'] . ' ' . thai_date($month, true);
$activeNav = 'bills';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link no-print" href="bills.php?month=<?= substr($month, 0, 7) ?>">บิลเดือน <?= thai_date($month, true) ?></a>

<header class="page-head">
    <div class="drawer-title">
        <h1>บิลห้อง <?= e($bill['room_number']) ?></h1>
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

<?php if ($wantDelete): ?>
    <section class="panel form-narrow no-print">
        <p>ลบบิลห้อง <b><?= e($bill['room_number']) ?></b> เดือน <?= thai_date($month, true) ?> ยอด <?= baht($b['total']) ?> ถาวร กู้คืนไม่ได้ ลบแล้วจดมิเตอร์ใหม่ได้</p>
        <form method="post" action="bill_action.php" class="actions">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button class="btn btn-danger-solid" type="submit" name="action" value="delete">ลบบิลนี้</button>
            <a class="btn btn-ghost" href="bill_view.php?id=<?= (int) $id ?>">ยกเลิก</a>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/invoice.php'; ?>

<div class="actions no-print">
    <form method="post" action="bill_action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php if ($isPaid): ?>
            <button class="btn" type="submit" name="action" value="unpaid">ยกเลิกสถานะชำระแล้ว</button>
        <?php else: ?>
            <button class="btn btn-primary" type="submit" name="action" value="paid">บันทึกว่าชำระแล้ว</button>
        <?php endif; ?>
    </form>
    <?php if ($canEdit): ?>
        <a class="btn" href="bill_edit.php?id=<?= (int) $id ?>">แก้เลขมิเตอร์</a>
        <?php if (!$wantDelete): ?><a class="btn btn-danger" href="bill_view.php?id=<?= (int) $id ?>&confirm=delete">ลบบิล</a><?php endif; ?>
    <?php elseif (!$isPaid): ?>
        <span class="hint">แก้หรือลบไม่ได้ เพราะห้องนี้มีบิลเดือนถัดไปแล้ว</span>
    <?php endif; ?>
    <a class="btn btn-ghost" href="room_view.php?id=<?= (int) $bill['room_id'] ?>">ดูห้อง <?= e($bill['room_number']) ?></a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
