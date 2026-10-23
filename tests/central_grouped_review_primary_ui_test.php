<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CentralRequestReview;

$assert = static function (bool $ok, string $why): void {
    if (!$ok) throw new RuntimeException($why);
};
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $v = file_get_contents($root . '/' . $relative);
    if (!is_string($v)) throw new RuntimeException('Falta archivo ' . $relative);
    return str_replace(["\r\n", "\r"], "\n", $v);
};
$index = $read('app/Views/requests/index.php');
$show = $read('app/Views/requests/show.php');
$group = $read('app/Views/requests/central-review.php');
$controller = $read('app/Controllers/RequestsController.php');
$repository = $read('app/Repositories/RequestRepository.php');
$js = $read('public/assets/js/requests.js');

$assert(sindesg_role_action_allowed('requests', 'central-review', 'GET', ['role_codes'=>['CENTRAL']]),
    'Central debe consultar revisión agrupada.');
$assert(!sindesg_role_action_allowed('requests', 'central-review', 'GET', ['role_codes'=>['P4']]),
    'P4 no debe ingresar a revisión Central.');
$assert(!sindesg_role_action_allowed('requests', 'decide', 'POST', ['role_codes'=>['P4']]),
    'P4 no puede aprobar.');
$assert(sindesg_role_action_allowed('requests', 'decide', 'POST', ['role_codes'=>['CENTRAL']]),
    'Central conserva aprobación.');
$assert(sindesg_role_action_allowed('requests', 'central-review', 'GET', ['role_codes'=>['ADMIN']]),
    'ADMIN conserva revisión de solicitudes.');

$assert(str_contains($index, "in_array(\$row['status'], ['submitted', 'in_review'], true)")
    && str_contains($index, "sindesg_role_action_allowed('requests', 'central-review', 'GET'")
    && str_contains($index, 'Revisar por talla')
    && str_contains($index, 'Ver expediente')
    && str_contains($index, '#seguimiento-p4'),
    'Listado debe priorizar revisión por talla para Central, conservando expediente y seguimiento.');
$assert(str_contains($show, 'Revisión consolidada por artículo y talla')
    && str_contains($show, 'Revisar y aprobar por talla'),
    'Detalle debe llevar a la revisión agrupada.');
$assert(!str_contains($show, 'class="request-review-lines"')
    && !str_contains($show, 'name="approved_quantity[')
    && !str_contains($show, 'class="request-review-form"'),
    'Detalle conserva el formulario individual duplicado.');
$assert(str_contains($show, 'tracking.php')
    && str_contains($show, 'beneficiary_label_snapshot')
    && str_contains($show, 'request-send-panel'),
    'No se debe perder seguimiento ni edición de borrador P4.');

foreach ([
    'data-central-review',
    'data-central-variant',
    'data-central-quantity',
    'data-central-apply',
    '<details class="central-group-detail">',
    'data-central-item',
    'approved_quantity[',
    'csrf_token()',
    'reviewed_by_employee_id',
    'action=decide',
    'central_stock',
    'available_to_approve',
    '(int) $totalNominees',
] as $mark) {
    $assert(str_contains($group, $mark), 'Revisión Central perdió función: ' . $mark);
}
$assert(!str_contains($group, 'sin abrir 722 expedientes'),
    'Cabecera contiene cifra nominal de ejemplo en vez del total real.');
foreach (['data-central-apply','data-central-variant','data-central-item','event.preventDefault()'] as $mark) {
    $assert(str_contains($js, $mark), 'JS de distribución por talla no conectado: '.$mark);
}
$assert(str_contains($controller,"if (\$action === 'central-review')")
    && str_contains($controller,'CentralRequestReview::groups($items)')
    && str_contains($controller, "if (\$action === 'decide')")
    && str_contains($repository, 'freeByVariant'),
    'Ruta agrupada y verificación server-side de stock deben conservarse.');

$lines = [];
for ($i = 0; $i < 520; $i++) {
    $size = $i < 260 ? '11' : '11.5';
    $variant = $i < 260 ? 11 : 12;
    $lines[] = [
        'id'=>$i+1, 'product_variant_id'=>$variant,
        'product_name'=>'Botas Tácticas', 'size_name'=>$size,
        'central_stock'=>150, 'available_to_approve'=>80,
        'quantity_requested'=>1, 'quantity_approved'=>0,
        'beneficiary_employee_id'=>$i+1
    ];
}
$groups = CentralRequestReview::groups($lines);
$assert(count($groups)===2 && count($groups[0]['items'])===260
    && count($groups[1]['items'])===260
    && (float)$groups[0]['requested']===260.0
    && (float)$groups[1]['requested']===260.0
    && (float)$groups[0]['available_to_approve']===80.0,
    'Agrupación de 520 beneficiarios: no debe inventar stock ni perder renglones.');

echo "[OK] Central: aprobación consolidada como vista principal; nominal desplegable, control de stock y P4 intactos.\n";
