<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\P4NominalReportState;
use PDO;

/**
 * Read-only report of nominal request lines.
 * Authorization is performed in SQL before COUNT, filtering or pagination.
 * No RRHH2029 connection and no mutations of operational records.
 */
final class P4NominalReportRepository
{
    public function __construct(private PDO $pdo) {}

    public static function filters(array $query): array
    {
        $value = static fn(string $key, int $max): string =>
            substr(trim(is_scalar($query[$key] ?? null) ? (string)$query[$key] : ''), 0, $max);
        $situation = $value('situation', 32);
        $allowed = ['','review','rejected','waiting_receipt','ready','partial','delivered','anomaly','duplicate'];
        return [
            'q' => $value('q', 120),
            'unit' => $value('unit', 120),
            'request' => $value('request', 80),
            'warehouse_id' => max(0, (int)($query['warehouse_id'] ?? 0)),
            'product_id' => max(0, (int)($query['product_id'] ?? 0)),
            'size_id' => max(0, (int)($query['size_id'] ?? 0)),
            'situation' => in_array($situation, $allowed, true) ? $situation : '',
            'per_page' => in_array((int)($query['per_page'] ?? 25), [10,25,50], true) ? (int)$query['per_page'] : 25,
            'page' => max(1, min((int)($query['page'] ?? 1), 1000000)),
        ];
    }

