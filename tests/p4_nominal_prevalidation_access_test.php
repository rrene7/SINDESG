<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\P4RequestGuard;

$ensure = static function (bool $ok, string $msg): void {
    if (!$ok) throw new RuntimeException($msg);
};
$p4 = ['role_codes' => ['P4']];
$central = ['role_codes' => ['CENTRAL']];
$both = ['role_codes' => ['CENTRAL','P4']];
$viewer = ['role_codes' => ['VIEWER']];
$admin = ['role_codes' => ['ADMIN']];

$ensure(sindesg_role_action_allowed('requests', 'validate-nominal-selected', 'POST', $p4)
    && sindesg_role_action_allowed('requests', 'import-from-nominal-preview', 'POST', $p4),
    'El P4 no puede completar la conciliación nominal autorizada.');
$ensure(sindesg_role_action_allowed('requests', 'preview-nominal-file', 'POST', $p4),
    'El P4 debe poder prevalidar su Excel nominal por POST.');
$ensure(sindesg_permission_for('requests', 'preview-nominal-file', 'POST') === 'requests.manage',
    'La prevalidación debe continuar exigiendo requests.manage.');
$ensure(!sindesg_role_action_allowed('requests', 'preview-nominal-file', 'GET', $p4),
    'El P4 no puede prevalidar con GET.');
foreach ([$central, $both, $viewer] as $actor) {
    $ensure(!sindesg_role_action_allowed('requests', 'preview-nominal-file', 'POST', $actor),
        'Rol no autorizado podría prevalorar el Excel nominal.');
}
$ensure(sindesg_role_action_allowed('requests', 'preview-nominal-file', 'POST', $admin),
    'El ADMIN mantiene la prevalidación excepcional.');
foreach (['import-nominal-file', 'validate-nominal-row', 'decide'] as $action) {
    $ensure(!sindesg_role_action_allowed('requests', $action, 'POST', $p4),
        'La prevalidación no puede conceder importación o aprobación: ' . $action);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('Se necesita pdo_sqlite para comprobar el aislamiento P4.');
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER PRIMARY KEY, warehouse_type TEXT, is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER, warehouse_id INTEGER);
CREATE TABLE service_requests(id INTEGER PRIMARY KEY,p4_warehouse_id INTEGER);
INSERT INTO warehouses VALUES (6,'P4',1),(8,'P4',1),(7,'P4',0),(9,'CENTRAL',1);
INSERT INTO security_user_p4_scopes VALUES (10,6),(10,7),(10,9),(20,8);
INSERT INTO service_requests VALUES (101,6),(102,8),(103,7),(104,9);
SQL);
$guard = new P4RequestGuard($db);
$guard->assertRequest(10, 101);
$guard->assertRequest(20, 102);
foreach ([[10,102],[10,103],[10,104],[20,101],[0,101],[10,0]] as [$user,$request]) {
    try {
        $guard->assertRequest($user,$request);
        throw new LogicException('Prevalidación ajena permitida: '.$user.'/'.$request);
    } catch (RuntimeException $e) {
        // Required: deny another P4, inactive warehouse or Central warehouse.
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/RequestsController.php');
$view = file_get_contents($root . '/app/Views/requests/show.php');
$service = file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$stage = file_get_contents($root . '/app/Services/P4NominalStagedFile.php');
foreach (['controller'=>$controller, 'view'=>$view, 'service'=>$service, 'stage'=>$stage] as $name=>$content) {
    $ensure(is_string($content) && $content !== '', 'Archivo faltante: '.$name);
}
$controller = str_replace(["\r\n","\r"],"\n",$controller);
$view = str_replace(["\r\n","\r"],"\n",$view);
foreach ([
    "'save', 'add-item', 'remove-item', 'submit', 'preview-nominal-file'",
    '$guard->assertRequest($userId, (int) ($post[\'request_id\'] ?? 0))',
    "if (!csrf_valid(",
    "if (\$action === 'preview-nominal-file')",
    '$this->bulk->previewWorkbook(',
    '$this->handleGet(\'show\', [\'id\' => $requestId])',
    '$_FILES[\'nominal_workbook\']',
    '15 * 1024 * 1024',
    "['xlsx', 'csv']",
    'is_uploaded_file($path)',
] as $needle) {
    $ensure(str_contains($controller, $needle), 'Falta protección en prevalidación: '.$needle);
}
$guardPos = strpos($controller, '$guard->assertRequest($userId, (int) ($post[\'request_id\'] ?? 0))');
$processingPos = strpos($controller, '$this->bulk->previewWorkbook(');
$ensure($guardPos !== false && $processingPos !== false && $guardPos < $processingPos,
    'La pertenencia P4 debe verificarse antes de analizar un archivo.');
$ensure(str_contains($service, 'unset($report[\'_batch\']);'),
    'La prevalidación no puede incluir el lote que se importa.');
$ensure(str_contains($service, '$this->editableRequest($requestId);'),
    'Solo se pueden prevalidar solicitudes editables.');
$ensure(str_contains($stage, 'sys_get_temp_dir()')
    && str_contains($stage, 'public const TTL = 3600;'),
    'El Excel temporal debe permanecer privado y con caducidad.');
$ensure(str_contains($view, "action=preview-nominal-file")
    && str_contains($view,"sindesg_role_action_allowed('requests', 'import-nominal-file', 'POST'")
    && str_contains($view,"sindesg_role_action_allowed('requests', 'import-from-nominal-preview', 'POST'")
    && str_contains($view,"sindesg_role_action_allowed('requests', 'validate-nominal-selected', 'POST'"),
    'La interfaz mezcla prevalidación con botones restringidos.');
$ensure(str_contains($view, 'Esta prevalidación es informativa.'),
    'Al P4 debe explicarse que el resultado no importa personas.');

echo "[OK] Prevalidación nominal P4: POST, CSRF, solicitud asignada activa, Excel privado y sin permisos de importación.\n";
