<?php
/**
 * ไฟล์: includes/settings.php
 * ค่าตั้งของระบบ (อัตราค่าน้ำค่าไฟย้ายไปเก็บในตาราง project_settings แล้ว แก้ได้จากหน้า "อัตราค่าบริการ")
 */

date_default_timezone_set('Asia/Bangkok');   // ให้ "เดือนปัจจุบัน" ตรงกับเวลาไทย

const DORM_NAME = 'ระบบจัดการหอพัก';

// อัตราสำรอง ใช้เฉพาะกรณีตาราง project_settings ยังไม่มีข้อมูล
const DEFAULT_RATES = ['elec_rate' => 8.0, 'water_rate' => 18.0, 'water_flat_rate' => 150.0];
