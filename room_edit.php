<?php
/**
 * ไฟล์: room_edit.php  (CRUD: UPDATE)
 * GET  = ดึงข้อมูลห้องเดิมมาใส่ฟอร์ม   —  room_edit.php?id=1
 * POST = ตรวจข้อมูล -> UPDATE ... WHERE id = ? -> กลับไปหน้ารายละเอียดห้อง
 */
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบห้องที่ต้องการแก้ไข');
    redirect('rooms.php');
}

// ---------- ดึงข้อมูลเดิม ----------
$stmt = $conn->prepare('SELECT id, room_number, price, status, water_type FROM project_rooms WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
    flash('error', 'ไม่พบห้องที่ต้องการแก้ไข อาจถูกลบไปแล้ว');
    redirect('rooms.php');
}

// ห้องนี้มีผู้เช่าอยู่ไหม (ถ้ามี จะล็อกสถานะไว้ที่ "มีผู้เช่า")
$stmt = $conn->prepare('SELECT COUNT(*) FROM project_tenants WHERE room_id = ? AND move_out_date IS NULL');
$stmt->bind_param('i', $id);
$stmt->execute();
$hasTenant = $stmt->get_result()->fetch_row()[0] > 0;
$stmt->close();

// ค่าในฟอร์มเริ่มจากข้อมูลเดิม (ตัด .00 ออกให้อ่านง่าย)
$data = [
    'room_number' => $room['room_number'],
    'price'       => rtrim(rtrim($room['price'], '0'), '.'),
    'water_type'  => $room['water_type'],
    'status'      => $room['status'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$data, $errors] = validate_room($_POST, $hasTenant);

    if (!$errors) {
        $sql  = 'UPDATE project_rooms SET room_number = ?, price = ?, status = ?, water_type = ? WHERE id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $price = (float) $data['price'];
        $stmt->bind_param('sdssi', $data['room_number'], $price, $data['status'], $data['water_type'], $id);

        try {
            $stmt->execute();
            flash('success', 'บันทึกการแก้ไขห้อง ' . $data['room_number'] . ' แล้ว');
            redirect('room_view.php?id=' . $id);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $errors['room_number'] = 'มีห้อง ' . $data['room_number'] . ' อยู่แล้ว ใช้เลขห้องอื่น';
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = 'แก้ไขห้อง ' . $room['room_number'];
$activeNav = 'rooms';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="room_view.php?id=<?= (int) $id ?>">ห้อง <?= e($room['room_number']) ?></a>
<header class="page-head"><div><h1>แก้ไขห้อง <?= e($room['room_number']) ?></h1>
    <p>การเปลี่ยนค่าเช่ามีผลกับบิลเดือนถัดไป บิลที่ออกแล้วยังคงยอดเดิม</p></div></header>

<?php
$submitLabel = 'บันทึกการแก้ไข';
$cancelUrl   = 'room_view.php?id=' . $id;
require __DIR__ . '/includes/room_form.php';
?>

<?php require __DIR__ . '/includes/footer.php'; ?>
