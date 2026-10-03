-- SINDESG - Fase 7
-- Solicitudes P4 -> Almacén Central.
-- Requiere Fases 1 a 6.
-- Solo modifica la base sindesg. RRHH2029 permanece en solo lectura.

SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS service_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_code VARCHAR(40) NOT NULL,
    p4_warehouse_id BIGINT UNSIGNED NOT NULL,
    requested_by_employee_id BIGINT UNSIGNED NOT NULL,
    requested_by_label_snapshot VARCHAR(180) NOT NULL,
    requested_by_document_snapshot VARCHAR(50) NULL,
    requested_by_position_snapshot VARCHAR(50) NULL,
    requested_by_rank_snapshot VARCHAR(120) NULL,
    requested_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    submitted_at DATETIME NULL,
    reviewed_by_employee_id BIGINT UNSIGNED NULL,
    reviewed_by_label_snapshot VARCHAR(180) NULL,
    reviewed_by_document_snapshot VARCHAR(50) NULL,
    reviewed_by_position_snapshot VARCHAR(50) NULL,
    reviewed_by_rank_snapshot VARCHAR(120) NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_requests_code (request_code),
    KEY idx_service_requests_p4_status (p4_warehouse_id, status),
    KEY idx_service_requests_status_date (status, requested_at),
    CONSTRAINT fk_service_requests_p4
        FOREIGN KEY (p4_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT chk_service_requests_status
        CHECK (status IN (
            'draft',
            'submitted',
            'in_review',
            'approved',
            'partially_approved',
            'rejected',
            'fulfilled',
            'closed'
        ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_request_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    beneficiary_employee_id BIGINT UNSIGNED NULL,
    beneficiary_label_snapshot VARCHAR(180) NULL,
    beneficiary_document_snapshot VARCHAR(50) NULL,
    beneficiary_position_snapshot VARCHAR(50) NULL,
    beneficiary_rank_snapshot VARCHAR(120) NULL,
    beneficiary_unit_id_snapshot BIGINT UNSIGNED NULL,
    beneficiary_unit_name_snapshot VARCHAR(220) NULL,
    quantity_requested DECIMAL(14,3) NOT NULL,
    quantity_approved DECIMAL(14,3) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_request_items_request (request_id),
    KEY idx_service_request_items_variant (product_variant_id),
    KEY idx_service_request_items_beneficiary (beneficiary_employee_id),
    CONSTRAINT fk_service_request_items_request
        FOREIGN KEY (request_id) REFERENCES service_requests (id),
    CONSTRAINT fk_service_request_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
    CONSTRAINT chk_service_request_items_requested
        CHECK (quantity_requested > 0),
    CONSTRAINT chk_service_request_items_approved
        CHECK (quantity_approved >= 0 AND quantity_approved <= quantity_requested)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.7.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('009_requests')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
