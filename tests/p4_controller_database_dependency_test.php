<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$controllers = [
    'DistributionController',
    'RequestsController',
    'DeliveryController',
    'WarehousesController',
];
foreach ($controllers as $controller) {
    $source = (string) file_get_contents($root . '/app/Controllers/' . $controller . '.php');
    if (str_contains($source, '$this->database')
        && !preg_match('/function __construct\s*\(\s*private\s+(?:readonly\s+)?Database\s+\$database\b/', $source)
        && !preg_match('/private\s+Database\s+\$database\s*;/', $source)) {
        throw new RuntimeException($controller . ' uses an uninitialized Database property.');
    }
}
echo "[OK] All scoped P4 controllers retain the Database dependency.\n";
