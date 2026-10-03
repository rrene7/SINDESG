-- SINDESG - Corrección de catálogo
-- Los códigos de categoría pasan a ser administrados por el sistema.
-- La secuencia usa el ID permanente de la categoría para garantizar orden y trazabilidad.
-- Ejemplo: id 1 = CAT-000001, id 11 = CAT-000011.

USE sindesg;

-- Primera etapa: apartar los códigos heredados/manuales para evitar conflictos
-- durante la normalización.
UPDATE categories
SET code = CONCAT('TMP-', LPAD(id, 10, '0'))
WHERE code <> CONCAT('CAT-', LPAD(id, 6, '0'));

-- Segunda etapa: asignar el código canónico y el orden de creación.
UPDATE categories
SET
    code = CONCAT('CAT-', LPAD(id, 6, '0')),
    sort_order = id * 10;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.2.2')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('004_category_codes')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
