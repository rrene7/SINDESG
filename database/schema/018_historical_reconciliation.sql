-- SINDESG - Fase 13C
-- Conciliación documentada de incidencias históricas.
-- Esta fase solo corrige staging/snapshots históricos antes del archivado.
-- NO crea ni modifica existencias vivas ni movimientos de kardex.

SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS historical_import_resolutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    import_row_id BIGINT UNSIGNED NOT NULL,
    resolution_type VARCHAR(40) NOT NULL,
    official_code_snapshot VARCHAR(180) NOT NULL,

    original_initial_quantity DECIMAL(14,3) NULL,
    original_entries_quantity DECIMAL(14,3) NULL,
    original_exits_quantity DECIMAL(14,3) NULL,
    original_recalculated_quantity DECIMAL(14,3) NULL,

    resolved_initial_quantity DECIMAL(14,3) NULL,
    resolved_entries_quantity DECIMAL(14,3) NULL,
    resolved_exits_quantity DECIMAL(14,3) NULL,
    resolved_recalculated_quantity DECIMAL(14,3) NULL,

    source_reference VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    resolved_by_user_id BIGINT UNSIGNED NOT NULL,
    resolved_by_label_snapshot VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_historical_import_resolution_row (import_row_id),
    KEY idx_historical_import_resolution_batch (batch_id, id),

    CONSTRAINT fk_historical_import_resolution_batch
        FOREIGN KEY (batch_id) REFERENCES historical_import_batches (id),
    CONSTRAINT fk_historical_import_resolution_row
        FOREIGN KEY (import_row_id) REFERENCES historical_import_rows (id),
    CONSTRAINT fk_historical_import_resolution_user
        FOREIGN KEY (resolved_by_user_id) REFERENCES security_users (id),

    CONSTRAINT chk_historical_import_resolution_type
        CHECK (resolution_type IN ('normalized_source'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.13.0-c')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('018_historical_reconciliation')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
