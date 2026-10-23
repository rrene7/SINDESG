<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Repositories/DashboardRepository.php';

use App\Repositories\DashboardRepository;

$check = static function (bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException($reason);
};

// Fixture in memory only: never connects to local SINDESG or RRHH2029.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE receipts (
    id INTEGER PRIMARY KEY, receipt_code TEXT NOT NULL,
    supplier_or_origin TEXT NOT NULL, status TEXT NOT NULL, received_at TEXT NOT NULL
);
CREATE TABLE lots (receipt_id INTEGER NOT NULL, lot_code TEXT NOT NULL);
CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE product_variants (id INTEGER PRIMARY KEY, product_id INTEGER NOT NULL);
CREATE TABLE receipt_items (
    id INTEGER PRIMARY KEY, receipt_id INTEGER NOT NULL,
    product_variant_id INTEGER NOT NULL, quantity NUMERIC NOT NULL
);
INSERT INTO receipts VALUES
 (2,'REC-000002','MÁX GEAR, S.A.','confirmed','2026-10-14 12:26:00'),
 (3,'REC-000003','Proveedor mixto','draft','2026-10-15 12:26:00'),
 (4,'REC-000004','Proveedor vacío','draft','2026-10-16 12:26:00');
INSERT INTO lots VALUES
 (2,'LOT-000002'),(3,'LOT-000003'),(4,'LOT-000004');
INSERT INTO products VALUES
 (10,'Botas Tácticas'),(11,'Camisas'),(12,'Artículo <script>alert(1)</script>');
SQL);
$variant = $pdo->prepare('INSERT INTO product_variants(id, product_id) VALUES (?, ?)');
$item = $pdo->prepare('INSERT INTO receipt_items(id, receipt_id, product_variant_id, quantity) VALUES (?, ?, ?, ?)');
for ($i=1; $i<=17; $i++) {
    $variant->execute([$i,10]);
    // 937 + (16 * 925) = 15 737: one product, 17 separate sizes.
    $item->execute([$i,2,$i,$i===1 ? 937 : 925]);
}
$variant->execute([20,11]);
$variant->execute([21,12]);
$item->execute([30,3,1,2]);
$item->execute([31,3,20,3]);
$item->execute([32,3,21,4]);

$repo = new DashboardRepository($pdo);
$rows = $repo->recentReceipts(10);
$check(count($rows)===3, 'El dashboard debe conservar recepciones incluso vacías.');
$byId = [];
foreach ($rows as $row) $byId[(int) $row['id']] = $row;
$boots = $byId[2];
$check($boots['receipt_code']==='REC-000002'
    && $boots['lot_code']==='LOT-000002'
    && $boots['supplier_or_origin']==='MÁX GEAR, S.A.'
    && $boots['status']==='confirmed'
    && (float)$boots['total_quantity']===15737.0,
    'La cabecera original y el total del lote confirmado deben permanecer intactos.');
$check((int)$boots['product_count']===1
    && $boots['product_name']==='Botas Tácticas',
    'Las 17 tallas deben mostrar el único nombre de artículo real.');
$check((int)$byId[3]['product_count']===3
    && $byId[3]['product_name']===null
    && (float)$byId[3]['total_quantity']===9.0,
    'Un lote mixto nunca debe anunciar falsamente que contiene solo botas.');
$check((int)$byId[4]['product_count']===0
    && $byId[4]['product_name']===null,
    'Un lote vacío debe identificarse sin inventar artículos.');

// Render real dashboard view using the fixture instead of only string-matching.
$health = [
    'sindesg'=>['ok'=>true,'message'=>'Prueba aislada'],
    'rrhh'=>['ok'=>true,'message'=>'Solo lectura'],
];
$dashboardProducts = [];
$dashboardProductFocus = [];
$featuredProduct = null;
$featuredSizes = [];
$featuredStockBreakdown = [];
$recentDashboardReceipts = $rows;
$recentDashboardRequests = [];
$dashboardOperationalSummary = [
    'requests_pending_review'=>0,
    'requests_ready_distribution'=>0,
    'distributions_in_transit'=>0,
    'active_p4'=>0,
];
$modules = [];
ob_start();
require dirname(__DIR__) . '/app/Views/dashboard.php';
$html = (string) ob_get_clean();

$check(str_contains($html, 'Botas Tácticas')
    && str_contains($html, 'REC-000002')
    && str_contains($html, 'LOT-000002')
    && str_contains($html, 'MÁX GEAR, S.A.')
    && str_contains($html, '15 737'),
    'La tarjeta real debe mostrar el artículo, código, proveedor y cantidad.');
$check(str_contains($html, '3 artículos diferentes')
    && str_contains($html, 'Sin artículos registrados'),
    'Lotes mixtos y vacíos deben ser identificados sin falsa atribución.');
$check(!str_contains($html, '<script>alert(1)</script>'),
    'No se deben insertar nombres de catálogo como HTML ejecutable.');
$check(substr_count($html,'class="dashboard-receipt-product"')===3,
    'Todas las tarjetas recientes deben mostrar identificación del contenido.');

$css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
$check(str_contains($css,'.dashboard-receipt-product')
    && str_contains($css,'@media(max-width:480px)'),
    'El nombre del artículo debe estar adaptado a celular.');
echo "[OK] Dashboard real: REC-000002 identifica Botas Tácticas, 15 737 unidades y conserva otros lotes con claridad.\n";
