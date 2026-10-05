<?php
declare(strict_types=1);

$bootstrap = file_get_contents(dirname(__DIR__) . '/app/bootstrap.php');

if ($bootstrap === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer bootstrap.php" . PHP_EOL);
    exit(1);
}

foreach (['filemtime', "'?v='", 'rawurlencode'] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta cache-busting de assets: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Cache-busting de CSS/JS validado." . PHP_EOL;
