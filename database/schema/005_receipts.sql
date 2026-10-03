-- SINDESG - Fase 3
-- Recepción de lotes al Almacén Central.
-- Requiere 001_bootstrap.sql a 004_category_codes.sql.
-- No modifica rrhh2029.

USE sindesg;

CREATE TABLE IF NOT EXISTS receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_code VARCHAR(40) NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(30) NOT NULL,
    supplier_or_origin VARCHAR(180) NOT NULL,
    document_number VARCHAR(120) NULL,
    received_at DATETIME NOT NULL,
    received_by_label VARCHAR(160) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_receipts_code (receipt_code),
    KEY idx_receipts_status_date (status, received_at),
    KEY idx_receipts_warehouse_date (warehouse_id, received_at),
    CONSTRAINT fk_receipts_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT chk_receipts_status
        CHECK (status IN ('draft', 'confirmed', 'cancelled')),
    CONSTRAINT chk_receipts_source_type
        CHECK (source_type IN ('purchase', 'donation', 'transfer', 'return', 'other'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_id BIGINT UNSIGNED NOT NULL,
    lot_code VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    received_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lots_receipt (receipt_id),
    UNIQUE KEY uq_lots_code (lot_code),
    KEY idx_lots_status_date (status, received_at),
    CONSTRAINT fk_lots_receipt
        FOREIGN KEY (receipt_id) REFERENCES receipts (id),
    CONSTRAINT chk_lots_status
        CHECK (status IN ('pending', 'received', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipt_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_id BIGINT UNSIGNED NOT NULL,
    lot_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_receipt_items_variant (receipt_id, product_variant_id),
    KEY idx_receipt_items_lot (lot_id),
    KEY idx_receipt_items_variant (product_variant_id),
    CONSTRAINT fk_receipt_items_receipt
        FOREIGN KEY (receipt_id) REFERENCES receipts (id),
    CONSTRAINT fk_receipt_items_lot
        FOREIGN KEY (lot_id) REFERENCES lots (id),
    CONSTRAINT fk_receipt_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT chk_receipt_items_quantity
        CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.3.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('005_receipts')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
