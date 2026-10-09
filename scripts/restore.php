<?php
declare(strict_types=1);

require __DIR__ . '/lib/BackupSupport.php';
BackupSupport::requireCli();

$root = dirname(__DIR__);
$validateOnly = BackupSupport::hasFlag($argv, 'validate-only');

if ($validateOnly) {
    foreach (['proc_open', 'hash_file', 'json_decode', 'rename'] as $function) {
        if (!function_exists($function)) {
            fwrite(STDERR, "[ERROR] Función PHP requerida no disponible: {$function}" . PHP_EOL);
            exit(1);
        }
    }

    if (!class_exists(ZipArchive::class)) {
        fwrite(STDERR, "[ERROR] La extensión ZIP de PHP es obligatoria para restauración." . PHP_EOL);
        exit(1);
    }

    echo "[OK] Script de restauración SINDESG validado." . PHP_EOL;
    exit(0);
}

$source = BackupSupport::option($argv, 'source');
if ($source === null || trim($source) === '') {
    fwrite(
        STDERR,
        "Uso: php scripts/restore.php --source=<carpeta_respaldo> --confirm=RESTORE-SINDESG" . PHP_EOL
    );
    exit(1);
}

$source = rtrim($source, '/\\');
if (!is_dir($source)) {
    throw new RuntimeException('No existe la carpeta de respaldo: ' . $source);
}

$manifest = BackupSupport::manifest($source);

echo "[OK] Integridad del paquete verificada por SHA-256." . PHP_EOL;
echo "[OK] Base declarada: sindesg." . PHP_EOL;
echo "[OK] RRHH2029 no forma parte del paquete." . PHP_EOL;

if (BackupSupport::hasFlag($argv, 'verify-only')) {
    echo "[OK] Verificación terminada; no se restauró ningún dato." . PHP_EOL;
    exit(0);
}

$confirmation = BackupSupport::option($argv, 'confirm');
if ($confirmation !== 'RESTORE-SINDESG') {
    fwrite(
        STDERR,
        "[ERROR] Restauración bloqueada. Use --confirm=RESTORE-SINDESG para autorizarla explícitamente." . PHP_EOL
    );
    exit(1);
}

$config = BackupSupport::databaseConfig($root);
$dbConfig = $config['sindesg'];

$mysql = BackupSupport::findExecutable('mysql');
if ($mysql === null) {
    throw new RuntimeException(
        'No se encontró mysql. En XAMPP se espera C:\\xampp\\mysql\\bin\\mysql.exe.'
    );
}

$dumpEntry = $manifest['files']['database_dump'];
$uploadsEntry = $manifest['files']['uploads_archive'];
$dumpPath = $source . '/' . basename((string) $dumpEntry['filename']);
$uploadsZipPath = $source . '/' . basename((string) $uploadsEntry['filename']);

$dumpPreview = file_get_contents($dumpPath, false, null, 0, min(1024 * 1024, (int) filesize($dumpPath)));
if (!is_string($dumpPreview) || stripos($dumpPreview, 'sindesg') === false) {
    throw new RuntimeException('El dump SQL no identifica la base sindesg.');
}

if (stripos($dumpPreview, 'rrhh2029') !== false) {
    throw new RuntimeException(
        'Restauración bloqueada: el dump menciona rrhh2029.'
    );
}

if (!BackupSupport::hasFlag($argv, 'skip-safety-backup')) {
    echo "[INFO] Creando respaldo de seguridad previo a restaurar..." . PHP_EOL;
    $safety = BackupSupport::run([
        PHP_BINARY,
        __DIR__ . '/backup.php',
        '--label=pre_restore',
    ]);

    if ($safety['exit_code'] !== 0) {
        throw new RuntimeException(
            'No se pudo crear respaldo de seguridad previo. Restauración cancelada. '
            . trim($safety['stderr'])
        );
    }

    echo $safety['stdout'];
} else {
    echo "[WARN] Se omitió el respaldo de seguridad por --skip-safety-backup." . PHP_EOL;
}

