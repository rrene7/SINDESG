-- SINDESG 023: recepción presencial de incidencias P4
-- ADITIVA. Requiere respaldo probado, autorización DBA y ejecución manual.
-- No altera existencias, kardex, movimientos o RRHH2029.
USE sindesg;
CREATE TABLE IF NOT EXISTS p4_incident_cases (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 case_code VARCHAR(32) NOT NULL,
 delivery_item_id BIGINT UNSIGNED NOT NULL,
 warehouse_id BIGINT UNSIGNED NOT NULL,
 recipient_employee_id BIGINT UNSIGNED NOT NULL,
 product_variant_id BIGINT UNSIGNED NOT NULL,
 case_type VARCHAR(32) NOT NULL,
 quantity DECIMAL(14,3) NOT NULL,
 physically_received TINYINT(1) NOT NULL DEFAULT 0,
 item_condition VARCHAR(24) NOT NULL DEFAULT 'not_present',
 reason VARCHAR(500) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'submitted',
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 reviewed_by_user_id BIGINT UNSIGNED NULL,
 closed_by_user_id BIGINT UNSIGNED NULL,
 linked_adjustment_id BIGINT UNSIGNED NULL,
 resolution_notes VARCHAR(1000) NULL,
 reviewed_at DATETIME NULL,
 closed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_p4_incident_case_code(case_code),
 UNIQUE KEY uq_p4_incident_single_adjustment(linked_adjustment_id),
 KEY idx_p4_incident_source(delivery_item_id, status),
 KEY idx_p4_incident_queue(status, created_at),
 KEY idx_p4_incident_scope(warehouse_id, created_at),
 CONSTRAINT fk_p4_incident_delivery_item FOREIGN KEY(delivery_item_id) REFERENCES individual_delivery_items(id),
 CONSTRAINT fk_p4_incident_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id),
 CONSTRAINT fk_p4_incident_adjustment FOREIGN KEY(linked_adjustment_id) REFERENCES inventory_adjustments(id),
 CONSTRAINT chk_p4_incident_case_type CHECK(case_type IN('return','exchange','deterioration','replacement','loss','other')),
 CONSTRAINT chk_p4_incident_status CHECK(status IN('submitted','in_review','resolved','rejected')),
 CONSTRAINT chk_p4_incident_quantity CHECK(quantity>0),
 CONSTRAINT chk_p4_incident_physical CHECK(physically_received IN(0,1)),
 CONSTRAINT chk_p4_incident_condition CHECK(item_condition IN('reusable','non_reusable','evaluation_pending','not_present'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS p4_incident_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 case_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 action_code VARCHAR(32) NOT NULL,
 note VARCHAR(1000) NULL,
 occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_p4_incident_event_history(case_id, occurred_at),
 CONSTRAINT fk_p4_incident_event_case FOREIGN KEY(case_id) REFERENCES p4_incident_cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(migration) VALUES('023_p4_incident_intake')
ON DUPLICATE KEY UPDATE migration=VALUES(migration);
