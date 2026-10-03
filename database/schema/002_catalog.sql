-- SINDESG - Fase 2A
-- Catálogo maestro de artículos.
-- Requiere 001_bootstrap.sql aplicado previamente.

USE sindesg;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 900,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_code (code),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sizes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 900,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sizes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brands (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    internal_code VARCHAR(50) NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    sex VARCHAR(20) NOT NULL DEFAULT 'no_aplica',
    color VARCHAR(80) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_internal_code (internal_code),
    KEY idx_products_category_status (category_id, status),
    KEY idx_products_name (name),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    size_id BIGINT UNSIGNED NULL,
    brand_id BIGINT UNSIGNED NULL,
    internal_code VARCHAR(50) NULL,
    institutional_code VARCHAR(120) NULL,
    supplier_code VARCHAR(120) NULL,
    color VARCHAR(80) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_variants_internal_code (internal_code),
    KEY idx_product_variants_product_status (product_id, status),
    KEY idx_product_variants_institutional_code (institutional_code),
    KEY idx_product_variants_supplier_code (supplier_code),
    CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_product_variants_size FOREIGN KEY (size_id) REFERENCES sizes (id),
    CONSTRAINT fk_product_variants_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_images_product (product_id),
    KEY idx_product_images_primary (product_id, is_primary),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (code, slug, name, description, sort_order, is_active)
VALUES
    ('SUET', 'sueter', 'Suéter', 'Suéteres y prendas equivalentes registradas por Servicios Generales.', 10, 1),
    ('CAM', 'camisa', 'Camisa', 'Camisas de uniforme y otras camisas institucionales.', 20, 1),
    ('PANT', 'pantalon', 'Pantalón', 'Pantalones de uniforme y faena.', 30, 1),
    ('FALD', 'falda', 'Falda', 'Faldas de uniforme.', 40, 1),
    ('MNJ', 'camisa-mnj', 'Camisa MNJ', 'Camisas registradas para MNJ.', 50, 1),
    ('BOTA', 'botas', 'Botas', 'Botas y calzado operativo administrado por Servicios Generales.', 60, 1),
    ('GORR', 'gorras', 'Gorras', 'Gorras institucionales.', 70, 1),
    ('KEPI', 'kepis', 'Kepis', 'Kepis institucionales.', 80, 1),
    ('ACCE', 'accesorios', 'Accesorios', 'Accesorios y complementos de dotación.', 90, 1),
    ('TELA', 'telas-materiales', 'Telas y materiales', 'Telas y materiales utilizados, entre otros fines, en confección interna.', 100, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

INSERT INTO sizes (code, name, sort_order, is_active)
VALUES
    ('XS', 'XS', 10, 1),
    ('S', 'S', 20, 1),
    ('M', 'M', 30, 1),
    ('L', 'L', 40, 1),
    ('XL', 'XL', 50, 1),
    ('XXL', 'XXL', 60, 1),
    ('XXXL', 'XXXL', 70, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('schema_version', '0.2.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('002_catalog')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
