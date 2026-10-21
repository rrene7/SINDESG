<?php
declare(strict_types=1);

namespace App\Services;

/** Pure, read-only classification. No institutional periodicity rule. */
final class P4NominalReportState
{
    public static function classify(array $row): array
    {
        $requested=(float)($row['quantity_requested']??0);
        $approved=(float)($row['quantity_approved']??0);
        $dispatched=(float)($row['quantity_dispatched']??0);
        $received=(float)($row['quantity_received']??0);
        $delivered=(float)($row['quantity_delivered']??0);
        $evidenced=(float)($row['evidenced_delivery_quantity']??0);
        $status=(string)($row['request_status']??'');
        $epsilon=0.0005;

        $logistics=match(true) {
            $status==='rejected'=>'Solicitud rechazada',
            $approved<=$epsilon && in_array($status,['draft','submitted','in_review'],true)
                =>'Pendiente de aprobación',
            $approved<=$epsilon => 'Sin unidades aprobadas',
            $received+$epsilon >= $approved => 'Recibido en P4',
            $received>0 => 'Recepción parcial en P4',
            $dispatched>0 => 'Despachado / en tránsito',
            default => 'Aprobado; pendiente de despacho',
        };
        $delivery=match(true) {
            $approved>$epsilon && $delivered+$epsilon >= $approved => 'Entrega completada',
            $delivered>$epsilon => 'Entrega parcial al funcionario',
            $received>$epsilon => 'Pendiente de entregar al funcionario',
            default => 'Sin entrega individual',
        };
        $warnings=[];
        if ((int)($row['overlapping_lines']??0)>0) {
            $warnings[]='Posible duplicidad nominal: verificar renglones de la misma solicitud';
        }
        if ($delivered>$received+$epsilon || $received>$approved+$epsilon
            || $approved>$requested+$epsilon) {
            $warnings[]='Cantidades inconsistentes: revisar el historial';
        }
        if (abs($evidenced-$delivered)>$epsilon) {
            $warnings[]='El total de entregas confirmadas no coincide con el acumulado nominal';
        }
        return [
            'logistics'=>$logistics,
            'delivery'=>$delivery,
            'pending_receive'=>max(0,$approved-$received),
            'ready_to_hand'=>max(0,$received-$delivered),
            'pending_individual'=>max(0,$approved-$delivered),
            'warnings'=>$warnings,
        ];
    }
}
