<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Seguimiento por hitos reales de SINDESG, no geolocalización.
 * Nunca infiere entregas ni movimientos de inventario inexistentes.
 */
final class P4RequestTracking
{
    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $quantities
     * @param array<string,mixed> $transfers
     * @return array{steps:list<array<string,mixed>>,summary:string}
     */
    public static function build(array $request, array $quantities, array $transfers): array
    {
        $status = (string) ($request['status'] ?? 'draft');
        $sent = !empty($request['submitted_at']) || $status !== 'draft';
        $inReview = in_array($status, [
            'in_review', 'approved', 'partially_approved', 'rejected', 'fulfilled', 'closed',
        ], true);
        $decided = !empty($request['reviewed_at'])
            || in_array($status, ['approved','partially_approved','rejected','fulfilled','closed'], true);
        $approved = (float) ($quantities['approved'] ?? 0);
        $sentToTransit = (float) ($quantities['dispatched'] ?? 0);
        $received = (float) ($quantities['received'] ?? 0);
        $delivered = (float) ($quantities['delivered'] ?? 0);
        $preparations = (int) ($transfers['prepared_count'] ?? 0);
        $authorized = (int) ($transfers['authorized_count'] ?? 0);
        // Approval is the request's obligation; received stock is not the full target.
        $pendingDelivery = max(0.0, $approved - $delivered);
        $deliveryComplete = $approved > 0 && $delivered > 0 && $pendingDelivery < 0.0005;
        $deliveryStarted = $delivered > 0 && !$deliveryComplete;
        $deliveryDetail = $approved > 0
            ? P4QuantityDisplay::units($delivered) . ' de ' . P4QuantityDisplay::units($approved)
                . ' unidades aprobadas entregadas'
                . ($pendingDelivery >= 0.0005
                    ? ' · ' . P4QuantityDisplay::units($pendingDelivery) . ' pendientes'
                    : ' · dotación aprobada completada')
            : P4QuantityDisplay::units($delivered) . ' unidades entregadas a funcionarios';
        $latest = static function (mixed $value): ?string {
            return is_string($value) && trim($value) !== '' ? $value : null;
        };
        $steps = [
            ['key'=>'draft','label'=>'Preparación P4','done'=>true,
                'date'=>$latest($request['created_at'] ?? null),
                'detail'=>'Solicitud creada y destinatarios registrados'],
            ['key'=>'submitted','label'=>'Enviada al Central','done'=>$sent,
                'date'=>$latest($request['submitted_at'] ?? null),
                'detail'=>'El Central puede iniciar la revisión'],
            ['key'=>'review','label'=>'Revisión del Central','done'=>$inReview,
                'date'=>$latest($request['reviewed_at'] ?? null),
                'detail'=>$decided ? 'Decisión registrada' : 'Pendiente de decisión'],
            ['key'=>'approved','label'=>'Aprobación de cantidades','done'=>$approved > 0,
                'date'=>$latest($request['reviewed_at'] ?? null),
                'detail'=>P4QuantityDisplay::units($approved) . ' unidades aprobadas'],
            ['key'=>'prepared','label'=>'Preparación del despacho','done'=>$preparations > 0,
                'date'=>$latest($transfers['last_prepared_at'] ?? null),
                'detail'=>$authorized > 0 ? 'Existe transferencia autorizada' : 'Pendiente de preparación/autorización'],
            ['key'=>'dispatched','label'=>'Salida de Central / Tránsito','done'=>$sentToTransit > 0,
                'date'=>$latest($transfers['last_dispatched_at'] ?? null),
                'detail'=>P4QuantityDisplay::units($sentToTransit) . ' unidades despachadas'],
            ['key'=>'received','label'=>'Recepción confirmada en P4','done'=>$received > 0,
                'date'=>$latest($transfers['last_received_at'] ?? null),
                'detail'=>P4QuantityDisplay::units($received) . ' unidades recibidas'],
            ['key'=>'delivered','label'=>'Entrega individual','done'=>$deliveryComplete,
                'in_progress'=>$deliveryStarted,
                'date'=>null,
                'detail'=>$deliveryDetail],
        ];
        $summary = match (true) {
            $status === 'draft' => 'Borrador P4: falta enviar al Almacén Central.',
            $status === 'rejected' => 'La solicitud fue rechazada por Central.',
            $deliveryComplete => 'Entrega de la dotación aprobada completada.',
            $deliveryStarted && $approved > 0 => 'Entrega parcial: '
                . P4QuantityDisplay::units($delivered) . ' de ' . P4QuantityDisplay::units($approved)
                . ' unidades aprobadas entregadas; '
                . P4QuantityDisplay::units($pendingDelivery) . ' pendientes.',
            $deliveryStarted => 'Hay entregas individuales registradas que requieren conciliación con la cantidad aprobada.',
            $received > 0 => 'El P4 confirmó recepción; puede continuar entregando.',
            $sentToTransit > 0 => 'La dotación salió de Central y se encuentra en tránsito o entrega parcial.',
            $preparations > 0 => 'Transferencia en preparación o autorización en el Central.',
            $approved > 0 => 'Cantidades aprobadas; pendiente de preparación logística.',
            $decided => 'El Central registró una decisión sin aprobación de cantidades.',
            $inReview => 'Solicitud en revisión del Almacén Central.',
            default => 'Solicitud enviada; pendiente de revisión del Almacén Central.',
        };
        return ['steps'=>$steps,'summary'=>$summary];
    }
}
