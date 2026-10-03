<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dashboardRepository = file_get_contents($root . '/app/Repositories/DashboardRepository.php');
$dashboard = file_get_contents($root . '/app/Views/dashboard.php');
$warehouseIndex = file_get_contents($root . '/app/Views/warehouses/index.php');
$warehouseShow = file_get_contents($root . '/app/Views/warehouses/show.php');
$warehouseRepository = file_get_contents($root . '/app/Repositories/WarehouseRepository.php');
$migration = file_get_contents($root . '/database/schema/011_post_phase8_display.sql');

foreach (compact('dashboardRepository','dashboard','warehouseIndex','warehouseShow','warehouseRepository','migration') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach (['stockBreakdownForProduct', "warehouse_type = 'CENTRAL'", "warehouse_type = 'P4'", "warehouse_type = 'TRANSIT'"] as $fragment) {
    if (!str_contains($dashboardRepository, $fragment)) {
        fwrite(STDERR, "[ERROR] Dashboard sin desglose: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['unidades institucionales', 'Central', 'P4', 'Tránsito'] as $fragment) {
    if (!str_contains($dashboard, $fragment)) {
        fwrite(STDERR, "[ERROR] Presentación de stock incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (["'TRANSIT'", "'TRÁNSITO'", 'Inventario temporal entre el Almacén Central y los P4'] as $fragment) {
    if (!str_contains($warehouseIndex, $fragment)) {
        fwrite(STDERR, "[ERROR] Tarjeta de tránsito incorrecta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['$isTransit', 'INVENTARIO EN TRÁNSITO', 'No es un P4 y no se vincula a RRHH'] as $fragment) {
    if (!str_contains($warehouseShow, $fragment)) {
        fwrite(STDERR, "[ERROR] Detalle de tránsito incorrecto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($warehouseRepository, "warehouse['warehouse_type'] !== 'P4'")) {
    fwrite(STDERR, "[ERROR] Activación/inactivación no está restringida a P4." . PHP_EOL);
    exit(1);
}

foreach (['CENTRAL-SG', '416C6D6163C3A96E', "VALUES ('011_post_phase8_display')"] as $fragment) {
    if (!str_contains($migration, $fragment)) {
        fwrite(STDERR, "[ERROR] Reparación UTF-8 incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Presentación post Fase 8 validada." . PHP_EOL;
