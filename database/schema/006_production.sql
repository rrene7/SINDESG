-- SINDESG - Fase 4
-- Producción interna en Servicios Generales.
-- Requiere 001_bootstrap.sql a 005_receipts.sql.
-- No modifica rrhh2029.

USE sindesg;

CREATE TABLE IF NOT EXISTS production_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_code VARCHAR(40) NOT NULL,
    internal_lot_code VARCHAR(40) NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    produced_at DATETIME NOT NULL,
    responsible_label VARCHAR(160) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_orders_code (production_code),
    UNIQUE KEY uq_production_orders_lot (internal_lot_code),
    KEY idx_production_orders_status_date (status, produced_at),
    CONSTRAINT fk_production_orders_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT chk_production_orders_status
        CHECK (status IN ('draft', 'confirmed', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_outputs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_order_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_outputs_variant (production_order_id, product_variant_id),
    KEY idx_production_outputs_variant (product_variant_id),
    CONSTRAINT fk_production_outputs_order
        FOREIGN KEY (production_order_id) REFERENCES production_orders (id),
    CONSTRAINT fk_production_outputs_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT chk_production_outputs_quantity
        CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_inputs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_order_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_inputs_variant (production_order_id, product_variant_id),
    KEY idx_production_inputs_variant (product_variant_id),
    CONSTRAINT fk_production_inputs_order
        FOREIGN KEY (production_order_id) REFERENCES production_orders (id),
    CONSTRAINT fk_production_inputs_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT chk_production_inputs_quantity
        CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.4.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('006_production')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
