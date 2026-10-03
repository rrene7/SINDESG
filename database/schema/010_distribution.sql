-- SINDESG - Fase 8
-- Distribución Almacén Central -> P4 con inventario en tránsito.
-- Solo modifica sindesg. RRHH2029 permanece en solo lectura.

SET NAMES utf8mb4;
USE sindesg;

ALTER TABLE service_request_items
    ADD COLUMN IF NOT EXISTS quantity_dispatched DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER quantity_approved,
    ADD COLUMN IF NOT EXISTS quantity_received DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER quantity_dispatched;

INSERT INTO warehouses (code, name, warehouse_type, organization_unit_id, is_active)
VALUES ('TRANSIT-SG', 'Inventario en tránsito', 'TRANSIT', NULL, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    warehouse_type = VALUES(warehouse_type),
    is_active = 1;

CREATE TABLE IF NOT EXISTS distributions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transfer_code VARCHAR(40) NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    source_warehouse_id BIGINT UNSIGNED NOT NULL,
    transit_warehouse_id BIGINT UNSIGNED NOT NULL,
    destination_warehouse_id BIGINT UNSIGNED NOT NULL,
    document_number VARCHAR(120) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'prepared',
    notes TEXT NULL,

    prepared_by_employee_id BIGINT UNSIGNED NOT NULL,
    prepared_by_label_snapshot VARCHAR(180) NOT NULL,
    prepared_by_document_snapshot VARCHAR(50) NULL,
    prepared_by_position_snapshot VARCHAR(50) NULL,
    prepared_by_rank_snapshot VARCHAR(120) NULL,
    prepared_at DATETIME NOT NULL,

    authorized_by_employee_id BIGINT UNSIGNED NULL,
    authorized_by_label_snapshot VARCHAR(180) NULL,
    authorized_at DATETIME NULL,
    authorization_notes TEXT NULL,

    delivered_by_employee_id BIGINT UNSIGNED NULL,
    delivered_by_label_snapshot VARCHAR(180) NULL,
    dispatched_at DATETIME NULL,
    dispatch_notes TEXT NULL,

    received_by_employee_id BIGINT UNSIGNED NULL,
    received_by_label_snapshot VARCHAR(180) NULL,
    received_at DATETIME NULL,
    receipt_notes TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_distributions_transfer_code (transfer_code),
    KEY idx_distributions_request_status (request_id, status),
    KEY idx_distributions_destination_status (destination_warehouse_id, status),

    CONSTRAINT fk_distributions_request
        FOREIGN KEY (request_id) REFERENCES service_requests (id),
    CONSTRAINT fk_distributions_source
        FOREIGN KEY (source_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_distributions_transit
        FOREIGN KEY (transit_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_distributions_destination
        FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT chk_distributions_status
        CHECK (status IN ('prepared', 'authorized', 'dispatched', 'received', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    distribution_id BIGINT UNSIGNED NOT NULL,
    request_item_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_distribution_request_item (distribution_id, request_item_id),
    KEY idx_distribution_items_variant (product_variant_id),

    CONSTRAINT fk_distribution_items_distribution
        FOREIGN KEY (distribution_id) REFERENCES distributions (id),
    CONSTRAINT fk_distribution_items_request_item
        FOREIGN KEY (request_item_id) REFERENCES service_request_items (id),
    CONSTRAINT fk_distribution_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT chk_distribution_items_quantity
        CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.8.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('010_distribution')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
