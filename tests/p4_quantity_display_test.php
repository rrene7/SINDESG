<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/P4QuantityDisplay.php';
use App\Services\P4QuantityDisplay;

$cases = [
    [349.000, '349'],
    [1.000, '1'],
    [0.000, '0'],
    [349.500, '349,5'],
    [349.025, '349,025'],
    [1000.000, '1 000'],
    [1234.500, '1 234,5'],
];
foreach ($cases as [$quantity, $expected]) {
    $actual = P4QuantityDisplay::units($quantity);
    if ($actual !== $expected) {
        throw new RuntimeException("Expected {$expected} for {$quantity}, got {$actual}");
    }
}
$bootstrap = (string)file_get_contents(dirname(__DIR__) . '/app/bootstrap.php');
if (!str_contains($bootstrap, 'function sindesg_units(')) {
    throw new RuntimeException('Missing shared unit-formatting function.');
}
$view = (string)file_get_contents(dirname(__DIR__) . '/app/Views/dashboard-p4.php');
if (substr_count($view, 'P4QuantityDisplay::units(') !== 2
    || !str_contains($view, 'Unidades, no miles')) {
    throw new RuntimeException('P4 dashboard does not use readable unit formatting for metrics and stock rows.');
}
echo "[OK] P4 quantity display shows units without misleading decimal zeros.\n";
