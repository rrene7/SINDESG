<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/DashboardRepository.php';

use App\Repositories\DashboardRepository;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

// Small real SQL contract: no live stock, fake receipts or application writes.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE categories(id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER NOT NULL);
CREATE TABLE products(
    id INTEGER PRIMARY KEY, internal_code TEXT, name TEXT NOT NULL,
    created_at TEXT, category_id INTEGER NOT NULL, status TEXT NOT NULL
);
CREATE TABLE product_images (
    id INTEGER PRIMARY KEY, product_id INTEGER NOT NULL, path TEXT, is_primary INTEGER NOT NULL
);
CREATE TABLE v_inventory_product_stock(product_id INTEGER PRIMARY KEY, quantity_on_hand NUMERIC);
INSERT INTO categories VALUES (1,'UNIFORMES',1),(2,'GORRAS',1),(3,'ARCHIVO',0);
INSERT INTO products VALUES
 (1,'PROD-0001','Botas Táctica','2026-10-01 12:00:00',1,'active'),
 (2,'PROD-0002','Otro uniforme','2026-10-20 09:00:00',1,'active'),
 (3,'PROD-0003','Gorras históricas','2026-10-21 11:00:00',2,'deleted'),
 (4,'PROD-0004','Uniforme de archivo','2026-10-21 12:00:00',3,'active'),
 (5,'PROD-0005','Uniforme inactivo','2026-10-21 13:00:00',1,'inactive'),
 (6,'PROD-0006','UNIFORME REGULAR MANGA LARGA','2026-10-21 16:55:00',1,'active'),
 (7,'PROD-0007','UNIFORME REGULAR','2026-10-21 16:55:00',1,'active');
INSERT INTO product_images VALUES
 (1,6,'products/uniforme-larga.webp',1),
 (2,6,'products/no-principal.webp',0),
 (3,7,'products/uniforme-corta.webp',1);
INSERT INTO v_inventory_product_stock VALUES
 (1,15387),
 (6,0);
SQL);
$repo = new DashboardRepository($pdo);
$rows = $repo->recentProducts(8);
$assert(array_column($rows, 'id') === [7,6,2,1], 'El orden debe priorizar nuevas altas, desempatar ID y excluir inactivos.');
$assert($rows[0]['name'] === 'UNIFORME REGULAR', 'Manga corta no aparece en novedades.');
$assert($rows[1]['name'] === 'UNIFORME REGULAR MANGA LARGA', 'Manga larga no aparece en novedades.');
$assert($rows[0]['primary_image'] === 'products/uniforme-corta.webp', 'La foto de manga corta no se ha recuperado.');
$assert($rows[1]['primary_image'] === 'products/uniforme-larga.webp', 'Debe recuperarse solo la foto principal.');
$assert((float)$rows[0]['stock_total'] === 0.0 && (float)$rows[1]['stock_total'] === 0.0, 'Alta de artículo no significa existencia.');
$assert((float)$rows[3]['stock_total'] === 15387.0, 'El stock histórico real debe mantenerse visible.');
$assert(count($repo->recentProducts(1)) === 1, 'La paginación de novedades no respeta límite.');
$assert(count($repo->recentProducts(500)) === 4, 'Los límites deben ser seguros.');

$repoSource = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/DashboardRepository.php');
$index = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
$view = (string) file_get_contents(dirname(__DIR__) . '/app/Views/dashboard.php');
$css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
$testSource = (string) file_get_contents(__FILE__);

foreach ([
    ['ORDER BY p.created_at DESC, p.id DESC', $repoSource],
    ['v_inventory_product_stock', $repoSource],
    ['p.status =', $repoSource],
    ['$dashboardRepository->recentProducts(8)', $index],
    ['$recentDashboardProducts = []', $index],
    ['Últimos artículos incorporados', $view],
    ['recentDashboardProducts', $view],
    ['sindesg_upload_url($recentImage)', $view],
    ['Registrado · Sin existencias', $view],
    ['?module=inventory&action=product&id=', $view],
    ['dashboard-recent-grid', $css],
    ['dashboard-recent-photo img', $css],
    ['repeat(4, minmax(0, 1fr))', $css],
] as [$needle, $content]) {
    $assert(str_contains($content, $needle), 'Contrato dashboard faltante: ' . $needle);
}
// Prevent accidentally destroying the existing category selector/featured controls.
$assert(str_contains($view, 'data-dashboard-category-focus'), 'No se permite quitar el selector por categoría.');
$assert(str_contains($view, 'data-feature-sizes'), 'No se permite quitar las existencias/tallas del destacado.');
$assert(str_contains($testSource, 'UNIFORME REGULAR MANGA LARGA'), 'Falta caso del uniforme reportado.');
echo "[OK] Dashboard refleja productos nuevos con 0 stock, imagen real, enlaces y destacado por existencias intacto.\n";
