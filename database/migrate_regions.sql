USE sipintar;

CREATE TABLE region_codes (
    code VARCHAR(13) NOT NULL PRIMARY KEY,
    parent_code VARCHAR(13) NULL,
    name VARCHAR(100) NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    CONSTRAINT fk_region_codes_parent FOREIGN KEY (parent_code) REFERENCES region_codes(code),
    INDEX idx_region_codes_parent (parent_code, name)
) ENGINE=InnoDB;

ALTER TABLE institutions
    ADD COLUMN institution_npsn VARCHAR(20) NULL UNIQUE AFTER institution_type,
    ADD COLUMN province_code VARCHAR(13) NULL AFTER address,
    ADD COLUMN regency_code VARCHAR(13) NULL AFTER province_code,
    ADD CONSTRAINT fk_institutions_province FOREIGN KEY (province_code) REFERENCES region_codes(code),
    ADD CONSTRAINT fk_institutions_regency FOREIGN KEY (regency_code) REFERENCES region_codes(code),
    ADD INDEX idx_institutions_region_status (province_code, regency_code, status);
