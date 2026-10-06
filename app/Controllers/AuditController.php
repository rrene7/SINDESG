<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditRepository;
use InvalidArgumentException;
use PDO;

final class AuditController
{
    private AuditRepository $repository;

    public function __construct(PDO $pdo)
    {
        $this->repository = new AuditRepository($pdo);
    }

    public function handle(string $action, array $query): array
    {
        if ($action === 'show') {
            $audit = $this->repository->find((int) ($query['id'] ?? 0));
            if ($audit === null) {
                throw new InvalidArgumentException('El registro de auditoría no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/audit/show.php',
                'title' => 'Auditoría #' . $audit['id'],
                'data' => [
                    'auditEvent' => $audit,
                ],
            ];
        }

        $filters = [
            'date_from' => trim((string) ($query['date_from'] ?? '')),
            'date_to' => trim((string) ($query['date_to'] ?? '')),
            'user_id' => max(0, (int) ($query['user_id'] ?? 0)),
            'module' => trim((string) ($query['module_filter'] ?? '')),
            'action' => trim((string) ($query['action'] ?? '')),
            'q' => trim((string) ($query['q'] ?? '')),
        ];

        return [
            'view' => SINDESG_ROOT . '/app/Views/audit/index.php',
            'title' => 'Auditoría',
            'data' => [
                'auditEvents' => $this->repository->list($filters, 500),
                'auditUsers' => $this->repository->users(),
                'auditModules' => $this->repository->modules(),
                'auditFilters' => $filters,
            ],
        ];
    }
}
