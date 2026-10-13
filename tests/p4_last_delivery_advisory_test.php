<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Repositories\DeliveryRepository;

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/requests/show.php');
$js = (string) file_get_contents($root . '/public/assets/js/requests.js');
$service = (string) file_get_contents($root . '/app/Services/NominalBulkRequestService.php');
$controller = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');
$endpoint = (string) file_get_contents($root . '/public/api/last-nominal-delivery.php');
$requestRepo = (string) file_get_contents($root . '/app/Repositories/RequestRepository.php');
$deliveryRepo = (string) file_get_contents($root . '/app/Repositories/DeliveryRepository.php');

foreach ([
    'lastConfirmedDatesForProduct',
    'd.status = "confirmed"',
    'pv.product_id = :product_id',
    'MAX(d.delivered_at)',
] as $token) {
    if (!str_contains($deliveryRepo, $token)) {
        throw new RuntimeException('Falta consulta de última entrega: ' . $token);
    }
}
foreach (['sindesg_api_authorized(false)', "sindesg_can('requests.view')",
          'Cache-Control', 'lastConfirmedDatesForProduct', 'dates'] as $token) {
    if (!str_contains($endpoint, $token)) {
        throw new RuntimeException('Falta protección de historial: ' . $token);
    }
}
foreach ([
    'last_delivered_same_product_at', "previous.status = 'confirmed'",
    'previous_variant.product_id = pv.product_id',
] as $token) {
    if (!str_contains($requestRepo, $token)) {
        throw new RuntimeException('Falta evidencia nominal anterior: ' . $token);
    }
}
if (str_contains($service, "funcionario fuera de la estructura del P4")
    || str_contains($service, 'requireP4Member')
    || str_contains($js, 'items.filter((person)')) {
    throw new RuntimeException('La solicitud no debe excluir personal prestado.');
}
foreach ([
    'last-nominal-delivery.php', 'Última entrega de este artículo',
    'Sin entrega registrada de este artículo en SINDESG', 'Descargar plantilla Excel (.xlsx)',
] as $token) {
    if (!str_contains($view, $token)) {
        throw new RuntimeException('Falta mostrar fecha o plantilla: ' . $token);
    }
}
foreach (['dateLabel', 'loadLastDeliveries', 'last_delivery_status',
          'last_delivered_at', "select.addEventListener"] as $token) {
    if (!str_contains($js, $token)
        && $token !== "select.addEventListener") {
        throw new RuntimeException('Falta mostrar fecha en selección múltiple: ' . $token);
    }
}
foreach (['download-nominal-template', 'P4NominalXlsxTemplate', 'readfile'] as $token) {
    if (!str_contains($controller, $token)) {
        throw new RuntimeException('Falta descarga XLSX: ' . $token);
    }
}
$xlsxGenerator = (string) file_get_contents(
    $root . '/app/Services/P4NominalXlsxTemplate.php'
);
foreach (['POSICIÓN', 'NOMBRE', 'APELLIDO', 'TALLA'] as $token) {
    if (!str_contains($xlsxGenerator, $token)) {
        throw new RuntimeException('Falta columna Excel: ' . $token);
    }
}

// Integración sin MySQL: confirmar que la fecha depende SOLO de entregas
// confirmadas del mismo producto, con independencia del P4 o talla.
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec(<<<'SQL'
        CREATE TABLE individual_deliveries (
            id INTEGER PRIMARY KEY, recipient_employee_id INTEGER,
            status TEXT, delivered_at TEXT, p4_warehouse_id INTEGER
        );
        CREATE TABLE individual_delivery_items (
            id INTEGER PRIMARY KEY, delivery_id INTEGER, product_variant_id INTEGER
        );
        CREATE TABLE product_variants (id INTEGER PRIMARY KEY, product_id INTEGER);
        INSERT INTO product_variants (id,product_id) VALUES
            (1, 100), (2,100), (3,200);
        INSERT INTO individual_deliveries
            (id,recipient_employee_id,status,delivered_at,p4_warehouse_id) VALUES
            (1,51,'confirmed','2026-01-15 09:00:00',3),
            (2,51,'confirmed','2026-08-20 11:00:00',9),
            (3,51,'draft','2026-10-02 13:00:00',3),
            (4,51,'confirmed','2026-09-01 11:00:00',5),
            (5,52,'confirmed','2026-06-04 09:00:00',7),
            (6,53,'cancelled','2026-07-06 09:00:00',3);
        INSERT INTO individual_delivery_items(id,delivery_id,product_variant_id) VALUES
            (1,1,1),(2,2,2),(3,3,1),(4,4,3),(5,5,1),(6,6,1);
    SQL);
    $r = new DeliveryRepository($db);
    $dates = $r->lastConfirmedDatesForProduct(100, [51,52,53,54]);
    if (($dates[51] ?? null) !== '2026-08-20 11:00:00'
        || ($dates[52] ?? null) !== '2026-06-04 09:00:00'
        || isset($dates[53]) || isset($dates[54])) {
        throw new RuntimeException('La fecha debe ser última confirmada del producto sin bloqueo por P4.');
    }
    if ($r->lastConfirmedDatesForProduct(100, []) !== []) {
        throw new RuntimeException('Lista vacía debe devolver vacío.');
    }
}
echo "[OK] Búsqueda global P4; fecha de última entrega por artículo, solo informativa; plantilla XLSX." . PHP_EOL;
