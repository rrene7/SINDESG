<?php
declare(strict_types=1);

/**
 * SINDESG - Reinicio único de transacciones ficticias en XAMPP local.
 *
 * POR DEFECTO: únicamente vista previa.
 * Para aplicar: --apply --confirm=RESET-FICTICIOS-LOCAL --backup-path="..."
 *
 * Protecciones:
 * - Solo configuración local y servidor de BD localhost.
 * - Fingerprint exacto de los datos ficticios auditados el 10/10/2026.
 * - Respaldo completo, reciente, verificado con SHA-256.
 * - Una transacción; rollback ante cualquier error.
 * - No altera P4, catálogo, seguridad, usuarios, ni migración histórica.
 * - Registra el reinicio excepcional de datos DEMO en audit_logs.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERROR] Solo CLI." . PHP_EOL);
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

const DEMO_TRANSACTION_TABLES = [
    'inventory_movements',
    'inventory_adjustments',
    'individual_delivery_items',
    'individual_deliveries',
    'distribution_items',
    'distributions',
    'service_request_items',
    'service_requests',
    'production_inputs',
    'production_outputs',
    'production_orders',
    'receipt_items',
    'lots',
    'receipts',
];

$apply = in_array('--apply', $argv, true);
$confirm = in_array('--confirm=RESET-FICTICIOS-LOCAL', $argv, true);
$backupPath = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backup-path=')) {
        $backupPath = substr($arg, strlen('--backup-path='));
    }
}

$fmt = static fn (int $n): string => number_format($n, 0, '.', ',');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$countsFor = static function (PDO $pdo): array {
    $result = [];
    foreach (DEMO_TRANSACTION_TABLES as $table) {
        $result[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    return $result;
};
$stockFor = static function (PDO $pdo): array {
    $result = ['CENTRAL' => 0.0, 'P4' => 0.0, 'TRANSIT' => 0.0];
    $rows = $pdo->query(
        'SELECT w.warehouse_type, COALESCE(SUM(v.quantity_on_hand), 0) AS qty
         FROM warehouses w LEFT JOIN v_inventory_stock v
             ON v.warehouse_id = w.id
         WHERE w.is_active = 1
         GROUP BY w.warehouse_type'
    )->fetchAll();
    foreach ($rows as $row) {
        $result[(string) $row['warehouse_type']] = (float) $row['qty'];
    }
    return $result;
};
$historyFor = static function (PDO $pdo): array {
    return [
        'batches' => (int) $pdo->query('SELECT COUNT(*) FROM historical_import_batches')->fetchColumn(),
        'closed' => (int) $pdo->query(
            "SELECT COUNT(*) FROM historical_import_batches WHERE status = 'imported'"
        )->fetchColumn(),
        'rows' => (int) $pdo->query('SELECT COUNT(*) FROM historical_import_rows')->fetchColumn(),
        'archived' => (int) $pdo->query(
            "SELECT COUNT(*) FROM historical_import_rows WHERE row_status = 'imported'"
        )->fetchColumn(),
        'references' => (int) $pdo->query(
            "SELECT COUNT(*) FROM historical_import_rows WHERE row_status = 'skipped'"
        )->fetchColumn(),
        'snapshots' => (int) $pdo->query(
            'SELECT COUNT(*) FROM historical_stock_snapshots'
        )->fetchColumn(),
    ];
};

try {
    $assert(($app['environment'] ?? '') === 'local',
        'Bloqueado: solo environment=local. No ejecutar en producción.');

    $db = $databaseConfig['sindesg'] ?? [];
    $host = strtolower((string) ($db['host'] ?? ''));
    $assert(in_array($host, ['127.0.0.1', 'localhost', '::1'], true),
        'Bloqueado: la base SINDESG debe residir en localhost.');
    $assert(strtolower((string) ($db['database'] ?? '')) === 'sindesg',
        'Bloqueado: base configurada distinta de sindesg.');

    $pdo = $database->connection('sindesg');
    $assert(strtolower((string) $pdo->query('SELECT DATABASE()')->fetchColumn()) === 'sindesg',
        'Bloqueado: conexión apuntando a otra base.');

    $requiredTables = array_merge(DEMO_TRANSACTION_TABLES, [
        'warehouses', 'historical_import_batches', 'historical_import_rows',
        'historical_stock_snapshots', 'audit_logs',
    ]);
    $existingTables = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($requiredTables, array_map('strval', $existingTables));
    $assert($missing === [], 'Tablas faltantes: ' . implode(', ', $missing));

    $before = $countsFor($pdo);
    $stock = $stockFor($pdo);
    $history = $historyFor($pdo);
    $expectedHistory = [
        'batches' => 1, 'closed' => 1, 'rows' => 7717,
        'archived' => 182, 'references' => 7535, 'snapshots' => 182,
    ];

    echo "SINDESG | REINICIO DE PRUEBAS LOCALES" . PHP_EOL;
    echo str_repeat('=', 58) . PHP_EOL;
    echo 'Histórico: ' . $fmt($history['rows']) . ' filas | '
        . $fmt($history['snapshots']) . ' snapshots (SE CONSERVAN)' . PHP_EOL;
    foreach ($before as $table => $total) {
        echo '  ' . $table . ': ' . $fmt($total) . PHP_EOL;
    }
    echo 'Stock operativo: central=' . ($stock['CENTRAL'] ?? 0)
        . ', P4=' . ($stock['P4'] ?? 0)
        . ', tránsito=' . ($stock['TRANSIT'] ?? 0) . PHP_EOL;

    $expectedDemo =
        $before['inventory_movements'] === 12
        && $before['individual_deliveries'] === 2
        && abs(($stock['CENTRAL'] ?? 0) - 18.0) < 0.0001
        && abs(($stock['P4'] ?? 0) - 1.0) < 0.0001
        && abs(($stock['TRANSIT'] ?? 0)) < 0.0001
        && $history === $expectedHistory;

    if (!$expectedDemo) {
        echo "[BLOQUEADO] Los datos actuales ya no coinciden exactamente con la auditoría." . PHP_EOL;
        echo "No se borrará nada. Revise si ya existen registros reales." . PHP_EOL;
        exit(2);
    }

    if (!$apply) {
        echo "[PREVIA] Coinciden los datos de prueba (12 movimientos, 2 entregas, 19 unidades)." . PHP_EOL;
        echo "No se modificó nada. Se conservarán los históricos y los P4." . PHP_EOL;
        exit(0);
    }
    $assert($confirm, 'Falta confirmación --confirm=RESET-FICTICIOS-LOCAL.');
    $assert(is_string($backupPath) && trim($backupPath) !== '',
        'Falta --backup-path con un respaldo creado ANTES de ejecutar.');

    $backupReal = realpath($backupPath);
    $expectedRoot = realpath(SINDESG_ROOT . '/storage/backups');
    $assert(is_string($backupReal) && is_string($expectedRoot)
        && str_starts_with(strtolower($backupReal), strtolower($expectedRoot . DIRECTORY_SEPARATOR)),
        'El respaldo debe estar en storage/backups de SINDESG.');
    $manifestFile = $backupReal . '/manifest.json';
    $assert(is_file($backupReal . '/BACKUP_COMPLETE') && is_file($manifestFile),
        'Falta manifiesto o marcador BACKUP_COMPLETE.');
    $assert(time() - (int) filemtime($manifestFile) >= 0
        && time() - (int) filemtime($manifestFile) <= 3600,
        'El respaldo debe haberse creado en la última hora.');

    $manifest = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
    $assert(($manifest['database']['name'] ?? '') === 'sindesg'
        && ($manifest['database']['rrhh_included'] ?? true) === false,
        'El respaldo no corresponde exclusivamente a sindesg.');
    foreach (['database_dump', 'uploads_archive'] as $key) {
        $part = $manifest['files'][$key] ?? null;
        $assert(is_array($part) && is_string($part['filename'] ?? null),
            'Manifiesto incompleto: ' . $key);
        $file = $backupReal . '/' . basename($part['filename']);
        $assert(is_file($file)
            && hash_equals(strtolower((string) ($part['sha256'] ?? '')),
                strtolower((string) hash_file('sha256', $file))),
            'SHA-256 del respaldo no coincide: ' . $key);
    }

    $pdo->beginTransaction();
    try {
        // Volvemos a comprobar antes de borrar, dentro de la transacción.
        $assert($countsFor($pdo) === $before
            && $stockFor($pdo) === $stock
            && $historyFor($pdo) === $history,
            'Los datos cambiaron durante la operación. No se borró nada.');

        // Orden hijo -> padre, con claves foráneas habilitadas.
        // ÚNICA excepción: reinicio de pruebas en entorno local NO productivo.
        foreach (DEMO_TRANSACTION_TABLES as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }

        $after = $countsFor($pdo);
        $assert(array_sum($after) === 0,
            'Persisten transacciones ficticias; revertiendo operación.');
        $remainingStock = $stockFor($pdo);
        foreach ($remainingStock as $quantity) {
            $assert(abs($quantity) < 0.0001,
                'Persiste stock operativo; revertiendo operación.');
        }
        $assert($historyFor($pdo) === $expectedHistory,
            'Cambió el histórico; se abortará la limpieza.');

        $details = json_encode([
            'reason' => 'Restablecimiento excepcional de pruebas ficticias en XAMPP local',
            'backed_up_at' => $manifest['created_at'] ?? null,
            'backup_folder' => basename($backupReal),
            'before_counts' => $before,
            'before_stock' => $stock,
            'historical_rows_preserved' => $history['rows'],
            'historical_snapshots_preserved' => $history['snapshots'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $log = $pdo->prepare(
            'INSERT INTO audit_logs (user_label_snapshot, module_code, action_code,
                record_type, record_id, details_json)
             VALUES (:actor, :module, :action, :type, :record, :details)'
        );
        $log->execute([
            'actor' => 'Mantenimiento local controlado',
            'module' => 'maintenance',
            'action' => 'reset_demo_local',
            'type' => 'dev_environment',
            'record' => 'TEST-DATA-20261010',
            'details' => $details,
        ]);

        $pdo->commit();
        echo "[OK] Registros operativos ficticios reiniciados." . PHP_EOL;
        echo "[OK] Inventario Central/P4/Tránsito: 0 / 0 / 0." . PHP_EOL;
        echo "[OK] 7,717 filas históricas, 182 snapshots, catálogo y P4 preservados." . PHP_EOL;
        echo "[OK] Acción registrada en auditoría y respaldo conservado." . PHP_EOL;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
