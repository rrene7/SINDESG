<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RequestRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class RequestService
{
    public function __construct(
        private RequestRepository $repository,
        private RrhhEmployeeDirectory $employees
    ) {
    }

    public function createDraft(array $input): int
    {
        $p4Id = (int) ($input['p4_warehouse_id'] ?? 0);
        $requesterId = (int) ($input['requested_by_employee_id'] ?? 0);
        $requestedAt = $this->normalizeDateTime((string) ($input['requested_at'] ?? ''));
        $notes = $this->nullableText($input['notes'] ?? null);

        if ($p4Id <= 0 || !$this->repository->p4IsActive($p4Id)) {
            throw new InvalidArgumentException('Seleccione un P4 activo.');
        }

        $requester = $this->employees->findEligibleEmployee($requesterId);
        if ($requester === null) {
            throw new InvalidArgumentException('Seleccione un funcionario solicitante válido en RRHH2029.');
        }

        return $this->repository->createDraft([
            'p4_warehouse_id' => $p4Id,
            'requested_by_employee_id' => $requester['employee_id'],
            'requested_by_label_snapshot' => $requester['display_name'],
            'requested_by_document_snapshot' => $requester['document_number'],
            'requested_by_position_snapshot' => $requester['position_number'],
            'requested_by_rank_snapshot' => $requester['rank_name'],
            'requested_at' => $requestedAt,
            'notes' => $notes,
        ]);
    }

    public function addItem(int $requestId, array $input): void
    {
        $variantId = (int) ($input['product_variant_id'] ?? 0);
        $beneficiaryId = (int) ($input['beneficiary_employee_id'] ?? 0);
        $quantity = trim((string) ($input['quantity_requested'] ?? ''));
        $notes = $this->nullableText($input['item_notes'] ?? null);

        if ($variantId <= 0 || !$this->repository->variantIsActive($variantId)) {
            throw new InvalidArgumentException('Seleccione una variante activa.');
        }

        if (!is_numeric($quantity) || (float) $quantity <= 0) {
            throw new InvalidArgumentException('La cantidad solicitada debe ser mayor que cero.');
        }

        $beneficiary = null;
        if ($beneficiaryId > 0) {
            $beneficiary = $this->employees->findP4Nominee($beneficiaryId);
            if ($beneficiary === null) {
                throw new InvalidArgumentException('El beneficiario seleccionado no está disponible en RRHH2029.');
            }
        }

        $duplicateBeneficiaryId = $beneficiary ? (int) $beneficiary['employee_id'] : null;
        if ($this->repository->hasDuplicateItem($requestId, $variantId, $duplicateBeneficiaryId)) {
            throw new InvalidArgumentException(
                'Ese artículo ya existe para el mismo beneficiario dentro de la solicitud.'
            );
        }

        $this->repository->addItem($requestId, [
            'product_variant_id' => $variantId,
            'beneficiary_employee_id' => $duplicateBeneficiaryId,
            'beneficiary_label_snapshot' => $beneficiary['display_name'] ?? null,
            'beneficiary_document_snapshot' => $beneficiary['document_number'] ?? null,
            'beneficiary_position_snapshot' => $beneficiary['position_number'] ?? null,
            'beneficiary_rank_snapshot' => $beneficiary['rank_name'] ?? null,
            'beneficiary_unit_id_snapshot' => $beneficiary['organization_unit_id'] ?? null,
            'beneficiary_unit_name_snapshot' => $beneficiary['organization_unit_name'] ?? null,
            'quantity_requested' => number_format((float) $quantity, 3, '.', ''),
            'notes' => $notes,
        ]);
    }

    public function removeItem(int $requestId, int $itemId): void
    {
        if ($requestId <= 0 || $itemId <= 0) {
            throw new InvalidArgumentException('El renglón seleccionado no es válido.');
        }

        $this->repository->removeItem($requestId, $itemId);
    }

    public function submit(int $requestId): void
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('La solicitud seleccionada no es válida.');
        }

        $this->repository->submit($requestId);
    }

    public function startReview(int $requestId): void
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('La solicitud seleccionada no es válida.');
        }

        $this->repository->startReview($requestId);
    }

    public function decide(int $requestId, array $input): string
    {
        $reviewerId = (int) ($input['reviewed_by_employee_id'] ?? 0);
        $reviewer = $this->employees->findEligibleEmployee($reviewerId);

        if ($reviewer === null) {
            throw new InvalidArgumentException('Seleccione un funcionario revisor válido en RRHH2029.');
        }

        $raw = $input['approved_quantity'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Las cantidades aprobadas no tienen un formato válido.');
        }

        $approved = [];
        foreach ($raw as $itemId => $quantity) {
            $itemId = (int) $itemId;
            $quantity = trim((string) $quantity);

            if ($itemId <= 0 || $quantity === '' || !is_numeric($quantity)) {
                throw new InvalidArgumentException('Revise las cantidades aprobadas.');
            }

            $approved[$itemId] = (float) $quantity;
        }

        return $this->repository->decide(
            $requestId,
            $approved,
            $reviewer,
            $this->nullableText($input['review_notes'] ?? null)
        );
    }

    private function normalizeDateTime(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new InvalidArgumentException('La fecha de la solicitud no tiene un formato válido.');
        }

        return $date->format('Y-m-d H:i:00');
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
