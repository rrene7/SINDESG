<?php
declare(strict_types=1);
// Product totals and allowed P4 scope were checked by P4DashboardData.
// $product is the aggregate for exactly one product_id in exactly one P4.
?>
<details class="p4-v2-product" <?= !empty($expandFirst) ? 'open' : '' ?>>
    <summary>
        <span class="p4-v2-product-name"><?= e($product['name']) ?></span>
        <span class="p4-v2-product-total"><?= e(sindesg_units((float)$product['total'])) ?> unid.</span>
    </summary>
    <div class="p4-v2-product-sizes">
        <?php if (!$product['first_sizes']): ?>
            <p class="p4-v2-inventory-note">Tallas no incluidas en este resumen.</p>
        <?php else: ?>
            <?php foreach ($product['first_sizes'] as $size): ?>
                <div class="p4-v2-stock-row">
                    <span><?= e($size['name']) ?></span>
                    <strong><?= e(\App\Services\P4QuantityDisplay::units((float)$size['quantity'])) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($product['more_sizes']): ?>
            <details class="p4-v2-stock-more">
                <summary>Ver <?= e(count($product['more_sizes'])) ?> tallas más ↓</summary>
                <div class="p4-v2-stock-extra">
                    <?php foreach ($product['more_sizes'] as $size): ?>
                        <div class="p4-v2-stock-row">
                            <span><?= e($size['name']) ?></span>
                            <strong><?= e(sindesg_units((float)$size['quantity'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
        <?php if (!$product['complete']): ?>
            <p class="p4-v2-inventory-note">
                Tallas detalladas: <?= e((int)$product['shown_variants']) ?> de
                <?= e((int)$product['variant_count']) ?> variantes.
                El total del artículo sí incluye todas.
            </p>
        <?php endif; ?>
    </div>
</details>
