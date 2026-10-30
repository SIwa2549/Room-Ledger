<?php
/**
 * ไฟล์: tenants.php  (CRUD ผู้เช่า: READ)
 * รายชื่อผู้เช่า กรองได้ว่า "พักอยู่ / ย้ายออกแล้ว / ทั้งหมด" และค้นหาจากชื่อ เบอร์โทร หรือเลขห้อง
 */
require_once __DIR__ . '/includes/auth.php';

$q    = trim((string) ($_GET['q'] ?? ''));
$show = (string) ($_GET['show'] ?? 'current');
if (!in_array($show, ['current', 'past', 'all'], true)) $show = 'current';

// ---------- จำนวนในแต่ละกลุ่ม (สำหรับชิปกรอง) ----------
$stmt = $conn->prepare('SELECT COUNT(*) AS all_count,
                               COALESCE(SUM(CASE WHEN move_out_date IS NULL THEN 1 ELSE 0 END), 0) AS current_count
                        FROM project_tenants');
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
$counts = ['current' => (int) $c['current_count'], 'past' => (int) $c['all_count'] - (int) $c['current_count'], 'all' => (int) $c['all_count']];

// ---------- รายชื่อผู้เช่า + ห้อง + ยอดค้างชำระของแต่ละคน ----------
$sql = "SELECT t.id, t.full_name, t.phone, t.move_in_date, t.move_out_date,
               r.id AS room_id, r.room_number, u.email AS account_email,
               COALESCE((SELECT SUM(b.total_amount) FROM project_bills b
                         WHERE b.tenant_id = t.id AND b.status = 'unpaid'), 0) AS unpaid
        FROM project_tenants t
        JOIN project_rooms r ON r.id = t.room_id
        LEFT JOIN project_users u ON u.tenant_id = t.id
        WHERE ((? = 'current' AND t.move_out_date IS NULL)
            OR (? = 'past' AND t.move_out_date IS NOT NULL)
            OR ? = 'all')
          AND (? = '' OR t.full_name LIKE ? OR t.phone LIKE ? OR r.room_number LIKE ?)
        ORDER BY t.move_out_date IS NOT NULL, r.room_number, t.move_in_date DESC";
$like = '%' . $q . '%';
$stmt = $conn->prepare($sql);
$stmt->bind_param('sssssss', $show, $show, $show, $q, $like, $like, $like);
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$labels = ['current' => 'พักอยู่', 'past' => 'ย้ายออกแล้ว', 'all' => 'ทั้งหมด'];

$pageTitle = 'ผู้เช่า';
$activeNav = 'tenants';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div>
        <h1>ผู้เช่า</h1>
        <p>พักอยู่ <?= $counts['current'] ?> คน</p>
    </div>
    <a class="btn btn-primary" href="tenant_create.php">เพิ่มผู้เช่า</a>
</header>

<form class="toolbar" method="get" action="tenants.php" role="search">
    <label class="search">
        <span class="visually-hidden">ค้นหาชื่อ เบอร์โทร หรือเลขห้อง</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="tenant-search" type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อ เบอร์โทร หรือเลขห้อง" maxlength="100" autocomplete="off">
    </label>
    <?php if ($show !== 'current'): ?><input type="hidden" name="show" value="<?= e($show) ?>"><?php endif; ?>
    <button class="btn" type="submit">ค้นหา</button>
</form>

<nav class="chips" aria-label="กรองผู้เช่า">
    <?php foreach ($labels as $key => $label):
        $params = array_filter(['show' => $key === 'current' ? '' : $key, 'q' => $q]); ?>
        <a class="chip" href="tenants.php<?= $params ? '?' . e(http_build_query($params)) : '' ?>" aria-current="<?= $show === $key ? 'true' : 'false' ?>">
            <?= $label ?> <span class="count"><?= $counts[$key] ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$tenants): ?>
    <div class="empty">
        <?php if ($counts['all'] === 0): ?>
            <p>ยังไม่มีผู้เช่าในระบบ เพิ่มผู้เช่าคนแรกเข้าห้องที่ว่างอยู่</p>
            <a class="btn btn-sm btn-primary" href="tenant_create.php">เพิ่มผู้เช่า</a>
        <?php else: ?>
            <p>ไม่พบผู้เช่าที่ตรงกับตัวกรอง</p>
            <a class="btn btn-sm" href="tenants.php">ล้างตัวกรอง</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ชื่อ</th><th>ห้อง</th><th>โทร</th><th>เข้าพัก</th><th>สถานะ</th><th class="right">ยอดค้าง</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $t): ?>
                    <tr>
                        <td><?= e($t['full_name']) ?><?= $t['account_email'] ? ' <span class="pill pill-paid" title="' . e($t['account_email']) . '">มีบัญชี</span>' : '' ?></td>
                        <td class="num"><a href="room_view.php?id=<?= (int) $t['room_id'] ?>"><?= e($t['room_number']) ?></a></td>
                        <td class="num"><?= e($t['phone']) ?></td>
                        <td><?= thai_date($t['move_in_date']) ?></td>
                        <td>
                            <?php if ($t['move_out_date'] === null): ?>
                                <span class="pill pill-rented">พักอยู่</span>
                            <?php else: ?>
                                <span class="pill pill-paid">ย้ายออก <?= thai_date($t['move_out_date']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="right num"><?= (float) $t['unpaid'] > 0 ? '<b style="color:var(--overdue)">' . baht($t['unpaid']) . '</b>' : '-' ?></td>
                        <td class="right"><a class="btn btn-sm" href="tenant_edit.php?id=<?= (int) $t['id'] ?>">จัดการ</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
