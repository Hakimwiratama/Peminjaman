CREATE DATABASE IF NOT EXISTS sipintar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sipintar;

CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    display_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS region_codes (
    code VARCHAR(13) NOT NULL PRIMARY KEY,
    parent_code VARCHAR(13) NULL,
    name VARCHAR(100) NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    CONSTRAINT fk_region_codes_parent FOREIGN KEY (parent_code) REFERENCES region_codes(code),
    INDEX idx_region_codes_parent (parent_code, name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS institutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    institution_type ENUM('school','university','other') NOT NULL DEFAULT 'school',
    institution_npsn VARCHAR(20) NULL UNIQUE,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    province_code VARCHAR(13) NULL,
    regency_code VARCHAR(13) NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_institutions_province FOREIGN KEY (province_code) REFERENCES region_codes(code),
    CONSTRAINT fk_institutions_regency FOREIGN KEY (regency_code) REFERENCES region_codes(code),
    INDEX idx_institutions_region_status (province_code, regency_code, status),
    INDEX idx_institutions_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    student_number VARCHAR(50) NULL,
    cohort VARCHAR(120) NULL,
    phone VARCHAR(40) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_users_tenant_role (tenant_id, role_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    type ENUM('item','book','lab','room') NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_categories_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_category_tenant_type_name (tenant_id, type, name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS institution_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    module_key ENUM('item','book','lab','room','complaints') NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_tenant_module (tenant_id, module_key),
    CONSTRAINT fk_modules_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(40) NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    `condition` VARCHAR(80) NOT NULL DEFAULT 'Baik',
    location VARCHAR(160) NULL,
    image_path VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_items_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_items_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    author VARCHAR(160) NULL,
    publisher VARCHAR(160) NULL,
    published_year SMALLINT UNSIGNED NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    `condition` VARCHAR(80) NOT NULL DEFAULT 'Baik',
    location VARCHAR(160) NULL,
    image_path VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_books_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_books_name (name),
    INDEX idx_books_author (author)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS laboratory_equipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    author VARCHAR(160) NULL,
    publisher VARCHAR(160) NULL,
    published_year SMALLINT UNSIGNED NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    `condition` VARCHAR(80) NOT NULL DEFAULT 'Baik',
    location VARCHAR(160) NULL,
    image_path VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_labs_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_labs_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_labs_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    facilities TEXT NULL,
    image_path VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rooms_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_rooms_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_rooms_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS borrowings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('item','book','lab','room') NOT NULL,
    status ENUM('waiting','approved','rejected','borrowed','return_pending','completed') NOT NULL DEFAULT 'waiting',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    rejection_reason TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    returned_at DATETIME NULL,
    CONSTRAINT fk_borrowings_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_borrowings_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_borrowings_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_borrowings_confirmer FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_borrowings_status (status),
    INDEX idx_borrowings_dates (start_date, end_date),
    INDEX idx_borrowings_requested (requested_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS borrowing_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borrowing_id BIGINT UNSIGNED NOT NULL,
    resource_type ENUM('item','book','lab','room') NOT NULL,
    resource_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_details_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings(id) ON DELETE CASCADE,
    UNIQUE KEY uq_borrowing_resource (borrowing_id, resource_type, resource_id),
    INDEX idx_details_resource (resource_type, resource_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS room_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borrowing_id BIGINT UNSIGNED NOT NULL UNIQUE,
    room_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    CONSTRAINT fk_room_booking_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings(id) ON DELETE CASCADE,
    CONSTRAINT fk_room_booking_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    INDEX idx_room_booking_schedule (room_id, start_at, end_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borrowing_id BIGINT UNSIGNED NOT NULL UNIQUE,
    confirmed_by BIGINT UNSIGNED NULL,
    condition_note VARCHAR(500) NULL,
    returned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_returns_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings(id),
    CONSTRAINT fk_returns_confirmer FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    detail TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS complaints (
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

CREATE TABLE IF NOT EXISTS system_settings (
    tenant_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id,setting_key),
    CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES institutions(id) ON DELETE CASCADE,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (name, display_name) VALUES
('admin','Admin SIPINTAR'),('institution_admin','Admin Institusi'),('staff','Petugas'),('user','Siswa / Mahasiswa');

INSERT IGNORE INTO categories (type, name) VALUES
('item','Elektronik'),('item','Peralatan kelas'),('item','Audio visual'),
('book','Fiksi'),('book','Referensi'),('book','Sains'),
('lab','Biologi'),('lab','Fisika'),('lab','Komputer'),
('room','Ruang kelas'),('room','Laboratorium'),('room','Ruang kegiatan');
