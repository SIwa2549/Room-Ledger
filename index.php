<?php
/**
 * ไฟล์: index.php
 * เข้า http://localhost/dorm/ แล้วส่งไปหน้าที่เหมาะสม: ล็อกอินแล้วไปหน้าแรกตามบทบาท (เจ้าของหอ = ภาพรวม, ผู้เช่า = บิลของฉัน) ยังไม่ล็อกอินไปหน้า login
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
} else {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'tenant' ? 'my_bills.php' : 'dashboard.php'));
}
exit;
