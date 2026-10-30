<?php
/**
 * ไฟล์: includes/invoice.php
 * ใบแจ้งค่าเช่า 1 ใบ ใช้ร่วมกันระหว่าง bill_view.php (เจ้าของหอ) และ my_bill.php (ผู้เช่า)
 * ตัวแปรที่ต้องมีก่อน include:
 *   $bill   แถวบิลจากฐานข้อมูล + room_number, full_name, phone, created_by_email
 *   $b      ผลจาก bill_breakdown()
 *   $month  เดือนของบิล "YYYY-MM-01"
 */
?>
<section class="panel invoice">
    <div class="invoice-head">
        <div>
            <h2 class="h-sm">ใบแจ้งค่าเช่า เดือน <?= thai_date($month, true) ?></h2>
            <p class="muted"><?= e(DORM_NAME) ?></p>
        </div>
        <dl class="facts">
            <dt>ห้อง</dt><dd><?= e($bill['room_number']) ?></dd>
            <dt>ผู้เช่า</dt><dd><?= e($bill['full_name']) ?></dd>
            <dt>โทร</dt><dd><?= e($bill['phone']) ?></dd>
        </dl>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>รายการ</th><th class="right">เลขก่อน</th><th class="right">เลขหลัง</th><th class="right">หน่วย</th><th class="right">อัตรา</th><th class="right">จำนวนเงิน</th></tr></thead>
            <tbody>
                <tr><td>ค่าเช่าห้อง</td><td></td><td></td><td></td><td></td><td class="right num"><?= baht($b['rent']) ?></td></tr>
                <tr>
                    <td>ค่าไฟ</td>
                    <td class="right num"><?= number_format($bill['elec_prev']) ?></td>
                    <td class="right num"><?= number_format($bill['elec_curr']) ?></td>
                    <td class="right num"><?= number_format($b['elec_units']) ?></td>
                    <td class="right num"><?= baht($bill['elec_rate']) ?></td>
                    <td class="right num"><?= baht($b['elec_cost']) ?></td>
                </tr>
                <tr>
                    <td>ค่าน้ำ<?= $b['water_flat'] ? ' (เหมาจ่าย)' : '' ?></td>
                    <?php if ($b['water_flat']): ?>
                        <td></td><td></td><td></td><td></td>
                    <?php else: ?>
                        <td class="right num"><?= number_format($bill['water_prev']) ?></td>
                        <td class="right num"><?= number_format($bill['water_curr']) ?></td>
                        <td class="right num"><?= number_format($b['water_units']) ?></td>
                        <td class="right num"><?= baht($bill['water_rate']) ?></td>
                    <?php endif; ?>
                    <td class="right num"><?= baht($b['water_cost']) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr><th colspan="5">ยอดรวม</th><th class="right num invoice-total"><?= baht($b['total']) ?></th></tr>
            </tfoot>
        </table>
    </div>
    <p class="muted" style="font-size:var(--fs-xs)">ออกบิลโดย <?= e($bill['created_by_email']) ?> เมื่อ <?= thai_date($bill['created_at']) ?></p>
</section>
