<?php
declare(strict_types=1);

require __DIR__ . '/lib/BackupSupport.php';
BackupSupport::requireCli();

$root = dirname(__DIR__);
$validateOnly = BackupSupport::hasFlag($argv, 'validate-only');

if ($validateOnly) {
    foreach (['proc_open', 'hash_file', 'json_encode'] as $function) {
        if (!function_exists($function)) {
            fwrite(STDERR, "[ERROR] Función PHP requerida no disponible: {$function}" . PHP_EOL);
            exit(1);
        }
    }

    if (!class_exists(ZipArchive::class)) {
        fwrite(STDERR, "[ERROR] La extensión ZIP de PHP es obligatoria para respaldos." . PHP_EOL);
        exit(1);
    }

    echo "[OK] Script de respaldo SINDESG validado." . PHP_EOL;
    exit(0);
}

$config = BackupSupport::databaseConfig($root);
$dbConfig = $config['sindesg'];

if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('La extensión ZIP de PHP no está habilitada.');
}

$mysqldump = BackupSupport::findExecutable('mysqldump');
if ($mysqldump === null) {
    throw new RuntimeException(
        'No se encontró mysqldump. En XAMPP se espera C:\\xampp\\mysql\\bin\\mysqldump.exe.'
    );
}

$app = require $root . '/config/app.php';
$destinationOption = BackupSupport::option($argv, 'destination');
$label = BackupSupport::safeLabel(BackupSupport::option($argv, 'label'));

$backupRoot = $destinationOption !== null && trim($destinationOption) !== ''
    ? rtrim($destinationOption, '/\\')
    : $root . '/storage/backups';

if (!is_dir($backupRoot) && !mkdir($backupRoot, 0770, true) && !is_dir($backupRoot)) {
    throw new RuntimeException('No fue posible crear el directorio de respaldos.');
}

$timestamp = (new DateTimeImmutable('now', new DateTimeZone('America/Panama')))
    ->format('Ymd_His');
$directoryName = 'sindesg_' . $timestamp . ($label !== '' ? '_' . $label : '');
$backupDirectory = $backupRoot . DIRECTORY_SEPARATOR . $directoryName;

if (file_exists($backupDirectory)) {
    throw new RuntimeException('El directorio de respaldo ya existe: ' . $backupDirectory);
}

