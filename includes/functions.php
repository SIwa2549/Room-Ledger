<?php
/**
 * ไฟล์: includes/functions.php
 * ฟังก์ชันช่วยที่ทุกหน้าใช้ร่วมกัน: แสดงผลอย่างปลอดภัย, CSRF, Flash message, Redirect
 */

// ข้อความภาษาไทยของค่าใน ENUM ของฐานข้อมูล
const ROOM_STATUS = [
    'available'   => 'ว่าง',
    'occupied'    => 'มีผู้เช่า',
    'maintenance' => 'ซ่อมบำรุง',
];
const WATER_TYPE = [
    'meter'     => 'ตามมิเตอร์',
    'flat_rate' => 'เหมาจ่าย',
];

/** แสดงข้อความลง HTML อย่างปลอดภัย (ป้องกัน XSS) ใช้ทุกครั้งที่ echo ข้อมูลจากผู้ใช้หรือฐานข้อมูล */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** แสดงจำนวนเงิน เช่น 3500 -> ฿3,500 และ 3500.5 -> ฿3,500.50 */
function baht($amount): string
{
    $amount = (float) $amount;
    $decimals = floor($amount) == $amount ? 0 : 2;
    return '฿' . number_format($amount, $decimals);
}

/** ชั้นของห้อง = หลักร้อยของเลขห้อง เช่น "204" -> 2 (เลขห้องที่มีตัวอักษรจะคืน 0) */
function room_floor(string $roomNumber): int
{
    return ctype_digit($roomNumber) ? intdiv((int) $roomNumber, 100) : 0;
}

/** วันที่ภาษาไทยแบบย่อ เช่น "2026-09-01" -> "1 ก.ย. 2569", เลือกแสดงแค่เดือนได้ */
function thai_date(string $isoDate, bool $monthOnly = false): string
{
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    [$y, $m, $d] = array_map('intval', explode('-', substr($isoDate, 0, 10)));
    $text = $months[$m - 1] . ' ' . ($y + 543);
    return $monthOnly ? $text : $d . ' ' . $text;
}

/** Redirect แล้วหยุดการทำงานทันที */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------------- CSRF Token ----------------
// ฟอร์มที่เปลี่ยนข้อมูล (เพิ่ม/แก้ไข/ลบ) ต้องแนบ Token นี้ เพื่อกันเว็บอื่นแอบส่งฟอร์มแทนเรา

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** ใส่ใน <form> ทุกอันที่ใช้ method="post" */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** เรียกเป็นบรรทัดแรกของการประมวลผล POST */
function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('ฟอร์มหมดอายุหรือไม่ถูกต้อง กรุณากลับไปหน้าก่อนหน้า รีเฟรช แล้วลองอีกครั้ง');
    }
}

// ---------------- Flash message ----------------
// ข้อความแจ้งผลที่แสดงครั้งเดียวหลัง Redirect เช่น "เพิ่มห้อง 107 แล้ว"

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];  // type: success | error
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---------------- ข้อมูลห้อง ----------------

/**
 * สถานะที่ใช้แสดงผล: ค้างชำระ (overdue) เป็นกรณีย่อยของ "มีผู้เช่า"
 * คืนค่า: free | rented | overdue | repair (ตรงกับคลาส CSS room--xxx และ pill-xxx)
 */
function room_view_status(string $status, float $unpaid): string
{
    if ($status === 'maintenance') return 'repair';
    if ($status === 'available')   return 'free';
    return $unpaid > 0 ? 'overdue' : 'rented';
}

const VIEW_STATUS_LABEL = [
    'free'    => 'ว่าง',
    'rented'  => 'มีผู้เช่า',
    'overdue' => 'ค้างชำระ',
    'repair'  => 'ซ่อมบำรุง',
];

/**
 * ตรวจข้อมูลฟอร์มห้อง คืนค่า [ข้อมูลที่ผ่านการตรวจ, รายการ error]
 * $hasTenant = ห้องนี้มีผู้เช่าอยู่ตอนนี้หรือไม่ (ใช้ตอนแก้ไข)
 */
