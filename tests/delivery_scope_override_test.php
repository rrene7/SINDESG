<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/database/schema/013_delivery_scope_override.sql');
$service = file_get_contents($root . '/app/Services/DeliveryService.php');
$repository = file_get_contents($root . '/app/Repositories/DeliveryRepository.php');
$form = file_get_contents($root . '/app/Views/deliveries/form.php');
$show = file_get_contents($root . '/app/Views/deliveries/show.php');
$js = file_get_contents($root . '/public/assets/js/deliveries.js');

foreach (compact('schema','service','repository','form','show','js') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach (['scope_override_enabled', 'scope_override_reason', '013_delivery_scope_override'] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Migración de excepción incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['allow_out_of_scope', 'scope_override_reason', 'Habilitar entrega', 'Entrega excepcional'] as $fragment) {
    if (!str_contains($form, $fragment)) {
        fwrite(STDERR, "[ERROR] Casilla de excepción incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['scopeOverrideEnabled', 'scopeOverrideReason', 'pertenece actualmente', 'Habilitar entrega'] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Validación de excepción incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['scope_override_enabled', 'scope_override_reason'] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Trazabilidad de excepción incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($show, 'Entrega excepcional autorizada')) {
    fwrite(STDERR, "[ERROR] El comprobante no muestra la excepción." . PHP_EOL);
    exit(1);
}

if (!str_contains($js, 'syncScopeOverride')) {
    fwrite(STDERR, "[ERROR] La casilla no controla el motivo requerido." . PHP_EOL);
    exit(1);
}

echo "[OK] Excepción controlada de adscripción validada." . PHP_EOL;
