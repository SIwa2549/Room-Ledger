<?php
/**
 * ไฟล์: includes/auth.php
 * ใส่เป็นบรรทัดแรกของทุกหน้าที่ต้องล็อกอินก่อน: require_once __DIR__ . '/includes/auth.php';
 *
 * 1) Authentication: ยังไม่ล็อกอิน -> กลับหน้า login
 * 2) Authorization:  ทุกหน้าเป็นของ "เจ้าของหอ" (owner) เป็นค่าเริ่มต้น
 *    หน้าที่ให้ผู้เช่าใช้ ต้องประกาศก่อน require:  const PAGE_ROLE = 'tenant';
 *    หน้าที่ใช้ได้ทุกบทบาท (เช่น เปลี่ยนรหัสผ่าน):   const PAGE_ROLE = 'any';
 *    ผู้เช่าเปิดหน้าของเจ้าของหอ -> เจอหน้า 403 "ไม่มีสิทธิ์"
 * หลังไฟล์นี้ใช้ได้: $conn (ฐานข้อมูล) และ $currentUser (id, email, role, tenant_id)
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/functions.php';

// ---------- 1) Authentication ----------
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

// โหลดบัญชีจากฐานข้อมูลทุกครั้ง: ถ้าบัญชีถูกลบหรือถูกเปลี่ยนบทบาท จะมีผลทันที ไม่ต้องรอ Session หมดอายุ
$stmt = $conn->prepare('SELECT id, email, role, tenant_id FROM project_users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentUser) {             // บัญชีถูกลบไปแล้ว
    $_SESSION = [];
    session_destroy();
    redirect('login.php');
}

// ---------- 2) Authorization ----------
$pageRole = defined('PAGE_ROLE') ? PAGE_ROLE : 'owner';
if ($pageRole !== 'any' && $currentUser['role'] !== $pageRole) {
    if ($currentUser['role'] === 'owner') {
        redirect('dashboard.php');          // เจ้าของหอไม่มีหน้าผู้เช่า ส่งกลับหน้าภาพรวม
    }
    http_response_code(403);                // ผู้เช่าพยายามเปิดหน้าของเจ้าของหอ
    $pageTitle = 'ไม่มีสิทธิ์เข้าถึง';
    $activeNav = '';
    require __DIR__ . '/header.php';
    echo '<div class="empty"><h1 class="h-sm">ไม่มีสิทธิ์เข้าถึงหน้านี้</h1>'
       . '<p>บัญชีผู้เช่าดูได้เฉพาะห้องและบิลของตัวเอง</p>'
       . '<a class="btn btn-sm btn-primary" href="my_bills.php">ไปหน้าบิลของฉัน</a></div>';
    require __DIR__ . '/footer.php';
    exit;
}
