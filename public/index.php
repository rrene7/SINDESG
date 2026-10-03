<?php
declare(strict_types=1);

use App\Controllers\InventoryController;
use App\Controllers\ReceiptsController;
use App\Controllers\ProductionController;
use App\Controllers\PeopleController;
use App\Controllers\WarehousesController;
use App\Controllers\RequestsController;
use App\Controllers\DistributionController;
use App\Repositories\CatalogRepository;
use App\Repositories\DashboardRepository;

require dirname(__DIR__) . '/app/bootstrap.php';

$modules = [
    'inventory' => [
        'label' => 'Inventario',
        'short' => 'Inventario',
        'icon' => 'IN',
        'phase' => 'Fase 2',
        'description' => 'Catálogo, existencias, tallas, lotes y movimientos por almacén.',
    ],
    'receipts' => [
        'label' => 'Recepción de lotes',
        'short' => 'Recepción',
        'icon' => 'RL',
        'phase' => 'Fase 3',
        'description' => 'Ingreso por compra, transferencia, donación, devolución u otro origen autorizado.',
    ],
    'production' => [
        'label' => 'Producción interna',
        'short' => 'Producción',
        'icon' => 'PI',
        'phase' => 'Fase 4',
        'description' => 'Registro de uniformes y artículos confeccionados dentro de Servicios Generales.',
    ],
    'requests' => [
        'label' => 'Solicitudes',
        'short' => 'Solicitudes',
        'icon' => 'SO',
        'phase' => 'Fase 7',
        'description' => 'Necesidades enviadas por Direcciones, Zonas y P4 con aprobación parcial o total.',
    ],
    'distribution' => [
        'label' => 'Distribución a P4',
        'short' => 'Distribución',
        'icon' => 'DP',
        'phase' => 'Fase 8',
        'description' => 'Transferencias desde el Almacén Central hacia los almacenes/P4.',
    ],
    'deliveries' => [
        'label' => 'Despacho individual',
        'short' => 'Despacho',
        'icon' => 'DI',
        'phase' => 'Fase 9',
        'description' => 'Entrega final al funcionario y conservación del historial de dotación.',
    ],
    'warehouses' => [
        'label' => 'Almacenes y P4',
        'short' => 'P4',
        'icon' => 'P4',
        'phase' => 'Fase 6',
        'description' => 'Configuración de almacén central y P4 vinculados con la estructura oficial.',
    ],
    'people' => [
        'label' => 'Funcionarios',
        'short' => 'Funcionarios',
        'icon' => 'RH',
        'phase' => 'Fase 5',
        'description' => 'Consulta de personal y adscripción vigente desde RRHH2029 en modo lectura.',
    ],
    'reports' => [
        'label' => 'Reportes',
        'short' => 'Reportes',
        'icon' => 'RE',
        'phase' => 'Fase 11',
        'description' => 'Inventario, entregas, transferencias, lotes, diferencias y rendición.',
    ],
    'audit' => [
        'label' => 'Auditoría',
        'short' => 'Auditoría',
        'icon' => 'AU',
        'phase' => 'Fase 12',
        'description' => 'Trazabilidad de acciones críticas, usuarios, cambios y movimientos.',
    ],
];

$fallbackCategories = [
    ['id' => 0, 'name' => 'Suéter', 'code' => 'CAT', 'slug' => 'sueter'],
    ['id' => 0, 'name' => 'Camisa', 'code' => 'CAT', 'slug' => 'camisa'],
    ['id' => 0, 'name' => 'Pantalón', 'code' => 'CAT', 'slug' => 'pantalon'],
    ['id' => 0, 'name' => 'Falda', 'code' => 'CAT', 'slug' => 'falda'],
    ['id' => 0, 'name' => 'Camisa MNJ', 'code' => 'CAT', 'slug' => 'camisa-mnj'],
    ['id' => 0, 'name' => 'Botas', 'code' => 'CAT', 'slug' => 'botas'],
    ['id' => 0, 'name' => 'Gorras', 'code' => 'CAT', 'slug' => 'gorras'],
    ['id' => 0, 'name' => 'Kepis', 'code' => 'CAT', 'slug' => 'kepis'],
];

