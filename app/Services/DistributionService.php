<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DistributionRepository;
use InvalidArgumentException;

final class DistributionService
{
    public function __construct(
        private DistributionRepository $repository,
        private RrhhEmployeeDirectory $employees
    ) {
    }

    public function prepare(int $requestId, array $input): int
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('Seleccione una solicitud aprobada.');
        }

        $employee = $this->employee((int) ($input['prepared_by_employee_id'] ?? 0), 'preparador');
        $quantities = $this->quantities($input['transfer_quantity'] ?? []);

        return $this->repository->createPrepared(
            $requestId,
            $employee,
            $quantities,
            $this->nullableText($input['document_number'] ?? null),
            $this->nullableText($input['notes'] ?? null)
        );
    }

    public function authorize(int $distributionId, array $input): void
    {
        $employee = $this->employee((int) ($input['authorized_by_employee_id'] ?? 0), 'autorizador');
        $this->repository->authorize(
            $distributionId,
            $employee,
            $this->nullableText($input['authorization_notes'] ?? null)
        );
    }

    public function dispatch(int $distributionId, array $input): void
    {
        $employee = $this->employee((int) ($input['delivered_by_employee_id'] ?? 0), 'funcionario que entrega');
        $this->repository->dispatch(
            $distributionId,
            $employee,
            $this->nullableText($input['dispatch_notes'] ?? null)
        );
    }

    public function receive(int $distributionId, array $input): void
    {
        $employee = $this->employee((int) ($input['received_by_employee_id'] ?? 0), 'funcionario que recibe');
        $this->repository->receive(
            $distributionId,
            $employee,
            $this->nullableText($input['receipt_notes'] ?? null)
        );
    }

    public function cancel(int $distributionId): void
    {
        if ($distributionId <= 0) {
            throw new InvalidArgumentException('La transferencia seleccionada no es válida.');
        }

        $this->repository->cancel($distributionId);
    }

    private function employee(int $employeeId, string $role): array
    {
        $employee = $this->employees->findEligibleEmployee($employeeId);
        if ($employee === null) {
            throw new InvalidArgumentException("Seleccione un {$role} válido en RRHH2029.");
        }

        return $employee;
    }

    private function quantities(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Las cantidades de transferencia no tienen un formato válido.');
        }

        $quantities = [];
        foreach ($raw as $itemId => $quantity) {
            $itemId = (int) $itemId;
            $quantity = trim((string) $quantity);

            if ($itemId <= 0 || $quantity === '' || !is_numeric($quantity) || (float) $quantity < 0) {
                throw new InvalidArgumentException('Revise las cantidades que desea transferir.');
            }

            $quantities[$itemId] = (float) $quantity;
        }

        return $quantities;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
