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

if (mb_strlen($query) < 2) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Escriba al menos 2 caracteres.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $repository = new RrhhRepository($database);
    echo json_encode([
        'ok' => true,
        'items' => $repository->searchUnits($query, 30, $p4Only),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'RRHH2029 no está disponible para búsqueda de unidades.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
