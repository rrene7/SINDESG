<?php
declare(strict_types=1);

use App\Repositories\DeliveryRepository;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!sindesg_api_authorized(false)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Autenticación requerida.', 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Cache-Control: no-store');
$roles = (array) (sindesg_user()['role_codes'] ?? []);
if (in_array('P4', $roles, true) && !in_array('ADMIN', $roles, true)
    && !in_array('CENTRAL', $roles, true)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Historial global no disponible para P4.','items'=>[]],JSON_UNESCAPED_UNICODE);
    exit;
}


$employeeId = (int) ($_GET['employee_id'] ?? 0);

if ($employeeId <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Funcionario no válido.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $repository = new DeliveryRepository($database->connection('sindesg'));

    echo json_encode([
        'ok' => true,
        'items' => $repository->historySummaryForEmployee($employeeId, 12),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'No fue posible consultar el historial de dotación.',
        'items' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
