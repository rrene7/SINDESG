-- SINDESG - Evidencia de recibido P4 (migración manual, con respaldo y verificación).
-- Solo SINDESG. RRHH2029 permanece estrictamente en lectura.
USE sindesg;
CREATE TABLE IF NOT EXISTS individual_delivery_receipts (
 delivery_id BIGINT UNSIGNED NOT NULL,
 recipient_employee_id BIGINT UNSIGNED NOT NULL,
 signature_png MEDIUMBLOB NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 captured_by_user_id BIGINT UNSIGNED NOT NULL,
 captured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 signature_method VARCHAR(30) NOT NULL DEFAULT 'touch_or_mouse',
 PRIMARY KEY(delivery_id),
 CONSTRAINT fk_idr_delivery FOREIGN KEY(delivery_id) REFERENCES individual_deliveries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO schema_migrations (migration) VALUES ('021_p4_delivery_receipts')
 ON DUPLICATE KEY UPDATE migration=VALUES(migration);
