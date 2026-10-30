<?php
/**
 * ไฟล์: my_requests.php  (หน้าของผู้เช่า: แจ้งซ่อม)
 * ผู้เช่าแจ้งปัญหาในห้อง (ล้างแอร์ ก๊อกเสีย น้ำไม่ไหล ฯลฯ) และติดตามสถานะรายการของตัวเอง
 * Authorization: tenant_id มาจากบัญชีที่ล็อกอินอยู่เท่านั้น ไม่รับจากฟอร์มหรือ URL
 */
const PAGE_ROLE = 'tenant';
require_once __DIR__ . '/includes/auth.php';

$tenantId = (int) $currentUser['tenant_id'];

$stmt = $conn->prepare('SELECT t.full_name, t.move_out_date, r.room_number
                        FROM project_tenants t JOIN project_rooms r ON r.id = t.room_id
                        WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
$canReport = $me && $me['move_out_date'] === null;   // ย้ายออกแล้วแจ้งซ่อมใหม่ไม่ได้ แต่ยังดูประวัติได้

// ---------- POST: แจ้งซ่อมใหม่ ----------
$old = ['category' => '', 'detail' => ''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReport) {
    csrf_check();
    $old = [
        'category' => (string) ($_POST['category'] ?? ''),
        'detail'   => trim((string) ($_POST['detail'] ?? '')),
    ];
    if (!array_key_exists($old['category'], REQUEST_CATEGORY)) {
        $errors['category'] = 'เลือกประเภทปัญหา';
    }
    if ($old['detail'] === '') {
        $errors['detail'] = 'อธิบายปัญหาสั้น ๆ เช่น จุดที่เสีย อาการ';
    } elseif (mb_strlen($old['detail']) > 500) {
        $errors['detail'] = 'รายละเอียดยาวเกินไป (ไม่เกิน 500 ตัวอักษร)';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO project_requests (tenant_id, category, detail) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $tenantId, $old['category'], $old['detail']);
        $stmt->execute();
        $stmt->close();
        flash('success', 'ส่งเรื่องแจ้งซ่อมแล้ว เจ้าของหอจะดำเนินการให้เร็วที่สุด');
        redirect('my_requests.php');
    }
}

// ---------- รายการแจ้งซ่อมของผู้เช่าคนนี้เท่านั้น ----------
$stmt = $conn->prepare("SELECT id, category, detail, status, created_at, completed_at
                        FROM project_requests
                        WHERE tenant_id = ?
                        ORDER BY status = 'done', created_at DESC");
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'แจ้งซ่อม';
$activeNav = 'my_requests';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$me): ?>
    <div class="empty"><p>บัญชีนี้ยังไม่ได้ผูกกับข้อมูลผู้เช่า ติดต่อเจ้าของหอ</p></div>
<?php else: ?>
    <header class="page-head">
        <div>
            <h1>แจ้งซ่อม</h1>
            <p>ห้อง <?= e($me['room_number']) ?> · แจ้งปัญหาในห้องให้เจ้าของหอทราบ</p>
        </div>
    </header>

    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">ส่งเรื่องไม่ได้ แก้ไขช่องที่มีข้อความสีแดงก่อน</div>
    <?php endif; ?>

    <?php if ($canReport): ?>
        <form class="form panel form-narrow" method="post" novalidate>
            <?= csrf_field() ?>
            <h2 class="h-sm">แจ้งปัญหาใหม่</h2>
            <div class="field">
                <label for="category">ประเภทปัญหา</label>
                <select id="category" name="category" required<?= invalid_attr($errors, 'category') ?>>
                    <option value="">เลือกประเภท</option>
                    <?php foreach (REQUEST_CATEGORY as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $old['category'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'category') ?>
            </div>
            <div class="field">
                <label for="detail">รายละเอียด</label>
                <textarea id="detail" name="detail" maxlength="500" required placeholder="เช่น แอร์มีน้ำหยด, ก๊อกอ่างล้างหน้าปิดไม่สนิท"<?= invalid_attr($errors, 'detail') ?>><?= e($old['detail']) ?></textarea>
                <span class="hint">ไม่เกิน 500 ตัวอักษร</span>
                <?= field_error($errors, 'detail') ?>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">ส่งเรื่องแจ้งซ่อม</button>
            </div>
        </form>
    <?php else: ?>
        <div class="empty"><p>บันทึกย้ายออกแล้ว จึงแจ้งซ่อมใหม่ไม่ได้ ดูได้เฉพาะประวัติด้านล่าง</p></div>
    <?php endif; ?>

    <section class="stack">
        <h2 class="h-sm">รายการที่แจ้งไว้</h2>
        <?php if ($requests): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>วันที่แจ้ง</th><th>ประเภท</th><th>รายละเอียด</th><th>สถานะ</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?= thai_date($req['created_at']) ?></td>
                                <td><?= e(REQUEST_CATEGORY[$req['category']]) ?></td>
                                <td class="request-detail"><?= e($req['detail']) ?></td>
                                <td>
                                    <?php if ($req['status'] === 'done'): ?>
                                        <span class="pill pill-free">เสร็จแล้ว</span>
                                        <span class="muted"><?= thai_date($req['completed_at']) ?></span>
                                    <?php else: ?>
                                        <span class="pill pill-overdue">รอดำเนินการ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">ยังไม่เคยแจ้งซ่อม</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
