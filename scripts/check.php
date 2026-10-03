<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'index.php',
    'public/index.php',
    'public/assets/css/app.css',
    'public/assets/js/app.js',
    'app/bootstrap.php',
    'app/Support/Database.php',
    'app/Views/layout.php',
    'app/Views/dashboard.php',
    'app/Views/module.php',
    'config/app.php',
    'config/database.example.php',
    'database/schema/001_bootstrap.sql',
];

$errors = [];

foreach ($required as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Falta archivo requerido: {$file}";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[ERROR] {$error}" . PHP_EOL);
    }
    exit(1);
}

echo "[OK] Estructura base completa." . PHP_EOL;

if (in_array('--database', $argv, true)) {
    require $root . '/app/bootstrap.php';

    foreach (['sindesg', 'rrhh'] as $connection) {
        $status = $database->health($connection);
        echo sprintf(
            "[%s] %s: %s%s",
            $status['ok'] ? 'OK' : 'WARN',
            strtoupper($connection),
            $status['message'],
            PHP_EOL
        );
    }
}

echo "[OK] Verificación Fase 1 finalizada." . PHP_EOL;
