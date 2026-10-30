<?php
/**
 * ไฟล์: my_bills.php  (หน้าของผู้เช่า)
 * ผู้เช่าเห็นเฉพาะห้องและบิลของตัวเอง
 * Authorization: ทุก Query กรองด้วย tenant_id ของบัญชีที่ล็อกอินอยู่ (จากฐานข้อมูล ไม่ใช่จาก URL)
 */
const PAGE_ROLE = 'tenant';
require_once __DIR__ . '/includes/auth.php';

$tenantId = (int) $currentUser['tenant_id'];

// ---------- ข้อมูลผู้เช่าและห้อง ----------
$stmt = $conn->prepare('SELECT t.full_name, t.phone, t.move_in_date, t.move_out_date, r.room_number, r.price, r.water_type
                        FROM project_tenants t JOIN project_rooms r ON r.id = t.room_id
                        WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------- บิลทั้งหมดของผู้เช่าคนนี้เท่านั้น ----------
$stmt = $conn->prepare('SELECT id, billing_month, elec_prev, elec_curr, water_prev, water_curr, total_amount, status
                        FROM project_bills
                        WHERE tenant_id = ?
                        ORDER BY billing_month DESC');
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$thisMonth = current_month();
$unpaid = 0.0;
foreach ($bills as $bill) {
    if ($bill['status'] === 'unpaid') $unpaid += (float) $bill['total_amount'];
}
$rates = current_rates($conn);

$pageTitle = 'บิลของฉัน';
$activeNav = 'my_bills';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$me): ?>
    <div class="empty"><p>บัญชีนี้ยังไม่ได้ผูกกับข้อมูลผู้เช่า ติดต่อเจ้าของหอ</p></div>
<?php else: ?>
    <header class="page-head">
        <div>
            <h1>สวัสดี <?= e($me['full_name']) ?></h1>
            <p>ห้อง <?= e($me['room_number']) ?> · <?= $me['move_out_date'] === null ? 'เข้าพักตั้งแต่ ' . thai_date($me['move_in_date']) : 'ย้ายออกเมื่อ ' . thai_date($me['move_out_date']) ?></p>
        </div>
    </header>

    <?php if ($unpaid > 0): ?>
        <div class="flash flash-error">ยอดที่ยังไม่ชำระ <?= baht($unpaid) ?></div>
    <?php elseif ($bills): ?>
        <div class="flash flash-success">ชำระครบทุกบิลแล้ว</div>
    <?php endif; ?>

    <div class="detail-grid">
        <section class="panel">
            <h2 class="h-sm">ห้องของฉัน</h2>
            <dl class="facts">
                <dt>ห้อง</dt><dd><?= e($me['room_number']) ?></dd>
                <dt>ค่าเช่า</dt><dd><?= baht($me['price']) ?> / เดือน</dd>
                <dt>ค่าน้ำ</dt><dd><?= $me['water_type'] === 'meter' ? 'ตามมิเตอร์ ' . rate_text($rates['water_rate']) . ' บาท/หน่วย' : 'เหมาจ่าย ' . baht($rates['water_flat_rate']) . ' / เดือน' ?></dd>
                <dt>ค่าไฟ</dt><dd><?= rate_text($rates['elec_rate']) ?> บาท/หน่วย</dd>
            </dl>
        </section>
        <section class="panel">
            <h2 class="h-sm">ข้อมูลติดต่อที่หอมีอยู่</h2>
            <dl class="facts">
                <dt>ชื่อ</dt><dd><?= e($me['full_name']) ?></dd>
                <dt>โทร</dt><dd><?= e($me['phone']) ?></dd>
                <dt>อีเมลเข้าระบบ</dt><dd><?= e($currentUser['email']) ?></dd>
            </dl>
            <p class="hint">ข้อมูลไม่ถูกต้อง แจ้งเจ้าของหอเพื่อแก้ไข</p>
        </section>
    </div>

    <section class="stack">
        <h2 class="h-sm">บิลของฉัน</h2>
        <?php if ($bills): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>เดือน</th><th class="right">ไฟ (หน่วย)</th><th class="right">น้ำ</th><th class="right">ยอดรวม</th><th>สถานะ</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($bills as $bill): $m = substr($bill['billing_month'], 0, 10); ?>
                            <tr>
                                <td><?= thai_date($m, true) ?></td>
                                <td class="right num"><?= number_format($bill['elec_curr'] - $bill['elec_prev']) ?></td>
                                <td class="right num"><?= $bill['water_curr'] === null ? 'เหมาจ่าย' : number_format($bill['water_curr'] - $bill['water_prev']) . ' หน่วย' ?></td>
                                <td class="right num"><?= baht($bill['total_amount']) ?></td>
                                <td>
                                    <?php if ($bill['status'] === 'paid'): ?><span class="pill pill-free">ชำระแล้ว</span>
                                    <?php elseif ($m < $thisMonth): ?><span class="pill pill-overdue">ค้างชำระ</span>
                                    <?php else: ?><span class="pill pill-rented">รอชำระ</span><?php endif; ?>
                                </td>
                                <td class="right"><a class="btn btn-sm" href="my_bill.php?id=<?= (int) $bill['id'] ?>">ดูใบแจ้งค่าเช่า</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">ยังไม่มีบิล บิลแรกจะขึ้นที่นี่หลังเจ้าของหอจดมิเตอร์</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
