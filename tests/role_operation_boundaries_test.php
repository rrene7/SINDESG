<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$central = ['role_codes' => ['CENTRAL']];
$p4 = ['role_codes' => ['P4']];
$admin = ['role_codes' => ['ADMIN']];
$viewer = ['role_codes' => ['VIEWER']];
$both = ['role_codes' => ['CENTRAL','P4']];

$cases = [
    // El Almacén Central NO se convierte en P4 aunque posea requests.manage.
    ['requests','index','GET',$central,true],
    ['requests','show','GET',$central,true],
    ['requests','central-review','GET',$central,true],
    ['requests','new','GET',$central,false],
    ['requests','new','GET',$both,false],
    ['requests','save','POST',$central,false],
    ['requests','add-item','POST',$central,false],
    ['requests','remove-item','POST',$central,false],
    ['requests','submit','POST',$central,false],
    ['requests','add-nominal-bulk','POST',$central,false],
    ['requests','import-nominal-file','POST',$central,false],
    ['requests','preview-nominal-file','POST',$central,false],
    ['requests','import-boots','GET',$central,false],
    ['requests','download-nominal-template','GET',$central,false],
    ['requests','validate-nominal-row','POST',$central,false],
    ['requests','validate-nominal-selected','POST',$central,false],
    ['requests','decide','POST',$central,true],
    ['requests','start-review','POST',$central,true],
    // Creación por unidad P4 y función excepcional ADMIN conservadas.
    ['requests','new','GET',$p4,true],
    ['requests','download-nominal-template','GET',$p4,true],
    ['requests','download-nominal-template','POST',$p4,false],
    ['requests','download-nominal-template','GET',$central,false],
    ['requests','download-nominal-template','GET',$both,false],
    ['requests','save','POST',$p4,true],
    ['requests','add-item','POST',$p4,true],
    ['requests','submit','POST',$p4,true],
    ['requests','preview-nominal-file','POST',$p4,true],
    ['requests','preview-nominal-file','GET',$p4,false],
    ['requests','preview-nominal-file','POST',$central,false],
    ['requests','preview-nominal-file','POST',$both,false],
    ['requests','import-nominal-file','POST',$p4,false],
    ['requests','import-from-nominal-preview','POST',$p4,false],
    ['requests','new','GET',$admin,true],
    ['requests','save','POST',$admin,true],
    ['requests','central-review','GET',$p4,false],
    ['requests','start-review','POST',$p4,false],
    ['requests','decide','POST',$p4,false],
    ['requests','decide','POST',$central,true],
    ['requests','submit','POST',$p4,true],
    ['distribution','new','GET',$p4,false],
    ['distribution','prepare','POST',$p4,false],
    ['distribution','authorize','POST',$p4,false],
    ['distribution','dispatch','POST',$p4,false],
    ['distribution','cancel','POST',$p4,false],
    ['distribution','receive','POST',$central,false],
    ['distribution','receive','POST',$p4,true],
    ['distribution','receive','POST',$both,true],
    ['distribution','receive','POST',$viewer,false],
    ['requests','decide','POST',$admin,true],
    ['distribution','receive','POST',$admin,true],
];
foreach ($cases as [$module,$action,$method,$user,$expected]) {
    $actual = sindesg_role_action_allowed($module,$action,$method,$user);
    if ($actual !== $expected) {
        throw new RuntimeException("Unexpected role gate: $module/$action ($method).");
    }
}
$root = dirname(__DIR__);
$router = (string) file_get_contents($root.'/public/index.php');
$requests = (string) file_get_contents($root.'/app/Views/requests/show.php');
$distribution = (string) file_get_contents($root.'/app/Views/distribution/show.php');
if (!str_contains($router, 'sindesg_role_action_allowed(')
    || !str_contains($requests, "sindesg_role_action_allowed('requests', 'central-review'")
    || !str_contains($distribution, "sindesg_role_action_allowed('distribution', 'receive'")) {
    throw new RuntimeException('Role gate must protect routes and visible workflow controls.');
}
echo "[OK] Separación operacional de roles P4 y Central.\n";
