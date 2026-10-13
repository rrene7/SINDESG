<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\NominalBulkRequestService;
use App\Services\RrhhEmployeeDirectory;

$reflection = new ReflectionClass(RrhhEmployeeDirectory::class);
$source = $reflection->getMethod('positionSources');
$requested = array_fill_keys([15080, 16912, 47681, 11219, 99000], true);
$cases = [
    [15080, 0, 0, [15080 => 'current']],
    [0, 47681, 0, [47681 => 'assignment']],
    [11219, 0, 16912, [11219 => 'current', 16912 => 'historical']],
    [11219, 16912, 0, [11219 => 'current', 16912 => 'assignment_conflict']],
    [0, 0, 16912, [16912 => 'historical']],
    [0, 0, 0, []],
];
foreach ($cases as [$current, $assignment, $previous, $expected]) {
    $actual = $source->invoke(null, $current, $assignment, $previous, $requested);
    if ($actual !== $expected) {
        throw new RuntimeException(
            'RRHH origen de posición incorrecto: ' . json_encode([$actual, $expected])
        );
    }
}

$svc = new ReflectionClass(NominalBulkRequestService::class);
$instance = $svc->newInstanceWithoutConstructor();
$match = $svc->getMethod('namesCompatible');
$nameCases = [
    ['JULIO GRAJALES', 'JULIO CESAR', 'GRAJALES RODRIGUEZ', true],
    ['MANNING JARAMILLO', 'MANNING', 'JARAMILLO PEREZ', true],
    ['OSCAR VILLAREAL', 'OSCAR SALVADOR', 'VILLARREAL M', true],
    ['JAIME ARCHIBOLD', 'JAIME EFRAIN', 'ARCHIBOLD C', true],
    ['VICTOR CORTEZ', 'VICTOR OMAR', 'CORTES WENHAM', true],
    ['YONATHAN JIMENEZ', 'YONATAN YOEL', 'JIMENEZ B', true],
    ['LUIS POLO', 'LUIS ALBERTO', 'POLO SANCHEZ', true],
    ['JUAN GRAJALES', 'JULIO CESAR', 'GRAJALES RODRIGUEZ', false],
    ['OSCAR GONZALEZ', 'OSCAR SALVADOR', 'VILLARREAL M', false],
    ['LUIS', 'LUIS ALBERTO', 'POLO SANCHEZ', false],
];
foreach ($nameCases as [$excel, $first, $last, $expected]) {
    if ($match->invoke($instance, $excel, $first, $last) !== $expected) {
        throw new RuntimeException('Comparación incorrecta del nombre: ' . $excel);
    }
}
$directorySource = (string) file_get_contents(
    dirname(__DIR__) . '/app/Services/RrhhEmployeeDirectory.php'
);
$nominalSource = (string) file_get_contents(
    dirname(__DIR__) . '/app/Services/NominalBulkRequestService.php'
);
foreach ([
    'e.legacy_position',
    'e.last_position_number',
    'pa.position_number',
    'e.first_name_1',
    'e.first_name_2',
    'e.last_name_1',
    'e.last_name_2',
    'positionSources(',
    'position_source',
    'is_rrhh_eligible',
    'rrhhRead($sql, $params)',
] as $part) {
    if (!str_contains($directorySource, $part)) {
        throw new RuntimeException('Falta campo RRHH solo lectura: ' . $part);
    }
}
foreach (['historical', 'assignment_conflict', 'is_rrhh_eligible', 'namesCompatible'] as $part) {
    if (!str_contains($nominalSource, $part)) {
        throw new RuntimeException('No se protege una coincidencia de RRHH: ' . $part);
    }
}
echo "[OK] Identidad RRHH: legacy_position vigente, historica visible y nombres abreviados con posición exacta.\n";
