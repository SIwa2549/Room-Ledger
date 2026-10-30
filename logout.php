<?php
/**
 * ไฟล์: logout.php
 * หน้าที่: ออกจากระบบ — ล้าง Session ทั้งหมดแล้วกลับไปหน้า login.php
 */

// ต้องเริ่ม Session ก่อน จึงจะเข้าถึงและทำลาย Session ปัจจุบันได้
session_start();

// 1) ล้างค่าทุกตัวใน $_SESSION (เช่น user_id, email)
$_SESSION = [];

// 2) ลบคุกกี้ Session ฝั่งเบราว์เซอร์ด้วย (ตั้งวันหมดอายุเป็นอดีต)
//    ใช้ค่า path/domain เดียวกับตอนสร้าง คุกกี้จึงจะถูกลบจริง
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3) ทำลายข้อมูล Session ฝั่งเซิร์ฟเวอร์
session_destroy();

// 4) กลับไปหน้าเข้าสู่ระบบ
header('Location: login.php');
exit;
