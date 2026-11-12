-- 024: P4 de cabecera o de dependencia bajo una Dirección/Zona/Servicio.
-- Aditiva: solo altera sindesg.warehouses. RRHH2029 permanece en lectura.
USE sindesg;

ALTER TABLE warehouses
    ADD COLUMN IF NOT EXISTS p4_head_unit_id BIGINT UNSIGNED NULL AFTER organization_unit_type_snapshot,
    ADD COLUMN IF NOT EXISTS p4_head_unit_code_snapshot VARCHAR(80) NULL AFTER p4_head_unit_id,
    ADD COLUMN IF NOT EXISTS p4_head_unit_name_snapshot VARCHAR(220) NULL AFTER p4_head_unit_code_snapshot,
    ADD COLUMN IF NOT EXISTS p4_head_unit_type_snapshot VARCHAR(80) NULL AFTER p4_head_unit_name_snapshot;

-- Los P4 existentes no se modifican: su organization_unit_id ya es la unidad
-- logística efectiva. La cabecera histórica se guarda en los P4 nuevos.
INSERT INTO schema_migrations (migration)
VALUES ('024_p4_subordinate_warehouses')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
