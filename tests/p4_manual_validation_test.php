<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\NominalBulkRequestService as Service;

$issue = [
    'excel_row' => 166, 'position' => '17381',
    'excel_name' => 'CARLOS LARA',
    'rrhh_name' => 'Omar Barrera Martinez',
    'candidate_employee_id' => 123,
    'candidate_document' => '9-111-222',
    'position_source' => 'current',
    'size' => '9', 'normalized_size' => '9',
    'severity' => 'blocking',
    'message' => 'Nombres del Excel y del expediente RRHH no coinciden suficientemente. Revisar antes de asociar.',
];
if (!Service::isManuallyValidatable($issue)) {
    throw new RuntimeException('El botón Validar debe mostrarse en una discrepancia nominal conciliable.');
}
foreach ([
    array_replace($issue, ['position_source'=>'historical']),
    array_replace($issue, ['candidate_document'=>'']),
    array_replace($issue, ['candidate_employee_id'=>0]),
    array_replace($issue, ['severity'=>'notice']),
    array_replace($issue, ['message'=>'Posición anterior']),
    array_replace($issue, ['message'=>'Estado inactivo']),
    array_replace($issue, ['message'=>'Talla inexistente']),
] as $blocked) {
    if (Service::isManuallyValidatable($blocked)) {
        throw new RuntimeException('Se ofreció validación insegura en una incidencia no nominal.');
    }
}

$row = ['position'=>'17381','name'=>'CARLOS LARA','raw_size'=>'9'];
$person = [
    'employee_id'=>123,'document_number'=>'9-111-222','position_number'=>'17381',
    'position_source'=>'current','is_rrhh_eligible'=>true,
];
$saved = [
    'rrhh_employee_id'=>123,'rrhh_document_snapshot'=>'9-111-222',
    'rrhh_position_snapshot'=>'17381','excel_position'=>'17381',
    'excel_name'=>'CARLOS LARA','excel_size'=>'9',
];
if (!Service::isSavedValidationApplicable($saved,$row,$person)) {
    throw new RuntimeException('Una decisión exacta confirmada debe poder aplicarse en el mismo archivo.');
}
foreach ([
    [$saved,array_replace($row,['name'=>'ROSA ORTEGA']),$person],
    [$saved,array_replace($row,['raw_size'=>'10']),$person],
    [$saved,array_replace($row,['position'=>'29041']),$person],
    [array_replace($saved,['rrhh_employee_id'=>999]),$row,$person],
    [$saved,$row,array_replace($person,['document_number'=>'9-888-999'])],
    [$saved,$row,array_replace($person,['position_number'=>'10000'])],
    [$saved,$row,array_replace($person,['is_rrhh_eligible'=>false])],
    [$saved,$row,array_replace($person,['position_source'=>'historical'])],
] as [$savedCandidate,$rowCandidate,$personCandidate]) {
    if (Service::isSavedValidationApplicable($savedCandidate,$rowCandidate,$personCandidate)) {
        throw new RuntimeException('No puede reusarse una validación tras cambiar el Excel o RRHH.');
    }
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');
$bulk = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
$sql = (string) file_get_contents($root . '/database/schema/019_p4_nominal_validation.sql');
$repository = (string) file_get_contents($root . '/app/Repositories/P4NominalValidationRepository.php');

foreach ([
    'isSavedValidationApplicable(',
    'validatePendingRow(',
    "hash_equals(",
    "sizeVariantsForProduct(",
    'position_source',
    "'source_sha256' => \$sourceHash",
] as $part) {
    if (!str_contains($bulk,$part)) {
        throw new RuntimeException('Falta control servidor: ' . $part);
    }
}
foreach ([
    "'validate-nominal-selected'", "'validate-nominal-row'", "sindesg_can('requests.manage')",
    "csrf_valid(", "confirm_identity",
    "p4_nominal_review", "time() - 3600",
] as $part) {
    if (!str_contains($controller,$part)) {
        throw new RuntimeException('Falta protección del formulario P4: ' . $part);
    }
}
foreach ([
    'Validar seleccionados','name="review_rows[]"','name="confirm_bulk"',
    'isManuallyValidatable($issue)','csrf_token()'
] as $part) {
    if (!str_contains($view,$part)) {
        throw new RuntimeException('Falta formulario de conciliación P4: ' . $part);
    }
}
if (str_contains($view, 'name="document_number"')
    || str_contains($controller, "(string) (\$post['document_number'] ?? '')")
    || !str_contains($bulk, "hash_equals(")
    || str_contains($view, 'name="justification"')
    || !str_contains($bulk, 'validateSelectedRows(')) {
    throw new RuntimeException('El P4 no debe digitar la cédula: debe consultarse y verificarse en el servidor.');
}
if (!str_contains($sql,'p4_nominal_validation_decisions')
    || !str_contains($sql,'source_sha256 CHAR(64)')
    || !str_contains($sql,'FOREIGN KEY (request_id)')
    || !str_contains($repository,'ORDER BY v.id DESC')
    || !str_contains($repository,'INSERT INTO p4_nominal_validation_decisions')
    || !str_contains($repository,'public function recordBatch(')
    || preg_match('/\b(?:UPDATE|DELETE)\s+(?:rrhh|p4_nominal_validation_decisions)\b/i', $repository)) {
    throw new RuntimeException('El historial auditable no es solo aditivo o no está aislado.');
}
echo "[OK] Validación P4 individual: cédula cotejada en servidor, justificación, fuente SHA y revalidación RRHH sin modificar kardex.\n";
