<?php
/**
 * ไฟล์: bills.php  (CRUD บิล: READ รายเดือน)
 * แสดงทุกห้องที่มีผู้เช่าในเดือนที่เลือก: ยังไม่จดมิเตอร์ / รอชำระ / ชำระแล้ว
 * bills.php?month=2026-10  (ไม่ใส่ = เดือนปัจจุบัน)
 */
require_once __DIR__ . '/includes/auth.php';

$thisMonth = current_month();
$month = parse_month($_GET['month'] ?? null) ?? $thisMonth;
if ($month > $thisMonth) $month = $thisMonth;   // ออกบิลล่วงหน้าไม่ได้

// ---------- ห้องที่ต้องมีบิลเดือนนี้ = ห้องที่มีผู้เช่าอยู่ + ห้องที่ออกบิลเดือนนี้ไปแล้ว ----------
$sql = "SELECT r.id AS room_id, r.room_number, r.water_type,
               b.id AS bill_id, b.total_amount, b.status AS bill_status,
               COALESCE(bt.full_name, ct.full_name) AS tenant_name
        FROM project_rooms r
        LEFT JOIN project_bills b    ON b.room_id = r.id AND b.billing_month = ?
        LEFT JOIN project_tenants bt ON bt.id = b.tenant_id
        LEFT JOIN project_tenants ct ON ct.room_id = r.id AND ct.move_out_date IS NULL
        WHERE b.id IS NOT NULL OR r.status = 'occupied'
        ORDER BY r.room_number";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $month);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- สรุปยอดของเดือน ----------
$sum = ['todo' => 0, 'unpaid' => 0.0, 'paid' => 0.0, 'unpaid_count' => 0, 'paid_count' => 0];
foreach ($rows as $row) {
    if ($row['bill_id'] === null)            { $sum['todo']++; }
    elseif ($row['bill_status'] === 'paid')  { $sum['paid'] += (float) $row['total_amount'];   $sum['paid_count']++; }
    else                                     { $sum['unpaid'] += (float) $row['total_amount']; $sum['unpaid_count']++; }
}
$firstTodo = null;
foreach ($rows as $row) { if ($row['bill_id'] === null) { $firstTodo = $row; break; } }

$ym = fn (string $m) => substr($m, 0, 7);
$rates = current_rates($conn);

$pageTitle = 'บิล ' . thai_date($month, true);
$activeNav = 'bills';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div>
        <h1>บิลเดือน <?= thai_date($month, true) ?></h1>
        <p>อัตราปัจจุบัน: ไฟ <?= rate_text($rates['elec_rate']) ?> บาท/หน่วย · น้ำ <?= rate_text($rates['water_rate']) ?> บาท/หน่วย · น้ำเหมาจ่าย <?= baht($rates['water_flat_rate']) ?>/เดือน · <a href="rates.php">เปลี่ยนอัตรา</a></p>
    </div>
    <nav class="segmented" aria-label="เลือกเดือน">
        <a class="btn-seg" href="bills.php?month=<?= $ym(shift_month($month, -1)) ?>">‹ <?= thai_date(shift_month($month, -1), true) ?></a>
        <?php if ($month < $thisMonth): ?>
            <a class="btn-seg" href="bills.php?month=<?= $ym(shift_month($month, 1)) ?>"><?= thai_date(shift_month($month, 1), true) ?> ›</a>
        <?php endif; ?>
    </nav>
</header>

<section class="summary" aria-label="สรุปบิลเดือนนี้">
    <ul class="summary-stats">
        <li>ยังไม่จดมิเตอร์ <b class="num"><?= $sum['todo'] ?></b> ห้อง</li>
        <li><span class="dot dot-overdue"></span>รอชำระ <b class="num"><?= baht($sum['unpaid']) ?></b> (<?= $sum['unpaid_count'] ?> ใบ)</li>
        <li><span class="dot dot-free"></span>ชำระแล้ว <b class="num"><?= baht($sum['paid']) ?></b> (<?= $sum['paid_count'] ?> ใบ)</li>
    </ul>
    <?php if ($firstTodo): ?>
        <div><a class="btn btn-primary" href="bill_create.php?room_id=<?= (int) $firstTodo['room_id'] ?>&month=<?= $ym($month) ?>">จดมิเตอร์ห้อง <?= e($firstTodo['room_number']) ?></a></div>
    <?php endif; ?>
</section>

<?php if (!$rows): ?>
    <div class="empty"><p>เดือนนี้ไม่มีห้องที่มีผู้เช่า จึงยังไม่มีบิลให้ออก</p><a class="btn btn-sm" href="tenant_create.php">เพิ่มผู้เช่า</a></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ห้อง</th><th>ผู้เช่า</th><th>ค่าน้ำ</th><th class="right">ยอดรวม</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="num"><b><?= e($row['room_number']) ?></b></td>
                        <td><?= $row['tenant_name'] ? e($row['tenant_name']) : '<span class="muted">-</span>' ?></td>
                        <td><?= WATER_TYPE[$row['water_type']] ?></td>
                        <td class="right num"><?= $row['bill_id'] ? baht($row['total_amount']) : '-' ?></td>
                        <td>
                            <?php if ($row['bill_id'] === null): ?>
                                <span class="pill pill-paid">ยังไม่จดมิเตอร์</span>
                            <?php elseif ($row['bill_status'] === 'paid'): ?>
                                <span class="pill pill-free">ชำระแล้ว</span>
                            <?php else: ?>
                                <span class="pill pill-<?= $month < $thisMonth ? 'overdue' : 'rented' ?>"><?= $month < $thisMonth ? 'ค้างชำระ' : 'รอชำระ' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <?php if ($row['bill_id'] === null): ?>
                                <a class="btn btn-sm btn-primary" href="bill_create.php?room_id=<?= (int) $row['room_id'] ?>&month=<?= $ym($month) ?>">จดมิเตอร์</a>
                            <?php else: ?>
                                <a class="btn btn-sm" href="bill_view.php?id=<?= (int) $row['bill_id'] ?>">ดูบิล</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
