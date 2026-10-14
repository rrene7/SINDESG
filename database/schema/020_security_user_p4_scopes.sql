-- Proposed additive migration 020 for SINDESG only.
-- DO NOT apply without a verified SINDESG backup and deployment readiness.
-- RRHH2029 is strictly read-only.
USE sindesg;

CREATE TABLE IF NOT EXISTS security_user_p4_scopes (
    user_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, warehouse_id),
    KEY idx_security_p4_scope_warehouse (warehouse_id, user_id),
    CONSTRAINT fk_security_p4_scope_user
        FOREIGN KEY (user_id) REFERENCES security_users(id),
    CONSTRAINT fk_security_p4_scope_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No automatic assignments: admins must explicitly authorize each P4.
-- No updates or deletes to requests, deliveries, stock, kardex or RRHH.
