<?php
declare(strict_types=1);

/**
 * Instalador aditivo 024: solo sindesg.warehouses.
 *
 * Comprobación: php scripts/install_p4_subordinate_warehouses.php
 * Aplicación:  php scripts/install_p4_subordinate_warehouses.php --apply --backup=RUTA_VERIFICADA
 */
require __DIR__ . '/lib/BackupSupport.php';
BackupSupport::requireCli();

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$pdo = $database->connection('sindesg');
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sindesg') {
    throw new RuntimeException('Migración bloqueada: la conexión no apunta a sindesg.');
}

$columns = [
    'p4_head_unit_id',
    'p4_head_unit_code_snapshot',
    'p4_head_unit_name_snapshot',
    'p4_head_unit_type_snapshot',
];
$verify = static function () use ($pdo, $columns): bool {
    $quoted = implode(', ', array_map(static fn (string $column): string => $pdo->quote($column), $columns));
    $rows = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'warehouses'
           AND column_name IN ($quoted)"
    )->fetchAll(PDO::FETCH_COLUMN);

    $marker = $pdo->query(
        "SELECT COUNT(*) FROM schema_migrations
         WHERE migration = '024_p4_subordinate_warehouses'"
    )->fetchColumn();

    return count(array_unique($rows)) === count($columns) && (int) $marker > 0;
};

if (BackupSupport::hasFlag($argv, 'apply')) {
    $backup = BackupSupport::option($argv, 'backup');
    if ($backup === null || trim($backup) === '') {
        throw new RuntimeException('Requiere --backup=RUTA de respaldo reciente y verificado.');
    }
    $manifest = BackupSupport::manifest(rtrim($backup, '/\\'));
    if (($manifest['database']['name'] ?? '') !== 'sindesg'
        || ($manifest['database']['rrhh_included'] ?? true) !== false) {
        throw new RuntimeException('El respaldo no corresponde exclusivamente a sindesg.');
    }

    $sql = file_get_contents($root . '/database/schema/024_p4_subordinate_warehouses.sql');
    if ($sql === false) {
        throw new RuntimeException('No existe la migración 024.');
    }

    // Archivo versionado, instrucciones controladas; nunca se ejecuta SQL externo.
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '' || strcasecmp($statement, 'USE sindesg') === 0) {
            continue;
        }
        $pdo->exec($statement);
    }
    if (!$verify()) {
        throw new RuntimeException('Migración 024 incompleta: revisar antes de operar P4.');
    }
    echo "[OK] Migración 024 aplicada. Cabeceras y dependencias P4 habilitadas." . PHP_EOL;
} elseif ($verify()) {
    echo "[OK] Migración 024 instalada. Sin cambios." . PHP_EOL;
} else {
    echo "[PENDIENTE] Faltan columnas o registro 024. No se modificó la base." . PHP_EOL;
    exit(1);
}
