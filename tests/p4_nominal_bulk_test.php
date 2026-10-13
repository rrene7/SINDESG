<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\BootsP4WorkbookPreview;
use App\Services\NominalBulkRequestService;

$book = (new BootsP4WorkbookPreview())->analyze([[
    'name' => 'Hoja1',
    'rows' => [
        ['3ª ZONA DE POLICIA DE COLON'],
        ['CONTRATO DA-038-2025'],
        ['REQUISICION Nº 1122965'],
        ['CANTIDAD DE UNIDADES A DOTAR: 2'],
        ['N°','RANGO','POSICIÓN','NOMBRE','APELLIDO','TALLA','FIRMA'],
        ['1','AGENTE','10001','CARLOS','PEREZ','8.5',''],
        ['2','AGENTE','10001','CARLOS','PEREZ','8',''],
        ['3','AGENTE','10002','MARIA','GARCIA','8 5',''],
        ['', '', 'TALLAS', 'CANTIDADES'],
        ['', '', '8', '1'],
        ['', '', '8.5', '2'],
        ['', '', 'TOTAL', '3'],
    ],
]], 'fake.xlsx');

if (($book['nominal_count'] ?? null) !== 3 || count($book['nominal'] ?? []) !== 3) {
    throw new RuntimeException('No se exponen todas las filas nominales del archivo.');
}
if (($book['document']['requisition'] ?? '') !== '1122965') {
    throw new RuntimeException('La requisición no fue identificada.');
}
$types = array_column($book['issues'], 'type');
foreach (['duplicate', 'size_format', 'quantity'] as $type) {
    if (!in_array($type, $types, true)) {
        throw new RuntimeException('Falta advertencia: ' . $type);
    }
}

if (in_array('size', $types, true) || in_array('size_summary', $types, true)
    || ($book['size_counts']['8.5'] ?? null) !== 2) {
    throw new RuntimeException('La talla 8 5 no se normalizó a una sola variante 8.5.');
}

$reflect = new ReflectionClass(NominalBulkRequestService::class);
$nominal = $reflect->newInstanceWithoutConstructor();
$method = $reflect->getMethod('namesCompatible');
if (!$method->invoke($nominal, 'Carlos Perez', 'CARLOS ALBERTO', 'PEREZ GARCIA')) {
    throw new RuntimeException('Coincidencia nominal entre Excel y RRHH incorrecta.');
}
if ($method->invoke($nominal, 'Juan Perez', 'CARLOS', 'PEREZ')) {
    throw new RuntimeException('La comparación nominal no detecta nombres discordantes.');
}

$svc = (string) file_get_contents(dirname(__DIR__) . '/app/Services/NominalBulkRequestService.php');
$repo = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/RequestRepository.php');
$directory = (string) file_get_contents(dirname(__DIR__) . '/app/Services/RrhhEmployeeDirectory.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/RequestsController.php');
$view = (string) file_get_contents(dirname(__DIR__) . '/app/Views/requests/show.php');
$js = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/requests.js');

foreach ([
    'resolveBulk',
    // Personal prestado también puede solicitarse: no se bloquea por adscripción.
    'namesCompatible',
    'addNominalBatch',
    'allowPartial',
    'status',
] as $token) {
    if (!str_contains($svc, $token)) {
        throw new RuntimeException('Falta protección nominal: ' . $token);
    }
}
foreach ([
    'beginTransaction', 'rollBack', 'commit',
    "status'] !== 'draft'",
    'beneficiary_employee_id', 'product_id',
] as $token) {
    if (!str_contains($repo, $token)) {
        throw new RuntimeException('Falta protección transaccional: ' . $token);
    }
}
foreach (['rrhhRead', "['employee_id', 'position_number']", 'unit_path_ids'] as $token) {
    if (!str_contains($directory, $token)) {
        throw new RuntimeException('Falta validación RRHH solo lectura: ' . $token);
    }
}
foreach (['add-nominal-bulk', 'import-nominal-file', 'is_uploaded_file', 'UPLOAD_ERR_OK'] as $token) {
    if (!str_contains($controller, $token)) {
        throw new RuntimeException('Falta control de carga: ' . $token);
    }
}
foreach (['data-bulk-nominal-picker', 'nominal_workbook', 'allow_partial'] as $token) {
    if (!str_contains($view, $token)) {
        throw new RuntimeException('Falta UI masiva: ' . $token);
    }
}
foreach (['data-bulk-search','employee_ids[]', 'data-bulk-select-page'] as $token) {
    if (!str_contains($js, $token)) {
        throw new RuntimeException('Falta interacción de selección múltiple: ' . $token);
    }
}
if (preg_match('/\b(?:INSERT\s+INTO\s+inventory_movements|UPDATE\s+inventory_movements|DELETE\s+FROM\s+inventory_movements)\b/i', $svc)) {
    throw new RuntimeException('La captura nominal no puede alterar el kardex.');
}

echo "[OK] Selección múltiple/importación nominal: validaciones sin tocar kardex." . PHP_EOL;
