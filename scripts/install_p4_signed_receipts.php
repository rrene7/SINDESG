<?php
declare(strict_types=1);
/**
 * Apply the additive receipt-evidence table only to SINDESG after a verified backup.
 * Usage: php scripts/install_p4_signed_receipts.php --check
 *        php scripts/install_p4_signed_receipts.php --apply
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI únicamente.\n");
    exit(1);
}
$apply = in_array('--apply', $argv, true);
require dirname(__DIR__) . '/app/bootstrap.php';
$pdo = $database->connection('sindesg');
$name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($name !== 'sindesg') {
    throw new RuntimeException('La conexión no apunta a sindesg; se cancela sin escribir.');
}
$existing = $pdo->query("SHOW TABLES LIKE 'individual_delivery_receipts'")->fetchColumn() !== false;
if (!$apply) {
    echo '[INFO] Base: sindesg | Tabla de firmas: ' . ($existing ? 'presente' : 'pendiente') . PHP_EOL;
    echo '[INFO] Use --apply únicamente después de completar un respaldo.' . PHP_EOL;
    exit(0);
}
if (!$existing) {
    $sql = (string) file_get_contents(dirname(__DIR__) . '/database/schema/021_p4_delivery_receipts.sql');
    if (!preg_match('/CREATE TABLE IF NOT EXISTS individual_delivery_receipts\\s*\\((.*?)\\) ENGINE=InnoDB/s', $sql, $match)) {
        throw new RuntimeException('Esquema esperado de firmas no encontrado.');
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS individual_delivery_receipts (' . $match[1] . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
$pdo->exec("INSERT INTO schema_migrations (migration) VALUES ('021_p4_delivery_receipts') ON DUPLICATE KEY UPDATE migration=VALUES(migration)");
$check = $pdo->query("SHOW COLUMNS FROM individual_delivery_receipts")->fetchAll(PDO::FETCH_COLUMN);
foreach (['delivery_id','recipient_employee_id','signature_png','signature_sha256','captured_by_user_id','captured_at'] as $column) {
    if (!in_array($column, $check, true)) throw new RuntimeException('Columna faltante: ' . $column);
}
echo "[OK] Tabla de evidencias P4 verificada exclusivamente en SINDESG.\n";
