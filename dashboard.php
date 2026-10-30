<?php
/**
 * ไฟล์: dashboard.php  (หน้าภาพรวม)
 * สรุปสถานะห้อง, ผังอาคาร, รายการค้างชำระ และความคืบหน้าการจดมิเตอร์เดือนนี้
 */
require_once __DIR__ . '/includes/auth.php';

$thisMonth = current_month();

// ---------- ทุกห้อง + ผู้เช่าปัจจุบัน + ยอดค้าง (บิลเดือนก่อน ๆ ที่ยังไม่ชำระ) ----------
$stmt = $conn->prepare("SELECT r.id, r.room_number, r.status, t.full_name,
                               COALESCE((SELECT SUM(b.total_amount) FROM project_bills b
                                         WHERE b.room_id = r.id AND b.status = 'unpaid' AND b.billing_month < ?), 0) AS unpaid
                        FROM project_rooms r
                        LEFT JOIN project_tenants t ON t.room_id = r.id AND t.move_out_date IS NULL
                        ORDER BY r.room_number");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['all' => 0, 'free' => 0, 'rented' => 0, 'overdue' => 0, 'repair' => 0];
$floors = [];
foreach ($rooms as $room) {
    $room['view_status'] = room_view_status($room['status'], (float) $room['unpaid']);
    $counts['all']++;
    $counts[$room['view_status']]++;
    $floors[room_floor($room['room_number'])][] = $room;
}
krsort($floors);
$occupied = $counts['rented'] + $counts['overdue'];
$pct = fn (int $n) => $counts['all'] ? round($n / $counts['all'] * 100, 2) . '%' : '0%';

// ---------- รายการค้างชำระ แยกตามห้อง ----------
$stmt = $conn->prepare("SELECT r.id AS room_id, r.room_number, t.full_name,
                               SUM(b.total_amount) AS amount, MIN(b.billing_month) AS oldest, COUNT(*) AS bill_count
                        FROM project_bills b
                        JOIN project_rooms r   ON r.id = b.room_id
                        JOIN project_tenants t ON t.id = b.tenant_id
                        WHERE b.status = 'unpaid' AND b.billing_month < ?
                        GROUP BY r.id, r.room_number, t.full_name
                        ORDER BY oldest, amount DESC");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$debtors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$debtTotal = array_sum(array_map(fn ($d) => (float) $d['amount'], $debtors));

// ---------- ความคืบหน้าการจดมิเตอร์เดือนนี้ ----------
$stmt = $conn->prepare("SELECT COUNT(*) AS rooms_count,
                               COALESCE(SUM(CASE WHEN b.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS done,
                               COALESCE(SUM(CASE WHEN b.status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS paid_amount,
                               COALESCE(SUM(CASE WHEN b.status = 'unpaid' THEN b.total_amount ELSE 0 END), 0) AS unpaid_amount
                        FROM project_rooms r
                        LEFT JOIN project_bills b ON b.room_id = r.id AND b.billing_month = ?
                        WHERE r.status = 'occupied' OR b.id IS NOT NULL");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$meter = $stmt->get_result()->fetch_assoc();
$stmt->close();
$meterTotal = (int) $meter['rooms_count'];
$meterDone  = (int) $meter['done'];

$pageTitle = 'ภาพรวม';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div><h1>ภาพรวมหอพัก</h1><p>รอบบิล <?= thai_date($thisMonth, true) ?></p></div>
    <a class="btn btn-primary" href="bills.php">จดมิเตอร์ประจำเดือน</a>
</header>

<section class="summary" aria-label="สรุปสถานะห้อง">
    <div class="summary-top">
        <div class="summary-total"><strong class="num"><?= $counts['all'] ?></strong><span class="muted">ห้องทั้งหมด</span></div>
        <ul class="summary-stats">
            <li><span class="dot dot-free"></span>ว่าง <b class="num"><?= $counts['free'] ?></b></li>
            <li><span class="dot dot-rented"></span>มีผู้เช่า <b class="num"><?= $occupied ?></b></li>
            <li><span class="dot dot-overdue"></span>ค้างชำระ <b class="num"><?= $counts['overdue'] ?></b></li>
            <li><span class="dot dot-repair"></span>ซ่อมบำรุง <b class="num"><?= $counts['repair'] ?></b></li>
        </ul>
    </div>
    <div class="stack-bar" role="img" aria-label="สัดส่วนห้อง ว่าง <?= $counts['free'] ?> มีผู้เช่า <?= $counts['rented'] ?> ค้างชำระ <?= $counts['overdue'] ?> ซ่อม <?= $counts['repair'] ?>">
        <span class="seg-rented" style="width:<?= $pct($counts['rented']) ?>"></span><span class="seg-overdue" style="width:<?= $pct($counts['overdue']) ?>"></span><span class="seg-free" style="width:<?= $pct($counts['free']) ?>"></span><span class="seg-repair" style="width:<?= $pct($counts['repair']) ?>"></span>
    </div>
</section>

<div class="dash-grid">
    <?php if ($floors): ?>
        <?php require __DIR__ . '/includes/building.php'; ?>
    <?php else: ?>
        <div class="empty"><p>ยังไม่มีห้องในระบบ เริ่มจากเพิ่มห้องแรก</p><a class="btn btn-sm btn-primary" href="room_create.php">เพิ่มห้อง</a></div>
    <?php endif; ?>

    <aside class="side">
        <section class="panel" aria-label="ห้องค้างชำระ">
            <div class="panel-head"><h3>ค้างชำระ</h3><span class="num" style="font-weight:600;color:var(--overdue)"><?= baht($debtTotal) ?></span></div>
            <?php if ($debtors): ?>
                <ul class="debt-list">
                    <?php foreach ($debtors as $d): ?>
                        <li><a class="debt-item" href="room_view.php?id=<?= (int) $d['room_id'] ?>">
                            <span class="debt-room"><?= e($d['room_number']) ?></span>
                            <span style="min-width:0">
                                <span class="debt-name" style="display:block"><?= e($d['full_name']) ?></span>
                                <span class="debt-days">ตั้งแต่บิล <?= thai_date($d['oldest'], true) ?><?= (int) $d['bill_count'] > 1 ? ' (' . (int) $d['bill_count'] . ' ใบ)' : '' ?></span>
                            </span>
                            <span class="debt-amt"><?= baht($d['amount']) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted" style="font-size:var(--fs-sm)">ไม่มีห้องค้างชำระ</p>
            <?php endif; ?>
        </section>

        <section class="panel" aria-label="ความคืบหน้าการจดมิเตอร์">
            <div class="panel-head"><h3>จดมิเตอร์ <?= thai_date($thisMonth, true) ?></h3><span class="num muted" style="font-size:var(--fs-sm)"><?= $meterDone ?> / <?= $meterTotal ?> ห้อง</span></div>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $meterTotal ?>" aria-valuenow="<?= $meterDone ?>">
                <span style="width:<?= $meterTotal ? round($meterDone / $meterTotal * 100, 2) : 0 ?>%"></span>
            </div>
            <p class="muted" style="font-size:var(--fs-sm)">
                <?= $meterTotal - $meterDone > 0 ? 'เหลืออีก ' . ($meterTotal - $meterDone) . ' ห้องที่ยังไม่ได้จดเลขมิเตอร์' : ($meterTotal ? 'จดครบทุกห้องแล้ว' : 'ยังไม่มีห้องที่มีผู้เช่า') ?>
            </p>
            <dl class="facts">
                <dt>ชำระแล้ว</dt><dd><?= baht($meter['paid_amount']) ?></dd>
                <dt>รอชำระ</dt><dd><?= baht($meter['unpaid_amount']) ?></dd>
            </dl>
            <a class="btn" href="bills.php">ไปหน้าบิล</a>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
