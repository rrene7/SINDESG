<?php
declare(strict_types=1);

use App\Services\AuthService;
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

$app = require SINDESG_ROOT . '/config/app.php';

/*
 | Las sesiones solo pertenecen al contexto web.
 | scripts/check.php y las pruebas se ejecutan por CLI y pueden haber escrito
 | salida antes de cargar bootstrap; intentar session_start() allí genera
 | "headers already sent" aunque la conexión y el sistema estén correctos.
 */
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (bool) ($app['session_secure'] ?? false),
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set((string) ($app['timezone'] ?? 'America/Panama'));

$databaseConfigFile = SINDESG_ROOT . '/config/database.php';
if (!is_file($databaseConfigFile)) {
    $databaseConfigFile = SINDESG_ROOT . '/config/database.example.php';
}

$databaseConfig = require $databaseConfigFile;
$database = new Database($databaseConfig);
$authService = new AuthService($database);

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
    $relative = 'public/assets/' . ltrim($path, '/');
    $url = sindesg_url($relative);
    $fullPath = SINDESG_ROOT . '/' . $relative;

    $version = is_file($fullPath)
        ? (string) filemtime($fullPath)
        : (string) sindesg_config('version', '1');

    return $url . '?v=' . rawurlencode($version);
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


function sindesg_user(): ?array
{
    global $authService;

    return $authService->currentUser();
}

function sindesg_can(string $permission): bool
{
    global $authService;

    return $authService->can($permission);
}

function sindesg_permission_for(string $module, string $action = 'index', string $method = 'GET'): ?string
{
    if ($module === 'auth') {
        return null;
    }

    if ($module === 'dashboard') {
        return 'dashboard.view';
    }

    if ($module === 'admin') {
        return 'admin.manage';
    }

    if ($module === 'audit') {
        return 'audit.view';
    }

    $viewPermissions = [
        'inventory' => 'inventory.view',
        'receipts' => 'receipts.view',
        'production' => 'production.view',
        'people' => 'people.view',
        'warehouses' => 'warehouses.view',
        'requests' => 'requests.view',
        'distribution' => 'distribution.view',
        'deliveries' => 'deliveries.view',
        'adjustments' => 'adjustments.view',
        'reports' => 'reports.view',
        'migration' => 'migration.view',
    ];

    $managePermissions = [
        'inventory' => 'inventory.manage',
        'receipts' => 'receipts.manage',
        'production' => 'production.manage',
        'warehouses' => 'warehouses.manage',
        'requests' => 'requests.manage',
        'distribution' => 'distribution.manage',
        'deliveries' => 'deliveries.manage',
        'adjustments' => 'adjustments.manage',
        'migration' => 'migration.manage',
    ];

    if (strtoupper($method) === 'POST') {
        return $managePermissions[$module] ?? ($viewPermissions[$module] ?? null);
    }

    $manageGetActions = [
        'new',
        'create',
        'edit',
        'form',
        'prepare',
        'approve',
    ];

    if (in_array($action, $manageGetActions, true) && isset($managePermissions[$module])) {
        return $managePermissions[$module];
    }

    return $viewPermissions[$module] ?? null;
}

/**
 * Separación operativa adicional al RBAC de módulos.
 * No concede permisos: exige rol funcional más permisos existentes.
 */
function sindesg_role_action_allowed(string $module, string $action, string $method, array $user): bool
{
    $roles = (array) ($user['role_codes'] ?? []);
    if (in_array('ADMIN', $roles, true)) {
        return true;
    }

    $isWrite = strtoupper($method) === 'POST';
    $isScopedP4 = in_array('P4', $roles, true) && !in_array('CENTRAL', $roles, true);
    // These modules still expose institution-wide information. P4 staff must
    // not inherit global visibility just because a role permission was edited.
    if ($isScopedP4 && in_array($module, [
        'reports', 'adjustments', 'inventory', 'receipts', 'production',
        'migration', 'audit',
    ], true)) {
        return false;
    }
    // A P4 operator may consult only assigned warehouses; never create or
    // modify the network of warehouses through global management actions.
    if ($isScopedP4 && $module === 'warehouses'
        && ($isWrite || in_array($action, ['new', 'edit'], true))) {
        return false;
    }
    $centralOnly = $module === 'requests' && (
        ($isWrite && in_array($action, ['start-review', 'decide'], true))
        || (!$isWrite && $action === 'central-review')
    );
    $centralOnly = $centralOnly || ($module === 'distribution' && (
        ($isWrite && in_array($action, ['prepare', 'authorize', 'dispatch', 'cancel'], true))
        || (!$isWrite && $action === 'new')
    ));
    if ($centralOnly) {
        return in_array('CENTRAL', $roles, true);
    }

    if ($module === 'distribution' && $isWrite && $action === 'receive') {
        return in_array('P4', $roles, true);
    }
    return true;
}

function sindesg_api_authorized(bool $allowSetup = false): bool
{
    global $authService;

    if ($allowSetup && !$authService->hasUsers()) {
        return true;
    }

    return $authService->currentUser() !== null;
}
