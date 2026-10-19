-- SINDESG: firma institucional personal P4 y copia histórica por entrega.
-- NO modifica RRHH2029.
USE sindesg;
CREATE TABLE IF NOT EXISTS p4_user_signatures (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 signature_png MEDIUMBLOB NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_p4_signature_user FOREIGN KEY(user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS p4_user_signature_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 action_code VARCHAR(30) NOT NULL,
 changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_p4_signature_history_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE individual_delivery_receipts
 ADD COLUMN IF NOT EXISTS issuer_signature_png MEDIUMBLOB NULL,
 ADD COLUMN IF NOT EXISTS issuer_signature_sha256 CHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS issuer_user_id BIGINT UNSIGNED NULL;
INSERT INTO schema_migrations(migration) VALUES ('022_p4_issuer_signatures')
 ON DUPLICATE KEY UPDATE migration=VALUES(migration);
