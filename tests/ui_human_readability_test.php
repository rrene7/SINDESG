<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$cases = [
    [1.000, '1'],
    [1000.000, '1 000'],
    [1234.500, '1 234,5'],
    [0.125, '0,125'],
    [0.000, '0'],
];
foreach ($cases as [$raw, $expected]) {
    if (sindesg_units($raw) !== $expected) {
        throw new RuntimeException('Unit presentation regression for ' . $raw);
    }
}
$row = [
    'quantity' => '1.000', 'warehouse_code' => 'P4-000006',
    'warehouse_name' => '16 Zona Policial de Pacora',
    'variant_code' => 'SND-V-0000193', 'size_name' => '9.5',
    'product_name' => 'Botas Tácticas',
    'request_code' => 'SOL-000004'
];
if (sindesg_report_value($row, 'quantity') !== '1'
    || sindesg_report_value($row, 'warehouse_code') !== '16 Zona Policial de Pacora'
    || sindesg_report_value($row, 'variant_code') !== 'Talla 9.5'
    || str_contains(sindesg_report_value($row, 'request_code'), 'SOL-')) {
    throw new RuntimeException('Report still exposes technical codes instead of human labels.');
}
$root = dirname(__DIR__);
foreach ([
    'app/Views/deliveries/show.php',
    'app/Views/requests/show.php',
    'app/Views/distribution/show.php',
    'app/Views/receipts/show.php',
    'app/Views/warehouses/show.php',
    'app/Views/production/show.php',
    'app/Views/adjustments/show.php',
    'app/Views/reports/index.php',
    'app/Views/reports/print.php'
] as $viewPath) {
    $view = (string)file_get_contents($root . '/' . $viewPath);
    if (!str_contains($view, 'sindesg_units(')
        && !str_contains($view, 'sindesg_report_value(')) {
        throw new RuntimeException('Readable quantities not enabled: ' . $viewPath);
    }
}
$printedReport = (string)file_get_contents($root . '/app/Views/reports/print.php');
$liveReport = (string)file_get_contents($root . '/app/Views/reports/index.php');
if (!str_contains($printedReport, 'sindesg_report_value(')
    || !str_contains($liveReport, 'sindesg_report_value(')) {
    throw new RuntimeException('Printable or live reports bypass human labels.');
}
$repository = (string)file_get_contents($root . '/app/Repositories/ReportRepository.php');
if (!str_contains($repository, "'variant_code' => 'Variante'")
    || !str_contains($repository, 'pv.internal_code AS variant_code')) {
    throw new RuntimeException('Audit/report internal references cannot be discarded.');
}
echo "[OK] Human labels and exact physical units; internal identifiers preserved.\n";
