<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/009_requests.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 009_requests.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'CREATE TABLE IF NOT EXISTS service_requests',
    'CREATE TABLE IF NOT EXISTS service_request_items',
    'requested_by_employee_id',
    'beneficiary_employee_id',
    'quantity_requested',
    'quantity_approved',
    "'partially_approved'",
    "'fulfilled'",
    "VALUES ('009_requests')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta en Fase 7: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Esquema de Solicitudes validado." . PHP_EOL;
