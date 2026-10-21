<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/AuditRepository.php';
require dirname(__DIR__) . '/app/Repositories/CatalogRepository.php';
require dirname(__DIR__) . '/app/Services/CatalogService.php';

use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

$root = dirname(__DIR__);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec(<<<'SQL'
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    sort_order INTEGER NOT NULL DEFAULT 900,
    is_active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER, user_label_snapshot TEXT, module_code TEXT, action_code TEXT,
    record_type TEXT, record_id TEXT, details_json TEXT, before_json TEXT, after_json TEXT,
    ip_address TEXT, user_agent TEXT
);
INSERT INTO categories (id, code, slug, name, description, sort_order, is_active) VALUES
(1,'CAT-000001','uniformes','Uniformes',NULL,10,1),
(2,'CAT-000002','gorros','Gorros',NULL,20,1),
(3,'CAT-000003','archivo','Archivo histórico',NULL,30,0);
INSERT INTO products (category_id,status) VALUES (1,'deleted');
SQL);

$assert = static function (bool $test, string $message): void {
    if (!$test) throw new RuntimeException($message);
};
$repo = new CatalogRepository($pdo);
$service = new CatalogService($repo);
$actor = ['id' => 77, 'display_name' => 'Administrador de prueba'];

$service->updateCategory(1, [
    'name' => 'Uniformes institucionales',
    'description' => 'Dotación',
    'sort_order' => '15',
], $actor);
$cat = $repo->getCategory(1);
$assert($cat['name'] === 'Uniformes institucionales', 'No actualizó el nombre');
$assert($cat['code'] === 'CAT-000001' && $cat['slug'] === 'uniformes', 'Alteró identificadores permanentes');
$assert((int) $cat['sort_order'] === 15, 'No actualizó orden');
$assert((int) $cat['all_product_count'] === 1, 'No contabiliza un artículo histórico');

$reject = static function (Closure $action, string $message): void {
    try {
        $action();
        throw new RuntimeException('No fue rechazada la operación: ' . $message);
    } catch (InvalidArgumentException $exception) {
        // Expected.
    }
};

$reject(
    static fn () => $service->changeCategoryStatus(1, false, 'Retiro administrativo', $actor),
    'Categoría vinculada con un artículo histórico'
);
$assert((int) $repo->getCategory(1)['is_active'] === 1, 'Archivó categoría vinculada');

$reject(
    static fn () => $service->changeCategoryStatus(2, false, 'No', $actor),
    'Motivo insuficiente'
);
$reject(
    static fn () => $service->updateCategory(2, ['name'=>'Uniformes institucionales', 'sort_order'=>22], $actor),
    'Nombre duplicado'
);

$service->changeCategoryStatus(2, false, 'Categoría duplicada sin artículos', $actor);
$assert((int) $repo->getCategory(2)['is_active'] === 0, 'No archivó categoría vacía');
$activeIds = array_map(static fn (array $row): int => (int) $row['id'], $repo->listActiveCategories());
$assert(!in_array(2, $activeIds, true), 'La categoría archivada aún se ofrece para nuevos artículos');
$service->changeCategoryStatus(2, true, 'Reactivación autorizada y revisada', $actor);
$assert((int) $repo->getCategory(2)['is_active'] === 1, 'No restauró la categoría');

$newId = $service->createCategory(['name'=>'Accesorios tácticos'], $actor);
$new = $repo->getCategory($newId);
$assert($new['code'] === sprintf('CAT-%06d', $newId), 'La secuencia de códigos falló');
$assert(
    $new['slug'] === 'accesorios-tacticos',
    'Slug español distinto entre sistemas: esperado accesorios-tacticos, obtenido ' . $new['slug']
);

$events = $pdo->query('SELECT action_code, user_id, before_json, after_json FROM audit_logs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$actions = array_column($events, 'action_code');
$assert($actions === ['category_updated','category_archived','category_restored','category_created'],
    'Las cuatro acciones no están auditadas o hay auditoría de operaciones rechazadas');
foreach ($events as $event) {
    $assert((int) $event['user_id'] === 77 && $event['after_json'] !== null,
        'La auditoría no identifica actor y estado posterior');
}

// Deterministic across Linux iconv and Windows XAMPP iconv.
$accentCases = [
    ['name' => 'Ñandú y pingüinos', 'slug' => 'nandu-y-pinguinos'],
    ['name' => 'CAMISAS ÁÉÍÓÚ', 'slug' => 'camisas-aeiou'],
    ['name' => "Accesorios ta\u{0301}cticos", 'slug' => 'accesorios-tacticos-2'],
];
foreach ($accentCases as $case) {
    $id = $service->createCategory(['name' => $case['name']], $actor);
    $actualSlug = (string) $repo->getCategory($id)['slug'];
    $assert(
        $actualSlug === $case['slug'],
        'Normalización Unicode no portable para ' . $case['name']
            . ': esperado ' . $case['slug'] . ', recibido ' . $actualSlug
    );
}

$pdo->exec('DROP TABLE audit_logs');
try {
    $service->updateCategory(1, [
        'name'=>'No debe persistir', 'description'=>'rollback', 'sort_order'=>16,
    ], $actor);
    throw new RuntimeException('La actualización ignoró un error de auditoría');
} catch (PDOException $exception) {
    $assert($repo->getCategory(1)['name'] === 'Uniformes institucionales',
        'Actualización sin auditoría: falta rollback transaccional');
}

// Route authorization and CSRF remain in the original application router.
$bootstrap = (string) file_get_contents($root . '/app/bootstrap.php');
$controller = (string) file_get_contents($root . '/app/Controllers/InventoryController.php');
$index = (string) file_get_contents($root . '/app/Views/inventory/index.php');
$adminView = (string) file_get_contents($root . '/app/Views/inventory/categories-admin.php');
$form = (string) file_get_contents($root . '/app/Views/inventory/category-form.php');
foreach ([
    ["return false;\n    }\n\n    // Administration is reserved to ADMIN", $bootstrap],
    ["'edit-category'", $bootstrap],
    ["'archive-category'", $bootstrap],
    ["'categories'", $controller],
    ['assertCatalogAdministrator', $controller],
    ['csrf_valid', $controller],
    ['name="_token"', $adminView],
    ['name="reason"', $adminView],
    ['name="confirm"', $adminView],
    ['all_product_count', $adminView],
    ['Editar categoría', $index],
    ['name="sort_order"', $form],
    ['category_id', $form],
] as [$fragment, $content]) {
    $assert(str_contains($content, $fragment), 'Falta el contrato UI/RBAC: ' . $fragment);
}
echo "[OK] Administración de categorías: edición, código estable, archivo reversible, historial protegido, auditoría atómica, UI y RBAC.\n";
