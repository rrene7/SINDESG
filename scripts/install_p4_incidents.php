<?php
declare(strict_types=1);

/**
 * php scripts/install_p4_incidents.php --check
 * php scripts/install_p4_incidents.php --apply --confirm=APLICAR-023 --backup=<validated SINDESG backup directory>
 *
 * Never runs during web requests, never connects to RRHH2029,
 * never executes migrations automatically, never repairs partial DDL.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}
require __DIR__ . '/lib/BackupSupport.php';
$root = dirname(__DIR__);

try {
    $apply = in_array('--apply', $argv, true);
    if (!$apply && !in_array('--check', $argv, true)) {
        throw new RuntimeException('Use --check o --apply --confirm=APLICAR-023 --backup=<carpeta>.');
    }
    // Do not fall back to the example credentials when modifying an actual database.
    $dbConfig = BackupSupport::databaseConfig($root);
    require $root . '/app/bootstrap.php';

    $sqlPath = $root . '/database/schema/023_p4_incident_intake.sql';
    $sql = is_file($sqlPath) ? file_get_contents($sqlPath) : false;
    if (!is_string($sql)) {
        throw new RuntimeException('No se encontró el archivo SQL 023.');
    }
    $statements = \App\Services\P4IncidentMigrationPlan::statements($sql);

    $pdo = $database->connection('sindesg');
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
        || (string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sindesg') {
        throw new RuntimeException('La conexión no es MySQL/MariaDB en sindesg. Cancelado.');
    }
    $tableExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :name AND table_type = \'BASE TABLE\''
    );
    foreach ([
        'schema_migrations','warehouses','individual_deliveries',
        'individual_delivery_items','inventory_adjustments','security_user_p4_scopes'
    ] as $name) {
        $tableExists->execute(['name'=>$name]);
        if ((int) $tableExists->fetchColumn() !== 1) {
            throw new RuntimeException('Dependencia ausente: ' . $name . '. Sin cambios.');
        }
    }

    $columnType = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :name AND column_name = \'id\''
    );
    foreach (['warehouses','individual_delivery_items','inventory_adjustments'] as $name) {
        $columnType->execute(['name'=>$name]);
        if (!preg_match('/^bigint(?:\(\d+\))? unsigned$/i', (string) $columnType->fetchColumn())) {
            throw new RuntimeException('El tipo de clave no coincide en ' . $name . '. Sin cambios.');
        }
    }

    $tableExists->execute(['name'=>'p4_incident_cases']);
    $cases = (int) $tableExists->fetchColumn();
    $tableExists->execute(['name'=>'p4_incident_events']);
    $events = (int) $tableExists->fetchColumn();
    $marker = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :name');
    $marker->execute(['name'=>'023_p4_incident_intake']);
    $recorded = (int) $marker->fetchColumn();

    if ($cases === 1 && $events === 1 && $recorded === 1) {
        $idx = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name = \'p4_incident_cases\'
             AND index_name = \'uq_p4_incident_single_adjustment\' AND non_unique=0'
        );
        $idx->execute();
        if ((int) $idx->fetchColumn() !== 1) {
            throw new RuntimeException('Migración marcada como instalada, pero falta su índice único. Revisión manual.');
        }
        echo "[OK] Migración 023 ya registrada con tablas e índice único. No se ejecutó DDL.\n";
        exit(0);
    }
    if ($cases !== 0 || $events !== 0 || $recorded !== 0) {
        throw new RuntimeException('La migración 023 está parcialmente aplicada. Revisión manual, sin reintentos.');
    }

    echo "[OK] Base sindesg y dependencias comprobadas.\n";
    echo "[OK] SQL 023 normalizado para LF y CRLF; tres operaciones autorizadas.\n";
    echo "[OK] No hay tablas ni marcador de la migración 023.\n";

    if (!$apply) {
        echo "[OK] Comprobación de solo lectura terminada. Ningún dato fue modificado.\n";
        exit(0);
    }
    if (BackupSupport::option($argv, 'confirm') !== 'APLICAR-023') {
        throw new RuntimeException('Falta confirmación --confirm=APLICAR-023. Sin cambios.');
    }
    $backupDirectory = BackupSupport::option($argv, 'backup');
    if ($backupDirectory === null || !is_dir($backupDirectory)) {
        throw new RuntimeException('Indique la carpeta exacta del respaldo con --backup. Sin cambios.');
    }
    $manifest = BackupSupport::manifest($backupDirectory);
    if (($manifest['database']['name'] ?? '') !== 'sindesg'
        || ($manifest['database']['rrhh_included'] ?? true) !== false) {
        throw new RuntimeException('Respaldo no es exclusivamente SINDESG. Sin cambios.');
    }
    echo "[OK] Respaldo con hashes SHA-256 válidos: " . $backupDirectory . "\n";
    echo "[INFO] Ejecutando dos CREATE TABLE y el registro de migración.\n";
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    foreach (['p4_incident_cases','p4_incident_events'] as $name) {
        $tableExists->execute(['name'=>$name]);
        if ((int) $tableExists->fetchColumn() !== 1) {
            throw new RuntimeException('Falta tabla ' . $name . ' tras DDL. Revise el estado, NO reintente.');
        }
    }
    $marker->execute(['name'=>'023_p4_incident_intake']);
    if ((int) $marker->fetchColumn() !== 1) {
        throw new RuntimeException('Falta marcador 023. Revise el estado, NO reintente.');
    }
    echo "[OK] MIGRACIÓN 023 APLICADA: dos tablas y marcador verificados.\n";
    echo "[OK] No se ejecutaron cambios de movimientos, stock, entregas ni RRHH2029.\n";
} catch (\Throwable $error) {
    fwrite(STDERR, '[ERROR] ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
