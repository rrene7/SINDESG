<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/DeliveryRepository.php';
use App\Repositories\DeliveryRepository;

$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

// Exercise rollback using an in-memory DB, never any institutional records.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses (id INTEGER PRIMARY KEY,warehouse_type TEXT,is_active INTEGER);
CREATE TABLE service_requests(id INTEGER PRIMARY KEY,p4_warehouse_id INTEGER,status TEXT);
CREATE TABLE service_request_items (
 id INTEGER PRIMARY KEY, request_id INTEGER,product_variant_id INTEGER,
 beneficiary_employee_id INTEGER, quantity_received NUMERIC,quantity_delivered NUMERIC
);
CREATE TABLE individual_deliveries (
 id INTEGER PRIMARY KEY AUTOINCREMENT,delivery_code TEXT,
 p4_warehouse_id INTEGER,recipient_employee_id INTEGER,
 recipient_label_snapshot TEXT,recipient_document_snapshot TEXT,
 recipient_position_snapshot TEXT,recipient_rank_snapshot TEXT,
 recipient_unit_id_snapshot INTEGER,recipient_unit_name_snapshot TEXT,
 recipient_direction_snapshot TEXT,recipient_office_snapshot TEXT,
 delivered_by_employee_id INTEGER,delivered_by_label_snapshot TEXT,
 delivered_by_document_snapshot TEXT,delivered_by_position_snapshot TEXT,
 delivered_by_rank_snapshot TEXT,delivered_at TEXT,document_number TEXT,
 notes TEXT,scope_override_enabled INTEGER,scope_override_reason TEXT,
 status TEXT
);
CREATE TABLE individual_delivery_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,delivery_id INTEGER,
 product_variant_id INTEGER,request_item_id INTEGER,quantity NUMERIC,notes TEXT
);
INSERT INTO warehouses VALUES (6,'P4',1);
INSERT INTO service_requests VALUES (1,6,'fulfilled');
INSERT INTO service_request_items VALUES (11,1,8,321,1,0);
SQL);
$repo = new DeliveryRepository($pdo);
$data = [
 'p4_warehouse_id'=>6,'recipient_employee_id'=>321,
 'recipient_label_snapshot'=>'Persona de prueba','recipient_document_snapshot'=>'ANON',
 'recipient_position_snapshot'=>'1','recipient_rank_snapshot'=>'Rango',
 'recipient_unit_id_snapshot'=>null,'recipient_unit_name_snapshot'=>'Unidad P4',
 'recipient_direction_snapshot'=>'','recipient_office_snapshot'=>'',
 'delivered_by_employee_id'=>777,'delivered_by_label_snapshot'=>'Operador P4',
 'delivered_by_document_snapshot'=>'PRUEBA','delivered_by_position_snapshot'=>'2',
 'delivered_by_rank_snapshot'=>'Rango','delivered_at'=>'2026-10-23 12:00:00',
 'document_number'=>null,'notes'=>null,'scope_override_enabled'=>0,
 'scope_override_reason'=>null
];
$line = ['request_item_id'=>11,'product_variant_id'=>8,
 'beneficiary_employee_id'=>321,'p4_warehouse_id'=>6,'request_code'=>'SOL-TEST'];
try {
    $repo->createAndConfirmNominal(
        static fn(): int => $repo->createDraft($data,$line,1.0),
        ['png'=>'invalid','actor'=>100,'issuer_user_id'=>100,'issuer_employee_id'=>777]
    );
    throw new LogicException('La confirmación inválida no debía aprobarse.');
} catch (Throwable $expected) {
    $check(!($expected instanceof LogicException), 'Se confirmó un despacho inválido.');
}
$check(!$pdo->inTransaction()
    && (int)$pdo->query('SELECT COUNT(*) FROM individual_deliveries')->fetchColumn()===0
    && (int)$pdo->query('SELECT COUNT(*) FROM individual_delivery_items')->fetchColumn()===0,
    'La confirmación fallida dejó un borrador huérfano o una transacción abierta.');
$check((float)$pdo->query('SELECT quantity_delivered FROM service_request_items WHERE id=11')->fetchColumn()===0.0,
    'El fallo alteró la cantidad nominal entregada.');
