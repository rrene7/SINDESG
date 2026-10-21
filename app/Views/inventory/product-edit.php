<?php
declare(strict_types=1);
?>
<section class="form-shell">
    <div class="form-copy">
        <span class="eyebrow">ADMINISTRADOR · GOBIERNO DE ARTÍCULOS</span>
        <h1>Editar datos del artículo</h1>
        <p>Actualice la denominación y los datos descriptivos para distinguir artículos semejantes. Los identificadores, la categoría, las variantes y el kardex no se modifican aquí.</p>
        <div class="auto-code-note">
            <span class="auto-code-badge">FIJO</span>
            <div>
                <strong><?= e($product['internal_code'] ?: 'Sin código') ?></strong>
                <small>ID <?= (int)$product['id'] ?> · Categoría: <?= e($product['category_name']) ?>. El historial institucional permanece asociado.</small>
            </div>
        </div>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=duplicate-products')) ?>">Ver posibles duplicados</a>
    </div>
    <form class="catalog-form" method="post" action="<?= e(sindesg_url('?module=inventory&action=update-product')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <label class="span-2">
            <span>Nombre del artículo *</span>
            <input type="text" name="name" maxlength="180" required value="<?= e($product['name']) ?>">
        </label>
        <label>
            <span>Aplicación</span>
            <select name="sex">
                <?php foreach (['no_aplica'=>'No aplica','unisex'=>'Unisex','masculino'=>'Masculino','femenino'=>'Femenino'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $product['sex'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Color general</span>
            <input type="text" name="color" maxlength="80" value="<?= e((string)($product['color'] ?? '')) ?>">
        </label>
        <label class="span-2">
            <span>Descripción</span>
            <textarea name="description" rows="5" maxlength="4000"><?= e((string)($product['description'] ?? '')) ?></textarea>
        </label>
        <div class="category-admin-note span-2">
            El cambio quedará registrado en Auditoría con el valor anterior y nuevo.
            No retire artículos duplicados con existencias ni comprobantes: primero revise sus diferencias.
        </div>
        <div class="form-actions span-2">
            <a href="<?= e(sindesg_url('?module=inventory&action=product&id=' . (int)$product['id'])) ?>">Cancelar</a>
            <button class="primary-button" type="submit">Guardar datos descriptivos</button>
        </div>
    </form>
</section>
