<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$directory = file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');
$form = file_get_contents($root . '/app/Views/receipts/form.php');
$service = file_get_contents($root . '/app/Services/ReceiptService.php');
$migration = file_get_contents($root . '/database/schema/007_dashboard_rrhh_receipts.sql');

foreach (compact('directory', 'form', 'service', 'migration') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach (['employees e', 'employee_position_assignments', 'employee_unit_assignments', 'organization_units_current', 'rrhhRead'] as $fragment) {
    if (!str_contains($directory, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta integración RRHH: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'REPLACE '] as $forbidden) {
    if (stripos($directory, $forbidden) !== false) {
        fwrite(STDERR, "[ERROR] El directorio RRHH contiene una operación de escritura." . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($form, 'name="received_by_employee_id"')) {
    fwrite(STDERR, "[ERROR] Recepción no conserva employee_id." . PHP_EOL);
    exit(1);
}

if (!str_contains($service, 'findEligibleEmployee')) {
    fwrite(STDERR, "[ERROR] La selección de funcionario no se revalida contra RRHH." . PHP_EOL);
    exit(1);
}

foreach (['received_by_document_snapshot', 'received_by_position_snapshot', 'received_by_rank_snapshot', 'received_by_unit_name_snapshot'] as $column) {
    if (!str_contains($migration, $column)) {
        fwrite(STDERR, "[ERROR] Falta snapshot: {$column}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Receptor RRHH y snapshot de recepción validados." . PHP_EOL;