// The failed direct delivery must not lock the beneficiary into a draft.
$draftId=$repo->createDraft($data,$line,1.0);
$check($draftId>0 && (int)$pdo->query('SELECT COUNT(*) FROM individual_deliveries')->fetchColumn()===1,
    'No se permite volver a crear una entrega después de la reversión atómica.');

$root = dirname(__DIR__);
$controller=(string)file_get_contents($root.'/app/Controllers/DeliveryController.php');
$service=(string)file_get_contents($root.'/app/Services/DeliveryService.php');
$repository=(string)file_get_contents($root.'/app/Repositories/DeliveryRepository.php');
$view=(string)file_get_contents($root.'/app/Views/deliveries/p4-quick.php');
$js=(string)file_get_contents($root.'/public/assets/js/deliveries.js');
$css=(string)file_get_contents($root.'/public/assets/css/deliveries.css');
$show=(string)file_get_contents($root.'/app/Views/deliveries/show.php');
$router=(string)file_get_contents($root.'/public/index.php');
$roleGate=(string)file_get_contents($root.'/app/bootstrap.php');
require $root.'/app/bootstrap.php';

// Reproduce precisely the P4 403 on POST /?module=deliveries&action=deliver-now.
$p4=['role_codes'=>['P4']];
$central=['role_codes'=>['CENTRAL']];
$viewer=['role_codes'=>['VIEWER']];
$dual=['role_codes'=>['CENTRAL','P4']];
$admin=['role_codes'=>['ADMIN']];
$check(sindesg_permission_for('deliveries','deliver-now','POST') === 'deliveries.manage',
    'La entrega directa debe conservar permiso RBAC deliveries.manage.');
$check(sindesg_role_action_allowed('deliveries','deliver-now','POST',$p4),
    'El router debe permitir POST deliver-now al P4 autenticado.');
foreach ([
    ['deliveries','deliver-now','GET',$p4],
    ['deliveries','deliver-now','POST',$central],
    ['deliveries','deliver-now','POST',$viewer],
    ['deliveries','deliver-now','POST',$dual],
    ['deliveries','deliver-now','POST',$admin],
    ['deliveries','add-item','POST',$p4],
    ['deliveries','cancel','POST',$p4],
] as [$module,$action,$method,$user]) {
    $check(!sindesg_role_action_allowed($module,$action,$method,$user),
        'El router no debe ampliar otros permisos: '.$action.'/'.$method);
}
$check(str_contains($router,'sindesg_role_action_allowed(')
    && str_contains($router,'$authService->can($requiredPermission)'),
    'La ruta debe conservar controles RBAC y de rol antes del controlador.');
$check(str_contains($roleGate,"'save', 'confirm', 'deliver-now'"),
    'La lista P4 debe contener la acción directa sin habilitar operaciones de Central.');
foreach ([
 [$controller,"'deliver-now'"],[$controller,'$guard->assertNominal('],
 [$controller,'csrf_valid('],[$controller,'$this->isP4Only()'],
 [$controller,'p4SignatureEvidence($post, true)'],[$controller,"'delivered_at'"],
 [$controller,"'recipient_ack'"],[$controller,"'issuer_ack'"],
 [$controller,'P4IssuerSignature::validatePng($png)'],
 [$service,'deliverNominalNow('],[$repository,'createAndConfirmNominal('],
 [$repository,'$ownsTransaction = !$this->pdo->inTransaction()'],
 [$view,'data-p4-direct-signature'],[$view,'action=deliver-now'],
 [$view,'name="linked_quantity"'],[$view,'name="recipient_ack"'],
 [$view,'name="issuer_ack"'],[$view,'name="recipient_signature_png"'],
 [$view,'data-p4-direct-submit'],[$js,"canvas.toDataURL('image/png')"],
 [$js,'data-p4-direct-canvas'],[$css,'.p4-direct-grid'],
 [$show,'Imprimir comprobante']
] as [$source,$part]) {
    $check(str_contains($source,$part),'Falta un control de la entrega directa: '.$part);
}
$check(!str_contains($view,'name="delivered_by_employee_id"')
    && !str_contains($view,'name="recipient_employee_id"')
    && !str_contains($view,'name="p4_warehouse_id"')
    && !str_contains($view,'name="delivered_at"')
    && !str_contains($view,'Crear borrador'),
    'El formulario directo expone campos manipulables o exige datos redundantes.');
echo "[OK] Entrega P4 directa: operación única, firma, autorización y rollback sin borrador huérfano.\n";
