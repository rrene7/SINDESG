<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$appSource = file_get_contents($root . '/config/app.php');
$bootstrap = file_get_contents($root . '/app/bootstrap.php');
$localExample = file_get_contents($root . '/config/local.example.php');
$deploy = file_get_contents($root . '/deploy-windows.ps1');
$readiness = file_get_contents($root . '/scripts/readiness.php');
$docs = file_get_contents($root . '/docs/DESPLIEGUE_INSTITUCIONAL.md');
$gitignore = file_get_contents($root . '/.gitignore');
$app = require $root . '/config/app.php';

foreach (compact(
    'appSource',
    'bootstrap',
    'localExample',
    'deploy',
    'readiness',
    'docs',
    'gitignore'
) as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para Fase 14D." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "config = [",
    "'version' => '0.14.0-d-rc1'",
    "'session_secure' => false",
    "'/local.php'",
    'array_replace',
] as $fragment) {
    if (!str_contains($appSource, $fragment)) {
        fwrite(STDERR, "[ERROR] Configuración 14D incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($bootstrap, "'secure' => (bool) (\$app['session_secure'] ?? false)")) {
    fwrite(STDERR, "[ERROR] Bootstrap no respeta session_secure." . PHP_EOL);
    exit(1);
}

foreach ([
    "'environment' => 'production'",
    "'base_url' => 'auto'",
    "'session_secure' => true",
] as $fragment) {
    if (!str_contains($localExample, $fragment)) {
        fwrite(STDERR, "[ERROR] local.example.php incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'pre_deploy',
    'config\\database.php',
    'config\\local.php',
    'storage\\uploads',
    'storage\\backups',
    'storage\\deployments',
    'robocopy.exe',
    '/MIR',
    'check.php',
    'readiness.php',
    '--strict',
    'DEPLOYMENT_MARKER=',
    'PRE_DEPLOY_BACKUP=',
    '[switch]$Bootstrap',
] as $fragment) {
    if (!str_contains($deploy, $fragment)) {
        fwrite(STDERR, "[ERROR] deploy-windows.ps1 incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "'/config/local.php'",
    "'session_secure'",
    'nginx-sindesg.conf.example',
    "environment === 'production'",
] as $fragment) {
    if (!str_contains($readiness, $fragment)) {
        fwrite(STDERR, "[ERROR] readiness de producción incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    '# Despliegue institucional de SINDESG',
    'release candidate',
    'config/local.php',
    'pre_deploy',
    'Rollback de código',
    'Rollback de datos',
    'readiness.php --strict',
    'Criterio de cierre de Fase 14D',
] as $fragment) {
    if (!str_contains($docs, $fragment)) {
        fwrite(STDERR, "[ERROR] Procedimiento 14D incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($gitignore, 'storage/deployments/*')) {
    fwrite(STDERR, "[ERROR] Marcadores de despliegue runtime no están excluidos de Git." . PHP_EOL);
    exit(1);
}

if (($app['version'] ?? '') !== '0.14.0-d-rc1') {
    fwrite(STDERR, "[ERROR] La versión no corresponde al release candidate 14D." . PHP_EOL);
    exit(1);
}

echo "[OK] Fase 14D de despliegue institucional validada." . PHP_EOL;
