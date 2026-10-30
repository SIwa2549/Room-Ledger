-- ============================================================
-- ไฟล์: database/migrate_v2.sql
-- อัปเดตฐานข้อมูลที่สร้างจาก setup เวอร์ชันแรก (4 ตาราง) ให้เป็นเวอร์ชันใหม่ โดยข้อมูลเดิมไม่หาย
--   1) เพิ่มบทบาทผู้ใช้ (owner / tenant) และผูกบัญชีผู้เช่ากับ project_tenants
--   2) เพิ่มตาราง project_settings เก็บอัตราค่าบริการ
--   3) สร้างบัญชีผู้เช่าตัวอย่างของห้อง 101 และ 204
-- วิธีใช้: phpMyAdmin > คลิกเลือกฐานข้อมูล (บน Angsila คือ s68160390) > แท็บ SQL > วางทั้งไฟล์ > Go
-- รันครั้งเดียวพอ (ถ้ารันซ้ำจะ error ว่ามีคอลัมน์/ตารางอยู่แล้ว ข้อมูลไม่เสียหาย)
-- ติดตั้งใหม่ทั้งหมดใช้ setup.sql / setup_server.sql แทน
-- ============================================================

ALTER TABLE project_users
    ADD COLUMN role      ENUM('owner','tenant') NOT NULL DEFAULT 'owner' AFTER password_hash,
    ADD COLUMN tenant_id INT UNSIGNED NULL DEFAULT NULL AFTER role,
    ADD UNIQUE KEY uq_project_users_tenant (tenant_id),
    ADD CONSTRAINT fk_project_users_tenant FOREIGN KEY (tenant_id) REFERENCES project_tenants (id)
        ON UPDATE CASCADE ON DELETE CASCADE;

CREATE TABLE project_settings (
    id              TINYINT UNSIGNED NOT NULL,
    elec_rate       DECIMAL(10,2) NOT NULL,
    water_rate      DECIMAL(10,2) NOT NULL,
    water_flat_rate DECIMAL(10,2) NOT NULL,
    updated_by      INT UNSIGNED  NULL,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_project_settings_user FOREIGN KEY (updated_by) REFERENCES project_users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO project_settings (id, elec_rate, water_rate, water_flat_rate, updated_by)
SELECT 1, 8.00, 18.00, 150.00, MIN(id) FROM project_users WHERE role = 'owner';

-- บัญชีผู้เช่าตัวอย่าง (รหัสผ่าน password123)
INSERT INTO project_users (email, password_hash, role, tenant_id)
SELECT CONCAT('room', r.room_number, '@dorm.com'), '$2y$10$EeBL8yiaWhkTDG6PweMz5uLO1o6mnIb7xejIvkUD7wk9vUqXi9gSe', 'tenant', t.id
FROM project_tenants t JOIN project_rooms r ON r.id = t.room_id
WHERE r.room_number IN ('101', '204') AND t.move_out_date IS NULL;
