<?php
declare(strict_types=1);

$employeeLabel = $historyEmployee['display_name'] ?? ($deliveryHistory[0]['recipient_label_snapshot'] ?? ('Funcionario #' . $historyEmployeeId));
$employeeDocument = $historyEmployee['document_number'] ?? ($deliveryHistory[0]['recipient_document_snapshot'] ?? '');
$totalUnits = array_sum(array_map(
    static fn (array $row): float => (float) $row['quantity'],
    $deliveryHistory
));
?>
<section class="delivery-hero compact">
    <div>
        <span class="eyebrow">HISTORIAL DE DOTACIÓN</span>
        <h1><?= e($employeeLabel) ?></h1>
        <p><?= e($employeeDocument ?: 'Sin cédula disponible') ?> · <?= e(count($deliveryHistory)) ?> renglones · <?= e(number_format($totalUnits, 3, '.', ',')) ?> unidades entregadas</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=deliveries')) ?>">← Despachos</a>
</section>

<?php if ($deliveryHistory === []): ?>
    <section class="delivery-empty standalone">
        <div class="delivery-empty-mark">DI</div>
        <h2>Sin entregas registradas</h2>
        <p>Este funcionario todavía no tiene dotaciones confirmadas en SINDESG.</p>
    </section>
<?php else: ?>
    <section class="delivery-panel">
        <div class="responsive-table">
            <table>
                <thead>
                <tr><th>Fecha</th><th>Despacho</th><th>P4</th><th>Artículo</th><th>Variante</th><th>Talla</th><th>Cantidad</th><th>Solicitud</th></tr>
                </thead>
                <tbody>
                <?php foreach ($deliveryHistory as $row): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $row['delivered_at']))) ?></td>
                        <td><a class="delivery-link" href="<?= e(sindesg_url('?module=deliveries&action=show&id=' . (int) $row['delivery_id'])) ?>"><?= e($row['delivery_code']) ?></a></td>
                        <td><?= e($row['p4_code'] . ' · ' . ($row['p4_name'] ?: 'P4')) ?></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><code><?= e($row['variant_code']) ?></code></td>
                        <td><?= e($row['size_name'] ?: '—') ?></td>
                        <td class="delivery-qty"><?= e(number_format((float) $row['quantity'], 3, '.', ',')) ?></td>
                        <td><?= e($row['request_code'] ?: 'Entrega manual') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
