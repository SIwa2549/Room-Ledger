<?php
/**
 * ไฟล์: room_delete.php  (CRUD: DELETE)
 * GET  = หน้ายืนยันการลบ (ไม่ลบทันทีจากการกดลิงก์)
 * POST = ตรวจ CSRF -> ตรวจว่าไม่มีผู้เช่า/บิล -> DELETE ... WHERE id = ? -> กลับไปหน้า rooms.php
 */
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบห้องที่ต้องการลบ');
    redirect('rooms.php');
}

$stmt = $conn->prepare('SELECT id, room_number, price, status FROM project_rooms WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
    flash('error', 'ไม่พบห้องที่ต้องการลบ อาจถูกลบไปแล้ว');
    redirect('rooms.php');
}

// ---------- ตรวจว่ามีข้อมูลที่ผูกกับห้องนี้หรือไม่ ----------
// ห้องที่เคยมีผู้เช่าหรือบิล ห้ามลบ เพราะจะทำให้ประวัติหาย (ฐานข้อมูลตั้ง ON DELETE RESTRICT ไว้ด้วย)
$stmt = $conn->prepare('SELECT
                          (SELECT COUNT(*) FROM project_tenants WHERE room_id = ?) AS tenant_count,
                          (SELECT COUNT(*) FROM project_bills   WHERE room_id = ?) AS bill_count');
$stmt->bind_param('ii', $id, $id);
$stmt->execute();
$usage = $stmt->get_result()->fetch_assoc();
$stmt->close();
$canDelete = (int) $usage['tenant_count'] === 0 && (int) $usage['bill_count'] === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!$canDelete) {
        flash('error', 'ลบห้อง ' . $room['room_number'] . ' ไม่ได้ เพราะมีประวัติผู้เช่าหรือบิล ถ้าไม่ใช้ห้องแล้วให้ตั้งสถานะเป็นซ่อมบำรุงแทน');
        redirect('room_view.php?id=' . $id);
    }

    $stmt = $conn->prepare('DELETE FROM project_rooms WHERE id = ? LIMIT 1');   // มี WHERE เสมอ ห้ามลืม
    $stmt->bind_param('i', $id);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // 1451 = ติด Foreign Key (มีข้อมูลถูกเพิ่มเข้ามาระหว่างที่เปิดหน้านี้)
        if ($e->getCode() === 1451) {
            flash('error', 'ลบห้อง ' . $room['room_number'] . ' ไม่ได้ เพราะมีข้อมูลผู้เช่าหรือบิลผูกอยู่');
            redirect('room_view.php?id=' . $id);
        }
        throw $e;
    }

    flash('success', 'ลบห้อง ' . $room['room_number'] . ' แล้ว');
    redirect('rooms.php');
}

$pageTitle = 'ลบห้อง ' . $room['room_number'];
$activeNav = 'rooms';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="room_view.php?id=<?= (int) $id ?>">ห้อง <?= e($room['room_number']) ?></a>
<header class="page-head"><div><h1>ลบห้อง <?= e($room['room_number']) ?></h1></div></header>

<section class="panel form-narrow">
    <?php if ($canDelete): ?>
        <p>ห้อง <b><?= e($room['room_number']) ?></b> ค่าเช่า <?= baht($room['price']) ?> จะถูกลบถาวร กู้คืนไม่ได้</p>
        <form method="post" class="actions">
            <?= csrf_field() ?>
            <button class="btn btn-danger-solid" type="submit">ลบห้อง <?= e($room['room_number']) ?></button>
            <a class="btn btn-ghost" href="room_view.php?id=<?= (int) $id ?>">ยกเลิก</a>
        </form>
    <?php else: ?>
        <p>ลบห้องนี้ไม่ได้ เพราะมีผู้เช่า <?= (int) $usage['tenant_count'] ?> คน และบิล <?= (int) $usage['bill_count'] ?> ใบผูกอยู่ การลบจะทำให้ประวัติหายไป</p>
        <p class="muted">ถ้าไม่ใช้ห้องนี้แล้ว ให้แก้ไขสถานะเป็น "ซ่อมบำรุง" แทน</p>
        <div class="actions">
            <a class="btn" href="room_edit.php?id=<?= (int) $id ?>">แก้ไขห้อง</a>
            <a class="btn btn-ghost" href="room_view.php?id=<?= (int) $id ?>">กลับ</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
