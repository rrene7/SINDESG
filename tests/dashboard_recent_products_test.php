<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/DashboardRepository.php';

use App\Repositories\DashboardRepository;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->sqliteCreateFunction('GREATEST', static fn (...$n): float => (float) max(...$n), -1);
$pdo->exec(<<<'SQL'
CREATE TABLE categories(id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER NOT NULL);
CREATE TABLE products(
    id INTEGER PRIMARY KEY, internal_code TEXT, name TEXT NOT NULL,
    description TEXT, sex TEXT, color TEXT,
    created_at TEXT, category_id INTEGER NOT NULL, status TEXT NOT NULL
);
CREATE TABLE product_images(id INTEGER PRIMARY KEY, product_id INTEGER NOT NULL, path TEXT, is_primary INTEGER NOT NULL);
CREATE TABLE v_inventory_product_stock(product_id INTEGER PRIMARY KEY, quantity_on_hand NUMERIC);
CREATE TABLE product_variants(id INTEGER PRIMARY KEY, product_id INTEGER, size_id INTEGER, status TEXT NOT NULL);
CREATE TABLE v_inventory_stock(product_variant_id INTEGER, warehouse_id INTEGER, quantity_on_hand NUMERIC);
CREATE TABLE warehouses(id INTEGER PRIMARY KEY, code TEXT, warehouse_type TEXT);
CREATE TABLE sizes(id INTEGER PRIMARY KEY, name TEXT, sort_order INTEGER);
CREATE TABLE service_requests(id INTEGER PRIMARY KEY, status TEXT NOT NULL);
CREATE TABLE service_request_items(id INTEGER PRIMARY KEY, request_id INTEGER, product_variant_id INTEGER, quantity_approved NUMERIC, quantity_dispatched NUMERIC);
INSERT INTO categories VALUES (1,'UNIFORMES',1),(2,'GORRAS',1),(3,'ARCHIVO',0);
INSERT INTO products VALUES
 (1,'PROD-0001','Botas Táctica','Modelo con inventario','unisex','Negro','2026-10-01 12:00:00',1,'active'),
 (2,'PROD-0002','Otro uniforme','','mixto',NULL,'2026-10-20 09:00:00',1,'active'),
 (3,'PROD-0003','Gorras históricas',NULL,'unisex',NULL,'2026-10-21 11:00:00',2,'deleted'),
 (4,'PROD-0004','Uniforme de archivo',NULL,'unisex',NULL,'2026-10-21 12:00:00',3,'active'),
 (5,'PROD-0005','Uniforme inactivo',NULL,'unisex',NULL,'2026-10-21 13:00:00',1,'inactive'),
 (6,'PROD-0006','UNIFORME REGULAR MANGA LARGA',NULL,'masculino','Azul','2026-10-21 16:55:00',1,'active'),
 (7,'PROD-0007','UNIFORME REGULAR',NULL,'unisex','Azul','2026-10-21 16:55:00',1,'active');
INSERT INTO product_images VALUES
 (1,6,'products/uniforme-larga.webp',1),
 (2,6,'products/no-principal.webp',0),
 (3,7,'products/uniforme-corta.webp',1);
INSERT INTO v_inventory_product_stock VALUES (1,15387),(6,0);
INSERT INTO product_variants VALUES (1,1,1,'active'),(2,6,2,'active'),(3,7,2,'active');
INSERT INTO v_inventory_stock VALUES (1,1,15387),(2,1,0);
INSERT INTO warehouses VALUES (1,'CENTRAL-SG','CENTRAL');
INSERT INTO sizes VALUES (1,'10',10),(2,'M',20);
SQL);
// More than twelve products proves that the carousel never silently truncates.
$statement = $pdo->prepare(
    'INSERT INTO products (id,internal_code,name,created_at,category_id,status)
     VALUES (:id,:code,:name,:created,1,"active")'
);
for ($i = 10; $i <= 24; $i++) {
    $statement->execute([
        'id' => $i,
        'code' => 'PROD-' . $i,
        'name' => 'Artículo adicional ' . $i,
        'created' => '2026-09-01 01:00:00',
    ]);
}

