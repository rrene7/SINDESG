<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Support/Database.php';
require_once dirname(__DIR__) . '/app/Repositories/RrhhRepository.php';

use App\Repositories\RrhhRepository;
use App\Support\Database;

if (!extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('La prueba requiere PDO SQLite.');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('CONCAT_WS', static function ($separator, ...$parts): string {
    return implode((string) $separator, array_filter($parts, static fn ($v) => $v !== null && $v !== ''));
}, -1);

foreach ([
    'CREATE TABLE employees (id INTEGER PRIMARY KEY, document_number TEXT, first_name TEXT, last_name TEXT, rank_id INTEGER, status_id INTEGER, current_moi_unit_id INTEGER, sex TEXT, hire_date TEXT)',
    'CREATE TABLE ranks (id INTEGER PRIMARY KEY, name TEXT, abbreviation TEXT)',
    'CREATE TABLE statuses (id INTEGER PRIMARY KEY, name TEXT)',
    'CREATE TABLE employee_position_assignments (id INTEGER PRIMARY KEY, employee_id INTEGER, position_number INTEGER, start_date TEXT, end_date TEXT)',
    'CREATE TABLE employee_unit_assignments (id INTEGER PRIMARY KEY, employee_id INTEGER, organization_unit_id INTEGER, is_primary INTEGER, status TEXT, start_date TEXT, end_date TEXT)',
    'CREATE TABLE organization_units_current (id INTEGER PRIMARY KEY, system_code TEXT, institutional_code TEXT, official_name TEXT, official_abbreviation TEXT, unit_type TEXT, moi_level TEXT, hierarchy_path TEXT, is_active INTEGER, lifecycle_status TEXT)',
] as $sql) {
    $pdo->exec($sql);
}
$pdo->exec("INSERT INTO ranks VALUES (1, 'SARGENTO', 'SGT')");
$pdo->exec("INSERT INTO statuses VALUES (1, 'ACTIVO')");
$pdo->exec("INSERT INTO organization_units_current
    VALUES (10, 'CAP-10', 'CAP-10', 'Oficina de Capellanía', 'CAP', 'oficina', '5', '10', 1, 'vigente'),
           (20, 'TEL-20', 'TEL-20', 'Dirección Nacional de Telemática', 'TEL', 'direccion_nacional', '2', '20', 1, 'vigente')");
$pdo->exec("INSERT INTO employees VALUES
    (1, 'A-101', 'PRUEBA', 'CONFLICTO', 1, 1, 20, 'M', '2010-01-01'),
    (2, 'A-102', 'PRUEBA', 'FALLBACK', 1, 1, 20, 'M', '2010-01-01'),
    (3, 'A-103', 'PRUEBA', 'ULTIMO', 1, 1, 20, 'M', '2010-01-01')");
$pdo->exec("INSERT INTO employee_position_assignments VALUES
    (1, 1, 25583, '2020-01-01', NULL),
    (2, 2, 25584, '2020-01-01', NULL),
    (3, 3, 25585, '2020-01-01', NULL)");
$pdo->exec("INSERT INTO employee_unit_assignments VALUES
    (1, 1, 10, 1, 'active', '2024-01-01', NULL),
    (2, 2, 10, 1, 'closed', '2024-01-01', '2025-01-01'),
    (3, 3, 10, 1, 'active', '2024-01-01', NULL),
    (4, 3, 20, 1, 'active', '2026-11-19', NULL)");

$database = new Database(['rrhh' => []]);
$ref = new ReflectionProperty(Database::class, 'pdo');
$ref->setValue($database, ['rrhh' => $pdo]);
$repo = new RrhhRepository($database);

$one = $repo->searchPeople('25583');
if (count($one) !== 1
    || $one[0]['organization_unit_name'] !== 'Oficina de Capellanía'
    || $one[0]['rrhh_master_unit_name'] !== 'Dirección Nacional de Telemática'
    || (int) $one[0]['unit_source_discrepancy'] !== 1
    || $one[0]['unit_source'] !== 'assignment') {
    throw new RuntimeException('Debe identificar la discrepancia entre asignación y expediente RRHH sin ocultarla.');
}
$two = $repo->searchPeople('25584');
if (count($two) !== 1
    || $two[0]['organization_unit_name'] !== 'Dirección Nacional de Telemática'
    || $two[0]['unit_source'] !== 'employee'
    || (int) $two[0]['unit_source_discrepancy'] !== 0) {
    throw new RuntimeException('Debe consultar el expediente RRHH si no existe asignación principal vigente.');
}
$three = $repo->searchPeople('25585');
if (count($three) !== 1
    || $three[0]['organization_unit_name'] !== 'Dirección Nacional de Telemática'
    || (int) $three[0]['unit_source_discrepancy'] !== 0) {
    throw new RuntimeException('Debe priorizar la asignación principal vigente más reciente.');
}
$detail = $repo->findPerson(1);
if (!$detail || (int) $detail['unit_source_discrepancy'] !== 1
    || $detail['rrhh_master_unit_name'] !== 'Dirección Nacional de Telemática') {
    throw new RuntimeException('La ficha debe reflejar el mismo conflicto que el buscador.');
}

foreach (['app/Views/people/index.php', 'app/Views/people/show.php'] as $file) {
    $content = file_get_contents(dirname(__DIR__) . '/' . $file);
    if (!is_string($content) || !str_contains($content, 'unit_source_discrepancy')
        || !str_contains($content, 'rrhh_master_unit_name')) {
        throw new RuntimeException('La interfaz no muestra el conflicto RRHH: ' . $file);
    }
}
echo "[OK] RRHH actual: fuente maestra, discrepancia y asignación más reciente." . PHP_EOL;
