-- SINDESG - Fase 13B
-- Preparación controlada del catálogo maestro histórico.
-- Esta fase crea/actualiza únicamente datos maestros de catálogo.
-- NO crea existencias, entradas, salidas ni movimientos de kardex.

SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS historical_catalog_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_name VARCHAR(180) NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    source_key VARCHAR(80) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    metadata_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_historical_catalog_source_entity
        (source_name, entity_type, source_key),
    KEY idx_historical_catalog_target (entity_type, target_id),

    CONSTRAINT chk_historical_catalog_entity
        CHECK (entity_type IN ('category', 'subcategory', 'product', 'size', 'variant'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.13.0-b')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('017_historical_catalog_preparation')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
