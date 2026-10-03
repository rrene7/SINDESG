<?php
declare(strict_types=1);

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
        'description' => 'Ingreso por compra, confección interna, transferencia, donación o devolución.',
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

$categories = [
    ['name' => 'Suéter', 'key' => 'sueter', 'initials' => 'SU'],
    ['name' => 'Camisa', 'key' => 'camisa', 'initials' => 'CA'],
    ['name' => 'Pantalón', 'key' => 'pantalon', 'initials' => 'PA'],
    ['name' => 'Falda', 'key' => 'falda', 'initials' => 'FA'],
    ['name' => 'Camisa MNJ', 'key' => 'camisa-mnj', 'initials' => 'MN'],
    ['name' => 'Botas', 'key' => 'botas', 'initials' => 'BO'],
    ['name' => 'Gorras', 'key' => 'gorras', 'initials' => 'GO'],
    ['name' => 'Kepis', 'key' => 'kepis', 'initials' => 'KE'],
];

$currentModule = $_GET['module'] ?? 'dashboard';
if ($currentModule !== 'dashboard' && !isset($modules[$currentModule])) {
    http_response_code(404);
    $currentModule = 'not-found';
}

$health = [
    'sindesg' => $database->health('sindesg'),
    'rrhh' => $database->health('rrhh'),
];

if ($currentModule === 'dashboard') {
    $pageTitle = 'Dashboard';
    $contentView = dirname(__DIR__) . '/app/Views/dashboard.php';
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
