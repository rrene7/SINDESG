<?php
declare(strict_types=1);

use App\Services\RrhhEmployeeDirectory;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!sindesg_api_authorized(true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Autenticación requerida.', 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Cache-Control: no-store');

$query = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Escriba entre 2 y 100 caracteres.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $directory = new RrhhEmployeeDirectory($database);
    $forP4 = (string) ($_GET['scope'] ?? '') === 'p4';
    if ($forP4 && !sindesg_can('requests.view')) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Permiso de consulta P4 requerido.',
            'items' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Endpoint de solo lectura: la consulta RRHH puede tardar y no debe
    // bloquear otras acciones del mismo usuario con el candado de sesión.
    // La autorización y los permisos ya se verificaron anteriormente.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    echo json_encode([
        'ok' => true,
        'items' => $forP4
            ? $directory->searchForP4($query, 20)
            : $directory->search($query, 20),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'RRHH2029 no está disponible para búsqueda en este momento.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
