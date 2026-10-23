<?php
declare(strict_types=1);

use App\Services\AuthService;
use App\Support\Database;
use App\Support\WebPaths;

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

// El layout web se detecta desde el archivo PHP ejecutado, no por SO ni Host.
// Windows: /SINDESG/index.php, recursos /SINDESG/public/assets/...
// Linux: DocumentRoot .../public, /index.php, recursos /assets/...
$webPaths = WebPaths::fromServer(
    $_SERVER, SINDESG_ROOT, (string) ($app['base_url'] ?? 'auto')
);
$app['base_url'] = $webPaths->basePath;
$app['public_document_root'] = $webPaths->publicDocumentRoot;

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
    global $webPaths;
    return $webPaths->url($path);
}

function sindesg_asset(string $path): string
{
    global $webPaths;
    $asset = ltrim($path, '/');
    $url = $webPaths->asset($asset);
    $fullPath = SINDESG_ROOT . '/public/assets/' . $asset;

    $version = is_file($fullPath)
        ? (string) filemtime($fullPath)
        : (string) sindesg_config('version', '1');

    return $url . '?v=' . rawurlencode($version);
}

function sindesg_upload_url(string $path): string
{
    global $webPaths;
    return $webPaths->uploaded($path);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Cantidad legible: 1.000 unidades almacenadas se presentan como 1. */
function sindesg_units(int|float|string $quantity): string
{
    return \App\Services\P4QuantityDisplay::units((float) $quantity);
}

/** Etiqueta humana sin sustituir los códigos almacenados para auditoría. */
function sindesg_variant_label(array $row): string
{
    $parts = [];
    $brand = trim((string) ($row['brand_name'] ?? ''));
    $color = trim((string) ($row['variant_color'] ?? ''));
    $size = trim((string) ($row['size_name'] ?? ''));
    if ($brand !== '' && $brand !== '—') $parts[] = $brand;
    if ($color !== '') $parts[] = $color;
    if ($size !== '' && $size !== 'S/T' && $size !== '—') $parts[] = 'Talla ' . $size;
    return $parts ? implode(' · ', $parts) : 'Presentación estándar';
}

/** Solo para vistas e impresión; la exportación mantiene referencias originales. */
function sindesg_report_value(array $row, string $key): string
{
    $value = (string) ($row[$key] ?? '');
    $quantityKeys = [
        'quantity','physical_quantity','committed_quantity','available_quantity',
        'quantity_requested','quantity_approved','quantity_dispatched',
        'quantity_received','quantity_delivered','pending_quantity'
    ];
    if (in_array($key, $quantityKeys, true)) return sindesg_units((float) $value);
    if ($key === 'variant_code') return sindesg_variant_label($row);
    if (in_array($key, ['warehouse_code','p4_code','destination_code'], true)) {
        return (string) ($row['warehouse_name'] ?? $row['p4_name']
            ?? $row['destination_name'] ?? 'Almacén institucional');
    }
    if ($value === '' || $value === '—') return $value;
    return match ($key) {
        'request_code' => 'Solicitud de dotación' .
            (!empty($row['p4_name']) ? ' · ' . $row['p4_name'] : ''),
        'transfer_code' => 'Transferencia a ' . ($row['destination_name'] ?? 'almacén P4'),
        'delivery_code' => 'Entrega a ' . ($row['recipient'] ?? 'funcionario'),
        'receipt_code' => 'Recepción de artículos',
        'lot_code' => 'Lote de recepción',
        'adjustment_code' => 'Ajuste de inventario',
        'reference_code' => 'Movimiento registrado en kardex',
        'direction' => match ($value) { 'IN'=>'Entrada', 'OUT'=>'Salida', default=>$value },
        'status' => match ($value) {
            'confirmed'=>'Confirmado', 'draft'=>'Borrador', 'cancelled'=>'Cancelado',
            'received'=>'Recibido', 'dispatched'=>'Despachado', 'approved'=>'Aprobado',
            'submitted'=>'Enviado', 'rejected'=>'Rechazado', default=>$value
        },
        default => $value,
    };
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
    if ($module === 'profile') {
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
        'new-category',
        'edit-category',
        'categories',
        'duplicate-products',
        'edit-product',
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
    // The nominal XLSX is a blank P4 work template. It is never a generic
    // requests.view export: allow GET only to ADMIN or a non-Central P4.
    if ($module === 'requests' && $action === 'download-nominal-template') {
        return strtoupper($method) === 'GET'
            && (in_array('ADMIN', $roles, true)
                || (in_array('P4', $roles, true)
                    && !in_array('CENTRAL', $roles, true)));
    }
    // Prevalidación nominal: nunca accesible a VIEWER ni CENTRAL aunque
    // tengan otro permiso de módulo. Solo POST P4 con ámbito o ADMIN.
    // La solicitud y almacén concretos se comprueban en RequestsController.
    if ($module === 'requests' && $action === 'preview-nominal-file') {
        return strtoupper($method) === 'POST'
            && (in_array('ADMIN', $roles, true)
                || (in_array('P4', $roles, true)
                    && !in_array('CENTRAL', $roles, true)));
    }
    // Retomar el flujo original del P4 después de prevalidar.
    // La confirmación de incidencias escribe solo evidencias auditables;
    // importar desde la vista previa escribe solo filas del borrador.
    // Ambas acciones exigen POST, requests.manage, sesión y
    // pertenencia al P4 activo verificada por RequestsController.
    if ($module === 'requests' && in_array($action, [
        'validate-nominal-selected', 'import-from-nominal-preview'
    ], true)) {
        return strtoupper($method) === 'POST'
            && (in_array('ADMIN', $roles, true)
                || (in_array('P4', $roles, true)
                    && !in_array('CENTRAL', $roles, true)));
    }
    // La corrección de un rechazo es excepcional: exclusivamente ADMIN.
    if ($module === 'requests' && $action === 'reopen-rejected') {
        return strtoupper($method) === 'POST' && in_array('ADMIN', $roles, true);
    }
    if (in_array('ADMIN', $roles, true)) {
        return true;
    }
    // Category maintenance is administrative, not an ordinary inventory action.
    if ($module === 'inventory' && in_array($action, [
        'new-category', 'edit-category', 'categories', 'save-category',
        'update-category', 'archive-category', 'restore-category',
        'duplicate-products', 'edit-product', 'update-product'
    ], true)) {
        return false;
    }

    // Administration is reserved to ADMIN even if RBAC permissions are edited.
    if ($module === 'admin') {
        return false;
    }

    // La estructura institucional de P4 es competencia exclusiva de ADMIN.
    // Quienes solo tienen warehouses.view conservan consulta y seguimiento;
    // warehouses.manage nunca habilita creación/inactivación a CENTRAL.
    if ($module === 'warehouses') {
        // P4 sin rol Central no puede listar/consultar otros P4;
        // no hay aislamiento por fila en warehouses/show.
        return (!in_array('P4', $roles, true) || in_array('CENTRAL', $roles, true))
            && strtoupper($method) === 'GET'
            && in_array($action, ['index', 'show'], true);
    }

    // Los lotes históricos pueden alterar catálogo de referencia y trazabilidad.
    // Su mantenimiento es administrativo, incluso si se otorgase migration.manage.
    if ($module === 'migration') {
        return false;
    }

    // Preparar en masa el catálogo base de calzado no es una recepción cotidiana.
    if ($module === 'inventory' && $action === 'prepare-footwear') {
        return false;
    }

    // El Almacén Central recibe y revisa solicitudes P4, pero nunca las
    // origina, edita, envía ni importa a nombre de una Dirección o Zona.
    // ADMIN conserva el acceso excepcional, sujeto al RBAC y auditoría.
    if ($module === 'requests' && in_array('CENTRAL', $roles, true)) {
        return strtoupper($method) === 'POST'
            ? in_array($action, ['start-review', 'decide'], true)
            : in_array($action, ['index', 'show', 'central-review'], true);
    }

    // Stock-compensating adjustments: Central prepares; only ADMIN confirms.
    // This role check supplements, never replaces, module RBAC permissions.
    if ($module === 'adjustments') {
        if ($action === 'confirm') {
            return false; // ADMIN returned true above; reject all others.
        }
        return in_array('CENTRAL', $roles, true)
            && (strtoupper($method) === 'POST'
                ? in_array($action, ['save', 'cancel'], true)
                : in_array($action, ['index', 'new', 'show'], true));
    }

    if ($module === 'profile') {
        return $action === 'signature'
            && in_array(strtoupper($method), ['GET','POST'], true)
            && in_array('P4', $roles, true)
            && !in_array('ADMIN', $roles, true)
            && !in_array('CENTRAL', $roles, true);
    }

    // Safe local pilot: the legacy controllers do not enforce row-level
    // warehouse scopes. Until that is complete, P4 may only view its home.
    if (in_array('P4', $roles, true) && !in_array('ADMIN', $roles, true)
        && !in_array('CENTRAL', $roles, true)) {
        if ($module === 'dashboard' || $module === 'auth') return true;
        if ($module === 'deliveries') {
            return strtoupper($method) === 'POST'
                ? in_array($action, ['save', 'confirm', 'deliver-now'], true)
                : in_array($action, ['index', 'new', 'show', 'nominal-report'], true);
        }
        if ($module === 'distribution') {
            return strtoupper($method) === 'POST'
                ? $action === 'receive'
                : $action === 'show';
        }
        if ($module !== 'requests') return false;
        $allowed = strtoupper($method) === 'POST'
            ? ['save', 'add-item', 'remove-item', 'submit', 'preview-nominal-file',
                'validate-nominal-selected', 'import-from-nominal-preview']
            : ['new', 'show', 'download-nominal-template'];
        return in_array($action, $allowed, true);
    }

    $isWrite = strtoupper($method) === 'POST';
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
