<?php
declare(strict_types=1);

/**
 * Auditoría local de históricos SINDESG (solo lectura).
 *
 * Uso: php scripts/audit_historical_data.php
 *
 * Consulta exclusivamente la base SINDESG; no consulta ni modifica RRHH2029.
 * Los snapshots históricos no forman parte del stock operativo.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este diagnóstico solo puede ejecutarse por CLI." . PHP_EOL);
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$fmt = static fn (int $value): string => number_format($value, 0, '.', ',');
$quantity = static fn (float $value): string => number_format($value, 3, '.', ',');

try {
    $pdo = $database->connection('sindesg');

    $required = [
        'historical_import_batches',
        'historical_import_sheets',
        'historical_import_rows',
        'historical_stock_snapshots',
        'warehouses',
        'inventory_movements',
        'individual_deliveries',
    ];
    $existing = $pdo->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($required, array_map('strval', $existing));
    if ($missing !== []) {
        throw new RuntimeException(
            'Tablas SINDESG faltantes: ' . implode(', ', $missing)
            . '. Verifique las migraciones 001-018 antes de auditar.'
        );
    }

    $totalBatches = (int) $pdo->query(
        'SELECT COUNT(*) FROM historical_import_batches'
    )->fetchColumn();
    $totalSheets = (int) $pdo->query(
        'SELECT COUNT(*) FROM historical_import_sheets'
    )->fetchColumn();
    $totalRows = (int) $pdo->query(
        'SELECT COUNT(*) FROM historical_import_rows'
    )->fetchColumn();
    $totalSnapshots = (int) $pdo->query(
        'SELECT COUNT(*) FROM historical_stock_snapshots'
    )->fetchColumn();
    $totalMovements = (int) $pdo->query(
        'SELECT COUNT(*) FROM inventory_movements'
    )->fetchColumn();
    $totalDeliveries = (int) $pdo->query(
        'SELECT COUNT(*) FROM individual_deliveries'
    )->fetchColumn();

    $batchStatuses = ['staged' => 0, 'partially_imported' => 0, 'imported' => 0, 'cancelled' => 0];
    foreach ($pdo->query(
        'SELECT status, COUNT(*) AS total FROM historical_import_batches GROUP BY status'
    )->fetchAll() as $row) {
        $batchStatuses[(string) $row['status']] = (int) $row['total'];
    }

    $rowStatuses = ['ready' => 0, 'review' => 0, 'imported' => 0, 'skipped' => 0];
    foreach ($pdo->query(
        'SELECT row_status, COUNT(*) AS total FROM historical_import_rows GROUP BY row_status'
    )->fetchAll() as $row) {
        $rowStatuses[(string) $row['row_status']] = (int) $row['total'];
    }

    $profiles = [];
    foreach ($pdo->query(
        'SELECT row_profile, COUNT(*) AS total
         FROM historical_import_rows
         GROUP BY row_profile
         ORDER BY total DESC, row_profile'
    )->fetchAll() as $row) {
        $profiles[(string) $row['row_profile']] = (int) $row['total'];
    }

    $warehouseRows = $pdo->query(
        'SELECT w.warehouse_type, w.is_active, COUNT(DISTINCT w.id) AS warehouse_count,
                COALESCE(SUM(vs.quantity_on_hand), 0) AS stock_quantity
         FROM warehouses w
         LEFT JOIN v_inventory_stock vs ON vs.warehouse_id = w.id
         GROUP BY w.warehouse_type, w.is_active
         ORDER BY w.warehouse_type, w.is_active DESC'
    )->fetchAll();

    $batches = $pdo->query(
        'SELECT b.id, b.batch_code, b.source_filename, b.status,
                (SELECT COUNT(*) FROM historical_import_rows r WHERE r.batch_id = b.id
                 AND r.row_status = "ready") AS ready_rows,
                (SELECT COUNT(*) FROM historical_import_rows r WHERE r.batch_id = b.id
                 AND r.row_status = "review") AS review_rows,
                (SELECT COUNT(*) FROM historical_import_rows r WHERE r.batch_id = b.id
                 AND r.row_status = "imported") AS archived_rows,
                (SELECT COUNT(*) FROM historical_import_rows r WHERE r.batch_id = b.id
                 AND r.row_status = "skipped") AS reference_rows,
                (SELECT COUNT(*) FROM historical_stock_snapshots s WHERE s.batch_id = b.id
                ) AS snapshot_count
         FROM historical_import_batches b
         ORDER BY b.id DESC
         LIMIT 20'
    )->fetchAll();

    echo "SINDESG | AUDITORÍA DE HISTÓRICOS (SOLO LECTURA)" . PHP_EOL;
    echo str_repeat('=', 60) . PHP_EOL;
    echo 'Lotes históricos: ' . $fmt($totalBatches)
        . ' | Hojas: ' . $fmt($totalSheets)
        . ' | Filas: ' . $fmt($totalRows) . PHP_EOL;
    echo 'Estado lotes: archivados=' . $fmt($batchStatuses['imported'])
        . ', staging=' . $fmt($batchStatuses['staged'])
        . ', parciales=' . $fmt($batchStatuses['partially_imported'])
        . ', cancelados=' . $fmt($batchStatuses['cancelled']) . PHP_EOL;
    echo 'Estado filas: archivadas=' . $fmt($rowStatuses['imported'])
        . ', referencias=' . $fmt($rowStatuses['skipped'])
        . ', listas=' . $fmt($rowStatuses['ready'])
        . ', revision=' . $fmt($rowStatuses['review']) . PHP_EOL;
    echo 'Snapshots históricos: ' . $fmt($totalSnapshots)
        . ' (NO son existencias operativas)' . PHP_EOL;

    echo PHP_EOL . 'Perfiles detectados:' . PHP_EOL;
    foreach ($profiles as $name => $count) {
        echo '  ' . $name . ': ' . $fmt($count) . PHP_EOL;
    }
    if ($profiles === []) {
        echo '  Sin filas históricas.' . PHP_EOL;
    }

    echo PHP_EOL . 'Últimos lotes (máximo 20):' . PHP_EOL;
    foreach ($batches as $batch) {
        echo '  ' . $batch['batch_code'] . ' | ' . $batch['source_filename']
            . ' | ' . $batch['status'] . PHP_EOL;
        echo '    snapshots=' . $fmt((int) $batch['snapshot_count'])
            . ', archivadas=' . $fmt((int) $batch['archived_rows'])
            . ', referencias=' . $fmt((int) $batch['reference_rows'])
            . ', listas=' . $fmt((int) $batch['ready_rows'])
            . ', revision=' . $fmt((int) $batch['review_rows']) . PHP_EOL;
    }
    if ($batches === []) {
        echo '  No existen lotes en esta base SINDESG.' . PHP_EOL;
    }
    if ($totalBatches > 20) {
        echo '  Se muestran 20 de ' . $fmt($totalBatches) . ' lotes.' . PHP_EOL;
    }

    echo PHP_EOL . 'Inventario y P4 (KARDEX OPERATIVO):' . PHP_EOL;
    echo '  Movimientos: ' . $fmt($totalMovements)
        . ' | Entregas individuales: ' . $fmt($totalDeliveries) . PHP_EOL;
    foreach ($warehouseRows as $warehouse) {
        echo '  ' . $warehouse['warehouse_type']
            . ((int) $warehouse['is_active'] === 1 ? ' activo' : ' inactivo')
            . ': almacenes=' . $fmt((int) $warehouse['warehouse_count'])
            . ', stock=' . $quantity((float) $warehouse['stock_quantity']) . PHP_EOL;
    }

    echo PHP_EOL . 'INTERPRETACIÓN:' . PHP_EOL;
    if ($totalBatches === 0) {
        echo '  [ATENCION] No hay archivos historicos registrados en esta base.' . PHP_EOL;
    } elseif ($rowStatuses['ready'] > 0 || $rowStatuses['review'] > 0) {
        echo '  [PENDIENTE] Existen filas que requieren revision o archivado.' . PHP_EOL;
    } else {
        echo '  [OK] No hay filas pendientes de revision o archivado.' . PHP_EOL;
    }
    if ($totalSnapshots > 0 && $totalMovements === 0) {
        echo '  [ATENCION] Hay snapshots, pero no movimientos operativos.' . PHP_EOL;
    }
    echo '  Las existencias reales provienen de inventory_movements;' . PHP_EOL;
    echo '  los históricos no se convierten automaticamente en kardex.' . PHP_EOL;
    echo '  No se modificaron datos de SINDESG ni de RRHH2029.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] No fue posible completar la auditoría: '
        . $exception->getMessage() . PHP_EOL);
    exit(1);
}
