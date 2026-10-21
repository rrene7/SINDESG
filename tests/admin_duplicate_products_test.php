<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/AuditRepository.php';
require dirname(__DIR__) . '/app/Repositories/CatalogRepository.php';
require dirname(__DIR__) . '/app/Services/CatalogService.php';
require dirname(__DIR__) . '/app/Services/CatalogDuplicateInspector.php';

use App\Services\CatalogDuplicateInspector;
use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$rows = [
    ['id'=>12,'name'=>'UNIFORME DE FAENA','category_id'=>1,'status'=>'active'],
    ['id'=>15,'name'=>' Uniforme   de faéna ','category_id'=>1,'status'=>'active'],
    ['id'=>19,'name'=>'UNIFORME-DE-FAENA','category_id'=>2,'status'=>'active'],
    ['id'=>20,'name'=>'Uniforme de gala','category_id'=>1,'status'=>'active'],
    ['id'=>21,'name'=>'CAMISA MANGA LARGA','category_id'=>1,'status'=>'active'],
    ['id'=>22,'name'=>'camisa manga larga','category_id'=>4,'status'=>'active'],
    ['id'=>23,'name'=>'camisa manga larga','category_id'=>1,'status'=>'deleted'],
    ['id'=>24,'name'=>'CAMISA MANGA CORTA','category_id'=>1,'status'=>'active'],
];
$grouped = CatalogDuplicateInspector::groups($rows);
$assert(count($grouped) === 2, 'Debe detectar dos grupos de coincidencia');
$byKey = [];
foreach ($grouped as $g) $byKey[$g['key']] = $g;
$assert(isset($byKey['uniforme de faena']), 'Nombre institucional repetido no detectado');
$assert(array_column($byKey['uniforme de faena']['items'], 'id') === [12,15,19], 'Se perdieron identificadores distintos');
$assert($byKey['uniforme de faena']['category_count'] === 2, 'Debe comparar entre categorías');
$assert(count($byKey['camisa manga larga']['items']) === 2, 'No debe incluir artículos eliminados');
$assert(!isset($byKey['uniforme de gala']), 'No fusionar por prefijos parecidos');
$assert(!isset($byKey['camisa manga corta']), 'No fusionar presentaciones diferentes');
$assert(CatalogDuplicateInspector::nameKey('ÁÉÍÓÚ Ñ') === 'aeiou n','Normalización de tildes incorrecta');
$assert(CatalogDuplicateInspector::nameKey("FAE\u{0301}NA") === 'faena','No normaliza tildes descompuestas');

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(<<<'SQL'
CREATE TABLE products (
 id INTEGER PRIMARY KEY,
 category_id INTEGER NOT NULL,
 internal_code TEXT,
 name TEXT,
 description TEXT,
 sex TEXT,
 color TEXT,
 status TEXT NOT NULL
);
CREATE TABLE audit_logs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER, user_label_snapshot TEXT, module_code TEXT, action_code TEXT,
 record_type TEXT, record_id TEXT, details_json TEXT, before_json TEXT, after_json TEXT,
 ip_address TEXT, user_agent TEXT
);
INSERT INTO products
(id,category_id,internal_code,name,description,sex,color,status)
VALUES
(12,1,'SND-PRODUCT-12','UNIFORME DE FAENA','Antes','unisex','Azul','active'),
(13,1,'SND-PRODUCT-13','Histórico',NULL,'no_aplica',NULL,'deleted');
SQL);
$repo = new CatalogRepository($db);
$service = new CatalogService($repo);
$actor = ['id'=>71,'display_name'=>'Administrador de prueba'];

$service->updateProductMetadata(12, [
    'name'=>'UNIFORME DE FAENA TIPO A',
    'description'=>'Modelo verificado: mantener sus variantes.',
    'sex'=>'masculino',
    'color'=>'Azul oscuro',
    // Attempted mass assignment must be ignored.
    'internal_code'=>'ALTERADO',
    'category_id'=>999,
    'status'=>'deleted',
], $actor);

$p = $db->query('SELECT * FROM products WHERE id = 12')->fetch(PDO::FETCH_ASSOC);
$assert($p['name'] === 'UNIFORME DE FAENA TIPO A','No editó descripción');
$assert($p['internal_code'] === 'SND-PRODUCT-12','Alteró código permanente');
$assert((int)$p['category_id'] === 1,'Alteró categoría histórica');
$assert($p['status'] === 'active','Alteró estado');
$assert($p['sex'] === 'masculino' && $p['color'] === 'Azul oscuro','Campos descriptivos no guardados');
$a = $db->query('SELECT * FROM audit_logs')->fetch(PDO::FETCH_ASSOC);
$assert($a['action_code'] === 'product_metadata_updated','Sin evento de auditoría');
$assert((int)$a['user_id'] === 71,'Auditoría sin actor');
$assert(str_contains((string)$a['before_json'],'UNIFORME DE FAENA'), 'Sin fotografía anterior');

$reject = static function (Closure $fn, string $reason): void {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Se aceptó entrada inválida: ' . $reason);
};
$reject(static fn()=> $service->updateProductMetadata(13,['name'=>'X','sex'=>'unisex'], $actor),'Producto eliminado');
$reject(static fn()=> $service->updateProductMetadata(12,['name'=>'','sex'=>'unisex'], $actor),'Nombre en blanco');
$reject(static fn()=> $service->updateProductMetadata(12,['name'=>'Bien','sex'=>'especial'], $actor),'Sexo fuera de catálogo');

$db->exec('DROP TABLE audit_logs');
try {
    $service->updateProductMetadata(12, [
        'name'=>'NO DEBE QUEDAR','description'=>'Rollback','sex'=>'unisex','color'=>'Rojo'
    ], $actor);
    throw new RuntimeException('Se aceptó modificación sin auditoría');
} catch (PDOException $e) {
    $persisted = (string)$db->query('SELECT name FROM products WHERE id = 12')->fetchColumn();
    $assert($persisted === 'UNIFORME DE FAENA TIPO A','La auditoría no es atómica');
}

// UI is strictly review-only for duplicate detection. Edits require ADMIN + CSRF.
$root = dirname(__DIR__);
$bootstrap = (string)file_get_contents($root.'/app/bootstrap.php');
$controller = (string)file_get_contents($root.'/app/Controllers/InventoryController.php');
$view = (string)file_get_contents($root.'/app/Views/inventory/duplicate-products.php');
$edit = (string)file_get_contents($root.'/app/Views/inventory/product-edit.php');
$admin = (string)file_get_contents($root.'/app/Views/admin/index.php');
foreach ([
    ['duplicate-products', $bootstrap],['edit-product',$bootstrap],['update-product',$bootstrap],
    ['assertCatalogAdministrator',$controller],
    ['CatalogDuplicateInspector::groups',$controller],
    ['productStockMap',$controller],
    ['variantsForProduct',$controller],
    ['csrf_valid',$controller],
    ['Ver artículo y kardex',$view],
    ['no se han eliminado ni fusionado productos',$view],
    ['Editar descripción',$view],
    ['name="_token"',$edit],['name="product_id"',$edit],
    ['Revisar artículos repetidos',$admin],
] as [$needle,$haystack]) {
    $assert(str_contains($haystack,$needle),'Falta protección o interfaz: '.$needle);
}
$assert(!str_contains($view,'method="post"'),'El inspector nunca debe alterar el catálogo');
echo "[OK] Repetidos: nombres, variantes comparables, edición auditada, permisos y ninguna conciliación automática.\n";
