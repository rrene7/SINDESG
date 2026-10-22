<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$admin = ['role_codes' => ['ADMIN']];
$central = ['role_codes' => ['CENTRAL']];
$centralWithP4 = ['role_codes' => ['CENTRAL', 'P4']];
$p4 = ['role_codes' => ['P4']];
$viewer = ['role_codes' => ['VIEWER']];
$cases = [
    // Almacén Central: consultar P4, pero no configurar su estructura.
    ['warehouses','index','GET',$central,true],
    ['warehouses','show','GET',$central,true],
    ['warehouses','new','GET',$central,false],
    ['warehouses','save','POST',$central,false],
    ['warehouses','toggle','POST',$central,false],
    ['warehouses','unknown','POST',$central,false],
    ['warehouses','new','GET',$centralWithP4,false],
    ['warehouses','toggle','POST',$centralWithP4,false],
    ['warehouses','show','GET',$centralWithP4,true],
    // P4 no debe recuperar accidentalmente la vista de almacenes ajenos.
    ['warehouses','index','GET',$p4,false],
    ['warehouses','show','GET',$p4,false],
    ['warehouses','toggle','POST',$p4,false],
    // VIEWER conserva lectura de la red sin obtener facultades de escritura.
    ['warehouses','index','GET',$viewer,true],
    ['warehouses','show','GET',$viewer,true],
    ['warehouses','toggle','POST',$viewer,false],
    ['warehouses','new','GET',$viewer,false],
    // ADMIN mantiene habilitación, inactivación y reactivación según RBAC.
    ['warehouses','index','GET',$admin,true],
    ['warehouses','new','GET',$admin,true],
    ['warehouses','save','POST',$admin,true],
    ['warehouses','toggle','POST',$admin,true],
    // Importación histórica y la preparación masiva del catálogo son ADMIN.
    ['migration','index','GET',$central,false],
    ['migration','show','GET',$central,false],
    ['migration','upload','POST',$central,false],
    ['migration','prepare-catalog','POST',$central,false],
    ['migration','archive-ready','POST',$central,false],
    ['migration','finalize-references','POST',$central,false],
    ['migration','index','GET',$p4,false],
    ['migration','upload','POST',$viewer,false],
    ['migration','index','GET',$admin,true],
    ['migration','upload','POST',$admin,true],
    ['inventory','prepare-footwear','POST',$central,false],
    ['inventory','prepare-footwear','POST',$admin,true],
    // No se deben retirar operaciones legítimas del almacenista.
    ['inventory','index','GET',$central,true],
    ['inventory','new-product','GET',$central,true],
    ['inventory','save-product','POST',$central,true],
    ['inventory','save-variant','POST',$central,true],
    ['receipts','new','GET',$central,true],
    ['receipts','confirm','POST',$central,true],
    ['production','new','GET',$central,true],
    ['production','confirm','POST',$central,true],
    ['requests','central-review','GET',$central,true],
    ['requests','decide','POST',$central,true],
    ['requests','new','GET',$central,false],
    ['distribution','prepare','POST',$central,true],
    ['distribution','dispatch','POST',$central,true],
    ['adjustments','save','POST',$central,true],
    ['adjustments','confirm','POST',$central,false],
    ['admin','roles','GET',$central,false],
    ['admin','save-role','POST',$central,false],
    ['inventory','categories','GET',$central,false],
    ['inventory','update-category','POST',$central,false],
    ['inventory','update-product','POST',$central,false],
];

foreach ($cases as [$module, $action, $method, $actor, $expected]) {
    $actual = sindesg_role_action_allowed($module,$action,$method,$actor);
    $assert($actual === $expected,
        "Límite incorrecto para $module/$action $method: "
        . implode(',', $actor['role_codes']));
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $text = file_get_contents($root.'/'.$path);
    if (!is_string($text)) throw new RuntimeException('No se encontró: '.$path);
    return str_replace(["\r\n","\r"], "\n", $text);
};
$bootstrap = $read('app/bootstrap.php');
$warehouseCtl = $read('app/Controllers/WarehousesController.php');
$migrationCtl = $read('app/Controllers/HistoricalImportController.php');
$inventoryCtl = $read('app/Controllers/InventoryController.php');
$listView = $read('app/Views/warehouses/index.php');
$detailView = $read('app/Views/warehouses/show.php');
$seed = $read('database/schema/015_security_audit.sql');
$router = $read('public/index.php');

foreach ([
    ['warehouse access through router', 'sindesg_role_action_allowed($currentModule', $router],
    ['warehouse authorization defense', "!sindesg_role_action_allowed('warehouses', \$action, \$method", $warehouseCtl],
    ['warehouse permission defense', "sindesg_can(sindesg_permission_for('warehouses', \$action, \$method)", $warehouseCtl],
    ['direct warehouse forbidden message', 'Administración de P4 restringida.', $warehouseCtl],
    ['warehouse list show new only ADMIN', "sindesg_role_action_allowed('warehouses', 'new', 'GET'", $listView],
    ['warehouse detail show toggle only ADMIN', "sindesg_role_action_allowed('warehouses', 'toggle', 'POST'", $detailView],
    ['warehouse detail RBAC check', "sindesg_can('warehouses.manage')", $detailView],
    ['migration denial in controller', "!sindesg_role_action_allowed('migration', \$action, \$method", $migrationCtl],
    ['bulk catalog controller guard', "'prepare-footwear'", $inventoryCtl],
    ['migration nonadmin refusal in router', "if (\$module === 'migration')", $bootstrap],
    ['warehouses nonadmin read-only in router', "if (\$module === 'warehouses')", $bootstrap],
    ['role grants seed', "'warehouses.view',", $seed],
] as [$name,$needle,$haystack]) {
    $assert(str_contains($haystack,$needle),'Contrato administrativo ausente: '.$name);
}

// Future installations must not seed warehouses.manage to CENTRAL.
$centralSeed = explode('-- CENTRAL.', $seed, 2)[1] ?? '';
$centralSeed = explode('-- P4.', $centralSeed, 2)[0];
$assert($centralSeed !== '' && !str_contains($centralSeed,"'warehouses.manage'"),
    'La plantilla de roles futuros concede administración de P4 a CENTRAL.');

// Operational proof: Central sees no management form even if RBAC is granted.
// Protection is server-side; merely hiding buttons is not sufficient.
$assert(!str_contains($warehouseCtl, "return \$this->handlePost(\$action, \$post);")
    || str_contains($warehouseCtl, "sindesg_role_action_allowed('warehouses'"),
    'El controlador de P4 necesita barrera real.');
echo "[OK] Permisos Central: P4 read-only, provision/toggle ADMIN, histórico restringido y operación diaria intacta.\n";
