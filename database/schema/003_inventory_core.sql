-- SINDESG - Fase 2B
-- Almacén Central, movimientos base y cálculo de existencias.
-- Requiere 001_bootstrap.sql y 002_catalog.sql.
-- No modifica rrhh2029.

USE sindesg;

CREATE TABLE IF NOT EXISTS warehouses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    warehouse_type VARCHAR(30) NOT NULL,
    organization_unit_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_warehouses_code (code),
    KEY idx_warehouses_type_active (warehouse_type, is_active),
    KEY idx_warehouses_org_unit (organization_unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    movement_kind VARCHAR(40) NOT NULL,
    direction VARCHAR(3) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    occurred_at DATETIME NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    reference_code VARCHAR(100) NULL,
    document_number VARCHAR(120) NULL,
    notes TEXT NULL,
    actor_label VARCHAR(160) NULL,
    source_key VARCHAR(191) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_movements_source_key (source_key),
    KEY idx_inventory_movements_warehouse_variant (warehouse_id, product_variant_id),
    KEY idx_inventory_movements_variant_date (product_variant_id, occurred_at),
    KEY idx_inventory_movements_reference (reference_type, reference_id),
    KEY idx_inventory_movements_kind_date (movement_kind, occurred_at),
    CONSTRAINT fk_inventory_movements_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_inventory_movements_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT chk_inventory_movements_direction
        CHECK (direction IN ('IN', 'OUT')),
    CONSTRAINT chk_inventory_movements_quantity
        CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO warehouses (code, name, warehouse_type, organization_unit_id, is_active)
VALUES ('CENTRAL-SG', 'Almacén Central de Servicios Generales', 'CENTRAL', NULL, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    warehouse_type = VALUES(warehouse_type),
    is_active = VALUES(is_active);

CREATE OR REPLACE VIEW v_inventory_stock AS
SELECT
    im.warehouse_id,
    im.product_variant_id,
    CAST(SUM(
        CASE
            WHEN im.direction = 'IN' THEN im.quantity
            WHEN im.direction = 'OUT' THEN -im.quantity
            ELSE 0
        END
    ) AS DECIMAL(14,3)) AS quantity_on_hand,
    MAX(im.occurred_at) AS last_movement_at
FROM inventory_movements im
GROUP BY im.warehouse_id, im.product_variant_id;

CREATE OR REPLACE VIEW v_inventory_product_stock AS
SELECT
    pv.product_id,
    CAST(COALESCE(SUM(vs.quantity_on_hand), 0) AS DECIMAL(14,3)) AS quantity_on_hand
FROM product_variants pv
LEFT JOIN v_inventory_stock vs ON vs.product_variant_id = pv.id
WHERE pv.status <> 'deleted'
GROUP BY pv.product_id;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.2.1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('003_inventory_core')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
