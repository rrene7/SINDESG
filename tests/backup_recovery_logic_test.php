<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$support = file_get_contents($root . '/scripts/lib/BackupSupport.php');
$backup = file_get_contents($root . '/scripts/backup.php');
$restore = file_get_contents($root . '/scripts/restore.php');
$readiness = file_get_contents($root . '/scripts/readiness.php');
$gitignore = file_get_contents($root . '/.gitignore');
$app = require $root . '/config/app.php';

foreach (compact('support','backup','restore','readiness','gitignore') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para Fase 14B." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'findExecutable',
    'createDefaultsFile',
    'manifest',
    'hash_file',
    'BACKUP_COMPLETE',
] as $fragment) {
    if (!str_contains($support, $fragment)) {
        fwrite(STDERR, "[ERROR] BackupSupport incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'mysqldump',
    '--single-transaction',
    '--routines',
    '--triggers',
    '--events',
    '--add-drop-table',
    'uploads.zip',
    'manifest.json',
    'backup_format_version',
    "'rrhh_included' => false",
    'BACKUP_PATH=',
] as $fragment) {
    if (!str_contains($backup, $fragment)) {
        fwrite(STDERR, "[ERROR] Respaldo 14B incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    '--confirm=RESTORE-SINDESG',
    'verify-only',
    '--skip-safety-backup',
    '--label=pre_restore',
    '--one-database',
    'sindesg',
    'rrhh2029',
    'uploads_restore_',
    'readiness.php',
] as $fragment) {
    if (!str_contains($restore, $fragment)) {
        fwrite(STDERR, "[ERROR] Restauración 14B incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($restore, "stripos($haystack, 'rrhh2029')")) {
    fwrite(STDERR, "[ERROR] La restauración no bloquea dumps que mencionen RRHH2029." . PHP_EOL);
    exit(1);
}

foreach ([
    'storage/backups',
    'storage/uploads_restore_*',
    'storage/uploads_before_restore_*',
] as $fragment) {
    if (!str_contains($gitignore, $fragment)) {
        fwrite(STDERR, "[ERROR] .gitignore no protege {$fragment}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "'storage/backups'",
    "findExecutable('mysqldump')",
    "findExecutable('mysql')",
] as $fragment) {
    if (!str_contains($readiness, $fragment)) {
        fwrite(STDERR, "[ERROR] readiness.php no valida {$fragment}." . PHP_EOL);
        exit(1);
    }
}

if (($app['version'] ?? '') !== '0.14.0-b-dev') {
    fwrite(STDERR, "[ERROR] La versión de aplicación no corresponde a Fase 14B." . PHP_EOL);
    exit(1);
}

echo "[OK] Fase 14B de respaldo y recuperación validada." . PHP_EOL;
