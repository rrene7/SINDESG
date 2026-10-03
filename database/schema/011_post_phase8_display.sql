-- SINDESG - ajuste posterior a Fase 8
-- Corrige etiqueta persistida del Almacén Central sin depender de la codificación del terminal.

SET NAMES utf8mb4;
USE sindesg;

UPDATE warehouses
SET name = CONVERT(0x416C6D6163C3A96E2043656E7472616C20646520536572766963696F732047656E6572616C6573 USING utf8mb4)
WHERE code = 'CENTRAL-SG';

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.8.1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('011_post_phase8_display')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
