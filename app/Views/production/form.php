<?php
declare(strict_types=1);
?>
<section class="production-hero compact">
    <div>
        <span class="eyebrow">NUEVA ORDEN</span>
        <h1>Registrar producción</h1>
        <p>Guarde primero como borrador. El inventario solo cambiará al confirmar la orden.</p>
    </div>
    <a class="secondary-button" href="<?= e(sindesg_url('?module=production')) ?>">← Volver</a>
</section>

<?php if ($variantOptions === []): ?>
    <section class="production-empty">
        <div class="production-empty-mark">!</div>
        <h2>Prepare el primer artículo producido</h2>
        <p>Registre el artículo que confeccionará Servicios Generales. SINDESG generará su primera variante interna aunque no tenga código de proveedor o ISTMO.</p>
        <a class="primary-button" href="<?= e(sindesg_url('?module=inventory&action=new-product&return_to=production')) ?>">+ Crear artículo para producción</a>
    </section>
<?php else: ?>
<form class="production-form" method="post" action="<?= e(sindesg_url('?module=production&action=save-draft')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

    <section class="production-panel">
        <div class="production-section-head">
            <div>
                <span class="eyebrow">ORDEN DE PRODUCCIÓN</span>
                <h2>Datos generales</h2>
            </div>
            <div class="generated-production-codes">
                <strong>Códigos automáticos</strong>
                <span>OPR-###### · PLT-######</span>
            </div>
        </div>

        <div class="production-form-grid">
            <label>
                <span>Fecha y hora *</span>
                <input type="datetime-local" name="produced_at" value="<?= e($defaultProducedAt) ?>" required>
            </label>
            <label>
                <span>Responsable *</span>
                <input type="text" name="responsible_label" maxlength="160" required placeholder="Responsable de la producción">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3" placeholder="Orden interna, taller, costurera, detalle del trabajo..."></textarea>
            </label>
        </div>
    </section>

    <section class="production-panel">
        <div class="production-section-head">
            <div>
                <span class="eyebrow">PRODUCTO TERMINADO</span>
                <h2>Qué se produjo</h2>
            </div>
            <div class="production-head-actions">
                <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=new-product&return_to=production')) ?>">+ Nuevo artículo</a>
                <button class="secondary-button" type="button" data-production-add="output">+ Agregar renglón</button>
            </div>
        </div>

        <div class="production-lines" data-production-lines="output">
            <div class="production-line" data-production-line>
                <label class="production-variant-field">
                    <span>Artículo / variante *</span>
                    <select name="output_variant_id[]" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($variantOptions as $option): ?>
                            <option value="<?= e($option['id']) ?>">
                                <?= e($option['category_name'] . ' · ' . $option['product_name'] . ($option['size_name'] ? ' · Talla ' . $option['size_name'] : '') . ($option['brand_name'] ? ' · ' . $option['brand_name'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Cantidad *</span><input type="number" name="output_quantity[]" min="0.001" step="0.001" required placeholder="0"></label>
                <label><span>Observación</span><input type="text" name="output_notes[]" maxlength="255" placeholder="Opcional"></label>
                <button class="production-remove" type="button" data-production-remove>×</button>
            </div>
        </div>
    </section>

    <section class="production-panel optional-inputs">
        <div class="production-section-head">
            <div>
                <span class="eyebrow">INSUMOS · OPCIONAL</span>
                <h2>Qué se consumió</h2>
                <p class="production-helper">Si todavía no se controla materia prima, puede dejar esta sección vacía.</p>
            </div>
            <button class="secondary-button" type="button" data-production-add="input">+ Agregar insumo</button>
        </div>

        <div class="production-lines" data-production-lines="input"></div>
    </section>

    <template data-production-template="output">
        <div class="production-line" data-production-line>
            <label class="production-variant-field">
                <span>Artículo / variante *</span>
                <select name="output_variant_id[]" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($variantOptions as $option): ?>
                        <option value="<?= e($option['id']) ?>"><?= e($option['category_name'] . ' · ' . $option['product_name'] . ($option['size_name'] ? ' · Talla ' . $option['size_name'] : '') . ($option['brand_name'] ? ' · ' . $option['brand_name'] : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Cantidad *</span><input type="number" name="output_quantity[]" min="0.001" step="0.001" required placeholder="0"></label>
            <label><span>Observación</span><input type="text" name="output_notes[]" maxlength="255" placeholder="Opcional"></label>
            <button class="production-remove" type="button" data-production-remove>×</button>
        </div>
    </template>

    <template data-production-template="input">
        <div class="production-line" data-production-line>
            <label class="production-variant-field">
                <span>Insumo / variante *</span>
                <select name="input_variant_id[]" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($variantOptions as $option): ?>
                        <option value="<?= e($option['id']) ?>">
                            <?= e($option['category_name'] . ' · ' . $option['product_name'] . ' · Stock central ' . sindesg_units((float) $option['central_stock'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Cantidad *</span><input type="number" name="input_quantity[]" min="0.001" step="0.001" required placeholder="0"></label>
            <label><span>Observación</span><input type="text" name="input_notes[]" maxlength="255" placeholder="Opcional"></label>
            <button class="production-remove" type="button" data-production-remove>×</button>
        </div>
    </template>

    <div class="production-form-actions">
        <a href="<?= e(sindesg_url('?module=production')) ?>">Cancelar</a>
        <button class="primary-button" type="submit">Guardar borrador</button>
    </div>
</form>
<?php endif; ?>
