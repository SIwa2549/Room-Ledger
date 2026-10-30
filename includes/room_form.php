<?php
/**
 * ไฟล์: includes/room_form.php
 * ฟอร์มข้อมูลห้อง ใช้ร่วมกันระหว่าง room_create.php และ room_edit.php
 * ตัวแปรที่ต้องมีก่อน include:
 *   $data        ค่าในฟอร์ม (room_number, price, water_type, status)
 *   $errors      ข้อความ error รายช่อง
 *   $hasTenant   ห้องมีผู้เช่าอยู่หรือไม่ (true = ล็อกสถานะไว้ที่ "มีผู้เช่า")
 *   $submitLabel ข้อความบนปุ่มบันทึก
 *   $cancelUrl   ปุ่มยกเลิกกลับไปหน้าไหน
 */

// field_error() และ invalid_attr() อยู่ใน includes/functions.php
?>
<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">บันทึกไม่ได้ แก้ไขช่องที่มีข้อความสีแดงก่อน</div>
<?php endif; ?>

<form class="form panel form-narrow" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="field-row">
        <div class="field">
            <label for="room_number">เลขห้อง</label>
            <input id="room_number" name="room_number" value="<?= e($data['room_number']) ?>" maxlength="10" required autocomplete="off"<?= invalid_attr($errors, 'room_number') ?>>
            <span class="hint">เช่น 107 หรือ A-201 หลักร้อยคือชั้น (107 = ชั้น 1)</span>
            <?= field_error($errors, 'room_number') ?>
        </div>
        <div class="field">
            <label for="price">ค่าเช่าต่อเดือน (บาท)</label>
            <input id="price" name="price" value="<?= e($data['price']) ?>" inputmode="decimal" required<?= invalid_attr($errors, 'price') ?>>
            <?= field_error($errors, 'price') ?>
        </div>
    </div>

    <fieldset class="field">
        <legend>วิธีคิดค่าน้ำ</legend>
        <div class="choice-row">
            <?php foreach (WATER_TYPE as $value => $label): ?>
                <label class="choice">
                    <input type="radio" name="water_type" value="<?= $value ?>" <?= $data['water_type'] === $value ? 'checked' : '' ?>>
                    <span><?= $label ?><small><?= $value === 'meter' ? 'คิดตามหน่วยที่ใช้แต่ละเดือน' : 'คิดยอดเท่ากันทุกเดือน' ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'water_type') ?>
    </fieldset>

    <fieldset class="field">
        <legend>สถานะห้อง</legend>
        <?php if ($hasTenant): ?>
            <p class="hint">มีผู้เช่า: ห้องนี้มีผู้เช่าอยู่ สถานะจะเปลี่ยนได้เมื่อผู้เช่าย้ายออก</p>
        <?php else: ?>
            <div class="choice-row">
                <?php foreach (['available', 'maintenance'] as $value): ?>
                    <label class="choice">
                        <input type="radio" name="status" value="<?= $value ?>" <?= $data['status'] === $value ? 'checked' : '' ?>>
                        <span><?= ROOM_STATUS[$value] ?><small><?= $value === 'available' ? 'พร้อมให้เช่า' : 'ปิดชั่วคราว ยังให้เช่าไม่ได้' ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= field_error($errors, 'status') ?>
        <?php endif; ?>
    </fieldset>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= e($submitLabel) ?></button>
        <a class="btn btn-ghost" href="<?= e($cancelUrl) ?>">ยกเลิก</a>
    </div>
</form>
