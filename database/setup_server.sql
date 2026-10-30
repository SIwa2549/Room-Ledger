-- ============================================================
-- ไฟล์: database/setup_server.sql  (เวอร์ชันสำหรับเซิร์ฟเวอร์ Angsila)
-- ระบบจัดการหอพักและคำนวณบิลค่าเช่าออนไลน์
-- สร้างฐานข้อมูล 5 ตารางตาม ER Diagram ใน Proposal
-- ทุกตารางขึ้นต้นด้วย project_ เพื่อไม่ชนกับตารางของ Lab อื่นในฐานข้อมูลเดียวกัน
-- project_users, project_rooms, project_tenants, project_bills, project_settings
-- วิธีใช้: phpMyAdmin ของ Angsila > เลือกฐานข้อมูล s68160390 > แท็บ SQL > วางทั้งไฟล์ > Go
-- (บนเครื่องตัวเองที่ใช้ XAMPP ให้ใช้ setup.sql แทน)
-- คำเตือน: รันซ้ำแล้วข้อมูลเดิมทั้งหมดจะถูกลบและสร้างใหม่
-- ============================================================

-- เซิร์ฟเวอร์ Angsila: ใช้ฐานข้อมูลที่มหาวิทยาลัยสร้างไว้ให้ (สร้างฐานข้อมูลใหม่เองไม่ได้)
USE s68160390;

-- ลบตารางลูกก่อนตารางแม่ (ตารางที่มี Foreign Key ต้องลบก่อน)
DROP TABLE IF EXISTS project_bills;
DROP TABLE IF EXISTS project_settings;
DROP TABLE IF EXISTS project_users;      -- users มี FK ไปที่ tenants จึงต้องลบก่อน tenants
DROP TABLE IF EXISTS project_tenants;
DROP TABLE IF EXISTS project_rooms;

