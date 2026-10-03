<?php
declare(strict_types=1);
?>
<section class="receipts-hero compact">
    <div>
        <span class="eyebrow">NUEVA RECEPCIÓN</span>
        <h1>Registrar lote</h1>
        <p>Guarde primero el lote como borrador. El inventario solo aumentará cuando la recepción sea confirmada.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=receipts')) ?>">← Volver</a>
</section>

<?php if ($variantOptions === []): ?>
    <section class="receipt-empty">
        <div class="receipt-empty-mark">!</div>
        <h2>Prepare el primer artículo para recibir este lote</h2>
        <p>No debe crear una variante por separado. Registre el artículo y SINDESG generará automáticamente una primera variante stockable, incluso si no tiene talla, marca o código externo. Al terminar volverá a esta recepción.</p>
        <a class="primary-button" href="<?= e(sindesg_url('?module=inventory&action=new-product&return_to=receipts')) ?>">+ Crear artículo para este lote</a>
    </section>
<?php else: ?>
    <form class="receipt-form" method="post" action="<?= e(sindesg_url('?module=receipts&action=save-draft')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

        <section class="receipt-form-panel">
            <div class="receipt-section-head">
                <div>
                    <span class="eyebrow">DATOS DE INGRESO</span>
                    <h2>Identificación de la recepción</h2>
                </div>
                <div class="generated-codes-note">
                    <strong>Códigos automáticos</strong>
                    <span>REC-###### · LOT-######</span>
                </div>
            </div>

            <div class="receipt-form-grid">
                <label>
                    <span>Procedencia *</span>
                    <select name="source_type" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($sourceTypes as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Fecha y hora de ingreso *</span>
                    <input type="datetime-local" name="received_at" value="<?= e($defaultReceivedAt) ?>" required>
                </label>

                <label>
                    <span>Proveedor / origen *</span>
                    <input type="text" name="supplier_or_origin" maxlength="180" required placeholder="Ej.: Proveedor, institución o dependencia de origen">
                </label>

                <label>
                    <span>Documento soporte</span>
                    <input type="text" name="document_number" maxlength="120" placeholder="Orden de compra, nota, requisición, acta...">
                </label>

                <label>
                    <span>Recibido por *</span>
                    <input type="text" name="received_by_label" maxlength="160" required placeholder="Nombre del responsable que recibe">
                </label>

                <label>
                    <span>Almacén destino</span>
                    <input type="text" value="Almacén Central de Servicios Generales" readonly>
                </label>

                <label class="span-2">
                    <span>Observaciones generales</span>
                    <textarea name="notes" rows="3" placeholder="Condición del lote, referencia, particularidades..."></textarea>
                </label>
            </div>
        </section>

        <section class="receipt-form-panel">
            <div class="receipt-section-head">
                <div>
                    <span class="eyebrow">DETALLE DEL LOTE</span>
                    <h2>Artículos recibidos</h2>
                </div>
                <button class="secondary-button" type="button" data-receipt-add>+ Agregar renglón</button>
            </div>

            <div class="receipt-lines" data-receipt-lines>
                <div class="receipt-line" data-receipt-line>
                    <label class="receipt-variant-field">
                        <span>Artículo / variante *</span>
                        <select name="variant_id[]" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($variantOptions as $option): ?>
                                <option value="<?= e($option['id']) ?>">
                                    <?= e($option['category_name'] . ' · ' . $option['product_name'] . ' · ' . $option['variant_code'] . ($option['size_name'] ? ' · Talla ' . $option['size_name'] : '') . ($option['brand_name'] ? ' · ' . $option['brand_name'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Cantidad *</span>
                        <input type="number" name="quantity[]" min="0.001" step="0.001" required placeholder="0">
                    </label>

                    <label>
                        <span>Observación</span>
                        <input type="text" name="item_notes[]" maxlength="255" placeholder="Opcional">
                    </label>

                    <button class="receipt-remove" type="button" data-receipt-remove aria-label="Eliminar renglón">×</button>
                </div>
            </div>

            <template data-receipt-template>
                <div class="receipt-line" data-receipt-line>
                    <label class="receipt-variant-field">
                        <span>Artículo / variante *</span>
                        <select name="variant_id[]" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($variantOptions as $option): ?>
                                <option value="<?= e($option['id']) ?>">
                                    <?= e($option['category_name'] . ' · ' . $option['product_name'] . ' · ' . $option['variant_code'] . ($option['size_name'] ? ' · Talla ' . $option['size_name'] : '') . ($option['brand_name'] ? ' · ' . $option['brand_name'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Cantidad *</span>
                        <input type="number" name="quantity[]" min="0.001" step="0.001" required placeholder="0">
                    </label>

                    <label>
                        <span>Observación</span>
                        <input type="text" name="item_notes[]" maxlength="255" placeholder="Opcional">
                    </label>

                    <button class="receipt-remove" type="button" data-receipt-remove aria-label="Eliminar renglón">×</button>
                </div>
            </template>
        </section>

        <div class="receipt-form-actions">
            <a href="<?= e(sindesg_url('?module=receipts')) ?>">Cancelar</a>
            <button class="primary-button" type="submit">Guardar borrador</button>
        </div>
    </form>
<?php endif; ?>
