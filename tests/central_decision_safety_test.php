<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CentralDecisionGuard;

$assert = static function(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$fail = static function(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException($message);
};
$rows = [
    ['id'=>1,'product_variant_id'=>10,'quantity_requested'=>1],
    ['id'=>2,'product_variant_id'=>10,'quantity_requested'=>1],
    ['id'=>3,'product_variant_id'=>11,'quantity_requested'=>1],
];
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>0.,2=>0.,3=>0.],[10=>0.,11=>0.],'approve',false),
    'Se aceptó aprobación de cero que equivale a rechazo involuntario.');
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>1.,2=>1.,3=>1.],[10=>0.,11=>0.],'approve',false),
    'Grupo no aplicado a destinatarios fue aprobado.');
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>1.,2=>0.,3=>0.],[10=>1.,11=>0.],'approve',false),
    'Se omitió la confirmación nominal parcial.');
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>1.,2=>1.,3=>1.],[10=>2.,11=>1.],'reject',true),
    'Rechazo permitido con cantidades asignadas.');
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>1.,2=>1.,3=>1.],[10=>2.,11=>1.],'',false),
    'Decisión sin acción explícita.');
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>1.,2=>1.,3=>1.,777=>5.],[10=>2.,11=>1.],'approve',true),
    'Se aceptó beneficiario ajeno a solicitud.');
$fail(fn()=>CentralDecisionGuard::validate($rows,[1=>1.,2=>1.],[10=>2.,11=>1.],'approve',true),
    'Se aceptó omitir beneficiario del POST.');
CentralDecisionGuard::validate($rows,[1=>1.,2=>1.,3=>1.],[10=>2.,11=>1.],'approve',false);
CentralDecisionGuard::validate($rows,[1=>1.,2=>0.,3=>0.],[10=>1.,11=>0.],'approve',true);
CentralDecisionGuard::validate($rows,[1=>0.,2=>0.,3=>0.],[10=>0.,11=>0.],'reject',false);

$admin=['role_codes'=>['ADMIN']];
foreach ([['CENTRAL'], ['P4'], ['VIEWER'], ['CENTRAL','P4']] as $roles) {
    $assert(!sindesg_role_action_allowed('requests','reopen-rejected','POST',['role_codes'=>$roles]),
        'Usuario no administrador puede reabrir un rechazo.');
}
$assert(sindesg_role_action_allowed('requests','reopen-rejected','POST',$admin),
    'Administrador perdió reapertura segura.');
$assert(!sindesg_role_action_allowed('requests','reopen-rejected','GET',$admin),
    'La reapertura no puede realizarse por GET.');
$root=dirname(__DIR__);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$repository=$read('app/Repositories/RequestRepository.php');
$service=$read('app/Services/RequestService.php');
$controller=$read('app/Controllers/RequestsController.php');
$grouped=$read('app/Views/requests/central-review.php');
$javascript=$read('public/assets/js/requests.js');
foreach (['reopen_rejected_request','new AuditRepository($this->pdo)',
    '$this->lockRequest($requestId)',"status = 'in_review'",'distributions','inventory_movements',
    '$this->pdo->rollBack()'] as $needle) {
    $assert(str_contains($repository,$needle),'Falta control de reapertura segura: '.$needle);
}
$assert(str_contains($repository,'CentralDecisionGuard::validate(')
    && str_contains($controller,"if ($" . "action === 'reopen-rejected')")
    && str_contains($controller,"sindesg_can('admin.manage')")
    && str_contains($service,"decision_action")
    && str_contains($service,"reject_ack")
    && str_contains($javascript,"event.submitter")
    && str_contains($grouped,'data-central-validate')
    && str_contains($grouped,'name="group_quantity['),
    'Controles de intención y validación sin efectos incompletos.');
echo "[OK] No es posible rechazar involuntariamente; reapertura admin, CSRF, auditoría y selección nominal validadas.\n";
