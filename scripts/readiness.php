<?php
declare(strict_types=1);

/**
 * SINDESG - Fase 14A
 * Verificación integral previa a despliegue institucional.
 *
 * Uso:
 *   php scripts/readiness.php
 *   php scripts/readiness.php --strict
 *   php scripts/readiness.php --no-database
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este verificador solo puede ejecutarse por CLI." . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/scripts/lib/BackupSupport.php';

$strict = in_array('--strict', $argv, true);
$skipDatabase = in_array('--no-database', $argv, true);

$results = [];
$add = static function (string $level, string $label, string $message) use (&$results): void {
    $results[] = [
        'level' => $level,
        'label' => $label,
        'message' => $message,
    ];
};

$ok = static function (string $label, string $message) use ($add): void {
    $add('OK', $label, $message);
};
$warn = static function (string $label, string $message) use ($add): void {
    $add('WARN', $label, $message);
};
$error = static function (string $label, string $message) use ($add): void {
    $add('ERROR', $label, $message);
};

echo "SINDESG - Fase 14A | Verificación de preparación" . PHP_EOL;
echo str_repeat('=', 58) . PHP_EOL;

if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    $ok('PHP', PHP_VERSION);
} else {
    $error('PHP', 'Se requiere PHP 8.2 o superior. Detectado: ' . PHP_VERSION);
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'dom', 'fileinfo', 'zip'];
foreach ($requiredExtensions as $extension) {
    if (extension_loaded($extension)) {
        $ok('Extensión ' . $extension, 'Disponible');
    } else {
        $error('Extensión ' . $extension, 'No está habilitada');
    }
}

$appFile = $root . '/config/app.php';
$app = is_file($appFile) ? require $appFile : [];

if (!is_array($app)) {
    $error('Configuración', 'config/app.php no devuelve una configuración válida');
    $app = [];
} else {
    $ok('Aplicación', (string) ($app['name'] ?? 'SINDESG') . ' ' . (string) ($app['version'] ?? 'sin versión'));
}

$environment = strtolower(trim((string) ($app['environment'] ?? '')));
if ($environment === 'production') {
    $ok('Entorno', 'production');

    if (is_file($root . '/config/local.php')) {
        $ok('Configuración local', 'config/local.php presente y fuera de Git');
    } else {
        $error(
            'Configuración local',
            'Producción requiere config/local.php para separar parámetros del código versionado'
        );
    }

    $baseUrl = trim((string) ($app['base_url'] ?? ''));
    if ($baseUrl !== '' && str_starts_with($baseUrl, '/')) {
        $ok('Base URL', $baseUrl);
    } else {
        $error('Base URL', 'Debe definirse como ruta absoluta del sitio, por ejemplo /SINDESG');
    }

    if (($app['session_secure'] ?? false) === true) {
        $ok('Cookie de sesión', 'secure habilitado');
    } else {
        $warn(
            'Cookie de sesión',
            'session_secure está deshabilitado; producción HTTPS debe habilitarlo'
        );
    }
} elseif ($environment === 'local') {
    $warn('Entorno', 'local; antes del despliegue institucional debe cambiarse a production');
} else {
    $warn('Entorno', $environment !== '' ? $environment : 'no definido');
}

$timezone = (string) ($app['timezone'] ?? '');
if ($timezone === 'America/Panama') {
    $ok('Zona horaria', $timezone);
} else {
    $warn('Zona horaria', $timezone !== '' ? $timezone : 'no definida');
}

foreach ([
    'storage/uploads',
    'storage/logs',
    'storage/cache',
    'storage/backups',
] as $relativeDirectory) {
    $directory = $root . '/' . $relativeDirectory;

    if (!is_dir($directory)) {
        $error('Directorio ' . $relativeDirectory, 'No existe');
        continue;
    }

    if (!is_writable($directory)) {
        $error('Directorio ' . $relativeDirectory, 'Existe pero no tiene permiso de escritura');
        continue;
    }

    $ok('Directorio ' . $relativeDirectory, 'Disponible y escribible');
}

$gitignore = @file_get_contents($root . '/.gitignore');
if (
    is_string($gitignore)
    && preg_match('/^config\/database\.php\s*$/m', $gitignore) === 1
) {
    $ok('Secretos locales', 'config/database.php está excluido de Git');
} else {
    $error('Secretos locales', 'config/database.php debe estar excluido de Git');
}

if (function_exists('proc_open')) {
    $ok('Procesos externos', 'proc_open disponible para respaldo/restauración');
} else {
    $error('Procesos externos', 'proc_open está deshabilitado');
}

$databaseConfigPath = $root . '/config/database.php';
if (is_file($databaseConfigPath)) {
    $ok('Configuración DB', 'config/database.php presente');
} else {
    $warn(
        'Configuración DB',
        'No existe config/database.php; se está dependiendo de database.example.php'
    );
}

$schemaDirectory = $root . '/database/schema';
$schemaFiles = glob($schemaDirectory . '/*.sql') ?: [];
$schemaNumbers = [];

foreach ($schemaFiles as $file) {
    $basename = basename($file);
    if (preg_match('/^(\d{3})_/', $basename, $matches) === 1) {
        $schemaNumbers[(int) $matches[1]] = $basename;
    }
}

for ($number = 1; $number <= 18; $number++) {
    if (!isset($schemaNumbers[$number])) {
        $error(
            'Migración ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'No existe archivo SQL versionado'
        );
    }
}

if (count(array_intersect(range(1, 18), array_keys($schemaNumbers))) === 18) {
    $ok('Migraciones versionadas', '001–018 presentes en el repositorio');
}

if (!$skipDatabase) {
    $mysqldump = BackupSupport::findExecutable('mysqldump');
    $mysqlClient = BackupSupport::findExecutable('mysql');

    if ($mysqldump !== null) {
        $ok('Herramienta mysqldump', $mysqldump);
    } else {
        $error('Herramienta mysqldump', 'No encontrada');
    }

    if ($mysqlClient !== null) {
        $ok('Herramienta mysql', $mysqlClient);
    } else {
        $error('Herramienta mysql', 'No encontrada');
    }

    try {
        require $root . '/app/bootstrap.php';

        foreach (['sindesg', 'rrhh'] as $connectionName) {
            $health = $database->health($connectionName);
            if ($health['ok']) {
                $ok('Base ' . strtoupper($connectionName), 'Conectada');
            } else {
                $error('Base ' . strtoupper($connectionName), 'Sin conexión');
            }
        }

        if ($database->health('sindesg')['ok']) {
            $pdo = $database->connection('sindesg');

            $migrationRows = $pdo
                ->query('SELECT migration FROM schema_migrations ORDER BY id')
                ->fetchAll(PDO::FETCH_COLUMN);
            $appliedNumbers = [];

            foreach ($migrationRows as $migration) {
                if (preg_match('/^(\d{3})_/', (string) $migration, $matches) === 1) {
                    $appliedNumbers[(int) $matches[1]] = true;
                }
            }

            $missingApplied = [];
            for ($number = 1; $number <= 18; $number++) {
                if (!isset($appliedNumbers[$number])) {
                    $missingApplied[] = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                }
            }

            if ($missingApplied === []) {
                $ok('Migraciones DB', '001–018 registradas en schema_migrations');
            } else {
                $error(
                    'Migraciones DB',
                    'Faltan en schema_migrations: ' . implode(', ', $missingApplied)
                );
            }

            $versionStatement = $pdo->prepare(
                'SELECT setting_value
                 FROM system_settings
                 WHERE setting_key = "schema_version"
                 LIMIT 1'
            );
            $versionStatement->execute();
            $schemaVersion = trim((string) ($versionStatement->fetchColumn() ?: ''));

            if ($schemaVersion === '0.13.0-c') {
                $ok('Versión esquema', $schemaVersion);
            } else {
                $warn(
                    'Versión esquema',
                    $schemaVersion !== '' ? $schemaVersion : 'no registrada'
                );
            }

            $schemaInfo = $pdo->query(
                'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                 FROM information_schema.SCHEMATA
                 WHERE SCHEMA_NAME = DATABASE()'
            )->fetch();

            $charset = strtolower((string) ($schemaInfo['DEFAULT_CHARACTER_SET_NAME'] ?? ''));
            if ($charset === 'utf8mb4') {
                $ok(
                    'Charset SINDESG',
                    $charset . ' / ' . (string) ($schemaInfo['DEFAULT_COLLATION_NAME'] ?? '')
                );
            } else {
                $error('Charset SINDESG', $charset !== '' ? $charset : 'no detectado');
            }
        }

        $rrhhConfig = $databaseConfig['rrhh'] ?? [];
        if (($rrhhConfig['read_only'] ?? false) === true) {
            $ok('Contrato RRHH', 'Configurado como read_only');
        } else {
            $error('Contrato RRHH', 'La conexión RRHH debe declarar read_only => true');
        }

        if ($database->health('rrhh')['ok']) {
            $rrhhPdo = $database->connection('rrhh');
            $grants = $rrhhPdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
            $grantText = strtoupper(implode("\n", array_map('strval', $grants)));

            $writePrivilegePattern = '/\b(ALL PRIVILEGES|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRIGGER|GRANT OPTION)\b/';
            if (preg_match($writePrivilegePattern, $grantText) === 1) {
                $warn(
                    'Privilegios RRHH',
                    'El usuario DB parece tener permisos de escritura; producción debe usar una cuenta SELECT-only'
                );
            } else {
                $ok('Privilegios RRHH', 'No se detectaron privilegios de escritura');
            }

            try {
                $database->rrhhRead('SELECT 1 AS readiness_check');
                $ok('Contrato de lectura RRHH', 'rrhhRead() operativo');
            } catch (Throwable $exception) {
                $error('Contrato de lectura RRHH', 'No fue posible ejecutar consulta de lectura');
            }
        }
    } catch (Throwable $exception) {
        $error('Base de datos', 'Falló la validación integral: ' . $exception->getMessage());
    }
} else {
    $warn('Base de datos', 'Validación omitida por --no-database');
}

$counts = ['OK' => 0, 'WARN' => 0, 'ERROR' => 0];

foreach ($results as $result) {
    $counts[$result['level']]++;
    printf(
        "[%s] %-28s %s%s",
        $result['level'],
        $result['label'] . ':',
        $result['message'],
        PHP_EOL
    );
}

echo str_repeat('-', 58) . PHP_EOL;
printf(
    "Resultado: %d OK · %d WARN · %d ERROR%s",
    $counts['OK'],
    $counts['WARN'],
    $counts['ERROR'],
    PHP_EOL
);

$failed = $counts['ERROR'] > 0 || ($strict && $counts['WARN'] > 0);

if ($failed) {
    echo $strict && $counts['WARN'] > 0 && $counts['ERROR'] === 0
        ? "NO LISTO: --strict considera las advertencias como bloqueantes." . PHP_EOL
        : "NO LISTO: corrija los errores antes de desplegar." . PHP_EOL;
    exit(1);
}

echo $counts['WARN'] > 0
    ? "APTO PARA DESARROLLO: revise advertencias antes de producción." . PHP_EOL
    : "LISTO: no se detectaron bloqueos de preparación." . PHP_EOL;
