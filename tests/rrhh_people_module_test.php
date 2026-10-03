<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/RrhhRepository.php');
$controller = file_get_contents($root . '/app/Controllers/PeopleController.php');
$router = file_get_contents($root . '/public/index.php');
$index = file_get_contents($root . '/app/Views/people/index.php');

foreach (compact('repository', 'controller', 'router', 'index') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'employees e',
    'employee_position_assignments',
    'employee_unit_assignments',
    'organization_units_current',
    'searchPeople',
    'rootUnits',
    'peopleByUnit',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta capacidad RRHH: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new PeopleController')) {
    fwrite(STDERR, "[ERROR] El módulo Funcionarios no está conectado al router." . PHP_EOL);
    exit(1);
}

foreach (['Buscar funcionario', 'ESTRUCTURA OFICIAL', 'Solo lectura'] as $fragment) {
    if (!str_contains($index, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta UI de Fase 5: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Módulo de funcionarios y estructura RRHH validado." . PHP_EOL;
