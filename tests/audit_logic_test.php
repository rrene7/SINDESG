<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/AuditRepository.php');
$service = file_get_contents($root . '/app/Services/AuthService.php');
$admin = file_get_contents($root . '/app/Controllers/AdminController.php');

foreach (compact('repository','service','admin') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach (['before_json', 'after_json', 'details_json', 'module_code', 'action_code', 'record_id', 'ip_address', 'user_agent'] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Auditoría incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (["'_token'", "'password'", "'password_confirmation'", "'new_password'"] as $fragment) {
    if (!str_contains($service, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta excluir dato sensible: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['user_created', 'user_updated', 'password_reset', 'settings_updated', 'role_permissions_updated'] as $fragment) {
    if (!str_contains($admin, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta auditoría administrativa: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Auditoría de acciones validada." . PHP_EOL;
