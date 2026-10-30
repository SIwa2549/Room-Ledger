<?php
/**
 * ไฟล์: login.php
 * หน้าที่: แสดงฟอร์มเข้าสู่ระบบ และตรวจสอบอีเมล/รหัสผ่าน
 */

// เริ่มต้น Session (ต้องเรียกก่อนมี output ใด ๆ ส่งออกไปที่เบราว์เซอร์)
session_start();

// ถ้าล็อกอินอยู่แล้ว ไม่ต้องเห็นหน้า login ซ้ำ ส่งไปหน้าแรกตามบทบาท
if (isset($_SESSION['user_id'])) {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'tenant' ? 'my_bills.php' : 'dashboard.php'));
    exit;
}

// เรียกไฟล์เชื่อมต่อฐานข้อมูล (ได้ตัวแปร $conn)
require_once __DIR__ . '/config/db.php';

$error = '';  // เก็บข้อความ error ไว้แสดงในหน้าเว็บ
$email = '';  // เก็บอีเมลที่ผู้ใช้กรอก เพื่อเติมกลับในฟอร์มเมื่อล็อกอินพลาด

// ทำงานเฉพาะตอนกดปุ่มส่งฟอร์ม (method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม: trim ตัดช่องว่างหน้า-หลังอีเมล, ?? '' กันกรณีไม่มีค่าส่งมา
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';   // รหัสผ่านไม่ trim เพราะช่องว่างอาจเป็นส่วนหนึ่งของรหัส

    // 1) ตรวจว่ากรอกครบหรือไม่
    if ($email === '' || $password === '') {
        $error = 'กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน';

    // 2) ตรวจรูปแบบอีเมล
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';

    } else {
        // 3) ค้นหาผู้ใช้ด้วย Prepared Statement (ป้องกัน SQL Injection)
        //    ใช้เครื่องหมาย ? แทนค่า แล้วค่อยผูกค่าจริงด้วย bind_param
        $sql  = 'SELECT id, email, password_hash, role FROM project_users WHERE email = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);   // 's' = string (ชนิดข้อมูลของ ? ตัวที่ 1)
        $stmt->execute();

        $result = $stmt->get_result();    // ดึงผลลัพธ์ (ต้องมี mysqlnd ซึ่ง XAMPP มีให้แล้ว)
        $user   = $result->fetch_assoc(); // ได้ array ของแถวนั้น หรือ null ถ้าไม่พบ
        $stmt->close();

        // 4) ตรวจรหัสผ่านด้วย password_verify() เทียบรหัสที่กรอกกับค่า Hash ในฐานข้อมูล
        if ($user && password_verify($password, $user['password_hash'])) {

            // สร้าง Session ID ใหม่ ป้องกันการโจมตีแบบ Session Fixation
            session_regenerate_id(true);

            // เก็บข้อมูลผู้ใช้ลง Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];   // owner = เจ้าของหอ, tenant = ผู้เช่า

            // ล็อกอินสำเร็จ: เจ้าของหอไปหน้าภาพรวม ผู้เช่าไปหน้าบิลของตัวเอง
            // (สิทธิ์จริงตรวจซ้ำจากฐานข้อมูลทุกหน้าใน includes/auth.php)
            header('Location: ' . ($user['role'] === 'tenant' ? 'my_bills.php' : 'dashboard.php'));
            exit;
        }

        // ล็อกอินไม่สำเร็จ: ใช้ข้อความเดียวกันทั้งกรณีไม่มีอีเมลและรหัสผิด
        // เพื่อไม่ให้ผู้ไม่หวังดีรู้ว่าอีเมลไหนมีอยู่ในระบบ
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | ระบบจัดการหอพัก</title>

    <!-- Bootstrap 5 CSS ผ่าน CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons สำหรับไอคอนในฟอร์ม -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- ฟอนต์ภาษาไทย IBM Plex Sans Thai + ธีมของระบบ (โหลดหลัง Bootstrap) -->
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">

    <style>
        /* เฉพาะหน้า login: การ์ดกลางจอบนพื้นสีของระบบ */
        .login-card {
            max-width: 420px;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            background: var(--surface);
            color: var(--ink);
        }
        .brand-icon {
            width: 56px;
            height: 56px;
            font-size: 1.6rem;
            background: var(--ink);
            color: var(--surface);
            border-radius: var(--radius-md);
        }
        .login-card .form-control { background: var(--surface); color: var(--ink); border-color: var(--line); }
        .login-card .form-floating > label { color: var(--ink-2); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">

    <div class="card login-card w-100">
        <div class="card-body p-4 p-md-5">

            <!-- ส่วนหัวการ์ด: ไอคอน + ชื่อระบบ -->
            <div class="text-center mb-4">
                <div class="brand-icon d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-building"></i>
                </div>
                <h1 class="h4 fw-semibold mb-1">ระบบจัดการหอพัก</h1>
                <p class="text-muted small mb-0">กรุณาเข้าสู่ระบบเพื่อใช้งาน</p>
            </div>

            <!-- กล่องแจ้งเตือน Error: แสดงเฉพาะเมื่อ $error มีค่า -->
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <!-- htmlspecialchars ป้องกัน XSS ทุกครั้งที่แสดงค่าบนหน้าเว็บ -->
                    <div><?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
                </div>
            <?php endif; ?>

            <!-- ฟอร์มเข้าสู่ระบบ: ส่งข้อมูลกลับมาที่ไฟล์นี้เองด้วย POST -->
            <form method="post" action="login.php" novalidate>

                <!-- ช่องอีเมล (form-floating = ป้ายชื่อลอยแบบสวย ๆ ของ Bootstrap) -->
                <div class="form-floating mb-3">
                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           placeholder="name@example.com"
                           value="<?= htmlspecialchars($email) ?>"
                           autocomplete="username"
                           required
                           autofocus>
                    <label for="email"><i class="bi bi-envelope me-1"></i> อีเมล</label>
                </div>

                <!-- ช่องรหัสผ่าน (ไม่เติมค่ากลับเพื่อความปลอดภัย) -->
                <div class="form-floating mb-4">
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="รหัสผ่าน"
                           autocomplete="current-password"
                           required>
                    <label for="password"><i class="bi bi-lock me-1"></i> รหัสผ่าน</label>
                </div>

                <!-- ปุ่มเข้าสู่ระบบ -->
                <button type="submit" class="btn btn-primary w-100" style="min-height:48px">
                    <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                </button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                &copy; <?= date('Y') ?> Dormitory Management System
            </p>
        </div>
    </div>

    <!-- Bootstrap 5 JS (จำเป็นสำหรับปุ่มปิดกล่องแจ้งเตือน) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
