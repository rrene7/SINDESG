<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'index.php',
    'public/index.php',
    'public/assets/css/app.css',
    'public/assets/css/inventory.css',
    'public/assets/css/receipts.css',
    'public/assets/css/production.css',
    'public/assets/css/people.css',
    'public/assets/css/warehouses.css',
    'public/assets/css/requests.css',
    'public/assets/css/distribution.css',
    'public/assets/js/app.js',
    'public/assets/js/receipts.js',
    'public/assets/js/production.js',
    'public/assets/js/warehouses.js',
    'public/assets/js/requests.js',
    'public/assets/js/distribution.js',
    'public/api/employees.php',
    'public/api/units.php',
    'app/bootstrap.php',
    'app/Support/Database.php',
    'app/Controllers/InventoryController.php',
    'app/Controllers/ReceiptsController.php',
    'app/Controllers/ProductionController.php',
    'app/Controllers/PeopleController.php',
    'app/Controllers/WarehousesController.php',
    'app/Controllers/RequestsController.php',
    'app/Controllers/DistributionController.php',
    'app/Repositories/CatalogRepository.php',
    'app/Repositories/InventoryRepository.php',
    'app/Repositories/ReceiptRepository.php',
    'app/Repositories/ProductionRepository.php',
    'app/Repositories/DashboardRepository.php',
    'app/Repositories/RrhhRepository.php',
    'app/Repositories/WarehouseRepository.php',
    'app/Repositories/RequestRepository.php',
    'app/Repositories/DistributionRepository.php',
    'app/Services/CatalogService.php',
    'app/Services/ReceiptService.php',
    'app/Services/ProductionService.php',
    'app/Services/RrhhEmployeeDirectory.php',
    'app/Services/WarehouseService.php',
    'app/Services/RequestService.php',
    'app/Services/DistributionService.php',
    'app/Views/layout.php',
    'app/Views/dashboard.php',
    'app/Views/module.php',
    'app/Views/inventory/index.php',
    'app/Views/inventory/product-form.php',
    'app/Views/inventory/product-show.php',
    'app/Views/inventory/category-form.php',
    'app/Views/inventory/unavailable.php',
    'app/Views/receipts/index.php',
    'app/Views/receipts/form.php',
    'app/Views/receipts/show.php',
    'app/Views/receipts/unavailable.php',
    'app/Views/production/index.php',
    'app/Views/production/form.php',
    'app/Views/production/show.php',
    'app/Views/production/unavailable.php',
    'app/Views/people/index.php',
    'app/Views/people/show.php',
    'app/Views/people/unit.php',
    'app/Views/people/unavailable.php',
    'app/Views/warehouses/index.php',
    'app/Views/warehouses/form.php',
    'app/Views/warehouses/show.php',
    'app/Views/warehouses/unavailable.php',
    'app/Views/requests/index.php',
    'app/Views/requests/form.php',
    'app/Views/requests/show.php',
    'app/Views/requests/unavailable.php',
    'app/Views/distribution/index.php',
    'app/Views/distribution/form.php',
    'app/Views/distribution/show.php',
    'app/Views/distribution/unavailable.php',
    'config/app.php',
    'config/database.example.php',
    'database/schema/001_bootstrap.sql',
    'database/schema/002_catalog.sql',
    'database/schema/003_inventory_core.sql',
    'database/schema/004_category_codes.sql',
    'database/schema/005_receipts.sql',
    'database/schema/006_production.sql',
    'database/schema/007_dashboard_rrhh_receipts.sql',
    'database/schema/008_warehouses_p4.sql',
    'database/schema/009_requests.sql',
    'database/schema/010_distribution.sql',
    'database/schema/011_post_phase8_display.sql',
    'tests/catalog_schema_test.php',
    'tests/inventory_core_schema_test.php',
    'tests/category_auto_code_test.php',
    'tests/receipts_schema_test.php',
    'tests/receipts_logic_test.php',
    'tests/cli_bootstrap_test.php',
    'tests/product_base_variant_test.php',
    'tests/production_schema_test.php',
    'tests/production_logic_test.php',
    'tests/rrhh_receiver_test.php',
    'tests/dashboard_dynamic_test.php',
    'tests/utf8_repair_test.php',
    'tests/rrhh_people_module_test.php',
    'tests/rrhh_readonly_test.php',
    'tests/people_direction_office_test.php',
    'tests/warehouses_schema_test.php',
    'tests/warehouses_logic_test.php',
    'tests/warehouses_rrhh_test.php',
    'tests/requests_schema_test.php',
    'tests/requests_logic_test.php',
    'tests/requests_stock_test.php',
    'tests/distribution_schema_test.php',
    'tests/distribution_logic_test.php',
    'tests/distribution_accounting_test.php',
    'tests/post_phase8_display_test.php',
];

$errors = [];

foreach ($required as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Falta archivo requerido: {$file}";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[ERROR] {$error}" . PHP_EOL);
    }
    exit(1);
}

echo "[OK] Estructura base completa." . PHP_EOL;

if (in_array('--database', $argv, true)) {
    require $root . '/app/bootstrap.php';

    foreach (['sindesg', 'rrhh'] as $connection) {
        $status = $database->health($connection);
        echo sprintf(
            "[%s] %s: %s%s",
            $status['ok'] ? 'OK' : 'WARN',
            strtoupper($connection),
            $status['message'],
            PHP_EOL
        );
    }
}

echo "[OK] Verificación de SINDESG finalizada." . PHP_EOL;
