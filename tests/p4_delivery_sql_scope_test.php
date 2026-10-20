<?php
declare(strict_types=1);
// Consolidated regression: P4 now uses its own scoped queue and denies global
// history/listing, rather than extending the central recent() method.
$root = dirname(__DIR__);
$repo = (string)file_get_contents($root.'/app/Repositories/DeliveryRepository.php');
$controller = (string)file_get_contents($root.'/app/Controllers/DeliveryController.php');
$routes = (string)file_get_contents($root.'/app/bootstrap.php');
$start = strpos($repo, 'public function pendingNominalPageForUser(');
$end = strpos($repo, 'public function pendingNominalOptionsForUser(', $start === false ? 0 : $start);
if ($start === false || $end === false || $end <= $start) {
    throw new RuntimeException('User-scoped nominal queue missing.');
}
$query = substr($repo, $start, $end - $start);
$where = strpos($query, 'WHERE scope.user_id=:user_id');
$limit = strpos($query, 'LIMIT ');
if ($where === false || $limit === false || $where >= $limit
    || !str_contains($query, 'JOIN security_user_p4_scopes scope')
    || !str_contains($query, "w.warehouse_type='P4' AND w.is_active=1")) {
    throw new RuntimeException('P4 scoped SQL must filter authorization before LIMIT.');
}
foreach (['pendingNominalPageForUser(', 'pendingNominalOptionsForUser(', 'assertNominal(', 'assertDelivery(', 'if ($this->isP4Only())'] as $required) {
    if (!str_contains($controller, $required)) {
        throw new RuntimeException('P4 controller guard missing: '.$required);
    }
}
if (!str_contains($routes, "if (in_array('P4', \$roles, true)")
    || !str_contains($routes, "if (\$module !== 'requests') return false")) {
    throw new RuntimeException('P4 action allowlist no longer fails closed.');
}
echo "[OK] Modern P4-only nominal queries and delivery guards scope before pagination.\n";
