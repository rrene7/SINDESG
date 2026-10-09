<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root . '/scripts/readiness.php');
$check = file_get_contents($root . '/scripts/check.php');
$app = require $root . '/config/app.php';

if ($script === false || $check === false || !is_array($app)) {
    fwrite(STDERR, "[ERROR] No se pudieron leer artefactos de Fase 14A." . PHP_EOL);
    exit(1);
}

foreach ([
    "version_compare(PHP_VERSION, '8.2.0'",
    "'pdo_mysql'",
    "'mbstring'",
    "'dom'",
    "'fileinfo'",
    "'zip'",
    "'storage/uploads'",
    "'storage/logs'",
    "'storage/cache'",
    "'config/local.php'",
    "'session_secure'",
    "'/config/database.php'",
    "schema_migrations",
    "'0.13.0-c'",
    "SHOW GRANTS",
    "read_only",
    "rrhhRead('SELECT 1 AS readiness_check')",
    "--strict",
    "--no-database",
] as $fragment) {
    if (!str_contains($script, $fragment)) {
        fwrite(STDERR, "[ERROR] Readiness 14A incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'database/schema/017_historical_catalog_preparation.sql',
    'database/schema/018_historical_reconciliation.sql',
    'scripts/readiness.php',
    'tests/readiness_logic_test.php',
] as $fragment) {
    if (!str_contains($check, $fragment)) {
        fwrite(STDERR, "[ERROR] scripts/check.php no exige {$fragment}." . PHP_EOL);
        exit(1);
    }
}

if (!in_array(($app['version'] ?? ''), ['0.14.0-a-dev', '0.14.0-b-dev', '0.14.0-c-dev', '0.14.0-d-rc1'], true)) {
    fwrite(STDERR, "[ERROR] La versión de aplicación no corresponde a una versión válida de estabilización 14A/14B/14C/14D." . PHP_EOL);
    exit(1);
}

echo "[OK] Fase 14A de preparación integral validada." . PHP_EOL;
