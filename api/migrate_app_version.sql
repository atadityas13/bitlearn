-- Manajemen versi aplikasi Android (opsional; juga dibuat otomatis oleh AppVersion::ensureTable)
CREATE TABLE IF NOT EXISTS app_version_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(20) NOT NULL DEFAULT 'android',
    latest_version_code INT NOT NULL DEFAULT 1,
    latest_version_name VARCHAR(20) NOT NULL DEFAULT '1.0.0',
    min_version_code INT NOT NULL DEFAULT 1,
    force_update TINYINT(1) NOT NULL DEFAULT 0,
    update_url VARCHAR(500) DEFAULT NULL,
    release_notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    UNIQUE KEY uniq_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
