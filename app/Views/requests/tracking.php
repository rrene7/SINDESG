<?php
declare(strict_types=1);
/** @var array{steps:list<array<string,mixed>>,summary:string} $requestTracking */
?>
<section class="request-panel request-tracking-panel" id="seguimiento-p4">
    <div class="request-section-head">
        <div>
            <span class="eyebrow">SEGUIMIENTO P4 · ETAPAS REALES</span>
            <h2>¿Dónde está mi solicitud?</h2>
            <p><?= e($requestTracking['summary']) ?></p>
        </div>
        <span class="request-tracking-kind">Seguimiento del trámite, no ubicación GPS</span>
    </div>
    <ol class="request-tracking-steps">
        <?php foreach ($requestTracking['steps'] as $step): ?>
            <li class="request-tracking-step <?= !empty($step['done']) ? 'completed' : 'pending' ?>">
                <span class="request-tracking-dot" aria-hidden="true"><?= !empty($step['done']) ? '✓' : '·' ?></span>
                <div>
                    <strong><?= e($step['label']) ?></strong>
                    <small><?= e($step['detail']) ?></small>
                    <?php if (!empty($step['date'])): ?>
                        <time><?= e(date('d/m/Y H:i', strtotime((string) $step['date']))) ?></time>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
    <p class="request-cell-sub">
        Se actualiza con cambios registrados en SINDESG: envío, revisión, aprobación,
        preparación, despacho, recepción y entregas. Si existen entregas parciales,
        las cantidades mostradas corresponden solo a movimientos confirmados.
    </p>
</section>