$health = [
    'sindesg' => $database->health('sindesg'),
    'rrhh' => $database->health('rrhh'),
];

$categories = $fallbackCategories;

if ($health['sindesg']['ok']) {
    try {
        $catalogRepository = new CatalogRepository($database->connection('sindesg'));
        $databaseCategories = $catalogRepository->listCategories();

        if ($databaseCategories !== []) {
            $categories = $databaseCategories;
        }
    } catch (\Throwable $exception) {
        $categories = $fallbackCategories;
    }
}

$currentModule = $_GET['module'] ?? 'dashboard';
if ($currentModule !== 'dashboard' && !isset($modules[$currentModule])) {
    http_response_code(404);
    $currentModule = 'not-found';
}

if ($currentModule === 'dashboard') {
    $pageTitle = 'Dashboard';
    $dashboardCategories = [];
    $featuredProduct = null;
    $featuredSizes = [];
    $featuredStockBreakdown = ['central' => 0.0, 'p4' => 0.0, 'transit' => 0.0];
    $recentDashboardReceipts = [];

    if ($health['sindesg']['ok']) {
        try {
            $dashboardRepository = new DashboardRepository($database->connection('sindesg'));
            $dashboardCategories = $dashboardRepository->categoryCards();
            $featuredProduct = $dashboardRepository->featuredProduct();
            $featuredSizes = $featuredProduct
                ? $dashboardRepository->sizesForProduct((int) $featuredProduct['id'])
                : [];
            $featuredStockBreakdown = $featuredProduct
                ? $dashboardRepository->stockBreakdownForProduct((int) $featuredProduct['id'])
                : ['central' => 0.0, 'p4' => 0.0, 'transit' => 0.0];
            $recentDashboardReceipts = $dashboardRepository->recentReceipts(4);
        } catch (\Throwable $exception) {
            $dashboardCategories = [];
            $featuredProduct = null;
            $featuredSizes = [];
            $featuredStockBreakdown = ['central' => 0.0, 'p4' => 0.0, 'transit' => 0.0];
            $recentDashboardReceipts = [];
        }
    }

    $contentView = dirname(__DIR__) . '/app/Views/dashboard.php';
} elseif ($currentModule === 'inventory') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Inventario';
        $catalogError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/inventory/unavailable.php';
    } else {
        $controller = new InventoryController($database->connection('sindesg'));
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'receipts') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Recepción de lotes';
        $receiptError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/receipts/unavailable.php';
    } else {
        $controller = new ReceiptsController($database->connection('sindesg'), $database);
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET,
            $_POST,
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'production') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Producción interna';
        $productionError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/production/unavailable.php';
    } else {
        $controller = new ProductionController($database->connection('sindesg'));
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET,
            $_POST,
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'distribution') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Distribución a P4';
        $distributionError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/distribution/unavailable.php';
    } else {
        $controller = new DistributionController($database);
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET,
            $_POST,
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'requests') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Solicitudes';
        $requestError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/requests/unavailable.php';
    } else {
        $controller = new RequestsController($database);
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET,
            $_POST,
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'warehouses') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Almacenes y P4';
        $warehouseError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/warehouses/unavailable.php';
    } else {
        $controller = new WarehousesController($database);
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET,
            $_POST,
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'people') {
    if (!$health['rrhh']['ok']) {
        $pageTitle = 'Funcionarios';
        $peopleError = 'No existe conexión de solo lectura con RRHH2029. Verifique config/database.php y MariaDB.';
        $contentView = dirname(__DIR__) . '/app/Views/people/unavailable.php';
    } else {
        $controller = new PeopleController($database);
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'not-found') {
    $pageTitle = 'Módulo no encontrado';
    $contentView = dirname(__DIR__) . '/app/Views/module.php';
    $module = [
        'label' => 'Módulo no encontrado',
        'icon' => '!',
        'phase' => '—',
        'description' => 'La ruta solicitada no existe dentro de SINDESG.',
    ];
} else {
    $module = $modules[$currentModule];
    $pageTitle = $module['label'];
    $contentView = dirname(__DIR__) . '/app/Views/module.php';
}

require dirname(__DIR__) . '/app/Views/layout.php';
