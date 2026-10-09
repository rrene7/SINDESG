<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/DeliveryRepository.php');
$deliveryController = file_get_contents($root . '/app/Controllers/DeliveryController.php');
$deliveryView = file_get_contents($root . '/app/Views/deliveries/show.php');
$peopleController = file_get_contents($root . '/app/Controllers/PeopleController.php');
$peopleView = file_get_contents($root . '/app/Views/people/show.php');

foreach (compact(
    'repository',
    'deliveryController',
    'deliveryView',
    'peopleController',
    'peopleView'
) as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'public function confirmationEvidence',
    "im.movement_kind = 'individual_delivery'",
    "im.direction = 'OUT'",
    "CONCAT(",
    "'individual-delivery:'",
    'LEFT JOIN v_inventory_stock vs',
    "'all_verified'",
    'request_received',
    'request_delivered',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta evidencia P4 → funcionario: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'deliveryVerification',
    'confirmationEvidence($deliveryId)',
    'P4 → funcionario',
    'P4 OUT',
    'Stock P4 actual',
    'renglones verificados',
    'Ver artículo →',
] as $fragment) {
    if (!str_contains($deliveryController . $deliveryView, $fragment)) {
        fwrite(STDERR, "[ERROR] Despacho no muestra verificación funcional: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'use App\\Repositories\\DeliveryRepository;',
    'private DeliveryRepository $deliveries;',
    "connection('sindesg')",
    'historySummaryForEmployee',
    "'deliveryHistory'",
] as $fragment) {
    if (!str_contains($peopleController, $fragment)) {
        fwrite(STDERR, "[ERROR] Perfil de funcionario no integra historial SINDESG: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'HISTORIAL SINDESG',
    'Dotaciones entregadas',
    'Historial completo',
    'module=deliveries&action=show&id=',
    'no tiene entregas confirmadas registradas en SINDESG',
] as $fragment) {
    if (!str_contains($peopleView, $fragment)) {
        fwrite(STDERR, "[ERROR] Ficha de funcionario no muestra historial: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (preg_match('/\b(INSERT|UPDATE|DELETE)\b.*rrhh2029/is', $peopleController . $repository) === 1) {
    fwrite(STDERR, "[ERROR] El historial no debe escribir en RRHH2029." . PHP_EOL);
    exit(1);
}

echo "[OK] P4 → funcionario → historial SINDESG validado." . PHP_EOL;
