<?php
/**
 * ไฟล์: room_view.php  (CRUD: READ รายห้อง)
 * แสดงข้อมูลห้อง, ผู้เช่าปัจจุบัน และประวัติบิล  —  room_view.php?id=1
 */
require_once __DIR__ . '/includes/auth.php';

// รับ id จาก URL และตรวจว่าเป็นจำนวนเต็มบวกเท่านั้น
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบห้องที่ต้องการ');
    redirect('rooms.php');
}

// ---------- ข้อมูลห้อง ----------
$stmt = $conn->prepare('SELECT id, room_number, price, status, water_type FROM project_rooms WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
    flash('error', 'ไม่พบห้องที่ต้องการ อาจถูกลบไปแล้ว');
    redirect('rooms.php');
}

// ---------- ผู้เช่าปัจจุบัน (ยังไม่ย้ายออก) ----------
$stmt = $conn->prepare('SELECT id, full_name, phone, move_in_date FROM project_tenants
                        WHERE room_id = ? AND move_out_date IS NULL
                        ORDER BY move_in_date DESC LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------- ประวัติบิล 12 เดือนล่าสุด ----------
$stmt = $conn->prepare('SELECT id, billing_month, water_prev, water_curr, elec_prev, elec_curr, total_amount, status
                        FROM project_bills WHERE room_id = ?
                        ORDER BY billing_month DESC LIMIT 12');
$stmt->bind_param('i', $id);
$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ค้างชำระ = บิลที่ยังไม่จ่ายของเดือนก่อน ๆ (บิลเดือนนี้ยังอยู่ในช่วงรอชำระ)
$thisMonth = current_month();
$overdue = 0.0;
$hasBillThisMonth = false;
foreach ($bills as $bill) {
    $billMonth = substr($bill['billing_month'], 0, 10);
    if ($bill['status'] === 'unpaid' && $billMonth < $thisMonth) $overdue += (float) $bill['total_amount'];
    if ($billMonth === $thisMonth) $hasBillThisMonth = true;
}
$viewStatus = room_view_status($room['status'], $overdue);

$pageTitle = 'ห้อง ' . $room['room_number'];
$activeNav = 'rooms';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="rooms.php">ห้องพักทั้งหมด</a>

<header class="page-head">
    <div class="drawer-title">
        <h1>ห้อง <?= e($room['room_number']) ?></h1>
        <span class="pill pill-<?= $viewStatus ?>"><?= VIEW_STATUS_LABEL[$viewStatus] ?></span>
    </div>
    <div class="actions">
        <a class="btn" href="room_edit.php?id=<?= (int) $room['id'] ?>">แก้ไขห้อง</a>
        <a class="btn btn-danger" href="room_delete.php?id=<?= (int) $room['id'] ?>">ลบห้อง</a>
    </div>
</header>

<div class="detail-grid">
    <section class="panel">
        <h2 class="h-sm">ข้อมูลห้อง</h2>
        <dl class="facts">
            <dt>ชั้น</dt><dd><?= room_floor($room['room_number']) ?: '-' ?></dd>
            <dt>ค่าเช่า</dt><dd><?= baht($room['price']) ?> / เดือน</dd>
            <dt>ค่าน้ำ</dt><dd><?= WATER_TYPE[$room['water_type']] ?></dd>
            <dt>สถานะห้อง</dt><dd><?= ROOM_STATUS[$room['status']] ?></dd>
        </dl>
    </section>

    <section class="panel">
        <h2 class="h-sm">ผู้เช่า</h2>
        <?php if ($tenant): ?>
            <dl class="facts">
                <dt>ชื่อ</dt><dd><?= e($tenant['full_name']) ?></dd>
                <dt>โทร</dt><dd><?= e($tenant['phone']) ?></dd>
                <dt>เข้าพักตั้งแต่</dt><dd><?= thai_date($tenant['move_in_date']) ?></dd>
            </dl>
            <div class="actions">
                <?php if (!$hasBillThisMonth): ?>
                    <a class="btn btn-sm btn-primary" href="bill_create.php?room_id=<?= (int) $room['id'] ?>&month=<?= substr($thisMonth, 0, 7) ?>">จดมิเตอร์ <?= thai_date($thisMonth, true) ?></a>
                <?php endif; ?>
                <a class="btn btn-sm" href="tenant_edit.php?id=<?= (int) $tenant['id'] ?>">แก้ข้อมูลผู้เช่า</a>
                <a class="btn btn-sm" href="tenant_moveout.php?id=<?= (int) $tenant['id'] ?>">บันทึกย้ายออก</a>
            </div>
        <?php else: ?>
            <p class="muted"><?= $room['status'] === 'maintenance' ? 'ยังไม่มีผู้เช่า ห้องนี้อยู่ระหว่างซ่อม' : 'ห้องนี้ว่าง ยังไม่มีผู้เช่า' ?></p>
            <?php if ($room['status'] === 'available'): ?>
                <div class="actions"><a class="btn btn-sm btn-primary" href="tenant_create.php?room_id=<?= (int) $room['id'] ?>">เพิ่มผู้เช่าเข้าห้องนี้</a></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php if ($overdue > 0): ?>
    <div class="flash flash-error">ห้องนี้ค้างชำระ <?= baht($overdue) ?> จากบิลเดือนก่อน</div>
<?php endif; ?>

<section class="stack">
    <h2 class="h-sm">ประวัติบิล</h2>
    <?php if ($bills): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>เดือน</th><th class="right">ไฟ (หน่วย)</th><th class="right">น้ำ</th><th class="right">ยอดรวม</th><th>สถานะ</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                        <tr>
                            <td><?= thai_date($bill['billing_month'], true) ?></td>
                            <td class="right num"><?= number_format($bill['elec_curr'] - $bill['elec_prev']) ?></td>
                            <td class="right num"><?= $bill['water_curr'] === null ? 'เหมาจ่าย' : number_format($bill['water_curr'] - $bill['water_prev']) . ' หน่วย' ?></td>
                            <td class="right num"><?= baht($bill['total_amount']) ?></td>
                            <td>
                                <?php if ($bill['status'] === 'paid'): ?><span class="pill pill-free">ชำระแล้ว</span>
                                <?php elseif (substr($bill['billing_month'], 0, 10) < $thisMonth): ?><span class="pill pill-overdue">ค้างชำระ</span>
                                <?php else: ?><span class="pill pill-rented">รอชำระ</span><?php endif; ?>
                            </td>
                            <td class="right"><a class="btn btn-sm" href="bill_view.php?id=<?= (int) $bill['id'] ?>">ดูบิล</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">ยังไม่มีบิลของห้องนี้</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
