-- SINDESG - Correcciones de validación local
-- 1) Snapshot del funcionario que recibe.
-- 2) Reparación UTF-8 de categorías afectadas.
-- Solo modifica la base propia sindesg. NO modifica rrhh2029.

SET NAMES utf8mb4;
USE sindesg;

ALTER TABLE receipts
    ADD COLUMN IF NOT EXISTS received_by_employee_id BIGINT UNSIGNED NULL AFTER received_at,
    ADD COLUMN IF NOT EXISTS received_by_document_snapshot VARCHAR(50) NULL AFTER received_by_label,
    ADD COLUMN IF NOT EXISTS received_by_position_snapshot VARCHAR(50) NULL AFTER received_by_document_snapshot,
    ADD COLUMN IF NOT EXISTS received_by_rank_snapshot VARCHAR(120) NULL AFTER received_by_position_snapshot,
    ADD COLUMN IF NOT EXISTS received_by_unit_id_snapshot BIGINT UNSIGNED NULL AFTER received_by_rank_snapshot,
    ADD COLUMN IF NOT EXISTS received_by_unit_name_snapshot VARCHAR(220) NULL AFTER received_by_unit_id_snapshot;

-- Hex UTF-8 para que la reparación no dependa de la codificación de la consola.
UPDATE categories
SET
    name = CONVERT(0x5375C3A9746572 USING utf8mb4),
    description = CONVERT(0x5375C3A974657265732079207072656e646173206571756976616c656e74657320726567697374726164617320706f7220536572766963696f732047656e6572616c65732e USING utf8mb4)
WHERE slug = 'sueter';

UPDATE categories
SET
    name = CONVERT(0x50616e74616cc3b36e USING utf8mb4),
    description = CONVERT(0x50616e74616c6f6e657320646520756e69666f726d652079206661656e612e USING utf8mb4)
WHERE slug = 'pantalon';

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.4.1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('007_dashboard_rrhh_receipts')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
