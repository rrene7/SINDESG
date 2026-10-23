<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

/**
 * Read-only, server-paginated operational listings for the Central dashboard.
 * Counts remain aligned with DashboardRepository::operationalSummary().
 */
final class DashboardOperationalFeed
{
    public const PAGE_SIZE = 8;
    private const LABELS = [
        'submitted' => 'Enviada',
        'in_review' => 'En revisión',
        'approved' => 'Aprobada',
        'partially_approved' => 'Aprobada parcial',
        'dispatched' => 'En tránsito',
    ];

    public function __construct(private PDO $pdo) {}

    public static function permissionFor(string $kind): string
    {
        return match ($kind) {
            'review', 'ready' => 'requests.view',
            'transit' => 'distribution.view',
            'p4' => 'warehouses.view',
            default => throw new InvalidArgumentException('Filtro operativo desconocido.'),
        };
    }

    /** @return array{kind:string,items:list<array<string,mixed>>,total:int,page:int,pages:int,page_size:int} */
    public function fetch(string $kind, int $page = 1, string $search = ''): array
    {
        self::permissionFor($kind);
        $search = mb_substr(trim($search), 0, 100);
        $page = min(100000, max(1, $page));

        $from = '';
        $where = '';
        $fields = '';
        $sort = '';
        if ($kind === 'review' || $kind === 'ready') {
            $from = 'FROM service_requests r
                INNER JOIN warehouses w ON w.id = r.p4_warehouse_id';
            $where = $kind === 'review'
                ? "r.status IN ('submitted', 'in_review')"
                : "r.status IN ('approved', 'partially_approved') AND EXISTS (
                    SELECT 1 FROM service_request_items eligible
                    WHERE eligible.request_id = r.id
                      AND eligible.quantity_approved > eligible.quantity_dispatched
                )";
            $fields = "r.id, r.request_code AS code, r.status,
                r.requested_at AS activity_at,
                COALESCE(w.organization_unit_name_snapshot, w.name) AS location_name,
                (SELECT COUNT(*) FROM service_request_items i WHERE i.request_id = r.id) AS item_count,
                (SELECT COALESCE(SUM(i.quantity_requested),0)
                    FROM service_request_items i WHERE i.request_id = r.id) AS quantity,
                (SELECT COALESCE(SUM(GREATEST(i.quantity_approved - i.quantity_dispatched,0)),0)
                    FROM service_request_items i WHERE i.request_id = r.id) AS pending_quantity";
            $sort = 'r.requested_at DESC, r.id DESC';
            $searchSql = '(COALESCE(w.organization_unit_name_snapshot, w.name) LIKE :term
                OR w.code LIKE :term2 OR r.request_code LIKE :term3)';
        } elseif ($kind === 'transit') {
            $from = 'FROM distributions d
                INNER JOIN warehouses w ON w.id = d.destination_warehouse_id';
            $where = "d.status = 'dispatched'";
            $fields = "d.id, d.transfer_code AS code, d.status,
                d.dispatched_at AS activity_at,
                COALESCE(w.organization_unit_name_snapshot, w.name) AS location_name,
                (SELECT COUNT(*) FROM distribution_items di WHERE di.distribution_id = d.id) AS item_count,
                (SELECT COALESCE(SUM(di.quantity),0)
                    FROM distribution_items di WHERE di.distribution_id = d.id) AS quantity,
                0 AS pending_quantity";
            $sort = 'd.dispatched_at DESC, d.id DESC';
            $searchSql = '(COALESCE(w.organization_unit_name_snapshot, w.name) LIKE :term
                OR w.code LIKE :term2 OR d.transfer_code LIKE :term3)';
        } else {
            $from = 'FROM warehouses w';
            $where = "w.warehouse_type = 'P4' AND w.is_active = 1";
            $fields = "w.id, w.code, 'active' AS status, NULL AS activity_at,
                COALESCE(w.organization_unit_name_snapshot, w.name) AS location_name,
                0 AS item_count, 0 AS quantity, 0 AS pending_quantity";
            $sort = 'w.name, w.id';
            $searchSql = '(COALESCE(w.organization_unit_name_snapshot, w.name) LIKE :term
                OR w.code LIKE :term2 OR w.name LIKE :term3)';
        }

        $params = [];
        if ($search !== '') {
            $where .= ' AND ' . $searchSql;
            $params = [
                'term' => '%' . $search . '%',
                'term2' => '%' . $search . '%',
                'term3' => '%' . $search . '%',
            ];
        }
        $count = $this->pdo->prepare('SELECT COUNT(*) ' . $from . ' WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($pages, $page);
        $offset = ($page - 1) * self::PAGE_SIZE;
        // The limit and offset are integers computed on the server, not SQL inputs.
        $statement = $this->pdo->prepare('SELECT ' . $fields . ' ' . $from
            . ' WHERE ' . $where . ' ORDER BY ' . $sort
            . ' LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset);
        $statement->execute($params);
        $items = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) $row['status'];
            $items[] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'status' => $status,
                'status_label' => $kind === 'p4'
                    ? 'P4 activo' : (self::LABELS[$status] ?? $status),
                'location_name' => (string) $row['location_name'],
                'item_count' => (int) $row['item_count'],
                'quantity' => (float) $row['quantity'],
                'pending_quantity' => (float) $row['pending_quantity'],
                'activity_at' => $row['activity_at'],
            ];
        }
        return [
            'kind' => $kind,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'page_size' => self::PAGE_SIZE,
        ];
    }
}
