<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Repositories\P4IncidentRepository;
$check=static function(bool $yes,string $why):void {if(!$yes)throw new RuntimeException($why);};
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('GREATEST',static fn($a,$b)=>max((float)$a,(float)$b),2);
$db->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER PRIMARY KEY,name TEXT,organization_unit_name_snapshot TEXT,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE security_user_p4_scopes(user_id INTEGER,warehouse_id INTEGER);
CREATE TABLE products(id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE sizes(id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE product_variants(id INTEGER PRIMARY KEY,product_id INTEGER,size_id INTEGER);
CREATE TABLE individual_deliveries(id INTEGER PRIMARY KEY,delivery_code TEXT,recipient_employee_id INTEGER,recipient_label_snapshot TEXT,recipient_document_snapshot TEXT,p4_warehouse_id INTEGER,status TEXT);
CREATE TABLE individual_delivery_items(id INTEGER PRIMARY KEY,delivery_id INTEGER,product_variant_id INTEGER,request_item_id INTEGER,quantity REAL);
CREATE TABLE inventory_adjustments(id INTEGER PRIMARY KEY,source_delivery_item_id INTEGER,status TEXT,adjustment_type TEXT,quantity REAL,warehouse_id INTEGER,return_condition TEXT);
CREATE TABLE p4_incident_cases(
 id INTEGER PRIMARY KEY AUTOINCREMENT,case_code TEXT,delivery_item_id INTEGER,warehouse_id INTEGER,
 recipient_employee_id INTEGER,product_variant_id INTEGER,case_type TEXT,quantity REAL,
 physically_received INTEGER,item_condition TEXT,reason TEXT,status TEXT,
 created_by_user_id INTEGER,reviewed_by_user_id INTEGER,reviewed_at TEXT,
 closed_by_user_id INTEGER,closed_at TEXT,resolution_notes TEXT,linked_adjustment_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE p4_incident_events(
 id INTEGER PRIMARY KEY AUTOINCREMENT,case_id INTEGER,actor_user_id INTEGER,
 action_code TEXT,note TEXT,occurred_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE inventory_movements(id INTEGER PRIMARY KEY);
INSERT INTO warehouses VALUES(3,'P4 Oeste','Zona Oeste','P4',1),(4,'P4 Norte','Zona Norte','P4',1),(9,'Central','Central','CENTRAL',1);
INSERT INTO security_user_p4_scopes VALUES(10,3),(20,4),(10,9);
INSERT INTO products VALUES(1,'Botas Tácticas');
INSERT INTO sizes VALUES(5,'9');
INSERT INTO product_variants VALUES(7,1,5);
INSERT INTO individual_deliveries VALUES
 (15,'DSP-000015',101,'Funcionario de prueba','DOCUMENTO-TEST',3,'confirmed'),
 (16,'DSP-000016',102,'Otra unidad','DOCUMENTO-2',4,'confirmed'),
 (17,'DSP-000017',103,'Borrador','DOCUMENTO-3',3,'draft');
INSERT INTO individual_delivery_items VALUES (85,15,7,1465,1),(86,16,7,1466,1),(87,17,7,1467,1),(88,15,7,NULL,1);
SQL);
$repo=new P4IncidentRepository($db);
$source=$repo->sourceForP4(10,85);
$check((int)$source['warehouse_id']===3&&(float)$source['returnable_quantity']===1.0,
    'Debe leer la entrega confirmada original de un P4 asignado.');
foreach([[10,86],[20,85],[10,87],[10,88],[0,85],[10,0]] as [$u,$item]){
    $check($repo->sourceForP4($u,$item)===null,'Se filtró una entrega ajena, no confirmada o sin vínculo nominal.');
}
$input=['case_type'=>'return','quantity'=>'1.000','physically_received'=>'1',
 'item_condition'=>'reusable','reason'=>'Cambio de talla presencial solicitado por el beneficiario.'];
try {
    $repo->open(20,85,$input);
    throw new LogicException('Admitió incidencia ajena.');
}catch(RuntimeException $ok){}
$bad=$input;$bad['physically_received']='';
try {$repo->open(10,85,$bad);throw new LogicException('Se aceptó devolución sin prenda.');}
catch(InvalidArgumentException $ok){}
$bad=$input;$bad['quantity']='1.500';
try {$repo->open(10,85,$bad);throw new LogicException('Se excedió cantidad nominal.');}
catch(InvalidArgumentException $ok){}
$id=$repo->open(10,85,$input);
$check($id>0,'No creó expediente.');
$check((int)$db->query('SELECT COUNT(*) FROM inventory_movements')->fetchColumn()===0,
    'La recepción de incidencia jamás modifica kardex.');
$check((int)$db->query('SELECT COUNT(*) FROM p4_incident_events')->fetchColumn()===1,
    'Debe crear evento auditable de recepción.');
try {$repo->open(10,85,$input);throw new LogicException('Duplicó el caso activo.');}
catch(InvalidArgumentException $ok){}
$check($repo->find(20,$id,false)===null,'P4 ajeno consultó expediente.');
$check($repo->find(10,$id,false)!==null,'P4 titular debe consultar su expediente.');
$check($repo->listing(10,false)['total']===1 && $repo->listing(20,false)['total']===0,
    'El listado P4 debe filtrarse desde SQL por almacén activo.');
$check($repo->listing(300,true)['total']===1,'Central debe consultar la cola general.');
$repo->transition($id,300,'review');
$check($repo->find(300,$id,true)['status']==='in_review','El caso no avanzó a revisión.');
try {$repo->transition($id,400,'resolve','Autorizar cambio correctamente.',0);
 throw new LogicException('Cierre de devolución sin ajuste confirmado.');}
catch(InvalidArgumentException $ok){}
$db->exec("INSERT INTO inventory_adjustments VALUES(5,86,'confirmed','return',1,4,'reusable')");
try {$repo->transition($id,400,'resolve','Ajuste de otra zona no debe aceptarse.',5);
 throw new LogicException('Se aceptó ajuste ajeno.');}
catch(InvalidArgumentException $ok){}
$db->exec("INSERT INTO inventory_adjustments VALUES(6,85,'confirmed','return',1,3,'reusable')");
$repo->transition($id,400,'resolve','Entrega compensada con ajuste confirmado.',6);
$final=$repo->find(300,$id,true);
$check($final['status']==='resolved'&&(int)$final['linked_adjustment_id']===6,
    'Debe vincular exactamente la entrega a un ajuste ya confirmado.');
$check(count($repo->history($id))===3,'Falta historial inmutable apertura-revisión-cierre.');
$check((int)$db->query('SELECT COUNT(*) FROM inventory_movements')->fetchColumn()===0,
    'El módulo de incidencias no debe insertar movimientos.');
$roles=[
 ['incidents','new','GET',['role_codes'=>['P4']],true],
 ['incidents','save','POST',['role_codes'=>['P4']],true],
 ['incidents','show','GET',['role_codes'=>['P4']],true],
 ['incidents','review','POST',['role_codes'=>['P4']],false],
 ['incidents','resolve','POST',['role_codes'=>['P4']],false],
 ['incidents','index','GET',['role_codes'=>['CENTRAL']],true],
 ['incidents','new','GET',['role_codes'=>['CENTRAL']],false],
 ['incidents','save','POST',['role_codes'=>['CENTRAL']],false],
 ['incidents','review','POST',['role_codes'=>['CENTRAL']],true],
 ['incidents','resolve','POST',['role_codes'=>['CENTRAL']],false],
 ['incidents','resolve','POST',['role_codes'=>['ADMIN']],true],
 ['incidents','save','POST',['role_codes'=>['ADMIN']],false],
 ['incidents','index','GET',['role_codes'=>['VIEWER']],false],
 ['incidents','save','GET',['role_codes'=>['P4']],false],
 ['incidents','review','GET',['role_codes'=>['CENTRAL']],false],
 ['incidents','resolve','POST',['role_codes'=>['CENTRAL','P4']],false]
];
foreach($roles as [$module,$action,$method,$user,$expected]){
    $check(sindesg_role_action_allowed($module,$action,$method,$user)===$expected,
        'Permisos incorrectos: '.$action.' '.$method);
}
$root=dirname(__DIR__);
$route=(string)file_get_contents($root.'/public/index.php');
$guard=(string)file_get_contents($root.'/app/bootstrap.php');
$controller=(string)file_get_contents($root.'/app/Controllers/P4IncidentController.php');
$receipt=(string)file_get_contents($root.'/app/Views/deliveries/show.php');
$central=(string)file_get_contents($root.'/app/Views/adjustments/index.php');
$schema=(string)file_get_contents($root.'/database/schema/023_p4_incident_intake.sql');
$repository=(string)file_get_contents($root.'/app/Repositories/P4IncidentRepository.php');
foreach([
 [$route,"\$currentModule === 'incidents'"],
 [$guard,"if (\$module === 'incidents')"],
 [$controller,'csrf_valid('],
 [$controller,'$this->cases->open('],
 [$controller,'$this->cases->transition('],
 [$receipt,'?module=incidents&action=new&delivery_item_id='],
 [$central,'?module=incidents'],
 [$schema,'CREATE TABLE IF NOT EXISTS p4_incident_cases'],
 [$schema,'CREATE TABLE IF NOT EXISTS p4_incident_events'],
 [$repository,'security_user_p4_scopes'],
 [$repository,'this->pdo->beginTransaction()'],
] as [$haystack,$fragment]) {
    $check(str_contains($haystack,$fragment),'Elemento de seguridad o flujo ausente: '.$fragment);
}
$check(!str_contains($repository,'INSERT INTO inventory_movements')
 && !str_contains($repository,'UPDATE individual_deliveries')
 && !str_contains($repository,'UPDATE service_request_items')
 && !str_contains($schema,'DELETE FROM'),'La incidencia no debe corregir datos históricos ni kardex.');
echo "[OK] Incidencias P4: 403, ámbito por almacén, recepción física, revisión, resolución, auditoría y kardex intacto.\n";
