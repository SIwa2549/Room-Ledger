<?php
/**
 * ไฟล์: bill_create.php  (CRUD บิล: CREATE)
 * จดมิเตอร์และออกบิลของห้อง 1 ห้องใน 1 เดือน   —  bill_create.php?room_id=5&month=2026-10
 * เลขเดือนก่อนดึงจากบิลล่าสุดของห้องโดยอัตโนมัติ ยอดเงินคำนวณด้วย PHP แล้วบันทึกพร้อมอัตราที่ใช้
 */
require_once __DIR__ . '/includes/auth.php';

$roomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$month  = parse_month($_GET['month'] ?? null) ?? current_month();
$monthParam = substr($month, 0, 7);
if (!$roomId || $month > current_month()) {
    flash('error', 'เลือกห้องและเดือนที่จะออกบิลจากหน้าบิล');
    redirect('bills.php');
}

// ---------- ห้อง + ผู้เช่าปัจจุบัน ----------
$stmt = $conn->prepare("SELECT r.id, r.room_number, r.price, r.water_type, r.status, t.id AS tenant_id, t.full_name
                        FROM project_rooms r
                        LEFT JOIN project_tenants t ON t.room_id = r.id AND t.move_out_date IS NULL
                        WHERE r.id = ? LIMIT 1");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room || $room['tenant_id'] === null) {
    flash('error', 'ออกบิลได้เฉพาะห้องที่มีผู้เช่าอยู่');
    redirect('bills.php?month=' . $monthParam);
}

// ---------- มีบิลเดือนนี้แล้ว หรือมีบิลเดือนหลังจากนี้แล้วหรือยัง ----------
$stmt = $conn->prepare('SELECT id, billing_month FROM project_bills WHERE room_id = ? AND billing_month >= ? ORDER BY billing_month LIMIT 1');
$stmt->bind_param('is', $roomId, $month);
$stmt->execute();
$later = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($later) {
    if (substr($later['billing_month'], 0, 10) === $month) {
        flash('error', 'ห้อง ' . $room['room_number'] . ' มีบิลเดือน ' . thai_date($month, true) . ' แล้ว');
        redirect('bill_view.php?id=' . $later['id']);
    }
    flash('error', 'ออกบิลย้อนหลังเดือนนี้ไม่ได้ เพราะห้อง ' . $room['room_number'] . ' มีบิลเดือน ' . thai_date($later['billing_month'], true) . ' แล้ว');
    redirect('bills.php?month=' . $monthParam);
}

// ---------- เลขมิเตอร์เดือนก่อน = เลขเดือนนี้ของบิลล่าสุด ----------
$stmt = $conn->prepare('SELECT elec_curr, water_curr FROM project_bills WHERE room_id = ? AND billing_month < ? ORDER BY billing_month DESC LIMIT 1');
$stmt->bind_param('is', $roomId, $month);
$stmt->execute();
$last = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isFlat    = $room['water_type'] === 'flat_rate';
$rent      = (float) $room['price'];
$rates     = current_rates($conn);      // อัตราปัจจุบันจากหน้า "อัตราค่าบริการ" จะถูกบันทึกลงบิลใบนี้
$elecRate  = $rates['elec_rate'];
$waterRate = $isFlat ? $rates['water_flat_rate'] : $rates['water_rate'];
$askPrev   = [
    'elec'  => $last === null,
    'water' => !$isFlat && ($last === null || $last['water_curr'] === null),
];
$form = [
    'elec_prev'  => $last['elec_curr'] ?? '',
    'water_prev' => $last['water_curr'] ?? '',
    'elec_curr'  => '',
    'water_curr' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($askPrev['elec'])  $form['elec_prev']  = (string) ($_POST['elec_prev'] ?? '');
    if ($askPrev['water']) $form['water_prev'] = (string) ($_POST['water_prev'] ?? '');
    $form['elec_curr']  = (string) ($_POST['elec_curr'] ?? '');
    $form['water_curr'] = (string) ($_POST['water_curr'] ?? '');

    // ตรวจเลขเดือนก่อน (เฉพาะกรณีที่ต้องกรอกเอง) แล้วตรวจเลขเดือนนี้ว่าไม่น้อยกว่าเลขเดือนก่อน
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
        $userId = (int) $_SESSION['user_id'];
        $tenantId = (int) $room['tenant_id'];

        $sql = 'INSERT INTO project_bills (room_id, tenant_id, created_by, billing_month, rent_amount,
                    water_prev, water_curr, water_rate, elec_prev, elec_curr, elec_rate, total_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'unpaid\')';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiisdiididdd', $roomId, $tenantId, $userId, $month, $rent,
            $waterPrev, $waterCurr, $waterRate, $elecPrev, $elecCurr, $elecRate, $b['total']);
        try {
            $stmt->execute();
            $billId = $conn->insert_id;
            flash('success', 'ออกบิลห้อง ' . $room['room_number'] . ' เดือน ' . thai_date($month, true) . ' แล้ว ยอด ' . baht($b['total']));
            redirect('bill_view.php?id=' . $billId);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {   // มีคนออกบิลห้องนี้เดือนนี้ไปก่อนแล้ว
                flash('error', 'ห้อง ' . $room['room_number'] . ' มีบิลเดือนนี้แล้ว');
                redirect('bills.php?month=' . $monthParam);
            }
            throw $e;
        }
    }
}

$pageTitle = 'จดมิเตอร์ห้อง ' . $room['room_number'];
$activeNav = 'bills';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="bills.php?month=<?= $monthParam ?>">บิลเดือน <?= thai_date($month, true) ?></a>
<header class="page-head"><div>
    <h1>จดมิเตอร์ห้อง <?= e($room['room_number']) ?></h1>
    <p>บิลเดือน <?= thai_date($month, true) ?> · <?= e($room['full_name']) ?> · ค่าเช่า <?= baht($rent) ?></p>
</div></header>

<?php
$submitLabel = 'ออกบิล ' . thai_date($month, true);
$cancelUrl   = 'bills.php?month=' . $monthParam;
require __DIR__ . '/includes/meter_form.php';
?>

<?php require __DIR__ . '/includes/footer.php'; ?>
