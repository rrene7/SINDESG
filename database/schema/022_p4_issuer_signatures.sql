-- SINDESG migration 022. RRHH2029 solo lectura.
USE sindesg;
CREATE TABLE IF NOT EXISTS p4_user_signatures (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 signature_png MEDIUMBLOB NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_p4_user_signature_user FOREIGN KEY(user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS p4_user_signature_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 previous_sha256 CHAR(64) NULL,
 current_sha256 CHAR(64) NOT NULL,
 changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_p4_signature_history_user(user_id),
 CONSTRAINT fk_p4_user_signature_history_user FOREIGN KEY(user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS individual_delivery_issuer_signatures (
 delivery_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 issuer_user_id BIGINT UNSIGNED NOT NULL,
 signature_png MEDIUMBLOB NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 captured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_p4_issuer_delivery FOREIGN KEY(delivery_id) REFERENCES individual_deliveries(id),
 CONSTRAINT fk_p4_issuer_user FOREIGN KEY(issuer_user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO schema_migrations(migration) VALUES ('022_p4_issuer_signatures')
 ON DUPLICATE KEY UPDATE migration=VALUES(migration);
