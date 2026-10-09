<?php
declare(strict_types=1);

final class BackupSupport
{
    public static function requireCli(): void
    {
        if (PHP_SAPI !== 'cli') {
            fwrite(STDERR, "Este script solo puede ejecutarse por CLI." . PHP_EOL);
            exit(1);
        }
    }

    public static function option(array $argv, string $name): ?string
    {
        $prefix = '--' . $name . '=';
        foreach ($argv as $argument) {
            if (str_starts_with((string) $argument, $prefix)) {
                return substr((string) $argument, strlen($prefix));
            }
        }

        return null;
    }

    public static function hasFlag(array $argv, string $flag): bool
    {
        return in_array('--' . $flag, $argv, true);
    }

    public static function findExecutable(string $name): ?string
    {
        $envName = 'SINDESG_' . strtoupper(str_replace('-', '_', $name));
        $fromEnv = getenv($envName);
        if (is_string($fromEnv) && $fromEnv !== '' && is_file($fromEnv)) {
            return $fromEnv;
        }

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $binaryName = $name . ($isWindows ? '.exe' : '');
        $phpDirectory = dirname(PHP_BINARY);

        $candidates = [
            dirname($phpDirectory) . '/mysql/bin/' . $binaryName,
            'C:/xampp/mysql/bin/' . $binaryName,
            '/usr/bin/' . $binaryName,
            '/usr/local/bin/' . $binaryName,
        ];

        foreach ($candidates as $candidate) {
            $candidate = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $locator = $isWindows ? ['where.exe', $name] : ['sh', '-lc', 'command -v ' . escapeshellarg($name)];
        try {
            $result = self::run($locator);
            if ($result['exit_code'] === 0) {
                $line = trim(strtok($result['stdout'], "\r\n") ?: '');
                if ($line !== '' && is_file($line)) {
                    return $line;
                }
            }
        } catch (Throwable) {
            // La ausencia se reporta al llamador.
        }

        return null;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public static function run(
        array $command,
        ?string $stdinPath = null,
        ?string $stdoutPath = null
    ): array {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open está deshabilitado en PHP.');
        }

        $descriptors = [
            0 => $stdinPath !== null
                ? ['file', $stdinPath, 'rb']
                : ['pipe', 'r'],
            1 => $stdoutPath !== null
                ? ['file', $stdoutPath, 'wb']
                : ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('No fue posible iniciar el proceso requerido.');
        }

        if ($stdinPath === null && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $stdout = '';
        if ($stdoutPath === null && isset($pipes[1]) && is_resource($pipes[1])) {
            $stdout = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }

        $stderr = '';
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);

        return [
            'exit_code' => (int) $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    public static function databaseConfig(string $root): array
    {
        $path = $root . '/config/database.php';
        if (!is_file($path)) {
            throw new RuntimeException(
                'No existe config/database.php. Configure credenciales locales antes de respaldo/restauración.'
            );
        }

        $config = require $path;
        if (!is_array($config) || !isset($config['sindesg']) || !is_array($config['sindesg'])) {
            throw new RuntimeException('config/database.php no contiene la conexión sindesg.');
        }

        $database = trim((string) ($config['sindesg']['database'] ?? ''));
        if ($database !== 'sindesg') {
            throw new RuntimeException(
                'Por seguridad, respaldo/restauración solo opera sobre la base sindesg.'
            );
        }

        return $config;
    }

    public static function createDefaultsFile(array $dbConfig): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sindesg_mysql_');
        if ($path === false) {
            throw new RuntimeException('No fue posible crear archivo temporal de credenciales.');
        }

        $quote = static function (mixed $value): string {
            $value = (string) $value;
            $value = str_replace('\\', '\\\\', $value);
            $value = str_replace('"', '\\"', $value);
            return '"' . $value . '"';
        };

        $contents = implode(PHP_EOL, [
            '[client]',
            'host=' . $quote($dbConfig['host'] ?? '127.0.0.1'),
            'port=' . (int) ($dbConfig['port'] ?? 3306),
            'user=' . $quote($dbConfig['username'] ?? ''),
            'password=' . $quote($dbConfig['password'] ?? ''),
            'default-character-set=' . $quote($dbConfig['charset'] ?? 'utf8mb4'),
            '',
        ]);

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            @unlink($path);
            throw new RuntimeException('No fue posible escribir archivo temporal de credenciales.');
        }

        @chmod($path, 0600);
        return $path;
    }

    public static function safeLabel(?string $label): string
    {
        $label = trim((string) $label);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/[^A-Za-z0-9_-]+/', '-', $label) ?? '';
        return trim($label, '-_');
    }

    public static function recursiveFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);
        return $files;
    }

    public static function relativePath(string $path, string $base): string
    {
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $path = str_replace('\\', '/', $path);

        if (!str_starts_with($path, $base)) {
            throw new RuntimeException('La ruta no pertenece al directorio esperado.');
        }

        return ltrim(substr($path, strlen($base)), '/');
    }

    public static function deleteTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                if (!@rmdir($path)) {
                    throw new RuntimeException('No se pudo eliminar directorio: ' . $path);
                }
            } else {
                if (!@unlink($path)) {
                    throw new RuntimeException('No se pudo eliminar archivo: ' . $path);
                }
            }
        }

        if (!@rmdir($directory)) {
            throw new RuntimeException('No se pudo eliminar directorio: ' . $directory);
        }
    }

    public static function manifest(string $sourceDirectory): array
    {
        $manifestPath = rtrim($sourceDirectory, '/\\') . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('El respaldo no contiene manifest.json.');
        }

        $raw = file_get_contents($manifestPath);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($manifest)) {
            throw new RuntimeException('manifest.json no contiene JSON válido.');
        }

        if (($manifest['backup_format_version'] ?? null) !== 1) {
            throw new RuntimeException('Versión de formato de respaldo no soportada.');
        }

        if (($manifest['database']['name'] ?? null) !== 'sindesg') {
            throw new RuntimeException('El respaldo no pertenece a la base sindesg.');
        }

        $completeMarker = rtrim($sourceDirectory, '/\\') . '/BACKUP_COMPLETE';
        if (!is_file($completeMarker)) {
            throw new RuntimeException('El respaldo no tiene marcador BACKUP_COMPLETE.');
        }

        foreach (['database_dump', 'uploads_archive'] as $key) {
            $entry = $manifest['files'][$key] ?? null;
            if (!is_array($entry)) {
                throw new RuntimeException('El manifiesto no contiene ' . $key . '.');
            }

            $filename = basename((string) ($entry['filename'] ?? ''));
            $expectedHash = strtolower((string) ($entry['sha256'] ?? ''));
            $path = rtrim($sourceDirectory, '/\\') . '/' . $filename;

            if ($filename === '' || !is_file($path)) {
                throw new RuntimeException('Falta archivo del respaldo: ' . $filename);
            }

            $actualHash = strtolower((string) hash_file('sha256', $path));
            if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
                throw new RuntimeException('SHA-256 no coincide para ' . $filename . '.');
            }
        }

        return $manifest;
    }
}
