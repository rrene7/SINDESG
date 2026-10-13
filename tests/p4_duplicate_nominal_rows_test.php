<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\NominalBulkRequestService;

$reflection = new ReflectionClass(NominalBulkRequestService::class);
$service = $reflection->newInstanceWithoutConstructor();
$compare = $reflection->getMethod('namesCompatible');
foreach ([
    ['EDWIN HURTADO', 'EDUIN', 'HURTADO BERTIAGA', true],
    ['ORIEL SAEZ', 'ORIEL ANTONIO', 'SAENZ PEREZ', true],
    ['EVANS BARRÍA', 'EVANS ALEXIS', 'BARRIA AVILA', true],
    ['RAMIRO RUETE', 'RAMIRO', 'RUETE RODRIGUEZ', true],
    ['LUIS POLO', 'LUIS ALBERTO', 'POLO JIMENEZ', true],
    ['EVANS BARRIA', 'JULIO CESAR', 'GRAJALES RODRIGUEZ', false],
    ['EDWIN HURTADO', 'EDUIN', 'HERNANDEZ', false],
    ['ORIEL SAENZ', 'JORGE LUIS', 'SAENZ HERRERA', false],
] as [$excel, $first, $last, $expected]) {
    if ($compare->invoke($service, $excel, $first, $last) !== $expected) {
        throw new RuntimeException(
            'Error comparación nombre: ' . $excel . ' / ' . $first . ' ' . $last
        );
    }
}

$classify = $reflection->getMethod('classifyNominalDuplicates');
$person = static fn (int $position, int $id, bool $eligible = true, string $source = 'current'): array => [
    'position_number' => (string) $position,
    'employee_id' => $id,
    'first_name' => 'EVANS ALEXIS',
    'last_name' => 'BARRIA AVILA',
    'is_rrhh_eligible' => $eligible,
    'position_source' => $source,
];
$row = static fn (int $excelRow, string $position, string $name, string $size): array => [
    'excel_row' => $excelRow,
    'position' => $position,
    'name' => $name,
    'size' => $size,
];
$people = [40719 => [10 => $person(40719, 10)]];
$variants = ['9' => 100, '8' => 101];

$identical = $classify->invoke($service, [
    $row(8, '40719', 'EVANS BARRIA', '9'),
    $row(9, '40719', 'EVANS ALEXIS BARRÍA', '9'),
    $row(10, '40719', 'EVANS BARRIA', '9'),
], $people, $variants);
if ($identical['redundant'] !== [9 => 8, 10 => 8] || $identical['conflicts'] !== []) {
    throw new RuntimeException('Las copias del mismo funcionario/talla deben omitirse una sola vez.');
}

$otherSize = $classify->invoke($service, [
    $row(8, '40719', 'EVANS BARRIA', '9'),
    $row(9, '40719', 'EVANS BARRIA', '8'),
], $people, $variants);
if (count($otherSize['conflicts']) !== 1 || $otherSize['redundant'] !== []) {
    throw new RuntimeException('No se permite decidir automáticamente entre dos tallas.');
}

$otherName = $classify->invoke($service, [
    $row(8, '40719', 'EVANS BARRIA', '9'),
    $row(9, '40719', 'LUIS POLO', '9'),
], $people, $variants);
if (count($otherName['conflicts']) !== 1 || $otherName['redundant'] !== []) {
    throw new RuntimeException('No se permite ignorar nombres contradictorios.');
}
$hist = [40719 => [10 => $person(40719, 10, true, 'historical')]];
$historical = $classify->invoke($service, [
    $row(8, '40719', 'EVANS BARRIA', '9'),
    $row(9, '40719', 'EVANS BARRIA', '9'),
], $hist, $variants);
if (count($historical['conflicts']) !== 1 || $historical['redundant'] !== []) {
    throw new RuntimeException('No se debe conciliar automáticamente una posición histórica.');
}
$inactive = [40719 => [10 => $person(40719, 10, false)]];
$notEligible = $classify->invoke($service, [
    $row(8, '40719', 'EVANS BARRIA', '9'),
    $row(9, '40719', 'EVANS BARRIA', '9'),
], $inactive, $variants);
if (count($notEligible['conflicts']) !== 1 || $notEligible['redundant'] !== []) {
    throw new RuntimeException('No se debe ignorar la condición RRHH al detectar duplicados.');
}
$ambiguous = [40719 => [10 => $person(40719,10), 11 => $person(40719,11)]];
$notUnique = $classify->invoke($service, [
    $row(8, '40719', 'EVANS BARRIA', '9'),
    $row(9, '40719', 'EVANS BARRIA', '9'),
], $ambiguous, $variants);
if (count($notUnique['conflicts']) !== 1 || $notUnique['redundant'] !== []) {
    throw new RuntimeException('Posiciones ambiguas no son duplicados idénticos.');
}
$root = dirname(__DIR__);
$code = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
if (!str_contains($code, "duplicate_count")
    || !str_contains($code, "'status' => 'duplicate_omitted'")
    || !str_contains($code, "isset(\$duplicates['conflicts'][\$position])")
    || !str_contains($view, "Repetidos que se omitirán")
    || !str_contains($view, "Verificables únicos")) {
    throw new RuntimeException('Falta el resumen de duplicados y bloqueos en la prevalidación.');
}
echo "[OK] Posiciones duplicadas verificables se omiten; talla o identidad contradictoria bloquean; tolerancia 1 errata segura.\n";
