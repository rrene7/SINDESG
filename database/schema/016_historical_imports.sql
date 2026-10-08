-- SINDESG - Fase 13A
-- Staging y migración controlada de históricos.
-- Ninguna tabla de esta fase modifica el kardex operativo.

SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS historical_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_code VARCHAR(40) NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    file_extension VARCHAR(10) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
    uploaded_by_label_snapshot VARCHAR(180) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'staged',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_historical_import_batches_code (batch_code),
    UNIQUE KEY uq_historical_import_batches_hash (file_sha256),
    KEY idx_historical_import_batches_status (status, created_at),

    CONSTRAINT fk_historical_import_batches_user
        FOREIGN KEY (uploaded_by_user_id) REFERENCES security_users (id),

    CONSTRAINT chk_historical_import_batches_status
        CHECK (status IN ('staged', 'partially_imported', 'imported', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historical_import_sheets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    sheet_name VARCHAR(180) NOT NULL,
    profile VARCHAR(80) NOT NULL DEFAULT 'unclassified',
    header_row_number INT UNSIGNED NULL,
    total_source_rows INT UNSIGNED NOT NULL DEFAULT 0,
    staged_rows INT UNSIGNED NOT NULL DEFAULT 0,
    ready_rows INT UNSIGNED NOT NULL DEFAULT 0,
    review_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_historical_import_sheets_batch (batch_id, id),
    KEY idx_historical_import_sheets_profile (profile),

    CONSTRAINT fk_historical_import_sheets_batch
        FOREIGN KEY (batch_id) REFERENCES historical_import_batches (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historical_import_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    sheet_id BIGINT UNSIGNED NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    row_profile VARCHAR(80) NOT NULL DEFAULT 'unclassified',
    raw_json LONGTEXT NOT NULL,

    normalized_code VARCHAR(180) NULL,
    normalized_description VARCHAR(255) NULL,
    normalized_product VARCHAR(180) NULL,
    normalized_category VARCHAR(180) NULL,
    normalized_size VARCHAR(80) NULL,
    normalized_sex VARCHAR(40) NULL,

    normalized_initial_quantity DECIMAL(14,3) NULL,
    normalized_entries_quantity DECIMAL(14,3) NULL,
    normalized_exits_quantity DECIMAL(14,3) NULL,
    normalized_quantity DECIMAL(14,3) NULL,

    mapping_variant_id BIGINT UNSIGNED NULL,
    row_status VARCHAR(20) NOT NULL DEFAULT 'review',
    issues_json LONGTEXT NULL,
    imported_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_historical_import_row_source (sheet_id, source_row_number),
    KEY idx_historical_import_rows_batch_status (batch_id, row_status),
    KEY idx_historical_import_rows_sheet_status (sheet_id, row_status),
    KEY idx_historical_import_rows_code (normalized_code),
    KEY idx_historical_import_rows_variant (mapping_variant_id),

    CONSTRAINT fk_historical_import_rows_batch
        FOREIGN KEY (batch_id) REFERENCES historical_import_batches (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_historical_import_rows_sheet
        FOREIGN KEY (sheet_id) REFERENCES historical_import_sheets (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_historical_import_rows_variant
        FOREIGN KEY (mapping_variant_id) REFERENCES product_variants (id),

    CONSTRAINT chk_historical_import_rows_status
        CHECK (row_status IN ('ready', 'review', 'imported', 'skipped'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historical_stock_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    import_row_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    source_sheet VARCHAR(180) NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    official_code_snapshot VARCHAR(180) NULL,
    description_snapshot VARCHAR(255) NULL,
    size_snapshot VARCHAR(80) NULL,
    initial_quantity DECIMAL(14,3) NULL,
    entries_quantity DECIMAL(14,3) NULL,
    exits_quantity DECIMAL(14,3) NULL,
    recalculated_quantity DECIMAL(14,3) NOT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_historical_stock_snapshot_row (import_row_id),
    KEY idx_historical_stock_snapshot_batch (batch_id),
    KEY idx_historical_stock_snapshot_variant (product_variant_id),

    CONSTRAINT fk_historical_stock_snapshot_batch
        FOREIGN KEY (batch_id) REFERENCES historical_import_batches (id),
    CONSTRAINT fk_historical_stock_snapshot_row
        FOREIGN KEY (import_row_id) REFERENCES historical_import_rows (id),
    CONSTRAINT fk_historical_stock_snapshot_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO security_permissions (code, name, module_code, description)
VALUES
    ('migration.view', 'Ver migración histórica', 'migration', 'Consulta lotes, hojas, filas e incidencias históricas.'),
    ('migration.manage', 'Gestionar migración histórica', 'migration', 'Cargar archivos y confirmar archivado histórico.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module_code = VALUES(module_code),
    description = VALUES(description);

INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
INNER JOIN security_permissions p
    ON p.code IN ('migration.view', 'migration.manage')
WHERE r.code IN ('ADMIN', 'CENTRAL');

INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
INNER JOIN security_permissions p
    ON p.code = 'migration.view'
WHERE r.code IN ('AUDITOR', 'VIEWER');

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.13.0-a')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('016_historical_imports')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
