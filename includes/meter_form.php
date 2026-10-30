<?php
/**
 * ไฟล์: includes/meter_form.php
 * ฟอร์มจดมิเตอร์ ใช้ร่วมกันระหว่าง bill_create.php และ bill_edit.php
 * ตัวแปรที่ต้องมีก่อน include:
 *   $form        ค่าในฟอร์ม: elec_prev, elec_curr, water_prev, water_curr
 *   $errors      ข้อความ error รายช่อง
 *   $askPrev     ['elec' => bool, 'water' => bool]  true = ไม่มีเลขเดือนก่อนในระบบ ต้องกรอกเอง
 *   $isFlat      ห้องคิดค่าน้ำแบบเหมาจ่ายหรือไม่
 *   $rent, $elecRate, $waterRate   ค่าเช่าและอัตราที่ใช้คำนวณ
 *   $submitLabel, $cancelUrl
 */
?>
<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">บันทึกไม่ได้ แก้ไขช่องที่มีข้อความสีแดงก่อน</div>
<?php endif; ?>

<form class="form panel form-narrow" method="post" novalidate id="meter-form"
      data-rent="<?= e($rent) ?>" data-elec-rate="<?= e($elecRate) ?>" data-water-rate="<?= e($waterRate) ?>" data-flat="<?= $isFlat ? '1' : '0' ?>">
    <?= csrf_field() ?>

    <fieldset class="field">
        <legend>มิเตอร์ไฟ (<?= $elecRate ?> บาท/หน่วย)</legend>
        <div class="field-row">
            <div class="field">
                <label for="elec_prev">เลขเดือนก่อน</label>
                <?php if ($askPrev['elec']): ?>
                    <input id="elec_prev" name="elec_prev" inputmode="numeric" value="<?= e($form['elec_prev']) ?>" required<?= invalid_attr($errors, 'elec_prev') ?>>
                    <span class="hint">ยังไม่มีบิลเดือนก่อนในระบบ กรอกเลขตอนผู้เช่าเข้าพัก</span>
                    <?= field_error($errors, 'elec_prev') ?>
                <?php else: ?>
                    <input id="elec_prev" value="<?= e(number_format((int) $form['elec_prev'])) ?>" readonly>
                    <span class="hint">จากบิลเดือนก่อน</span>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="elec_curr">เลขเดือนนี้</label>
                <input id="elec_curr" name="elec_curr" inputmode="numeric" value="<?= e($form['elec_curr']) ?>" required autofocus<?= invalid_attr($errors, 'elec_curr') ?>>
                <?= field_error($errors, 'elec_curr') ?>
            </div>
        </div>
    </fieldset>

    <?php if ($isFlat): ?>
        <p class="hint">ห้องนี้ค่าน้ำเหมาจ่าย <?= baht($waterRate) ?> ต่อเดือน ไม่ต้องจดมิเตอร์น้ำ</p>
    <?php else: ?>
        <fieldset class="field">
            <legend>มิเตอร์น้ำ (<?= $waterRate ?> บาท/หน่วย)</legend>
            <div class="field-row">
                <div class="field">
                    <label for="water_prev">เลขเดือนก่อน</label>
                    <?php if ($askPrev['water']): ?>
                        <input id="water_prev" name="water_prev" inputmode="numeric" value="<?= e($form['water_prev']) ?>" required<?= invalid_attr($errors, 'water_prev') ?>>
                        <span class="hint">ยังไม่มีบิลเดือนก่อนในระบบ กรอกเลขตอนผู้เช่าเข้าพัก</span>
                        <?= field_error($errors, 'water_prev') ?>
                    <?php else: ?>
                        <input id="water_prev" value="<?= e(number_format((int) $form['water_prev'])) ?>" readonly>
                        <span class="hint">จากบิลเดือนก่อน</span>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label for="water_curr">เลขเดือนนี้</label>
                    <input id="water_curr" name="water_curr" inputmode="numeric" value="<?= e($form['water_curr']) ?>" required<?= invalid_attr($errors, 'water_curr') ?>>
                    <?= field_error($errors, 'water_curr') ?>
                </div>
            </div>
        </fieldset>
    <?php endif; ?>

    <!-- สรุปยอด: JavaScript คำนวณให้ดูทันทีที่พิมพ์ แต่ยอดที่บันทึกจริงคำนวณใหม่ด้วย PHP ฝั่งเซิร์ฟเวอร์ -->
    <div class="bill" id="bill-preview" aria-live="polite">
        <div class="bill-row"><span>ค่าเช่า</span><span><?= baht($rent) ?></span></div>
        <div class="bill-row"><span id="pv-elec-label">ค่าไฟ</span><span id="pv-elec">-</span></div>
        <div class="bill-row"><span id="pv-water-label">ค่าน้ำ<?= $isFlat ? ' เหมาจ่าย' : '' ?></span><span id="pv-water"><?= $isFlat ? baht($waterRate) : '-' ?></span></div>
        <div class="bill-row bill-total"><span>ยอดรวม</span><span id="pv-total">กรอกเลขมิเตอร์ให้ครบ</span></div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= e($submitLabel) ?></button>
        <a class="btn btn-ghost" href="<?= e($cancelUrl) ?>">ยกเลิก</a>
    </div>
</form>

<script>
// คำนวณยอดให้เห็นทันทีระหว่างพิมพ์ (แสดงผลเท่านั้น ยอดจริงคำนวณด้วย PHP ตอนบันทึก)
(function () {
    const f = document.getElementById('meter-form');
    const num = id => { const el = document.getElementById(id); if (!el) return null;
        const v = el.value.replace(/,/g, '').trim(); return /^\d+$/.test(v) ? Number(v) : null; };
    const baht = n => '฿' + n.toLocaleString('th-TH', { maximumFractionDigits: 2 });
    const rent = Number(f.dataset.rent), er = Number(f.dataset.elecRate), wr = Number(f.dataset.waterRate), flat = f.dataset.flat === '1';
    function update() {
        const ep = num('elec_prev'), ec = num('elec_curr');
        const eu = ep !== null && ec !== null && ec >= ep ? ec - ep : null;
        let wCost = flat ? wr : null;
        document.getElementById('pv-elec-label').textContent = 'ค่าไฟ' + (eu !== null ? ` ${eu} หน่วย × ${er}` : '');
        document.getElementById('pv-elec').textContent = eu !== null ? baht(eu * er) : '-';
        if (!flat) {
            const wp = num('water_prev'), wc = num('water_curr');
            const wu = wp !== null && wc !== null && wc >= wp ? wc - wp : null;
            wCost = wu !== null ? wu * wr : null;
            document.getElementById('pv-water-label').textContent = 'ค่าน้ำ' + (wu !== null ? ` ${wu} หน่วย × ${wr}` : '');
            document.getElementById('pv-water').textContent = wCost !== null ? baht(wCost) : '-';
        }
        document.getElementById('pv-total').textContent = eu !== null && wCost !== null ? baht(rent + eu * er + wCost) : 'กรอกเลขมิเตอร์ให้ครบ';
    }
    f.addEventListener('input', update);
    update();
})();
</script>
