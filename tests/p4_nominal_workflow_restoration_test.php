<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Services/P4RequestGuard.php';

use App\Services\P4RequestGuard;
use App\Services\NominalBulkRequestService;

$must = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$p4 = ['role_codes' => ['P4']];
$central = ['role_codes' => ['CENTRAL']];
$combined = ['role_codes' => ['CENTRAL','P4']];
$viewer = ['role_codes' => ['VIEWER']];
$admin = ['role_codes' => ['ADMIN']];

foreach (['preview-nominal-file','validate-nominal-selected','import-from-nominal-preview'] as $action) {
    $must(sindesg_role_action_allowed('requests',$action,'POST',$p4),
        'P4 perdió una etapa de conciliación nominal: '.$action);
    $must(sindesg_role_action_allowed('requests',$action,'POST',$admin),
        'Administrador perdió etapa nominal: '.$action);
    $must(sindesg_permission_for('requests',$action,'POST') === 'requests.manage',
        'El paso nominal carece de permiso RBAC de escritura: '.$action);
    $must(!sindesg_role_action_allowed('requests',$action,'GET',$p4),
        'La etapa nominal acepta GET: '.$action);
    foreach ([$central,$combined,$viewer] as $user) {
        $must(!sindesg_role_action_allowed('requests',$action,'POST',$user),
            'P4 ajeno/central/viewer podría validar o importar: '.$action);
    }
}
foreach (['import-nominal-file','validate-nominal-row','decide','start-review'] as $action) {
    $must(!sindesg_role_action_allowed('requests',$action,'POST',$p4),
        'Se concedió una acción adicional no solicitada: '.$action);
}
$must(sindesg_role_action_allowed('requests','submit','POST',$p4),
    'El P4 perdió el envío legítimo del borrador.');
$must(!sindesg_role_action_allowed('requests','submit','POST',$central),
    'El Central no puede enviar borradores P4.');
$must(sindesg_role_action_allowed('requests','decide','POST',$central),
    'El Central conserva decisión de solicitudes enviadas.');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('Se requiere SQLite para la prueba de ámbitos P4.');
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER PRIMARY KEY,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER PRIMARY KEY,p4_warehouse_id INTEGER);
INSERT INTO warehouses VALUES (6,'P4',1),(4,'P4',1),(7,'P4',0),(9,'CENTRAL',1);
INSERT INTO security_user_p4_scopes VALUES (10,6),(10,7),(10,9),(20,4);
INSERT INTO service_requests VALUES (101,6),(102,4),(103,7),(104,9);
SQL);
$guard = new P4RequestGuard($db);
$guard->assertRequest(10,101);
foreach ([[10,102],[10,103],[10,104],[20,101],[0,101],[10,0]] as [$uid,$rid]) {
    $blocked = false;
    try { $guard->assertRequest($uid,$rid); } catch (RuntimeException $e) { $blocked = true; }
    $must($blocked, 'No se bloqueó otra unidad o almacén inactivo.');
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $code = file_get_contents($root . '/' . $path);
    if (!is_string($code)) throw new RuntimeException('Archivo ausente: '.$path);
    return str_replace(["\r\n","\r"], "\n", $code);
};
$controller = $read('app/Controllers/RequestsController.php');
$view = $read('app/Views/requests/show.php');
$service = $read('app/Services/NominalBulkRequestService.php');
$stage = $read('app/Services/P4NominalStagedFile.php');
$validation = $read('app/Repositories/P4NominalValidationRepository.php');
$policy = $read('app/bootstrap.php');

foreach ([
    'validate-nominal-selected',
    'import-from-nominal-preview',
    '$guard->assertRequest($userId, (int) ($post[\'request_id\'] ?? 0))',
    'csrf_valid(',
    'validateSelectedRows(',
    'P4NominalStagedFile::resolve(',
    'P4NominalStagedFile::delete(',
    '$this->bulk->importWorkbook(',
    '$session[\'user_id\']',
    "['save', 'add-item', 'remove-item', 'submit', 'preview-nominal-file',",
] as $part) {
    $must(str_contains($controller,$part), 'Falta barrera en el controlador: '.$part);
}
$first = strpos($controller, '$guard->assertRequest($userId, (int) ($post[\'request_id\'] ?? 0))');
$import = strpos($controller, "if (\$action === 'import-from-nominal-preview')");
$validationAction = strpos($controller, "if (\$action === 'validate-nominal-selected')");
$must($first !== false && $import !== false && $validationAction !== false
    && $first < $import && $first < $validationAction,
    'La autorización P4 debe preceder a la validación y carga.');
foreach ([
    "sindesg_role_action_allowed('requests', 'import-from-nominal-preview', 'POST'",
    "sindesg_role_action_allowed('requests', 'validate-nominal-selected', 'POST'",
    'name="confirm_bulk" value="1" required',
    'Validar seleccionados',
    'Cargar al sistema',
    'name="allow_partial"',
    'name="accept_document_gap"',
    'Requiere corregir Excel o RRHH',
] as $part) {
    $must(str_contains($view,$part), 'Falta control o advertencia de UI: '.$part);
}
$must(!str_contains($view, '<input type="hidden" name="confirm_bulk" value="1">'),
    'Confirmación implícita: el P4 debe aceptar expresamente las identidades.');
foreach ([
    'public function validateSelectedRows(',
    'public static function isSavedValidationApplicable(',
    'public function importWorkbook(',
    '$this->editableRequest($requestId);',
    '$this->reconcileWorkbook(',
    'is_rrhh_eligible',
    'hash_equals(',
] as $part) {
    $must(str_contains($service,$part), 'Falta verificación RRHH/importación: '.$part);
}
$must(str_contains($stage, 'public const TTL = 3600;')
    && str_contains($stage, 'sys_get_temp_dir()')
    && str_contains($stage, 'hash_file(\'sha256\''),
    'El archivo temporal no es privado o no se verifica por SHA-256.');
$must(str_contains($validation, '$this->pdo->beginTransaction()')
    && str_contains($validation, '$this->pdo->rollBack()')
    && str_contains($validation, 'INSERT INTO p4_nominal_validation_decisions'),
    'Las validaciones excepcionales requieren auditoría atómica.');
$must(str_contains($policy, "if (\$module === 'requests' && in_array(\$action, ["),
    'Se requiere deny-by-default para la ruta de validación e importación.');

$issue = [
    'severity'=>'blocking',
    'message'=>'Nombres del Excel y del expediente RRHH no coinciden suficientemente.',
    'candidate_employee_id'=>25,
    'candidate_document'=>'9-111-222',
    'position_source'=>'current',
];
$must(NominalBulkRequestService::isManuallyValidatable($issue),
    'Una discrepancia con candidato RRHH inequívoco debe poder revisarse.');
foreach ([
    array_replace($issue,['candidate_employee_id'=>0]),
    array_replace($issue,['candidate_document'=>'']),
    array_replace($issue,['position_source'=>'historical']),
    array_replace($issue,['severity'=>'notice']),
] as $blocked) {
    $must(!NominalBulkRequestService::isManuallyValidatable($blocked),
        'Un problema de identidad sin certeza no debe aprobarse manualmente.');
}
$must(NominalBulkRequestService::documentGap(517,516)===1,
    'El sobrante documental de una unidad debe preservarse sin dotación inventada.');

echo "[OK] P4: validación nominal auditada e importación al borrador; aislamiento y aprobación Central intactos.\n";
