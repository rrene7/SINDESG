<?php
declare(strict_types=1);

use App\Controllers\InventoryController;
use App\Controllers\ReceiptsController;
use App\Controllers\ProductionController;
use App\Controllers\PeopleController;
use App\Controllers\WarehousesController;
use App\Controllers\RequestsController;
use App\Controllers\DistributionController;
use App\Controllers\DeliveryController;
use App\Controllers\AdjustmentController;
use App\Controllers\ReportsController;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\AuditController;
use App\Controllers\HistoricalImportController;
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
    'adjustments' => [
        'label' => 'Ajustes y devoluciones',
        'short' => 'Ajustes',
        'icon' => 'AJ',
        'phase' => 'Fase 10',
        'description' => 'Cambios, devoluciones, bajas, pérdidas, sobrantes, faltantes y conteos auditables.',
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
    'admin' => [
        'label' => 'Administración',
        'short' => 'Admin',
        'icon' => 'AD',
        'phase' => 'Fase 12',
        'description' => 'Usuarios, roles, permisos y parámetros del sistema.',
    ],
    'migration' => [
        'label' => 'Migración histórica',
        'short' => 'Históricos',
        'icon' => 'MH',
        'phase' => 'Fase 13A',
        'description' => 'Carga, depuración y staging de archivos históricos antes de incorporarlos.',
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

$currentModule = $_GET['module'] ?? 'dashboard';
$currentAction = (string) ($_GET['action'] ?? 'index');
$currentMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($currentModule === 'auth') {
    $controller = new AuthController($database, $authService);
    $result = $controller->handle($currentAction, $_GET, $_POST, $currentMethod);
    $pageTitle = $result['title'];
    $contentView = $result['view'];
    extract($result['data'], EXTR_SKIP);
    require dirname(__DIR__) . '/app/Views/auth/layout.php';
    exit;
}

try {
    if (!$authService->hasUsers()) {
        header('Location: ' . sindesg_url('?module=auth&action=setup'), true, 303);
        exit;
    }
} catch (Throwable $exception) {
    $pageTitle = 'Seguridad pendiente';
    $authError = 'Aplique la migración 015_security_audit.sql antes de continuar.';
    $contentView = dirname(__DIR__) . '/app/Views/auth/unavailable.php';
    require dirname(__DIR__) . '/app/Views/auth/layout.php';
    exit;
}

if ($authService->currentUser() === null) {
    header('Location: ' . sindesg_url('?module=auth&action=login'), true, 303);
    exit;
}

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

if ($currentModule !== 'dashboard' && !isset($modules[$currentModule])) {
    http_response_code(404);
    $currentModule = 'not-found';
}

$requiredPermission = sindesg_permission_for($currentModule, $currentAction, $currentMethod);
if (($requiredPermission !== null && !$authService->can($requiredPermission))
    || !sindesg_role_action_allowed($currentModule, $currentAction, $currentMethod, (array) sindesg_user())) {
    http_response_code(403);
    $pageTitle = 'Acceso denegado';
    $contentView = dirname(__DIR__) . '/app/Views/auth/forbidden.php';
    require dirname(__DIR__) . '/app/Views/layout.php';
    exit;
}

// Enforce P4 record scope before controller dispatch (GET and POST).
if (in_array($currentModule, ['requests', 'distribution', 'deliveries'], true)) {
    $scope = \App\Services\P4WarehouseScope::current($database);
    if (!$scope['unrestricted']) {
        $pdoScope = $database->connection('sindesg');
        $inputScope = $currentMethod === 'POST' ? $_POST : $_GET;
        $table = null;
        $id = 0;
        $sql = null;
        if ($currentModule === 'requests') {
            $id = (int) ($inputScope['request_id'] ?? $inputScope['id'] ?? 0);
            $sql = 'SELECT p4_warehouse_id FROM service_requests WHERE id = :id';
        } elseif ($currentModule === 'distribution') {
            $id = (int) ($inputScope['distribution_id'] ?? $inputScope['id'] ?? 0);
            $sql = 'SELECT destination_warehouse_id FROM distributions WHERE id = :id';
        } else {
            $id = (int) ($inputScope['delivery_id'] ?? $inputScope['id'] ?? 0);
            $sql = 'SELECT p4_warehouse_id FROM individual_deliveries WHERE id = :id';
        }
        if ($currentModule === 'deliveries' && $id === 0 && isset($inputScope['request_item_id'])) {
            $id = (int) $inputScope['request_item_id'];
            $sql = 'SELECT r.p4_warehouse_id FROM service_request_items i
                    JOIN service_requests r ON r.id = i.request_id WHERE i.id = :id';
        }
        if ($id > 0) {
            $stmtScope = $pdoScope->prepare($sql);
            $stmtScope->execute(['id' => $id]);
            $warehouseScope = (int) ($stmtScope->fetchColumn() ?: 0);
            if (!\App\Services\P4WarehouseScope::authorized($scope['roles'], $scope['warehouses'], $warehouseScope)) {
                http_response_code(403);
                $pageTitle = 'Acceso denegado';
                $contentView = dirname(__DIR__) . '/app/Views/auth/forbidden.php';
                require dirname(__DIR__) . '/app/Views/layout.php';
                exit;
            }
        }
        if ($currentModule === 'requests' && $currentAction === 'save' && $currentMethod === 'POST') {
            \App\Services\P4WarehouseScope::assertAuthorized(
                $scope['roles'], $scope['warehouses'],
                (int) ($_POST['p4_warehouse_id'] ?? 0)
            );
        }
        if ($currentModule === 'deliveries' && $currentAction === 'save' && $currentMethod === 'POST') {
            \App\Services\P4WarehouseScope::assertAuthorized(
                $scope['roles'], $scope['warehouses'],
                (int) ($_POST['p4_warehouse_id'] ?? 0)
            );
        }
    }
}

if ($currentMethod === 'POST') {
    $authService->registerRequestAudit($currentModule, $currentAction, $_POST);
}

if ($currentModule === 'dashboard') {
    $pageTitle = 'Dashboard';
    $dashboardCategories = [];
    $featuredProduct = null;
    $featuredSizes = [];
    $featuredStockBreakdown = ['central' => 0.0, 'p4' => 0.0, 'transit' => 0.0];
    $recentDashboardReceipts = [];
    $recentDashboardRequests = [];
    $dashboardCategoryFocus = [];
    $dashboardOperationalSummary = [
        'requests_pending_review' => 0,
        'requests_ready_distribution' => 0,
        'distributions_in_transit' => 0,
        'active_p4' => 0,
    ];

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
            $recentDashboardRequests = $dashboardRepository->recentRequests(5);
            $dashboardCategoryFocus = $dashboardRepository->categoryFocusData($dashboardCategories);
            $dashboardOperationalSummary = $dashboardRepository->operationalSummary();
        } catch (\Throwable $exception) {
            $dashboardCategories = [];
            $featuredProduct = null;
            $featuredSizes = [];
            $featuredStockBreakdown = ['central' => 0.0, 'p4' => 0.0, 'transit' => 0.0];
            $recentDashboardReceipts = [];
            $recentDashboardRequests = [];
            $dashboardCategoryFocus = [];
            $dashboardOperationalSummary = [
                'requests_pending_review' => 0,
                'requests_ready_distribution' => 0,
                'distributions_in_transit' => 0,
                'active_p4' => 0,
            ];
        }
    }

    $dashboardScope = \App\Services\P4WarehouseScope::current($database);
    if (!$dashboardScope['unrestricted']) {
        $p4DashboardRequests = \App\Services\P4WarehouseScope::filter(
            (new \App\Repositories\RequestRepository($database->connection('sindesg')))->recent(100),
            $dashboardScope, 'p4_warehouse_id'
        );
        $p4DashboardTransfers = \App\Services\P4WarehouseScope::filter(
            (new \App\Repositories\DistributionRepository($database->connection('sindesg')))->recent(100),
            $dashboardScope, 'destination_warehouse_id'
        );
        $p4DashboardDeliveries = \App\Services\P4WarehouseScope::filter(
            (new \App\Repositories\DeliveryRepository($database->connection('sindesg')))->recent(100),
            $dashboardScope, 'p4_warehouse_id'
        );
        $contentView = dirname(__DIR__) . '/app/Views/dashboard-p4.php';
    } else {
        $contentView = dirname(__DIR__) . '/app/Views/dashboard.php';
    }
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
} elseif ($currentModule === 'deliveries') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Despacho individual';
        $deliveryError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/deliveries/unavailable.php';
    } else {
        $controller = new DeliveryController($database);
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
} elseif ($currentModule === 'adjustments') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Ajustes y devoluciones';
        $adjustmentError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/adjustments/unavailable.php';
    } else {
        $controller = new AdjustmentController($database);
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
} elseif ($currentModule === 'reports') {
    if (!$health['sindesg']['ok']) {
        $pageTitle = 'Reportes';
        $reportError = 'No existe conexión con la base SINDESG. Verifique MariaDB y config/database.php.';
        $contentView = dirname(__DIR__) . '/app/Views/reports/unavailable.php';
    } else {
        $controller = new ReportsController($database);
        $result = $controller->handle(
            (string) ($_GET['action'] ?? 'index'),
            $_GET
        );

        $pageTitle = $result['title'];
        $contentView = $result['view'];
        extract($result['data'], EXTR_SKIP);
    }
} elseif ($currentModule === 'migration') {
    $controller = new HistoricalImportController($database);
    $result = $controller->handle(
        $currentAction,
        $_GET,
        $_POST,
        $_FILES,
        $currentMethod
    );

    $pageTitle = $result['title'];
    $contentView = $result['view'];
    extract($result['data'], EXTR_SKIP);
} elseif ($currentModule === 'audit') {
    $controller = new AuditController($database->connection('sindesg'));
    $result = $controller->handle(
        $currentAction,
        $_GET
    );

    $pageTitle = $result['title'];
    $contentView = $result['view'];
    extract($result['data'], EXTR_SKIP);
} elseif ($currentModule === 'admin') {
    $controller = new AdminController($database, $authService);
    $result = $controller->handle(
        $currentAction,
        $_GET,
        $_POST,
        $currentMethod
    );

    $pageTitle = $result['title'];
    $contentView = $result['view'];
    extract($result['data'], EXTR_SKIP);
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
