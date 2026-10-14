<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class DistributionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM distributions WHERE status = 'prepared') AS prepared,
                (SELECT COUNT(*) FROM distributions WHERE status = 'authorized') AS authorized,
                (SELECT COUNT(*) FROM distributions WHERE status = 'dispatched') AS in_transit,
                (SELECT COUNT(*) FROM distributions WHERE status = 'received') AS received,
                (
                    SELECT COALESCE(SUM(vs.quantity_on_hand), 0)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.code = 'TRANSIT-SG'
                ) AS transit_stock
        SQL)->fetch();

        return [
            'prepared' => (int) ($row['prepared'] ?? 0),
            'authorized' => (int) ($row['authorized'] ?? 0),
            'in_transit' => (int) ($row['in_transit'] ?? 0),
            'received' => (int) ($row['received'] ?? 0),
            'transit_stock' => (float) ($row['transit_stock'] ?? 0),
        ];
    }

    public function recent(int $limit = 60): array
    {
        $limit = max(1, min($limit, 100));

        return $this->pdo->query('
            SELECT
                d.id,
                d.transfer_code,
                d.destination_warehouse_id,
                d.status,
                d.prepared_at,
                d.authorized_at,
                d.dispatched_at,
                d.received_at,
                r.request_code,
                wd.code AS p4_code,
                wd.organization_unit_name_snapshot AS p4_name,
                COALESCE(SUM(di.quantity), 0) AS total_quantity,
                COUNT(di.id) AS item_count
            FROM distributions d
            INNER JOIN service_requests r ON r.id = d.request_id
            INNER JOIN warehouses wd ON wd.id = d.destination_warehouse_id
            LEFT JOIN distribution_items di ON di.distribution_id = d.id
            GROUP BY d.id
            ORDER BY d.id DESC
            LIMIT ' . $limit)->fetchAll();
    }

    public function readyRequests(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                r.id,
                r.request_code,
                r.status,
                r.requested_at,
                w.id AS p4_warehouse_id,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                COALESCE(SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0)), 0) AS remaining_quantity,
                COUNT(CASE WHEN i.quantity_approved > i.quantity_dispatched THEN 1 END) AS remaining_lines
            FROM service_requests r
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            INNER JOIN service_request_items i ON i.request_id = r.id
            WHERE r.status IN ('approved', 'partially_approved')
              AND w.warehouse_type = 'P4'
              AND w.is_active = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM distributions d
                    WHERE d.request_id = r.id
                      AND d.status IN ('prepared', 'authorized', 'dispatched')
              )
            GROUP BY r.id
            HAVING remaining_quantity > 0
            ORDER BY r.requested_at, r.id
        SQL)->fetchAll();
    }

    public function requestForPreparation(int $requestId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                r.*,
                w.id AS p4_warehouse_id,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                w.is_active AS p4_is_active
            FROM service_requests r
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            WHERE r.id = :id
              AND r.status IN ('approved', 'partially_approved')
            LIMIT 1
        SQL);
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function requestItemsForPreparation(int $requestId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                i.id AS request_item_id,
                i.product_variant_id,
                i.quantity_requested,
                i.quantity_approved,
                i.quantity_dispatched,
                i.quantity_received,
                GREATEST(i.quantity_approved - i.quantity_dispatched, 0) AS remaining_quantity,
                i.beneficiary_label_snapshot,
                pv.internal_code AS variant_code,
                p.id AS product_id,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(COALESCE((
                    SELECT vs.quantity_on_hand
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.code = 'CENTRAL-SG'
                      AND vs.product_variant_id = i.product_variant_id
                    LIMIT 1
                ), 0) AS DECIMAL(14,3)) AS central_stock
            FROM service_request_items i
            INNER JOIN product_variants pv ON pv.id = i.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE i.request_id = :request_id
              AND i.quantity_approved > i.quantity_dispatched
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, i.id
        SQL);
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    public function hasOpenDistribution(int $requestId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM distributions
             WHERE request_id = :request_id
               AND status IN ("prepared", "authorized", "dispatched")'
        );
        $statement->execute(['request_id' => $requestId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createPrepared(
        int $requestId,
        array $preparedBy,
        array $quantities,
        ?string $documentNumber,
        ?string $notes
    ): int {
        $this->pdo->beginTransaction();

        try {
            $request = $this->lockRequest($requestId);

            if (!in_array($request['status'], ['approved', 'partially_approved'], true)) {
                throw new RuntimeException('La solicitud ya no está disponible para distribución.');
            }

            if ($this->hasOpenDistribution($requestId)) {
                throw new RuntimeException(
                    'La solicitud ya tiene una transferencia abierta. Recíbala o cancélela antes de preparar otra.'
                );
            }

            $destination = $this->lockWarehouse((int) $request['p4_warehouse_id']);
            if ($destination['warehouse_type'] !== 'P4' || (int) $destination['is_active'] !== 1) {
                throw new RuntimeException('El P4 destino no está activo.');
            }

            $sourceId = $this->warehouseIdByCode('CENTRAL-SG', true);
            $transitId = $this->warehouseIdByCode('TRANSIT-SG', true);

            $itemsStatement = $this->pdo->prepare(
                'SELECT id, product_variant_id, quantity_approved, quantity_dispatched
                 FROM service_request_items
                 WHERE request_id = :request_id
                 ORDER BY id
                 FOR UPDATE'
            );
            $itemsStatement->execute(['request_id' => $requestId]);
            $requestItems = $itemsStatement->fetchAll();

            $selected = [];
            foreach ($requestItems as $item) {
                $itemId = (int) $item['id'];
                $remaining = max(
                    0.0,
                    (float) $item['quantity_approved'] - (float) $item['quantity_dispatched']
                );
                $quantity = (float) ($quantities[$itemId] ?? 0);

                if ($quantity < 0 || $quantity > $remaining + 0.000001) {
                    throw new RuntimeException(
                        'Una cantidad preparada supera el saldo aprobado pendiente.'
                    );
                }

                if ($quantity > 0.000001) {
                    $selected[] = [
                        'request_item_id' => $itemId,
                        'product_variant_id' => (int) $item['product_variant_id'],
                        'quantity' => number_format($quantity, 3, '.', ''),
                    ];
                }
            }

            if ($selected === []) {
                throw new RuntimeException('Seleccione al menos una cantidad para preparar.');
            }

            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO distributions
                    (
                        transfer_code,
                        request_id,
                        source_warehouse_id,
                        transit_warehouse_id,
                        destination_warehouse_id,
                        document_number,
                        status,
                        notes,
                        prepared_by_employee_id,
                        prepared_by_label_snapshot,
                        prepared_by_document_snapshot,
                        prepared_by_position_snapshot,
                        prepared_by_rank_snapshot,
                        prepared_at
                    )
                VALUES
                    (
                        :transfer_code,
                        :request_id,
                        :source_warehouse_id,
                        :transit_warehouse_id,
                        :destination_warehouse_id,
                        :document_number,
                        'prepared',
                        :notes,
                        :prepared_by_employee_id,
                        :prepared_by_label_snapshot,
                        :prepared_by_document_snapshot,
                        :prepared_by_position_snapshot,
                        :prepared_by_rank_snapshot,
                        NOW()
                    )
            SQL);
            $insert->execute([
                'transfer_code' => 'TMP-TRF-' . strtoupper(bin2hex(random_bytes(6))),
                'request_id' => $requestId,
                'source_warehouse_id' => $sourceId,
                'transit_warehouse_id' => $transitId,
                'destination_warehouse_id' => $destination['id'],
                'document_number' => $documentNumber,
                'notes' => $notes,
                'prepared_by_employee_id' => $preparedBy['employee_id'],
                'prepared_by_label_snapshot' => $preparedBy['display_name'],
                'prepared_by_document_snapshot' => $preparedBy['document_number'],
                'prepared_by_position_snapshot' => $preparedBy['position_number'],
                'prepared_by_rank_snapshot' => $preparedBy['rank_name'],
            ]);

            $distributionId = (int) $this->pdo->lastInsertId();
            $code = sprintf('TRF-%06d', $distributionId);

            $this->pdo->prepare(
                'UPDATE distributions SET transfer_code = :code WHERE id = :id'
            )->execute(['code' => $code, 'id' => $distributionId]);

            $itemInsert = $this->pdo->prepare(
                'INSERT INTO distribution_items
                    (distribution_id, request_item_id, product_variant_id, quantity)
                 VALUES
                    (:distribution_id, :request_item_id, :product_variant_id, :quantity)'
            );

            foreach ($selected as $item) {
                $itemInsert->execute([
                    'distribution_id' => $distributionId,
                    'request_item_id' => $item['request_item_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            $this->pdo->commit();
            return $distributionId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function find(int $distributionId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                d.*,
                r.request_code,
                r.status AS request_status,
                ws.code AS source_code,
                ws.name AS source_name,
                wt.code AS transit_code,
                wt.name AS transit_name,
                wd.code AS destination_code,
                wd.name AS destination_internal_name,
                wd.organization_unit_name_snapshot AS destination_name,
                wd.is_active AS destination_is_active
            FROM distributions d
            INNER JOIN service_requests r ON r.id = d.request_id
            INNER JOIN warehouses ws ON ws.id = d.source_warehouse_id
            INNER JOIN warehouses wt ON wt.id = d.transit_warehouse_id
            INNER JOIN warehouses wd ON wd.id = d.destination_warehouse_id
            WHERE d.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $distributionId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function items(int $distributionId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                di.*,
                sri.beneficiary_label_snapshot,
                sri.beneficiary_document_snapshot,
                pv.internal_code AS variant_code,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name
            FROM distribution_items di
            INNER JOIN service_request_items sri ON sri.id = di.request_item_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE di.distribution_id = :distribution_id
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, di.id
        SQL);
        $statement->execute(['distribution_id' => $distributionId]);

        return $statement->fetchAll();
    }

    public function movementEvidence(int $distributionId): array
    {
        $distribution = $this->find($distributionId);
        if ($distribution === null) {
            return [
                'rows' => [],
                'expected' => 0,
                'verified' => 0,
                'all_verified' => false,
            ];
        }

        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                di.id AS distribution_item_id,
                di.quantity AS expected_quantity,
                p.id AS product_id,
                p.name AS product_name,
                pv.id AS variant_id,
                pv.internal_code AS variant_code,
                s.name AS size_name,

                dc.id AS dispatch_central_id,
                dc.quantity AS dispatch_central_quantity,
                dt.id AS dispatch_transit_id,
                dt.quantity AS dispatch_transit_quantity,
                rt.id AS receive_transit_id,
                rt.quantity AS receive_transit_quantity,
                rp.id AS receive_p4_id,
                rp.quantity AS receive_p4_quantity,

                CAST(COALESCE(vsc.quantity_on_hand, 0) AS DECIMAL(14,3)) AS central_stock,
                CAST(COALESCE(vst.quantity_on_hand, 0) AS DECIMAL(14,3)) AS transit_stock,
                CAST(COALESCE(vsp.quantity_on_hand, 0) AS DECIMAL(14,3)) AS p4_stock
            FROM distribution_items di
            INNER JOIN distributions d ON d.id = di.distribution_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN sizes s ON s.id = pv.size_id

            LEFT JOIN inventory_movements dc
                ON dc.source_key = CONCAT('distribution:', di.distribution_id, ':dispatch:central:', di.id)
            LEFT JOIN inventory_movements dt
                ON dt.source_key = CONCAT('distribution:', di.distribution_id, ':dispatch:transit:', di.id)
            LEFT JOIN inventory_movements rt
                ON rt.source_key = CONCAT('distribution:', di.distribution_id, ':receive:transit:', di.id)
            LEFT JOIN inventory_movements rp
                ON rp.source_key = CONCAT('distribution:', di.distribution_id, ':receive:p4:', di.id)

            LEFT JOIN v_inventory_stock vsc
                ON vsc.warehouse_id = d.source_warehouse_id
               AND vsc.product_variant_id = di.product_variant_id
            LEFT JOIN v_inventory_stock vst
                ON vst.warehouse_id = d.transit_warehouse_id
               AND vst.product_variant_id = di.product_variant_id
            LEFT JOIN v_inventory_stock vsp
                ON vsp.warehouse_id = d.destination_warehouse_id
               AND vsp.product_variant_id = di.product_variant_id

            WHERE di.distribution_id = :distribution_id
            ORDER BY di.id
        SQL);
        $statement->execute(['distribution_id' => $distributionId]);
        $rows = $statement->fetchAll();

        $verified = 0;
        foreach ($rows as &$row) {
            $expected = (float) ($row['expected_quantity'] ?? 0);

            $dispatchBalanced =
                !empty($row['dispatch_central_id'])
                && !empty($row['dispatch_transit_id'])
                && abs((float) ($row['dispatch_central_quantity'] ?? 0) - $expected) < 0.0005
                && abs((float) ($row['dispatch_transit_quantity'] ?? 0) - $expected) < 0.0005;

            $receiveBalanced =
                !empty($row['receive_transit_id'])
                && !empty($row['receive_p4_id'])
                && abs((float) ($row['receive_transit_quantity'] ?? 0) - $expected) < 0.0005
                && abs((float) ($row['receive_p4_quantity'] ?? 0) - $expected) < 0.0005;

            if ($distribution['status'] === 'dispatched') {
                $row['verified'] = $dispatchBalanced;
                $row['verification_label'] = $dispatchBalanced
                    ? 'En tránsito verificado'
                    : 'Revisar';
            } elseif ($distribution['status'] === 'received') {
                $row['verified'] = $dispatchBalanced && $receiveBalanced;
                $row['verification_label'] = $row['verified']
                    ? 'P4 verificado'
                    : 'Revisar';
            } else {
                $row['verified'] = false;
                $row['verification_label'] = 'Pendiente de movimiento';
            }

            if ($row['verified']) {
                $verified++;
            }
        }
        unset($row);

        $isMovementStage = in_array(
            $distribution['status'],
            ['dispatched', 'received'],
            true
        );

        return [
            'rows' => $rows,
            'expected' => count($rows),
            'verified' => $verified,
            'all_verified' => $isMovementStage
                && $rows !== []
                && $verified === count($rows),
        ];
    }

    public function authorize(
        int $distributionId,
        array $authorizedBy,
        ?string $notes
    ): void {
        $this->pdo->beginTransaction();

        try {
            $distribution = $this->lockDistribution($distributionId);
            if ($distribution['status'] !== 'prepared') {
                throw new RuntimeException('Solo una transferencia preparada puede autorizarse.');
            }

            $this->lockWarehouse((int) $distribution['source_warehouse_id']);
            $destination = $this->lockWarehouse((int) $distribution['destination_warehouse_id']);
            if ((int) $destination['is_active'] !== 1) {
                throw new RuntimeException('El P4 destino está inactivo.');
            }

            foreach ($this->lockedItems($distributionId) as $item) {
                $stock = $this->stock(
                    (int) $distribution['source_warehouse_id'],
                    (int) $item['product_variant_id']
                );
                if ($stock + 0.000001 < (float) $item['quantity']) {
                    throw new RuntimeException(
                        'No existe stock suficiente en el Almacén Central para autorizar toda la transferencia.'
                    );
                }
            }

            $statement = $this->pdo->prepare(<<<'SQL'
                UPDATE distributions
                SET
                    status = 'authorized',
                    authorized_by_employee_id = :employee_id,
                    authorized_by_label_snapshot = :label,
                    authorized_at = NOW(),
                    authorization_notes = :notes
                WHERE id = :id
            SQL);
            $statement->execute([
                'employee_id' => $authorizedBy['employee_id'],
                'label' => $authorizedBy['display_name'],
                'notes' => $notes,
                'id' => $distributionId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function dispatch(
        int $distributionId,
        array $deliveredBy,
        ?string $notes
    ): void {
        $this->pdo->beginTransaction();

        try {
            $distribution = $this->lockDistribution($distributionId);
            if ($distribution['status'] !== 'authorized') {
                throw new RuntimeException('Solo una transferencia autorizada puede despacharse.');
            }

            $sourceId = (int) $distribution['source_warehouse_id'];
            $transitId = (int) $distribution['transit_warehouse_id'];
            $this->lockWarehouse($sourceId);
            $this->lockWarehouse($transitId);

            $items = $this->lockedItems($distributionId);
            foreach ($items as $item) {
                if ($this->stock($sourceId, (int) $item['product_variant_id']) + 0.000001 < (float) $item['quantity']) {
                    throw new RuntimeException(
                        'El stock del Almacén Central cambió y ya no permite despachar toda la transferencia.'
                    );
                }
            }

            $movement = $this->movementStatement();

            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                $quantity = $item['quantity'];
                $variantId = (int) $item['product_variant_id'];

                $movement->execute([
                    'warehouse_id' => $sourceId,
                    'product_variant_id' => $variantId,
                    'movement_kind' => 'transfer_dispatch',
                    'direction' => 'OUT',
                    'quantity' => $quantity,
                    'reference_id' => $distributionId,
                    'reference_code' => $distribution['transfer_code'],
                    'document_number' => $distribution['document_number'],
                    'notes' => $notes,
                    'actor_label' => $deliveredBy['display_name'],
                    'source_key' => sprintf('distribution:%d:dispatch:central:%d', $distributionId, $itemId),
                ]);

                $movement->execute([
                    'warehouse_id' => $transitId,
                    'product_variant_id' => $variantId,
                    'movement_kind' => 'transfer_in_transit',
                    'direction' => 'IN',
                    'quantity' => $quantity,
                    'reference_id' => $distributionId,
                    'reference_code' => $distribution['transfer_code'],
                    'document_number' => $distribution['document_number'],
                    'notes' => $notes,
                    'actor_label' => $deliveredBy['display_name'],
                    'source_key' => sprintf('distribution:%d:dispatch:transit:%d', $distributionId, $itemId),
                ]);

                $this->pdo->prepare(
                    'UPDATE service_request_items
                     SET quantity_dispatched = quantity_dispatched + :quantity
                     WHERE id = :request_item_id'
                )->execute([
                    'quantity' => $quantity,
                    'request_item_id' => $item['request_item_id'],
                ]);
            }

            $this->pdo->prepare(<<<'SQL'
                UPDATE distributions
                SET
                    status = 'dispatched',
                    delivered_by_employee_id = :employee_id,
                    delivered_by_label_snapshot = :label,
                    dispatched_at = NOW(),
                    dispatch_notes = :notes
                WHERE id = :id
            SQL)->execute([
                'employee_id' => $deliveredBy['employee_id'],
                'label' => $deliveredBy['display_name'],
                'notes' => $notes,
                'id' => $distributionId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function receive(
        int $distributionId,
        array $receivedBy,
        ?string $notes
    ): void {
        $this->pdo->beginTransaction();

        try {
            $distribution = $this->lockDistribution($distributionId);
            if ($distribution['status'] !== 'dispatched') {
                throw new RuntimeException('Solo una transferencia despachada puede recibirse en el P4.');
            }

            $transitId = (int) $distribution['transit_warehouse_id'];
            $destinationId = (int) $distribution['destination_warehouse_id'];
            $this->lockWarehouse($transitId);
            $destination = $this->lockWarehouse($destinationId);

            if ((int) $destination['is_active'] !== 1) {
                throw new RuntimeException('El P4 destino está inactivo y no puede confirmar recepción.');
            }

            $items = $this->lockedItems($distributionId);
            foreach ($items as $item) {
                if ($this->stock($transitId, (int) $item['product_variant_id']) + 0.000001 < (float) $item['quantity']) {
                    throw new RuntimeException(
                        'El inventario en tránsito no coincide con la transferencia.'
                    );
                }
            }

            $movement = $this->movementStatement();

            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                $quantity = $item['quantity'];
                $variantId = (int) $item['product_variant_id'];

                $movement->execute([
                    'warehouse_id' => $transitId,
                    'product_variant_id' => $variantId,
                    'movement_kind' => 'transfer_receive',
                    'direction' => 'OUT',
                    'quantity' => $quantity,
                    'reference_id' => $distributionId,
                    'reference_code' => $distribution['transfer_code'],
                    'document_number' => $distribution['document_number'],
                    'notes' => $notes,
                    'actor_label' => $receivedBy['display_name'],
                    'source_key' => sprintf('distribution:%d:receive:transit:%d', $distributionId, $itemId),
                ]);

                $movement->execute([
                    'warehouse_id' => $destinationId,
                    'product_variant_id' => $variantId,
                    'movement_kind' => 'transfer_receive',
                    'direction' => 'IN',
                    'quantity' => $quantity,
                    'reference_id' => $distributionId,
                    'reference_code' => $distribution['transfer_code'],
                    'document_number' => $distribution['document_number'],
                    'notes' => $notes,
                    'actor_label' => $receivedBy['display_name'],
                    'source_key' => sprintf('distribution:%d:receive:p4:%d', $distributionId, $itemId),
                ]);

                $this->pdo->prepare(
                    'UPDATE service_request_items
                     SET quantity_received = quantity_received + :quantity
                     WHERE id = :request_item_id'
                )->execute([
                    'quantity' => $quantity,
                    'request_item_id' => $item['request_item_id'],
                ]);
            }

            $this->pdo->prepare(<<<'SQL'
                UPDATE distributions
                SET
                    status = 'received',
                    received_by_employee_id = :employee_id,
                    received_by_label_snapshot = :label,
                    received_at = NOW(),
                    receipt_notes = :notes
                WHERE id = :id
            SQL)->execute([
                'employee_id' => $receivedBy['employee_id'],
                'label' => $receivedBy['display_name'],
                'notes' => $notes,
                'id' => $distributionId,
            ]);

            $pending = $this->pdo->prepare(
                'SELECT COALESCE(SUM(GREATEST(quantity_approved - quantity_received, 0)), 0)
                 FROM service_request_items
                 WHERE request_id = :request_id'
            );
            $pending->execute(['request_id' => $distribution['request_id']]);

            if ((float) $pending->fetchColumn() <= 0.000001) {
                $this->pdo->prepare(
                    'UPDATE service_requests
                     SET status = "fulfilled"
                     WHERE id = :id
                       AND status IN ("approved", "partially_approved")'
                )->execute(['id' => $distribution['request_id']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $distributionId): void
    {
        $this->pdo->beginTransaction();

        try {
            $distribution = $this->lockDistribution($distributionId);

            if (!in_array($distribution['status'], ['prepared', 'authorized'], true)) {
                throw new RuntimeException(
                    'Solo una transferencia preparada o autorizada puede cancelarse.'
                );
            }

            $this->pdo->prepare(
                'UPDATE distributions SET status = "cancelled" WHERE id = :id'
            )->execute(['id' => $distributionId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function movementStatement(): \PDOStatement
    {
        return $this->pdo->prepare(<<<'SQL'
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
                    :movement_kind,
                    :direction,
                    :quantity,
                    NOW(),
                    'distribution',
                    :reference_id,
                    :reference_code,
                    :document_number,
                    :notes,
                    :actor_label,
                    :source_key
                )
            ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)
        SQL);
    }

    private function stock(int $warehouseId, int $variantId): float
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

    private function warehouseIdByCode(string $code, bool $lock): int
    {
        $sql = 'SELECT id FROM warehouses WHERE code = :code LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['code' => $code]);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("No existe el almacén {$code}.");
        }

        return (int) $id;
    }

    private function lockWarehouse(int $warehouseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM warehouses WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $warehouseId]);
        $warehouse = $statement->fetch();

        if (!$warehouse) {
            throw new RuntimeException('El almacén solicitado no existe.');
        }

        return $warehouse;
    }

    private function lockRequest(int $requestId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM service_requests WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $requestId]);
        $request = $statement->fetch();

        if (!$request) {
            throw new RuntimeException('La solicitud vinculada no existe.');
        }

        return $request;
    }

    private function lockDistribution(int $distributionId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM distributions WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $distributionId]);
        $distribution = $statement->fetch();

        if (!$distribution) {
            throw new RuntimeException('La transferencia no existe.');
        }

        return $distribution;
    }

    private function lockedItems(int $distributionId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM distribution_items
             WHERE distribution_id = :distribution_id
             ORDER BY id
             FOR UPDATE'
        );
        $statement->execute(['distribution_id' => $distributionId]);

        return $statement->fetchAll();
    }
}