-- ------------------------------------------------------------
-- project_users : บัญชีที่ล็อกอินได้ มี 2 บทบาท
--   owner  = เจ้าของหอ/ผู้ดูแล จัดการได้ทุกอย่าง
--   tenant = ผู้เช่า ดูได้เฉพาะห้องและบิลของตัวเอง (ผูกกับ project_tenants ผ่าน tenant_id)
-- ------------------------------------------------------------
CREATE TABLE project_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('owner','tenant') NOT NULL DEFAULT 'owner',
    tenant_id     INT UNSIGNED NULL DEFAULT NULL,    -- มีค่าเฉพาะบัญชีผู้เช่า
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_users_email (email),
    UNIQUE KEY uq_project_users_tenant (tenant_id)   -- ผู้เช่า 1 คน มีบัญชีได้ 1 บัญชี
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- project_rooms : ห้องพัก (ชั้นของห้อง = หลักร้อยของเลขห้อง เช่น 204 อยู่ชั้น 2)
-- ------------------------------------------------------------
CREATE TABLE project_rooms (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    room_number VARCHAR(10)   NOT NULL,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,                 -- ค่าเช่าต่อเดือน
    status      ENUM('available','occupied','maintenance')
                NOT NULL DEFAULT 'available',                         -- ว่าง / มีผู้เช่า / ซ่อมบำรุง
    water_type  ENUM('meter','flat_rate') NOT NULL DEFAULT 'meter',   -- ค่าน้ำตามมิเตอร์ / เหมาจ่าย
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_rooms_room_number (room_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- project_tenants : ผู้เช่า (move_out_date เป็น NULL = ยังพักอยู่)
-- ------------------------------------------------------------
CREATE TABLE project_tenants (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id       INT UNSIGNED NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    move_in_date  DATE         NOT NULL,
    move_out_date DATE         NULL DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_tenants_room (room_id),
    -- RESTRICT: ลบห้องไม่ได้ถ้ายังมีประวัติผู้เช่า ป้องกันข้อมูลกำพร้า
    CONSTRAINT fk_project_tenants_room FOREIGN KEY (room_id) REFERENCES project_rooms (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- project_bills : บิลประจำเดือน (1 ห้อง 1 บิลต่อเดือน)
-- billing_month เก็บเป็นวันที่ 1 ของเดือน เช่น 2026-10-01 = บิลเดือน ต.ค. 2569
-- เก็บอัตราต่อหน่วยไว้ในบิลแต่ละใบ เมื่อปรับราคาภายหลัง บิลเก่าจะไม่เปลี่ยน
-- ห้องเหมาจ่าย: water_prev / water_curr เป็น NULL และ water_rate = ยอดเหมาจ่ายต่อเดือน
-- ------------------------------------------------------------
CREATE TABLE project_bills (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    room_id       INT UNSIGNED  NOT NULL,
    tenant_id     INT UNSIGNED  NOT NULL,
    created_by    INT UNSIGNED  NOT NULL,                 -- ผู้ดูแลที่ออกบิล
    billing_month DATE          NOT NULL,
    rent_amount   DECIMAL(10,2) NOT NULL,
    water_prev    INT UNSIGNED  NULL,
    water_curr    INT UNSIGNED  NULL,
    water_rate    DECIMAL(10,2) NOT NULL,
    elec_prev     INT UNSIGNED  NOT NULL,
    elec_curr     INT UNSIGNED  NOT NULL,
    elec_rate     DECIMAL(10,2) NOT NULL,
    total_amount  DECIMAL(10,2) NOT NULL,
    status        ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_bills_room_month (room_id, billing_month),   -- ห้ามออกบิลซ้ำเดือนเดียวกัน
    KEY idx_project_bills_tenant (tenant_id),
    KEY idx_project_bills_creator (created_by),
    CONSTRAINT fk_project_bills_room    FOREIGN KEY (room_id)    REFERENCES project_rooms (id)   ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_project_bills_tenant  FOREIGN KEY (tenant_id)  REFERENCES project_tenants (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_project_bills_creator FOREIGN KEY (created_by) REFERENCES project_users (id)   ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_project_bills_elec  CHECK (elec_curr >= elec_prev),
    CONSTRAINT chk_project_bills_water CHECK (water_curr IS NULL OR water_curr >= water_prev)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- project_settings : อัตราค่าบริการปัจจุบัน (มีแถวเดียว id = 1) แก้ได้จากหน้า "อัตราค่าบริการ"
-- ตอนออกบิล ระบบคัดลอกอัตราจากตารางนี้ไปเก็บในบิล เปลี่ยนอัตราภายหลัง บิลเก่าจึงไม่เปลี่ยน
-- ------------------------------------------------------------
CREATE TABLE project_settings (
    id              TINYINT UNSIGNED NOT NULL,
    elec_rate       DECIMAL(10,2) NOT NULL,     -- ค่าไฟ บาท/หน่วย
    water_rate      DECIMAL(10,2) NOT NULL,     -- ค่าน้ำตามมิเตอร์ บาท/หน่วย
    water_flat_rate DECIMAL(10,2) NOT NULL,     -- ค่าน้ำเหมาจ่าย บาท/เดือน
    updated_by      INT UNSIGNED  NULL,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_project_settings_user FOREIGN KEY (updated_by) REFERENCES project_users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK จาก users ไป tenants ต้องเพิ่มหลังสร้าง project_tenants แล้ว
-- CASCADE: ลบผู้เช่า (ได้เฉพาะคนที่ไม่มีบิล) บัญชีของผู้เช่าคนนั้นถูกลบตามไปด้วย
ALTER TABLE project_users
    ADD CONSTRAINT fk_project_users_tenant FOREIGN KEY (tenant_id) REFERENCES project_tenants (id)
        ON UPDATE CASCADE ON DELETE CASCADE;

-- ------------------------------------------------------------
-- บัญชีแอดมินตั้งต้น: admin@dorm.com / password123
-- (Hash จาก password_hash('password123', PASSWORD_DEFAULT) ทดสอบกับ password_verify() แล้ว)
-- ------------------------------------------------------------
INSERT INTO project_users (email, password_hash, role) VALUES
('admin@dorm.com', '$2y$10$EeBL8yiaWhkTDG6PweMz5uLO1o6mnIb7xejIvkUD7wk9vUqXi9gSe', 'owner');

-- ------------------------------------------------------------
-- ข้อมูลตัวอย่างสำหรับทดสอบ: 18 ห้อง 3 ชั้น, ผู้เช่า 12 คน, บิลเดือน ก.ย. 2569
-- (ชื่อและเบอร์โทรสมมติทั้งหมด) อัตรา: ไฟ 8 บาท/หน่วย, น้ำ 18 บาท/หน่วย, เหมาจ่าย 150 บาท
-- ------------------------------------------------------------
INSERT INTO project_rooms (room_number, price, status, water_type) VALUES
('101', 3500.00, 'occupied', 'meter'),
('102', 3500.00, 'available', 'meter'),
('103', 3500.00, 'occupied', 'meter'),
('104', 3500.00, 'maintenance', 'meter'),
('105', 3500.00, 'occupied', 'flat_rate'),
('106', 3500.00, 'available', 'meter'),
('201', 3800.00, 'occupied', 'meter'),
('202', 3800.00, 'occupied', 'meter'),
('203', 3800.00, 'available', 'flat_rate'),
('204', 3800.00, 'occupied', 'meter'),
('205', 3800.00, 'occupied', 'meter'),
('206', 3800.00, 'occupied', 'flat_rate'),
('301', 4000.00, 'occupied', 'meter'),
('302', 4000.00, 'occupied', 'meter'),
('303', 4000.00, 'available', 'flat_rate'),
('304', 4000.00, 'occupied', 'meter'),
('305', 4000.00, 'maintenance', 'meter'),
('306', 4000.00, 'occupied', 'flat_rate');

INSERT INTO project_tenants (room_id, full_name, phone, move_in_date, move_out_date) VALUES
(1, 'ศุภกร รัตนพันธ์', '080-455-2219', '2024-09-01', NULL),
(3, 'พิมพ์ชนก สายทอง', '061-876-3354', '2025-12-01', NULL),
(5, 'กิตติพัฒน์ นาคสวัสดิ์', '098-120-4478', '2026-02-01', NULL),
(7, 'อรอุมา ทองดี', '086-778-1203', '2023-07-01', NULL),
(8, 'ภานุวัฒน์ ชัยมงคล', '083-901-5566', '2025-02-20', NULL),
(10, 'สุดารัตน์ พรหมมา', '064-221-0098', '2024-05-01', NULL),
(11, 'วีรยุทธ อินทร์แก้ว', '092-640-7731', '2026-03-01', NULL),
(12, 'ชลธิชา มีสุข', '087-312-6640', '2025-10-01', NULL),
(13, 'กนกพร ศรีสุข', '081-234-5501', '2025-06-01', NULL),
(14, 'ธนพล วงศ์ใหญ่', '089-110-2290', '2024-11-15', NULL),
(16, 'ปิยะธิดา แก้วมณี', '062-554-8812', '2026-01-10', NULL),
(18, 'ณัฐวุฒิ บุญมา', '095-003-4417', '2025-08-01', NULL);

INSERT INTO project_bills (room_id, tenant_id, created_by, billing_month, rent_amount,
                   water_prev, water_curr, water_rate, elec_prev, elec_curr, elec_rate, total_amount, status) VALUES
(1, 1, 1, '2026-09-01', 3500.00, 1677, 1688, 18.00, 7977, 8105, 8.00, 4722.00, 'paid'),
(3, 2, 1, '2026-09-01', 3500.00, 499, 512, 18.00, 2633, 2730, 8.00, 4510.00, 'unpaid'),
(5, 3, 1, '2026-09-01', 3500.00, NULL, NULL, 150.00, 3239, 3350, 8.00, 4538.00, 'paid'),
(7, 4, 1, '2026-09-01', 3800.00, 1517, 1530, 18.00, 7702, 7810, 8.00, 4898.00, 'paid'),
(8, 5, 1, '2026-09-01', 3800.00, 788, 802, 18.00, 3879, 3994, 8.00, 4972.00, 'paid'),
(10, 6, 1, '2026-09-01', 3800.00, 1093, 1102, 18.00, 5078, 5207, 8.00, 4994.00, 'unpaid'),
(11, 7, 1, '2026-09-01', 3800.00, 367, 377, 18.00, 1754, 1890, 8.00, 5068.00, 'paid'),
(12, 8, 1, '2026-09-01', 3800.00, NULL, NULL, 150.00, 4372, 4470, 8.00, 4734.00, 'paid'),
(13, 9, 1, '2026-09-01', 4000.00, 1196, 1204, 18.00, 5497, 5630, 8.00, 5208.00, 'paid'),
(14, 10, 1, '2026-09-01', 4000.00, 971, 980, 18.00, 4272, 4412, 8.00, 5282.00, 'unpaid'),
(16, 11, 1, '2026-09-01', 4000.00, 644, 655, 18.00, 2912, 3021, 8.00, 5070.00, 'paid'),
(18, 12, 1, '2026-09-01', 4000.00, NULL, NULL, 150.00, 5997, 6120, 8.00, 5134.00, 'paid');

-- ------------------------------------------------------------
-- อัตราค่าบริการเริ่มต้น
-- ------------------------------------------------------------
INSERT INTO project_settings (id, elec_rate, water_rate, water_flat_rate, updated_by) VALUES
(1, 8.00, 18.00, 150.00, 1);

-- ------------------------------------------------------------
-- บัญชีผู้เช่าตัวอย่าง 2 บัญชี (รหัสผ่าน password123 เหมือนแอดมิน)
--   room101@dorm.com = ผู้เช่าห้อง 101 (ชำระครบ)
--   room204@dorm.com = ผู้เช่าห้อง 204 (มีบิลค้างชำระ)
-- ------------------------------------------------------------
INSERT INTO project_users (email, password_hash, role, tenant_id)
SELECT CONCAT('room', r.room_number, '@dorm.com'), '$2y$10$EeBL8yiaWhkTDG6PweMz5uLO1o6mnIb7xejIvkUD7wk9vUqXi9gSe', 'tenant', t.id
FROM project_tenants t JOIN project_rooms r ON r.id = t.room_id
WHERE r.room_number IN ('101', '204') AND t.move_out_date IS NULL;
