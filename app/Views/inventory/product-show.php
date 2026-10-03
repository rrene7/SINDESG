<?php
declare(strict_types=1);

$productTotalStock = 0.0;
$productCentralStock = 0.0;
foreach ($variants as $variantStockRow) {
    $productTotalStock += (float) ($variantStockRow['stock_total'] ?? 0);
    $productCentralStock += (float) ($variantStockRow['stock_central'] ?? 0);
}
?>
<section class="product-detail">
    <div class="product-detail-visual">
        <?php if (!empty($product['primary_image'])): ?>
            <img src="<?= e(sindesg_upload_url((string) $product['primary_image'])) ?>" alt="<?= e($product['name']) ?>">
        <?php else: ?>
            <div class="detail-image-placeholder">
                <span><?= e(substr((string) $product['category_code'], 0, 2)) ?></span>
                <small>Fotografía pendiente</small>
            </div>
        <?php endif; ?>
    </div>

    <div class="product-detail-copy">
        <span class="eyebrow"><?= e($product['category_name']) ?></span>
        <h1><?= e($product['name']) ?></h1>
        <code><?= e($product['internal_code']) ?></code>

        <?php if (!empty($product['description'])): ?>
            <p><?= e($product['description']) ?></p>
        <?php endif; ?>

        <dl class="detail-list">
            <div><dt>Aplicación</dt><dd><?= e(ucfirst((string) $product['sex'])) ?></dd></div>
            <div><dt>Color general</dt><dd><?= e($product['color'] ?: 'No definido') ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(ucfirst((string) $product['status'])) ?></dd></div>
            <div><dt>Variantes</dt><dd><?= e(count($variants)) ?></dd></div>
            <div class="stock-detail"><dt>Stock institucional</dt><dd><?= e(number_format($productTotalStock, 0, '.', ',')) ?></dd></div>
            <div class="stock-detail"><dt>Almacén Central</dt><dd><?= e(number_format($productCentralStock, 0, '.', ',')) ?></dd></div>
        </dl>

        <form class="photo-update-form" method="post" enctype="multipart/form-data"
              action="<?= e(sindesg_url('?module=inventory&action=save-image')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
            <label>
                <span><?= !empty($product['primary_image']) ? 'Reemplazar fotografía' : 'Cargar fotografía' ?></span>
                <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp" required>
            </label>
            <button class="secondary-button" type="submit">Guardar fotografía</button>
        </form>

        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory')) ?>">← Volver al inventario</a>
    </div>
</section>

<section class="variant-layout">
    <article class="variant-table-panel">
        <div class="inventory-section-head">
            <div>
                <span class="eyebrow">VARIANTES Y EXISTENCIAS</span>
                <h2>Tallas, marcas, códigos y stock</h2>
            </div>
        </div>

        <?php if ($variants === []): ?>
            <div class="catalog-empty compact">
                <h3>Este artículo aún no tiene variantes</h3>
                <p>Agregue talla, marca o código para diferenciar las presentaciones reales.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table">
                <table>
                    <thead>
                    <tr>
                        <th>Código SINDESG</th>
                        <th>Talla</th>
                        <th>Marca</th>
                        <th>Cód. institucional</th>
                        <th>Cód. proveedor</th>
                        <th>Color</th>
                        <th>Central</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($variants as $variant): ?>
                        <tr>
                            <td><code><?= e($variant['internal_code']) ?></code></td>
                            <td><?= e($variant['size_name'] ?: '—') ?></td>
                            <td><?= e($variant['brand_name'] ?: '—') ?></td>
                            <td><?= e($variant['institutional_code'] ?: '—') ?></td>
                            <td><?= e($variant['supplier_code'] ?: '—') ?></td>
                            <td><?= e($variant['color'] ?: $product['color'] ?: '—') ?></td>
                            <td class="stock-cell"><?= e(number_format((float) $variant['stock_central'], 0, '.', ',')) ?></td>
                            <td class="stock-cell strong"><?= e(number_format((float) $variant['stock_total'], 0, '.', ',')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <aside class="variant-form-panel">
        <span class="eyebrow">AGREGAR VARIANTE</span>
        <h2>Nueva presentación</h2>
        <p>La talla y la marca se crean automáticamente si todavía no existen en el catálogo.</p>

        <form method="post" action="<?= e(sindesg_url('?module=inventory&action=save-variant')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">

            <label>
                <span>Talla</span>
                <input type="text" name="size_value" list="known-sizes" maxlength="40" placeholder="Ej.: M, 34, 8">
                <datalist id="known-sizes">
                    <?php foreach ($sizes as $size): ?>
                        <option value="<?= e($size['name']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label>
                <span>Marca</span>
                <input type="text" name="brand_name" list="known-brands" maxlength="120" placeholder="Ej.: Proveedor o marca">
                <datalist id="known-brands">
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= e($brand['name']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label>
                <span>Código institucional / ISTMO</span>
                <input type="text" name="institutional_code" maxlength="120" placeholder="Opcional">
            </label>

            <label>
                <span>Código proveedor</span>
                <input type="text" name="supplier_code" maxlength="120" placeholder="Opcional">
            </label>

            <label>
                <span>Color específico</span>
                <input type="text" name="color" maxlength="80" placeholder="Opcional">
            </label>

            <button class="primary-button" type="submit">Agregar variante</button>
        </form>
    </aside>
</section>

<section class="kardex-panel">
    <div class="inventory-section-head">
        <div>
            <span class="eyebrow">KARDEX</span>
            <h2>Movimientos recientes</h2>
        </div>
        <span class="ledger-rule">Las existencias se calculan desde este historial.</span>
    </div>

    <?php if ($recentMovements === []): ?>
        <div class="catalog-empty compact">
            <h3>Sin movimientos registrados</h3>
            <p>Las recepciones, producción, transferencias y despachos de las próximas fases generarán aquí sus movimientos automáticamente.</p>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Almacén</th>
                    <th>Variante</th>
                    <th>Movimiento</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Referencia</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentMovements as $movement): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $movement['occurred_at']))) ?></td>
                        <td><?= e($movement['warehouse_name']) ?></td>
                        <td>
                            <code><?= e($movement['variant_code']) ?></code>
                            <?php if (!empty($movement['size_name'])): ?>
                                <small class="table-subline">Talla <?= e($movement['size_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e(str_replace('_', ' ', ucfirst((string) $movement['movement_kind']))) ?></td>
                        <td class="movement-in"><?= $movement['direction'] === 'IN' ? e(number_format((float) $movement['quantity'], 0, '.', ',')) : '—' ?></td>
                        <td class="movement-out"><?= $movement['direction'] === 'OUT' ? e(number_format((float) $movement['quantity'], 0, '.', ',')) : '—' ?></td>
                        <td><?= e($movement['reference_code'] ?: $movement['document_number'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
