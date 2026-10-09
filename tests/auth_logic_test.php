<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/AuthService.php');
$controller = file_get_contents($root . '/app/Controllers/AuthController.php');
$bootstrap = file_get_contents($root . '/app/bootstrap.php');
$router = file_get_contents($root . '/public/index.php');

foreach (compact('service','controller','bootstrap','router') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'password_verify',
    'password_hash',
    'session_regenerate_id',
    'auth_last_activity',
    'security.session_idle_minutes',
    'sanitizePayload',
    'currentUser',
    'securityRepository(): SecurityRepository',
    'return $this->security();',
    'auditRepository(): AuditRepository',
    'return $this->audit();',
] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Autenticación incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['setupFirstAdmin', 'findEligibleEmployee', 'module=auth&action=setup', 'module=auth&action=login'] as $fragment) {
    if (!str_contains($controller, $fragment) && !str_contains($router, $fragment)) {
        fwrite(STDERR, "[ERROR] Flujo de acceso incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($bootstrap, "'httponly' => true") || !str_contains($bootstrap, "'samesite' => 'Lax'")) {
    fwrite(STDERR, "[ERROR] Faltan parámetros seguros de cookie de sesión." . PHP_EOL);
    exit(1);
}

echo "[OK] Autenticación y sesión validadas." . PHP_EOL;
