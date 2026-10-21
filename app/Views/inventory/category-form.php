<?php
declare(strict_types=1);
$editing = isset($category) && is_array($category);
?>
<section class="form-shell">
    <div class="form-copy">
        <span class="eyebrow">ADMINISTRACIÓN · CATÁLOGO MAESTRO</span>
        <h1><?= $editing ? 'Editar categoría' : 'Nueva categoría' ?></h1>
        <p><?= $editing
            ? 'Actualice el nombre, la descripción y el orden sin alterar el código institucional ni el historial de los artículos.'
            : 'Agrupe artículos por su naturaleza: camisas, botas, uniformes, equipos o materiales. No cree categorías para tallas.' ?></p>
        <p class="category-admin-note">Las operaciones administrativas quedan registradas en Auditoría. Los artículos y las existencias no se editan aquí.</p>
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=categories')) ?>">← Administrar categorías</a>
    </div>

    <form class="catalog-form" method="post" action="<?= e(sindesg_url(
        $editing ? '?module=inventory&action=update-category' : '?module=inventory&action=save-category'
    )) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
        <?php endif; ?>

        <label class="span-2">
            <span>Nombre *</span>
            <input type="text" name="name" maxlength="120" required
                   value="<?= e($editing ? (string) $category['name'] : '') ?>"
                   placeholder="Ej.: Uniformes">
        </label>

        <div class="auto-code-note span-2">
            <span class="auto-code-badge"><?= $editing ? 'FIJO' : 'AUTO' ?></span>
            <div>
                <strong><?= $editing ? e($category['code']) : 'Código de categoría automático' ?></strong>
                <small><?= $editing
                    ? 'Este código y su identificador interno son permanentes para conservar referencias y auditoría.'
                    : 'SINDESG generará CAT-000011, CAT-000012, etc. El usuario no puede modificarlo.' ?></small>
            </div>
        </div>

        <label class="span-2">
            <span>Descripción</span>
            <textarea name="description" rows="5" maxlength="2000"
                      placeholder="Descripción general"><?= e($editing ? (string) ($category['description'] ?? '') : '') ?></textarea>
        </label>

        <?php if ($editing): ?>
            <label class="span-2">
                <span>Orden de presentación</span>
                <input type="number" name="sort_order" min="0" max="1000000" required
                       value="<?= (int) $category['sort_order'] ?>">
                <small>Modifica solo el orden visual de las categorías; no el stock ni sus códigos.</small>
            </label>
            <div class="category-admin-note span-2">
                Estado: <strong><?= (int) $category['is_active'] === 1 ? 'Activa' : 'Archivada' ?></strong>
                · Referencias históricas: <?= (int) $category['all_product_count'] ?> artículos.
                Para archivar o restaurar utilice Administración de categorías.
            </div>
        <?php endif; ?>

        <div class="form-actions span-2">
            <a href="<?= e(sindesg_url('?module=inventory&action=categories')) ?>">Cancelar</a>
            <button class="primary-button" type="submit"><?= $editing ? 'Guardar cambios' : 'Crear categoría' ?></button>
        </div>
    </form>
</section>
