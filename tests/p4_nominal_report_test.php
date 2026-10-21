<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Repositories/P4NominalReportRepository.php';
require dirname(__DIR__).'/app/Services/P4NominalReportState.php';

use App\Repositories\P4NominalReportRepository;
use App\Services\P4NominalReportState;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    throw new RuntimeException('pdo_sqlite required for nominal report isolation test.');
}
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('CONCAT',static fn(...$values):string=>implode('',$values),-1);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses (id INTEGER, code TEXT, name TEXT, organization_unit_name_snapshot TEXT, warehouse_type TEXT, is_active INTEGER);
CREATE TABLE security_user_p4_scopes (user_id INTEGER, warehouse_id INTEGER);
CREATE TABLE service_requests (id INTEGER, request_code TEXT, requested_at TEXT, status TEXT, p4_warehouse_id INTEGER);
CREATE TABLE service_request_items (
 id INTEGER,request_id INTEGER,product_variant_id INTEGER,beneficiary_employee_id INTEGER,
 beneficiary_label_snapshot TEXT,beneficiary_document_snapshot TEXT,
 beneficiary_unit_name_snapshot TEXT,beneficiary_position_snapshot TEXT,beneficiary_rank_snapshot TEXT,
 quantity_requested REAL,quantity_approved REAL,quantity_dispatched REAL,quantity_received REAL,quantity_delivered REAL
);
CREATE TABLE products (id INTEGER,name TEXT);
CREATE TABLE sizes (id INTEGER,name TEXT);
CREATE TABLE product_variants (id INTEGER,product_id INTEGER,size_id INTEGER,internal_code TEXT);
CREATE TABLE individual_deliveries (
 id INTEGER,delivery_code TEXT,status TEXT,p4_warehouse_id INTEGER,recipient_employee_id INTEGER,delivered_at TEXT
);
CREATE TABLE individual_delivery_items (
 id INTEGER,delivery_id INTEGER,product_variant_id INTEGER,request_item_id INTEGER,quantity REAL
);
CREATE TABLE inventory_movements (
 id INTEGER,source_key TEXT,reference_type TEXT,reference_id INTEGER,warehouse_id INTEGER,
 product_variant_id INTEGER,movement_kind TEXT,direction TEXT
);
INSERT INTO warehouses VALUES
 (6,'P4-6','Pacora','16 Zona Policial - Pacora','P4',1),
 (4,'P4-4','Colón','Colón','P4',1),
 (9,'P4-9','Inactivo','Inactivo','P4',0);
INSERT INTO security_user_p4_scopes VALUES (101,6),(101,9),(202,4),(303,6),(303,4);
INSERT INTO service_requests VALUES
 (1,'PAC-001','2026-10-19 12:00:00','approved',6),
 (2,'COL-002','2026-10-19 13:00:00','approved',4),
 (3,'INA-003','2026-10-19 14:00:00','fulfilled',9),
 (4,'PAC-PEND','2026-10-19 15:00:00','submitted',6);
