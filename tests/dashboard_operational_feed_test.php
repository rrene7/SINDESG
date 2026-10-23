<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\DashboardOperationalFeed;

$check = static function(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->sqliteCreateFunction('GREATEST', static fn(...$values) => max(...$values), -1);
$pdo->exec(<<<'SQL'
CREATE TABLE warehouses (
 id INTEGER PRIMARY KEY, code TEXT, name TEXT, warehouse_type TEXT,
 organization_unit_name_snapshot TEXT, is_active INTEGER
);
CREATE TABLE service_requests (
 id INTEGER PRIMARY KEY, request_code TEXT, status TEXT, requested_at TEXT,
 p4_warehouse_id INTEGER
);
CREATE TABLE service_request_items (
 id INTEGER PRIMARY KEY, request_id INTEGER,
 quantity_requested NUMERIC, quantity_approved NUMERIC,
 quantity_dispatched NUMERIC
);
CREATE TABLE distributions (
 id INTEGER PRIMARY KEY, transfer_code TEXT, status TEXT,
 dispatched_at TEXT, destination_warehouse_id INTEGER
);
CREATE TABLE distribution_items (
 id INTEGER PRIMARY KEY, distribution_id INTEGER, quantity NUMERIC
);
SQL);

$warehouse = $pdo->prepare('INSERT INTO warehouses VALUES (?,?,?,?,?,?)');
for ($i = 1; $i <= 135; $i++) {
    $warehouse->execute([$i, 'P4-' . $i, 'Almacén ' . $i, 'P4',
        'Zona Policial ' . $i, $i === 135 ? 0 : 1]);
}
$warehouse->execute([500,'CENTRAL-SG','Central','CENTRAL',NULL,1]);

$request = $pdo->prepare('INSERT INTO service_requests VALUES (?,?,?,?,?)');
$item = $pdo->prepare('INSERT INTO service_request_items VALUES (?,?,?,?,?)');
for ($i = 1; $i <= 31; $i++) {
    $status = $i <= 12 ? 'submitted' : ($i <= 20 ? 'in_review'
        : ($i <= 27 ? 'approved' : 'partially_approved'));
    $request->execute([$i,'SOL-'.str_pad((string)$i,5,'0',STR_PAD_LEFT),
        $status,'2026-10-23 11:00:00',$i]);
    // Remaining approved quantity in ready requests; zero for one to exclude.
    $approved = $i > 20 ? 2 : 0;
    $dispatched = $i === 31 ? 2 : 0;
    $item->execute([$i,$i,2,$approved,$dispatched]);
}
$distribution = $pdo->prepare('INSERT INTO distributions VALUES (?,?,?,?,?)');
$distributionItem = $pdo->prepare('INSERT INTO distribution_items VALUES (?,?,?)');
for ($i=1;$i<=4;$i++) {
    $distribution->execute([$i,'TRF-'.str_pad((string)$i,5,'0',STR_PAD_LEFT),
        $i===4 ? 'received' : 'dispatched', '2026-10-23 12:00:00', $i]);
    $distributionItem->execute([$i,$i,2]);
}

$feed = new DashboardOperationalFeed($pdo);
$review = $feed->fetch('review',1);
$check($review['total']===20 && count($review['items'])===8
    && $review['page']===1 && $review['pages']===3,
    'Las pendientes deben estar paginadas y completas (20, 8 por página).');
$last = $feed->fetch('review',3);
$check(count($last['items'])===4 && $last['total']===20,
    'La tercera página debe mostrar los cuatro registros restantes.');
$search = $feed->fetch('review',1,'Zona Policial 12');
$check($search['total']===1 && $search['items'][0]['location_name']==='Zona Policial 12',
    'La búsqueda por zona no debe mezclar solicitudes ajenas.');
$over = $feed->fetch('review',9999);
$check($over['page']===3 && count($over['items'])===4,
    'Las páginas fuera de rango se deben acotar sin revelar otras categorías.');
$ready = $feed->fetch('ready');
$check($ready['total']===10 && $ready['items'][0]['pending_quantity']>0,
    'Listas para distribuir: solo cantidades aprobadas pendientes de despacho.');
foreach ($ready['items'] as $row) {
    $check(in_array($row['status'], ['approved','partially_approved'],true),
        'Solo solicitudes aprobadas deben entrar en listas de distribución.');
}
$transit = $feed->fetch('transit');
$check($transit['total']===3 && $transit['items'][0]['status']==='dispatched',
    'Transferencias en tránsito deben excluir las ya recibidas.');
$p4 = $feed->fetch('p4',1);
$check($p4['total']===134 && $p4['pages']===17
    && count($p4['items'])===8, '134 P4 activos deben consultarse sin lista creciente.');
$p4Last = $feed->fetch('p4',17);
$check(count($p4Last['items'])===6, 'Última página de 134 P4.');
$check($feed->fetch('p4',1,'P4-135')['total']===0,
    'No mostrar almacenes P4 inactivos.');
$check($feed->fetch('p4',1,'Zona Policial 120')['total']===1,
    'Búsqueda paginada por unidad orgánica.');
$check(DashboardOperationalFeed::permissionFor('review')==='requests.view'
    && DashboardOperationalFeed::permissionFor('ready')==='requests.view'
    && DashboardOperationalFeed::permissionFor('transit')==='distribution.view'
    && DashboardOperationalFeed::permissionFor('p4')==='warehouses.view',
    'Cada categoría debe exigir permiso del módulo correspondiente.');
try {
    DashboardOperationalFeed::permissionFor('all');
    throw new RuntimeException('Filtro arbitrario aceptado.');
} catch (InvalidArgumentException $expected) {
}

$root = dirname(__DIR__);
$controller = (string)file_get_contents($root.'/public/index.php');
$view = (string)file_get_contents($root.'/app/Views/dashboard.php');
$js = (string)file_get_contents($root.'/public/assets/js/app.js');
$css = (string)file_get_contents($root.'/public/assets/css/app.css');
$check(str_contains($controller, "if (\$currentModule === 'dashboard' && \$currentAction === 'operational-feed')")
    && str_contains($controller, "in_array('ADMIN', \$roles, true)")
    && str_contains($controller, "in_array('CENTRAL', \$roles, true)")
    && str_contains($controller, "sindesg_can(\$permission)")
    && str_contains($controller, "Cache-Control: no-store")
    && str_contains($controller, "json_encode(\$feed->fetch("),
    'La ruta de lectura necesita autenticación, aislamiento P4 y permisos diferenciados.');
$check(str_contains($view,'data-operations-browser')
    && str_contains($view,'data-ops-kind')
    && str_contains($view,'data-ops-search-form')
    && str_contains($view,'data-ops-prev')
    && str_contains($view,'data-ops-next')
    && !str_contains($view,'foreach ($recentDashboardRequests as $row)'),
    'El dashboard debe ser interactivo y no mostrar una lista ilimitada.');
$check(str_contains($js,"fetch(url.toString()")
    && str_contains($js,"credentials: 'same-origin'")
    && str_contains($js,"list.replaceChildren()")
    && str_contains($js, "textContent = value")
    && str_contains($js, "aria-pressed"),
    'El cliente debe recuperar páginas y renderizar texto seguro.');
$check(str_contains($css,'.dashboard-ops-scroll{max-height:290px'),
    'El detalle necesita una altura máxima aunque aumente el número de zonas.');
echo "[OK] Gestión operativa: 134 P4, solicitudes/transferencias filtradas, búsqueda, paginación, permisos y panel compacto.\n";
