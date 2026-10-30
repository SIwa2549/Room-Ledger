<?php
/**
 * ไฟล์: requests.php  (เจ้าของหอ: รายการแจ้งซ่อม)
 * ดูรายการแจ้งซ่อมจากผู้เช่าทุกห้อง กรองได้ว่า "รอดำเนินการ / เสร็จแล้ว / ทั้งหมด"
 * กดปุ่ม "เสร็จแล้ว" เพื่อปิดงาน (ส่งไปที่ request_action.php)
 */
require_once __DIR__ . '/includes/auth.php';

$show = (string) ($_GET['show'] ?? 'pending');
if (!in_array($show, ['pending', 'done', 'all'], true)) $show = 'pending';

// ---------- จำนวนในแต่ละกลุ่ม (สำหรับชิปกรอง) ----------
$stmt = $conn->prepare("SELECT COUNT(*) AS all_count,
                               COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count
                        FROM project_requests");
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
$counts = ['pending' => (int) $c['pending_count'], 'done' => (int) $c['all_count'] - (int) $c['pending_count'], 'all' => (int) $c['all_count']];

// ---------- รายการแจ้งซ่อม + ห้อง + ผู้เช่า (รอดำเนินการ: เก่าสุดก่อน, เสร็จแล้ว: ล่าสุดก่อน) ----------
$sql = "SELECT q.id, q.category, q.detail, q.status, q.created_at, q.completed_at,
               t.full_name, t.phone, r.room_number, u.email AS completed_by_email
        FROM project_requests q
        JOIN project_tenants t ON t.id = q.tenant_id
        JOIN project_rooms r   ON r.id = t.room_id
        LEFT JOIN project_users u ON u.id = q.completed_by
        WHERE ? = 'all' OR q.status = ?
        ORDER BY q.status = 'done',
                 CASE WHEN q.status = 'pending' THEN q.created_at END ASC,
                 q.completed_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $show, $show);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$labels = ['pending' => 'รอดำเนินการ', 'done' => 'เสร็จแล้ว', 'all' => 'ทั้งหมด'];

$pageTitle = 'แจ้งซ่อม';
$activeNav = 'requests';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div>
        <h1>แจ้งซ่อม</h1>
        <p>รอดำเนินการ <?= $counts['pending'] ?> รายการ</p>
    </div>
</header>

<nav class="chips" aria-label="กรองรายการแจ้งซ่อม">
    <?php foreach ($labels as $key => $label): ?>
        <a class="chip" href="requests.php<?= $key === 'pending' ? '' : '?show=' . $key ?>" aria-current="<?= $show === $key ? 'true' : 'false' ?>">
            <?= $label ?> <span class="count"><?= $counts[$key] ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$requests): ?>
    <div class="empty"><p><?= $show === 'pending' ? 'ไม่มีงานค้าง ทุกรายการดำเนินการเสร็จแล้ว' : 'ยังไม่มีรายการในกลุ่มนี้' ?></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>วันที่แจ้ง</th><th>ห้อง</th><th>ผู้เช่า</th><th>ประเภท</th><th>รายละเอียด</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?= thai_date($req['created_at']) ?></td>
                        <td class="num"><b><?= e($req['room_number']) ?></b></td>
                        <td><?= e($req['full_name']) ?><br><span class="muted"><?= e($req['phone']) ?></span></td>
                        <td><?= e(REQUEST_CATEGORY[$req['category']]) ?></td>
                        <td class="request-detail"><?= e($req['detail']) ?></td>
                        <td>
                            <?php if ($req['status'] === 'done'): ?>
                                <span class="pill pill-free">เสร็จแล้ว</span>
                                <br><span class="muted"><?= thai_date($req['completed_at']) ?><?= $req['completed_by_email'] ? ' · ' . e($req['completed_by_email']) : '' ?></span>
                            <?php else: ?>
                                <span class="pill pill-overdue">รอดำเนินการ</span>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <form method="post" action="request_action.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $req['id'] ?>">
                                <input type="hidden" name="show" value="<?= e($show) ?>">
                                <?php if ($req['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-primary" type="submit" name="action" value="done">เสร็จแล้ว</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" name="action" value="pending">เปิดงานอีกครั้ง</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
