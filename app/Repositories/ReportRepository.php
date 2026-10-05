<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use InvalidArgumentException;

final class ReportRepository
{
    private const REPORTS = [
        'stock' => [
            'title' => 'Stock actual',
            'description' => 'Existencia física por almacén, artículo y variante. En el Central separa stock físico, comprometido y disponible.',
            'date_filter' => false,
        ],
        'low_stock' => [
            'title' => 'Próximos a agotarse',
            'description' => 'Variantes con existencia disponible mayor que cero y menor o igual al umbral seleccionado.',
            'date_filter' => false,
        ],
        'kardex' => [
            'title' => 'Kardex',
            'description' => 'Todos los movimientos de inventario con almacén, referencia, documento y responsable.',
            'date_filter' => true,
        ],
        'entries' => [
            'title' => 'Entradas por período',
            'description' => 'Movimientos IN procedentes de recepciones, producción, devoluciones y ajustes.',
            'date_filter' => true,
        ],
        'exits' => [
            'title' => 'Salidas por período',
            'description' => 'Movimientos OUT por distribución, despacho individual, consumo, baja y ajustes.',
            'date_filter' => true,
        ],
        'receipts' => [
            'title' => 'Recepciones y lotes',
            'description' => 'Lotes recibidos con procedencia, proveedor/origen, documento y cantidades.',
            'date_filter' => true,
        ],
        'requests' => [
            'title' => 'Solicitudes y pendientes',
            'description' => 'Solicitado, aprobado, despachado, recibido en P4 y entregado al funcionario.',
            'date_filter' => true,
        ],
        'transfers' => [
            'title' => 'Transferencias Central → P4',
            'description' => 'Distribuciones por solicitud, destino, estado y cantidades transferidas.',
            'date_filter' => true,
        ],
        'deliveries' => [
            'title' => 'Entregas individuales',
            'description' => 'Despachos P4 → funcionario con adscripción histórica y artículo entregado.',
            'date_filter' => true,
        ],
        'employee_dotations' => [
            'title' => 'Dotación por funcionario',
            'description' => 'Historial confirmado de artículos entregados a cada funcionario.',
            'date_filter' => true,
        ],
        'adjustments' => [
            'title' => 'Ajustes, cambios y devoluciones',
            'description' => 'Operaciones compensatorias de Fase 10, con stock antes/después y responsable.',
            'date_filter' => true,
        ],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function reportDefinitions(): array
    {
        return self::REPORTS;
    }

    public function hasReport(string $type): bool
    {
        return isset(self::REPORTS[$type]);
    }

    public function reportMeta(string $type): array
    {
        if (!$this->hasReport($type)) {
            throw new InvalidArgumentException('El reporte solicitado no existe.');
        }

        return self::REPORTS[$type];
    }

    public function summary(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                COALESCE((SELECT SUM(quantity_on_hand) FROM v_inventory_stock), 0) AS institutional_stock,
                COALESCE((
                    SELECT SUM(vs.quantity_on_hand)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'CENTRAL'
                ), 0) AS central_stock,
                COALESCE((
                    SELECT SUM(vs.quantity_on_hand)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'P4'
                ), 0) AS p4_stock,
                COALESCE((
                    SELECT SUM(vs.quantity_on_hand)
                    FROM v_inventory_stock vs
                    INNER JOIN warehouses w ON w.id = vs.warehouse_id
                    WHERE w.warehouse_type = 'TRANSIT'
                ), 0) AS transit_stock,
                COALESCE((
                    SELECT SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0))
                    FROM service_request_items i
                    INNER JOIN service_requests r
                        ON r.id = i.request_id
                       AND r.status IN ('approved', 'partially_approved')
                ), 0) AS committed_stock,
                (SELECT COUNT(*) FROM inventory_movements) AS movement_count,
                (SELECT COUNT(*) FROM individual_deliveries WHERE status = 'confirmed') AS confirmed_deliveries,
                (
                    SELECT COUNT(*)
                    FROM service_requests
                    WHERE status NOT IN ('rejected', 'fulfilled', 'closed')
                ) AS open_requests
        SQL)->fetch() ?: [];

        $central = (float) ($row['central_stock'] ?? 0);
        $committed = (float) ($row['committed_stock'] ?? 0);

        return [
            'institutional_stock' => (float) ($row['institutional_stock'] ?? 0),
            'central_stock' => $central,
            'committed_stock' => $committed,
            'available_stock' => max(0.0, $central - $committed),
            'p4_stock' => (float) ($row['p4_stock'] ?? 0),
            'transit_stock' => (float) ($row['transit_stock'] ?? 0),
            'movement_count' => (int) ($row['movement_count'] ?? 0),
            'confirmed_deliveries' => (int) ($row['confirmed_deliveries'] ?? 0),
            'open_requests' => (int) ($row['open_requests'] ?? 0),
        ];
    }

    public function filterOptions(): array
    {
        return [
            'warehouses' => $this->pdo->query(<<<'SQL'
                SELECT id, code, name, warehouse_type, organization_unit_name_snapshot
                FROM warehouses
                WHERE is_active = 1
                ORDER BY
                    CASE
                        WHEN warehouse_type = 'CENTRAL' THEN 0
                        WHEN warehouse_type = 'TRANSIT' THEN 1
                        ELSE 2
                    END,
                    COALESCE(organization_unit_name_snapshot, name),
                    code
            SQL)->fetchAll(),
            'categories' => $this->pdo->query(<<<'SQL'
                SELECT id, code, name
                FROM categories
                WHERE is_active = 1
                ORDER BY sort_order, name
            SQL)->fetchAll(),
            'products' => $this->pdo->query(<<<'SQL'
                SELECT p.id, p.name, p.sex, c.name AS category_name
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE p.status <> 'deleted'
                ORDER BY c.sort_order, p.name
            SQL)->fetchAll(),
            'sizes' => $this->pdo->query(<<<'SQL'
                SELECT id, code, name
                FROM sizes
                WHERE is_active = 1
                ORDER BY sort_order, name
            SQL)->fetchAll(),
        ];
    }

    public function run(string $type, array $filters, int $limit = 500): array
    {
        if (!$this->hasReport($type)) {
            throw new InvalidArgumentException('El reporte solicitado no existe.');
        }

        $limit = max(1, min($limit, 20000));

        return match ($type) {
            'stock' => $this->stock($filters, $limit, false),
            'low_stock' => $this->stock($filters, $limit, true),
            'kardex' => $this->movements($filters, $limit, null),
            'entries' => $this->movements($filters, $limit, 'IN'),
            'exits' => $this->movements($filters, $limit, 'OUT'),
            'receipts' => $this->receipts($filters, $limit),
            'requests' => $this->requests($filters, $limit),
            'transfers' => $this->transfers($filters, $limit),
            'deliveries' => $this->deliveries($filters, $limit, false),
            'employee_dotations' => $this->deliveries($filters, $limit, true),
            'adjustments' => $this->adjustments($filters, $limit),
            default => [],
        };
    }

    public function columns(string $type): array
    {
        return match ($type) {
            'stock', 'low_stock' => [
                'warehouse_code' => 'Almacén',
                'warehouse_type' => 'Tipo almacén',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'brand_name' => 'Marca',
                'sex' => 'Aplicación',
                'physical_quantity' => 'Físico',
                'committed_quantity' => 'Comprometido',
                'available_quantity' => 'Disponible',
            ],
            'kardex', 'entries', 'exits' => [
                'occurred_at' => 'Fecha/hora',
                'warehouse_code' => 'Almacén',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'movement_kind' => 'Movimiento',
                'direction' => 'Dirección',
                'quantity' => 'Cantidad',
                'reference_code' => 'Referencia',
                'document_number' => 'Documento',
                'actor_label' => 'Responsable',
            ],
            'receipts' => [
                'received_at' => 'Fecha/hora',
                'receipt_code' => 'Recepción',
                'lot_code' => 'Lote',
                'source_type' => 'Procedencia',
                'supplier_or_origin' => 'Proveedor/origen',
                'document_number' => 'Documento',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'quantity' => 'Cantidad',
                'status' => 'Estado',
            ],
            'requests' => [
                'requested_at' => 'Fecha/hora',
                'request_code' => 'Solicitud',
                'p4_code' => 'P4',
                'p4_name' => 'Dependencia P4',
                'beneficiary' => 'Beneficiario',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'quantity_requested' => 'Solicitado',
                'quantity_approved' => 'Aprobado',
                'quantity_dispatched' => 'Despachado',
                'quantity_received' => 'Recibido P4',
                'quantity_delivered' => 'Entregado',
                'pending_quantity' => 'Pendiente',
                'status' => 'Estado',
            ],
            'transfers' => [
                'event_at' => 'Fecha/hora',
                'transfer_code' => 'Transferencia',
                'request_code' => 'Solicitud',
                'destination_code' => 'P4',
                'destination_name' => 'Dependencia P4',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'quantity' => 'Cantidad',
                'status' => 'Estado',
                'document_number' => 'Documento',
            ],
            'deliveries', 'employee_dotations' => [
                'delivered_at' => 'Fecha/hora',
                'delivery_code' => 'Despacho',
                'p4_code' => 'P4',
                'recipient' => 'Funcionario',
                'document_number' => 'Cédula',
                'position_number' => 'Posición',
                'direction_name' => 'Dirección',
                'office_name' => 'Oficina',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'quantity' => 'Cantidad',
                'request_code' => 'Solicitud',
                'status' => 'Estado',
            ],
            'adjustments' => [
                'occurred_at' => 'Fecha/hora',
                'adjustment_code' => 'Ajuste',
                'adjustment_type' => 'Tipo',
                'warehouse_code' => 'Almacén',
                'affected_employee' => 'Funcionario',
                'category_name' => 'Categoría',
                'product_name' => 'Artículo',
                'variant_code' => 'Variante',
                'size_name' => 'Talla',
                'quantity' => 'Cantidad',
                'status' => 'Estado',
                'responsible' => 'Responsable',
                'document_number' => 'Documento',
                'reason' => 'Motivo',
            ],
            default => [],
        };
    }

    private function stock(array $filters, int $limit, bool $lowOnly): array
    {
        $where = ['vs.quantity_on_hand <> 0'];
        $params = [];
        $this->catalogFilters($filters, $where, $params, 'w', 'c', 'p', 'pv', 's');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(w.code LIKE :stock_q1 OR COALESCE(w.organization_unit_name_snapshot, w.name) LIKE :stock_q2 OR p.name LIKE :stock_q3 OR pv.internal_code LIKE :stock_q4 OR COALESCE(pv.institutional_code, "") LIKE :stock_q5)';
            foreach (range(1, 5) as $index) {
                $params['stock_q' . $index] = $term;
            }
        }

        $having = '';
        if ($lowOnly) {
            $threshold = max(0.0, min((float) ($filters['threshold'] ?? 10), 1000000.0));
            $having = ' HAVING available_quantity > 0 AND available_quantity <= :stock_threshold';
            $params['stock_threshold'] = $threshold;
        }

        $sql = '
            SELECT
                w.code AS warehouse_code,
                CASE w.warehouse_type
                    WHEN "CENTRAL" THEN "Central"
                    WHEN "P4" THEN "P4"
                    WHEN "TRANSIT" THEN "Tránsito"
                    ELSE w.warehouse_type
                END AS warehouse_type,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                COALESCE(b.name, "—") AS brand_name,
                p.sex,
                CAST(vs.quantity_on_hand AS DECIMAL(14,3)) AS physical_quantity,
                CAST(
                    CASE
                        WHEN w.warehouse_type = "CENTRAL" THEN COALESCE((
                            SELECT SUM(GREATEST(i.quantity_approved - i.quantity_dispatched, 0))
                            FROM service_request_items i
                            INNER JOIN service_requests r
                                ON r.id = i.request_id
                               AND r.status IN ("approved", "partially_approved")
                            WHERE i.product_variant_id = pv.id
                        ), 0)
                        ELSE 0
                    END
                    AS DECIMAL(14,3)
                ) AS committed_quantity,
                CAST(
                    GREATEST(
                        vs.quantity_on_hand
                        -
                        CASE
                            WHEN w.warehouse_type = "CENTRAL" THEN COALESCE((
                                SELECT SUM(GREATEST(i2.quantity_approved - i2.quantity_dispatched, 0))
                                FROM service_request_items i2
                                INNER JOIN service_requests r2
                                    ON r2.id = i2.request_id
                                   AND r2.status IN ("approved", "partially_approved")
                                WHERE i2.product_variant_id = pv.id
                            ), 0)
                            ELSE 0
                        END,
                        0
                    )
                    AS DECIMAL(14,3)
                ) AS available_quantity
            FROM v_inventory_stock vs
            INNER JOIN warehouses w ON w.id = vs.warehouse_id
            INNER JOIN product_variants pv ON pv.id = vs.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN brands b ON b.id = pv.brand_id
            WHERE ' . implode(' AND ', $where)
            . $having .
            ' ORDER BY
                CASE w.warehouse_type WHEN "CENTRAL" THEN 0 WHEN "TRANSIT" THEN 1 ELSE 2 END,
                COALESCE(w.organization_unit_name_snapshot, w.name),
                c.sort_order,
                p.name,
                s.sort_order,
                s.name,
                pv.id
              LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function movements(array $filters, int $limit, ?string $forcedDirection): array
    {
        $where = ['1=1'];
        $params = [];
        $this->dateFilters($filters, $where, $params, 'im.occurred_at', 'mov');
        $this->catalogFilters($filters, $where, $params, 'w', 'c', 'p', 'pv', 's');

        $direction = $forcedDirection ?? strtoupper(trim((string) ($filters['direction'] ?? '')));
        if (in_array($direction, ['IN', 'OUT'], true)) {
            $where[] = 'im.direction = :mov_direction';
            $params['mov_direction'] = $direction;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'im.movement_kind = :mov_kind';
            $params['mov_kind'] = $status;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(COALESCE(im.reference_code, "") LIKE :mov_q1 OR COALESCE(im.document_number, "") LIKE :mov_q2 OR COALESCE(im.actor_label, "") LIKE :mov_q3 OR p.name LIKE :mov_q4 OR pv.internal_code LIKE :mov_q5)';
            foreach (range(1, 5) as $index) {
                $params['mov_q' . $index] = $term;
            }
        }

        $sql = '
            SELECT
                im.occurred_at,
                w.code AS warehouse_code,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                im.movement_kind,
                im.direction,
                CAST(im.quantity AS DECIMAL(14,3)) AS quantity,
                COALESCE(im.reference_code, "—") AS reference_code,
                COALESCE(im.document_number, "—") AS document_number,
                COALESCE(im.actor_label, "—") AS actor_label
            FROM inventory_movements im
            INNER JOIN warehouses w ON w.id = im.warehouse_id
            INNER JOIN product_variants pv ON pv.id = im.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY im.occurred_at DESC, im.id DESC
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function receipts(array $filters, int $limit): array
    {
        $where = ['1=1'];
        $params = [];
        $this->dateFilters($filters, $where, $params, 'r.received_at', 'rec');
        $this->catalogFilters($filters, $where, $params, 'w', 'c', 'p', 'pv', 's');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'r.status = :rec_status';
            $params['rec_status'] = $status;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(r.receipt_code LIKE :rec_q1 OR l.lot_code LIKE :rec_q2 OR r.supplier_or_origin LIKE :rec_q3 OR COALESCE(r.document_number, "") LIKE :rec_q4 OR p.name LIKE :rec_q5)';
            foreach (range(1, 5) as $index) {
                $params['rec_q' . $index] = $term;
            }
        }

        $sql = '
            SELECT
                r.received_at,
                r.receipt_code,
                l.lot_code,
                r.source_type,
                r.supplier_or_origin,
                COALESCE(r.document_number, "—") AS document_number,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                CAST(ri.quantity AS DECIMAL(14,3)) AS quantity,
                r.status
            FROM receipt_items ri
            INNER JOIN receipts r ON r.id = ri.receipt_id
            INNER JOIN lots l ON l.id = ri.lot_id
            INNER JOIN warehouses w ON w.id = r.warehouse_id
            INNER JOIN product_variants pv ON pv.id = ri.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY r.received_at DESC, r.id DESC, ri.id
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function requests(array $filters, int $limit): array
    {
        $where = ['1=1'];
        $params = [];
        $this->dateFilters($filters, $where, $params, 'r.requested_at', 'req');
        $this->catalogFilters($filters, $where, $params, 'w', 'c', 'p', 'pv', 's');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'r.status = :req_status';
            $params['req_status'] = $status;
        }

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $where[] = '(i.beneficiary_employee_id = :req_employee OR r.requested_by_employee_id = :req_requester)';
            $params['req_employee'] = $employeeId;
            $params['req_requester'] = $employeeId;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(r.request_code LIKE :req_q1 OR COALESCE(i.beneficiary_label_snapshot, "") LIKE :req_q2 OR r.requested_by_label_snapshot LIKE :req_q3 OR p.name LIKE :req_q4 OR pv.internal_code LIKE :req_q5)';
            foreach (range(1, 5) as $index) {
                $params['req_q' . $index] = $term;
            }
        }

        $sql = '
            SELECT
                r.requested_at,
                r.request_code,
                w.code AS p4_code,
                COALESCE(w.organization_unit_name_snapshot, w.name) AS p4_name,
                COALESCE(i.beneficiary_label_snapshot, "Solicitud no nominal") AS beneficiary,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                CAST(i.quantity_requested AS DECIMAL(14,3)) AS quantity_requested,
                CAST(i.quantity_approved AS DECIMAL(14,3)) AS quantity_approved,
                CAST(i.quantity_dispatched AS DECIMAL(14,3)) AS quantity_dispatched,
                CAST(i.quantity_received AS DECIMAL(14,3)) AS quantity_received,
                CAST(i.quantity_delivered AS DECIMAL(14,3)) AS quantity_delivered,
                CAST(GREATEST(i.quantity_approved - i.quantity_delivered, 0) AS DECIMAL(14,3)) AS pending_quantity,
                r.status
            FROM service_request_items i
            INNER JOIN service_requests r ON r.id = i.request_id
            INNER JOIN warehouses w ON w.id = r.p4_warehouse_id
            INNER JOIN product_variants pv ON pv.id = i.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY r.requested_at DESC, r.id DESC, i.id
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function transfers(array $filters, int $limit): array
    {
        $where = ['1=1'];
        $params = [];
        $this->dateFilters(
            $filters,
            $where,
            $params,
            'COALESCE(d.received_at, d.dispatched_at, d.prepared_at)',
            'trf'
        );
        $this->catalogFilters($filters, $where, $params, 'dw', 'c', 'p', 'pv', 's');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'd.status = :trf_status';
            $params['trf_status'] = $status;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(d.transfer_code LIKE :trf_q1 OR r.request_code LIKE :trf_q2 OR COALESCE(d.document_number, "") LIKE :trf_q3 OR p.name LIKE :trf_q4 OR pv.internal_code LIKE :trf_q5)';
            foreach (range(1, 5) as $index) {
                $params['trf_q' . $index] = $term;
            }
        }

        $sql = '
            SELECT
                COALESCE(d.received_at, d.dispatched_at, d.prepared_at) AS event_at,
                d.transfer_code,
                r.request_code,
                dw.code AS destination_code,
                COALESCE(dw.organization_unit_name_snapshot, dw.name) AS destination_name,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                CAST(di.quantity AS DECIMAL(14,3)) AS quantity,
                d.status,
                COALESCE(d.document_number, "—") AS document_number
            FROM distribution_items di
            INNER JOIN distributions d ON d.id = di.distribution_id
            INNER JOIN service_requests r ON r.id = d.request_id
            INNER JOIN warehouses dw ON dw.id = d.destination_warehouse_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY event_at DESC, d.id DESC, di.id
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function deliveries(array $filters, int $limit, bool $confirmedOnly): array
    {
        $where = [$confirmedOnly ? 'd.status = "confirmed"' : '1=1'];
        $params = [];
        $this->dateFilters($filters, $where, $params, 'd.delivered_at', 'del');
        $this->catalogFilters($filters, $where, $params, 'w', 'c', 'p', 'pv', 's');

        $status = trim((string) ($filters['status'] ?? ''));
        if (!$confirmedOnly && $status !== '') {
            $where[] = 'd.status = :del_status';
            $params['del_status'] = $status;
        }

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $where[] = 'd.recipient_employee_id = :del_employee';
            $params['del_employee'] = $employeeId;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(d.delivery_code LIKE :del_q1 OR d.recipient_label_snapshot LIKE :del_q2 OR COALESCE(d.recipient_document_snapshot, "") LIKE :del_q3 OR p.name LIKE :del_q4 OR pv.internal_code LIKE :del_q5)';
            foreach (range(1, 5) as $index) {
                $params['del_q' . $index] = $term;
            }
        }

        $sql = '
            SELECT
                d.delivered_at,
                d.delivery_code,
                w.code AS p4_code,
                d.recipient_label_snapshot AS recipient,
                COALESCE(d.recipient_document_snapshot, "—") AS document_number,
                COALESCE(d.recipient_position_snapshot, "—") AS position_number,
                COALESCE(d.recipient_direction_snapshot, "—") AS direction_name,
                COALESCE(d.recipient_office_snapshot, "—") AS office_name,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                CAST(di.quantity AS DECIMAL(14,3)) AS quantity,
                COALESCE(sr.request_code, "Entrega manual") AS request_code,
                d.status
            FROM individual_delivery_items di
            INNER JOIN individual_deliveries d ON d.id = di.delivery_id
            INNER JOIN warehouses w ON w.id = d.p4_warehouse_id
            INNER JOIN product_variants pv ON pv.id = di.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            LEFT JOIN service_request_items sri ON sri.id = di.request_item_id
            LEFT JOIN service_requests sr ON sr.id = sri.request_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY d.delivered_at DESC, d.id DESC, di.id
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function adjustments(array $filters, int $limit): array
    {
        $where = ['1=1'];
        $params = [];
        $this->dateFilters($filters, $where, $params, 'a.occurred_at', 'adj');
        $this->catalogFilters($filters, $where, $params, 'w', 'c', 'p', 'pv', 's');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'a.status = :adj_status';
            $params['adj_status'] = $status;
        }

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $where[] = '(a.affected_employee_id = :adj_employee OR a.responsible_employee_id = :adj_responsible)';
            $params['adj_employee'] = $employeeId;
            $params['adj_responsible'] = $employeeId;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(a.adjustment_code LIKE :adj_q1 OR a.reason LIKE :adj_q2 OR COALESCE(a.document_number, "") LIKE :adj_q3 OR COALESCE(a.affected_employee_label_snapshot, "") LIKE :adj_q4 OR p.name LIKE :adj_q5)';
            foreach (range(1, 5) as $index) {
                $params['adj_q' . $index] = $term;
            }
        }

        $sql = '
            SELECT
                a.occurred_at,
                a.adjustment_code,
                a.adjustment_type,
                w.code AS warehouse_code,
                COALESCE(a.affected_employee_label_snapshot, "—") AS affected_employee,
                c.name AS category_name,
                p.name AS product_name,
                pv.internal_code AS variant_code,
                COALESCE(s.name, "S/T") AS size_name,
                CAST(a.quantity AS DECIMAL(14,3)) AS quantity,
                a.status,
                a.responsible_label_snapshot AS responsible,
                COALESCE(a.document_number, "—") AS document_number,
                a.reason
            FROM inventory_adjustments a
            INNER JOIN warehouses w ON w.id = a.warehouse_id
            INNER JOIN product_variants pv ON pv.id = a.product_variant_id
            INNER JOIN products p ON p.id = pv.product_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN sizes s ON s.id = pv.size_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.occurred_at DESC, a.id DESC
            LIMIT ' . $limit;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function dateFilters(
        array $filters,
        array &$where,
        array &$params,
        string $field,
        string $prefix
    ): void {
        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '') {
            $where[] = $field . ' >= :' . $prefix . '_from';
            $params[$prefix . '_from'] = $from . ' 00:00:00';
        }

        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '') {
            $where[] = $field . ' <= :' . $prefix . '_to';
            $params[$prefix . '_to'] = $to . ' 23:59:59';
        }
    }

    private function catalogFilters(
        array $filters,
        array &$where,
        array &$params,
        string $warehouseAlias,
        string $categoryAlias,
        string $productAlias,
        string $variantAlias,
        string $sizeAlias
    ): void {
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            $where[] = $warehouseAlias . '.id = :catalog_warehouse';
            $params['catalog_warehouse'] = $warehouseId;
        }

        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = $categoryAlias . '.id = :catalog_category';
            $params['catalog_category'] = $categoryId;
        }

        $productId = (int) ($filters['product_id'] ?? 0);
        if ($productId > 0) {
            $where[] = $productAlias . '.id = :catalog_product';
            $params['catalog_product'] = $productId;
        }

        $sizeId = (int) ($filters['size_id'] ?? 0);
        if ($sizeId > 0) {
            $where[] = $sizeAlias . '.id = :catalog_size';
            $params['catalog_size'] = $sizeId;
        }

        $sex = trim((string) ($filters['sex'] ?? ''));
        if (in_array($sex, ['masculino', 'femenino', 'unisex', 'no_aplica'], true)) {
            $where[] = $productAlias . '.sex = :catalog_sex';
            $params['catalog_sex'] = $sex;
        }
    }
}
