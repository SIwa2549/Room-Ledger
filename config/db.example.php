<?php
/**
 * ไฟล์: config/db.php  (ไฟล์ตัวอย่าง: คัดลอกเป็น config/db.php แล้วแก้ค่าให้ตรงกับเครื่อง)
 * หน้าที่: เชื่อมต่อฐานข้อมูล MySQL ด้วย mysqli (แบบ Object-oriented)
 * วิธีใช้: ไฟล์อื่นเรียกด้วย require_once __DIR__ . '/config/db.php';
 *          แล้วใช้ตัวแปร $conn ได้ทันที
 */

// ---------- ค่าการเชื่อมต่อ (แก้ให้ตรงกับเครื่องของตัวเอง) ----------
// XAMPP ค่าเริ่มต้น: user = root, password = ว่าง
// MAMP ค่าเริ่มต้น: password = root
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // ใส่รหัสผ่านของเครื่องตัวเอง
define('DB_NAME', 'dorm_db');

// สั่งให้ mysqli โยน Exception เมื่อเกิดข้อผิดพลาด
// (ตั้งแต่ PHP 8.1 เป็นค่าเริ่มต้นอยู่แล้ว แต่ใส่ไว้ให้ชัดเจนและรองรับ PHP เวอร์ชันเก่า)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // สร้างการเชื่อมต่อ
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // ตั้ง charset เป็น utf8mb4 เพื่อให้อ่าน/เขียนภาษาไทยได้ถูกต้อง
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    // บันทึกรายละเอียด error จริงลง log ของเซิร์ฟเวอร์ (ไว้ให้นักพัฒนาดู)
    error_log('Database connection failed: ' . $e->getMessage());

    // แสดงข้อความกลาง ๆ ให้ผู้ใช้ ไม่เปิดเผยรายละเอียดระบบ (เช่น ชื่อ user/host)
    // ระหว่างพัฒนาถ้าอยากเห็น error จริง ให้ชั่วคราวเปลี่ยนเป็น echo $e->getMessage();
    http_response_code(500);
    exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่อีกครั้งภายหลัง');
}
