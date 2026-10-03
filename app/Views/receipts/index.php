<?php
declare(strict_types=1);
?>
<section class="receipts-hero">
    <div>
        <span class="eyebrow">FASE 3 · ENTRADA CONTROLADA</span>
        <h1>Recepción de lotes</h1>
        <p>Registre lo que realmente ingresa al Almacén Central. El código de recepción y el código de lote los genera SINDESG automáticamente.</p>
    </div>
    <a class="primary-button" href="<?= e(sindesg_url('?module=receipts&action=new')) ?>">+ Nueva recepción</a>
</section>

<section class="receipt-stats">
    <article>
        <span>Borradores</span>
        <strong><?= e($receiptStats['drafts']) ?></strong>
        <small>Aún no afectan el inventario</small>
    </article>
    <article>
        <span>Recepciones confirmadas</span>
        <strong><?= e($receiptStats['confirmed']) ?></strong>
        <small>Con movimientos de entrada</small>
    </article>
    <article>
        <span>Cantidad recibida</span>
        <strong><?= e(number_format((float) $receiptStats['received_quantity'], 0, '.', ',')) ?></strong>
        <small>Solo recepciones confirmadas</small>
    </article>
</section>

<section class="receipt-table-panel">
    <div class="receipt-section-head">
        <div>
            <span class="eyebrow">TRAZABILIDAD</span>
            <h2>Recepciones recientes</h2>
        </div>
    </div>

    <?php if ($receipts === []): ?>
        <div class="receipt-empty">
            <div class="receipt-empty-mark">RL</div>
            <h3>Aún no hay recepciones</h3>
            <p>La primera recepción confirmada generará automáticamente movimientos de entrada en el Almacén Central.</p>
            <a class="primary-button" href="<?= e(sindesg_url('?module=receipts&action=new')) ?>">Registrar primera recepción</a>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr>
                    <th>Recepción</th>
                    <th>Lote</th>
                    <th>Ingreso</th>
                    <th>Procedencia</th>
                    <th>Proveedor / origen</th>
                    <th>Renglones</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($receipts as $row): ?>
                    <tr>
                        <td>
                            <a class="receipt-link" href="<?= e(sindesg_url('?module=receipts&action=show&id=' . (int) $row['id'])) ?>">
                                <?= e($row['receipt_code']) ?>
                            </a>
                        </td>
                        <td><code><?= e($row['lot_code']) ?></code></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['received_at']))) ?></td>
                        <td><?= e($sourceTypes[$row['source_type']] ?? $row['source_type']) ?></td>
                        <td><?= e($row['supplier_or_origin']) ?></td>
                        <td><?= e($row['item_count']) ?></td>
                        <td><?= e(number_format((float) $row['total_quantity'], 0, '.', ',')) ?></td>
                        <td><span class="receipt-status <?= e($row['status']) ?>"><?= e($row['status'] === 'confirmed' ? 'Confirmada' : 'Borrador') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
