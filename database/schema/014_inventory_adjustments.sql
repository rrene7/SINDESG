-- SINDESG - Fase 10
-- Cambios, devoluciones, bajas y ajustes.
-- Nunca modifica saldos manualmente: confirma movimientos compensatorios en kardex.

SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS inventory_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    adjustment_code VARCHAR(40) NOT NULL,
    adjustment_type VARCHAR(40) NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,

    source_delivery_item_id BIGINT UNSIGNED NULL,
    affected_employee_id BIGINT UNSIGNED NULL,
    affected_employee_label_snapshot VARCHAR(180) NULL,
    affected_employee_document_snapshot VARCHAR(50) NULL,
    affected_employee_position_snapshot VARCHAR(50) NULL,
    affected_employee_rank_snapshot VARCHAR(120) NULL,

    product_variant_id BIGINT UNSIGNED NOT NULL,
    replacement_variant_id BIGINT UNSIGNED NULL,

    quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    counted_quantity DECIMAL(14,3) NULL,
    return_condition VARCHAR(20) NULL,
    manual_direction VARCHAR(3) NULL,

    responsible_employee_id BIGINT UNSIGNED NOT NULL,
    responsible_label_snapshot VARCHAR(180) NOT NULL,
    responsible_document_snapshot VARCHAR(50) NULL,
    responsible_position_snapshot VARCHAR(50) NULL,
    responsible_rank_snapshot VARCHAR(120) NULL,

    occurred_at DATETIME NOT NULL,
    document_number VARCHAR(120) NULL,
    reason VARCHAR(255) NOT NULL,
    notes TEXT NULL,

    system_quantity_snapshot DECIMAL(14,3) NULL,
    primary_stock_before DECIMAL(14,3) NULL,
    primary_stock_after DECIMAL(14,3) NULL,
    replacement_stock_before DECIMAL(14,3) NULL,
    replacement_stock_after DECIMAL(14,3) NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    confirmed_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_adjustments_code (adjustment_code),
    KEY idx_inventory_adjustments_status_date (status, occurred_at),
    KEY idx_inventory_adjustments_warehouse (warehouse_id, occurred_at),
    KEY idx_inventory_adjustments_source_delivery_item (source_delivery_item_id),
    KEY idx_inventory_adjustments_employee (affected_employee_id, occurred_at),
    KEY idx_inventory_adjustments_variant (product_variant_id, occurred_at),

    CONSTRAINT fk_inventory_adjustments_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_inventory_adjustments_source_delivery_item
        FOREIGN KEY (source_delivery_item_id) REFERENCES individual_delivery_items (id),
    CONSTRAINT fk_inventory_adjustments_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT fk_inventory_adjustments_replacement_variant
        FOREIGN KEY (replacement_variant_id) REFERENCES product_variants (id),

    CONSTRAINT chk_inventory_adjustments_type
        CHECK (adjustment_type IN (
            'return',
            'exchange',
            'replacement',
            'deterioration',
            'writeoff',
            'loss',
            'shortage',
            'surplus',
            'authorized_adjustment',
            'cycle_count'
        )),
    CONSTRAINT chk_inventory_adjustments_status
        CHECK (status IN ('draft', 'confirmed', 'cancelled')),
    CONSTRAINT chk_inventory_adjustments_return_condition
        CHECK (return_condition IS NULL OR return_condition IN ('reusable', 'non_reusable')),
    CONSTRAINT chk_inventory_adjustments_manual_direction
        CHECK (manual_direction IS NULL OR manual_direction IN ('IN', 'OUT')),
    CONSTRAINT chk_inventory_adjustments_quantity
        CHECK (quantity >= 0),
    CONSTRAINT chk_inventory_adjustments_counted_quantity
        CHECK (counted_quantity IS NULL OR counted_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.10.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('014_inventory_adjustments')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
