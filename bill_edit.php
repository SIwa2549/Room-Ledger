<?php
/**
 * ไฟล์: bill_edit.php  (CRUD บิล: UPDATE เลขมิเตอร์)
 * แก้เลขมิเตอร์ของบิลที่จดผิด แล้วคำนวณยอดใหม่ด้วยอัตราเดิมของบิลนั้น   —  bill_edit.php?id=1
 * แก้ได้เฉพาะบิลล่าสุดของห้องที่ยังไม่ชำระ (บิลเดือนถัดไปใช้เลขนี้เป็นเลขตั้งต้น)
 */
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบบิลที่ต้องการ');
    redirect('bills.php');
}

$stmt = $conn->prepare('SELECT b.*, r.room_number, t.full_name,
                               (SELECT COUNT(*) FROM project_bills nb WHERE nb.room_id = b.room_id AND nb.billing_month > b.billing_month) AS later_count,
                               (SELECT COUNT(*) FROM project_bills pb WHERE pb.room_id = b.room_id AND pb.billing_month < b.billing_month) AS earlier_count
                        FROM project_bills b
                        JOIN project_rooms r   ON r.id = b.room_id
                        JOIN project_tenants t ON t.id = b.tenant_id
                        WHERE b.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$bill) {
    flash('error', 'ไม่พบบิลที่ต้องการ อาจถูกลบไปแล้ว');
    redirect('bills.php');
}
if ($bill['status'] === 'paid' || (int) $bill['later_count'] > 0) {
    flash('error', 'แก้บิลนี้ไม่ได้ แก้ได้เฉพาะบิลล่าสุดของห้องที่ยังไม่ชำระ');
    redirect('bill_view.php?id=' . $id);
}

$month     = substr($bill['billing_month'], 0, 10);
$isFlat    = $bill['water_curr'] === null;
$rent      = (float) $bill['rent_amount'];
$elecRate  = (float) $bill['elec_rate'];
$waterRate = (float) $bill['water_rate'];
// เลขเดือนก่อนแก้ได้เฉพาะบิลใบแรกของห้อง (ใบอื่นใช้เลขจากบิลเดือนก่อน)
$isFirst = (int) $bill['earlier_count'] === 0;
$askPrev = ['elec' => $isFirst, 'water' => $isFirst && !$isFlat];

$form = [
    'elec_prev'  => (string) $bill['elec_prev'],
    'elec_curr'  => (string) $bill['elec_curr'],
    'water_prev' => (string) ($bill['water_prev'] ?? ''),
    'water_curr' => (string) ($bill['water_curr'] ?? ''),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($askPrev['elec'])  $form['elec_prev']  = (string) ($_POST['elec_prev'] ?? '');
    if ($askPrev['water']) $form['water_prev'] = (string) ($_POST['water_prev'] ?? '');
    $form['elec_curr']  = (string) ($_POST['elec_curr'] ?? '');
    $form['water_curr'] = (string) ($_POST['water_curr'] ?? '');

    [$elecPrev, $err] = read_meter($form['elec_prev'], 0, 'ไฟเดือนก่อน', false);
    if ($err) $errors['elec_prev'] = $err;
    [$elecCurr, $err] = read_meter($form['elec_curr'], $elecPrev ?? 0, 'ไฟเดือนนี้');
    if ($err) $errors['elec_curr'] = $err;

    $waterPrev = $waterCurr = null;
    if (!$isFlat) {
        [$waterPrev, $err] = read_meter($form['water_prev'], 0, 'น้ำเดือนก่อน', false);
        if ($err) $errors['water_prev'] = $err;
        [$waterCurr, $err] = read_meter($form['water_curr'], $waterPrev ?? 0, 'น้ำเดือนนี้');
        if ($err) $errors['water_curr'] = $err;
    }

    if (!$errors) {
        $b = bill_breakdown($rent, $elecPrev, $elecCurr, $elecRate, $waterPrev, $waterCurr, $waterRate);
        $stmt = $conn->prepare("UPDATE project_bills
                                SET elec_prev = ?, elec_curr = ?, water_prev = ?, water_curr = ?, total_amount = ?
                                WHERE id = ? AND status = 'unpaid' LIMIT 1");
        $stmt->bind_param('iiiidi', $elecPrev, $elecCurr, $waterPrev, $waterCurr, $b['total'], $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'แก้เลขมิเตอร์แล้ว ยอดใหม่ ' . baht($b['total']));
        redirect('bill_view.php?id=' . $id);
    }
}

$pageTitle = 'แก้บิลห้อง ' . $bill['room_number'];
$activeNav = 'bills';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="bill_view.php?id=<?= (int) $id ?>">บิลห้อง <?= e($bill['room_number']) ?></a>
<header class="page-head"><div>
    <h1>แก้เลขมิเตอร์ห้อง <?= e($bill['room_number']) ?></h1>
    <p>บิลเดือน <?= thai_date($month, true) ?> · <?= e($bill['full_name']) ?> · ใช้อัตราเดิมของบิลนี้</p>
</div></header>

<?php
$submitLabel = 'บันทึกการแก้ไข';
$cancelUrl   = 'bill_view.php?id=' . $id;
require __DIR__ . '/includes/meter_form.php';
?>

<?php require __DIR__ . '/includes/footer.php'; ?>
