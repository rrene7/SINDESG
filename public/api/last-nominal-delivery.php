<?php
declare(strict_types=1);

use App\Repositories\DeliveryRepository;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!sindesg_api_authorized(false) || !sindesg_can('requests.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Permiso de solicitudes requerido.']);
    exit;
}

$variantId = filter_var($_GET['variant_id'] ?? null, FILTER_VALIDATE_INT);
$rawIds = (string) ($_GET['employee_ids'] ?? '');
$values = explode(',', $rawIds);

if (!$variantId || count($values) < 1 || count($values) > 100) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Seleccione variante y entre 1 y 100 funcionarios.']);
    exit;
}

$ids = [];
foreach ($values as $value) {
    $value = trim($value);
    if (!ctype_digit($value) || (int) $value <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'IDs no válidos.']);
        exit;
    }
    $ids[(int) $value] = (int) $value;
}

try {
    $pdo = $database->connection('sindesg');
    $query = $pdo->prepare(<<<'SQL'
        SELECT pv.product_id
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        WHERE pv.id = :id AND pv.status = 'active' AND p.status = 'active'
        LIMIT 1
    SQL);
    $query->execute(['id' => (int) $variantId]);
    $productId = (int) $query->fetchColumn();
    if ($productId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Variante no disponible.']);
        exit;
    }
    $scope = \App\Services\P4WarehouseScope::current($database);
    $dates = (new DeliveryRepository($pdo))
        ->lastConfirmedDatesForProduct(
            $productId,
            array_values($ids),
            $scope['unrestricted'] ? null : $scope['warehouses']
        );

    echo json_encode([
        'ok' => true,
        'dates' => $dates,
        'meaning' => 'Fecha de la última entrega del mismo artículo confirmada en SINDESG',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'No fue posible consultar las entregas anteriores.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
