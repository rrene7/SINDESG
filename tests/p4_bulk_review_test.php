<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\NominalBulkRequestService as ReviewService;
use App\Repositories\P4NominalValidationRepository;

$issue = [
    'excel_row' => 166, 'position' => '17381', 'excel_name' => 'CARLOS LARA',
    'candidate_employee_id' => 123, 'candidate_document' => '9-111-222',
    'rrhh_name' => 'Omar Barrera Martinez', 'position_source' => 'current',
    'size' => '9', 'normalized_size' => '9', 'severity' => 'blocking',
    'message' => 'Nombres del Excel y del expediente RRHH no coinciden suficientemente.',
];
if (!ReviewService::isManuallyValidatable($issue)) {
    throw new RuntimeException('Las diferencias de nombre deben poder seleccionarse.');
}
foreach ([
    array_replace($issue, ['position_source' => 'historical']),
    array_replace($issue, ['candidate_employee_id' => 0]),
    array_replace($issue, ['candidate_document' => '']),
    array_replace($issue, ['message' => 'Talla no válida']),
    array_replace($issue, ['severity' => 'notice']),
] as $blocked) {
    if (ReviewService::isManuallyValidatable($blocked)) {
        throw new RuntimeException('La selección masiva no puede saltarse controles RRHH.');
    }
}

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$controller = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
$repo = (string) file_get_contents($root . '/app/Repositories/P4NominalValidationRepository.php');
$js = (string) file_get_contents($root . '/public/assets/js/requests.js');

foreach ([
    'validateSelectedRows(', 'prepareValidatedRow(', 'recordBatch($payloads)',
    'resolveBulk(', 'hash_equals(', 'is_rrhh_eligible',
    'La selección de filas contiene valores repetidos o inválidos.',
    'Dos filas apuntan al mismo funcionario RRHH',
] as $part) {
    if (!str_contains($service, $part)) {
        throw new RuntimeException('Falta control de validación colectiva: ' . $part);
    }
}
foreach ([
    "if (\$action === 'validate-nominal-selected')",
    "sindesg_can('requests.manage')", 'csrf_valid(',
    "p4_nominal_review", "time() - 3600", "confirm_bulk",
    "array_fill_keys", "validateSelectedRows(",
] as $part) {
    if (!str_contains($controller, $part)) {
        throw new RuntimeException('Falta protección del botón único P4: ' . $part);
    }
}
foreach ([
    'id="p4-bulk-review-form"', 'name="review_rows[]"',
    'data-p4-review-row', 'data-p4-review-select-all',
    'Validar seleccionados', 'form="p4-bulk-review-form"',
    'name="confirm_bulk"', 'csrf_token()',
] as $part) {
    if (!str_contains($view, $part)) {
        throw new RuntimeException('Falta selección múltiple visible: ' . $part);
    }
}
if (str_contains($view,'<textarea name="justification"')
    || str_contains($view,'name="document_number"')
    || str_contains($view,'<details class="p4-validate-row"')
    || !str_contains($js, 'data-p4-review-row')) {
    throw new RuntimeException('El formulario individual pesado sigue presente.');
}
if (!str_contains($repo, '$this->pdo->beginTransaction()')
    || !str_contains($repo, '$this->pdo->rollBack()')
    || !str_contains($repo, 'public function recordBatch(')) {
    throw new RuntimeException('La evidencia colectiva debe guardarse atómicamente.');
}

// Prueba real del rollback sin tocar la base de datos de producción/local.
// Usa SQLite en memoria si está disponible en el runner.
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE p4_nominal_validation_decisions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
        source_sha256 TEXT NOT NULL, requisition TEXT NOT NULL,
        excel_row INTEGER NOT NULL, excel_position TEXT NOT NULL,
        excel_name TEXT NOT NULL, excel_size TEXT NOT NULL,
        rrhh_employee_id INTEGER NOT NULL, rrhh_document_snapshot TEXT NOT NULL,
        rrhh_position_snapshot TEXT NOT NULL, rrhh_name_snapshot TEXT NOT NULL,
        justification TEXT NOT NULL, validated_by_user_id INTEGER NOT NULL,
        validated_by_label_snapshot TEXT NOT NULL
    )');
    $storage = new P4NominalValidationRepository($pdo);
    $template = [
        'request_id'=>3,'product_id'=>5,
        'source_sha256'=>str_repeat('b', 64),'requisition'=>'1122965',
        'excel_row'=>166,'excel_position'=>'17381','excel_name'=>'CARLOS LARA',
        'excel_size'=>'9','rrhh_employee_id'=>123,'rrhh_document_snapshot'=>'9-111-222',
        'rrhh_position_snapshot'=>'17381','rrhh_name_snapshot'=>'Omar Barrera Martinez',
        'justification'=>'Confirmación colectiva responsable del P4, fila 166.',
        'validated_by_user_id'=>1,'validated_by_label_snapshot'=>'P4 Colón',
    ];
    $second = array_replace($template,['excel_row'=>368,'excel_name'=>'JOSÉ HINRSTR0ZA']);
    $ids = $storage->recordBatch([166=>$template,368=>$second]);
    if (count($ids)!==2 || $ids[166] <= 0 || $ids[368] <= 0) {
        throw new RuntimeException('No se crearon ambas decisiones del grupo.');
    }
    $broken = array_replace($second,['excel_row'=>537]);
    unset($broken['rrhh_employee_id']);
    try {
        $storage->recordBatch([385=>array_replace($template,['excel_row'=>385]),537=>$broken]);
        throw new RuntimeException('Debió rechazarse el lote incompleto.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Debió rechazarse el lote incompleto.') throw $e;
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM p4_nominal_validation_decisions')->fetchColumn()!==2) {
        throw new RuntimeException('El lote fallido dejó aprobaciones parciales.');
    }
}
echo "[OK] Un solo clic valida seleccionados y conserva auditoría atómica sin formularios por fila.\n";
