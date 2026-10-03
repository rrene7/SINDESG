<?php
declare(strict_types=1);
?>
<section class="inventory-hero">
    <div>
        <span class="eyebrow">FASE 2B · INVENTARIO TRAZABLE</span>
        <h1>Inventario</h1>
        <p>Administra el catálogo y consulta existencias calculadas desde movimientos. El saldo no se edita manualmente.</p>
    </div>
    <div class="inventory-actions">
        <a class="secondary-button" href="<?= e(sindesg_url('?module=inventory&action=new-category')) ?>">+ Nueva categoría</a>
        <a class="primary-button" href="<?= e(sindesg_url('?module=inventory&action=new-product')) ?>">+ Nuevo artículo</a>
    </div>
</section>

<section class="catalog-stats">
    <article><span>Categorías</span><strong><?= e($catalogStats['categories']) ?></strong></article>
    <article><span>Artículos</span><strong><?= e($catalogStats['products']) ?></strong></article>
    <article><span>Variantes</span><strong><?= e($catalogStats['variants']) ?></strong></article>
    <article class="stock-stat">
        <span>Stock disponible</span>
        <strong><?= e(number_format((float) $inventoryStats['available_stock'], 0, '.', ',')) ?></strong>
        <small><?= e(number_format((float) $inventoryStats['total_stock'], 0, '.', ',')) ?> unidades institucionales</small>
    </article>
</section>

<section class="inventory-health-grid">
    <article>
        <span>Almacenes activos</span>
        <strong><?= e($inventoryStats['warehouses']) ?></strong>
        <small>Almacén Central habilitado</small>
    </article>
    <article>
        <span>Movimientos registrados</span>
        <strong><?= e($inventoryStats['movements']) ?></strong>
        <small>Entradas y salidas trazables</small>
    </article>
    <article>
        <span>Variantes con existencia</span>
        <strong><?= e($inventoryStats['variants_with_stock']) ?></strong>
        <small>Saldo mayor que cero</small>
    </article>
</section>

<section class="catalog-toolbar">
    <form method="get" action="<?= e(sindesg_url()) ?>">
        <input type="hidden" name="module" value="inventory">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($catalogSearch) ?>" placeholder="Nombre, código o categoría">
        </label>
        <label>
            <span>Categoría</span>
            <select name="category_id">
                <option value="">Todas</option>
                <?php foreach ($catalogCategories as $category): ?>
                    <option value="<?= e($category['id']) ?>" <?= (int) $selectedCategoryId === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?> (<?= e($category['product_count']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="filter-button" type="submit">Filtrar</button>
        <?php if ($catalogSearch !== '' || $selectedCategoryId): ?>
            <a class="clear-filter" href="<?= e(sindesg_url('?module=inventory')) ?>">Limpiar</a>
        <?php endif; ?>
    </form>
</section>

<section class="category-strip">
    <?php foreach ($catalogCategories as $category): ?>
        <a href="<?= e(sindesg_url('?module=inventory&category_id=' . (int) $category['id'])) ?>"
           class="<?= (int) $selectedCategoryId === (int) $category['id'] ? 'active' : '' ?>">
            <span><?= e($category['code']) ?></span>
            <strong><?= e($category['name']) ?></strong>
            <small><?= e($category['product_count']) ?> artículos</small>
        </a>
    <?php endforeach; ?>
</section>

<?php if ($products === []): ?>
    <section class="catalog-empty">
        <div class="catalog-empty-mark">+</div>
        <h2>No hay artículos para mostrar</h2>
        <p>Puede crear el primer artículo o cambiar los filtros aplicados.</p>
        <a class="primary-button" href="<?= e(sindesg_url('?module=inventory&action=new-product')) ?>">Crear artículo</a>
    </section>
<?php else: ?>
    <section class="product-grid">
        <?php foreach ($products as $product): ?>
            <a class="product-card" href="<?= e(sindesg_url('?module=inventory&action=product&id=' . (int) $product['id'])) ?>">
                <div class="product-card-image">
                    <?php if (!empty($product['primary_image'])): ?>
                        <img src="<?= e(sindesg_upload_url((string) $product['primary_image'])) ?>" alt="<?= e($product['name']) ?>">
                    <?php else: ?>
                        <span><?= e(substr((string) $product['category_code'], 0, 2)) ?></span>
                        <small>Sin fotografía</small>
                    <?php endif; ?>
                </div>
                <div class="product-card-body">
                    <span class="product-category"><?= e($product['category_name']) ?></span>
                    <h2><?= e($product['name']) ?></h2>
                    <code><?= e($product['internal_code']) ?></code>
                    <div class="product-stock-line">
                        <span>Disponible</span>
                        <strong><?= e(number_format((float) $product['stock_available'], 0, '.', ',')) ?></strong>
                    </div>
                    <div class="product-meta">
                        <span><?= e(number_format((float) $product['stock_total'], 0, '.', ',')) ?> institucionales</span>
                        <span><?= e($product['variant_count']) ?> variantes · <?= e(ucfirst((string) $product['sex'])) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
