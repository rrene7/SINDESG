<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CentralRequestReview;

$check = static function (bool $yes, string $msg): void {
    if (!$yes) throw new RuntimeException($msg);
};
$root = dirname(__DIR__);
$read = static function (string $file) use ($root): string {
    $data = file_get_contents($root . '/' . $file);
    if (!is_string($data)) throw new RuntimeException('Archivo no disponible: ' . $file);
    return str_replace(["\r\n","\r"], "\n", $data);
};
$show = $read('app/Views/requests/show.php');
$listing = $read('app/Views/requests/index.php');
$grouped = $read('app/Views/requests/central-review.php');
$controller = $read('app/Controllers/RequestsController.php');
$css = $read('public/assets/css/requests.css');
$js = $read('public/assets/js/requests.js');

$check(!str_contains($show, 'action=decide')
    && !str_contains($show, 'request-review-lines')
    && !str_contains($show, 'class="request-review-form"'),
    'El formulario individual duplicado debe desaparecer de la ficha.');
$check(str_contains($show, 'request-send-panel')
    && str_contains($show, 'tracking.php')
    && str_contains($show, 'quantity_requested')
    && str_contains($show, 'request-detail-grid'),
    'No se debe perder seguimiento, los beneficiarios ni la fase de envío del P4.');

$check(str_contains($listing, 'request-primary-review-link')
    && str_contains($listing, "['submitted', 'in_review']")
    && str_contains($listing, "sindesg_role_action_allowed('requests', 'central-review', 'GET'")
    && str_contains($listing, 'Ver ficha nominal')
    && str_contains($listing, '#seguimiento-p4'),
    'Central debe acceder prioritariamente a revisión por tallas sin perder ficha/seguimiento.');
$check(str_contains($show, 'request-hero-review-action')
    && str_contains($show, 'Iniciar revisión por tallas')
    && str_contains($show, 'Revisar y aprobar por tallas')
    && str_contains($show, 'Iniciar revisión agrupada'),
    'Las solicitudes enviadas o en revisión deben ofrecer acceso agrupado prominente.');
$check(str_contains($grouped, '<?= (int) $totalNominees ?>')
    && !str_contains($grouped, '722 expedientes')
    && str_contains($grouped, 'Ver <?= count($group[\'items\']) ?> destinatarios'),
    'El encabezado debe mostrar un número nominal real y conservar el detalle desplegable.');
$check(substr_count($grouped, 'action=decide') === 1
    && str_contains($grouped, 'data-central-variant')
    && str_contains($grouped, 'data-central-item')
    && str_contains($grouped, 'data-central-apply')
    && str_contains($grouped, 'approved_quantity[')
    && str_contains($grouped, 'csrf_token()')
    && str_contains($grouped, 'reviewed_by_employee_id')
    && str_contains($grouped, 'review_notes')
    && str_contains($grouped, '<details class="central-group-detail">'),
    'La revisión agrupada debe seguir admitiendo decisión y desglose nominal auditables.');
$check(str_contains($grouped, "sindesg_role_action_allowed('requests', 'decide', 'POST'")
    && str_contains($grouped, "sindesg_can('requests.manage')"),
    'El formulario de decisión debe ocultarse sin rol CENTRAL/ADMIN o permiso RBAC.');
$check(str_contains($controller, "if (\$action === 'central-review')")
    && str_contains($controller, "CentralRequestReview::groups(\$items)")
    && str_contains($controller, "if (\$action === 'decide')")
    && str_contains($controller, '$this->service->decide('),
    'Se debe reutilizar el controlador y la decisión del servidor, no crear otra ruta de stock.');
$check(str_contains($js, 'data-central-apply')
    && str_contains($css, '.request-primary-review-link')
    && str_contains($css, '.request-hero-review-action'),
    'La interacción de aprobación por grupo debe conservar el JS original y accesos responsivos.');

foreach ([['CENTRAL',true], ['ADMIN',true], ['P4',false], ['VIEWER',false]] as [$role,$canReview]) {
    $user = ['role_codes'=>[$role]];
    $check(sindesg_role_action_allowed('requests','central-review','GET',$user) === $canReview,
        'Acceso agrupado incorrecto para '.$role);
    $check(sindesg_role_action_allowed('requests','decide','POST',$user) === $canReview,
        'Decisión por lote incorrecta para '.$role);
}

// A high-volume request should show one group per variant, never merge beneficiaries.
$rows = [];
$sizeNames = ['10', '10.5', '11', '11.5'];
foreach (range(1,520) as $i) {
    $offset = ($i-1) % 4;
    $rows[] = [
        'id'=>$i, 'product_variant_id'=>100+$offset,
        'product_name'=>'Botas Tácticas',
        'size_name'=>$sizeNames[$offset],
        'quantity_requested'=>1, 'quantity_approved'=>0,
        'central_stock'=>130, 'available_to_approve'=>130,
        'beneficiary_employee_id'=>$i,
        'beneficiary_label_snapshot'=>'Funcionario '.$i,
    ];
}
$groups = CentralRequestReview::groups($rows);
$check(count($groups)===4,'520 renglones deben mostrar únicamente 4 grupos por talla.');
$counts = [];
$seen = [];
foreach ($groups as $group) {
    $check(count($group['items'])===130 && (float) $group['requested']===130.0,
        'Cada talla debe agrupar 130 registros sin alterar cantidades.');
    foreach ($group['items'] as $row) {
        $check(!isset($seen[$row['id']]), 'Beneficiario duplicado o perdido en agrupación.');
        $seen[$row['id']] = true;
    }
    $counts[] = $group['size_name'];
}
$check(count($seen)===520 && count(array_unique($counts))===4,
    'El consolidado no puede borrar o mezclar identidades nominales.');
$check(substr_count($grouped, 'data-label=') >= 8
    && str_contains($grouped, 'data-central-requested=')
    && str_contains($grouped, 'Solicitado: <?= e(sindesg_units((float) $item[\'quantity_requested\'])) ?>'),
    'Cada tarjeta movil debe identificar los totales por talla y la necesidad nominal individual.');
$check(str_contains($js, 'selectedPeople')
    && str_contains($js, 'requestedTotal')
    && str_contains($js, 'Aprobación parcial:')
    && str_contains($js, 'Verifique los funcionarios incluidos')
    && str_contains($js, 'desired > available')
    && str_contains($js, 'totalForGroup > available'),
    'La selección parcial debe mostrar cuantos funcionarios cubre, advertir limites y confirmar la decision nominal.');
$check(str_contains($css, '@media (max-width: 760px)')
    && str_contains($css, '.central-size-table tr:not(.central-detail-row)')
    && str_contains($css, 'content: attr(data-label)')
    && str_contains($grouped, 'approved_quantity[')
    && substr_count($grouped, 'action=decide') === 1,
    'La interfaz movil debe reutilizar el unico POST seguro, sin duplicar campos.');
echo "[OK] Central: aprobacion agrupada unica, confirmacion parcial nominal y tarjetas moviles, sin tocar inventario.\n";
