<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$central = ['role_codes' => ['CENTRAL']];
$p4 = ['role_codes' => ['P4']];
$admin = ['role_codes' => ['ADMIN']];
$viewer = ['role_codes' => ['VIEWER']];
$both = ['role_codes' => ['CENTRAL','P4']];

$cases = [
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
    ['distribution','receive','POST',$p4,false],
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