function validate_room(array $input, bool $hasTenant = false): array
{
    $data = [
        'room_number' => strtoupper(trim((string) ($input['room_number'] ?? ''))),
        'price'       => trim(str_replace(',', '', (string) ($input['price'] ?? ''))),
        'water_type'  => (string) ($input['water_type'] ?? ''),
        'status'      => (string) ($input['status'] ?? ''),
    ];
    $errors = [];

    if ($data['room_number'] === '') {
        $errors['room_number'] = 'กรอกเลขห้อง';
    } elseif (!preg_match('/^[0-9A-Z-]{1,10}$/', $data['room_number'])) {
        $errors['room_number'] = 'ใช้ได้เฉพาะตัวเลข ตัวอักษรอังกฤษ และขีด (-) ไม่เกิน 10 ตัว';
    }

    if ($data['price'] === '') {
        $errors['price'] = 'กรอกค่าเช่าต่อเดือน';
    } elseif (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $data['price'])) {
        $errors['price'] = 'กรอกเป็นตัวเลข ทศนิยมไม่เกิน 2 ตำแหน่ง';
    } elseif ((float) $data['price'] <= 0 || (float) $data['price'] > 100000) {
        $errors['price'] = 'ค่าเช่าต้องมากกว่า 0 และไม่เกิน 100,000 บาท';
    }

    if (!array_key_exists($data['water_type'], WATER_TYPE)) {
        $errors['water_type'] = 'เลือกวิธีคิดค่าน้ำ';
    }

    if ($hasTenant) {
        // ห้องที่มีผู้เช่าอยู่ สถานะต้องเป็น "มีผู้เช่า" เสมอ จะเปลี่ยนได้เมื่อผู้เช่าย้ายออก
        $data['status'] = 'occupied';
    } elseif (!in_array($data['status'], ['available', 'maintenance'], true)) {
        $errors['status'] = 'เลือกสถานะ "ว่าง" หรือ "ซ่อมบำรุง" (สถานะมีผู้เช่าจะตั้งให้อัตโนมัติเมื่อเพิ่มผู้เช่า)';
    }

    return [$data, $errors];
}

// ---------------- ช่วยแสดง error ในฟอร์ม (ใช้ทุกฟอร์ม) ----------------

/** แสดงข้อความ error ใต้ช่องกรอก (ถ้ามี) */
function field_error(array $errors, string $name): string
{
    return isset($errors[$name])
        ? '<span class="error" id="' . $name . '-error">' . e($errors[$name]) . '</span>'
        : '';
}

/** attribute สำหรับช่องที่มี error ให้โปรแกรมอ่านหน้าจออ่าน error ได้ */
function invalid_attr(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '';
}

// ---------------- เดือนของบิล ----------------
// เก็บเดือนในฐานข้อมูลเป็นวันที่ 1 ของเดือน เช่น 2026-10-01 = บิลเดือน ต.ค. 2569

/** เดือนปัจจุบันตามเวลาประเทศไทย เช่น "2026-10-01" */
function current_month(): string
{
    return date('Y-m-01');
}

/** แปลง "2026-10" จาก URL เป็น "2026-10-01" ถ้ารูปแบบผิดคืน null */
function parse_month(?string $value): ?string
{
    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) return null;
    if (!checkdate((int) $m[2], 1, (int) $m[1]) || (int) $m[1] < 2000) return null;
    return $value . '-01';
}

/** เลื่อนเดือน เช่น shift_month("2026-10-01", -1) = "2026-09-01" */
function shift_month(string $month, int $by): string
{
    return date('Y-m-01', strtotime($month . ' ' . ($by >= 0 ? '+' : '') . $by . ' month'));
}

// ---------------- ตรวจข้อมูลผู้เช่า ----------------

/** ตรวจวันที่รูปแบบ YYYY-MM-DD ว่ามีอยู่จริง */
function valid_date(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) return false;
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * ตรวจฟอร์มผู้เช่า คืนค่า [ข้อมูลที่ผ่านการตรวจ, รายการ error]
 * $roomIds = รายการ id ห้องที่อนุญาตให้เลือก (null = ไม่ต้องเลือกห้อง เช่น ตอนแก้ไข)
 */
