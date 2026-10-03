<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/RrhhRepository.php');
$directory = file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');
$database = file_get_contents($root . '/app/Support/Database.php');

foreach (compact('repository', 'directory', 'database') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([$repository, $directory] as $content) {
    foreach (['INSERT INTO', 'UPDATE ', 'DELETE FROM', 'REPLACE INTO', 'TRUNCATE ', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
        if (stripos($content, $forbidden) !== false) {
            fwrite(STDERR, "[ERROR] Se detectó escritura hacia RRHH: {$forbidden}" . PHP_EOL);
            exit(1);
        }
    }

    if (!str_contains($content, 'rrhhRead')) {
        fwrite(STDERR, "[ERROR] La consulta RRHH no usa rrhhRead." . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($database, "SELECT|WITH|SHOW|DESCRIBE|EXPLAIN")) {
    fwrite(STDERR, "[ERROR] Falta la barrera de solo lectura en Database::rrhhRead." . PHP_EOL);
    exit(1);
}

echo "[OK] Contrato de solo lectura RRHH validado." . PHP_EOL;
