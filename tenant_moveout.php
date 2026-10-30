<?php
/**
 * ไฟล์: tenant_moveout.php  (CRUD ผู้เช่า: UPDATE วันย้ายออก)
 * บันทึกวันย้ายออก แล้วเปลี่ยนห้องกลับเป็น "ว่าง" ในคราวเดียว (Transaction)
 * ประวัติผู้เช่าและบิลเก่ายังเก็บไว้ครบ ไม่ถูกลบ
 */
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบผู้เช่าที่ต้องการ');
    redirect('tenants.php');
}

$stmt = $conn->prepare('SELECT t.id, t.room_id, t.full_name, t.move_in_date, t.move_out_date, r.room_number
                        FROM project_tenants t JOIN project_rooms r ON r.id = t.room_id
                        WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tenant || $tenant['move_out_date'] !== null) {
    flash('error', 'ไม่พบผู้เช่าที่ยังพักอยู่ หรือบันทึกย้ายออกไปแล้ว');
    redirect('tenants.php');
}

// ยอดค้างชำระของผู้เช่าคนนี้ (แจ้งเตือนก่อนย้ายออก)
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM project_bills WHERE tenant_id = ? AND status = 'unpaid'");
$stmt->bind_param('i', $id);
$stmt->execute();
$unpaid = (float) $stmt->get_result()->fetch_row()[0];
$stmt->close();

$moveOut = date('Y-m-d');
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $moveOut = trim((string) ($_POST['move_out_date'] ?? ''));

    if (!valid_date($moveOut)) {
        $error = 'เลือกวันที่ย้ายออก';
    } elseif ($moveOut < $tenant['move_in_date']) {
        $error = 'วันย้ายออกต้องไม่ก่อนวันเข้าพัก (' . thai_date($tenant['move_in_date']) . ')';
    } elseif ($moveOut > date('Y-m-d', strtotime('+30 days'))) {
        $error = 'วันย้ายออกล่วงหน้าได้ไม่เกิน 30 วัน';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE project_tenants SET move_out_date = ? WHERE id = ? AND move_out_date IS NULL');
            $stmt->bind_param('si', $moveOut, $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE project_rooms SET status = 'available' WHERE id = ? AND status = 'occupied'");
            $stmt->bind_param('i', $tenant['room_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            throw $e;
        }
        flash('success', $tenant['full_name'] . ' ย้ายออกจากห้อง ' . $tenant['room_number'] . ' แล้ว ห้องกลับเป็นสถานะว่าง');
        redirect('room_view.php?id=' . $tenant['room_id']);
    }
}

$pageTitle = 'บันทึกย้ายออก';
$activeNav = 'tenants';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="tenant_edit.php?id=<?= (int) $id ?>"><?= e($tenant['full_name']) ?></a>
<header class="page-head"><div><h1>บันทึกย้ายออก</h1>
    <p><?= e($tenant['full_name']) ?> · ห้อง <?= e($tenant['room_number']) ?> · เข้าพักตั้งแต่ <?= thai_date($tenant['move_in_date']) ?></p></div></header>

<?php if ($unpaid > 0): ?>
    <div class="flash flash-error">ผู้เช่ายังค้างชำระ <?= baht($unpaid) ?> บิลค้างจะยังอยู่ในระบบหลังย้ายออก</div>
<?php endif; ?>

<form class="form panel form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="move_out_date">วันที่ย้ายออก</label>
        <input id="move_out_date" name="move_out_date" type="date" value="<?= e($moveOut) ?>" required<?= $error ? ' aria-invalid="true" aria-describedby="move_out_date-error"' : '' ?>>
        <span class="hint">ห้อง <?= e($tenant['room_number']) ?> จะกลับเป็นสถานะว่าง ประวัติผู้เช่าและบิลยังเก็บไว้</span>
        <?php if ($error): ?><span class="error" id="move_out_date-error"><?= e($error) ?></span><?php endif; ?>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="submit">บันทึกย้ายออก</button>
        <a class="btn btn-ghost" href="tenant_edit.php?id=<?= (int) $id ?>">ยกเลิก</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
