<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AdjustmentApprovalPolicy as Policy;
use App\Repositories\AdjustmentRepository;

$assert = static function (bool $valid, string $why): void {
    if (!$valid) throw new RuntimeException($why);
};
$expectDenied = static function (Closure $fn, string $why): void {
    try {
        $fn();
        throw new RuntimeException('Se permitió operación prohibida: ' . $why);
    } catch (InvalidArgumentException $e) {
        // Expected.
    }
};
$admin = ['id'=>1, 'role_codes'=>['ADMIN'], 'display_name'=>'Administrador'];
$admin2 = ['id'=>2, 'role_codes'=>['ADMIN'], 'display_name'=>'Segundo administrador'];
$central = ['id'=>3, 'role_codes'=>['CENTRAL'], 'display_name'=>'Almacenista'];
$p4 = ['id'=>4, 'role_codes'=>['P4']];
$viewer = ['id'=>5, 'role_codes'=>['VIEWER']];

$assert(Policy::canDraft($central), 'Central debe poder preparar un borrador.');
$assert(Policy::canDraft($admin), 'ADMIN debe poder preparar un borrador.');
$assert(!Policy::canDraft($p4) && !Policy::canDraft($viewer), 'P4/viewer no debe preparar ajustes generales.');
$assert(!Policy::canDraft(['role_codes'=>['ADMIN']]), 'Rol sin sesión autenticada.');
$assert(Policy::canConfirm($admin) && !Policy::canConfirm($central), 'Solo ADMIN confirma.');
$expectDenied(static fn() => Policy::assertConfirmAllowed($central), 'Almacén confirmando');
$expectDenied(static fn() => Policy::assertConfirmAllowed($p4), 'P4 confirmando');
$expectDenied(static fn() => Policy::assertDifferentActor(1,$admin), 'ADMIN autoaprueba');
$expectDenied(static fn() => Policy::assertDifferentActor(null,$admin2), 'Histórico sin creador');
$expectDenied(static fn() => Policy::assertDifferentActor(0,$admin2), 'Creador inválido');
Policy::assertDifferentActor(3,$admin);
Policy::assertDifferentActor(1,$admin2);

foreach ([
    ['save','POST',$central,true],
    ['cancel','POST',$central,true],
    ['confirm','POST',$central,false],
    ['confirm','POST',$p4,false],
    ['confirm','POST',$viewer,false],
    ['confirm','POST',$admin,true],
    ['confirm','POST',$admin2,true],
    ['new','GET',$central,true],
    ['show','GET',$central,true],
    ['index','GET',$central,true],
    ['new','GET',$p4,false],
    ['index','GET',$viewer,false],
    ['delete','POST',$central,false],
] as [$action,$method,$user,$expected]) {
    $actual = sindesg_role_action_allowed('adjustments',$action,$method,$user);
    $assert($actual === $expected, 'Ruta adjustments/' . $action . ' debe respetar roles.');
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE audit_logs (
 id INTEGER PRIMARY KEY,
 module_code TEXT, action_code TEXT, record_type TEXT, record_id TEXT,
 user_id INTEGER
);
INSERT INTO audit_logs VALUES
(1,'adjustments','adjustment_draft_created','inventory_adjustment','44',3),
(2,'adjustments','adjustment_confirmed','inventory_adjustment','44',1),
(3,'adjustments','adjustment_draft_created','inventory_adjustment','45',1),
(4,'other','adjustment_draft_created','inventory_adjustment','46',8),
(5,'adjustments','adjustment_draft_created','inventory_adjustment','46',2);
SQL);
$repo = new AdjustmentRepository($pdo);
$assert($repo->draftCreatorId(44) === 3, 'Debe identificar creador original, no aprobador.');
$assert($repo->draftCreatorId(45) === 1, 'Debe registrar ADMIN si fue quien preparó el borrador.');
$assert($repo->draftCreatorId(46) === 2, 'Debe limitar auditoría al módulo correcto.');
$assert($repo->draftCreatorId(99) === null, 'Los históricos sin autor no se consideran autorizados.');
$expectDenied(static fn() => Policy::assertDifferentActor($repo->draftCreatorId(45),$admin), 'Rechazar aprobación propia.');

$root = dirname(__DIR__);
$controller = (string)file_get_contents($root.'/app/Controllers/AdjustmentController.php');
$service = (string)file_get_contents($root.'/app/Services/AdjustmentService.php');
$repository = (string)file_get_contents($root.'/app/Repositories/AdjustmentRepository.php');
$form = (string)file_get_contents($root.'/app/Views/adjustments/form.php');
$show = (string)file_get_contents($root.'/app/Views/adjustments/show.php');
$index = (string)file_get_contents($root.'/app/Views/adjustments/index.php');
$bootstrap = (string)file_get_contents($root.'/app/bootstrap.php');

foreach ([
    ['AdjustmentApprovalPolicy::assertConfirmAllowed($actor)', $service],
    ['AdjustmentApprovalPolicy::assertDraftAllowed($actor)', $service],
    ['AdjustmentApprovalPolicy::assertDifferentActor(', $repository],
    ['document_number', $repository],
    ['adjustment_draft_created', $repository],
    ['adjustment_confirmed', $repository],
    ['adjustment_draft_cancelled', $repository],
    ["'module_code' => 'adjustments'", $repository],
    ['(new AuditRepository($this->pdo))->log', $repository],
    ['$this->auditAdjustment($actor,', $repository],
    ['AdjustmentApprovalPolicy', $controller],
    ['createDraft($post, (array) sindesg_user())', $controller],
    ['confirm($id, (array) sindesg_user())', $controller],
    ['csrf_valid(', $controller],
    ["if ($"."action === 'confirm')", $bootstrap],
    ["if ($"."module === 'adjustments')", $bootstrap],
    ['canConfirmAdjustment', $show],
    ['canCancelAdjustment', $show],
    ['Pendiente de validación por un Administrador', $show],
    ['type="submit">Confirmar ajuste como ADMIN', $show],
    ["!$"."isSourceOperation ? 'required'", $form],
    ['Crear borrador', $form],
    ['Solo otro usuario ADMIN', $index],
] as [$needle,$haystack]) {
    $assert(str_contains($haystack,$needle), 'Contrato funcional ausente: '.$needle);
}

// Must authorize BEFORE any stock movement and write the approval audit
// BEFORE the DB COMMIT; failure of audit triggers rollback.
$start = strpos($repository, 'public function confirm(');
$auth = strpos($repository, 'AdjustmentApprovalPolicy::assertDifferentActor(', $start);
$movement = strpos($repository, '$this->insertMovement(', $start);
$audit = strpos($repository, "'adjustment_confirmed'", $start);
$commit = strpos($repository, '$this->pdo->commit();', $audit);
$assert($start !== false && $auth !== false && $movement !== false && $audit !== false
    && $commit !== false && $start < $auth && $auth < $movement && $movement < $audit
    && $audit < $commit, 'La autorización y auditoría deben estar dentro de la transacción.');
$assert(str_contains($repository, 'if ($this->pdo->inTransaction())'), 'Falta rollback.');

echo "[OK] Ajustes: CENTRAL prepara, ADMIN distinto aprueba, auditoría atómica, históricos bloqueados, CSRF y kardex protegido.\n";
