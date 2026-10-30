-- ============================================================
-- ไฟล์: database/migrate_v3.sql
-- เพิ่มตาราง project_requests เก็บรายการแจ้งซ่อมจากผู้เช่า (ข้อมูลเดิมไม่หาย)
-- วิธีใช้: phpMyAdmin > คลิกเลือกฐานข้อมูล (บน Angsila คือ s68160390) > แท็บ SQL > วางทั้งไฟล์ > Go
-- รันหลัง migrate_v2.sql ครั้งเดียวพอ (ถ้ารันซ้ำจะ error ว่ามีตารางอยู่แล้ว ข้อมูลไม่เสียหาย)
-- ติดตั้งใหม่ทั้งหมดใช้ setup.sql / setup_server.sql แทน
-- ============================================================

CREATE TABLE project_requests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id    INT UNSIGNED NOT NULL,
    category     ENUM('aircon','plumbing','water','electric','other') NOT NULL,
    detail       VARCHAR(500) NOT NULL,
    status       ENUM('pending','done') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME     NULL DEFAULT NULL,
    completed_by INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_project_requests_tenant (tenant_id),
    KEY idx_project_requests_status (status, created_at),
    CONSTRAINT fk_project_requests_tenant FOREIGN KEY (tenant_id) REFERENCES project_tenants (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_project_requests_user FOREIGN KEY (completed_by) REFERENCES project_users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
