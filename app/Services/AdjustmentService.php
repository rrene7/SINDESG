<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdjustmentRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class AdjustmentService
{
    private const TYPES = [
        'return',
        'exchange',
        'replacement',
        'deterioration',
        'writeoff',
        'loss',
        'shortage',
        'surplus',
        'authorized_adjustment',
        'cycle_count',
    ];

    public function __construct(
        private AdjustmentRepository $repository,
        private RrhhEmployeeDirectory $employees
    ) {
    }

    public function createDraft(array $input): int
    {
        $type = trim((string) ($input['adjustment_type'] ?? ''));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Seleccione un tipo de operación válido.');
        }

        $responsible = $this->employees->findEligibleEmployee(
            (int) ($input['responsible_employee_id'] ?? 0)
        );
        if ($responsible === null) {
            throw new InvalidArgumentException('Seleccione un funcionario responsable válido en RRHH2029.');
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Indique el motivo de la operación.');
        }

        $document = $this->nullableText($input['document_number'] ?? null);
        if (
            in_array($type, ['writeoff', 'authorized_adjustment', 'cycle_count'], true)
            && $document === null
        ) {
            throw new InvalidArgumentException('Esta operación requiere documento o acta de soporte.');
        }

        $sourceDeliveryItemId = (int) ($input['source_delivery_item_id'] ?? 0);
        $source = null;

        if (in_array($type, AdjustmentRepository::SOURCE_TYPES, true)) {
            if ($sourceDeliveryItemId <= 0) {
                throw new InvalidArgumentException('Seleccione una entrega individual confirmada como origen.');
            }

            $source = $this->repository->sourceDeliveryItem($sourceDeliveryItemId);
            if ($source === null) {
                throw new InvalidArgumentException('La entrega de origen no existe o no está confirmada.');
            }
        } elseif ($sourceDeliveryItemId > 0) {
            throw new InvalidArgumentException('El tipo seleccionado no utiliza una entrega individual como origen.');
        }

        $warehouseId = $source !== null
            ? (int) $source['warehouse_id']
            : (int) ($input['warehouse_id'] ?? 0);

        if ($warehouseId <= 0 || !$this->repository->warehouseIsAdjustable($warehouseId)) {
            throw new InvalidArgumentException('Seleccione un Almacén Central o P4 activo.');
        }

        $variantId = $source !== null
            ? (int) $source['product_variant_id']
            : (int) ($input['product_variant_id'] ?? 0);

        if ($variantId <= 0 || !$this->repository->variantIsActive($variantId)) {
            throw new InvalidArgumentException('Seleccione una variante activa.');
        }

        $replacementVariantId = (int) ($input['replacement_variant_id'] ?? 0);
        if (in_array($type, ['exchange', 'replacement'], true)) {
            if (
                $replacementVariantId <= 0
                || !$this->repository->variantIsActive($replacementVariantId)
            ) {
                throw new InvalidArgumentException('Seleccione la variante que se entregará como reemplazo.');
            }

            $sourceProductId = $this->repository->variantProductId($variantId);
            $replacementProductId = $this->repository->variantProductId($replacementVariantId);

            if ($sourceProductId === null || $sourceProductId !== $replacementProductId) {
                throw new InvalidArgumentException(
                    'El cambio o reposición debe usar una variante del mismo artículo.'
                );
            }

            if ($type === 'exchange' && $replacementVariantId === $variantId) {
                throw new InvalidArgumentException('Para un cambio seleccione una variante diferente.');
            }
        } else {
            $replacementVariantId = 0;
        }

        $returnCondition = null;
        if (in_array($type, ['return', 'exchange'], true)) {
            $returnCondition = trim((string) ($input['return_condition'] ?? ''));
            if (!in_array($returnCondition, ['reusable', 'non_reusable'], true)) {
                throw new InvalidArgumentException('Indique si la prenda devuelta es reutilizable.');
            }
        }

        $manualDirection = null;
        if ($type === 'authorized_adjustment') {
            $manualDirection = strtoupper(trim((string) ($input['manual_direction'] ?? '')));
            if (!in_array($manualDirection, ['IN', 'OUT'], true)) {
                throw new InvalidArgumentException('Seleccione si el ajuste autorizado es entrada o salida.');
            }
        }

        $quantity = 0.0;
        $countedQuantity = null;

        if ($type === 'cycle_count') {
            $rawCounted = trim((string) ($input['counted_quantity'] ?? ''));
            if ($rawCounted === '' || !is_numeric($rawCounted) || (float) $rawCounted < 0) {
                throw new InvalidArgumentException('La cantidad contada debe ser cero o mayor.');
            }
            $countedQuantity = (float) $rawCounted;
        } else {
            $rawQuantity = trim((string) ($input['quantity'] ?? ''));
            if ($rawQuantity === '' || !is_numeric($rawQuantity) || (float) $rawQuantity <= 0) {
                throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
            }
            $quantity = (float) $rawQuantity;
        }

        if ($source !== null && in_array($type, ['return', 'exchange'], true)) {
            if ($quantity > (float) $source['returnable_quantity'] + 0.000001) {
                throw new InvalidArgumentException(
                    'La cantidad supera lo pendiente de devolver de la entrega original.'
                );
            }
        }

        if ($source !== null && $type === 'replacement') {
            if ($quantity > (float) $source['delivered_quantity'] + 0.000001) {
                throw new InvalidArgumentException('La reposición supera la cantidad de la entrega original.');
            }
        }

        return $this->repository->createDraft([
            'adjustment_type' => $type,
            'warehouse_id' => $warehouseId,
            'source_delivery_item_id' => $source !== null ? $sourceDeliveryItemId : null,
            'affected_employee_id' => $source['recipient_employee_id'] ?? null,
            'affected_employee_label_snapshot' => $source['recipient_label_snapshot'] ?? null,
            'affected_employee_document_snapshot' => $source['recipient_document_snapshot'] ?? null,
            'affected_employee_position_snapshot' => $source['recipient_position_snapshot'] ?? null,
            'affected_employee_rank_snapshot' => $source['recipient_rank_snapshot'] ?? null,
            'product_variant_id' => $variantId,
            'replacement_variant_id' => $replacementVariantId > 0 ? $replacementVariantId : null,
            'quantity' => $quantity,
            'counted_quantity' => $countedQuantity,
            'return_condition' => $returnCondition,
            'manual_direction' => $manualDirection,
            'responsible_employee_id' => $responsible['employee_id'],
            'responsible_label_snapshot' => $responsible['display_name'],
            'responsible_document_snapshot' => $responsible['document_number'],
            'responsible_position_snapshot' => $responsible['position_number'],
            'responsible_rank_snapshot' => $responsible['rank_name'],
            'occurred_at' => $this->normalizeDateTime((string) ($input['occurred_at'] ?? '')),
            'document_number' => $document,
            'reason' => $reason,
            'notes' => $this->nullableText($input['notes'] ?? null),
        ]);
    }

    public function confirm(int $adjustmentId): array
    {
        if ($adjustmentId <= 0) {
            throw new InvalidArgumentException('El ajuste seleccionado no es válido.');
        }

        return $this->repository->confirm($adjustmentId);
    }

    public function cancel(int $adjustmentId): void
    {
        if ($adjustmentId <= 0) {
            throw new InvalidArgumentException('El ajuste seleccionado no es válido.');
        }

        $this->repository->cancel($adjustmentId);
    }

    private function normalizeDateTime(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new InvalidArgumentException('La fecha de la operación no tiene un formato válido.');
        }

        return $date->format('Y-m-d H:i:00');
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
