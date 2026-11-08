<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/DistributionNavigation.php';
require dirname(__DIR__) . '/app/Repositories/RequestRepository.php';

use App\Repositories\RequestRepository;
use App\Services\DistributionNavigation;

$cases = [
    ['approved', 1.0, true, null, true],
    ['partially_approved', 4.0, true, 'received', true],
    ['fulfilled', 0.0, true, 'received', false],
    ['fulfilled', 2.0, true, 'received', false],
    ['approved', 0.0, true, null, false],
    ['approved', 2.0, false, null, false],
    ['approved', 2.0, true, 'prepared', false],
    ['approved', 2.0, true, 'authorized', false],
    ['approved', 2.0, true, 'dispatched', false],
    ['rejected', 2.0, true, null, false],
    ['approved', INF, true, null, false],
];

foreach ($cases as [$status, $remaining, $p4Active, $existing, $expected]) {
    $actual = DistributionNavigation::canPrepare($status, $remaining, $p4Active, $existing);
    if ($actual !== $expected) {
        throw new RuntimeException("Invalid prepare action for request status {$status} / transfer {$existing}.");
    }
}

// The actual existing transfer is queried, not inferred from aggregated
// received/approved quantities. Prefer an open transfer over a received one.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE distributions (id INTEGER PRIMARY KEY, request_id INTEGER, status TEXT)');
$pdo->exec("INSERT INTO distributions VALUES
    (8, 8, 'received'),
    (9, 8, 'cancelled'),
    (10, 8, 'authorized'),
    (11, 9, 'cancelled'),
    (12, 10, 'received')");
$repository = new RequestRepository($pdo);
$active = $repository->latestDistribution(8);
if ((int) ($active['id'] ?? 0) !== 10 || $active['status'] !== 'authorized') {
    throw new RuntimeException('Open transfer must be shown ahead of historical received transfers.');
}
$pdo->exec("UPDATE distributions SET status = 'received' WHERE id = 10");
$received = $repository->latestDistribution(8);
if ((int) ($received['id'] ?? 0) !== 10 || $received['status'] !== 'received') {
    throw new RuntimeException('Latest received transfer must remain available for audit.');
}
if ($repository->latestDistribution(9) !== null || $repository->latestDistribution(999) !== null) {
    throw new RuntimeException('Cancelled or missing transfers must not create a follow-up link.');
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/Controllers/DistributionController.php');
$requestController = (string) file_get_contents($root . '/app/Controllers/RequestsController.php');
$requestView = (string) file_get_contents($root . '/app/Views/requests/show.php');
$notice = (string) file_get_contents($root . '/app/Views/distribution/notice.php');

// Failures during GET must render a notice, never send the user back to
// distribution?action=new&request_id=N; POST PRG semantics remain intact.
foreach ([
    "if (strtoupper(\$method) !== 'POST')",
    "/app/Views/distribution/notice.php",
    "'distributionNotice' => \$exception->getMessage()",
] as $required) {
    if (!str_contains($controller, $required)) {
        throw new RuntimeException('Distribution GET error path lacks safe navigation.');
    }
}
if (!str_contains($requestController, "'requestDistribution' => \$this->repository->latestDistribution(\$requestId)")
    || !str_contains($requestView, 'DistributionNavigation::canPrepare(')
    || !str_contains($requestView, 'Ver transferencia')
    || !str_contains($requestView, "'distribution.view'")
    || !str_contains($requestView, "'distribution.manage'")
    || !str_contains($notice, 'Ver solicitud y seguimiento')
    || !str_contains($notice, "e(\$distributionNotice")) {
    throw new RuntimeException('Transfer navigation or authorized user notice is missing.');
}

echo "[OK] Solicitud atendida, transferencia existente y salida sin redirecciones circulares.\n";
