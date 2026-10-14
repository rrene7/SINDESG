<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class DeliveryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM individual_deliveries WHERE status = 'draft') AS drafts,
                (SELECT COUNT(*) FROM individual_deliveries WHERE status = 'confirmed') AS confirmed,
                (
                    SELECT COALESCE(SUM(di.quantity), 0)
                    FROM individual_delivery_items di
                    INNER JOIN individual_deliveries d ON d.id = di.delivery_id
                    WHERE d.status = 'confirmed'
                ) AS delivered_quantity,
                (
                    SELECT COUNT(*)
                    FROM service_request_items i
                    INNER JOIN service_requests r ON r.id = i.request_id
                    INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
                    WHERE i.beneficiary_employee_id IS NOT NULL
                      AND i.quantity_received > i.quantity_delivered
                      AND r.status IN ('approved', 'partially_approved', 'fulfilled')
                      AND w.warehouse_type = 'P4'
                      AND w.is_active = 1
                ) AS pending_nominal
        SQL)->fetch();

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'delivered_quantity' => (float) ($row['delivered_quantity'] ?? 0),
            'pending_nominal' => (int) ($row['pending_nominal'] ?? 0),
        ];
    }

    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        return $this->pdo->query('
            SELECT
                d.id,
                d.delivery_code,
                d.p4_warehouse_id,
                d.delivered_at,
                d.status,
                d.recipient_employee_id,
                d.recipient_label_snapshot,
                d.recipient_document_snapshot,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                COUNT(di.id) AS item_count,
                COALESCE(SUM(di.quantity), 0) AS total_quantity
            FROM individual_deliveries d
            INNER JOIN warehouses w ON w.id = d.p4_warehouse_id
            LEFT JOIN individual_delivery_items di ON di.delivery_id = d.id
            GROUP BY d.id
            ORDER BY d.delivered_at DESC, d.id DESC
            LIMIT ' . $limit)->fetchAll();
    }

    public function activeP4s(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                id,
                code,
                name,
                organization_unit_id,
                organization_unit_name_snapshot
            FROM warehouses
            WHERE warehouse_type = 'P4'
              AND is_active = 1
            ORDER BY organization_unit_name_snapshot, name
        SQL)->fetchAll();
    }

    public function p4IsActive(int $warehouseId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM warehouses
             WHERE id = :id
               AND warehouse_type = "P4"
               AND is_active = 1'
        );
        $statement->execute(['id' => $warehouseId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function p4OrganizationUnitId(int $warehouseId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT organization_unit_id
             FROM warehouses
             WHERE id = :id
               AND warehouse_type = "P4"
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $warehouseId]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (int) $value;
    }

    public function pendingNominal(int $limit = 40): array
    {
        $limit = max(1, min($limit, 100));

        return $this->pdo->query('
            SELECT
                i.id AS request_item_id,
                i.product_variant_id,
                i.beneficiary_employee_id,
                i.beneficiary_label_snapshot,
                i.beneficiary_document_snapshot,
                i.beneficiary_position_snapshot,
                i.beneficiary_rank_snapshot,
                i.quantity_received,
                i.quantity_delivered,
                GREATEST(i.quantity_received - i.quantity_delivered, 0) AS pending_quantity,
                r.id AS request_id,
                r.request_code,
                w.id AS p4_warehouse_id,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                (
                    SELECT d.transfer_code
                    FROM distribution_items dit
                    INNER JOIN distributions d ON d.id = dit.distribution_id
                    WHERE dit.request_item_id = i.id
                      AND d.status = "received"
                    ORDER BY d.received_at DESC, d.id DESC
                    LIMIT 1
                ) AS transfer_code
            FROM service_request_items i
            INNER JOIN service_requests r ON r.id = i.request_id
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            INNER JOIN product_variants pv ON pv.id = i.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE i.beneficiary_employee_id IS NOT NULL
              AND i.quantity_received > i.quantity_delivered
              AND r.status IN ("approved", "partially_approved", "fulfilled")
              AND w.warehouse_type = "P4"
              AND w.is_active = 1
            ORDER BY r.requested_at, i.id
            LIMIT ' . $limit)->fetchAll();
    }

    public function pendingNominalLine(int $requestItemId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                i.id AS request_item_id,
                i.product_variant_id,
                i.beneficiary_employee_id,
                i.beneficiary_label_snapshot,
                i.beneficiary_document_snapshot,
                i.beneficiary_position_snapshot,
                i.beneficiary_rank_snapshot,
                i.beneficiary_unit_id_snapshot,
                i.beneficiary_unit_name_snapshot,
                i.quantity_received,
                i.quantity_delivered,
                GREATEST(i.quantity_received - i.quantity_delivered, 0) AS pending_quantity,
                r.id AS request_id,
                r.request_code,
                w.id AS p4_warehouse_id,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                (
                    SELECT d.transfer_code
                    FROM distribution_items dit
                    INNER JOIN distributions d ON d.id = dit.distribution_id
                    WHERE dit.request_item_id = i.id
                      AND d.status = 'received'
                    ORDER BY d.received_at DESC, d.id DESC
                    LIMIT 1
                ) AS transfer_code
            FROM service_request_items i
            INNER JOIN service_requests r ON r.id = i.request_id
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            INNER JOIN product_variants pv ON pv.id = i.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE i.id = :request_item_id
              AND i.beneficiary_employee_id IS NOT NULL
              AND i.quantity_received > i.quantity_delivered
              AND r.status IN ('approved', 'partially_approved', 'fulfilled')
              AND w.warehouse_type = 'P4'
              AND w.is_active = 1
            LIMIT 1
        SQL);
        $statement->execute(['request_item_id' => $requestItemId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function createDraft(
        array $data,
        ?array $linkedLine = null,
        ?float $linkedQuantity = null
    ): int {
        $this->pdo->beginTransaction();

        try {
            if (!$this->p4IsActive((int) $data['p4_warehouse_id'])) {
                throw new RuntimeException('El P4 seleccionado no está activo.');
            }

            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO individual_deliveries
                    (
                        delivery_code,
                        p4_warehouse_id,
                        recipient_employee_id,
                        recipient_label_snapshot,
                        recipient_document_snapshot,
                        recipient_position_snapshot,
                        recipient_rank_snapshot,
                        recipient_unit_id_snapshot,
                        recipient_unit_name_snapshot,
                        recipient_direction_snapshot,
                        recipient_office_snapshot,
                        delivered_by_employee_id,
                        delivered_by_label_snapshot,
                        delivered_by_document_snapshot,
                        delivered_by_position_snapshot,
                        delivered_by_rank_snapshot,
                        delivered_at,
                        document_number,
                        notes,
                        scope_override_enabled,
                        scope_override_reason,
                        status
                    )
                VALUES
                    (
                        :delivery_code,
                        :p4_warehouse_id,
                        :recipient_employee_id,
                        :recipient_label_snapshot,
                        :recipient_document_snapshot,
                        :recipient_position_snapshot,
                        :recipient_rank_snapshot,
                        :recipient_unit_id_snapshot,
                        :recipient_unit_name_snapshot,
                        :recipient_direction_snapshot,
                        :recipient_office_snapshot,
                        :delivered_by_employee_id,
                        :delivered_by_label_snapshot,
                        :delivered_by_document_snapshot,
                        :delivered_by_position_snapshot,
                        :delivered_by_rank_snapshot,
                        :delivered_at,
                        :document_number,
                        :notes,
                        :scope_override_enabled,
                        :scope_override_reason,
                        'draft'
                    )
            SQL);

            $statement->execute([
                'delivery_code' => 'TMP-DSP-' . strtoupper(bin2hex(random_bytes(6))),
                'p4_warehouse_id' => $data['p4_warehouse_id'],
                'recipient_employee_id' => $data['recipient_employee_id'],
                'recipient_label_snapshot' => $data['recipient_label_snapshot'],
                'recipient_document_snapshot' => $data['recipient_document_snapshot'],
                'recipient_position_snapshot' => $data['recipient_position_snapshot'],
                'recipient_rank_snapshot' => $data['recipient_rank_snapshot'],
                'recipient_unit_id_snapshot' => $data['recipient_unit_id_snapshot'],
                'recipient_unit_name_snapshot' => $data['recipient_unit_name_snapshot'],
                'recipient_direction_snapshot' => $data['recipient_direction_snapshot'],
                'recipient_office_snapshot' => $data['recipient_office_snapshot'],
                'delivered_by_employee_id' => $data['delivered_by_employee_id'],
                'delivered_by_label_snapshot' => $data['delivered_by_label_snapshot'],
                'delivered_by_document_snapshot' => $data['delivered_by_document_snapshot'],
                'delivered_by_position_snapshot' => $data['delivered_by_position_snapshot'],
                'delivered_by_rank_snapshot' => $data['delivered_by_rank_snapshot'],
                'delivered_at' => $data['delivered_at'],
                'document_number' => $data['document_number'],
                'notes' => $data['notes'],
                'scope_override_enabled' => $data['scope_override_enabled'],
                'scope_override_reason' => $data['scope_override_reason'],
            ]);

            $deliveryId = (int) $this->pdo->lastInsertId();
            $code = sprintf('DSP-%06d', $deliveryId);

            $this->pdo->prepare(
                'UPDATE individual_deliveries SET delivery_code = :code WHERE id = :id'
            )->execute(['code' => $code, 'id' => $deliveryId]);

            if ($linkedLine !== null && $linkedQuantity !== null) {
                $item = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO individual_delivery_items
                        (delivery_id, product_variant_id, request_item_id, quantity, notes)
                    VALUES
                        (:delivery_id, :product_variant_id, :request_item_id, :quantity, :notes)
                SQL);
                $item->execute([
                    'delivery_id' => $deliveryId,
                    'product_variant_id' => $linkedLine['product_variant_id'],
                    'request_item_id' => $linkedLine['request_item_id'],
                    'quantity' => number_format($linkedQuantity, 3, '.', ''),
                    'notes' => 'Entrega nominal de ' . $linkedLine['request_code'],
                ]);
            }

            $this->pdo->commit();
            return $deliveryId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function find(int $deliveryId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                d.*,
                w.code AS p4_code,
                w.name AS p4_internal_name,
                w.organization_unit_id AS p4_unit_id,
                w.organization_unit_name_snapshot AS p4_name,
                w.is_active AS p4_is_active
            FROM individual_deliveries d
            INNER JOIN warehouses w ON w.id = d.p4_warehouse_id
            WHERE d.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $deliveryId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function items(int $deliveryId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                di.*,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                p.id AS product_id,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                sr.request_code,
                (
                    SELECT d.transfer_code
                    FROM distribution_items dit
                    INNER JOIN distributions d ON d.id = dit.distribution_id
                    WHERE dit.request_item_id = di.request_item_id
                      AND d.status = 'received'
                    ORDER BY d.received_at DESC, d.id DESC
                    LIMIT 1
                ) AS transfer_code
            FROM individual_delivery_items di
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            LEFT JOIN service_request_items sri ON sri.id = di.request_item_id
            LEFT JOIN service_requests sr ON sr.id = sri.request_id
            WHERE di.delivery_id = :delivery_id
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, di.id
        SQL);
        $statement->execute(['delivery_id' => $deliveryId]);

        return $statement->fetchAll();
    }

    public function confirmationEvidence(int $deliveryId): array
    {
        $delivery = $this->find($deliveryId);
        if ($delivery === null) {
            return [
                'rows' => [],
                'expected' => 0,
                'verified' => 0,
                'all_verified' => false,
            ];
        }

        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                di.id AS delivery_item_id,
                di.quantity AS expected_quantity,
                di.request_item_id,
                p.id AS product_id,
                p.name AS product_name,
                pv.id AS variant_id,
                pv.internal_code AS variant_code,
                s.name AS size_name,
                sr.request_code,

                im.id AS movement_id,
                im.quantity AS movement_quantity,
                im.occurred_at AS movement_at,

                CAST(COALESCE(vs.quantity_on_hand, 0) AS DECIMAL(14,3)) AS p4_stock,
                CAST(COALESCE(sri.quantity_received, 0) AS DECIMAL(14,3)) AS request_received,
                CAST(COALESCE(sri.quantity_delivered, 0) AS DECIMAL(14,3)) AS request_delivered
            FROM individual_delivery_items di
            INNER JOIN individual_deliveries d ON d.id = di.delivery_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN service_request_items sri ON sri.id = di.request_item_id
            LEFT JOIN service_requests sr ON sr.id = sri.request_id
            LEFT JOIN inventory_movements im
                ON im.source_key = CONCAT(
                    'individual-delivery:',
                    di.delivery_id,
                    ':item:',
                    di.id
                )
               AND im.reference_type = 'individual_delivery'
               AND im.reference_id = di.delivery_id
               AND im.movement_kind = 'individual_delivery'
               AND im.direction = 'OUT'
               AND im.warehouse_id = d.p4_warehouse_id
               AND im.product_variant_id = di.product_variant_id
            LEFT JOIN v_inventory_stock vs
                ON vs.warehouse_id = d.p4_warehouse_id
               AND vs.product_variant_id = di.product_variant_id
            WHERE di.delivery_id = :delivery_id
            ORDER BY di.id
        SQL);
        $statement->execute(['delivery_id' => $deliveryId]);
        $rows = $statement->fetchAll();

        $verified = 0;
        foreach ($rows as &$row) {
            $movementMatches =
                !empty($row['movement_id'])
                && abs(
                    (float) ($row['movement_quantity'] ?? 0)
                    - (float) ($row['expected_quantity'] ?? 0)
                ) < 0.0005;

            $requestMatches = true;
            if ($row['request_item_id'] !== null) {
                $requestMatches =
                    (float) ($row['request_delivered'] ?? 0)
                    <= (float) ($row['request_received'] ?? 0) + 0.0005;
            }

            $row['verified'] = $delivery['status'] === 'confirmed'
                && $movementMatches
                && $requestMatches;

            if ($row['verified']) {
                $verified++;
            }
        }
        unset($row);

        return [
            'rows' => $rows,
            'expected' => count($rows),
            'verified' => $verified,
            'all_verified' => $delivery['status'] === 'confirmed'
                && $rows !== []
                && $verified === count($rows),
        ];
    }

    public function stockOptionsForP4(int $warehouseId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                pv.id,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(vs.quantity_on_hand AS DECIMAL(14,3)) AS physical_stock,
                CAST(COALESCE((
                    SELECT SUM(GREATEST(i.quantity_received - i.quantity_delivered, 0))
                    FROM service_request_items i
                    INNER JOIN service_requests r ON r.id = i.request_id
                    WHERE r.p4_warehouse_id = :reserved_warehouse_id
                      AND i.product_variant_id = pv.id
                      AND i.beneficiary_employee_id IS NOT NULL
                      AND i.quantity_received > i.quantity_delivered
                      AND r.status IN ('approved', 'partially_approved', 'fulfilled')
                ), 0) AS DECIMAL(14,3)) AS nominal_reserved,
                CAST(GREATEST(
                    vs.quantity_on_hand
                    -
                    COALESCE((
                        SELECT SUM(GREATEST(i2.quantity_received - i2.quantity_delivered, 0))
                        FROM service_request_items i2
                        INNER JOIN service_requests r2 ON r2.id = i2.request_id
                        WHERE r2.p4_warehouse_id = :available_warehouse_id
                          AND i2.product_variant_id = pv.id
                          AND i2.beneficiary_employee_id IS NOT NULL
                          AND i2.quantity_received > i2.quantity_delivered
                          AND r2.status IN ('approved', 'partially_approved', 'fulfilled')
                    ), 0),
                    0
                ) AS DECIMAL(14,3)) AS manual_available
            FROM v_inventory_stock vs
            INNER JOIN warehouses w ON w.id = vs.warehouse_id
            INNER JOIN product_variants pv ON pv.id = vs.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE vs.warehouse_id = :warehouse_id
              AND w.warehouse_type = 'P4'
              AND w.is_active = 1
              AND vs.quantity_on_hand > 0
              AND pv.status = 'active'
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, pv.id
        SQL);
        $statement->execute([
            'reserved_warehouse_id' => $warehouseId,
            'available_warehouse_id' => $warehouseId,
            'warehouse_id' => $warehouseId,
        ]);

        return $statement->fetchAll();
    }

    public function variantIsActive(int $variantId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM product_variants WHERE id = :id AND status = "active"'
        );
        $statement->execute(['id' => $variantId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function manualAvailable(int $warehouseId, int $variantId): float
    {
        $physical = $this->physicalStock($warehouseId, $variantId);
        $reserved = $this->nominalReserved($warehouseId, $variantId);

        return max(0.0, $physical - $reserved);
    }

    public function hasDuplicateVariant(int $deliveryId, int $variantId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM individual_delivery_items
             WHERE delivery_id = :delivery_id
               AND product_variant_id = :variant_id'
        );
        $statement->execute([
            'delivery_id' => $deliveryId,
            'variant_id' => $variantId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function addItem(int $deliveryId, int $variantId, float $quantity, ?string $notes): void
    {
        $delivery = $this->find($deliveryId);
        if ($delivery === null || $delivery['status'] !== 'draft') {
            throw new RuntimeException('Solo se pueden agregar artículos a despachos en borrador.');
        }

        if ($this->manualAvailable((int) $delivery['p4_warehouse_id'], $variantId) + 0.000001 < $quantity) {
            throw new RuntimeException('La cantidad supera el stock libre del P4. Las unidades nominales reservadas no pueden utilizarse.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO individual_delivery_items
                (delivery_id, product_variant_id, request_item_id, quantity, notes)
             VALUES
                (:delivery_id, :product_variant_id, NULL, :quantity, :notes)'
        );
        $statement->execute([
            'delivery_id' => $deliveryId,
            'product_variant_id' => $variantId,
            'quantity' => number_format($quantity, 3, '.', ''),
            'notes' => $notes,
        ]);
    }

    public function removeItem(int $deliveryId, int $itemId): void
    {
        $delivery = $this->find($deliveryId);
        if ($delivery === null || $delivery['status'] !== 'draft') {
            throw new RuntimeException('Solo se pueden quitar artículos de despachos en borrador.');
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM individual_delivery_items
             WHERE id = :id
               AND delivery_id = :delivery_id'
        );
        $statement->execute([
            'id' => $itemId,
            'delivery_id' => $deliveryId,
        ]);
    }

    public function confirm(int $deliveryId): array
    {
        $this->pdo->beginTransaction();

        try {
            $delivery = $this->lockDelivery($deliveryId);

            if ($delivery['status'] === 'confirmed') {
                $this->pdo->commit();
                return ['already_confirmed' => true];
            }

            if ($delivery['status'] !== 'draft') {
                throw new RuntimeException('Solo se pueden confirmar despachos en borrador.');
            }

            $warehouse = $this->lockWarehouse((int) $delivery['p4_warehouse_id']);
            if ($warehouse['warehouse_type'] !== 'P4' || (int) $warehouse['is_active'] !== 1) {
                throw new RuntimeException('El P4 del despacho no está activo.');
            }

            $items = $this->lockedItems($deliveryId);
            if ($items === []) {
                throw new RuntimeException('Agregue al menos un artículo antes de confirmar el despacho.');
            }

            $requiredByVariant = [];
            $manualByVariant = [];
            $linkedRows = [];

            foreach ($items as $item) {
                $variantId = (int) $item['product_variant_id'];
                $quantity = (float) $item['quantity'];
                $requiredByVariant[$variantId] = ($requiredByVariant[$variantId] ?? 0.0) + $quantity;

                if ($item['request_item_id'] === null) {
                    $manualByVariant[$variantId] = ($manualByVariant[$variantId] ?? 0.0) + $quantity;
                    continue;
                }

                $requestItem = $this->lockRequestItem((int) $item['request_item_id']);

                if ((int) $requestItem['p4_warehouse_id'] !== (int) $delivery['p4_warehouse_id']) {
                    throw new RuntimeException('La entrega nominal pertenece a otro P4.');
                }

                if ((int) $requestItem['beneficiary_employee_id'] !== (int) $delivery['recipient_employee_id']) {
                    throw new RuntimeException('El beneficiario de la solicitud no coincide con el funcionario receptor.');
                }

                if ((int) $requestItem['product_variant_id'] !== $variantId) {
                    throw new RuntimeException('La variante del despacho no coincide con la solicitud nominal.');
                }

                $pending = max(
                    0.0,
                    (float) $requestItem['quantity_received'] - (float) $requestItem['quantity_delivered']
                );

                if ($quantity > $pending + 0.000001) {
                    throw new RuntimeException('La cantidad nominal supera lo recibido y pendiente de entregar al funcionario.');
                }

                $linkedRows[(int) $item['id']] = $requestItem;
            }

            foreach ($requiredByVariant as $variantId => $quantity) {
                $physical = $this->physicalStockForUpdate(
                    (int) $delivery['p4_warehouse_id'],
                    (int) $variantId
                );

                if ($physical + 0.000001 < $quantity) {
                    throw new RuntimeException(sprintf(
                        'Stock físico insuficiente en el P4 para la variante #%d. Disponible: %s; requerido: %s.',
                        (int) $variantId,
                        number_format($physical, 3, '.', ''),
                        number_format($quantity, 3, '.', '')
                    ));
                }
            }

            foreach ($manualByVariant as $variantId => $quantity) {
                $available = $this->manualAvailableForUpdate(
                    (int) $delivery['p4_warehouse_id'],
                    (int) $variantId
                );

                if ($available + 0.000001 < $quantity) {
                    throw new RuntimeException(sprintf(
                        'Stock libre insuficiente en el P4 para la variante #%d. Libre: %s; requerido manual: %s.',
                        (int) $variantId,
                        number_format($available, 3, '.', ''),
                        number_format($quantity, 3, '.', '')
                    ));
                }
            }

            $movement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO inventory_movements
                    (
                        warehouse_id,
                        product_variant_id,
                        movement_kind,
                        direction,
                        quantity,
                        occurred_at,
                        reference_type,
                        reference_id,
                        reference_code,
                        document_number,
                        notes,
                        actor_label,
                        source_key
                    )
                VALUES
                    (
                        :warehouse_id,
                        :product_variant_id,
                        'individual_delivery',
                        'OUT',
                        :quantity,
                        :occurred_at,
                        'individual_delivery',
                        :reference_id,
                        :reference_code,
                        :document_number,
                        :notes,
                        :actor_label,
                        :source_key
                    )
                ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)
            SQL);

            foreach ($items as $item) {
                $movement->execute([
                    'warehouse_id' => $delivery['p4_warehouse_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'occurred_at' => $delivery['delivered_at'],
                    'reference_id' => $deliveryId,
                    'reference_code' => $delivery['delivery_code'],
                    'document_number' => $delivery['document_number'],
                    'notes' => $item['notes'],
                    'actor_label' => $delivery['delivered_by_label_snapshot'],
                    'source_key' => sprintf(
                        'individual-delivery:%d:item:%d',
                        $deliveryId,
                        (int) $item['id']
                    ),
                ]);

                if ($item['request_item_id'] !== null) {
                    $this->pdo->prepare(
                        'UPDATE service_request_items
                         SET quantity_delivered = quantity_delivered + :quantity
                         WHERE id = :request_item_id'
                    )->execute([
                        'quantity' => $item['quantity'],
                        'request_item_id' => $item['request_item_id'],
                    ]);
                }
            }

            $this->pdo->prepare(
                'UPDATE individual_deliveries
                 SET status = "confirmed", confirmed_at = NOW()
                 WHERE id = :id'
            )->execute(['id' => $deliveryId]);

            $this->pdo->commit();
            return ['already_confirmed' => false];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $deliveryId): void
    {
        $this->pdo->beginTransaction();

        try {
            $delivery = $this->lockDelivery($deliveryId);

            if ($delivery['status'] !== 'draft') {
                throw new RuntimeException('Solo un despacho en borrador puede cancelarse.');
            }

            $this->pdo->prepare(
                'UPDATE individual_deliveries
                 SET status = "cancelled", cancelled_at = NOW()
                 WHERE id = :id'
            )->execute(['id' => $deliveryId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function historyForEmployee(int $employeeId, int $limit = 100, ?array $allowedP4Ids = null): array
    {
        $limit = max(1, min($limit, 200));
        if ($allowedP4Ids !== null && $allowedP4Ids === []) {
            return [];
        }
        $ids = $allowedP4Ids === null ? [] : array_values(array_unique(array_map('intval', $allowedP4Ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        $scopeSql = $allowedP4Ids === null ? '' : ' AND d.p4_warehouse_id IN (' . implode(',', $ids) . ')';

        $statement = $this->pdo->prepare('
            SELECT
                d.id AS delivery_id,
                d.delivery_code,
                d.delivered_at,
                d.recipient_employee_id,
                d.recipient_label_snapshot,
                d.recipient_document_snapshot,
                d.recipient_position_snapshot,
                d.recipient_rank_snapshot,
                d.recipient_unit_name_snapshot,
                d.recipient_direction_snapshot,
                d.recipient_office_snapshot,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                di.quantity,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                sr.request_code
            FROM individual_deliveries d
            INNER JOIN individual_delivery_items di ON di.delivery_id = d.id
            INNER JOIN warehouses w ON w.id = d.p4_warehouse_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            LEFT JOIN service_request_items sri ON sri.id = di.request_item_id
            LEFT JOIN service_requests sr ON sr.id = sri.request_id
            WHERE d.recipient_employee_id = :employee_id
              AND d.status = "confirmed"
            ' . $scopeSql . '
            ORDER BY d.delivered_at DESC, d.id DESC, di.id
            LIMIT ' . $limit
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetchAll();
    }

    /**
     * Última entrega individual CONFIRMADA del mismo ARTÍCULO (cualquier talla
     * y P4), para facilitar la decisión humana del P4 solicitante.
     * Ausencia de datos NO prueba que no hubo dotaciones fuera de SINDESG.
     *
     * @param list<int> $employeeIds
     * @return array<int,string> employee_id => fecha DATETIME
     */
    public function lastConfirmedDatesForProduct(int $productId, array $employeeIds): array
    {
        if ($productId <= 0 || count($employeeIds) > 100) {
            throw new \InvalidArgumentException('Producto o lote de funcionarios no válido.');
        }
        $ids = [];
        foreach ($employeeIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                throw new \InvalidArgumentException('Funcionario no válido.');
            }
            $ids[$id] = $id;
        }
        if (!$ids) {
            return [];
        }

        $bind = ['product_id' => $productId];
        $holders = [];
        foreach (array_values($ids) as $idx => $employeeId) {
            $key = 'person' . $idx;
            $holders[] = ':' . $key;
            $bind[$key] = $employeeId;
        }
        $sql = '
            SELECT d.recipient_employee_id AS employee_id,
                   MAX(d.delivered_at) AS last_delivered_at
            FROM individual_deliveries d
            INNER JOIN individual_delivery_items di ON di.delivery_id = d.id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            WHERE d.status = "confirmed"
              AND pv.product_id = :product_id
              AND d.recipient_employee_id IN (' . implode(', ', $holders) . ')
            GROUP BY d.recipient_employee_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        $dates = [];
        foreach ($stmt->fetchAll() as $row) {
            $dates[(int) $row['employee_id']] = (string) $row['last_delivered_at'];
        }
        return $dates;
    }

    public function historySummaryForEmployee(int $employeeId, int $limit = 12, ?array $allowedP4Ids = null): array
    {
        $rows = $this->historyForEmployee($employeeId, $limit, $allowedP4Ids);

        return array_map(
            static fn (array $row): array => [
                'delivery_id' => (int) $row['delivery_id'],
                'delivery_code' => (string) $row['delivery_code'],
                'delivered_at' => (string) $row['delivered_at'],
                'product_name' => (string) $row['product_name'],
                'variant_code' => (string) $row['variant_code'],
                'size_name' => (string) ($row['size_name'] ?? ''),
                'quantity' => (float) $row['quantity'],
                'p4_code' => (string) $row['p4_code'],
                'p4_name' => (string) ($row['p4_name'] ?? ''),
            ],
            $rows
        );
    }

    private function lockDelivery(int $deliveryId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM individual_deliveries WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $deliveryId]);
        $delivery = $statement->fetch();

        if (!$delivery) {
            throw new RuntimeException('El despacho no existe.');
        }

        return $delivery;
    }

    private function lockWarehouse(int $warehouseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM warehouses WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $warehouseId]);
        $warehouse = $statement->fetch();

        if (!$warehouse) {
            throw new RuntimeException('El P4 solicitado no existe.');
        }

        return $warehouse;
    }

    private function lockedItems(int $deliveryId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM individual_delivery_items
             WHERE delivery_id = :delivery_id
             ORDER BY id
             FOR UPDATE'
        );
        $statement->execute(['delivery_id' => $deliveryId]);

        return $statement->fetchAll();
    }

    private function lockRequestItem(int $requestItemId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                i.*,
                r.p4_warehouse_id,
                r.request_code
            FROM service_request_items i
            INNER JOIN service_requests r ON r.id = i.request_id
            WHERE i.id = :id
            FOR UPDATE
        SQL);
        $statement->execute(['id' => $requestItemId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new RuntimeException('El renglón de solicitud vinculado no existe.');
        }

        return $row;
    }

    private function physicalStock(int $warehouseId, int $variantId): float
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(quantity_on_hand, 0)
             FROM v_inventory_stock
             WHERE warehouse_id = :warehouse_id
               AND product_variant_id = :variant_id
             LIMIT 1'
        );
        $statement->execute([
            'warehouse_id' => $warehouseId,
            'variant_id' => $variantId,
        ]);

        return (float) ($statement->fetchColumn() ?: 0);
    }

    private function physicalStockForUpdate(int $warehouseId, int $variantId): float
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT COALESCE(SUM(
                CASE
                    WHEN direction = 'IN' THEN quantity
                    WHEN direction = 'OUT' THEN -quantity
                    ELSE 0
                END
            ), 0)
            FROM inventory_movements
            WHERE warehouse_id = :warehouse_id
              AND product_variant_id = :variant_id
        SQL);
        $statement->execute([
            'warehouse_id' => $warehouseId,
            'variant_id' => $variantId,
        ]);

        return (float) $statement->fetchColumn();
    }

    private function nominalReserved(int $warehouseId, int $variantId): float
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT COALESCE(SUM(GREATEST(i.quantity_received - i.quantity_delivered, 0)), 0)
            FROM service_request_items i
            INNER JOIN service_requests r ON r.id = i.request_id
            WHERE r.p4_warehouse_id = :warehouse_id
              AND i.product_variant_id = :variant_id
              AND i.beneficiary_employee_id IS NOT NULL
              AND i.quantity_received > i.quantity_delivered
              AND r.status IN ('approved', 'partially_approved', 'fulfilled')
        SQL);
        $statement->execute([
            'warehouse_id' => $warehouseId,
            'variant_id' => $variantId,
        ]);

        return (float) $statement->fetchColumn();
    }

    private function manualAvailableForUpdate(int $warehouseId, int $variantId): float
    {
        return max(
            0.0,
            $this->physicalStockForUpdate($warehouseId, $variantId)
            - $this->nominalReserved($warehouseId, $variantId)
        );
    }
}
