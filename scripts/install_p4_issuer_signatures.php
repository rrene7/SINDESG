<?php
declare(strict_types=1);
/** php scripts/install_p4_issuer_signatures.php --check | --apply
 * Always back up SINDESG before --apply.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$apply = in_array('--apply', $argv, true);
require dirname(__DIR__) . '/app/bootstrap.php';
$pdo = $database->connection('sindesg');
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sindesg') {
    throw new RuntimeException('Conexión no es sindesg. Cancelado.');
}
foreach (['security_users','individual_deliveries','individual_delivery_receipts','schema_migrations'] as $table) {
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
    $s->execute(['table'=>$table]);
    if (!(int)$s->fetchColumn()) throw new RuntimeException("Falta tabla requerida: $table. Sin cambios.");
}
$expected = [
    'p4_user_signatures' => ['user_id','signature_png','signature_sha256','updated_at'],
    'p4_user_signature_history' => ['id','user_id','previous_sha256','current_sha256','changed_at'],
    'individual_delivery_issuer_signatures' => ['delivery_id','issuer_user_id','signature_png','signature_sha256','captured_at'],
];
$definitions = [
'p4_user_signatures' => 'CREATE TABLE IF NOT EXISTS p4_user_signatures (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 signature_png MEDIUMBLOB NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_p4_user_signature_user FOREIGN KEY(user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
'p4_user_signature_history' => 'CREATE TABLE IF NOT EXISTS p4_user_signature_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 previous_sha256 CHAR(64) NULL,
 current_sha256 CHAR(64) NOT NULL,
 changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_p4_signature_history_user(user_id),
 CONSTRAINT fk_p4_user_signature_history_user FOREIGN KEY(user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
'individual_delivery_issuer_signatures' => 'CREATE TABLE IF NOT EXISTS individual_delivery_issuer_signatures (
 delivery_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 issuer_user_id BIGINT UNSIGNED NOT NULL,
 signature_png MEDIUMBLOB NOT NULL,
 signature_sha256 CHAR(64) NOT NULL,
 captured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_p4_issuer_delivery FOREIGN KEY(delivery_id) REFERENCES individual_deliveries(id),
 CONSTRAINT fk_p4_issuer_user FOREIGN KEY(issuer_user_id) REFERENCES security_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
foreach ($expected as $name => $columns) {
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
    $s->execute(['table'=>$name]);
    $present=(bool)$s->fetchColumn();
    if (!$apply) { echo "[INFO] $name: " . ($present?'presente':'pendiente') . PHP_EOL; continue; }
    $pdo->exec($definitions[$name]);
    $existingColumns=$pdo->query('SHOW COLUMNS FROM ' . $name)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $column) {
        if (!in_array($column,$existingColumns,true)) throw new RuntimeException("Columna faltante: $name.$column");
    }
}
if (!$apply) { echo "[INFO] Para aplicar --apply tras respaldo." . PHP_EOL; exit(0); }
$pdo->exec("INSERT INTO schema_migrations(migration)
 VALUES ('022_p4_issuer_signatures') ON DUPLICATE KEY UPDATE migration=VALUES(migration)");
echo "[OK] Evidencias P4 listas en sindesg. RRHH2029 intacta." . PHP_EOL;
