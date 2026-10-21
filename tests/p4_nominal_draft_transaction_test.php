<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/DeliveryRepository.php';
use App\Repositories\DeliveryRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite required for safe in-memory nominal draft tests.');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses(id INTEGER PRIMARY KEY, warehouse_type TEXT, is_active INTEGER);
CREATE TABLE service_requests(id INTEGER PRIMARY KEY,p4_warehouse_id INTEGER,status TEXT);
CREATE TABLE service_request_items(
 id INTEGER PRIMARY KEY,request_id INTEGER,product_variant_id INTEGER,
 beneficiary_employee_id INTEGER,quantity_received REAL,quantity_delivered REAL
);
CREATE TABLE individual_deliveries(
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
CREATE TABLE individual_delivery_items(
 id INTEGER PRIMARY KEY AUTOINCREMENT,delivery_id INTEGER,
 product_variant_id INTEGER,request_item_id INTEGER,quantity REAL,notes TEXT
);
INSERT INTO warehouses VALUES (6,'P4',1),(4,'P4',1),(9,'P4',0);
INSERT INTO service_requests VALUES (1,6,'fulfilled'),(2,4,'fulfilled'),(3,9,'fulfilled');
INSERT INTO service_request_items VALUES
 (11,1,1,321,1,0),(12,2,1,654,1,0),(13,3,1,987,1,0);
SQL);
$repository = new DeliveryRepository($pdo);
$data = [
 'p4_warehouse_id'=>6, 'recipient_employee_id'=>321,
 'recipient_label_snapshot'=>'Funcionario Pacora',
 'recipient_document_snapshot'=>'D-321',
 'recipient_position_snapshot'=>'P-321','recipient_rank_snapshot'=>'Cabo',
 'recipient_unit_id_snapshot'=>null,'recipient_unit_name_snapshot'=>'Pacora',
 'recipient_direction_snapshot'=>'', 'recipient_office_snapshot'=>'',
 'delivered_by_employee_id'=>777,'delivered_by_label_snapshot'=>'Responsable P4',
 'delivered_by_document_snapshot'=>'D-777',
 'delivered_by_position_snapshot'=>'P-777','delivered_by_rank_snapshot'=>'Sargento',
 'delivered_at'=>'2026-10-21 10:00:00','document_number'=>null,
 'notes'=>null,'scope_override_enabled'=>0,'scope_override_reason'=>null
];
$line = [
 'request_item_id'=>11,'product_variant_id'=>1,
 'beneficiary_employee_id'=>321,'p4_warehouse_id'=>6,
 'request_code'=>'PACORA-TEST'
];
$count = static function () use ($pdo): int {
    return (int) $pdo->query('SELECT COUNT(*) FROM individual_deliveries')->fetchColumn();
};
$checkRejected = static function (
    array $draftData, array $draftLine, float $quantity, string $expected
) use ($repository, $pdo, $count): void {
    $before = $count();
    try {
        $repository->createDraft($draftData, $draftLine, $quantity);
        throw new LogicException('Expected nominal draft rejection: '.$expected);
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), $expected)) {
            throw new RuntimeException('Unexpected rejection: '.$e->getMessage());
        }
    }
    if ($pdo->inTransaction() || $count() !== $before) {
        throw new RuntimeException('Rejected nominal draft modified data or left transaction open.');
    }
};
$first = $repository->createDraft($data, $line, 0.4);
if ($first <= 0 || $count() !== 1
    || (float)$pdo->query('SELECT quantity FROM individual_delivery_items WHERE delivery_id='.$first)->fetchColumn() !== 0.4) {
    throw new RuntimeException('First partial nominal draft was not created correctly.');
}
$checkRejected($data,$line,0.4,'Ya existe un borrador abierto');
$checkRejected(array_replace($data,['recipient_employee_id'=>999]),$line,0.4,'ya no corresponde');
$checkRejected(array_replace($data,['p4_warehouse_id'=>4]),$line,0.4,'ya no corresponde');
$checkRejected($data,array_replace($line,['product_variant_id'=>2]),0.4,'ya no corresponde');

// Historical cancelled drafts remain untouched and permit a new draft.
$pdo->exec('UPDATE individual_deliveries SET status="cancelled" WHERE id='.$first);
$second = $repository->createDraft($data,$line,0.25);
if ($second <= $first || $count() !== 2) {
    throw new RuntimeException('Cancelled draft should not block legitimate replacement.');
}
$pdo->exec('UPDATE individual_deliveries SET status="confirmed" WHERE id='.$second);
$pdo->exec('UPDATE service_request_items SET quantity_delivered=0.25 WHERE id=11');
$third = $repository->createDraft($data,$line,0.5);
if ($third <= $second || $count() !== 3) {
    throw new RuntimeException('Confirmed partial deliveries should permit remaining quantity.');
}
$pdo->exec('UPDATE individual_deliveries SET status="cancelled" WHERE id='.$third);
$checkRejected($data,$line,0.8,'cantidad supera');
$pdo->exec('UPDATE service_request_items SET quantity_received=0.25,quantity_delivered=0.25 WHERE id=11');
$checkRejected($data,$line,0.1,'cantidad supera');
$pdo->exec('UPDATE service_request_items SET quantity_received=1,quantity_delivered=0 WHERE id=11');
$pdo->exec("UPDATE service_requests SET status='rejected' WHERE id=1");
$checkRejected($data,$line,0.1,'ya no corresponde');
$pdo->exec("UPDATE service_requests SET status='fulfilled' WHERE id=1");
$checkRejected(array_replace($data,['p4_warehouse_id'=>4]),
    array_replace($line,['request_item_id'=>12,'beneficiary_employee_id'=>654]),
    0.1,'ya no corresponde');
$checkRejected($data,array_replace($line,['request_item_id'=>13,'p4_warehouse_id'=>9,
    'beneficiary_employee_id'=>987]),0.1,'ya no corresponde');

$source = (string)file_get_contents(dirname(__DIR__) . '/app/Repositories/DeliveryRepository.php');
if (!str_contains($source, 'assertNominalDraftAvailableForUpdate($data, $linkedLine, $linkedQuantity)')
    || !str_contains($source, 'PDO::ATTR_DRIVER_NAME')
    || !str_contains($source, ' FOR UPDATE')
    || !str_contains($source, "AND d.status='draft'")
    || !str_contains($source, 'WHERE i.id=:request_item_id')
    || !str_contains($source, 'beginTransaction()')) {
    throw new RuntimeException('Transactional nominal draft concurrency guard missing.');
}
echo "[OK] P4 nominal drafts: one open draft, safe rollback, partial replay and current-line guard.\n";