    /** Only ADMIN/CENTRAL should pass $institutional=true (controller checks roles). */
    public function page(int $userId, bool $institutional, array $filters): array
    {
        $size = in_array((int)($filters['per_page'] ?? 25), [10,25,50], true)
            ? (int)$filters['per_page'] : 25;
        $empty = ['rows'=>[], 'total'=>0, 'page'=>1, 'pages'=>1, 'per_page'=>$size];
        if ($userId <= 0) return $empty;

        [$from, $params] = $this->base($userId, $institutional);
        $this->addFilters($from, $params, $filters);
        $count = $this->pdo->prepare('SELECT COUNT(*) ' . $from);
        $this->bind($count, $params);
        $count->execute();
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, (int)($filters['page'] ?? 1)), $pages);

        $query = <<<'SQL'
            SELECT i.id AS request_item_id,
                   i.beneficiary_employee_id, i.beneficiary_label_snapshot,
                   i.beneficiary_document_snapshot, i.beneficiary_unit_name_snapshot,
                   i.beneficiary_position_snapshot, i.beneficiary_rank_snapshot,
                   r.id AS request_id, r.request_code, r.requested_at, r.status AS request_status,
                   w.id AS p4_warehouse_id, w.code AS p4_code,
                   COALESCE(w.organization_unit_name_snapshot,w.name) AS p4_name,
                   p.id AS product_id, p.name AS product_name,
                   pv.internal_code AS variant_code, pv.id AS variant_id,
                   s.id AS size_id, COALESCE(s.name,'S/T') AS size_name,
                   i.quantity_requested, i.quantity_approved, i.quantity_dispatched,
                   i.quantity_received, i.quantity_delivered,
                   (SELECT COUNT(*)
                      FROM service_request_items other
                      WHERE other.id <> i.id
                        AND other.request_id = i.request_id
                        AND other.beneficiary_employee_id = i.beneficiary_employee_id
                        AND other.product_variant_id = i.product_variant_id
                   ) AS overlapping_lines,
                   (SELECT COALESCE(SUM(di.quantity),0)
                      FROM individual_delivery_items di
                      JOIN individual_deliveries d ON d.id=di.delivery_id
                      WHERE di.request_item_id=i.id AND di.product_variant_id=i.product_variant_id
                        AND d.status='confirmed' AND d.p4_warehouse_id=r.p4_warehouse_id
                        AND d.recipient_employee_id=i.beneficiary_employee_id
                   ) AS evidenced_delivery_quantity,
                   (SELECT COUNT(DISTINCT d.id)
                      FROM individual_delivery_items di
                      JOIN individual_deliveries d ON d.id=di.delivery_id
                      WHERE di.request_item_id=i.id AND di.product_variant_id=i.product_variant_id
                        AND d.status='confirmed' AND d.p4_warehouse_id=r.p4_warehouse_id
                        AND d.recipient_employee_id=i.beneficiary_employee_id
                   ) AS confirmed_deliveries,
                   (SELECT MAX(d.delivered_at)
                      FROM individual_delivery_items di
                      JOIN individual_deliveries d ON d.id=di.delivery_id
                      WHERE di.request_item_id=i.id AND di.product_variant_id=i.product_variant_id
                        AND d.status='confirmed' AND d.p4_warehouse_id=r.p4_warehouse_id
                        AND d.recipient_employee_id=i.beneficiary_employee_id
                   ) AS latest_delivery_at,
                   (SELECT d.id
                      FROM individual_delivery_items di
                      JOIN individual_deliveries d ON d.id=di.delivery_id
                      WHERE di.request_item_id=i.id AND di.product_variant_id=i.product_variant_id
                        AND d.status='confirmed' AND d.p4_warehouse_id=r.p4_warehouse_id
                        AND d.recipient_employee_id=i.beneficiary_employee_id
                        AND NOT EXISTS(
                          SELECT 1 FROM individual_delivery_items mixed
                          WHERE mixed.delivery_id=d.id AND mixed.request_item_id IS NULL
                        )
                        AND NOT EXISTS(
                          SELECT 1 FROM individual_delivery_items other_item
                          JOIN service_request_items other_line ON other_line.id=other_item.request_item_id
                          JOIN service_requests other_request ON other_request.id=other_line.request_id
                          WHERE other_item.delivery_id=d.id
                            AND (other_request.p4_warehouse_id<>d.p4_warehouse_id
                                OR other_line.beneficiary_employee_id<>d.recipient_employee_id
                                OR other_line.product_variant_id<>other_item.product_variant_id)
                        )
                   ) AS last_safe_delivery_id,
                   (SELECT MAX(im.id)
                      FROM individual_delivery_items di
                      JOIN individual_deliveries d ON d.id=di.delivery_id
                      JOIN inventory_movements im
                        ON im.source_key=CONCAT('individual-delivery:',di.delivery_id,':item:',di.id)
                       AND im.reference_type='individual_delivery' AND im.reference_id=d.id
                       AND im.warehouse_id=r.p4_warehouse_id
                       AND im.product_variant_id=i.product_variant_id
                       AND im.movement_kind='individual_delivery' AND im.direction='OUT'
                      WHERE di.request_item_id=i.id AND di.product_variant_id=i.product_variant_id
                        AND d.status='confirmed' AND d.p4_warehouse_id=r.p4_warehouse_id
                        AND d.recipient_employee_id=i.beneficiary_employee_id
                   ) AS last_movement_id
        SQL;
        $stmt = $this->pdo->prepare($query . ' ' . $from
            . ' ORDER BY r.requested_at DESC, r.id DESC, i.id ASC LIMIT :limit OFFSET :offset');
        $this->bind($stmt, $params);
        $stmt->bindValue(':limit', $size, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $size, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['classification'] = P4NominalReportState::classify($row);
        }
        unset($row);

        return ['rows'=>$rows, 'total'=>$total, 'page'=>$page,
            'pages'=>$pages, 'per_page'=>$size];
    }

    /** Choices are derived only from authorized, active nominal P4 records. */
    public function options(int $userId, bool $institutional): array
    {
        $options = ['warehouses'=>[], 'products'=>[], 'sizes'=>[]];
        if ($userId <= 0) return $options;
        [$from,$params] = $this->base($userId,$institutional);
        $sql = 'SELECT DISTINCT w.id AS warehouse_id, w.code AS warehouse_code,
                COALESCE(w.organization_unit_name_snapshot,w.name) AS warehouse_name,
                p.id AS product_id,p.name AS product_name,
                COALESCE(s.id,0) AS size_id,COALESCE(s.name,\'S/T\') AS size_name ' . $from;
        $stmt = $this->pdo->prepare($sql . ' ORDER BY warehouse_name,product_name,size_name');
        $this->bind($stmt,$params);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $options['warehouses'][(int)$r['warehouse_id']] = [
                'id'=>(int)$r['warehouse_id'], 'name'=>(string)$r['warehouse_name']
            ];
            $options['products'][(int)$r['product_id']] = [
                'id'=>(int)$r['product_id'], 'name'=>(string)$r['product_name']
            ];
            $options['sizes'][(int)$r['size_id']] = [
                'id'=>(int)$r['size_id'], 'name'=>(string)$r['size_name']
            ];
        }
        foreach ($options as &$values) $values = array_values($values);
        unset($values);
        return $options;
    }

    private function base(int $userId, bool $institutional): array
    {
        $from = <<<'SQL'
            FROM service_request_items i
            JOIN service_requests r ON r.id=i.request_id
            JOIN warehouses w ON w.id=r.p4_warehouse_id
            JOIN product_variants pv ON pv.id=i.product_variant_id
            JOIN products p ON p.id=pv.product_id
            LEFT JOIN sizes s ON s.id=pv.size_id
            WHERE i.beneficiary_employee_id IS NOT NULL AND w.warehouse_type='P4'
        SQL;
        $params=[];
        if (!$institutional) {
            $from .= <<<'SQL'

              AND w.is_active=1
              AND EXISTS (
                  SELECT 1 FROM security_user_p4_scopes scope
                  WHERE scope.user_id=:auth_user AND scope.warehouse_id=r.p4_warehouse_id
              )
            SQL;
            $params[':auth_user']=$userId;
        }
        return [$from,$params];
    }

    private function addFilters(string &$from, array &$params, array $f): void
    {
        foreach (['warehouse_id'=>'w.id','product_id'=>'p.id','size_id'=>'s.id'] as $key=>$column) {
            $id=(int)($f[$key]??0);
            if ($id>0) { $from.=' AND '.$column.'=:'.$key; $params[':'.$key]=$id; }
        }
        if (($f['request']??'')!=='') {
            $from.=' AND r.request_code=:request_code';
            $params[':request_code']=$f['request'];
        }
        if (($f['unit']??'')!=='') {
            $from.=" AND LOWER(COALESCE(i.beneficiary_unit_name_snapshot,'')) LIKE :unit_filter ESCAPE '!'";
            $params[':unit_filter']='%'.$this->escapeLike(strtolower($f['unit'])).'%';
        }
        if (($f['q']??'')!=='') {
            $escaped='%'.$this->escapeLike(strtolower($f['q'])).'%';
            $conditions = [
                'i.beneficiary_label_snapshot','i.beneficiary_document_snapshot',
                'i.beneficiary_unit_name_snapshot','p.name','pv.internal_code',
                'r.request_code',
            ];
            $parts=[];
            foreach ($conditions as $index=>$column) {
                $param=':q'.$index;
                $parts[]="LOWER(COALESCE($column,'')) LIKE $param ESCAPE '!'";
                $params[$param]=$escaped;
            }
            $from.=' AND ('.implode(' OR ',$parts).')';
        }
        $state=(string)($f['situation']??'');
        $situations = [
            'review' => "r.status IN ('draft','submitted','in_review') AND i.quantity_approved=0",
            'rejected' => "r.status='rejected'",
            'waiting_receipt' => 'i.quantity_approved>i.quantity_received',
            'ready' => 'i.quantity_received>i.quantity_delivered',
            'partial' => 'i.quantity_delivered>0 AND i.quantity_delivered<i.quantity_approved',
            'delivered' => 'i.quantity_approved>0 AND i.quantity_delivered>=i.quantity_approved',
            'anomaly' => '(i.quantity_delivered>i.quantity_received OR i.quantity_received>i.quantity_approved)',
            'duplicate' => '(SELECT COUNT(*) FROM service_request_items other
                WHERE other.request_id=i.request_id AND other.id<>i.id
                  AND other.beneficiary_employee_id=i.beneficiary_employee_id
                  AND other.product_variant_id=i.product_variant_id)>0',
        ];
        if (isset($situations[$state])) $from.=' AND ('.$situations[$state].')';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!','%','_'],['!!','!%','!_'],$value);
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name=>$value) {
            $stmt->bindValue($name,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        }
    }
}