INSERT INTO products VALUES (1,'Botas Tácticas');
INSERT INTO sizes VALUES (1,'7.5'),(2,'9');
INSERT INTO product_variants VALUES (1,1,1,'BT-75'),(2,1,2,'BT-90');
SQL);
$insert=$pdo->prepare('INSERT INTO service_request_items VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=160;$i++){
    $name=$i===155?'SIG%FUNC':sprintf('Funcionario %03d',$i);
    $received=$i===2?1:3;
    $delivered=match($i){1=>2,4=>3,5=>1,default=>0};
    if($i===4)$received=1;
    $insert->execute([$i,1,$i>130?2:1,1000+$i,$name,
        '8-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),
        'Pacora Operaciones', 'POS-'.$i,'Agente',3,3,3,$received,$delivered]);
}
$insert->execute([161,1,1,1001,'Funcionario 001','8-0001','Pacora Operaciones','POS-1','Agente',2,2,2,2,0]);
$insert->execute([162,4,1,9999,'Pendiente aprobación','8-9999','Pacora Operaciones','POS-4','Agente',1,0,0,0,0]);
$insert->execute([300,2,1,9001,'Funcionario Colón','C-001','Colón','POS-X','Agente',3,3,3,3,0]);
$insert->execute([301,3,1,9002,'Funcionario Inactivo','I-001','Inactivo','POS-Y','Agente',3,3,3,3,0]);
$pdo->exec(<<<'SQL'
INSERT INTO individual_deliveries VALUES
 (10,'DSP-000010','confirmed',6,1001,'2026-10-19 13:00:00'),
 (11,'DSP-000011','confirmed',6,1001,'2026-10-20 12:00:00'),
 (12,'DSP-000012','draft',6,1001,'2026-10-21 09:00:00'),
 (13,'DSP-000013','confirmed',4,1001,'2026-10-21 10:00:00');
INSERT INTO individual_delivery_items VALUES
 (20,10,1,1,1),(21,11,1,1,1),(22,12,1,1,100),(23,13,1,1,200);
INSERT INTO inventory_movements VALUES
 (50,'individual-delivery:10:item:20','individual_delivery',10,6,1,'individual_delivery','OUT'),
 (51,'individual-delivery:11:item:21','individual_delivery',11,6,1,'individual_delivery','OUT'),
 (52,'individual-delivery:13:item:23','individual_delivery',13,4,1,'individual_delivery','OUT');
SQL);
$repo=new P4NominalReportRepository($pdo);
$f=P4NominalReportRepository::filters([]);
$first=$repo->page(101,false,$f);
if($first['total']!==162 || $first['pages']!==7 || count($first['rows'])!==25) {
    throw new RuntimeException('Expected 162 authorized Pacora rows across 7 real pages.');
}
$p7=$repo->page(101,false,array_merge($f,['page'=>7]));
if($p7['page']!==7 || count($p7['rows'])!==12) {
    throw new RuntimeException('Rows after the first 100 are missing.');
}
$noAccess=$repo->page(0,false,$f);
if($noAccess['total']!==0 || $repo->options(0,false)!==['warehouses'=>[],'products'=>[],'sizes'=>[]]) {
    throw new RuntimeException('Anonymous/report scope not closed.');
}
$foreign=$repo->page(101,false,array_merge($f,['q'=>'Funcionario Colón']));
$inactive=$repo->page(101,false,array_merge($f,['warehouse_id'=>9]));
if($foreign['total']!==0 || $inactive['total']!==0) {
    throw new RuntimeException('Foreign or inactive P4 data leaked.');
}
$col=$repo->page(202,false,$f);
$central=$repo->page(101,true,$f);
if($col['total']!==1 || $central['total']!==164) {
    throw new RuntimeException('P4 scopes or ADMIN/CENTRAL breadth wrong.');
}
$options=$repo->options(101,false);
if(count($options['warehouses'])!==1 || (int)$options['warehouses'][0]['id']!==6
    || count($options['sizes'])!==2) {
    throw new RuntimeException('Filter options leak warehouses or truncate authorized sizes.');
}
if($repo->page(101,false,array_merge($f,['q'=>'%']))['total']!==1) {
    throw new RuntimeException('LIKE wildcard should be literal.');
}
if($repo->page(101,false,array_merge($f,['q'=>'Funcionario 150']))['total']!==1
    || $repo->page(101,false,array_merge($f,['request'=>'PAC-001','size_id'=>2]))['total']!==30
    || $repo->page(101,false,array_merge($f,['situation'=>'duplicate']))['total']!==2
    || $repo->page(101,false,array_merge($f,['situation'=>'partial']))['total']!==2
    || $repo->page(101,false,array_merge($f,['situation'=>'review']))['total']!==1) {
    throw new RuntimeException('Filters, duplicate detection, or individual status incorrect.');
}
$line=$repo->page(101,false,array_merge($f,['q'=>'Funcionario 001']))['rows'][0];
$classification=$line['classification'];
if((int)$line['last_safe_delivery_id']!==11
   || (int)$line['last_movement_id']!==51
   || (int)$line['confirmed_deliveries']!==2
   || abs((float)$line['evidenced_delivery_quantity']-2)>0.0001
   || $classification['logistics']!=='Recibido en P4'
   || $classification['delivery']!=='Entrega parcial al funcionario'
   || abs($classification['pending_individual']-1)>0.0001
   || abs($classification['ready_to_hand']-1)>0.0001
   || count($classification['warnings'])!==1) {
    throw new RuntimeException('Confirmed evidence, movements, quantities or duplicate alert incorrect.');
}
$state=P4NominalReportState::classify([
 'quantity_requested'=>3,'quantity_approved'=>3,'quantity_dispatched'=>3,
 'quantity_received'=>3,'quantity_delivered'=>0,'evidenced_delivery_quantity'=>0,
 'request_status'=>'approved'
]);
if($state['delivery']!=='Pendiente de entregar al funcionario'
    || $state['logistics']!=='Recibido en P4'
    || $state['pending_individual']!==3.0) {
    throw new RuntimeException('Warehouse receipt confused with individual delivery.');
}
$source=file_get_contents(dirname(__DIR__).'/app/Repositories/P4NominalReportRepository.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/deliveries/nominal-report.php');
$controller=file_get_contents(dirname(__DIR__).'/app/Controllers/DeliveryController.php');
$bootstrap=file_get_contents(dirname(__DIR__).'/app/bootstrap.php');
if(!str_contains($source,'security_user_p4_scopes')
    || !str_contains($source,'SELECT COUNT(*)')
    || !str_contains($source,'LIMIT :limit OFFSET :offset')
    || !str_contains($source,'NOT EXISTS(')
    || preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE)\s+(?:INTO|FROM|service_request|individual_)/i',$source)
    || str_contains(strtolower($source),'rrhh2029')) {
    throw new RuntimeException('Report must be read-only and SQL scoped before pagination.');
}
foreach(['name="situation"','name="warehouse_id"','name="product_id"',
 'name="size_id"','name="q"','name="unit"','name="request"',
 'nomrep-pagebar','last_safe_delivery_id','last_movement_id','warnings'] as $token) {
    if(!str_contains($view,$token)) throw new RuntimeException('UI contract: '.$token);
}
if(!str_contains($controller,"if (\$action === 'nominal-report')")
   || !str_contains($controller,'P4NominalReportRepository::filters')
   || !str_contains($bootstrap,"['index', 'new', 'show', 'nominal-report']")) {
    throw new RuntimeException('Dedicated guarded nominal report route missing.');
}

// Historical cross-P4 linkage must not expose a mixed receipt to a Pacora user.
require dirname(__DIR__).'/app/Services/P4DeliveryGuard.php';
$pdo->exec('INSERT INTO individual_delivery_items VALUES (24,11,1,300,1)');
$afterMix=$repo->page(101,false,array_merge($f,['q'=>'Funcionario 001']))['rows'][0];
if((int)$afterMix['last_safe_delivery_id']!==10) {
    throw new RuntimeException('Mixed warehouse receipt was offered as a safe link.');
}
$guard=new \App\Services\P4DeliveryGuard($pdo);
$denied=false;
try { $guard->assertDelivery(101,11); } catch (RuntimeException $e) { $denied=true; }
if(!$denied) {
    throw new RuntimeException('Mixed-warehouse direct receipt URL bypassed P4 guard.');
}
$guard->assertDelivery(101,10);

echo "[OK] P4 nominal report: count/page, 160+ rows, isolation, evidence, state, anomalies and UI.\n";