function validate_tenant(array $input, ?array $roomIds = null): array
{
    $digits = preg_replace('/[\s-]/', '', (string) ($input['phone'] ?? ''));
    $data = [
        'room_id'      => (int) ($input['room_id'] ?? 0),
        'full_name'    => trim(preg_replace('/\s+/u', ' ', (string) ($input['full_name'] ?? ''))),
        'phone'        => $digits,
        'move_in_date' => trim((string) ($input['move_in_date'] ?? '')),
    ];
    $errors = [];

    if ($roomIds !== null && !in_array($data['room_id'], $roomIds, true)) {
        $errors['room_id'] = 'เลือกห้องที่ว่างอยู่';
    }

    if ($data['full_name'] === '') {
        $errors['full_name'] = 'กรอกชื่อ-นามสกุล';
    } elseif (!preg_match('/^.{2,100}$/u', $data['full_name'])) {
        $errors['full_name'] = 'ชื่อต้องยาว 2-100 ตัวอักษร';
    }

    if ($digits === '') {
        $errors['phone'] = 'กรอกเบอร์โทร';
    } elseif (!preg_match('/^0\d{8,9}$/', $digits)) {
        $errors['phone'] = 'เบอร์โทรต้องขึ้นต้นด้วย 0 และมี 9-10 หลัก เช่น 081-234-5678';
    } else {
        // เก็บแบบมีขีดให้อ่านง่าย: 0812345678 -> 081-234-5678
        $data['phone'] = strlen($digits) === 10
            ? substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6)
            : substr($digits, 0, 2) . '-' . substr($digits, 2, 3) . '-' . substr($digits, 5);
    }

    if (!valid_date($data['move_in_date'])) {
        $errors['move_in_date'] = 'เลือกวันที่เข้าพัก';
    } elseif ($data['move_in_date'] > date('Y-m-d', strtotime('+60 days'))) {
        $errors['move_in_date'] = 'วันเข้าพักล่วงหน้าได้ไม่เกิน 60 วัน';
    } elseif ($data['move_in_date'] < '2000-01-01') {
        $errors['move_in_date'] = 'วันที่ไม่ถูกต้อง';
    }

    return [$data, $errors];
}

// ---------------- มิเตอร์และการคำนวณบิล ----------------

/**
 * ตรวจเลขมิเตอร์ 1 ช่อง คืนค่า [ตัวเลข หรือ null, ข้อความ error หรือ null]
 * $min = เลขต่ำสุดที่ยอมรับ (เลขเดือนก่อน)
 * $checkJump = เตือนถ้าใช้ไปเกิน 5,000 หน่วย (ปิดไว้ตอนตรวจเลขเดือนก่อน เพราะไม่มีอะไรให้เทียบ)
 */
function read_meter($raw, int $min, string $label, bool $checkJump = true): array
{
    $raw = trim(str_replace(',', '', (string) $raw));
    if ($raw === '')                 return [null, 'กรอกเลขมิเตอร์' . $label];
    if (!preg_match('/^\d{1,7}$/', $raw)) return [null, 'กรอกเป็นตัวเลขจำนวนเต็มเท่านั้น'];
    $value = (int) $raw;
    if ($value < $min)               return [null, 'ต้องไม่น้อยกว่าเลขเดือนก่อน (' . number_format($min) . ')'];
    if ($checkJump && $value - $min > 5000) return [null, 'ใช้ไป ' . number_format($value - $min) . ' หน่วย มากผิดปกติ ตรวจเลขอีกครั้ง'];
    return [$value, null];
}

/**
 * คำนวณยอดบิล คืนค่าแยกเป็นส่วน ๆ เพื่อแสดงในใบแจ้งหนี้
 * ห้องเหมาจ่าย: $waterPrev / $waterCurr = null และ $waterRate = ยอดเหมาจ่ายต่อเดือน
 */
function bill_breakdown(float $rent, int $elecPrev, int $elecCurr, float $elecRate,
                        ?int $waterPrev, ?int $waterCurr, float $waterRate): array
{
    $elecUnits  = $elecCurr - $elecPrev;
    $isFlat     = $waterCurr === null;
    $waterUnits = $isFlat ? null : $waterCurr - $waterPrev;
    $elecCost   = $elecUnits * $elecRate;
    $waterCost  = $isFlat ? $waterRate : $waterUnits * $waterRate;

    return [
        'rent'        => $rent,
        'elec_units'  => $elecUnits,
        'elec_cost'   => $elecCost,
        'water_flat'  => $isFlat,
        'water_units' => $waterUnits,
        'water_cost'  => $waterCost,
        'total'       => round($rent + $elecCost + $waterCost, 2),
    ];
}

// ---------------- อัตราค่าบริการ ----------------

/**
 * อัตราปัจจุบันจากตาราง project_settings
 * คืนค่า: elec_rate, water_rate, water_flat_rate (float), updated_at, updated_by_email
 */
function current_rates($conn): array
{
    $stmt = $conn->prepare('SELECT s.elec_rate, s.water_rate, s.water_flat_rate, s.updated_at, u.email AS updated_by_email
                            FROM project_settings s LEFT JOIN project_users u ON u.id = s.updated_by
                            WHERE s.id = 1 LIMIT 1');
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return DEFAULT_RATES + ['updated_at' => null, 'updated_by_email' => null, 'missing' => true];
    }
    foreach (['elec_rate', 'water_rate', 'water_flat_rate'] as $k) $row[$k] = (float) $row[$k];
    return $row + ['missing' => false];
}

/** แสดงอัตราแบบสั้น เช่น 8 หรือ 7.50 */
function rate_text(float $rate): string
{
    return floor($rate) == $rate ? number_format($rate) : number_format($rate, 2);
}
