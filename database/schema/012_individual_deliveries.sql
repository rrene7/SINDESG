-- SINDESG - Fase 9
-- Despacho individual P4 -> funcionario.
-- Requiere Fases 1 a 8.
-- RRHH2029 permanece en solo lectura.

SET NAMES utf8mb4;
USE sindesg;

ALTER TABLE service_request_items
    ADD COLUMN IF NOT EXISTS quantity_delivered DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER quantity_received;

CREATE TABLE IF NOT EXISTS individual_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_code VARCHAR(40) NOT NULL,
    p4_warehouse_id BIGINT UNSIGNED NOT NULL,

    recipient_employee_id BIGINT UNSIGNED NOT NULL,
    recipient_label_snapshot VARCHAR(180) NOT NULL,
    recipient_document_snapshot VARCHAR(50) NULL,
    recipient_position_snapshot VARCHAR(50) NULL,
    recipient_rank_snapshot VARCHAR(120) NULL,
    recipient_unit_id_snapshot BIGINT UNSIGNED NULL,
    recipient_unit_name_snapshot VARCHAR(220) NULL,
    recipient_direction_snapshot VARCHAR(220) NULL,
    recipient_office_snapshot VARCHAR(220) NULL,

    delivered_by_employee_id BIGINT UNSIGNED NOT NULL,
    delivered_by_label_snapshot VARCHAR(180) NOT NULL,
    delivered_by_document_snapshot VARCHAR(50) NULL,
    delivered_by_position_snapshot VARCHAR(50) NULL,
    delivered_by_rank_snapshot VARCHAR(120) NULL,

    delivered_at DATETIME NOT NULL,
    document_number VARCHAR(120) NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    confirmed_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_individual_deliveries_code (delivery_code),
    KEY idx_individual_deliveries_recipient_date (recipient_employee_id, delivered_at),
    KEY idx_individual_deliveries_p4_status (p4_warehouse_id, status),

    CONSTRAINT fk_individual_deliveries_p4
        FOREIGN KEY (p4_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT chk_individual_deliveries_status
        CHECK (status IN ('draft', 'confirmed', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS individual_delivery_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    request_item_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(14,3) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_individual_delivery_items_delivery (delivery_id),
    KEY idx_individual_delivery_items_variant (product_variant_id),
    KEY idx_individual_delivery_items_request_item (request_item_id),

    CONSTRAINT fk_individual_delivery_items_delivery
        FOREIGN KEY (delivery_id) REFERENCES individual_deliveries (id),
    CONSTRAINT fk_individual_delivery_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT fk_individual_delivery_items_request_item
        FOREIGN KEY (request_item_id) REFERENCES service_request_items (id),
    CONSTRAINT chk_individual_delivery_items_quantity
        CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.9.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('012_individual_deliveries')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
