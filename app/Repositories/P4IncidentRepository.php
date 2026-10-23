<?php
declare(strict_types=1);
namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Presential P4 incident case ledger. Never inserts stock movements. */
final class P4IncidentRepository
{
    public const TYPES = [
        'return'=>'Devolución', 'exchange'=>'Cambio de talla',
        'deterioration'=>'Deterioro', 'replacement'=>'Reposición',
        'loss'=>'Pérdida reportada', 'other'=>'Otra incidencia',
    ];
    public const STATUS = [
        'submitted'=>'Por revisar','in_review'=>'En revisión',
        'resolved'=>'Resuelta','rejected'=>'No procedente',
    ];
    public function __construct(private PDO $pdo) {}

    public function sourceForP4(int $userId, int $itemId): ?array
    {
        if ($userId <= 0 || $itemId <= 0) return null;
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT di.id AS delivery_item_id,di.delivery_id,di.quantity AS delivered_quantity,
 d.delivery_code,d.p4_warehouse_id AS warehouse_id,d.recipient_employee_id,
 d.recipient_label_snapshot,d.recipient_document_snapshot,
 di.product_variant_id,p.name AS product_name,s.name AS size_name,
 COALESCE(w.organization_unit_name_snapshot,w.name) AS warehouse_name,
 GREATEST(di.quantity-COALESCE((
   SELECT SUM(a.quantity) FROM inventory_adjustments a
   WHERE a.source_delivery_item_id=di.id AND a.status='confirmed'
   AND a.adjustment_type IN('return','exchange')
 ),0),0) AS returnable_quantity
FROM individual_delivery_items di
JOIN individual_deliveries d ON d.id=di.delivery_id AND d.status='confirmed'
JOIN warehouses w ON w.id=d.p4_warehouse_id AND w.warehouse_type='P4' AND w.is_active=1
JOIN security_user_p4_scopes sc ON sc.warehouse_id=w.id AND sc.user_id=:user
JOIN product_variants pv ON pv.id=di.product_variant_id
JOIN products p ON p.id=pv.product_id
LEFT JOIN sizes s ON s.id=pv.size_id
WHERE di.id=:item AND di.request_item_id IS NOT NULL
LIMIT 1
SQL);
        $stmt->execute(['user'=>$userId,'item'=>$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function event(int $id,int $actor,string $action,?string $notes=null): void
    {
        $s=$this->pdo->prepare('INSERT INTO p4_incident_events(case_id,actor_user_id,action_code,note) VALUES(:id,:actor,:action,:notes)');
        $s->execute(['id'=>$id,'actor'=>$actor,'action'=>$action,'notes'=>$notes]);
    }

    public function open(int $userId,int $itemId,array $input): int
    {
        $type=(string)($input['case_type']??'');
        $quantityText=trim((string)($input['quantity']??''));
        $reason=trim((string)($input['reason']??''));
        if (!isset(self::TYPES[$type])) throw new InvalidArgumentException('Seleccione el tipo de incidencia.');
        if ($quantityText==='' || !is_numeric($quantityText) || !is_finite((float)$quantityText)
            || (float)$quantityText<=0 || round((float)$quantityText,3)!==(float)$quantityText) {
            throw new InvalidArgumentException('Indique una cantidad válida, con máximo tres decimales.');
        }
        $quantity=(float)$quantityText;
        if (mb_strlen($reason)<8 || mb_strlen($reason)>500) {
            throw new InvalidArgumentException('Describa el motivo de la incidencia (8 a 500 caracteres).');
        }
        $received=($input['physically_received']??'')==='1';
        $physicalRequired=in_array($type,['return','exchange','deterioration'],true);
        if ($physicalRequired && !$received) {
            throw new InvalidArgumentException('Debe verificar físicamente la prenda recibida en el P4.');
        }
        if ($type==='loss' && $received) {
            throw new InvalidArgumentException('Una pérdida no puede marcarse como prenda recibida.');
        }
        $condition=$received ? (string)($input['item_condition']??'') : 'not_present';
        if ($received && !in_array($condition,['reusable','non_reusable','evaluation_pending'],true)) {
            throw new InvalidArgumentException('Indique el estado de la prenda presentada.');
        }
        if ($type==='deterioration' && $received && $condition==='reusable') {
            throw new InvalidArgumentException('Revise la condición: deterioro no puede registrarse como reutilizable sin evaluación.');
        }
        if ($userId<=0 || $itemId<=0) throw new RuntimeException('Sesión o entrega inválida.');

        $this->pdo->beginTransaction();
        try {
            // Serializes concurrent reports on one physical delivery line.
            $sql='SELECT id FROM individual_delivery_items WHERE id=:id';
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite') $sql.=' FOR UPDATE';
            $lock=$this->pdo->prepare($sql);$lock->execute(['id'=>$itemId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('La entrega ya no existe.');
            $source=$this->sourceForP4($userId,$itemId);
            if (!$source) throw new RuntimeException('Entrega confirmada de otro P4 o sin autorización.');
            $pending=$this->pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM p4_incident_cases
                WHERE delivery_item_id=:item AND status IN('submitted','in_review')");
            $pending->execute(['item'=>$itemId]);
            $available=(float)$source['returnable_quantity']-(float)$pending->fetchColumn();
            if ($quantity>$available+0.000001) {
                throw new InvalidArgumentException('La cantidad supera la entregada todavía disponible para reclamos; revise casos anteriores.');
            }

            $stmt=$this->pdo->prepare(<<<'SQL'
INSERT INTO p4_incident_cases(
 case_code,delivery_item_id,warehouse_id,recipient_employee_id,product_variant_id,
 case_type,quantity,physically_received,item_condition,reason,created_by_user_id,status
) VALUES (
 :code,:item,:warehouse,:recipient,:variant,:type,:qty,:received,:condition,:reason,:user,'submitted'
)
SQL);
            $stmt->execute([
                'code'=>'TMP-INC-'.bin2hex(random_bytes(6)),
                'item'=>$itemId,'warehouse'=>$source['warehouse_id'],
                'recipient'=>$source['recipient_employee_id'],'variant'=>$source['product_variant_id'],
                'type'=>$type,'qty'=>number_format($quantity,3,'.',''),
                'received'=>$received?1:0,'condition'=>$condition,'reason'=>$reason,'user'=>$userId
            ]);
            $id=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE p4_incident_cases SET case_code=:code WHERE id=:id')
                ->execute(['code'=>sprintf('INC-%06d',$id),'id'=>$id]);
            $this->event($id,$userId,'submitted','Recepción presencial registrada, sin movimientos de inventario.');
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Central/Admin: list institutional cases. P4: only currently assigned active warehouses. */
    public function listing(int $userId,bool $institutional,int $page=1,string $status=''): array
    {
        if ($userId<=0) throw new RuntimeException('Usuario no autorizado.');
        if ($status!==''&&!isset(self::STATUS[$status])) throw new InvalidArgumentException('Estado desconocido.');
        $where=$status!==''?'c.status=:status':'1=1';
        $params=$status!==''?['status'=>$status]:[];
        if (!$institutional) {
            $where.=" AND EXISTS(SELECT 1 FROM security_user_p4_scopes sc
                JOIN warehouses scoped ON scoped.id=sc.warehouse_id
                WHERE sc.user_id=:user AND sc.warehouse_id=c.warehouse_id
                  AND scoped.warehouse_type='P4' AND scoped.is_active=1)";
            $params['user']=$userId;
        }
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM p4_incident_cases c WHERE '.$where);
        $count->execute($params);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/20));$page=min($pages,max(1,$page));
        $query=<<<SQL
SELECT c.id,c.case_code,c.case_type,c.status,c.quantity,c.physically_received,
 c.created_at,d.recipient_label_snapshot,p.name AS product_name,
 COALESCE(w.organization_unit_name_snapshot,w.name) AS warehouse_name
FROM p4_incident_cases c
JOIN individual_delivery_items di ON di.id=c.delivery_item_id
JOIN individual_deliveries d ON d.id=di.delivery_id
JOIN warehouses w ON w.id=c.warehouse_id
JOIN product_variants pv ON pv.id=c.product_variant_id
JOIN products p ON p.id=pv.product_id
WHERE $where
ORDER BY c.id DESC LIMIT 20 OFFSET %{offset}
SQL;
        // SQL page offset is an integer validated and calculated on the server.
        $query=str_replace('%{offset}',(string)(($page-1)*20),$query);
        $stmt=$this->pdo->prepare($query);
        $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'pages'=>$pages,'page'=>$page,'status'=>$status];
    }

    public function find(int $userId,int $id,bool $institutional): ?array
    {
        if ($userId<=0||$id<=0) return null;
        $sql=<<<'SQL'
SELECT c.*,di.delivery_id,d.delivery_code,d.recipient_label_snapshot,
 d.recipient_document_snapshot,di.quantity AS delivered_quantity,
 p.name AS product_name,s.name AS size_name,
 COALESCE(w.organization_unit_name_snapshot,w.name) AS warehouse_name
FROM p4_incident_cases c
JOIN individual_delivery_items di ON di.id=c.delivery_item_id
JOIN individual_deliveries d ON d.id=di.delivery_id
JOIN warehouses w ON w.id=c.warehouse_id
JOIN product_variants pv ON pv.id=c.product_variant_id
JOIN products p ON p.id=pv.product_id
LEFT JOIN sizes s ON s.id=pv.size_id
WHERE c.id=:id
SQL;
        $params=['id'=>$id];
        if (!$institutional) {
            $sql.=" AND EXISTS(SELECT 1 FROM security_user_p4_scopes sc
              JOIN warehouses w2 ON w2.id=sc.warehouse_id
              WHERE sc.user_id=:user AND sc.warehouse_id=c.warehouse_id
              AND w2.warehouse_type='P4' AND w2.is_active=1)";
            $params['user']=$userId;
        }
        $stmt=$this->pdo->prepare($sql.' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function history(int $caseId): array
    {
        $stmt=$this->pdo->prepare('SELECT action_code,actor_user_id,note,occurred_at FROM p4_incident_events WHERE case_id=:id ORDER BY id');
        $stmt->execute(['id'=>$caseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function transition(int $id,int $userId,string $action,string $notes='',int $adjustmentId=0): void
    {
        if ($id<=0||$userId<=0||!in_array($action,['review','resolve','reject'],true))
            throw new InvalidArgumentException('Operación de revisión inválida.');
        $notes=trim($notes);
        if ($action!=='review' && (mb_strlen($notes)<8||mb_strlen($notes)>1000))
            throw new InvalidArgumentException('La resolución requiere un motivo de 8 a 1000 caracteres.');
        $this->pdo->beginTransaction();
        try {
            $sql='SELECT * FROM p4_incident_cases WHERE id=:id';
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite')$sql.=' FOR UPDATE';
            $s=$this->pdo->prepare($sql);$s->execute(['id'=>$id]);$c=$s->fetch(PDO::FETCH_ASSOC);
            if (!$c) throw new RuntimeException('Incidencia inexistente.');
            if ($action==='review') {
                if ($c['status']!=='submitted') throw new RuntimeException('El caso no está pendiente de revisión.');
                $this->pdo->prepare("UPDATE p4_incident_cases SET status='in_review',reviewed_by_user_id=:user,reviewed_at=CURRENT_TIMESTAMP WHERE id=:id")
                    ->execute(['user'=>$userId,'id'=>$id]);
            } else {
                if ($c['status']!=='in_review') throw new RuntimeException('La incidencia debe estar en revisión.');
                $mustAdjust=in_array($c['case_type'],['return','exchange','replacement','deterioration'],true);
                if ($action==='resolve' && $mustAdjust && $adjustmentId<=0) {
                    throw new InvalidArgumentException('Para resolver esta devolución, cambio o deterioro debe existir un ajuste confirmado por ADMIN.');
                }
                if ($action==='resolve' && $adjustmentId>0) {
                    $x=$this->pdo->prepare("SELECT id,adjustment_type,source_delivery_item_id,quantity,warehouse_id,return_condition
                      FROM inventory_adjustments WHERE id=:id AND status='confirmed' LIMIT 1");
                    $x->execute(['id'=>$adjustmentId]);$a=$x->fetch(PDO::FETCH_ASSOC);
                    $expected=$c['case_type']==='deterioration'?['return']:[$c['case_type']];
                    if (!$a||(int)$a['source_delivery_item_id']!==(int)$c['delivery_item_id']
                        ||(int)$a['warehouse_id']!==(int)$c['warehouse_id']
                        ||!in_array($a['adjustment_type'],$expected,true)
                        ||(float)$a['quantity']+0.000001<(float)$c['quantity']
                        ||($c['case_type']==='deterioration'&&$a['return_condition']!=='non_reusable')) {
                        throw new InvalidArgumentException('El ajuste confirmado no corresponde a esta entrega, tipo, cantidad o condición.');
                    }
                }
                $status=$action==='resolve'?'resolved':'rejected';
                $this->pdo->prepare('UPDATE p4_incident_cases SET status=:status,
                    closed_by_user_id=:actor,closed_at=CURRENT_TIMESTAMP,resolution_notes=:notes,
                    linked_adjustment_id=:adjustment WHERE id=:id')->execute([
                    'status'=>$status,'actor'=>$userId,'notes'=>$notes,
                    'adjustment'=>$action==='resolve'&&$adjustmentId>0?$adjustmentId:null,'id'=>$id
                ]);
            }
            $this->event($id,$userId,$action,$notes!==''?$notes:null);
            $this->pdo->commit();
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
