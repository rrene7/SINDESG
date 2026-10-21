<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/DeliveryRepository.php';
use App\Repositories\DeliveryRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite required for isolated nominal pagination test.');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('GREATEST', static fn (...$values) => max($values), -1);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses (id INTEGER, warehouse_type TEXT, is_active INTEGER, organization_unit_name_snapshot TEXT);
CREATE TABLE security_user_p4_scopes (user_id INTEGER, warehouse_id INTEGER);
CREATE TABLE service_requests (id INTEGER, request_code TEXT, p4_warehouse_id INTEGER, status TEXT, requested_at TEXT);
CREATE TABLE service_request_items (
 id INTEGER, request_id INTEGER, product_variant_id INTEGER,
 beneficiary_employee_id INTEGER, beneficiary_label_snapshot TEXT,
 beneficiary_document_snapshot TEXT, quantity_received REAL, quantity_delivered REAL
);
CREATE TABLE product_variants (id INTEGER, product_id INTEGER, internal_code TEXT, size_id INTEGER);
CREATE TABLE products (id INTEGER, name TEXT);
CREATE TABLE sizes (id INTEGER, name TEXT);
CREATE TABLE individual_deliveries (id INTEGER, p4_warehouse_id INTEGER, recipient_employee_id INTEGER, status TEXT);
CREATE TABLE individual_delivery_items (id INTEGER, delivery_id INTEGER, request_item_id INTEGER);
INSERT INTO warehouses VALUES
 (6,'P4',1,'Pacora'),(4,'P4',1,'Colón'),(9,'P4',0,'Inactivo');
INSERT INTO security_user_p4_scopes VALUES (101,6),(101,9),(202,4);
INSERT INTO service_requests VALUES
 (1,'PACORA-A',6,'fulfilled','2026-10-14'),
 (2,'PACORA-B',6,'approved','2026-10-15'),
 (3,'COLON-X',4,'fulfilled','2026-10-14'),
 (4,'INACTIVE',9,'fulfilled','2026-10-15');
INSERT INTO products VALUES (1,'Botas Tácticas');
INSERT INTO sizes VALUES (1,'7.5'),(2,'9.5');
INSERT INTO product_variants VALUES (1,1,'T-75',1),(2,1,'T-95',2);
SQL);
$stmt = $pdo->prepare(
    'INSERT INTO service_request_items
     (id,request_id,product_variant_id,beneficiary_employee_id,
      beneficiary_label_snapshot,beneficiary_document_snapshot,
      quantity_received,quantity_delivered)
     VALUES (?,?,?,?,?,?,?,?)'
);
for ($i = 1; $i <= 160; $i++) {
    $request = $i <= 130 ? 1 : 2;
    $variant = $i <= 130 ? 1 : 2;
    $name = $i === 155 ? 'SIG%FUNC' : sprintf('Funcionario %03d', $i);
    $stmt->execute([$i, $request, $variant, 1000+$i,
        $name, 'P-'.sprintf('%04d',$i), 1, 0]);
}
for ($i = 161; $i <= 170; $i++) {
    $stmt->execute([$i, 3, 1, 1000+$i, 'Solo Colón', 'C-'.$i, 1, 0]);
}
$stmt->execute([171,4,1,4000,'Almacén inactivo','I-171',1,0]);
$stmt->execute([172,1,1,4001,'Ya entregado','P-172',1,1]);
$stmt->execute([173,1,1,null,'Consolidado','P-173',1,0]);

