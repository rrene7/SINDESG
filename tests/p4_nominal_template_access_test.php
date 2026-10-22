<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$p4 = ['role_codes' => ['P4']];
$central = ['role_codes' => ['CENTRAL']];
$both = ['role_codes' => ['P4','CENTRAL']];
$admin = ['role_codes' => ['ADMIN']];
$viewer = ['role_codes' => ['VIEWER']];

$assert(sindesg_role_action_allowed('requests','download-nominal-template','GET',$p4),
    'P4 no puede descargar plantilla Excel vacía.');
$assert(sindesg_permission_for('requests','download-nominal-template','GET') === 'requests.view',
    'Descargar plantilla debe requerir permiso de lectura de Solicitudes.');
$assert(!sindesg_role_action_allowed('requests','download-nominal-template','POST',$p4),
    'La ruta de plantilla no debe aceptar POST para P4.');
foreach ([$central,$both,$viewer] as $actor) {
    $assert(!sindesg_role_action_allowed('requests','download-nominal-template','GET',$actor),
        'Plantilla habilitada para rol distinto de P4/ADMIN.');
}
$assert(sindesg_role_action_allowed('requests','import-from-nominal-preview','POST',$p4)
    && sindesg_role_action_allowed('requests','validate-nominal-selected','POST',$p4),
    'El P4 debe conservar el flujo validación + carga al borrador.');
$assert(sindesg_role_action_allowed('requests','download-nominal-template','GET',$admin),
    'ADMIN perdió acceso existente a plantillas.');

foreach ([
    ['import-nominal-file','POST'],
    ['validate-nominal-row','POST'],
    ['validate-nominal-selected','POST'],
    ['central-review','GET'],
    ['decide','POST'],
] as [$action,$method]) {
    $assert(!sindesg_role_action_allowed('requests',$action,$method,$p4),
        "Permiso adicional no autorizado para P4: $action/$method.");
}
foreach (['new','show'] as $action) {
    $assert(sindesg_role_action_allowed('requests',$action,'GET',$p4),
        'Se perdió pantalla de trabajo P4.');
}

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Controllers/RequestsController.php');
$view = file_get_contents($root.'/app/Views/requests/show.php');
$template = file_get_contents($root.'/app/Services/P4NominalXlsxTemplate.php');
foreach (['controller'=>$controller,'view'=>$view,'template'=>$template] as $name=>$source) {
    $assert(is_string($source) && $source!=='','Archivo inexistente: '.$name);
}
$controller = str_replace(["\r\n","\r"],"\n",$controller);
$assert(str_contains($controller, "'download-nominal-template'"),
    'Controlador no acepta la descarga por nombre.');
$assert(str_contains($controller, "['new', 'show', 'download-nominal-template']"),
    'Controlador P4 sigue bloqueando la descarga.');
$assert(str_contains($controller, "if (\$action === 'download-nominal-template')"),
    'Controlador no maneja la descarga por la ruta GET.');
$assert(str_contains($controller,'new P4NominalXlsxTemplate()'),
    'No utiliza el generador de XLSX existente.');
$assert(str_contains($controller,'Content-Disposition: attachment; filename='),
    'La respuesta no fuerza la descarga de archivo.');
$assert(str_contains($controller,'Cache-Control: no-store'),
    'Descarga sin restricción de caché.');
$assert(str_contains($view, "action=download-nominal-template"),
    'La vista P4 no contiene enlace de descarga.');
$assert(str_contains($template,'No contiene datos de funcionarios ni stock.'),
    'La plantilla podría contener datos personales o de inventario.');
$assert(!str_contains($template,'SELECT ') && !str_contains($template,'FROM security_'),
    'La plantilla no debe hacer consultas SQL.');

echo "[OK] P4: descarga XLSX en GET con permiso requests.view; POST, importación, Central y datos ajenos bloqueados.\n";
