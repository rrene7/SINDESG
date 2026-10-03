<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReceiptRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class ReceiptService
{
    private const SOURCE_TYPES = [
        'purchase',
        'donation',
        'transfer',
        'return',
        'other',
    ];

    public function __construct(private ReceiptRepository $repository)
    {
    }

    public function createDraft(array $input): int
    {
        $sourceType = trim((string) ($input['source_type'] ?? ''));
        $supplierOrOrigin = trim((string) ($input['supplier_or_origin'] ?? ''));
        $documentNumber = $this->nullableText($input['document_number'] ?? null);
        $receivedBy = trim((string) ($input['received_by_label'] ?? ''));
        $notes = $this->nullableText($input['notes'] ?? null);
        $receivedAt = $this->normalizeDateTime((string) ($input['received_at'] ?? ''));

        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw new InvalidArgumentException('Seleccione una procedencia válida.');
        }

        if ($supplierOrOrigin === '') {
            throw new InvalidArgumentException('Indique el proveedor u origen del lote.');
        }

        if ($receivedBy === '') {
            throw new InvalidArgumentException('Indique quién recibe el lote.');
        }

        if (mb_strlen($supplierOrOrigin) > 180) {
            throw new InvalidArgumentException('El proveedor u origen es demasiado largo.');
        }

        if (mb_strlen($receivedBy) > 160) {
            throw new InvalidArgumentException('El nombre del responsable es demasiado largo.');
        }

        $variantIds = $input['variant_id'] ?? [];
        $quantities = $input['quantity'] ?? [];
        $itemNotes = $input['item_notes'] ?? [];

        if (!is_array($variantIds) || !is_array($quantities) || !is_array($itemNotes)) {
            throw new InvalidArgumentException('El detalle de artículos no tiene un formato válido.');
        }

        $items = [];
        $seen = [];

        foreach ($variantIds as $index => $rawVariantId) {
            $variantId = (int) $rawVariantId;
            $rawQuantity = trim((string) ($quantities[$index] ?? ''));
            $itemNote = $this->nullableText($itemNotes[$index] ?? null);

            if ($variantId <= 0 && $rawQuantity === '') {
                continue;
            }

            if ($variantId <= 0) {
                throw new InvalidArgumentException('Seleccione una variante en cada renglón utilizado.');
            }

            if (isset($seen[$variantId])) {
                throw new InvalidArgumentException(
                    'Una misma variante no puede repetirse dentro del lote. Use un solo renglón y acumule la cantidad.'
                );
            }

            if (!is_numeric($rawQuantity)) {
                throw new InvalidArgumentException('La cantidad debe ser numérica.');
            }

            $quantity = (float) $rawQuantity;

            if ($quantity <= 0 || $quantity > 999999999.999) {
                throw new InvalidArgumentException('Cada cantidad debe ser mayor que cero.');
            }

            $seen[$variantId] = true;
            $items[] = [
                'product_variant_id' => $variantId,
                'quantity' => number_format($quantity, 3, '.', ''),
                'notes' => $itemNote,
            ];
        }

        if ($items === []) {
            throw new InvalidArgumentException('Agregue al menos un artículo al lote.');
        }

        $activeIds = $this->repository->activeVariantIds(array_keys($seen));
        sort($activeIds);
        $requestedIds = array_keys($seen);
        sort($requestedIds);

        if ($activeIds !== $requestedIds) {
            throw new InvalidArgumentException(
                'Una o más variantes seleccionadas ya no están activas o no existen.'
            );
        }

        return $this->repository->createDraft([
            'source_type' => $sourceType,
            'supplier_or_origin' => $supplierOrOrigin,
            'document_number' => $documentNumber,
            'received_at' => $receivedAt,
            'received_by_label' => $receivedBy,
            'notes' => $notes,
        ], $items);
    }

    public function confirm(int $receiptId): array
    {
        if ($receiptId <= 0) {
            throw new InvalidArgumentException('La recepción seleccionada no es válida.');
        }

        return $this->repository->confirm($receiptId);
    }

    public static function sourceTypes(): array
    {
        return [
            'purchase' => 'Compra',
            'donation' => 'Donación',
            'transfer' => 'Transferencia recibida',
            'return' => 'Devolución al almacén',
            'other' => 'Otro autorizado',
        ];
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Indique la fecha y hora de ingreso.');
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new InvalidArgumentException('La fecha de ingreso no tiene un formato válido.');
        }

        return $date->format('Y-m-d H:i:00');
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
