<?php
declare(strict_types=1);

/**
 * Instala SOLO la tabla de asignación P4 en sindesg.
 * Requiere respaldo completo y verificado ANTES de cualquier cambio.
 * Uso: php scripts/apply_p4_scope.php --backup-dir="C:\\...\\sindesg_fecha"
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__);
$arg = null;
foreach ($argv as $item) {
    if (str_starts_with($item, '--backup-dir=')) {
        $arg = substr($item, strlen('--backup-dir='));
        break;
    }
}
if (!$arg || !is_dir($arg)) {
    throw new RuntimeException('Debe indicar --backup-dir con un respaldo SINDESG completo.');
}
$directory = realpath($arg);
if ($directory === false || !is_file($directory.'/BACKUP_COMPLETE')
    || !is_file($directory.'/manifest.json')
    || !is_file($directory.'/sindesg.sql')) {
    throw new RuntimeException('No existe respaldo SINDESG completo.');
}
$manifest = json_decode((string) file_get_contents($directory.'/manifest.json'), true);
if (!is_array($manifest)
    || ($manifest['database']['name'] ?? '') !== 'sindesg'
    || ($manifest['database']['rrhh_included'] ?? true) !== false) {
    throw new RuntimeException('El manifiesto de respaldo no corresponde a SINDESG.');
}
$expected = (string) ($manifest['files']['database_dump']['sha256'] ?? '');
$actual = hash_file('sha256', $directory.'/sindesg.sql');
if (!preg_match('/^[0-9a-f]{64}$/i', $expected) || !hash_equals($expected, (string) $actual)) {
    throw new RuntimeException('El SQL de respaldo no coincide con su SHA-256.');
}
require $root . '/app/bootstrap.php';
$pdo = $database->connection('sindesg');
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sindesg') {
    throw new RuntimeException('Prohibido aplicar migración en base distinta de sindesg.');
}
$required = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :migration');
$required->execute(['migration' => '019_p4_nominal_validation']);
if ((int) $required->fetchColumn() < 1) {
    throw new RuntimeException('Primero se requiere la migración 019 ya registrada.');
}
$sql = file_get_contents($root.'/database/schema/020_security_user_p4_scopes.sql');
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('No se encontró SQL de migración 020.');
}
$sql = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
foreach (preg_split('/;\s*(?:\r?\n|$)/', (string) $sql) ?: [] as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec(trim($statement));
    }
}
$check = $pdo->query("SHOW TABLES LIKE 'security_user_p4_scopes'")->fetchColumn();
$required->execute(['migration' => '020_security_user_p4_scopes']);
if (!$check || (int) $required->fetchColumn() !== 1) {
    throw new RuntimeException('Migración 020 incompleta: revisar SINDESG sin modificar RRHH.');
}
echo "[OK] Asignación de unidades P4 instalada en sindesg. RRHH2029 intacto.\n";
