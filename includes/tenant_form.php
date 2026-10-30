<?php
/**
 * ไฟล์: includes/tenant_form.php
 * ฟอร์มข้อมูลผู้เช่า ใช้ร่วมกันระหว่าง tenant_create.php และ tenant_edit.php
 * ตัวแปรที่ต้องมีก่อน include:
 *   $data        ค่าในฟอร์ม (room_id, full_name, phone, move_in_date)
 *   $errors      ข้อความ error รายช่อง
 *   $roomChoices รายการห้องว่างให้เลือก หรือ null ถ้าไม่ให้เลือกห้อง (ตอนแก้ไข)
 *   $submitLabel ข้อความบนปุ่ม   $cancelUrl ปุ่มยกเลิกกลับไปหน้าไหน
 */
?>
<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">บันทึกไม่ได้ แก้ไขช่องที่มีข้อความสีแดงก่อน</div>
<?php endif; ?>

<form class="form panel form-narrow" method="post" novalidate>
    <?= csrf_field() ?>

    <?php if ($roomChoices !== null): ?>
        <div class="field">
            <label for="room_id">ห้อง</label>
            <select id="room_id" name="room_id" required<?= invalid_attr($errors, 'room_id') ?>>
                <option value="">เลือกห้องที่ว่าง</option>
                <?php foreach ($roomChoices as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= (int) $data['room_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                        ห้อง <?= e($r['room_number']) ?> · <?= baht($r['price']) ?>/เดือน · น้ำ<?= WATER_TYPE[$r['water_type']] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint">แสดงเฉพาะห้องที่สถานะว่าง เมื่อบันทึกแล้วห้องจะเปลี่ยนเป็นมีผู้เช่า</span>
            <?= field_error($errors, 'room_id') ?>
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="full_name">ชื่อ-นามสกุล</label>
        <input id="full_name" name="full_name" value="<?= e($data['full_name']) ?>" maxlength="100" required autocomplete="off"<?= invalid_attr($errors, 'full_name') ?>>
        <?= field_error($errors, 'full_name') ?>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="phone">เบอร์โทร</label>
            <input id="phone" name="phone" value="<?= e($data['phone']) ?>" inputmode="tel" maxlength="15" placeholder="081-234-5678" required<?= invalid_attr($errors, 'phone') ?>>
            <?= field_error($errors, 'phone') ?>
        </div>
        <div class="field">
            <label for="move_in_date">วันที่เข้าพัก</label>
            <input id="move_in_date" name="move_in_date" type="date" value="<?= e($data['move_in_date']) ?>" required<?= invalid_attr($errors, 'move_in_date') ?>>
            <?= field_error($errors, 'move_in_date') ?>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= e($submitLabel) ?></button>
        <a class="btn btn-ghost" href="<?= e($cancelUrl) ?>">ยกเลิก</a>
    </div>
</form>
