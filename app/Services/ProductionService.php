<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductionRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class ProductionService
{
    public function __construct(private ProductionRepository $repository)
    {
    }

    public function createDraft(array $input): int
    {
        $producedAt = $this->normalizeDateTime((string) ($input['produced_at'] ?? ''));
        $responsible = trim((string) ($input['responsible_label'] ?? ''));
        $notes = $this->nullableText($input['notes'] ?? null);

        if ($responsible === '') {
            throw new InvalidArgumentException('Indique el responsable de la producción.');
        }

        if (mb_strlen($responsible) > 160) {
            throw new InvalidArgumentException('El nombre del responsable es demasiado largo.');
        }

        $outputs = $this->parseLines(
            $input['output_variant_id'] ?? [],
            $input['output_quantity'] ?? [],
            $input['output_notes'] ?? [],
            true,
            'producto terminado'
        );

        $inputs = $this->parseLines(
            $input['input_variant_id'] ?? [],
            $input['input_quantity'] ?? [],
            $input['input_notes'] ?? [],
            false,
            'insumo'
        );

        $variantIds = array_merge(
            array_column($outputs, 'product_variant_id'),
            array_column($inputs, 'product_variant_id')
        );

        $activeIds = $this->repository->activeVariantIds($variantIds);
        sort($activeIds);
        $requestedIds = array_values(array_unique(array_map('intval', $variantIds)));
        sort($requestedIds);

        if ($activeIds !== $requestedIds) {
            throw new InvalidArgumentException(
                'Una o más variantes seleccionadas ya no están activas o no existen.'
            );
        }

        return $this->repository->createDraft([
            'produced_at' => $producedAt,
            'responsible_label' => $responsible,
            'notes' => $notes,
        ], $outputs, $inputs);
    }

    public function confirm(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new InvalidArgumentException('La orden seleccionada no es válida.');
        }

        return $this->repository->confirm($orderId);
    }

    private function parseLines(
        mixed $variantIds,
        mixed $quantities,
        mixed $notes,
        bool $required,
        string $label
    ): array {
        if (!is_array($variantIds) || !is_array($quantities) || !is_array($notes)) {
            throw new InvalidArgumentException("El detalle de {$label} no tiene un formato válido.");
        }

        $lines = [];
        $seen = [];

        foreach ($variantIds as $index => $rawVariantId) {
            $variantId = (int) $rawVariantId;
            $rawQuantity = trim((string) ($quantities[$index] ?? ''));
            $lineNote = $this->nullableText($notes[$index] ?? null);

            if ($variantId <= 0 && $rawQuantity === '') {
                continue;
            }

            if ($variantId <= 0) {
                throw new InvalidArgumentException("Seleccione una variante para cada {$label} utilizado.");
            }

            if (isset($seen[$variantId])) {
                throw new InvalidArgumentException(
                    "Una misma variante no puede repetirse en la sección de {$label}. Acumule la cantidad en un solo renglón."
                );
            }

            if (!is_numeric($rawQuantity)) {
                throw new InvalidArgumentException("La cantidad de {$label} debe ser numérica.");
            }

            $quantity = (float) $rawQuantity;
            if ($quantity <= 0 || $quantity > 999999999.999) {
                throw new InvalidArgumentException("Cada cantidad de {$label} debe ser mayor que cero.");
            }

            $seen[$variantId] = true;
            $lines[] = [
                'product_variant_id' => $variantId,
                'quantity' => number_format($quantity, 3, '.', ''),
                'notes' => $lineNote,
            ];
        }

        if ($required && $lines === []) {
            throw new InvalidArgumentException('Agregue al menos un producto terminado.');
        }

        return $lines;
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Indique la fecha y hora de producción.');
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new InvalidArgumentException('La fecha de producción no tiene un formato válido.');
        }

        return $date->format('Y-m-d H:i:00');
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
