-- SINDESG - Fase 1
-- Núcleo técnico inicial para MariaDB / XAMPP.
-- No modifica rrhh2029.

CREATE DATABASE IF NOT EXISTS sindesg
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sindesg;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(160) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES
    ('app_name', 'SINDESG'),
    ('app_full_name', 'Sistema de Inventario y Despacho de Servicios Generales'),
    ('schema_version', '0.1.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('001_bootstrap')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
