-- SINDESG - Fase 12
-- Seguridad, roles, permisos, sesiones lógicas y auditoría.
-- RRHH2029 permanece en solo lectura.

SET NAMES utf8mb4;
USE sindesg;

CREATE TABLE IF NOT EXISTS security_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name_snapshot VARCHAR(180) NOT NULL,
    document_snapshot VARCHAR(50) NULL,
    position_snapshot VARCHAR(50) NULL,
    rank_snapshot VARCHAR(120) NULL,
    direction_snapshot VARCHAR(220) NULL,
    office_snapshot VARCHAR(220) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_security_users_employee (employee_id),
    UNIQUE KEY uq_security_users_username (username),
    KEY idx_security_users_status (status),

    CONSTRAINT chk_security_users_status
        CHECK (status IN ('active', 'disabled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_security_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(140) NOT NULL,
    module_code VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_security_permissions_code (code),
    KEY idx_security_permissions_module (module_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_security_user_roles_user
        FOREIGN KEY (user_id) REFERENCES security_users (id),
    CONSTRAINT fk_security_user_roles_role
        FOREIGN KEY (role_id) REFERENCES security_roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_security_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES security_roles (id),
    CONSTRAINT fk_security_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES security_permissions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    user_label_snapshot VARCHAR(180) NULL,
    module_code VARCHAR(60) NOT NULL,
    action_code VARCHAR(100) NOT NULL,
    record_type VARCHAR(80) NULL,
    record_id VARCHAR(80) NULL,
    details_json LONGTEXT NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_logs_created (created_at),
    KEY idx_audit_logs_user (user_id, created_at),
    KEY idx_audit_logs_module_action (module_code, action_code, created_at),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES security_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO security_roles (code, name, description, is_system)
VALUES
    ('ADMIN', 'Administrador SINDESG', 'Control total del sistema, usuarios, permisos y auditoría.', 1),
    ('CENTRAL', 'Almacén Central', 'Operación integral del Almacén Central y distribución institucional.', 1),
    ('P4', 'P4', 'Solicitudes, recepción en P4, despacho individual y consulta.', 1),
    ('AUDITOR', 'Auditor', 'Consulta de reportes, inventario, funcionarios y auditoría.', 1),
    ('VIEWER', 'Consulta', 'Consulta operativa sin acciones de modificación.', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT INTO security_permissions (code, name, module_code, description)
VALUES
    ('dashboard.view', 'Ver dashboard', 'dashboard', 'Acceso al dashboard principal.'),
    ('inventory.view', 'Ver inventario', 'inventory', 'Consulta catálogo, existencias y kardex.'),
    ('inventory.manage', 'Gestionar inventario', 'inventory', 'Crear/editar catálogo y operaciones permitidas.'),
    ('receipts.view', 'Ver recepciones', 'receipts', 'Consulta recepciones y lotes.'),
    ('receipts.manage', 'Gestionar recepciones', 'receipts', 'Crear y confirmar recepciones.'),
    ('production.view', 'Ver producción', 'production', 'Consulta producción interna.'),
    ('production.manage', 'Gestionar producción', 'production', 'Crear y confirmar producción interna.'),
    ('people.view', 'Ver funcionarios', 'people', 'Consulta RRHH2029 desde SINDESG.'),
    ('warehouses.view', 'Ver almacenes y P4', 'warehouses', 'Consulta red de almacenes.'),
    ('warehouses.manage', 'Gestionar almacenes y P4', 'warehouses', 'Habilitar y actualizar P4.'),
    ('requests.view', 'Ver solicitudes', 'requests', 'Consulta solicitudes.'),
    ('requests.manage', 'Gestionar solicitudes', 'requests', 'Crear, revisar y aprobar solicitudes.'),
    ('distribution.view', 'Ver distribución', 'distribution', 'Consulta transferencias Central → P4.'),
    ('distribution.manage', 'Gestionar distribución', 'distribution', 'Preparar, autorizar, despachar y recibir transferencias.'),
    ('deliveries.view', 'Ver despachos', 'deliveries', 'Consulta entregas individuales.'),
    ('deliveries.manage', 'Gestionar despachos', 'deliveries', 'Crear y confirmar entregas individuales.'),
    ('adjustments.view', 'Ver ajustes', 'adjustments', 'Consulta devoluciones, cambios, bajas y ajustes.'),
    ('adjustments.manage', 'Gestionar ajustes', 'adjustments', 'Crear y confirmar movimientos compensatorios.'),
    ('reports.view', 'Ver reportes', 'reports', 'Consulta y exportación de reportes.'),
    ('audit.view', 'Ver auditoría', 'audit', 'Consulta de acciones auditadas.'),
    ('admin.manage', 'Administrar seguridad', 'admin', 'Usuarios, roles, permisos y parámetros.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module_code = VALUES(module_code),
    description = VALUES(description);

-- ADMIN recibe todos los permisos.
INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
CROSS JOIN security_permissions p
WHERE r.code = 'ADMIN';

-- CENTRAL.
INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
INNER JOIN security_permissions p ON p.code IN (
    'dashboard.view',
    'inventory.view','inventory.manage',
    'receipts.view','receipts.manage',
    'production.view','production.manage',
    'people.view',
    'warehouses.view',
    'requests.view','requests.manage',
    'distribution.view','distribution.manage',
    'deliveries.view','deliveries.manage',
    'adjustments.view','adjustments.manage',
    'reports.view'
)
WHERE r.code = 'CENTRAL';

-- P4.
INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
INNER JOIN security_permissions p ON p.code IN (
    'dashboard.view',
    'inventory.view',
    'people.view',
    'warehouses.view',
    'requests.view','requests.manage',
    'distribution.view','distribution.manage',
    'deliveries.view','deliveries.manage',
    'adjustments.view','adjustments.manage',
    'reports.view'
)
WHERE r.code = 'P4';

-- AUDITOR.
INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
INNER JOIN security_permissions p ON p.code IN (
    'dashboard.view',
    'inventory.view',
    'receipts.view',
    'production.view',
    'people.view',
    'warehouses.view',
    'requests.view',
    'distribution.view',
    'deliveries.view',
    'adjustments.view',
    'reports.view',
    'audit.view'
)
WHERE r.code = 'AUDITOR';

-- VIEWER.
INSERT IGNORE INTO security_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM security_roles r
INNER JOIN security_permissions p ON p.code IN (
    'dashboard.view',
    'inventory.view',
    'receipts.view',
    'production.view',
    'people.view',
    'warehouses.view',
    'requests.view',
    'distribution.view',
    'deliveries.view',
    'adjustments.view',
    'reports.view'
)
WHERE r.code = 'VIEWER';

INSERT INTO system_settings (setting_key, setting_value)
VALUES
    ('security.session_idle_minutes', '120'),
    ('security.password_min_length', '12'),
    ('schema_version', '0.12.0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO schema_migrations (migration)
VALUES ('015_security_audit')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
