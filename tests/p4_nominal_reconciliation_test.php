<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\NominalBulkRequestService;

$reflect = new ReflectionClass(NominalBulkRequestService::class);
$instance = $reflect->newInstanceWithoutConstructor();
$compare = $reflect->getMethod('namesCompatible');

$cases = [
    // Los dos tokens del Excel existen en RRHH, pero "JARAMILLO" puede
    // quedar registrado como segundo nombre y "PEREZ" como apellido.
    ['MANNING JARAMILLO', 'MANNING JARAMILLO', 'PEREZ', true],
    ['LUIS POLO', 'LUIS ALBERTO', 'POLO SANCHEZ', true],
    ['OSCAR SALVADOR VILLARREAL', 'OSCAR SALVADOR', 'VILLARREAL M', true],
    ['ERIC DEL CID', 'ERIC', 'DEL CID', true],
    ['MARIA NUÑEZ', 'MARIA ELENA', 'NÚÑEZ', true],
    ['MANNING JARAMILLO', 'OSCAR SALVADOR', 'VILLARREAL', false],
    ['OSCAR JARAMILLO', 'MANNING JARAMILLO', 'PEREZ', false],
    ['LUIS', 'LUIS', 'POLO', false],
];
foreach ($cases as [$excel, $first, $last, $expected]) {
    $actual = $compare->invoke($instance, $excel, $first, $last);
    if ($actual !== $expected) {
        throw new RuntimeException(
            'Comparación inconsistente para "' . $excel . '" frente a "'
            . $first . ' ' . $last . '"'
        );
    }
}

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$controller = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');

foreach ([
    'previewWorkbook(', 'reconcileWorkbook(',
    "count(\$matches) > 1", 'array_values($byPosition[$position] ?? [])',
    "count(\$matches) === 0", 'Nombres del Excel y del expediente RRHH',
    "if (\$blockers > 0 && !\$allowPartial)",
    "'_batch' => \$batch", "unset(\$report['_batch'])",
    'position_number',
] as $phrase) {
    if (!str_contains($service, $phrase)) {
        throw new RuntimeException('Falta control de seguridad de conciliación: ' . $phrase);
    }
}
if (!str_contains($controller, "'preview-nominal-file'")
    || !str_contains($controller, "\$page['data']['nominalPreview'] = \$report")
    || !str_contains($view, 'Prevalidar y ver incidencias sin guardar')
    || !str_contains($view, "['rrhh_name']")
    || !str_contains($view, "['excel_name']")
    || !str_contains($view, 'No se guardaron funcionarios ni movimientos de inventario.')) {
    throw new RuntimeException('La pantalla de prevalidación por posición no está integrada.');
}
if (preg_match('/\b(?:UPDATE\s+rrhh|INSERT\s+INTO\s+inventory_movements|TRUNCATE)\b/i', $service)) {
    throw new RuntimeException('La reconciliación no debe modificar RRHH ni kardex.');
}
echo "[OK] Nombres multicomponente, posiciones ambiguas y revisión nominal previa sin escritura." . PHP_EOL;