$repo = new DashboardRepository($pdo);
$rows = $repo->productCarouselCards();
$ids = array_map('intval', array_column($rows, 'id'));
$assert(count($rows) === 19, 'El carrusel debe mostrar todos los artículos activos, no solo los 8 o 12 recientes.');
$assert(array_slice($ids, 0, 4) === [7,6,2,1], 'Los nuevos uniformes deben aparecer primero, incluso con stock 0.');
$assert(!in_array(3,$ids,true) && !in_array(4,$ids,true) && !in_array(5,$ids,true), 'No mostrar productos o categorías desactivados.');
$assert($rows[0]['primary_image'] === 'products/uniforme-corta.webp', 'Fotografía del uniforme regular.');
$assert($rows[1]['primary_image'] === 'products/uniforme-larga.webp', 'Fotografía de manga larga.');
$assert((float) $rows[0]['stock_total'] === 0.0 && (float) $rows[1]['stock_total'] === 0.0, 'El registro no puede inventar stock.');
$assert((float) $rows[3]['stock_total'] === 15387.0, 'No ocultar existencias de botas.');

$focus = $repo->productCarouselFocusData(array_slice($rows, 0, 4));
$assert(count($focus) === 4, 'Debe haber exactamente un panel de enfoque por artículo.');
$assert($focus[6]['product']['name'] === 'UNIFORME REGULAR MANGA LARGA', 'La selección debe apuntar al artículo exacto.');
$assert((float)$focus[6]['stock']['available'] === 0.0, 'El artículo nuevo no tiene unidades disponibles.');
$assert((float)$focus[1]['stock']['available'] === 15387.0, 'Stock de botas debe salir del kardex, no de la categoría.');
$assert(count($focus[1]['sizes']) === 1 && $focus[1]['sizes'][0]['name'] === '10', 'Tallas del artículo, no de otro.');
$assert(count($focus[6]['sizes']) === 0, 'No mostrar tallas sin existencias.');
$assert($focus[6]['product']['image_path'] === 'products/uniforme-larga.webp', 'Imagen correcta en enfoque.');
$assert($focus[7]['product']['id'] !== $focus[6]['product']['id'], 'Uniformes distintos mantienen identificadores distintos.');

$root = dirname(__DIR__);
$index = (string)file_get_contents($root.'/public/index.php');
$view = (string)file_get_contents($root.'/app/Views/dashboard.php');
$js = (string)file_get_contents($root.'/public/assets/js/app.js');
$css = (string)file_get_contents($root.'/public/assets/css/app.css');
foreach ([
    ['$dashboardRepository->productCarouselCards()', $index],
    ['$dashboardRepository->productCarouselFocusData($dashboardProducts)', $index],
    ['data-dashboard-product-id', $view],
    ['data-dashboard-product-focus', $view],
    ['product-carousel-card', $view],
    ['Sin existencias disponibles', $view],
    ['Ver artículo →', $view],
    ['sindesg_upload_url($photo)', $view],
    ["querySelectorAll('[data-dashboard-product-id]')", $js],
    ['const activateProduct', $js],
    ['renderSizes(entry.sizes)', $js],
    ['fields.image.appendChild(image)', $js],
    ['carousel.scrollBy', $js],
    ['data-carousel-next', $view],
    ['scroll-snap-type: x proximity', $css],
] as [$needle,$content]) {
    $assert(str_contains($content,$needle), 'Contrato de carrusel por artículo ausente: '.$needle);
}
foreach (['Últimos artículos incorporados', 'dashboard-recent-grid', 'data-dashboard-category-id', 'data-dashboard-category-focus'] as $old) {
    $assert(!str_contains($view,$old), 'Se mantiene una vista duplicada/antigua: '.$old);
}
$assert(!str_contains($index, 'recentProducts(8)'), 'No se debe volver a cargar la lista reducida.');
echo "[OK] Carrusel único: todos los artículos, nuevos uniformes, stock real, tallas correctas y selección interactiva.\n";
