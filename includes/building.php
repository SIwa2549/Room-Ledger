<?php
/**
 * ไฟล์: includes/building.php
 * วาด "ผังอาคาร": ห้องเรียงตามชั้น ชั้นบนสุดอยู่ด้านบน ใช้ใน rooms.php และ dashboard.php
 * ตัวแปรที่ต้องมีก่อน include:
 *   $floors  [เลขชั้น => [ห้อง, ...]] เรียงชั้นจากมากไปน้อย
 *            แต่ละห้องต้องมี id, room_number, full_name, unpaid, view_status
 */
?>
<section class="building" aria-label="ผังห้องตามชั้น">
    <?php foreach ($floors as $floor => $floorRooms): ?>
        <div class="floor">
            <span class="floor-label"><?= $floor > 0 ? 'ชั้น ' . $floor : 'อื่น ๆ' ?></span>
            <?php foreach ($floorRooms as $room):
                $st = $room['view_status'];
                $meta = match ($st) {
                    'free'    => 'ว่าง พร้อมเข้าอยู่',
                    'rented'  => $room['full_name'] ?? 'มีผู้เช่า',
                    'overdue' => 'ค้าง ' . baht($room['unpaid']),
                    'repair'  => 'ซ่อมบำรุง',
                };
            ?>
                <a class="room room--<?= $st ?>" href="room_view.php?id=<?= (int) $room['id'] ?>" aria-label="ห้อง <?= e($room['room_number']) ?> <?= VIEW_STATUS_LABEL[$st] ?>">
                    <span class="room-no"><?= e($room['room_number']) ?></span>
                    <span class="room-meta"><?= e($meta) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <div class="ground" aria-hidden="true"></div>
</section>
