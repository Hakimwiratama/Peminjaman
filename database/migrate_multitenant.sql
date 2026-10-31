USE sipintar;

CREATE TABLE institutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    institution_type ENUM('school','university','other') NOT NULL DEFAULT 'school',
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_institutions_status (status)
) ENGINE=InnoDB;

INSERT INTO institutions (id,name,slug,institution_type,email,status)
VALUES (1,'Institusi Demo','institusi-demo','school','admin@sipintar.local','active')
ON DUPLICATE KEY UPDATE id=id;

INSERT IGNORE INTO roles (name,display_name) VALUES
('institution_admin','Admin Institusi'),('admin','Admin SIPINTAR');

ALTER TABLE users
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER role_id,
    ADD COLUMN student_number VARCHAR(50) NULL AFTER password,
    ADD COLUMN cohort VARCHAR(120) NULL AFTER student_number,
    ADD COLUMN phone VARCHAR(40) NULL AFTER cohort,
    ADD INDEX idx_users_tenant_role (tenant_id,role_id),
    ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;

UPDATE users u JOIN roles r ON r.id=u.role_id
SET u.tenant_id=1
WHERE r.name IN ('staff','user');

ALTER TABLE categories
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    DROP INDEX uq_category_type_name,
    ADD CONSTRAINT fk_categories_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    ADD UNIQUE KEY uq_category_tenant_type_name (tenant_id,type,name);

CREATE TABLE institution_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    module_key ENUM('item','book','lab','room','complaints') NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_tenant_module (tenant_id,module_key),
    CONSTRAINT fk_modules_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO institution_modules (tenant_id,module_key,is_enabled)
SELECT i.id,m.module_key,1 FROM institutions i
CROSS JOIN (SELECT 'item' module_key UNION ALL SELECT 'book' UNION ALL SELECT 'lab' UNION ALL SELECT 'room' UNION ALL SELECT 'complaints') m;

ALTER TABLE items ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_items_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;
ALTER TABLE books ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_books_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;
ALTER TABLE laboratory_equipments ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_labs_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;
ALTER TABLE rooms ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_rooms_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;

UPDATE items SET tenant_id=1 WHERE tenant_id IS NULL;
UPDATE books SET tenant_id=1 WHERE tenant_id IS NULL;
UPDATE laboratory_equipments SET tenant_id=1 WHERE tenant_id IS NULL;
UPDATE rooms SET tenant_id=1 WHERE tenant_id IS NULL;

ALTER TABLE borrowings ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_borrowings_tenant_status (tenant_id,status),
    ADD CONSTRAINT fk_borrowings_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;
UPDATE borrowings b JOIN users u ON u.id=b.user_id SET b.tenant_id=u.tenant_id WHERE b.tenant_id IS NULL;

ALTER TABLE activity_logs ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_activity_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;
UPDATE activity_logs a JOIN users u ON u.id=a.user_id SET a.tenant_id=u.tenant_id WHERE a.tenant_id IS NULL;

ALTER TABLE system_settings
    ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 FIRST,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (tenant_id,setting_key),
    ADD CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE;

CREATE TABLE complaints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    borrowing_id BIGINT UNSIGNED NULL,
    category ENUM('fasilitas','peminjaman','akun','layanan','lainnya') NOT NULL DEFAULT 'lainnya',
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
    admin_reply TEXT NULL,
    handled_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaints_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_complaints_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_complaints_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaints_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaints_tenant_status (tenant_id,status,created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO system_settings (tenant_id,setting_key,setting_value) VALUES
(1,'school_name','Institusi Demo'),(1,'max_loan_days','14'),(1,'late_fee_per_day','0');
