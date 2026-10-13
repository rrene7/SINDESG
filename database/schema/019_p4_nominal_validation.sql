-- SINDESG - Validación manual nominal P4
-- Solo tabla de decisiones en sindesg. RRHH2029 es SOLO LECTURA.
-- Las validaciones no son entregas ni movimientos.
SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS p4_nominal_validation_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    requisition VARCHAR(80) NOT NULL,
    excel_row INT UNSIGNED NOT NULL,
    excel_position VARCHAR(50) NOT NULL,
    excel_name VARCHAR(220) NOT NULL,
    excel_size VARCHAR(30) NOT NULL,
    rrhh_employee_id BIGINT UNSIGNED NOT NULL,
    rrhh_document_snapshot VARCHAR(60) NOT NULL,
    rrhh_position_snapshot VARCHAR(50) NOT NULL,
    rrhh_name_snapshot VARCHAR(220) NOT NULL,
    justification TEXT NOT NULL,
    validated_by_user_id BIGINT UNSIGNED NOT NULL,
    validated_by_label_snapshot VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_p4_nominal_validation_source
        (request_id, product_id, source_sha256, excel_row, id),
    CONSTRAINT fk_p4_nominal_validation_request FOREIGN KEY (request_id)
        REFERENCES service_requests(id),
    CONSTRAINT fk_p4_nominal_validation_product FOREIGN KEY (product_id)
        REFERENCES products(id),
    CONSTRAINT fk_p4_nominal_validation_user FOREIGN KEY (validated_by_user_id)
        REFERENCES security_users(id),
    CONSTRAINT chk_p4_nominal_validation_hash
        CHECK (CHAR_LENGTH(source_sha256) = 64),
    CONSTRAINT chk_p4_nominal_validation_reason
        CHECK (CHAR_LENGTH(justification) >= 15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration)
VALUES ('019_p4_nominal_validation')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
