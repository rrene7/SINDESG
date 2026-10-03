-- SINDESG - Fase 6
-- Red de Almacenes y P4 vinculada a RRHH2029.
-- Solo modifica la base propia sindesg. NO modifica rrhh2029.

SET NAMES utf8mb4;
USE sindesg;

ALTER TABLE warehouses
    ADD COLUMN IF NOT EXISTS organization_unit_code_snapshot VARCHAR(80) NULL AFTER organization_unit_id,
    ADD COLUMN IF NOT EXISTS organization_unit_name_snapshot VARCHAR(220) NULL AFTER organization_unit_code_snapshot,
    ADD COLUMN IF NOT EXISTS organization_unit_type_snapshot VARCHAR(80) NULL AFTER organization_unit_name_snapshot,
    ADD COLUMN IF NOT EXISTS responsible_employee_id BIGINT UNSIGNED NULL AFTER organization_unit_type_snapshot,
    ADD COLUMN IF NOT EXISTS responsible_label_snapshot VARCHAR(180) NULL AFTER responsible_employee_id,
    ADD COLUMN IF NOT EXISTS responsible_document_snapshot VARCHAR(50) NULL AFTER responsible_label_snapshot,
    ADD COLUMN IF NOT EXISTS responsible_position_snapshot VARCHAR(50) NULL AFTER responsible_document_snapshot,
    ADD COLUMN IF NOT EXISTS responsible_rank_snapshot VARCHAR(120) NULL AFTER responsible_position_snapshot,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER responsible_rank_snapshot,
    ADD COLUMN IF NOT EXISTS status_changed_at DATETIME NULL AFTER is_active;

SET @warehouse_org_unique_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'sindesg'
      AND TABLE_NAME = 'warehouses'
      AND INDEX_NAME = 'uq_warehouses_org_unit_unique'
);

SET @warehouse_org_unique_sql = IF(
    @warehouse_org_unique_exists = 0,
    'CREATE UNIQUE INDEX uq_warehouses_org_unit_unique ON warehouses (organization_unit_id)',
    'SELECT 1'
);

PREPARE warehouse_org_unique_stmt FROM @warehouse_org_unique_sql;
EXECUTE warehouse_org_unique_stmt;
DEALLOCATE PREPARE warehouse_org_unique_stmt;

UPDATE warehouses
SET status_changed_at = COALESCE(status_changed_at, updated_at)
WHERE status_changed_at IS NULL;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.6.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('008_warehouses_p4')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
