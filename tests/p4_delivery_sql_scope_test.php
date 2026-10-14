<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$repo = (string) file_get_contents($root . '/app/Repositories/DeliveryRepository.php');
$controller = (string) file_get_contents($root . '/app/Controllers/DeliveryController.php');
foreach ([
    'deliveryScopeClause($allowedP4Ids',
    'if ($scope === null) return [];',
    "'d.p4_warehouse_id'",
    "'r.p4_warehouse_id'",
    'WHERE 1 = 1 \' . $scope',
    '\' . $scope . \',
] as $fragment) {
    if (!str_contains($repo, $fragment)) {
        throw new RuntimeException('Missing SQL P4 scope: ' . $fragment);
    }
}
if (str_contains($controller, "P4WarehouseScope::filter($this->repository->recent(")
    || str_contains($controller, "P4WarehouseScope::filter($this->repository->pendingNominal(")
    || !str_contains($controller, 'pendingNominal(100,')
    || !str_contains($controller, 'recent(100,')) {
    throw new RuntimeException('P4 delivery scope must filter at SQL level before limit.');
}
echo "[OK] SQL applies P4 scope before delivery and nominal pagination.\n";
