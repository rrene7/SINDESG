<?php
declare(strict_types=1);

use App\Support\Database;

define('SINDESG_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = SINDESG_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$app = require SINDESG_ROOT . '/config/app.php';

$databaseConfigFile = SINDESG_ROOT . '/config/database.php';
if (!is_file($databaseConfigFile)) {
    $databaseConfigFile = SINDESG_ROOT . '/config/database.example.php';
}

$databaseConfig = require $databaseConfigFile;
$database = new Database($databaseConfig);

function sindesg_config(string $key, mixed $default = null): mixed
{
    global $app;

    return $app[$key] ?? $default;
}

function sindesg_url(string $path = ''): string
{
    $base = rtrim((string) sindesg_config('base_url', '/SINDESG'), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function sindesg_asset(string $path): string
{
    return sindesg_url('public/assets/' . ltrim($path, '/'));
}

function sindesg_upload_url(string $path): string
{
    return sindesg_url('storage/uploads/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_valid(?string $token): bool
{
    $stored = $_SESSION['csrf_token'] ?? null;

    return is_string($token)
        && is_string($stored)
        && hash_equals($stored, $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash_messages'] ??= [];
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}
