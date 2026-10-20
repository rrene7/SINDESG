<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/P4QuantityDisplay.php';
require dirname(__DIR__) . '/app/Services/P4RequestTracking.php';

use App\Services\P4RequestTracking;

// Pure, isolated regression. No DB, no real request, no inventory mutation.
$request = [
    'status' => 'partially_approved',
    'created_at' => '2026-10-13 12:00:00',
    'submitted_at' => '2026-10-14 10:00:00',
    'reviewed_at' => '2026-10-14 12:00:00',
];
$quantities = [
    'approved' => 1000.0,
    'dispatched' => 350.0,
    'received' => 350.0,
    'delivered' => 2.0,
];
$transfers = [
    'prepared_count' => 1,
    'authorized_count' => 1,
    'last_dispatched_at' => '2026-10-19 10:00:00',
    'last_received_at' => '2026-10-19 11:00:00',
];
$tracking = P4RequestTracking::build($request, $quantities, $transfers);
$steps = array_column($tracking['steps'], null, 'key');
foreach ([
    'approved' => '1 000 unidades aprobadas',
    'dispatched' => '350 unidades despachadas',
    'received' => '350 unidades recibidas',
    'delivered' => '2 de 1 000 unidades aprobadas entregadas · 998 pendientes',
] as $key => $expected) {
    if (!isset($steps[$key]) || $steps[$key]['detail'] !== $expected
        || ($key !== 'delivered' && !$steps[$key]['done'])) {
        throw new RuntimeException('Tracking value or milestone changed: ' . $key);
    }
}
if (count($steps) !== 8
    || $steps['delivered']['done'] !== false
    || $steps['delivered']['in_progress'] !== true
    || $tracking['summary'] !== 'Entrega parcial: 2 de 1 000 unidades aprobadas entregadas; 998 pendientes.') {
    throw new RuntimeException('Tracking summary or milestone count unexpectedly changed.');
}
$partial = P4RequestTracking::build(
    ['status' => 'approved', 'reviewed_at' => '2026-10-15'],
    ['approved' => 1.5, 'dispatched' => 1.25, 'received' => 1.125, 'delivered' => 0],
    ['prepared_count' => 1]
);
$p = array_column($partial['steps'], null, 'key');
if ($p['approved']['detail'] !== '1,5 unidades aprobadas'
    || $p['dispatched']['detail'] !== '1,25 unidades despachadas'
    || $p['received']['detail'] !== '1,125 unidades recibidas'
    || $p['delivered']['detail'] !== '0 de 1,5 unidades aprobadas entregadas · 1,5 pendientes'
    || $p['delivered']['done'] !== false
    || $p['delivered']['in_progress'] !== false) {
    throw new RuntimeException('Fractional or zero quantities were altered or misleading.');
}
$draft = P4RequestTracking::build(['status' => 'draft'], [], []);
if ($draft['summary'] !== 'Borrador P4: falta enviar al Almacén Central.'
    || $draft['steps'][1]['done'] !== false) {
    throw new RuntimeException('Draft state changed.');
}
$rejected = P4RequestTracking::build(['status'=>'rejected','submitted_at'=>'2026-10-14'], [], []);
if ($rejected['summary'] !== 'La solicitud fue rechazada por Central.') {
    throw new RuntimeException('Rejected state changed.');
}
$source = (string)file_get_contents(dirname(__DIR__) . '/app/Services/P4RequestTracking.php');
if (str_contains($source, 'number_format(')
    || substr_count($source, 'P4QuantityDisplay::units(') < 4) {
    throw new RuntimeException('Tracking bypasses institutional formatting.');
}
$complete = P4RequestTracking::build(['status'=>'fulfilled'], [
    'approved'=>350, 'dispatched'=>350, 'received'=>350, 'delivered'=>350,
], []);
$completeSteps = array_column($complete['steps'], null, 'key');
if (!$completeSteps['delivered']['done'] || $completeSteps['delivered']['in_progress']
    || $complete['summary'] !== 'Entrega de la dotación aprobada completada.') {
    throw new RuntimeException('Complete individual delivery was not marked completed.');
}
$partiallyReceived = P4RequestTracking::build(['status'=>'fulfilled'], [
    'approved'=>350, 'dispatched'=>200, 'received'=>200, 'delivered'=>200,
], []);
if ($partiallyReceived['steps'][7]['done'] || !$partiallyReceived['steps'][7]['in_progress']) {
    throw new RuntimeException('Delivering received stock must not close an unfulfilled approval.');
}
$view = (string) file_get_contents(dirname(__DIR__) . '/app/Views/requests/tracking.php');
$css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/requests.css');
if (!str_contains($view, "request-tracking-state")
    || !str_contains($view, "'in-progress'")
    || !str_contains($css, '.request-tracking-step.in-progress')) {
    throw new RuntimeException('Partial delivery visual status missing.');
}
$show = (string) file_get_contents(dirname(__DIR__) . '/app/Views/requests/show.php');
if (!str_contains($show, "'fulfilled' => 'Atendida por Central'")) {
    throw new RuntimeException('Central fulfillment label must be distinct from individual deliveries.');
}
echo "[OK] P4 request tracking distinguishes partial and completed individual deliveries.\n";
