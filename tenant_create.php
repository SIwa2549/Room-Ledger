<?php
/**
 * ไฟล์: tenant_create.php  (CRUD ผู้เช่า: CREATE)
 * เพิ่มผู้เช่าเข้าห้องที่ว่าง แล้วเปลี่ยนสถานะห้องเป็น "มีผู้เช่า" ในคราวเดียว (Transaction)
 * tenant_create.php?room_id=5 = เลือกห้อง 5 ไว้ให้ก่อน
 */
require_once __DIR__ . '/includes/auth.php';

// ---------- ห้องที่ว่างอยู่ (ให้เลือกได้เฉพาะห้องเหล่านี้) ----------
$stmt = $conn->prepare("SELECT id, room_number, price, water_type FROM project_rooms
                        WHERE status = 'available' ORDER BY room_number");
$stmt->execute();
$roomChoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$roomIds = array_map(fn ($r) => (int) $r['id'], $roomChoices);

$data = [
    'room_id'      => (int) ($_GET['room_id'] ?? 0),
    'full_name'    => '',
    'phone'        => '',
    'move_in_date' => date('Y-m-d'),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$data, $errors] = validate_tenant($_POST, $roomIds);

    if (!$errors) {
        // Transaction: ทั้ง 2 คำสั่งต้องสำเร็จพร้อมกัน ถ้าพลาดข้อใดข้อหนึ่งให้ยกเลิกทั้งหมด
        $conn->begin_transaction();
        try {
            // เปลี่ยนห้องเป็น "มีผู้เช่า" เฉพาะเมื่อยังว่างอยู่ (กันกรณี 2 คนเพิ่มผู้เช่าห้องเดียวกันพร้อมกัน)
            $stmt = $conn->prepare("UPDATE project_rooms SET status = 'occupied' WHERE id = ? AND status = 'available'");
            $stmt->bind_param('i', $data['room_id']);
            $stmt->execute();
            $updated = $stmt->affected_rows;
            $stmt->close();

            if ($updated !== 1) {
                $conn->rollback();
                $errors['room_id'] = 'ห้องนี้ไม่ว่างแล้ว เลือกห้องอื่น';
            } else {
                $stmt = $conn->prepare('INSERT INTO project_tenants (room_id, full_name, phone, move_in_date) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('isss', $data['room_id'], $data['full_name'], $data['phone'], $data['move_in_date']);
                $stmt->execute();
                $stmt->close();
                $conn->commit();

                flash('success', 'เพิ่มผู้เช่า ' . $data['full_name'] . ' แล้ว');
                redirect('room_view.php?id=' . $data['room_id']);
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

$pageTitle = 'เพิ่มผู้เช่า';
$activeNav = 'tenants';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="tenants.php">ผู้เช่าทั้งหมด</a>
<header class="page-head"><div><h1>เพิ่มผู้เช่า</h1></div></header>

<?php if (!$roomChoices): ?>
    <div class="empty">
        <p>ตอนนี้ไม่มีห้องว่าง ต้องมีห้องสถานะ "ว่าง" ก่อนจึงจะเพิ่มผู้เช่าได้</p>
        <a class="btn btn-sm" href="rooms.php?status=free">ดูห้องพัก</a>
    </div>
<?php else:
    $submitLabel = 'เพิ่มผู้เช่า';
    $cancelUrl   = 'tenants.php';
    require __DIR__ . '/includes/tenant_form.php';
endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
