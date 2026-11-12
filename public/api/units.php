<?php
declare(strict_types=1);

use App\Repositories\RrhhRepository;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!sindesg_api_authorized(false)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Autenticación requerida.', 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Cache-Control: no-store');

$query = trim((string) ($_GET['q'] ?? ''));
$p4Only = (string) ($_GET['scope'] ?? '') === 'p4';
$p4Subunits = (string) ($_GET['scope'] ?? '') === 'p4-subunits';
$headUnitId = (int) ($_GET['parent_id'] ?? 0);

if ($p4Subunits && (
    $headUnitId <= 0
    || !sindesg_can('warehouses.manage')
    || !sindesg_role_action_allowed('warehouses', 'new', 'GET', (array) sindesg_user())
)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Selección de dependencias P4 no autorizada.', 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($query) > 100 || (!$p4Subunits && mb_strlen($query) < 2)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Consulta de unidades no válida (2 a 100 caracteres; para dependencias puede dejarse vacía).',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // Las consultas de jerarquía son solo lectura; evitar bloquear la sesión.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $repository = new RrhhRepository($database);
    echo json_encode([
        'ok' => true,
        'items' => $p4Subunits
            ? $repository->searchP4Subunits($headUnitId, $query, 50)
            : $repository->searchUnits($query, 30, $p4Only),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'RRHH2029 no está disponible para búsqueda de unidades.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
