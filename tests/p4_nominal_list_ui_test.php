<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/P4NominalLineState.php';

use App\Services\P4NominalLineState;

// Read-only classification: no DB, no stock movement or request edit.
$base = ['beneficiary_employee_id'=>991, 'quantity_approved'=>1.0,
    'quantity_received'=>1.0, 'quantity_delivered'=>0.0];
$cases = [
    [$base, 'ready', 'Disponible para entregar'],
    [array_replace($base,['quantity_delivered'=>0.5]), 'ready', 'Entrega parcial · disponible'],
    [array_replace($base,['quantity_received'=>0.0]), 'waiting', 'Pendiente de recepción'],
    [array_replace($base,['quantity_received'=>0.5,'quantity_delivered'=>0.5]), 'waiting', 'Entrega parcial · por recibir'],
    [array_replace($base,['quantity_delivered'=>1.0]), 'delivered', 'Entregado'],
    [array_replace($base,['quantity_approved'=>0.0]), 'review', 'Pendiente de aprobación'],
    [array_replace($base,['beneficiary_employee_id'=>null]), 'consolidated', 'Cantidad consolidada'],
    [array_replace($base,['quantity_approved'=>2.0,'quantity_received'=>0.0,'quantity_delivered'=>0.0]), 'waiting', 'Pendiente de recepción'],
];
foreach ($cases as [$item,$expected,$label]) {
    $state=P4NominalLineState::classify($item);
    if ($state !== ['key'=>$expected,'label'=>$label]) {
        throw new RuntimeException('Incorrect nominal line status: ' . json_encode($state));
    }
}
$root=dirname(__DIR__);
$view=(string)file_get_contents($root.'/app/Views/requests/show.php');
$js=(string)file_get_contents($root.'/public/assets/js/requests.js');
$css=(string)file_get_contents($root.'/public/assets/css/requests.css');
foreach ([
    'data-request-nominal-list','data-request-nominal-row','data-request-line-state',
    'data-request-nominal-search','data-request-nominal-filter','data-request-nominal-page-size',
    'data-request-nominal-empty','data-request-nominal-pagination','P4NominalLineState::classify'
] as $token) {
    if (!str_contains($view,$token)) throw new RuntimeException('Missing scoped nominal UI: '.$token);
}
if (!str_contains($view, "sindesg_url('?module=deliveries&action=new&request_item_id='")
    || !str_contains($view, 'csrf_token()') || !str_contains($view, 'sindesg_units(')) {
    throw new RuntimeException('Original delivery link, CSRF or unit display was lost.');
}
foreach ([
    'DOMContentLoaded','requestLineState','page * perPage','row.hidden = !visible.has(row)',
    'normalize(\'NFD\')','data-request-nominal-next','data-request-nominal-prev'
] as $token) {
    if (!str_contains($js,$token)) throw new RuntimeException('Missing nominal client filter: '.$token);
}
if (!str_contains($css, '[data-request-nominal-row][hidden]')) {
    throw new RuntimeException('Hidden rows must remain hidden in responsive table.');
}
echo "[OK] P4 nominal list state, search/filter/pagination controls remain read-only.\n";