// Two open drafts for one recipient; a confirmed and a foreign-warehouse
// draft must not be reported as drafts belonging to this nominal line.
$pdo->exec(<<<'SQL'
INSERT INTO individual_deliveries VALUES
 (501,6,1001,'draft'),(502,6,1001,'draft'),
 (503,6,1001,'confirmed'),(504,4,1001,'draft'),
 (505,6,1155,'draft'),(506,6,1002,'draft'),(507,6,1003,'draft'),
 (508,4,1004,'draft');
INSERT INTO individual_delivery_items VALUES
 (1,501,1),(2,502,1),(3,503,1),(4,504,1),
 (5,505,155),(6,506,2),(7,507,3),(8,507,NULL),
 (9,508,4);
SQL);
$repo = new DeliveryRepository($pdo);
$p1 = $repo->pendingNominalPageForUser(101);
if ($p1['total'] !== 160 || $p1['page'] !== 1 || $p1['pages'] !== 7
    || count($p1['rows']) !== 25 || (int)$p1['rows'][0]['request_item_id'] !== 1
    || (int)$p1['rows'][0]['draft_delivery_id'] !== 501
    || (int)$p1['rows'][0]['open_draft_count'] !== 2
    || (int)$p1['rows'][1]['draft_delivery_id'] !== 506
    || (int)$p1['rows'][1]['open_draft_count'] !== 1
    || !empty($p1['rows'][2]['draft_delivery_id'])
    || (int)$p1['rows'][2]['open_draft_count'] !== 1
    || (int)$p1['rows'][2]['blocking_draft_exists'] !== 1
    || !empty($p1['rows'][3]['draft_delivery_id'])
    || (int)$p1['rows'][3]['open_draft_count'] !== 0
    || (int)$p1['rows'][3]['blocking_draft_exists'] !== 1) {
    throw new RuntimeException('P4 total, draft guard or pages incorrect across 160 nominal lines.');
}
$p5 = $repo->pendingNominalPageForUser(101,'','','',5,25);
if ((int)$p5['rows'][0]['request_item_id'] !== 101) {
    throw new RuntimeException('Server paging did not move past first 100.');
}
$last = $repo->pendingNominalPageForUser(101,'','','',999,25);
if ($last['page'] !== 7 || count($last['rows']) !== 10
    || (int)$last['rows'][9]['request_item_id'] !== 160) {
    throw new RuntimeException('Last page clamp or final rows are incorrect.');
}
$search = $repo->pendingNominalPageForUser(101,'Funcionario 150');
if ($search['total'] !== 1 || (int)$search['rows'][0]['request_item_id'] !== 150
    || (int)$search['rows'][0]['open_draft_count'] !== 0) {
    throw new RuntimeException('Search did not cover results beyond 100.');
}
$percent = $repo->pendingNominalPageForUser(101,'%');
if ($percent['total'] !== 1 || (int)$percent['rows'][0]['request_item_id'] !== 155
    || (int)$percent['rows'][0]['draft_delivery_id'] !== 505
    || (int)$percent['rows'][0]['open_draft_count'] !== 1) {
    throw new RuntimeException('LIKE wildcard not safely escaped.');
}
$filtered = $repo->pendingNominalPageForUser(101,'','9.5','PACORA-B',1,10);
if ($filtered['total'] !== 30 || count($filtered['rows']) !== 10
    || (int)$filtered['rows'][0]['request_item_id'] !== 131) {
    throw new RuntimeException('Size and request filters must be applied before page LIMIT.');
}
$other = $repo->pendingNominalPageForUser(202);
$missing = $repo->pendingNominalPageForUser(0);
$foreign = $repo->pendingNominalPageForUser(101,'Solo Colón');
if ($other['total'] !== 10 || $missing['total'] !== 0 || $foreign['total'] !== 0) {
    throw new RuntimeException('Foreign P4, inactive or unknown-user data leaked.');
}
$choices = $repo->pendingNominalOptionsForUser(101);
$codes = array_unique(array_column($choices, 'request_code'));
$sizes = array_unique(array_column($choices, 'size_name'));
if (!in_array('PACORA-B',$codes,true) || !in_array('9.5',$sizes,true)
    || in_array('COLON-X',$codes,true) || in_array('INACTIVE',$codes,true)
    || $repo->pendingNominalOptionsForUser(0) !== []) {
    throw new RuntimeException('Filter options are incomplete or leak another P4.');
}
$view = (string)file_get_contents(dirname(__DIR__).'/app/Views/deliveries/p4-index.php');
$controller = (string)file_get_contents(dirname(__DIR__).'/app/Controllers/DeliveryController.php');
foreach (['method="get"','name="q"','name="size"','name="request"',
    'name="per_page"','p4-delivery-pagination','data-p4-delivery-row',
    'data-p4-filter-reset','?module=deliveries&action=new&request_item_id='] as $token) {
    if (!str_contains($view,$token)) {
        throw new RuntimeException('Server-side delivery view contract missing: ' . $token);
    }
}
if (!str_contains($view, 'Continuar borrador')
    || !str_contains($view, 'Existe un borrador relacionado que requiere revisión')
    || !str_contains($view, '$safeDraftId > 0')
    || !str_contains($view, "'blocking_draft_exists'")
    || !str_contains($view, "'open_draft_count'")
    || !str_contains($view, "'draft_delivery_id'")
    || !str_contains($view, 'revisar posibles duplicados')
    || !str_contains($view, "?module=deliveries&action=show&id=")) {
    throw new RuntimeException('Existing nominal draft warning/resume links missing.');
}
if (!str_contains($controller,'pendingNominalPageForUser(')
    || !str_contains($controller,'pendingNominalOptionsForUser(')
    || str_contains($view,'Los filtros actúan sobre los resultados cargados')) {
    throw new RuntimeException('P4 delivery list still uses only client-side filtering.');
}
echo "[OK] P4 server pagination: 160 nominal rows, scope, search, literal LIKE and all pages.\n";
