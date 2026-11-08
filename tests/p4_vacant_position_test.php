<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\NominalBulkRequestService;
use App\Services\RrhhEmployeeDirectory;

// P4 nomination eligibility must reject known position placeholders,
// regardless of an otherwise valid ID, administrative status or position.
$eligibility = (new ReflectionClass(RrhhEmployeeDirectory::class))
    ->getMethod('isP4NomineeEligible');
$person = [
    'document_number' => '8-888-888',
    'status_name' => 'Licencia',
    'status_legacy_code' => '3',
    'status_is_inactive' => 0,
    'is_position_placeholder' => 0,
];
if ($eligibility->invoke(null, $person) !== true) {
    throw new RuntimeException('Valid P4 candidate must remain eligible.');
}
foreach ([1, -1, 2] as $placeholder) {
    $vacant = $person;
    $vacant['is_position_placeholder'] = $placeholder;
    if ($eligibility->invoke(null, $vacant) !== false) {
        throw new RuntimeException('Vacant position must never be treated as a nominated officer.');
    }
}
$withoutDocument = $person;
$withoutDocument['document_number'] = '';
if ($eligibility->invoke(null, $withoutDocument) !== false) {
    throw new RuntimeException('P4 document identity validation must remain enforced.');
}
$inactive = $person;
$inactive['status_is_inactive'] = 1;
if ($eligibility->invoke(null, $inactive) !== false) {
    throw new RuntimeException('Existing ineligible RRHH status rules must remain enforced.');
}

// Even existing P4 manual approvals cannot override newly detected vacancies.
$saved = [
    'rrhh_employee_id' => 12,
    'rrhh_document_snapshot' => '8-888-888',
    'rrhh_position_snapshot' => '12345',
    'excel_position' => '12345',
    'excel_name' => 'NOMBRE APELLIDO',
    'excel_size' => '9',
];
$excel = ['position' => '12345', 'name' => 'NOMBRE APELLIDO', 'raw_size' => '9'];
$matched = [
    'employee_id' => 12,
    'document_number' => '8-888-888',
    'position_number' => '12345',
    'position_source' => 'current',
    'is_rrhh_eligible' => true,
];
if (!NominalBulkRequestService::isSavedValidationApplicable($saved, $excel, $matched)) {
    throw new RuntimeException('Eligible unchanged manual evidence should remain applicable.');
}
$vacantMatch = $matched;
$vacantMatch['position_source'] = 'vacant';
$vacantMatch['is_rrhh_eligible'] = false;
if (NominalBulkRequestService::isSavedValidationApplicable($saved, $excel, $vacantMatch)) {
    throw new RuntimeException('Vacant position must invalidate prior manual evidence.');
}
$manualIssue = [
    'severity' => 'blocking',
    'message' => 'Nombres del Excel y del expediente RRHH no coinciden suficientemente.',
    'candidate_employee_id' => 12,
    'candidate_document' => '8-888-888',
    'position_source' => 'vacant',
];
if (NominalBulkRequestService::isManuallyValidatable($manualIssue)) {
    throw new RuntimeException('Vacant position cannot be approved through manual P4 validation.');
}

// Contract checks: both batch resolution and P4 search read the actual RRHH
// placeholder flag, without any data migration or writing to RRHH2029.
$root = dirname(__DIR__);
$directorySource = (string) file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');
$nominalSource = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$requestView = (string) file_get_contents($root . '/app/Views/requests/show.php');
if (substr_count($directorySource, 'e.is_position_placeholder') < 3
    || !str_contains($directorySource, 'COALESCE(e.is_position_placeholder, 0) = 0')
    || !str_contains($directorySource, "'vacant'")
    || !str_contains($directorySource, "Posición vacante o sin titular")
    || !str_contains($nominalSource, "=== 'vacant'")
    || !str_contains($requestView, "'vacant' => 'Vacante / sin titular'")) {
    throw new RuntimeException('RRHH vacant-position read-only safeguards are missing.');
}

echo "[OK] P4 vacantes no se nominan ni validan manualmente; RRHH solo lectura.\n";
