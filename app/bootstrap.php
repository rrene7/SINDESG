<?php
declare(strict_types=1);

use App\Support\Database;

define('SINDESG_ROOT', dirname(__DIR__));

require_once SINDESG_ROOT . '/app/Support/Database.php';

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

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
