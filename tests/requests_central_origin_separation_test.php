<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$read = static function (string $name) use ($root): string {
    $path = $root.'/'.$name;
    $data = file_get_contents($path);
    if (!is_string($data)) throw new RuntimeException('Archivo inaccesible: '.$name);
    return str_replace(["\r\n","\r"], "\n", $data);
};
$central = ['role_codes'=>['CENTRAL']];
$both = ['role_codes'=>['CENTRAL','P4']];
$p4 = ['role_codes'=>['P4']];
$admin = ['role_codes'=>['ADMIN']];
$policy = [
    ['new','GET',false],['save','POST',false],
    ['add-item','POST',false],['remove-item','POST',false],
    ['submit','POST',false],['import-nominal-file','POST',false],
    ['preview-nominal-file','POST',false],
    ['import-from-nominal-preview','POST',false],
    ['add-nominal-bulk','POST',false],
    ['validate-nominal-row','POST',false],
    ['validate-nominal-selected','POST',false],
    ['preview-boots','POST',false],['import-boots','GET',false],
    ['download-nominal-template','GET',false],
    ['index','GET',true],['show','GET',true],
    ['central-review','GET',true],
    ['start-review','POST',true],['decide','POST',true],
    ['reopen-rejected','POST',false]
];
foreach ([$central,$both] as $user) {
    foreach ($policy as [$action,$method,$allowed]) {
        $assert(sindesg_role_action_allowed('requests',$action,$method,$user)===$allowed,
            "Central no cumple separación: $action/$method");
    }
}
foreach (['new','show'] as $action) {
    $assert(sindesg_role_action_allowed('requests',$action,'GET',$p4), 'P4 perdió '.$action);
}
foreach (['save','add-item','remove-item','submit'] as $action) {
    $assert(sindesg_role_action_allowed('requests',$action,'POST',$p4), 'P4 perdió '.$action);
}
$assert(!sindesg_role_action_allowed('requests','decide','POST',$p4), 'P4 no puede decidir');
$assert(sindesg_role_action_allowed('requests','new','GET',$admin), 'ADMIN excepción no disponible');
$assert(sindesg_role_action_allowed('requests','save','POST',$admin), 'ADMIN excepción no disponible');
$assert(sindesg_role_action_allowed('requests','central-review','GET',$admin), 'ADMIN consulta perdida');

$controller = $read('app/Controllers/RequestsController.php');
$index = $read('app/Views/requests/index.php');
$show = $read('app/Views/requests/show.php');
$css = $read('public/assets/css/requests.css');
$bootstrap = $read('app/bootstrap.php');
$router = $read('public/index.php');
foreach ([
 ['role guard in router','sindesg_role_action_allowed($currentModule', $router],
 ['central denial','Almacén Central recibe, revisa y decide solicitudes', $controller],
 ['controller-check',"!sindesg_role_action_allowed('requests', \$action, \$method, \$current)", $controller],
 ['P4 warehouse SQL guard','P4RequestGuard(', $controller],
 ['P4 requester binding',"\$post['requested_by_employee_id'] = (int) (\$current['employee_id']", $controller],
 ['central request policy',"if (\$module === 'requests' && in_array('CENTRAL', \$roles, true))", $bootstrap],
 ['central allowed review',"'start-review', 'decide'", $bootstrap],
 ['hide new button', "sindesg_role_action_allowed('requests', 'new', 'GET'", $index],
 ['hide import button', "sindesg_role_action_allowed('requests', 'import-boots', 'GET'", $index],
 ['hide draft send prompt', "sindesg_role_action_allowed('requests', 'submit', 'POST'", $index],
 ['read-only draft on Central',"sindesg_role_action_allowed('requests', 'submit', 'POST'", $show],
 ['central review button',"sindesg_role_action_allowed('requests', 'central-review', 'GET'", $show],
 ['restrict P4 form', "sindesg_role_action_allowed('requests', 'submit', 'POST'", $show],
 ['grid minmax', 'grid-template-columns: repeat(2, minmax(0, 1fr))', $css],
 ['form minwidth', '.request-form-grid > label', $css],
 ['mobile stack', '.request-form-grid { grid-template-columns: minmax(0,1fr); }', $css],
] as [$name,$needle,$haystack]) {
    $assert(str_contains($haystack,$needle), 'Contrato ausente: '.$name);
}
$assert(substr_count($show,"sindesg_role_action_allowed('requests', 'submit', 'POST'") >= 6,
    'Pueden reaparecer controles de P4 en la vista Central.');
$assert(str_contains($index,'La creación corresponde a cada unidad P4.'),
    'El usuario Central no recibe una explicación de la restricción.');

echo "[OK] Solicitudes P4/Central: origen por P4, revisión Central, controles UI seguros y formulario responsivo.\n";
