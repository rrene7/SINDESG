<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\P4NominalStatusPolicy;
use App\Services\RrhhEmployeeDirectory;

$base = [
    'document_number' => '4-111-222',
    'status_name' => 'Activo / en servicio',
    'status_legacy_code' => '01',
    'status_is_active' => 1,
    'status_is_inactive' => 0,
    'blocks_actions' => 0,
];

$allow = [
    ['Licencia con sueldo por estudio', '10', 1, 0, 1],
    ['Licencia sin sueldo', '30', 1, 0, 1],
    ['Incapacidad', '08', 1, 0, 1],
    ['Vacaciones regulares', '04', 1, 0, 1],
    ['Permiso administrativo', '20', 1, 0, 1],
    ['Activo / en servicio', '01', 1, 0, 0],
];
foreach ($allow as [$label, $code, $active, $inactive, $blocks]) {
    $row = array_merge($base, [
        'status_name' => $label,
        'status_legacy_code' => $code,
        'status_is_active' => $active,
        'status_is_inactive' => $inactive,
        'blocks_actions' => $blocks,
    ]);
    if (!P4NominalStatusPolicy::isAllowed($row)) {
        throw new RuntimeException('P4 debe admitir: ' . $label);
    }
}
$reject = [
    ['Vacaciones por jubilacion', '15', 1, 0, 1],
    ['Vacaciones por jubilación', '15', 1, 0, 1],
    ['Vacaciones por jubilación', '50', 1, 0, 0],
    ['INACTIVO', '09', 1, 0, 0],
    ['Inactivo / baja', '09', 1, 1, 1],
    ['Desconocido', '09', 1, 1, 0],
    ['Inactiva', '09', 1, 0, 0],
    ['No registrado', '09', 0, 0, 0],
    ['Vacaciones', '15', 1, 0, 0],
];
foreach ($reject as [$label, $code, $active, $inactive, $blocks]) {
    $row = array_merge($base, [
        'status_name' => $label,
        'status_legacy_code' => $code,
        'status_is_active' => $active,
        'status_is_inactive' => $inactive,
        'blocks_actions' => $blocks,
    ]);
    if (P4NominalStatusPolicy::isAllowed($row)
        || P4NominalStatusPolicy::rejectionReason($row) === null) {
        throw new RuntimeException('P4 debe excluir: ' . $label);
    }
}

$reflection = new ReflectionClass(RrhhEmployeeDirectory::class);
$eligible = $reflection->getMethod('isP4NomineeEligible');
$permit = array_merge($base, [
    'status_name' => 'Licencia con sueldo por estudio',
    'blocks_actions' => 1,
]);
if (!$eligible->invoke(null, $permit)) {
    throw new RuntimeException('Licencia de estudios debe poder nominarse en P4.');
}
if ($eligible->invoke(null, array_merge($permit, ['document_number' => '']))) {
    throw new RuntimeException('Un expediente sin identidad verificable nunca se asigna.');
}
if ($eligible->invoke(null, array_merge($permit, [
    'status_name' => 'Vacaciones por jubilacion',
    'status_legacy_code' => '15'
]))) {
    throw new RuntimeException('Vacaciones por jubilación no debe nominarse.');
}

$root = dirname(__DIR__);
$dir = (string) file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');
$api = (string) file_get_contents($root . '/public/api/employees.php');
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
$request = (string) file_get_contents($root . '/app/Services/RequestService.php');
$delivery = (string) file_get_contents($root . '/app/Services/DeliveryService.php');
$nominal = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');

foreach ([
    'searchForP4(', 'findP4Nominee(', 'isP4NomineeEligible(',
    's.legacy_code AS status_legacy_code',
    'COALESCE(s.is_inactive, 0) AS status_is_inactive',
    'P4NominalStatusPolicy::isAllowed',
    'positionSources(',
    'rrhhRead($sql, $params)',
] as $token) {
    if (!str_contains($dir, $token)) {
        throw new RuntimeException('Falta regla P4 solo lectura: ' . $token);
    }
}
if (!str_contains($api, "sindesg_can('requests.view')")
    || !str_contains($api, "\$directory->searchForP4(\$query, 20)")
    || !str_contains($view, 'employees.php?scope=p4')
    || !str_contains($request, 'findP4Nominee($beneficiaryId)')
    || !str_contains($delivery, 'findEligibleEmployee(')
    || !str_contains($dir, 'COALESCE(s.blocks_actions, 0) = 0')) {
    throw new RuntimeException('Regla P4 debe quedar aislada de entrega real y búsquedas normales.');
}
if (!str_contains($nominal, "'p4_rejection_reason'")) {
    throw new RuntimeException('Conciliación debe explicar las exclusiones por estado.');
}
echo "[OK] P4 nomina todos los estados salvo vacaciones por jubilación/inactivos, sin tocar RRHH ni kardex.\n";
