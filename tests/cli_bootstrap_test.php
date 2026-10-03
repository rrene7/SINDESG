<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERROR] Esta prueba debe ejecutarse por CLI." . PHP_EOL);
    exit(1);
}

set_error_handler(static function (
    int $severity,
    string $message,
    string $file,
    int $line
): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

echo "[TEST] salida previa a bootstrap" . PHP_EOL;

try {
    require dirname(__DIR__) . '/app/bootstrap.php';
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "[ERROR] Bootstrap CLI emitió advertencia/error: " . $exception->getMessage() . PHP_EOL
    );
    exit(1);
} finally {
    restore_error_handler();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    fwrite(STDERR, "[ERROR] No debe abrirse una sesión PHP durante CLI." . PHP_EOL);
    exit(1);
}

echo "[OK] Bootstrap CLI sin advertencias de sesión." . PHP_EOL;
