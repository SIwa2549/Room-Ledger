<?php
/**
 * ไฟล์: rooms.php  (CRUD: READ)
 * แสดงห้องทั้งหมดแบบ "ผังอาคาร" หรือ "รายการ" พร้อมค้นหาและกรองตามสถานะ
 * พารามิเตอร์ (GET): q = คำค้น, status = all|free|rented|overdue|repair, view = grid|list
 */
require_once __DIR__ . '/includes/auth.php';

// ---------- 1) รับค่าตัวกรอง และจำกัดให้อยู่ในค่าที่อนุญาตเท่านั้น ----------
$q      = trim((string) ($_GET['q'] ?? ''));
$filter = (string) ($_GET['status'] ?? 'all');
$view   = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
if ($filter !== 'all' && !array_key_exists($filter, VIEW_STATUS_LABEL)) {
    $filter = 'all';
}

// ---------- 2) ดึงข้อมูลห้อง + ผู้เช่าปัจจุบัน + ยอดค้างชำระ ด้วย Prepared Statement ----------
// LEFT JOIN: ห้องว่างไม่มีผู้เช่า ก็ยังต้องแสดง
// ยอดค้าง = ผลรวมบิลที่ยังไม่ชำระของ "เดือนก่อน ๆ" (บิลเดือนนี้ยังอยู่ในช่วงรอชำระ ไม่นับเป็นค้าง)
$sql = "SELECT r.id, r.room_number, r.price, r.status, r.water_type,
               t.full_name,
               COALESCE((SELECT SUM(b.total_amount) FROM project_bills b
                         WHERE b.room_id = r.id AND b.status = 'unpaid' AND b.billing_month < ?), 0) AS unpaid
        FROM project_rooms r
        LEFT JOIN project_tenants t ON t.room_id = r.id AND t.move_out_date IS NULL
        WHERE (? = '' OR r.room_number LIKE ? OR t.full_name LIKE ?)
        ORDER BY r.room_number";
$like = '%' . $q . '%';
$stmt = $conn->prepare($sql);
$thisMonth = current_month();
$stmt->bind_param('ssss', $thisMonth, $q, $like, $like);
$stmt->execute();
$allRooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- 3) คำนวณสถานะที่ใช้แสดง และนับจำนวนแต่ละสถานะ ----------
$counts = ['all' => 0, 'free' => 0, 'rented' => 0, 'overdue' => 0, 'repair' => 0];
foreach ($allRooms as &$room) {
    $room['view_status'] = room_view_status($room['status'], (float) $room['unpaid']);
    $counts['all']++;
    $counts[$room['view_status']]++;
}
unset($room);

$rooms = $filter === 'all'
    ? $allRooms
    : array_values(array_filter($allRooms, fn ($r) => $r['view_status'] === $filter));

// จัดกลุ่มตามชั้น ชั้นบนสุดอยู่ด้านบน (เหมือนมองหน้าตึก)
$floors = [];
foreach ($rooms as $room) {
    $floors[room_floor($room['room_number'])][] = $room;
}
krsort($floors);

/** สร้าง URL ของหน้านี้ โดยเปลี่ยนเฉพาะพารามิเตอร์ที่ระบุ */
function rooms_url(array $change): string
{
    global $q, $filter, $view;
    $params = array_merge(['q' => $q, 'status' => $filter, 'view' => $view], $change);
    $params = array_filter($params, fn ($v, $k) => !($k === 'q' && $v === '') && !($k === 'status' && $v === 'all') && !($k === 'view' && $v === 'grid'), ARRAY_FILTER_USE_BOTH);
    return 'rooms.php' . ($params ? '?' . http_build_query($params) : '');
}

$pageTitle = 'ห้องพัก';
$activeNav = 'rooms';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div>
        <h1>ห้องพัก</h1>
        <p><?= $counts['all'] ?> ห้อง<?= $q !== '' ? ' ที่ตรงกับ "' . e($q) . '"' : '' ?></p>
    </div>
    <a class="btn btn-primary" href="room_create.php">เพิ่มห้อง</a>
</header>

<!-- ค้นหา (ส่งแบบ GET เพื่อให้ URL แชร์ต่อได้) -->
<form class="toolbar" method="get" action="rooms.php" role="search">
    <label class="search">
        <span class="visually-hidden">ค้นหาเลขห้องหรือชื่อผู้เช่า</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="room-search" type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาเลขห้องหรือชื่อผู้เช่า" maxlength="100" autocomplete="off">
    </label>
    <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <?php if ($view === 'list'): ?><input type="hidden" name="view" value="list"><?php endif; ?>
    <button class="btn" type="submit">ค้นหา</button>
    <div class="segmented" role="group" aria-label="รูปแบบการแสดง">
        <a class="btn-seg" href="<?= e(rooms_url(['view' => 'grid'])) ?>" aria-current="<?= $view === 'grid' ? 'true' : 'false' ?>">ผังอาคาร</a>
        <a class="btn-seg" href="<?= e(rooms_url(['view' => 'list'])) ?>" aria-current="<?= $view === 'list' ? 'true' : 'false' ?>">รายการ</a>
    </div>
</form>

<!-- กรองตามสถานะ -->
<nav class="chips" aria-label="กรองตามสถานะ">
    <?php foreach (['all' => 'ทั้งหมด'] + VIEW_STATUS_LABEL as $key => $label): ?>
        <a class="chip" href="<?= e(rooms_url(['status' => $key])) ?>" aria-current="<?= $filter === $key ? 'true' : 'false' ?>">
            <?= e($label) ?> <span class="count"><?= $counts[$key] ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$rooms): ?>
    <div class="empty">
        <?php if ($counts['all'] === 0 && $q === ''): ?>
            <p>ยังไม่มีห้องในระบบ เริ่มจากเพิ่มห้องแรก</p>
            <a class="btn btn-sm btn-primary" href="room_create.php">เพิ่มห้อง</a>
        <?php else: ?>
            <p>ไม่พบห้องที่ตรงกับตัวกรอง</p>
            <a class="btn btn-sm" href="rooms.php">ล้างตัวกรอง</a>
        <?php endif; ?>
    </div>

<?php elseif ($view === 'grid'): ?>
    <!-- มุมมองผังอาคาร -->
    <?php require __DIR__ . '/includes/building.php'; ?>

<?php else: ?>
    <!-- มุมมองรายการ (ตาราง) -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ห้อง</th><th>สถานะ</th><th>ผู้เช่า</th><th class="right">ค่าเช่า/เดือน</th><th>ค่าน้ำ</th><th class="right">ยอดค้าง</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td class="num"><b><?= e($room['room_number']) ?></b></td>
                        <td><span class="pill pill-<?= $room['view_status'] ?>"><?= VIEW_STATUS_LABEL[$room['view_status']] ?></span></td>
                        <td><?= $room['full_name'] ? e($room['full_name']) : '<span class="muted">-</span>' ?></td>
                        <td class="right num"><?= baht($room['price']) ?></td>
                        <td><?= WATER_TYPE[$room['water_type']] ?></td>
                        <td class="right num"><?= (float) $room['unpaid'] > 0 ? '<b style="color:var(--overdue)">' . baht($room['unpaid']) . '</b>' : '-' ?></td>
                        <td class="right"><a class="btn btn-sm" href="room_view.php?id=<?= (int) $room['id'] ?>">ดูรายละเอียด</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