if (!mkdir($backupDirectory, 0770, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('No fue posible crear el paquete de respaldo.');
}

$dumpPath = $backupDirectory . '/sindesg.sql';
$uploadsZipPath = $backupDirectory . '/uploads.zip';
$manifestPath = $backupDirectory . '/manifest.json';
$completeMarkerPath = $backupDirectory . '/BACKUP_COMPLETE';

$defaultsFile = BackupSupport::createDefaultsFile($dbConfig);

try {
    $command = [
        $mysqldump,
        '--defaults-extra-file=' . $defaultsFile,
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        '--add-drop-table',
        '--skip-lock-tables',
        '--no-tablespaces',
        '--databases',
        'sindesg',
    ];

    $result = BackupSupport::run($command, null, $dumpPath);
    if ($result['exit_code'] !== 0) {
        throw new RuntimeException(
            'mysqldump falló: ' . trim($result['stderr'])
        );
    }
} finally {
    @unlink($defaultsFile);
}

if (!is_file($dumpPath) || filesize($dumpPath) < 100) {
    throw new RuntimeException('El dump SQL quedó vacío o incompleto.');
}

$uploadsDirectory = $root . '/storage/uploads';
if (!is_dir($uploadsDirectory)) {
    throw new RuntimeException('No existe storage/uploads.');
}

$zip = new ZipArchive();
if ($zip->open($uploadsZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('No fue posible crear uploads.zip.');
}

$uploadFiles = BackupSupport::recursiveFiles($uploadsDirectory);
$uploadBytes = 0;

try {
    foreach ($uploadFiles as $file) {
        $relative = BackupSupport::relativePath($file, $uploadsDirectory);
        if (!$zip->addFile($file, $relative)) {
            throw new RuntimeException('No se pudo agregar al ZIP: ' . $relative);
        }

        $size = filesize($file);
        $uploadBytes += $size !== false ? (int) $size : 0;
    }

    $zip->setArchiveComment('SINDESG uploads backup');
} finally {
    $zip->close();
}

if (!is_file($uploadsZipPath)) {
    throw new RuntimeException('No se creó uploads.zip.');
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=sindesg;charset=%s',
    (string) ($dbConfig['host'] ?? '127.0.0.1'),
    (int) ($dbConfig['port'] ?? 3306),
    (string) ($dbConfig['charset'] ?? 'utf8mb4')
);
$pdo = new PDO(
    $dsn,
    (string) ($dbConfig['username'] ?? ''),
    (string) ($dbConfig['password'] ?? ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$schemaVersion = (string) (
    $pdo->query(
        "SELECT setting_value
         FROM system_settings
         WHERE setting_key = 'schema_version'
         LIMIT 1"
    )->fetchColumn() ?: ''
);

$tableCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_type = 'BASE TABLE'"
)->fetchColumn();

$gitCommit = null;
$git = BackupSupport::findExecutable('git');
if ($git !== null) {
    $gitResult = BackupSupport::run([
        $git,
        '-C',
        $root,
        'rev-parse',
        'HEAD',
    ]);
    if ($gitResult['exit_code'] === 0) {
        $candidate = trim($gitResult['stdout']);
        if (preg_match('/^[a-f0-9]{40}$/i', $candidate) === 1) {
            $gitCommit = strtolower($candidate);
        }
    }
}

$dumpHash = hash_file('sha256', $dumpPath);
$uploadsHash = hash_file('sha256', $uploadsZipPath);

if (!is_string($dumpHash) || !is_string($uploadsHash)) {
    throw new RuntimeException('No fue posible calcular SHA-256 del respaldo.');
}

$manifest = [
    'backup_format_version' => 1,
    'application' => [
        'name' => (string) ($app['name'] ?? 'SINDESG'),
        'version' => (string) ($app['version'] ?? ''),
        'git_commit' => $gitCommit,
    ],
    'created_at' => (new DateTimeImmutable('now', new DateTimeZone('America/Panama')))
        ->format(DateTimeInterface::ATOM),
    'database' => [
        'name' => 'sindesg',
        'schema_version' => $schemaVersion,
        'table_count' => $tableCount,
        'rrhh_included' => false,
    ],
    'uploads' => [
        'file_count' => count($uploadFiles),
        'uncompressed_bytes' => $uploadBytes,
    ],
    'files' => [
        'database_dump' => [
            'filename' => basename($dumpPath),
            'bytes' => filesize($dumpPath),
            'sha256' => $dumpHash,
        ],
        'uploads_archive' => [
            'filename' => basename($uploadsZipPath),
            'bytes' => filesize($uploadsZipPath),
            'sha256' => $uploadsHash,
        ],
    ],
];

$manifestJson = json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if (!is_string($manifestJson) || file_put_contents($manifestPath, $manifestJson . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('No fue posible escribir manifest.json.');
}

$marker = sprintf(
    "SINDESG BACKUP COMPLETE%screated_at=%s%sdatabase=sindesg%sschema_version=%s%s",
    PHP_EOL,
    $manifest['created_at'],
    PHP_EOL,
    PHP_EOL,
    $schemaVersion,
    PHP_EOL
);

if (file_put_contents($completeMarkerPath, $marker, LOCK_EX) === false) {
    throw new RuntimeException('No fue posible crear BACKUP_COMPLETE.');
}

echo "[OK] Base sindesg respaldada." . PHP_EOL;
echo "[OK] Uploads respaldados: " . count($uploadFiles) . " archivos." . PHP_EOL;
echo "[OK] SHA-256 registrado para dump y uploads." . PHP_EOL;
echo "[OK] RRHH2029 no fue incluido ni modificado." . PHP_EOL;
echo "BACKUP_PATH=" . $backupDirectory . PHP_EOL;