$zip = new ZipArchive();
if ($zip->open($uploadsZipPath) !== true) {
    throw new RuntimeException('No fue posible abrir uploads.zip.');
}

$restoreStaging = $root . '/storage/uploads_restore_' . bin2hex(random_bytes(6));

if (!mkdir($restoreStaging, 0770, true) && !is_dir($restoreStaging)) {
    $zip->close();
    throw new RuntimeException('No fue posible crear staging temporal de uploads.');
}

try {
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $normalized = str_replace('\\', '/', $name);

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || in_array('..', explode('/', $normalized), true)
        ) {
            throw new RuntimeException(
                'uploads.zip contiene una ruta insegura: ' . $name
            );
        }
    }

    if (!$zip->extractTo($restoreStaging)) {
        throw new RuntimeException('No fue posible extraer uploads.zip.');
    }
} catch (Throwable $exception) {
    $zip->close();
    BackupSupport::deleteTree($restoreStaging);
    throw $exception;
}

$zip->close();

$defaultsFile = BackupSupport::createDefaultsFile($dbConfig);

try {
    echo "[INFO] Restaurando base sindesg..." . PHP_EOL;
    $result = BackupSupport::run(
        [
            $mysql,
            '--defaults-extra-file=' . $defaultsFile,
            '--default-character-set=utf8mb4',
            '--one-database',
            'sindesg',
        ],
        $dumpPath
    );

    if ($result['exit_code'] !== 0) {
        BackupSupport::deleteTree($restoreStaging);
        throw new RuntimeException(
            'mysql falló durante la restauración: ' . trim($result['stderr'])
        );
    }
} finally {
    @unlink($defaultsFile);
}

$uploadsDirectory = $root . '/storage/uploads';
$previousUploads = $root . '/storage/uploads_before_restore_'
    . (new DateTimeImmutable('now', new DateTimeZone('America/Panama')))->format('Ymd_His');

if (!is_dir($uploadsDirectory)) {
    if (!mkdir($uploadsDirectory, 0770, true) && !is_dir($uploadsDirectory)) {
        BackupSupport::deleteTree($restoreStaging);
        throw new RuntimeException('No fue posible preparar storage/uploads.');
    }
}

if (!rename($uploadsDirectory, $previousUploads)) {
    BackupSupport::deleteTree($restoreStaging);
    throw new RuntimeException(
        'La base fue restaurada, pero no se pudo apartar storage/uploads. '
        . 'Use el respaldo pre_restore antes de reintentar.'
    );
}

if (!rename($restoreStaging, $uploadsDirectory)) {
    @rename($previousUploads, $uploadsDirectory);
    BackupSupport::deleteTree($restoreStaging);
    throw new RuntimeException(
        'La base fue restaurada, pero falló el intercambio de uploads. '
        . 'Se intentó devolver los archivos anteriores.'
    );
}

try {
    BackupSupport::deleteTree($previousUploads);
} catch (Throwable $exception) {
    echo "[WARN] La restauración terminó, pero quedó una copia temporal de uploads: "
        . $previousUploads . PHP_EOL;
}

echo "[OK] Base sindesg restaurada." . PHP_EOL;
echo "[OK] storage/uploads restaurado." . PHP_EOL;
echo "[OK] RRHH2029 no fue restaurado ni modificado." . PHP_EOL;

$readiness = BackupSupport::run([
    PHP_BINARY,
    __DIR__ . '/readiness.php',
]);

if ($readiness['exit_code'] !== 0) {
    echo $readiness['stdout'];
    fwrite(STDERR, $readiness['stderr']);
    throw new RuntimeException(
        'La restauración terminó, pero readiness.php reportó un bloqueo.'
    );
}

echo $readiness['stdout'];
echo "[OK] Restauración SINDESG finalizada y verificada." . PHP_EOL;
