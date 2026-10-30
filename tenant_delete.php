<?php
/**
 * ไฟล์: tenant_delete.php  (CRUD ผู้เช่า: DELETE)
 * ลบได้เฉพาะผู้เช่าที่ยังไม่มีบิล (เช่น บันทึกผิดคน) ถ้ามีบิลแล้วให้ใช้ "บันทึกย้ายออก" แทน
 * GET = หน้ายืนยัน, POST = ลบ (ถ้าผู้เช่ายังพักอยู่ ห้องจะกลับเป็นว่าง)
 */
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    flash('error', 'ไม่พบผู้เช่าที่ต้องการลบ');
    redirect('tenants.php');
}

$stmt = $conn->prepare('SELECT t.id, t.room_id, t.full_name, t.move_out_date, r.room_number,
                               (SELECT COUNT(*) FROM project_bills b WHERE b.tenant_id = t.id) AS bill_count
                        FROM project_tenants t JOIN project_rooms r ON r.id = t.room_id
                        WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tenant) {
    flash('error', 'ไม่พบผู้เช่าที่ต้องการลบ อาจถูกลบไปแล้ว');
    redirect('tenants.php');
}
$canDelete = (int) $tenant['bill_count'] === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$canDelete) {
        flash('error', 'ลบ ' . $tenant['full_name'] . ' ไม่ได้ เพราะมีบิลผูกอยู่ ให้ใช้บันทึกย้ายออกแทน');
        redirect('tenant_edit.php?id=' . $id);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('DELETE FROM project_tenants WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        if ($tenant['move_out_date'] === null) {   // ยังพักอยู่ -> ห้องกลับเป็นว่าง
            $stmt = $conn->prepare("UPDATE project_rooms SET status = 'available' WHERE id = ? AND status = 'occupied'");
            $stmt->bind_param('i', $tenant['room_id']);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ($e->getCode() === 1451) {   // มีบิลถูกเพิ่มเข้ามาระหว่างนั้น
            flash('error', 'ลบไม่ได้ เพราะมีบิลผูกอยู่ ให้ใช้บันทึกย้ายออกแทน');
            redirect('tenant_edit.php?id=' . $id);
        }
        throw $e;
    }
    flash('success', 'ลบผู้เช่า ' . $tenant['full_name'] . ' แล้ว');
    redirect('tenants.php');
}

$pageTitle = 'ลบผู้เช่า';
$activeNav = 'tenants';
require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="tenant_edit.php?id=<?= (int) $id ?>"><?= e($tenant['full_name']) ?></a>
<header class="page-head"><div><h1>ลบผู้เช่า</h1></div></header>

<section class="panel form-narrow">
    <?php if ($canDelete): ?>
        <p><b><?= e($tenant['full_name']) ?></b> ห้อง <?= e($tenant['room_number']) ?> จะถูกลบถาวร กู้คืนไม่ได้
           <?= $tenant['move_out_date'] === null ? 'และห้อง ' . e($tenant['room_number']) . ' จะกลับเป็นสถานะว่าง' : '' ?></p>
        <p class="muted">ใช้สำหรับลบข้อมูลที่บันทึกผิด ถ้าผู้เช่าย้ายออกจริง ให้ใช้ "บันทึกย้ายออก" เพื่อเก็บประวัติไว้</p>
        <form method="post" class="actions">
            <?= csrf_field() ?>
            <button class="btn btn-danger-solid" type="submit">ลบ <?= e($tenant['full_name']) ?></button>
            <a class="btn btn-ghost" href="tenant_edit.php?id=<?= (int) $id ?>">ยกเลิก</a>
        </form>
    <?php else: ?>
        <p>ลบไม่ได้ เพราะมีบิล <?= (int) $tenant['bill_count'] ?> ใบผูกกับผู้เช่าคนนี้ การลบจะทำให้ประวัติบิลหายไป</p>
        <div class="actions">
            <?php if ($tenant['move_out_date'] === null): ?>
                <a class="btn" href="tenant_moveout.php?id=<?= (int) $id ?>">บันทึกย้ายออกแทน</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="tenant_edit.php?id=<?= (int) $id ?>">กลับ</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
