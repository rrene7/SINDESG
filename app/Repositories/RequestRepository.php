<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class RequestRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function stats(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM service_requests WHERE status = 'draft') AS drafts,
                (SELECT COUNT(*) FROM service_requests WHERE status IN ('submitted','in_review')) AS pending_review,
                (SELECT COUNT(*) FROM service_requests WHERE status = 'approved') AS approved,
                (SELECT COUNT(*) FROM service_requests WHERE status = 'partially_approved') AS partials,
                (SELECT COUNT(*) FROM service_requests WHERE status = 'rejected') AS rejected
        SQL)->fetch();

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'pending_review' => (int) ($row['pending_review'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'partials' => (int) ($row['partials'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
        ];
    }

    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        return $this->pdo->query('
            SELECT
                r.id,
                r.request_code,
                r.p4_warehouse_id,
                r.requested_at,
                r.status,
                r.requested_by_label_snapshot,
                w.code AS p4_code,
                w.organization_unit_name_snapshot AS p4_name,
                COUNT(i.id) AS item_count,
                COALESCE(SUM(i.quantity_requested), 0) AS quantity_requested,
                COALESCE(SUM(i.quantity_approved), 0) AS quantity_approved
            FROM service_requests r
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            LEFT JOIN service_request_items i ON i.request_id = r.id
            GROUP BY r.id
            ORDER BY r.requested_at DESC, r.id DESC
            LIMIT ' . $limit)->fetchAll();
    }

    public function activeP4s(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT id, code, name, organization_unit_name_snapshot, responsible_label_snapshot
            FROM warehouses
            WHERE warehouse_type = 'P4'
              AND is_active = 1
            ORDER BY organization_unit_name_snapshot, name
        SQL)->fetchAll();
    }

    public function find(int $requestId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                r.*,
                w.code AS p4_code,
                w.name AS p4_internal_name,
                w.organization_unit_id,
                w.organization_unit_name_snapshot AS p4_name_snapshot,
                w.organization_unit_code_snapshot AS p4_unit_code_snapshot,
                w.is_active AS p4_is_active
            FROM service_requests r
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            WHERE r.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function items(int $requestId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                i.*,
                (
                    SELECT MAX(previous.delivered_at)
                    FROM individual_deliveries previous
                    INNER JOIN individual_delivery_items previous_item
                        ON previous_item.delivery_id = previous.id
                    INNER JOIN product_variants previous_variant
                        ON previous_variant.id = previous_item.product_variant_id
                    WHERE previous.recipient_employee_id = i.beneficiary_employee_id
                      AND previous.status = 'confirmed'
                      AND previous_variant.product_id = pv.product_id
                ) AS last_delivered_same_product_at,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                p.id AS product_id,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(COALESCE((
                    SELECT vs.quantity_on_hand
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses wc ON wc.id = vs.warehouse_id
                    WHERE wc.code = 'CENTRAL-SG'
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
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, i.id
        SQL);
        $statement->execute(['request_id' => $requestId]);

        $rows = $statement->fetchAll();
        // Una requisición nominal puede tener >700 filas, pero ~17 tallas.
        // Evitar una consulta de existencias por funcionario.
        $freeByVariant = [];
        foreach ($rows as &$row) {
            $variantId = (int) $row['product_variant_id'];
            if (!array_key_exists($variantId, $freeByVariant)) {
                $freeByVariant[$variantId] = $this->availableToApprove($variantId, $requestId);
            }
            $row['available_to_approve'] = $freeByVariant[$variantId];
        }
        unset($row);

        return $rows;
    }

    /**
     * Seguimiento exclusivamente de transferencias reales de esta
     * requisición. No presume ubicación GPS ni registra movimiento.
     *
     * @return array<string,mixed>
     */
    public function transferProgress(int $requestId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT
                COUNT(*) AS transfer_count,
                SUM(CASE WHEN status IN ('prepared','authorized','dispatched','received')
                    THEN 1 ELSE 0 END) AS prepared_count,
                SUM(CASE WHEN status IN ('authorized','dispatched','received')
                    THEN 1 ELSE 0 END) AS authorized_count,
                SUM(CASE WHEN status IN ('dispatched','received')
                    THEN 1 ELSE 0 END) AS dispatched_count,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) AS received_count,
                MAX(CASE WHEN status <> 'cancelled' THEN prepared_at END) AS last_prepared_at,
                MAX(CASE WHEN status IN ('dispatched','received')
                    THEN dispatched_at END) AS last_dispatched_at,
                MAX(CASE WHEN status = 'received' THEN received_at END) AS last_received_at
            FROM distributions
            WHERE request_id = :request_id
        SQL);
        $stmt->execute(['request_id' => $requestId]);
        return $stmt->fetch() ?: [];
    }

    public function commitmentEvidence(int $requestId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                CAST(COALESCE(SUM(i.quantity_requested), 0) AS DECIMAL(14,3)) AS requested,
                CAST(COALESCE(SUM(i.quantity_approved), 0) AS DECIMAL(14,3)) AS approved,
                CAST(COALESCE(SUM(i.quantity_dispatched), 0) AS DECIMAL(14,3)) AS dispatched,
                CAST(COALESCE(SUM(i.quantity_received), 0) AS DECIMAL(14,3)) AS received,
                CAST(COALESCE(SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0)), 0) AS DECIMAL(14,3)) AS committed,
                (
                    SELECT COUNT(*)
                    FROM inventory_movements im
                    WHERE im.reference_type = 'request'
                      AND im.reference_id = :movement_request_id
                ) AS direct_movements
            FROM service_request_items i
            WHERE i.request_id = :request_id
        SQL);
        $statement->execute([
            'movement_request_id' => $requestId,
            'request_id' => $requestId,
        ]);

        $row = $statement->fetch() ?: [];

        return [
            'requested' => (float) ($row['requested'] ?? 0),
            'approved' => (float) ($row['approved'] ?? 0),
            'dispatched' => (float) ($row['dispatched'] ?? 0),
            'received' => (float) ($row['received'] ?? 0),
            'committed' => (float) ($row['committed'] ?? 0),
            'direct_movements' => (int) ($row['direct_movements'] ?? 0),
        ];
    }

    public function variantOptions(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT
                pv.id,
                pv.internal_code AS variant_code,
                pv.institutional_code,
                pv.supplier_code,
                pv.color AS variant_color,
                p.name AS product_name,
                c.name AS category_name,
                s.name AS size_name,
                b.name AS brand_name,
                CAST(COALESCE((
                    SELECT vs.quantity_on_hand
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses wc ON wc.id = vs.warehouse_id
                    WHERE wc.code = 'CENTRAL-SG'
                      AND vs.product_variant_id = pv.id
                    LIMIT 1
                ), 0) AS DECIMAL(14,3)) AS central_stock
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id AND p.status = 'active'
            INNER JOIN categories c ON c.id = p.category_id AND c.is_active = 1
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE pv.status = 'active'
            ORDER BY c.sort_order, p.name, s.sort_order, s.name, b.name, pv.id
        SQL)->fetchAll();
    }

    public function createDraft(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO service_requests
                    (
                        request_code,
                        p4_warehouse_id,
                        requested_by_employee_id,
                        requested_by_label_snapshot,
                        requested_by_document_snapshot,
                        requested_by_position_snapshot,
                        requested_by_rank_snapshot,
                        requested_at,
                        status,
                        notes
                    )
                VALUES
                    (
                        :request_code,
                        :p4_warehouse_id,
                        :requested_by_employee_id,
                        :requested_by_label_snapshot,
                        :requested_by_document_snapshot,
                        :requested_by_position_snapshot,
                        :requested_by_rank_snapshot,
                        :requested_at,
                        'draft',
                        :notes
                    )
            SQL);
            $statement->execute([
                'request_code' => 'TMP-SOL-' . strtoupper(bin2hex(random_bytes(6))),
                'p4_warehouse_id' => $data['p4_warehouse_id'],
                'requested_by_employee_id' => $data['requested_by_employee_id'],
                'requested_by_label_snapshot' => $data['requested_by_label_snapshot'],
                'requested_by_document_snapshot' => $data['requested_by_document_snapshot'],
                'requested_by_position_snapshot' => $data['requested_by_position_snapshot'],
                'requested_by_rank_snapshot' => $data['requested_by_rank_snapshot'],
                'requested_at' => $data['requested_at'],
                'notes' => $data['notes'],
            ]);

            $requestId = (int) $this->pdo->lastInsertId();
            $code = sprintf('SOL-%06d', $requestId);

            $update = $this->pdo->prepare(
                'UPDATE service_requests SET request_code = :code WHERE id = :id'
            );
            $update->execute(['code' => $code, 'id' => $requestId]);

            $this->pdo->commit();
            return $requestId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function p4IsActive(int $warehouseId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM warehouses
             WHERE id = :id AND warehouse_type = "P4" AND is_active = 1'
        );
        $statement->execute(['id' => $warehouseId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function variantIsActive(int $variantId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM product_variants WHERE id = :id AND status = "active"'
        );
        $statement->execute(['id' => $variantId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function hasDuplicateItem(
        int $requestId,
        int $variantId,
        ?int $beneficiaryEmployeeId
    ): bool {
        if ($beneficiaryEmployeeId === null) {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM service_request_items
                 WHERE request_id = :request_id
                   AND product_variant_id = :variant_id
                   AND beneficiary_employee_id IS NULL'
            );
            $statement->execute([
                'request_id' => $requestId,
                'variant_id' => $variantId,
            ]);
        } else {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM service_request_items
                 WHERE request_id = :request_id
                   AND product_variant_id = :variant_id
                   AND beneficiary_employee_id = :beneficiary_employee_id'
            );
            $statement->execute([
                'request_id' => $requestId,
                'variant_id' => $variantId,
                'beneficiary_employee_id' => $beneficiaryEmployeeId,
            ]);
        }

        return (int) $statement->fetchColumn() > 0;
    }

    public function addItem(int $requestId, array $data): int
    {
        $request = $this->find($requestId);
        if ($request === null || $request['status'] !== 'draft') {
            throw new RuntimeException('Solo se pueden agregar renglones a solicitudes en borrador.');
        }

        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO service_request_items
                (
                    request_id,
                    product_variant_id,
                    beneficiary_employee_id,
                    beneficiary_label_snapshot,
                    beneficiary_document_snapshot,
                    beneficiary_position_snapshot,
                    beneficiary_rank_snapshot,
                    beneficiary_unit_id_snapshot,
                    beneficiary_unit_name_snapshot,
                    quantity_requested,
                    notes
                )
            VALUES
                (
                    :request_id,
                    :product_variant_id,
                    :beneficiary_employee_id,
                    :beneficiary_label_snapshot,
                    :beneficiary_document_snapshot,
                    :beneficiary_position_snapshot,
                    :beneficiary_rank_snapshot,
                    :beneficiary_unit_id_snapshot,
                    :beneficiary_unit_name_snapshot,
                    :quantity_requested,
                    :notes
                )
        SQL);
        $statement->execute([
            'request_id' => $requestId,
            'product_variant_id' => $data['product_variant_id'],
            'beneficiary_employee_id' => $data['beneficiary_employee_id'],
            'beneficiary_label_snapshot' => $data['beneficiary_label_snapshot'],
            'beneficiary_document_snapshot' => $data['beneficiary_document_snapshot'],
            'beneficiary_position_snapshot' => $data['beneficiary_position_snapshot'],
            'beneficiary_rank_snapshot' => $data['beneficiary_rank_snapshot'],
            'beneficiary_unit_id_snapshot' => $data['beneficiary_unit_id_snapshot'],
            'beneficiary_unit_name_snapshot' => $data['beneficiary_unit_name_snapshot'],
            'quantity_requested' => $data['quantity_requested'],
            'notes' => $data['notes'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Inserta la selección nominal como UNA operación atómica. No mueve stock.
     * Un funcionario no puede recibir dos renglones del mismo producto en
     * una misma solicitud, aunque haya variantes/tallas distintas.
     *
     * @param list<array<string,mixed>> $rows
     */
    public function addNominalBatch(int $requestId, array $rows, ?array $documentGap = null): int
    {
        if ($rows === [] || count($rows) > 1000) {
            throw new RuntimeException('Seleccione entre 1 y 1,000 beneficiarios.');
        }
        $this->pdo->beginTransaction();
        try {
            $request = $this->lockRequest($requestId);
            if ($request['status'] !== 'draft') {
                throw new RuntimeException('Solo es posible agregar dotaciones a solicitudes en borrador.');
            }

            $existing = $this->pdo->prepare(<<<'SQL'
                SELECT i.beneficiary_employee_id, pv.product_id
                FROM service_request_items i
                INNER JOIN product_variants pv ON pv.id = i.product_variant_id
                WHERE i.request_id = :request_id AND i.beneficiary_employee_id IS NOT NULL
                FOR UPDATE
            SQL);
            $existing->execute(['request_id' => $requestId]);
            $seen = [];
            foreach ($existing->fetchAll() as $line) {
                $seen[$line['beneficiary_employee_id'] . ':' . $line['product_id']] = true;
            }

            $variantIds = [];
            foreach ($rows as $line) {
                $variantIds[(int) ($line['product_variant_id'] ?? 0)] = true;
            }
            $variants = [];
            $lookup = $this->pdo->prepare(<<<'SQL'
                SELECT pv.id, pv.product_id
                FROM product_variants pv INNER JOIN products p ON p.id = pv.product_id
                WHERE pv.id = :id AND pv.status = 'active' AND p.status = 'active'
            SQL);
            foreach (array_keys($variantIds) as $id) {
                $lookup->execute(['id' => $id]);
                $variant = $lookup->fetch();
                if (!$variant) {
                    throw new RuntimeException('Una talla o variante ya no está disponible en catálogo.');
                }
                $variants[$id] = (int) $variant['product_id'];
            }

            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO service_request_items (
                    request_id, product_variant_id,
                    beneficiary_employee_id, beneficiary_label_snapshot,
                    beneficiary_document_snapshot, beneficiary_position_snapshot,
                    beneficiary_rank_snapshot, beneficiary_unit_id_snapshot,
                    beneficiary_unit_name_snapshot, quantity_requested, notes
                ) VALUES (
                    :request_id, :variant_id,
                    :employee_id, :label, :document, :position,
                    :rank, :unit_id, :unit_name, :quantity, :notes
                )
            SQL);
            foreach ($rows as $line) {
                $employeeId = (int) ($line['employee_id'] ?? 0);
                $variantId = (int) ($line['product_variant_id'] ?? 0);
                if ($employeeId <= 0 || !isset($variants[$variantId])) {
                    throw new RuntimeException('El destinatario o artículo no es válido.');
                }
                $key = $employeeId . ':' . $variants[$variantId];
                if (isset($seen[$key])) {
                    throw new RuntimeException(
                        'Asignación duplicada para el funcionario RRHH #' . $employeeId
                        . ' en este producto. Elimine o corrija la fila antes de repetir la carga.'
                    );
                }
                $seen[$key] = true;
                $insert->execute([
                    'request_id' => $requestId,
                    'variant_id' => $variantId,
                    'employee_id' => $employeeId,
                    'label' => (string) $line['display_name'],
                    'document' => (string) $line['document_number'],
                    'position' => $line['position_number'],
                    'rank' => $line['rank_name'],
                    'unit_id' => $line['organization_unit_id'],
                    'unit_name' => $line['organization_unit_name'],
                    'quantity' => '1.000',
                    'notes' => mb_substr((string) ($line['notes'] ?? ''), 0, 255),
                ]);
            }
            if ($documentGap !== null) {
                // Evidencia de diferencia del encabezado, dentro de LA
                // MISMA transacción que las asignaciones nominales.
                // No representa una unidad despachada ni almacenada.
                (new AuditRepository($this->pdo))->log([
                    'user_id' => $documentGap['user_id'],
                    'user_label_snapshot' => $documentGap['user_label_snapshot'],
                    'module_code' => 'requests',
                    'action_code' => 'nominal_quantity_discrepancy_accepted',
                    'record_type' => 'service_request',
                    'record_id' => $requestId,
                    'details' => [
                        'requisition' => $documentGap['requisition'],
                        'source_sha256' => $documentGap['source_sha256'],
                        'declared_quantity' => $documentGap['declared_quantity'],
                        'listed_quantity' => $documentGap['listed_quantity'],
                        'nominal_imported' => count($rows),
                        'not_assigned' => $documentGap['not_assigned'],
                        'note' => 'Diferencia documental pendiente; NO es inventario ni dotación entregada.',
                    ],
                ]);
            }
            $this->pdo->commit();
            return count($rows);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function nominalProducts(): array
    {
        return $this->pdo->query(<<<'SQL'
            SELECT DISTINCT p.id, p.name, p.internal_code, c.name AS category_name
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            INNER JOIN product_variants pv ON pv.product_id = p.id
            WHERE p.status = 'active' AND pv.status = 'active'
            ORDER BY c.name, p.name
        SQL)->fetchAll();
    }

    /** @return array<int,bool> */
    public function nominalEmployeeIdsForProduct(int $requestId, int $productId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT DISTINCT i.beneficiary_employee_id
            FROM service_request_items i
            INNER JOIN product_variants pv ON pv.id = i.product_variant_id
            WHERE i.request_id = :request_id
              AND pv.product_id = :product_id
              AND i.beneficiary_employee_id IS NOT NULL
        SQL);
        $stmt->execute(['request_id' => $requestId, 'product_id' => $productId]);
        return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /** @return array<string,int> */
    public function sizeVariantsForProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT pv.id, s.name AS size_name
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN sizes s ON s.id = pv.size_id
            WHERE pv.product_id = :product_id
              AND pv.status = 'active' AND p.status = 'active'
        SQL);
        $stmt->execute(['product_id' => $productId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = str_replace(',', '.', trim((string) $row['size_name']));
            if (!preg_match('/^\d{1,2}(?:\.5)?$/', $key)) {
                continue;
            }
            $key = rtrim(rtrim(number_format((float) $key, 1, '.', ''), '0'), '.');
            if (isset($map[$key])) {
                throw new RuntimeException(
                    'Hay más de una variante activa para talla ' . $key
                    . '; seleccione un producto con tallas inequívocas.'
                );
            }
            $map[$key] = (int) $row['id'];
        }
        return $map;
    }

    public function removeItem(int $requestId, int $itemId): void
    {
        $request = $this->find($requestId);
        if ($request === null || $request['status'] !== 'draft') {
            throw new RuntimeException('Solo se pueden quitar renglones de solicitudes en borrador.');
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM service_request_items WHERE id = :id AND request_id = :request_id'
        );
        $statement->execute(['id' => $itemId, 'request_id' => $requestId]);
    }

    public function submit(int $requestId): void
    {
        $this->pdo->beginTransaction();

        try {
            $request = $this->lockRequest($requestId);

            if ($request['status'] !== 'draft') {
                throw new RuntimeException('Solo se pueden enviar solicitudes en borrador.');
            }

            $count = $this->pdo->prepare(
                'SELECT COUNT(*) FROM service_request_items WHERE request_id = :request_id'
            );
            $count->execute(['request_id' => $requestId]);

            if ((int) $count->fetchColumn() === 0) {
                throw new RuntimeException('Agregue al menos un renglón antes de enviar la solicitud.');
            }

            $update = $this->pdo->prepare(
                'UPDATE service_requests
                 SET status = "submitted", submitted_at = NOW()
                 WHERE id = :id'
            );
            $update->execute(['id' => $requestId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function startReview(int $requestId): void
    {
        $this->pdo->beginTransaction();

        try {
            $request = $this->lockRequest($requestId);

            if ($request['status'] !== 'submitted') {
                throw new RuntimeException('Solo una solicitud enviada puede pasar a revisión.');
            }

            $update = $this->pdo->prepare(
                'UPDATE service_requests SET status = "in_review" WHERE id = :id'
            );
            $update->execute(['id' => $requestId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function decide(
        int $requestId,
        array $approvedByItemId,
        array $reviewer,
        ?string $reviewNotes
    ): string {
        $this->pdo->beginTransaction();

        try {
            $request = $this->lockRequest($requestId);

            if ($request['status'] !== 'in_review') {
                throw new RuntimeException('La solicitud debe estar en revisión para registrar una decisión.');
            }

            $itemsStatement = $this->pdo->prepare(
                'SELECT id, product_variant_id, quantity_requested
                 FROM service_request_items
                 WHERE request_id = :request_id
                 ORDER BY id
                 FOR UPDATE'
            );
            $itemsStatement->execute(['request_id' => $requestId]);
            $items = $itemsStatement->fetchAll();

            if ($items === []) {
                throw new RuntimeException('La solicitud no contiene renglones.');
            }

            $remainingByVariant = [];
            $updates = [];
            $totalRequested = 0.0;
            $totalApproved = 0.0;

            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                $variantId = (int) $item['product_variant_id'];
                $requested = (float) $item['quantity_requested'];
                $approved = (float) ($approvedByItemId[$itemId] ?? 0);

                if ($approved < 0 || $approved > $requested) {
                    throw new RuntimeException(
                        'La cantidad aprobada debe estar entre cero y la cantidad solicitada.'
                    );
                }

                if (!array_key_exists($variantId, $remainingByVariant)) {
                    $remainingByVariant[$variantId] = $this->availableToApprove(
                        $variantId,
                        $requestId
                    );
                }

                if ($approved > $remainingByVariant[$variantId] + 0.000001) {
                    throw new RuntimeException(sprintf(
                        'Stock comprometible insuficiente para la variante #%d. Disponible: %s; intento de aprobación: %s.',
                        $variantId,
                        number_format($remainingByVariant[$variantId], 3, '.', ''),
                        number_format($approved, 3, '.', '')
                    ));
                }

                $remainingByVariant[$variantId] -= $approved;
                $updates[$itemId] = number_format($approved, 3, '.', '');
                $totalRequested += $requested;
                $totalApproved += $approved;
            }

            $updateItem = $this->pdo->prepare(
                'UPDATE service_request_items
                 SET quantity_approved = :quantity_approved
                 WHERE id = :id AND request_id = :request_id'
            );

            foreach ($updates as $itemId => $approved) {
                $updateItem->execute([
                    'quantity_approved' => $approved,
                    'id' => $itemId,
                    'request_id' => $requestId,
                ]);
            }

            if ($totalApproved <= 0.000001) {
                $status = 'rejected';
            } elseif (abs($totalApproved - $totalRequested) <= 0.000001) {
                $status = 'approved';
            } else {
                $status = 'partially_approved';
            }

            $updateRequest = $this->pdo->prepare(<<<'SQL'
                UPDATE service_requests
                SET
                    status = :status,
                    reviewed_by_employee_id = :reviewed_by_employee_id,
                    reviewed_by_label_snapshot = :reviewed_by_label_snapshot,
                    reviewed_by_document_snapshot = :reviewed_by_document_snapshot,
                    reviewed_by_position_snapshot = :reviewed_by_position_snapshot,
                    reviewed_by_rank_snapshot = :reviewed_by_rank_snapshot,
                    reviewed_at = NOW(),
                    review_notes = :review_notes
                WHERE id = :id
            SQL);
            $updateRequest->execute([
                'status' => $status,
                'reviewed_by_employee_id' => $reviewer['employee_id'],
                'reviewed_by_label_snapshot' => $reviewer['display_name'],
                'reviewed_by_document_snapshot' => $reviewer['document_number'],
                'reviewed_by_position_snapshot' => $reviewer['position_number'],
                'reviewed_by_rank_snapshot' => $reviewer['rank_name'],
                'review_notes' => $reviewNotes,
                'id' => $requestId,
            ]);

            $this->pdo->commit();
            return $status;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function availableToApprove(int $variantId, int $excludeRequestId = 0): float
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                COALESCE((
                    SELECT vs.quantity_on_hand
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.code = 'CENTRAL-SG'
                      AND vs.product_variant_id = :stock_variant_id
                    LIMIT 1
                ), 0)
                -
                COALESCE((
                    SELECT SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0))
                    FROM service_request_items i
                    INNER JOIN service_requests r ON r.id = i.request_id
                    WHERE i.product_variant_id = :commit_variant_id
                      AND r.status IN ('approved', 'partially_approved')
                      AND r.id <> :exclude_request_id
                ), 0) AS available_quantity
        SQL);
        $statement->execute([
            'stock_variant_id' => $variantId,
            'commit_variant_id' => $variantId,
            'exclude_request_id' => $excludeRequestId,
        ]);

        return max(0.0, (float) $statement->fetchColumn());
    }

    private function lockRequest(int $requestId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM service_requests WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $requestId]);
        $request = $statement->fetch();

        if (!$request) {
            throw new RuntimeException('La solicitud no existe.');
        }

        return $request;
    }
}
