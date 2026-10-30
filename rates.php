<?php
/**
 * ไฟล์: rates.php  (ตั้งค่าอัตราค่าบริการ)
 * แก้อัตราค่าไฟ ค่าน้ำตามมิเตอร์ และค่าน้ำเหมาจ่าย เก็บในตาราง project_settings (แถว id = 1)
 * บิลที่ออกหลังบันทึกจะใช้อัตราใหม่ บิลที่ออกไปแล้วยังใช้อัตราเดิมที่เก็บไว้ในบิล
 */
require_once __DIR__ . '/includes/auth.php';   // เจ้าของหอเท่านั้น

$rates = current_rates($conn);

// ช่องในฟอร์ม: ชื่อคอลัมน์ => [ข้อความ, หน่วย, ค่าต่ำสุด, ค่าสูงสุด, คำอธิบาย]
$fields = [
    'elec_rate'       => ['ค่าไฟ', 'บาท/หน่วย', 0.01, 50, 'การไฟฟ้าส่วนภูมิภาคคิดประมาณ 4-5 บาท หอพักส่วนใหญ่คิด 7-9 บาท'],
    'water_rate'      => ['ค่าน้ำตามมิเตอร์', 'บาท/หน่วย', 0.01, 100, 'ใช้กับห้องที่ตั้งค่าน้ำแบบ "ตามมิเตอร์"'],
    'water_flat_rate' => ['ค่าน้ำเหมาจ่าย', 'บาท/เดือน', 0, 5000, 'ใช้กับห้องที่ตั้งค่าน้ำแบบ "เหมาจ่าย"'],
];

$form = [];
foreach ($fields as $key => $_) $form[$key] = rate_text($rates[$key]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $values = [];
    foreach ($fields as $key => [$label, $unit, $min, $max]) {
        $raw = trim(str_replace(',', '', (string) ($_POST[$key] ?? '')));
        $form[$key] = $raw;
        if ($raw === '') {
            $errors[$key] = 'กรอก' . $label;
        } elseif (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $raw)) {
            $errors[$key] = 'กรอกเป็นตัวเลข ทศนิยมไม่เกิน 2 ตำแหน่ง';
        } elseif ((float) $raw < $min || (float) $raw > $max) {
            $errors[$key] = $label . 'ต้องอยู่ระหว่าง ' . rate_text($min) . '-' . rate_text($max) . ' ' . $unit;
        } else {
            $values[$key] = (float) $raw;
        }
    }

    if (!$errors) {
        $userId = (int) $currentUser['id'];
        if ($rates['missing']) {
            $sql = 'INSERT INTO project_settings (elec_rate, water_rate, water_flat_rate, updated_by, id) VALUES (?, ?, ?, ?, 1)';
        } else {
            $sql = 'UPDATE project_settings SET elec_rate = ?, water_rate = ?, water_flat_rate = ?, updated_by = ? WHERE id = 1';
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('dddi', $values['elec_rate'], $values['water_rate'], $values['water_flat_rate'], $userId);
        $stmt->execute();
        $stmt->close();

        flash('success', 'บันทึกอัตราใหม่แล้ว: ไฟ ' . rate_text($values['elec_rate']) . ' บาท/หน่วย, น้ำ '
            . rate_text($values['water_rate']) . ' บาท/หน่วย, น้ำเหมาจ่าย ' . baht($values['water_flat_rate']) . '/เดือน');
        redirect('rates.php');
    }
}

$pageTitle = 'อัตราค่าบริการ';
$activeNav = 'rates';
require __DIR__ . '/includes/header.php';
?>

<header class="page-head">
    <div>
        <h1>อัตราค่าบริการ</h1>
        <p>ใช้คำนวณบิลที่ออกหลังจากนี้ บิลที่ออกไปแล้วยังคงยอดเดิม</p>
    </div>
</header>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">บันทึกไม่ได้ แก้ไขช่องที่มีข้อความสีแดงก่อน</div>
<?php endif; ?>

<form class="form panel form-narrow" method="post" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => [$label, $unit, $min, $max, $hint]): ?>
        <div class="field">
            <label for="<?= $key ?>"><?= $label ?> (<?= $unit ?>)</label>
            <input id="<?= $key ?>" name="<?= $key ?>" value="<?= e($form[$key]) ?>" inputmode="decimal" required<?= invalid_attr($errors, $key) ?>>
            <span class="hint"><?= e($hint) ?></span>
            <?= field_error($errors, $key) ?>
        </div>
    <?php endforeach; ?>
    <div class="actions">
        <button class="btn btn-primary" type="submit">บันทึกอัตรา</button>
    </div>
    <?php if ($rates['updated_at']): ?>
        <p class="hint">แก้ไขล่าสุด <?= thai_date($rates['updated_at']) ?><?= $rates['updated_by_email'] ? ' โดย ' . e($rates['updated_by_email']) : '' ?></p>
    <?php endif; ?>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
