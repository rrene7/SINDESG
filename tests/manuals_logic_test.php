<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$technical = file_get_contents($root . '/docs/MANUAL_TECNICO.md');
$user = file_get_contents($root . '/docs/MANUAL_USUARIO.md');
$readme = file_get_contents($root . '/README.md');
$roadmap = file_get_contents($root . '/docs/ROADMAP.md');
$app = require $root . '/config/app.php';

foreach (compact('technical', 'user', 'readme', 'roadmap') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para Fase 14C." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    '# Manual técnico de SINDESG',
    'RRHH2029',
    'solo lectura',
    'readiness.php',
    'backup.php',
    'restore.php',
    'git pull --ff-only origin main',
    'Migraciones de base de datos',
    'Auditoría',
    'Inventario y consistencia',
    'Cierre técnico antes de producción',
] as $fragment) {
    if (!str_contains($technical, $fragment)) {
        fwrite(STDERR, "[ERROR] Manual técnico incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    '# Manual de usuario de SINDESG',
    'Dashboard',
    'Inventario',
    'Recepción de lotes',
    'Producción interna',
    'Funcionarios',
    'Almacenes y P4',
    'Solicitudes',
    'Distribución Central → P4',
    'Despacho individual',
    'Ajustes y devoluciones',
    'Reportes',
    'Migración histórica',
    'Auditoría',
    'Administración',
    'Qué no debe hacerse',
] as $fragment) {
    if (!str_contains($user, $fragment)) {
        fwrite(STDERR, "[ERROR] Manual de usuario incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    '[Manual técnico](docs/MANUAL_TECNICO.md)',
    '[Manual de usuario](docs/MANUAL_USUARIO.md)',
] as $fragment) {
    if (!str_contains($readme, $fragment)) {
        fwrite(STDERR, "[ERROR] README no enlaza {$fragment}." . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($roadmap, 'Fase 14C: manual técnico y manual de usuario implementados')) {
    fwrite(STDERR, "[ERROR] ROADMAP no registra Fase 14C." . PHP_EOL);
    exit(1);
}

if (($app['version'] ?? '') !== '0.14.0-c-dev') {
    fwrite(STDERR, "[ERROR] La versión de aplicación no corresponde a Fase 14C." . PHP_EOL);
    exit(1);
}

echo "[OK] Fase 14C de manuales validada." . PHP_EOL;
