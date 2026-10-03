<?php
declare(strict_types=1);

$migration = file_get_contents(dirname(__DIR__) . '/database/schema/007_dashboard_rrhh_receipts.sql');

if ($migration === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer migración UTF-8." . PHP_EOL);
    exit(1);
}

foreach ([
    'SET NAMES utf8mb4',
    '0x5375C3A9746572',
    '0x50616e74616cc3b36e',
    "WHERE slug = 'sueter'",
    "WHERE slug = 'pantalon'",
] as $fragment) {
    if (!str_contains($migration, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta reparación UTF-8: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Reparación UTF-8 validada." . PHP_EOL;
