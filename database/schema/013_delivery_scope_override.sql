-- SINDESG - Excepción controlada de adscripción en despacho individual
SET NAMES utf8mb4;
USE sindesg;

ALTER TABLE individual_deliveries
    ADD COLUMN IF NOT EXISTS scope_override_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notes,
    ADD COLUMN IF NOT EXISTS scope_override_reason VARCHAR(255) NULL AFTER scope_override_enabled;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.9.1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('013_delivery_scope_override')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
