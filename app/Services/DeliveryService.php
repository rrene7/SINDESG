<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DeliveryRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class DeliveryService
{
    public function __construct(
        private DeliveryRepository $repository,
        private RrhhEmployeeDirectory $employees
    ) {
    }

    public function createDraft(array $input): int
    {
        $requestItemId = (int) ($input['request_item_id'] ?? 0);
        $linkedLine = $requestItemId > 0
            ? $this->repository->pendingNominalLine($requestItemId)
            : null;

        if ($requestItemId > 0 && $linkedLine === null) {
            throw new InvalidArgumentException(
                'La entrega nominal seleccionada ya no está pendiente o no está disponible.'
            );
        }

        $p4Id = (int) ($input['p4_warehouse_id'] ?? 0);
        $recipientId = (int) ($input['recipient_employee_id'] ?? 0);

        if ($linkedLine !== null) {
            $p4Id = (int) $linkedLine['p4_warehouse_id'];
            $recipientId = (int) $linkedLine['beneficiary_employee_id'];
        }

        if ($p4Id <= 0 || !$this->repository->p4IsActive($p4Id)) {
            throw new InvalidArgumentException('Seleccione un P4 activo.');
        }

        $recipient = $this->employees->findEligibleEmployee($recipientId);
        if ($recipient === null) {
            throw new InvalidArgumentException(
                'Seleccione un funcionario receptor válido y activo en RRHH2029.'
            );
        }

        if (
            $linkedLine !== null
            && (int) $linkedLine['beneficiary_employee_id'] !== (int) $recipient['employee_id']
        ) {
            throw new InvalidArgumentException(
                'El funcionario receptor no coincide con el beneficiario de la solicitud.'
            );
        }

        $p4OrganizationUnitId = $this->repository->p4OrganizationUnitId($p4Id);
        $recipientBelongsToP4 = $p4OrganizationUnitId === null
            || $this->employees->belongsToOrganizationUnit(
                (int) $recipient['employee_id'],
                $p4OrganizationUnitId
            );

        $scopeOverrideEnabled = !$recipientBelongsToP4
            && (string) ($input['allow_out_of_scope'] ?? '') === '1';
        $scopeOverrideReason = $this->nullableText($input['scope_override_reason'] ?? null);

        if (!$recipientBelongsToP4 && !$scopeOverrideEnabled) {
            $currentUnit = trim((string) ($recipient['organization_unit_name'] ?? ''));
            $currentDirection = trim((string) ($recipient['direction_name'] ?? ''));
            $context = $currentUnit !== '' ? $currentUnit : $currentDirection;

            throw new InvalidArgumentException(
                'El funcionario receptor no pertenece actualmente a la estructura atendida por este P4.'
                . ($context !== '' ? ' RRHH registra: ' . $context . '.' : '')
                . ' Si existe una autorización excepcional, marque "Habilitar entrega" e indique el motivo.'
            );
        }

        if ($scopeOverrideEnabled && $scopeOverrideReason === null) {
            throw new InvalidArgumentException(
                'Indique el motivo de la entrega excepcional fuera de la adscripción del P4.'
            );
        }

        $deliveredBy = $this->employees->findEligibleEmployee(
            (int) ($input['delivered_by_employee_id'] ?? 0)
        );
        if ($deliveredBy === null) {
            throw new InvalidArgumentException(
                'Seleccione un funcionario que entrega válido en RRHH2029.'
            );
        }

        $linkedQuantity = null;
        if ($linkedLine !== null) {
            $rawQuantity = trim((string) ($input['linked_quantity'] ?? ''));
            if ($rawQuantity === '' || !is_numeric($rawQuantity) || (float) $rawQuantity <= 0) {
                throw new InvalidArgumentException('La cantidad a entregar debe ser mayor que cero.');
            }

            $linkedQuantity = (float) $rawQuantity;
            if ($linkedQuantity > (float) $linkedLine['pending_quantity'] + 0.000001) {
                throw new InvalidArgumentException(
                    'La cantidad supera lo recibido y pendiente para ese beneficiario.'
                );
            }
        }

        return $this->repository->createDraft([
            'p4_warehouse_id' => $p4Id,
            'recipient_employee_id' => $recipient['employee_id'],
            'recipient_label_snapshot' => $recipient['display_name'],
            'recipient_document_snapshot' => $recipient['document_number'],
            'recipient_position_snapshot' => $recipient['position_number'],
            'recipient_rank_snapshot' => $recipient['rank_name'],
            'recipient_unit_id_snapshot' => $recipient['organization_unit_id'],
            'recipient_unit_name_snapshot' => $recipient['organization_unit_name'],
            'recipient_direction_snapshot' => $recipient['direction_name'],
            'recipient_office_snapshot' => $recipient['office_name'],
            'delivered_by_employee_id' => $deliveredBy['employee_id'],
            'delivered_by_label_snapshot' => $deliveredBy['display_name'],
            'delivered_by_document_snapshot' => $deliveredBy['document_number'],
            'delivered_by_position_snapshot' => $deliveredBy['position_number'],
            'delivered_by_rank_snapshot' => $deliveredBy['rank_name'],
            'delivered_at' => $this->normalizeDateTime((string) ($input['delivered_at'] ?? '')),
            'document_number' => $this->nullableText($input['document_number'] ?? null),
            'notes' => $this->nullableText($input['notes'] ?? null),
            'scope_override_enabled' => $scopeOverrideEnabled ? 1 : 0,
            'scope_override_reason' => $scopeOverrideEnabled ? $scopeOverrideReason : null,
        ], $linkedLine, $linkedQuantity);
    }

    /**
     * Single-screen delivery for an already received nominal item.
     * The database still creates the auditable draft internally, then confirms
     * it in one transaction together with stock and both signatures.
     */
    public function deliverNominalNow(array $input, array $evidence): int
    {
        if ((int) ($input['request_item_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Se requiere un renglón nominal recibido.');
        }
        return $this->repository->createAndConfirmNominal(
            fn (): int => $this->createDraft($input),
            $evidence
        );
    }

    public function addItem(int $deliveryId, array $input): void
    {
        if ($deliveryId <= 0) {
            throw new InvalidArgumentException('El despacho seleccionado no es válido.');
        }

        $variantId = (int) ($input['product_variant_id'] ?? 0);
        $rawQuantity = trim((string) ($input['quantity'] ?? ''));

        if ($variantId <= 0 || !$this->repository->variantIsActive($variantId)) {
            throw new InvalidArgumentException('Seleccione una variante activa.');
        }

        if ($rawQuantity === '' || !is_numeric($rawQuantity) || (float) $rawQuantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if ($this->repository->hasDuplicateVariant($deliveryId, $variantId)) {
            throw new InvalidArgumentException(
                'Esa variante ya está incluida en el despacho. Quite el renglón si necesita corregirlo.'
            );
        }

        $this->repository->addItem(
            $deliveryId,
            $variantId,
            (float) $rawQuantity,
            $this->nullableText($input['item_notes'] ?? null)
        );
    }

    public function removeItem(int $deliveryId, int $itemId): void
    {
        if ($deliveryId <= 0 || $itemId <= 0) {
            throw new InvalidArgumentException('El renglón seleccionado no es válido.');
        }

        $this->repository->removeItem($deliveryId, $itemId);
    }

    public function confirm(int $deliveryId, ?array $signatureEvidence = null): array
    {
        if ($deliveryId <= 0) {
            throw new InvalidArgumentException('El despacho seleccionado no es válido.');
        }

        return $this->repository->confirm($deliveryId, $signatureEvidence);
    }

    public function cancel(int $deliveryId): void
    {
        if ($deliveryId <= 0) {
            throw new InvalidArgumentException('El despacho seleccionado no es válido.');
        }

        $this->repository->cancel($deliveryId);
    }

    private function normalizeDateTime(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new InvalidArgumentException('La fecha del despacho no tiene un formato válido.');
        }

        return $date->format('Y-m-d H:i:00');
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
