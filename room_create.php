<?php
/**
 * ไฟล์: room_create.php  (CRUD: CREATE)
 * GET  = แสดงฟอร์มเพิ่มห้อง
 * POST = ตรวจข้อมูล -> INSERT ด้วย Prepared Statement -> กลับไปหน้า rooms.php
 */
require_once __DIR__ . '/includes/auth.php';

// ค่าเริ่มต้นของฟอร์ม
$data   = ['room_number' => '', 'price' => '', 'water_type' => 'meter', 'status' => 'available'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$data, $errors] = validate_room($_POST);

    if (!$errors) {
        $sql  = 'INSERT INTO project_rooms (room_number, price, status, water_type) VALUES (?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        $price = (float) $data['price'];
        $stmt->bind_param('sdss', $data['room_number'], $price, $data['status'], $data['water_type']);
        // 's' = string, 'd' = double (ทศนิยม)

        try {
            $stmt->execute();
            flash('success', 'เพิ่มห้อง ' . $data['room_number'] . ' แล้ว');
            redirect('room_view.php?id=' . $conn->insert_id);
        } catch (mysqli_sql_exception $e) {
            // 1062 = Duplicate entry: เลขห้องซ้ำ (ตาราง project_rooms ตั้ง UNIQUE ไว้ที่ room_number)
            if ($e->getCode() === 1062) {
                $errors['room_number'] = 'มีห้อง ' . $data['room_number'] . ' อยู่แล้ว ใช้เลขห้องอื่น';
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = 'เพิ่มห้อง';
$activeNav = 'rooms';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="rooms.php">ห้องพักทั้งหมด</a>
<header class="page-head"><div><h1>เพิ่มห้อง</h1><p>ห้องใหม่จะมีสถานะว่างหรือซ่อมบำรุง เมื่อเพิ่มผู้เช่าแล้วจึงเปลี่ยนเป็นมีผู้เช่า</p></div></header>

<?php
$hasTenant   = false;
$submitLabel = 'เพิ่มห้อง';
$cancelUrl   = 'rooms.php';
require __DIR__ . '/includes/room_form.php';
?>

<?php require __DIR__ . '/includes/footer.php'; ?>
